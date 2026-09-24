<?php
// View contratos_rdci.php
// Variáveis esperadas: $registros, $ufs, $brs, $statusGerais, $situacoesCronograma, $total, $totalPaginas, $paginaAtual, $filtros

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

// Variáveis da sessão
$usuario_nome = $usuario_nome ?? $_SESSION['usuario_nome'] ?? 'Usuário';
$usuario_nivel = $usuario_nivel ?? $_SESSION['usuario_nivel'] ?? 'usuario';
$usuario_id = $usuario_id ?? $_SESSION['usuario_id'] ?? 0;
$setor_slug = $_SESSION['setor_slug'] ?? 'assessoria-projetos';
$setor_nome = $setor_slug === 'assessoria-projetos' ? 'Assessoria e Projetos' : 'Atlas/Monitoramento';

$isAdmin = in_array($usuario_nivel, ['desenvolvedor', 'admin']);

$pendentes = 0;
if ($isAdmin) {
    try {
        global $pdo;
        $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE status = 'pendente'");
        $pendentes = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        $pendentes = 0;
    }
}

$total_setores = 0;
if ($usuario_id) {
    try {
        global $pdo;
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuario_setor WHERE usuario_id = ?");
        $stmt->execute([$usuario_id]);
        $total_setores = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        $total_setores = 0;
    }
}

$setores_usuario = [];
if ($usuario_id) {
    try {
        global $pdo;
        $stmt = $pdo->prepare("
            SELECT s.nome 
            FROM setores s 
            INNER JOIN usuario_setor us ON s.id = us.setor_id 
            WHERE us.usuario_id = ?
        ");
        $stmt->execute([$usuario_id]);
        $setores_usuario = $stmt->fetchAll(PDO::FETCH_COLUMN);
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
    <title>Contratos RDCI - SISPRO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fc; padding-top: 70px; }
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
        .topbar-left .logo-dnit { max-height: 40px; width: auto; }
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
     CONTEÚDO PRINCIPAL (mantido igual ao original)
     ============================================================ -->
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
                <input type="text" name="instrumento" class="form-control" placeholder="Contrato" value="<?= htmlspecialchars($filtros['instrumento'] ?? '') ?>">
            </div>
            <div class="col-md-1">
                <select name="uf" class="form-select">
                    <option value="">UF</option>
                    <?php foreach ($ufs as $uf): ?>
                        <option value="<?= htmlspecialchars($uf) ?>" <?= ($filtros['uf'] ?? '') == $uf ? 'selected' : '' ?>><?= htmlspecialchars($uf) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1">
                <select name="br" class="form-select">
                    <option value="">BR</option>
                    <?php foreach ($brs as $br): ?>
                        <option value="<?= htmlspecialchars($br) ?>" <?= ($filtros['br'] ?? '') == $br ? 'selected' : '' ?>><?= htmlspecialchars($br) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status_geral" class="form-select">
                    <option value="">Status Geral</option>
                    <?php foreach ($statusGerais as $s): ?>
                        <option value="<?= htmlspecialchars($s) ?>" <?= ($filtros['status_geral'] ?? '') == $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="situacao_cronograma" class="form-select">
                    <option value="">Cronograma</option>
                    <?php foreach ($situacoesCronograma as $s): ?>
                        <option value="<?= htmlspecialchars($s) ?>" <?= ($filtros['situacao_cronograma'] ?? '') == $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <input type="text" name="empresa" class="form-control" placeholder="Empresa" value="<?= htmlspecialchars($filtros['empresa'] ?? '') ?>">
            </div>
            <div class="col-md-1">
                <select name="limit" class="form-select">
                    <option value="20" <?= ($filtros['limit'] ?? 50) == 20 ? 'selected' : '' ?>>20</option>
                    <option value="50" <?= ($filtros['limit'] ?? 50) == 50 ? 'selected' : '' ?>>50</option>
                    <option value="100" <?= ($filtros['limit'] ?? 50) == 100 ? 'selected' : '' ?>>100</option>
                    <option value="500" <?= ($filtros['limit'] ?? 50) == 500 ? 'selected' : '' ?>>500</option>
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
                            <td>
                                <?php
                                $status = $r['status_geral'] ?? '';
                                $badgeClass = 'secondary';
                                if ((strpos($status, 'Verde') !== false)) $badgeClass = 'success';
                                elseif ((strpos($status, 'Amarelo') !== false)) $badgeClass = 'warning';
                                elseif ((strpos($status, 'Vermelho') !== false)) $badgeClass = 'danger';
                                ?>
                                <span class="badge bg-<?= $badgeClass ?>"><?= htmlspecialchars($status) ?></span>
                            </td>
                            <td><?= htmlspecialchars($r['situacao_cronograma']) ?></td>
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
        <?php if ($total > ($filtros['limit'] ?? 50)): ?>
        <div class="d-flex justify-content-between align-items-center mt-3">
            <span>Total: <?= $total ?> registros</span>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php for ($p = 1; $p <= $totalPaginas; $p++): ?>
                        <li class="page-item <?= $p == $paginaAtual ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($filtros, ['offset' => ($p-1)*($filtros['limit'] ?? 50)])) ?>"><?= $p ?></a>
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
            html += '<hr><h6>Objeto do Contrato</h6><p class="text-muted small">' + (data.objeto_contrato || '-') + '</p>';
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