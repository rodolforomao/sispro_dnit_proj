<?php
require_once 'config.php';

$id = $_GET['id'] ?? 0;
if ($id) {
    // Verifica se o registro existe antes de deletar
    $check = $pdo->prepare("SELECT id FROM processos WHERE id = ?");
    $check->execute([$id]);
    if ($check->fetch()) {
        $stmt = $pdo->prepare("DELETE FROM processos WHERE id = ?");
        $stmt->execute([$id]);
    }
}
header('Location: index.php');
exit;
?>