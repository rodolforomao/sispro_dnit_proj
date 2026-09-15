<?php
require_once 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['error' => 'Não autenticado']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

// ============================================================
// DETALHES DO CONTRATO
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
// TOGGLE NOTIFICADO (legado - mantido para compatibilidade)
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
// TOGGLE ISENTO (legado - mantido para compatibilidade)
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
// ALTERAR STATUS DE NOTIFICAÇÃO (4 AÇÕES) — CORRIGIDO
// ============================================================
if ($action === 'alterar_status_notificacao' && isset($_POST['id'])) {
    $id         = (int)$_POST['id'];
    $notificado = isset($_POST['notificado']) ? (int)$_POST['notificado'] : 0;
    $isento     = isset($_POST['isento'])     ? (int)$_POST['isento']     : 0;
    $statusAcao = trim($_POST['status_acao'] ?? '');

    // Valida a ação
    $acoesPermitidas = ['notificar', 'notificado', 'isento', 'nao_notificar'];
    if (!in_array($statusAcao, $acoesPermitidas, true)) {
        echo json_encode(['success' => false, 'error' => 'Ação inválida: ' . $statusAcao]);
        exit;
    }

    // Normaliza coerência entre os campos
    if ($isento === 1) {
        $notificado = 0;
        $statusAcao = 'isento';
    } elseif ($notificado === 1) {
        $isento     = 0;
        $statusAcao = 'notificado';
    }

    // Se marcou como "notificado", atualiza a data da última notificação
    $dataUltimaNotif = null;
    if ($statusAcao === 'notificado') {
        $dataUltimaNotif = date('Y-m-d');
    }

    // Verifica o contrato e a data atual de notificação existente
    $stmtCheck = $pdo->prepare("SELECT id, data_ultima_notificacao FROM contratos_rdci WHERE id = ?");
    $stmtCheck->execute([$id]);
    $contratoAtual = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$contratoAtual) {
        echo json_encode(['success' => false, 'error' => 'Contrato não encontrado']);
        exit;
    }

    // Preserva a data anterior se não for "notificado"
    if ($dataUltimaNotif === null && !empty($contratoAtual['data_ultima_notificacao'])) {
        $dataUltimaNotif = $contratoAtual['data_ultima_notificacao'];
    }

    // Se estava em "notificado" e o usuário mudou para outra ação, limpa a data
    if ($statusAcao !== 'notificado' && !empty($contratoAtual['data_ultima_notificacao'])) {
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
echo json_encode(['error' => 'Ação inválida']);
exit;