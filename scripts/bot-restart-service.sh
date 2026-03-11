#!/bin/bash
# systemctl restart <nginx|php-fpm|mysqld|mariadb>. Запускается от root по sudo от пользователя PHP-FPM.
case "$1" in
    nginx|php-fpm|mysqld|mariadb) systemctl restart "$1" 2>&1 ;;
    *) exit 1 ;;
esac
