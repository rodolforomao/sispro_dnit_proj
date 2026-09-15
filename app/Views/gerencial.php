<?php
// View gerencial.php - Painel Gerencial com barra fixa idêntica à home
// ============================================================
// BLOCO DE DEFINIÇÃO DE VARIÁVEIS (IGUAL À HOME)
// ============================================================
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
        if (isset($pdo)) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE status = 'pendente'");
            $pendentes = (int)$stmt->fetchColumn();
        }
    } catch (Exception $e) {
        $pendentes = 0;
    }
}

$total_setores = 0;
if ($usuario_id) {
    try {
        global $pdo;
        if (isset($pdo)) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuario_setor WHERE usuario_id = ?");
            $stmt->execute([$usuario_id]);
            $total_setores = (int)$stmt->fetchColumn();
        }
    } catch (Exception $e) {
        $total_setores = 0;
    }
}

$setores_usuario = [];
if ($usuario_id) {
    try {
        global $pdo;
        if (isset($pdo)) {
            $stmt = $pdo->prepare("
                SELECT s.nome 
                FROM setores s 
                INNER JOIN usuario_setor us ON s.id = us.setor_id 
                WHERE us.usuario_id = ?
            ");
            $stmt->execute([$usuario_id]);
            $setores_usuario = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
    } catch (Exception $e) {
        $setores_usuario = [];
    }
}

// ============================================================
// DADOS PARA O PAINEL (VALORES PADRÃO PARA EVITAR ERROS)
// ============================================================
$recebidosSemana = $recebidosSemana ?? 0;
$emAberto = $emAberto ?? 0;
$concluidosSemana = $concluidosSemana ?? 0;
$vencerHojeAmanha = $vencerHojeAmanha ?? 0;

$pizzaLabels = $pizzaLabels ?? ['Sem dados'];
$pizzaData = $pizzaData ?? [0];
$barLabels = $barLabels ?? ['Sem dados'];
$barData = $barData ?? [0];
$suggestedMax = $suggestedMax ?? 5;

$prazoProjetos = $prazoProjetos ?? '-';
$prazoAssessoriaCOAC = $prazoAssessoriaCOAC ?? '-';
$prazoAssessoriaCGCONT = $prazoAssessoriaCGCONT ?? '-';
$tempoAssinatura = $tempoAssinatura ?? '-';
$tempoConclusao = $tempoConclusao ?? '-';
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Painel Gerencial - SISPRO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <!-- Carregar Chart.js com fallback -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
    <style>
        /* ======= ESTILOS GERAIS (MANTIDOS) ======= */
        body { background: #f8f9fc; padding-top: 70px; }
        .container-fluid { max-width: 98%; padding: 0 15px; }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.06); background: #fff; padding: 16px 20px; margin-bottom: 16px; }
        .card-kpi { border-left: 4px solid #4a90e2; padding: 12px 20px; margin-bottom: 0; }
        .kpi-numero { font-size: 1.8rem; font-weight: 700; color: #2c3e50; line-height: 1.2; }
        .kpi-label { font-size: 0.8rem; color: #6c757d; }
        .card-kpi.verde { border-left-color: #28a745; }
        .card-kpi.azul { border-left-color: #0d6efd; }
        .card-kpi.amarelo { border-left-color: #ffc107; }
        .card-kpi.roxo { border-left-color: #6f42c1; }
        .chart-container { height: 180px; }
        .logo-dnit { max-height: 40px; }
        .sistema-titulo { font-weight: 700; color: #004a8f; font-size: 1.2rem; letter-spacing: 0.5px; }
        .prazo-item { padding: 4px 0; border-bottom: 1px solid #f0f0f0; font-size: 0.9rem; }
        .prazo-item:last-child { border-bottom: none; }
        .prazo-valor { font-weight: 700; color: #2c3e50; }
        .graf-titulo { font-size: 0.95rem; font-weight: 600; color: #2c3e50; margin-bottom: 8px; }
        @media (max-width: 768px) {
            .container-fluid { padding: 10px; }
            .card { padding: 12px; }
            .kpi-numero { font-size: 1.4rem; }
            .chart-container { height: 150px; }
        }

        /* ============================================================
           BARRA SUPERIOR FIXA (IGUAL À HOME)
           ============================================================ */
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
    </style>
</head>
<?php include APP_PATH . '/public/chat_widget.php'; ?>
<body>
<?php include APP_PATH . '/Views/header.php'; ?>

<!-- ============================================================
     CONTEÚDO PRINCIPAL (com valores de fallback)
     ============================================================ -->
<div class="container-fluid mt-3">
    <!-- Cabeçalho do conteúdo -->
    <div class="card">
        <div class="d-flex align-items-center justify-content-between flex-wrap">
            <div class="d-flex align-items-center">
                <img src="https://www.gov.br/dnit/pt-br/central-de-conteudos/publicacoes/manual-de-gestao-da-marca/marcas-dnit/assinaturas-e-marcas/monocromatica-dnit-extenso.png" alt="DNIT" class="logo-dnit me-3">
                <span class="sistema-titulo"><i class="bi bi-diagram-3"></i> SISPRO - Painel Gerencial</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="small"><i class="bi bi-person-circle"></i> Olá, <strong><?= htmlspecialchars($usuario_nome) ?></strong></span>
                <a href="index.php" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left"></i> Voltar</a>
            </div>
        </div>
    </div>

    <!-- Cards KPI -->
    <div class="row g-2 mb-3">
        <div class="col-md-3 col-6">
            <div class="card card-kpi azul">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="kpi-numero"><?= $recebidosSemana ?></div>
                        <div class="kpi-label"><i class="bi bi-calendar-check"></i> Recebidos essa semana</div>
                    </div>
                    <i class="bi bi-calendar-check" style="font-size:1.8rem; color:#0d6efd; opacity:0.4;"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card card-kpi amarelo">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="kpi-numero"><?= $emAberto ?></div>
                        <div class="kpi-label"><i class="bi bi-folder2-open"></i> Em aberto</div>
                    </div>
                    <i class="bi bi-folder2-open" style="font-size:1.8rem; color:#ffc107; opacity:0.4;"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card card-kpi verde">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="kpi-numero"><?= $concluidosSemana ?></div>
                        <div class="kpi-label"><i class="bi bi-check-circle"></i> Concluídos essa semana</div>
                    </div>
                    <i class="bi bi-check-circle" style="font-size:1.8rem; color:#28a745; opacity:0.4;"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card card-kpi roxo">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="kpi-numero"><?= $vencerHojeAmanha ?></div>
                        <div class="kpi-label"><i class="bi bi-clock"></i> A vencer hoje/amanhã</div>
                    </div>
                    <i class="bi bi-clock" style="font-size:1.8rem; color:#6f42c1; opacity:0.4;"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráficos -->
    <div class="row g-2 mb-3">
        <div class="col-md-6">
            <div class="card">
                <div class="graf-titulo"><i class="bi bi-pie-chart"></i> Processos em aberto por equipe</div>
                <div class="chart-container">
                    <canvas id="pizzaChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="graf-titulo"><i class="bi bi-bar-chart"></i> Processos em aberto por responsável</div>
                <div class="chart-container">
                    <canvas id="barChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Prazo médio e Tempos -->
    <div class="row g-2">
        <div class="col-md-6">
            <div class="card">
                <div class="graf-titulo"><i class="bi bi-clock-history"></i> Prazo médio por equipe (dias)</div>
                <div class="prazo-item"><strong>Projetos:</strong> <span class="prazo-valor"><?= $prazoProjetos ?></span></div>
                <div class="prazo-item"><strong>Assessoria COAC:</strong> <span class="prazo-valor"><?= $prazoAssessoriaCOAC ?></span></div>
                <div class="prazo-item"><strong>Assessoria CGCONT:</strong> <span class="prazo-valor"><?= $prazoAssessoriaCGCONT ?></span></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="graf-titulo"><i class="bi bi-hourglass-split"></i> Tempos médios (dias)</div>
                <div class="prazo-item"><strong>Tempo médio de assinatura:</strong> <span class="prazo-valor"><?= $tempoAssinatura ?></span></div>
                <div class="prazo-item"><strong>Tempo médio de conclusão:</strong> <span class="prazo-valor"><?= $tempoConclusao ?></span></div>
            </div>
        </div>
    </div>

    <div class="text-muted small text-center mt-3">
        Painel Gerencial - SISPRO - <?= date('d/m/Y H:i') ?>
    </div>
</div>

<script>
// Registrar o plugin de datalabels
Chart.register(ChartDataLabels);

// Gráfico de Pizza
const ctxPizza = document.getElementById('pizzaChart').getContext('2d');
new Chart(ctxPizza, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($pizzaLabels) ?>,
        datasets: [{
            data: <?= json_encode($pizzaData) ?>,
            backgroundColor: ['#0d6efd', '#198754', '#ffc107', '#dc3545', '#6f42c1', '#fd7e14', '#20c997'],
            borderWidth: 1,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'right', labels: { boxWidth: 10, padding: 8, font: { size: 10 } } },
            datalabels: {
                color: '#fff',
                font: { weight: 'bold', size: 12 },
                formatter: (value) => value,
                anchor: 'center',
                align: 'center'
            }
        }
    },
    plugins: [ChartDataLabels]
});

// Gráfico de Barras
const ctxBar = document.getElementById('barChart').getContext('2d');
new Chart(ctxBar, {
    type: 'bar',
    data: {
        labels: <?= json_encode($barLabels) ?>,
        datasets: [{
            label: 'Processos em aberto',
            data: <?= json_encode($barData) ?>,
            backgroundColor: '#0d6efd',
            borderColor: '#0a58ca',
            borderWidth: 1,
            borderRadius: 4,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            datalabels: {
                color: '#2c3e50',
                font: { weight: 'bold', size: 11 },
                anchor: 'end',
                align: 'end',
                offset: 2
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                suggestedMax: <?= $suggestedMax ?>,
                ticks: { display: false },
                grid: { display: false }
            },
            x: { ticks: { font: { size: 9 } } }
        }
    },
    plugins: [ChartDataLabels]
});
</script>
</body>
</html>