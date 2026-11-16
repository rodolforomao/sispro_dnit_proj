<?php
// ============================================================
// SCRIPT DE EXPORTAÇÃO DE MEDIÇÕES NÃO LIQUIDADAS - V3
// Exporta CSV com as medições pendentes de liquidação (FIFO).
// Requer sessão ativa (login) e usa a conexão de config.php
// (que já garante charset=utf8).
// ============================================================

require_once 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

// Função auxiliar para nome do mês
function nomeMes($m) {
    $meses = array(
        1=>'janeiro', 2=>'fevereiro', 3=>'marco',    4=>'abril',
        5=>'maio',    6=>'junho',     7=>'julho',    8=>'agosto',
        9=>'setembro',10=>'outubro',  11=>'novembro',12=>'dezembro'
    );
    return isset($meses[$m]) ? $meses[$m] : '';
}

// Buscar todos os contratos
$sqlContratos = "SELECT * FROM `supra_dataset_47_financeiro_orcamentario_atlas`";
$stmt = $pdo->query($sqlContratos);
$contratos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$linhasExportacao = array();
$totalGeralMedido = 0;

foreach ($contratos as $c) {
    $contrato = $c['contrato'];

    // Total liquidado do contrato (usa coluna global OU soma mensal, o que for maior)
    $totalLiquidadoGlobal  = (float)($c['total_liquidado'] ?? 0);
    $totalLiquidadoColunas = 0;

    for ($ano = 2024; $ano <= (date('Y') + 1); $ano++) {
        for ($mes = 1; $mes <= 12; $mes++) {
            $col = 'liquidado_' . nomeMes($mes) . '_' . $ano;
            if (array_key_exists($col, $c) && $c[$col] !== null) {
                $totalLiquidadoColunas += (float)$c[$col];
            }
        }
    }

    $totalLiquidado = max($totalLiquidadoGlobal, $totalLiquidadoColunas);

    // Buscar as medições (mais recentes primeiro)
    $sqlMedicoes = "SELECT mes_referencia,
                           SUM(COALESCE(total_liquido,0)) as valor,
                           MAX(data_termino) as data_real
                    FROM `siac_medicoes`
                    WHERE contrato = :c AND mes_referencia IS NOT NULL
                    GROUP BY mes_referencia
                    ORDER BY mes_referencia DESC";
    $stmtMed = $pdo->prepare($sqlMedicoes);
    $stmtMed->execute(array(':c' => $contrato));
    $medicoes = $stmtMed->fetchAll(PDO::FETCH_ASSOC);

    if (empty($medicoes)) continue;

    $totalMedido = 0;
    foreach ($medicoes as $m) {
        $totalMedido += (float)$m['valor'];
    }

    // Lógica FIFO
    $saldoNaoLiquidado = $totalMedido - $totalLiquidado;
    if ($saldoNaoLiquidado <= 0) continue;

    $medicoesPendentes = array();
    $acumulado = 0;

    // Data da última medição
    $ultimaMedicao    = $medicoes[0];
    $ultimaDataReal   = !empty($ultimaMedicao['data_real'])
        ? $ultimaMedicao['data_real']
        : date('Y-m-t', strtotime($ultimaMedicao['mes_referencia']));
    $ultimaMedicaoData = date('d/m/Y', strtotime($ultimaDataReal));

    foreach ($medicoes as $m) {
        $valorMedicao = (float)$m['valor'];
        if ($valorMedicao <= 0) continue;

        $acumulado += $valorMedicao;
        $medicoesPendentes[] = $m;

        // Arredonda para 2 casas para evitar erro de float
        if (round($acumulado, 2) >= round($saldoNaoLiquidado, 2)) {
            break;
        }
    }

    $medicoesPendentes = array_reverse($medicoesPendentes);

    // Montar linhas da exportação
    foreach ($medicoesPendentes as $mp) {
        $valor = (float)$mp['valor'];
        $totalGeralMedido += $valor;

        $dataMedicaoReal = !empty($mp['data_real'])
            ? date('d/m/Y', strtotime($mp['data_real']))
            : date('d/m/Y', strtotime(date('Y-m-t', strtotime($mp['mes_referencia']))));

        $linhasExportacao[] = array(
            'acao'       => "'" . $c['acao'],
            'contrato'   => "'" . $contrato,
            'uf'         => $c['estado'],
            'br'         => $c['empreendimento'],
            'data_med'   => $dataMedicaoReal,
            'valor_med'  => number_format($valor, 2, ',', '.'),
            'ultima_med' => $ultimaMedicaoData
        );
    }
}

// Gerar o CSV
$filename = "medicoes_nao_liquidadas_" . date('Ymd_His') . ".csv";

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// BOM UTF-8 (para Excel abrir acentos corretamente)
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

fputcsv($out, array('AÇÃO', 'CONTRATO', 'UF', 'BR', 'DATA MEDIÇÃO', 'VALOR MEDIÇÃO', 'DATA ÚLTIMA MEDIÇÃO'), ';');

foreach ($linhasExportacao as $linha) {
    fputcsv($out, array(
        $linha['acao'],
        $linha['contrato'],
        $linha['uf'],
        $linha['br'],
        $linha['data_med'],
        $linha['valor_med'],
        $linha['ultima_med']
    ), ';');
}

fputcsv($out, array(
    'TOTAL', '', '', '', '', number_format($totalGeralMedido, 2, ',', '.'), ''
), ';');

fclose($out);
exit;