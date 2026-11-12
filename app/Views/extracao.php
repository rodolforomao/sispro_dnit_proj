<?php
// app/Views/extracao.php
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Gerador de Extrações - SISPRO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <style>
        body { background: #f8f9fc; padding-top: 70px; }
        .card { border-radius: 16px; box-shadow: 0 8px 30px rgba(0,0,0,0.05); border: none; }
        
        /* Correção para o Select2 não estourar o container */
        .select2-container { width: 100% !important; }
        .select2-container--bootstrap-5 .select2-selection { min-height: 42px; display: flex; align-items: center; }
        
        /* Estilo das etiquetas da Caixa de Seleção (Passo 3) */
        .tag-container { 
            display: flex; flex-wrap: wrap; gap: 8px; min-height: 60px; 
            padding: 12px; background: #f8f9fa; border: 2px dashed #ced4da; border-radius: 0.375rem;
            align-items: flex-start; transition: all 0.2s;
        }
        .tag-container:hover { border-color: #86b7fe; background: #f1f8ff; }
        
        .coluna-tag {
            background-color: #0d6efd; color: #fff; border-radius: 20px; padding: 6px 14px;
            font-size: 0.85rem; display: inline-flex; align-items: center; cursor: grab;
            box-shadow: 0 2px 5px rgba(13,110,253,0.3);
            transition: transform 0.1s; font-weight: 500;
        }
        .coluna-tag:active { cursor: grabbing; transform: scale(0.95); }
        .coluna-tag .remove-tag { cursor: pointer; margin-left: 8px; font-size: 1.2rem; line-height: 1; opacity: 0.8; }
        .coluna-tag .remove-tag:hover { opacity: 1; }
        .coluna-tag.sortable-ghost { opacity: 0.4; background-color: #0a58ca !important; border: 2px dashed #fff; }
        
        /* Divisor visual dos passos */
        .step-divider { text-align: center; color: #adb5bd; margin: 10px 0; font-size: 1.2rem; }

        /* Ajuste fino do Switch para não vazar */
        .switch-custom .form-check-input {
            width: 3em;
            height: 1.5em;
            margin-top: 0;
            cursor: pointer;
        }
    </style>
</head>
<body>
<?php include APP_PATH . '/Views/header_atlas.php'; ?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="h4 mb-0 text-primary fw-bold"><i class="bi bi-file-earmark-excel"></i> Construtor de Extrações</h2>
        <a href="admin" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>

    <!-- Transforma o catálogo PHP num objeto JavaScript -->
    <script>
        const CATALOGO_JS = <?= json_encode($catalogo) ?>;
    </script>

    <div class="row">
        <div class="col-lg-8">
            <div class="card p-4 mb-4">
                
                <!-- PASSO 1 -->
                <h5 class="mb-2 text-secondary fw-bold"><i class="bi bi-1-circle-fill text-primary"></i> Escolha a Tabela (Filtro)</h5>
                <select class="form-select mb-2" id="select-tabela">
                    <option value="todas">— Todas as Tabelas (Mostrar tudo) —</option>
                    <?php foreach ($catalogo as $tabelaNome => $colunas): ?>
                        <option value="<?= htmlspecialchars($tabelaNome) ?>"><?= htmlspecialchars($tabelaNome) ?></option>
                    <?php endforeach; ?>
                </select>

                <div class="step-divider"><i class="bi bi-arrow-down-circle"></i></div>

                <!-- PASSO 2 -->
                <h5 class="mb-2 text-secondary fw-bold"><i class="bi bi-2-circle-fill text-primary"></i> Escolha a Coluna</h5>
                <!-- Container relativo para o dropdown do Select2 não fugir -->
                <div class="position-relative mb-2" id="passo2-container">
                    <select class="form-select" id="select-coluna-disponivel">
                        <option></option>
                    </select>
                </div>

                <div class="step-divider"><i class="bi bi-arrow-down-circle"></i></div>

                <!-- PASSO 3 -->
                <div class="d-flex justify-content-between align-items-end mb-2">
                    <h5 class="mb-0 text-secondary fw-bold"><i class="bi bi-3-circle-fill text-primary"></i> Colunas Selecionadas</h5>
                    <small class="text-muted"><i class="bi bi-arrows-move"></i> Arraste para reordenar</small>
                </div>
                <div class="tag-container mb-4" id="colunas-selecionadas">
                    <!-- As etiquetas aparecerão aqui via JS -->
                </div>

                <!-- FILTROS E CONFIGURAÇÕES FINAIS -->
                <h5 class="mb-3 mt-4 text-secondary fw-bold">Filtros (opcional)</h5>
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label class="form-label small">Ação Orçamentária</label>
                        <input type="text" class="form-control form-control-sm" id="f-acao" placeholder="Ex: 10IX">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Tipo</label>
                        <select class="form-select form-select-sm" id="f-tipo">
                            <option value="">Todos</option>
                            <option value="OBRA">OBRA</option>
                            <option value="SUPERVISAO">SUPERVISÃO</option>
                            <option value="MEIO_AMBIENTE">MEIO AMBIENTE</option>
                            <option value="DESAPROPRIACAO">DESAPROPRIAÇÃO</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">UF</label>
                        <input type="text" class="form-control form-control-sm" id="f-estado" placeholder="Ex: MG" maxlength="2">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Contrato</label>
                        <input type="text" class="form-control form-control-sm" id="f-contrato" placeholder="Ex: 00 00041/2025">
                    </div>
                </div>

                <h5 class="mb-3 text-secondary fw-bold">Nome do Arquivo</h5>
                <div class="input-group mb-4">
                    <span class="input-group-text">.xls</span>
                    <input type="text" class="form-control" id="f-nome" value="extracao" placeholder="Ex: Extração Analista">
                    <span class="input-group-text" id="sufixo-data"><?= date('d-m-Y') ?></span>
                </div>

                <!-- ⬇️ SWITCH DE DISTINCT COM FLEXBOX (CORRIGIDO) ⬇️ -->
                <div class="d-flex align-items-center p-3 mb-4 bg-light rounded border border-primary" style="border-style: dashed !important;">
                    <div class="form-check form-switch switch-custom m-0 me-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="check-distinct" style="cursor: pointer;">
                    </div>
                    <div>
                        <label class="fw-bold text-primary mb-0" for="check-distinct" style="cursor: pointer; font-size: 1rem;">
                            <i class="bi bi-funnel-fill"></i> Modo Distinct (Único)
                        </label>
                        <div class="small text-muted mt-1">Remove linhas duplicadas. Marque se quiser apenas <b>um contrato por linha</b>.</div>
                    </div>
                </div>
                <!-- ⬆️ FIM ⬆️ -->

                <button type="button" id="btn-gerar" class="btn btn-primary w-100 fw-bold py-2 fs-5">
                    <i class="bi bi-cloud-arrow-down"></i> Gerar Planilha Excel
                </button>
                <div id="msg-erro" class="alert alert-danger mt-3 d-none"></div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card p-4">
                <h5 class="mb-3 text-secondary fw-bold"><i class="bi bi-clock-history"></i> Histórico Recente</h5>
                <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                    <table class="table table-sm table-hover text-sm">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Arquivo</th>
                                <th>Ação</th>
                            </tr>
                        </thead>
                        <tbody id="tbl-historico">
                            <tr><td colspan="3" class="text-center text-muted">Carregando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<script>
$(function() {
    const API = 'extracao';
    let colunasSelecionadas = []; // Array que guarda os IDs na ordem exata

    // Inicializa o Select2 do Passo 2
    const $selectColuna = $('#select-coluna-disponivel');
    $selectColuna.select2({
        theme: 'bootstrap-5',
        placeholder: 'Digite para buscar a coluna...',
        allowClear: true,
        width: '100%',
        dropdownParent: $('#passo2-container'), // ⬅️ MÁGICA AQUI (impede de fugir do container)
        language: {
            noResults: function() { return "Nenhuma coluna encontrada"; },
            searching: function() { return "Buscando..."; }
        }
    });

    // FUNÇÃO PARA RENDERIZAR O PASSO 2 BASEADO NA TABELA ESCOLHIDA
    function carregarColunasDisponiveis(tabelaFiltro) {
        $selectColuna.empty().append('<option></option>');
        
        if (tabelaFiltro === 'todas') {
            for (const [grupo, cols] of Object.entries(CATALOGO_JS)) {
                let optgroup = $('<optgroup>').attr('label', grupo);
                for (const [key, info] of Object.entries(cols)) {
                    if (!colunasSelecionadas.includes(key)) {
                        optgroup.append(new Option(info.label.replace(/_/g, ' '), key));
                    }
                }
                $selectColuna.append(optgroup);
            }
        } else {
            if (CATALOGO_JS[tabelaFiltro]) {
                let optgroup = $('<optgroup>').attr('label', tabelaFiltro);
                for (const [key, info] of Object.entries(CATALOGO_JS[tabelaFiltro])) {
                    if (!colunasSelecionadas.includes(key)) {
                        optgroup.append(new Option(info.label.replace(/_/g, ' '), key));
                    }
                }
                $selectColuna.append(optgroup);
            }
        }
        $selectColuna.trigger('change.select2');
    }

    // FUNÇÃO PARA RENDERIZAR O PASSO 3 (Etiquetas)
    function renderizarTags() {
        const container = $('#colunas-selecionadas');
        container.empty();

        if (colunasSelecionadas.length === 0) {
            container.append('<span class="text-muted small mt-2"><i class="bi bi-inbox"></i> Nenhuma coluna selecionada ainda. Use o campo acima.</span>');
            return;
        }

        colunasSelecionadas.forEach((key, index) => {
            // Descobre o label real varrendo o JSON
            let labelReal = key;
            for (const grupo in CATALOGO_JS) {
                if (CATALOGO_JS[grupo][key]) {
                    labelReal = CATALOGO_JS[grupo][key].label.replace(/_/g, ' ');
                    break;
                }
            }

            const tag = $(`
                <div class="coluna-tag" data-key="${key}">
                    ${labelReal}
                    <span class="remove-tag" title="Remover">&times;</span>
                </div>
            `);

            // Evento para remover a coluna
            tag.find('.remove-tag').on('click', function() {
                colunasSelecionadas = colunasSelecionadas.filter(k => k !== key);
                renderizarTags();
                carregarColunasDisponiveis($('#select-tabela').val()); 
            });

            container.append(tag);
        });
    }

    // Inicializa o SortableJS na caixa de etiquetas (Passo 3)
    new Sortable(document.getElementById('colunas-selecionadas'), {
        animation: 150,
        ghostClass: 'sortable-ghost',
        onEnd: function() {
            const novaOrdem = [];
            $('#colunas-selecionadas .coluna-tag').each(function() {
                novaOrdem.push($(this).data('key'));
            });
            colunasSelecionadas = novaOrdem;
        }
    });

    // EVENTOS
    $('#select-tabela').on('change', function() {
        carregarColunasDisponiveis($(this).val());
    });

    $selectColuna.on('select2:select', function (e) {
        const key = e.params.data.id;
        if (!colunasSelecionadas.includes(key)) {
            colunasSelecionadas.push(key);
        }
        
        $(this).val(null).trigger('change');
        renderizarTags();
        carregarColunasDisponiveis($('#select-tabela').val());
        
        // Foca no campo para digitar a próxima coluna imediatamente
        $selectColuna.select2('open');
    });

    // DEFINIÇÃO DOS DEFAULTS
    const defaultCols = [
        'supra_dataset_47_financeiro_orcamentario_atlas___contrato',
        'supra_dataset_47_financeiro_orcamentario_atlas___estado',
        'supra_dataset_47_financeiro_orcamentario_atlas___br',
        'supra_dataset_36_todos___nome_usual'
    ];

    setTimeout(() => {
        colunasSelecionadas = [...defaultCols];
        renderizarTags();
        carregarColunasDisponiveis('todas');
    }, 100);

    // HISTÓRICO E GERAÇÃO
    function loadHistorico() {
        $.get(`${API}/historico`, function(res) {
            if (!res.ok) return;
            let html = '';
            if (res.itens.length === 0) {
                html = '<tr><td colspan="3" class="text-center text-muted">Nenhuma extração ainda.</td></tr>';
            } else {
                res.itens.forEach(i => {
                    const dt = new Date(i.data_extracao).toLocaleString('pt-BR');
                    html += `<tr>
                        <td class="align-middle" style="font-size:0.8rem">${dt}</td>
                        <td class="align-middle" style="font-size:0.8rem" title="${i.nome_arquivo}">${i.nome_arquivo}</td>
                        <td class="text-end">
                            <a href="${API}/baixar?f=${i.nome_arquivo}" class="btn btn-sm btn-outline-primary" title="Baixar novamente">
                                <i class="bi bi-download"></i>
                            </a>
                        </td>
                    </tr>`;
                });
            }
            $('#tbl-historico').html(html);
        });
    }
    loadHistorico();

    $('#btn-gerar').click(function() {
        const btn = $(this);
        const original = btn.html();

        if (colunasSelecionadas.length === 0) {
            $('#msg-erro').text('Selecione pelo menos uma coluna.').removeClass('d-none');
            return;
        }

        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Gerando...');
        $('#msg-erro').addClass('d-none');

        const payload = {
            colunas: colunasSelecionadas,
            nome_arquivo: $('#f-nome').val(),
            distinct: $('#check-distinct').is(':checked'), // Envia o distinct
            filtros: {
                acao: $('#f-acao').val(),
                tipo: $('#f-tipo').val(),
                estado: $('#f-estado').val(),
                contrato: $('#f-contrato').val()
            }
        };

        $.ajax({
            url: `${API}/gerar`,
            method: 'POST',
            data: payload,
            dataType: 'json',
            success: function(res) {
                if (res.ok) {
                    window.location.href = `${API}/baixar?f=${res.arquivo}`;
                    loadHistorico();
                } else {
                    $('#msg-erro').text(res.erro).removeClass('d-none');
                }
            },
            error: function() {
                $('#msg-erro').text('Erro de rede ao gerar extração.').removeClass('d-none');
            },
            complete: function() {
                btn.prop('disabled', false).html(original);
            }
        });
    });
});
</script>
</body>
</html>