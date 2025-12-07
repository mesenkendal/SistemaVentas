<?php

declare(strict_types=1);

use SistemaVentas\Models\PermissionModel;
use SistemaVentas\Models\UserModel;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$config = require __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../includes/db.php';

$baseUrl = rtrim($config['app']['base_url'] ?? '/SistemaVentas', '/');
$publicBase = rtrim($config['app']['public_url'] ?? $baseUrl . '/public', '/');
$loginUrl = $publicBase . '/login.php';
$homeUrl = $publicBase . '/index.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $loginUrl);
    exit;
}

$usuario = trim((string) filter_input(INPUT_POST, 'usuario', FILTER_SANITIZE_STRING));
$clave = (string) filter_input(INPUT_POST, 'clave');

if ($usuario === '' || $clave === '') {
    $_SESSION['flash_error'] = 'Debes ingresar usuario y clave.';
    header('Location: ' . $loginUrl);
    exit;
}

$userModel = new UserModel();
$usuarioBD = $userModel->findByUsername($usuario);

if ($usuarioBD === null || (int) $usuarioBD['Activo'] !== 1) {
    $_SESSION['flash_error'] = 'Credenciales inválidas.';
    header('Location: ' . $loginUrl);
    exit;
}

if (!password_verify($clave, $usuarioBD['Clave'])) {
    $_SESSION['flash_error'] = 'Credenciales inválidas.';
    header('Location: ' . $loginUrl);
    exit;
}

$_SESSION['usuario'] = [
    'id'        => (int) $usuarioBD['IdUsuario'],
    'username'  => $usuarioBD['NombreUsuario'],
    'apellido'  => $usuarioBD['Apellido'],
    'rol'       => $usuarioBD['IdRol'],
    'nombreRol' => $usuarioBD['NombreRol'] ?? null,
];

$permissionModel = new PermissionModel();
$routes = $permissionModel->getAllowedRoutesForRole((int) $usuarioBD['IdRol']);
$_SESSION['permissions'] = ['routes' => $routes];
$_SESSION['last_activity'] = time();

$_SESSION['flash_success'] = 'Bienvenido, ' . $usuarioBD['NombreUsuario'] . '.';

header('Location: ' . $homeUrl);
exit;
