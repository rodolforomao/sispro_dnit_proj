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
