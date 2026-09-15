<?php
require_once 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$usuario_nome = $_SESSION['usuario_nome'] ?? 'Usuário';
$usuario_nivel = $_SESSION['usuario_nivel'] ?? 'usuario';

// Contar pendentes (admin)
$pendentes = 0;
if (in_array($usuario_nivel, ['admin_premium', 'admin'])) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE status = 'aguardando'");
    $pendentes = $stmt->fetchColumn();
}

// --- Filtros (GET) ---
$filtro_equipe    = $_GET['equipe'] ?? '';
$filtro_responsavel = $_GET['responsavel'] ?? '';
$filtro_status    = $_GET['status'] ?? '';
$filtro_processo  = $_GET['processo'] ?? '';
$filtro_contrato  = $_GET['contrato'] ?? '';
$filtro_tipo      = $_GET['tipo'] ?? '';
$filtro_assunto   = $_GET['assunto'] ?? '';
$filtro_uf        = $_GET['uf'] ?? '';
$filtro_br        = $_GET['br'] ?? '';

// --- Construção da query com filtros ---
$where = "WHERE 1=1";
$params = [];

if (!empty($filtro_equipe)) {
    $where .= " AND e.id = ?";
    $params[] = $filtro_equipe;
}
if (!empty($filtro_responsavel)) {
    $where .= " AND r.id = ?";
    $params[] = $filtro_responsavel;
}
if (!empty($filtro_contrato)) {
    $where .= " AND c.id = ?";
    $params[] = $filtro_contrato;
}
if (!empty($filtro_tipo)) {
    $where .= " AND t.id = ?";
    $params[] = $filtro_tipo;
}
if (!empty($filtro_uf)) {
    if ($filtro_uf === 'GO/DF') {
        $where .= " AND p.uf IN ('GO','DF')";
    } else {
        $where .= " AND p.uf = ?";
        $params[] = $filtro_uf;
    }
}
if (!empty($filtro_br)) {
    $where .= " AND p.br = ?";
    $params[] = $filtro_br;
}
if (!empty($filtro_processo)) {
    $where .= " AND p.numero_processo LIKE ?";
    $params[] = "%$filtro_processo%";
}

$modoAssunto = !empty($filtro_assunto);
if ($modoAssunto) {
    $where .= " AND p.assunto LIKE ?";
    $params[] = "%$filtro_assunto%";
} else {
    if (!empty($filtro_status)) {
        $where .= " AND s.id = ?";
        $params[] = $filtro_status;
    } else {
        $where .= " AND (s.nome NOT IN ('Assinado', 'Concluído') OR s.nome IS NULL)";
    }
}

$ordem = "p.prazo ASC";
if (!empty($filtro_status)) {
    $stmtStatus = $pdo->prepare("SELECT nome FROM status_processo WHERE id = ?");
    $stmtStatus->execute([$filtro_status]);
    $nomeStatus = $stmtStatus->fetchColumn();
    if (in_array($nomeStatus, ['Assinado', 'Concluído'])) {
        $ordem = "p.prazo DESC";
    }
}
$ordem = "CASE WHEN p.prazo IS NULL THEN 1 ELSE 0 END, " . $ordem;

$sql = "SELECT p.*, 
               c.numero AS contrato_num,
               e.nome AS equipe_nome,
               r.nome AS responsavel_nome,
               s.nome AS status_nome,
               t.nome AS tipo_nome,
               p.sei_criado_1
        FROM processos p
        LEFT JOIN contratos c ON p.contrato_id = c.id
        LEFT JOIN equipes e ON p.equipe_id = e.id
        LEFT JOIN responsaveis r ON p.responsavel_id = r.id
        LEFT JOIN status_processo s ON p.status_id = s.id
        LEFT JOIN tipos t ON p.tipo_id = t.id
        $where
        ORDER BY $ordem";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$processos = $stmt->fetchAll();

// --- CONTADORES ---
$totalProcessos = count($processos);
$contarFazer = 0;
$contarAtrasados = 0;
$hoje = new DateTime();
foreach ($processos as $p) {
    if ($p['status_nome'] == 'Em elaboração') {
        $contarFazer++;
    }
    if (!empty($p['prazo']) && $p['status_nome'] != 'Assinado') {
        $prazo = new DateTime($p['prazo']);
        if ($prazo < $hoje) {
            $contarAtrasados++;
        }
    }
}

// --- Dados para os selects dos filtros ---
$equipes = $pdo->query("SELECT id, nome FROM equipes ORDER BY nome")->fetchAll();
$responsaveis = $pdo->query("SELECT id, nome FROM responsaveis ORDER BY nome")->fetchAll();
$statuses = $pdo->query("SELECT id, nome FROM status_processo ORDER BY nome")->fetchAll();
$contratos = $pdo->query("SELECT id, numero FROM contratos ORDER BY numero")->fetchAll();
$tipos = $pdo->query("SELECT id, nome FROM tipos ORDER BY nome")->fetchAll();
$ufs = $pdo->query("SELECT DISTINCT uf FROM processos ORDER BY uf")->fetchAll(PDO::FETCH_COLUMN);
$brs = $pdo->query("SELECT DISTINCT br FROM processos ORDER BY br")->fetchAll(PDO::FETCH_COLUMN);

foreach ($processos as &$p) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM comentarios WHERE processo_id = ?");
    $stmt->execute([$p['id']]);
    $p['total_comentarios'] = $stmt->fetchColumn();
}
unset($p);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>SISPRO - Sistema de Processos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fc; }
        .container-fluid { max-width: 100%; padding: 0 15px; }
        .card { border: none; border-radius: 16px; box-shadow: 0 8px 30px rgba(0,0,0,0.08); background: #fff; padding: 20px 25px; }
        .table th { font-weight: 600; color: #2c3e50; border-top: none; white-space: nowrap; }
        .table td { vertical-align: middle; word-break: break-word; }
        .table-responsive { overflow-x: auto; }
        .badge-status { padding: 6px 12px; border-radius: 20px; font-weight: 500; }
        .btn-action { border-radius: 8px; padding: 4px 10px; margin: 0 2px; }
        .logo-dnit { max-height: 50px; }
        .sistema-titulo { font-weight: 700; color: #004a8f; font-size: 1.4rem; letter-spacing: 1px; }
        .processo-titulo { font-weight: 700; color: #2c3e50; font-size: 1.8rem; }
        .footer-text { font-size: 0.9rem; color: #6c757d; }
        .comentario-badge { position: relative; cursor: pointer; font-size: 1.3rem; }
        .comentario-badge .badge-dot {
            position: absolute;
            top: -6px;
            right: -6px;
            width: 12px;
            height: 12px;
            background-color: #ffc107;
            border-radius: 50%;
            border: 2px solid white;
            display: none;
        }
        .comentario-badge.has-comentario .badge-dot { display: block; }
        .filtros-row .form-control, .filtros-row .form-select { font-size: 0.9rem; }
        @media (max-width: 768px) {
            .table-responsive { font-size: 0.85rem; }
            .sistema-titulo { font-size: 1.1rem; }
            .processo-titulo { font-size: 1.3rem; }
        }
        .col-min-width { min-width: 100px; }
        .col-min-width-sm { min-width: 80px; }
        .col-min-width-lg { min-width: 150px; }
        .copiar-ao-clicar {
            cursor: pointer;
            color: #0d6efd;
            text-decoration: none;
        }
        .copiar-ao-clicar:hover {
            text-decoration: underline;
        }
        #toastCopiado {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.85);
            color: #fff;
            padding: 12px 28px;
            border-radius: 30px;
            font-weight: 500;
            font-size: 0.95rem;
            z-index: 99999;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            pointer-events: none;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }
        #toastCopiado.show {
            opacity: 1;
            visibility: visible;
        }
        #toastCopiado i {
            margin-right: 8px;
        }
        .badge-status-em-elaboracao { background-color: #FFA500; color: #000; }
        .badge-status-elaborado { background-color: #006400; color: #fff; }
        .badge-status-revisado { background-color: #000; color: #fff; }
        .badge-status-aguardar { background-color: #0000FF; color: #fff; }
        .badge-status-assinado { background-color: #000; color: #fff; }
        .badge-status-marilia { background-color: #800080; color: #fff; }
        .badge-status-concluido { background-color: #000; color: #fff; }
        .text-atrasado { color: #FF0000 !important; font-weight: bold; }
        .text-entregue { color: #000 !important; font-weight: bold; }
        .text-no-prazo { color: #000 !important; font-weight: bold; }
        .icone-alerta {
            color: #FFC107;
            font-size: 1.1rem;
            margin-left: 5px;
            cursor: help;
        }

        /* Comentários em bolhas */
        .comentario-bolha {
            background: #f1f3f5;
            border-radius: 12px;
            padding: 12px 16px;
            margin-bottom: 12px;
            border-left: 4px solid #0d6efd;
            position: relative;
        }
        .comentario-bolha .comentario-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 4px;
        }
        .comentario-bolha .comentario-usuario {
            font-weight: 700;
            color: #0d6efd;
        }
        .comentario-bolha .comentario-data {
            font-size: 0.8rem;
            color: #6c757d;
        }
        .comentario-bolha .comentario-texto {
            margin-top: 4px;
            word-wrap: break-word;
            white-space: pre-wrap;
        }
        .comentario-bolha .comentario-acoes {
            display: flex;
            gap: 4px;
            margin-left: 10px;
        }
        .comentario-bolha .comentario-acoes span {
            cursor: pointer;
            font-size: 0.9rem;
        }
        .comentario-bolha .comentario-acoes span:hover {
            opacity: 0.7;
        }
        .comentario-bolha .comentario-edit-area {
            width: 100%;
        }
        .comentario-bolha .comentario-actions {
            margin-top: 6px;
            display: flex;
            gap: 6px;
        }
    </style>
</head>
<?php include 'chat_widget.php'; ?>
<body>
<div class="container-fluid mt-4">
    <div class="card">
        <!-- Cabeçalho -->
        <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap">
            <div class="d-flex align-items-center">
                <img src="https://www.gov.br/dnit/pt-br/central-de-conteudos/publicacoes/manual-de-gestao-da-marca/marcas-dnit/assinaturas-e-marcas/monocromatica-dnit-extenso.png" alt="DNIT" class="logo-dnit me-3">
                <span class="sistema-titulo"><i class="bi bi-diagram-3"></i> Sistema de Processos - SISPRO</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span><i class="bi bi-person-circle"></i> Olá, <strong><?= htmlspecialchars($usuario_nome ?? '') ?></strong></span>
                <?php if (in_array($usuario_nivel, ['admin_premium', 'admin'])): ?>
                    <a href="admin.php" class="btn btn-outline-primary btn-sm position-relative">
                        <i class="bi bi-people"></i> Admin
                        <?php if ($pendentes > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= $pendentes ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="gerencial.php" class="btn btn-outline-info btn-sm">
                        <i class="bi bi-graph-up-arrow"></i> Gerencial
                    </a>
                    <a href="notificacoes_rdci.php" class="btn btn-outline-warning btn-sm">
                        <i class="bi bi-bell"></i> Notificações RDCI
                    </a>
                    <a href="rdci.php" class="btn btn-outline-info btn-sm">
                        <i class="bi bi-table"></i> RDCI
                    </a>
                <?php endif; ?>
                <a href="modelos.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-files"></i> Modelos</a>
                <a href="logout.php" class="btn btn-outline-danger btn-sm"><i class="bi bi-box-arrow-right"></i> Sair</a>
            </div>
        </div>

        <!-- Título, contadores e botão -->
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap">
            <div>
                <h2 class="processo-titulo">Processos</h2>
                <div class="mt-1">
                    <span class="badge bg-secondary me-2">Total: <?= $totalProcessos ?></span>
                    <span class="badge bg-warning text-dark me-2">A fazer: <?= $contarFazer ?></span>
                    <span class="badge bg-danger">Atrasados: <?= $contarAtrasados ?></span>
                </div>
            </div>
            <a href="create.php" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Novo Processo</a>
        </div>

        <!-- Filtros -->
        <form method="GET" class="mb-4">
            <div class="row g-2 filtros-row">
                <div class="col-md-2">
                    <label class="form-label">Equipe</label>
                    <select name="equipe" class="form-select" onchange="this.form.submit();">
                        <option value="">Todas</option>
                        <?php foreach ($equipes as $e): ?>
                            <option value="<?= $e['id'] ?>" <?= ($filtro_equipe == $e['id']) ? 'selected' : '' ?>><?= htmlspecialchars($e['nome'] ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Responsável</label>
                    <select name="responsavel" class="form-select" onchange="this.form.submit();">
                        <option value="">Todos</option>
                        <?php foreach ($responsaveis as $r): ?>
                            <option value="<?= $r['id'] ?>" <?= ($filtro_responsavel == $r['id']) ? 'selected' : '' ?>><?= htmlspecialchars($r['nome'] ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select" onchange="this.form.submit();">
                        <option value="">Todos</option>
                        <?php foreach ($statuses as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= ($filtro_status == $s['id']) ? 'selected' : '' ?>><?= htmlspecialchars($s['nome'] ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Contrato</label>
                    <select name="contrato" class="form-select" onchange="this.form.submit();">
                        <option value="">Todos</option>
                        <?php foreach ($contratos as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ($filtro_contrato == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['numero'] ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Assunto</label>
                    <input type="text" name="assunto" class="form-control" value="<?= htmlspecialchars($filtro_assunto ?? '') ?>" placeholder="Palavra-chave" onchange="this.form.submit();">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <a href="index.php" class="btn btn-secondary w-100">Limpar</a>
                </div>
            </div>

            <div class="mt-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnFiltrosExtras">
                    <i class="bi bi-plus-circle"></i> Mais filtros
                </button>
            </div>

            <div id="filtrosExtras" style="display: none; margin-top: 10px;">
                <div class="row g-2">
                    <div class="col-md-2">
                        <label class="form-label">Processo (nº)</label>
                        <input type="text" name="processo" class="form-control" value="<?= htmlspecialchars($filtro_processo ?? '') ?>" placeholder="Digite o nº" onchange="this.form.submit();">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Tipo</label>
                        <select name="tipo" class="form-select" onchange="this.form.submit();">
                            <option value="">Todos</option>
                            <?php foreach ($tipos as $t): ?>
                                <option value="<?= $t['id'] ?>" <?= ($filtro_tipo == $t['id']) ? 'selected' : '' ?>><?= htmlspecialchars($t['nome'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">UF</label>
                        <select name="uf" class="form-select" onchange="this.form.submit();">
                            <option value="">Todas</option>
                            <?php foreach ($ufs as $uf): ?>
                                <option value="<?= htmlspecialchars($uf ?? '') ?>" <?= ($filtro_uf == $uf) ? 'selected' : '' ?>><?= htmlspecialchars($uf ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">BR</label>
                        <select name="br" class="form-select" onchange="this.form.submit();">
                            <option value="">Todas</option>
                            <?php foreach ($brs as $br): ?>
                                <option value="<?= htmlspecialchars($br ?? '') ?>" <?= ($filtro_br == $br) ? 'selected' : '' ?>><?= htmlspecialchars($br ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </form>

        <!-- Tabela -->
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <colgroup>
                    <col class="col-min-width">
                    <col class="col-min-width">
                    <col class="col-min-width-sm">
                    <col class="col-min-width-lg">
                    <col style="min-width: 100px;"> <!-- SEI Criado 1 -->
                    <col style="min-width: 200px;">
                    <col class="col-min-width">
                    <col style="min-width: 60px;">
                    <col style="min-width: 80px;">
                    <col style="min-width: 110px;">
                    <col style="min-width: 110px;">
                    <col style="min-width: 90px;">
                    <col style="min-width: 70px;">
                    <col style="min-width: 140px;">
                </colgroup>
                <thead>
                    <tr>
                        <th>Equipe</th>
                        <th>Responsável</th>
                        <th>Status</th>
                        <th>Nº Processo</th>
                        <th>SEI Criado 1</th>
                        <th>Assunto</th>
                        <th>Contrato</th>
                        <th>UF</th>
                        <th>BR</th>
                        <th>Data Entrada</th>
                        <th>Prazo</th>
                        <th>Situação</th>
                        <th class="text-center">Coment.</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($processos as $p): ?>
                    <?php
                        $statusNome = $p['status_nome'] ?? '';
                        $statusClasses = [
                            'Em elaboração' => 'badge-status-em-elaboracao',
                            'Elaborado'     => 'badge-status-elaborado',
                            'Revisado'      => 'badge-status-revisado',
                            'Aguardar'      => 'badge-status-aguardar',
                            'Assinado'      => 'badge-status-assinado',
                            'Marília'       => 'badge-status-marilia',
                            'Concluído'     => 'badge-status-concluido'
                        ];
                        $statusClass = $statusClasses[$statusNome] ?? 'bg-secondary';
                        $situacao = '';
                        $classe_situacao = '';
                        if ($statusNome == 'Assinado') {
                            $situacao = 'Entregue';
                            $classe_situacao = 'text-entregue';
                        } elseif (!empty($p['prazo'])) {
                            $hoje = new DateTime();
                            $prazo = new DateTime($p['prazo']);
                            if ($prazo < $hoje) {
                                $situacao = 'Atrasado';
                                $classe_situacao = 'text-atrasado';
                            } else {
                                $situacao = 'No prazo';
                                $classe_situacao = 'text-no-prazo';
                            }
                        } else {
                            $situacao = 'Sem prazo';
                            $classe_situacao = 'text-muted';
                        }
                        $mostrarAlerta = ($statusNome == 'Revisado' && ($p['cadastrado_sima'] == 0 || $p['cadastrado_sima'] == 'Não'));
                        $tem_comentario = ($p['total_comentarios'] > 0);
                        $seiCriado1 = !empty($p['sei_criado_1']) ? $p['sei_criado_1'] : '-';
                        $numeroProcesso = $p['numero_processo'] ?? '';
                    ?>
                    <tr>
                        <td class="text-nowrap"><?= htmlspecialchars($p['equipe_nome'] ?? '') ?></td>
                        <td class="text-nowrap"><?= htmlspecialchars($p['responsavel_nome'] ?? '') ?></td>
                        <td class="text-nowrap">
                            <span class="badge <?= $statusClass ?>"><?= htmlspecialchars($statusNome) ?></span>
                            <?php if ($mostrarAlerta): ?>
                                <i class="bi bi-exclamation-triangle-fill icone-alerta" title="Necessita Cadastro no SIMA"></i>
                            <?php endif; ?>
                        </td>
                        <td class="text-nowrap">
                            <span class="copiar-ao-clicar" data-copiar="<?= htmlspecialchars($numeroProcesso) ?>" title="Clique para copiar o número do processo">
                                <?= htmlspecialchars($numeroProcesso) ?>
                            </span>
                        </td>
                        <td class="text-nowrap">
                            <?php if ($seiCriado1 !== '-'): ?>
                                <span class="copiar-ao-clicar" data-copiar="<?= htmlspecialchars($seiCriado1) ?>" title="Clique para copiar o SEI">
                                    <?= htmlspecialchars($seiCriado1) ?>
                                </span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($p['assunto'] ?? '') ?></td>
                        <td class="text-nowrap"><?= htmlspecialchars($p['contrato_num'] ?? '') ?></td>
                        <td class="text-nowrap"><?= htmlspecialchars($p['uf'] ?? '') ?></td>
                        <td class="text-nowrap"><?= htmlspecialchars($p['br'] ?? '') ?></td>
                        <td class="text-nowrap"><?= isset($p['data_entrada']) ? date('d/m/Y', strtotime($p['data_entrada'])) : '-' ?></td>
                        <td class="text-nowrap"><?= isset($p['prazo']) && $p['prazo'] ? date('d/m/Y', strtotime($p['prazo'])) : '-' ?></td>
                        <td class="text-nowrap <?= $classe_situacao ?>"><?= $situacao ?></td>
                        <td class="text-center">
                            <span class="comentario-badge <?= $tem_comentario ? 'has-comentario' : '' ?>" 
                                  data-bs-toggle="modal" 
                                  data-bs-target="#modalComentario" 
                                  data-processo-id="<?= $p['id'] ?>"
                                  data-processo-num="<?= htmlspecialchars($p['numero_processo'] ?? '') ?>"
                                  title="Comentários">
                                <i class="bi bi-chat-dots"></i>
                                <span class="badge-dot"></span>
                            </span>
                        </td>
                        <td class="text-center text-nowrap">
                            <a href="view.php?id=<?= $p['id'] ?>" class="btn btn-outline-secondary btn-action" title="Visualizar"><i class="bi bi-eye"></i></a>
                            <a href="edit.php?id=<?= $p['id'] ?>" class="btn btn-outline-primary btn-action" title="Editar"><i class="bi bi-pencil"></i></a>
                            <a href="delete.php?id=<?= $p['id'] ?>" class="btn btn-outline-danger btn-action" title="Excluir" onclick="return confirm('Tem certeza?')"><i class="bi bi-trash"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($processos)): ?>
                    <tr><td colspan="14" class="text-center text-muted py-4">Nenhum processo encontrado.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="footer-text text-center mt-4">
            Desenvolvido por <strong>Bruno Pimenta</strong> - Versão 1.0 - <?= date('Y') ?>
        </div>
    </div>
</div>

<!-- Toast de notificação -->
<div id="toastCopiado"><i class="bi bi-check-circle-fill text-success"></i> Copiado!</div>

<!-- Modal de Comentários -->
<div class="modal fade modal-comentario" id="modalComentario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-chat-dots"></i> Histórico de Comentários - Processo <span id="modal-processo-num"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="lista-comentarios">
                    <p class="text-muted text-center">Carregando...</p>
                </div>
                <hr>
                <form id="form-comentario">
                    <input type="hidden" id="comentario-processo-id">
                    <div class="mb-2">
                        <label class="form-label">Digite seu comentário</label>
                        <textarea class="form-control" id="novo-comentario" rows="2" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-send"></i> Enviar</button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('novo-comentario').value='';">Limpar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    var usuarioNivel = <?= json_encode($usuario_nivel) ?>;
    var usuarioId = <?= json_encode($usuario_id) ?>;

    // ============================================================
    // TOAST
    // ============================================================
    var toastTimer = null;

    function mostrarToast(mensagem) {
        var toast = document.getElementById('toastCopiado');
        if (!toast) return;
        toast.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i> ' + mensagem;
        toast.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function() {
            toast.classList.remove('show');
        }, 2000);
    }

    // ============================================================
    // COPIAR TEXTO (via Clipboard API + fallback)
    // ============================================================
    function copiarTexto(texto) {
        if (!texto || texto === '-') {
            mostrarToast('Nada para copiar');
            return;
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(texto)
                .then(function() {
                    mostrarToast('Copiado: ' + texto);
                })
                .catch(function() {
                    fallbackCopiar(texto);
                });
        } else {
            fallbackCopiar(texto);
        }
    }

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
            if (sucesso) {
                mostrarToast('Copiado: ' + texto);
            } else {
                mostrarToast('Erro ao copiar');
            }
        } catch (e) {
            mostrarToast('Erro ao copiar');
        }
        document.body.removeChild(textarea);
    }

    // ============================================================
    // EVENTOS DE CLIQUE PARA COPIAR (delegados)
    // ============================================================
    $(document).on('click', '.copiar-ao-clicar', function() {
        var texto = $(this).data('copiar');
        copiarTexto(texto);
    });

    // ============================================================
    // MODAL DE COMENTÁRIOS
    // ============================================================
    $('#modalComentario').on('show.bs.modal', function(event) {
        var trigger = $(event.relatedTarget);
        var processoId = trigger.data('processo-id');
        var processoNum = trigger.data('processo-num');
        $('#modal-processo-num').text(processoNum);
        $('#comentario-processo-id').val(processoId);
        carregarComentarios(processoId);
    });

    function carregarComentarios(processoId) {
        $('#lista-comentarios').html('<p class="text-muted text-center">Carregando...</p>');
        $.ajax({
            url: 'ajax_comentarios.php',
            method: 'GET',
            data: { action: 'get', processo_id: processoId },
            dataType: 'json',
            success: function(data) {
                var html = '';
                if (!data || data.length === 0) {
                    html = '<p class="text-muted text-center">Nenhum comentário ainda.</p>';
                } else {
                    for (var i = 0; i < data.length; i++) {
                        var c = data[i];
                        var texto = c.comentario || '';
                        var podeEditar = (usuarioNivel === 'admin_premium' || c.usuario_id == usuarioId);
                        var podeExcluir = (usuarioNivel === 'admin_premium');
                        var btnEditar = podeEditar ? ' <span class="btn-editar-comentario text-primary" data-id="' + c.id + '" title="Editar"><i class="bi bi-pencil"></i></span>' : '';
                        var btnExcluir = podeExcluir ? ' <span class="btn-excluir-comentario text-danger" data-id="' + c.id + '" title="Excluir"><i class="bi bi-trash"></i></span>' : '';
                        html += '<div class="comentario-bolha" data-id="' + c.id + '">' +
                                    '<div class="comentario-header">' +
                                        '<span class="comentario-usuario">' + escaparHtml(c.usuario_nome) + '</span>' +
                                        '<div class="comentario-acoes">' +
                                            '<span class="comentario-data">' + c.created_at + '</span>' +
                                            btnEditar + btnExcluir +
                                        '</div>' +
                                    '</div>' +
                                    '<div class="comentario-texto">' + escaparHtml(texto) + '</div>' +
                                '</div>';
                    }
                }
                $('#lista-comentarios').html(html);

                // Editar
                $('.btn-editar-comentario').off('click').on('click', function() {
                    var id = $(this).data('id');
                    var bolha = $(this).closest('.comentario-bolha');
                    var textoAtual = bolha.find('.comentario-texto').text().trim();
                    var areaEdit = $('<textarea class="form-control comentario-edit-area" rows="2">' + textoAtual + '</textarea>');
                    var botoes = $('<div class="comentario-actions">' +
                                    '<button class="btn btn-success btn-sm salvar-edicao">Salvar</button> ' +
                                    '<button class="btn btn-secondary btn-sm cancelar-edicao">Cancelar</button>' +
                                '</div>');
                    bolha.find('.comentario-texto').replaceWith(areaEdit);
                    bolha.find('.comentario-acoes').after(botoes);
                    bolha.find('.btn-editar-comentario, .btn-excluir-comentario').hide();
                    botoes.find('.salvar-edicao').on('click', function() {
                        var novoTexto = areaEdit.val().trim();
                        if (!novoTexto) { alert('O comentário não pode ficar vazio.'); return; }
                        $.ajax({
                            url: 'ajax_comentarios.php',
                            method: 'POST',
                            data: { action: 'editar', id: id, comentario: novoTexto },
                            dataType: 'json',
                            success: function(res) {
                                if (res.success) {
                                    var processoId = $('#comentario-processo-id').val();
                                    carregarComentarios(processoId);
                                } else {
                                    alert('Erro ao editar: ' + (res.error || 'Desconhecido'));
                                }
                            },
                            error: function() { alert('Erro de comunicação.'); }
                        });
                    });
                    botoes.find('.cancelar-edicao').on('click', function() {
                        var processoId = $('#comentario-processo-id').val();
                        carregarComentarios(processoId);
                    });
                });

                // Excluir
                $('.btn-excluir-comentario').off('click').on('click', function() {
                    var id = $(this).data('id');
                    if (confirm('Excluir este comentário?')) {
                        $.ajax({
                            url: 'ajax_comentarios.php',
                            method: 'POST',
                            data: { action: 'excluir', id: id },
                            dataType: 'json',
                            success: function(res) {
                                if (res.success) {
                                    var processoId = $('#comentario-processo-id').val();
                                    carregarComentarios(processoId);
                                    atualizarBadge(processoId);
                                } else {
                                    alert('Erro: ' + (res.error || 'Desconhecido'));
                                }
                            },
                            error: function() { alert('Erro de comunicação.'); }
                        });
                    }
                });
            },
            error: function() {
                $('#lista-comentarios').html('<p class="text-danger text-center">Erro ao carregar comentários.</p>');
            }
        });
    }

    function atualizarBadge(processoId) {
        $.ajax({
            url: 'ajax_comentarios.php',
            method: 'GET',
            data: { action: 'get', processo_id: processoId, count_only: 1 },
            dataType: 'json',
            success: function(data) {
                var total = data.total || 0;
                var badge = $('.comentario-badge[data-processo-id="' + processoId + '"]');
                if (total > 0) badge.addClass('has-comentario');
                else badge.removeClass('has-comentario');
            }
        });
    }

    $('#form-comentario').on('submit', function(e) {
        e.preventDefault();
        var processoId = $('#comentario-processo-id').val();
        var comentario = $('#novo-comentario').val().trim();
        if (!comentario) return;
        $.ajax({
            url: 'ajax_comentarios.php',
            method: 'POST',
            data: { action: 'adicionar', processo_id: processoId, comentario: comentario },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    $('#novo-comentario').val('');
                    carregarComentarios(processoId);
                    var badge = $('.comentario-badge[data-processo-id="' + processoId + '"]');
                    badge.addClass('has-comentario');
                } else {
                    alert('Erro: ' + (res.error || 'Desconhecido'));
                }
            },
            error: function() { alert('Erro de comunicação.'); }
        });
    });

    function escaparHtml(texto) {
        return $('<div>').text(texto).html();
    }

    // Filtros extras
    $('#btnFiltrosExtras').on('click', function() {
        $('#filtrosExtras').slideToggle();
        $(this).find('i').toggleClass('bi-plus-circle bi-dash-circle');
    });

});
</script>
</body>
</html>