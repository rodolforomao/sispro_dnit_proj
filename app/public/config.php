<?php
// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Defaults (dev local). Em produção, use config.local.php no servidor.
$host = 'localhost';
$dbname = 'controle_processos';
$user = 'root';
$pass = '';

$localConfig = __DIR__ . '/config.local.php';
if (is_readable($localConfig)) {
    $local = require $localConfig;
    if (is_array($local)) {
        if (isset($local['host'])) {
            $host = $local['host'];
        }
        if (isset($local['dbname'])) {
            $dbname = $local['dbname'];
        }
        if (isset($local['user'])) {
            $user = $local['user'];
        }
        if (isset($local['pass'])) {
            $pass = $local['pass'];
        }
    }
}

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
