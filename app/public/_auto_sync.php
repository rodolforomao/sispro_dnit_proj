<?php
// ============================================================
// AUTO-SYNC — Dispara importação no primeiro acesso do dia
// Incluído pelo index.php raiz
// ============================================================

if (php_sapi_name() === 'cli') return;
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) return;

$dirBase    = __DIR__;   // app/public
$marcadorOK = $dirBase . '/.sync_ultima_execucao';
$lockSync   = $dirBase . '/.sync_em_andamento';

// Chave compartilhada (mesma fórmula no maestro)
$chave = 'sispro_auto_sync_2026_' . md5($dirBase . '/_auto_sync.php' . 'atlas');

// Evita auto-trigger nas próprias rotas de importação
$urlAtual = $_GET['url'] ?? '';
if (strpos($urlAtual, 'importar_') !== false) return;
if (strpos($urlAtual, 'importar_todos_datasets') !== false) return;

// ============================================================
// 1) Já rodou hoje? (compara SÓ a parte da data, ignora "|status")
// ============================================================
$hoje   = date('Y-m-d');
$conteudoMarcador = file_exists($marcadorOK) ? trim(@file_get_contents($marcadorOK)) : '';
$ultimaData       = explode('|', $conteudoMarcador)[0]; // pega "YYYY-MM-DD"

if ($ultimaData === $hoje) return; // já rodou hoje, sai fora

// ============================================================
// 2) Já está em andamento? (lock de até 2h)
// ============================================================
if (file_exists($lockSync)) {
    $idade = time() - filemtime($lockSync);
    if ($idade < 7200) return;   // ainda rodando
    @unlink($lockSync);           // lock zumbi, remove
}

// ============================================================
// 3) Cria o lock e dispara em segundo plano
// ============================================================
@touch($lockSync);

try {
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';

    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $base       = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
    if ($base === '.') $base = '';

    $url = "{$proto}://{$host}{$base}/?url=importar_todos_datasets&key={$chave}&background=1";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT_MS     => 800,
        CURLOPT_NOSIGNAL       => 1,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    curl_exec($ch);
    curl_close($ch);
} catch (Throwable $e) {
    // Se nem conseguiu disparar, remove o lock pra tentar de novo no próximo acesso
    @unlink($lockSync);
}