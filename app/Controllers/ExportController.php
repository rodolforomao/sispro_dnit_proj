<?php
// app/Controllers/ExportController.php

require_once APP_PATH . '/Models/ProcessoModel.php';

class ExportController {
    private $pdo;
    private $model;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->model = new ProcessoModel($pdo);
    }

    /**
     * Exporta processos para CSV com os filtros atuais ou todos
     */
    public function csv() {
        // Verifica permissão (apenas usuários logados)
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: login');
            exit;
        }

        // ⬇️ VALIDAÇÃO DA FLAG "TODOS" ⬇️
        $isTodos = isset($_GET['todos']) && $_GET['todos'] == '1';

        if ($isTodos) {
            // Se for "Todos", faz uma consulta direta no banco ignorando o Model
            // e ignorando qualquer filtro padrão de usuário.
            $sql = "SELECT p.*, 
                           e.nome AS equipe_nome,
                           r.nome AS responsavel_nome,
                           s.nome AS status_nome,
                           t.nome AS tipo_nome,
                           c.instrumento AS contrato_num
                    FROM processos p
                    LEFT JOIN equipes e ON p.equipe_id = e.id
                    LEFT JOIN responsaveis r ON p.responsavel_id = r.id
                    LEFT JOIN status_processo s ON p.status_id = s.id
                    LEFT JOIN tipos t ON p.tipo_id = t.id
                    LEFT JOIN contratos_rdci c ON p.contrato_id = c.id
                    ORDER BY p.id DESC";
            
            try {
                $stmt = $this->pdo->query($sql);
                $processos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $e) {
                $processos = [];
                error_log("Erro ao exportar todos: " . $e->getMessage());
            }
        } else {
            // Recupera os filtros da URL normalmente (com providencia)
            $filtros = [
                'equipe'      => $_GET['equipe'] ?? '',
                'responsavel' => $_GET['responsavel'] ?? '',
                'status'      => $_GET['status'] ?? '',
                'processo'    => $_GET['processo'] ?? '',
                'contrato'    => $_GET['contrato'] ?? '',
                'tipo'        => $_GET['tipo'] ?? '',
                'assunto'     => $_GET['assunto'] ?? '',
                'uf'          => $_GET['uf'] ?? '',
                'br'          => $_GET['br'] ?? '',
                'providencia' => $_GET['providencia'] ?? ''
            ];

            // Busca todos os processos com filtro usando o Model original
            $dados = $this->model->getDadosHome($filtros, 1, 999999);
            $processos = $dados['processos'] ?? [];
        }
        // ⬆️ FIM DA VALIDAÇÃO ⬆️

        // Define o nome do arquivo
        $sufixo = $isTodos ? 'todos_' : 'filtrados_';
        $nomeArquivo = 'processos_' . $sufixo . date('Ymd_His') . '.csv';

        // Cabeçalhos HTTP para download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Abre a saída
        $output = fopen('php://output', 'w');

        // Adiciona BOM para UTF-8 (facilita abertura no Excel)
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Cabeçalhos das colunas
        $cabecalho = [
            'ID',
            'Nº Processo',
            'Assunto',
            'Equipe',
            'Responsável',
            'Status',
            'Tipo',
            'Contrato',
            'UF',
            'BR',
            'Data Entrada',
            'Prazo',
            'Data Revisão',
            'Data Assinatura',
            'SEI Recebido',
            'SEI Criado 1',
            'SEI Criado 2',
            'SEI Criado 3',
            'Providência',
            'Observações',
            'Tem Prazo',
            'Cadastrado SIMA',
            'Criado por'
        ];
        fputcsv($output, $cabecalho, ';');

        // Dados
        foreach ($processos as $p) {
            $linha = [
                $p['id'] ?? '',
                $p['numero_processo'] ?? '',
                $p['assunto'] ?? '',
                $p['equipe_nome'] ?? '',
                $p['responsavel_nome'] ?? '',
                $p['status_nome'] ?? '',
                $p['tipo_nome'] ?? '',
                $p['contrato_num'] ?? '',
                $p['uf'] ?? '',
                $p['br'] ?? '',
                $p['data_entrada'] ?? '',
                $p['prazo'] ?? '',
                $p['data_revisao'] ?? '',
                $p['data_assinatura'] ?? '',
                $p['sei_recebido'] ?? '',
                $p['sei_criado_1'] ?? '',
                $p['sei_criado_2'] ?? '',
                $p['sei_criado_3'] ?? '',
                $p['providencia'] ?? '',
                $p['observacoes'] ?? '',
                isset($p['tem_prazo']) && $p['tem_prazo'] ? 'Sim' : 'Não',
                isset($p['cadastrado_sima']) && $p['cadastrado_sima'] ? 'Sim' : 'Não',
                $p['criado_por'] ?? ''
            ];
            fputcsv($output, $linha, ';');
        }

        fclose($output);
        exit;
    }
}