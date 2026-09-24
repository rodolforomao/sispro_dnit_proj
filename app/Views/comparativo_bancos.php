<?php
// View comparativo_bancos.php
// Comparativo entre 3 DataSets: 36 × 38 × 1 (Atlas/Monitoramento)

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

$usuario_nome  = $usuario_nome  ?? $_SESSION['usuario_nome']  ?? 'Usuário';
$usuario_nivel = $usuario_nivel ?? $_SESSION['usuario_nivel'] ?? 'usuario';
$usuario_id    = $usuario_id    ?? $_SESSION['usuario_id']    ?? 0;
$setor_slug    = $_SESSION['setor_slug'] ?? 'atlas-monitoramento';
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

// Filtros aplicados (badges)
$filtrosAplicados = [];
if (!empty($filtroUF))    $filtrosAplicados[] = ['param' => 'uf',    'label' => 'UF',    'valor' => $filtroUF];
if (!empty($filtroBR))    $filtrosAplicados[] = ['param' => 'br',    'label' => 'BR',    'valor' => $filtroBR];
if (!empty($filtroBusca)) $filtrosAplicados[] = ['param' => 'busca', 'label' => 'Busca', 'valor' => $filtroBusca];
if (!empty($apenasDivergentes)) $filtrosAplicados[] = ['param' => 'divergentes', 'label' => 'Filtro', 'valor' => 'Apenas divergências'];
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Comparativo entre Bancos - SISPRO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fc; padding-top: 70px; }
        .container-fluid { max-width: 98%; }
        .card { border: none; border-radius: 16px; box-shadow: 0 8px 30px rgba(0,0,0,0.08); background: #fff; padding: 20px 25px; }
        .sistema-titulo { font-weight: 700; color: #004a8f; font-size: 1.4rem; letter-spacing: 1px; }
        .table th { font-weight: 600; color: #2c3e50; white-space: nowrap; font-size: 0.85rem; }
        .table td { vertical-align: middle; font-size: 0.85rem; }
        .table-responsive { overflow-x: auto; }
        .btn-sm { padding: 0.2rem 0.5rem; font-size: 0.75rem; }

        /* KPI cards */
        .card-kpi {
            border: none; border-radius: 12px; padding: 16px 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
            border-left: 5px solid #4a90e2;
            background: #fff;
            height: 100%;
        }
        .card-kpi.azul     { border-left-color: #0d6efd; }
        .card-kpi.roxo     { border-left-color: #6f42c1; }
        .card-kpi.verde    { border-left-color: #198754; }
        .card-kpi.laranja  { border-left-color: #fd7e14; }
        .card-kpi.vermelho { border-left-color: #dc3545; }
        .card-kpi.amarelo  { border-left-color: #ffc107; }
        .kpi-numero { font-size: 1.8rem; font-weight: 700; color: #2c3e50; line-height: 1.1; }
        .kpi-label  { font-size: 0.85rem; color: #6c757d; margin-top: 4px; display: flex; align-items: center; gap: 6px; }
        .kpi-label i { font-size: 1rem; }

        /* Badges filtros */
        .badge-filtro {
            background: #e9ecef; color: #2c3e50; font-weight: 500;
            padding: 6px 12px; border-radius: 20px; margin-right: 8px; margin-bottom: 4px;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .badge-filtro .remover-filtro { cursor: pointer; opacity: 0.7; }
        .badge-filtro .remover-filtro:hover { opacity: 1; }

        /* Ícones de presença */
        .icone-sim { color: #198754; font-size: 1.2rem; }
        .icone-nao { color: #dc3545; font-size: 1.2rem; opacity: 0.45; }

        /* Badge de combinação */
        .badge-combo {
            padding: 3px 10px; border-radius: 12px;
            font-size: 0.72rem; font-weight: 600; white-space: nowrap;
        }
        .combo-3   { background: #d1e7dd; color: #0a3622; }
        .combo-2   { background: #fff3cd; color: #664d03; }
        .combo-1   { background: #f8d7da; color: #58151c; }

        .tabela-compacta td, .tabela-compacta th { padding: 8px 10px; }

        .linha-divergente { background: #fff5f5 !important; }
        .linha-divergente:hover { background: #ffe6e6 !important; }

        /* Topbar */
        .topbar {
            position: fixed; top: 0; left: 0; right: 0; z-index: 1030;
            background: #ffffff; border-bottom: 1px solid #dce1e8;
            padding: 8px 20px; display: flex; align-items: center;
            justify-content: space-between; flex-wrap: wrap;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); min-height: 60px;
        }
        .topbar-left { display: flex; align-items: center; gap: 10px; }
        .topbar-left .sistema-titulo { font-weight: 700; color: #004a8f; font-size: 1.2rem; letter-spacing: 1px; }
        .topbar-center { flex: 1; text-align: center; }
        .topbar-center .badge-setor { background: #17a2b8; color: #fff; font-size: 0.9rem; padding: 6px 14px; border-radius: 50px; }
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

        /* Toast */
        #toastComparativo {
            position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%);
            background: rgba(0,0,0,0.85); color: #fff; padding: 12px 28px;
            border-radius: 30px; font-weight: 500; font-size: 0.95rem;
            z-index: 99999; box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            opacity: 0; visibility: hidden; transition: opacity 0.3s, visibility 0.3s;
            pointer-events: none;
        }
        #toastComparativo.show { opacity: 1; visibility: visible; }
        #toastComparativo i { margin-right: 8px; }

        @media (max-width: 768px) {
            .kpi-numero { font-size: 1.4rem; }
            .container-fluid { padding: 0 10px; }
        }
    </style>
</head>
<?php include APP_PATH . '/public/chat_widget.php'; ?>
<body>
<?php include APP_PATH . '/Views/header_atlas.php'; ?>

<div class="container-fluid mt-4">
    <div class="card">
        <!-- Cabeçalho -->
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <div>
                <h2 class="sistema-titulo">
                    <i class="bi bi-columns-gap"></i> Comparativo entre Bancos
                </h2>
                <p class="text-muted mb-0 small">
                    DataSet 36 (Informações Gerais de Obras) × DataSet 38 (Lista Todos Instrumentos) × DataSet 1 (Carteira de Contratos COCCONV)
                </p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-outline-primary btn-sm" id="btnAtualizar36">
                    <i class="bi bi-arrow-repeat"></i> Atualizar 36
                </button>
                <button type="button" class="btn btn-outline-info btn-sm" id="btnAtualizar38">
                    <i class="bi bi-arrow-repeat"></i> Atualizar 38
                </button>
                <button type="button" class="btn btn-outline-warning btn-sm" id="btnAtualizar1">
                    <i class="bi bi-arrow-repeat"></i> Atualizar 1
                </button>
                <button type="button" class="btn btn-success btn-sm" id="btnAtualizarTudo">
                    <i class="bi bi-cloud-arrow-down"></i> Atualizar Todos 36 - 38 - 1
                </button>
                <a href="index" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left"></i> Voltar</a>
            </div>
        </div>

        <!-- KPIs -->
        <div class="row row-cols-2 row-cols-md-3 row-cols-lg-6 g-3 mb-4">
            <div class="col">
                <div class="card-kpi azul">
                    <div class="kpi-numero"><?= $total36 ?></div>
                    <div class="kpi-label"><i class="bi bi-database"></i> DataSet 36</div>
                </div>
            </div>
            <div class="col">
                <div class="card-kpi roxo">
                    <div class="kpi-numero"><?= $total38 ?></div>
                    <div class="kpi-label"><i class="bi bi-list-ul"></i> DataSet 38</div>
                </div>
            </div>
            <div class="col">
                <div class="card-kpi laranja">
                    <div class="kpi-numero"><?= $total1 ?></div>
                    <div class="kpi-label"><i class="bi bi-briefcase"></i> DataSet 1</div>
                </div>
            </div>
            <div class="col">
                <div class="card-kpi verde">
                    <div class="kpi-numero"><?= $emTodos ?></div>
                    <div class="kpi-label"><i class="bi bi-check-circle"></i> Em todos os 3</div>
                </div>
            </div>
            <div class="col">
                <div class="card-kpi amarelo">
                    <div class="kpi-numero"><?= $emDois ?></div>
                    <div class="kpi-label"><i class="bi bi-dash-circle"></i> Em 2 de 3</div>
                </div>
            </div>
            <div class="col">
                <div class="card-kpi vermelho">
                    <div class="kpi-numero"><?= $emApenas1 ?></div>
                    <div class="kpi-label"><i class="bi bi-exclamation-triangle"></i> Em apenas 1</div>
                </div>
            </div>
        </div>

        <!-- Filtros aplicados -->
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
            <a href="comparativo_bancos" class="btn btn-sm btn-outline-secondary ms-2">Limpar todos</a>
        </div>
        <?php endif; ?>

        <!-- Filtros -->
        <form method="GET" class="row g-2 mb-3 align-items-end">
            <div class="col-lg-2 col-md-3 col-sm-6 col-12">
                <label class="form-label" style="font-size:0.75rem;">UF</label>
                <select name="uf" class="form-select form-select-sm" onchange="this.form.submit();">
                    <option value="">Todas</option>
                    <?php foreach ($ufs as $uf): ?>
                        <option value="<?= htmlspecialchars($uf) ?>" <?= $filtroUF == $uf ? 'selected' : '' ?>><?= htmlspecialchars($uf) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-2 col-md-3 col-sm-6 col-12">
                <label class="form-label" style="font-size:0.75rem;">BR</label>
                <select name="br" class="form-select form-select-sm" onchange="this.form.submit();">
                    <option value="">Todas</option>
                    <?php foreach ($brs as $br): ?>
                        <option value="<?= htmlspecialchars($br) ?>" <?= $filtroBR == $br ? 'selected' : '' ?>><?= htmlspecialchars($br) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-4 col-md-4 col-sm-12 col-12">
                <label class="form-label" style="font-size:0.75rem;">Buscar</label>
                <input type="text" name="busca" class="form-control form-control-sm" value="<?= htmlspecialchars($filtroBusca) ?>" placeholder="Contrato, nome usual, UF, BR...">
            </div>
            <div class="col-lg-2 col-md-2 col-sm-6 col-12">
                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" name="divergentes" value="1" id="chkDivergentes"
                           <?= $apenasDivergentes ? 'checked' : '' ?> onchange="this.form.submit();">
                    <label class="form-check-label small" for="chkDivergentes">
                        Apenas divergências
                    </label>
                </div>
            </div>
            <div class="col-lg-1 col-md-2 col-sm-6 col-12">
                <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search"></i></button>
            </div>
            <div class="col-lg-1 col-md-2 col-sm-6 col-12">
                <a href="comparativo_bancos" class="btn btn-outline-secondary btn-sm w-100"><i class="bi bi-x-circle"></i></a>
            </div>
        </form>

        <div class="info-count mb-2 small text-muted">
            <i class="bi bi-list-ul"></i>
            <strong><?= (int)$totalRegistrosFiltrados ?></strong> registros exibidos
            <?php if ($apenasDivergentes): ?>
                <span class="badge bg-warning text-dark ms-1">Apenas divergências</span>
            <?php endif; ?>
        </div>

        <!-- Tabela -->
        <div class="table-responsive">
            <table class="table table-hover align-middle tabela-compacta">
                <thead>
                    <tr>
                        <th>Contrato</th>
                        <th>UF</th>
                        <th>BR</th>
                        <th>Nome Usual</th>
                        <th class="text-center" title="Informações Gerais de Obras">36</th>
                        <th class="text-center" title="Lista Todos Instrumentos">38</th>
                        <th class="text-center" title="Carteira de Contratos COCCONV">1</th>
                        <th class="text-center">Presente em</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($registros)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">Nenhum registro encontrado.</td></tr>
                    <?php else: ?>
                        <?php foreach ($registros as $r):
                            $qtd = ($r['em_36']?1:0) + ($r['em_38']?1:0) + ($r['em_1']?1:0);
                            $classeLinha = ($qtd < 3) ? 'linha-divergente' : '';

                            $combos = [];
                            if ($r['em_36']) $combos[] = '36';
                            if ($r['em_38']) $combos[] = '38';
                            if ($r['em_1'])  $combos[] = '1';
                            $comboTexto = implode(' + ', $combos);

                            $comboClasse = $qtd === 3 ? 'combo-3' : ($qtd === 2 ? 'combo-2' : 'combo-1');
                        ?>
                        <tr class="<?= $classeLinha ?>">
                            <td><strong><?= htmlspecialchars($r['instrumento']) ?></strong></td>
                            <td><?= htmlspecialchars($r['uf'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($r['br'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($r['nome_usual'] ?? '—') ?></td>
                            <td class="text-center">
                                <?php if ($r['em_36']): ?>
                                    <i class="bi bi-check-circle-fill icone-sim" title="Presente no DataSet 36"></i>
                                <?php else: ?>
                                    <i class="bi bi-x-circle-fill icone-nao" title="Ausente no DataSet 36"></i>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($r['em_38']): ?>
                                    <i class="bi bi-check-circle-fill icone-sim" title="Presente no DataSet 38"></i>
                                <?php else: ?>
                                    <i class="bi bi-x-circle-fill icone-nao" title="Ausente no DataSet 38"></i>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($r['em_1']): ?>
                                    <i class="bi bi-check-circle-fill icone-sim" title="Presente no DataSet 1"></i>
                                <?php else: ?>
                                    <i class="bi bi-x-circle-fill icone-nao" title="Ausente no DataSet 1"></i>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="badge-combo <?= $comboClasse ?>"><?= htmlspecialchars($comboTexto) ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <p class="text-muted small mt-3">
            <i class="bi bi-info-circle"></i>
            Linhas em <span style="background:#fff5f5; padding:2px 6px; border-radius:4px; color:#dc3545;">vermelho</span>
            indicam contratos que <strong>não estão presentes nos 3 bancos</strong>.
            O "Nome Usual", "UF" e "BR" são priorizados pelo DataSet 36 → 38 → 1.
        </p>

        <div class="text-center text-muted small mt-3">
            Comparativo gerado em <?= date('d/m/Y H:i') ?>
        </div>
    </div>
</div>

<!-- Toast -->
<div id="toastComparativo"><i class="bi bi-check-circle-fill text-success"></i> <span id="toastComparativoMsg"></span></div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function() {
    var toastTimer = null;
    function mostrarToast(msg, tipo) {
        var t = document.getElementById('toastComparativo');
        var icone = tipo === 'erro' ? 'bi-x-circle-fill text-danger' : 'bi-check-circle-fill text-success';
        t.innerHTML = '<i class="bi ' + icone + '"></i> <span>' + msg + '</span>';
        t.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function() { t.classList.remove('show'); }, 4000);
    }

    // ---- Atualizar DataSet 36 ----
    $('#btnAtualizar36').on('click', function() {
        if (!confirm('Atualizar o DataSet 36 (Informações Gerais de Obras)?')) return;
        var btn = this;
        btn.disabled = true;
        var original = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>...';
        $.ajax({
            url: 'importar_supra_dataset_36.php',
            method: 'GET',
            timeout: 300000,
            success: function() {
                btn.disabled = false; btn.innerHTML = original;
                mostrarToast('DataSet 36 atualizado!');
                setTimeout(function() { location.reload(); }, 1200);
            },
            error: function() {
                btn.disabled = false; btn.innerHTML = original;
                mostrarToast('Erro ao atualizar DataSet 36.', 'erro');
            }
        });
    });

    // ---- Atualizar DataSet 38 ----
    $('#btnAtualizar38').on('click', function() {
        if (!confirm('Atualizar o DataSet 38 (Lista Todos Instrumentos)?')) return;
        var btn = this;
        btn.disabled = true;
        var original = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>...';
        $.ajax({
            url: 'importar_supra_dataset_38.php',
            method: 'GET',
            timeout: 300000,
            success: function() {
                btn.disabled = false; btn.innerHTML = original;
                mostrarToast('DataSet 38 atualizado!');
                setTimeout(function() { location.reload(); }, 1200);
            },
            error: function() {
                btn.disabled = false; btn.innerHTML = original;
                mostrarToast('Erro ao atualizar DataSet 38.', 'erro');
            }
        });
    });

    // ---- Atualizar DataSet 1 ----
    $('#btnAtualizar1').on('click', function() {
        if (!confirm('Atualizar o DataSet 1 (Carteira de Contratos COCCONV)?')) return;
        var btn = this;
        btn.disabled = true;
        var original = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>...';
        $.ajax({
            url: 'importar_supra_dataset_1.php',
            method: 'GET',
            timeout: 300000,
            success: function() {
                btn.disabled = false; btn.innerHTML = original;
                mostrarToast('DataSet 1 atualizado!');
                setTimeout(function() { location.reload(); }, 1200);
            },
            error: function() {
                btn.disabled = false; btn.innerHTML = original;
                mostrarToast('Erro ao atualizar DataSet 1.', 'erro');
            }
        });
    });

    // ---- Atualizar Tudo ----
    $('#btnAtualizarTudo').on('click', function() {
        if (!confirm('Atualizar TUDO (36 + 38 + 1)? Isso pode demorar.')) return;

        var btn = this;
        btn.disabled = true;
        var original = btn.innerHTML;

        var etapas = [
            { url: 'importar_supra_dataset_36_infoobras.php', nome: '36' },
            { url: 'importar_supra_dataset_38.php', nome: '38' },
            { url: 'importar_supra_dataset_1.php',  nome: '1'  }
        ];

        function proximaEtapa(i) {
            if (i >= etapas.length) {
                btn.disabled = false;
                btn.innerHTML = original;
                mostrarToast('Todos os DataSets foram atualizados!');
                setTimeout(function() { location.reload(); }, 1500);
                return;
            }
            var etapa = etapas[i];
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> ' + etapa.nome + '...';

            $.ajax({
                url: etapa.url,
                method: 'GET',
                timeout: 300000,
                success: function() { proximaEtapa(i + 1); },
                error: function() {
                    btn.disabled = false;
                    btn.innerHTML = original;
                    mostrarToast('Erro ao atualizar DataSet ' + etapa.nome + '.', 'erro');
                }
            });
        }
        proximaEtapa(0);
    });
});
</script>
</body>
</html>