<?php

return [
    'app' => [
        'name'      => env('APP_NAME', 'Sistema de Ventas'),
        // URL base sin sufijo /public para que las redirecciones funcionen en Render
        'base_url'  => env('APP_URL', '/'),
        // Permite sobreescribir la URL pública; por defecto igual a base_url
        'public_url'=> env('PUBLIC_URL', env('APP_URL', '/')),
        'timezone'  => env('APP_TIMEZONE', 'UTC'),
        'debug'     => filter_var(env('APP_DEBUG', 'true'), FILTER_VALIDATE_BOOL),
    ],
    'db' => [
        'host'      => env('DB_HOST', '127.0.0.1'),
        'port'      => (int) env('DB_PORT', '3306'),
        'database'  => env('DB_DATABASE', 'SistemaVentas'),
        'username'  => env('DB_USERNAME', 'root'),
        'password'  => env('DB_PASSWORD', ''),
        'charset'   => env('DB_CHARSET', 'utf8mb4'),
        'options'   => [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ],
    ],
];
