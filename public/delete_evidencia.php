<?php
// public/delete_evidencia.php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

function evidencia_to_array($ev): array {
  $files = [];
  if ($ev === null || $ev === '') return $files;

  $decoded = json_decode((string)$ev, true);
  if (is_array($decoded)) {
    foreach ($decoded as $p) {
      $p = trim((string)$p);
      if ($p !== '') $files[] = $p;
    }
    return $files;
  }

  $ev = (string)$ev;
  $sep = null;
  if (strpos($ev,'|') !== false)      $sep = '|';
  elseif (strpos($ev,';') !== false) $sep = ';';
  elseif (strpos($ev,',') !== false) $sep = ',';

  if ($sep !== null) {
    foreach (explode($sep,$ev) as $p) {
      $p = trim($p);
      if ($p !== '') $files[] = $p;
    }
  } else {
    $p = trim($ev);
    if ($p !== '') $files[] = $p;
  }

  return $files;
}

/* ==== Inputs ==== */
$file_rel  = $_GET['p']   ?? '';
$row_idx   = isset($_GET['row'])  ? (int)$_GET['row']  : 0;
$file_idx  = isset($_GET['file']) ? (int)$_GET['file'] : -1;

$sheet     = isset($_GET['s']) ? (int)$_GET['s'] : 0;
$showcolor = isset($_GET['showcolor']) ? (string)$_GET['showcolor'] : '0';
$perPage   = isset($_GET['pp']) ? (int)$_GET['pp'] : 20;
$page      = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$fmt       = $_GET['fmt']  ?? '';
$area      = $_GET['area'] ?? '';

if ($file_rel === '' || $row_idx <= 0 || $file_idx < 0) {
  http_response_code(400);
  echo "Parámetros inválidos";
  exit;
}

/* ==== Buscar evidencia actual ==== */
$st = $pdo->prepare("SELECT evidencia_path FROM checklist WHERE file_rel=? AND row_idx=?");
$st->execute([$file_rel, $row_idx]);
$evRel = $st->fetchColumn();

$files = evidencia_to_array($evRel);

/* ==== Si existe ese índice, borrar archivo físico y sacarlo del array ==== */
if (isset($files[$file_idx])) {
  $projectBase = realpath(__DIR__ . '/..') ?: (__DIR__ . '/..');

  $relPath = $files[$file_idx];
  $evAbs   = $projectBase . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relPath);

  if (is_file($evAbs)) {
    @unlink($evAbs);
  }

  unset($files[$file_idx]);
  $files = array_values($files); // reindexar
}

/* ==== Guardar lista actualizada (o NULL si vacío) ==== */
if (empty($files)) {
  $newEv = null;
} else {
  $files = array_values(array_unique($files));
  $newEv = json_encode($files, JSON_UNESCAPED_SLASHES);
}

$up = $pdo->prepare("UPDATE checklist SET evidencia_path = ?, updated_at = NOW() WHERE file_rel=? AND row_idx=?");
$up->execute([$newEv, $file_rel, $row_idx]);

/* ==== Volver a la tabla conservando parámetros ==== */
$qs = 'p='  . rawurlencode($file_rel)
    . '&s=' . (int)$sheet
    . '&pp='.(int)$perPage
    . '&page='.(int)$page;

if ($showcolor === '1') $qs .= '&showcolor=1';
if ($fmt !== '')       $qs .= '&fmt=' . rawurlencode($fmt);
if ($area !== '')      $qs .= '&area=' . rawurlencode($area);

header('Location: ver_tabla.php?' . $qs);
exit;
