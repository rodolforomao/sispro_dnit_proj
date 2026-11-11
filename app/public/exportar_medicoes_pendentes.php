<?php
// ============================================================
// SCRIPT DE EXPORTAÇÃO DE MEDIÇÕES NÃO LIQUIDADAS - V3
// ============================================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 1. CONFIGURAÇÕES DO BANCO DE DADOS
$host = 'localhost';
$dbname = 'controle_processos'; // <--- COLOQUE O NOME DO SEU BANCO AQUI
$user = 'root';
$pass = '';

// 2. CONEXÃO PDO
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}

// Função auxiliar para nome do mês
function nomeMes($m) {
    $meses = [1=>'janeiro',2=>'fevereiro',3=>'marco',4=>'abril',5=>'maio',6=>'junho',
              7=>'julho',8=>'agosto',9=>'setembro',10=>'outubro',11=>'novembro',12=>'dezembro'];
    return $meses[$m] ?? '';
}

// 3. BUSCAR TODOS OS CONTRATOS
$sqlContratos = "SELECT * FROM `supra_dataset_47_financeiro_orcamentario_atlas`";
$stmt = $pdo->query($sqlContratos);
$contratos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$linhasExportacao = [];
$totalGeralMedido = 0;

foreach ($contratos as $c) {
    $contrato = $c['contrato'];
    
    // 3.1. Calcular o Total Liquidado do contrato
    $totalLiquidadoGlobal = (float)($c['total_liquidado'] ?? 0);
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

    // 4. BUSCAR AS MEDIÇÕES (Mais recentes primeiro)
    // Removemos o DATE_FORMAT daqui para podermos trabalhar a data real no PHP
    $sqlMedicoes = "SELECT mes_referencia, 
                           SUM(COALESCE(total_liquido,0)) as valor,
                           MAX(data_termino) as data_real
                    FROM `siac_medicoes` 
                    WHERE contrato = :c AND mes_referencia IS NOT NULL
                    GROUP BY mes_referencia
                    ORDER BY mes_referencia DESC";
    $stmtMed = $pdo->prepare($sqlMedicoes);
    $stmtMed->execute([':c' => $contrato]);
    $medicoes = $stmtMed->fetchAll(PDO::FETCH_ASSOC);

    if (empty($medicoes)) continue;

    $totalMedido = 0;
    foreach ($medicoes as $m) {
        $totalMedido += (float)$m['valor'];
    }

    // 5. LÓGICA FIFO (Waterfall)
    $saldoNaoLiquidado = $totalMedido - $totalLiquidado;
    
    if ($saldoNaoLiquidado <= 0) {
        continue;
    }

    $medicoesPendentes = [];
    $acumulado = 0;
    
    // Formata a data da última medição
    $ultimaMedicao = $medicoes[0];
    $ultimaDataReal = !empty($ultimaMedicao['data_real']) 
        ? $ultimaMedicao['data_real'] 
        : date('Y-m-t', strtotime($ultimaMedicao['mes_referencia'])); // Se vier vazio, pega o último dia do mês
    $ultimaMedicaoData = date('d/m/Y', strtotime($ultimaDataReal));

    foreach ($medicoes as $m) {
        $valorMedicao = (float)$m['valor'];
        if ($valorMedicao <= 0) continue;

        $acumulado += $valorMedicao;
        $medicoesPendentes[] = $m;

        // Arredonda para 2 casas decimais para evitar erro de precisão do float
        if (round($acumulado, 2) >= round($saldoNaoLiquidado, 2)) {
            break;
        }
    }

    $medicoesPendentes = array_reverse($medicoesPendentes);

    // 6. MONTAR AS LINHAS DO EXCEL
    foreach ($medicoesPendentes as $mp) {
        $valor = (float)$mp['valor'];
        $totalGeralMedido += $valor;

        // Data real da medição formatada
        $dataMedicaoReal = !empty($mp['data_real']) 
            ? date('d/m/Y', strtotime($mp['data_real'])) 
            : date('d/m/Y', strtotime(date('Y-m-t', strtotime($mp['mes_referencia']))));

        $linhasExportacao[] = [
            'acao'       => "'" . $c['acao'],
            'contrato'   => "'" . $contrato,
            'uf'         => $c['estado'],
            'br'         => $c['empreendimento'],
            'data_med'   => $dataMedicaoReal,
            'valor_med'  => number_format($valor, 2, ',', '.'),
            'ultima_med' => $ultimaMedicaoData
        ];
    }
}

// 7. GERAR O ARQUIVO CSV
$filename = "medicoes_nao_liquidadas_" . date('Ymd_His') . ".csv";

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

echo "\xEF\xBB\xBF"; // BOM para Excel

$out = fopen('php://output', 'w');

fputcsv($out, ['AÇÃO', 'CONTRATO', 'UF', 'BR', 'DATA MEDIÇÃO', 'VALOR MEDIÇÃO', 'DATA ÚLTIMA MEDIÇÃO'], ';');

foreach ($linhasExportacao as $linha) {
    fputcsv($out, [
        $linha['acao'],
        $linha['contrato'],
        $linha['uf'],
        $linha['br'],
        $linha['data_med'],
        $linha['valor_med'],
        $linha['ultima_med']
    ], ';');
}

fputcsv($out, [
    'TOTAL', '', '', '', '', number_format($totalGeralMedido, 2, ',', '.'), ''
], ';');

fclose($out);
exit;