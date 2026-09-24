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
            try {
                $this->model->salvarProcesso($_POST);
                $_SESSION['flash_processo'] = [
                    'tipo'     => 'success',
                    'titulo'   => 'Salvo com sucesso!',
                    'mensagem' => 'O processo foi cadastrado com sucesso.'
                ];
            } catch (Exception $e) {
                $_SESSION['flash_processo'] = [
                    'tipo'     => 'danger',
                    'titulo'   => 'Erro ao salvar',
                    'mensagem' => 'Não foi possível cadastrar o processo: ' . $e->getMessage()
                ];
            }
            header('Location: index.php');
            exit;
        }

        // RECUPERA DADOS DO USUÁRIO LOGADO
        $usuario_id = $_SESSION['usuario_id'];
        $usuario_equipe_id = $_SESSION['usuario_equipe_id'] ?? null;
        $usuario_nome = $_SESSION['usuario_nome'] ?? 'Usuário';
        $usuario_nivel = $_SESSION['usuario_nivel'] ?? 'usuario';

        // BUSCA O RESPONSÁVEL CORRESPONDENTE AO NOME DO USUÁRIO
        $usuario_responsavel_id = null;
        if (!empty($usuario_nome)) {
            $stmt = $this->pdo->prepare("SELECT id FROM responsaveis WHERE UPPER(nome) = UPPER(?)");
            $stmt->execute([$usuario_nome]);
            $usuario_responsavel_id = $stmt->fetchColumn();

            if (!$usuario_responsavel_id) {
                $partes = explode(' ', $usuario_nome);
                $primeiro_nome = $partes[0] ?? '';
                if (!empty($primeiro_nome)) {
                    $stmt = $this->pdo->prepare("SELECT id FROM responsaveis WHERE UPPER(nome) LIKE UPPER(?)");
                    $stmt->execute(['%' . $primeiro_nome . '%']);
                    $usuario_responsavel_id = $stmt->fetchColumn();
                }
            }

            if (!$usuario_responsavel_id) {
                $stmt = $this->pdo->prepare("SELECT id FROM responsaveis WHERE UPPER(nome) LIKE UPPER(?)");
                $stmt->execute(['%' . $usuario_nome . '%']);
                $usuario_responsavel_id = $stmt->fetchColumn();
            }

            if (!$usuario_responsavel_id) {
                $nome_normalizado = $this->normalizarNome($usuario_nome);
                $stmt = $this->pdo->prepare("SELECT id FROM responsaveis WHERE UPPER(nome) LIKE UPPER(?)");
                $stmt->execute(['%' . $nome_normalizado . '%']);
                $usuario_responsavel_id = $stmt->fetchColumn();
            }
        }

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
    // EDIT
    // ============================
    public function edit() {
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: login.php');
            exit;
        }

        $usuario_nivel = $_SESSION['usuario_nivel'] ?? 'usuario';
        $usuario_nome  = $_SESSION['usuario_nome']  ?? 'Usuário';

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
            try {
                $this->model->atualizarProcesso($id, $_POST);
                $_SESSION['flash_processo'] = [
                    'tipo'     => 'success',
                    'titulo'   => 'Atualizado com sucesso!',
                    'mensagem' => 'As alterações do processo foram salvas com sucesso.'
                ];
            } catch (Exception $e) {
                $_SESSION['flash_processo'] = [
                    'tipo'     => 'danger',
                    'titulo'   => 'Erro ao atualizar',
                    'mensagem' => 'Não foi possível atualizar o processo: ' . $e->getMessage()
                ];
            }
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

    // ============================
    // VIEW
    // ============================
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

    // ============================
    // DELETE
    // ============================
    public function delete() {
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: login.php');
            exit;
        }

        $usuario_nivel = $_SESSION['usuario_nivel'] ?? 'usuario';
        if ($usuario_nivel === 'leitor') {
            header('Location: index.php');
            exit;
        }

        $id = $_GET['id'] ?? 0;
        if ($id) {
            try {
                $this->model->excluirProcesso($id);
                $_SESSION['flash_processo'] = [
                    'tipo'     => 'success',
                    'titulo'   => 'Excluído com sucesso!',
                    'mensagem' => 'O processo foi removido do sistema.'
                ];
            } catch (Exception $e) {
                $_SESSION['flash_processo'] = [
                    'tipo'     => 'danger',
                    'titulo'   => 'Erro ao excluir',
                    'mensagem' => 'Não foi possível excluir o processo: ' . $e->getMessage()
                ];
            }
        }
        header('Location: index.php');
        exit;
    }

    // ============================
    // ✅ GET CONTRATO INFO (AJAX - Modal Info Contrato)
    // ✅ FONTE AGORA É contratos_rdci DIRETO
    // ============================
    public function getContratoInfo() {
        header('Content-Type: application/json; charset=utf-8');

        if (!isset($_SESSION['usuario_id'])) {
            echo json_encode(['error' => 'Não autenticado']);
            exit;
        }

        $contrato_id = (int)($_GET['contrato_id'] ?? 0);
        if (!$contrato_id) {
            echo json_encode(['error' => 'ID do contrato inválido']);
            exit;
        }

        try {
            $rdci = $this->model->getContratoInfo($contrato_id);
            if (!$rdci) {
                echo json_encode(['error' => 'Contrato não encontrado na base RDCI']);
                exit;
            }

            // Formata datas para dd/mm/aaaa
            $camposData = [
                'data_ordem_inicio_projeto',
                'data_ordem_inicio_obra',
                'data_termino_servico',
                'data_termino_vigencia',
                'data_termino_projeto_edital',
                'data_termino_projeto_cronog',
                'data_ultima_notificacao',
                'data_atualizacao'
            ];
            foreach ($camposData as $campo) {
                if (!empty($rdci[$campo])) {
                    $ts = strtotime($rdci[$campo]);
                    if ($ts !== false) {
                        $rdci[$campo] = date('d/m/Y', $ts);
                    }
                }
            }

            echo json_encode($rdci);
        } catch (Exception $e) {
            echo json_encode(['error' => 'Erro ao buscar contrato: ' . $e->getMessage()]);
        }
        exit;
    }
}