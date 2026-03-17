<?php
/**
 * ea/public/s3/operaciones/operaciones_adiestramiento.php
 * Panel PAFB (Adiestramiento físico-militar) — ECMILM
 *
 * ✅ REGLA ABSOLUTA (FS):
 *   TODO lo referido a PAFB se guarda en:
 *   <ROOT>/storage/unidades/ecmilm/OPERACIONES/PAFB/
 *
 * ✅ Estructura:
 *   - Documentacion fija: /PAFB/DOCUMENTACION/<docKey>/ + _meta.json
 *   - PAFB por año: /PAFB/<YEAR>/<cardId>/ + /PAFB/<YEAR>/_meta.json
 *
 * ✅ Descarga/abrir archivos:
 *   - Se hace por este mismo PHP (download endpoint) para NO depender de exponer /storage por URL.
 *
 * ✅ FIXES CLAVE vs tu versión:
 *   1) require_once corregidos (3 niveles arriba).
 *   2) Rutas FS NO dependen de realpath/dirname; se usa ruta canónica absoluta pedida.
 *   3) "Abrir" usa endpoint seguro (?download=1...) así funciona aunque storage no esté publicado.
 *   4) Upload año también informa errores INI_SIZE/FORM_SIZE.
 */

declare(strict_types=1);

$OFFLINE_MODE = false;

/* ===== Boot / Auth / DB (archivo está en /ea/public/s3/operaciones) ===== */
require_once __DIR__ . '/../../auth/bootstrap.php';
if (!$OFFLINE_MODE) require_login();
require_once __DIR__ . '/../../config/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function starts_with(string $h, string $n): bool { return substr($h, 0, strlen($n)) === $n; }
function normalize_rel_path(string $path): ?string {
  $path = trim(str_replace('\\', '/', $path));
  if ($path === '' || $path === '/') return '';
  $parts = array_values(array_filter(explode('/', $path), static fn($p) => $p !== '' && $p !== '.'));
  $safe = [];
  foreach ($parts as $part) {
    if ($part === '..') return null;
    $safe[] = $part;
  }
  return implode('/', $safe);
}
function format_dt_local(?int $ts): string {
  if (!$ts || $ts <= 0) return '—';
  return date('d/m/Y H:i', $ts);
}
function human_size(int $bytes): string {
  if ($bytes < 1024) return $bytes . ' B';
  if ($bytes < 1024 * 1024) return number_format($bytes / 1024, 1) . ' KB';
  if ($bytes < 1024 * 1024 * 1024) return number_format($bytes / 1024 / 1024, 2) . ' MB';
  return number_format($bytes / 1024 / 1024 / 1024, 2) . ' GB';
}
function scan_shared_dir(string $baseAbs, string $relative): array {
  $baseReal = realpath($baseAbs);
  if ($baseReal === false) {
    return ['ok' => false, 'error' => 'No existe la carpeta compartida.', 'current' => '', 'entries' => []];
  }

  $relative = normalize_rel_path($relative);
  if ($relative === null) {
    return ['ok' => false, 'error' => 'Ruta inválida.', 'current' => '', 'entries' => []];
  }

  $targetAbs = $relative === '' ? $baseReal : ($baseReal . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));
  $targetReal = realpath($targetAbs);
  if ($targetReal === false || !is_dir($targetReal) || !starts_with($targetReal, $baseReal)) {
    return ['ok' => false, 'error' => 'La carpeta solicitada no existe.', 'current' => $relative, 'entries' => []];
  }

  $items = @scandir($targetReal);
  if (!is_array($items)) {
    return ['ok' => false, 'error' => 'No se pudo leer la carpeta compartida.', 'current' => $relative, 'entries' => []];
  }

  $entries = [];
  foreach ($items as $item) {
    if ($item === '.' || $item === '..') continue;
    $abs = $targetReal . DIRECTORY_SEPARATOR . $item;
    $isDir = is_dir($abs);
    $childRel = $relative === '' ? $item : ($relative . '/' . $item);
    $entries[] = [
      'name' => $item,
      'rel' => $childRel,
      'is_dir' => $isDir,
      'size' => $isDir ? null : (int)@filesize($abs),
      'mtime' => (int)@filemtime($abs),
      'ext' => $isDir ? '' : strtolower((string)pathinfo($item, PATHINFO_EXTENSION)),
    ];
  }

  usort($entries, static function (array $a, array $b): int {
    if ($a['is_dir'] !== $b['is_dir']) return $a['is_dir'] ? -1 : 1;
    return strcasecmp((string)$a['name'], (string)$b['name']);
  });

  return ['ok' => true, 'error' => '', 'current' => $relative, 'entries' => $entries];
}

/* ==========================================================
   ✅ BASE WEB robusta (/ea)
   - Script: /ea/public/s3/operaciones/operaciones_adiestramiento.php
   - APP_BASE: /ea
   ========================================================== */
$SCRIPT_NAME = (string)($_SERVER['SCRIPT_NAME'] ?? '');
$APP_BASE = preg_replace('#/public(/.*)?$#', '', $SCRIPT_NAME);
$APP_BASE = rtrim((string)$APP_BASE, '/');
if ($APP_BASE === '') $APP_BASE = ''; // por si corre en raíz

$ASSETS_URL = $APP_BASE . '/assets';
$IMG_BG     = $ASSETS_URL . '/img/fondo.png';
$ESCUDO     = $ASSETS_URL . '/img/ecmilm.png';

/* ==========================================================
   ✅ STORAGE CANÓNICO ABSOLUTO (FS)
   TODO PAFB va acá:
   <ROOT>/storage/unidades/ecmilm/OPERACIONES/PAFB/
   ========================================================== */
$ROOT_FS = realpath(__DIR__ . '/../../');
if ($ROOT_FS === false) {
  http_response_code(500);
  exit('No se pudo resolver la raiz del proyecto.');
}

$PAFB_ROOT_ABS = $ROOT_FS . '/storage/unidades/ecmilm/OPERACIONES/PAFB';

$DOCS_ABS_DIR  = $PAFB_ROOT_ABS . '/DOCUMENTACION'; // /DOCUMENTACION/<docKey>/
$PAFB_BASE_ABS = $PAFB_ROOT_ABS;            // /<YEAR>/<cardId>/

/* ==========================
   Config DOCS + Cards
   ========================== */
$DOCS = [
  'directiva' => [
    'title' => 'Directiva Educación Física Militar',
    'sub'   => '(Régimen Funcional y de Evaluación)',
    'icon'  => '📜',
  ],
  'tablas' => [
    'title' => 'Tablas de exigencias',
    'sub'   => 'Tablas de evaluación y puntajes PAFB.',
    'icon'  => '📑',
  ],
  'plan' => [
    'title' => 'Plan de entrenamiento',
    'sub'   => '(Plan de entrenamiento)',
    'icon'  => '🏃‍♂️',
  ],
];

$FIXED_ORDER = ['diag1','c1','diag2','c2'];
$BASE_CARDS = [
  'diag1' => ['title'=>'Diagnóstico 1',         'sub'=>'Evaluación / diagnóstico previo.',       'icon'=>'🧪'],
  'c1'    => ['title'=>'PAFB 1ra comprobación', 'sub'=>'Resultados oficiales del 1er período.',  'icon'=>'🏃‍♂️'],
  'diag2' => ['title'=>'Diagnóstico 2',         'sub'=>'Evaluación / diagnóstico previo a 2da.', 'icon'=>'🧪'],
  'c2'    => ['title'=>'PAFB 2da comprobación', 'sub'=>'Resultados oficiales del 2do período.',  'icon'=>'🏁'],
];

/** Límite en código (por archivo). */
$MAX_DOC_MB    = 80;
$MAX_DOC_BYTES = $MAX_DOC_MB * 1024 * 1024;

/* ===== server limits (para avisos) ===== */
$ini_upload = (string)ini_get('upload_max_filesize');
$ini_post   = (string)ini_get('post_max_size');

/* ==========================
   Helpers FS / JSON / SIZES
   ========================== */
function ensure_dir(string $absDir): bool {
  if (is_dir($absDir)) return true;
  return @mkdir($absDir, 0775, true);
}
function read_meta_docs(string $metaAbs): array {
  if (!is_file($metaAbs)) return ['uploads' => []];
  $raw = @file_get_contents($metaAbs);
  if ($raw === false) return ['uploads' => []];
  $j = json_decode($raw, true);
  if (!is_array($j)) return ['uploads' => []];
  if (!isset($j['uploads']) || !is_array($j['uploads'])) $j['uploads'] = [];
  return $j;
}
function read_meta_year(string $metaAbs): array {
  if (!is_file($metaAbs)) return [];
  $raw = @file_get_contents($metaAbs);
  if ($raw === false) return [];
  $j = json_decode($raw, true);
  return is_array($j) ? $j : [];
}
function write_meta(string $metaAbs, array $data): bool {
  $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
  if ($json === false) return false;
  return (@file_put_contents($metaAbs, $json, LOCK_EX) !== false);
}
function parse_ini_size_to_bytes(string $v): int {
  $v = trim($v);
  if ($v === '') return 0;
  $last = strtolower(substr($v, -1));
  $num = (float)$v;
  switch ($last) {
    case 'g': return (int)($num * 1024 * 1024 * 1024);
    case 'm': return (int)($num * 1024 * 1024);
    case 'k': return (int)($num * 1024);
    default:  return (int)$num;
  }
}
function human_mb(int $bytes): string {
  return number_format($bytes / 1024 / 1024, 2) . ' MB';
}
function ascii_slug(string $txt): string {
  $txt = trim($txt);
  if ($txt === '') return 'archivo';
  $map = [
    'Á'=>'A','À'=>'A','Ä'=>'A','Â'=>'A','á'=>'a','à'=>'a','ä'=>'a','â'=>'a',
    'É'=>'E','È'=>'E','Ë'=>'E','Ê'=>'E','é'=>'e','è'=>'e','ë'=>'e','ê'=>'e',
    'Í'=>'I','Ì'=>'I','Ï'=>'I','Î'=>'I','í'=>'i','ì'=>'i','ï'=>'i','î'=>'i',
    'Ó'=>'O','Ò'=>'O','Ö'=>'O','Ô'=>'O','ó'=>'o','ò'=>'o','ö'=>'o','ô'=>'o',
    'Ú'=>'U','Ù'=>'U','Ü'=>'U','Û'=>'U','ú'=>'u','ù'=>'u','ü'=>'u','û'=>'u',
    'Ñ'=>'N','ñ'=>'n'
  ];
  $txt = strtr($txt, $map);
  $txt = preg_replace('/[^A-Za-z0-9._ -]+/', '', $txt) ?? $txt;
  $txt = preg_replace('/\s+/', ' ', $txt) ?? $txt;
  $txt = trim($txt, " .-_");
  $txt = str_replace(' ', '_', $txt);
  return $txt !== '' ? $txt : 'archivo';
}
function unique_filename_in_dir(string $dirAbs, string $baseName, string $ext): string {
  $baseName = ascii_slug($baseName);
  $ext = strtolower(ltrim($ext, '.'));
  $candidate = $baseName . ($ext !== '' ? '.' . $ext : '');
  $n = 2;
  while (is_file($dirAbs . '/' . $candidate)) {
    $candidate = $baseName . '_' . $n . ($ext !== '' ? '.' . $ext : '');
    $n++;
  }
  return $candidate;
}

/* ===== Validaciones DOCS ===== */
function is_pdf_name(string $name): bool {
  return strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'pdf';
}
function safe_pdf_filename(string $dirAbs, string $label): string {
  return unique_filename_in_dir($dirAbs, $label, 'pdf');
}
function is_valid_doc_key(string $k, array $DOCS): bool {
  return isset($DOCS[$k]);
}

/* ===== Validaciones Year + Cards + Files ===== */
function is_year_valid(int $y): bool { return ($y >= 2000 && $y <= 2100); }

function is_allowed_ext(string $name): bool {
  $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
  return in_array($ext, ['xlsx','xls','pdf'], true);
}
function ext_of(string $name): string {
  return strtolower(pathinfo($name, PATHINFO_EXTENSION));
}
function safe_filename(string $dirAbs, string $label, string $original): string {
  return unique_filename_in_dir($dirAbs, $label, ext_of($original));
}
function is_valid_card_id(string $id): bool {
  if (in_array($id, ['diag1','c1','diag2','c2'], true)) return true;
  return (bool)preg_match('/^cx_\d{14}_[a-f0-9]{6,12}$/', $id);
}
function rrmdir_safe(string $dirAbs, string $mustStartWithAbs): bool {
  $dirReal  = realpath($dirAbs);
  $baseReal = realpath($mustStartWithAbs);
  if (!$dirReal || !$baseReal || !starts_with($dirReal, $baseReal)) return false;
  if (!is_dir($dirReal)) return true;

  $items = @scandir($dirReal);
  if (!is_array($items)) return false;

  foreach ($items as $it) {
    if ($it === '.' || $it === '..') continue;
    $p = $dirReal . DIRECTORY_SEPARATOR . $it;
    if (is_dir($p)) {
      if (!rrmdir_safe($p, $mustStartWithAbs)) return false;
    } else {
      if (!@unlink($p)) return false;
    }
  }
  return @rmdir($dirReal);
}

/* ==========================================================
   ✅ ENDPOINT SEGURO PARA ABRIR/DESCARGAR (NO depende de /storage por URL)
   - Docs:  ?download=1&type=doc&key=directiva&file=...pdf
   - Año:   ?download=1&type=year&year=2026&card=c1&file=...pdf|xlsx|xls
   ========================================================== */
function send_file_download(string $absPath, string $downloadName, string $mime): void {
  if (!is_file($absPath)) {
    http_response_code(404);
    echo "Archivo no encontrado.";
    exit;
  }
  if (!is_readable($absPath)) {
    http_response_code(403);
    echo "Archivo no accesible (permisos).";
    exit;
  }
  $size = (int)@filesize($absPath);

  header('X-Content-Type-Options: nosniff');
  header('Content-Type: ' . $mime);
  header('Content-Length: ' . $size);
  header('Content-Disposition: inline; filename="' . rawurlencode($downloadName) . '"');
  header('Cache-Control: private, max-age=0, no-cache, no-store, must-revalidate');
  header('Pragma: no-cache');
  header('Expires: 0');

  @readfile($absPath);
  exit;
}
function mime_for_ext(string $ext): string {
  $ext = strtolower($ext);
  if ($ext === 'pdf')  return 'application/pdf';
  if ($ext === 'xlsx') return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
  if ($ext === 'xls')  return 'application/vnd.ms-excel';
  if ($ext === 'doc')  return 'application/msword';
  if ($ext === 'docx') return 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
  if ($ext === 'jpg' || $ext === 'jpeg') return 'image/jpeg';
  if ($ext === 'png') return 'image/png';
  if ($ext === 'webp') return 'image/webp';
  if ($ext === 'txt' || $ext === 'log') return 'text/plain; charset=UTF-8';
  if ($ext === 'csv') return 'text/csv; charset=UTF-8';
  if ($ext === 'zip') return 'application/zip';
  return 'application/octet-stream';
}

if (isset($_GET['download'])) {
  $type = (string)($_GET['type'] ?? '');

  if ($type === 'doc') {
    $key  = (string)($_GET['key'] ?? '');
    $file = (string)($_GET['file'] ?? '');

    // validaciones estrictas
    if ($key === '' || !preg_match('/^[a-z0-9_]+$/', $key)) { http_response_code(400); echo "Parámetro inválido."; exit; }
    if ($file === '' || preg_match('/^[A-Za-z0-9_\-]+\.(pdf)$/i', $file) !== 1) { http_response_code(400); echo "Archivo inválido."; exit; }

    $base = realpath($GLOBALS['DOCS_ABS_DIR'] . '/' . $key);
    if (!$base) { http_response_code(404); echo "No existe."; exit; }

    $abs = $base . '/' . $file;
    $realAbs = realpath($abs);
    if (!$realAbs || !starts_with($realAbs, $base)) { http_response_code(403); echo "Ruta no permitida."; exit; }

    send_file_download($realAbs, $file, mime_for_ext('pdf'));
  }

  if ($type === 'year') {
    $year = (int)($_GET['year'] ?? 0);
    $card = (string)($_GET['card'] ?? '');
    $file = (string)($_GET['file'] ?? '');

    if (!is_year_valid($year)) { http_response_code(400); echo "Año inválido."; exit; }
    if (!is_valid_card_id($card)) { http_response_code(400); echo "Tarjeta inválida."; exit; }
    if ($file === '' || preg_match('/^[A-Za-z0-9_\-]+\.(xlsx|xls|pdf)$/i', $file) !== 1) { http_response_code(400); echo "Archivo inválido."; exit; }

    $yearBase = realpath($GLOBALS['PAFB_BASE_ABS'] . '/' . $year . '/' . $card);
    if (!$yearBase) { http_response_code(404); echo "No existe."; exit; }

    $abs = $yearBase . '/' . $file;
    $realAbs = realpath($abs);
    if (!$realAbs || !starts_with($realAbs, $yearBase)) { http_response_code(403); echo "Ruta no permitida."; exit; }

    $ext = strtolower(pathinfo($realAbs, PATHINFO_EXTENSION));
    send_file_download($realAbs, $file, mime_for_ext($ext));
  }

  if ($type === 'shared') {
    $path = (string)($_GET['path'] ?? '');
    $rel = normalize_rel_path($path);
    if ($rel === null || $rel === '') { http_response_code(400); echo "Ruta inválida."; exit; }

    $base = realpath($GLOBALS['PAFB_ROOT_ABS']);
    if (!$base) { http_response_code(404); echo "Base no disponible."; exit; }

    $abs = $base . '/' . str_replace('\\', '/', $rel);
    $realAbs = realpath($abs);
    if (!$realAbs || !is_file($realAbs) || !starts_with($realAbs, $base)) {
      http_response_code(404);
      echo "Archivo no encontrado.";
      exit;
    }

    $ext = strtolower(pathinfo($realAbs, PATHINFO_EXTENSION));
    send_file_download($realAbs, basename($realAbs), mime_for_ext($ext));
  }

  http_response_code(400);
  echo "Tipo inválido.";
  exit;
}

/* ==========================
   CSRF (separado docs vs año)
   ========================== */
if (empty($_SESSION['csrf_pafb_docs'])) $_SESSION['csrf_pafb_docs'] = bin2hex(random_bytes(16));
$CSRF_DOCS = (string)$_SESSION['csrf_pafb_docs'];

function csrf_year_token(int $year): string {
  $k = 'csrf_pafb_year_' . $year;
  if (empty($_SESSION[$k])) $_SESSION[$k] = bin2hex(random_bytes(16));
  return (string)$_SESSION[$k];
}

/* ==========================
   Sembrar años “hoy”: Y, Y-1, Y-2 (auto)
   ========================== */
ensure_dir($PAFB_BASE_ABS);

$currentYear = (int)date('Y');
$seedYears = [$currentYear, $currentYear - 1, $currentYear - 2];

foreach ($seedYears as $y) {
  if (!is_year_valid($y)) continue;
  $yearAbs = $PAFB_BASE_ABS . '/' . $y;
  ensure_dir($yearAbs);

  $metaAbs = $yearAbs . '/_meta.json';
  if (!is_file($metaAbs)) {
    $m = ['custom_cards' => [], 'uploads' => []];
    foreach ($GLOBALS['FIXED_ORDER'] as $cid) $m['uploads'][$cid] = [];
    @write_meta($metaAbs, $m);
  }
}

/* ==========================
   Detectar años por storage
   ========================== */
$years = [];
foreach (glob($PAFB_BASE_ABS . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
  $base = basename($dir);
  if (preg_match('/^\d{4}$/', $base)) {
    $y = (int)$base;
    if (is_year_valid($y)) $years[$y] = true;
  }
}
if (empty($years)) $years[(int)date('Y')] = true;

$allYears = array_keys($years);
rsort($allYears);

$limitRecent = 3;
$yearsToShow = array_slice($allYears, 0, $limitRecent);
$oldYears    = array_slice($allYears, $limitRecent);

/* ==========================
   Router (vista)
   ========================== */
$view = 'home';
$yearView = 0;
if (isset($_GET['new'])) {
  $view = 'new';
} elseif (isset($_GET['year'])) {
  $yearView = (int)($_GET['year'] ?? 0);
  $view = is_year_valid($yearView) ? 'year' : 'home';
}

/* ==========================
   Flash
   ========================== */
$flashOk  = '';
$flashErr = '';

/* ==========================================================
   Estado/listado de DOCS (home)
   ========================================================== */
$docsState = [];
foreach ($DOCS as $k => $d) {
  $docDirAbs = $DOCS_ABS_DIR . '/' . $k;
  $metaAbs   = $docDirAbs . '/_meta.json';

  $meta = read_meta_docs($metaAbs);

  $uploads = [];
  $totalSize = 0;

  foreach ($meta['uploads'] as $it) {
    $file = (string)($it['file'] ?? '');
    if ($file === '') continue;

    $abs = $docDirAbs . '/' . $file;
    if (!is_file($abs)) continue;

    $size = (int)($it['size'] ?? (int)@filesize($abs));
    $totalSize += max(0, $size);

    $uploads[] = [
      'file' => $file,
      'orig' => (string)($it['orig'] ?? $file),
      'ref'  => (string)($it['ref'] ?? ''),
      'size' => $size,
      'at'   => (string)($it['at'] ?? ''),
      // ✅ abrir por endpoint
      'url'  => $SCRIPT_NAME . '?download=1&type=doc&key=' . rawurlencode($k) . '&file=' . rawurlencode($file),
    ];
  }

  usort($uploads, fn($a, $b) => strcmp((string)($b['at'] ?? ''), (string)($a['at'] ?? '')));

  $docsState[$k] = [
    'count' => count($uploads),
    'totalSize' => $totalSize,
    'uploads' => $uploads,
  ];
}

$sharedBrowseRel = normalize_rel_path((string)($_GET['shared'] ?? ''));
if ($sharedBrowseRel === null) $sharedBrowseRel = '';
$sharedState = scan_shared_dir($PAFB_ROOT_ABS, $sharedBrowseRel);
$sharedSegments = [];
if ($sharedState['ok'] && $sharedState['current'] !== '') {
  $acc = [];
  foreach (explode('/', (string)$sharedState['current']) as $seg) {
    $acc[] = $seg;
    $sharedSegments[] = ['name' => $seg, 'rel' => implode('/', $acc)];
  }
}

/* ==========================================================
   Helpers Year-meta init
   ========================================================== */
function init_year_meta(int $YEAR, string $META_ABS, array $FIXED_ORDER): array {
  $meta = read_meta_year($META_ABS);
  if (!isset($meta['custom_cards']) || !is_array($meta['custom_cards'])) $meta['custom_cards'] = [];
  if (!isset($meta['uploads']) || !is_array($meta['uploads'])) $meta['uploads'] = [];

  foreach (array_merge($FIXED_ORDER, array_map(fn($c)=> (string)($c['id'] ?? ''), $meta['custom_cards'])) as $cid) {
    if ($cid !== '' && !isset($meta['uploads'][$cid])) $meta['uploads'][$cid] = [];
  }
  return $meta;
}

/* ==========================================================
   POST handler unificado
   ========================================================== */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  $action = (string)($_POST['action'] ?? '');

  /* --------------------------
     Acciones DOCS (home)
     -------------------------- */
  if (in_array($action, ['upload_doc','delete_doc_file'], true)) {
    $token = (string)($_POST['csrf_docs'] ?? '');
    if (!hash_equals($CSRF_DOCS, $token)) {
      $flashErr = 'Acción bloqueada (token inválido).';
    } else {

      if ($action === 'upload_doc') {
        $key = (string)($_POST['doc_key'] ?? '');
        $ref = trim((string)($_POST['doc_ref'] ?? ''));

        if (!is_valid_doc_key($key, $DOCS)) {
          $flashErr = 'Documento inválido.';
        } elseif ($ref === '') {
          $flashErr = 'La referencia es obligatoria.';
        } else {
          $docDirAbs = $DOCS_ABS_DIR . '/' . $key;
          $metaAbs   = $docDirAbs . '/_meta.json';

          if (!ensure_dir($docDirAbs)) {
            $flashErr = 'No se pudo crear/acceder a la carpeta de documentos (permisos): ' . $docDirAbs;
          } elseif (empty($_FILES['doc_files']) || !isset($_FILES['doc_files']['name']) || !is_array($_FILES['doc_files']['name'])) {
            $flashErr = 'No se recibieron archivos.';
          } else {
            $meta = read_meta_docs($metaAbs);
            $count = count($_FILES['doc_files']['name']);
            $added = 0;

            for ($i = 0; $i < $count; $i++) {
              $name = (string)($_FILES['doc_files']['name'][$i] ?? '');
              $tmp  = (string)($_FILES['doc_files']['tmp_name'][$i] ?? '');
              $err  = (int)($_FILES['doc_files']['error'][$i] ?? UPLOAD_ERR_NO_FILE);
              $size = (int)($_FILES['doc_files']['size'][$i] ?? 0);

              if ($err === UPLOAD_ERR_NO_FILE) continue;

              if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
                $flashErr = 'El servidor rechazó el archivo por tamaño (límite PHP). '
                          . "Actual: upload_max_filesize={$GLOBALS['ini_upload']}, post_max_size={$GLOBALS['ini_post']}.";
                break;
              }
              if ($err !== UPLOAD_ERR_OK) { $flashErr = "Error al subir {$name} (código {$err})."; break; }
              if ($size <= 0) continue;

              if (!is_pdf_name($name)) { $flashErr = "Solo PDF permitido: {$name}."; break; }
              if ($size > $MAX_DOC_BYTES) { $flashErr = "Archivo demasiado grande: {$name} (" . human_mb($size) . "). Máximo: {$MAX_DOC_MB}MB."; break; }

              $final = safe_pdf_filename($docDirAbs, $ref);
              $destAbs = $docDirAbs . '/' . $final;

              if (!@move_uploaded_file($tmp, $destAbs)) {
                $flashErr = 'No se pudo guardar el PDF (permisos en storage). Carpeta: ' . $docDirAbs;
                break;
              }

              $meta['uploads'][] = [
                'file' => $final,
                'orig' => $name,
                'ref'  => $ref,
                'size' => $size,
                'at'   => date('Y-m-d H:i:s'),
              ];
              $added++;
            }

            if ($flashErr === '') {
              if ($added === 0) $flashErr = 'No se subió ningún archivo (verificá selección).';
              elseif (!write_meta($metaAbs, $meta)) $flashErr = 'Se subieron archivos pero no se pudo guardar _meta.json (permisos).';
              else { header('Location: ' . $SCRIPT_NAME . '?doc_ok=' . rawurlencode($key)); exit; }
            }
          }
        }
      }

      if ($action === 'delete_doc_file') {
        $key  = (string)($_POST['doc_key'] ?? '');
        $file = (string)($_POST['file'] ?? '');

        if (!is_valid_doc_key($key, $DOCS)) {
          $flashErr = 'Documento inválido.';
        } elseif ($file === '' || preg_match('/^[A-Za-z0-9_\-]+\.(pdf)$/i', $file) !== 1) {
          $flashErr = 'Archivo inválido.';
        } else {
          $docDirAbs = $DOCS_ABS_DIR . '/' . $key;
          $metaAbs   = $docDirAbs . '/_meta.json';

          $abs = $docDirAbs . '/' . $file;
          $realAbs  = realpath($abs);
          $realBase = realpath($docDirAbs);
          $okPath = $realAbs && $realBase && starts_with($realAbs, $realBase);

          if (!$okPath || !is_file((string)$realAbs)) {
            $flashErr = 'No se encontró el archivo para eliminar.';
          } elseif (!@unlink((string)$realAbs)) {
            $flashErr = 'No se pudo eliminar (permisos).';
          } else {
            $meta = read_meta_docs($metaAbs);
            $meta['uploads'] = array_values(array_filter($meta['uploads'], fn($it) => (string)($it['file'] ?? '') !== $file));
            @write_meta($metaAbs, $meta);
            header('Location: ' . $SCRIPT_NAME . '?doc_deleted=' . rawurlencode($key));
            exit;
          }
        }
      }
    }
  }

  /* --------------------------
     Acciones de AÑO (year/new)
     -------------------------- */
  if (in_array($action, ['create_year','delete_year','add_card','edit_card','delete_card','upload','delete_file'], true)) {
    $yearPosted = (int)($_POST['year'] ?? 0);

    if (!is_year_valid($yearPosted)) {
      $flashErr = 'Año inválido.';
    } else {
      $CSRF_YEAR = csrf_year_token($yearPosted);
      $token = (string)($_POST['csrf_year'] ?? '');

      if (!hash_equals($CSRF_YEAR, $token)) {
        $flashErr = 'Acción bloqueada (CSRF inválido).';
      } else {
        $YEAR = $yearPosted;

        $STORAGE_ABS_BASE = $PAFB_BASE_ABS . '/' . $YEAR;
        $META_ABS = $STORAGE_ABS_BASE . '/_meta.json';

        /* ===== create_year ===== */
        if ($action === 'create_year') {
          $anioActual = (int)date('Y');
          if ($YEAR <= $anioActual) {
            $flashErr = 'Solo se permite crear años futuros (mínimo: ' . ($anioActual + 1) . ').';
          } else {
            if (!ensure_dir($STORAGE_ABS_BASE)) {
              $flashErr = 'No se pudo crear/acceder a la carpeta del año (permisos): ' . $STORAGE_ABS_BASE;
            } else {
              $meta = init_year_meta($YEAR, $META_ABS, $FIXED_ORDER);
              if (!is_file($META_ABS)) {
                $meta['custom_cards'] = [];
                $meta['uploads'] = [];
                foreach ($FIXED_ORDER as $cid) $meta['uploads'][$cid] = [];
                if (!write_meta($META_ABS, $meta)) {
                  $flashErr = 'No se pudo crear _meta.json (permisos).';
                } else {
                  header('Location: ' . $SCRIPT_NAME . '?created=' . rawurlencode((string)$YEAR));
                  exit;
                }
              } else {
                header('Location: ' . $SCRIPT_NAME . '?exists=' . rawurlencode((string)$YEAR));
                exit;
              }
            }
          }
        }

        /* ===== delete_year ===== */
        if ($action === 'delete_year') {
          if (!is_dir($STORAGE_ABS_BASE)) {
            $flashErr = 'Ese año no existe en storage.';
          } else {
            $ok = rrmdir_safe($STORAGE_ABS_BASE, $PAFB_BASE_ABS);
            if (!$ok) $flashErr = 'No se pudo eliminar el año (permisos).';
            else { header('Location: ' . $SCRIPT_NAME . '?deleted=' . rawurlencode((string)$YEAR)); exit; }
          }
        }

        /* ===== Acciones del panel de año ===== */
        if (in_array($action, ['add_card','edit_card','delete_card','upload','delete_file'], true)) {
          if (!ensure_dir($STORAGE_ABS_BASE)) {
            $flashErr = 'No se pudo crear/acceder a la carpeta del año (permisos): ' . $STORAGE_ABS_BASE;
          } else {
            $meta = init_year_meta($YEAR, $META_ABS, $FIXED_ORDER);
            if (!is_file($META_ABS)) @write_meta($META_ABS, $meta);

            /* add_card */
            if ($action === 'add_card' && $flashErr === '') {
              $title = trim((string)($_POST['title'] ?? ''));
              $sub   = trim((string)($_POST['sub'] ?? ''));
              $icon  = trim((string)($_POST['icon'] ?? ''));

              if ($title === '') {
                $flashErr = 'El nombre de la tarjeta es obligatorio.';
              } else {
                $id = 'cx_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(8)), 0, 8);

                $meta['custom_cards'][] = [
                  'id'    => $id,
                  'title' => $title,
                  'sub'   => $sub,
                  'icon'  => ($icon !== '' ? $icon : '📌'),
                ];
                if (!isset($meta['uploads'][$id])) $meta['uploads'][$id] = [];

                if (!ensure_dir($STORAGE_ABS_BASE . '/' . $id)) {
                  $flashErr = 'No se pudo crear la carpeta de la tarjeta (permisos).';
                } elseif (!write_meta($META_ABS, $meta)) {
                  $flashErr = 'No se pudo guardar _meta.json (permisos).';
                } else {
                  header('Location: ' . $SCRIPT_NAME . '?year=' . $YEAR . '&ok=' . rawurlencode('Tarjeta agregada.'));
                  exit;
                }
              }
            }

            /* edit_card */
            if ($action === 'edit_card' && $flashErr === '') {
              $id    = (string)($_POST['id'] ?? '');
              $title = trim((string)($_POST['title'] ?? ''));
              $sub   = trim((string)($_POST['sub'] ?? ''));
              $icon  = trim((string)($_POST['icon'] ?? ''));

              if (!is_valid_card_id($id) || in_array($id, ['diag1','c1','diag2','c2'], true)) {
                $flashErr = 'Tarjeta inválida para editar.';
              } elseif ($title === '') {
                $flashErr = 'El nombre de la tarjeta es obligatorio.';
              } else {
                $found = false;
                foreach ($meta['custom_cards'] as &$cc) {
                  if (($cc['id'] ?? '') === $id) {
                    $cc['title'] = $title;
                    $cc['sub']   = $sub;
                    $cc['icon']  = ($icon !== '' ? $icon : ($cc['icon'] ?? '📌'));
                    $found = true;
                    break;
                  }
                }
                unset($cc);

                if (!$found) $flashErr = 'No se encontró la tarjeta.';
                elseif (!write_meta($META_ABS, $meta)) $flashErr = 'No se pudo guardar _meta.json (permisos).';
                else { header('Location: ' . $SCRIPT_NAME . '?year=' . $YEAR . '&ok=' . rawurlencode('Tarjeta actualizada.')); exit; }
              }
            }

            /* delete_card */
            if ($action === 'delete_card' && $flashErr === '') {
              $id = (string)($_POST['id'] ?? '');
              if (!is_valid_card_id($id) || in_array($id, ['diag1','c1','diag2','c2'], true)) {
                $flashErr = 'Tarjeta inválida para eliminar.';
              } else {
                $before = count($meta['custom_cards']);
                $meta['custom_cards'] = array_values(array_filter($meta['custom_cards'], fn($c)=> (string)($c['id'] ?? '') !== $id));
                $after = count($meta['custom_cards']);

                if ($after === $before) {
                  $flashErr = 'No se encontró la tarjeta.';
                } else {
                  unset($meta['uploads'][$id]);
                  $cardDir = $STORAGE_ABS_BASE . '/' . $id;
                  $okDel = rrmdir_safe($cardDir, $STORAGE_ABS_BASE);

                  if (!$okDel) {
                    $flashErr = 'Se quitó la tarjeta del listado, pero no se pudo borrar su carpeta (permisos).';
                    @write_meta($META_ABS, $meta);
                  } elseif (!write_meta($META_ABS, $meta)) {
                    $flashErr = 'Tarjeta eliminada, pero no se pudo guardar _meta.json (permisos).';
                  } else {
                    header('Location: ' . $SCRIPT_NAME . '?year=' . $YEAR . '&ok=' . rawurlencode('Tarjeta eliminada.'));
                    exit;
                  }
                }
              }
            }

            /* upload */
            if ($action === 'upload' && $flashErr === '') {
              $key = (string)($_POST['key'] ?? '');
              $ref = trim((string)($_POST['ref'] ?? ''));

              if (!is_valid_card_id($key)) {
                $flashErr = 'Tarjeta inválida.';
              } else {
                $destDirAbs = $STORAGE_ABS_BASE . '/' . $key;
                if (!ensure_dir($destDirAbs)) {
                  $flashErr = 'No se pudo crear carpeta destino (permisos).';
                } else {
                  if (empty($_FILES['files']) || !isset($_FILES['files']['name']) || !is_array($_FILES['files']['name'])) {
                    $flashErr = 'No se recibieron archivos.';
                  } else {
                    $count = count($_FILES['files']['name']);
                    $added = 0;

                    for ($i=0; $i<$count; $i++) {
                      $name = (string)($_FILES['files']['name'][$i] ?? '');
                      $tmp  = (string)($_FILES['files']['tmp_name'][$i] ?? '');
                      $err  = (int)($_FILES['files']['error'][$i] ?? UPLOAD_ERR_NO_FILE);
                      $size = (int)($_FILES['files']['size'][$i] ?? 0);

                      if ($err === UPLOAD_ERR_NO_FILE) continue;

                      // ✅ más claro cuando es límite de PHP
                      if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
                        $flashErr = 'El servidor rechazó el archivo por tamaño (límite PHP). '
                                  . "Actual: upload_max_filesize={$GLOBALS['ini_upload']}, post_max_size={$GLOBALS['ini_post']}.";
                        break;
                      }

                      if ($err !== UPLOAD_ERR_OK) { $flashErr = "Error al subir: $name (código $err)."; break; }
                      if ($size <= 0) continue;
                      if ($size > 25 * 1024 * 1024) { $flashErr = "Archivo demasiado grande (máx 25MB): $name"; break; }
                      if (!is_allowed_ext($name)) { $flashErr = "Extensión no permitida: $name (solo .xlsx/.xls/.pdf)."; break; }

                      $labelBase = $ref !== '' ? $ref : pathinfo($name, PATHINFO_FILENAME);
                      $finalName = safe_filename($destDirAbs, $labelBase, $name);
                      $destAbs   = $destDirAbs . '/' . $finalName;

                      if (!@move_uploaded_file($tmp, $destAbs)) {
                        $flashErr = 'No se pudo mover el archivo (permisos storage). Carpeta: ' . $destDirAbs;
                        break;
                      }

                      if (!isset($meta['uploads'][$key]) || !is_array($meta['uploads'][$key])) $meta['uploads'][$key] = [];
                      $meta['uploads'][$key][] = [
                        'file' => $finalName,
                        'orig' => $name,
                        'ref'  => $ref,
                        'size' => $size,
                        'at'   => date('Y-m-d H:i:s'),
                      ];
                      $added++;
                    }

                    if ($flashErr === '') {
                      if ($added === 0) $flashErr = 'No se subió ningún archivo (verificá selección).';
                      elseif (!write_meta($META_ABS, $meta)) $flashErr = 'Se subieron archivos pero no se pudo guardar _meta.json (permisos).';
                      else { header('Location: ' . $SCRIPT_NAME . '?year=' . $YEAR . '&ok=' . rawurlencode("Se subieron {$added} archivo(s).")); exit; }
                    }
                  }
                }
              }
            }

            /* delete_file */
            if ($action === 'delete_file' && $flashErr === '') {
              $key  = (string)($_POST['key'] ?? '');
              $file = (string)($_POST['file'] ?? '');

              if (!is_valid_card_id($key)) {
                $flashErr = 'Tarjeta inválida.';
              } elseif ($file === '' || preg_match('/^[A-Za-z0-9_\-]+\.(xlsx|xls|pdf)$/', $file) !== 1) {
                $flashErr = 'Archivo inválido.';
              } else {
                $abs = $STORAGE_ABS_BASE . '/' . $key . '/' . $file;

                $realAbs  = realpath($abs);
                $realBase = realpath($STORAGE_ABS_BASE . '/' . $key);
                $okPath = $realAbs && $realBase && starts_with($realAbs, $realBase);

                if (!$okPath || !is_file((string)$realAbs)) {
                  $flashErr = 'No se encontró el archivo para eliminar.';
                } elseif (!@unlink((string)$realAbs)) {
                  $flashErr = 'No se pudo eliminar el archivo (permisos).';
                } else {
                  $meta['uploads'][$key] = array_values(array_filter(
                    $meta['uploads'][$key] ?? [],
                    fn($it) => (string)($it['file'] ?? '') !== $file
                  ));

                  if (!write_meta($META_ABS, $meta)) $flashErr = 'Archivo eliminado, pero no se pudo actualizar _meta.json (permisos).';
                  else { header('Location: ' . $SCRIPT_NAME . '?year=' . $YEAR . '&ok=' . rawurlencode('Archivo eliminado correctamente.')); exit; }
                }
              }
            }
          }
        }
      }
    }
  }
}

/* ==========================
   Mensajes por querystring
   ========================== */
if (isset($_GET['deleted'])) {
  $y = (int)$_GET['deleted'];
  if ($y > 0) $flashOk = "Se eliminó el año {$y} (storage).";
}
if (isset($_GET['created'])) {
  $y = (int)$_GET['created'];
  if ($y > 0) $flashOk = "Se creó correctamente el año {$y}.";
}
if (isset($_GET['exists'])) {
  $y = (int)$_GET['exists'];
  if ($y > 0) $flashErr = "El año {$y} ya existe en storage.";
}
if (isset($_GET['doc_ok'])) {
  $k = (string)$_GET['doc_ok'];
  if (isset($DOCS[$k])) $flashOk = "Se subieron/actualizaron archivos en: {$DOCS[$k]['title']}.";
}
if (isset($_GET['doc_deleted'])) {
  $k = (string)$_GET['doc_deleted'];
  if (isset($DOCS[$k])) $flashOk = "Se eliminó un archivo de: {$DOCS[$k]['title']}.";
}
if (isset($_GET['ok'])) {
  $flashOk = (string)$_GET['ok'];
}

/* ==========================
   Build vista “year” (cards)
   ========================== */
$YEAR = 0;
$CSRF_YEAR = '';
$STORAGE_ABS_BASE = '';
$META_ABS = '';
$metaYear = [];
$cardsYear = [];

if ($view === 'year') {
  $YEAR = $yearView;
  $CSRF_YEAR = csrf_year_token($YEAR);

  $STORAGE_ABS_BASE = $PAFB_BASE_ABS . '/' . $YEAR;
  $META_ABS = $STORAGE_ABS_BASE . '/_meta.json';

  ensure_dir($STORAGE_ABS_BASE);

  $metaYear = init_year_meta($YEAR, $META_ABS, $FIXED_ORDER);
  if (!is_file($META_ABS)) @write_meta($META_ABS, $metaYear);

  // 4 fijas
  foreach ($FIXED_ORDER as $id) {
    $cardsYear[] = [
      'id' => $id,
      'title' => $BASE_CARDS[$id]['title'],
      'sub' => $BASE_CARDS[$id]['sub'],
      'icon' => $BASE_CARDS[$id]['icon'],
      'is_custom' => false,
    ];
  }
  // custom
  foreach ($metaYear['custom_cards'] as $cc) {
    $id = (string)($cc['id'] ?? '');
    if ($id === '' || !is_valid_card_id($id)) continue;
    $cardsYear[] = [
      'id' => $id,
      'title' => (string)($cc['title'] ?? 'Tarjeta'),
      'sub' => (string)($cc['sub'] ?? ''),
      'icon' => (string)($cc['icon'] ?? '📌'),
      'is_custom' => true,
    ];
  }
}

/* ==========================
   Build vista “new”
   ========================== */
$anioNuevo = 0;
if ($view === 'new') {
  $cur = (int)date('Y');
  $anioNuevo = $cur + 1;
  if (!empty($allYears)) {
    $max = max($allYears);
    $anioNuevo = max($anioNuevo, $max + 1);
  }
  $CSRF_YEAR = csrf_year_token($anioNuevo);
}

/* ==========================
   UI
   ========================== */
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>EC MIL M · Adiestramiento físico-militar (PAFB)</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<link rel="stylesheet" href="<?= e($ASSETS_URL) ?>/css/theme-602.css">
<link rel="icon" type="image/png" href="<?= e($ASSETS_URL) ?>/img/ecmilm.png">

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
  :root{
    --bg-dark: #020617;
    --card-bg: rgba(15,23,42,.94);
    --text-main: #e5e7eb;
    --text-muted: #9ca3af;
    --accent: #22c55e;
    --danger: #ef4444;
    --warn: #fbbf24;
  }
  *{ box-sizing:border-box; }
  body{
    min-height:100vh;margin:0;
    font-family:system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,sans-serif;
    background:
      radial-gradient(circle at top left, rgba(56,189,248,.22), transparent 60%),
      radial-gradient(circle at bottom right, rgba(34,197,94,.18), transparent 55%),
      url("<?= e($IMG_BG) ?>") center/cover fixed;
    background-color:var(--bg-dark);
    color:var(--text-main);
    overflow-x:hidden;
  }
  body::before{
    content:"";position:fixed;inset:0;
    background:radial-gradient(circle at top, rgba(15,23,42,.75), rgba(15,23,42,.95));
    pointer-events:none;z-index:-1;
  }
  .page-wrap{ padding:24px 16px 32px; }
  .container-main{ max-width:1200px;margin:0 auto; }

  header.brand-hero{ padding:14px 0 6px; }
  .hero-inner{
    max-width:1200px;margin:0 auto;
    display:flex;justify-content:space-between;align-items:center;gap:16px;
  }
  .brand-left{ display:flex;align-items:center;gap:14px; }
  .brand-logo{ height:56px;width:auto;filter:drop-shadow(0 0 10px rgba(0,0,0,.8)); }
  .brand-title{ font-weight:800;font-size:1.1rem;letter-spacing:.03em; }
  .brand-sub{ font-size:.8rem;color:#cbd5f5; }

  .header-actions{ display:flex;flex-wrap:wrap;gap:8px; align-items:center; justify-content:flex-end; }
  .btn-ghost{
    border-radius:999px;border:1px solid rgba(148,163,184,.55);
    background:rgba(15,23,42,.8);color:var(--text-main);
    font-size:.8rem;font-weight:600;padding:.35rem 1rem;
    box-shadow:0 10px 30px rgba(0,0,0,.55);
    text-decoration:none;white-space:nowrap;
  }
  .btn-ghost:hover{ background:rgba(30,64,175,.9);border-color:rgba(129,140,248,.9);color:white; }
  .btn-add{
    border-radius:999px;border:1px solid rgba(56,189,248,.55);
    background:rgba(56,189,248,.14);color:#dbeafe;
    font-size:.8rem;font-weight:800;padding:.35rem 1rem;
    box-shadow:0 10px 30px rgba(0,0,0,.55);white-space:nowrap;
  }
  .btn-add:hover{ background:rgba(56,189,248,.24); border-color:rgba(56,189,248,.85); color:#fff; }

  .section-header{ margin-bottom:16px; }
  .section-kicker{ margin-bottom:4px; }
  .section-kicker .sk-text{
    font-size:1.05rem;font-weight:900;letter-spacing:.18em;text-transform:uppercase;
    background:linear-gradient(90deg,#38bdf8,#22c55e);
    -webkit-background-clip:text;-webkit-text-fill-color:transparent;
    filter:drop-shadow(0 0 6px rgba(30,58,138,.55));
    padding-bottom:3px;border-bottom:2px solid rgba(34,197,94,.45);
    display:inline-block;
  }
  .section-title{ font-size:1.6rem;font-weight:900;margin-top:2px; }
  .section-sub{ font-size:.9rem;color:#cbd5f5;max-width:860px; }

  .docs-grid{
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:18px;
    margin:10px 0 18px;
    align-items:stretch;
  }
  @media (max-width:1199px){
    .docs-grid{ grid-template-columns:repeat(2,minmax(0,1fr)); }
  }
  @media (max-width:767px){
    .docs-grid{ grid-template-columns:1fr; }
  }
  .docs-grid .card-s3{ display:flex; flex-direction:column; min-height:300px; }
  .docs-grid .card-topline{ flex:1 1 auto; }
  .docs-grid .actions-row{ margin-top:auto; }

  .modules-grid{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:18px;
    align-items:stretch;
  }
  @media (max-width:991px){ .modules-grid{ grid-template-columns:repeat(2,minmax(0,1fr)); } }
  @media (max-width:575px){ .modules-grid{ grid-template-columns:1fr; } }
  .modules-grid > div{ display:flex; }
  .modules-grid > div > article.card-s3{ width:100%; }
  .modules-grid > div > a{ display:flex; flex:1; width:100%; height:100%; }
  .modules-grid > div > a > article.card-s3{ width:100%; }
  @media (min-width:992px){
    .modules-grid article.card-s3{
      height:170px;
      display:flex;
      flex-direction:column;
    }
    .modules-grid .card-topline{ flex:1 1 auto; }
    .modules-grid .actions-row{ margin-top:auto; }
    .modules-grid .card-sub{
      display:-webkit-box;
      -webkit-line-clamp:2;
      -webkit-box-orient:vertical;
      overflow:hidden;
    }
  }
  @media (max-width:991px){
    .modules-grid article.card-s3{
      display:flex;
      flex-direction:column;
      min-height:170px;
    }
    .modules-grid .card-topline{ flex:1 1 auto; }
    .modules-grid .actions-row{ margin-top:auto; }
  }

  .grid{ display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px; }
  @media (max-width:991px){ .grid{ grid-template-columns:repeat(2,minmax(0,1fr)); } }
  @media (max-width:575px){ .grid{ grid-template-columns:1fr; } }

  .card-s3{
    position:relative;border-radius:22px;padding:18px 18px 14px;
    background:
      radial-gradient(circle at top left, rgba(56,189,248,.22), transparent 60%),
      radial-gradient(circle at bottom right, rgba(34,197,94,.18), transparent 70%),
      var(--card-bg);
    border:1px solid rgba(15,23,42,.9);
    box-shadow:0 22px 40px rgba(0,0,0,.85), 0 0 0 1px rgba(148,163,184,.35);
    backdrop-filter:blur(12px);
    transition:transform .18s ease-out, box-shadow .18s ease-out, border-color .18s ease-out;
    overflow:hidden;
  }
  .card-s3:hover{
    transform:translateY(-3px) scale(1.01);
    box-shadow:0 28px 60px rgba(0,0,0,.9), 0 0 0 1px rgba(129,140,248,.65);
    border-color:rgba(129,140,248,.9);
  }

  .card-topline{ display:flex;align-items:flex-start;justify-content:space-between;gap:10px; }
  .card-icon{ font-size:1.4rem;line-height:1;filter:drop-shadow(0 0 6px rgba(0,0,0,.7)); }
  .card-title{ font-weight:850;font-size:1rem;margin:0; }
  .card-sub{ font-size:.78rem;color:var(--text-muted);margin-top:4px; }

  .actions-row, .actions{
    display:flex;align-items:center;justify-content:space-between;
    gap:10px;margin-top:14px;flex-wrap:wrap;
  }
  .btn-open{
    display:inline-flex;align-items:center;justify-content:center;
    padding:8px 12px;border-radius:12px;font-weight:900;text-decoration:none;
    background:rgba(34,197,94,.2);color:#d1fae5;border:1px solid rgba(34,197,94,.45);
    white-space:nowrap;
  }
  .btn-open:hover{ background:rgba(34,197,94,.32); border-color:rgba(34,197,94,.7); color:white; }
  .btn-open.disabled{
    pointer-events:none; opacity:.55;
    background:rgba(251,191,36,.12);
    border-color:rgba(251,191,36,.35);
    color:#fde68a;
  }
  .btn-upload{
    display:inline-flex; align-items:center; justify-content:center;
    padding:8px 12px; border-radius:12px; font-weight:900;
    background:rgba(56,189,248,.18);
    color:#dbeafe;
    border:1px solid rgba(56,189,248,.40);
    white-space:nowrap;
  }
  .btn-upload:hover{ background:rgba(56,189,248,.28); border-color:rgba(56,189,248,.65); color:#fff; }

  .btn-del{
    border-radius:12px;padding:8px 12px;font-weight:900;
    border:1px solid rgba(239,68,68,.45);
    background:rgba(239,68,68,.12);color:#fecaca;white-space:nowrap;
  }
  .btn-del:hover{ background:rgba(239,68,68,.20); border-color:rgba(239,68,68,.7); color:#fff; }

  .btn-mini{
    border-radius:10px;padding:6px 10px;font-weight:900;font-size:.78rem;
    border:1px solid rgba(148,163,184,.35);
    background:rgba(15,23,42,.55); color:#e5e7eb; text-decoration:none; white-space:nowrap;
  }
  .btn-mini:hover{ background:rgba(30,64,175,.35); border-color:rgba(129,140,248,.45); color:#fff; }
  .btn-danger-mini{
    border-radius:10px;padding:6px 10px;font-weight:900;font-size:.78rem;
    border:1px solid rgba(239,68,68,.45);
    background:rgba(239,68,68,.12); color:#fecaca; white-space:nowrap;
  }
  .btn-danger-mini:hover{ background:rgba(239,68,68,.20); border-color:rgba(239,68,68,.70); color:#fff; }

  .pill{
    font-size:.68rem;text-transform:uppercase;letter-spacing:.16em;
    padding:.15rem .55rem;border-radius:999px;
    border:1px solid rgba(148,163,184,.6);color:#e5e7eb;
    background:rgba(15,23,42,.4);
    display:inline-flex;align-items:center;gap:6px;white-space:nowrap;
  }
  .pill.ok{ border-color:rgba(34,197,94,.75); }
  .pill.wait{ border-color:rgba(251,191,36,.75); }

  .files{ margin-top:12px; border-top:1px solid rgba(148,163,184,.20); padding-top:10px; }
  .file-item{
    display:flex; align-items:center; justify-content:space-between; gap:10px;
    padding:8px 10px; border-radius:12px;
    background:rgba(2,6,23,.35);
    border:1px solid rgba(148,163,184,.16);
    margin-top:8px;
  }
  .file-left{ min-width:0; }
  .file-name{ font-size:.82rem; font-weight:800; margin:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .file-meta{ font-size:.72rem; color:#cbd5f5; opacity:.9; margin-top:2px; }
  .file-actions{ display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end; }

  .doc-badge{
    display:inline-flex;align-items:center;gap:.35rem;font-size:.72rem;color:#e5e7eb;
    padding:.18rem .55rem;border-radius:999px;border:1px solid rgba(148,163,184,.45);
    background:rgba(15,23,42,.35);
  }
  .doc-badge.ok{ border-color:rgba(34,197,94,.55); background:rgba(34,197,94,.10); color:#bbf7d0; }
  .doc-badge.missing{ border-color:rgba(251,191,36,.55); background:rgba(251,191,36,.10); color:#fde68a; }

  .files-list{ margin-top:10px; display:flex; flex-direction:column; gap:8px; }
  .file-item-doc{
    display:flex; align-items:flex-start; justify-content:space-between; gap:10px;
    padding:8px 10px; border-radius:14px;
    border:1px solid rgba(148,163,184,.28);
    background:rgba(2,6,23,.28);
  }
  .card-tools{ margin-top:10px; display:flex; gap:8px; justify-content:flex-end; flex-wrap:wrap; opacity:.95; }
  .panel{
    background:rgba(15,23,42,.92);
    border-radius:18px;
    border:1px solid rgba(148,163,184,.45);
    padding:20px;
    box-shadow:0 20px 45px rgba(0,0,0,.85);
  }
  .year-input-group .form-control, .year-input-group .btn{ height:44px; }
  .year-input-group .form-control{ max-width:180px; }
  .year-input-group .btn{ display:flex; align-items:center; gap:.45rem; white-space:nowrap; }
  .shared-panel{
    margin-top:24px;
    background:rgba(15,23,42,.82);
    border:1px solid rgba(148,163,184,.28);
    border-radius:18px;
    padding:18px;
  }
  .shared-toolbar{
    display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;
    margin-bottom:12px;
  }
  .shared-path{
    display:flex; flex-wrap:wrap; gap:6px; align-items:center; font-size:.86rem; color:#cbd5e1;
  }
  .shared-path a{ color:#7dd3fc; text-decoration:none; }
  .shared-table{
    width:100%;
    border-collapse:collapse;
    overflow:hidden;
    border-radius:12px;
  }
  .shared-table th, .shared-table td{
    padding:10px 12px;
    border-bottom:1px solid rgba(148,163,184,.16);
    text-align:left;
    font-size:.84rem;
  }
  .shared-table th{
    color:#93c5fd;
    background:rgba(30,41,59,.92);
    font-weight:800;
  }
  .shared-table td{ color:#e5e7eb; }
  .shared-table tr:hover td{ background:rgba(59,130,246,.08); }
  .shared-name{
    display:flex; align-items:center; gap:10px; min-width:0;
  }
  .shared-icon{
    width:28px; text-align:center; font-size:1rem;
  }
  .shared-link{
    color:#e5e7eb; text-decoration:none; font-weight:700;
    overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
  }
  .shared-link:hover{ color:#7dd3fc; }
  .shared-meta{ color:#94a3b8; font-size:.75rem; }
  .shared-empty{
    padding:18px; border:1px dashed rgba(148,163,184,.24); border-radius:12px; color:#94a3b8;
  }
</style>
</head>

<body>
<header class="brand-hero">
  <div class="hero-inner">
    <div class="brand-left">
      <img src="<?= e($ESCUDO) ?>" class="brand-logo" alt="Escudo Ec Mil M">
      <div>
        <div class="brand-title">Escuela Militar de Montaña</div>
        <div class="brand-sub">“La montaña nos une”</div>
      </div>
    </div>

    <div class="header-actions">
      <?php if ($view === 'year'): ?>
        <button class="btn-add" type="button" id="btnAddCard">➕ Agregar tarjeta</button>
        <a href="<?= e($SCRIPT_NAME) ?>" class="btn-ghost">⬅ Volver a PAFB</a>
      <?php elseif ($view === 'new'): ?>
        <a href="<?= e($SCRIPT_NAME) ?>" class="btn-ghost">⬅ Volver a PAFB</a>
      <?php else: ?>
        <a href="<?= e($SCRIPT_NAME) ?>?new=1" class="btn-ghost">➕ Nuevo año</a>
      <?php endif; ?>

      <a href="<?= e($APP_BASE) ?>/public/inicio.php" class="btn-ghost">Volver a Inicio</a>
      <a href="<?= e($APP_BASE) ?>/public/s3/operaciones/operaciones.php" class="btn-ghost">Volver a Operaciones</a>
    </div>
  </div>
</header>

<div class="page-wrap">
  <div class="container-main">

    <?php if ($flashOk): ?>
      <div class="alert alert-success"><?= e($flashOk) ?></div>
    <?php endif; ?>
    <?php if ($flashErr): ?>
      <div class="alert alert-danger"><?= e($flashErr) ?></div>
    <?php endif; ?>

    <?php if ($view === 'home'): ?>
      <div class="section-header">
        <div class="section-title">Adiestramiento físico-militar (PAFB)</div>
        <p class="section-sub mb-2">
          Seleccioná un año. Dentro vas a encontrar la <b>1ra</b> y <b>2da</b> comprobación, y
          los <b>diagnósticos</b>.
        </p>

        <?php if (!empty($oldYears)): ?>
          <button class="btn btn-ghost mt-2" type="button"
                  data-bs-toggle="collapse" data-bs-target="#oldYearsBox"
                  aria-expanded="false" aria-controls="oldYearsBox">
            Ver años viejos (<?= (int)count($oldYears) ?>)
          </button>
        <?php endif; ?>
      </div>

      <!-- DOCS -->
      <div class="docs-grid">
        <?php foreach ($DOCS as $k => $d):
          $st = $docsState[$k] ?? ['count'=>0,'totalSize'=>0,'uploads'=>[]];
          $badgeCls = ($st['count'] > 0) ? 'ok' : 'missing';
          $badgeTxt = ($st['count'] > 0) ? 'Cargado' : 'No cargado';
        ?>
          <article class="card-s3">
            <div class="card-topline">
              <div style="min-width:0">
                <div class="card-title"><?= e($d['title']) ?></div>
                <div class="card-sub"><?= e($d['sub']) ?></div>
                <div class="mt-2">
                  <span class="doc-badge <?= e($badgeCls) ?>">
                    <?= ($st['count'] > 0) ? '✅' : '⚠️' ?> <?= e($badgeTxt) ?>
                  </span>
                  <span class="doc-badge ms-2">📎 <?= (int)$st['count'] ?> archivo(s)</span>
                  <?php if ((int)$st['totalSize'] > 0): ?>
                    <span class="doc-badge ms-2">📦 <?= e(human_mb((int)$st['totalSize'])) ?></span>
                  <?php endif; ?>
                </div>

                <?php if (!empty($st['uploads'])): ?>
                  <div class="files-list">
                    <?php foreach (array_slice($st['uploads'], 0, 3) as $it): ?>
                      <div class="file-item-doc">
                        <div class="file-left" style="min-width:0">
                          <div class="file-name"><?= e($it['orig']) ?></div>
                          <div class="file-meta">
                            <span><b>Ref:</b> <?= e($it['ref']) ?></span>
                            <?php if ($it['at'] !== ''): ?> · <span><?= e($it['at']) ?></span><?php endif; ?>
                            · <span><?= e(human_mb((int)$it['size'])) ?></span>
                          </div>
                        </div>
                        <div class="file-actions">
                          <a class="btn-mini" href="<?= e($it['url']) ?>" target="_blank" rel="noopener">Abrir</a>

                          <form method="post" class="m-0 js-delete-doc-file"
                                data-doc="<?= e($k) ?>"
                                data-name="<?= e($it['orig']) ?>"
                                data-ref="<?= e($it['ref']) ?>">
                            <input type="hidden" name="action" value="delete_doc_file">
                            <input type="hidden" name="csrf_docs" value="<?= e($CSRF_DOCS) ?>">
                            <input type="hidden" name="doc_key" value="<?= e($k) ?>">
                            <input type="hidden" name="file" value="<?= e($it['file']) ?>">
                            <button type="submit" class="btn-danger-mini">Eliminar</button>
                          </form>
                        </div>
                      </div>
                    <?php endforeach; ?>

                    <?php if (count($st['uploads']) > 3): ?>
                      <div class="file-meta" style="opacity:.8">Mostrando 3 de <?= (int)count($st['uploads']) ?> archivos…</div>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
              </div>

              <div class="d-flex flex-column align-items-end gap-1">
                <div class="card-icon"><?= e($d['icon']) ?></div>
              </div>
            </div>

            <div class="actions-row">
              <button type="button" class="btn-upload js-open-upload-doc"
                      data-doc="<?= e($k) ?>" data-title="<?= e($d['title']) ?>">
                ⬆️ Subir archivos
              </button>
            </div>
          </article>
        <?php endforeach; ?>
      </div>

      <!-- AÑOS -->
      <div class="modules-grid">
        <?php
          $currentYearUI = (int)date('Y');
          foreach ($yearsToShow as $year):
            if ($year === $currentYearUI) { $sub = 'PAFB en ejecución.'; $icon = '📈'; }
            elseif ($year === $currentYearUI - 1) { $sub = 'Resultados / documentación del año anterior.'; $icon = '📊'; }
            elseif ($year < $currentYearUI - 1) { $sub = 'Referencias y antecedentes de PAFB.'; $icon = '📚'; }
            else { $sub = 'Configurado a futuro (sin datos cargados).'; $icon = '🗓️'; }
        ?>
          <div>
            <article class="card-s3">
              <div class="card-topline">
                <div>
                  <div class="card-title">Año <?= e($year) ?></div>
                  <div class="card-sub"><?= e($sub) ?></div>
                </div>
                <div class="d-flex flex-column align-items-end gap-1">
                  <div class="card-icon"><?= e($icon) ?></div>
                </div>
              </div>

              <div class="actions-row">
                <a class="btn-open" href="<?= e($SCRIPT_NAME) ?>?year=<?= (int)$year ?>">Entrar</a>

                <?php $csrfYearHome = csrf_year_token((int)$year); ?>
                <form method="post" class="m-0 js-delete-year" data-year="<?= (int)$year ?>">
                  <input type="hidden" name="action" value="delete_year">
                  <input type="hidden" name="year" value="<?= (int)$year ?>">
                  <input type="hidden" name="csrf_year" value="<?= e($csrfYearHome) ?>">
                  <button type="submit" class="btn-del">Eliminar</button>
                </form>
              </div>
            </article>
          </div>
        <?php endforeach; ?>

        <div>
          <a href="<?= e($SCRIPT_NAME) ?>?new=1" style="text-decoration:none;color:inherit;display:block;height:100%;">
            <article class="card-s3">
              <div class="card-topline">
                <div>
                  <div class="card-title">Agregar nuevo año</div>
                  <div class="card-sub">Configurar PAFB para el próximo período.</div>
                </div>
                <div class="d-flex flex-column align-items-end gap-1">
                  <div class="card-icon">➕</div>
                </div>
              </div>
              <div class="actions-row">
                <span class="btn-open" style="opacity:.65; pointer-events:none;">Configurar</span>
                <span class="btn-del" style="opacity:.35; pointer-events:none;">+</span>
              </div>
            </article>
          </a>
        </div>
      </div>

      <?php if (!empty($oldYears)): ?>
        <div class="collapse mt-3" id="oldYearsBox">
          <div class="alert alert-secondary" style="background:rgba(15,23,42,.75); border-color:rgba(148,163,184,.35); color:#e5e7eb;">
            <b>Años viejos:</b> quedan ocultos para no ensuciar el panel. Desde acá podés abrirlos o eliminarlos.
            <br><span class="text-warning">Eliminar borra la carpeta del año en storage.</span>
          </div>

          <div class="modules-grid">
            <?php foreach ($oldYears as $year): ?>
              <div>
                <article class="card-s3">
                  <div class="card-topline">
                    <div>
                      <div class="card-title">Año <?= e($year) ?></div>
                      <div class="card-sub">Año histórico (oculto por defecto).</div>
                    </div>
                    <div class="d-flex flex-column align-items-end gap-1">
                      <div class="card-icon">📚</div>
                    </div>
                  </div>

                  <div class="actions-row">
                    <a class="btn-open" href="<?= e($SCRIPT_NAME) ?>?year=<?= (int)$year ?>">Abrir</a>
                    <?php $csrfYearOld = csrf_year_token((int)$year); ?>
                    <form method="post" class="m-0 js-delete-year" data-year="<?= (int)$year ?>">
                      <input type="hidden" name="action" value="delete_year">
                      <input type="hidden" name="year" value="<?= (int)$year ?>">
                      <input type="hidden" name="csrf_year" value="<?= e($csrfYearOld) ?>">
                      <button type="submit" class="btn-del">Eliminar</button>
                    </form>
                  </div>
                </article>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <div class="shared-panel" id="shared-browser">
        <div class="section-header" style="margin-bottom:10px;">
          <div class="section-title" style="font-size:1.25rem;">Carpeta compartida PAFB</div>
          <div class="section-sub">
            Acá se ve todo lo que exista físicamente dentro de <code><?= e(str_replace('\\', '/', $PAFB_ROOT_ABS)) ?></code>,
            incluso si lo cargaron desde Windows Explorer y no desde la página.
          </div>
        </div>

        <?php if (!$sharedState['ok']): ?>
          <div class="alert alert-warning mb-0"><?= e((string)$sharedState['error']) ?></div>
        <?php else: ?>
          <div class="shared-toolbar">
            <div class="shared-path">
              <span><b>Ruta:</b></span>
                <a class="js-shared-nav" href="<?= e($SCRIPT_NAME) ?>#shared-browser">PAFB</a>
              <?php foreach ($sharedSegments as $seg): ?>
                <span>/</span>
                <a class="js-shared-nav" href="<?= e($SCRIPT_NAME) ?>?shared=<?= e(rawurlencode((string)$seg['rel'])) ?>#shared-browser"><?= e((string)$seg['name']) ?></a>
              <?php endforeach; ?>
            </div>

            <div class="d-flex gap-2 flex-wrap">
              <?php if ($sharedState['current'] !== ''): ?>
                <?php
                  $parentRel = '';
                  $partsParent = explode('/', (string)$sharedState['current']);
                  array_pop($partsParent);
                  $parentRel = implode('/', $partsParent);
                ?>
                <a class="btn-ghost js-shared-nav" href="<?= e($SCRIPT_NAME) ?><?= $parentRel !== '' ? ('?shared=' . e(rawurlencode($parentRel))) : '' ?>#shared-browser">⬅ Volver</a>
              <?php endif; ?>
              <a class="btn-ghost js-shared-nav" href="<?= e($SCRIPT_NAME) ?>#shared-browser">Ir a raíz PAFB</a>
            </div>
          </div>

          <?php if (empty($sharedState['entries'])): ?>
            <div class="shared-empty">Esta carpeta está vacía.</div>
          <?php else: ?>
            <div style="overflow:auto;">
              <table class="shared-table">
                <thead>
                  <tr>
                    <th>Nombre</th>
                    <th style="width:140px;">Tipo</th>
                    <th style="width:160px;">Modificado</th>
                    <th style="width:120px;">Tamaño</th>
                    <th style="width:120px;">Acción</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($sharedState['entries'] as $entry): ?>
                    <?php
                      $isDir = (bool)$entry['is_dir'];
                      $rel = (string)$entry['rel'];
                      $openHref = $isDir
                        ? ($SCRIPT_NAME . '?shared=' . rawurlencode($rel))
                        : ($SCRIPT_NAME . '?download=1&type=shared&path=' . rawurlencode($rel));
                    ?>
                    <tr>
                      <td>
                        <div class="shared-name">
                          <div class="shared-icon"><?= $isDir ? '📁' : '📄' ?></div>
                          <div style="min-width:0;">
                            <a class="shared-link <?= $isDir ? 'js-shared-nav' : '' ?>" <?= $isDir ? '' : 'target="_blank" rel="noopener"' ?> href="<?= e($isDir ? ($openHref . '#shared-browser') : $openHref) ?>">
                              <?= e((string)$entry['name']) ?>
                            </a>
                            <div class="shared-meta"><?= e($rel) ?></div>
                          </div>
                        </div>
                      </td>
                      <td><?= $isDir ? 'Carpeta' : e(strtoupper((string)($entry['ext'] !== '' ? $entry['ext'] : 'archivo'))) ?></td>
                      <td><?= e(format_dt_local((int)$entry['mtime'])) ?></td>
                      <td><?= $isDir ? '—' : e(human_size((int)($entry['size'] ?? 0))) ?></td>
                      <td>
                        <a class="btn-mini <?= $isDir ? 'js-shared-nav' : '' ?>" <?= $isDir ? '' : 'target="_blank" rel="noopener"' ?> href="<?= e($isDir ? ($openHref . '#shared-browser') : $openHref) ?>">
                          <?= $isDir ? 'Abrir' : 'Ver' ?>
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>

    <?php elseif ($view === 'new'): ?>
      <?php
        $anioActual = (int)date('Y');
        $minYear = $anioActual + 1;
      ?>
      <div class="section-header">
        <div class="section-kicker"><span class="sk-text">PAFB</span></div>
        <div class="section-title">Crear un nuevo período</div>
        <div class="section-sub">Genera el año creando su carpeta en <code>storage</code>.</div>
      </div>

      <div class="panel">
        <p class="mb-2">Sugerencia automática:</p>
        <h2 class="text-success fw-bold mb-3"><?= e((string)$anioNuevo) ?></h2>

        <form method="post">
          <input type="hidden" name="action" value="create_year">
          <input type="hidden" name="csrf_year" value="<?= e($CSRF_YEAR) ?>">
          <div class="mb-1">
            <label class="form-label fw-bold mb-1">Año a generar</label>
            <div class="input-group year-input-group">
              <input type="number" name="year" class="form-control"
                     min="<?= e((string)$minYear) ?>" max="2100"
                     value="<?= e((string)$anioNuevo) ?>" required>
              <button type="submit" class="btn btn-success fw-bold">➕ Generar</button>
            </div>
            <div class="form-text text-light" style="opacity:.75">
              Solo futuro (mínimo <?= e((string)$minYear) ?>).
            </div>
          </div>
        </form>
      </div>

    <?php else: /* year view */ ?>
      <div class="section-header">
        <div class="section-kicker"><span class="sk-text">PAFB <?= e($YEAR) ?></span></div>
        <div class="section-title">Comprobaciones y diagnósticos</div>
        <div class="section-sub">
          Las 4 tarjetas base se muestran siempre. Podés agregar tarjetas extra.
          Subís <b>Excel/PDF</b> con referencia y se abren <b>directo</b>.
        </div>
      </div>

      <div class="grid">
        <?php foreach ($cardsYear as $card):
          $id = (string)$card['id'];
          $uploads = $metaYear['uploads'][$id] ?? [];
          $hasFiles = !empty($uploads);

          $last = $hasFiles ? $uploads[count($uploads)-1] : null;
          $lastFile = (string)($last['file'] ?? '');
          // ✅ abrir por endpoint
          $openHref = ($hasFiles && $lastFile !== '')
            ? ($SCRIPT_NAME . '?download=1&type=year&year=' . $YEAR . '&card=' . rawurlencode($id) . '&file=' . rawurlencode($lastFile))
            : '#';
        ?>
          <article class="card-s3">
            <div class="card-topline">
              <div style="min-width:0">
                <h3 class="card-title"><?= e($card['title']) ?></h3>
                <div class="card-sub"><?= e($card['sub']) ?></div>
              </div>
              <div class="d-flex flex-column align-items-end gap-1">
                <div class="card-icon"><?= e($card['icon']) ?></div>
                <?php if ($hasFiles): ?><span class="pill ok">Cargado</span><?php else: ?><span class="pill wait">Pendiente</span><?php endif; ?>
              </div>
            </div>

            <div class="actions">
              <a class="btn-open <?= $hasFiles ? '' : 'disabled' ?>"
                 href="<?= e($openHref) ?>" target="_blank" rel="noopener">Abrir último</a>

              <button type="button" class="btn-upload js-open-upload"
                      data-key="<?= e($id) ?>" data-title="<?= e($card['title']) ?>">
                Subir
              </button>
            </div>

            <?php if (!empty($card['is_custom'])): ?>
              <div class="card-tools">
                <button type="button" class="btn-mini js-edit-card"
                        data-id="<?= e($id) ?>"
                        data-title="<?= e($card['title']) ?>"
                        data-sub="<?= e($card['sub']) ?>"
                        data-icon="<?= e($card['icon']) ?>">
                  Editar
                </button>

                <form method="post" class="m-0 js-delete-card" data-title="<?= e($card['title']) ?>">
                  <input type="hidden" name="action" value="delete_card">
                  <input type="hidden" name="year" value="<?= (int)$YEAR ?>">
                  <input type="hidden" name="csrf_year" value="<?= e($CSRF_YEAR) ?>">
                  <input type="hidden" name="id" value="<?= e($id) ?>">
                  <button type="submit" class="btn-danger-mini">Eliminar tarjeta</button>
                </form>
              </div>
            <?php endif; ?>

            <div class="files">
              <?php if (!$hasFiles): ?>
                <div class="file-meta">Sin archivos cargados todavía.</div>
              <?php else: ?>
                <?php
                  $toShow = array_slice($uploads, -8);
                  foreach (array_reverse($toShow) as $it):
                    $file = (string)($it['file'] ?? '');
                    $orig = (string)($it['orig'] ?? $file);
                    $ref  = (string)($it['ref'] ?? '');
                    $at   = (string)($it['at'] ?? '');
                    $href = ($file !== '')
                      ? ($SCRIPT_NAME . '?download=1&type=year&year=' . $YEAR . '&card=' . rawurlencode($id) . '&file=' . rawurlencode($file))
                      : '#';
                    $isPdf = strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'pdf';
                ?>
                  <div class="file-item">
                    <div class="file-left">
                      <p class="file-name" title="<?= e($orig) ?>"><?= $isPdf ? '📄' : '📊' ?> <?= e($orig) ?></p>
                      <div class="file-meta">
                        <?= $ref !== '' ? ('Ref: <b>' . e($ref) . '</b> · ') : '' ?>
                        <?= $at !== '' ? e($at) : '' ?>
                      </div>
                    </div>

                    <div class="file-actions">
                      <a class="btn-mini" href="<?= e($href) ?>" target="_blank" rel="noopener">Abrir</a>

                      <form method="post" class="m-0 js-delete-file" data-orig="<?= e($orig) ?>">
                        <input type="hidden" name="action" value="delete_file">
                        <input type="hidden" name="year" value="<?= (int)$YEAR ?>">
                        <input type="hidden" name="csrf_year" value="<?= e($CSRF_YEAR) ?>">
                        <input type="hidden" name="key" value="<?= e($id) ?>">
                        <input type="hidden" name="file" value="<?= e($file) ?>">
                        <button type="submit" class="btn-danger-mini">Eliminar</button>
                      </form>
                    </div>
                  </div>
                <?php endforeach; ?>

                <?php if (count($uploads) > 8): ?>
                  <div class="file-meta mt-2">Mostrando últimos 8. (Total: <?= (int)count($uploads) ?>)</div>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>

    <?php endif; ?>

  </div>
</div>

<!-- Modal subir DOCS (home) -->
<div class="modal fade" id="uploadDocModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="background:rgba(15,23,42,.98);border:1px solid rgba(148,163,184,.35);color:#e5e7eb;border-radius:16px;">
      <div class="modal-header" style="border-bottom:1px solid rgba(148,163,184,.2);">
        <h5 class="modal-title fw-bold" id="uploadDocTitle">Subir documentos</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <form method="post" enctype="multipart/form-data" class="m-0">
        <div class="modal-body">
          <input type="hidden" name="action" value="upload_doc">
          <input type="hidden" name="csrf_docs" value="<?= e($CSRF_DOCS) ?>">
          <input type="hidden" name="doc_key" id="uploadDocKey" value="">

          <div class="mb-2">
            <label class="form-label fw-bold mb-1">Nombre / referencia (obligatorio)</label>
            <input type="text" name="doc_ref" class="form-control" maxlength="180"
                   placeholder="Ej: Directiva 828/20 - Actualización 2025 / Fuente: ... / Observación ..."
                   required>
            <div class="form-text text-light" style="opacity:.75">
              Ese texto se usa como nombre del archivo guardado dentro de <code>DOCUMENTACION</code>.
            </div>
          </div>

          <div class="mb-2">
            <label class="form-label fw-bold mb-1">Archivo(s) PDF</label>
            <input type="file" name="doc_files[]" class="form-control" accept="application/pdf" multiple required>
            <div class="form-text text-light" style="opacity:.75">
              Podés seleccionar varios PDFs en una misma subida. Máx código: <?= (int)$MAX_DOC_MB ?>MB c/u.
              <br>Límites servidor: upload_max_filesize=<?= e($ini_upload) ?> · post_max_size=<?= e($ini_post) ?>
            </div>
          </div>
        </div>

        <div class="modal-footer" style="border-top:1px solid rgba(148,163,184,.2);">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-success fw-bold">⬆️ Subir</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal subir archivos AÑO -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="background:rgba(15,23,42,.96); color:#e5e7eb; border:1px solid rgba(148,163,184,.25); border-radius:16px;">
      <div class="modal-header" style="border-color:rgba(148,163,184,.18);">
        <h5 class="modal-title fw-bold" id="uploadModalTitle">Subir archivos</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <form method="post" enctype="multipart/form-data">
        <div class="modal-body">
          <input type="hidden" name="action" value="upload">
          <input type="hidden" name="year" value="<?= (int)$YEAR ?>">
          <input type="hidden" name="csrf_year" value="<?= e($CSRF_YEAR) ?>">
          <input type="hidden" name="key" id="uploadKey" value="">

          <div class="mb-3">
            <label class="form-label fw-bold">Nombre / referencia</label>
            <input type="text" name="ref" class="form-control" placeholder="Ej: AFI <?= e((string)$YEAR) ?> / Acta / Orden / Observación">
            <div class="form-text text-light" style="opacity:.75">
              Si lo completás, el archivo se guarda con ese nombre. Si lo dejás vacío, se usa el nombre original.
            </div>
          </div>

          <div class="mb-2">
            <label class="form-label fw-bold">Archivos (Excel/PDF)</label>
            <input type="file" name="files[]" class="form-control" multiple
                   accept=".xlsx,.xls,.pdf,application/pdf,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel">
            <div class="form-text text-light" style="opacity:.75">
              Permitidos: .xlsx, .xls, .pdf (máx 25MB c/u).<br>
              Límites servidor: upload_max_filesize=<?= e($ini_upload) ?> · post_max_size=<?= e($ini_post) ?>
            </div>
          </div>
        </div>

        <div class="modal-footer" style="border-color:rgba(148,163,184,.18);">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-success fw-bold">Subir</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Agregar/Editar Tarjeta (AÑO) -->
<div class="modal fade" id="cardModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="background:rgba(15,23,42,.96); color:#e5e7eb; border:1px solid rgba(148,163,184,.25); border-radius:16px;">
      <div class="modal-header" style="border-color:rgba(148,163,184,.18);">
        <h5 class="modal-title fw-bold" id="cardModalTitle">Agregar tarjeta</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <form method="post" id="cardModalForm">
        <div class="modal-body">
          <input type="hidden" name="year" value="<?= (int)$YEAR ?>">
          <input type="hidden" name="csrf_year" value="<?= e($CSRF_YEAR) ?>">
          <input type="hidden" name="action" id="cardAction" value="add_card">
          <input type="hidden" name="id" id="cardId" value="">

          <div class="mb-3">
            <label class="form-label fw-bold">Nombre de la tarjeta</label>
            <input type="text" name="title" id="cardTitle" class="form-control" placeholder="Ej: AFI <?= e((string)$YEAR) ?>" required>
          </div>

          <div class="mb-3">
            <label class="form-label fw-bold">Descripción (opcional)</label>
            <input type="text" name="sub" id="cardSub" class="form-control" placeholder="Ej: Evaluación física integral / documentación del ejercicio">
          </div>

          <div class="mb-1">
            <label class="form-label fw-bold">Ícono (opcional)</label>
            <input type="text" name="icon" id="cardIcon" class="form-control" placeholder="Ej: 📌 / 🧾 / 🪖">
            <div class="form-text text-light" style="opacity:.75">Podés poner un emoji. Si lo dejás vacío, usa 📌.</div>
          </div>
        </div>

        <div class="modal-footer" style="border-color:rgba(148,163,184,.18);">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-success fw-bold" id="cardSaveBtn">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const sharedScrollKey = 'pafb_shared_scroll';
  const sharedNavLinks = document.querySelectorAll('.js-shared-nav');
  const sharedBrowser = document.getElementById('shared-browser');

  if (sharedBrowser && window.location.hash === '#shared-browser') {
    const rawSavedScroll = sessionStorage.getItem(sharedScrollKey);
    if (rawSavedScroll) {
      const savedScroll = parseInt(rawSavedScroll, 10);
      if (!Number.isNaN(savedScroll)) {
        window.requestAnimationFrame(() => window.scrollTo({ top: savedScroll, behavior: 'auto' }));
      }
    } else {
      window.requestAnimationFrame(() => {
        sharedBrowser.scrollIntoView({ block: 'start', behavior: 'auto' });
      });
    }
  }

  sharedNavLinks.forEach(link => {
    link.addEventListener('click', () => {
      sessionStorage.setItem(sharedScrollKey, String(window.scrollY));
    });
  });

  // Confirm delete year (storage)
  document.querySelectorAll('form.js-delete-year').forEach(form => {
    form.addEventListener('submit', (ev) => {
      ev.preventDefault();
      const year = form.getAttribute('data-year') || form.querySelector('input[name="year"]')?.value || '';
      Swal.fire({
        title: 'Confirmar eliminación',
        html: `
          <div style="text-align:left">
            <div>¿Querés eliminar el año <b>${year}</b>?</div>
            <div class="mt-2">Se borrará la carpeta en storage:</div>
            <div><code><?= e(str_replace('\\', '/', $PAFB_ROOT_ABS)) ?>/${year}/</code></div>
            <div class="mt-2" style="opacity:.9">Esta acción no se puede deshacer.</div>
          </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
        focusCancel: true,
        confirmButtonColor: '#ef4444'
      }).then((result) => {
        if (result.isConfirmed) form.submit();
      });
    });
  });

  // DOCS upload modal
  const uploadDocModalEl = document.getElementById('uploadDocModal');
  if (uploadDocModalEl) {
    const uploadDocModal = new bootstrap.Modal(uploadDocModalEl);
    const keyInput = document.getElementById('uploadDocKey');
    const titleEl  = document.getElementById('uploadDocTitle');

    document.querySelectorAll('.js-open-upload-doc').forEach(btn => {
      btn.addEventListener('click', () => {
        const k = btn.getAttribute('data-doc') || '';
        const t = btn.getAttribute('data-title') || 'Subir documentos';
        keyInput.value = k;
        titleEl.textContent = `Subir PDF(s) · ${t}`;
        const form = uploadDocModalEl.querySelector('form');
        if (form) form.reset();
        uploadDocModal.show();
      });
    });
  }

  // Delete DOC file
  document.querySelectorAll('form.js-delete-doc-file').forEach(form => {
    form.addEventListener('submit', (ev) => {
      ev.preventDefault();
      const name = form.getAttribute('data-name') || 'archivo';
      const ref  = form.getAttribute('data-ref') || '';
      Swal.fire({
        title: 'Eliminar archivo',
        html: `
          <div style="text-align:left">
            <div>¿Eliminar <b>${name}</b>?</div>
            ${ref ? `<div class="mt-2"><b>Ref:</b> ${ref}</div>` : ``}
            <div class="mt-2" style="opacity:.9">Esta acción no se puede deshacer.</div>
          </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
        focusCancel: true,
        confirmButtonColor: '#ef4444'
      }).then((result) => {
        if (result.isConfirmed) form.submit();
      });
    });
  });

  // YEAR upload modal
  const uploadModalEl = document.getElementById('uploadModal');
  if (uploadModalEl) {
    const uploadModal = new bootstrap.Modal(uploadModalEl);
    const uploadTitleEl = document.getElementById('uploadModalTitle');
    const uploadKeyInput = document.getElementById('uploadKey');

    document.querySelectorAll('.js-open-upload').forEach(btn => {
      btn.addEventListener('click', () => {
        const key = btn.getAttribute('data-key') || '';
        const t   = btn.getAttribute('data-title') || 'Subir archivos';
        uploadKeyInput.value = key;
        uploadTitleEl.textContent = `Subir archivos · ${t}`;
        // reset inputs del modal
        const form = uploadModalEl.querySelector('form');
        if (form) form.reset();
        uploadModal.show();
      });
    });
  }

  // Card modal (add/edit)
  const cardModalEl = document.getElementById('cardModal');
  if (cardModalEl) {
    const cardModal = new bootstrap.Modal(cardModalEl);
    const cardModalTitle = document.getElementById('cardModalTitle');
    const cardAction = document.getElementById('cardAction');
    const cardId = document.getElementById('cardId');
    const cardTitle = document.getElementById('cardTitle');
    const cardSub = document.getElementById('cardSub');
    const cardIcon = document.getElementById('cardIcon');
    const cardSaveBtn = document.getElementById('cardSaveBtn');

    const btnAddCard = document.getElementById('btnAddCard');
    if (btnAddCard) {
      btnAddCard.addEventListener('click', () => {
        cardModalTitle.textContent = 'Agregar tarjeta';
        cardAction.value = 'add_card';
        cardId.value = '';
        cardTitle.value = '';
        cardSub.value = '';
        cardIcon.value = '';
        cardSaveBtn.textContent = 'Crear';
        cardModal.show();
      });
    }

    document.querySelectorAll('.js-edit-card').forEach(btn => {
      btn.addEventListener('click', () => {
        cardModalTitle.textContent = 'Editar tarjeta';
        cardAction.value = 'edit_card';
        cardId.value = btn.getAttribute('data-id') || '';
        cardTitle.value = btn.getAttribute('data-title') || '';
        cardSub.value = btn.getAttribute('data-sub') || '';
        cardIcon.value = btn.getAttribute('data-icon') || '';
        cardSaveBtn.textContent = 'Guardar cambios';
        cardModal.show();
      });
    });
  }

  // Delete file (SweetAlert2)
  document.querySelectorAll('form.js-delete-file').forEach(form => {
    form.addEventListener('submit', (ev) => {
      ev.preventDefault();
      const orig = form.getAttribute('data-orig') || 'archivo';
      Swal.fire({
        title: 'Confirmar eliminación',
        html: `
          <div style="text-align:left">
            <div>¿Eliminar este archivo?</div>
            <div class="mt-2"><b>${orig}</b></div>
            <div class="mt-2" style="opacity:.9">Se borrará del storage.</div>
          </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
        focusCancel: true,
        confirmButtonColor: '#ef4444'
      }).then((res) => {
        if (res.isConfirmed) form.submit();
      });
    });
  });

  // Delete card (SweetAlert2)
  document.querySelectorAll('form.js-delete-card').forEach(form => {
    form.addEventListener('submit', (ev) => {
      ev.preventDefault();
      const t = form.getAttribute('data-title') || 'la tarjeta';
      Swal.fire({
        title: 'Eliminar tarjeta',
        html: `
          <div style="text-align:left">
            <div>¿Seguro que querés eliminar esta tarjeta?</div>
            <div class="mt-2"><b>${t}</b></div>
            <div class="mt-2" style="opacity:.9">
              Se borrarán también todos los archivos guardados en esa tarjeta.
            </div>
          </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar tarjeta',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
        focusCancel: true,
        confirmButtonColor: '#ef4444'
      }).then((res) => {
        if (res.isConfirmed) form.submit();
      });
    });
  });
});
</script>

</body>
</html>
