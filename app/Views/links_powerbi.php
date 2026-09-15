<?php
// View links_powerbi.php - Página para exibir o Power BI em iframe
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Power BI - SISPRO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fc; margin: 0; padding: 0; }
        .header-bar {
            background: #fff;
            padding: 10px 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #e9ecef;
        }
        .header-bar .logo-dnit { max-height: 40px; }
        .header-bar .sistema-titulo { font-weight: 700; color: #004a8f; font-size: 1.2rem; letter-spacing: 0.5px; }
        .iframe-container {
            position: absolute;
            top: 70px; /* altura do header */
            left: 0;
            right: 0;
            bottom: 0;
            width: 100%;
            height: calc(100% - 70px);
            border: none;
        }
        .iframe-container iframe {
            width: 100%;
            height: 100%;
            border: none;
        }
        .btn-voltar {
            background: #004a8f;
            color: #fff;
            border: none;
            padding: 6px 16px;
            border-radius: 8px;
            font-weight: 500;
        }
        .btn-voltar:hover { background: #003366; color: #fff; }
        @media (max-width: 768px) {
            .header-bar { padding: 8px 12px; }
            .header-bar .sistema-titulo { font-size: 1rem; }
            .iframe-container { top: 60px; height: calc(100% - 60px); }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header-bar">
        <div class="d-flex align-items-center gap-3">
            <img src="https://www.gov.br/dnit/pt-br/central-de-conteudos/publicacoes/manual-de-gestao-da-marca/marcas-dnit/assinaturas-e-marcas/monocromatica-dnit-extenso.png" alt="DNIT" class="logo-dnit">
            <span class="sistema-titulo"><i class="bi bi-bar-chart-fill"></i> Power BI - SISPRO</span>
        </div>
        <a href="index" class="btn-voltar"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>

    <!-- Iframe do Power BI -->
    <div class="iframe-container">
        <iframe src="https://app.powerbi.com/view?r=eyJrIjoiNmY0YTdkM2QtYTQxNi00NTRhLTk3OTUtNmVmYzk3ZTU0NGU3IiwidCI6IjEwNTk1NzEyLWE3YTEtNDQ0YS1iM2E4LWU1MzFjYTMxN2M4MCJ9" allowfullscreen></iframe>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>