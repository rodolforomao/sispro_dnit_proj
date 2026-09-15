<?php
require_once 'config.php';

$arquivoCSV = __DIR__ . '/modelos.csv';

if (!file_exists($arquivoCSV)) {
    die("Arquivo modelos.csv não encontrado em: $arquivoCSV");
}

// Lê o arquivo com separador ponto-e-vírgula
$handle = fopen($arquivoCSV, 'r');
if (!$handle) {
    die("Erro ao abrir o arquivo.");
}

// Lê cabeçalho (primeira linha)
$cabecalho = fgetcsv($handle, 0, ';');
if (!$cabecalho) {
    die("Erro ao ler cabeçalho do CSV.");
}

// Remove BOM se existir
$cabecalho[0] = preg_replace('/^\xEF\xBB\xBF/', '', $cabecalho[0]);

// Mapear índices
$idxCategoria = array_search('categoria', $cabecalho);
$idxDescricao = array_search('descricao', $cabecalho);
$idxSei = array_search('sei', $cabecalho);
$idxData = array_search('data', $cabecalho);

if ($idxCategoria === false || $idxDescricao === false) {
    die("CSV deve ter colunas: categoria, descricao, sei, data");
}

// Limpar tabela antes de importar (opcional – remover se quiser manter duplicatas)
// $pdo->query("TRUNCATE TABLE modelos");

$stmt = $pdo->prepare("INSERT INTO modelos (categoria, descricao, sei, data) VALUES (?, ?, ?, ?)");
$count = 0;
$erros = [];

while (($dados = fgetcsv($handle, 0, ';')) !== false) {
    // Pular linhas vazias
    if (count($dados) < 2) continue;

    $categoria = trim($dados[$idxCategoria] ?? '');
    $descricao = trim($dados[$idxDescricao] ?? '');
    $sei = trim($dados[$idxSei] ?? '');
    $data = trim($dados[$idxData] ?? '');

    // Pular linhas sem categoria ou descrição
    if (empty($categoria) || empty($descricao)) continue;

    // Converter data de dd/mm/aaaa para aaaa-mm-dd
    if (!empty($data)) {
        $partes = explode('/', $data);
        if (count($partes) === 3) {
            $data = $partes[2] . '-' . $partes[1] . '-' . $partes[0];
        } else {
            // Se já estiver no formato Y-m-d, mantém
            $data = $data;
        }
    }

    try {
        $stmt->execute([
            $categoria,
            $descricao,
            $sei ?: null,
            $data ?: null
        ]);
        $count++;
    } catch (Exception $e) {
        $erros[] = "Erro ao inserir '$descricao': " . $e->getMessage();
    }
}

fclose($handle);

echo "<h2>Importação concluída!</h2>";
echo "<p>✅ $count modelos inseridos com sucesso.</p>";
if (!empty($erros)) {
    echo "<h4>⚠️ Erros:</h4><ul>";
    foreach ($erros as $e) {
        echo "<li>$e</li>";
    }
    echo "</ul>";
}
echo "<p><a href='modelos.php'>Ir para a página de modelos</a></p>";