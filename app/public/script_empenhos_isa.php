<?php
// app/public/script_empenhos_isa.php
//
// Worker: extrai empenhos + eventos (consulta_situacao_empenho.do + abas de detalhe)
// Tabelas destino: siac_empenhos, siac_empenho_eventos
// Baseado no scr_emp_det_paralelos.py

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
$jobId        = $opts['job']        ?? ('emp_' . date('Ymd_His'));

$headless = isset($opts['headless'])
    ? filter_var($opts['headless'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
    : false;
if ($headless === null) $headless = false;

if (!$contratosArq || !is_file($contratosArq)) { fwrite(STDERR, "Contratos invalido\n"); exit(1); }
if (!$outputDir) { fwrite(STDERR, "--output-dir obrigatorio\n"); exit(1); }
@mkdir($outputDir, 0775, true);

$statusFile = rtrim($outputDir, '/\\') . '/status.json';
$loginDir   = rtrim($outputDir, '/\\') . '/login_debug';
$falhasDir  = rtrim($outputDir, '/\\') . '/falhas';
@mkdir($loginDir, 0775, true);
@mkdir($falhasDir, 0775, true);

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
    } catch (Throwable $e) { fwrite(STDERR, "[dbUpdate] " . $e->getMessage() . "\n"); }
}
function logPhase(string $msg): void {
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
    'total'=>$total,'processados'=>0,'empenhos_inseridos'=>0,'eventos_inseridos'=>0,
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
function extrairLinhasTabela($b, $sid, string $tabelaId): array {
    $r = execJs($b, $sid, "
        var t = document.getElementById(arguments[0]);
        if (!t) return '[]';
        var out = [];
        var linhas = t.querySelectorAll('tbody tr');
        for (var i = 0; i < linhas.length; i++) {
            var tds = linhas[i].querySelectorAll('td');
            var row = [];
            for (var j = 0; j < tds.length; j++) {
                row.push((tds[j].innerText || '').trim());
            }
            if (row.length > 0) out.push(row);
        }
        return JSON.stringify(out);
    ", [$tabelaId]);
    return json_decode((string)($r['value'] ?? '[]'), true) ?: [];
}

function extrairNotaEmpenho($b, $sid): string {
    $r = execJs($b, $sid, "
        try {
            var el = document.evaluate(
                \"//*[@id='content']/form/table[1]/tbody/tr[4]/td\",
                document, null, XPathResult.FIRST_ORDERED_NODE_TYPE, null
            ).singleNodeValue;
            return el ? (el.innerText || '').trim() : '';
        } catch(e) { return ''; }
    ");
    return (string)($r['value'] ?? '');
}

function valorMonetario(?string $s): ?float {
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

function dataBR(?string $s): ?string {
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
         '--disable-blink-features=AutomationControlled',
         '--disable-extensions'];
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
// LOGIN (idêntico)
// ============================================================
logPhase("══════════════════════════════════════");
logPhase("[login] iniciando (3 tentativas se necessario)");
dbUpdate($pdo, $jobId, ['etapa'=>'login','progresso'=>3]);

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
            if ($path !== '/sisdnit/jsp/' && $path !== '/sisdnit/jsp/index.jsp'
                && !str_contains($path, 'login.do')) {
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
$sqlDelEmp = $pdo->prepare('DELETE FROM `siac_empenhos` WHERE contrato = ?');
$sqlDelEvt = $pdo->prepare('DELETE FROM `siac_empenho_eventos` WHERE contrato = ?');
$sqlInsEmp = $pdo->prepare('
    INSERT INTO `siac_empenhos`
        (importacao_id, contrato, unidade_gestora, numero_documento,
         natureza_despesa, vinc_pagamento, valor_empenho,
         valor_ajustes, valor_consumido, saldo)
    VALUES (?,?,?,?,?,?,?,?,?,?)
');
$sqlInsEvt = $pdo->prepare('
    INSERT INTO `siac_empenho_eventos`
        (importacao_id, contrato, nota_empenho, data_evento,
         evento, valor_incluido, valor_gasto)
    VALUES (?,?,?,?,?,?,?)
');

// ============================================================
// LOOP DE CONTRATOS
// ============================================================
$ignorados = [];
$totalEmp  = 0;
$totalEvt  = 0;
$urlConsulta = 'https://sisdnit.dnit.gov.br/sisdnit/jsp/siac/consulta_situacao_empenho.do?tipoAcao=reset';

logPhase("══════════════════════════════════════");
logPhase("[loop] iniciando $total contratos");

foreach ($contratos as $i => $item) {
    $contrato = trim((string)($item['Contrato'] ?? ''));
    $estado   = trim((string)($item['Estado']   ?? ''));
    $pct      = round(5 + ($i / max(1, $total)) * 88, 1);

    dbUpdate($pdo, $jobId, [
        'etapa'=>'extraindo','progresso'=>$pct,'processados'=>$i,
        'contrato_atual'=>$contrato,'inseridos'=>$totalEmp,
    ]);
    setStatus($statusFile, [
        'status'=>'rodando','etapa'=>'extraindo','progresso'=>$pct,
        'total'=>$total,'processados'=>$i,'contrato_atual'=>$contrato,
        'empenhos_inseridos'=>$totalEmp,'eventos_inseridos'=>$totalEvt,
    ]);

    logPhase("[$i/$total] $contrato ($estado)");
    $ignorar = false; $motivo = '';
    $empenhos = []; $eventos = [];

    try {
        navTo($driverUrl, $sid, $urlConsulta);
        sleep(1);

        $pronto = false;
        for ($j = 0; $j < 20; $j++) {
            $r = execJs($driverUrl, $sid, "
                return typeof jQuery !== 'undefined'
                    && typeof jQuery.fn.select2 === 'function'
                    && !!document.getElementById('select2-fiscalizadora-container');
            ");
            if (($r['value'] ?? false) === true) { $pronto = true; break; }
            usleep(500000);
        }
        if (!$pronto) throw new Exception('Pagina nao carregou');

        if (!preencherSelect2($driverUrl, $sid, 'fiscalizadora', $estado, 'UNIDADE')) {
            throw new Exception('Falha Unidade');
        }
        usleep(600000);

        if (!preencherSelect2($driverUrl, $sid, 'numeroContrato', $contrato, 'CONTRATO')) {
            throw new Exception('Falha Contrato');
        }

        usleep(500000);
        $body = execJs($driverUrl, $sid, "return document.body ? document.body.innerText : '';");
        if (stripos((string)($body['value'] ?? ''), 'No results found') !== false) {
            $ignorar = true; $motivo = 'No results found';
            continue;
        }

        // Clica em Consultar
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

        // Aguarda tabela de empenhos
        $temEmp = false;
        for ($j = 0; $j < 20; $j++) {
            usleep(500000);
            $r = execJs($driverUrl, $sid, "return !!document.getElementById('empenhos');");
            if (($r['value'] ?? false) === true) { $temEmp = true; break; }
        }
        if (!$temEmp) throw new Exception('Tabela de empenhos nao apareceu');

        // Coleta linhas de empenhos
        $linhas = extrairLinhasTabela($driverUrl, $sid, 'empenhos');

        // Coleta os hrefs dos links de cada linha de empenho (para detalhes)
        $rLinks = execJs($driverUrl, $sid, "
            var t = document.getElementById('empenhos');
            if (!t) return '[]';
            var out = [];
            var linhas = t.querySelectorAll('tbody tr');
            for (var i = 0; i < linhas.length; i++) {
                var a = linhas[i].querySelector('a');
                if (a && a.href) out.push(a.href);
            }
            return JSON.stringify(out);
        ");
        $hrefs = json_decode((string)($rLinks['value'] ?? '[]'), true) ?: [];

        // Guarda os empenhos (ainda em memória; insere depois de coletar eventos)
        foreach ($linhas as $linha) {
            $empenhos[] = $linha;
        }

        // Para cada empenho: abre nova aba, extrai eventos, fecha
        foreach ($hrefs as $href) {
            try {
                $mainHandle = wdGet($driverUrl, "/session/{$sid}/window")['value'] ?? null;

                execJs($driverUrl, $sid, "window.open(arguments[0], '_blank');", [$href]);
                usleep(800000);

                $handles = wdGet($driverUrl, "/session/{$sid}/window/handles")['value'] ?? [];
                $newHandle = end($handles);
                if (!$newHandle || $newHandle === $mainHandle) continue;

                wdPost($driverUrl, "/session/{$sid}/window", ['handle' => $newHandle]);
                usleep(500000);

                // Aguarda tabela de eventos
                $temEvt = false;
                for ($j = 0; $j < 20; $j++) {
                    usleep(500000);
                    $r = execJs($driverUrl, $sid, "return !!document.getElementById('eventos');");
                    if (($r['value'] ?? false) === true) { $temEvt = true; break; }
                }
                if (!$temEvt) {
                    wdDelete($driverUrl, "/session/{$sid}/window");
                    wdPost($driverUrl, "/session/{$sid}/window", ['handle' => $mainHandle]);
                    continue;
                }

                $nota = extrairNotaEmpenho($driverUrl, $sid);
                $evtLinhas = extrairLinhasTabela($driverUrl, $sid, 'eventos');
                foreach ($evtLinhas as $evt) {
                    $eventos[] = ['nota' => $nota, 'dados' => $evt];
                }

                wdDelete($driverUrl, "/session/{$sid}/window");
                wdPost($driverUrl, "/session/{$sid}/window", ['handle' => $mainHandle]);
                usleep(300000);
            } catch (Throwable $e) {
                logPhase("    [detalhe] falha em $href: " . mb_substr($e->getMessage(), 0, 100));
                // Tenta voltar para a janela principal
                try {
                    $handles = wdGet($driverUrl, "/session/{$sid}/window/handles")['value'] ?? [];
                    if (count($handles) > 1) {
                        $last = end($handles);
                        wdPost($driverUrl, "/session/{$sid}/window", ['handle' => $last]);
                        wdDelete($driverUrl, "/session/{$sid}/window");
                    }
                    $handles = wdGet($driverUrl, "/session/{$sid}/window/handles")['value'] ?? [];
                    if (!empty($handles)) {
                        wdPost($driverUrl, "/session/{$sid}/window", ['handle' => $handles[0]]);
                    }
                } catch (Throwable $e2) {}
            }
        }

        // Persiste (transação)
        $pdo->beginTransaction();
        $sqlDelEmp->execute([$contrato]);
        $sqlDelEvt->execute([$contrato]);
        $nEmp = 0;
        foreach ($empenhos as $linha) {
            // linha = [unidade_gestora, numero_documento, natureza_despesa, vinc_pagamento,
            //          valor_empenho, valor_ajustes, valor_consumido, saldo]
            $valores = array_pad($linha, 8, null);
            $sqlInsEmp->execute([
                $jobId, $contrato,
                $valores[0] ?: null,
                $valores[1] ?: null,
                $valores[2] ?: null,
                $valores[3] ?: null,
                valorMonetario($valores[4]),
                valorMonetario($valores[5]),
                valorMonetario($valores[6]),
                valorMonetario($valores[7]),
            ]);
            $nEmp++;
        }
        $nEvt = 0;
        foreach ($eventos as $evt) {
            // evt['dados'] = [data, evento, valor_incluido, valor_gasto]
            $vals = array_pad($evt['dados'], 4, null);
            $sqlInsEvt->execute([
                $jobId, $contrato, $evt['nota'] ?: null,
                dataBR($vals[0]),
                $vals[1] ?: null,
                valorMonetario($vals[2]),
                valorMonetario($vals[3]),
            ]);
            $nEvt++;
        }
        $pdo->commit();
        $totalEmp += $nEmp;
        $totalEvt += $nEvt;
        logPhase("    -> $nEmp empenho(s), $nEvt evento(s) salvos");

    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        $ignorar = true;
        $motivo  = 'Erro: ' . mb_substr($e->getMessage(), 0, 200);
        saveScreenshot($driverUrl, $sid, $falhasDir . '/' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $contrato) . '.png');
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
    'processados'=>$total,'inseridos'=>$totalEmp,
    'contratos_afetados'=>$total - count($ignorados),
    'ignorados'=>json_encode($ignorados, JSON_UNESCAPED_UNICODE),
    'finalizado_em'=>date('Y-m-d H:i:s'),
]);
setStatus($statusFile, [
    'status'=>'concluido','etapa'=>'concluido','progresso'=>100,
    'total'=>$total,'processados'=>$total,
    'empenhos_inseridos'=>$totalEmp,'eventos_inseridos'=>$totalEvt,
    'contratos_ignorados'=>count($ignorados),'ignorados_detalhe'=>$ignorados,
    'iniciado_em'=>date('Y-m-d H:i:s',$inicio),'finalizado_em'=>date('Y-m-d H:i:s'),
    'tempo_segundos'=>time()-$inicio,
]);

$jobFinalizado = true;
logPhase("══════════════════════════════════════");
logPhase("[fim] $totalEmp empenhos, $totalEvt eventos, " . count($ignorados) . " ignorados, " . (time()-$inicio) . "s total");
exit(0);