<?php
// ============================================================
// Partial: modal_atualizacao_supra.php
// Modal de detalhes SUPRA (Resumos + Meio Ambiente)
// ============================================================
?>

<div class="modal fade" id="modalDetalhesSupra" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-file-earmark-text"></i>
                    Atualização de Contratos RDCI <span id="modalSupraTitulo" class="text-primary"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="supraConteudo">
                <p class="text-muted text-center">Carregando...</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<style>
    .supra-bloco {
        background: #fff; border-radius: 12px; padding: 18px 20px; margin-bottom: 18px;
        border: 1px solid #e6e9ef; box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        border-left: 5px solid #4a90e2;
    }
    .supra-bloco.resumos { border-left-color: #0d6efd; }
    .supra-bloco.meio    { border-left-color: #198754; }

    .supra-bloco-titulo {
        font-weight: 700; font-size: 1.05rem; color: #2c3e50; margin-bottom: 14px;
        display: flex; align-items: center; gap: 8px;
        padding-bottom: 8px; border-bottom: 1px dashed #e6e9ef;
    }
    .supra-bloco-titulo i { font-size: 1.25rem; }

    .supra-item {
        padding: 10px 0; font-size: 0.93rem;
        display: flex; flex-direction: column; gap: 6px;
        border-bottom: 1px solid #f2f4f7;
    }
    .supra-item:last-child { border-bottom: none; }
    .supra-item .supra-label {
        font-weight: 600; color: #495057; display: flex; align-items: center; gap: 6px; font-size: 0.9rem;
    }
    .supra-item .supra-label i { color: #6c757d; font-size: 0.95rem; }
    .supra-item .supra-valor {
        color: #2c3e50; word-break: break-word; white-space: pre-wrap;
        line-height: 1.5; background: #fafbfd; padding: 10px 14px;
        border-radius: 8px; border: 1px solid #eef1f6; min-height: 40px;
    }
    .supra-vazio { color: #adb5bd; font-style: italic; }
</style>

<script>
(function() {
    function escapeHtml(txt) {
        if (txt === null || txt === undefined) return '';
        return String(txt)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // ✅ Busca o primeiro valor não-vazio entre várias chaves possíveis
    function pegar(obj, chaves) {
        if (!obj) return null;
        for (var i = 0; i < chaves.length; i++) {
            var k = chaves[i];
            if (obj[k] !== undefined && obj[k] !== null && obj[k] !== '') {
                return obj[k];
            }
        }
        return null;
    }

    function valorBloco(v) {
        if (v === null || v === undefined) return '<span class="supra-vazio">—</span>';
        var s = String(v).trim();
        if (s === '' || s.toLowerCase() === 'null' || s.toLowerCase() === 'undefined') {
            return '<span class="supra-vazio">—</span>';
        }
        return escapeHtml(s);
    }

    function item(label, valorHtml, icone) {
        return '<div class="supra-item">' +
                    '<span class="supra-label">' +
                        (icone ? '<i class="bi ' + icone + '"></i>' : '') +
                        escapeHtml(label) +
                    '</span>' +
                    '<span class="supra-valor">' + valorHtml + '</span>' +
               '</div>';
    }

    function montarHtml(d) {
        var r = d.resumo || {};
        var m = d.meio   || {};

        var html = '';

        // ---------- BLOCO RESUMOS ----------
        html += '<div class="supra-bloco resumos">';
        html += '  <div class="supra-bloco-titulo"><i class="bi bi-file-earmark-text text-primary"></i> Bloco Resumos</div>';

        var situacaoGeo = pegar(r, [
            'situacao_geo', 'situacao_geo_mapa_gerencial', 'Situação GEO (Mapa Gerencial)',
            'SITUAÇÃO GEO (MAPA GERENCIAL)', 'situacao_geo_mapa'
        ]);
        html +=    item('Situação GEO (Mapa Gerencial)', valorBloco(situacaoGeo), 'bi-map');

        var textoCapa = pegar(r, [
            'texto_mapa_capa', 'texto_mapa_capa_atlas', 'Texto Mapa Capa do Atlas',
            'TEXTO MAPA CAPA DO ATLAS'
        ]);
        html +=    item('Texto Mapa Capa do Atlas', valorBloco(textoCapa), 'bi-file-text');

        var resumoLote = pegar(r, [
            'resumo_geral_lote', 'resumo_geral_lote_slide', 'Resumo geral do Lote',
            'RESUMO GERAL DO LOTE (SLIDE DA FOTO)', 'resumo_lote'
        ]);
        html +=    item('Resumo geral do Lote', valorBloco(resumoLote), 'bi-list-ul');

        var sitEmpreend = pegar(r, [
            'situacao_empreendimento_atlas', 'situacao_empreendimento_atlas_slide',
            'SITUAÇÃO EMPREENDIMENTO ATLAS (SLIDE DA FOTO)', 'situacao_empreendimento'
        ]);
        html +=    item('Situação empreendimento ATLAS', valorBloco(sitEmpreend), 'bi-flag');

        html += '</div>';

        // ---------- BLOCO MEIO AMBIENTE ----------
        html += '<div class="supra-bloco meio">';
        html += '  <div class="supra-bloco-titulo"><i class="bi bi-tree text-success"></i> Meio Ambiente, Desapropriação e Interferência</div>';

        var analiseMeio = pegar(m, [
            'analise_meio_ambiente', 'atlas_analise_meio_ambiente',
            'analise_meio_ambiente_desapropriacao_interferencia',
            'ATLAS - ANÁLISE MEIO AMBIENTE, DESAPROPRIAÇÃO E INTERFERÊNCIA'
        ]);
        html +=    item('ATLAS - Análise Meio Ambiente, Desapropriação e Interferência',
                        valorBloco(analiseMeio), 'bi-clipboard-check');

        html += '</div>';

        return html;
    }

    window.verDetalhesSupra = function(id) {
        if (!id) return;

        var modalEl = document.getElementById('modalDetalhesSupra');
        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);

        document.getElementById('supraConteudo').innerHTML =
            '<p class="text-muted text-center"><span class="spinner-border spinner-border-sm me-2"></span> Carregando...</p>';
        document.getElementById('modalSupraTitulo').textContent = '';

        $.ajax({
            url: 'ajax_rdci.php',
            method: 'GET',
            data: { action: 'detalhes_supra', id: id },
            dataType: 'json',
            success: function(data) {
                if (data.error) {
                    document.getElementById('supraConteudo').innerHTML =
                        '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> ' +
                        escapeHtml(data.error) + '</div>';
                    modal.show();
                    return;
                }
                document.getElementById('modalSupraTitulo').textContent = '— ' + (data.instrumento || '');
                document.getElementById('supraConteudo').innerHTML = montarHtml(data);
                modal.show();
            },
            error: function(xhr) {
                var detalhe = '';
                if (xhr.responseText) detalhe = xhr.responseText.substring(0, 300);
                document.getElementById('supraConteudo').innerHTML =
                    '<div class="alert alert-danger">' +
                        '<strong>Erro ao carregar informações do SUPRA.</strong><br>' +
                        '<small>Status: ' + xhr.status + '</small><br>' +
                        (detalhe ? '<small>Detalhe: ' + escapeHtml(detalhe) + '</small>' : '') +
                    '</div>';
                modal.show();
            }
        });
    };
})();
</script>