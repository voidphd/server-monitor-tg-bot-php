#!/bin/bash
# Управление процессом по PID: остановка или перезапуск (через systemctl по имени сервиса).
# Вызов: bot-process-pid.sh stop|restart PID
# Защита: PID 1, 2 и процессы init, systemd, kthreadd, sshd — запрещены.
set -e
ACTION="${1:-}"
PID="${2:-}"
if [[ "$ACTION" != "stop" && "$ACTION" != "restart" ]] || [[ -z "$PID" || ! "$PID" =~ ^[0-9]+$ ]]; then
    echo "Usage: $0 stop|restart PID"
    exit 2
fi
if [[ "$PID" -le 2 ]]; then
    echo "Запрещено: системный PID $PID"
    exit 1
fi
COMM=""
if [[ -d "/proc/$PID" ]]; then
    COMM=$(cat "/proc/$PID/comm" 2>/dev/null || true)
fi
if [[ -z "$COMM" ]]; then
    echo "Процесс с PID $PID не найден"
    exit 1
fi
case "$COMM" in
    init|systemd|kthreadd|sshd)
        echo "Запрещено: системный процесс $COMM"
        exit 1
        ;;
esac
if [[ "$ACTION" == "stop" ]]; then
    kill -TERM "$PID" 2>/dev/null || { echo "Не удалось остановить PID $PID"; exit 1; }
    echo "Процесс $PID ($COMM) остановлен"
    exit 0
fi
# restart: по имени процесса пробуем systemctl restart
SVC=""
case "$COMM" in
    nginx) SVC="nginx" ;;
    php-fpm) SVC="php-fpm" ;;
    mysqld) SVC="mysqld" ;;
    mariadbd) SVC="mariadb" ;;
    *)
        echo "Перезапуск по PID для $COMM не поддерживается (только nginx, php-fpm, mysqld, mariadbd)"
        exit 1
        ;;
esac
if [[ -n "$SVC" ]]; then
    systemctl restart "$SVC" 2>/dev/null && echo "Сервис $SVC перезапущен" || { echo "Ошибка перезапуска $SVC"; exit 1; }
fi
exit 0
