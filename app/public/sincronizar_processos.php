<?php
// ============================================================
// Tela de sincronização — puxa processos de PRODUÇÃO via API
// ⚠️ Rodar APENAS no seu XAMPP (dev)
// ============================================================
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================
// ⚙️ CONFIGURAÇÕES
// ============================================================
$SYNC_URL   = 'http://10.100.11.235/sispro/api_exportar_processos.php';
$SYNC_TOKEN = 'kzseb1XXFRO1CWL5FAPQ3mJi5KBI5viG5OsPWwv2xTI';
$BACKUP_DIR = __DIR__ . '/backups';

// ✅ Preview salvo em ARQUIVO (não em sessão) — mais confiável
$PREVIEW_FILE = $BACKUP_DIR . '/sync_preview.json';

// Garante que o diretório existe desde o início
if (!is_dir($BACKUP_DIR)) {
    @mkdir($BACKUP_DIR, 0755, true);
}

// ============================================================
// FUNÇÕES AUXILIARES
// ============================================================
function obterOuCriarId($pdo, $tabela, $nome) {
    $tabelasPermitidas = ['equipes', 'responsaveis', 'status_processo', 'tipos'];
    if (!in_array($tabela, $tabelasPermitidas, true)) {
        throw new Exception("Tabela não permitida: $tabela");
    }
    if ($nome === null || trim($nome) === '') return null;
    $nome = trim($nome);

    $stmt = $pdo->prepare("SELECT id FROM $tabela WHERE LOWER(TRIM(nome)) = LOWER(?) LIMIT 1");
    $stmt->execute([$nome]);
    $id = $stmt->fetchColumn();
    if ($id) return (int)$id;

    $stmt = $pdo->prepare("INSERT INTO $tabela (nome) VALUES (?)");
    $stmt->execute([$nome]);
    return (int)$pdo->lastInsertId();
}

/**
 * Resolve o id do contrato a partir do instrumento (ex.: "00 00375/2024").
 *
 * Estratégia:
 *   1. Tenta em `contratos_rdci` (fonte primária — é onde a maioria está)
 *   2. Se não achar, tenta em `contratos` (tabela legada, resolve casos órfãos)
 *   3. Fallback: normaliza zeros à esquerda em ambas
 *
 * Trata "-", "N/A", "NA", "NÃO", "NAO", "NENHUM" como "sem contrato".
 */
function obterContratoId($pdo, $instrumento) {
    if (empty($instrumento)) return null;
    $instrumento = strtoupper(trim($instrumento));

    // "-" e variações significam "sem contrato"
    if (in_array($instrumento, ['-', 'N/A', 'NA', 'NÃO', 'NAO', 'NENHUM'], true)) {
        return null;
    }

    // ---- Tentativa 1: contratos_rdci (exato) ----
    $stmt = $pdo->prepare("SELECT MIN(id) FROM contratos_rdci WHERE UPPER(TRIM(instrumento)) = ?");
    $stmt->execute([$instrumento]);
    $id = $stmt->fetchColumn();
    if ($id) return (int)$id;

    // ---- Tentativa 2: contratos (exato) ----
    $stmt = $pdo->prepare("SELECT MIN(id) FROM contratos WHERE UPPER(TRIM(numero)) = ?");
    $stmt->execute([$instrumento]);
    $id = $stmt->fetchColumn();
    if ($id) return (int)$id;

    // ---- Fallback: normaliza "00 00375/2024" -> "00375/2024" ----
    if (preg_match('/^0+(\d+\/\d+)$/', $instrumento, $m)) {
        // contratos_rdci
        $stmt = $pdo->prepare("SELECT MIN(id) FROM contratos_rdci WHERE UPPER(TRIM(instrumento)) = ?");
        $stmt->execute([$m[1]]);
        $id = $stmt->fetchColumn();
        if ($id) return (int)$id;

        // contratos
        $stmt = $pdo->prepare("SELECT MIN(id) FROM contratos WHERE UPPER(TRIM(numero)) = ?");
        $stmt->execute([$m[1]]);
        $id = $stmt->fetchColumn();
        if ($id) return (int)$id;
    }

    return null;
}

function consultarApi($url, $token) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-Sync-Token: ' . $token,
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    $resposta = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erro = curl_error($ch);
    curl_close($ch);

    if ($resposta === false) {
        throw new Exception("Falha na conexão: $erro");
    }
    if ($httpCode !== 200) {
        throw new Exception("HTTP $httpCode — resposta: " . substr($resposta, 0, 300));
    }
    return $resposta;
}

// ============================================================
// AÇÕES
// ============================================================
$mensagem     = '';
$tipoMensagem = '';
$preview      = null;
$diagnostico  = null;
$resultado    = null;

$action = $_POST['action'] ?? '';

// ---- PREVIEW ----
if ($action === 'preview') {
    try {
        $json  = consultarApi($SYNC_URL, $SYNC_TOKEN);
        $dados = json_decode($json, true);

        if (!is_array($dados) || empty($dados['success'])) {
            throw new Exception("Resposta inválida: " . substr($json, 0, 300));
        }

        // ✅ Salva em ARQUIVO (não em sessão)
        file_put_contents(
            $PREVIEW_FILE,
            json_encode($dados, JSON_UNESCAPED_UNICODE)
        );

        $preview = $dados;

        // ============================================================
        // DIAGNÓSTICO DA PRÉVIA
        // ============================================================
        $diag = [
            'total_api'            => count($dados['processos']),
            'com_instrumento'      => 0,
            'instrumento_vazio'    => 0,
            'instrumento_nao_casa' => 0,
            'instrumento_casa'     => 0,
            'por_tipo'             => [],
        ];
        $exemplos_nao_casa = [];
        $exemplos_vazio    = [];

        foreach ($dados['processos'] as $p) {
            $instr = trim((string)($p['contrato_instrumento'] ?? ''));
            $tipo  = $p['tipo_nome'] ?? '(sem tipo)';
            $diag['por_tipo'][$tipo] = ($diag['por_tipo'][$tipo] ?? 0) + 1;

            if ($instr === '' || $instr === '-' || $instr === null) {
                $diag['instrumento_vazio']++;
                if (count($exemplos_vazio) < 5) {
                    $exemplos_vazio[] = [
                        'id'    => $p['origem_id'] ?? null,
                        'num'   => $p['numero_processo'] ?? '',
                        'tipo'  => $tipo,
                        'valor' => $instr,
                    ];
                }
                continue;
            }

            $diag['com_instrumento']++;

            // Usa a mesma lógica do obterContratoId (testa as duas tabelas)
            $achou = obterContratoId($pdo, $instr);

            if ($achou) {
                $diag['instrumento_casa']++;
            } else {
                $diag['instrumento_nao_casa']++;
                if (count($exemplos_nao_casa) < 10) {
                    $exemplos_nao_casa[] = [
                        'id'    => $p['origem_id'] ?? null,
                        'num'   => $p['numero_processo'] ?? '',
                        'instr' => $instr,
                        'tipo'  => $tipo,
                    ];
                }
            }
        }

        $diag['exemplos_nao_casa'] = $exemplos_nao_casa;
        $diag['exemplos_vazio']    = $exemplos_vazio;

        $diagnostico = $diag;

        $mensagem = "Prévia carregada: " . $dados['total'] . " processos recebidos. "
                  . "Diagnóstico: {$diag['instrumento_casa']} casam com contrato, "
                  . "{$diag['instrumento_nao_casa']} têm contrato mas não casam, "
                  . "{$diag['instrumento_vazio']} vêm sem contrato.";
        $tipoMensagem = 'success';

    } catch (Exception $e) {
        $mensagem = "Erro na prévia: " . $e->getMessage();
        $tipoMensagem = 'danger';
    }
}

// ---- IMPORT ----
if ($action === 'import') {
    try {
        // ✅ Lê do ARQUIVO, não da sessão
        if (!file_exists($PREVIEW_FILE)) {
            throw new Exception("Nenhuma prévia encontrada. Faça a busca novamente (arquivo não existe: " . basename($PREVIEW_FILE) . ").");
        }
        $conteudo = file_get_contents($PREVIEW_FILE);
        if ($conteudo === false || trim($conteudo) === '') {
            throw new Exception("Arquivo de prévia está vazio. Faça a busca novamente.");
        }
        $dados = json_decode($conteudo, true);
        if (!is_array($dados) || empty($dados['success'])) {
            throw new Exception("Arquivo de prévia está inválido. Faça a busca novamente.");
        }

        $processos = $dados['processos'] ?? [];
        if (empty($processos)) {
            throw new Exception("Nenhum processo recebido na prévia.");
        }

        $preservar = !empty($_POST['preservar']);

        // ---- Backup do estado atual ----
        $arquivoBackup = $BACKUP_DIR . '/processos_backup_' . date('Y-m-d_His') . '.json';
        $atuais = $pdo->query("SELECT * FROM processos")->fetchAll(PDO::FETCH_ASSOC);
        file_put_contents($arquivoBackup, json_encode($atuais, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        // ---- Mapa de preservação ----
        $mapContratos = [];
        if ($preservar) {
            $stmtMap = $pdo->query("SELECT id, contrato_id FROM processos WHERE contrato_id IS NOT NULL");
            while ($row = $stmtMap->fetch(PDO::FETCH_ASSOC)) {
                $mapContratos[(int)$row['id']] = (int)$row['contrato_id'];
            }
        }

        // ---- Limpeza + Insert em transação ----
        $pdo->beginTransaction();

        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        $pdo->exec("DELETE FROM processos");
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

        $sqlInsert = "INSERT INTO processos (
            id, numero_processo, assunto,
            equipe_id, responsavel_id, status_id, tipo_id, contrato_id,
            data_entrada, prazo, data_revisao, data_assinatura,
            sei_recebido, sei_criado_1, sei_criado_2, sei_criado_3,
            tem_prazo, cadastrado_sima,
            providencia, observacoes, uf, br, criado_por, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmtInsert = $pdo->prepare($sqlInsert);

        $importados  = 0;
        $preservados = 0;
        $daApi       = 0;
        $avisos      = [];

        foreach ($processos as $p) {
            $equipeId      = obterOuCriarId($pdo, 'equipes',         $p['equipe_nome']      ?? null);
            $responsavelId = obterOuCriarId($pdo, 'responsaveis',    $p['responsavel_nome'] ?? null);
            $statusId      = obterOuCriarId($pdo, 'status_processo', $p['status_nome']      ?? null);
            $tipoId        = obterOuCriarId($pdo, 'tipos',           $p['tipo_nome']        ?? null);

            $contratoId = obterContratoId($pdo, $p['contrato_instrumento'] ?? null);
            if ($contratoId) $daApi++;

            if (!$contratoId && $preservar && isset($mapContratos[(int)$p['origem_id']])) {
                $contratoId = $mapContratos[(int)$p['origem_id']];
                $preservados++;
            }

            if (!$contratoId && !empty($p['contrato_instrumento'])
                && !in_array(strtoupper(trim($p['contrato_instrumento'])), ['-', 'N/A', 'NA'], true)) {
                $avisos[] = "Contrato não encontrado: {$p['contrato_instrumento']} (processo {$p['numero_processo']})";
            }

            $stmtInsert->execute([
                (int)$p['origem_id'],
                $p['numero_processo'] ?? '',
                $p['assunto'] ?? '',
                $equipeId,
                $responsavelId,
                $statusId,
                $tipoId,
                $contratoId,
                !empty($p['data_entrada'])    ? $p['data_entrada']    : null,
                !empty($p['prazo'])           ? $p['prazo']           : null,
                !empty($p['data_revisao'])    ? $p['data_revisao']    : null,
                !empty($p['data_assinatura']) ? $p['data_assinatura'] : null,
                $p['sei_recebido'] ?? '',
                $p['sei_criado_1'] ?? '',
                $p['sei_criado_2'] ?? '',
                $p['sei_criado_3'] ?? '',
                (int)($p['tem_prazo'] ?? 0),
                (int)($p['cadastrado_sima'] ?? 0),
                $p['providencia'] ?? '',
                $p['observacoes'] ?? '',
                $p['uf'] ?? '',
                $p['br'] ?? '',
                null,
                !empty($p['created_at']) ? $p['created_at'] : date('Y-m-d H:i:s'),
            ]);
            $importados++;
        }

        $pdo->commit();

        // ✅ Remove o arquivo de prévia após sucesso
        @unlink($PREVIEW_FILE);

        $resultado = [
            'importados'    => $importados,
            'daApi'         => $daApi,
            'preservados'   => $preservados,
            'preservar'     => $preservar,
            'avisos'        => $avisos,
            'arquivoBackup' => basename($arquivoBackup)
        ];

        $modo = $preservar ? "com preservação" : "sem preservação";
        $mensagem = "Importação concluída ($modo)! $importados processos inseridos. "
                  . "$daApi vieram da API, $preservados do mapa local.";
        $tipoMensagem = 'success';

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $mensagem = "Erro na importação: " . $e->getMessage();
        $tipoMensagem = 'danger';
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Sincronizar Processos - DEV</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fc; padding: 40px 20px; }
        .container { max-width: 1000px; }
        .card { border: none; border-radius: 16px; box-shadow: 0 8px 30px rgba(0,0,0,0.08); padding: 30px; }
        .aviso { background: #fff3cd; border-left: 5px solid #ffc107; padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; }
        .diag-box { background: #f1f5fb; border-radius: 12px; padding: 20px; margin-bottom: 20px; }
        .diag-box h5 { margin-bottom: 15px; }
        .tabela-exemplos code { font-size: 0.85em; }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <h3><i class="bi bi-cloud-arrow-down"></i> Sincronizar Processos (Produção → Dev)</h3>

        <div class="aviso">
            <strong>⚠️ Ambiente de desenvolvimento</strong><br>
            Esta ferramenta <strong>substitui todos</strong> os processos locais pelos da produção.
            Um backup será salvo em <code>backups/</code> antes.
            A prévia é salva em <code>backups/sync_preview.json</code>.
        </div>

        <?php if ($mensagem): ?>
            <div class="alert alert-<?= $tipoMensagem ?>"><?= htmlspecialchars($mensagem) ?></div>
        <?php endif; ?>

        <!-- Estado da prévia em arquivo -->
        <?php if (file_exists($PREVIEW_FILE)): ?>
            <div class="alert alert-secondary small">
                <i class="bi bi-file-earmark-text"></i>
                Prévia disponível em <code>backups/sync_preview.json</code>
                (modificada em <?= date('d/m/Y H:i:s', filemtime($PREVIEW_FILE)) ?>)
            </div>
        <?php else: ?>
            <div class="alert alert-secondary small">
                <i class="bi bi-file-earmark-x"></i>
                Nenhuma prévia salva ainda. Clique em <strong>1. Buscar prévia</strong> abaixo.
            </div>
        <?php endif; ?>

        <!-- Etapa 1 -->
        <form method="POST" class="mb-3">
            <input type="hidden" name="action" value="preview">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-search"></i> 1. Buscar prévia da produção
            </button>
        </form>

        <?php if ($diagnostico): ?>
            <div class="diag-box">
                <h5>🔍 Diagnóstico da prévia (o que a API está entregando)</h5>

                <table class="table table-sm">
                    <tr>
                        <td>Total recebido da API</td>
                        <td class="text-end"><strong><?= $diagnostico['total_api'] ?></strong></td>
                    </tr>
                    <tr>
                        <td>Vêm com <code>contrato_instrumento</code> preenchido</td>
                        <td class="text-end"><strong><?= $diagnostico['com_instrumento'] ?></strong></td>
                    </tr>
                    <tr class="table-success">
                        <td>↳ desses, <strong>casam</strong> com <code>contratos_rdci</code> ou <code>contratos</code></td>
                        <td class="text-end"><strong><?= $diagnostico['instrumento_casa'] ?></strong></td>
                    </tr>
                    <tr class="table-warning">
                        <td>↳ desses, <strong>NÃO casam</strong></td>
                        <td class="text-end"><strong><?= $diagnostico['instrumento_nao_casa'] ?></strong></td>
                    </tr>
                    <tr class="table-danger">
                        <td>Vêm sem contrato (<code>null</code>, <code>''</code> ou <code>'-'</code>)</td>
                        <td class="text-end"><strong><?= $diagnostico['instrumento_vazio'] ?></strong></td>
                    </tr>
                </table>

                <h6 class="mt-3">Por tipo:</h6>
                <table class="table table-sm">
                    <thead><tr><th>Tipo</th><th class="text-end">Qtd</th></tr></thead>
                    <tbody>
                    <?php foreach ($diagnostico['por_tipo'] as $t => $q): ?>
                        <tr><td><?= htmlspecialchars($t) ?></td><td class="text-end"><?= $q ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if (!empty($diagnostico['exemplos_nao_casa'])): ?>
                    <h6 class="mt-3">Exemplos de contratos que a API mandou mas <strong>NÃO existem</strong> em nenhuma das tabelas:</h6>
                    <table class="table table-sm tabela-exemplos">
                        <thead><tr><th>ID</th><th>Nº Processo</th><th>Contrato (API)</th><th>Tipo</th></tr></thead>
                        <tbody>
                        <?php foreach ($diagnostico['exemplos_nao_casa'] as $e): ?>
                            <tr>
                                <td><?= (int)$e['id'] ?></td>
                                <td><?= htmlspecialchars($e['num']) ?></td>
                                <td><code><?= htmlspecialchars($e['instr']) ?></code></td>
                                <td><?= htmlspecialchars($e['tipo']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <?php if (!empty($diagnostico['exemplos_vazio'])): ?>
                    <h6 class="mt-3">Exemplos de processos que vêm <strong>sem contrato</strong>:</h6>
                    <table class="table table-sm tabela-exemplos">
                        <thead><tr><th>ID</th><th>Nº Processo</th><th>Valor</th><th>Tipo</th></tr></thead>
                        <tbody>
                        <?php foreach ($diagnostico['exemplos_vazio'] as $e): ?>
                            <tr>
                                <td><?= (int)$e['id'] ?></td>
                                <td><?= htmlspecialchars($e['num']) ?></td>
                                <td><code><?= htmlspecialchars($e['valor']) ?></code></td>
                                <td><?= htmlspecialchars($e['tipo']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($preview): ?>
            <div class="alert alert-info">
                <strong>Prévia:</strong>
                <?= $preview['total'] ?> processos encontrados
                (gerado em <?= htmlspecialchars($preview['gerado_em']) ?>)
            </div>

            <div class="table-responsive mb-3" style="max-height: 300px;">
                <table class="table table-sm table-hover">
                    <thead>
                        <tr>
                            <th>ID Origem</th>
                            <th>Nº Processo</th>
                            <th>Contrato</th>
                            <th>Equipe</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($preview['processos'], 0, 20) as $p): ?>
                        <tr>
                            <td><?= (int)$p['origem_id'] ?></td>
                            <td><?= htmlspecialchars($p['numero_processo'] ?? '') ?></td>
                            <td><?= htmlspecialchars($p['contrato_instrumento'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($p['equipe_nome'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($p['status_nome'] ?? '—') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (count($preview['processos']) > 20): ?>
                    <p class="text-muted small">... e mais <?= count($preview['processos']) - 20 ?> processos.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Etapa 2 — sempre visível se existe prévia em arquivo -->
        <?php if (file_exists($PREVIEW_FILE)): ?>
            <form method="POST" onsubmit="return confirm('Tem CERTEZA? Isso vai substituir TODOS os processos locais pelos da produção.')">
                <input type="hidden" name="action" value="import">

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="preservar" id="preservar" value="1" checked>
                    <label class="form-check-label" for="preservar">
                        <strong>Preservar vínculos antigos pelo id de origem</strong><br>
                        <small class="text-muted">
                            Se marcado, mantém o <code>contrato_id</code> dos processos já existentes localmente
                            quando a API não devolver contrato para eles.<br>
                            <strong>Deixe desmarcado</strong> para diagnóstico limpo — os vínculos virão
                            <em>somente</em> do que a API mandar.
                        </small>
                    </label>
                </div>

                <button type="submit" class="btn btn-danger">
                    <i class="bi bi-arrow-repeat"></i> 2. Confirmar e importar
                </button>
            </form>
        <?php endif; ?>

        <?php if ($resultado): ?>
            <hr>
            <h5>Resultado</h5>
            <p>
                <strong><?= $resultado['importados'] ?></strong> processos importados.<br>
                <strong><?= $resultado['daApi'] ?></strong> contratos resolvidos via API.<br>
                <strong><?= $resultado['preservados'] ?></strong> contratos preservados pelo mapa local
                (<?= $resultado['preservar'] ? 'preservação ATIVADA' : 'preservação DESATIVADA' ?>).<br>
                Backup: <code>backups/<?= htmlspecialchars($resultado['arquivoBackup']) ?></code>
            </p>
            <?php if (!empty($resultado['avisos'])): ?>
                <div class="alert alert-warning">
                    <strong>Avisos (<?= count($resultado['avisos']) ?>):</strong>
                    <ul class="mb-0 small">
                        <?php foreach (array_slice($resultado['avisos'], 0, 10) as $a): ?>
                            <li><?= htmlspecialchars($a) ?></li>
                        <?php endforeach; ?>
                        <?php if (count($resultado['avisos']) > 10): ?>
                            <li>... e mais <?= count($resultado['avisos']) - 10 ?>.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>