<?php
// Model: obras_contratos

class ObrasContrato
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** Busca um contrato pelo ID. */
    public function buscarPorId(int $id): ?array
    {
        $sql = "SELECT * FROM obras_contratos WHERE id = :id LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Atualiza apenas status_acao/notificado/isento (controlados na tela). */
    public function atualizarAcao(int $id, string $statusAcao, int $notificado, int $isento): bool
    {
        $sql = "UPDATE obras_contratos
                   SET status_acao = :s, notificado = :n, isento = :i
                 WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':s'  => $statusAcao,
            ':n'  => $notificado,
            ':i'  => $isento,
            ':id' => $id,
        ]);
    }

    /**
     * Lista contratos aplicando filtros e já trazendo o total de comentários
     * em UMA única consulta (evita N+1).
     */
    public function listarComFiltros(array $filtros): array
    {
        $sql = "SELECT c.*,
                       (SELECT COUNT(*) FROM obras_comentarios cm WHERE cm.contrato_id = c.id) AS total_comentarios
                  FROM obras_contratos c
                 WHERE 1=1";
        $params = [];

        if (!empty($filtros['uf'])) {
            $sql .= " AND c.uf = :uf";
            $params[':uf'] = $filtros['uf'];
        }
        if (!empty($filtros['tipo_contrato'])) {
            $sql .= " AND c.tipo_contrato = :tipo";
            $params[':tipo'] = $filtros['tipo_contrato'];
        }
        if (!empty($filtros['status_acao'])) {
            $sql .= " AND c.status_acao = :sacao";
            $params[':sacao'] = $filtros['status_acao'];
        }
        if (!empty($filtros['instrumento'])) {
            $sql .= " AND c.instrumento = :instr";
            $params[':instr'] = $filtros['instrumento'];
        }
        if (!empty($filtros['busca'])) {
            $sql .= " AND (c.instrumento LIKE :b
                        OR c.nome_usual  LIKE :b
                        OR c.empresa     LIKE :b)";
            $params[':b'] = '%' . $filtros['busca'] . '%';
        }

        // Ordenação básica; o agrupamento por categoria de cronograma é feito no Service
        $sql .= " ORDER BY c.data_termino_cronograma IS NULL, c.data_termino_cronograma ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Lista apenas UF/instrumento distintos para popular Select2 (opcional). */
    public function listarUFs(): array
    {
        $stmt = $this->pdo->query("SELECT DISTINCT uf FROM obras_contratos WHERE uf IS NOT NULL AND uf <> '' ORDER BY uf");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function listarInstrumentos(): array
    {
        $stmt = $this->pdo->query("SELECT instrumento FROM obras_contratos ORDER BY instrumento");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}