<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/bootstrap.php';
require_login();

require_once __DIR__ . '/../config/db.php'; // $pdo (PDO)

$id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$tipo = strtolower((string)($_GET['tipo'] ?? 'logo')); // logo | escudo | icono | fondo

if ($id <= 0 || !in_array($tipo, ['logo','escudo','icono','fondo'], true)) {
  http_response_code(400);
  exit('Bad request');
}

$pathCol = match ($tipo) {
  'fondo' => 'bg_path',
  'escudo', 'icono' => 'escudo_path',
  default => 'logo_path',
};

$sql = "SELECT `$pathCol` AS rel_path
        FROM unidades
        WHERE id = :id AND activa = 1
        LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row || empty($row['rel_path'])) {
  http_response_code(404);
  exit('Not found');
}

$rel = str_replace('\\', '/', (string)$row['rel_path']);
$rel = ltrim($rel, '/');
if ($rel === '' || str_contains($rel, '..') || preg_match('#^[a-z]+:#i', $rel)) {
  http_response_code(400);
  exit('Bad path');
}

$root = realpath(__DIR__ . '/..');
$file = realpath($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel));
if ($root === false || $file === false || !str_starts_with($file, $root . DIRECTORY_SEPARATOR) || !is_file($file)) {
  http_response_code(404);
  exit('Not found');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = (string)($finfo->file($file) ?: 'application/octet-stream');
if (!in_array($mime, ['image/png', 'image/jpeg', 'image/webp', 'image/gif', 'image/x-icon'], true)) {
  http_response_code(415);
  exit('Unsupported media type');
}

header('Content-Type: ' . $mime);
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');
readfile($file);
exit;
