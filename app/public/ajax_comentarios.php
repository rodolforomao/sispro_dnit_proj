<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Não autenticado']);
    exit;
}

$action = $_REQUEST['action'] ?? '';
$usuario_id = $_SESSION['usuario_id'];
$usuario_nivel = $_SESSION['usuario_nivel'] ?? 'usuario';

header('Content-Type: application/json');

try {
    // ============================================================
    // LISTAR COMENTÁRIOS
    // ============================================================
    if ($action === 'get') {
        $processo_id = isset($_GET['processo_id']) ? (int)$_GET['processo_id'] : null;
        $contrato_id = isset($_GET['contrato_id']) ? (int)$_GET['contrato_id'] : null;
        $count_only = isset($_GET['count_only']) && $_GET['count_only'] == 1;

        if ($processo_id) {
            $where = "processo_id = ?";
            $param = $processo_id;
        } elseif ($contrato_id) {
            $where = "contrato_id = ?";
            $param = $contrato_id;
        } else {
            echo json_encode([]);
            exit;
        }

        if ($count_only) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM comentarios WHERE $where");
            $stmt->execute([$param]);
            echo json_encode(['total' => (int)$stmt->fetchColumn()]);
            exit;
        }

        $stmt = $pdo->prepare("
            SELECT c.*, u.nome AS usuario_nome 
            FROM comentarios c 
            LEFT JOIN usuarios u ON c.usuario_id = u.id 
            WHERE $where 
            ORDER BY c.created_at ASC
        ");
        $stmt->execute([$param]);
        $comentarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($comentarios);
        exit;
    }

    // ============================================================
    // ADICIONAR COMENTÁRIO
    // ============================================================
    if ($action === 'adicionar') {
        $comentario = trim($_POST['comentario'] ?? '');
        if (empty($comentario)) {
            echo json_encode(['error' => 'Comentário vazio']);
            exit;
        }

        $processo_id = isset($_POST['processo_id']) && $_POST['processo_id'] !== '' ? (int)$_POST['processo_id'] : null;
        $contrato_id = isset($_POST['contrato_id']) && $_POST['contrato_id'] !== '' ? (int)$_POST['contrato_id'] : null;

        if (!$processo_id && !$contrato_id) {
            echo json_encode(['error' => 'ID da entidade não informado']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO comentarios (processo_id, contrato_id, usuario_id, comentario, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$processo_id, $contrato_id, $usuario_id, $comentario]);
        echo json_encode(['success' => true]);
        exit;
    }

    // ============================================================
    // EDITAR COMENTÁRIO
    // ============================================================
    if ($action === 'editar' && isset($_POST['id']) && isset($_POST['comentario'])) {
        $id = (int)$_POST['id'];
        $comentario = trim($_POST['comentario']);
        if (empty($comentario)) {
            echo json_encode(['error' => 'Comentário vazio']);
            exit;
        }
        // Verifica permissão (próprio usuário ou admin)
        $stmt = $pdo->prepare("SELECT usuario_id FROM comentarios WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row || ($row['usuario_id'] != $usuario_id && !in_array($usuario_nivel, ['admin', 'desenvolvedor']))) {
            echo json_encode(['error' => 'Sem permissão']);
            exit;
        }
        $stmt = $pdo->prepare("UPDATE comentarios SET comentario = ? WHERE id = ?");
        $stmt->execute([$comentario, $id]);
        echo json_encode(['success' => true]);
        exit;
    }

    // ============================================================
    // EXCLUIR COMENTÁRIO
    // ============================================================
    if ($action === 'excluir' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        // Verifica permissão (admin ou próprio)
        $stmt = $pdo->prepare("SELECT usuario_id FROM comentarios WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row || ($row['usuario_id'] != $usuario_id && !in_array($usuario_nivel, ['admin', 'desenvolvedor']))) {
            echo json_encode(['error' => 'Sem permissão']);
            exit;
        }
        $stmt = $pdo->prepare("DELETE FROM comentarios WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['error' => 'Ação inválida']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno: ' . $e->getMessage()]);
}