<?php
// app/Controllers/SetorController.php

class SetorController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // ============================================================
    // 🔥 Helper — decide pra onde redirecionar conforme o setor
    // ============================================================
    private function redirectParaSetor($slug) {
        if ($slug === 'atlas-monitoramento') {
            header('Location: home_atlas');
        } else {
            header('Location: index');
        }
        exit;
    }

    public function selecionar() {
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: login');
            exit;
        }

        $stmt = $this->pdo->prepare("
            SELECT s.id, s.nome, s.slug, s.descricao, s.icone
            FROM setores s
            JOIN usuario_setor us ON us.setor_id = s.id
            WHERE us.usuario_id = ?
            AND s.ativo = 1
            ORDER BY s.nome
        ");
        $stmt->execute([$_SESSION['usuario_id']]);
        $setores = $stmt->fetchAll();

        if (empty($setores)) {
            session_destroy();
            header('Location: login?erro=sem_setor');
            exit;
        }

        // ✅ Se só tem 1 setor, define e redireciona conforme o slug
        if (count($setores) === 1) {
            $setor = $setores[0];
            $_SESSION['setor_id']   = $setor['id'];
            $_SESSION['setor_slug'] = $setor['slug'];
            $this->redirectParaSetor($setor['slug']);
        }

        require_once APP_PATH . '/Views/selecionar_setor.php';
    }

    public function definirSetor() {
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: login');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setor_id'])) {
            $setor_id = (int)$_POST['setor_id'];

            // Confirma que o usuário realmente tem acesso a esse setor
            $stmt = $this->pdo->prepare("SELECT 1 FROM usuario_setor WHERE usuario_id = ? AND setor_id = ?");
            $stmt->execute([$_SESSION['usuario_id'], $setor_id]);

            if ($stmt->fetchColumn()) {
                $stmt = $this->pdo->prepare("SELECT slug FROM setores WHERE id = ?");
                $stmt->execute([$setor_id]);
                $slug = $stmt->fetchColumn();

                $_SESSION['setor_id']   = $setor_id;
                $_SESSION['setor_slug'] = $slug;

                // ✅ Redireciona conforme o setor escolhido
                $this->redirectParaSetor($slug);
            }
        }

        header('Location: selecionar_setor');
        exit;
    }

    public function trocar() {
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: login');
            exit;
        }
        unset($_SESSION['setor_id']);
        unset($_SESSION['setor_slug']);
        header('Location: selecionar_setor');
        exit;
    }
}