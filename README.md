# Telegram Server Monitor

[English](README.en.md) | **Русский**

> Self-hosted Telegram bot for Linux server monitoring: RAM, CPU, disk, Nginx, PM2, fail2ban, site checks. PHP, no cloud.

Лёгкий мониторинг Linux-сервера через Telegram-бота: метрики, процессы, PM2, проверка сайтов и перезапуск сервисов по кнопкам. Без облаков и подписок — всё работает на вашем сервере.

**Для кого:** админы и разработчики с VPS/dedicated, которые хотят быстро смотреть статус и выполнять простые действия из телефона.

---

## Возможности

| Действие | Описание |
|----------|----------|
| **RAM** | Сводка по памяти + «Показать процессы» (топ по RAM). Кнопки «Перезапустить»/«Остановить» — ввод PID; управление включается в настройках. Системные процессы (init, systemd и т.д.) запрещены. |
| **CPU** | Load average, число ядер, uptime + «Показать процессы» (топ по CPU) и те же кнопки по PID. |
| **Диск** | Занято/свободно по `/` + кнопка «Подробнее» (по разделам: место и inodes). |
| **Nginx** | Статус процесса + «Подробнее» (версия, результат `nginx -t`). |
| **PM2** | Список процессов и кнопка «Управление» → выбор процесса → статус и кнопки Start / Stop / Restart. |
| **Сайты** | Проверка доступности доменов (HTTPS/HTTP). Список в веб-интерфейсе или `config.php` (`sites_domains`). |
| **Забаненные IP** | Список IP, забаненных в fail2ban (jail nginx-limit-req), с пагинацией и кнопкой «Разбанить» (ввод номера по списку). Нужен скрипт `bot-fail2ban.sh` и права в sudoers. |
| **Всё** | Сводка RAM + CPU + диск + Nginx. |
| **Перезагрузка сервера** | С подтверждением (нужен `bot-reboot.sh` и sudoers). |

---

## Веб-интерфейс настроек

Настройки (токен бота, секрет webhook, разрешённый username, список доменов для проверки «Сайты», **включение модулей** — что показывать в боте) можно менять через браузер. Файл `index.php` — форма и сохранение в `config.php`. Кнопка **«Определить технологии на сервере»** запускает скрипт `scripts/detect-technologies.php`: он проверяет наличие Nginx, PM2, PHP-FPM, MySQL/MariaDB, Docker и предлагает отметить соответствующие модули (RAM, CPU, Диск всегда рекомендуются).

Вкладка **«Cron / диск»** задаёт **порог заполнения диска /** (в процентах) для скрипта **`server-monitor.sh`** (cron на сервере): при сохранении настроек рядом с `config.php` создаётся файл **`monitor-threshold.env`** с переменной `DISK_ALERT_THRESHOLD_PERCENT`. Скрипт мониторинга при наличии этого файла подключает его после `/etc/server-monitor.conf`. Логика уведомлений в Telegram: первое сообщение при первом достижении порога, следующие — только если процент **вырос** (например, после 90% следующее при 91% и т.д.).

**Важно:** интерфейс настроек обязательно защитите паролем. Рекомендуется вынести его на отдельный домен или поддомен (например `tg.example.com`) и закрыть доступ паролем на уровне Nginx.

### Защита паролем (Nginx)

Если у вас Nginx, добавьте в конфиг виртуального хоста для интерфейса настроек:

1. **Создайте файл с логином и паролем** (один раз):

```bash
sudo htpasswd -c /etc/nginx/.htpasswd_telegram_monitor admin
```

Введите пароль для пользователя `admin`. Для добавления ещё одного пользователя используйте без `-c`:  
`sudo htpasswd /etc/nginx/.htpasswd_telegram_monitor second_user`.

2. **В блоке `server`** (внутри блока `listen 443 ssl;` для вашего домена) добавьте строки:

```nginx
auth_basic "Settings";
auth_basic_user_file /etc/nginx/.htpasswd_telegram_monitor;
```

Пример полного конфига для домена с настройками см. в файле **`nginx-settings.example.conf`**.

После перезагрузки Nginx при открытии сайта в браузере будет запрашиваться логин и пароль.

---

## Требования

- **PHP** 7.4+ (расширения: json, без обязательных доп. модулей)
- **Nginx** (или другой веб-сервер с PHP-FPM)
- **HTTPS** для webhook (Telegram требует HTTPS)
- **Telegram Bot Token** — создаётся через [@BotFather](https://t.me/BotFather)

Опционально для полного функционала:

- **Node.js + PM2** — для раздела «PM2»
- **sudo** — для перезапуска сервисов и PM2 (хелпер-скрипты и права в sudoers)

---

## Установка

### 1. Клонирование и размещение файлов

Склонируйте репозиторий или скопируйте файлы в каталог на сервере, доступный веб-серверу, например:

```bash
sudo mkdir -p /var/www/telegram-bot
sudo cp -r . /var/www/telegram-bot/
sudo chown -R apache:apache /var/www/telegram-bot
```

(Замените `apache` на пользователя PHP-FPM: `nginx`, `www-data` и т.д.)

### 2. Конфигурация

```bash
cd /var/www/telegram-bot
cp config.example.php config.php
nano config.php
```

Заполните:

- **bot_token** — токен от @BotFather  
- **webhook_secret** — произвольная длинная строка (например `openssl rand -hex 32`)  
- **allowed_username** — Telegram username без `@` (только этот пользователь сможет пользоваться ботом; оставьте пустую строку `''`, чтобы разрешить всем)

Файл `config.php` не должен попадать в git (он в `.gitignore`).

### 3. Модули и список доменов

- **Модули** — в веб-интерфейсе отметьте, какие кнопки показывать в боте: RAM, CPU, Диск, Nginx, PM2, Сайты, Перезагрузка сервера. Сохраняется в `config.php` как `modules` (массив). Используйте «Определить технологии на сервере», чтобы автоматически отметить модули по обнаруженным сервисам (скрипт `scripts/detect-technologies.php`).
- **Домены для проверки «Сайты»** — в веб-интерфейсе или в `config.php` ключ `sites_domains` (массив строк).

### 4. Nginx

- **Webhook (бот):** используйте `nginx.example.conf` для домена, с которого Telegram будет вызывать `webhook.php` (например `bot.example.com`). Замените `YOUR_DOMAIN`, путь к каталогу приложения и к SSL-сертификатам.
- **Веб-интерфейс настроек:** используйте `nginx-settings.example.conf` для отдельного домена (например `tg.example.com`). В конфиг входят строки защиты паролем (`auth_basic`, `auth_basic_user_file`); пароль создаётся через `htpasswd` (см. раздел «Веб-интерфейс настроек» выше).

Проверка и перезагрузка:

```bash
sudo nginx -t && sudo systemctl reload nginx
```

### 5. SSL-сертификат (Let's Encrypt)

Если ещё нет сертификата для домена:

```bash
sudo certbot certonly --webroot -w /usr/share/nginx/html -d YOUR_DOMAIN
# или standalone: sudo systemctl stop nginx && sudo certbot certonly --standalone -d YOUR_DOMAIN && sudo systemctl start nginx
```

### 6. Webhook URL в настройках

В веб-интерфейсе поле **Webhook URL** формируется из адреса, с которого открыта страница (`HTTP_HOST` + путь к `webhook.php`). У вас отображается ваш домен; у другого пользователя на своём сервере — его домен. В `setWebhook` укажите тот URL, по которому реально доступен webhook (часто отдельный поддомен, например `bot.example.com`).

### 7. Регистрация webhook в Telegram

После того как HTTPS и Nginx работают:

```bash
curl -s -X POST "https://api.telegram.org/bot<YOUR_BOT_TOKEN>/setWebhook" \
  -H "Content-Type: application/json" \
  -d "{\"url\":\"https://YOUR_DOMAIN/webhook.php\",\"secret_token\":\"<YOUR_WEBHOOK_SECRET>\"}"
```

Подставьте `YOUR_BOT_TOKEN` и `YOUR_WEBHOOK_SECRET` из `config.php`. В ответе должно быть `"ok":true`.

### 8. Настройки процессов (RAM/CPU)

В веб-интерфейсе (вкладка «Модули») блок **«Процессы (RAM/CPU)»**:

- **Количество процессов в списке** (1–25) — сколько строк показывать в «Показать процессы» для RAM и CPU. Рекомендуется 10; больше — дольше ответ.
- **Разрешить остановку процесса по PID** — кнопка «Остановить» в списке процессов; по нажатию бот просит ввести PID.
- **Разрешить перезапуск процесса по PID** — кнопка «Перезапустить»; перезапуск возможен только для nginx, php-fpm, mysqld, mariadbd (через systemctl). PID 1, 2 и процессы init, systemd, kthreadd, sshd запрещены.

### 9. Хелперы для PM2, перезапуска сервисов и управления по PID (опционально)

Чтобы раздел «PM2» и кнопки «Перезапустить»/«Остановить» по PID работали, нужны скрипты и права sudo.

Скопируйте скрипты в `/usr/local/bin/` и сделайте исполняемыми:

```bash
sudo cp scripts/bot-pm2-list.sh scripts/bot-pm2-restart.sh scripts/bot-pm2-stop.sh scripts/bot-pm2-start.sh scripts/bot-restart-service.sh scripts/bot-process-pid.sh scripts/bot-reboot.sh scripts/bot-fail2ban.sh /usr/local/bin/
sudo chmod +x /usr/local/bin/bot-pm2-*.sh /usr/local/bin/bot-restart-service.sh /usr/local/bin/bot-process-pid.sh /usr/local/bin/bot-reboot.sh /usr/local/bin/bot-fail2ban.sh
```

Добавьте правило sudoers (пользователь — тот, под которым работает PHP-FPM, например `apache`):

```bash
sudo cp sudoers.example /etc/sudoers.d/bot-telegram
sudo visudo -f /etc/sudoers.d/bot-telegram
# Замените 'apache' на своего пользователя PHP-FPM, сохраните
sudo chmod 440 /etc/sudoers.d/bot-telegram
```

Проверка: `sudo visudo -c -f /etc/sudoers.d/bot-telegram`

---

## Использование

1. Откройте бота в Telegram и отправьте `/start` или «статус».  
2. Появится меню с кнопками: RAM, CPU, Диск, Nginx, PM2, Сайты, Всё, Перезагрузить сервер.  
3. Нажимайте кнопки для получения данных или действий (перезапуск сервиса/PM2).

Если задан `allowed_username`, только этот пользователь получит ответы; остальные запросы игнорируются (бот молчит).

---

## Безопасность

- **secret_token** — обязательно задайте в `config.php` и передайте в `setWebhook`. Telegram будет присылать этот заголовок; без него запросы к webhook отклоняются (403).  
- **allowed_username** — ограничьте доступ к боту своим Telegram-username.  
- **HTTPS** — обязателен для webhook.  
- **sudoers** — выданы права только на конкретные скрипты и аргументы (nginx, php-fpm, mysqld, mariadb, pm2), не на произвольные команды.

---

## Смена секрета или URL webhook

1. Поменяйте `webhook_secret` в `config.php`.  
2. Вызовите `setWebhook` с новым `secret_token` и при необходимости новым `url`.  
Иначе Telegram будет слать запросы со старым (или без) секрета, и скрипт будет отвечать 403.

---

## Модуль «Забаненные IP» (fail2ban)

Если включён модуль **banned_ips**, в боте появляется кнопка «Забаненные IP»: нумерованный список IP из jail `nginx-limit-req` (fail2ban), с пагинацией. Кнопка «Разбанить» запрашивает номер по списку и снимает бан.

Требуется:

- Скрипт **`/usr/local/bin/bot-fail2ban.sh`** (есть в `scripts/`). Команды: `list [jail]` — список IP, `unban <jail> <ip>` — разбан.
- В sudoers для пользователя PHP-FPM:  
  `bot-fail2ban.sh *`

---

## Перед публикацией в открытый репозиторий

- [ ] Убедиться, что **config.php** не попадает в репозиторий (он в `.gitignore`). Не выполнять `git add config.php` и не коммитить его.
- [ ] В **config.example.php** и **config.test.php** только заглушки (`YOUR_BOT_TOKEN`, `test` и т.п.), без реальных токенов и секретов.
- [ ] В коде и README нет ваших доменов, IP, имён серверов и Telegram username (кроме примеров вида `example.com`, `your_telegram_username`).
- [ ] Файл **LICENSE** на месте (в проекте — MIT).
- [ ] В README есть описание установки, требований и опциональных модулей (PM2, fail2ban, перезагрузка).

После клонирования пользователь копирует `config.example.php` в `config.php` и подставляет свои данные.

---

## Структура кода

- **webhook.php** — точка входа: проверка секрета, авторизация по username, обработка callback и команд, состояние (pending state). Подключает `helpers.php`.
- **helpers.php** — функции сбора метрик и данных: `getServerStat`, `getRamProcessesList`, `getCpuProcessesList`, `getPm2ListRaw` / `getPm2ListSimple`, `getBannedIpsList`, `getSitesCheckResult`. Не зависят от конфига и запроса.
- **index.php** — веб-интерфейс настроек (модули, домены, webhook URL).
- Константы `BANNED_IPS_JAIL` и `BANNED_IPS_PER_PAGE` заданы в webhook.php; при необходимости их можно вынести в config.

---

## Лицензия

MIT.
