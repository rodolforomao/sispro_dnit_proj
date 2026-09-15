<?php
// app/Models/ModeloModel.php

class ModeloModel {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Retorna todos os modelos de uma categoria específica
     */
    public function getModelosPorCategoria($categoria) {
        $stmt = $this->pdo->prepare("SELECT * FROM modelos WHERE categoria = ? ORDER BY data DESC, id DESC");
        $stmt->execute([$categoria]);
        return $stmt->fetchAll();
    }

    /**
     * Busca um modelo pelo ID (para edição)
     */
    public function getModeloPorId($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM modelos WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * Salva um novo modelo
     */
    public function salvarModelo($dados) {
        $categoria = $dados['categoria'] ?? '';
        $descricao = trim($dados['descricao'] ?? '');
        $sei = trim($dados['sei'] ?? '');
        $data = trim($dados['data'] ?? '');

        if (empty($categoria) || empty($descricao)) {
            throw new Exception("Categoria e Descrição são obrigatórios.");
        }

        $stmt = $this->pdo->prepare("INSERT INTO modelos (categoria, descricao, sei, data) VALUES (?, ?, ?, ?)");
        $stmt->execute([$categoria, $descricao, $sei ?: null, $data ?: null]);
        return $this->pdo->lastInsertId();
    }

    /**
     * Atualiza um modelo existente
     */
    public function atualizarModelo($id, $dados) {
        $categoria = $dados['categoria'] ?? '';
        $descricao = trim($dados['descricao'] ?? '');
        $sei = trim($dados['sei'] ?? '');
        $data = trim($dados['data'] ?? '');

        if (empty($categoria) || empty($descricao)) {
            throw new Exception("Categoria e Descrição são obrigatórios.");
        }

        $stmt = $this->pdo->prepare("UPDATE modelos SET categoria = ?, descricao = ?, sei = ?, data = ? WHERE id = ?");
        $stmt->execute([$categoria, $descricao, $sei ?: null, $data ?: null, $id]);
    }

    /**
     * Exclui um modelo
     */
    public function excluirModelo($id) {
        $stmt = $this->pdo->prepare("DELETE FROM modelos WHERE id = ?");
        $stmt->execute([$id]);
    }
}