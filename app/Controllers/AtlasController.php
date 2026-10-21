<?php
// app/Controllers/AtlasController.php
// Controller dedicado ao setor Atlas/Monitoramento

class AtlasController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // ============================================================
    // HOME DO SETOR ATLAS
    // ============================================================
    public function index() {
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: login');
            exit;
        }

        // Variáveis esperadas pela view home_atlas.php
        $usuario_nome  = $_SESSION['usuario_nome']  ?? 'Usuário';
        $usuario_nivel = $_SESSION['usuario_nivel'] ?? 'usuario';
        $usuario_id    = $_SESSION['usuario_id']    ?? 0;
        $setor_slug    = $_SESSION['setor_slug']    ?? 'atlas-monitoramento';
        $setor_nome    = 'Atlas/Monitoramento';
        $isAdmin       = in_array($usuario_nivel, ['desenvolvedor', 'admin']);
        $podeEditar    = true;

        $pendentes = 0;
        if ($isAdmin) {
            try {
                $stmt = $this->pdo->query("SELECT COUNT(*) FROM usuarios WHERE status = 'pendente'");
                $pendentes = (int)$stmt->fetchColumn();
            } catch (Exception $e) { $pendentes = 0; }
        }

        require_once APP_PATH . '/Views/home_atlas.php';
    }
}