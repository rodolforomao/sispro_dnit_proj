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
     * Exporta processos para CSV com os filtros atuais
     */
    public function csv() {
        // Verifica permissão (apenas usuários logados)
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: login');
            exit;
        }

        // Recupera os filtros da URL (iguais aos da listagem)
        $filtros = [
            'equipe'      => $_GET['equipe'] ?? '',
            'responsavel' => $_GET['responsavel'] ?? '',
            'status'      => $_GET['status'] ?? '',
            'processo'    => $_GET['processo'] ?? '',
            'contrato'    => $_GET['contrato'] ?? '',
            'tipo'        => $_GET['tipo'] ?? '',
            'assunto'     => $_GET['assunto'] ?? '',
            'uf'          => $_GET['uf'] ?? '',
            'br'          => $_GET['br'] ?? ''
        ];

        // Busca todos os processos sem paginação (limit = 999999)
        $dados = $this->model->getDadosHome($filtros, 1, 999999);
        $processos = $dados['processos'] ?? [];

        // Define o nome do arquivo
        $nomeArquivo = 'processos_' . date('Ymd_His') . '.csv';

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
        fputcsv($output, $cabecalho, ';'); // Usa ponto e vírgula como separador (melhor para Excel PT-BR)

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