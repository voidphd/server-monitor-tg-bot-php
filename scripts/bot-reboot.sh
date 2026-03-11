#!/bin/bash
# Запускается из Telegram-бота по sudo. Даёт время ответу уйти в Telegram, затем перезагружает сервер.
set -e
sleep 5
exec /sbin/reboot
