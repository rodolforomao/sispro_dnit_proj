<?php
// solicitar_acesso.php - View pura
// Variáveis: $mensagem, $erro, $nome, $email
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Solicitar Acesso</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fc; display: flex; justify-content: center; align-items: center; height: 100vh; }
        .card { max-width: 550px; width: 100%; padding: 30px; border-radius: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.08); background: #fff; }
    </style>
</head>
<body>
<div class="card">
    <h3 class="text-center mb-4"><i class="bi bi-person-plus"></i> Solicitar Acesso</h3>
    <?php if ($mensagem): ?>
        <div class="alert alert-success"><?= $mensagem ?></div>
    <?php endif; ?>
    <?php if ($erro): ?>
        <div class="alert alert-danger"><?= $erro ?></div>
    <?php endif; ?>
    <form method="POST">
        <div class="mb-3">
            <label>Nome Completo</label>
            <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($nome ?? '') ?>" required>
        </div>
        <div class="mb-3">
            <label>Email Corporativo</label>
            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($email ?? '') ?>" required>
        </div>
        <div class="mb-3">
            <label>Senha</label>
            <input type="password" name="senha" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Confirmar Senha</label>
            <input type="password" name="confirma_senha" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">Enviar Solicitação</button>
    </form>
    <p class="text-center mt-3"><a href="login">Já tem conta? Faça login</a></p>
</div>
</body>
</html>