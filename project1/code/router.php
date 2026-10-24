<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/test_client.html') return false;
require __DIR__ . '/index.php';
