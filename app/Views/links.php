<?php
// links.php - Página com links úteis (acesso para todos os usuários logados)

// --- BLOCO DE DEFINIÇÃO DE VARIÁVEIS (IGUAL À HOME) ---
if (!function_exists('obterIniciais')) {
    function obterIniciais($nome) {
        $partes = explode(' ', trim($nome));
        $iniciais = '';
        foreach ($partes as $parte) {
            if (!empty($parte)) {
                $iniciais .= strtoupper(substr($parte, 0, 1));
            }
        }
        return substr($iniciais, 0, 2);
    }
}

$usuario_nome  = $_SESSION['usuario_nome']  ?? 'Usuário';
$usuario_nivel = $_SESSION['usuario_nivel'] ?? 'usuario';
$usuario_id    = $_SESSION['usuario_id']    ?? 0;
$setor_slug    = $_SESSION['setor_slug']    ?? 'assessoria-projetos';
$setor_nome    = $setor_slug === 'assessoria-projetos' ? 'Assessoria e Projetos' : 'Atlas/Monitoramento';

$isAdmin = in_array($usuario_nivel, ['desenvolvedor', 'admin']);

$pendentes = 0;
if ($isAdmin) {
    try {
        global $pdo;
        if (isset($pdo)) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE status = 'pendente'");
            $pendentes = (int)$stmt->fetchColumn();
        }
    } catch (Exception $e) {
        $pendentes = 0;
    }
}

$total_setores = 0;
if ($usuario_id) {
    try {
        global $pdo;
        if (isset($pdo)) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuario_setor WHERE usuario_id = ?");
            $stmt->execute([$usuario_id]);
            $total_setores = (int)$stmt->fetchColumn();
        }
    } catch (Exception $e) {
        $total_setores = 0;
    }
}

$setores_usuario = [];
if ($usuario_id) {
    try {
        global $pdo;
        if (isset($pdo)) {
            $stmt = $pdo->prepare("
                SELECT s.nome 
                FROM setores s 
                INNER JOIN usuario_setor us ON s.id = us.setor_id 
                WHERE us.usuario_id = ?
            ");
            $stmt->execute([$usuario_id]);
            $setores_usuario = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
    } catch (Exception $e) {
        $setores_usuario = [];
    }
}
// --- FIM DO BLOCO ---
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Links Úteis - SISPRO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fc; padding-top: 70px; }
        .container { max-width: 1200px; }
        .card { border: none; border-radius: 16px; box-shadow: 0 8px 30px rgba(0,0,0,0.08); background: #fff; padding: 25px 30px; margin-bottom: 20px; }
        .btn-voltar { background: #6c757d; color: #fff; border: none; padding: 8px 20px; border-radius: 8px; font-weight: 600; }
        .btn-voltar:hover { background: #5a6268; color: #fff; }
        .btn-link-externo { background: #0d6efd; color: #fff; border: none; padding: 10px 25px; border-radius: 8px; font-weight: 600; }
        .btn-link-externo:hover { background: #0b5ed7; color: #fff; }
        .iframe-container { width: 100%; height: 700px; border: 1px solid #dee2e6; border-radius: 8px; overflow: hidden; }
        .iframe-container iframe { width: 100%; height: 100%; border: none; }
        .sistema-titulo { font-weight: 700; color: #004a8f; font-size: 1.4rem; letter-spacing: 1px; }
        .btn-group-links { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px; margin-bottom: 20px; }
        .btn-group-links .btn { flex: 0 1 auto; }
        @media (max-width: 768px) {
            .iframe-container { height: 400px; }
            .btn-group-links .btn { flex: 1 1 100%; }
        }
        .dica-lentidao {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 12px 18px;
            border-radius: 6px;
            margin-top: 15px;
            font-size: 0.9rem;
        }

        /* ============================================================
           BARRA SUPERIOR FIXA (IGUAL À HOME)
           ============================================================ */
        .topbar {
            position: fixed; top: 0; left: 0; right: 0; z-index: 1030;
            background: #ffffff; border-bottom: 1px solid #dce1e8;
            padding: 8px 20px; display: flex; align-items: center;
            justify-content: space-between; flex-wrap: wrap;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); min-height: 60px;
        }
        .topbar-left { display: flex; align-items: center; gap: 10px; }
        .topbar-left .sistema-titulo { font-weight: 700; color: #004a8f; font-size: 1.2rem; letter-spacing: 1px; }
        .topbar-center { flex: 1; text-align: center; }
        .topbar-center .badge-setor { background: #17a2b8; color: #fff; font-size: 0.9rem; padding: 6px 14px; }
        .topbar-right { display: flex; align-items: center; gap: 10px; }
        .topbar-right .btn-icon { background: none; border: none; font-size: 1.5rem; color: #6c757d; padding: 0 6px; cursor: pointer; transition: color 0.2s; }
        .topbar-right .btn-icon:hover { color: #004a8f; }
        .topbar-right .btn-avatar { position: relative; background: none; border: none; padding: 0; cursor: pointer; }
        .topbar-right .btn-avatar .avatar-icon { width: 38px; height: 38px; border-radius: 50%; background: #0d6efd; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 1rem; text-transform: uppercase; }
        .topbar-right .btn-avatar .badge-notificacao { position: absolute; top: -4px; right: -4px; background-color: #dc3545; color: #fff; border-radius: 50%; padding: 2px 6px; font-size: 0.65rem; font-weight: 700; line-height: 1; border: 2px solid #fff; min-width: 18px; text-align: center; }
        .dropdown-menu-avatar { min-width: 220px; padding: 8px 0; }
        .dropdown-menu-avatar .dropdown-item { padding: 10px 20px; font-size: 0.95rem; display: flex; align-items: center; gap: 10px; }
        .dropdown-menu-avatar .dropdown-item i { font-size: 1.2rem; width: 24px; text-align: center; }
        .dropdown-menu-avatar .dropdown-header { padding: 10px 20px; font-weight: 600; color: #2c3e50; border-bottom: 1px solid #dce1e8; margin-bottom: 4px; }
        .dropdown-menu-avatar .dropdown-header small { display: block; font-weight: 400; font-size: 0.85rem; color: #6c757d; margin-top: 2px; }
    </style>
</head>
<?php include APP_PATH . '/public/chat_widget.php'; ?>
<body>

<?php include APP_PATH . '/Views/header.php'; ?>
<!-- ============================================================
     CONTEÚDO PRINCIPAL
     ============================================================ -->
<div class="container mt-4">
    <div class="card">
        <!-- Cabeçalho -->
        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap">
            <div class="d-flex align-items-center">
                <span class="sistema-titulo"><i class="bi bi-link-45deg"></i> Links Úteis - SISPRO</span>
            </div>
            <div>
                <a href="index" class="btn btn-voltar btn-sm"><i class="bi bi-arrow-left"></i> Voltar</a>
            </div>
        </div>

        <!-- Links externos (acima do BI) -->
        <div class="mt-2">
            <h5><i class="bi bi-box-arrow-up-right text-success"></i> Links Externos</h5>
            <p class="text-muted">Clique nos botões abaixo para abrir os links em uma nova aba.</p>
            <div class="btn-group-links">
                <a href="https://supra.dnit.gov.br/index_cgcont_common.php/cgcont/cib/dataList?dataset_id=30" target="_blank" class="btn btn-link-externo">
                    <i class="bi bi-file-earmark-pdf"></i> SUPRA - Endereço Empresas
                </a>
                <a href="https://supra.dnit.gov.br/index_cgcont_common.php/cgcont/cib/dataList?dataset_id=26" target="_blank" class="btn btn-link-externo">
                    <i class="bi bi-database"></i> SUPRA - Projetos RDCI
                </a>
                <a href="https://supra.dnit.gov.br/index_cgcont_common.php/cgcont/atlasCgcontInterno" target="_blank" class="btn btn-link-externo">
                    <i class="bi bi-map"></i> SUPRA - Atlas Interno
                </a>
                <a href="https://supra.dnit.gov.br/index_cgcont_common.php/cgcont/cib/dataList?dataset_id=36" target="_blank" class="btn btn-link-externo">
                    <i class="bi bi-hdd-stack"></i> Banco de dados Atlas
                </a>
            </div>
        </div>

        <!-- Power BI -->
        <h4 class="mt-3"><i class="bi bi-bar-chart-fill text-primary"></i> Power BI - Painel de Gestão de Projetos de Contratos RDCI</h4>
        <p class="text-muted">Painel interativo para acompanhamento dos projetos.</p>
        <div class="iframe-container">
            <iframe src="https://app.powerbi.com/view?r=eyJrIjoiNmY0YTdkM2QtYTQxNi00NTRhLTk3OTUtNmVmYzk3ZTU0NGU3IiwidCI6IjEwNTk1NzEyLWE3YTEtNDQ0YS1iM2E4LWU1MzFjYTMxN2M4MCJ9" allowfullscreen loading="lazy"></iframe>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>