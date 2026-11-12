<?php
// ============================================================
// View analise_orc_ranking.php
// Ranking de contratos — Análise Orçamentária (Atlas)
// ============================================================
if (session_status() === PHP_SESSION_NONE) @session_start();
if (empty($_SESSION['usuario_id']) && empty($_SESSION['usuario_nivel'])) {
    header('Location: login'); exit;
}
if (!isset($_SESSION['setor_slug'])) {
    header('Location: selecionar_setor'); exit;
}

/** @var array $acoes */
$exercicio = (int)($exercicio ?? date('Y'));
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Ranking — Análise Orçamentária (SISPRO)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        body { background:#f8f9fc; padding-top:70px; }
        .container-fluid { max-width:98%; }
        .card { border:none; border-radius:16px; box-shadow:0 8px 30px rgba(0,0,0,0.08); background:#fff; padding:20px 25px; }
        .sistema-titulo { font-weight:700; color:#004a8f; font-size:1.4rem; letter-spacing:1px; }
        .btn-sm { padding:0.2rem 0.5rem; font-size:0.75rem; }
        .select2-container .select2-selection--multiple { min-height:38px; }
        .badge-filtro { background:#e9ecef; color:#2c3e50; font-weight:500; padding:6px 12px; border-radius:20px;
            margin-right:8px; margin-bottom:4px; display:inline-flex; align-items:center; gap:6px; }
        .badge-filtro .remover-filtro { cursor:pointer; opacity:.7; }
        .badge-filtro .remover-filtro:hover { opacity:1; }

        /* Cards resumo */
        .sum-card { background:#fff; border-radius:14px; box-shadow:0 4px 14px rgba(0,0,0,.06);
            padding:14px 16px; display:flex; flex-direction:column; gap:4px; height:100%; }
        .sum-card .lbl { font-size:.68rem; text-transform:uppercase; color:#6c757d;
            letter-spacing:.05em; font-weight:700; }
        .sum-card .val { font-size:1.5rem; font-weight:800; color:#1f2d3d; line-height:1.1; }
        .sum-card .sub { font-size:.72rem; color:#6c757d; }
        .sum-card.farol-VERDE   { border-left:5px solid #2e7d32; }
        .sum-card.farol-AMARELO { border-left:5px solid #f9a825; }
        .sum-card.farol-VERMELHO{ border-left:5px solid #c62828; }
        .sum-card.farol-SEM     { border-left:5px solid #9ca3af; }
        .sum-card.tipo          { border-left:5px solid #0d6efd; }
        .sum-card.total         { border-left:5px solid #0d3b66; background:linear-gradient(135deg,#eef5ff,#fff); }

        /* Tabela ranking */
        table.tbl-ranking { font-size:.83rem; margin-bottom:0; }
        table.tbl-ranking thead th {
            background:#f8fafc; color:#495057; font-weight:700; font-size:.7rem;
            text-transform:uppercase; letter-spacing:.03em; white-space:nowrap;
            position:sticky; top:0; z-index:5; border-bottom:2px solid #dee2e6;
        }
        table.tbl-ranking thead th.sortable { cursor:pointer; user-select:none; }
        table.tbl-ranking thead th.sortable:hover { background:#eef5ff; }
        table.tbl-ranking thead th .sort-icon { color:#adb5bd; font-size:.75rem; margin-left:3px; }
        table.tbl-ranking thead th.sorted .sort-icon { color:#0d6efd; }
        table.tbl-ranking tbody tr:hover td { background:#f4f8ff; }
        table.tbl-ranking td { vertical-align:middle; padding:6px 8px; }

        .farol-badge { display:inline-block; padding:.22rem .65rem; border-radius:999px; color:#fff;
            font-size:.72rem; font-weight:600; white-space:nowrap; }
        .farol-VERDE{background:#2e7d32}
        .farol-AMARELO{background:#f9a825;color:#1f2937}
        .farol-VERMELHO{background:#c62828}
        .farol-SEM{background:#9ca3af}
        .perf-ok  { color:#198754; font-weight:600; }
        .perf-med { color:#b98900; font-weight:600; }
        .perf-bad { color:#c62828; font-weight:600; }

        .tipo-badge { font-size:.65rem; padding:2px 7px; border-radius:5px; font-weight:700;
            background:#e0e7ff; color:#3730a3; }

        /* Linha TOTAL */
        table.tbl-ranking tfoot td {
            background:#eef5ff !important;
            font-weight:800;
            color:#0d3b66;
            border-top:2px solid #0d3b66;
            padding:8px;
            font-size:.85rem;
        }

        /* Modal / aviso */
        .loading-overlay { position:absolute; inset:0; background:rgba(255,255,255,.85);
            display:flex; align-items:center; justify-content:center; z-index:99;
            border-radius:16px; }

        .chart-wrap { position:relative; height:260px; }
        .chart-sm   { position:relative; height:220px; }

        @media (max-width: 767px) {
            .sum-card .val { font-size:1.2rem; }
        }
    </style>
</head>
<body>
<?php include APP_PATH . '/Views/header_atlas.php'; ?>

<div class="container-fluid mt-4">
    <div class="card" style="position:relative;">
        <div id="loading" class="loading-overlay" hidden>
            <div class="text-center">
                <div class="spinner-border text-primary mb-2"></div>
                <div class="small text-muted">Carregando contratos…</div>
            </div>
        </div>

        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <h2 class="sistema-titulo mb-0">
                    <i class="bi bi-trophy"></i> Ranking de Contratos
                </h2>
                <span class="badge bg-light text-dark border">
                    <i class="bi bi-calendar3"></i> Exercício: <strong><?= $h($exercicio) ?></strong>
                </span>
            </div>
            <div class="d-flex gap-2 align-items-center flex-wrap">
                <button type="button" id="btn-export-csv" class="btn btn-outline-success btn-sm">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Exportar CSV
                </button>
                <button type="button" id="btn-recarregar" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-arrow-clockwise"></i> Recarregar
                </button>
                <a href="analise_orc" class="btn btn-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i> Voltar
                </a>
            </div>
        </div>

        <!-- FILTROS -->
        <div class="row g-2 mb-3 align-items-end">
            <div class="col-lg-3 col-md-4 col-sm-6 col-12">
                <label class="form-label" style="font-size:.72rem;">Ação orçamentária</label>
                <select id="r-acao" class="form-select" multiple>
                    <?php foreach ($acoes as $a): ?>
                        <option value="<?= $h($a['id']) ?>"><?= $h($a['codigo'].' — '.$a['descricao']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-2 col-md-3 col-sm-6 col-12">
                <label class="form-label" style="font-size:.72rem;">Tipo</label>
                <select id="r-tipo" class="form-select" multiple>
                    <option value="OBRA">Obra</option>
                    <option value="SUPERVISAO">Supervisão</option>
                    <option value="MEIO_AMBIENTE">Meio Ambiente</option>
                    <option value="DESAPROPRIACAO">Desapropriação</option>
                    <option value="OUTROS">Outros</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-2 col-sm-6 col-12">
                <label class="form-label" style="font-size:.72rem;">Superintendência</label>
                <select id="r-super" class="form-select" multiple></select>
            </div>
            <div class="col-lg-2 col-md-3 col-sm-6 col-12">
                <label class="form-label" style="font-size:.72rem;">Farol</label>
                <select id="r-farol" class="form-select" multiple>
                    <option value="VERDE">🟢 Verde</option>
                    <option value="AMARELO">🟡 Amarelo</option>
                    <option value="VERMELHO">🔴 Vermelho</option>
                    <option value="SEM">⚪ Sem farol</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-6 col-sm-6 col-12">
                <label class="form-label" style="font-size:.72rem;">Buscar contrato</label>
                <input type="text" id="r-busca" class="form-control" placeholder="Ex.: 00245">
            </div>
            <div class="col-lg-1 col-md-2 col-sm-6 col-6 d-flex align-items-end">
                <button type="button" id="r-limpar" class="btn btn-outline-secondary w-100" title="Limpar filtros">
                    <i class="bi bi-x-circle"></i>
                </button>
            </div>
        </div>

        <!-- RESUMO -->
        <div class="row g-3 mb-3">
            <div class="col-6 col-md-3 col-lg-2">
                <div class="sum-card total">
                    <div class="lbl">Total contratos</div>
                    <div class="val" id="sum-total">—</div>
                    <div class="sub" id="sum-total-sub">—</div>
                </div>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <div class="sum-card farol-VERDE">
                    <div class="lbl">🟢 Verde</div>
                    <div class="val" id="sum-verde">—</div>
                    <div class="sub">Cobertura &gt; 3 meses</div>
                </div>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <div class="sum-card farol-AMARELO">
                    <div class="lbl">🟡 Amarelo</div>
                    <div class="val" id="sum-amarelo">—</div>
                    <div class="sub">2 a 3 meses</div>
                </div>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <div class="sum-card farol-VERMELHO">
                    <div class="lbl">🔴 Vermelho</div>
                    <div class="val" id="sum-vermelho">—</div>
                    <div class="sub">até 1 mês</div>
                </div>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <div class="sum-card farol-SEM">
                    <div class="lbl">⚪ Sem farol</div>
                    <div class="val" id="sum-sem">—</div>
                    <div class="sub">sem medição/concluído</div>
                </div>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <div class="sum-card tipo">
                    <div class="lbl">Total medido no ano</div>
                    <div class="val" id="sum-medido" style="font-size:1.05rem">—</div>
                    <div class="sub">soma dos contratos filtrados</div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-12 col-md-6 col-lg-4">
                <div class="sum-card tipo">
                    <div class="lbl">Por tipo de contrato</div>
                    <div id="sum-tipos" style="font-size:.85rem;line-height:1.7">—</div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-4">
                <div class="sum-card tipo">
                    <div class="lbl">Top ações (por nº de contratos)</div>
                    <div id="sum-acoes" style="font-size:.85rem;line-height:1.7">—</div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-4">
                <div class="sum-card tipo">
                    <div class="lbl">Por superintendência</div>
                    <div id="sum-supers" style="font-size:.85rem;line-height:1.7">—</div>
                </div>
            </div>
        </div>

        <!-- MODO DE RANKING -->
        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
            <div class="d-flex gap-2 align-items-center flex-wrap">
                <label class="small fw-bold mb-0" for="r-modo">Modo de ranking:</label>
                <select id="r-modo" class="form-select form-select-sm" style="width:auto;min-width:300px">
                    <option value="farol_critico">Farol — mais crítico primeiro (Vermelho → Amarelo → Verde → Sem)</option>
                    <option value="performance_melhor">Melhor performance (% desvio menor)</option>
                    <option value="performance_pior">Pior performance (% desvio maior)</option>
                    <option value="medido_maior">Mais medido no ano</option>
                    <option value="medido_menor">Menos medido no ano</option>
                    <option value="liquidado_maior">Mais faturado (liquidado)</option>
                    <option value="saldo_pendente_maior">Maior saldo pendente (medido − liquidado)</option>
                    <option value="cobertura_maior">Maior cobertura (meses)</option>
                    <option value="cobertura_menor">Menor cobertura (meses)</option>
                    <option value="total_maior">Maior valor de instrumento</option>
                    <option value="total_menor">Menor valor de instrumento</option>
                </select>
                <small class="text-muted">· Dica: clique em qualquer cabeçalho da tabela para reordenar</small>
            </div>
            <small class="text-muted" id="info-count">—</small>
        </div>

        <!-- TABELA -->
        <div class="table-responsive" style="max-height:620px;overflow-y:auto;">
            <table class="table table-hover tbl-ranking" id="tbl-ranking">
                <thead>
                    <tr>
                        <th class="sortable" data-col="numero">Contrato <i class="bi bi-arrow-down-up sort-icon"></i></th>
                        <th class="sortable" data-col="acao_codigo">Ação <i class="bi bi-arrow-down-up sort-icon"></i></th>
                        <th class="sortable" data-col="tipo">Tipo <i class="bi bi-arrow-down-up sort-icon"></i></th>
                        <th class="sortable" data-col="superintendencia">Superint. <i class="bi bi-arrow-down-up sort-icon"></i></th>
                        <th class="sortable text-end" data-col="total_instrumento">Total Instr. <i class="bi bi-arrow-down-up sort-icon"></i></th>
                        <th class="sortable text-end" data-col="disponivel_real">Valor Disp. <i class="bi bi-arrow-down-up sort-icon"></i></th>
                        <th class="sortable text-end" data-col="cobertura_meses">Cobertura <i class="bi bi-arrow-down-up sort-icon"></i></th>
                        <th class="sortable" data-col="farol">Farol <i class="bi bi-arrow-down-up sort-icon"></i></th>
                        <th class="sortable text-end" data-col="diferenca_pct">% Desvio <i class="bi bi-arrow-down-up sort-icon"></i></th>
                        <th class="sortable text-end" data-col="medido_acum">Medido <i class="bi bi-arrow-down-up sort-icon"></i></th>
                        <th class="sortable text-end" data-col="liquidado_acum">Liquidado <i class="bi bi-arrow-down-up sort-icon"></i></th>
                        <th class="sortable text-end" data-col="saldo_medido_nao_liq">Saldo Pend. <i class="bi bi-arrow-down-up sort-icon"></i></th>
                    </tr>
                </thead>
                <tbody id="tbl-body">
                    <tr><td colspan="12" class="text-center text-muted py-4">
                        <span class="spinner-border spinner-border-sm"></span> Carregando…
                    </td></tr>
                </tbody>
                <tfoot id="tbl-tfoot"></tfoot>
            </table>
        </div>

        <!-- GRÁFICOS -->
        <div class="row g-3 mt-4">
            <div class="col-12 col-md-4">
                <div class="card" style="padding:14px;">
                    <h6 class="mb-2" style="font-size:.82rem">Distribuição por tipo</h6>
                    <div class="chart-sm"><canvas id="chart-tipo"></canvas></div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card" style="padding:14px;">
                    <h6 class="mb-2" style="font-size:.82rem">Distribuição por farol</h6>
                    <div class="chart-sm"><canvas id="chart-farol"></canvas></div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card" style="padding:14px;">
                    <h6 class="mb-2" style="font-size:.82rem">Top 8 ações (contratos)</h6>
                    <div class="chart-wrap"><canvas id="chart-acao"></canvas></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const API = window.location.pathname.replace(/\/ranking.*$/, '');
const EXERCICIO = <?= (int)$exercicio ?>;

const $el = (s) => document.querySelector(s);
const brl = (v) => (v == null ? '—' : Number(v).toLocaleString('pt-BR', { style:'currency', currency:'BRL' }));
const pct = (v) => (v == null ? '—' : Number(v).toLocaleString('pt-BR', { minimumFractionDigits:2 }) + '%');

let CONTRATOS = [];   // todos os contratos carregados
let FILTRADOS = [];   // após filtros
let ORDENACAO = { col: 'farol', dir: 'asc' }; // default = modo farol crítico

let chartTipo, chartFarol, chartAcao;

const TIPO_LABEL = {
    OBRA:            'Obra',
    SUPERVISAO:      'Supervisão',
    MEIO_AMBIENTE:   'Meio Ambiente',
    DESAPROPRIACAO:  'Desapropriação',
    OUTROS:          'Outros',
};

// ================================================================
// AUTENTICAÇÃO
// ================================================================
let _redirLogin = false;
function _verificaSessao(resp) {
    if (resp.status === 401) {
        if (_redirLogin) return true;
        _redirLogin = true;
        alert('Sua sessão expirou. Faça login novamente.');
        const back = encodeURIComponent(window.location.pathname + window.location.search);
        window.location.href = 'login?redirect=' + back;
        return true;
    }
    return false;
}
async function fetchAuth(url, opts = {}) {
    const resp = await fetch(url, opts);
    if (_verificaSessao(resp)) throw new Error('Sessão expirada');
    return resp;
}
async function apiGet(path) {
    const resp = await fetchAuth(`${API}/${path}`, { headers: { 'X-Requested-With':'XMLHttpRequest' } });
    const ct = resp.headers.get('Content-Type') || '';
    if (!ct.includes('application/json')) {
        console.error(`[${API}/${path}] não-JSON (HTTP ${resp.status})`, await resp.text());
        throw new Error(`Resposta inválida do servidor (HTTP ${resp.status})`);
    }
    return await resp.json();
}

// ================================================================
// INICIALIZAÇÃO
// ================================================================
$(function () {
    $.fn.select2.defaults.set('language', {
        noResults: () => 'Nenhum resultado',
        searching: () => 'Buscando…',
        removeAllItems: () => 'Limpar',
    });

    $('.form-select[multiple]').select2({
        placeholder: 'Todos',
        allowClear: true,
        width: '100%',
    });

    $('#r-acao, #r-tipo, #r-super, #r-farol').on('change', aplicarFiltrosERender);

    $('#r-busca').on('input', debounce(aplicarFiltrosERender, 250));
    $('#r-modo').on('change', function () {
        ORDENACAO = modoParaOrdenacao(this.value);
        renderTabela();
    });
    $('#r-limpar').on('click', limparFiltros);
    $('#btn-recarregar').on('click', carregarTudo);
    $('#btn-export-csv').on('click', exportCSV);

    document.querySelectorAll('th.sortable').forEach(th => {
        th.addEventListener('click', () => {
            const col = th.dataset.col;
            if (ORDENACAO.col === col) {
                ORDENACAO.dir = ORDENACAO.dir === 'asc' ? 'desc' : 'asc';
            } else {
                ORDENACAO.col = col;
                ORDENACAO.dir = col === 'numero' || col === 'acao_codigo' || col === 'tipo' || col === 'superintendencia' ? 'asc' : 'desc';
            }
            renderTabela();
        });
    });

    carregarTudo();
});

function debounce(fn, ms) {
    let t;
    return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); };
}

async function carregarTudo() {
    mostrarLoading(true);
    try {
        const data = await apiGet(`ranking_data?exercicio=${EXERCICIO}`);
        if (data.erro) { alert(data.erro); return; }

        CONTRATOS = Array.isArray(data.contratos) ? data.contratos : [];

        // Preenche select de superintendências
        const supers = [...new Set(CONTRATOS.map(c => c.superintendencia).filter(Boolean))].sort();
        const selSup = $el('#r-super');
        selSup.innerHTML = '';
        supers.forEach(s => selSup.appendChild(new Option(s, s)));
        $('#r-super').trigger('change.select2');

        aplicarFiltrosERender();
    } catch (e) {
        alert('Erro: ' + e.message);
    } finally {
        mostrarLoading(false);
    }
}

function mostrarLoading(show) {
    const el = $el('#loading');
    if (!el) return;
    el.hidden = !show;
}

// ================================================================
// FILTROS + RENDER
// ================================================================
function valorMulti(id) {
    return Array.from(document.querySelectorAll(`#${id} option:checked`)).map(o => o.value);
}

function aplicarFiltrosERender() {
    const acoes  = valorMulti('r-acao');
    const tipos  = valorMulti('r-tipo');
    const supers = valorMulti('r-super');
    const farois = valorMulti('r-farol');
    const busca  = ($el('#r-busca').value || '').trim().toLowerCase();

    FILTRADOS = CONTRATOS.filter(c => {
        if (acoes.length  && !acoes.includes(String(c.acao_id))) return false;
        if (tipos.length  && !tipos.includes(c.tipo))            return false;
        if (supers.length && !supers.includes(c.superintendencia)) return false;
        if (farois.length) {
            const f = c.farol || 'SEM';
            if (!farois.includes(f)) return false;
        }
        if (busca && !String(c.numero).toLowerCase().includes(busca)) return false;
        return true;
    });

    $el('#info-count').textContent = `${FILTRADOS.length} de ${CONTRATOS.length} contrato(s) · exercício ${EXERCICIO}`;

    renderResumo(FILTRADOS);
    renderTabela();
    renderGraficos(FILTRADOS);
}

function limparFiltros() {
    $('#r-acao, #r-tipo, #r-super, #r-farol').val(null).trigger('change.select2');
    $el('#r-busca').value = '';
    $el('#r-modo').value = 'farol_critico';
    ORDENACAO = modoParaOrdenacao('farol_critico');
    aplicarFiltrosERender();
}

// ================================================================
// RESUMO
// ================================================================
function renderResumo(lista) {
    const total = lista.length;

    const porFarol = { VERDE: 0, AMARELO: 0, VERMELHO: 0, SEM: 0 };
    const porTipo  = {};
    const porSuper = {};
    const porAcao  = {};
    let somaMedido = 0;

    lista.forEach(c => {
        const f = c.farol || 'SEM';
        porFarol[f] = (porFarol[f] || 0) + 1;

        const t = c.tipo || 'OUTROS';
        porTipo[t] = (porTipo[t] || 0) + 1;

        if (c.superintendencia) porSuper[c.superintendencia] = (porSuper[c.superintendencia] || 0) + 1;

        if (c.acao_codigo) {
            const key = `${c.acao_codigo} — ${c.acao_descricao || ''}`;
            porAcao[key] = (porAcao[key] || 0) + 1;
        }

        somaMedido += Number(c.medido_acum || 0);
    });

    $el('#sum-total').textContent = String(total);
    $el('#sum-total-sub').textContent = `${Object.keys(porAcao).length} ação(ões)`;
    $el('#sum-verde').textContent    = String(porFarol.VERDE);
    $el('#sum-amarelo').textContent  = String(porFarol.AMARELO);
    $el('#sum-vermelho').textContent = String(porFarol.VERMELHO);
    $el('#sum-sem').textContent      = String(porFarol.SEM);
    $el('#sum-medido').textContent   = brl(somaMedido);

    // tipos
    const tiposOrdem = ['OBRA','SUPERVISAO','MEIO_AMBIENTE','DESAPROPRIACAO','OUTROS'];
    let htmlTipos = '';
    tiposOrdem.forEach(t => {
        const qtd = porTipo[t] || 0;
        htmlTipos += `<div class="d-flex justify-content-between">
            <span>${TIPO_LABEL[t]}</span><strong>${qtd}</strong></div>`;
    });
    $el('#sum-tipos').innerHTML = htmlTipos;

    // top ações
    const acoesOrdenadas = Object.entries(porAcao).sort((a,b) => b[1] - a[1]).slice(0, 8);
    $el('#sum-acoes').innerHTML = acoesOrdenadas.length
        ? acoesOrdenadas.map(([nome, qtd]) =>
            `<div class="d-flex justify-content-between gap-2">
                <span class="text-truncate" title="${escapeHtml(nome)}">${escapeHtml(nome)}</span>
                <strong>${qtd}</strong></div>`).join('')
        : '<span class="text-muted">—</span>';

    // supers
    const supersOrdenadas = Object.entries(porSuper).sort((a,b) => b[1] - a[1]).slice(0, 8);
    $el('#sum-supers').innerHTML = supersOrdenadas.length
        ? supersOrdenadas.map(([nome, qtd]) =>
            `<div class="d-flex justify-content-between">
                <span>${escapeHtml(nome)}</span><strong>${qtd}</strong></div>`).join('')
        : '<span class="text-muted">—</span>';
}

function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
}

// ================================================================
// ORDENAÇÃO — modos predefinidos
// ================================================================
function modoParaOrdenacao(modo) {
    switch (modo) {
        case 'farol_critico':           return { col:'farol', dir:'asc' };
        case 'performance_melhor':      return { col:'diferenca_pct', dir:'asc' };
        case 'performance_pior':        return { col:'diferenca_pct', dir:'desc' };
        case 'medido_maior':            return { col:'medido_acum', dir:'desc' };
        case 'medido_menor':            return { col:'medido_acum', dir:'asc' };
        case 'liquidado_maior':         return { col:'liquidado_acum', dir:'desc' };
        case 'saldo_pendente_maior':    return { col:'saldo_medido_nao_liq', dir:'desc' };
        case 'cobertura_maior':         return { col:'cobertura_meses', dir:'desc' };
        case 'cobertura_menor':         return { col:'cobertura_meses', dir:'asc' };
        case 'total_maior':             return { col:'total_instrumento', dir:'desc' };
        case 'total_menor':             return { col:'total_instrumento', dir:'asc' };
        default:                        return { col:'farol', dir:'asc' };
    }
}

const FAROL_ORDEM = { VERMELHO: 0, AMARELO: 1, VERDE: 2, SEM: 3 };

function valorParaOrdenacao(c, col) {
    if (col === 'farol')       return FAROL_ORDEM[c.farol || 'SEM'] ?? 4;
    if (col === 'diferenca_pct') {
        const v = c.performance?.diferenca_pct;
        return v == null ? Number.POSITIVE_INFINITY : Number(v);
    }
    if (col === 'numero')      return String(c.numero || '').padStart(50, '0');
    if (col === 'acao_codigo') return String(c.acao_codigo || '');
    if (col === 'tipo')        return String(c.tipo || '');
    if (col === 'superintendencia') return String(c.superintendencia || '');

    // numéricos
    const v = c[col];
    return v == null ? Number.NEGATIVE_INFINITY : Number(v);
}

// ================================================================
// TABELA
// ================================================================
function renderTabela() {
    const tb = $el('#tbl-body');
    const col = ORDENACAO.col;
    const dir = ORDENACAO.dir === 'asc' ? 1 : -1;

    const ordenados = [...FILTRADOS].sort((a, b) => {
        const va = valorParaOrdenacao(a, col);
        const vb = valorParaOrdenacao(b, col);
        if (va < vb) return -1 * dir;
        if (va > vb) return  1 * dir;
        // desempate por contrato
        return String(a.numero).localeCompare(String(b.numero), 'pt-BR');
    });

    if (!ordenados.length) {
        tb.innerHTML = `<tr><td colspan="12" class="text-center text-muted py-4">
            <i class="bi bi-funnel"></i> Nenhum contrato para os filtros atuais.
        </td></tr>`;
        $el('#tbl-tfoot').innerHTML = '';
    } else {
        tb.innerHTML = ordenados.map((c, i) => rowHtml(c, i + 1)).join('');
    }

    // Atualiza indicadores "sorted" no cabeçalho
    document.querySelectorAll('th.sortable').forEach(th => {
        th.classList.toggle('sorted', th.dataset.col === col);
        const icon = th.querySelector('.sort-icon');
        if (icon) {
            icon.className = 'bi sort-icon ' +
                (th.dataset.col === col
                    ? (dir > 0 ? 'bi-arrow-up' : 'bi-arrow-down')
                    : 'bi-arrow-down-up');
        }
    });

    renderTotalTabela(ordenados);
}

function rowHtml(c, idx) {
    const farol = c.farol || 'SEM';
    const farolBadge = `<span class="farol-badge farol-${farol}">${farol === 'SEM' ? '—' : farol}</span>`;

    const desvio = c.performance?.diferenca_pct;
    let desvioCls = '';
    if (desvio != null) {
        const abs = Math.abs(desvio);
        desvioCls = abs < 10 ? 'perf-ok' : (abs < 30 ? 'perf-med' : 'perf-bad');
    }

    const tipoBadge = `<span class="tipo-badge">${TIPO_LABEL[c.tipo] || c.tipo || '—'}</span>`;
    const acaoCod  = c.acao_codigo || '—';
    const acaoDesc = (c.acao_descricao || '').slice(0, 30);

    return `<tr>
        <td><strong>${escapeHtml(c.numero)}</strong></td>
        <td title="${escapeHtml(c.acao_descricao || '')}">
            <span class="text-muted small">${escapeHtml(acaoCod)}</span>
            ${acaoDesc ? `<div class="small text-truncate" style="max-width:180px">${escapeHtml(acaoDesc)}</div>` : ''}
        </td>
        <td>${tipoBadge}</td>
        <td>${escapeHtml(c.superintendencia || '—')}</td>
        <td class="text-end">${brl(c.total_instrumento)}</td>
        <td class="text-end">${brl(c.disponivel_real)}</td>
        <td class="text-end">${c.cobertura_meses != null ? c.cobertura_meses + ' m' : '—'}</td>
        <td>${farolBadge}</td>
        <td class="text-end ${desvioCls}">${pct(desvio)}</td>
        <td class="text-end">${brl(c.medido_acum)}</td>
        <td class="text-end">${brl(c.liquidado_acum)}</td>
        <td class="text-end">${brl(c.saldo_medido_nao_liq)}</td>
    </tr>`;
}

function renderTotalTabela(lista) {
    const tf = $el('#tbl-tfoot');
    if (!lista.length) { tf.innerHTML = ''; return; }

    let totInst = 0, totDisp = 0, totMed = 0, totLiq = 0, totSaldo = 0;
    lista.forEach(c => {
        totInst  += Number(c.total_instrumento || 0);
        totDisp  += Number(c.disponivel_real || 0);
        totMed   += Number(c.medido_acum || 0);
        totLiq   += Number(c.liquidado_acum || 0);
        totSaldo += Number(c.saldo_medido_nao_liq || 0);
    });

    tf.innerHTML = `<tr>
        <td colspan="4">TOTAL (${lista.length} contrato${lista.length === 1 ? '' : 's'})</td>
        <td class="text-end">${brl(totInst)}</td>
        <td class="text-end">${brl(totDisp)}</td>
        <td class="text-end">—</td>
        <td>—</td>
        <td class="text-end">—</td>
        <td class="text-end">${brl(totMed)}</td>
        <td class="text-end">${brl(totLiq)}</td>
        <td class="text-end">${brl(totSaldo)}</td>
    </tr>`;
}

// ================================================================
// GRÁFICOS
// ================================================================
function renderGraficos(lista) {
    const porTipo  = {};
    const porFarol = { VERDE: 0, AMARELO: 0, VERMELHO: 0, SEM: 0 };
    const porAcao  = {};

    lista.forEach(c => {
        const t = TIPO_LABEL[c.tipo] || 'Outros';
        porTipo[t] = (porTipo[t] || 0) + 1;

        const f = c.farol || 'SEM';
        porFarol[f] = (porFarol[f] || 0) + 1;

        if (c.acao_codigo) {
            porAcao[c.acao_codigo] = (porAcao[c.acao_codigo] || 0) + 1;
        }
    });

    // Tipo
    if (chartTipo) chartTipo.destroy();
    chartTipo = new Chart($el('#chart-tipo'), {
        type: 'doughnut',
        data: {
            labels: Object.keys(porTipo),
            datasets: [{
                data: Object.values(porTipo),
                backgroundColor: ['#0d6efd','#6610f2','#20c997','#fd7e14','#6c757d','#e83e8c'],
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'right', labels: { boxWidth: 10, font: { size: 11 } } } },
        },
    });

    // Farol
    if (chartFarol) chartFarol.destroy();
    chartFarol = new Chart($el('#chart-farol'), {
        type: 'bar',
        data: {
            labels: ['🟢 Verde','🟡 Amarelo','🔴 Vermelho','⚪ Sem'],
            datasets: [{
                data: [porFarol.VERDE, porFarol.AMARELO, porFarol.VERMELHO, porFarol.SEM],
                backgroundColor: ['#2e7d32','#f9a825','#c62828','#9ca3af'],
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
        },
    });

    // Top ações
    const acoes = Object.entries(porAcao).sort((a,b) => b[1] - a[1]).slice(0, 8);
    if (chartAcao) chartAcao.destroy();
    chartAcao = new Chart($el('#chart-acao'), {
        type: 'bar',
        data: {
            labels: acoes.map(x => x[0]),
            datasets: [{ data: acoes.map(x => x[1]), backgroundColor: '#0d6efd' }],
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { beginAtZero: true, ticks: { precision: 0 } },
                y: { ticks: { font: { size: 10 } } },
            },
        },
    });
}

// ================================================================
// CSV
// ================================================================
function exportCSV() {
    const col = ORDENACAO.col;
    const dir = ORDENACAO.dir === 'asc' ? 1 : -1;
    const ordenados = [...FILTRADOS].sort((a, b) => {
        const va = valorParaOrdenacao(a, col);
        const vb = valorParaOrdenacao(b, col);
        if (va < vb) return -1 * dir;
        if (va > vb) return  1 * dir;
        return String(a.numero).localeCompare(String(b.numero), 'pt-BR');
    });

    const header = [
        'Contrato','Ação (código)','Ação (descrição)','Tipo','Superintendência',
        'Total Instrumento','Valor Disponível','Cobertura (meses)','Farol','Desvio %',
        'Medido no ano','Liquidado no ano','Saldo pendente'
    ];

    const linhas = ordenados.map(c => [
        c.numero ?? '',
        c.acao_codigo ?? '',
        c.acao_descricao ?? '',
        TIPO_LABEL[c.tipo] || c.tipo || '',
        c.superintendencia ?? '',
        c.total_instrumento ?? '',
        c.disponivel_real ?? '',
        c.cobertura_meses ?? '',
        c.farol ?? 'SEM',
        c.performance?.diferenca_pct ?? '',
        c.medido_acum ?? '',
        c.liquidado_acum ?? '',
        c.saldo_medido_nao_liq ?? '',
    ]);

    const csv = [header, ...linhas]
        .map(row => row.map(v => `"${String(v).replace(/"/g, '""')}"`).join(';'))
        .join('\r\n');

    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url;
    a.download = `ranking_contratos_${EXERCICIO}_${new Date().toISOString().slice(0,10)}.csv`;
    document.body.appendChild(a);
    a.click();
    setTimeout(() => { document.body.removeChild(a); URL.revokeObjectURL(url); }, 100);
}
</script>
</body>
</html>