<?php
// app/Controllers/AuthController.php

require_once APP_PATH . '/Models/UsuarioModel.php';

class AuthController {
    private $model;
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->model = new UsuarioModel($pdo);
    }

    public function login() {
        if (isset($_SESSION['usuario_id'])) {
            header('Location: ./');
            exit;
        }

        $erro = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = $_POST['email'] ?? '';
            $senha = $_POST['senha'] ?? '';
            $lembrar = isset($_POST['lembrar']);
            $manter = isset($_POST['manter']);

            $usuario = $this->model->autenticar($email, $senha);
            if ($usuario) {
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['usuario_nome'] = $usuario['nome'];
                $_SESSION['usuario_nivel'] = $usuario['nivel'];
                $_SESSION['usuario_email'] = $usuario['email'];
                $_SESSION['usuario_equipe_id'] = $usuario['equipe_id'] ?? null;

                if ($lembrar) setcookie('email', $email, time() + 86400*30, '/');
                else setcookie('email', '', time() - 3600, '/');

                if ($manter) {
                    ini_set('session.cookie_lifetime', 86400*30);
                    session_set_cookie_params(86400*30);
                } else {
                    ini_set('session.cookie_lifetime', 0);
                    session_set_cookie_params(0);
                }
                session_regenerate_id(true);

                header('Location: selecionar_setor');
                exit;
            } else {
                $erro = 'Email ou senha inválidos.';
            }
        }

        $email = $_COOKIE['email'] ?? '';
        require_once APP_PATH . '/Views/login.php';
    }

    public function logout() {
        session_destroy();
        header('Location: login');
        exit;
    }

    public function solicitarAcesso() {
        $mensagem = '';
        $erro = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nome = $_POST['nome'] ?? '';
            $email = $_POST['email'] ?? '';
            $senha = $_POST['senha'] ?? '';
            $confirma = $_POST['confirma_senha'] ?? '';

            if (empty($nome) || empty($email) || empty($senha) || $senha !== $confirma) {
                $erro = 'Preencha todos os campos corretamente.';
            } elseif ($this->model->existeEmail($email)) {
                $erro = 'Email já cadastrado.';
            } else {
                if ($this->model->criarSolicitacao($nome, $email, $senha)) {
                    $mensagem = 'Solicitação enviada! Aguarde aprovação.';
                } else {
                    $erro = 'Erro ao cadastrar. Tente novamente.';
                }
            }
        }
        require_once APP_PATH . '/Views/solicitar_acesso.php';
    }

    public function alterarSenha() {
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: login');
            exit;
        }

        $usuario_id = $_SESSION['usuario_id'];
        $usuario_nome = $_SESSION['usuario_nome'] ?? 'Usuário';
        $erro = '';
        $sucesso = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $senhaAtual = $_POST['senha_atual'] ?? '';
            $novaSenha = $_POST['nova_senha'] ?? '';
            $confirmaSenha = $_POST['confirma_senha'] ?? '';

            if (empty($senhaAtual) || empty($novaSenha) || empty($confirmaSenha)) {
                $erro = 'Todos os campos são obrigatórios.';
            } elseif ($novaSenha !== $confirmaSenha) {
                $erro = 'As senhas não coincidem.';
            } elseif (strlen($novaSenha) < 6) {
                $erro = 'A nova senha deve ter pelo menos 6 caracteres.';
            } else {
                if ($this->model->verificarSenhaAtual($usuario_id, $senhaAtual)) {
                    $this->model->atualizarSenha($usuario_id, $novaSenha);
                    $sucesso = 'Senha alterada com sucesso!';
                } else {
                    $erro = 'Senha atual incorreta.';
                }
            }
        }

        require_once APP_PATH . '/Views/alterar_senha.php';
    }

    public function recuperarSenha() {
        $mensagem = '';
        $erro = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = $_POST['email'] ?? '';
            if (empty($email)) {
                $erro = 'Informe seu e-mail.';
            } elseif (!$this->model->existeEmail($email)) {
                $erro = 'E-mail não cadastrado.';
            } else {
                $token = $this->model->gerarTokenRecuperacao($email);
                $link = "http://".$_SERVER['HTTP_HOST']."/sispro/redefinir_senha?token=".$token;
                $mensagem = "Um link de redefinição foi gerado. <a href='$link'>Clique aqui para redefinir sua senha</a> (este link expira em 1 hora).";
            }
        }

        require_once APP_PATH . '/Views/recuperar_senha.php';
    }

    public function redefinirSenha() {
        $token = $_GET['token'] ?? '';
        $erro = '';
        $sucesso = '';
        $email = '';

        if (empty($token)) {
            die('Token inválido.');
        }

        $email = $this->model->validarTokenRecuperacao($token);
        if (!$email) {
            die('Token inválido ou expirado. Solicite uma nova redefinição.');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $novaSenha = $_POST['nova_senha'] ?? '';
            $confirmaSenha = $_POST['confirma_senha'] ?? '';

            if (empty($novaSenha) || empty($confirmaSenha)) {
                $erro = 'Preencha todos os campos.';
            } elseif ($novaSenha !== $confirmaSenha) {
                $erro = 'As senhas não coincidem.';
            } elseif (strlen($novaSenha) < 6) {
                $erro = 'A senha deve ter pelo menos 6 caracteres.';
            } else {
                $this->model->atualizarSenhaPorEmail($email, $novaSenha);
                $sucesso = 'Senha redefinida com sucesso! <a href="login">Faça login</a>';
            }
        }

        require_once APP_PATH . '/Views/redefinir_senha.php';
    }
}