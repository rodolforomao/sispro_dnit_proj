<?php
// View rdci.php - Página RDCI
// Variáveis esperadas: $contratos, $ufs, $brs, $statusGerais, $situacoesCronograma, $usuario_nome, $usuario_nivel
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>RDCI - Contratos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fc; }
        .container-fluid { max-width: 98%; }
        .card { border: none; border-radius: 16px; box-shadow: 0 8px 30px rgba(0,0,0,0.08); background: #fff; padding: 20px 25px; }
        .table th { font-weight: 600; color: #2c3e50; white-space: nowrap; }
        .table td { vertical-align: middle; }
        .badge-status { padding: 6px 12px; border-radius: 20px; font-weight: 500; }
        .logo-dnit { max-height: 50px; }
        .sistema-titulo { font-weight: 700; color: #004a8f; font-size: 1.4rem; letter-spacing: 1px; }
        .btn-sm { padding: 0.25rem 0.6rem; }
        .filtros .form-select, .filtros .form-control { font-size: 0.9rem; }
        .table-responsive { overflow-x: auto; }
        @media (max-width: 768px) { .table-responsive { font-size: 0.85rem; } }
        .info-count { font-size: 0.9rem; color: #6c757d; margin-top: 0.5rem; }
        .info-count strong { color: #2c3e50; }
    </style>
</head>
<body>
<div class="container-fluid mt-4">
    <div class="card">
        <!-- Cabeçalho -->
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap">
            <div class="d-flex align-items-center">
                <img src="https://www.gov.br/dnit/pt-br/central-de-conteudos/publicacoes/manual-de-gestao-da-marca/marcas-dnit/assinaturas-e-marcas/monocromatica-dnit-extenso.png" alt="DNIT" class="logo-dnit me-3">
                <span class="sistema-titulo"><i class="bi bi-diagram-3"></i> RDCI - Contratos</span>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary btn-sm" onclick="atualizarDados()"><i class="bi bi-arrow-repeat"></i> Atualizar Dados</button>
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
                        <option value="<?= htmlspecialchars($uf ?? '') ?>" <?= ($_GET['uf'] ?? '') == $uf ? 'selected' : '' ?>><?= htmlspecialchars($uf ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">BR</label>
                <select name="br" class="form-select" onchange="this.form.submit();">
                    <option value="">Todas</option>
                    <?php foreach ($brs as $br): ?>
                        <option value="<?= htmlspecialchars($br ?? '') ?>" <?= ($_GET['br'] ?? '') == $br ? 'selected' : '' ?>><?= htmlspecialchars($br ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status Geral</label>
                <select name="status" class="form-select" onchange="this.form.submit();">
                    <option value="">Todos</option>
                    <?php foreach ($statusGerais as $s): ?>
                        <option value="<?= htmlspecialchars($s ?? '') ?>" <?= ($_GET['status'] ?? '') == $s ? 'selected' : '' ?>><?= htmlspecialchars($s ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Situação Cronograma</label>
                <select name="cronograma" class="form-select" onchange="this.form.submit();">
                    <option value="">Todas</option>
                    <?php foreach ($situacoesCronograma as $s): ?>
                        <option value="<?= htmlspecialchars($s ?? '') ?>" <?= ($_GET['cronograma'] ?? '') == $s ? 'selected' : '' ?>><?= htmlspecialchars($s ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Buscar</label>
                <input type="text" name="busca" class="form-control" value="<?= htmlspecialchars($_GET['busca'] ?? '') ?>" placeholder="Contrato, obra, empresa..." onchange="this.form.submit();">
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <a href="rdci.php" class="btn btn-secondary w-100">Limpar</a>
            </div>
        </form>

        <!-- Contagem abaixo dos filtros -->
        <?php
        // Ordenar contratos por UF (alfabética)
        usort($contratos, function($a, $b) {
            return strcmp($a['uf'] ?? '', $b['uf'] ?? '');
        });
        $totalContratos = count($contratos);
        ?>
        <div class="info-count">
            <i class="bi bi-list-ul"></i> <strong>Total de contratos:</strong> <?= $totalContratos ?> &nbsp;|&nbsp; <strong>Registros exibidos:</strong> <?= $totalContratos ?>
        </div>

        <!-- Tabela -->
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Contrato</th>
                        <th>UF</th>
                        <th>BR</th>
                        <th>Obra</th>
                        <th>Empresa</th>
                        <th>Status</th>
                        <th>Fase</th>
                        <th>Início Obra</th>
                        <th>Término Vigência</th>
                        <th>Cronograma</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($contratos)): ?>
                        <tr><td colspan="11" class="text-center text-muted py-4">Nenhum contrato encontrado.</td></tr>
                    <?php else: ?>
                        <?php foreach ($contratos as $c): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($c['instrumento'] ?? '') ?></strong></td>
                            <td><?= htmlspecialchars($c['uf'] ?? '') ?></td>
                            <td><?= htmlspecialchars($c['br'] ?? '') ?></td>
                            <td><?= htmlspecialchars($c['nome_usual'] ?? '') ?></td>
                            <td><?= htmlspecialchars($c['empresa'] ?? '') ?></td>
                            <td>
                                <?php
                                $status = $c['status_geral'] ?? '—';
                                $badgeClass = 'secondary';
                                if ((strpos($status, 'Verde') !== false)) $badgeClass = 'success';
                                elseif ((strpos($status, 'Amarelo') !== false)) $badgeClass = 'warning';
                                elseif ((strpos($status, 'Vermelho') !== false)) $badgeClass = 'danger';
                                ?>
                                <span class="badge bg-<?= $badgeClass ?>"><?= htmlspecialchars($status) ?></span>
                            </td>
                            <td><?= htmlspecialchars($c['fase'] ?? '') ?></td>
                            <td><?= $c['data_ordem_inicio_obra'] ? date('d/m/Y', strtotime($c['data_ordem_inicio_obra'])) : '-' ?></td>
                            <td><?= $c['data_termino_vigencia'] ? date('d/m/Y', strtotime($c['data_termino_vigencia'])) : '-' ?></td>
                            <td><?= htmlspecialchars($c['situacao_cronograma'] ?? '') ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-info" onclick="verDetalhes(<?= $c['id'] ?>)"><i class="bi bi-eye"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal de Detalhes -->
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
function atualizarDados() {
    if (!confirm('Atualizar dados do RDCI?')) return;
    const btn = event.target;
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
}

function verDetalhes(id) {
    $.ajax({
        url: 'ajax_rdci.php',
        method: 'GET',
        data: { action: 'detalhes', id: id },
        dataType: 'json',
        success: function(data) {
            if (data.error) { alert(data.error); return; }
            let html = '<div class="row">';
            const campos = [
                ['Instrumento', 'instrumento'],
                ['UF', 'uf'],
                ['BR', 'br'],
                ['Região', 'regiao'],
                ['Lote', 'lote'],
                ['Nome Usual', 'nome_usual'],
                ['Subtrecho', 'subtrecho'],
                ['Empresa', 'empresa'],
                ['Supervisora', 'supervisora'],
                ['Processo base', 'processo_base'],
                ['Processo Projetos', 'processo_projeto'],
                ['Fase', 'fase'],
                ['Data Ordem Início Projeto', 'data_ordem_inicio_projeto'],
                ['Data Ordem Início Obra', 'data_ordem_inicio_obra'],
                ['Data Término Serviço', 'data_termino_servico'],
                ['Data Término Vigência', 'data_termino_vigencia'],
                ['Edital', 'edital'],
                ['Situação Contrato (SIAC)', 'situacao_contrato_siac'],
                ['Situação Projeto', 'situacao_projeto'],
                ['Status Geral', 'status_geral'],
                ['Status Contrato (Síntese)', 'status_contrato_sintese'],
                ['Cronograma SEI', 'cronograma_sei'],
                ['Situação Cronograma', 'situacao_cronograma'],
                ['Status Cronograma Atual', 'status_cronograma_atual'],
                ['Justificativa Cronograma', 'justificativa_cronograma'],
                ['Processo de Notificação SR/UF', 'processo_notificacao_sr_uf'],
                ['Data Última Notificação', 'data_ultima_notificacao'],
                ['SEI Ofício Cobrança Cronograma', 'n_sei_oficio_cobranca_cronograma'],
                ['PAAR', 'paar'],
                ['Valor PI+A+R', 'valor_pi_a_r'],
                ['Valor Projetos', 'valor_projetos'],
                ['Data Atualização', 'data_atualizacao']
            ];
            let count = 0;
            let htmlEsquerda = '<div class="col-md-6">';
            let htmlDireita = '<div class="col-md-6">';
            campos.forEach(([label, key]) => {
                let valor = data[key] ?? '—';
                if (key.includes('data') && valor !== '—' && !isNaN(Date.parse(valor))) {
                    const d = new Date(valor);
                    if (!isNaN(d)) valor = d.toLocaleDateString('pt-BR');
                }
                const item = `<div class="mb-2"><strong>${label}:</strong> ${valor}</div>`;
                if (count % 2 === 0) htmlEsquerda += item;
                else htmlDireita += item;
                count++;
            });
            htmlEsquerda += '</div>';
            htmlDireita += '</div>';
            html = '<div class="row">' + htmlEsquerda + htmlDireita + '</div>';
            document.getElementById('detalhesConteudo').innerHTML = html;
            new bootstrap.Modal(document.getElementById('modalDetalhes')).show();
        },
        error: function() { alert('Erro ao carregar detalhes.'); }
    });
}
</script>
<?php include APP_PATH . '/public/chat_widget.php'; ?>
<?php include APP_PATH . '/Views/header.php'; ?>
</body>
</html>