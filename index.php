<?php
/**
 * Веб-интерфейс настроек Telegram Server Monitor.
 * Должен быть защищён паролем на уровне Nginx (auth_basic) или иным способом.
 * Язык: cookie tsm_lang (ru|en) или ?lang=ru|en.
 */
declare(strict_types=1);

$allowedLangs = ['ru', 'en'];
$lang = 'ru';
if (isset($_GET['lang']) && in_array($_GET['lang'], $allowedLangs, true)) {
    setcookie('tsm_lang', $_GET['lang'], ['expires' => time() + 86400 * 365, 'path' => '/', 'secure' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'), 'httponly' => true, 'samesite' => 'Lax']);
    $lang = $_GET['lang'];
    $redirect = preg_replace('/[?&]lang=(ru|en)(&|$)/', '$2', $_SERVER['REQUEST_URI'] ?? '');
    $redirect = rtrim($redirect, '?&') ?: parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '/';
    header('Location: ' . $redirect);
    exit;
}
if (isset($_COOKIE['tsm_lang']) && in_array($_COOKIE['tsm_lang'], $allowedLangs, true)) {
    $lang = $_COOKIE['tsm_lang'];
}

$translations = [
    'ru' => [
        'title' => 'Настройки — Telegram Server Monitor',
        'heading' => '⚙️ Настройки Telegram Server Monitor',
        'saved_ok' => 'Настройки сохранены.',
        'error_required' => 'Токен бота и секрет webhook обязательны.',
        'error_write' => 'Не удалось записать config.php. Проверьте права на каталог.',
        'tab_modules' => 'Модули',
        'tab_telegram' => 'Телеграм',
        'tab_monitor' => 'Cron / диск',
        'hint_modules' => 'Отметьте модули, которые будут отображаться в меню бота. Иконка ⚙️ открывает настройки модуля (если есть).',
        'module_settings_btn_title' => 'Настройки модуля',
        'output_label' => 'Выводит:',
        'actions_label' => 'Доп. действия:',
        'processes_block_title' => 'Процессы (RAM / CPU)',
        'processes_block_hint' => 'Настройки списка процессов и управления ими по PID в боте.',
        'process_list_limit_label' => 'Количество процессов в списке (1–25)',
        'process_list_limit_hint' => 'Рекомендуется 10. Чем больше — тем дольше выполняется запрос.',
        'allow_stop' => 'Разрешить остановку процесса по PID (кнопка «Остановить» в списке процессов)',
        'allow_restart' => 'Разрешить перезапуск процесса по PID (кнопка «Перезапустить»; для системных процессов запрещено)',
        'btn_detect' => 'Определить технологии на сервере',
        'detect_checking' => 'Проверяю…',
        'detect_found' => 'Обнаружены: %s. Рекомендуемые модули отмечены. Нажмите «Сохранить», чтобы применить.',
        'detect_done' => 'Рекомендуемые модули отмечены. Нажмите «Сохранить», чтобы применить.',
        'detect_error' => 'Не удалось выполнить проверку.',
        'bot_token_label' => 'Токен бота (BotFather)',
        'bot_token_placeholder' => '123456:ABC...',
        'webhook_secret_label' => 'Секрет webhook (X-Telegram-Bot-Api-Secret-Token)',
        'webhook_secret_placeholder' => 'длинная случайная строка',
        'webhook_secret_hint' => 'Используйте тот же секрет в setWebhook. По умолчанию поля заблокированы, чтобы не перезаписать ключи случайно.',
        'allowed_username_label' => 'Разрешённый Telegram username (без @)',
        'allowed_username_placeholder' => 'оставьте пустым для доступа всем',
        'webhook_url_heading' => 'Webhook URL',
        'webhook_url_hint' => 'Формируется из адреса, с которого открыта эта страница (HTTP_HOST + путь к webhook.php). У вас отображается ваш домен; у другого пользователя на своём сервере будет его домен. Укажите в setWebhook тот URL, по которому реально доступен webhook (часто отдельный поддомен, например bot.example.com).',
        'webhook_curl_hint' => 'Регистрация webhook:',
        'save_btn' => 'Сохранить',
        'modal_sites_title' => 'Настройки модуля «Сайты»',
        'modal_sites_hint' => 'Бот по кнопке «Сайты» проверяет доступность каждого домена (HTTPS, при неудаче HTTP) и показывает код ответа.',
        'sites_domains_label' => 'Домены (по одному в строке)',
        'sites_domains_placeholder' => 'example.com',
        'sites_domains_hint' => 'Только домены, без https://. Пустые строки игнорируются.',
        'close_btn' => 'Закрыть',
        'toggle_edit_title' => 'Разрешить редактирование',
        'toggle_lock_title' => 'Запретить редактирование',
        'disk_threshold_label' => 'Порог заполнения диска / для уведомлений в Telegram (%)',
        'disk_threshold_hint' => 'Используется скриптом server-monitor.sh (cron). Первое сообщение — при первом достижении этого процента; следующее — только если занятость выросла (например, с 90% до 91%). Ниже порога счётчик сбрасывается. Значение записывается в файл monitor-threshold.env рядом с config.php.',
        'warn_monitor_env' => 'Не удалось записать monitor-threshold.env (проверьте права на каталог). Порог в config.php сохранён.',
    ],
    'en' => [
        'title' => 'Settings — Telegram Server Monitor',
        'heading' => '⚙️ Telegram Server Monitor Settings',
        'saved_ok' => 'Settings saved.',
        'error_required' => 'Bot token and webhook secret are required.',
        'error_write' => 'Could not write config.php. Check directory permissions.',
        'tab_modules' => 'Modules',
        'tab_telegram' => 'Telegram',
        'tab_monitor' => 'Cron / disk',
        'hint_modules' => 'Check the modules to show in the bot menu. The ⚙️ icon opens module settings (if available).',
        'module_settings_btn_title' => 'Module settings',
        'output_label' => 'Output:',
        'actions_label' => 'Actions:',
        'processes_block_title' => 'Processes (RAM / CPU)',
        'processes_block_hint' => 'Settings for the process list and PID control in the bot.',
        'process_list_limit_label' => 'Number of processes in list (1–25)',
        'process_list_limit_hint' => 'Recommended 10. More rows mean slower response.',
        'allow_stop' => 'Allow process stop by PID ("Stop" button in process list)',
        'allow_restart' => 'Allow process restart by PID ("Restart"; forbidden for system processes)',
        'btn_detect' => 'Detect server technologies',
        'detect_checking' => 'Checking…',
        'detect_found' => 'Found: %s. Suggested modules checked. Click "Save" to apply.',
        'detect_done' => 'Suggested modules checked. Click "Save" to apply.',
        'detect_error' => 'Detection failed.',
        'bot_token_label' => 'Bot token (BotFather)',
        'bot_token_placeholder' => '123456:ABC...',
        'webhook_secret_label' => 'Webhook secret (X-Telegram-Bot-Api-Secret-Token)',
        'webhook_secret_placeholder' => 'long random string',
        'webhook_secret_hint' => 'Use the same secret in setWebhook. Fields are locked by default to avoid overwriting keys.',
        'allowed_username_label' => 'Allowed Telegram username (no @)',
        'allowed_username_placeholder' => 'leave empty to allow everyone',
        'webhook_url_heading' => 'Webhook URL',
        'webhook_url_hint' => 'Built from the host of this page (HTTP_HOST + path to webhook.php). Set in setWebhook the URL where your webhook is reachable (often a subdomain like bot.example.com).',
        'webhook_curl_hint' => 'Register webhook:',
        'save_btn' => 'Save',
        'modal_sites_title' => 'Sites module settings',
        'modal_sites_hint' => 'The bot checks each domain (HTTPS, then HTTP on failure) and shows the response code.',
        'sites_domains_label' => 'Domains (one per line)',
        'sites_domains_placeholder' => 'example.com',
        'sites_domains_hint' => 'Domains only, no https://. Empty lines ignored.',
        'close_btn' => 'Close',
        'toggle_edit_title' => 'Allow editing',
        'toggle_lock_title' => 'Lock editing',
        'disk_threshold_label' => 'Root disk alert threshold for Telegram (%)',
        'disk_threshold_hint' => 'Used by server-monitor.sh (cron). First alert when this % is first reached; further alerts only if usage increases (e.g. 90% → 91%). Below the threshold the counter resets. Written to monitor-threshold.env next to config.php.',
        'warn_monitor_env' => 'Could not write monitor-threshold.env (check directory permissions). Threshold saved in config.php.',
    ],
];

$moduleLabels = [
    'ru' => [
        'ram' => '🖥 RAM', 'cpu' => '📊 CPU', 'disk' => '💾 Диск', 'nginx' => '🌐 Nginx',
        'pm2' => '📦 PM2', 'sites' => '🌍 Сайты', 'reboot' => '🔄 Перезагрузка сервера', 'banned_ips' => '🚫 Забаненные IP',
    ],
    'en' => [
        'ram' => '🖥 RAM', 'cpu' => '📊 CPU', 'disk' => '💾 Disk', 'nginx' => '🌐 Nginx',
        'pm2' => '📦 PM2', 'sites' => '🌍 Sites', 'reboot' => '🔄 Server reboot', 'banned_ips' => '🚫 Banned IPs',
    ],
];
$moduleDescriptions = [
    'ru' => [
        'ram'    => ['output' => 'Сводка по памяти: занято / всего (MiB), процент использования.', 'actions' => '«Показать процессы» — топ по RAM. Две кнопки: «Перезапустить» и «Остановить»; по нажатию запрос PID. Управление включается в блоке «Процессы (RAM/CPU)».'],
        'cpu'    => ['output' => 'Load average (1m, 5m, 15m), число ядер, примерная загрузка %, uptime.', 'actions' => '«Показать процессы» — топ по CPU; далее «Перезапустить»/«Остановить» с вводом PID (настройки в блоке «Процессы (RAM/CPU)»).'],
        'disk'   => ['output' => 'Занято и свободно по разделу /, процент. Кнопка «Подробнее» — по каждому разделу место и inodes.', 'actions' => null],
        'nginx'  => ['output' => 'Статус процесса Nginx. «Подробнее» — версия, результат nginx -t.', 'actions' => null],
        'pm2'    => ['output' => 'Список процессов PM2 и кнопка «Управление». В управлении — выбор процесса, затем статус и кнопки Start/Stop/Restart.', 'actions' => null],
        'sites'  => ['output' => 'Проверка доступности доменов из списка (настройки по кнопке ⚙️): запрос HTTPS, при неудаче — HTTP; код ответа 2xx/3xx = успех.', 'actions' => null],
        'reboot' => ['output' => null, 'actions' => 'Запрос подтверждения, затем перезагрузка сервера через 5 сек. Требует: скрипт bot-reboot.sh и права в sudoers.'],
        'banned_ips' => ['output' => 'Нумерованный список IP, забаненных в jail nginx-limit-req (fail2ban), с пагинацией по 10.', 'actions' => 'Кнопка «Разбанить» — ввод номера по списку (1, 2, 3…), затем разбан. Требует: скрипт /usr/local/bin/bot-fail2ban.sh и права в sudoers (bot-fail2ban.sh *).'],
    ],
    'en' => [
        'ram'    => ['output' => 'Memory summary: used/total (MiB), usage %.', 'actions' => '"Show processes" — top by RAM. "Restart" and "Stop" buttons ask for PID. Enable in "Processes (RAM/CPU)" block.'],
        'cpu'    => ['output' => 'Load average (1m, 5m, 15m), core count, load %, uptime.', 'actions' => '"Show processes" — top by CPU; then "Restart"/"Stop" with PID (settings in "Processes (RAM/CPU)" block).'],
        'disk'   => ['output' => 'Used and free for /, percentage. "Details" shows each mount and inodes.', 'actions' => null],
        'nginx'  => ['output' => 'Nginx process status. "Details" — version, nginx -t result.', 'actions' => null],
        'pm2'    => ['output' => 'PM2 process list and "Manage" button. Then pick process, status and Start/Stop/Restart.', 'actions' => null],
        'sites'  => ['output' => 'Domain availability from list (⚙️): HTTPS first, then HTTP; 2xx/3xx = success.', 'actions' => null],
        'reboot' => ['output' => null, 'actions' => 'Confirmation then server reboot in 5 sec. Requires: bot-reboot.sh and sudoers.'],
        'banned_ips' => ['output' => 'Numbered list of IPs banned in jail nginx-limit-req (fail2ban), 10 per page.', 'actions' => '"Unban" — enter list number (1, 2, 3…). Requires: /usr/local/bin/bot-fail2ban.sh and sudoers (bot-fail2ban.sh *).'],
    ],
];

$t = $translations[$lang];
$moduleLabelsByLang = $moduleLabels[$lang];
$moduleDescriptionsByLang = $moduleDescriptions[$lang];

$configPath = __DIR__ . '/config.php';
$defaults = [
    'bot_token'             => '',
    'webhook_secret'        => '',
    'allowed_username'      => '',
    'sites_domains'         => ['example.com'],
    'modules'               => ['ram', 'cpu', 'disk', 'nginx', 'pm2', 'sites', 'reboot', 'banned_ips'],
    'process_list_limit'    => 10,
    'allow_process_stop'    => false,
    'allow_process_restart' => false,
    'disk_alert_threshold_percent' => 90,
];

// Ответ JSON для определения технологий (action=detect)
if (isset($_GET['action']) && $_GET['action'] === 'detect') {
    header('Content-Type: application/json; charset=utf-8');
    $detectScript = __DIR__ . '/scripts/detect-technologies.php';
    if (is_readable($detectScript)) {
        ob_start();
        include $detectScript;
        $out = ob_get_clean();
        echo $out !== false ? $out : json_encode(['suggested' => $defaults['modules'], 'found' => []]);
    } else {
        echo json_encode(['suggested' => $defaults['modules'], 'found' => []]);
    }
    return;
}

$config = file_exists($configPath) ? (require $configPath) : [];
if (!is_array($config)) {
    $config = [];
}
$config = array_merge($defaults, $config);

$saved = false;
$error = '';
$warnMonitorEnv = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = trim((string) ($_POST['bot_token'] ?? ''));
    $secret = trim((string) ($_POST['webhook_secret'] ?? ''));
    $username = trim((string) ($_POST['allowed_username'] ?? ''));
    $sitesRaw = trim((string) ($_POST['sites_domains'] ?? ''));
    $sites = array_values(array_filter(array_map('trim', preg_split('/[\r\n]+/', $sitesRaw)), static function ($d) {
        return $d !== '' && preg_match('/^[a-zA-Z0-9._-]+$/i', $d);
    }));
    if ($sites === []) {
        $sites = ['example.com'];
    }
    $modules = isset($_POST['modules']) && is_array($_POST['modules'])
        ? array_values(array_intersect($_POST['modules'], ['ram', 'cpu', 'disk', 'nginx', 'pm2', 'sites', 'reboot', 'banned_ips']))
        : $defaults['modules'];
    $processListLimit = (int) ($_POST['process_list_limit'] ?? $defaults['process_list_limit']);
    $processListLimit = max(1, min(25, $processListLimit));
    $allowProcessStop = !empty($_POST['allow_process_stop']);
    $allowProcessRestart = !empty($_POST['allow_process_restart']);
    $diskAlertThreshold = (int) ($_POST['disk_alert_threshold_percent'] ?? $defaults['disk_alert_threshold_percent']);
    $diskAlertThreshold = max(1, min(99, $diskAlertThreshold));

    if ($token === '' || $secret === '') {
        $error = $t['error_required'];
    } else {
        $php = "<?php\n/** Generated by settings UI. Do not commit. */\nreturn [\n";
        $php .= "    'bot_token'        => " . var_export($token, true) . ",\n";
        $php .= "    'webhook_secret'   => " . var_export($secret, true) . ",\n";
        $php .= "    'allowed_username' => " . var_export($username, true) . ",\n";
        $php .= "    'sites_domains'    => " . var_export($sites, true) . ",\n";
        $php .= "    'modules'               => " . var_export($modules, true) . ",\n";
        $php .= "    'process_list_limit'    => " . $processListLimit . ",\n";
        $php .= "    'allow_process_stop'    => " . ($allowProcessStop ? 'true' : 'false') . ",\n";
        $php .= "    'allow_process_restart' => " . ($allowProcessRestart ? 'true' : 'false') . ",\n";
        $php .= "    'disk_alert_threshold_percent' => " . $diskAlertThreshold . ",\n";
        $php .= "];\n";

        if (@file_put_contents($configPath, $php) !== false) {
            $saved = true;
            $config = [
                'bot_token' => $token, 'webhook_secret' => $secret, 'allowed_username' => $username,
                'sites_domains' => $sites, 'modules' => $modules,
                'process_list_limit' => $processListLimit, 'allow_process_stop' => $allowProcessStop, 'allow_process_restart' => $allowProcessRestart,
                'disk_alert_threshold_percent' => $diskAlertThreshold,
            ];
            $envLine = 'DISK_ALERT_THRESHOLD_PERCENT=' . $diskAlertThreshold . "\n";
            $envPath = __DIR__ . '/monitor-threshold.env';
            if (@file_put_contents($envPath, $envLine) === false) {
                $warnMonitorEnv = true;
            }
        } else {
            $error = $t['error_write'];
        }
    }
}

$sitesText = implode("\n", $config['sites_domains'] ?? []);
$configModules = $config['modules'] ?? $defaults['modules'];
if (!is_array($configModules)) {
    $configModules = $defaults['modules'];
}
$webhookUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname($_SERVER['SCRIPT_NAME'] ?? '') . '/webhook.php';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($t['title'], ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        :root { --bg: #1a1b26; --surface: #24283b; --text: #c0caf5; --muted: #565f89; --accent: #7aa2f7; --success: #9ece6a; --error: #f7768e; }
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--bg); color: var(--text); margin: 0; padding: 1.5rem; line-height: 1.5; }
        .container { max-width: 560px; margin: 0 auto; }
        h1 { font-size: 1.35rem; margin: 0 0 1rem; color: var(--accent); }
        .card { background: var(--surface); border-radius: 8px; padding: 1.25rem; margin-bottom: 1rem; }
        label { display: block; margin-bottom: 0.25rem; font-size: 0.9rem; color: var(--muted); }
        input[type="text"], input[type="password"], textarea { width: 100%; padding: 0.5rem 0.75rem; background: var(--bg); border: 1px solid var(--muted); border-radius: 4px; color: var(--text); font-size: 0.95rem; }
        textarea { min-height: 120px; resize: vertical; font-family: inherit; }
        .hint { font-size: 0.8rem; color: var(--muted); margin-top: 0.25rem; }
        button { background: var(--accent); color: var(--bg); border: none; padding: 0.6rem 1.2rem; border-radius: 4px; font-size: 0.95rem; cursor: pointer; }
        button:hover { filter: brightness(1.1); }
        .btn-secondary { background: var(--muted); margin-top: 0.5rem; }
        .btn-secondary:hover { filter: brightness(1.2); }
        .tabs { display: flex; gap: 0; margin-bottom: 0; border-bottom: 1px solid var(--muted); }
        .tabs .tab-btn { background: transparent; color: var(--muted); padding: 0.6rem 1rem; border: none; border-bottom: 2px solid transparent; cursor: pointer; font-size: 0.95rem; }
        .tabs .tab-btn:hover { color: var(--text); }
        .tabs .tab-btn.active { color: var(--accent); border-bottom-color: var(--accent); }
        .tab-panel { display: none; padding: 1.25rem 0 0; }
        .tab-panel.active { display: block; }
        .module-block { background: var(--bg); border: 1px solid var(--muted); border-radius: 6px; padding: 1rem; margin-bottom: 0.75rem; }
        .module-block label { display: flex; align-items: flex-start; gap: 0.75rem; cursor: pointer; margin-bottom: 0; }
        .module-block input[type="checkbox"] { margin-top: 0.2rem; flex-shrink: 0; }
        .module-block .module-body { flex: 1; min-width: 0; }
        .module-block .module-title { font-weight: 600; color: var(--text); margin-bottom: 0.35rem; }
        .module-block .module-desc { font-size: 0.875rem; color: var(--muted); line-height: 1.45; }
        .module-block .module-desc strong { color: var(--text); }
        .module-block .module-row { display: flex; align-items: flex-start; gap: 0.5rem; }
        .module-block .module-row label { flex: 1; min-width: 0; }
        .module-settings-btn { flex-shrink: 0; width: 2rem; height: 2rem; padding: 0; border: none; border-radius: 4px; background: transparent; color: var(--muted); cursor: pointer; font-size: 1.1rem; display: flex; align-items: center; justify-content: center; }
        .module-settings-btn:hover { background: var(--muted); color: var(--text); }
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 100; align-items: center; justify-content: center; padding: 1rem; }
        .modal-overlay.visible { display: flex; }
        .modal-box { background: var(--surface); border-radius: 8px; padding: 1.25rem; max-width: 360px; width: 100%; border: 1px solid var(--muted); }
        .modal-box p { margin: 0 0 0.5rem; color: var(--muted); }
        .modal-box .modal-title { font-weight: 600; color: var(--accent); margin-bottom: 0.5rem; }
        .modal-box .modal-close { display: block; margin-left: auto; }
        .modal-box textarea { margin-top: 0.25rem; }
        .input-row { display: flex; gap: 0.5rem; align-items: center; }
        .input-row input { flex: 1; }
        .toggle-edit-btn { flex-shrink: 0; width: 2.25rem; height: 2.25rem; padding: 0; border: 1px solid var(--muted); border-radius: 4px; background: var(--bg); color: var(--muted); cursor: pointer; font-size: 1rem; }
        .toggle-edit-btn:hover { color: var(--accent); border-color: var(--accent); }
        .toggle-edit-btn.editing { background: var(--accent); color: var(--bg); border-color: var(--accent); }
        input.lockable-field[readonly] { opacity: 0.85; }
        .msg { padding: 0.75rem; border-radius: 4px; margin-bottom: 1rem; }
        .msg.success { background: rgba(158, 206, 106, 0.2); color: var(--success); }
        .msg.error { background: rgba(247, 118, 142, 0.2); color: var(--error); }
        pre { background: var(--bg); padding: 0.75rem; border-radius: 4px; overflow-x: auto; font-size: 0.8rem; color: var(--muted); }
        code { font-family: ui-monospace, monospace; }
        .lang-switch { font-size: 0.9rem; color: var(--muted); margin-left: auto; }
        .lang-switch a { color: var(--muted); text-decoration: none; }
        .lang-switch a:hover { color: var(--accent); }
        .lang-switch a.active { color: var(--accent); font-weight: 600; }
        .header-row { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem; }
        .header-row h1 { margin: 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-row">
            <h1><?= htmlspecialchars($t['heading'], ENT_QUOTES, 'UTF-8') ?></h1>
            <span class="lang-switch" aria-label="Language">
                <a href="?lang=en" class="<?= $lang === 'en' ? 'active' : '' ?>">EN</a>
                <span aria-hidden="true"> · </span>
                <a href="?lang=ru" class="<?= $lang === 'ru' ? 'active' : '' ?>">RU</a>
            </span>
        </div>

        <?php if ($saved): ?>
            <div class="msg success"><?= htmlspecialchars($t['saved_ok'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($warnMonitorEnv): ?>
            <div class="msg error"><?= htmlspecialchars($t['warn_monitor_env'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="msg error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="post" class="card" id="settings-form">
            <div class="tabs" role="tablist">
                <button type="button" class="tab-btn active" role="tab" aria-selected="true" data-tab="modules"><?= htmlspecialchars($t['tab_modules'], ENT_QUOTES, 'UTF-8') ?></button>
                <button type="button" class="tab-btn" role="tab" aria-selected="false" data-tab="telegram"><?= htmlspecialchars($t['tab_telegram'], ENT_QUOTES, 'UTF-8') ?></button>
                <button type="button" class="tab-btn" role="tab" aria-selected="false" data-tab="monitor"><?= htmlspecialchars($t['tab_monitor'], ENT_QUOTES, 'UTF-8') ?></button>
            </div>

            <div id="panel-modules" class="tab-panel active" role="tabpanel">
                <p class="hint" style="margin-bottom: 1rem;"><?= htmlspecialchars($t['hint_modules'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php foreach ($moduleLabelsByLang as $key => $label):
                    $desc = $moduleDescriptionsByLang[$key] ?? ['output' => '', 'actions' => null];
                    $hasSettings = ($key === 'sites');
                ?>
                <div class="module-block" data-module="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="module-row">
                        <label>
                            <input type="checkbox" name="modules[]" value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"<?= in_array($key, $configModules, true) ? ' checked' : '' ?>>
                            <span class="module-body">
                                <span class="module-title"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                                <div class="module-desc">
                                    <?php if (!empty($desc['output'])): ?>
                                    <strong><?= htmlspecialchars($t['output_label'], ENT_QUOTES, 'UTF-8') ?></strong> <?= htmlspecialchars($desc['output'], ENT_QUOTES, 'UTF-8') ?>
                                    <?php endif; ?>
                                    <?php if (!empty($desc['actions'])): ?>
                                    <?php if (!empty($desc['output'])): ?><br><?php endif; ?>
                                    <strong><?= htmlspecialchars($t['actions_label'], ENT_QUOTES, 'UTF-8') ?></strong> <?= htmlspecialchars($desc['actions'], ENT_QUOTES, 'UTF-8') ?>
                                    <?php endif; ?>
                                </div>
                            </span>
                        </label>
                        <?php if ($hasSettings): ?>
                        <button type="button" class="module-settings-btn" data-module="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($t['module_settings_btn_title'], ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($t['module_settings_btn_title'], ENT_QUOTES, 'UTF-8') ?>">⚙️</button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <div class="module-block" style="margin-top: 1rem; padding: 1rem; background: var(--card-bg); border: 1px solid var(--muted); border-radius: 6px;">
                    <strong><?= htmlspecialchars($t['processes_block_title'], ENT_QUOTES, 'UTF-8') ?></strong>
                    <p class="hint" style="margin: 0.5rem 0 0.75rem;"><?= htmlspecialchars($t['processes_block_hint'], ENT_QUOTES, 'UTF-8') ?></p>
                    <div style="margin-bottom: 0.75rem;">
                        <label for="process_list_limit"><?= htmlspecialchars($t['process_list_limit_label'], ENT_QUOTES, 'UTF-8') ?></label>
                        <input type="number" id="process_list_limit" name="process_list_limit" value="<?= (int) ($config['process_list_limit'] ?? 10) ?>" min="1" max="25" style="width: 5rem;">
                        <span class="hint"><?= htmlspecialchars($t['process_list_limit_hint'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <label style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                        <input type="checkbox" name="allow_process_stop" value="1"<?= !empty($config['allow_process_stop']) ? ' checked' : '' ?>>
                        <?= htmlspecialchars($t['allow_stop'], ENT_QUOTES, 'UTF-8') ?>
                    </label>
                    <label style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" name="allow_process_restart" value="1"<?= !empty($config['allow_process_restart']) ? ' checked' : '' ?>>
                        <?= htmlspecialchars($t['allow_restart'], ENT_QUOTES, 'UTF-8') ?>
                    </label>
                </div>
                <button type="button" id="btn-detect" class="btn-secondary" style="margin-top: 0.5rem;"><?= htmlspecialchars($t['btn_detect'], ENT_QUOTES, 'UTF-8') ?></button>
                <div class="hint" id="detect-hint" style="margin-top: 0.25rem; display: none;"></div>
            </div>

            <div id="panel-monitor" class="tab-panel" role="tabpanel">
                <p class="hint" style="margin-bottom: 1rem;"><?= htmlspecialchars($t['disk_threshold_hint'], ENT_QUOTES, 'UTF-8') ?></p>
                <label for="disk_alert_threshold_percent"><?= htmlspecialchars($t['disk_threshold_label'], ENT_QUOTES, 'UTF-8') ?></label>
                <input type="number" id="disk_alert_threshold_percent" name="disk_alert_threshold_percent" value="<?= (int) ($config['disk_alert_threshold_percent'] ?? 90) ?>" min="1" max="99" style="width: 5rem; margin-top: 0.25rem;">
            </div>

            <div id="panel-telegram" class="tab-panel" role="tabpanel">
                <div class="field-with-toggle" style="margin-bottom: 1rem;">
                    <label for="bot_token"><?= htmlspecialchars($t['bot_token_label'], ENT_QUOTES, 'UTF-8') ?></label>
                    <div class="input-row">
                        <input type="password" id="bot_token" name="bot_token" value="<?= htmlspecialchars($config['bot_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>" autocomplete="off" placeholder="<?= htmlspecialchars($t['bot_token_placeholder'], ENT_QUOTES, 'UTF-8') ?>" readonly class="lockable-field">
                        <button type="button" class="toggle-edit-btn" data-for="bot_token" title="<?= htmlspecialchars($t['toggle_edit_title'], ENT_QUOTES, 'UTF-8') ?>">✏️</button>
                    </div>
                </div>
                <div class="field-with-toggle" style="margin-bottom: 1rem;">
                    <label for="webhook_secret"><?= htmlspecialchars($t['webhook_secret_label'], ENT_QUOTES, 'UTF-8') ?></label>
                    <div class="input-row">
                        <input type="password" id="webhook_secret" name="webhook_secret" value="<?= htmlspecialchars($config['webhook_secret'] ?? '', ENT_QUOTES, 'UTF-8') ?>" autocomplete="off" placeholder="<?= htmlspecialchars($t['webhook_secret_placeholder'], ENT_QUOTES, 'UTF-8') ?>" readonly class="lockable-field">
                        <button type="button" class="toggle-edit-btn" data-for="webhook_secret" title="<?= htmlspecialchars($t['toggle_edit_title'], ENT_QUOTES, 'UTF-8') ?>">✏️</button>
                    </div>
                    <div class="hint"><?= htmlspecialchars($t['webhook_secret_hint'], ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <div style="margin-bottom: 1rem;">
                    <label for="allowed_username"><?= htmlspecialchars($t['allowed_username_label'], ENT_QUOTES, 'UTF-8') ?></label>
                    <input type="text" id="allowed_username" name="allowed_username" value="<?= htmlspecialchars($config['allowed_username'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="<?= htmlspecialchars($t['allowed_username_placeholder'], ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div style="margin-bottom: 1rem;">
                    <strong><?= htmlspecialchars($t['webhook_url_heading'], ENT_QUOTES, 'UTF-8') ?></strong>
                    <p class="hint"><?= htmlspecialchars($t['webhook_url_hint'], ENT_QUOTES, 'UTF-8') ?></p>
                    <pre id="webhook_url" style="margin: 0.5rem 0;"><?= htmlspecialchars($webhookUrl, ENT_QUOTES, 'UTF-8') ?></pre>
                    <p class="hint"><?= htmlspecialchars($t['webhook_curl_hint'], ENT_QUOTES, 'UTF-8') ?></p>
                    <pre style="margin-top: 0.25rem;">curl -s -X POST "https://api.telegram.org/bot&lt;TOKEN&gt;/setWebhook" \
  -H "Content-Type: application/json" \
  -d '{"url":"https://ваш-домен/webhook.php","secret_token":"&lt;SECRET&gt;"}'</pre>
                </div>
            </div>

            <div style="margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid var(--muted);">
                <button type="submit"><?= htmlspecialchars($t['save_btn'], ENT_QUOTES, 'UTF-8') ?></button>
            </div>

            <div id="modal-sites" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="modal-sites-title">
                <div class="modal-box">
                    <p id="modal-sites-title" class="modal-title"><?= htmlspecialchars($t['modal_sites_title'], ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="hint" style="margin-bottom: 0.75rem;"><?= htmlspecialchars($t['modal_sites_hint'], ENT_QUOTES, 'UTF-8') ?></p>
                    <label for="sites_domains"><?= htmlspecialchars($t['sites_domains_label'], ENT_QUOTES, 'UTF-8') ?></label>
                    <textarea id="sites_domains" name="sites_domains" placeholder="<?= htmlspecialchars($t['sites_domains_placeholder'], ENT_QUOTES, 'UTF-8') ?>" style="min-height: 180px; margin-top: 0.25rem;"><?= htmlspecialchars($sitesText, ENT_QUOTES, 'UTF-8') ?></textarea>
                    <div class="hint" style="margin-top: 0.25rem;"><?= htmlspecialchars($t['sites_domains_hint'], ENT_QUOTES, 'UTF-8') ?></div>
                    <button type="button" class="modal-close btn-secondary" style="margin-top: 1rem;"><?= htmlspecialchars($t['close_btn'], ENT_QUOTES, 'UTF-8') ?></button>
                </div>
            </div>
        </form>
    </div>

    <script>
window.TSM_I18n = {
    detect_checking: <?= json_encode($t['detect_checking'], JSON_UNESCAPED_UNICODE) ?>,
    detect_done: <?= json_encode($t['detect_done'], JSON_UNESCAPED_UNICODE) ?>,
    detect_error: <?= json_encode($t['detect_error'], JSON_UNESCAPED_UNICODE) ?>,
    detect_found: <?= json_encode($t['detect_found'], JSON_UNESCAPED_UNICODE) ?>,
    toggle_edit_title: <?= json_encode($t['toggle_edit_title'], JSON_UNESCAPED_UNICODE) ?>,
    toggle_lock_title: <?= json_encode($t['toggle_lock_title'], JSON_UNESCAPED_UNICODE) ?>
};
(function() {
    var form = document.getElementById('settings-form');
    function switchTab(tabId) {
        if (!form) return;
        form.querySelectorAll('.tab-btn').forEach(function(b) {
            var isActive = b.getAttribute('data-tab') === tabId;
            b.classList.toggle('active', isActive);
            b.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
        form.querySelectorAll('.tab-panel').forEach(function(p) {
            p.classList.toggle('active', p.id === 'panel-' + tabId);
        });
    }
    if (form) {
        form.querySelectorAll('.tab-btn').forEach(function(btn) {
            btn.addEventListener('click', function() { switchTab(this.getAttribute('data-tab')); });
        });
        form.querySelectorAll('.module-settings-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                if (this.getAttribute('data-module') === 'sites') {
                    var m = document.getElementById('modal-sites');
                    if (m) m.classList.add('visible');
                }
            });
        });
    }
    var modalSites = document.getElementById('modal-sites');
    if (modalSites) {
        modalSites.querySelector('.modal-close').addEventListener('click', function() { modalSites.classList.remove('visible'); });
        modalSites.addEventListener('click', function(e) { if (e.target === modalSites) modalSites.classList.remove('visible'); });
    }
    var i18n = window.TSM_I18n || {};
    document.querySelectorAll('.toggle-edit-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = this.getAttribute('data-for');
            var input = document.getElementById(id);
            if (!input) return;
            var isReadonly = input.hasAttribute('readonly');
            if (isReadonly) { input.removeAttribute('readonly'); this.classList.add('editing'); this.setAttribute('title', i18n.toggle_lock_title || 'Lock'); this.textContent = '🔒'; }
            else { input.setAttribute('readonly', 'readonly'); this.classList.remove('editing'); this.setAttribute('title', i18n.toggle_edit_title || 'Edit'); this.textContent = '✏️'; }
        });
    });
    var formEl = document.getElementById('settings-form');
    if (formEl) {
        formEl.addEventListener('submit', function() {
            ['bot_token', 'webhook_secret'].forEach(function(id) {
                var input = document.getElementById(id);
                if (input) { input.setAttribute('readonly', 'readonly'); }
                var t = document.querySelector('.toggle-edit-btn[data-for="' + id + '"]');
                if (t) { t.classList.remove('editing'); t.textContent = '✏️'; t.setAttribute('title', i18n.toggle_edit_title || 'Edit'); }
            });
        });
    }
    var btn = document.getElementById('btn-detect');
    var hint = document.getElementById('detect-hint');
    if (btn && hint) {
        btn.addEventListener('click', function() {
            hint.style.display = 'block';
            hint.textContent = i18n.detect_checking || 'Checking…';
            fetch('?action=detect').then(function(r) { return r.json(); }).then(function(data) {
                var suggested = data.suggested || [];
                var found = data.found || {};
                document.querySelectorAll('input[name="modules[]"]').forEach(function(cb) {
                    cb.checked = suggested.indexOf(cb.value) !== -1;
                });
                var list = [];
                if (found.nginx) list.push('Nginx');
                if (found.pm2) list.push('PM2');
                if (found.php_fpm) list.push('PHP-FPM');
                if (found.mysql) list.push('MySQL/MariaDB');
                if (found.docker) list.push('Docker');
                var fmt = i18n.detect_found || 'Found: %s.';
                hint.textContent = list.length ? (fmt.indexOf('%s') !== -1 ? fmt.replace('%s', list.join(', ')) : fmt.replace(/%s/g, list.join(', '))) : (i18n.detect_done || 'Done.');
            }).catch(function() { hint.textContent = i18n.detect_error || 'Error.'; });
        });
    }
})();
    </script>
</body>
</html>
