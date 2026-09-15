<?php
// View edit.php - Formulário de edição de processos
// Variáveis: $processo, $equipes, $responsaveis, $tipos, $statuses, $uf_lista
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Editar Processo - SISPRO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fc; }
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
        .btn-clipboard { border-radius: 0 10px 10px 0; background: #f1f4f9; border-color: #dce1e8; }
        .btn-clipboard:hover { background: #e2e8f0; }
        .required:after { content: " *"; color: #e74c3c; }
        .card-header-icon { font-size: 1.4rem; margin-right: 10px; color: #4a90e2; }
        #divNomeUsual { margin-top: 5px; font-size: 0.9rem; color: #0d6efd; }
        @media (max-width: 768px) { .container { padding: 10px; } .card { padding: 15px; } }
    </style>
</head>
<body>
<div class="container mt-4">
    <div class="card">
        <div class="d-flex align-items-center mb-4">
            <i class="bi bi-pencil-square card-header-icon"></i>
            <h2 class="mb-0" style="font-weight: 700; color: #2c3e50;">Editar Processo - SISPRO</h2>
        </div>

        <div class="checkbox-top mb-4 p-3 bg-light rounded-3">
            <div class="form-check form-check-inline">
                <input type="checkbox" name="tem_prazo" class="form-check-input" id="tem_prazo" <?= ($processo['tem_prazo'] ?? 0) ? 'checked' : '' ?>>
                <label class="form-check-label" for="tem_prazo"><i class="bi bi-clock"></i> Tem Prazo?</label>
            </div>
            <div class="form-check form-check-inline" id="div_cadastrado_sima" style="display: <?= ($processo['status_id'] && in_array($processo['status_id'], array_column($statuses, 'id')) && array_column($statuses, 'nome')[array_search($processo['status_id'], array_column($statuses, 'id'))] == 'Revisado') ? 'inline-block' : 'none' ?>;">
                <input type="checkbox" name="cadastrado_sima" class="form-check-input" id="cadastrado_sima" <?= ($processo['cadastrado_sima'] ?? 0) ? 'checked' : '' ?>>
                <label class="form-check-label" for="cadastrado_sima"><i class="bi bi-check-circle"></i> Cadastrado no SIMA?</label>
            </div>
        </div>

        <form method="POST" id="formProcesso" novalidate>
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label required"><i class="bi bi-people"></i> Equipe</label>
                        <select name="equipe_id" class="form-select select2">
                            <option value="">Selecione</option>
                            <?php foreach ($equipes as $e): ?>
                            <option value="<?= $e['id'] ?>" <?= ($e['id'] == ($processo['equipe_id'] ?? '')) ? 'selected' : '' ?>><?= htmlspecialchars($e['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label required"><i class="bi bi-person"></i> Responsável</label>
                        <select name="responsavel_id" class="form-select select2">
                            <option value="">Selecione</option>
                            <?php foreach ($responsaveis as $r): ?>
                            <option value="<?= $r['id'] ?>" <?= ($r['id'] == ($processo['responsavel_id'] ?? '')) ? 'selected' : '' ?>><?= htmlspecialchars($r['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label required"><i class="bi bi-calendar-event"></i> Data Entrada</label>
                        <input type="date" name="data_entrada" id="data_entrada" class="form-control" value="<?= htmlspecialchars($processo['data_entrada'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label required"><i class="bi bi-tags"></i> Tipo</label>
                        <select name="tipo_id" id="tipo_id" class="form-select select2">
                            <option value="">Selecione</option>
                            <?php foreach ($tipos as $t): ?>
                            <option value="<?= $t['id'] ?>" data-nome="<?= htmlspecialchars($t['nome']) ?>" <?= ($t['id'] == ($processo['tipo_id'] ?? '')) ? 'selected' : '' ?>><?= htmlspecialchars($t['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label required"><i class="bi bi-calendar-check"></i> Prazo</label>
                        <input type="date" name="prazo" id="prazo" class="form-control" value="<?= htmlspecialchars($processo['prazo'] ?? '') ?>">
                        <div class="help-text">Calculado automaticamente com base no Tipo e Data Entrada.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label required"><i class="bi bi-file-earmark-text"></i> Número do Processo</label>
                        <div class="input-group">
                            <input type="text" name="numero_processo" id="numero_processo" class="form-control" maxlength="20" required value="<?= htmlspecialchars($processo['numero_processo'] ?? '') ?>">
                            <button type="button" class="btn btn-clipboard" id="btnPaste"><i class="bi bi-clipboard"></i> Colar</button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><i class="bi bi-hash"></i> SEI Recebido (8 números)</label>
                        <input type="text" name="sei_recebido" class="form-control sei-input" maxlength="8" value="<?= htmlspecialchars($processo['sei_recebido'] ?? '') ?>" placeholder="Apenas números">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="bi bi-hash"></i> SEI Criado 1 (8 números)</label>
                        <input type="text" name="sei_criado_1" class="form-control sei-input" maxlength="8" value="<?= htmlspecialchars($processo['sei_criado_1'] ?? '') ?>" placeholder="Apenas números">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="bi bi-hash"></i> SEI Criado 2 (8 números)</label>
                        <input type="text" name="sei_criado_2" class="form-control sei-input" maxlength="8" value="<?= htmlspecialchars($processo['sei_criado_2'] ?? '') ?>" placeholder="Apenas números">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="bi bi-hash"></i> SEI Criado 3 (8 números)</label>
                        <input type="text" name="sei_criado_3" class="form-control sei-input" maxlength="8" value="<?= htmlspecialchars($processo['sei_criado_3'] ?? '') ?>" placeholder="Apenas números">
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label required"><i class="bi bi-geo-alt"></i> UF</label>
                        <select name="uf_manual" id="uf_manual" class="form-select select2">
                            <option value="">Selecione</option>
                            <?php foreach ($uf_lista as $uf): ?>
                            <option value="<?= htmlspecialchars($uf) ?>" <?= ($uf == ($processo['uf'] ?? '')) ? 'selected' : '' ?>><?= htmlspecialchars($uf) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="help-text">Selecione a UF para filtrar contratos.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label required"><i class="bi bi-file-pdf"></i> Contrato</label>
                        <select name="contrato_id" id="contrato_id" class="form-select select2">
                            <option value="">Selecione</option>
                            <option value="-">-</option>
                        </select>
                        <div class="help-text">Selecione o Contrato. Use "-" para nenhum contrato.</div>
                        <!-- Div para exibir o Nome Usual -->
                        <div id="divNomeUsual" style="<?= (!empty($processo['contrato_id'])) ? 'display:block;' : 'display:none;' ?>; margin-top: 6px;">
                            <i class="bi bi-building"></i> <strong>Nome usual:</strong> 
                            <span id="nomeUsual">
                                <?php 
                                // Buscar nome usual do contrato atual nos contratos carregados
                                $nomeUsualAtual = '';
                                if (!empty($processo['contrato_id']) && isset($contratos)) {
                                    foreach ($contratos as $c) {
                                        if ($c['id'] == $processo['contrato_id']) {
                                            $nomeUsualAtual = $c['nome_usual'] ?? '';
                                            break;
                                        }
                                    }
                                }
                                echo htmlspecialchars($nomeUsualAtual);
                                ?>
                            </span>
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

                    <input type="hidden" name="uf" id="uf_hidden" value="<?= htmlspecialchars($processo['uf'] ?? '') ?>">
                    <input type="hidden" name="br" id="br_hidden" value="<?= htmlspecialchars($processo['br'] ?? '') ?>">

                    <div class="mb-3">
                        <label class="form-label required"><i class="bi bi-check2-square"></i> Status</label>
                        <select name="status_id" id="status_id" class="form-select select2">
                            <option value="">Selecione</option>
                            <?php foreach ($statuses as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= ($s['id'] == ($processo['status_id'] ?? '')) ? 'selected' : '' ?>><?= htmlspecialchars($s['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="div_revisao" style="display: <?= ($processo['status_id'] && in_array($processo['status_id'], array_column($statuses, 'id')) && array_column($statuses, 'nome')[array_search($processo['status_id'], array_column($statuses, 'id'))] == 'Revisado') ? 'block' : 'none' ?>;">
                        <div class="mb-3">
                            <label class="form-label"><i class="bi bi-calendar2-check"></i> Data Revisão</label>
                            <input type="date" name="data_revisao" id="data_revisao" class="form-control" value="<?= htmlspecialchars($processo['data_revisao'] ?? '') ?>">
                        </div>
                    </div>
                    <div id="div_assinatura" style="display: <?= ($processo['status_id'] && in_array($processo['status_id'], array_column($statuses, 'id')) && array_column($statuses, 'nome')[array_search($processo['status_id'], array_column($statuses, 'id'))] == 'Assinado') ? 'block' : 'none' ?>;">
                        <div class="mb-3">
                            <label class="form-label"><i class="bi bi-calendar2-check"></i> Data Assinatura</label>
                            <input type="date" name="data_assinatura" id="data_assinatura" class="form-control" value="<?= htmlspecialchars($processo['data_assinatura'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label required"><i class="bi bi-chat"></i> Assunto (máx. 100)</label>
                        <textarea name="assunto" class="form-control" rows="2" maxlength="100"><?= htmlspecialchars($processo['assunto'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label required"><i class="bi bi-lightbulb"></i> Providência (máx. 100)</label>
                        <textarea name="providencia" class="form-control" rows="2" maxlength="100"><?= htmlspecialchars($processo['providencia'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><i class="bi bi-sticky"></i> Observações (máx. 100)</label>
                        <textarea name="observacoes" class="form-control" rows="2" maxlength="100"><?= htmlspecialchars($processo['observacoes'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-3 mt-4">
                <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Atualizar</button>
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
    $('.select2').select2({
        width: '100%',
        allowClear: true,
        placeholder: 'Selecione...',
        dropdownAutoWidth: true
    });

    $(document).on('select2:open', function(e) {
        var searchField = document.querySelector('.select2-search__field');
        if (searchField) {
            setTimeout(function() { searchField.focus(); }, 100);
        }
    });

    $('.sei-input').on('input', function() {
        this.value = this.value.replace(/\D/g, '');
    });

    // ---- AJAX para contratos por UF ----
    function carregarContratosBR(uf, callback) {
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

                if (callback) callback();
            },
            error: function(xhr, status, error) {
                console.error('Erro AJAX:', status, error);
                alert('Erro ao carregar contratos e BRs.');
            }
        });
    }

    // ---- Inicialização com valores atuais ----
    var ufInicial = $('#uf_hidden').val();
    var contratoInicial = <?= json_encode($processo['contrato_id'] ?? null) ?>;
    var brInicial = <?= json_encode($processo['br'] ?? '') ?>;

    carregarContratosBR(ufInicial, function() {
        if (contratoInicial) {
            $('#contrato_id').val(contratoInicial).trigger('change');
        } else {
            $('#contrato_id').val('-').trigger('change');
        }
        if (!contratoInicial && brInicial) {
            $('#br_manual').val(brInicial).trigger('change');
            $('#br_hidden').val(brInicial);
        }
        if (!ufInicial || ufInicial === '') {
            $('#uf_manual').val('-').trigger('change.select2');
        } else {
            $('#uf_manual').val(ufInicial).trigger('change.select2');
        }
    });

    // ---- Eventos ----
    $('#uf_manual').on('change', function() {
        var uf = $(this).val();
        $('#uf_hidden').val(uf);
        carregarContratosBR(uf);
        $('#contrato_id').val('').trigger('change');
        $('#br_hidden').val('');
        $('#br_manual').prop('disabled', false);
        $('#divNomeUsual').hide();
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

            // Exibe o nome usual
            if (nomeUsual) {
                $('#divNomeUsual').show();
                $('#nomeUsual').text(nomeUsual);
            } else {
                $('#divNomeUsual').hide();
            }
        } else {
            $('#uf_manual').prop('disabled', false).trigger('change.select2');
            $('#br_manual').prop('disabled', false).trigger('change.select2');
            $('#uf_hidden').val($('#uf_manual').val());
            $('#br_hidden').val($('#br_manual').val());
            $('#divNomeUsual').hide();
        }
    });

    $('#br_manual').on('change', function() {
        var contratoVal = $('#contrato_id').val();
        if (!contratoVal || contratoVal === '-') {
            $('#br_hidden').val($(this).val());
        }
    });

    // Botão Colar
    $('#btnPaste').on('click', function() {
        if (navigator.clipboard) {
            navigator.clipboard.readText().then(text => {
                $('#numero_processo').val(text);
            }).catch(() => {
                alert('Não foi possível acessar a área de transferência. Use Ctrl+V.');
            });
        } else {
            var text = prompt('Cole o número do processo aqui:');
            if (text) $('#numero_processo').val(text);
        }
    });

    // Cálculo do Prazo
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
    if (!$('#prazo').val()) calcularPrazo();

    // Campos condicionais
    function toggleCamposStatus() {
        var statusNome = $('#status_id option:selected').text();
        if (statusNome === 'Revisado') {
            $('#div_revisao').show();
            $('#div_assinatura').hide();
            $('#div_cadastrado_sima').show();
        } else if (statusNome === 'Assinado') {
            $('#div_revisao').hide();
            $('#div_assinatura').show();
            $('#div_cadastrado_sima').hide();
        } else {
            $('#div_revisao').hide();
            $('#div_assinatura').hide();
            $('#div_cadastrado_sima').hide();
        }
    }
    $('#status_id').on('change', toggleCamposStatus);
    toggleCamposStatus();

    // Validação
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
        return true;
    });
});
</script>
<?php include APP_PATH . '/public/chat_widget.php'; ?>
</body>
</html>