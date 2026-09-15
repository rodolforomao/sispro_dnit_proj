<?php
// app/Models/ComentarioModel.php

class ComentarioModel {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getComentariosPorProcesso($processoId) {
        $stmt = $this->pdo->prepare("SELECT c.*, u.nome as usuario_nome FROM comentarios c LEFT JOIN usuarios u ON c.usuario_id = u.id WHERE c.processo_id = ? ORDER BY c.created_at DESC");
        $stmt->execute([$processoId]);
        return $stmt->fetchAll();
    }

    public function adicionarComentario($processoId, $usuarioId, $comentario) {
        $stmt = $this->pdo->prepare("INSERT INTO comentarios (processo_id, usuario_id, comentario) VALUES (?, ?, ?)");
        return $stmt->execute([$processoId, $usuarioId, $comentario]);
    }

    public function editarComentario($id, $comentario) {
        $stmt = $this->pdo->prepare("UPDATE comentarios SET comentario = ? WHERE id = ?");
        return $stmt->execute([$comentario, $id]);
    }

    public function excluirComentario($id) {
        $stmt = $this->pdo->prepare("DELETE FROM comentarios WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function contarComentarios($processoId) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM comentarios WHERE processo_id = ?");
        $stmt->execute([$processoId]);
        return $stmt->fetchColumn();
    }
}