<?php
require_once 'config.php';
require_once __DIR__ . '/../Config/version.php';

// Se já estiver logado, redireciona para o index
if (isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

$erro = '';
$email = '';
$senha = '';

// Se tiver cookie de email, preencher
if (isset($_COOKIE['email'])) {
    $email = $_COOKIE['email'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = trim($_POST['senha'] ?? '');
    $lembrar = isset($_POST['lembrar']);
    $manter = isset($_POST['manter']);

    if (empty($email) || empty($senha)) {
        $erro = 'Preencha todos os campos.';
    } else {
        // Buscar usuário pelo email
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? AND status = 'ativo'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Verifica a senha (MD5)
            if (md5($senha) === $user['senha']) {
                // Login bem-sucedido
                $_SESSION['usuario_id'] = $user['id'];
                $_SESSION['usuario_nome'] = $user['nome'];
                $_SESSION['usuario_nivel'] = $user['nivel'];
                $_SESSION['usuario_email'] = $user['email'];

                // Lembrar usuário
                if ($lembrar) {
                    setcookie('email', $email, time() + (86400 * 30), "/"); // 30 dias
                } else {
                    // Se não marcar, deletar cookie se existir
                    setcookie('email', '', time() - 3600, "/");
                }

                // Manter conectado: estender sessão por 30 dias
                if ($manter) {
                    // Configura cookie de sessão para durar 30 dias
                    ini_set('session.cookie_lifetime', 86400 * 30);
                    session_set_cookie_params(86400 * 30);
                } else {
                    // Sessão padrão (até fechar o navegador)
                    ini_set('session.cookie_lifetime', 0);
                    session_set_cookie_params(0);
                }
                // Como a sessão já foi iniciada em config.php, precisamos recriar a sessão com as novas configurações
                // Para simplificar, podemos regenerar a sessão
                session_regenerate_id(true);

                header('Location: index.php');
                exit;
            } else {
                $erro = 'Senha incorreta.';
            }
        } else {
            $erro = 'Usuário não encontrado ou inativo.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Login - Controle de Processos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #e8f0fe 0%, #f8f9fc 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            max-width: 500px;
            width: 100%;
            padding: 35px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            background: #fff;
        }
        .logo-dnit {
            max-width: 180px;
            display: block;
            margin: 0 auto 20px;
        }
        .btn-login {
            background: #004a8f;
            border: none;
            border-radius: 10px;
            padding: 10px;
            font-weight: 600;
        }
        .btn-login:hover {
            background: #003366;
        }
        .form-control {
            border-radius: 10px;
        }
        .footer-text {
            text-align: center;
            margin-top: 15px;
            font-size: 0.8rem;
            color: #6c757d;
        }
        .form-check-label {
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
<div class="login-card">
    <img src="https://www.gov.br/dnit/pt-br/central-de-conteudos/publicacoes/manual-de-gestao-da-marca/marcas-dnit/assinaturas-e-marcas/monocromatica-dnit-extenso.png" alt="DNIT" class="logo-dnit">
    <h4 class="text-center mb-4" style="color: #004a8f; font-weight: 700;">Controle de Processos COAC</h4>

    <?php if ($erro): ?>
        <div class="alert alert-danger"><?= $erro ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label"><i class="bi bi-envelope"></i> Email</label>
            <input type="email" name="email" class="form-control" placeholder="seu@email.com" value="<?= htmlspecialchars($email) ?>" required autofocus>
        </div>
        <div class="mb-3">
            <label class="form-label"><i class="bi bi-lock"></i> Senha</label>
            <input type="password" name="senha" class="form-control" placeholder="Digite sua senha" required>
        </div>

        <!-- Checkboxes -->
        <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="lembrar" name="lembrar" <?= isset($_COOKIE['email']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="lembrar">Lembrar usuário</label>
        </div>
        <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="manter" name="manter">
            <label class="form-check-label" for="manter">Manter conectado</label>
        </div>

        <button type="submit" class="btn btn-primary btn-login w-100"><i class="bi bi-box-arrow-in-right"></i> Entrar</button>
    </form>

    <p class="text-center mt-3"><a href="solicitar_acesso.php">Solicitar acesso</a></p>

    <div class="footer-text">
        <p>Desenvolvido por <strong>Bruno Pimenta</strong> - Versão <?= SISPRO_VERSION ?></p>
    </div>
</div>
</body>
</html>