<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/*
 * CONFIGURACIÓN
 * -------------
 * Tiempo máximo de inactividad (en segundos)
 * 900 = 15 minutos, 1800 = 30 minutos, etc.
 */
const IDLE_TIMEOUT = 900; // 15 minutos

/*
 * URL del login.
 * Si tu sistema está en http://IP/ea y el login en /ea/login.php,
 * esta ruta es correcta. Si lo tenés en otro lado, AJUSTAR.
 */
const LOGIN_URL = '/ea/login.php';

/* ================= CSRF ================= */

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_input(): string {
    $t = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="_csrf" value="'.$t.'">';
}

function csrf_verify(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $sent  = $_POST['_csrf'] ?? '';
        $saved = $_SESSION['csrf_token'] ?? '';
        if (!is_string($sent) || !is_string($saved) || $sent === '' || !hash_equals($saved, $sent)) {
            http_response_code(400);
            exit('CSRF token inválido.');
        }
    }
}

/* ============== SESIÓN / USUARIO ============== */

function current_user(): ?array {
    return (isset($_SESSION['user']) && is_array($_SESSION['user']))
        ? $_SESSION['user']
        : null;
}

/**
 * Marca actividad actual (último movimiento) para controlar inactividad.
 */
function auth_touch_activity(): void {
    $_SESSION['last_activity'] = time();
}

/**
 * Verifica si la sesión está expirada por inactividad.
 * Si se excede IDLE_TIMEOUT:
 *   - destruye la sesión
 *   - redirige al login con un flag de timeout
 */
function auth_check_idle_timeout(): void {
    // Si no hay usuario logueado, no hacemos control de timeout
    if (empty($_SESSION['user'])) {
        return;
    }

    $now = time();

    if (!isset($_SESSION['last_activity'])) {
        auth_touch_activity();
        return;
    }

    $idle = $now - (int)($_SESSION['last_activity']);

    if ($idle > IDLE_TIMEOUT) {
        // Sesión expirada por inactividad
        session_unset();
        session_destroy();
        session_start(); // nueva sesión vacía para poder setear mensajes

        $_SESSION['timeout'] = true;

        $sep = (strpos(LOGIN_URL, '?') === false) ? '?' : '&';
        header('Location: ' . LOGIN_URL . $sep . 'timeout=1');
        exit;
    }

    // Aún dentro del tiempo → actualizamos marca de actividad
    auth_touch_activity();
}

/**
 * Fuerza que el usuario esté logueado.
 * Si no lo está, lo manda al login.
 */
function require_login(): void {
    // Primero controlar timeout por inactividad
    auth_check_idle_timeout();

    if (empty($_SESSION['user']) || !is_array($_SESSION['user'])) {
        $next = $_SERVER['REQUEST_URI'] ?? '';
        $sep  = (strpos(LOGIN_URL, '?') === false) ? '?' : '&';
        $url  = LOGIN_URL . $sep . 'next=' . urlencode($next);

        header('Location: ' . $url);
        exit;
    }
}

/**
 * Helper simple por si lo querés usar en otros lados.
 */
function is_logged_in(): bool {
    return !empty($_SESSION['user']) && is_array($_SESSION['user']);
}

