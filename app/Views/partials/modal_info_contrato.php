<?php
// ============================================================
// Partial: modal_info_contrato.php
// Modal reutilizável de informações do contrato
// Usado em: create.php, edit.php, view.php
// Requer: jQuery + Bootstrap 5 carregados na página
// ============================================================
?>

<!-- Modal Info Contrato -->
<div class="modal fade" id="modalInfoContrato" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
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

<style>
    /* ===== Blocos visuais dentro do modal ===== */
    .info-bloco {
        background: #fff;
        border-radius: 12px;
        padding: 18px 20px;
        margin-bottom: 18px;
        border: 1px solid #e6e9ef;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        border-left: 5px solid #4a90e2;
    }
    .info-bloco.contratual { border-left-color: #0d6efd; }
    .info-bloco.processos  { border-left-color: #fd7e14; }
    .info-bloco.cronograma { border-left-color: #198754; }

    .info-bloco-titulo {
        font-weight: 700;
        font-size: 1.05rem;
        color: #2c3e50;
        margin-bottom: 14px;
        display: flex;
        align-items: center;
        gap: 8px;
        padding-bottom: 8px;
        border-bottom: 1px dashed #e6e9ef;
    }
    .info-bloco-titulo i { font-size: 1.25rem; }

    /* Item padrão (label + valor na mesma linha) */
    .info-item {
        padding: 8px 0;
        font-size: 0.93rem;
        display: flex;
        gap: 10px;
        align-items: flex-start;
        border-bottom: 1px solid #f2f4f7;
    }
    .info-item:last-child { border-bottom: none; }
    .info-item .info-label {
        font-weight: 600;
        color: #495057;
        min-width: 160px;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .info-item .info-label i {
        color: #6c757d;
        font-size: 0.95rem;
    }
    .info-item .info-valor {
        color: #2c3e50;
        word-break: break-word;
        flex: 1;
    }
    .info-item.full-width {
        flex-direction: column;
        gap: 6px;
    }
    .info-item.full-width .info-label { min-width: 0; }

    .info-vazio { color: #adb5bd; font-style: italic; }

    /* Chips de processos/SEIs */
    .lista-processos {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    .processo-chip {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: #f1f5fb;
        border: 1px solid #d7e1ec;
        border-radius: 8px;
        padding: 6px 10px 6px 12px;
        font-size: 0.88rem;
        color: #2c3e50;
        font-family: 'Courier New', monospace;
        transition: all 0.15s ease;
    }
    .processo-chip:hover {
        background: #e6eefb;
        border-color: #b8c9df;
    }
    .processo-chip .btn-copiar-chip {
        background: transparent;
        border: none;
        color: #6c757d;
        cursor: pointer;
        padding: 0;
        line-height: 1;
        font-size: 0.9rem;
        transition: color 0.15s;
    }
    .processo-chip .btn-copiar-chip:hover {
        color: #0d6efd;
    }

    /* Seção de múltiplos processos */
    .processo-grupo {
        margin-bottom: 12px;
    }
    .processo-grupo:last-child { margin-bottom: 0; }
    .processo-grupo-titulo {
        font-weight: 600;
        color: #495057;
        font-size: 0.88rem;
        margin-bottom: 6px;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .processo-grupo-titulo i {
        color: #6c757d;
        font-size: 0.9rem;
    }

    /* Toast do copiar */
    #toastCopiaContrato {
        position: fixed;
        bottom: 30px;
        left: 50%;
        transform: translateX(-50%);
        background: rgba(0,0,0,0.85);
        color: #fff;
        padding: 10px 22px;
        border-radius: 30px;
        font-weight: 500;
        font-size: 0.92rem;
        z-index: 99999;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.3s, visibility 0.3s;
        pointer-events: none;
    }
    #toastCopiaContrato.show { opacity: 1; visibility: visible; }
    #toastCopiaContrato i { margin-right: 6px; color: #4ade80; }
</style>

<div id="toastCopiaContrato"><i class="bi bi-check-circle-fill"></i> Copiado!</div>

<script>
$(document).ready(function() {
    // ============================================================
    // CLICK DO BOTÃO "INFO CONTRATO"
    // ============================================================
    $(document).on('click', '#btnInfoContrato', function(e) {
        e.preventDefault();
        var contratoId = $(this).data('contrato-id');
        if (!contratoId) {
            $('#infoContratoBody').html('<p class="text-muted">Nenhum contrato selecionado.</p>');
            return;
        }

        $('#infoContratoBody').html(
            '<p class="text-muted text-center">' +
            '<span class="spinner-border spinner-border-sm me-2"></span> Carregando informações...</p>'
        );

        $.ajax({
            url: 'index.php?url=get_contrato_info&contrato_id=' + contratoId,
            method: 'GET',
            dataType: 'json',
            success: function(data) {
                if (data.error) {
                    $('#infoContratoBody').html('<p class="text-danger">' + data.error + '</p>');
                    return;
                }
                $('#infoContratoBody').html(montarHtmlInfoContrato(data));
            },
            error: function(xhr, status, err) {
                console.error('Erro Info Contrato:', xhr.status, xhr.responseText);
                $('#infoContratoBody').html('<p class="text-danger">Erro ao carregar informações.</p>');
            }
        });
    });

    // ============================================================
    // HELPERS
    // ============================================================
    function escapeHtml(txt) {
        if (txt === null || txt === undefined) return '';
        return String(txt)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatarData(valor) {
        if (!valor) return '';
        var s = String(valor).trim();
        if (/^\d{2}\/\d{2}\/\d{4}$/.test(s)) return s;
        var m = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (m) return m[3] + '/' + m[2] + '/' + m[1];
        return s;
    }

    function valorOuVazio(v) {
        if (v === null || v === undefined) return '<span class="info-vazio">—</span>';
        var s = String(v).trim();
        if (s === '' || s.toLowerCase() === 'null') return '<span class="info-vazio">—</span>';
        return escapeHtml(s);
    }

    function item(label, valorHtml, icone, fullWidth) {
        return '<div class="info-item' + (fullWidth ? ' full-width' : '') + '">' +
                    '<span class="info-label">' +
                        (icone ? '<i class="bi ' + icone + '"></i>' : '') +
                        escapeHtml(label) + ':' +
                    '</span>' +
                    '<span class="info-valor">' + valorHtml + '</span>' +
               '</div>';
    }

    // ✅ Divide uma string de processos em vários itens
    // Aceita separadores: ; , | ou quebra de linha
    // (não usa / porque SEI tem / dentro do número)
    function dividirProcessos(valor) {
        if (!valor) return [];
        var s = String(valor).trim();
        if (!s) return [];

        // Split por ; , | ou \n
        var partes = s.split(/\s*[;|]\s*|\s*\n\s*/);

        // Se não achou separador mas tem vírgula seguida de padrão SEI, também divide
        if (partes.length === 1) {
            partes = s.split(/,\s*(?=\d)/);
        }

        // Limpa
        partes = partes
            .map(function(p) { return p.trim(); })
            .filter(function(p) { return p.length > 0; });

        return partes;
    }

    // ✅ Renderiza chips de processos com botão de copiar
    function renderizarProcessos(valor, iconeVazio) {
        var lista = dividirProcessos(valor);
        if (lista.length === 0) {
            return '<span class="info-vazio">—</span>';
        }
        var html = '<div class="lista-processos">';
        lista.forEach(function(p) {
            html += '<span class="processo-chip">' +
                        '<i class="bi bi-hash" style="color:#0d6efd;"></i>' +
                        '<span>' + escapeHtml(p) + '</span>' +
                        '<button type="button" class="btn-copiar-chip" data-copiar="' + escapeHtml(p) + '" title="Copiar">' +
                            '<i class="bi bi-clipboard"></i>' +
                        '</button>' +
                    '</span>';
        });
        html += '</div>';
        return html;
    }

    // ✅ Copiar ao clicar no chip
    $(document).on('click', '.btn-copiar-chip', function(e) {
        e.stopPropagation();
        var texto = $(this).data('copiar');
        if (!texto) return;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(texto).then(function() {
                mostrarToastCopia('Copiado: ' + texto);
            }).catch(function() {
                fallbackCopiar(texto);
            });
        } else {
            fallbackCopiar(texto);
        }
    });

    var toastTimer = null;
    function mostrarToastCopia(msg) {
        var t = document.getElementById('toastCopiaContrato');
        if (!t) return;
        t.innerHTML = '<i class="bi bi-check-circle-fill"></i> ' + escapeHtml(msg);
        t.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function() { t.classList.remove('show'); }, 2000);
    }

    function fallbackCopiar(texto) {
        var ta = document.createElement('textarea');
        ta.value = texto;
        ta.style.position = 'fixed';
        ta.style.top = '-9999px';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try {
            document.execCommand('copy');
            mostrarToastCopia('Copiado: ' + texto);
        } catch (e) {
            mostrarToastCopia('Erro ao copiar');
        }
        document.body.removeChild(ta);
    }

    // ============================================================
    // MONTA O HTML EM BLOCOS
    // ============================================================
    function montarHtmlInfoContrato(d) {
        var html = '';

        // ---------- BLOCO CONTRATUAL ----------
        html += '<div class="info-bloco contratual">';
        html += '  <div class="info-bloco-titulo"><i class="bi bi-file-earmark-text text-primary"></i> Bloco Contratual</div>';

        html += item('Empresa',          valorOuVazio(d.empresa),          'bi-building');
        html += item('Endereço Empresa', valorOuVazio(d.endereco_empresa), 'bi-geo-alt');

        // Objeto em largura total (texto longo)
        html += item('Objeto', valorOuVazio(d.objeto_contrato), 'bi-file-text', true);

        html += '  <div class="row">';
        html += '    <div class="col-md-6">';
        html +=        item('Análise',       valorOuVazio(d.analise),       'bi-clipboard-check');
        html += '    </div>';
        html += '    <div class="col-md-6">';
        html +=        item('SEI Delegação', valorOuVazio(d.sei_delegacao), 'bi-hash');
        html += '    </div>';
        html += '  </div>';
        html += '</div>';

        // ---------- BLOCO PROCESSOS ----------
        html += '<div class="info-bloco processos">';
        html += '  <div class="info-bloco-titulo"><i class="bi bi-diagram-3 text-warning"></i> Bloco Processos</div>';

        // Processo Base
        html += '<div class="processo-grupo">';
        html += '  <div class="processo-grupo-titulo"><i class="bi bi-folder"></i> Processo Base</div>';
        html +=    renderizarProcessos(d.processo_base);
        html += '</div>';

        // Processo de Projetos (geralmente múltiplos)
        html += '<div class="processo-grupo">';
        html += '  <div class="processo-grupo-titulo"><i class="bi bi-diagram-2"></i> Processo de Projetos</div>';
        html +=    renderizarProcessos(d.processo_projeto);
        html += '</div>';

        // Processo Notificações
        html += '<div class="processo-grupo">';
        html += '  <div class="processo-grupo-titulo"><i class="bi bi-bell"></i> Processo Notificações SR/UF</div>';
        html +=    renderizarProcessos(d.processo_notificacao_sr_uf || d.n_sei_oficio_cobranca_cronograma);
        html += '</div>';

        html += '</div>';

        // ---------- BLOCO CRONOGRAMAS ----------
        html += '<div class="info-bloco cronograma">';
        html += '  <div class="info-bloco-titulo"><i class="bi bi-calendar-check text-success"></i> Bloco Cronogramas</div>';
        html += '  <div class="row">';
        html += '    <div class="col-md-6">';
        html +=        item('SEI Cronograma',    valorOuVazio(d.cronograma_sei),         'bi-hash');
        html +=        item('Status Cronograma', valorOuVazio(d.situacao_cronograma),    'bi-flag');
        html += '    </div>';
        html += '    <div class="col-md-6">';
        html +=        item('Data Cronograma',   valorOuVazio(formatarData(d.data_termino_projeto_cronog)), 'bi-calendar-event');
        html +=        item('Data Última Notificação',  valorOuVazio(formatarData(d.data_ultima_notificacao)),     'bi-calendar-check');
        html += '    </div>';
        html += '  </div>';
        html += '</div>';

        return html;
    }
});
</script>