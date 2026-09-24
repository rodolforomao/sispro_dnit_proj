<?php
require_once 'config.php';

// ============================================================
// IMPORT DATASET 36 → supra_dataset_36_todos
// ============================================================
$url = "https://supra.dnit.gov.br/index_cgcont_common.php/cgcont/cib/exportBi/36";
$token = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyIjoic3VwcmFfY2liX2FwaUBkbml0Lmdvdi5iciIsInBhc3MiOiJTdXByYUBDaWIxMjM0NUAifQ.GyejoRFofL3mMnN7jD64RBrr2JSB9hVpR_BWrfAtd3U";

// ============================================================
// FUNÇÕES AUXILIARES
// ============================================================
function consumirApi($url, $token) {
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

function tratarArray($valor) {
    if (is_array($valor)) {
        return implode(' | ', array_filter($valor, function($v) { return $v !== '' && $v !== null; }));
    }
    return $valor;
}

/**
 * Converte data para Y-m-d.
 * Aceita: Y-m-d, d/m/Y, serial Excel (número inteiro de 4-5 dígitos).
 */
function converterData($valor) {
    if (empty($valor)) return null;
    $valor = trim((string)$valor);
    if ($valor === '' || strtoupper($valor) === 'N/A') return null;

    // Y-m-d
    if (preg_match('/^\d{4}-\d{2}-\d{2}/', $valor)) {
        return substr($valor, 0, 10);
    }

    // d/m/Y
    if (preg_match('#^\d{2}/\d{2}/\d{4}#', $valor)) {
        $p = explode('/', $valor);
        return $p[2] . '-' . $p[1] . '-' . $p[0];
    }

    // Serial Excel (inteiro de 4-5 dígitos)
    if (preg_match('/^\d{4,5}$/', $valor)) {
        $serial = (int)$valor;
        // Base: 25569 = dias entre 1900-01-01 e 1970-01-01 (com bug do Excel)
        $timestamp = ($serial - 25569) * 86400;
        return gmdate('Y-m-d', $timestamp);
    }

    return null;
}

function converterValor($valor) {
    if (empty($valor)) return null;
    $valor = trim((string)$valor);
    if (!is_numeric($valor)) return null;
    return (float)$valor;
}

// ============================================================
// MAPEAMENTO — chave da API => coluna no banco
// ============================================================
$mapa = [
    'Instrumento'                                 => 'instrumento',
    'UF'                                          => 'uf',
    'BR'                                          => 'br',
    'Lote'                                        => 'lote',
    'Nome usual'                                  => 'nome_usual',
    'Ação'                                        => 'acao',
    'Código PPA'                                  => 'codigo_ppa',
    'FID (GEO)'                                   => 'fid_geo',
    'Tipo de contratação'                         => 'tipo_contratacao',
    'Processo Licitação'                          => 'processo_licitacao',
    'Edital (COCCONV)'                            => 'edital_cocconv',
    'Dispensa de Licitação (COCCONV)'             => 'dispensa_cocconv',
    'Edital ou Dispensa Manual (COAC)'            => 'edital_dispensa_manual_coac',
    'kmi'                                         => 'kmi',
    'kmf'                                         => 'kmf',
    'Extensão SIAC'                               => 'extensao_siac',
    'Extensão real'                               => 'extensao_real',
    'Tipo de execução'                            => 'tipo_execucao',
    'Tipo de pavimento'                           => 'tipo_pavimento',
    'Conceito da Obra'                            => 'conceito_obra',
    'Divisão em segmentos'                        => 'divisao_segmentos',
    'Empresa construtora'                         => 'empresa_construtora',
    'Data início vigência'                        => 'data_inicio_vigencia',
    'Data término vigência'                       => 'data_termino_vigencia',
    'Data término execução'                       => 'data_termino_execucao',
    'Processo base'                               => 'processo_base',
    'É remanescente?'                             => 'eh_remanescente',
    'Instrumentos anteriores 
(se houver)'                                    => 'instrumentos_anteriores',
    'ID PAC'                                      => 'id_pac',
    'Nome do Empreendimento governa'              => 'nome_empreendimento_governa',
    'Municípios governa'                          => 'municipios_governa',
    'Valor Total de Investimento governa'         => 'valor_total_investimento',
    'Instrumento supervisão (COAC)'               => 'instrumento_supervisao_coac',
    'Supervisão (COCCONV)'                        => 'supervisao_cocconv',
    'Proc. licitação supervisão'                  => 'proc_licitacao_supervisao',
    'Edital supervisão (COCCONV)'                 => 'edital_supervisao_cocconv',
    'Dispensa de Licitação supervisão (COCCONV)'  => 'dispensa_supervisao_cocconv',
    'Edital/Dispensa supervisão manual (COAC)'    => 'edital_dispensa_supervisao_manual_coac',
    'Empresa supervisão'                          => 'empresa_supervisao',
    'Início vigência supervisão'                  => 'inicio_vigencia_supervisao',
    'Término vigência supervisão'                 => 'termino_vigencia_supervisao',
    'Término execução supervisão'                 => 'termino_execucao_supervisao',
    'Proc. Base supervisão'                       => 'proc_base_supervisao',
    'Contrato Ambiental'                          => 'contrato_ambiental',
    'Início Vigência Acessório Ambiental'         => 'inicio_vigencia_ambiental',
    'Término Vigência Acessório Ambiental'        => 'termino_vigencia_ambiental',
    'Contrato Desapropriação'                     => 'contrato_desapropriacao',
    'Início Vigência Acessório Desapropriação'    => 'inicio_vigencia_desapropriacao',
    'Término Vigência Acessório Desapropriação'   => 'termino_vigencia_desapropriacao',
];

// Colunas que precisam de conversão de data
$camposData = [
    'data_inicio_vigencia', 'data_termino_vigencia', 'data_termino_execucao',
    'inicio_vigencia_supervisao', 'termino_vigencia_supervisao', 'termino_execucao_supervisao',
    'inicio_vigencia_ambiental', 'termino_vigencia_ambiental',
    'inicio_vigencia_desapropriacao', 'termino_vigencia_desapropriacao',
];

// Colunas numéricas
$camposDecimal = ['kmi', 'valor_total_investimento'];

// ============================================================
// EXECUTAR
// ============================================================
try {
    echo "🔄 Conectando à API (Dataset 36 - Base completa)...\n";
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
$sqlInsert = "INSERT INTO supra_dataset_36_todos (" . implode(',', $campos) . ")
              VALUES (" . implode(',', array_fill(0, count($campos), '?')) . ")";
$stmtInsert = $pdo->prepare($sqlInsert);

$pdo->beginTransaction();
try {
    $pdo->exec("DELETE FROM supra_dataset_36_todos");

    $inseridos = 0;
    foreach ($dados as $item) {
        $valores = [];
        foreach ($campos as $dbField) {
            $apiKey = array_search($dbField, $mapa);
            $valor = $item[$apiKey] ?? null;
            $valor = tratarArray($valor);
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

echo "\n✅ Sincronização do Dataset 36 finalizada!\n";