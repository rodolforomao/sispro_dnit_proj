<?php
// ============================================================
// api_exportar_banco.php
// ⚠️ COLOCAR NA RAIZ PÚBLICA DE PRODUÇÃO
// Uso: GET /api_exportar_banco.php
//      Headers: X-Sync-Token: <TOKEN>
//      Opcional: ?tables=contratos_rdci,processos
//      Opcional: ?gzip=1
// ============================================================
require_once __DIR__ . '/app/public/config.php';

@ini_set('memory_limit', '1024M');
@set_time_limit(600);
header('Content-Type: application/json; charset=utf-8');

// ============================================================
// 🔑 TOKEN (mesmo do sincronizar_banco.php)
// ============================================================
define('SYNC_TOKEN', 'kzseb1XXFRO1CWL5FAPQ3mJi5KBI5viG5OsPWwv2xTI');

$token_recebido = isset($_SERVER['HTTP_X_SYNC_TOKEN']) ? $_SERVER['HTTP_X_SYNC_TOKEN'] : '';
if (!hash_equals(SYNC_TOKEN, (string)$token_recebido)) {
    http_response_code(401);
    echo json_encode(array('error' => 'Não autorizado'));
    exit;
}

// ============================================================
// 🚫 BLACKLIST (nunca exportar)
// ============================================================
$BLACKLIST = array(
    'logs_auditoria',   // logs são específicos de cada ambiente
);

$BLACKLIST_PATTERNS = array(
    '/_bkp_/i',
    '/_backup_/i',
    '/_preview_/i',
);

function tabelaPermitida($nome, $blacklist, $patterns)
{
    if (in_array($nome, $blacklist, true)) return false;
    foreach ($patterns as $p) {
        if (preg_match($p, $nome)) return false;
    }
    return true;
}

// ============================================================
// 📚 ORDEM PREFERENCIAL (respeita FKs no import)
// ============================================================
$ORDEM_PREFERIDA = array(
    'setores',
    'equipes',
    'responsaveis',
    'status_processo',
    'tipos',
    'usuarios',
    'contratos',
    'contratos_rdci',
    'processos',
    'comentarios',
    'mensagens',
    'rdci_notificacoes',
    'modelos',
    'supra_dataset_1_todos',
);

try {
    // ---- Filtro opcional de tabelas ----
    $apenasTabelas = array();
    if (!empty($_GET['tables'])) {
        $partes = explode(',', (string)$_GET['tables']);
        foreach ($partes as $parte) {
            $parte = trim($parte);
            if ($parte !== '') {
                $apenasTabelas[] = $parte;
            }
        }
    }

    $todas = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

    $permitidas = array();
    foreach ($todas as $t) {
        if (tabelaPermitida($t, $BLACKLIST, $BLACKLIST_PATTERNS)) {
            $permitidas[] = $t;
        }
    }

    usort($permitidas, function ($a, $b) use ($ORDEM_PREFERIDA) {
        $pa = array_search($a, $ORDEM_PREFERIDA, true);
        $pb = array_search($b, $ORDEM_PREFERIDA, true);
        $pa = ($pa === false) ? 9999 : $pa;
        $pb = ($pb === false) ? 9999 : $pb;
        if ($pa === $pb) {
            return strcmp($a, $b);
        }
        return ($pa < $pb) ? -1 : 1;
    });

    if (!empty($apenasTabelas)) {
        $permitidas = array_values(array_intersect($permitidas, $apenasTabelas));
    }

    $tabelas = array();
    $resumo  = array();

    foreach ($permitidas as $t) {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $t)) {
            continue;
        }

        // ---- Colunas ----
        $colunas = array();
        $colsStmt = $pdo->query('SHOW COLUMNS FROM `' . $t . '`');
        while ($c = $colsStmt->fetch(PDO::FETCH_ASSOC)) {
            $colunas[] = array(
                'nome'    => $c['Field'],
                'tipo'    => $c['Type'],
                'null'    => $c['Null'],
                'key'     => $c['Key'],
                'default' => $c['Default'],
                'extra'   => $c['Extra'],
            );
        }

        // ---- CREATE TABLE (inclui AUTO_INCREMENT na DDL) ----
        $createRow = $pdo->query('SHOW CREATE TABLE `' . $t . '`')->fetch(PDO::FETCH_ASSOC);
        $createSql = isset($createRow['Create Table']) ? $createRow['Create Table'] : null;

        // ---- AUTO_INCREMENT atual (fallback) ----
        $aiStmt = $pdo->prepare(
            'SELECT AUTO_INCREMENT FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $aiStmt->execute(array($t));
        $autoInc = (int)($aiStmt->fetchColumn() ?: 0);

        // ---- Dados ----
        $linhas = $pdo->query('SELECT * FROM `' . $t . '`')->fetchAll(PDO::FETCH_ASSOC);

        // ---- Checksum para validação no destino ----
        $jsonLinhas = json_encode($linhas, JSON_UNESCAPED_UNICODE);
        if ($jsonLinhas === false) {
            throw new Exception('Falha ao serializar ' . $t . ': ' . json_last_error_msg());
        }
        $checksum = hash('sha256', $jsonLinhas);

        $tabelas[$t] = array(
            'colunas'        => $colunas,
            'create_sql'     => $createSql,
            'auto_increment' => $autoInc,
            'total'          => count($linhas),
            'checksum'       => $checksum,
            'linhas'         => $linhas,
        );

        $resumo[] = array('tabela' => $t, 'linhas' => count($linhas));
    }

    $payload = array(
        'success'       => true,
        'versao_api'    => '2.0',
        'gerado_em'     => date('c'),
        'banco'         => $pdo->query('SELECT DATABASE()')->fetchColumn(),
        'mysql_version' => $pdo->query('SELECT VERSION()')->fetchColumn(),
        'total_tabelas' => count($tabelas),
        'ordem'         => array_keys($tabelas),
        'resumo'        => $resumo,
        'tabelas'       => $tabelas,
        'excluidas'     => array_values(array_diff($todas, $permitidas)),
    );

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        throw new Exception('Falha ao gerar JSON: ' . json_last_error_msg());
    }

    if (!empty($_GET['gzip']) && function_exists('gzencode')) {
        header('Content-Encoding: gzip');
        echo gzencode($json, 6);
    } else {
        echo $json;
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(array('error' => 'Erro: ' . $e->getMessage()));
}
