<?php if (!isset($setores) || empty($setores)) {
    header('Location: login');
    exit;
} ?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Selecionar Setor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { 
            background: #f0f2f5; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            min-height: 100vh; 
            margin: 0; 
        }
        .card-setor {
            border: none;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            cursor: pointer;
            background: #fff;
            height: 100%;
            min-height: 220px; /* Aumentado para dar mais presença ao botão */
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
        }
        .card-setor:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
        }
        .card-setor .card-body {
            padding: 40px 25px; /* Maior espaçamento interno */
            text-align: center;
            width: 100%;
        }
        .card-setor i {
            font-size: 4rem; /* Ícone ampliado */
            color: #004a8f;
            margin-bottom: 20px;
            display: block;
        }
        .card-setor .card-title {
            font-weight: 600;
            color: #2c3e50;
            font-size: 1.35rem; /* Fonte do botão ampliada */
            margin: 0;
        }
        .card-setor button {
            background: transparent;
            border: none;
            width: 100%;
            padding: 0;
        }
        .container-selector {
            max-width: 800px; /* Ligeiramente ampliado para acomodar melhor os botões */
            padding: 20px;
        }
        .logo-dnit { max-height: 60px; }
        .titulo { color: #004a8f; font-weight: 700; }
    </style>
</head>
<body>
<div class="container container-selector">
    <div class="text-center mb-4">
        <img src="https://www.gov.br/dnit/pt-br/central-de-conteudos/publicacoes/manual-de-gestao-da-marca/marcas-dnit/assinaturas-e-marcas/monocromatica-dnit-simples.png" alt="DNIT" class="logo-dnit">
        <h3 class="titulo mt-3">Selecione o Setor</h3>
        <p class="text-muted">Escolha o setor para acessar as funcionalidades</p>
    </div>
    
    <div class="row g-4 justify-content-center">
        <?php foreach ($setores as $setor): ?>
        <div class="col-md-6 col-lg-5">
            <form method="POST" action="definir_setor">
                <input type="hidden" name="setor_id" value="<?= $setor['id'] ?>">
                <button type="submit" class="card card-setor">
                    <div class="card-body">
                        <i class="<?= htmlspecialchars($setor['icone'] ?? 'bi bi-building') ?>"></i>
                        <h5 class="card-title"><?= htmlspecialchars($setor['nome']) ?></h5>
                    </div>
                </button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
    
    <div class="mt-4 text-center">
        <a href="logout" class="btn btn-outline-secondary btn-sm"><i class="bi bi-box-arrow-right"></i> Sair</a>
    </div>
</div>
</body>
</html>