<?php
// ============================================================
// MAESTRO — Executa todas as importações em sequência
// Acesso: ?url=importar_todos_datasets&key=CHAVE           (manual)
//         ?url=importar_todos_datasets&key=CHAVE&background=1 (auto)
// ============================================================
chdir(__DIR__);
require_once 'config.php';

set_time_limit(0);
ignore_user_abort(true);

$viaCLI        = (php_sapi_name() === 'cli');
$emBackground  = isset($_GET['background']) && $_GET['background'] == '1';
$chaveEsperada = 'sispro_auto_sync_2026_' . md5(__DIR__ . '/_auto_sync.php' . 'atlas');

// ---------- Controle de acesso ----------
if (!$viaCLI) {
    $chave  = $_GET['key'] ?? '';
    $logado = isset($_SESSION['usuario_id']) &&
              in_array($_SESSION['usuario_nivel'] ?? '', ['admin', 'desenvolvedor']);
    if ($chave !== $chaveEsperada && !$logado) {
        http_response_code(403);
        die('Acesso negado.');
    }
}

// ---------- Libera o cliente (se background) ----------
if ($emBackground && !$viaCLI) {
    header('Content-Type: text/plain');
    header('Connection: close');
    header('Content-Length: 2');
    echo 'OK';
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        while (ob_get_level() > 0) ob_end_flush();
        flush();
    }
}

// ---------- Controle ----------
$dirBase    = __DIR__;
$marcadorOK = $dirBase . '/.sync_ultima_execucao';
$lockSync   = $dirBase . '/.sync_em_andamento';
$arquivoLog = $dirBase . '/importacao_cron.log';

function logMsg($msg, $arquivoLog) {
    @file_put_contents($arquivoLog, '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL, FILE_APPEND);
}

// ============================================================
// 🔥 AUTO-LIMPEZA DE LOCKS TRAVADOS (evita loop)
// ============================================================
if (file_exists($lockSync)) {
    $idade = time() - filemtime($lockSync);
    if ($idade > 7200) { // 2h travado = considerado zumbi
        @unlink($lockSync);
        logMsg('🔓 Lock antigo removido (' . round($idade/60) . ' min travado).', $arquivoLog);
    } else {
        logMsg('⏳ Lock ativo há ' . round($idade/60) . ' min — abortando.', $arquivoLog);
        exit;
    }
}

// ============================================================
// LISTA COMPLETA (nomes conforme arquivos reais em app/public/)
// ============================================================
$scripts = [
    ['arquivo' => 'importar_rdci_api.php',                            'nome' => 'RDCI Contratos (26)'],
    ['arquivo' => 'importar_supra_dataset_1.php',                     'nome' => 'Dataset 1 — Todos'],
    ['arquivo' => 'importar_supra_dataset_36_infoobras.php',          'nome' => 'Dataset 36 — Contratos Atlas'],
    ['arquivo' => 'importar_supra_dataset_38.php',                    'nome' => 'Dataset 38 — Lista Todos Instrumentos'],
    ['arquivo' => 'importar_supra_dataset_39.php',                    'nome' => 'Dataset 39 — Meio Ambiente'],
    ['arquivo' => 'importar_supra_dataset_40_resumos.php',            'nome' => 'Dataset 40 — Resumos'],
    ['arquivo' => 'importar_supra_dataset_40_slides_atlas.php',       'nome' => 'Dataset 40 — Slides Atlas'],
    ['arquivo' => 'importar_supra_dataset_16_base_controle_geo.php',  'nome' => 'Dataset 16 — Base Controle GEO'],
];

$inicio = microtime(true);
logMsg('═══════════════════════════════════════════', $arquivoLog);
logMsg('🚀 INÍCIO (' . count($scripts) . ' importações)', $arquivoLog);
logMsg('═══════════════════════════════════════════', $arquivoLog);

// Binário PHP (evita httpd.exe)
$phpBin = PHP_BINARY;
if (stripos($phpBin, 'httpd') !== false || stripos($phpBin, 'apache') !== false) {
    $ini = php_ini_loaded_file();
    $phpBin = ($ini && file_exists(dirname($ini) . '/php.exe'))
        ? dirname($ini) . '/php.exe'
        : 'C:\\xampp\\php\\php.exe';
}
logMsg("🔧 PHP: {$phpBin}", $arquivoLog);

$resultados = [];
$todosOk    = true;

foreach ($scripts as $i => $s) {
    $num     = $i + 1;
    $caminho = $dirBase . '/' . $s['arquivo'];
    logMsg("▶ [{$num}/" . count($scripts) . "] {$s['nome']}", $arquivoLog);

    if (!file_exists($caminho)) {
        logMsg("  ❌ Arquivo não encontrado: {$s['arquivo']}", $arquivoLog);
        $resultados[$s['nome']] = 'arquivo inexistente';
        $todosOk = false;
        continue;
    }

    $t0    = microtime(true);
    $cmd   = escapeshellarg($phpBin) . ' ' . escapeshellarg($caminho) . ' 2>&1';
    $saida = @shell_exec($cmd);
    $dur   = round(microtime(true) - $t0, 1);

    if ($saida === null) {
        logMsg("  ❌ shell_exec falhou ({$dur}s)", $arquivoLog);
        $resultados[$s['nome']] = 'falha';
        $todosOk = false;
    } else {
        // Detecta se houve erro no output do filho
        $erroDetectado = (
            stripos($saida, '❌') !== false ||
            stripos($saida, 'Fatal error') !== false ||
            stripos($saida, 'Parse error') !== false
        );

        foreach (array_slice(array_filter(explode("\n", trim($saida))), -3) as $l) {
            logMsg('  │ ' . trim($l), $arquivoLog);
        }

        if ($erroDetectado) {
            logMsg("  ⚠️ Concluído com ERRO em {$dur}s", $arquivoLog);
            $resultados[$s['nome']] = "erro ({$dur}s)";
            $todosOk = false;
        } else {
            logMsg("  ✅ Concluído em {$dur}s", $arquivoLog);
            $resultados[$s['nome']] = "ok ({$dur}s)";
        }
    }
}

$tempoTotal = round(microtime(true) - $inicio, 1);
$okCount    = count(array_filter($resultados, fn($r) => strpos($r, 'ok') === 0));
$errCount   = count($resultados) - $okCount;

logMsg('═══════════════════════════════════════════', $arquivoLog);
logMsg("🏁 FIM — {$tempoTotal}s | Sucesso: {$okCount}/" . count($resultados) . " | Falhas: {$errCount}", $arquivoLog);
logMsg('', $arquivoLog);

// ============================================================
// 🔥 MARCADOR GRAVADO SEMPRE (evita loop infinito)
//    Formato: YYYY-MM-DD|status
// ============================================================
$status = $todosOk ? 'ok' : 'parcial';
@file_put_contents($marcadorOK, date('Y-m-d') . '|' . $status);

if ($todosOk) {
    logMsg('📌 Marcador gravado: sync do dia concluída com sucesso.', $arquivoLog);
} else {
    logMsg("📌 Marcador gravado mesmo com falhas (status: {$status}) — não tentará de novo hoje.", $arquivoLog);
}

// Libera o lock
@unlink($lockSync);

if (!$emBackground) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status'      => $status,
        'tempo_total' => $tempoTotal,
        'sucesso'     => $okCount,
        'total'       => count($resultados),
        'resultados'  => $resultados,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}