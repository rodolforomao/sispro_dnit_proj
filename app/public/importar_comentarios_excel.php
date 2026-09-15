<?php
require_once 'config.php';

// ============================================================
// CONFIGURAÇÕES
// ============================================================
$arquivoCSV = __DIR__ . '/carga.csv';
$usuarioPadraoId = 1;
$confirmarExclusao = isset($_GET['confirmar']) && $_GET['confirmar'] == 1;

// ============================================================
// VERIFICA ARQUIVO
// ============================================================
if (!file_exists($arquivoCSV)) {
    die("Arquivo CSV não encontrado: $arquivoCSV");
}

// ============================================================
// FUNÇÃO PARA DETECTAR SEPARADOR
// ============================================================
function detectarSeparador($arquivo) {
    $handle = fopen($arquivo, 'r');
    $linha = fgets($handle);
    fclose($handle);
    if (strpos($linha, ';') !== false) return ';';
    if (strpos($linha, ',') !== false) return ',';
    return ',';
}

$separador = detectarSeparador($arquivoCSV);
$delimitadorTexto = '"';

$handle = fopen($arquivoCSV, 'r');
if (!$handle) die("Erro ao abrir o arquivo.");

// ============================================================
// LÊ CABEÇALHO
// ============================================================
$cabecalho = fgetcsv($handle, 0, $separador, $delimitadorTexto);
if (!$cabecalho) die("Erro ao ler cabeçalho.");
$cabecalho[0] = preg_replace('/^\xEF\xBB\xBF/', '', $cabecalho[0]);
$cabecalho = array_map('trim', $cabecalho);

// ============================================================
// MAPEIA ÍNDICES
// ============================================================
$idx = [];
$mapa = [
    'equipe' => 'Equipe',
    'responsavel' => 'Responsável',
    'status' => 'Status',
    'processo' => 'Processo',
    'sei_criado_1' => '1 - SEI Criado',
    'assunto' => 'Assunto',
    'contrato' => 'Contrato',
    'uf' => 'UF',
    'br' => 'BR',
    'data_entrada' => 'Data Entrada',
    'comentarios' => 'Comentários',
    'providencia' => 'Providência',
    'prazo' => 'Prazo'
];

foreach ($mapa as $campo => $nome) {
    $found = false;
    foreach ($cabecalho as $i => $col) {
        if (strtolower(trim($col)) === strtolower($nome)) {
            $idx[$campo] = $i;
            $found = true;
            break;
        }
    }
    if (!$found) {
        $idx[$campo] = -1;
        echo "⚠️ Coluna '$nome' não encontrada. Será ignorada.<br>\n";
    }
}

if ($idx['processo'] === -1) die("❌ Coluna 'Processo' é obrigatória.");
if ($idx['comentarios'] === -1) die("❌ Coluna 'Comentários' é obrigatória.");

echo "✅ Colunas mapeadas com sucesso.<br>\n";

// ============================================================
// VERIFICA USUÁRIO PADRÃO
// ============================================================
$stmt = $pdo->prepare("SELECT id FROM usuarios WHERE id = ?");
$stmt->execute([$usuarioPadraoId]);
if (!$stmt->fetch()) {
    $stmt = $pdo->query("SELECT id FROM usuarios ORDER BY id LIMIT 1");
    $user = $stmt->fetch();
    if ($user) $usuarioPadraoId = $user['id'];
    else die("❌ Nenhum usuário encontrado.");
}
echo "Usuário padrão: ID $usuarioPadraoId<br>\n";

// ============================================================
// EXCLUI COMENTÁRIOS (somente se confirmado)
// ============================================================
if ($confirmarExclusao) {
    $pdo->query("DELETE FROM comentarios");
    echo "✅ Comentários antigos removidos.<br>\n";
} else {
    echo "⚠️ Para excluir os comentários existentes, adicione <strong>?confirmar=1</strong> na URL.<br>\n";
    echo "<a href=\"?confirmar=1\">Clique aqui para excluir e prosseguir</a><br><br>";
}

// ============================================================
// PREPARA STATEMENT DE INSERÇÃO
// ============================================================
$stmtInsere = $pdo->prepare("INSERT INTO comentarios (processo_id, usuario_id, comentario, created_at) VALUES (?, ?, ?, ?)");

// ============================================================
// LOOP PRINCIPAL
// ============================================================
$linha = 0;
$importados = 0;
$ignorados = 0;
$naoEncontrados = 0;

$pdo->beginTransaction();

try {
    while (($dados = fgetcsv($handle, 0, $separador, $delimitadorTexto)) !== false) {
        $linha++;
        if (count(array_filter($dados)) == 0) continue;

        $numeroProcesso = trim($dados[$idx['processo']] ?? '');
        $assunto = trim($dados[$idx['assunto']] ?? '');
        $responsavel = trim($dados[$idx['responsavel']] ?? '');
        $dataEntrada = trim($dados[$idx['data_entrada']] ?? '');
        $equipe = trim($dados[$idx['equipe']] ?? '');
        $status = trim($dados[$idx['status']] ?? '');
        $contrato = trim($dados[$idx['contrato']] ?? '');
        $uf = trim($dados[$idx['uf']] ?? '');
        $br = trim($dados[$idx['br']] ?? '');
        $prazo = trim($dados[$idx['prazo']] ?? '');
        $providencia = trim($dados[$idx['providencia']] ?? '');
        $seiCriado1 = trim($dados[$idx['sei_criado_1']] ?? '');
        $comentario = trim($dados[$idx['comentarios']] ?? '');

        if (empty($numeroProcesso) || empty($comentario)) {
            $ignorados++;
            continue;
        }

        // --- CONSTRUIR WHERE DINÂMICO ---
        $whereParts = [];
        $params = [];

        // Número do processo é obrigatório
        $whereParts[] = "p.numero_processo = ?";
        $params[] = $numeroProcesso;

        // Adicionar outros campos se disponíveis
        if (!empty($assunto)) {
            $whereParts[] = "p.assunto LIKE ?";
            $params[] = "%$assunto%";
        }
        if (!empty($responsavel)) {
            $whereParts[] = "r.nome = ?";
            $params[] = $responsavel;
        }
        if (!empty($dataEntrada)) {
            // Normalizar data
            $dataNormalizada = $dataEntrada;
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $dataEntrada)) {
                $parts = explode('/', $dataEntrada);
                $dataNormalizada = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
            }
            $whereParts[] = "p.data_entrada = ?";
            $params[] = $dataNormalizada;
        }
        if (!empty($equipe)) {
            $whereParts[] = "e.nome = ?";
            $params[] = $equipe;
        }
        if (!empty($status)) {
            $whereParts[] = "s.nome = ?";
            $params[] = $status;
        }
        if (!empty($contrato) && $contrato !== '-') {
            $whereParts[] = "c.numero = ?";
            $params[] = $contrato;
        }
        if (!empty($uf) && $uf !== '-') {
            $whereParts[] = "p.uf = ?";
            $params[] = $uf;
        }
        if (!empty($br) && $br !== '-') {
            $whereParts[] = "p.br = ?";
            $params[] = $br;
        }
        if (!empty($prazo)) {
            $prazoNormalizada = $prazo;
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $prazo)) {
                $parts = explode('/', $prazo);
                $prazoNormalizada = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
            }
            $whereParts[] = "p.prazo = ?";
            $params[] = $prazoNormalizada;
        }
        if (!empty($providencia)) {
            $whereParts[] = "p.providencia LIKE ?";
            $params[] = "%$providencia%";
        }
        if (!empty($seiCriado1)) {
            $whereParts[] = "p.sei_criado_1 = ?";
            $params[] = $seiCriado1;
        }

        // --- MONTA A QUERY ---
        $sql = "SELECT p.id 
                FROM processos p
                LEFT JOIN equipes e ON p.equipe_id = e.id
                LEFT JOIN responsaveis r ON p.responsavel_id = r.id
                LEFT JOIN status_processo s ON p.status_id = s.id
                LEFT JOIN contratos c ON p.contrato_id = c.id
                WHERE " . implode(" AND ", $whereParts);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        if (count($rows) === 0) {
            echo "Linha $linha: NENHUM processo encontrado para $numeroProcesso (assunto: $assunto).<br>\n";
            $naoEncontrados++;
            continue;
        }

        if (count($rows) > 1) {
            echo "Linha $linha: MÚLTIPLOS processos encontrados para $numeroProcesso (assunto: $assunto). Ignorando.<br>\n";
            $naoEncontrados++;
            continue;
        }

        $processoId = $rows[0]['id'];

        // Extrair data do comentário
        $dataExtraida = null;
        $comentarioLimpo = $comentario;
        if (preg_match('/(\d{2}\/\d{2}\/\d{4}(?:\s+\d{2}:\d{2})?)/', $comentario, $matches)) {
            $dataStr = $matches[1];
            $dataExtraida = DateTime::createFromFormat('d/m/Y H:i', $dataStr);
            if (!$dataExtraida) $dataExtraida = DateTime::createFromFormat('d/m/Y', $dataStr);
            $comentarioLimpo = trim(str_replace($dataStr, '', $comentario));
            $comentarioLimpo = trim(preg_replace('/\s*-\s*/', ' ', $comentarioLimpo));
        }
        if (!$dataExtraida) $dataExtraida = new DateTime();

        // Insere
        $stmtInsere->execute([
            $processoId,
            $usuarioPadraoId,
            $comentarioLimpo,
            $dataExtraida->format('Y-m-d H:i:s')
        ]);

        $importados++;
        echo "Linha $linha: Comentário importado para processo $numeroProcesso.<br>\n";
    }

    $pdo->commit();
    echo "\n---<br>\n";
    echo "✅ Importação concluída!<br>\n";
    echo "Comentários importados: $importados<br>\n";
    echo "Ignorados (sem comentário): $ignorados<br>\n";
    echo "Não encontrados (0 ou múltiplos): $naoEncontrados<br>\n";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "Erro na linha $linha: " . $e->getMessage() . "<br>\n";
}

fclose($handle);