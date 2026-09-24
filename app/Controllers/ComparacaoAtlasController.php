<?php
// app/Controllers/ComparacaoAtlasController.php
// Controller dedicado ao Comparativo entre Bancos (Setor Atlas/Monitoramento)
// DataSets: 36 (Info. Gerais de Obras) × 38 (Lista Todos Instrumentos) × 1 (Carteira COCCONV)

class ComparacaoAtlasController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // ============================================================
    // Helpers privados
    // ============================================================

    /**
     * Normaliza o número do instrumento para comparação.
     * Remove zeros à esquerda do bloco antes da barra e deixa em maiúsculo.
     * Ex.: "00 00608/2024" → "00608/2024"
     */
    private function normalizarInstrumento($inst) {
        $inst = strtoupper(trim((string)$inst));
        if (preg_match('/^0+(\d+\/\d+)$/', $inst, $m)) {
            return $m[1];
        }
        return $inst;
    }

    /**
     * Lê uma tabela de dataset e devolve um array associativo indexado
     * pelo instrumento normalizado.
     *
     * ⚠️ AJUSTE os nomes das tabelas conforme o seu banco:
     *   - DataSet 36: supra_dataset_36_todos
     *   - DataSet 38: supra_dataset_38_todos
     *   - DataSet 1 : supra_dataset_1_todos
     */
        private function carregarDataset($tabela) {
            $tabelasPermitidas = [
                'supra_dataset_36_todos',
                'supra_dataset_38_todos',
                'supra_dataset_1_todos',
            ];
            if (!in_array($tabela, $tabelasPermitidas, true)) {
                throw new Exception("Tabela não permitida: $tabela");
            }

            try {
                $sql = "SELECT instrumento, uf, br, nome_usual
                        FROM `$tabela`
                        WHERE instrumento IS NOT NULL AND TRIM(instrumento) <> ''";
                $stmt = $this->pdo->query($sql);
                $linhas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $this->tabelasAusentes[] = $tabela;
                return [];
            }

            $index = [];
            foreach ($linhas as $l) {
                $chave = $this->normalizarInstrumento($l['instrumento']);
                if ($chave === '') continue;
                if (!isset($index[$chave])) $index[$chave] = $l;
            }
            return $index;
        }
    /**
     * Consolida os 3 datasets num array único de registros.
     * Cada registro tem: instrumento, uf, br, nome_usual, em_36, em_38, em_1
     * A UF/BR/Nome Usual é preenchida com prioridade 36 → 38 → 1.
     */
    private function consolidar($index36, $index38, $index1) {
        $todasChaves = array_unique(array_merge(
            array_keys($index36),
            array_keys($index38),
            array_keys($index1)
        ));
        sort($todasChaves);

        $registros = [];
        foreach ($todasChaves as $chave) {
            $r = [
                'instrumento' => $chave,
                'uf'          => null,
                'br'          => null,
                'nome_usual'  => null,
                'em_36'       => isset($index36[$chave]),
                'em_38'       => isset($index38[$chave]),
                'em_1'        => isset($index1[$chave]),
            ];

            foreach ([$index36, $index38, $index1] as $idx) {
                if (!isset($idx[$chave])) continue;
                $d = $idx[$chave];
                if ($r['uf']         === null && !empty($d['uf']))         $r['uf']         = $d['uf'];
                if ($r['br']         === null && !empty($d['br']))         $r['br']         = $d['br'];
                if ($r['nome_usual'] === null && !empty($d['nome_usual'])) $r['nome_usual'] = $d['nome_usual'];
            }

            $registros[] = $r;
        }
        return $registros;
    }

    /**
     * Aplica filtros de UF/BR/Busca e (opcional) apenas divergências.
     */
    private function aplicarFiltros(array $registros, $filtroUF, $filtroBR, $filtroBusca, $apenasDivergentes) {
        return array_values(array_filter($registros, function ($r) use ($filtroUF, $filtroBR, $filtroBusca, $apenasDivergentes) {
            if ($filtroUF && strcasecmp((string)$r['uf'], $filtroUF) !== 0) return false;
            if ($filtroBR && strcasecmp((string)$r['br'], $filtroBR) !== 0) return false;

            if ($filtroBusca !== '') {
                $busca = strtolower($filtroBusca);
                $match = false;
                foreach (['instrumento', 'nome_usual', 'uf', 'br'] as $campo) {
                    if (stripos((string)$r[$campo], $busca) !== false) {
                        $match = true;
                        break;
                    }
                }
                if (!$match) return false;
            }

            if ($apenasDivergentes) {
                $qtd = ($r['em_36'] ? 1 : 0) + ($r['em_38'] ? 1 : 0) + ($r['em_1'] ? 1 : 0);
                if ($qtd === 3) return false;
            }

            return true;
        }));
    }

    // ============================================================
    // AÇÃO PRINCIPAL
    // ============================================================
    public function comparativoBancos() {
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: login.php');
            exit;
        }

        $usuario_nome  = $_SESSION['usuario_nome']  ?? 'Usuário';
        $usuario_nivel = $_SESSION['usuario_nivel'] ?? 'usuario';
        $usuario_id    = $_SESSION['usuario_id']    ?? 0;

        // ---- Filtros da URL ----
        $filtroUF          = $_GET['uf']    ?? '';
        $filtroBR          = $_GET['br']    ?? '';
        $filtroBusca       = $_GET['busca'] ?? '';
        $apenasDivergentes = isset($_GET['divergentes']) && $_GET['divergentes'] === '1';

        // ---- Carrega os 3 datasets ----
        $index36 = $this->carregarDataset('supra_dataset_36_todos');
        $index38 = $this->carregarDataset('supra_dataset_38_todos');
        $index1  = $this->carregarDataset('supra_dataset_1_todos');

        $total36 = count($index36);
        $total38 = count($index38);
        $total1  = count($index1);

        // ---- Consolida ----
        $registros = $this->consolidar($index36, $index38, $index1);

        // ---- KPIs globais (antes dos filtros) ----
        $emTodos   = 0;   // em 36, 38 e 1
        $emDois    = 0;   // em dois quaisquer
        $emApenas1 = 0;   // em apenas um

        foreach ($registros as $r) {
            $qtd = ($r['em_36'] ? 1 : 0) + ($r['em_38'] ? 1 : 0) + ($r['em_1'] ? 1 : 0);
            if ($qtd === 3)     $emTodos++;
            elseif ($qtd === 2) $emDois++;
            else                $emApenas1++;
        }

        // ---- Opções para os selects (a partir do consolidado, sem filtros) ----
        $ufsSet = [];
        $brsSet = [];
        foreach ($registros as $r) {
            if (!empty($r['uf'])) $ufsSet[$r['uf']] = true;
            if (!empty($r['br'])) $brsSet[$r['br']] = true;
        }
        $ufs = array_keys($ufsSet);
        $brs = array_keys($brsSet);
        sort($ufs);
        sort($brs);

        // ---- Aplica filtros ----
        $registros = $this->aplicarFiltros($registros, $filtroUF, $filtroBR, $filtroBusca, $apenasDivergentes);
        $totalRegistrosFiltrados = count($registros);

        // ---- Variáveis disponíveis na view ----
        // (mantém os mesmos nomes que a view comparativo_bancos.php espera)
        require_once APP_PATH . '/Views/comparativo_bancos.php';
    }
}