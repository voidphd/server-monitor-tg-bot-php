#!/bin/bash
# pm2 start <id|name>. Запускается от root по sudo от пользователя PHP-FPM.
export NVM_DIR="${NVM_DIR:-/root/.nvm}"
[ -s "$NVM_DIR/nvm.sh" ] && . "$NVM_DIR/nvm.sh"
[ -z "$1" ] && exit 1
pm2 start "$1" 2>&1
