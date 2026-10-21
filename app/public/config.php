<?php
// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configurações do banco de dados
$host = 'localhost';
$dbname = 'controle_processos';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro na conexão: " . $e->getMessage());
}

// Atualizar última atividade do usuário (depois que $pdo existe)
if (isset($_SESSION['usuario_id'])) {
    $stmt = $pdo->prepare("UPDATE usuarios SET last_activity = NOW() WHERE id = ?");
    $stmt->execute([$_SESSION['usuario_id']]);
}
?>