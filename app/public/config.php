<?php
// app/public/config.php
//
// Carrega config.local.php se existir (produção tem o dela; XAMPP usa
// os defaults abaixo). Item do checklist da produção.
//
// Produção: host 172.17.0.1 + user sispro
// XAMPP:    host localhost  + user root sem senha
//
// PHP 7.0-safe.

// Inicia sessão se não estiver ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ─────────────────────────────────────────────────────────────
// Defaults (DEV — XAMPP). Em produção, config.local.php sobrescreve.
// ─────────────────────────────────────────────────────────────
$host   = 'localhost';
$dbname = 'controle_processos';
$user   = 'root';
$pass   = '';
$port   = 3306;

// ─────────────────────────────────────────────────────────────
// Carrega config.local.php (produção) — NÃO commitar este arquivo.
// Deve definir $host, $dbname, $user, $pass (e opcionalmente $port).
// ─────────────────────────────────────────────────────────────
$configLocal = __DIR__ . '/config.local.php';
if (is_file($configLocal)) {
    require_once $configLocal;
}

// ─────────────────────────────────────────────────────────────
// Conexão PDO — charset utf8 (NÃO utf8mb4 em produção MySQL 5.5)
// ─────────────────────────────────────────────────────────────
try {
    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Não expor detalhes em produção — mensagem genérica + log
    error_log('[config.php] Falha na conexão PDO: ' . $e->getMessage());
    die("Erro na conexão com o banco de dados. Contate o administrador.");
}

// ─────────────────────────────────────────────────────────────
// Atualiza última atividade do usuário logado
// ─────────────────────────────────────────────────────────────
if (isset($_SESSION['usuario_id'])) {
    try {
        $stmt = $pdo->prepare("UPDATE usuarios SET last_activity = NOW() WHERE id = ?");
        $stmt->execute([$_SESSION['usuario_id']]);
    } catch (Exception $e) {
        // Silencioso — last_activity não é crítico
        error_log('[config.php] Falha ao atualizar last_activity: ' . $e->getMessage());
    }
}