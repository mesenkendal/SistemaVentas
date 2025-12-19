<?php

declare(strict_types=1);

use SistemaVentas\Models\InventoryModel;
use SistemaVentas\Models\SaleDetailModel;
use SistemaVentas\Models\SaleModel;
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
ensure_access('ventas.php', $publicBase);

$user = $_SESSION['usuario'];
$appName = $config['app']['name'] ?? 'Sistema de Ventas';
$asset = static fn(string $path): string => 'assets/' . ltrim($path, '/');
$navItems = filtered_nav_items($publicBase);

$saleModel = new SaleModel();
$saleDetailModel = new SaleDetailModel();
$inventoryModel = new InventoryModel();

$salesBaseUrl = $publicBase . '/ventas.php';
$currentSalesQuery = is_array($_GET) ? $_GET : [];
$salesFormQuery = $currentSalesQuery ? '?' . http_build_query($currentSalesQuery) : '';
$salesFormActionUrl = $salesBaseUrl . $salesFormQuery;
$salesRedirectParams = $currentSalesQuery;
unset($salesRedirectParams['edit']);
$salesRedirectQuery = $salesRedirectParams ? '?' . http_build_query($salesRedirectParams) : '';
$salesRedirectUrl = $salesBaseUrl . $salesRedirectQuery;
$buildSalesPageUrl = static function (int $targetPage) use ($salesBaseUrl, $currentSalesQuery): string {
    $params = $currentSalesQuery;
    unset($params['edit']);
    if ($targetPage <= 1) {
        unset($params['page']);
    } else {
        $params['page'] = $targetPage;
    }
    $query = $params ? '?' . http_build_query($params) : '';
    return $salesBaseUrl . $query;
};

$inventoryItems = $inventoryModel->all();
$inventoryMap = [];
foreach ($inventoryItems as $inv) {
    $inventoryMap[(int) $inv['CodigoProducto']] = $inv;
}

$flashError = $_SESSION['flash_error'] ?? null;
$flashSuccess = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_error'], $_SESSION['flash_success']);

$formMode = 'create';
$editingSaleId = null;
$formErrors = [];
$formValues = [
    'Fecha'   => date('Y-m-d'),
    'Cliente' => '',
];
$formDetails = [
    [
        'CodigoProducto' => '',
        'Cantidad'       => '1.00',
        'Precio'         => '0.00',
    ],
];

$ensureDetailRows = static function (array $rows): array {
    return empty($rows) ? [[
        'CodigoProducto' => '',
        'Cantidad'       => '1.00',
        'Precio'         => '0.00',
    ]] : $rows;
};

$renderInventoryOptions = static function (?int $selected) use ($inventoryItems): string {
    $options = '<option value=""' . ($selected === null ? ' selected' : '') . ' disabled>Selecciona material</option>';
    foreach ($inventoryItems as $inv) {
        $value = (int) $inv['CodigoProducto'];
        $price = number_format((float) $inv['Precio'], 2, '.', '');
        $isSelected = $selected === $value ? ' selected' : '';
        $options .= '<option value="' . $value . '" data-precio="' . e($price) . '"' . $isSelected . '>' . e((string) $inv['Nombre']) . '</option>';
    }
    return $options;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $currentUserId = $user['id'] ?? null;

    if ($currentUserId === null) {
        $_SESSION['flash_error'] = 'No se pudo identificar al usuario autenticado.';
        header('Location: ' . $salesBaseUrl);
        exit;
    }

    if (in_array($action, ['create', 'update'], true)) {
        $formMode = $action;
        $formValues['Fecha'] = trim((string) ($_POST['fecha'] ?? date('Y-m-d')));
        $formValues['Cliente'] = trim((string) ($_POST['cliente'] ?? ''));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $formValues['Fecha'])) {
            $formErrors[] = 'Selecciona una fecha válida con formato AAAA-MM-DD.';
        }

        $rawProductos = $_POST['detalle_producto'] ?? [];
        $rawCantidades = $_POST['detalle_cantidad'] ?? [];
        $rawPrecios = $_POST['detalle_precio'] ?? [];

        $normalizedDetails = [];
        $formDetails = [];
        $detailCount = max(count($rawProductos), count($rawCantidades), count($rawPrecios));
        for ($i = 0; $i < $detailCount; $i++) {
            $productoId = isset($rawProductos[$i]) ? filter_var($rawProductos[$i], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : null;
            $cantidad = isset($rawCantidades[$i]) ? filter_var($rawCantidades[$i], FILTER_VALIDATE_FLOAT) : null;
            $precio = isset($rawPrecios[$i]) ? filter_var($rawPrecios[$i], FILTER_VALIDATE_FLOAT) : null;

            $formDetails[] = [
                'CodigoProducto' => $productoId ? (string) $productoId : '',
                'Cantidad'       => $cantidad !== null ? number_format((float) $cantidad, 2, '.', '') : '',
                'Precio'         => $precio !== null ? number_format((float) $precio, 2, '.', '') : '',
            ];

            if ($productoId === null && $cantidad === null && $precio === null) {
                continue;
            }

            if ($productoId === null || !isset($inventoryMap[$productoId])) {
                $formErrors[] = 'Selecciona un material válido para cada concepto.';
                continue;
            }

            if ($cantidad === false || $cantidad === null || $cantidad <= 0) {
                $formErrors[] = 'Ingresa una cantidad válida y mayor a cero.';
                continue;
            }

            if ($precio === false || $precio === null || $precio < 0) {
                $formErrors[] = 'Ingresa un precio válido para cada concepto.';
                continue;
            }

            $normalizedDetails[] = [
                'CodigoProducto' => $productoId,
                'Cantidad'       => round((float) $cantidad, 2),
                'Precio'         => round((float) $precio, 2),
            ];
        }

        if (empty($normalizedDetails)) {
            $formErrors[] = 'Debes agregar al menos un concepto válido para registrar la venta.';
        }

        $ventaTotal = 0.0;
        foreach ($normalizedDetails as $detalle) {
            $ventaTotal += $detalle['Cantidad'] * $detalle['Precio'];
        }

        if ($ventaTotal <= 0) {
            $formErrors[] = 'El total de la venta debe ser mayor a cero.';
        }

        if ($action === 'update') {
            $editingSaleId = filter_input(INPUT_POST, 'venta_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
            if ($editingSaleId === null) {
                $formErrors[] = 'No se pudo identificar la venta a actualizar.';
            }
        }

        if (empty($formErrors)) {
            $ventaData = [
                'Fecha'     => $formValues['Fecha'],
                'Cliente'   => $formValues['Cliente'] ?: null,
                'Total'     => $ventaTotal,
                'IdUsuario' => $currentUserId,
            ];

            try {
                if ($action === 'create') {
                    $saleId = $saleModel->createWithDetails($ventaData, $normalizedDetails, $currentUserId);
                    $_SESSION['flash_success'] = 'Venta #' . $saleId . ' registrada correctamente.';
                } else {
                    $saleModel->updateWithDetails((int) $editingSaleId, $ventaData, $normalizedDetails, $currentUserId);
                    $_SESSION['flash_success'] = 'Venta actualizada con éxito.';
                }
            } catch (\Throwable $th) {
                $_SESSION['flash_error'] = 'No fue posible guardar la venta. Intenta nuevamente.';
            }

            header('Location: ' . $salesRedirectUrl);
            exit;
        }

        $formDetails = $ensureDetailRows($formDetails);
    } elseif ($action === 'delete') {
        $saleId = filter_input(INPUT_POST, 'venta_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($saleId === false || $saleId === null) {
            $_SESSION['flash_error'] = 'No se pudo identificar la venta a eliminar.';
        } else {
            try {
                $rows = $saleModel->delete((int) $saleId, $currentUserId);
                $_SESSION['flash_success'] = $rows > 0
                    ? 'Venta eliminada (soft delete) correctamente.'
                    : 'No fue posible eliminar la venta indicada.';
            } catch (\Throwable $th) {
                $_SESSION['flash_error'] = 'Ocurrió un error al eliminar la venta.';
            }
        }

        header('Location: ' . $salesRedirectUrl);
        exit;
    } else {
        $_SESSION['flash_error'] = 'Acción no permitida.';
        header('Location: ' . $salesRedirectUrl);
        exit;
    }
}

if ($formMode === 'create' && isset($_GET['edit'])) {
    $editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($editId) {
        $sale = $saleModel->find((int) $editId);
        if ($sale) {
            $formMode = 'update';
            $editingSaleId = (int) $sale['IdVenta'];
            $formValues = [
                'Fecha'   => (string) $sale['Fecha'],
                'Cliente' => (string) ($sale['Cliente'] ?? ''),
            ];
            $details = $saleDetailModel->forSale($editingSaleId);
            $formDetails = [];
            foreach ($details as $detail) {
                $formDetails[] = [
                    'CodigoProducto' => (string) $detail['CodigoProducto'],
                    'Cantidad'       => number_format((float) $detail['Cantidad'], 2, '.', ''),
                    'Precio'         => number_format((float) $detail['Precio'], 2, '.', ''),
                ];
            }
            $formDetails = $ensureDetailRows($formDetails);
        } else {
            $flashError = 'La venta solicitada no existe o ya fue desactivada.';
        }
    }
}

$formDetails = $ensureDetailRows($formDetails);

$sales = $saleModel->all();
$saleDetailsPayload = [];
$totalSales = count($sales);
$totalAmount = 0.0;
$today = date('Y-m-d');
$todayCount = 0;
$uniqueClients = [];
$clientTotals = [];
$lastSaleTs = null;
$itemsSold = 0;

foreach ($sales as $sale) {
    $saleId = (int) $sale['IdVenta'];
    $totalAmount += (float) $sale['Total'];
    if ((string) $sale['Fecha'] === $today) {
        $todayCount++;
    }
    $cliente = (string) ($sale['Cliente'] ?? 'Sin cliente');
    if ($cliente !== '') {
        $uniqueClients[$cliente] = true;
        $clientTotals[$cliente] = ($clientTotals[$cliente] ?? 0.0) + (float) $sale['Total'];
    }
    $timestamp = strtotime((string) $sale['Fecha']);
    if ($timestamp && ($lastSaleTs === null || $timestamp > $lastSaleTs)) {
        $lastSaleTs = $timestamp;
    }

    $details = $saleDetailModel->forSale($saleId);
    $itemsSold += count($details);
    $saleDetailsPayload[$saleId] = [
        'cliente' => $cliente,
        'fecha'   => (string) $sale['Fecha'],
        'usuario' => (string) $sale['NombreUsuario'],
        'total'   => (float) $sale['Total'],
        'items'   => array_map(static fn(array $detail): array => [
            'producto' => (string) $detail['Nombre'],
            'cantidad' => (float) $detail['Cantidad'],
            'precio'   => (float) $detail['Precio'],
            'subtotal' => (float) $detail['Cantidad'] * (float) $detail['Precio'],
        ], $details),
    ];
}

$averageTicket = $totalSales > 0 ? $totalAmount / $totalSales : 0.0;
$topClient = 'Sin cliente registrado';
if (!empty($clientTotals)) {
    $maxClient = array_keys($clientTotals, max($clientTotals))[0] ?? null;
    if ($maxClient !== null) {
        $topClient = $maxClient;
    }
}
$lastSaleText = $lastSaleTs ? date('d/m/Y', $lastSaleTs) : 'Sin historial';
$uniqueClientsCount = count($uniqueClients);
$emptyFilterId = 'sales-empty-filter';

$salesPerPage = 10;
$salesPage = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
$salesTotalPages = max(1, (int) ceil(($totalSales ?: 0) / $salesPerPage));
if ($salesPage > $salesTotalPages) {
    $salesPage = $salesTotalPages;
}
$salesOffset = max(0, ($salesPage - 1) * $salesPerPage);
$pagedSales = $totalSales > 0 ? array_slice($sales, $salesOffset, $salesPerPage) : [];

$formTotalDisplay = 0.0;
foreach ($formDetails as $detail) {
    $cantidad = isset($detail['Cantidad']) ? (float) $detail['Cantidad'] : 0.0;
    $precio = isset($detail['Precio']) ? (float) $detail['Precio'] : 0.0;
    $formTotalDisplay += $cantidad * $precio;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($appName); ?> | Ventas</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e($asset('css/dashboard.css')); ?>?v=<?= urlencode((string) time()); ?>">
    <link rel="stylesheet" href="<?= e($asset('css/inventory.css')); ?>?v=<?= urlencode((string) time()); ?>">
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

    <main class="inventory-layout">
        <section class="inventory-hero">
            <div>
                <p class="eyebrow">Seguimiento de ventas</p>
                <h1>Pipeline comercial</h1>
                <p>Registra, actualiza y audita cada operación. Última venta: <?= e($lastSaleText); ?>.</p>
                <div class="inventory-stats">
                    <div>
                        <span>Total ventas</span>
                        <strong><?= number_format($totalSales); ?></strong>
                    </div>
                    <div>
                        <span>Facturación acumulada</span>
                        <strong>$<?= number_format($totalAmount, 2); ?></strong>
                    </div>
                    <div>
                        <span>Ticket promedio</span>
                        <strong>$<?= number_format($averageTicket, 2); ?></strong>
                    </div>
                    <div>
                        <span>Ventas hoy</span>
                        <strong><?= number_format($todayCount); ?></strong>
                    </div>
                </div>
            </div>
            <div class="inventory-actions">
                <div>
                    <p>Clientes activos</p>
                    <strong><?= number_format($uniqueClientsCount); ?></strong>
                </div>
                <div>
                    <p>Conceptos vendidos</p>
                    <strong><?= number_format($itemsSold); ?></strong>
                </div>
                <div>
                    <p>Mejor cliente</p>
                    <strong style="font-size: 1.1rem; line-height: 1.3;"><?= e($topClient); ?></strong>
                </div>
            </div>
        </section>

        <?php if ($flashError): ?>
            <div class="alert alert-error"><?= e($flashError); ?></div>
        <?php endif; ?>
        <?php if ($flashSuccess): ?>
            <div class="alert alert-success"><?= e($flashSuccess); ?></div>
        <?php endif; ?>
        <?php if (!empty($formErrors)): ?>
            <div class="alert alert-error">
                <strong>Revisa la información:</strong>
                <ul>
                    <?php foreach ($formErrors as $error): ?>
                        <li><?= e($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="inventory-grid">
            <section class="inventory-table-card">
                <header class="table-head">
                    <div>
                        <h2>Ventas activas</h2>
                        <p><?= number_format($totalSales); ?> operaciones registradas.</p>
                    </div>
                    <div class="filter-bar">
                        <input type="text" id="sales-search" placeholder="Buscar por cliente, usuario o folio">
                    </div>
                </header>
                <div class="table-scroll">
                    <table class="inventory-table sales-table">
                        <thead>
                            <tr>
                                <th>Folio</th>
                                <th>Fecha</th>
                                <th>Cliente</th>
                                <th>Usuario</th>
                                <th>Total</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pagedSales as $sale): ?>
                                <?php
                                    $saleId = (int) $sale['IdVenta'];
                                    $cliente = (string) ($sale['Cliente'] ?? 'Sin cliente');
                                    $usuarioVenta = (string) $sale['NombreUsuario'];
                                    $fecha = (string) $sale['Fecha'];
                                    $total = (float) $sale['Total'];
                                    $searchIndex = strtolower('#' . $saleId . ' ' . $cliente . ' ' . $usuarioVenta . ' ' . $fecha);
                                    $saleEditParams = $currentSalesQuery;
                                    $saleEditParams['edit'] = $saleId;
                                    $saleEditLink = $salesBaseUrl . ($saleEditParams ? '?' . http_build_query($saleEditParams) : '');
                                ?>
                                <tr data-sale-row data-index="<?= e($searchIndex); ?>">
                                    <td>#<?= $saleId; ?></td>
                                    <td><?= e(date('d/m/Y', strtotime($fecha))); ?></td>
                                    <td>
                                        <strong><?= e($cliente ?: 'Sin cliente'); ?></strong>
                                    </td>
                                    <td><?= e($usuarioVenta); ?></td>
                                    <td class="text-right">$<?= number_format($total, 2); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button type= "button" class= "ghost-btn" data-sale-view-data-sale-id="<?= $saleId; ?>">Ver</button>
                                            
                                            <button type="button" class="danger-btn" data-sale-delete data-sale-id="<?= $saleId; ?>">Eliminar</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($pagedSales)): ?>
                                <tr>
                                    <td colspan="6" class="empty-state">No hay ventas registradas.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <div id="<?= e($emptyFilterId); ?>" class="empty-state" hidden>
                        No se encontraron ventas con el filtro ingresado.
                    </div>
                    <?php if ($salesTotalPages > 1): ?>
                        <div class="table-pagination">
                            <?php if ($salesPage > 1): ?>
                                <a class="page-link" href="<?= e($buildSalesPageUrl($salesPage - 1)); ?>">&laquo;</a>
                            <?php endif; ?>
                            <?php for ($salesPageNumber = 1; $salesPageNumber <= $salesTotalPages; $salesPageNumber++): ?>
                                <a class="page-link<?= $salesPageNumber === $salesPage ? ' active' : ''; ?>" href="<?= e($buildSalesPageUrl($salesPageNumber)); ?>">
                                    <?= $salesPageNumber; ?>
                                </a>
                            <?php endfor; ?>
                            <?php if ($salesPage < $salesTotalPages): ?>
                                <a class="page-link" href="<?= e($buildSalesPageUrl($salesPage + 1)); ?>">&raquo;</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="inventory-form-card">
                <?php if ($formMode === 'update' && $editingSaleId !== null): ?>
                    <div class="editing-banner">
                        Editando venta #<?= (int) $editingSaleId; ?>
                        <a href="<?= e($salesRedirectUrl); ?>">Cancelar</a>
                    </div>
                <?php endif; ?>
                <h2><?= $formMode === 'update' ? 'Actualizar venta' : 'Registrar venta'; ?></h2>
                <p><?= $formMode === 'update' ? 'Ajusta los datos necesarios y guarda los cambios.' : 'Completa la información para registrar una nueva venta.'; ?></p>
                <form method="post" class="inventory-form" action="<?= e($salesFormActionUrl); ?>">
                    <input type="hidden" name="action" value="<?= $formMode === 'update' ? 'update' : 'create'; ?>">
                    <?php if ($formMode === 'update' && $editingSaleId !== null): ?>
                        <input type="hidden" name="venta_id" value="<?= (int) $editingSaleId; ?>">
                    <?php endif; ?>
                    <div class="form-group">
                        <label for="fecha">Fecha de la venta</label>
                        <input type="date" id="fecha" name="fecha" required value="<?= e($formValues['Fecha']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="cliente">Cliente (opcional)</label>
                        <input type="text" id="cliente" name="cliente" placeholder="Nombre o razón social" value="<?= e($formValues['Cliente']); ?>">
                    </div>

                    <div class="detail-list" data-detail-list>
                        <?php foreach ($formDetails as $index => $detail): ?>
                            <div class="detail-row" data-detail-row>
                                <div class="form-group">
                                    <label>Producto</label>
                                    <select name="detalle_producto[]" required>
                                        <?= $renderInventoryOptions($detail['CodigoProducto'] !== '' ? (int) $detail['CodigoProducto'] : null); ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Cantidad</label>
                                    <input type="number" step="0.01" min="0.01" name="detalle_cantidad[]" value="<?= e($detail['Cantidad']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Precio unitario</label>
                                    <input type="number" step="0.01" min="0" name="detalle_precio[]" value="<?= e($detail['Precio']); ?>" required>
                                </div>
                                <button type="button" class="ghost-btn remove-detail" data-remove-detail>&times;</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="detail-actions">
                        <button type="button" class="ghost-btn" data-add-detail>Agregar concepto</button>
                    </div>

                    <div class="form-summary">
                        <div>
                            <span>Total estimado</span>
                            <strong id="sales-total-display">$<?= number_format($formTotalDisplay, 2); ?></strong>
                        </div>
                        <small>El total se recalcula automáticamente según los conceptos.</small>
                    </div>

                    <button type="submit" class="primary-btn">
                        <?= $formMode === 'update' ? 'Guardar cambios' : 'Registrar venta'; ?>
                    </button>
                </form>
            </section>
        </div>
    </main>

    <div class="modal" id="modal-sale-view" aria-hidden="true">
        <div class="modal-dialog modal-wide">
            <button type="button" class="modal-close" data-close-modal>&times;</button>
            <p class="eyebrow">Detalle de la venta</p>
            <h3 id="view-sale-title">Venta</h3>
            <div class="modal-details">
                <div>
                    <dt>Cliente</dt>
                    <dd id="view-sale-client">-</dd>
                </div>
                <div>
                    <dt>Fecha</dt>
                    <dd id="view-sale-date">-</dd>
                </div>
                <div>
                    <dt>Usuario</dt>
                    <dd id="view-sale-user">-</dd>
                </div>
                <div>
                    <dt>Total</dt>
                    <dd id="view-sale-total">-</dd>
                </div>
            </div>
            <div class="modal-table">
                <table>
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Precio</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody id="view-sale-items">
                        <tr><td colspan="4">Sin conceptos</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="modal-actions">
                <button type="button" class="ghost-btn" data-close-modal>Cerrar</button>
            </div>
        </div>
    </div>

    <div class="modal" id="modal-sale-delete" aria-hidden="true">
        <div class="modal-dialog">
            <button type="button" class="modal-close" data-close-modal>&times;</button>
            <p class="eyebrow">Eliminar venta</p>
            <h3>Esta acción aplicará soft delete</h3>
            <p>La venta y sus detalles quedarán inactivos pero se conservarán para auditoría.</p>
            <div class="modal-warning">
                <strong id="delete-sale-label">Venta</strong>
            </div>
            <div class="modal-actions">
                <button type="button" class="ghost-btn" data-close-modal>Cancelar</button>
                <button type="button" class="danger-btn" id="confirm-sale-delete">Eliminar</button>
            </div>
        </div>
    </div>

    <form method="post" id="sale-delete-form" action="<?= e($salesRedirectUrl); ?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="venta_id" id="delete-sale-id">
    </form>

    <template id="detail-row-template">
        <div class="detail-row" data-detail-row>
            <div class="form-group">
                <label>Producto</label>
                <select name="detalle_producto[]" required>
                    <?= $renderInventoryOptions(null); ?>
                </select>
            </div>
            <div class="form-group">
                <label>Cantidad</label>
                <input type="number" step="0.01" min="0.01" name="detalle_cantidad[]" value="1.00" required>
            </div>
            <div class="form-group">
                <label>Precio unitario</label>
                <input type="number" step="0.01" min="0" name="detalle_precio[]" value="0.00" required>
            </div>
            <button type="button" class="ghost-btn remove-detail" data-remove-detail>&times;</button>
        </div>
    </template>

    <script>
        window.saleDetails = <?= json_encode($saleDetailsPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    </script>
    <script>
        (function () {
            const body = document.body;
            const viewModal = document.getElementById('modal-sale-view');
            const deleteModal = document.getElementById('modal-sale-delete');
            const deleteForm = document.getElementById('sale-delete-form');
            const deleteInput = document.getElementById('delete-sale-id');
            const deleteLabel = document.getElementById('delete-sale-label');
            const confirmDelete = document.getElementById('confirm-sale-delete');
            const saleItemsTable = document.getElementById('view-sale-items');
            const titleField = document.getElementById('view-sale-title');
            const clientField = document.getElementById('view-sale-client');
            const dateField = document.getElementById('view-sale-date');
            const userField = document.getElementById('view-sale-user');
            const totalField = document.getElementById('view-sale-total');
            const detailList = document.querySelector('[data-detail-list]');
            const addDetailBtn = document.querySelector('[data-add-detail]');
            const detailTemplate = document.getElementById('detail-row-template');
            const totalDisplay = document.getElementById('sales-total-display');
            const searchInput = document.getElementById('sales-search');
            const rows = Array.from(document.querySelectorAll('[data-sale-row]'));
            const emptyFilter = document.getElementById('<?= e($emptyFilterId); ?>');

            const toggleBodyLock = () => {
                const anyOpen = document.querySelector('.modal.open');
                body.classList.toggle('modal-open', Boolean(anyOpen));
            };

            const openModal = (modal) => {
                if (!modal) return;
                modal.classList.add('open');
                toggleBodyLock();
            };

            const closeModal = (modal) => {
                if (!modal) return;
                modal.classList.remove('open');
                toggleBodyLock();
            };

            document.querySelectorAll('[data-close-modal]').forEach((btn) => {
                btn.addEventListener('click', () => closeModal(btn.closest('.modal')));
            });

            document.querySelectorAll('[data-sale-view]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const saleId = btn.dataset.saleId;
                    const payload = window.saleDetails?.[saleId];
                    if (!payload) {
                        return;
                    }
                    titleField.textContent = 'Venta #' + saleId;
                    clientField.textContent = payload.cliente || 'Sin cliente';
                    dateField.textContent = payload.fecha || '-';
                    userField.textContent = payload.usuario || '-';
                    totalField.textContent = '$' + (payload.total ?? 0).toFixed(2);
                    saleItemsTable.innerHTML = '';
                    if (!payload.items || !payload.items.length) {
                        saleItemsTable.innerHTML = '<tr><td colspan="4">Sin conceptos</td></tr>';
                    } else {
                        payload.items.forEach((item) => {
                            const row = document.createElement('tr');
                            const values = [
                                item.producto || '-',
                                Number(item.cantidad || 0).toFixed(2),
                                '$' + Number(item.precio || 0).toFixed(2),
                                '$' + Number(item.subtotal || 0).toFixed(2),
                            ];
                            values.forEach((value) => {
                                const cell = document.createElement('td');
                                cell.textContent = value;
                                row.appendChild(cell);
                            });
                            saleItemsTable.appendChild(row);
                        });
                    }
                    openModal(viewModal);
                });
            });

            document.querySelectorAll('[data-sale-delete]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const saleId = btn.dataset.saleId;
                    deleteInput.value = saleId;
                    deleteLabel.textContent = 'Venta #' + saleId;
                    openModal(deleteModal);
                });
            });

            confirmDelete?.addEventListener('click', () => {
                if (deleteInput.value) {
                    deleteForm.submit();
                }
            });

            const recalcTotal = () => {
                let total = 0;
                detailList.querySelectorAll('[data-detail-row]').forEach((row) => {
                    const qty = parseFloat(row.querySelector('input[name="detalle_cantidad[]"]').value) || 0;
                    const price = parseFloat(row.querySelector('input[name="detalle_precio[]"]').value) || 0;
                    total += qty * price;
                });
                if (totalDisplay) {
                    totalDisplay.textContent = '$' + total.toFixed(2);
                }
            };

            detailList.addEventListener('input', (event) => {
                if (event.target.matches('input[name="detalle_cantidad[]"], input[name="detalle_precio[]"]')) {
                    recalcTotal();
                }
            });

            detailList.addEventListener('change', (event) => {
                if (event.target.matches('select[name="detalle_producto[]"]')) {
                    const selected = event.target.selectedOptions[0];
                    if (selected && selected.dataset.precio) {
                        const sibling = event.target.closest('[data-detail-row]').querySelector('input[name="detalle_precio[]"]');
                        if (sibling && (!sibling.value || Number(sibling.value) === 0)) {
                            sibling.value = parseFloat(selected.dataset.precio).toFixed(2);
                            recalcTotal();
                        }
                    }
                }
            });

            const bindRow = (row) => {
                const removeBtn = row.querySelector('[data-remove-detail]');
                removeBtn?.addEventListener('click', () => {
                    if (detailList.querySelectorAll('[data-detail-row]').length === 1) {
                        return;
                    }
                    row.remove();
                    recalcTotal();
                });
            };

            detailList.querySelectorAll('[data-detail-row]').forEach(bindRow);

            addDetailBtn?.addEventListener('click', () => {
                if (!detailTemplate?.content) {
                    return;
                }
                const clone = detailTemplate.content.cloneNode(true);
                detailList.appendChild(clone);
                const newRow = detailList.querySelector('[data-detail-row]:last-child');
                bindRow(newRow);
                recalcTotal();
            });

            const applyFilter = () => {
                const term = (searchInput?.value || '').trim().toLowerCase();
                let visible = 0;
                rows.forEach((row) => {
                    if (!term || (row.dataset.index || '').includes(term)) {
                        row.style.display = '';
                        visible++;
                    } else {
                        row.style.display = 'none';
                    }
                });
                if (emptyFilter) {
                    emptyFilter.hidden = Boolean(visible) || term.length === 0;
                }
            };

            searchInput?.addEventListener('input', applyFilter);

            recalcTotal();
        })();
    </script>
    <?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
