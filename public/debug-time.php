<?php
require_once __DIR__ . '/_bootstrap.php';

echo '<pre>';
echo 'PHP default timezone: ' . date_default_timezone_get() . PHP_EOL;
echo 'Current PHP time: ' . date('Y-m-d H:i:s') . PHP_EOL;
echo 'Current UTC time: ' . gmdate('Y-m-d H:i:s') . PHP_EOL;
echo '</pre>';