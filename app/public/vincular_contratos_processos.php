<?php
// ============================================================
// Vincula processos aos contratos — 4 camadas com LIKE
// Camada 1: processo_base
// Camada 2: processo_projeto
// Camada 3: processo_notificacao_sr_uf
// Camada 4: n_sei_oficio_cobranca_cronograma
//
// ⚠️ processos.contrato_id aponta para `contratos` (FK
//    processos_ibfk_1), NÃO para `contratos_rdci`. Portanto:
//    - fazemos LIKE em contratos_rdci
//    - mas gravamos o id correspondente de `contratos`
//      casando por numero <-> instrumento
// ============================================================
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$action = $_POST['action'] ?? '';
$mensagem = '';
$tipoMensagem = '';
$diagnostico = null;
$vinculados = null;
$naoEncontrados = [];

// ============================================================
// CAMPOS A TESTAR (em contratos_rdci)
// ============================================================
$campos = [
    'processo_base'                    => 'Processo Base',
    'processo_projeto'                 => 'Processo de Projeto',
    'processo_notificacao_sr_uf'       => 'Processo de Notificação SR/UF',
    'n_sei_oficio_cobranca_cronograma' => 'SEI Ofício Cobrança',
];

// ============================================================
// DIAGNÓSTICO
// ============================================================
if ($action === 'diagnostico' || $action === 'vincular') {
    try {
        $total       = (int)$pdo->query("SELECT COUNT(*) FROM processos")->fetchColumn();
        $comContrato = (int)$pdo->query("SELECT COUNT(*) FROM processos WHERE contrato_id IS NOT NULL")->fetchColumn();
        $semContrato = $total - $comContrato;

        $matriz = [];
        foreach ($campos as $campo => $label) {
            $stmt = $pdo->prepare("
                SELECT COUNT(DISTINCT p.id)
                FROM processos p
                WHERE p.contrato_id IS NULL
                  AND EXISTS (
                      SELECT 1
                      FROM contratos_rdci r
                      INNER JOIN contratos c
                          ON UPPER(TRIM(c.numero)) = UPPER(TRIM(r.instrumento))
                      WHERE r.$campo LIKE CONCAT('%', p.numero_processo, '%')
                  )
            ");
            $stmt->execute();
            $matriz[$campo] = [
                'label' => $label,
                'qtd'   => (int)$stmt->fetchColumn(),
            ];
        }

        $diagnostico = [
            'total'       => $total,
            'comContrato' => $comContrato,
            'semContrato' => $semContrato,
            'matriz'      => $matriz,
        ];
    } catch (Exception $e) {
        $mensagem = "Erro no diagnóstico: " . $e->getMessage();
        $tipoMensagem = 'danger';
    }
}

// ============================================================
// VINCULAR (4 camadas, com LIKE e MIN(c.id) por processo)
// ============================================================
if ($action === 'vincular') {
    try {
        $pdo->beginTransaction();
        $resultados = [];

        foreach ($campos as $campo => $label) {
            // ✅ Grava id de `contratos`, casando numero <-> instrumento
            $sql = "UPDATE processos p
                    SET p.contrato_id = (
                        SELECT MIN(c.id)
                        FROM contratos c
                        INNER JOIN contratos_rdci r
                            ON UPPER(TRIM(r.instrumento)) = UPPER(TRIM(c.numero))
                        WHERE r.$campo LIKE CONCAT('%', p.numero_processo, '%')
                    )
                    WHERE p.contrato_id IS NULL
                      AND EXISTS (
                          SELECT 1
                          FROM contratos c2
                          INNER JOIN contratos_rdci r2
                              ON UPPER(TRIM(r2.instrumento)) = UPPER(TRIM(c2.numero))
                          WHERE r2.$campo LIKE CONCAT('%', p.numero_processo, '%')
                      )";

            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $resultados[$label] = $stmt->rowCount();
        }

        $pdo->commit();

        $totalVinculado = array_sum($resultados);
        $vinculados = [
            'camadas' => $resultados,
            'total'   => $totalVinculado,
        ];

        // Busca os que sobraram
        $stmt = $pdo->query("
            SELECT p.id, p.numero_processo, p.uf, p.br,
                   e.nome AS equipe_nome, s.nome AS status_nome
            FROM processos p
            LEFT JOIN equipes e ON p.equipe_id = e.id
            LEFT JOIN status_processo s ON p.status_id = s.id
            WHERE p.contrato_id IS NULL
            ORDER BY p.uf, p.numero_processo
        ");
        $naoEncontrados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $mensagem = "Vinculação concluída! $totalVinculado processos vinculados nesta rodada.";
        $tipoMensagem = 'success';

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $mensagem = "Erro na vinculação: " . $e->getMessage();
        $tipoMensagem = 'danger';
    }
}

// Estado atual
$total       = (int)$pdo->query("SELECT COUNT(*) FROM processos")->fetchColumn();
$comContrato = (int)$pdo->query("SELECT COUNT(*) FROM processos WHERE contrato_id IS NOT NULL")->fetchColumn();
$semContrato = $total - $comContrato;
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Vincular Contratos - DEV</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fc; padding: 40px 20px; }
        .container { max-width: 1100px; }
        .card { border: none; border-radius: 16px; box-shadow: 0 8px 30px rgba(0,0,0,0.08); padding: 30px; }
        .kpi {
            border: none; border-radius: 12px; padding: 16px 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
            border-left: 5px solid #4a90e2; background: #fff;
        }
        .kpi.azul    { border-left-color: #0d6efd; }
        .kpi.verde   { border-left-color: #198754; }
        .kpi.vermelho{ border-left-color: #dc3545; }
        .kpi-numero { font-size: 1.8rem; font-weight: 700; color: #2c3e50; line-height: 1.1; }
        .kpi-label  { font-size: 0.85rem; color: #6c757d; margin-top: 4px; }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <h3><i class="bi bi-link-45deg"></i> Vincular Contratos aos Processos</h3>
        <p class="text-muted">
            Tenta casar o <code>numero_processo</code> de cada processo com <strong>4 campos</strong> da tabela <code>contratos_rdci</code>
            usando <code>LIKE</code> (porque esses campos podem conter vários processos separados por quebra de linha).
            O id gravado é sempre de <code>contratos</code> (respeitando a FK <code>processos_ibfk_1</code>).
        </p>

        <?php if ($mensagem): ?>
            <div class="alert alert-<?= $tipoMensagem ?>"><?= htmlspecialchars($mensagem) ?></div>
        <?php endif; ?>

        <!-- KPIs -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="kpi azul">
                    <div class="kpi-numero"><?= $total ?></div>
                    <div class="kpi-label">Total de processos</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="kpi verde">
                    <div class="kpi-numero"><?= $comContrato ?></div>
                    <div class="kpi-label">Com contrato vinculado</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="kpi vermelho">
                    <div class="kpi-numero"><?= $semContrato ?></div>
                    <div class="kpi-label">Sem contrato</div>
                </div>
            </div>
        </div>

        <?php if ($diagnostico): ?>
            <h5>📊 Diagnóstico por campo (com LIKE)</h5>
            <p class="text-muted small">Quantos processos (dos <?= $diagnostico['semContrato'] ?> sem contrato) podem ser vinculados por cada campo:</p>
            <table class="table table-sm table-hover mb-4">
                <thead>
                    <tr>
                        <th>Campo da contratos_rdci</th>
                        <th class="text-end">Podem ser vinculados</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($diagnostico['matriz'] as $campo => $d): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($campo) ?></code></td>
                        <td class="text-end"><strong><?= $d['qtd'] ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if ($vinculados): ?>
            <h5>✅ Vinculados nesta rodada</h5>
            <table class="table table-sm mb-4">
                <?php foreach ($vinculados['camadas'] as $label => $qtd): ?>
                <tr>
                    <td>Por <strong><?= htmlspecialchars($label) ?></strong></td>
                    <td class="text-end"><strong><?= $qtd ?></strong></td>
                </tr>
                <?php endforeach; ?>
                <tr class="table-light">
                    <td><strong>Total</strong></td>
                    <td class="text-end"><strong><?= $vinculados['total'] ?></strong></td>
                </tr>
            </table>
        <?php endif; ?>

        <!-- Botões -->
        <div class="d-flex gap-2 mb-3">
            <form method="POST">
                <input type="hidden" name="action" value="diagnostico">
                <button type="submit" class="btn btn-outline-primary">
                    <i class="bi bi-search"></i> Diagnóstico
                </button>
            </form>

            <form method="POST" onsubmit="return confirm('Vincular automaticamente os processos aos contratos?')">
                <input type="hidden" name="action" value="vincular">
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-link-45deg"></i> Vincular automaticamente (4 camadas com LIKE)
                </button>
            </form>
        </div>

        <?php if (!empty($naoEncontrados)): ?>
            <hr>
            <h5>⚠️ Processos que ainda ficaram sem contrato (<?= count($naoEncontrados) ?>)</h5>
            <div class="table-responsive" style="max-height: 400px;">
                <table class="table table-sm table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nº Processo</th>
                            <th>UF</th>
                            <th>BR</th>
                            <th>Equipe</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($naoEncontrados as $p): ?>
                        <tr>
                            <td><?= (int)$p['id'] ?></td>
                            <td><?= htmlspecialchars($p['numero_processo'] ?? '') ?></td>
                            <td><?= htmlspecialchars($p['uf'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($p['br'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($p['equipe_nome'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($p['status_nome'] ?? '—') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>