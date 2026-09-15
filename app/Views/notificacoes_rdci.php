<?php
// View notificacoes_rdci.php
// ============================================================
// VARIÁVEIS ESPERADAS DO CONTROLLER:
// $contratos, $ufs, $brs, $statusCronogramaList, $usuario_nome
// ============================================================

// --- BLOCO DE DEFINIÇÃO DE VARIÁVEIS (IGUAL À HOME) ---
if (!function_exists('obterIniciais')) {
    function obterIniciais($nome) {
        $partes = explode(' ', trim($nome));
        $iniciais = '';
        foreach ($partes as $parte) {
            if (!empty($parte)) {
                $iniciais .= strtoupper(substr($parte, 0, 1));
            }
        }
        return substr($iniciais, 0, 2);
    }
}

$usuario_nome = $_SESSION['usuario_nome'] ?? 'Usuário';
$usuario_nivel = $_SESSION['usuario_nivel'] ?? 'usuario';
$usuario_id = $_SESSION['usuario_id'] ?? 0;
$setor_slug = $_SESSION['setor_slug'] ?? 'assessoria-projetos';
$setor_nome = $setor_slug === 'assessoria-projetos' ? 'Assessoria e Projetos' : 'Atlas/Monitoramento';
$isAdmin = in_array($usuario_nivel, ['desenvolvedor', 'admin']);

$pendentes = 0;
if ($isAdmin) {
    try {
        global $pdo;
        $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE status = 'pendente'");
        $pendentes = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        $pendentes = 0;
    }
}

$total_setores = 0;
if ($usuario_id) {
    try {
        global $pdo;
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuario_setor WHERE usuario_id = ?");
        $stmt->execute([$usuario_id]);
        $total_setores = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        $total_setores = 0;
    }
}

$setores_usuario = [];
if ($usuario_id) {
    try {
        global $pdo;
        $stmt = $pdo->prepare("
            SELECT s.nome 
            FROM setores s 
            INNER JOIN usuario_setor us ON s.id = us.setor_id 
            WHERE us.usuario_id = ?
        ");
        $stmt->execute([$usuario_id]);
        $setores_usuario = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        $setores_usuario = [];
    }
}

// Captura filtros da URL
$filtroUF = $_GET['uf'] ?? '';
$filtroStatusCron = $_GET['status_cronograma'] ?? '';
$filtroNotificado = $_GET['notificado'] ?? '';
$filtroIsento = $_GET['isento'] ?? '';
$filtroBusca = $_GET['busca'] ?? '';
$filtroContrato = $_GET['contrato'] ?? '';
$filtroStatusNotif = $_GET['status_notificacao'] ?? '';
$filtroAcao = $_GET['acao'] ?? ''; // Filtro por ação

// ============================================================
// FUNÇÕES DE CÁLCULO
// ============================================================

function calcularStatusCronograma($dataCron) {
    if (empty($dataCron)) {
        return ['texto' => 'Sem data', 'categoria' => 'sem_data', 'class' => 'cron-semdata'];
    }
    $hoje = new DateTime();
    $hoje->setTime(0, 0, 0);
    $dataCronObj = new DateTime($dataCron);
    $dataCronObj->setTime(0, 0, 0);
    $diff = $hoje->diff($dataCronObj);
    $dias = $diff->days;

    if ($dataCronObj < $hoje) {
        return ['texto' => "Vencido há {$dias} dias", 'categoria' => 'vencido', 'class' => 'cron-vencido'];
    } elseif ($dias <= 30) {
        return ['texto' => ($dias == 0) ? 'A vencer hoje' : "A vencer em {$dias} dias", 'categoria' => 'a_vencer', 'class' => 'cron-atencao'];
    } else {
        return ['texto' => 'No prazo', 'categoria' => 'no_prazo', 'class' => 'cron-prazo'];
    }
}

function calcularStatusNotificacao($c) {
    $hoje = new DateTime();
    $hoje->setTime(0, 0, 0);
    $isento = $c['isento_notificacao'] ?? 0;
    $dataCron = $c['data_termino_projeto_cronog'] ?? null;
    $dataUltimaNotif = $c['data_ultima_notificacao'] ?? null;

    if ($isento) {
        return ['status' => 'Isento de notificação', 'class' => 'notif-isento'];
    }

    if (empty($dataCron)) {
        return ['status' => 'Sem data de cronograma', 'class' => 'notif-faltadata'];
    }

    $cronStatus = calcularStatusCronograma($dataCron);
    if ($cronStatus['categoria'] === 'no_prazo') {
        return ['status' => 'Não necessita notificar', 'class' => 'notif-semnecessidade'];
    }

    if ($cronStatus['categoria'] === 'a_vencer') {
        preg_match('/\d+/', $cronStatus['texto'], $matches);
        $dias = $matches[0] ?? 0;
        if ($dias == 0) {
            return ['status' => 'Notificar hoje', 'class' => 'notif-atencao'];
        } else {
            return ['status' => "Notificar em {$dias} dias", 'class' => 'notif-atencao'];
        }
    }

    // Vencido
    if (empty($dataUltimaNotif)) {
        return ['status' => 'Falta data de notificação', 'class' => 'notif-faltadata'];
    }

    $dataNotif = new DateTime($dataUltimaNotif);
    $dataNotif->setTime(0, 0, 0);
    $diasDesdeNotif = $hoje->diff($dataNotif)->days;
    if ($dataNotif > $hoje) $diasDesdeNotif = 0;

    if ($diasDesdeNotif >= 45) {
        return ['status' => "Notificação vencida há " . ($diasDesdeNotif - 45) . " dias", 'class' => 'notif-vencido'];
    } elseif ($diasDesdeNotif >= 30) {
        $diasRestantes = 45 - $diasDesdeNotif;
        return ['status' => ($diasRestantes == 1) ? 'Notificar amanhã' : "Notificar em {$diasRestantes} dias", 'class' => 'notif-atencao'];
    } else {
        return ['status' => 'Aguardando prazo de notificação', 'class' => 'notif-tranquilo'];
    }
}

// ============================================================
// APLICAÇÃO DOS FILTROS
// ============================================================
$contratosFiltrados = array_filter($contratos, function($c) use (
    $filtroUF, $filtroStatusCron, $filtroNotificado, $filtroIsento,
    $filtroBusca, $filtroContrato, $filtroStatusNotif, $filtroAcao
) {
    if ($filtroUF && strcasecmp($c['uf'] ?? '', $filtroUF) !== 0) return false;
    if ($filtroStatusCron) {
        $statusCron = calcularStatusCronograma($c['data_termino_projeto_cronog'] ?? null);
        if ($statusCron['categoria'] !== $filtroStatusCron) return false;
    }
    if ($filtroNotificado === 'sim' && !($c['notificado'] ?? 0)) return false;
    if ($filtroNotificado === 'nao' && ($c['notificado'] ?? 0)) return false;
    if ($filtroIsento === 'sim' && !($c['isento_notificacao'] ?? 0)) return false;
    if ($filtroIsento === 'nao' && ($c['isento_notificacao'] ?? 0)) return false;
    if ($filtroAcao) {
        $acao = $c['status_acao'] ?? 'nao_notificar';
        if ($acao !== $filtroAcao) return false;
    }
    if ($filtroBusca) {
        $busca = strtolower($filtroBusca);
        $match = false;
        $campos = ['instrumento', 'nome_usual', 'empresa', 'subtrecho'];
        foreach ($campos as $campo) {
            if (stripos($c[$campo] ?? '', $busca) !== false) { $match = true; break; }
        }
        if (!$match) return false;
    }
    if ($filtroContrato && strcasecmp($c['instrumento'] ?? '', $filtroContrato) !== 0) return false;
    if ($filtroStatusNotif && $filtroStatusNotif !== 'todos') {
        $notifStatus = calcularStatusNotificacao($c);
        $catNotif = '';
        switch ($notifStatus['status']) {
            case 'Isento de notificação': $catNotif = 'isento'; break;
            case 'Não necessita notificar': $catNotif = 'nao_necessita'; break;
            case (strpos($notifStatus['status'], 'Notificar') === 0):
            case (strpos($notifStatus['status'], 'Aguardando') !== false):
                if (strpos($notifStatus['status'], 'vencida') !== false) {
                    $catNotif = 'vencido_notificacao_vencida';
                } elseif (strpos($notifStatus['status'], 'Falta data') !== false) {
                    $catNotif = 'vencido_sem_notificacao';
                } elseif (strpos($notifStatus['status'], 'Aguardando') !== false) {
                    $catNotif = 'vencido_notificacao_em_dia';
                } else {
                    $catNotif = 'atencao';
                }
                break;
            default:
                if (strpos($notifStatus['status'], 'vencida') !== false) {
                    $catNotif = 'vencido_notificacao_vencida';
                } elseif (strpos($notifStatus['status'], 'Falta data') !== false) {
                    $catNotif = 'vencido_sem_notificacao';
                } elseif (strpos($notifStatus['status'], 'Aguardando') !== false) {
                    $catNotif = 'vencido_notificacao_em_dia';
                } else {
                    $catNotif = 'vencido_sem_notificacao';
                }
                break;
        }
        if ($catNotif !== $filtroStatusNotif) return false;
    }
    return true;
});

$contratos = $contratosFiltrados;
$totalLinhas = count($contratos);

// Estatísticas
$contratosUnicos = [];
foreach ($contratos as $c) {
    $contratosUnicos[$c['instrumento']] = true;
}
$totalContratos = count($contratosUnicos);
$contratosList = array_keys($contratosUnicos);
sort($contratosList);

$ufList = [];
foreach ($contratos as $c) {
    if (!empty($c['uf'])) $ufList[$c['uf']] = true;
}
$ufList = array_keys($ufList);
sort($ufList);

$statusCronOptions = [
    'sem_data' => 'Sem data',
    'no_prazo' => 'No prazo',
    'a_vencer' => 'A vencer (até 30 dias)',
    'vencido'  => 'Vencido'
];

// Lista de ações para filtro
$acoesList = [
    'notificar' => 'Notificar',
    'notificado' => 'Notificado',
    'isento' => 'Isento',
    'nao_notificar' => 'Não Notificar'
];

// ============================================================
// EXPORTAÇÃO EXCEL
// ============================================================
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="Relatório_Notificações_RDCI_' . date('d-m-Y') . '.xls"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta charset="UTF-8">';
    echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Relatório</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
    echo '<style>';
    echo 'th { background-color: #004a8f; color: #ffffff; font-weight: bold; padding: 5px; border: 1px solid #000; }';
    echo 'td { padding: 4px 6px; border: 1px solid #000; }';
    echo '.titulo { font-size: 14pt; font-weight: bold; }';
    echo '.texto { mso-number-format:\@; }';
    echo '</style></head><body>';
    echo '<div class="titulo">Relatório de Notificações - RDCI</div>';
    echo '<div>Gerado em: ' . date('d/m/Y H:i:s') . ' - Total de contratos: ' . count($contratos) . '</div>';
    echo '<table>';
    echo '<tr><th>Contrato</th><th>UF</th><th>BR</th><th>Nome Usual</th><th>Lote</th><th>Subtrecho</th>';
    echo '<th>Data Cronograma</th><th>Status Cronograma</th><th>Status Notificação</th><th>Ação</th><th>Isento</th></tr>';

    foreach ($contratos as $c) {
        $dataCron = $c['data_termino_projeto_cronog'] ? date('d/m/Y', strtotime($c['data_termino_projeto_cronog'])) : '-';
        $isento = $c['isento_notificacao'] ?? 0;
        $acao = $c['status_acao'] ?? 'nao_notificar';
        $labelAcao = $acoesList[$acao] ?? $acao;
        $cronStatus = calcularStatusCronograma($c['data_termino_projeto_cronog'] ?? null);
        $notifStatus = calcularStatusNotificacao($c);

        echo '<tr>';
        echo '<td class="texto">' . htmlspecialchars($c['instrumento'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($c['uf'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($c['br'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($c['nome_usual'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($c['lote'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($c['subtrecho'] ?? '') . '</td>';
        echo '<td>' . $dataCron . '</td>';
        echo '<td>' . $cronStatus['texto'] . '</td>';
        echo '<td>' . $notifStatus['status'] . '</td>';
        echo '<td>' . $labelAcao . '</td>';
        echo '<td>' . ($isento ? 'Isento' : 'Ativo') . '</td>';
        echo '</tr>';
    }

    echo '</table></body></html>';
    exit;
}

// ============================================================
// BADGES DE FILTROS APLICADOS
// ============================================================
$filtrosAplicados = [];
if (!empty($filtroUF)) {
    $filtrosAplicados[] = ['param' => 'uf', 'label' => 'UF', 'valor' => $filtroUF];
}
if (!empty($filtroStatusCron)) {
    $labelCron = $statusCronOptions[$filtroStatusCron] ?? $filtroStatusCron;
    $filtrosAplicados[] = ['param' => 'status_cronograma', 'label' => 'Status Cronograma', 'valor' => $labelCron];
}
if (!empty($filtroStatusNotif)) {
    $labelNotif = [
        'isento' => 'Isento',
        'nao_necessita' => 'Não necessita',
        'atencao' => 'Atenção (a vencer)',
        'vencido_sem_notificacao' => 'Vencido sem notificação',
        'vencido_notificacao_vencida' => 'Vencido com notif. vencida',
        'vencido_notificacao_em_dia' => 'Vencido com notif. em dia'
    ];
    $filtrosAplicados[] = ['param' => 'status_notificacao', 'label' => 'Status Notificação', 'valor' => $labelNotif[$filtroStatusNotif] ?? $filtroStatusNotif];
}
if (!empty($filtroNotificado)) {
    $filtrosAplicados[] = ['param' => 'notificado', 'label' => 'Notificado', 'valor' => ($filtroNotificado == 'sim' ? 'Notificados' : 'Pendentes')];
}
if (!empty($filtroIsento)) {
    $filtrosAplicados[] = ['param' => 'isento', 'label' => 'Isento', 'valor' => ($filtroIsento == 'sim' ? 'Isentos' : 'Não isentos')];
}
if (!empty($filtroContrato)) {
    $filtrosAplicados[] = ['param' => 'contrato', 'label' => 'Contrato', 'valor' => $filtroContrato];
}
if (!empty($filtroBusca)) {
    $filtrosAplicados[] = ['param' => 'busca', 'label' => 'Busca', 'valor' => $filtroBusca];
}
if (!empty($filtroAcao)) {
    $labelAcao = $acoesList[$filtroAcao] ?? $filtroAcao;
    $filtrosAplicados[] = ['param' => 'acao', 'label' => 'Ação', 'valor' => $labelAcao];
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Notificações RDCI - SISPRO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        /* ======= ESTILOS GERAIS ======= */
        body { background: #f8f9fc; padding-top: 70px; }
        .container-fluid { max-width: 98%; }
        .card { border: none; border-radius: 16px; box-shadow: 0 8px 30px rgba(0,0,0,0.08); background: #fff; padding: 20px 25px; }
        .table th { font-weight: 600; color: #2c3e50; white-space: nowrap; font-size: 0.85rem; }
        .table td { vertical-align: middle; font-size: 0.85rem; }
        .table .badge { font-size: 0.75rem; }
        .logo-dnit { max-height: 50px; }
        .sistema-titulo { font-weight: 700; color: #004a8f; font-size: 1.4rem; letter-spacing: 1px; }
        .btn-sm { padding: 0.2rem 0.5rem; font-size: 0.75rem; }
        .filtros .form-select, .filtros .form-control { font-size: 0.85rem; }
        .table-responsive { overflow-x: auto; }

        .cron-vencido { background: #dc3545; color: #fff; padding: 4px 10px; border-radius: 20px; font-weight: 500; white-space: nowrap; font-size: 0.75rem; }
        .cron-atencao { background: #ffc107; color: #000; padding: 4px 10px; border-radius: 20px; font-weight: 500; white-space: nowrap; font-size: 0.75rem; }
        .cron-prazo { background: #198754; color: #fff; padding: 4px 10px; border-radius: 20px; font-weight: 500; white-space: nowrap; font-size: 0.75rem; }
        .cron-semdata { background: #fd7e14; color: #fff; padding: 4px 10px; border-radius: 20px; font-weight: 500; white-space: nowrap; font-size: 0.75rem; }

        .notif-vencido { background: #dc3545; color: #fff; padding: 4px 10px; border-radius: 20px; font-weight: 500; white-space: nowrap; font-size: 0.75rem; }
        .notif-atencao { background: #ffc107; color: #000; padding: 4px 10px; border-radius: 20px; font-weight: 500; white-space: nowrap; font-size: 0.75rem; }
        .notif-tranquilo { background: #198754; color: #fff; padding: 4px 10px; border-radius: 20px; font-weight: 500; white-space: nowrap; font-size: 0.75rem; }
        .notif-isento { background: #0d6efd; color: #fff; padding: 4px 10px; border-radius: 20px; font-weight: 500; white-space: nowrap; font-size: 0.75rem; }
        .notif-faltadata { background: #fd7e14; color: #fff; padding: 4px 10px; border-radius: 20px; font-weight: 500; white-space: nowrap; font-size: 0.75rem; }
        .notif-semnecessidade { background: #6c757d; color: #fff; padding: 4px 10px; border-radius: 20px; font-weight: 500; white-space: nowrap; font-size: 0.75rem; }

        /* Badge de ação (Notificar, Notificado, Isento, Não Notificar) */
        .badge-acao {
            padding: 4px 10px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.75rem;
            white-space: nowrap;
        }
        .badge-acao.notificar { background: #0d6efd; color: #fff; }
        .badge-acao.notificado { background: #198754; color: #fff; }
        .badge-acao.isento { background: #6c757d; color: #fff; }
        .badge-acao.nao_notificar { background: #ffc107; color: #000; }

        /* Badge Isento na coluna "Isento" - CORRIGIDO */
        .isento-badge {
            background: #6c757d;
            color: #fff;
            padding: 4px 10px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.75rem;
            white-space: nowrap;
        }

        .col-checkbox { width: 40px; min-width: 40px; max-width: 40px; }
        .col-contrato { min-width: 120px; word-break: break-word; }
        .col-br { min-width: 70px; white-space: nowrap; }
        .col-vencimento { min-width: 120px; }
        .col-comentario { min-width: 60px; text-align: center; }

        .sei-link { cursor: pointer; color: #0d6efd; text-decoration: underline; font-size: 0.85rem; }
        .sei-link:hover { color: #0a58ca; }

        #toastCopiado {
            position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%);
            background: rgba(0,0,0,0.8); color: #fff; padding: 10px 24px;
            border-radius: 30px; font-weight: 500; font-size: 0.95rem;
            opacity: 0; visibility: hidden; transition: opacity 0.3s ease, visibility 0.3s ease;
            z-index: 9999; box-shadow: 0 4px 12px rgba(0,0,0,0.3); pointer-events: none;
        }
        #toastCopiado.show { opacity: 1; visibility: visible; }
        #toastCopiado i { margin-right: 8px; }

        .detalhe-item { padding: 4px 0; border-bottom: 1px solid #eee; font-size: 0.9rem; }
        .detalhe-item:last-child { border-bottom: none; }
        .modal-body { max-height: 70vh; overflow-y: auto; }
        .btn-acoes { padding: 0.2rem 0.4rem; font-size: 0.7rem; }
        .info-count { font-size: 0.9rem; color: #6c757d; margin-top: 0.5rem; }
        .info-count strong { color: #2c3e50; }
        .info-count .obs { color: #6c757d; font-style: italic; margin-left: 0.5rem; }

        .select2-container .select2-selection--single { height: 38px; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 38px; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px; }
        .select2-container .select2-selection--single .select2-selection__clear { display: none; }

        .badge-filtro {
            background: #e9ecef;
            color: #2c3e50;
            font-weight: 500;
            padding: 6px 12px;
            border-radius: 20px;
            margin-right: 8px;
            margin-bottom: 4px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .badge-filtro .remover-filtro { cursor: pointer; opacity: 0.7; }
        .badge-filtro .remover-filtro:hover { opacity: 1; }

        @media (max-width: 1199px) { .filtro-col { flex: 0 0 33.333%; max-width: 33.333%; } }
        @media (max-width: 767px) { .filtro-col { flex: 0 0 50%; max-width: 50%; } }
        @media (max-width: 575px) { .filtro-col { flex: 0 0 100%; max-width: 100%; } .btn-group-actions { flex-direction: column; align-items: stretch; gap: 5px; } .btn-group-actions .btn { width: 100%; } }
        .btn-group-actions { display: flex; flex-wrap: wrap; gap: 4px; justify-content: flex-end; align-items: flex-end; }
        .btn-group-actions .btn { white-space: nowrap; font-size: 0.75rem; padding: 0.2rem 0.5rem; height: 38px; display: flex; align-items: center; justify-content: center; gap: 4px; }
        .btn-group-actions .btn i { font-size: 0.85rem; }
        .export-row { margin-top: 8px; }
        .export-row .btn { font-size: 0.85rem; padding: 0.3rem 0.8rem; }

        .modal-confirm-export .modal-dialog { max-width: 420px; }
        .modal-confirm-export .modal-icon { font-size: 3rem; color: #28a745; text-align: center; margin-bottom: 10px; }
        .modal-confirm-export .modal-title-confirm { text-align: center; font-weight: 600; }
        .modal-confirm-export .modal-body { text-align: center; padding: 25px 30px; }

        /* ===== BARRA SUPERIOR FIXA ===== */
        .topbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1030;
            background: #ffffff;
            border-bottom: 1px solid #dce1e8;
            padding: 8px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            min-height: 60px;
        }
        .topbar-left { display: flex; align-items: center; gap: 10px; }
        .topbar-left .logo-dnit { max-height: 40px; width: auto; }
        .topbar-left .sistema-titulo { font-weight: 700; color: #004a8f; font-size: 1.2rem; letter-spacing: 1px; }
        .topbar-center { flex: 1; text-align: center; }
        .topbar-center .badge-setor { background: #17a2b8; color: #fff; font-size: 0.9rem; padding: 6px 14px; }
        .topbar-right { display: flex; align-items: center; gap: 10px; }
        .topbar-right .btn-icon { background: none; border: none; font-size: 1.5rem; color: #6c757d; padding: 0 6px; cursor: pointer; transition: color 0.2s; }
        .topbar-right .btn-icon:hover { color: #004a8f; }
        .topbar-right .btn-avatar { position: relative; background: none; border: none; padding: 0; cursor: pointer; }
        .topbar-right .btn-avatar .avatar-icon { width: 38px; height: 38px; border-radius: 50%; background: #0d6efd; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 1rem; text-transform: uppercase; }
        .topbar-right .btn-avatar .badge-notificacao { position: absolute; top: -4px; right: -4px; background-color: #dc3545; color: #fff; border-radius: 50%; padding: 2px 6px; font-size: 0.65rem; font-weight: 700; line-height: 1; border: 2px solid #fff; min-width: 18px; text-align: center; }
        .dropdown-menu-avatar { min-width: 220px; padding: 8px 0; }
        .dropdown-menu-avatar .dropdown-item { padding: 10px 20px; font-size: 0.95rem; display: flex; align-items: center; gap: 10px; }
        .dropdown-menu-avatar .dropdown-item i { font-size: 1.2rem; width: 24px; text-align: center; }
        .dropdown-menu-avatar .dropdown-header { padding: 10px 20px; font-weight: 600; color: #2c3e50; border-bottom: 1px solid #dce1e8; margin-bottom: 4px; }
        .dropdown-menu-avatar .dropdown-header small { display: block; font-weight: 400; font-size: 0.85rem; color: #6c757d; margin-top: 2px; }

        /* Comentários */
        .comentario-badge { position: relative; cursor: pointer; font-size: 1.3rem; }
        .comentario-badge .badge-dot { position: absolute; top: -6px; right: -6px; width: 12px; height: 12px; background-color: #ffc107; border-radius: 50%; border: 2px solid white; display: none; }
        .comentario-badge.has-comentario .badge-dot { display: block; }
        .comentario-bolha { background: #f1f3f5; border-radius: 12px; padding: 12px 16px; margin-bottom: 12px; border-left: 4px solid #0d6efd; position: relative; }
        .comentario-bolha .comentario-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; margin-bottom: 4px; }
        .comentario-bolha .comentario-usuario { font-weight: 700; color: #0d6efd; }
        .comentario-bolha .comentario-data { font-size: 0.8rem; color: #6c757d; }
        .comentario-bolha .comentario-texto { margin-top: 4px; word-wrap: break-word; white-space: pre-wrap; }
        .comentario-bolha .comentario-acoes { display: flex; gap: 4px; margin-left: 10px; }
        .comentario-bolha .comentario-acoes span { cursor: pointer; font-size: 0.9rem; }
        .comentario-bolha .comentario-acoes span:hover { opacity: 0.7; }
        .comentario-bolha .comentario-edit-area { width: 100%; }
        .comentario-bolha .comentario-actions { margin-top: 6px; display: flex; gap: 6px; }

        /* Scroll fixo */
        .table-wrapper { position: relative; max-height: 600px; overflow: auto; }
        .table-wrapper table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .table-wrapper table thead th { position: sticky; top: 0; z-index: 10; background: #fff; border-bottom: 2px solid #dee2e6; }
        .table-wrapper table thead th:first-child { position: sticky; left: 0; z-index: 11; background: #fff; }
        .table-wrapper table tbody td:first-child { position: sticky; left: 0; z-index: 5; background: #fff; }
        .table-wrapper table tbody tr.linha-marcada td:first-child { background: #d4edda; }
        .table-wrapper table tbody tr.linha-marcada { background-color: #d4edda !important; }
        .table-wrapper table tbody tr.linha-marcada td { background-color: #d4edda !important; }
        .table-wrapper table tbody td:first-child { min-width: 40px; max-width: 40px; }

        /* Botão de notificação com dropdown 4 opções */
        .btn-notificacao-dropdown .dropdown-toggle {
            padding: 0.2rem 0.4rem;
            font-size: 0.7rem;
        }
        .btn-notificacao-dropdown .dropdown-menu {
            min-width: 160px;
            padding: 0.2rem 0;
        }
        .btn-notificacao-dropdown .dropdown-item {
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
            cursor: pointer;
        }
        .btn-notificacao-dropdown .dropdown-item:hover {
            background: #e9ecef;
        }
        .btn-notificacao-dropdown .dropdown-item i {
            margin-right: 6px;
        }
    </style>
</head>
<?php include APP_PATH . '/public/chat_widget.php'; ?>
<body>
<?php include APP_PATH . '/Views/header.php'; ?>

<div class="container-fluid mt-4">
    <div class="card">
        <!-- Cabeçalho -->
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap">
            <div>
                <h2 class="sistema-titulo"><i class="bi bi-bell"></i> Notificações RDCI - Cronogramas</h2>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary btn-sm" id="btnAtualizar"><i class="bi bi-arrow-repeat"></i> Atualizar Dados</button>
                <a href="index.php" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left"></i> Voltar</a>
            </div>
        </div>

        <!-- Badges de filtros -->
        <?php if (!empty($filtrosAplicados)): ?>
        <div class="mb-3 p-2 bg-light rounded-3 d-flex flex-wrap align-items-center">
            <span class="fw-bold me-2"><i class="bi bi-funnel"></i> Filtros aplicados:</span>
            <?php foreach ($filtrosAplicados as $f): ?>
                <?php
                    $novosGET = $_GET;
                    unset($novosGET[$f['param']]);
                    unset($novosGET['export']);
                    $urlSemFiltro = '?' . http_build_query($novosGET);
                ?>
                <span class="badge-filtro">
                    <?= htmlspecialchars($f['label']) ?>: <?= htmlspecialchars($f['valor']) ?>
                    <a href="<?= $urlSemFiltro ?>" class="remover-filtro text-decoration-none" title="Remover filtro">
                        <i class="bi bi-x-circle"></i>
                    </a>
                </span>
            <?php endforeach; ?>
            <a href="notificacoes_rdci.php" class="btn btn-sm btn-outline-secondary ms-2">Limpar todos</a>
        </div>
        <?php endif; ?>

        <!-- Filtros -->
        <form method="GET" class="row g-2 mb-1 align-items-end" id="filtrosForm">
            <div class="col-lg-1 col-md-3 col-sm-6 col-12 filtro-col">
                <label class="form-label" style="font-size:0.75rem;">UF</label>
                <select name="uf" class="form-select filtro-select2">
                    <option value="">Selecione...</option>
                    <?php foreach ($ufList as $uf): ?>
                        <option value="<?= htmlspecialchars($uf) ?>" <?= ($_GET['uf'] ?? '') == $uf ? 'selected' : '' ?>><?= htmlspecialchars($uf) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-2 col-md-3 col-sm-6 col-12 filtro-col">
                <label class="form-label" style="font-size:0.75rem;">Status Cronograma</label>
                <select name="status_cronograma" class="form-select filtro-select2">
                    <option value="">Selecione...</option>
                    <?php foreach ($statusCronOptions as $value => $label): ?>
                        <option value="<?= $value ?>" <?= ($_GET['status_cronograma'] ?? '') == $value ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-2 col-md-3 col-sm-6 col-12 filtro-col">
                <label class="form-label" style="font-size:0.75rem;">Status Notificação</label>
                <select name="status_notificacao" class="form-select filtro-select2">
                    <option value="">Selecione...</option>
                    <option value="isento" <?= ($_GET['status_notificacao'] ?? '') == 'isento' ? 'selected' : '' ?>>Isento</option>
                    <option value="nao_necessita" <?= ($_GET['status_notificacao'] ?? '') == 'nao_necessita' ? 'selected' : '' ?>>Não necessita</option>
                    <option value="atencao" <?= ($_GET['status_notificacao'] ?? '') == 'atencao' ? 'selected' : '' ?>>Atenção (a vencer)</option>
                    <option value="vencido_sem_notificacao" <?= ($_GET['status_notificacao'] ?? '') == 'vencido_sem_notificacao' ? 'selected' : '' ?>>Vencido sem notificação</option>
                    <option value="vencido_notificacao_vencida" <?= ($_GET['status_notificacao'] ?? '') == 'vencido_notificacao_vencida' ? 'selected' : '' ?>>Vencido com notif. vencida</option>
                    <option value="vencido_notificacao_em_dia" <?= ($_GET['status_notificacao'] ?? '') == 'vencido_notificacao_em_dia' ? 'selected' : '' ?>>Vencido com notif. em dia</option>
                </select>
            </div>
            <div class="col-lg-1 col-md-2 col-sm-6 col-12 filtro-col">
                <label class="form-label" style="font-size:0.75rem;">Notificado</label>
                <select name="notificado" class="form-select filtro-select2">
                    <option value="">Selecione...</option>
                    <option value="sim" <?= ($_GET['notificado'] ?? '') == 'sim' ? 'selected' : '' ?>>Notificados</option>
                    <option value="nao" <?= ($_GET['notificado'] ?? '') == 'nao' ? 'selected' : '' ?>>Pendentes</option>
                </select>
            </div>
            <div class="col-lg-1 col-md-2 col-sm-6 col-12 filtro-col">
                <label class="form-label" style="font-size:0.75rem;">Isento</label>
                <select name="isento" class="form-select filtro-select2">
                    <option value="">Selecione...</option>
                    <option value="sim" <?= ($_GET['isento'] ?? '') == 'sim' ? 'selected' : '' ?>>Isentos</option>
                    <option value="nao" <?= ($_GET['isento'] ?? '') == 'nao' ? 'selected' : '' ?>>Não isentos</option>
                </select>
            </div>
            <div class="col-lg-1 col-md-2 col-sm-6 col-12 filtro-col">
                <label class="form-label" style="font-size:0.75rem;">Ação</label>
                <select name="acao" class="form-select filtro-select2">
                    <option value="">Selecione...</option>
                    <?php foreach ($acoesList as $value => $label): ?>
                        <option value="<?= $value ?>" <?= ($_GET['acao'] ?? '') == $value ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-2 col-md-3 col-sm-6 col-12 filtro-col">
                <label class="form-label" style="font-size:0.75rem;">Contrato</label>
                <select name="contrato" class="form-select filtro-select2">
                    <option value="">Selecione...</option>
                    <?php foreach ($contratosList as $inst): ?>
                        <option value="<?= htmlspecialchars($inst) ?>" <?= ($_GET['contrato'] ?? '') == $inst ? 'selected' : '' ?>><?= htmlspecialchars($inst) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-2 col-md-3 col-sm-6 col-12 filtro-col">
                <label class="form-label" style="font-size:0.75rem;">Buscar</label>
                <input type="text" name="busca" class="form-control" value="<?= htmlspecialchars($_GET['busca'] ?? '') ?>" placeholder="Obra, empresa...">
            </div>
            <div class="col-12 d-none">
                <input type="submit" class="btn btn-primary">
            </div>
        </form>

        <!-- Botão Gerar Relatório -->
        <div class="export-row d-flex justify-content-end">
            <button type="button" class="btn btn-success" id="btnExportar">
                <i class="bi bi-file-excel"></i> Gerar Relatório
            </button>
        </div>

        <!-- Modal de Confirmação Exportar -->
        <div class="modal fade modal-confirm-export" id="modalConfirmExport" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-body">
                        <div class="modal-icon"><i class="bi bi-file-earmark-spreadsheet"></i></div>
                        <h5 class="modal-title-confirm">Gerar Relatório</h5>
                        <p class="mt-2">Deseja realmente gerar o relatório de notificações com os filtros atuais?</p>
                        <div class="d-flex justify-content-center gap-3 mt-4">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-success" id="btnConfirmarExportar">Confirmar</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contagem -->
        <div class="info-count">
            <i class="bi bi-list-ul"></i> <strong>Contratos:</strong> <?= $totalContratos ?> &nbsp;|&nbsp; <strong>Linhas:</strong> <?= $totalLinhas ?>
            <span class="obs"><i class="bi bi-info-circle"></i> Contratos concluídos ou com projetos aprovados em sua totalidade estão ocultos.</span>
        </div>

        <!-- Tabela -->
        <div class="table-wrapper">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th class="col-checkbox text-center"><input type="checkbox" id="selecionarTodos" title="Selecionar todos"></th>
                        <th class="col-contrato">Contrato</th>
                        <th class="col-br">BR</th>
                        <th>UF</th>
                        <th>Lote</th>
                        <th>Nome Usual</th>
                        <th>Subtrecho</th>
                        <th>Data Cronog.</th>
                        <th class="col-vencimento">Status Cronograma</th>
                        <th>SEI Cronog.</th>
                        <th>Data Últ. Notif.</th>
                        <th class="col-vencimento">Status Notificação</th>
                        <th>Ofício Notif.</th>
                        <th>Ação</th>
                        <th>Isento</th>
                        <th class="col-comentario">Coment.</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($contratos)): ?>
                        <tr><td colspan="17" class="text-center text-muted py-4">Nenhum contrato encontrado.</td></tr>
                    <?php else: ?>
                        <?php foreach ($contratos as $c): 
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM comentarios WHERE contrato_id = ?");
                            $stmt->execute([$c['id']]);
                            $total_comentarios = (int)$stmt->fetchColumn();
                            $tem_comentario = $total_comentarios > 0;

                            $dataCron = $c['data_termino_projeto_cronog'] ? date('d/m/Y', strtotime($c['data_termino_projeto_cronog'])) : '-';
                            $notificado = $c['notificado'] ?? 0;
                            $isento = $c['isento_notificacao'] ?? 0;
                            $seiCron = $c['cronograma_sei'] ?? '';
                            $dataUltimaNotificacao = $c['data_ultima_notificacao'] ?? null;
                            $oficioNotificacao = $c['n_sei_oficio_cobranca_cronograma'] ?? '';

                            $cronStatus = calcularStatusCronograma($c['data_termino_projeto_cronog'] ?? null);
                            $notifStatus = calcularStatusNotificacao($c);

                            // Ação armazenada
                            $acao = $c['status_acao'] ?? 'nao_notificar';
                            $labelAcao = $acoesList[$acao] ?? $acao;
                            // Classe CSS para o badge de ação
                            $classAcao = 'badge-acao ' . $acao;

                            // Definir cor do botão dropdown com base no status da ação
                            switch ($acao) {
                                case 'notificar': $btnColor = 'primary'; $btnIcon = 'bi-pencil-square'; break;
                                case 'notificado': $btnColor = 'success'; $btnIcon = 'bi-check-circle'; break;
                                case 'isento': $btnColor = 'secondary'; $btnIcon = 'bi-slash-circle'; break;
                                default: $btnColor = 'warning'; $btnIcon = 'bi-clock'; break;
                            }
                        ?>
                        <tr data-contrato-id="<?= $c['id'] ?>" data-notificado="<?= $notificado ?>" data-isento="<?= $isento ?>" data-acao="<?= $acao ?>">
                            <td class="text-center">
                                <input type="checkbox" class="checkbox-selecionar" data-contrato-id="<?= $c['id'] ?>">
                            </td>
                            <td class="col-contrato"><strong><?= htmlspecialchars($c['instrumento'] ?? '') ?></strong></td>
                            <td class="col-br"><?= htmlspecialchars($c['br'] ?? '') ?></td>
                            <td><?= htmlspecialchars($c['uf'] ?? '') ?></td>
                            <td><?= htmlspecialchars($c['lote'] ?? '') ?></td>
                            <td><?= htmlspecialchars($c['nome_usual'] ?? '') ?></td>
                            <td><?= htmlspecialchars($c['subtrecho'] ?? '') ?></td>
                            <td class="text-nowrap"><?= $dataCron ?></td>
                            <td><span class="badge <?= $cronStatus['class'] ?>"><?= $cronStatus['texto'] ?></span></td>
                            <td>
                                <?php if (!empty($seiCron)): ?>
                                    <span class="sei-link" onclick="copiarTexto('<?= htmlspecialchars($seiCron) ?>')"><?= htmlspecialchars($seiCron) ?></span>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td><?= $dataUltimaNotificacao ? date('d/m/Y', strtotime($dataUltimaNotificacao)) : '-' ?></td>
                            <td><span class="badge <?= $notifStatus['class'] ?>"><?= $notifStatus['status'] ?></span></td>
                            <td>
                                <?php if (!empty($oficioNotificacao)): ?>
                                    <span class="sei-link" onclick="copiarTexto('<?= htmlspecialchars($oficioNotificacao) ?>')"><?= htmlspecialchars($oficioNotificacao) ?></span>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="<?= $classAcao ?>">
                                    <?= $labelAcao ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $isento ? 'isento-badge' : 'bg-secondary' ?>">
                                    <?= $isento ? 'Isento' : 'Ativo' ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="comentario-badge <?= $tem_comentario ? 'has-comentario' : '' ?>" 
                                      data-bs-toggle="modal" 
                                      data-bs-target="#modalComentario" 
                                      data-contrato-id="<?= $c['id'] ?>"
                                      data-contrato-num="<?= htmlspecialchars($c['instrumento'] ?? '') ?>"
                                      title="Comentários">
                                    <i class="bi bi-chat-dots"></i>
                                    <span class="badge-dot"></span>
                                </span>
                            </td>
                            <td class="text-nowrap">
                                <button class="btn btn-outline-info btn-sm btn-acoes" onclick="verDetalhes(<?= $c['id'] ?>)" title="Visualizar detalhes">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <!-- Dropdown com 4 opções -->
                                <div class="btn-group btn-notificacao-dropdown">
                                    <button type="button" class="btn btn-sm btn-outline-<?= $btnColor ?> dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="bi <?= $btnIcon ?>"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a class="dropdown-item" href="#" onclick="alterarStatusNotificacao(<?= $c['id'] ?>, 'notificar', this); return false;">
                                                <i class="bi bi-pencil-square text-primary"></i> Notificar
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="#" onclick="alterarStatusNotificacao(<?= $c['id'] ?>, 'notificado', this); return false;">
                                                <i class="bi bi-check-circle text-success"></i> Notificado
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="#" onclick="alterarStatusNotificacao(<?= $c['id'] ?>, 'isento', this); return false;">
                                                <i class="bi bi-slash-circle text-secondary"></i> Isento
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="#" onclick="alterarStatusNotificacao(<?= $c['id'] ?>, 'nao_notificar', this); return false;">
                                                <i class="bi bi-clock text-warning"></i> Não Notificar
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal de Comentários -->
<div class="modal fade modal-comentario" id="modalComentario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-chat-dots"></i> Histórico de Comentários - Contrato <span id="modal-contrato-num"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="lista-comentarios"><p class="text-muted text-center">Carregando...</p></div>
                <hr>
                <form id="form-comentario">
                    <input type="hidden" id="comentario-contrato-id">
                    <div class="mb-2">
                        <label class="form-label">Digite seu comentário</label>
                        <textarea class="form-control" id="novo-comentario" rows="2" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-send"></i> Enviar</button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('novo-comentario').value='';">Limpar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Toast -->
<div id="toastCopiado"><i class="bi bi-check-circle-fill text-success"></i> SEI copiado!</div>

<!-- Modal Detalhes -->
<div class="modal fade" id="modalDetalhes" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalhes do Cronograma</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detalhesConteudo">
                <p class="text-muted">Carregando...</p>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    // ============================================================
    // SELECT2
    // ============================================================
    $('.filtro-select2').select2({
        placeholder: 'Selecione...',
        allowClear: false,
        width: '100%'
    });
    $('.filtro-select2').on('change', function() {
        $(this).closest('form').submit();
    });
    $('.filtro-select2').on('select2:open', function(e) {
        setTimeout(function() {
            var searchField = document.querySelector('.select2-search__field');
            if (searchField) searchField.focus();
        }, 100);
    });
    $('.filtro-select2').on('focus', function() {
        $(this).select2('open');
    });

    // ============================================================
    // EXPORTAR
    // ============================================================
    $('#btnExportar').on('click', function(e) {
        e.preventDefault();
        var modalConfirm = new bootstrap.Modal(document.getElementById('modalConfirmExport'));
        modalConfirm.show();
    });
    $('#btnConfirmarExportar').on('click', function() {
        var form = $('#filtrosForm');
        form.find('input[name="export"]').remove();
        $('<input>').attr({ type: 'hidden', name: 'export', value: 'excel' }).appendTo(form);
        var modal = bootstrap.Modal.getInstance(document.getElementById('modalConfirmExport'));
        modal.hide();
        form.submit();
    });

    // ============================================================
    // ATUALIZAR DADOS
    // ============================================================
    $('#btnAtualizar').on('click', function() {
        if (!confirm('Atualizar dados do RDCI?')) return;
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Atualizando...';
        $.ajax({
            url: 'importar_rdci_api.php',
            method: 'GET',
            success: function(data) {
                alert('Dados atualizados!');
                location.reload();
            },
            error: function() {
                alert('Erro ao atualizar.');
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Atualizar Dados';
            }
        });
    });

    // ============================================================
    // TOAST E COPIA
    // ============================================================
    var toastTimer = null;
    function mostrarToast(mensagem) {
        var toast = document.getElementById('toastCopiado');
        if (!toast) return;
        toast.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i> ' + mensagem;
        toast.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function() {
            toast.classList.remove('show');
        }, 2000);
    }
    window.copiarTexto = function(texto) {
        if (!texto || texto === '—') return;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(texto)
                .then(function() { mostrarToast('Copiado: ' + texto); })
                .catch(function() { fallbackCopiar(texto); });
        } else {
            fallbackCopiar(texto);
        }
    };
    function fallbackCopiar(texto) {
        var textarea = document.createElement('textarea');
        textarea.value = texto;
        textarea.style.position = 'fixed';
        textarea.style.top = '-9999px';
        textarea.style.left = '-9999px';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        try {
            var sucesso = document.execCommand('copy');
            if (sucesso) { mostrarToast('Copiado: ' + texto); }
            else { mostrarToast('Erro ao copiar'); }
        } catch (e) { mostrarToast('Erro ao copiar'); }
        document.body.removeChild(textarea);
    }

    // ============================================================
    // CHECKBOXES
    // ============================================================
    var STORAGE_KEY = 'rdci_contratos_marcados';
    function salvarMarcados() {
        var ids = [];
        $('.checkbox-selecionar:checked').each(function() {
            ids.push($(this).data('contrato-id'));
        });
        localStorage.setItem(STORAGE_KEY, JSON.stringify(ids));
    }
    function restaurarMarcados() {
        var dados = localStorage.getItem(STORAGE_KEY);
        if (!dados) return;
        try {
            var ids = JSON.parse(dados);
            $('.checkbox-selecionar').each(function() {
                var id = $(this).data('contrato-id');
                if (ids.indexOf(id) !== -1) {
                    $(this).prop('checked', true).trigger('change');
                }
            });
        } catch (e) {}
    }
    $(document).on('change', '.checkbox-selecionar', function() {
        var $row = $(this).closest('tr');
        if (this.checked) $row.addClass('linha-marcada');
        else $row.removeClass('linha-marcada');
        salvarMarcados();
    });
    $('#selecionarTodos').on('change', function() {
        var isChecked = this.checked;
        $('.checkbox-selecionar').prop('checked', isChecked).trigger('change');
    });
    restaurarMarcados();

    // ============================================================
    // ALTERAR STATUS DE NOTIFICAÇÃO (4 AÇÕES)
    // ============================================================
    window.alterarStatusNotificacao = function(id, acao, element) {
        var notificado = 0;
        var isento = 0;
        var labelAcao = '';
        var classAcao = '';
        var icon = '';
        var btnClass = '';

        switch(acao) {
            case 'notificar':
                notificado = 0;
                isento = 0;
                labelAcao = 'Notificar';
                classAcao = 'notificar';
                icon = 'bi-pencil-square';
                btnClass = 'btn-outline-primary';
                break;
            case 'notificado':
                notificado = 1;
                isento = 0;
                labelAcao = 'Notificado';
                classAcao = 'notificado';
                icon = 'bi-check-circle';
                btnClass = 'btn-outline-success';
                break;
            case 'isento':
                notificado = 0;
                isento = 1;
                labelAcao = 'Isento';
                classAcao = 'isento';
                icon = 'bi-slash-circle';
                btnClass = 'btn-outline-secondary';
                break;
            case 'nao_notificar':
                notificado = 0;
                isento = 0;
                labelAcao = 'Não Notificar';
                classAcao = 'nao_notificar';
                icon = 'bi-clock';
                btnClass = 'btn-outline-warning';
                break;
            default:
                return;
        }

        $.ajax({
            url: 'ajax_rdci.php',
            method: 'POST',
            data: { 
                action: 'alterar_status_notificacao', 
                id: id, 
                notificado: notificado, 
                isento: isento,
                status_acao: acao  // Novo campo
            },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    var $row = $('tr[data-contrato-id="' + id + '"]');
                    // Atualiza a coluna "Ação" (td:eq(13))
                    var $tdAcao = $row.find('td:eq(13)');
                    $tdAcao.html('<span class="badge-acao ' + classAcao + '">' + labelAcao + '</span>');
                    // Atualiza a coluna "Isento" (td:eq(14))
                    var $tdIsento = $row.find('td:eq(14) .badge');
                    if (isento) {
                        $tdIsento.removeClass('bg-secondary').addClass('isento-badge').text('Isento');
                    } else {
                        $tdIsento.removeClass('isento-badge').addClass('bg-secondary').text('Ativo');
                    }
                    // Atualiza o botão dropdown
                    var $btn = $row.find('.btn-notificacao-dropdown .dropdown-toggle');
                    $btn.removeClass('btn-outline-primary btn-outline-success btn-outline-secondary btn-outline-warning').addClass(btnClass);
                    $btn.html('<i class="bi ' + icon + '"></i>');
                    // Atualiza data attributes
                    $row.attr('data-notificado', notificado);
                    $row.attr('data-isento', isento);
                    $row.attr('data-acao', acao);
                } else {
                    alert('Erro: ' + (res.error || 'Desconhecido'));
                }
            },
            error: function() {
                alert('Erro de comunicação.');
            }
        });
    };

    // ============================================================
    // COMENTÁRIOS
    // ============================================================
    $('#modalComentario').on('show.bs.modal', function(event) {
        var trigger = $(event.relatedTarget);
        var contratoId = trigger.data('contrato-id');
        var contratoNum = trigger.data('contrato-num');
        $('#modal-contrato-num').text(contratoNum);
        $('#comentario-contrato-id').val(contratoId);
        carregarComentarios(contratoId);
    });

    function carregarComentarios(contratoId) {
        $('#lista-comentarios').html('<p class="text-muted text-center">Carregando...</p>');
        $.ajax({
            url: 'ajax_comentarios.php',
            method: 'GET',
            data: { action: 'get', contrato_id: contratoId },
            dataType: 'json',
            success: function(data) {
                var html = '';
                if (!data || data.length === 0) {
                    html = '<p class="text-muted text-center">Nenhum comentário ainda.</p>';
                } else {
                    for (var i = 0; i < data.length; i++) {
                        var c = data[i];
                        var texto = c.comentario || '';
                        var podeEditar = (<?= json_encode($usuario_nivel) ?> === 'desenvolvedor' || <?= json_encode($usuario_nivel) ?> === 'admin' || c.usuario_id == <?= json_encode($usuario_id) ?>);
                        var podeExcluir = (<?= json_encode($usuario_nivel) ?> === 'desenvolvedor' || <?= json_encode($usuario_nivel) ?> === 'admin');
                        var btnEditar = podeEditar ? ' <span class="btn-editar-comentario text-primary" data-id="' + c.id + '" title="Editar"><i class="bi bi-pencil"></i></span>' : '';
                        var btnExcluir = podeExcluir ? ' <span class="btn-excluir-comentario text-danger" data-id="' + c.id + '" title="Excluir"><i class="bi bi-trash"></i></span>' : '';
                        html += '<div class="comentario-bolha" data-id="' + c.id + '">' +
                                    '<div class="comentario-header">' +
                                        '<span class="comentario-usuario">' + escaparHtml(c.usuario_nome) + '</span>' +
                                        '<div class="comentario-acoes">' +
                                            '<span class="comentario-data">' + c.created_at + '</span>' +
                                            btnEditar + btnExcluir +
                                        '</div>' +
                                    '</div>' +
                                    '<div class="comentario-texto">' + escaparHtml(texto) + '</div>' +
                                '</div>';
                    }
                }
                $('#lista-comentarios').html(html);

                $('.btn-editar-comentario').off('click').on('click', function() {
                    var id = $(this).data('id');
                    var bolha = $(this).closest('.comentario-bolha');
                    var textoAtual = bolha.find('.comentario-texto').text().trim();
                    var areaEdit = $('<textarea class="form-control comentario-edit-area" rows="2">' + textoAtual + '</textarea>');
                    var botoes = $('<div class="comentario-actions">' +
                                    '<button class="btn btn-success btn-sm salvar-edicao">Salvar</button> ' +
                                    '<button class="btn btn-secondary btn-sm cancelar-edicao">Cancelar</button>' +
                                '</div>');
                    bolha.find('.comentario-texto').replaceWith(areaEdit);
                    bolha.find('.comentario-acoes').after(botoes);
                    bolha.find('.btn-editar-comentario, .btn-excluir-comentario').hide();
                    botoes.find('.salvar-edicao').on('click', function() {
                        var novoTexto = areaEdit.val().trim();
                        if (!novoTexto) { alert('O comentário não pode ficar vazio.'); return; }
                        $.ajax({
                            url: 'ajax_comentarios.php',
                            method: 'POST',
                            data: { action: 'editar', id: id, comentario: novoTexto },
                            dataType: 'json',
                            success: function(res) {
                                if (res.success) {
                                    var contratoId = $('#comentario-contrato-id').val();
                                    carregarComentarios(contratoId);
                                } else {
                                    alert('Erro ao editar: ' + (res.error || 'Desconhecido'));
                                }
                            },
                            error: function() { alert('Erro de comunicação.'); }
                        });
                    });
                    botoes.find('.cancelar-edicao').on('click', function() {
                        var contratoId = $('#comentario-contrato-id').val();
                        carregarComentarios(contratoId);
                    });
                });

                $('.btn-excluir-comentario').off('click').on('click', function() {
                    var id = $(this).data('id');
                    if (confirm('Excluir este comentário?')) {
                        $.ajax({
                            url: 'ajax_comentarios.php',
                            method: 'POST',
                            data: { action: 'excluir', id: id },
                            dataType: 'json',
                            success: function(res) {
                                if (res.success) {
                                    var contratoId = $('#comentario-contrato-id').val();
                                    carregarComentarios(contratoId);
                                    atualizarBadge(contratoId);
                                } else {
                                    alert('Erro: ' + (res.error || 'Desconhecido'));
                                }
                            },
                            error: function() { alert('Erro de comunicação.'); }
                        });
                    }
                });
            },
            error: function() {
                $('#lista-comentarios').html('<p class="text-danger text-center">Erro ao carregar comentários.</p>');
            }
        });
    }

    function atualizarBadge(contratoId) {
        $.ajax({
            url: 'ajax_comentarios.php',
            method: 'GET',
            data: { action: 'get', contrato_id: contratoId, count_only: 1 },
            dataType: 'json',
            success: function(data) {
                var total = data.total || 0;
                var badge = $('.comentario-badge[data-contrato-id="' + contratoId + '"]');
                if (total > 0) badge.addClass('has-comentario');
                else badge.removeClass('has-comentario');
            }
        });
    }

    $('#form-comentario').on('submit', function(e) {
        e.preventDefault();
        var contratoId = $('#comentario-contrato-id').val();
        var comentario = $('#novo-comentario').val().trim();
        if (!comentario) return;

        $.ajax({
            url: 'ajax_comentarios.php',
            method: 'POST',
            data: { action: 'adicionar', contrato_id: contratoId, comentario: comentario },
            dataType: 'json',
            timeout: 30000,
            success: function(res) {
                if (res.success) {
                    $('#novo-comentario').val('');
                    carregarComentarios(contratoId);
                    var badge = $('.comentario-badge[data-contrato-id="' + contratoId + '"]');
                    badge.addClass('has-comentario');
                } else {
                    alert('Erro: ' + (res.error || 'Desconhecido'));
                }
            },
            error: function(xhr, status, error) {
                console.error('Erro AJAX:', status, error);
                alert('Erro de comunicação ao salvar comentário.');
            }
        });
    });

    function escaparHtml(texto) {
        return $('<div>').text(texto).html();
    }

    // ============================================================
    // VISUALIZAR DETALHES
    // ============================================================
    window.verDetalhes = function(id) {
        $.ajax({
            url: 'ajax_rdci.php',
            method: 'GET',
            data: { action: 'detalhes', id: id },
            dataType: 'json',
            success: function(data) {
                if (data.error) {
                    document.getElementById('detalhesConteudo').innerHTML = '<p class="text-danger">' + data.error + '</p>';
                    return;
                }
                const campos = [
                    { label: 'Contrato', key: 'instrumento' },
                    { label: 'BR', key: 'br' },
                    { label: 'UF', key: 'uf' },
                    { label: 'Lote', key: 'lote' },
                    { label: 'Nome Usual', key: 'nome_usual' },
                    { label: 'Subtrecho', key: 'subtrecho' },
                    { label: 'Situação do Cronograma', key: 'situacao_cronograma' },
                    { label: 'Data do Cronograma', key: 'data_termino_projeto_cronog' },
                    { label: 'Cronograma SEI', key: 'cronograma_sei' },
                    { label: 'Justificativa do Cronograma', key: 'justificativa_cronograma' },
                    { label: 'Ofício de Notificação', key: 'n_sei_oficio_cobranca_cronograma' },
                    { label: 'Data da Última Notificação', key: 'data_ultima_notificacao' },
                    { label: 'Isento de Notificação', key: 'isento_notificacao' },
                    { label: 'Status Ação', key: 'status_acao' },
                    { label: 'Processo de Notificação SR/UF', key: 'processo_notificacao_sr_uf' },
                    { label: 'PAAR', key: 'paar' },
                    { label: 'Processo Base', key: 'processo_base' },
                    { label: 'Processo de Projetos', key: 'processo_projeto' }
                ];

                function formatarData(valor) {
                    if (!valor || valor === '—') return '—';
                    if (/^\d{2}\/\d{2}\/\d{4}$/.test(valor)) return valor;
                    if (/^\d{4}-\d{2}-\d{2}$/.test(valor)) {
                        var partes = valor.split('-');
                        return partes[2] + '/' + partes[1] + '/' + partes[0];
                    }
                    return valor;
                }

                function formatarAcao(valor) {
                    var mapa = {
                        'notificar': 'Notificar',
                        'notificado': 'Notificado',
                        'isento': 'Isento',
                        'nao_notificar': 'Não Notificar'
                    };
                    return mapa[valor] || valor;
                }

                let html = '<div class="row">';
                const metade = Math.ceil(campos.length / 2);
                const col1 = campos.slice(0, metade);
                const col2 = campos.slice(metade);

                html += '<div class="col-md-6">';
                col1.forEach(campo => {
                    let valor = data[campo.key] !== undefined && data[campo.key] !== null ? data[campo.key] : '—';
                    if (campo.key === 'data_termino_projeto_cronog' || campo.key === 'data_ultima_notificacao') {
                        valor = formatarData(valor);
                    }
                    if (campo.key === 'isento_notificacao') {
                        valor = valor ? 'Sim' : 'Não';
                    }
                    if (campo.key === 'status_acao') {
                        valor = formatarAcao(valor);
                    }
                    html += `<div class="detalhe-item"><strong>${campo.label}:</strong> ${valor}</div>`;
                });
                html += '</div>';

                html += '<div class="col-md-6">';
                col2.forEach(campo => {
                    let valor = data[campo.key] !== undefined && data[campo.key] !== null ? data[campo.key] : '—';
                    if (campo.key === 'data_termino_projeto_cronog' || campo.key === 'data_ultima_notificacao') {
                        valor = formatarData(valor);
                    }
                    if (campo.key === 'isento_notificacao') {
                        valor = valor ? 'Sim' : 'Não';
                    }
                    if (campo.key === 'status_acao') {
                        valor = formatarAcao(valor);
                    }
                    html += `<div class="detalhe-item"><strong>${campo.label}:</strong> ${valor}</div>`;
                });
                html += '</div>';
                html += '</div>';

                if (data.objeto_contrato) {
                    html += '<hr><h6>Objeto do Contrato</h6><p class="text-muted small">' + (data.objeto_contrato || '-') + '</p>';
                }
                if (data.analise) {
                    html += '<hr><h6>Análise</h6><p class="text-muted small">' + (data.analise || '-') + '</p>';
                }
                if (data.observacoes) {
                    html += '<hr><h6>Observações</h6><p class="text-muted small">' + (data.observacoes || '-') + '</p>';
                }

                document.getElementById('detalhesConteudo').innerHTML = html;
                new bootstrap.Modal(document.getElementById('modalDetalhes')).show();
            },
            error: function() {
                document.getElementById('detalhesConteudo').innerHTML = '<p class="text-danger">Erro ao carregar detalhes.</p>';
            }
        });
    };
});
</script>
</body>
</html>