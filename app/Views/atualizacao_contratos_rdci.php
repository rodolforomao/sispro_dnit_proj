<?php
// View atualizacao_contratos_rdci.php - Atualização de Contratos RDCI SUPRA

if (!function_exists('obterIniciais')) {
    function obterIniciais($nome) {
        $partes = explode(' ', trim($nome));
        $iniciais = '';
        foreach ($partes as $parte) {
            if (!empty($parte)) $iniciais .= strtoupper(substr($parte, 0, 1));
        }
        return substr($iniciais, 0, 2);
    }
}

if (!function_exists('renderProcessosCopiaveis')) {
    function renderProcessosCopiaveis($valor) {
        if ($valor === null || trim((string)$valor) === '') {
            return '—';
        }
        $linhas = preg_split('/\r\n|\r|\n/', (string)$valor);
        $linhas = array_filter(array_map('trim', $linhas), function ($l) {
            return $l !== '';
        });
        if (empty($linhas)) {
            return '—';
        }
        $out = [];
        foreach ($linhas as $linha) {
            $out[] = '<span class="copiar-ao-clicar"'
                   . ' data-copiar="' . htmlspecialchars($linha, ENT_QUOTES) . '"'
                   . ' title="Clique para copiar">'
                   . htmlspecialchars($linha)
                   . '</span>';
        }
        return implode('<br>', $out);
    }
}

if (!function_exists('getBadgeSituacaoClass')) {
    function getBadgeSituacaoClass($situacao) {
        if ($situacao === null || trim((string)$situacao) === '') {
            return 'badge-situacao badge-situacao-cinza';
        }

        $s = strtoupper(trim((string)$situacao));
        $s = preg_replace('/\s+/', ' ', $s);

        if (strpos($s, 'CONCLU') !== false
            || strpos($s, 'FINALIZ') !== false
            || strpos($s, 'APROVAD') !== false) {
            return 'badge-situacao badge-situacao-verde';
        }
        if (strpos($s, 'ANDAMENTO') !== false
            || strpos($s, 'EXECU') !== false) {
            return 'badge-situacao badge-situacao-azul';
        }
        if (strpos($s, 'PARALIS') !== false
            || strpos($s, 'SUSPENS') !== false) {
            return 'badge-situacao badge-situacao-vermelho';
        }
        if (strpos($s, 'CANCEL') !== false
            || strpos($s, 'RESCIN') !== false) {
            return 'badge-situacao badge-situacao-preto';
        }
        if (strpos($s, 'FALTAM') !== false
            || strpos($s, 'AGUARD') !== false) {
            return 'badge-situacao badge-situacao-laranja';
        }
        if (strpos($s, 'NAO ENTREG') !== false
            || strpos($s, 'NÃO ENTREG') !== false
            || strpos($s, 'NAO INICI') !== false
            || strpos($s, 'NÃO INICI') !== false
            || strpos($s, 'FORA DO ESCOPO') !== false) {
            return 'badge-situacao badge-situacao-cinza';
        }
        return 'badge-situacao badge-situacao-amarelo';
    }
}

$usuario_nome  = $usuario_nome  ?? $_SESSION['usuario_nome']  ?? 'Usuário';
$usuario_nivel = $usuario_nivel ?? $_SESSION['usuario_nivel'] ?? 'usuario';
$usuario_id    = $usuario_id    ?? $_SESSION['usuario_id']    ?? 0;
$setor_slug    = $_SESSION['setor_slug'] ?? 'assessoria-projetos';
$setor_nome    = $setor_slug === 'assessoria-projetos' ? 'Assessoria e Projetos' : 'Atlas/Monitoramento';
$isAdmin       = in_array($usuario_nivel, ['desenvolvedor', 'admin']);

$pendentes = 0;
if ($isAdmin) {
    try {
        global $pdo;
        $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE status = 'pendente'");
        $pendentes = (int)$stmt->fetchColumn();
    } catch (Exception $e) { $pendentes = 0; }
}

$total_setores = 0;
if ($usuario_id) {
    try {
        global $pdo;
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuario_setor WHERE usuario_id = ?");
        $stmt->execute([$usuario_id]);
        $total_setores = (int)$stmt->fetchColumn();
    } catch (Exception $e) { $total_setores = 0; }
}

$setores_usuario = [];
if ($usuario_id) {
    try {
        global $pdo;
        $stmt = $pdo->prepare("
            SELECT s.nome FROM setores s 
            INNER JOIN usuario_setor us ON s.id = us.setor_id 
            WHERE us.usuario_id = ?
        ");
        $stmt->execute([$usuario_id]);
        $setores_usuario = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) { $setores_usuario = []; }
}

// Filtros da URL
$filtroUF       = $_GET['uf']              ?? '';
$filtroBR       = $_GET['br']              ?? '';
$filtroContrato = $_GET['contrato']        ?? '';
$filtroSituacao = $_GET['situacao_projeto']?? '';
$filtroBusca    = $_GET['busca']           ?? '';

// ============================================================
// ✅ CONTADORES (vêm do controller)
//    $totalLinhas   = total de linhas brutas
//    $totalContratos = contratos únicos (por instrumento)
// ============================================================
$totalLinhas    = $totalLinhas    ?? count($contratos ?? []);
$totalContratos = $totalContratos ?? 0;

// ============================================================
// BADGES DE FILTROS APLICADOS
// ============================================================
$filtrosAplicados = [];
if (!empty($filtroUF))       $filtrosAplicados[] = ['param' => 'uf', 'label' => 'UF', 'valor' => $filtroUF];
if (!empty($filtroBR))       $filtrosAplicados[] = ['param' => 'br', 'label' => 'BR', 'valor' => $filtroBR];
if (!empty($filtroContrato)) $filtrosAplicados[] = ['param' => 'contrato', 'label' => 'Contrato', 'valor' => $filtroContrato];
if (!empty($filtroSituacao)) $filtrosAplicados[] = ['param' => 'situacao_projeto', 'label' => 'Situação do Projeto', 'valor' => $filtroSituacao];
if (!empty($filtroBusca))    $filtrosAplicados[] = ['param' => 'busca', 'label' => 'Busca', 'valor' => $filtroBusca];
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Atualização de Contratos RDCI - SISPRO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        body { background: #f8f9fc; padding-top: 70px; }
        .container-fluid { max-width: 98%; }
        .card { border: none; border-radius: 16px; box-shadow: 0 8px 30px rgba(0,0,0,0.08); background: #fff; padding: 20px 25px; }
        .table th { font-weight: 600; color: #2c3e50; white-space: nowrap; font-size: 0.85rem; }
        .table td { vertical-align: middle; font-size: 0.85rem; }
        .sistema-titulo { font-weight: 700; color: #004a8f; font-size: 1.4rem; letter-spacing: 1px; }
        .btn-sm { padding: 0.2rem 0.5rem; font-size: 0.75rem; }
        .table-responsive { overflow-x: auto; }

        .col-checkbox      { width: 40px; min-width: 40px; max-width: 40px; }
        .col-contrato      { min-width: 130px; white-space: nowrap; }
        .col-br            { min-width: 70px;  white-space: nowrap; }
        .col-uf            { min-width: 55px;  white-space: nowrap; }
        .col-lote          { min-width: 90px;  white-space: nowrap; }
        .col-nome-usual    { min-width: 180px; }
        .col-subtrecho     { min-width: 150px; max-width: 250px; }
        .col-processo      { min-width: 160px; }
        .col-situacao      { min-width: 140px; }
        .col-acoes         { min-width: 70px;  white-space: nowrap; text-align: center; }

        /* ✅ Info-count no padrão da página de Notificações */
        .info-count { font-size: 0.9rem; color: #6c757d; margin-top: 0.5rem; }
        .info-count strong { color: #2c3e50; }
        .info-count .sep { margin: 0 10px; color: #cfd7ee; }

        /* Badge de situação */
        .badge-situacao {
            padding: 4px 10px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.72rem;
            white-space: nowrap;
            display: inline-block;
            border: 1px solid transparent;
        }
        .badge-situacao-verde    { background: #d1e7dd; color: #0a3622; border-color: #a3cfbb; }
        .badge-situacao-azul     { background: #cfe2ff; color: #084298; border-color: #9ec5fe; }
        .badge-situacao-vermelho { background: #f8d7da; color: #58151c; border-color: #f1aeb5; }
        .badge-situacao-cinza    { background: #e2e3e5; color: #41464b; border-color: #c4cbcf; }
        .badge-situacao-preto    { background: #212529; color: #ffffff; border-color: #000000; }
        .badge-situacao-laranja  { background: #ffe5d0; color: #984c0c; border-color: #fecba1; }
        .badge-situacao-amarelo  { background: #fff3cd; color: #664d03; border-color: #ffecb5; }

        /* Badges de filtros */
        .badge-filtro {
            background: #e9ecef; color: #2c3e50; font-weight: 500;
            padding: 6px 12px; border-radius: 20px; margin-right: 8px; margin-bottom: 4px;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .badge-filtro .remover-filtro { cursor: pointer; opacity: 0.7; }
        .badge-filtro .remover-filtro:hover { opacity: 1; }

        .select2-container .select2-selection--single { height: 38px; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 38px; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px; }
        .select2-container .select2-selection--single .select2-selection__clear { display: none; }

        .table-wrapper { position: relative; max-height: 620px; overflow: auto; }
        .table-wrapper table thead th { position: sticky; top: 0; z-index: 10; background: #fff; border-bottom: 2px solid #dee2e6; }
        .table-wrapper table thead th:first-child { position: sticky; left: 0; z-index: 11; background: #fff; }
        .table-wrapper table tbody td:first-child { position: sticky; left: 0; z-index: 5; background: #fff; }
        .table-wrapper table tbody tr.linha-marcada td:first-child { background: #d4edda; }
        .table-wrapper table tbody tr.linha-marcada { background-color: #d4edda !important; }
        .table-wrapper table tbody tr.linha-marcada td { background-color: #d4edda !important; }

        @media (max-width: 1199px) { .filtro-col { flex: 0 0 33.333%; max-width: 33.333%; } }
        @media (max-width: 767px) { .filtro-col { flex: 0 0 50%; max-width: 50%; } }
        @media (max-width: 575px) { .filtro-col { flex: 0 0 100%; max-width: 100%; } }

        /* Topbar */
        .topbar {
            position: fixed; top: 0; left: 0; right: 0; z-index: 1030;
            background: #ffffff; border-bottom: 1px solid #dce1e8;
            padding: 8px 20px; display: flex; align-items: center;
            justify-content: space-between; flex-wrap: wrap;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); min-height: 60px;
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

        /* Copiar ao clicar */
        .copiar-ao-clicar {
            cursor: pointer;
            color: #0d6efd;
            text-decoration: none;
        }
        .copiar-ao-clicar:hover { text-decoration: underline; }

        /* Toast "Copiado!" */
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
                <h2 class="sistema-titulo"><i class="bi bi-arrow-repeat"></i> Atualização de Contratos RDCI - SUPRA</h2>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <button class="btn btn-outline-success btn-sm" id="btnAtualizarMeio">
                    <i class="bi bi-tree"></i> Atualizar Meio Ambiente
                </button>
                <button class="btn btn-outline-primary btn-sm" id="btnAtualizarResumos">
                    <i class="bi bi-file-text"></i> Atualizar Resumos
                </button>
                <button class="btn btn-outline-warning btn-sm" id="btnAtualizarRdci">
                    <i class="bi bi-database"></i> Atualizar RDCIs
                </button>
                <button class="btn btn-success btn-sm" id="btnAtualizarTudo">
                    <i class="bi bi-cloud-arrow-down"></i> Atualizar Tudo
                </button>
                <a href="index.php" class="btn btn-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i> Voltar
                </a>
            </div>
        </div>

        <!-- Badges de filtros aplicados -->
        <?php if (!empty($filtrosAplicados)): ?>
        <div class="mb-3 p-2 bg-light rounded-3 d-flex flex-wrap align-items-center">
            <span class="fw-bold me-2"><i class="bi bi-funnel"></i> Filtros aplicados:</span>
            <?php foreach ($filtrosAplicados as $f): ?>
                <?php
                    $novosGET = $_GET;
                    unset($novosGET[$f['param']]);
                    $urlSemFiltro = '?' . http_build_query($novosGET);
                ?>
                <span class="badge-filtro">
                    <?= htmlspecialchars($f['label']) ?>: <?= htmlspecialchars($f['valor']) ?>
                    <a href="<?= $urlSemFiltro ?>" class="remover-filtro text-decoration-none" title="Remover filtro">
                        <i class="bi bi-x-circle"></i>
                    </a>
                </span>
            <?php endforeach; ?>
            <a href="atualizacoes_rdci" class="btn btn-sm btn-outline-secondary ms-2">Limpar todos</a>
        </div>
        <?php endif; ?>

        <!-- Filtros -->
        <form method="GET" class="row g-2 mb-3 align-items-end" id="filtrosForm">
            <div class="col-lg-2 col-md-3 col-sm-6 col-12 filtro-col">
                <label class="form-label" style="font-size:0.75rem;">UF</label>
                <select name="uf" class="form-select filtro-select2">
                    <option value="">Selecione...</option>
                    <?php foreach ($ufs as $uf): ?>
                        <option value="<?= htmlspecialchars($uf) ?>" <?= ($_GET['uf'] ?? '') == $uf ? 'selected' : '' ?>><?= htmlspecialchars($uf) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-2 col-md-3 col-sm-6 col-12 filtro-col">
                <label class="form-label" style="font-size:0.75rem;">BR</label>
                <select name="br" class="form-select filtro-select2">
                    <option value="">Selecione...</option>
                    <?php foreach ($brs as $br): ?>
                        <option value="<?= htmlspecialchars($br) ?>" <?= ($_GET['br'] ?? '') == $br ? 'selected' : '' ?>><?= htmlspecialchars($br) ?></option>
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
                <label class="form-label" style="font-size:0.75rem;">Situação do Projeto</label>
                <select name="situacao_projeto" class="form-select filtro-select2">
                    <option value="">Selecione...</option>
                    <?php foreach ($situacoesProjeto as $s): ?>
                        <option value="<?= htmlspecialchars($s) ?>" <?= ($_GET['situacao_projeto'] ?? '') == $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-3 col-md-6 col-sm-12 col-12 filtro-col">
                <label class="form-label" style="font-size:0.75rem;">Buscar</label>
                <input type="text" name="busca" class="form-control" value="<?= htmlspecialchars($_GET['busca'] ?? '') ?>" placeholder="Contrato, obra, empresa, subtrecho...">
            </div>
            <div class="col-lg-1 col-md-3 col-sm-6 col-12 filtro-col">
                <button type="submit" class="btn btn-primary w-100" style="height:38px;"><i class="bi bi-search"></i></button>
            </div>
            <div class="col-12 d-none">
                <input type="submit">
            </div>
        </form>

        <!-- ✅ CONTADOR — LINHAS E CONTRATOS (padrão da Notificações) -->
        <div class="info-count mb-3">
            <i class="bi bi-list-ul"></i>
            <strong>Contratos:</strong> <?= $totalContratos ?>
            <span class="sep">|</span>
            <strong>Linhas:</strong> <?= $totalLinhas ?>
        </div>

        <!-- Tabela -->
        <div class="table-wrapper">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th class="col-checkbox text-center"><input type="checkbox" id="selecionarTodos" title="Selecionar todos"></th>
                        <th class="col-contrato">Contrato</th>
                        <th class="col-br">BR</th>
                        <th class="col-uf">UF</th>
                        <th class="col-lote">Lote</th>
                        <th class="col-nome-usual">Nome Usual</th>
                        <th class="col-subtrecho">Subtrecho</th>
                        <th class="col-processo">Processo Base</th>
                        <th class="col-processo">Processo de Projetos</th>
                        <th class="col-situacao">Situação do Projeto</th>
                        <th class="col-acoes">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($contratos)): ?>
                        <tr><td colspan="11" class="text-center text-muted py-4">Nenhum contrato encontrado.</td></tr>
                    <?php else: ?>
                        <?php foreach ($contratos as $c): ?>
                        <?php $badgeClass = getBadgeSituacaoClass($c['situacao_projeto'] ?? ''); ?>
                        <tr data-contrato-id="<?= $c['id'] ?>">
                            <td class="text-center">
                                <input type="checkbox" class="checkbox-selecionar" data-contrato-id="<?= $c['id'] ?>">
                            </td>
                            <td class="col-contrato"><strong><?= htmlspecialchars($c['instrumento'] ?? '') ?></strong></td>
                            <td class="col-br"><?= htmlspecialchars($c['br'] ?? '') ?></td>
                            <td class="col-uf"><?= htmlspecialchars($c['uf'] ?? '') ?></td>
                            <td class="col-lote"><?= htmlspecialchars($c['lote'] ?? '') ?></td>
                            <td class="col-nome-usual"><?= htmlspecialchars($c['nome_usual'] ?? '') ?></td>
                            <td class="col-subtrecho"><?= htmlspecialchars($c['subtrecho'] ?? '') ?></td>

                            <td class="col-processo">
                                <?= renderProcessosCopiaveis($c['processo_base'] ?? '') ?>
                            </td>

                            <td class="col-processo">
                                <?= renderProcessosCopiaveis($c['processo_projeto'] ?? '') ?>
                            </td>

                            <td class="col-situacao">
                                <span class="<?= htmlspecialchars($badgeClass) ?>">
                                    <?= htmlspecialchars($c['situacao_projeto'] ?? '—') ?>
                                </span>
                            </td>
                            <td class="col-acoes">
                                <button class="btn btn-outline-info btn-sm" onclick="verDetalhesSupra(<?= $c['id'] ?>)" title="Visualizar detalhes">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Toast -->
<div id="toastAtualizacao" style="position:fixed; bottom:30px; left:50%; transform:translateX(-50%); background:rgba(0,0,0,0.85); color:#fff; padding:12px 28px; border-radius:30px; font-weight:500; font-size:0.95rem; z-index:99999; box-shadow:0 4px 12px rgba(0,0,0,0.3); pointer-events:none; opacity:0; visibility:hidden; transition:opacity 0.3s, visibility 0.3s;">
    <i class="bi bi-check-circle-fill text-success"></i> <span id="toastMsg"></span>
</div>

<!-- Toast "Copiado!" -->
<div id="toastCopiado"><i class="bi bi-check-circle-fill text-success"></i> Copiado!</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    // ============================================================
    // SELECT2 — auto-submit nos filtros
    // ============================================================
    $('.filtro-select2').select2({ placeholder: 'Selecione...', allowClear: false, width: '100%' });
    $('.filtro-select2').on('change', function() { $(this).closest('form').submit(); });
    $('.filtro-select2').on('select2:open', function() {
        setTimeout(function() {
            var f = document.querySelector('.select2-search__field');
            if (f) f.focus();
        }, 100);
    });
    $('.filtro-select2').on('focus', function() { $(this).select2('open'); });

    // ============================================================
    // CHECKBOXES persistentes
    // ============================================================
    var STORAGE_KEY = 'rdci_atualizacao_contratos_marcados';
    var marcadosSet = new Set();
    try {
        var salvos = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
        salvos.forEach(function(id) { marcadosSet.add(String(id)); });
    } catch (e) { marcadosSet = new Set(); }

    function salvarMarcados() {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(Array.from(marcadosSet)));
    }

    function aplicarMarcadosVisiveis() {
        $('.checkbox-selecionar').each(function() {
            var id = String($(this).data('contrato-id'));
            if (marcadosSet.has(id)) {
                $(this).prop('checked', true);
                $(this).closest('tr').addClass('linha-marcada');
            }
        });
    }
    aplicarMarcadosVisiveis();

    $(document).on('change', '.checkbox-selecionar', function() {
        var id = String($(this).data('contrato-id'));
        var $row = $(this).closest('tr');
        if (this.checked) { marcadosSet.add(id); $row.addClass('linha-marcada'); }
        else { marcadosSet.delete(id); $row.removeClass('linha-marcada'); }
        salvarMarcados();
    });
    $('#selecionarTodos').on('change', function() {
        var isChecked = this.checked;
        $('.checkbox-selecionar').each(function() {
            var id = String($(this).data('contrato-id'));
            if (isChecked) { marcadosSet.add(id); $(this).prop('checked', true); $(this).closest('tr').addClass('linha-marcada'); }
            else { marcadosSet.delete(id); $(this).prop('checked', false); $(this).closest('tr').removeClass('linha-marcada'); }
        });
        salvarMarcados();
    });

    // ============================================================
    // TOAST de atualização
    // ============================================================
    var toastTimer = null;
    function mostrarToast(msg, tipo) {
        var t = document.getElementById('toastAtualizacao');
        var icone = tipo === 'erro' ? 'bi-x-circle-fill text-danger' : 'bi-check-circle-fill text-success';
        t.innerHTML = '<i class="bi ' + icone + '"></i> <span>' + msg + '</span>';
        t.style.opacity = '1';
        t.style.visibility = 'visible';
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function() {
            t.style.opacity = '0';
            t.style.visibility = 'hidden';
        }, 4000);
    }

    // ============================================================
    // COPIAR AO CLICAR
    // ============================================================
    var toastCopiadoTimer = null;

    function mostrarToastCopiado(mensagem) {
        var toast = document.getElementById('toastCopiado');
        if (!toast) return;
        toast.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i> ' + mensagem;
        toast.classList.add('show');
        clearTimeout(toastCopiadoTimer);
        toastCopiadoTimer = setTimeout(function() { toast.classList.remove('show'); }, 2000);
    }

    function copiarTexto(texto) {
        if (!texto || texto === '-') { mostrarToastCopiado('Nada para copiar'); return; }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(texto)
                .then(function() { mostrarToastCopiado('Copiado: ' + texto); })
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
            if (sucesso) mostrarToastCopiado('Copiado: ' + texto);
            else mostrarToastCopiado('Erro ao copiar');
        } catch (e) {
            mostrarToastCopiado('Erro ao copiar');
        }
        document.body.removeChild(textarea);
    }

    $(document).on('click', '.copiar-ao-clicar', function() {
        var texto = $(this).data('copiar');
        copiarTexto(texto);
    });

    // ============================================================
    // BOTÕES DE ATUALIZAÇÃO
    // ============================================================
    function executarImport(url, nomeBotao, callback) {
        $.ajax({
            url: url,
            method: 'GET',
            timeout: 300000,
            success: function() { callback(true); },
            error: function() { callback(false); }
        });
    }

    $('#btnAtualizarMeio').on('click', function() {
        if (!confirm('Atualizar dados de Meio Ambiente (Dataset 39)?')) return;
        var btn = this;
        btn.disabled = true;
        var original = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Atualizando...';
        executarImport('importar_supra_dataset_meio_ambiente.php', 'Meio Ambiente', function(ok) {
            btn.disabled = false;
            btn.innerHTML = original;
            if (ok) { mostrarToast('Meio Ambiente atualizado!'); setTimeout(function(){ location.reload(); }, 1200); }
            else { mostrarToast('Erro ao atualizar Meio Ambiente.', 'erro'); }
        });
    });

    $('#btnAtualizarResumos').on('click', function() {
        if (!confirm('Atualizar Resumos (Dataset 40)?')) return;
        var btn = this;
        btn.disabled = true;
        var original = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Atualizando...';
        executarImport('importar_supra_dataset_resumo.php', 'Resumos', function(ok) {
            btn.disabled = false;
            btn.innerHTML = original;
            if (ok) { mostrarToast('Resumos atualizados!'); setTimeout(function(){ location.reload(); }, 1200); }
            else { mostrarToast('Erro ao atualizar Resumos.', 'erro'); }
        });
    });

    $('#btnAtualizarRdci').on('click', function() {
        if (!confirm('Atualizar dados dos Contratos RDCI?')) return;
        var btn = this;
        btn.disabled = true;
        var original = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Atualizando...';
        executarImport('importar_rdci_api.php', 'RDCIs', function(ok) {
            btn.disabled = false;
            btn.innerHTML = original;
            if (ok) { mostrarToast('RDCIs atualizados!'); setTimeout(function(){ location.reload(); }, 1200); }
            else { mostrarToast('Erro ao atualizar RDCIs.', 'erro'); }
        });
    });

    $('#btnAtualizarTudo').on('click', function() {
        if (!confirm('Atualizar TUDO (Meio Ambiente + Resumos + RDCIs)? Isso pode demorar.')) return;
        var btn = this;
        btn.disabled = true;
        var original = btn.innerHTML;

        function proximo(i) {
            if (i > 2) {
                btn.disabled = false;
                btn.innerHTML = original;
                mostrarToast('Todos os dados foram atualizados!');
                setTimeout(function() { location.reload(); }, 1500);
                return;
            }
            var urls  = ['importar_supra_dataset_meio_ambiente.php', 'importar_supra_dataset_resumo.php', 'importar_rdci_api.php'];
            var nomes = ['Meio Ambiente', 'Resumos', 'RDCIs'];
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Atualizando ' + nomes[i] + '...';
            $.ajax({
                url: urls[i],
                method: 'GET',
                timeout: 300000,
                success: function() { proximo(i + 1); },
                error: function() {
                    btn.disabled = false;
                    btn.innerHTML = original;
                    mostrarToast('Erro ao atualizar ' + nomes[i] + '.', 'erro');
                }
            });
        }
        proximo(0);
    });
});
</script>

<?php include APP_PATH . '/Views/partials/modal_atualizacao_supra.php'; ?>
</body>
</html>