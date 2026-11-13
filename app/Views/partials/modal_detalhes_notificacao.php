<?php
// ============================================================
// Partial: modal_detalhes_notificacao.php
// Modal reutilizável de detalhes do cronograma RDCI
// Usado em: notificacoes_rdci.php
// Requer: jQuery + Bootstrap 5 carregados na página
// ============================================================
?>

<!-- Modal Detalhes Notificação -->
<div class="modal fade" id="modalDetalhes" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-info-circle"></i>
                    Detalhes do Cronograma <span id="modalDetalhesTitulo" class="text-primary"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detalhesConteudo">
                <p class="text-muted text-center">Carregando...</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<style>
    .det-bloco {
        background: #fff;
        border-radius: 12px;
        padding: 18px 20px;
        margin-bottom: 18px;
        border: 1px solid #e6e9ef;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        border-left: 5px solid #4a90e2;
    }
    .det-bloco.contratual { border-left-color: #0d6efd; }
    .det-bloco.processos  { border-left-color: #fd7e14; }
    .det-bloco.cronograma { border-left-color: #198754; }

    .det-bloco-titulo {
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
    .det-bloco-titulo i { font-size: 1.25rem; }

    .det-item {
        padding: 8px 0;
        font-size: 0.93rem;
        display: flex;
        gap: 10px;
        align-items: flex-start;
        border-bottom: 1px solid #f2f4f7;
    }
    .det-item:last-child { border-bottom: none; }
    .det-item .det-label {
        font-weight: 600;
        color: #495057;
        min-width: 160px;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .det-item .det-label i { color: #6c757d; font-size: 0.95rem; }
    .det-item .det-valor { color: #2c3e50; word-break: break-word; flex: 1; }
    .det-item.full-width { flex-direction: column; gap: 6px; }
    .det-item.full-width .det-label { min-width: 0; }

    .det-vazio { color: #adb5bd; font-style: italic; }

    .lista-processos { display: flex; flex-wrap: wrap; gap: 8px; }
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
    }
    .processo-chip:hover { background: #e6eefb; border-color: #b8c9df; }
    .processo-chip .btn-copiar-chip {
        background: transparent;
        border: none;
        color: #6c757d;
        cursor: pointer;
        padding: 0;
        line-height: 1;
        font-size: 0.9rem;
    }
    .processo-chip .btn-copiar-chip:hover { color: #0d6efd; }

    .processo-grupo { margin-bottom: 12px; }
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
    .processo-grupo-titulo i { color: #6c757d; font-size: 0.9rem; }

    #toastCopiaDetalhes {
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
        z-index: 2147483647;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.3s, visibility 0.3s;
        pointer-events: none;
    }
    #toastCopiaDetalhes.show { opacity: 1; visibility: visible; }
    #toastCopiaDetalhes i { margin-right: 6px; color: #4ade80; }
</style>

<div id="toastCopiaDetalhes"><i class="bi bi-check-circle-fill"></i> Copiado!</div>

<script>
(function() {
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
        if (v === null || v === undefined) return '<span class="det-vazio">—</span>';
        var s = String(v).trim();
        if (s === '' || s.toLowerCase() === 'null') return '<span class="det-vazio">—</span>';
        return escapeHtml(s);
    }

    function item(label, valorHtml, icone, fullWidth) {
        return '<div class="det-item' + (fullWidth ? ' full-width' : '') + '">' +
                    '<span class="det-label">' +
                        (icone ? '<i class="bi ' + icone + '"></i>' : '') +
                        escapeHtml(label) + ':' +
                    '</span>' +
                    '<span class="det-valor">' + valorHtml + '</span>' +
               '</div>';
    }

    // Divide uma string em múltiplos itens (por ; | ou quebra de linha)
    function dividirProcessos(valor) {
        if (!valor) return [];
        var s = String(valor).trim();
        if (!s) return [];
        var partes = s.split(/\s*[;|]\s*|\s*\n\s*/);
        if (partes.length === 1) {
            partes = s.split(/,\s*(?=\d)/);
        }
        return partes.map(function(p) { return p.trim(); }).filter(function(p) { return p.length > 0; });
    }

    // Renderiza lista de chips (usado para múltiplos processos)
    function renderizarProcessos(valor) {
        var lista = dividirProcessos(valor);
        if (lista.length === 0) return '<span class="det-vazio">—</span>';
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

    // Renderiza um campo único como chip copiável (SEI Cronograma, Ofício)
    function renderizarCampoCopiavel(valor) {
        if (valor === null || valor === undefined) return '<span class="det-vazio">—</span>';
        var s = String(valor).trim();
        if (s === '' || s.toLowerCase() === 'null') return '<span class="det-vazio">—</span>';

        return '<div class="lista-processos">' +
                    '<span class="processo-chip">' +
                        '<i class="bi bi-hash" style="color:#0d6efd;"></i>' +
                        '<span>' + escapeHtml(s) + '</span>' +
                        '<button type="button" class="btn-copiar-chip" data-copiar="' + escapeHtml(s) + '" title="Copiar">' +
                            '<i class="bi bi-clipboard"></i>' +
                        '</button>' +
                    '</span>' +
               '</div>';
    }

    function formatarAcao(valor) {
        var mapa = {
            'notificar': 'Notificar',
            'notificado': 'Notificado',
            'aguardando_assinatura': 'Aguardando assinatura',
            'isento': 'Isento',
            'nao_notificar': 'Não Notificar'
        };
        return mapa[valor] || valor || '';
    }

    var toastTimer = null;
    function mostrarToast(msg) {
        var t = document.getElementById('toastCopiaDetalhes');
        if (!t) return;
        t.innerHTML = '<i class="bi bi-check-circle-fill"></i> ' + escapeHtml(msg);
        t.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function() { t.classList.remove('show'); }, 2000);
    }

    // ============================================================
    // ✅ CÓPIA — projeto para HTTP (sem Clipboard API)
    // ============================================================
    // Em HTTP o navegador desabilita navigator.clipboard. A única
    // via é document.execCommand('copy'). Para ele funcionar de
    // forma confiável precisamos de:
    //   1) Um <textarea> (não <span>) — elemento nativamente
    //      focável, com seleção suportada em todos os browsers.
    //   2) opacity: 1 (não 0.01) — em HTTP alguns browsers
    //      recusam copiar de elementos "invisíveis".
    //   3) Posicionamento off-screen (left: -9999px) para
    //      esconder visualmente sem parecer "oculto".
    //   4) Interceptação do evento 'copy' para escrever direto
    //      em e.clipboardData (evita depender do valor nativo).
    // ============================================================
    function copiarComEvento(texto) {
        var activeEl = document.activeElement;

        // Usa <textarea> — nativamente focável e selecionável
        var ta = document.createElement('textarea');
        ta.value = texto;
        ta.setAttribute('readonly', '');
        ta.setAttribute('aria-hidden', 'true');

        // FIX PRINCIPAL: left:-9999px + opacity:1
        // (em vez de opacity:0.01 no canto da tela)
        ta.style.cssText =
            'position:fixed !important;' +
            'top:0 !important;' +
            'left:-9999px !important;' +
            'width:200px !important;' +
            'height:50px !important;' +
            'padding:0 !important;' +
            'border:0 !important;' +
            'outline:0 !important;' +
            'box-shadow:none !important;' +
            'background:transparent !important;' +
            'color:transparent !important;' +
            'opacity:1 !important;' +
            'font-size:16px !important;' +
            'z-index:2147483647 !important;';

        // Anexa DENTRO do modal aberto (FocusTrap do Bootstrap
        // só permite foco/seleção dentro do modal)
        var modalEl = document.getElementById('modalDetalhes');
        var container = (modalEl && modalEl.classList.contains('show')) ? modalEl : document.body;
        container.appendChild(ta);

        // Força reflow (alguns engines precisam "ver" o elemento
        // antes de permitir select())
        void ta.offsetHeight;

        var copiou = false;
        var handlerRodou = false;

        var handler = function(e) {
            handlerRodou = true;
            if (!e.clipboardData) {
                console.warn('[copiar] e.clipboardData está null — abortando');
                return;
            }
            try {
                e.clipboardData.setData('text/plain', texto);
                e.preventDefault();
                copiou = true;
                console.log('[copiar] setData OK para:', JSON.stringify(texto));
            } catch (err) {
                console.error('[copiar] setData falhou:', err);
            }
        };

        // capture=true para garantir prioridade sobre outros handlers
        document.addEventListener('copy', handler, true);

        try {
            ta.focus({ preventScroll: true });
            ta.select();
            ta.setSelectionRange(0, ta.value.length);

            var execResult = document.execCommand('copy');
            console.log('[copiar] execCommand:', execResult,
                        '| handler rodou:', handlerRodou,
                        '| copiou:', copiou);

            // Fallback: se execCommand retornou true mas nosso
            // handler não rodou, o copy nativo já copiou o valor
            // do textarea (que é o texto desejado).
            if (execResult && !handlerRodou) {
                console.log('[copiar] confiando no execCommand nativo');
                copiou = true;
            }
        } catch (e) {
            console.error('[copiar] execCommand lançou exceção:', e);
        } finally {
            document.removeEventListener('copy', handler, true);
            ta.remove();

            // Restaura foco original para não quebrar o FocusTrap
            if (activeEl && typeof activeEl.focus === 'function' && activeEl !== document.body) {
                try { activeEl.focus({ preventScroll: true }); } catch (e) {}
            }
        }

        return copiou;
    }

    // Handler único do botão de copiar chip
    $(document).on('click', '.btn-copiar-chip', function(e) {
        e.preventDefault();
        e.stopPropagation();

        // .attr() em vez de .data(): .data() faz cache e converte
        // strings numéricas para number, cortando zeros à esquerda
        var texto = $(this).attr('data-copiar');
        if (texto === null || texto === undefined) return;
        texto = String(texto).trim();
        if (!texto) return;

        console.log('========================================');
        console.log('[copiar] texto:', JSON.stringify(texto));
        console.log('[copiar] secureContext:', window.isSecureContext);
        console.log('[copiar] has clipboard API:', !!(navigator.clipboard && navigator.clipboard.writeText));

        // 1ª tentativa: execCommand + copy event (única via em HTTP)
        if (copiarComEvento(texto)) {
            mostrarToast('Copiado: ' + texto);
            return;
        }

        // 2ª tentativa: Clipboard API (só se estiver em HTTPS)
        if (navigator.clipboard && window.isSecureContext && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(texto).then(function() {
                mostrarToast('Copiado: ' + texto);
            }).catch(function(err) {
                console.error('[copiar] clipboard API falhou:', err);
                mostrarToast('Erro ao copiar — veja o console');
            });
            return;
        }

        console.error('[copiar] todos os métodos falharam');
        mostrarToast('Erro ao copiar — veja o console');
    });

    function montarDetalhes(d) {
        var html = '';

        // ---------- BLOCO CONTRATUAL ----------
        html += '<div class="det-bloco contratual">';
        html += '  <div class="det-bloco-titulo"><i class="bi bi-file-earmark-text text-primary"></i> Bloco Contratual</div>';
        html += '  <div class="row">';
        html += '    <div class="col-md-6">';
        html +=        item('Contrato',   valorOuVazio(d.instrumento), 'bi-file-text');
        html +=        item('UF',         valorOuVazio(d.uf),          'bi-geo-alt');
        html +=        item('Lote',       valorOuVazio(d.lote),        'bi-tag');
        html += '    </div>';
        html += '    <div class="col-md-6">';
        html +=        item('BR',         valorOuVazio(d.br),          'bi-signpost');
        html +=        item('Nome Usual', valorOuVazio(d.nome_usual),  'bi-building');
        html +=        item('Subtrecho',  valorOuVazio(d.subtrecho),   'bi-diagram-2');
        html += '    </div>';
        html += '  </div>';
        html +=    item('Empresa', valorOuVazio(d.empresa), 'bi-briefcase', true);
        html +=    item('Objeto',  valorOuVazio(d.objeto_contrato), 'bi-file-text', true);
        html +=    item('Resumo Informativo', valorOuVazio(d.resumo_informativo), 'bi-info-circle', true);
        html += '</div>';

        // ---------- BLOCO PROCESSOS ----------
        html += '<div class="det-bloco processos">';
        html += '  <div class="det-bloco-titulo"><i class="bi bi-diagram-3 text-warning"></i> Bloco Processos</div>';
        html += '  <div class="processo-grupo">';
        html += '    <div class="processo-grupo-titulo"><i class="bi bi-folder"></i> Processo Base</div>';
        html +=      renderizarProcessos(d.processo_base);
        html += '  </div>';
        html += '  <div class="processo-grupo">';
        html += '    <div class="processo-grupo-titulo"><i class="bi bi-diagram-2"></i> Processo de Projetos</div>';
        html +=      renderizarProcessos(d.processo_projeto);
        html += '  </div>';
        html += '  <div class="processo-grupo">';
        html += '    <div class="processo-grupo-titulo"><i class="bi bi-bell"></i> Processo Notificações SR/UF</div>';
        html +=      renderizarProcessos(d.processo_notificacao_sr_uf || d.n_sei_oficio_cobranca_cronograma);
        html += '  </div>';
        html += '</div>';

        // ---------- BLOCO CRONOGRAMAS ----------
        html += '<div class="det-bloco cronograma">';
        html += '  <div class="det-bloco-titulo"><i class="bi bi-calendar-check text-success"></i> Bloco Cronogramas</div>';
        html += '  <div class="row">';
        html += '    <div class="col-md-6">';
        html +=        item('SEI Cronograma',        renderizarCampoCopiavel(d.cronograma_sei), 'bi-hash');
        html +=        item('Data Cronograma',       valorOuVazio(formatarData(d.data_termino_projeto_cronog)), 'bi-calendar-event');
        html +=        item('Status Cronograma',     valorOuVazio(d.situacao_cronograma), 'bi-flag');
        html +=        item('Ofício de Notificação', renderizarCampoCopiavel(d.n_sei_oficio_cobranca_cronograma), 'bi-file-earmark-text');
        html += '    </div>';
        html += '    <div class="col-md-6">';
        html +=        item('Data Notificação', valorOuVazio(formatarData(d.data_ultima_notificacao)), 'bi-calendar-check');
        html +=        item('Status Ação',      valorOuVazio(formatarAcao(d.status_acao)), 'bi-check2-square');
        html +=        item('PAAR',             valorOuVazio(d.paar), 'bi-file-earmark');
        html += '    </div>';
        html += '  </div>';
        html +=    item('Justificativa do Cronograma', valorOuVazio(d.justificativa_cronograma), 'bi-chat-left-text', true);
        html += '</div>';

        return html;
    }

    window.verDetalhes = function(id) {
        if (!id) return;

        var modalEl = document.getElementById('modalDetalhes');
        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);

        document.getElementById('detalhesConteudo').innerHTML =
            '<p class="text-muted text-center"><span class="spinner-border spinner-border-sm me-2"></span> Carregando...</p>';
        document.getElementById('modalDetalhesTitulo').textContent = '';

        $.ajax({
            url: 'ajax_rdci.php',
            method: 'GET',
            data: { action: 'detalhes', id: id },
            dataType: 'json',
            success: function(data) {
                if (data.error) {
                    document.getElementById('detalhesConteudo').innerHTML =
                        '<p class="text-danger">' + escapeHtml(data.error) + '</p>';
                    modal.show();
                    return;
                }
                document.getElementById('modalDetalhesTitulo').textContent = data.instrumento || '';
                document.getElementById('detalhesConteudo').innerHTML = montarDetalhes(data);
                modal.show();
            },
            error: function() {
                document.getElementById('detalhesConteudo').innerHTML =
                    '<p class="text-danger">Erro ao carregar detalhes.</p>';
                modal.show();
            }
        });
    };
})();
</script>