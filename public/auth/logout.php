<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

session_destroy();

$config = require __DIR__ . '/../../bootstrap.php';
$baseUrl = rtrim($config['app']['base_url'] ?? '/', '/');
$publicBase = rtrim($config['app']['public_url'] ?? $baseUrl, '/');

header('Location: ' . $publicBase . '/login.php');
exit;
