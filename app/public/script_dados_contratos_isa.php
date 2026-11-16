<?php
// app/public/script_dados_contratos_isa.php

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
$jobId        = $opts['job']        ?? ('dados_' . date('Ymd_His'));

$headless = isset($opts['headless'])
    ? filter_var($opts['headless'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
    : false;
if ($headless === null) $headless = false;

if (!$contratosArq || !is_file($contratosArq)) { fwrite(STDERR, "Contratos invalido\n"); exit(1); }
if (!$outputDir) { fwrite(STDERR, "--output-dir obrigatorio\n"); exit(1); }
@mkdir($outputDir, 0775, true);

$statusFile = rtrim($outputDir, '/\\') . '/status.json';
$loginDir   = rtrim($outputDir, '/\\') . '/login_debug';
@mkdir($loginDir, 0775, true);

$usuario    = getenv('SIAC_USER')  ?: '';
$senha      = getenv('SIAC_PASS')  ?: '';
$edgeDriver = getenv('EDGEDRIVER') ?: '';
if (!$usuario || !$senha) { fwrite(STDERR, "SIAC_USER/SIAC_PASS ausentes\n"); exit(2); }
if (!$edgeDriver || !is_file($edgeDriver)) { fwrite(STDERR, "EDGEDRIVER invalido\n"); exit(3); }

// ============================================================
// MySQL
// ============================================================
require_once $configPath;
/** @var PDO $pdo */
if (!isset($pdo) || !($pdo instanceof PDO)) { fwrite(STDERR, "config.php sem \$pdo\n"); exit(4); }
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$contratos = json_decode(file_get_contents($contratosArq), true);
if (!is_array($contratos) || !$contratos) { fwrite(STDERR, "Contratos vazios\n"); exit(6); }
$total  = count($contratos);
$inicio = time();

// ============================================================
// HELPERS
// ============================================================
function setStatus(string $file, array $data) {
    @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}
function dbUpdate(PDO $pdo, string $jobId, array $campos) {
    if (!$campos) return;
    $set = []; $p = [];
    foreach ($campos as $k => $v) { $set[] = "`$k` = ?"; $p[] = $v; }
    $set[] = "`atualizado_em` = NOW()";
    $p[] = $jobId;
    try {
        $pdo->prepare("UPDATE `siac_import_jobs` SET " . implode(', ', $set) . " WHERE `job_id` = ?")
            ->execute($p);
    } catch (Throwable $e) { fwrite(STDERR, "[dbUpdate] " . $e->getMessage() . "\n"); }
}
function logPhase(string $msg) {
    fwrite(STDOUT, "[" . date('H:i:s') . "] $msg\n");
    @flush();
}

// ============================================================
// SHUTDOWN
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
    'total'=>$total,'processados'=>0,'contratos_inseridos'=>0,
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

function findEl($b,$sid,$using,$value) {
    $r = wdPost($b, "/session/{$sid}/element", ['using'=>$using,'value'=>$value]);
    return $r['value'][ELEM_KEY] ?? null;
}
function clickEl($b,$sid,$elId) { wdPost($b, "/session/{$sid}/element/{$elId}/click", []); }
function execJs($b,$sid,$script,$args=[]): array {
    return wdPost($b, "/session/{$sid}/execute/sync", ['script'=>$script,'args'=>$args]);
}
function navTo($b,$sid,$url) { wdPost($b, "/session/{$sid}/url", ['url'=>$url]); }
function currentUrl($b,$sid): string {
    $r = wdGet($b, "/session/{$sid}/url");
    return is_string($r['value'] ?? null) ? $r['value'] : '';
}
function saveScreenshot($b,$sid,$path) {
    $r = wdGet($b, "/session/{$sid}/screenshot");
    if (!empty($r['value']) && is_string($r['value'])) @file_put_contents($path, base64_decode($r['value']));
}
function pressKey($b,$sid,$key) {
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

// ============================================================
// SELECT2
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
// HELPERS DE EXTRAÇÃO
// ============================================================
function extrairParesTabela($b, $sid, string $tabelaId): array {
    $r = execJs($b, $sid, "
        var t = document.getElementById(arguments[0]);
        if (!t) return '{}';
        var pares = {};
        var linhas = t.querySelectorAll('tbody tr');
        for (var i = 0; i < linhas.length; i++) {
            var cels = linhas[i].querySelectorAll('th, td');
            if (cels.length >= 2) {
                var rot = (cels[0].innerText || '').trim();
                var val = (cels[1].innerText || '').trim();
                if (rot) pares[rot] = val;
            }
        }
        return JSON.stringify(pares);
    ", [$tabelaId]);
    return json_decode((string)($r['value'] ?? '{}'), true) ?: [];
}

function extrairValorMonetario($s) {
    if ($s === null) return null;
    $s = trim($s);
    if ($s === '' || $s === '-' || strcasecmp($s, 'N/A') === 0) return null;
    $s = preg_replace('/[Rr]\$\s*/', '', $s);
    if (preg_match('/^-?\d{1,3}(\.\d{3})*(,\d+)?$/', $s)) {
        return (float)str_replace(',', '.', str_replace('.', '', $s));
    }
    if (is_numeric($s)) return (float)$s;
    return null;
}

function extrairData($s) {
    if (!$s) return null;
    $s = trim($s);
    if ($s === '' || $s === '-' || strcasecmp($s, 'N/A') === 0) return null;
    if (preg_match('#^(\d{2})/(\d{2})/(\d{4})#', $s, $m)) return "{$m[3]}-{$m[2]}-{$m[1]}";
    if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s)) return substr($s, 0, 10);
    return null;
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
        'prefs' => ['download.prompt_for_download' => false],
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
    'cmd' => 'Page.addScriptToEvaluateOnNewDocument',
    'params' => ['source' => "Object.defineProperty(navigator, 'webdriver', {get: () => undefined});"],
]);
logPhase("[sess] criada, headless=" . ($headless ? 'SIM' : 'NAO'));

// ============================================================
// LOGIN
// ============================================================
logPhase("══════════════════════════════════════");
logPhase("[login] iniciando (3 tentativas se necessario)");
dbUpdate($pdo, $jobId, ['etapa'=>'login','progresso'=>3]);
setStatus($statusFile, ['status'=>'rodando','etapa'=>'login','progresso'=>3,'total'=>$total]);

$urlBase = 'https://sisdnit.dnit.gov.br/sisdnit/jsp/';
$loginOk = false;
$loginsFalhas = [];

for ($tentativa = 1; $tentativa <= 3 && !$loginOk; $tentativa++) {
    logPhase("[login] --- tentativa $tentativa/3 ---");
    try {
        navTo($driverUrl, $sid, $urlBase);
        sleep(2);

        $ready = false; $diag = [];
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

        $loginOk = false; $formGoneCount = 0;
        for ($i = 0; $i < 30; $i++) {
            usleep(500000);
            $path = parse_url(currentUrl($driverUrl, $sid), PHP_URL_PATH) ?? '';
            // PHP 7.0: strpos === false no lugar de str_contains
            if ($path !== '/sisdnit/jsp/' && $path !== '/sisdnit/jsp/index.jsp'
                && strpos($path, 'login.do') === false) {
                $loginOk = true;
                logPhase("[login]   OK (URL) em ~" . (($i + 1) * 0.5) . "s -> $path");
                break;
            }
            $r = execJs($driverUrl, $sid, "return !!document.querySelector('[name=cpf]');");
            $hasForm = ($r['value'] ?? true);
            if ($hasForm === false) {
                $formGoneCount++;
                if ($formGoneCount >= 2) {
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
         . "\nScreenshots em: $loginDir";
    dbUpdate($pdo, $jobId, ['status'=>'erro','etapa'=>'login','login_ok'=>0,
        'erro'=>$msg,'finalizado_em'=>date('Y-m-d H:i:s')]);
    setStatus($statusFile, ['status'=>'erro','etapa'=>'login','erro'=>$msg]);
    $jobFinalizado = true;
    fwrite(STDERR, $msg . "\n");
    exit(9);
}

dbUpdate($pdo, $jobId, ['login_ok'=>1,'etapa'=>'extraindo','progresso'=>5]);

// ============================================================
// PREPARA INSERT
// ============================================================
$sqlDel = $pdo->prepare('DELETE FROM `siac_contratos_dados` WHERE contrato = ?');
$sqlIns = $pdo->prepare('
    INSERT INTO `siac_contratos_dados` (
        importacao_id, contrato, estado, numero_contrato, empresa_executora,
        tipo_contrato, situacao, processo_base, empresa_supervisora,
        modalidade, tipo_licitacao, edital, tipo_intervencao, objeto,
        preco_inicial, total_aditivos, pi_vigente, total_reajustamento, total_pi_r,
        data_assinatura, inicio_vigencia, inicio_servicos, termino_servicos,
        termino_vigencia, data_base
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
');

// ============================================================
// LOOP DE CONTRATOS
// ============================================================
$ignorados = [];
$totalOk   = 0;
$urlConsulta = 'https://sisdnit.dnit.gov.br/sisdnit/jsp/siac/consulta_contrato.do?tipoAcao=reset';

logPhase("══════════════════════════════════════");
logPhase("[loop] iniciando $total contratos");

foreach ($contratos as $i => $item) {
    $contrato = trim((string)($item['Contrato'] ?? ''));
    $estado   = trim((string)($item['Estado']   ?? ''));
    $pct      = round(5 + ($i / max(1, $total)) * 88, 1);

    dbUpdate($pdo, $jobId, [
        'etapa'=>'extraindo','progresso'=>$pct,'processados'=>$i,
        'contrato_atual'=>$contrato,'inseridos'=>$totalOk,
    ]);
    setStatus($statusFile, [
        'status'=>'rodando','etapa'=>'extraindo','progresso'=>$pct,
        'total'=>$total,'processados'=>$i,'contrato_atual'=>$contrato,
        'contratos_inseridos'=>$totalOk,
    ]);

    logPhase("[$i/$total] $contrato ($estado)");

    $ignorar = false;
    $motivo  = '';

    try {
        navTo($driverUrl, $sid, $urlConsulta);
        sleep(1);

        $pronto = false;
        for ($j = 0; $j < 20; $j++) {
            $r = execJs($driverUrl, $sid, "
                return typeof jQuery !== 'undefined'
                    && typeof jQuery.fn.select2 === 'function'
                    && !!document.getElementById('select2-idUnidFiscContr-container');
            ");
            if (($r['value'] ?? false) === true) { $pronto = true; break; }
            usleep(500000);
        }
        if (!$pronto) throw new Exception('Pagina nao carregou');

        if (!preencherSelect2($driverUrl, $sid, 'idUnidFiscContr', $estado, 'UNIDADE')) {
            saveScreenshot($driverUrl, $sid, $falhasDir ?? ($outputDir . '/falhas') . '/sel_unidade_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $contrato) . '.png');
            throw new Exception('Falha Unidade');
        }
        usleep(600000);

        if (!preencherSelect2($driverUrl, $sid, 'idContrato', $contrato, 'CONTRATO')) {
            throw new Exception('Falha Contrato');
        }

        usleep(500000);
        $body = execJs($driverUrl, $sid, "return document.body ? document.body.innerText : '';");
        if (stripos((string)($body['value'] ?? ''), 'No results found') !== false) {
            $ignorar = true; $motivo = 'No results found';
            continue;
        }

        $clickConsultar = execJs($driverUrl, $sid, "
            var botoes = document.querySelectorAll('form table tfoot input[type=button], form table tfoot input[type=submit]');
            for (var i = 0; i < botoes.length; i++) {
                var v = (botoes[i].value || '').toLowerCase();
                if (v.indexOf('consult') !== -1) { botoes[i].click(); return 'OK'; }
            }
            var tfoot = document.querySelector('form table tfoot input');
            if (tfoot) { tfoot.click(); return 'OK'; }
            return 'NOT_FOUND';
        ");
        if (($clickConsultar['value'] ?? '') !== 'OK') throw new Exception('Botao Consultar nao encontrado');
        usleep(1500000);

        $temLinha = false;
        for ($j = 0; $j < 20; $j++) {
            usleep(500000);
            $r = execJs($driverUrl, $sid, "return !!document.getElementById('row');");
            if (($r['value'] ?? false) === true) { $temLinha = true; break; }
        }
        if (!$temLinha) throw new Exception('Linha de resultado nao apareceu');

        $clickLink = execJs($driverUrl, $sid, "
            var tds = document.querySelectorAll('td');
            for (var i = 0; i < tds.length; i++) {
                if ((tds[i].innerText || '').indexOf(arguments[0]) !== -1) {
                    var tr = tds[i].closest('tr');
                    if (tr) {
                        var a = tr.querySelector('a');
                        if (a) { a.click(); return 'OK'; }
                    }
                }
            }
            var row = document.getElementById('row');
            if (row) { row.click(); return 'OK_FALLBACK'; }
            return 'NOT_FOUND';
        ", [$contrato]);
        if (strpos((string)($clickLink['value'] ?? ''), 'OK') !== 0) throw new Exception('Link do contrato nao encontrado');
        usleep(1500000);

        $temContent = false;
        for ($j = 0; $j < 20; $j++) {
            usleep(500000);
            $r = execJs($driverUrl, $sid, "return !!document.getElementById('content');");
            if (($r['value'] ?? false) === true) { $temContent = true; break; }
        }
        if (!$temContent) throw new Exception('Pagina de detalhe nao carregou');

        $pares1     = extrairParesTabela($driverUrl, $sid, 'content');
        $pares2     = extrairParesTabela($driverUrl, $sid, 'divInformacoesBasicas');
        $paresValor = extrairParesTabela($driverUrl, $sid, 'divValoresContrato');
        $paresData  = extrairParesTabela($driverUrl, $sid, 'divDatasEPrazos');

        $registro = [
            'importacao_id'      => $jobId,
            'contrato'           => $contrato,
            'estado'             => $estado,
            'numero_contrato'    => $pares1['Nº do Contrato']         ?? null,
            'empresa_executora'  => $pares1['Empresa Executora']      ?? null,
            'tipo_contrato'      => $pares2['Tipo do Contrato']       ?? null,
            'situacao'           => $pares2['Situação']               ?? null,
            'processo_base'      => $pares2['Número do Processo']     ?? null,
            'empresa_supervisora'=> $pares2['Empresa Supervisora']    ?? null,
            'modalidade'         => $pares2['Modalidade']             ?? null,
            'tipo_licitacao'     => $pares2['Tipo']                   ?? null,
            'edital'             => $pares2['Número do Edital']       ?? null,
            'tipo_intervencao'   => $pares2['Tipo de Intervenção']    ?? null,
            'objeto'             => $pares2['Objeto do Contrato']     ?? null,
            'preco_inicial'      => extrairValorMonetario($paresValor['Preço Inicial']           ?? null),
            'total_aditivos'     => extrairValorMonetario($paresValor['Total de Aditivos']       ?? null),
            'pi_vigente'         => extrairValorMonetario($paresValor['PI Vigente']              ?? null),
            'total_reajustamento'=> extrairValorMonetario($paresValor['Total de Reajustamento']  ?? null),
            'total_pi_r'         => extrairValorMonetario($paresValor['Total (PI + R)']          ?? null),
            'data_assinatura'    => extrairData($paresData['Data da Assinatura']      ?? null),
            'inicio_vigencia'    => extrairData($paresData['Início da Vigência']      ?? null),
            'inicio_servicos'    => extrairData($paresData['Início dos Serviços']     ?? null),
            'termino_servicos'   => extrairData($paresData['Término dos Serviços']    ?? null),
            'termino_vigencia'   => extrairData($paresData['Término da Vigência']     ?? null),
            'data_base'          => extrairData($paresData['Data-Base']               ?? null),
        ];

        $pdo->beginTransaction();
        $sqlDel->execute([$contrato]);
        $sqlIns->execute(array_values($registro));
        $pdo->commit();
        $totalOk++;
        logPhase("    -> salvo (empresa=" . ($registro['empresa_executora'] ?: '?') . ")");

    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        $ignorar = true;
        $motivo  = 'Erro: ' . mb_substr($e->getMessage(), 0, 200);
        @mkdir($outputDir . '/falhas', 0775, true);
        saveScreenshot($driverUrl, $sid, $outputDir . '/falhas/' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $contrato) . '.png');
    }

    if ($ignorar) {
        $ignorados[] = ['Contrato'=>$contrato, 'Estado'=>$estado, 'Motivo'=>$motivo];
        logPhase("    ignorado: $motivo");
    }
}

// ============================================================
// FINAL
// ============================================================
wdDelete($driverUrl, "/session/{$sid}");
proc_terminate($driverProc);

dbUpdate($pdo, $jobId, [
    'status'=>'concluido','etapa'=>'concluido','progresso'=>100,
    'processados'=>$total,'inseridos'=>$totalOk,
    'contratos_afetados'=>$total - count($ignorados),
    'ignorados'=>json_encode($ignorados, JSON_UNESCAPED_UNICODE),
    'finalizado_em'=>date('Y-m-d H:i:s'),
]);
setStatus($statusFile, [
    'status'=>'concluido','etapa'=>'concluido','progresso'=>100,
    'total'=>$total,'processados'=>$total,'contratos_inseridos'=>$totalOk,
    'contratos_ignorados'=>count($ignorados),'ignorados_detalhe'=>$ignorados,
    'iniciado_em'=>date('Y-m-d H:i:s',$inicio),'finalizado_em'=>date('Y-m-d H:i:s'),
    'tempo_segundos'=>time()-$inicio,
]);

$jobFinalizado = true;
logPhase("══════════════════════════════════════");
logPhase("[fim] $totalOk contratos salvos, " . count($ignorados) . " ignorados, " . (time()-$inicio) . "s total");
exit(0);