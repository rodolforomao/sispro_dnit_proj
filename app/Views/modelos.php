<?php
// View modelos.php
// Variáveis disponíveis: $modelos, $categorias, $labels, $badgeClasses, $usuario_nome, $usuario_nivel

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

$usuario_nome = $usuario_nome ?? $_SESSION['usuario_nome'] ?? 'Usuário';
$usuario_nivel = $usuario_nivel ?? $_SESSION['usuario_nivel'] ?? 'usuario';
$usuario_id = $_SESSION['usuario_id'] ?? 0;
$setor_slug = $_SESSION['setor_slug'] ?? 'assessoria-projetos';
$setor_nome = $setor_slug === 'assessoria-projetos' ? 'Assessoria e Projetos' : 'Atlas/Monitoramento';

$isAdmin = in_array($usuario_nivel, ['desenvolvedor', 'admin']);

$pendentes = 0;
if ($isAdmin) {
    try {
        global $pdo;
        if (isset($pdo)) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE status = 'pendente'");
            $pendentes = (int)$stmt->fetchColumn();
        }
    } catch (Exception $e) {
        $pendentes = 0;
    }
}

$total_setores = 0;
if ($usuario_id) {
    try {
        global $pdo;
        if (isset($pdo)) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuario_setor WHERE usuario_id = ?");
            $stmt->execute([$usuario_id]);
            $total_setores = (int)$stmt->fetchColumn();
        }
    } catch (Exception $e) {
        $total_setores = 0;
    }
}

$setores_usuario = [];
if ($usuario_id) {
    try {
        global $pdo;
        if (isset($pdo)) {
            $stmt = $pdo->prepare("
                SELECT s.nome 
                FROM setores s 
                INNER JOIN usuario_setor us ON s.id = us.setor_id 
                WHERE us.usuario_id = ?
            ");
            $stmt->execute([$usuario_id]);
            $setores_usuario = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
    } catch (Exception $e) {
        $setores_usuario = [];
    }
}
// --- FIM DO BLOCO ---
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Modelos - SISPRO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fc; padding-top: 70px; }
        .container-fluid { max-width: 98%; padding: 0 15px; }
        .card { border: none; border-radius: 16px; box-shadow: 0 8px 30px rgba(0,0,0,0.08); background: #fff; padding: 20px 25px; }
        .table th { font-weight: 600; color: #2c3e50; border-top: none; }
        .table td { vertical-align: middle; }
        .table-responsive { overflow-x: auto; }
        .sistema-titulo { font-weight: 700; color: #004a8f; font-size: 1.4rem; letter-spacing: 1px; }
        .categoria-titulo { font-weight: 700; color: #2c3e50; font-size: 1.3rem; border-bottom: 3px solid #4a90e2; padding-bottom: 8px; margin-bottom: 15px; }
        .badge-projetos { background: #0d6efd; color: #fff; }
        .badge-assessoria { background: #198754; color: #fff; }
        .badge-oficios { background: #6f42c1; color: #fff; }
        .copiar-ao-clicar {
            cursor: pointer;
            color: #0d6efd;
            text-decoration: none;
            font-family: monospace;
            font-weight: 600;
        }
        .copiar-ao-clicar:hover { text-decoration: underline; }
        .data-defasada { color: #dc3545 !important; font-weight: bold; }
        .acao-btn { padding: 2px 6px; font-size: 0.8rem; }
        .modal-lg-custom { max-width: 800px; }
        .obs-defasagem {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 0.95rem;
        }
        .obs-defasagem i { color: #ffc107; }
        #toastCopiado {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0,0,0,0.85);
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
        #toastCopiado.show { opacity: 1; visibility: visible; }
        #toastCopiado i { margin-right: 8px; }
        @media (max-width: 768px) { .container-fluid { padding: 10px; } .card { padding: 15px; } }

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

<div class="container-fluid mt-4">
    <div class="card">
        <!-- Cabeçalho -->
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap">
            <h2 class="mb-0" style="font-weight: 700; color: #2c3e50;">
                <i class="bi bi-files"></i> Modelos de Documentos
            </h2>
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-primary" onclick="abrirModalNovo()">
                    <i class="bi bi-plus-circle"></i> Novo Modelo
                </button>
                <a href="index" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i> Voltar
                </a>
            </div>
        </div>

        <!-- Observação sobre datas defasadas -->
        <div class="obs-defasagem">
            <i class="bi bi-info-circle"></i>
            <strong>Obs.:</strong> Modelos defasados em <strong>4 meses</strong> mostram datas em <span style="color: #dc3545; font-weight: bold;">vermelho</span>.
        </div>

        <!-- Galerias -->
        <?php foreach ($categorias as $cat):
            $lista = $modelos[$cat] ?? [];
            $badgeClass = $badgeClasses[$cat] ?? '';
            $label = $labels[$cat] ?? ucfirst($cat);
        ?>
        <div class="mb-4">
            <h4 class="categoria-titulo"><span class="badge <?= $badgeClass ?> me-2"><?= $label ?></span> Modelos</h4>
            <?php if (empty($lista)): ?>
                <p class="text-muted">Nenhum modelo cadastrado.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="tabela-<?= $cat ?>">
                        <thead>
                            <tr>
                                <th style="width: 10%;">SEI</th>
                                <th style="width: 60%;">Descrição</th>
                                <th style="width: 15%;">Data</th>
                                <th style="width: 15%;" class="text-center">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lista as $m):
                                $defasado = function_exists('dataDefasada') ? dataDefasada($m['data']) : false;
                                $dataClass = $defasado ? 'data-defasada' : '';
                            ?>
                            <tr data-id="<?= $m['id'] ?>">
                                <td>
                                    <span class="copiar-ao-clicar" data-copiar="<?= htmlspecialchars($m['sei'] ?? '') ?>" title="Clique para copiar o SEI">
                                        <?= htmlspecialchars($m['sei'] ?? '-') ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($m['descricao']) ?></td>
                                <td class="<?= $dataClass ?>"><?= $m['data'] ? date('d/m/Y', strtotime($m['data'])) : '-' ?></td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-primary acao-btn" onclick="editarModelo(<?= $m['id'] ?>)"><i class="bi bi-pencil"></i></button>
                                    <button class="btn btn-sm btn-outline-danger acao-btn" onclick="excluirModelo(<?= $m['id'] ?>)"><i class="bi bi-trash"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <div class="text-muted small text-center mt-3">
            Clique no número SEI para copiar.
        </div>
    </div>
</div>

<!-- Toast de notificação -->
<div id="toastCopiado"><i class="bi bi-check-circle-fill text-success"></i> Copiado!</div>

<!-- Modal -->
<div class="modal fade" id="modalModelo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg-custom">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitulo"><i class="bi bi-file-earmark-text"></i> Novo Modelo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formModelo" onsubmit="salvarModelo(event)">
                <div class="modal-body">
                    <input type="hidden" name="id" id="modeloId">
                    <div class="mb-3">
                        <label class="form-label">Categoria</label>
                        <select name="categoria" id="modeloCategoria" class="form-select" required>
                            <option value="projetos">Projetos</option>
                            <option value="assessoria">Assessoria</option>
                            <option value="oficios">Ofícios / Portarias</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <input type="text" name="descricao" id="modeloDescricao" class="form-control" maxlength="255" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">SEI (opcional)</label>
                        <input type="text" name="sei" id="modeloSei" class="form-control" maxlength="20" placeholder="Ex: 25598464">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Data</label>
                        <input type="date" name="data" id="modeloData" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnSalvar"><i class="bi bi-save"></i> Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {

    // Toast
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

    // Copiar texto
    function copiarTexto(texto) {
        if (!texto || texto === '-') {
            mostrarToast('Nada para copiar');
            return;
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(texto)
                .then(function() { mostrarToast('Copiado: ' + texto); })
                .catch(function() { fallbackCopiar(texto); });
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
            if (sucesso) mostrarToast('Copiado: ' + texto);
            else mostrarToast('Erro ao copiar');
        } catch (e) {
            mostrarToast('Erro ao copiar');
        }
        document.body.removeChild(textarea);
    }

    $(document).on('click', '.copiar-ao-clicar', function() {
        var texto = $(this).data('copiar');
        copiarTexto(texto);
    });

    // CRUD Modelos
    function abrirModalNovo() {
        document.getElementById('modalTitulo').innerHTML = '<i class="bi bi-file-earmark-text"></i> Novo Modelo';
        document.getElementById('formModelo').reset();
        document.getElementById('modeloId').value = '';
        document.getElementById('btnSalvar').innerHTML = '<i class="bi bi-save"></i> Salvar';
        var modal = new bootstrap.Modal(document.getElementById('modalModelo'));
        modal.show();
    }

    function editarModelo(id) {
        $.ajax({
            url: 'ajax_modelos.php',
            method: 'GET',
            data: { action: 'get', id: id },
            dataType: 'json',
            success: function(data) {
                if (data.error) {
                    alert('Erro: ' + data.error);
                    return;
                }
                document.getElementById('modalTitulo').innerHTML = '<i class="bi bi-pencil-square"></i> Editar Modelo';
                document.getElementById('modeloId').value = data.id;
                document.getElementById('modeloCategoria').value = data.categoria;
                document.getElementById('modeloDescricao').value = data.descricao;
                document.getElementById('modeloSei').value = data.sei || '';
                document.getElementById('modeloData').value = data.data || '';
                document.getElementById('btnSalvar').innerHTML = '<i class="bi bi-save"></i> Atualizar';
                var modal = new bootstrap.Modal(document.getElementById('modalModelo'));
                modal.show();
            },
            error: function(xhr, status, error) {
                alert('Erro ao carregar dados: ' + error);
            }
        });
    }

    function salvarModelo(e) {
        e.preventDefault();
        var form = document.getElementById('formModelo');
        var dados = new FormData(form);
        dados.append('action', 'salvar');

        $.ajax({
            url: 'ajax_modelos.php',
            method: 'POST',
            data: dados,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(res) {
                if (res.error) {
                    alert('Erro: ' + res.error);
                    return;
                }
                bootstrap.Modal.getInstance(document.getElementById('modalModelo')).hide();
                location.reload();
            },
            error: function(xhr, status, error) {
                alert('Erro ao salvar: ' + error);
            }
        });
    }

    function excluirModelo(id) {
        if (!confirm('Tem certeza que deseja excluir este modelo?')) return;
        $.ajax({
            url: 'ajax_modelos.php',
            method: 'POST',
            data: { action: 'excluir', id: id },
            dataType: 'json',
            success: function(res) {
                if (res.error) {
                    alert('Erro: ' + res.error);
                    return;
                }
                $('tr[data-id="' + id + '"]').remove();
            },
            error: function(xhr, status, error) {
                alert('Erro ao excluir: ' + error);
            }
        });
    }

    window.abrirModalNovo = abrirModalNovo;
    window.editarModelo = editarModelo;
    window.salvarModelo = salvarModelo;
    window.excluirModelo = excluirModelo;

});
</script>
</body>
</html>