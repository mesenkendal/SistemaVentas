<?php

declare(strict_types=1);

use function htmlspecialchars as e;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$config = require __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/session_guard.php';

$baseUrl = rtrim($config['app']['base_url'] ?? '/', '/');
$publicBase = rtrim($config['app']['public_url'] ?? $baseUrl, '/');

if (empty($_SESSION['usuario'])) {
    header('Location: ' . $publicBase . '/login.php');
    exit;
}

enforce_session_timeout($publicBase);

ensure_access('index.php', $publicBase);

$appName = $config['app']['name'] ?? 'SIFVER';
$asset = static fn(string $path): string => 'assets/' . ltrim($path, '/');

function metric(string $sql): float
{
    $result = run_query($sql);
    return isset($result[0]['total']) ? (float) $result[0]['total'] : 0.0;
}

$stats = [
    'ventas'      => (int) metric('SELECT COUNT(*) AS total FROM Ventas WHERE Activo = 1'),
    'ventasMonto' => metric('SELECT COALESCE(SUM(Total), 0) AS total FROM Ventas WHERE Activo = 1'),
    'inventario'  => (int) metric('SELECT COUNT(*) AS total FROM Inventario WHERE Activo = 1'),
    'stockValor'  => metric('SELECT COALESCE(SUM(Precio * Stock), 0) AS total FROM Inventario WHERE Activo = 1'),
    'usuarios'    => (int) metric('SELECT COUNT(*) AS total FROM Usuarios WHERE Activo = 1'),
    'roles'       => (int) metric('SELECT COUNT(*) AS total FROM Roles WHERE Activo = 1'),
];

$recentSales = run_query(
    'SELECT v.IdVenta, v.Cliente, v.Total, v.Fecha, u.NombreUsuario
     FROM Ventas v
     JOIN Usuarios u ON u.IdUsuario = v.IdUsuario
     WHERE v.Activo = 1
     ORDER BY v.Fecha DESC, v.IdVenta DESC
     LIMIT 4'
);

$inventoryAlerts = run_query(
    'SELECT Nombre, Stock, Precio
     FROM Inventario
     WHERE Activo = 1
     ORDER BY Stock ASC
     LIMIT 4'
);

$team = run_query(
    'SELECT u.NombreUsuario, u.Apellido, r.NombreRol
     FROM Usuarios u
     JOIN Roles r ON r.IdRol = u.IdRol
     WHERE u.Activo = 1
     ORDER BY u.FechaCreacion DESC
     LIMIT 4'
);

$user = $_SESSION['usuario'];
$flashError = $_SESSION['flash_error'] ?? null;
$flashSuccess = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_error'], $_SESSION['flash_success']);
$navItems = filtered_nav_items($publicBase);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($appName); ?> | Panel Principal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e($asset('css/dashboard.css')); ?>?v=<?= urlencode((string) time()); ?>">
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

    <?php if ($flashError): ?>
        <div class="alert alert-error"><?= e($flashError); ?></div>
    <?php elseif ($flashSuccess): ?>
        <div class="alert alert-success"><?= e($flashSuccess); ?></div>
    <?php endif; ?>

    <main>
    
        <section class="hero">
            <h1>Bienvenido de nuevo <?= e($user['username']); ?>, hoy es <?= ucfirst(IntlDateFormatter::formatObject(new DateTime(), "EEEE d 'de' MMMM 'de' y", 'es_ES')); ?>.</h1>
            <p>Síntesis operativa de inventario, ventas y colaboradores activos.</p>
            <div class="stats-grid">
                <div class="card">
                    <h3>Ventas activas</h3>
                    <strong><?= number_format($stats['ventas']); ?></strong>
                    <span>Histórico general</span>
                </div>
                <div class="card">
                    <h3>Facturación</h3>
                    <strong>₡<?= number_format($stats['ventasMonto'], 2); ?></strong>
                    <span>Total acumulado</span>
                </div>
                <div class="card">
                    <h3>SKU operativos</h3>
                    <strong><?= number_format($stats['inventario']); ?></strong>
                    <span>Productos disponibles</span>
                </div>
                <div class="card">
                    <h3>Valor inventario</h3>
                    <strong>₡<?= number_format($stats['stockValor'], 2); ?></strong>
                    <span>Precio x stock</span>
                </div>
            </div>
        </section>

        <h2 class="section-title">Resumen operativo</h2>
        <div class="data-panels">
            <div class="panel">
                <div class="panel-head">
                    <h4>Últimas ventas</h4>
                    <span>Actualización reciente</span>
                </div>
                <div class="list">
                    <?php foreach ($recentSales as $venta): ?>
                        <div class="list-item">
                            <div>
                                <strong>#<?= (int) $venta['IdVenta']; ?></strong>
                                <div><?= e($venta['Cliente'] ?: 'Sin cliente'); ?></div>
                                <small><?= e($venta['Fecha']); ?> · <?= e($venta['NombreUsuario']); ?></small>
                            </div>
                            <span class="badge badge-success">₡<?= number_format((float) $venta['Total'], 2); ?></span>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($recentSales)): ?>
                        <p>No hay ventas registradas.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head">
                    <h4>Alertas de inventario</h4>
                    <span>Stock en vigilancia</span>
                </div>
                <div class="list">
                    <?php foreach ($inventoryAlerts as $item): ?>
                        <div class="list-item">
                            <div>
                                <strong><?= e($item['Nombre']); ?></strong>
                                <div>Stock: <?= number_format((float) $item['Stock'], 2); ?></div>
                            </div>
                            <span class="badge badge-warning">₡<?= number_format((float) $item['Precio'], 2); ?></span>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($inventoryAlerts)): ?>
                        <p>Inventario completo sin alertas.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head">
                    <h4>Equipo activo</h4>
                    <span><?= number_format($stats['usuarios']); ?> usuarios</span>
                </div>
                <div class="list">
                    <?php foreach ($team as $member): ?>
                        <div class="list-item">
                            <div>
                                <strong><?= e($member['NombreUsuario']); ?></strong>
                                <div><?= e($member['Apellido']); ?></div>
                            </div>
                            <span class="badge badge-info"><?= e($member['NombreRol']); ?></span>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($team)): ?>
                        <p>No hay usuarios activos registrados.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </main>

    
</body>
</html>
