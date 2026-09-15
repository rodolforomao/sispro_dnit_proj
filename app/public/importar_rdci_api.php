<?php
require_once 'config.php';

// ============================================================
// CONFIGURAÇÕES
// ============================================================
$url = "https://supra.dnit.gov.br/index_cgcont_common.php/cgcont/cib/exportBi/26";
$token = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyIjoic3VwcmFfY2liX2FwaUBkbml0Lmdvdi5iciIsInBhc3MiOiJTdXByYUBDaWIxMjM0NUAifQ.GyejoRFofL3mMnN7jD64RBrr2JSB9hVpR_BWrfAtd3U";

// ============================================================
// FUNÇÃO DE CONSUMO
// ============================================================
function consumirApi($url, $token) {
    if (ini_get('allow_url_fopen')) {
        $options = [
            'http' => [
                'method' => 'GET',
                'header' => "token: $token\r\n" . "Content-Type: application/json\r\n",
                'timeout' => 30,
                'ignore_errors' => true
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];
        $context = stream_context_create($options);
        $resposta = @file_get_contents($url, false, $context);
        if ($resposta !== false) return $resposta;
    }

    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'token: ' . $token,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $resposta = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($resposta !== false && $httpCode == 200) return $resposta;
        throw new Exception("cURL falhou (HTTP $httpCode)");
    }
    throw new Exception("Nenhum método disponível.");
}

// ============================================================
// FUNÇÕES DE CONVERSÃO
// ============================================================
function converterData($data) {
    if (empty($data)) return null;
    $data = trim($data);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) return $data;
    if (preg_match('/^\d{4}-\d{2}-\d{2}/', $data)) return substr($data, 0, 10);
    if (preg_match('/^\d{2}\/\d{2}\/\d{4}/', $data)) {
        $p = explode('/', $data);
        return $p[2] . '-' . $p[1] . '-' . $p[0];
    }
    return null;
}

function converterValor($valor) {
    if (empty($valor)) return null;
    $valor = trim($valor);
    $valor = str_replace(['R$', ' ', '.'], '', $valor);
    $valor = str_replace(',', '.', $valor);
    return is_numeric($valor) ? (float) $valor : null;
}

function tratarArray($valor) {
    if (is_array($valor)) {
        return implode(' | ', array_filter($valor, function($v) { return !empty($v); }));
    }
    return $valor;
}

// ============================================================
// MAPEAMENTO MANUAL COMPLETO
// ============================================================
$mapa = [
    'INSTRUMENTO' => 'instrumento',
    'UF' => 'uf',
    'BR' => 'br',
    'REGIÃO' => 'regiao',
    'LOTE' => 'lote',
    'NOME USUAL' => 'nome_usual',
    'Nº ID PAC' => 'n_id_pac',
    'SUBTRECHO' => 'subtrecho',
    'EMPRESA' => 'empresa',
    'SUPERVISORA' => 'supervisora',
    'PROCESSO BASE' => 'processo_base',
    'PROCESSO DE PROJETO' => 'processo_projeto',
    'FASE' => 'fase',
    'DATA DA ORDEM DE INÍCIO DE PROJETO' => 'data_ordem_inicio_projeto',
    'DATA DA ORDEM DE INÍCIO DE OBRA' => 'data_ordem_inicio_obra',
    'DATA DO TÉRMINO DE SERVIÇO' => 'data_termino_servico',
    'DATA DO TÉRMINO DE VIGÊNCIA' => 'data_termino_vigencia',
    'EDITAL' => 'edital',
    'SITUAÇÃO DO CONTRATO (SIAC)' => 'situacao_contrato_siac',
    'SITUAÇÃO DO PROJETO' => 'situacao_projeto',
    'OBJETO DO CONTRATO' => 'objeto_contrato',
    'KM INICIAL' => 'km_inicial',
    'KM FINAL' => 'km_final',
    'EXTENSÃO' => 'extensao',
    'Geológico' => 'geologico',
    'Topográfico' => 'topografico',
    'Tráfego' => 'trafego',
    'Geotécnico' => 'geotecnico',
    'Hidrológico' => 'hidrologico',
    'Hidráulico e Hidrológico de OAE' => 'hidraulico_hidrologico_oae',
    'OAE' => 'oae',
    'Avaliação Ambiental' => 'avaliacao_ambiental',
    'Traçado' => 'tracado',
    'Pavimento' => 'pavimento',
    'Geométrico' => 'geometrico_basico',
    'Terraplanagem' => 'terraplanagem_basico',
    'Drenagem e OAC' => 'drenagem_oac_basico',
    'Pavimentação' => 'pavimentacao_basico',
    'Iluminação' => 'iluminacao_basico',
    'Sinalização e Segurança Viária' => 'sinalizacao_seguranca_viaria_basico',
    'Obras Complem. - OC' => 'obras_complem_oc_basico',
    'Contenções' => 'contencoes_basico',
    'OAE_1160' => 'oae_basico',
    'Componente Ambiental' => 'componente_ambiental_basico',
    'Desapropriação' => 'desapropriacao_basico',
    'Passarela' => 'passarela_basico',
    'Interseções, Retornos e Acessos' => 'interseccoes_retornos_acessos_basico',
    'Reassentamento' => 'reassentamento_basico',
    'Soluções de Interferências' => 'solucoes_interferencias_basico',
    'Paisagismo' => 'paisagismo_basico',
    'Restauração do Pavimento' => 'restauracao_pavimento_basico',
    'Reabilitação da Faixa de Domínio' => 'reabilitacao_faixa_dominio_basico',
    'Aduana' => 'aduana_basico',
    'Tráfego_1171' => 'trafego_basico',
    'Orçamento' => 'orcamento_basico',
    'Geométrico_1173' => 'geometrico_executivo',
    'Terraplanagem_1174' => 'terraplanagem_executivo',
    'Drenagem e OAC_1175' => 'drenagem_oac_executivo',
    'Pavimentação_1176' => 'pavimentacao_executivo',
    'Iluminação_1177' => 'iluminacao_executivo',
    'Sinalização e Segurança Viária_1178' => 'sinalizacao_seguranca_viaria_executivo',
    'Obras Complem. - OC_1179' => 'obras_complem_oc_executivo',
    'Contenções_1180' => 'contencoes_executivo',
    'OAE_1181' => 'oae_executivo',
    'Componente Ambiental_1182' => 'componente_ambiental_executivo',
    'Desapropriação_1183' => 'desapropriacao_executivo',
    'Passarela_1184' => 'passarela_executivo',
    'Interseções, Retornos e Acessos_1185' => 'interseccoes_retornos_acessos_executivo',
    'Reassentamento_1186' => 'reassentamento_executivo',
    'Soluções de Interferências_1187' => 'solucoes_interferencias_executivo',
    'Paisagismo_1188' => 'paisagismo_executivo',
    'Restauração do Pavimento_1189' => 'restauracao_pavimento_executivo',
    'Reabilitação da Faixa de Domínio_1190' => 'reabilitacao_faixa_dominio_executivo',
    'Aduana_1191' => 'aduana_executivo',
    'Tráfego_1192' => 'trafego_executivo',
    'Orçamento_1193' => 'orcamento_executivo',
    'Projeto Concepção - Geométrico' => 'projeto_concepcao_geometrico',
    'Estudos Hidráulico /Hidrológico' => 'estudos_hidraulico_hidrologico',
    'BÁSICO - Infraestrutura' => 'basico_infraestrutura',
    'BÁSICO - Mesoestrutura' => 'basico_mesoestrutura',
    'BÁSICO - Superestrutura' => 'basico_superestrutura',
    'EXECUTIVO - Infraestrutura' => 'executivo_infraestrutura',
    'EXECUTIVO - Mesoestrutura' => 'executivo_mesoestrutura',
    'EXECUTIVO - Superestrutura' => 'executivo_superestrutura',
    'Sinalização Viária e Náutica' => 'sinalizacao_viaria_nautica',
    'Componente Ambiental_1203' => 'componente_ambiental_resumo',
    'Complementares - Marinha' => 'complementares_marinha',
    'ESTUDOS' => 'estudos_resumo',
    'GEOMÉTRICO' => 'geometrico_resumo',
    'PROJETO BÁSICO' => 'projeto_basico_resumo',
    'PROJETO EXECUTIVO' => 'projeto_executivo_resumo',
    'Resumo Informativo' => 'resumo_informativo',
    'CRONOGRAMA SEI' => 'cronograma_sei',
    'PERÍODO EDITAL (dias)' => 'periodo_edital_dias',
    'DATA TERMINO PROJETO EDITAL' => 'data_termino_projeto_edital',
    'DATA TERMINO PROJETO CRONOG.' => 'data_termino_projeto_cronog',
    'Cronograma e Vigencia' => 'cronograma_vigencia',
    'Situação do Cronograma' => 'situacao_cronograma',
    'STATUS CRONOG. ATUAL' => 'status_cronograma_atual',
    'JUSTIFICATIVA CRONOGRAMA' => 'justificativa_cronograma',
    'Processo de Notificação por SR/UF' => 'processo_notificacao_sr_uf',
    'Data da Última Notificação' => 'data_ultima_notificacao',
    'Nº SEI Ofício Cobrança Cronograma' => 'n_sei_oficio_cobranca_cronograma',
    'STATUS GERAL' => 'status_geral',
    'STATUS CONTRATO (SÍNTESE)' => 'status_contrato_sintese',
    'ANÁLISE' => 'analise',
    'SEI Delegação' => 'sei_delegacao',
    'Observações' => 'observacoes',
    'Nº SEI Proj. Geométrico' => 'n_sei_proj_geometrico',
    'Projeto Básico Finalizado' => 'projeto_basico_finalizado',
    'Obra Iniciada' => 'obra_iniciada',
    'Contratos Listados TCU - Portaria 6398 (22954489)' => 'contratos_listados_tcu_portaria',
    'PAAR' => 'paar',
    'Situação PAAR' => 'situacao_paar',
    'Atualização' => 'atualizacao',
    'DATA ATUALIZAÇÃO' => 'data_atualizacao',
    'Atualização dos Dados' => 'atualizacao_dados',
    'Valor(PI + A + R)' => 'valor_pi_a_r',
    'Valor Projetos' => 'valor_projetos',
];

// ============================================================
// VERIFICAR / CRIAR ÍNDICE ÚNICO NA TABELA
// ============================================================
function garantirIndiceUnico($pdo) {
    // Verifica se o índice já existe
    $stmt = $pdo->query("SHOW INDEX FROM contratos_rdci WHERE Key_name = 'unique_instrumento_subtrecho'");
    $existe = $stmt->fetch();
    if (!$existe) {
        echo "🔧 Criando índice UNIQUE em (instrumento, subtrecho)...\n";
        // Remove possíveis duplicatas antes de criar o índice
        $pdo->exec("
            DELETE t1 FROM contratos_rdci t1
            INNER JOIN contratos_rdci t2 
            WHERE t1.id > t2.id 
              AND t1.instrumento = t2.instrumento 
              AND (t1.subtrecho = t2.subtrecho OR (t1.subtrecho IS NULL AND t2.subtrecho IS NULL))
        ");
        echo "   Duplicatas removidas.\n";
        // Cria o índice
        $pdo->exec("ALTER TABLE contratos_rdci ADD UNIQUE INDEX unique_instrumento_subtrecho (instrumento, subtrecho)");
        echo "   Índice criado com sucesso.\n";
    } else {
        echo "✅ Índice UNIQUE já existe.\n";
    }
}

// ============================================================
// EXECUTAR
// ============================================================
$reset = isset($_GET['reset']) && $_GET['reset'] == 1;

try {
    echo "🔄 Conectando à API...\n";
    $resposta = consumirApi($url, $token);
    $dados = json_decode($resposta, true);
    if (!is_array($dados) || empty($dados)) {
        throw new Exception("Resposta vazia ou inválida.");
    }
    echo "✅ Dados obtidos: " . count($dados) . " registros.\n";
} catch (Exception $e) {
    die("❌ Erro: " . $e->getMessage());
}

// Se reset=1, truncar a tabela
if ($reset) {
    $pdo->exec("TRUNCATE TABLE contratos_rdci");
    echo "🔄 Tabela contratos_rdci foi esvaziada.\n";
}

// Garantir índice único (evita duplicatas)
garantirIndiceUnico($pdo);

// ============================================================
// PREPARAR LISTA DE CHAVES (INSTRUMENTO + SUBTRECHO) DA API
// ============================================================
$chavesApi = [];
foreach ($dados as $item) {
    $instrumento = trim($item['INSTRUMENTO'] ?? '');
    $subtrecho = trim($item['SUBTRECHO'] ?? '');
    if (!empty($instrumento)) {
        $chave = $instrumento . '|' . $subtrecho;
        $chavesApi[] = $chave;
    }
}
$chavesApi = array_unique($chavesApi);

// ============================================================
// INSERIR/ATUALIZAR DADOS (COM ON DUPLICATE KEY UPDATE)
// ============================================================
$camposInsert = array_values($mapa);
$camposInsert = array_unique($camposInsert);

// Monta SQL com ON DUPLICATE KEY UPDATE
$sql = "INSERT INTO contratos_rdci (" . implode(',', $camposInsert) . ")
        VALUES (" . implode(',', array_fill(0, count($camposInsert), '?')) . ")
        ON DUPLICATE KEY UPDATE " . implode(',', array_map(function ($c) { return "$c = VALUES($c)"; }, $camposInsert));

$stmtInsert = $pdo->prepare($sql);

$linha = 0;
$importados = 0;
$ignorados = 0;

$pdo->beginTransaction();

try {
    foreach ($dados as $item) {
        $linha++;
        $valores = [];
        foreach ($camposInsert as $dbField) {
            $apiKey = array_search($dbField, $mapa);
            $valor = $item[$apiKey] ?? null;
            $valor = tratarArray($valor);
            if ($valor !== null) {
                $valor = trim($valor);
            }
            if (strpos($dbField, 'data') === 0 || $dbField === 'data_ultima_notificacao') {
                $valor = converterData($valor);
            } elseif (strpos($dbField, 'valor_') === 0 || $dbField === 'valor_pi_a_r' || $dbField === 'valor_projetos') {
                $valor = converterValor($valor);
            } elseif ($dbField === 'periodo_edital_dias') {
                $valor = is_numeric($valor) ? (int) $valor : null;
            }
            $valores[] = $valor;
        }

        $instrumentoIndex = array_search('instrumento', $camposInsert);
        if ($instrumentoIndex !== false && empty($valores[$instrumentoIndex])) {
            $ignorados++;
            continue;
        }

        $stmtInsert->execute($valores);
        $importados++;
        if ($linha % 50 == 0) echo "Processados $linha registros...\n";
    }

    $pdo->commit();
    echo "\n✅ Importação/atualização concluída!\n";
    echo "Importados/atualizados: $importados\n";
    echo "Ignorados (sem instrumento): $ignorados\n";

} catch (Exception $e) {
    $pdo->rollBack();
    die("❌ Erro na linha $linha: " . $e->getMessage());
}

// ============================================================
// REMOVER CONTRATOS QUE NÃO EXISTEM MAIS NA API
// ============================================================
if (!empty($chavesApi)) {
    // Buscar todas as combinações (instrumento, subtrecho) que estão no banco
    $sqlSelect = "SELECT instrumento, subtrecho FROM contratos_rdci";
    $stmtSelect = $pdo->prepare($sqlSelect);
    $stmtSelect->execute();
    $registrosBanco = $stmtSelect->fetchAll(PDO::FETCH_ASSOC);

    $chavesBanco = [];
    foreach ($registrosBanco as $row) {
        $chave = $row['instrumento'] . '|' . ($row['subtrecho'] ?? '');
        $chavesBanco[] = $chave;
    }

    // Encontrar chaves que estão no banco mas não na API
    $chavesRemover = array_diff($chavesBanco, $chavesApi);

    if (!empty($chavesRemover)) {
        $deletados = 0;
        foreach ($chavesRemover as $chave) {
            list($instrumento, $subtrecho) = explode('|', $chave);
            $sqlDelete = "DELETE FROM contratos_rdci WHERE instrumento = ? AND (subtrecho = ? OR (subtrecho IS NULL AND ? IS NULL))";
            $stmtDelete = $pdo->prepare($sqlDelete);
            $stmtDelete->execute([$instrumento, $subtrecho, $subtrecho]);
            $deletados += $stmtDelete->rowCount();
        }
        echo "🗑️ Removidos $deletados contratos que não existem mais na API.\n";
    } else {
        echo "✅ Nenhum contrato obsoleto para remover.\n";
    }
} else {
    echo "⚠️ Nenhuma chave válida encontrada na API. Nenhum contrato foi removido.\n";
}

echo "\n✅ Sincronização concluída!\n";
echo "Dica: para recarregar do zero (apagar tudo e importar), use ?reset=1 na URL.\n";