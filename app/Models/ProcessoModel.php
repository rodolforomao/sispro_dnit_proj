<?php
// app/Models/ProcessoModel.php

// 🔥 Definição da classe LogModel diretamente neste arquivo
class LogModel {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function registrar($usuario_id, $usuario_nome, $acao, $tabela, $registro_id = null, $dados_anteriores = null, $dados_novos = null) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $sql = "INSERT INTO logs_auditoria (usuario_id, usuario_nome, acao, tabela, registro_id, dados_anteriores, dados_novos, ip, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $usuario_id,
            $usuario_nome,
            $acao,
            $tabela,
            $registro_id,
            $dados_anteriores ? json_encode($dados_anteriores) : null,
            $dados_novos ? json_encode($dados_novos) : null,
            $ip,
            $user_agent
        ]);
    }
}

// Agora a classe ProcessoModel
class ProcessoModel {
    private $pdo;
    private $logModel;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->logModel = new LogModel($pdo);
    }

    /**
     * ✅ Retorna o nome do status (em MAIÚSCULO, sem espaços extras) ou '' se não achar
     */
    private function getNomeStatus($status_id) {
        if (empty($status_id)) return '';
        $stmt = $this->pdo->prepare("SELECT nome FROM status_processo WHERE id = ?");
        $stmt->execute([$status_id]);
        $nome = $stmt->fetchColumn();
        return $nome ? strtoupper(trim($nome)) : '';
    }

    /**
     * ✅ Limpa data_revisao / data_assinatura de acordo com o status
     */
    private function normalizarDatasCondicionais(&$dados) {
        $statusNome = $this->getNomeStatus($dados['status_id'] ?? null);

        if ($statusNome !== 'REVISADO') {
            $dados['data_revisao'] = null;
        }
        if ($statusNome !== 'ASSINADO') {
            $dados['data_assinatura'] = null;
        }
    }

    /**
     * ✅ Retorna a data de hoje com hora zerada (00:00:00).
     *    Usado em comparações de prazo para não contar como atrasado
     *    um processo que vence hoje.
     */
    private function hojeZerado() {
        $d = new DateTime();
        $d->setTime(0, 0, 0);
        return $d;
    }

    /**
     * ✅ Converte uma data (Y-m-d) em DateTime com hora zerada.
     */
    private function dataZerada($data) {
        $d = new DateTime($data);
        $d->setTime(0, 0, 0);
        return $d;
    }

    /**
     * Busca processos com paginação e filtros
     */
    public function getDadosHome($filtros, $pagina = 1, $porPagina = 20) {
        $offset = ($pagina - 1) * $porPagina;

        $where = "WHERE 1=1";
        $params = [];

        if (!empty($filtros['equipe'])) {
            $where .= " AND e.id = ?";
            $params[] = $filtros['equipe'];
        }
        if (!empty($filtros['responsavel'])) {
            $where .= " AND rp.id = ?";
            $params[] = $filtros['responsavel'];
        }
        if (!empty($filtros['contrato'])) {
            $where .= " AND r.id = ?";
            $params[] = $filtros['contrato'];
        }
        if (!empty($filtros['tipo'])) {
            $where .= " AND t.id = ?";
            $params[] = $filtros['tipo'];
        }
        if (!empty($filtros['uf'])) {
            if ($filtros['uf'] === 'GO/DF') {
                $where .= " AND p.uf IN ('GO','DF')";
            } else {
                $where .= " AND p.uf = ?";
                $params[] = $filtros['uf'];
            }
        }
        if (!empty($filtros['br'])) {
            $where .= " AND p.br = ?";
            $params[] = $filtros['br'];
        }
        if (!empty($filtros['processo'])) {
            $where .= " AND p.numero_processo LIKE ?";
            $params[] = "%" . $filtros['processo'] . "%";
        }

        $modoAssunto = !empty($filtros['assunto']);
        if ($modoAssunto) {
            $where .= " AND p.assunto LIKE ?";
            $params[] = "%" . $filtros['assunto'] . "%";
        } else {
            if (!empty($filtros['status'])) {
                $where .= " AND s.id = ?";
                $params[] = $filtros['status'];
            } else {
                $where .= " AND (s.nome NOT IN ('Assinado', 'Concluído') OR s.nome IS NULL)";
            }
        }

        $ordem = "p.prazo ASC";
        if (!empty($filtros['status'])) {
            $stmtStatus = $this->pdo->prepare("SELECT nome FROM status_processo WHERE id = ?");
            $stmtStatus->execute([$filtros['status']]);
            $nomeStatus = $stmtStatus->fetchColumn();
            if (in_array($nomeStatus, ['Assinado', 'Concluído'])) {
                $ordem = "p.prazo DESC";
            }
        }
        $ordem = "CASE WHEN p.prazo IS NULL THEN 1 ELSE 0 END, " . $ordem;

        // ✅ JOIN com contratos_rdci (fonte é a base RDCI)
        $sql = "SELECT p.*, 
                       r.instrumento AS contrato_num,
                       e.nome AS equipe_nome,
                       rp.nome AS responsavel_nome,
                       s.nome AS status_nome,
                       t.nome AS tipo_nome,
                       p.sei_criado_1
                FROM processos p
                LEFT JOIN contratos_rdci r ON p.contrato_id = r.id
                LEFT JOIN equipes e ON p.equipe_id = e.id
                LEFT JOIN responsaveis rp ON p.responsavel_id = rp.id
                LEFT JOIN status_processo s ON p.status_id = s.id
                LEFT JOIN tipos t ON p.tipo_id = t.id
                $where
                ORDER BY $ordem
                LIMIT " . intval($porPagina) . " OFFSET " . intval($offset);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $processos = $stmt->fetchAll();

        $sqlCount = "SELECT COUNT(*) 
                     FROM processos p
                     LEFT JOIN contratos_rdci r ON p.contrato_id = r.id
                     LEFT JOIN equipes e ON p.equipe_id = e.id
                     LEFT JOIN responsaveis rp ON p.responsavel_id = rp.id
                     LEFT JOIN status_processo s ON p.status_id = s.id
                     LEFT JOIN tipos t ON p.tipo_id = t.id
                     $where";
        $stmtCount = $this->pdo->prepare($sqlCount);
        $stmtCount->execute($params);
        $totalRegistros = (int) $stmtCount->fetchColumn();

        foreach ($processos as &$p) {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM comentarios WHERE processo_id = ?");
            $stmt->execute([$p['id']]);
            $p['total_comentarios'] = (int) $stmt->fetchColumn();
        }
        unset($p);

        // ============================================================
        // ✅ CONTADORES — corrigidos para não marcar "vence hoje" como atrasado
        // ============================================================
        $totalProcessos  = count($processos);
        $contarFazer     = 0;
        $contarAtrasados = 0;

        $hoje = $this->hojeZerado();   // ✅ 00:00:00

        foreach ($processos as $p) {
            // ---- A fazer ----
            if (($p['status_nome'] ?? '') == 'Em elaboração') {
                $contarFazer++;
            }

            // ---- Atrasados ----
            if (!empty($p['prazo']) && ($p['status_nome'] ?? '') != 'Assinado') {
                $prazo = $this->dataZerada($p['prazo']);   // ✅ 00:00:00
                if ($prazo < $hoje) {                      // só conta se for ANTES de hoje
                    $contarAtrasados++;
                }
            }
        }

        $equipes = $this->pdo->query("SELECT id, nome FROM equipes ORDER BY nome")->fetchAll();
        $responsaveis = $this->pdo->query("SELECT id, nome FROM responsaveis ORDER BY nome")->fetchAll();
        $statuses = $this->pdo->query("SELECT id, nome FROM status_processo ORDER BY nome")->fetchAll();

        // ✅ Contratos para filtro — DISTINCT por instrumento
        $contratos = $this->pdo->query("
            SELECT MIN(id) AS id, instrumento AS numero 
            FROM contratos_rdci 
            WHERE instrumento IS NOT NULL AND instrumento != '' 
            GROUP BY instrumento 
            ORDER BY instrumento
        ")->fetchAll();

        $tipos = $this->pdo->query("SELECT id, nome FROM tipos ORDER BY nome")->fetchAll();
        $ufs = $this->pdo->query("SELECT DISTINCT uf FROM processos ORDER BY uf")->fetchAll(PDO::FETCH_COLUMN);
        $brs = $this->pdo->query("SELECT DISTINCT br FROM processos ORDER BY br")->fetchAll(PDO::FETCH_COLUMN);

        return [
            'processos'       => $processos,
            'equipes'         => $equipes,
            'responsaveis'    => $responsaveis,
            'statuses'        => $statuses,
            'contratos'       => $contratos,
            'tipos'           => $tipos,
            'ufs'             => $ufs,
            'brs'             => $brs,
            'totalProcessos'  => $totalRegistros,
            'contarFazer'     => $contarFazer,
            'contarAtrasados' => $contarAtrasados,
            'pagina'          => $pagina,
            'porPagina'       => $porPagina,
            'totalRegistros'  => $totalRegistros
        ];
    }

    /**
     * ✅ FONTE: contratos_rdci (dataset 26)
     *    DISTINCT por instrumento (agrupa múltiplos subtrechos em uma linha só)
     */
    public function getTodosContratos() {
        $sql = "SELECT MIN(id)              AS id,
                       instrumento          AS numero,
                       MAX(uf)              AS uf,
                       MAX(br)              AS br,
                       MAX(nome_usual)      AS nome_usual
                FROM contratos_rdci
                WHERE instrumento IS NOT NULL
                  AND instrumento != ''
                GROUP BY instrumento
                ORDER BY instrumento";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private function getTodasBRs() {
        return [
            'BR-010', 'BR-020', 'BR-030', 'BR-040', 'BR-050', 'BR-060', 'BR-070', 'BR-080',
            'BR-101', 'BR-104', 'BR-110', 'BR-116', 'BR-120', 'BR-122', 'BR-135', 'BR-146',
            'BR-153', 'BR-154', 'BR-155', 'BR-156', 'BR-158', 'BR-163', 'BR-164', 'BR-174',
            'BR-210', 'BR-222', 'BR-226', 'BR-230', 'BR-232', 'BR-235', 'BR-242', 'BR-251',
            'BR-259', 'BR-262', 'BR-267', 'BR-272', 'BR-277', 'BR-280', 'BR-282', 'BR-285',
            'BR-290',
            'BR-304', 'BR-307', 'BR-316', 'BR-317', 'BR-319', 'BR-320', 'BR-324', 'BR-330',
            'BR-343', 'BR-349', 'BR-354', 'BR-356', 'BR-359', 'BR-364', 'BR-365', 'BR-367',
            'BR-369', 'BR-373', 'BR-376', 'BR-381', 'BR-383', 'BR-386', 'BR-392', 'BR-393',
            'BR-401', 'BR-402', 'BR-403', 'BR-404', 'BR-405', 'BR-406', 'BR-407', 'BR-408',
            'BR-409', 'BR-410', 'BR-411', 'BR-412', 'BR-413', 'BR-414', 'BR-415', 'BR-416',
            'BR-417', 'BR-418', 'BR-419', 'BR-420', 'BR-421', 'BR-422', 'BR-423', 'BR-424',
            'BR-425', 'BR-426', 'BR-427', 'BR-428', 'BR-429', 'BR-430', 'BR-431', 'BR-432',
            'BR-433', 'BR-434', 'BR-435', 'BR-436', 'BR-437', 'BR-438', 'BR-439', 'BR-440',
            'BR-441', 'BR-442', 'BR-443', 'BR-444', 'BR-445', 'BR-446', 'BR-447', 'BR-448',
            'BR-449', 'BR-450', 'BR-451', 'BR-452', 'BR-453', 'BR-454', 'BR-455', 'BR-456',
            'BR-457', 'BR-458', 'BR-459', 'BR-460', 'BR-461', 'BR-462', 'BR-463', 'BR-464',
            'BR-465', 'BR-466', 'BR-467', 'BR-468', 'BR-469', 'BR-470', 'BR-471', 'BR-472',
            'BR-473', 'BR-474', 'BR-475', 'BR-476', 'BR-477', 'BR-478', 'BR-479', 'BR-480',
            'BR-481', 'BR-482', 'BR-483', 'BR-484', 'BR-485', 'BR-486', 'BR-487', 'BR-488',
            'BR-489', 'BR-490', 'BR-491', 'BR-492', 'BR-493', 'BR-494', 'BR-495', 'BR-496',
            'BR-497', 'BR-498', 'BR-499'
        ];
    }

    /**
     * ✅ FONTE: contratos_rdci com DISTINCT por instrumento
     */
    public function getContratosPorUF($uf) {
        $where = '';
        $params = [];
        if (!empty($uf) && $uf !== '-') {
            if ($uf === 'GO/DF') {
                $where = "AND uf IN ('GO','DF')";
            } else {
                $where = "AND uf = ?";
                $params[] = $uf;
            }
        }

        $sql = "SELECT MIN(id)              AS id,
                       instrumento          AS numero,
                       MAX(uf)              AS uf,
                       MAX(br)              AS br,
                       MAX(nome_usual)      AS nome_usual
                FROM contratos_rdci
                WHERE instrumento IS NOT NULL
                  AND instrumento != ''
                  $where
                GROUP BY instrumento
                ORDER BY instrumento";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $contratos = $stmt->fetchAll();

        $brs = $this->getTodasBRs();

        return ['contratos' => $contratos, 'brs' => $brs];
    }

    public function getDadosFormulario() {
        $equipes = $this->pdo->query("SELECT id, nome FROM equipes ORDER BY nome")->fetchAll();
        usort($equipes, function($a, $b) {
            $ordem = ['PROJETO', 'ASSESSORIA COAC', 'ASSESSORIA CGCONT'];
            $posA = array_search($a['nome'], $ordem);
            $posB = array_search($b['nome'], $ordem);
            if ($posA === false) $posA = 999;
            if ($posB === false) $posB = 999;
            return $posA - $posB;
        });

        $responsaveis = $this->pdo->query("SELECT id, nome FROM responsaveis WHERE nome NOT LIKE '%Thânia%'")->fetchAll();
        $ordemResp = ['Bruno', 'Gabrielle', 'Maria Júlia', 'Rafaela', 'Rebeca'];
        usort($responsaveis, function($a, $b) use ($ordemResp) {
            $posA = array_search($a['nome'], $ordemResp);
            $posB = array_search($b['nome'], $ordemResp);
            if ($posA === false) $posA = 999;
            if ($posB === false) $posB = 999;
            return $posA - $posB;
        });

        $tipos = $this->pdo->query("SELECT id, nome FROM tipos ORDER BY id")->fetchAll();
        $statuses = $this->pdo->query("SELECT id, nome FROM status_processo ORDER BY id")->fetchAll();

        $uf_lista = [
            '-', 'AC','AL','AM','AP','BA','CE','ES','GO/DF',
            'MA','MG','MS','MT','PA','PB','PE','PI',
            'PR','RJ','RN','RO','RR','RS','SC','SE','SP','TO'
        ];

        return [
            'equipes' => $equipes,
            'responsaveis' => $responsaveis,
            'tipos' => $tipos,
            'statuses' => $statuses,
            'uf_lista' => $uf_lista
        ];
    }

    /**
     * Calcula o prazo com base no tipo e data de entrada
     */
    private function calcularPrazo($tipo_id, $data_entrada) {
        if (empty($data_entrada)) {
            return null;
        }

        $stmt = $this->pdo->prepare("SELECT nome FROM tipos WHERE id = ?");
        $stmt->execute([$tipo_id]);
        $tipoNome = $stmt->fetchColumn();

        $dias = 8;
        if (strtoupper($tipoNome) === 'PAAR') {
            $dias = 30;
        }

        $data = new DateTime($data_entrada);
        $data->modify("+$dias days");
        return $data->format('Y-m-d');
    }

    /**
     * Salva um novo processo, calculando o prazo se necessário
     */
    public function salvarProcesso($dados) {
        $numero_processo = trim($dados['numero_processo'] ?? '');
        if (empty($numero_processo)) {
            throw new Exception("Número do processo é obrigatório.");
        }

        $this->normalizarDatasCondicionais($dados);

        $prazo = !empty($dados['prazo']) ? $dados['prazo'] : $this->calcularPrazo($dados['tipo_id'] ?? null, $dados['data_entrada'] ?? null);

        $contrato_id = $dados['contrato_id'] ?? '';
        $uf = $dados['uf'] ?? '';
        $br = $dados['br'] ?? '';
        $contrato_id_db = ($contrato_id === '-' || $contrato_id === '') ? null : $contrato_id;

        // ✅ Busca UF e BR na contratos_rdci
        if (!empty($contrato_id_db)) {
            $stmt = $this->pdo->prepare("SELECT uf, br FROM contratos_rdci WHERE id = ?");
            $stmt->execute([$contrato_id_db]);
            $contrato = $stmt->fetch();
            if ($contrato) {
                $uf = $contrato['uf'];
                $br = $contrato['br'];
            }
        }

        $cadastrado_sima = isset($dados['cadastrado_sima']) && $dados['cadastrado_sima'] == 1 ? 1 : 0;

        // 🔥 LOG: inserir
        $usuario_id = $_SESSION['usuario_id'] ?? null;
        $usuario_nome = $_SESSION['usuario_nome'] ?? 'Sistema';
        if ($usuario_id) {
            $logDados = [
                'numero_processo' => $numero_processo,
                'assunto' => $dados['assunto'] ?? '',
                'equipe_id' => $dados['equipe_id'] ?? null,
                'responsavel_id' => $dados['responsavel_id'] ?? null,
                'status_id' => $dados['status_id'] ?? null,
                'tipo_id' => $dados['tipo_id'] ?? null,
                'data_entrada' => $dados['data_entrada'] ?? null,
                'prazo' => $prazo,
                'contrato_id' => $contrato_id_db,
                'uf' => $uf,
                'br' => $br,
                'cadastrado_sima' => $cadastrado_sima,
                'providencia' => $dados['providencia'] ?? '',
                'observacoes' => $dados['observacoes'] ?? '',
                'sei_recebido' => $dados['sei_recebido'] ?? '',
                'sei_criado_1' => $dados['sei_criado_1'] ?? '',
                'sei_criado_2' => $dados['sei_criado_2'] ?? '',
                'sei_criado_3' => $dados['sei_criado_3'] ?? '',
                'tem_prazo' => isset($dados['tem_prazo']) ? 1 : 0,
                'data_revisao' => $dados['data_revisao'] ?? null,
                'data_assinatura' => $dados['data_assinatura'] ?? null,
            ];
            $this->logModel->registrar(
                $usuario_id,
                $usuario_nome,
                'inserir',
                'processos',
                null,
                null,
                $logDados
            );
        }

        $sql = "INSERT INTO processos (contrato_id, numero_processo, assunto, equipe_id, responsavel_id, status_id, tipo_id, data_entrada, prazo, data_revisao, data_assinatura, sei_recebido, sei_criado_1, sei_criado_2, sei_criado_3, tem_prazo, cadastrado_sima, providencia, observacoes, uf, br, criado_por) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $contrato_id_db,
            $numero_processo,
            $dados['assunto'] ?? '',
            $dados['equipe_id'] ?: null,
            $dados['responsavel_id'] ?: null,
            $dados['status_id'] ?: null,
            $dados['tipo_id'] ?: null,
            $dados['data_entrada'],
            $prazo,
            $dados['data_revisao'] ?: null,
            $dados['data_assinatura'] ?: null,
            $dados['sei_recebido'] ?? '',
            $dados['sei_criado_1'] ?? '',
            $dados['sei_criado_2'] ?? '',
            $dados['sei_criado_3'] ?? '',
            isset($dados['tem_prazo']) ? 1 : 0,
            $cadastrado_sima,
            $dados['providencia'] ?? '',
            $dados['observacoes'] ?? '',
            $uf,
            $br,
            $_SESSION['usuario_id']
        ]);
    }

    /**
     * Atualiza um processo existente, calculando o prazo se necessário
     */
    public function atualizarProcesso($id, $dados) {
        $numero_processo = trim($dados['numero_processo'] ?? '');
        if (empty($numero_processo)) {
            throw new Exception("Número do processo é obrigatório.");
        }

        $dadosAntigos = $this->getProcessoPorId($id);
        if (!$dadosAntigos) {
            throw new Exception("Processo não encontrado.");
        }

        $this->normalizarDatasCondicionais($dados);

        $prazo = !empty($dados['prazo']) ? $dados['prazo'] : $this->calcularPrazo($dados['tipo_id'] ?? null, $dados['data_entrada'] ?? null);

        $contrato_id = $dados['contrato_id'] ?? '';
        $uf = $dados['uf'] ?? '';
        $br = $dados['br'] ?? '';
        $contrato_id_db = ($contrato_id === '-' || $contrato_id === '') ? null : $contrato_id;

        // ✅ Busca UF e BR na contratos_rdci
        if (!empty($contrato_id_db)) {
            $stmt = $this->pdo->prepare("SELECT uf, br FROM contratos_rdci WHERE id = ?");
            $stmt->execute([$contrato_id_db]);
            $contrato = $stmt->fetch();
            if ($contrato) {
                $uf = $contrato['uf'];
                $br = $contrato['br'];
            }
        }

        $cadastrado_sima = isset($dados['cadastrado_sima']) && $dados['cadastrado_sima'] == 1 ? 1 : 0;

        // 🔥 LOG: atualizar
        $usuario_id = $_SESSION['usuario_id'] ?? null;
        $usuario_nome = $_SESSION['usuario_nome'] ?? 'Sistema';
        if ($usuario_id) {
            $logDadosNovos = [
                'numero_processo' => $numero_processo,
                'assunto' => $dados['assunto'] ?? '',
                'equipe_id' => $dados['equipe_id'] ?? null,
                'responsavel_id' => $dados['responsavel_id'] ?? null,
                'status_id' => $dados['status_id'] ?? null,
                'tipo_id' => $dados['tipo_id'] ?? null,
                'data_entrada' => $dados['data_entrada'] ?? null,
                'prazo' => $prazo,
                'contrato_id' => $contrato_id_db,
                'uf' => $uf,
                'br' => $br,
                'cadastrado_sima' => $cadastrado_sima,
                'providencia' => $dados['providencia'] ?? '',
                'observacoes' => $dados['observacoes'] ?? '',
                'sei_recebido' => $dados['sei_recebido'] ?? '',
                'sei_criado_1' => $dados['sei_criado_1'] ?? '',
                'sei_criado_2' => $dados['sei_criado_2'] ?? '',
                'sei_criado_3' => $dados['sei_criado_3'] ?? '',
                'tem_prazo' => isset($dados['tem_prazo']) ? 1 : 0,
                'data_revisao' => $dados['data_revisao'] ?? null,
                'data_assinatura' => $dados['data_assinatura'] ?? null,
            ];
            $this->logModel->registrar(
                $usuario_id,
                $usuario_nome,
                'atualizar',
                'processos',
                $id,
                $dadosAntigos,
                $logDadosNovos
            );
        }

        $sql = "UPDATE processos SET contrato_id = ?, numero_processo = ?, assunto = ?, equipe_id = ?, responsavel_id = ?, status_id = ?, tipo_id = ?, data_entrada = ?, prazo = ?, data_revisao = ?, data_assinatura = ?, sei_recebido = ?, sei_criado_1 = ?, sei_criado_2 = ?, sei_criado_3 = ?, tem_prazo = ?, cadastrado_sima = ?, providencia = ?, observacoes = ?, uf = ?, br = ? WHERE id = ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $contrato_id_db,
            $numero_processo,
            $dados['assunto'] ?? '',
            $dados['equipe_id'] ?: null,
            $dados['responsavel_id'] ?: null,
            $dados['status_id'] ?: null,
            $dados['tipo_id'] ?: null,
            $dados['data_entrada'],
            $prazo,
            $dados['data_revisao'] ?: null,
            $dados['data_assinatura'] ?: null,
            $dados['sei_recebido'] ?? '',
            $dados['sei_criado_1'] ?? '',
            $dados['sei_criado_2'] ?? '',
            $dados['sei_criado_3'] ?? '',
            isset($dados['tem_prazo']) ? 1 : 0,
            $cadastrado_sima,
            $dados['providencia'] ?? '',
            $dados['observacoes'] ?? '',
            $uf,
            $br,
            $id
        ]);
    }

    public function getProcessoCompleto($id) {
        // ✅ JOIN com contratos_rdci
        $sql = "SELECT p.*, 
                       r.instrumento AS contrato_num, 
                       e.nome AS equipe_nome, 
                       rp.nome AS responsavel_nome, 
                       s.nome AS status_nome, 
                       t.nome AS tipo_nome 
                FROM processos p 
                LEFT JOIN contratos_rdci r ON p.contrato_id = r.id 
                LEFT JOIN equipes e ON p.equipe_id = e.id 
                LEFT JOIN responsaveis rp ON p.responsavel_id = rp.id 
                LEFT JOIN status_processo s ON p.status_id = s.id 
                LEFT JOIN tipos t ON p.tipo_id = t.id 
                WHERE p.id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function getProcessoPorId($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM processos WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function getComentarios($processoId) {
        $stmt = $this->pdo->prepare("SELECT c.*, u.nome as usuario_nome FROM comentarios c LEFT JOIN usuarios u ON c.usuario_id = u.id WHERE c.processo_id = ? ORDER BY c.created_at DESC");
        $stmt->execute([$processoId]);
        return $stmt->fetchAll();
    }

    public function excluirProcesso($id) {
        $check = $this->pdo->prepare("SELECT id FROM processos WHERE id = ?");
        $check->execute([$id]);
        if ($check->fetch()) {
            $usuario_id = $_SESSION['usuario_id'] ?? null;
            $usuario_nome = $_SESSION['usuario_nome'] ?? 'Sistema';
            if ($usuario_id) {
                $dados = $this->getProcessoPorId($id);
                $this->logModel->registrar(
                    $usuario_id,
                    $usuario_nome,
                    'excluir',
                    'processos',
                    $id,
                    $dados,
                    null
                );
            }
            $stmt = $this->pdo->prepare("DELETE FROM processos WHERE id = ?");
            $stmt->execute([$id]);
        }
    }

    /**
     * ✅ Retorna dados do contrato RDCI pelo ID (usado no modal Info Contrato)
     */
    public function getContratoInfo($contrato_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM contratos_rdci WHERE id = ?");
        $stmt->execute([$contrato_id]);
        return $stmt->fetch();
    }
}