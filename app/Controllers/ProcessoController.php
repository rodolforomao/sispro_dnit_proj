<?php
// app/Controllers/ProcessoController.php

require_once APP_PATH . '/Models/ProcessoModel.php';

class ProcessoController {
    private $model;
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->model = new ProcessoModel($pdo);
    }

    public function create() {
        // AJAX para buscar contratos por UF
        if (isset($_GET['action']) && $_GET['action'] === 'get_contratos') {
            try {
                $uf = $_GET['uf'] ?? '';
                $dados = $this->model->getContratosPorUF($uf);
                header('Content-Type: application/json');
                echo json_encode($dados);
            } catch (Exception $e) {
                header('Content-Type: application/json');
                echo json_encode(['error' => $e->getMessage()]);
            }
            exit;
        }

        if (!isset($_SESSION['usuario_id'])) {
            header('Location: login.php');
            exit;
        }

        if ($_SESSION['usuario_nivel'] === 'leitor') {
            header('Location: index.php');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->model->salvarProcesso($_POST);
            header('Location: index.php');
            exit;
        }

        // ------------------------------------------------------------
        // RECUPERA DADOS DO USUÁRIO LOGADO
        $usuario_id = $_SESSION['usuario_id'];
        $usuario_equipe_id = $_SESSION['usuario_equipe_id'] ?? null;
        $usuario_nome = $_SESSION['usuario_nome'] ?? 'Usuário';
        $usuario_nivel = $_SESSION['usuario_nivel'] ?? 'usuario';

        // 🔥 BUSCA O RESPONSÁVEL CORRESPONDENTE AO NOME DO USUÁRIO
        $usuario_responsavel_id = null;
        if (!empty($usuario_nome)) {
            // 1. Tenta busca exata (case-insensitive) com UPPER
            $stmt = $this->pdo->prepare("SELECT id FROM responsaveis WHERE UPPER(nome) = UPPER(?)");
            $stmt->execute([$usuario_nome]);
            $usuario_responsavel_id = $stmt->fetchColumn();

            // 2. Se não encontrou, tenta com LIKE usando o primeiro nome
            if (!$usuario_responsavel_id) {
                $partes = explode(' ', $usuario_nome);
                $primeiro_nome = $partes[0] ?? '';
                if (!empty($primeiro_nome)) {
                    $stmt = $this->pdo->prepare("SELECT id FROM responsaveis WHERE UPPER(nome) LIKE UPPER(?)");
                    $stmt->execute(['%' . $primeiro_nome . '%']);
                    $usuario_responsavel_id = $stmt->fetchColumn();
                }
            }

            // 3. Se ainda não encontrou, tenta com LIKE usando o nome completo
            if (!$usuario_responsavel_id) {
                $stmt = $this->pdo->prepare("SELECT id FROM responsaveis WHERE UPPER(nome) LIKE UPPER(?)");
                $stmt->execute(['%' . $usuario_nome . '%']);
                $usuario_responsavel_id = $stmt->fetchColumn();
            }

            // 4. Se ainda não encontrou, tenta normalizar removendo acentos
            if (!$usuario_responsavel_id) {
                $nome_normalizado = $this->normalizarNome($usuario_nome);
                $stmt = $this->pdo->prepare("SELECT id FROM responsaveis WHERE UPPER(nome) LIKE UPPER(?)");
                $stmt->execute(['%' . $nome_normalizado . '%']);
                $usuario_responsavel_id = $stmt->fetchColumn();
            }
        }

        // Se não encontrou, deixa como null (select ficará em "Selecione")
        // ------------------------------------------------------------

        $contratos = $this->model->getTodosContratos();
        $dados = $this->model->getDadosFormulario();
        $dados['contratos'] = $contratos;
        $dados['usuario_id'] = $usuario_id;
        $dados['usuario_equipe_id'] = $usuario_equipe_id;
        $dados['usuario_nome'] = $usuario_nome;
        $dados['usuario_nivel'] = $usuario_nivel;
        $dados['usuario_responsavel_id'] = $usuario_responsavel_id;

        extract($dados);
        require_once APP_PATH . '/Views/create.php';
    }

    /**
     * Remove acentos e caracteres especiais, converte para minúsculas
     * (compatível com qualquer ambiente PHP, sem mbstring)
     */
    private function normalizarNome($nome) {
        $nome = strtolower($nome);
        $map = [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n'
        ];
        return strtr($nome, $map);
    }

    // ============================
    // DEMAIS MÉTODOS (edit, view, delete, getContratoInfo)
    // ============================

    public function edit() {
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: login.php');
            exit;
        }

        $usuario_nivel = $_SESSION['usuario_nivel'] ?? 'usuario';
        $usuario_nome = $_SESSION['usuario_nome'] ?? 'Usuário';

        if ($usuario_nivel === 'leitor') {
            header('Location: index.php');
            exit;
        }

        $id = $_GET['id'] ?? 0;
        if (!$id) {
            header('Location: index.php');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->model->atualizarProcesso($id, $_POST);
            header('Location: index.php');
            exit;
        }

        $processo = $this->model->getProcessoPorId($id);
        if (!$processo) {
            header('Location: index.php');
            exit;
        }

        $contratos = $this->model->getTodosContratos();
        $dados = $this->model->getDadosFormulario();
        $dados['processo'] = $processo;
        $dados['contratos'] = $contratos;
        $dados['usuario_nivel'] = $usuario_nivel;
        $dados['usuario_nome'] = $usuario_nome;
        $dados['isAdmin'] = in_array($usuario_nivel, ['desenvolvedor', 'admin']);

        extract($dados);
        require_once APP_PATH . '/Views/edit.php';
    }

    public function view() {
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: login.php');
            exit;
        }

        $id = $_GET['id'] ?? 0;
        $processo = $this->model->getProcessoCompleto($id);
        if (!$processo) {
            header('Location: index.php');
            exit;
        }

        $comentarios = $this->model->getComentarios($id);
        $usuario_nivel = $_SESSION['usuario_nivel'] ?? 'usuario';
        $usuario_nome = $_SESSION['usuario_nome'] ?? 'Usuário';

        require_once APP_PATH . '/Views/view.php';
    }

    public function delete() {
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: login.php');
            exit;
        }

        $id = $_GET['id'] ?? 0;
        if ($id) {
            $this->model->excluirProcesso($id);
        }
        header('Location: index.php');
        exit;
    }

    public function getContratoInfo() {
        if (!isset($_SESSION['usuario_id'])) {
            echo json_encode(['error' => 'Não autenticado']);
            exit;
        }

        $contrato_id = $_GET['contrato_id'] ?? 0;
        if (!$contrato_id) {
            echo json_encode(['error' => 'ID do contrato inválido']);
            exit;
        }

        $rdci = $this->model->getContratoInfo($contrato_id);
        if ($rdci) {
            $camposData = [
                'data_ordem_inicio_projeto',
                'data_ordem_inicio_obra',
                'data_termino_servico',
                'data_termino_vigencia',
                'data_termino_projeto_edital',
                'data_termino_projeto_cronog',
                'data_atualizacao'
            ];
            foreach ($camposData as $campo) {
                if (!empty($rdci[$campo])) {
                    $rdci[$campo] = date('d/m/Y', strtotime($rdci[$campo]));
                }
            }
            echo json_encode($rdci);
        } else {
            echo json_encode(['error' => 'Dados RDCI não encontrados para este contrato']);
        }
        exit;
    }
}