<?php
// ============================================================
// API de exportação da tabela `processos`
// ⚠️ Colocar na raiz pública do sistema em PRODUÇÃO
// ============================================================
require_once __DIR__ . '/app/public/config.php';

header('Content-Type: application/json; charset=utf-8');

// ============================================================
// 🔑 TOKEN
// ============================================================
define('SYNC_TOKEN', 'kzseb1XXFRO1CWL5FAPQ3mJi5KBI5viG5OsPWwv2xTI');

$token_recebido = $_SERVER['HTTP_X_SYNC_TOKEN'] ?? ($_GET['token'] ?? '');

if (!hash_equals(SYNC_TOKEN, $token_recebido)) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

try {

    // ============================================================
    // 🔧 DIAGNÓSTICO OPCIONAL (?diag=1)
    // Útil para confirmar em qual tabela os contrato_id apontam
    // ============================================================
    if (!empty($_GET['diag'])) {
        $total          = (int)$pdo->query("SELECT COUNT(*) FROM processos")->fetchColumn();
        $comId          = (int)$pdo->query("SELECT COUNT(*) FROM processos WHERE contrato_id IS NOT NULL")->fetchColumn();
        $batemRdci      = (int)$pdo->query("
            SELECT COUNT(*) FROM processos p
            INNER JOIN contratos_rdci r ON p.contrato_id = r.id
        ")->fetchColumn();
        $batemContratos = (int)$pdo->query("
            SELECT COUNT(*) FROM processos p
            INNER JOIN contratos c ON p.contrato_id = c.id
        ")->fetchColumn();

        // Descobre a FK declarada (se existir)
        $fk = $pdo->query("
            SELECT REFERENCED_TABLE_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'processos'
              AND COLUMN_NAME = 'contrato_id'
              AND REFERENCED_TABLE_NAME IS NOT NULL
        ")->fetchColumn();

        echo json_encode([
            'success'                   => true,
            'diag'                      => true,
            'processos_total'           => $total,
            'com_contrato_id'           => $comId,
            'sem_contrato_id'           => $total - $comId,
            'ids_batem_contratos_rdci'  => $batemRdci,
            'ids_batem_contratos'       => $batemContratos,
            'fk_declarada'              => $fk ?: '(sem FK declarada)',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ============================================================
    // EXPORTAÇÃO PRINCIPAL
    //
    // O JOIN foi ajustado para cobrir as DUAS tabelas possíveis,
    // porque em produção o `processos.contrato_id` pode apontar
    // tanto para `contratos` (FK declarada) quanto para
    // `contratos_rdci`. Usamos COALESCE para devolver o
    // instrumento/número de qualquer uma delas, e um campo extra
    // `contrato_origem_tabela` para sabermos de onde veio.
    // ============================================================
    $sql = "SELECT
                p.id                              AS origem_id,
                p.numero_processo,
                p.assunto,
                p.data_entrada,
                p.prazo,
                p.data_revisao,
                p.data_assinatura,
                p.sei_recebido,
                p.sei_criado_1,
                p.sei_criado_2,
                p.sei_criado_3,
                p.tem_prazo,
                p.cadastrado_sima,
                p.providencia,
                p.observacoes,
                p.uf,
                p.br,
                p.created_at,

                -- ⬇️ Campos de contrato (novos / ajustados)
                p.contrato_id                     AS contrato_id_origem,
                COALESCE(rdci.instrumento, ctr.numero) AS contrato_instrumento,
                CASE
                    WHEN rdci.id IS NOT NULL THEN 'rdci'
                    WHEN ctr.id  IS NOT NULL THEN 'contratos'
                    ELSE NULL
                END                               AS contrato_origem_tabela,

                -- ⬇️ Relacionamentos auxiliares
                eq.nome                           AS equipe_nome,
                resp.nome                         AS responsavel_nome,
                st.nome                           AS status_nome,
                tp.nome                           AS tipo_nome

            FROM processos p

            LEFT JOIN equipes eq            ON p.equipe_id      = eq.id
            LEFT JOIN responsaveis resp     ON p.responsavel_id = resp.id
            LEFT JOIN status_processo st    ON p.status_id      = st.id
            LEFT JOIN tipos tp              ON p.tipo_id        = tp.id

            -- ✅ Agora testamos as duas tabelas de contrato
            LEFT JOIN contratos_rdci rdci   ON p.contrato_id    = rdci.id
            LEFT JOIN contratos ctr         ON p.contrato_id    = ctr.id

            ORDER BY p.id ASC";

    $stmt      = $pdo->query($sql);
    $processos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Estatísticas rápidas (ajudam no dev)
    $comInstrumento = 0;
    $porTabela      = ['rdci' => 0, 'contratos' => 0];
    foreach ($processos as $p) {
        if (!empty($p['contrato_instrumento'])) {
            $comInstrumento++;
            $tb = $p['contrato_origem_tabela'] ?? null;
            if ($tb !== null && isset($porTabela[$tb])) {
                $porTabela[$tb]++;
            }
        }
    }

    echo json_encode([
        'success'   => true,
        'total'     => count($processos),
        'gerado_em' => date('Y-m-d H:i:s'),
        'stats'     => [
            'com_contrato_instrumento' => $comInstrumento,
            'sem_contrato_instrumento' => count($processos) - $comInstrumento,
            'por_tabela'               => $porTabela,
        ],
        'processos' => $processos,
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro: ' . $e->getMessage()]);
}