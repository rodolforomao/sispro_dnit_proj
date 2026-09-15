<?php
// View view.php - Visualização de um processo
// Variáveis: $processo, $comentarios, $usuario_nivel, $usuario_nome

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

$usuario_nome = $usuario_nome ?? $_SESSION['usuario_nome'] ?? 'Usuário';
$usuario_nivel = $usuario_nivel ?? $_SESSION['usuario_nivel'] ?? 'usuario';
$usuario_id = $_SESSION['usuario_id'] ?? 0;
$setor_slug = $_SESSION['setor_slug'] ?? 'assessoria-projetos';
$setor_nome = $setor_slug === 'assessoria-projetos' ? 'Assessoria e Projetos' : 'Atlas/Monitoramento';

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
    <title>Visualizar Processo - SISPRO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fc; padding-top: 70px; }
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
<div class="container mt-4">
    <div class="card">
        <!-- Cabeçalho -->
        <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap">
            <div class="d-flex align-items-center">
                <img src="https://www.gov.br/dnit/pt-br/central-de-conteudos/publicacoes/manual-de-gestao-da-marca/marcas-dnit/assinaturas-e-marcas/monocromatica-dnit-extenso.png" alt="DNIT" class="logo-dnit me-3">
                <span class="sistema-titulo"><i class="bi bi-diagram-3"></i> Sistema de Processos - SISPRO</span>
            </div>
            <div>
                <a href="index" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left"></i> Voltar</a>
                <?php if ($usuario_nivel !== 'leitor'): ?>
                    <a href="edit?id=<?= $processo['id'] ?>" class="btn btn-primary btn-sm"><i class="bi bi-pencil"></i> Editar</a>
                <?php endif; ?>
                <?php if (!empty($processo['contrato_id'])): ?>
                    <button type="button" class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#modalInfoContrato" data-contrato-id="<?= $processo['contrato_id'] ?>">
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
                    <span class="value"><?= ($processo['cadastrado_sima'] ?? 0) ? 'Sim' : 'Não' ?></span>
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
                <p class="text-muted">Carregando informações...</p>
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
    $('#modalInfoContrato').on('show.bs.modal', function(event) {
        var button = $(event.relatedTarget);
        var contratoId = button.data('contrato-id');
        if (!contratoId) {
            $('#infoContratoBody').html('<p class="text-muted">Selecione um contrato.</p>');
            return;
        }
        $('#infoContratoBody').html('<p class="text-muted">Carregando...</p>');
        $.ajax({
            url: 'index.php?url=get_contrato_info&contrato_id=' + contratoId,
            method: 'GET',
            dataType: 'json',
            success: function(data) {
                if (data.error) {
                    $('#infoContratoBody').html('<p class="text-danger">' + data.error + '</p>');
                    return;
                }
                var campos = [
                    { label: 'Empresa', key: 'empresa' },
                    { label: 'Endereço empresa', key: 'endereco_empresa' },
                    { label: 'Objeto', key: 'objeto_contrato' },
                    { label: 'Processo base', key: 'processo_base' },
                    { label: 'Processo Projetos', key: 'processo_projeto' },
                    { label: 'Edital', key: 'edital' },
                    { label: 'Análise', key: 'analise' },
                    { label: 'SEI Delegação', key: 'sei_delegacao' },
                    { label: 'Status Cronograma', key: 'situacao_cronograma' },
                    { label: 'SEI Notificação', key: 'n_sei_oficio_cobranca_cronograma' },
                    { label: 'Data Última Notificação', key: 'data_ultima_notificacao' },
                    { label: 'Data Término do Cronograma', key: 'data_termino_projeto_cronog' }
                ];
                var html = '<div class="row">';
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