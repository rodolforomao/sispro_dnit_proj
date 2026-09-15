<?php
// app/Models/RdciModel.php

class RdciModel {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getContratosRdci($filtros) {
        $where = "WHERE 1=1";
        $params = [];

        if (!empty($filtros['uf'])) {
            $where .= " AND uf = ?";
            $params[] = $filtros['uf'];
        }
        if (!empty($filtros['br'])) {
            $where .= " AND br = ?";
            $params[] = $filtros['br'];
        }
        if (!empty($filtros['status'])) {
            $where .= " AND status_geral = ?";
            $params[] = $filtros['status'];
        }
        if (!empty($filtros['cronograma'])) {
            $where .= " AND situacao_cronograma = ?";
            $params[] = $filtros['cronograma'];
        }
        if (!empty($filtros['busca'])) {
            $where .= " AND (instrumento LIKE ? OR nome_usual LIKE ? OR empresa LIKE ?)";
            $params[] = "%" . $filtros['busca'] . "%";
            $params[] = "%" . $filtros['busca'] . "%";
            $params[] = "%" . $filtros['busca'] . "%";
        }

        $sql = "SELECT id, instrumento, uf, br, regiao, nome_usual, empresa, situacao_contrato_siac, 
                       fase, data_ordem_inicio_obra, data_termino_vigencia, status_geral, 
                       situacao_cronograma, status_cronograma_atual, obra_iniciada, projeto_basico_finalizado
                FROM contratos_rdci
                $where
                ORDER BY instrumento ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $contratos = $stmt->fetchAll();

        // Dados para filtros
        $ufs = $this->pdo->query("SELECT DISTINCT uf FROM contratos_rdci WHERE uf IS NOT NULL AND uf != '' ORDER BY uf")->fetchAll(PDO::FETCH_COLUMN);
        $brs = $this->pdo->query("SELECT DISTINCT br FROM contratos_rdci WHERE br IS NOT NULL AND br != '' ORDER BY br")->fetchAll(PDO::FETCH_COLUMN);
        $statusGerais = $this->pdo->query("SELECT DISTINCT status_geral FROM contratos_rdci WHERE status_geral IS NOT NULL AND status_geral != '' ORDER BY status_geral")->fetchAll(PDO::FETCH_COLUMN);
        $situacoesCronograma = $this->pdo->query("SELECT DISTINCT situacao_cronograma FROM contratos_rdci WHERE situacao_cronograma IS NOT NULL AND situacao_cronograma != '' ORDER BY situacao_cronograma")->fetchAll(PDO::FETCH_COLUMN);

        return compact('contratos', 'ufs', 'brs', 'statusGerais', 'situacoesCronograma');
    }

    public function getContratosRdciDetalhados($filtros) {
        $where = "WHERE 1=1";
        $params = [];

        if (!empty($filtros['instrumento'])) {
            $where .= " AND instrumento LIKE ?";
            $params[] = "%" . $filtros['instrumento'] . "%";
        }
        if (!empty($filtros['uf'])) {
            $where .= " AND uf = ?";
            $params[] = $filtros['uf'];
        }
        if (!empty($filtros['br'])) {
            $where .= " AND br = ?";
            $params[] = $filtros['br'];
        }
        if (!empty($filtros['status_geral'])) {
            $where .= " AND status_geral = ?";
            $params[] = $filtros['status_geral'];
        }
        if (!empty($filtros['situacao_cronograma'])) {
            $where .= " AND situacao_cronograma = ?";
            $params[] = $filtros['situacao_cronograma'];
        }
        if (!empty($filtros['empresa'])) {
            $where .= " AND empresa LIKE ?";
            $params[] = "%" . $filtros['empresa'] . "%";
        }

        // Contar total
        $countSql = "SELECT COUNT(*) FROM contratos_rdci $where";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = $countStmt->fetchColumn();

        // Dados com paginação
        $sql = "SELECT id, instrumento, uf, br, regiao, lote, nome_usual, empresa, supervisora, 
                       fase, situacao_contrato_siac, situacao_projeto, 
                       data_termino_vigencia, data_termino_servico,
                       status_geral, situacao_cronograma, status_cronograma_atual,
                       projeto_basico_finalizado, obra_iniciada, 
                       valor_pi_a_r, valor_projetos, data_atualizacao
                FROM contratos_rdci $where
                ORDER BY instrumento ASC
                LIMIT ? OFFSET ?";
        $params[] = $filtros['limit'];
        $params[] = $filtros['offset'];
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $registros = $stmt->fetchAll();

        // Dados para filtros (distintos)
        $ufs = $this->pdo->query("SELECT DISTINCT uf FROM contratos_rdci WHERE uf IS NOT NULL AND uf != '' ORDER BY uf")->fetchAll(PDO::FETCH_COLUMN);
        $brs = $this->pdo->query("SELECT DISTINCT br FROM contratos_rdci WHERE br IS NOT NULL AND br != '' ORDER BY br")->fetchAll(PDO::FETCH_COLUMN);
        $statusGerais = $this->pdo->query("SELECT DISTINCT status_geral FROM contratos_rdci WHERE status_geral IS NOT NULL AND status_geral != '' ORDER BY status_geral")->fetchAll(PDO::FETCH_COLUMN);
        $situacoesCronograma = $this->pdo->query("SELECT DISTINCT situacao_cronograma FROM contratos_rdci WHERE situacao_cronograma IS NOT NULL AND situacao_cronograma != '' ORDER BY situacao_cronograma")->fetchAll(PDO::FETCH_COLUMN);

        $totalPaginas = ceil($total / $filtros['limit']);
        $paginaAtual = floor($filtros['offset'] / $filtros['limit']) + 1;

        return compact('registros', 'ufs', 'brs', 'statusGerais', 'situacoesCronograma', 'total', 'totalPaginas', 'paginaAtual', 'filtros');
    }

    public function getNotificacoesRdci($filtros) {
        $where = "WHERE 1=1 AND (situacao_projeto IS NULL OR situacao_projeto != 'CONCLUÍDO')";
        $params = [];

        if (!empty($filtros['uf'])) {
            $where .= " AND uf = ?";
            $params[] = $filtros['uf'];
        }
        if (!empty($filtros['br'])) {
            $where .= " AND br = ?";
            $params[] = $filtros['br'];
        }
        if (!empty($filtros['status_cronograma'])) {
            $where .= " AND situacao_cronograma = ?";
            $params[] = $filtros['status_cronograma'];
        }
        if (!empty($filtros['busca'])) {
            $where .= " AND (instrumento LIKE ? OR nome_usual LIKE ? OR empresa LIKE ? OR subtrecho LIKE ?)";
            $params[] = "%" . $filtros['busca'] . "%";
            $params[] = "%" . $filtros['busca'] . "%";
            $params[] = "%" . $filtros['busca'] . "%";
            $params[] = "%" . $filtros['busca'] . "%";
        }
        if ($filtros['vencido'] === 'sim') {
            $where .= " AND data_termino_projeto_cronog < CURDATE()";
        } elseif ($filtros['vencido'] === 'nao') {
            $where .= " AND (data_termino_projeto_cronog >= CURDATE() OR data_termino_projeto_cronog IS NULL)";
        }
        if ($filtros['notificado'] === 'sim') {
            $where .= " AND notificado = 1";
        } elseif ($filtros['notificado'] === 'nao') {
            $where .= " AND (notificado IS NULL OR notificado = 0)";
        }

        $sql = "SELECT id, instrumento, uf, br, lote, nome_usual, subtrecho, empresa,
                       data_termino_projeto_cronog, situacao_cronograma, status_cronograma_atual,
                       cronograma_sei, justificativa_cronograma, n_sei_oficio_cobranca_cronograma, paar,
                       status_geral, notificado
                FROM contratos_rdci
                $where
                ORDER BY uf ASC, instrumento ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $contratos = $stmt->fetchAll();

        // Dados para filtros
        $ufs = $this->pdo->query("SELECT DISTINCT uf FROM contratos_rdci WHERE uf IS NOT NULL AND uf != '' ORDER BY uf")->fetchAll(PDO::FETCH_COLUMN);
        $brs = $this->pdo->query("SELECT DISTINCT br FROM contratos_rdci WHERE br IS NOT NULL AND br != '' ORDER BY br")->fetchAll(PDO::FETCH_COLUMN);
        $statusCronogramaList = $this->pdo->query("SELECT DISTINCT situacao_cronograma FROM contratos_rdci WHERE situacao_cronograma IS NOT NULL AND situacao_cronograma != '' ORDER BY situacao_cronograma")->fetchAll(PDO::FETCH_COLUMN);

        return compact('contratos', 'ufs', 'brs', 'statusCronogramaList');
    }
}