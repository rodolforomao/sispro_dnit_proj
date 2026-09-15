<?php
// home.php - Página inicial do sistema

// Verificar se o setor está definido
if (!isset($_SESSION['setor_slug'])) {
    header('Location: selecionar_setor');
    exit;
}

// Garantir que as variáveis estejam definidas
$usuario_nome = $usuario_nome ?? 'Usuário';
$usuario_nivel = $usuario_nivel ?? 'usuario';
$usuario_id = $usuario_id ?? 0;
$usuario_equipe_id = $usuario_equipe_id ?? null;
$setor_slug = $_SESSION['setor_slug'] ?? 'assessoria-projetos';
$setor_nome = $setor_slug === 'assessoria-projetos' ? 'Assessoria e Projetos' : 'Atlas/Monitoramento';
$isAdmin = $isAdmin ?? in_array($usuario_nivel, ['desenvolvedor', 'admin']);
$podeEditar = $podeEditar ?? ($usuario_nivel !== 'leitor');
$pendentes = $pendentes ?? 0;
$totalProcessos = $totalProcessos ?? 0;
$contarFazer = $contarFazer ?? 0;
$contarAtrasados = $contarAtrasados ?? 0;
$processos = $processos ?? [];
$equipes = $equipes ?? [];
$responsaveis = $responsaveis ?? [];
$statuses = $statuses ?? [];
$contratos = $contratos ?? [];
$tipos = $tipos ?? [];
$ufs = $ufs ?? [];
$brs = $brs ?? [];
$filtros = $filtros ?? [];
$total_setores = $total_setores ?? 0;
$default_equipe = $default_equipe ?? null;
$default_responsavel = $default_responsavel ?? null;
$setores_usuario = $setores_usuario ?? [];

// Paginação
$pagina = $pagina ?? 1;
$totalPaginas = $totalPaginas ?? 1;
$porPagina = $porPagina ?? 20;
$totalRegistros = $totalRegistros ?? 0;

// Função para obter iniciais
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

// Construir array de filtros aplicados (com 'param')
$filtrosAplicados = [];
if (!empty($filtros['equipe'])) {
    $nomeEquipe = '';
    foreach ($equipes as $e) {
        if ($e['id'] == $filtros['equipe']) {
            $nomeEquipe = $e['nome'];
            break;
        }
    }
    if ($nomeEquipe) $filtrosAplicados[] = ['param' => 'equipe', 'label' => 'Equipe', 'valor' => $nomeEquipe];
}
if (!empty($filtros['responsavel'])) {
    $nomeResp = '';
    foreach ($responsaveis as $r) {
        if ($r['id'] == $filtros['responsavel']) {
            $nomeResp = $r['nome'];
            break;
        }
    }
    if ($nomeResp) $filtrosAplicados[] = ['param' => 'responsavel', 'label' => 'Responsável', 'valor' => $nomeResp];
}
if (!empty($filtros['status'])) {
    $nomeStatus = '';
    foreach ($statuses as $s) {
        if ($s['id'] == $filtros['status']) {
            $nomeStatus = $s['nome'];
            break;
        }
    }
    if ($nomeStatus) $filtrosAplicados[] = ['param' => 'status', 'label' => 'Status', 'valor' => $nomeStatus];
}
if (!empty($filtros['contrato'])) {
    $numContrato = '';
    foreach ($contratos as $c) {
        if ($c['id'] == $filtros['contrato']) {
            $numContrato = $c['numero'];
            break;
        }
    }
    if ($numContrato) $filtrosAplicados[] = ['param' => 'contrato', 'label' => 'Contrato', 'valor' => $numContrato];
}
if (!empty($filtros['assunto'])) {
    $filtrosAplicados[] = ['param' => 'assunto', 'label' => 'Assunto', 'valor' => $filtros['assunto']];
}
if (!empty($filtros['processo'])) {
    $filtrosAplicados[] = ['param' => 'processo', 'label' => 'Nº Processo', 'valor' => $filtros['processo']];
}
if (!empty($filtros['tipo'])) {
    $nomeTipo = '';
    foreach ($tipos as $t) {
        if ($t['id'] == $filtros['tipo']) {
            $nomeTipo = $t['nome'];
            break;
        }
    }
    if ($nomeTipo) $filtrosAplicados[] = ['param' => 'tipo', 'label' => 'Tipo', 'valor' => $nomeTipo];
}
if (!empty($filtros['uf'])) {
    $filtrosAplicados[] = ['param' => 'uf', 'label' => 'UF', 'valor' => $filtros['uf']];
}
if (!empty($filtros['br'])) {
    $filtrosAplicados[] = ['param' => 'br', 'label' => 'BR', 'valor' => $filtros['br']];
}
?>
<!DOCTYPE html>
<html lang="pt" data-theme="light">
<head>
    <meta charset="UTF-8">
    <title>SISPRO - Sistema de Processos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        /* ============================================================
           VARIÁVEIS DE TEMA
           ============================================================ */
        :root {
            --bg-body: #f8f9fc;
            --bg-card: #ffffff;
            --text-primary: #2c3e50;
            --text-secondary: #6c757d;
            --border-color: #dce1e8;
            --shadow: 0 8px 30px rgba(0,0,0,0.08);
            --table-hover: rgba(0,0,0,0.03);
            --input-bg: #ffffff;
            --input-border: #dce1e8;
            --header-bg: #ffffff;
            --dropdown-bg: #ffffff;
            --dropdown-hover: #f0f4f8;
            --modal-bg: #ffffff;
            --modal-header-bg: #f8f9fc;
        }

        /* ============================================================
           ESTILOS GLOBAIS
           ============================================================ */
        body {
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s, color 0.3s;
            padding-top: 70px;
        }
        .card {
            background: var(--bg-card);
            border: none;
            border-radius: 16px;
            box-shadow: var(--shadow);
            padding: 20px 25px;
            transition: background 0.3s, box-shadow 0.3s;
        }
        .table th {
            color: var(--text-primary);
            border-top: none;
            white-space: nowrap;
        }
        .table td {
            vertical-align: middle;
            word-break: break-word;
            color: var(--text-primary);
        }
        .table-hover tbody tr:hover {
            background-color: var(--table-hover);
        }
        .form-control, .form-select {
            background: var(--input-bg);
            border-color: var(--input-border);
            color: var(--text-primary);
            border-radius: 10px;
        }
        .form-control:focus, .form-select:focus {
            border-color: #4a90e2;
            box-shadow: 0 0 0 0.2rem rgba(74,144,226,0.15);
        }
        .modal-content {
            background: var(--modal-bg);
            color: var(--text-primary);
        }
        .modal-header {
            background: var(--modal-header-bg);
            border-bottom: 1px solid var(--border-color);
        }
        .dropdown-menu {
            background: var(--dropdown-bg);
            border-color: var(--border-color);
        }
        .dropdown-item {
            color: var(--text-primary);
        }
        .dropdown-item:hover {
            background: var(--dropdown-hover);
            color: var(--text-primary);
        }

        /* ============================================================
           BARRA SUPERIOR FIXA
           ============================================================ */
        .topbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1030;
            background: var(--header-bg);
            border-bottom: 1px solid var(--border-color);
            padding: 8px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            transition: background 0.3s, border-color 0.3s;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            min-height: 60px;
        }
        .topbar-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .topbar-left .logo-dnit {
            max-height: 40px;
            width: auto;
        }
        .topbar-left .sistema-titulo {
            font-weight: 700;
            color: #004a8f;
            font-size: 1.2rem;
            letter-spacing: 1px;
        }
        .topbar-center {
            flex: 1;
            text-align: center;
        }
        .topbar-center .badge-setor {
            background: #17a2b8;
            color: #fff;
            font-size: 0.9rem;
            padding: 6px 14px;
        }
        .topbar-right {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .topbar-right .btn-icon {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--text-secondary);
            padding: 0 6px;
            cursor: pointer;
            transition: color 0.2s;
        }
        .topbar-right .btn-icon:hover {
            color: #004a8f;
        }

        /* Avatar com badge de notificação */
        .topbar-right .btn-avatar {
            position: relative;
            background: none;
            border: none;
            padding: 0;
            cursor: pointer;
        }
        .topbar-right .btn-avatar .avatar-icon {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #0d6efd;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1rem;
            text-transform: uppercase;
        }
        .topbar-right .btn-avatar .badge-notificacao {
            position: absolute;
            top: -4px;
            right: -4px;
            background-color: #dc3545;
            color: #fff;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.65rem;
            font-weight: 700;
            line-height: 1;
            border: 2px solid var(--header-bg);
            min-width: 18px;
            text-align: center;
        }

        .dropdown-menu-avatar {
            min-width: 220px;
            padding: 8px 0;
        }
        .dropdown-menu-avatar .dropdown-item {
            padding: 10px 20px;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .dropdown-menu-avatar .dropdown-item i {
            font-size: 1.2rem;
            width: 24px;
            text-align: center;
        }
        .dropdown-menu-avatar .dropdown-header {
            padding: 10px 20px;
            font-weight: 600;
            color: var(--text-primary);
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 4px;
        }
        .dropdown-menu-avatar .dropdown-header small {
            display: block;
            font-weight: 400;
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-top: 2px;
        }

        /* ============================================================
           DEMAIS ESTILOS (tabela, paginação, badges, etc.)
           ============================================================ */
        .table-responsive { overflow-x: auto; }
        .badge-status { padding: 6px 12px; border-radius: 20px; font-weight: 500; }
        .btn-action { border-radius: 8px; padding: 4px 10px; margin: 0 2px; }
        .footer-text { font-size: 0.9rem; color: var(--text-secondary); }
        .comentario-badge { position: relative; cursor: pointer; font-size: 1.3rem; }
        .comentario-badge .badge-dot {
            position: absolute;
            top: -6px;
            right: -6px;
            width: 12px;
            height: 12px;
            background-color: #ffc107;
            border-radius: 50%;
            border: 2px solid white;
            display: none;
        }
        .comentario-badge.has-comentario .badge-dot { display: block; }
        .col-min-width { min-width: 100px; }
        .col-min-width-sm { min-width: 80px; }
        .col-min-width-lg { min-width: 150px; }
        .copiar-ao-clicar {
            cursor: pointer;
            color: #0d6efd;
            text-decoration: none;
        }
        .copiar-ao-clicar:hover { text-decoration: underline; }
        #toastCopiado {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0,0,0,0.85);
            color: #fff;
            padding: 12px 28px;
            border-radius: 30px;
            font-weight: 500;
            font-size: 0.95rem;
            z-index: 99999;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            pointer-events: none;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }
        #toastCopiado.show { opacity: 1; visibility: visible; }
        #toastCopiado i { margin-right: 8px; }
        .badge-status-em-elaboracao { background-color: #FFA500; color: #000; }
        .badge-status-elaborado { background-color: #006400; color: #fff; }
        .badge-status-revisado { background-color: #000; color: #fff; }
        .badge-status-aguardar { background-color: #0000FF; color: #fff; }
        .badge-status-assinado { background-color: #000; color: #fff; }
        .badge-status-marilia { background-color: #800080; color: #fff; }
        .badge-status-concluido { background-color: #000; color: #fff; }
        .text-atrasado { color: #FF0000 !important; font-weight: bold; }
        .text-entregue { color: #000 !important; font-weight: bold; }
        .text-no-prazo { color: #000 !important; font-weight: bold; }
        .icone-alerta { color: #FFC107; font-size: 1.1rem; margin-left: 5px; cursor: help; }
        .comentario-bolha {
            background: #f1f3f5;
            border-radius: 12px;
            padding: 12px 16px;
            margin-bottom: 12px;
            border-left: 4px solid #0d6efd;
            position: relative;
        }
        .comentario-bolha .comentario-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 4px;
        }
        .comentario-bolha .comentario-usuario { font-weight: 700; color: #0d6efd; }
        .comentario-bolha .comentario-data { font-size: 0.8rem; color: var(--text-secondary); }
        .comentario-bolha .comentario-texto { margin-top: 4px; word-wrap: break-word; white-space: pre-wrap; }
        .comentario-bolha .comentario-acoes { display: flex; gap: 4px; margin-left: 10px; }
        .comentario-bolha .comentario-acoes span { cursor: pointer; font-size: 0.9rem; }
        .comentario-bolha .comentario-acoes span:hover { opacity: 0.7; }
        .comentario-bolha .comentario-edit-area { width: 100%; }
        .comentario-bolha .comentario-actions { margin-top: 6px; display: flex; gap: 6px; }
        .pagination .page-link { color: #004a8f; }
        .pagination .page-item.active .page-link { background-color: #004a8f; border-color: #004a8f; color: #fff; }
        .pagination .page-link:hover { background-color: #e9ecef; border-color: #dee2e6; }
        .btn-filtros { border-radius: 30px; padding: 8px 24px; font-weight: 500; }
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
        .badge-filtro .remover-filtro {
            cursor: pointer;
            opacity: 0.7;
        }
        .badge-filtro .remover-filtro:hover { opacity: 1; }
        @media (max-width: 768px) {
            .table-responsive { font-size: 0.85rem; }
            .topbar-left .sistema-titulo { font-size: 1rem; }
            .topbar-left .logo-dnit { max-height: 30px; }
            .topbar-center .badge-setor { font-size: 0.75rem; padding: 4px 10px; }
            .processo-titulo { font-size: 1.3rem; }
            .btn-filtros { padding: 6px 16px; font-size: 0.9rem; }
        }
        .modal-confirm-export .modal-dialog { max-width: 420px; }
        .modal-confirm-export .modal-icon { font-size: 3rem; color: #28a745; text-align: center; margin-bottom: 10px; }
        .modal-confirm-export .modal-title-confirm { text-align: center; font-weight: 600; }
        .modal-confirm-export .modal-body { text-align: center; padding: 25px 30px; }
    </style>
</head>
<?php include APP_PATH . '/public/chat_widget.php'; ?>
<body>
<?php include APP_PATH . '/Views/header.php'; ?>
<!-- ============================================================
     CONTEÚDO PRINCIPAL (mantido igual)
     ============================================================ -->
<div class="container-fluid mt-4">
    <div class="card">
        <!-- Título, contadores e botões -->
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap">
            <div>
                <h2 class="processo-titulo">Processos</h2>
                <div class="mt-1">
                    <span class="badge bg-secondary me-2">Total: <?= $totalProcessos ?></span>
                    <span class="badge bg-warning text-dark me-2">A fazer: <?= $contarFazer ?></span>
                    <span class="badge bg-danger">Atrasados: <?= $contarAtrasados ?></span>
                </div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <?php if ($podeEditar): ?>
                    <a href="create" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Novo Processo</a>
                <?php endif; ?>
                <button type="button" class="btn btn-outline-secondary btn-filtros" data-bs-toggle="modal" data-bs-target="#modalFiltros">
                    <i class="bi bi-funnel"></i> Filtros
                </button>
                <a href="index" class="btn btn-outline-secondary btn-filtros">
                    <i class="bi bi-eraser"></i> Limpar Filtros
                </a>
            </div>
        </div>

        <!-- Filtros aplicados -->
        <?php if (!empty($filtrosAplicados)): ?>
        <div class="mb-3 p-2 bg-light rounded-3 d-flex flex-wrap align-items-center">
            <span class="fw-bold me-2"><i class="bi bi-funnel"></i> Filtros aplicados:</span>
            <?php foreach ($filtrosAplicados as $f): ?>
                <?php
                    $novosGET = $_GET;
                    if (array_key_exists($f['param'], $_GET)) {
                        unset($novosGET[$f['param']]);
                    } else {
                        $novosGET[$f['param']] = '';
                    }
                    unset($novosGET['pagina']);
                    $urlSemFiltro = '?' . http_build_query($novosGET);
                ?>
                <span class="badge-filtro">
                    <?= htmlspecialchars($f['label']) ?>: <?= htmlspecialchars($f['valor']) ?>
                    <a href="<?= $urlSemFiltro ?>" class="remover-filtro text-decoration-none" title="Remover filtro">
                        <i class="bi bi-x-circle"></i>
                    </a>
                </span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- MODAL DE FILTROS -->
        <div class="modal fade modal-filtros" id="modalFiltros" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-funnel"></i> Filtros</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="GET" id="formFiltrosModal">
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Equipe</label>
                                    <select name="equipe" class="form-select select2-filtro">
                                        <option value="">Todas</option>
                                        <?php foreach ($equipes as $e): ?>
                                            <?php
                                                $selected = '';
                                                if ($filtros['equipe'] == $e['id']) {
                                                    $selected = 'selected';
                                                } elseif (empty($filtros['equipe']) && isset($default_equipe) && $default_equipe == $e['id'] && !array_key_exists('equipe', $_GET)) {
                                                    $selected = 'selected';
                                                }
                                            ?>
                                            <option value="<?= $e['id'] ?>" <?= $selected ?>><?= htmlspecialchars($e['nome'] ?? '') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Responsável</label>
                                    <select name="responsavel" class="form-select select2-filtro">
                                        <option value="">Todos</option>
                                        <?php foreach ($responsaveis as $r): ?>
                                            <?php
                                                $selected = '';
                                                if ($filtros['responsavel'] == $r['id']) {
                                                    $selected = 'selected';
                                                } elseif (empty($filtros['responsavel']) && isset($default_responsavel) && $default_responsavel == $r['id'] && !array_key_exists('responsavel', $_GET)) {
                                                    $selected = 'selected';
                                                }
                                            ?>
                                            <option value="<?= $r['id'] ?>" <?= $selected ?>><?= htmlspecialchars($r['nome'] ?? '') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select select2-filtro">
                                        <option value="">Todos</option>
                                        <?php foreach ($statuses as $s): ?>
                                            <option value="<?= $s['id'] ?>" <?= ($filtros['status'] == $s['id']) ? 'selected' : '' ?>><?= htmlspecialchars($s['nome'] ?? '') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Contrato</label>
                                    <select name="contrato" class="form-select select2-filtro">
                                        <option value="">Todos</option>
                                        <?php foreach ($contratos as $c): ?>
                                            <option value="<?= $c['id'] ?>" <?= ($filtros['contrato'] == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['numero'] ?? '') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Assunto</label>
                                    <input type="text" name="assunto" class="form-control" value="<?= htmlspecialchars($filtros['assunto'] ?? '') ?>" placeholder="Palavra-chave">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Processo (nº)</label>
                                    <input type="text" name="processo" class="form-control" value="<?= htmlspecialchars($filtros['processo'] ?? '') ?>" placeholder="Digite o nº">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Tipo</label>
                                    <select name="tipo" class="form-select select2-filtro">
                                        <option value="">Todos</option>
                                        <?php foreach ($tipos as $t): ?>
                                            <option value="<?= $t['id'] ?>" <?= ($filtros['tipo'] == $t['id']) ? 'selected' : '' ?>><?= htmlspecialchars($t['nome'] ?? '') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">UF</label>
                                    <select name="uf" class="form-select select2-filtro">
                                        <option value="">Todas</option>
                                        <?php foreach ($ufs as $uf): ?>
                                            <option value="<?= htmlspecialchars($uf ?? '') ?>" <?= ($filtros['uf'] == $uf) ? 'selected' : '' ?>><?= htmlspecialchars($uf ?? '') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">BR</label>
                                    <select name="br" class="form-select select2-filtro">
                                        <option value="">Todas</option>
                                        <?php foreach ($brs as $br): ?>
                                            <option value="<?= htmlspecialchars($br ?? '') ?>" <?= ($filtros['br'] == $br) ? 'selected' : '' ?>><?= htmlspecialchars($br ?? '') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <input type="hidden" name="pagina" value="1">
                        </div>
                        <div class="modal-footer">
                            <a href="index" class="btn btn-secondary">Limpar</a>
                            <button type="submit" class="btn btn-primary btn-aplicar">Aplicar Filtros</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- MODAL DE CONFIRMAÇÃO EXPORTAR -->
        <div class="modal fade modal-confirm-export" id="modalConfirmExport" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-body">
                        <div class="modal-icon"><i class="bi bi-file-earmark-spreadsheet"></i></div>
                        <h5 class="modal-title-confirm">Exportar CSV</h5>
                        <p class="mt-2">Deseja realmente exportar todos os processos com os filtros atuais?</p>
                        <div class="d-flex justify-content-center gap-3 mt-4">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <a href="#" id="btnConfirmarExportar" class="btn btn-success">Confirmar</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TABELA -->
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <colgroup>
                    <col class="col-min-width">
                    <col class="col-min-width">
                    <col class="col-min-width-sm">
                    <col class="col-min-width-lg">
                    <col style="min-width: 100px;">
                    <col style="min-width: 200px;">
                    <col class="col-min-width">
                    <col style="min-width: 60px;">
                    <col style="min-width: 80px;">
                    <col style="min-width: 110px;">
                    <col style="min-width: 110px;">
                    <col style="min-width: 90px;">
                    <col style="min-width: 70px;">
                    <col style="min-width: 140px;">
                </colgroup>
                <thead>
                    <tr>
                        <th>Equipe</th>
                        <th>Responsável</th>
                        <th>Status</th>
                        <th>Nº Processo</th>
                        <th>SEI Criado 1</th>
                        <th>Assunto</th>
                        <th>Contrato</th>
                        <th>UF</th>
                        <th>BR</th>
                        <th>Data Entrada</th>
                        <th>Prazo</th>
                        <th>Situação</th>
                        <th class="text-center">Coment.</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($processos as $p): ?>
                    <?php
                        $statusNome = $p['status_nome'] ?? '';
                        $statusClasses = [
                            'Em elaboração' => 'badge-status-em-elaboracao',
                            'Elaborado'     => 'badge-status-elaborado',
                            'Revisado'      => 'badge-status-revisado',
                            'Aguardar'      => 'badge-status-aguardar',
                            'Assinado'      => 'badge-status-assinado',
                            'Marília'       => 'badge-status-marilia',
                            'Concluído'     => 'badge-status-concluido'
                        ];
                        $statusClass = $statusClasses[$statusNome] ?? 'bg-secondary';
                        $situacao = '';
                        $classe_situacao = '';
                        $hoje = new DateTime();
                        $hoje->setTime(0, 0, 0);
                        if ($statusNome == 'Assinado') {
                            $situacao = 'Entregue';
                            $classe_situacao = 'text-entregue';
                        } elseif (!empty($p['prazo'])) {
                            $prazo = new DateTime($p['prazo']);
                            $prazo->setTime(0, 0, 0);
                            if ($prazo < $hoje) {
                                $situacao = 'Atrasado';
                                $classe_situacao = 'text-atrasado';
                            } else {
                                $situacao = 'No prazo';
                                $classe_situacao = 'text-no-prazo';
                            }
                        } else {
                            $situacao = 'Sem prazo';
                            $classe_situacao = 'text-muted';
                        }
                        $mostrarAlerta = ($statusNome == 'Revisado' && ($p['cadastrado_sima'] == 0 || $p['cadastrado_sima'] == 'Não'));
                        $tem_comentario = ($p['total_comentarios'] > 0);
                        $seiCriado1 = !empty($p['sei_criado_1']) ? $p['sei_criado_1'] : '-';
                        $numeroProcesso = $p['numero_processo'] ?? '';
                    ?>
                    <tr>
                        <td class="text-nowrap"><?= htmlspecialchars($p['equipe_nome'] ?? '') ?></td>
                        <td class="text-nowrap"><?= htmlspecialchars($p['responsavel_nome'] ?? '') ?></td>
                        <td class="text-nowrap">
                            <span class="badge <?= $statusClass ?>"><?= htmlspecialchars($statusNome) ?></span>
                            <?php if ($mostrarAlerta): ?>
                                <i class="bi bi-exclamation-triangle-fill icone-alerta" title="Necessita Cadastro no SIMA"></i>
                            <?php endif; ?>
                        </td>
                        <td class="text-nowrap">
                            <span class="copiar-ao-clicar" data-copiar="<?= htmlspecialchars($numeroProcesso) ?>" title="Clique para copiar o número do processo">
                                <?= htmlspecialchars($numeroProcesso) ?>
                            </span>
                        </td>
                        <td class="text-nowrap">
                            <?php if ($seiCriado1 !== '-'): ?>
                                <span class="copiar-ao-clicar" data-copiar="<?= htmlspecialchars($seiCriado1) ?>" title="Clique para copiar o SEI">
                                    <?= htmlspecialchars($seiCriado1) ?>
                                </span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($p['assunto'] ?? '') ?></td>
                        <td class="text-nowrap"><?= htmlspecialchars($p['contrato_num'] ?? '') ?></td>
                        <td class="text-nowrap"><?= htmlspecialchars($p['uf'] ?? '') ?></td>
                        <td class="text-nowrap"><?= htmlspecialchars($p['br'] ?? '') ?></td>
                        <td class="text-nowrap"><?= isset($p['data_entrada']) ? date('d/m/Y', strtotime($p['data_entrada'])) : '-' ?></td>
                        <td class="text-nowrap"><?= isset($p['prazo']) && $p['prazo'] ? date('d/m/Y', strtotime($p['prazo'])) : '-' ?></td>
                        <td class="text-nowrap <?= $classe_situacao ?>"><?= $situacao ?></td>
                        <td class="text-center">
                            <span class="comentario-badge <?= $tem_comentario ? 'has-comentario' : '' ?>" 
                                  data-bs-toggle="modal" 
                                  data-bs-target="#modalComentario" 
                                  data-processo-id="<?= $p['id'] ?>"
                                  data-processo-num="<?= htmlspecialchars($p['numero_processo'] ?? '') ?>"
                                  title="Comentários">
                                <i class="bi bi-chat-dots"></i>
                                <span class="badge-dot"></span>
                            </span>
                        </td>
                        <td class="text-center text-nowrap">
                            <a href="view?id=<?= $p['id'] ?>" class="btn btn-outline-secondary btn-action" title="Visualizar"><i class="bi bi-eye"></i></a>
                            <?php if ($podeEditar): ?>
                                <a href="edit?id=<?= $p['id'] ?>" class="btn btn-outline-primary btn-action" title="Editar"><i class="bi bi-pencil"></i></a>
                                <a href="delete?id=<?= $p['id'] ?>" class="btn btn-outline-danger btn-action" title="Excluir" onclick="return confirm('Tem certeza?')"><i class="bi bi-trash"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($processos)): ?>
                    <tr><td colspan="14" class="text-center text-muted py-4">Nenhum processo encontrado.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- PAGINAÇÃO -->
        <?php if ($totalRegistros > $porPagina): ?>
        <nav aria-label="Paginação" class="mt-3">
            <ul class="pagination justify-content-center flex-wrap">
                <?php
                    $totalPaginas = ceil($totalRegistros / $porPagina);
                    $paginaAtual = $pagina ?? 1;
                    $queryParams = $_GET;
                    unset($queryParams['pagina']);
                    $baseUrl = '?' . http_build_query($queryParams);
                ?>
                <li class="page-item <?= ($paginaAtual <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= $baseUrl . '&pagina=1' ?>">«</a>
                </li>
                <li class="page-item <?= ($paginaAtual <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= $baseUrl . '&pagina=' . ($paginaAtual - 1) ?>">‹</a>
                </li>
                <?php
                    $inicio = max(1, $paginaAtual - 2);
                    $fim = min($totalPaginas, $paginaAtual + 2);
                    if ($inicio > 1) {
                        echo '<li class="page-item"><a class="page-link" href="' . $baseUrl . '&pagina=1">1</a></li>';
                        if ($inicio > 2) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                    }
                    for ($i = $inicio; $i <= $fim; $i++) {
                        $ativo = ($i == $paginaAtual) ? 'active' : '';
                        echo '<li class="page-item ' . $ativo . '"><a class="page-link" href="' . $baseUrl . '&pagina=' . $i . '">' . $i . '</a></li>';
                    }
                    if ($fim < $totalPaginas) {
                        if ($fim < $totalPaginas - 1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                        echo '<li class="page-item"><a class="page-link" href="' . $baseUrl . '&pagina=' . $totalPaginas . '">' . $totalPaginas . '</a></li>';
                    }
                ?>
                <li class="page-item <?= ($paginaAtual >= $totalPaginas) ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= $baseUrl . '&pagina=' . ($paginaAtual + 1) ?>">›</a>
                </li>
                <li class="page-item <?= ($paginaAtual >= $totalPaginas) ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= $baseUrl . '&pagina=' . $totalPaginas ?>">»</a>
                </li>
            </ul>
            <div class="text-center text-muted small">
                Mostrando <?= count($processos) ?> de <?= $totalRegistros ?> processos
            </div>
        </nav>
        <?php endif; ?>

        <div class="footer-text text-center mt-4">
            Desenvolvido por <strong>Bruno Pimenta</strong> - Versão 1.0 - <?= date('Y') ?>
        </div>
    </div>
</div>

<!-- Toast e Modal de Comentários (mantidos) -->
<div id="toastCopiado"><i class="bi bi-check-circle-fill text-success"></i> Copiado!</div>

<div class="modal fade modal-comentario" id="modalComentario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-chat-dots"></i> Histórico de Comentários - Processo <span id="modal-processo-num"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="lista-comentarios"><p class="text-muted text-center">Carregando...</p></div>
                <hr>
                <form id="form-comentario">
                    <input type="hidden" id="comentario-processo-id">
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

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    var usuarioNivel = <?= json_encode($usuario_nivel) ?>;
    var usuarioId = <?= json_encode($usuario_id) ?>;

    // SELECT2
    $('.select2-filtro').select2({
        width: '100%',
        allowClear: true,
        placeholder: 'Selecione...',
        dropdownAutoWidth: true,
        dropdownParent: $('#modalFiltros')
    });

    $(document).on('select2:open', function() {
        setTimeout(function() {
            var searchField = document.querySelector('.select2-search__field');
            if (searchField) searchField.focus();
        }, 200);
    });

    // Exportar CSV
    var urlExportar = 'exportar_csv?<?= http_build_query($_GET) ?>';
    $('#btnExportarCSV').on('click', function(e) {
        e.preventDefault();
        var modalConfirm = new bootstrap.Modal(document.getElementById('modalConfirmExport'));
        modalConfirm.show();
        $('#btnConfirmarExportar').attr('href', urlExportar);
    });

    // Toast e copiar
    var toastTimer = null;
    function mostrarToast(mensagem) {
        var toast = document.getElementById('toastCopiado');
        if (!toast) return;
        toast.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i> ' + mensagem;
        toast.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function() { toast.classList.remove('show'); }, 2000);
    }

    function copiarTexto(texto) {
        if (!texto || texto === '-') { mostrarToast('Nada para copiar'); return; }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(texto)
                .then(function() { mostrarToast('Copiado: ' + texto); })
                .catch(function() { fallbackCopiar(texto); });
        } else {
            fallbackCopiar(texto);
        }
    }

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
            if (sucesso) mostrarToast('Copiado: ' + texto);
            else mostrarToast('Erro ao copiar');
        } catch (e) { mostrarToast('Erro ao copiar'); }
        document.body.removeChild(textarea);
    }

    $(document).on('click', '.copiar-ao-clicar', function() {
        var texto = $(this).data('copiar');
        copiarTexto(texto);
    });

    // Comentários
    $('#modalComentario').on('show.bs.modal', function(event) {
        var trigger = $(event.relatedTarget);
        var processoId = trigger.data('processo-id');
        var processoNum = trigger.data('processo-num');
        $('#modal-processo-num').text(processoNum);
        $('#comentario-processo-id').val(processoId);
        carregarComentarios(processoId);
    });

    function carregarComentarios(processoId) {
        $('#lista-comentarios').html('<p class="text-muted text-center">Carregando...</p>');
        $.ajax({
            url: 'ajax_comentarios.php',
            method: 'GET',
            data: { action: 'get', processo_id: processoId },
            dataType: 'json',
            success: function(data) {
                var html = '';
                if (!data || data.length === 0) {
                    html = '<p class="text-muted text-center">Nenhum comentário ainda.</p>';
                } else {
                    for (var i = 0; i < data.length; i++) {
                        var c = data[i];
                        var texto = c.comentario || '';
                        var podeEditar = (usuarioNivel === 'desenvolvedor' || usuarioNivel === 'admin' || c.usuario_id == usuarioId);
                        var podeExcluir = (usuarioNivel === 'desenvolvedor' || usuarioNivel === 'admin');
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
                                    var processoId = $('#comentario-processo-id').val();
                                    carregarComentarios(processoId);
                                } else {
                                    alert('Erro ao editar: ' + (res.error || 'Desconhecido'));
                                }
                            },
                            error: function() { alert('Erro de comunicação.'); }
                        });
                    });
                    botoes.find('.cancelar-edicao').on('click', function() {
                        var processoId = $('#comentario-processo-id').val();
                        carregarComentarios(processoId);
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
                                    var processoId = $('#comentario-processo-id').val();
                                    carregarComentarios(processoId);
                                    atualizarBadge(processoId);
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

    function atualizarBadge(processoId) {
        $.ajax({
            url: 'ajax_comentarios.php',
            method: 'GET',
            data: { action: 'get', processo_id: processoId, count_only: 1 },
            dataType: 'json',
            success: function(data) {
                var total = data.total || 0;
                var badge = $('.comentario-badge[data-processo-id="' + processoId + '"]');
                if (total > 0) badge.addClass('has-comentario');
                else badge.removeClass('has-comentario');
            }
        });
    }

    $('#form-comentario').on('submit', function(e) {
        e.preventDefault();
        var processoId = $('#comentario-processo-id').val();
        var comentario = $('#novo-comentario').val().trim();
        if (!comentario) return;
        $.ajax({
            url: 'ajax_comentarios.php',
            method: 'POST',
            data: { action: 'adicionar', processo_id: processoId, comentario: comentario },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    $('#novo-comentario').val('');
                    carregarComentarios(processoId);
                    var badge = $('.comentario-badge[data-processo-id="' + processoId + '"]');
                    badge.addClass('has-comentario');
                } else {
                    alert('Erro: ' + (res.error || 'Desconhecido'));
                }
            },
            error: function() { alert('Erro de comunicação.'); }
        });
    });

    function escaparHtml(texto) {
        return $('<div>').text(texto).html();
    }
});
</script>
</body>
</html>