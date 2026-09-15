<?php
require_once 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['error' => 'Não autenticado']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

// --- GET: buscar um modelo para edição ---
if ($action === 'get' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM modelos WHERE id = ?");
    $stmt->execute([$id]);
    $modelo = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($modelo) {
        // Formatar a data para o input date (Y-m-d)
        if (!empty($modelo['data'])) {
            $modelo['data'] = date('Y-m-d', strtotime($modelo['data']));
        }
        echo json_encode($modelo);
    } else {
        echo json_encode(['error' => 'Modelo não encontrado']);
    }
    exit;
}

// --- POST: salvar (inserir ou atualizar) ---
if ($action === 'salvar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    $categoria = $_POST['categoria'] ?? '';
    $descricao = trim($_POST['descricao'] ?? '');
    $sei = trim($_POST['sei'] ?? '');
    $data = trim($_POST['data'] ?? '');

    if (empty($categoria) || empty($descricao)) {
        echo json_encode(['error' => 'Categoria e Descrição são obrigatórios']);
        exit;
    }

    if (empty($id)) {
        // Inserir
        $stmt = $pdo->prepare("INSERT INTO modelos (categoria, descricao, sei, data) VALUES (?, ?, ?, ?)");
        $stmt->execute([$categoria, $descricao, $sei ?: null, $data ?: null]);
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
    } else {
        // Atualizar
        $stmt = $pdo->prepare("UPDATE modelos SET categoria = ?, descricao = ?, sei = ?, data = ? WHERE id = ?");
        $stmt->execute([$categoria, $descricao, $sei ?: null, $data ?: null, $id]);
        echo json_encode(['success' => true]);
    }
    exit;
}

// --- POST: excluir ---
if ($action === 'excluir' && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    $stmt = $pdo->prepare("DELETE FROM modelos WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['error' => 'Ação inválida']);