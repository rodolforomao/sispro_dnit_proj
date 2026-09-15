<?php
// app/Models/GerencialModel.php

class GerencialModel {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Obtém os dados para o painel gerencial
     */
    public function getDadosGerencial() {
        // Datas de início e fim da semana atual (segunda a sexta)
        $inicioSemana = $this->inicioSemana();
        $fimSemana = $this->fimSemana();
        $hoje = (new DateTime())->format('Y-m-d');
        $amanha = (new DateTime('+1 day'))->format('Y-m-d');

        // 1. Recebidos essa semana
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM processos WHERE data_entrada BETWEEN ? AND ?");
        $stmt->execute([$inicioSemana, $fimSemana]);
        $recebidosSemana = (int) $stmt->fetchColumn();

        // 2. Em aberto (status != Assinado e != Concluído)
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM processos p 
                                   LEFT JOIN status_processo s ON p.status_id = s.id 
                                   WHERE s.nome NOT IN ('Assinado', 'Concluído') OR s.nome IS NULL");
        $emAberto = (int) $stmt->fetchColumn();

        // 3. Concluídos essa semana
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM processos p 
                                     LEFT JOIN status_processo s ON p.status_id = s.id 
                                     WHERE s.nome = 'Assinado' AND p.data_assinatura BETWEEN ? AND ?");
        $stmt->execute([$inicioSemana, $fimSemana]);
        $concluidosSemana = (int) $stmt->fetchColumn();

        // 4. A vencer hoje/amanhã
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM processos p 
                                     LEFT JOIN status_processo s ON p.status_id = s.id 
                                     WHERE s.nome NOT IN ('Assinado', 'Concluído') 
                                     AND (p.prazo = ? OR p.prazo = ?)");
        $stmt->execute([$hoje, $amanha]);
        $vencerHojeAmanha = (int) $stmt->fetchColumn();

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

        // 5. Dados para gráfico de pizza (por equipe)
        $equipeCount = [];
        foreach ($processos as $p) {
            if (in_array($p['status_nome'] ?? '', ['Assinado', 'Concluído'])) continue;
            $equipe = $p['equipe_nome'] ?? 'Sem equipe';
            if (!isset($equipeCount[$equipe])) $equipeCount[$equipe] = 0;
            $equipeCount[$equipe]++;
        }
        arsort($equipeCount);
        $pizzaLabels = array_keys($equipeCount);
        $pizzaData = array_values($equipeCount);

        // 6. Dados para gráfico de barras (por responsável)
        $responsavelCount = [];
        foreach ($processos as $p) {
            if (in_array($p['status_nome'] ?? '', ['Assinado', 'Concluído'])) continue;
            $resp = $p['responsavel_nome'] ?? 'Sem responsável';
            if (!isset($responsavelCount[$resp])) $responsavelCount[$resp] = 0;
            $responsavelCount[$resp]++;
        }
        arsort($responsavelCount);
        $barLabels = array_keys($responsavelCount);
        $barData = array_values($responsavelCount);
        $maxBar = max($barData);
        $suggestedMax = $maxBar > 0 ? ceil($maxBar * 1.25) : 5;

        // 7. Prazo médio por equipe
        $prazoProjetos = $this->calcularPrazoMedio($processos, 'PROJETO');
        $prazoAssessoriaCOAC = $this->calcularPrazoMedio($processos, 'ASSESSORIA COAC');
        $prazoAssessoriaCGCONT = $this->calcularPrazoMedio($processos, 'ASSESSORIA CGCONT');

        // 8. Tempo médio de assinatura
        $tempoAssinatura = $this->calcularTempoAssinatura($processos);

        // 9. Tempo médio de conclusão
        $tempoConclusao = $this->calcularTempoConclusao($processos);

        return [
            'recebidosSemana' => $recebidosSemana,
            'emAberto' => $emAberto,
            'concluidosSemana' => $concluidosSemana,
            'vencerHojeAmanha' => $vencerHojeAmanha,
            'pizzaLabels' => $pizzaLabels,
            'pizzaData' => $pizzaData,
            'barLabels' => $barLabels,
            'barData' => $barData,
            'suggestedMax' => $suggestedMax,
            'prazoProjetos' => $prazoProjetos,
            'prazoAssessoriaCOAC' => $prazoAssessoriaCOAC,
            'prazoAssessoriaCGCONT' => $prazoAssessoriaCGCONT,
            'tempoAssinatura' => $tempoAssinatura,
            'tempoConclusao' => $tempoConclusao
        ];
    }

    private function inicioSemana() {
        $hoje = new DateTime();
        $diaSemana = (int) $hoje->format('N');
        $diferenca = $diaSemana - 1;
        $inicio = clone $hoje;
        $inicio->modify("- $diferenca days");
        return $inicio->format('Y-m-d');
    }

    private function fimSemana() {
        $inicio = new DateTime($this->inicioSemana());
        $fim = clone $inicio;
        $fim->modify('+4 days');
        return $fim->format('Y-m-d');
    }

    private function calcularPrazoMedio($processos, $equipeNome) {
        $totais = 0;
        $count = 0;
        foreach ($processos as $p) {
            if (($p['status_nome'] ?? '') != 'Assinado') continue;
            if (($p['equipe_nome'] ?? '') != $equipeNome) continue;
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

    private function calcularTempoAssinatura($processos) {
        $totais = 0;
        $count = 0;
        foreach ($processos as $p) {
            if (($p['status_nome'] ?? '') != 'Assinado') continue;
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

    private function calcularTempoConclusao($processos) {
        $totais = 0;
        $count = 0;
        foreach ($processos as $p) {
            if (($p['status_nome'] ?? '') != 'Assinado') continue;
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
}