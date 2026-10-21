<?php
// ============================================================
// diag_import.php — Mostra o erro exato do import
// ============================================================
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== DIAGNÓSTICO DE IMPORT ===\n\n";

// ------------------------------------------------------------
// 1. Testa a API
// ------------------------------------------------------------
$url   = "https://supra.dnit.gov.br/index_cgcont_common.php/cgcont/cib/exportBi/1";
$token = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyIjoic3VwcmFfY2liX2FwaUBkbml0Lmdvdi5iciIsInBhc3MiOiJTdXByYUBDaWIxMjM0NUAifQ.GyejoRFofL3mMnN7jD64RBrr2JSB9hVpR_BWrfAtd3U";

echo "1) Chamando API do DataSet 1...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['token: ' . $token, 'Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 120);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   HTTP Code: {$code}\n";
echo "   Tamanho:   " . strlen($resp) . " bytes\n";

$dados = json_decode($resp, true);
if (!is_array($dados)) {
    die("   ❌ JSON inválido: " . json_last_error_msg() . "\n");
}
echo "   ✅ JSON válido: " . count($dados) . " registros\n\n";

// ------------------------------------------------------------
// 2. Testa a conexão e a tabela
// ------------------------------------------------------------
echo "2) Verificando tabela supra_dataset_1_todos...\n";
try {
    $total = $pdo->query("SELECT COUNT(*) FROM supra_dataset_1_todos")->fetchColumn();
    echo "   ✅ Tabela existe. Registros atuais: {$total}\n";

    // Lista as colunas REAIS da tabela
    $cols = $pdo->query("SHOW COLUMNS FROM supra_dataset_1_todos")->fetchAll(PDO::FETCH_COLUMN);
    echo "   Colunas na tabela: " . implode(', ', $cols) . "\n";
    echo "   Total de colunas: " . count($cols) . "\n\n";
} catch (Exception $e) {
    die("   ❌ Erro: " . $e->getMessage() . "\n");
}

// ------------------------------------------------------------
// 3. Testa um INSERT simples
// ------------------------------------------------------------
echo "3) Testando INSERT de 1 registro (instrumento e uf apenas)...\n";
try {
    $pdo->beginTransaction();
    $pdo->exec("DELETE FROM supra_dataset_1_todos WHERE instrumento = 'TESTE_DIAG'");
    $stmt = $pdo->prepare("INSERT INTO supra_dataset_1_todos (instrumento, uf) VALUES (?, ?)");
    $stmt->execute(['TESTE_DIAG', 'XX']);
    $pdo->commit();
    echo "   ✅ INSERT simples funcionou\n";
    $pdo->exec("DELETE FROM supra_dataset_1_todos WHERE instrumento = 'TESTE_DIAG'");
    echo "   ✅ DELETE de teste funcionou\n\n";
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "   ❌ INSERT falhou: " . $e->getMessage() . "\n\n";
}

// ------------------------------------------------------------
// 4. Testa um INSERT com TODAS as colunas (igual ao import real)
// ------------------------------------------------------------
echo "4) Testando INSERT com TODAS as colunas do import...\n";
$colunas = [
    'instrumento','uf','br','lote','numero_processo','contratado',
    'lider_consorcio','empresa_projetista','situacao_contrato','situacao_obra',
    'tipo_intervencao','modalidade_licitacao','objeto_contratacao',
    'km_inicial','km_final','extensao','data_inicio_vigencia',
    'data_ordem_inicio_projeto','data_termino_projeto','data_inicio_obra',
    'data_termino_obra','data_termino_vigencia','valor_pi','valor_aditivo',
    'percentual_aditivo','pi_vigente','valor_reajuste','valor_pi_a_r',
    'valor_pi_medido','valor_pi_r_a_medido','percentual_pi_medido',
    'as_built_trd','observacao_prazo','acompanhamentos','contrato_supervisao',
    'objeto_supervisao','nome_referencia',
];

$placeholders = implode(',', array_fill(0, count($colunas), '?'));
$sqlInsert = "INSERT INTO supra_dataset_1_todos (" . implode(',', $colunas) . ") VALUES ($placeholders)";

try {
    $stmt = $pdo->prepare($sqlInsert);
    echo "   ✅ prepare() do INSERT completo funcionou\n";

    $pdo->beginTransaction();
    $valores = array_fill(0, count($colunas), 'X'); // valores falsos
    $valores[0] = 'TESTE_DIAG_FULL'; // instrumento
    $stmt->execute($valores);
    $pdo->commit();
    echo "   ✅ INSERT completo funcionou\n";
    $pdo->exec("DELETE FROM supra_dataset_1_todos WHERE instrumento = 'TESTE_DIAG_FULL'");
    echo "   ✅ Limpeza ok\n";
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "   ❌ ERRO AQUI: " . $e->getMessage() . "\n";
    echo "   SQL usado:\n   " . $sqlInsert . "\n";
}

echo "\n=== FIM ===\n";