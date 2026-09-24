<?php
require_once 'config.php';

// ============================================================
// IMPORT DATASET 40 → supra_dataset_40_resumos
// ============================================================
$url = "https://supra.dnit.gov.br/index_cgcont_common.php/cgcont/cib/exportBi/40";
$token = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyIjoic3VwcmFfY2liX2FwaUBkbml0Lmdvdi5iciIsInBhc3MiOiJTdXByYUBDaWIxMjM0NUAifQ.GyejoRFofL3mMnN7jD64RBrr2JSB9hVpR_BWrfAtd3U";

// ============================================================
// FUNÇÃO DE CONSUMO
// ============================================================
function consumirApi($url, $token) {
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['token: ' . $token, 'Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $resposta = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($resposta !== false && $httpCode == 200) return $resposta;
        throw new Exception("cURL falhou (HTTP $httpCode)");
    }

    if (ini_get('allow_url_fopen')) {
        $options = [
            'http' => [
                'method' => 'GET',
                'header' => "token: $token\r\n" . "Content-Type: application/json\r\n",
                'timeout' => 60,
                'ignore_errors' => true
            ],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]
        ];
        $context = stream_context_create($options);
        $resposta = @file_get_contents($url, false, $context);
        if ($resposta !== false) return $resposta;
    }
    throw new Exception("Nenhum método disponível.");
}

// ============================================================
// ✅ MAPEAMENTO CORRIGIDO — chaves em Capitalize conforme retorno da API
// ============================================================
$mapa = [
    'Instrumento'                                    => 'instrumento',
    'Situação GEO (Mapa Gerencial)'                  => 'situacao_geo',
    'Texto Mapa Capa do Atlas'                       => 'texto_mapa_capa',
    'Resumo geral do Lote (slide da foto)'           => 'resumo_geral_lote',
    'Situação empreendimento ATLAS (slide da foto)'  => 'situacao_empreendimento_atlas',
];

// ============================================================
// FUNÇÃO AUXILIAR PARA TRATAR ARRAYS
// ============================================================
function tratarArray($valor) {
    if (is_array($valor)) {
        return implode(' | ', array_filter($valor, function($v) { return !empty($v); }));
    }
    return $valor;
}

// ============================================================
// EXECUTAR
// ============================================================
try {
    echo "🔄 Conectando à API (Dataset 40 - Resumos)...\n";
    $resposta = consumirApi($url, $token);
    $dados = json_decode($resposta, true);
    if (!is_array($dados) || empty($dados)) {
        throw new Exception("Resposta vazia ou inválida.");
    }
    echo "✅ Dados obtidos: " . count($dados) . " registros.\n";
} catch (Exception $e) {
    die("❌ Erro: " . $e->getMessage());
}

// ============================================================
// CONSOLIDAR POR INSTRUMENTO
// ============================================================
$porInstrumento = [];
foreach ($dados as $item) {
    $instrumento = trim($item['Instrumento'] ?? '');
    if (empty($instrumento)) continue;
    if (isset($porInstrumento[$instrumento])) continue;
    $porInstrumento[$instrumento] = $item;
}
echo "📦 Registros únicos por instrumento: " . count($porInstrumento) . "\n";

// ============================================================
// INSERIR
// ============================================================
$campos = array_values($mapa);
$campos = array_unique($campos);

$sqlInsert = "INSERT INTO supra_dataset_40_resumos (" . implode(',', $campos) . ") 
              VALUES (" . implode(',', array_fill(0, count($campos), '?')) . ")";
$stmtInsert = $pdo->prepare($sqlInsert);

$pdo->beginTransaction();
try {
    $pdo->exec("DELETE FROM supra_dataset_40_resumos");

    $inseridos = 0;
    foreach ($porInstrumento as $instrumento => $item) {
        $valores = [];
        foreach ($campos as $dbField) {
            $apiKey = array_search($dbField, $mapa);
            $valor = $item[$apiKey] ?? null;
            $valor = tratarArray($valor);
            if ($valor !== null) $valor = trim($valor);
            $valores[] = $valor;
        }
        $stmtInsert->execute($valores);
        $inseridos++;
    }

    $pdo->commit();
    echo "\n✅ Importação concluída!\n";
    echo "Inseridos: $inseridos\n";
} catch (Exception $e) {
    $pdo->rollBack();
    die("❌ Erro: " . $e->getMessage());
}

echo "\n✅ Sincronização de Resumos finalizada!\n";