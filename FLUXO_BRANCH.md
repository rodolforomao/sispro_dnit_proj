# Fluxo de branch, tag, master e deploy

Publicação do SISPRO em `10.100.11.235/sispro`. A tag vigente fica em `app/Config/version.php` (`SISPRO_VERSION`). O rodapé de login usa essa constante, e `scripts/deploy.sh` recusa o deploy se o HTML não mostrar a mesma versão.

## Nomes

| Peça | Formato | Regra |
|------|---------|--------|
| Branch | `YYYYMMDDBB` | Data local + sequência do dia, começando em `01`. Se `2026102101` já existir, use `2026102102`. |
| Tag | `X.XX.XXX` | Incremente só o último grupo (A), com três dígitos. `0.00.005` vira `0.00.006`. Não altere `X` nem `XX` neste fluxo. |
| Versão no código | igual à tag | `define('SISPRO_VERSION', '0.00.006');` em `app/Config/version.php`. |

A tag é anotada e aponta para o commit de merge em `master`, não para a ponta da branch.

## Passos

1. Confira o que está sem commit. Não inclua `.env.deploy`, `secrets/` nem `app/public/backups/`.
2. Calcule a branch do dia e a próxima tag (`git tag -l '0.00.*'`).
3. Crie a branch a partir de `master`:

```bash
git checkout master
git checkout -b 2026102101
```

4. Grave a nova `SISPRO_VERSION` e o restante da publicação nesta branch. Commit na branch.
5. Atualize `master` com merge sem fast-forward:

```bash
git checkout master
git merge --no-ff 2026102101 -m "Merge da branch 2026102101: ..."
```

6. Crie a tag anotada em `master` (o commit de merge):

```bash
git tag -a 0.00.006 -m "SISPRO 0.00.006 — ..."
```

7. Envie branch, tag e master:

```bash
git push -u origin 2026102101
git push origin master
git push origin 0.00.006
```

8. Inicie o deploy descrito em `DEPLOY.md`, sem `--delete`:

```bash
./scripts/deploy.sh --dry-run
./scripts/deploy.sh
```

`FLUXO_BRANCH.md` fica fora do rsync, no mesmo critério de `DEPLOY.md`. O servidor não tem git; o que entra em produção é o `rsync` desse script.

## Publicação 21/10/2026

| Item | Valor |
|------|--------|
| Branch | `2026102101` |
| Tag | `0.00.006` |
| Base | `master` em `4a83143` |

Entra a API `api_exportar_banco.php` (produção exporta o banco) e `app/public/sincronizar_banco.php` (o XAMPP mescla: atualiza a chave que já existe, insere a que só está na produção e mantém a linha que só existe no dev).
