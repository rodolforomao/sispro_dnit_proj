<?php
require_once 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$usuario_nome = $_SESSION['usuario_nome'] ?? 'Usuário';
$usuario_nivel = $_SESSION['usuario_nivel'] ?? 'usuario';

// Filtros
$filtro_instrumento = $_GET['instrumento'] ?? '';
$filtro_uf = $_GET['uf'] ?? '';
$filtro_br = $_GET['br'] ?? '';
$filtro_status_geral = $_GET['status_geral'] ?? '';
$filtro_situacao_cronograma = $_GET['situacao_cronograma'] ?? '';
$filtro_empresa = $_GET['empresa'] ?? '';
$limit = (int)($_GET['limit'] ?? 50);
$offset = (int)($_GET['offset'] ?? 0);

// Montar WHERE
$where = "WHERE 1=1";
$params = [];
if (!empty($filtro_instrumento)) {
    $where .= " AND instrumento LIKE ?";
    $params[] = "%$filtro_instrumento%";
}
if (!empty($filtro_uf)) {
    $where .= " AND uf = ?";
    $params[] = $filtro_uf;
}
if (!empty($filtro_br)) {
    $where .= " AND br = ?";
    $params[] = $filtro_br;
}
if (!empty($filtro_status_geral)) {
    $where .= " AND status_geral = ?";
    $params[] = $filtro_status_geral;
}
if (!empty($filtro_situacao_cronograma)) {
    $where .= " AND situacao_cronograma = ?";
    $params[] = $filtro_situacao_cronograma;
}
if (!empty($filtro_empresa)) {
    $where .= " AND empresa LIKE ?";
    $params[] = "%$filtro_empresa%";
}

// Contar total
$stmt = $pdo->prepare("SELECT COUNT(*) FROM contratos_rdci $where");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

// Buscar dados
$sql = "SELECT id, instrumento, uf, br, regiao, lote, nome_usual, empresa, supervisora, 
               fase, situacao_contrato_siac, situacao_projeto, 
               data_termino_vigencia, data_termino_servico,
               status_geral, situacao_cronograma, status_cronograma_atual,
               projeto_basico_finalizado, obra_iniciada, 
               valor_pi_a_r, valor_projetos, data_atualizacao
        FROM contratos_rdci $where
        ORDER BY instrumento ASC
        LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar valores para filtros (distintos)
$ufs = $pdo->query("SELECT DISTINCT uf FROM contratos_rdci WHERE uf IS NOT NULL AND uf != '' ORDER BY uf")->fetchAll(PDO::FETCH_COLUMN);
$brs = $pdo->query("SELECT DISTINCT br FROM contratos_rdci WHERE br IS NOT NULL AND br != '' ORDER BY br")->fetchAll(PDO::FETCH_COLUMN);
$statusGerais = $pdo->query("SELECT DISTINCT status_geral FROM contratos_rdci WHERE status_geral IS NOT NULL AND status_geral != '' ORDER BY status_geral")->fetchAll(PDO::FETCH_COLUMN);
$situacoesCronograma = $pdo->query("SELECT DISTINCT situacao_cronograma FROM contratos_rdci WHERE situacao_cronograma IS NOT NULL AND situacao_cronograma != '' ORDER BY situacao_cronograma")->fetchAll(PDO::FETCH_COLUMN);

// Função para formatar status com cores
function getStatusBadge($status) {
    $status = strtoupper($status);
    if (str_contains($status, 'VERDE')) return '<span class="badge bg-success">' . htmlspecialchars($status) . '</span>';
    if (str_contains($status, 'AMARELO') || str_contains($status, 'AMAREL')) return '<span class="badge bg-warning text-dark">' . htmlspecialchars($status) . '</span>';
    if (str_contains($status, 'VERMELHO') || str_contains($status, 'VERMEL')) return '<span class="badge bg-danger">' . htmlspecialchars($status) . '</span>';
    return '<span class="badge bg-secondary">' . htmlspecialchars($status) . '</span>';
}

$totalPaginas = ceil($total / $limit);
$paginaAtual = floor($offset / $limit) + 1;
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Contratos RDCI - SISPROC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fc; }
        .container-fluid { max-width: 98%; padding: 0 15px; }
        .card { border: none; border-radius: 16px; box-shadow: 0 8px 30px rgba(0,0,0,0.08); background: #fff; padding: 20px 25px; margin-bottom: 16px; }
        .table th { font-weight: 600; color: #2c3e50; white-space: nowrap; }
        .table td { vertical-align: middle; }
        .table-responsive { overflow-x: auto; }
        .logo-dnit { max-height: 50px; }
        .sistema-titulo { font-weight: 700; color: #004a8f; font-size: 1.4rem; letter-spacing: 1px; }
        .filtros .form-select, .filtros .form-control { font-size: 0.9rem; }
        .badge-status { padding: 6px 12px; border-radius: 20px; font-weight: 500; }
        @media (max-width: 768px) { .table-responsive { font-size: 0.85rem; } }
    </style>
</head>
<body>
<div class="container-fluid mt-4">
    <div class="card">
        <!-- Cabeçalho -->
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap">
            <div class="d-flex align-items-center">
                <img src="https://www.gov.br/dnit/pt-br/central-de-conteudos/publicacoes/manual-de-gestao-da-marca/marcas-dnit/assinaturas-e-marcas/monocromatica-dnit-extenso.png" alt="DNIT" class="logo-dnit me-3">
                <span class="sistema-titulo"><i class="bi bi-table"></i> Contratos RDCI</span>
            </div>
            <div class="d-flex gap-2">
                <a href="importar_contratos_rdci.php" class="btn btn-outline-primary btn-sm" target="_blank"><i class="bi bi-arrow-repeat"></i> Atualizar</a>
                <a href="index.php" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left"></i> Voltar</a>
            </div>
        </div>

        <!-- Filtros -->
        <form method="GET" class="row g-2 mb-3 filtros">
            <div class="col-md-2">
                <input type="text" name="instrumento" class="form-control" placeholder="Contrato" value="<?= htmlspecialchars($filtro_instrumento) ?>">
            </div>
            <div class="col-md-1">
                <select name="uf" class="form-select">
                    <option value="">UF</option>
                    <?php foreach ($ufs as $uf): ?>
                        <option value="<?= htmlspecialchars($uf) ?>" <?= $filtro_uf == $uf ? 'selected' : '' ?>><?= htmlspecialchars($uf) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1">
                <select name="br" class="form-select">
                    <option value="">BR</option>
                    <?php foreach ($brs as $br): ?>
                        <option value="<?= htmlspecialchars($br) ?>" <?= $filtro_br == $br ? 'selected' : '' ?>><?= htmlspecialchars($br) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status_geral" class="form-select">
                    <option value="">Status Geral</option>
                    <?php foreach ($statusGerais as $s): ?>
                        <option value="<?= htmlspecialchars($s) ?>" <?= $filtro_status_geral == $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="situacao_cronograma" class="form-select">
                    <option value="">Cronograma</option>
                    <?php foreach ($situacoesCronograma as $s): ?>
                        <option value="<?= htmlspecialchars($s) ?>" <?= $filtro_situacao_cronograma == $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <input type="text" name="empresa" class="form-control" placeholder="Empresa" value="<?= htmlspecialchars($filtro_empresa) ?>">
            </div>
            <div class="col-md-1">
                <select name="limit" class="form-select">
                    <option value="20" <?= $limit==20?'selected':'' ?>>20</option>
                    <option value="50" <?= $limit==50?'selected':'' ?>>50</option>
                    <option value="100" <?= $limit==100?'selected':'' ?>>100</option>
                    <option value="500" <?= $limit==500?'selected':'' ?>>500</option>
                </select>
            </div>
            <div class="col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search"></i></button>
                <a href="contratos_rdci.php" class="btn btn-secondary btn-sm w-100"><i class="bi bi-eraser"></i></a>
            </div>
        </form>

        <!-- Tabela -->
        <div class="table-responsive">
            <table class="table table-hover align-middle table-sm">
                <thead>
                    <tr>
                        <th>Contrato</th>
                        <th>UF</th>
                        <th>BR</th>
                        <th>Empresa</th>
                        <th>Fase</th>
                        <th>Status Geral</th>
                        <th>Cronograma</th>
                        <th>Término Vigência</th>
                        <th>Valor PI+A+R</th>
                        <th>Valor Projetos</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($registros)): ?>
                        <tr><td colspan="11" class="text-center text-muted py-4">Nenhum registro encontrado.</td></tr>
                    <?php else: ?>
                        <?php foreach ($registros as $r): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($r['instrumento']) ?></strong></td>
                            <td><?= htmlspecialchars($r['uf']) ?></td>
                            <td><?= htmlspecialchars($r['br']) ?></td>
                            <td><?= htmlspecialchars($r['empresa']) ?></td>
                            <td><?= htmlspecialchars($r['fase']) ?></td>
                            <td><?= getStatusBadge($r['status_geral']) ?></td>
                            <td><?= getStatusBadge($r['situacao_cronograma']) ?></td>
                            <td><?= $r['data_termino_vigencia'] ? date('d/m/Y', strtotime($r['data_termino_vigencia'])) : '-' ?></td>
                            <td><?= $r['valor_pi_a_r'] ? 'R$ ' . number_format($r['valor_pi_a_r'], 2, ',', '.') : '-' ?></td>
                            <td><?= $r['valor_projetos'] ? 'R$ ' . number_format($r['valor_projetos'], 2, ',', '.') : '-' ?></td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-outline-info" onclick="verDetalhes(<?= $r['id'] ?>)"><i class="bi bi-eye"></i></button>
                                <a href="editar_contrato_rdci.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginação -->
        <?php if ($total > $limit): ?>
        <div class="d-flex justify-content-between align-items-center mt-3">
            <span>Total: <?= $total ?> registros</span>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php for ($p = 1; $p <= $totalPaginas; $p++): ?>
                        <li class="page-item <?= $p == $paginaAtual ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['offset' => ($p-1)*$limit, 'limit' => $limit])) ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Detalhes -->
<div class="modal fade" id="modalDetalhes" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalhes do Contrato</h5>
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
function verDetalhes(id) {
    $.ajax({
        url: 'ajax_contratos_rdci.php',
        method: 'GET',
        data: { action: 'detalhes', id: id },
        dataType: 'json',
        success: function(data) {
            if (data.error) {
                document.getElementById('detalhesConteudo').innerHTML = '<p class="text-danger">' + data.error + '</p>';
                return;
            }
            let html = '<div class="row">';
            // Campos principais em colunas
            const campos = [
                ['Instrumento', 'instrumento'],
                ['UF', 'uf'],
                ['BR', 'br'],
                ['Região', 'regiao'],
                ['Lote', 'lote'],
                ['Nome Usual', 'nome_usual'],
                ['ID PAC', 'n_id_pac'],
                ['Subtrecho', 'subtrecho'],
                ['Empresa', 'empresa'],
                ['Supervisora', 'supervisora'],
                ['Processo Base', 'processo_base'],
                ['Processo Projeto', 'processo_projeto'],
                ['Fase', 'fase'],
                ['Data Ordem Início Projeto', 'data_ordem_inicio_projeto'],
                ['Data Ordem Início Obra', 'data_ordem_inicio_obra'],
                ['Data Término Serviço', 'data_termino_servico'],
                ['Data Término Vigência', 'data_termino_vigencia'],
                ['Edital', 'edital'],
                ['Situação Contrato (SIAC)', 'situacao_contrato_siac'],
                ['Situação Projeto', 'situacao_projeto'],
                ['KM Inicial', 'km_inicial'],
                ['KM Final', 'km_final'],
                ['Extensão', 'extensao'],
                ['Status Geral', 'status_geral'],
                ['Status Contrato (Síntese)', 'status_contrato_sintese'],
                ['Cronograma SEI', 'cronograma_sei'],
                ['Situação Cronograma', 'situacao_cronograma'],
                ['Status Cronograma Atual', 'status_cronograma_atual'],
                ['Projeto Básico Finalizado', 'projeto_basico_finalizado'],
                ['Obra Iniciada', 'obra_iniciada'],
                ['Valor PI+A+R', 'valor_pi_a_r'],
                ['Valor Projetos', 'valor_projetos'],
                ['Data Atualização', 'data_atualizacao']
            ];
            let campos2 = [];
            for (let i = 0; i < campos.length; i++) {
                const [label, key] = campos[i];
                const val = data[key] || '-';
                const display = (key.includes('data') && val !== '-') ? new Date(val).toLocaleDateString('pt-BR') : val;
                campos2.push({ label, value: display, key });
            }
            // Dividir em duas colunas
            const mid = Math.ceil(campos2.length / 2);
            const col1 = campos2.slice(0, mid);
            const col2 = campos2.slice(mid);
            html += '<div class="col-md-6"><dl class="row">';
            col1.forEach(c => {
                html += `<dt class="col-sm-5">${c.label}</dt><dd class="col-sm-7">${c.value}</dd>`;
            });
            html += '</dl></div><div class="col-md-6"><dl class="row">';
            col2.forEach(c => {
                html += `<dt class="col-sm-5">${c.label}</dt><dd class="col-sm-7">${c.value}</dd>`;
            });
            html += '</dl></div>';
            html += '</div>';
            
            // Objeto do contrato
            html += '<hr><h6>Objeto do Contrato</h6><p class="text-muted small">' + (data.objeto_contrato || '-') + '</p>';
            
            // Análise e Observações
            html += '<hr><h6>Análise</h6><p class="text-muted small">' + (data.analise || '-') + '</p>';
            html += '<hr><h6>Observações</h6><p class="text-muted small">' + (data.observacoes || '-') + '</p>';
            
            document.getElementById('detalhesConteudo').innerHTML = html;
            new bootstrap.Modal(document.getElementById('modalDetalhes')).show();
        },
        error: function() {
            document.getElementById('detalhesConteudo').innerHTML = '<p class="text-danger">Erro ao carregar detalhes.</p>';
        }
    });
}
</script>
</body>
</html>