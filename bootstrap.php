<?php

declare(strict_types=1);

/**
 * Bootstrap básico para el Sistema de Ventas.
 * Incluye configuración, autoload simple y utilidades globales.
 */

if (!defined('BASE_PATH')) {
    define('BASE_PATH', __DIR__);
}

if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) === 0;
    }
}

loadEnv(BASE_PATH . '/.env');

$__config = require BASE_PATH . '/config/config.php';

if (!empty($__config['app']['timezone'])) {
    date_default_timezone_set($__config['app']['timezone']);
}

if (!empty($__config['app']['debug'])) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    ini_set('display_errors', '0');
}

spl_autoload_register(function (string $class): void {
    $prefix = 'SistemaVentas\\';
    $baseDir = BASE_PATH . '/src/';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_readable($file)) {
        require $file;
    }
});

/**
 * Carga variables desde un archivo .env simple con formato KEY=VALUE.
 */
function loadEnv(string $envFile): void
{
    if (!is_readable($envFile)) {
        return;
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $key = trim($key);
        $value = trim($value);

        if ($key === '') {
            continue;
        }

        $value = trim($value, "\"' ");

        putenv("$key=$value");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

/**
 * Obtiene valores de entorno con valor por defecto.
 */
function env(string $key, ?string $default = null): ?string
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    return $value === false ? $default : ($value ?? $default);
}

return $__config;
