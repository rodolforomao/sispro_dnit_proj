-- SISPRO — tabela alerta_notificacao (análise orçamentária)
-- MySQL 5.5.49 / controle_processos

CREATE TABLE IF NOT EXISTS `alerta_notificacao` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `contrato_numero` varchar(50) NOT NULL,
  `tipo` varchar(50) NOT NULL,
  `mes_referencia` varchar(20) NOT NULL,
  `observacao` text,
  `lida` tinyint(1) NOT NULL DEFAULT 0,
  `lida_em` datetime DEFAULT NULL,
  `notificado_em` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_contrato_tipo_mes` (`contrato_numero`,`tipo`,`mes_referencia`),
  KEY `idx_lida` (`lida`),
  KEY `idx_contrato` (`contrato_numero`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
