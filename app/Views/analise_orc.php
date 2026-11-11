<?php
// ============================================================
// View analise_orc.php
// Análise Orçamentária e Financeira (Atlas/Monitoramento)
// ============================================================
if (session_status() === PHP_SESSION_NONE) @session_start();
if (empty($_SESSION['usuario_id']) && empty($_SESSION['usuario_nivel'])) {
    header('Location: login'); exit;
}
if (!isset($_SESSION['setor_slug'])) {
    header('Location: selecionar_setor'); exit;
}

/** @var array $acoes */
$exercicio  = (int)($exercicio  ?? date('Y'));
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$limitePerformance = (int)($limitePerformance ?? 30);

$podeScraper = (bool)($podeScraper ?? false);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Análise Orçamentária e Financeira - SISPRO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        body { background:#f8f9fc; padding-top:70px; }
        .container-fluid { max-width:98%; }
        .card { border:none; border-radius:16px; box-shadow:0 8px 30px rgba(0,0,0,0.08); background:#fff; padding:20px 25px; }
        .sistema-titulo { font-weight:700; color:#004a8f; font-size:1.4rem; letter-spacing:1px; }
        .btn-sm { padding:0.2rem 0.5rem; font-size:0.75rem; }
        .ultima-atualizacao { font-size:.78rem; color:#6c757d; background:#f1f5f9; border:1px solid #e2e8f0;
            padding:4px 12px; border-radius:20px; display:inline-flex; align-items:center; gap:6px; white-space:nowrap; }
        .ultima-atualizacao strong { color:#0d6efd; }
        .select2-container .select2-selection--single { height:38px; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height:38px; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height:36px; }
        .select2-container--default .select2-selection--single .select2-selection__placeholder { color:#6c757d; }
        .badge-filtro { background:#e9ecef; color:#2c3e50; font-weight:500; padding:6px 12px; border-radius:20px;
            margin-right:8px; margin-bottom:4px; display:inline-flex; align-items:center; gap:6px; }
        .badge-filtro .remover-filtro { cursor:pointer; opacity:.7; }
        .badge-filtro .remover-filtro:hover { opacity:1; }
        @media (max-width: 1199px) { .filtro-col { flex:0 0 33.333%; max-width:33.333%; } }
        @media (max-width: 767px)  { .filtro-col { flex:0 0 50%;     max-width:50%; } }
        @media (max-width: 575px)  { .filtro-col { flex:0 0 100%;    max-width:100%; } }
        .btn-ajuda-criterios { background:#fff; border:1px solid #d7dfea; color:#0b1a4a; border-radius:50%;
            width:34px; height:34px; padding:0; display:inline-flex; align-items:center; justify-content:center;
            font-weight:700; font-size:1rem; }
        .btn-ajuda-criterios:hover { background:#f4f7fb; border-color:#b9c7db; }
        .popover-criterios { max-width:420px; }
        .popover-criterios .popover-body { font-size:.83rem; }
        .info-count { font-size:.9rem; color:#6c757d; }
        .info-count strong { color:#2c3e50; }
        .info-count .obs { color:#6c757d; font-style:italic; margin-left:.5rem; }
        .table-wrapper { position:relative; max-height:600px; overflow-y:auto; overflow-x:auto; }
        .table-wrapper table { width:100%; border-collapse:separate; border-spacing:0; }
        .table-wrapper table thead th { position:sticky; top:0; z-index:10; background:#fff; border-bottom:2px solid #dee2e6; }
        .table th { font-weight:600; color:#2c3e50; font-size:.72rem; padding:8px 6px; white-space:nowrap; }
        .table td { vertical-align:middle; font-size:.8rem; padding:6px 6px; line-height:1.2; word-break:break-word; }
        .table tbody tr.alerta-linha td { background:#faf0ff !important; }
        .table tbody tr.alerta-linha { box-shadow:inset 4px 0 0 #8e24aa; }
        .table tbody tr { cursor:pointer; }
        .farol-badge { display:inline-block; padding:.25rem .7rem; border-radius:999px; color:#fff;
            font-size:.75rem; font-weight:600; }
        .farol-VERDE{background:#2e7d32}
        .farol-AMARELO{background:#f9a825;color:#1f2937}
        .farol-VERMELHO{background:#c62828}
        .farol-SEM{background:#9ca3af}
        .dot { display:inline-block; width:10px; height:10px; border-radius:50%; margin:0 4px 0 12px; }
        .dot.verde{background:#2e7d32} .dot.amarelo{background:#f9a825}
        .dot.vermelho{background:#c62828} .dot.alerta{background:#8e24aa}
        .bell-btn { position:relative; width:42px; height:42px; padding:0; border-radius:50%;
            border:1px solid #d7dfea; background:#fff; color:#0d3b66; font-size:1.15rem; }
        .bell-btn:hover { background:#f4f7fb; }
        .bell-badge { position:absolute; top:-4px; right:-4px; background:#e53935; color:#fff;
            border-radius:999px; font-size:.65rem; padding:1px 6px; font-weight:700; min-width:18px; }
        .bell-btn.has-unread { animation:pulse 1.6s infinite; border-color:#e53935; color:#e53935; }
        @keyframes pulse { 0%,100%{transform:scale(1)} 50%{transform:scale(1.08)} }
        .card-ind { background:#fff; border-radius:12px; box-shadow:0 4px 14px rgba(0,0,0,.06);
            padding:16px 14px; height:100%; min-height:100px;
            display:flex; flex-direction:column; justify-content:center; align-items:center;
            text-align:center; gap:8px; }
        .card-ind-label { font-size:0.7rem; color:#6c757d; text-transform:uppercase;
            letter-spacing:0.03em; line-height:1.3; font-weight:600;
            display:inline-flex; align-items:center; gap:4px; justify-content:center; }
        .card-ind-label .info-icon { color:#adb5bd; cursor:help; font-size:.85rem; transition:color .15s; }
        .card-ind-label .info-icon:hover { color:#0d6efd; }
        .card-ind-value { font-size:1.15rem; font-weight:700; color:#1f2d3d; line-height:1.2; }

        .tooltip {
            --bs-tooltip-bg: #f8f9fa;
            --bs-tooltip-color: #212529;
            --bs-tooltip-max-width: 560px;
            --bs-tooltip-opacity: 1;
            --bs-tooltip-border-radius: 12px;
        }
        .tooltip .tooltip-inner {
            background: #f8f9fa !important;
            color: #212529 !important;
            border: 1px solid #dee2e6;
            box-shadow: 0 8px 28px rgba(0,0,0,0.18);
            text-align: left !important;
            font-size: .83rem;
            line-height: 1.55;
            max-width: 560px;
            padding: 16px 18px;
            border-radius: 12px;
        }
        .tooltip.bs-tooltip-top .tooltip-arrow::before,
        .tooltip.bs-tooltip-auto[data-popper-placement^="top"] .tooltip-arrow::before { border-top-color: #f8f9fa; }
        .tooltip.bs-tooltip-bottom .tooltip-arrow::before,
        .tooltip.bs-tooltip-auto[data-popper-placement^="bottom"] .tooltip-arrow::before { border-bottom-color: #f8f9fa; }
        .tooltip.bs-tooltip-start .tooltip-arrow::before,
        .tooltip.bs-tooltip-auto[data-popper-placement^="left"] .tooltip-arrow::before { border-left-color: #f8f9fa; }
        .tooltip.bs-tooltip-end .tooltip-arrow::before,
        .tooltip.bs-tooltip-auto[data-popper-placement^="right"] .tooltip-arrow::before { border-right-color: #f8f9fa; }

        .calc-bloco { background:#fff; border:1px solid #e9ecef; border-radius:10px; padding:16px 18px; margin-bottom:14px; }
        .calc-bloco-titulo { font-size:.78rem; text-transform:uppercase; letter-spacing:.05em;
            color:#0d3b66; font-weight:700; margin-bottom:10px;
            display:flex; align-items:center; gap:8px; }
        .calc-bloco-titulo .num { display:inline-flex; align-items:center; justify-content:center;
            width:24px; height:24px; border-radius:50%; background:#0d3b66; color:#fff;
            font-size:.72rem; font-weight:700; }
        .calc-linha { display:flex; padding:8px 0; border-bottom:1px dashed #e9ecef; align-items:center; }
        .calc-linha:last-child { border-bottom:none; }
        .calc-linha .lbl { color:#6c757d; font-weight:600; flex:0 0 240px; min-width:240px; font-size:.84rem; }
        .calc-linha .val { color:#1f2d3d; flex:1; font-size:.9rem; }
        .calc-linha .val code { background:#e9ecef; padding:2px 8px; border-radius:4px; font-size:.85rem; }
        .calc-destaque { background:linear-gradient(135deg,#eef5ff,#f8f9fa); border-left:4px solid #0d6efd;
            padding:16px 20px; border-radius:10px; margin-bottom:14px; }
        .calc-comparacao { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px; }
        .calc-comparacao .cel { background:#f8f9fa; border-radius:8px; padding:14px 16px; border:1px solid #e9ecef; }
        .calc-comparacao .cel .rotulo { font-size:.7rem; text-transform:uppercase; color:#6c757d;
            letter-spacing:.04em; font-weight:600; margin-bottom:6px; }
        .calc-comparacao .cel .valor { font-size:1.3rem; font-weight:800; line-height:1.2; }
        .calc-comparacao .cel.ds47 .valor { color:#0d3b66; }
        .calc-comparacao .cel.calc .valor { color:#198754; }
        .calc-diff { padding:10px 14px; border-radius:8px; font-weight:600; font-size:.9rem;
            display:flex; align-items:center; gap:8px; }
        .calc-memoria { background:#fffaf0; border:1px solid #f9e0a0; border-radius:8px;
            padding:14px 18px; font-family:'Consolas','Monaco',monospace; font-size:.85rem;
            line-height:1.7; color:#5a3e00; }
        .calc-memoria .titulo { font-family:inherit; font-weight:700; color:#a06f00;
            font-size:.72rem; text-transform:uppercase; letter-spacing:.06em; margin-bottom:8px; }
        .calc-memoria .linha-op { padding:3px 0; }
        .calc-memoria .resultado-final { border-top:2px dashed #d4a300; padding-top:8px; margin-top:8px;
            font-size:1.05rem; color:#0d3b66; font-weight:800; }
        .calc-badge-medicao { background:#eef5ff; border:1px solid #b6d4fe; color:#0d3b66;
            padding:8px 14px; border-radius:8px; margin:4px 6px 4px 0; display:inline-block;
            font-size:.85rem; }
        .calc-badge-medicao .mes { font-weight:700; }
        .calc-badge-medicao .vl { color:#198754; font-weight:700; }

        .ntf-lista { list-style:none; padding:0; margin:0; max-height:500px; overflow:auto; }
        .ntf-lista li { padding:12px 14px; border-bottom:1px solid #e9ecef; display:flex;
            gap:12px; align-items:flex-start; cursor:pointer; transition:background 0.15s; }
        .ntf-lista li:hover { background:#f4f7fb; }
        .ntf-lista li.nao-lida { background:#faf0ff; border-left:3px solid #8e24aa; }
        .ntf-lista li.nao-lida:hover { background:#f3e6ff; }
        .ntf-icon { font-size:1.4rem; flex-shrink:0; margin-top:2px; }
        .ntf-body { flex:1; min-width:0; }
        .ntf-tipo { font-size:0.92rem; font-weight:700; color:#1f2d3d; margin-bottom:5px; }
        .ntf-contrato { font-size:0.86rem; color:#495057; display:flex; gap:6px;
            align-items:center; flex-wrap:wrap; margin-bottom:3px; }
        .ntf-acao { font-size:0.82rem; color:#6c757d; margin-bottom:3px; }
        .ntf-meses { font-size:0.82rem; color:#b98900; margin-bottom:3px; font-weight:600; }
        .ntf-time { font-size:0.75rem; color:#adb5bd; margin-top:4px; }
        .ler-btn { align-self:flex-start; font-size:0.7rem; padding:2px 8px; }
        table.tbl-compacta { font-size:.85rem; margin-bottom:0; }
        table.tbl-compacta th { background:#f9fafb; color:#6c757d; font-weight:600;
            font-size:.72rem; text-transform:uppercase; white-space:nowrap; }
        table.tbl-compacta th .th-info { color:#adb5bd; cursor:help; margin-left:2px; font-size:.7rem; }
        table.tbl-compacta tbody tr.linha-mes-pendente td { background:#fffbea; }
        table.tbl-compacta tbody tr.linha-sem-plano td { background:#fff3cd; }
        table.tbl-compacta tbody tr.linha-sem-plano:hover td { background:#ffe9a8; }

        table.tbl-compacta tfoot tr.tbl-total td {
            background:#eef5ff !important;
            font-weight:800 !important;
            color:#0d3b66 !important;
            border-top:2px solid #0d3b66;
            padding:8px 6px;
            font-size:.86rem;
        }
        table.tbl-compacta tfoot tr.tbl-total td:first-child {
            text-transform:uppercase;
            letter-spacing:.05em;
            font-size:.78rem;
        }

        .edit-est { cursor:pointer; color:#adb5bd; font-size:.85rem; margin-left:3px; }
        .edit-est:hover { color:#0d6efd; }
        .edit-est.manual { color:#0d6efd; }
        .edit-ana { cursor:pointer; color:#adb5bd; font-size:.85rem; margin-left:3px; }
        .edit-ana:hover { color:#0d6efd; }
        .edit-ana.manual { color:#0d6efd; }

        .badge-src {
            font-size:.6rem;
            padding:1px 5px;
            border-radius:3px;
            font-weight:700;
            margin-right:4px;
            letter-spacing:.02em;
            display:inline-block;
            vertical-align:middle;
            line-height:1.4;
        }
        .badge-src.npo { background:#dbeafe; color:#1e40af; }
        .badge-src.med { background:#e3f2fd; color:#0d47a1; }
        .badge-src.est { background:#fef3c7; color:#92400e; }
        .badge-src.man { background:#fed7aa; color:#9a3412; }

        .tab-btn.active { background:#0d3b66; color:#fff; border-color:#0d3b66; }
        .med-stat { background:#f8f9fa; border:1px solid #e9ecef; border-radius:8px; padding:.75rem; height:100%; }
        .med-stat small { display:block; color:#6c757d; font-size:.7rem; text-transform:uppercase;
            letter-spacing:.03em; margin-bottom:3px; }
        .med-stat strong { font-size:1.05rem; }
        .alerta-estat { background:#fff3e0; border-left:4px solid #f9a825; padding:.6rem;
            font-size:.8rem; color:#7c4a03; border-radius:4px; }
        @keyframes flash-alerta {
            0%, 100% { box-shadow: 0 0 0 0 rgba(249, 168, 37, 0); }
            50% { box-shadow: 0 0 0 8px rgba(249, 168, 37, 0.55); }
        }
        .flash-alerta { animation: flash-alerta 0.8s ease-in-out 3;
            border-radius: 6px; padding: 6px 10px; }

        .col-planejamento { transition:background .15s, opacity .15s, box-shadow .15s; position:relative; }
        .col-planejamento.inativa { background:#f1f3f5 !important; opacity:.68; }
        .col-planejamento.ativa   { background:#e3f2fd !important; box-shadow:inset 0 -3px 0 #0d6efd; }

        .col-planejamento .radio-col {
            cursor:pointer;
            margin-right:6px;
            font-weight:700;
            color:#495057;
            font-size:1.35rem;
            line-height:1;
            vertical-align:middle;
            display:inline-block;
        }
        .col-planejamento.ativa .radio-col {
            color:#0d6efd;
            text-shadow:0 0 2px rgba(13,110,253,.35);
        }
        th.col-planejamento { cursor:pointer; user-select:none; }
        th.col-planejamento:hover { background:#eef5ff; }

        .plano-cell { display:inline-flex; align-items:center; gap:4px; justify-content:flex-end; }

        #banner-siac-ativo {
            position:fixed; top:80px; right:20px; z-index:9999;
            max-width:460px; min-width:340px; box-shadow:0 6px 24px rgba(0,0,0,0.18);
            border-left:6px solid #0d6efd; border-radius:12px;
        }
        #siac-usuario::placeholder { color:#6c757d; font-style:italic; opacity:1; }

        .siac-rel-linha { padding:10px 12px; border-bottom:1px solid #e9ecef; }
        .siac-rel-linha:last-child { border-bottom:none; }
        .siac-rel-ok       { border-left:3px solid #198754; }
        .siac-rel-ignorado { border-left:3px solid #f9a825; }
        .siac-rel-erro     { border-left:3px solid #dc3545; }
        .siac-rel-contrato { font-family:Consolas,Monaco,monospace; font-weight:600; font-size:.9rem; }
        .siac-rel-motivo   { font-size:.78rem; color:#6c757d; margin-top:3px; }
        .siac-rel-duracao  { font-size:.72rem; color:#adb5bd; }
        .siac-rel-tab { padding:.35rem .85rem; font-size:.78rem; border:1px solid #dee2e6;
            border-radius:20px; background:#fff; color:#495057; cursor:pointer; }
        .siac-rel-tab.ativo { background:#0d3b66; color:#fff; border-color:#0d3b66; }
        .siac-ultima-badge { background:#f8f9fa; border:1px solid #dee2e6; border-radius:20px;
            padding:4px 12px; font-size:.72rem; cursor:pointer; color:#495057;
            display:inline-flex; align-items:center; gap:5px; white-space:nowrap; transition:background .15s; }
        .siac-ultima-badge:hover { background:#eef5ff; border-color:#b6d4fe; }
        .siac-ultima-badge.alerta { background:#fff3cd; border-color:#ffe69c; color:#664d03; }
        .siac-ultima-badge.erro   { background:#f8d7da; border-color:#f1aeb5; color:#842029; }
    </style>
</head>
<body>
<?php include APP_PATH . '/Views/header_atlas.php'; ?>

<div class="container-fluid mt-4">
    <div class="card">
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <h2 class="sistema-titulo mb-0">
                    <i class="bi bi-cash-stack"></i> Análise Orçamentária e Financeira
                </h2>
                <button type="button" class="btn-ajuda-criterios"
                        data-bs-toggle="popover" data-bs-trigger="focus" data-bs-html="true"
                        data-bs-placement="bottom" data-bs-custom-class="popover-criterios"
                        title="<i class='bi bi-info-circle'></i> Critérios do farol"
                        data-bs-content="<p class='mb-2'><strong>Farol de cobertura orçamentária:</strong><br>
                            <code>Cálculo mês a mês (Valor Disponível)</code></p>
                        <ul style='padding-left:18px;margin-bottom:8px'>
                            <li><strong style='color:#198754;'>🟢 VERDE</strong> — <b>mais de 3 meses</b> de cobertura</li>
                            <li><strong style='color:#b98900;'>🟡 AMARELO</strong> — <b>de 2 a 3 meses</b> de cobertura</li>
                            <li><strong style='color:#dc3545;'>🔴 VERMELHO</strong> — <b>somente 1 mês</b> de cobertura</li>
                        </ul>
                        <p class='mb-0'><strong>Valor Disponível</strong> = <code>Empenhado + RAP</code></p>">
                    ?
                </button>
                <span class="ultima-atualizacao">
                    <i class="bi bi-calendar3"></i> Exercício: <strong><?= $h($exercicio) ?></strong>
                </span>
            </div>
            <div class="d-flex gap-2 align-items-center flex-wrap">
                <?php if ($podeScraper): ?>
                <button type="button" id="btn-importar-siac" class="btn btn-outline-success btn-sm"
                        title="Importar medições mensais do SIAC (login necessário)">
                    <i class="bi bi-cloud-download"></i> Importar Medições SIAC
                </button>

                <span class="siac-ultima-badge" id="siac-ultima-extracao" hidden
                      title="Clique para abrir o relatório detalhado da última extração completa">
                    <i class="bi bi-clock-history"></i>
                    <span id="siac-ultima-texto">—</span>
                </span>

                <button type="button" id="btn-importar-dados" class="btn btn-outline-success btn-sm"
                        title="Importar dados cadastrais dos contratos do SIAC">
                    <i class="bi bi-file-earmark-text"></i> Importar Dados Contratos
                </button>
                <button type="button" id="btn-importar-empenhos" class="btn btn-outline-success btn-sm"
                        title="Importar empenhos + eventos do SIAC">
                    <i class="bi bi-receipt"></i> Importar Empenhos
                </button>
                <?php endif; ?>

                <button type="button" id="btn-atualizar-dataset47" class="btn btn-outline-primary btn-sm"
                        title="Sincronizar o Dataset 47 (Financeiro/Orçamentário ATLAS)">
                    <i class="bi bi-arrow-repeat"></i> Atualizar Dataset 47
                </button>

                <a href="analise_orc/ranking" class="btn btn-warning btn-sm fw-bold"
                   title="Ranking de contratos (farol, performance, medições, etc.)">
                    <i class="bi bi-trophy-fill"></i> Ranking
                </a>

                <button id="bell" class="bell-btn" title="Notificações" type="button">
                    <i class="bi bi-bell"></i>
                    <span id="bell-count" class="bell-badge" hidden>0</span>
                </button>
                <a href="home_atlas" target="_self" class="btn btn-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i> Voltar
                </a>
            </div>
        </div>

        <div id="filtros-aplicados" class="mb-3 p-2 bg-light rounded-3 d-none">
            <span class="fw-bold me-2"><i class="bi bi-funnel"></i> Filtros aplicados:</span>
            <span id="badges-container"></span>
        </div>

        <form id="filtrosForm" class="row g-2 mb-3 align-items-end" onsubmit="return false;">
            <div class="col-lg-3 col-md-4 col-sm-6 col-12 filtro-col">
                <label class="form-label" style="font-size:0.75rem;">Ação orçamentária</label>
                <select id="f-acao" class="form-select filtro-select2">
                    <option value="">Selecione...</option>
                    <?php foreach ($acoes as $a): ?>
                        <option value="<?= $h($a['id']) ?>"><?= $h($a['codigo'].' — '.$a['descricao']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-2 col-md-3 col-sm-6 col-12 filtro-col">
                <label class="form-label" style="font-size:0.75rem;">Tipo de contrato</label>
                <select id="f-tipo" class="form-select filtro-select2"><option value="">Todos</option></select>
            </div>
            <div class="col-lg-2 col-md-3 col-sm-6 col-12 filtro-col">
                <label class="form-label" style="font-size:0.75rem;">Superintendência</label>
                <select id="f-super" class="form-select filtro-select2"><option value="">Todas</option></select>
            </div>
            <div class="col-lg-2 col-md-3 col-sm-6 col-12 filtro-col">
                <label class="form-label" style="font-size:0.75rem;">Contrato</label>
                <select id="f-contrato" class="form-select filtro-select2"><option value="">Selecione...</option></select>
            </div>
            <div class="col-lg-1 col-md-2 col-sm-3 col-6 filtro-col">
                <label class="form-label" style="font-size:0.75rem;">Exercício</label>
                <select id="f-exercicio" class="form-select filtro-select2">
                    <option value="<?= $exercicio ?>" selected><?= $h($exercicio) ?></option>
                </select>
            </div>
            <div class="col-lg-2 col-md-3 col-sm-3 col-6 filtro-col d-flex align-items-end">
                <button type="button" id="btn-reset-filtros" class="btn btn-outline-secondary w-100">
                    <i class="bi bi-x-circle"></i> Limpar filtros
                </button>
            </div>
        </form>

        <div class="info-count mb-2">
            <i class="bi bi-list-ul"></i> <strong>Contratos:</strong>
            <span id="info-total">0</span>
            <span class="obs" id="info-pendentes"></span>
        </div>

        <div class="table-wrapper">
            <table class="table table-hover align-middle" id="tbl-contratos">
                <thead>
                    <tr>
                        <th>Contrato</th>
                        <th>Nome usual</th>
                        <th>BR</th>
                        <th>Empresa</th>
                        <th>Tipo</th><th>Superint.</th>
                        <th class="text-end">Total instrumento</th>
                        <th class="text-end">Valor Disponível</th>
                        <th class="text-end">Cobertura (meses)</th>
                        <th>Farol</th>
                        <th class="text-end">Desvio %</th>
                        <th>Alertas</th>
                    </tr>
                </thead>
                <tbody id="tbl-body">
                    <tr><td colspan="12" class="text-center text-muted py-4">
                        <span class="spinner-border spinner-border-sm"></span> Carregando contratos...
                    </td></tr>
                </tbody>
            </table>
        </div>

        <small class="text-muted mt-2 d-block">
            <span class="dot verde"></span> Cobertura &gt; 3 meses
            <span class="dot amarelo"></span> de 2 a 3 meses
            <span class="dot vermelho"></span> somente 1 mês
            <span class="dot alerta"></span> Sem planejamento completo
            <i class="bi bi-info-circle ms-2 text-secondary" style="cursor:help;font-size:.85rem"
               data-bs-toggle="tooltip" data-bs-html="true" data-bs-placement="top"
               title="<strong>Cobertura</strong> — quantidade de meses com valor disponível para liquidação.<br><em style='color:#6c757d'>Exibida em valor inteiro (ex.: 3,45 → 3).</em>"></i>
        </small>

        <div id="cards" class="row g-3 mt-4" hidden>
            <div class="col-6 col-md-4 col-lg-2">
                <div class="card-ind">
                    <div class="card-ind-label">
                        Dotação atualizada
                        <i class="bi bi-info-circle info-icon" id="card-dotacao-info"
                           data-bs-toggle="tooltip" data-bs-placement="top" data-bs-html="true"></i>
                    </div>
                    <div class="card-ind-value" id="c-dotacao">—</div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <div class="card-ind">
                    <div class="card-ind-label">
                        Total do instrumento
                        <i class="bi bi-info-circle info-icon" id="card-total-info"
                           data-bs-toggle="tooltip" data-bs-placement="top" data-bs-html="true"></i>
                    </div>
                    <div class="card-ind-value" id="c-total">—</div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <div class="card-ind">
                    <div class="card-ind-label">
                        Valor Disponível
                        <i class="bi bi-info-circle info-icon" id="card-disponivel-info"
                           data-bs-toggle="tooltip" data-bs-placement="top" data-bs-html="true"></i>
                    </div>
                    <div class="card-ind-value" id="c-disponivel">—</div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <div class="card-ind">
                    <div class="card-ind-label">
                        Necessário no ano
                        <i class="bi bi-info-circle info-icon" id="card-necessario-info"
                           data-bs-toggle="tooltip" data-bs-placement="top" data-bs-html="true"></i>
                    </div>
                    <div class="card-ind-value" id="c-necessario">—</div>
                </div>
            </div>
            <div class="col-12 col-md-8 col-lg-4">
                <div class="card-ind" style="align-items:flex-start;text-align:left">
                    <div class="card-ind-label">Farol de cobertura</div>
                    <div class="card-ind-value" id="c-farol">—</div>
                    <span id="c-cobertura" class="text-muted small"></span>
                    <div id="c-alerta-plan" class="mt-1" hidden></div>
                    <div class="mt-2 d-flex gap-2">
                        <button id="btn-calculo" class="btn btn-sm btn-outline-secondary" type="button">ℹ Ver cálculo</button>
                        <button id="btn-medicoes" class="btn btn-sm btn-outline-primary" type="button">📊 Medições</button>
                    </div>
                </div>
            </div>
        </div>

        <div id="chart-box" class="mt-4" hidden>
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0">Evolução mensal</h6>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="chart-acumulado">
                    <label class="form-check-label small" for="chart-acumulado">acumulado</label>
                </div>
            </div>
            <canvas id="chart" height="100"></canvas>
        </div>

        <div id="mensal-box" class="mt-4" hidden>
            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                <h6 class="mb-0">Detalhamento mensal</h6>
                <small class="text-muted">
                    <i class="bi bi-pencil-square"></i>
                    Clique no lápis para editar (com auditoria). O cabeçalho <b>Planejado (NPO)</b> ou
                    <b>Planejado Analista</b> define qual coluna entra no farol.
                </small>
            </div>
            <div id="aviso-saldo" class="alert mb-2" hidden></div>
            <div class="table-responsive">
                <table class="table table-sm tbl-compacta" id="tbl-mensal">
                    <thead><tr>
                        <th>Mês <i class="bi bi-info-circle th-info" data-bs-toggle="tooltip" data-bs-placement="top"
                               title="Período de referência (mês/ano)"></i></th>
                        <th class="text-end col-planejamento" id="th-col-npo" data-campo="NPO">
                            <span class="radio-col">⦿</span> Planejado (NPO)
                            <i class="bi bi-info-circle th-info" data-bs-toggle="tooltip" data-bs-placement="top"
                               title="Dataset 47 — coluna planejado_npo_&lt;mes&gt;_&lt;ano&gt;. Somente leitura."></i>
                        </th>
                        <th class="text-end">Estimativa
                            <i class="bi bi-info-circle th-info" data-bs-toggle="tooltip" data-bs-placement="top"
                               title="Média das últimas 3 medições <b>não zeradas</b>. Editável (vira MAN)."></i></th>
                        <th class="text-end col-planejamento" id="th-col-ana" data-campo="ANALISTA">
                            <span class="radio-col">○</span> Planejado Analista
                            <i class="bi bi-info-circle th-info" data-bs-toggle="tooltip" data-bs-placement="top"
                               title="Editável em todos os meses. Inicializa com a medição do mês ou a média não-zerada."></i>
                        </th>
                        <th class="text-end">Medido
                            <i class="bi bi-info-circle th-info" data-bs-toggle="tooltip" data-bs-placement="top"
                               title="Medição registrada no SIAC. 0,00 = medição real de R$ 0,00; — = sem medição."></i></th>
                        <th class="text-end">Liquidado
                            <i class="bi bi-info-circle th-info" data-bs-toggle="tooltip" data-bs-placement="top"
                               title="Dataset 47 — liquidado_&lt;mes&gt;_&lt;ano&gt;."></i></th>
                        <th>Data ref. medição</th>
                        <th class="text-end">Saldo medido não liq.
                            <i class="bi bi-info-circle th-info" data-bs-toggle="tooltip" data-bs-placement="top"
                               title="Diferença acumulada entre medido e liquidado."></i></th>
                    </tr></thead>
                    <tbody></tbody>
                    <tfoot id="tbl-mensal-tfoot"></tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODAL NOTIFICAÇÕES -->
<div class="modal fade" id="modal-ntf" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-bell"></i> Notificações</h6>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex gap-2 mb-2 flex-wrap">
                    <button class="btn btn-sm btn-outline-secondary tab-btn active" data-tab="nao-lidas">Não lidas</button>
                    <button class="btn btn-sm btn-outline-secondary tab-btn" data-tab="todas">Todas</button>
                    <button id="btn-marcar-todas" class="btn btn-sm btn-link ms-auto">Marcar todas como lidas</button>
                </div>
                <ul id="ntf-lista" class="ntf-lista"></ul>
            </div>
        </div>
    </div>
</div>

<!-- MODAL MEDIÇÕES -->
<div class="modal fade" id="modal-medicoes" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-graph-up"></i> Medições — <span id="med-titulo"></span></h6>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="med-stats" class="row g-3 mb-4"></div>
                <div id="med-sem-serie" class="alerta-estat mb-3" hidden></div>
                <canvas id="med-chart" height="160"></canvas>
                <p id="med-alerta" class="alerta-estat mt-4" hidden></p>
            </div>
        </div>
    </div>
</div>

<!-- MODAL VER CÁLCULO -->
<div class="modal fade" id="modal-calculo" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-calculator"></i> Memória de cálculo — <span id="calc-titulo"></span></h6>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="calculo-conteudo"></div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL EDITAR ESTIMATIVA / PLANEJADO ANALISTA -->
<div class="modal fade" id="modal-editar-est" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-pencil-square"></i> <span id="ed-titulo">Editar valor</span></h6>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2"><small class="text-muted">Mês: <strong id="ed-mes"></strong></small></div>
                <div class="mb-2"><small class="text-muted">Valor calculado original: <strong id="ed-calc">—</strong></small></div>
                <label class="form-label small">Novo valor (R$)</label>
                <input type="number" step="0.01" min="0" class="form-control" id="ed-valor">
                <label class="form-label small mt-2">
                    Justificativa <span class="text-danger">*</span>
                </label>
                <textarea class="form-control" id="ed-justificativa" rows="3"
                          placeholder="Explique o motivo da alteração (obrigatório, máx. 500 caracteres)"
                          maxlength="500"></textarea>
                <div id="ed-erro" class="text-danger small mt-1" hidden></div>
            </div>
            <div class="modal-footer justify-content-between">
                <button class="btn btn-outline-danger btn-sm" id="ed-reset">
                    <i class="bi bi-arrow-counterclockwise"></i> Restaurar cálculo
                </button>
                <div class="d-flex gap-2">
                    <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary btn-sm" id="ed-salvar">Salvar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($podeScraper): ?>
<!-- MODAL IMPORTAR SIAC -->
<div class="modal fade" id="modal-siac" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-cloud-download"></i> Importar Medições SIAC</h6>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="siac-step-1">
                    <div class="alert alert-info py-2 small mb-3">
                        <i class="bi bi-shield-lock"></i>
                        As credenciais <strong>não são salvas</strong>. São usadas apenas para esta execução e apagadas em seguida.
                    </div>
                    <div class="mb-2">
                        <small class="text-muted">Contratos disponíveis (Dataset 38)</small>
                        <div id="siac-total-contratos" class="fw-bold">—</div>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="siac-atualizar-ds38" checked>
                        <label class="form-check-label small" for="siac-atualizar-ds38">
                            Atualizar Dataset 38 antes de rodar
                        </label>
                    </div>
                    <div class="mb-2 p-2 rounded" style="background:#f0f9ff;border:1px dashed #0dcaf0">
                        <label class="form-label small mb-1">
                            <strong>Contrato único</strong> <span class="text-muted">(opcional)</span>
                        </label>
                        <input type="text" class="form-control form-control-sm" id="siac-contrato-unico"
                               placeholder="Ex.: 00 00245/2025 — deixe vazio para todos"
                               autocomplete="off">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Usuário SIAC</label>
                        <input type="text" class="form-control form-control-sm" id="siac-usuario"
                               placeholder="CPF somente números" inputmode="numeric" autocomplete="off">
                        <small class="text-muted d-block mt-1" style="font-size:.72rem">
                            <i class="bi bi-info-circle"></i> <strong>CPF somente números</strong> — sem pontos, traços ou espaços.
                        </small>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Senha SIAC</label>
                        <input type="password" class="form-control form-control-sm" id="siac-senha" autocomplete="new-password">
                    </div>
                    <div id="siac-erro" class="text-danger small" hidden></div>
                </div>

                <div id="siac-step-2" hidden>
                    <div class="alert alert-info py-2 small mb-2">
                        <i class="bi bi-window"></i>
                        <strong>Pode fechar esta janela.</strong> A importação continua em segundo plano no servidor.
                    </div>
                    <div id="siac-login-status" class="small mb-2"></div>
                    <div class="d-flex justify-content-between mb-1">
                        <small class="text-muted" id="siac-etapa-label">Iniciando…</small>
                        <small class="text-muted" id="siac-progresso-pct">0%</small>
                    </div>
                    <div class="progress" style="height:14px">
                        <div class="progress-bar progress-bar-striped progress-bar-animated"
                             id="siac-progresso-bar" style="width:0%"></div>
                    </div>
                    <div class="mt-2 small text-muted" id="siac-progresso-info"></div>
                </div>

                <div id="siac-step-3" hidden><div id="siac-resultado"></div></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm"
                        id="siac-btn-cancelar">Cancelar</button>
                <button type="button" class="btn btn-success btn-sm" id="siac-btn-executar">
                    <i class="bi bi-play-fill"></i> Executar importação
                </button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL RELATÓRIO SIAC -->
<div class="modal fade" id="modal-siac-relatorio" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h6 class="modal-title mb-0">
                        <i class="bi bi-file-earmark-text"></i> Relatório da importação SIAC
                    </h6>
                    <small class="text-muted" id="siac-rel-header">—</small>
                </div>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="siac-rel-aviso" hidden></div>
                <div class="row g-2 mb-3" id="siac-rel-cards">
                    <div class="col-6 col-md-3">
                        <div class="card-ind" style="min-height:80px;padding:12px 10px">
                            <div class="card-ind-label" style="color:#198754"><i class="bi bi-check-circle-fill"></i> OK</div>
                            <div class="card-ind-value" id="siac-rel-ok" style="color:#198754">0</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card-ind" style="min-height:80px;padding:12px 10px">
                            <div class="card-ind-label" style="color:#b98900"><i class="bi bi-dash-circle-fill"></i> IGNORADOS</div>
                            <div class="card-ind-value" id="siac-rel-ignorado" style="color:#b98900">0</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card-ind" style="min-height:80px;padding:12px 10px">
                            <div class="card-ind-label" style="color:#dc3545"><i class="bi bi-x-circle-fill"></i> ERROS</div>
                            <div class="card-ind-value" id="siac-rel-erro" style="color:#dc3545">0</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card-ind" style="min-height:80px;padding:12px 10px">
                            <div class="card-ind-label"><i class="bi bi-database-check"></i> MEDIÇÕES</div>
                            <div class="card-ind-value" id="siac-rel-medicoes">0</div>
                        </div>
                    </div>
                </div>
                <div class="d-flex gap-2 mb-3 flex-wrap align-items-center">
                    <button class="siac-rel-tab ativo" data-filtro="todos">Todos (<span id="siac-rel-count-todos">0</span>)</button>
                    <button class="siac-rel-tab" data-filtro="OK">OK (<span id="siac-rel-count-ok">0</span>)</button>
                    <button class="siac-rel-tab" data-filtro="IGNORADO">Ignorados (<span id="siac-rel-count-ign">0</span>)</button>
                    <button class="siac-rel-tab" data-filtro="ERRO">Erros (<span id="siac-rel-count-err">0</span>)</button>
                    <input type="text" id="siac-rel-busca" class="form-control form-control-sm ms-auto"
                           placeholder="Buscar contrato..." style="max-width:220px">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="siac-rel-csv">
                        <i class="bi bi-download"></i> CSV
                    </button>
                </div>
                <div id="siac-rel-lista"
                     style="background:#fff;border:1px solid #e9ecef;border-radius:8px;max-height:420px;overflow-y:auto">
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL IMPORTAR (Dados Contratos / Empenhos) -->
<div class="modal fade" id="modal-importer" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">
                    <i class="bi bi-cloud-download"></i> <span id="imp-titulo">Importar</span>
                </h6>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="imp-step-1">
                    <div class="alert alert-info py-2 small mb-3">
                        <i class="bi bi-shield-lock"></i>
                        As credenciais <strong>não são salvas</strong>. São usadas apenas para esta execução.
                    </div>
                    <div class="mb-2">
                        <small class="text-muted">Contratos disponíveis (Dataset 38)</small>
                        <div id="imp-total-contratos" class="fw-bold">—</div>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="imp-atualizar-ds38" checked>
                        <label class="form-check-label small" for="imp-atualizar-ds38">
                            Atualizar Dataset 38 antes de rodar
                        </label>
                    </div>
                    <div class="mb-2 p-2 rounded" style="background:#f0f9ff;border:1px dashed #0dcaf0">
                        <label class="form-label small mb-1">
                            <strong>Contrato único</strong> <span class="text-muted">(opcional)</span>
                        </label>
                        <input type="text" class="form-control form-control-sm" id="imp-contrato-unico"
                               placeholder="Ex.: 00 00245/2025 — deixe vazio para todos"
                               autocomplete="off">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Usuário SIAC</label>
                        <input type="text" class="form-control form-control-sm" id="imp-usuario"
                               placeholder="CPF somente números" inputmode="numeric" autocomplete="off">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Senha SIAC</label>
                        <input type="password" class="form-control form-control-sm" id="imp-senha"
                               autocomplete="new-password">
                    </div>
                    <div id="imp-erro" class="text-danger small" hidden></div>
                </div>
                <div id="imp-step-2" hidden>
                    <div class="alert alert-info py-2 small mb-2">
                        <i class="bi bi-window"></i>
                        <strong>Pode fechar esta janela.</strong> A importação continua em segundo plano.
                    </div>
                    <div id="imp-login-status" class="small mb-2"></div>
                    <div class="d-flex justify-content-between mb-1">
                        <small class="text-muted" id="imp-etapa-label">Iniciando…</small>
                        <small class="text-muted" id="imp-progresso-pct">0%</small>
                    </div>
                    <div class="progress" style="height:14px">
                        <div class="progress-bar progress-bar-striped progress-bar-animated"
                             id="imp-progresso-bar" style="width:0%"></div>
                    </div>
                    <div class="mt-2 small text-muted" id="imp-progresso-info"></div>
                </div>
                <div id="imp-step-3" hidden><div id="imp-resultado"></div></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"
                        id="imp-btn-cancelar">Cancelar</button>
                <button type="button" class="btn btn-success btn-sm" id="imp-btn-executar">
                    <i class="bi bi-play-fill"></i> Executar importação
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const API = 'analise_orc';
const EXERCICIO_INICIAL = <?= (int)$exercicio ?>;
const PODE_SCRAPER = <?= $podeScraper ? 'true' : 'false' ?>;
const MESES_ABREV = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];

const SELECT2_PTBR = {
    errorLoading: () => 'Não foi possível carregar os resultados',
    inputTooLong: (a) => 'Apague ' + (a.input.length - a.maximum) + ' caractere(s)',
    inputTooShort: (a) => 'Digite ' + (a.minimum - a.input.length) + ' ou mais caractere(s)',
    loadingMore: () => 'Carregando mais resultados…',
    maximumSelected: (a) => 'Só é possível selecionar ' + a.maximum + ' item(ns)',
    noResults: () => 'Nenhum resultado encontrado',
    searching: () => 'Buscando…',
    removeAllItems: () => 'Remover todos',
    removeItem: () => 'Remover item',
    search: () => 'Buscar'
};

const $el = (s) => document.querySelector(s);
const brl = (v) => (v == null ? '—' : Number(v).toLocaleString('pt-BR', { style:'currency', currency:'BRL' }));
const pct = (v) => (v == null ? '—' : Number(v).toLocaleString('pt-BR', { minimumFractionDigits:2 }) + '%');
const dt  = (v) => v ? new Date(v + 'T00:00:00').toLocaleDateString('pt-BR') : '—';
const dtMesAno = (v) => {
    if (!v) return '—';
    const d = new Date(v + 'T00:00:00');
    if (isNaN(d.getTime())) return '—';
    const m = d.toLocaleDateString('pt-BR', { month:'short' }).replace('.', '').toUpperCase();
    return `${m}/${d.getFullYear()}`;
};
const dtCurto = (v) => {
    if (!v) return '';
    const d = new Date(v + 'T00:00:00');
    if (isNaN(d.getTime())) return '';
    const m = d.toLocaleDateString('pt-BR', { month:'short' }).replace('.', '').toUpperCase();
    return `${m}/${String(d.getFullYear()).slice(-2)}`;
};
const dtMesNome = (v) => {
    if (!v) return '';
    const d = new Date(v + 'T00:00:00');
    if (isNaN(d.getTime())) return '';
    return d.toLocaleDateString('pt-BR', { month:'long' }).replace(/^\w/, c => c.toUpperCase());
};
function cssEscape(s) {
    if (window.CSS && CSS.escape) return CSS.escape(s);
    return String(s).replace(/["\\]/g, '\\$&');
}
function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
function setarTooltip(seletor, html) {
    const el = $el(seletor);
    if (!el) return;
    const old = bootstrap.Tooltip.getInstance(el);
    if (old) old.dispose();
    el.setAttribute('title', html);
    new bootstrap.Tooltip(el, { html: true, sanitize: false, placement: 'top' });
}
function fmtDuracao(seg) {
    seg = Number(seg) || 0;
    if (seg < 60) return seg + 's';
    const m = Math.floor(seg / 60);
    const s = seg % 60;
    return m + 'm ' + s + 's';
}
function fmtDataHora(iso) {
    if (!iso) return '—';
    try {
        const d = new Date(iso.replace(' ', 'T'));
        if (isNaN(d.getTime())) return iso;
        return d.toLocaleString('pt-BR');
    } catch(e) { return iso; }
}

// ================================================================
// AUTENTICAÇÃO
// ================================================================
let _redirecionandoLogin = false;
function _verificarSessao(resp) {
    if (resp.status === 401) {
        if (_redirecionandoLogin) return true;
        _redirecionandoLogin = true;
        alert('Sua sessão expirou. Faça login novamente.');
        const back = encodeURIComponent(window.location.pathname + window.location.search);
        window.location.href = 'login?redirect=' + back;
        return true;
    }
    return false;
}
async function fetchAuth(url, opts = {}) {
    const resp = await fetch(url, opts);
    if (_verificarSessao(resp)) throw new Error('Sessão expirada');
    return resp;
}
async function apiGet(path) {
    const resp = await fetchAuth(`${API}/${path}`, { headers: { 'X-Requested-With':'XMLHttpRequest' } });
    const ct = resp.headers.get('Content-Type') || '';
    if (!ct.includes('application/json')) {
        console.error(`[${API}/${path}] não-JSON (HTTP ${resp.status})`, await resp.text());
        throw new Error(`Resposta inválida do servidor (HTTP ${resp.status})`);
    }
    return await resp.json();
}
async function fetchJsonPost(path, formData) {
    const resp = await fetchAuth(`${API}/${path}`, { method:'POST', body: formData });
    const ct   = (resp.headers.get('Content-Type') || '').toLowerCase();
    const text = await resp.text();

    if (!resp.ok || !ct.includes('application/json')) {
        console.error(`[POST ${API}/${path}] HTTP ${resp.status} · CT=${ct}`, text.substring(0, 800));
        let msgExtra = '';
        const mTitle = text.match(/<title>([^<]*)<\/title>/i);
        const mErr   = text.match(/(Fatal error|Parse error|Warning|Notice)[^<]{0,200}/i);
        if (mErr)   msgExtra = ' · ' + mErr[0].substring(0, 200);
        else if (mTitle) msgExtra = ' · ' + mTitle[1].substring(0, 100);
        throw new Error(
            `Resposta inválida (HTTP ${resp.status}). ` +
            `Verifique se a rota "${API}/${path}" está registrada.${msgExtra}`
        );
    }
    try {
        return JSON.parse(text.replace(/^\uFEFF/, ''));
    } catch (e) {
        console.error(`[POST ${API}/${path}] JSON inválido:`, text.substring(0, 500));
        throw new Error('Servidor retornou JSON inválido.');
    }
}

// ================================================================
// HELPERS DE TOOLTIP
// ================================================================
function ttTitulo(icone, titulo) {
    return `<div style="font-weight:700;color:#0d3b66;font-size:.92rem;margin-bottom:12px;padding-bottom:8px;border-bottom:2px solid #e9ecef">
        <i class="bi ${icone}"></i> ${titulo}
    </div>`;
}
function ttSecao(titulo, conteudo) {
    return `<div style="margin-bottom:12px">
        <div style="font-size:.68rem;text-transform:uppercase;color:#6c757d;letter-spacing:.05em;font-weight:700;margin-bottom:5px">${titulo}</div>
        <div style="font-size:.82rem;line-height:1.5;color:#212529">${conteudo}</div>
    </div>`;
}
function ttCodigo(html) {
    return `<code style="background:#eef5ff;padding:6px 10px;border-radius:6px;display:inline-block;font-size:.82rem;color:#0d3b66;font-family:Consolas,monospace;max-width:100%;word-break:break-word;line-height:1.5">${html}</code>`;
}
function ttTabelaValores(linhas) {
    let html = '<table style="width:100%;font-size:.8rem;line-height:1.4;border-collapse:collapse">';
    linhas.forEach(l => {
        html += `<tr>
            <td style="color:#495057;padding:5px 0;vertical-align:top;width:60%">
                ${l.label}
                ${l.explicacao ? `<div style="font-size:.72rem;color:#6c757d;padding-left:10px;margin-top:2px">↳ ${l.explicacao}</div>` : ''}
            </td>
            <td style="text-align:right;font-weight:600;color:#1f2d3d;white-space:nowrap;padding-left:8px;vertical-align:top;width:40%">${l.valor}</td>
        </tr>`;
    });
    html += '</table>';
    return html;
}
function ttResultado(rotulo, valor, cor) {
    return `<div style="border-top:2px solid #dee2e6;padding-top:10px;margin-top:8px">
        <div style="font-size:.7rem;text-transform:uppercase;color:#6c757d;letter-spacing:.05em;font-weight:700;margin-bottom:4px">${rotulo}</div>
        <div style="font-size:1.2rem;font-weight:800;color:${cor || '#0d3b66'}">${valor}</div>
    </div>`;
}

// ================================================================
// ESTADO
// ================================================================
let chartMain = null;
let chartMed  = null;
let estadoAtual = { acaoId:'', tipo:'', super:'', contrato:'', exercicio:EXERCICIO_INICIAL };
let editCtx = { contrato:'', mes:'', valorCalculado:0, mode:'estimado' };
let contratosCache = [];
let cacheStatus = 'idle';
let cacheLoadingPromise = null;
let suppressChange = false;

function calcularNecessarioNoAno(c) {
    const serie = c.serie_mensal || [];
    const exercicio = estadoAtual.exercicio;
    const hojeMes = new Date().toISOString().slice(0, 7) + '-01';
    const campoPref = (c.campo_planejamento || 'NPO').toUpperCase();

    const comMed = serie
        .filter(r => r.medido != null && r.mes <= hojeMes)
        .sort((a, b) => String(a.mes).localeCompare(String(b.mes)));
    const ultimaMed = comMed.length ? comMed[comMed.length - 1] : null;

    const ultimas3 = comMed.slice(-3);
    const media = ultimas3.length
        ? ultimas3.reduce((s, r) => s + Number(r.medido), 0) / ultimas3.length
        : 0;

    const totalMedido = serie.reduce((s, r) => s + Number(r.medido || 0), 0);
    const totalLiq    = serie.reduce((s, r) => s + Number(r.liquidado || 0), 0);
    const pendente    = Math.max(0, totalMedido - totalLiq);

    const startAfter = ultimaMed ? ultimaMed.mes : null;
    const dez = `${exercicio}-12-01`;
    const mesesProjetados = [];

    for (const r of serie) {
        if (startAfter && r.mes <= startAfter) continue;
        if (r.mes > dez) break;

        const planNpo = r.planejado          != null ? Number(r.planejado)          : null;
        const planAna = r.planejado_analista != null ? Number(r.planejado_analista) : null;
        const medMes  = r.medido             != null ? Number(r.medido)             : null;
        const estExib = r.estimado           != null ? Number(r.estimado)           : null;

        let valor = null, fonte = '';

        if (campoPref === 'ANALISTA' && planAna != null) {
            valor = planAna; fonte = 'planejado_analista';
        } else if (planNpo != null && planNpo > 0) {
            valor = planNpo; fonte = 'planejado_npo';
        } else if (medMes != null) {
            valor = medMes; fonte = 'medido';
        } else if (estExib != null) {
            valor = estExib;
            fonte = r.estimado_manual ? 'estimado_manual' : 'estimado_calc';
        }

        if (valor == null) continue;
        mesesProjetados.push({ mes: r.mes, valor, fonte });
    }
    const totalProjetado = mesesProjetados.reduce((s, x) => s + x.valor, 0);

    return {
        media,
        ultimaMedMes: ultimaMed ? ultimaMed.mes : null,
        startAfter,
        mesesProjetados,
        totalProjetado,
        pendente,
        necessario: totalProjetado + pendente,
        ultimas3Medicoes: ultimas3.map(r => ({ mes: r.mes, valor: Number(r.medido) })),
        totalMedido,
        totalLiq,
        campoPreferencia: campoPref,
    };
}

function extrairMediaDataset47(c, calculo) {
    calculo = calculo || c.calculo_previsao || {};
    return (
        calculo.media_dataset_47 ??
        calculo.media_ds47 ??
        calculo.media_dataset ??
        calculo.media_ultimas_3_medicoes ??
        c.media_dataset_47 ??
        c.media_ultimas_3_medicoes ??
        null
    );
}

// ================================================================
// INICIALIZAÇÃO
// ================================================================
$(function () {
    $.fn.select2.defaults.set('language', SELECT2_PTBR);
    $('.filtro-select2').select2({ placeholder: 'Todos', allowClear: true, width: '100%' });
    $('#f-acao, #f-tipo, #f-super, #f-contrato, #f-exercicio').on('select2:open', function () {
        setTimeout(() => {
            const f = document.querySelector('.select2-container--open .select2-search__field');
            if (f) f.focus();
        }, 30);
    });
    document.querySelectorAll('[data-bs-toggle="popover"]').forEach(el => new bootstrap.Popover(el, { container:'body', sanitize:false }));
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el, { html: true }));

    $('#f-acao').on('change', function () { if (suppressChange) return; estadoAtual.acaoId = this.value || ''; onFilterChanged('acao'); });
    $('#f-tipo').on('change', function () { if (suppressChange) return; estadoAtual.tipo = this.value || ''; onFilterChanged('tipo'); });
    $('#f-super').on('change', function () { if (suppressChange) return; estadoAtual.super = this.value || ''; onFilterChanged('super'); });
    $('#f-contrato').on('change', function () { if (suppressChange) return; estadoAtual.contrato = this.value || ''; onFilterChanged('contrato'); });
    $('#f-exercicio').on('change', function () {
        if (suppressChange) return;
        estadoAtual.exercicio = parseInt(this.value, 10) || EXERCICIO_INICIAL;
        cacheStatus = 'idle'; contratosCache = []; cacheLoadingPromise = null;
        carregarBaseEAtualizar();
    });

    atualizarBadges();
});

// ================================================================
// CARGA / CACHE
// ================================================================
async function carregarCacheContratos() {
    if (cacheStatus === 'loaded') return contratosCache;
    if (cacheLoadingPromise) return cacheLoadingPromise;
    cacheStatus = 'loading';

    cacheLoadingPromise = (async () => {
        try {
            const lista = await apiGet(`contratos?exercicio=${estadoAtual.exercicio}`);
            if (Array.isArray(lista) && lista.length > 0) {
                contratosCache = lista; cacheStatus = 'loaded'; return contratosCache;
            }
        } catch (e) { console.warn('[cache] bulk falhou:', e.message); }

        const acoesIds = Array.from(document.querySelectorAll('#f-acao option')).map(o => o.value).filter(Boolean);
        if (!acoesIds.length) { cacheStatus = 'error'; return []; }

        const results = await Promise.all(acoesIds.map(async (id) => {
            try {
                const list = await apiGet(`contratos?acao_id=${encodeURIComponent(id)}&exercicio=${estadoAtual.exercicio}`);
                return Array.isArray(list) ? list.map(c => ({
                    ...c, acao_id: c.acao_id ?? c.acaoId ?? c.acao_principal ?? c.acao_principal_id ?? id
                })) : [];
            } catch (e) { return []; }
        }));

        const map = new Map();
        for (const list of results) for (const c of list) if (c.numero && !map.has(c.numero)) map.set(c.numero, c);
        contratosCache = Array.from(map.values());
        cacheStatus = contratosCache.length > 0 ? 'loaded' : 'error';
        return contratosCache;
    })();

    try { return await cacheLoadingPromise; }
    finally { cacheLoadingPromise = null; }
}

async function carregarBaseEAtualizar() {
    mostrarCarregandoTabela();
    await carregarCacheContratos();
    popularSelects();
    aplicarFiltrosTabela();
}

function popularSelects() {
    const vTipo = estadoAtual.tipo, vSuper = estadoAtual.super, vContrato = estadoAtual.contrato;

    const tipos = [...new Set(contratosCache.map(c => c.tipo).filter(Boolean))].sort();
    const selTipo = $el('#f-tipo');
    selTipo.innerHTML = '<option value="">Todos</option>';
    tipos.forEach(t => selTipo.appendChild(new Option(t, t)));

    const supers = [...new Set(contratosCache.map(c => c.superintendencia).filter(Boolean))].sort();
    const selSuper = $el('#f-super');
    selSuper.innerHTML = '<option value="">Todas</option>';
    supers.forEach(s => selSuper.appendChild(new Option(s, s)));

    const selContrato = $el('#f-contrato');
    selContrato.innerHTML = '<option value="">Selecione...</option>';
    contratosCache.slice().sort((a,b) => String(a.numero).localeCompare(String(b.numero),'pt-BR')).forEach(c => {
        const label = `${c.numero}${c.tipo ? ' (' + c.tipo + ')' : ''}`;
        selContrato.appendChild(new Option(label, c.numero));
    });

    suppressChange = true;
    $('#f-tipo').val(vTipo || '').trigger('change.select2');
    $('#f-super').val(vSuper || '').trigger('change.select2');
    $('#f-contrato').val(vContrato || '').trigger('change.select2');
    suppressChange = false;
}

function mostrarCarregandoTabela() {
    $el('#tbl-body').innerHTML = `<tr><td colspan="12" class="text-center text-muted py-4">
        <span class="spinner-border spinner-border-sm"></span> Carregando contratos...
    </td></tr>`;
    $el('#info-total').textContent = '0';
    $el('#info-pendentes').innerHTML = '';
}

function aplicarFiltrosTabela() {
    if (cacheStatus === 'loading') { mostrarCarregandoTabela(); return; }
    if (cacheStatus === 'error' || !contratosCache.length) {
        $el('#tbl-body').innerHTML = `<tr><td colspan="12" class="text-center text-danger py-4">
            <i class="bi bi-exclamation-triangle-fill"></i> Nenhum contrato pôde ser carregado.
        </td></tr>`;
        return;
    }

    const semFiltro = !estadoAtual.acaoId && !estadoAtual.tipo && !estadoAtual.super && !estadoAtual.contrato;
    if (semFiltro) {
        $el('#tbl-body').innerHTML = `<tr><td colspan="12" class="text-center text-muted py-4">
            <i class="bi bi-funnel"></i> Escolha um filtro acima (ou busque um contrato) para ver os contratos.
        </td></tr>`;
        $el('#info-total').textContent = '0';
        $el('#info-pendentes').innerHTML = '';
        return;
    }

    const filtrados = contratosCache.filter(c => matchContratoFiltros(c));
    renderTabelaContratos(filtrados);
    $el('#info-total').textContent = String(filtrados.length);
    const pendentes = filtrados.filter(c => c.alerta_planejamento).length;
    $el('#info-pendentes').innerHTML = pendentes > 0
        ? `<span class="text-warning">⚠ ${pendentes} sem planejamento completo</span>` : '';
}

function matchContratoFiltros(c) {
    if (estadoAtual.acaoId) {
        const a = extrairAcaoId(c);
        if (a != null && String(a) !== String(estadoAtual.acaoId)) return false;
    }
    if (estadoAtual.tipo && c.tipo !== estadoAtual.tipo) return false;
    if (estadoAtual.super && c.superintendencia !== estadoAtual.super) return false;
    if (estadoAtual.contrato && c.numero !== estadoAtual.contrato) return false;
    return true;
}
function extrairAcaoId(c) { return c ? (c.acao_id ?? c.acaoId ?? c.acao_principal ?? c.acao_principal_id ?? null) : null; }

async function onFilterChanged(which) {
    if (which === 'contrato' && estadoAtual.contrato) await autoFillFromContrato(estadoAtual.contrato);
    if (which !== 'contrato' && estadoAtual.contrato) {
        const c = contratosCache.find(x => x.numero === estadoAtual.contrato);
        if (c && !matchContratoFiltros(c)) {
            estadoAtual.contrato = '';
            suppressChange = true;
            $('#f-contrato').val(null).trigger('change.select2');
            suppressChange = false;
            $el('#cards').hidden = true;
            $el('#chart-box').hidden = true;
            $el('#mensal-box').hidden = true;
        }
    }
    atualizarBadges();
    aplicarFiltrosTabela();
    if (which === 'contrato' && estadoAtual.contrato) await carregarDetalhe(estadoAtual.contrato);
    else if (!estadoAtual.contrato) {
        $el('#cards').hidden = true;
        $el('#chart-box').hidden = true;
        $el('#mensal-box').hidden = true;
    }
}

async function autoFillFromContrato(numero) {
    const c = contratosCache.find(x => x.numero === numero);
    if (!c) return;
    suppressChange = true;
    const a = extrairAcaoId(c);
    if (a != null) {
        const selAcao = $el('#f-acao');
        if (!selAcao.querySelector(`option[value="${cssEscape(a)}"]`)) selAcao.appendChild(new Option(String(a), String(a)));
        estadoAtual.acaoId = String(a);
        $('#f-acao').val(String(a)).trigger('change.select2');
    }
    if (c.tipo) {
        const selTipo = $el('#f-tipo');
        if (!selTipo.querySelector(`option[value="${cssEscape(c.tipo)}"]`)) selTipo.appendChild(new Option(c.tipo, c.tipo));
        estadoAtual.tipo = c.tipo;
        $('#f-tipo').val(c.tipo).trigger('change.select2');
    }
    if (c.superintendencia) {
        const selSuper = $el('#f-super');
        if (!selSuper.querySelector(`option[value="${cssEscape(c.superintendencia)}"]`)) selSuper.appendChild(new Option(c.superintendencia, c.superintendencia));
        estadoAtual.super = c.superintendencia;
        $('#f-super').val(c.superintendencia).trigger('change.select2');
    }
    suppressChange = false;
}

async function carregarDetalhe(numero) {
    try {
        const data = await apiGet(`detalhe?numero=${encodeURIComponent(numero)}&exercicio=${estadoAtual.exercicio}`);
        if (data && data.erro) { alert(data.erro); return; }
        renderDetalhe(data);
    } catch (err) { alert('Erro ao carregar detalhe: ' + err.message); }
}

function resetTudo() {
    estadoAtual = { acaoId:'', tipo:'', super:'', contrato:'', exercicio:EXERCICIO_INICIAL };
    suppressChange = true;
    $('#f-acao').val(null).trigger('change.select2');
    $('#f-tipo').val(null).trigger('change.select2');
    $('#f-super').val(null).trigger('change.select2');
    $('#f-contrato').val(null).trigger('change.select2');
    $el('#f-exercicio').value = String(EXERCICIO_INICIAL);
    suppressChange = false;
    atualizarBadges();
    aplicarFiltrosTabela();
    $el('#cards').hidden = true;
    $el('#chart-box').hidden = true;
    $el('#mensal-box').hidden = true;
}
$el('#btn-reset-filtros').addEventListener('click', resetTudo);

// ================================================================
// TABELA DE CONTRATOS — com Nome usual / BR / Empresa
// ================================================================
function renderTabelaContratos(lista) {
    const tb = $el('#tbl-body');
    tb.innerHTML = '';
    lista.forEach(c => {
        const tr = document.createElement('tr');
        if (c.alerta_planejamento) tr.classList.add('alerta-linha');
        const farolHtml = c.farol ? `<span class="farol-badge farol-${c.farol}">${c.farol}</span>` : `<span class="farol-badge farol-SEM">—</span>`;
        const cobertura = c.cobertura_meses != null ? c.cobertura_meses
            : (c.status_medicao === 'SEM_MEDICAO' ? '<span class="text-muted small">sem medição</span>' : '—');
        const desvio = c.performance?.diferenca_pct;
        let alertasHtml = '—';
        if (c.alerta_planejamento) {
            const faltando = (c.meses_faltantes || []).map(dtCurto).join(', ');
            alertasHtml = faltando ? `<span class="text-warning">⚠ não planejado: ${faltando}</span>` : '<span class="text-warning">⚠ planejamento incompleto</span>';
        }

        // NOVO: nome usual, BR e empresa (vindos do DS38)
        const nomeUsual = c.nome_usual || c.nome_empreendimento || '';
        const brRod     = c.br || '';
        const empresaTx = c.empresa || c.empresa_construtora || '';

        const nomeCell = nomeUsual
            ? `<span title="${escapeHtml(nomeUsual)}">${escapeHtml(nomeUsual)}</span>`
            : '<span class="text-muted">—</span>';
        const brCell   = brRod
            ? `<span class="badge bg-light text-dark border">${escapeHtml(brRod)}</span>`
            : '<span class="text-muted">—</span>';
        const empCell  = empresaTx
            ? `<span title="${escapeHtml(empresaTx)}">${escapeHtml(empresaTx)}</span>`
            : '<span class="text-muted">—</span>';

        tr.innerHTML = `
            <td><strong>${escapeHtml(c.numero)}</strong></td>
            <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${nomeCell}</td>
            <td>${brCell}</td>
            <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${empCell}</td>
            <td>${c.tipo ?? '—'}</td>
            <td>${c.superintendencia ?? '—'}</td>
            <td class="text-end">${brl(c.total_instrumento)}</td>
            <td class="text-end">${brl(c.disponivel_real)}</td>
            <td class="text-end">${cobertura}</td>
            <td>${farolHtml}</td>
            <td class="text-end">${pct(desvio)}</td>
            <td>${alertasHtml}</td>`;
        tr.addEventListener('click', () => {
            suppressChange = true;
            $('#f-contrato').val(c.numero).trigger('change.select2');
            suppressChange = false;
            estadoAtual.contrato = c.numero;
            onFilterChanged('contrato');
        });
        tb.appendChild(tr);
    });
}

// ================================================================
// BADGES DE FILTRO
// ================================================================
function atualizarBadges() {
    const wrap = $el('#filtros-aplicados'), cont = $el('#badges-container');
    cont.innerHTML = '';
    const labels = [];
    if (estadoAtual.acaoId) {
        const opt = $el('#f-acao').querySelector(`option[value="${cssEscape(estadoAtual.acaoId)}"]`);
        if (opt) labels.push({ k:'acao', label:'Ação', valor: opt.textContent });
    }
    if (estadoAtual.tipo)  labels.push({ k:'tipo', label:'Tipo', valor: estadoAtual.tipo });
    if (estadoAtual.super) labels.push({ k:'super', label:'Superintendência', valor: estadoAtual.super });
    if (estadoAtual.contrato) labels.push({ k:'contrato', label:'Contrato', valor: estadoAtual.contrato });

    if (!labels.length) { wrap.classList.add('d-none'); return; }
    wrap.classList.remove('d-none');

    labels.forEach(item => {
        const span = document.createElement('span');
        span.className = 'badge-filtro';
        span.innerHTML = `${item.label}: ${item.valor} <span class="remover-filtro" data-key="${item.k}" title="Remover"><i class="bi bi-x-circle"></i></span>`;
        cont.appendChild(span);
    });

    cont.querySelectorAll('.remover-filtro').forEach(btn => {
        btn.addEventListener('click', () => {
            const key = btn.dataset.key;
            suppressChange = true;
            if (key === 'acao')     { estadoAtual.acaoId='';    $('#f-acao').val(null).trigger('change.select2'); }
            if (key === 'tipo')     { estadoAtual.tipo='';      $('#f-tipo').val(null).trigger('change.select2'); }
            if (key === 'super')    { estadoAtual.super='';     $('#f-super').val(null).trigger('change.select2'); }
            if (key === 'contrato') { estadoAtual.contrato='';  $('#f-contrato').val(null).trigger('change.select2'); }
            suppressChange = false;
            if (key === 'contrato') {
                $el('#cards').hidden = true;
                $el('#chart-box').hidden = true;
                $el('#mensal-box').hidden = true;
            }
            atualizarBadges();
            aplicarFiltrosTabela();
        });
    });
}

// ================================================================
// DATASET 47
// ================================================================
$el('#btn-atualizar-dataset47').addEventListener('click', async () => {
    if (!confirm('Sincronizar o Dataset 47 agora?\nA base atual será substituída.')) return;
    const btn = $el('#btn-atualizar-dataset47');
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Sincronizando...';
    try {
        const resp = await fetchAuth('importar_supra_dataset_47_financeiro_orcamentario_atlas.php?ajax=1',
            { headers: { 'X-Requested-With': 'XMLHttpRequest' }, redirect: 'manual' });
        if (resp.type === 'opaqueredirect' || resp.status === 0 || resp.redirected)
            throw new Error('Servidor redirecionou a requisição.');
        const ct = resp.headers.get('Content-Type') || '';
        if (!ct.includes('application/json')) throw new Error(`Resposta inválida (HTTP ${resp.status})`);
        const r = await resp.json();
        if (!r.success) throw new Error(r.error || 'Erro desconhecido');
        toast(`Dataset 47 atualizado! ${r.inseridos} registro(s).`, 'success');
        cacheStatus = 'idle'; contratosCache = []; cacheLoadingPromise = null;
        await carregarBaseEAtualizar();
        if (estadoAtual.contrato) await carregarDetalhe(estadoAtual.contrato);
        atualizarSino();
    } catch (e) {
        alert('Erro ao importar: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = original;
    }
});

function toast(msg, tipo = 'success') {
    const t = document.createElement('div');
    t.className = 'position-fixed bottom-0 start-50 translate-middle-x mb-3 px-4 py-2 rounded-pill shadow';
    t.style.cssText = 'z-index:9999;background:' + (tipo === 'success' ? '#198754' : '#dc3545') + ';color:#fff;font-weight:600;';
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 4000);
}

// ================================================================
// SIAC — estado e helpers
// ================================================================
const SIAC = { job: null, timer: null, cancelando: false };
let SIAC_REL_CACHE = null;
let SIAC_REL_FILTRO = 'todos';
let SIAC_REL_BUSCA = '';

const btnSiac = $el('#btn-importar-siac');
if (btnSiac) {
    btnSiac.addEventListener('click', async () => {
        resetModalSiac();
        try {
            const c = await fetchAuth('analise_orc/contratos_disponiveis', { headers:{ 'X-Requested-With':'XMLHttpRequest' }});
            const t = c.ok ? await c.json() : null;
            $el('#siac-total-contratos').textContent = t && typeof t.total === 'number' ? `${t.total} contrato(s)` : '—';
        } catch (e) { $el('#siac-total-contratos').textContent = '—'; }
        new bootstrap.Modal($el('#modal-siac')).show();
    });
}

function setCancelarBtn(modo) {
    const b = $el('#siac-btn-cancelar');
    if (!b) return;
    b.disabled = false;
    if (modo === 'cancelar') {
        b.className = 'btn btn-danger btn-sm';
        b.innerHTML = '<i class="bi bi-x-circle"></i> Cancelar importação';
        b.dataset.modo = 'cancelar';
    } else if (modo === 'fechar') {
        b.className = 'btn btn-secondary btn-sm';
        b.textContent = 'Fechar';
        b.dataset.modo = 'fechar';
    } else {
        b.className = 'btn btn-secondary btn-sm';
        b.textContent = 'Cancelar';
        b.dataset.modo = 'normal';
    }
}

function resetModalSiac() {
    if (!$el('#siac-step-1')) return;
    if (SIAC.timer) { clearInterval(SIAC.timer); SIAC.timer = null; }
    SIAC.job = null;
    SIAC.cancelando = false;
    $el('#siac-step-1').hidden = false;
    $el('#siac-step-2').hidden = true;
    $el('#siac-step-3').hidden = true;
    $el('#siac-erro').hidden = true;
    $el('#siac-erro').textContent = '';
    $el('#siac-usuario').value = '';
    $el('#siac-senha').value = '';
    $el('#siac-contrato-unico').value = '';
    $el('#siac-progresso-bar').style.width = '0%';
    $el('#siac-progresso-bar').classList.add('progress-bar-animated','progress-bar-striped');
    $el('#siac-progresso-pct').textContent = '0%';
    $el('#siac-etapa-label').textContent = 'Iniciando…';
    $el('#siac-progresso-info').textContent = '';
    $el('#siac-login-status').innerHTML = '';
    $el('#siac-resultado').innerHTML = '';
    $el('#siac-btn-executar').disabled = false;
    $el('#siac-btn-executar').innerHTML = '<i class="bi bi-play-fill"></i> Executar importação';
    setCancelarBtn('normal');
    $el('#siac-atualizar-ds38').checked = true;
}

// ================================================================
// SIAC — botão Cancelar
// ================================================================
const btnCancelarSiac = $el('#siac-btn-cancelar');
if (btnCancelarSiac) {
    btnCancelarSiac.addEventListener('click', async () => {
        const modo = btnCancelarSiac.dataset.modo || 'normal';

        if (modo !== 'cancelar' || !SIAC.job) {
            const m = bootstrap.Modal.getInstance($el('#modal-siac'));
            if (m) m.hide();
            return;
        }

        if (!confirm(
            'Cancelar a importação em andamento?\n\n' +
            'O processo no servidor será interrompido. Contratos já processados terão suas medições mantidas.\n\n' +
            'Deseja continuar?'
        )) return;

        SIAC.cancelando = true;
        const b = btnCancelarSiac;
        b.disabled = true;
        b.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Cancelando...';

        try {
            const resp = await fetchAuth(
                `importar_siac_isabella.php?acao=cancelar&job=${encodeURIComponent(SIAC.job)}`
            );
            const r = await resp.json();

            if (SIAC.timer) { clearInterval(SIAC.timer); SIAC.timer = null; }

            if (r.ok) {
                $el('#siac-step-2').hidden = true;
                $el('#siac-step-3').hidden = false;
                $el('#siac-resultado').innerHTML = `
                    <div class="alert alert-warning py-2 mb-2">
                        <i class="bi bi-x-circle-fill"></i>
                        <strong>Importação cancelada.</strong>
                        <div class="small mt-1">${r.mensagem || ''}</div>
                    </div>
                    <p class="text-muted small mb-0">
                        <i class="bi bi-info-circle"></i>
                        Os contratos já processados antes do cancelamento têm suas medições gravadas.
                        <br>Clique em <strong>Ver relatório completo</strong> para ver o que foi feito.
                    </p>`;
                adicionarBotaoRelatorioSiac(SIAC.job, 'Cancelado');
                $el('#siac-btn-executar').disabled = false;
                $el('#siac-btn-executar').innerHTML = '<i class="bi bi-arrow-repeat"></i> Nova importação';
                setCancelarBtn('fechar');
                mostrarToast('Importação cancelada.', 'erro');
                SIAC.cancelando = false;
            } else {
                alert('Não foi possível cancelar: ' + (r.erro || 'desconhecido') +
                    (r.saida ? '\n\nSaída: ' + (Array.isArray(r.saida) ? r.saida.join('\n') : r.saida) : ''));
                SIAC.cancelando = false;
                b.disabled = false;
                setCancelarBtn('cancelar');
            }
        } catch (e) {
            SIAC.cancelando = false;
            b.disabled = false;
            setCancelarBtn('cancelar');
            alert('Erro ao cancelar: ' + e.message);
        }
    });
}

function mostrarToast(msg, tipo) {
    const bg = tipo === 'erro' ? '#dc3545' : (tipo === 'warning' ? '#f9a825' : '#198754');
    const t = document.createElement('div');
    t.className = 'position-fixed bottom-0 start-50 translate-middle-x mb-3 px-4 py-2 rounded-pill shadow';
    t.style.cssText = 'z-index:99999;background:' + bg + ';color:#fff;font-weight:600;';
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 4000);
}

// ================================================================
// SIAC — executar
// ================================================================
const siacBtnExecutar = $el('#siac-btn-executar');
if (siacBtnExecutar) {
    siacBtnExecutar.addEventListener('click', async () => {
        if (!$el('#siac-step-3').hidden) {
            $el('#siac-step-3').hidden = true;
            $el('#siac-step-2').hidden = true;
            $el('#siac-step-1').hidden = false;
            $el('#siac-erro').hidden = true;
            $el('#siac-erro').textContent = '';
            $el('#siac-senha').value = '';
            $el('#siac-btn-executar').disabled = false;
            $el('#siac-btn-executar').innerHTML = '<i class="bi bi-play-fill"></i> Executar importação';
            setCancelarBtn('normal');
            SIAC.job = null;
            setTimeout(() => $el('#siac-senha').focus(), 150);
            return;
        }
        const usuario = $el('#siac-usuario').value.trim();
        const senha   = $el('#siac-senha').value;
        const atDs38  = $el('#siac-atualizar-ds38').checked;
        const contratoUnico = $el('#siac-contrato-unico').value.trim();
        const errBox = $el('#siac-erro');

        if (!usuario || !senha) { errBox.textContent = 'Informe usuário e senha.'; errBox.hidden = false; return; }
        errBox.hidden = true;

        const btn = $el('#siac-btn-executar');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Preparando...';
        $el('#siac-step-1').hidden = true;
        $el('#siac-step-2').hidden = false;
        atualizarLoginSiac({ login_ok: null });
        setCancelarBtn('cancelar');

        if (atDs38 && !contratoUnico) {
            setProgressoSiac(5, 'Atualizando Dataset 38…', '');
            try {
                const resp = await fetchAuth('analise_orc/atualizar_dataset38', { method: 'POST', headers: { 'X-Requested-With':'XMLHttpRequest' } });
                const r = await resp.json();
                if (!r.ok) throw new Error(r.erro || 'Falha ao atualizar Dataset 38');
                setProgressoSiac(20, 'Dataset 38 atualizado', `${r.inseridos ?? '?'} registro(s)`);
            } catch (e) { mostrarErroSiac('Falha ao atualizar Dataset 38: ' + e.message); setCancelarBtn('fechar'); return; }
        } else if (contratoUnico) {
            setProgressoSiac(20, 'Modo teste (1 contrato)', contratoUnico);
        } else {
            setProgressoSiac(20, 'Usando Dataset 38 atual', '');
        }

        setProgressoSiac(25, 'Iniciando scraper SIAC…', '');
        try {
            const fd = new FormData();
            fd.append('usuario', usuario);
            fd.append('senha', senha);
            if (contratoUnico) fd.append('contrato_unico', contratoUnico);
            const resp = await fetchAuth('importar_siac_isabella.php?acao=executar', {
                method: 'POST', body: fd, headers: { 'X-Requested-With':'XMLHttpRequest' }
            });
            const r = await resp.json();
            if (!r.ok) throw new Error(r.erro || 'Falha ao iniciar');
            $el('#siac-senha').value = '';
            SIAC.job = r.job;
            iniciarPollingSiac();
        } catch (e) {
            mostrarErroSiac(e.message);
            setCancelarBtn('fechar');
        }
    });
}

function setProgressoSiac(pctVal, etapa, info) {
    if (!$el('#siac-progresso-bar')) return;
    $el('#siac-progresso-bar').style.width = pctVal + '%';
    $el('#siac-progresso-pct').textContent = pctVal.toFixed(1) + '%';
    $el('#siac-etapa-label').textContent = etapa;
    $el('#siac-progresso-info').textContent = info || '';
}

function atualizarLoginSiac(s) {
    const el = $el('#siac-login-status');
    if (!el) return;
    if (s.login_ok === 1) el.innerHTML = '<span class="text-success"><i class="bi bi-check-circle-fill"></i> Login no SIAC realizado com sucesso</span>';
    else if (s.login_ok === 0) el.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle-fill"></i> Falha no login do SIAC</span>';
    else el.innerHTML = '<span class="text-muted"><span class="spinner-border spinner-border-sm"></span> Autenticando no SIAC…</span>';
}

function voltarFormSiac(msg) {
    if (!$el('#siac-step-1')) return;
    $el('#siac-step-2').hidden = true;
    $el('#siac-step-3').hidden = true;
    $el('#siac-step-1').hidden = false;
    $el('#siac-senha').value = '';
    const e = $el('#siac-erro');
    e.textContent = msg;
    e.hidden = false;
    $el('#siac-btn-executar').disabled = false;
    $el('#siac-btn-executar').innerHTML = '<i class="bi bi-play-fill"></i> Executar importação';
    setCancelarBtn('normal');
}

function iniciarPollingSiac() {
    if (!PODE_SCRAPER || !SIAC.job) return;
    let falhasRede = 0;
    SIAC.timer = setInterval(async () => {
        if (SIAC.cancelando) return;
        try {
            const resp = await fetchAuth(`importar_siac_isabella.php?acao=status&job=${encodeURIComponent(SIAC.job)}`);
            const s = await resp.json();
            falhasRede = 0;
            atualizarLoginSiac(s);
            const p = Math.max(25, Math.min(99, 25 + (Number(s.progresso) || 0) * 0.74));
            if ($el('#siac-progresso-bar')) {
                $el('#siac-progresso-bar').style.width = p + '%';
                $el('#siac-progresso-pct').textContent = p.toFixed(1) + '%';
                $el('#siac-etapa-label').textContent = descreverEtapaSiac(s);
                $el('#siac-progresso-info').textContent =
                    (s.contrato_atual ? `Processando: ${s.contrato_atual}` : '') +
                    (s.processados != null && s.total ? ` (${s.processados}/${s.total})` : '');
            }

            if (s.status === 'concluido') {
                clearInterval(SIAC.timer); SIAC.timer = null;
                setProgressoSiac(100, 'Concluído', `${s.inseridos} medição(ões)`);
                mostrarResultadoSiac(s);
                if (estadoAtual.contrato) {
                    try {
                        const d = await apiGet(`detalhe?numero=${encodeURIComponent(estadoAtual.contrato)}&exercicio=${estadoAtual.exercicio}`);
                        if (d && !d.erro) renderDetalhe(d);
                    } catch (_) {}
                }
                recarregarBadgeUltimaExtracao();
            } else if (s.status === 'cancelado') {
                clearInterval(SIAC.timer); SIAC.timer = null;
            } else if (s.status === 'erro') {
                clearInterval(SIAC.timer); SIAC.timer = null;
                if (s.etapa === 'login' || s.login_ok === 0) voltarFormSiac(s.erro || 'Falha no login do SIAC.');
                else mostrarErroSiac(s.erro || 'Erro no scraper.');
                recarregarBadgeUltimaExtracao();
            }
        } catch (e) {
            if (++falhasRede >= 10) {
                clearInterval(SIAC.timer); SIAC.timer = null;
                mostrarErroSiac('Sem resposta do servidor.');
                setCancelarBtn('fechar');
            }
        }
    }, 2000);
}

function descreverEtapaSiac(s) {
    const map = { 'spawn':'Iniciando navegador', 'login':'Autenticando no SIAC', 'extraindo':'Extraindo e gravando medições', 'concluido':'Concluído', 'cancelado':'Cancelado' };
    return map[s.etapa] || 'Processando…';
}

function mostrarResultadoSiac(r) {
    if (!$el('#siac-step-2')) return;
    $el('#siac-step-2').hidden = true;
    $el('#siac-step-3').hidden = false;
    let html = `<div class="alert alert-success py-2 mb-2">
        <i class="bi bi-check-circle-fill"></i>
        <strong>${r.inseridos}</strong> medição(ões) importada(s) — ${r.contratos_afetados} contrato(s) atualizado(s).
    </div>`;
    if (r.contratos_ignorados > 0) {
        html += `<div class="alert alert-warning py-2 small mb-2"><strong>${r.contratos_ignorados}</strong> contrato(s) ignorado(s).</div>`;
    }
    $el('#siac-resultado').innerHTML = html;
    adicionarBotaoRelatorioSiac(SIAC.job, 'Concluído');
    $el('#siac-btn-executar').disabled = false;
    $el('#siac-btn-executar').innerHTML = '<i class="bi bi-arrow-repeat"></i> Nova importação';
    setCancelarBtn('fechar');
}

function mostrarErroSiac(msg) {
    if (!$el('#siac-step-2')) return;
    $el('#siac-step-2').hidden = true;
    $el('#siac-step-3').hidden = false;
    $el('#siac-resultado').innerHTML = `<div class="alert alert-danger py-2 mb-2"><i class="bi bi-exclamation-triangle-fill"></i> ${msg}</div>`;
    adicionarBotaoRelatorioSiac(SIAC.job, 'Erro');
    $el('#siac-btn-executar').disabled = false;
    $el('#siac-btn-executar').innerHTML = '<i class="bi bi-arrow-repeat"></i> Tentar novamente';
    setCancelarBtn('fechar');
}

function adicionarBotaoRelatorioSiac(jobId, _statusTxt) {
    if (!jobId) return;
    const anterior = $el('#siac-btn-ver-relatorio');
    if (anterior) anterior.remove();
    const btn = document.createElement('button');
    btn.id = 'siac-btn-ver-relatorio';
    btn.type = 'button';
    btn.className = 'btn btn-sm btn-outline-primary mt-2';
    btn.innerHTML = '<i class="bi bi-file-earmark-text"></i> Ver relatório completo';
    btn.addEventListener('click', () => abrirRelatorioSiac(jobId));
    $el('#siac-resultado').appendChild(btn);
}

// ================================================================
// SIAC — relatório
// ================================================================
async function abrirRelatorioSiac(jobId) {
    if (!jobId) return;
    $el('#siac-rel-header').textContent = `Carregando ${jobId}…`;
    $el('#siac-rel-lista').innerHTML = '<div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm"></span> Carregando relatório…</div>';
    $el('#siac-rel-cards').style.display = '';
    $el('#siac-rel-aviso').hidden = true;
    const modal = new bootstrap.Modal($el('#modal-siac-relatorio'));
    modal.show();

    try {
        const resp = await fetchAuth(`importar_siac_isabella.php?acao=relatorio&job=${encodeURIComponent(jobId)}`);
        const d = await resp.json();
        if (!d.ok) { throw new Error(d.erro || 'Erro ao carregar relatório'); }
        SIAC_REL_CACHE = d;
        SIAC_REL_FILTRO = 'todos';
        SIAC_REL_BUSCA = '';
        renderRelatorioSiac(d);
    } catch (e) {
        $el('#siac-rel-lista').innerHTML = `<div class="alert alert-danger m-3"><i class="bi bi-exclamation-triangle-fill"></i> ${e.message}</div>`;
    }
}

function renderRelatorioSiac(d) {
    const ini = fmtDataHora(d.iniciado_em);
    const fim = fmtDataHora(d.finalizado_em);
    const st  = d.status || '—';
    $el('#siac-rel-header').textContent = `${d.job} · ${st} · Iniciado ${ini} · Finalizado ${fim}`;

    const aviso = $el('#siac-rel-aviso');
    if (st === 'cancelado' || st === 'erro') {
        aviso.hidden = false;
        aviso.className = 'alert alert-warning py-2 small';
        aviso.innerHTML = `<i class="bi bi-exclamation-triangle-fill"></i> <strong>Relatório parcial.</strong> ` +
            (st === 'cancelado' ? 'A importação foi cancelada. ' : 'A importação falhou. ') +
            'Abaixo estão os contratos processados até o momento.';
    } else {
        aviso.hidden = true;
    }

    const r = d.resumo || {};
    $el('#siac-rel-ok').textContent        = r.ok       || 0;
    $el('#siac-rel-ignorado').textContent = r.ignorado || 0;
    $el('#siac-rel-erro').textContent     = r.erro     || 0;
    $el('#siac-rel-medicoes').textContent = r.medicoes || 0;

    const itens = d.itens || [];
    const cnt = { todos: itens.length, OK: 0, IGNORADO: 0, ERRO: 0 };
    itens.forEach(it => { const s = it.status || 'IGNORADO'; cnt[s] = (cnt[s]||0) + 1; });
    $el('#siac-rel-count-todos').textContent = cnt.todos;
    $el('#siac-rel-count-ok').textContent    = cnt.OK;
    $el('#siac-rel-count-ign').textContent   = cnt.IGNORADO;
    $el('#siac-rel-count-err').textContent   = cnt.ERRO;

    document.querySelectorAll('.siac-rel-tab').forEach(t => {
        t.onclick = () => {
            document.querySelectorAll('.siac-rel-tab').forEach(x => x.classList.remove('ativo'));
            t.classList.add('ativo');
            SIAC_REL_FILTRO = t.dataset.filtro;
            aplicarFiltroRelSiac();
        };
    });
    document.querySelectorAll('.siac-rel-tab').forEach(t => {
        t.classList.toggle('ativo', t.dataset.filtro === SIAC_REL_FILTRO);
    });

    const busca = $el('#siac-rel-busca');
    if (busca) {
        busca.value = SIAC_REL_BUSCA;
        busca.oninput = () => { SIAC_REL_BUSCA = busca.value || ''; aplicarFiltroRelSiac(); };
    }

    const csv = $el('#siac-rel-csv');
    if (csv) { csv.onclick = () => baixarCsvRelatorio(d); }

    aplicarFiltroRelSiac();
}

function aplicarFiltroRelSiac() {
    const d = SIAC_REL_CACHE;
    if (!d) return;
    const itens = (d.itens || []).filter(it => {
        const st = it.status || 'IGNORADO';
        if (SIAC_REL_FILTRO !== 'todos' && st !== SIAC_REL_FILTRO) return false;
        if (SIAC_REL_BUSCA) {
            const q = SIAC_REL_BUSCA.toLowerCase();
            const alvo = String(it.contrato || '').toLowerCase() + ' ' + String(it.estado || '').toLowerCase();
            if (alvo.indexOf(q) === -1) return false;
        }
        return true;
    });

    if (!itens.length) {
        $el('#siac-rel-lista').innerHTML = '<div class="text-center text-muted py-4">Nenhum registro com os filtros atuais.</div>';
        return;
    }

    const html = itens.map(it => {
        const st = it.status || 'IGNORADO';
        const cls = st === 'OK' ? 'siac-rel-ok' : (st === 'ERRO' ? 'siac-rel-erro' : 'siac-rel-ignorado');
        const badge = st === 'OK'
            ? '<span class="badge bg-success">OK</span>'
            : (st === 'ERRO'
                ? '<span class="badge bg-danger">ERRO</span>'
                : '<span class="badge bg-warning text-dark">IGNORADO</span>');
        const med = (st === 'OK' && it.medicoes > 0)
            ? `<span class="badge bg-light text-dark border ms-1">${it.medicoes} medição(ões)</span>`
            : '';
        const dur = it.duracao_seg ? `<span class="siac-rel-duracao ms-2"><i class="bi bi-stopwatch"></i> ${fmtDuracao(it.duracao_seg)}</span>` : '';
        const motivo = it.motivo
            ? `<div class="siac-rel-motivo"><i class="bi bi-info-circle"></i> ${escapeHtml(it.motivo)}</div>`
            : '';
        return `<div class="siac-rel-linha ${cls}">
            <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                <div class="flex-grow-1 min-w-0">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="siac-rel-contrato">${escapeHtml(it.contrato || '—')}</span>
                        ${it.estado ? `<span class="badge bg-light text-dark border">${escapeHtml(it.estado)}</span>` : ''}
                        ${badge}${med}${dur}
                    </div>
                    ${motivo}
                </div>
            </div>
        </div>`;
    }).join('');

    $el('#siac-rel-lista').innerHTML = html;
}

function baixarCsvRelatorio(d) {
    const linhas = [
        ['Contrato', 'Estado', 'Status', 'Medições', 'Duração (s)', 'Motivo']
    ];
    (d.itens || []).forEach(it => {
        linhas.push([
            it.contrato || '',
            it.estado || '',
            it.status || '',
            it.medicoes || 0,
            it.duracao_seg || 0,
            it.motivo || ''
        ]);
    });
    const csv = linhas.map(l => l.map(c => `"${String(c).replace(/"/g, '""')}"`).join(',')).join('\r\n');
    const blob = new Blob(['\ufeff' + csv], { type:'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `relatorio_siac_${d.job || 'job'}.csv`;
    a.click();
    URL.revokeObjectURL(url);
}

// ================================================================
// SIAC — última extração completa
// ================================================================
async function recarregarBadgeUltimaExtracao() {
    if (!PODE_SCRAPER) return;
    const badge = $el('#siac-ultima-extracao');
    const txt   = $el('#siac-ultima-texto');
    if (!badge || !txt) return;

    try {
        const resp = await fetchAuth('importar_siac_isabella.php?acao=ultima_extracao');
        const r = await resp.json();
        const j = r && r.job;
        if (!j || !j.job_id) { badge.hidden = true; return; }

        badge.classList.remove('alerta', 'erro');
        if (j.status === 'cancelado') badge.classList.add('alerta');
        else if (j.status === 'erro') badge.classList.add('erro');

        const dataIni = j.iniciado_em ? new Date(j.iniciado_em.replace(' ', 'T')) : null;
        const dd = dataIni
            ? dataIni.toLocaleDateString('pt-BR', { day:'2-digit', month:'2-digit' }) + ' ' +
              dataIni.toLocaleTimeString('pt-BR', { hour:'2-digit', minute:'2-digit' })
            : '—';
        const total = Number(j.total) || 0;
        const meds  = Number(j.inseridos) || 0;
        const medsFmt = meds.toLocaleString('pt-BR');

        txt.textContent = `${dd} · ${total} ct · ${medsFmt} med`;

        const oldTt = bootstrap.Tooltip.getInstance(badge);
        if (oldTt) oldTt.dispose();
        badge.setAttribute('title',
            `<strong>Última extração completa</strong><br>` +
            `${fmtDataHora(j.iniciado_em)} → ${fmtDataHora(j.finalizado_em)}<br>` +
            `Por: <strong>${escapeHtml(j.usuario || '—')}</strong><br>` +
            `${total} contratos · ${medsFmt} medições<br>` +
            `Status: <strong>${escapeHtml(j.status || '—')}</strong>`
        );
        new bootstrap.Tooltip(badge, { html: true, sanitize: false, placement: 'bottom' });

        badge.onclick = () => abrirRelatorioSiac(j.job_id);
        badge.hidden = false;
    } catch (e) {
        badge.hidden = true;
    }
}

// ================================================================
// SIAC — banner de job ativo
// ================================================================
let bannerProgressTimer = null;

async function verificarJobAtivo() {
    if (!PODE_SCRAPER) return;
    try {
        const r = await fetchAuth('importar_siac_isabella.php?acao=historico');
        const h = await r.json();
        const j = (h.itens || []).find(x => ['iniciando','rodando'].includes(x.status));
        const existing = document.getElementById('banner-siac-ativo');
        if (!j) {
            if (existing) existing.remove();
            if (bannerProgressTimer) { clearInterval(bannerProgressTimer); bannerProgressTimer = null; }
            return;
        }
        if (existing && existing.dataset.jobId === j.job_id) return;
        if (existing) existing.remove();

        const banner = document.createElement('div');
        banner.id = 'banner-siac-ativo';
        banner.dataset.jobId = j.job_id;
        banner.className = 'alert alert-info mb-0';
        banner.style.cssText = 'display:flex;justify-content:space-between;align-items:center;';
        banner.innerHTML = `
            <div style="flex:1;min-width:0;padding-right:12px">
                <div><i class="bi bi-hourglass-split"></i> <strong>SIAC em andamento</strong></div>
                <small id="banner-siac-etapa" class="d-block">${j.status}${j.etapa ? ' · ' + j.etapa : ''}</small>
                <div class="progress mt-1" style="height:8px;background:#dbeafe">
                    <div class="progress-bar progress-bar-striped progress-bar-animated"
                         id="banner-siac-bar" style="width:0%;background:#0d6efd"></div>
                </div>
                <small class="text-muted d-block mt-1" id="banner-siac-info" style="font-size:.72rem"></small>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-primary" id="btn-reabrir-siac"><i class="bi bi-eye"></i> Ver</button>
                <button class="btn btn-sm btn-outline-secondary" id="btn-fechar-banner"><i class="bi bi-x-lg"></i></button>
            </div>`;
        document.body.appendChild(banner);

        document.getElementById('btn-reabrir-siac').addEventListener('click', () => {
            if (!$el('#modal-siac')) return;
            SIAC.job = j.job_id;
            resetModalSiac();
            SIAC.job = j.job_id;
            $el('#siac-step-1').hidden = true;
            $el('#siac-step-2').hidden = false;
            setCancelarBtn('cancelar');
            new bootstrap.Modal($el('#modal-siac')).show();
            iniciarPollingSiac();
        });
        document.getElementById('btn-fechar-banner').addEventListener('click', () => {
            banner.remove();
            if (bannerProgressTimer) { clearInterval(bannerProgressTimer); bannerProgressTimer = null; }
        });
        atualizarProgressoBanner(j.job_id);
        if (bannerProgressTimer) clearInterval(bannerProgressTimer);
        bannerProgressTimer = setInterval(() => atualizarProgressoBanner(j.job_id), 3000);
    } catch (e) {}
}

async function atualizarProgressoBanner(jobId) {
    const banner = document.getElementById('banner-siac-ativo');
    if (!banner || banner.dataset.jobId !== jobId) {
        if (bannerProgressTimer) { clearInterval(bannerProgressTimer); bannerProgressTimer = null; }
        return;
    }
    try {
        const r = await fetchAuth(`importar_siac_isabella.php?acao=status&job=${encodeURIComponent(jobId)}`);
        const s = await r.json();
        const p = Math.max(0, Math.min(100, Number(s.progresso) || 0));
        const bar   = document.getElementById('banner-siac-bar');
        const etapa = document.getElementById('banner-siac-etapa');
        const info  = document.getElementById('banner-siac-info');
        if (bar)   bar.style.width = p + '%';
        if (etapa) etapa.textContent = (s.status || 'rodando') + (s.etapa ? ' · ' + s.etapa : '');
        if (info) {
            const partes = [];
            if (s.contrato_atual) partes.push(`Processando: ${s.contrato_atual}`);
            if (s.processados != null && s.total) partes.push(`(${s.processados}/${s.total})`);
            if (s.inseridos != null) partes.push(`· ${s.inseridos} medição(ões)`);
            info.textContent = partes.join(' ');
        }
        if (s.status === 'concluido' || s.status === 'erro' || s.status === 'cancelado') {
            if (bannerProgressTimer) { clearInterval(bannerProgressTimer); bannerProgressTimer = null; }
            if (bar) {
                bar.classList.remove('progress-bar-animated','progress-bar-striped');
                bar.style.background = s.status === 'concluido' ? '#198754' : (s.status === 'cancelado' ? '#f9a825' : '#dc3545');
            }
            setTimeout(() => {
                const b = document.getElementById('banner-siac-ativo');
                if (b && b.dataset.jobId === jobId) b.remove();
            }, 5000);
            verificarJobAtivo();
            recarregarBadgeUltimaExtracao();
        }
    } catch (e) {}
}

// ================================================================
// RENDER DETALHE
// ================================================================
function renderDetalhe(c) {
    $el('#cards').hidden = false;
    const calcNec = calcularNecessarioNoAno(c);
    const valorDisponivel = Number(c.valor_empenhado || 0) + Number(c.valor_rap || 0);

    $el('#c-dotacao').textContent    = c.dotacao_atualizada > 0 ? brl(c.dotacao_atualizada) : '—';
    $el('#c-total').textContent      = brl(c.total_instrumento);
    $el('#c-disponivel').textContent = brl(valorDisponivel);
    $el('#c-necessario').textContent = brl(calcNec.necessario);

    $el('#c-farol').innerHTML = c.farol
        ? `<span class="farol-badge farol-${c.farol}">${c.farol}</span>`
        : `<span class="farol-badge farol-SEM">—</span>`;

    const alertaPlan = $el('#c-alerta-plan');
    if (c.alerta_planejamento) {
        const faltando = (c.meses_faltantes || []).map(dtCurto).join(', ');
        alertaPlan.hidden = false;
        alertaPlan.innerHTML = `<span class="badge bg-warning text-dark">⚠ Não planejado: ${faltando || 'vários meses'}</span>`;
    } else {
        alertaPlan.hidden = true;
    }

    if (c.status_medicao === 'SEM_MEDICAO') {
        $el('#c-cobertura').textContent = 'Contrato sem medição — farol não calculado.';
    } else if (c.cobertura_meses != null) {
        $el('#c-cobertura').textContent = `Cobertura: ${c.cobertura_meses} mês(es) · previsão mensal: ${brl(c.previsao_mensal)}`;
    } else {
        $el('#c-cobertura').textContent = '';
    }

    c.disponivel_real = valorDisponivel;
    window.__contratoAtual = c;

    renderSerieMensal(c.serie_mensal, c.anomalias_saldo || 0, c.campo_planejamento || 'NPO');
    renderGrafico(c.serie_mensal);
    atualizarTooltipsCards(c, calcNec);

    document.querySelectorAll('#tbl-mensal [data-bs-toggle="tooltip"]').forEach(el => {
        const old = bootstrap.Tooltip.getInstance(el);
        if (old) old.dispose();
        new bootstrap.Tooltip(el, { html: true });
    });
}

// ================================================================
// TOOLTIPS DOS CARDS
// ================================================================
function atualizarTooltipsCards(c, calcNec) {
    const dotHtml =
        ttTitulo('bi-cash-coin', 'Dotação atualizada') +
        ttSecao('Fonte', `
            <div><b>Tabela</b> <code>npo_dotacao</code> (via <code>npo_acao_orcamentaria.codigo</code>)</div>
            <div style="margin-top:3px"><b>Coluna</b> <code>npo_dotacao.valor</code> (SUM)</div>
            <div style="margin-top:3px"><b>Filtro</b> ação = <code>${c.acoes?.[0] ?? '—'}</code> · exercício = <code>${estadoAtual.exercicio}</code></div>
        `) +
        ttSecao('Fórmula', ttCodigo('SUM(npo_dotacao.valor)')) +
        ttSecao('O que é', 'Valor autorizado no orçamento do exercício para esta ação orçamentária.') +
        ttResultado('Valor atual', brl(c.dotacao_atualizada));
    setarTooltip('#card-dotacao-info', dotHtml);

    const pi        = Number(c.valor_pi        ?? 0);
    const aditivo   = Number(c.valor_aditivo   ?? 0);
    const reajuste  = Number(c.valor_reajuste  ?? 0);
    const totalCalc = pi + aditivo + reajuste;
    const totalCol  = (c.total_instrumento_coluna != null) ? Number(c.total_instrumento_coluna) : null;

    let blocoComparacao = '';
    if (totalCol != null) {
        const diffTot    = totalCalc - totalCol;
        const diffTotPct = totalCol > 0 ? (diffTot / totalCol) * 100 : 0;
        const absDiffTot = Math.abs(diffTotPct);
        const alinhado   = Math.abs(diffTot) < 0.01;
        const corDiff = alinhado
            ? 'background:#d1e7dd;color:#0f5132;border:1px solid #a3cfbb'
            : (absDiffTot < 5
                ? 'background:#fff3cd;color:#664d03;border:1px solid #ffe69c'
                : 'background:#f8d7da;color:#842029;border:1px solid #f1aeb5');
        const iconeDiff = alinhado
            ? 'bi-check-circle-fill'
            : (absDiffTot < 5 ? 'bi-exclamation-triangle-fill' : 'bi-x-circle-fill');
        blocoComparacao = ttSecao('Comparação — coluna existente × cálculo', `
            <table style="width:100%;font-size:.82rem;border-collapse:collapse">
                <tr><td style="padding:6px 0;color:#495057">Coluna <code>pi_a_r</code> (Dataset 47)</td>
                    <td style="text-align:right;font-weight:700;color:#1f2d3d">${brl(totalCol)}</td></tr>
                <tr><td style="padding:6px 0;color:#495057">Soma calculada (PI + A + R)</td>
                    <td style="text-align:right;font-weight:700;color:#1f2d3d">${brl(totalCalc)}</td></tr>
                <tr style="border-top:1px dashed #dee2e6">
                    <td style="padding:6px 0;color:#495057"><b>Diferença</b></td>
                    <td style="text-align:right;font-weight:700;color:#1f2d3d">${brl(diffTot)} (${diffTotPct >= 0 ? '+' : ''}${diffTotPct.toFixed(2)}%)</td>
                </tr>
            </table>
            <div class="calc-diff" style="${corDiff};margin-top:10px">
                <i class="bi ${iconeDiff}"></i>
                <span>${alinhado ? 'Valores alinhados' : (absDiffTot < 5 ? 'Divergência pequena (até 5%)' : 'Divergência alta (≥ 5%) — verificar origem do dado')}</span>
            </div>
        `);
    }
    const totalHtml =
        ttTitulo('bi-file-earmark-text', 'Total do instrumento') +
        ttSecao('Fonte', `
            <div><b>Dataset 47</b> — <code>supra_dataset_47_financeiro_orcamentario_atlas</code></div>
            <div style="margin-top:6px"><b>Colunas utilizadas</b></div>
            <div style="margin-top:3px;padding-left:10px;line-height:1.75">
                <b>PI</b> → <code>valor_pi_dnit</code><br>
                <b>A</b>&nbsp; → <code>valor_total_aditivo</code><br>
                <b>R</b>&nbsp; → <code>reajuste</code>
            </div>
        `) +
        ttSecao('Fórmula', ttCodigo('PI + A + R')) +
        ttSecao('Cálculo', ttCodigo(`(${brl(pi)} + ${brl(aditivo)} + ${brl(reajuste)})`)) +
        blocoComparacao +
        ttResultado('Total do instrumento (calculado)', brl(totalCalc));
    setarTooltip('#card-total-info', totalHtml);

    const empenhado = Number(c.valor_empenhado || 0);
    const rap       = Number(c.valor_rap || 0);
    const valorDisp = empenhado + rap;
    const dispHtml =
        ttTitulo('bi-wallet2', 'Valor Disponível') +
        ttSecao('Fórmula', ttCodigo('Empenhado + RAP')) +
        ttSecao('Cálculo', ttCodigo(`${brl(empenhado)} + ${brl(rap)}`)) +
        ttResultado('Valor Disponível', brl(valorDisp));
    setarTooltip('#card-disponivel-info', dispHtml);

    const projMes   = calcNec.mesesProjetados;
    const totalProj = calcNec.totalProjetado;
    const necessario= calcNec.necessario;
    const campoPref = calcNec.campoPreferencia;
    const necHtml =
        ttTitulo('bi-calendar-range', 'Necessário no ano') +
        ttSecao('Fórmula', ttCodigo('Σ(projeção mensal) + (medido − liquidado)')) +
        ttSecao('Projeção mensal', `
            <table style="width:100%;font-size:.82rem;border-collapse:collapse">
                <tr><td style="padding:5px 0;color:#495057">Meses projetados</td>
                    <td style="text-align:right;font-weight:600;color:#1f2d3d">${projMes.length}</td></tr>
                <tr><td style="padding:5px 0;color:#495057">Total projetado</td>
                    <td style="text-align:right;font-weight:600;color:#1f2d3d">${brl(totalProj)}</td></tr>
                <tr><td style="padding:5px 0;color:#495057">Pendente (medido − liquidado)</td>
                    <td style="text-align:right;font-weight:600;color:#1f2d3d">${brl(calcNec.pendente)}</td></tr>
            </table>
        `) +
        ttResultado('Necessário no ano', brl(necessario));
    setarTooltip('#card-necessario-info', necHtml);
}

// ================================================================
// MODAL VER CÁLCULO
// ================================================================
$el('#btn-calculo').addEventListener('click', () => {
    const c = window.__contratoAtual;
    if (!c) { alert('Selecione um contrato primeiro.'); return; }
    $el('#calc-titulo').textContent = c.numero || c.contrato || '';
    $el('#calculo-conteudo').innerHTML = renderCalculoModal(c);
    new bootstrap.Modal($el('#modal-calculo')).show();
});

function renderCalculoModal(c) {
    const calcNec = calcularNecessarioNoAno(c);
    const calculo = c.calculo_previsao || {};
    let ultimas3 = calcNec.ultimas3Medicoes;
    const mediaCalc  = calcNec.media || calculo.media || 0;
    const n3         = ultimas3.length;
    const campoPref  = calcNec.campoPreferencia;

    let html = `<div class="calc-bloco">
        <div class="calc-bloco-titulo"><span class="num">1</span> Análise de cobertura orçamentária</div>
        <div class="calc-linha"><span class="lbl">Coluna utilizada</span><span class="val"><b>${campoPref === 'ANALISTA' ? 'Planejado Analista' : 'Planejado (NPO)'}</b></span></div>
        <div class="calc-linha"><span class="lbl">Valor Disponível</span><span class="val"><b>${brl(c.disponivel_real)}</b></span></div>
        <div class="calc-linha"><span class="lbl">Cobertura</span><span class="val"><b>${c.cobertura_meses ?? '—'} mês(es)</b></span></div>
        <div class="calc-linha"><span class="lbl">Farol</span><span class="val">${c.farol ?? '—'}</span></div>
    </div>`;

    if (n3 > 0) {
        const soma3 = ultimas3.reduce((s, m) => s + m.valor, 0);
        html += `<div class="calc-bloco">
            <div class="calc-bloco-titulo"><span class="num">2</span> Média das últimas ${n3} medições (SIAC)</div>
            <div class="calc-memoria">
                <div class="linha-op">( ${ultimas3.map(m => brl(m.valor)).join(' + ')} ) ÷ ${n3}</div>
                <div class="resultado-final">= ${brl(soma3 / n3)}</div>
            </div>
        </div>`;
    }

    return html;
}

// ================================================================
// SÉRIE MENSAL
// ================================================================
function renderSerieMensal(rows, anomalias, campoPref) {
    $el('#mensal-box').hidden = false;
    campoPref = (campoPref || 'NPO').toUpperCase();

    const thNpo = $el('#th-col-npo');
    const thAna = $el('#th-col-ana');
    if (thNpo && thAna) {
        thNpo.classList.toggle('ativa',   campoPref === 'NPO');
        thNpo.classList.toggle('inativa', campoPref !== 'NPO');
        thAna.classList.toggle('ativa',   campoPref === 'ANALISTA');
        thAna.classList.toggle('inativa', campoPref !== 'ANALISTA');
        thNpo.querySelector('.radio-col').textContent = campoPref === 'NPO'      ? '⦿' : '○';
        thAna.querySelector('.radio-col').textContent = campoPref === 'ANALISTA' ? '⦿' : '○';
    }

    const aviso = $el('#aviso-saldo');
    if (anomalias > 0) {
        aviso.className = 'alert alert-danger mb-2';
        aviso.innerHTML = `<i class="bi bi-exclamation-triangle-fill"></i> <strong>${anomalias} inconsistência(s)</strong> detectada(s).`;
    } else {
        aviso.className = 'alert alert-info mb-2';
        aviso.innerHTML = `<i class="bi bi-info-circle"></i> Clique no cabeçalho NPO ou Analista para escolher qual coluna entra no farol.`;
    }
    aviso.hidden = false;

    const hoje = new Date().toISOString().slice(0, 7) + '-01';
    const tb = $el('#tbl-mensal').querySelector('tbody');
    tb.innerHTML = '';
    rows.forEach(r => {
        const tr = document.createElement('tr');
        let npoCell = '<span class="text-muted">—</span>';
        if (r.planejado !== null) npoCell = brl(r.planejado);
        let estCell = '<span class="text-muted">—</span>';
        if (r.estimado !== null) estCell = brl(r.estimado);
        let anaCell = '<span class="text-muted">—</span>';
        if (r.planejado_analista !== null) anaCell = brl(r.planejado_analista);
        const medidoCell = r.medido !== null ? brl(r.medido) : '<span class="text-muted">—</span>';
        const saldoCell  = r.saldo_medido_nao_liq !== null ? brl(r.saldo_medido_nao_liq) : '<span class="text-muted">—</span>';

        tr.innerHTML = `
            <td>${dtMesAno(r.mes)}</td>
            <td class="text-end">${npoCell}</td>
            <td class="text-end">${estCell}</td>
            <td class="text-end">${anaCell}</td>
            <td class="text-end">${medidoCell}</td>
            <td class="text-end">${brl(r.liquidado)}</td>
            <td>${dt(r.data_ref_medicao)}</td>
            <td class="text-end">${saldoCell}</td>`;
        tb.appendChild(tr);
    });

    renderTotalMensal(rows);
}

function renderTotalMensal(rows) {
    const tf = $el('#tbl-mensal-tfoot');
    tf.innerHTML = '';
    if (!rows || rows.length === 0) return;
    let totalNpo = 0, totalEst = 0, totalAna = 0, totalMed = 0, totalLiq = 0, lastSaldo = null;
    rows.forEach(r => {
        if (r.planejado !== null)            totalNpo += Number(r.planejado);
        if (r.estimado !== null)             totalEst += Number(r.estimado);
        if (r.planejado_analista !== null)   totalAna += Number(r.planejado_analista);
        if (r.medido !== null)               totalMed += Number(r.medido);
        if (r.liquidado !== null)            totalLiq += Number(r.liquidado);
        if (r.saldo_medido_nao_liq !== null) lastSaldo = Number(r.saldo_medido_nao_liq);
    });
    const tr = document.createElement('tr');
    tr.className = 'tbl-total';
    tr.innerHTML = `
        <td>TOTAL</td>
        <td class="text-end">${brl(totalNpo)}</td>
        <td class="text-end">${brl(totalEst)}</td>
        <td class="text-end">${brl(totalAna)}</td>
        <td class="text-end">${brl(totalMed)}</td>
        <td class="text-end">${brl(totalLiq)}</td>
        <td></td>
        <td class="text-end">${lastSaldo !== null ? brl(lastSaldo) : '—'}</td>`;
    tf.appendChild(tr);
}

function renderGrafico(serie) {
    $el('#chart-box').hidden = false;
    const acumulado = $el('#chart-acumulado').checked;
    let plan = serie.map(r => Number(r.planejado_exibido || 0));
    let med  = serie.map(r => Number(r.medido || 0));
    let liq  = serie.map(r => Number(r.liquidado || 0));
    if (acumulado) {
        const acc = (arr) => arr.reduce((a, v, i) => (a[i] = (a[i - 1] ?? 0) + v, a), []);
        plan = acc(plan); med = acc(med); liq = acc(liq);
    }
    const labels = serie.map(r => dtMesAno(r.mes));
    if (chartMain) chartMain.destroy();
    chartMain = new Chart($el('#chart'), {
        type: 'bar',
        data: { labels, datasets: [
            { label:'Planejado', data: plan, backgroundColor:'#90caf9' },
            { label:'Medido',    data: med,  backgroundColor:'#ffcc80' },
            { label:'Liquidado', data: liq,  backgroundColor:'#a5d6a7' },
        ]},
        options: {
            responsive: true,
            plugins: { tooltip: { callbacks: { label: (ctx) => `${ctx.dataset.label}: ${brl(ctx.parsed.y)}` } } },
            scales: { y: { ticks: { callback: v => brl(v) } } },
        },
    });
}

$el('#chart-acumulado').addEventListener('change', async () => {
    if (!estadoAtual.contrato) return;
    try {
        const data = await apiGet(`detalhe?numero=${encodeURIComponent(estadoAtual.contrato)}&exercicio=${estadoAtual.exercicio}`);
        renderDetalhe(data);
    } catch (err) {}
});

// ================================================================
// ALTERAR PREFERÊNCIA / EDIÇÕES
// ================================================================
async function alterarPreferencia(campo) {
    if (!estadoAtual.contrato) return;
    const atual = (window.__contratoAtual?.campo_planejamento || 'NPO').toUpperCase();
    if (atual === campo) return;
    try {
        const fd = new FormData();
        fd.append('contrato', estadoAtual.contrato);
        fd.append('campo', campo);
        const r = await fetchJsonPost('preferencia/salvar', fd);
        if (r.erro) { alert(r.erro); return; }
        toast(`Cálculos agora usam: ${campo === 'NPO' ? 'Planejado (NPO)' : 'Planejado Analista'}.`);
        await carregarDetalhe(estadoAtual.contrato);
    } catch (e) { alert('Erro: ' + e.message); }
}

document.addEventListener('click', (e) => {
    const btnEst = e.target.closest('.edit-est');
    if (btnEst) {
        e.stopPropagation();
        editCtx.mode = 'estimado'; editCtx.contrato = estadoAtual.contrato;
        editCtx.mes = btnEst.dataset.mes;
        editCtx.valorCalculado = parseFloat(btnEst.dataset.calculado) || 0;
        $el('#ed-titulo').textContent = 'Editar valor da Estimativa';
        $el('#ed-mes').textContent = dtMesAno(editCtx.mes);
        $el('#ed-calc').textContent = brl(editCtx.valorCalculado);
        $el('#ed-valor').value = parseFloat(btnEst.dataset.valor) || '';
        $el('#ed-justificativa').value = btnEst.dataset.justificativa || '';
        $el('#ed-erro').hidden = true;
        new bootstrap.Modal($el('#modal-editar-est')).show();
        return;
    }
    const btnAna = e.target.closest('.edit-ana');
    if (btnAna) {
        e.stopPropagation();
        editCtx.mode = 'analista'; editCtx.contrato = estadoAtual.contrato;
        editCtx.mes = btnAna.dataset.mes;
        editCtx.valorCalculado = parseFloat(btnAna.dataset.calculado) || 0;
        $el('#ed-titulo').textContent = 'Editar Planejado Analista';
        $el('#ed-mes').textContent = dtMesAno(editCtx.mes);
        $el('#ed-calc').textContent = brl(editCtx.valorCalculado);
        $el('#ed-valor').value = parseFloat(btnAna.dataset.valor) || '';
        $el('#ed-justificativa').value = btnAna.dataset.justificativa || '';
        $el('#ed-erro').hidden = true;
        new bootstrap.Modal($el('#modal-editar-est')).show();
    }
});

$el('#ed-salvar').addEventListener('click', async () => {
    const v    = parseFloat($el('#ed-valor').value);
    const just = ($el('#ed-justificativa').value || '').trim();
    if (isNaN(v) || v < 0) { $el('#ed-erro').textContent = 'Informe um valor válido.'; $el('#ed-erro').hidden = false; return; }
    if (!just) { $el('#ed-erro').textContent = 'Justificativa é obrigatória.'; $el('#ed-erro').hidden = false; $el('#ed-justificativa').focus(); return; }
    if (editCtx.mode === 'analista') await salvarPlanejadoAnalista('salvar', v, just);
    else                             await salvarEstimativa('salvar', v, just);
});

$el('#ed-reset').addEventListener('click', async () => {
    if (!confirm('Remover o valor manual deste mês?')) return;
    if (editCtx.mode === 'analista') await salvarPlanejadoAnalista('reset', null, null);
    else                             await salvarEstimativa('reset', null, null);
});

async function salvarEstimativa(acao, valor, justificativa) {
    try {
        const fd = new FormData();
        fd.append('contrato', editCtx.contrato);
        fd.append('mes', editCtx.mes);
        fd.append('acao', acao);
        if (acao === 'salvar') { fd.append('valor', valor); fd.append('justificativa', justificativa || ''); }
        const r = await fetchJsonPost('estimativa/salvar', fd);
        if (r.erro) { $el('#ed-erro').textContent = r.erro; $el('#ed-erro').hidden = false; return; }
        const modal = bootstrap.Modal.getInstance($el('#modal-editar-est')); if (modal) modal.hide();
        toast(acao === 'reset' ? 'Estimativa restaurada.' : 'Estimativa salva.');
        const data = await apiGet(`detalhe?numero=${encodeURIComponent(editCtx.contrato)}&exercicio=${estadoAtual.exercicio}`);
        renderDetalhe(data);
    } catch (e) { $el('#ed-erro').textContent = e.message; $el('#ed-erro').hidden = false; }
}

async function salvarPlanejadoAnalista(acao, valor, justificativa) {
    try {
        const fd = new FormData();
        fd.append('contrato', editCtx.contrato);
        fd.append('mes', editCtx.mes);
        fd.append('acao', acao);
        if (acao === 'salvar') { fd.append('valor', valor); fd.append('justificativa', justificativa || ''); }
        const r = await fetchJsonPost('planejado_analista/salvar', fd);
        if (r.erro) { $el('#ed-erro').textContent = r.erro; $el('#ed-erro').hidden = false; return; }
        const modal = bootstrap.Modal.getInstance($el('#modal-editar-est')); if (modal) modal.hide();
        toast(acao === 'reset' ? 'Planejado Analista restaurado.' : 'Planejado Analista salvo.');
        const data = await apiGet(`detalhe?numero=${encodeURIComponent(editCtx.contrato)}&exercicio=${estadoAtual.exercicio}`);
        renderDetalhe(data);
    } catch (e) { $el('#ed-erro').textContent = e.message; $el('#ed-erro').hidden = false; }
}

// ================================================================
// MEDIÇÕES
// ================================================================
$el('#btn-medicoes').addEventListener('click', async () => {
    if (!estadoAtual.contrato) { alert('Selecione um contrato primeiro.'); return; }
    try {
        const data = await apiGet(`medicoes?numero=${encodeURIComponent(estadoAtual.contrato)}&limite=12`);
        if (data.erro) { alert(data.erro); return; }
        renderModalMedicoes(data);
        new bootstrap.Modal($el('#modal-medicoes')).show();
    } catch (err) { alert('Erro ao carregar medições: ' + err.message); }
});

function renderModalMedicoes(d) {
    $el('#med-titulo').textContent = d.contrato;
    if (d.sem_serie) {
        $el('#med-stats').innerHTML = `<div class="col-6 col-md-3"><div class="med-stat"><small>Média</small><strong>${brl(d.media)}</strong></div></div>`;
        $el('#med-sem-serie').hidden = false;
        $el('#med-sem-serie').textContent = d.mensagem || 'Sem série de medições.';
        $el('#med-alerta').hidden = true;
        if (chartMed) chartMed.destroy();
        return;
    }
    $el('#med-sem-serie').hidden = true;
    $el('#med-stats').innerHTML = `
        <div class="col-6 col-md-2"><div class="med-stat"><small>Média</small><strong>${brl(d.media)}</strong></div></div>
        <div class="col-6 col-md-2"><div class="med-stat"><small>Mediana</small><strong>${brl(d.mediana)}</strong></div></div>
        <div class="col-6 col-md-2"><div class="med-stat"><small>Desvio</small><strong>${brl(d.desvio)}</strong></div></div>
        <div class="col-6 col-md-2"><div class="med-stat"><small>CV</small><strong>${d.cv != null ? d.cv.toFixed(2) + '%' : '—'}</strong></div></div>
        <div class="col-6 col-md-2"><div class="med-stat"><small>Faixa ±1σ</small><strong class="small">${brl(d.faixa_1sigma.inf)}<br>${brl(d.faixa_1sigma.sup)}</strong></div></div>
        <div class="col-6 col-md-2"><div class="med-stat"><small>Outliers</small><strong>${d.outliers.length}</strong></div></div>`;
    const labels = d.serie.map(r => dtMesAno(r.mes_referencia));
    const valores = d.serie.map(r => Number(r.valor_medido));
    if (chartMed) chartMed.destroy();
    chartMed = new Chart($el('#med-chart'), {
        type: 'bar',
        data: { labels, datasets: [
            { label:'Medição', data: valores, backgroundColor:'#90caf9' },
            { type:'line', label:'Média', data: valores.map(() => d.media), borderColor:'#0d3b66', borderDash:[6,4], pointRadius:0, fill:false },
        ]},
        options: {
            responsive: true,
            plugins: { tooltip: { callbacks: { label: c => `${c.dataset.label}: ${brl(c.parsed.y)}` } } },
            scales: { y: { ticks: { callback: v => brl(v) } } },
        },
    });
}

// ================================================================
// NOTIFICAÇÕES
// ================================================================
async function atualizarSino() {
    try {
        const data = await apiGet(`notificacoes`);
        const badge = $el('#bell-count');
        const bell  = $el('#bell');
        if (data.nao_lidas > 0) {
            badge.hidden = false; badge.textContent = data.nao_lidas;
            bell.classList.add('has-unread');
        } else {
            badge.hidden = true; bell.classList.remove('has-unread');
        }
    } catch (e) {}
}

$el('#bell').addEventListener('click', async () => {
    await carregarNotificacoes('nao-lidas');
    new bootstrap.Modal($el('#modal-ntf')).show();
});

document.querySelectorAll('#modal-ntf .tab-btn').forEach(t => {
    t.addEventListener('click', () => {
        document.querySelectorAll('#modal-ntf .tab-btn').forEach(x => x.classList.remove('active'));
        t.classList.add('active');
        carregarNotificacoes(t.dataset.tab);
    });
});

async function carregarNotificacoes(filtro) {
    try {
        const qs = new URLSearchParams();
        if (filtro === 'nao-lidas') qs.set('lida', '0');
        const data = await apiGet(`notificacoes?${qs.toString()}`);
        const ul = $el('#ntf-lista');
        ul.innerHTML = '';
        if (!data.itens.length) {
            ul.innerHTML = '<li class="text-muted small p-3">Nenhuma notificação.</li>';
            return;
        }
        data.itens.forEach(n => {
            const li = document.createElement('li');
            if (!n.lida) li.classList.add('nao-lida');
            li.dataset.acao = n.acao_principal || '';
            li.dataset.contrato = n.contrato_numero;
            const faltando = (n.meses_faltantes || []).map(dtCurto).join(', ');
            const tipoLabel = ({ PLANEJAMENTO_INCOMPLETO: 'Sem planejamento completo' })[n.tipo] || n.tipo;
            li.innerHTML = `
                <div class="ntf-icon"><i class="bi bi-exclamation-triangle-fill text-warning"></i></div>
                <div class="ntf-body">
                    <div class="ntf-tipo">${tipoLabel}</div>
                    <div class="ntf-contrato">
                        <strong>Contrato ${n.contrato_numero}</strong>
                    </div>
                    ${n.acao_codigo ? `<div class="ntf-acao">Ação ${n.acao_codigo}</div>` : ''}
                    ${faltando ? `<div class="ntf-meses">Meses sem previsão: ${faltando}</div>` : ''}
                    <div class="ntf-time">${new Date(n.notificado_em).toLocaleString('pt-BR')}</div>
                </div>
                ${n.lida ? '' : `<button class="btn btn-sm btn-outline-secondary ler-btn" data-id="${n.id}"><i class="bi bi-check2"></i></button>`}`;
            ul.appendChild(li);
        });
        ul.querySelectorAll('.ler-btn').forEach(b => b.addEventListener('click', async (e) => {
            e.stopPropagation();
            const fd = new FormData(); fd.append('id', b.dataset.id);
            await fetchAuth(`${API}/notificacoes/marcar_lida`, { method:'POST', body: fd });
            await atualizarSino();
            carregarNotificacoes(document.querySelector('#modal-ntf .tab-btn.active').dataset.tab);
        }));
    } catch (e) {}
}

$el('#btn-marcar-todas').addEventListener('click', async () => {
    await fetchAuth(`${API}/notificacoes/marcar_todas`, { method:'POST' });
    await atualizarSino();
    carregarNotificacoes('nao-lidas');
});

// ================================================================
// DEEP LINK
// ================================================================
async function carregarDoUrl() {
    const params = new URLSearchParams(window.location.search);
    const acaoParam     = params.get('acao');
    const contratoParam = params.get('contrato');
    if (!acaoParam && !contratoParam) return;

    if (acaoParam) {
        const selAcao = $el('#f-acao');
        if (selAcao.querySelector(`option[value="${cssEscape(acaoParam)}"]`)) {
            suppressChange = true;
            $('#f-acao').val(acaoParam).trigger('change.select2');
            suppressChange = false;
            estadoAtual.acaoId = String(acaoParam);
        }
    }
    if (contratoParam) {
        const selC = $el('#f-contrato');
        if (selC.querySelector(`option[value="${cssEscape(contratoParam)}"]`)) {
            suppressChange = true;
            $('#f-contrato').val(contratoParam).trigger('change.select2');
            suppressChange = false;
            estadoAtual.contrato = contratoParam;
            await autoFillFromContrato(contratoParam);
        }
    }
    atualizarBadges();
    aplicarFiltrosTabela();
    if (estadoAtual.contrato) await carregarDetalhe(estadoAtual.contrato);
}

document.addEventListener('DOMContentLoaded', async () => {
    atualizarSino();
    setInterval(atualizarSino, 60_000);
    await carregarBaseEAtualizar();
    await carregarDoUrl();
    if (PODE_SCRAPER) {
        verificarJobAtivo();
        setInterval(verificarJobAtivo, 30_000);
        recarregarBadgeUltimaExtracao();
    }
});

// ================================================================
// IMPORTERS (Dados Contratos / Empenhos)
// ================================================================
(function initImporters() {
    if (!PODE_SCRAPER) return;
    const modalEl = document.getElementById('modal-importer');
    if (!modalEl) return;

    const IMPORTERS = {
        dados: { key:'dados', titulo:'Importar Dados de Contratos', endpoint:'importar_dados_contratos_isa.php' },
        empenhos: { key:'empenhos', titulo:'Importar Empenhos', endpoint:'importar_empenhos_isa.php' }
    };
    const state = { config: null, job: null, timer: null };
    let _modal = null;
    const $id = (id) => document.getElementById(id);
    const _bsModal = () => (_modal = _modal || bootstrap.Modal.getOrCreateInstance(modalEl));

    function setStep(n) { $id('imp-step-1').hidden = n !== 1; $id('imp-step-2').hidden = n !== 2; $id('imp-step-3').hidden = n !== 3; }
    function setProgresso(pct, etapa, info) {
        $id('imp-progresso-bar').style.width = pct + '%';
        $id('imp-progresso-pct').textContent = pct.toFixed(1) + '%';
        $id('imp-etapa-label').textContent = etapa;
        $id('imp-progresso-info').textContent = info || '';
    }
    function setLoginStatus(s) {
        const el = $id('imp-login-status');
        if (!el) return;
        if (s.login_ok === 1) el.innerHTML = '<span class="text-success"><i class="bi bi-check-circle-fill"></i> Login OK</span>';
        else if (s.login_ok === 0) el.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle-fill"></i> Falha no login</span>';
        else el.innerHTML = '<span class="text-muted"><span class="spinner-border spinner-border-sm"></span> Autenticando…</span>';
    }
    function setBtnExecutar(modo) {
        const b = $id('imp-btn-executar'); if (!b) return;
        if (modo === 'disabled') { b.disabled = true; b.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Preparando...'; }
        else if (modo === 'novo') { b.disabled = false; b.innerHTML = '<i class="bi bi-arrow-repeat"></i> Nova importação'; }
        else if (modo === 'retry') { b.disabled = false; b.innerHTML = '<i class="bi bi-arrow-repeat"></i> Tentar novamente'; }
        else { b.disabled = false; b.innerHTML = '<i class="bi bi-play-fill"></i> Executar importação'; }
    }
    function mostrarErro(msg) {
        setStep(3);
        $id('imp-resultado').innerHTML = `<div class="alert alert-danger py-2 mb-0"><i class="bi bi-exclamation-triangle-fill"></i> ${msg}</div>`;
        setBtnExecutar('retry');
        $id('imp-btn-cancelar').textContent = 'Fechar';
    }
    function voltarForm(msg) {
        setStep(1);
        $id('imp-senha').value = '';
        const e = $id('imp-erro'); e.textContent = msg; e.hidden = false;
        setBtnExecutar('inicial');
        $id('imp-btn-cancelar').textContent = 'Cancelar';
    }
    function mostrarResultado(r) {
        setStep(3);
        let html = `<div class="alert alert-success py-2 mb-2"><i class="bi bi-check-circle-fill"></i> <strong>${r.inseridos}</strong> registro(s) importado(s).</div>`;
        if (r.contratos_ignorados > 0) {
            html += `<div class="alert alert-warning py-2 small mb-2"><strong>${r.contratos_ignorados}</strong> contrato(s) ignorado(s).</div>`;
        }
        $id('imp-resultado').innerHTML = html;
        setBtnExecutar('novo');
        $id('imp-btn-cancelar').textContent = 'Fechar';
    }
    async function abrir(config) {
        state.config = config; state.job = null;
        if (state.timer) { clearInterval(state.timer); state.timer = null; }
        $id('imp-titulo').textContent = config.titulo;
        $id('imp-usuario').value = ''; $id('imp-senha').value = '';
        $id('imp-contrato-unico').value = '';
        $id('imp-erro').hidden = true; $id('imp-erro').textContent = '';
        $id('imp-atualizar-ds38').checked = true;
        $id('imp-resultado').innerHTML = '';
        $id('imp-total-contratos').textContent = '—';
        setLoginStatus({ login_ok: null });
        setProgresso(0, 'Iniciando…', '');
        setBtnExecutar('inicial');
        $id('imp-btn-cancelar').textContent = 'Cancelar';
        setStep(1);
        _bsModal().show();
        setTimeout(() => $id('imp-usuario').focus(), 200);
        try {
            const r = await fetchAuth(`${config.endpoint}?acao=ambiente_check`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const t = await r.json();
            $id('imp-total-contratos').textContent = (t && typeof t.contratos_ds38 === 'number') ? `${t.contratos_ds38} contrato(s)` : '—';
        } catch (e) { $id('imp-total-contratos').textContent = '—'; }
    }
    function iniciarPolling() {
        if (!state.job || !state.config) return;
        const endpoint = state.config.endpoint;
        let falhasRede = 0;
        state.timer = setInterval(async () => {
            try {
                const resp = await fetchAuth(`${endpoint}?acao=status&job=${encodeURIComponent(state.job)}`);
                const s = await resp.json();
                falhasRede = 0;
                setLoginStatus(s);
                const p = Math.max(25, Math.min(99, 25 + (Number(s.progresso) || 0) * 0.74));
                setProgresso(p, s.etapa || 'Processando…',
                    (s.contrato_atual ? `Processando: ${s.contrato_atual}` : '') +
                    (s.processados != null && s.total ? ` (${s.processados}/${s.total})` : ''));
                if (s.status === 'concluido') { clearInterval(state.timer); state.timer = null; setProgresso(100, 'Concluído', ''); mostrarResultado(s); }
                else if (s.status === 'erro') { clearInterval(state.timer); state.timer = null; if (s.etapa === 'login' || s.login_ok === 0) voltarForm(s.erro || 'Falha no login.'); else mostrarErro(s.erro || 'Erro no scraper.'); }
            } catch (e) {
                if (++falhasRede >= 10) { clearInterval(state.timer); state.timer = null; mostrarErro('Sem resposta do servidor.'); }
            }
        }, 2000);
    }
    async function executar() {
        const cfg = state.config; if (!cfg) return;
        const usuario = $id('imp-usuario').value.trim();
        const senha = $id('imp-senha').value;
        const contratoUnico = $id('imp-contrato-unico').value.trim();
        if (!usuario || !senha) { $id('imp-erro').textContent = 'Informe usuário e senha.'; $id('imp-erro').hidden = false; return; }
        $id('imp-erro').hidden = true;
        setBtnExecutar('disabled'); setStep(2); setLoginStatus({ login_ok: null });
        setProgresso(25, 'Iniciando scraper…', '');
        try {
            const fd = new FormData();
            fd.append('usuario', usuario); fd.append('senha', senha);
            if (contratoUnico) fd.append('contrato_unico', contratoUnico);
            const resp = await fetchAuth(`${cfg.endpoint}?acao=executar`, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const r = await resp.json();
            if (!r.ok) throw new Error(r.erro || 'Falha ao iniciar');
            $id('imp-senha').value = '';
            state.job = r.job;
            iniciarPolling();
        } catch (e) { mostrarErro(e.message); }
    }
    const bDados = document.getElementById('btn-importar-dados');
    if (bDados) bDados.addEventListener('click', () => abrir(IMPORTERS.dados));
    const bEmp = document.getElementById('btn-importar-empenhos');
    if (bEmp) bEmp.addEventListener('click', () => abrir(IMPORTERS.empenhos));
    $id('imp-btn-executar').addEventListener('click', async () => {
        if (!$id('imp-step-3').hidden) {
            setStep(1); $id('imp-erro').hidden = true; $id('imp-senha').value = '';
            setBtnExecutar('inicial'); $id('imp-btn-cancelar').textContent = 'Cancelar';
            setTimeout(() => $id('imp-senha').focus(), 150);
            return;
        }
        await executar();
    });
})();
</script>
</body>
</html>