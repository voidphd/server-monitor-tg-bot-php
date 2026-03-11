<?php return array (
  'bot_token' => 'test',
  'webhook_secret' => 'test',
  'allowed_username' => 'your_telegram_username',
  'sites_domains' => 
  array (
    0 => 'example.com',
  ),
  'modules' => 
  array (
    0 => 'ram',
    1 => 'cpu',
    2 => 'disk',
    3 => 'nginx',
    4 => 'pm2',
    5 => 'sites',
    6 => 'reboot',
    7 => 'banned_ips',
  ),
  'process_list_limit' => 10,
  'allow_process_stop' => false,
  'allow_process_restart' => false,
);