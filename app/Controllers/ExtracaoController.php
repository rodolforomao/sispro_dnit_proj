<?php
// app/Controllers/ExtracaoController.php

class ExtracaoController
{
    private  $pdo;
    
    // Tabelas que NÃO devem aparecer na extração
    private  $tabelasIgnoradas = [
        'extracao_historico',
        'siac_import_jobs',
        'logs_auditoria',
        'mensagens',
        'comentarios'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        if (session_status() === PHP_SESSION_NONE) @session_start();
        $this->requireDesenvolvedor();
    }

    private function requireDesenvolvedor()
    {
        if (empty($_SESSION['usuario_id']) && empty($_SESSION['usuario_nivel'])) {
            header('Location: login'); exit;
        }
        $nivel = strtolower(trim((string)($_SESSION['usuario_nivel'] ?? '')));
        $isDev = !empty($_SESSION['is_desenvolvedor']) || in_array($nivel, ['desenvolvedor', 'developer', 'dev'], true);
        
        if (!$isDev) {
            http_response_code(403);
            die("<div style='font-family:sans-serif; text-align:center; margin-top:50px;'>
                    <h2>Acesso Negado (403)</h2>
                    <p>Esta página é restrita para Desenvolvedores.</p>
                    <a href='home_atlas'>Voltar para o início</a>
                 </div>");
        }
    }

    /**
     * Varre o banco e monta o catálogo dinâmico.
     */
    private function catalogoColunas(): array
    {
        $catalogo = [];
        
        $tabelasRaw = $this->pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($tabelasRaw as $tabela) {
            if (in_array($tabela, $this->tabelasIgnoradas)) continue;
            if (strpos($tabela, '_bkp_') !== false) continue;

            try {
                $cols = $this->pdo->query("SHOW COLUMNS FROM `$tabela`")->fetchAll(PDO::FETCH_ASSOC);
                $grupo = [];
                foreach ($cols as $col) {
                    $campo = $col['Field'];
                    
                    $format = 'text';
                    if (strpos($col['Type'], 'decimal') !== false || strpos($col['Type'], 'int') !== false) {
                        $format = 'money';
                    } elseif (strpos($col['Type'], 'date') !== false || strpos($col['Type'], 'timestamp') !== false) {
                        $format = 'date';
                    }

                    $grupo["{$tabela}___{$campo}"] = [
                        'tabela' => $tabela,
                        'coluna' => $campo,
                        'label'  => $campo,
                        'format' => $format
                    ];
                }
                $catalogo[strtoupper($tabela)] = $grupo;
            } catch (Throwable $e) { 
                continue; 
            }
        }
        return $catalogo;
    }

    public function index()
    {
        $catalogo = $this->catalogoColunas();
        require APP_PATH . '/Views/extracao.php';
    }

    public function gerar()
    {
        header('Content-Type: application/json; charset=utf-8');

        $colunasSelecionadas = $_POST['colunas'] ?? [];
        $filtros = $_POST['filtros'] ?? [];
        $nomePersonalizado = trim($_POST['nome_arquivo'] ?? 'extracao');
        $isDistinct = isset($_POST['distinct']) && $_POST['distinct'] === 'true'; // ⬅️ NOVO

        if (empty($colunasSelecionadas)) {
            echo json_encode(['ok' => false, 'erro' => 'Selecione pelo menos uma coluna.']); exit;
        }

        $catalogo = $this->catalogoColunas();
        $catalogoFlat = [];
        foreach ($catalogo as $grupo) {
            foreach ($grupo as $key => $info) {
                $catalogoFlat[$key] = $info;
            }
        }

        $colsSql = [];
        $colsFormat = [];
        
        // A PRIMEIRA tabela selecionada será a principal (FROM)
        $primeiraKey = $colunasSelecionadas[0];
        $tabelaBase = $catalogoFlat[$primeiraKey]['tabela'];
        $tabelasUsadas = [$tabelaBase];

        foreach ($colunasSelecionadas as $key) {
            if (isset($catalogoFlat[$key])) {
                $info = $catalogoFlat[$key];
                $colsSql[] = "`{$info['tabela']}`.`{$info['coluna']}` AS `{$key}`";
                $colsFormat[$key] = $info['format'];
                
                if (!in_array($info['tabela'], $tabelasUsadas)) {
                    $tabelasUsadas[] = $info['tabela'];
                }
            }
        }

        // Monta os JOINs Dinâmicos
        $from = "`{$tabelaBase}` AS `{$tabelaBase}`";
        $joins = "";
        
        foreach ($tabelasUsadas as $tbl) {
            if ($tbl === $tabelaBase) continue;
            
            $temInstrumento = $this->pdo->query("SHOW COLUMNS FROM `$tbl` LIKE 'instrumento'")->fetch();
            $temContrato = $this->pdo->query("SHOW COLUMNS FROM `$tbl` LIKE 'contrato'")->fetch();
            $temId = $this->pdo->query("SHOW COLUMNS FROM `$tbl` LIKE 'id'")->fetch();
            
            $temBaseInstrumento = $this->pdo->query("SHOW COLUMNS FROM `$tabelaBase` LIKE 'instrumento'")->fetch();
            $temBaseContrato = $this->pdo->query("SHOW COLUMNS FROM `$tabelaBase` LIKE 'contrato'")->fetch();
            $temBaseId = $this->pdo->query("SHOW COLUMNS FROM `$tabelaBase` LIKE 'id'")->fetch();

            if ($temInstrumento && $temBaseContrato) {
                $joins .= " LEFT JOIN `$tbl` ON `$tbl`.`instrumento` COLLATE utf8mb4_unicode_ci = `$tabelaBase`.`contrato` ";
            } elseif ($temContrato && $temBaseInstrumento) {
                $joins .= " LEFT JOIN `$tbl` ON `$tbl`.`contrato` COLLATE utf8mb4_unicode_ci = `$tabelaBase`.`instrumento` ";
            } elseif ($temInstrumento && $temBaseInstrumento) {
                $joins .= " LEFT JOIN `$tbl` ON `$tbl`.`instrumento` COLLATE utf8mb4_unicode_ci = `$tabelaBase`.`instrumento` ";
            } elseif ($temContrato && $temBaseContrato) {
                $joins .= " LEFT JOIN `$tbl` ON `$tbl`.`contrato` COLLATE utf8mb4_unicode_ci = `$tabelaBase`.`contrato` ";
            } elseif ($temId && $temBaseId) {
                $joins .= " LEFT JOIN `$tbl` ON `$tbl`.`id` = `$tabelaBase`.`id` ";
            }
        }

        // Filtros
        $where = ['1=1'];
        $params = [];
        if (!empty($filtros['acao']) && $this->pdo->query("SHOW COLUMNS FROM `$tabelaBase` LIKE 'acao'")->fetch()) {
            $where[] = "`$tabelaBase`.`acao` = :acao";
            $params[':acao'] = $filtros['acao'];
        }
        if (!empty($filtros['tipo']) && $this->pdo->query("SHOW COLUMNS FROM `$tabelaBase` LIKE 'tipo_atlas'")->fetch()) {
            $where[] = "`$tabelaBase`.`tipo_atlas` = :tipo";
            $params[':tipo'] = $filtros['tipo'];
        }
        if (!empty($filtros['estado'])) {
            if ($this->pdo->query("SHOW COLUMNS FROM `$tabelaBase` LIKE 'estado'")->fetch()) {
                $where[] = "`$tabelaBase`.`estado` = :estado";
            } elseif ($this->pdo->query("SHOW COLUMNS FROM `$tabelaBase` LIKE 'uf'")->fetch()) {
                $where[] = "`$tabelaBase`.`uf` = :estado";
            }
            $params[':estado'] = $filtros['estado'];
        }
        if (!empty($filtros['contrato'])) {
            if ($this->pdo->query("SHOW COLUMNS FROM `$tabelaBase` LIKE 'contrato'")->fetch()) {
                $where[] = "`$tabelaBase`.`contrato` = :contrato";
            } elseif ($this->pdo->query("SHOW COLUMNS FROM `$tabelaBase` LIKE 'instrumento'")->fetch()) {
                $where[] = "`$tabelaBase`.`instrumento` = :contrato";
            }
            $params[':contrato'] = $filtros['contrato'];
        }

        // ⬇️ MÁGICA DO DISTINCT AQUI ⬇️
        $selectPrefix = $isDistinct ? 'SELECT DISTINCT ' : 'SELECT ';
        $sql = $selectPrefix . implode(', ', $colsSql) . " 
                FROM $from 
                $joins 
                WHERE " . implode(' AND ', $where);
        // ⬆️ FIM ⬆️

        try {
            $st = $this->pdo->prepare($sql);
            $st->execute($params);
            $dados = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            echo json_encode(['ok' => false, 'erro' => 'Erro no SQL: ' . $e->getMessage()]); exit;
        }

        if (empty($dados)) {
            echo json_encode(['ok' => false, 'erro' => 'Nenhum dado encontrado com os filtros.']); exit;
        }

        // Limpa o nome do arquivo
        $nomeLimpo = preg_replace(
            ['/[áàâãä]/u', '/[ÁÀÂÃÄ]/u', '/[éèêë]/u', '/[ÉÈÊË]/u', '/[íìîï]/u', '/[ÍÌÎÏ]/u', '/[óòôõö]/u', '/[ÓÒÔÕÖ]/u', '/[úùûü]/u', '/[ÚÙÛÜ]/u', '/ç/u', '/Ç/u'],
            ['a', 'A', 'e', 'E', 'i', 'I', 'o', 'O', 'u', 'U', 'c', 'C'],
            $nomePersonalizado
        );
        $nomeLimpo = preg_replace('/\s+/', '_', $nomeLimpo);
        $nomeBase  = preg_replace('/[^a-zA-Z0-9_-]/', '', $nomeLimpo);
        if (empty($nomeBase)) $nomeBase = 'extracao';

        $nomeArquivo = $nomeBase . '_' . date('d-m-Y_His') . '.xls';
        $dir = APP_PATH . '/public/extratos/'; 
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        
        $html = '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
        $html .= '<head><meta charset="UTF-8"></head><body>';
        $html .= '<table border="1" style="font-family: Arial, sans-serif; font-size: 11pt;">';
        
        $html .= '<thead><tr style="background-color: #004a8f; color: #ffffff; font-weight: bold;">';
        foreach ($colunasSelecionadas as $key) {
            $nomeAmigavel = str_replace('_', ' ', $catalogoFlat[$key]['label']);
            $html .= '<th>' . mb_strtoupper($nomeAmigavel) . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        foreach ($dados as $row) {
            $html .= '<tr>';
            foreach ($colunasSelecionadas as $key) {
                $val = $row[$key] ?? '';
                $fmt = $colsFormat[$key] ?? 'text';

                if ($fmt === 'money') {
                    $val = number_format((float)$val, 2, ',', '.');
                    $html .= '<td style="mso-number-format:\'#,##0.00\'">' . $val . '</td>';
                } elseif ($fmt === 'date') {
                    $val = ($val && $val !== '0000-00-00' && $val !== '0000-00-00 00:00:00') ? date('d/m/Y', strtotime($val)) : '';
                    $html .= '<td style="mso-number-format:\'dd/mm/yyyy\'">' . $val . '</td>';
                } else {
                    $html .= '<td style="mso-number-format:\'@\'">' . htmlspecialchars($val) . '</td>';
                }
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table></body></html>';

        file_put_contents($dir . $nomeArquivo, $html);

        $this->pdo->prepare("INSERT INTO extracao_historico (usuario, nome_arquivo, parametros_json, total_linhas) VALUES (?, ?, ?, ?)")
                  ->execute([$_SESSION['usuario_nome'] ?? 'sistema', $nomeArquivo, json_encode(['filtros'=>$filtros, 'distinct'=>$isDistinct]), count($dados)]);

        echo json_encode(['ok' => true, 'arquivo' => $nomeArquivo]);
    }

    public function historico()
    {
        header('Content-Type: application/json; charset=utf-8');
        $st = $this->pdo->query("SELECT id, usuario, data_extracao, nome_arquivo, total_linhas FROM extracao_historico ORDER BY id DESC LIMIT 50");
        echo json_encode(['ok' => true, 'itens' => $st->fetchAll(PDO::FETCH_ASSOC)]);
    }

    public function baixar()
    {
        $arquivo = preg_replace('/[^a-zA-Z0-9_.-]/', '', $_GET['f'] ?? '');
        $filepath = APP_PATH . '/public/extratos/' . $arquivo;
        
        if (!is_file($filepath)) { die('Arquivo não encontrado.'); }
        
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $arquivo . '"');
        readfile($filepath);
        exit;
    }
}