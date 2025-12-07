<?php

declare(strict_types=1);

$config = require __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$appName = $config['app']['name'] ?? 'Sistema de Ventas';
$baseUrl = rtrim($config['app']['base_url'] ?? '/', '/');
$publicBase = rtrim($config['app']['public_url'] ?? $baseUrl, '/');
$asset = static fn(string $path): string => 'assets/' . ltrim($path, '/');

$flashError = $_SESSION['flash_error'] ?? null;
$flashSuccess = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_error'], $_SESSION['flash_success']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($appName); ?> | Acceso Seguro</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Playfair+Display:wght@600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars($asset('css/login.css')); ?>?v=<?= urlencode((string) time()); ?>">
</head>
<body>
    <div class="login-shell">
        <section class="brand-panel">
            <div>
                <p class="brand-motto">Precisi&oacute;n · Transparencia · Confianza</p>
                <h1 class="brand-title"><?= htmlspecialchars($appName); ?></h1>
                <p class="brand-subtitle">
                    Administra inventarios, ventas y equipos con una plataforma pensada para negocios que exigen excelencia.
                    Mant&eacute;n el pulso del negocio con dashboards elegantes y reportes trazables.
                </p>
            </div>
            <div>
                <p class="brand-subtitle">
                    ¿Necesitas acceso para tu equipo? Solic&iacute;talo con el administrador del sistema.
                </p>
            </div>
        </section>
        <section class="form-panel">
            <div class="form-header">
                <span>Ingreso Corporativo</span>
                <h1>Bienvenido de nuevo</h1>
                <p>Autent&iacute;cate para continuar con tus operaciones.</p>
            </div>
            <?php if ($flashError): ?>
                <div class="alert alert-error">
                    <?= htmlspecialchars($flashError); ?>
                </div>
            <?php elseif ($flashSuccess): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($flashSuccess); ?>
                </div>
            <?php endif; ?>
            <form method="post" action="<?= htmlspecialchars($publicBase); ?>/auth/login-handler.php">
                <div class="field-group">
                    <label for="usuario">Usuario</label>
                    <div class="input-shell">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 12c2.67 0 4.8-2.13 4.8-4.8S14.67 2.4 12 2.4 7.2 4.53 7.2 7.2 9.33 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2V19.2c0-3.2-6.4-4.8-9.6-4.8z" />
                        </svg>
                        <input type="text" id="usuario" name="usuario" placeholder="Nombre de usuario" required autocomplete="username">
                    </div>
                </div>
                <div class="field-group">
                    <label for="clave">Clave</label>
                    <div class="input-shell">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M17 9h-1V7A4 4 0 0 0 8 7v2H7a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-7a2 2 0 0 0-2-2zm-6 8v-3a1 1 0 1 1 2 0v3a1 1 0 0 1-2 0zm3-8H10V7a2 2 0 0 1 4 0z" />
                        </svg>
                        <input type="password" id="clave" name="clave" placeholder="••••••••" required autocomplete="current-password">
                    </div>
                </div>
               
                <button type="submit">Iniciar sesi&oacute;n</button>
            </form>
            <div class="security-strip">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M12 1.2 2.4 5.2v6.2c0 6 4.1 11.6 9.6 13.4 5.5-1.8 9.6-7.4 9.6-13.4V5.2L12 1.2zm0 18a1.4 1.4 0 1 1 0-2.8 1.4 1.4 0 0 1 0 2.8zm1.4-5.6h-2.8V7.8h2.8z" />
                </svg>
                Conexi&oacute;n protegida · Registro de auditor&iacute;a activo
            </div>
        </section>
    </div>
</body>
</html>
