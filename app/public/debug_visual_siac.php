<?php
// app/public/debug_visual_siac.php
//
// Fluxo completo. Select2 = abrir → digitar → clicar na opção → Enter (confirma).
//
// PHP 7.0: sem `: void` e sem `?Type` (nullable type hint).
// str_contains() → strpos() !== false.

require_once __DIR__ . '/config.php';
header('Content-Type: text/html; charset=utf-8');
set_time_limit(0);
while (ob_get_level()) @ob_end_flush();
ob_implicit_flush(true);

@mkdir(__DIR__ . '/debug_c', 0775, true);
@mkdir(__DIR__ . '/debug_c/pdfs', 0775, true);

$usuario  = trim((string)($_GET['u'] ?? ''));
$senha    = (string)($_GET['p'] ?? '');
$unidade  = trim((string)($_GET['unidade']  ?? 'S.R.E - AL - SUPERINTENDÊNCIA REGIONAL DO DNIT NO ESTADO DE ALAGOAS'));
$contrato = trim((string)($_GET['contrato'] ?? '00 00083/2017'));

echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Debug Completo</title>';
echo '<style>body{font-family:Consolas,monospace;background:#1e1e1e;color:#eee;padding:20px;line-height:1.6}';
echo 'pre{background:#252526;padding:12px;border-left:4px solid #4ec9b0;border-radius:4px;white-space:pre-wrap;margin:6px 0;max-height:400px;overflow:auto}';
echo '.err{border-color:#f48771;color:#f48771}.ok{border-color:#4ec9b0;color:#4ec9b0}.warn{border-color:#dcdcaa;color:#dcdcaa}';
echo '.step{color:#4ec9b0;font-weight:bold}h2{color:#4ec9b0}';
echo '.field{background:#2d3a1f;border-left:4px solid #6a9955;color:#b5cea8;font-size:.85rem;padding:8px;margin:4px 0;border-radius:4px}</style>';
echo '</head><body><h2>🎯 Debug — Fluxo completo (click+Enter no Select2)</h2>';

if (!$usuario || !$senha) {
    echo '<pre class="warn">?u=CPF&p=SENHA</pre></body></html>';
    exit;
}

echo "<pre class='field'>CPF: " . htmlspecialchars($usuario) . "</pre>";
echo "<pre class='field'>Unidade: " . htmlspecialchars($unidade) . "</pre>";
echo "<pre class='field'>Contrato: " . htmlspecialchars($contrato) . "</pre>";

$t0 = microtime(true);
// PHP 7.0: sem : void
function logMsg(string $msg, string $cls = '') {
    global $t0;
    $e = number_format(microtime(true) - $t0, 2);
    echo "<pre class='$cls'>[{$e}s] " . htmlspecialchars($msg) . "</pre>";
    @flush();
}

$edgeDriver = __DIR__ . '/msedgedriver.exe';
if (!is_file($edgeDriver)) { logMsg('msedgedriver.exe não encontrado', 'err'); exit; }

// ============================================================
// DRIVER
// ============================================================
$porta     = 4444 + random_int(0, 500);
$driverUrl = "http://127.0.0.1:{$porta}";
$driverLog = __DIR__ . '/debug_driver.log';
$pdfDir    = __DIR__ . '/debug_c/pdfs';

logMsg("→ Iniciando msedgedriver porta $porta");
$driverProc = proc_open('"' . $edgeDriver . '" --port=' . $porta,
    [0=>['pipe','r'], 1=>['file',$driverLog,'a'], 2=>['file',$driverLog,'a']], $driverPipes);

for ($i = 0; $i < 40; $i++) {
    usleep(500000);
    $ch = curl_init($driverUrl . '/status');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>2]);
    curl_exec($ch);
    if (curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200) { curl_close($ch); break; }
    curl_close($ch);
}
logMsg('✓ Driver pronto', 'ok');

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

// PHP 7.0: sem ?string no retorno
function findEl($b,$sid,$using,$value) {
    $r = wdPost($b, "/session/{$sid}/element", ['using'=>$using,'value'=>$value]);
    return $r['value'][ELEM_KEY] ?? null;
}
// PHP 7.0: sem : void
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
// PHP 7.0: sem ?string no retorno
function aguardarDownload(string $pasta, int $timeout = 30) {
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

/**
 * Envia tecla via WebDriver Actions (dispara eventos nativos que o Select2 escuta)
 * PHP 7.0: sem : void
 */
function pressKey(string $b, string $sid, string $key) {
    $map = [
        'Enter'  => "\u{E007}",
        'Tab'    => "\u{E004}",
        'Escape' => "\u{E00C}",
        'Space'  => "\u{E00D}",
    ];
    $code = $map[$key] ?? $key;
    wdPost($b, "/session/{$sid}/actions", ['actions'=>[[
        'type' => 'key', 'id' => 'kb', 'actions' => [
            ['type'=>'keyDown', 'value'=>$code],
            ['type'=>'keyUp',   'value'=>$code],
        ],
    ]]]);
    usleep(300000);
    wdDelete($b, "/session/{$sid}/actions");
}

/**
 * Foca no input de busca do Select2, digita o texto,
 * clica na opção e aperta Enter.
 *
 * Fluxo do SIAC: abrir → digitar → click na <li> (destaca) → Enter (confirma)
 */
function preencherSelect2(string $b, string $sid, string $selectId, string $texto, string $label): bool
{
    logMsg("══════════════════════════════════════");
    logMsg("[$label] Iniciando (selectId = $selectId)", 'step');
    logMsg("[$label] texto = " . mb_substr($texto, 0, 80));

    // ---------- 1) ABRIR ----------
    logMsg("[$label] 1/5 — Abrindo Select2 via jQuery...");
    $r = execJs($b, $sid, "
        var \$sel = jQuery('#' + arguments[0]);
        if (!\$sel.length) return JSON.stringify({ok:false, erro:'select nao encontrado'});
        \$sel.select2('open');
        return JSON.stringify({ok:true, aberto: \$sel.data('select2').isOpen()});
    ", [$selectId]);
    logMsg("[$label] " . ($r['value'] ?? '?'));

    // Aguarda search field aparecer
    $searchId = null;
    for ($i = 0; $i < 20; $i++) {
        usleep(300000);
        $searchId = findEl($b, $sid, 'css selector', '.select2-container--open .select2-search__field');
        if ($searchId) break;
    }
    if (!$searchId) {
        logMsg("[$label] ❌ search field não apareceu", 'err');
        saveScreenshot($b, $sid, __DIR__ . "/debug_c/{$selectId}_sem_search.png");
        return false;
    }
    logMsg("[$label] ✓ search field encontrado");

    // ---------- 2) DIGITAR ----------
    logMsg("[$label] 2/5 — Digitando no search field...");
    clickEl($b, $sid, $searchId);
    usleep(200000);

    // Limpa
    execJs($b, $sid, "
        var el = document.activeElement;
        if (el && el.classList.contains('select2-search__field')) {
            el.value = '';
            el.dispatchEvent(new Event('input', {bubbles:true}));
        }
    ");

    // Digita via WebDriver (keydown/keyup nativos)
    wdPost($b, "/session/{$sid}/element/{$searchId}/value",
        ['text' => $texto, 'value' => preg_split('//u', $texto, -1, PREG_SPLIT_NO_EMPTY)]);

    // ---------- 3) AGUARDAR OPÇÕES ----------
    logMsg("[$label] 3/5 — Aguardando opções no dropdown...");
    $opcoes = [];
    for ($i = 0; $i < 30; $i++) {
        usleep(400000);
        $r = execJs($b, $sid, "
            var lis = document.querySelectorAll('.select2-results__option--selectable, .select2-results__option');
            var arr = [];
            for (var i = 0; i < lis.length; i++) {
                var t = (lis[i].textContent || '').trim();
                if (t && t !== 'Carregando...' && t !== 'Loading...') {
                    if (!lis[i].classList.contains('select2-results__message')) {
                        arr.push(t);
                    }
                }
            }
            return JSON.stringify(arr);
        ");
        $opcoes = json_decode((string)($r['value'] ?? '[]'), true) ?: [];
        if (!empty($opcoes)) break;
    }
    logMsg("[$label] Opções: " . count($opcoes));
    foreach (array_slice($opcoes, 0, 5) as $o) logMsg("[$label]   • " . mb_substr($o, 0, 80));

    if (empty($opcoes)) {
        logMsg("[$label] ❌ Nenhuma opção", 'err');
        saveScreenshot($b, $sid, __DIR__ . "/debug_c/{$selectId}_sem_opcoes.png");
        return false;
    }

    // ---------- 4) CLICAR NA OPÇÃO ----------
    logMsg("[$label] 4/5 — Clicando na 1ª opção...");
    $r = execJs($b, $sid, "
        var li = document.querySelector('.select2-results__option--selectable, .select2-results__option:not(.select2-results__message)');
        if (!li) return JSON.stringify({ok:false});
        // Simula clique completo (mousedown + mouseup + click)
        li.dispatchEvent(new MouseEvent('mousedown', {bubbles:true, cancelable:true, view:window}));
        li.dispatchEvent(new MouseEvent('mouseup',   {bubbles:true, cancelable:true, view:window}));
        li.dispatchEvent(new MouseEvent('click',     {bubbles:true, cancelable:true, view:window}));
        return JSON.stringify({ok:true, texto: li.textContent.trim()});
    ");
    logMsg("[$label] " . ($r['value'] ?? '?'));

    usleep(300000);

    // ---------- 5) ENTER (confirma) ----------
    logMsg("[$label] 5/5 — Enter (confirmar)...");
    pressKey($b, $sid, 'Enter');
    sleep(1);

    // ---------- VERIFICAÇÃO ----------
    $rVerif = execJs($b, $sid, "
        var \$sel = jQuery('#' + arguments[0]);
        return JSON.stringify({
            valor: \$sel.val(),
            texto: \$sel.find('option:selected').text() || '',
            titulo: \$sel.data('select2') ? \$sel.data('select2').selection.\$selection.find('.select2-selection__rendered').attr('title') : ''
        });
    ", [$selectId]);
    $v = json_decode((string)($rVerif['value'] ?? '{}'), true) ?: [];
    logMsg("[$label] valor  = " . ($v['valor'] ?? 'NULL'));
    logMsg("[$label] texto  = " . mb_substr((string)($v['texto'] ?? ''), 0, 80));
    logMsg("[$label] titulo = " . mb_substr((string)($v['titulo'] ?? ''), 0, 80));

    // Fecha dropdown se ainda estiver aberto
    execJs($b, $sid, "var \$s = jQuery('#' + arguments[0]); if (\$s.data('select2') && \$s.data('select2').isOpen()) \$s.select2('close');", [$selectId]);
    sleep(1);

    if (!empty($v['valor'])) {
        logMsg("[$label] ✅ SELECIONADO COM SUCESSO", 'ok');
        return true;
    }

    logMsg("[$label] ❌ valor continua vazio", 'err');
    saveScreenshot($b, $sid, __DIR__ . "/debug_c/{$selectId}_falha.png");
    return false;
}

// ============================================================
// SESSÃO
// ============================================================
$caps = ['capabilities'=>['alwaysMatch'=>[
    'browserName' => 'MicrosoftEdge',
    'ms:edgeOptions' => [
        'args' => ['--disable-gpu','--no-sandbox','--disable-dev-shm-usage',
                   '--disable-blink-features=AutomationControlled'],
        'prefs' => [
            'download.default_directory'         => str_replace('/', '\\', $pdfDir),
            'download.prompt_for_download'       => false,
            'download.directory_upgrade'         => true,
            'plugins.always_open_pdf_externally' => true,
        ],
    ],
]]];
$sess = wdPost($driverUrl, '/session', $caps);
$sid  = $sess['value']['sessionId'] ?? null;
if (!$sid) { logMsg('❌ Falha sessão', 'err'); proc_terminate($driverProc); exit; }

wdPost($driverUrl, "/session/{$sid}/chromium/send_command", [
    'cmd' => 'Page.setDownloadBehavior',
    'params' => ['behavior'=>'allow', 'downloadPath'=>str_replace('/', '\\', $pdfDir)],
]);
wdPost($driverUrl, "/session/{$sid}/chromium/send_command", [
    'cmd' => 'Page.addScriptToEvaluateOnNewDocument',
    'params' => ['source' => "Object.defineProperty(navigator, 'webdriver', {get: () => undefined});"],
]);
logMsg('✓ Sessão criada');

// ============================================================
// FASE 1: LOGIN
// ============================================================
$urlBase = 'https://sisdnit.dnit.gov.br/sisdnit/jsp/';
logMsg('══════════════════════════════════════');
logMsg('FASE 1: LOGIN', 'step');

navTo($driverUrl, $sid, $urlBase);
sleep(2);

$ready = false;
for ($i = 0; $i < 40; $i++) {
    $r = execJs($driverUrl, $sid, "
        return JSON.stringify({
            ready: document.readyState === 'complete',
            form: !!document.forms[0],
            encriptar: typeof index !== 'undefined' && typeof index.encriptarEnviar === 'function'
        });
    ");
    $info = json_decode((string)($r['value'] ?? '{}'), true) ?: [];
    if (($info['ready'] ?? false) && ($info['form'] ?? false) && ($info['encriptar'] ?? false)) {
        $ready = true; break;
    }
    usleep(300000);
}
logMsg('  JS pronto: ' . ($ready ? 'SIM' : 'TIMEOUT'));
if (!$ready) { logMsg('❌ abort', 'err'); exit; }

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
sleep(2);
execJs($driverUrl, $sid, "index.encriptarEnviar(document.forms[0]); return 'ok';");

for ($i = 0; $i < 40; $i++) {
    usleep(500000);
    $path = parse_url(currentUrl($driverUrl, $sid), PHP_URL_PATH) ?? '';
    // PHP 7.0: strpos === false no lugar de str_contains
    if ($path !== '/sisdnit/jsp/' && $path !== '/sisdnit/jsp/index.jsp'
        && strpos($path, 'login.do') === false) {
        logMsg("  ✓ Login OK em " . ($i * 0.5) . "s → $path", 'ok');
        break;
    }
}
logMsg('  URL: ' . currentUrl($driverUrl, $sid));

// ============================================================
// FASE 2: PÁGINA DE RELATÓRIO
// ============================================================
$urlReset = 'https://sisdnit.dnit.gov.br/sisdnit/jsp/siac/relatorio_historico_medicoes.do?tipoAcao=reset';
logMsg('══════════════════════════════════════');
logMsg('FASE 2: Página de relatório', 'step');

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
        logMsg("  ✓ pronto em " . ($i * 0.5) . "s", 'ok');
        break;
    }
}
if (!$pageReady) {
    logMsg('❌ Página não carregou', 'err');
    sleep(60); wdDelete($driverUrl, "/session/{$sid}"); proc_terminate($driverProc); exit;
}

// ============================================================
// FASE 3: UNIDADE
// ============================================================
$okUnidade = preencherSelect2($driverUrl, $sid, 'idFiscalizacaoUnidadeResponsavel', $unidade, 'UNIDADE');
if (!$okUnidade) {
    logMsg('❌ Unidade falhou', 'err');
    sleep(60); wdDelete($driverUrl, "/session/{$sid}"); proc_terminate($driverProc); exit;
}

// ============================================================
// FASE 4: CONTRATO
// ============================================================
$okContrato = preencherSelect2($driverUrl, $sid, 'codContrato', $contrato, 'CONTRATO');
if (!$okContrato) {
    logMsg('❌ Contrato falhou', 'err');
    sleep(60); wdDelete($driverUrl, "/session/{$sid}"); proc_terminate($driverProc); exit;
}

// ============================================================
// FASE 5: GERAR
// ============================================================
logMsg('══════════════════════════════════════');
logMsg('FASE 5: Gerar relatório', 'step');

logMsg("  → Chamando relatoriosMedicao.emitirPDF('gerar')...");
$r = execJs($driverUrl, $sid, "
    if (typeof relatoriosMedicao === 'undefined' || typeof relatoriosMedicao.emitirPDF !== 'function') {
        return JSON.stringify({ok:false, erro:'funcao nao existe'});
    }
    try { relatoriosMedicao.emitirPDF('gerar'); return JSON.stringify({ok:true}); }
    catch(e) { return JSON.stringify({ok:false, erro: e.message || String(e)}); }
");
logMsg("  " . ($r['value'] ?? '?'));

logMsg('  → Aguardando PDF (até 40s)...');
$pdf = aguardarDownload($pdfDir, 40);

// ============================================================
// RESULTADO
// ============================================================
logMsg('══════════════════════════════════════');
logMsg('RESULTADO', 'step');
logMsg("  Login     : OK");
logMsg("  Relatório : OK");
logMsg("  Unidade   : " . ($okUnidade  ? 'OK' : 'FALHOU'));
logMsg("  Contrato  : " . ($okContrato ? 'OK' : 'FALHOU'));
if ($pdf) {
    logMsg("  PDF       : ✅ " . basename($pdf) . " (" . filesize($pdf) . " bytes)", 'ok');
    logMsg("  → $pdf", 'ok');
} else {
    logMsg("  PDF       : ❌ não baixou", 'err');
    saveScreenshot($driverUrl, $sid, __DIR__ . '/debug_c/sem_pdf.png');
}

logMsg('Navegador aberto 60s');
sleep(60);

wdDelete($driverUrl, "/session/{$sid}");
proc_terminate($driverProc);
echo '<pre class="ok">✓ Script encerrado</pre></body></html>';