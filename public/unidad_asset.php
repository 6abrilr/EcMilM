<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php'; // $pdo (PDO)

$id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$tipo = strtolower((string)($_GET['tipo'] ?? 'logo')); // logo | icono | fondo

if ($id <= 0 || !in_array($tipo, ['logo','icono','fondo'], true)) {
  http_response_code(400);
  exit('Bad request');
}

if ($tipo === 'icono') {
  $colBlob = 'Icono';
  $colMime = 'IconoMime';
} elseif ($tipo === 'fondo') {
  $colBlob = 'Fondo';
  $colMime = 'FondoMime';
} else {
  $colBlob = 'Logo';
  $colMime = 'LogoMime';
}

$sql = "SELECT `$colBlob` AS bin, `$colMime` AS mime
        FROM `unidades`
        WHERE `idUnidad` = :id
        LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row || empty($row['bin']) || empty($row['mime'])) {
  http_response_code(404);
  exit('Not found');
}

header('Content-Type: ' . $row['mime']);
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
echo $row['bin'];
exit;
