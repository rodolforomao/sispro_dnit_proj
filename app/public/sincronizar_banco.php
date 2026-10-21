<?php
// ============================================================
// Tela de merge do banco — puxa a produção e mescla no DEV
// ⚠️ Rodar APENAS no XAMPP (dev)
//
// Atualiza a linha cuja chave já existe, insere a que só existe
// na produção e mantém a linha que só existe localmente.
// ============================================================
require_once 'config.php';

@ini_set('memory_limit', '1024M');
@set_time_limit(0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================
// ⚙️ CONFIGURAÇÕES
// ============================================================
$SYNC_URL   = 'http://10.100.11.235/sispro/api_exportar_banco.php';
$SYNC_TOKEN = 'kzseb1XXFRO1CWL5FAPQ3mJi5KBI5viG5OsPWwv2xTI';
$BACKUP_DIR = __DIR__ . '/backups';
$PREVIEW_FILE = $BACKUP_DIR . '/banco_sync_preview.json';
$META_FILE    = $BACKUP_DIR . '/banco_sync_meta.json';

$BLACKLIST = array('logs_auditoria');
$BLACKLIST_PATTERNS = array('/_bkp_/i', '/_backup_/i', '/_preview_/i');

if (!is_dir($BACKUP_DIR)) {
    @mkdir($BACKUP_DIR, 0755, true);
}

// ============================================================
// FUNÇÕES
// ============================================================
function sqlIdent($nome)
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', (string)$nome)) {
        throw new Exception('Identificador inválido: ' . $nome);
    }
    return '`' . $nome . '`';
}

function tabelaPermitida($nome, $blacklist, $patterns)
{
    if (in_array($nome, $blacklist, true)) {
        return false;
    }
    foreach ($patterns as $p) {
        if (preg_match($p, $nome)) {
            return false;
        }
    }
    return true;
}

function nomesTabelasInformados($csv)
{
    $out = array();
    foreach (explode(',', (string)$csv) as $nome) {
        $nome = trim($nome);
        if ($nome === '') {
            continue;
        }
        if (!preg_match('/^[A-Za-z0-9_]+$/', $nome)) {
            throw new Exception('Nome de tabela inválido: ' . $nome);
        }
        $out[] = $nome;
    }
    return $out;
}

function consultarApi($url, $token)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'X-Sync-Token: ' . $token,
        'Accept: application/json',
    ));
    curl_setopt($ch, CURLOPT_TIMEOUT, 600);
    curl_setopt($ch, CURLOPT_ENCODING, '');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    $resposta = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erro = curl_error($ch);
    curl_close($ch);

    if ($resposta === false) {
        throw new Exception('Falha na conexão: ' . $erro);
    }
    if (strncmp($resposta, "\x1f\x8b", 2) === 0) {
        $dec = gzdecode($resposta);
        if ($dec === false) {
            throw new Exception('Falha ao descompactar a resposta da API');
        }
        $resposta = $dec;
    }
    if ($httpCode !== 200) {
        throw new Exception('HTTP ' . $httpCode . ' — resposta: ' . substr($resposta, 0, 300));
    }
    return $resposta;
}

function checksumLinhas($linhas)
{
    $json = json_encode($linhas, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        throw new Exception('Falha ao calcular checksum: ' . json_last_error_msg());
    }
    return hash('sha256', $json);
}

function tabelaExiste($pdo, $nome)
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND TABLE_TYPE = \'BASE TABLE\''
    );
    $stmt->execute(array($nome));
    return (int)$stmt->fetchColumn() > 0;
}

function contarLinhas($pdo, $tabela)
{
    return (int)$pdo->query('SELECT COUNT(*) FROM ' . sqlIdent($tabela))->fetchColumn();
}

function colunasLocais($pdo, $tabela)
{
    $cols = array();
    $stmt = $pdo->query('SHOW COLUMNS FROM ' . sqlIdent($tabela));
    while ($c = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $cols[] = $c['Field'];
    }
    return $cols;
}

function temIndiceUnico($pdo, $tabela)
{
    $stmt = $pdo->query('SHOW INDEX FROM ' . sqlIdent($tabela));
    while ($idx = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ((int)$idx['Non_unique'] === 0) {
            return true;
        }
    }
    return false;
}

function ddlColuna($pdo, $c)
{
    $tipo = isset($c['tipo']) ? $c['tipo'] : '';
    if (!preg_match('/^[a-zA-Z0-9(),\s\'._-]+$/', $tipo)) {
        throw new Exception('Tipo de coluna inválido: ' . $tipo);
    }

    $sql = sqlIdent($c['nome']) . ' ' . $tipo;
    $notNull = strtoupper((string)$c['null']) === 'NO';
    $sql .= $notNull ? ' NOT NULL' : ' NULL';

    $extra = strtolower(trim((string)(isset($c['extra']) ? $c['extra'] : '')));
    $ehAuto = strpos($extra, 'auto_increment') !== false;

    if (!$ehAuto) {
        if ($c['default'] !== null) {
            $d = (string)$c['default'];
            if (preg_match('/^current_timestamp(\(\))?$/i', $d)) {
                $sql .= ' DEFAULT CURRENT_TIMESTAMP';
            } elseif (preg_match('/^-?\d+(\.\d+)?$/', $d)) {
                $sql .= ' DEFAULT ' . $d;
            } else {
                $sql .= ' DEFAULT ' . $pdo->quote($d);
            }
        } elseif (!$notNull) {
            $sql .= ' DEFAULT NULL';
        }
    }

    if ($extra !== '') {
        if (!preg_match('/^[a-z0-9_ ()]+$/', $extra)) {
            throw new Exception('Extra inválido na coluna ' . $c['nome']);
        }
        $sql .= ' ' . $extra;
    }
    return $sql;
}

function aplicarCreate($pdo, $createSql, $tabela)
{
    $sql = trim((string)$createSql);
    if (!preg_match('/^CREATE TABLE\s+/i', $sql)) {
        throw new Exception('DDL inesperada para ' . $tabela);
    }
    if (strpos($sql, ';') !== false) {
        throw new Exception('DDL com mais de um comando: ' . $tabela);
    }
    $pdo->exec($sql);
}

function backupTabela($pdo, $dir, $tabela)
{
    $path = $dir . '/' . $tabela . '.json';
    $fh = fopen($path, 'wb');
    if ($fh === false) {
        throw new Exception('Não foi possível gravar o backup de ' . $tabela);
    }

    try {
        $stmt = $pdo->query('SELECT * FROM ' . sqlIdent($tabela));
        fwrite($fh, "[\n");
        $first = true;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $json = json_encode($row, JSON_UNESCAPED_UNICODE);
            if ($json === false) {
                throw new Exception('Falha ao serializar backup de ' . $tabela);
            }
            if (!$first) {
                fwrite($fh, ",\n");
            }
            fwrite($fh, $json);
            $first = false;
        }
        fwrite($fh, "\n]");
    } catch (Exception $e) {
        fclose($fh);
        throw $e;
    }

    fclose($fh);
    return basename($path);
}

function chavesPrimarias($colunas)
{
    $pk = array();
    foreach ($colunas as $c) {
        if (isset($c['key']) && $c['key'] === 'PRI') {
            $pk[] = $c['nome'];
        }
    }
    return $pk;
}

function montarPlano($pdo, $dados, $blacklist, $patterns)
{
    $plano = array();
    $tabelas = isset($dados['tabelas']) && is_array($dados['tabelas']) ? $dados['tabelas'] : array();

    foreach ($tabelas as $nome => $info) {
        if (!tabelaPermitida($nome, $blacklist, $patterns)) {
            continue;
        }
        sqlIdent($nome);

        $existe = tabelaExiste($pdo, $nome);
        $locais = $existe ? contarLinhas($pdo, $nome) : null;
        $colunasNovas = array();
        $checksumOk = false;

        if (isset($info['linhas']) && is_array($info['linhas'])) {
            $checksumOk = isset($info['checksum']) && hash_equals(
                (string)$info['checksum'],
                checksumLinhas($info['linhas'])
            );
        }

        if ($existe && isset($info['colunas']) && is_array($info['colunas'])) {
            $jaTem = colunasLocais($pdo, $nome);
            foreach ($info['colunas'] as $c) {
                if (!in_array($c['nome'], $jaTem, true)) {
                    $colunasNovas[] = $c['nome'];
                }
            }
        }

        $plano[] = array(
            'tabela'         => $nome,
            'remotas'        => isset($info['total']) ? (int)$info['total'] : 0,
            'locais'         => $locais,
            'existe'         => $existe,
            'colunas_novas'  => $colunasNovas,
            'checksum_ok'    => $checksumOk,
            'auto_increment' => isset($info['auto_increment']) ? (int)$info['auto_increment'] : 0,
        );
    }

    return $plano;
}

function mesclarTabela($pdo, $nome, $info)
{
    $colunasMeta = isset($info['colunas']) && is_array($info['colunas']) ? $info['colunas'] : array();
    $linhas = isset($info['linhas']) && is_array($info['linhas']) ? $info['linhas'] : array();
    $pk = chavesPrimarias($colunasMeta);

    if (!isset($info['checksum']) || !hash_equals((string)$info['checksum'], checksumLinhas($linhas))) {
        throw new Exception('Checksum não confere. A prévia foi recarregada ou está incompleta.');
    }

    $criada = false;
    if (!tabelaExiste($pdo, $nome)) {
        if (empty($info['create_sql'])) {
            throw new Exception('Tabela não existe localmente e a API não enviou o CREATE TABLE.');
        }
        aplicarCreate($pdo, $info['create_sql'], $nome);
        $criada = true;
    }

    $locais = colunasLocais($pdo, $nome);
    $adicionadas = array();
    foreach ($colunasMeta as $c) {
        if (in_array($c['nome'], $locais, true)) {
            continue;
        }
        if (isset($c['key']) && $c['key'] === 'PRI') {
            throw new Exception('A chave ' . $c['nome'] . ' não existe nesta tabela. Ajuste o schema antes de mesclar.');
        }
        $pdo->exec('ALTER TABLE ' . sqlIdent($nome) . ' ADD COLUMN ' . ddlColuna($pdo, $c));
        $adicionadas[] = $c['nome'];
        $locais[] = $c['nome'];
    }

    if (empty($pk) && !temIndiceUnico($pdo, $nome)) {
        throw new Exception('Sem chave única. O merge duplicaria as linhas a cada execução.');
    }
    if (!temIndiceUnico($pdo, $nome)) {
        throw new Exception('A tabela local não tem chave única. O merge não consegue atualizar pelo id.');
    }

    $colunasInserir = array();
    foreach ($colunasMeta as $c) {
        if (in_array($c['nome'], $locais, true)) {
            $colunasInserir[] = $c['nome'];
        }
    }
    if (empty($colunasInserir)) {
        throw new Exception('Nenhuma coluna em comum para gravar.');
    }
    foreach ($pk as $colPk) {
        if (!in_array($colPk, $colunasInserir, true)) {
            throw new Exception('A chave ' . $colPk . ' não pôde ser gravada.');
        }
    }

    $resultado = array(
        'tabela'        => $nome,
        'criada'        => $criada,
        'colunas_novas' => $adicionadas,
        'processadas'   => 0,
        'ok'            => false,
        'erro'          => '',
    );

    if (!empty($linhas)) {
        $colsSql = array();
        $updates = array();
        foreach ($colunasInserir as $c) {
            $colsSql[] = sqlIdent($c);
            if (!in_array($c, $pk, true)) {
                $updates[] = sqlIdent($c) . ' = VALUES(' . sqlIdent($c) . ')';
            }
        }
        if (empty($updates)) {
            $updates[] = sqlIdent($pk[0]) . ' = ' . sqlIdent($pk[0]);
        }

        $sql = 'INSERT INTO ' . sqlIdent($nome)
            . ' (' . implode(',', $colsSql) . ') VALUES ('
            . implode(',', array_fill(0, count($colunasInserir), '?'))
            . ') ON DUPLICATE KEY UPDATE ' . implode(',', $updates);

        $stmt = $pdo->prepare($sql);
        $pdo->beginTransaction();
        try {
            foreach ($linhas as $linha) {
                if (!is_array($linha)) {
                    throw new Exception('Linha inválida no payload.');
                }
                $vals = array();
                foreach ($colunasInserir as $c) {
                    $vals[] = array_key_exists($c, $linha) ? $linha[$c] : null;
                }
                $stmt->execute($vals);
                $resultado['processadas']++;
            }
            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    $ai = isset($info['auto_increment']) ? (int)$info['auto_increment'] : 0;
    if ($ai > 1) {
        $pdo->exec('ALTER TABLE ' . sqlIdent($nome) . ' AUTO_INCREMENT = ' . $ai);
    }

    $resultado['ok'] = true;
    $resultado['locais_depois'] = contarLinhas($pdo, $nome);
    return $resultado;
}

// ============================================================
// AÇÕES
// ============================================================
$mensagem     = '';
$tipoMensagem = '';
$meta         = null;
$resultado    = null;

if (file_exists($META_FILE)) {
    $metaLida = json_decode((string)file_get_contents($META_FILE), true);
    if (is_array($metaLida)) {
        $meta = $metaLida;
    }
}

$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($action === 'preview') {
    try {
        $filtro = nomesTabelasInformados(isset($_POST['tables']) ? $_POST['tables'] : '');
        $url = $SYNC_URL . '?gzip=1';
        if (!empty($filtro)) {
            $url .= '&tables=' . rawurlencode(implode(',', $filtro));
        }

        $json = consultarApi($url, $SYNC_TOKEN);
        $dados = json_decode($json, true);
        if (!is_array($dados) || empty($dados['success'])) {
            $trecho = substr($json, 0, 300);
            throw new Exception('Resposta inválida: ' . $trecho);
        }
        if (!isset($dados['tabelas']) || !is_array($dados['tabelas'])) {
            throw new Exception('A API não devolveu a lista de tabelas.');
        }

        $plano = montarPlano($pdo, $dados, $BLACKLIST, $BLACKLIST_PATTERNS);

        if (file_put_contents($PREVIEW_FILE, $json) === false) {
            throw new Exception('Não foi possível gravar a prévia.');
        }

        $meta = array(
            'gerado_em'     => isset($dados['gerado_em']) ? $dados['gerado_em'] : '',
            'banco'         => isset($dados['banco']) ? $dados['banco'] : '',
            'mysql_version' => isset($dados['mysql_version']) ? $dados['mysql_version'] : '',
            'versao_api'    => isset($dados['versao_api']) ? $dados['versao_api'] : '',
            'filtro'        => $filtro,
            'excluidas'     => isset($dados['excluidas']) && is_array($dados['excluidas']) ? array_values($dados['excluidas']) : array(),
            'plano'         => $plano,
            'salvo_em'      => date('c'),
        );
        file_put_contents($META_FILE, json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $mensagem = 'Prévia carregada: ' . count($plano) . ' tabelas recebidas da produção.';
        $tipoMensagem = 'success';
    } catch (Exception $e) {
        $mensagem = 'Erro na prévia: ' . $e->getMessage();
        $tipoMensagem = 'danger';
    }
}

if ($action === 'merge') {
    $modeAnterior = null;
    try {
        if (!file_exists($PREVIEW_FILE)) {
            throw new Exception('Nenhuma prévia encontrada. Busque de novo.');
        }
        $conteudo = file_get_contents($PREVIEW_FILE);
        if ($conteudo === false || trim($conteudo) === '') {
            throw new Exception('Arquivo de prévia está vazio. Busque de novo.');
        }
        $dados = json_decode($conteudo, true);
        if (!is_array($dados) || empty($dados['success']) || empty($dados['tabelas']) || !is_array($dados['tabelas'])) {
            throw new Exception('Arquivo de prévia está inválido. Busque de novo.');
        }

        $ordem = isset($dados['ordem']) && is_array($dados['ordem'])
            ? $dados['ordem']
            : array_keys($dados['tabelas']);

        $dirBackup = $BACKUP_DIR . '/banco_merge_' . date('Y-m-d_His');
        if (!mkdir($dirBackup, 0755, true) && !is_dir($dirBackup)) {
            throw new Exception('Não foi possível criar a pasta de backup.');
        }

        foreach ($ordem as $nome) {
            if (!isset($dados['tabelas'][$nome]) || !tabelaPermitida($nome, $BLACKLIST, $BLACKLIST_PATTERNS)) {
                continue;
            }
            if (tabelaExiste($pdo, $nome)) {
                backupTabela($pdo, $dirBackup, $nome);
            }
        }

        $modeAnterior = $pdo->query('SELECT @@SESSION.sql_mode')->fetchColumn();
        $pdo->exec('SET SESSION sql_mode = \'\'');
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        $porTabela = array();
        foreach ($ordem as $nome) {
            if (!isset($dados['tabelas'][$nome])) {
                continue;
            }
            if (!tabelaPermitida($nome, $BLACKLIST, $BLACKLIST_PATTERNS)) {
                $porTabela[] = array(
                    'tabela' => $nome,
                    'ok'     => false,
                    'erro'   => 'Tabela bloqueada. Não entra no merge.',
                );
                continue;
            }

            try {
                $porTabela[] = mesclarTabela($pdo, $nome, $dados['tabelas'][$nome]);
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $porTabela[] = array(
                    'tabela'      => $nome,
                    'ok'          => false,
                    'erro'        => $e->getMessage(),
                    'processadas' => 0,
                );
            }
        }

        $ok = 0;
        $falhas = 0;
        foreach ($porTabela as $item) {
            if (!empty($item['ok'])) {
                $ok++;
            } else {
                $falhas++;
            }
        }

        $resultado = array(
            'por_tabela' => $porTabela,
            'ok'         => $ok,
            'falhas'     => $falhas,
            'backup'     => basename($dirBackup),
        );

        if ($falhas === 0) {
            $mensagem = 'Merge concluído. ' . $ok . ' tabelas gravadas. Linhas que só existem aqui foram mantidas.';
            $tipoMensagem = 'success';
        } else {
            $mensagem = 'Merge parcial: ' . $ok . ' tabelas gravadas, ' . $falhas . ' com erro. Pode buscar a prévia e rodar de novo — o que já entrou não é duplicado.';
            $tipoMensagem = 'warning';
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $mensagem = 'Erro no merge: ' . $e->getMessage();
        $tipoMensagem = 'danger';
    }

    if ($modeAnterior !== null) {
        $pdo->exec('SET SESSION sql_mode = ' . $pdo->quote((string)$modeAnterior));
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
}

$filtroAtual = '';
if ($action === 'preview' && isset($_POST['tables'])) {
    $filtroAtual = (string)$_POST['tables'];
} elseif (is_array($meta) && !empty($meta['filtro']) && is_array($meta['filtro'])) {
    $filtroAtual = implode(',', $meta['filtro']);
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Mesclar banco - DEV</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fc; padding: 40px 20px; }
        .container { max-width: 1100px; }
        .card { border: none; border-radius: 16px; box-shadow: 0 8px 30px rgba(0,0,0,0.08); padding: 30px; }
        .aviso { background: #fff3cd; border-left: 5px solid #ffc107; padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <h3><i class="bi bi-intersect"></i> Mesclar banco (Produção → Dev)</h3>

        <div class="aviso">
            <strong>Ambiente de desenvolvimento.</strong><br>
            Esta ferramenta <strong>não apaga</strong> linha que só existe no banco local.
            Quando a chave já existe, a linha fica com os valores da produção.
            Quando a chave só existe na produção, a linha é inserida.
            <code>logs_auditoria</code> não entra. Um backup é gravado em <code>backups/</code> antes de escrever.
        </div>

        <?php if ($mensagem): ?>
            <div class="alert alert-<?= htmlspecialchars($tipoMensagem) ?>"><?= htmlspecialchars($mensagem) ?></div>
        <?php endif; ?>

        <?php if (file_exists($PREVIEW_FILE)): ?>
            <div class="alert alert-secondary small">
                <i class="bi bi-file-earmark-text"></i>
                Prévia em <code>backups/banco_sync_preview.json</code>
                (modificada em <?= date('d/m/Y H:i:s', filemtime($PREVIEW_FILE)) ?>)
            </div>
        <?php else: ?>
            <div class="alert alert-secondary small">
                <i class="bi bi-file-earmark-x"></i>
                Nenhuma prévia salva. Busque a produção antes de mesclar.
            </div>
        <?php endif; ?>

        <form method="POST" class="mb-4">
            <input type="hidden" name="action" value="preview">
            <label class="form-label" for="tables">Tabelas (opcional)</label>
            <input class="form-control mb-2" id="tables" name="tables" value="<?= htmlspecialchars($filtroAtual) ?>" placeholder="vazio = banco inteiro. Ex.: processos,contratos_rdci">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-search"></i> 1. Buscar prévia da produção
            </button>
        </form>

        <?php if (is_array($meta) && !empty($meta['plano'])): ?>
            <div class="mb-3">
                <h5>Prévia</h5>
                <p class="text-muted small mb-2">
                    Banco <code><?= htmlspecialchars((string)$meta['banco']) ?></code>
                    · MySQL <?= htmlspecialchars((string)$meta['mysql_version']) ?>
                    · API <?= htmlspecialchars((string)$meta['versao_api']) ?>
                    · gerado em <?= htmlspecialchars((string)$meta['gerado_em']) ?>
                </p>
                <div class="table-responsive" style="max-height: 420px;">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Tabela</th>
                                <th class="text-end">Produção</th>
                                <th class="text-end">Local agora</th>
                                <th>No merge</th>
                                <th>Checksum</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($meta['plano'] as $item): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($item['tabela']) ?></code></td>
                                <td class="text-end"><?= (int)$item['remotas'] ?></td>
                                <td class="text-end"><?= $item['locais'] === null ? '—' : (int)$item['locais'] ?></td>
                                <td>
                                    <?php if (empty($item['existe'])): ?>
                                        criar tabela
                                    <?php elseif (!empty($item['colunas_novas'])): ?>
                                        acrescentar <?= htmlspecialchars(implode(', ', $item['colunas_novas'])) ?>
                                    <?php else: ?>
                                        atualizar e inserir
                                    <?php endif; ?>
                                </td>
                                <td><?= !empty($item['checksum_ok']) ? 'ok' : 'divergente' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (!empty($meta['excluidas'])): ?>
                    <p class="text-muted small">
                        Fora desta carga:
                        <?php
                        $excluidas = array();
                        foreach ($meta['excluidas'] as $exc) {
                            $excluidas[] = htmlspecialchars((string)$exc);
                        }
                        echo implode(', ', $excluidas);
                        ?>
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (file_exists($PREVIEW_FILE)): ?>
            <form method="POST" onsubmit="if (!confirm('Mesclar a prévia no banco local? Linhas que só existem aqui permanecem. Linhas com a mesma chave passam a valer o que veio da produção.')) return false; this.querySelector('button[type=submit]').disabled = true;">
                <input type="hidden" name="action" value="merge">
                <button type="submit" class="btn btn-danger">
                    <i class="bi bi-intersect"></i> 2. Confirmar e mesclar
                </button>
            </form>
        <?php endif; ?>

        <?php if ($resultado): ?>
            <hr>
            <h5>Resultado</h5>
            <p>
                <strong><?= (int)$resultado['ok'] ?></strong> tabelas gravadas,
                <strong><?= (int)$resultado['falhas'] ?></strong> com erro.<br>
                Backup: <code>backups/<?= htmlspecialchars($resultado['backup']) ?></code>
            </p>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Tabela</th>
                            <th>Situação</th>
                            <th class="text-end">Linhas da produção</th>
                            <th class="text-end">Local depois</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($resultado['por_tabela'] as $item): ?>
                        <tr class="<?= !empty($item['ok']) ? '' : 'table-warning' ?>">
                            <td><code><?= htmlspecialchars($item['tabela']) ?></code></td>
                            <td>
                                <?php if (!empty($item['ok'])): ?>
                                    <?php if (!empty($item['criada'])): ?>criada · <?php endif; ?>
                                    <?php if (!empty($item['colunas_novas'])): ?>
                                        colunas <?= htmlspecialchars(implode(', ', $item['colunas_novas'])) ?> ·
                                    <?php endif; ?>
                                    mesclada
                                <?php else: ?>
                                    <?= htmlspecialchars(isset($item['erro']) ? $item['erro'] : 'erro') ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-end"><?= isset($item['processadas']) ? (int)$item['processadas'] : 0 ?></td>
                            <td class="text-end"><?= isset($item['locais_depois']) ? (int)$item['locais_depois'] : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
