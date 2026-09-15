<?php
require_once 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['error' => 'Não autenticado']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

if ($action === 'detalhes' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM contratos_rdci WHERE id = ?");
    $stmt->execute([$id]);
    $contrato = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$contrato) {
        echo json_encode(['error' => 'Contrato não encontrado']);
        exit;
    }
    echo json_encode($contrato);
    exit;
}

echo json_encode(['error' => 'Ação inválida']);