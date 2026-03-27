<?php
/**
 * Пример конфигурации. Скопируйте в config.php и подставьте свои значения.
 * config.php не должен попадать в репозиторий (.gitignore).
 * Настройки можно менять через веб-интерфейс (index.php), если он включён.
 *
 * bot_token             — токен бота от @BotFather
 * webhook_secret        — произвольная строка для заголовка X-Telegram-Bot-Api-Secret-Token
 * allowed_username      — только этот пользователь может использовать бота (без @); '' = доступ всем
 * sites_domains         — список доменов для проверки кнопки «Сайты»
 * modules               — какие модули показывать: ram, cpu, disk, nginx, pm2, sites, reboot, banned_ips
 * process_list_limit    — сколько процессов выводить в RAM/CPU (1–25, рекомендуется 10)
 * allow_process_stop    — разрешить остановку процесса по PID из бота (true/false)
 * allow_process_restart — разрешить перезапуск процесса по PID из бота (true/false)
 * disk_alert_threshold_percent — порог заполнения / для cron server-monitor.sh (1–99, по умолчанию 90);
 *   в Telegram уведомление при первом достижении порога и далее только при росте %; см. monitor-threshold.env
 */
return [
    'bot_token'             => 'YOUR_BOT_TOKEN_FROM_BOTFATHER',
    'webhook_secret'         => 'GENERATE_RANDOM_STRING_32_CHARS_OR_MORE',
    'allowed_username'       => 'your_telegram_username',
    'sites_domains'          => ['example.com'],
    'modules'                => ['ram', 'cpu', 'disk', 'nginx', 'pm2', 'sites', 'reboot', 'banned_ips'],
    'process_list_limit'     => 10,
    'allow_process_stop'     => false,
    'allow_process_restart'  => false,
    'disk_alert_threshold_percent' => 90,
];
