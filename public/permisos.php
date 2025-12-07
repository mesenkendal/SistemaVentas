<?php

declare(strict_types=1);

use SistemaVentas\Models\PermissionModel;
use SistemaVentas\Models\RoleModel;
use function htmlspecialchars as e;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$config = require __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/session_guard.php';

$baseUrl = rtrim($config['app']['base_url'] ?? '/SistemaVentas', '/');
$publicBase = rtrim($config['app']['public_url'] ?? $baseUrl . '/public', '/');

if (empty($_SESSION['usuario'])) {
    header('Location: ' . $publicBase . '/login.php');
    exit;
}

enforce_session_timeout($publicBase);
ensure_access('permisos.php', $publicBase);

$user = $_SESSION['usuario'];
$appName = $config['app']['name'] ?? 'Sistema de Ventas';
$asset = static fn(string $path): string => 'assets/' . ltrim($path, '/');
$navItems = filtered_nav_items($publicBase);

$permissionModel = new PermissionModel();
$roleModel = new RoleModel();
$roles = $roleModel->all();
$views = $permissionModel->getAllViews();

$selectedRoleId = filter_input(INPUT_GET, 'rol', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($selectedRoleId === null && !empty($roles)) {
    $selectedRoleId = (int) $roles[0]['IdRol'];
}
if ($selectedRoleId !== null) {
    $roleIds = array_map(static fn(array $rol): int => (int) $rol['IdRol'], $roles);
    if (!in_array($selectedRoleId, $roleIds, true)) {
        $selectedRoleId = !empty($roles) ? (int) $roles[0]['IdRol'] : null;
    }
}

$flashError = $_SESSION['flash_error'] ?? null;
$flashSuccess = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_error'], $_SESSION['flash_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $roleId = filter_input(INPUT_POST, 'rol', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $selectedRoleId = $roleId ?: $selectedRoleId;
    $vistasSeleccionadas = isset($_POST['vistas']) && is_array($_POST['vistas']) ? $_POST['vistas'] : [];

    if ($roleId === null) {
        $_SESSION['flash_error'] = 'Selecciona un rol válido.';
    } elseif (empty($views)) {
        $_SESSION['flash_error'] = 'No existen vistas configuradas.';
    } else {
        try {
            $permissionModel->syncRoleViews($roleId, $vistasSeleccionadas, $user['id'] ?? null);
            if (($user['rol'] ?? null) === $roleId) {
                $_SESSION['permissions']['routes'] = $permissionModel->getAllowedRoutesForRole($roleId);
            }
            $_SESSION['flash_success'] = 'Permisos actualizados exitosamente.';
        } catch (\Throwable $exception) {
            $_SESSION['flash_error'] = 'No fue posible actualizar los permisos. Intenta nuevamente.';
        }
    }

    $redirectUrl = $publicBase . '/permisos.php' . ($roleId ? '?rol=' . $roleId : '');
    header('Location: ' . $redirectUrl);
    exit;
}

$assignedViewIds = $selectedRoleId ? $permissionModel->getViewIdsByRole($selectedRoleId) : [];
$assignedViewIds = array_map('intval', $assignedViewIds);
$viewCount = count($views);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($appName); ?> | Permisos por rol</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e($asset('css/dashboard.css')); ?>?v=<?= urlencode((string) time()); ?>">
    <link rel="stylesheet" href="<?= e($asset('css/permissions.css')); ?>?v=<?= urlencode((string) time()); ?>">
</head>
<body>
    <nav class="navbar">
        <a class="navbar-brand" href="<?= e($publicBase); ?>/index.php">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M3 12 2 7l10-5 10 5-1 5-9 10z" />
            </svg>
            <?= e($appName); ?>
        </a>
        <div class="nav-links">
            <?php foreach ($navItems as $item): ?>
                <a<?= $item['active'] ? ' class="active"' : ''; ?> href="<?= e($item['url']); ?>">
                    <?= e($item['label']); ?>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="user-controls">
            <div class="badge badge-warning">
                <?= e($user['username']); ?>
            </div>
            <a class="logout-btn" href="<?= e($publicBase); ?>/auth/logout.php">Cerrar sesi&oacute;n</a>
        </div>
    </nav>

    <main class="permissions-layout">
        <section class="permissions-hero">
            <div>
                <p class="eyebrow">Control de acceso</p>
                <h1>Permisos por rol</h1>
                <p>Define qué secciones del sistema estarán disponibles para cada perfil. Los cambios son inmediatos y se registran en la bitácora.</p>
            </div>
            <div class="highlight-card">
                <span>Vistas configuradas</span>
                <strong><?= number_format($viewCount); ?></strong>
            </div>
        </section>

        <?php if ($flashError): ?>
            <div class="alert alert-error"><?= e($flashError); ?></div>
        <?php endif; ?>
        <?php if ($flashSuccess): ?>
            <div class="alert alert-success"><?= e($flashSuccess); ?></div>
        <?php endif; ?>

        <section class="permissions-card">
            <header>
                <div>
                    <h2>Asignación de vistas</h2>
                    <p>Selecciona un rol y marca las vistas disponibles.</p>
                </div>
            </header>
            <?php if (empty($roles)): ?>
                <p class="empty-state">Aún no hay roles activos para configurar.</p>
            <?php elseif (empty($views)): ?>
                <p class="empty-state">No existen vistas registradas en el catálogo.</p>
            <?php else: ?>
                <form method="post" class="permission-form">
                    <div class="form-group">
                        <label for="rol">Rol</label>
                        <select id="rol" name="rol" required>
                            <?php foreach ($roles as $rol): ?>
                                <?php $idRol = (int) $rol['IdRol']; ?>
                                <option value="<?= $idRol; ?>" <?= $idRol === $selectedRoleId ? 'selected' : ''; ?>>
                                    <?= e($rol['NombreRol']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="views-grid">
                        <?php foreach ($views as $vista): ?>
                            <?php $idVista = (int) $vista['IdVista']; ?>
                            <label class="view-card">
                                <input type="checkbox" name="vistas[]" value="<?= $idVista; ?>" <?= in_array($idVista, $assignedViewIds, true) ? 'checked' : ''; ?>>
                                <div>
                                    <strong><?= e($vista['NombreVista']); ?></strong>
                                    <span><?= e($vista['Ruta']); ?></span>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="primary-btn">Guardar permisos</button>
                    </div>
                </form>
            <?php endif; ?>
        </section>
    </main>
    <script>
        (function () {
            const select = document.getElementById('rol');
            if (!select) {
                return;
            }
            select.addEventListener('change', () => {
                const value = select.value;
                const url = new URL(window.location.href);
                if (value) {
                    url.searchParams.set('rol', value);
                } else {
                    url.searchParams.delete('rol');
                }
                window.location.href = url.toString();
            });
        })();
    </script>
    <?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
