<?php
/**
 * Telegram webhook: мониторинг сервера по кнопкам (RAM, CPU, диск, Nginx, PM2, сайты).
 * Доступ только от allowed_username (см. config.php). Запросы проверяются по X-Telegram-Bot-Api-Secret-Token.
 */

declare(strict_types=1);

/** Список доменов по умолчанию для кнопки «Сайты» (если в config нет sites_domains). */
const SITES_DOMAINS_DEFAULT = ['example.com'];

$config = require __DIR__ . '/config.php';
$token  = $config['bot_token'];
$secret = $config['webhook_secret'];
$allowedUsername = isset($config['allowed_username']) ? strtolower(trim((string) $config['allowed_username'])) : '';
$sitesDomains = !empty($config['sites_domains']) && is_array($config['sites_domains'])
    ? $config['sites_domains']
    : SITES_DOMAINS_DEFAULT;
$enabledModules = isset($config['modules']) && is_array($config['modules'])
    ? array_map('strtolower', $config['modules'])
    : ['ram', 'cpu', 'disk', 'nginx', 'pm2', 'sites', 'reboot', 'banned_ips'];
$isModuleEnabled = static function (string $key) use ($enabledModules): bool {
    return in_array(strtolower($key), $enabledModules, true);
};
$processListLimit = isset($config['process_list_limit']) ? max(1, min(25, (int) $config['process_list_limit'])) : 10;
$allowProcessStop = !empty($config['allow_process_stop']);
$allowProcessRestart = !empty($config['allow_process_restart']);

const STATE_TTL = 300;

function getStatePath(int $chatId): string {
    $dir = __DIR__ . '/state';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    return $dir . '/' . $chatId . '.json';
}

function getPendingState(int $chatId): ?array {
    $path = getStatePath($chatId);
    if (!is_readable($path)) return null;
    $raw = @file_get_contents($path);
    if ($raw === false) return null;
    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data['action']) || empty($data['ts'])) return null;
    if (time() - (int) $data['ts'] > STATE_TTL) {
        @unlink($path);
        return null;
    }
    return $data;
}

function setPendingState(int $chatId, string $action): void {
    $path = getStatePath($chatId);
    $data = ['action' => $action, 'ts' => time()];
    @file_put_contents($path, json_encode($data));
}

function clearPendingState(int $chatId): void {
    $path = getStatePath($chatId);
    if (is_file($path)) @unlink($path);
}

require __DIR__ . '/helpers.php';

header('Content-Type: application/json');
http_response_code(200);

$headerSecret = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
if ($secret !== '' && $headerSecret !== $secret) {
    echo json_encode(['ok' => false, 'error' => 'forbidden']);
    exit;
}

$raw = file_get_contents('php://input');
$input = $raw ? json_decode($raw, true) : null;
if (!$input) {
    echo json_encode(['ok' => true]);
    exit;
}

$from = null;
if (isset($input['message']['from'])) {
    $from = $input['message']['from'];
} elseif (isset($input['callback_query']['from'])) {
    $from = $input['callback_query']['from'];
}
if ($allowedUsername !== '' && $from !== null) {
    $username = strtolower(trim((string) ($from['username'] ?? '')));
    if ($username !== '' && $username !== $allowedUsername) {
        echo json_encode(['ok' => true]);
        exit;
    }
}

$api = function (string $method, array $params) use ($token): array {
    $url = "https://api.telegram.org/bot{$token}/{$method}";
    $ctx = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => 'Content-Type: application/json',
            'content' => json_encode($params),
            'timeout' => 8,
        ],
    ]);
    $out = @file_get_contents($url, false, $ctx);
    return $out ? (json_decode($out, true) ?? []) : [];
};

$chatId = null;
$messageId = null;
$callbackQueryId = null;
$data = null;

if (isset($input['message'])) {
    $msg = $input['message'];
    $chatId = $msg['chat']['id'] ?? null;
    $text = trim((string) ($msg['text'] ?? ''));
    if ($chatId === null) {
        echo json_encode(['ok' => true]);
        exit;
    }
    $cid = (int) $chatId;
    $state = getPendingState($cid);
    if ($state !== null && $text !== '' && ctype_digit($text)) {
        $pid = (int) $text;
        $action = $state['action'];
        $allowed = false;
        $label = '';
        if (($action === 'ram_stop' || $action === 'cpu_stop') && $allowProcessStop) {
            $allowed = true;
            $label = 'остановить';
        } elseif (($action === 'ram_restart' || $action === 'cpu_restart') && $allowProcessRestart) {
            $allowed = true;
            $label = 'перезапустить';
        } elseif ($action === 'banned_ips_unban' && $isModuleEnabled('banned_ips')) {
            $allowed = true;
            $label = 'разбанить';
        }
        clearPendingState($cid);
        if (!$allowed) {
            $api('sendMessage', ['chat_id' => $chatId, 'text' => $action === 'banned_ips_unban' ? 'Модуль отключён.' : 'Управление процессами по PID отключено в настройках.']);
        } elseif ($action === 'banned_ips_unban') {
            $jail = 'nginx-limit-req';
            $ips = getBannedIpsList($jail);
            $idx = (int) $pid;
            if ($idx < 1 || $idx > count($ips)) {
                $api('sendMessage', ['chat_id' => $chatId, 'text' => 'Неверный номер. В списке от 1 до ' . count($ips) . '.']);
            } else {
                $ip = $ips[$idx - 1];
                $out = @shell_exec('timeout 5 sudo -n /usr/local/bin/bot-fail2ban.sh unban ' . escapeshellarg($jail) . ' ' . escapeshellarg($ip) . ' 2>&1');
                $api('sendMessage', ['chat_id' => $chatId, 'text' => '✅ IP ' . $ip . ' разбанен (jail: ' . $jail . ').']);
            }
        } else {
            $cmd = $action === 'ram_stop' || $action === 'cpu_stop' ? 'stop' : 'restart';
            $out = @shell_exec('timeout 10 sudo -n /usr/local/bin/bot-process-pid.sh ' . escapeshellarg($cmd) . ' ' . escapeshellarg((string) $pid) . ' 2>&1');
            $result = $out !== null ? trim($out) : 'Не удалось выполнить команду (проверьте sudo и скрипт bot-process-pid.sh).';
            $api('sendMessage', ['chat_id' => $chatId, 'text' => $result]);
        }
        echo json_encode(['ok' => true]);
        exit;
    }
    if ($state !== null) {
        clearPendingState($cid);
        $msg = $state['action'] === 'banned_ips_unban' ? 'Ожидался номер по списку. Действие отменено.' : 'Ожидался номер процесса (PID). Действие отменено.';
        $api('sendMessage', ['chat_id' => $chatId, 'text' => $msg]);
        echo json_encode(['ok' => true]);
        exit;
    }
    $showMenu = in_array($text, ['/start', 'статус', 'status', ''], true)
        || in_array(mb_strtolower($text), ['меню', '📋 меню', 'menu'], true);
    if ($showMenu) {
        $rows = [];
        $row1 = [];
        if ($isModuleEnabled('ram')) $row1[] = ['text' => '🖥 RAM', 'callback_data' => 'ram'];
        if ($isModuleEnabled('cpu')) $row1[] = ['text' => '📊 CPU', 'callback_data' => 'cpu'];
        if (!empty($row1)) $rows[] = $row1;
        $row2 = [];
        if ($isModuleEnabled('disk')) $row2[] = ['text' => '💾 Диск', 'callback_data' => 'disk'];
        if ($isModuleEnabled('nginx')) $row2[] = ['text' => '🌐 Nginx', 'callback_data' => 'nginx'];
        if (!empty($row2)) $rows[] = $row2;
        $row3 = [];
        if ($isModuleEnabled('pm2')) $row3[] = ['text' => '📦 PM2', 'callback_data' => 'pm2_list'];
        if ($isModuleEnabled('sites')) $row3[] = ['text' => '🌍 Сайты', 'callback_data' => 'sites_check'];
        if (!empty($row3)) $rows[] = $row3;
        if ($isModuleEnabled('banned_ips')) {
            $rows[] = [['text' => '🚫 Забаненные IP', 'callback_data' => 'banned_ips']];
        }
        if ($isModuleEnabled('ram') || $isModuleEnabled('cpu') || $isModuleEnabled('disk') || $isModuleEnabled('nginx')) {
            $rows[] = [['text' => 'Всё', 'callback_data' => 'all']];
        }
        if ($isModuleEnabled('reboot')) {
            $rows[] = [['text' => '🔄 Перезагрузить сервер', 'callback_data' => 'reboot_confirm']];
        }
        $keyboard = ['inline_keyboard' => $rows];
        if (empty($rows)) {
            $keyboard = ['inline_keyboard' => [[['text' => 'Нет включённых модулей', 'callback_data' => 'noop']]]];
        }
        $api('sendMessage', [
            'chat_id'      => $chatId,
            'text'         => 'Выберите параметр:',
            'reply_markup' => json_encode($keyboard),
        ]);
        $api('sendMessage', [
            'chat_id'      => $chatId,
            'text'         => ' ',
            'reply_markup' => json_encode([
                'keyboard'          => [['📋 Меню']],
                'resize_keyboard'   => true,
                'is_persistent'     => true,
                'one_time_keyboard' => false,
            ]),
        ]);
    }
    echo json_encode(['ok' => true]);
    exit;
}

if (isset($input['callback_query'])) {
    $cq = $input['callback_query'];
    $callbackQueryId = $cq['id'] ?? null;
    $chatId = $cq['message']['chat']['id'] ?? $cq['from']['id'] ?? null;
    $messageId = $cq['message']['message_id'] ?? null;
    $data = trim((string) ($cq['data'] ?? ''));
}

if ($chatId === null) {
    echo json_encode(['ok' => true]);
    exit;
}

if ($callbackQueryId !== null) {
    $api('answerCallbackQuery', ['callback_query_id' => $callbackQueryId]);
}

if ($data === null || $data === '') {
    $api('sendMessage', ['chat_id' => $chatId, 'text' => 'Неизвестная кнопка.']);
    echo json_encode(['ok' => true]);
    exit;
}

if ($data === 'noop') {
    echo json_encode(['ok' => true]);
    exit;
}

// Проверка включённости модуля по callback_data
$dataToModule = [
    'ram' => 'ram', 'ram_procs' => 'ram', 'ram_restart' => 'ram', 'ram_stop' => 'ram',
    'cpu' => 'cpu', 'cpu_procs' => 'cpu', 'cpu_restart' => 'cpu', 'cpu_stop' => 'cpu',
    'disk' => 'disk', 'disk_more' => 'disk', 'nginx' => 'nginx', 'nginx_more' => 'nginx',
    'pm2_list' => 'pm2', 'pm2_manage' => 'pm2', 'sites_check' => 'sites',
    'reboot_confirm' => 'reboot', 'reboot_yes' => 'reboot', 'reboot_cancel' => 'reboot',
    'banned_ips' => 'banned_ips', 'banned_ips_unban' => 'banned_ips',
];
$moduleForData = $dataToModule[$data] ?? null;
if ($moduleForData === null && strpos($data, 'pm2_') === 0) $moduleForData = 'pm2';
if ($moduleForData === null && strpos($data, 'banned_ips_page_') === 0) $moduleForData = 'banned_ips';
if ($moduleForData !== null && !$isModuleEnabled($moduleForData)) {
    $api('sendMessage', ['chat_id' => $chatId, 'text' => 'Модуль отключён в настройках.']);
    echo json_encode(['ok' => true]);
    exit;
}

if (strpos($data, 'restart_svc_') === 0) {
    $svc = substr($data, strlen('restart_svc_'));
    $allowed = ['nginx' => 'nginx', 'phpfpm' => 'php-fpm', 'mysqld' => 'mysqld', 'mariadb' => 'mariadb'];
    if (isset($allowed[$svc])) {
        $out = @shell_exec('timeout 15 sudo -n /usr/local/bin/bot-restart-service.sh ' . escapeshellarg($allowed[$svc]) . ' 2>&1');
        $text = $out !== null && trim($out) === '' ? '✅ ' . $allowed[$svc] . ' перезапущен.' : '⚠️ ' . trim($out ?: 'Ошибка');
    } else {
        $text = 'Неизвестный сервис.';
    }
    $api('sendMessage', ['chat_id' => $chatId, 'text' => $text]);
    echo json_encode(['ok' => true]);
    exit;
}

if (strpos($data, 'pm2_restart_') === 0) {
    $arg = substr($data, strlen('pm2_restart_'));
    $cmd = $arg === 'all' ? 'all' : $arg;
    $out = @shell_exec('timeout 30 sudo -n /usr/local/bin/bot-pm2-restart.sh ' . escapeshellarg($cmd) . ' 2>&1');
    $text = $out !== null ? trim($out) : 'Выполнено.';
    if (strlen($text) > 4000) $text = substr($text, 0, 3997) . '…';
    $api('sendMessage', ['chat_id' => $chatId, 'text' => '🔄 PM2 restart: ' . $text]);
    echo json_encode(['ok' => true]);
    exit;
}
if (strpos($data, 'pm2_stop_') === 0) {
    $id = substr($data, strlen('pm2_stop_'));
    $out = @shell_exec('timeout 10 sudo -n /usr/local/bin/bot-pm2-stop.sh ' . escapeshellarg($id) . ' 2>&1');
    $api('sendMessage', ['chat_id' => $chatId, 'text' => '⏹ PM2 stop: ' . trim($out ?: 'OK')]);
    echo json_encode(['ok' => true]);
    exit;
}
if (strpos($data, 'pm2_start_') === 0) {
    $id = substr($data, strlen('pm2_start_'));
    $out = @shell_exec('timeout 10 sudo -n /usr/local/bin/bot-pm2-start.sh ' . escapeshellarg($id) . ' 2>&1');
    $api('sendMessage', ['chat_id' => $chatId, 'text' => '▶️ PM2 start: ' . trim($out ?: 'OK')]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($data === 'ram') {
    $text = getServerStat('ram');
    $keyboard = ['inline_keyboard' => [[['text' => 'Показать процессы', 'callback_data' => 'ram_procs']]]];
    $api('sendMessage', [
        'chat_id'      => $chatId,
        'text'         => $text,
        'parse_mode'   => 'HTML',
        'reply_markup' => json_encode($keyboard),
    ]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($data === 'ram_procs') {
    $api('sendMessage', ['chat_id' => $chatId, 'text' => '⏳ Получаю список процессов…']);
    try {
        $result = getRamProcessesList($processListLimit);
        $rows = [];
        if ($allowProcessRestart) $rows[] = ['text' => '🔄 Перезапустить', 'callback_data' => 'ram_restart'];
        if ($allowProcessStop) $rows[] = ['text' => '⏹ Остановить', 'callback_data' => 'ram_stop'];
        $keyboard = ['inline_keyboard' => $rows ? [array_chunk($rows, 2)[0]] : []];
        $params = ['chat_id' => $chatId, 'text' => $result['text'], 'parse_mode' => 'HTML'];
        if (!empty($keyboard['inline_keyboard'][0])) $params['reply_markup'] = json_encode($keyboard);
        $api('sendMessage', $params);
    } catch (Throwable $e) {
        $api('sendMessage', ['chat_id' => $chatId, 'text' => 'Ошибка: ' . $e->getMessage()]);
    }
    echo json_encode(['ok' => true]);
    exit;
}

if ($data === 'ram_restart' || $data === 'ram_stop') {
    if (($data === 'ram_restart' && !$allowProcessRestart) || ($data === 'ram_stop' && !$allowProcessStop)) {
        $api('sendMessage', ['chat_id' => $chatId, 'text' => 'Действие отключено в настройках.']);
        echo json_encode(['ok' => true]);
        exit;
    }
    $label = $data === 'ram_restart' ? 'перезапуска' : 'остановки';
    setPendingState((int) $chatId, $data);
    $api('sendMessage', ['chat_id' => $chatId, 'text' => "Введите PID процесса для {$label} (одним числом):"]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($data === 'cpu') {
    $text = getServerStat('cpu');
    $keyboard = ['inline_keyboard' => [[['text' => 'Показать процессы', 'callback_data' => 'cpu_procs']]]];
    $api('sendMessage', [
        'chat_id'      => $chatId,
        'text'         => $text,
        'parse_mode'   => 'HTML',
        'reply_markup' => json_encode($keyboard),
    ]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($data === 'cpu_procs') {
    $api('sendMessage', ['chat_id' => $chatId, 'text' => '⏳ Получаю список процессов…']);
    try {
        $result = getCpuProcessesList($processListLimit);
        $rows = [];
        if ($allowProcessRestart) $rows[] = ['text' => '🔄 Перезапустить', 'callback_data' => 'cpu_restart'];
        if ($allowProcessStop) $rows[] = ['text' => '⏹ Остановить', 'callback_data' => 'cpu_stop'];
        $keyboard = ['inline_keyboard' => $rows ? [array_chunk($rows, 2)[0]] : []];
        $params = ['chat_id' => $chatId, 'text' => $result['text'], 'parse_mode' => 'HTML'];
        if (!empty($keyboard['inline_keyboard'][0])) $params['reply_markup'] = json_encode($keyboard);
        $api('sendMessage', $params);
    } catch (Throwable $e) {
        $api('sendMessage', ['chat_id' => $chatId, 'text' => 'Ошибка: ' . $e->getMessage()]);
    }
    echo json_encode(['ok' => true]);
    exit;
}

if ($data === 'cpu_restart' || $data === 'cpu_stop') {
    if (($data === 'cpu_restart' && !$allowProcessRestart) || ($data === 'cpu_stop' && !$allowProcessStop)) {
        $api('sendMessage', ['chat_id' => $chatId, 'text' => 'Действие отключено в настройках.']);
        echo json_encode(['ok' => true]);
        exit;
    }
    $label = $data === 'cpu_restart' ? 'перезапуска' : 'остановки';
    setPendingState((int) $chatId, $data);
    $api('sendMessage', ['chat_id' => $chatId, 'text' => "Введите PID процесса для {$label} (одним числом):"]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($data === 'disk') {
    $text = getServerStat('disk');
    $keyboard = ['inline_keyboard' => [[['text' => 'Подробнее', 'callback_data' => 'disk_more']]]];
    $api('sendMessage', ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML', 'reply_markup' => json_encode($keyboard)]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($data === 'disk_more') {
    $out = @shell_exec('df -h 2>/dev/null; echo "---"; df -i 2>/dev/null');
    $host = htmlspecialchars(gethostname() ?: 'server', ENT_QUOTES, 'UTF-8');
    $text = "<b>{$host}</b>\n💾 Диск (место и inodes):\n<pre>" . htmlspecialchars($out ? trim($out) : 'Нет данных', ENT_QUOTES, 'UTF-8') . '</pre>';
    if (strlen($text) > 4000) $text = substr($text, 0, 3997) . '</pre>';
    $api('sendMessage', ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML']);
    echo json_encode(['ok' => true]);
    exit;
}

if ($data === 'nginx') {
    $text = getServerStat('nginx');
    $keyboard = ['inline_keyboard' => [[['text' => 'Подробнее', 'callback_data' => 'nginx_more']]]];
    $api('sendMessage', ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML', 'reply_markup' => json_encode($keyboard)]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($data === 'nginx_more') {
    $v = @shell_exec('nginx -v 2>&1');
    $t = @shell_exec('nginx -t 2>&1');
    $host = htmlspecialchars(gethostname() ?: 'server', ENT_QUOTES, 'UTF-8');
    $text = "<b>{$host}</b>\n🌐 Nginx:\n<pre>Версия: " . htmlspecialchars(trim($v ?: '?'), ENT_QUOTES, 'UTF-8') . "\n\nПроверка конфига:\n" . htmlspecialchars(trim($t ?: '?'), ENT_QUOTES, 'UTF-8') . '</pre>';
    if (strlen($text) > 4000) $text = substr($text, 0, 3997) . '</pre>';
    $api('sendMessage', ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML']);
    echo json_encode(['ok' => true]);
    exit;
}

if ($data === 'pm2_list') {
    $result = getPm2ListSimple();
    $keyboard = ['inline_keyboard' => [[['text' => 'Управление', 'callback_data' => 'pm2_manage']]]];
    $api('sendMessage', [
        'chat_id'      => $chatId,
        'text'         => $result['text'],
        'parse_mode'   => 'HTML',
        'reply_markup' => json_encode($keyboard),
    ]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($data === 'pm2_manage') {
    $list = getPm2ListRaw();
    if (empty($list)) {
        $api('sendMessage', ['chat_id' => $chatId, 'text' => 'Нет процессов PM2.']);
        echo json_encode(['ok' => true]);
        exit;
    }
    $flat = [];
    foreach ($list as $i => $proc) {
        $name = $proc['name'] ?? ('app_' . $i);
        $flat[] = ['text' => $name, 'callback_data' => 'pm2_sel_' . $i];
    }
    $keyboard = ['inline_keyboard' => array_chunk($flat, 2)];
    $api('sendMessage', ['chat_id' => $chatId, 'text' => 'Выберите процесс:', 'reply_markup' => json_encode($keyboard)]);
    echo json_encode(['ok' => true]);
    exit;
}

if (strpos($data, 'pm2_sel_') === 0) {
    $id = (int) substr($data, strlen('pm2_sel_'));
    $list = getPm2ListRaw();
    if (!isset($list[$id])) {
        $api('sendMessage', ['chat_id' => $chatId, 'text' => 'Процесс не найден.']);
        echo json_encode(['ok' => true]);
        exit;
    }
    $proc = $list[$id];
    $name = $proc['name'] ?? ('app_' . $id);
    $status = $proc['pm2_env']['status'] ?? $proc['status'] ?? '?';
    $pid = $proc['pid'] ?? '-';
    $mem = isset($proc['monit']['memory']) ? round($proc['monit']['memory'] / 1024 / 1024, 1) . ' MiB' : '?';
    $cpu = isset($proc['monit']['cpu']) ? $proc['monit']['cpu'] . '%' : '?';
    $online = ($status === 'online' || $status === 'launching');
    $lines = ["<b>{$name}</b> (id: {$id})", "Статус: {$status}", "PID: {$pid}", "Память: {$mem}", "CPU: {$cpu}"];
    $buttons = [];
    $buttons[] = ['text' => ($online ? '⏹ Stop' : '▶ Start'), 'callback_data' => $online ? 'pm2_stop_' . $id : 'pm2_start_' . $id];
    if ($online) $buttons[] = ['text' => '🔄 Restart', 'callback_data' => 'pm2_restart_' . $id];
    $keyboard = ['inline_keyboard' => [$buttons]];
    $api('sendMessage', [
        'chat_id'      => $chatId,
        'text'         => implode("\n", $lines),
        'parse_mode'   => 'HTML',
        'reply_markup' => json_encode($keyboard),
    ]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($data === 'sites_check') {
    $api('sendMessage', ['chat_id' => $chatId, 'text' => '⏳ Проверяю сайты…']);
    try {
        $text = getSitesCheckResult($sitesDomains);
        $api('sendMessage', [
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'HTML',
        ]);
    } catch (Throwable $e) {
        $api('sendMessage', ['chat_id' => $chatId, 'text' => 'Ошибка проверки сайтов: ' . $e->getMessage()]);
    }
    echo json_encode(['ok' => true]);
    exit;
}

if ($data === 'reboot_confirm') {
    $api('sendMessage', [
        'chat_id'   => $chatId,
        'text'      => "⚠️ <b>Перезагрузка сервера</b>\n\nВы уверены? Сервер перезагрузится через несколько секунд. Это действие нельзя отменить.",
        'parse_mode'=> 'HTML',
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => '✅ Да, перезагрузить', 'callback_data' => 'reboot_yes']],
                [['text' => '❌ Отмена', 'callback_data' => 'reboot_cancel']],
            ],
        ]),
    ]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($data === 'reboot_cancel') {
    $api('editMessageText', [
        'chat_id'    => $chatId,
        'message_id' => $messageId,
        'text'       => 'Действие отменено.',
        'reply_markup' => json_encode(['inline_keyboard' => []]),
    ]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($data === 'reboot_yes') {
    $api('sendMessage', [
        'chat_id' => $chatId,
        'text'    => '🔄 Перезагрузка запущена. Сервер будет перезагружен через 5 сек.',
    ]);
    @shell_exec('nohup sudo -n /usr/local/bin/bot-reboot.sh > /dev/null 2>&1 &');
    echo json_encode(['ok' => true]);
    exit;
}

const BANNED_IPS_JAIL = 'nginx-limit-req';
const BANNED_IPS_PER_PAGE = 10;

if ($data === 'banned_ips' || strpos($data, 'banned_ips_page_') === 0) {
    $page = 0;
    if (strpos($data, 'banned_ips_page_') === 0) {
        $page = (int) substr($data, strlen('banned_ips_page_'));
    }
    $ips = getBannedIpsList(BANNED_IPS_JAIL);
    $total = count($ips);
    $totalPages = $total > 0 ? (int) ceil($total / BANNED_IPS_PER_PAGE) : 1;
    $page = max(0, min($page, $totalPages - 1));
    $offset = $page * BANNED_IPS_PER_PAGE;
    $slice = array_slice($ips, $offset, BANNED_IPS_PER_PAGE);
    $lines = ["<b>🚫 Забаненные IP</b> (jail: " . BANNED_IPS_JAIL . ")\n"];
    if ($total === 0) {
        $lines[] = 'Нет забаненных IP.';
    } else {
        foreach ($slice as $i => $ip) {
            $num = $offset + $i + 1;
            $lines[] = $num . '. ' . htmlspecialchars($ip, ENT_QUOTES, 'UTF-8');
        }
        $lines[] = "\nВсего: " . $total . ". Страница " . ($page + 1) . ' из ' . $totalPages . '.';
    }
    $rows = [];
    if ($total > 0) {
        $nav = [];
        if ($page > 0) {
            $nav[] = ['text' => '◀ Назад', 'callback_data' => 'banned_ips_page_' . ($page - 1)];
        }
        if ($page < $totalPages - 1) {
            $nav[] = ['text' => 'Вперёд ▶', 'callback_data' => 'banned_ips_page_' . ($page + 1)];
        }
        if (!empty($nav)) $rows[] = $nav;
        $rows[] = [['text' => '✅ Разбанить', 'callback_data' => 'banned_ips_unban']];
    }
    $keyboard = ['inline_keyboard' => $rows];
    $api('sendMessage', [
        'chat_id'      => $chatId,
        'text'         => implode("\n", $lines),
        'parse_mode'   => 'HTML',
        'reply_markup' => json_encode($keyboard),
    ]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($data === 'banned_ips_unban') {
    setPendingState((int) $chatId, 'banned_ips_unban');
    $api('sendMessage', ['chat_id' => $chatId, 'text' => 'Введите номер по списку (1, 2, 3, …) для разбана:']);
    echo json_encode(['ok' => true]);
    exit;
}

if ($data === 'all') {
    $parts = [];
    if ($isModuleEnabled('ram')) $parts[] = getServerStat('ram');
    if ($isModuleEnabled('cpu')) $parts[] = getServerStat('cpu');
    if ($isModuleEnabled('disk')) $parts[] = getServerStat('disk');
    if ($isModuleEnabled('nginx')) $parts[] = getServerStat('nginx');
    $reply = implode("\n\n", $parts);
    if ($reply === '') $reply = 'Нет включённых модулей.';
    $api('sendMessage', ['chat_id' => $chatId, 'text' => $reply, 'parse_mode' => 'HTML']);
    echo json_encode(['ok' => true]);
    exit;
}

$reply = getServerStat($data);
$api('sendMessage', [
    'chat_id'    => $chatId,
    'text'       => $reply,
    'parse_mode' => 'HTML',
]);
echo json_encode(['ok' => true]);
exit;
