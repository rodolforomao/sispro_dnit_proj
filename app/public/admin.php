<?php
require_once 'config.php';

// Proteger - apenas admin_premium e admin
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['usuario_nivel'], ['admin_premium', 'admin'])) {
    header('Location: index.php');
    exit;
}

// Mapeamentos para exibição amigável
$niveis_map = [
    'admin_premium' => 'Admin Premium',
    'admin' => 'Administrador',
    'usuario' => 'Usuário',
    'leitor' => 'Leitor'
];

$status_map = [
    'ativo' => 'Ativo',
    'inativo' => 'Inativo',
    'pendente' => 'Pendente'
];

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? 0;
    $action = $_POST['action'] ?? '';

    if ($action === 'aprovar') {
        $stmt = $pdo->prepare("UPDATE usuarios SET status = 'ativo' WHERE id = ?");
        $stmt->execute([$id]);
    } elseif ($action === 'reprovar') {
        $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->execute([$id]);
    } elseif ($action === 'editar') {
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $nivel = $_POST['nivel'] ?? 'usuario';
        $status = $_POST['status'] ?? 'ativo';
        $senha = trim($_POST['senha'] ?? '');

        if (!empty($nome) && !empty($email)) {
            $sql = "UPDATE usuarios SET nome = ?, email = ?, nivel = ?, status = ?";
            $params = [$nome, $email, $nivel, $status];
            if (!empty($senha)) {
                $sql .= ", senha = ?";
                $params[] = md5($senha);
            }
            $sql .= " WHERE id = ?";
            $params[] = $id;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }
    } elseif ($action === 'excluir') {
        // Impede que o usuário exclua a si mesmo
        if ($id == $_SESSION['usuario_id']) {
            header('Location: admin.php?erro=1');
            exit;
        }
        $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->execute([$id]);
    }
    header('Location: admin.php');
    exit;
}

// Buscar usuários
$pendentes = $pdo->query("SELECT * FROM usuarios WHERE status = 'pendente' ORDER BY created_at ASC")->fetchAll();
$todos = $pdo->query("SELECT * FROM usuarios ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Administração - Usuários</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fc; }
        .container-fluid { max-width: 1400px; }
        .card { border: none; border-radius: 16px; box-shadow: 0 8px 30px rgba(0,0,0,0.08); padding: 20px 25px; margin-bottom: 20px; background: #fff; }
        .badge-status { padding: 6px 12px; border-radius: 20px; font-weight: 500; }
        .table td { vertical-align: middle; }
        .acoes { display: flex; gap: 4px; flex-wrap: nowrap; justify-content: center; align-items: center; }
        .acoes form { display: inline; margin: 0; }
        .acoes .btn { white-space: nowrap; }
        .btn-excluir { color: #dc3545; border-color: #dc3545; }
        .btn-excluir:hover { background: #dc3545; color: #fff; }
        .alert-erro { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 8px; padding: 10px 15px; margin-bottom: 15px; }
    </style>
</head>
<body>
<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-people"></i> Administração de Usuários</h2>
        <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>

    <!-- Exibir erro se tentou excluir a si mesmo -->
    <?php if (isset($_GET['erro']) && $_GET['erro'] == 1): ?>
    <div class="alert-erro">
        <i class="bi bi-exclamation-triangle"></i> Você não pode excluir seu próprio usuário.
    </div>
    <?php endif; ?>

    <!-- Pendentes -->
    <div class="card">
        <h5><i class="bi bi-clock-history text-warning"></i> Solicitações Pendentes</h5>
        <?php if (count($pendentes) > 0): ?>
            <div class="alert alert-info">Há <?= count($pendentes) ?> solicitação(ões) aguardando análise.</div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Email</th>
                            <th>Solicitado em</th>
                            <th class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendentes as $u): ?>
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
                                        <button type="submit" name="action" value="reprovar" class="btn btn-danger btn-sm" onclick="return confirm('Tem certeza que deseja reprovar este usuário?')"><i class="bi bi-x-circle"></i> Reprovar</button>
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
        <h5><i class="bi bi-list-ul"></i> Todos os Usuários</h5>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Email</th>
                        <th>Nível</th>
                        <th>Status</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($todos as $u): ?>
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
                        <td class="text-center">
                            <div class="acoes">
                                <!-- Editar -->
                                <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalEditar" 
                                        data-id="<?= $u['id'] ?>"
                                        data-nome="<?= htmlspecialchars($u['nome']) ?>"
                                        data-email="<?= htmlspecialchars($u['email']) ?>"
                                        data-nivel="<?= $u['nivel'] ?>"
                                        data-status="<?= $u['status'] ?>">
                                    <i class="bi bi-pencil"></i> Editar
                                </button>
                                <!-- Excluir -->
                                <form method="POST" onsubmit="return confirm('Tem certeza que deseja excluir este usuário? Esta ação não pode ser desfeita.');">
                                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                    <input type="hidden" name="action" value="excluir">
                                    <button type="submit" class="btn btn-outline-danger btn-sm" title="Excluir usuário">
                                        <i class="bi bi-trash"></i> Excluir
                                    </button>
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

<!-- Modal de Edição -->
<div class="modal fade" id="modalEditar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Editar Usuário</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="id" id="edit-id">
                <input type="hidden" name="action" value="editar">
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Nome</label>
                        <input type="text" name="nome" id="edit-nome" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Email</label>
                        <input type="email" name="email" id="edit-email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Nível</label>
                        <select name="nivel" id="edit-nivel" class="form-select">
                            <option value="leitor">Leitor</option>
                            <option value="usuario">Usuário</option>
                            <option value="admin">Administrador</option>
                            <option value="admin_premium">Admin Premium</option>
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
    document.querySelectorAll('[data-bs-toggle="modal"]').forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('edit-id').value = this.dataset.id;
            document.getElementById('edit-nome').value = this.dataset.nome;
            document.getElementById('edit-email').value = this.dataset.email;
            document.getElementById('edit-nivel').value = this.dataset.nivel;
            document.getElementById('edit-status').value = this.dataset.status;
        });
    });
</script>
</body>
</html>