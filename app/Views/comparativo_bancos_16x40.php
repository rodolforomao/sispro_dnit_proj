<?php
// View comparativo_bancos_16x40.php — Slides Atlas (40) × Base GEO (16)
// Match por INSTRUMENTO em camadas:
//   1) alias manual (tabela supra_alias_instrumento)
//   2) match exato (Instrumento 40 vs Instrumento ATLAS do 16)
//   3) parte do composto (split por "|")
//   4) FID (GEO) declarado no 40
//   5) comparação compacta (sem espaços)
//   6) fallback para O_INSTRUM / S_INSTRUM do 16

if (!function_exists('obterIniciais')) {
    function obterIniciais($nome) {
        $partes = explode(' ', trim($nome)); $ini = '';
        foreach ($partes as $p) { if (!empty($p)) $ini .= strtoupper(substr($p, 0, 1)); }
        return substr($ini, 0, 2);
    }
}

if (!function_exists('obterUltimaSincronizacao')) {
    function obterUltimaSincronizacao() {
        $base = defined('APP_PATH') ? APP_PATH : dirname(__DIR__, 2);
        $arq  = $base . '/public/.sync_ultima_execucao';
        if (file_exists($arq)) return date('d/m/Y H:i', filemtime($arq));
        $log = $base . '/public/importacao_cron.log';
        if (file_exists($log)) return date('d/m/Y H:i', filemtime($log));
        return '—';
    }
}

// NOVO: helper para acessar um campo com fallback seguro
if (!function_exists('obterCampoComFallback')) {
    function obterCampoComFallback($row, $campo, $fallbacks = []) {
        $val = trim((string)($row[$campo] ?? ''));
        if ($val !== '') return $val;
        foreach ($fallbacks as $fb) {
            $val = trim((string)($row[$fb] ?? ''));
            if ($val !== '') return $val;
        }
        return '';
    }
}

$usuario_nome  = $usuario_nome  ?? $_SESSION['usuario_nome']  ?? 'Usuário';
$usuario_nivel = $usuario_nivel ?? $_SESSION['usuario_nivel'] ?? 'usuario';
$usuario_id    = $usuario_id    ?? $_SESSION['usuario_id']    ?? 0;
$setor_slug    = $_SESSION['setor_slug'] ?? 'atlas-monitoramento';
$setor_nome    = $setor_slug === 'assessoria-projetos' ? 'Assessoria e Projetos' : 'Atlas/Monitoramento';
$isAdmin       = in_array($usuario_nivel, ['desenvolvedor', 'admin']);

$pendentes = 0;
if ($isAdmin) {
    try { global $pdo; $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE status = 'pendente'"); $pendentes = (int)$stmt->fetchColumn(); }
    catch (Exception $e) { $pendentes = 0; }
}

$total_setores = 0;
if ($usuario_id) {
    try { global $pdo; $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuario_setor WHERE usuario_id = ?"); $stmt->execute([$usuario_id]); $total_setores = (int)$stmt->fetchColumn(); }
    catch (Exception $e) { $total_setores = 0; }
}

$setores_usuario = [];
if ($usuario_id) {
    try {
        global $pdo;
        $stmt = $pdo->prepare("SELECT s.nome FROM setores s INNER JOIN usuario_setor us ON s.id = us.setor_id WHERE us.usuario_id = ?");
        $stmt->execute([$usuario_id]);
        $setores_usuario = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) { $setores_usuario = []; }
}

$ultimaAtualizacao = obterUltimaSincronizacao();

// ---------- POST: criar alias manual 40 → 16 ----------
global $pdo;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'criar_alias' && $isAdmin) {
    $instr40 = trim((string)($_POST['instrumento_40'] ?? ''));
    $instr16 = trim((string)($_POST['instrumento_16'] ?? ''));
    if ($instr40 !== '' && $instr16 !== '') {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO supra_alias_instrumento (instrumento_40, instrumento_16)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE instrumento_16 = VALUES(instrumento_16), criado_em = CURRENT_TIMESTAMP
            ");
            $stmt->execute([$instr40, $instr16]);
        } catch (Exception $e) { /* silencioso */ }
    }
    header('Location: comparativo_bancos_16x40');
    exit;
}

// ---------- Filtros ----------
$filtroUF             = $_GET['uf']             ?? '';
$filtroBR             = $_GET['br']             ?? '';
$filtroBusca          = $_GET['busca']          ?? '';
$filtroMapaGerencial  = $_GET['mapa_gerencial'] ?? '';
$filtroMetodo         = $_GET['metodo']         ?? ''; // '' = todos, 'exato', 'fallback', 'ausente'

$filtrosAplicados = [];
if (!empty($filtroUF))            $filtrosAplicados[] = ['param' => 'uf',             'label' => 'UF',              'valor' => $filtroUF];
if (!empty($filtroBR))            $filtrosAplicados[] = ['param' => 'br',             'label' => 'BR',              'valor' => $filtroBR];
if (!empty($filtroMapaGerencial)) $filtrosAplicados[] = ['param' => 'mapa_gerencial', 'label' => 'Mapa Gerencial',  'valor' => $filtroMapaGerencial];
if (!empty($filtroMetodo))        $filtrosAplicados[] = ['param' => 'metodo',         'label' => 'Método',          'valor' => $filtroMetodo];
if (!empty($filtroBusca))         $filtrosAplicados[] = ['param' => 'busca',          'label' => 'Busca',           'valor' => $filtroBusca];

// ---------- Normalização ----------
$normalizar = function($v) {
    $v = trim((string)$v);
    if ($v === '') return '';
    $low = strtolower($v);
    if (in_array($low, ['n/a', 'null', 'undefined'], true)) return '';
    $v = preg_replace('/[\s\x{00A0}]+/u', ' ', $v);
    return function_exists('mb_strtoupper') ? mb_strtoupper($v, 'UTF-8') : strtoupper($v);
};

$normalizarCompacto = function($v) use ($normalizar) {
    $k = $normalizar($v);
    return $k === '' ? '' : preg_replace('/\s+/', '', $k);
};

$extrairPartes = function($v) {
    $v = (string)$v;
    if (strpos($v, '|') === false) return [$v];
    return array_map('trim', explode('|', $v));
};

// ---------- Base 16 (GEO) ----------
if (!isset($base16)) {
    $where = []; $params = [];
    if ($filtroUF !== '') { $where[] = "uf = ?"; $params[] = $filtroUF; }
    if ($filtroBR !== '') { $where[] = "br = ?"; $params[] = $filtroBR; }
    if ($filtroBusca !== '') {
        $where[] = "(o_instrum LIKE ? OR o_obj_resu LIKE ? OR o_empresa LIKE ? OR trecho LIKE ? OR id_pac LIKE ? OR fid LIKE ? OR instrumento_atlas LIKE ?)";
        $like = '%' . $filtroBusca . '%';
        array_push($params, $like, $like, $like, $like, $like, $like, $like);
    }
    $sql = "SELECT * FROM supra_dataset_16_base_controle_geo";
    if ($where) $sql .= " WHERE " . implode(' AND ', $where);
    $sql .= " ORDER BY uf, br, o_km_inici";
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    $base16 = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ---------- Base 40 (Atlas) ----------
if (!isset($base40Full)) {
    $where = []; $params = [];
    if ($filtroUF !== '') { $where[] = "estado = ?"; $params[] = $filtroUF; }
    if ($filtroBR !== '') { $where[] = "br = ?"; $params[] = $filtroBR; }
    if ($filtroBusca !== '') {
        $where[] = "(instrumento LIKE ? OR nome_empreendimento_slides LIKE ? OR nome_lote_slide LIKE ? OR empresa_construtora LIKE ? OR id_pac LIKE ? OR fid_geo LIKE ?)";
        $like = '%' . $filtroBusca . '%';
        array_push($params, $like, $like, $like, $like, $like, $like);
    }
    $sql = "SELECT * FROM supra_dataset_40_slides_atlas";
    if ($where) $sql .= " WHERE " . implode(' AND ', $where);
    $sql .= " ORDER BY estado, br";
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    $base40Full = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ============================================================
// NOVO: Seletores de campo ID — base 16 (default fid_principal)
//                                  e base 40 (default fid)
// ============================================================
$camposId16Opcoes = [];
if (!empty($base16)) {
    foreach (['fid_principal', 'fid', 'id_pac'] as $c) {
        if (array_key_exists($c, $base16[0])) {
            $label = $c === 'fid_principal' ? 'FID Principal (padrão)'
                   : ($c === 'fid' ? 'FID' : 'ID PAC');
            $camposId16Opcoes[$c] = $label;
        }
    }
}
if (empty($camposId16Opcoes)) $camposId16Opcoes = ['fid' => 'FID (padrão)'];

$camposId40Opcoes = [];
if (!empty($base40Full)) {
    foreach (['fid', 'fid_geo', 'id_pac'] as $c) {
        if (array_key_exists($c, $base40Full[0])) {
            $label = $c === 'fid' ? 'FID (padrão)'
                   : ($c === 'fid_geo' ? 'FID GEO' : 'ID PAC');
            $camposId40Opcoes[$c] = $label;
        }
    }
}
if (empty($camposId40Opcoes)) $camposId40Opcoes = ['fid_geo' => 'FID GEO (padrão)'];

// array_key_first() é PHP 7.3+; produção é 7.0.32
$defaultId16 = array_key_exists('fid_principal', $camposId16Opcoes) ? 'fid_principal' : (string) key($camposId16Opcoes);
$defaultId40 = array_key_exists('fid', $camposId40Opcoes)            ? 'fid'            : (string) key($camposId40Opcoes);

$filtroCampoId16 = $_GET['campo_id_16'] ?? $defaultId16;
$filtroCampoId40 = $_GET['campo_id_40'] ?? $defaultId40;
if (!array_key_exists($filtroCampoId16, $camposId16Opcoes)) $filtroCampoId16 = $defaultId16;
if (!array_key_exists($filtroCampoId40, $camposId40Opcoes)) $filtroCampoId40 = $defaultId40;

// Adiciona aos "filtros aplicados" só quando o usuário muda o padrão
if ($filtroCampoId16 !== $defaultId16) {
    $filtrosAplicados[] = ['param' => 'campo_id_16', 'label' => 'Campo ID (16)', 'valor' => $camposId16Opcoes[$filtroCampoId16] ?? $filtroCampoId16];
}
if ($filtroCampoId40 !== $defaultId40) {
    $filtrosAplicados[] = ['param' => 'campo_id_40', 'label' => 'Campo ID (40)', 'valor' => $camposId40Opcoes[$filtroCampoId40] ?? $filtroCampoId40];
}

// ---------- Aliases manuais 40 → 16 ----------
$aliases = [];
try {
    $stmt = $pdo->query("SELECT instrumento_40, instrumento_16 FROM supra_alias_instrumento");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $a) {
        $k40 = $normalizar($a['instrumento_40']);
        $k16 = $normalizar($a['instrumento_16']);
        if ($k40 !== '' && $k16 !== '') $aliases[$k40] = $k16;
    }
} catch (Exception $e) { $aliases = []; }

// ---------- Índices do 16 (normal + compacto + FID) ----------
$idx16         = [];
$idx16Compacto = [];
$idx16Fid      = [];
foreach ($base16 as $r) {
    // ALTERADO: usa o campo ID selecionado pelo usuário (default fid_principal)
    $fid = obterCampoComFallback($r, $filtroCampoId16, ['fid_principal', 'fid']);
    if ($fid !== '' && !isset($idx16Fid[$fid])) $idx16Fid[$fid] = $r;

    $chaves = [
        $r['instrumento_atlas'] ?? '',
        $r['o_instrum']         ?? '',
        $r['s_instrum']         ?? '',
    ];
    foreach ($chaves as $ch) {
        $k = $normalizar($ch);
        if ($k !== '' && !isset($idx16[$k])) $idx16[$k] = $r;
        $kc = $normalizarCompacto($ch);
        if ($kc !== '' && !isset($idx16Compacto[$kc])) $idx16Compacto[$kc] = $r;
    }
}

// ---------- Aplica filtro Mapa Gerencial ----------
$base40 = $base40Full;
if ($filtroMapaGerencial !== '') {
    $base40 = array_values(array_filter($base40Full, function($r) use ($filtroMapaGerencial) {
        return strcasecmp(trim($r['incluir_mapa_gerencial'] ?? ''), trim($filtroMapaGerencial)) === 0;
    }));
}

// NOVO: contagem de registros da base 40 com incluir_mapa_gerencial = SIM
$mapaGerencialSim40 = 0;
foreach ($base40 as $r) {
    if (strcasecmp(trim($r['incluir_mapa_gerencial'] ?? ''), 'SIM') === 0) $mapaGerencialSim40++;
}

// ---------- Função central de match ----------
$buscarGEO = function($instr40, $fidGeo = '') use (
    $idx16, $idx16Compacto, $idx16Fid, $aliases,
    $normalizar, $normalizarCompacto, $extrairPartes
) {
    $k0 = $normalizar($instr40);

    // 1) alias (valor inteiro)
    if ($k0 !== '' && isset($aliases[$k0])) {
        $kA = $aliases[$k0];
        if (isset($idx16[$kA]))         return ['geo' => $idx16[$kA],         'metodo' => 'alias'];
        if (isset($idx16Compacto[$kA])) return ['geo' => $idx16Compacto[$kA], 'metodo' => 'alias'];
    }

    // 2) exato
    if ($k0 !== '' && isset($idx16[$k0])) return ['geo' => $idx16[$k0], 'metodo' => 'exato'];

    // 3) partes do composto "|"
    foreach ($extrairPartes($instr40) as $p) {
        $kp = $normalizar($p);
        if ($kp !== '' && isset($idx16[$kp])) return ['geo' => $idx16[$kp], 'metodo' => 'parte'];

        if ($kp !== '' && isset($aliases[$kp])) {
            $kA = $aliases[$kp];
            if (isset($idx16[$kA]))         return ['geo' => $idx16[$kA],         'metodo' => 'alias'];
            if (isset($idx16Compacto[$kA])) return ['geo' => $idx16Compacto[$kA], 'metodo' => 'alias'];
        }
    }

    // 4) FID (GEO) declarado no slide
    if ($fidGeo !== '' && isset($idx16Fid[$fidGeo])) return ['geo' => $idx16Fid[$fidGeo], 'metodo' => 'fid'];

    // 5) compacto (sem espaços)
    $kc = $normalizarCompacto($instr40);
    if ($kc !== '' && isset($idx16Compacto[$kc])) return ['geo' => $idx16Compacto[$kc], 'metodo' => 'compacto'];
    foreach ($extrairPartes($instr40) as $p) {
        $kc = $normalizarCompacto($p);
        if ($kc !== '' && isset($idx16Compacto[$kc])) return ['geo' => $idx16Compacto[$kc], 'metodo' => 'compacto'];
    }

    return ['geo' => null, 'metodo' => null];
};

// ---------- Monta validação + candidatos ----------
$validacao         = [];
$candidatos        = [];
$contagemMetodos   = ['exato' => 0, 'parte' => 0, 'fid' => 0, 'compacto' => 0, 'alias' => 0, 'ausente' => 0];

foreach ($base40 as $r) {
    $instr40 = $r['instrumento'] ?? '';
    // ALTERADO: FID vem do campo selecionado pelo usuário (default 'fid')
    $fidGeo  = obterCampoComFallback($r, $filtroCampoId40, ['fid', 'fid_geo']);
    $res     = $buscarGEO($instr40, $fidGeo);
    $metodo  = $res['metodo'] ?? 'ausente';
    $contagemMetodos[$metodo] = ($contagemMetodos[$metodo] ?? 0) + 1;

    $validacao[] = [
        'atlas'   => $r,
        'geo'     => $res['geo'],
        'presente'=> $res['geo'] !== null,
        'metodo'  => $metodo,
    ];
    if ($res['geo'] === null) $candidatos[] = $r;
}

$total16          = count($base16);
$total40          = count($base40);
$presentes        = $total40 - count($candidatos);
$ausentesGEO      = count($candidatos);
$totalCandidatos  = $ausentesGEO;
$presentesExato   = $contagemMetodos['exato'];
$presentesFallbk  = $contagemMetodos['parte'] + $contagemMetodos['fid'] + $contagemMetodos['compacto'] + $contagemMetodos['alias'];

// ---------- Filtro por método (só afeta a tabela de validação) ----------
if ($filtroMetodo !== '') {
    $validacao = array_values(array_filter($validacao, function($v) use ($filtroMetodo) {
        if ($filtroMetodo === 'exato')    return $v['metodo'] === 'exato';
        if ($filtroMetodo === 'ausente')  return $v['metodo'] === 'ausente';
        if ($filtroMetodo === 'fallback') return in_array($v['metodo'], ['parte', 'fid', 'compacto', 'alias'], true);
        return true;
    }));
}

// ---------- Valores distintos para o filtro Mapa Gerencial ----------
$mapaGerencialValores = [];
$temSimNaLista = false;
try {
    $stmt = $pdo->query("
        SELECT UPPER(TRIM(incluir_mapa_gerencial)) AS valor
        FROM supra_dataset_40_slides_atlas
        WHERE incluir_mapa_gerencial IS NOT NULL AND TRIM(incluir_mapa_gerencial) <> ''
        GROUP BY UPPER(TRIM(incluir_mapa_gerencial))
        ORDER BY valor
    ");
    $mapaGerencialValores = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($mapaGerencialValores as $v) {
        if (strcasecmp($v, 'SIM') === 0) { $temSimNaLista = true; break; }
    }
} catch (Exception $e) { $mapaGerencialValores = []; }

// ---------- UF/BR para selects ----------
$ufsSet = []; $brsSet = [];
foreach (array_merge($base16, $base40Full) as $r) {
    $uf = $r['uf'] ?? $r['estado'] ?? '';
    $br = $r['br'] ?? '';
    if ($uf !== '') $ufsSet[$uf] = true;
    if ($br !== '') $brsSet[$br] = true;
}
$ufs = array_keys($ufsSet); sort($ufs);
$brs = array_keys($brsSet); sort($brs);

// ---------- Rótulos/cores por método ----------
$metodoLabel = [
    'exato'    => ['label' => 'Exato',      'cls' => 'bg-success'],
    'parte'    => ['label' => 'Parte do |', 'cls' => 'bg-primary'],
    'fid'      => ['label' => 'FID GEO',    'cls' => 'bg-info text-dark'],
    'compacto' => ['label' => 'Compacto',   'cls' => 'bg-warning text-dark'],
    'alias'    => ['label' => 'Alias',      'cls' => 'bg-purple text-white'],
    'ausente'  => ['label' => 'Sem match',  'cls' => 'bg-danger'],
];
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Comparativo Bancos 16 × 40 — Atlas/Monitoramento — SISPRO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        body { background: #f8f9fc; padding-top: 70px; }
        .container-fluid { max-width: 98%; }
        .card { border: none; border-radius: 16px; box-shadow: 0 8px 30px rgba(0,0,0,0.08); background: #fff; padding: 20px 25px; }
        .sistema-titulo { font-weight: 700; color: #004a8f; font-size: 1.4rem; letter-spacing: 1px; }
        .table th { font-weight: 600; color: #2c3e50; white-space: nowrap; font-size: 0.85rem; }
        .table td { vertical-align: middle; font-size: 0.85rem; }
        .table-responsive { overflow-x: auto; }
        .btn-sm { padding: 0.2rem 0.5rem; font-size: 0.75rem; }

        .ultima-atualizacao {
            font-size: 0.78rem; color: #6c757d;
            background: #f1f5f9; border: 1px solid #e2e8f0;
            padding: 4px 12px; border-radius: 20px;
            display: inline-flex; align-items: center; gap: 6px; white-space: nowrap;
        }
        .ultima-atualizacao strong { color: #0d6efd; }

        .card-kpi { border: none; border-radius: 12px; padding: 14px 18px; box-shadow: 0 4px 12px rgba(0,0,0,0.06); border-left: 5px solid #4a90e2; background: #fff; height: 100%; }
        .card-kpi.azul     { border-left-color: #0d6efd; }
        .card-kpi.roxo     { border-left-color: #6f42c1; }
        .card-kpi.verde    { border-left-color: #198754; }
        .card-kpi.amarelo  { border-left-color: #ffc107; }
        .card-kpi.vermelho { border-left-color: #dc3545; }
        /* NOVO: KPI Mapa Gerencial SIM */
        .card-kpi.ciano    { border-left-color: #0891b2; }
        .kpi-numero { font-size: 1.6rem; font-weight: 700; color: #2c3e50; line-height: 1.1; }
        .kpi-label  { font-size: 0.8rem; color: #6c757d; margin-top: 4px; display: flex; align-items: center; gap: 6px; }

        .nav-tabs .nav-link { color: #6c757d; font-weight: 500; border: none; border-bottom: 3px solid transparent; padding: 10px 18px; display: flex; align-items: center; gap: 8px; }
        .nav-tabs .nav-link:hover { color: #0d6efd; }
        .nav-tabs .nav-link.active { color: #0d6efd; background: transparent; border-bottom: 3px solid #0d6efd; font-weight: 600; }
        .nav-tabs .nav-link .badge { font-size: 0.7rem; }

        .select2-container .select2-selection--single { height: 38px; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 38px; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px; }

        .badge-filtro { background: #e9ecef; color: #2c3e50; font-weight: 500; padding: 6px 12px; border-radius: 20px; margin-right: 8px; margin-bottom: 4px; display: inline-flex; align-items: center; gap: 6px; }
        .badge-filtro .remover-filtro { cursor: pointer; opacity: 0.7; }
        .badge-filtro .remover-filtro:hover { opacity: 1; }

        .status-ok    { color: #198754; font-weight: 600; }
        .status-falta { color: #dc3545; font-weight: 600; }

        .badge-tipo { background: #eef2ff; color: #3730a3; padding: 3px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 500; white-space: nowrap; }
        .badge-fid  { background: #fef3c7; color: #92400e; padding: 3px 10px; border-radius: 12px; font-size: 0.78rem; font-weight: 600; font-family: 'Courier New', monospace; }
        .badge-metodo { font-size: 0.72rem; font-weight: 500; padding: 3px 8px; border-radius: 10px; white-space: nowrap; }
        .bg-purple { background-color: #6f42c1 !important; }

        /* NOVO: badges para o campo incluir_mapa_gerencial */
        .badge-mapa-sim { background: #d1fae5; color: #065f46; padding: 3px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; }
        .badge-mapa-nao { background: #f3f4f6; color: #6b7280; padding: 3px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 500; }

        .tabela-compacta td, .tabela-compacta th { padding: 8px 10px; }
        .linha-divergente { background: #fff5f5 !important; }
        .linha-divergente:hover { background: #ffe6e6 !important; }
        .linha-fallback { background: #fffbea !important; }
        .linha-fallback:hover { background: #fff6cc !important; }

        #toastComparativo { position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%); background: rgba(0,0,0,0.85); color: #fff; padding: 12px 28px; border-radius: 30px; font-weight: 500; font-size: 0.95rem; z-index: 99999; box-shadow: 0 4px 12px rgba(0,0,0,0.3); opacity: 0; visibility: hidden; transition: opacity 0.3s, visibility 0.3s; pointer-events: none; }
        #toastComparativo.show { opacity: 1; visibility: visible; }
        #toastComparativo i { margin-right: 8px; }

        @media (max-width: 768px) { .kpi-numero { font-size: 1.3rem; } .container-fluid { padding: 0 10px; } }
    </style>
</head>
<body>
<?php include APP_PATH . '/Views/header_atlas.php'; ?>

<div class="container-fluid mt-4">
    <div class="card">
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <h2 class="sistema-titulo mb-0">
                    <i class="bi bi-columns-gap"></i> Comparativo Bancos — Slides Atlas (40) × Base GEO (16)
                </h2>
                <span class="ultima-atualizacao">
                    <i class="bi bi-clock-history"></i>
                    Última sincronização dos bancos: <strong><?= htmlspecialchars($ultimaAtualizacao) ?></strong>
                </span>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-success btn-sm" id="btnAtualizarTudo">
                    <i class="bi bi-cloud-arrow-down"></i> Atualizar Todos (16 + 40)
                </button>
                <a href="home_atlas" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left"></i> Voltar</a>
            </div>
        </div>

        <p class="text-muted mb-3 small">
            Match por <code>Instrumento</code> em <strong>5 camadas</strong>:
            alias manual → exato → parte do composto (<code>|</code>) → <code>FID (GEO)</code> declarado → comparação compacta.
            Fallback adicional em <code>O_INSTRUM</code> / <code>S_INSTRUM</code> do 16 quando <code>Instrumento ATLAS</code> está vazio.
            Use os seletores de <strong>Campo ID</strong> para escolher qual FID usar no matching e na exibição
            (padrões: <code>fid_principal</code> na 16 e <code>fid</code> na 40).
        </p>

        <!-- NOVO: inclui o KPI de Mapa Gerencial SIM -->
        <div class="row row-cols-2 row-cols-md-3 row-cols-xl-6 g-3 mb-4">
            <div class="col"><div class="card-kpi roxo"><div class="kpi-numero"><?= $total40 ?></div><div class="kpi-label"><i class="bi bi-easel"></i> Slides Atlas (40)</div></div></div>
            <div class="col"><div class="card-kpi azul"><div class="kpi-numero"><?= $total16 ?></div><div class="kpi-label"><i class="bi bi-geo-alt"></i> Base GEO (16)</div></div></div>
            <div class="col"><div class="card-kpi verde"><div class="kpi-numero"><?= $presentesExato ?></div><div class="kpi-label"><i class="bi bi-check-circle"></i> Match exato</div></div></div>
            <div class="col"><div class="card-kpi amarelo"><div class="kpi-numero"><?= $presentesFallbk ?></div><div class="kpi-label"><i class="bi bi-diagram-3"></i> Match via fallback</div></div></div>
            <div class="col"><div class="card-kpi vermelho"><div class="kpi-numero"><?= $ausentesGEO ?></div><div class="kpi-label"><i class="bi bi-exclamation-triangle"></i> Sem GEO</div></div></div>
            <div class="col"><div class="card-kpi ciano"><div class="kpi-numero"><?= $mapaGerencialSim40 ?></div><div class="kpi-label"><i class="bi bi-map"></i> Mapa Gerencial SIM</div></div></div>
        </div>

        <div class="mb-3 small text-muted">
            <strong>Detalhe dos matches:</strong>
            <span class="badge bg-primary ms-1">Parte do | : <?= $contagemMetodos['parte'] ?></span>
            <span class="badge bg-info text-dark ms-1">FID GEO: <?= $contagemMetodos['fid'] ?></span>
            <span class="badge bg-warning text-dark ms-1">Compacto: <?= $contagemMetodos['compacto'] ?></span>
            <span class="badge bg-purple ms-1">Alias: <?= $contagemMetodos['alias'] ?></span>
            <?php if (!empty($aliases)): ?>
                <span class="text-muted ms-2">(<?= count($aliases) ?> alias(es) cadastrado(s))</span>
            <?php endif; ?>
        </div>

        <?php if (!empty($filtrosAplicados)): ?>
        <div class="mb-3 p-2 bg-light rounded-3 d-flex flex-wrap align-items-center">
            <span class="fw-bold me-2"><i class="bi bi-funnel"></i> Filtros aplicados:</span>
            <?php foreach ($filtrosAplicados as $f):
                $novosGET = $_GET; unset($novosGET[$f['param']]);
                $urlSemFiltro = '?' . http_build_query($novosGET);
            ?>
                <span class="badge-filtro">
                    <?= htmlspecialchars($f['label']) ?>: <?= htmlspecialchars($f['valor']) ?>
                    <a href="<?= $urlSemFiltro ?>" class="remover-filtro text-decoration-none" title="Remover"><i class="bi bi-x-circle"></i></a>
                </span>
            <?php endforeach; ?>
            <a href="comparativo_bancos_16x40" class="btn btn-sm btn-outline-secondary ms-2">Limpar todos</a>
        </div>
        <?php endif; ?>

        <form method="GET" class="mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-lg-2 col-md-3 col-sm-6 col-12">
                    <label class="form-label" style="font-size:0.75rem;">UF / Estado</label>
                    <select name="uf" class="form-select filtro-select2">
                        <option value="">Selecione...</option>
                        <?php foreach ($ufs as $uf): ?>
                            <option value="<?= htmlspecialchars($uf) ?>" <?= $filtroUF == $uf ? 'selected' : '' ?>><?= htmlspecialchars($uf) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-3 col-sm-6 col-12">
                    <label class="form-label" style="font-size:0.75rem;">BR</label>
                    <select name="br" class="form-select filtro-select2">
                        <option value="">Selecione...</option>
                        <?php foreach ($brs as $br): ?>
                            <option value="<?= htmlspecialchars($br) ?>" <?= $filtroBR == $br ? 'selected' : '' ?>><?= htmlspecialchars($br) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-3 col-sm-6 col-12">
                    <label class="form-label" style="font-size:0.75rem;">Mapa Gerencial (GEO)</label>
                    <select name="mapa_gerencial" class="form-select filtro-select2">
                        <option value="">Todos</option>
                        <?php if (!$temSimNaLista): ?>
                            <option value="SIM" <?= strcasecmp($filtroMapaGerencial, 'SIM') === 0 ? 'selected' : '' ?>>SIM</option>
                        <?php endif; ?>
                        <?php foreach ($mapaGerencialValores as $v): ?>
                            <option value="<?= htmlspecialchars($v) ?>" <?= strcasecmp($filtroMapaGerencial, $v) === 0 ? 'selected' : '' ?>><?= htmlspecialchars($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-3 col-sm-6 col-12">
                    <label class="form-label" style="font-size:0.75rem;">Método de match</label>
                    <select name="metodo" class="form-select filtro-select2">
                        <option value="">Todos</option>
                        <option value="exato"    <?= $filtroMetodo === 'exato'    ? 'selected' : '' ?>>Match exato</option>
                        <option value="fallback" <?= $filtroMetodo === 'fallback' ? 'selected' : '' ?>>Somente fallback</option>
                        <option value="ausente"  <?= $filtroMetodo === 'ausente'  ? 'selected' : '' ?>>Sem match</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-3 col-sm-12 col-12">
                    <label class="form-label" style="font-size:0.75rem;">Buscar</label>
                    <input type="text" name="busca" class="form-control" value="<?= htmlspecialchars($filtroBusca) ?>" placeholder="Instrumento, empreendimento, FID...">
                </div>
                <div class="col-lg-1 col-md-2 col-sm-6 col-12">
                    <button type="submit" class="btn btn-primary w-100" style="height:38px;"><i class="bi bi-search"></i></button>
                </div>
                <div class="col-lg-1 col-md-2 col-sm-6 col-12">
                    <a href="comparativo_bancos_16x40" class="btn btn-outline-secondary w-100" style="height:38px; display:flex; align-items:center; justify-content:center;"><i class="bi bi-x-circle"></i></a>
                </div>
            </div>

            <!-- NOVO: seletores de Campo ID (16 e 40) -->
            <div class="row g-2 align-items-end mt-1">
                <div class="col-lg-3 col-md-4 col-sm-6 col-12">
                    <label class="form-label" style="font-size:0.75rem;"><i class="bi bi-key"></i> Campo ID — Base 16 (GEO)</label>
                    <select name="campo_id_16" class="form-select filtro-select2">
                        <?php foreach ($camposId16Opcoes as $k => $lbl): ?>
                            <option value="<?= $k ?>" <?= $filtroCampoId16 === $k ? 'selected' : '' ?>><?= htmlspecialchars($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-3 col-md-4 col-sm-6 col-12">
                    <label class="form-label" style="font-size:0.75rem;"><i class="bi bi-key"></i> Campo ID — Base 40 (Slides)</label>
                    <select name="campo_id_40" class="form-select filtro-select2">
                        <?php foreach ($camposId40Opcoes as $k => $lbl): ?>
                            <option value="<?= $k ?>" <?= $filtroCampoId40 === $k ? 'selected' : '' ?>><?= htmlspecialchars($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-6 col-md-4 col-sm-12 col-12">
                    <span class="text-muted small">
                        <i class="bi bi-info-circle"></i>
                        Campo usado no matching por FID e na exibição das colunas.
                        <br>
                        Em uso: <strong>16 = <?= htmlspecialchars($filtroCampoId16) ?></strong> | <strong>40 = <?= htmlspecialchars($filtroCampoId40) ?></strong>
                    </span>
                </div>
            </div>
        </form>

        <ul class="nav nav-tabs mb-3" id="tabsComparativo" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="tab-validacao-tab" data-bs-toggle="tab" data-bs-target="#tab-validacao" type="button" role="tab" aria-selected="true">
                    <i class="bi bi-check2-circle"></i> Validação (Slides Atlas → GEO)
                    <span class="badge bg-secondary"><?= count($validacao) ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-candidatos-tab" data-bs-toggle="tab" data-bs-target="#tab-candidatos" type="button" role="tab" aria-selected="false">
                    <i class="bi bi-exclamation-triangle"></i> Slides sem GEO
                    <span class="badge bg-danger"><?= $totalCandidatos ?></span>
                </button>
            </li>
        </ul>

        <div class="tab-content" id="tabsComparativoContent">

            <!-- ABA VALIDAÇÃO -->
            <div class="tab-pane fade show active" id="tab-validacao" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-hover align-middle tabela-compacta">
                        <thead>
                            <tr>
                                <th class="text-center">FID (Slides)</th>
                                <th>Instrumento (Slides)</th>
                                <th>Estado</th>
                                <th>BR</th>
                                <th>Nome do Empreendimento (Slides)</th>
                                <th class="text-center">Mapa Ger. (GEO)</th>
                                <th class="text-center">Como casou</th>
                                <th class="text-center">FID (GEO)</th>
                                <th>Instrumento ATLAS (GEO)</th>
                                <th>Objeto (GEO)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($validacao)): ?>
                                <tr><td colspan="10" class="text-center text-muted py-4">Nenhum registro encontrado.</td></tr>
                            <?php else: foreach ($validacao as $v):
                                $a = $v['atlas']; $g = $v['geo']; $presente = $v['presente']; $m = $v['metodo'];
                                $isFallback = in_array($m, ['parte', 'fid', 'compacto', 'alias'], true);
                                $rowCls = !$presente ? 'linha-divergente' : ($isFallback ? 'linha-fallback' : '');
                                $label = $metodoLabel[$m] ?? ['label' => $m, 'cls' => 'bg-secondary'];

                                // NOVO: FID exibido respeita os seletores de Campo ID
                                $fidSlides = obterCampoComFallback($a, $filtroCampoId40, ['fid', 'fid_geo']);
                                $fidGeoGEO = $g ? obterCampoComFallback($g, $filtroCampoId16, ['fid_principal', 'fid']) : '';

                                // NOVO: status do mapa gerencial
                                $mg      = trim((string)($a['incluir_mapa_gerencial'] ?? ''));
                                $mgIsSim = strcasecmp($mg, 'SIM') === 0;
                            ?>
                                <tr class="<?= $rowCls ?>">
                                    <td class="text-center"><?php if ($fidSlides !== ''): ?><span class="badge-fid"><?= htmlspecialchars($fidSlides) ?></span><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
                                    <td><strong><?= htmlspecialchars($a['instrumento'] ?? '') ?></strong></td>
                                    <td><?= htmlspecialchars($a['estado'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($a['br'] ?? '') ?></td>
                                    <td><small><?= htmlspecialchars($a['nome_empreendimento_slides'] ?? '') ?></small></td>
                                    <td class="text-center">
                                        <?php if ($mg === ''): ?>
                                            <span class="text-muted">—</span>
                                        <?php elseif ($mgIsSim): ?>
                                            <span class="badge-mapa-sim"><i class="bi bi-check-circle-fill me-1"></i>SIM</span>
                                        <?php else: ?>
                                            <span class="badge-mapa-nao"><?= htmlspecialchars($mg) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><span class="badge badge-metodo <?= $label['cls'] ?>"><?= htmlspecialchars($label['label']) ?></span></td>
                                    <td class="text-center"><?php if ($fidGeoGEO !== ''): ?><span class="badge-fid"><?= htmlspecialchars($fidGeoGEO) ?></span><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
                                    <td><?php if ($g && !empty($g['instrumento_atlas'])): ?><strong><?= htmlspecialchars($g['instrumento_atlas']) ?></strong><?php elseif ($g && !empty($g['o_instrum'])): ?><em class="text-muted"><?= htmlspecialchars($g['o_instrum']) ?> <small>(O_INSTRUM)</small></em><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
                                    <td><?php if ($g && !empty($g['o_obj_resu'])): ?><small><?= htmlspecialchars($g['o_obj_resu']) ?></small><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
                <p class="text-muted small mt-2">
                    <i class="bi bi-info-circle"></i>
                    Linhas <span style="background:#fffbea;padding:2px 6px;border-radius:4px;">amarelas</span> casaram por <strong>fallback</strong> (parte do <code>|</code>, FID GEO, compacto ou alias).
                    Linhas em <span style="background:#fff5f5;padding:2px 6px;border-radius:4px;color:#dc3545;">vermelho</span> <strong>não têm correspondência na GEO</strong>.
                    Coluna <strong>Mapa Ger. (GEO)</strong> reflete o campo <code>incluir_mapa_gerencial</code> da base 40.
                </p>
            </div>

            <!-- ABA SLIDES SEM GEO -->
            <div class="tab-pane fade" id="tab-candidatos" role="tabpanel">
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong>Slides do Atlas (base 40)</strong> que <strong>não possuem correspondência na Base GEO (base 16)</strong>.
                    Use o botão <i class="bi bi-link"></i> <strong>Vincular</strong> para criar um alias manual quando a correspondência existir mas o instrumento estiver grafado diferente.
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle tabela-compacta">
                        <thead>
                            <tr>
                                <th>Instrumento (Slides)</th>
                                <th>Estado</th>
                                <th>BR</th>
                                <th>Lote</th>
                                <th>Nome do Empreendimento (Slides)</th>
                                <th>Nome do Lote (Slides)</th>
                                <th>Status Empreendimento</th>
                                <th class="text-center">Mapa Ger. (GEO)</th>
                                <th class="text-center">FID (GEO) declarado</th>
                                <th class="text-center">Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($candidatos)): ?>
                                <tr><td colspan="10" class="text-center text-muted py-4">Nenhum slide sem GEO encontrado.</td></tr>
                            <?php else: foreach ($candidatos as $a):
                                // NOVO: FID declarado respeita o seletor
                                $fidDeclarado = obterCampoComFallback($a, $filtroCampoId40, ['fid', 'fid_geo']);
                                // NOVO: status do mapa gerencial
                                $mg      = trim((string)($a['incluir_mapa_gerencial'] ?? ''));
                                $mgIsSim = strcasecmp($mg, 'SIM') === 0;
                            ?>
                                <tr class="linha-divergente">
                                    <td><strong><?= htmlspecialchars($a['instrumento'] ?? '') ?></strong></td>
                                    <td><?= htmlspecialchars($a['estado'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($a['br'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($a['lote'] ?? '—') ?></td>
                                    <td><small><?= htmlspecialchars($a['nome_empreendimento_slides'] ?? '') ?></small></td>
                                    <td><small><?= htmlspecialchars($a['nome_lote_slide'] ?? '—') ?></small></td>
                                    <td>
                                        <?php if (!empty($a['status_empreendimento'])): ?>
                                            <span class="badge-tipo"><?= htmlspecialchars($a['status_empreendimento']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($mg === ''): ?>
                                            <span class="text-muted">—</span>
                                        <?php elseif ($mgIsSim): ?>
                                            <span class="badge-mapa-sim"><i class="bi bi-check-circle-fill me-1"></i>SIM</span>
                                        <?php else: ?>
                                            <span class="badge-mapa-nao"><?= htmlspecialchars($mg) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($fidDeclarado !== ''): ?>
                                            <span class="badge-fid"><?= htmlspecialchars($fidDeclarado) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($isAdmin): ?>
                                            <button type="button" class="btn btn-sm btn-outline-primary btn-vincular"
                                                    data-instr="<?= htmlspecialchars($a['instrumento'] ?? '') ?>"
                                                    title="Criar alias manual 40 → 16">
                                                <i class="bi bi-link"></i> Vincular
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted small">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
                <p class="text-muted small mt-2">
                    <i class="bi bi-lightbulb"></i>
                    <strong>Sugestão de correção:</strong> verifique se o instrumento existe na Base GEO com grafia diferente,
                    ou crie o alias manual pelo botão <strong>Vincular</strong>.
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Modal: criar alias manual -->
<div class="modal fade" id="modalVincular" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="criar_alias">
            <input type="hidden" name="instrumento_40" id="alias_instr40">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-link-45deg"></i> Criar alias 40 → 16</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2 small">
                    <i class="bi bi-info-circle"></i> O alias será usado quando o match automático falhar.
                </div>
                <p><strong>Instrumento (Slides/40):</strong><br><code id="alias_instr40_label" class="text-break"></code></p>
                <label class="form-label mt-2"><strong>Selecione o registro correspondente na Base 16:</strong></label>
                <select name="instrumento_16" id="alias_instr16" class="form-select" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($base16 as $r16):
                        $val = !empty($r16['instrumento_atlas']) ? $r16['instrumento_atlas'] : ($r16['o_instrum'] ?? '');
                        if ($val === '') continue;
                        // ALTERADO: label exibe o campo ID selecionado (default fid_principal)
                        $fidExibir = obterCampoComFallback($r16, $filtroCampoId16, ['fid_principal', 'fid']);
                        $lbl = $val
                             . ' | FID ' . ($fidExibir !== '' ? $fidExibir : '?')
                             . ' | ' . ($r16['uf'] ?? '?') . ' ' . ($r16['br'] ?? '')
                             . ' | ' . mb_substr((string)($r16['o_obj_resu'] ?? ''), 0, 70);
                    ?>
                        <option value="<?= htmlspecialchars($val) ?>"><?= htmlspecialchars($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2"></i> Criar alias</button>
            </div>
        </form>
    </div>
</div>

<div id="toastComparativo"></div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    $('.filtro-select2').select2({ placeholder: 'Selecione...', allowClear: false, width: '100%' });
    $('.filtro-select2').on('change', function() { $(this).closest('form').submit(); });
    $('.filtro-select2').on('select2:open', function() {
        setTimeout(function() { var f = document.querySelector('.select2-search__field'); if (f) f.focus(); }, 100);
    });
    $('.filtro-select2').on('focus', function() { $(this).select2('open'); });

    $('#alias_instr16').select2({
        dropdownParent: $('#modalVincular'),
        placeholder: 'Selecione...',
        width: '100%'
    });

    document.querySelectorAll('.btn-vincular').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var instr = this.dataset.instr || '';
            document.getElementById('alias_instr40').value = instr;
            document.getElementById('alias_instr40_label').textContent = instr || '(vazio)';
            var modal = new bootstrap.Modal(document.getElementById('modalVincular'));
            modal.show();
        });
    });

    var toastTimer = null;
    function mostrarToast(msg, tipo) {
        var t = document.getElementById('toastComparativo');
        var icone = tipo === 'erro' ? 'bi-x-circle-fill text-danger' : 'bi-check-circle-fill text-success';
        t.innerHTML = '<i class="bi ' + icone + '"></i> <span>' + msg + '</span>';
        t.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function() { t.classList.remove('show'); }, 4000);
    }

    function atualizar(url, cb) {
        $.ajax({ url: url, method: 'GET', timeout: 300000,
            success: function() { cb(true); },
            error: function(xhr) { console.error('Falha:', url, xhr.status); cb(false); }
        });
    }

    $('#btnAtualizarTudo').on('click', function() {
        if (!confirm('Atualizar TUDO (DataSet 16 + DataSet 40)? Isso pode demorar.')) return;
        var b = this, o = b.innerHTML; b.disabled = true;
        var etapas = [
            { url: 'importar_supra_dataset_16_base_controle_geo.php', nome: 'DataSet 16' },
            { url: 'importar_supra_dataset_40_slides_atlas.php',      nome: 'DataSet 40' }
        ];
        function proxima(i) {
            if (i >= etapas.length) {
                b.disabled = false; b.innerHTML = o;
                mostrarToast('Todos os DataSets foram atualizados!');
                setTimeout(function() { location.reload(); }, 1500);
                return;
            }
            b.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Atualizando ' + etapas[i].nome + '...';
            atualizar(etapas[i].url, function(ok) {
                if (ok) proxima(i + 1);
                else { b.disabled = false; b.innerHTML = o; mostrarToast('Erro ao atualizar ' + etapas[i].nome + '.', 'erro'); }
            });
        }
        proxima(0);
    });
});
</script>
</body>
</html>