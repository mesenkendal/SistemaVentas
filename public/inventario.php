<?php

declare(strict_types=1);

use SistemaVentas\Models\InventoryModel;
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
ensure_access('inventario.php', $publicBase);

$user = $_SESSION['usuario'];
$appName = $config['app']['name'] ?? 'Sistema de Ventas';
$asset = static fn(string $path): string => 'assets/' . ltrim($path, '/');
$navItems = filtered_nav_items($publicBase);

$inventoryModel = new InventoryModel();

$inventoryBaseUrl = $publicBase . '/inventario.php';
$currentQueryParams = is_array($_GET) ? $_GET : [];
$formQueryString = $currentQueryParams ? '?' . http_build_query($currentQueryParams) : '';
$formActionUrl = $inventoryBaseUrl . $formQueryString;
$redirectParams = $currentQueryParams;
unset($redirectParams['edit']);
$redirectQueryString = $redirectParams ? '?' . http_build_query($redirectParams) : '';
$redirectUrl = $inventoryBaseUrl . $redirectQueryString;
$buildInventoryPageUrl = static function (int $targetPage) use ($inventoryBaseUrl, $currentQueryParams): string {
    $params = $currentQueryParams;
    unset($params['edit']);
    if ($targetPage <= 1) {
        unset($params['page']);
    } else {
        $params['page'] = $targetPage;
    }
    $query = $params ? '?' . http_build_query($params) : '';
    return $inventoryBaseUrl . $query;
};

$flashError = $_SESSION['flash_error'] ?? null;
$flashSuccess = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_error'], $_SESSION['flash_success']);

$formErrors = [];
$formValues = [
    'Nombre'    => '',
    'TipoVenta' => 'Kilo',
    'Precio'    => '',
    'Stock'     => '',
];
$mode = 'create';
$editingId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $currentUserId = $user['id'] ?? null;

    if (in_array($action, ['create', 'update'], true)) {
        $formValues['Nombre'] = trim((string) filter_input(INPUT_POST, 'nombre', FILTER_UNSAFE_RAW));
        $formValues['TipoVenta'] = (string) filter_input(INPUT_POST, 'tipoVenta', FILTER_UNSAFE_RAW);
        $formValues['Precio'] = (string) filter_input(INPUT_POST, 'precio', FILTER_UNSAFE_RAW);
        $formValues['Stock'] = (string) filter_input(INPUT_POST, 'stock', FILTER_UNSAFE_RAW);

        $precioValue = filter_var($formValues['Precio'], FILTER_VALIDATE_FLOAT);
        $stockValue = filter_var($formValues['Stock'], FILTER_VALIDATE_FLOAT);

        $nameLength = function_exists('mb_strlen') ? mb_strlen($formValues['Nombre']) : strlen($formValues['Nombre']);

        if ($formValues['Nombre'] === '') {
            $formErrors[] = 'El nombre del material es obligatorio.';
        } elseif ($nameLength > 100) {
            $formErrors[] = 'El nombre supera el límite permitido (100 caracteres).';
        }

        if (!in_array($formValues['TipoVenta'], ['Kilo', 'Unidad'], true)) {
            $formErrors[] = 'Selecciona un tipo de venta válido.';
        }

        if ($precioValue === false || $precioValue < 0) {
            $formErrors[] = 'Ingresa un precio válido (mayor o igual a 0).';
        }

        if ($stockValue === false || $stockValue < 0) {
            $formErrors[] = 'Ingresa un stock válido (mayor o igual a 0).';
        }

        if ($action === 'update') {
            $mode = 'update';
            $editingId = filter_input(
                INPUT_POST,
                'codigo',
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            ) ?: null;

            if ($editingId === null) {
                $formErrors[] = 'No se pudo identificar el material a actualizar.';
            }
        }

        if (empty($formErrors)) {
            $payload = [
                'Nombre'    => $formValues['Nombre'],
                'TipoVenta' => $formValues['TipoVenta'],
                'Precio'    => $precioValue ?? 0.0,
                'Stock'     => $stockValue ?? 0.0,
            ];

            try {
                if ($action === 'create') {
                    $inventoryModel->create($payload, $currentUserId);
                    $_SESSION['flash_success'] = 'Material agregado al inventario.';
                } else {
                    $affected = $inventoryModel->update((int) $editingId, $payload, $currentUserId);
                    $_SESSION['flash_success'] = $affected > 0
                        ? 'Material actualizado correctamente.'
                        : 'No hubo cambios para guardar.';
                }
            } catch (\Throwable $th) {
                $_SESSION['flash_error'] = 'No fue posible guardar el material. Intenta nuevamente.';
            }

            header('Location: ' . $redirectUrl);
            exit;
        }
    } elseif ($action === 'delete') {
        $codigo = filter_input(INPUT_POST, 'codigo', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($codigo === false || $codigo === null) {
            $_SESSION['flash_error'] = 'Material no válido para eliminar.';
        } else {
            $rows = $inventoryModel->delete((int) $codigo, $currentUserId);
            $_SESSION['flash_success'] = $rows > 0
                ? 'Material eliminado (soft delete) exitosamente.'
                : 'No fue posible eliminar el material solicitado.';
        }

        header('Location: ' . $redirectUrl);
        exit;
    } else {
        $_SESSION['flash_error'] = 'Acción no permitida.';
        header('Location: ' . $redirectUrl);
        exit;
    }
}

if ($mode === 'create' && isset($_GET['edit'])) {
    $editCode = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($editCode) {
        $item = $inventoryModel->find((int) $editCode);
        if ($item) {
            $mode = 'update';
            $editingId = (int) $item['CodigoProducto'];
            $formValues = [
                'Nombre'    => (string) $item['Nombre'],
                'TipoVenta' => (string) $item['TipoVenta'],
                'Precio'    => (string) $item['Precio'],
                'Stock'     => (string) $item['Stock'],
            ];
        } else {
            $flashError = 'El material solicitado no existe o ya fue eliminado.';
        }
    }
}

$items = $inventoryModel->all();
$totalItems = count($items);
$totalStock = 0.0;
$totalValue = 0.0;
$lowStock = 0;
$latestUpdate = null;
$tipoBreakdown = [
    'Kilo'   => 0,
    'Unidad' => 0,
];

foreach ($items as $item) {
    $stock = (float) $item['Stock'];
    $precio = (float) $item['Precio'];
    $totalStock += $stock;
    $totalValue += $stock * $precio;
    if ($stock <= 5) {
        $lowStock++;
    }
    if (isset($tipoBreakdown[$item['TipoVenta']])) {
        $tipoBreakdown[$item['TipoVenta']]++;
    }
    $timestamp = strtotime((string) $item['FechaActualiza']);
    if ($timestamp && ($latestUpdate === null || $timestamp > $latestUpdate)) {
        $latestUpdate = $timestamp;
    }
}

$lastUpdatedText = $latestUpdate ? date('d/m/Y H:i', $latestUpdate) : 'Sin movimientos registrados';
$emptyFilterId = 'empty-filter';

$inventoryPerPage = 10;
$inventoryPage = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
$inventoryTotalPages = max(1, (int) ceil(($totalItems ?: 0) / $inventoryPerPage));
if ($inventoryPage > $inventoryTotalPages) {
    $inventoryPage = $inventoryTotalPages;
}
$inventoryOffset = max(0, ($inventoryPage - 1) * $inventoryPerPage);
$pagedItems = $totalItems > 0 ? array_slice($items, $inventoryOffset, $inventoryPerPage) : [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($appName); ?> | Inventario</title>
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
                <p class="eyebrow">Control de materiales</p>
                <h1>Inventario operativo</h1>
                <p>Gestiona altas, actualizaciones y bajas lógicas de tus recursos. Último ajuste: <?= e($lastUpdatedText); ?>.</p>
                <div class="inventory-stats">
                    <div>
                        <span>Total SKU</span>
                        <strong><?= number_format($totalItems); ?></strong>
                    </div>
                    <div>
                        <span>Valor estimado</span>
                        <strong>₡<?= number_format($totalValue, 2); ?></strong>
                    </div>
                    <div>
                        <span>Stock acumulado</span>
                        <strong><?= number_format($totalStock, 2); ?></strong>
                    </div>
                    <div>
                        <span>Alertas de stock</span>
                        <strong><?= number_format($lowStock); ?></strong>
                    </div>
                </div>
            </div>
            <div class="inventory-actions">
                <div>
                    <p>Venta por kilo</p>
                    <strong><?= number_format($tipoBreakdown['Kilo']); ?></strong>
                </div>
                <div>
                    <p>Venta por unidad</p>
                    <strong><?= number_format($tipoBreakdown['Unidad']); ?></strong>
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
                <strong>Revisa el formulario:</strong>
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
                        <h2>Productos activos</h2>
                        <p><?= number_format($totalItems); ?> registros disponibles para operar.</p>
                    </div>
                    <div class="filter-bar">
                        <input type="text" id="inventory-search" placeholder="Buscar por nombre o tipo">
                    </div>
                </header>
                <div class="table-scroll">
                    <table class="inventory-table">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Nombre</th>
                                <th>Tipo</th>
                                <th>Precio</th>
                                <th>Stock</th>
                                <th>Actualizaci&oacute;n</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pagedItems as $item): ?>
                                <?php
                                    $codigo = (int) $item['CodigoProducto'];
                                    $nombre = (string) $item['Nombre'];
                                    $tipo = (string) $item['TipoVenta'];
                                    $precio = (float) $item['Precio'];
                                    $stock = (float) $item['Stock'];
                                    $fecha = (string) $item['FechaActualiza'];
                                    $rowIndex = strtolower($nombre . ' ' . $tipo);
                                    $isLow = $stock <= 5;
                                    $fechaTimestamp = strtotime($fecha);
                                    $fechaFormato = $fechaTimestamp ? date('d/m/Y H:i', $fechaTimestamp) : 'Sin registro';
                                    $editParams = $currentQueryParams;
                                    $editParams['edit'] = $codigo;
                                    $editLink = $inventoryBaseUrl . ($editParams ? '?' . http_build_query($editParams) : '');
                                ?>
                                <tr data-inventory-row data-index="<?= e($rowIndex); ?>">
                                    <td>#<?= $codigo; ?></td>
                                    <td>
                                        <strong><?= e($nombre); ?></strong>
                                        <?php if ($isLow): ?>
                                            <span class="pill pill-warning">Stock bajo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="pill"><?= e($tipo); ?></span></td>
                                    <td>₡<?= number_format($precio, 2); ?></td>
                                    <td><?= number_format($stock, 2); ?></td>
                                    <td><?= e($fechaFormato); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button type="button"
                                                class="ghost-btn"
                                                data-view-trigger
                                                data-codigo="<?= $codigo; ?>"
                                                data-nombre="<?= e($nombre); ?>"
                                                data-tipoventa="<?= e($tipo); ?>"
                                                data-precio="<?= number_format($precio, 2, '.', ''); ?>"
                                                data-stock="<?= number_format($stock, 2, '.', ''); ?>"
                                                data-fecha="<?= e($fechaFormato); ?>">
                                                Ver
                                            </button>
                                            <a class="ghost-btn" href="<?= e($editLink); ?>">Editar</a>
                                            <button type="button"
                                                class="danger-btn"
                                                data-delete-trigger
                                                data-codigo="<?= $codigo; ?>"
                                                data-nombre="<?= e($nombre); ?>">
                                                Eliminar
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($pagedItems)): ?>
                                <tr>
                                    <td colspan="7" class="empty-state">
                                        No hay materiales activos registrados.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <div id="<?= e($emptyFilterId); ?>" class="empty-state" hidden>
                        No se encontraron coincidencias con el filtro aplicado.
                    </div>
                    <?php if ($inventoryTotalPages > 1): ?>
                        <div class="table-pagination">
                            <?php if ($inventoryPage > 1): ?>
                                <a class="page-link" href="<?= e($buildInventoryPageUrl($inventoryPage - 1)); ?>">&laquo;</a>
                            <?php endif; ?>
                            <?php for ($pageNumber = 1; $pageNumber <= $inventoryTotalPages; $pageNumber++): ?>
                                <a class="page-link<?= $pageNumber === $inventoryPage ? ' active' : ''; ?>" href="<?= e($buildInventoryPageUrl($pageNumber)); ?>">
                                    <?= $pageNumber; ?>
                                </a>
                            <?php endfor; ?>
                            <?php if ($inventoryPage < $inventoryTotalPages): ?>
                                <a class="page-link" href="<?= e($buildInventoryPageUrl($inventoryPage + 1)); ?>">&raquo;</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="inventory-form-card">
                <?php if ($mode === 'update' && $editingId !== null): ?>
                    <div class="editing-banner">
                        Estás editando el material #<?= (int) $editingId; ?>
                        <a href="<?= e($redirectUrl); ?>">Cancelar</a>
                    </div>
                <?php endif; ?>
                <h2><?= $mode === 'update' ? 'Actualizar material' : 'Registrar Producto'; ?></h2>
                <p><?= $mode === 'update' ? 'Modifica los campos requeridos y guarda los cambios.' : 'Completa el formulario para agregar un nuevo insumo.'; ?></p>
                <form method="post" class="inventory-form" action="<?= e($formActionUrl); ?>">
                    <input type="hidden" name="action" value="<?= $mode === 'update' ? 'update' : 'create'; ?>">
                    <?php if ($mode === 'update' && $editingId !== null): ?>
                        <input type="hidden" name="codigo" value="<?= (int) $editingId; ?>">
                    <?php endif; ?>
                    <div class="form-group">
                        <label for="nombre">Nombre del material</label>
                        <input type="text" id="nombre" name="nombre" maxlength="100" required value="<?= e($formValues['Nombre']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="tipoVenta">Tipo de venta</label>
                        <select id="tipoVenta" name="tipoVenta" required>
                            <option value="Kilo" <?= $formValues['TipoVenta'] === 'Kilo' ? 'selected' : ''; ?>>Por kilo</option>
                            <option value="Unidad" <?= $formValues['TipoVenta'] === 'Unidad' ? 'selected' : ''; ?>>Por unidad</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="precio">Precio unitario</label>
                            <input type="number" step="0.01" min="0" id="precio" name="precio" required value="<?= e($formValues['Precio']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="stock">Stock disponible</label>
                            <input type="number" step="0.01" min="0" id="stock" name="stock" required value="<?= e($formValues['Stock']); ?>">
                        </div>
                    </div>
                    <button type="submit" class="primary-btn">
                        <?= $mode === 'update' ? 'Guardar cambios' : 'Agregar Productos'; ?>
                    </button>
                </form>
            </section>
        </div>
    </main>

    <div class="modal" id="modal-view" aria-hidden="true">
        <div class="modal-dialog">
            <button type="button" class="modal-close" data-close-modal>&times;</button>
            <p class="eyebrow">Detalle del material</p>
            <h3 id="view-name">-</h3>
            <dl class="modal-details">
                <div>
                    <dt>Código</dt>
                    <dd id="view-code">-</dd>
                </div>
                <div>
                    <dt>Tipo de venta</dt>
                    <dd id="view-type">-</dd>
                </div>
                <div>
                    <dt>Precio</dt>
                    <dd id="view-price">-</dd>
                </div>
                <div>
                    <dt>Stock</dt>
                    <dd id="view-stock">-</dd>
                </div>
                <div>
                    <dt>Última actualización</dt>
                    <dd id="view-date">-</dd>
                </div>
            </dl>
            <div class="modal-actions">
                <button type="button" class="ghost-btn" data-close-modal>Cerrar</button>
            </div>
        </div>
    </div>

    <div class="modal" id="modal-delete" aria-hidden="true">
        <div class="modal-dialog">
            <button type="button" class="modal-close" data-close-modal>&times;</button>
            <p class="eyebrow">Confirmar eliminación</p>
            <h3>¿Deseas eliminar este material?</h3>
            <p>Esta acción aplicará un soft delete: el registro quedará inactivo pero podrás recuperarlo desde base de datos.</p>
            <div class="modal-warning">
                <strong id="delete-name">-</strong>
            </div>
            <div class="modal-actions">
                <button type="button" class="ghost-btn" data-close-modal>Cancelar</button>
                <button type="button" class="danger-btn" id="confirm-delete">Eliminar</button>
            </div>
        </div>
    </div>

    <form method="post" id="delete-form" action="<?= e($redirectUrl); ?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="codigo" id="delete-id">
    </form>

    <script>
        (function () {
            const body = document.body;
            const viewModal = document.getElementById('modal-view');
            const deleteModal = document.getElementById('modal-delete');
            const deleteForm = document.getElementById('delete-form');
            const deleteInput = document.getElementById('delete-id');
            const deleteName = document.getElementById('delete-name');
            const confirmDelete = document.getElementById('confirm-delete');
            const viewFields = {
                name: document.getElementById('view-name'),
                code: document.getElementById('view-code'),
                type: document.getElementById('view-type'),
                price: document.getElementById('view-price'),
                stock: document.getElementById('view-stock'),
                date: document.getElementById('view-date'),
            };

            const toggleBodyLock = () => {
                const anyOpen = document.querySelector('.modal.open');
                body.classList.toggle('modal-open', Boolean(anyOpen));
            };

            const openModal = (modal) => {
                if (!modal) {
                    return;
                }
                modal.classList.add('open');
                toggleBodyLock();
            };

            const closeModal = (modal) => {
                if (!modal) {
                    return;
                }
                modal.classList.remove('open');
                toggleBodyLock();
            };

            document.querySelectorAll('[data-close-modal]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    closeModal(btn.closest('.modal'));
                });
            });

            document.querySelectorAll('[data-view-trigger]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    viewFields.name.textContent = btn.dataset.nombre || '-';
                    viewFields.code.textContent = '#' + (btn.dataset.codigo || '-');
                    viewFields.type.textContent = btn.dataset.tipoventa || '-';
                    viewFields.price.textContent = '$' + (btn.dataset.precio || '0.00');
                    viewFields.stock.textContent = btn.dataset.stock || '0';
                    viewFields.date.textContent = btn.dataset.fecha || '-';
                    openModal(viewModal);
                });
            });

            document.querySelectorAll('[data-delete-trigger]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    deleteInput.value = btn.dataset.codigo || '';
                    deleteName.textContent = btn.dataset.nombre || '';
                    openModal(deleteModal);
                });
            });

            confirmDelete?.addEventListener('click', () => {
                if (deleteInput.value) {
                    deleteForm.submit();
                }
            });

            const searchInput = document.getElementById('inventory-search');
            const rows = Array.from(document.querySelectorAll('[data-inventory-row]'));
            const emptyFilter = document.getElementById('<?= e($emptyFilterId); ?>');

            const applyFilter = () => {
                if (!searchInput) {
                    return;
                }
                const term = searchInput.value.trim().toLowerCase();
                let visibleRows = 0;
                rows.forEach((row) => {
                    if (!term || (row.dataset.index || '').includes(term)) {
                        row.style.display = '';
                        visibleRows++;
                    } else {
                        row.style.display = 'none';
                    }
                });
                if (emptyFilter) {
                    emptyFilter.hidden = Boolean(visibleRows) || term.length === 0;
                }
            };

            searchInput?.addEventListener('input', applyFilter);
        })();
    </script>
    <?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
