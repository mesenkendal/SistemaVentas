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
ensure_access('acerca.php', $publicBase);

$user = $_SESSION['usuario'];
$appName = $config['app']['name'] ?? 'Sistema de Ventas';
$asset = static fn(string $path): string => 'assets/' . ltrim($path, '/');
$navItems = filtered_nav_items($publicBase);

$flashError = $_SESSION['flash_error'] ?? null;
$flashSuccess = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_error'], $_SESSION['flash_success']);

$quickSteps = [
    'Ingresa con tu usuario y contraseña. Cambia la contraseña en el primer ingreso.',
    'Revisa el Dashboard para conocer el estado del negocio (ventas, inventario, usuarios).',
    'Gestiona Inventario: crea, edita o desactiva productos según disponibilidad.',
    'Registra Ventas: agrega cliente y líneas de productos, verifica totales y confirma.',
    'Administra Usuarios: crea cuentas, asigna roles y controla el estado activo/inactivo.',
    'Configura Permisos: decide qué vistas puede usar cada rol desde la sección Permisos.',
    'Exporta Reportes: descarga información filtrada en Excel para análisis.',
];

$rolesGuides = [
    [
        'title' => 'Administrador',
        'items' => [
            'Acceso completo a todas las vistas y configuraciones.',
            'Define roles y permisos, crea usuarios y gestiona inventario y ventas.',
            'Monitorea bitácora y reportes para auditoría.',
        ],
    ],
    [
        'title' => 'Supervisor',
        'items' => [
            'Acceso a Dashboard, Inventario, Ventas, Reportes.',
            'Puede editar inventario y revisar ventas.',
            'No gestiona permisos ni usuarios (opcional según configuración).',
        ],
    ],
    [
        'title' => 'Vendedor',
        'items' => [
            'Acceso a Ventas e Inventario en modo lectura (según permisos).',
            'Registra ventas y consulta stock disponible.',
            'No administra usuarios ni configuración.',
        ],
    ],
];

$operacion = [
    [
        'title' => 'Inventario',
        'steps' => [
            'Crear producto: llena nombre, tipo de venta (Kilo/Unidad), precio y stock.',
            'Editar producto: selecciona "Editar" en la fila, ajusta valores y guarda.',
            'Eliminar (soft delete): usa "Eliminar" para ocultar sin borrar histórico.',
            'Alertas: los productos con bajo stock se muestran en el Dashboard.',
        ],
    ],
    [
        'title' => 'Ventas',
        'steps' => [
            'Nueva venta: agrega cliente (opcional) y selecciona productos con cantidad.',
            'Totales: se calculan automáticamente por línea y venta.',
            'Historial: revisa ventas recientes y sus detalles.',
            'Cancelación: usa la opción correspondiente (según permisos).',
        ],
    ],
    [
        'title' => 'Usuarios y permisos',
        'steps' => [
            'Crear usuario: define nombre, email y rol asignado.',
            'Roles: personaliza vistas permitidas en Permisos → marca/desmarca vistas.',
            'Seguridad: sesiones expiran tras inactividad; usa contraseñas fuertes.',
        ],
    ],
];

$reportes = [
    'Filtra por fecha, usuario o producto para acotar la información.',
    'Descarga en Excel (TSV) con los mismos filtros aplicados.',
    'Los datos incluyen ventas, inventario y bitácora según la vista usada.',
];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($appName); ?> | Acerca de & Manual</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e($asset('css/dashboard.css')); ?>?v=<?= urlencode((string) time()); ?>">
    <link rel="stylesheet" href="<?= e($asset('css/about.css')); ?>?v=<?= urlencode((string) time()); ?>">
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

    <main class="about-layout">
        <section class="hero about-hero">
            <div>
                <p class="eyebrow">Guía rápida</p>
                <h1>Acerca de & Manual de uso</h1>
                <p>Conoce el flujo completo para operar el <?= e($appName); ?>: desde iniciar sesión hasta exportar reportes y administrar permisos.</p>
                <div class="hero-tags">
                    <span class="badge badge-success">Operación diaria</span>
                    <span class="badge badge-warning">Permisos</span>
                    <span class="badge badge-accent">Reportes</span>
                </div>
            </div>
            <div class="callout">
                <h3>Checklist rápido</h3>
                <ul>
                    <?php foreach ($quickSteps as $step): ?>
                        <li><?= e($step); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>

        <section class="grid two-cols">
            <div class="panel">
                <div class="panel-head">
                    <h4>Roles y alcances</h4>
                    <span>Configúralos en Permisos</span>
                </div>
                <div class="roles-grid">
                    <?php foreach ($rolesGuides as $rol): ?>
                        <article class="role-card">
                            <header>
                                <h5><?= e($rol['title']); ?></h5>
                            </header>
                            <ul>
                                <?php foreach ($rol['items'] as $item): ?>
                                    <li><?= e($item); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head">
                    <h4>Reportes y exportación</h4>
                    <span>Excel con filtros activos</span>
                </div>
                <div class="list compact">
                    <?php foreach ($reportes as $tip): ?>
                        <div class="list-item">
                            <span><?= e($tip); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="info-box">
                    <strong>Buenas prácticas</strong>
                    <ul>
                        <li>Aplica filtros antes de exportar para obtener datos precisos.</li>
                        <li>Protege el archivo descargado: contiene información sensible.</li>
                        <li>Regenera reportes tras cambios en permisos o inventario.</li>
                    </ul>
                </div>
            </div>
        </section>

        <section class="panel">
            <div class="panel-head">
                <h4>Operación diaria por módulo</h4>
                <span>Pasos esenciales</span>
            </div>
            <div class="modules-grid">
                <?php foreach ($operacion as $mod): ?>
                    <article class="module-card">
                        <header>
                            <h5><?= e($mod['title']); ?></h5>
                        </header>
                        <ol>
                            <?php foreach ($mod['steps'] as $step): ?>
                                <li><?= e($step); ?></li>
                            <?php endforeach; ?>
                        </ol>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="panel highlight">
            <div class="panel-head">
                <h4>Seguridad y mejores prácticas</h4>
                <span>Cuida accesos y datos</span>
            </div>
            <div class="two-cols">
                <ul class="checklist">
                    <li>Usa contraseñas fuertes y únicas.</li>
                    <li>Cierra sesión al terminar; el sistema expira por inactividad.</li>
                    <li>Asigna el rol mínimo necesario para cada usuario.</li>
                    <li>Revisa la bitácora ante cambios críticos.</li>
                    <li>Exporta solo lo necesario y elimina archivos locales cuando no se usen.</li>
                </ul>
                <div class="info-box">
                    <strong>Soporte</strong>
                    <p>Si detectas comportamientos inusuales o errores, reporta al administrador. Consulta los logs y la bitácora para auditoría.</p>
                </div>
            </div>
        </section>
    </main>
    <?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
