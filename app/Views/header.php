<?php
// header.php - Barra superior fixa (idêntica à home)
// Este arquivo deve ser incluído em todas as views, logo após o <body>.
// Ele obtém automaticamente os dados da sessão e do banco.

// Função para obter iniciais (usada no avatar)
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

// Obtém dados da sessão
$usuario_nome = $_SESSION['usuario_nome'] ?? 'Usuário';
$usuario_nivel = $_SESSION['usuario_nivel'] ?? 'usuario';
$usuario_id = $_SESSION['usuario_id'] ?? 0;
$setor_slug = $_SESSION['setor_slug'] ?? 'assessoria-projetos';
$setor_nome = $setor_slug === 'assessoria-projetos' ? 'Assessoria e Projetos' : 'Atlas/Monitoramento';

$isAdmin = in_array($usuario_nivel, ['desenvolvedor', 'admin']);

// Contar pendentes (para o badge)
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

// Quantos setores o usuário possui (para exibir "Trocar Setor")
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

// Setores do usuário (para exibir no dropdown)
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

// Se o usuário não tem setores, exibe uma mensagem
if (empty($setores_usuario)) {
    $setores_usuario[] = 'Nenhum setor associado';
}
?>
<!-- ============================================================
     BARRA SUPERIOR FIXA (MESMA DA HOME)
     ============================================================ -->
<header class="topbar">
    <div class="topbar-left">
        <img src="https://www.gov.br/dnit/pt-br/central-de-conteudos/publicacoes/manual-de-gestao-da-marca/marcas-dnit/assinaturas-e-marcas/monocromatica-dnit-simples.png" alt="DNIT" class="logo-dnit">
        <span class="sistema-titulo"><i class="bi bi-diagram-3"></i> SISPRO</span>
    </div>
    <div class="topbar-center">
        <span class="badge badge-setor"><i class="bi bi-map"></i> <?= htmlspecialchars($setor_nome) ?></span>
    </div>
    <div class="topbar-right">
        <!-- Relógio, Data, Temperatura e Umidade (Brasília) -->
        <div class="info-clima">
            <span id="dataHora" class="d-none d-md-inline me-2" style="font-size:0.9rem; color:#6c757d;">
                <i class="bi bi-calendar3 ms-2"></i> <span id="dataDisplay">--/--/----</span>
                <i class="bi bi-clock"></i> <span id="clockDisplay">00:00:00</span>
            </span>
            <span id="climaInfo" class="d-none d-md-inline" style="font-size:0.9rem; color:#6c757d;">
                <i class="bi bi-geo-alt"></i> Brasília/DF
                <i class="bi bi-thermometer-half ms-1"></i> <span id="tempDisplay">--°C</span>
                <i class="bi bi-droplet-half ms-1"></i> <span id="umidDisplay">--%</span>
            </span>
        </div>

        <!-- Ferramentas -->
        <div class="dropdown">
            <button class="btn-icon" id="dropdownApps" data-bs-toggle="dropdown" aria-expanded="false" title="Ferramentas">
                <i class="bi bi-grid-3x3-gap-fill"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownApps" style="min-width: 190px;">
                <li><a class="dropdown-item" href="notificacoes_rdci"><i class="bi bi-bell"></i> Notificações RDCI</a></li>
                <li><a class="dropdown-item" href="links"><i class="bi bi-link-45deg"></i> Links</a></li>
                <li><a class="dropdown-item" href="rdci"><i class="bi bi-table"></i> RDCI</a></li>
                <li><a class="dropdown-item" href="modelos"><i class="bi bi-files"></i> Modelos</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="#" id="btnExportarCSV"><i class="bi bi-download"></i> Exportar CSV</a></li>
                <!-- Admin removido daqui (está no menu avatar) -->
            </ul>
        </div>

        <!-- Avatar / Minha Conta -->
        <div class="dropdown">
            <button class="btn-avatar" id="dropdownUser" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="avatar-icon"><?= htmlspecialchars(obterIniciais($usuario_nome)) ?></span>
                <?php if ($pendentes > 0): ?>
                    <span class="badge-notificacao"><?= $pendentes ?></span>
                <?php endif; ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end dropdown-menu-avatar" aria-labelledby="dropdownUser">
                <li class="dropdown-header">
                    <?= htmlspecialchars($usuario_nome) ?>
                    <small>
                        <?php if (!empty($setores_usuario)): ?>
                            Setores: <?= htmlspecialchars(implode(', ', $setores_usuario)) ?>
                        <?php else: ?>
                            Nenhum setor associado
                        <?php endif; ?>
                    </small>
                </li>
                <li><hr class="dropdown-divider"></li>
                <?php if ($isAdmin): ?>
                    <li>
                        <a class="dropdown-item" href="admin">
                            <i class="bi bi-people"></i> Admin
                            <?php if ($pendentes > 0): ?>
                                <span class="badge bg-danger ms-2"><?= $pendentes ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li><a class="dropdown-item" href="gerencial"><i class="bi bi-graph-up-arrow"></i> Gerencial</a></li>
                <?php endif; ?>
                <?php if ($total_setores > 1): ?>
                    <li><a class="dropdown-item" href="trocar_setor"><i class="bi bi-arrow-left-right"></i> Trocar Setor</a></li>
                <?php endif; ?>
                <li><a class="dropdown-item" href="alterar_senha"><i class="bi bi-key"></i> Alterar Senha</a></li>
                <li><a class="dropdown-item text-danger" href="logout"><i class="bi bi-box-arrow-right"></i> Sair</a></li>
            </ul>
        </div>
    </div>
</header>

<style>
/* ============================================================
   ESTILOS DA BARRA SUPERIOR (DEVEM ESTAR EM TODAS AS PÁGINAS)
   ============================================================ */
.topbar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1030;
    background: #ffffff;
    border-bottom: 1px solid #dce1e8;
    padding: 8px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    min-height: 60px;
}
.topbar-left { display: flex; align-items: center; gap: 10px; }
.topbar-left .logo-dnit { max-height: 40px; width: auto; }
.topbar-left .sistema-titulo { font-weight: 700; color: #004a8f; font-size: 1.2rem; letter-spacing: 1px; }
.topbar-center { flex: 1; text-align: center; }
.topbar-center .badge-setor { background: #17a2b8; color: #fff; font-size: 0.9rem; padding: 6px 14px; }
.topbar-right { display: flex; align-items: center; gap: 10px; }
.topbar-right .info-clima { display: flex; align-items: center; gap: 6px; margin-right: 6px; }
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

body { padding-top: 70px; }

/* Para telas menores, esconde o widget de clima */
@media (max-width: 768px) {
    .info-clima { display: none !important; }
}
</style>

<script>
// ============================================================
// RELÓGIO, DATA, TEMPERATURA E UMIDADE (Brasília/DF)
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    // Atualiza relógio e data a cada segundo
    function atualizarRelogio() {
        const agora = new Date();
        const horas = String(agora.getHours()).padStart(2, '0');
        const minutos = String(agora.getMinutes()).padStart(2, '0');
        const segundos = String(agora.getSeconds()).padStart(2, '0');
        document.getElementById('clockDisplay').textContent = horas + ':' + minutos + ':' + segundos;

        const dia = String(agora.getDate()).padStart(2, '0');
        const mes = String(agora.getMonth() + 1).padStart(2, '0');
        const ano = agora.getFullYear();
        document.getElementById('dataDisplay').textContent = dia + '/' + mes + '/' + ano;
    }

    atualizarRelogio();
    setInterval(atualizarRelogio, 1000);

    // ============================================================
    // TEMPERATURA E UMIDADE DE BRASÍLIA (via Open-Meteo)
    // ============================================================
    function buscarClima() {
        // Verifica cache (válido por 10 minutos)
        const cache = localStorage.getItem('climaBrasilia');
        const cacheTime = localStorage.getItem('climaBrasiliaTime');
        const agora = Date.now();

        if (cache && cacheTime && (agora - parseInt(cacheTime)) < 600000) {
            const dados = JSON.parse(cache);
            document.getElementById('tempDisplay').textContent = dados.temp + '°C';
            document.getElementById('umidDisplay').textContent = dados.umid + '%';
            return;
        }

        // URL com temperatura e umidade (usando o novo endpoint 'current')
        const url = 'https://api.open-meteo.com/v1/forecast?latitude=-15.7939&longitude=-47.8828&current=temperature_2m,relative_humidity_2m';

        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.current) {
                    const temp = Math.round(data.current.temperature_2m);
                    const umid = Math.round(data.current.relative_humidity_2m);
                    document.getElementById('tempDisplay').textContent = temp + '°C';
                    document.getElementById('umidDisplay').textContent = umid + '%';
                    // Salva em cache
                    const cacheObj = { temp: temp, umid: umid };
                    localStorage.setItem('climaBrasilia', JSON.stringify(cacheObj));
                    localStorage.setItem('climaBrasiliaTime', String(agora));
                } else {
                    document.getElementById('tempDisplay').textContent = '--°C';
                    document.getElementById('umidDisplay').textContent = '--%';
                }
            })
            .catch(() => {
                document.getElementById('tempDisplay').textContent = '--°C';
                document.getElementById('umidDisplay').textContent = '--%';
            });
    }

    buscarClima();
    // Atualiza a cada 10 minutos
    setInterval(buscarClima, 600000);
});
</script>