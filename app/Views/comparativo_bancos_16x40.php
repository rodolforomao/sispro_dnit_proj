<?php
// View comparativo_bancos_16x40.php — Slides Atlas (40) × Base GEO (16)
// Match por FID

if (!function_exists('obterIniciais')) {
    function obterIniciais($nome) {
        $partes = explode(' ', trim($nome)); $ini = '';
        foreach ($partes as $p) { if (!empty($p)) $ini .= strtoupper(substr($p, 0, 1)); }
        return substr($ini, 0, 2);
    }
}

if (!function_exists('obterUltimaSincronizacao')) {
    function obterUltimaSincronizacao() {
        $base = defined('APP_PATH') ? APP_PATH : dirname(__DIR__, 2);
        $arq  = $base . '/public/.sync_ultima_execucao';
        if (file_exists($arq)) return date('d/m/Y H:i', filemtime($arq));
        $log = $base . '/public/importacao_cron.log';
        if (file_exists($log)) return date('d/m/Y H:i', filemtime($log));
        return '—';
    }
}

$usuario_nome  = $usuario_nome  ?? $_SESSION['usuario_nome']  ?? 'Usuário';
$usuario_nivel = $usuario_nivel ?? $_SESSION['usuario_nivel'] ?? 'usuario';
$usuario_id    = $usuario_id    ?? $_SESSION['usuario_id']    ?? 0;
$setor_slug    = $_SESSION['setor_slug'] ?? 'atlas-monitoramento';
$setor_nome    = $setor_slug === 'assessoria-projetos' ? 'Assessoria e Projetos' : 'Atlas/Monitoramento';
$isAdmin       = in_array($usuario_nivel, ['desenvolvedor', 'admin']);

$pendentes = 0;
if ($isAdmin) {
    try { global $pdo; $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE status = 'pendente'"); $pendentes = (int)$stmt->fetchColumn(); }
    catch (Exception $e) { $pendentes = 0; }
}

$total_setores = 0;
if ($usuario_id) {
    try { global $pdo; $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuario_setor WHERE usuario_id = ?"); $stmt->execute([$usuario_id]); $total_setores = (int)$stmt->fetchColumn(); }
    catch (Exception $e) { $total_setores = 0; }
}

$setores_usuario = [];
if ($usuario_id) {
    try {
        global $pdo;
        $stmt = $pdo->prepare("SELECT s.nome FROM setores s INNER JOIN usuario_setor us ON s.id = us.setor_id WHERE us.usuario_id = ?");
        $stmt->execute([$usuario_id]);
        $setores_usuario = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) { $setores_usuario = []; }
}

$ultimaAtualizacao = obterUltimaSincronizacao();

$filtroUF    = $_GET['uf']    ?? '';
$filtroBR    = $_GET['br']    ?? '';
$filtroBusca = $_GET['busca'] ?? '';

$filtrosAplicados = [];
if (!empty($filtroUF))    $filtrosAplicados[] = ['param' => 'uf',    'label' => 'UF',    'valor' => $filtroUF];
if (!empty($filtroBR))    $filtrosAplicados[] = ['param' => 'br',    'label' => 'BR',    'valor' => $filtroBR];
if (!empty($filtroBusca)) $filtrosAplicados[] = ['param' => 'busca', 'label' => 'Busca', 'valor' => $filtroBusca];

$normalizar = function($fid) {
    $fid = trim((string)$fid);
    if ($fid === '' || strtoupper($fid) === 'N/A') return '';
    if (preg_match('/^\d+$/', $fid)) return (string)(int)$fid;
    return strtoupper($fid);
};

global $pdo;

if (!isset($base16)) {
    $where = []; $params = [];
    if ($filtroUF !== '') { $where[] = "uf = ?"; $params[] = $filtroUF; }
    if ($filtroBR !== '') { $where[] = "br = ?"; $params[] = $filtroBR; }
    if ($filtroBusca !== '') {
        $where[] = "(o_instrum LIKE ? OR o_obj_resu LIKE ? OR o_empresa LIKE ? OR trecho LIKE ? OR id_pac LIKE ? OR fid LIKE ?)";
        $like = '%' . $filtroBusca . '%';
        array_push($params, $like, $like, $like, $like, $like, $like);
    }
    $sql = "SELECT * FROM supra_dataset_16_base_controle_geo";
    if ($where) $sql .= " WHERE " . implode(' AND ', $where);
    $sql .= " ORDER BY uf, br, o_km_inici";
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    $base16 = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if (!isset($base40)) {
    $where = []; $params = [];
    if ($filtroUF !== '') { $where[] = "estado = ?"; $params[] = $filtroUF; }
    if ($filtroBR !== '') { $where[] = "br = ?"; $params[] = $filtroBR; }
    if ($filtroBusca !== '') {
        $where[] = "(instrumento LIKE ? OR nome_empreendimento_slides LIKE ? OR nome_lote_slide LIKE ? OR empresa_construtora LIKE ? OR id_pac LIKE ? OR fid_geo LIKE ?)";
        $like = '%' . $filtroBusca . '%';
        array_push($params, $like, $like, $like, $like, $like, $like);
    }
    $sql = "SELECT * FROM supra_dataset_40_slides_atlas";
    if ($where) $sql .= " WHERE " . implode(' AND ', $where);
    $sql .= " ORDER BY estado, br";
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    $base40 = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$idx16 = []; foreach ($base16 as $r) { $k = $normalizar($r['fid'] ?? ''); if ($k !== '' && !isset($idx16[$k])) $idx16[$k] = $r; }
$idx40 = []; foreach ($base40 as $r) { $k = $normalizar($r['fid_geo'] ?? ''); if ($k !== '' && !isset($idx40[$k])) $idx40[$k] = $r; }

$total16 = count($base16);
$total40 = count($base40);
$presentes = 0;
foreach ($idx40 as $k => $_) if (isset($idx16[$k])) $presentes++;
$ausentesGEO = $total40 - $presentes;

$validacao = [];
foreach ($base40 as $r) {
    $k = $normalizar($r['fid_geo'] ?? '');
    $d16 = ($k !== '' && isset($idx16[$k])) ? $idx16[$k] : null;
    $validacao[] = ['atlas' => $r, 'geo' => $d16, 'presente' => $d16 !== null];
}

$candidatos = [];
foreach ($base16 as $r) {
    $k = $normalizar($r['fid'] ?? '');
    if ($k === '' || !isset($idx40[$k])) $candidatos[] = $r;
}

$ufsSet = []; $brsSet = [];
foreach (array_merge($base16, $base40) as $r) {
    $uf = $r['uf'] ?? $r['estado'] ?? '';
    $br = $r['br'] ?? '';
    if ($uf !== '') $ufsSet[$uf] = true;
    if ($br !== '') $brsSet[$br] = true;
}
$ufs = array_keys($ufsSet); sort($ufs);
$brs = array_keys($brsSet); sort($brs);
$totalCandidatos = count($candidatos);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Comparativo Bancos 16 × 40 — Atlas/Monitoramento — SISPRO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        body { background: #f8f9fc; padding-top: 70px; }
        .container-fluid { max-width: 98%; }
        .card { border: none; border-radius: 16px; box-shadow: 0 8px 30px rgba(0,0,0,0.08); background: #fff; padding: 20px 25px; }
        .sistema-titulo { font-weight: 700; color: #004a8f; font-size: 1.4rem; letter-spacing: 1px; }
        .table th { font-weight: 600; color: #2c3e50; white-space: nowrap; font-size: 0.85rem; }
        .table td { vertical-align: middle; font-size: 0.85rem; }
        .table-responsive { overflow-x: auto; }
        .btn-sm { padding: 0.2rem 0.5rem; font-size: 0.75rem; }

        .ultima-atualizacao {
            font-size: 0.78rem; color: #6c757d;
            background: #f1f5f9; border: 1px solid #e2e8f0;
            padding: 4px 12px; border-radius: 20px;
            display: inline-flex; align-items: center; gap: 6px; white-space: nowrap;
        }
        .ultima-atualizacao strong { color: #0d6efd; }

        .card-kpi { border: none; border-radius: 12px; padding: 16px 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.06); border-left: 5px solid #4a90e2; background: #fff; height: 100%; }
        .card-kpi.azul     { border-left-color: #0d6efd; }
        .card-kpi.roxo     { border-left-color: #6f42c1; }
        .card-kpi.verde    { border-left-color: #198754; }
        .card-kpi.vermelho { border-left-color: #dc3545; }
        .card-kpi.amarelo  { border-left-color: #ffc107; }
        .kpi-numero { font-size: 1.8rem; font-weight: 700; color: #2c3e50; line-height: 1.1; }
        .kpi-label  { font-size: 0.85rem; color: #6c757d; margin-top: 4px; display: flex; align-items: center; gap: 6px; }

        .nav-tabs .nav-link { color: #6c757d; font-weight: 500; border: none; border-bottom: 3px solid transparent; padding: 10px 18px; display: flex; align-items: center; gap: 8px; }
        .nav-tabs .nav-link:hover { color: #0d6efd; }
        .nav-tabs .nav-link.active { color: #0d6efd; background: transparent; border-bottom: 3px solid #0d6efd; font-weight: 600; }
        .nav-tabs .nav-link .badge { font-size: 0.7rem; }

        .select2-container .select2-selection--single { height: 38px; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 38px; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px; }
        .select2-container .select2-selection--single .select2__clear { display: none; }

        .badge-filtro { background: #e9ecef; color: #2c3e50; font-weight: 500; padding: 6px 12px; border-radius: 20px; margin-right: 8px; margin-bottom: 4px; display: inline-flex; align-items: center; gap: 6px; }
        .badge-filtro .remover-filtro { cursor: pointer; opacity: 0.7; }
        .badge-filtro .remover-filtro:hover { opacity: 1; }

        .status-ok    { color: #198754; font-weight: 600; }
        .status-falta { color: #dc3545; font-weight: 600; }
        .badge-tipo { background: #eef2ff; color: #3730a3; padding: 3px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 500; white-space: nowrap; }
        .badge-fid  { background: #fef3c7; color: #92400e; padding: 3px 10px; border-radius: 12px; font-size: 0.78rem; font-weight: 600; font-family: 'Courier New', monospace; }
        .tabela-compacta td, .tabela-compacta th { padding: 8px 10px; }
        .linha-divergente { background: #fff5f5 !important; }
        .linha-divergente:hover { background: #ffe6e6 !important; }

        #toastComparativo { position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%); background: rgba(0,0,0,0.85); color: #fff; padding: 12px 28px; border-radius: 30px; font-weight: 500; font-size: 0.95rem; z-index: 99999; box-shadow: 0 4px 12px rgba(0,0,0,0.3); opacity: 0; visibility: hidden; transition: opacity 0.3s, visibility 0.3s; pointer-events: none; }
        #toastComparativo.show { opacity: 1; visibility: visible; }
        #toastComparativo i { margin-right: 8px; }

        @media (max-width: 768px) { .kpi-numero { font-size: 1.4rem; } .container-fluid { padding: 0 10px; } }
    </style>
</head>
<body>
<?php include APP_PATH . '/Views/header_atlas.php'; ?>

<div class="container-fluid mt-4">
    <div class="card">
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <h2 class="sistema-titulo mb-0">
                    <i class="bi bi-columns-gap"></i> Comparativo Bancos — Slides Atlas (40) × Base GEO (16)
                </h2>
                <span class="ultima-atualizacao">
                    <i class="bi bi-clock-history"></i>
                    Última sicronização dos bancos: <strong><?= htmlspecialchars($ultimaAtualizacao) ?></strong>
                </span>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-success btn-sm" id="btnAtualizarTudo">
                    <i class="bi bi-cloud-arrow-down"></i> Atualizar Todos (16 + 40)
                </button>
                <a href="home_atlas" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left"></i> Voltar</a>
            </div>
        </div>

        <p class="text-muted mb-3 small">Validação cruzada a partir dos Slides do Atlas (match por <code>FID</code>)</p>

        <div class="row row-cols-2 row-cols-md-3 row-cols-lg-5 g-3 mb-4">
            <div class="col"><div class="card-kpi roxo"><div class="kpi-numero"><?= $total40 ?></div><div class="kpi-label"><i class="bi bi-easel"></i> Slides Atlas (40)</div></div></div>
            <div class="col"><div class="card-kpi azul"><div class="kpi-numero"><?= $total16 ?></div><div class="kpi-label"><i class="bi bi-geo-alt"></i> Base Controle GEO (16)</div></div></div>
            <div class="col"><div class="card-kpi verde"><div class="kpi-numero"><?= $presentes ?></div><div class="kpi-label"><i class="bi bi-check-circle"></i> Slides com GEO (FID)</div></div></div>
            <div class="col"><div class="card-kpi vermelho"><div class="kpi-numero"><?= $ausentesGEO ?></div><div class="kpi-label"><i class="bi bi-exclamation-triangle"></i> Slides sem GEO</div></div></div>
            <div class="col"><div class="card-kpi amarelo"><div class="kpi-numero"><?= $totalCandidatos ?></div><div class="kpi-label"><i class="bi bi-search"></i> GEO sem Slides Atlas</div></div></div>
        </div>

        <?php if (!empty($filtrosAplicados)): ?>
        <div class="mb-3 p-2 bg-light rounded-3 d-flex flex-wrap align-items-center">
            <span class="fw-bold me-2"><i class="bi bi-funnel"></i> Filtros aplicados:</span>
            <?php foreach ($filtrosAplicados as $f):
                $novosGET = $_GET; unset($novosGET[$f['param']]);
                $urlSemFiltro = '?' . http_build_query($novosGET);
            ?>
                <span class="badge-filtro">
                    <?= htmlspecialchars($f['label']) ?>: <?= htmlspecialchars($f['valor']) ?>
                    <a href="<?= $urlSemFiltro ?>" class="remover-filtro text-decoration-none" title="Remover"><i class="bi bi-x-circle"></i></a>
                </span>
            <?php endforeach; ?>
            <a href="comparativo_bancos_16x40" class="btn btn-sm btn-outline-secondary ms-2">Limpar todos</a>
        </div>
        <?php endif; ?>

        <form method="GET" class="row g-2 mb-3 align-items-end">
            <div class="col-lg-2 col-md-3 col-sm-6 col-12">
                <label class="form-label" style="font-size:0.75rem;">UF / Estado</label>
                <select name="uf" class="form-select filtro-select2">
                    <option value="">Selecione...</option>
                    <?php foreach ($ufs as $uf): ?>
                        <option value="<?= htmlspecialchars($uf) ?>" <?= $filtroUF == $uf ? 'selected' : '' ?>><?= htmlspecialchars($uf) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-2 col-md-3 col-sm-6 col-12">
                <label class="form-label" style="font-size:0.75rem;">BR</label>
                <select name="br" class="form-select filtro-select2">
                    <option value="">Selecione...</option>
                    <?php foreach ($brs as $br): ?>
                        <option value="<?= htmlspecialchars($br) ?>" <?= $filtroBR == $br ? 'selected' : '' ?>><?= htmlspecialchars($br) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-5 col-md-4 col-sm-12 col-12">
                <label class="form-label" style="font-size:0.75rem;">Buscar</label>
                <input type="text" name="busca" class="form-control" value="<?= htmlspecialchars($filtroBusca) ?>" placeholder="Instrumento, empreendimento, empresa, FID...">
            </div>
            <div class="col-lg-1 col-md-2 col-sm-6 col-12">
                <button type="submit" class="btn btn-primary w-100" style="height:38px;"><i class="bi bi-search"></i></button>
            </div>
            <div class="col-lg-2 col-md-2 col-sm-6 col-12">
                <a href="comparativo_bancos_16x40" class="btn btn-outline-secondary w-100" style="height:38px; display:flex; align-items:center; justify-content:center;"><i class="bi bi-x-circle"></i> Limpar</a>
            </div>
        </form>

        <ul class="nav nav-tabs mb-3" id="tabsComparativo" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="tab-validacao-tab" data-bs-toggle="tab" data-bs-target="#tab-validacao" type="button" role="tab" aria-selected="true">
                    <i class="bi bi-check2-circle"></i> Validação (Slides Atlas → GEO)
                    <span class="badge bg-secondary"><?= $total40 ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-candidatos-tab" data-bs-toggle="tab" data-bs-target="#tab-candidatos" type="button" role="tab" aria-selected="false">
                    <i class="bi bi-search"></i> Candidatos (GEO sem Slides)
                    <span class="badge bg-warning text-dark"><?= $totalCandidatos ?></span>
                </button>
            </li>
        </ul>

        <div class="tab-content" id="tabsComparativoContent">
            <div class="tab-pane fade show active" id="tab-validacao" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-hover align-middle tabela-compacta">
                        <thead>
                            <tr>
                                <th class="text-center">FID (Slides)</th>
                                <th>Instrumento (Slides)</th>
                                <th>Estado</th>
                                <th>BR</th>
                                <th>Nome do Empreendimento (Slides)</th>
                                <th class="text-center">Na Base GEO?</th>
                                <th class="text-center">FID (GEO)</th>
                                <th>Instrumento (GEO)</th>
                                <th>Objeto (GEO)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($validacao)): ?>
                                <tr><td colspan="9" class="text-center text-muted py-4">Nenhum registro encontrado.</td></tr>
                            <?php else: foreach ($validacao as $v):
                                $a = $v['atlas']; $g = $v['geo']; $presente = $v['presente'];
                            ?>
                                <tr class="<?= $presente ? '' : 'linha-divergente' ?>">
                                    <td class="text-center"><?php if (!empty($a['fid_geo'])): ?><span class="badge-fid"><?= htmlspecialchars($a['fid_geo']) ?></span><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
                                    <td><strong><?= htmlspecialchars($a['instrumento'] ?? '') ?></strong></td>
                                    <td><?= htmlspecialchars($a['estado'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($a['br'] ?? '') ?></td>
                                    <td><small><?= htmlspecialchars($a['nome_empreendimento_slides'] ?? '') ?></small></td>
                                    <td class="text-center"><?php if ($presente): ?><span class="status-ok"><i class="bi bi-check-circle-fill"></i> Sim</span><?php else: ?><span class="status-falta"><i class="bi bi-x-circle-fill"></i> Não</span><?php endif; ?></td>
                                    <td class="text-center"><?php if ($g && !empty($g['fid'])): ?><span class="badge-fid"><?= htmlspecialchars($g['fid']) ?></span><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
                                    <td><?php if ($g && !empty($g['o_instrum'])): ?><?= htmlspecialchars($g['o_instrum']) ?><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
                                    <td><?php if ($g && !empty($g['o_obj_resu'])): ?><small><?= htmlspecialchars($g['o_obj_resu']) ?></small><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
                <p class="text-muted small mt-2">
                    <i class="bi bi-info-circle"></i> Match feito por <strong>FID</strong>.
                    Linhas em <span style="background:#fff5f5; padding:2px 6px; border-radius:4px; color:#dc3545;">vermelho</span>
                    indicam <strong>Slides do Atlas que NÃO possuem registro na Base GEO</strong>.
                </p>
            </div>

            <div class="tab-pane fade" id="tab-candidatos" role="tabpanel">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Registros da <strong>Base Controle GEO (16)</strong> que <strong>não têm Slides do Atlas</strong> correspondente (por FID).
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle tabela-compacta">
                        <thead>
                            <tr>
                                <th class="text-center">FID (GEO)</th>
                                <th>Instrumento (GEO)</th>
                                <th>UF</th>
                                <th>BR</th>
                                <th>Objeto (GEO)</th>
                                <th>Empresa (GEO)</th>
                                <th>Trecho (GEO)</th>
                                <th class="text-center">RDCi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($candidatos)): ?>
                                <tr><td colspan="8" class="text-center text-muted py-4">Nenhum candidato encontrado.</td></tr>
                            <?php else: foreach ($candidatos as $g): ?>
                                <tr>
                                    <td class="text-center"><?php if (!empty($g['fid'])): ?><span class="badge-fid"><?= htmlspecialchars($g['fid']) ?></span><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
                                    <td><strong><?= htmlspecialchars($g['o_instrum'] ?? '') ?></strong></td>
                                    <td><?= htmlspecialchars($g['uf'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($g['br'] ?? '') ?></td>
                                    <td><small><?= htmlspecialchars($g['o_obj_resu'] ?? '') ?></small></td>
                                    <td><small><?= htmlspecialchars($g['o_empresa'] ?? '—') ?></small></td>
                                    <td><small><?= htmlspecialchars($g['trecho'] ?? '—') ?></small></td>
                                    <td class="text-center"><?php if (!empty($g['rdci'])): ?><span class="badge-tipo"><?= htmlspecialchars($g['rdci']) ?></span><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="toastComparativo"><i class="bi bi-check-circle-fill text-success"></i> <span id="toastComparativoMsg"></span></div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    $('.filtro-select2').select2({ placeholder: 'Selecione...', allowClear: false, width: '100%' });
    $('.filtro-select2').on('change', function() { $(this).closest('form').submit(); });
    $('.filtro-select2').on('select2:open', function() {
        setTimeout(function() { var f = document.querySelector('.select2-search__field'); if (f) f.focus(); }, 100);
    });
    $('.filtro-select2').on('focus', function() { $(this).select2('open'); });

    var toastTimer = null;
    function mostrarToast(msg, tipo) {
        var t = document.getElementById('toastComparativo');
        var icone = tipo === 'erro' ? 'bi-x-circle-fill text-danger' : 'bi-check-circle-fill text-success';
        t.innerHTML = '<i class="bi ' + icone + '"></i> <span>' + msg + '</span>';
        t.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function() { t.classList.remove('show'); }, 4000);
    }

    function atualizar(url, cb) {
        $.ajax({ url: url, method: 'GET', timeout: 300000,
            success: function() { cb(true); },
            error: function(xhr) { console.error('Falha:', url, xhr.status); cb(false); }
        });
    }

    $('#btnAtualizarTudo').on('click', function() {
        if (!confirm('Atualizar TUDO (DataSet 16 + DataSet 40)? Isso pode demorar.')) return;
        var b = this, o = b.innerHTML; b.disabled = true;
        var etapas = [
            { url: 'importar_supra_dataset_16_base_controle_geo.php', nome: 'DataSet 16' },
            { url: 'importar_supra_dataset_40_slides_atlas.php',      nome: 'DataSet 40' }
        ];
        function proxima(i) {
            if (i >= etapas.length) {
                b.disabled = false; b.innerHTML = o;
                mostrarToast('Todos os DataSets foram atualizados!');
                setTimeout(function() { location.reload(); }, 1500);
                return;
            }
            b.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Atualizando ' + etapas[i].nome + '...';
            atualizar(etapas[i].url, function(ok) {
                if (ok) proxima(i + 1);
                else { b.disabled = false; b.innerHTML = o; mostrarToast('Erro ao atualizar ' + etapas[i].nome + '.', 'erro'); }
            });
        }
        proxima(0);
    });
});
</script>
</body>
</html>