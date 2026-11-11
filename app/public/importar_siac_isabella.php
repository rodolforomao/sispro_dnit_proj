<?php
// app/public/importar_siac_isabella.php
//
// Ações:
//   ?acao=ambiente_check     → diagnóstico
//   ?acao=executar   (POST)  → cria job, insere em siac_import_jobs, dispara script
//   ?acao=status&job=X       → lê siac_import_jobs
//   ?acao=historico          → últimos 10
//   ?acao=cancelar&job=X     → mata o processo (taskkill /F /T)
//   ?acao=relatorio&job=X    → lê relatorio.json (por contrato)
//   ?acao=ultima_extracao    → última job completo (total > 1)

require_once __DIR__ . '/config.php';   // fornece $pdo

if (session_status() === PHP_SESSION_NONE) session_start();
session_write_close();
header('Content-Type: application/json; charset=utf-8');

const SCRIPT_SIAC = __DIR__ . '/script_siac_isabella.php';
const T_JOBS      = 'siac_import_jobs';
const T_MED       = 'siac_medicoes';
const T_DS38      = 'supra_dataset_38_todos';
const JOBS_DIR    = __DIR__ . '/importar_siac_jobs';
const EDGEDRIVER  = __DIR__ . '/msedgedriver.exe';

if (!is_dir(JOBS_DIR)) {
    if (!@mkdir(JOBS_DIR, 0775, true) && !is_dir(JOBS_DIR)) {
        http_response_code(500);
        echo json_encode(['erro' => 'Não foi possível criar ' . JOBS_DIR]);
        exit;
    }
}

$acao = $_REQUEST['acao'] ?? '';
try {
    switch ($acao) {
        case 'ambiente_check':  ambienteCheck($pdo);  break;
        case 'executar':        executar($pdo);       break;
        case 'status':          status($pdo);         break;
        case 'historico':       historico($pdo);      break;
        case 'cancelar':        cancelar($pdo);       break;
        case 'relatorio':       relatorio($pdo);      break;
        case 'ultima_extracao': ultimaExtracao($pdo); break;
        default:                falha(400, 'Ação inválida');
    }
} catch (Throwable $e) {
    falha(500, $e->getMessage());
}

function falha(int $code, string $msg): void
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'erro' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

// =============================================================
// AMBIENTE
// =============================================================
function ambienteCheck(PDO $pdo): void
{
    $autoload  = __DIR__ . '/../vendor/autoload.php';
    $temParser = false;
    if (is_file($autoload)) {
        require_once $autoload;
        $temParser = class_exists('Smalot\\PdfParser\\Parser');
    }

    $tabelas = [];
    foreach ([T_MED, T_JOBS, T_DS38] as $t) {
        try { $tabelas[$t] = (bool)$pdo->query('SHOW TABLES LIKE ' . $pdo->quote($t))->fetchColumn(); }
        catch (Throwable $e) { $tabelas[$t] = false; }
    }

    $totalContratos = 0;
    if ($tabelas[T_DS38]) {
        try {
            $totalContratos = (int)$pdo->query("
                SELECT COUNT(DISTINCT instrumento) FROM `" . T_DS38 . "`
                 WHERE instrumento    IS NOT NULL AND instrumento    <> ''
                   AND unidade_manual IS NOT NULL AND unidade_manual <> ''
            ")->fetchColumn();
        } catch (Throwable $e) {}
    }

    echo json_encode([
        'ok'             => $temParser && !in_array(false, $tabelas, true) && is_dir(JOBS_DIR) && is_file(EDGEDRIVER),
        'autoload'       => is_file($autoload),
        'pdf_parser'     => $temParser,
        'script_php'     => is_file(SCRIPT_SIAC),
        'edgedriver'     => is_file(EDGEDRIVER) ? EDGEDRIVER : 'AUSENTE — coloque msedgedriver.exe em app/public/',
        'pasta_jobs'     => is_dir(JOBS_DIR),
        'tabelas'        => $tabelas,
        'contratos_ds38' => $totalContratos,
        'os'             => PHP_OS,
    ], JSON_UNESCAPED_UNICODE);
}

// =============================================================
// EXECUTAR
// =============================================================
function executar(PDO $pdo): void
{
    $usuario       = trim((string)($_POST['usuario'] ?? ''));
    $senha         = (string)($_POST['senha']   ?? '');
    $contratoUnico = trim((string)($_POST['contrato_unico'] ?? ''));

    if ($usuario === '' || $senha === '') {
        falha(422, 'Usuário e senha são obrigatórios');
    }
    if (!is_file(SCRIPT_SIAC)) {
        falha(500, 'script_siac_isabella.php não encontrado em ' . SCRIPT_SIAC);
    }
    if (!is_file(__DIR__ . '/../vendor/autoload.php')) {
        falha(500, 'vendor/autoload.php ausente. Instale smalot/pdfparser.');
    }
    if (!is_file(EDGEDRIVER)) {
        falha(500, 'msedgedriver.exe não encontrado em ' . EDGEDRIVER);
    }
    if (!is_dir(JOBS_DIR)) {
        falha(500, 'Pasta ' . JOBS_DIR . ' não existe e não pôde ser criada.');
    }

    try {
        $sql = "
            SELECT instrumento AS Contrato, unidade_manual AS Estado
              FROM `" . T_DS38 . "`
             WHERE instrumento    IS NOT NULL AND instrumento    <> ''
               AND unidade_manual IS NOT NULL AND unidade_manual <> ''
        ";
        $params = [];
        if ($contratoUnico !== '') {
            $sql .= " AND instrumento = :contrato ";
            $params[':contrato'] = $contratoUnico;
        }
        $sql .= " GROUP BY instrumento, unidade_manual ORDER BY instrumento";

        $st = $pdo->prepare($sql);
        $st->execute($params);
        $contratos = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        falha(422, 'Falha ao ler Dataset 38: ' . $e->getMessage());
    }
    if (!$contratos) {
        if ($contratoUnico !== '') {
            falha(422, "Contrato \"{$contratoUnico}\" não encontrado no Dataset 38.");
        }
        falha(422, 'Nenhum contrato no Dataset 38. Atualize-o primeiro.');
    }

    $jobId  = 'siac_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3));
    $jobDir = JOBS_DIR . '/' . $jobId;

    if (!@mkdir($jobDir, 0775, true) || !is_dir($jobDir)) {
        falha(500, 'Não foi possível criar ' . $jobDir);
    }
    @mkdir($jobDir . '/saida', 0775, true);

    $contratosJson = $jobDir . '/contratos.json';
    file_put_contents($contratosJson, json_encode($contratos, JSON_UNESCAPED_UNICODE));

    try {
        $pdo->prepare("
            INSERT INTO `" . T_JOBS . "`
                (job_id, status, etapa, progresso, total, processados,
                 inseridos, contratos_afetados, iniciado_em, atualizado_em, usuario)
            VALUES (?, 'iniciando', 'spawn', 0, ?, 0, 0, 0, NOW(), NOW(), ?)
        ")->execute([$jobId, count($contratos), $usuario]);
    } catch (Throwable $e) {
        falha(500, 'Falha ao registrar job: ' . $e->getMessage());
    }

    $phpBin = 'php';
    foreach (['C:\\xampp\\php\\php.exe', 'C:\\php\\php.exe'] as $c) {
        if (is_file($c)) { $phpBin = $c; break; }
    }

    $cmd = '"' . $phpBin . '" "' . SCRIPT_SIAC . '"'
         . ' --contratos="'  . $contratosJson . '"'
         . ' --output-dir="' . $jobDir . '/saida"'
         . ' --job="'        . $jobId . '"'
         . ' --config="'     . __DIR__ . '/config.php"';

    $logFile = $jobDir . '/log.txt';

    @file_put_contents($logFile,
        "[" . date('Y-m-d H:i:s') . "] INICIANDO JOB $jobId\n" .
        "[modo] " . ($contratoUnico !== '' ? "TESTE (1 contrato: $contratoUnico)" : "COMPLETO (" . count($contratos) . " contratos)") . "\n" .
        "[cmd] $cmd\n" .
        "[" . date('Y-m-d H:i:s') . "] ----------------------------------------\n",
        FILE_APPEND
    );

    $env = [
        'SIAC_USER'    => $usuario,
        'SIAC_PASS'    => $senha,
        'EDGEDRIVER'   => EDGEDRIVER,
        'SYSTEMROOT'   => getenv('SYSTEMROOT')   ?: 'C:\\Windows',
        'WINDIR'       => getenv('WINDIR')       ?: 'C:\\Windows',
        'PATH'         => getenv('PATH')         ?: '',
        'TEMP'         => getenv('TEMP')         ?: sys_get_temp_dir(),
        'TMP'          => getenv('TMP')          ?: sys_get_temp_dir(),
        'USERPROFILE'  => getenv('USERPROFILE')  ?: '',
        'PROGRAMFILES' => getenv('PROGRAMFILES') ?: 'C:\\Program Files',
    ];

    $proc = @proc_open(
        $cmd,
        [0 => ['pipe','r'], 1 => ['file',$logFile,'a'], 2 => ['file',$logFile,'a']],
        $pipes,
        $jobDir,
        $env,
        ['bypass_shell' => true]
    );

    if (!is_resource($proc)) {
        $erroMsg = 'proc_open falhou. Verifique ' . $logFile;
        try {
            $pdo->prepare("UPDATE `" . T_JOBS . "` SET status='erro', erro=?, atualizado_em=NOW(), finalizado_em=NOW() WHERE job_id=?")
                ->execute([$erroMsg, $jobId]);
        } catch (Throwable $e) {}
        falha(500, $erroMsg);
    }
    fclose($pipes[0]);
    $pid = proc_get_status($proc)['pid'] ?? null;

    @file_put_contents($jobDir . '/meta.json', json_encode([
        'job_id' => $jobId, 'pid' => $pid, 'php' => $phpBin,
        'cmd'    => $cmd, 'usuario' => $usuario,
        'contrato_unico' => $contratoUnico ?: null,
        'iniciado_em' => date('Y-m-d H:i:s'),
        'total_contratos' => count($contratos),
    ], JSON_UNESCAPED_UNICODE));

    echo json_encode([
        'ok'    => true,
        'job'   => $jobId,
        'pid'   => $pid,
        'total' => count($contratos),
        'modo'  => $contratoUnico !== '' ? 'teste' : 'completo',
        'log'   => 'app/public/importar_siac_jobs/' . $jobId . '/log.txt',
    ]);
}

// =============================================================
// STATUS
// =============================================================
function status(PDO $pdo): void
{
    $job = preg_replace('/[^A-Za-z0-9_\-]/', '', $_GET['job'] ?? '');
    if ($job === '') falha(422, 'job obrigatório');

    $st = $pdo->prepare("
        SELECT *, TIMESTAMPDIFF(SECOND, atualizado_em, NOW()) AS parado_seg
          FROM `" . T_JOBS . "` WHERE job_id = ?
    ");
    $st->execute([$job]);
    $j = $st->fetch(PDO::FETCH_ASSOC);
    if (!$j) falha(404, 'job não encontrado');

    $parado = (int)$j['parado_seg'];
    $msg = null;
    if ($j['status'] === 'iniciando' && $parado > 180) {
        $msg = 'O scraper travou em "iniciando" há ' . round($parado/60) . ' min. '
             . 'Verifique app/public/importar_siac_jobs/' . $job . '/log.txt';
    } elseif ($j['status'] === 'rodando' && $parado > 1800) {
        $msg = 'O scraper parou de atualizar há ' . round($parado/60) . ' min.';
    }
    if ($msg) {
        $pdo->prepare("UPDATE `" . T_JOBS . "` SET status='erro', erro=?, atualizado_em=NOW(), finalizado_em=NOW() WHERE job_id=?")
            ->execute([$msg, $job]);
        $j['status'] = 'erro';
        $j['erro']   = $msg;
    }

    $ign = json_decode((string)($j['ignorados'] ?? ''), true);
    $ign = is_array($ign) ? $ign : [];

    // Relatório detalhado disponível?
    $relFile = JOBS_DIR . '/' . $job . '/saida/relatorio.json';
    $relOk   = is_file($relFile);

    echo json_encode([
        'status'              => $j['status'],
        'etapa'               => $j['etapa'],
        'login_ok'            => $j['login_ok'] === null ? null : (int)$j['login_ok'],
        'progresso'           => (float)$j['progresso'],
        'total'               => (int)$j['total'],
        'processados'         => (int)$j['processados'],
        'contrato_atual'      => $j['contrato_atual'],
        'inseridos'           => (int)$j['inseridos'],
        'contratos_afetados'  => (int)$j['contratos_afetados'],
        'contratos_ignorados' => count($ign),
        'ignorados_detalhe'   => $ign,
        'erro'                => $j['erro'],
        'ok'                  => !in_array($j['status'], ['erro'], true),
        'parado_segundos'     => $parado,
        'relatorio_disponivel'=> $relOk,
    ], JSON_UNESCAPED_UNICODE);
}

// =============================================================
// HISTÓRICO
// =============================================================
function historico(PDO $pdo): void
{
    try {
        $itens = $pdo->query("
            SELECT job_id, status, etapa, login_ok, total, processados, inseridos,
                   contratos_afetados, usuario, iniciado_em, finalizado_em, erro
              FROM `" . T_JOBS . "`
             ORDER BY iniciado_em DESC LIMIT 10
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { $itens = []; }
    echo json_encode(['itens' => $itens], JSON_UNESCAPED_UNICODE);
}

// =============================================================
// CANCELAR (mata o processo via taskkill /F /T /PID)
// =============================================================
function cancelar(PDO $pdo): void
{
    $job = preg_replace('/[^A-Za-z0-9_\-]/', '', $_REQUEST['job'] ?? '');
    if ($job === '') falha(422, 'job obrigatório');

    $st = $pdo->prepare("SELECT status FROM `" . T_JOBS . "` WHERE job_id = ?");
    $st->execute([$job]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) falha(404, 'job não encontrado');

    if (!in_array($row['status'], ['iniciando', 'rodando'], true)) {
        echo json_encode([
            'ok'       => true,
            'job'      => $job,
            'mensagem' => 'Job já está finalizado (' . $row['status'] . ').',
            'status'   => $row['status'],
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    // PID fica salvo em meta.json pelo executar()
    $jobDir   = JOBS_DIR . '/' . $job;
    $metaFile = $jobDir . '/meta.json';
    $pid = null;
    if (is_file($metaFile)) {
        $meta = json_decode((string)file_get_contents($metaFile), true);
        $pid  = isset($meta['pid']) ? (int)$meta['pid'] : null;
    }

    if (!$pid) {
        falha(500, 'PID do processo não encontrado em ' . $metaFile . '. Use o Gerenciador de Tarefas.');
    }

    // Mata a árvore inteira (worker + msedgedriver + msedge)
    $matou = false;
    $exit  = -1;
    $out   = [];
    if (PHP_OS_FAMILY === 'Windows') {
        @exec('taskkill /F /T /PID ' . $pid . ' 2>&1', $out, $exit);
        // Código 0 = sucesso; 128 = "não encontrado" (já morreu)
        $matou = in_array($exit, [0, 128], true);
    } else {
        @exec('kill -9 ' . $pid . ' 2>&1', $out, $exit);
        $matou = ($exit === 0);
    }

    if (!$matou) {
        echo json_encode([
            'ok'    => false,
            'erro'  => 'Não foi possível matar o processo (PID ' . $pid . ', exit ' . $exit . '). '
                     . 'Use o Gerenciador de Tarefas.',
            'saida' => $out,
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    $msg = 'Cancelado pelo usuário em ' . date('d/m/Y H:i:s');
    $pdo->prepare("
        UPDATE `" . T_JOBS . "`
           SET status='cancelado', etapa='cancelado', erro=?,
               atualizado_em=NOW(), finalizado_em=NOW()
         WHERE job_id=?
    ")->execute([$msg, $job]);

    @file_put_contents($jobDir . '/cancelado.flag', date('Y-m-d H:i:s'));

    echo json_encode([
        'ok'       => true,
        'job'      => $job,
        'pid'      => $pid,
        'mensagem' => 'Cancelamento solicitado. O processo foi interrompido.',
        'status'   => 'cancelado',
    ], JSON_UNESCAPED_UNICODE);
}

// =============================================================
// RELATÓRIO POR CONTRATO (lê saida/relatorio.json)
// =============================================================
function relatorio(PDO $pdo): void
{
    $job = preg_replace('/[^A-Za-z0-9_\-]/', '', $_GET['job'] ?? '');
    if ($job === '') falha(422, 'job obrigatório');

    $st = $pdo->prepare("
        SELECT status, total, processados, inseridos, iniciado_em, finalizado_em
          FROM `" . T_JOBS . "` WHERE job_id = ?
    ");
    $st->execute([$job]);
    $j = $st->fetch(PDO::FETCH_ASSOC);
    if (!$j) falha(404, 'job não encontrado');

    $relFile = JOBS_DIR . '/' . $job . '/saida/relatorio.json';
    $itens = [];
    if (is_file($relFile)) {
        $itens = json_decode((string)file_get_contents($relFile), true) ?: [];
    }

    $ok = 0; $ignorado = 0; $erro = 0; $totalMed = 0;
    foreach ($itens as $r) {
        $status = $r['status'] ?? 'IGNORADO';
        if ($status === 'OK') { $ok++; $totalMed += (int)($r['medicoes'] ?? 0); }
        elseif ($status === 'ERRO') $erro++;
        else $ignorado++;
    }

    echo json_encode([
        'ok'           => true,
        'job'          => $job,
        'status'       => $j['status'],
        'iniciado_em'  => $j['iniciado_em'],
        'finalizado_em'=> $j['finalizado_em'],
        'total'        => (int)$j['total'],
        'processados'  => (int)$j['processados'],
        'resumo' => [
            'ok'       => $ok,
            'ignorado' => $ignorado,
            'erro'     => $erro,
            'medicoes' => $totalMed,
        ],
        'itens' => $itens,
    ], JSON_UNESCAPED_UNICODE);
}

// =============================================================
// ÚLTIMA EXTRAÇÃO COMPLETA (total > 1 = não é teste)
// =============================================================
function ultimaExtracao(PDO $pdo): void
{
    try {
        $st = $pdo->query("
            SELECT job_id, status, etapa, total, processados, inseridos,
                   contratos_afetados, iniciado_em, finalizado_em, usuario, erro
              FROM `" . T_JOBS . "`
             WHERE total > 1
             ORDER BY iniciado_em DESC
             LIMIT 1
        ");
        $j = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        echo json_encode(['ok' => true, 'job' => $j], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'erro' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}