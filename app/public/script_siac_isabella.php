<?php
// app/public/script_siac_isabella.php
//
// Scraper SIAC - producao.
// FIX v10:
//  - Login: detecta tambem pela ausencia do form [name=cpf] (SIAC mantem URL login.do apos autenticar).
//  - Login: resposta em ~1.5s (nao espera 20s quando auth ja aconteceu).
//  - DB: log por linha + dump do .txt do PDF quando parse retorna 0 medicoes.
// headless desligado por padrao.

@set_time_limit(0);
@ini_set('max_execution_time', '0');
@ini_set('memory_limit', '512M');
@ini_set('display_errors', '1');
@ini_set('log_errors', '1');

// ============================================================
// ARGS
// ============================================================
$opts = getopt('', ['contratos:', 'output-dir:', 'config:', 'job:', 'headless:']);
$contratosArq = $opts['contratos']  ?? null;
$outputDir    = $opts['output-dir'] ?? null;
$configPath   = $opts['config']     ?? __DIR__ . '/config.php';
$jobId        = $opts['job']        ?? ('siac_' . date('Ymd_His'));

$headless = isset($opts['headless'])
    ? filter_var($opts['headless'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
    : false;
if ($headless === null) $headless = false;

if (!$contratosArq || !is_file($contratosArq)) { fwrite(STDERR, "Contratos invalido\n"); exit(1); }
if (!$outputDir) { fwrite(STDERR, "--output-dir obrigatorio\n"); exit(1); }
@mkdir($outputDir, 0775, true);
$statusFile = rtrim($outputDir, '/\\') . '/status.json';
$tmpDir     = rtrim($outputDir, '/\\') . '/pdfs_tmp';
$falhasDir  = rtrim($outputDir, '/\\') . '/pdfs_falhas';
$loginDir   = rtrim($outputDir, '/\\') . '/login_debug';
@mkdir($tmpDir,    0775, true);
@mkdir($falhasDir, 0775, true);
@mkdir($loginDir,  0775, true);

$usuario    = getenv('SIAC_USER')  ?: '';
$senha      = getenv('SIAC_PASS')  ?: '';
$edgeDriver = getenv('EDGEDRIVER') ?: '';
if (!$usuario || !$senha) { fwrite(STDERR, "SIAC_USER/SIAC_PASS ausentes\n"); exit(2); }
if (!$edgeDriver || !is_file($edgeDriver)) { fwrite(STDERR, "EDGEDRIVER invalido\n"); exit(3); }

// ============================================================
// MySQL + autoload
// ============================================================
require_once $configPath;
/** @var PDO $pdo */
if (!isset($pdo) || !($pdo instanceof PDO)) { fwrite(STDERR, "config.php sem \$pdo\n"); exit(4); }
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$autoload = null;
foreach ([__DIR__ . '/../vendor/autoload.php', __DIR__ . '/vendor/autoload.php'] as $p) {
    if (is_file($p)) { $autoload = $p; break; }
}
if (!$autoload) { fwrite(STDERR, "vendor/autoload.php nao encontrado\n"); exit(5); }
require_once $autoload;
if (!class_exists('Smalot\\PdfParser\\Parser')) { fwrite(STDERR, "Parser nao carregada\n"); exit(5); }

$contratos = json_decode(file_get_contents($contratosArq), true);
if (!is_array($contratos) || !$contratos) { fwrite(STDERR, "Contratos vazios\n"); exit(6); }
$total  = count($contratos);
$inicio = time();

// ============================================================
// HELPERS
// ============================================================
function setStatus(string $file, array $data): void {
    @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}
function dbUpdate(PDO $pdo, string $jobId, array $campos): void {
    if (!$campos) return;
    $set = []; $p = [];
    foreach ($campos as $k => $v) { $set[] = "`$k` = ?"; $p[] = $v; }
    $set[] = "`atualizado_em` = NOW()";
    $p[] = $jobId;
    try {
        $pdo->prepare("UPDATE `siac_import_jobs` SET " . implode(', ', $set) . " WHERE `job_id` = ?")
            ->execute($p);
    } catch (Throwable $e) {
        fwrite(STDERR, "[dbUpdate] " . $e->getMessage() . "\n");
    }
}
function logPhase(string $msg): void {
    fwrite(STDOUT, "[" . date('H:i:s') . "] $msg\n");
    @flush();
}

// ============================================================
// SHUTDOWN HANDLER
// ============================================================
$jobFinalizado = false;
register_shutdown_function(function () use (&$jobFinalizado, $pdo, $jobId, $statusFile) {
    if ($jobFinalizado) return;
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        $msg = 'Erro fatal: ' . $err['message'] . ' em ' . $err['file'] . ':' . $err['line'];
        fwrite(STDERR, $msg . "\n");
        dbUpdate($pdo, $jobId, ['status'=>'erro','etapa'=>'fatal','erro'=>$msg,'finalizado_em'=>date('Y-m-d H:i:s')]);
        setStatus($statusFile, ['status'=>'erro','etapa'=>'fatal','erro'=>$msg]);
        return;
    }
    dbUpdate($pdo, $jobId, ['status'=>'erro','etapa'=>'abortado',
        'erro'=>'Script encerrou sem finalizar.','finalizado_em'=>date('Y-m-d H:i:s')]);
});

dbUpdate($pdo, $jobId, ['status'=>'rodando','etapa'=>'spawn','progresso'=>1]);
setStatus($statusFile, [
    'status'=>'rodando','etapa'=>'spawn','progresso'=>1,
    'total'=>$total,'processados'=>0,'medicoes_inseridas'=>0,
    'iniciado_em'=>date('Y-m-d H:i:s'),
]);
logPhase("[spawn] job $jobId, $total contratos (headless=" . ($headless ? 'SIM' : 'NAO') . ")");

// ============================================================
// MSEDGEDRIVER
// ============================================================
$porta     = 4444 + random_int(0, 500);
$driverUrl = "http://127.0.0.1:{$porta}";
$driverLog = rtrim($outputDir, '/\\') . '/driver.log';

logPhase("[driver] iniciando porta $porta");
$driverProc = proc_open('"' . $edgeDriver . '" --port=' . $porta,
    [0=>['pipe','r'], 1=>['file',$driverLog,'a'], 2=>['file',$driverLog,'a']], $driverPipes);
if (!is_resource($driverProc)) {
    dbUpdate($pdo, $jobId, ['status'=>'erro','etapa'=>'spawn','erro'=>'Falha ao iniciar driver','finalizado_em'=>date('Y-m-d H:i:s')]);
    $jobFinalizado = true; exit(7);
}

$ready = false;
for ($i = 0; $i < 40; $i++) {
    usleep(500000);
    $ch = curl_init($driverUrl . '/status');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>2]);
    curl_exec($ch);
    if (curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200) { $ready = true; curl_close($ch); break; }
    curl_close($ch);
}
if (!$ready) {
    proc_terminate($driverProc);
    dbUpdate($pdo, $jobId, ['status'=>'erro','etapa'=>'spawn','erro'=>'driver nao respondeu','finalizado_em'=>date('Y-m-d H:i:s')]);
    $jobFinalizado = true; exit(7);
}
logPhase("[driver] pronto");

// ============================================================
// WEBDRIVER RAW
// ============================================================
function wdCall(string $base, string $method, string $path, $body = null): array {
    $ch = curl_init($base . $path);
    $opts = [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_TIMEOUT => 60, CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ];
    if ($body !== null) $opts[CURLOPT_POSTFIELDS] = json_encode($body);
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch); curl_close($ch);
    return is_string($resp) ? (json_decode($resp, true) ?: []) : [];
}
function wdPost($b,$p,$body=[]){return wdCall($b,'POST',$p,$body);}
function wdGet($b,$p){return wdCall($b,'GET',$p);}
function wdDelete($b,$p){return wdCall($b,'DELETE',$p);}

const ELEM_KEY = 'element-6066-11e4-a52e-4f735466cecf';

function findEl($b,$sid,$using,$value): ?string {
    $r = wdPost($b, "/session/{$sid}/element", ['using'=>$using,'value'=>$value]);
    return $r['value'][ELEM_KEY] ?? null;
}
function clickEl($b,$sid,$elId): void { wdPost($b, "/session/{$sid}/element/{$elId}/click", []); }
function execJs($b,$sid,$script,$args=[]): array {
    return wdPost($b, "/session/{$sid}/execute/sync", ['script'=>$script,'args'=>$args]);
}
function navTo($b,$sid,$url): void { wdPost($b, "/session/{$sid}/url", ['url'=>$url]); }
function currentUrl($b,$sid): string {
    $r = wdGet($b, "/session/{$sid}/url");
    return is_string($r['value'] ?? null) ? $r['value'] : '';
}
function saveScreenshot($b,$sid,$path): void {
    $r = wdGet($b, "/session/{$sid}/screenshot");
    if (!empty($r['value']) && is_string($r['value'])) @file_put_contents($path, base64_decode($r['value']));
}
function pressKey($b,$sid,$key): void {
    $map = ['Enter'=>"\u{E007}", 'Tab'=>"\u{E004}", 'Escape'=>"\u{E00C}", 'Space'=>"\u{E00D}"];
    $code = $map[$key] ?? $key;
    wdPost($b, "/session/{$sid}/actions", ['actions'=>[[
        'type'=>'key','id'=>'kb','actions'=>[
            ['type'=>'keyDown','value'=>$code],
            ['type'=>'keyUp',  'value'=>$code],
        ],
    ]]]);
    usleep(300000);
    wdDelete($b, "/session/{$sid}/actions");
}
function aguardarDownload(string $pasta, int $timeout = 30): ?string {
    $antes = array_flip(@scandir($pasta) ?: []);
    $end = time() + $timeout;
    while (time() < $end) {
        $agora = array_flip(@scandir($pasta) ?: []);
        $novos = array_diff_key($agora, $antes);
        foreach ($novos as $f => $_) {
            $f = (string)$f;
            if (strtolower(substr($f, -10)) === '.crdownload') continue;
            if (strtolower(pathinfo($f, PATHINFO_EXTENSION)) !== 'pdf') continue;
            usleep(600000);
            if (is_file($pasta . '/' . $f)) return $pasta . '/' . $f;
        }
        sleep(1);
    }
    return null;
}

// ============================================================
// SELECT2 — fluxo identico ao debug validado
// ============================================================
function preencherSelect2(string $b, string $sid, string $selectId, string $texto, string $label): bool
{
    execJs($b, $sid, "
        var \$sel = jQuery('#' + arguments[0]);
        if (!\$sel.length) return false;
        \$sel.select2('open');
        return true;
    ", [$selectId]);

    $searchId = null;
    for ($i = 0; $i < 20; $i++) {
        usleep(300000);
        $searchId = findEl($b, $sid, 'css selector', '.select2-container--open .select2-search__field');
        if ($searchId) break;
    }
    if (!$searchId) { logPhase("    [$label] search field nao apareceu"); return false; }

    clickEl($b, $sid, $searchId);
    usleep(200000);
    execJs($b, $sid, "
        var el = document.activeElement;
        if (el && el.classList.contains('select2-search__field')) {
            el.value = '';
            el.dispatchEvent(new Event('input', {bubbles:true}));
        }
    ");
    wdPost($b, "/session/{$sid}/element/{$searchId}/value",
        ['text' => $texto, 'value' => preg_split('//u', $texto, -1, PREG_SPLIT_NO_EMPTY)]);

    $temOpcao = false;
    for ($i = 0; $i < 30; $i++) {
        usleep(400000);
        $r = execJs($b, $sid, "
            var lis = document.querySelectorAll('.select2-results__option--selectable, .select2-results__option');
            for (var i = 0; i < lis.length; i++) {
                var t = (lis[i].textContent || '').trim();
                if (t && t !== 'Carregando...' && t !== 'Loading...'
                    && !lis[i].classList.contains('select2-results__message')) return true;
            }
            return false;
        ");
        if (($r['value'] ?? false) === true) { $temOpcao = true; break; }
    }
    if (!$temOpcao) {
        execJs($b, $sid, "jQuery('#' + arguments[0]).select2('close');", [$selectId]);
        logPhase("    [$label] nenhuma opcao retornada");
        return false;
    }

    execJs($b, $sid, "
        var li = document.querySelector('.select2-results__option--selectable, .select2-results__option:not(.select2-results__message)');
        if (li) {
            li.dispatchEvent(new MouseEvent('mousedown', {bubbles:true, cancelable:true, view:window}));
            li.dispatchEvent(new MouseEvent('mouseup',   {bubbles:true, cancelable:true, view:window}));
            li.dispatchEvent(new MouseEvent('click',     {bubbles:true, cancelable:true, view:window}));
        }
    ");
    usleep(300000);
    pressKey($b, $sid, 'Enter');
    sleep(1);

    $r = execJs($b, $sid, "return jQuery('#' + arguments[0]).val();", [$selectId]);
    $valor = (string)($r['value'] ?? '');

    execJs($b, $sid, "var \$s = jQuery('#' + arguments[0]); if (\$s.data('select2') && \$s.data('select2').isOpen()) \$s.select2('close');", [$selectId]);

    if ($valor === '') logPhase("    [$label] valor continua vazio apos Enter");
    return $valor !== '';
}

// ============================================================
// SESSAO
// ============================================================
$args = ['--disable-gpu','--no-sandbox','--disable-dev-shm-usage',
         '--disable-blink-features=AutomationControlled'];
if ($headless) $args[] = '--headless=new';

$caps = ['capabilities'=>['alwaysMatch'=>[
    'browserName' => 'MicrosoftEdge',
    'ms:edgeOptions' => [
        'args'  => $args,
        'prefs' => [
            'download.default_directory'         => str_replace('/', '\\', $tmpDir),
            'download.prompt_for_download'       => false,
            'download.directory_upgrade'         => true,
            'plugins.always_open_pdf_externally' => true,
        ],
    ],
]]];

$sess = wdPost($driverUrl, '/session', $caps);
$sid  = $sess['value']['sessionId'] ?? null;
if (!$sid) {
    proc_terminate($driverProc);
    dbUpdate($pdo, $jobId, ['status'=>'erro','etapa'=>'spawn','erro'=>'Falha ao criar sessao','finalizado_em'=>date('Y-m-d H:i:s')]);
    $jobFinalizado = true; exit(8);
}

wdPost($driverUrl, "/session/{$sid}/chromium/send_command", [
    'cmd' => 'Page.setDownloadBehavior',
    'params' => ['behavior'=>'allow', 'downloadPath'=>str_replace('/', '\\', $tmpDir)],
]);
wdPost($driverUrl, "/session/{$sid}/chromium/send_command", [
    'cmd' => 'Page.addScriptToEvaluateOnNewDocument',
    'params' => ['source' => "Object.defineProperty(navigator, 'webdriver', {get: () => undefined});"],
]);
logPhase("[sess] criada, headless=" . ($headless ? 'SIM' : 'NAO'));

// ============================================================
// FASE 1: LOGIN
// ============================================================
logPhase("══════════════════════════════════════");
logPhase("[login] iniciando (3 tentativas se necessario)");
dbUpdate($pdo, $jobId, ['etapa'=>'login','progresso'=>3]);
setStatus($statusFile, ['status'=>'rodando','etapa'=>'login','progresso'=>3,'total'=>$total]);

$urlBase  = 'https://sisdnit.dnit.gov.br/sisdnit/jsp/';
$urlReset = 'https://sisdnit.dnit.gov.br/sisdnit/jsp/siac/relatorio_historico_medicoes.do?tipoAcao=reset';

$loginOk = false;
$loginsFalhas = [];

for ($tentativa = 1; $tentativa <= 3 && !$loginOk; $tentativa++) {
    logPhase("[login] --- tentativa $tentativa/3 ---");
    try {
        navTo($driverUrl, $sid, $urlBase);
        sleep(2);

        $ready = false;
        $diag = [];
        for ($i = 0; $i < 40; $i++) {
            $r = execJs($driverUrl, $sid, "
                return JSON.stringify({
                    ready: document.readyState === 'complete',
                    form: !!document.forms[0],
                    encriptar: typeof index !== 'undefined' && typeof index.encriptarEnviar === 'function'
                });
            ");
            $diag = json_decode((string)($r['value'] ?? '{}'), true) ?: [];
            if (($diag['ready'] ?? false) && ($diag['form'] ?? false) && ($diag['encriptar'] ?? false)) {
                $ready = true; break;
            }
            usleep(300000);
        }
        logPhase("[login]   JS pronto=" . ($ready ? 'SIM' : 'NAO') . " " . json_encode($diag));

        if (!$ready) {
            saveScreenshot($driverUrl, $sid, $loginDir . "/t{$tentativa}_js_nao_pronto.png");
            $loginsFalhas[] = "t{$tentativa}: JS nao pronto";
            continue;
        }

        execJs($driverUrl, $sid, "
            var form = document.forms[0];
            var cpf = form.querySelector('[name=cpf]');
            var senha = form.querySelector('[name=senhaTemp]');
            function preencher(el, valor) {
                el.focus(); el.value = '';
                for (var i = 0; i < valor.length; i++) {
                    el.value += valor[i];
                    el.dispatchEvent(new KeyboardEvent('keydown', {key: valor[i], bubbles: true}));
                    el.dispatchEvent(new KeyboardEvent('keypress', {key: valor[i], bubbles: true}));
                    el.dispatchEvent(new InputEvent('input', {data: valor[i], bubbles: true}));
                    el.dispatchEvent(new KeyboardEvent('keyup', {key: valor[i], bubbles: true}));
                }
                el.dispatchEvent(new Event('change', {bubbles: true}));
                el.dispatchEvent(new Event('blur', {bubbles: true}));
            }
            preencher(cpf, arguments[0]);
            preencher(senha, arguments[1]);
        ", [$usuario, $senha]);
        sleep(1);

        $resEnv = execJs($driverUrl, $sid, "
            try { index.encriptarEnviar(document.forms[0]); return 'OK'; }
            catch(e) { return 'ERRO: ' + e.message; }
        ");
        logPhase("[login]   encriptarEnviar: " . ($resEnv['value'] ?? '?'));

        // ===== DETECCAO DE LOGIN =====
        // O SIAC mantem a URL em login.do mesmo apos autenticar.
        // Portanto: sucesso = URL muda OU o form [name=cpf] some de forma persistente.
        $loginOk = false;
        $formGoneCount = 0;
        for ($i = 0; $i < 30; $i++) {           // ate 15s
            usleep(500000);

            $path = parse_url(currentUrl($driverUrl, $sid), PHP_URL_PATH) ?? '';
            if ($path !== '/sisdnit/jsp/' && $path !== '/sisdnit/jsp/index.jsp'
                && !str_contains($path, 'login.do')) {
                $loginOk = true;
                logPhase("[login]   OK (URL) em ~" . (($i + 1) * 0.5) . "s -> $path");
                break;
            }

            $r = execJs($driverUrl, $sid,
                "return !!document.querySelector('[name=cpf]');");
            $hasForm = ($r['value'] ?? true);
            if ($hasForm === false) {
                $formGoneCount++;
                if ($formGoneCount >= 2) {      // 2 checagens consecutivas sem form
                    $loginOk = true;
                    logPhase("[login]   OK (conteudo) em ~" . (($i + 1) * 0.5) . "s");
                    break;
                }
            } else {
                $formGoneCount = 0;
            }
        }

        if (!$loginOk) {
            $urlFinal = currentUrl($driverUrl, $sid);
            logPhase("[login]   falhou. URL: $urlFinal");
            saveScreenshot($driverUrl, $sid, $loginDir . "/t{$tentativa}_timeout.png");
            $loginsFalhas[] = "t{$tentativa}: timeout URL ($urlFinal)";
        }
    } catch (Throwable $e) {
        logPhase("[login]   excecao: " . $e->getMessage());
        saveScreenshot($driverUrl, $sid, $loginDir . "/t{$tentativa}_excecao.png");
        $loginsFalhas[] = "t{$tentativa}: " . $e->getMessage();
    }
}

if (!$loginOk) {
    saveScreenshot($driverUrl, $sid, $loginDir . '/login_falhou_final.png');
    wdDelete($driverUrl, "/session/{$sid}");
    proc_terminate($driverProc);

    $msg = "Login falhou apos 3 tentativas.\nDetalhes:\n  - " . implode("\n  - ", $loginsFalhas)
         . "\nScreenshots em: $loginDir\nDica: SIAC bloqueia login em headless. headless="
         . ($headless ? 'SIM' : 'NAO');

    dbUpdate($pdo, $jobId, ['status'=>'erro','etapa'=>'login','login_ok'=>0,
        'erro'=>$msg,'finalizado_em'=>date('Y-m-d H:i:s')]);
    setStatus($statusFile, ['status'=>'erro','etapa'=>'login','erro'=>$msg]);
    $jobFinalizado = true;
    fwrite(STDERR, $msg . "\n");
    exit(9);
}

dbUpdate($pdo, $jobId, ['login_ok'=>1,'etapa'=>'validando','progresso'=>4]);

// ============================================================
// FASE 2: PAGINA DE RELATORIO
// ============================================================
logPhase("══════════════════════════════════════");
logPhase("[valid] abrindo pagina de relatorio...");
navTo($driverUrl, $sid, $urlReset);
sleep(1);

$pageReady = false;
for ($i = 0; $i < 60; $i++) {
    usleep(500000);
    $r = execJs($driverUrl, $sid, "
        return JSON.stringify({
            ready: document.readyState,
            jquery: typeof jQuery !== 'undefined',
            select2: typeof jQuery !== 'undefined' && typeof jQuery.fn.select2 === 'function',
            campo: !!document.getElementById('select2-idFiscalizacaoUnidadeResponsavel-container')
        });
    ");
    $d = json_decode((string)($r['value'] ?? '{}'), true) ?: [];
    if (($d['ready'] ?? '') === 'complete' && ($d['jquery'] ?? false) && ($d['select2'] ?? false) && ($d['campo'] ?? false)) {
        $pageReady = true;
        logPhase("[valid] pronto em " . ($i * 0.5) . "s");
        break;
    }
}

if (!$pageReady) {
    saveScreenshot($driverUrl, $sid, $falhasDir . '/sem_relatorio.png');
    wdDelete($driverUrl, "/session/{$sid}");
    proc_terminate($driverProc);
    dbUpdate($pdo, $jobId, ['status'=>'erro','etapa'=>'validacao',
        'erro'=>'Pagina de relatorio nao carregou','finalizado_em'=>date('Y-m-d H:i:s')]);
    $jobFinalizado = true;
    exit(10);
}

// ============================================================
// PREPARA INSERT
// ============================================================
$sqlDel = $pdo->prepare('DELETE FROM `siac_medicoes` WHERE contrato = ?');
$sqlIns = $pdo->prepare('
    INSERT INTO `siac_medicoes`
        (importacao_id, contrato, numero_medicao, data_inicio, data_termino, mes_referencia,
         processo_suporte, processo_pagamento,
         valor_pi_liquido, valor_pi_acumulado,
         reajustamento_liquido, ajuste_contratual_liquido,
         reajustamento_acumulado_consolidado,
         total_liquido, total_acumulado)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
');

// ============================================================
// LOOP DE CONTRATOS
// ============================================================
$ignorados = [];
$totalMed  = 0;
$relatorio = [];   // <-- NOVO
$parser    = new \Smalot\PdfParser\Parser();

logPhase("══════════════════════════════════════");
logPhase("[loop] iniciando $total contratos");

foreach ($contratos as $i => $item) {
    $contrato = trim((string)($item['Contrato'] ?? ''));
    $estado   = trim((string)($item['Estado']   ?? ''));
    $pct      = round(5 + ($i / max(1, $total)) * 88, 1);
    $inicioContrato = time();

    dbUpdate($pdo, $jobId, [
        'etapa'=>'extraindo','progresso'=>$pct,'processados'=>$i,
        'contrato_atual'=>$contrato,'inseridos'=>$totalMed,
    ]);
    setStatus($statusFile, [
        'status'=>'rodando','etapa'=>'extraindo','progresso'=>$pct,
        'total'=>$total,'processados'=>$i,'contrato_atual'=>$contrato,
        'medicoes_inseridas'=>$totalMed,
    ]);

    logPhase("[$i/$total] $contrato ($estado)");

    $ignorar = false;
    $motivo  = '';
    $nOk     = 0;

    try {  // ← OUTER try (era só o antigo)
        navTo($driverUrl, $sid, $urlReset);
        sleep(1);

        $pronto = false;
        for ($j = 0; $j < 20; $j++) {
            $r = execJs($driverUrl, $sid, "
                return typeof jQuery !== 'undefined'
                    && typeof jQuery.fn.select2 === 'function'
                    && !!document.getElementById('select2-idFiscalizacaoUnidadeResponsavel-container');
            ");
            if (($r['value'] ?? false) === true) { $pronto = true; break; }
            usleep(500000);
        }
        if (!$pronto) throw new Exception('Pagina nao carregou');

        if (!preencherSelect2($driverUrl, $sid, 'idFiscalizacaoUnidadeResponsavel', $estado, 'UNIDADE')) {
            saveScreenshot($driverUrl, $sid, $falhasDir . '/sel_unidade_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $contrato) . '.png');
            throw new Exception('Falha Unidade');
        }
        usleep(600000);

        if (!preencherSelect2($driverUrl, $sid, 'codContrato', $contrato, 'CONTRATO')) {
            saveScreenshot($driverUrl, $sid, $falhasDir . '/sel_contrato_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $contrato) . '.png');
            throw new Exception('Falha Contrato');
        }
        usleep(600000);

        $html = execJs($driverUrl, $sid, "return document.body ? document.body.innerText : '';");
        if (stripos((string)($html['value'] ?? ''), 'No results found') !== false) {
            $ignorar = true; $motivo = 'No results found';
            continue;
        }

        logPhase("    -> gerando PDF...");
        $r = execJs($driverUrl, $sid, "
            if (typeof relatoriosMedicao === 'undefined' || typeof relatoriosMedicao.emitirPDF !== 'function') {
                return JSON.stringify({ok:false, erro:'funcao nao existe'});
            }
            try { relatoriosMedicao.emitirPDF('gerar'); return JSON.stringify({ok:true}); }
            catch(e) { return JSON.stringify({ok:false, erro: e.message}); }
        ");
        $resGerar = json_decode((string)($r['value'] ?? '{}'), true) ?: [];
        if (!($resGerar['ok'] ?? false)) throw new Exception('emitirPDF: ' . ($resGerar['erro'] ?? '?'));

        $pdf = aguardarDownload($tmpDir, 40);
        if (!$pdf) { $ignorar = true; $motivo = 'Download falhou'; continue; }

        logPhase("    -> parse PDF...");
        $base = preg_replace('/[^A-Za-z0-9_\-]/', '_', $contrato);
        try {  // ← INNER try (já existia)
            $doc = $parser->parseFile($pdf);
            $texto = $doc->getText();
            $medicoes = parseMedicoes($contrato, $texto);
            logPhase("    -> " . count($medicoes) . " medicoes parseadas");

            if (empty($medicoes)) {
                $destPdf = $falhasDir . '/' . $base . '.pdf';
                $destTxt = $falhasDir . '/' . $base . '.txt';
                @copy($pdf, $destPdf);
                @file_put_contents($destTxt, $texto);
                $ignorar = true;
                $motivo  = 'Parse 0 medicoes (PDF+TXT em falhas/' . basename($destPdf) . ')';
                continue;
            }

            $pdo->beginTransaction();
            $sqlDel->execute([$contrato]);
            foreach ($medicoes as $idx => $m) {
                try {
                    $sqlIns->execute([
                        $jobId, $contrato,
                        $m['numero_medicao'], $m['data_inicio'], $m['data_termino'],
                        $m['mes_referencia'],
                        $m['processo_suporte'], $m['processo_pagamento'],
                        $m['valor_pi_liquido'], $m['valor_pi_acumulado'],
                        $m['reajustamento_liquido'], $m['ajuste_contratual_liquido'],
                        $m['reajustamento_acumulado_consolidado'],
                        $m['total_liquido'], $m['total_acumulado'],
                    ]);
                    $nOk++;
                } catch (Throwable $e) {
                    fwrite(STDERR, "[insert] linha #$idx falhou: " . $e->getMessage()
                        . " | dados: " . json_encode($m, JSON_UNESCAPED_UNICODE) . "\n");
                    throw $e;
                }
            }
            $pdo->commit();
            $totalMed += $nOk;
            logPhase("    -> $nOk medicao(oes) inserida(s)");
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $destPdf = $falhasDir . '/' . $base . '.pdf';
            $destErr = $falhasDir . '/' . $base . '.err.txt';
            @copy($pdf, $destPdf);
            @file_put_contents($destErr,
                "ERRO: " . $e->getMessage() . "\n\nTRACE:\n" . $e->getTraceAsString() . "\n");
            $ignorar = true;
            $motivo  = 'Erro parse/DB: ' . mb_substr($e->getMessage(), 0, 200)
                     . ' (detalhes em falhas/' . basename($destErr) . ')';
        } finally {
            @unlink($pdf);
        }
    } catch (Throwable $e) {
        $ignorar = true;
        $motivo  = 'Erro: ' . mb_substr($e->getMessage(), 0, 200);
    } finally {  // ← NOVO: registra resultado sempre (funciona com continue e exceptions)
        $statusRel = 'OK';
        $motivoRel = null;

        if ($ignorar) {
            // "Erro..." = ERRO; "No results found", "Download falhou", "Parse 0..." = IGNORADO
            $statusRel = (stripos($motivo, 'Erro') === 0) ? 'ERRO' : 'IGNORADO';
            $motivoRel = $motivo;
            $ignorados[] = ['Contrato'=>$contrato, 'Estado'=>$estado, 'Motivo'=>$motivo];
            logPhase("    ignorado: $motivo");
        }

        $relatorio[] = [
            'contrato'     => $contrato,
            'estado'       => $estado,
            'status'       => $statusRel,
            'medicoes'     => $nOk,
            'motivo'       => $motivoRel,
            'duracao_seg'  => time() - $inicioContrato,
        ];

        // Grava parcial a cada 3 contratos (para sobreviver a cancelamento/falha)
        if (count($relatorio) % 3 === 0 || count($relatorio) === $total) {
            @file_put_contents(
                $outputDir . '/relatorio.json',
                json_encode($relatorio, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            );
        }
    }
}

// ============================================================
// FINAL
// ============================================================
wdDelete($driverUrl, "/session/{$sid}");
proc_terminate($driverProc);
@file_put_contents(
    $outputDir . '/relatorio.json',
    json_encode($relatorio, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
);

dbUpdate($pdo, $jobId, [
    'status'=>'concluido','etapa'=>'concluido','progresso'=>100,
    'processados'=>$total,'inseridos'=>$totalMed,
    'contratos_afetados'=>count($contratos) - count($ignorados),
    'ignorados'=>json_encode($ignorados, JSON_UNESCAPED_UNICODE),
    'finalizado_em'=>date('Y-m-d H:i:s'),
]);
setStatus($statusFile, [
    'status'=>'concluido','etapa'=>'concluido','progresso'=>100,
    'total'=>$total,'processados'=>$total,'medicoes_inseridas'=>$totalMed,
    'contratos_ignorados'=>count($ignorados),'ignorados_detalhe'=>$ignorados,
    'iniciado_em'=>date('Y-m-d H:i:s',$inicio),'finalizado_em'=>date('Y-m-d H:i:s'),
    'tempo_segundos'=>time()-$inicio,
]);

$jobFinalizado = true;
logPhase("══════════════════════════════════════");
logPhase("[fim] $totalMed medicoes inseridas, " . count($ignorados) . " ignorados, " . (time()-$inicio) . "s total");
exit(0);

// ============================================================
// PARSE PDF  (v11 — orientado por offset, nao por regex de linha)
// ============================================================
function parseMedicoes(string $contrato, string $texto): array {
    $out = [];
    $linhas = preg_split('/\r\n|\r|\n/', $texto) ?: [];
    foreach ($linhas as $linha) {
        $linha = trim($linha);
        if ($linha === '') continue;

        // 1) Datas no inicio:  DD/MM/YYYY  DD/MM/YYYY  <resto>
        if (!preg_match('/^(\d{2}\/\d{2}\/\d{4})\s+(\d{2}\/\d{2}\/\d{4})\s+(.+)$/u', $linha, $m)) {
            continue;
        }
        $dataIniTxt = $m[1];
        $dataFimTxt = $m[2];
        $resto      = $m[3];

        // 2) Extrai todos os valores monetarios COM offset
        //    Regex aceita sinal negativo (Ajuste pode ser -778.192,70)
        if (!preg_match_all('/-?\d{1,3}(?:\.\d{3})*,\d{2}/', $resto, $mm, PREG_OFFSET_CAPTURE)) {
            continue;
        }
        $moneys = $mm[0];
        if (count($moneys) < 7) continue;   // linha incompleta (raro)

        // 3) Ordem semantica: VPL, VPA, RL, RAC, TL, TA, Ajuste
        $valores = [];
        for ($k = 0; $k < 7; $k++) {
            $valores[$k] = normalizarValor($moneys[$k][0]);
        }

        // 4) Med: digitos entre o fim do money[5] (Total Acum) e o inicio do money[6] (Ajuste)
        $taEnd       = $moneys[5][1] + strlen($moneys[5][0]);
        $ajusteStart = $moneys[6][1];
        if ($ajusteStart <= $taEnd) continue;
        $between = substr($resto, $taEnd, $ajusteStart - $taEnd);
        if (!preg_match('/(\d+)/', $between, $mv)) continue;
        $med = $mv[1];

        // 5) Processos: padrao NNNNN.NNNNNN/AAAA-NN  depois do Ajuste
        $ajusteEnd   = $moneys[6][1] + strlen($moneys[6][0]);
        $afterAjuste = substr($resto, $ajusteEnd);
        preg_match_all('/\d{5,6}\.\d{6}\/\d{4}-\d{2}/', $afterAjuste, $mp);
        $processos = $mp[0];

        $dIni = converterData($dataIniTxt);
        $dFim = converterData($dataFimTxt);

        $out[] = [
            'numero_medicao' => $med,
            'data_inicio'    => $dIni,
            'data_termino'   => $dFim,
            'mes_referencia' => $dFim ? substr($dFim, 0, 7) . '-01' : null,
            // Mapeamento provisorio: 1 processo -> suporte; 2 processos -> suporte + pagamento
            // (logaremos contagem para revisao; o .txt inverte a ordem em algumas linhas)
            'processo_suporte'   => $processos[0] ?? null,
            'processo_pagamento' => $processos[1] ?? null,

            'valor_pi_liquido'                   => $valores[0],
            'valor_pi_acumulado'                 => $valores[1],
            'reajustamento_liquido'              => $valores[2],
            'reajustamento_acumulado_consolidado'=> $valores[3],
            'total_liquido'                      => $valores[4],
            'total_acumulado'                    => $valores[5],
            'ajuste_contratual_liquido'          => $valores[6],
        ];
    }
    return $out;
}

function converterData(?string $s): ?string {
    if (!$s) return null;
    if (preg_match('#^(\d{2})/(\d{2})/(\d{4})#', $s, $m)) return "{$m[3]}-{$m[2]}-{$m[1]}";
    if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s)) return substr($s, 0, 10);
    return null;
}
function normalizarValor(?string $s): ?float {
    if ($s === null || $s === '' || $s === '-') return null;
    $s = trim($s);
    if (preg_match('/^-?\d{1,3}(\.\d{3})*(,\d+)?$/', $s)) {
        return (float)str_replace(',', '.', str_replace('.', '', $s));
    }
    return is_numeric($s) ? (float)$s : null;
}