#!/bin/bash
# Вывод pm2 jlist (JSON). Запускается от root по sudo от пользователя PHP-FPM (например apache).
export NVM_DIR="${NVM_DIR:-/root/.nvm}"
[ -s "$NVM_DIR/nvm.sh" ] && . "$NVM_DIR/nvm.sh"
exec pm2 jlist 2>/dev/null || echo "[]"
