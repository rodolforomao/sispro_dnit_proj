#!/usr/bin/env bash
# Deploy SISPRO → 10.100.11.235/sispro (sem git no remoto).
# Uso:
#   cp .env.deploy.example .env.deploy   # uma vez
#   ./scripts/deploy.sh                 # sincroniza arquivos
#   ./scripts/deploy.sh --dry-run       # só mostra o que iria enviar
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

ENV_FILE="${ENV_FILE:-$ROOT/.env.deploy}"
DRY_RUN=0
for arg in "$@"; do
  case "$arg" in
    --dry-run|-n) DRY_RUN=1 ;;
    -h|--help)
      sed -n '2,8p' "$0"
      exit 0
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

if [[ ! -f "$ROOT/index.php" || ! -d "$ROOT/app" || ! -f "$ROOT/.htaccess" ]]; then
  echo "Estrutura inválida na raiz do projeto (faltam index.php, app/ ou .htaccess)."
  exit 1
fi

need_cmd() { command -v "$1" >/dev/null 2>&1 || { echo "Instale: $1"; exit 1; }; }
need_cmd rsync
need_cmd ssh

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
  --exclude 'DEPLOY.md'
  --exclude 'README.md'
  --exclude '.vs/'
  --exclude '.vscode/'
  --exclude '.idea/'
  --exclude '*.sql'
  --exclude '*.tgz'
  --exclude '*.tar.gz'
  --exclude '*.zip'
  --exclude 'dist/'
  --exclude 'vendor/'
  --exclude 'node_modules/'
  --exclude 'phpinfo.php'
  --exclude 'info.php'
  --exclude 'config.local.php'
  --exclude 'config.local.php.example'
  --exclude '* (2).*'
  --exclude '* (3).*'
  --exclude '.DS_Store'
  --exclude '*.sqlite'
  --exclude '*.log'
)

RSYNC_FLAGS=(-az --delete --human-readable --itemize-changes)
if [[ "$DRY_RUN" -eq 1 ]]; then
  RSYNC_FLAGS+=(--dry-run)
  echo "==> DRY-RUN (nada será alterado no remoto)"
fi

echo "==> Destino: ${SSH_USER}@${SSH_HOST}:${REMOTE_PATH}"
echo "==> Garantindo pasta remota..."
"${SSH_BASE[@]}" "${SSH_USER}@${SSH_HOST}" "mkdir -p $(printf '%q' "$REMOTE_PATH")"

echo "==> Sincronizando (config.local.php no servidor é preservado)..."
rsync "${RSYNC_FLAGS[@]}" "${EXCLUDE[@]}" -e "$RSYNC_RSH" \
  "$ROOT/" "$REMOTE"

if [[ "$DRY_RUN" -eq 1 ]]; then
  echo "==> Dry-run concluído."
  exit 0
fi

echo "==> Ajustando permissões no remoto..."
REMOTE_FIX=$(cat <<EOF
set -e
if [[ ! -f "$REMOTE_PATH/app/public/config.local.php" ]]; then
  echo "AVISO: $REMOTE_PATH/app/public/config.local.php não existe."
  echo "Crie a partir de config.local.php.example antes de usar o sistema."
fi
if [[ "$USE_SUDO" == "1" ]]; then
  if [[ -n "${SSH_PASSWORD:-}" ]]; then
    printf '%s\n' "$SSH_PASSWORD" | sudo -S -p '' chown -R "$REMOTE_OWNER:$REMOTE_GROUP" "$REMOTE_PATH"
    printf '%s\n' "$SSH_PASSWORD" | sudo -S -p '' chmod -R g+rwX "$REMOTE_PATH"
    printf '%s\n' "$SSH_PASSWORD" | sudo -S -p '' find "$REMOTE_PATH" -type d -exec chmod g+s {} +
  else
    sudo chown -R "$REMOTE_OWNER:$REMOTE_GROUP" "$REMOTE_PATH"
    sudo chmod -R g+rwX "$REMOTE_PATH"
  fi
else
  chown -R "$REMOTE_OWNER:$REMOTE_GROUP" "$REMOTE_PATH" 2>/dev/null || true
  chmod -R g+rwX "$REMOTE_PATH"
fi
test -f "$REMOTE_PATH/index.php"
test -f "$REMOTE_PATH/.htaccess"
echo PERMS_OK
EOF
)
"${SSH_BASE[@]}" "${SSH_USER}@${SSH_HOST}" "bash -lc $(printf '%q' "$REMOTE_FIX")"

echo "==> Smoke HTTP..."
CODE=$(curl -sS -m 15 -o /dev/null -w '%{http_code}' "http://${SSH_HOST}/sispro/login" || echo '000')
echo "    GET /sispro/login → HTTP $CODE"
if [[ "$CODE" != "200" ]]; then
  echo "AVISO: resposta inesperada. Verifique Apache/container e config.local.php."
  exit 2
fi

echo "==> Deploy concluído: http://${SSH_HOST}/sispro/"
