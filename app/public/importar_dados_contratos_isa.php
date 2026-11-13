<?php
// app/public/importar_dados_contratos_isa.php
//
// Ações:
//   ?acao=ambiente_check        → diagnóstico
//   ?acao=setup_db              → cria tabelas necessárias (executar 1x)
//   ?acao=executar  (POST)      → cria job, insere em siac_import_jobs, dispara script
//   ?acao=status&job=X          → lê siac_import_jobs
//   ?acao=historico             → últimos 10 jobs (prefixo dados_)
//   ?acao=ultima_extracao       → último job (relógio da view)          ← NOVO
//   ?acao=relatorio&job=X       → resumo + itens por contrato           ← NOVO
//   ?acao=cancelar&job=X        → marca cancelado + mata processo        ← NOVO

require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();
session_write_close();
header('Content-Type: application/json; charset=utf-8');

const SCRIPT_DADOS = __DIR__ . '/script_dados_contratos_isa.php';
const T_JOBS       = 'siac_import_jobs';
const T_DADOS      = 'siac_contratos_dados';
const T_DS38       = 'supra_dataset_38_todos';
const JOBS_DIR     = __DIR__ . '/importar_dados_jobs';
const EDGEDRIVER   = __DIR__ . '/msedgedriver.exe';

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
        case 'setup_db':        setupDb($pdo);        break;
        case 'executar':        executar($pdo);       break;
        case 'status':          status($pdo);         break;
        case 'historico':       historico($pdo);      break;
        case 'ultima_extracao': ultimaExtracao($pdo); break;
        case 'relatorio':       relatorio($pdo);      break;
        case 'cancelar':        cancelar($pdo);       break;
        default:                falha(400, "Ação inválida: {$acao}. Esperado: ambiente_check, setup_db, executar, status, historico, ultima_extracao, relatorio, cancelar.");
    }
} catch (Throwable $e) {
    falha(500, $e->getMessage());
}

function falha(int $code, string $msg) {
    http_response_code($code);
    echo json_encode(['ok' => false, 'erro' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

// =============================================================
// SETUP DB
// =============================================================
function setupDb(PDO $pdo) {
    $criadas = [];
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `" . T_DADOS . "` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                importacao_id VARCHAR(100) NOT NULL,
                contrato VARCHAR(50) NOT NULL,
                estado VARCHAR(50) DEFAULT NULL,
                numero_contrato VARCHAR(50) DEFAULT NULL,
                empresa_executora VARCHAR(255) DEFAULT NULL,
                tipo_contrato VARCHAR(100) DEFAULT NULL,
                situacao VARCHAR(100) DEFAULT NULL,
                processo_base VARCHAR(100) DEFAULT NULL,
                empresa_supervisora VARCHAR(255) DEFAULT NULL,
                modalidade VARCHAR(100) DEFAULT NULL,
                tipo_licitacao VARCHAR(100) DEFAULT NULL,
                edital VARCHAR(100) DEFAULT NULL,
                tipo_intervencao VARCHAR(255) DEFAULT NULL,
                objeto TEXT,
                preco_inicial DECIMAL(18,2) DEFAULT NULL,
                total_aditivos DECIMAL(18,2) DEFAULT NULL,
                pi_vigente DECIMAL(18,2) DEFAULT NULL,
                total_reajustamento DECIMAL(18,2) DEFAULT NULL,
                total_pi_r DECIMAL(18,2) DEFAULT NULL,
                data_assinatura DATE DEFAULT NULL,
                inicio_vigencia DATE DEFAULT NULL,
                inicio_servicos DATE DEFAULT NULL,
                termino_servicos DATE DEFAULT NULL,
                termino_vigencia DATE DEFAULT NULL,
                data_base DATE DEFAULT NULL,
                criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
                KEY idx_contrato (contrato),
                KEY idx_importacao (importacao_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $criadas[] = T_DADOS;

        $col = $pdo->query("SHOW COLUMNS FROM `" . T_JOBS . "` LIKE 'tipo'")->fetch();
        if (!$col) {
            $pdo->exec("ALTER TABLE `" . T_JOBS . "` ADD COLUMN `tipo` VARCHAR(20) DEFAULT 'medicoes'");
        }

        echo json_encode(['ok' => true, 'tabelas' => $criadas, 'coluna_tipo' => true],
            JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        falha(500, 'setup_db: ' . $e->getMessage());
    }
}

// =============================================================
// AMBIENTE
// =============================================================
function ambienteCheck(PDO $pdo) {
    $tabelas = [];
    foreach ([T_DADOS, T_JOBS, T_DS38] as $t) {
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
        'ok'             => !in_array(false, $tabelas, true) && is_dir(JOBS_DIR) && is_file(EDGEDRIVER),
        'script_php'     => is_file(SCRIPT_DADOS),
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
function executar(PDO $pdo) {
    $usuario       = trim((string)($_POST['usuario'] ?? ''));
    $senha         = (string)($_POST['senha']   ?? '');
    $contratoUnico = trim((string)($_POST['contrato_unico'] ?? ''));

    if ($usuario === '' || $senha === '')  falha(422, 'Usuário e senha são obrigatórios');
    if (!is_file(SCRIPT_DADOS))            falha(500, 'script_dados_contratos.php não encontrado');
    if (!is_file(EDGEDRIVER))              falha(500, 'msedgedriver.exe não encontrado');

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
        falha(422, $contratoUnico !== ''
            ? "Contrato \"{$contratoUnico}\" não encontrado no Dataset 38."
            : 'Nenhum contrato no Dataset 38. Atualize-o primeiro.');
    }

    $jobId  = 'dados_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3));
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
                (job_id, tipo, status, etapa, progresso, total, processados,
                 inseridos, contratos_afetados, iniciado_em, atualizado_em, usuario)
            VALUES (?, 'dados', 'iniciando', 'spawn', 0, ?, 0, 0, 0, NOW(), NOW(), ?)
        ")->execute([$jobId, count($contratos), $usuario]);
    } catch (Throwable $e) {
        try {
            $pdo->prepare("
                INSERT INTO `" . T_JOBS . "`
                    (job_id, status, etapa, progresso, total, processados,
                     inseridos, contratos_afetados, iniciado_em, atualizado_em, usuario)
                VALUES (?, 'iniciando', 'spawn', 0, ?, 0, 0, 0, NOW(), NOW(), ?)
            ")->execute([$jobId, count($contratos), $usuario]);
        } catch (Throwable $e2) {
            falha(500, 'Falha ao registrar job: ' . $e2->getMessage());
        }
    }

    $phpBin = 'php';
    foreach (['C:\\xampp\\php\\php.exe', 'C:\\php\\php.exe'] as $c) {
        if (is_file($c)) { $phpBin = $c; break; }
    }

    $cmd = '"' . $phpBin . '" "' . SCRIPT_DADOS . '"'
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
        FILE_APPEND);

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

    $proc = @proc_open($cmd,
        [0 => ['pipe','r'], 1 => ['file',$logFile,'a'], 2 => ['file',$logFile,'a']],
        $pipes, $jobDir, $env, ['bypass_shell' => true]);

    if (!is_resource($proc)) {
        $erroMsg = 'proc_open falhou. Verifique ' . $logFile;
        try { $pdo->prepare("UPDATE `" . T_JOBS . "` SET status='erro', erro=?, atualizado_em=NOW(), finalizado_em=NOW() WHERE job_id=?")
                   ->execute([$erroMsg, $jobId]); } catch (Throwable $e) {}
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
        'log'   => 'app/public/importar_dados_jobs/' . $jobId . '/log.txt',
    ]);
}

// =============================================================
// STATUS
// =============================================================
function status(PDO $pdo) {
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
             . 'Verifique app/public/importar_dados_jobs/' . $job . '/log.txt';
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
        'ok'                  => $j['status'] !== 'erro',
        'parado_segundos'     => $parado,
    ], JSON_UNESCAPED_UNICODE);
}

// =============================================================
// HISTÓRICO
// =============================================================
function historico(PDO $pdo) {
    try {
        $itens = $pdo->query("
            SELECT job_id, status, etapa, login_ok, total, processados, inseridos,
                   contratos_afetados, usuario, iniciado_em, finalizado_em, erro
              FROM `" . T_JOBS . "`
             WHERE job_id LIKE 'dados\\_%'
             ORDER BY iniciado_em DESC LIMIT 10
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { $itens = []; }
    echo json_encode(['itens' => $itens], JSON_UNESCAPED_UNICODE);
}

// =============================================================
// ÚLTIMA EXTRAÇÃO  (relógio da view)
// =============================================================
function ultimaExtracao(PDO $pdo) {
    try {
        $st = $pdo->query("
            SELECT job_id, status, iniciado_em, finalizado_em, usuario, total, inseridos
              FROM `" . T_JOBS . "`
             WHERE job_id LIKE 'dados\\_%'
             ORDER BY iniciado_em DESC LIMIT 1
        ");
        $j = $st->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $j = null;
    }

    if (!$j) {
        echo json_encode(['job' => null], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['job' => [
        'job_id'        => $j['job_id'],
        'status'        => $j['status'],
        'iniciado_em'   => $j['iniciado_em'],
        'finalizado_em' => $j['finalizado_em'],
        'usuario'       => $j['usuario'],
        'total'         => (int)$j['total'],
        'inseridos'     => (int)$j['inseridos'],
    ]], JSON_UNESCAPED_UNICODE);
    exit;
}

// =============================================================
// RELATÓRIO DO JOB
//
// Reconstrói os itens a partir de:
//   • siac_contratos_dados  (WHERE importacao_id = job) → OK
//   • siac_import_jobs.ignorados (JSON)                → IGNORADO
//   • siac_import_jobs.erro      (string)              → ERRO agregado
// =============================================================
function relatorio(PDO $pdo) {
    $jobId = preg_replace('/[^A-Za-z0-9_\-]/', '', $_GET['job'] ?? '');
    if ($jobId === '') falha(422, 'job obrigatório');

    try {
        $st = $pdo->prepare("SELECT * FROM `" . T_JOBS . "` WHERE job_id = ?");
        $st->execute([$jobId]);
        $j = $st->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        falha(500, 'Erro ao ler job: ' . $e->getMessage());
    }
    if (!$j) falha(404, 'job não encontrado');

    $itens = [];

    // --- Itens OK (a partir da tabela de dados) ---
    try {
        $st2 = $pdo->prepare("
            SELECT contrato, estado, empresa_executora, situacao, numero_contrato
              FROM `" . T_DADOS . "`
             WHERE importacao_id = ?
             ORDER BY contrato
        ");
        $st2->execute([$jobId]);
        foreach ($st2->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $motivo = $r['empresa_executora']
                ? 'Empresa: ' . $r['empresa_executora']
                : ($r['situacao'] ?: '');
            $itens[] = [
                'contrato'    => (string)$r['contrato'],
                'estado'      => (string)($r['estado'] ?? ''),
                'status'      => 'OK',
                'medicoes'    => 1,
                'duracao_seg' => 0,
                'motivo'      => $motivo,
            ];
        }
    } catch (Throwable $e) {
        // Se a tabela não existir ainda, segue sem os OK
    }

    // --- Itens IGNORADOS (JSON da coluna ignorados) ---
    $ign = json_decode((string)($j['ignorados'] ?? ''), true);
    if (is_array($ign)) {
        foreach ($ign as $i) {
            if (is_array($i)) {
                $itens[] = [
                    'contrato'    => (string)($i['contrato'] ?? $i['Contrato'] ?? $i['instrumento'] ?? '?'),
                    'estado'      => (string)($i['estado']   ?? $i['Estado']   ?? $i['unidade_manual'] ?? ''),
                    'status'      => 'IGNORADO',
                    'medicoes'    => 0,
                    'duracao_seg' => 0,
                    'motivo'      => (string)($i['motivo'] ?? $i['razao'] ?? $i['msg'] ?? $i['mensagem'] ?? ''),
                ];
            } else {
                $itens[] = [
                    'contrato'    => (string)$i,
                    'estado'      => '',
                    'status'      => 'IGNORADO',
                    'medicoes'    => 0,
                    'duracao_seg' => 0,
                    'motivo'      => '',
                ];
            }
        }
    }

    // --- Erro geral do job (se houver) ---
    $erroGeral = trim((string)($j['erro'] ?? ''));
    if ($erroGeral !== '') {
        $itens[] = [
            'contrato'    => '—',
            'estado'      => '',
            'status'      => 'ERRO',
            'medicoes'    => 0,
            'duracao_seg' => 0,
            'motivo'      => $erroGeral,
        ];
    }

    // --- Resumo ---
    $ok = 0; $ignCount = 0; $err = 0;
    foreach ($itens as $it) {
        if ($it['status'] === 'OK')             $ok++;
        elseif ($it['status'] === 'IGNORADO')   $ignCount++;
        else                                     $err++;
    }

    echo json_encode([
        'ok'            => true,
        'job'           => $jobId,
        'status'        => $j['status'],
        'iniciado_em'   => $j['iniciado_em']   ?? null,
        'finalizado_em' => $j['finalizado_em'] ?? null,
        'total'         => (int)$j['total'],
        'processados'   => (int)$j['processados'],
        'resumo'        => [
            'ok'       => $ok,
            'ignorado' => $ignCount,
            'erro'     => $err,
            'medicoes' => (int)$j['inseridos'],
        ],
        'itens'         => $itens,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// =============================================================
// CANCELAR
// =============================================================
function cancelar(PDO $pdo) {
    $jobId = preg_replace('/[^A-Za-z0-9_\-]/', '', $_GET['job'] ?? '');
    if ($jobId === '') falha(422, 'job obrigatório');

    try {
        $pdo->prepare("UPDATE `" . T_JOBS . "` SET status='cancelado', finalizado_em=NOW(), atualizado_em=NOW()
                        WHERE job_id=? AND status IN ('iniciando','rodando')")
            ->execute([$jobId]);
    } catch (Throwable $e) {
        falha(500, 'Erro ao cancelar: ' . $e->getMessage());
    }

    $meta = @json_decode(@file_get_contents(JOBS_DIR . '/' . $jobId . '/meta.json'), true);
    $pid  = (int)($meta['pid'] ?? 0);
    if ($pid > 0) {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            @exec('taskkill /PID ' . $pid . ' /F /T 2>NUL');
        } else {
            @exec('kill -9 ' . $pid . ' 2>/dev/null');
        }
    }

    echo json_encode([
        'ok'       => true,
        'mensagem' => 'Cancelamento solicitado. Processos em background foram encerrados.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}