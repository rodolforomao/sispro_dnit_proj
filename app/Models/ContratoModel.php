<?php
// app/Models/ContratoModel.php

class ContratoModel {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // Métodos para RDCI
    public function getRdciContratos($filtros) {
        // Monta WHERE com $filtros
        $where = "WHERE 1=1";
        $params = [];
        // ... (copie a lógica do rdci.php original)
        $sql = "SELECT id, instrumento, uf, br, regiao, nome_usual, empresa, situacao_contrato_siac, 
                       fase, data_ordem_inicio_obra, data_termino_vigencia, status_geral, 
                       situacao_cronograma, status_cronograma_atual, obra_iniciada, projeto_basico_finalizado
                FROM contratos_rdci $where ORDER BY instrumento ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getUFsRdci() {
        return $this->pdo->query("SELECT DISTINCT uf FROM contratos_rdci WHERE uf IS NOT NULL AND uf != '' ORDER BY uf")->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getBRsRdci() {
        return $this->pdo->query("SELECT DISTINCT br FROM contratos_rdci WHERE br IS NOT NULL AND br != '' ORDER BY br")->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getStatusGeraisRdci() {
        return $this->pdo->query("SELECT DISTINCT status_geral FROM contratos_rdci WHERE status_geral IS NOT NULL AND status_geral != '' ORDER BY status_geral")->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getSituacoesCronogramaRdci() {
        return $this->pdo->query("SELECT DISTINCT situacao_cronograma FROM contratos_rdci WHERE situacao_cronograma IS NOT NULL AND situacao_cronograma != '' ORDER BY situacao_cronograma")->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getContratosRdci($filtros) {
        // Similar ao contratos_rdci.php original com paginação
        // Retorna ['registros' => ..., 'total' => ..., 'ufs' => ..., 'brs' => ..., etc.]
        // Para economizar, você pode adaptar o código original.
    }

    public function getNotificacoesRdci($filtros) {
        // Similar ao notificacoes_rdci.php original
    }
}