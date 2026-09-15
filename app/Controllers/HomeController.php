<?php
// app/Controllers/HomeController.php

require_once APP_PATH . '/Models/ProcessoModel.php';

class HomeController {
    private $model;
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->model = new ProcessoModel($pdo);
    }

    public function index() {
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: login');
            exit;
        }

        if (!isset($_SESSION['setor_slug'])) {
            header('Location: selecionar_setor');
            exit;
        }

        $usuario_id = $_SESSION['usuario_id'];
        $usuario_nome = $_SESSION['usuario_nome'] ?? 'Usuário';
        $usuario_nivel = $_SESSION['usuario_nivel'] ?? 'usuario';
        $usuario_equipe_id = $_SESSION['usuario_equipe_id'] ?? null;
        $isAdmin = in_array($usuario_nivel, ['desenvolvedor', 'admin']);
        $setor_slug = $_SESSION['setor_slug'];

        // Buscar o ID do responsável correspondente ao nome do usuário logado
        $usuario_responsavel_id = null;
        if (!empty($usuario_nome)) {
            // Tenta busca exata (case-insensitive)
            $stmt = $this->pdo->prepare("SELECT id FROM responsaveis WHERE UPPER(nome) = UPPER(?)");
            $stmt->execute([$usuario_nome]);
            $usuario_responsavel_id = $stmt->fetchColumn();

            // Se não encontrou, tenta com LIKE usando o primeiro nome
            if (!$usuario_responsavel_id) {
                $partes = explode(' ', $usuario_nome);
                $primeiro_nome = $partes[0] ?? '';
                if (!empty($primeiro_nome)) {
                    $stmt = $this->pdo->prepare("SELECT id FROM responsaveis WHERE UPPER(nome) LIKE UPPER(?)");
                    $stmt->execute(['%' . $primeiro_nome . '%']);
                    $usuario_responsavel_id = $stmt->fetchColumn();
                }
            }

            // Se ainda não encontrou, tenta com LIKE usando o nome completo
            if (!$usuario_responsavel_id) {
                $stmt = $this->pdo->prepare("SELECT id FROM responsaveis WHERE UPPER(nome) LIKE UPPER(?)");
                $stmt->execute(['%' . $usuario_nome . '%']);
                $usuario_responsavel_id = $stmt->fetchColumn();
            }
        }

        // Busca os nomes dos setores do usuário
        $setores_usuario = [];
        if ($usuario_id) {
            $stmt = $this->pdo->prepare("
                SELECT s.nome 
                FROM setores s 
                INNER JOIN usuario_setor us ON s.id = us.setor_id 
                WHERE us.usuario_id = ?
            ");
            $stmt->execute([$usuario_id]);
            $setores_usuario = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        // Conta quantos setores o usuário possui
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM usuario_setor WHERE usuario_id = ?");
        $stmt->execute([$usuario_id]);
        $total_setores = (int)$stmt->fetchColumn();

        $pendentes = 0;
        if ($isAdmin) {
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM usuarios WHERE status = 'pendente'");
            $pendentes = $stmt->fetchColumn();
        }

        // Filtros recebidos
        $filtros = [
            'equipe'      => $_GET['equipe'] ?? '',
            'responsavel' => $_GET['responsavel'] ?? '',
            'status'      => $_GET['status'] ?? '',
            'processo'    => $_GET['processo'] ?? '',
            'contrato'    => $_GET['contrato'] ?? '',
            'tipo'        => $_GET['tipo'] ?? '',
            'assunto'     => $_GET['assunto'] ?? '',
            'uf'          => $_GET['uf'] ?? '',
            'br'          => $_GET['br'] ?? ''
        ];

        // Aplica filtro padrão de equipe se o parâmetro NÃO FOI ENVIADO
        if (!array_key_exists('equipe', $_GET) && !empty($usuario_equipe_id)) {
            $filtros['equipe'] = $usuario_equipe_id;
        }

        // Aplica filtro padrão de responsável se o parâmetro NÃO FOI ENVIADO
        if (!array_key_exists('responsavel', $_GET) && !empty($usuario_responsavel_id)) {
            $filtros['responsavel'] = $usuario_responsavel_id;
        }

        $default_equipe = $filtros['equipe'];
        $default_responsavel = $filtros['responsavel'];

        // Paginação
        $pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
        if ($pagina < 1) $pagina = 1;
        $porPagina = 20;

        $dadosModel = $this->model->getDadosHome($filtros, $pagina, $porPagina);
        $dados = array_merge($dadosModel, [
            'usuario_id'          => $usuario_id,
            'usuario_nome'        => $usuario_nome,
            'usuario_nivel'       => $usuario_nivel,
            'usuario_equipe_id'   => $usuario_equipe_id,
            'default_equipe'      => $default_equipe,
            'default_responsavel' => $default_responsavel,
            'total_setores'       => $total_setores,
            'pendentes'           => $pendentes,
            'isAdmin'             => $isAdmin,
            'filtros'             => $filtros,
            'setor_slug'          => $setor_slug,
            'pagina'              => $pagina,
            'porPagina'           => $porPagina,
            'setores_usuario'     => $setores_usuario
        ]);

        extract($dados);

        if ($setor_slug === 'assessoria-projetos') {
            require_once APP_PATH . '/Views/home.php';
        } elseif ($setor_slug === 'atlas-monitoramento') {
            require_once APP_PATH . '/Views/home_atlas.php';
        } else {
            header('Location: selecionar_setor');
            exit;
        }
    }

    /**
     * Página do Diagrama Unifilar
     */
    public function diagramaUnifilar() {
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: login');
            exit;
        }

        if (!isset($_SESSION['setor_slug'])) {
            header('Location: selecionar_setor');
            exit;
        }

        require_once APP_PATH . '/Views/diagrama_unifilar.php';
    }

    /**
     * Página de Gestão de Obras (em desenvolvimento)
     */
    public function gestaoObras() {
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: login');
            exit;
        }

        if (!isset($_SESSION['setor_slug'])) {
            header('Location: selecionar_setor');
            exit;
        }

        // Exibe uma página simples em desenvolvimento
        ?>
        <!DOCTYPE html>
        <html lang="pt">
        <head>
            <meta charset="UTF-8">
            <title>Gestão de Obras - SISPRO</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
        </head>
        <body>
            <?php include APP_PATH . '/Views/header.php'; ?>
            <div class="container mt-5">
                <div class="card p-5 text-center">
                    <i class="bi bi-tools" style="font-size: 4rem; color: #0d6efd;"></i>
                    <h1 class="mt-3">Gestão de Obras</h1>
                    <p class="text-muted">Esta funcionalidade está em desenvolvimento. Em breve você poderá acompanhar o andamento das obras, disciplinas e cronogramas.</p>
                    <a href="home_atlas" class="btn btn-primary btn-lg mt-3"><i class="bi bi-arrow-left"></i> Voltar ao Atlas</a>
                </div>
            </div>
            <?php include APP_PATH . '/public/chat_widget.php'; ?>
        </body>
        </html>
        <?php
        exit;
    }
}