-- =============================================================
-- delta_producao_completo.sql
-- Delta aditivo, idempotente e condicional: Dev (28/10) -> Produção
-- =============================================================
-- O QUE ESTE SCRIPT FAZ:
-- 1. Cria as 10 tabelas novas se não existirem.
-- 2. Altera a tabela contratos_rdci (ajusta a coluna subtrecho).
-- 3. Adiciona a coluna instrumento_atlas na supra_dataset_16.
-- 4. Cria as FKs nas tabelas antigas (se os dados permitirem).
-- =============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- =============================================================
-- PARTE 1: CRIAÇÃO DAS TABELAS NOVAS
-- =============================================================

CREATE TABLE IF NOT EXISTS `tipos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) DEFAULT NULL,
  `nome` varchar(100) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `nivel` enum('leitor','usuario','admin','desenvolvedor') NOT NULL DEFAULT 'usuario',
  `status` enum('ativo','inativo','pendente') DEFAULT 'pendente',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_activity` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `equipe_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `equipe_id` (`equipe_id`),
  CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`equipe_id`) REFERENCES `equipes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS `usuario_setor` (
  `usuario_id` int(11) NOT NULL,
  `setor_id` int(11) NOT NULL,
  PRIMARY KEY (`usuario_id`,`setor_id`),
  KEY `setor_id` (`setor_id`),
  CONSTRAINT `usuario_setor_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `usuario_setor_ibfk_2` FOREIGN KEY (`setor_id`) REFERENCES `setores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS `supra_dataset_16_base_controle_geo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fid` varchar(50) DEFAULT NULL,
  `shape` varchar(50) DEFAULT NULL,
  `fonte_seg` varchar(255) DEFAULT NULL,
  `br` varchar(20) DEFAULT NULL,
  `uf` char(2) DEFAULT NULL,
  `br_uf` varchar(20) DEFAULT NULL,
  `tipo` varchar(100) DEFAULT NULL,
  `o_instrum` varchar(100) DEFAULT NULL,
  `o_empresa` varchar(255) DEFAULT NULL,
  `o_obj_resu` text DEFAULT NULL,
  `o_km_inici` decimal(15,4) DEFAULT NULL,
  `o_km_final` decimal(15,4) DEFAULT NULL,
  `o_extensao` decimal(15,4) DEFAULT NULL,
  `o_lote` varchar(100) DEFAULT NULL,
  `o_interven` varchar(255) DEFAULT NULL,
  `espacializ` varchar(20) DEFAULT NULL,
  `id_obra` varchar(50) DEFAULT NULL,
  `id_pac` varchar(50) DEFAULT NULL,
  `id_ppa` varchar(50) DEFAULT NULL,
  `acao_orcam` varchar(50) DEFAULT NULL,
  `s_instrum` varchar(100) DEFAULT NULL,
  `s_empresa` varchar(255) DEFAULT NULL,
  `id_notion` varchar(100) DEFAULT NULL,
  `npo` varchar(100) DEFAULT NULL,
  `shape_leng` decimal(15,6) DEFAULT NULL,
  `o_edital` varchar(100) DEFAULT NULL,
  `s_edital` varchar(100) DEFAULT NULL,
  `o_proc_lic` varchar(100) DEFAULT NULL,
  `s_proc_lic` varchar(100) DEFAULT NULL,
  `id_sup` varchar(50) DEFAULT NULL,
  `o_termino` date DEFAULT NULL,
  `dez_2024` text DEFAULT NULL,
  `trecho` varchar(255) DEFAULT NULL,
  `o_inicio` date DEFAULT NULL,
  `o_pi_a_r` decimal(20,4) DEFAULT NULL,
  `o_executad` decimal(20,10) DEFAULT NULL,
  `valor_dnit` decimal(20,2) DEFAULT NULL,
  `valor_cont` decimal(20,2) DEFAULT NULL,
  `od_valor_t` decimal(20,2) DEFAULT NULL,
  `auxiliar` varchar(100) DEFAULT NULL,
  `cs_cad` varchar(20) DEFAULT NULL,
  `cs_est` varchar(20) DEFAULT NULL,
  `cs_est_ok` varchar(20) DEFAULT NULL,
  `fev_2025` text DEFAULT NULL,
  `rp` varchar(50) DEFAULT NULL,
  `maio_2025` text DEFAULT NULL,
  `jun_2025` text DEFAULT NULL,
  `jul_2025` text DEFAULT NULL,
  `ago_2025` text DEFAULT NULL,
  `set_2025` text DEFAULT NULL,
  `rdci` varchar(20) DEFAULT NULL,
  `em_projeto` varchar(100) DEFAULT NULL,
  `out_2025` text DEFAULT NULL,
  `dez_2025` text DEFAULT NULL,
  `jan_26` text DEFAULT NULL,
  `qfid` varchar(50) DEFAULT NULL,
  `fev_2026` text DEFAULT NULL,
  `marco_26` text DEFAULT NULL,
  `abril_2026` text DEFAULT NULL,
  `situacao_obra_mes` text DEFAULT NULL,
  `instrumento_atlas` varchar(100) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_id_pac` (`id_pac`),
  KEY `idx_fid` (`fid`),
  KEY `idx_uf_br` (`uf`,`br`),
  KEY `idx_instrumento_atlas` (`instrumento_atlas`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `supra_dataset_36_todos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `instrumento` varchar(255) NOT NULL,
  `uf` varchar(10) DEFAULT NULL,
  `br` varchar(30) DEFAULT NULL,
  `lote` varchar(50) DEFAULT NULL,
  `nome_usual` varchar(500) DEFAULT NULL,
  `acao` varchar(50) DEFAULT NULL,
  `codigo_ppa` varchar(50) DEFAULT NULL,
  `fid_geo` varchar(50) DEFAULT NULL,
  `tipo_contratacao` varchar(100) DEFAULT NULL,
  `processo_licitacao` varchar(100) DEFAULT NULL,
  `edital_cocconv` varchar(100) DEFAULT NULL,
  `dispensa_cocconv` varchar(100) DEFAULT NULL,
  `edital_dispensa_manual_coac` varchar(100) DEFAULT NULL,
  `kmi` decimal(10,3) DEFAULT NULL,
  `kmf` varchar(200) DEFAULT NULL,
  `extensao_siac` varchar(100) DEFAULT NULL,
  `extensao_real` varchar(100) DEFAULT NULL,
  `tipo_execucao` varchar(150) DEFAULT NULL,
  `tipo_pavimento` varchar(100) DEFAULT NULL,
  `conceito_obra` varchar(150) DEFAULT NULL,
  `divisao_segmentos` varchar(150) DEFAULT NULL,
  `empresa_construtora` varchar(500) DEFAULT NULL,
  `data_inicio_vigencia` date DEFAULT NULL,
  `data_termino_vigencia` date DEFAULT NULL,
  `data_termino_execucao` date DEFAULT NULL,
  `processo_base` varchar(150) DEFAULT NULL,
  `eh_remanescente` varchar(10) DEFAULT NULL,
  `instrumentos_anteriores` varchar(500) DEFAULT NULL,
  `id_pac` varchar(50) DEFAULT NULL,
  `nome_empreendimento_governa` varchar(500) DEFAULT NULL,
  `municipios_governa` varchar(500) DEFAULT NULL,
  `valor_total_investimento` decimal(15,2) DEFAULT NULL,
  `instrumento_supervisao_coac` varchar(100) DEFAULT NULL,
  `supervisao_cocconv` varchar(100) DEFAULT NULL,
  `proc_licitacao_supervisao` varchar(100) DEFAULT NULL,
  `edital_supervisao_cocconv` varchar(100) DEFAULT NULL,
  `dispensa_supervisao_cocconv` varchar(100) DEFAULT NULL,
  `edital_dispensa_supervisao_manual_coac` varchar(100) DEFAULT NULL,
  `empresa_supervisao` varchar(500) DEFAULT NULL,
  `inicio_vigencia_supervisao` date DEFAULT NULL,
  `termino_vigencia_supervisao` date DEFAULT NULL,
  `termino_execucao_supervisao` date DEFAULT NULL,
  `proc_base_supervisao` varchar(150) DEFAULT NULL,
  `situacao_supervisao` varchar(150) DEFAULT NULL,
  `empresa_supervisora_atlas` varchar(500) DEFAULT NULL,
  `contrato_ambiental` varchar(100) DEFAULT NULL,
  `inicio_vigencia_ambiental` date DEFAULT NULL,
  `termino_vigencia_ambiental` date DEFAULT NULL,
  `contrato_desapropriacao` varchar(100) DEFAULT NULL,
  `inicio_vigencia_desapropriacao` date DEFAULT NULL,
  `termino_vigencia_desapropriacao` date DEFAULT NULL,
  `numero_sei_cronograma_vigente` varchar(100) DEFAULT NULL,
  `numero_sei_aprovacao_cronograma` varchar(100) DEFAULT NULL,
  `data_termino_servicos` date DEFAULT NULL,
  `numero_sei_cronograma_vigente_atualizado` varchar(100) DEFAULT NULL,
  `data_termino_cronograma_obra_atualizado` date DEFAULT NULL,
  `valor_previsto` decimal(15,2) DEFAULT NULL,
  `valor_executado` decimal(15,2) DEFAULT NULL,
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_instrumento` (`instrumento`),
  KEY `idx_uf` (`uf`),
  KEY `idx_br` (`br`),
  KEY `idx_tipo_contratacao` (`tipo_contratacao`),
  KEY `idx_tipo_execucao` (`tipo_execucao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `supra_dataset_38_todos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `instrumento` varchar(100) DEFAULT NULL,
  `uf` varchar(10) DEFAULT NULL,
  `br` varchar(20) DEFAULT NULL,
  `nome_usual` varchar(500) DEFAULT NULL,
  `tipo_manual` varchar(100) DEFAULT NULL,
  `intervencao_atlas` varchar(100) DEFAULT NULL,
  `acao` varchar(50) DEFAULT NULL,
  `outras_acoes` varchar(20) DEFAULT NULL,
  `todas_acoes` varchar(255) DEFAULT NULL,
  `empreendimento_npo` text DEFAULT NULL,
  `unidade_manual` varchar(255) DEFAULT NULL,
  `entrar_lista_atlas` varchar(20) DEFAULT NULL,
  `tipo_contratacao` varchar(100) DEFAULT NULL,
  `lote` varchar(50) DEFAULT NULL,
  `empresa` varchar(500) DEFAULT NULL,
  `inicio_vigencia` date DEFAULT NULL,
  `termino_vigencia` date DEFAULT NULL,
  `valor_pi_r_a` decimal(15,2) DEFAULT NULL,
  `saldo_a_executar` decimal(15,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_instrumento` (`instrumento`),
  KEY `idx_uf` (`uf`),
  KEY `idx_br` (`br`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `supra_dataset_39_meio` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `instrumento` varchar(255) NOT NULL,
  `analise_meio_ambiente` longtext DEFAULT NULL,
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_instrumento` (`instrumento`),
  KEY `idx_instrumento_prefix` (`instrumento`(80))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `supra_dataset_40_resumos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `instrumento` varchar(255) NOT NULL,
  `situacao_geo` longtext DEFAULT NULL,
  `texto_mapa_capa` longtext DEFAULT NULL,
  `resumo_geral_lote` longtext DEFAULT NULL,
  `situacao_empreendimento_atlas` longtext DEFAULT NULL,
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_instrumento` (`instrumento`),
  KEY `idx_instrumento_prefix` (`instrumento`(80))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `supra_dataset_40_slides_atlas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `instrumento` varchar(255) DEFAULT NULL,
  `estado` char(2) DEFAULT NULL,
  `br` varchar(20) DEFAULT NULL,
  `lote` varchar(100) DEFAULT NULL,
  `acao` varchar(50) DEFAULT NULL,
  `incluir_capa_atlas` varchar(20) DEFAULT NULL,
  `criar_slides_atlas` varchar(20) DEFAULT NULL,
  `incluir_slide_foto` varchar(20) DEFAULT NULL,
  `incluir_slide_unifilar` varchar(20) DEFAULT NULL,
  `incluir_slide_analises` varchar(20) DEFAULT NULL,
  `incluir_grafico_slide` varchar(20) DEFAULT NULL,
  `situacao_geo` text DEFAULT NULL,
  `incluir_mapa_gerencial` varchar(20) DEFAULT NULL,
  `fid_geo` varchar(50) DEFAULT NULL,
  `valor_total_geo` decimal(20,2) DEFAULT NULL,
  `valor_pago_geo` decimal(20,2) DEFAULT NULL,
  `perc_executada_geo` varchar(50) DEFAULT NULL,
  `nome_empreendimento_slides` varchar(255) DEFAULT NULL,
  `texto_mapa_capa` text DEFAULT NULL,
  `cor_legenda_mapa` varchar(100) DEFAULT NULL,
  `status_empreendimento` varchar(100) DEFAULT NULL,
  `extensao_empreendimento` varchar(50) DEFAULT NULL,
  `situacao_empreendimento_atlas` text DEFAULT NULL,
  `importancia_slide` text DEFAULT NULL,
  `nome_lote_slide` varchar(255) DEFAULT NULL,
  `resumo_geral_lote` text DEFAULT NULL,
  `kmi` decimal(15,4) DEFAULT NULL,
  `kmf` decimal(15,4) DEFAULT NULL,
  `segmento` varchar(100) DEFAULT NULL,
  `extensao_siac` varchar(100) DEFAULT NULL,
  `empresa_construtora` varchar(255) DEFAULT NULL,
  `saldo_empreendimento` varchar(100) DEFAULT NULL,
  `valor_previsto_conclusao` decimal(20,2) DEFAULT NULL,
  `id_pac` varchar(50) DEFAULT NULL,
  `valor_sugerido_conclusao` decimal(20,2) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_id_pac` (`id_pac`),
  KEY `idx_fid_geo` (`fid_geo`),
  KEY `idx_estado_br` (`estado`,`br`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `supra_log_importacao` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `data_execucao` timestamp NOT NULL DEFAULT current_timestamp(),
  `total_registros` int(11) DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  `mensagem` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- =============================================================
-- PARTE 2: ALTERAÇÕES EM TABELAS EXISTENTES
-- =============================================================

-- 2.1) Ajuste da coluna subtrecho na tabela contratos_rdci
-- Primeiro, preenchemos com vazio onde estiver nulo para não dar erro no MODIFY
UPDATE `contratos_rdci` SET `subtrecho` = '' WHERE `subtrecho` IS NULL;
ALTER TABLE `contratos_rdci` MODIFY COLUMN `subtrecho` varchar(255) NOT NULL DEFAULT '';

-- 2.2) Adicionar a nova coluna instrumento_atlas e seu índice
ALTER TABLE `supra_dataset_16_base_controle_geo` 
ADD COLUMN IF NOT EXISTS `instrumento_atlas` varchar(100) DEFAULT NULL AFTER `situacao_obra_mes`;

ALTER TABLE `supra_dataset_16_base_controle_geo` 
ADD INDEX IF NOT EXISTS `idx_instrumento_atlas` (`instrumento_atlas`);


-- =============================================================
-- PARTE 3: CRIAÇÃO DAS CHAVES ESTRANGEIRAS (FKs)
-- (Só cria se não existirem. Se houver dados inconsistentes, dará erro e a FK não será criada)
-- =============================================================

-- 3.1) FKs na tabela comentarios
SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'comentarios' AND CONSTRAINT_NAME = 'comentarios_ibfk_2');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE comentarios ADD CONSTRAINT comentarios_ibfk_2 FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3.2) FKs na tabela mensagens
SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'mensagens' AND CONSTRAINT_NAME = 'mensagens_ibfk_1');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE mensagens ADD CONSTRAINT mensagens_ibfk_1 FOREIGN KEY (remetente_id) REFERENCES usuarios (id) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'mensagens' AND CONSTRAINT_NAME = 'mensagens_ibfk_2');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE mensagens ADD CONSTRAINT mensagens_ibfk_2 FOREIGN KEY (destinatario_id) REFERENCES usuarios (id) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3.3) FKs na tabela processos
SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'processos' AND CONSTRAINT_NAME = 'fk_processos_criado_por');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE processos ADD CONSTRAINT fk_processos_criado_por FOREIGN KEY (criado_por) REFERENCES usuarios (id) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'processos' AND CONSTRAINT_NAME = 'processos_ibfk_5');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE processos ADD CONSTRAINT processos_ibfk_5 FOREIGN KEY (tipo_id) REFERENCES tipos (id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3.4) FKs na tabela responsaveis
SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'responsaveis' AND CONSTRAINT_NAME = 'fk_responsavel_usuario');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE responsaveis ADD CONSTRAINT fk_responsavel_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3.5) FKs na tabela rdci_notificacoes
SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'rdci_notificacoes' AND CONSTRAINT_NAME = 'rdci_notificacoes_ibfk_2');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE rdci_notificacoes ADD CONSTRAINT rdci_notificacoes_ibfk_2 FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;