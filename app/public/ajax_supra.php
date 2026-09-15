<?php
require_once 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['error' => 'Não autenticado']);
    exit;
}

$action = $_REQUEST['action'] ?? '';
$usuario_id = $_SESSION['usuario_id'];

// --- Alternar notificação ---
if ($action === 'toggle_notificacao' && isset($_POST['id']) && isset($_POST['tipo'])) {
    $id = (int)$_POST['id'];
    $tipo = $_POST['tipo'];
    if (!in_array($tipo, ['vencido', 'proximo_vencer'])) {
        echo json_encode(['error' => 'Tipo inválido']);
        exit;
    }

    // Verifica se já existe notificação para este contrato + tipo
    $stmt = $pdo->prepare("SELECT id FROM supra_notificacoes WHERE contrato_id = ? AND tipo = ?");
    $stmt->execute([$id, $tipo]);
    $existente = $stmt->fetch();

    if ($existente) {
        // Remove
        $stmt = $pdo->prepare("DELETE FROM supra_notificacoes WHERE id = ?");
        $stmt->execute([$existente['id']]);
        echo json_encode(['success' => true, 'action' => 'removido']);
    } else {
        // Adiciona
        $stmt = $pdo->prepare("INSERT INTO supra_notificacoes (contrato_id, usuario_id, tipo) VALUES (?, ?, ?)");
        $stmt->execute([$id, $usuario_id, $tipo]);
        echo json_encode(['success' => true, 'action' => 'adicionado']);
    }
    exit;
}

// --- Buscar detalhes de um contrato ---
if ($action === 'detalhes' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM supra_contratos WHERE id = ?");
    $stmt->execute([$id]);
    $contrato = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$contrato) {
        echo json_encode(['error' => 'Contrato não encontrado']);
        exit;
    }
    // Formata datas
    $camposData = ['data_ordem_inicio_projeto', 'data_ordem_inicio_obra', 'data_termino_servico', 
                   'data_termino_vigencia', 'data_termino_projeto_edital', 'data_termino_projeto_cronog',
                   'data_atualizacao'];
    foreach ($camposData as $campo) {
        if ($contrato[$campo]) {
            $contrato[$campo] = date('d/m/Y', strtotime($contrato[$campo]));
        }
    }
    echo json_encode($contrato);
    exit;
}

echo json_encode(['error' => 'Ação inválida']);