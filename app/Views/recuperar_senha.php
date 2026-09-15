<?php
// View recuperar_senha.php
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Recuperar Senha</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fc; display: flex; justify-content: center; align-items: center; height: 100vh; }
        .card { max-width: 500px; width: 100%; padding: 30px; border-radius: 16px; box-shadow: 0 8px 30px rgba(0,0,0,0.08); background: #fff; }
    </style>
</head>
<body>
<div class="card">
    <h3 class="mb-4"><i class="bi bi-envelope"></i> Recuperar Senha</h3>
    <?php if ($mensagem ?? ''): ?>
        <div class="alert alert-success"><?= $mensagem ?></div>
    <?php endif; ?>
    <?php if ($erro ?? ''): ?>
        <div class="alert alert-danger"><?= $erro ?></div>
    <?php endif; ?>
    <form method="POST">
        <div class="mb-3">
            <label for="email" class="form-label">E-mail cadastrado</label>
            <input type="email" name="email" id="email" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">Enviar link de recuperação</button>
    </form>
    <p class="text-center mt-3"><a href="login">Voltar ao login</a></p>
</div>
</body>
</html>