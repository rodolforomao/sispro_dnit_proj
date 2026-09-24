<?php
// ============================================================
// View avanco_fisico.php
// Avanço físico por família de serviço (Atlas/Monitoramento)
// ============================================================

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

// Badge de filtros ativos
$filtrosAplicados = [];
if (!empty($dados['contrato'])) {
    $filtrosAplicados[] = ['param' => 'contrato', 'label' => 'Contrato', 'valor' => $dados['contrato']];
}

// Prepara dados para o JS
$jsonLabels     = json_encode($dados['labels'], JSON_UNESCAPED_UNICODE);
$jsonMedido     = json_encode($dados['medido'], JSON_UNESCAPED_UNICODE);
$jsonExecutado  = json_encode($dados['executado'], JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Avanço Físico por Serviço - SISPRO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        :root {
            --dp-azul:        #004a8f;
            --dp-azul-escuro: #0b1a4a;
            --dp-dourado-bg:  #fdf9ed;
            --dp-bege-borda:  #e6d9a8;
            --dp-offset-top:  70px;
        }
        body { background: #f8f9fc; padding-top: var(--dp-offset-top); }

        .card {
            border: none; border-radius: 16px;
            box-shadow: 0 8px 30px rgba(0,0,0,.08);
            background: #fff; padding: 20px 25px;
        }
        .card-rdci {
            background:#fff; border:2px solid var(--dp-bege-borda);
            border-radius:16px; padding:20px 22px;
            box-shadow:0 6px 18px rgba(11,26,74,.06); margin-bottom:18px;
        }
        .sistema-titulo {
            font-weight: 700; color: #004a8f;
            font-size: 1.4rem; letter-spacing: 1px;
        }

        /* Filtros sticky */
        .dp-filtros-sticky {
            position:sticky; top:var(--dp-offset-top); z-index:1020;
            background:#fff; border:2px solid var(--dp-bege-borda);
            border-radius:14px; padding:12px 16px 10px;
            box-shadow:0 6px 14px rgba(11,26,74,.08); margin-bottom:18px;
        }
        .dp-filtros-sticky .form-label {
            font-size:.68rem; text-transform:uppercase; color:#7a859b; margin-bottom:2px;
        }
        .dp-filtros-sticky .form-select,
        .dp-filtros-sticky .form-control { font-size:.85rem; }
        .dp-filtros-sticky .select2-container .select2-selection--single { height: 34px; }
        .dp-filtros-sticky .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 32px; font-size:.85rem; }
        .dp-filtros-sticky .select2-container--default .select2-selection--single .select2-selection__arrow { height: 32px; }

        .dp-filtros-badges {
            display:flex; flex-wrap:wrap; gap:6px; margin-top:10px;
            border-top:1px dashed #e3e8f0; padding-top:10px;
        }
        .dp-filtro-tag {
            background:#e9ecef; color:#2c3e50; font-weight:500;
            font-size:.72rem; padding:5px 10px; border-radius:20px;
            display:inline-flex; align-items:center; gap:6px;
        }
        .dp-filtro-tag .remover-filtro { cursor:pointer; opacity:.7; text-decoration:none; color:inherit; }
        .dp-filtro-tag .remover-filtro:hover { opacity:1; }

        /* Gráfico */
        .chart-wrapper {
            position: relative;
            width: 100%;
            height: 620px;
        }
        .chart-titulo {
            color: var(--dp-azul-escuro);
            font-weight: 700;
            font-size: 1.15rem;
            margin: 0 0 4px;
        }
        .chart-subtitulo {
            color: #6c757d;
            font-size: .85rem;
            margin: 0 0 20px;
            font-style: italic;
        }

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

        /* Aviso */
        .aviso-dados-fixos {
            background: #fff8e6;
            border-left: 4px solid #ffc107;
            border-radius: 8px;
            padding: 10px 16px;
            font-size: .82rem;
            color: #664d03;
            margin-bottom: 16px;
        }
        .aviso-dados-fixos i { margin-right: 6px; }
    </style>
</head>
<?php include APP_PATH . '/public/chat_widget.php'; ?>
<body>
<?php include APP_PATH . '/Views/header_atlas.php'; ?>

<div class="container-fluid mt-4">

    <!-- ============================================================
         FILTROS — padrão sticky (mesmo estilo dos outros)
         ============================================================ -->
    <div class="dp-filtros-sticky">
        <form method="GET" action="avanco_fisico" id="formFiltros">
            <div class="row g-2 align-items-end">
                <div class="col-lg-4 col-md-6 col-sm-8">
                    <label class="form-label">Contrato</label>
                    <select name="contrato" class="form-select filtro-select2">
                        <option value="">Selecione...</option>
                        <?php foreach ($contratosList as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>" <?= $dados['contrato'] === $c ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-6 col-sm-4 d-flex align-items-end">
                    <a href="avanco_fisico" class="btn btn-outline-secondary btn-sm w-100">
                        <i class="bi bi-eraser"></i> Limpar
                    </a>
                </div>
            </div>

            <?php if (!empty($filtrosAplicados)): ?>
            <div class="dp-filtros-badges">
                <span class="fw-bold small text-muted me-1"><i class="bi bi-funnel"></i> Filtros aplicados:</span>
                <?php foreach ($filtrosAplicados as $f): ?>
                    <?php
                        $novosGET = $_GET;
                        unset($novosGET[$f['param']]);
                        $urlSemFiltro = '?' . http_build_query($novosGET);
                    ?>
                    <span class="dp-filtro-tag">
                        <?= htmlspecialchars($f['label']) ?>: <?= htmlspecialchars($f['valor']) ?>
                        <a href="<?= $urlSemFiltro ?>" class="remover-filtro" title="Remover filtro">
                            <i class="bi bi-x-circle"></i>
                        </a>
                    </span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- ============================================================
         GRÁFICO
         ============================================================ -->
    <div class="card-rdci">
        <div class="d-flex align-items-center justify-content-between flex-wrap mb-3">
            <div>
                <h2 class="chart-titulo">
                    <i class="bi bi-bar-chart-line"></i> Avanço físico por família de serviço
                </h2>
                <p class="chart-subtitulo mb-0">Medição contratual × execução física informada</p>
            </div>
            <div class="text-end">
                <div class="text-muted small">Contrato</div>
                <div class="fw-bold" style="color: var(--dp-azul-escuro); font-size: 1.05rem;">
                    <?= htmlspecialchars($dados['contrato']) ?>
                </div>
                <div class="text-muted small mt-1">
                    Extensão: <?= number_format($dados['km_total_contrato'], 1, ',', '.') ?> km
                </div>
            </div>
        </div>

        <?php if (!empty($dados['labels'])): ?>
            <div class="chart-wrapper">
                <canvas id="graficoAvanco"></canvas>
            </div>
        <?php else: ?>
            <div class="alert alert-info">Nenhum dado disponível para o contrato selecionado.</div>
        <?php endif; ?>

        <div class="aviso-dados-fixos mt-3">
            <i class="bi bi-info-circle-fill"></i>
            <strong>Série "Medido":</strong> valores ainda <em>placeholder</em> — a medição contratual real
            será conectada numa próxima etapa.
            <strong>Série "Realmente executado":</strong> calculada como
            <code>km_executado / km_total × 100</code>.
        </div>
    </div>
</div>

<!-- Chart.js + plugins -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@3.0.1/dist/chartjs-plugin-annotation.min.js"></script>

<script>
// Registra os plugins globalmente
Chart.register(ChartDataLabels);
// chartjs-plugin-annotation se registra automaticamente via UMD

$(document).ready(function () {
    // Auto-submit do filtro
    $('.filtro-select2').select2({ placeholder: 'Selecione...', allowClear: false, width: '100%' });
    $('.filtro-select2').on('change', function () {
        $('#formFiltros').submit();
    });
    $('.filtro-select2').on('select2:open', function () {
        setTimeout(function () {
            var f = document.querySelector('.select2-search__field');
            if (f) f.focus();
        }, 100);
    });

    // ============================================================
    // GRÁFICO DE BARRAS HORIZONTAIS AGRUPADAS
    // ============================================================
    const labels    = <?= $jsonLabels ?>;
    const medido    = <?= $jsonMedido ?>;
    const executado = <?= $jsonExecutado ?>;

    const ctx = document.getElementById('graficoAvanco');
    if (!ctx) return;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Medido',
                    data: medido,
                    backgroundColor: '#1f77b4',
                    borderColor: '#1f77b4',
                    borderWidth: 1,
                    borderRadius: 3,
                    barPercentage: 0.9,
                    categoryPercentage: 0.7
                },
                {
                    label: 'Realmente executado',
                    data: executado,
                    backgroundColor: '#ff7f0e',
                    borderColor: '#ff7f0e',
                    borderWidth: 1,
                    borderRadius: 3,
                    barPercentage: 0.9,
                    categoryPercentage: 0.7
                }
            ]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            layout: { padding: { right: 60, left: 10, top: 10, bottom: 10 } },
            scales: {
                x: {
                    min: 0,
                    max: 120,
                    title: {
                        display: true,
                        text: '% do avanço',
                        color: '#495057',
                        font: { size: 12, weight: '600' }
                    },
                    ticks: {
                        callback: function (v) { return v + '%'; },
                        color: '#6c757d',
                        font: { size: 11 }
                    },
                    grid: {
                        color: 'rgba(0,0,0,0.06)'
                    }
                },
                y: {
                    reverse: true,   // ← Drenagem/OAC no topo (ordem do array)
                    ticks: {
                        color: '#2c3e50',
                        font: { size: 12, weight: '500' }
                    },
                    grid: {
                        display: false
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        font: { size: 12, weight: '500' },
                        boxWidth: 14,
                        boxHeight: 14,
                        padding: 16,
                        usePointStyle: true,
                        pointStyle: 'rectRounded'
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function (ctx) {
                            return ' ' + ctx.dataset.label + ': ' + ctx.parsed.x.toFixed(1) + '%';
                        }
                    },
                    backgroundColor: 'rgba(11,26,74,0.92)',
                    titleFont: { size: 12, weight: '600' },
                    bodyFont: { size: 12 },
                    padding: 10,
                    cornerRadius: 6
                },
                datalabels: {
                    anchor: 'end',
                    align: 'end',
                    offset: 4,
                    color: '#2c3e50',
                    font: { size: 10, weight: '700' },
                    formatter: function (v) {
                        if (v === null || v === undefined) return '';
                        return v.toFixed(1) + '%';
                    },
                    clamp: true,
                    clip: false
                },
                annotation: {
                    annotations: {
                        linha100: {
                            type: 'line',
                            xMin: 100,
                            xMax: 100,
                            borderColor: 'rgba(220, 53, 69, 0.85)',
                            borderWidth: 2,
                            borderDash: [6, 4],
                            label: {
                                display: true,
                                content: '100%',
                                position: 'start',
                                backgroundColor: 'rgba(220, 53, 69, 0.85)',
                                color: '#fff',
                                font: { size: 10, weight: '700' },
                                padding: { top: 2, bottom: 2, left: 6, right: 6 },
                                borderRadius: 4
                            }
                        }
                    }
                }
            }
        }
    });
});
</script>
</body>
</html>