<?php
// home_atlas.php
$usuario_nome  = $usuario_nome  ?? $_SESSION['usuario_nome']  ?? 'Usuário';
$usuario_nivel = $usuario_nivel ?? $_SESSION['usuario_nivel'] ?? 'usuario';
$setor_slug    = $_SESSION['setor_slug']    ?? 'atlas-monitoramento';
$setor_nome    = 'Atlas/Monitoramento';
$isAdmin       = $isAdmin   ?? false;
$pendentes     = $pendentes ?? 0;
$podeEditar    = $podeEditar ?? false;

$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Home - Atlas/Monitoramento</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fc; padding-top: 80px; }

        .card-principal {
            border: none;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.08);
            background: #fff;
            padding: 20px;
        }

        /* Card de ferramenta clicável */
        .card-ferramentas {
            transition: transform 0.2s, box-shadow 0.2s;
            border-radius: 14px;
            cursor: pointer;
            height: 100%;
            min-height: 220px;
        }
        .card-ferramentas:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(0,0,0,0.12) !important;
        }
        .card-ferramentas .icone-ferramenta {
            font-size: 3.2rem;
            display: inline-block;
        }
        .card-ferramentas h5 {
            font-weight: 600;
            color: #1f2d3d;
        }
        .card-ferramentas .descricao {
            color: #6c757d;
            font-size: 0.85rem;
            min-height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
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
            <p class="text-muted mb-0">Selecione a ferramenta abaixo para começar.</p>
        </div>

        <div class="row justify-content-center g-4 mt-2">

            <!-- FERRAMENTA 1: Notificações Obras -->
            <div class="col-12 col-md-6 col-lg-4 d-flex">
                <a href="notificacoes_obras" class="text-decoration-none w-100">
                    <div class="card card-ferramentas border-0 shadow-sm w-100">
                        <div class="card-body text-center d-flex flex-column justify-content-center py-4">
                            <i class="bi bi-clipboard-check icone-ferramenta text-primary"></i>
                            <h5 class="mt-3 mb-2">Notificações Obras</h5>
                            <p class="descricao mb-0">
                                Cronogramas e notificações da fase de obras
                            </p>
                        </div>
                    </div>
                </a>
            </div>

            <!-- FERRAMENTA 2: Comparativo Bancos 1x36x38 -->
            <div class="col-12 col-md-6 col-lg-4 d-flex">
                <a href="comparativo_bancos_1x36x38" class="text-decoration-none w-100">
                    <div class="card card-ferramentas border-0 shadow-sm w-100">
                        <div class="card-body text-center d-flex flex-column justify-content-center py-4">
                            <i class="bi bi-columns-gap icone-ferramenta text-success"></i>
                            <h5 class="mt-3 mb-2">Comparativo Bancos 1 × 36 × 38</h5>
                            <p class="descricao mb-0">
                                Carteira de Contratos - COCCONV (1) × Informações Gerais de Obras - COAC (36) × Lista Todos Instrumentos - COAC (38)
                            </p>
                        </div>
                    </div>
                </a>
            </div>

            <!-- FERRAMENTA 3: Comparativo Bancos 16x40 -->
            <div class="col-12 col-md-6 col-lg-4 d-flex">
                <a href="comparativo_bancos_16x40" class="text-decoration-none w-100">
                    <div class="card card-ferramentas border-0 shadow-sm w-100">
                        <div class="card-body text-center d-flex flex-column justify-content-center py-4">
                            <i class="bi bi-diagram-2 icone-ferramenta text-warning"></i>
                            <h5 class="mt-3 mb-2">Comparativo Bancos 16 × 40</h5>
                            <p class="descricao mb-0">
                                Slides Atlas (40) × Base Controle GEO (16)
                            </p>
                        </div>
                    </div>
                </a>
            </div>

        </div>

    </div>
</div>

<!-- Bootstrap JS (necessário para dropdowns do header) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>