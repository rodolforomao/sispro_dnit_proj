<?php
// ============================================================
// app/Controllers/AvancoFisicoController.php
// Controller dedicado ao Avanço Físico por Família de Serviço
// (Setor Atlas/Monitoramento)
// ============================================================

class AvancoFisicoController {
    private $pdo;

    /**
     * Contrato padrão — pode ser sobrescrito via ?contrato=XX
     * ⚠️ AJUSTE: hoje é um valor fixo, no futuro pode vir da URL ou do banco
     */
    private $contratoPadrao = '-';

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // ============================================================
    // AÇÃO PRINCIPAL
    // ============================================================
    public function index() {
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: login.php');
            exit;
        }

        $usuario_nome  = $_SESSION['usuario_nome']  ?? 'Usuário';
        $usuario_nivel = $_SESSION['usuario_nivel'] ?? 'usuario';

        // Contrato selecionado (via URL ou padrão)
        $contrato = trim($_GET['contrato'] ?? $this->contratoPadrao);
        if ($contrato === '') $contrato = $this->contratoPadrao;

        // ---- Dados para o gráfico ----
        $dados = $this->getDadosGrafico($contrato);

        // Lista de contratos disponíveis (para o select de filtro)
        // ⚠️ FUTURO: substituir por SELECT DISTINCT contrato FROM medicoes
        $contratosList = $this->getContratosDisponiveis();

        // ---- Variáveis expostas para a view ----
        require_once APP_PATH . '/Views/avanco_fisico.php';
    }

    // ============================================================
    // FONTE DE DADOS — HOJE FIXA, AMANHÃ MySQL
    // ============================================================
    /**
     * Retorna os dados agregados por família de serviço para o gráfico.
     *
     * ⚠️ FUTURO — Para trocar por banco de dados, substituir o bloco
     * de arrays fixos por uma consulta como:
     *
     *   $stmt = $this->pdo->prepare("
     *       SELECT servico, km_total, km_executado, medido_pct
     *       FROM medicoes_servico
     *       WHERE contrato = ?
     *   ");
     *   $stmt->execute([$contrato]);
     *   $linhas = $stmt->fetchAll(PDO::FETCH_ASSOC);
     *
     * Depois montar os arrays 'medido' e 'executado' a partir de $linhas.
     *
     * Schema esperado da tabela futura:
     *   CREATE TABLE medicoes_servico (
     *       id           INT AUTO_INCREMENT PRIMARY KEY,
     *       contrato     VARCHAR(50)  NOT NULL,
     *       servico      VARCHAR(100) NOT NULL,
     *       km_total     DECIMAL(10,3),
     *       km_executado DECIMAL(10,3),
     *       medido_pct   DECIMAL(6,2),   -- medição contratual em %
     *       updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
     *   );
     *
     * @param string $contrato
     * @return array
     */
    private function getDadosGrafico($contrato) {
        // ============================================================
        // ORDEM DAS CATEGORIAS (fixa, do topo para baixo no gráfico)
        // ============================================================
        $categorias = [
            'Drenagem/OAC',
            'Drenagem/Superficial',
            'Obras Complementares',
            'Recuperação de Áreas Degradadas',
            'Regularização Sub-Leito',
            'Sub-Base',
            'Supressão Vegetal',
            'Terraplenagem',
            'Base',
            'Revestimento',
            'Sinalização',
        ];

        // ============================================================
        // FASE 1 — DADOS DE EXECUÇÃO FÍSICA (por contrato)
        // Fonte: tabela informada pelo usuário
        // km_total do contrato = 9,9 km
        // ============================================================
        $execucaoFisica = [
            'Base'                              => ['km_total' => 9.9, 'km_executado' => 0.4],
            'Drenagem/OAC'                      => ['km_total' => 9.9, 'km_executado' => 1.9],
            'Drenagem/Superficial'              => ['km_total' => 9.9, 'km_executado' => 1.9],
            'Obras Complementares'              => ['km_total' => 9.9, 'km_executado' => 1.9],
            'Recuperação de Áreas Degradadas'   => ['km_total' => 9.9, 'km_executado' => 0.0],
            'Regularização Sub-Leito'           => ['km_total' => 9.9, 'km_executado' => 0.4],
            'Revestimento'                      => ['km_total' => 9.9, 'km_executado' => 0.4],
            'Sinalização'                       => ['km_total' => 9.9, 'km_executado' => 0.0],
            'Sub-Base'                          => ['km_total' => 9.9, 'km_executado' => 0.4],
            'Supressão Vegetal'                 => ['km_total' => 9.9, 'km_executado' => 11.3],
            'Terraplenagem'                     => ['km_total' => 9.9, 'km_executado' => 10.7],
        ];

        // ============================================================
        // FASE 2 — MEDIÇÃO CONTRATUAL (placeholder)
        // ⚠️ Ainda não temos a fonte real. Valores abaixo são apenas
        //    para o gráfico ficar completo — serão substituídos quando
        //    você me passar a tabela de medição.
        // ============================================================
        $medicaoContratual = [
            'Drenagem/OAC'                    => 92.5,
            'Drenagem/Superficial'            => 6.1,
            'Obras Complementares'            => 89.3,
            'Recuperação de Áreas Degradadas' => 0.0,
            'Regularização Sub-Leito'         => 50.0,
            'Sub-Base'                        => 48.6,
            'Supressão Vegetal'               => 95.6,
            'Terraplenagem'                   => 92.3,
            'Base'                            => 46.2,
            'Revestimento'                    => 28.4,
            'Sinalização'                     => 0.0,
        ];

        // ============================================================
        // MONTA OS ARRAYS NA ORDEM DAS CATEGORIAS
        // ============================================================
        $labels    = [];
        $medido    = [];
        $executado = [];

        foreach ($categorias as $cat) {
            $labels[] = $cat;

            // Medição contratual (%)
            $medido[] = round((float)($medicaoContratual[$cat] ?? 0), 1);

            // Execução física (% = km_executado / km_total * 100)
            $d = $execucaoFisica[$cat] ?? ['km_total' => 0, 'km_executado' => 0];
            $pct = $d['km_total'] > 0 ? ($d['km_executado'] / $d['km_total']) * 100 : 0;
            $executado[] = round($pct, 1);
        }

        return [
            'contrato'         => $contrato,
            'km_total_contrato'=> 9.9,
            'labels'           => $labels,
            'medido'           => $medido,
            'executado'        => $executado,
        ];
    }

    // ============================================================
    // LISTA DE CONTRATOS DISPONÍVEIS
    // ============================================================
    /**
     * ⚠️ FUTURO: substituir por
     *   SELECT DISTINCT contrato FROM medicoes_servico ORDER BY contrato
     */
    private function getContratosDisponiveis() {
        // Por enquanto, devolve só o contrato padrão
        return [$this->contratoPadrao];
    }
}