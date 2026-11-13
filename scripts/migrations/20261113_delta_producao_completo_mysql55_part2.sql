-- SISPRO — conclusão do delta no MySQL 5.5.49 (após CREATEs já aplicados)
-- Parte 2.1 segura: uk_contrato_segmento = (instrumento, subtrecho).
-- Vários NULL no mesmo instrumento não podem virar '' ao mesmo tempo.

-- Conflitos: mesmo instrumento com mais de um subtrecho NULL/'' → distinguir por id
UPDATE contratos_rdci c
INNER JOIN (
  SELECT instrumento
  FROM contratos_rdci
  WHERE subtrecho IS NULL OR subtrecho = ''
  GROUP BY instrumento
  HAVING COUNT(*) > 1
) d ON d.instrumento = c.instrumento
SET c.subtrecho = CONCAT('seg-', c.id)
WHERE c.subtrecho IS NULL;

-- Demais NULL isolados → ''
UPDATE contratos_rdci SET subtrecho = '' WHERE subtrecho IS NULL;

ALTER TABLE contratos_rdci
  MODIFY COLUMN subtrecho varchar(255) NOT NULL DEFAULT '';

-- 2.2 / 2.3 (idempotente; colunas já existem se o CREATE novo rodou)
DROP PROCEDURE IF EXISTS add_column_if_not_exists;
DELIMITER //
CREATE PROCEDURE add_column_if_not_exists(IN tbl VARCHAR(64), IN col VARCHAR(64), IN def TEXT)
BEGIN
    IF NOT EXISTS (
      SELECT * FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = col
    ) THEN
        SET @s = CONCAT('ALTER TABLE ', tbl, ' ADD COLUMN ', col, ' ', def);
        PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;
END //
DELIMITER ;
CALL add_column_if_not_exists('supra_dataset_16_base_controle_geo', 'instrumento_atlas', 'VARCHAR(100) DEFAULT NULL');
DROP PROCEDURE IF EXISTS add_column_if_not_exists;

DROP PROCEDURE IF EXISTS add_column_if_not_exists_2;
DELIMITER //
CREATE PROCEDURE add_column_if_not_exists_2(IN tbl VARCHAR(64), IN col VARCHAR(64), IN def TEXT)
BEGIN
    IF NOT EXISTS (
      SELECT * FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = col
    ) THEN
        SET @s = CONCAT('ALTER TABLE ', tbl, ' ADD COLUMN ', col, ' ', def);
        PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;
END //
DELIMITER ;
CALL add_column_if_not_exists_2('siac_import_jobs', 'tipo', 'VARCHAR(20) DEFAULT ''medicoes''');
DROP PROCEDURE IF EXISTS add_column_if_not_exists_2;

-- Parte 3: FKs idempotentes
SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'comentarios' AND CONSTRAINT_NAME = 'comentarios_ibfk_2');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE comentarios ADD CONSTRAINT comentarios_ibfk_2 FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'mensagens' AND CONSTRAINT_NAME = 'mensagens_ibfk_1');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE mensagens ADD CONSTRAINT mensagens_ibfk_1 FOREIGN KEY (remetente_id) REFERENCES usuarios (id) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'mensagens' AND CONSTRAINT_NAME = 'mensagens_ibfk_2');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE mensagens ADD CONSTRAINT mensagens_ibfk_2 FOREIGN KEY (destinatario_id) REFERENCES usuarios (id) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'processos' AND CONSTRAINT_NAME = 'fk_processos_criado_por');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE processos ADD CONSTRAINT fk_processos_criado_por FOREIGN KEY (criado_por) REFERENCES usuarios (id) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'processos' AND CONSTRAINT_NAME = 'processos_ibfk_5');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE processos ADD CONSTRAINT processos_ibfk_5 FOREIGN KEY (tipo_id) REFERENCES tipos (id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'responsaveis' AND CONSTRAINT_NAME = 'fk_responsavel_usuario');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE responsaveis ADD CONSTRAINT fk_responsavel_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'rdci_notificacoes' AND CONSTRAINT_NAME = 'rdci_notificacoes_ibfk_2');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE rdci_notificacoes ADD CONSTRAINT rdci_notificacoes_ibfk_2 FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
