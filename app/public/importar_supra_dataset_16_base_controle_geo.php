<?php
require_once 'config.php';

// ============================================================
// IMPORT DATASET 16 → supra_dataset_16_base_controle_geo
// ============================================================
$url   = "https://supra.dnit.gov.br/index_cgcont_common.php/cgcont/cib/exportBi/16";
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
    'FID'                       => 'fid',
    'Shape'                     => 'shape',
    'FONTE_SEG'                 => 'fonte_seg',
    'BR'                        => 'br',
    'UF'                        => 'uf',
    'BR_UF'                     => 'br_uf',
    'TIPO'                      => 'tipo',
    'O_INSTRUM'                 => 'o_instrum',
    'O_EMPRESA'                 => 'o_empresa',
    'O_OBJ_RESU'                => 'o_obj_resu',
    'O_KM_INICI'                => 'o_km_inici',
    'O_KM_FINAL'                => 'o_km_final',
    'O_EXTENSAO'                => 'o_extensao',
    'O_LOTE'                    => 'o_lote',
    'O_INTERVEN'                => 'o_interven',
    'ESPACIALIZ'                => 'espacializ',
    'ID_OBRA'                   => 'id_obra',
    'ID_PAC'                    => 'id_pac',
    'ID_PPA'                    => 'id_ppa',
    'ACAO_ORCAM'                => 'acao_orcam',
    'S_INSTRUM'                 => 's_instrum',
    'S_EMPRESA'                 => 's_empresa',
    'ID_NOTION'                 => 'id_notion',
    'NPO'                       => 'npo',
    'Shape_Leng'                => 'shape_leng',
    'O_EDITAL'                  => 'o_edital',
    'S_EDITAL'                  => 's_edital',
    'O_PROC_LIC'                => 'o_proc_lic',
    'S_PROC_LIC'                => 's_proc_lic',
    'ID_SUP'                    => 'id_sup',
    'O_TERMINO'                 => 'o_termino',
    'DEZ_2024'                  => 'dez_2024',
    'TRECHO'                    => 'trecho',
    'O_INICIO'                  => 'o_inicio',
    'O_PI_A_R'                  => 'o_pi_a_r',
    'O_EXECUTAD'                => 'o_executad',
    'VALOR_DNIT'                => 'valor_dnit',
    'VALOR_CONT'                => 'valor_cont',
    'OD_VALOR_T'                => 'od_valor_t',
    'auxiliar'                  => 'auxiliar',
    'CS_CAD'                    => 'cs_cad',
    'CS_EST'                    => 'cs_est',
    'CS_EST_OK'                 => 'cs_est_ok',
    'FEV_2025'                  => 'fev_2025',
    'RP'                        => 'rp',
    'MAIO_2025'                 => 'maio_2025',
    'JUN_2025'                  => 'jun_2025',
    'JUL_2025'                  => 'jul_2025',
    'AGO_2025'                  => 'ago_2025',
    'SET_2025'                  => 'set_2025',
    'RDCi'                      => 'rdci',
    'Em_Projeto'                => 'em_projeto',
    'OUT_2025'                  => 'out_2025',
    'DEZ_2025'                  => 'dez_2025',
    'JAN_26'                    => 'jan_26',
    'QFID'                      => 'qfid',
    'FEV_2026'                  => 'fev_2026',
    'MARÇO_26'                  => 'marco_26',
    'abril_2026'                => 'abril_2026',
    'SITUAÇÃO DA OBRA NO MÊS'   => 'situacao_obra_mes',
];

$camposData = ['o_termino', 'o_inicio'];

$camposDecimal = [
    'o_km_inici', 'o_km_final', 'o_extensao', 'shape_leng',
    'o_pi_a_r', 'o_executad', 'valor_dnit', 'valor_cont', 'od_valor_t'
];

try {
    echo "🔄 Conectando à API (Dataset 16 - Base Controle GEO)...\n";
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
$sqlInsert = "INSERT INTO supra_dataset_16_base_controle_geo (" . implode(',', $campos) . ")
              VALUES (" . implode(',', array_fill(0, count($campos), '?')) . ")";
$stmtInsert = $pdo->prepare($sqlInsert);

$pdo->beginTransaction();
try {
    $pdo->exec("DELETE FROM supra_dataset_16_base_controle_geo");

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

echo "\n✅ Sincronização do Dataset 16 finalizada!\n";