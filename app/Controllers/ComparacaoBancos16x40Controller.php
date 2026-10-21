<?php
// app/Controllers/ComparacaoBancos16x40Controller.php
// Comparativo: Base Controle GEO (DataSet 16) × Slides Atlas (DataSet 40)
// Setor: Atlas/Monitoramento
// Match entre os bancos: ID PAC

class ComparacaoBancos16x40Controller {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // ============================================================
    // VIEW PRINCIPAL — comparativo_bancos_16x40
    // ============================================================
    public function index() {
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: login');
            exit;
        }

        $usuario_nome  = $_SESSION['usuario_nome']  ?? 'Usuário';
        $usuario_nivel = $_SESSION['usuario_nivel'] ?? 'usuario';
        $usuario_id    = $_SESSION['usuario_id']    ?? 0;
        $setor_slug    = $_SESSION['setor_slug']    ?? 'atlas-monitoramento';

        // Filtros
        $filtroUF    = $_GET['uf']    ?? '';
        $filtroBR    = $_GET['br']    ?? '';
        $filtroBusca = $_GET['busca'] ?? '';

        // --------------------------------------------------------
        // Lado A — DataSet 16 (Base Controle GEO)
        // --------------------------------------------------------
        $sql16    = "SELECT * FROM supra_dataset_16_base_controle_geo WHERE 1=1";
        $params16 = [];

        if (!empty($filtroUF)) {
            $sql16 .= " AND uf = ?";
            $params16[] = $filtroUF;
        }
        if (!empty($filtroBR)) {
            $sql16 .= " AND br = ?";
            $params16[] = $filtroBR;
        }
        if (!empty($filtroBusca)) {
            $sql16 .= " AND (o_instrum LIKE ? OR o_obj_resu LIKE ? OR o_empresa LIKE ? OR trecho LIKE ? OR id_pac LIKE ?)";
            for ($i = 0; $i < 5; $i++) $params16[] = "%$filtroBusca%";
        }
        $sql16 .= " ORDER BY uf ASC, br ASC, o_km_inici ASC";

        $stmt = $this->pdo->prepare($sql16);
        $stmt->execute($params16);
        $base16 = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // --------------------------------------------------------
        // Lado B — DataSet 40 (Slides Atlas)
        // --------------------------------------------------------
        $sql40    = "SELECT * FROM supra_dataset_40_slides_atlas WHERE 1=1";
        $params40 = [];

        if (!empty($filtroUF)) {
            $sql40 .= " AND estado = ?";
            $params40[] = $filtroUF;
        }
        if (!empty($filtroBR)) {
            $sql40 .= " AND br = ?";
            $params40[] = $filtroBR;
        }
        if (!empty($filtroBusca)) {
            $sql40 .= " AND (instrumento LIKE ? OR nome_empreendimento_slides LIKE ? OR nome_lote_slide LIKE ? OR empresa_construtora LIKE ? OR id_pac LIKE ?)";
            for ($i = 0; $i < 5; $i++) $params40[] = "%$filtroBusca%";
        }
        $sql40 .= " ORDER BY estado ASC, br ASC";

        $stmt = $this->pdo->prepare($sql40);
        $stmt->execute($params40);
        $base40 = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // --------------------------------------------------------
        // Índices por ID PAC
        // --------------------------------------------------------
        $idx16 = [];
        foreach ($base16 as $r) {
            $id = trim((string)($r['id_pac'] ?? ''));
            if ($id !== '') $idx16[$id] = $r;
        }

        $idx40 = [];
        foreach ($base40 as $r) {
            $id = trim((string)($r['id_pac'] ?? ''));
            if ($id !== '') $idx40[$id] = $r;
        }

        // --------------------------------------------------------
        // Totais / KPIs
        // --------------------------------------------------------
        $total16 = count($base16);
        $total40 = count($base40);

        $presentes = 0;
        foreach ($idx16 as $id_pac => $_) {
            if (isset($idx40[$id_pac])) $presentes++;
        }

        $ausentes40      = $total16 - $presentes;   // GEO sem Slides Atlas
        $totalCandidatos = 0;                        // Slides Atlas sem GEO

        // --------------------------------------------------------
        // Aba 1 — Validação (GEO → Slides)
        // --------------------------------------------------------
        $validacao = [];
        foreach ($base16 as $r) {
            $id = trim((string)($r['id_pac'] ?? ''));
            $d40 = ($id !== '' && isset($idx40[$id])) ? $idx40[$id] : null;

            $validacao[] = [
                'geo'      => $r,
                'atlas'    => $d40,
                'presente' => $d40 !== null,
            ];
        }

        // --------------------------------------------------------
        // Aba 2 — Candidatos (Slides sem GEO)
        // --------------------------------------------------------
        $candidatos = [];
        foreach ($base40 as $r) {
            $id = trim((string)($r['id_pac'] ?? ''));
            if ($id === '' || !isset($idx16[$id])) {
                $candidatos[] = $r;
            }
        }
        $totalCandidatos = count($candidatos);

        // --------------------------------------------------------
        // Opções para filtros (UF / BR) — combinando os dois bancos
        // --------------------------------------------------------
        $ufsSet = [];
        $brsSet = [];
        foreach (array_merge($base16, $base40) as $r) {
            $uf = $r['uf']     ?? $r['estado'] ?? '';
            $br = $r['br']     ?? '';
            if ($uf !== '') $ufsSet[$uf] = true;
            if ($br !== '') $brsSet[$br] = true;
        }
        $ufs = array_keys($ufsSet);
        sort($ufs);
        $brs = array_keys($brsSet);
        sort($brs);

        // --------------------------------------------------------
        // Dados para o header da página
        // --------------------------------------------------------
        $isAdmin   = in_array($usuario_nivel, ['desenvolvedor', 'admin']);
        $pendentes = 0;
        if ($isAdmin) {
            try {
                $stmt = $this->pdo->query("SELECT COUNT(*) FROM usuarios WHERE status = 'pendente'");
                $pendentes = (int)$stmt->fetchColumn();
            } catch (Exception $e) { $pendentes = 0; }
        }

        $total_setores = 0;
        if ($usuario_id) {
            try {
                $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM usuario_setor WHERE usuario_id = ?");
                $stmt->execute([$usuario_id]);
                $total_setores = (int)$stmt->fetchColumn();
            } catch (Exception $e) { $total_setores = 0; }
        }

        $setores_usuario = [];
        if ($usuario_id) {
            try {
                $stmt = $this->pdo->prepare("
                    SELECT s.nome
                    FROM setores s
                    INNER JOIN usuario_setor us ON s.id = us.setor_id
                    WHERE us.usuario_id = ?
                ");
                $stmt->execute([$usuario_id]);
                $setores_usuario = $stmt->fetchAll(PDO::FETCH_COLUMN);
            } catch (Exception $e) { $setores_usuario = []; }
        }

        // --------------------------------------------------------
        // Renderizar view
        // --------------------------------------------------------
        require_once APP_PATH . '/Views/comparativo_bancos_16x40.php';
    }
}