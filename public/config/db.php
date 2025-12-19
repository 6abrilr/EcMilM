<?php
// config/db.php
declare(strict_types=1);

/**
 * db.php (modo PRO)
 * - Soporta variables de entorno: DB_HOST, DB_NAME, DB_USER, DB_PASS
 * - Mantiene compatibilidad: expone $pdo global
 * - Evita repetir conexiones con static singleton
 *
 * IMPORTANTE:
 * - NO hardcodees la contraseña en el repo público.
 * - Seteá DB_PASS en el servidor (recomendado).
 */

function getDB(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    // Defaults razonables
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $name = getenv('DB_NAME') ?: 'inspecciones';
    $user = getenv('DB_USER') ?: 'root';

    // DB_PASS: si no existe la env var, queda '' (compatibilidad).
    // Recomendado: SETEAR DB_PASS en el servidor.
    $pass = getenv('DB_PASS');
    if ($pass === false) $pass = '';

    $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        // PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '-03:00'",
    ]);

    return $pdo;
}

// Compatibilidad con tu código actual (usa $pdo directamente)
$pdo = getDB();
