<?php
// View contratos_rdci.php

if (!function_exists('obterIniciais')) {
    function obterIniciais($nome) {
        $partes = explode(' ', trim($nome)); $ini = '';
        foreach ($partes as $p) { if (!empty($p)) $ini .= strtoupper(substr($p, 0, 1)); }
        return substr($ini, 0, 2);
    }
}

if (!function_exists('obterUltimaSincronizacao')) {
    function obterUltimaSincronizacao() {
        $base = defined('APP_PATH') ? APP_PATH : dirname(__DIR__, 2);
        $arq  = $base . '/public/.sync_ultima_execucao';
        if (file_exists($arq)) return date('d/m/Y H:i', filemtime($arq));
        $log = $base . '/public/importacao_cron.log';
        if (file_exists($log)) return date('d/m/Y H:i', filemtime($log));
        return '—';
    }
}

$usuario_nome  = $usuario_nome  ?? $_SESSION['usuario_nome']  ?? 'Usuário';
$usuario_nivel = $usuario_nivel ?? $_SESSION['usuario_nivel'] ?? 'usuario';
$usuario_id    = $usuario_id    ?? $_SESSION['usuario_id']    ?? 0;
$setor_slug    = $_SESSION['setor_slug'] ?? 'assessoria-projetos';
$setor_nome    = $setor_slug === 'assessoria-projetos' ? 'Assessoria e Projetos' : 'Atlas/Monitoramento';
$isAdmin       = in_array($usuario_nivel, ['desenvolvedor', 'admin']);

$pendentes = 0;
if ($isAdmin) {
    try { global $pdo; $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE status = 'pendente'"); $pendentes = (int)$stmt->fetchColumn(); }
    catch (Exception $e) { $pendentes = 0; }
}

$total_setores = 0;
if ($usuario_id) {
    try { global $pdo; $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuario_setor WHERE usuario_id = ?"); $stmt->execute([$usuario_id]); $total_setores = (int)$stmt->fetchColumn(); }
    catch (Exception $e) { $total_setores = 0; }
}

$setores_usuario = [];
if ($usuario_id) {
    try {
        global $pdo;
        $stmt = $pdo->prepare("SELECT s.nome FROM setores s INNER JOIN usuario_setor us ON s.id = us.setor_id WHERE us.usuario_id = ?");
        $stmt->execute([$usuario_id]);
        $setores_usuario = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) { $setores_usuario = []; }
}

$ultimaAtualizacao = obterUltimaSincronizacao();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Contratos RDCI - SISPRO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        body { background: #f8f9fc; padding-top: 70px; }
        .container-fluid { max-width: 98%; padding: 0 15px; }
        .card { border: none; border-radius: 16px; box-shadow: 0 8px 30px rgba(0,0,0,0.08); background: #fff; padding: 20px 25px; margin-bottom: 16px; }
        .table th { font-weight: 600; color: #2c3e50; white-space: nowrap; font-size: 0.85rem; }
        .table td { vertical-align: middle; font-size: 0.85rem; }
        .table-responsive { overflow-x: auto; }
        .sistema-titulo { font-weight: 700; color: #004a8f; font-size: 1.4rem; letter-spacing: 1px; }
        .filtros .form-select, .filtros .form-control { font-size: 0.85rem; }
        .badge-status { padding: 6px 12px; border-radius: 20px; font-weight: 500; }
        .btn-sm { padding: 0.2rem 0.5rem; font-size: 0.75rem; }

        .ultima-atualizacao {
            font-size: 0.78rem; color: #6c757d;
            background: #f1f5f9; border: 1px solid #e2e8f0;
            padding: 4px 12px; border-radius: 20px;
            display: inline-flex; align-items: center; gap: 6px; white-space: nowrap;
        }
        .ultima-atualizacao strong { color: #0d6efd; }

        .select2-container .select2-selection--single { height: 38px; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 38px; font-size: 0.85rem; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px; }
        .select2-container .select2-selection--single .select2-selection__clear { display: none; }
        .select2-container { width: 100% !important; }
    </style>
</head>
<?php include APP_PATH . '/public/chat_widget.php'; ?>
<body>
<?php include APP_PATH . '/Views/header.php'; ?>

<div class="container-fluid mt-4">
    <div class="card">
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <h2 class="sistema-titulo mb-0"><i class="bi bi-table"></i> Contratos RDCI</h2>
                <span class="ultima-atualizacao">
                    <i class="bi bi-clock-history"></i>
                    Última atualização: <strong><?= htmlspecialchars($ultimaAtualizacao) ?></strong>
                </span>
            </div>
            <div class="d-flex gap-2">
                <a href="importar_contratos_rdci.php" class="btn btn-outline-primary btn-sm" target="_blank"><i class="bi bi-arrow-repeat"></i> Atualizar</a>
                <a href="index.php" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left"></i> Voltar</a>
            </div>
        </div>

        <form method="GET" class="row g-2 mb-3 filtros" id="filtrosForm">
            <div class="col-md-2">
                <input type="text" name="instrumento" class="form-control" placeholder="Contrato" value="<?= htmlspecialchars($filtros['instrumento'] ?? '') ?>">
            </div>
            <div class="col-md-1">
                <select name="uf" class="form-select filtro-select2">
                    <option value="">UF</option>
                    <?php foreach ($ufs as $uf): ?>
                        <option value="<?= htmlspecialchars($uf) ?>" <?= ($filtros['uf'] ?? '') == $uf ? 'selected' : '' ?>><?= htmlspecialchars($uf) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1">
                <select name="br" class="form-select filtro-select2">
                    <option value="">BR</option>
                    <?php foreach ($brs as $br): ?>
                        <option value="<?= htmlspecialchars($br) ?>" <?= ($filtros['br'] ?? '') == $br ? 'selected' : '' ?>><?= htmlspecialchars($br) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status_geral" class="form-select filtro-select2">
                    <option value="">Status Geral</option>
                    <?php foreach ($statusGerais as $s): ?>
                        <option value="<?= htmlspecialchars($s) ?>" <?= ($filtros['status_geral'] ?? '') == $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="situacao_cronograma" class="form-select filtro-select2">
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
                <select name="limit" class="form-select filtro-select2">
                    <option value="20" <?= ($filtros['limit'] ?? 50) == 20 ? 'selected' : '' ?>>20</option>
                    <option value="50" <?= ($filtros['limit'] ?? 50) == 50 ? 'selected' : '' ?>>50</option>
                    <option value="100" <?= ($filtros['limit'] ?? 50) == 100 ? 'selected' : '' ?>>100</option>
                    <option value="500" <?= ($filtros['limit'] ?? 50) == 500 ? 'selected' : '' ?>>500</option>
                </select>
            </div>
            <div class="col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm w-100" style="height:38px;"><i class="bi bi-search"></i></button>
                <a href="contratos_rdci.php" class="btn btn-secondary btn-sm w-100" style="height:38px; display:flex; align-items:center; justify-content:center;"><i class="bi bi-eraser"></i></a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle table-sm">
                <thead>
                    <tr>
                        <th>Contrato</th><th>UF</th><th>BR</th><th>Empresa</th><th>Fase</th>
                        <th>Status Geral</th><th>Cronograma</th><th>Término Vigência</th>
                        <th>Valor PI+A+R</th><th>Valor Projetos</th><th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($registros)): ?>
                        <tr><td colspan="11" class="text-center text-muted py-4">Nenhum registro encontrado.</td></tr>
                    <?php else: foreach ($registros as $r): ?>
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
                                if (str_contains($status, 'Verde')) $badgeClass = 'success';
                                elseif (str_contains($status, 'Amarelo')) $badgeClass = 'warning';
                                elseif (str_contains($status, 'Vermelho')) $badgeClass = 'danger';
                                ?>
                                <span class="badge bg-<?= $badgeClass ?> badge-status"><?= htmlspecialchars($status) ?></span>
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
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total > ($filtros['limit'] ?? 50)): ?>
        <div class="d-flex justify-content-between align-items-center mt-3">
            <span class="small text-muted">Total: <?= $total ?> registros</span>
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

<div class="modal fade" id="modalDetalhes" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalhes do Contrato</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detalhesConteudo"><p class="text-muted">Carregando...</p></div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    $('.filtro-select2').select2({ placeholder: 'Selecione...', allowClear: false, width: '100%' });
    $('.filtro-select2').on('change', function() { $(this).closest('form').submit(); });
    $('.filtro-select2').on('select2:open', function() {
        setTimeout(function() { var f = document.querySelector('.select2-search__field'); if (f) f.focus(); }, 100);
    });
    $('.filtro-select2').on('focus', function() { $(this).select2('open'); });
});

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
                ['Instrumento', 'instrumento'], ['UF', 'uf'], ['BR', 'br'], ['Região', 'regiao'],
                ['Lote', 'lote'], ['Nome Usual', 'nome_usual'], ['ID PAC', 'n_id_pac'], ['Subtrecho', 'subtrecho'],
                ['Empresa', 'empresa'], ['Supervisora', 'supervisora'], ['Processo Base', 'processo_base'],
                ['Processo Projeto', 'processo_projeto'], ['Fase', 'fase'],
                ['Data Ordem Início Projeto', 'data_ordem_inicio_projeto'],
                ['Data Ordem Início Obra', 'data_ordem_inicio_obra'],
                ['Data Término Serviço', 'data_termino_servico'],
                ['Data Término Vigência', 'data_termino_vigencia'],
                ['Edital', 'edital'], ['Situação Contrato (SIAC)', 'situacao_contrato_siac'],
                ['Situação Projeto', 'situacao_projeto'], ['KM Inicial', 'km_inicial'], ['KM Final', 'km_final'],
                ['Extensão', 'extensao'], ['Status Geral', 'status_geral'],
                ['Status Contrato (Síntese)', 'status_contrato_sintese'], ['Cronograma SEI', 'cronograma_sei'],
                ['Situação Cronograma', 'situacao_cronograma'], ['Status Cronograma Atual', 'status_cronograma_atual'],
                ['Projeto Básico Finalizado', 'projeto_basico_finalizado'], ['Obra Iniciada', 'obra_iniciada'],
                ['Valor PI+A+R', 'valor_pi_a_r'], ['Valor Projetos', 'valor_projetos'], ['Data Atualização', 'data_atualizacao']
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
            col1.forEach(c => { html += `<dt class="col-sm-5">${c.label}</dt><dd class="col-sm-7">${c.value}</dd>`; });
            html += '</dl></div><div class="col-md-6"><dl class="row">';
            col2.forEach(c => { html += `<dt class="col-sm-5">${c.label}</dt><dd class="col-sm-7">${c.value}</dd>`; });
            html += '</dl></div></div>';
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