# SISPRO — Documentação do deploy em 10.100.11.235/sispro

**Data:** 15/09/2026  
**URL:** http://10.100.11.235/sispro/  
**Status:** publicado e validado (smoke test OK)

---

## Resposta rápida: dados no banco?

**Sim.** O dump `controle_processos.sql` foi importado no MySQL do host `10.100.11.235`.

Contagens atuais (pós-import):

| Tabela | Registros |
|--------|-----------|
| usuarios | 7 |
| processos | 784 |
| contratos | 55 |
| contratos_rdci | 86 |
| comentarios | 47 |
| modelos | 42 |
| mensagens | 1 |
| logs_auditoria | 159 |

Total: **16 tabelas** no schema `controle_processos`.

---

## Infra: o que já existia e o que foi reutilizado

### Respostas diretas

| Pergunta | Resposta |
|----------|----------|
| Usou Docker? | **Sim — o Docker que já rodava no servidor.** Não criamos container novo para o SISPRO. |
| Usou o Apache do host (Debian)? | **Não.** A porta 80 já está no container `supra-hom`. Usamos o **Apache de dentro desse container**. |
| Já tinha banco MySQL para o SISPRO? | **O serviço MySQL no host já existia (5.5.49), mas o schema/dados do SISPRO não.** Criamos o DB `controle_processos`, o user `sispro` e importamos o dump. |

### Docker (já existente)

No host já havia vários containers. O relevante para HTTP na porta 80:

- **`supra-hom`** — sobe o SUPRA (CodeIgniter) + Apache + **PHP 7.0.32**
- Volume bind: `/home/code/supra` (host) → `/var/www` (container)
- DocumentRoot do Apache **dentro do container**: `/var/www`

**O que fizemos:** apenas criamos a pasta `sispro` nesse volume já montado:

```
/home/code/supra/sispro/   →   http://10.100.11.235/sispro/
```

Não foi preciso `docker run`, nova imagem, novo compose nem alterar portas. O SISPRO “entra de carona” no mesmo Apache/PHP do SUPRA homolog.

Outros containers no host (não usados pelo SISPRO): `supra-dev` (:8087), `geoserver` (:8080), `sima_*`, etc.

### Apache: host vs container

| | Apache no host (Debian) | Apache no `supra-hom` |
|--|-------------------------|------------------------|
| Quem atende `:80`? | Não (a porta 80 é do Docker) | **Sim** |
| DocumentRoot config | `/var/www/sistema/supra` (path inexistente / quebrado) | `/var/www` (= `/home/code/supra`) |
| PHP | 7.2 no CLI/mods do host (não é o que serve o site) | **7.0.32** (header `X-Powered-By`) |
| Usado pelo SISPRO? | Não | **Sim** |

Conclusão: reutilizamos a **infra HTTP já em produção de homologação** (container), sem ligar o Apache “nativo” do Debian para este app.

### Banco de dados

Havia **dois mundos** no servidor:

1. **SQL Server** em `10.100.10.65` — usado pelo SUPRA (`pdo_sqlsrv`). **Não** serve para o SISPRO (app é MySQL/PDO MySQL).
2. **MySQL 5.5.49 no próprio host** — serviço já instalado e ativo (`mysqld`), mas praticamente vazio para nosso uso (só system DBs + phpmyadmin). **Não existia** o database `controle_processos` nem o user `sispro`.

O que foi feito no MySQL do host:

1. Criar DB `controle_processos` + user `sispro`
2. Importar o dump local (adaptado ao MySQL 5.5)
3. Ajuste mínimo de `bind-address=172.17.0.1` para o container alcançar o MySQL da gateway Docker (antes só escutava `127.0.0.1`)

Credenciais e contagens: `secrets/DB_REMOTE.md` (fora do git).

### Diagrama

```
Browser
  → http://10.100.11.235:80
    → Docker (já existia): container supra-hom
         Apache + PHP 7.0.32
         DocumentRoot /var/www  ← /home/code/supra
           ├── (SUPRA CodeIgniter)
           └── sispro/          ← app novo (só arquivos)
    → MySQL do host (já existia o serviço; schema SISPRO é novo)
         172.17.0.1:3306
         DB controle_processos
```

### Inventário resumido

| Item | Valor |
|------|--------|
| Host | DNIT-SIGACONT-HMG (`10.100.11.235`) |
| HTTP | Container `supra-hom` (já existia) |
| PHP | 7.0.32 (no container) |
| Path físico | `/home/code/supra/sispro/` |
| Path no container | `/var/www/sispro/` |
| MySQL | 5.5.49 no host — serviço antigo; **DB SISPRO criado no deploy** |
| SUPRA DB | SQL Server `10.100.10.65` (não usado pelo SISPRO) |

---

## O que foi alterado no código (local)

Arquivos na raiz do projeto (`index.php`, `.htaccess`, `app/`):

1. **`.htaccess`** — `RewriteBase /sispro/`
2. **`app/Controllers/AuthController.php`** — link de redefinição de senha com `/sispro/`
3. **`app/public/importar_rdci_api.php`** — `fn()` trocado por `function ()` (compatível PHP 7.0)
4. **`app/public/config.php`** — passa a carregar `config.local.php` se existir (credenciais de produção fora do default local)

`config.local.php` **não** vai no repositório; existe só no servidor (e cópia local em `secrets/`).

---

## Banco de dados

### Criação

- Database: `controle_processos`
- User: `sispro`@`172.17.%` e `sispro`@`localhost`
- Privileges: só nesse schema

### Importação

- Fonte: `controle_processos.sql` (dump MariaDB 10.4 / phpMyAdmin)
- Adaptado para MySQL 5.5 antes do import:
  - `utf8mb4` → `utf8`
  - remoção de `ROW_FORMAT=DYNAMIC`
  - ajuste de `TIMESTAMP`/`DATETIME` (limite do 5.5: um único `CURRENT_TIMESTAMP` automático)
  - remoção de `DEFAULT NULL` em colunas `TEXT`

### Acesso do container ao MySQL

MySQL do host escutava só em `127.0.0.1`, inacessível ao Docker.  
Ajuste mínimo (backup em `/etc/mysql/my.cnf.sispro.bak`):

```
bind-address = 172.17.0.1
```

Assim o container alcança o MySQL pela gateway Docker, **sem** abrir 3306 na LAN.

### Credenciais de produção

Arquivo no servidor:

```
/home/code/supra/sispro/app/public/config.local.php
```

Documentação **local, fora do git** (senha e contagens): pasta `secrets/` — ver `secrets/DB_REMOTE.md`.  
Essa pasta está no `.gitignore`; não versionar.

---

## Estrutura do repositório (local)

O app **não** fica mais sob `htdocs/` (XAMPP removido do projeto). Na raiz:

```
SISPRO/
├── index.php
├── .htaccess
├── app/
├── scripts/deploy.sh
├── .env.deploy.example
├── DEPLOY.md
└── .gitignore
```

No servidor continua: `/home/code/supra/sispro/` → URL `/sispro/`.

O `.htaccess` do SUPRA tem `RewriteCond %{REQUEST_FILENAME} !-d`, então a pasta `/sispro` é servida diretamente e **não** cai no 404 do CodeIgniter.

---

## Hardening feito

- Removidos do deploy: `phpinfo.php`, `info.php`, pastas `.vs`
- Credenciais não ficam no `config.php` default (só em `config.local.php` no servidor)
- User MySQL dedicado (não `root` sem senha)

---

## Smoke test (resultado)

| Teste | Resultado |
|-------|-----------|
| `GET /sispro/` | 302 → `/sispro/login` |
| `GET /sispro/login` | 200 — tela de login |
| `POST` login inválido | “Email ou senha inválidos” (DB OK) |
| `GET /` (SUPRA) | 200 — intacto |
| PDO container → MySQL | OK (7 usuários) |

---

## Atualizar o app (sem git no remoto)

Não há git no servidor. O deploy é por **rsync via SSH**.

```bash
cp .env.deploy.example .env.deploy   # uma vez; preencha SSH_PASSWORD
./scripts/deploy.sh --dry-run        # opcional: ver o que mudaria
./scripts/deploy.sh                  # envia arquivos + ajusta permissões + smoke HTTP
```

O script:

- sincroniza a raiz do projeto → `/home/code/supra/sispro/`
- **não** sobrescreve `app/public/config.local.php` no servidor
- exclui `.git`, dumps `.sql`, IDE, `phpinfo`, scripts locais
- faz `chown` para `rodolfo.neto:supra_hom_dev` (via sudo se `USE_SUDO=1`)
- testa `http://HOST/sispro/login`

Requisitos locais: `rsync`, `ssh`, `sshpass` (se usar senha), `curl`.

Se houver novo dump SQL, importar com as mesmas adaptações MySQL 5.5 (ou gerar dump já compatível).

---

## Rollback

1. **Arquivos:** se existir `sispro_backup_*.tgz` em `/home/code/supra/`, restaurar  
2. **MySQL bind:** `cp /etc/mysql/my.cnf.sispro.bak /etc/mysql/my.cnf && service mysql restart`  
3. **DB:** `DROP DATABASE controle_processos;` (e recriar se necessário)  
4. Remover pasta: `rm -rf /home/code/supra/sispro`

---

## Acesso SSH (referência operacional)

- Usuário: `rodolfo.neto` (sudo)
- Credenciais de homolog: `enviroment/config_remote/agent_supra_space/.env_homlog` (não versionar senhas)
- Script auxiliar Docker: `enviroment/config_remote/235_docker/ssh-docker-diagnostico.sh`

---

## Pendências / atenção

- MySQL **5.5** é antigo; features novas do MariaDB 10.4+ podem exigir adaptação em dumps futuros
- App desenvolvido em PHP 8.2 localmente; produção é PHP **7.0** — evitar sintaxe ≥ 7.4 (`fn()`, typed properties, etc.)
- Sem HTTPS na porta 443 do host
- Login: usar usuários já importados do dump (senhas são as do ambiente de origem do dump)

---

## Publicação vigente — 25/09/2026

O que está no ar é a tag **`0.00.003`**. A tag `0.00.002` ficou na publicação de 24/09. A tag `0.00.001` continua no commit `6baadf2`, antes de qualquer atualização do desenvolvedor.

| Item | Valor vigente |
|------|----------------|
| URL | http://10.100.11.235/sispro/ |
| Tag vigente | **`0.00.003`** |
| Branch da atualização | `2026092501` |
| Rodapé em produção | `Versão 0.00.003` (`app/Config/version.php`) |
| Repositório | https://github.com/rodolforomao/sispro_dnit_proj |

Origem do pacote (pasta `new_changes/`, no `.gitignore`):

- `new_changes/20260925/htdocs25-09-2026.7z`
- `new_changes/20260925/controle_processos 25-09-2026.sql` (gerado em 25/09/2026 10:41, MariaDB 10.4.32)

O app dentro do 7z continua sendo `htdocs/controle_processos`. `htdocs/sispro_dnit_proj-master` é a árvore antiga de 15/09 (sem avanço físico nem datasets SUPRA). `dashboard/` e `xampp/` não entram.

### Código: o 7z não substituiu a produção

Comparação arquivo a arquivo (ignorando fim de linha do Windows): o PHP do 7z é o mesmo de 23/09. As únicas diferenças contra o repositório são os ajustes de produção já feitos na tag `0.00.002` e a correção do dashboard. Copiar o zip por cima quebraria o servidor:

- `RewriteBase /controle_processos/` no lugar de `/sispro/`
- `config.php` com `root` e senha vazia, sem `config.local.php`
- link de senha em `/controle_processos/redefinir_senha`
- `str_contains()`, `fn()` e `foreach ($x as [$a, $b])` (PHP 8; produção é 7.0.32)
- três consultas com `ROW_NUMBER() OVER (PARTITION BY instrumento ...)` em `RdciController.php`

Arquivos só do zip, deixados de fora: `debug_supra_json.php`, `diag_import.php`, `notificacoes_rdci (2).php`.

O que esta tag acrescenta no código, em relação à `0.00.002`:

- `dashKpisPortfolio` reescrito com `MAX(id)` por `instrumento` (MySQL 5.5). Validado no banco depois da carga de 25/09: 25 concluídos, 41 em andamento, 4 PAAR abertos.
- `scripts/deploy.sh` recusa pacote cru do XAMPP, sintaxe de PHP 8 e a versão `0.00.001`. Rsync sem `--delete`, a menos que se passe `--delete`.
- Rodapé na tag `0.00.003`.

### Banco (sem apagar e sem duplicar)

Backup anterior à carga: `/home/code/supra/sispro_db_backup_20260925_pre.sql`

O `.sql` do phpMyAdmin não foi executado direto. Sem `DROP` e sem `DELETE`. Tabelas `*_bkp_20260924` do dump não foram criadas em produção. `logs_auditoria` do dump (ids até 177) não entrou, para não reescrever a trilha do servidor (259 linhas).

Casos que não podiam ser upsert só por `id`:

- O dump de 25/09 não tem a tabela viva `contratos`. Os contratos novos estão em `contratos_bkp_20260924` (79 ids, 160–241). Foram aplicados na tabela `contratos`, atualizando pelo id e sem incluir o id na cláusula de update.
- `supra_dataset_39_meio` e `supra_dataset_40_resumos` vieram com ids novos (859–1144), enquanto produção ainda tinha 573–858 para os mesmos instrumentos. Carga em tabela temporária, `UPDATE` pelo instrumento e `INSERT` só do instrumento que faltava.

Contagens depois da carga:

| Tabela | Antes | Depois |
|--------|------:|-------:|
| comentarios | 50 | 50 (ids 523–525 de produção mantidos) |
| contratos | 55 | 79 |
| contratos_rdci | 89 | 103 |
| processos | 801 | 801 |
| logs_auditoria | 259 | 259 |
| usuarios | 7 | 7 |
| usuario_setor | 11 | 11 |
| supra_dataset_1_todos | 1353 | 1353 (mesmo intervalo de id, atualizado) |
| supra_dataset_36_todos | 286 | 286 |
| supra_dataset_38_todos | 642 | 642 |
| supra_dataset_39_meio | 286 | 286 instrumentos, sem duplicar |
| supra_dataset_40_resumos | 286 | 286 instrumentos, sem duplicar |

---

## Publicação de 24/09/2026 (código novo)

O deploy de 24/09 é a atualização de **23/09/2026**. Não é a tag `0.00.001`. A tag vigente passou a ser `0.00.003` em 25/09/2026.

| Item | Valor na época |
|------|----------------|
| URL | http://10.100.11.235/sispro/ |
| Tag | **`0.00.002`** |
| Commit da tag | `c84d3b0` — merge em `master` |
| Branch da atualização | `2026092401` |
| Commit da branch | `77a64e7` — código novo + rodapé com a versão |
| Tag antiga | `0.00.001` no commit `6baadf2` (`first code`), **antes** desta atualização |
| Rodapé na época | `Versão 0.00.002` (`app/Config/version.php`, constante `SISPRO_VERSION`) |
| Repositório | https://github.com/rodolforomao/sispro_dnit_proj |

`master`, a branch `2026092401` e a tag `0.00.002` já foram enviadas para `origin`.

Origem do pacote do desenvolvedor (não versionar a pasta; está no `.gitignore`):

- `new_changes/20260924/htdocs 23-09-2026.7z`
- `new_changes/20260924/controle_processos 23-09-2026.sql`

Dentro do 7z, o app novo é `htdocs/controle_processos` (23/09). `htdocs/sispro_dnit_proj-master` é cópia de 15/09. `htdocs/dashboard` e `htdocs/xampp` são do XAMPP e **não** entram em produção.

### O que entrou no código

- Avanço físico, comparativo de bases, comparativo de bancos, dashboard de projetos, atualização de contratos RDCI
- Importação SUPRA: datasets 1, 36, 38, meio ambiente e resumos
- API `api_exportar_processos.php` na raiz do app (produção exporta; o XAMPP do dev consome)
- Coluna `processos.contrato_id_old`
- Tabelas novas: `supra_dataset_1_todos`, `supra_dataset_36_todos`, `supra_dataset_38_todos`, `supra_dataset_39_meio`, `supra_dataset_40_resumos`

### Banco (sem apagar e sem duplicar)

Backup anterior à carga: `/home/code/supra/sispro_db_backup_20260924_pre.sql`  
Backup dos arquivos: `/home/code/supra/sispro_files_backup_20260924.tgz`

O dump do dev **não** foi importado com `DROP` nem com `CREATE` por cima das tabelas que já existiam. Carga feita com `INSERT ... ON DUPLICATE KEY UPDATE` (atualiza o id que já existe, insere o id novo, **não** apaga linha que só existe em produção). `logs_auditoria` entrou com `INSERT IGNORE` para não substituir a trilha do servidor.

Contagens depois da carga:

| Tabela | Registros |
|--------|----------:|
| comentarios | 50 (inclui ids 523–525 que só existiam em produção) |
| contratos | 55 |
| contratos_rdci | 89 |
| logs_auditoria | 255 |
| processos | 801 |
| usuarios | 7 |
| usuario_setor | 11 |
| supra_dataset_1_todos | 1353 |
| supra_dataset_36_todos | 286 |
| supra_dataset_38_todos | 642 |
| supra_dataset_39_meio | 286 |
| supra_dataset_40_resumos | 286 |

Ajustes de schema no MySQL 5.5: `contratos_rdci.processo_projeto` varchar(200), `contratos_rdci.status_acao` varchar(30), `processos.contrato_id_old`. Nas tabelas novas, um único `TIMESTAMP` automático por tabela; o segundo carimbo ficou `DATETIME`. `utf8`, sem `utf8mb4` e sem `ROW_FORMAT=DYNAMIC`. `TEXT`/`LONGTEXT` sem `DEFAULT`.

### Versões dos bancos

| Onde | Servidor | PHP do dump / app | Papel |
|------|----------|-------------------|--------|
| Produção `10.100.11.235` | **MySQL 5.5.49-0+deb8u1** | PHP **7.0.32** no container `supra-hom` | Banco que atende o SISPRO. Consultado em 24/09/2026 com `SELECT VERSION()`. |
| XAMPP do desenvolvedor | **MariaDB 10.4.32** | PHP **8.2.12** (phpMyAdmin 5.2.1) | Origem dos dumps. `controle_processosv1.1.sql` (15/09/2026 15:36) e `controle_processos 23-09-2026.sql` (23/09/2026 14:49). |

O código do dev é escrito contra MariaDB 10.4. Função de janela (`ROW_NUMBER()`, `OVER`, `PARTITION BY`) existe no MariaDB 10.2+ e no MySQL 8. O MySQL **5.5.49** de produção não tem isso. Também não aceita, sem adaptação: `utf8mb4` em índice longo, mais de um `TIMESTAMP` com `CURRENT_TIMESTAMP` na mesma tabela, `DEFAULT` em `TEXT`/`LONGTEXT` e `current_timestamp()` com parênteses.

### Dashboard de projetos (24/09/2026)

O dashboard quebrava porque a consulta usava `ROW_NUMBER() OVER (PARTITION BY ...)`, que o MySQL 5.5 de produção não aceita. Essa sintaxe só existe a partir do MySQL 8 (e no MariaDB 10.4 do desenvolvedor).

A consulta foi reescrita em `app/Controllers/RdciController.php` (`dashKpisPortfolio`) para pegar o último trecho de cada contrato pelo maior `id` (`MAX(id)` agrupado por `instrumento`). Já está no ar. No banco, a mesma consulta devolveu 25 contratos concluídos e 41 em andamento.

---

## Não sobrescrever no próximo pacote do dev

O zip do XAMPP vem com raiz `/controle_processos/`, `config.php` em `root` sem senha e PHP 8.2. Copiar esse pacote em cima de `/home/code/supra/sispro/` quebra a produção. Preservar o que está abaixo.

### Arquivos e valores de produção

| O quê | Manter |
|-------|--------|
| `.htaccess` | `RewriteBase /sispro/` — o dev manda `/controle_processos/` |
| `app/Controllers/AuthController.php` | link `http://HOST/sispro/redefinir_senha?token=` |
| `app/public/config.php` | continua carregando `config.local.php` se existir |
| `app/public/config.local.php` | **só no servidor** (e cópia em `secrets/config.local.php`). O rsync exclui esse arquivo. Não substituir pelo `config.php` do XAMPP |
| `app/Config/version.php` | `SISPRO_VERSION` = tag vigente `0.00.003`. Rodapé de login, home e diagrama unifilar usa essa constante |
| `index.php` | dá `require` em `app/Config/version.php` |
| `app/Views/diagrama_unifilar.php` e `app/Views/rdci.php` | existem em produção; um pacote novo pode não trazê-los. Não apagar no rsync (`deploy.sh` usa `--delete`) |
| Credenciais MySQL | ver `secrets/DB_REMOTE.md`. Host a partir do container: `172.17.0.1`. Não abrir `bind-address=0.0.0.0` |

`scripts/deploy.sh` exclui `config.local.php`, mas o `--delete` apaga no servidor qualquer outro arquivo que não esteja na cópia local. Antes de usá-lo num pacote cru do dev, conferir a lista acima. O deploy de 24/09 foi rsync **sem** `--delete`.

### PHP 7.0.32 (container `supra-hom`)

O pacote do dev não sobe direto. Já foi reescrito e precisa continuar assim:

- `fn($x) => ...` → `function($x) { return ...; }`
- `str_contains($a, $b)` → `strpos($a, $b) !== false`
- `foreach ($lista as [$a, $b, $c])` → `foreach ($lista as $item) { list($a, $b, $c) = $item; }`

Arquivos em que isso foi feito: `importar_supra_dataset_1.php`, `importar_supra_dataset_38.php`, `importar_rdci_api.php`, `app/Views/contratos_rdci.php`, `app/Views/rdci.php`, `app/public/contratos_rdci.php`, `app/public/rdci.php`.

### Não publicar

- `app/public/debug_supra_json.php`
- `app/public/diag_import.php`
- `app/public/notificacoes_rdci (2).php` (duplicata do Windows)
- `app/public/backups/*.json`
- `.vs/`, `phpinfo.php`, pasta `dashboard/` e `xampp/` do 7z

Os dois scripts de debug chamam a API do SUPRA sem autenticação do SISPRO e devolvem o corpo da resposta. Ficaram de fora de propósito.

### Tokens que estão em produção

Não trocar esses valores por vazio, por token de outra máquina, nem apagar o arquivo ao copiar o zip.

**1. Exportação de processos (SISPRO → XAMPP do dev)**

Arquivos (o mesmo valor nos dois):

- `api_exportar_processos.php` — constante `SYNC_TOKEN`
- `app/public/sincronizar_processos.php` — `$SYNC_TOKEN` e `$SYNC_URL`

```
SYNC_TOKEN = kzseb1XXFRO1CWL5FAPQ3mJi5KBI5viG5OsPWwv2xTI
SYNC_URL   = http://10.100.11.235/sispro/api_exportar_processos.php
```

Header: `X-Sync-Token`. Query alternativa: `?token=`. Sem o token a API responde 401. Cópia também em `secrets/API_SYNC.md`.

`sincronizar_processos.php` é ferramenta do ambiente do desenvolvedor (está no servidor porque veio no pacote). Ela lê a produção; não é o caminho de atualizar o 235.

**2. API SUPRA (importação dos datasets e RDCI)**

O mesmo JWT vai no header HTTP `token:` nestes arquivos:

- `app/public/importar_rdci_api.php`
- `app/public/importar_supra_dataset_1.php`
- `app/public/importar_supra_dataset_36_infoobras.php`
- `app/public/importar_supra_dataset_38.php`
- `app/public/importar_supra_dataset_meio_ambiente.php`
- `app/public/importar_supra_dataset_resumo.php`

```
eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyIjoic3VwcmFfY2liX2FwaUBkbml0Lmdvdi5iciIsInBhc3MiOiJTdXByYUBDaWIxMjM0NUAifQ.GyejoRFofL3mMnN7jD64RBrr2JSB9hVpR_BWrfAtd3U
```

URL base: `https://supra.dnit.gov.br/index_cgcont_common.php/cgcont/cib/exportBi/{dataset}`.

Se o próximo zip vier com outro JWT, não substituir o de produção sem confirmar com quem opera o SUPRA. Esses scripts estão sem tela de login do SISPRO; a URL direta dispara a importação.

### Próximo dump SQL

Não rodar o `.sql` do phpMyAdmin direto no MySQL 5.5 (ele faz `CREATE TABLE` sem `IF NOT EXISTS` e duplicaria ou falharia). Repetir o procedimento de 24/09: backup, alterar só coluna nova, `ON DUPLICATE KEY UPDATE` nas tabelas de negócio, `INSERT IGNORE` em `logs_auditoria`, sem `DELETE` e sem `DROP` das tabelas com dados.
