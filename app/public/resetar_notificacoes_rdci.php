<?php
require_once 'config.php';

// Remove notificações com mais de 30 dias
$stmt = $pdo->prepare("DELETE n FROM rdci_notificacoes n
                        JOIN contratos_rdci c ON n.contrato_id = c.id
                        WHERE n.notificado_em < DATE_SUB(NOW(), INTERVAL 30 DAY)
                        OR c.situacao_projeto LIKE '%CONCLUÍDO%'");
$stmt->execute();
$deletados = $stmt->rowCount();
echo "Notificações resetadas: $deletados";