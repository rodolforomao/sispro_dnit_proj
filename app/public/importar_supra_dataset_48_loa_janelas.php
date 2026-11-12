<?php
// ============================================================
// IMPORT DATASET 48 → supra_dataset_48_loa_janelas
// LOA Dotações Janelas 2026 ATLAS
//
// Uso normal:   importar_supra_dataset_48_loa_janelas.php
// Uso debug:    importar_supra_dataset_48_loa_janelas.php?debug=1
//               (mostra a resposta bruta da API e não grava nada)
// ============================================================
require_once 'config.php';

if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}

$url = "https://supra.dnit.gov.br/index_cgcont_common.php/cgcont/cib/exportBi/48";

// Token: de preferência defina $SUPRA_TOKEN no config.php.
// Se não existir, cole o token abaixo.
$token = $SUPRA_TOKEN ?? "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyIjoic3VwcmFfY2liX2FwaUBkbml0Lmdvdi5iciIsInBhc3MiOiJTdXByYUBDaWIxMjM0NUAifQ.GyejoRFofL3mMnN7jD64RBrr2JSB9hVpR_BWrfAtd3U";

$debug = isset($_GET['debug']) || (php_sapi_name() === 'cli' && in_array('--debug', $argv ?? [], true));

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
    $err  = curl_error($ch);
    curl_close($ch);
    if ($resp !== false && $code == 200) return $resp;
    throw new Exception("cURL falhou (HTTP $code) $err");
}

function normalizarChave($chave) {
    return preg_replace('/\s+/', ' ', trim((string)$chave));
}

function tratarArray($valor) {
    if (is_array($valor)) {
        return implode(' | ', array_filter($valor, fn($v) => $v !== '' && $v !== null));
    }
    return $valor;
}

// Aceita 17979495, -14000000, "-2979495", "1.234.567,89", "1234.56"
function converterValor($valor) {
    if ($valor === null) return null;
    if (is_int($valor) || is_float($valor)) return (float)$valor;
    $valor = trim((string)$valor);
    if ($valor === '' || strtoupper($valor) === 'N/A') return null;
    if (strpos($valor, ',') !== false) {            // formato BR
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
    }
    return is_numeric($valor) ? (float)$valor : null;
}

// PORTARIAS vem com quebras de linha e espaços sobrando: limpa cada linha
function limparPortarias($valor) {
    if ($valor === null) return null;
    $linhas = preg_split('/\R/', (string)$valor);
    $linhas = array_map('trim', $linhas);
    $linhas = array_filter($linhas, fn($l) => $l !== '');
    return implode("\n", $linhas);
}

// ============================================================
// MAPEAMENTO — formato [chave_api, coluna_db, tipo]
// ============================================================
$campos = [
    ['UF',                    'uf',                    'text'],
    ['AÇÃO',                  'acao',                  'text'],
    ['DESCRITOR',             'descritor',             'text'],
    ['LOA SANCIONADA',        'loa_sancionada',        'decimal'],
    ['PORTARIAS',             'portarias',             'portarias'],
    ['1ª JANELA',             'janela_1',              'decimal'],
    ['2ª JANELA',             'janela_2',              'decimal'],
    ['3ª JANELA',             'janela_3',              'decimal'],
    ['4ª JANELA',             'janela_4',              'decimal'],
    ['JANELA EXTRAORDINÁRIA', 'janela_extraordinaria', 'decimal'],
    ['LOA ATUALIZADA',        'loa_atualizada',        'decimal'],
];

$colunasInsert = array_column($campos, 1);

// ============================================================
// TABELA (cria se não existir)
// ============================================================
$pdo->exec("
CREATE TABLE IF NOT EXISTS supra_dataset_48_loa_janelas (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    uf                      VARCHAR(2)    NULL,
    acao                    VARCHAR(20)   NULL,
    descritor               TEXT          NULL,
    loa_sancionada          DECIMAL(18,2) NULL,
    portarias               MEDIUMTEXT    NULL,
    janela_1                DECIMAL(18,2) NULL,
    janela_2                DECIMAL(18,2) NULL,
    janela_3                DECIMAL(18,2) NULL,
    janela_4                DECIMAL(18,2) NULL,
    janela_extraordinaria   DECIMAL(18,2) NULL,
    loa_atualizada          DECIMAL(18,2) NULL,
    importado_em            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_uf_acao (uf, acao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ============================================================
// BUSCA NA API
// ============================================================
try {
    echo "🔄 Conectando à API (DataSet 48 - LOA Dotações Janelas 2026 ATLAS)...\n";
    $resposta = consumirApi($url, $token);

    if ($debug) {
        echo "\n--- RESPOSTA BRUTA (primeiros 3000 caracteres) ---\n";
        echo substr($resposta, 0, 3000) . "\n";
        echo "--- FIM (modo debug: nada foi gravado) ---\n";
        exit;
    }

    $dados = json_decode($resposta, true);

    // Desembrulha envelopes comuns: {"data":[...]}, {"dados":[...]} etc.
    if (is_array($dados) && !isset($dados[0])) {
        foreach (['data', 'dados', 'result', 'results', 'items'] as $k) {
            if (isset($dados[$k]) && is_array($dados[$k])) {
                $dados = $dados[$k];
                break;
            }
        }
    }

    // Mantém só registros válidos (arrays associativos)
    $dados = is_array($dados) ? array_values(array_filter($dados, 'is_array')) : [];

    if (empty($dados)) {
        throw new Exception("Nenhum registro válido. Resposta bruta da API:\n" . substr($resposta, 0, 1500));
    }
    echo "✅ Dados obtidos: " . count($dados) . " registros.\n";
} catch (Exception $e) {
    die("❌ Erro: " . $e->getMessage());
}

// ============================================================
// GRAVA NO BANCO
// ============================================================
$sqlInsert = "INSERT INTO supra_dataset_48_loa_janelas (" . implode(',', $colunasInsert) . ")
              VALUES (" . implode(',', array_fill(0, count($colunasInsert), '?')) . ")";
$stmtInsert = $pdo->prepare($sqlInsert);

$pdo->beginTransaction();
try {
    $pdo->exec("DELETE FROM supra_dataset_48_loa_janelas");

    $inseridos = 0;
    $inconsistentes = [];
    $semChaves = 0;

    foreach ($dados as $item) {
        $itemNorm = [];
        foreach ($item as $k => $v) {
            $itemNorm[normalizarChave($k)] = $v;
        }

        $valores = [];
        $linha = [];
        $achouAlgumaChave = false;

        foreach ($campos as [$apiKey, $dbField, $tipo]) {
            $chaveNorm = normalizarChave($apiKey);
            if (array_key_exists($chaveNorm, $itemNorm)) $achouAlgumaChave = true;

            $valor = $itemNorm[$chaveNorm] ?? null;
            $valor = tratarArray($valor);

            if ($tipo === 'decimal') {
                $valor = converterValor($valor);
            } elseif ($tipo === 'portarias') {
                $valor = limparPortarias($valor);
            } else {
                $valor = ($valor !== null) ? trim((string)$valor) : null;
            }

            if ($valor === '') $valor = null;
            $valores[] = $valor;
            $linha[$dbField] = $valor;
        }

        // Registro sem nenhuma chave reconhecida: ignora
        if (!$achouAlgumaChave) {
            $semChaves++;
            continue;
        }

        // Conferência: LOA atualizada = sancionada + soma das janelas
        $soma = (float)$linha['loa_sancionada']
              + (float)$linha['janela_1'] + (float)$linha['janela_2']
              + (float)$linha['janela_3'] + (float)$linha['janela_4']
              + (float)$linha['janela_extraordinaria'];
        if (abs($soma - (float)$linha['loa_atualizada']) > 0.01) {
            $inconsistentes[] = ($linha['uf'] ?? '?') . '/' . ($linha['acao'] ?? '?');
        }

        $stmtInsert->execute($valores);
        $inseridos++;
    }

    // Se nada foi reconhecido, não apaga a tabela
    if ($inseridos === 0) {
        throw new Exception("Nenhum registro com as chaves esperadas (UF, AÇÃO, DESCRITOR, ...). Rode com ?debug=1 para ver a resposta da API.");
    }

    $pdo->commit();
    echo "\n✅ Importação concluída!\n";
    echo "Inseridos: $inseridos\n";
    if ($semChaves > 0) {
        echo "⚠️ Ignorados (sem chaves reconhecidas): $semChaves\n";
    }

    $totSanc = (float)$pdo->query("SELECT COALESCE(SUM(loa_sancionada),0) FROM supra_dataset_48_loa_janelas")->fetchColumn();
    $totAtu  = (float)$pdo->query("SELECT COALESCE(SUM(loa_atualizada),0) FROM supra_dataset_48_loa_janelas")->fetchColumn();
    echo "Total LOA sancionada: R$ " . number_format($totSanc, 2, ',', '.') . "\n";
    echo "Total LOA atualizada: R$ " . number_format($totAtu, 2, ',', '.') . "\n";

    if ($inconsistentes) {
        echo "⚠️ " . count($inconsistentes) . " registro(s) onde sancionada + janelas ≠ atualizada: "
             . implode(', ', array_slice($inconsistentes, 0, 20)) . "\n";
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    die("❌ Erro: " . $e->getMessage());
}

echo "\n✅ Sincronização do Dataset 48 finalizada!\n";