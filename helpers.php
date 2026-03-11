<?php
/**
 * Вспомогательные функции для webhook.php: сбор метрик и данных (RAM, CPU, диск, Nginx, PM2, fail2ban, сайты).
 * Подключается из webhook.php через require.
 */

declare(strict_types=1);

function getServerStat(string $key): string {
    $host = gethostname() ?: 'server';
    switch ($key) {
        case 'ram':
            $out = @shell_exec('free -m 2>/dev/null');
            if ($out === null) return 'Не удалось получить RAM.';
            $lines = array_slice(explode("\n", $out), 0, 2);
            $mem = preg_match('/Mem:\s+(\d+)\s+(\d+)\s+(\d+)/', $lines[1] ?? '', $m) ? $m : null;
            if ($mem) {
                $total = (int) $m[1];
                $used = (int) $m[2];
                $pct = $total > 0 ? round($used / $total * 100, 1) : 0;
                return "<b>{$host}</b>\n🖥 RAM: {$used} / {$total} MiB ({$pct}%)";
            }
            return "<b>{$host}</b>\n" . trim($out);
        case 'cpu':
            $load = @file_get_contents('/proc/loadavg');
            if ($load === false) return 'Не удалось получить CPU.';
            $a = array_map('trim', explode(' ', trim($load), 5));
            $load1 = isset($a[0]) ? (float) $a[0] : 0.0;
            $load5 = isset($a[1]) ? (float) $a[1] : 0.0;
            $load15 = isset($a[2]) ? (float) $a[2] : 0.0;
            $info = @shell_exec('nproc 2>/dev/null');
            $cores = $info !== null ? max(1, (int) trim($info)) : 1;
            $pct = $cores > 0 ? min(100, (int) round($load1 / $cores * 100)) : 0;
            if ($load1 / $cores >= 1.5) {
                $status = '🔴 высокая';
            } elseif ($load1 / $cores >= 0.7) {
                $status = '🟡 повышенная';
            } else {
                $status = '🟢 норма';
            }
            $uptime = @shell_exec('uptime -p 2>/dev/null');
            $uptimeStr = $uptime ? trim($uptime) : '';
            $lines = [
                "<b>{$host}</b>",
                "📊 CPU:",
                "  Load: 1m {$load1}, 5m {$load5}, 15m {$load15}",
                "  Ядер: {$cores}",
                "  Загрузка: ~{$pct}% — {$status}",
            ];
            if ($uptimeStr !== '') {
                $lines[] = "  Uptime: {$uptimeStr}";
            }
            return implode("\n", $lines);
        case 'disk':
            $out = @shell_exec("df -h / 2>/dev/null | awk 'NR==2 {print \$3\" \"\$4\" \"\$5}'");
            if ($out === null) return 'Не удалось получить диск.';
            $p = preg_split('/\s+/', trim($out), 3);
            $used = $p[0] ?? '?';
            $avail = $p[1] ?? '?';
            $pct = $p[2] ?? '?';
            return "<b>{$host}</b>\n💾 Диск /: занято {$used}, свободно {$avail} ({$pct})";
        case 'nginx':
            $out = @shell_exec('pgrep -x nginx >/dev/null 2>&1 && echo active || echo inactive');
            $active = $out ? trim($out) : 'unknown';
            $status = $active === 'active' ? '✅' : '❌';
            return "<b>{$host}</b>\n🌐 Nginx: {$status} {$active}";
        case 'all':
            $ram = getServerStat('ram');
            $cpu = getServerStat('cpu');
            $disk = getServerStat('disk');
            $nginx = getServerStat('nginx');
            return $ram . "\n\n" . $cpu . "\n\n" . $disk . "\n\n" . $nginx;
        default:
            return 'Неизвестный параметр.';
    }
}

function getRamProcessesList(int $limit): array {
    $host = gethostname() ?: 'server';
    $hostSafe = htmlspecialchars($host, ENT_QUOTES, 'UTF-8');
    $limit = max(1, min(25, $limit));
    $psOut = @shell_exec('timeout 4 ps -eo pid,user,%mem,rss,comm --sort=-%mem --no-headers 2>/dev/null | head -' . $limit);
    if ($psOut === null || trim($psOut) === '') {
        return ['text' => "<b>{$hostSafe}</b>\n🖥 Процессы: не удалось выполнить ps."];
    }
    $lines = array_values(array_filter(array_map('trim', explode("\n", trim($psOut)))));
    $formatted = [];
    foreach ($lines as $line) {
        if (preg_match('/^\s*(\d+)\s+(\S+)\s+([\d.]+)\s+(\d+)\s+(.*)$/s', $line, $m)) {
            $rssMb = round((int) $m[4] / 1024, 1);
            $formatted[] = sprintf('%s %s%% %s MiB %s', $m[1], $m[3], $rssMb, trim($m[5]));
        }
    }
    $preContent = $formatted ? implode("\n", array_map(function ($l) { return htmlspecialchars($l, ENT_QUOTES, 'UTF-8'); }, $formatted)) : 'нет данных';
    $text = "<b>{$hostSafe}</b>\n🖥 Топ процессов по RAM:\n<pre>" . $preContent . '</pre>';
    if (strlen($text) > 4000) $text = substr($text, 0, 3997) . '</pre>';
    return ['text' => $text];
}

function getCpuProcessesList(int $limit): array {
    $host = gethostname() ?: 'server';
    $hostSafe = htmlspecialchars($host, ENT_QUOTES, 'UTF-8');
    $limit = max(1, min(25, $limit));
    $psOut = @shell_exec('timeout 4 ps -eo pid,user,%cpu,comm --sort=-%cpu --no-headers 2>/dev/null | head -' . $limit);
    if ($psOut === null || trim($psOut) === '') {
        return ['text' => "<b>{$hostSafe}</b>\n📊 Процессы: не удалось выполнить ps."];
    }
    $lines = array_values(array_filter(array_map('trim', explode("\n", trim($psOut)))));
    $formatted = [];
    foreach ($lines as $line) {
        if (preg_match('/^\s*(\d+)\s+(\S+)\s+([\d.]+)\s+(.*)$/s', $line, $m)) {
            $formatted[] = sprintf('%s %s%% %s', $m[1], $m[3], trim($m[4]));
        }
    }
    $preContent = $formatted ? implode("\n", array_map(function ($l) { return htmlspecialchars($l, ENT_QUOTES, 'UTF-8'); }, $formatted)) : 'нет данных';
    $text = "<b>{$hostSafe}</b>\n📊 Топ процессов по CPU:\n<pre>" . $preContent . '</pre>';
    if (strlen($text) > 4000) $text = substr($text, 0, 3997) . '</pre>';
    return ['text' => $text];
}

function getPm2ListRaw(): array {
    $out = @shell_exec('timeout 6 sudo -n /usr/local/bin/bot-pm2-list.sh 2>/dev/null');
    if ($out === null) return [];
    $list = json_decode($out, true);
    return is_array($list) ? $list : [];
}

function getPm2ListSimple(): array {
    $host = gethostname() ?: 'server';
    $list = getPm2ListRaw();
    if (empty($list)) {
        return ['text' => "<b>{$host}</b>\n📦 PM2: недоступен или нет процессов."];
    }
    $lines = [];
    foreach ($list as $i => $proc) {
        $name = $proc['name'] ?? ('app_' . $i);
        $status = $proc['pm2_env']['status'] ?? $proc['status'] ?? '?';
        $pid = $proc['pid'] ?? '-';
        $icon = ($status === 'online' || $status === 'launching') ? '✅' : '⏹';
        $lines[] = "{$icon} [{$i}] {$name} (pid {$pid})";
    }
    $text = "<b>{$host}</b>\n📦 PM2:\n<pre>" . implode("\n", $lines) . '</pre>';
    if (strlen($text) > 4000) $text = substr($text, 0, 3997) . '</pre>';
    return ['text' => $text];
}

function getBannedIpsList(string $jail = 'nginx-limit-req'): array {
    $out = @shell_exec('timeout 5 sudo -n /usr/local/bin/bot-fail2ban.sh list ' . escapeshellarg($jail) . ' 2>/dev/null');
    if ($out === null || $out === '') {
        return [];
    }
    $lines = explode("\n", trim($out));
    $ips = [];
    foreach ($lines as $line) {
        $ip = trim($line);
        if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
            $ips[] = $ip;
        }
    }
    return $ips;
}

function getSitesCheckResult(array $sitesDomains): string {
    $host = htmlspecialchars((string) (gethostname() ?: 'server'), ENT_QUOTES, 'UTF-8');
    $lines = ["<b>{$host}</b>\n🌍 Проверка сайтов (код ответа):"];
    foreach ($sitesDomains as $domain) {
        $ok = false;
        $displayCode = 0;
        foreach (['https', 'http'] as $scheme) {
            $url = $scheme . '://' . $domain . '/';
            $out = @shell_exec(
                'curl -s -o /dev/null -w "%{http_code}" -L --connect-timeout 3 --max-time 8 -k -A "ServerMonitor/1.0" ' . escapeshellarg($url) . ' 2>/dev/null'
            );
            $code = $out !== null ? (int) trim($out) : 0;
            $displayCode = $code;
            if ($code >= 200 && $code < 400) {
                $ok = true;
                break;
            }
        }
        $lines[] = ($ok ? '✅' : '❌') . ' ' . $domain . ' (' . $displayCode . ')';
    }
    return implode("\n", $lines);
}
