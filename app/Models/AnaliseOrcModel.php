<?php
// app/Models/AnaliseOrcModel.php
//
// FONTES:
//   - supra_dataset_47_financeiro_orcamentario_atlas  (financeiro/orçamentário)
//   - supra_dataset_48_loa_janelas                    (LOA atualizada — dotações)
//   - supra_dataset_38_todos                          (BR, fallback cadastral)
//   - supra_dataset_36_todos                          (nome usual, empresa construtora, datas de execução)
//   - siac_medicoes                                   (medições mensais — scraper)
//   - analise_orc_estimativa                          (valores manuais — estimado)
//   - analise_orc_planejado_analista                  (valores manuais — planejado analista)
//   - analise_orc_preferencia                         (NPO vs ANALISTA por contrato)
//   - alerta_notificacao                              (notificações)
//   - npo_dotacao (fallback legado)                   (dotação)

class AnaliseOrcModel
{
    private const TABELA      = '`supra_dataset_47_financeiro_orcamentario_atlas`';
    private const TABELA_48   = '`supra_dataset_48_loa_janelas`';
    private const TABELA_38   = '`supra_dataset_38_todos`';
    private const TABELA_36   = '`supra_dataset_36_todos`';   // ⬅️ NOVO
    private const TABELA_MED  = '`siac_medicoes`';
    private const EXERCICIO_PADRAO = 2026;

    private ?array $configCache    = null;
    private array  $datasetRowCache = [];
    private array  $ds38RowCache    = [];
    private array  $ds36RowCache    = [];    // ⬅️ NOVO
    private array  $ds48Cache       = [];

    public function __construct(private PDO $pdo) {}

    public function pdo(): PDO { return $this->pdo; }

    // =============================================================
    // CONFIG
    // =============================================================
    public function configAll(): array
    {
        if ($this->configCache !== null) return $this->configCache;
        $this->configCache = [];
        try {
            foreach ($this->pdo->query('SELECT chave, valor FROM config_parametros')->fetchAll() as $r) {
                $this->configCache[$r['chave']] = $r['valor'];
            }
        } catch (Throwable $e) {}
        return $this->configCache + [
            'farol_verde_meses'          => '3',
            'farol_amarelo_meses'        => '1',
            'desvio_performance_pct'     => '30',
            'media_meses_estimativa'     => '3',
            'media_outlier_sigma'        => '2',
            'media_cv_alerta'            => '30',
            'saldo_tolerancia_anomalia'  => '100',
            'planejado_cor_verde_max'    => '1.5',
            'planejado_cor_amarelo_max'  => '2.0',
            'planejado_cor_laranja_max'  => '3.0',
        ];
    }
    public function configInt(string $c, int $d = 0): int       { return (int)($this->configAll()[$c] ?? $d); }
    public function configFloat(string $c, float $d = 0): float { return (float)($this->configAll()[$c] ?? $d); }

    // =============================================================
    // DATASET 47
    // =============================================================
    public function datasetRow(string $numero): ?array
    {
        if (array_key_exists($numero, $this->datasetRowCache)) return $this->datasetRowCache[$numero];
        try {
            $st = $this->pdo->prepare('SELECT * FROM ' . self::TABELA . ' WHERE contrato = :n LIMIT 1');
            $st->execute([':n' => $numero]);
            $r = $st->fetch() ?: null;
        } catch (Throwable $e) { $r = null; }
        return $this->datasetRowCache[$numero] = $r;
    }

    // =============================================================
    // DATASET 38 — BR (prioritário), fallback nome/empresa
    // =============================================================
    public function ds38Row(string $numero): ?array
    {
        if (array_key_exists($numero, $this->ds38RowCache)) return $this->ds38RowCache[$numero];

        try {
            $st = $this->pdo->prepare(
                'SELECT instrumento, br, nome_usual, empresa
                   FROM ' . self::TABELA_38 . '
                  WHERE instrumento = :n
                  ORDER BY id ASC
                  LIMIT 1'
            );
            $st->execute([':n' => $numero]);
            $r = $st->fetch() ?: null;
        } catch (Throwable $e) { $r = null; }

        return $this->ds38RowCache[$numero] = $r;
    }

    // =============================================================
    // DATASET 36 — nome usual, empresa construtora, datas de execução
    // =============================================================
    /**
     * Retorna a linha do DS36 para um contrato.
     *
     * Estratégia em cascata:
     *   1) Match exato em `instrumento`.
     *   2) Match normalizado (remove espaços, hífens, pontos, barras)
     *      para cobrir diferenças tipo "00 00041/2025" × "0000041/2025".
     *   3) Match por sufixo "<digitos>/<ano>" (remove zeros à esquerda).
     *
     * Cache em memória por número — zero N+1 ao listar contratos.
     */
    public function ds36Row(string $numero): ?array
    {
        if (array_key_exists($numero, $this->ds36RowCache)) {
            return $this->ds36RowCache[$numero];
        }

        $tabelas = [self::TABELA_36, '`supra_dataset_36_todos`'];

        // ---------- 1) Match exato ----------
        foreach ($tabelas as $tbl) {
            try {
                $st = $this->pdo->prepare("SELECT * FROM $tbl WHERE instrumento = :n LIMIT 1");
                $st->execute([':n' => $numero]);
                $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;
                if ($row !== null) return $this->ds36RowCache[$numero] = $row;
            } catch (Throwable $e) {
                continue;   // tabela pode não existir com esse nome
            }
        }

        // ---------- 2) Match normalizado ----------
        $numeroNorm = preg_replace('/[\s\-\.\/]+/', '', $numero);
        if ($numeroNorm !== '' && $numeroNorm !== $numero) {
            foreach ($tabelas as $tbl) {
                try {
                    $st = $this->pdo->prepare("
                        SELECT *
                          FROM $tbl
                         WHERE REPLACE(REPLACE(REPLACE(REPLACE(instrumento, ' ', ''), '-', ''), '.', ''), '/', '') = :n
                         LIMIT 1
                    ");
                    $st->execute([':n' => $numeroNorm]);
                    $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;
                    if ($row !== null) return $this->ds36RowCache[$numero] = $row;
                } catch (Throwable $e) {
                    continue;
                }
            }
        }

        // ---------- 3) Match por sufixo "<digitos>/<ano>" ----------
        if (preg_match('/(\d+)\s*\/\s*(\d{4})\s*$/', $numero, $m)) {
            $ano     = $m[2];
            $digitos = ltrim($m[1], '0');
            if ($digitos === '') $digitos = $m[1];

            foreach ($tabelas as $tbl) {
                try {
                    $st = $this->pdo->prepare("
                        SELECT *
                          FROM $tbl
                         WHERE instrumento LIKE :sufixo
                         LIMIT 1
                    ");
                    $st->execute([':sufixo' => "%{$digitos}/{$ano}"]);
                    $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;
                    if ($row !== null) return $this->ds36RowCache[$numero] = $row;
                } catch (Throwable $e) {
                    continue;
                }
            }
        }

        return $this->ds36RowCache[$numero] = null;
    }

    /**
     * Retorna o primeiro valor não-vazio entre as chaves indicadas.
     * Usado para montar a cascata DS36 → DS38 → DS47 sem repetir `??`.
     */
    private function pickFirst(?array $row, array $keys): mixed
    {
        if (!$row) return null;
        foreach ($keys as $k) {
            if (array_key_exists($k, $row) && $row[$k] !== null && $row[$k] !== '') {
                return $row[$k];
            }
        }
        return null;
    }

    // =============================================================
    // AÇÕES E TIPOS
    // =============================================================
    public function acoes(int $exercicio): array
    {
        try {
            $rows = $this->pdo->query(
                'SELECT acao, MIN(empreendimento) AS empreendimento, MIN(estado) AS estado
                   FROM ' . self::TABELA . "
                  WHERE acao IS NOT NULL AND acao <> ''
                  GROUP BY acao ORDER BY acao"
            )->fetchAll();
        } catch (Throwable $e) { return []; }

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id'               => (string)$r['acao'],
                'codigo'           => (string)$r['acao'],
                'descricao'        => $this->resumir((string)($r['empreendimento'] ?? '')),
                'exercicio'        => $exercicio,
                'superintendencia' => $r['estado'] ?? null,
            ];
        }
        return $out;
    }

    public function exercicioDisponivel(): ?int { return self::EXERCICIO_PADRAO; }

    public function tiposPorAcao(string $acaoId): array
    {
        try {
            $st = $this->pdo->prepare(
                'SELECT DISTINCT tipo_atlas FROM ' . self::TABELA . '
                  WHERE acao = :a AND tipo_atlas IS NOT NULL AND tipo_atlas <> ""
                  ORDER BY tipo_atlas'
            );
            $st->execute([':a' => $acaoId]);
            $raw = array_column($st->fetchAll(), 'tipo_atlas');
        } catch (Throwable $e) { return []; }
        $out = array_values(array_unique(array_map([$this, 'mapearTipo'], $raw)));
        sort($out);
        return $out;
    }

    public function contratosDaAcao(string $acaoId, ?string $tipo, ?string $super): array
    {
        $sql = 'SELECT * FROM ' . self::TABELA . ' WHERE acao = :a';
        $p = [':a' => $acaoId];
        if ($super !== null && $super !== '') { $sql .= ' AND estado = :e'; $p[':e'] = $super; }
        $sql .= ' ORDER BY contrato';
        try {
            $st = $this->pdo->prepare($sql);
            $st->execute($p);
            $rows = $st->fetchAll();
        } catch (Throwable $e) { return []; }

        $out = [];
        foreach ($rows as $r) {
            $c = $this->mapearContrato($r);
            if ($tipo !== null && $tipo !== '' && $c['tipo'] !== $tipo) continue;
            $out[] = $c;
        }
        return $out;
    }

    public function contratoPorNumero(string $numero): ?array
    {
        $r = $this->datasetRow($numero);
        return $r ? $this->mapearContrato($r) : null;
    }

    public function acoesDoContrato(string $numero): array
    {
        $r = $this->datasetRow($numero);
        return ($r && !empty($r['acao'])) ? [(string)$r['acao']] : [];
    }

    // =============================================================
    // DOTAÇÃO ATUALIZADA — lê do Dataset 48
    // =============================================================
    public function dotacaoAtualizada(string $acaoId, int $exercicio): float
    {
        $cacheKey = $acaoId;
        if (array_key_exists($cacheKey, $this->ds48Cache)) return $this->ds48Cache[$cacheKey];

        // 1) Match exato
        try {
            $st = $this->pdo->prepare(
                'SELECT COUNT(*), COALESCE(SUM(loa_atualizada), 0)
                   FROM ' . self::TABELA_48 . ' WHERE acao = :acao'
            );
            $st->execute([':acao' => $acaoId]);
            $row = $st->fetch(PDO::FETCH_NUM);
            if ($row && (int)$row[0] > 0) return $this->ds48Cache[$cacheKey] = (float)$row[1];
        } catch (Throwable $e) { /* DS48 não importado */ }

        // 2) Match normalizado
        $acaoNorm = preg_replace('/[^0-9A-Za-z]/', '', $acaoId);
        if ($acaoNorm !== '' && $acaoNorm !== $acaoId) {
            try {
                $st = $this->pdo->prepare(
                    'SELECT COUNT(*), COALESCE(SUM(loa_atualizada), 0)
                       FROM ' . self::TABELA_48 . '
                      WHERE REPLACE(REPLACE(REPLACE(acao, ".", ""), "-", ""), " ", "") = :acao'
                );
                $st->execute([':acao' => $acaoNorm]);
                $row = $st->fetch(PDO::FETCH_NUM);
                if ($row && (int)$row[0] > 0) return $this->ds48Cache[$cacheKey] = (float)$row[1];
            } catch (Throwable $e) {}
        }

        // 3) Fallback legado
        try {
            $st = $this->pdo->prepare(
                'SELECT COALESCE(SUM(d.valor), 0)
                   FROM npo_dotacao d
                   JOIN npo_acao_orcamentaria a ON a.id = d.acao_id
                  WHERE a.codigo = :acao AND d.exercicio = :ano'
            );
            $st->execute([':acao' => $acaoId, ':ano' => $exercicio]);
            return $this->ds48Cache[$cacheKey] = (float)$st->fetchColumn();
        } catch (Throwable $e) {
            return $this->ds48Cache[$cacheKey] = 0.0;
        }
    }

    // =============================================================
    // TOTAL LIQUIDADO (TODOS OS ANOS)
    // =============================================================
    public function totalLiquidadoContrato(string $numero): ?float
    {
        $r = $this->datasetRow($numero);
        if (!$r) return null;

        if (array_key_exists('total_liquidado', $r) && $r['total_liquidado'] !== null) {
            return (float)$r['total_liquidado'];
        }

        $total = 0.0;
        $achou = false;
        foreach ($r as $key => $val) {
            if (str_starts_with((string)$key, 'liquidado_') && $val !== null) {
                $total += (float)$val;
                $achou = true;
            }
        }
        return $achou ? $total : null;
    }

    // =============================================================
    // SIAC
    // =============================================================
    public function siacCabecalho(string $numero): ?array
    {
        $r = $this->datasetRow($numero);
        if (!$r) return null;
        $raw = strtoupper((string)($r['status_siac'] ?? ''));
        $status = match (true) {
            str_contains($raw, 'CONCLU')  => 'CONCLUIDO',
            str_contains($raw, 'PARALI')  => 'PARALISADO',
            str_contains($raw, 'RESCIND') => 'RESCINDIDO',
            default                        => 'EM_EXECUCAO',
        };
        return [
            'numero'                => $numero,
            'status'                => $status,
            'valor_medido_total'    => (float)($r['total_medido']        ?? 0),
            'valor_liquidado_total' => (float)($r['total_liquidado']     ?? 0),
            'media_ult_3_medicoes'  => (float)($r['media_ult_3_medicoes'] ?? 0),
            'data_ultima_medicao'   => $r['data_ref_ultima_medicao']      ?? null,
            'total_medido'          => (float)($r['total_medido']        ?? 0),
            'total_liquidado'       => (float)($r['total_liquidado']     ?? 0),
            'medido_no_ano'         => (float)($r['medido_no_ano']       ?? 0),
            'liquidado_no_ano'      => (float)($r['liquidado_no_ano']    ?? 0),
            'a_medir_ate_fim'       => (float)($r['a_medir_ate_fim_contrato']    ?? 0),
            'a_liquidar_ate_fim'    => (float)($r['a_liquidar_ate_fim_contrato'] ?? 0),
            'ultimo_mes_medido'     => $r['ultimo_mes_medido']           ?? null,
        ];
    }

    public function siacMedicoes(string $numero): array
    {
        try {
            $st = $this->pdo->prepare(
                'SELECT DATE_FORMAT(mes_referencia, "%Y-%m-01") AS mes_referencia,
                        SUM(COALESCE(total_liquido,0)) AS valor_medido,
                        MAX(data_termino) AS data_medicao
                   FROM ' . self::TABELA_MED . '
                  WHERE contrato = :n AND mes_referencia IS NOT NULL
                  GROUP BY mes_referencia
                  ORDER BY mes_referencia'
            );
            $st->execute([':n' => $numero]);
            $rows = $st->fetchAll();
            foreach ($rows as &$r) $r['valor_medido'] = (float)$r['valor_medido'];
            return $rows;
        } catch (Throwable $e) { return []; }
    }

    // =============================================================
    // SUPRA (planejado/liquidado mensal do Dataset 47)
    // =============================================================
    public function supraPlanejamento(string $numero, int $ano): array
    {
        $r = $this->datasetRow($numero);
        if (!$r) return [];
        $out = [];
        for ($m = 1; $m <= 12; $m++) {
            $col = 'planejado_npo_' . $this->nomeMes($m) . '_' . $ano;
            if (!array_key_exists($col, $r)) continue;
            $v = $r[$col] === null ? null : (float)$r[$col];
            if ($v !== null && $v > 0) $out[sprintf('%04d-%02d-01', $ano, $m)] = $v;
        }
        return $out;
    }

    public function supraLiquidacao(string $numero): array
    {
        $r = $this->datasetRow($numero);
        if (!$r) return [];
        $ano = self::EXERCICIO_PADRAO;
        $out = [];
        for ($m = 1; $m <= 12; $m++) {
            $col = 'liquidado_' . $this->nomeMes($m) . '_' . $ano;
            if (!array_key_exists($col, $r)) continue;
            if ($r[$col] === null) continue;
            $out[sprintf('%04d-%02d-01', $ano, $m)] = ['valor' => (float)$r[$col], 'data' => null];
        }
        return $out;
    }

    // =============================================================
    // NOTIFICAÇÕES
    // =============================================================
    public function registrarNotificacao(string $numero, string $tipo, string $mes, ?string $obs = null): void
    {
        try {
            $this->pdo->prepare(
                'INSERT INTO alerta_notificacao (contrato_numero, tipo, mes_referencia, observacao)
                 VALUES (:n, :t, :m, :o)
                 ON DUPLICATE KEY UPDATE observacao = COALESCE(VALUES(observacao), observacao)'
            )->execute([':n'=>$numero, ':t'=>$tipo, ':m'=>$mes, ':o'=>$obs]);
        } catch (Throwable $e) {}
    }

    public function listarNotificacoes(?string $numero = null, ?bool $lida = null, int $limit = 100): array
    {
        try {
            $sql = 'SELECT n.id, n.contrato_numero, n.tipo, n.mes_referencia,
                           n.observacao, n.lida, n.lida_em, n.notificado_em,
                           d.estado AS superintendencia
                      FROM alerta_notificacao n
                      LEFT JOIN ' . self::TABELA . ' d ON d.contrato = n.contrato_numero
                     WHERE 1=1';
            $p = [];
            if ($numero !== null && $numero !== '') { $sql .= ' AND n.contrato_numero = :n'; $p[':n'] = $numero; }
            if ($lida   !== null)                   { $sql .= ' AND n.lida = :l';            $p[':l'] = (int)$lida; }
            $sql .= ' ORDER BY n.notificado_em DESC LIMIT ' . max(1, min(500, $limit));
            $st = $this->pdo->prepare($sql);
            $st->execute($p);
            return $st->fetchAll();
        } catch (Throwable $e) { return []; }
    }

    public function contarNaoLidas(?string $numero = null): int
    {
        try {
            $sql = 'SELECT COUNT(*) FROM alerta_notificacao WHERE lida = 0';
            $p = [];
            if ($numero) { $sql .= ' AND contrato_numero = :n'; $p[':n'] = $numero; }
            $st = $this->pdo->prepare($sql);
            $st->execute($p);
            return (int)$st->fetchColumn();
        } catch (Throwable $e) { return 0; }
    }

    public function marcarNotificacaoLida(int $id): void
    {
        try {
            $this->pdo->prepare('UPDATE alerta_notificacao SET lida = 1, lida_em = NOW() WHERE id = :id')
                 ->execute([':id' => $id]);
        } catch (Throwable $e) {}
    }

    public function marcarTodasNotificacoesLidas(?string $numero = null): void
    {
        try {
            $sql = 'UPDATE alerta_notificacao SET lida = 1, lida_em = NOW() WHERE lida = 0';
            $p = [];
            if ($numero) { $sql .= ' AND contrato_numero = :n'; $p[':n'] = $numero; }
            $this->pdo->prepare($sql)->execute($p);
        } catch (Throwable $e) {}
    }

    // =============================================================
    // ESTIMATIVA MANUAL
    // =============================================================
    public function getEstimativasContrato(string $numero): array
    {
        try {
            $st = $this->pdo->prepare(
                'SELECT mes, valor, valor_calculado, usuario, justificativa, alterado_em
                   FROM analise_orc_estimativa WHERE contrato = :n'
            );
            $st->execute([':n' => $numero]);
            $out = [];
            foreach ($st->fetchAll() as $r) {
                $out[$r['mes']] = [
                    'valor'           => (float)$r['valor'],
                    'valor_calculado' => (float)$r['valor_calculado'],
                    'usuario'         => $r['usuario'],
                    'justificativa'   => $r['justificativa'] ?? null,
                    'alterado_em'     => date('d/m/Y H:i', strtotime($r['alterado_em'])),
                ];
            }
            return $out;
        } catch (Throwable $e) {
            $all = $_SESSION['analise_orc_estimativas'] ?? [];
            $out = [];
            foreach ($all as $k => $v) {
                if (str_starts_with($k, $numero . '|')) {
                    $out[substr($k, strlen($numero) + 1)] = $v;
                }
            }
            return $out;
        }
    }

    public function setEstimativaManual(string $numero, string $mes, float $valor,
                                        string $usuario, float $valorCalculado,
                                        string $justificativa = ''): void
    {
        try {
            $this->pdo->prepare(
                'INSERT INTO analise_orc_estimativa
                    (contrato, mes, valor, valor_calculado, usuario, justificativa, alterado_em)
                 VALUES (:c, :m, :v, :vc, :u, :j, NOW())
                 ON DUPLICATE KEY UPDATE
                    valor = VALUES(valor),
                    valor_calculado = VALUES(valor_calculado),
                    usuario = VALUES(usuario),
                    justificativa = VALUES(justificativa),
                    alterado_em = NOW()'
            )->execute([
                ':c'=>$numero, ':m'=>$mes, ':v'=>$valor, ':vc'=>$valorCalculado,
                ':u'=>$usuario, ':j'=>$justificativa
            ]);
        } catch (Throwable $e) {
            $_SESSION['analise_orc_estimativas'][$numero . '|' . $mes] = [
                'valor' => $valor, 'valor_calculado' => $valorCalculado,
                'usuario' => $usuario, 'alterado_em' => date('d/m/Y H:i'),
                'justificativa' => $justificativa,
            ];
        }
    }

    public function removerEstimativaManual(string $numero, string $mes): void
    {
        try {
            $this->pdo->prepare('DELETE FROM analise_orc_estimativa WHERE contrato = :c AND mes = :m')
                 ->execute([':c' => $numero, ':m' => $mes]);
        } catch (Throwable $e) {
            unset($_SESSION['analise_orc_estimativas'][$numero . '|' . $mes]);
        }
    }

    // =============================================================
    // PLANEJADO ANALISTA
    // =============================================================
    public function getPlanejadoAnalistaContrato(string $numero): array
    {
        try {
            $st = $this->pdo->prepare(
                'SELECT mes, valor, valor_calculado, usuario, justificativa, alterado_em
                   FROM analise_orc_planejado_analista WHERE contrato = :n'
            );
            $st->execute([':n' => $numero]);
            $out = [];
            foreach ($st->fetchAll() as $r) {
                $out[$r['mes']] = [
                    'valor'           => (float)$r['valor'],
                    'valor_calculado' => (float)$r['valor_calculado'],
                    'usuario'         => $r['usuario'],
                    'justificativa'   => $r['justificativa'] ?? null,
                    'alterado_em'     => date('d/m/Y H:i', strtotime($r['alterado_em'])),
                ];
            }
            return $out;
        } catch (Throwable $e) { return []; }
    }

    public function setPlanejadoAnalista(string $numero, string $mes, float $valor,
                                          string $usuario, float $valorCalculado,
                                          string $justificativa = ''): void
    {
        try {
            $this->pdo->prepare(
                'INSERT INTO analise_orc_planejado_analista
                    (contrato, mes, valor, valor_calculado, usuario, justificativa, alterado_em)
                 VALUES (:c, :m, :v, :vc, :u, :j, NOW())
                 ON DUPLICATE KEY UPDATE
                    valor = VALUES(valor),
                    valor_calculado = VALUES(valor_calculado),
                    usuario = VALUES(usuario),
                    justificativa = VALUES(justificativa),
                    alterado_em = NOW()'
            )->execute([
                ':c'=>$numero, ':m'=>$mes, ':v'=>$valor, ':vc'=>$valorCalculado,
                ':u'=>$usuario, ':j'=>$justificativa
            ]);
        } catch (Throwable $e) {}
    }

    public function removerPlanejadoAnalista(string $numero, string $mes): void
    {
        try {
            $this->pdo->prepare(
                'DELETE FROM analise_orc_planejado_analista WHERE contrato = :c AND mes = :m'
            )->execute([':c' => $numero, ':m' => $mes]);
        } catch (Throwable $e) {}
    }

    // =============================================================
    // PREFERÊNCIA (NPO vs ANALISTA)
    // =============================================================
    public function getPreferencia(string $numero): string
    {
        try {
            $st = $this->pdo->prepare(
                'SELECT campo_planejamento FROM analise_orc_preferencia WHERE contrato = :c'
            );
            $st->execute([':c' => $numero]);
            $v = $st->fetchColumn();
            return in_array($v, ['NPO','ANALISTA'], true) ? $v : 'NPO';
        } catch (Throwable $e) { return 'NPO'; }
    }

    public function setPreferencia(string $numero, string $campo, string $usuario): void
    {
        $campo = in_array($campo, ['NPO','ANALISTA'], true) ? $campo : 'NPO';
        try {
            $this->pdo->prepare(
                'INSERT INTO analise_orc_preferencia (contrato, campo_planejamento, usuario, alterado_em)
                 VALUES (:c, :p, :u, NOW())
                 ON DUPLICATE KEY UPDATE
                    campo_planejamento = VALUES(campo_planejamento),
                    usuario = VALUES(usuario),
                    alterado_em = NOW()'
            )->execute([':c' => $numero, ':p' => $campo, ':u' => $usuario]);
        } catch (Throwable $e) {}
    }

    // =============================================================
    // CLASSIFICAÇÃO DE COR DO NPO vs MÉDIA
    // =============================================================
    public function classificarCorNpo(?float $npo, ?float $media): ?string
    {
        if ($npo === null || $media === null || $media <= 0 || $npo <= 0) return null;
        $ratio   = $npo / $media;
        $verde   = $this->configFloat('planejado_cor_verde_max', 1.5);
        $amarelo = $this->configFloat('planejado_cor_amarelo_max', 2.0);
        $laranja = $this->configFloat('planejado_cor_laranja_max', 3.0);
        if ($ratio <= $verde)   return 'VERDE';
        if ($ratio <= $amarelo) return 'AMARELO';
        if ($ratio <= $laranja) return 'LARANJA';
        return 'VERMELHO';
    }

    // =============================================================
    // MAPEAR CONTRATO — cascata DS36 → DS38 → DS47
    // =============================================================
    private function mapearContrato(array $r): array
    {
        $numero   = (string)$r['contrato'];
        $tipo     = $this->mapearTipo((string)($r['tipo_atlas'] ?? ''));
        $acao     = !empty($r['acao']) ? (string)$r['acao'] : null;

        $pi       = (float)($r['valor_pi_dnit']       ?? 0);
        $aditivo  = (float)($r['valor_total_aditivo'] ?? 0);
        $reajuste = (float)($r['reajuste']            ?? 0);

        $saldoEmp = (float)($r['saldo_empenho_acao']    ?? 0);
        $saldoRap = (float)($r['saldo_rap_acao']        ?? 0);
        $saldoER  = (float)($r['saldo_empenho_rap_acao'] ?? ($saldoEmp + $saldoRap));

        // Cadastros externos
        $ds38 = $this->ds38Row($numero);
        $ds36 = $this->ds36Row($numero);

        // ============================================================
        // NOME USUAL — DS36 (nome_usual / nome_empreendimento_governa) → DS38 → DS47
        // ============================================================
        $nomeUsual = $this->pickFirst($ds36, [
            'nome_usual',            
        ]) ?? $this->pickFirst($ds38, [
            'nome_usual',
            'nome_empreendimento',
        ]) ?? ($r['nome_usual'] ?? null);

        // ============================================================
        // EMPRESA (executora) — DS36 usa `empresa_construtora`
        // ============================================================
        $empresa = $this->pickFirst($ds36, [
            'empresa_construtora',        // ⬅️ campo correto no DS36
        ]) ?? $this->pickFirst($ds38, [
            'empresa',
            'empresa_construtora',
            'empresa_executora',
        ]) ?? ($r['empresa'] ?? null);

        // ============================================================
        // BR — DS38 → DS36 → DS47
        // ============================================================
        $br = $this->pickFirst($ds38, ['br', 'rodovia', 'br_rodovia'])
           ?? $this->pickFirst($ds36, ['br', 'rodovia', 'br_rodovia'])
           ?? ($r['br'] ?? null);

        // ============================================================
        // DATAS — DS36 (execução) → DS47 (serviços/vigência)
        // ============================================================
        $inicio36 = $this->pickFirst($ds36, [
            'data_inicio_vigencia',
            'inicio_vigencia',
            'data_inicio_execucao',
            'inicio_servicos',
        ]);

        $termino36 = $this->pickFirst($ds36, [
            'data_termino_servicos',                       // término dos serviços (mais específico)
            'data_termino_execucao',                       // término da execução
            'data_termino_cronograma_obra_atualizado',     // cronograma atualizado
            'data_termino_vigencia',                       // vigência
        ]);

        $dataInicioServ  = $r['inicio_servicos']  ?? null;
        $dataInicioVig   = $r['inicio_vigencia']  ?? null;
        $dataTerminoServ = $r['termino_servicos'] ?? null;
        $dataTerminoVig  = $r['termino_vigencia'] ?? null;

        $dataInicioFinal     = $inicio36  ?? $dataInicioServ  ?? $dataInicioVig;
        $dataTerminoServicos = $termino36 ?? $dataTerminoServ ?? $dataTerminoVig;

        return [
            'id'                      => crc32($numero),
            'numero'                  => $numero,
            'tipo'                    => $tipo,
            'superintendencia'        => $r['estado']          ?? null,
            'acao_id_principal'       => $acao,
            'acoes'                   => $acao ? [$acao] : [],

            // Cadastro consolidado
            'nome_usual'              => $nomeUsual,
            'br'                      => $br,
            'empresa'                 => $empresa,

            // Instrumento
            'valor_pi'                => $pi,
            'valor_aditivo'           => $aditivo,
            'valor_reajuste'          => $reajuste,
            'pi_a_r'                  => isset($r['pi_a_r']) ? (float)$r['pi_a_r'] : null,
            'total_instrumento'       => $pi + $aditivo + $reajuste,

            // Empenho / RAP
            'valor_empenhado'         => $saldoEmp,
            'valor_rap'               => $saldoRap,
            'valor_empenhado_efetivo' => $saldoEmp,
            'valor_rap_efetivo'       => $saldoRap,
            'disponivel_bruto'        => $saldoER,
            'valor_planejado'         => 0.0,

            // Datas
            'data_inicio'             => $dataInicioFinal,
            'data_fim'                => $dataTerminoServicos,
            'termino_servicos'        => $dataTerminoServicos,
            'termino_vigencia'        => $dataTerminoVig  ?? null,
            'inicio_servicos'         => $dataInicioServ  ?? null,
            'inicio_vigencia'         => $dataInicioVig   ?? null,

            // SIAC / status
            'status_siac'             => $r['status_siac']            ?? null,
            'ultimo_mes_medido'       => $r['ultimo_mes_medido']      ?? null,
            'ultimo_mes_liquidado'    => $r['ultimo_mes_liquidado']   ?? null,
            'a_medir_ate_fim'         => (float)($r['a_medir_ate_fim_contrato']    ?? 0),
            'a_liquidar_ate_fim'      => (float)($r['a_liquidar_ate_fim_contrato'] ?? 0),
        ];
    }

    private function mapearTipo(string $raw): string
    {
        $u = mb_strtoupper($raw, 'UTF-8');
        if (str_contains($u, 'SUPERV')) return 'SUPERVISAO';
        if (str_contains($u, 'AMBIENT')) return 'MEIO_AMBIENTE';
        if (str_contains($u, 'DESAPRO')) return 'DESAPROPRIACAO';
        if (str_contains($u, 'OBRA'))    return 'OBRA';
        return 'OUTROS';
    }

    private function nomeMes(int $m): string
    {
        return [1=>'janeiro',2=>'fevereiro',3=>'marco',4=>'abril',5=>'maio',6=>'junho',
                7=>'julho',8=>'agosto',9=>'setembro',10=>'outubro',11=>'novembro',12=>'dezembro'][$m] ?? '';
    }

    private function resumir(string $t): string
    {
        $t = trim($t);
        return mb_strlen($t) > 90 ? mb_substr($t, 0, 87) . '...' : $t;
    }
}