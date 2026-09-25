-- SISPRO — corrige processos_ibfk_1 para apontar para a tabela certa.
--
-- Schema original (15/09/2026, controle_processos.sql / controle_processosv1.1.sql):
--   CONSTRAINT `processos_ibfk_1` FOREIGN KEY (`contrato_id`) REFERENCES `contratos` (`id`) ON DELETE CASCADE
--
-- O código sempre tratou `processos.contrato_id` como id de `contratos_rdci`
-- (app/Models/ProcessoModel.php, joins `LEFT JOIN contratos_rdci r ON p.contrato_id = r.id`
-- e `getContratoInfo()`). A FK antiga exigia um id de `contratos`, que usa outra numeração
-- (ids 160-241) — por isso o cadastro de processo falhava com erro 1452 em produção
-- (SQLSTATE 23000) sempre que o formulário enviava um id válido de contratos_rdci
-- (ex.: 44, 68) que não existia em `contratos`.
--
-- O SQL do dev de 25/09/2026 (new_changes/20260925/controle_processos 25-09-2026.sql,
-- linha 6274) já trazia a FK corrigida:
--   ADD CONSTRAINT `processos_ibfk_1` FOREIGN KEY (`contrato_id`) REFERENCES `contratos_rdci` (`id`) ON DELETE SET NULL
--
-- Este script aplica a mesma correção em produção. Idempotente: pode rodar de novo,
-- o DROP falha silenciosamente se a FK já não referenciar `contratos`.

-- 0 linhas órfãs esperado antes de aplicar (confirmado em produção em 25/09/2026):
--   SELECT COUNT(*) FROM processos p LEFT JOIN contratos_rdci r ON p.contrato_id = r.id
--   WHERE p.contrato_id IS NOT NULL AND r.id IS NULL;

ALTER TABLE `processos` DROP FOREIGN KEY `processos_ibfk_1`;

ALTER TABLE `processos`
  ADD CONSTRAINT `processos_ibfk_1` FOREIGN KEY (`contrato_id`) REFERENCES `contratos_rdci` (`id`) ON DELETE SET NULL;
