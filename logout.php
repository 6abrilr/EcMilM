<?php
declare(strict_types=1);

// Siempre iniciar sesión antes de tocar $_SESSION
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// limpiar datos de sesión
$_SESSION = [];

// destruir cookie de sesión si existe
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'] ?? '/',
        $params['domain'] ?? '',
        $params['secure'] ?? false,
        $params['httponly'] ?? true
    );
}

// destruir la sesión
session_destroy();

// base de la app
$base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
if ($base === '/') $base = '';
if ($base === '\\') $base = '';

header('Location: ' . $base . '/login.php?out=1');
exit;
