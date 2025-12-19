<?php

declare(strict_types=1);

date_default_timezone_set('America/Costa_Rica');
use SistemaVentas\Models\BitacoraModel;
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
$appName = $config['app']['name'] ?? 'Sistema de Ventas';

if (empty($_SESSION['usuario'])) {
    header('Location: ' . $publicBase . '/login.php');
    exit;
}

enforce_session_timeout($publicBase);
ensure_access('reportes.php', $publicBase);

$user = $_SESSION['usuario'];
$asset = static fn(string $path): string => 'assets/' . ltrim($path, '/');
$navItems = filtered_nav_items($publicBase);

$bitacoraModel = new BitacoraModel();

$tablaParam = trim((string) filter_input(INPUT_GET, 'tabla', FILTER_UNSAFE_RAW));
$tablaParam = $tablaParam !== '' ? substr($tablaParam, 0, 50) : null;
$accionParam = trim((string) filter_input(INPUT_GET, 'accion', FILTER_UNSAFE_RAW));
$accionParam = $accionParam !== '' ? strtoupper(substr($accionParam, 0, 20)) : null;
$usuarioParam = filter_input(INPUT_GET, 'usuario', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
$desdeParam = trim((string) filter_input(INPUT_GET, 'desde', FILTER_UNSAFE_RAW));
$hastaParam = trim((string) filter_input(INPUT_GET, 'hasta', FILTER_UNSAFE_RAW));

$datePattern = '/^\d{4}-\d{2}-\d{2}$/';
if ($desdeParam === '' || ($desdeParam !== '' && !preg_match($datePattern, $desdeParam))) {
    $desdeParam = null;
}
if ($hastaParam === '' || ($hastaParam !== '' && !preg_match($datePattern, $hastaParam))) {
    $hastaParam = null;
}

$filters = [
    'table'     => $tablaParam,
    'action'    => $accionParam,
    'user'      => $usuarioParam,
    'date_from' => $desdeParam,
    'date_to'   => $hastaParam,
];

$perPage = 20;
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
$offset = ($page - 1) * $perPage;

$totalRecords = $bitacoraModel->countRecords($filters);
$totalPages = max(1, (int) ceil($totalRecords / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$entries = $totalRecords > 0 ? $bitacoraModel->search($filters, $perPage, $offset) : [];
$latestBatch = $totalRecords > 0 ? $bitacoraModel->search($filters, 1, 0) : [];
$latestEntry = $latestBatch[0] ?? null;
$tables = $bitacoraModel->distinctTables();
$actions = $bitacoraModel->distinctActions();
$filtersApplied = !empty(array_filter($filters, static fn($value) => $value !== null && $value !== ''));

$queryParams = array_filter([
    'tabla'   => $tablaParam,
    'accion'  => $accionParam,
    'usuario' => $usuarioParam,
    'desde'   => $desdeParam,
    'hasta'   => $hastaParam,
], static fn($value) => $value !== null && $value !== '');

$buildPageUrl = static function (int $targetPage) use ($publicBase, $queryParams): string {
    $params = $queryParams;
    if ($targetPage <= 1) {
        unset($params['page']);
    } else {
        $params['page'] = $targetPage;
    }
    $queryString = $params ? '?' . http_build_query($params) : '';
    return $publicBase . '/reportes.php' . $queryString;
};

$exportQuery = $queryParams ? http_build_query($queryParams) : '';
$latestTimestamp = $latestEntry['FechaEvento'] ?? null;
$latestFormatted = $latestTimestamp ? date('d/m/Y H:i:s', strtotime((string) $latestTimestamp)) : 'Sin registros';

$decodePayload = static function (?string $json): array {
    if ($json === null || $json === '') {
        return [];
    }
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($appName); ?> | Reportes</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e($asset('css/dashboard.css')); ?>?v=<?= urlencode((string) time()); ?>">
    <link rel="stylesheet" href="<?= e($asset('css/reports.css')); ?>?v=<?= urlencode((string) time()); ?>">
</head>
<body>
    <nav class="navbar">
        <a class="navbar-brand" href="<?= e($publicBase); ?>/index.php">
    <svg viewBox="0 0 24 24" aria-hidden="true" width="24" height="24">
        <path fill="currentColor" d="M3 12 2 7l10-5 10 5-1 5-9 10z" />
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

    <main class="reports-layout">
        <section class="reports-hero">
            <div>
                <p class="eyebrow">Bit&aacute;cora del sistema</p>
                <h1>Auditor&iacute;a en tiempo real</h1>
                <p>Consulta cada alta, actualizaci&oacute;n o baja registrada en las tablas principales. Último evento: <?= e($latestFormatted); ?>.</p>
            </div>
            <div class="reports-stats">
                <div>
                    <span>Registros</span>
                    <strong><?= number_format($totalRecords); ?></strong>
                </div>
                <div>
                    <span>Tablas</span>
                    <strong><?= number_format(count($tables)); ?></strong>
                </div>
                <div>
                    <span>Acciones</span>
                    <strong><?= number_format(count($actions)); ?></strong>
                </div>
                <div>
                    <span>Filtros</span>
                    <strong><?= $filtersApplied ? 'Activos' : 'Sin filtros'; ?></strong>
                </div>
            </div>
        </section>

        <section class="reports-card">
            <header class="reports-card__header">
                <div>
                    <h2>Filtrar movimientos</h2>
                    <p>Refina la consulta por tabla, acci&oacute;n, usuario o rango de fechas.</p>
                </div>
                <div class="reports-export">
                    <a class="primary-btn" href="<?= e($publicBase); ?>/reportes_export.php?format=excel<?= $exportQuery ? '&' . e($exportQuery) : ''; ?>">Exportar Excel</a>
                </div>
            </header>
            <form method="get" class="reports-filters">
                <div>
                    <label for="tabla">Tabla</label>
                    <select id="tabla" name="tabla">
                        <option value="">Todas</option>
                        <?php foreach ($tables as $tableName): ?>
                            <option value="<?= e($tableName); ?>" <?= $tableName === $tablaParam ? 'selected' : ''; ?>><?= e($tableName); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="accion">Acci&oacute;n</label>
                    <select id="accion" name="accion">
                        <option value="">Todas</option>
                        <?php foreach ($actions as $actionName): ?>
                            <option value="<?= e($actionName); ?>" <?= $actionName === $accionParam ? 'selected' : ''; ?>><?= e($actionName); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="usuario">ID Usuario</label>
                    <input type="number" min="1" id="usuario" name="usuario" value="<?= e($usuarioParam ? (string) $usuarioParam : ''); ?>" placeholder="Cualquiera">
                </div>
                <div>
                    <label for="desde">Desde</label>
                    <input type="date" id="desde" name="desde" value="<?= e($desdeParam ?? ''); ?>">
                </div>
                <div>
                    <label for="hasta">Hasta</label>
                    <input type="date" id="hasta" name="hasta" value="<?= e($hastaParam ?? ''); ?>">
                </div>
                <div class="filters-actions">
                    <button type="submit" class="primary-btn">Aplicar filtros</button>
                    <?php if ($filtersApplied): ?>
                        <a class="ghost-btn" href="<?= e($publicBase); ?>/reportes.php">Limpiar</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="reports-card">
            <header class="table-head">
                <div>
                    <h2>Eventos recientes</h2>
                    <p><?= number_format($totalRecords); ?> registros encontrados.</p>
                </div>
            </header>
            <div class="table-scroll">
                <table class="reports-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Tabla</th>
                            <th>Acci&oacute;n</th>
                            <th>ID Registro</th>
                            <th>Usuario</th>
                            <th>Datos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($entries as $entry): ?>
                            <?php
                                $dt = new DateTime($entry['FechaEvento'], new DateTimeZone('UTC')); 
                                $dt->setTimezone(new DateTimeZone('America/Costa_Rica'));
                                $fecha = $dt->format('d/m/Y H:i:s');                                
                                $tabla = (string) $entry['Tabla'];
                                $accion = (string) $entry['Accion'];
                                $registro = (string) $entry['RegistroId'];
                                $usuarioNombre = $entry['NombreUsuario'] ?? null;
                                $usuarioId = $entry['IdUsuario'] ?? null;
                                $payload = $decodePayload($entry['Datos'] ?? null);
                            ?>
                            <tr>
                                <td><?= e($fecha); ?></td>
                                <td><span class="pill"><?= e($tabla); ?></span></td>
                                <td><span class="pill pill-action"><?= e($accion); ?></span></td>
                                <td>#<?= e($registro); ?></td>
                                <td>
                                    <?php if ($usuarioNombre): ?>
                                        <strong><?= e($usuarioNombre); ?></strong>
                                        <small>ID <?= e((string) $usuarioId); ?></small>
                                    <?php elseif ($usuarioId): ?>
                                        <strong>Usuario #<?= e((string) $usuarioId); ?></strong>
                                    <?php else: ?>
                                        <span class="muted">Sistema</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($payload)): ?>
                                        <ul class="payload-list">
                                            <?php foreach ($payload as $key => $value): ?>
                                                <?php $display = is_scalar($value) ? (string) $value : json_encode($value); ?>
                                                <li><strong><?= e((string) $key); ?>:</strong> <?= e($display); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <span class="muted">Sin detalles</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($entries)): ?>
                            <tr>
                                <td colspan="6" class="empty-state">
                                    No hay movimientos que coincidan con los filtros seleccionados.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <?php if ($totalPages > 1): ?>
                    <div class="table-pagination">
                        <?php if ($page > 1): ?>
                            <a class="page-link" href="<?= e($buildPageUrl($page - 1)); ?>">&laquo;</a>
                        <?php endif; ?>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a class="page-link<?= $i === $page ? ' active' : ''; ?>" href="<?= e($buildPageUrl($i)); ?>"><?= $i; ?></a>
                        <?php endfor; ?>
                        <?php if ($page < $totalPages): ?>
                            <a class="page-link" href="<?= e($buildPageUrl($page + 1)); ?>">&raquo;</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>
    <?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
