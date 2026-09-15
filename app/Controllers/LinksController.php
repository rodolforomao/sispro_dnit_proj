<?php
// app/Controllers/LinksController.php

class LinksController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function index() {
        // Verifica se está logado
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: login.php');
            exit;
        }

        // Variáveis para a view (todos os perfis podem acessar)
        $usuario_nivel = $_SESSION['usuario_nivel'] ?? 'usuario';
        $usuario_nome = $_SESSION['usuario_nome'] ?? 'Usuário';

        require_once APP_PATH . '/Views/links.php';
    }

    // Método para a rota links_powerbi (se quiser manter)
    // public function powerbi() {
    //     // Se precisar de uma página separada para o BI
    // }
}