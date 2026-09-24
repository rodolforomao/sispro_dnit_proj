<?php
// ============================================================
// IMPORT DATASET 38 → supra_dataset_38_todos
// Lista Todos Instrumentos
// ============================================================
require_once 'config.php';

$url   = "https://supra.dnit.gov.br/index_cgcont_common.php/cgcont/cib/exportBi/38";
$token = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyIjoic3VwcmFfY2liX2FwaUBkbml0Lmdvdi5iciIsInBhc3MiOiJTdXByYUBDaWIxMjM0NUAifQ.GyejoRFofL3mMnN7jD64RBrr2JSB9hVpR_BWrfAtd3U";

// ============================================================
// HELPERS
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
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($resp !== false && $code == 200) return $resp;
    throw new Exception("cURL falhou (HTTP $code)");
}

function normalizarChave($chave) {
    return preg_replace('/\s+/', ' ', trim((string)$chave));
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
        $timestamp = ($serial - 25569) * 86400;
        return gmdate('Y-m-d', $timestamp);
    }
    return null;
}

function converterValor($valor) {
    if ($valor === null || $valor === '') return null;
    if (is_numeric($valor)) return (float)$valor;
    $valor = trim((string)$valor);
    if ($valor === '' || !is_numeric($valor)) return null;
    return (float)$valor;
}

// ============================================================
// MAPEAMENTO — formato [chave_api, coluna_db, tipo]
// ============================================================
$campos = [
    ['Instrumento',                       'instrumento',          'text'],
    ['Estado',                            'uf',                   'text'],
    ['BR',                                'br',                   'text'],
    // ✅ nome_usual agora tem origem: Empreendimento (conforme NPO)
    ['Empreendimento (conforme NPO)',     'nome_usual',           'text'],

    ['Tipo (manual)',                     'tipo_manual',          'text'],
    ['Intervenção para lista do ATLAS',   'intervencao_atlas',    'text'],
    ['AÇÃO',                              'acao',                 'text'],
    ['Outras ações?',                     'outras_acoes',         'text'],
    ['Todas as Ações',                    'todas_acoes',          'text'],
    ['Empreendimento (conforme NPO)',     'empreendimento_npo',   'text'],
    ['Unidade MANUAL',                    'unidade_manual',       'text'],
    ['Entrar na Lista do Atlas?',         'entrar_lista_atlas',   'text'],
    ['Tipo de contratação',               'tipo_contratacao',     'text'],
    ['Lote',                              'lote',                 'text'],
    ['Empresa',                           'empresa',              'text'],
    ['Início Vigência',                   'inicio_vigencia',      'date'],
    ['Término Vigência',                  'termino_vigencia',     'date'],
    ['Valor (PI+R+A)',                    'valor_pi_r_a',         'decimal'],
    ['Saldo a Executar',                  'saldo_a_executar',     'decimal'],
];

$colunasInsert = [];
foreach ($campos as $campoItem) {
    list($apiKey, $dbField, $tipo) = $campoItem;
    if (!in_array($dbField, $colunasInsert, true)) {
        $colunasInsert[] = $dbField;
    }
}

// ============================================================
// EXECUTA
// ============================================================
try {
    echo "🔄 Conectando à API (DataSet 38 - Lista Todos Instrumentos)...\n";
    $resposta = consumirApi($url, $token);
    $dados = json_decode($resposta, true);
    if (!is_array($dados) || empty($dados)) {
        throw new Exception("Resposta vazia ou inválida.");
    }
    echo "✅ Dados obtidos: " . count($dados) . " registros.\n";
} catch (Exception $e) {
    die("❌ Erro: " . $e->getMessage());
}

$sqlInsert = "INSERT INTO supra_dataset_38_todos (" . implode(',', $colunasInsert) . ")
              VALUES (" . implode(',', array_fill(0, count($colunasInsert), '?')) . ")";
$stmtInsert = $pdo->prepare($sqlInsert);

$pdo->beginTransaction();
try {
    $pdo->exec("DELETE FROM supra_dataset_38_todos");

    $inseridos = 0;
    foreach ($dados as $item) {
        $itemNorm = [];
        foreach ($item as $k => $v) {
            $itemNorm[normalizarChave($k)] = $v;
        }

        $valoresPorColuna = [];
        foreach ($campos as $campoItem) {
            list($apiKey, $dbField, $tipo) = $campoItem;
            if (isset($valoresPorColuna[$dbField]) && $valoresPorColuna[$dbField] !== null && $valoresPorColuna[$dbField] !== '') {
                continue;
            }
            $chaveNorm = normalizarChave($apiKey);
            $valor = $itemNorm[$chaveNorm] ?? null;
            $valor = tratarArray($valor);
            if ($valor !== null) $valor = trim((string)$valor);

            if ($tipo === 'date') {
                $valor = converterData($valor);
            } elseif ($tipo === 'decimal') {
                $valor = converterValor($valor);
            }

            $valoresPorColuna[$dbField] = ($valor === '') ? null : $valor;
        }

        $valores = [];
        foreach ($colunasInsert as $col) {
            $valores[] = $valoresPorColuna[$col] ?? null;
        }

        $stmtInsert->execute($valores);
        $inseridos++;
    }

    $pdo->commit();
    echo "\n✅ Importação concluída!\n";
    echo "Inseridos: $inseridos\n";

    // Estatísticas
    $comNome = (int)$pdo->query("SELECT COUNT(*) FROM supra_dataset_38_todos WHERE nome_usual IS NOT NULL AND TRIM(nome_usual) <> ''")->fetchColumn();
    echo "Registros com 'nome_usual' preenchido: $comNome de $inseridos\n";

} catch (Exception $e) {
    $pdo->rollBack();
    die("❌ Erro: " . $e->getMessage());
}

echo "\n✅ Sincronização do Dataset 38 finalizada!\n";