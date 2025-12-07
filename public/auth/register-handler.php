<?php

declare(strict_types=1);

use SistemaVentas\Models\RoleModel;
use SistemaVentas\Models\UserModel;
use Throwable;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$config = require __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../includes/db.php';

$baseUrl = rtrim($config['app']['base_url'] ?? '/', '/');
$publicBase = rtrim($config['app']['public_url'] ?? $baseUrl, '/');
$registerUrl = $publicBase . '/register.php';
$loginUrl = $publicBase . '/login.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $registerUrl);
    exit;
}

$nombre = trim((string) filter_input(INPUT_POST, 'nombre', FILTER_UNSAFE_RAW));
$apellido = trim((string) filter_input(INPUT_POST, 'apellido', FILTER_UNSAFE_RAW));
$rolId = (int) filter_input(INPUT_POST, 'rol', FILTER_VALIDATE_INT);
$clave = (string) filter_input(INPUT_POST, 'clave');
$claveConfirmacion = (string) filter_input(INPUT_POST, 'clave_confirmacion');

$errores = [];
if ($nombre === '') {
    $errores[] = 'El nombre de usuario es obligatorio.';
}
if ($apellido === '') {
    $errores[] = 'El apellido es obligatorio.';
}
if ($rolId <= 0) {
    $errores[] = 'Debes seleccionar un rol válido.';
}
if ($clave !== $claveConfirmacion) {
    $errores[] = 'La confirmación de clave no coincide.';
}

$roleModel = new RoleModel();
$rol = $rolId > 0 ? $roleModel->find($rolId) : null;
if ($rol === null) {
    $errores[] = 'El rol seleccionado no existe o no está activo.';
}

$userModel = new UserModel();
$usuarioExistente = $userModel->findByUsername($nombre);
if ($usuarioExistente !== null) {
    $errores[] = 'El nombre de usuario ya está registrado.';
}

if (!empty($errores)) {
    $_SESSION['flash_error'] = implode(' ', $errores);
    header('Location: ' . $registerUrl);
    exit;
}

try {
    $hash = password_hash($clave, PASSWORD_BCRYPT);
    $userModel->create([
        'IdRol'         => $rolId,
        'NombreUsuario' => $nombre,
        'Apellido'      => $apellido,
        'Clave'         => $hash,
        'Activo'        => 1,
    ]);
    $_SESSION['flash_success'] = 'Cuenta creada correctamente. Puedes iniciar sesión.';
    header('Location: ' . $loginUrl);
    exit;
} catch (Throwable $e) {
    $_SESSION['flash_error'] = 'Ocurrió un error al registrar el usuario.';
    header('Location: ' . $registerUrl);
    exit;
}
