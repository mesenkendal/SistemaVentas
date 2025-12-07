<?php

declare(strict_types=1);

if (!function_exists('reset_active_session')) {
    function reset_active_session(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }
            session_destroy();
        }
    }
}

if (!function_exists('enforce_session_timeout')) {
    function enforce_session_timeout(string $publicBase, int $maxInactiveSeconds = 3600): void
    {
        if (empty($_SESSION['usuario'])) {
            return;
        }

        $now = time();
        $lastActivity = isset($_SESSION['last_activity']) ? (int) $_SESSION['last_activity'] : $now;
        if (($now - $lastActivity) > $maxInactiveSeconds) {
            reset_active_session();
            session_start();
            $_SESSION['flash_error'] = 'Tu sesión expiró por inactividad. Vuelve a iniciar sesión.';
            header('Location: ' . $publicBase . '/login.php');
            exit;
        }

        $_SESSION['last_activity'] = $now;
    }
}
