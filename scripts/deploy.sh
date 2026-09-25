#!/usr/bin/env bash
# Deploy SISPRO → 10.100.11.235/sispro (sem git no remoto).
# Uso:
#   cp .env.deploy.example .env.deploy   # uma vez
#   ./scripts/deploy.sh                  # publica o código vigente
#   ./scripts/deploy.sh --dry-run        # só mostra o que iria enviar
#   ./scripts/deploy.sh --delete         # também apaga no servidor o que não está na cópia local
#
# Não sobrescreve config.local.php. Recusa pacote cru do XAMPP
# (RewriteBase /controle_processos/, PHP 8, config.php sem config.local.php).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

ENV_FILE="${ENV_FILE:-$ROOT/.env.deploy}"
DRY_RUN=0
DO_DELETE=0
for arg in "$@"; do
  case "$arg" in
    --dry-run|-n) DRY_RUN=1 ;;
    --delete) DO_DELETE=1 ;;
    -h|--help)
      sed -n '2,12p' "$0"
      exit 0
      ;;
    *)
      echo "Opção desconhecida: $arg"
      exit 1
      ;;
  esac
done

if [[ ! -f "$ENV_FILE" ]]; then
  echo "Arquivo $ENV_FILE não encontrado."
  echo "Copie: cp .env.deploy.example .env.deploy  e preencha SSH_PASSWORD."
  exit 1
fi

# Carrega .env.deploy sem expandir caracteres especiais da senha
while IFS= read -r line || [[ -n "$line" ]]; do
  line="${line%$'\r'}"
  [[ -z "$line" || "$line" =~ ^[[:space:]]*# ]] && continue
  key="${line%%=*}"
  val="${line#*=}"
  key="$(echo "$key" | xargs)"
  export "$key=$val"
done < "$ENV_FILE"

: "${SSH_HOST:?Defina SSH_HOST}"
: "${SSH_USER:?Defina SSH_USER}"
: "${SSH_PORT:=22}"
: "${REMOTE_PATH:?Defina REMOTE_PATH}"
: "${REMOTE_OWNER:=rodolfo.neto}"
: "${REMOTE_GROUP:=supra_hom_dev}"
: "${USE_SUDO:=1}"
: "${APP_URL_PATH:=/sispro/}"

fail() { echo "ERRO: $*" >&2; exit 1; }

if [[ ! -f "$ROOT/index.php" || ! -d "$ROOT/app" || ! -f "$ROOT/.htaccess" ]]; then
  fail "Estrutura inválida na raiz (faltam index.php, app/ ou .htaccess)."
fi

echo "==> Checagens locais (não publica se o pacote for o XAMPP cru)..."
grep -q 'RewriteBase /sispro/' "$ROOT/.htaccess" \
  || fail ".htaccess precisa de RewriteBase /sispro/ (o zip do dev usa /controle_processos/)."
grep -q 'config.local.php' "$ROOT/app/public/config.php" \
  || fail "app/public/config.php precisa carregar config.local.php."
grep -q '/sispro/redefinir_senha' "$ROOT/app/Controllers/AuthController.php" \
  || fail "AuthController precisa do link /sispro/redefinir_senha."
grep -q "define('SISPRO_VERSION'" "$ROOT/app/Config/version.php" \
  || fail "Falta app/Config/version.php com SISPRO_VERSION."
grep -q 'Config/version.php' "$ROOT/index.php" \
  || fail "index.php precisa carregar app/Config/version.php."
[[ -f "$ROOT/app/Views/diagrama_unifilar.php" && -f "$ROOT/app/Views/rdci.php" ]] \
  || fail "Faltam app/Views/diagrama_unifilar.php ou app/Views/rdci.php. O --delete apagaria essas telas no servidor."

VERSION="$(sed -n "s/.*define('SISPRO_VERSION', '\\([^']*\\)').*/\\1/p" "$ROOT/app/Config/version.php")"
[[ -n "$VERSION" ]] || fail "Não li SISPRO_VERSION em app/Config/version.php."
if [[ "$VERSION" == "0.00.001" ]]; then
  fail "SISPRO_VERSION=0.00.001 é a publicação anterior. A vigente é a tag nova (0.00.002 ou superior)."
fi

PHP8_HITS="$(grep -R -n -E 'str_contains\(|[^[:alnum:]_]fn[[:space:]]*\(|foreach[[:space:]]*\([^)]*as[[:space:]]*\[' \
  --include='*.php' "$ROOT/app" "$ROOT/index.php" "$ROOT/api_exportar_processos.php" 2>/dev/null || true)"
if [[ -n "$PHP8_HITS" ]]; then
  echo "$PHP8_HITS" >&2
  fail "Sintaxe incompatível com PHP 7.0 (str_contains, fn() ou list no foreach). Produção é PHP 7.0.32."
fi

WIN_HITS="$(grep -R -n -E 'ROW_NUMBER[[:space:]]*\(|[[:space:]]OVER[[:space:]]*\(' \
  --include='*.php' "$ROOT/app" "$ROOT/index.php" "$ROOT/api_exportar_processos.php" 2>/dev/null || true)"
if [[ -n "$WIN_HITS" ]]; then
  echo "$WIN_HITS" >&2
  fail "Consulta com ROW_NUMBER/OVER. O MySQL 5.5.49 de produção não executa função de janela."
fi

if [[ ! -f "$ROOT/api_exportar_processos.php" ]] || ! grep -q "define('SYNC_TOKEN'" "$ROOT/api_exportar_processos.php"; then
  fail "api_exportar_processos.php com SYNC_TOKEN precisa ir junto. Não publique sem ele."
fi

need_cmd() { command -v "$1" >/dev/null 2>&1 || { echo "Instale: $1"; exit 1; }; }
need_cmd rsync
need_cmd ssh
need_cmd curl

SSH_BASE=(ssh -p "$SSH_PORT" -o StrictHostKeyChecking=accept-new -o ConnectTimeout=20)
RSYNC_RSH="ssh -p $SSH_PORT -o StrictHostKeyChecking=accept-new -o ConnectTimeout=20"

if [[ -n "${SSH_PASSWORD:-}" ]]; then
  need_cmd sshpass
  export SSHPASS="$SSH_PASSWORD"
  SSH_BASE=(sshpass -e "${SSH_BASE[@]}")
  RSYNC_RSH="sshpass -e ssh -p $SSH_PORT -o StrictHostKeyChecking=accept-new -o ConnectTimeout=20"
fi

REMOTE="${SSH_USER}@${SSH_HOST}:${REMOTE_PATH}/"
EXCLUDE=(
  --exclude '.git/'
  --exclude '.gitignore'
  --exclude '.env'
  --exclude '.env.*'
  --exclude '.env.deploy'
  --exclude '.env.deploy.example'
  --exclude 'scripts/'
  --exclude 'secrets/'
  --exclude 'new_changes/'
  --exclude 'DEPLOY.md'
  --exclude 'README.md'
  --exclude '.vs/'
  --exclude '.vscode/'
  --exclude '.idea/'
  --exclude '.claude/'
  --exclude '*.sql'
  --exclude '*.tgz'
  --exclude '*.tar.gz'
  --exclude '*.zip'
  --exclude '*.7z'
  --exclude 'dist/'
  --exclude 'vendor/'
  --exclude 'node_modules/'
  --exclude 'phpinfo.php'
  --exclude 'info.php'
  --exclude 'config.local.php'
  --exclude 'config.local.php.example'
  --exclude 'app/public/backups/'
  --exclude 'app/public/debug_supra_json.php'
  --exclude 'app/public/diag_import.php'
  --exclude 'dashboard/'
  --exclude 'xampp/'
  --exclude 'webalizer/'
  --exclude '* (2).*'
  --exclude '* (3).*'
  --exclude '.DS_Store'
  --exclude '*.sqlite'
  --exclude '*.log'
)

RSYNC_FLAGS=(-az --human-readable --itemize-changes)
if [[ "$DO_DELETE" -eq 1 ]]; then
  echo "==> --delete ligado: arquivos só do servidor (exceto os excluídos) serão removidos."
  RSYNC_FLAGS+=(--delete)
else
  echo "==> Sem --delete: config.local.php e arquivos só do servidor permanecem."
fi
if [[ "$DRY_RUN" -eq 1 ]]; then
  RSYNC_FLAGS+=(--dry-run)
  echo "==> DRY-RUN (nada será alterado no remoto)"
fi

echo "==> Versão: ${VERSION}"
echo "==> Destino: ${SSH_USER}@${SSH_HOST}:${REMOTE_PATH}"
echo "==> Garantindo pasta remota..."
"${SSH_BASE[@]}" "${SSH_USER}@${SSH_HOST}" "mkdir -p $(printf '%q' "$REMOTE_PATH")"

echo "==> Sincronizando..."
rsync "${RSYNC_FLAGS[@]}" "${EXCLUDE[@]}" -e "$RSYNC_RSH" \
  "$ROOT/" "$REMOTE"

if [[ "$DRY_RUN" -eq 1 ]]; then
  echo "==> Dry-run concluído. Versão local: ${VERSION}"
  exit 0
fi

echo "==> Ajustando permissões e conferindo o que não pode sumir..."
REMOTE_FIX=$(cat <<EOF
set -e
if [[ ! -f "$REMOTE_PATH/app/public/config.local.php" ]]; then
  echo "ERRO: $REMOTE_PATH/app/public/config.local.php não existe. Produção ficaria sem o banco."
  exit 1
fi
grep -q 'RewriteBase /sispro/' "$REMOTE_PATH/.htaccess"
grep -q '/sispro/redefinir_senha' "$REMOTE_PATH/app/Controllers/AuthController.php"
grep -q "define('SISPRO_VERSION', '${VERSION}')" "$REMOTE_PATH/app/Config/version.php"
grep -q "define('SYNC_TOKEN'" "$REMOTE_PATH/api_exportar_processos.php"
if [[ "$USE_SUDO" == "1" ]]; then
  if [[ -n "${SSH_PASSWORD:-}" ]]; then
    printf '%s\n' $(printf '%q' "$SSH_PASSWORD") | sudo -S -p '' chown -R "$REMOTE_OWNER:$REMOTE_GROUP" "$REMOTE_PATH"
    printf '%s\n' $(printf '%q' "$SSH_PASSWORD") | sudo -S -p '' chmod -R g+rwX "$REMOTE_PATH"
    printf '%s\n' $(printf '%q' "$SSH_PASSWORD") | sudo -S -p '' find "$REMOTE_PATH" -type d -exec chmod g+s {} +
    echo "==> PHP 7.0 no container supra-hom..."
    printf '%s\n' $(printf '%q' "$SSH_PASSWORD") | sudo -S -p '' docker exec supra-hom bash -c 'find /var/www/sispro -name "*.php" -print0 | xargs -0 -n1 php -l' > /tmp/sispro_deploy_lint.txt 2>&1 || true
  else
    sudo chown -R "$REMOTE_OWNER:$REMOTE_GROUP" "$REMOTE_PATH"
    sudo chmod -R g+rwX "$REMOTE_PATH"
    sudo docker exec supra-hom bash -c 'find /var/www/sispro -name "*.php" -print0 | xargs -0 -n1 php -l' > /tmp/sispro_deploy_lint.txt 2>&1 || true
  fi
else
  chown -R "$REMOTE_OWNER:$REMOTE_GROUP" "$REMOTE_PATH" 2>/dev/null || true
  chmod -R g+rwX "$REMOTE_PATH"
fi
if [[ -f /tmp/sispro_deploy_lint.txt ]]; then
  if grep -q 'Parse error' /tmp/sispro_deploy_lint.txt; then
    grep 'Parse error' /tmp/sispro_deploy_lint.txt
    exit 1
  fi
  if ! grep -q 'No syntax errors' /tmp/sispro_deploy_lint.txt; then
    echo "ERRO: php -l no container supra-hom não confirmou sintaxe."
    exit 1
  fi
fi
test -f "$REMOTE_PATH/index.php"
test -f "$REMOTE_PATH/.htaccess"
echo PERMS_OK
EOF
)
"${SSH_BASE[@]}" "${SSH_USER}@${SSH_HOST}" "bash -lc $(printf '%q' "$REMOTE_FIX")"

echo "==> Smoke HTTP..."
BODY="$(mktemp)"
CODE=$(curl -sS -m 20 -o "$BODY" -w '%{http_code}' "http://${SSH_HOST}/sispro/login" || echo '000')
echo "    GET /sispro/login → HTTP $CODE"
if [[ "$CODE" != "200" ]]; then
  rm -f "$BODY"
  echo "AVISO: resposta inesperada. Verifique Apache/container e config.local.php."
  exit 2
fi
if ! grep -q "Versão ${VERSION}" "$BODY"; then
  rm -f "$BODY"
  fail "Login não exibiu Versão ${VERSION}. O rodapé de produção não está na tag vigente."
fi
rm -f "$BODY"

echo "==> Deploy concluído: http://${SSH_HOST}/sispro/  (Versão ${VERSION})"
