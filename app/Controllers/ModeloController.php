<?php
// app/Controllers/ModeloController.php

require_once APP_PATH . '/Models/ModeloModel.php';

class ModeloController {
    private $model;
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->model = new ModeloModel($pdo);
    }

    public function index() {
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: login.php');
            exit;
        }

        $usuario_nome = $_SESSION['usuario_nome'] ?? 'Usuário';
        $usuario_nivel = $_SESSION['usuario_nivel'] ?? 'usuario';

        $categorias = ['projetos', 'assessoria', 'oficios'];
        $modelos = [];
        foreach ($categorias as $cat) {
            $modelos[$cat] = $this->model->getModelosPorCategoria($cat);
        }

        $labels = [
            'projetos' => 'Projetos',
            'assessoria' => 'Assessoria',
            'oficios' => 'Ofícios / Portarias'
        ];
        $badgeClasses = [
            'projetos' => 'badge-projetos',
            'assessoria' => 'badge-assessoria',
            'oficios' => 'badge-oficios'
        ];

        // Função auxiliar para verificar data defasada (4 meses)
        function dataDefasada($data) {
            if (empty($data)) return false;
            $dataModelo = new DateTime($data);
            $hoje = new DateTime();
            $diferenca = $hoje->diff($dataModelo);
            $meses = ($diferenca->y * 12) + $diferenca->m;
            return ($meses >= 4);
        }

        // Passa todas as variáveis para a view
        require_once APP_PATH . '/Views/modelos.php';
    }
}