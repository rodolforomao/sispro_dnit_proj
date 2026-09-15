<?php
// app/Controllers/RdciController.php

class RdciController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // ============================================================
    // PÁGINA PRINCIPAL RDCI
    // ============================================================
    public function index() {
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: login.php');
            exit;
        }

        $usuario_nome  = $_SESSION['usuario_nome']  ?? 'Usuário';
        $usuario_nivel = $_SESSION['usuario_nivel'] ?? 'usuario';

        // Filtros
        $filtroUF         = $_GET['uf']         ?? '';
        $filtroBR         = $_GET['br']         ?? '';
        $filtroStatus     = $_GET['status']     ?? '';
        $filtroCronograma = $_GET['cronograma'] ?? '';
        $filtroBusca      = $_GET['busca']      ?? '';

        $where  = "WHERE 1=1";
        $params = [];

        if (!empty($filtroUF)) {
            $where .= " AND uf = ?";
            $params[] = $filtroUF;
        }
        if (!empty($filtroBR)) {
            $where .= " AND br = ?";
            $params[] = $filtroBR;
        }
        if (!empty($filtroStatus)) {
            $where .= " AND status_geral = ?";
            $params[] = $filtroStatus;
        }
        if (!empty($filtroCronograma)) {
            $where .= " AND situacao_cronograma = ?";
            $params[] = $filtroCronograma;
        }
        if (!empty($filtroBusca)) {
            $where .= " AND (instrumento LIKE ? OR nome_usual LIKE ? OR empresa LIKE ?)";
            $params[] = "%$filtroBusca%";
            $params[] = "%$filtroBusca%";
            $params[] = "%$filtroBusca%";
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

        // Dados para filtros (distintos)
        $ufs = $this->pdo->query("SELECT DISTINCT uf FROM contratos_rdci WHERE uf IS NOT NULL AND uf != '' ORDER BY uf")->fetchAll(PDO::FETCH_COLUMN);
        $brs = $this->pdo->query("SELECT DISTINCT br FROM contratos_rdci WHERE br IS NOT NULL AND br != '' ORDER BY br")->fetchAll(PDO::FETCH_COLUMN);
        $statusGerais = $this->pdo->query("SELECT DISTINCT status_geral FROM contratos_rdci WHERE status_geral IS NOT NULL AND status_geral != '' ORDER BY status_geral")->fetchAll(PDO::FETCH_COLUMN);
        $situacoesCronograma = $this->pdo->query("SELECT DISTINCT situacao_cronograma FROM contratos_rdci WHERE situacao_cronograma IS NOT NULL AND situacao_cronograma != '' ORDER BY situacao_cronograma")->fetchAll(PDO::FETCH_COLUMN);

        require_once APP_PATH . '/Views/rdci.php';
    }

    // ============================================================
    // PÁGINA DE CONTRATOS RDCI (com paginação)
    // ============================================================
    public function contratos() {
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: login.php');
            exit;
        }

        $usuario_nome  = $_SESSION['usuario_nome']  ?? 'Usuário';
        $usuario_nivel = $_SESSION['usuario_nivel'] ?? 'usuario';

        // Filtros
        $filtro_instrumento         = $_GET['instrumento']         ?? '';
        $filtro_uf                  = $_GET['uf']                  ?? '';
        $filtro_br                  = $_GET['br']                  ?? '';
        $filtro_status_geral        = $_GET['status_geral']        ?? '';
        $filtro_situacao_cronograma = $_GET['situacao_cronograma'] ?? '';
        $filtro_empresa             = $_GET['empresa']             ?? '';
        $limit  = (int)($_GET['limit']  ?? 50);
        $offset = (int)($_GET['offset'] ?? 0);

        $where  = "WHERE 1=1";
        $params = [];

        if (!empty($filtro_instrumento)) {
            $where .= " AND instrumento LIKE ?";
            $params[] = "%$filtro_instrumento%";
        }
        if (!empty($filtro_uf)) {
            $where .= " AND uf = ?";
            $params[] = $filtro_uf;
        }
        if (!empty($filtro_br)) {
            $where .= " AND br = ?";
            $params[] = $filtro_br;
        }
        if (!empty($filtro_status_geral)) {
            $where .= " AND status_geral = ?";
            $params[] = $filtro_status_geral;
        }
        if (!empty($filtro_situacao_cronograma)) {
            $where .= " AND situacao_cronograma = ?";
            $params[] = $filtro_situacao_cronograma;
        }
        if (!empty($filtro_empresa)) {
            $where .= " AND empresa LIKE ?";
            $params[] = "%$filtro_empresa%";
        }

        // Contar total
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM contratos_rdci $where");
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        // Buscar dados paginados
        $sql = "SELECT id, instrumento, uf, br, regiao, lote, nome_usual, empresa, supervisora, 
                       fase, situacao_contrato_siac, situacao_projeto, 
                       data_termino_vigencia, data_termino_servico,
                       status_geral, situacao_cronograma, status_cronograma_atual,
                       projeto_basico_finalizado, obra_iniciada, 
                       valor_pi_a_r, valor_projetos, data_atualizacao
                FROM contratos_rdci $where
                ORDER BY instrumento ASC
                LIMIT ? OFFSET ?";

        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $registros = $stmt->fetchAll();

        // Dados para filtros
        $ufs = $this->pdo->query("SELECT DISTINCT uf FROM contratos_rdci WHERE uf IS NOT NULL AND uf != '' ORDER BY uf")->fetchAll(PDO::FETCH_COLUMN);
        $brs = $this->pdo->query("SELECT DISTINCT br FROM contratos_rdci WHERE br IS NOT NULL AND br != '' ORDER BY br")->fetchAll(PDO::FETCH_COLUMN);
        $statusGerais = $this->pdo->query("SELECT DISTINCT status_geral FROM contratos_rdci WHERE status_geral IS NOT NULL AND status_geral != '' ORDER BY status_geral")->fetchAll(PDO::FETCH_COLUMN);
        $situacoesCronograma = $this->pdo->query("SELECT DISTINCT situacao_cronograma FROM contratos_rdci WHERE situacao_cronograma IS NOT NULL AND situacao_cronograma != '' ORDER BY situacao_cronograma")->fetchAll(PDO::FETCH_COLUMN);

        $totalPaginas = ceil($total / $limit);
        $paginaAtual  = floor($offset / $limit) + 1;

        require_once APP_PATH . '/Views/contratos_rdci.php';
    }

    // ============================================================
    // PÁGINA DE NOTIFICAÇÕES RDCI
    // ============================================================
    public function notificacoes() {
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: login.php');
            exit;
        }

        $usuario_nome  = $_SESSION['usuario_nome']  ?? 'Usuário';
        $usuario_nivel = $_SESSION['usuario_nivel'] ?? 'usuario';
        $usuario_id    = $_SESSION['usuario_id']    ?? 0;

        // Captura filtros da URL
        $filtroUF         = $_GET['uf']         ?? '';
        $filtroBR         = $_GET['br']         ?? '';
        $filtroBusca      = $_GET['busca']      ?? '';
        $filtroNotificado = $_GET['notificado'] ?? '';
        $filtroIsento     = $_GET['isento']     ?? '';

        // Monta a consulta base: exclui projetos concluídos
        $where  = "WHERE (situacao_projeto IS NULL OR situacao_projeto != 'CONCLUÍDO')";
        $params = [];

        if (!empty($filtroUF)) {
            $where .= " AND uf = ?";
            $params[] = $filtroUF;
        }
        if (!empty($filtroBR)) {
            $where .= " AND br = ?";
            $params[] = $filtroBR;
        }
        if (!empty($filtroBusca)) {
            $where .= " AND (instrumento LIKE ? OR nome_usual LIKE ? OR empresa LIKE ? OR subtrecho LIKE ?)";
            $params[] = "%$filtroBusca%";
            $params[] = "%$filtroBusca%";
            $params[] = "%$filtroBusca%";
            $params[] = "%$filtroBusca%";
        }
        if ($filtroNotificado === 'sim') {
            $where .= " AND notificado = 1";
        } elseif ($filtroNotificado === 'nao') {
            $where .= " AND (notificado IS NULL OR notificado = 0)";
        }
        if ($filtroIsento === 'sim') {
            $where .= " AND isento_notificacao = 1";
        } elseif ($filtroIsento === 'nao') {
            $where .= " AND (isento_notificacao IS NULL OR isento_notificacao = 0)";
        }

        // ✅ status_acao incluído no SELECT
        $sql = "SELECT id, instrumento, uf, br, lote, nome_usual, subtrecho, empresa,
                       data_termino_projeto_cronog, situacao_cronograma, status_cronograma_atual,
                       cronograma_sei, justificativa_cronograma, n_sei_oficio_cobranca_cronograma, paar,
                       status_geral, notificado, data_ultima_notificacao, isento_notificacao,
                       status_acao
                FROM contratos_rdci
                $where
                ORDER BY uf ASC, instrumento ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $contratos = $stmt->fetchAll();

        // Lista de UFs disponíveis (considerando o filtro de situação_projeto)
        $ufSql = "SELECT DISTINCT uf FROM contratos_rdci 
                  WHERE (situacao_projeto IS NULL OR situacao_projeto != 'CONCLUÍDO') 
                    AND uf IS NOT NULL AND uf != '' 
                  ORDER BY uf";
        $ufs = $this->pdo->query($ufSql)->fetchAll(PDO::FETCH_COLUMN);

        // Para compatibilidade com a view
        $brs = [];
        $statusCronogramaList = [];

        require_once APP_PATH . '/Views/notificacoes_rdci.php';
    }
}