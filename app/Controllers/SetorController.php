<?php
// app/Controllers/SetorController.php

class SetorController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
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

        if (count($setores) === 1) {
            $setor = $setores[0];
            $_SESSION['setor_id'] = $setor['id'];
            $_SESSION['setor_slug'] = $setor['slug'];
            header('Location: index');
            exit;
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
            $stmt = $this->pdo->prepare("SELECT 1 FROM usuario_setor WHERE usuario_id = ? AND setor_id = ?");
            $stmt->execute([$_SESSION['usuario_id'], $setor_id]);
            if ($stmt->fetchColumn()) {
                $stmt = $this->pdo->prepare("SELECT slug FROM setores WHERE id = ?");
                $stmt->execute([$setor_id]);
                $slug = $stmt->fetchColumn();
                $_SESSION['setor_id'] = $setor_id;
                $_SESSION['setor_slug'] = $slug;
                header('Location: index');
                exit;
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