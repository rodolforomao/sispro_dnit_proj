<?php
// app/Controllers/RdciController.php

class RdciController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    private function paraMaiusculo($texto) {
        if ($texto === null) return '';
        $texto = (string)$texto;
        $map = [
            'á'=>'Á','à'=>'À','ã'=>'Ã','â'=>'Â','ä'=>'Ä',
            'é'=>'É','è'=>'È','ê'=>'Ê','ë'=>'Ë',
            'í'=>'Í','ì'=>'Ì','î'=>'Î','ï'=>'Ï',
            'ó'=>'Ó','ò'=>'Ò','õ'=>'Õ','ô'=>'Ô','ö'=>'Ö',
            'ú'=>'Ú','ù'=>'Ù','û'=>'Û','ü'=>'Ü',
            'ç'=>'Ç','ñ'=>'Ñ',
        ];
        return strtoupper(strtr($texto, $map));
    }

    private function normalizarSemAcento($texto) {
        if ($texto === null) return '';
        $texto = strtolower((string)$texto);
        $map = [
            'á'=>'a','à'=>'a','ã'=>'a','â'=>'a','ä'=>'a',
            'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
            'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
            'ó'=>'o','ò'=>'o','õ'=>'o','ô'=>'o','ö'=>'o',
            'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u',
            'ç'=>'c','ñ'=>'n',
        ];
        return strtoupper(strtr($texto, $map));
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

        if (!empty($filtro_instrumento)) { $where .= " AND instrumento LIKE ?"; $params[] = "%$filtro_instrumento%"; }
        if (!empty($filtro_uf))          { $where .= " AND uf = ?";             $params[] = $filtro_uf; }
        if (!empty($filtro_br))          { $where .= " AND br = ?";             $params[] = $filtro_br; }
        if (!empty($filtro_status_geral)) { $where .= " AND status_geral = ?";  $params[] = $filtro_status_geral; }
        if (!empty($filtro_situacao_cronograma)) { $where .= " AND situacao_cronograma = ?"; $params[] = $filtro_situacao_cronograma; }
        if (!empty($filtro_empresa))     { $where .= " AND empresa LIKE ?";     $params[] = "%$filtro_empresa%"; }

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM contratos_rdci $where");
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

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

        $filtroUF         = $_GET['uf']         ?? '';
        $filtroBR         = $_GET['br']         ?? '';
        $filtroBusca      = $_GET['busca']      ?? '';
        $filtroNotificado = $_GET['notificado'] ?? '';
        $filtroIsento     = $_GET['isento']     ?? '';

        $where  = "WHERE (situacao_projeto IS NULL OR situacao_projeto != 'CONCLUÍDO')";
        $params = [];

        if (!empty($filtroUF))    { $where .= " AND uf = ?"; $params[] = $filtroUF; }
        if (!empty($filtroBR))    { $where .= " AND br = ?"; $params[] = $filtroBR; }
        if (!empty($filtroBusca)) {
            $where .= " AND (instrumento LIKE ? OR nome_usual LIKE ? OR empresa LIKE ? OR subtrecho LIKE ?)";
            $params[] = "%$filtroBusca%";
            $params[] = "%$filtroBusca%";
            $params[] = "%$filtroBusca%";
            $params[] = "%$filtroBusca%";
        }
        if ($filtroNotificado === 'sim')      { $where .= " AND notificado = 1"; }
        elseif ($filtroNotificado === 'nao')  { $where .= " AND (notificado IS NULL OR notificado = 0)"; }
        if ($filtroIsento === 'sim')          { $where .= " AND isento_notificacao = 1"; }
        elseif ($filtroIsento === 'nao')      { $where .= " AND (isento_notificacao IS NULL OR isento_notificacao = 0)"; }

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

        $ufSql = "SELECT DISTINCT uf FROM contratos_rdci 
                  WHERE (situacao_projeto IS NULL OR situacao_projeto != 'CONCLUÍDO') 
                    AND uf IS NOT NULL AND uf != '' 
                  ORDER BY uf";
        $ufs = $this->pdo->query($ufSql)->fetchAll(PDO::FETCH_COLUMN);

        $brs = [];
        $statusCronogramaList = [];

        require_once APP_PATH . '/Views/notificacoes_rdci.php';
    }

    // ============================================================
    // PÁGINA DE ATUALIZAÇÃO DE CONTRATOS (SUPRA)
    // ✅ Agora conta LINHAS e CONTRATOS (padrão da página Notificações)
    // ============================================================
    public function atualizacoes() {
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: login.php');
            exit;
        }

        $usuario_nome  = $_SESSION['usuario_nome']  ?? 'Usuário';
        $usuario_nivel = $_SESSION['usuario_nivel'] ?? 'usuario';

        $filtroUF       = $_GET['uf']              ?? '';
        $filtroBR       = $_GET['br']              ?? '';
        $filtroContrato = $_GET['contrato']        ?? '';
        $filtroSituacao = $_GET['situacao_projeto']?? '';
        $filtroBusca    = $_GET['busca']           ?? '';

        $where  = "WHERE 1=1";
        $params = [];

        if (!empty($filtroUF))       { $where .= " AND uf = ?";        $params[] = $filtroUF; }
        if (!empty($filtroBR))       { $where .= " AND br = ?";        $params[] = $filtroBR; }
        if (!empty($filtroContrato)) { $where .= " AND instrumento = ?"; $params[] = $filtroContrato; }
        if (!empty($filtroSituacao)) { $where .= " AND situacao_projeto = ?"; $params[] = $filtroSituacao; }
        if (!empty($filtroBusca)) {
            $where .= " AND (instrumento LIKE ? OR nome_usual LIKE ? OR empresa LIKE ? OR subtrecho LIKE ?)";
            $params[] = "%$filtroBusca%";
            $params[] = "%$filtroBusca%";
            $params[] = "%$filtroBusca%";
            $params[] = "%$filtroBusca%";
        }

        $sql = "SELECT id, instrumento, br, uf, lote, nome_usual, subtrecho,
                       processo_base, processo_projeto, situacao_projeto
                FROM contratos_rdci
                $where
                ORDER BY uf ASC, instrumento ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $contratos = $stmt->fetchAll();

        // ============================================================
        // ✅ CONTADORES — linhas totais e contratos únicos
        // ============================================================
        $totalLinhas = count($contratos);

        $contratosUnicos = [];
        foreach ($contratos as $c) {
            $contratosUnicos[$c['instrumento']] = true;
        }
        $totalContratos = count($contratosUnicos);
        $contratosList  = array_keys($contratosUnicos);
        sort($contratosList);

        // Opções para os selects
        $ufs = $this->pdo->query("SELECT DISTINCT uf FROM contratos_rdci WHERE uf IS NOT NULL AND uf != '' ORDER BY uf")->fetchAll(PDO::FETCH_COLUMN);
        $brs = $this->pdo->query("SELECT DISTINCT br FROM contratos_rdci WHERE br IS NOT NULL AND br != '' ORDER BY br")->fetchAll(PDO::FETCH_COLUMN);
        $situacoesProjeto = $this->pdo->query("SELECT DISTINCT situacao_projeto FROM contratos_rdci WHERE situacao_projeto IS NOT NULL AND situacao_projeto != '' ORDER BY situacao_projeto")->fetchAll(PDO::FETCH_COLUMN);

        require_once APP_PATH . '/Views/atualizacao_contratos_rdci.php';
    }

    // ============================================================
    // COMPARATIVO — Base RDCI (26) × Lista Contratos Atlas (36)
    // ============================================================
    public function comparativo() {
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: login.php');
            exit;
        }

        $usuario_nome  = $_SESSION['usuario_nome']  ?? 'Usuário';
        $usuario_nivel = $_SESSION['usuario_nivel'] ?? 'usuario';

        $filtroUF    = $_GET['uf']    ?? '';
        $filtroBR    = $_GET['br']    ?? '';
        $filtroBusca = $_GET['busca'] ?? '';

        // Normalização de instrumento (colapsa "00 00608/2024" → "608/2024")
        $normalizar = function($inst) {
            $inst = strtoupper(trim((string)$inst));
            $inst = preg_replace('/\s+/', ' ', $inst);
            if (preg_match('/^\d+\s+0*(\d+\/\d+)$/', $inst, $m)) return $m[1];
            if (preg_match('/^0+(\d+\/\d+)$/', $inst, $m))       return $m[1];
            return $inst;
        };

        // --- Lado A: contratos_rdci ---
        $sqlA = "SELECT id, instrumento, uf, br, lote, nome_usual, subtrecho,
                        empresa, situacao_projeto, data_termino_vigencia
                 FROM contratos_rdci
                 WHERE instrumento IS NOT NULL AND instrumento != ''";
        $paramsA = [];
        if (!empty($filtroUF)) { $sqlA .= " AND uf = ?"; $paramsA[] = $filtroUF; }
        if (!empty($filtroBR)) { $sqlA .= " AND br = ?"; $paramsA[] = $filtroBR; }
        if (!empty($filtroBusca)) {
            $sqlA .= " AND (instrumento LIKE ? OR nome_usual LIKE ? OR empresa LIKE ? OR subtrecho LIKE ?)";
            for ($i = 0; $i < 4; $i++) $paramsA[] = "%$filtroBusca%";
        }
        $sqlA .= " ORDER BY uf ASC, instrumento ASC, subtrecho ASC";
        $stmtA = $this->pdo->prepare($sqlA);
        $stmtA->execute($paramsA);
        $contratosRdciBruto = $stmtA->fetchAll(PDO::FETCH_ASSOC);

        $totalLinhasRdci = count($contratosRdciBruto);

        $rdciUnicos = [];
        foreach ($contratosRdciBruto as $c) {
            $chave = $normalizar($c['instrumento']);
            if (!isset($rdciUnicos[$chave])) $rdciUnicos[$chave] = $c;
        }
        $contratosRdci = array_values($rdciUnicos);
        $totalRdci     = count($contratosRdci);

        // --- Lado B: supra_dataset_36_todos ---
        $sqlB = "SELECT instrumento, uf, br, lote, nome_usual, tipo_contratacao,
                        tipo_execucao, nome_empreendimento_governa,
                        municipios_governa, id_pac, empresa_construtora,
                        data_inicio_vigencia, data_termino_vigencia
                 FROM supra_dataset_36_todos
                 WHERE instrumento IS NOT NULL AND instrumento != ''";
        $paramsB = [];
        if (!empty($filtroUF)) { $sqlB .= " AND uf = ?"; $paramsB[] = $filtroUF; }
        if (!empty($filtroBR)) { $sqlB .= " AND br = ?"; $paramsB[] = $filtroBR; }
        $sqlB .= " ORDER BY uf ASC, instrumento ASC";
        $stmtB = $this->pdo->prepare($sqlB);
        $stmtB->execute($paramsB);
        $base36Bruto = $stmtB->fetchAll(PDO::FETCH_ASSOC);

        $totalLinhasBase36 = count($base36Bruto);

        $base36Unicos = [];
        foreach ($base36Bruto as $b) {
            $chave = $normalizar($b['instrumento']);
            if (!isset($base36Unicos[$chave])) $base36Unicos[$chave] = $b;
        }
        $base36    = array_values($base36Unicos);
        $totalBase = count($base36);

        // Índices
        $index36 = [];
        foreach ($base36 as $b) {
            $chave = $normalizar($b['instrumento']);
            if (!isset($index36[$chave])) $index36[$chave] = $b;
        }

        $chavesRdciUnicas = [];
        foreach ($contratosRdci as $c) $chavesRdciUnicas[$normalizar($c['instrumento'])] = true;

        $chavesBase36Unicas = [];
        foreach ($base36 as $b) $chavesBase36Unicas[$normalizar($b['instrumento'])] = true;

        // Validação
        $validacao = [];
        foreach ($contratosRdci as $c) {
            $chave = $normalizar($c['instrumento']);
            $presente = isset($index36[$chave]);
            $validacao[] = [
                'contrato'    => $c,
                'presente_36' => $presente,
                'dados_36'    => $presente ? $index36[$chave] : null,
            ];
        }

        // Candidatos
        $tiposContratacaoPermitidos = ['DIRETA', 'PREV'];
        $tiposExecucaoBloqueados    = ['OBRA DELEGADA', 'SOMENTE EXECUCAO'];

        $candidatos = [];
        foreach ($base36 as $b) {
            $chave = $normalizar($b['instrumento']);
            if (isset($chavesRdciUnicas[$chave])) continue;

            $tipoContr = $this->normalizarSemAcento($b['tipo_contratacao'] ?? '');
            if (!in_array($tipoContr, $tiposContratacaoPermitidos, true)) continue;

            $tipoExec = $this->normalizarSemAcento($b['tipo_execucao'] ?? '');
            $bloqueado = false;
            foreach ($tiposExecucaoBloqueados as $palavra) {
                if (strpos($tipoExec, $palavra) !== false) { $bloqueado = true; break; }
            }
            if ($bloqueado) continue;

            $candidatos[] = $b;
        }
        $totalCandidatos = count($candidatos);

        // Totais por UF
        $totaisUf = [];
        foreach ($contratosRdci as $c) {
            $uf = !empty($c['uf']) ? $c['uf'] : '—';
            if (!isset($totaisUf[$uf])) $totaisUf[$uf] = ['rdci' => 0, 'base' => 0];
            $totaisUf[$uf]['rdci']++;
        }
        foreach ($base36 as $b) {
            $uf = !empty($b['uf']) ? $b['uf'] : '—';
            if (!isset($totaisUf[$uf])) $totaisUf[$uf] = ['rdci' => 0, 'base' => 0];
            $totaisUf[$uf]['base']++;
        }
        ksort($totaisUf);

        // Totais por BR
        $totaisBr = [];
        foreach ($contratosRdci as $c) {
            $br = !empty($c['br']) ? $c['br'] : '—';
            if (!isset($totaisBr[$br])) $totaisBr[$br] = ['rdci' => 0, 'base' => 0];
            $totaisBr[$br]['rdci']++;
        }
        foreach ($base36 as $b) {
            $br = !empty($b['br']) ? $b['br'] : '—';
            if (!isset($totaisBr[$br])) $totaisBr[$br] = ['rdci' => 0, 'base' => 0];
            $totaisBr[$br]['base']++;
        }
        uasort($totaisBr, function($a, $b) { return $b['base'] - $a['base']; });

        // KPIs
        $presentes = 0;
        foreach ($chavesRdciUnicas as $chave => $_) {
            if (isset($chavesBase36Unicas[$chave])) $presentes++;
        }
        $ausentesRdci = $totalRdci - $presentes;

        $ufs = $this->pdo->query("SELECT DISTINCT uf FROM contratos_rdci WHERE uf IS NOT NULL AND uf != '' ORDER BY uf")->fetchAll(PDO::FETCH_COLUMN);
        $brs = $this->pdo->query("SELECT DISTINCT br FROM contratos_rdci WHERE br IS NOT NULL AND br != '' ORDER BY br")->fetchAll(PDO::FETCH_COLUMN);

        require_once APP_PATH . '/Views/comparativo_rdci_base36.php';
    }

    // ============================================================
    // DASHBOARD DE PROJETOS
    // ============================================================
    public function dashboardProjetos() {
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: login.php');
            exit;
        }

        $usuario_nome  = $_SESSION['usuario_nome']  ?? 'Usuário';
        $usuario_nivel = $_SESSION['usuario_nivel'] ?? 'usuario';

        $filtros = [
            'regiao'    => $_GET['regiao']    ?? '',
            'uf'        => $_GET['uf']        ?? '',
            'br'        => $_GET['br']        ?? '',
            'contrato'  => $_GET['contrato']  ?? '',
            'subtrecho' => $_GET['subtrecho'] ?? '',
        ];

        $regioes       = $this->dashOpcoesDistintas('regiao', []);
        $ufs           = $this->dashOpcoesDistintas('uf',     ['regiao' => $filtros['regiao']]);
        $brs           = $this->dashOpcoesDistintas('br',     ['regiao' => $filtros['regiao'], 'uf' => $filtros['uf']]);
        $contratosList = $this->dashOpcoesDistintas('instrumento',
            ['regiao' => $filtros['regiao'], 'uf' => $filtros['uf'], 'br' => $filtros['br']]);
        $subtrechos    = $this->dashOpcoesDistintas('subtrecho',
            ['regiao' => $filtros['regiao'], 'uf' => $filtros['uf'], 'br' => $filtros['br'], 'contrato' => $filtros['contrato']]);

        $qtdContratos  = $this->dashContarContratos($filtros);
        $contratos     = $this->dashBuscarContratos($filtros);
        $contratoAtual = !empty($contratos) ? $contratos[0] : null;

        $kpisPortfolio = $this->dashKpisPortfolio($filtros);

        require_once APP_PATH . '/Views/dashboard_projetos.php';
    }

    // ============================================================
    // Helpers internos do Dashboard
    // ============================================================
    private function dashBuildWhere(array $filtros, array &$params) {
        $w = [];
        if (!empty($filtros['regiao']))    { $w[] = 'regiao = ?';      $params[] = $filtros['regiao']; }
        if (!empty($filtros['uf']))        { $w[] = 'uf = ?';          $params[] = $filtros['uf']; }
        if (!empty($filtros['br']))        { $w[] = 'br = ?';          $params[] = $filtros['br']; }
        if (!empty($filtros['contrato']))  { $w[] = 'instrumento = ?'; $params[] = $filtros['contrato']; }
        if (!empty($filtros['subtrecho'])) { $w[] = 'subtrecho = ?';   $params[] = $filtros['subtrecho']; }
        return $w ? ('WHERE ' . implode(' AND ', $w)) : '';
    }

    private function dashOpcoesDistintas($coluna, array $filtros = []) {
        $permitidas = ['regiao','uf','br','instrumento','subtrecho'];
        if (!in_array($coluna, $permitidas, true)) {
            throw new Exception("Coluna não permitida: $coluna");
        }
        $params = [];
        $where  = $this->dashBuildWhere($filtros, $params);
        $sql    = "SELECT DISTINCT `$coluna` FROM contratos_rdci
                   " . ($where ? $where . ' AND' : 'WHERE') . "
                     `$coluna` IS NOT NULL AND TRIM(`$coluna`) <> ''
                   ORDER BY `$coluna`";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function dashContarContratos(array $filtros) {
        $params = [];
        $where  = $this->dashBuildWhere($filtros, $params);
        $stmt   = $this->pdo->prepare("SELECT COUNT(DISTINCT instrumento) FROM contratos_rdci $where");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    private function dashBuscarContratos(array $filtros) {
        $params = [];
        $where  = $this->dashBuildWhere($filtros, $params);
        $stmt   = $this->pdo->prepare("SELECT * FROM contratos_rdci $where ORDER BY uf, br, instrumento, subtrecho");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function dashKpisPortfolio(array $filtros) {
        $paramsBase = [];
        $whereBase  = $this->dashBuildWhere($filtros, $paramsBase);
        $whereSql   = $whereBase ? ($whereBase . ' AND 1=1') : 'WHERE 1=1';

        // MySQL 5.5 não tem ROW_NUMBER/PARTITION BY. O último trecho do contrato é o maior id.
        $ultimo = "SELECT instrumento, MAX(id) AS max_id
                   FROM contratos_rdci
                   $whereSql
                   GROUP BY instrumento";

        $sql = "SELECT c.situacao_projeto AS rotulo, COUNT(*) AS qtd
                FROM contratos_rdci c
                INNER JOIN ($ultimo) ult ON c.id = ult.max_id
                WHERE c.situacao_projeto IS NOT NULL AND TRIM(c.situacao_projeto) <> ''
                GROUP BY c.situacao_projeto";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($paramsBase);
        $situacao = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sql2 = "SELECT c.situacao_cronograma AS rotulo, COUNT(*) AS qtd
                 FROM contratos_rdci c
                 INNER JOIN ($ultimo) ult ON c.id = ult.max_id
                 WHERE c.situacao_cronograma IS NOT NULL AND TRIM(c.situacao_cronograma) <> ''
                   AND (c.situacao_projeto IS NULL OR UPPER(c.situacao_projeto) <> 'CONCLUÍDO')
                 GROUP BY c.situacao_cronograma";
        $stmt = $this->pdo->prepare($sql2);
        $stmt->execute($paramsBase);
        $cronograma = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sql3 = "SELECT COUNT(*)
                 FROM contratos_rdci c
                 INNER JOIN ($ultimo) ult ON c.id = ult.max_id
                 WHERE c.situacao_paar = 'Aberto'";
        $stmt = $this->pdo->prepare($sql3);
        $stmt->execute($paramsBase);
        $paarTotal = (int)$stmt->fetchColumn();

        $sql4 = "SELECT c.instrumento, c.uf, c.br, c.nome_usual, c.situacao_paar
                 FROM contratos_rdci c
                 INNER JOIN ($ultimo) ult ON c.id = ult.max_id
                 WHERE c.situacao_paar = 'Aberto'
                 ORDER BY c.uf, c.br";
        $stmt = $this->pdo->prepare($sql4);
        $stmt->execute($paramsBase);
        $paarLista = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'situacao'   => $situacao,
            'cronograma' => $cronograma,
            'paarTotal'  => $paarTotal,
            'paarLista'  => $paarLista,
        ];
    }
}