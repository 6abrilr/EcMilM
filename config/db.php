<?php
declare(strict_types=1);

$envOr = static function (string $key, string $default): string {
  $value = getenv($key);
  if ($value === false) return $default;
  $value = trim((string)$value);
  return $value === '' ? $default : $value;
};

$cfg = [
  'host' => $envOr('EA_DB_HOST', '127.0.0.1'),
  'port' => (int)$envOr('EA_DB_PORT', '3306'),
  'db'   => $envOr('EA_DB_NAME', 'unidad'),
  'user' => $envOr('EA_DB_USER', 'root'),
  'pass' => $envOr('EA_DB_PASS', ''),
];

$dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['db']};charset=utf8mb4";
$pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES => false,
]);
