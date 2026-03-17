<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/bootstrap.php';
require_login();
require_once __DIR__ . '/../config/db.php';

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function norm_dni(string $dni): string { return preg_replace('/\D+/', '', $dni) ?? ''; }
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
function human_size(int $bytes): string {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1024 * 1024) return number_format($bytes / 1024, 1) . ' KB';
    if ($bytes < 1024 * 1024 * 1024) return number_format($bytes / 1024 / 1024, 2) . ' MB';
    return number_format($bytes / 1024 / 1024 / 1024, 2) . ' GB';
}
function format_dt_local(?int $ts): string {
    if (!$ts || $ts <= 0) return '—';
    return date('d/m/Y H:i', $ts);
}
function mime_for_ext(string $ext): string {
    $ext = strtolower($ext);
    return match ($ext) {
        'pdf'  => 'application/pdf',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'xls'  => 'application/vnd.ms-excel',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'jpg', 'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp',
        'gif'  => 'image/gif',
        'txt', 'log' => 'text/plain; charset=UTF-8',
        'csv'  => 'text/csv; charset=UTF-8',
        'zip'  => 'application/zip',
        default => 'application/octet-stream',
    };
}
function send_file_inline(string $absPath, string $downloadName, string $mime): void {
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
function detect_kind(bool $isDir, string $ext): string {
    if ($isDir) return 'folder';
    $ext = strtolower($ext);
    if ($ext === 'pdf') return 'pdf';
    if (in_array($ext, ['doc', 'docx'], true)) return 'word';
    if (in_array($ext, ['xls', 'xlsx', 'csv'], true)) return 'excel';
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) return 'image';
    if (in_array($ext, ['txt', 'log'], true)) return 'text';
    if (in_array($ext, ['zip', 'rar', '7z'], true)) return 'archive';
    return 'other';
}
function kind_label(string $kind): string {
    return match ($kind) {
        'folder' => 'Carpeta',
        'pdf'    => 'PDF',
        'word'   => 'Word',
        'excel'  => 'Excel',
        'image'  => 'Imagen',
        'text'   => 'Texto',
        'archive'=> 'Comprimido',
        default  => 'Otro',
    };
}
function kind_icon(string $kind): string {
    return match ($kind) {
        'folder' => '📁',
        'pdf'    => '📕',
        'word'   => '📘',
        'excel'  => '📗',
        'image'  => '🖼️',
        'text'   => '📝',
        'archive'=> '🗜️',
        default  => '📄',
    };
}
function scan_shared_dir(string $baseAbs, string $relative): array {
    $baseReal = realpath($baseAbs);
    if ($baseReal === false) return ['ok' => false, 'error' => 'No existe la carpeta DOCUMENTACION.', 'current' => '', 'entries' => []];
    $relative = normalize_rel_path($relative);
    if ($relative === null) return ['ok' => false, 'error' => 'Ruta inválida.', 'current' => '', 'entries' => []];
    $targetAbs = $relative === '' ? $baseReal : ($baseReal . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));
    $targetReal = realpath($targetAbs);
    if ($targetReal === false || !is_dir($targetReal) || !starts_with($targetReal, $baseReal)) {
        return ['ok' => false, 'error' => 'La carpeta solicitada no existe.', 'current' => $relative, 'entries' => []];
    }

    $items = @scandir($targetReal);
    if (!is_array($items)) return ['ok' => false, 'error' => 'No se pudo leer la carpeta.', 'current' => $relative, 'entries' => []];

    $entries = [];
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $abs = $targetReal . DIRECTORY_SEPARATOR . $item;
        $isDir = is_dir($abs);
        $childRel = $relative === '' ? $item : ($relative . '/' . $item);
        $ext = $isDir ? '' : strtolower((string)pathinfo($item, PATHINFO_EXTENSION));
        $kind = detect_kind($isDir, $ext);
        $entries[] = [
            'name' => $item,
            'rel' => $childRel,
            'is_dir' => $isDir,
            'size' => $isDir ? null : (int)@filesize($abs),
            'mtime' => (int)@filemtime($abs),
            'ext' => $ext,
            'kind' => $kind,
        ];
    }

    usort($entries, static function (array $a, array $b): int {
        if ($a['is_dir'] !== $b['is_dir']) return $a['is_dir'] ? -1 : 1;
        return strcasecmp((string)$a['name'], (string)$b['name']);
    });

    return ['ok' => true, 'error' => '', 'current' => $relative, 'entries' => $entries];
}
function search_documents(string $baseAbs, string $relative, string $query, string $kindFilter): array {
    $baseReal = realpath($baseAbs);
    if ($baseReal === false) return [];
    $relative = normalize_rel_path($relative);
    if ($relative === null) return [];
    $startAbs = $relative === '' ? $baseReal : ($baseReal . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));
    $startReal = realpath($startAbs);
    if ($startReal === false || !is_dir($startReal) || !starts_with($startReal, $baseReal)) return [];

    $query = trim($query);
    $qNorm = mb_strtolower($query, 'UTF-8');

    $results = [];
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($startReal, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($it as $fileInfo) {
        $realPath = $fileInfo->getPathname();
        if (!starts_with($realPath, $baseReal)) continue;

        $isDir = $fileInfo->isDir();
        $name = $fileInfo->getFilename();
        $rel = ltrim(str_replace('\\', '/', substr($realPath, strlen($baseReal))), '/');
        $ext = $isDir ? '' : strtolower((string)$fileInfo->getExtension());
        $kind = detect_kind($isDir, $ext);

        if ($kindFilter !== '' && $kindFilter !== 'all' && $kind !== $kindFilter) continue;
        if ($qNorm !== '') {
            $haystack = mb_strtolower($name . ' ' . $rel, 'UTF-8');
            if (mb_strpos($haystack, $qNorm) === false) continue;
        }

        $results[] = [
            'name' => $name,
            'rel' => $rel,
            'is_dir' => $isDir,
            'size' => $isDir ? null : (int)$fileInfo->getSize(),
            'mtime' => (int)$fileInfo->getMTime(),
            'ext' => $ext,
            'kind' => $kind,
        ];
    }

    usort($results, static function (array $a, array $b): int {
        if ($a['is_dir'] !== $b['is_dir']) return $a['is_dir'] ? -1 : 1;
        return ($b['mtime'] <=> $a['mtime']) ?: strcasecmp((string)$a['name'], (string)$b['name']);
    });

    return $results;
}

$user = function_exists('current_user') ? current_user() : ($_SESSION['user'] ?? null);
$dniNorm = norm_dni((string)($user['dni'] ?? $user['username'] ?? ''));
$personalId = 0;
$unidadPropia = 1;
$fullNameDB = '';

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
            $personalId = (int)($r['id'] ?? 0);
            $unidadPropia = (int)($r['unidad_id'] ?? 1);
            $fullNameDB = (string)($r['nombre_comp'] ?? '');
        }
    }
} catch (Throwable $e) {}

$roleCodigo = 'USUARIO';
try {
    if ($personalId > 0) {
        $st = $pdo->prepare("
            SELECT r.codigo
            FROM personal_unidad pu
            LEFT JOIN roles r ON r.id = pu.role_id
            WHERE pu.id = :pid
            LIMIT 1
        ");
        $st->execute([':pid' => $personalId]);
        $c = $st->fetchColumn();
        if (is_string($c) && $c !== '') $roleCodigo = strtoupper($c);
    }
} catch (Throwable $e) {}

$esSuperAdmin = ($roleCodigo === 'SUPERADMIN');
$unidadActiva = $unidadPropia;
if ($esSuperAdmin) {
    $uSel = (int)($_SESSION['unidad_id'] ?? 0);
    if ($uSel > 0) $unidadActiva = $uSel;
}

$unidadInfo = [
    'nombre_completo' => 'Escuela Militar de Montaña',
    'subnombre' => '',
    'slug' => 'ecmilm',
];
try {
    $st = $pdo->prepare("
        SELECT nombre_completo, subnombre, slug
        FROM unidades
        WHERE id = :id
        LIMIT 1
    ");
    $st->execute([':id' => $unidadActiva]);
    if ($u = $st->fetch(PDO::FETCH_ASSOC)) {
        $unidadInfo = array_merge($unidadInfo, array_filter($u, static fn($v) => $v !== null && $v !== ''));
    }
} catch (Throwable $e) {}

$SELF_WEB = (string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
$BASE_PUBLIC_WEB = rtrim(str_replace('\\', '/', dirname($SELF_WEB)), '/');
$BASE_APP_WEB = rtrim(str_replace('\\', '/', dirname($BASE_PUBLIC_WEB)), '/');
$ASSET_WEB = $BASE_APP_WEB . '/assets';
$IMG_BG = $ASSET_WEB . '/img/fondo.png';
$ESCUDO = $ASSET_WEB . '/img/ecmilm.png';
$FAVICON = $ESCUDO;
$ROOT_FS = realpath(__DIR__ . '/..');
$unidadSlug = trim((string)($unidadInfo['slug'] ?? 'ecmilm'));
if ($unidadSlug === '') $unidadSlug = 'ecmilm';
$DOC_ROOT_ABS = ($ROOT_FS !== false)
    ? ($ROOT_FS . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'unidades' . DIRECTORY_SEPARATOR . $unidadSlug . DIRECTORY_SEPARATOR . 'DOCUMENTACION')
    : '';

if (isset($_GET['download']) && (string)($_GET['download']) === '1') {
    $rel = normalize_rel_path((string)($_GET['path'] ?? ''));
    $base = realpath($DOC_ROOT_ABS);
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
    send_file_inline($realAbs, basename($realAbs), mime_for_ext($ext));
}

$browseRel = normalize_rel_path((string)($_GET['dir'] ?? ''));
if ($browseRel === null) $browseRel = '';
$q = trim((string)($_GET['q'] ?? ''));
$kindFilter = trim((string)($_GET['kind'] ?? 'all'));
$allowedKinds = ['all', 'folder', 'pdf', 'word', 'excel', 'image', 'text', 'archive', 'other'];
if (!in_array($kindFilter, $allowedKinds, true)) $kindFilter = 'all';

$sharedState = scan_shared_dir($DOC_ROOT_ABS, $browseRel);
$segments = [];
if ($sharedState['ok'] && $sharedState['current'] !== '') {
    $acc = [];
    foreach (explode('/', (string)$sharedState['current']) as $seg) {
        $acc[] = $seg;
        $segments[] = ['name' => $seg, 'rel' => implode('/', $acc)];
    }
}

$searchActive = ($q !== '' || $kindFilter !== 'all');
$searchResults = ($sharedState['ok'] && $searchActive)
    ? search_documents($DOC_ROOT_ABS, (string)$sharedState['current'], $q, $kindFilter)
    : [];

$currentEntries = $sharedState['ok'] ? $sharedState['entries'] : [];
$countFolders = 0;
$countFiles = 0;
foreach ($currentEntries as $entry) {
    if (!empty($entry['is_dir'])) $countFolders++;
    else $countFiles++;
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Documentacion</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="icon" type="image/png" href="<?= e($FAVICON) ?>">
<style>
  html,body{ min-height:100%; }
  body{
    margin:0;
    color:#e5e7eb;
    background:
      radial-gradient(circle at top left, rgba(14,165,233,.18), transparent 28%),
      radial-gradient(circle at top right, rgba(16,185,129,.14), transparent 24%),
      linear-gradient(180deg, rgba(2,6,23,.98), rgba(10,15,28,.98));
    font-family:system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,sans-serif;
  }
  .page-bg{
    position:fixed; inset:0; z-index:-1; pointer-events:none;
    background:
      linear-gradient(160deg, rgba(0,0,0,.76), rgba(2,6,23,.86)),
      url("<?= e($IMG_BG) ?>") center/cover no-repeat;
    opacity:.28;
    filter:saturate(1.02);
  }
  .container-main{ max-width:1480px; margin:auto; padding:18px; }
  .hero{
    background:linear-gradient(135deg, rgba(15,23,42,.96), rgba(12,18,30,.92));
    border:1px solid rgba(125,211,252,.22);
    border-radius:24px;
    padding:22px;
    box-shadow:0 20px 48px rgba(0,0,0,.42);
    margin-bottom:18px;
  }
  .hero-top{
    display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap;
  }
  .hero-brand{
    display:flex; align-items:center; gap:14px;
  }
  .hero-brand img{
    width:58px; height:58px; object-fit:contain;
    border-radius:16px;
    border:1px solid rgba(148,163,184,.28);
    background:rgba(255,255,255,.04);
    padding:6px;
  }
  .hero-kicker{
    text-transform:uppercase;
    letter-spacing:.12em;
    font-size:.72rem;
    color:#7dd3fc;
    font-weight:900;
  }
  .hero-title{
    font-size:1.8rem;
    font-weight:900;
    margin-top:4px;
  }
  .hero-sub{
    color:#bfd3ea;
    max-width:780px;
    margin-top:8px;
    line-height:1.55;
  }
  .hero-actions{ display:flex; gap:10px; flex-wrap:wrap; }
  .btn-soft{
    display:inline-flex; align-items:center; gap:8px;
    padding:.58rem .95rem; border-radius:12px;
    background:rgba(15,23,42,.76); color:#eef2ff; text-decoration:none;
    border:1px solid rgba(148,163,184,.24); font-weight:800;
  }
  .btn-soft:hover{ color:#fff; border-color:rgba(125,211,252,.42); background:rgba(30,41,59,.86); }
  .hero-stats{
    display:grid; grid-template-columns:repeat(4, minmax(0,1fr)); gap:12px; margin-top:18px;
  }
  .stat-card{
    border-radius:18px;
    background:rgba(15,23,42,.72);
    border:1px solid rgba(148,163,184,.18);
    padding:14px 16px;
  }
  .stat-num{ font-size:1.45rem; font-weight:900; line-height:1; }
  .stat-lbl{ color:#9fb1c9; font-size:.78rem; margin-top:5px; text-transform:uppercase; letter-spacing:.06em; }
  .search-shell{
    background:rgba(8,13,24,.88);
    border:1px solid rgba(125,211,252,.20);
    border-radius:22px;
    padding:18px;
    box-shadow:0 16px 40px rgba(0,0,0,.32);
    margin-bottom:18px;
  }
  .search-title{
    display:flex; align-items:center; gap:10px;
    font-weight:900; font-size:1.06rem; margin-bottom:6px;
  }
  .search-sub{ color:#9fb1c9; font-size:.88rem; margin-bottom:14px; }
  .search-grid{
    display:grid; grid-template-columns:minmax(0, 1.8fr) minmax(220px, .8fr) auto auto; gap:12px;
  }
  .search-grid .form-control,
  .search-grid .form-select{
    background:rgba(15,23,42,.88);
    border:1px solid rgba(148,163,184,.22);
    color:#eef2ff;
    border-radius:14px;
    min-height:48px;
  }
  .search-grid .form-control:focus,
  .search-grid .form-select:focus{
    box-shadow:none;
    border-color:rgba(125,211,252,.46);
    background:rgba(15,23,42,.96);
    color:#fff;
  }
  .search-grid .form-select option{ background:#0f172a; color:#eef2ff; }
  .chip-row{ display:flex; flex-wrap:wrap; gap:8px; margin-top:14px; }
  .chip{
    display:inline-flex; align-items:center; gap:6px;
    padding:.42rem .72rem; border-radius:999px;
    background:rgba(15,23,42,.72); border:1px solid rgba(148,163,184,.18);
    color:#dbe7f6; text-decoration:none; font-size:.8rem; font-weight:800;
  }
  .chip.active{ background:rgba(14,165,233,.16); border-color:rgba(125,211,252,.40); color:#dff6ff; }
  .panel-box{
    background:rgba(8,13,24,.84);
    border:1px solid rgba(148,163,184,.18);
    border-radius:22px;
    padding:18px;
    box-shadow:0 16px 36px rgba(0,0,0,.26);
  }
  .panel-head{
    display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap;
    margin-bottom:12px;
  }
  .panel-title{
    font-size:1.12rem; font-weight:900; display:flex; align-items:center; gap:10px;
  }
  .panel-sub{ color:#9fb1c9; font-size:.86rem; }
  .pathbar{
    display:flex; align-items:center; gap:8px; flex-wrap:wrap;
    color:#dbe7f6; font-size:.88rem; margin-bottom:14px;
  }
  .pathbar a{ color:#7dd3fc; text-decoration:none; font-weight:800; }
  .pathbar code{
    color:#dbe7f6;
    background:rgba(15,23,42,.78);
    border:1px solid rgba(148,163,184,.18);
    padding:.2rem .45rem;
    border-radius:8px;
  }
  .doc-table-wrap{ overflow:auto; border-radius:18px; }
  .doc-table{
    width:100%; border-collapse:collapse; min-width:900px;
    background:rgba(15,23,42,.46); overflow:hidden; border-radius:18px;
  }
  .doc-table th, .doc-table td{
    padding:.78rem .85rem;
    border-bottom:1px solid rgba(148,163,184,.10);
    vertical-align:middle;
  }
  .doc-table th{
    background:rgba(15,23,42,.98);
    color:#93c5fd;
    font-size:.77rem;
    text-transform:uppercase;
    letter-spacing:.08em;
    position:sticky; top:0;
  }
  .doc-table tr:hover td{ background:rgba(59,130,246,.08); }
  .doc-name{
    display:flex; align-items:center; gap:12px; min-width:0;
  }
  .doc-icon{
    width:38px; height:38px; display:inline-flex; align-items:center; justify-content:center;
    border-radius:12px;
    background:rgba(15,23,42,.92);
    border:1px solid rgba(148,163,184,.16);
    font-size:1.15rem;
    flex:0 0 auto;
  }
  .doc-link{
    color:#eef2ff; text-decoration:none; font-weight:800;
  }
  .doc-link:hover{ color:#7dd3fc; }
  .doc-meta{ color:#94a3b8; font-size:.76rem; margin-top:2px; }
  .type-pill{
    display:inline-flex; align-items:center; justify-content:center;
    padding:.22rem .56rem; border-radius:999px;
    background:rgba(148,163,184,.12); border:1px solid rgba(148,163,184,.20);
    color:#dbe7f6; font-size:.72rem; font-weight:900;
  }
  .search-results{
    display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:14px;
  }
  .result-card{
    border-radius:18px;
    background:linear-gradient(180deg, rgba(15,23,42,.88), rgba(8,13,24,.88));
    border:1px solid rgba(148,163,184,.16);
    padding:16px;
  }
  .result-top{
    display:flex; align-items:flex-start; gap:12px;
  }
  .result-card .doc-icon{ width:42px; height:42px; }
  .result-name{
    color:#eef2ff; font-weight:900; text-decoration:none; line-height:1.28;
    word-break:break-word;
  }
  .result-name:hover{ color:#7dd3fc; }
  .result-path{
    color:#9fb1c9; font-size:.78rem; margin-top:4px; word-break:break-word;
  }
  .result-meta{
    display:flex; flex-wrap:wrap; gap:8px; margin-top:12px; color:#cbd5e1; font-size:.78rem;
  }
  .result-actions{
    display:flex; gap:8px; margin-top:14px; flex-wrap:wrap;
  }
  .empty-state{
    padding:28px 16px; text-align:center; color:#c3d0e2;
    border:1px dashed rgba(148,163,184,.24); border-radius:18px;
    background:rgba(15,23,42,.36);
  }
  @media (max-width: 992px){
    .hero-stats{ grid-template-columns:repeat(2, minmax(0,1fr)); }
    .search-grid{ grid-template-columns:1fr; }
  }
  @media (max-width: 640px){
    .container-main{ padding:12px; }
    .hero-title{ font-size:1.42rem; }
    .hero-stats{ grid-template-columns:1fr; }
  }
</style>
</head>
<body>
<div class="page-bg"></div>

<div class="container-main">
  <section class="hero">
    <div class="hero-top">
      <div>
        <div class="hero-brand">
          <img src="<?= e($ESCUDO) ?>" alt="EA" onerror="this.style.display='none'">
          <div>
            <div class="hero-kicker">Repositorio documental</div>
            <div class="hero-title">Documentacion</div>
            <div class="hero-sub">
              Navegá y buscá archivos dentro de <code><?= e(str_replace('\\', '/', $DOC_ROOT_ABS)) ?></code>.
              Esta vista toma tanto lo cargado desde el sistema como lo agregado directamente en la carpeta compartida.
            </div>
          </div>
        </div>
      </div>

      <div class="hero-actions">
        <a class="btn-soft" href="<?= e($BASE_PUBLIC_WEB) ?>/inicio.php"><i class="bi bi-house-door"></i> Inicio</a>
        <a class="btn-soft" href="<?= e($SELF_WEB) ?>"><i class="bi bi-arrow-counterclockwise"></i> Limpiar vista</a>
      </div>
    </div>

    <div class="hero-stats">
      <div class="stat-card">
        <div class="stat-num"><?= (int)$countFolders ?></div>
        <div class="stat-lbl">Carpetas en esta ruta</div>
      </div>
      <div class="stat-card">
        <div class="stat-num"><?= (int)$countFiles ?></div>
        <div class="stat-lbl">Archivos en esta ruta</div>
      </div>
      <div class="stat-card">
        <div class="stat-num"><?= $searchActive ? (int)count($searchResults) : 0 ?></div>
        <div class="stat-lbl">Resultados del buscador</div>
      </div>
      <div class="stat-card">
        <div class="stat-num"><?= e($sharedState['current'] !== '' ? basename((string)$sharedState['current']) : 'RAIZ') ?></div>
        <div class="stat-lbl">Ruta actual</div>
      </div>
    </div>
  </section>

  <section class="search-shell">
    <div class="search-title"><i class="bi bi-search"></i> Buscador de archivos</div>
    <div class="search-sub">
      Buscá por nombre o ruta, y filtrá por tipo. La búsqueda se hace de forma recursiva dentro de la carpeta actual.
    </div>
    <div class="search-sub" style="margin-top:-4px; margin-bottom:12px;">
      La organizacion por siglas como <b>RFP</b>, <b>MFP</b>, <b>RFD</b> o <b>DIR</b> la podés manejar con subcarpetas. El buscador entra en todas.
    </div>

    <form method="get">
      <input type="hidden" name="dir" value="<?= e((string)$sharedState['current']) ?>">
      <div class="search-grid">
        <input class="form-control" type="text" name="q" value="<?= e($q) ?>" placeholder="Ej: directiva, anexo, orden, boletin, cuadro...">
        <select class="form-select" name="kind">
          <option value="all" <?= $kindFilter === 'all' ? 'selected' : '' ?>>Todos los tipos</option>
          <option value="folder" <?= $kindFilter === 'folder' ? 'selected' : '' ?>>Carpetas</option>
          <option value="pdf" <?= $kindFilter === 'pdf' ? 'selected' : '' ?>>PDF</option>
          <option value="word" <?= $kindFilter === 'word' ? 'selected' : '' ?>>Word</option>
          <option value="excel" <?= $kindFilter === 'excel' ? 'selected' : '' ?>>Excel</option>
          <option value="image" <?= $kindFilter === 'image' ? 'selected' : '' ?>>Imagen</option>
          <option value="text" <?= $kindFilter === 'text' ? 'selected' : '' ?>>Texto</option>
          <option value="archive" <?= $kindFilter === 'archive' ? 'selected' : '' ?>>Comprimido</option>
          <option value="other" <?= $kindFilter === 'other' ? 'selected' : '' ?>>Otros</option>
        </select>
        <button class="btn btn-info" type="submit" style="min-width:140px; border-radius:14px; font-weight:900;">
          <i class="bi bi-search"></i> Buscar
        </button>
        <a class="btn btn-outline-light" href="<?= e($SELF_WEB) ?><?= $sharedState['current'] !== '' ? ('?dir=' . e(rawurlencode((string)$sharedState['current']))) : '' ?>" style="min-width:120px; border-radius:14px; font-weight:800;">
          Limpiar
        </a>
      </div>
    </form>

    <div class="chip-row">
      <?php foreach (['all','pdf','word','excel','image','folder'] as $chipKind): ?>
        <?php
          $chipUrl = $SELF_WEB . '?dir=' . rawurlencode((string)$sharedState['current']) . '&kind=' . rawurlencode($chipKind);
          if ($q !== '') $chipUrl .= '&q=' . rawurlencode($q);
        ?>
        <a class="chip <?= $kindFilter === $chipKind ? 'active' : '' ?>" href="<?= e($chipUrl) ?>">
          <?= e(kind_label($chipKind === 'all' ? 'other' : $chipKind) === 'Otro' ? 'Todos' : kind_label($chipKind)) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <?php if (!$searchActive): ?>
    <section class="panel-box" style="margin-bottom:18px;">
      <div class="panel-head">
        <div>
          <div class="panel-title"><i class="bi bi-folder2-open"></i> Navegacion de carpetas</div>
          <div class="panel-sub">Explorador de la carpeta compartida DOCUMENTACION.</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <?php if ($sharedState['current'] !== ''): ?>
            <?php
              $partsParent = explode('/', (string)$sharedState['current']);
              array_pop($partsParent);
              $parentRel = implode('/', $partsParent);
            ?>
            <a class="btn-soft" href="<?= e($SELF_WEB) ?><?= $parentRel !== '' ? ('?dir=' . e(rawurlencode($parentRel))) : '' ?>"><i class="bi bi-arrow-left"></i> Volver</a>
          <?php endif; ?>
          <a class="btn-soft" href="<?= e($SELF_WEB) ?>"><i class="bi bi-house"></i> Raiz DOCUMENTACION</a>
        </div>
      </div>

      <?php if (!$sharedState['ok']): ?>
        <div class="alert alert-warning mb-0"><?= e((string)$sharedState['error']) ?></div>
      <?php else: ?>
        <div class="pathbar">
          <span><b>Ruta actual:</b></span>
          <a href="<?= e($SELF_WEB) ?>">DOCUMENTACION</a>
          <?php foreach ($segments as $seg): ?>
            <span>/</span>
            <a href="<?= e($SELF_WEB) ?>?dir=<?= e(rawurlencode((string)$seg['rel'])) ?>"><?= e((string)$seg['name']) ?></a>
          <?php endforeach; ?>
        </div>

        <?php if (empty($currentEntries)): ?>
          <div class="empty-state">Esta carpeta esta vacia.</div>
        <?php else: ?>
          <div class="doc-table-wrap">
            <table class="doc-table">
              <thead>
                <tr>
                  <th>Nombre</th>
                  <th style="width:130px;">Tipo</th>
                  <th style="width:170px;">Modificado</th>
                  <th style="width:130px;">Tamano</th>
                  <th style="width:140px;">Accion</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($currentEntries as $entry): ?>
                <?php
                  $isDir = (bool)$entry['is_dir'];
                  $rel = (string)$entry['rel'];
                  $openHref = $isDir
                      ? ($SELF_WEB . '?dir=' . rawurlencode($rel))
                      : ($SELF_WEB . '?download=1&path=' . rawurlencode($rel));
                ?>
                <tr>
                  <td>
                    <div class="doc-name">
                      <div class="doc-icon"><?= kind_icon((string)$entry['kind']) ?></div>
                      <div style="min-width:0;">
                        <a class="doc-link" <?= $isDir ? '' : 'target="_blank" rel="noopener"' ?> href="<?= e($openHref) ?>">
                          <?= e((string)$entry['name']) ?>
                        </a>
                        <div class="doc-meta"><?= e($rel) ?></div>
                      </div>
                    </div>
                  </td>
                  <td><span class="type-pill"><?= e(kind_label((string)$entry['kind'])) ?></span></td>
                  <td><?= e(format_dt_local((int)$entry['mtime'])) ?></td>
                  <td><?= $isDir ? '—' : e(human_size((int)($entry['size'] ?? 0))) ?></td>
                  <td>
                    <a class="btn btn-outline-info btn-sm" <?= $isDir ? '' : 'target="_blank" rel="noopener"' ?> href="<?= e($openHref) ?>">
                      <?= $isDir ? 'Abrir carpeta' : 'Abrir archivo' ?>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <section class="panel-box">
    <div class="panel-head">
      <div>
        <div class="panel-title"><i class="bi bi-stars"></i> <?= $searchActive ? 'Documentos encontrados' : 'Resultados del buscador' ?></div>
        <div class="panel-sub">
          <?= $searchActive ? 'Cuando el filtro está activo, esta vista reemplaza la lista general y muestra solo coincidencias.' : 'Escribí algo o elegí un filtro para activar el buscador.' ?>
        </div>
      </div>
    </div>

    <?php if (!$searchActive): ?>
      <div class="empty-state">
        El buscador todavia no esta activo. Probá con una palabra clave o filtrá por PDF, Word, Excel o Imagen.
      </div>
    <?php elseif (empty($searchResults)): ?>
      <div class="empty-state">
        No encontre coincidencias para <b><?= e($q !== '' ? $q : kind_label($kindFilter)) ?></b> dentro de esta ruta.
      </div>
    <?php else: ?>
      <div class="search-results">
        <?php foreach ($searchResults as $item): ?>
          <?php
            $isDir = (bool)$item['is_dir'];
            $rel = (string)$item['rel'];
            $href = $isDir
                ? ($SELF_WEB . '?dir=' . rawurlencode($rel))
                : ($SELF_WEB . '?download=1&path=' . rawurlencode($rel));
          ?>
          <article class="result-card">
            <div class="result-top">
              <div class="doc-icon"><?= kind_icon((string)$item['kind']) ?></div>
              <div style="min-width:0;">
                <a class="result-name" <?= $isDir ? '' : 'target="_blank" rel="noopener"' ?> href="<?= e($href) ?>">
                  <?= e((string)$item['name']) ?>
                </a>
                <div class="result-path"><?= e($rel) ?></div>
              </div>
            </div>
            <div class="result-meta">
              <span class="type-pill"><?= e(kind_label((string)$item['kind'])) ?></span>
              <span><?= e(format_dt_local((int)$item['mtime'])) ?></span>
              <?php if (!$isDir): ?><span><?= e(human_size((int)($item['size'] ?? 0))) ?></span><?php endif; ?>
            </div>
            <div class="result-actions">
              <a class="btn btn-outline-info btn-sm" <?= $isDir ? '' : 'target="_blank" rel="noopener"' ?> href="<?= e($href) ?>">
                <?= $isDir ? 'Entrar' : 'Abrir' ?>
              </a>
              <?php if (!$isDir): ?>
                <a class="btn btn-outline-light btn-sm" href="<?= e($SELF_WEB) ?>?dir=<?= e(rawurlencode((string)dirname($rel) === '.' ? '' : dirname($rel))) ?>">
                  Ir a carpeta
                </a>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>
</body>
</html>
