<?php
// ============================================================
// IMPORT DATASET 1 → supra_dataset_1_todos
// ============================================================
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';

echo "<pre style='font-family:monospace;padding:20px;font-size:13px;'>";
echo "=== IMPORT DATASET 1 ===\n\n";

$url   = "https://supra.dnit.gov.br/index_cgcont_common.php/cgcont/cib/exportBi/1";
$token = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyIjoic3VwcmFfY2liX2FwaUBkbml0Lmdvdi5iciIsInBhc3MiOiJTdXByYUBDaWIxMjM0NUAifQ.GyejoRFofL3mMnN7jD64RBrr2JSB9hVpR_BWrfAtd3U";

// ============================================================
// HELPERS
// ============================================================
function normalizarChave($k) {
    return preg_replace('/\s+/', ' ', trim((string)$k));
}
function tratarValor($v) {
    if (is_array($v)) {
        $limpos = array_filter($v, function($x) { return $x !== '' && $x !== null; });
        return $limpos ? implode(' | ', $limpos) : null;
    }
    return $v;
}
function converterData($v) {
    if (empty($v)) return null;
    $v = trim((string)$v);
    if ($v === '' || strtoupper($v) === 'N/A') return null;
    if (preg_match('/^\d{4}-\d{2}-\d{2}/', $v)) return substr($v, 0, 10);
    if (preg_match('#^\d{2}/\d{2}/\d{4}#', $v)) {
        $p = explode('/', $v);
        return $p[2] . '-' . $p[1] . '-' . $p[0];
    }
    if (preg_match('/^\d{4,5}$/', $v)) {
        $ts = ((int)$v - 25569) * 86400;
        return gmdate('Y-m-d', $ts);
    }
    return null;
}
function converterValor($v) {
    if ($v === null || $v === '') return null;
    if (is_numeric($v)) return (float)$v;
    $v = trim((string)$v);
    return is_numeric($v) ? (float)$v : null;
}

// ============================================================
// MAPA: [chave_api, coluna_db, tipo]
// tipo: 'text' | 'date' | 'decimal' | 'fallback' (só preenche se vazio)
// ============================================================
$mapa = [
    ['Contrato',                                                          'instrumento',                'text'],
    ['UF',                                                                'uf',                         'text'],
    ['BR',                                                                'br',                         'text'],
    ['Nome da Referência',                                                'nome_usual',                 'text'],
    ['Objeto da Contratação',                                             'nome_usual',                 'fallback'],
    ['LOTE',                                                              'lote',                       'text'],
    ['Número do Processo',                                                'numero_processo',            'text'],
    ['Contratado(a)',                                                     'contratado',                 'text'],
    ['Líder do Consórcio',                                                'lider_consorcio',            'text'],
    ['Empresa Projetista',                                                'empresa_projetista',         'text'],
    ['Situação do Contrato',                                              'situacao_contrato',          'text'],
    ['Situação da Obra',                                                  'situacao_obra',              'text'],
    ['Tipo Intervenção',                                                  'tipo_intervencao',           'text'],
    ['Modalidade da Licitação',                                           'modalidade_licitacao',       'text'],
    ['Objeto da Contratação',                                             'objeto_contratacao',         'text'],
    ['Km Inicial',                                                        'km_inicial',                 'decimal'],
    ['Km Final',                                                          'km_final',                   'decimal'],
    ['Extensão',                                                          'extensao',                   'decimal'],
    ['Data de Início da Vigência',                                        'data_inicio_vigencia',       'date'],
    ['Data de Ordem de Início de Serviço de Elaboração de Projeto',       'data_ordem_inicio_projeto',  'date'],
    ['Data de Término prevista para entrega dos Serviços de Elaboração dos Projetos Básico/Executivo', 'data_termino_projeto', 'date'],
    ['Data de Início de Serviço de Obra',                                 'data_inicio_obra',           'date'],
    ['Data do Término de Serviço de Obra',                                'data_termino_obra',          'date'],
    ['Data de Término da Vigência',                                       'data_termino_vigencia',      'date'],
    ['Valor PI',                                                          'valor_pi',                   'decimal'],
    ['Valor Aditivo',                                                     'valor_aditivo',              'decimal'],
    ['Percentual Acumulado de Aditivo',                                   'percentual_aditivo',         'decimal'],
    ['PI VIGENTE',                                                        'pi_vigente',                 'decimal'],
    ['Valor Reajuste',                                                    'valor_reajuste',             'decimal'],
    ['Valor (PI+A+R)',                                                    'valor_pi_a_r',               'decimal'],
    ['Valor PI Medido',                                                   'valor_pi_medido',            'decimal'],
    ['Valor PI + R + A Medido',                                           'valor_pi_r_a_medido',        'decimal'],
    ['Percentual (PI medido)',                                            'percentual_pi_medido',       'decimal'],
    ['As Built / Termo de Recebimento Definitivo - TRD',                  'as_built_trd',               'text'],
    ['Observação - Prazo',                                                'observacao_prazo',           'text'],
    ['Acompanhamentos',                                                   'acompanhamentos',            'text'],
    ['Contrato de Supervisão',                                            'contrato_supervisao',        'text'],
    ['Objeto da Supervisão',                                              'objeto_supervisao',          'text'],
    ['Nome da Referência',                                                'nome_referencia',            'text'],
];

// Colunas únicas para o INSERT
$colunasInsert = [];
foreach ($mapa as $mapaItem) {
    list($apiKey, $dbField, $tipo) = $mapaItem;
    if (!in_array($dbField, $colunasInsert, true)) {
        $colunasInsert[] = $dbField;
    }
}

echo "Colunas do INSERT (" . count($colunasInsert) . "): " . implode(', ', $colunasInsert) . "\n\n";

// ============================================================
// 1. Chama a API
// ============================================================
echo "1) Chamando API...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['token: ' . $token, 'Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 120);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

if ($resp === false) die("❌ cURL: $err\n");
if ($code != 200)   die("❌ HTTP $code\n");

$dados = json_decode($resp, true);
if (!is_array($dados)) die("❌ JSON inválido\n");
echo "   ✅ " . count($dados) . " registros\n\n";

// ============================================================
// 2. Prepara o INSERT
// ============================================================
echo "2) Preparando INSERT...\n";
$ph = implode(',', array_fill(0, count($colunasInsert), '?'));
$sql = "INSERT INTO supra_dataset_1_todos (" . implode(',', $colunasInsert) . ") VALUES ($ph)";

try {
    $stmt = $pdo->prepare($sql);
    echo "   ✅ prepare() ok\n\n";
} catch (PDOException $e) {
    echo "   ❌ ERRO no prepare(): " . $e->getMessage() . "\n";
    echo "   SQL: $sql\n";
    exit;
}

// ============================================================
// 3. Executa
// ============================================================
echo "3) Inserindo registros...\n";
$pdo->beginTransaction();
try {
    $pdo->exec("DELETE FROM supra_dataset_1_todos");

    $n = 0;
    foreach ($dados as $item) {
        // Normaliza chaves
        $itemNorm = [];
        foreach ($item as $k => $v) {
            $itemNorm[normalizarChave($k)] = $v;
        }

        $valores = [];
        $porColuna = [];

        foreach ($mapa as $mapaItem) {
            list($apiKey, $dbField, $tipo) = $mapaItem;
            if ($tipo === 'fallback' && !empty($porColuna[$dbField])) continue;

            $v = $itemNorm[normalizarChave($apiKey)] ?? null;
            $v = tratarValor($v);
            if (is_string($v)) $v = trim($v);

            if ($tipo === 'date')        $v = converterData($v);
            elseif ($tipo === 'decimal') $v = converterValor($v);

            $porColuna[$dbField] = ($v === '') ? null : $v;
        }

        foreach ($colunasInsert as $col) {
            $valores[] = $porColuna[$col] ?? null;
        }

        $stmt->execute($valores);
        $n++;
    }

    $pdo->commit();
    echo "   ✅ {$n} registros inseridos\n\n";
} catch (PDOException $e) {
    $pdo->rollBack();
    echo "   ❌ ERRO no INSERT: " . $e->getMessage() . "\n";
    exit;
}

// ============================================================
// 4. Verifica
// ============================================================
echo "4) Verificando...\n";
$total = $pdo->query("SELECT COUNT(*) FROM supra_dataset_1_todos")->fetchColumn();
echo "   Total na tabela: {$total}\n";
$comNome = $pdo->query("SELECT COUNT(*) FROM supra_dataset_1_todos WHERE nome_usual IS NOT NULL AND TRIM(nome_usual) <> ''")->fetchColumn();
echo "   Com nome_usual: {$comNome}\n";

echo "\n=== FIM ===\n";
echo "</pre>";