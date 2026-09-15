<?php
require_once 'config.php';

// Verifica conexão
if (!$pdo) {
    die("Erro: não foi possível conectar ao banco de dados.");
}

// Caminho do arquivo CSV
$arquivoCSV = __DIR__ . '/carga.csv';

if (!file_exists($arquivoCSV)) {
    die("Arquivo CSV não encontrado em: $arquivoCSV");
}

// Detecta separador automaticamente (vírgula ou ponto-e-vírgula)
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
if (!$handle) {
    die("Não foi possível abrir o arquivo.");
}

// Lê cabeçalho
$cabecalho = fgetcsv($handle, 0, $separador, $delimitadorTexto);
if (!$cabecalho) {
    die("Erro ao ler cabeçalho do CSV.");
}

// Remove BOM se existir
$cabecalho[0] = preg_replace('/^\xEF\xBB\xBF/', '', $cabecalho[0]);

// Normaliza cabeçalho (trim)
$cabecalho = array_map('trim', $cabecalho);

echo "Cabeçalho detectado: " . implode(' | ', $cabecalho) . "\n";

// Função para obter índice de coluna (case-insensitive)
function indiceColuna($cabecalho, $nome) {
    $nomeLower = strtolower(trim($nome));
    foreach ($cabecalho as $i => $col) {
        if (strtolower(trim($col)) === $nomeLower) {
            return $i;
        }
    }
    return -1;
}

// Mapeamento dos nomes esperados (ajuste conforme necessário)
$mapa = [
    'equipe' => 'Equipe',
    'processo' => 'Processo',
    'data_entrada' => 'Data Entrada',
    'status' => 'Status',
    'responsavel' => 'Responsável',
    'prazo' => 'Prazo',
    'uf' => 'UF',
    'contrato' => 'Contrato',
    'br' => 'BR',
    'tipo' => 'Tipo',
    'assunto' => 'Assunto',
    'providencia' => 'Providência',
    'sei_recebido' => 'SEI Recebido',
    'sei_criado_1' => '1 - SEI Criado',
    'sei_criado_2' => '2 - SEI Criado',
    'sei_criado_3' => '3 - SEI Criado',
    'data_revisao' => 'Data de Revisão',
    'observacoes' => 'Observações',
    'data_assinatura' => 'Data de Assinatura',
    'criado_por' => 'Criado por',
    'data_criacao' => 'Data Criação',
    'cadastrado_sima' => 'Cadastrado no SIMA?',
    'tem_prazo' => 'Tem Prazo',
    'comentarios' => 'Comentários'
];

// Constroi array com índices
$indices = [];
foreach ($mapa as $campo => $nomeEsperado) {
    $idx = indiceColuna($cabecalho, $nomeEsperado);
    if ($idx === -1) {
        echo "Aviso: coluna '$nomeEsperado' não encontrada no cabeçalho. Será ignorada.\n";
    }
    $indices[$campo] = $idx;
}

// Funções auxiliares
function limpar($valor) {
    if ($valor === null) return null;
    $valor = trim($valor);
    if ($valor === '' || $valor === '-') return null;
    return $valor;
}

function converterData($data) {
    if (empty($data)) return null;
    $data = trim($data);
    // Formato "2025-02-14 00:00:00"
    if (preg_match('/^\d{4}-\d{2}-\d{2}/', $data)) {
        return substr($data, 0, 10);
    }
    // Formato "25/06/2025"
    if (preg_match('/^\d{2}\/\d{2}\/\d{4}/', $data)) {
        $partes = explode(' ', $data)[0];
        $d = DateTime::createFromFormat('d/m/Y', $partes);
        if ($d) return $d->format('Y-m-d');
    }
    // Tentar com DateTime
    try {
        $d = new DateTime($data);
        return $d->format('Y-m-d');
    } catch (Exception $e) {
        return null;
    }
}

function getOrCreate($pdo, $tabela, $campo, $valor) {
    if (empty($valor)) return null;
    $stmt = $pdo->prepare("SELECT id FROM $tabela WHERE $campo = ?");
    $stmt->execute([$valor]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        return $row['id'];
    } else {
        $stmt = $pdo->prepare("INSERT INTO $tabela ($campo) VALUES (?)");
        $stmt->execute([$valor]);
        return $pdo->lastInsertId();
    }
}

function getUsuarioId($pdo, $nome) {
    if (empty($nome)) return null;
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE nome = ?");
    $stmt->execute([$nome]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        return $row['id'];
    } else {
        // Gera email a partir do nome (remove espaços e caracteres especiais)
        $email = strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', $nome))) . '@temp.com';
        // Verifica se email já existe (evita colisão)
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $email = $email . rand(1, 999);
        }
        $senhaHash = password_hash('123456', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, nivel, status, senha) VALUES (?, ?, 'usuario', 'ativo', ?)");
        $stmt->execute([$nome, $email, $senhaHash]);
        return $pdo->lastInsertId();
    }
}

$linha = 0;
$importados = 0;
$ignorados = 0;

$pdo->beginTransaction();

try {
    while (($dados = fgetcsv($handle, 0, $separador, $delimitadorTexto)) !== false) {
        $linha++;
        // Pula linhas vazias
        if (count(array_filter($dados)) == 0) continue;

        $numeroProcesso = limpar($dados[$indices['processo']] ?? '');
        if (empty($numeroProcesso)) {
            echo "Linha $linha: Número do processo vazio. Ignorando.\n";
            $ignorados++;
            continue;
        }

        // *** REMOVIDA a verificação de duplicata – permite todas as linhas ***

        // Coleta valores
        $equipeNome = limpar($dados[$indices['equipe']] ?? '');
        $statusNome = limpar($dados[$indices['status']] ?? '');
        $responsavelNome = limpar($dados[$indices['responsavel']] ?? '');
        $tipoNome = limpar($dados[$indices['tipo']] ?? '');
        $uf = limpar($dados[$indices['uf']] ?? '');
        $br = limpar($dados[$indices['br']] ?? '');
        $contratoNumero = limpar($dados[$indices['contrato']] ?? '');
        $assunto = limpar($dados[$indices['assunto']] ?? '');
        $providencia = limpar($dados[$indices['providencia']] ?? '');
        $seiRecebido = limpar($dados[$indices['sei_recebido']] ?? '');
        $seiCriado1 = limpar($dados[$indices['sei_criado_1']] ?? '');
        $seiCriado2 = limpar($dados[$indices['sei_criado_2']] ?? '');
        $seiCriado3 = limpar($dados[$indices['sei_criado_3']] ?? '');
        $dataEntrada = converterData($dados[$indices['data_entrada']] ?? '');
        $prazo = converterData($dados[$indices['prazo']] ?? '');
        $dataRevisao = converterData($dados[$indices['data_revisao']] ?? '');
        $dataAssinatura = converterData($dados[$indices['data_assinatura']] ?? '');
        $observacoes = limpar($dados[$indices['observacoes']] ?? '');
        $criadoPorNome = limpar($dados[$indices['criado_por']] ?? '');
        $cadastradoSima = limpar($dados[$indices['cadastrado_sima']] ?? '');
        $temPrazo = limpar($dados[$indices['tem_prazo']] ?? '');
        $comentarios = limpar($dados[$indices['comentarios']] ?? '');

        if (!empty($comentarios)) {
            $observacoes .= ($observacoes ? " | " : "") . "Comentários: " . $comentarios;
        }

        // Contrato
        $contratoId = null;
        if (!empty($contratoNumero) && $contratoNumero !== '-') {
            $stmt = $pdo->prepare("SELECT id FROM contratos WHERE numero = ?");
            $stmt->execute([$contratoNumero]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $contratoId = $row['id'];
                // Atualiza UF e BR se estiverem vazios
                if (!empty($uf)) {
                    $stmt = $pdo->prepare("UPDATE contratos SET uf = COALESCE(uf, ?) WHERE id = ?");
                    $stmt->execute([$uf, $contratoId]);
                }
                if (!empty($br)) {
                    $stmt = $pdo->prepare("UPDATE contratos SET br = COALESCE(br, ?) WHERE id = ?");
                    $stmt->execute([$br, $contratoId]);
                }
            } else {
                $stmt = $pdo->prepare("INSERT INTO contratos (numero, uf, br) VALUES (?, ?, ?)");
                $stmt->execute([$contratoNumero, $uf, $br]);
                $contratoId = $pdo->lastInsertId();
            }
        }

        // Equipe
        $equipeId = !empty($equipeNome) ? getOrCreate($pdo, 'equipes', 'nome', $equipeNome) : null;

        // Responsável
        $responsavelId = !empty($responsavelNome) ? getOrCreate($pdo, 'responsaveis', 'nome', $responsavelNome) : null;

        // Status
        $statusId = !empty($statusNome) ? getOrCreate($pdo, 'status_processo', 'nome', $statusNome) : null;

        // Tipo
        $tipoId = !empty($tipoNome) ? getOrCreate($pdo, 'tipos', 'nome', $tipoNome) : null;

        // Usuário criador
        $criadoPorId = !empty($criadoPorNome) ? getUsuarioId($pdo, $criadoPorNome) : null;

        // Booleans
        $cadastradoSimaBool = (strtolower($cadastradoSima) === 'sim' || $cadastradoSima === '1') ? 1 : 0;
        $temPrazoBool = (strtolower($temPrazo) === 'sim' || $temPrazo === '1') ? 1 : 0;

        // Inserção
        $sql = "INSERT INTO processos (
            contrato_id, numero_processo, assunto, equipe_id, responsavel_id,
            status_id, tipo_id, data_entrada, prazo, data_revisao, data_assinatura,
            sei_recebido, sei_criado_1, sei_criado_2, sei_criado_3,
            tem_prazo, cadastrado_sima, providencia, observacoes, uf, br, criado_por
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $contratoId,
            $numeroProcesso,
            $assunto,
            $equipeId,
            $responsavelId,
            $statusId,
            $tipoId,
            $dataEntrada,
            $prazo,
            $dataRevisao,
            $dataAssinatura,
            $seiRecebido,
            $seiCriado1,
            $seiCriado2,
            $seiCriado3,
            $temPrazoBool,
            $cadastradoSimaBool,
            $providencia,
            $observacoes,
            $uf,
            $br,
            $criadoPorId ?: 1 // fallback para admin (ID 1)
        ]);

        $importados++;
        echo "Linha $linha: Processo $numeroProcesso importado com sucesso.\n";
    }

    $pdo->commit();
    echo "\n---\nImportação concluída! $importados processos importados, $ignorados ignorados.\n";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "Erro na linha $linha: " . $e->getMessage() . "\n";
    echo "Dados da linha: " . print_r($dados, true) . "\n";
}

fclose($handle);
?>