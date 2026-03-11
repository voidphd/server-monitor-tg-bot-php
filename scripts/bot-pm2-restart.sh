#!/bin/bash
# pm2 restart <id|name|all>. Запускается от root по sudo от пользователя PHP-FPM.
export NVM_DIR="${NVM_DIR:-/root/.nvm}"
[ -s "$NVM_DIR/nvm.sh" ] && . "$NVM_DIR/nvm.sh"
id="${1:-}"
if [ "$id" = "all" ] || [ "$id" = "--all" ]; then
    pm2 restart all 2>&1
else
    [ -z "$id" ] && exit 1
    pm2 restart "$id" 2>&1
fi
