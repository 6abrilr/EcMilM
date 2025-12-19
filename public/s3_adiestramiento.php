<?php
// public/s3_adiestramiento.php — Panel Adiestramiento físico-militar (PAFB) · S-3
declare(strict_types=1);

$OFFLINE_MODE = false;

require_once __DIR__ . '/../auth/bootstrap.php';
if (!$OFFLINE_MODE) require_login();
require_once __DIR__ . '/../config/db.php';

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

/* ===== Assets ===== */
$PUBLIC_URL = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$APP_URL    = rtrim(dirname($PUBLIC_URL), '/');
$ASSETS     = ($APP_URL === '' ? '' : $APP_URL) . '/assets';
$IMG_BG     = $ASSETS . '/img/fondo.png';
$ESCUDO     = $ASSETS . '/img/escudo_bcom602.png';

/* ===== CSRF simple ===== */
if (empty($_SESSION['csrf_s3_adiestramiento'])) {
  $_SESSION['csrf_s3_adiestramiento'] = bin2hex(random_bytes(16));
}
$CSRF = (string)$_SESSION['csrf_s3_adiestramiento'];

function safe_redirect(string $url): void { header('Location: ' . $url); exit; }
function starts_with(string $h, string $n): bool { return substr($h, 0, strlen($n)) === $n; }

/* ==========================================================
   DOCUMENTOS PAFB (PDF) — multi-archivo + referencia
   Guardado en: storage/s3_operaciones/pafb/_docs/<docKey>/
   Meta:         storage/s3_operaciones/pafb/_docs/<docKey>/_meta.json
   ========================================================== */
$projectBase = realpath(__DIR__ . '/..') ?: (__DIR__ . '/..');

$DOCS_REL_DIR = 'storage/s3_operaciones/pafb/_docs';
$DOCS_ABS_DIR = rtrim((string)$projectBase, '/') . '/' . $DOCS_REL_DIR;
$DOCS_URL_DIR = '../' . $DOCS_REL_DIR;

/** Límite en código (POR ARCHIVO). OJO: el server puede limitar antes. */
$MAX_DOC_MB = 80;
$MAX_DOC_BYTES = $MAX_DOC_MB * 1024 * 1024;

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
];

function ensure_dir(string $absDir): bool {
  if (is_dir($absDir)) return true;
  return @mkdir($absDir, 0775, true);
}
function is_pdf_name(string $name): bool {
  return strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'pdf';
}
function safe_pdf_filename(string $original): string {
  // siempre guardamos como .pdf (evita nombres raros)
  $rnd = bin2hex(random_bytes(4));
  $ts  = date('Ymd_His');
  return $ts . '_' . $rnd . '.pdf';
}
function read_meta(string $metaAbs): array {
  if (!is_file($metaAbs)) return ['uploads' => []];
  $raw = @file_get_contents($metaAbs);
  if ($raw === false) return ['uploads' => []];
  $j = json_decode($raw, true);
  if (!is_array($j)) return ['uploads' => []];
  if (!isset($j['uploads']) || !is_array($j['uploads'])) $j['uploads'] = [];
  return $j;
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
function is_valid_doc_key(string $k, array $DOCS): bool {
  return isset($DOCS[$k]);
}

/* ===== Detectar años disponibles según archivos s3_adiestramiento_YYYY.php ===== */
$files  = glob(__DIR__ . '/s3_adiestramiento_*.php') ?: [];
$yearsMap = [];

foreach ($files as $f) {
  $base = basename($f);
  if (preg_match('/^s3_adiestramiento_(\d{4})\.php$/', $base, $m)) {
    $y = (int)$m[1];
    $yearsMap[$y] = true;
  }
}

if (empty($yearsMap)) {
  $yearsMap[(int)date('Y')] = true;
}

$allYears = array_keys($yearsMap);
rsort($allYears);

$limitRecent = 3;
$yearsToShow = array_slice($allYears, 0, $limitRecent);
$oldYears    = array_slice($allYears, $limitRecent);

/* ===== Flash ===== */
$flashOk  = '';
$flashErr = '';

/* ===== server limits info (para avisar en UI/errores) ===== */
$ini_upload = (string)ini_get('upload_max_filesize');
$ini_post   = (string)ini_get('post_max_size');
$ini_max_upload_b = parse_ini_size_to_bytes($ini_upload);
$ini_post_b       = parse_ini_size_to_bytes($ini_post);

/* ==========================================================
   POST actions:
   - upload_doc (multi + referencia)
   - delete_doc_file (borra un archivo puntual)
   - delete_year
   ========================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = (string)($_POST['action'] ?? '');
  $token  = (string)($_POST['csrf'] ?? '');

  if (!hash_equals($CSRF, $token)) {
    $flashErr = 'Acción bloqueada (token inválido).';
  } else {

    /* ===== Subir PDF(s) a una tarjeta (multi + referencia) ===== */
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
          $meta = read_meta($metaAbs);

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
                        . 'Ajustá upload_max_filesize y post_max_size. '
                        . "Actual: upload_max_filesize={$ini_upload}, post_max_size={$ini_post}.";
              break;
            }
            if ($err !== UPLOAD_ERR_OK) { $flashErr = "Error al subir {$name} (código {$err})."; break; }
            if ($size <= 0) continue;

            if (!is_pdf_name($name)) { $flashErr = "Solo PDF permitido: {$name}."; break; }

            if ($size > $MAX_DOC_BYTES) {
              $flashErr = "Archivo demasiado grande: {$name} (" . human_mb($size) . "). Máximo por archivo: {$MAX_DOC_MB}MB.";
              break;
            }

            $final = safe_pdf_filename($name);
            $destAbs = $docDirAbs . '/' . $final;

            if (!@move_uploaded_file($tmp, $destAbs)) {
              $flashErr = 'No se pudo guardar el PDF (permisos en storage).';
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
            if ($added === 0) {
              $flashErr = 'No se subió ningún archivo (verificá selección).';
            } elseif (!write_meta($metaAbs, $meta)) {
              $flashErr = 'Se subieron archivos pero no se pudo guardar _meta.json (permisos).';
            } else {
              safe_redirect('s3_adiestramiento.php?doc_ok=' . rawurlencode($key));
            }
          }
        }
      }
    }

    /* ===== Eliminar un archivo puntual de un doc ===== */
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
          $meta = read_meta($metaAbs);
          $meta['uploads'] = array_values(array_filter(
            $meta['uploads'],
            fn($it) => (string)($it['file'] ?? '') !== $file
          ));
          // si no puede guardar meta, igual lo borró del disco
          @write_meta($metaAbs, $meta);

          safe_redirect('s3_adiestramiento.php?doc_deleted=' . rawurlencode($key));
        }
      }
    }

    /* ===== Eliminar año (cualquier año) ===== */
    if ($action === 'delete_year') {
      $yearToDelete = (int)($_POST['year'] ?? 0);

      if ($yearToDelete < 2000 || $yearToDelete > 2100) {
        $flashErr = 'Año inválido.';
      } elseif (!in_array($yearToDelete, $allYears, true)) {
        $flashErr = 'Ese año no existe en el panel.';
      } else {
        $target = __DIR__ . '/s3_adiestramiento_' . $yearToDelete . '.php';
        $realTarget = realpath($target);

        $publicDir = realpath(__DIR__);
        $okPath = false;

        if ($realTarget && $publicDir) {
          $prefix = $publicDir . DIRECTORY_SEPARATOR;
          $okPath = (strncmp($realTarget, $prefix, strlen($prefix)) === 0);
        }

        if (!$okPath || !is_file((string)$realTarget)) {
          $flashErr = 'No se encontró el archivo a eliminar.';
        } else {
          if (!preg_match('/^s3_adiestramiento_(\d{4})\.php$/', basename((string)$realTarget))) {
            $flashErr = 'Archivo no permitido para eliminación.';
          } else {
            if (@unlink((string)$realTarget)) {
              safe_redirect('s3_adiestramiento.php?deleted=' . rawurlencode((string)$yearToDelete));
            } else {
              $flashErr = 'No se pudo eliminar. Verificá permisos (www-data).';
            }
          }
        }
      }
    }
  }
}

/* ===== Mensajes por querystring ===== */
if (isset($_GET['deleted'])) {
  $y = (int)$_GET['deleted'];
  if ($y > 0) $flashOk = "Se eliminó el año {$y}.";
}
if (isset($_GET['doc_ok'])) {
  $k = (string)$_GET['doc_ok'];
  if (isset($DOCS[$k])) $flashOk = "Se subieron/actualizaron archivos en: {$DOCS[$k]['title']}.";
}
if (isset($_GET['doc_deleted'])) {
  $k = (string)$_GET['doc_deleted'];
  if (isset($DOCS[$k])) $flashOk = "Se eliminó un archivo de: {$DOCS[$k]['title']}.";
}

/* ===== Estado/listado de docs ===== */
$docsState = [];
foreach ($DOCS as $k => $d) {
  $docDirAbs = $DOCS_ABS_DIR . '/' . $k;
  $metaAbs   = $docDirAbs . '/_meta.json';

  $meta = read_meta($metaAbs);

  // filtrar entradas que ya no existen en disco
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
      'url'  => $DOCS_URL_DIR . '/' . rawurlencode($k) . '/' . rawurlencode($file),
    ];
  }

  // ordenar por fecha desc (si no hay fecha, queda como vino)
  usort($uploads, function($a, $b){
    return strcmp((string)($b['at'] ?? ''), (string)($a['at'] ?? ''));
  });

  $docsState[$k] = [
    'count' => count($uploads),
    'totalSize' => $totalSize,
    'uploads' => $uploads,
  ];
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>S-3 · Adiestramiento físico-militar · B Com 602</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/theme-602.css">
<link rel="icon" type="image/png" href="../assets/img/bcom602.png">

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
  .header-back{ display:flex;flex-wrap:wrap;gap:8px; }
  .btn-ghost{
    border-radius:999px;border:1px solid rgba(148,163,184,.55);
    background:rgba(15,23,42,.8);color:var(--text-main);
    font-size:.8rem;font-weight:600;padding:.35rem 1rem;
    box-shadow:0 10px 30px rgba(0,0,0,.55);
    text-decoration:none;
  }
  .btn-ghost:hover{ background:rgba(30,64,175,.9);border-color:rgba(129,140,248,.9);color:white; }

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
  .section-title{ font-size:1.6rem;font-weight:800;margin-top:2px; }
  .section-sub{ font-size:.9rem;color:#cbd5f5;max-width:760px; }

  .docs-grid{ display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:18px; margin: 10px 0 18px; }
  @media (max-width:991px){ .docs-grid{ grid-template-columns:1fr; } }

  .modules-grid{ display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:18px; }
  @media (max-width:991px){ .modules-grid{ grid-template-columns:repeat(2,minmax(0,1fr)); } }
  @media (max-width:575px){ .modules-grid{ grid-template-columns:1fr; } }

  .card-s3{
    position:relative;border-radius:22px;padding:18px 18px 16px;
    background:
      radial-gradient(circle at top left, rgba(56,189,248,.22), transparent 60%),
      radial-gradient(circle at bottom right, rgba(34,197,94,.18), transparent 70%),
      var(--card-bg);
    border:1px solid rgba(15,23,42,.9);
    box-shadow:0 22px 40px rgba(0,0,0,.85), 0 0 0 1px rgba(148,163,184,.35);
    backdrop-filter:blur(12px);
    transition:transform .18s ease-out, box-shadow .18s ease-out, border-color .18s ease-out;
    overflow:hidden;height:100%;
    display:flex;flex-direction:column;justify-content:space-between;
  }
  .card-s3:hover{
    transform:translateY(-4px) scale(1.01);
    box-shadow:0 28px 60px rgba(0,0,0,.9), 0 0 0 1px rgba(129,140,248,.65);
    border-color:rgba(129,140,248,.9);
  }

  .card-topline{ display:flex;align-items:flex-start;justify-content:space-between;gap:10px; }
  .card-icon{ font-size:1.4rem;line-height:1;filter:drop-shadow(0 0 6px rgba(0,0,0,.7)); }
  .card-title{ font-weight:750;font-size:.95rem;margin-bottom:2px; }
  .card-sub{ font-size:.76rem;color:var(--text-muted); }

  .actions-row{
    display:flex;align-items:center;justify-content:space-between;
    gap:10px;margin-top:14px;flex-wrap:wrap;
  }
  .btn-open{
    display:inline-flex;align-items:center;justify-content:center;
    padding:8px 12px;border-radius:12px;font-weight:800;text-decoration:none;
    background:rgba(34,197,94,.2);color:#d1fae5;
    border:1px solid rgba(34,197,94,.45);white-space:nowrap;
  }
  .btn-open:hover{ background:rgba(34,197,94,.32); border-color:rgba(34,197,94,.7); color:white; }

  .btn-del{
    border-radius:12px;padding:8px 12px;font-weight:900;
    border:1px solid rgba(239,68,68,.45);
    background:rgba(239,68,68,.12);color:#fecaca;white-space:nowrap;
  }
  .btn-del:hover{ background:rgba(239,68,68,.20); border-color:rgba(239,68,68,.7); color:#fff; }

  .btn-edit{
    border-radius:12px;padding:8px 12px;font-weight:900;
    border:1px solid rgba(56,189,248,.45);
    background:rgba(56,189,248,.12);color:#bae6fd;white-space:nowrap;
    text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:.4rem;
  }
  .btn-edit:hover{ background:rgba(56,189,248,.18); border-color:rgba(56,189,248,.7); color:#fff; }

  .doc-badge{
    display:inline-flex;align-items:center;gap:.35rem;font-size:.72rem;color:#e5e7eb;
    padding:.18rem .55rem;border-radius:999px;border:1px solid rgba(148,163,184,.45);
    background:rgba(15,23,42,.35);
  }
  .doc-badge.ok{ border-color:rgba(34,197,94,.55); background:rgba(34,197,94,.10); color:#bbf7d0; }
  .doc-badge.missing{ border-color:rgba(251,191,36,.55); background:rgba(251,191,36,.10); color:#fde68a; }

  .files-list{ margin-top:10px; display:flex; flex-direction:column; gap:8px; }
  .file-item{
    display:flex; align-items:flex-start; justify-content:space-between; gap:10px;
    padding:8px 10px; border-radius:14px;
    border:1px solid rgba(148,163,184,.28);
    background:rgba(2,6,23,.28);
  }
  .file-left{ min-width:0; }
  .file-name{ font-size:.82rem; font-weight:800; line-height:1.2; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:520px; }
  .file-meta{ font-size:.72rem; color:#cbd5f5; opacity:.85; }
  .file-actions{ display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
  .btn-mini{
    border-radius:10px; padding:6px 10px; font-weight:900; font-size:.78rem;
    border:1px solid rgba(148,163,184,.35);
    background:rgba(15,23,42,.55); color:#e5e7eb; text-decoration:none; white-space:nowrap;
  }
  .btn-mini:hover{ background:rgba(30,64,175,.7); border-color:rgba(129,140,248,.65); color:white; }
  .btn-mini-danger{
    border-radius:10px; padding:6px 10px; font-weight:900; font-size:.78rem;
    border:1px solid rgba(239,68,68,.45);
    background:rgba(239,68,68,.12); color:#fecaca; white-space:nowrap;
  }
  .btn-mini-danger:hover{ background:rgba(239,68,68,.20); border-color:rgba(239,68,68,.7); color:#fff; }
</style>
</head>
<body>

<header class="brand-hero">
  <div class="hero-inner">
    <div class="brand-left">
      <img src="<?= e($ESCUDO) ?>" class="brand-logo" alt="Escudo B Com 602">
      <div>
        <div class="brand-title">Batallón de Comunicaciones 602</div>
        <div class="brand-sub">“Hogar de las Comunicaciones Fijas del Ejército”</div>
      </div>
    </div>
    <div class="header-back">
      <a href="areas_s3.php" class="btn btn-ghost">⬅ Volver a S-3</a>
      <a href="areas.php" class="btn btn-ghost">Volver a Áreas</a>
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

    <div class="section-header">
      <div class="section-kicker"><span class="sk-text">S-3 · OPERACIONES</span></div>
      <div class="section-title">Adiestramiento físico-militar (PAFB)</div>
      <p class="section-sub mb-2">
        Seleccioná un año. Dentro vas a encontrar la <b>1ra</b> y <b>2da</b> comprobación, y
        los <b>diagnósticos</b> (si tu unidad los rindió).
      </p>

      <?php if (!empty($oldYears)): ?>
        <button class="btn btn-ghost mt-2" type="button"
                data-bs-toggle="collapse" data-bs-target="#oldYearsBox"
                aria-expanded="false" aria-controls="oldYearsBox">
          Ver años viejos (<?= (int)count($oldYears) ?>)
        </button>
      <?php endif; ?>
    </div>

    <!-- =======================
         DOCUMENTACIÓN (2 tarjetas)
         ======================= -->
    <div class="docs-grid">
      <?php foreach ($DOCS as $k => $d):
        $st = $docsState[$k] ?? ['count'=>0,'totalSize'=>0,'uploads'=>[]];
        $badgeCls = ($st['count'] > 0) ? 'ok' : 'missing';
        $badgeTxt = ($st['count'] > 0) ? 'Cargado' : 'No cargado';
      ?>
        <article class="card-s3">
          <div class="card-topline">
            <div>
              <div class="card-title"><?= e($d['title']) ?></div>
              <div class="card-sub"><?= e($d['sub']) ?></div>
              <div class="mt-2">
                <span class="doc-badge <?= e($badgeCls) ?>">
                  <?= ($st['count'] > 0) ? '✅' : '⚠️' ?> <?= e($badgeTxt) ?>
                </span>
                <span class="doc-badge ms-2">
                  📎 <?= (int)$st['count'] ?> archivo(s)
                </span>
                <?php if ((int)$st['totalSize'] > 0): ?>
                  <span class="doc-badge ms-2">
                    📦 <?= e(human_mb((int)$st['totalSize'])) ?>
                  </span>
                <?php endif; ?>
              </div>

              <?php if (!empty($st['uploads'])): ?>
                <div class="files-list">
                  <?php
                    $toShow = array_slice($st['uploads'], 0, 3);
                    foreach ($toShow as $it):
                  ?>
                    <div class="file-item">
                      <div class="file-left">
                        <div class="file-name"><?= e($it['orig']) ?></div>
                        <div class="file-meta">
                          <span><b>Ref:</b> <?= e($it['ref']) ?></span>
                          <?php if ($it['at'] !== ''): ?>
                            · <span><?= e($it['at']) ?></span>
                          <?php endif; ?>
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
                          <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
                          <input type="hidden" name="doc_key" value="<?= e($k) ?>">
                          <input type="hidden" name="file" value="<?= e($it['file']) ?>">
                          <button type="submit" class="btn-mini-danger">Eliminar</button>
                        </form>
                      </div>
                    </div>
                  <?php endforeach; ?>

                  <?php if (count($st['uploads']) > 3): ?>
                    <div class="file-meta" style="opacity:.8">
                      Mostrando 3 de <?= (int)count($st['uploads']) ?> archivos…
                    </div>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </div>

            <div class="d-flex flex-column align-items-end gap-1">
              <div class="card-icon"><?= e($d['icon']) ?></div>
            </div>
          </div>

          <div class="actions-row">
            <button type="button"
                    class="btn-edit js-open-upload"
                    data-doc="<?= e($k) ?>"
                    data-title="<?= e($d['title']) ?>">
              ⬆️ Subir archivos
            </button>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <!-- =======================
         AÑOS (top 3) + NUEVO AÑO
         ======================= -->
    <div class="modules-grid">
      <?php
      $currentYear = (int)date('Y');
      foreach ($yearsToShow as $year):
        if ($year === $currentYear) { $sub = 'PAFB en ejecución.'; $icon = '📈'; }
        elseif ($year === $currentYear - 1) { $sub = 'Resultados / documentación del año anterior.'; $icon = '📊'; }
        elseif ($year < $currentYear - 1) { $sub = 'Referencias y antecedentes de PAFB.'; $icon = '📚'; }
        else { $sub = 'Configurado a futuro (sin datos cargados).'; $icon = '🗓️'; }

        $openHref = 's3_adiestramiento_' . $year . '.php';
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
              <a class="btn-open" href="<?= e($openHref) ?>">Entrar</a>

              <form method="post" class="m-0 js-delete-year" data-year="<?= (int)$year ?>">
                <input type="hidden" name="action" value="delete_year">
                <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
                <input type="hidden" name="year" value="<?= (int)$year ?>">
                <button type="submit" class="btn-del">Eliminar</button>
              </form>
            </div>
          </article>
        </div>
      <?php endforeach; ?>

      <div>
        <a href="s3_adiestramiento_nuevo.php" style="text-decoration:none;color:inherit;display:block;height:100%;">
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
              <span class="btn-del" style="opacity:.35; pointer-events:none;">—</span>
            </div>
          </article>
        </a>
      </div>
    </div>

    <?php if (!empty($oldYears)): ?>
      <div class="collapse mt-3" id="oldYearsBox">
        <div class="alert alert-secondary" style="background:rgba(15,23,42,.75); border-color:rgba(148,163,184,.35); color:#e5e7eb;">
          <b>Años viejos:</b> quedan ocultos para no ensuciar el panel. Desde acá podés abrirlos o eliminarlos.
          <br><span class="text-warning">Eliminar borra el archivo PHP del año.</span>
        </div>

        <div class="modules-grid">
          <?php foreach ($oldYears as $year):
            $openHref = 's3_adiestramiento_' . $year . '.php';
          ?>
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
                  <a class="btn-open" href="<?= e($openHref) ?>">Abrir</a>

                  <form method="post" class="m-0 js-delete-year" data-year="<?= (int)$year ?>">
                    <input type="hidden" name="action" value="delete_year">
                    <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
                    <input type="hidden" name="year" value="<?= (int)$year ?>">
                    <button type="submit" class="btn-del">Eliminar</button>
                  </form>
                </div>
              </article>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

  </div>
</div>

<!-- Modal subir PDFs -->
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
          <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
          <input type="hidden" name="doc_key" id="uploadDocKey" value="">

          <div class="mb-2">
            <label class="form-label fw-bold mb-1">Referencia (obligatoria)</label>
            <input type="text"
                   name="doc_ref"
                   class="form-control"
                   maxlength="180"
                   placeholder="Ej: Directiva 828/20 - Actualización 2025 / Fuente: ... / Observación ..."
                   required>
          </div>

          <div class="mb-2">
            <label class="form-label fw-bold mb-1">Archivo(s) PDF</label>
            <input type="file"
                   name="doc_files[]"
                   class="form-control"
                   accept="application/pdf"
                   multiple
                   required>
            <div class="form-text text-light" style="opacity:.75">
              Podés seleccionar varios PDFs en una misma subida.
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {
  // eliminar año
  document.querySelectorAll('form.js-delete-year').forEach(form => {
    form.addEventListener('submit', (ev) => {
      ev.preventDefault();
      const year = form.getAttribute('data-year') || form.querySelector('input[name="year"]')?.value || '';
      const fileName = `s3_adiestramiento_${year}.php`;

      Swal.fire({
        title: 'Confirmar eliminación',
        html: `
          <div style="text-align:left">
            <div>¿Querés eliminar el año <b>${year}</b>?</div>
            <div class="mt-2">Se borrará el archivo:</div>
            <div><code>${fileName}</code></div>
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

  // modal subir doc
  const uploadModalEl = document.getElementById('uploadDocModal');
  const uploadModal = new bootstrap.Modal(uploadModalEl);
  const keyInput = document.getElementById('uploadDocKey');
  const titleEl  = document.getElementById('uploadDocTitle');

  document.querySelectorAll('.js-open-upload').forEach(btn => {
    btn.addEventListener('click', () => {
      const k = btn.getAttribute('data-doc') || '';
      const t = btn.getAttribute('data-title') || 'Subir documentos';
      keyInput.value = k;
      titleEl.textContent = `Subir PDF(s) · ${t}`;
      // reset campos del modal
      const form = uploadModalEl.querySelector('form');
      if (form) form.reset();
      uploadModal.show();
    });
  });

  // eliminar archivo puntual
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
});
</script>

</body>
</html>
