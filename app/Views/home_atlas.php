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
        .btn-diagrama { background: #198754; color: #fff; }
        .btn-diagrama:hover { background: #157347; color: #fff; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(25,135,84,0.3); }
        .card-ferramentas { transition: transform 0.2s; max-width: 500px; margin: 0 auto; }
        .card-ferramentas:hover { transform: translateY(-5px); }
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
            <!-- Diagrama Unifilar - centralizado -->
            <div class="col-md-6 col-lg-5">
                <div class="card card-ferramentas h-100 text-center p-4 border-0 shadow-sm" style="background: #f0fff4; border-radius: 16px;">
                    <div class="card-body">
                        <i class="bi bi-diagram-3" style="font-size: 3rem; color: #198754;"></i>
                        <h4 class="card-title mt-3">Diagrama Unifilar</h4>
                        <p class="card-text text-muted">Gere diagramas rodoviários com OAEs e acompanhamento de obras.</p>
                        <a href="diagrama_unifilar" class="btn btn-diagrama btn-lg">Acessar <i class="bi bi-arrow-right"></i></a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Botão "Voltar ao início" removido conforme solicitado -->
    </div>
</div>

</body>
</html>