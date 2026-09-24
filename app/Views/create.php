<?php
// View create.php - Formulário de criação de processos
// Variáveis: $equipes, $responsaveis, $tipos, $statuses, $uf_lista, $contratos
// $usuario_id, $usuario_equipe_id, $usuario_nome, $usuario_nivel, $usuario_responsavel_id

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
$usuario_id = $usuario_id ?? $_SESSION['usuario_id'] ?? 0;
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

// Localizar ID do status "Em elaboração"
$status_em_elaboracao_id = null;
foreach ($statuses as $s) {
    if (strcasecmp(trim($s['nome']), 'Em elaboração') === 0) {
        $status_em_elaboracao_id = $s['id'];
        break;
    }
}
$mostrar_revisao = false;
$mostrar_assinatura = false;
$mostrar_sima = false;
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Novo Processo - SISPRO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fc; padding-top: 70px; }
        .container { max-width: 1200px; }
        .card { border: none; border-radius: 16px; box-shadow: 0 8px 30px rgba(0,0,0,0.08); background: #fff; padding: 25px 30px; }
        .form-label { font-weight: 600; color: #2c3e50; margin-bottom: 0.3rem; }
        .form-control, .form-select { border-radius: 10px; border: 1px solid #dce1e8; }
        .form-control:focus, .form-select:focus { border-color: #4a90e2; box-shadow: 0 0 0 0.2rem rgba(74,144,226,0.15); }
        .form-control:disabled, .form-select:disabled { background: #eef2f7; opacity: 0.8; }
        .btn-primary { background: #4a90e2; border: none; border-radius: 10px; padding: 10px 30px; font-weight: 600; }
        .btn-primary:hover { background: #357abd; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(74,144,226,0.3); }
        .btn-secondary { border-radius: 10px; padding: 10px 30px; }
        .checkbox-top .form-check { margin-right: 25px; }
        .checkbox-top .form-check-input { width: 1.2rem; height: 1.2rem; }
        .checkbox-top .form-check-label { font-weight: 500; }
        .help-text { font-size: 0.8rem; color: #6c757d; margin-top: 0.2rem; }
        .select2-container--default .select2-selection--single { border-radius: 10px; border: 1px solid #dce1e8; height: 40px; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 40px; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 38px; }
        .required:after { content: " *"; color: #e74c3c; }
        .card-header-icon { font-size: 1.4rem; margin-right: 10px; color: #4a90e2; }
        #divNomeUsual { margin-top: 5px; font-size: 0.9rem; color: #0d6efd; }
        .btn-clipboard { border-radius: 0 10px 10px 0; background: #f1f4f9; border-color: #dce1e8; }
        .btn-clipboard:hover { background: #e2e8f0; }
        @media (max-width: 768px) { .container { padding: 10px; } .card { padding: 15px; } }
        .spinner-border-sm { width: 1rem; height: 1rem; border-width: 0.15em; }
        .btn-loading { opacity: 0.7; pointer-events: none; }

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

<div class="container mt-4">
    <div class="card">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div class="d-flex align-items-center">
                <i class="bi bi-plus-circle-fill card-header-icon"></i>
                <h2 class="mb-0" style="font-weight: 700; color: #2c3e50;">Novo Processo - SISPRO</h2>
            </div>
            <a href="index" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left"></i> Voltar</a>
        </div>

        <form method="POST" id="formProcesso" novalidate>
            <input type="hidden" name="cadastrado_sima" value="0">

            <div class="checkbox-top mb-4 p-3 bg-light rounded-3">
                <div class="form-check form-check-inline">
                    <input type="checkbox" name="tem_prazo" class="form-check-input" id="tem_prazo">
                    <label class="form-check-label" for="tem_prazo"><i class="bi bi-clock"></i> Tem Prazo?</label>
                </div>
                <div class="form-check form-check-inline" id="div_cadastrado_sima" style="display: <?= $mostrar_sima ? 'inline-block' : 'none' ?>;">
                    <input type="checkbox" name="cadastrado_sima" class="form-check-input" id="cadastrado_sima" value="1">
                    <label class="form-check-label" for="cadastrado_sima"><i class="bi bi-check-circle"></i> Cadastrado no SIMA?</label>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label required"><i class="bi bi-people"></i> Equipe</label>
                        <select name="equipe_id" class="form-select select2">
                            <option value="">Selecione</option>
                            <?php foreach ($equipes as $e): ?>
                            <option value="<?= $e['id'] ?>" <?= ($e['id'] == $usuario_equipe_id) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($e['nome']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label required"><i class="bi bi-person"></i> Responsável</label>
                        <select name="responsavel_id" class="form-select select2">
                            <option value="">Selecione</option>
                            <?php foreach ($responsaveis as $r): ?>
                            <option value="<?= $r['id'] ?>" <?= ($r['id'] == $usuario_responsavel_id) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($r['nome']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label required"><i class="bi bi-calendar-event"></i> Data Entrada</label>
                        <input type="date" name="data_entrada" id="data_entrada" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label required"><i class="bi bi-tags"></i> Tipo</label>
                        <select name="tipo_id" id="tipo_id" class="form-select select2">
                            <option value="">Selecione</option>
                            <?php foreach ($tipos as $t): ?>
                            <option value="<?= $t['id'] ?>" data-nome="<?= htmlspecialchars($t['nome']) ?>"><?= htmlspecialchars($t['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label required"><i class="bi bi-calendar-check"></i> Prazo</label>
                        <input type="date" name="prazo" id="prazo" class="form-control" placeholder="Calculado automaticamente">
                        <div class="help-text">Calculado automaticamente com base no Tipo e Data Entrada.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label required"><i class="bi bi-file-earmark-text"></i> Número do Processo</label>
                        <div class="input-group">
                            <input type="text" name="numero_processo" id="numero_processo" class="form-control" maxlength="20" required placeholder="Colar processo SEI - Ex.:XXXXX.XXXXXX/20XX-XX">
                            <button type="button" class="btn btn-clipboard" id="btnCopy" title="Copiar número do processo">
                                <i class="bi bi-clipboard"></i> Copiar
                            </button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><i class="bi bi-hash"></i> SEI Recebido (8 números)</label>
                        <input type="text" name="sei_recebido" class="form-control sei-input" maxlength="8" placeholder="Apenas números">
                    </div>

                    <div id="div-sei-criado">
                        <div class="mb-3 sei-criado-item" data-index="1">
                            <label class="form-label"><i class="bi bi-hash"></i> SEI Criado 1 (8 números)</label>
                            <input type="text" name="sei_criado_1" class="form-control sei-input" maxlength="8" placeholder="Apenas números">
                        </div>
                    </div>

                    <button type="button" id="btn-adicionar-sei" class="btn btn-outline-primary btn-sm mb-3">
                        <i class="bi bi-plus-circle"></i> Adicionar outro SEI
                    </button>
                    <div id="msg-limite-sei" class="text-danger small" style="display:none;">
                        <i class="bi bi-exclamation-triangle"></i> Limite de 3 SEIs atingido.
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label required"><i class="bi bi-geo-alt"></i> UF</label>
                        <select name="uf_manual" id="uf_manual" class="form-select select2">
                            <option value="">Selecione</option>
                            <?php foreach ($uf_lista as $uf): ?>
                            <option value="<?= htmlspecialchars($uf) ?>"><?= htmlspecialchars($uf) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="help-text">Selecione a UF para filtrar contratos. Selecione "-" para nenhum contrato.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label required"><i class="bi bi-file-pdf"></i> Contrato</label>
                        <div class="d-flex gap-2 align-items-center">
                            <select name="contrato_id" id="contrato_id" class="form-select select2" style="flex:1;">
                                <option value="">Selecione</option>
                                <option value="-">-</option>
                                <?php foreach ($contratos as $c): ?>
                                    <option value="<?= $c['id'] ?>" 
                                            data-uf="<?= $c['uf'] ?>" 
                                            data-br="<?= $c['br'] ?>" 
                                            data-nome-usual="<?= htmlspecialchars($c['nome_usual'] ?? '') ?>">
                                        <?= htmlspecialchars($c['numero']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn btn-outline-info btn-sm" id="btnInfoContrato" style="display:none;" data-bs-toggle="modal" data-bs-target="#modalInfoContrato">
                                <i class="bi bi-info-circle"></i> Info Contrato
                            </button>
                        </div>
                        <div class="help-text">Selecione o Contrato. Use "-" para nenhum contrato.</div>
                        <div id="divNomeUsual" style="display:none; margin-top: 6px;">
                            <i class="bi bi-building"></i> <strong>Nome usual:</strong> <span id="nomeUsual"></span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label required"><i class="bi bi-diagram-2"></i> BR</label>
                        <select name="br_manual" id="br_manual" class="form-select select2">
                            <option value="">Selecione</option>
                            <option value="-">-</option>
                        </select>
                        <div class="help-text">Preenchido automaticamente ao selecionar um contrato.</div>
                    </div>

                    <input type="hidden" name="uf" id="uf_hidden" value="">
                    <input type="hidden" name="br" id="br_hidden" value="">

                    <div class="mb-3">
                        <label class="form-label required"><i class="bi bi-check2-square"></i> Status</label>
                        <select name="status_id" id="status_id" class="form-select select2">
                            <option value="">Selecione</option>
                            <?php foreach ($statuses as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= ($s['id'] == $status_em_elaboracao_id) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['nome']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="div_revisao" style="display: none;">
                        <div class="mb-3">
                            <label class="form-label"><i class="bi bi-calendar2-check"></i> Data Revisão</label>
                            <input type="date" name="data_revisao" id="data_revisao" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    <div id="div_assinatura" style="display: none;">
                        <div class="mb-3">
                            <label class="form-label"><i class="bi bi-calendar2-check"></i> Data Assinatura</label>
                            <input type="date" name="data_assinatura" id="data_assinatura" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label required"><i class="bi bi-chat"></i> Assunto</label>
                        <textarea name="assunto" class="form-control" rows="2" maxlength="200"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label required"><i class="bi bi-lightbulb"></i> Providência</label>
                        <textarea name="providencia" class="form-control" rows="2" maxlength="200"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><i class="bi bi-sticky"></i> Observações</label>
                        <textarea name="observacoes" class="form-control" rows="2" maxlength="500"></textarea>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-3 mt-4">
                <button type="submit" class="btn btn-primary" id="btnSalvar">
                    <span id="btnSalvarText"><i class="bi bi-save"></i> Salvar</span>
                    <span id="btnSalvarSpinner" style="display:none;">
                        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                        Salvando...
                    </span>
                </button>
                <a href="index" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Cancelar</a>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    var HOJE = '<?= date("Y-m-d") ?>';

    $('.select2').select2({
        width: '100%',
        allowClear: true,
        placeholder: 'Selecione...',
        dropdownAutoWidth: true
    });

    $(document).on('select2:open', function() {
        setTimeout(function() {
            var searchField = document.querySelector('.select2-search__field');
            if (searchField) searchField.focus();
        }, 200);
    });

    $('.sei-input').on('input', function() {
        this.value = this.value.replace(/\D/g, '');
    });

    // ============================
    // PREENCHER DATAS CONDICIONAIS (default sempre = hoje)
    // ============================
    function preencherDataCondicional(campoId) {
        var campo = $('#' + campoId);
        if (!campo.val()) {
            campo.val(HOJE);
        }
    }

    function toggleCamposStatus() {
        var statusNome = $('#status_id option:selected').text().trim();
        var isRevisado = statusNome.toUpperCase() === 'REVISADO';
        var isAssinado = statusNome.toUpperCase() === 'ASSINADO';

        if (isRevisado) {
            $('#div_revisao').show();
            $('#div_assinatura').hide();
            $('#div_cadastrado_sima').show();
            preencherDataCondicional('data_revisao');
        } else if (isAssinado) {
            $('#div_revisao').hide();
            $('#div_assinatura').show();
            $('#div_cadastrado_sima').show();
            preencherDataCondicional('data_assinatura');
        } else {
            $('#div_revisao').hide();
            $('#div_assinatura').hide();
            $('#div_cadastrado_sima').hide();
        }
    }

    $('#status_id').on('change', toggleCamposStatus);
    toggleCamposStatus();

    // ============================
    // SEI DINÂMICO
    // ============================
    var maxSeis = 3;
    var contadorSeis = 1;

    function atualizarBotoesSei() {
        var totalItems = $('.sei-criado-item').length;
        if (totalItems >= maxSeis) {
            $('#btn-adicionar-sei').hide();
            $('#msg-limite-sei').show();
        } else {
            $('#btn-adicionar-sei').show();
            $('#msg-limite-sei').hide();
        }
    }
    atualizarBotoesSei();

    $('#btn-adicionar-sei').on('click', function() {
        if (contadorSeis >= maxSeis) { atualizarBotoesSei(); return; }
        contadorSeis++;
        var novoIndex = contadorSeis;
        var nomeCampo = 'sei_criado_' + novoIndex;
        var novoDiv = $('<div class="mb-3 sei-criado-item" data-index="' + novoIndex + '">' +
                            '<label class="form-label"><i class="bi bi-hash"></i> SEI Criado ' + novoIndex + ' (8 números)</label>' +
                            '<input type="text" name="' + nomeCampo + '" class="form-control sei-input" maxlength="8" placeholder="Apenas números">' +
                        '</div>');
        $('#btn-adicionar-sei').before(novoDiv);
        novoDiv.find('.sei-input').on('input', function() {
            this.value = this.value.replace(/\D/g, '');
        });
        atualizarBotoesSei();
    });

    // ============================
    // CONTRATO/UF/BR
    // ============================
    function carregarContratosBR(uf) {
        if (uf === '-') {
            $('#contrato_id').val('-').trigger('change');
            $('#br_manual').val('-').trigger('change');
            $('#br_hidden').val('-');
            $('#uf_hidden').val('-');
            $('#divNomeUsual').hide();
            $('#btnInfoContrato').hide();
            $('#contrato_id').prop('disabled', true);
            $('#br_manual').prop('disabled', false);
            $('#contrato_id').trigger('change.select2');
            $('#br_manual').trigger('change.select2');
            return;
        }

        $('#contrato_id').prop('disabled', false);
        $('#br_manual').prop('disabled', false);

        var urlUf = (uf && uf !== '-') ? uf : '';
        $.ajax({
            url: 'index.php?url=create&action=get_contratos&uf=' + encodeURIComponent(urlUf),
            method: 'GET',
            dataType: 'json',
            success: function(data) {
                if (data.error) {
                    console.error('Erro:', data.error);
                    return;
                }
                var contratoSelect = $('#contrato_id');
                contratoSelect.empty().append('<option value="">Selecione</option><option value="-">-</option>');
                $.each(data.contratos, function(i, c) {
                    contratoSelect.append('<option value="' + c.id + '" data-uf="' + c.uf + '" data-br="' + c.br + '" data-nome-usual="' + (c.nome_usual || '') + '">' + c.numero + '</option>');
                });
                contratoSelect.trigger('change');

                var brSelect = $('#br_manual');
                brSelect.empty().append('<option value="">Selecione</option><option value="-">-</option>');
                $.each(data.brs, function(i, b) {
                    brSelect.append('<option value="' + b + '">' + b + '</option>');
                });
                brSelect.trigger('change');
            },
            error: function() { alert('Erro ao carregar contratos e BRs.'); }
        });
    }

    $('#uf_manual').on('change', function() {
        var uf = $(this).val();
        $('#uf_hidden').val(uf);
        carregarContratosBR(uf);
        if (uf !== '-') {
            $('#contrato_id').val('').trigger('change');
            $('#br_hidden').val('');
            $('#br_manual').prop('disabled', false);
            $('#divNomeUsual').hide();
            $('#btnInfoContrato').hide();
        }
    });

    $('#contrato_id').on('change', function() {
        var option = $(this).find(':selected');
        var uf = option.data('uf') || '';
        var br = option.data('br') || '';
        var nomeUsual = option.data('nome-usual') || '';
        var contratoId = this.value;

        if (this.value && this.value !== '' && this.value !== '-') {
            $('#uf_manual').val(uf).prop('disabled', true).trigger('change.select2');
            $('#br_manual').val(br || '-').prop('disabled', true).trigger('change.select2');
            $('#uf_hidden').val(uf);
            $('#br_hidden').val(br || '');
            if (nomeUsual) {
                $('#divNomeUsual').show();
                $('#nomeUsual').text(nomeUsual);
            } else {
                $('#divNomeUsual').hide();
            }
            $('#btnInfoContrato').show().data('contrato-id', contratoId);
        } else {
            $('#uf_manual').prop('disabled', false).trigger('change.select2');
            $('#br_manual').prop('disabled', false).trigger('change.select2');
            $('#uf_hidden').val($('#uf_manual').val());
            $('#br_hidden').val($('#br_manual').val());
            $('#divNomeUsual').hide();
            $('#btnInfoContrato').hide();
        }
    });

    $('#br_manual').on('change', function() {
        var contratoVal = $('#contrato_id').val();
        if (!contratoVal || contratoVal === '-') {
            $('#br_hidden').val($(this).val());
        }
    });

    carregarContratosBR('');

    // ============================
    // CÁLCULO DO PRAZO
    // ============================
    function calcularPrazo() {
        var tipoSelect = $('#tipo_id option:selected');
        var tipoNome = tipoSelect.data('nome') || '';
        var dataEntrada = $('#data_entrada').val();
        if (!dataEntrada) { $('#prazo').val(''); return; }
        var data = new Date(dataEntrada);
        var dias = 8;
        if (tipoNome === 'PAAR') dias = 30;
        data.setDate(data.getDate() + dias);
        var ano = data.getFullYear();
        var mes = String(data.getMonth() + 1).padStart(2, '0');
        var dia = String(data.getDate()).padStart(2, '0');
        $('#prazo').val(ano + '-' + mes + '-' + dia);
    }
    $('#data_entrada, #tipo_id').on('change', calcularPrazo);
    calcularPrazo();

    // ============================
    // BOTÃO COPIAR
    // ============================
    function mostrarToast(mensagem) {
        var toast = document.getElementById('toastCopiado');
        if (!toast) {
            toast = $('<div id="toastCopiado" style="position:fixed; bottom:30px; left:50%; transform:translateX(-50%); background:rgba(0,0,0,0.85); color:#fff; padding:12px 28px; border-radius:30px; font-weight:500; font-size:0.95rem; z-index:99999; box-shadow:0 4px 12px rgba(0,0,0,0.3); pointer-events:none; opacity:0; visibility:hidden; transition:opacity 0.3s, visibility 0.3s;"><i class="bi bi-check-circle-fill text-success"></i> ' + mensagem + '</div>').appendTo('body');
        } else {
            toast.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i> ' + mensagem;
        }
        toast.style.opacity = '1';
        toast.style.visibility = 'visible';
        clearTimeout(window.toastTimer);
        window.toastTimer = setTimeout(function() {
            toast.style.opacity = '0';
            toast.style.visibility = 'hidden';
        }, 3000);
    }

    $('#btnCopy').on('click', function(e) {
        e.preventDefault();
        var texto = $('#numero_processo').val().trim();
        if (!texto) { mostrarToast('Nenhum número para copiar'); return; }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(texto).then(function() {
                mostrarToast('Copiado: ' + texto);
            }).catch(function() {
                fallbackCopiar(texto);
            });
        } else {
            fallbackCopiar(texto);
        }
    });

    function fallbackCopiar(texto) {
        var textarea = document.createElement('textarea');
        textarea.value = texto;
        textarea.style.position = 'fixed';
        textarea.style.top = '-9999px';
        textarea.style.left = '-9999px';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        try {
            var sucesso = document.execCommand('copy');
            if (sucesso) mostrarToast('Número copiado: ' + texto);
            else mostrarToast('Erro ao copiar');
        } catch (e) {
            mostrarToast('Erro ao copiar');
        }
        document.body.removeChild(textarea);
    }

    // ============================
    // SPINNER NO SUBMIT
    // ============================
    $('#formProcesso').on('submit', function(e) {
        var numeroProcesso = $('#numero_processo').val().trim();
        if (!numeroProcesso) {
            alert('O campo Número do Processo é obrigatório.');
            e.preventDefault();
            return false;
        }
        var contrato = $('#contrato_id').val();
        var uf = $('#uf_hidden').val();
        var br = $('#br_hidden').val();
        if ((!contrato || contrato === '-') && (!uf || !br)) {
            alert('Selecione um contrato ou preencha UF e BR manualmente.');
            e.preventDefault();
            return false;
        }

        var btn = $('#btnSalvar');
        btn.addClass('btn-loading');
        $('#btnSalvarText').hide();
        $('#btnSalvarSpinner').show();
        setTimeout(function() {
            $('#btnSalvarText').show();
            $('#btnSalvarSpinner').hide();
            btn.removeClass('btn-loading');
        }, 30000);
        return true;
    });
});
</script>

<!-- ✅ Modal Info Contrato (partial reutilizável) -->
<?php include APP_PATH . '/Views/partials/modal_info_contrato.php'; ?>
</body>
</html>