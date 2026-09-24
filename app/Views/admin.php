<?php
if (!in_array($usuario_nivel ?? '', ['desenvolvedor', 'admin', 'admin_premium'])) {
    header('Location: index.php');
    exit;
}

// --- BLOCO DE DEFINIÇÃO DE VARIÁVEIS (IGUAL À HOME) ---
if (!function_exists('obterIniciais')) {
    function obterIniciais($nome) {
        $partes = explode(' ', trim($nome));
        $iniciais = '';
        foreach ($partes as $parte) {
            if (!empty($parte)) {
                $iniciais .= strtoupper(substr($parte, 0, 1));
            }
        }
        return substr($iniciais, 0, 2);
    }
}

$usuario_nome  = $usuario_nome  ?? $_SESSION['usuario_nome']  ?? 'Usuário';
$usuario_nivel = $usuario_nivel ?? $_SESSION['usuario_nivel'] ?? 'usuario';
$usuario_id    = $usuario_id    ?? $_SESSION['usuario_id']    ?? 0;
$setor_slug    = $_SESSION['setor_slug'] ?? 'assessoria-projetos';
$setor_nome    = $setor_slug === 'assessoria-projetos' ? 'Assessoria e Projetos' : 'Atlas/Monitoramento';

$isAdmin = in_array($usuario_nivel, ['desenvolvedor', 'admin']);

// Contar pendentes (para o badge)
$total_pendentes = 0;
if ($isAdmin) {
    try {
        global $pdo;
        $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE status = 'pendente'");
        $total_pendentes = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        $total_pendentes = 0;
    }
}

$total_setores = 0;
if ($usuario_id) {
    try {
        global $pdo;
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuario_setor WHERE usuario_id = ?");
        $stmt->execute([$usuario_id]);
        $total_setores = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        $total_setores = 0;
    }
}

$setores_usuario = [];
if ($usuario_id) {
    try {
        global $pdo;
        $stmt = $pdo->prepare("
            SELECT s.nome 
            FROM setores s 
            INNER JOIN usuario_setor us ON s.id = us.setor_id 
            WHERE us.usuario_id = ?
        ");
        $stmt->execute([$usuario_id]);
        $setores_usuario = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        $setores_usuario = [];
    }
}
// --- FIM DO BLOCO ---

$niveis_map = [
    'desenvolvedor' => 'Desenvolvedor',
    'admin'         => 'Administrador',
    'admin_premium' => 'Admin Premium',
    'usuario'       => 'Usuário',
    'leitor'        => 'Leitor'
];
$status_map = [
    'ativo'    => 'Ativo',
    'inativo'  => 'Inativo',
    'pendente' => 'Pendente'
];

if (!isset($setores)) $setores = [];
if (!isset($equipes)) $equipes = [];

// --- PROTEÇÃO DEFENSIVA + ISOLAMENTO DE ESCOPO ---
$pendentesAdmin = (isset($pendentes) && is_array($pendentes)) ? $pendentes : [];
$todosAdmin     = (isset($todos)     && is_array($todos))     ? $todos     : [];
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Administração - Usuários</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fc; padding-top: 70px; }
        .container-fluid { max-width: 1400px; }
        .card { border: none; border-radius: 16px; box-shadow: 0 8px 30px rgba(0,0,0,0.08); padding: 20px 25px; margin-bottom: 20px; background: #fff; }
        .badge-status { padding: 6px 12px; border-radius: 20px; font-weight: 500; }
        .table td { vertical-align: middle; }
        .acoes { display: flex; gap: 4px; flex-wrap: nowrap; justify-content: center; align-items: center; }
        .acoes form { display: inline; margin: 0; }
        .sistema-titulo { font-weight: 700; color: #004a8f; font-size: 1.4rem; letter-spacing: 1px; }
        .checkbox-group { display: flex; gap: 20px; flex-wrap: wrap; }
        .checkbox-group .form-check { margin-right: 15px; }
        .setores-col { max-width: 250px; }
        .alert-erro { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 8px; padding: 10px 15px; margin-bottom: 15px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; border-radius: 8px; padding: 10px 15px; margin-bottom: 15px; }

        /* ============================================================
           BARRA SUPERIOR FIXA (IGUAL À HOME)
           ============================================================ */
        .topbar {
            position: fixed; top: 0; left: 0; right: 0; z-index: 1030;
            background: #ffffff; border-bottom: 1px solid #dce1e8;
            padding: 8px 20px; display: flex; align-items: center;
            justify-content: space-between; flex-wrap: wrap;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); min-height: 60px;
        }
        .topbar-left { display: flex; align-items: center; gap: 10px; }
        .topbar-left .logo-dnit { max-height: 40px; width: auto; }
        .topbar-left .sistema-titulo { font-weight: 700; color: #004a8f; font-size: 1.2rem; letter-spacing: 1px; }
        .topbar-center { flex: 1; text-align: center; }
        .topbar-center .badge-setor { background: #17a2b8; color: #fff; font-size: 0.9rem; padding: 6px 14px; }
        .topbar-right { display: flex; align-items: center; gap: 10px; }
        .topbar-right .btn-icon { background: none; border: none; font-size: 1.5rem; color: #6c757d; padding: 0 6px; cursor: pointer; transition: color 0.2s; }
        .topbar-right .btn-icon:hover { color: #004a8f; }
        .topbar-right .btn-avatar { position: relative; background: none; border: none; padding: 0; cursor: pointer; }
        .topbar-right .btn-avatar .avatar-icon { width: 38px; height: 38px; border-radius: 50%; background: #0d6efd; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 1rem; text-transform: uppercase; }
        .topbar-right .btn-avatar .badge-notificacao { position: absolute; top: -4px; right: -4px; background-color: #dc3545; color: #fff; border-radius: 50%; padding: 2px 6px; font-size: 0.65rem; font-weight: 700; line-height: 1; border: 2px solid #fff; min-width: 18px; text-align: center; }
        .dropdown-menu-avatar { min-width: 220px; padding: 8px 0; }
        .dropdown-menu-avatar .dropdown-item { padding: 10px 20px; font-size: 0.95rem; display: flex; align-items: center; gap: 10px; }
        .dropdown-menu-avatar .dropdown-item i { font-size: 1.2rem; width: 24px; text-align: center; }
        .dropdown-menu-avatar .dropdown-header { padding: 10px 20px; font-weight: 600; color: #2c3e50; border-bottom: 1px solid #dce1e8; margin-bottom: 4px; }
        .dropdown-menu-avatar .dropdown-header small { display: block; font-weight: 400; font-size: 0.85rem; color: #6c757d; margin-top: 2px; }
    </style>
</head>
<?php include APP_PATH . '/public/chat_widget.php'; ?>
<body>

<?php include APP_PATH . '/Views/header.php'; ?>
<!-- ============================================================
     CONTEÚDO PRINCIPAL
     ============================================================ -->
<div class="container-fluid mt-4">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div class="d-flex align-items-center">
            <span class="sistema-titulo"><i class="bi bi-people"></i> Administração de Usuários</span>
        </div>
        <div>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalCriar">
                <i class="bi bi-plus-circle"></i> Novo Usuário
            </button>
            <a href="index" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
        </div>
    </div>

    <?php if (isset($_GET['erro']) && $_GET['erro'] == 1): ?>
    <div class="alert-erro"><i class="bi bi-exclamation-triangle"></i> Você não pode excluir seu próprio usuário.</div>
    <?php endif; ?>
    <?php if (isset($_GET['sucesso']) && $_GET['sucesso'] == 1): ?>
    <div class="alert-success"><i class="bi bi-check-circle"></i> Operação realizada com sucesso!</div>
    <?php endif; ?>

    <!-- Pendentes -->
    <div class="card">
        <h5><i class="bi bi-clock-history text-warning"></i> Solicitações Pendentes</h5>
        <?php if (count($pendentesAdmin) > 0): ?>
            <div class="alert alert-info">Há <?= count($pendentesAdmin) ?> solicitação(ões) aguardando análise.</div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead><tr><th>Nome</th><th>Email</th><th>Solicitado em</th><th class="text-center">Ações</th></tr></thead>
                    <tbody>
                        <?php foreach ($pendentesAdmin as $u): ?>
                        <tr>
                            <td><?= htmlspecialchars($u['nome']) ?></td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($u['created_at'])) ?></td>
                            <td class="text-center">
                                <div class="acoes">
                                    <form method="POST">
                                        <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                        <button type="submit" name="action" value="aprovar" class="btn btn-success btn-sm"><i class="bi bi-check-circle"></i> Aprovar</button>
                                    </form>
                                    <form method="POST">
                                        <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                        <button type="submit" name="action" value="reprovar" class="btn btn-danger btn-sm" onclick="return confirm('Tem certeza?')"><i class="bi bi-x-circle"></i> Reprovar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted">Nenhuma solicitação pendente.</p>
        <?php endif; ?>
    </div>

    <!-- Todos os usuários -->
    <div class="card">
        <h5><i class="bi bi-list-ul"></i> Todos os Usuários (<?= count($todosAdmin) ?>)</h5>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr><th>Nome</th><th>Email</th><th>Nível</th><th>Status</th><th>Equipe</th><th class="setores-col">Setores</th><th class="text-center">Ações</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($todosAdmin as $u): ?>
                    <tr>
                        <td><?= htmlspecialchars($u['nome']) ?></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td><span class="badge bg-primary badge-status"><?= htmlspecialchars($niveis_map[$u['nivel']] ?? $u['nivel']) ?></span></td>
                        <td>
                            <?php
                            $status_class = $u['status'] === 'ativo' ? 'success' : ($u['status'] === 'inativo' ? 'danger' : 'warning');
                            ?>
                            <span class="badge bg-<?= $status_class ?> badge-status"><?= htmlspecialchars($status_map[$u['status']] ?? $u['status']) ?></span>
                        </td>
                        <td>
                            <?php
                            $equipeNome = '';
                            if (!empty($u['equipe_id'])) {
                                foreach ($equipes as $eq) {
                                    if ($eq['id'] == $u['equipe_id']) {
                                        $equipeNome = $eq['nome'];
                                        break;
                                    }
                                }
                            }
                            echo htmlspecialchars($equipeNome ?: '—');
                            ?>
                        </td>
                        <td class="setores-col">
                            <?php
                            if (!empty($u['setores']) && is_array($u['setores'])) {
                                $nomes = [];
                                foreach ($setores as $s) {
                                    if (in_array($s['id'], $u['setores'])) {
                                        $nomes[] = $s['nome'];
                                    }
                                }
                                echo implode(', ', array_map('htmlspecialchars', $nomes));
                            } else {
                                echo '<span class="text-muted">—</span>';
                            }
                            ?>
                        </td>
                        <td class="text-center">
                            <div class="acoes">
                                <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalEditar" 
                                        data-id="<?= $u['id'] ?>"
                                        data-nome="<?= htmlspecialchars($u['nome']) ?>"
                                        data-email="<?= htmlspecialchars($u['email']) ?>"
                                        data-nivel="<?= $u['nivel'] ?>"
                                        data-status="<?= $u['status'] ?>"
                                        data-setores="<?= htmlspecialchars(json_encode(array_values(array_map('strval', $u['setores'] ?? [])))) ?>"
                                        data-equipe="<?= $u['equipe_id'] ?? '' ?>">
                                    <i class="bi bi-pencil"></i> Editar
                                </button>
                                <form method="POST" onsubmit="return confirm('Tem certeza que deseja excluir este usuário?')">
                                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                    <input type="hidden" name="action" value="excluir">
                                    <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i> Excluir</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Criar -->
<div class="modal fade" id="modalCriar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="bi bi-person-plus"></i> Criar Novo Usuário</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <input type="hidden" name="action" value="criar">
                <div class="modal-body">
                    <div class="mb-3"><label>Nome</label><input type="text" name="nome" class="form-control" required></div>
                    <div class="mb-3"><label>Email</label><input type="email" name="email" class="form-control" required></div>
                    <div class="mb-3"><label>Senha</label><input type="password" name="senha" class="form-control" required></div>
                    <div class="mb-3">
                        <label>Nível</label>
                        <select name="nivel" class="form-select">
                            <option value="leitor">Leitor</option>
                            <option value="usuario" selected>Usuário</option>
                            <option value="admin">Administrador</option>
                            <option value="desenvolvedor">Desenvolvedor</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Status</label>
                        <select name="status" class="form-select">
                            <option value="ativo">Ativo</option>
                            <option value="inativo">Inativo</option>
                            <option value="pendente">Pendente</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Equipe</label>
                        <select name="equipe_id" class="form-select">
                            <option value="">Selecione...</option>
                            <?php foreach ($equipes as $eq): ?>
                                <option value="<?= $eq['id'] ?>"><?= htmlspecialchars($eq['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Setores</label>
                        <div class="checkbox-group">
                            <?php foreach ($setores as $s): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="setores[]" value="<?= $s['id'] ?>" id="criar_setor_<?= $s['id'] ?>">
                                <label class="form-check-label" for="criar_setor_<?= $s['id'] ?>"><?= htmlspecialchars($s['nome']) ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">Criar Usuário</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar -->
<div class="modal fade" id="modalEditar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="bi bi-pencil-square"></i> Editar Usuário</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <input type="hidden" name="id" id="edit-id">
                <input type="hidden" name="action" value="editar">
                <div class="modal-body">
                    <div class="mb-3"><label>Nome</label><input type="text" name="nome" id="edit-nome" class="form-control" required></div>
                    <div class="mb-3"><label>Email</label><input type="email" name="email" id="edit-email" class="form-control" required></div>
                    <div class="mb-3">
                        <label>Nível</label>
                        <select name="nivel" id="edit-nivel" class="form-select">
                            <option value="leitor">Leitor</option>
                            <option value="usuario">Usuário</option>
                            <option value="admin">Administrador</option>
                            <option value="desenvolvedor">Desenvolvedor</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Status</label>
                        <select name="status" id="edit-status" class="form-select">
                            <option value="ativo">Ativo</option>
                            <option value="inativo">Inativo</option>
                            <option value="pendente">Pendente</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Nova Senha (deixe em branco para manter)</label>
                        <input type="password" name="senha" class="form-control" placeholder="Digite nova senha">
                    </div>
                    <div class="mb-3">
                        <label>Equipe</label>
                        <select name="equipe_id" id="edit-equipe" class="form-select">
                            <option value="">Selecione...</option>
                            <?php foreach ($equipes as $eq): ?>
                                <option value="<?= $eq['id'] ?>"><?= htmlspecialchars($eq['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Setores</label>
                        <div class="checkbox-group" id="edit-setores-container">
                            <?php foreach ($setores as $s): ?>
                            <div class="form-check">
                                <input class="form-check-input edit-setor" type="checkbox" name="setores[]" value="<?= $s['id'] ?>" id="edit_setor_<?= $s['id'] ?>">
                                <label class="form-check-label" for="edit_setor_<?= $s['id'] ?>"><?= htmlspecialchars($s['nome']) ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('[data-bs-toggle="modal"][data-bs-target="#modalEditar"]').forEach(function(button) {
    button.addEventListener('click', function() {
        document.getElementById('edit-id').value    = this.dataset.id;
        document.getElementById('edit-nome').value  = this.dataset.nome;
        document.getElementById('edit-email').value = this.dataset.email;
        document.getElementById('edit-nivel').value = this.dataset.nivel;
        document.getElementById('edit-status').value = this.dataset.status;
        document.getElementById('edit-equipe').value = this.dataset.equipe || '';

        // ✅ Correção: normaliza tudo para string antes de comparar
        var setoresSelecionados = [];
        try {
            setoresSelecionados = JSON.parse(this.dataset.setores || '[]');
        } catch (e) {
            setoresSelecionados = [];
        }
        // Converte todos os IDs para string, evitando conflito number vs string
        setoresSelecionados = setoresSelecionados.map(function(v) { return String(v); });

        document.querySelectorAll('.edit-setor').forEach(function(cb) {
            cb.checked = setoresSelecionados.indexOf(String(cb.value)) !== -1;
        });
    });
});
</script>
</body>
</html>