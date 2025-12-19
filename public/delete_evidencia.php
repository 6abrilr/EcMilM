<?php
// public/delete_evidencia.php
declare(strict_types=1);

require_once __DIR__ . '/../auth/bootstrap.php';
require_login();
require_once __DIR__ . '/../config/db.php';

function starts_with(string $h, string $n): bool { return substr($h, 0, strlen($n)) === $n; }

if (!function_exists('user_display_name')) {
  function user_display_name(): string {
    $u = $_SESSION['user'] ?? [];
    if (isset($u['grado'], $u['arma'], $u['nombre_completo'])) {
      return trim($u['grado'].' '.$u['arma'].' '.$u['nombre_completo']);
    }
    if (isset($u['display_name']))    return trim((string)$u['display_name']);
    if (isset($u['nombre_completo'])) return trim((string)$u['nombre_completo']);
    if (isset($u['username']))        return strtoupper((string)$u['username']);
    return 'Usuario';
  }
}

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

function json_out(array $data, int $code = 200): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}

function is_ajax_request(): bool {
  $xrw = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
  if (strcasecmp($xrw, 'XMLHttpRequest') === 0) return true;

  $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
  if (stripos($accept, 'application/json') !== false) return true;

  return (($_GET['ajax'] ?? '') === '1');
}

function build_qs_back(string $file_rel, int $sheet, int $perPage, int $page, string $showcolor, string $fmt, string $area): string {
  $qs = 'p='  . rawurlencode($file_rel)
      . '&s=' . (int)$sheet
      . '&pp='.(int)$perPage
      . '&page='.(int)$page;

  if ($showcolor === '1') $qs .= '&showcolor=1';
  if ($fmt !== '')       $qs .= '&fmt=' . rawurlencode($fmt);
  if ($area !== '')      $qs .= '&area=' . rawurlencode($area);

  return $qs;
}

/* ==== Inputs ==== */
$file_rel  = $_GET['p']   ?? '';
$row_idx   = isset($_GET['row'])  ? (int)$_GET['row']  : 0;
$file_idx  = isset($_GET['file']) ? (int)$_GET['file'] : -1;

$sheet     = isset($_GET['s']) ? (int)$_GET['s'] : 0;
$showcolor = isset($_GET['showcolor']) ? (string)$_GET['showcolor'] : '0';
$perPage   = isset($_GET['pp']) ? (int)$_GET['pp'] : 20;
$page      = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$fmt       = (string)($_GET['fmt']  ?? '');
$area      = (string)($_GET['area'] ?? '');

$isAjax = is_ajax_request();

/* ==== Validaciones básicas ==== */
if ($file_rel === '' || $row_idx <= 0 || $file_idx < 0) {
  if ($isAjax) json_out(['ok'=>false,'msg'=>'Parámetros inválidos'], 400);
  http_response_code(400); echo "Parámetros inválidos"; exit;
}

/* ==== PDO fallback ==== */
if (!isset($pdo) || !$pdo instanceof PDO) {
  $pdo = getDB();
}

/* ==== Validar que file_rel esté dentro de carpetas permitidas ==== */
$projectBase = realpath(__DIR__ . '/..');
if (!$projectBase) {
  if ($isAjax) json_out(['ok'=>false,'msg'=>'No se pudo resolver base del proyecto'], 500);
  http_response_code(500); echo "No se pudo resolver base del proyecto"; exit;
}

$absFile = realpath($projectBase . '/' . $file_rel);
if (!$absFile || !is_file($absFile)) {
  if ($isAjax) json_out(['ok'=>false,'msg'=>'Archivo de origen inválido'], 400);
  http_response_code(400); echo "Archivo de origen inválido"; exit;
}

$roots = [
  'listas_control'          => realpath($projectBase.'/storage/listas_control'),
  'ultima_inspeccion'       => realpath($projectBase.'/storage/ultima_inspeccion'),
  'visitas_de_estado_mayor' => realpath($projectBase.'/storage/visitas_de_estado_mayor'),
];

$inScope = null;
foreach ($roots as $slug => $root) {
  if ($root && starts_with($absFile, $root)) { $inScope = $slug; break; }
}
if (!$inScope) {
  if ($isAjax) json_out(['ok'=>false,'msg'=>'Ruta fuera de las carpetas permitidas'], 400);
  http_response_code(400); echo "Ruta fuera de las carpetas permitidas"; exit;
}

/* ==== Buscar evidencia actual ==== */
$st = $pdo->prepare("SELECT evidencia_path FROM checklist WHERE file_rel=? AND row_idx=?");
$st->execute([$file_rel, $row_idx]);
$evRel = $st->fetchColumn();

$files = evidencia_to_array($evRel);

$qsBack = build_qs_back($file_rel, $sheet, $perPage, $page, $showcolor, $fmt, $area);

/* ==== Si no existe ese índice, salir sin romper ==== */
if (!isset($files[$file_idx])) {
  if ($isAjax) json_out(['ok'=>true,'files'=>$files,'qs_back'=>$qsBack,'msg'=>'Índice no existe (ya eliminado)']);
  header('Location: ver_tabla.php?' . $qsBack);
  exit;
}

/* ==== Borrar archivo físico con validación fuerte (solo evidencias) ==== */
$relPath = (string)$files[$file_idx];

// Solo permitimos borrar dentro de storage/evidencias/
$relNorm = ltrim(str_replace('\\','/',$relPath), '/');
if (!starts_with($relNorm, 'storage/evidencias/')) {
  // Quitamos de BD pero NO tocamos FS
  unset($files[$file_idx]);
  $files = array_values($files);
} else {
  $evidRoot  = realpath($projectBase . '/storage/evidencias');
  $candidate = realpath($projectBase . '/' . $relNorm); // puede ser false si ya no está

  if ($evidRoot && $candidate && starts_with($candidate, $evidRoot) && is_file($candidate)) {
    @unlink($candidate);
  }

  unset($files[$file_idx]);
  $files = array_values($files);
}

/* ==== Guardar lista actualizada (o NULL si vacío) + updated_by ==== */
$newEv = empty($files) ? null : json_encode(array_values(array_unique($files)), JSON_UNESCAPED_SLASHES);

$updatedBy = user_display_name();
$up = $pdo->prepare("UPDATE checklist SET evidencia_path = ?, updated_at = NOW(), updated_by = ? WHERE file_rel=? AND row_idx=?");
$up->execute([$newEv, $updatedBy, $file_rel, $row_idx]);

/* ==== Responder ==== */
if ($isAjax) {
  json_out([
    'ok' => true,
    'files' => $files,
    'updated_by' => $updatedBy,
    'qs_back' => $qsBack
  ]);
}

/* ==== Volver a la tabla ==== */
header('Location: ver_tabla.php?' . $qsBack);
exit;
