<?php
// ============================================================
// debug_dataset1.php — Inspecionar JSON do DataSet (com busca)
// ============================================================
require_once 'config.php';
require_once __DIR__ . '/../Config/version.php';

// ============================================================
// 📚 MAPA DOS DATASETS CONHECIDOS
// ============================================================
$DATASETS = [
    1  => ['sigla' => 'COCCONV',  'nome' => 'Carteira de Contratos', 'cor' => 'primary'],
    16 => ['sigla' => 'GEO', 'nome' => 'Base Controle GEO', 'cor' => 'sucess'],
    26 => ['sigla' => 'PROJETOS', 'nome' => 'Controle Projetos RDCI', 'cor' => 'info'],
    30 => ['sigla' => 'CGCONT', 'nome' => 'Endereço Empresas',    'cor' => 'success'],
    36 => ['sigla' => 'ATLAS', 'nome' => 'Informações Gerais de Obras', 'cor' => 'success'],
    38 => ['sigla' => 'ATLAS', 'nome' => 'Lista Todos Instrumentos', 'cor' => 'success'],
    39 => ['sigla' => 'ATLAS', 'nome' => 'Acompanhamento de Processos', 'cor' => 'sucess'],
    40 => ['sigla' => 'ATLAS', 'nome' => 'Slides ATLAS', 'cor' => 'sucess'],
    41 => ['sigla' => 'ATLAS', 'nome' => '2026 Físico', 'cor' => 'sucess'],
    42 => ['sigla' => 'ATLAS', 'nome' => '2025 Físico', 'cor' => 'sucess'],
    43 => ['sigla' => 'ATLAS', 'nome' => 'Físico Total', 'cor' => 'sucess'],
];

// ============================================================
// ⚙️ CONFIGURAÇÃO — dataset via GET
// ============================================================
$DATASET = (int)($_GET['ds'] ?? 1);
$BUSCA   = trim((string)($_GET['q'] ?? ''));

// Fallback para datasets não mapeados
$meta = $DATASETS[$DATASET] ?? [
    'sigla' => 'DESCONHECIDO',
    'nome'  => 'DataSet não catalogado',
    'cor'   => 'secondary',
];

$url   = "https://supra.dnit.gov.br/index_cgcont_common.php/cgcont/cib/exportBi/{$DATASET}";
$token = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyIjoic3VwcmFfY2liX2FwaUBkbml0Lmdvdi5iciIsInBhc3MiOiJTdXByYUBDaWIxMjM0NUAifQ.GyejoRFofL3mMnN7jD64RBrr2JSB9hVpR_BWrfAtd3U";

// ============================================================
// CHAMADA — mesmo padrão do import 36
// ============================================================
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['token: ' . $token, 'Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 120);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$resposta = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$tamanho  = $resposta !== false ? strlen($resposta) : 0;
curl_close($ch);

// ============================================================
// ANÁLISE
// ============================================================
$json        = null;
$erroJson    = null;
$ehArrayJson = false;

if ($resposta !== false && $resposta !== '') {
    $json = json_decode($resposta, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $erroJson = json_last_error_msg();
    } else {
        $ehArrayJson = is_array($json);
    }
}

$info = [
    'url'            => $url,
    'http_code'      => $httpCode,
    'tamanho'        => $tamanho,
    'erro_json'      => $erroJson,
    'eh_array'       => $ehArrayJson,
    'total'          => 0,
    'total_filtrado' => 0,
    'chaves'         => [],
    'chaves_extra'   => [],
    'exemplo'        => null,
];

if ($ehArrayJson && !empty($json)) {
    $lista = $json;
    $ehLista = array_keys($json) === range(0, count($json) - 1);
    if (!$ehLista) {
        $melhor = null;
        foreach ($json as $v) {
            if (is_array($v) && !empty($v)) {
                $interna = array_keys($v) === range(0, count($v) - 1);
                if ($interna && ($melhor === null || count($v) > count($melhor))) {
                    $melhor = $v;
                }
            }
        }
        if ($melhor !== null) $lista = $melhor;
    }

    $info['total'] = count($lista);

    // ✅ Aplica filtro de busca se houver termo
    $listaFiltrada = $lista;
    if ($BUSCA !== '') {
        $b = mb_strtolower($BUSCA);
        $listaFiltrada = array_values(array_filter($lista, function ($item) use ($b) {
            if (!is_array($item)) return false;
            foreach ($item as $v) {
                if (is_scalar($v) && mb_strpos(mb_strtolower((string)$v), $b) !== false) return true;
            }
            return false;
        }));
    }
    $info['total_filtrado'] = count($listaFiltrada);

    // ✅ Mostra SOMENTE o primeiro registro (o primeiro do dataset, ou o primeiro que casar com a busca)
    $info['exemplo'] = $listaFiltrada[0] ?? null;

    if ($info['exemplo']) {
        $info['chaves'] = array_keys($info['exemplo']);
    }

    $todas = [];
    foreach ($lista as $r) {
        if (!is_array($r)) continue;
        foreach (array_keys($r) as $k) $todas[$k] = true;
    }
    $info['chaves_extra'] = array_values(array_diff(array_keys($todas), $info['chaves']));
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>Debug DataSet <?= (int)$DATASET ?> — <?= htmlspecialchars($meta['sigla']) ?></title>
<link href="<?= sispro_asset('https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css') ?>" rel="stylesheet">
<link rel="stylesheet" href="<?= sispro_asset('https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css') ?>">
<style>
    body { background:#f8f9fc; padding:30px 20px; font-family:-apple-system,"Segoe UI",Roboto,sans-serif; }
    .container { max-width:1200px; }
    .card { border:none; border-radius:14px; box-shadow:0 6px 18px rgba(0,0,0,.06); padding:20px 24px; margin-bottom:18px; }
    .card h5 { color:#0b1a4a; font-weight:700; font-size:1rem; margin-bottom:14px; }
    pre { background:#f6f8fb; border:1px solid #e3e8f0; border-radius:8px; padding:14px; font-size:12px; max-height:600px; overflow:auto; }
    .kpi-num { font-size:2rem; font-weight:800; color:#0b1a4a; line-height:1; }
    .kpi-lbl { font-size:.75rem; color:#7a859b; text-transform:uppercase; margin-top:4px; }
    .chave { display:inline-block; background:#e9f2ff; color:#0d47a1; border:1px solid #c6dbff; border-radius:12px; padding:3px 10px; font-size:.78rem; margin:2px 4px 2px 0; font-family:monospace; }
    .chave-extra { background:#fff3cd; color:#664d03; border-color:#ffecb5; }

    /* Cabeçalho com info do dataset + dropdown */
    .header-ds {
        display: flex; justify-content: space-between; align-items: center;
        gap: 16px; flex-wrap: wrap;
        background: #fff; border-radius: 14px;
        padding: 16px 22px;
        box-shadow: 0 6px 18px rgba(0,0,0,.06);
        margin-bottom: 18px;
    }
    .ds-titulo h4 { margin: 0; font-size: 1.15rem; font-weight: 700; color: #0b1a4a; }
    .ds-titulo small { color: #7a859b; font-size: .82rem; }
    .ds-badge {
        font-family: monospace; font-size: .78rem; font-weight: 600;
        padding: 2px 8px; border-radius: 8px;
        background: #eef2f8; color: #3a4a6b; margin-left: 6px;
    }

    /* Botões do cabeçalho */
    .btn-ds {
        background: #fff; border: 1px solid #d7dfea; color: #0b1a4a;
        border-radius: 10px; padding: 8px 14px; font-weight: 600;
        display: inline-flex; align-items: center; gap: 8px;
        text-decoration: none;
    }
    .btn-ds:hover { background: #f4f7fb; border-color: #b9c7db; color: #0b1a4a; }
    .dropdown-menu-ds { min-width: 340px; padding: 8px; border-radius: 12px; border: 1px solid #e3e8f0; box-shadow: 0 12px 30px rgba(0,0,0,.08); }
    .dropdown-item-ds {
        display: flex; align-items: center; gap: 12px;
        padding: 10px 12px; border-radius: 10px; text-decoration: none;
        color: #1f2d3d; transition: background .15s;
    }
    .dropdown-item-ds:hover { background: #f4f7fb; color: #0b1a4a; }
    .dropdown-item-ds.active { background: #e9f2ff; color: #0b1a4a; }
    .dropdown-item-ds .ds-info { flex: 1; min-width: 0; }
    .dropdown-item-ds .ds-info strong { display: block; font-size: .9rem; }
    .dropdown-item-ds .ds-info small { color: #7a859b; font-size: .78rem; }

    /* ✅ Busca destacada */
    .busca-card {
        background:#fff; border-radius:14px; padding:14px 20px;
        box-shadow:0 6px 18px rgba(0,0,0,.06); margin-bottom:18px;
        display:flex; gap:10px; align-items:center; flex-wrap:wrap;
    }
    .busca-card .form-control { border-radius:10px; }
    .busca-card .btn { border-radius:10px; font-weight:600; }

    /* Realce dos campos que casaram com a busca */
    .match { background:#fff3cd; padding:2px 4px; border-radius:4px; }
</style>
</head>
<body>
<div class="container">

    <!-- ============================================================
         CABEÇALHO — Info do dataset + seletor suspenso + voltar
         ============================================================ -->
    <div class="header-ds">
        <div class="ds-titulo">
            <div>
                <h4>
                    DataSet <?= (int)$DATASET ?>
                    <span class="ds-badge"><?= htmlspecialchars($meta['sigla']) ?></span>
                </h4>
                <small><?= htmlspecialchars($meta['nome']) ?></small>
            </div>
        </div>

        <div class="d-flex gap-2">
            <!-- Seletor suspenso -->
            <div class="dropdown">
                <button class="btn-ds dropdown-toggle" type="button" id="ddDatasets" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-collection"></i> Trocar DataSet
                </button>
                <ul class="dropdown-menu dropdown-menu-end dropdown-menu-ds" aria-labelledby="ddDatasets">
                    <li><h6 class="dropdown-header">Datasets disponíveis</h6></li>
                    <?php foreach ($DATASETS as $id => $d): ?>
                        <li>
                            <a class="dropdown-item-ds <?= $id === $DATASET ? 'active' : '' ?>" href="?ds=<?= (int)$id ?>">
                                <div class="ds-info">
                                    <strong>DS <?= (int)$id ?> — <?= htmlspecialchars($d['sigla']) ?></strong>
                                    <small><?= htmlspecialchars($d['nome']) ?></small>
                                </div>
                                <?php if ($id === $DATASET): ?>
                                    <i class="bi bi-check-circle-fill text-primary"></i>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="get" class="px-3 py-2 d-flex gap-2">
                            <input type="number" name="ds" class="form-control form-control-sm"
                                   placeholder="Outro ID..." min="1" style="max-width:120px;">
                            <button class="btn btn-sm btn-outline-primary" type="submit">
                                <i class="bi bi-search"></i> Abrir
                            </button>
                        </form>
                    </li>
                </ul>
            </div>

            <!-- Botão Voltar -->
            <a href="admin" class="btn-ds" title="Voltar para Administração">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </div>

    <!-- ============================================================
         BUSCADOR
         ============================================================ -->
    <form method="get" class="busca-card">
        <input type="hidden" name="ds" value="<?= (int)$DATASET ?>">
        <i class="bi bi-search" style="font-size:1.2rem;color:#0d6efd;"></i>
        <input type="text" name="q" class="form-control"
               style="flex:1; min-width:240px;"
               placeholder="Buscar em qualquer campo — ex: 14 00070/2026, BR-101, Contorno..."
               value="<?= htmlspecialchars($BUSCA) ?>">
        <button type="submit" class="btn btn-primary px-3">
            <i class="bi bi-search"></i> Buscar
        </button>
        <?php if ($BUSCA !== ''): ?>
            <a href="?ds=<?= (int)$DATASET ?>" class="btn btn-outline-secondary px-3">
                <i class="bi bi-x-lg"></i> Limpar
            </a>
        <?php endif; ?>
    </form>

    <!-- Chamada HTTP -->
    <div class="card">
        <h5><i class="bi bi-cloud-arrow-down"></i> Chamada HTTP</h5>
        <table class="table table-sm mb-0">
            <tr><td width="180">URL</td><td><code><?= htmlspecialchars($info['url']) ?></code></td></tr>
            <tr><td>HTTP Code</td><td>
                <span class="badge bg-<?= $info['http_code'] == 200 ? 'success' : 'danger' ?>">
                    <?= (int)$info['http_code'] ?>
                </span>
            </td></tr>
            <tr><td>Tamanho</td><td><?= number_format($info['tamanho'], 0, ',', '.') ?> bytes</td></tr>
            <?php if ($info['erro_json']): ?>
                <tr><td class="text-danger">Erro JSON</td><td class="text-danger"><?= htmlspecialchars($info['erro_json']) ?></td></tr>
            <?php endif; ?>
        </table>
    </div>

    <?php if ($info['eh_array']): ?>

        <!-- KPIs -->
        <div class="card">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="kpi-num"><?= (int)$info['total'] ?></div>
                    <div class="kpi-lbl">Registros no DataSet <?= (int)$DATASET ?></div>
                </div>
                <div class="col-md-3">
                    <div class="kpi-num" style="font-size:1.5rem;"><?= count($info['chaves']) ?></div>
                    <div class="kpi-lbl">Colunas por registro</div>
                </div>
                <div class="col-md-3">
                    <div class="kpi-num" style="font-size:1.5rem;">
                        <span class="badge bg-success">JSON OK</span>
                    </div>
                    <div class="kpi-lbl">Status</div>
                </div>
                <?php if ($BUSCA !== ''): ?>
                <div class="col-md-3">
                    <div class="kpi-num" style="font-size:1.5rem;">
                        <span class="badge bg-<?= $info['total_filtrado'] > 0 ? 'primary' : 'danger' ?>">
                            <?= (int)$info['total_filtrado'] ?> encontrado(s)
                        </span>
                    </div>
                    <div class="kpi-lbl">Busca: "<?= htmlspecialchars($BUSCA) ?>"</div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($BUSCA !== '' && $info['total_filtrado'] === 0): ?>
            <div class="card">
                <div class="alert alert-warning mb-0">
                    <i class="bi bi-exclamation-triangle"></i>
                    Nenhum registro contém o termo <strong>"<?= htmlspecialchars($BUSCA) ?>"</strong>.
                    Tente buscar por parte do número, BR, UF ou nome usual.
                </div>
            </div>
        <?php endif; ?>

        <!-- Chaves -->
        <?php if (!empty($info['chaves'])): ?>
        <div class="card">
            <h5><i class="bi bi-key"></i> Colunas encontradas em cada registro</h5>
            <?php foreach ($info['chaves'] as $k): ?>
                <span class="chave"><?= htmlspecialchars($k) ?></span>
            <?php endforeach; ?>

            <?php if (!empty($info['chaves_extra'])): ?>
                <hr>
                <div class="text-muted small mb-1">Colunas que aparecem só em alguns registros:</div>
                <?php foreach ($info['chaves_extra'] as $k): ?>
                    <span class="chave chave-extra"><?= htmlspecialchars($k) ?></span>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Exemplo (1 registro) -->
        <?php if ($info['exemplo']): ?>
        <div class="card">
            <h5>
                <i class="bi bi-file-earmark-code"></i>
                <?= $BUSCA !== '' ? 'Registro encontrado' : 'Exemplo de 1 registro' ?>
                <?php if (!empty($info['exemplo']['Instrumento'])): ?>
                    — <span class="text-primary"><?= htmlspecialchars($info['exemplo']['Instrumento']) ?></span>
                <?php endif; ?>
            </h5>

            <!-- Tabela campo / valor -->
            <table class="table table-sm mb-3">
                <thead>
                    <tr>
                        <th style="width:280px;">Chave</th>
                        <th>Valor</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($info['exemplo'] as $k => $v):
                        $ehNull = ($v === null || $v === '');
                        $mostrar = $ehNull ? 'null / vazio' : (is_scalar($v) ? (string)$v : json_encode($v, JSON_UNESCAPED_UNICODE));
                        $casaram = ($BUSCA !== '' && is_scalar($v) && mb_stripos((string)$v, $BUSCA) !== false);
                    ?>
                        <tr>
                            <td><code><?= htmlspecialchars($k) ?></code></td>
                            <td class="<?= $ehNull ? 'text-muted fst-italic' : '' ?>">
                                <?php if ($casaram): ?>
                                    <span class="match"><?= htmlspecialchars($mostrar) ?></span>
                                <?php else: ?>
                                    <?= htmlspecialchars($mostrar) ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <hr>
            <h6 class="mb-2"><i class="bi bi-file-earmark-code"></i> JSON bruto</h6>
            <pre><?= htmlspecialchars(json_encode($info['exemplo'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
        </div>
        <?php endif; ?>

    <?php else: ?>

        <div class="card">
            <h5><i class="bi bi-exclamation-triangle text-warning"></i> Resposta não é JSON válido</h5>
            <pre><?= htmlspecialchars(substr((string)$resposta, 0, 3000)) ?></pre>
        </div>

    <?php endif; ?>

</div>

<script src="<?= sispro_asset('https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js') ?>"></script>
</body>
</html>