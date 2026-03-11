<?php
/**
 * Определение технологий на сервере для предложения модулей мониторинга.
 * Запуск: php detect-technologies.php (из корня проекта или scripts/)
 * Возвращает JSON: { "suggested": ["ram", "cpu", ...], "found": { "nginx": true, "pm2": true, ... } }
 */
declare(strict_types=1);

$found = [
    'nginx'    => false,
    'pm2'      => false,
    'php_fpm'  => false,
    'mysql'    => false,
    'docker'   => false,
    'fail2ban' => false,
];

// Nginx: процесс или бинарник
$out = @shell_exec('pgrep -x nginx >/dev/null 2>&1 && echo 1 || which nginx 2>/dev/null');
$found['nginx'] = trim((string) $out) !== '';

// PM2: через nvm в shell или через хелпер бота (sudo bot-pm2-list.sh)
$out = @shell_exec('export NVM_DIR="${NVM_DIR:-/root/.nvm}"; [ -s "$NVM_DIR/nvm.sh" ] && . "$NVM_DIR/nvm.sh" 2>/dev/null; which pm2 2>/dev/null');
if ($out !== null && trim($out) !== '') {
    $found['pm2'] = true;
} else {
    $out = @shell_exec('timeout 2 sudo -n /usr/local/bin/bot-pm2-list.sh 2>/dev/null');
    $found['pm2'] = $out !== null && trim($out) !== '' && trim($out) !== '[]' && strpos($out, '[') === 0;
}

// PHP-FPM: процесс
$out = @shell_exec('pgrep -x php-fpm >/dev/null 2>&1 || pgrep php-fpm >/dev/null 2>&1 && echo 1');
$found['php_fpm'] = trim((string) $out) !== '';

// MySQL / MariaDB: процесс или сокет
$out = @shell_exec('pgrep -x mysqld >/dev/null 2>&1 || pgrep -x mariadbd >/dev/null 2>&1 || [ -S /var/lib/mysql/mysql.sock ] 2>/dev/null && echo 1');
$found['mysql'] = trim((string) $out) !== '';

// Docker: демон доступен
$out = @shell_exec('docker info >/dev/null 2>&1 && echo 1');
$found['docker'] = trim((string) $out) !== '';

// Fail2ban: доступна команда (для модуля «Забаненные IP»)
$out = @shell_exec('which fail2ban-client 2>/dev/null || command -v fail2ban-client 2>/dev/null');
$found['fail2ban'] = trim((string) $out) !== '';

// Рекомендуемые модули: базовые всегда, остальные по обнаружению
$suggested = ['ram', 'cpu', 'disk'];
if ($found['nginx']) $suggested[] = 'nginx';
if ($found['pm2']) $suggested[] = 'pm2';
$suggested[] = 'sites';
$suggested[] = 'reboot';
if ($found['fail2ban']) $suggested[] = 'banned_ips';

$result = [
    'suggested' => array_values(array_unique($suggested)),
    'found'     => $found,
];

if (php_sapi_name() === 'cli') {
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} else {
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
}
