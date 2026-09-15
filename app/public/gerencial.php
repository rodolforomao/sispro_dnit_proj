<?php
require_once 'config.php';

// Proteger - apenas admin_premium e admin
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['usuario_nivel'], ['admin_premium', 'admin'])) {
    header('Location: index.php');
    exit;
}

$usuario_nome = $_SESSION['usuario_nome'] ?? 'Usuário';

// --- Funções auxiliares ---
function inicioSemana() {
    $hoje = new DateTime();
    $diaSemana = (int)$hoje->format('N');
    $diferenca = $diaSemana - 1;
    $inicio = clone $hoje;
    $inicio->modify("- $diferenca days");
    return $inicio->format('Y-m-d');
}

function fimSemana() {
    $inicio = new DateTime(inicioSemana());
    $fim = clone $inicio;
    $fim->modify('+4 days');
    return $fim->format('Y-m-d');
}

$inicioSemanaStr = inicioSemana();
$fimSemanaStr = fimSemana();

// --- 1. Recebidos essa semana (via SQL) ---
$stmt = $pdo->prepare("SELECT COUNT(*) FROM processos WHERE data_entrada BETWEEN ? AND ?");
$stmt->execute([$inicioSemanaStr, $fimSemanaStr]);
$recebidosSemana = (int)$stmt->fetchColumn();

// --- 2. Em aberto (status != Assinado e != Concluído) ---
$stmt = $pdo->query("SELECT COUNT(*) FROM processos p 
                     LEFT JOIN status_processo s ON p.status_id = s.id 
                     WHERE s.nome NOT IN ('Assinado', 'Concluído') OR s.nome IS NULL");
$emAberto = (int)$stmt->fetchColumn();

// --- 3. Concluídos essa semana ---
$stmt = $pdo->prepare("SELECT COUNT(*) FROM processos p 
                       LEFT JOIN status_processo s ON p.status_id = s.id 
                       WHERE s.nome = 'Assinado' AND p.data_assinatura BETWEEN ? AND ?");
$stmt->execute([$inicioSemanaStr, $fimSemanaStr]);
$concluidosSemana = (int)$stmt->fetchColumn();

// --- 4. A vencer hoje/amanhã ---
$hoje = (new DateTime())->format('Y-m-d');
$amanha = (new DateTime('+1 day'))->format('Y-m-d');
$stmt = $pdo->prepare("SELECT COUNT(*) FROM processos p 
                       LEFT JOIN status_processo s ON p.status_id = s.id 
                       WHERE s.nome NOT IN ('Assinado', 'Concluído') 
                       AND (p.prazo = ? OR p.prazo = ?)");
$stmt->execute([$hoje, $amanha]);
$vencerHojeAmanha = (int)$stmt->fetchColumn();

// --- Buscar todos os processos para os demais cálculos ---
$sql = "SELECT p.*, 
               e.nome AS equipe_nome,
               r.nome AS responsavel_nome,
               s.nome AS status_nome
        FROM processos p
        LEFT JOIN equipes e ON p.equipe_id = e.id
        LEFT JOIN responsaveis r ON p.responsavel_id = r.id
        LEFT JOIN status_processo s ON p.status_id = s.id";
$processos = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// --- 5. Dados para gráfico de pizza ---
$equipeCount = [];
foreach ($processos as $p) {
    if ($p['status_nome'] == 'Assinado' || $p['status_nome'] == 'Concluído') continue;
    $equipe = $p['equipe_nome'] ?? 'Sem equipe';
    if (!isset($equipeCount[$equipe])) $equipeCount[$equipe] = 0;
    $equipeCount[$equipe]++;
}
arsort($equipeCount);
$pizzaLabels = array_keys($equipeCount);
$pizzaData = array_values($equipeCount);

// --- 6. Dados para gráfico de barras ---
$responsavelCount = [];
foreach ($processos as $p) {
    if ($p['status_nome'] == 'Assinado' || $p['status_nome'] == 'Concluído') continue;
    $resp = $p['responsavel_nome'] ?? 'Sem responsável';
    if (!isset($responsavelCount[$resp])) $responsavelCount[$resp] = 0;
    $responsavelCount[$resp]++;
}
arsort($responsavelCount);
$barLabels = array_keys($responsavelCount);
$barData = array_values($responsavelCount);

// --- 7. Prazo médio por equipe ---
function calcularPrazoMedio($processos, $equipeNome) {
    $totais = 0;
    $count = 0;
    foreach ($processos as $p) {
        if ($p['status_nome'] != 'Assinado') continue;
        if ($p['equipe_nome'] != $equipeNome) continue;
        if (empty($p['data_entrada']) || empty($p['data_revisao'])) continue;
        $entrada = new DateTime($p['data_entrada']);
        $revisao = new DateTime($p['data_revisao']);
        $diff = $entrada->diff($revisao)->days;
        $totais += $diff;
        $count++;
    }
    if ($count == 0) return '-';
    return ceil($totais / $count) . ' dias';
}

$prazoProjetos = calcularPrazoMedio($processos, 'PROJETO');
$prazoAssessoriaCOAC = calcularPrazoMedio($processos, 'ASSESSORIA COAC');
$prazoAssessoriaCGCONT = calcularPrazoMedio($processos, 'ASSESSORIA CGCONT');

// --- 8. Tempo médio de assinatura ---
function calcularTempoAssinatura($processos) {
    $totais = 0;
    $count = 0;
    foreach ($processos as $p) {
        if ($p['status_nome'] != 'Assinado') continue;
        if (empty($p['data_revisao']) || empty($p['data_assinatura'])) continue;
        $revisao = new DateTime($p['data_revisao']);
        $assinatura = new DateTime($p['data_assinatura']);
        $diff = $revisao->diff($assinatura)->days;
        $totais += $diff;
        $count++;
    }
    if ($count == 0) return '-';
    return ceil($totais / $count) . ' dias';
}
$tempoAssinatura = calcularTempoAssinatura($processos);

// --- 9. Tempo médio de conclusão ---
function calcularTempoConclusao($processos) {
    $totais = 0;
    $count = 0;
    foreach ($processos as $p) {
        if ($p['status_nome'] != 'Assinado') continue;
        if (empty($p['data_entrada']) || empty($p['data_assinatura'])) continue;
        $entrada = new DateTime($p['data_entrada']);
        $assinatura = new DateTime($p['data_assinatura']);
        $diff = $entrada->diff($assinatura)->days;
        $totais += $diff;
        $count++;
    }
    if ($count == 0) return '-';
    return ceil($totais / $count) . ' dias';
}
$tempoConclusao = calcularTempoConclusao($processos);

// Calcular o máximo para o eixo Y do gráfico de barras
$maxBar = max($barData);
$suggestedMax = $maxBar > 0 ? ceil($maxBar * 1.25) : 5; // 25% a mais de espaço
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Painel Gerencial - SISPROC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
    <style>
        body { background: #f8f9fc; }
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
    </style>
</head>
<body>
<div class="container-fluid mt-3">
    <!-- Cabeçalho -->
    <div class="card">
        <div class="d-flex align-items-center justify-content-between flex-wrap">
            <div class="d-flex align-items-center">
                <img src="https://www.gov.br/dnit/pt-br/central-de-conteudos/publicacoes/manual-de-gestao-da-marca/marcas-dnit/assinaturas-e-marcas/monocromatica-dnit-extenso.png" alt="DNIT" class="logo-dnit me-3">
                <span class="sistema-titulo"><i class="bi bi-diagram-3"></i> SISPROC - Painel Gerencial</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="small"><i class="bi bi-person-circle"></i> Olá, <strong><?= htmlspecialchars($usuario_nome ?? '') ?></strong></span>
                <a href="index.php" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left"></i> Voltar</a>
                <a href="logout.php" class="btn btn-outline-danger btn-sm"><i class="bi bi-box-arrow-right"></i> Sair</a>
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

    <!-- Rodapé -->
    <div class="text-muted small text-center mt-3">
        Painel Gerencial - SISPROC - <?= date('d/m/Y H:i') ?>
    </div>
</div>

<script>
// Registrar o plugin de datalabels
Chart.register(ChartDataLabels);

// Gráfico de Pizza (rosca) com valores absolutos
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
            legend: {
                position: 'right',
                labels: {
                    boxWidth: 10,
                    padding: 8,
                    font: { size: 10 }
                }
            },
            datalabels: {
                color: '#fff',
                font: { weight: 'bold', size: 12 },
                formatter: (value) => value, // exibe o valor absoluto
                anchor: 'center',
                align: 'center'
            }
        }
    },
    plugins: [ChartDataLabels]
});

// Gráfico de Barras com valores em cima e sem eixo Y
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
                suggestedMax: <?= $suggestedMax ?>, // espaço extra acima da maior barra
                ticks: { display: false },
                grid: { display: false }
            },
            x: {
                ticks: { font: { size: 9 } }
            }
        }
    },
    plugins: [ChartDataLabels]
});
</script>
</body>
</html>