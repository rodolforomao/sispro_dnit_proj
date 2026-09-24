<?php
// home_atlas.php
$usuario_nome = $usuario_nome ?? $_SESSION['usuario_nome'] ?? 'Usuário';
$usuario_nivel = $_SESSION['usuario_nivel'] ?? 'usuario';
$setor_slug = $_SESSION['setor_slug'] ?? 'atlas-monitoramento';
$setor_nome = 'Atlas/Monitoramento';
$isAdmin = $isAdmin ?? false;
$pendentes = $pendentes ?? 0;
$podeEditar = $podeEditar ?? false;
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Home - Atlas/Monitoramento</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fc; padding-top: 80px; }
        .card-principal { border: none; border-radius: 16px; box-shadow: 0 8px 30px rgba(0,0,0,0.08); background: #fff; padding: 20px; }
        .card-ferramentas { transition: transform 0.2s; max-width: 500px; margin: 0 auto; }
        .card-ferramentas:hover { transform: translateY(-5px); }

        /* ✅ Card em desenvolvimento */
        .card-desenvolvimento {
            background: #fff8e1;
            border: 2px dashed #ffc107;
            border-radius: 16px;
            padding: 40px 30px;
            text-align: center;
        }
        .card-desenvolvimento .icone-dev {
            font-size: 3.5rem;
            color: #ffc107;
            display: inline-block;
            animation: girar 3s linear infinite;
        }
        @keyframes girar {
            from { transform: rotate(0deg); }
            to   { transform: rotate(360deg); }
        }
        .card-desenvolvimento h4 {
            color: #b8860b;
            font-weight: 700;
            margin-top: 15px;
        }
        .card-desenvolvimento p {
            color: #8a6d3b;
            margin-bottom: 0;
        }
    </style>
</head>
<body>
<!-- HEADER ATLAS -->
<?php include APP_PATH . '/Views/header_atlas.php'; ?>

<div class="container-fluid mt-4">
    <div class="card card-principal">
        <div class="text-center py-4">
            <h3><i class="bi bi-map"></i> Atlas/Monitoramento</h3>
            <p class="text-muted">Selecione a ferramenta abaixo para começar.</p>
        </div>

        <div class="row justify-content-center g-4 mt-2">
            <!-- Área em desenvolvimento -->
            <div class="col-md-8 col-lg-6">
                <div class="card-desenvolvimento">
                    <i class="bi bi-tools icone-dev"></i>
                    <h4>Em desenvolvimento</h4>
                    <p>
                        Esta área está em atualização.<br>
                        Novas ferramentas serão disponibilizadas em breve.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ✅ ADICIONADO: Bootstrap JS (necessário para dropdowns do header) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>