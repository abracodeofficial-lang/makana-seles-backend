<?php
$log = __DIR__ . '/../storage/logs/laravel.log';
if (!file_exists($log)) { echo 'No log file'; exit; }
$lines = file($log);
$last = array_slice($lines, -50);
echo '<pre style="direction:ltr;font-size:12px">' . htmlspecialchars(implode('', $last)) . '</pre>';
