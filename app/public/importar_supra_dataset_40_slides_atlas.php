<?php
require_once 'config.php';

// ============================================================
// IMPORT DATASET 40 → supra_dataset_40_slides_atlas
// ============================================================
$url   = "https://supra.dnit.gov.br/index_cgcont_common.php/cgcont/cib/exportBi/40";
$token = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyIjoic3VwcmFfY2liX2FwaUBkbml0Lmdvdi5iciIsInBhc3MiOiJTdXByYUBDaWIxMjM0NUAifQ.GyejoRFofL3mMnN7jD64RBrr2JSB9hVpR_BWrfAtd3U";

function consumirApi($url, $token) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['token: ' . $token, 'Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $resposta = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($resposta !== false && $httpCode == 200) return $resposta;
    throw new Exception("cURL falhou (HTTP $httpCode)");
}

function tratarArray($valor) {
    if (is_array($valor)) {
        return implode(' | ', array_filter($valor, function($v) { return $v !== '' && $v !== null; }));
    }
    return $valor;
}

function converterData($valor) {
    if (empty($valor)) return null;
    $valor = trim((string)$valor);
    if ($valor === '' || strtoupper($valor) === 'N/A') return null;
    if (preg_match('/^\d{4}-\d{2}-\d{2}/', $valor)) return substr($valor, 0, 10);
    if (preg_match('#^\d{2}/\d{2}/\d{4}#', $valor)) {
        $p = explode('/', $valor);
        return $p[2] . '-' . $p[1] . '-' . $p[0];
    }
    if (preg_match('/^\d{4,5}$/', $valor)) {
        $serial = (int)$valor;
        return gmdate('Y-m-d', ($serial - 25569) * 86400);
    }
    return null;
}

function converterValor($valor) {
    if ($valor === null) return null;
    $valor = trim((string)$valor);
    if ($valor === '') return null;
    if (!is_numeric($valor)) return null;
    return (float)$valor;
}

$mapa = [
    'Instrumento'                                                => 'instrumento',
    'Estado'                                                     => 'estado',
    'BR'                                                         => 'br',
    'Lote'                                                       => 'lote',
    'Ação'                                                       => 'acao',
    'Incluir na capa do ATLAS (mapa do estado)?'                 => 'incluir_capa_atlas',
    'Criar slides do ATLAS? (exceto capa do estado)'             => 'criar_slides_atlas',
    'Incluir no Slide da foto?'                                  => 'incluir_slide_foto',
    'Incluir no Slide do unifilar?'                              => 'incluir_slide_unifilar',
    'Incluir no Slide das análises?'                             => 'incluir_slide_analises',
    'Incluir gráfico no Slide?'                                  => 'incluir_grafico_slide',
    'Situação GEO (Mapa Gerencial)'                              => 'situacao_geo',
    'incluir no mapa gerencial? (GEO)'                           => 'incluir_mapa_gerencial',
    'FID (GEO)'                                                  => 'fid_geo',
    'Valor Total (GEO)'                                          => 'valor_total_geo',
    'Valor pago (GEO)'                                           => 'valor_pago_geo',
    '% executada (GEO)'                                          => 'perc_executada_geo',
    'Nome do empreendimento nos slides'                          => 'nome_empreendimento_slides',
    'Texto Mapa Capa do Atlas'                                   => 'texto_mapa_capa',
    'Cor da Legenda Mapa Capa do Atlas'                          => 'cor_legenda_mapa',
    'Status empreendimento (slide da foto)'                      => 'status_empreendimento',
    'Extensão empreendimento (slide da foto)'                    => 'extensao_empreendimento',
    'Situação empreendimento ATLAS (slide da foto)'              => 'situacao_empreendimento_atlas',
    'Importância (slide da foto)'                                => 'importancia_slide',
    'Nome do lote (slide da foto)'                               => 'nome_lote_slide',
    'Resumo geral do Lote (slide da foto)'                       => 'resumo_geral_lote',
    'kmi'                                                        => 'kmi',
    'kmf'                                                        => 'kmf',
    'Segmento (kmi e kmf)'                                       => 'segmento',
    'Extensão SIAC'                                              => 'extensao_siac',
    'Empresa construtora'                                        => 'empresa_construtora',
    'Saldo empreendimento'                                       => 'saldo_empreendimento',
    'Valor previsto para conclusão do ano'                       => 'valor_previsto_conclusao',
    'ID PAC'                                                     => 'id_pac',
    'Valor sugerido para conclusão do ano'                       => 'valor_sugerido_conclusao',
];

$camposData    = []; // nenhum campo de data nesse dataset
$camposDecimal = ['kmi', 'kmf', 'valor_total_geo', 'valor_pago_geo', 'valor_previsto_conclusao', 'valor_sugerido_conclusao'];

try {
    echo "🔄 Conectando à API (Dataset 40 - Slides Atlas)...\n";
    $resposta = consumirApi($url, $token);
    $dados = json_decode($resposta, true);
    if (!is_array($dados) || empty($dados)) {
        throw new Exception("Resposta vazia ou inválida.");
    }
    echo "✅ Dados obtidos: " . count($dados) . " registros.\n";
} catch (Exception $e) {
    die("❌ Erro: " . $e->getMessage());
}

$campos = array_values($mapa);
$sqlInsert = "INSERT INTO supra_dataset_40_slides_atlas (" . implode(',', $campos) . ")
              VALUES (" . implode(',', array_fill(0, count($campos), '?')) . ")";
$stmtInsert = $pdo->prepare($sqlInsert);

$pdo->beginTransaction();
try {
    $pdo->exec("DELETE FROM supra_dataset_40_slides_atlas");

    $inseridos = 0;
    foreach ($dados as $item) {
        $valores = [];
        foreach ($campos as $dbField) {
            $apiKey = array_search($dbField, $mapa);
            $valor  = $item[$apiKey] ?? null;
            $valor  = tratarArray($valor);
            if ($valor !== null) $valor = trim((string)$valor);

            if (in_array($dbField, $camposData, true)) {
                $valor = converterData($valor);
            } elseif (in_array($dbField, $camposDecimal, true)) {
                $valor = converterValor($valor);
            }

            $valores[] = ($valor === '') ? null : $valor;
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

echo "\n✅ Sincronização do Dataset 40 finalizada!\n";