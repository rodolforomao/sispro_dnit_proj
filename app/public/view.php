<?php
// View view.php - Visualização de um processo
// Variáveis disponíveis: $processo, $comentarios
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Visualizar Processo - SISPROC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fc; }
        .container { max-width: 1000px; }
        .card { border: none; border-radius: 16px; box-shadow: 0 8px 30px rgba(0,0,0,0.08); background: #fff; padding: 25px 30px; }
        .label { font-weight: 600; color: #2c3e50; }
        .value { color: #333; }
        .badge-status { padding: 6px 12px; border-radius: 20px; font-weight: 500; }
        .badge-status-em-elaboracao { background: #FFA500; color: #000; }
        .badge-status-elaborado { background: #006400; color: #fff; }
        .badge-status-revisado { background: #000; color: #fff; }
        .badge-status-aguardar { background: #0000FF; color: #fff; }
        .badge-status-assinado { background: #000; color: #fff; }
        .badge-status-marilia { background: #800080; color: #fff; }
        .badge-status-concluido { background: #000; color: #fff; }
        .comentario-item { border-bottom: 1px solid #dee2e6; padding: 12px 0; }
        .comentario-item:last-child { border-bottom: none; }
        .comentario-usuario { color: #004a8f; font-weight: 700; }
        .comentario-data { color: #6c757d; font-size: 0.85rem; }
        .logo-dnit { max-height: 50px; }
        .sistema-titulo { font-weight: 700; color: #004a8f; font-size: 1.4rem; letter-spacing: 1px; }
        @media (max-width: 768px) { .container { padding: 10px; } .card { padding: 15px; } }
    </style>
</head>
<body>
<div class="container mt-4">
    <div class="card">
        <!-- Cabeçalho -->
        <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap">
            <div class="d-flex align-items-center">
                <img src="https://www.gov.br/dnit/pt-br/central-de-conteudos/publicacoes/manual-de-gestao-da-marca/marcas-dnit/assinaturas-e-marcas/monocromatica-dnit-extenso.png" alt="DNIT" class="logo-dnit me-3">
                <span class="sistema-titulo"><i class="bi bi-diagram-3"></i> Sistema de Processos - SISPROC</span>
            </div>
            <div>
                <a href="index" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
                <a href="edit?id=<?= $processo['id'] ?>" class="btn btn-primary"><i class="bi bi-pencil"></i> Editar</a>
                <?php if (!empty($processo['contrato_id'])): ?>
                    <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#modalInfoContrato" id="btnInfoContratoView" data-contrato-id="<?= $processo['contrato_id'] ?>">
                        <i class="bi bi-info-circle"></i> Info Contrato
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <h2 class="mb-4" style="font-weight: 700; color: #2c3e50;">Detalhes do Processo</h2>

        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <span class="label">Nº Processo:</span>
                    <span class="value"><?= $processo['numero_processo'] ? htmlspecialchars($processo['numero_processo']) : '-' ?></span>
                </div>
                <div class="mb-3">
                    <span class="label">Assunto:</span>
                    <span class="value"><?= htmlspecialchars($processo['assunto'] ?? '-') ?></span>
                </div>
                <div class="mb-3">
                    <span class="label">Equipe:</span>
                    <span class="value"><?= htmlspecialchars($processo['equipe_nome'] ?? '-') ?></span>
                </div>
                <div class="mb-3">
                    <span class="label">Responsável:</span>
                    <span class="value"><?= htmlspecialchars($processo['responsavel_nome'] ?? '-') ?></span>
                </div>
                <div class="mb-3">
                    <span class="label">Status:</span>
                    <?php
                        $statusNome = $processo['status_nome'] ?? '-';
                        if ($statusNome !== '-') {
                            $statusClass = 'badge-status-' . strtolower(str_replace(' ', '-', $statusNome));
                            if (!in_array($statusNome, ['Em elaboração', 'Elaborado', 'Revisado', 'Aguardar', 'Assinado', 'Marília', 'Concluído'])) $statusClass = 'bg-secondary';
                            echo '<span class="badge-status ' . $statusClass . '">' . htmlspecialchars($statusNome) . '</span>';
                        } else {
                            echo '<span class="value">-</span>';
                        }
                    ?>
                </div>
                <div class="mb-3">
                    <span class="label">Tipo:</span>
                    <span class="value"><?= htmlspecialchars($processo['tipo_nome'] ?? '-') ?></span>
                </div>
                <div class="mb-3">
                    <span class="label">Data Entrada:</span>
                    <span class="value"><?= $processo['data_entrada'] ? date('d/m/Y', strtotime($processo['data_entrada'])) : '-' ?></span>
                </div>
                <div class="mb-3">
                    <span class="label">Prazo:</span>
                    <span class="value"><?= $processo['prazo'] ? date('d/m/Y', strtotime($processo['prazo'])) : '-' ?></span>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <span class="label">Contrato:</span>
                    <span class="value"><?= htmlspecialchars($processo['contrato_num'] ?? '-') ?></span>
                </div>
                <div class="mb-3">
                    <span class="label">UF:</span>
                    <span class="value"><?= htmlspecialchars($processo['uf'] ?? '-') ?></span>
                </div>
                <div class="mb-3">
                    <span class="label">BR:</span>
                    <span class="value"><?= htmlspecialchars($processo['br'] ?? '-') ?></span>
                </div>
                <div class="mb-3">
                    <span class="label">SEI Recebido:</span>
                    <span class="value"><?= htmlspecialchars($processo['sei_recebido'] ?? '-') ?></span>
                </div>
                <div class="mb-3">
                    <span class="label">SEI Criado 1:</span>
                    <span class="value"><?= htmlspecialchars($processo['sei_criado_1'] ?? '-') ?></span>
                </div>
                <div class="mb-3">
                    <span class="label">SEI Criado 2:</span>
                    <span class="value"><?= htmlspecialchars($processo['sei_criado_2'] ?? '-') ?></span>
                </div>
                <div class="mb-3">
                    <span class="label">SEI Criado 3:</span>
                    <span class="value"><?= htmlspecialchars($processo['sei_criado_3'] ?? '-') ?></span>
                </div>
                <div class="mb-3">
                    <span class="label">Data Revisão:</span>
                    <span class="value"><?= $processo['data_revisao'] ? date('d/m/Y', strtotime($processo['data_revisao'])) : '-' ?></span>
                </div>
                <div class="mb-3">
                    <span class="label">Data Assinatura:</span>
                    <span class="value"><?= $processo['data_assinatura'] ? date('d/m/Y', strtotime($processo['data_assinatura'])) : '-' ?></span>
                </div>
                <div class="mb-3">
                    <span class="label">Tem Prazo?</span>
                    <span class="value"><?= $processo['tem_prazo'] ? 'Sim' : 'Não' ?></span>
                </div>
                <div class="mb-3">
                    <span class="label">Cadastrado no SIMA?</span>
                    <span class="value"><?= $processo['cadastrado_sima'] ? 'Sim' : 'Não' ?></span>
                </div>
            </div>
        </div>

        <div class="row mt-3">
            <div class="col-12">
                <div class="mb-3">
                    <span class="label">Providência:</span>
                    <p class="value border p-2 bg-light rounded"><?= nl2br(htmlspecialchars($processo['providencia'] ?? '-')) ?></p>
                </div>
                <div class="mb-3">
                    <span class="label">Observações:</span>
                    <p class="value border p-2 bg-light rounded"><?= nl2br(htmlspecialchars($processo['observacoes'] ?? '-')) ?></p>
                </div>
            </div>
        </div>

        <!-- Comentários -->
        <div class="mt-4">
            <h5><i class="bi bi-chat-dots"></i> Histórico de Comentários</h5>
            <?php if (empty($comentarios)): ?>
                <p class="text-muted">Nenhum comentário.</p>
            <?php else: ?>
                <?php foreach ($comentarios as $c): ?>
                    <div class="comentario-item">
                        <strong class="comentario-usuario"><?= htmlspecialchars($c['usuario_nome'] ?? 'Usuário') ?></strong>
                        <span class="comentario-data"> - <?= date('d/m/Y H:i', strtotime($c['created_at'])) ?></span>
                        <p class="mb-0 mt-1"><?= nl2br(htmlspecialchars($c['comentario'] ?? '')) ?></p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Info Contrato -->
<div class="modal fade" id="modalInfoContrato" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-file-earmark-text"></i> Informações do Contrato</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="infoContratoBody">
                <p class="text-muted">Selecione um contrato para ver as informações.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    // Info Contrato na view
    $('#modalInfoContrato').on('show.bs.modal', function(event) {
        var button = $(event.relatedTarget);
        var contratoId = button.data('contrato-id');
        if (!contratoId) {
            $('#infoContratoBody').html('<p class="text-muted">Selecione um contrato.</p>');
            return;
        }
        $('#infoContratoBody').html('<p class="text-muted">Carregando...</p>');
        $.ajax({
            url: 'get_contrato_info?contrato_id=' + contratoId,
            method: 'GET',
            dataType: 'json',
            success: function(data) {
                if (data.error) {
                    $('#infoContratoBody').html('<p class="text-danger">' + data.error + '</p>');
                    return;
                }
                var html = '<div class="row">';
                var campos = [
                    { label: 'Empresa', key: 'empresa' },
                    { label: 'Objeto', key: 'objeto_contrato' },
                    { label: 'Processo base', key: 'processo_base' },
                    { label: 'Processo Projetos', key: 'processo_projeto' },
                    { label: 'Edital', key: 'edital' },
                    { label: 'Análise', key: 'analise' },
                    { label: 'SEI Delegação', key: 'sei_delegacao' },
                    { label: 'Status Cronograma', key: 'situacao_cronograma' },
                    { label: 'SEI Notificação', key: 'n_sei_oficio_cobranca_cronograma' },
                    { label: 'Data Término do Cronograma', key: 'data_termino_projeto_cronog' }
                ];
                campos.forEach(function(campo) {
                    var valor = data[campo.key] || '-';
                    html += '<div class="col-md-6"><strong>' + campo.label + ':</strong> ' + valor + '</div>';
                });
                html += '</div>';
                $('#infoContratoBody').html(html);
            },
            error: function() {
                $('#infoContratoBody').html('<p class="text-danger">Erro ao carregar informações.</p>');
            }
        });
    });
});
</script>
<?php include APP_PATH . '/public/chat_widget.php'; ?>
</body>
</html>