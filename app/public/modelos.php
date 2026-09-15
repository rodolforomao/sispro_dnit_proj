<?php
require_once 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$usuario_nome = $_SESSION['usuario_nome'] ?? 'Usuário';
$usuario_nivel = $_SESSION['usuario_nivel'] ?? 'usuario';

// Buscar modelos por categoria
$categorias = ['projetos', 'assessoria', 'oficios'];
$modelos = [];
foreach ($categorias as $cat) {
    $stmt = $pdo->prepare("SELECT * FROM modelos WHERE categoria = ? ORDER BY data DESC, id DESC");
    $stmt->execute([$cat]);
    $modelos[$cat] = $stmt->fetchAll();
}

$badgeClasses = [
    'projetos' => 'badge-projetos',
    'assessoria' => 'badge-assessoria',
    'oficios' => 'badge-oficios'
];
$labels = [
    'projetos' => 'Projetos',
    'assessoria' => 'Assessoria',
    'oficios' => 'Ofícios / Portarias'
];

// Função para verificar se a data tem mais de 4 meses
function dataDefasada($data) {
    if (empty($data)) return false;
    $dataModelo = new DateTime($data);
    $hoje = new DateTime();
    $diferenca = $hoje->diff($dataModelo);
    $meses = ($diferenca->y * 12) + $diferenca->m;
    return ($meses >= 4);
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Modelos - SISPROC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fc; }
        .container-fluid { max-width: 98%; padding: 0 15px; }
        .card { border: none; border-radius: 16px; box-shadow: 0 8px 30px rgba(0,0,0,0.08); background: #fff; padding: 20px 25px; }
        .table th { font-weight: 600; color: #2c3e50; border-top: none; }
        .table td { vertical-align: middle; }
        .table-responsive { overflow-x: auto; }
        .logo-dnit { max-height: 50px; }
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
        .copiar-ao-clicar:hover {
            text-decoration: underline;
        }
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
        @media (max-width: 768px) { .container-fluid { padding: 10px; } .card { padding: 15px; } }
    </style>
</head>
<body>
<div class="container-fluid mt-4">
    <div class="card">
        <!-- Cabeçalho -->
        <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap">
            <div class="d-flex align-items-center">
                <img src="https://www.gov.br/dnit/pt-br/central-de-conteudos/publicacoes/manual-de-gestao-da-marca/marcas-dnit/assinaturas-e-marcas/monocromatica-dnit-extenso.png" alt="DNIT" class="logo-dnit me-3">
                <span class="sistema-titulo"><i class="bi bi-diagram-3"></i> Sistema de Processos - SISPROC</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span><i class="bi bi-person-circle"></i> Olá, <strong><?= htmlspecialchars($usuario_nome ?? '') ?></strong></span>
                <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Voltar</a>
                <a href="logout.php" class="btn btn-outline-danger btn-sm"><i class="bi bi-box-arrow-right"></i> Sair</a>
            </div>
        </div>

        <div class="d-flex align-items-center justify-content-between mb-3">
            <h2 class="mb-0" style="font-weight: 700; color: #2c3e50;"><i class="bi bi-files"></i> Modelos de Documentos</h2>
            <button class="btn btn-primary" onclick="abrirModalNovo()">
                <i class="bi bi-plus-circle"></i> Novo Modelo
            </button>
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
                                $defasado = dataDefasada($m['data']);
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
    // MODAL / CRUD (mantido)
    // ============================================================
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
                console.log(xhr.responseText);
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
                console.log(xhr.responseText);
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

    // Expor funções globalmente para os onclick
    window.abrirModalNovo = abrirModalNovo;
    window.editarModelo = editarModelo;
    window.salvarModelo = salvarModelo;
    window.excluirModelo = excluirModelo;

});
</script>
</body>
</html>