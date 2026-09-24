<?php
require_once 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['error' => 'Não autenticado']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

// ============================================================
// DETALHES DO CONTRATO (modal notificações)
// ============================================================
if ($action === 'detalhes' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM contratos_rdci WHERE id = ?");
    $stmt->execute([$id]);
    $contrato = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$contrato) {
        echo json_encode(['error' => 'Contrato não encontrado']);
        exit;
    }
    $contrato['processo_base']    = $contrato['processo_base']    ?? '';
    $contrato['processo_projeto'] = $contrato['processo_projeto'] ?? '';
    echo json_encode($contrato);
    exit;
}

// ============================================================
// DETALHES SUPRA (modal atualização de contratos)
// ============================================================
if ($action === 'detalhes_supra' && isset($_GET['id'])) {
    header('Content-Type: application/json; charset=utf-8');
    $id = (int)$_GET['id'];

    try {
        $stmt = $pdo->prepare("SELECT instrumento FROM contratos_rdci WHERE id = ?");
        $stmt->execute([$id]);
        $instrumento = $stmt->fetchColumn();

        if (!$instrumento) {
            echo json_encode(['error' => 'Contrato não encontrado']);
            exit;
        }

        // Resumos (dataset 40) — usa SELECT * para ser resiliente a nomes de coluna
        $resumo = [];
        try {
            $stmtR = $pdo->prepare("SELECT * FROM supra_dataset_40_resumos WHERE instrumento = ? LIMIT 1");
            $stmtR->execute([$instrumento]);
            $resumo = $stmtR->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            $resumo = ['_erro' => 'Tabela supra_dataset_40_resumos: ' . $e->getMessage()];
        }

        // Meio Ambiente (dataset 39)
        $meio = [];
        try {
            $stmtM = $pdo->prepare("SELECT * FROM supra_dataset_39_meio WHERE instrumento = ? LIMIT 1");
            $stmtM->execute([$instrumento]);
            $meio = $stmtM->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            $meio = ['_erro' => 'Tabela supra_dataset_39_meio: ' . $e->getMessage()];
        }

        echo json_encode([
            'instrumento' => $instrumento,
            'resumo'      => $resumo,
            'meio'        => $meio,
        ]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Erro: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================================
// TOGGLE NOTIFICADO (legado)
// ============================================================
if ($action === 'toggle_notificado' && isset($_POST['id']) && isset($_POST['status'])) {
    $id = (int)$_POST['id'];
    $status = (int)$_POST['status'];
    $stmt = $pdo->prepare("UPDATE contratos_rdci SET notificado = ? WHERE id = ?");
    $stmt->execute([$status, $id]);
    echo json_encode(['success' => true]);
    exit;
}

// ============================================================
// TOGGLE ISENTO (legado)
// ============================================================
if ($action === 'toggle_isento' && isset($_POST['id']) && isset($_POST['status'])) {
    $id = (int)$_POST['id'];
    $status = (int)$_POST['status'];
    $stmt = $pdo->prepare("UPDATE contratos_rdci SET isento_notificacao = ? WHERE id = ?");
    $stmt->execute([$status, $id]);
    echo json_encode(['success' => true]);
    exit;
}

// ============================================================
// ALTERAR STATUS DE NOTIFICAÇÃO (5 AÇÕES)
// ============================================================
if ($action === 'alterar_status_notificacao' && isset($_POST['id'])) {
    $id         = (int)$_POST['id'];
    $notificado = isset($_POST['notificado']) ? (int)$_POST['notificado'] : 0;
    $isento     = isset($_POST['isento'])     ? (int)$_POST['isento']     : 0;
    $statusAcao = trim($_POST['status_acao'] ?? '');

    $acoesPermitidas = ['notificar', 'notificado', 'aguardando_assinatura', 'isento', 'nao_notificar'];
    if (!in_array($statusAcao, $acoesPermitidas, true)) {
        echo json_encode(['success' => false, 'error' => 'Ação inválida: ' . $statusAcao]);
        exit;
    }

    if ($isento === 1) {
        $notificado = 0;
        $statusAcao = 'isento';
    } elseif ($statusAcao === 'notificado') {
        $isento     = 0;
        $notificado = 1;
    } elseif ($statusAcao === 'aguardando_assinatura') {
        $isento     = 0;
        $notificado = 1;
    } else {
        $isento     = 0;
        $notificado = 0;
    }

    $dataUltimaNotif = null;
    if (in_array($statusAcao, ['notificado', 'aguardando_assinatura'], true)) {
        $dataUltimaNotif = date('Y-m-d');
    }

    $stmtCheck = $pdo->prepare("SELECT id, data_ultima_notificacao FROM contratos_rdci WHERE id = ?");
    $stmtCheck->execute([$id]);
    $contratoAtual = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$contratoAtual) {
        echo json_encode(['success' => false, 'error' => 'Contrato não encontrado']);
        exit;
    }

    if ($dataUltimaNotif === null && !empty($contratoAtual['data_ultima_notificacao'])) {
        $dataUltimaNotif = $contratoAtual['data_ultima_notificacao'];
    }
    if (in_array($statusAcao, ['notificar', 'isento', 'nao_notificar'], true)) {
        $dataUltimaNotif = null;
    }

    $sql = "UPDATE contratos_rdci 
            SET notificado              = :notificado,
                isento_notificacao      = :isento,
                status_acao             = :status_acao,
                data_ultima_notificacao = :data_ultima
            WHERE id = :id";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':notificado',  $notificado,     PDO::PARAM_INT);
    $stmt->bindValue(':isento',      $isento,         PDO::PARAM_INT);
    $stmt->bindValue(':status_acao', $statusAcao,     PDO::PARAM_STR);
    $stmt->bindValue(':data_ultima', $dataUltimaNotif);
    $stmt->bindValue(':id',          $id,             PDO::PARAM_INT);

    if ($stmt->execute()) {
        echo json_encode([
            'success'                 => true,
            'id'                      => $id,
            'notificado'              => $notificado,
            'isento'                  => $isento,
            'status_acao'             => $statusAcao,
            'data_ultima_notificacao' => $dataUltimaNotif,
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Falha ao atualizar']);
    }
    exit;
}

// ============================================================
// SALVAR OBSERVAÇÃO
// ============================================================
if ($action === 'salvar_observacao' && isset($_POST['id']) && isset($_POST['observacao'])) {
    $id = (int)$_POST['id'];
    $observacao = trim($_POST['observacao']);
    $stmt = $pdo->prepare("UPDATE contratos_rdci SET observacao_notificacao = ? WHERE id = ?");
    $stmt->execute([$observacao, $id]);
    echo json_encode(['success' => true]);
    exit;
}

// ============================================================
// AÇÃO INVÁLIDA
// ============================================================
echo json_encode(['error' => 'Ação inválida: ' . $action]);
exit;