<?php
require_once 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$usuario_nome = $_SESSION['usuario_nome'] ?? 'Usuário';
$usuario_nivel = $_SESSION['usuario_nivel'] ?? 'usuario';

// Filtros
$filtroUF = $_GET['uf'] ?? '';
$filtroBR = $_GET['br'] ?? '';
$filtroStatusCronograma = $_GET['status_cronograma'] ?? '';
$filtroBusca = $_GET['busca'] ?? '';
$filtroVencido = $_GET['vencido'] ?? '';
$filtroNotificado = $_GET['notificado'] ?? '';

// Montar query
$where = "WHERE 1=1 AND (situacao_projeto IS NULL OR situacao_projeto != 'CONCLUÍDO')";
$params = [];

if (!empty($filtroUF)) {
    $where .= " AND uf = ?";
    $params[] = $filtroUF;
}
if (!empty($filtroBR)) {
    $where .= " AND br = ?";
    $params[] = $filtroBR;
}
if (!empty($filtroStatusCronograma)) {
    $where .= " AND situacao_cronograma = ?";
    $params[] = $filtroStatusCronograma;
}
if (!empty($filtroBusca)) {
    $where .= " AND (instrumento LIKE ? OR nome_usual LIKE ? OR empresa LIKE ? OR subtrecho LIKE ?)";
    $params[] = "%$filtroBusca%";
    $params[] = "%$filtroBusca%";
    $params[] = "%$filtroBusca%";
    $params[] = "%$filtroBusca%";
}
if ($filtroVencido === 'sim') {
    $where .= " AND data_termino_projeto_cronog < CURDATE()";
} elseif ($filtroVencido === 'nao') {
    $where .= " AND (data_termino_projeto_cronog >= CURDATE() OR data_termino_projeto_cronog IS NULL)";
}
if ($filtroNotificado === 'sim') {
    $where .= " AND notificado = 1";
} elseif ($filtroNotificado === 'nao') {
    $where .= " AND (notificado IS NULL OR notificado = 0)";
}

// Ordenar por UF (alfabético) e depois por instrumento
$sql = "SELECT id, instrumento, uf, br, lote, nome_usual, subtrecho, empresa,
               data_termino_projeto_cronog, situacao_cronograma, status_cronograma_atual,
               cronograma_sei, justificativa_cronograma, n_sei_oficio_cobranca_cronograma, paar,
               status_geral, notificado
        FROM contratos_rdci
        $where
        ORDER BY uf ASC, instrumento ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$contratos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Dados para filtros
$ufs = $pdo->query("SELECT DISTINCT uf FROM contratos_rdci WHERE uf IS NOT NULL AND uf != '' ORDER BY uf")->fetchAll(PDO::FETCH_COLUMN);
$brs = $pdo->query("SELECT DISTINCT br FROM contratos_rdci WHERE br IS NOT NULL AND br != '' ORDER BY br")->fetchAll(PDO::FETCH_COLUMN);
$statusCronogramaList = $pdo->query("SELECT DISTINCT situacao_cronograma FROM contratos_rdci WHERE situacao_cronograma IS NOT NULL AND situacao_cronograma != '' ORDER BY situacao_cronograma")->fetchAll(PDO::FETCH_COLUMN);

function getStatusVencimento($dataCronograma) {
    if (empty($dataCronograma)) {
        return ['status' => 'Sem data', 'badge' => 'semdata'];
    }
    $hoje = new DateTime();
    $data = new DateTime($dataCronograma);
    if ($data < $hoje) {
        $dias = $hoje->diff($data)->days;
        return ['status' => "Vencido há $dias dias", 'badge' => 'vencido'];
    } else {
        $dias = $data->diff($hoje)->days;
        if ($dias <= 30) {
            return ['status' => "A vencer em $dias dias", 'badge' => 'proximo'];
        }
        return ['status' => "No prazo ($dias dias)", 'badge' => 'prazo'];
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Notificações RDCI - SISPROC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fc; }
        .container-fluid { max-width: 98%; }
        .card { border: none; border-radius: 16px; box-shadow: 0 8px 30px rgba(0,0,0,0.08); background: #fff; padding: 20px 25px; }
        .table th { font-weight: 600; color: #2c3e50; white-space: nowrap; }
        .table td { vertical-align: middle; }
        .logo-dnit { max-height: 50px; }
        .sistema-titulo { font-weight: 700; color: #004a8f; font-size: 1.4rem; letter-spacing: 1px; }
        .btn-sm { padding: 0.25rem 0.6rem; }
        .filtros .form-select, .filtros .form-control { font-size: 0.9rem; }
        .table-responsive { overflow-x: auto; }

        /* Badges de vencimento (cores) */
        .vencido-badge { background: #dc3545; color: #fff; padding: 6px 12px; border-radius: 20px; font-weight: 500; white-space: nowrap; }
        .proximo-badge { background: #ffc107; color: #000; padding: 6px 12px; border-radius: 20px; font-weight: 500; white-space: nowrap; }
        .prazo-badge { background: #198754; color: #fff; padding: 6px 12px; border-radius: 20px; font-weight: 500; white-space: nowrap; }
        .semdata-badge { background: #6c757d; color: #fff; padding: 6px 12px; border-radius: 20px; font-weight: 500; white-space: nowrap; }

        .notificado-badge { background: #0d6efd; color: #fff; padding: 6px 12px; border-radius: 20px; font-weight: 500; }
        .pendente-badge { background: #ffc107; color: #000; padding: 6px 12px; border-radius: 20px; font-weight: 500; }

        /* Coluna Contrato mais larga */
        .col-contrato { min-width: 180px; word-break: break-word; }

        /* Links SEI */
        .sei-link { cursor: pointer; color: #0d6efd; text-decoration: underline; }
        .sei-link:hover { color: #0a58ca; }

        @media (max-width: 768px) { .table-responsive { font-size: 0.85rem; } }
        .detalhe-item { padding: 4px 0; border-bottom: 1px solid #eee; }
        .detalhe-item:last-child { border-bottom: none; }
        .modal-body { max-height: 70vh; overflow-y: auto; }

        /* Toast de notificação */
        #toastCopiado {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0,0,0,0.8);
            color: #fff;
            padding: 10px 24px;
            border-radius: 30px;
            font-weight: 500;
            font-size: 0.95rem;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
            z-index: 9999;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            pointer-events: none;
        }
        #toastCopiado.show {
            opacity: 1;
            visibility: visible;
        }
        #toastCopiado i {
            margin-right: 8px;
        }
    </style>
</head>
<body>
<div class="container-fluid mt-4">
    <div class="card">
        <!-- Cabeçalho -->
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap">
            <div class="d-flex align-items-center">
                <img src="https://www.gov.br/dnit/pt-br/central-de-conteudos/publicacoes/manual-de-gestao-da-marca/marcas-dnit/assinaturas-e-marcas/monocromatica-dnit-extenso.png" alt="DNIT" class="logo-dnit me-3">
                <span class="sistema-titulo"><i class="bi bi-bell"></i> Notificações RDCI - Cronogramas</span>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary btn-sm" id="btnAtualizar"><i class="bi bi-arrow-repeat"></i> Atualizar Dados</button>
                <a href="index.php" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left"></i> Voltar</a>
            </div>
        </div>

        <!-- Filtros -->
        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-2">
                <label class="form-label">UF</label>
                <select name="uf" class="form-select" onchange="this.form.submit();">
                    <option value="">Todas</option>
                    <?php foreach ($ufs as $uf): ?>
                        <option value="<?= htmlspecialchars($uf) ?>" <?= ($filtroUF == $uf) ? 'selected' : '' ?>><?= htmlspecialchars($uf) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">BR</label>
                <select name="br" class="form-select" onchange="this.form.submit();">
                    <option value="">Todas</option>
                    <?php foreach ($brs as $br): ?>
                        <option value="<?= htmlspecialchars($br) ?>" <?= ($filtroBR == $br) ? 'selected' : '' ?>><?= htmlspecialchars($br) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status Cronograma</label>
                <select name="status_cronograma" class="form-select" onchange="this.form.submit();">
                    <option value="">Todos</option>
                    <?php foreach ($statusCronogramaList as $s): ?>
                        <option value="<?= htmlspecialchars($s) ?>" <?= ($filtroStatusCronograma == $s) ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Vencido</label>
                <select name="vencido" class="form-select" onchange="this.form.submit();">
                    <option value="">Todos</option>
                    <option value="sim" <?= ($filtroVencido == 'sim') ? 'selected' : '' ?>>Vencidos</option>
                    <option value="nao" <?= ($filtroVencido == 'nao') ? 'selected' : '' ?>>Não vencidos</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Notificado</label>
                <select name="notificado" class="form-select" onchange="this.form.submit();">
                    <option value="">Todos</option>
                    <option value="sim" <?= ($filtroNotificado == 'sim') ? 'selected' : '' ?>>Notificados</option>
                    <option value="nao" <?= ($filtroNotificado == 'nao') ? 'selected' : '' ?>>Pendentes</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Buscar</label>
                <input type="text" name="busca" class="form-control" value="<?= htmlspecialchars($filtroBusca) ?>" placeholder="Contrato, obra..." onchange="this.form.submit();">
            </div>
            <div class="col-12">
                <a href="notificacoes_rdci.php" class="btn btn-secondary">Limpar</a>
            </div>
        </form>

        <!-- Tabela -->
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th class="col-contrato">Contrato</th>
                        <th>BR</th>
                        <th>UF</th>
                        <th>Lote</th>
                        <th>Nome Usual</th>
                        <th>Subtrecho</th>
                        <th>Data Cronograma</th>
                        <th>Vencimento</th>
                        <th>SEI Cronograma</th>
                        <th>Notificado</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($contratos)): ?>
                        <tr><td colspan="11" class="text-center text-muted py-4">Nenhum contrato encontrado.</td></tr>
                    <?php else: ?>
                        <?php foreach ($contratos as $c): 
                            $venc = getStatusVencimento($c['data_termino_projeto_cronog']);
                            $dataCron = $c['data_termino_projeto_cronog'] ? date('d/m/Y', strtotime($c['data_termino_projeto_cronog'])) : '-';
                            $notificado = $c['notificado'] ?? 0;
                            $seiCron = $c['cronograma_sei'] ?? '';
                        ?>
                        <tr>
                            <td class="col-contrato"><strong><?= htmlspecialchars($c['instrumento'] ?? '') ?></strong></td>
                            <td><?= htmlspecialchars($c['br'] ?? '') ?></td>
                            <td><?= htmlspecialchars($c['uf'] ?? '') ?></td>
                            <td><?= htmlspecialchars($c['lote'] ?? '') ?></td>
                            <td><?= htmlspecialchars($c['nome_usual'] ?? '') ?></td>
                            <td><?= htmlspecialchars($c['subtrecho'] ?? '') ?></td>
                            <td class="text-nowrap"><?= $dataCron ?></td>
                            <td><span class="<?= $venc['badge'] ?>-badge"><?= $venc['status'] ?></span></td>
                            <td>
                                <?php if (!empty($seiCron)): ?>
                                    <span class="sei-link" onclick="copiarTexto('<?= htmlspecialchars($seiCron) ?>')"><?= htmlspecialchars($seiCron) ?></span>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= $notificado ? 'notificado-badge' : 'pendente-badge' ?>">
                                    <?= $notificado ? 'Notificado' : 'Pendente' ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-info" onclick="verDetalhes(<?= $c['id'] ?>)"><i class="bi bi-eye"></i></button>
                                <button class="btn btn-sm btn-outline-<?= $notificado ? 'warning' : 'success' ?>" onclick="toggleNotificado(<?= $c['id'] ?>, <?= $notificado ? 0 : 1 ?>)">
                                    <i class="bi bi-<?= $notificado ? 'clock' : 'check-circle' ?>"></i>
                                    <?= $notificado ? 'Desmarcar' : 'Marcar' ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="text-muted small mt-2">Total: <?= count($contratos) ?> contratos ativos (excluídos concluídos)</div>
    </div>
</div>

<!-- Toast de notificação -->
<div id="toastCopiado"><i class="bi bi-check-circle-fill text-success"></i> SEI copiado!</div>

<!-- Modal de Detalhes -->
<div class="modal fade" id="modalDetalhes" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalhes do Cronograma</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detalhesConteudo">
                <p class="text-muted">Carregando...</p>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ============================================================
// ATUALIZAR DADOS
// ============================================================
document.getElementById('btnAtualizar').addEventListener('click', function() {
    if (!confirm('Atualizar dados do RDCI?')) return;
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Atualizando...';
    $.ajax({
        url: 'importar_rdci_api.php',
        method: 'GET',
        success: function(data) {
            alert('Dados atualizados!');
            location.reload();
        },
        error: function() {
            alert('Erro ao atualizar.');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Atualizar Dados';
        }
    });
});

// ============================================================
// VER DETALHES
// ============================================================
function verDetalhes(id) {
    $.ajax({
        url: 'ajax_rdci.php',
        method: 'GET',
        data: { action: 'detalhes', id: id },
        dataType: 'json',
        success: function(data) {
            if (data.error) { alert(data.error); return; }
            const campos = [
                { label: 'Contrato', key: 'instrumento' },
                { label: 'BR', key: 'br' },
                { label: 'UF', key: 'uf' },
                { label: 'Lote', key: 'lote' },
                { label: 'Nome Usual', key: 'nome_usual' },
                { label: 'Subtrecho', key: 'subtrecho' },
                { label: 'Situação do Cronograma', key: 'situacao_cronograma' },
                { label: 'Data do Cronograma', key: 'data_termino_projeto_cronog' },
                { label: 'Cronograma SEI', key: 'cronograma_sei' },
                { label: 'Justificativa do Cronograma', key: 'justificativa_cronograma' },
                { label: 'Ofício de Notificação', key: 'n_sei_oficio_cobranca_cronograma' },
                { label: 'PAAR', key: 'paar' }
            ];
            let html = '<div class="row">';
            campos.forEach(campo => {
                let valor = data[campo.key] || '—';
                if (campo.key === 'data_termino_projeto_cronog' && valor !== '—') {
                    const d = new Date(valor);
                    if (!isNaN(d)) valor = d.toLocaleDateString('pt-BR');
                }
                html += `<div class="col-md-6 detalhe-item"><strong>${campo.label}:</strong> ${valor}</div>`;
            });
            html += '</div>';
            document.getElementById('detalhesConteudo').innerHTML = html;
            new bootstrap.Modal(document.getElementById('modalDetalhes')).show();
        },
        error: function() { alert('Erro ao carregar detalhes.'); }
    });
}

// ============================================================
// TOGGLE NOTIFICADO
// ============================================================
function toggleNotificado(id, novoStatus) {
    $.ajax({
        url: 'ajax_rdci.php',
        method: 'POST',
        data: { action: 'toggle_notificado', id: id, status: novoStatus },
        dataType: 'json',
        success: function(res) {
            if (res.success) {
                location.reload();
            } else {
                alert('Erro: ' + (res.error || 'Desconhecido'));
            }
        },
        error: function() { alert('Erro de comunicação.'); }
    });
}

// ============================================================
// COPIAR SEI (com toast)
// ============================================================
var toastTimer = null;

function copiarTexto(texto) {
    if (!texto || texto === '—') return;
    // Copia para a área de transferência
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(texto).then(function() {
            mostrarToast('SEI copiado!');
        }).catch(function() {
            copiarFallback(texto);
        });
    } else {
        copiarFallback(texto);
    }
}

function copiarFallback(texto) {
    var textarea = document.createElement('textarea');
    textarea.value = texto;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    try {
        document.execCommand('copy');
        mostrarToast('SEI copiado!');
    } catch (e) {}
    document.body.removeChild(textarea);
}

function mostrarToast(mensagem) {
    var toast = document.getElementById('toastCopiado');
    if (!toast) return;
    toast.textContent = mensagem;
    toast.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function() {
        toast.classList.remove('show');
    }, 1500);
}
</script>
</body>
</html>