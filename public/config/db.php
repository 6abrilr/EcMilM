<?php
// config/db.php
declare(strict_types=1);

$dsn  = 'mysql:host=127.0.0.1;dbname=inspecciones;charset=utf8mb4';
$user = 'root';
$pass = ''; // cambiá si tu MariaDB tiene clave

$pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
]);
