<?php
// ============================================================
// Tela de sincronização COMPLETA do banco (Produção → Dev)
// ⚠️ Rodar APENAS no seu XAMPP
// ============================================================
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

@ini_set('memory_limit', '512M');
@set_time_limit(300);

// ============================================================
// ⚙️ CONFIGURAÇÕES
// ============================================================
$SYNC_URL   = 'http://10.100.11.235/sispro/api_exportar_banco.php';
$SYNC_TOKEN = 'kzseb1XXFRO1CWL5FAPQ3mJi5KBI5viG5OsPWwv2xTI';
$BACKUP_DIR = __DIR__ . '/backups';
$PREVIEW_FILE = $BACKUP_DIR . '/banco_sync_preview.json';
$META_FILE    = $BACKUP_DIR . '/banco_sync_meta.json';

$PREVIEW_FILE = $BACKUP_DIR . '/sync_banco_preview.json';

if (!is_dir($BACKUP_DIR)) @mkdir($BACKUP_DIR, 0755, true);

// ============================================================
// FUNÇÕES AUXILIARES
// ============================================================
function consultarApi($url, $token) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-Sync-Token: ' . $token,
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erro = curl_error($ch);
    curl_close($ch);

    if ($resp === false) throw new Exception("Falha na conexão: $erro");
    if ($http !== 200)   throw new Exception("HTTP $http — " . substr($resp, 0, 300));
    return $resp;
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

function tabelaExiste(PDO $pdo, string $t): bool {
    $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
    $stmt->execute([$t]);
    return (bool)$stmt->fetchColumn();
    }
    return hash('sha256', $json);
}

function colunasLocais(PDO $pdo, string $t): array {
    if (!tabelaExiste($pdo, $t)) return [];
    return $pdo->query("SHOW COLUMNS FROM `$t`")->fetchAll(PDO::FETCH_COLUMN);
}

// ============================================================
// AÇÕES
// ============================================================
$mensagem = ''; $tipo = ''; $preview = null; $resultado = null;
$action = $_POST['action'] ?? '';

// ---- PREVIEW ----
if ($action === 'preview') {
    try {
        $json  = consultarApi($SYNC_URL, $SYNC_TOKEN);
        $dados = json_decode($json, true);

        if (!is_array($dados) || empty($dados['success'])) {
            throw new Exception("Resposta inválida: " . substr($json, 0, 300));
        }
    }
    return false;
}

        file_put_contents($PREVIEW_FILE, json_encode($dados, JSON_UNESCAPED_UNICODE));
        $preview = $dados;

        $mensagem = "Prévia carregada: {$dados['total_tabelas']} tabelas recebidas. "
                  . "Total de linhas: " . array_sum(array_column($dados['resumo'], 'linhas')) . ".";
        $tipo = 'success';

    } catch (Exception $e) {
        $mensagem = "Erro na prévia: " . $e->getMessage();
        $tipo = 'danger';
            }
        } elseif (!$notNull) {
            $sql .= ' DEFAULT NULL';
        }
    }

// ---- IMPORT ----
if ($action === 'import') {
    try {
        if (!file_exists($PREVIEW_FILE)) {
            throw new Exception("Nenhuma prévia encontrada. Faça a busca novamente.");
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

        $dados = json_decode(file_get_contents($PREVIEW_FILE), true);
        if (!is_array($dados) || empty($dados['success'])) {
            throw new Exception("Prévia inválida. Faça a busca novamente.");
}

        $selecionadas = $_POST['tabelas'] ?? [];
        if (empty($selecionadas)) {
            throw new Exception("Nenhuma tabela selecionada para sincronizar.");
        }
    }
    return $pk;
}

        $ordem = $dados['ordem'] ?? array_keys($dados['tabelas']);
        // Filtra a ordem pelas tabelas selecionadas
        $ordem = array_values(array_filter($ordem, fn($t) => in_array($t, $selecionadas, true)));

        // ---- Backup do estado atual (por tabela, em pasta datada) ----
        $stamp      = date('Y-m-d_His');
        $backupDir  = $BACKUP_DIR . '/banco_backup_' . $stamp;
        if (!is_dir($backupDir)) @mkdir($backupDir, 0755, true);

        foreach ($ordem as $t) {
            if (!tabelaExiste($pdo, $t)) continue;
            $linhas = $pdo->query("SELECT * FROM `$t`")->fetchAll(PDO::FETCH_ASSOC);
            file_put_contents(
                $backupDir . '/' . $t . '.json',
                json_encode($linhas, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            );
        }

        // ---- Transação ----
        $pdo->beginTransaction();
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

        $relatorio = [];

        foreach ($ordem as $t) {
            $info = $dados['tabelas'][$t] ?? null;
            if (!$info) continue;

            $colsProd = array_column($info['colunas'], 'nome');
            $linhas   = $info['linhas'] ?? [];

            // ---- Cria a tabela se não existir localmente ----
    $criada = false;
            if (!tabelaExiste($pdo, $t)) {
                if (!empty($info['create_sql'])) {
                    $pdo->exec($info['create_sql']);
        $criada = true;
                } else {
                    $relatorio[] = "$t: sem CREATE TABLE, ignorada.";
            continue;
        }
        if (isset($c['key']) && $c['key'] === 'PRI') {
            throw new Exception('A chave ' . $c['nome'] . ' não existe nesta tabela. Ajuste o schema antes de mesclar.');
        }
        $pdo->exec('ALTER TABLE ' . sqlIdent($nome) . ' ADD COLUMN ' . ddlColuna($pdo, $c));
        $adicionadas[] = $c['nome'];
        $locais[] = $c['nome'];
    }

            $colsDev = colunasLocais($pdo, $t);
            $cols    = array_values(array_intersect($colsProd, $colsDev));

            $faltandoLocal = array_values(array_diff($colsProd, $colsDev));
            $sobrandoLocal = array_values(array_diff($colsDev, $colsProd));

            // ---- Limpa tabela local ----
            $pdo->exec("DELETE FROM `$t`");

            // ---- Insere linhas ----
            $inseridas = 0;
            if (!empty($linhas) && !empty($cols)) {
                $placeholders = '(' . implode(',', array_fill(0, count($cols), '?')) . ')';
                $sql = "INSERT INTO `$t` (`" . implode('`,`', $cols) . "`) VALUES $placeholders";
                $stmt = $pdo->prepare($sql);

        $stmt = $pdo->prepare($sql);
        $pdo->beginTransaction();
        try {
            foreach ($linhas as $linha) {
                    $vals = [];
                    foreach ($cols as $c) {
                        $vals[] = $linha[$c] ?? null;
                }
                $stmt->execute($vals);
                    $inseridas++;
        }

        $ordem = isset($dados['ordem']) && is_array($dados['ordem'])
            ? $dados['ordem']
            : array_keys($dados['tabelas']);

        $dirBackup = $BACKUP_DIR . '/banco_merge_' . date('Y-m-d_His');
        if (!mkdir($dirBackup, 0755, true) && !is_dir($dirBackup)) {
            throw new Exception('Não foi possível criar a pasta de backup.');
        }

            $relatorio[] = [
                'tabela'         => $t,
                'criada'         => $criada,
                'inseridas'      => $inseridas,
                'faltando_local' => $faltandoLocal,
                'sobrando_local' => $sobrandoLocal,
            ];
        }

        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        $pdo->commit();

        @unlink($PREVIEW_FILE);

        $resultado = [
            'relatorio'   => $relatorio,
            'backupDir'   => basename($backupDir),
        ];

        $total = array_sum(array_column($relatorio, 'inseridas'));
        $mensagem = "Sincronização concluída! {$total} linhas inseridas em " . count($relatorio) . " tabelas.";
        $tipo = 'success';

        if ($falhas === 0) {
            $mensagem = 'Merge concluído. ' . $ok . ' tabelas gravadas. Linhas que só existem aqui foram mantidas.';
            $tipoMensagem = 'success';
        } else {
            $mensagem = 'Merge parcial: ' . $ok . ' tabelas gravadas, ' . $falhas . ' com erro. Pode buscar a prévia e rodar de novo — o que já entrou não é duplicado.';
            $tipoMensagem = 'warning';
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            try { $pdo->exec("SET FOREIGN_KEY_CHECKS = 1"); } catch (Exception $x) {}
            $pdo->rollBack();
        }
        $mensagem = "Erro na importação: " . $e->getMessage();
        $tipo = 'danger';
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
    <title>Sincronizar Banco Completo - DEV</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background:#f8f9fc; padding:40px 20px; }
        .container { max-width:1100px; }
        .card { border:none; border-radius:16px; box-shadow:0 8px 30px rgba(0,0,0,0.08); padding:30px; }
        .aviso { background:#fff3cd; border-left:5px solid #ffc107; padding:15px 20px; border-radius:8px; margin-bottom:20px; }
        .diag-box { background:#f1f5fb; border-radius:12px; padding:20px; margin-bottom:20px; }
        code { font-size:0.85em; }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <h3><i class="bi bi-database-down"></i> Sincronizar Banco Completo (Produção → Dev)</h3>

        <div class="aviso">
            <strong>⚠️ Isto substitui dados de várias tabelas locais.</strong><br>
            Um backup por tabela é salvo em <code>backups/banco_backup_&lt;data&gt;/</code> antes.
        </div>

        <?php if ($mensagem): ?>
            <div class="alert alert-<?= $tipo ?>"><?= htmlspecialchars($mensagem) ?></div>
        <?php endif; ?>

        <?php if (file_exists($PREVIEW_FILE)): ?>
            <div class="alert alert-secondary small">
                <i class="bi bi-file-earmark-text"></i>
                Prévia salva em <code>backups/sync_banco_preview.json</code>
                (<?= date('d/m/Y H:i:s', filemtime($PREVIEW_FILE)) ?>)
            </div>
        <?php endif; ?>

        <form method="POST" class="mb-3">
            <input type="hidden" name="action" value="preview">
            <button class="btn btn-primary"><i class="bi bi-search"></i> 1. Buscar prévia do banco</button>
        </form>

        <?php if ($preview): ?>
            <div class="diag-box">
                <h5>📦 Tabelas recebidas (<?= $preview['total_tabelas'] ?>)</h5>
                <?php if (!empty($preview['excluidas'])): ?>
                <p class="text-muted small mb-2">
                        Excluídas pela API: <code><?= htmlspecialchars(implode(', ', $preview['excluidas'])) ?></code>
                </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (file_exists($PREVIEW_FILE)):
            $dadosPreview = json_decode(file_get_contents($PREVIEW_FILE), true);
            if (!empty($dadosPreview['resumo'])):
        ?>
            <form method="POST" onsubmit="return confirm('Confirma substituir os dados das tabelas marcadas?')">
                <input type="hidden" name="action" value="import">

                <h5>2. Selecione as tabelas a sincronizar</h5>

                <div class="mb-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAll(true)">Marcar todas</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAll(false)">Desmarcar todas</button>
                </div>

                <div class="table-responsive mb-3" style="max-height:420px; border:1px solid #dee2e6; border-radius:8px;">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead class="table-light" style="position:sticky; top:0;">
                            <tr>
                                <th style="width:40px;"></th>
                                <th>Tabela</th>
                                <th class="text-end">Linhas (produção)</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($dadosPreview['resumo'] as $r): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($item['tabela']) ?></code></td>
                                <td class="text-end"><?= (int)$item['remotas'] ?></td>
                                <td class="text-end"><?= $item['locais'] === null ? '—' : (int)$item['locais'] ?></td>
                                <td>
                                    <input class="form-check-input chk-tab" type="checkbox"
                                           name="tabelas[]"
                                           value="<?= htmlspecialchars($r['tabela']) ?>" checked>
                                </td>
                                <td><code><?= htmlspecialchars($r['tabela']) ?></code></td>
                                <td class="text-end"><?= (int)$r['linhas'] ?></td>
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
                    <i class="bi bi-arrow-repeat"></i> 3. Confirmar e importar
                </button>
            </form>

            <script>
            function toggleAll(v) {
                document.querySelectorAll('.chk-tab').forEach(c => c.checked = v);
            }
            </script>
        <?php endif; endif; ?>

        <?php if ($resultado): ?>
            <hr>
            <h5>Resultado</h5>
            <p>Backup: <code>backups/<?= htmlspecialchars($resultado['backupDir']) ?>/</code></p>

                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Tabela</th>
                        <th class="text-center">Criada?</th>
                        <th class="text-end">Inseridas</th>
                        <th>Colunas faltando local</th>
                        <th>Colunas sobrando local</th>
                        </tr>
                    </thead>
                    <tbody>
                <?php foreach ($resultado['relatorio'] as $r): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($r['tabela']) ?></code></td>
                        <td class="text-center"><?= $r['criada'] ? '✔' : '—' ?></td>
                        <td class="text-end"><?= (int)$r['inseridas'] ?></td>
                            <td>
                            <?php if (!empty($r['faltando_local'])): ?>
                                <span class="badge bg-warning text-dark"><?= htmlspecialchars(implode(', ', $r['faltando_local'])) ?></span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                                    <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($r['sobrando_local'])): ?>
                                <span class="badge bg-info text-dark"><?= htmlspecialchars(implode(', ', $r['sobrando_local'])) ?></span>
                                <?php else: ?>
                                <span class="text-muted">—</span>
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
