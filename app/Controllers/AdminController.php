<?php
// app/Controllers/AdminController.php

require_once APP_PATH . '/Models/UsuarioModel.php';

class AdminController {
    private $pdo;
    private $model;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->model = new UsuarioModel($pdo);
    }

    public function index() {
        // Verifica permissão
        if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['usuario_nivel'], ['desenvolvedor', 'admin', 'admin_premium'])) {
            header('Location: index');
            exit;
        }

        // Processa ações POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Impede que o usuário exclua a si mesmo
            if (($_POST['action'] ?? '') === 'excluir' && ($_POST['id'] ?? 0) == $_SESSION['usuario_id']) {
                header('Location: admin?erro=1');
                exit;
            }
            $this->model->processarAcaoAdmin($_POST);
            header('Location: admin?sucesso=1');
            exit;
        }

        // Busca dados
        $pendentes = $this->model->getPendentes();
        $todos = $this->model->getTodosUsuarios();

        // 🔥 GARANTIA EXTREMA DE UNICIDADE POR ID
        $unicos = [];
        foreach ($todos as $u) {
            $unicos[$u['id']] = $u;
        }
        $todos = array_values($unicos);

        // Busca setores e equipes
        $setores = $this->pdo->query("SELECT * FROM setores WHERE ativo = 1 ORDER BY nome")->fetchAll();
        $equipes = $this->pdo->query("SELECT id, nome FROM equipes ORDER BY nome")->fetchAll();

        // Para cada usuário, carrega seus setores
        foreach ($todos as &$u) {
            $stmt = $this->pdo->prepare("SELECT setor_id FROM usuario_setor WHERE usuario_id = ?");
            $stmt->execute([$u['id']]);
            $u['setores'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
        unset($u);

        $usuario_nivel = $_SESSION['usuario_nivel'];
        $usuario_nome = $_SESSION['usuario_nome'] ?? 'Usuário';

        $niveis_map = [
            'desenvolvedor' => 'Desenvolvedor',
            'admin' => 'Administrador',
            'usuario' => 'Usuário',
            'leitor' => 'Leitor'
        ];
        $status_map = [
            'ativo' => 'Ativo',
            'inativo' => 'Inativo',
            'pendente' => 'Pendente'
        ];

        // Carrega a view
        require_once APP_PATH . '/Views/admin.php';
    }

    public function gerencial() {
        // Verifica permissão
        if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['usuario_nivel'], ['desenvolvedor', 'admin', 'admin_premium'])) {
            header('Location: index');
            exit;
        }

        $usuario_nome = $_SESSION['usuario_nome'] ?? 'Usuário';

        // Funções auxiliares
        function inicioSemana() {
            $hoje = new DateTime();
            $diaSemana = (int)$hoje->format('N');
            $diferenca = $diaSemana - 1;
            $inicio = clone $hoje;
            $inicio->modify("- $diferenca days");
            return $inicio->format('Y-m-d');
        }

        function fimSemana() {
            $inicio = new DateTime(inicioSemana());
            $fim = clone $inicio;
            $fim->modify('+4 days');
            return $fim->format('Y-m-d');
        }

        $inicioSemanaStr = inicioSemana();
        $fimSemanaStr = fimSemana();

        // --- 1. Recebidos essa semana ---
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM processos WHERE data_entrada BETWEEN ? AND ?");
        $stmt->execute([$inicioSemanaStr, $fimSemanaStr]);
        $recebidosSemana = (int)$stmt->fetchColumn();

        // --- 2. Em aberto ---
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM processos p 
                                   LEFT JOIN status_processo s ON p.status_id = s.id 
                                   WHERE s.nome NOT IN ('Assinado', 'Concluído') OR s.nome IS NULL");
        $emAberto = (int)$stmt->fetchColumn();

        // --- 3. Concluídos essa semana ---
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM processos p 
                                     LEFT JOIN status_processo s ON p.status_id = s.id 
                                     WHERE s.nome = 'Assinado' AND p.data_assinatura BETWEEN ? AND ?");
        $stmt->execute([$inicioSemanaStr, $fimSemanaStr]);
        $concluidosSemana = (int)$stmt->fetchColumn();

        // --- 4. A vencer hoje/amanhã ---
        $hoje = (new DateTime())->format('Y-m-d');
        $amanha = (new DateTime('+1 day'))->format('Y-m-d');
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM processos p 
                                     LEFT JOIN status_processo s ON p.status_id = s.id 
                                     WHERE s.nome NOT IN ('Assinado', 'Concluído') 
                                     AND (p.prazo = ? OR p.prazo = ?)");
        $stmt->execute([$hoje, $amanha]);
        $vencerHojeAmanha = (int)$stmt->fetchColumn();

        // --- Buscar todos os processos para os demais cálculos ---
        $sql = "SELECT p.*, 
                       e.nome AS equipe_nome,
                       r.nome AS responsavel_nome,
                       s.nome AS status_nome
                FROM processos p
                LEFT JOIN equipes e ON p.equipe_id = e.id
                LEFT JOIN responsaveis r ON p.responsavel_id = r.id
                LEFT JOIN status_processo s ON p.status_id = s.id";
        $processos = $this->pdo->query($sql)->fetchAll();

        // --- 5. Dados para gráfico de pizza (por equipe) ---
        $equipeCount = [];
        foreach ($processos as $p) {
            if ($p['status_nome'] == 'Assinado' || $p['status_nome'] == 'Concluído') continue;
            $equipe = $p['equipe_nome'] ?? 'Sem equipe';
            if (!isset($equipeCount[$equipe])) $equipeCount[$equipe] = 0;
            $equipeCount[$equipe]++;
        }
        arsort($equipeCount);
        $pizzaLabels = array_keys($equipeCount);
        $pizzaData = array_values($equipeCount);

        // --- 6. Dados para gráfico de barras (por responsável) ---
        $responsavelCount = [];
        foreach ($processos as $p) {
            if ($p['status_nome'] == 'Assinado' || $p['status_nome'] == 'Concluído') continue;
            $resp = $p['responsavel_nome'] ?? 'Sem responsável';
            if (!isset($responsavelCount[$resp])) $responsavelCount[$resp] = 0;
            $responsavelCount[$resp]++;
        }
        arsort($responsavelCount);
        $barLabels = array_keys($responsavelCount);
        $barData = array_values($responsavelCount);

        // --- 7. Prazo médio por equipe ---
        function calcularPrazoMedio($processos, $equipeNome) {
            $totais = 0;
            $count = 0;
            foreach ($processos as $p) {
                if ($p['status_nome'] != 'Assinado') continue;
                if ($p['equipe_nome'] != $equipeNome) continue;
                if (empty($p['data_entrada']) || empty($p['data_revisao'])) continue;
                $entrada = new DateTime($p['data_entrada']);
                $revisao = new DateTime($p['data_revisao']);
                $diff = $entrada->diff($revisao)->days;
                $totais += $diff;
                $count++;
            }
            if ($count == 0) return '-';
            return ceil($totais / $count) . ' dias';
        }

        $prazoProjetos = calcularPrazoMedio($processos, 'PROJETO');
        $prazoAssessoriaCOAC = calcularPrazoMedio($processos, 'ASSESSORIA COAC');
        $prazoAssessoriaCGCONT = calcularPrazoMedio($processos, 'ASSESSORIA CGCONT');

        // --- 8. Tempo médio de assinatura ---
        function calcularTempoAssinatura($processos) {
            $totais = 0;
            $count = 0;
            foreach ($processos as $p) {
                if ($p['status_nome'] != 'Assinado') continue;
                if (empty($p['data_revisao']) || empty($p['data_assinatura'])) continue;
                $revisao = new DateTime($p['data_revisao']);
                $assinatura = new DateTime($p['data_assinatura']);
                $diff = $revisao->diff($assinatura)->days;
                $totais += $diff;
                $count++;
            }
            if ($count == 0) return '-';
            return ceil($totais / $count) . ' dias';
        }
        $tempoAssinatura = calcularTempoAssinatura($processos);

        // --- 9. Tempo médio de conclusão ---
        function calcularTempoConclusao($processos) {
            $totais = 0;
            $count = 0;
            foreach ($processos as $p) {
                if ($p['status_nome'] != 'Assinado') continue;
                if (empty($p['data_entrada']) || empty($p['data_assinatura'])) continue;
                $entrada = new DateTime($p['data_entrada']);
                $assinatura = new DateTime($p['data_assinatura']);
                $diff = $entrada->diff($assinatura)->days;
                $totais += $diff;
                $count++;
            }
            if ($count == 0) return '-';
            return ceil($totais / $count) . ' dias';
        }
        $tempoConclusao = calcularTempoConclusao($processos);

        // Calcular o máximo para o eixo Y do gráfico de barras
        $maxBar = max($barData);
        $suggestedMax = $maxBar > 0 ? ceil($maxBar * 1.25) : 5;

        // Carrega a View com os dados
        require_once APP_PATH . '/Views/gerencial.php';
    }
}