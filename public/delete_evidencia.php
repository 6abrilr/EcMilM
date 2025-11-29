<?php
// public/delete_evidencia.php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';

/* ==== Inputs ==== */
$file_rel  = $_GET['p']   ?? '';                 // ruta relativa del XLSX (misma que guarda checklist.file_rel)
$row_idx   = isset($_GET['row']) ? (int)$_GET['row'] : 0;
$sheet     = isset($_GET['s']) ? (int)$_GET['s'] : 0;
$showcolor = isset($_GET['showcolor']) ? (string)$_GET['showcolor'] : '0';

if ($file_rel === '' || $row_idx <= 0) {
  http_response_code(400);
  echo "Parámetros inválidos"; exit;
}

/* ==== Buscar evidencia actual ==== */
$st = $pdo->prepare("SELECT evidencia_path FROM checklist WHERE file_rel=? AND row_idx=?");
$st->execute([$file_rel, $row_idx]);
$evRel = $st->fetchColumn();

/* ==== Si existe archivo, borrarlo del disco ==== */
if ($evRel) {
  $projectBase = realpath(__DIR__ . '/..') ?: (__DIR__ . '/..');

  // Checklist guarda rutas tipo "storage/evidencias/AAAA/MM/archivo.ext" (con /).
  // Construimos absoluta compatible Windows/Linux:
  $evAbs = $projectBase . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $evRel);

  if (is_file($evAbs)) { @unlink($evAbs); }
}

/* ==== Limpiar la referencia en la base ==== */
$up = $pdo->prepare("UPDATE checklist SET evidencia_path=NULL, updated_at=NOW() WHERE file_rel=? AND row_idx=?");
$up->execute([$file_rel, $row_idx]);

/* ==== Volver a la tabla conservando parámetros ==== */
$qs = 'p=' . rawurlencode($file_rel) . '&s=' . (int)$sheet . '&showcolor=' . urlencode($showcolor);
header('Location: ver_tabla.php?' . $qs);
exit;
