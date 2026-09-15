<?php
// View redefinir_senha.php
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Redefinir Senha</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fc; display: flex; justify-content: center; align-items: center; height: 100vh; }
        .card { max-width: 500px; width: 100%; padding: 30px; border-radius: 16px; box-shadow: 0 8px 30px rgba(0,0,0,0.08); background: #fff; }
    </style>
</head>
<body>
<div class="card">
    <h3 class="mb-4"><i class="bi bi-pencil-square"></i> Redefinir Senha</h3>
    <?php if ($sucesso ?? ''): ?>
        <div class="alert alert-success"><?= $sucesso ?></div>
    <?php else: ?>
        <?php if ($erro ?? ''): ?>
            <div class="alert alert-danger"><?= $erro ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-3">
                <label for="nova_senha" class="form-label">Nova Senha</label>
                <input type="password" name="nova_senha" id="nova_senha" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="confirma_senha" class="form-label">Confirmar Nova Senha</label>
                <input type="password" name="confirma_senha" id="confirma_senha" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Redefinir Senha</button>
        </form>
    <?php endif; ?>
    <p class="text-center mt-3"><a href="login">Voltar ao login</a></p>
</div>
</body>
</html>