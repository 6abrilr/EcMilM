<?php
// public/personal/personal.php — Área S-1 Personal
declare(strict_types=1);

// ========= MODO PRODUCCIÓN =========
$OFFLINE_MODE = false;
// ===================================

// Desde /ea/public/personal => /ea/auth y /ea/config
require_once __DIR__ . '/../../auth/bootstrap.php';
if (!$OFFLINE_MODE) {
  require_login();
}
require_once __DIR__ . '/../../config/db.php';

/** @var PDO $pdo */
function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function starts_with(string $haystack, string $needle): bool { return substr($haystack, 0, strlen($needle)) === $needle; }
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
function send_file_download(string $absPath, string $downloadName, string $mime): void {
  if (!is_file($absPath) || !is_readable($absPath)) {
    http_response_code(404);
    exit('Archivo no disponible.');
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
function scan_shared_dir(string $baseAbs, string $relative): array {
  $baseReal = realpath($baseAbs);
  if ($baseReal === false) return ['ok' => false, 'error' => 'No existe la carpeta compartida.', 'current' => '', 'entries' => []];
  $relative = normalize_rel_path($relative);
  if ($relative === null) return ['ok' => false, 'error' => 'Ruta inválida.', 'current' => '', 'entries' => []];
  $targetAbs = $relative === '' ? $baseReal : ($baseReal . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));
  $targetReal = realpath($targetAbs);
  if ($targetReal === false || !is_dir($targetReal) || !starts_with($targetReal, $baseReal)) {
    return ['ok' => false, 'error' => 'La carpeta solicitada no existe.', 'current' => $relative, 'entries' => []];
  }
  $items = @scandir($targetReal);
  if (!is_array($items)) return ['ok' => false, 'error' => 'No se pudo leer la carpeta compartida.', 'current' => $relative, 'entries' => []];
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

$user = function_exists('current_user') ? current_user() : null;

/* ==========================================================
   BASE WEB robusta
   - Estás en: /ea/public/personal/personal.php
   - Assets están en: /ea/assets/img
   ========================================================== */
$SELF_WEB        = (string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
$BASE_DIR_WEB    = rtrim(str_replace('\\','/', dirname($SELF_WEB)), '/');            // /ea/public/personal
$BASE_PUBLIC_WEB = rtrim(str_replace('\\','/', dirname($BASE_DIR_WEB)), '/');        // /ea/public
$BASE_APP_WEB    = rtrim(str_replace('\\','/', dirname($BASE_PUBLIC_WEB)), '/');     // /ea
$ASSET_WEB       = $BASE_APP_WEB . '/assets';                                        // /ea/assets
$ROOT_FS         = realpath(__DIR__ . '/../../');
$PERSONAL_ROOT_ABS = ($ROOT_FS !== false)
  ? ($ROOT_FS . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'unidades' . DIRECTORY_SEPARATOR . 'ecmilm' . DIRECTORY_SEPARATOR . 'PERSONAL')
  : '';

$IMG_BG  = $ASSET_WEB . '/img/fondo.png';
$ESCUDO  = $ASSET_WEB . '/img/ecmilm.png';
$FAVICON = $ASSET_WEB . '/img/ecmilm.png';

/* ===== Chat ===== */
$CHAT_FULL_URL = $BASE_PUBLIC_WEB . '/chat.php';
$CHAT_AJAX_URL = $BASE_PUBLIC_WEB . '/chat.php?ajax=1';
$CHAT_CSRF     = csrf_token();

if (isset($_GET['download']) && (string)($_GET['type'] ?? '') === 'shared') {
  $rel = normalize_rel_path((string)($_GET['path'] ?? ''));
  $base = realpath($PERSONAL_ROOT_ABS);
  if ($rel === null || $rel === '' || !$base) {
    http_response_code(400);
    exit('Ruta inválida.');
  }
  $abs = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
  $realAbs = realpath($abs);
  if (!$realAbs || !is_file($realAbs) || !starts_with($realAbs, $base)) {
    http_response_code(404);
    exit('Archivo no encontrado.');
  }
  $ext = strtolower((string)pathinfo($realAbs, PATHINFO_EXTENSION));
  send_file_download($realAbs, basename($realAbs), mime_for_ext($ext));
}

/* ==========================================================
   1) Obtener personal_id + unidad propia + rol admin
   ========================================================== */
$personalId   = 0;
$unidadPropia = 1;
$fullNameDB   = '';
$dniNorm      = '';

// Obtener DNI normalizado del usuario actual
if ($user && isset($user['dni'])) {
  $dniNorm = preg_replace('/\D+/', '', (string)$user['dni']);
} elseif ($user && isset($user['username'])) {
  $dniNorm = preg_replace('/\D+/', '', (string)$user['username']);
}

try {
  if ($dniNorm !== '') {
    $st = $pdo->prepare("
      SELECT id, unidad_id, CONCAT_WS(' ', grado, arma, apellido, nombre) AS nombre_comp
      FROM personal_unidad
      WHERE REPLACE(REPLACE(REPLACE(dni,'.',''),'-',''),' ','') = :dni
      LIMIT 1
    ");
    $st->execute([':dni' => $dniNorm]);
    if ($r = $st->fetch(PDO::FETCH_ASSOC)) {
      $personalId   = (int)($r['id'] ?? 0);
      $unidadPropia = (int)($r['unidad_id'] ?? $unidadPropia);
      $fullNameDB   = (string)($r['nombre_comp'] ?? '');
    }
  }
} catch (Throwable $e) {}

/* Obtener rol para permisos de escritura en chat */
$roleCodigo = 'USUARIO';
try {
  if ($personalId > 0) {
    $st = $pdo->prepare("
      SELECT r.codigo
      FROM personal_unidad pu
      INNER JOIN roles r ON r.id = pu.role_id
      WHERE pu.id = :pid
      LIMIT 1
    ");
    $st->execute([':pid' => $personalId]);
    $c = $st->fetchColumn();
    if (is_string($c) && $c !== '') $roleCodigo = strtoupper($c);
  }
} catch (Throwable $e) {}

$esSuperAdmin = ($roleCodigo === 'SUPERADMIN');
$esAdmin      = ($roleCodigo === 'ADMIN') || $esSuperAdmin;

/* Lista de módulos S-1. Agregá, remové o habilitá aquí según necesidad */
$s1_modules = [];
try {
    $st = $pdo->prepare("SELECT codigo,nombre,ruta FROM destino WHERE unidad_id = ? AND codigo LIKE 'S1%' AND activo = 1 ORDER BY id ASC");
    $st->execute([$unidadPropia]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    $iconMap = [
        'S1' => 'bi-person-vcard',
        'S2' => 'bi-bar-chart',
        'S3' => 'bi-briefcase',
        'S4' => 'bi-box-seam',
        'S5' => 'bi-cash-stack',
    ];
    foreach ($rows as $r) {
    $code = strtoupper(trim((string)$r['codigo']));
    $id   = strtolower(preg_replace('/[^a-z0-9]+/','', $code));

    $ruta = trim((string)($r['ruta'] ?? ''));

    // Normalizar a URL absoluta web
    if ($ruta !== '') {
        if ($ruta[0] !== '/') {
            $ruta = $BASE_PUBLIC_WEB . '/' . ltrim($ruta, '/');
        }
    } else {
        $ruta = '#';
    }

    // Caso especial S1 Personal
    if ($code === 'S1') {
        $ruta = $BASE_PUBLIC_WEB . '/personal/personal_lista.php';
    }

    $s1_modules[] = [
        'id'      => $id,
        'icon'    => $iconMap[$code] ?? 'bi-grid-3x3-gap',
        'title'   => $r['nombre'],
        'desc'    => '',
        'url'     => $ruta,
        'enabled' => true,
    ];
}
} catch (Throwable $e) {
    // si falla leer destino, seguimos con lista vacía
}

// módulos adicionales fijos
$s1_modules = array_merge($s1_modules, [
    [
        'id' => 'civil',
        'icon' => 'bi-person-workspace',
        'title' => 'Ingreso y egreso de personal civil',
        'desc'  => 'Importar padrón civil y registros diarios o semanales para calcular horas trabajadas.',
        'url'   => 'personal_civil_asistencia.php',
        'enabled' => true,
    ],
    [
        'id' => 'orden',
        'icon' => 'bi-file-earmark-text',
        'title' => 'Orden del día',
        'desc'  => 'Subir y consultar orden del día.',
        'url'   => 'orden_del_dia.php',
        'enabled' => false,
    ],
    [
        'id' => 'documentacion',
        'icon' => 'bi-folder2-open',
        'title' => 'Documentación S-1',
        'desc'  => 'Redacción/actualización/elevación de documentación.',
        'url'   => 'documentacion.php',
        'enabled' => false,
    ],
    [
        'id' => 'mesa',
        'icon' => 'bi-inbox',
        'title' => 'Mesa de entradas / salidas',
        'desc'  => 'Supervisión y control de plazos. (Próximamente)',
        'url'   => '#',
        'enabled' => false,
    ],
]);

/* ==========================================================
   Helpers DB (NO asume schema: verifica si existe tabla/col)
   ========================================================== */
function db_table_exists(PDO $pdo, string $table): bool {
  try {
    $sql = "SELECT 1
            FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name = :t
            LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([':t' => $table]);
    return (bool)$st->fetchColumn();
  } catch (Throwable $e) {
    return false;
  }
}

function db_column_exists(PDO $pdo, string $table, string $column): bool {
  try {
    $sql = "SELECT 1
            FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c
            LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([':t' => $table, ':c' => $column]);
    return (bool)$st->fetchColumn();
  } catch (Throwable $e) {
    return false;
  }
}

/* ==========================================================
   Resumen S-1 (robusto)
   - Total personal: personal_unidad
   - KPIs extra: si existen tablas (sin romper si no están)
   ========================================================== */
$totalPersonal = 0;

try {
  if (db_table_exists($pdo, 'personal_unidad')) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM personal_unidad");
    $totalPersonal = (int)($stmt->fetchColumn() ?: 0);
  }
} catch (Throwable $e) {
  $totalPersonal = 0;
}

/**
 * Distribución por situación:
 * - MODO DEMO hasta que definamos los campos reales.
 * - (No asumo columnas. Cuando vos me digas qué campo usar, lo conecto.)
 */
$personalActivo   = 0;
$personalLicencia = 0;
$personalBaja     = 0;

$personalOtros  = max($totalPersonal - ($personalActivo + $personalLicencia + $personalBaja), 0);

$porcActivo   = $totalPersonal > 0 ? round($personalActivo   * 100 / $totalPersonal, 1) : 0.0;
$porcLicencia = $totalPersonal > 0 ? round($personalLicencia * 100 / $totalPersonal, 1) : 0.0;
$porcBaja     = $totalPersonal > 0 ? round($personalBaja     * 100 / $totalPersonal, 1) : 0.0;

$porcGlobal   = $totalPersonal > 0 ? round($personalActivo * 100 / $totalPersonal, 1) : 0.0;

/* KPIs extra (si existen tablas reales) */
$kpiDocumentosTotal = null;   // int|null
$kpiTareasTotal      = null;   // int|null
$kpiEventosTotal     = null;   // int|null
$kpiPartesTotal     = null;   // int|null
$kpiAltasTotal      = null;   // int|null

try {
  if (db_table_exists($pdo, 'documentos')) {
    $st = $pdo->prepare("SELECT COUNT(*) FROM documentos WHERE unidad_id = ?");
    $st->execute([$unidadPropia]);
    $kpiDocumentosTotal = (int)($st->fetchColumn() ?: 0);
  }
} catch (Throwable $e) {
  $kpiDocumentosTotal = null;
}

try {
  if (db_table_exists($pdo, 'calendario_tareas')) {
    $st = $pdo->prepare("SELECT COUNT(*) FROM calendario_tareas WHERE unidad_id = ?");
    $st->execute([$unidadPropia]);
    $kpiTareasTotal = (int)($st->fetchColumn() ?: 0);
  }
} catch (Throwable $e) {
  $kpiTareasTotal = null;
}

try {
  if (db_table_exists($pdo, 'calendario_diario')) {
    $st = $pdo->prepare("SELECT COUNT(*) FROM calendario_diario WHERE unidad_id = ?");
    $st->execute([$unidadPropia]);
    $kpiEventosTotal = (int)($st->fetchColumn() ?: 0);
  }
} catch (Throwable $e) {
  $kpiEventosTotal = null;
}

try {
  // Tu esquema real menciona sanidad_partes_enfermo; lo trato como histórico.
  if (db_table_exists($pdo, 'sanidad_partes_enfermo')) {
    // Si existen columnas para distinguir “parte” vs “alta”, lo usamos; si no, mostramos total.
    $hasTieneParte = db_column_exists($pdo, 'sanidad_partes_enfermo', 'tiene_parte');
    if ($hasTieneParte) {
      // Asumo convención: tiene_parte=1 parte, tiene_parte=0 alta (si vos lo manejás distinto, lo ajustamos)
      $st1 = $pdo->query("SELECT COUNT(*) FROM sanidad_partes_enfermo WHERE tiene_parte = 1");
      $kpiPartesTotal = (int)($st1->fetchColumn() ?: 0);

      $st0 = $pdo->query("SELECT COUNT(*) FROM sanidad_partes_enfermo WHERE tiene_parte = 0");
      $kpiAltasTotal = (int)($st0->fetchColumn() ?: 0);
    } else {
      $st = $pdo->query("SELECT COUNT(*) FROM sanidad_partes_enfermo");
      $kpiPartesTotal = (int)($st->fetchColumn() ?: 0);
      $kpiAltasTotal  = null;
    }
  }
} catch (Throwable $e) {
  $kpiPartesTotal = null;
  $kpiAltasTotal  = null;
}
$sharedBrowseRel = normalize_rel_path((string)($_GET['shared'] ?? ''));
if ($sharedBrowseRel === null) $sharedBrowseRel = '';
$sharedState = scan_shared_dir($PERSONAL_ROOT_ABS, $sharedBrowseRel);
$sharedSegments = [];
if ($sharedState['ok'] && $sharedState['current'] !== '') {
  $acc = [];
  foreach (explode('/', (string)$sharedState['current']) as $seg) {
    $acc[] = $seg;
    $sharedSegments[] = ['name' => $seg, 'rel' => implode('/', $acc)];
  }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Personal · S-1</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= e($ASSET_WEB) ?>/css/theme-602.css">
<link rel="icon" href="<?= e($FAVICON) ?>">

<style>
  html,body{ height:100%; }
  body{
    margin:0;
    color:#e5e7eb;
    background:#000;
    font-family:system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,sans-serif;
  }

  .page-bg{
    position:fixed;
    inset:0;
    z-index:-2;
    pointer-events:none;
    background:
      linear-gradient(160deg, rgba(0,0,0,.85) 0%, rgba(0,0,0,.65) 55%, rgba(0,0,0,.85) 100%),
      url("<?= e($IMG_BG) ?>") center/cover no-repeat;
    background-attachment: fixed, fixed;
    filter:saturate(1.05);
  }
  .page-bg::before{
    content:"";
    position:absolute;
    inset:0;
    z-index:-1;
    opacity:.18;
    background-image:
      radial-gradient(1.4px 1.4px at 18% 22%, #9cd1ff 20%, transparent 60%),
      radial-gradient(1.2px 1.2px at 63% 48%, #b7ddff 20%, transparent 60%),
      radial-gradient(1.2px 1.2px at 82% 70%, #b7ddff 20%, transparent 60%),
      radial-gradient(1.6px 1.6px at 34% 76%, #cbe8ff 20%, transparent 60%),
      radial-gradient(1.1px 1.1px at 72% 16%, #a7d6ff 20%, transparent 60%);
    background-repeat:no-repeat;
    background-size: 1200px 800px, 1400px 900px, 1100px 900px, 1400px 1000px, 1300px 800px;
    background-position: 0 0, 30% 40%, 80% 60%, 10% 90%, 70% 10%;
  }

  .page-wrap{ padding:18px; position:relative; z-index:2; }
  .container-main{ max-width:1400px; margin:auto; }

  .panel{
    background:rgba(15,17,23,.94);
    border:1px solid rgba(148,163,184,.40);
    border-radius:18px;
    padding:18px 22px 22px;
    box-shadow:0 18px 40px rgba(0,0,0,.75), inset 0 1px 0 rgba(255,255,255,.05);
  }

  .panel-title{
    font-size:1.05rem;
    font-weight:900;
    margin-bottom:6px;
    display:flex;
    align-items:center;
    gap:.55rem;
  }
  .panel-title .badge{
    font-weight:800;
    letter-spacing:.04em;
  }

  .panel-sub{
    font-size:.86rem;
    color:#cbd5f5;
    margin-bottom:18px;
  }

  /* Header */
  .brand-hero{ padding-top:10px; padding-bottom:10px; position:relative; z-index:3; }
  .brand-hero .hero-inner{
    align-items:center;
    display:flex;
    justify-content:space-between;
    gap:12px;
  }
  .brand-logo{
    width:58px;
    height:58px;
    object-fit:contain;
    filter: drop-shadow(0 10px 18px rgba(0,0,0,.55));
  }
  .brand-title{ font-weight:900; font-size:1.15rem; line-height:1.1; color:#e5e7eb; }
  .brand-sub{ font-size:.9rem; color:#cbd5f5; opacity:.9; margin-top:2px; }

  .header-back{
    margin-left:auto;
    margin-right:17px; /* tu config preferida */
    margin-top:4px;
    display:flex;
    gap:8px;
  }

  /* Layout */
  .layout-s-row{ display:flex; flex-wrap:wrap; gap:18px; }
  .layout-s-sidebar{ flex:0 0 280px; max-width:360px; }
  .layout-s-main{ flex:1 1 0; min-width:0; }
  @media (max-width: 768px){
    .layout-s-sidebar, .layout-s-main{ flex:1 1 100%; max-width:100%; }
  }

  /* Sidebar */
  .s-sidebar-box{
    background:rgba(15,23,42,.95);
    border-radius:16px;
    border:1px solid rgba(148,163,184,.45);
    padding:14px 14px 10px;
    box-shadow:0 10px 28px rgba(0,0,0,.75);
  }
  .s-sidebar-title{
    font-size:.88rem;
    font-weight:800;
    letter-spacing:.05em;
    text-transform:uppercase;
    color:#9ca3af;
    margin-bottom:10px;
    display:flex;
    align-items:center;
    gap:.5rem;
  }

  .accordion-s .accordion-item{ background:transparent; border:none; border-radius:12px; margin-bottom:6px; overflow:hidden; }
  .accordion-s .accordion-button{
    background:radial-gradient(circle at left, rgba(34,197,94,.35), transparent 60%);
    border:none;
    color:#e5e7eb;
    font-size:.86rem;
    font-weight:800;
    padding:.55rem .75rem;
    box-shadow:0 6px 14px rgba(0,0,0,.65);
  }
  .accordion-s .accordion-button:not(.collapsed){
    background:radial-gradient(circle at left, rgba(34,197,94,.5), transparent 70%);
    color:#ecfdf5;
  }
  .accordion-s .accordion-body{
    background:rgba(15,23,42,.96);
    font-size:.84rem;
    color:#cbd5f5;
    border-top:1px solid rgba(148,163,184,.35);
  }

  .gest-btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:.45rem;
    padding:.45rem 1.1rem;
    border-radius:999px;
    border:none;
    font-size:.82rem;
    font-weight:900;
    text-decoration:none;
    background:#22c55e;
    color:#052e16;
    box-shadow:0 8px 22px rgba(22,163,74,.7);
  }
  .gest-btn:hover{ background:#4ade80; color:#052e16; }
  .gest-btn.disabled,
  .gest-btn[aria-disabled="true"]{
    opacity:.45;
    pointer-events:none;
    filter:grayscale(.4);
  }

  .s-main-text{ font-size:.9rem; color:#cbd5f5; }

  /* KPIs */
  .s-kpi-grid{ display:flex; flex-wrap:wrap; gap:10px; margin-top:10px; }
  .s-kpi-card{
    flex:1 1 200px;
    min-width:180px;
    background:rgba(15,23,42,.96);
    border-radius:14px;
    border:1px solid rgba(148,163,184,.45);
    padding:10px 12px;
    font-size:.78rem;
  }
  .s-kpi-title{ text-transform:uppercase; letter-spacing:.06em; color:#9ca3af; font-weight:800; margin-bottom:4px; }
  .s-kpi-main{ font-size:1.05rem; font-weight:900; display:flex; align-items:center; gap:.45rem; }
  .s-kpi-sub{ font-size:.78rem; color:#cbd5f5; }
  .progress{ background:rgba(15,23,42,.9); }
  .shared-panel{ display:none; }
  .shared-toolbar{
    display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;
    margin-bottom:12px;
  }
  .shared-path{
    display:flex; flex-wrap:wrap; gap:6px; align-items:center; font-size:.86rem; color:#cbd5e1;
  }
  .shared-path a{ color:#7dd3fc; text-decoration:none; }
  .shared-table{ width:100%; border-collapse:collapse; overflow:hidden; border-radius:12px; }
  .shared-table th, .shared-table td{
    padding:10px 12px; border-bottom:1px solid rgba(148,163,184,.16); text-align:left; font-size:.84rem;
  }
  .shared-table th{ color:#93c5fd; background:rgba(30,41,59,.92); font-weight:800; }
  .shared-table td{ color:#e5e7eb; }
  .shared-table tr:hover td{ background:rgba(59,130,246,.08); }
  .shared-name{ display:flex; align-items:center; gap:10px; min-width:0; }
  .shared-icon{ width:28px; text-align:center; font-size:1rem; }
  .shared-link{
    color:#e5e7eb; text-decoration:none; font-weight:700;
    overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
  }
  .shared-link:hover{ color:#7dd3fc; }
  .shared-meta{ color:#94a3b8; font-size:.75rem; }
  .shared-empty{
    padding:18px; border:1px dashed rgba(148,163,184,.24); border-radius:12px; color:#94a3b8;
  }
  .quick-access-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit, minmax(210px, 1fr));
    gap:12px;
    margin:16px 0 18px;
  }
  .quick-access-card{
    display:flex;
    align-items:flex-start;
    gap:12px;
    padding:14px 16px;
    border-radius:16px;
    text-decoration:none;
    background:rgba(15,23,42,.82);
    border:1px solid rgba(148,163,184,.28);
    color:#e5e7eb;
    transition:transform .18s ease, border-color .18s ease, box-shadow .18s ease;
  }
  .quick-access-card:hover{
    transform:translateY(-2px);
    border-color:rgba(34,197,94,.45);
    box-shadow:0 14px 30px rgba(0,0,0,.32);
    color:#f8fafc;
  }
  .quick-access-icon{
    width:48px;
    height:48px;
    flex:0 0 48px;
    display:flex;
    align-items:center;
    justify-content:center;
    border-radius:14px;
    background:rgba(34,197,94,.18);
    color:#bbf7d0;
    font-size:1.35rem;
  }
  .quick-access-title{
    font-size:.92rem;
    font-weight:900;
    margin-bottom:2px;
  }
  .quick-access-desc{
    font-size:.8rem;
    color:#cbd5f5;
    line-height:1.45;
  }

  /* Donut */
  .s-pie-wrapper{ display:flex; justify-content:center; align-items:center; padding:8px 0; }
  .s-pie{
    width:220px;
    aspect-ratio:1 / 1;
    border-radius:50%;
    position:relative;
    box-shadow:0 16px 35px rgba(0,0,0,.9);
  }
  .s-pie-inner{
    position:absolute;
    inset:20px;
    border-radius:50%;
    background:rgba(15,23,42,.98);
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    text-align:center;
  }
  .s-pie-perc{ font-size:1.6rem; font-weight:900; }
  .s-pie-label{
    font-size:.75rem;
    color:#9ca3af;
    text-transform:uppercase;
    letter-spacing:.09em;
    margin-top:4px;
  }

  /* Caja doctrinaria S-1 */
  .s1-doctrina{
    background:rgba(2,6,23,.55);
    border:1px dashed rgba(148,163,184,.45);
    border-radius:14px;
    padding:12px 12px;
    margin-top:10px;
  }
  .s1-doctrina h6{
    margin:0 0 8px;
    font-weight:900;
    font-size:.88rem;
    color:#e5e7eb;
    display:flex;
    align-items:center;
    gap:.45rem;
  }
  .s1-doctrina ul{
    margin:0;
    padding-left:18px;
    color:#cbd5f5;
    font-size:.84rem;
  }
  .s1-doctrina li{ margin:6px 0; }
</style>
<link rel="stylesheet" href="<?= e($BASE_PUBLIC_WEB) ?>/chat.css">
</head>
<body>

<div class="page-bg"></div>

<header class="brand-hero">
  <div class="hero-inner container-main">
    <div class="d-flex align-items-center gap-3">
      <img class="brand-logo"
     src="<?= e($ESCUDO) ?>"
     alt="EC MIL M"
     onerror='this.onerror=null; this.src="<?= e($ASSET_WEB) ?>/img/EA.png";'>
      <div>
        <div class="brand-title">Escuela Militar de Montaña</div>
        <div class="brand-sub">“La montaña nos une”</div>
      </div>
    </div>

    <div class="header-back">
      <a href="../inicio.php" class="btn btn-success btn-sm" style="font-weight:700; padding:.35rem .9rem;">
        <i class="bi bi-house-door"></i> Inicio
      </a>
    </div>
  </div>
</header>

<div class="page-wrap">
  <div class="container-main">
    <div class="panel">
      <div class="panel-title">
        <i class="bi bi-people-fill"></i>
        Área S-1 · Personal
        <span class="badge text-bg-success">PM</span>
      </div>

      <div class="panel-sub">
        Seleccioná el módulo correspondiente. Este panel consolida información de personal y deja
        preparados accesos a funciones típicas de S-1 (documentación, mesa de entradas/salidas, disciplina).
      </div>

      <div class="quick-access-grid">
        <a class="quick-access-card" href="./personalcarpetacompartida.php">
          <div class="quick-access-icon"><i class="bi bi-folder2-open"></i></div>
          <div>
            <div class="quick-access-title">Carpeta compartida</div>
            <div class="quick-access-desc">Entrá al explorador de archivos de Personal desde una vista separada.</div>
          </div>
        </a>
      </div>

      <div class="layout-s-row">
        <!-- Sidebar -->
        <aside class="layout-s-sidebar">
          <div class="s-sidebar-box">
            <div class="s-sidebar-title"><i class="bi bi-grid-3x3-gap"></i> Módulos S-1</div>

            <div class="accordion accordion-s" id="accordionS1">
<?php
  $first = true;
  foreach ($s1_modules as $m) {
    ?>
    <div class="accordion-item">
      <h2 class="accordion-header" id="s1-h-<?= e($m['id']) ?>">
        <button class="accordion-button <?= $first ? '' : 'collapsed' ?>"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#s1-<?= e($m['id']) ?>"
                aria-expanded="<?= $first ? 'true' : 'false' ?>"
                aria-controls="s1-<?= e($m['id']) ?>">
          <i class="bi <?= e($m['icon']) ?> me-1"></i> <?= e($m['title']) ?>
        </button>
      </h2>
      <div id="s1-<?= e($m['id']) ?>" class="accordion-collapse collapse <?= $first ? 'show' : '' ?>" aria-labelledby="s1-h-<?= e($m['id']) ?>" data-bs-parent="#accordionS1">
        <div class="accordion-body">
          <?= e($m['desc']) ?>
          <div class="mt-2">
            <?php if ($m['enabled']): ?>
              <a href="<?= e($m['url']) ?>" class="gest-btn"><i class="bi bi-box-arrow-in-right"></i> Entrar</a>
            <?php else: ?>
              <a href="#" class="gest-btn disabled" aria-disabled="true"><i class="bi bi-hourglass-split"></i> Próximamente</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <?php
    $first = false;
  }
?>
            </div><!-- /accordion -->
          </div>
        </aside>

        <!-- Main -->
        <section class="layout-s-main">
          <div class="row g-3 align-items-start">
            <div class="col-md-7">
              <div class="s-main-text">
                <p>
                  Este panel integra, en forma consolidada, la situación del <strong>personal</strong>.
                  El <strong>total</strong> se obtiene desde <code>personal_unidad</code>.
                </p>
                <p class="mb-2">
                  La distribución por “en servicio / licencia / baja” queda en <strong>modo demo</strong>
                  hasta que confirmemos el/los campos reales para calcularlo sin suposiciones.
                </p>
              </div>

              <div class="s1-doctrina">
                <h6><i class="bi bi-compass"></i> Qué hace S-1 (base doctrinaria)</h6>
                <ul>
                  <li>Responsabilidad primaria sobre planeamiento, organización, coordinación y control del personal bajo control militar directo.</li>
                  <li>Redacción/actualización/elevación de documentación según publicaciones vigentes.</li>
                  <li>Supervisión de mesa de entradas y salidas.</li>
                  <li>Secretaría del consejo de disciplina guarnicional (según Código de Disciplina).</li>
                  <li>Coordinación con S-3 para propuestas reglamentarias y lecciones aprendidas del área.</li>
                </ul>
              </div>

              <div class="s-kpi-grid mt-3">
                <div class="s-kpi-card">
                  <div class="s-kpi-title">Personal total</div>
                  <div class="s-kpi-main"><i class="bi bi-people"></i> <?= e($totalPersonal) ?></div>
                  <div class="s-kpi-sub">Incluye personal en todas las situaciones.</div>
                </div>

                <div class="s-kpi-card">
                  <div class="s-kpi-title">Documentos (sistema)</div>
                  <div class="s-kpi-main"><i class="bi bi-files"></i> <?= e($kpiDocumentosTotal ?? '—') ?></div>
                  <div class="s-kpi-sub">Se calcula si existe <code>documentos</code>.</div>
                </div>

                <div class="s-kpi-card">
                  <div class="s-kpi-title">Tareas (calendario)</div>
                  <div class="s-kpi-main"><i class="bi bi-list-task"></i> <?= e($kpiTareasTotal ?? '—') ?></div>
                  <div class="s-kpi-sub">Se calcula si existe <code>calendario_tareas</code>.</div>
                </div>

                <div class="s-kpi-card">
                  <div class="s-kpi-title">Eventos diarios</div>
                  <div class="s-kpi-main"><i class="bi bi-calendar-event"></i> <?= e($kpiEventosTotal ?? '—') ?></div>
                  <div class="s-kpi-sub">Se calcula si existe <code>calendario_diario</code>.</div>
                </div>

                <div class="s-kpi-card">
                  <div class="s-kpi-title">Partes enfermo (histórico)</div>
                  <div class="s-kpi-main"><i class="bi bi-clipboard-pulse"></i> <?= e($kpiPartesTotal ?? '—') ?></div>
                  <div class="s-kpi-sub">Se calcula si existe <code>sanidad_partes_enfermo</code>.</div>
                </div>

                <div class="s-kpi-card">
                  <div class="s-kpi-title">Altas (histórico)</div>
                  <div class="s-kpi-main"><i class="bi bi-check2-circle"></i> <?= e($kpiAltasTotal ?? '—') ?></div>
                  <div class="s-kpi-sub">Solo si puede distinguirse por columna.</div>
                </div>

                <div class="s-kpi-card">
                  <div class="s-kpi-title">En servicio (demo)</div>
                  <div class="s-kpi-main"><?= e($personalActivo) ?></div>
                  <div class="s-kpi-sub"><?= e($porcActivo) ?>% del total.</div>
                  <div class="progress mt-1" style="height:6px;">
                    <div class="progress-bar bg-success" style="width:<?= e($porcActivo) ?>%"></div>
                  </div>
                </div>

                <div class="s-kpi-card">
                  <div class="s-kpi-title">En licencia (demo)</div>
                  <div class="s-kpi-main"><?= e($personalLicencia) ?></div>
                  <div class="s-kpi-sub"><?= e($porcLicencia) ?>% del total.</div>
                  <div class="progress mt-1" style="height:6px;">
                    <div class="progress-bar bg-success" style="width:<?= e($porcLicencia) ?>%"></div>
                  </div>
                </div>

                <div class="s-kpi-card">
                  <div class="s-kpi-title">Bajas (demo)</div>
                  <div class="s-kpi-main"><?= e($personalBaja) ?></div>
                  <div class="s-kpi-sub"><?= e($porcBaja) ?>% del total.</div>
                  <div class="progress mt-1" style="height:6px;">
                    <div class="progress-bar bg-success" style="width:<?= e($porcBaja) ?>%"></div>
                  </div>
                </div>

              </div>
            </div>

            <div class="col-md-5">
              <div class="s-pie-wrapper">
  <div class="s-pie"
        style="background: conic-gradient(#22c55e 0 <?= e($porcGlobal) ?>%, rgba(30,64,175,.6) <?= e($porcGlobal) ?>% 100%);">
      <div class="s-pie-inner">
        <div class="s-pie-perc"><?= e($porcGlobal) ?>%</div>
        <div class="s-pie-label">Personal en servicio</div>
        <div style="font-size:.7rem; color:#9ca3af; margin-top:4px;">
          <?= e($personalActivo) ?>/<?= e($totalPersonal) ?> efectivos (demo)
        </div>
      </div>
    </div>
  </div>

              <div class="alert alert-dark mt-2" style="background:rgba(15,23,42,.9); border:1px solid rgba(148,163,184,.35); color:#cbd5f5;">
                <div style="font-weight:900; margin-bottom:6px;"><i class="bi bi-wrench-adjustable-circle"></i> Próximo paso</div>
                <div style="font-size:.86rem;">
                  Para dejar de lado el “demo” y calcular <b>en servicio / licencia / baja</b>, necesito que me indiques
                  qué campo/s en <code>personal_unidad</code> representan la situación de revista (nombre exacto).
                </div>
              </div>

            </div>

          </div><!-- /row -->
        </section>
      </div><!-- /layout row -->

      <div class="shared-panel" id="shared-browser">
        <div class="panel-title" style="margin-bottom:4px;">
          <i class="bi bi-folder2-open"></i>
          Carpeta compartida de Personal
        </div>
        <div class="panel-sub" style="margin-bottom:10px;">
          Acá podés ver todo lo que exista físicamente dentro de <code><?= e(str_replace('\\', '/', $PERSONAL_ROOT_ABS)) ?></code>,
          incluso si lo cargaron directamente desde la carpeta compartida.
        </div>

        <?php if (!$sharedState['ok']): ?>
          <div class="alert alert-warning mb-0"><?= e((string)$sharedState['error']) ?></div>
        <?php else: ?>
          <div class="shared-toolbar">
            <div class="shared-path">
              <span><b>Ruta:</b></span>
              <a class="js-shared-nav" href="<?= e($SELF_WEB) ?>#shared-browser">PERSONAL</a>
              <?php foreach ($sharedSegments as $seg): ?>
                <span>/</span>
                <a class="js-shared-nav" href="<?= e($SELF_WEB) ?>?shared=<?= e(rawurlencode((string)$seg['rel'])) ?>#shared-browser"><?= e((string)$seg['name']) ?></a>
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
                <a class="btn btn-outline-light btn-sm js-shared-nav" href="<?= e($SELF_WEB) ?><?= $parentRel !== '' ? ('?shared=' . e(rawurlencode($parentRel))) : '' ?>#shared-browser">Volver</a>
              <?php endif; ?>
              <a class="btn btn-outline-light btn-sm js-shared-nav" href="<?= e($SELF_WEB) ?>#shared-browser">Ir a raíz</a>
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
                      ? ($SELF_WEB . '?shared=' . rawurlencode($rel))
                      : ($SELF_WEB . '?download=1&type=shared&path=' . rawurlencode($rel));
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
                      <a class="btn btn-outline-info btn-sm <?= $isDir ? 'js-shared-nav' : '' ?>" <?= $isDir ? '' : 'target="_blank" rel="noopener"' ?> href="<?= e($isDir ? ($openHref . '#shared-browser') : $openHref) ?>">
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

    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Launcher mínimo -->
<div id="chatLauncher" class="chat-launcher show">
  <div class="chat-launcher-title">Chat interno</div>
  <span id="chatLauncherBadge" class="chat-total-badge">0</span>
</div>

<!-- Dock fijo -->
<div id="chatDock" class="chat-dock chat-hidden">
  <div class="chat-dock-head">
    <div class="chat-dock-title-wrap">
      <div class="chat-dock-title">Chat interno</div>
      <span id="chatDockBadge" class="chat-total-badge">0</span>
    </div>

    <div class="chat-dock-actions">
      <a id="chatOpenFull" href="<?= e($CHAT_FULL_URL) ?>" class="chat-btn chat-btn-open">Agrandar</a>
      <button type="button" id="chatCloseBtn" class="chat-btn chat-btn-close">Cerrar</button>
    </div>
  </div>

  <div class="chat-dock-body">
    <div class="chat-conv-pane">
      <div class="chat-conv-pane-head">Conversaciones</div>
      <div id="chatConvList" class="chat-conv-list">
        <div class="chat-empty">Cargando...</div>
      </div>
    </div>

    <div class="chat-thread">
      <div class="chat-thread-head">
        <div id="chatThreadTitle" class="chat-thread-title">Chat General</div>
        <div id="chatThreadSub" class="chat-thread-sub">Mensajes generales de la unidad</div>
      </div>

      <div id="chatMessages" class="chat-messages">
        <div class="chat-empty">Cargando mensajes...</div>
      </div>

      <div id="chatReadonly" class="chat-readonly">
        Solo ADMIN y SUPERADMIN pueden escribir en el chat general.
      </div>

      <form id="chatCompose" class="chat-compose">
        <div class="chat-compose-row">
          <input type="text" id="chatInput" class="form-control" maxlength="4000" placeholder="Escribí un mensaje...">
          <button type="submit" class="btn btn-success btn-sm" style="font-weight:800;">Enviar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function(){
  const CHAT_AJAX_URL = <?= json_encode($CHAT_AJAX_URL, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  const CHAT_FULL_URL = <?= json_encode($CHAT_FULL_URL, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  const CSRF_TOKEN    = <?= json_encode($CHAT_CSRF, JSON_UNESCAPED_UNICODE) ?>;
  const CAN_WRITE_GENERAL = <?= $esAdmin ? 'true' : 'false' ?>;
  const STORAGE_KEY = 'ea_chat_seen_<?= (int)$personalId ?>';

  const dock = document.getElementById('chatDock');
  const launcher = document.getElementById('chatLauncher');
  const launcherBadge = document.getElementById('chatLauncherBadge');
  const dockBadge = document.getElementById('chatDockBadge');
  const closeBtn = document.getElementById('chatCloseBtn');

  const convList = document.getElementById('chatConvList');
  const messagesBox = document.getElementById('chatMessages');
  const threadTitle = document.getElementById('chatThreadTitle');
  const threadSub = document.getElementById('chatThreadSub');
  const compose = document.getElementById('chatCompose');
  const input = document.getElementById('chatInput');
  const readonlyBox = document.getElementById('chatReadonly');
  const openFull = document.getElementById('chatOpenFull');

  const state = {
    conversations: [],
    selectedConversationId: null,
    unreadMap: {},
    seenMap: {},
    baselineLoaded: false,
    pollingHandle: null
  };

  try {
    state.seenMap = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}') || {};
  } catch (e) {
    state.seenMap = {};
  }

  function saveSeenMap() {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(state.seenMap));
    } catch (e) {}
  }

  function escapeHtml(str) {
    return String(str ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function apiGet(action, params = {}) {
    const qs = new URLSearchParams({ action, ...params });
    return fetch(`${CHAT_AJAX_URL}&${qs.toString()}`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).then(r => r.json());
  }

  function apiPost(action, params = {}) {
    const body = new URLSearchParams({ action, _csrf: CSRF_TOKEN, ...params });
    return fetch(CHAT_AJAX_URL, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: body.toString()
    }).then(r => r.json());
  }

  function selectedConversation() {
    return state.conversations.find(c => Number(c.id) === Number(state.selectedConversationId)) || null;
  }

  function updateUnreadBadges() {
    const total = Object.keys(state.unreadMap).length;

    launcherBadge.textContent = String(total);
    dockBadge.textContent = String(total);

    launcherBadge.classList.toggle('show', total > 0);
    dockBadge.classList.toggle('show', total > 0);

    document.title = total > 0 ? `(${total}) Personal` : 'Personal';
  }

  function processUnread() {
    if (!state.baselineLoaded) {
      state.conversations.forEach(c => {
        state.seenMap[c.id] = Number(c.last_message_id || 0);
      });
      state.baselineLoaded = true;
      saveSeenMap();
      updateUnreadBadges();
      return;
    }

    state.conversations.forEach(c => {
      const currentId = Number(c.last_message_id || 0);
      const seenId = Number(state.seenMap[c.id] || 0);
      const isSelected = Number(c.id) === Number(state.selectedConversationId);

      if (currentId > seenId) {
        if (isSelected) {
          state.seenMap[c.id] = currentId;
          delete state.unreadMap[c.id];
        } else if (!c.last_from_me && currentId > 0) {
          state.unreadMap[c.id] = true;
        } else {
          state.seenMap[c.id] = currentId;
        }
      }
    });

    saveSeenMap();
    updateUnreadBadges();
  }

  function markConversationSeen(conversationId) {
    const c = state.conversations.find(x => Number(x.id) === Number(conversationId));
    if (!c) return;

    state.seenMap[conversationId] = Number(c.last_message_id || 0);
    delete state.unreadMap[conversationId];
    saveSeenMap();
    updateUnreadBadges();
  }

  function setThreadHeader() {
    const c = selectedConversation();
    if (!c) {
      threadTitle.textContent = 'Sin conversación';
      threadSub.textContent = '';
      compose.classList.remove('chat-hidden');
      readonlyBox.classList.remove('show');
      openFull.href = CHAT_FULL_URL;
      return;
    }

    threadTitle.textContent = c.title || 'Conversación';
    threadSub.textContent = c.type === 'general'
      ? 'Mensajes generales de la unidad'
      : (c.is_self ? 'Tus notas personales' : 'Conversación privada');

    const readOnly = (c.type === 'general' && !CAN_WRITE_GENERAL);
    compose.classList.toggle('chat-hidden', readOnly);
    readonlyBox.classList.toggle('show', readOnly);
    openFull.href = CHAT_FULL_URL;
  }

  function renderConversations() {
    if (!state.conversations.length) {
      convList.innerHTML = `<div class="chat-empty">No hay conversaciones.</div>`;
      return;
    }

    convList.innerHTML = state.conversations.map(c => {
      const typeText = c.type === 'general' ? 'GENERAL' : (c.is_self ? 'NOTAS' : 'PRIVADO');
      const isNew = !!state.unreadMap[c.id];

      return `
        <button type="button"
                class="chat-conv-item ${Number(c.id) === Number(state.selectedConversationId) ? 'active' : ''}"
                data-id="${Number(c.id)}">
          <div class="chat-conv-top">
            <div class="chat-conv-name">${escapeHtml(c.title || 'Conversación')}</div>
            <div style="display:flex; align-items:center;">
              <span class="chat-conv-badge ${isNew ? 'show' : ''}">NUEVO</span>
              <span class="chat-conv-type">${escapeHtml(typeText)}</span>
            </div>
          </div>
          <div class="chat-conv-last">${escapeHtml(c.last_message || 'Sin mensajes todavía')}</div>
        </button>
      `;
    }).join('');

    convList.querySelectorAll('.chat-conv-item').forEach(btn => {
      btn.addEventListener('click', async () => {
        state.selectedConversationId = Number(btn.dataset.id);
        markConversationSeen(state.selectedConversationId);
        renderConversations();
        setThreadHeader();
        await loadMessages(true);
      });
    });
  }

  async function loadConversations(preferId = null) {
    const r = await apiGet('list_conversations');
    if (!r.ok) {
      convList.innerHTML = `<div class="chat-empty">No se pudieron cargar las conversaciones.</div>`;
      return;
    }

    state.conversations = Array.isArray(r.items) ? r.items : [];

    if (preferId) {
      state.selectedConversationId = Number(preferId);
    } else if (!state.selectedConversationId && state.conversations.length) {
      state.selectedConversationId = Number(state.conversations[0].id);
    } else {
      const exists = state.conversations.some(c => Number(c.id) === Number(state.selectedConversationId));
      if (!exists && state.conversations.length) {
        state.selectedConversationId = Number(state.conversations[0].id);
      }
    }

    processUnread();
    renderConversations();
    setThreadHeader();
  }

  async function loadMessages(scrollBottom = false) {
    if (!state.selectedConversationId) return;

    const r = await apiGet('get_messages', {
      conversation_id: state.selectedConversationId
    });

    if (!r.ok) {
      messagesBox.innerHTML = `<div class="chat-empty">${escapeHtml(r.error || 'No se pudieron cargar los mensajes.')}</div>`;
      return;
    }

    const items = Array.isArray(r.items) ? r.items : [];
    const viewItems = items.slice(-25);

    if (!viewItems.length) {
      messagesBox.innerHTML = `<div class="chat-empty">No hay mensajes todavía.</div>`;
      markConversationSeen(state.selectedConversationId);
      return;
    }

    messagesBox.innerHTML = viewItems.map(m => `
      <div class="msg-row ${m.mine ? 'me' : 'other'}">
        <div class="msg-meta">${escapeHtml(m.mine ? 'Yo' : m.author)} · ${escapeHtml(m.created_hm || '')}</div>
        <div class="msg-bubble ${m.mine ? 'me' : 'other'}">${escapeHtml(m.message || '')}</div>
      </div>
    `).join('');

    markConversationSeen(state.selectedConversationId);
    renderConversations();

    if (scrollBottom) {
      messagesBox.scrollTop = messagesBox.scrollHeight;
    }
  }

  async function sendMessage(ev) {
    ev.preventDefault();

    const text = input.value.trim();
    const c = selectedConversation();
    if (!c || !text) return;

    const r = await apiPost('send_message', {
      conversation_id: c.id,
      message: text
    });

    if (!r.ok) {
      alert(r.error || 'No se pudo enviar el mensaje.');
      return;
    }

    input.value = '';
    await loadMessages(true);
    await loadConversations(c.id);
  }

  function closeDock() {
    dock.classList.add('chat-hidden');
    launcher.classList.remove('chat-hidden');
    launcher.classList.add('show');
  }

  function openDock() {
    launcher.classList.remove('show');
    launcher.classList.add('chat-hidden');
    dock.classList.remove('chat-hidden');
  }

  closeBtn.addEventListener('click', closeDock);
  launcher.addEventListener('click', openDock);
  compose.addEventListener('submit', sendMessage);

  document.addEventListener('keydown', function(ev){
    if (ev.key === 'Escape' && !dock.classList.contains('chat-hidden')) {
      closeDock();
    }
  });

  loadConversations().then(() => loadMessages(true));

  state.pollingHandle = setInterval(async () => {
    const current = state.selectedConversationId;
    await loadConversations(current);
    await loadMessages(false);
  }, 5000);
})();

(function () {
  const sharedScrollKey = 'personal_shared_scroll';
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
})();
</script>
</body>
</html>
