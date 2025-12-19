<?php

declare(strict_types=1);

use SistemaVentas\Models\RoleModel;
use SistemaVentas\Models\UserModel;
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
ensure_access('usuarios.php', $publicBase);

$user = $_SESSION['usuario'];
$appName = $config['app']['name'] ?? 'Sistema de Ventas';
$asset = static fn(string $path): string => 'assets/' . ltrim($path, '/');
$navItems = filtered_nav_items($publicBase);

$userModel = new UserModel();
$roleModel = new RoleModel();

$usersBaseUrl = $publicBase . '/usuarios.php';
$currentQuery = is_array($_GET) ? $_GET : [];
$formQuery = $currentQuery ? '?' . http_build_query($currentQuery) : '';
$formActionUrl = $usersBaseUrl . $formQuery;
$redirectParams = $currentQuery;
unset($redirectParams['edit']);
$redirectQuery = $redirectParams ? '?' . http_build_query($redirectParams) : '';
$redirectUrl = $usersBaseUrl . $redirectQuery;
$buildUsersPageUrl = static function (int $targetPage) use ($usersBaseUrl, $currentQuery): string {
    $params = $currentQuery;
    unset($params['edit']);
    if ($targetPage <= 1) {
        unset($params['page']);
    } else {
        $params['page'] = $targetPage;
    }
    $query = $params ? '?' . http_build_query($params) : '';
    return $usersBaseUrl . $query;
};

$roles = $roleModel->all();
$roleMap = [];
foreach ($roles as $rol) {
    $roleMap[(int) $rol['IdRol']] = $rol;
}

$flashError = $_SESSION['flash_error'] ?? null;
$flashSuccess = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_error'], $_SESSION['flash_success']);

$formMode = 'create';
$editingUserId = null;
$formErrors = [];
$formValues = [
    'NombreUsuario' => '',
    'Apellido'      => '',
    'IdRol'         => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $currentUserId = $user['id'] ?? null;

    if (!in_array($action, ['create', 'update', 'delete'], true)) {
        $_SESSION['flash_error'] = 'Acción no permitida.';
        header('Location: ' . $redirectUrl);
        exit;
    }

    if ($currentUserId === null) {
        $_SESSION['flash_error'] = 'No se pudo identificar al usuario autenticado.';
        header('Location: ' . $redirectUrl);
        exit;
    }

    if (in_array($action, ['create', 'update'], true)) {
        $formMode = $action;
        $formValues['NombreUsuario'] = trim((string) ($_POST['nombre'] ?? ''));
        $formValues['Apellido'] = trim((string) ($_POST['apellido'] ?? ''));
        $formValues['IdRol'] = trim((string) ($_POST['rol'] ?? ''));
        $password = (string) ($_POST['clave'] ?? '');

        $nameLength = function_exists('mb_strlen') ? mb_strlen($formValues['NombreUsuario']) : strlen($formValues['NombreUsuario']);
        $lastLength = function_exists('mb_strlen') ? mb_strlen($formValues['Apellido']) : strlen($formValues['Apellido']);

        if ($formValues['NombreUsuario'] === '') {
            $formErrors[] = 'El nombre es obligatorio.';
        } elseif ($nameLength > 50) {
            $formErrors[] = 'El nombre supera el límite de 50 caracteres.';
        }

        if ($formValues['Apellido'] === '') {
            $formErrors[] = 'El apellido es obligatorio.';
        } elseif ($lastLength > 50) {
            $formErrors[] = 'El apellido supera el límite de 50 caracteres.';
        }

        $roleId = filter_var($formValues['IdRol'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
        if ($roleId === null || !isset($roleMap[$roleId])) {
            $formErrors[] = 'Selecciona un rol válido.';
        }

        if ($action === 'create' && trim($password) === '') {
            $formErrors[] = 'Debes asignar una clave temporal para el usuario.';
        }

        if ($action === 'update') {
            $editingUserId = filter_input(INPUT_POST, 'usuario_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
            if ($editingUserId === null) {
                $formErrors[] = 'No se pudo identificar el usuario a actualizar.';
            }
        }

        if (empty($formErrors)) {
            $payload = [
                'IdRol'         => $roleId,
                'NombreUsuario' => $formValues['NombreUsuario'],
                'Apellido'      => $formValues['Apellido'],
            ];

            if (trim($password) !== '') {
                $payload['Clave'] = password_hash($password, PASSWORD_BCRYPT);
            }

            try {
                if ($action === 'create') {
                    $payload['Clave'] = $payload['Clave'] ?? password_hash($password, PASSWORD_BCRYPT);
                    $payload['Activo'] = 1;
                    $newId = $userModel->create($payload, $currentUserId);
                    $_SESSION['flash_success'] = 'Usuario #' . $newId . ' registrado correctamente.';
                } else {
                    $affected = $userModel->update((int) $editingUserId, $payload, $currentUserId);
                    $_SESSION['flash_success'] = $affected > 0
                        ? 'Usuario actualizado con éxito.'
                        : 'No hubo cambios que guardar.';
                }
            } catch (\Throwable $th) {
                $_SESSION['flash_error'] = 'No fue posible guardar el usuario. Intenta nuevamente.';
            }

            header('Location: ' . $redirectUrl);
            exit;
        }
    }

    if ($action === 'delete') {
        $deleteId = filter_input(INPUT_POST, 'usuario_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($deleteId === false || $deleteId === null) {
            $_SESSION['flash_error'] = 'No se pudo identificar el usuario a eliminar.';
        } else {
            $rows = $userModel->delete((int) $deleteId, $currentUserId);
            $_SESSION['flash_success'] = $rows > 0
                ? 'Usuario eliminado (soft delete) correctamente.'
                : 'No fue posible eliminar el usuario indicado.';
        }

        header('Location: ' . $redirectUrl);
        exit;
    }
}

if ($formMode === 'create' && isset($_GET['edit'])) {
    $editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($editId) {
        $editingUser = $userModel->find((int) $editId);
        if ($editingUser) {
            $formMode = 'update';
            $editingUserId = (int) $editingUser['IdUsuario'];
            $formValues = [
                'NombreUsuario' => (string) $editingUser['NombreUsuario'],
                'Apellido'      => (string) $editingUser['Apellido'],
                'IdRol'         => (string) $editingUser['IdRol'],
            ];
        } else {
            $flashError = 'El usuario solicitado no existe o ya fue desactivado.';
        }
    }
}

$activeUsers = $userModel->all();
$allUsers = $userModel->all(false);
$activeCount = count($activeUsers);
$totalUsers = count($allUsers);
$inactiveCount = max(0, $totalUsers - $activeCount);

$recentJoins = 0;
$latestJoinTs = null;
$roleLeader = 'Sin registro';
$roleDistribution = [];

foreach ($allUsers as $member) {
    $timestamp = strtotime((string) $member['FechaCreacion']);
    if ($timestamp && ($latestJoinTs === null || $timestamp > $latestJoinTs)) {
        $latestJoinTs = $timestamp;
    }
    if ($timestamp && (time() - $timestamp) <= 30 * 86400) {
        $recentJoins++;
    }
    $roleName = (string) ($member['NombreRol'] ?? 'Rol indefinido');
    $roleDistribution[$roleName] = ($roleDistribution[$roleName] ?? 0) + 1;
}

if (!empty($roleDistribution)) {
    arsort($roleDistribution);
    $roleLeader = array_key_first($roleDistribution) ?? $roleLeader;
}

if ($latestJoinTs) {
    // Usamos DateTime para convertir el tiempo UTC del servidor a Costa Rica
    $dtJoin = new DateTime('@' . $latestJoinTs); 
    $dtJoin->setTimezone(new DateTimeZone('America/Costa_Rica'));
    $lastJoinText = $dtJoin->format('d/m/Y H:i');
} else {
    $lastJoinText = 'Sin registro';
}
$emptyFilterId = 'users-empty-filter';

$usersPerPage = 10;
$usersPage = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
$totalPages = max(1, (int) ceil(($activeCount ?: 0) / $usersPerPage));
if ($usersPage > $totalPages) {
    $usersPage = $totalPages;
}
$offset = max(0, ($usersPage - 1) * $usersPerPage);
$pagedUsers = $activeCount > 0 ? array_slice($activeUsers, $offset, $usersPerPage) : [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($appName); ?> | Usuarios</title>
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
                <p class="eyebrow">Directorio interno</p>
                <h1>Gestión de usuarios</h1>
                <p>Controla accesos, roles y altas recientes. Último registro: <?= e($lastJoinText); ?>.</p>
                <div class="inventory-stats">
                    <div>
                        <span>Usuarios activos</span>
                        <strong><?= number_format($activeCount); ?></strong>
                    </div>
                    <div>
                        <span>Roles disponibles</span>
                        <strong><?= number_format(count($roles)); ?></strong>
                    </div>
                    <div>
                        <span>Altas 30 días</span>
                        <strong><?= number_format($recentJoins); ?></strong>
                    </div>
                    <div>
                        <span>Usuarios inactivos</span>
                        <strong><?= number_format($inactiveCount); ?></strong>
                    </div>
                </div>
            </div>
            <div class="inventory-actions">
                <div>
                    <p>Rol más común</p>
                    <strong style="font-size: 1rem; line-height: 1.4;"><?= e($roleLeader); ?></strong>
                </div>
                <div>
                    <p>Promedio por rol</p>
                    <strong><?= count($roles) > 0 ? number_format($activeCount / count($roles), 1) : '0.0'; ?></strong>
                </div>
                <div>
                    <p>Total colaboradores</p>
                    <strong><?= number_format($totalUsers); ?></strong>
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
                        <h2>Usuarios activos</h2>
                        <p><?= number_format($activeCount); ?> perfiles con acceso vigente.</p>
                    </div>
                    <div class="filter-bar">
                        <input type="text" id="users-search" placeholder="Buscar por nombre, rol o apellido">
                    </div>
                </header>
                <div class="table-scroll">
                    <table class="inventory-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Apellido</th>
                                <th>Rol</th>
                                <th>Alta</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pagedUsers as $member): ?>
                                <?php
                                    $userId = (int) $member['IdUsuario'];
                                    $nombre = (string) $member['NombreUsuario'];
                                    $apellido = (string) $member['Apellido'];
                                    $rolNombre = (string) $member['NombreRol'];
                                    $fechaCreacion = (string) $member['FechaCreacion'];
                                    $dtRow = new DateTime($fechaCreacion, new DateTimeZone('UTC'));
                                    $dtRow->setTimezone(new DateTimeZone('America/Costa_Rica'));
                                    $fechaFormateada = $dtRow->format('d/m/Y H:i');
                                    $searchIndex = strtolower($nombre . ' ' . $apellido . ' ' . $rolNombre . ' #' . $userId);
                                    $editParams = $currentQuery;
                                    $editParams['edit'] = $userId;
                                    $editLink = $usersBaseUrl . ($editParams ? '?' . http_build_query($editParams) : '');
                                ?>
                                <tr data-user-row data-index="<?= e($searchIndex); ?>">
                                    <td>#<?= $userId; ?></td>
                                    <td><?= e($nombre); ?></td>
                                    <td><?= e($apellido); ?></td>
                                    <td><span class="pill"><?= e($rolNombre); ?></span></td>
                                    <td><?= e($fechaFormateada); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button type="button"
                                                class="ghost-btn"
                                                data-view-user
                                                data-id="<?= $userId; ?>"
                                                data-nombre="<?= e($nombre); ?>"
                                                data-apellido="<?= e($apellido); ?>"
                                                data-rol="<?= e($rolNombre); ?>"
                                                data-alta="<?= e($fechaFormateada); ?>">
                                                Ver
                                            </button>
                                            <a class="ghost-btn" href="<?= e($editLink); ?>">Editar</a>
                                            <button type="button"
                                                class="danger-btn"
                                                data-delete-user
                                                data-id="<?= $userId; ?>"
                                                data-nombre="<?= e($nombre . ' ' . $apellido); ?>">
                                                Eliminar
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($pagedUsers)): ?>
                                <tr>
                                    <td colspan="6" class="empty-state">No hay usuarios activos registrados.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <div id="<?= e($emptyFilterId); ?>" class="empty-state" hidden>
                        No se encontraron usuarios con el filtro aplicado.
                    </div>
                    <?php if ($totalPages > 1): ?>
                        <div class="table-pagination">
                            <?php if ($usersPage > 1): ?>
                                <a class="page-link" href="<?= e($buildUsersPageUrl($usersPage - 1)); ?>">&laquo;</a>
                            <?php endif; ?>
                            <?php for ($page = 1; $page <= $totalPages; $page++): ?>
                                <a class="page-link<?= $page === $usersPage ? ' active' : ''; ?>" href="<?= e($buildUsersPageUrl($page)); ?>">
                                    <?= $page; ?>
                                </a>
                            <?php endfor; ?>
                            <?php if ($usersPage < $totalPages): ?>
                                <a class="page-link" href="<?= e($buildUsersPageUrl($usersPage + 1)); ?>">&raquo;</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="inventory-form-card">
                <?php if ($formMode === 'update' && $editingUserId !== null): ?>
                    <div class="editing-banner">
                        Editando usuario #<?= (int) $editingUserId; ?>
                        <a href="<?= e($redirectUrl); ?>">Cancelar</a>
                    </div>
                <?php endif; ?>
                <h2><?= $formMode === 'update' ? 'Actualizar usuario' : 'Registrar usuario'; ?></h2>
                <p><?= $formMode === 'update' ? 'Ajusta la información necesaria y guarda los cambios.' : 'Completa el formulario para dar acceso a un nuevo colaborador.'; ?></p>
                <form method="post" class="inventory-form" action="<?= e($formActionUrl); ?>">
                    <input type="hidden" name="action" value="<?= $formMode === 'update' ? 'update' : 'create'; ?>">
                    <?php if ($formMode === 'update' && $editingUserId !== null): ?>
                        <input type="hidden" name="usuario_id" value="<?= (int) $editingUserId; ?>">
                    <?php endif; ?>
                    <div class="form-group">
                        <label for="nombre">Nombre</label>
                        <input type="text" id="nombre" name="nombre" maxlength="50" required value="<?= e($formValues['NombreUsuario']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="apellido">Apellido</label>
                        <input type="text" id="apellido" name="apellido" maxlength="50" required value="<?= e($formValues['Apellido']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="rol">Rol</label>
                        <select id="rol" name="rol" required>
                            <option value="" disabled <?= $formValues['IdRol'] === '' ? 'selected' : ''; ?>>Selecciona un rol</option>
                            <?php foreach ($roles as $rol): ?>
                                <option value="<?= (int) $rol['IdRol']; ?>" <?= (string) $rol['IdRol'] === (string) $formValues['IdRol'] ? 'selected' : ''; ?>>
                                    <?= e($rol['NombreRol']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="clave">Clave <?= $formMode === 'update' ? '(opcional)' : '(requerida)'; ?></label>
                        <input type="password" id="clave" name="clave" placeholder="<?= $formMode === 'update' ? 'Solo si deseas cambiarla' : 'Clave temporal'; ?>">
                    </div>
                    <button type="submit" class="primary-btn">
                        <?= $formMode === 'update' ? 'Guardar cambios' : 'Registrar usuario'; ?>
                    </button>
                </form>
            </section>
        </div>
    </main>

    <div class="modal" id="modal-user-view" aria-hidden="true">
        <div class="modal-dialog">
            <button type="button" class="modal-close" data-close-modal>&times;</button>
            <p class="eyebrow">Detalle del colaborador</p>
            <h3 id="view-user-name">-</h3>
            <div class="modal-details">
                <div>
                    <dt>Nombre</dt>
                    <dd id="view-user-first">-</dd>
                </div>
                <div>
                    <dt>Apellido</dt>
                    <dd id="view-user-last">-</dd>
                </div>
                <div>
                    <dt>Rol</dt>
                    <dd id="view-user-role">-</dd>
                </div>
                <div>
                    <dt>Alta</dt>
                    <dd id="view-user-date">-</dd>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="ghost-btn" data-close-modal>Cerrar</button>
            </div>
        </div>
    </div>

    <div class="modal" id="modal-user-delete" aria-hidden="true">
        <div class="modal-dialog">
            <button type="button" class="modal-close" data-close-modal>&times;</button>
            <p class="eyebrow">Eliminar usuario</p>
            <h3>Esta acción aplicará soft delete</h3>
            <p>El acceso quedará inactivo y se conservará el historial para auditoría.</p>
            <div class="modal-warning">
                <strong id="delete-user-label">Usuario</strong>
            </div>
            <div class="modal-actions">
                <button type="button" class="ghost-btn" data-close-modal>Cancelar</button>
                <button type="button" class="danger-btn" id="confirm-user-delete">Eliminar</button>
            </div>
        </div>
    </div>

    <form method="post" id="user-delete-form" action="<?= e($redirectUrl); ?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="usuario_id" id="delete-user-id">
    </form>

    <script>
        (function () {
            const body = document.body;
            const viewModal = document.getElementById('modal-user-view');
            const deleteModal = document.getElementById('modal-user-delete');
            const deleteForm = document.getElementById('user-delete-form');
            const deleteInput = document.getElementById('delete-user-id');
            const deleteLabel = document.getElementById('delete-user-label');
            const confirmDelete = document.getElementById('confirm-user-delete');
            const rows = Array.from(document.querySelectorAll('[data-user-row]'));
            const searchInput = document.getElementById('users-search');
            const emptyFilter = document.getElementById('<?= e($emptyFilterId); ?>');
            const viewFields = {
                title: document.getElementById('view-user-name'),
                first: document.getElementById('view-user-first'),
                last: document.getElementById('view-user-last'),
                role: document.getElementById('view-user-role'),
                date: document.getElementById('view-user-date'),
            };

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

            document.querySelectorAll('[data-view-user]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const nombre = btn.dataset.nombre || '-';
                    const apellido = btn.dataset.apellido || '-';
                    const rol = btn.dataset.rol || '-';
                    const alta = btn.dataset.alta || '-';
                    viewFields.title.textContent = nombre + ' ' + apellido;
                    viewFields.first.textContent = nombre;
                    viewFields.last.textContent = apellido;
                    viewFields.role.textContent = rol;
                    viewFields.date.textContent = alta;
                    openModal(viewModal);
                });
            });

            document.querySelectorAll('[data-delete-user]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const userId = btn.dataset.id;
                    deleteInput.value = userId || '';
                    deleteLabel.textContent = btn.dataset.nombre || 'Usuario';
                    openModal(deleteModal);
                });
            });

            confirmDelete?.addEventListener('click', () => {
                if (deleteInput.value) {
                    deleteForm.submit();
                }
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
        })();
    </script>
    <?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
