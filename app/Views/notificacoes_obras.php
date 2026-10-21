<?php
// View notificacoes_obras.php

if (!function_exists('obterIniciais')) {
    function obterIniciais($nome) {
        $partes = explode(' ', trim($nome));
        $ini = ''; foreach ($partes as $p) { if ($p !== '') $ini .= strtoupper(substr($p, 0, 1)); }
        return substr($ini, 0, 2);
    }
}

$usuario_nome  = $_SESSION['usuario_nome']  ?? 'Usuário';
$usuario_nivel = $_SESSION['usuario_nivel'] ?? 'usuario';
$usuario_id    = $_SESSION['usuario_id']    ?? 0;

// Limiar configurado no controller
$limiteAlerta = (int)NotificacoesObrasController::PERCENTUAL_ALERTA;

$ultimaAtualizacao = '—';
$arquivoSync = APP_PATH . '/public/.sync_ultima_execucao';
if (file_exists($arquivoSync)) {
    $ultimaAtualizacao = date('d/m/Y H:i', filemtime($arquivoSync));
} else {
    $arquivoLog = APP_PATH . '/public/importacao_cron.log';
    if (file_exists($arquivoLog)) $ultimaAtualizacao = date('d/m/Y H:i', filemtime($arquivoLog));
}

$filtroUF       = $_GET['uf']                 ?? '';
$filtroTipo     = $_GET['tipo_contrato']      ?? '';
$filtroCron     = $_GET['status_cronograma']  ?? '';
$filtroNotif    = $_GET['status_notificacao'] ?? '';
$filtroAcao     = $_GET['acao']               ?? '';
$filtroContrato = $_GET['contrato']           ?? '';
$filtroBusca    = $_GET['busca']              ?? '';

$statusCronOptions = ['vencido' => 'Vencido', 'a_vencer' => 'A vencer (até 30 dias)', 'sem_data' => 'Sem data', 'sem_cronograma' => 'Sem cronograma', 'no_prazo' => 'No prazo'];
$statusNotifOptions = ['isento' => 'Isento', 'sem_cronograma' => 'Sem cronograma', 'sem_data' => 'Sem data', 'nao_necessita' => 'Não necessita', 'notificar_hoje_ou_a_vencer' => 'Notificar hoje / em N dias', 'falta_data_notificacao' => 'Falta data de notificação', 'notificacao_vencida' => 'Notificação vencida', 'aguardando_prazo' => 'Aguardando prazo'];
$acoesList = ['notificar' => 'Notificar', 'notificado' => 'Notificado', 'aguardando_assinatura' => 'Aguardando assinatura', 'isento' => 'Isento', 'nao_notificar' => 'Não Notificar'];

$classCron = ['vencido' => 'cron-vencido', 'a_vencer' => 'cron-atencao', 'sem_data' => 'cron-semdata', 'sem_cronograma' => 'cron-semdata', 'no_prazo' => 'cron-prazo'];
$classNotif = ['isento' => 'notif-isento', 'sem_cronograma' => 'notif-semdata', 'sem_data' => 'notif-faltadata', 'nao_necessita' => 'notif-semnecessidade', 'notificar_hoje_ou_a_vencer' => 'notif-atencao', 'falta_data_notificacao' => 'notif-faltadata', 'notificacao_vencida' => 'notif-vencido', 'aguardando_prazo' => 'notif-tranquilo'];

$filtrosAplicados = [];
if ($filtroUF)       $filtrosAplicados[] = ['param'=>'uf',                 'label'=>'UF',                  'valor'=>$filtroUF];
if ($filtroTipo)     $filtrosAplicados[] = ['param'=>'tipo_contrato',      'label'=>'Tipo',                'valor'=>$filtroTipo];
if ($filtroCron)     $filtrosAplicados[] = ['param'=>'status_cronograma',  'label'=>'Status Cronograma',   'valor'=>$statusCronOptions[$filtroCron] ?? $filtroCron];
if ($filtroNotif)    $filtrosAplicados[] = ['param'=>'status_notificacao', 'label'=>'Status Notificação',  'valor'=>$statusNotifOptions[$filtroNotif] ?? $filtroNotif];
if ($filtroAcao)     $filtrosAplicados[] = ['param'=>'acao',               'label'=>'Ação',                'valor'=>$acoesList[$filtroAcao] ?? $filtroAcao];
if ($filtroContrato) $filtrosAplicados[] = ['param'=>'contrato',           'label'=>'Contrato',            'valor'=>$filtroContrato];
if ($filtroBusca)    $filtrosAplicados[] = ['param'=>'busca',              'label'=>'Busca',               'valor'=>$filtroBusca];
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Notificações de Obras - SISPRO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        body { background: #f8f9fc; padding-top: 70px; }
        .container-fluid { max-width: 98%; }
        .card { border: none; border-radius: 16px; box-shadow: 0 8px 30px rgba(0,0,0,0.08); background: #fff; padding: 20px 25px; }
        .table th { font-weight: 600; color: #2c3e50; white-space: nowrap; font-size: 0.85rem; }
        .table td { vertical-align: middle; font-size: 0.85rem; }
        .table .badge { font-size: 0.75rem; }
        .sistema-titulo { font-weight: 700; color: #004a8f; font-size: 1.4rem; letter-spacing: 1px; }
        .btn-sm { padding: 0.2rem 0.5rem; font-size: 0.75rem; }
        .table-responsive { overflow-x: auto; }

        .ultima-atualizacao { font-size: 0.78rem; color: #6c757d; background: #f1f5f9; border: 1px solid #e2e8f0; padding: 4px 12px; border-radius: 20px; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; }
        .ultima-atualizacao strong { color: #0d6efd; }

        .cron-vencido { background:#dc3545; color:#fff; padding:4px 10px; border-radius:20px; font-weight:500; white-space:nowrap; font-size:0.75rem; }
        .cron-atencao { background:#ffc107; color:#000; padding:4px 10px; border-radius:20px; font-weight:500; white-space:nowrap; font-size:0.75rem; }
        .cron-prazo   { background:#198754; color:#fff; padding:4px 10px; border-radius:20px; font-weight:500; white-space:nowrap; font-size:0.75rem; }
        .cron-semdata { background:#fd7e14; color:#fff; padding:4px 10px; border-radius:20px; font-weight:500; white-space:nowrap; font-size:0.75rem; }

        .notif-vencido        { background:#dc3545; color:#fff; padding:4px 10px; border-radius:20px; font-weight:500; white-space:nowrap; font-size:0.75rem; }
        .notif-atencao        { background:#ffc107; color:#000; padding:4px 10px; border-radius:20px; font-weight:500; white-space:nowrap; font-size:0.75rem; }
        .notif-tranquilo      { background:#198754; color:#fff; padding:4px 10px; border-radius:20px; font-weight:500; white-space:nowrap; font-size:0.75rem; }
        .notif-isento         { background:#0d6efd; color:#fff; padding:4px 10px; border-radius:20px; font-weight:500; white-space:nowrap; font-size:0.75rem; }
        .notif-faltadata      { background:#fd7e14; color:#fff; padding:4px 10px; border-radius:20px; font-weight:500; white-space:nowrap; font-size:0.75rem; }
        .notif-semdata        { background:#fd7e14; color:#fff; padding:4px 10px; border-radius:20px; font-weight:500; white-space:nowrap; font-size:0.75rem; }
        .notif-semnecessidade { background:#6c757d; color:#fff; padding:4px 10px; border-radius:20px; font-weight:500; white-space:nowrap; font-size:0.75rem; }

        .badge-tipo { background:#212529; color:#fff; padding:4px 10px; border-radius:20px; font-weight:500; font-size:0.72rem; white-space:nowrap; }

        .badge-acao { padding:4px 10px; border-radius:20px; font-weight:500; font-size:0.75rem; white-space:nowrap; }
        .badge-acao.notificar             { background:#0d6efd; color:#fff; }
        .badge-acao.notificado            { background:#198754; color:#fff; }
        .badge-acao.aguardando_assinatura { background:#6f42c1; color:#fff; }
        .badge-acao.isento                { background:#6c757d; color:#fff; }
        .badge-acao.nao_notificar         { background:#ffc107; color:#000; }

        .btn-outline-purple { color:#6f42c1; border-color:#6f42c1; }
        .btn-outline-purple:hover, .btn-outline-purple:focus { background:#6f42c1; color:#fff; }

        .isento-badge { background:#6c757d; color:#fff; padding:4px 10px; border-radius:20px; font-weight:500; font-size:0.75rem; white-space:nowrap; }

        .col-checkbox { width:40px; min-width:40px; max-width:40px; }
        .col-contrato { min-width:150px; word-break:break-word; }
        .col-tipo     { min-width:150px; }
        .col-br       { min-width:70px; white-space:nowrap; }
        .col-vencimento { min-width:120px; }
        .col-comentario { min-width:60px; text-align:center; }

        .sei-link { cursor:pointer; color:#0d6efd; text-decoration:underline; font-size:0.85rem; }
        .sei-link:hover { color:#0a58ca; }

        #toastCopiado { position: fixed; bottom:30px; left:50%; transform:translateX(-50%); background: rgba(0,0,0,0.85); color:#fff; padding:10px 24px; border-radius:30px; font-weight:500; font-size:0.95rem; opacity:0; visibility:hidden; transition: opacity .3s ease, visibility .3s ease; z-index:9999; box-shadow:0 4px 12px rgba(0,0,0,0.3); pointer-events:none; }
        #toastCopiado.show { opacity:1; visibility:visible; }
        #toastCopiado i { margin-right:8px; }

        .btn-acoes { padding:0.2rem 0.4rem; font-size:0.7rem; }
        .info-count { font-size:0.9rem; color:#6c757d; margin-top:0.5rem; }
        .info-count strong { color:#2c3e50; }
        .info-count .obs { color:#6c757d; font-style:italic; margin-left:0.5rem; }

        .select2-container .select2-selection--single { height:38px; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height:38px; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height:36px; }
        .select2-container .select2-selection--single .select2-selection__clear { display:none; }

        .badge-filtro { background:#e9ecef; color:#2c3e50; font-weight:500; padding:6px 12px; border-radius:20px; margin-right:8px; margin-bottom:4px; display:inline-flex; align-items:center; gap:6px; }
        .badge-filtro .remover-filtro { cursor:pointer; opacity:0.7; }
        .badge-filtro .remover-filtro:hover { opacity:1; }

        @media (max-width: 1199px) { .filtro-col { flex:0 0 33.333%; max-width:33.333%; } }
        @media (max-width: 767px)  { .filtro-col { flex:0 0 50%;     max-width:50%; } }
        @media (max-width: 575px)  { .filtro-col { flex:0 0 100%; max-width:100%; } }

        .table-wrapper { position:relative; max-height:600px; overflow:auto; }
        .table-wrapper table { width:100%; border-collapse:separate; border-spacing:0; }
        .table-wrapper table thead th { position:sticky; top:0; z-index:10; background:#fff; border-bottom:2px solid #dee2e6; }
        .table-wrapper table thead th:first-child { position:sticky; left:0; z-index:11; background:#fff; }
        .table-wrapper table tbody td:first-child { position:sticky; left:0; z-index:5; background:#fff; }
        .table-wrapper table tbody tr.linha-marcada td:first-child { background:#d4edda; }
        .table-wrapper table tbody tr.linha-marcada { background-color:#d4edda !important; }
        .table-wrapper table tbody tr.linha-marcada td { background-color:#d4edda !important; }
        .table-wrapper table tbody td:first-child { min-width:40px; max-width:40px; }

        .btn-notificacao-dropdown .dropdown-toggle { padding:0.2rem 0.4rem; font-size:0.7rem; }
        .btn-notificacao-dropdown .dropdown-menu { min-width:200px; padding:0.2rem 0; }
        .btn-notificacao-dropdown .dropdown-item { padding:0.25rem 0.75rem; font-size:0.75rem; cursor:pointer; }
        .btn-notificacao-dropdown .dropdown-item:hover { background:#e9ecef; }
        .btn-notificacao-dropdown .dropdown-item i { margin-right:6px; }

        .comentario-badge { position:relative; cursor:pointer; font-size:1.3rem; }
        .comentario-badge .badge-dot { position:absolute; top:-6px; right:-6px; width:12px; height:12px; background:#ffc107; border-radius:50%; border:2px solid white; display:none; }
        .comentario-badge.has-comentario .badge-dot { display:block; }
        .comentario-bolha { background:#f1f3f5; border-radius:12px; padding:12px 16px; margin-bottom:12px; border-left:4px solid #0d6efd; position:relative; }
        .comentario-bolha .comentario-header { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; margin-bottom:4px; }
        .comentario-bolha .comentario-usuario { font-weight:700; color:#0d6efd; }
        .comentario-bolha .comentario-data { font-size:0.8rem; color:#6c757d; }
        .comentario-bolha .comentario-texto { margin-top:4px; word-wrap:break-word; white-space:pre-wrap; }
        .comentario-bolha .comentario-acoes { display:flex; gap:4px; margin-left:10px; }
        .comentario-bolha .comentario-acoes span { cursor:pointer; font-size:0.9rem; }
        .comentario-bolha .comentario-acoes span:hover { opacity:0.7; }
        .comentario-bolha .comentario-edit-area { width:100%; }
        .comentario-bolha .comentario-actions { margin-top:6px; display:flex; gap:6px; }

        /* ============================================================
           ANÁLISE DE PERFORMANCE (dentro do modal de detalhes)
           ============================================================ */
        .analise-wrap { margin-top:20px; }
        .analise-card { border-radius:14px; padding:18px 22px; border-left:6px solid #6c757d; background:#f8f9fc; }
        .analise-card.ok     { border-left-color:#198754; background:#f0fbf4; }
        .analise-card.alerta { border-left-color:#dc3545; background:#fdf1f2; }
        .analise-card.meio   { border-left-color:#ffc107; background:#fffaf0; }
        .analise-card.sem    { border-left-color:#6c757d; background:#f5f6f8; }

        .analise-card h6 { font-weight:700; font-size:.95rem; color:#0b1a4a; margin-bottom:14px; }
        .analise-kpi { display:flex; flex-direction:column; gap:2px; }
        .analise-kpi small { font-size:.72rem; color:#7a859b; text-transform:uppercase; letter-spacing:.04em; white-space:nowrap; }
        .analise-kpi strong {
            font-size:1.15rem; font-weight:700; color:#1c2430;
            white-space:nowrap;
            overflow:hidden;
            text-overflow:ellipsis;
            display:block;
            max-width:100%;
        }
        .analise-kpi.positivo strong { color:#198754; }
        .analise-kpi.negativo strong { color:#dc3545; }
        .analise-kpi.neutro   strong { color:#495057; }

        @media (max-width: 991px) {
            .analise-kpi strong { font-size:1.05rem; }
        }

        .analise-progresso { height:12px; border-radius:6px; background:#e6eaf1; overflow:hidden; margin-top:6px; }
        .analise-progresso .barra { height:100%; background:#0d6efd; transition:width .4s ease; }
        .analise-progresso .barra.ok     { background:#198754; }
        .analise-progresso .barra.alerta { background:#dc3545; }
        .analise-progresso .barra.meio   { background:#ffc107; }

        .analise-badge { display:inline-block; padding:6px 14px; border-radius:20px; font-weight:600; font-size:.8rem; }
        .analise-badge.ok     { background:#198754; color:#fff; }
        .analise-badge.alerta { background:#dc3545; color:#fff; }
        .analise-badge.meio   { background:#ffc107; color:#000; }
        .analise-badge.sem    { background:#6c757d; color:#fff; }

        .analise-sugestao { margin-top:14px; padding:10px 14px; border-radius:10px; background:#fff; border:1px dashed #c6d0dd; font-size:.85rem; }
        .analise-sugestao strong { color:#0b1a4a; }
        .analise-acoes { margin-top:12px; display:flex; gap:8px; flex-wrap:wrap; }

        /* ============================================================
           CRITÉRIOS DE NOTIFICAÇÃO
           ============================================================ */
        .criterios { margin-top:16px; padding:14px 16px; background:#fff; border:1px solid #e3e8f0; border-radius:10px; }
        .criterios h6 { font-size:.8rem; font-weight:700; color:#0b1a4a; text-transform:uppercase; letter-spacing:.04em; margin:0 0 10px; }
        .criterios ul { list-style:none; padding:0; margin:0; }
        .criterios li { display:flex; align-items:flex-start; gap:10px; padding:6px 0; border-bottom:1px dashed #eef1f6; font-size:.83rem; }
        .criterios li:last-child { border-bottom:none; }
        .criterios .faixa { flex:0 0 160px; font-weight:700; white-space:nowrap; }
        .criterios .faixa.ok     { color:#198754; }
        .criterios .faixa.meio   { color:#b98900; }
        .criterios .faixa.alerta { color:#dc3545; }
        .criterios .desc { color:#495057; flex:1; }
        .criterios .desc strong { color:#0b1a4a; }
        .criterios .obs { margin-top:10px; padding-top:10px; border-top:1px solid #eef1f6; font-size:.78rem; color:#6c757d; font-style:italic; }

        /* Botão de ajuda no header */
        .btn-ajuda-criterios {
            background:#fff; border:1px solid #d7dfea; color:#0b1a4a;
            border-radius:50%; width:34px; height:34px; padding:0;
            display:inline-flex; align-items:center; justify-content:center;
            font-weight:700; font-size:1rem;
        }
        .btn-ajuda-criterios:hover { background:#f4f7fb; border-color:#b9c7db; }
        .popover-criterios { max-width:400px; }
        .popover-criterios .popover-body { font-size:.83rem; }
        .popover-criterios .popover-body ul { padding-left:18px; margin:8px 0 0; }
        .popover-criterios .popover-body li { margin-bottom:4px; }
    </style>
</head>
<body>
<?php include APP_PATH . '/Views/header_atlas.php'; ?>

<div class="container-fluid mt-4">

    <?php if (isset($_GET['sync']) && $_GET['sync'] === 'ok'): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle"></i>
        <strong>Sincronização do Dataset 36 concluída com sucesso!</strong>
        <?php if (isset($_GET['inseridos'])): ?>
            <span class="ms-1"><?= (int)$_GET['inseridos'] ?> registros atualizados.</span>
        <?php endif; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <h2 class="sistema-titulo mb-0"><i class="bi bi-clipboard-check"></i> Notificações de Obras - Cronogramas</h2>

                <!-- ✅ Botão de ajuda com critérios -->
                <button type="button" class="btn-ajuda-criterios"
                        data-bs-toggle="popover"
                        data-bs-trigger="focus"
                        data-bs-html="true"
                        data-bs-placement="bottom"
                        data-bs-custom-class="popover-criterios"
                        title="<i class='bi bi-info-circle'></i> Critérios de notificação"
                        data-bs-content="
                            <p class='mb-2'>Comparamos o <strong>valor previsto</strong> com o <strong>valor executado</strong> da obra. A diferença percentual define a ação:</p>
                            <ul>
                                <li><strong style='color:#198754;'>Diferença &lt; 15%</strong> — Empresa performando. <em>Não notificar.</em></li>
                                <li><strong style='color:#b98900;'>15% ≤ diferença &lt; <?= $limiteAlerta ?>%</strong> — Atenção. Acompanhar.</li>
                                <li><strong style='color:#dc3545;'>Diferença ≥ <?= $limiteAlerta ?>%</strong> — Baixa performance. <em>Notificar.</em></li>
                            </ul>
                            <p class='mb-0 mt-2 small text-muted'>Fórmula: (previsto − executado) ÷ previsto × 100</p>
                        ">
                    ?
                </button>

                <span class="ultima-atualizacao">
                    <i class="bi bi-clock-history"></i>
                    Última sincronização dos bancos: <strong><?= htmlspecialchars($ultimaAtualizacao) ?></strong>
                </span>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary btn-sm" id="btnAtualizar"><i class="bi bi-arrow-repeat"></i> Atualizar Dados</button>
                <a href="home_atlas" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left"></i> Voltar</a>
            </div>
        </div>

        <?php if (!empty($filtrosAplicados)): ?>
        <div class="mb-3 p-2 bg-light rounded-3 d-flex flex-wrap align-items-center">
            <span class="fw-bold me-2"><i class="bi bi-funnel"></i> Filtros aplicados:</span>
            <?php foreach ($filtrosAplicados as $f):
                $novosGET = $_GET; unset($novosGET[$f['param']], $novosGET['export'], $novosGET['sync'], $novosGET['inseridos'], $novosGET['msg']);
                $urlSemFiltro = '?' . http_build_query($novosGET);
            ?>
                <span class="badge-filtro">
                    <?= htmlspecialchars($f['label']) ?>: <?= htmlspecialchars($f['valor']) ?>
                    <a href="<?= $urlSemFiltro ?>" class="remover-filtro text-decoration-none" title="Remover"><i class="bi bi-x-circle"></i></a>
                </span>
            <?php endforeach; ?>
            <a href="notificacoes_obras" class="btn btn-sm btn-outline-secondary ms-2">Limpar todos</a>
        </div>
        <?php endif; ?>

        <form method="GET" class="row g-2 mb-1 align-items-end" id="filtrosForm">
            <div class="col-lg-1 col-md-3 col-sm-6 col-12 filtro-col">
                <label class="form-label" style="font-size:0.75rem;">UF</label>
                <select name="uf" class="form-select filtro-select2">
                    <option value="">Selecione...</option>
                    <?php foreach ($ufs as $uf): ?>
                        <option value="<?= htmlspecialchars($uf) ?>" <?= ($_GET['uf'] ?? '') == $uf ? 'selected' : '' ?>><?= htmlspecialchars($uf) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-2 col-md-3 col-sm-6 col-12 filtro-col">
                <label class="form-label" style="font-size:0.75rem;">Tipo de Contrato</label>
                <select name="tipo_contrato" class="form-select filtro-select2">
                    <option value="">Selecione...</option>
                    <?php foreach ($tipos as $t): ?>
                        <option value="<?= htmlspecialchars($t) ?>" <?= ($_GET['tipo_contrato'] ?? '') == $t ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-2 col-md-3 col-sm-6 col-12 filtro-col">
                <label class="form-label" style="font-size:0.75rem;">Status Cronograma</label>
                <select name="status_cronograma" class="form-select filtro-select2">
                    <option value="">Selecione...</option>
                    <?php foreach ($statusCronOptions as $v => $l): ?>
                        <option value="<?= $v ?>" <?= ($_GET['status_cronograma'] ?? '') == $v ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-2 col-md-3 col-sm-6 col-12 filtro-col">
                <label class="form-label" style="font-size:0.75rem;">Status Notificação</label>
                <select name="status_notificacao" class="form-select filtro-select2">
                    <option value="">Selecione...</option>
                    <?php foreach ($statusNotifOptions as $v => $l): ?>
                        <option value="<?= $v ?>" <?= ($_GET['status_notificacao'] ?? '') == $v ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-2 col-md-2 col-sm-6 col-12 filtro-col">
                <label class="form-label" style="font-size:0.75rem;">Ação</label>
                <select name="acao" class="form-select filtro-select2">
                    <option value="">Selecione...</option>
                    <?php foreach ($acoesList as $v => $l): ?>
                        <option value="<?= $v ?>" <?= ($_GET['acao'] ?? '') == $v ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-1 col-md-3 col-sm-6 col-12 filtro-col">
                <label class="form-label" style="font-size:0.75rem;">Contrato</label>
                <select name="contrato" class="form-select filtro-select2">
                    <option value="">Selecione...</option>
                    <?php foreach ($instrumentos as $ins): ?>
                        <option value="<?= htmlspecialchars($ins) ?>" <?= ($_GET['contrato'] ?? '') == $ins ? 'selected' : '' ?>><?= htmlspecialchars($ins) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-2 col-md-3 col-sm-6 col-12 filtro-col">
                <label class="form-label" style="font-size:0.75rem;">Buscar</label>
                <input type="text" name="busca" class="form-control" value="<?= htmlspecialchars($_GET['busca'] ?? '') ?>" placeholder="Contrato, nome usual, empresa...">
            </div>
            <div class="col-12 d-none"><input type="submit" class="btn btn-primary"></div>
        </form>

        <div class="export-row d-flex justify-content-end mt-2">
            <button type="button" class="btn btn-success" id="btnExportar"><i class="bi bi-file-excel"></i> Gerar Relatório</button>
        </div>

        <div class="modal fade modal-confirm-export" id="modalConfirmExport" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-body">
                        <div class="modal-icon"><i class="bi bi-file-earmark-spreadsheet"></i></div>
                        <h5 class="modal-title-confirm">Gerar Relatório</h5>
                        <p class="mt-2">Deseja realmente gerar o relatório de notificações de obras com os filtros atuais?</p>
                        <div class="d-flex justify-content-center gap-3 mt-4">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-success" id="btnConfirmarExportar">Confirmar</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="info-count">
            <i class="bi bi-list-ul"></i> <strong>Contratos:</strong> <?= (int)$total ?>
            <span class="obs"><i class="bi bi-info-circle"></i> Todos os contratos são exibidos, inclusive concluídos.</span>
        </div>

        <div class="table-wrapper">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th class="col-checkbox text-center"><input type="checkbox" id="selecionarTodos" title="Selecionar todos"></th>
                        <th class="col-contrato">Contrato</th>
                        <th class="col-tipo">Tipo de execução</th>
                        <th class="col-br">BR</th>
                        <th>UF</th>
                        <th>Lote</th>
                        <th>Nome Usual</th>
                        <th>Empresa</th>
                        <th>Data Término Cronog.</th>
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
                    <tr><td colspan="18" class="text-center text-muted py-4">Nenhum contrato encontrado.</td></tr>
                <?php else: foreach ($contratos as $c):
                    $sc = $c['_status_crono'];
                    $sn = $c['_status_notif'];
                    $classeCron  = $classCron[$sc['categoria']]  ?? 'cron-semdata';
                    $classeNotif = $classNotif[$sn['categoria']] ?? 'notif-semdata';
                    $seiCron   = $c['sei_cronograma_vigente'] ?? '';
                    $dataTerm  = $c['data_termino_servicos_cronograma'] ?? '';
                    $dataUlt   = $c['data_ultima_notificacao_cronograma'] ?? '';
                    $oficio    = $c['ultimo_oficio_notificacao_cronograma'] ?? '';
                    $isento    = (int)($c['isento'] ?? 0);
                    $acao      = $c['status_acao'] ?? 'nao_notificar';
                    $labelAcao = $acoesList[$acao] ?? $acao;
                    $tipo      = $c['tipo_contrato'] ?? '';
                    $totalCom  = (int)($c['total_comentarios'] ?? 0);
                    $temCom    = $totalCom > 0;
                    switch ($acao) {
                        case 'notificar':             $btnColor='primary';   $btnIcon='bi-pencil-square';   break;
                        case 'notificado':            $btnColor='success';   $btnIcon='bi-check-circle';    break;
                        case 'aguardando_assinatura': $btnColor='purple';    $btnIcon='bi-hourglass-split'; break;
                        case 'isento':                $btnColor='secondary'; $btnIcon='bi-slash-circle';    break;
                        default:                      $btnColor='warning';   $btnIcon='bi-clock';           break;
                    }
                    $dataTermFmt = $dataTerm ? date('d/m/Y', strtotime($dataTerm)) : '-';
                    $dataUltFmt  = $dataUlt  ? date('d/m/Y', strtotime($dataUlt))  : '-';
                ?>
                    <tr data-contrato-id="<?= (int)$c['id'] ?>" data-isento="<?= $isento ?>" data-acao="<?= htmlspecialchars($acao) ?>">
                        <td class="text-center"><input type="checkbox" class="checkbox-selecionar" data-contrato-id="<?= (int)$c['id'] ?>"></td>
                        <td class="col-contrato"><strong><?= htmlspecialchars($c['instrumento'] ?? '') ?></strong></td>
                        <td class="col-tipo"><span class="badge-tipo"><?= htmlspecialchars($tipo !== '' ? $tipo : '—') ?></span></td>
                        <td class="col-br"><?= htmlspecialchars($c['br'] ?? '') ?></td>
                        <td><?= htmlspecialchars($c['uf'] ?? '') ?></td>
                        <td><?= htmlspecialchars($c['lote'] ?? '') ?></td>
                        <td><?= htmlspecialchars($c['nome_usual'] ?? '') ?></td>
                        <td><?= htmlspecialchars($c['empresa'] ?? '') ?></td>
                        <td class="text-nowrap"><?= $dataTermFmt ?></td>
                        <td><span class="badge <?= $classeCron ?>"><?= htmlspecialchars($sc['texto']) ?></span></td>
                        <td><?php if ($seiCron !== ''): ?><span class="sei-link" data-copiar="<?= htmlspecialchars($seiCron) ?>"><?= htmlspecialchars($seiCron) ?></span><?php else: ?>—<?php endif; ?></td>
                        <td><?= $dataUltFmt ?></td>
                        <td><span class="badge <?= $classeNotif ?>"><?= htmlspecialchars($sn['texto']) ?></span></td>
                        <td><?php if ($oficio !== ''): ?><span class="sei-link" data-copiar="<?= htmlspecialchars($oficio) ?>"><?= htmlspecialchars($oficio) ?></span><?php else: ?>—<?php endif; ?></td>
                        <td><span class="badge-acao <?= htmlspecialchars($acao) ?>"><?= htmlspecialchars($labelAcao) ?></span></td>
                        <td><span class="badge <?= $isento ? 'isento-badge' : 'bg-secondary' ?>"><?= $isento ? 'Isento' : 'Ativo' ?></span></td>
                        <td class="text-center">
                            <span class="comentario-badge <?= $temCom ? 'has-comentario' : '' ?>"
                                  data-bs-toggle="modal" data-bs-target="#modalComentario"
                                  data-contrato-id="<?= (int)$c['id'] ?>"
                                  data-contrato-num="<?= htmlspecialchars($c['instrumento'] ?? '') ?>" title="Comentários">
                                <i class="bi bi-chat-dots"></i><span class="badge-dot"></span>
                            </span>
                        </td>
                        <td class="text-nowrap">
                            <button class="btn btn-outline-info btn-sm btn-acoes btn-detalhes" data-id="<?= (int)$c['id'] ?>" title="Visualizar detalhes"><i class="bi bi-eye"></i></button>
                            <div class="btn-group btn-notificacao-dropdown">
                                <button type="button" class="btn btn-sm btn-outline-<?= $btnColor ?> dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi <?= $btnIcon ?>"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="#" data-acao="notificar"><i class="bi bi-pencil-square text-primary"></i> Notificar</a></li>
                                    <li><a class="dropdown-item" href="#" data-acao="notificado"><i class="bi bi-check-circle text-success"></i> Notificado</a></li>
                                    <li><a class="dropdown-item" href="#" data-acao="aguardando_assinatura"><i class="bi bi-hourglass-split" style="color:#6f42c1;"></i> Aguardando assinatura</a></li>
                                    <li><a class="dropdown-item" href="#" data-acao="isento"><i class="bi bi-slash-circle text-secondary"></i> Isento</a></li>
                                    <li><a class="dropdown-item" href="#" data-acao="nao_notificar"><i class="bi bi-clock text-warning"></i> Não Notificar</a></li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

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

<div class="modal fade" id="modalDetalhes" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Detalhes do Contrato</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body" id="detalhesBody"><div class="text-center py-4">Carregando...</div></div>
        </div>
    </div>
</div>

<div id="toastCopiado"><i class="bi bi-check-circle-fill text-success"></i> SEI copiado!</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
const CSRF_TOKEN    = <?= json_encode($csrf ?? '') ?>;
const USUARIO_ID    = <?= (int)$usuario_id ?>;
const USUARIO_NIVEL = <?= json_encode($usuario_nivel) ?>;
const PERCENTUAL_ALERTA = <?= (int)NotificacoesObrasController::PERCENTUAL_ALERTA ?>;
const LIMITE_ATENCAO    = 15;

const URLS = {
    acao:       'notificacoes_obras/atualizar_acao',
    importar36: 'importar_supra_dataset_36_infoobras.php',
    detalhes:   'notificacoes_obras/detalhes',
    comListar:  'notificacoes_obras/comentarios',
    comAdd:     'notificacoes_obras/comentarios/adicionar',
    comEditar:  'notificacoes_obras/comentarios/editar',
    comExcluir: 'notificacoes_obras/comentarios/excluir'
};
const STORAGE_KEY = 'obras_contratos_marcados';

$(document).ready(function() {

    // ✅ Inicializa popovers (botão de ajuda dos critérios)
    document.querySelectorAll('[data-bs-toggle="popover"]').forEach(function (el) {
        new bootstrap.Popover(el, {
            container: 'body',
            sanitize: false
        });
    });

    var toastTimer = null;
    function mostrarToast(msg, tipo) {
        var t = document.getElementById('toastCopiado');
        if (!t) return;
        var icone = (tipo === 'erro') ? 'bi-x-circle-fill text-danger' : 'bi-check-circle-fill text-success';
        t.innerHTML = '<i class="bi ' + icone + '"></i> ' + msg;
        t.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function() { t.classList.remove('show'); }, 4000);
    }

    /* ============================================================
       FILTROS + SELECT2
       ============================================================ */
    $('.filtro-select2').select2({ placeholder:'Selecione...', allowClear:false, width:'100%' });
    $('.filtro-select2').on('change', function() { $(this).closest('form').submit(); });
    $('.filtro-select2').on('select2:open', function() {
        setTimeout(function(){ var f = document.querySelector('.select2-search__field'); if (f) f.focus(); }, 100);
    });
    $('.filtro-select2').on('focus', function() { $(this).select2('open'); });

    $('#btnExportar').on('click', function(e) { e.preventDefault(); new bootstrap.Modal(document.getElementById('modalConfirmExport')).show(); });
    $('#btnConfirmarExportar').on('click', function() {
        var form = $('#filtrosForm');
        form.find('input[name="export"]').remove();
        $('<input>').attr({type:'hidden', name:'export', value:'excel'}).appendTo(form);
        bootstrap.Modal.getInstance(document.getElementById('modalConfirmExport')).hide();
        window.location = 'notificacoes_obras/exportar?' + form.serialize();
    });

    /* ============================================================
       BOTÃO ATUALIZAR (AJAX + spinner + toast + reload)
       ============================================================ */
    $('#btnAtualizar').on('click', function() {
        if (!confirm('Deseja sincronizar os dados com o Dataset 36 (ATLAS — Informações Gerais de Obras) agora? A base atual será substituída.')) return;
        var btn = this;
        var original = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Sincronizando...';

        $.ajax({
            url: URLS.importar36,
            method: 'GET',
            data: { ajax: 1 },
            dataType: 'json',
            timeout: 300000
        })
        .done(function(r) {
            if (r && r.success) {
                mostrarToast('Dataset 36 atualizado! ' + r.inseridos + ' registros sincronizados.');
                setTimeout(function() { location.reload(); }, 1800);
            } else {
                btn.disabled = false;
                btn.innerHTML = original;
                mostrarToast('Erro ao sincronizar: ' + ((r && r.error) || 'Resposta inválida'), 'erro');
            }
        })
        .fail(function(xhr, status) {
            btn.disabled = false;
            btn.innerHTML = original;
            var msg = 'Falha na requisição';
            if (xhr && xhr.status) msg += ' (HTTP ' + xhr.status + ')';
            if (status) msg += ' — ' + status;
            mostrarToast(msg, 'erro');
        });
    });

    /* ============================================================
       COPIAR SEI
       ============================================================ */
    window.copiarTexto = function(texto) {
        if (!texto || texto === '—') return;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(texto).then(function(){ mostrarToast('Copiado: ' + texto); }).catch(function(){ fallback(texto); });
        } else fallback(texto);
    };
    function fallback(texto) {
        var ta = document.createElement('textarea'); ta.value = texto;
        ta.style.position='fixed'; ta.style.top='-9999px'; ta.style.opacity='0';
        document.body.appendChild(ta); ta.select();
        try { document.execCommand('copy') ? mostrarToast('Copiado: ' + texto) : mostrarToast('Erro ao copiar', 'erro'); } catch(e) { mostrarToast('Erro ao copiar', 'erro'); }
        document.body.removeChild(ta);
    }
    $(document).on('click', '.sei-link', function() { copiarTexto($(this).data('copiar')); });

    /* ============================================================
       MARCAÇÕES (localStorage)
       ============================================================ */
    var marcadosSet = new Set();
    try { JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]').forEach(function(id){ marcadosSet.add(String(id)); }); } catch(e){}
    function salvar() { localStorage.setItem(STORAGE_KEY, JSON.stringify(Array.from(marcadosSet))); }
    function aplicar() {
        $('.checkbox-selecionar').each(function() {
            var id = String($(this).data('contrato-id'));
            if (marcadosSet.has(id)) { $(this).prop('checked', true); $(this).closest('tr').addClass('linha-marcada'); }
        });
    }
    aplicar();
    $(document).on('change', '.checkbox-selecionar', function() {
        var id = String($(this).data('contrato-id'));
        var $row = $(this).closest('tr');
        if (this.checked) { marcadosSet.add(id); $row.addClass('linha-marcada'); }
        else { marcadosSet.delete(id); $row.removeClass('linha-marcada'); }
        salvar();
    });
    $('#selecionarTodos').on('change', function() {
        var on = this.checked;
        $('.checkbox-selecionar').each(function() {
            var id = String($(this).data('contrato-id'));
            if (on) { marcadosSet.add(id); $(this).prop('checked', true); $(this).closest('tr').addClass('linha-marcada'); }
            else { marcadosSet.delete(id); $(this).prop('checked', false); $(this).closest('tr').removeClass('linha-marcada'); }
        });
        salvar();
    });

    /* ============================================================
       DROPDOWN DE AÇÃO
       ============================================================ */
    $(document).on('click', '.btn-notificacao-dropdown .dropdown-item', function(e) {
        e.preventDefault();
        var $item = $(this); var acao = $item.data('acao');
        var $row = $item.closest('tr'); var id = $row.data('contrato-id');
        $.post(URLS.acao, { id: id, acao: acao, csrf_token: CSRF_TOKEN }, null, 'json')
            .done(function(r) {
                if (!r.success) { mostrarToast('Erro: ' + (r.error || 'Desconhecido'), 'erro'); return; }
                var config = {
                    notificar:             {label:'Notificar',             cls:'notificar',             color:'primary',   icon:'bi-pencil-square'},
                    notificado:            {label:'Notificado',            cls:'notificado',            color:'success',   icon:'bi-check-circle'},
                    aguardando_assinatura: {label:'Aguardando assinatura', cls:'aguardando_assinatura', color:'purple',    icon:'bi-hourglass-split'},
                    isento:                {label:'Isento',                cls:'isento',                color:'secondary', icon:'bi-slash-circle'},
                    nao_notificar:         {label:'Não Notificar',         cls:'nao_notificar',         color:'warning',   icon:'bi-clock'}
                }[acao];
                if (!config) return;
                $row.find('td').eq(14).html('<span class="badge-acao ' + config.cls + '">' + config.label + '</span>');
                var isento = (acao === 'isento') ? 1 : 0;
                $row.attr('data-isento', isento).attr('data-acao', acao);
                var $tdIsento = $row.find('td').eq(15).find('.badge');
                if (isento) $tdIsento.removeClass('bg-secondary').addClass('isento-badge').text('Isento');
                else        $tdIsento.removeClass('isento-badge').addClass('bg-secondary').text('Ativo');
                var $btn = $row.find('.btn-notificacao-dropdown .dropdown-toggle');
                $btn.removeClass('btn-outline-primary btn-outline-success btn-outline-purple btn-outline-secondary btn-outline-warning').addClass('btn-outline-' + config.color).html('<i class="bi ' + config.icon + '"></i>');
            })
            .fail(function() { mostrarToast('Falha na requisição.', 'erro'); });
    });

    /* ============================================================
       COMENTÁRIOS
       ============================================================ */
    $('#modalComentario').on('show.bs.modal', function(event) {
        var $t = $(event.relatedTarget);
        $('#modal-contrato-num').text($t.data('contrato-num'));
        $('#comentario-contrato-id').val($t.data('contrato-id'));
        carregarComentarios($t.data('contrato-id'));
    });
    function escaparHtml(texto) { return $('<div>').text(texto).html(); }
    function carregarComentarios(contratoId) {
        $('#lista-comentarios').html('<p class="text-muted text-center">Carregando...</p>');
        $.getJSON(URLS.comListar, { contrato_id: contratoId }, function(r) {
            if (!r.success) { $('#lista-comentarios').html('<p class="text-danger text-center">Erro.</p>'); return; }
            var data = r.data || [];
            if (data.length === 0) { $('#lista-comentarios').html('<p class="text-muted text-center">Nenhum comentário ainda.</p>'); return; }
            var html = '';
            data.forEach(function(c) {
                var texto = c.comentario || '';
                var podeEditar = (USUARIO_NIVEL === 'desenvolvedor' || USUARIO_NIVEL === 'admin' || c.usuario_id == USUARIO_ID);
                var podeExcluir = (USUARIO_NIVEL === 'desenvolvedor' || USUARIO_NIVEL === 'admin');
                var btnEditar  = podeEditar  ? ' <span class="btn-editar-comentario text-primary" data-id="'+c.id+'" title="Editar"><i class="bi bi-pencil"></i></span>' : '';
                var btnExcluir = podeExcluir ? ' <span class="btn-excluir-comentario text-danger" data-id="'+c.id+'" title="Excluir"><i class="bi bi-trash"></i></span>' : '';
                html += '<div class="comentario-bolha" data-id="'+c.id+'">' +
                            '<div class="comentario-header"><span class="comentario-usuario">'+escaparHtml(c.usuario_nome)+'</span>' +
                            '<div class="comentario-acoes"><span class="comentario-data">'+c.created_at+'</span>' + btnEditar + btnExcluir + '</div></div>' +
                            '<div class="comentario-texto">'+escaparHtml(texto)+'</div></div>';
            });
            $('#lista-comentarios').html(html);
            $('.btn-editar-comentario').off('click').on('click', function() {
                var id = $(this).data('id'); var $bolha = $(this).closest('.comentario-bolha');
                var textoAtual = $bolha.find('.comentario-texto').text().trim();
                var $area = $('<textarea class="form-control comentario-edit-area" rows="2"></textarea>').val(textoAtual);
                var $botoes = $('<div class="comentario-actions"><button class="btn btn-success btn-sm salvar-edicao">Salvar</button> <button class="btn btn-secondary btn-sm cancelar-edicao">Cancelar</button></div>');
                $bolha.find('.comentario-texto').replaceWith($area);
                $bolha.find('.comentario-acoes').after($botoes);
                $bolha.find('.btn-editar-comentario, .btn-excluir-comentario').hide();
                $botoes.find('.salvar-edicao').on('click', function() {
                    var novo = $area.val().trim(); if (!novo) { alert('O comentário não pode ficar vazio.'); return; }
                    $.post(URLS.comEditar, { id: id, comentario: novo, csrf_token: CSRF_TOKEN }, null, 'json')
                        .done(function(res) { if (res.success) carregarComentarios($('#comentario-contrato-id').val()); else alert('Erro: ' + (res.error || '')); });
                });
                $botoes.find('.cancelar-edicao').on('click', function() { carregarComentarios($('#comentario-contrato-id').val()); });
            });
            $('.btn-excluir-comentario').off('click').on('click', function() {
                if (!confirm('Excluir este comentário?')) return;
                var id = $(this).data('id');
                $.post(URLS.comExcluir, { id: id, csrf_token: CSRF_TOKEN }, null, 'json')
                    .done(function(res) { if (res.success) { carregarComentarios($('#comentario-contrato-id').val()); atualizarBadge($('#comentario-contrato-id').val()); } else alert('Erro: ' + (res.error || '')); });
            });
        });
    }
    function atualizarBadge(contratoId) {
        $.getJSON(URLS.comListar, { contrato_id: contratoId }, function(r) {
            var total = (r.data || []).length;
            var $b = $('.comentario-badge[data-contrato-id="'+contratoId+'"]');
            if (total > 0) $b.addClass('has-comentario'); else $b.removeClass('has-comentario');
        });
    }
    $('#form-comentario').on('submit', function(e) {
        e.preventDefault();
        var contratoId = $('#comentario-contrato-id').val();
        var comentario = $('#novo-comentario').val().trim(); if (!comentario) return;
        $.post(URLS.comAdd, { contrato_id: contratoId, comentario: comentario, csrf_token: CSRF_TOKEN }, null, 'json')
            .done(function(res) {
                if (res.success) { $('#novo-comentario').val(''); carregarComentarios(contratoId); $('.comentario-badge[data-contrato-id="'+contratoId+'"]').addClass('has-comentario'); }
                else alert('Erro: ' + (res.error || ''));
            }).fail(function() { alert('Erro de comunicação.'); });
    });

    /* ============================================================
       DETALHES + ANÁLISE DE PERFORMANCE
       ============================================================ */
    function formatarMoeda(v) {
        if (v === null || v === undefined || isNaN(v)) return '—';
        return Number(v).toLocaleString('pt-BR', { style:'currency', currency:'BRL', minimumFractionDigits:2 });
    }
    function formatarPercentual(v) {
        if (v === null || v === undefined || isNaN(v)) return '—';
        return Number(v).toLocaleString('pt-BR', { minimumFractionDigits:2, maximumFractionDigits:2 }) + '%';
    }
    function escapar(t) { return $('<div>').text(t == null ? '' : t).html(); }

    function calcularAnalise(valorPrevisto, valorExecutado) {
        var vp = parseFloat(valorPrevisto);
        var ve = parseFloat(valorExecutado);
        var temVp = !isNaN(vp) && vp > 0;
        var temVe = !isNaN(ve);

        if (!temVp || !temVe) {
            return { status:'sem', diferenca_valor:null, diferenca_percentual:null, progresso:0,
                     texto:'Sem dados de valor previsto/executado para análise.', badgeLabel:'Sem dados' };
        }

        var diferencaValor = vp - ve;
        var diferencaPercentual = (diferencaValor / vp) * 100;
        var progresso = Math.min(100, (ve / vp) * 100);

        var status, badgeLabel, texto;
        if (diferencaPercentual >= PERCENTUAL_ALERTA) {
            status = 'alerta';
            badgeLabel = 'Alerta — Notificar';
            texto = 'Diferença de <strong>' + formatarPercentual(diferencaPercentual) + '</strong> entre o previsto e o executado. '
                  + 'Como é <strong>≥ ' + PERCENTUAL_ALERTA + '%</strong>, a empresa está com baixa performance. '
                  + 'Recomendação: <strong>notificar</strong>.';
        } else if (diferencaPercentual >= LIMITE_ATENCAO) {
            status = 'meio';
            badgeLabel = 'Atenção';
            texto = 'Diferença de <strong>' + formatarPercentual(diferencaPercentual) + '</strong>. '
                  + 'Está entre <strong>' + LIMITE_ATENCAO + '% e ' + PERCENTUAL_ALERTA + '%</strong> — '
                  + 'abaixo do limite de notificação, mas merece acompanhamento.';
        } else {
            status = 'ok';
            badgeLabel = 'OK — Empresa performando';
            texto = 'Diferença de apenas <strong>' + formatarPercentual(diferencaPercentual) + '</strong> — '
                  + 'menor que <strong>' + LIMITE_ATENCAO + '%</strong>. '
                  + 'A empresa está performando dentro do esperado. Recomendação: <strong>não notificar</strong>.';
        }
        return { status:status, diferenca_valor:diferencaValor, diferenca_percentual:diferencaPercentual,
                 progresso:progresso, texto:texto, badgeLabel:badgeLabel };
    }

    function renderizarAnalise(c) {
        var a = calcularAnalise(c.valor_previsto, c.valor_executado);
        var classeCard = 'analise-card';
        if (a.status === 'ok') classeCard += ' ok';
        else if (a.status === 'alerta') classeCard += ' alerta';
        else if (a.status === 'meio') classeCard += ' meio';
        else classeCard += ' sem';

        var classeBarra = (a.status === 'alerta') ? 'alerta'
                        : (a.status === 'meio')   ? 'meio'
                        : (a.status === 'ok')     ? 'ok'
                        : '';

        var html = '';
        html += '<div class="analise-wrap">';
        html += '  <div class="' + classeCard + '">';
        html += '    <h6><i class="bi bi-graph-up-arrow"></i> Análise de Performance (Previsto x Executado)</h6>';

        // ✅ Colunas responsivas: 4 em XL, 2×2 em LG/MD, 1 em telas pequenas
        html += '    <div class="row g-3 mb-3">';
        html += '      <div class="col-xl-3 col-lg-6 col-md-6 col-12"><div class="analise-kpi neutro"><small>Valor Previsto</small><strong title="' + formatarMoeda(c.valor_previsto) + '">' + formatarMoeda(c.valor_previsto) + '</strong></div></div>';
        html += '      <div class="col-xl-3 col-lg-6 col-md-6 col-12"><div class="analise-kpi ' + (a.status === 'alerta' ? 'negativo' : 'positivo') + '"><small>Valor Executado</small><strong title="' + formatarMoeda(c.valor_executado) + '">' + formatarMoeda(c.valor_executado) + '</strong></div></div>';
        html += '      <div class="col-xl-3 col-lg-6 col-md-6 col-12"><div class="analise-kpi ' + (a.status === 'alerta' ? 'negativo' : 'neutro') + '"><small>Diferença (R$)</small><strong title="' + formatarMoeda(a.diferenca_valor) + '">' + formatarMoeda(a.diferenca_valor) + '</strong></div></div>';
        html += '      <div class="col-xl-3 col-lg-6 col-md-6 col-12"><div class="analise-kpi ' + (a.status === 'alerta' ? 'negativo' : 'neutro') + '"><small>Diferença (%)</small><strong>' + formatarPercentual(a.diferenca_percentual) + '</strong></div></div>';
        html += '    </div>';

        html += '    <div class="d-flex justify-content-between align-items-center mb-2">';
        html += '      <small class="text-muted">Execução: <strong>' + (a.progresso).toFixed(2) + '%</strong> do previsto</small>';
        html += '      <span class="analise-badge ' + a.status + '">' + a.badgeLabel + '</span>';
        html += '    </div>';
        html += '    <div class="analise-progresso"><div class="barra ' + classeBarra + '" style="width:' + a.progresso.toFixed(2) + '%"></div></div>';

        html += '    <div class="analise-sugestao"><i class="bi bi-info-circle"></i> ' + a.texto + '</div>';

        // ✅ Legenda dos critérios
        html += '    <div class="criterios">';
        html += '      <h6><i class="bi bi-list-check"></i> Critérios utilizados</h6>';
        html += '      <ul>';
        html += '        <li>';
        html += '          <span class="faixa ok">Diferença &lt; ' + LIMITE_ATENCAO + '%</span>';
        html += '          <span class="desc"><strong>Empresa performando.</strong> Execução compatível com o previsto. Ação: <strong>não notificar</strong>.</span>';
        html += '        </li>';
        html += '        <li>';
        html += '          <span class="faixa meio">' + LIMITE_ATENCAO + '% ≤ dif. &lt; ' + PERCENTUAL_ALERTA + '%</span>';
        html += '          <span class="desc"><strong>Atenção.</strong> Diferença moderada. Ação: <strong>monitorar</strong>, mas não notificar.</span>';
        html += '        </li>';
        html += '        <li>';
        html += '          <span class="faixa alerta">Diferença ≥ ' + PERCENTUAL_ALERTA + '%</span>';
        html += '          <span class="desc"><strong>Baixa performance.</strong> Ação recomendada: <strong>notificar</strong> a empresa.</span>';
        html += '        </li>';
        html += '      </ul>';
        html += '      <div class="obs">';
        html += '        <i class="bi bi-calculator"></i> Fórmula: <strong>(Valor Previsto − Valor Executado) ÷ Valor Previsto × 100</strong>. ';
        html += '        O limiar de notificação está configurado em <strong>' + PERCENTUAL_ALERTA + '%</strong> (constante <code>PERCENTUAL_ALERTA</code> no controller).';
        html += '      </div>';
        html += '    </div>';

        html += '  </div>';
        html += '</div>';
        return html;
    }

    $(document).on('click', '.btn-detalhes', function() {
        var id = $(this).data('id');
        $('#detalhesBody').html('<div class="text-center py-4">Carregando...</div>');
        new bootstrap.Modal('#modalDetalhes').show();
        $.getJSON(URLS.detalhes, { id: id }, function(r) {
            if (!r.success) { $('#detalhesBody').html('<div class="text-danger">Erro.</div>'); return; }
            var c = r.data;

            var campos = [
                ['Contrato', escapar(c.instrumento)],
                ['Tipo de execução', escapar(c.tipo_contrato || '—')],
                ['BR', escapar(c.br)],
                ['UF', escapar(c.uf)],
                ['Lote', escapar(c.lote)],
                ['Nome usual', escapar(c.nome_usual)],
                ['Empresa', escapar(c.empresa)],
                ['SEI Cronograma', escapar(c.sei_cronograma_vigente)],
                ['Data término cronograma', escapar(c.data_termino_servicos_cronograma)],
                ['Data última notificação', escapar(c.data_ultima_notificacao_cronograma)],
                ['Ofício notificação', escapar(c.ultimo_oficio_notificacao_cronograma)],
                ['Status cronograma', escapar(c._status_crono ? c._status_crono.texto : '')],
                ['Status notificação', escapar(c._status_notif ? c._status_notif.texto : '')],
                ['Ação', escapar(c.status_acao)]
            ];
            var html = '<div class="row g-3">';
            campos.forEach(function(p) {
                html += '<div class="col-md-4"><small class="text-muted d-block">'+p[0]+'</small><div>'+p[1]+'</div></div>';
            });
            html += '</div>';

            // ✅ Card de análise de performance + critérios
            html += renderizarAnalise(c);

            $('#detalhesBody').html(html);
        });
    });
});
</script>
</body>
</html>