#!/bin/bash
set -euo pipefail

HOST="45.79.220.248"
USER="root"
DEST="/var/www/posto"

if [ -z "${SSHPASS:-}" ]; then
  echo "Definí SSHPASS con la contraseña SSH antes de ejecutar: export SSHPASS='...'" >&2
  exit 1
fi

SSH_OPTS="-o StrictHostKeyChecking=no -o PreferredAuthentications=password -o PubkeyAuthentication=no"

echo "→ rsync a $USER@$HOST:$DEST"
sshpass -e rsync -az --delete \
  --exclude '.git' --exclude '.github' \
  -e "ssh $SSH_OPTS" ./ "$USER@$HOST:$DEST/"

echo "→ permisos + recarga Apache"
sshpass -e ssh $SSH_OPTS "$USER@$HOST" \
  "chown -R www-data:www-data $DEST && \
   find $DEST -type d -exec chmod 755 {} + && \
   find $DEST -type f -exec chmod 644 {} + && \
   systemctl reload apache2"

echo "✔ Deploy completo → https://posto.45.79.220.248.sslip.io/api/send.php"
