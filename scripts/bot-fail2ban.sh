#!/bin/bash
# Список забаненных IP и разбан по запросу из Telegram-бота.
# Установка: sudo cp scripts/bot-fail2ban.sh /usr/local/bin/ && sudo chmod +x /usr/local/bin/bot-fail2ban.sh
# Использование: bot-fail2ban.sh list <jail>  -> одна строка на IP
#               bot-fail2ban.sh unban <jail> <ip>
set -e
case "${1:-}" in
  list)
    JAIL="${2:-nginx-limit-req}"
    out=$(fail2ban-client status "$JAIL" 2>/dev/null || true)
    if echo "$out" | grep -q "Banned IP list:"; then
      echo "$out" | sed -n 's/.*Banned IP list:\s*//p' | tr -s ' \t' '\n' | sed 's/^[[:space:]]*//;s/[[:space:]]*$//' | grep -v '^$'
    fi
    ;;
  unban)
    JAIL="${2:?jail}"
    IP="${3:?ip}"
    fail2ban-client set "$JAIL" unbanip "$IP" 2>&1
    ;;
  *)
    echo "Usage: $0 list [jail] | unban <jail> <ip>" >&2
    exit 1
    ;;
esac
