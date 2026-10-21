<?php
// public importar_supra_dataset_36_infoobras.php

require_once 'config.php';

// Detecta se a chamada é AJAX
$isAjax = (!empty($_GET['ajax']))
       || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    ob_start();
}

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
        return gmdate('Y-m-d', ((int)$valor - 25569) * 86400);
    }
    return null;
}

/**
 * Converte valor para float aceitando:
 *  - número puro (int/float/string)
 *  - formato BR: 65.000.000,00
 *  - formato US: 65,000,000.00
 *  - com R$ na frente
 *  - vazio / N/A → null
 */
function converterValor($valor) {
    if ($valor === null) return null;

    // Já é número nativo
    if (is_int($valor) || is_float($valor)) return (float)$valor;

    // Numérico simples: "70000000" ou "70000000.50"
    if (is_numeric($valor)) return (float)$valor;

    $s = trim((string)$valor);
    if ($s === '' || strtoupper($s) === 'N/A') return null;

    // Remove R$, espaços e NBSP
    $s = preg_replace('/[R$\s\x{00A0}]/u', '', $s);

    // Formato BR: 1.234.567,89  →  1234567.89
    if (preg_match('/^\d{1,3}(\.\d{3})*(,\d+)?$/', $s)) {
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
        return (float)$s;
    }

    // Formato US: 1,234,567.89  →  1234567.89
    if (preg_match('/^\d{1,3}(,\d{3})*(\.\d+)?$/', $s)) {
        $s = str_replace(',', '', $s);
        return (float)$s;
    }

    // Última tentativa: vírgula → ponto
    $s = str_replace(',', '.', $s);
    return is_numeric($s) ? (float)$s : null;
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
    'Ação Principal'                              => 'acao',
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
    "Instrumentos anteriores \n(se houver)"        => 'instrumentos_anteriores',
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
    'Situação Supervisão'                         => 'situacao_supervisao',
    'Empresa Supervisora ATLAS'                   => 'empresa_supervisora_atlas',
    'Contrato Ambiental'                          => 'contrato_ambiental',
    'Início Vigência Acessório Ambiental'         => 'inicio_vigencia_ambiental',
    'Término Vigência Acessório Ambiental'        => 'termino_vigencia_ambiental',
    'Contrato Desapropriação'                     => 'contrato_desapropriacao',
    'Início Vigência Acessório Desapropriação'    => 'inicio_vigencia_desapropriacao',
    'Término Vigência Acessório Desapropriação'   => 'termino_vigencia_desapropriacao',
    'Número SEI do cronograma vigente'            => 'numero_sei_cronograma_vigente',
    'Número SEI da aprovação do cronograma'       => 'numero_sei_aprovacao_cronograma',
    'Data Término dos Serviços'                   => 'data_termino_servicos',
    'Número SEI do cronograma vigente atualizado' => 'numero_sei_cronograma_vigente_atualizado',
    'Data Término do Cronograma de Obra Atualizado' => 'data_termino_cronograma_obra_atualizado',
    'valor previsto'                              => 'valor_previsto',
    'Valor executado'                             => 'valor_executado',
];

$camposData = [
    'data_inicio_vigencia', 'data_termino_vigencia', 'data_termino_execucao',
    'inicio_vigencia_supervisao', 'termino_vigencia_supervisao', 'termino_execucao_supervisao',
    'inicio_vigencia_ambiental', 'termino_vigencia_ambiental',
    'inicio_vigencia_desapropriacao', 'termino_vigencia_desapropriacao',
    'data_termino_servicos', 'data_termino_cronograma_obra_atualizado',
];

$camposDecimal = [
    'kmi', 'valor_total_investimento', 'valor_previsto', 'valor_executado',
];

// ============================================================
// EXECUTAR
// ============================================================
try {
    if (!$isAjax) echo "🔄 Conectando à API (Dataset 36 - Base completa)...\n";
    $resposta = consumirApi($url, $token);
    $dados = json_decode($resposta, true);
    if (!is_array($dados) || empty($dados)) {
        throw new Exception("Resposta vazia ou inválida.");
    }
    if (!$isAjax) echo "✅ Dados obtidos: " . count($dados) . " registros.\n";
} catch (Exception $e) {
    if ($isAjax) {
        if (ob_get_level()) ob_end_clean();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
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
            if ($valor !== null && !is_float($valor) && !is_int($valor)) {
                $valor = trim((string)$valor);
            }

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

    // Atualiza o arquivo de timestamp para o "Última sincronização"
    $arquivoSync = __DIR__ . '/.sync_ultima_execucao';
    @file_put_contents($arquivoSync, date('Y-m-d H:i:s'));

    if ($isAjax) {
        if (ob_get_level()) ob_end_clean();
        echo json_encode([
            'success'       => true,
            'inseridos'     => $inseridos,
            'atualizado_em' => date('d/m/Y H:i'),
        ]);
        exit;
    }

    echo "\n✅ Importação concluída!\n";
    echo "Inseridos: $inseridos\n";
    echo "\n✅ Sincronização do Dataset 36 finalizada!\n";

    if (!headers_sent()) {
        header('Location: notificacoes_obras?sync=ok&inseridos=' . (int)$inseridos);
        exit;
    }

} catch (Exception $e) {
    $pdo->rollBack();
    if ($isAjax) {
        if (ob_get_level()) ob_end_clean();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
    die("❌ Erro: " . $e->getMessage());
}