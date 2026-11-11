<?php
// public/importar_supra_dataset_47_financeiro_orcamentario_atlas.php

require_once 'config.php';

// ============================================================
// CONFIGURAÇÃO
// ============================================================
const DATASET_ID = 47;
const TABELA     = 'supra_dataset_47_financeiro_orcamentario_atlas';
const ANO_REF    = 2026;   // exercício embutido nos nomes das colunas

// Detecta AJAX (mesmo padrão do importador 36)
$isAjax = (!empty($_GET['ajax']))
       || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    ob_start();
}

// ============================================================
// URL / TOKEN
// ============================================================
$url = "https://supra.dnit.gov.br/index_cgcont_common.php/cgcont/cib/exportBi/" . DATASET_ID;

// ATENÇÃO: o token expira. Se começar a retornar 401, troque aqui.
$token = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyIjoic3VwcmFfY2liX2FwaUBkbml0Lmdvdi5iciIsInBhc3MiOiJTdXByYUBDaWIxMjM0NUAifQ.GyejoRFofL3mMnN7jD64RBrr2JSB9hVpR_BWrfAtd3U";

// ============================================================
// FUNÇÕES AUXILIARES
// ============================================================

function consumirApi(string $url, string $token): string
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['token: ' . $token, 'Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 180,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $resposta = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erroCurl = curl_error($ch);
    curl_close($ch);

    if ($resposta === false) throw new Exception("cURL falhou: $erroCurl");
    if ($httpCode !== 200)   throw new Exception("HTTP $httpCode — verifique token/URL");
    return $resposta;
}

function tratarArray($valor)
{
    if (is_array($valor)) {
        $filtrado = array_filter($valor, fn($v) => $v !== '' && $v !== null);
        return $filtrado ? implode(' | ', $filtrado) : null;
    }
    return $valor;
}

function converterData($valor): ?string
{
    if ($valor === null || $valor === '') return null;
    $valor = trim((string)$valor);
    if ($valor === '' || strtoupper($valor) === 'N/A') return null;

    if (preg_match('/^\d{4}-\d{2}-\d{2}/', $valor)) return substr($valor, 0, 10);
    if (preg_match('#^\d{2}/\d{2}/\d{4}#', $valor)) {
        [$d, $m, $a] = explode('/', substr($valor, 0, 10));
        return "$a-$m-$d";
    }
    if (preg_match('/^\d{4,5}$/', $valor)) {
        return gmdate('Y-m-d', ((int)$valor - 25569) * 86400);
    }
    return null;
}

function converterValor($valor): ?float
{
    if ($valor === null) return null;
    if (is_int($valor) || is_float($valor)) return (float)$valor;
    if (is_numeric($valor)) return (float)$valor;

    $s = trim((string)$valor);
    if ($s === '' || strtoupper($s) === 'N/A') return null;

    // Remove R$, espaços e NBSP
    $s = preg_replace('/[R$\s\x{00A0}]/u', '', $s);

    // Formato BR: 1.234.567,89
    if (preg_match('/^\d{1,3}(\.\d{3})*(,\d+)?$/', $s)) {
        return (float) str_replace(',', '.', str_replace('.', '', $s));
    }
    // Formato US: 1,234,567.89
    if (preg_match('/^\d{1,3}(,\d{3})*(\.\d+)?$/', $s)) {
        return (float) str_replace(',', '', $s);
    }
    // Última tentativa
    $s = str_replace(',', '.', $s);
    return is_numeric($s) ? (float)$s : null;
}

/** Converte inteiro "0" / "8" / 4 para ?int */
function converterInt($valor): ?int
{
    if ($valor === null || $valor === '') return null;
    if (is_int($valor)) return $valor;
    if (is_numeric($valor)) return (int)$valor;
    return null;
}

/** JSON array para a coluna `sugestao_valor_previsto_ano` */
function converterJson($valor): ?string
{
    if ($valor === null) return null;
    if (is_array($valor)) {
        $filtrado = array_values(array_filter($valor, fn($v) => $v !== null && $v !== ''));
        return $filtrado ? json_encode($filtrado, JSON_UNESCAPED_UNICODE) : null;
    }
    if (is_string($valor) && $valor !== '') {
        $dec = json_decode($valor, true);
        if (is_array($dec)) {
            $filtrado = array_values(array_filter($dec, fn($v) => $v !== null && $v !== ''));
            return $filtrado ? json_encode($filtrado, JSON_UNESCAPED_UNICODE) : null;
        }
    }
    return null;
}

// ============================================================
// MAPEAMENTO: chave da API → coluna no banco
// ============================================================
// Nota: as chaves com ano (`/2026`) precisam acompanhar o $ANO_REF.
// Quando rolar o ano, edite os 24 nomes abaixo (12 liquidado + 12 planejado)
// ou re-genere este bloco. Os nomes de coluna em si continuam fixos — a
// tabela precisaria ser recriada se o ano mudar.

$mapa = [
    // Identificação
    'Contrato'                                      => 'contrato',
    'Estado'                                        => 'estado',
    'BR'                                            => 'br',
    'Tipo (Atlas)'                                  => 'tipo_atlas',
    'AÇÃO'                                          => 'acao',
    'Empreendimento'                                => 'empreendimento',
    'Tipo de contratação'                           => 'tipo_contratacao',
    'Incluir na lista/ficha?'                       => 'incluir_na_lista',
    'Status SIAC'                                   => 'status_siac',

    // Datas
    'Término da Vigência'                           => 'termino_vigencia',
    'Data Base'                                     => 'data_base',
    'Data de referência da última medição'          => 'data_ref_ultima_medicao',

    // Contratuais
    'Valor a PI (DNIT)'                             => 'valor_pi_dnit',
    'Valor Total de Aditivo'                        => 'valor_total_aditivo',
    'Reajuste'                                      => 'reajuste',
    'PI+A+R'                                        => 'pi_a_r',
    'Total Empenhado desde o início do contrato'    => 'total_empenhado_ini',
    'Empenhado no ano'                              => 'empenhado_no_ano',
    'Empenhado no ano (por Ação)'                   => 'empenhado_no_ano_acao',
    'Saldo Empenho+RAP(por Ação)'                   => 'saldo_empenho_rap_acao',
    'Saldo RAP(por Ação)'                           => 'saldo_rap_acao',
    'Saldo Empenho(por Ação)'                       => 'saldo_empenho_acao',

    // Medições
    'Última medição'                                => 'ultima_medicao',
    'Média das últ. 3 medições'                     => 'media_ult_3_medicoes',
    'Total Medido'                                  => 'total_medido',
    'Medido no ano'                                 => 'medido_no_ano',

    // Liquidado (ano)
    'Total Liquidado'                               => 'total_liquidado',
    'Liquidado no ano'                              => 'liquidado_no_ano',
    'Liquidado janeiro/2026'                        => 'liquidado_janeiro_2026',
    'Liquidado fevereiro/2026'                      => 'liquidado_fevereiro_2026',
    'Liquidado março/2026'                          => 'liquidado_marco_2026',
    'Liquidado abril/2026'                          => 'liquidado_abril_2026',
    'Liquidado maio/2026'                           => 'liquidado_maio_2026',
    'Liquidado junho/2026'                          => 'liquidado_junho_2026',
    'Liquidado julho/2026'                          => 'liquidado_julho_2026',
    'Liquidado agosto/2026'                         => 'liquidado_agosto_2026',
    'Liquidado setembro/2026'                       => 'liquidado_setembro_2026',
    'Liquidado outubro/2026'                        => 'liquidado_outubro_2026',
    'Liquidado novembro/2026'                       => 'liquidado_novembro_2026',
    'Liquidado dezembro/2026'                       => 'liquidado_dezembro_2026',

    // RAP + Planejado NPO (ano)
    'RAP inscrito(por ação NPO)'                    => 'rap_inscrito_npo',
    'Planejado NPO janeiro/2026'                    => 'planejado_npo_janeiro_2026',
    'Planejado NPO fevereiro/2026'                  => 'planejado_npo_fevereiro_2026',
    'Planejado NPO março/2026'                      => 'planejado_npo_marco_2026',
    'Planejado NPO abril/2026'                      => 'planejado_npo_abril_2026',
    'Planejado NPO maio/2026'                       => 'planejado_npo_maio_2026',
    'Planejado NPO junho/2026'                      => 'planejado_npo_junho_2026',
    'Planejado NPO julho/2026'                      => 'planejado_npo_julho_2026',
    'Planejado NPO agosto/2026'                     => 'planejado_npo_agosto_2026',
    'Planejado NPO setembro/2026'                   => 'planejado_npo_setembro_2026',
    'Planejado NPO outubro/2026'                    => 'planejado_npo_outubro_2026',
    'Planejado NPO novembro/2026'                   => 'planejado_npo_novembro_2026',
    'Planejado NPO dezembro/2026'                   => 'planejado_npo_dezembro_2026',

    // Resumos
    'A Empenhar até o fim do contrato'              => 'a_empenhar_ate_fim_contrato',
    'Total medições atrasadas'                      => 'total_medicoes_atrasadas',
    'Somatório do Liquidado na Ação'                => 'somatorio_liquidado_acao',
    'Total planejado na NPO para o ano na ação'     => 'total_planejado_npo_ano_acao',
    'Diferença entre o planejado e o liquidado na Ação' => 'diferenca_planejado_liquidado_acao',
    'A medir até o fim do contrato'                 => 'a_medir_ate_fim_contrato',
    'A liquidar até o fim do contrato'              => 'a_liquidar_ate_fim_contrato',

    // Reajustes / último estado
    'Número de reajustes'                           => 'numero_reajustes',
    'Prev Reajust'                                  => 'prev_reajust',
    'último mês medido'                             => 'ultimo_mes_medido',
    'Último mês liquidado'                          => 'ultimo_mes_liquidado',
    'Soma med atrasadas a pagar neste ano'          => 'soma_med_atrasadas_pagar_ano',

    // Estrutura
    'Sugestão de valor previsto para conclusão do ano' => 'sugestao_valor_previsto_ano',
];

// Grupos de conversão
$camposData    = ['termino_vigencia', 'data_base', 'data_ref_ultima_medicao'];
$camposInt     = ['ultima_medicao', 'total_medicoes_atrasadas', 'ultimo_mes_medido', 'ultimo_mes_liquidado'];
$camposJson    = ['sugestao_valor_previsto_ano'];
$camposTexto   = ['contrato', 'estado', 'br', 'tipo_atlas', 'acao', 'empreendimento',
                  'tipo_contratacao', 'incluir_na_lista', 'status_siac'];

// ============================================================
// EXECUÇÃO — 1) consumir a API
// ============================================================
try {
    if (!$isAjax) echo "🔄 Conectando à API (Dataset " . DATASET_ID . ")...\n";
    $resposta = consumirApi($url, $token);

    $dados = json_decode($resposta, true);
    if (!is_array($dados) || empty($dados)) {
        throw new Exception("Resposta vazia ou inválida da API.");
    }
    if (!$isAjax) echo "✅ Registros recebidos: " . count($dados) . "\n";

} catch (Exception $e) {
    if ($isAjax) {
        if (ob_get_level()) ob_end_clean();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
    die("❌ Erro ao consumir API: " . $e->getMessage() . "\n");
}

// ============================================================
// EXECUÇÃO — 2) gravar em lote
// ============================================================
$colunas      = array_values($mapa);
$placeholders = implode(',', array_fill(0, count($colunas), '?'));
$sqlInsert    = 'INSERT INTO `' . TABELA . '` (`' . implode('`,`', $colunas) . '`) VALUES (' . $placeholders . ')';

try {
    $pdo->beginTransaction();

    // Estratégia "full refresh": apaga tudo e reinsere (padrão do importador 36)
    $pdo->exec('DELETE FROM `' . TABELA . '`');
    $stmtInsert = $pdo->prepare($sqlInsert);

    $inseridos = 0;
    $erros     = [];

    foreach ($dados as $idx => $item) {
        $valores = [];
        foreach ($mapa as $apiKey => $dbCol) {
            $raw = $item[$apiKey] ?? null;

            // Conversões por tipo
            if (in_array($dbCol, $camposJson, true)) {
                $valores[] = converterJson($raw);
            } elseif (in_array($dbCol, $camposData, true)) {
                $valores[] = converterData($raw);
            } elseif (in_array($dbCol, $camposInt, true)) {
                $valores[] = converterInt($raw);
            } elseif (in_array($dbCol, $camposTexto, true)) {
                $txt = tratarArray($raw);
                $valores[] = ($txt === null || $txt === '') ? null : trim((string)$txt);
            } else {
                // Tudo o que sobra é valor monetário/decimal
                $valores[] = converterValor($raw);
            }
        }

        try {
            $stmtInsert->execute($valores);
            $inseridos++;
        } catch (PDOException $e) {
            // Se um registro falhar, não derruba o lote — registra e segue
            $erros[] = "Registro #$idx ({$item['Contrato']}): " . $e->getMessage();
            if (count($erros) >= 20) {
                throw new Exception("Muitos erros de importação. Abortando.\n" . implode("\n", $erros));
            }
        }
    }

    $pdo->commit();

    // Timestamp para o "Última sincronização" da view de notificações
    @file_put_contents(__DIR__ . '/.sync_ultima_execucao', date('Y-m-d H:i:s'));

    if ($isAjax) {
        if (ob_get_level()) ob_end_clean();
        echo json_encode([
            'success'   => true,
            'inseridos' => $inseridos,
            'erros'     => count($erros),
            'mensagem'  => $erros ? "Concluído com " . count($erros) . " falha(s)." : 'Concluído.',
            'atualizado_em' => date('d/m/Y H:i'),
        ]);
        exit;
    }

    echo "\n✅ Importação concluída!\n";
    echo "Registros inseridos: $inseridos\n";
    if ($erros) {
        echo "⚠ " . count($erros) . " falha(s):\n";
        foreach (array_slice($erros, 0, 10) as $e) echo "  - $e\n";
    }
    echo "\n✅ Dataset " . DATASET_ID . " — base `" . TABELA . "` atualizada.\n";

    if (!headers_sent()) {
        header('Location: analise_orc?sync=ok&inseridos=' . (int)$inseridos);
        exit;
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();

    if ($isAjax) {
        if (ob_get_level()) ob_end_clean();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
    die("❌ Erro na importação: " . $e->getMessage() . "\n");
}