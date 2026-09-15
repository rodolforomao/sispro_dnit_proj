<?php
require_once 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['error' => 'Não autenticado']);
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$usuario_nivel = $_SESSION['usuario_nivel'] ?? 'usuario';
$action = $_REQUEST['action'] ?? '';

// ============================================================
// 1. LISTAR CONVERSAS (com nome do responsável)
// ============================================================
if ($action === 'listar_conversas') {
    $conversas = [];

    // 1.1 "Todos"
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM mensagens 
                           WHERE destinatario_id IS NULL AND equipe_id IS NULL 
                           AND lida = 0 AND remetente_id != ?");
    $stmt->execute([$usuario_id]);
    $naoLidasTodos = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT mensagem, data_envio FROM mensagens 
                           WHERE destinatario_id IS NULL AND equipe_id IS NULL 
                           ORDER BY data_envio DESC LIMIT 1");
    $stmt->execute();
    $ultimaTodos = $stmt->fetch();
    
    $conversas[] = [
        'tipo' => 'todos',
        'id' => 'todos',
        'nome' => 'Todos',
        'ultima_mensagem' => $ultimaTodos['mensagem'] ?? null,
        'ultima_data' => $ultimaTodos['data_envio'] ?? null,
        'nao_lidas' => $naoLidasTodos,
        'is_online' => false
    ];

    // 1.2 Usuários (apenas ativos, com nome do responsável)
    // NOTA: Mostramos apenas com quem já houve interação (conversa existente)
    $sql = "SELECT u.id, 
                   u.last_activity,
                   COALESCE(r.nome, SUBSTRING_INDEX(u.nome, ' ', 1)) as nome,
                   (SELECT m.mensagem FROM mensagens m 
                    WHERE (m.remetente_id = u.id AND m.destinatario_id = ?) 
                       OR (m.remetente_id = ? AND m.destinatario_id = u.id)
                       OR (m.remetente_id = u.id AND m.destinatario_id IS NULL AND m.equipe_id IS NULL)
                       OR (m.remetente_id = ? AND m.destinatario_id IS NULL AND m.equipe_id IS NULL)
                    ORDER BY m.data_envio DESC LIMIT 1) as ultima_mensagem,
                   (SELECT m.data_envio FROM mensagens m 
                    WHERE (m.remetente_id = u.id AND m.destinatario_id = ?) 
                       OR (m.remetente_id = ? AND m.destinatario_id = u.id)
                       OR (m.remetente_id = u.id AND m.destinatario_id IS NULL AND m.equipe_id IS NULL)
                       OR (m.remetente_id = ? AND m.destinatario_id IS NULL AND m.equipe_id IS NULL)
                    ORDER BY m.data_envio DESC LIMIT 1) as ultima_data,
                   (SELECT COUNT(*) FROM mensagens m 
                    WHERE m.destinatario_id = ? AND m.remetente_id = u.id AND m.lida = 0) as nao_lidas
            FROM usuarios u
            LEFT JOIN responsaveis r ON u.id = r.usuario_id
            WHERE u.id != ? 
              AND u.status = 'ativo'
              AND (
                  EXISTS (SELECT 1 FROM mensagens m2 
                          WHERE (m2.remetente_id = u.id AND m2.destinatario_id = ?)
                             OR (m2.remetente_id = ? AND m2.destinatario_id = u.id)
                             OR (m2.remetente_id = u.id AND m2.destinatario_id IS NULL AND m2.equipe_id IS NULL)
                             OR (m2.remetente_id = ? AND m2.destinatario_id IS NULL AND m2.equipe_id IS NULL))
                  OR (SELECT COUNT(*) FROM mensagens m3 
                      WHERE m3.destinatario_id = ? AND m3.remetente_id = u.id AND m3.lida = 0) > 0
              )
            ORDER BY ultima_data DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$usuario_id, $usuario_id, $usuario_id, 
                    $usuario_id, $usuario_id, $usuario_id, 
                    $usuario_id, $usuario_id,
                    $usuario_id, $usuario_id, $usuario_id, $usuario_id]);
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($usuarios as $u) {
        $online = (time() - strtotime($u['last_activity'])) < 300;
        $conversas[] = [
            'tipo' => 'usuario',
            'id' => $u['id'],
            'nome' => $u['nome'],
            'ultima_mensagem' => $u['ultima_mensagem'],
            'ultima_data' => $u['ultima_data'],
            'nao_lidas' => $u['nao_lidas'],
            'is_online' => $online
        ];
    }

    // 1.3 Equipes (com última mensagem)
    $equipes = $pdo->query("SELECT id, nome FROM equipes ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($equipes as $e) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM mensagens 
                               WHERE equipe_id = ? AND lida = 0 AND remetente_id != ?");
        $stmt->execute([$e['id'], $usuario_id]);
        $nao = (int)$stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT mensagem, data_envio FROM mensagens 
                               WHERE equipe_id = ? ORDER BY data_envio DESC LIMIT 1");
        $stmt->execute([$e['id']]);
        $ult = $stmt->fetch();
        
        if ($ult || $nao > 0) {
            $conversas[] = [
                'tipo' => 'equipe',
                'id' => $e['id'],
                'nome' => $e['nome'],
                'ultima_mensagem' => $ult['mensagem'] ?? null,
                'ultima_data' => $ult['data_envio'] ?? null,
                'nao_lidas' => $nao,
                'is_online' => false
            ];
        }
    }

    // Ordenar por última data (mais recente primeiro)
    usort($conversas, function($a, $b) {
        return strtotime($b['ultima_data'] ?? '1970-01-01') 
             - strtotime($a['ultima_data'] ?? '1970-01-01');
    });

    echo json_encode($conversas);
    exit;
}

// ============================================================
// 2. PESQUISAR USUÁRIOS (para iniciar conversa)
// ============================================================
if ($action === 'pesquisar_usuarios') {
    $termo = trim($_GET['termo'] ?? '');
    if (strlen($termo) < 2) {
        echo json_encode([]);
        exit;
    }
    
    $sql = "SELECT u.id, 
                   COALESCE(r.nome, SUBSTRING_INDEX(u.nome, ' ', 1)) as nome,
                   u.last_activity
            FROM usuarios u
            LEFT JOIN responsaveis r ON u.id = r.usuario_id
            WHERE u.id != ? 
              AND u.status = 'ativo'
              AND (u.nome LIKE ? OR r.nome LIKE ?)
            ORDER BY nome ASC
            LIMIT 20";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$usuario_id, "%$termo%", "%$termo%"]);
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($usuarios as &$u) {
        $u['is_online'] = (time() - strtotime($u['last_activity'])) < 300;
        $u['tipo'] = 'usuario';
        $u['ultima_mensagem'] = null;
        $u['ultima_data'] = null;
        $u['nao_lidas'] = 0;
    }
    
    echo json_encode($usuarios);
    exit;
}

// ============================================================
// 3. CARREGAR MENSAGENS
// ============================================================
if ($action === 'carregar_mensagens') {
    $destinatario_id = $_GET['destinatario_id'] ?? null;
    $equipe_id = $_GET['equipe_id'] ?? null;
    $limit = (int)($_GET['limit'] ?? 50);
    $offset = (int)($_GET['offset'] ?? 0);
    
    $where = "1=1";
    $params = [];
    
    if ($destinatario_id === 'todos') {
        $where .= " AND destinatario_id IS NULL AND equipe_id IS NULL";
    } elseif ($destinatario_id !== null && $destinatario_id !== '') {
        $where .= " AND ((destinatario_id = ? AND remetente_id = ?) 
                        OR (destinatario_id = ? AND remetente_id = ?))";
        $params[] = $usuario_id;
        $params[] = $destinatario_id;
        $params[] = $destinatario_id;
        $params[] = $usuario_id;
    } elseif ($equipe_id !== null && $equipe_id !== '') {
        $where .= " AND equipe_id = ?";
        $params[] = $equipe_id;
    } else {
        echo json_encode(['error' => 'Destino inválido']);
        exit;
    }
    
    $sql = "SELECT m.*, 
                   COALESCE(r.nome, SUBSTRING_INDEX(u.nome, ' ', 1)) as remetente_nome 
            FROM mensagens m
            LEFT JOIN usuarios u ON m.remetente_id = u.id
            LEFT JOIN responsaveis r ON u.id = r.usuario_id
            WHERE $where
            ORDER BY m.data_envio DESC
            LIMIT ? OFFSET ?";
    
    $stmt = $pdo->prepare($sql);
    
    $idx = 1;
    foreach ($params as $p) {
        $stmt->bindValue($idx, $p);
        $idx++;
    }
    $stmt->bindValue($idx, $limit, PDO::PARAM_INT);
    $stmt->bindValue($idx+1, $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $mensagens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $mensagens = array_reverse($mensagens);
    
    // Marcar como lidas
    if ($destinatario_id !== null && $destinatario_id !== 'todos') {
        $stmt = $pdo->prepare("UPDATE mensagens 
                               SET lida = 1 
                               WHERE destinatario_id = ? AND remetente_id = ? AND lida = 0");
        $stmt->execute([$usuario_id, $destinatario_id]);
    } elseif ($equipe_id !== null) {
        $stmt = $pdo->prepare("UPDATE mensagens 
                               SET lida = 1 
                               WHERE equipe_id = ? AND remetente_id != ? AND lida = 0");
        $stmt->execute([$equipe_id, $usuario_id]);
    } elseif ($destinatario_id === 'todos') {
        $stmt = $pdo->prepare("UPDATE mensagens 
                               SET lida = 1 
                               WHERE destinatario_id IS NULL 
                                 AND equipe_id IS NULL 
                                 AND remetente_id != ? 
                                 AND lida = 0");
        $stmt->execute([$usuario_id]);
    }
    
    echo json_encode($mensagens);
    exit;
}

// ============================================================
// 4. ENVIAR MENSAGEM
// ============================================================
if ($action === 'enviar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $destinatario_id = $_POST['destinatario_id'] ?? null;
    $equipe_id = $_POST['equipe_id'] ?? null;
    $mensagem = trim($_POST['mensagem'] ?? '');
    
    if (empty($mensagem)) {
        echo json_encode(['error' => 'Mensagem vazia']);
        exit;
    }
    
    if (empty($destinatario_id) && empty($equipe_id)) {
        echo json_encode(['error' => 'Selecione um destinatário']);
        exit;
    }
    
    if ($destinatario_id === 'todos') {
        $destinatario_id = null;
        $equipe_id = null;
    } elseif ($destinatario_id !== null && $destinatario_id !== '') {
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE id = ? AND status = 'ativo'");
        $stmt->execute([$destinatario_id]);
        if (!$stmt->fetch()) {
            echo json_encode(['error' => 'Usuário não existe ou está inativo']);
            exit;
        }
        $equipe_id = null;
    } elseif ($equipe_id !== null && $equipe_id !== '') {
        $stmt = $pdo->prepare("SELECT id FROM equipes WHERE id = ?");
        $stmt->execute([$equipe_id]);
        if (!$stmt->fetch()) {
            echo json_encode(['error' => 'Equipe não existe']);
            exit;
        }
        $destinatario_id = null;
    }
    
    $stmt = $pdo->prepare("INSERT INTO mensagens (remetente_id, destinatario_id, equipe_id, mensagem) 
                           VALUES (?, ?, ?, ?)");
    $stmt->execute([$usuario_id, $destinatario_id, $equipe_id, $mensagem]);
    $id = $pdo->lastInsertId();
    
    // Buscar o nome do remetente para notificação
    $stmt = $pdo->prepare("SELECT COALESCE(r.nome, SUBSTRING_INDEX(u.nome, ' ', 1)) as nome
                           FROM usuarios u
                           LEFT JOIN responsaveis r ON u.id = r.usuario_id
                           WHERE u.id = ?");
    $stmt->execute([$usuario_id]);
    $remetente = $stmt->fetchColumn();
    
    echo json_encode([
        'success' => true, 
        'id' => $id,
        'remetente_nome' => $remetente
    ]);
    exit;
}

// ============================================================
// 5. CONTAR NÃO LIDAS
// ============================================================
if ($action === 'contar_nao_lidas') {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM mensagens 
                           WHERE destinatario_id = ? AND lida = 0");
    $stmt->execute([$usuario_id]);
    $total = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM mensagens 
                           WHERE equipe_id IN (SELECT equipe_id FROM processos WHERE responsavel_id = ? GROUP BY equipe_id) 
                           AND lida = 0");
    $stmt->execute([$usuario_id]);
    $total += (int)$stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM mensagens 
                           WHERE destinatario_id IS NULL AND equipe_id IS NULL AND lida = 0");
    $stmt->execute();
    $total += (int)$stmt->fetchColumn();
    
    echo json_encode(['total' => $total]);
    exit;
}

// ============================================================
// 6. ATUALIZAR ATIVIDADE
// ============================================================
if ($action === 'atualizar_atividade') {
    $stmt = $pdo->prepare("UPDATE usuarios SET last_activity = NOW() WHERE id = ?");
    $stmt->execute([$usuario_id]);
    echo json_encode(['success' => true]);
    exit;
}

// ============================================================
// 7. VERIFICAR NOVAS MENSAGENS (para notificações)
// ============================================================
if ($action === 'verificar_novas') {
    $ultimo_id = (int)($_GET['ultimo_id'] ?? 0);
    
    $sql = "SELECT m.id, m.remetente_id, m.mensagem, m.data_envio,
                   COALESCE(r.nome, SUBSTRING_INDEX(u.nome, ' ', 1)) as remetente_nome,
                   CASE 
                       WHEN m.destinatario_id IS NOT NULL THEN 'usuario'
                       WHEN m.equipe_id IS NOT NULL THEN 'equipe'
                       ELSE 'todos'
                   END as tipo,
                   COALESCE(m.destinatario_id, m.equipe_id, 'todos') as remetente_id
            FROM mensagens m
            LEFT JOIN usuarios u ON m.remetente_id = u.id
            LEFT JOIN responsaveis r ON u.id = r.usuario_id
            WHERE m.id > ? 
              AND m.lida = 0
              AND (
                  m.destinatario_id = ? 
                  OR m.equipe_id IN (SELECT equipe_id FROM processos WHERE responsavel_id = ? GROUP BY equipe_id)
                  OR (m.destinatario_id IS NULL AND m.equipe_id IS NULL)
              )
              AND m.remetente_id != ?
            ORDER BY m.data_envio ASC
            LIMIT 10";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$ultimo_id, $usuario_id, $usuario_id, $usuario_id]);
    $novas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Retorna apenas o nome do remetente e o id da mensagem, sem o conteúdo
    $resultado = [];
    foreach ($novas as $n) {
        $resultado[] = [
            'id' => $n['id'],
            'remetente_nome' => $n['remetente_nome'],
            'tipo' => $n['tipo'],
            'remetente_id' => $n['remetente_id']
        ];
    }
    
    echo json_encode($resultado);
    exit;
}

echo json_encode(['error' => 'Ação inválida']);