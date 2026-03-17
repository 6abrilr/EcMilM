<?php
// ea/public/operaciones/operaciones.php — Área S-3 Operaciones (Sección III - 2.023)
declare(strict_types=1);

// ========= MODO PRODUCCIÓN =========
$OFFLINE_MODE = false;
// ===================================

require_once __DIR__ . '/../../auth/bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/operaciones_helper.php';

if (!$OFFLINE_MODE) {
    operaciones_require_login();
}

$esAdmin      = operaciones_es_admin($pdo);
$modoResumido = !$esAdmin;
$user         = operaciones_current_user();
$areaCode     = operaciones_get_user_area_code($pdo);

/** Escapa output HTML de forma segura */
function e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
function starts_with(string $haystack, string $needle): bool {
    return substr($haystack, 0, strlen($needle)) === $needle;
}
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

/* ==========================================================
   BASE WEB robusta
   ========================================================== */
$SELF_WEB        = (string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
$BASE_DIR_WEB    = rtrim(str_replace('\\', '/', dirname($SELF_WEB)), '/');
$BASE_PUBLIC_WEB = rtrim(str_replace('\\', '/', dirname($BASE_DIR_WEB)), '/');
$BASE_APP_WEB    = rtrim(str_replace('\\', '/', dirname($BASE_PUBLIC_WEB)), '/');
$ASSET_WEB       = $BASE_APP_WEB . '/assets';
$ROOT_FS         = realpath(__DIR__ . '/../../');
$OPERACIONES_ROOT_ABS = ($ROOT_FS !== false)
    ? ($ROOT_FS . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'unidades' . DIRECTORY_SEPARATOR . 'ecmilm' . DIRECTORY_SEPARATOR . 'OPERACIONES')
    : '';

$IMG_BG  = $ASSET_WEB . '/img/fondo.png';
$ESCUDO  = $ASSET_WEB . '/img/ecmilm.png';
$FAVICON = $ESCUDO;

/* ===== Chat ===== */
$CHAT_FULL_URL = $BASE_PUBLIC_WEB . '/chat.php';
$CHAT_AJAX_URL = $BASE_PUBLIC_WEB . '/chat.php?ajax=1';
$CHAT_CSRF     = csrf_token();

if (isset($_GET['download']) && (string)($_GET['type'] ?? '') === 'shared') {
    $rel = normalize_rel_path((string)($_GET['path'] ?? ''));
    $base = realpath($OPERACIONES_ROOT_ABS);
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
   Datos del usuario actual
   ========================================================== */
$personalId   = 0;
$unidadPropia = 1;
$fullNameDB   = '';
$dniNorm      = '';

if ($user) {
    $dniNorm = preg_replace('/\D+/', '', (string)($user['dni'] ?? $user['username'] ?? ''));
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
            $personalId   = (int)($r['id']       ?? 0);
            $unidadPropia = (int)($r['unidad_id'] ?? $unidadPropia);
            $fullNameDB   = (string)($r['nombre_comp'] ?? '');
        }
    }
} catch (Throwable $ignored) {
    // No romper pantalla ante error de BD
}

/* ==========================================================
   KPIs S-3
   ========================================================== */
$totalCuadros = operaciones_safe_count($pdo, 'operaciones_educacion_cuadros');
$totalTropa   = operaciones_safe_count($pdo, 'operaciones_educacion_tropa');
$totalAdies   = operaciones_safe_count($pdo, 'operaciones_adiestramiento');
$totalTiro    = operaciones_safe_count($pdo, 'operaciones_tiro');

// Cumplidas: pendiente de campo real en BD
$hechasCuadros = 0;
$hechasTropa   = 0;
$hechasAdies   = 0;
$hechasTiro    = 0;

$totalActiv  = $totalCuadros + $totalTropa + $totalAdies + $totalTiro;
$hechasActiv = $hechasCuadros + $hechasTropa + $hechasAdies + $hechasTiro;

/**
 * Calcula porcentaje evitando división por cero.
 */
function calcPorc(int $hechas, int $total): float {
    return $total > 0 ? round($hechas * 100 / $total, 1) : 0.0;
}

$porcCuadros = calcPorc($hechasCuadros, $totalCuadros);
$porcTropa   = calcPorc($hechasTropa,   $totalTropa);
$porcAdies   = calcPorc($hechasAdies,   $totalAdies);
$porcTiro    = calcPorc($hechasTiro,    $totalTiro);
$porcGlobal  = calcPorc($hechasActiv,   $totalActiv);

/* ==========================================================
   Definición de ejes doctrinarios (datos separados de HTML)
   ========================================================== */
$ejesS3 = [
    [
        'id'    => 's3-org',
        'icon'  => 'bi-diagram-2',
        'label' => 'Organización (CO / estados)',
        'desc'  => 'CO actualizado, ajustes, prioridades; estados de personal/armas/vehículos.',
        'href'  => null,
    ],
    [
        'id'    => 's3-cuadros',
        'icon'  => 'bi-mortarboard',
        'label' => 'Educación · Cuadros',
        'desc'  => 'PEU, órdenes de instrucción, ejercicios, MAPE, aulas/pistas/campos, visitas/inspecciones educativas.',
        'href'  => './operaciones_educacion_cuadros.php',
    ],
    [
        'id'    => 's3-tropa',
        'icon'  => 'bi-people',
        'label' => 'Educación · Tropa',
        'desc'  => 'Planes de educación de fracciones, análisis de partes, asesoramiento y control.',
        'href'  => './operaciones_educacion_tropa.php',
    ],
    [
        'id'    => 's3-adies',
        'icon'  => 'bi-heart-pulse',
        'label' => 'Adiestramiento físico-militar',
        'desc'  => 'Planificación y registro del adiestramiento físico-militar.',
        'href'  => './operaciones_adiestramiento.php',
    ],
    [
        'id'    => 's3-tiro',
        'icon'  => 'bi-bullseye',
        'label' => 'Tiro',
        'desc'  => 'Planes de tiro, resultados y seguimiento de calificaciones.',
        'href'  => './operaciones_tiro.php',
    ],
    [
        'id'    => 's3-ops',
        'icon'  => 'bi-compass',
        'label' => 'Operaciones (planes/órdenes)',
        'desc'  => 'Apreciación de situación; planes/órdenes y supervisión; apoyo; zonas de descanso; alistamiento; defensa/recuperación y PON de seguridad.',
        'href'  => null,
    ],
    [
        'id'    => 's3-mov',
        'icon'  => 'bi-truck',
        'label' => 'Movimientos de tropa',
        'desc'  => 'Transporte con S-1/S-4; organización de marcha; prioridades; puntos terminales; horarios/descansos/caminos; seguridad.',
        'href'  => null,
    ],
    [
        'id'    => 's3-doc',
        'icon'  => 'bi-journal-text',
        'label' => 'Doctrina / SILEAP / Reglamentos',
        'desc'  => 'Modificaciones reglamentarias; experimentación; lecciones aprendidas (SILEAP); inventario/publicaciones con cargo y clasificación.',
        'href'  => null,
    ],
    [
        'id'    => 's3-rolc',
        'icon'  => 'bi-shield-check',
        'label' => 'Rol de Combate',
        'desc'  => 'Visualización del Rol de Combate (por jefatura, plana mayor y compañías).',
        'href'  => '../rol_combate.php',
    ],
    [
        'id'    => 's3-cal',
        'icon'  => 'bi-calendar2-week',
        'label' => 'Calendario',
        'desc'  => 'Agenda, tareas y diario de la unidad.',
        'href'  => '../calendario.php',
    ],
];

$kpis = [
    ['icon' => 'bi-mortarboard', 'label' => 'Educación Cuadros', 'hechas' => $hechasCuadros, 'total' => $totalCuadros, 'porc' => $porcCuadros],
    ['icon' => 'bi-people',      'label' => 'Educación Tropa',   'hechas' => $hechasTropa,   'total' => $totalTropa,   'porc' => $porcTropa],
    ['icon' => 'bi-heart-pulse', 'label' => 'Adiestramiento',    'hechas' => $hechasAdies,   'total' => $totalAdies,   'porc' => $porcAdies],
    ['icon' => 'bi-bullseye',    'label' => 'Tiro',              'hechas' => $hechasTiro,    'total' => $totalTiro,    'porc' => $porcTiro],
];
$sharedBrowseRel = normalize_rel_path((string)($_GET['shared'] ?? ''));
if ($sharedBrowseRel === null) $sharedBrowseRel = '';
$sharedState = scan_shared_dir($OPERACIONES_ROOT_ABS, $sharedBrowseRel);
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
<title>Operaciones · S-3</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<!-- CSS crítico cargado primero, JS diferido -->
<link rel="preconnect" href="https://cdn.jsdelivr.net">
<link rel="icon" type="image/png" href="<?= e($FAVICON) ?>">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= e($ASSET_WEB) ?>/css/theme-602.css">
<link rel="stylesheet" href="<?= e($BASE_PUBLIC_WEB) ?>/chat.css">

<style>
/* =============================================
   VARIABLES & RESET
   ============================================= */
:root {
    --c-bg:        #020617;
    --c-surface:   rgba(15,17,23,.94);
    --c-surface2:  rgba(15,23,42,.96);
    --c-border:    rgba(148,163,184,.40);
    --c-border2:   rgba(148,163,184,.45);
    --c-text:      #e5e7eb;
    --c-muted:     #9ca3af;
    --c-sub:       #cbd5f5;
    --c-green:     #22c55e;
    --c-green-dk:  #052e16;
    --c-green-lt:  #4ade80;
    --c-green-glow:rgba(22,163,74,.7);
    --radius-lg:   18px;
    --radius-md:   14px;
    --radius-sm:   12px;
    --shadow-lg:   0 18px 40px rgba(0,0,0,.75);
    --shadow-md:   0 10px 28px rgba(0,0,0,.75);
    --transition:  .18s ease;
}

html, body { height: 100%; }

body {
    background:
        linear-gradient(160deg, rgba(0,0,0,.82) 0%, rgba(0,0,0,.62) 55%, rgba(0,0,0,.85) 100%),
        url("<?= e($IMG_BG) ?>") no-repeat center center fixed;
    background-size: cover;
    background-color: var(--c-bg);
    color: var(--c-text);
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, Ubuntu, sans-serif;
    margin: 0;
}

/* =============================================
   LAYOUT
   ============================================= */
.page-wrap       { padding: 18px; }
.container-main  { max-width: 1400px; margin: auto; }

.layout-s3-row     { display: flex; flex-wrap: wrap; gap: 18px; }
.layout-s3-sidebar { flex: 0 0 300px; max-width: 380px; }
.layout-s3-main    { flex: 1 1 0; min-width: 0; }

@media (max-width: 768px) {
    .layout-s3-sidebar,
    .layout-s3-main { flex: 1 1 100%; max-width: 100%; }
}

/* =============================================
   PANEL BASE
   ============================================= */
.panel {
    background:      var(--c-surface);
    border:          1px solid var(--c-border);
    border-radius:   var(--radius-lg);
    padding:         18px 22px 22px;
    box-shadow:      var(--shadow-lg), inset 0 1px 0 rgba(255,255,255,.05);
    backdrop-filter: blur(8px);
}
.panel-title {
    font-size:     1.05rem;
    font-weight:   900;
    margin-bottom: 6px;
    display:       flex;
    align-items:   center;
    gap:           .55rem;
}
.panel-sub {
    font-size:     .86rem;
    color:         var(--c-sub);
    margin-bottom: 18px;
}

/* =============================================
   HEADER / BRAND
   ============================================= */
.brand-hero { padding: 10px 0; }
.brand-hero .hero-inner {
    display:         flex;
    align-items:     center;
    justify-content: space-between;
    gap:             12px;
}
.brand-logo {
    width:       58px;
    height:      58px;
    object-fit:  contain;
    filter:      drop-shadow(0 10px 18px rgba(0,0,0,.55));
    transition:  transform var(--transition);
}
.brand-logo:hover { transform: scale(1.06); }

.brand-title { font-weight: 900; font-size: 1.05rem; line-height: 1.1; }
.brand-sub   { font-size: .82rem; color: var(--c-muted); }
.header-back { margin-left: auto; margin-right: 17px; margin-top: 4px; display: flex; gap: 8px; }

/* =============================================
   SIDEBAR
   ============================================= */
.s3-sidebar-box {
    background:    rgba(15,23,42,.95);
    border-radius: var(--radius-sm);
    border:        1px solid var(--c-border2);
    padding:       14px 14px 10px;
    box-shadow:    var(--shadow-md);
}
.s3-sidebar-title {
    font-size:      .88rem;
    font-weight:    900;
    letter-spacing: .05em;
    text-transform: uppercase;
    color:          var(--c-muted);
    margin-bottom:  10px;
    display:        flex;
    align-items:    center;
    gap:            .5rem;
}

/* =============================================
   ACCORDION
   ============================================= */
.accordion-s3 .accordion-item {
    background:    transparent;
    border:        none;
    border-radius: var(--radius-sm);
    margin-bottom: 6px;
    overflow:      hidden;
}
.accordion-s3 .accordion-button {
    background:  radial-gradient(circle at left, rgba(34,197,94,.35), transparent 60%);
    border:      none;
    color:       var(--c-text);
    font-size:   .86rem;
    font-weight: 900;
    padding:     .55rem .75rem;
    box-shadow:  0 6px 14px rgba(0,0,0,.65);
    transition:  background var(--transition);
}
.accordion-s3 .accordion-button:not(.collapsed) {
    background: radial-gradient(circle at left, rgba(34,197,94,.52), transparent 70%);
    color:      #ecfdf5;
}
.accordion-s3 .accordion-button::after {
    filter: invert(1) brightness(1.5);
}
.accordion-s3 .accordion-body {
    background:  var(--c-surface2);
    font-size:   .84rem;
    color:       var(--c-sub);
    border-top:  1px solid rgba(148,163,184,.35);
}

/* =============================================
   BOTÓN DE ACCIÓN
   ============================================= */
.gest-btn {
    display:         inline-flex;
    align-items:     center;
    justify-content: center;
    gap:             .45rem;
    padding:         .45rem 1.1rem;
    border-radius:   999px;
    border:          none;
    font-size:       .82rem;
    font-weight:     900;
    text-decoration: none;
    background:      var(--c-green);
    color:           var(--c-green-dk);
    box-shadow:      0 8px 22px var(--c-green-glow);
    transition:      background var(--transition), transform var(--transition), box-shadow var(--transition);
}
.gest-btn:hover {
    background:  var(--c-green-lt);
    color:       var(--c-green-dk);
    transform:   translateY(-1px);
    box-shadow:  0 12px 28px var(--c-green-glow);
}
.gest-btn.disabled,
.gest-btn[aria-disabled="true"] {
    opacity:        .45;
    pointer-events: none;
    filter:         grayscale(.35);
}

/* =============================================
   KPIs
   ============================================= */
.s3-kpi-grid {
    display:    flex;
    flex-wrap:  wrap;
    gap:        10px;
    margin-top: 10px;
}
.s3-kpi-card {
    flex:          1 1 200px;
    min-width:     180px;
    background:    var(--c-surface2);
    border-radius: var(--radius-md);
    border:        1px solid var(--c-border2);
    padding:       10px 12px;
    font-size:     .78rem;
    transition:    border-color var(--transition), box-shadow var(--transition);
}
.s3-kpi-card:hover {
    border-color: rgba(34,197,94,.5);
    box-shadow:   0 0 16px rgba(34,197,94,.12);
}
.s3-kpi-title {
    text-transform: uppercase;
    letter-spacing: .06em;
    color:          var(--c-muted);
    font-weight:    900;
    margin-bottom:  4px;
}
.s3-kpi-main {
    font-size:   1.05rem;
    font-weight: 900;
    display:     flex;
    align-items: center;
    gap:         .45rem;
}
.s3-kpi-sub { font-size: .78rem; color: var(--c-sub); }
.progress   { background: rgba(15,23,42,.9); }

/* =============================================
   GRÁFICO CIRCULAR (donut)
   ============================================= */
.s3-pie-wrapper { display: flex; justify-content: center; align-items: center; padding: 8px 0; }
.s3-pie {
    width:         220px;
    aspect-ratio:  1 / 1;
    border-radius: 50%;
    position:      relative;
    box-shadow:    0 16px 35px rgba(0,0,0,.9);
    transition:    transform var(--transition);
}
.s3-pie:hover { transform: scale(1.03); }
.s3-pie-inner {
    position:        absolute;
    inset:           20px;
    border-radius:   50%;
    background:      rgba(15,23,42,.98);
    display:         flex;
    flex-direction:  column;
    align-items:     center;
    justify-content: center;
    text-align:      center;
}
.s3-pie-perc  { font-size: 1.6rem; font-weight: 900; }
.s3-pie-label { font-size: .75rem; color: var(--c-muted); text-transform: uppercase; letter-spacing: .09em; margin-top: 4px; }

/* =============================================
   DOCTRINA / ALERTAS
   ============================================= */
.s3-main-text { font-size: .9rem; color: var(--c-sub); }

.s3-doctrina {
    background:    rgba(2,6,23,.55);
    border:        1px dashed var(--c-border2);
    border-radius: var(--radius-md);
    padding:       12px;
    margin-top:    10px;
}
.s3-doctrina h6 {
    margin:      0 0 8px;
    font-weight: 900;
    font-size:   .88rem;
    color:       var(--c-text);
    display:     flex;
    align-items: center;
    gap:         .45rem;
}
.s3-doctrina ul { margin: 0; padding-left: 18px; color: var(--c-sub); font-size: .84rem; }
.s3-doctrina li { margin: 6px 0; }

.alert-dark-custom {
    background:    rgba(15,23,42,.9);
    border:        1px solid rgba(148,163,184,.35);
    border-radius: var(--radius-md);
    color:         var(--c-sub);
}
.alert-seg {
    background:    rgba(2,6,23,.55);
    border:        1px dashed var(--c-border2);
    border-radius: var(--radius-md);
    color:         var(--c-sub);
}
.shared-panel{
    margin: 18px 0 20px;
    background: rgba(15,23,42,.82);
    border: 1px solid rgba(148,163,184,.28);
    border-radius: 18px;
    padding: 18px;
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
    width:100%; border-collapse:collapse; overflow:hidden; border-radius:12px;
}
.shared-table th, .shared-table td{
    padding:10px 12px; border-bottom:1px solid rgba(148,163,184,.16); text-align:left; font-size:.84rem;
}
.shared-table th{
    color:#93c5fd; background:rgba(30,41,59,.92); font-weight:800;
}
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
</style>
</head>
<body>

<!-- =============================================
     HEADER
     ============================================= -->
<header class="brand-hero">
  <div class="hero-inner container-main">
    <div class="d-flex align-items-center gap-3">
      <img class="brand-logo"
           src="<?= e($ESCUDO) ?>"
           alt="Escudo ECMILM"
           onerror="this.onerror=null; this.src='<?= e($ASSET_WEB) ?>/img/EA.png';">
      <div>
        <div class="brand-title">Escuela Militar de Montaña</div>
        <div class="brand-sub">"La montaña nos une"</div>
      </div>
    </div>
    <div class="header-back">
      <a href="../inicio.php" class="btn btn-success btn-sm fw-bold px-3">
        <i class="bi bi-house-door"></i> Inicio
      </a>
    </div>
  </div>
</header>

<!-- =============================================
     CONTENIDO PRINCIPAL
     ============================================= -->
<div class="page-wrap">
  <div class="container-main">
    <div class="panel">

      <div class="panel-title">
        <i class="bi bi-diagram-3-fill"></i>
        Área S-3 · Operaciones
      </div>
      <div class="panel-sub">
        Seleccioná el eje de trabajo correspondiente. Este panel se alinea a la Sección III (S-3):
        organización, educación, operaciones, movimientos y doctrina (PON, SILEAP, reglamentos).
      </div>

      <div class="shared-panel" id="shared-browser">
        <div class="panel-title" style="margin-bottom:4px;">
          <i class="bi bi-folder2-open"></i>
          Carpeta compartida de Operaciones
        </div>
        <div class="panel-sub" style="margin-bottom:10px;">
          Acá podés ver todo lo que exista físicamente dentro de <code><?= e(str_replace('\\', '/', $OPERACIONES_ROOT_ABS)) ?></code>,
          incluso si lo cargaron directamente desde la carpeta compartida.
        </div>

        <?php if (!$sharedState['ok']): ?>
          <div class="alert alert-warning mb-0"><?= e((string)$sharedState['error']) ?></div>
        <?php else: ?>
          <div class="shared-toolbar">
            <div class="shared-path">
              <span><b>Ruta:</b></span>
              <a class="js-shared-nav" href="<?= e($SELF_WEB) ?>#shared-browser">OPERACIONES</a>
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

      <div class="layout-s3-row">

        <!-- =============================================
             SIDEBAR — Ejes doctrinarios (generado desde array)
             ============================================= -->
        <aside class="layout-s3-sidebar">
          <div class="s3-sidebar-box">
            <div class="s3-sidebar-title"><i class="bi bi-grid-3x3-gap"></i> Ejes doctrinarios S-3</div>

            <div class="accordion accordion-s3" id="accordionS3">
              <?php foreach ($ejesS3 as $i => $eje): ?>
              <div class="accordion-item">
                <h2 class="accordion-header" id="h-<?= e($eje['id']) ?>">
                  <button class="accordion-button <?= $i > 0 ? 'collapsed' : '' ?>"
                          type="button"
                          data-bs-toggle="collapse"
                          data-bs-target="#<?= e($eje['id']) ?>"
                          aria-expanded="<?= $i === 0 ? 'true' : 'false' ?>"
                          aria-controls="<?= e($eje['id']) ?>">
                    <i class="bi <?= e($eje['icon']) ?> me-1"></i> <?= e($eje['label']) ?>
                  </button>
                </h2>
                <div id="<?= e($eje['id']) ?>"
                     class="accordion-collapse collapse <?= $i === 0 ? 'show' : '' ?>"
                     aria-labelledby="h-<?= e($eje['id']) ?>"
                     data-bs-parent="#accordionS3">
                  <div class="accordion-body">
                    <?= e($eje['desc']) ?>
                    <div class="mt-2">
                      <?php if ($eje['href']): ?>
                        <a href="<?= e($eje['href']) ?>" class="gest-btn">
                          <i class="bi bi-box-arrow-in-right"></i> Entrar
                        </a>
                      <?php else: ?>
                        <a href="#" class="gest-btn disabled" aria-disabled="true">
                          <i class="bi bi-hourglass-split"></i> Próximamente
                        </a>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </aside>

        <!-- =============================================
             MAIN — Resumen y KPIs
             ============================================= -->
        <section class="layout-s3-main">
          <div class="row g-3 align-items-start">

            <div class="col-md-7">
              <?php if ($modoResumido): ?>
                <div class="alert alert-warning" role="alert">
                  <strong>Modo vista resumida</strong>: la información está limitada para usuarios no administradores.
                </div>
              <?php endif; ?>

              <div class="s3-main-text">
                <p class="mb-2">
                  Este panel consolida el estado general de los componentes de <strong>S-3</strong>.
                  Los totales se cuentan desde tablas si existen; el "cumplidas" queda en <strong>modo demo</strong>
                  hasta definir el campo real que marca cumplimiento/estado.
                </p>
                <p class="mb-2">
                  Incluye el enfoque completo de Sección III: <b>Organización</b>, <b>Educación</b>,
                  <b>Operaciones</b>, <b>Movimientos</b>, <b>Doctrina</b> y <b>PON</b>.
                </p>
              </div>

              <div class="s3-doctrina">
                <h6><i class="bi bi-compass"></i> Qué hace S-3 (Sección III · 2.023)</h6>
                <ul>
                  <li><b>Organización:</b> CO actualizado; ajustes según órdenes; prioridades de personal/material; estados de personal/armas/vehículos.</li>
                  <li><b>Educación:</b> PEU; órdenes de instrucción; ejercicios; MAPE; aulas/pistas/campos; consumo munición con S-4; inspecciones educativas; archivo/biblioteca de reglamentos.</li>
                  <li><b>Operaciones:</b> apreciación de situación; planes/órdenes; apoyo; zonas de descanso; seguridad (con S-2 CI y con S-1 seguridad contra accidentes); ubicación del PC; exploración/reconocimientos; alistamiento; plan defensa/recuperación + PON de seguridad (base estudio S-2).</li>
                  <li><b>Movimientos:</b> transporte con S-1/S-4; organización de marcha; prioridades; puntos terminales; tiempos/horarios/descansos/caminos; seguridad; órdenes preparatorias/de marcha; profundidades.</li>
                  <li><b>Doctrina:</b> modificaciones reglamentarias; experimentación; lecciones aprendidas (SILEAP); inventario/publicaciones con cargo y clasificación; reglamentos actualizados disponibles.</li>
                  <li><b>Varios:</b> redacta/actualiza PON sobre organización, educación y operaciones.</li>
                </ul>
              </div>

              <?php if ($esAdmin): ?>
                <div class="s3-kpi-grid mt-3">
                  <?php foreach ($kpis as $kpi): ?>
                  <div class="s3-kpi-card">
                    <div class="s3-kpi-title"><?= e($kpi['label']) ?></div>
                    <div class="s3-kpi-main">
                      <i class="bi <?= e($kpi['icon']) ?>"></i>
                      <?= e($kpi['hechas']) ?>/<?= e($kpi['total']) ?>
                    </div>
                    <div class="s3-kpi-sub">Cumplimiento (demo): <?= e($kpi['porc']) ?>%</div>
                    <div class="progress mt-1" style="height:6px;" role="progressbar"
                         aria-valuenow="<?= e($kpi['porc']) ?>" aria-valuemin="0" aria-valuemax="100">
                      <div class="progress-bar bg-success" style="width:<?= e($kpi['porc']) ?>%"></div>
                    </div>
                  </div>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <div class="alert alert-secondary mt-3">
                  Información de KPIs restringida. Contactá a un administrador para ver más detalles.
                </div>
              <?php endif; ?>

              <div class="alert alert-dark-custom p-3 mt-3">
                <div class="fw-bold mb-1">
                  <i class="bi bi-wrench-adjustable-circle"></i> Para hacerlo "REAL"
                </div>
                <div style="font-size:.86rem;">
                  Para calcular "cumplidas" (no demo), necesito el nombre exacto del campo en cada tabla
                  que indique estado/cumplimiento (ej: <code>estado</code>, <code>cumplida</code>, <code>finalizada</code>, etc.).
                </div>
              </div>
            </div>

            <!-- Columna derecha: donut + seguridad -->
            <div class="col-md-5">
              <div class="s3-pie-wrapper">
                <div class="s3-pie"
                     style="background: conic-gradient(
                         var(--c-green) 0 <?= e($porcGlobal) ?>%,
                         rgba(30,64,175,.6) <?= e($porcGlobal) ?>% 100%
                     );"
                     role="img"
                     aria-label="Cumplimiento global S-3: <?= e($porcGlobal) ?>%">
                  <div class="s3-pie-inner">
                    <div class="s3-pie-perc"><?= e($porcGlobal) ?>%</div>
                    <div class="s3-pie-label">Cumplimiento global S-3</div>
                    <div style="font-size:.7rem; color:var(--c-muted); margin-top:4px;">
                      <?= e($hechasActiv) ?>/<?= e($totalActiv) ?> actividades (demo)
                    </div>
                  </div>
                </div>
              </div>

              <div class="alert-seg p-3 mt-2">
                <div class="fw-bold mb-1">
                  <i class="bi bi-shield-check"></i> Seguridad (coordinación obligatoria)
                </div>
                <div style="font-size:.86rem;">
                  En operaciones, S-3 coordina medidas de seguridad con:
                  <ul style="margin:8px 0 0; padding-left:18px;">
                    <li><b>S-2</b> (Contrainteligencia / seguridad operativa)</li>
                    <li><b>S-1</b> (Seguridad contra accidentes)</li>
                    <li><b>S-4</b> (Apoyo logístico y consumo munición para instrucción)</li>
                  </ul>
                </div>
              </div>
            </div>

          </div>
        </section>

      </div>
    </div>
  </div>
</div>

<!-- JS diferido: no bloquea el render -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>

<!-- =============================================
     CHAT DOCK
     ============================================= -->
<div id="chatLauncher" class="chat-launcher show">
  <div class="chat-launcher-title">Chat interno</div>
  <span id="chatLauncherBadge" class="chat-total-badge">0</span>
</div>

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
          <button type="submit" class="btn btn-success btn-sm fw-bold">Enviar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
/* =============================================
   CHAT — lógica de UI
   ============================================= */
(function () {
    'use strict';

    const sharedBrowser = document.getElementById('shared-browser');
    const layoutRow = document.querySelector('.layout-s3-row');
    if (sharedBrowser && layoutRow && layoutRow.parentNode) {
        layoutRow.parentNode.appendChild(sharedBrowser);
        sharedBrowser.style.marginTop = '22px';
    }

    // ── Constantes ──────────────────────────────
    const CHAT_AJAX_URL      = <?= json_encode($CHAT_AJAX_URL, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const CHAT_FULL_URL      = <?= json_encode($CHAT_FULL_URL, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const CSRF_TOKEN         = <?= json_encode($CHAT_CSRF, JSON_UNESCAPED_UNICODE) ?>;
    const CAN_WRITE_GENERAL  = <?= $esAdmin ? 'true' : 'false' ?>;
    const STORAGE_KEY        = 'ea_chat_seen_<?= (int)$personalId ?>';
    const POLL_INTERVAL_MS   = 5000;
    const MAX_VISIBLE_MSGS   = 25;

    // ── Referencias DOM ─────────────────────────
    const dock          = document.getElementById('chatDock');
    const launcher      = document.getElementById('chatLauncher');
    const launcherBadge = document.getElementById('chatLauncherBadge');
    const dockBadge     = document.getElementById('chatDockBadge');
    const closeBtn      = document.getElementById('chatCloseBtn');
    const convList      = document.getElementById('chatConvList');
    const messagesBox   = document.getElementById('chatMessages');
    const threadTitle   = document.getElementById('chatThreadTitle');
    const threadSub     = document.getElementById('chatThreadSub');
    const compose       = document.getElementById('chatCompose');
    const input         = document.getElementById('chatInput');
    const readonlyBox   = document.getElementById('chatReadonly');
    const openFull      = document.getElementById('chatOpenFull');

    // ── Estado ──────────────────────────────────
    const state = {
        conversations:        [],
        selectedConversationId: null,
        unreadMap:            {},
        seenMap:              {},
        baselineLoaded:       false,
        pollingHandle:        null,
    };

    // ── Persistencia ────────────────────────────
    try {
        state.seenMap = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}') || {};
    } catch (_) {
        state.seenMap = {};
    }

    function saveSeenMap() {
        try { localStorage.setItem(STORAGE_KEY, JSON.stringify(state.seenMap)); } catch (_) {}
    }

    // ── Utilidades ──────────────────────────────
    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g,  '&amp;')
            .replace(/</g,  '&lt;')
            .replace(/>/g,  '&gt;')
            .replace(/"/g,  '&quot;')
            .replace(/'/g,  '&#039;');
    }

    function apiGet(action, params = {}) {
        const qs = new URLSearchParams({ action, ...params });
        return fetch(`${CHAT_AJAX_URL}&${qs}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        }).then(parseResponse);
    }

    function apiPost(action, params = {}) {
        const body = new URLSearchParams({ action, _csrf: CSRF_TOKEN, ...params });
        return fetch(CHAT_AJAX_URL, {
            method:  'POST',
            headers: {
                'Content-Type':     'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: body.toString(),
        }).then(parseResponse);
    }

    async function parseResponse(r) {
        const text = await r.text();
        try { return JSON.parse(text); }
        catch (_) { return { ok: false, error: 'Respuesta inválida del chat', raw: text }; }
    }

    // ── Selección de conversación ────────────────
    function selectedConversation() {
        return state.conversations.find(c => Number(c.id) === Number(state.selectedConversationId)) || null;
    }

    // ── Badges ──────────────────────────────────
    function updateUnreadBadges() {
        const total = Object.keys(state.unreadMap).length;
        launcherBadge.textContent = String(total);
        dockBadge.textContent     = String(total);
        launcherBadge.classList.toggle('show', total > 0);
        dockBadge.classList.toggle('show',     total > 0);
        document.title = total > 0 ? `(${total}) Operaciones` : 'Operaciones · S-3';
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
            const currentId  = Number(c.last_message_id || 0);
            const seenId     = Number(state.seenMap[c.id]  || 0);
            const isSelected = Number(c.id) === Number(state.selectedConversationId);

            if (currentId > seenId) {
                if (isSelected || c.last_from_me || currentId <= 0) {
                    state.seenMap[c.id] = currentId;
                    delete state.unreadMap[c.id];
                } else {
                    state.unreadMap[c.id] = true;
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

    // ── Render: cabecera del hilo ────────────────
    function setThreadHeader() {
        const c = selectedConversation();
        if (!c) {
            threadTitle.textContent = 'Sin conversación';
            threadSub.textContent   = '';
            compose.classList.remove('chat-hidden');
            readonlyBox.classList.remove('show');
            openFull.href = CHAT_FULL_URL;
            return;
        }
        threadTitle.textContent = c.title || 'Conversación';
        threadSub.textContent   = c.type === 'general'
            ? 'Mensajes generales de la unidad'
            : (c.is_self ? 'Tus notas personales' : 'Conversación privada');

        const readOnly = c.type === 'general' && !CAN_WRITE_GENERAL;
        compose.classList.toggle('chat-hidden', readOnly);
        readonlyBox.classList.toggle('show',    readOnly);
        openFull.href = CHAT_FULL_URL;
    }

    // ── Render: lista de conversaciones ─────────
    function renderConversations() {
        if (!state.conversations.length) {
            convList.innerHTML = '<div class="chat-empty">No hay conversaciones.</div>';
            return;
        }

        convList.innerHTML = state.conversations.map(c => {
            const typeText = c.type === 'general' ? 'GENERAL' : (c.is_self ? 'NOTAS' : 'PRIVADO');
            const isNew    = !!state.unreadMap[c.id];
            const isActive = Number(c.id) === Number(state.selectedConversationId);

            return `
              <button type="button" class="chat-conv-item ${isActive ? 'active' : ''}" data-id="${Number(c.id)}">
                <div class="chat-conv-top">
                  <div class="chat-conv-name">${escapeHtml(c.title || 'Conversación')}</div>
                  <div style="display:flex; align-items:center;">
                    <span class="chat-conv-badge ${isNew ? 'show' : ''}">NUEVO</span>
                    <span class="chat-conv-type">${escapeHtml(typeText)}</span>
                  </div>
                </div>
                <div class="chat-conv-last">${escapeHtml(c.last_message || 'Sin mensajes todavía')}</div>
              </button>`;
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

    // ── Carga de conversaciones ──────────────────
    async function loadConversations(preferId = null) {
        const r = await apiGet('list_conversations');
        if (!r.ok) {
            convList.innerHTML = `<div class="chat-empty">${escapeHtml(r.error || 'No se pudieron cargar las conversaciones.')}</div>`;
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

    // ── Carga de mensajes ────────────────────────
    async function loadMessages(scrollBottom = false) {
        if (!state.selectedConversationId) return;

        const r = await apiGet('get_messages', { conversation_id: state.selectedConversationId });
        if (!r.ok) {
            messagesBox.innerHTML = `<div class="chat-empty">${escapeHtml(r.error || 'No se pudieron cargar los mensajes.')}</div>`;
            return;
        }

        const items     = Array.isArray(r.items) ? r.items : [];
        const viewItems = items.slice(-MAX_VISIBLE_MSGS);

        if (!viewItems.length) {
            messagesBox.innerHTML = '<div class="chat-empty">No hay mensajes todavía.</div>';
            markConversationSeen(state.selectedConversationId);
            return;
        }

        messagesBox.innerHTML = viewItems.map(m => `
          <div class="msg-row ${m.mine ? 'me' : 'other'}">
            <div class="msg-meta">${escapeHtml(m.mine ? 'Yo' : m.author)} · ${escapeHtml(m.created_hm || '')}</div>
            <div class="msg-bubble ${m.mine ? 'me' : 'other'}">${escapeHtml(m.message || '')}</div>
          </div>`).join('');

        markConversationSeen(state.selectedConversationId);
        renderConversations();
        if (scrollBottom) messagesBox.scrollTop = messagesBox.scrollHeight;
    }

    // ── Envío de mensaje ─────────────────────────
    async function sendMessage(ev) {
        ev.preventDefault();
        const text = input.value.trim();
        const c    = selectedConversation();
        if (!c || !text) return;

        const r = await apiPost('send_message', { conversation_id: c.id, message: text });
        if (!r.ok) {
            alert(r.error || 'No se pudo enviar el mensaje.');
            return;
        }
        input.value = '';
        await loadMessages(true);
        await loadConversations(c.id);
    }

    // ── Dock: abrir / cerrar ─────────────────────
    function closeDock() {
        dock.classList.add('chat-hidden');
        launcher.classList.replace('chat-hidden', 'show') || launcher.classList.add('show');
    }
    function openDock() {
        launcher.classList.remove('show');
        launcher.classList.add('chat-hidden');
        dock.classList.remove('chat-hidden');
    }

    // ── Eventos ──────────────────────────────────
    closeBtn.addEventListener('click',  closeDock);
    launcher.addEventListener('click',  openDock);
    compose .addEventListener('submit', sendMessage);
    document.addEventListener('keydown', ev => {
        if (ev.key === 'Escape' && !dock.classList.contains('chat-hidden')) closeDock();
    });

    // ── Arranque & polling ───────────────────────
    loadConversations().then(() => loadMessages(true));

    state.pollingHandle = setInterval(async () => {
        const current = state.selectedConversationId;
        await loadConversations(current);
        await loadMessages(false);
    }, POLL_INTERVAL_MS);
})();

(function () {
    const sharedScrollKey = 'operaciones_shared_scroll';
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
