<?php
// ============================================================
// public/_relatorio_helper.php
// Helper compartilhado pelos endpoints:
//   - importar_dados_contratos_isa.php
//   - importar_empenhos_isa.php
// (e, opcionalmente, importar_siac_isabella.php)
//
// Responsável por:
//   • listar/último job
//   • carregar meta/status/relatorio
//   • reconstruir relatório quando relatorio.json ausente
// ============================================================

if (!function_exists('_jobs_dir')) {

    /**
     * Retorna o diretório de jobs de cada tipo.
     * Testa vários nomes candidatos — devolve o primeiro que existir.
     */
    function _jobs_dir(string $tipo): string
    {
        $candidatos = [
            'siac'     => ['importar_siac_jobs', 'siac_jobs', 'jobs_siac'],
            'dados'    => ['importar_dados_contratos_jobs', 'importar_dados_jobs',
                           'dados_contratos_jobs', 'dados_jobs'],
            'empenhos' => ['importar_empenhos_jobs', 'empenhos_jobs', 'importar_empenho_jobs'],
        ];
        $nomes = $candidatos[$tipo] ?? ['importar_' . $tipo . '_jobs'];

        foreach ($nomes as $n) {
            $p = __DIR__ . '/' . $n;
            if (is_dir($p)) return $p;
        }
        return __DIR__ . '/' . $nomes[0];
    }

    /** Lê um JSON, tolera arquivo ausente/inválido. */
    function _read_json(string $file): array
    {
        if (!is_file($file)) return [];
        $d = @json_decode(@file_get_contents($file), true);
        return is_array($d) ? $d : [];
    }

    /** Lista todos os jobs ordenados por mtime desc. */
    function _jobs_listar(string $tipo): array
    {
        $base = _jobs_dir($tipo);
        if (!is_dir($base)) return [];

        $out = [];
        foreach (glob($base . '/*/meta.json') ?: [] as $metaFile) {
            $dir  = dirname($metaFile);
            $meta = _read_json($metaFile);
            $meta['_dir']   = $dir;
            $meta['_job']   = basename($dir);
            $meta['_mtime'] = @filemtime($metaFile) ?: 0;
            $out[] = $meta;
        }
        usort($out, fn($a, $b) => $b['_mtime'] - $a['_mtime']);
        return $out;
    }

    /** Retorna o job mais recente do tipo (ou null). */
    function _jobs_ultimo(string $tipo): ?array
    {
        return _jobs_listar($tipo)[0] ?? null;
    }

    /** Carrega um job específico (meta + status + relatorio). */
    function _jobs_carregar(string $tipo, string $jobId): ?array
    {
        if (!preg_match('/^[a-z]+_[0-9]{8}_[0-9]{6}_[a-z0-9]+$/i', $jobId)) return null;
        $dir = _jobs_dir($tipo) . '/' . $jobId;
        if (!is_dir($dir)) return null;

        return [
            'job_id'    => $jobId,
            'dir'       => $dir,
            'meta'      => _read_json($dir . '/meta.json'),
            'status'    => _read_json($dir . '/status.json'),
            'relatorio' => _read_json($dir . '/relatorio.json'),
        ];
    }

    /**
     * Reconstrói o relatório quando `relatorio.json` não existe (jobs antigos).
     *
     * Estratégia:
     *   1) Tenta extrair linhas [OK]/[ERRO]/[IGNORADO] do log.txt.
     *   2) Se não achou nada e o job foi concluído, marca todos os contratos
     *      de `contratos.json` como OK (com medicoes = 0).
     */
    function _jobs_montar_relatorio_fallback(array $job): array
    {
        $dir    = $job['dir'];
        $meta   = $job['meta'];
        $status = $job['status'];

        $resumo = ['ok' => 0, 'ignorado' => 0, 'erro' => 0, 'medicoes' => 0];
        $itens  = [];

        // ---------- 1) Tentativa: parsear log.txt ----------
        $logFile = $dir . '/log.txt';
        $log = is_file($logFile) ? (file_get_contents($logFile) ?: '') : '';

        foreach (preg_split('/\r?\n/', $log) as $linha) {
            if (preg_match('/\[(OK|IGN|IGNORADO|ERRO)\]\s+([0-9][0-9\s]*\/\d{4})\s+\(([A-Z]{2})\)\s*(?:[—\-:]\s*(.+))?/u', $linha, $m)) {
                $st = $m[1] === 'OK' ? 'OK' : ($m[1] === 'ERRO' ? 'ERRO' : 'IGNORADO');
                $motivo = trim($m[4] ?? '');
                $meds = 0;
                if (preg_match('/(\d+)\s+mediç/i', $motivo, $mm)) $meds = (int)$mm[1];

                $itens[] = [
                    'contrato'    => trim($m[2]),
                    'estado'      => $m[3],
                    'status'      => $st,
                    'medicoes'    => $meds,
                    'duracao_seg' => 0,
                    'motivo'      => $motivo,
                ];

                if ($st === 'OK')       { $resumo['ok']++;       $resumo['medicoes'] += $meds; }
                elseif ($st === 'ERRO') $resumo['erro']++;
                else                    $resumo['ignorado']++;
            }
        }

        if (!empty($itens)) {
            return ['resumo' => $resumo, 'itens' => $itens];
        }

        // ---------- 2) Fallback: contratos.json + status ----------
        $total = (int)($meta['total_contratos'] ?? ($status['total'] ?? 0));
        $proc  = (int)($status['processados'] ?? 0);

        if ($total > 0 && $proc >= $total) {
            $contratos = _read_json($dir . '/contratos.json');
            foreach ($contratos as $c) {
                $itens[] = [
                    'contrato'    => $c['Contrato']         ?? $c['contrato']         ?? $c['instrumento']    ?? '?',
                    'estado'      => $c['Estado']           ?? $c['estado']           ?? $c['unidade_manual'] ?? '?',
                    'status'      => 'OK',
                    'medicoes'    => 0,
                    'duracao_seg' => 0,
                    'motivo'      => 'Reconstruído (relatorio.json ausente)',
                ];
            }
            $resumo['ok'] = count($itens);
        }

        return ['resumo' => $resumo, 'itens' => $itens];
    }
}