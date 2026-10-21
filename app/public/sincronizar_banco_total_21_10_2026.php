<?php
// ============================================================
// sincronizar_banco.php — RODAR NO XAMPP (DEV)
// ============================================================
require_once 'config.php';
require_once __DIR__ . '/../Config/version.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// ============================================================
// ⚙️ CONFIGURAÇÃO
// ============================================================
$SYNC_URL   = 'http://10.100.11.235/sispro/api_exportar_banco.php';
$SYNC_TOKEN = 'kzseb1XXFRO1CWL5FAPQ3mJi5KBI5viG5OsPWwv2xTI'; // igual do API
$BACKUP_DIR = __DIR__ . '/backups';
$PREVIEW_FILE = $BACKUP_DIR . '/sync_banco_preview.json';

if (!is_dir($BACKUP_DIR)) @mkdir($BACKUP_DIR, 0755, true);

// ============================================================
// FUNÇÕES
// ============================================================
function fetchApi(string $url, string $token): string
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'X-Sync-Token: ' . $token,
            'Accept: application/json',
            'Accept-Encoding: gzip',
        ],
        CURLOPT_TIMEOUT        => 600,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($resp === false) throw new Exception("Curl: $err");
    if ($code !== 200) throw new Exception("HTTP $code — " . substr((string)$resp, 0, 300));
    return $resp;
}

function backupTabelas(PDO $pdo, string $dir, array $tabelas): array
{
    $stamp = date('Y-m-d_His');
    $prefix = $dir . '/backup_banco_' . $stamp;
    @mkdir($prefix, 0755, true);

    $salvos = [];
    foreach ($tabelas as $t) {
        try {
            $rows = $pdo->query("SELECT * FROM `$t`")->fetchAll(PDO::FETCH_ASSOC);
            file_put_contents(
                "$prefix/$t.json",
                json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            );
            $salvos[] = ['tabela' => $t, 'linhas' => count($rows)];
        } catch (Throwable $e) {
            $salvos[] = ['tabela' => $t, 'erro' => $e->getMessage()];
        }
    }
    return [$prefix, $salvos];
}

function importarTabela(PDO $pdo, string $t, array $info, bool $recriarSchema = true): int
{
    if ($recriarSchema) {
        $create = $info['create_sql'] ?? null;
        if (!$create) throw new Exception("Tabela '$t' sem create_sql");
        $pdo->exec($create);
    }

    $linhas = $info['linhas'] ?? [];
    if (empty($linhas)) return 0;

    $colunas = array_column($info['colunas'] ?? [], 'nome');
    if (empty($colunas)) throw new Exception("Tabela '$t' sem metadados de colunas");

    $placeholders = implode(',', array_fill(0, count($colunas), '?'));
    $colsSql = '`' . implode('`,`', $colunas) . '`';
    $stmt = $pdo->prepare("INSERT INTO `$t` ($colsSql) VALUES ($placeholders)");

    $n = 0;
    foreach ($linhas as $row) {
        $vals = [];
        foreach ($colunas as $c) $vals[] = array_key_exists($c, $row) ? $row[$c] : null;
        $stmt->execute($vals);
        $n++;
    }

    // Restaura AUTO_INCREMENT (belt & suspenders — SHOW CREATE já traz, mas garante)
    if (!empty($info['auto_increment']) && (int)$info['auto_increment'] > 0) {
        $ai = (int)$info['auto_increment'];
        $pdo->exec("ALTER TABLE `$t` AUTO_INCREMENT = $ai");
    }

    return $n;
}

function tabelaExiste(PDO $pdo, string $t): bool
{
    $st = $pdo->prepare(
        "SELECT 1 FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1"
    );
    $st->execute([$t]);
    return (bool)$st->fetchColumn();
}

function checksumBate(PDO $pdo, $t, $esperado)
{
    try {
        $rows = $pdo->query("SELECT * FROM `$t`")->fetchAll(PDO::FETCH_ASSOC);
        return hash('sha256', json_encode($rows, JSON_UNESCAPED_UNICODE)) === $esperado;
    } catch (Throwable $e) {
        return null;
    }
}

// ============================================================
// AÇÕES
// ============================================================
$acao       = $_POST['action'] ?? '';
$mensagem   = '';
$tipoMsg    = '';
$preview    = null;
$diagnostico= null;
$resultado  = null;

// ---------------- PREVIEW ----------------
if ($acao === 'preview') {
    try {
        $json  = fetchApi($SYNC_URL, $SYNC_TOKEN);
        $dados = json_decode($json, true);

        if (!is_array($dados) || empty($dados['success'])) {
            throw new Exception("Resposta inválida: " . substr($json, 0, 400));
        }

        file_put_contents($PREVIEW_FILE, json_encode($dados, JSON_UNESCAPED_UNICODE));
        $preview = $dados;

        $tabelasDev = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $soNoDev    = array_values(array_diff($tabelasDev, array_keys($dados['tabelas'])));

        $totalLinhas = 0;
        foreach ($dados['tabelas'] as $info) $totalLinhas += (int)($info['total'] ?? 0);

        $diagnostico = [
            'total_tabelas'   => count($dados['tabelas']),
            'total_linhas'    => $totalLinhas,
            'tamanho_payload' => strlen($json),
            'so_no_dev'       => $soNoDev,
            'excluidas_prod'  => $dados['excluidas'] ?? [],
        ];

        $mensagem = sprintf(
            "Prévia carregada: %d tabelas, %d linhas, %.2f MB.",
            $diagnostico['total_tabelas'],
            $diagnostico['total_linhas'],
            $diagnostico['tamanho_payload'] / 1048576
        );
        $tipoMsg = 'success';

    } catch (Throwable $e) {
        $mensagem = "Erro na prévia: " . $e->getMessage();
        $tipoMsg  = 'danger';
    }
}

// ---------------- IMPORT ----------------
if ($acao === 'import') {
    try {
        if (!file_exists($PREVIEW_FILE)) {
            throw new Exception("Nenhuma prévia encontrada. Faça a busca primeiro.");
        }
        $dados = json_decode(file_get_contents($PREVIEW_FILE), true);
        if (!is_array($dados) || empty($dados['success'])) {
            throw new Exception("Arquivo de prévia inválido.");
        }

        $ordem   = $dados['ordem'];
        $tabelas = $dados['tabelas'];
        $modo    = $_POST['modo'] ?? 'drop_create';   // drop_create | truncate
        $fazerBackup = !empty($_POST['fazer_backup']);

        // 1) BACKUP
        $backupDir = null;
        $backupInfo = [];
        if ($fazerBackup) {
            list($backupDir, $backupInfo) = backupTabelas($pdo, $BACKUP_DIR, $ordem);
        }

        // 2) Desabilita FKs (vale para a sessão inteira via PDO)
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

        // 3) Importa cada tabela
        $stats = [];
        foreach ($ordem as $t) {
            $info = $tabelas[$t] ?? null;
            try {
                if (!$info) throw new Exception("Tabela não está na prévia");
                if ($modo === 'truncate') {
                    if (!tabelaExiste($pdo, $t)) {
                        throw new Exception("Tabela não existe no DEV (modo truncate exige schema local). Use 'drop_create'.");
                    }
                    $pdo->exec("TRUNCATE TABLE `$t`");
                    $n = importarTabela($pdo, $t, $info, /* recriarSchema */ false);
                } else {
                    $pdo->exec("DROP TABLE IF EXISTS `$t`");
                    $n = importarTabela($pdo, $t, $info, /* recriarSchema */ true);
                }
                $stats[$t] = ['ok' => true, 'linhas' => $n];
            } catch (Throwable $e) {
                $stats[$t] = ['ok' => false, 'erro' => $e->getMessage()];
            }
        }

        // 4) Reabilita FKs
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

        // 5) Verificação por checksum
        $verificacao = [];
        foreach ($ordem as $t) {
            if (!empty($stats[$t]['ok'])) {
                $verificacao[$t] = checksumBate($pdo, $t, (string)($tabelas[$t]['checksum'] ?? ''));
            }
        }

        $resultado = [
            'modo'         => $modo,
            'backup_dir'   => $backupDir ? basename($backupDir) : null,
            'backup_info'  => $backupInfo,
            'stats'        => $stats,
            'verificacao'  => $verificacao,
        ];

        $okCount = count(array_filter($stats, function($s) { return !empty($s['ok']); }));
        $tot     = count($ordem);

        if ($okCount === $tot) {
            // Só apaga a prévia se tudo deu certo
            @unlink($PREVIEW_FILE);
            $mensagem = "Importação concluída: $okCount/$tot tabelas OK.";
            $tipoMsg  = 'success';
        } else {
            $mensagem = "Importação parcial: $okCount/$tot tabelas OK. Veja o relatório abaixo.";
            $tipoMsg  = 'warning';
        }

    } catch (Throwable $e) {
        // Garante FK_CHECKS=1 mesmo em erro
        try { $pdo->exec("SET FOREIGN_KEY_CHECKS = 1"); } catch (Throwable $e2) {}
        $mensagem = "Erro na importação: " . $e->getMessage();
        $tipoMsg  = 'danger';
    }
}

// ---------------- LIMPAR PRÉVIA ----------------
if ($acao === 'limpar_preview') {
    @unlink($PREVIEW_FILE);
    $mensagem = "Prévia removida.";
    $tipoMsg  = 'info';
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>Sincronizar Banco — DEV</title>
<link href="<?= sispro_asset('https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css') ?>" rel="stylesheet">
<link href="<?= sispro_asset('https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css') ?>" rel="stylesheet">
<style>
  body { background:#f8f9fc; padding:40px 20px; }
  .container { max-width:1100px; }
  .card { border:none; border-radius:16px; box-shadow:0 8px 30px rgba(0,0,0,.08); padding:30px; }
  .aviso { background:#fff3cd; border-left:5px solid #ffc107; padding:15px 20px; border-radius:8px; margin-bottom:20px; }
  .diag-box { background:#f1f5fb; border-radius:12px; padding:20px; margin-bottom:20px; }
  code { font-size:.9em; }
</style>
</head>
<body>
<div class="container">
  <div class="card">
    <h3><i class="bi bi-database-down"></i> Sincronizar Banco (Produção → DEV)</h3>

    <div class="aviso">
      <strong>⚠️ Ambiente de desenvolvimento</strong><br>
      Esta ferramenta <strong>substitui todas as tabelas</strong> do DEV pelas de produção
      (exceto <code>logs_auditoria</code> e tabelas com <code>_bkp_</code> / <code>_backup_</code>).
      Um backup será salvo em <code>backups/</code> antes.
    </div>

    <?php if ($mensagem): ?>
      <div class="alert alert-<?= htmlspecialchars($tipoMsg) ?>"><?= htmlspecialchars($mensagem) ?></div>
    <?php endif; ?>

    <?php if (file_exists($PREVIEW_FILE)): ?>
      <div class="alert alert-secondary small mb-3">
        <i class="bi bi-file-earmark-text"></i>
        Prévia disponível em <code>backups/sync_banco_preview.json</code>
        (modificada em <?= date('d/m/Y H:i:s', filemtime($PREVIEW_FILE)) ?>)
        <form method="POST" class="d-inline ms-2">
          <input type="hidden" name="action" value="limpar_preview">
          <button class="btn btn-sm btn-outline-secondary">Descartar</button>
        </form>
      </div>
    <?php else: ?>
      <div class="alert alert-secondary small">
        <i class="bi bi-file-earmark-x"></i>
        Nenhuma prévia salva ainda. Clique em <strong>1. Buscar prévia</strong>.
      </div>
    <?php endif; ?>

    <!-- Etapa 1 -->
    <form method="POST" class="mb-3">
      <input type="hidden" name="action" value="preview">
      <button class="btn btn-primary"><i class="bi bi-cloud-download"></i> 1. Buscar prévia (API de produção)</button>
    </form>

    <?php if ($diagnostico): ?>
      <div class="diag-box">
        <h5>🔍 Diagnóstico da prévia</h5>
        <table class="table table-sm">
          <tr><td>Tabelas na API</td><td class="text-end"><strong><?= $diagnostico['total_tabelas'] ?></strong></td></tr>
          <tr><td>Linhas totais</td><td class="text-end"><strong><?= number_format($diagnostico['total_linhas']) ?></strong></td></tr>
          <tr><td>Tamanho do payload</td><td class="text-end"><strong><?= number_format($diagnostico['tamanho_payload']/1048576, 2) ?> MB</strong></td></tr>
          <tr><td>Blacklisted em produção</td><td class="text-end"><code><?= htmlspecialchars(implode(', ', $diagnostico['excluidas_prod']) ?: '—') ?></code></td></tr>
        </table>

        <?php if (!empty($diagnostico['so_no_dev'])): ?>
          <div class="alert alert-warning small mb-0">
            <strong>⚠️ Tabelas que existem só no DEV (NÃO serão tocadas):</strong>
            <code><?= htmlspecialchars(implode(', ', $diagnostico['so_no_dev'])) ?></code>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if ($preview): ?>
      <div class="alert alert-info">
        <strong>Prévia:</strong> <?= (int)$preview['total_tabelas'] ?> tabelas
        &nbsp;|&nbsp; gerada em <?= htmlspecialchars($preview['gerado_em']) ?>
        &nbsp;|&nbsp; banco <code><?= htmlspecialchars($preview['banco']) ?></code>
        (MySQL <?= htmlspecialchars($preview['mysql_version']) ?>)
      </div>

      <div class="table-responsive mb-3" style="max-height:320px; overflow:auto;">
        <table class="table table-sm table-hover">
          <thead>
            <tr><th>Tabela</th><th class="text-end">Linhas</th><th class="text-end">Checksum</th></tr>
          </thead>
          <tbody>
          <?php foreach ($preview['tabelas'] as $t => $info): ?>
            <tr>
              <td><code><?= htmlspecialchars($t) ?></code></td>
              <td class="text-end"><?= (int)$info['total'] ?></td>
              <td class="text-end"><small><?= substr($info['checksum'], 0, 10) ?>…</small></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <!-- Etapa 2 -->
    <?php if (file_exists($PREVIEW_FILE)): ?>
      <form method="POST" onsubmit="return confirm('Isso vai SUBSTITUIR as tabelas do DEV pelas de produção. Continuar?')">
        <input type="hidden" name="action" value="import">

        <div class="mb-3">
          <label class="form-label fw-bold">Modo de importação</label>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="modo" value="drop_create" id="modoDrop" checked>
            <label class="form-check-label" for="modoDrop">
              <strong>DROP + CREATE + INSERT</strong> (espelho fiel do schema — recomendado)
            </label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="modo" value="truncate" id="modoTrunc">
            <label class="form-check-label" for="modoTrunc">
              <strong>TRUNCATE + INSERT</strong> (mantém o schema do DEV, só troca dados)
            </label>
          </div>
        </div>

        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" name="fazer_backup" id="fazerBackup" value="1" checked>
          <label class="form-check-label" for="fazerBackup">
            <strong>Fazer backup antes de importar</strong>
            <small class="text-muted d-block">Salva cada tabela em <code>backups/backup_banco_.../&lt;tabela&gt;.json</code></small>
          </label>
        </div>

        <button class="btn btn-danger"><i class="bi bi-arrow-repeat"></i> 2. Confirmar e importar</button>
      </form>
    <?php endif; ?>

    <?php if ($resultado): ?>
      <hr>
      <h5>Resultado (modo <code><?= htmlspecialchars($resultado['modo']) ?></code>)</h5>

      <?php if ($resultado['backup_dir']): ?>
        <p class="small mb-2">
          Backup: <code>backups/<?= htmlspecialchars($resultado['backup_dir']) ?>/</code>
        </p>
      <?php endif; ?>

      <table class="table table-sm">
        <thead>
          <tr>
            <th>Tabela</th>
            <th class="text-end">Linhas importadas</th>
            <th class="text-center">Checksum</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($resultado['stats'] as $t => $st): ?>
          <tr class="<?= !empty($st['ok']) ? '' : 'table-danger' ?>">
            <td><code><?= htmlspecialchars($t) ?></code></td>
            <td class="text-end"><?= !empty($st['ok']) ? (int)$st['linhas'] : '—' ?></td>
            <td class="text-center">
              <?php if (array_key_exists($t, $resultado['verificacao'])): ?>
                <?php if ($resultado['verificacao'][$t] === true): ?>
                  <span class="badge bg-success">OK</span>
                <?php elseif ($resultado['verificacao'][$t] === false): ?>
                  <span class="badge bg-warning text-dark">Divergente</span>
                <?php else: ?>
                  <span class="badge bg-secondary">n/a</span>
                <?php endif; ?>
              <?php endif; ?>
            </td>
            <td>
              <?= !empty($st['ok']) ? '<span class="text-success">Importado</span>' : '<span class="text-danger">'.htmlspecialchars($st['erro'] ?? 'erro').'</span>' ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>
</body>
</html>