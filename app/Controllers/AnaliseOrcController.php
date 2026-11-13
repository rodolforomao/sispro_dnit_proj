<?php
// app/Controllers/AnaliseOrcController.php

require_once APP_PATH . '/Models/AnaliseOrcModel.php';

class AnaliseOrcController
{
    private  $model;

    public function __construct(PDO $pdo)
    {
        $this->model = new AnaliseOrcModel($pdo);
    }

    // =============================================================
    // AUTENTICAÇÃO
    // =============================================================
    private function requireLoginAjax()
    {
        if (session_status() === PHP_SESSION_NONE) @session_start();
        $logado = !empty($_SESSION['usuario_id']) || !empty($_SESSION['usuario_nivel']);
        if (!$logado || !isset($_SESSION['setor_slug'])) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'erro'  => 'Sessão expirada ou não autenticada. Faça login novamente.',
                'login' => false,
                'redir' => 'login',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    private function requireLoginPage()
    {
        if (session_status() === PHP_SESSION_NONE) @session_start();
        if (empty($_SESSION['usuario_id']) && empty($_SESSION['usuario_nivel'])) {
            header('Location: login'); exit;
        }
        if (!isset($_SESSION['setor_slug'])) {
            header('Location: selecionar_setor'); exit;
        }
    }

    private function usuarioPodeScraper(): bool
    {
        if (session_status() === PHP_SESSION_NONE) @session_start();
        if (!empty($_SESSION['is_desenvolvedor']) || !empty($_SESSION['is_admin'])) return true;
        $nivel = strtolower(trim((string)($_SESSION['usuario_nivel'] ?? '')));
        $niveis = [
            'admin', 'administrador', 'administrator',
            'desenvolvedor', 'developer', 'dev',
            'super_admin', 'superadmin', 'super-administrador',
            'root',
        ];
        return in_array($nivel, $niveis, true);
    }

    // =============================================================
    // VIEW
    // =============================================================
    public function index()
    {
        $this->requireLoginPage();
        $exercicio = (int)($_GET['exercicio'] ?? $this->model->exercicioDisponivel() ?? date('Y'));
        $acoes = $this->model->acoes($exercicio);
        $podeScraper = $this->usuarioPodeScraper();
        require APP_PATH . '/Views/analise_orc.php';
    }

    public function ranking()
    {
        $this->requireLoginPage();
        $exercicio = (int)($_GET['exercicio'] ?? $this->model->exercicioDisponivel() ?? date('Y'));
        $acoes = $this->model->acoes($exercicio);
        require APP_PATH . '/Views/analise_orc_ranking.php';
    }

    // =============================================================
    // API — LISTAGENS
    // =============================================================
    public function acoes()
    {
        $this->requireLoginAjax();
        $exercicio = (int)($_GET['exercicio'] ?? $this->model->exercicioDisponivel() ?? date('Y'));
        $this->json($this->model->acoes($exercicio));
    }

    public function tipos()
    {
        $this->requireLoginAjax();
        $acaoId = (string)($_GET['acao_id'] ?? '');
        $this->json($this->model->tiposPorAcao($acaoId));
    }

    public function contratos()
    {
        $this->requireLoginAjax();
        $acaoId    = (string)($_GET['acao_id'] ?? '');
        $tipo      = $_GET['tipo']  ?? '';
        $super     = $_GET['super'] ?? null;
        $exercicio = (int)($_GET['exercicio'] ?? $this->model->exercicioDisponivel() ?? date('Y'));

        $lista = $this->model->contratosDaAcao($acaoId, $tipo, $super ?: null);

        $out = [];
        foreach ($lista as $c) {
            $ind = $this->calcularIndicadores($c, $exercicio);
            if ($ind['alerta_planejamento']) {
                $this->model->registrarNotificacao($ind['numero'], 'PLANEJAMENTO_INCOMPLETO', date('Y-m-01'));
            }
            $out[] = $ind;
        }
        $this->json($out);
    }

    public function detalhe()
    {
        $this->requireLoginAjax();
        $numero    = (string)($_GET['numero'] ?? '');
        $exercicio = (int)($_GET['exercicio'] ?? $this->model->exercicioDisponivel() ?? date('Y'));
        $c = $this->model->contratoPorNumero($numero);
        if (!$c) { $this->json(['erro' => 'Contrato não encontrado'], 404); return; }
        $this->json($this->calcularIndicadores($c, $exercicio));
    }

    public function medicoes()
    {
        $this->requireLoginAjax();
        $numero = (string)($_GET['numero'] ?? '');
        $limite = (int)($_GET['limite'] ?? 12);
        if (!$numero) { $this->json(['erro' => 'numero obrigatório'], 422); return; }
        $c = $this->model->contratoPorNumero($numero);
        if (!$c) { $this->json(['erro' => 'Contrato não encontrado'], 404); return; }
        $this->json($this->medicoesComEstatistica($numero, $limite));
    }

    public function rankingData()
    {
        $this->requireLoginAjax();
        $exercicio = (int)($_GET['exercicio'] ?? $this->model->exercicioDisponivel() ?? date('Y'));
        try {
            $acoes = $this->model->acoes($exercicio);
            $todos = []; $vistos = [];
            foreach ($acoes as $a) {
                $lista = $this->model->contratosDaAcao((string)$a['id'], null, null);
                foreach ($lista as $c) {
                    $num = $c['numero'] ?? null;
                    if (!$num || isset($vistos[$num])) continue;
                    $vistos[$num] = true;
                    $ind = $this->calcularIndicadores($c, $exercicio);
                    $ind['acao_id']        = (string)$a['id'];
                    $ind['acao_codigo']    = (string)$a['codigo'];
                    $ind['acao_descricao'] = (string)$a['descricao'];
                    $todos[] = $ind;
                }
            }
            $this->json([
                'exercicio' => $exercicio,
                'acoes'     => $acoes,
                'contratos' => $todos,
            ]);
        } catch (Throwable $e) {
            $this->json(['erro' => $e->getMessage()], 500);
        }
    }

    // =============================================================
    // DATASET 38 e SIAC
    // =============================================================
    public function contratosDisponiveis()
    {
        $this->requireLoginAjax();
        try {
            $st = $this->model->pdo()->query("
                SELECT COUNT(DISTINCT instrumento)
                  FROM `supra_dataset_38_todos`
                 WHERE instrumento    IS NOT NULL AND instrumento    <> ''
                   AND unidade_manual IS NOT NULL AND unidade_manual <> ''
            ");
            $total = (int)$st->fetchColumn();
        } catch (Throwable $e) { $total = 0; }
        $this->json(['total' => $total]);
    }

    public function atualizarDataset38()
    {
        $this->requireLoginAjax();
        set_time_limit(0);
        @ini_set('max_execution_time', '0');

        $appPath = defined('APP_PATH') ? APP_PATH : dirname(__DIR__);
        $candidatos = [
            $appPath . '/public/importar_supra_dataset_38_todos.php',
            dirname($appPath) . '/public/importar_supra_dataset_38_todos.php',
            $appPath . '/public/importar_supra_dataset_38.php',
            dirname($appPath) . '/public/importar_supra_dataset_38.php',
            dirname($appPath) . '/importar_supra_dataset_38_todos.php',
        ];
        $arquivo = null;
        foreach ($candidatos as $c) { if (is_file($c)) { $arquivo = realpath($c); break; } }
        if (!$arquivo) {
            $this->json(['ok' => false, 'erro' => 'Importador do Dataset 38 não encontrado.'], 404);
            return;
        }
        $dir    = dirname($arquivo);
        $file   = basename($arquivo);
        $phpBin = $this->phpCliBinary();
        $cmd    = '"' . $phpBin . '" "' . $file . '"';

        $proc = @proc_open($cmd, [1 => ['pipe','w'], 2 => ['pipe','w']], $pipes, $dir, null, ['bypass_shell' => true]);
        if (!is_resource($proc)) {
            $this->json(['ok' => false, 'erro' => 'Não foi possível iniciar o processo PHP CLI.'], 500);
            return;
        }
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $rc = proc_close($proc);
        $texto = trim($stdout . "\n" . $stderr);

        $inseridos = null;
        if (preg_match('/Inseridos:\s*(\d+)/i', $texto, $m)) $inseridos = (int)$m[1];

        $sucesso = ($inseridos !== null && $inseridos >= 0)
                || (strpos($texto, 'Sincronização do Dataset 38 finalizada') !== false)
                || (strpos($texto, 'Importação concluída') !== false);

        if ($sucesso) {
            $this->json(['ok' => true, 'inseridos' => $inseridos, 'mensagem' => 'Dataset 38 atualizado com sucesso.']);
            return;
        }
        $this->json(['ok' => false, 'erro' => 'Importador executou mas não retornou sucesso. Saída: ' . mb_substr($texto, -1500)], 500);
    }

    public function executarSiac()
    {
        $this->requireLoginAjax();
        if (!$this->usuarioPodeScraper()) {
            $this->json(['erro' => 'Acesso negado. Apenas administradores/desenvolvedores.'], 403);
            return;
        }
        $usuario = trim((string)($_POST['usuario'] ?? ''));
        $senha   = (string)($_POST['senha'] ?? '');
        if ($usuario === '' || $senha === '') {
            $this->json(['erro' => 'Usuário e senha são obrigatórios'], 422);
            return;
        }
        $scriptPath = $this->scriptSiacPath();
        if (!is_file($scriptPath)) {
            $this->json(['erro' => 'script_siac_isabella.php não encontrado em ' . $scriptPath], 500);
            return;
        }
        if (!is_file(dirname(__DIR__) . '/vendor/autoload.php')) {
            $this->json(['erro' => 'vendor/autoload.php ausente. Instale smalot/pdfparser.'], 500);
            return;
        }
        $edgedriver = $this->edgedriverPath();
        if ($edgedriver === null) {
            $this->json(['erro' => 'msedgedriver.exe não encontrado em app/public/.'], 500);
            return;
        }
        try {
            $sql = "SELECT instrumento AS Contrato, unidade_manual AS Estado
                      FROM `supra_dataset_38_todos`
                     WHERE instrumento    IS NOT NULL AND instrumento    <> ''
                       AND unidade_manual IS NOT NULL AND unidade_manual <> ''
                     GROUP BY instrumento, unidade_manual
                     ORDER BY instrumento";
            $contratos = $this->model->pdo()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $this->json(['erro' => 'Falha ao ler Dataset 38: ' . $e->getMessage()], 422);
            return;
        }
        if (!$contratos) {
            $this->json(['erro' => 'Nenhum contrato no Dataset 38. Atualize-o primeiro.'], 422);
            return;
        }

        $jobId   = 'siac_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3));
        $baseDir = dirname($scriptPath);
        $jobDir  = $baseDir . '/importar_siac_jobs/' . $jobId;
        @mkdir($jobDir, 0775, true);
        @mkdir($jobDir . '/saida', 0775, true);

        $contratosJson = $jobDir . '/contratos.json';
        file_put_contents($contratosJson, json_encode($contratos, JSON_UNESCAPED_UNICODE));

        try {
            $this->model->pdo()->prepare("
                INSERT INTO `siac_import_jobs`
                    (job_id, status, etapa, progresso, total, processados,
                     inseridos, contratos_afetados, iniciado_em, atualizado_em, usuario)
                VALUES (?, 'iniciando', 'spawn', 0, ?, 0, 0, 0, NOW(), NOW(), ?)
            ")->execute([$jobId, count($contratos), $usuario]);
        } catch (Throwable $e) {
            $this->json(['erro' => 'Falha ao registrar job: ' . $e->getMessage()], 500);
            return;
        }

        $phpBin = $this->phpCliBinary();
        $cmd = '"' . $phpBin . '" "' . $scriptPath . '"'
             . ' --contratos="' . $contratosJson . '"'
             . ' --output-dir="' . $jobDir . '/saida"'
             . ' --job="' . $jobId . '"'
             . ' --config="' . $baseDir . '/config.php"';

        $logFile = $jobDir . '/log.txt';
        $env = [
            'SIAC_USER'    => $usuario,
            'SIAC_PASS'    => $senha,
            'EDGEDRIVER'   => $edgedriver,
            'SYSTEMROOT'   => getenv('SYSTEMROOT')   ?: 'C:\\Windows',
            'WINDIR'       => getenv('WINDIR')       ?: 'C:\\Windows',
            'PATH'         => getenv('PATH')         ?: '',
            'TEMP'         => getenv('TEMP')         ?: sys_get_temp_dir(),
            'TMP'          => getenv('TMP')          ?: sys_get_temp_dir(),
            'USERPROFILE'  => getenv('USERPROFILE')  ?: '',
            'PROGRAMFILES' => getenv('PROGRAMFILES') ?: 'C:\\Program Files',
        ];

        $proc = @proc_open($cmd, [0 => ['pipe','r'], 1 => ['file',$logFile,'a'], 2 => ['file',$logFile,'a']], $pipes, $baseDir, $env, ['bypass_shell' => true]);
        if (!is_resource($proc)) {
            try {
                $this->model->pdo()->prepare("UPDATE `siac_import_jobs` SET status='erro', erro=?, atualizado_em=NOW() WHERE job_id=?")
                     ->execute(['proc_open falhou. Veja ' . $logFile, $jobId]);
            } catch (Throwable $e) {}
            $this->json(['erro' => 'Falha ao iniciar o processo PHP. Veja ' . $logFile], 500);
            return;
        }
        fclose($pipes[0]);
        $pid = proc_get_status($proc)['pid'] ?? null;

        @file_put_contents($jobDir . '/meta.json', json_encode([
            'job_id' => $jobId, 'pid' => $pid, 'php' => $phpBin, 'usuario' => $usuario,
            'iniciado_em' => date('Y-m-d H:i:s'), 'total_contratos' => count($contratos), 'comando' => $cmd,
        ], JSON_UNESCAPED_UNICODE));

        $this->json(['ok' => true, 'job' => $jobId, 'pid' => $pid, 'total' => count($contratos)]);
    }

    public function notificacoes()
    {
        $this->requireLoginAjax();
        $numero = $_GET['numero'] ?? null;
        $lida   = isset($_GET['lida']) ? ((int)$_GET['lida'] === 1) : null;
        $itens  = $this->model->listarNotificacoes($numero, $lida);

        $exercicio  = $this->model->exercicioDisponivel() ?? (int)date('Y');
        $acoesList  = $this->model->acoes($exercicio);
        $acoesPorId = [];
        foreach ($acoesList as $a) $acoesPorId[(string)$a['id']] = $a;

        foreach ($itens as &$n) {
            $c = $this->model->contratoPorNumero($n['contrato_numero']);
            if (!$c) continue;
            $acoesIds = $c['acoes'] ?? [];
            $n['acoes'] = $acoesIds;
            $n['acao_principal'] = $acoesIds[0] ?? null;
            if ($n['acao_principal'] && isset($acoesPorId[$n['acao_principal']])) {
                $acao = $acoesPorId[$n['acao_principal']];
                $n['acao_codigo']    = $acao['codigo'];
                $n['acao_descricao'] = $acao['descricao'];
            }
            $plan        = $this->model->supraPlanejamento($n['contrato_numero'], $exercicio);
            $mesesAtivos = $this->mesesAtivosNoAno($c, $exercicio);
            $n['meses_faltantes']  = $this->mesesFaltantes($plan, $mesesAtivos);
            $n['superintendencia'] = $c['superintendencia'] ?? ($n['superintendencia'] ?? null);
        }

        $this->json(['nao_lidas' => $this->model->contarNaoLidas($numero), 'itens' => $itens]);
    }

    public function marcarLida()
    {
        $this->requireLoginAjax();
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { $this->json(['erro' => 'id obrigatório'], 422); return; }
        $this->model->marcarNotificacaoLida($id);
        $this->json(['ok' => true]);
    }

    public function marcarTodasLidas()
    {
        $this->requireLoginAjax();
        $numero = $_POST['numero'] ?? null;
        $this->model->marcarTodasNotificacoesLidas($numero);
        $this->json(['ok' => true]);
    }

    public function salvarEstimativa()
    {
        $this->requireLoginAjax();
        $contrato      = trim((string)($_POST['contrato']      ?? ''));
        $mes           = trim((string)($_POST['mes']           ?? ''));
        $acao          = (string)($_POST['acao'] ?? 'salvar');
        $valor         = isset($_POST['valor']) ? (float)$_POST['valor'] : null;
        $justificativa = trim((string)($_POST['justificativa'] ?? ''));
        $usuario       = $_SESSION['usuario_nome'] ?? 'sistema';

        if ($contrato === '' || $mes === '') { $this->json(['erro' => 'contrato e mes são obrigatórios'], 422); return; }
        try {
            if ($acao === 'reset') { $this->model->removerEstimativaManual($contrato, $mes); $this->json(['ok' => true]); return; }
            if ($valor === null || $valor < 0) { $this->json(['erro' => 'Valor inválido.'], 422); return; }
            if ($justificativa === '') { $this->json(['erro' => 'Justificativa é obrigatória.'], 422); return; }
            if (mb_strlen($justificativa) > 500) { $this->json(['erro' => 'Justificativa deve ter no máximo 500 caracteres.'], 422); return; }
            
            $c = $this->model->contratoPorNumero($contrato);
            if (!$c) { $this->json(['erro' => 'Contrato não encontrado'], 404); return; }

            $medicoes = $this->model->siacMedicoes($contrato);
            $mediaNZ  = $this->mediaUltimasNaoZeradas($medicoes, 3)['media'];
            if ($mediaNZ <= 0) {
                $ds = $this->model->datasetRow($contrato);
                $mediaNZ = (float)($ds['media_ult_3_medicoes'] ?? 0);
            }
            $this->model->setEstimativaManual($contrato, $mes, $valor, $usuario, $mediaNZ, $justificativa);
            $this->json(['ok' => true, 'valor_calculado' => $mediaNZ]);
        } catch (Throwable $e) { $this->json(['erro' => $e->getMessage()], 500); }
    }

    public function salvarPlanejadoAnalista()
    {
        $this->requireLoginAjax();
        $contrato      = trim((string)($_POST['contrato']      ?? ''));
        $mes           = trim((string)($_POST['mes']           ?? ''));
        $acao          = (string)($_POST['acao'] ?? 'salvar');
        $valor         = isset($_POST['valor']) ? (float)$_POST['valor'] : null;
        $justificativa = trim((string)($_POST['justificativa'] ?? ''));
        $usuario       = $_SESSION['usuario_nome'] ?? 'sistema';

        if ($contrato === '' || $mes === '') { $this->json(['erro' => 'contrato e mes são obrigatórios'], 422); return; }
        try {
            if ($acao === 'reset') { $this->model->removerPlanejadoAnalista($contrato, $mes); $this->json(['ok' => true]); return; }
            if ($valor === null || $valor < 0) { $this->json(['erro' => 'Valor inválido.'], 422); return; }
            if ($justificativa === '') { $this->json(['erro' => 'Justificativa é obrigatória.'], 422); return; }
            if (mb_strlen($justificativa) > 500) { $this->json(['erro' => 'Justificativa deve ter no máximo 500 caracteres.'], 422); return; }
            
            $c = $this->model->contratoPorNumero($contrato);
            if (!$c) { $this->json(['erro' => 'Contrato não encontrado'], 404); return; }

            $medicoes  = $this->model->siacMedicoes($contrato);
            $medidoMes = 0.0;
            foreach ($medicoes as $r) {
                if (($r['mes_referencia'] ?? '') === $mes) { $medidoMes = (float)$r['valor_medido']; break; }
            }
            $valorCalculado = $medidoMes > 0 ? $medidoMes : $this->mediaUltimasNaoZeradas($medicoes, 3)['media'];
            $this->model->setPlanejadoAnalista($contrato, $mes, $valor, $usuario, $valorCalculado, $justificativa);
            $this->json(['ok' => true, 'valor_calculado' => $valorCalculado]);
        } catch (Throwable $e) { $this->json(['erro' => $e->getMessage()], 500); }
    }

    public function salvarPreferencia()
    {
        $this->requireLoginAjax();
        $contrato = trim((string)($_POST['contrato'] ?? ''));
        $campo    = (string)($_POST['campo'] ?? 'NPO');
        $usuario  = $_SESSION['usuario_nome'] ?? 'sistema';
        if ($contrato === '') { $this->json(['erro' => 'contrato obrigatório'], 422); return; }
        if (!in_array($campo, ['NPO','ANALISTA'], true)) { $this->json(['erro' => 'campo inválido'], 422); return; }
        $this->model->setPreferencia($contrato, $campo, $usuario);
        $this->json(['ok' => true, 'campo' => $campo]);
    }

    // =============================================================
    // HELPERS
    // =============================================================
    private function phpCliBinary(): string
    {
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        if ($isWindows) {
            foreach (['C:\\xampp\\php\\php.exe', 'C:\\php\\php.exe'] as $c) if (is_file($c)) return $c;
            if (preg_match('/php\.exe$/i', PHP_BINARY) && is_file(PHP_BINARY)) return PHP_BINARY;
        } else {
            foreach (['/usr/bin/php', '/usr/local/bin/php'] as $c) if (is_file($c)) return $c;
            if (preg_match('/php$/i', PHP_BINARY) && is_file(PHP_BINARY)) return PHP_BINARY;
        }
        return 'php';
    }
    private function scriptSiacPath(): string
    {
        $appPath = defined('APP_PATH') ? APP_PATH : dirname(__DIR__);
        $cands = [ $appPath . '/public/script_siac_isabella.php', dirname($appPath) . '/public/script_siac_isabella.php' ];
        foreach ($cands as $c) if (is_file($c)) return realpath($c);
        return $cands[0];
    }
    private function edgedriverPath()
    {
        $appPath = defined('APP_PATH') ? APP_PATH : dirname(__DIR__);
        $cands = [ $appPath . '/public/msedgedriver.exe', dirname($appPath) . '/public/msedgedriver.exe' ];
        foreach ($cands as $c) if (is_file($c)) return realpath($c);
        return null;
    }

    private function mediaUltimasNaoZeradas(array $medicoes, int $n = 3): array
    {
        $validas = [];
        foreach (array_reverse($medicoes) as $r) {
            $v = (float)($r['valor_medido'] ?? 0);
            if ($v > 0) {
                $validas[] = ['mes' => $r['mes_referencia'], 'valor' => $v];
                if (count($validas) >= $n) break;
            }
        }
        $validas = array_reverse($validas);
        $soma = array_sum(array_column($validas, 'valor'));
        return ['valores' => $validas, 'media' => count($validas) > 0 ? $soma / count($validas) : 0.0];
    }

    // =============================================================
    // INDICADORES
    // =============================================================
    private function calcularIndicadores(array $contrato, int $exercicio): array
    {
        $numero = $contrato['numero'];
        $ds     = $this->model->datasetRow($numero);

        $acaoPrincipal     = (string)($contrato['acao_id_principal'] ?? ($contrato['acoes'][0] ?? ''));
        $dotacaoAtualizada = $this->model->dotacaoAtualizada($acaoPrincipal, $exercicio);

        $valorPi             = (float)($contrato['valor_pi']       ?? 0);
        $valorAditivo        = (float)($contrato['valor_aditivo']  ?? 0);
        $valorReajuste       = (float)($contrato['valor_reajuste'] ?? 0);
        $totalInstrumento    = $valorPi + $valorAditivo + $valorReajuste;
        $totalInstrumentoCol = (isset($contrato['pi_a_r']) && $contrato['pi_a_r'] !== null) ? (float)$contrato['pi_a_r'] : null;

        $disponivelBruto = (float)($contrato['disponivel_bruto'] ?? ((float)($contrato['valor_rap_efetivo'] ?? $contrato['valor_rap']) + (float)($contrato['valor_empenhado_efetivo'] ?? $contrato['valor_empenhado'])));

        $medicoes    = $this->model->siacMedicoes($numero);
        $liquidacoes = $this->model->supraLiquidacao($numero);
        $hojeMes     = (new DateTimeImmutable('first day of this month'))->format('Y-m-01');

        $medidoPorMes = [];
        foreach ($medicoes as $r) {
            $mr = $r['mes_referencia'] ?? null;
            if ($mr) $medidoPorMes[$mr] = (float)$r['valor_medido'];
        }

        $medicoesAno = array_filter($medicoes, function($r) use ($exercicio, $hojeMes) { return (strpos($r['mes_referencia'] ?? '', (string)$exercicio) === 0) && $r['mes_referencia'] <= $hojeMes; });
        $liqAno = array_filter($liquidacoes, function($k) use ($exercicio, $hojeMes) { return (strpos($k, (string)$exercicio) === 0) && $k <= $hojeMes; }, ARRAY_FILTER_USE_KEY);

        $medidoAcum    = array_sum(array_map(function($r) { return (float)$r['valor_medido']; }, $medicoesAno));
        $liquidadoAcum = array_sum(array_map(function($r) { return (float)$r['valor']; }, $liqAno));

        $totalLiquidadoGeral = $this->model->totalLiquidadoContrato($numero);
        $diferencaIL = $totalLiquidadoGeral !== null ? round($totalInstrumento - $totalLiquidadoGeral, 2) : null;

        $pendenteNaoLiq  = max(0.0, $medidoAcum - $liquidadoAcum);
        $valorDisponivel = $disponivelBruto;

        $temMedicao    = ($medidoAcum > 0) || ($medicoesAno !== []);
        $statusMedicao = $temMedicao ? 'COM_MEDICAO' : 'SEM_MEDICAO';

        $planejamentoSupra = $this->model->supraPlanejamento($numero, $exercicio);
        $mesesAtivos       = $this->mesesAtivosNoAno($contrato, $exercicio);
        $previsaoCompleta  = $this->previsaoEstaCompleta($planejamentoSupra, $mesesAtivos);
        $mesesFaltantes    = $this->mesesFaltantes($planejamentoSupra, $mesesAtivos);

        $mediaDs = (float)($ds['media_ult_3_medicoes'] ?? 0);
        $mediaNZ = $this->mediaUltimasNaoZeradas($medicoes, 3)['media'];
        $mediaHistorica = $mediaNZ > 0 ? $mediaNZ : ($mediaDs > 0 ? $mediaDs : ($this->estatisticaMedicoes($medicoesAno)['media'] ?? 0));

        $planejadosAnalista = $this->model->getPlanejadoAnalistaContrato($numero);
        $campoPref          = $this->model->getPreferencia($numero);

        $ultimaLiq = null;
        foreach (array_reverse($liquidacoes, true) as $m => $liq) {
            if (!(strpos($m, (string)$exercicio) === 0)) continue;
            if (($liq['valor'] ?? 0) > 0) { $ultimaLiq = $m; break; }
        }
        $ultimaMed = null;
        foreach (array_reverse($medicoes, false) as $r) {
            $mr = $r['mes_referencia'] ?? null;
            if ($mr && (strpos($mr, (string)$exercicio) === 0)) { $ultimaMed = $mr; break; }
        }

        $startAfter = null;
        if ($ultimaLiq && $ultimaMed) $startAfter = max($ultimaLiq, $ultimaMed);
        elseif ($ultimaLiq)           $startAfter = $ultimaLiq;
        elseif ($ultimaMed)           $startAfter = $ultimaMed;

        $estimativas = $this->model->getEstimativasContrato($numero);

        $totalPlanejadoRestante = 0.0;
        $mesesParaProjetar      = [];

        foreach ($mesesAtivos as $mes) {
            if ($startAfter && $mes <= $startAfter) continue;
            $mesesParaProjetar[] = $mes;

            $npo = $planejamentoSupra[$mes]           ?? null;
            $ana = $planejadosAnalista[$mes]['valor'] ?? null;

            if ($campoPref === 'ANALISTA' && $ana !== null && $ana > 0) { $totalPlanejadoRestante += $ana; continue; }
            if ($npo !== null && $npo > 0) { $totalPlanejadoRestante += $npo; continue; }
            if (array_key_exists($mes, $medidoPorMes)) { $totalPlanejadoRestante += $medidoPorMes[$mes]; continue; }
            if (isset($estimativas[$mes])) {
                $totalPlanejadoRestante += $estimativas[$mes]['valor'];
            } else {
                $totalPlanejadoRestante += $mediaHistorica;
            }
        }

        $previsaoMensal = $totalPlanejadoRestante + $pendenteNaoLiq;
        if ($totalPlanejadoRestante > 0 || $pendenteNaoLiq > 0) {
            $origem = 'Soma (NPO/Analista/Medido/Estimado) + pendente';
            $calculo = [
                'formula'          => 'Cascata: Analista → NPO → Medição do mês → Estimativa. + pendente.',
                'total_planejado'  => round($totalPlanejadoRestante, 2),
                'pendente'         => round($pendenteNaoLiq, 2),
                'media_nao_zero'   => round($mediaNZ, 2),
                'media_dataset_47' => $mediaDs > 0 ? round($mediaDs, 2) : null,
                'campo_preferencia'=> $campoPref,
            ];
        } else {
            $previsaoMensal = $mediaDs + $pendenteNaoLiq;
            $origem = 'Média Dataset 47 + pendente';
            $calculo = [
                'formula'          => 'Média das últimas 3 medições (Dataset 47) + medição não liquidada',
                'media_dataset_47' => round($mediaDs, 2),
                'pendente'         => round($pendenteNaoLiq, 2),
                'campo_preferencia'=> $campoPref,
            ];
        }

        $statusSiac = $this->model->siacCabecalho($numero)['status'] ?? 'EM_EXECUCAO';

        $coberturaMesesRaw = null;
        $coberturaMeses    = null;
        $farol             = null;

        if ($statusMedicao === 'SEM_MEDICAO' || $previsaoMensal <= 0) {
            $coberturaMeses = null;
            $farol          = null;
        } else {
            $saldoRestante = $valorDisponivel;
            if (count($mesesParaProjetar) > 0) {
                $mesesPagosInteiros = 0;
                $fracaoMes = 0;
                foreach ($mesesParaProjetar as $mesProj) {
                    $npo = $planejamentoSupra[$mesProj]           ?? null;
                    $ana = $planejadosAnalista[$mesProj]['valor'] ?? null;
                    if ($campoPref === 'ANALISTA' && $ana !== null && $ana > 0) $valorMes = $ana;
                    elseif ($npo !== null && $npo > 0)                          $valorMes = $npo;
                    elseif (array_key_exists($mesProj, $medidoPorMes))          $valorMes = $medidoPorMes[$mesProj];
                    elseif (isset($estimativas[$mesProj]))                      $valorMes = $estimativas[$mesProj]['valor'];
                    else                                                        $valorMes = $mediaHistorica;

                    if ($valorMes <= 0) continue;
                    if ($saldoRestante >= $valorMes) {
                        $saldoRestante -= $valorMes;
                        $mesesPagosInteiros++;
                    } else {
                        $fracaoMes = $saldoRestante / $valorMes;
                        break;
                    }
                }
                $coberturaMesesRaw = $mesesPagosInteiros + $fracaoMes;
            } else {
                $coberturaMesesRaw = $saldoRestante / $previsaoMensal;
            }
            $coberturaMeses = (int)floor($coberturaMesesRaw);
            $farol = $this->farol($coberturaMesesRaw);
            if ($statusSiac === 'CONCLUIDO') $farol = 'CONCLUIDO';
        }

        $valorNecessarioAno = $totalPlanejadoRestante + $pendenteNaoLiq;

        $serie = $this->serieMensal(
            $medicoes, $liquidacoes, $planejamentoSupra,
            $mesesAtivos, $previsaoCompleta, $mediaHistorica,
            $estimativas, $planejadosAnalista, $campoPref, $temMedicao
        );

        $nomeUsual      = $contrato['nome_usual']      ?? ($ds['nome_usual'] ?? null);
        $br             = $contrato['br']              ?? ($ds['br']         ?? null);
        $empresa        = $contrato['empresa']         ?? ($ds['empresa']    ?? null);
        $dataInicio     = $contrato['data_inicio']      ?? null;
        $dataFim        = $contrato['data_fim']         ?? null;
        $terminoServicos= $contrato['termino_servicos'] ?? null;
        $terminoVigencia= $contrato['termino_vigencia'] ?? null;

        return [
            'numero'                   => $numero,
            'tipo'                     => $contrato['tipo'],
            'superintendencia'         => $contrato['superintendencia'],
            'nome_usual'               => $nomeUsual,
            'br'                       => $br,
            'empresa'                  => $empresa,
            'acoes'                    => $contrato['acoes'] ?? [],
            'status'                   => $statusSiac,
            'concluido'                => $statusSiac === 'CONCLUIDO',
            'status_medicao'           => $statusMedicao,
            'dotacao_atualizada'       => round($dotacaoAtualizada, 2),

            'data_inicio'              => $dataInicio,
            'data_fim'                 => $dataFim,
            'termino_servicos'         => $terminoServicos,
            'termino_vigencia'         => $terminoVigencia,

            'valor_pi'                 => round($valorPi, 2),
            'valor_aditivo'            => round($valorAditivo, 2),
            'valor_reajuste'           => round($valorReajuste, 2),
            'total_instrumento'        => round($totalInstrumento, 2),
            'total_instrumento_coluna' => $totalInstrumentoCol !== null ? round($totalInstrumentoCol, 2) : null,

            'total_liquidado'                 => $totalLiquidadoGeral !== null ? round($totalLiquidadoGeral, 2) : null,
            'diferenca_instrumento_liquidado' => $diferencaIL,

            'disponivel_bruto'         => round($disponivelBruto, 2),
            'saldo_medido_nao_liq'     => round($medidoAcum - $liquidadoAcum, 2),
            'disponivel_real'          => round($valorDisponivel, 2),
            'valor_disponivel'         => round($valorDisponivel, 2),
            'valor_necessario_ano'     => round($valorNecessarioAno, 2),
            'medido_acum'              => round($medidoAcum, 2),
            'liquidado_acum'           => round($liquidadoAcum, 2),
            'valor_empenhado'          => round((float)($contrato['valor_empenhado_efetivo'] ?? $contrato['valor_empenhado'] ?? 0), 2),
            'valor_rap'                => round((float)($contrato['valor_rap_efetivo'] ?? $contrato['valor_rap'] ?? 0), 2),
            'previsao_mensal'          => round($previsaoMensal, 2),
            'origem_previsao'          => $origem,
            'previsao_completa'        => $previsaoCompleta,
            'meses_faltantes'          => $mesesFaltantes,
            'cobertura_meses'          => $coberturaMeses,
            'cobertura_meses_raw'      => $coberturaMesesRaw !== null ? round($coberturaMesesRaw, 2) : null,
            'farol'                    => $farol,
            'alerta_planejamento'      => ! $previsaoCompleta,

            'campo_planejamento'       => $campoPref,
            'planejados_analista'      => $planejadosAnalista,
            'media_nao_zero'           => round($mediaNZ, 2),

            'calculo_previsao'         => $calculo,
            'performance'              => $this->performance($medicoesAno, $planejamentoSupra, $exercicio, $previsaoMensal, $previsaoCompleta, $medidoAcum),
            'serie_mensal'             => $serie['rows'],
            'anomalias_saldo'          => $serie['anomalias'],
        ];
    }

    private function medicoesComEstatistica(string $numero, int $limite = 12): array
    {
        $medicoes = $this->model->siacMedicoes($numero);
        $hoje = new DateTimeImmutable('first day of this month');
        $anoAtual = (int)$hoje->format('Y');
        $anoAnterior = $anoAtual - 1;

        $regua = [];
        for ($m = 1; $m <= 12; $m++) $regua[] = sprintf('%04d-%02d-01', $anoAnterior, $m);
        for ($m = 1; $m <= 12; $m++) $regua[] = sprintf('%04d-%02d-01', $anoAtual, $m);

        $mapMed = [];
        foreach ($medicoes as $r) $mapMed[$r['mes_referencia']] = $r;

        $serieCompleta = [];
        $valoresReais = [];

        foreach ($regua as $mes) {
            if (isset($mapMed[$mes])) {
                $serieCompleta[] = $mapMed[$mes];
                $valoresReais[] = (float)$mapMed[$mes]['valor_medido'];
            } else {
                $serieCompleta[] = [
                    'mes_referencia' => $mes,
                    'valor_medido'   => null,
                    'data_medicao'   => null
                ];
            }
        }

        if (empty($valoresReais)) {
            return [
                'contrato'     => $numero,
                'serie'        => $serieCompleta,
                'media'        => 0, 'mediana' => 0, 'desvio' => 0.0, 'cv' => null, 'outliers' => [], 'alerta' => false,
                'faixa_1sigma' => ['inf' => 0, 'sup' => 0],
                'sem_serie'    => false,
                'mensagem'     => 'Nenhuma medição registrada neste período.'
            ];
        }

        $apenasReais = array_values($mapMed);
        $stats = $this->estatisticaMedicoes($apenasReais);

        return [
            'contrato' => $numero,
            'serie'    => $serieCompleta,
            'media'    => round($stats['media'], 2),
            'mediana'  => round($stats['mediana'], 2),
            'desvio'   => round($stats['desvio'], 2),
            'cv'       => $stats['cv'] !== null ? round($stats['cv'], 2) : null,
            'outliers' => $stats['outliers'],
            'alerta'   => $stats['alerta'],
            'faixa_1sigma' => [
                'inf' => round($stats['media'] - $stats['desvio'], 2),
                'sup' => round($stats['media'] + $stats['desvio'], 2),
            ],
            'sem_serie' => false,
        ];
    }

    // =============================================================
    // HELPERS
    // =============================================================
    private function mesesAtivosNoAno(array $contrato, int $ano): array
    {
        $fimContrato = $contrato['termino_servicos'] ?? $contrato['data_fim'] ?? $contrato['termino_vigencia'] ?? null;
        $ini = !empty($contrato['data_inicio']) ? new DateTimeImmutable($contrato['data_inicio']) : new DateTimeImmutable("$ano-01-01");
        $fim = !empty($fimContrato)             ? new DateTimeImmutable($fimContrato)             : new DateTimeImmutable("$ano-12-31");
        $jan = new DateTimeImmutable("$ano-01-01");
        $dez = new DateTimeImmutable("$ano-12-31");
        if ($ini < $jan) $ini = $jan;
        if ($fim > $dez) $fim = $dez;
        if ($ini > $fim) return [];
        $out = [];
        for ($m = $ini->modify('first day of this month'); $m <= $fim; $m = $m->modify('+1 month')) {
            $out[] = $m->format('Y-m-01');
        }
        return $out;
    }

    private function previsaoEstaCompleta(array $plan, array $mesesAtivos): bool
    {
        if ($mesesAtivos === []) return false;
        foreach ($mesesAtivos as $m) if (!array_key_exists($m, $plan)) return false;
        return true;
    }

    private function mesesFaltantes(array $plan, array $mesesAtivos): array
    {
        $out = [];
        foreach ($mesesAtivos as $m) if (!array_key_exists($m, $plan)) $out[] = $m;
        return $out;
    }

    private function estatisticaMedicoes(array $medicoes): array
    {
        if ($medicoes === []) return ['media'=>0.0,'mediana'=>0.0,'desvio'=>0.0,'cv'=>null, 'n'=>0,'valores'=>[],'outliers'=>[],'alerta'=>false];
        $nJanela = max(1, $this->model->configInt('media_meses_estimativa', 3));
        $ultimas = array_slice($medicoes, -$nJanela);
        $valores = array_map(function($r) { return (float)$r['valor_medido']; }, $ultimas);
        $n       = count($valores);
        $media   = array_sum($valores) / $n;
        $desvio  = 0.0;
        if ($n > 1) {
            $soma = 0.0;
            foreach ($valores as $v) $soma += ($v - $media) ** 2;
            $desvio = sqrt($soma / ($n - 1));
        }
        $ord = $valores; sort($ord);
        $mediana = ($n % 2 === 1) ? (float)$ord[intdiv($n, 2)] : ($ord[$n/2 - 1] + $ord[$n/2]) / 2.0;
        $cv = $media > 0 ? ($desvio / $media) * 100 : null;
        $k = $this->model->configFloat('media_outlier_sigma', 2.0);
        $outliers = [];
        foreach ($ultimas as $r) {
            $v = (float)$r['valor_medido'];
            if ($desvio > 0 && abs($v - $media) > $k * $desvio) $outliers[] = ['mes' => $r['mes_referencia'], 'valor' => $v];
        }
        $alerta = $cv !== null && $cv > $this->model->configFloat('media_cv_alerta', 30);
        return compact('media','mediana','desvio','cv','n','valores','outliers','alerta');
    }

    private function farol(float $cobertura): string
    {
        if ($cobertura > 3.0) return 'VERDE';
        if ($cobertura >= 2.0) return 'AMARELO';
        return 'VERMELHO';
    }

    private function performance(array $medicoes, array $plan, int $ano, float $mediaEstimada, bool $completa, $medidoAcumDs = null): array
    {
        $mesCorrente = (new DateTimeImmutable('first day of this month'))->format('Y-m-01');
        $previstoAcum = 0.0;
        for ($m = new DateTimeImmutable("$ano-01-01"); $m->format('Y-m-01') <= $mesCorrente; $m = $m->modify('+1 month')) {
            $k = $m->format('Y-m-01');
            $previstoAcum += $plan[$k] ?? ($completa ? 0.0 : $mediaEstimada);
        }
        if ($medicoes === [] && $medidoAcumDs !== null) {
            $medidoAcum = $medidoAcumDs;
        } else {
            $medidoAcum = array_sum(array_map(function($r) { return (float)$r['valor_medido']; }, array_filter($medicoes, function($r) use ($mesCorrente) { return $r['mes_referencia'] <= $mesCorrente; })));
        }
        $dif    = $previstoAcum - $medidoAcum;
        $difPct = $previstoAcum > 0 ? round($dif / $previstoAcum * 100, 2) : null;
        $limite = $this->model->configFloat('desvio_performance_pct', 30);
        return [
            'previsto_acum'    => round($previstoAcum, 2),
            'executado_acum'   => round($medidoAcum, 2),
            'diferenca_valor'  => round($dif, 2),
            'diferenca_pct'    => $difPct,
            'fora_performance' => $difPct !== null && abs($difPct) > $limite,
        ];
    }

    // ============================================================
    // ✅ CORREÇÃO CRÍTICA: DIFERENÇA SIMPLES (MÊS A MÊS) AO INVÉS DE ACUMULADO
    // ============================================================
    private function serieMensal(array $medicoes, array $liquidacoes, array $plan,
                                 array $mesesAtivos, bool $completa, float $media,
                                 array $estimativas = [],
                                 array $planejadosAnalista = [],
                                 string $campoPref = 'NPO',
                                 bool $temMedicao = false): array
    {
        $medIdx = array_column($medicoes, 'valor_medido', 'mes_referencia');
        $medDat = array_column($medicoes, 'data_medicao',  'mes_referencia');

        $hoje       = new DateTimeImmutable('first day of this month');
        $hojeMes    = $hoje->format('Y-m-01');
        $tolerancia = $this->model->configFloat('saldo_tolerancia_anomalia', 100.0);

        $anomalias = 0;
        $rows = [];

        $ano = (int)explode('-', $hojeMes)[0];
        $reguaMeses = [];
        for ($m = 1; $m <= 12; $m++) {
            $reguaMeses[] = sprintf('%04d-%02d-01', $ano, $m);
        }

        foreach ($reguaMeses as $m) {
            $isFuturo = ($m > $hojeMes);

            $temRegistroMes = !$isFuturo && array_key_exists($m, $medIdx);
            $medido         = $temRegistroMes ? (float)$medIdx[$m] : null;
            $liquidado      = !$isFuturo ? (float)($liquidacoes[$m]['valor'] ?? 0) : 0.0;

            $planReal = array_key_exists($m, $plan) ? (float)$plan[$m] : null;
            $temPlano = $planReal !== null;

            $estCalc = $media > 0 ? $media : null;
            $estExib = $estCalc;
            $estManual = null;
            if (isset($estimativas[$m])) {
                $estManual = $estimativas[$m];
                $estExib   = $estManual['valor'];
            }
            $planExib = $temPlano ? $planReal : $estExib;

            $anaManual = $planejadosAnalista[$m] ?? null;
            if ($anaManual !== null)                                      $planejadoAnalistaValor = $anaManual['valor'];
            elseif ($temRegistroMes && $medido !== null)                  $planejadoAnalistaValor = $medido;
            elseif ($estExib !== null)                                    $planejadoAnalistaValor = $estExib;
            else                                                          $planejadoAnalistaValor = null;

            // ✅ CORREÇÃO: Não acumula. Faz a diferença exata do mês.
            $difMes = null;
            if ($temRegistroMes && $medido !== null) {
                $difMes = $medido - $liquidado;
            }

            $mesRef         = new DateTimeImmutable($m);
            $fimMes         = $mesRef->modify('last day of this month');
            $mesEncerrado   = $hoje > $fimMes;
            $diasDesdeFim   = $mesEncerrado ? (int)$hoje->diff($fimMes)->days : 0;
            $muitoEncerrado = $diasDesdeFim > 30;

            $statusSaldo = 'ok';
            $avisoSaldo  = null;

            if ($temRegistroMes && $difMes !== null && abs($difMes) > $tolerancia) {
                if ($muitoEncerrado) {
                    $statusSaldo = 'anomalia';
                    $avisoSaldo  = sprintf('Mês encerrado há %d dias com medição (R$ %s) diferente da liquidação (R$ %s).', $diasDesdeFim, number_format((float)$medido, 2, ',', '.'), number_format($liquidado, 2, ',', '.'));
                    $anomalias++;
                } else {
                    $statusSaldo = 'pendente';
                    $avisoSaldo  = $mesEncerrado ? 'Mês recém-encerrado: liquidação aguardando processamento.' : 'Mês em andamento: medição aguardando liquidação.';
                }
            }

            $rows[] = [
                'mes'                       => $m,
                'planejado'                 => $temPlano ? round($planReal, 2) : null,
                'estimado'                  => $estExib !== null ? round($estExib, 2) : null,
                'estimado_calculado'        => $estCalc !== null ? round($estCalc, 2) : null,
                'estimado_manual'           => $estManual,
                'planejado_exibido'         => $planExib !== null ? round($planExib, 2) : 0.0,
                'tem_plano'                 => $temPlano,
                'medido'                    => $medido !== null ? round($medido, 2) : null,
                'liquidado'                 => round($liquidado, 2),
                'data_ref_medicao'          => $medDat[$m]              ?? null,
                'data_ref_liquidacao'       => $liquidacoes[$m]['data'] ?? null,
                'saldo_medido_nao_liq'      => $difMes !== null ? round($difMes, 2) : null, // ✅ Retorna apenas a diferença simples
                'diferenca_mes'             => $difMes !== null ? round($difMes, 2) : null,
                'status_saldo'              => $statusSaldo,
                'aviso_saldo'               => $avisoSaldo,
                'planejado_analista'        => $planejadoAnalistaValor !== null ? round($planejadoAnalistaValor, 2) : null,
                'planejado_analista_manual' => $anaManual,
                'campo_preferencia'         => $campoPref,
            ];
        }
        return ['rows' => $rows, 'anomalias' => $anomalias];
    }

    private function json($data, int $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}