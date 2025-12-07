<?php

declare(strict_types=1);

if (!function_exists('user_allowed_routes')) {
    function user_allowed_routes(): array
    {
        return $_SESSION['permissions']['routes'] ?? [];
    }
}

if (!function_exists('user_can_access')) {
    function user_can_access(string $route): bool
    {
        $route = strtolower(ltrim($route, '/'));
        $permissions = user_allowed_routes();
        if (empty($permissions)) {
            return true;
        }
        return in_array($route, $permissions, true);
    }
}

if (!function_exists('ensure_access')) {
    function ensure_access(string $route, string $publicBase): void
    {
        if (!user_can_access($route)) {
            $_SESSION['flash_error'] = 'No tienes permisos para acceder a esta sección.';
            header('Location: ' . $publicBase . '/index.php');
            exit;
        }
    }
}

if (!function_exists('app_nav_items')) {
    function app_nav_items(string $publicBase): array
    {
        return [
            ['route' => 'index.php', 'label' => 'Dashboard', 'url' => $publicBase . '/index.php'],
            ['route' => 'inventario.php', 'label' => 'Inventario', 'url' => $publicBase . '/inventario.php'],
            ['route' => 'ventas.php', 'label' => 'Ventas', 'url' => $publicBase . '/ventas.php'],
            ['route' => 'usuarios.php', 'label' => 'Usuarios', 'url' => $publicBase . '/usuarios.php'],
            ['route' => 'reportes.php', 'label' => 'Reportes', 'url' => $publicBase . '/reportes.php'],
            ['route' => 'permisos.php', 'label' => 'Permisos', 'url' => $publicBase . '/permisos.php'],
            ['route' => 'acerca.php', 'label' => 'Acerca de', 'url' => $publicBase . '/acerca.php'],
        ];
    }
}

if (!function_exists('filtered_nav_items')) {
    function filtered_nav_items(string $publicBase): array
    {
        $items = app_nav_items($publicBase);
        $current = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '') ?: basename($_SERVER['SCRIPT_NAME'] ?? '');
        foreach ($items as &$item) {
            $item['active'] = ($current === $item['route']);
        }
        unset($item);
        return array_values(array_filter($items, static fn(array $item): bool => user_can_access($item['route'])));
    }
}
