<?php
/**
 * Controller único do módulo Notificações de Obras.
 * Fonte: tabela supra_dataset_36_todos (Dataset 36 - ATLAS).
 */

class NotificacoesObrasController
{
    const CICLO_NOTIFICACAO_MESES = 3;
    const JANELA_ALERTA_DIAS      = 15;
    const JANELA_A_VENCER_DIAS    = 30;
    const PERCENTUAL_ALERTA       = 30;

    const ACOES_PERMITIDAS = [
        'notificar', 'notificado', 'aguardando_assinatura', 'isento', 'nao_notificar'
    ];

    const EFEITO_ACAO = [
        'notificar'             => ['notificado' => 0, 'isento' => 0],
        'notificado'            => ['notificado' => 1, 'isento' => 0],
        'aguardando_assinatura' => ['notificado' => 1, 'isento' => 0],
        'isento'                => ['notificado' => 0, 'isento' => 1],
        'nao_notificar'         => ['notificado' => 0, 'isento' => 0],
    ];

    private $cacheContratos = null;

    public function __construct()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        if (!isset($_SESSION['obras_overrides'])) {
            $_SESSION['obras_overrides'] = ['acoes' => [], 'prox_comentario' => 1];
        }
        if (!isset($_SESSION['obras_comentarios'])) {
            $_SESSION['obras_comentarios'] = [];
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
        }
    }

    /* =================== PÁGINA PRINCIPAL =================== */
    public function index()
    {
        $this->exigirLogin();

        $filtros = [
            'uf'                 => $_GET['uf']                 ?? '',
            'tipo_contrato'      => $_GET['tipo_contrato']      ?? '',
            'status_cronograma'  => $_GET['status_cronograma']  ?? '',
            'status_notificacao' => $_GET['status_notificacao'] ?? '',
            'acao'               => $_GET['acao']               ?? '',
            'contrato'           => $_GET['contrato']           ?? '',
            'busca'              => $_GET['busca']              ?? '',
        ];

        $todos = $this->todosContratos();

        // Enriquece com status
        foreach ($todos as &$c) {
            $c['_status_crono'] = $this->calcStatusCronograma(
                $c['sei_cronograma_vigente'] ?? null,
                $c['data_termino_servicos_cronograma'] ?? null
            );
            $c['_status_notif'] = $this->calcStatusNotificacao($c);
        }
        unset($c);

        // Opções dos selects — sobre o conjunto completo
        $ufs = $instrumentos = [];
        $tipos = [];
        foreach ($todos as $c) {
            if (!empty($c['uf']))               $ufs[$c['uf']]                   = true;
            if (!empty($c['instrumento']))      $instrumentos[$c['instrumento']] = true;
            if (!empty($c['tipo_contrato']))    $tipos[$c['tipo_contrato']]      = true;
        }
        $ufs = array_keys($ufs); sort($ufs);
        $instrumentos = array_keys($instrumentos); sort($instrumentos);
        $tipos = array_keys($tipos); sort($tipos);

        // Filtros
        $contratos = array_values(array_filter($todos, function ($c) use ($filtros) {
            if ($filtros['uf'] !== ''            && ($c['uf'] ?? '')            !== $filtros['uf'])            return false;
            if ($filtros['tipo_contrato'] !== '' && ($c['tipo_contrato'] ?? '') !== $filtros['tipo_contrato']) return false;
            if ($filtros['acao'] !== ''          && ($c['status_acao'] ?? '')   !== $filtros['acao'])          return false;
            if ($filtros['contrato'] !== ''      && ($c['instrumento'] ?? '')   !== $filtros['contrato'])      return false;
            if ($filtros['busca'] !== '') {
                $b   = mb_strtolower($filtros['busca']);
                $hay = mb_strtolower(
                    ($c['instrumento'] ?? '') . ' ' .
                    ($c['nome_usual']  ?? '') . ' ' .
                    ($c['empresa']     ?? '') . ' ' .
                    ($c['br']          ?? '') . ' ' .
                    ($c['uf']          ?? '')
                );
                if (mb_strpos($hay, $b) === false) return false;
            }
            return true;
        }));

        if ($filtros['status_cronograma'] !== '') {
            $contratos = array_values(array_filter($contratos,
                fn($x) => $x['_status_crono']['categoria'] === $filtros['status_cronograma']));
        }
        if ($filtros['status_notificacao'] !== '') {
            $contratos = array_values(array_filter($contratos,
                fn($x) => $x['_status_notif']['categoria'] === $filtros['status_notificacao']));
        }

        // ⚠️ Sem usort — mantém a ordem do banco (id ASC)

        $total = count($contratos);
        $csrf  = $_SESSION['csrf_token'];

        require APP_PATH . '/Views/notificacoes_obras.php';
    }

    /* =================== AÇÃO =================== */
    public function atualizarAcao()
    {
        $this->exigirLogin();
        header('Content-Type: application/json; charset=utf-8');
        try {
            $id   = (int)($_POST['id']   ?? 0);
            $acao = (string)($_POST['acao'] ?? '');
            if ($id <= 0 || !in_array($acao, self::ACOES_PERMITIDAS, true)) {
                throw new InvalidArgumentException('Dados inválidos.');
            }
            $flags = self::EFEITO_ACAO[$acao];
            $_SESSION['obras_overrides']['acoes'][$id] = [
                'status_acao' => $acao,
                'notificado'  => $flags['notificado'],
                'isento'      => $flags['isento'],
            ];
            echo json_encode(['success' => true, 'data' => ['id' => $id, 'acao' => $acao] + $flags]);
        } catch (Throwable $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /* =================== IMPORTAR (informativo) =================== */
    public function importar()
    {
        $this->exigirLogin();
        header('Content-Type: application/json; charset=utf-8');
        $this->cacheContratos = null;
        echo json_encode(['success' => true, 'data' => [
            'fonte'        => 'supra_dataset_36_todos',
            'registros'    => count($this->todosContratos()),
            'importado_em' => date('Y-m-d H:i:s'),
        ]]);
    }

    /* =================== COMENTÁRIOS =================== */
    public function listarComentarios()
    {
        $this->exigirLogin();
        header('Content-Type: application/json; charset=utf-8');
        $id = (int)($_GET['contrato_id'] ?? 0);
        echo json_encode(['success' => true, 'data' => $_SESSION['obras_comentarios'][$id] ?? []]);
    }

    public function adicionarComentario()
    {
        $this->exigirLogin();
        header('Content-Type: application/json; charset=utf-8');
        try {
            $cid   = (int)($_POST['contrato_id'] ?? 0);
            $texto = trim((string)($_POST['comentario'] ?? ''));
            if ($cid <= 0 || $texto === '') throw new InvalidArgumentException('Comentário vazio.');
            $novoId = (int)$_SESSION['obras_overrides']['prox_comentario']++;
            $_SESSION['obras_comentarios'][$cid][] = [
                'id'           => $novoId,
                'usuario_id'   => $_SESSION['usuario_id']   ?? null,
                'usuario_nome' => $_SESSION['usuario_nome'] ?? 'Desconhecido',
                'comentario'   => $texto,
                'created_at'   => date('Y-m-d H:i:s'),
            ];
            echo json_encode(['success' => true, 'data' => ['id' => $novoId]]);
        } catch (Throwable $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function editarComentario()
    {
        $this->exigirLogin();
        header('Content-Type: application/json; charset=utf-8');
        try {
            $id    = (int)($_POST['id'] ?? 0);
            $texto = trim((string)($_POST['comentario'] ?? ''));
            foreach ($_SESSION['obras_comentarios'] as $cid => $lista) {
                foreach ($lista as $i => $c) {
                    if ((int)$c['id'] === $id) {
                        $nivel   = $_SESSION['usuario_nivel'] ?? 'usuario';
                        $ehAdmin = in_array($nivel, ['desenvolvedor','admin'], true);
                        $ehAutor = (int)($c['usuario_id'] ?? 0) === (int)($_SESSION['usuario_id'] ?? 0);
                        if (!$ehAdmin && !$ehAutor) throw new RuntimeException('Sem permissão.');
                        $_SESSION['obras_comentarios'][$cid][$i]['comentario'] = $texto;
                        echo json_encode(['success' => true]);
                        return;
                    }
                }
            }
            throw new RuntimeException('Comentário não encontrado.');
        } catch (Throwable $e) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function excluirComentario()
    {
        $this->exigirLogin();
        header('Content-Type: application/json; charset=utf-8');
        try {
            $nivel = $_SESSION['usuario_nivel'] ?? 'usuario';
            if (!in_array($nivel, ['desenvolvedor','admin'], true)) throw new RuntimeException('Sem permissão.');
            $id = (int)($_POST['id'] ?? 0);
            foreach ($_SESSION['obras_comentarios'] as $cid => $lista) {
                foreach ($lista as $i => $c) {
                    if ((int)$c['id'] === $id) {
                        unset($_SESSION['obras_comentarios'][$cid][$i]);
                        $_SESSION['obras_comentarios'][$cid] = array_values($_SESSION['obras_comentarios'][$cid]);
                        echo json_encode(['success' => true]);
                        return;
                    }
                }
            }
            throw new RuntimeException('Comentário não encontrado.');
        } catch (Throwable $e) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /* =================== DETALHES =================== */
    public function detalhes()
    {
        $this->exigirLogin();
        header('Content-Type: application/json; charset=utf-8');
        $id = (int)($_GET['id'] ?? 0);
        $c  = $this->buscarContrato($id);
        if (!$c) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Contrato não encontrado.']);
            return;
        }
        $c['_status_crono'] = $this->calcStatusCronograma(
            $c['sei_cronograma_vigente'] ?? null,
            $c['data_termino_servicos_cronograma'] ?? null
        );
        $c['_status_notif'] = $this->calcStatusNotificacao($c);
        $c['comentarios']   = $_SESSION['obras_comentarios'][$id] ?? [];

        // Garante que os valores vão para o front
        $c['valor_previsto']  = $c['valor_previsto']  ?? null;
        $c['valor_executado'] = $c['valor_executado'] ?? null;

        echo json_encode(['success' => true, 'data' => $c]);
    }

    /* =================== EXPORTAR =================== */
    public function exportar()
    {
        $this->exigirLogin();

        $contratos = $this->todosContratos();
        foreach ($contratos as &$c) {
            $c['_status_crono'] = $this->calcStatusCronograma(
                $c['sei_cronograma_vigente'] ?? null,
                $c['data_termino_servicos_cronograma'] ?? null
            );
            $c['_status_notif'] = $this->calcStatusNotificacao($c);
        }
        unset($c);

        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="Relatorio_Notificacoes_Obras_' . date('d-m-Y') . '.xls"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

        echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
        echo '<head><meta charset="UTF-8">';
        echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Relatório</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
        echo '<style>';
        echo 'th { background-color: #004a8f; color: #ffffff; font-weight: bold; padding: 5px; border: 1px solid #000; }';
        echo 'td { padding: 4px 6px; border: 1px solid #000; }';
        echo '.titulo { font-size: 14pt; font-weight: bold; }';
        echo '.texto { mso-number-format:\@; }';
        echo '</style></head><body>';
        echo '<div class="titulo">Relatório de Notificações - Obras</div>';
        echo '<div>Gerado em: ' . date('d/m/Y H:i:s') . ' - Total de contratos: ' . count($contratos) . '</div>';
        echo '<table>';
        echo '<tr><th>Contrato</th><th>Tipo</th><th>BR</th><th>UF</th><th>Lote</th>';
        echo '<th>Nome Usual</th><th>Empresa</th><th>Data Término Cronog.</th>';
        echo '<th>Status Cronograma</th><th>SEI Cronog.</th><th>Data Últ. Notif.</th>';
        echo '<th>Status Notificação</th><th>Ofício Notif.</th><th>Ação</th><th>Isento</th></tr>';

        foreach ($contratos as $c) {
            $dataTerm = !empty($c['data_termino_servicos_cronograma'])
                ? date('d/m/Y', strtotime($c['data_termino_servicos_cronograma'])) : '-';
            $dataUlt  = !empty($c['data_ultima_notificacao_cronograma'])
                ? date('d/m/Y', strtotime($c['data_ultima_notificacao_cronograma'])) : '-';
            $isento   = (int)($c['isento'] ?? 0);

            echo '<tr>';
            echo '<td class="texto">' . $h($c['instrumento'] ?? '') . '</td>';
            echo '<td>' . $h($c['tipo_contrato'] ?? '') . '</td>';
            echo '<td>' . $h($c['br'] ?? '') . '</td>';
            echo '<td>' . $h($c['uf'] ?? '') . '</td>';
            echo '<td>' . $h($c['lote'] ?? '') . '</td>';
            echo '<td>' . $h($c['nome_usual'] ?? '') . '</td>';
            echo '<td>' . $h($c['empresa'] ?? '') . '</td>';
            echo '<td>' . $h($dataTerm) . '</td>';
            echo '<td>' . $h($c['_status_crono']['texto']) . '</td>';
            echo '<td>' . $h($c['sei_cronograma_vigente'] ?? '') . '</td>';
            echo '<td>' . $h($dataUlt) . '</td>';
            echo '<td>' . $h($c['_status_notif']['texto']) . '</td>';
            echo '<td>' . $h($c['ultimo_oficio_notificacao_cronograma'] ?? '') . '</td>';
            echo '<td>' . $h($c['status_acao'] ?? '') . '</td>';
            echo '<td>' . $h($isento ? 'Sim' : 'Não') . '</td>';
            echo '</tr>';
        }

        echo '</table></body></html>';
        exit;
    }

    /* =====================================================
       DADOS — tabela supra_dataset_36_todos
       ===================================================== */
    private function dadosIniciais(): array
    {
        if ($this->cacheContratos !== null) return $this->cacheContratos;

        global $pdo;
        if (!$pdo instanceof PDO) {
            $this->cacheContratos = [];
            return $this->cacheContratos;
        }

        // ✅ ORDER BY id — mesma ordem que chega no banco
        $sql = "
            SELECT
                d.id,
                d.instrumento,
                d.uf,
                d.br,
                d.lote,
                d.nome_usual,
                d.empresa_construtora                AS empresa,
                d.tipo_execucao                      AS tipo_execucao,
                d.numero_sei_cronograma_vigente      AS sei_cronograma_vigente,
                d.data_termino_servicos              AS data_termino_servicos_cronograma,
                d.valor_previsto,
                d.valor_executado
            FROM supra_dataset_36_todos d
            WHERE d.instrumento IS NOT NULL AND d.instrumento <> ''
            ORDER BY d.id ASC
        ";

        try {
            $stmt = $pdo->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $this->cacheContratos = [];
            return $this->cacheContratos;
        }

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id'                                  => (int)$r['id'],
                'instrumento'                         => $r['instrumento'] ?? '',
                // ✅ Tipo = tipo_execucao (valor bruto vindo do banco)
                'tipo_contrato'                       => $r['tipo_execucao'] ?? '',
                'br'                                  => $r['br'] ?? '',
                'uf'                                  => $r['uf'] ?? '',
                'lote'                                => $r['lote'] ?? '',
                'nome_usual'                          => $r['nome_usual'] ?? '',
                'empresa'                             => $r['empresa'] ?? '',
                'sei_cronograma_vigente'              => $r['sei_cronograma_vigente'] ?? '',
                'data_termino_servicos_cronograma'    => $r['data_termino_servicos_cronograma'] ?: null,
                'ultimo_oficio_notificacao_cronograma'=> '',
                'data_ultima_notificacao_cronograma'  => null,
                'status_acao'                         => 'nao_notificar',
                'notificado'                          => 0,
                'isento'                              => 0,
                'valor_previsto'                      => $r['valor_previsto'] !== null ? (float)$r['valor_previsto'] : null,
                'valor_executado'                     => $r['valor_executado'] !== null ? (float)$r['valor_executado'] : null,
            ];
        }

        $this->cacheContratos = $out;
        return $out;
    }

    private function todosContratos(): array
    {
        $base      = $this->dadosIniciais();
        $overrides = $_SESSION['obras_overrides']['acoes'] ?? [];

        foreach ($base as &$c) {
            $id = (int)$c['id'];
            if (isset($overrides[$id])) {
                $c = array_merge($c, $overrides[$id]);
            }
            $c['tipo_contrato'] = $c['tipo_contrato'] ?? '';
            $c['status_acao']   = $c['status_acao']   ?? 'nao_notificar';
            $c['notificado']    = (int)($c['notificado'] ?? 0);
            $c['isento']        = (int)($c['isento'] ?? 0);
            $c['total_comentarios'] = count($_SESSION['obras_comentarios'][$id] ?? []);
        }
        unset($c);
        return $base;
    }

    private function buscarContrato(int $id): ?array
    {
        foreach ($this->todosContratos() as $c) {
            if ((int)$c['id'] === $id) return $c;
        }
        return null;
    }

    /* =====================================================
       REGRAS DE NEGÓCIO
       ===================================================== */
    private function calcStatusCronograma(?string $sei, ?string $dataTermino): array
    {
        $hoje = new DateTimeImmutable('today');
        if (empty($sei) && empty($dataTermino)) return $this->badge('sem_cronograma', 'Sem cronograma');
        if (!empty($sei) && empty($dataTermino)) return $this->badge('sem_data', 'Sem data');

        $dt = $this->parseData($dataTermino);
        if (!$dt) return $this->badge('sem_data', 'Sem data');

        $diff = $this->diasEntre($hoje, $dt);
        if ($diff < 0) {
            $n = abs($diff);
            return $this->badge('vencido', "Vencido há {$n} " . ($n === 1 ? 'dia' : 'dias'));
        }
        if ($diff === 0) return $this->badge('a_vencer', 'A vencer hoje');
        if ($diff <= self::JANELA_A_VENCER_DIAS) {
            return $this->badge('a_vencer', "A vencer em {$diff} " . ($diff === 1 ? 'dia' : 'dias'));
        }
        return $this->badge('no_prazo', 'No prazo');
    }

    private function calcStatusNotificacao(array $c): array
    {
        $hoje = new DateTimeImmutable('today');
        if (!empty($c['isento'])) return $this->badge('isento', 'Isento de notificação');

        $sei  = trim((string)($c['sei_cronograma_vigente'] ?? ''));
        $data = $c['data_termino_servicos_cronograma'] ?? null;

        if ($sei === '' && empty($data)) return $this->badge('sem_cronograma', 'Sem cronograma');
        if ($sei !== '' && empty($data)) return $this->badge('sem_data', 'Sem data de cronograma');

        $dtTerm = $this->parseData($data);
        if (!$dtTerm) return $this->badge('sem_data', 'Sem data de cronograma');

        $diffTerm = $this->diasEntre($hoje, $dtTerm);
        if ($diffTerm > self::JANELA_A_VENCER_DIAS) {
            return $this->badge('nao_necessita', 'Não necessita notificar');
        }
        if ($diffTerm >= 0) {
            return $diffTerm === 0
                ? $this->badge('notificar_hoje_ou_a_vencer', 'Notificar hoje')
                : $this->badge('notificar_hoje_ou_a_vencer', "Notificar em {$diffTerm} " . ($diffTerm === 1 ? 'dia' : 'dias'));
        }

        $dataUltNot = $c['data_ultima_notificacao_cronograma'] ?? null;
        if (empty($dataUltNot)) return $this->badge('falta_data_notificacao', 'Falta data de notificação');
        $dtUlt = $this->parseData($dataUltNot);
        if (!$dtUlt) return $this->badge('falta_data_notificacao', 'Falta data de notificação');

        $dtVencCiclo = $this->somarMesesCalendario($dtUlt, self::CICLO_NOTIFICACAO_MESES);
        $diffCiclo   = $this->diasEntre($hoje, $dtVencCiclo);

        if ($diffCiclo <= 0) {
            $n = abs($diffCiclo);
            return $this->badge('notificacao_vencida', "Notificação vencida há {$n} " . ($n === 1 ? 'dia' : 'dias'));
        }
        if ($diffCiclo <= self::JANELA_ALERTA_DIAS) {
            return $diffCiclo === 1
                ? $this->badge('notificar_hoje_ou_a_vencer', 'Notificar amanhã')
                : $this->badge('notificar_hoje_ou_a_vencer', "Notificar em {$diffCiclo} " . ($diffCiclo === 1 ? 'dia' : 'dias'));
        }
        return $this->badge('aguardando_prazo', 'Aguardando prazo de notificação');
    }

    private function badge(string $categoria, string $texto): array
    {
        $class = [
            'sem_cronograma'             => 'bg-secondary',
            'sem_data'                   => 'bg-warning text-dark',
            'vencido'                    => 'bg-danger',
            'a_vencer'                   => 'bg-warning text-dark',
            'no_prazo'                   => 'bg-success',
            'isento'                     => 'bg-info text-dark',
            'nao_necessita'              => 'bg-success',
            'notificar_hoje_ou_a_vencer' => 'bg-warning text-dark',
            'falta_data_notificacao'     => 'bg-secondary',
            'notificacao_vencida'        => 'bg-danger',
            'aguardando_prazo'           => 'bg-primary',
        ][$categoria] ?? 'bg-secondary';
        return ['categoria' => $categoria, 'texto' => $texto, 'class' => $class];
    }

    private function parseData(?string $data): ?DateTimeImmutable
    {
        if (empty($data)) return null;
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', substr($data, 0, 10));
        return $dt ?: null;
    }

    private function diasEntre(DateTimeInterface $de, DateTimeInterface $ate): int
    {
        $d1 = new DateTimeImmutable($de->format('Y-m-d'));
        $d2 = new DateTimeImmutable($ate->format('Y-m-d'));
        $diff = $d1->diff($d2);
        $dias = (int)$diff->days;
        return $diff->invert ? -$dias : $dias;
    }

    private function somarMesesCalendario(DateTimeInterface $data, int $meses): DateTimeImmutable
    {
        $dt  = new DateTimeImmutable($data->format('Y-m-d'));
        $dia = (int)$dt->format('d');
        $ano = (int)$dt->format('Y');
        $mes = (int)$dt->format('n') + $meses;
        $ano += intdiv($mes - 1, 12);
        $mes  = (($mes - 1) % 12 + 12) % 12 + 1;
        $ultimoDia = (int)(new DateTimeImmutable(sprintf('%04d-%02d-01', $ano, $mes)))
            ->modify('last day of this month')->format('d');
        return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $ano, $mes, min($dia, $ultimoDia)));
    }

    private function exigirLogin()
    {
        if (empty($_SESSION['usuario_id'])) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'Sessão expirada.']);
            exit;
        }
    }
}