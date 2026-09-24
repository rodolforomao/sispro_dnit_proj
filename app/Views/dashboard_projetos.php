<?php
// ============================================================
// Views/dashboard_projetos.php
// ============================================================

if (!function_exists('dashLower')) {
    function dashLower($s) {
        if (function_exists('mb_strtolower')) return mb_strtolower((string)$s, 'UTF-8');
        return strtolower((string)$s);
    }
}

if (!function_exists('dashStatusInfo')) {
    function dashStatusInfo($status) {
        $s = dashLower(trim((string)$status));

        if ($s === '' || $s === '—' || $s === 'não entregue' || $s === 'nao entregue') {
            return ['icon' => 'bi-file-earmark-x',         'cor' => 'text-secondary', 'txt' => 'text-muted'];
        }
        if (strpos($s, 'aprovado parcial') !== false) {
            return ['icon' => 'bi-check-circle',           'cor' => 'text-success',   'txt' => 'text-success'];
        }
        if (strpos($s, 'aprovado') !== false) {
            return ['icon' => 'bi-check-circle-fill',      'cor' => 'text-success',   'txt' => 'text-success fw-bold'];
        }
        if (strpos($s, 'análise parcial') !== false || strpos($s, 'analise parcial') !== false) {
            return ['icon' => 'bi-exclamation-triangle',   'cor' => 'text-warning',   'txt' => 'text-info'];
        }
        if (strpos($s, 'em análise') !== false || strpos($s, 'em analise') !== false) {
            return ['icon' => 'bi-exclamation-triangle-fill','cor' => 'text-warning','txt' => 'text-primary fw-semibold'];
        }
        if (strpos($s, 'reprovado parcial') !== false) {
            return ['icon' => 'bi-x-circle',               'cor' => 'text-danger',    'txt' => 'text-danger'];
        }
        if (strpos($s, 'reprovado') !== false) {
            return ['icon' => 'bi-x-circle-fill',          'cor' => 'text-danger',    'txt' => 'text-danger fw-bold'];
        }
        if (strpos($s, 'não encontrado') !== false || strpos($s, 'nao encontrado') !== false) {
            return ['icon' => 'bi-search',                 'cor' => 'text-secondary', 'txt' => 'text-muted'];
        }
        if (strpos($s, 'fora do escopo') !== false) {
            return ['icon' => 'bi-bullseye',               'cor' => 'text-info',      'txt' => 'text-muted'];
        }
        if (strpos($s, 'com ressalvas') !== false) {
            return ['icon' => 'bi-flag-fill',              'cor' => 'text-primary',   'txt' => 'text-primary'];
        }
        return ['icon' => 'bi-dash-circle',                'cor' => 'text-secondary', 'txt' => 'text-muted'];
    }
}
if (!function_exists('dashIcone')) {
    function dashIcone($status) {
        $i = dashStatusInfo($status);
        return '<i class="bi ' . $i['icon'] . ' ' . $i['cor'] . '" title="' . htmlspecialchars($status ?: '—') . '"></i>';
    }
}
if (!function_exists('dashTexto')) {
    function dashTexto($status) {
        $i = dashStatusInfo($status);
        return '<span class="' . $i['txt'] . '">' . htmlspecialchars($status ?: '—') . '</span>';
    }
}
if (!function_exists('dashFmt')) {
    function dashFmt($v)   { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
    function dashData($d)  {
        if (empty($d) || $d === '0000-00-00') return '—';
        try { return (new DateTime($d))->format('d/m/Y'); } catch (Exception $e) { return htmlspecialchars($d); }
    }
    function dashMoeda($v, $divisor = 1) {
        if ($v === null || $v === '') return '—';
        $v = (float)$v / $divisor;
        if ($v >= 1e9) return 'R$ ' . number_format($v / 1e9, 2, ',', '.') . ' bi';
        if ($v >= 1e6) return 'R$ ' . number_format($v / 1e6, 2, ',', '.') . ' mi';
        if ($v >= 1e3) return 'R$ ' . number_format($v / 1e3, 2, ',', '.') . ' mil';
        return 'R$ ' . number_format($v, 2, ',', '.');
    }
    function dashMoedaCompleto($v, $divisor = 1) {
        if ($v === null || $v === '') return '—';
        $v = (float)$v / $divisor;
        return 'R$ ' . number_format($v, 2, ',', '.');
    }
}

if (!function_exists('dashCoordenadasUF')) {
    function dashCoordenadasUF($uf) {
        $mapa = [
            'AC' => [-9.97,  -67.81], 'AL' => [-9.66,  -35.73], 'AP' => [ 0.03,  -51.07],
            'AM' => [-3.10,  -60.02], 'BA' => [-12.97, -38.51], 'CE' => [-3.72,  -38.54],
            'DF' => [-15.78, -47.93], 'ES' => [-20.32, -40.34], 'GO' => [-16.68, -49.25],
            'MA' => [-2.53,  -44.30], 'MT' => [-15.60, -56.10], 'MS' => [-20.44, -54.65],
            'MG' => [-19.92, -43.94], 'PA' => [-1.46,  -48.49], 'PB' => [-7.12,  -34.88],
            'PR' => [-25.43, -49.27], 'PE' => [-8.05,  -34.88], 'PI' => [-5.09,  -42.80],
            'RJ' => [-22.91, -43.17], 'RN' => [-5.79,  -35.21], 'RS' => [-30.03, -51.23],
            'RO' => [-8.76,  -63.90], 'RR' => [ 2.82,  -60.67], 'SC' => [-27.60, -48.55],
            'SP' => [-23.55, -46.63], 'SE' => [-10.91, -37.07], 'TO' => [-10.18, -48.33],
        ];
        $uf = strtoupper(trim((string)$uf));
        return $mapa[$uf] ?? [-15.7, -47.9];
    }
}

if (!function_exists('dashAgregarStatus')) {
    function dashAgregarStatus($row, array $colunas) {
        if (!$row) return '—';
        $cnt = ['aprovado'=>0,'aprovado_parcial'=>0,'em_analise'=>0,'em_analise_parcial'=>0,
                'reprovado'=>0,'reprovado_parcial'=>0,'vazio'=>0,'outros'=>0];
        foreach ($colunas as $col) {
            $v = trim((string)($row[$col] ?? ''));
            $l = dashLower($v);
            if ($v === '' || $v === '—' || $l === 'não entregue' || $l === 'nao entregue') $cnt['vazio']++;
            elseif (strpos($l, 'aprovado parcial') !== false)     $cnt['aprovado_parcial']++;
            elseif (strpos($l, 'aprovado') !== false)             $cnt['aprovado']++;
            elseif (strpos($l, 'análise parcial') !== false || strpos($l, 'analise parcial') !== false) $cnt['em_analise_parcial']++;
            elseif (strpos($l, 'análise') !== false || strpos($l, 'analise') !== false) $cnt['em_analise']++;
            elseif (strpos($l, 'reprovado parcial') !== false)    $cnt['reprovado_parcial']++;
            elseif (strpos($l, 'reprovado') !== false)            $cnt['reprovado']++;
            else                                                   $cnt['outros']++;
        }
        $total = count($colunas);
        if ($cnt['vazio'] === $total) return 'Não Entregue';
        if ($cnt['reprovado'] > 0 || $cnt['reprovado_parcial'] > 0) return 'Reprovado';
        if ($cnt['em_analise'] > 0 || $cnt['em_analise_parcial'] > 0) return 'Em Análise';
        if ($cnt['aprovado'] === $total) return 'Aprovado';
        if ($cnt['aprovado'] + $cnt['aprovado_parcial'] === $total) return 'Aprovado Parcial';
        return 'Em Análise';
    }
}

// ---------- Estrutura das disciplinas ----------
$matrizEstudos = [
    'tracado'                    => 'Traçado',
    'geologico'                  => 'Geológico',
    'topografico'                => 'Topográfico',
    'trafego'                    => 'Tráfego',
    'geotecnico'                 => 'Geotécnico',
    'hidrologico'                => 'Hidrológico',
    'hidraulico_hidrologico_oae' => 'Hidrá. e Hidrol. de OAE',
    'oae'                        => 'OAE',
    'avaliacao_ambiental'        => 'Avaliação Ambiental',
    'pavimento'                  => 'Pavimento',
];

$matrizBasico = [
    'geometrico_basico'                    => 'Geométrico',
    'terraplanagem_basico'                 => 'Terraplanagem',
    'drenagem_oac_basico'                  => 'Drenagem e OAC',
    'pavimentacao_basico'                  => 'Pavimentação',
    'iluminacao_basico'                    => 'Iluminação',
    'sinalizacao_seguranca_viaria_basico'  => 'Sinalização e Seg. Viária',
    'obras_complem_oc_basico'              => 'Obras Complement.',
    'contencoes_basico'                    => 'Contenções',
    'oae_basico'                           => 'OAE',
    'componente_ambiental_basico'          => 'Componente Ambiental',
    'desapropriacao_basico'                => 'Desapropriação',
    'passarela_basico'                     => 'Passarela',
    'interseccoes_retornos_acessos_basico' => 'Intersec./Retor. e Acessos',
    'reassentamento_basico'                => 'Reassentamento',
    'solucoes_interferencias_basico'       => 'Soluc. de Interferências',
    'paisagismo_basico'                    => 'Paisagismo',
    'restauracao_pavimento_basico'         => 'Rest. de Pavimento',
    'reabilitacao_faixa_dominio_basico'    => 'Reab. da Faixa de Domínio',
    'aduana_basico'                        => 'Aduana',
    'trafego_basico'                       => 'Tráfego',
    'orcamento_basico'                     => 'Orçamento',
];
$matrizExec = [];
foreach ($matrizBasico as $k => $v) {
    $matrizExec[str_replace('_basico', '_executivo', $k)] = $v;
}

$matrizOae = [
    'projeto_concepcao_geometrico'   => 'Concepção',
    'estudos_hidraulico_hidrologico' => 'Est. Hidrol.',
    'basico_infraestrutura'          => 'Básico - Infra',
    'basico_mesoestrutura'           => 'Básico - Meso',
    'basico_superestrutura'          => 'Básico - Super',
    'executivo_infraestrutura'       => 'Exe. - Infra',
    'executivo_mesoestrutura'        => 'Exe. - Meso',
    'executivo_superestrutura'       => 'Exe. - Super',
    'sinalizacao_viaria_nautica'     => 'Sinaliz. Viária e Náutica',
    'componente_ambiental_resumo'    => 'Comp. Ambient.',
    'complementares_marinha'         => 'Marinha',
];

// ============================================================
// Lógica: só mostra o detalhe quando um CONTRATO está selecionado
// ============================================================
$temContratoSelecionado = !empty($filtros['contrato']);
$c = ($temContratoSelecionado && $contratoAtual) ? $contratoAtual : null;

// Badges dos filtros ativos
$filtrosAplicados = [];
if (!empty($filtros['regiao']))    $filtrosAplicados[] = ['param' => 'regiao',    'label' => 'Região',    'valor' => $filtros['regiao']];
if (!empty($filtros['uf']))        $filtrosAplicados[] = ['param' => 'uf',        'label' => 'UF',        'valor' => $filtros['uf']];
if (!empty($filtros['br']))        $filtrosAplicados[] = ['param' => 'br',        'label' => 'BR',        'valor' => $filtros['br']];
if (!empty($filtros['contrato']))  $filtrosAplicados[] = ['param' => 'contrato',  'label' => 'Contrato',  'valor' => $filtros['contrato']];
if (!empty($filtros['subtrecho'])) $filtrosAplicados[] = ['param' => 'subtrecho', 'label' => 'Subtrecho', 'valor' => $filtros['subtrecho']];
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Dashboard de Projetos - SISPRO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <style>
        :root {
            --dp-azul:        #004a8f;
            --dp-azul-escuro: #0b1a4a;
            --dp-dourado-bg:  #fdf9ed;
            --dp-bege-borda:  #e6d9a8;
            --dp-offset-top:  70px;
        }
        body { padding-top: var(--dp-offset-top); }

        .dp-card {
            background:#fff; border:2px solid var(--dp-bege-borda);
            border-radius:16px; padding:20px 22px;
            box-shadow:0 6px 18px rgba(11,26,74,.06); margin-bottom:18px;
        }
        .dp-card h5 {
            color:var(--dp-azul); font-weight:700; font-size:.95rem;
            margin:0 0 14px; display:flex; align-items:center; gap:8px;
        }

        .dp-filtros-sticky {
            position:sticky; top:var(--dp-offset-top); z-index:1020;
            background:#fff; border:2px solid var(--dp-bege-borda);
            border-radius:14px; padding:12px 16px 10px;
            box-shadow:0 6px 14px rgba(11,26,74,.08); margin-bottom:18px;
        }
        .dp-filtros-sticky .form-label {
            font-size:.68rem; text-transform:uppercase; color:#7a859b; margin-bottom:2px;
        }
        .dp-filtros-sticky .form-select,
        .dp-filtros-sticky .form-control { font-size:.85rem; }

        .dp-filtros-sticky .select2-container .select2-selection--single { height: 34px; }
        .dp-filtros-sticky .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 32px; font-size:.85rem; }
        .dp-filtros-sticky .select2-container--default .select2-selection--single .select2-selection__arrow { height: 32px; }

        .dp-filtros-badges {
            display:flex; flex-wrap:wrap; gap:6px; margin-top:10px;
            border-top:1px dashed #e3e8f0; padding-top:10px;
        }
        .dp-filtro-tag {
            background:#e9ecef; color:#2c3e50; font-weight:500;
            font-size:.72rem; padding:5px 10px; border-radius:20px;
            display:inline-flex; align-items:center; gap:6px;
        }
        .dp-filtro-tag .remover-filtro { cursor:pointer; opacity:.7; text-decoration:none; color:inherit; }
        .dp-filtro-tag .remover-filtro:hover { opacity:1; }

        .dp-ident-grid {
            display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));
            gap:10px 22px;
        }
        .dp-ident-item .lbl { font-size:.72rem; color:#7a859b; text-transform:uppercase; letter-spacing:.5px; }
        .dp-ident-item .val { font-size:.95rem; font-weight:600; color:#1f2a44; word-break:break-word; }

        .dp-chip {
            background:#f7f9fe; border:1px solid #e3e8f0; border-radius:10px;
            padding:12px 14px; height:100%;
        }
        .dp-chip .lbl { font-size:.72rem; color:#7a859b; text-transform:uppercase; }
        .dp-chip .val { font-size:.95rem; font-weight:600; margin-top:4px; }

        .dp-faixa-fin {
            display:flex; gap:30px; padding:14px 20px; background:var(--dp-dourado-bg);
            border-radius:10px; border:1px solid var(--dp-bege-borda); flex-wrap:wrap;
        }
        .dp-faixa-fin .item { display:flex; align-items:center; gap:8px; }
        .dp-bolinha { width:14px; height:14px; border-radius:50%; flex-shrink:0; }
        .dp-bol-azul-claro  { background:#60a5fa; }
        .dp-bol-azul-escuro { background:#0b1a4a; }

        .dp-matriz-col {
            background:#fff; border:2px solid var(--dp-bege-borda);
            border-radius:14px; padding:16px; height:100%;
        }
        .dp-matriz-col h6 {
            color:var(--dp-azul); font-weight:700; font-size:.85rem;
            text-transform:uppercase; letter-spacing:.5px;
            border-bottom:2px solid var(--dp-bege-borda); padding-bottom:8px; margin-bottom:12px;
        }
        .dp-matriz-item {
            display:flex; align-items:center; justify-content:space-between;
            padding:6px 0; border-bottom:1px dashed #eef1f7; font-size:.87rem;
        }
        .dp-matriz-item:last-child { border-bottom:none; }
        .dp-matriz-item .lbl { color:#344054; }
        .dp-matriz-item .ico { font-size:1.1rem; }
        .dp-matriz-item.destaque .lbl { font-weight:700; color:var(--dp-azul); }

        .dp-legenda-sticky {
            position:sticky; top:calc(var(--dp-offset-top) + 130px); z-index:1015;
            background:#fff; border:2px solid var(--dp-bege-borda);
            border-radius:14px; padding:12px 18px; margin:8px 0 14px;
            display:flex; gap:16px; flex-wrap:wrap; align-items:center; font-size:.8rem;
            box-shadow:0 4px 12px rgba(11,26,74,.06);
        }
        .dp-legenda-sticky .item { display:inline-flex; align-items:center; gap:6px; }

        .dp-donut-wrap {
            position: relative;
            width: 100%;
            height: 260px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .dp-donut-wrap canvas {
            position: absolute;
            top: 0; left: 0;
            width: 100% !important;
            height: 100% !important;
        }
        .dp-donut-centro {
            position: relative;
            z-index: 2;
            text-align: center;
            pointer-events: none;
        }
        .dp-donut-centro .num {
            font-size: 2rem; font-weight: 800;
            color: var(--dp-azul-escuro); line-height: 1;
        }
        .dp-donut-centro .lbl {
            font-size: .7rem; color:#7a859b;
            text-transform: uppercase; margin-top: 4px;
        }

        .dp-kpi-num { font-size:2.2rem; font-weight:800; color:var(--dp-azul-escuro); line-height:1; }

        .dp-tabela-scroll { max-height:220px; overflow-y:auto; }
        .dp-tabela-scroll thead th {
            position:sticky; top:0; background:#f7f9fe; z-index:1;
            font-size:.72rem; text-transform:uppercase; color:#7a859b;
        }
        .dp-tabela-scroll td { font-size:.85rem; }

        #dpMapa { height: 260px; border-radius:10px; overflow:hidden; border:1px solid #e3e8f0; }

        .dp-secao-titulo {
            color:var(--dp-azul); font-weight:700; font-size:1.05rem;
            margin:22px 0 12px; display:flex; align-items:center; gap:8px;
            border-bottom:2px solid var(--dp-bege-borda); padding-bottom:6px;
        }

        .dp-cron-grid {
            display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));
            gap:10px 22px;
        }
        .dp-cron-item .lbl { font-size:.72rem; color:#7a859b; text-transform:uppercase; letter-spacing:.5px; }
        .dp-cron-item .val { font-size:.9rem; font-weight:600; color:#1f2a44; word-break:break-word; }

        .dp-acordao-txt { font-size:.85rem; color:#495057; line-height:1.45; }

        .dp-aviso-sem-filtro {
            background:#f7f9fe; border:2px dashed var(--dp-bege-borda);
            border-radius:14px; padding:24px; text-align:center; color:#7a859b;
            font-size:.95rem;
        }
        .dp-aviso-sem-filtro i { font-size:1.6rem; color:var(--dp-azul); display:block; margin-bottom:8px; }
        .dp-aviso-sem-filtro strong { color:var(--dp-azul); }
    </style>
</head>
<?php include APP_PATH . '/public/chat_widget.php'; ?>
<body>
<?php include APP_PATH . '/Views/header.php'; ?>

<div class="container-fluid mt-4">

    <!-- ============================================================
         FILTROS — padrão Notificações
         ============================================================ -->
    <div class="dp-filtros-sticky">
        <form method="GET" action="dashboard_projetos" id="formFiltros">
            <div class="row g-2 align-items-end">
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <label class="form-label">Região</label>
                    <select name="regiao" class="form-select filtro-select2">
                        <option value="">Selecione...</option>
                        <?php foreach ($regioes as $r): ?>
                            <option value="<?= dashFmt($r) ?>" <?= $filtros['regiao'] === $r ? 'selected' : '' ?>><?= dashFmt($r) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <label class="form-label">UF</label>
                    <select name="uf" class="form-select filtro-select2">
                        <option value="">Selecione...</option>
                        <?php foreach ($ufs as $u): ?>
                            <option value="<?= dashFmt($u) ?>" <?= $filtros['uf'] === $u ? 'selected' : '' ?>><?= dashFmt($u) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <label class="form-label">BR</label>
                    <select name="br" class="form-select filtro-select2">
                        <option value="">Selecione...</option>
                        <?php foreach ($brs as $b): ?>
                            <option value="<?= dashFmt($b) ?>" <?= $filtros['br'] === $b ? 'selected' : '' ?>><?= dashFmt($b) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-3 col-md-4 col-sm-6">
                    <label class="form-label">Contrato</label>
                    <select name="contrato" class="form-select filtro-select2">
                        <option value="">Selecione...</option>
                        <?php foreach ($contratosList as $inst): ?>
                            <option value="<?= dashFmt($inst) ?>" <?= $filtros['contrato'] === $inst ? 'selected' : '' ?>><?= dashFmt($inst) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-3 col-md-4 col-sm-6">
                    <label class="form-label">Subtrecho</label>
                    <select name="subtrecho" class="form-select filtro-select2">
                        <option value="">Selecione...</option>
                        <?php foreach ($subtrechos as $st): ?>
                            <option value="<?= dashFmt($st) ?>" <?= $filtros['subtrecho'] === $st ? 'selected' : '' ?>><?= dashFmt($st) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <?php if (!empty($filtrosAplicados)): ?>
            <div class="dp-filtros-badges">
                <span class="fw-bold small text-muted me-1"><i class="bi bi-funnel"></i> Filtros aplicados:</span>
                <?php foreach ($filtrosAplicados as $f): ?>
                    <?php
                        $novosGET = $_GET;
                        unset($novosGET[$f['param']]);
                        $urlSemFiltro = '?' . http_build_query($novosGET);
                    ?>
                    <span class="dp-filtro-tag">
                        <?= dashFmt($f['label']) ?>: <?= dashFmt($f['valor']) ?>
                        <a href="<?= $urlSemFiltro ?>" class="remover-filtro" title="Remover filtro">
                            <i class="bi bi-x-circle"></i>
                        </a>
                    </span>
                <?php endforeach; ?>
                <a href="dashboard_projetos" class="btn btn-sm btn-outline-secondary ms-2">
                    <i class="bi bi-eraser"></i> Limpar todos
                </a>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- ============================================================
         VISÃO GERAL DO PORTFÓLIO
         ============================================================ -->
    <div class="dp-secao-titulo"><i class="bi bi-pie-chart-fill"></i> Visão Geral do Portfólio</div>

    <div class="row g-3 mb-3">
        <div class="col-lg-4">
            <div class="dp-card h-100">
                <h5>Situação dos Contratos</h5>
                <div class="dp-donut-wrap">
                    <canvas id="donutSituacao"></canvas>
                    <div class="dp-donut-centro">
                        <div class="num" id="totalSituacao">0</div>
                        <div class="lbl">Contratos</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="dp-card h-100">
                <h5>Situação dos Contratos em Andamento</h5>
                <div class="dp-donut-wrap">
                    <canvas id="donutCronograma"></canvas>
                    <div class="dp-donut-centro">
                        <div class="num" id="totalCronograma">0</div>
                        <div class="lbl">Contratos</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="dp-card h-100">
                <h5>Contratos com PAAR Aberto</h5>
                <div class="dp-kpi-num"><?= (int)$kpisPortfolio['paarTotal'] ?></div>
                <div class="dp-tabela-scroll mt-3">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Contrato</th><th>UF</th><th>BR</th></tr></thead>
                        <tbody>
                            <?php if (empty($kpisPortfolio['paarLista'])): ?>
                                <tr><td colspan="3" class="text-muted text-center py-2">Nenhum PAAR aberto</td></tr>
                            <?php else: ?>
                                <?php foreach ($kpisPortfolio['paarLista'] as $p): ?>
                                    <tr>
                                        <td><?= dashFmt($p['instrumento']) ?></td>
                                        <td><?= dashFmt($p['uf']) ?></td>
                                        <td><?= dashFmt($p['br']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <?php if (!$temContratoSelecionado): ?>

        <!-- ============================================================
             Sem contrato selecionado: só avisa
             ============================================================ -->
        <div class="dp-aviso-sem-filtro">
            <i class="bi bi-funnel"></i>
            Selecione algum <strong>Contrato</strong> com <strong>Subtrecho</strong> acima
            para visualizar os detalhes de um contrato.
        </div>

    <?php elseif (!$c): ?>

        <div class="alert alert-info">Nenhum contrato encontrado com os filtros atuais.</div>

    <?php else: ?>

        <div class="dp-secao-titulo"><i class="bi bi-info-circle"></i> Contrato Selecionado</div>

        <div class="row g-3">
            <div class="col-lg-3">
                <div class="dp-card">
                    <h5><i class="bi bi-geo-alt"></i> Localização</h5>
                    <div id="dpMapa"></div>
                    <div class="text-muted small mt-2">
                        <i class="bi bi-clock"></i> Atualizado em <?= date('d/m/Y H:i') ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-9">

                <!-- IDENTIFICAÇÃO -->
                <div class="dp-card">
                    <h5><i class="bi bi-file-earmark-text"></i> Identificação</h5>
                    <div class="dp-ident-grid">
                        <div class="dp-ident-item"><div class="lbl">Nome Usual</div>            <div class="val"><?= dashFmt($c['nome_usual'] ?? '—') ?></div></div>
                        <div class="dp-ident-item"><div class="lbl">ID PAC</div>                <div class="val"><?= dashFmt($c['n_id_pac'] ?? '—') ?></div></div>
                        <div class="dp-ident-item"><div class="lbl">Rodovia</div>               <div class="val"><?= dashFmt($c['br'] ?? '—') ?> / <?= dashFmt($c['uf'] ?? '') ?></div></div>
                        <div class="dp-ident-item"><div class="lbl">Extensão (km)</div>         <div class="val"><?= dashFmt($c['extensao'] ?? '—') ?></div></div>
                        <div class="dp-ident-item"><div class="lbl">Km Inicial</div>            <div class="val"><?= dashFmt($c['km_inicial'] ?? '—') ?></div></div>
                        <div class="dp-ident-item"><div class="lbl">Km Final</div>              <div class="val"><?= dashFmt($c['km_final'] ?? '—') ?></div></div>
                        <div class="dp-ident-item"><div class="lbl">Empresa</div>               <div class="val"><?= dashFmt($c['empresa'] ?? '—') ?></div></div>
                        <div class="dp-ident-item"><div class="lbl">Supervisora</div>           <div class="val"><?= dashFmt($c['supervisora'] ?? '—') ?></div></div>
                        <div class="dp-ident-item"><div class="lbl">Processo Base</div>         <div class="val"><?= dashFmt($c['processo_base'] ?? '—') ?></div></div>
                        <div class="dp-ident-item"><div class="lbl">Processo de Projetos</div>  <div class="val"><?= dashFmt($c['processo_projeto'] ?? '—') ?></div></div>
                        <div class="dp-ident-item"><div class="lbl">Análise (setor)</div>       <div class="val"><?= dashFmt($c['analise'] ?? '—') ?></div></div>
                        <div class="dp-ident-item"><div class="lbl">Ordem Início de Projetos</div><div class="val"><?= dashData($c['data_ordem_inicio_projeto'] ?? null) ?></div></div>
                        <div class="dp-ident-item"><div class="lbl">Fase</div>                  <div class="val"><?= dashFmt($c['fase'] ?? '—') ?></div></div>
                    </div>

                    <div class="dp-faixa-fin mt-3">
                        <div class="item">
                            <span class="dp-bolinha dp-bol-azul-claro"></span>
                            <span>
                                <small class="text-muted">Valor (PI + A + R):</small>
                                <strong><?= dashMoedaCompleto($c['valor_pi_a_r'] ?? null, 100) ?></strong>
                            </span>
                        </div>
                        <div class="item">
                            <span class="dp-bolinha dp-bol-azul-escuro"></span>
                            <span>
                                <small class="text-muted">Valor de Projetos:</small>
                                <strong><?= dashMoedaCompleto($c['valor_projetos'] ?? null, 100) ?></strong>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- RESUMOS -->
                <div class="dp-card mb-3">
                    <h5><i class="bi bi-clipboard-data"></i> Resumo</h5>

                    <div class="dp-cron-grid">
                        <div class="dp-cron-item">
                            <div class="lbl">Resumo Estudos</div>
                            <div class="val"><?= dashTexto($c['estudos_resumo'] ?? '—') ?></div>
                        </div>
                        <div class="dp-cron-item">
                            <div class="lbl">Resumo Projetos Básicos</div>
                            <div class="val"><?= dashTexto($c['projeto_basico_resumo'] ?? '—') ?></div>
                        </div>
                        <div class="dp-cron-item">
                            <div class="lbl">Resumo Projetos Executivos</div>
                            <div class="val"><?= dashTexto($c['projeto_executivo_resumo'] ?? '—') ?></div>
                        </div>
                        <div class="dp-cron-item">
                            <div class="lbl">Resumo Geométrico</div>
                            <div class="val"><?= dashTexto($c['geometrico_resumo'] ?? '—') ?></div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <div class="lbl small text-muted text-uppercase mb-1">Resumo Informativo</div>
                        <p class="mb-0" style="font-size:.9rem;"><?= nl2br(dashFmt($c['resumo_informativo'] ?? '—')) ?></p>
                    </div>
                </div>

                <!-- CRONOGRAMAS -->
                <div class="dp-card mb-3">
                    <h5><i class="bi bi-calendar-week"></i> Cronogramas e Notificações</h5>
                    <div class="dp-cron-grid">
                        <div class="dp-cron-item">
                            <div class="lbl">SEI do Cronograma</div>
                            <div class="val"><?= dashFmt($c['cronograma_sei'] ?? '—') ?></div>
                        </div>
                        <div class="dp-cron-item">
                            <div class="lbl">Situação do Cronograma</div>
                            <div class="val"><?= dashTexto($c['situacao_cronograma'] ?? '—') ?></div>
                        </div>
                        <div class="dp-cron-item">
                            <div class="lbl">Status Atual do Cronograma</div>
                            <div class="val"><?= dashTexto($c['status_cronograma_atual'] ?? '—') ?></div>
                        </div>
                        <div class="dp-cron-item">
                            <div class="lbl">Término dos Projetos (Cronograma)</div>
                            <div class="val"><?= dashData($c['data_termino_projeto_cronog'] ?? null) ?></div>
                        </div>
                        <div class="dp-cron-item">
                            <div class="lbl">Processo de Notificação</div>
                            <div class="val"><?= dashFmt($c['processo_notificacao_sr_uf'] ?? '—') ?></div>
                        </div>
                        <div class="dp-cron-item">
                            <div class="lbl">Ofício / SEI de Notificação</div>
                            <div class="val"><?= dashFmt($c['n_sei_oficio_cobranca_cronograma'] ?? '—') ?></div>
                        </div>
                        <div class="dp-cron-item">
                            <div class="lbl">Data da Última Notificação</div>
                            <div class="val"><?= dashData($c['data_ultima_notificacao'] ?? null) ?></div>
                        </div>
                        <div class="dp-cron-item">
                            <div class="lbl">Vigência do Cronograma</div>
                            <div class="val"><?= dashFmt($c['cronograma_vigencia'] ?? '—') ?></div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <div class="lbl small text-muted text-uppercase mb-1">Justificativa do Cronograma</div>
                        <p class="mb-0" style="font-size:.9rem;"><?= nl2br(dashFmt($c['justificativa_cronograma'] ?? '—')) ?></p>
                    </div>
                </div>

                <!-- Síntese + Observações -->
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="dp-card h-100 mb-0">
                            <h5><i class="bi bi-check-circle-fill text-success"></i> Síntese</h5>
                            <p class="mb-0" style="font-size:.9rem;"><?= nl2br(dashFmt($c['status_contrato_sintese'] ?? '—')) ?></p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="dp-card h-100 mb-0">
                            <h5><i class="bi bi-journal-text"></i> Observações</h5>
                            <p class="mb-0" style="font-size:.9rem;"><?= nl2br(dashFmt($c['observacoes'] ?? '—')) ?></p>
                        </div>
                    </div>
                </div>

                <!-- PAAR + Acórdão TCU -->
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="dp-card h-100 mb-0">
                            <h5><i class="bi bi-clipboard-check"></i> PAAR</h5>
                            <p class="mb-1"><strong>Situação:</strong> <?= dashFmt($c['situacao_paar'] ?? '—') ?></p>
                            <p class="mb-0 small text-muted"><?= nl2br(dashFmt($c['paar'] ?? '')) ?></p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="dp-card h-100 mb-0">
                            <h5>
                                <i class="bi bi-award"></i> Listado no Acórdão TCU
                                <i class="bi bi-info-circle text-muted" title="Indica se o contrato consta na listagem do TCU." style="font-size:.85rem;"></i>
                            </h5>
                            <p class="mb-1"><strong><?= dashFmt($c['contratos_listados_tcu_portaria'] ?? '—') ?></strong></p>
                            <p class="dp-acordao-txt mb-0">
                                Contratos contemplados no Acórdão do TCU (SEI nº 22954489), admite-se o início
                                da obra previamente à aprovação integral do Projeto Básico.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================
             MATRIZ DE DISCIPLINAS
             ============================================================ -->
        <div class="dp-secao-titulo"><i class="bi bi-grid-3x3-gap-fill"></i> Matriz de Disciplinas</div>

        <div class="dp-legenda-sticky">
            <strong style="color:var(--dp-azul);">Legenda:</strong>
            <span class="item"><i class="bi bi-check-circle-fill text-success"></i> Aprovado</span>
            <span class="item"><i class="bi bi-check-circle text-success"></i> Aprovado Parcial</span>
            <span class="item"><i class="bi bi-exclamation-triangle-fill text-warning"></i> Em análise</span>
            <span class="item"><i class="bi bi-exclamation-triangle text-warning"></i> Em anál. Parcial</span>
            <span class="item"><i class="bi bi-x-circle-fill text-danger"></i> Reprovado</span>
            <span class="item"><i class="bi bi-x-circle text-danger"></i> Reprovado Parcial</span>
            <span class="item"><i class="bi bi-file-earmark-x text-secondary"></i> Não Entregue</span>
            <span class="item"><i class="bi bi-search text-secondary"></i> Não Encontrado</span>
            <span class="item"><i class="bi bi-bullseye text-info"></i> Fora de Escopo</span>
            <span class="item"><i class="bi bi-flag-fill text-primary"></i> Com Ressalvas</span>
        </div>

        <div class="row g-3">
            <div class="col-lg-3 col-md-6">
                <div class="dp-matriz-col">
                    <h6>Estudos</h6>
                    <?php foreach ($matrizEstudos as $col => $label): ?>
                        <div class="dp-matriz-item">
                            <span class="lbl"><?= dashFmt($label) ?></span>
                            <span class="ico"><?= dashIcone($c[$col] ?? null) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="dp-matriz-col">
                    <h6>Projetos Básicos</h6>
                    <?php foreach ($matrizBasico as $col => $label): ?>
                        <div class="dp-matriz-item <?= $col === 'geometrico_basico' ? 'destaque' : '' ?>">
                            <span class="lbl"><?= dashFmt($label) ?></span>
                            <span class="ico"><?= dashIcone($c[$col] ?? null) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="dp-matriz-col">
                    <h6>Projetos Executivos</h6>
                    <?php foreach ($matrizExec as $col => $label): ?>
                        <div class="dp-matriz-item <?= $col === 'geometrico_executivo' ? 'destaque' : '' ?>">
                            <span class="lbl"><?= dashFmt($label) ?></span>
                            <span class="ico"><?= dashIcone($c[$col] ?? null) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="dp-matriz-col">
                    <h6>OAE (Obras de Arte Especiais)</h6>
                    <?php foreach ($matrizOae as $col => $label): ?>
                        <div class="dp-matriz-item">
                            <span class="lbl"><?= dashFmt($label) ?></span>
                            <span class="ico"><?= dashIcone($c[$col] ?? null) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
if (window.Chart && window.ChartDataLabels) {
    Chart.register(ChartDataLabels);
}

$(document).ready(function () {
    // ============================================================
    // FILTROS — Select2 (padrão Notificações)
    // ============================================================
    $('.filtro-select2').select2({ placeholder: 'Selecione...', allowClear: false, width: '100%' });

    $('.filtro-select2').on('change', function () {
        $('#formFiltros').submit();
    });

    $('.filtro-select2').on('select2:open', function () {
        setTimeout(function () {
            var f = document.querySelector('.select2-search__field');
            if (f) f.focus();
        }, 100);
    });

    $('.filtro-select2').on('focus', function () {
        $(this).select2('open');
    });

    // ============================================================
    // DONUTS
    // ============================================================
    const dadosSit = <?= json_encode($kpisPortfolio['situacao'] ?: []) ?>;
    const dadosCr  = <?= json_encode($kpisPortfolio['cronograma'] ?: []) ?>;

    const coresSit  = ['#0b1a4a', '#1e40af', '#60a5fa', '#93c5fd', '#d4af37', '#7c3aed'];
    const coresCron = {
        'No prazo':                 '#1e40af',
        'Vencido':                  '#d4af37',
        'Paralisado':               '#7c3aed',
        'Vence dentro de 30 dias':  '#60a5fa'
    };

    const dataLabelsConfig = {
        color: '#fff',
        font: { weight: '700', size: 12 },
        textStrokeColor: 'rgba(0,0,0,0.35)',
        textStrokeWidth: 3,
        textShadowColor: 'rgba(0,0,0,0.5)',
        textShadowBlur: 4,

        // ✅ Centralização dentro da fatia
        anchor: 'center',
        align: 'center',
        offset: 0,
        clamp: true,
        clip: false,

        formatter: function (value) {
            return value > 0 ? value : '';
        },
        display: function (ctx) {
            const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
            const val   = ctx.dataset.data[ctx.dataIndex];
            return total > 0 && (val / total) >= 0.04;
        }
    };

    const baseOptions = {
        responsive: true,
        maintainAspectRatio: false,
        layout: { padding: 8 },
        plugins: {
            legend: {
                position: 'bottom',
                labels: { font: { size: 11 }, boxWidth: 12, padding: 8 }
            },
            tooltip: {
                callbacks: {
                    label: function (ctx) {
                        const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                        const val   = ctx.parsed;
                        const pct   = total ? ((val / total) * 100).toFixed(1) : 0;
                        return ` ${ctx.label}: ${val} (${pct}%)`;
                    }
                }
            },
            datalabels: dataLabelsConfig
        },
        cutout: '62%'
    };

    const rotSit = dadosSit.map(d => ((d.rotulo || 'Sem informação') + '').toUpperCase());
    const valSit = dadosSit.map(d => Number(d.qtd));
    document.getElementById('totalSituacao').textContent = valSit.reduce((a, b) => a + b, 0);
    if (rotSit.length) {
        new Chart(document.getElementById('donutSituacao'), {
            type: 'doughnut',
            data: {
                labels: rotSit,
                datasets: [{
                    data: valSit,
                    backgroundColor: rotSit.map((_, i) => coresSit[i % coresSit.length]),
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: baseOptions
        });
    }

    const rotCr = dadosCr.map(d => (d.rotulo || 'Sem informação'));
    const valCr = dadosCr.map(d => Number(d.qtd));
    document.getElementById('totalCronograma').textContent = valCr.reduce((a, b) => a + b, 0);
    if (rotCr.length) {
        new Chart(document.getElementById('donutCronograma'), {
            type: 'doughnut',
            data: {
                labels: rotCr,
                datasets: [{
                    data: valCr,
                    backgroundColor: rotCr.map(r => coresCron[r] || '#94a3b8'),
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: baseOptions
        });
    }

    // ============================================================
    // MAPA
    // ============================================================
    <?php if ($c): ?>
    const UF_COORDS = {
        'AC': [-9.97,  -67.81], 'AL': [-9.66,  -35.73], 'AP': [ 0.03,  -51.07],
        'AM': [-3.10,  -60.02], 'BA': [-12.97, -38.51], 'CE': [-3.72,  -38.54],
        'DF': [-15.78, -47.93], 'ES': [-20.32, -40.34], 'GO': [-16.68, -49.25],
        'MA': [-2.53,  -44.30], 'MT': [-15.60, -56.10], 'MS': [-20.44, -54.65],
        'MG': [-19.92, -43.94], 'PA': [-1.46,  -48.49], 'PB': [-7.12,  -34.88],
        'PR': [-25.43, -49.27], 'PE': [-8.05,  -34.88], 'PI': [-5.09,  -42.80],
        'RJ': [-22.91, -43.17], 'RN': [-5.79,  -35.21], 'RS': [-30.03, -51.23],
        'RO': [-8.76,  -63.90], 'RR': [ 2.82,  -60.67], 'SC': [-27.60, -48.55],
        'SP': [-23.55, -46.63], 'SE': [-10.91, -37.07], 'TO': [-10.18, -48.33]
    };

    const ufAtual = '<?= dashFmt(strtoupper($c['uf'] ?? '')) ?>';
    const coords  = UF_COORDS[ufAtual] || [-15.7, -47.9];

    const mapa = L.map('dpMapa').setView(coords, 7);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap'
    }).addTo(mapa);

    L.marker(coords).addTo(mapa)
        .bindPopup(
            '<strong><?= dashFmt($c['br'] ?? '') ?> / <?= dashFmt($c['uf'] ?? '') ?></strong><br>' +
            '<?= dashFmt($c['nome_usual'] ?? '') ?>'
        )
        .openPopup();
    <?php endif; ?>
});
</script>
</body>
</html>