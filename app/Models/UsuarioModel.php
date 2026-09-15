<?php
// app/Models/UsuarioModel.php

class UsuarioModel {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getPendentes() {
        $stmt = $this->pdo->query("SELECT * FROM usuarios WHERE status = 'pendente' ORDER BY created_at ASC");
        return $stmt->fetchAll();
    }

    public function getTodosUsuarios() {
        $stmt = $this->pdo->query("SELECT id, nome, email, nivel, status, equipe_id, created_at FROM usuarios ORDER BY nome ASC");
        return $stmt->fetchAll();
    }

    public function processarAcaoAdmin($post) {
        $id = $post['id'] ?? 0;
        $action = $post['action'] ?? '';

        if ($action === 'aprovar') {
            $stmt = $this->pdo->prepare("UPDATE usuarios SET status = 'ativo' WHERE id = ?");
            $stmt->execute([$id]);
        } elseif ($action === 'reprovar') {
            $stmt = $this->pdo->prepare("DELETE FROM usuarios WHERE id = ?");
            $stmt->execute([$id]);
        } elseif ($action === 'editar') {
            $nome = trim($post['nome'] ?? '');
            $email = trim($post['email'] ?? '');
            $nivel = $post['nivel'] ?? 'usuario';
            $status = $post['status'] ?? 'ativo';
            $senha = trim($post['senha'] ?? '');
            $setores = isset($post['setores']) ? (array)$post['setores'] : [];
            $equipe_id = !empty($post['equipe_id']) ? (int)$post['equipe_id'] : null;

            if (!empty($nome) && !empty($email)) {
                $sql = "UPDATE usuarios SET nome = ?, email = ?, nivel = ?, status = ?, equipe_id = ?";
                $params = [$nome, $email, $nivel, $status, $equipe_id];
                if (!empty($senha)) {
                    $sql .= ", senha = ?";
                    $params[] = password_hash($senha, PASSWORD_DEFAULT);
                }
                $sql .= " WHERE id = ?";
                $params[] = $id;
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
                $this->atualizarSetores($id, $setores);
            }
        } elseif ($action === 'criar') {
            $nome = trim($post['nome'] ?? '');
            $email = trim($post['email'] ?? '');
            $senha = trim($post['senha'] ?? '');
            $nivel = $post['nivel'] ?? 'usuario';
            $status = $post['status'] ?? 'ativo';
            $setores = isset($post['setores']) ? (array)$post['setores'] : [];
            $equipe_id = !empty($post['equipe_id']) ? (int)$post['equipe_id'] : null;

            if (!empty($nome) && !empty($email) && !empty($senha) && !$this->existeEmail($email)) {
                $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
                $stmt = $this->pdo->prepare("INSERT INTO usuarios (nome, email, senha, nivel, status, equipe_id) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$nome, $email, $senhaHash, $nivel, $status, $equipe_id]);
                $usuario_id = $this->pdo->lastInsertId();
                $this->atualizarSetores($usuario_id, $setores);
            }
        } elseif ($action === 'excluir') {
            $stmt = $this->pdo->prepare("DELETE FROM usuarios WHERE id = ?");
            $stmt->execute([$id]);
        }
    }

    private function atualizarSetores($usuario_id, $setores) {
        $stmt = $this->pdo->prepare("DELETE FROM usuario_setor WHERE usuario_id = ?");
        $stmt->execute([$usuario_id]);
        foreach ($setores as $setor_id) {
            if (!empty($setor_id)) {
                $stmt = $this->pdo->prepare("INSERT INTO usuario_setor (usuario_id, setor_id) VALUES (?, ?)");
                $stmt->execute([$usuario_id, $setor_id]);
            }
        }
    }

    /**
     * Autenticação híbrida: suporta senhas em password_hash e MD5 (legado)
     */
    public function autenticar($email, $senha) {
        $stmt = $this->pdo->prepare("SELECT * FROM usuarios WHERE email = ? AND status = 'ativo'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) return null;

        // Verifica com password_verify (novo padrão)
        if (password_verify($senha, $user['senha'])) {
            return $user;
        }

        // Fallback: verifica com MD5 (legado)
        if (md5($senha) === $user['senha']) {
            // Migra para password_hash automaticamente
            $this->atualizarSenha($user['id'], $senha);
            // Recarrega o usuário com a nova senha (opcional, apenas para retornar)
            $stmt = $this->pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
            $stmt->execute([$user['id']]);
            return $stmt->fetch();
        }

        return null;
    }

    public function existeEmail($email) {
        $stmt = $this->pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch() !== false;
    }

    public function criarSolicitacao($nome, $email, $senha) {
        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("INSERT INTO usuarios (email, senha, nome, nivel, status) VALUES (?, ?, ?, 'usuario', 'pendente')");
        return $stmt->execute([$email, $senha_hash, $nome]);
    }

    /**
     * Verifica a senha atual (híbrido: password_verify ou MD5)
     */
    public function verificarSenhaAtual($usuario_id, $senhaAtual) {
        $stmt = $this->pdo->prepare("SELECT senha FROM usuarios WHERE id = ?");
        $stmt->execute([$usuario_id]);
        $user = $stmt->fetch();
        if (!$user) return false;

        // Tenta com password_verify
        if (password_verify($senhaAtual, $user['senha'])) {
            return true;
        }

        // Fallback MD5
        if (md5($senhaAtual) === $user['senha']) {
            // Migra para password_hash
            $this->atualizarSenha($usuario_id, $senhaAtual);
            return true;
        }

        return false;
    }

    public function atualizarSenha($usuario_id, $novaSenha) {
        $senhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
        return $stmt->execute([$senhaHash, $usuario_id]);
    }

    public function gerarTokenRecuperacao($email) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $stmt = $this->pdo->prepare("UPDATE usuarios SET reset_token = ?, reset_expires = ? WHERE email = ?");
        $stmt->execute([$token, $expires, $email]);
        return $token;
    }

    public function validarTokenRecuperacao($token) {
        $stmt = $this->pdo->prepare("SELECT email FROM usuarios WHERE reset_token = ? AND reset_expires > NOW() AND status = 'ativo'");
        $stmt->execute([$token]);
        return $stmt->fetchColumn();
    }

    public function atualizarSenhaPorEmail($email, $novaSenha) {
        $senhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("UPDATE usuarios SET senha = ?, reset_token = NULL, reset_expires = NULL WHERE email = ?");
        return $stmt->execute([$senhaHash, $email]);
    }
}