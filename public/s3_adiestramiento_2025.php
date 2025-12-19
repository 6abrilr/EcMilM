<?php
// public/s3_adiestramiento_2025.php — PAFB (4 fijas + tarjetas extra + gestión de archivos)
declare(strict_types=1);

$OFFLINE_MODE = false;

require_once __DIR__ . '/../auth/bootstrap.php';
if (!$OFFLINE_MODE) require_login();
require_once __DIR__ . '/../config/db.php';

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function starts_with($h,$n){ return substr($h,0,strlen($n)) === $n; }

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

/* ===== Assets ===== */
$PUBLIC_URL = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$APP_URL    = rtrim(dirname($PUBLIC_URL), '/');
$ASSETS     = ($APP_URL === '' ? '' : $APP_URL) . '/assets';
$IMG_BG     = $ASSETS . '/img/fondo.png';
$ESCUDO     = $ASSETS . '/img/escudo_bcom602.png';

/* ===== Año desde el nombre del archivo ===== */
$YEAR = (int)date('Y');
$base = basename(__FILE__);
if (preg_match('/^s3_adiestramiento_(\d{4})\.php$/', $base, $m)) $YEAR = (int)$m[1];

/* ===== CSRF ===== */
$csrfKey = 'csrf_s3_pafb_' . $YEAR;
if (empty($_SESSION[$csrfKey])) $_SESSION[$csrfKey] = bin2hex(random_bytes(16));
$CSRF = (string)$_SESSION[$csrfKey];

/* ===== Storage (filesystem) =====
   - Archivos: storage/s3_operaciones/pafb/<YEAR>/<cardId>/
   - Meta:     storage/s3_operaciones/pafb/<YEAR>/_meta.json
*/
$projectBase = realpath(__DIR__ . '/..') ?: (__DIR__ . '/..');
$STORAGE_REL_BASE = 'storage/s3_operaciones/pafb/' . $YEAR;
$STORAGE_ABS_BASE = rtrim((string)$projectBase, '/') . '/' . $STORAGE_REL_BASE;
$META_ABS = $STORAGE_ABS_BASE . '/_meta.json';

/* ===== 4 tarjetas fijas (siempre visibles) ===== */
$FIXED_ORDER = ['diag1','c1','diag2','c2'];
$BASE_CARDS = [
  'diag1' => ['title'=>'Diagnóstico 1',          'sub'=>'Evaluación / diagnóstico previo.',            'icon'=>'🧪'],
  'c1'    => ['title'=>'PAFB 1ra comprobación',   'sub'=>'Resultados oficiales del 1er período.',       'icon'=>'🏃‍♂️'],
  'diag2' => ['title'=>'Diagnóstico 2',           'sub'=>'Evaluación / diagnóstico previo a 2da.',      'icon'=>'🧪'],
  'c2'    => ['title'=>'PAFB 2da comprobación',   'sub'=>'Resultados oficiales del 2do período.',       'icon'=>'🏁'],
];

/* ===== Helpers meta/storage ===== */
function ensure_dir(string $absDir): bool {
  if (is_dir($absDir)) return true;
  return @mkdir($absDir, 0775, true);
}
function read_meta(string $metaAbs): array {
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
function rrmdir_safe(string $dirAbs, string $mustStartWithAbs): bool {
  $dirReal = realpath($dirAbs);
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

/* ===== Validaciones archivo ===== */
function is_allowed_ext(string $name): bool {
  $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
  return in_array($ext, ['xlsx','xls','pdf'], true);
}
function ext_of(string $name): string {
  return strtolower(pathinfo($name, PATHINFO_EXTENSION));
}
function safe_filename(string $original): string {
  $ext = ext_of($original);
  $rnd = bin2hex(random_bytes(4));
  $ts  = date('Ymd_His');
  return $ts . '_' . $rnd . '.' . $ext;
}
function is_valid_card_id(string $id): bool {
  // fijo: diag1/c1/diag2/c2 o custom: cx_YYYYmmddHHMMSS_xxxx
  if (in_array($id, ['diag1','c1','diag2','c2'], true)) return true;
  return (bool)preg_match('/^cx_\d{14}_[a-f0-9]{6,12}$/', $id);
}

/* ===== Inicializar meta ===== */
$flashOk = '';
$flashErr = '';

if (!ensure_dir($STORAGE_ABS_BASE)) {
  $flashErr = 'No se pudo crear/acceder a la carpeta de storage: ' . $STORAGE_ABS_BASE . ' (permisos).';
}

$meta = read_meta($META_ABS);
if (!isset($meta['custom_cards']) || !is_array($meta['custom_cards'])) $meta['custom_cards'] = [];
if (!isset($meta['uploads']) || !is_array($meta['uploads'])) $meta['uploads'] = [];

foreach (array_merge($FIXED_ORDER, array_map(fn($c)=> (string)($c['id'] ?? ''), $meta['custom_cards'])) as $cid) {
  if ($cid !== '' && !isset($meta['uploads'][$cid]) ) $meta['uploads'][$cid] = [];
}

if ($flashErr === '' && !is_file($META_ABS)) {
  @ensure_dir(dirname($META_ABS));
  write_meta($META_ABS, $meta);
}

/* ===== POST actions ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $flashErr === '') {
  $action = (string)($_POST['action'] ?? '');
  $token  = (string)($_POST['csrf'] ?? '');

  if (!hash_equals($CSRF, $token)) {
    $flashErr = 'Acción bloqueada (CSRF inválido).';
  } else {

    /* 1) Agregar tarjeta */
    if ($action === 'add_card') {
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
          $flashErr = 'No se pudo guardar meta.json (permisos).';
        } else {
          $flashOk = 'Tarjeta agregada: ' . $title;
        }
      }
    }

    /* 2) Editar tarjeta (solo custom) */
    if ($action === 'edit_card') {
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

        if (!$found) {
          $flashErr = 'No se encontró la tarjeta.';
        } elseif (!write_meta($META_ABS, $meta)) {
          $flashErr = 'No se pudo guardar meta.json (permisos).';
        } else {
          $flashOk = 'Tarjeta actualizada.';
        }
      }
    }

    /* 3) Eliminar tarjeta (solo custom) + borra su carpeta */
    if ($action === 'delete_card') {
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
          // borrar uploads meta + carpeta con archivos
          unset($meta['uploads'][$id]);

          $cardDir = $STORAGE_ABS_BASE . '/' . $id;
          $okDel = rrmdir_safe($cardDir, $STORAGE_ABS_BASE);

          if (!$okDel) {
            $flashErr = 'Se quitó la tarjeta del listado, pero no se pudo borrar su carpeta (permisos).';
            // igual guardamos meta
            write_meta($META_ABS, $meta);
          } elseif (!write_meta($META_ABS, $meta)) {
            $flashErr = 'Tarjeta eliminada, pero no se pudo guardar meta.json (permisos).';
          } else {
            $flashOk = 'Tarjeta eliminada.';
          }
        }
      }
    }

    /* 4) Subir archivos */
    if ($action === 'upload') {
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

              if ($err !== UPLOAD_ERR_OK) { $flashErr = "Error al subir: $name (código $err)."; break; }
              if ($size <= 0) continue;
              if ($size > 25 * 1024 * 1024) { $flashErr = "Archivo demasiado grande (máx 25MB): $name"; break; }
              if (!is_allowed_ext($name)) { $flashErr = "Extensión no permitida: $name (solo .xlsx/.xls/.pdf)."; break; }

              $finalName = safe_filename($name);
              $destAbs   = $destDirAbs . '/' . $finalName;

              if (!@move_uploaded_file($tmp, $destAbs)) { $flashErr = 'No se pudo mover el archivo (permisos storage).'; break; }

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
              elseif (!write_meta($META_ABS, $meta)) $flashErr = 'Se subieron archivos pero no se pudo guardar meta.json (permisos).';
              else $flashOk = "Se subieron {$added} archivo(s).";
            }
          }
        }
      }
    }

    /* 5) Eliminar archivo */
    if ($action === 'delete_file') {
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

        if (!$okPath || !is_file($realAbs)) {
          $flashErr = 'No se encontró el archivo para eliminar.';
        } elseif (!@unlink($realAbs)) {
          $flashErr = 'No se pudo eliminar el archivo (permisos).';
        } else {
          $meta['uploads'][$key] = array_values(array_filter(
            $meta['uploads'][$key] ?? [],
            fn($it) => (string)($it['file'] ?? '') !== $file
          ));

          if (!write_meta($META_ABS, $meta)) $flashErr = 'Archivo eliminado, pero no se pudo actualizar meta.json (permisos).';
          else $flashOk = 'Archivo eliminado correctamente.';
        }
      }
    }
  }
}

/* ===== Helpers URL abrir (SIEMPRE directo, NO ver_tabla.php) ===== */
function file_url(string $storageRelBase, string $key, string $file): string {
  return '../' . $storageRelBase . '/' . rawurlencode($key) . '/' . rawurlencode($file);
}

/* ===== Construcción de cards finales ===== */
$cards = [];

// 4 fijas
foreach ($FIXED_ORDER as $id) {
  $cards[] = [
    'id' => $id,
    'title' => $BASE_CARDS[$id]['title'],
    'sub' => $BASE_CARDS[$id]['sub'],
    'icon' => $BASE_CARDS[$id]['icon'],
    'is_custom' => false,
  ];
}

// extras (custom)
foreach ($meta['custom_cards'] as $cc) {
  $id = (string)($cc['id'] ?? '');
  if ($id === '' || !is_valid_card_id($id)) continue;
  $cards[] = [
    'id' => $id,
    'title' => (string)($cc['title'] ?? 'Tarjeta'),
    'sub' => (string)($cc['sub'] ?? ''),
    'icon' => (string)($cc['icon'] ?? '📌'),
    'is_custom' => true,
  ];
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>S-3 · PAFB <?= e($YEAR) ?> · B Com 602</title>
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
    --warn: #fbbf24;
    --danger: #ef4444;
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
    text-decoration:none;
    white-space:nowrap;
  }
  .btn-ghost:hover{ background:rgba(30,64,175,.9);border-color:rgba(129,140,248,.9);color:white; }

  .btn-add{
    border-radius:999px;
    border:1px solid rgba(56,189,248,.55);
    background:rgba(56,189,248,.14);
    color:#dbeafe;
    font-size:.8rem;font-weight:800;padding:.35rem 1rem;
    box-shadow:0 10px 30px rgba(0,0,0,.55);
    white-space:nowrap;
  }
  .btn-add:hover{ background:rgba(56,189,248,.24); border-color:rgba(56,189,248,.85); color:#fff; }

  .section-header{ margin:18px 0 18px; }
  .section-kicker .sk-text{
    font-size:1.05rem;font-weight:900;letter-spacing:.18em;text-transform:uppercase;
    background:linear-gradient(90deg,#38bdf8,#22c55e);
    -webkit-background-clip:text;-webkit-text-fill-color:transparent;
    filter:drop-shadow(0 0 6px rgba(30,58,138,.55));
    padding-bottom:3px;border-bottom:2px solid rgba(34,197,94,.45);
    display:inline-block;
  }
  .section-title{ font-size:1.6rem;font-weight:900;margin-top:6px; }
  .section-sub{ font-size:.9rem;color:#cbd5f5;max-width:860px; }

  .grid{ display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px; }
  @media (max-width:991px){ .grid{ grid-template-columns:repeat(2,minmax(0,1fr)); } }
  @media (max-width:575px){ .grid{ grid-template-columns:1fr; } }

  .card-s3{
    border-radius:22px;padding:18px 18px 14px;
    background:
      radial-gradient(circle at top left, rgba(56,189,248,.22), transparent 60%),
      radial-gradient(circle at bottom right, rgba(34,197,94,.18), transparent 70%),
      var(--card-bg);
    border:1px solid rgba(15,23,42,.9);
    box-shadow:0 22px 40px rgba(0,0,0,.85), 0 0 0 1px rgba(148,163,184,.35);
    backdrop-filter:blur(12px);
    transition:transform .18s ease-out, box-shadow .18s ease-out, border-color .18s ease-out;
    overflow:hidden;
    position:relative;
  }
  .card-s3:hover{
    transform:translateY(-3px) scale(1.01);
    box-shadow:0 28px 60px rgba(0,0,0,.9), 0 0 0 1px rgba(129,140,248,.65);
    border-color:rgba(129,140,248,.9);
  }

  .top{ display:flex;justify-content:space-between;gap:10px; }
  .title{ font-weight:850;font-size:1rem;margin:0; }
  .sub{ color:var(--text-muted);font-size:.78rem;margin:3px 0 0; }
  .icon{ font-size:1.4rem;line-height:1;filter:drop-shadow(0 0 6px rgba(0,0,0,.7)); }

  .pill{
    font-size:.68rem;text-transform:uppercase;letter-spacing:.16em;
    padding:.15rem .55rem;border-radius:999px;
    border:1px solid rgba(148,163,184,.6);color:#e5e7eb;
    background:rgba(15,23,42,.4);
    display:inline-flex;align-items:center;gap:6px;
    white-space:nowrap;
  }
  .pill.ok{ border-color:rgba(34,197,94,.75); }
  .pill.wait{ border-color:rgba(251,191,36,.75); }

  .actions{ margin-top:14px; display:flex; gap:10px; align-items:center; justify-content:space-between; flex-wrap:wrap; }
  .btn-open{
    display:inline-flex; align-items:center; justify-content:center;
    padding:8px 12px; border-radius:12px; font-weight:900;
    text-decoration:none;
    background:rgba(34,197,94,.2); color:#d1fae5;
    border:1px solid rgba(34,197,94,.45);
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

  .btn-mini{
    border-radius:10px;
    padding:6px 10px;
    font-weight:900;
    font-size:.78rem;
    text-decoration:none;
    border:1px solid rgba(148,163,184,.25);
    background:rgba(15,23,42,.55);
    color:#e5e7eb;
    white-space:nowrap;
  }
  .btn-mini:hover{ background:rgba(30,64,175,.35); border-color:rgba(129,140,248,.45); color:#fff; }
  .btn-danger-mini{
    border-radius:10px;
    padding:6px 10px;
    font-weight:900;
    font-size:.78rem;
    border:1px solid rgba(239,68,68,.40);
    background:rgba(239,68,68,.12);
    color:#fecaca;
    white-space:nowrap;
  }
  .btn-danger-mini:hover{ background:rgba(239,68,68,.20); border-color:rgba(239,68,68,.70); color:#fff; }

  .files{ margin-top:12px; border-top:1px solid rgba(148,163,184,.20); padding-top:10px; }
  .file-item{
    display:flex; align-items:center; justify-content:space-between; gap:10px;
    padding:8px 10px; border-radius:12px;
    background:rgba(2,6,23,.35);
    border:1px solid rgba(148,163,184,.16);
    margin-top:8px;
  }
  .file-left{ min-width:0; }
  .file-name{
    font-size:.82rem; font-weight:800; margin:0;
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
  }
  .file-meta{ font-size:.72rem; color:#cbd5f5; opacity:.9; margin-top:2px; }
  .file-actions{ display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end; }

  .card-tools{
    margin-top:10px;
    display:flex;
    gap:8px;
    justify-content:flex-end;
    flex-wrap:wrap;
    opacity:.95;
  }
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

    <div class="header-actions">
      <button class="btn-add" type="button" id="btnAddCard">➕ Agregar tarjeta</button>
      <a href="s3_adiestramiento.php" class="btn-ghost">⬅ Volver a PAFB</a>
      <a href="areas_s3.php" class="btn-ghost">Volver a S-3</a>
      <a href="areas.php" class="btn-ghost">Volver a Áreas</a>
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
      <div class="section-kicker"><span class="sk-text">PAFB <?= e($YEAR) ?></span></div>
      <div class="section-title">Comprobaciones y diagnósticos</div>
      <div class="section-sub">
        Las 4 tarjetas base se muestran siempre. Podés agregar tarjetas extra (ej: <b>AFI <?= e($YEAR) ?></b>).
        Subís <b>Excel/PDF</b> con referencia y se abren <b>directo</b> (sin ver_tabla.php).
      </div>
    </div>

    <div class="grid">
      <?php foreach ($cards as $card):
        $id = (string)$card['id'];
        $uploads = $meta['uploads'][$id] ?? [];
        $hasFiles = !empty($uploads);

        $last = $hasFiles ? $uploads[count($uploads)-1] : null;
        $lastFile = (string)($last['file'] ?? '');
        $openHref = ($hasFiles && $lastFile !== '') ? file_url($STORAGE_REL_BASE, $id, $lastFile) : '#';
      ?>
        <article class="card-s3">
          <div class="top">
            <div>
              <h3 class="title"><?= e($card['title']) ?></h3>
              <div class="sub"><?= e($card['sub']) ?></div>
            </div>
            <div class="d-flex flex-column align-items-end gap-1">
              <div class="icon"><?= e($card['icon']) ?></div>
              <?php if ($hasFiles): ?>
                <span class="pill ok">Cargado</span>
              <?php else: ?>
                <span class="pill wait">Pendiente</span>
              <?php endif; ?>
            </div>
          </div>

          <div class="actions">
            <a class="btn-open <?= $hasFiles ? '' : 'disabled' ?>"
               href="<?= e($openHref) ?>" target="_blank" rel="noopener">
              Abrir último
            </a>

            <button type="button"
                    class="btn-upload js-open-upload"
                    data-key="<?= e($id) ?>"
                    data-title="<?= e($card['title']) ?>">
              Subir
            </button>
          </div>

          <?php if (!empty($card['is_custom'])): ?>
            <div class="card-tools">
              <button type="button"
                      class="btn-mini js-edit-card"
                      data-id="<?= e($id) ?>"
                      data-title="<?= e($card['title']) ?>"
                      data-sub="<?= e($card['sub']) ?>"
                      data-icon="<?= e($card['icon']) ?>">
                Editar
              </button>

              <form method="post" class="m-0 js-delete-card" data-title="<?= e($card['title']) ?>">
                <input type="hidden" name="action" value="delete_card">
                <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
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
                  $href = ($file !== '') ? file_url($STORAGE_REL_BASE, $id, $file) : '#';
                  $isPdf = strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'pdf';
              ?>
                <div class="file-item">
                  <div class="file-left">
                    <p class="file-name" title="<?= e($orig) ?>">
                      <?= $isPdf ? '📄' : '📊' ?> <?= e($orig) ?>
                    </p>
                    <div class="file-meta">
                      <?= $ref !== '' ? ('Ref: <b>' . e($ref) . '</b> · ') : '' ?>
                      <?= $at !== '' ? e($at) : '' ?>
                    </div>
                  </div>

                  <div class="file-actions">
                    <a class="btn-mini" href="<?= e($href) ?>" target="_blank" rel="noopener">Abrir</a>

                    <form method="post" class="m-0 js-delete-file" data-orig="<?= e($orig) ?>">
                      <input type="hidden" name="action" value="delete_file">
                      <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
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

  </div>
</div>

<!-- Modal Subida -->
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
          <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
          <input type="hidden" name="key" id="uploadKey" value="">

          <div class="mb-3">
            <label class="form-label fw-bold">Referencia (opcional)</label>
            <input type="text" name="ref" class="form-control" placeholder="Ej: AFI <?= e($YEAR) ?> / Acta / Orden / Observación">
          </div>

          <div class="mb-2">
            <label class="form-label fw-bold">Archivos (Excel/PDF)</label>
            <input type="file" name="files[]" class="form-control" multiple
                   accept=".xlsx,.xls,.pdf,application/pdf,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel">
            <div class="form-text text-light" style="opacity:.75">
              Permitidos: .xlsx, .xls, .pdf (máx 25MB c/u).
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

<!-- Modal Agregar/Editar Tarjeta -->
<div class="modal fade" id="cardModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="background:rgba(15,23,42,.96); color:#e5e7eb; border:1px solid rgba(148,163,184,.25); border-radius:16px;">
      <div class="modal-header" style="border-color:rgba(148,163,184,.18);">
        <h5 class="modal-title fw-bold" id="cardModalTitle">Agregar tarjeta</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <form method="post" id="cardModalForm">
        <div class="modal-body">
          <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
          <input type="hidden" name="action" id="cardAction" value="add_card">
          <input type="hidden" name="id" id="cardId" value="">

          <div class="mb-3">
            <label class="form-label fw-bold">Nombre de la tarjeta</label>
            <input type="text" name="title" id="cardTitle" class="form-control" placeholder="Ej: AFI <?= e($YEAR) ?>" required>
          </div>

          <div class="mb-3">
            <label class="form-label fw-bold">Descripción (opcional)</label>
            <input type="text" name="sub" id="cardSub" class="form-control" placeholder="Ej: Evaluación física integral / documentación del ejercicio">
          </div>

          <div class="mb-1">
            <label class="form-label fw-bold">Ícono (opcional)</label>
            <input type="text" name="icon" id="cardIcon" class="form-control" placeholder="Ej: 📌 / 🧾 / 🪖">
            <div class="form-text text-light" style="opacity:.75">
              Podés poner un emoji. Si lo dejás vacío, usa 📌.
            </div>
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
  // Upload modal
  const uploadModalEl = document.getElementById('uploadModal');
  const uploadModal = new bootstrap.Modal(uploadModalEl);
  const uploadTitleEl = document.getElementById('uploadModalTitle');
  const uploadKeyInput = document.getElementById('uploadKey');

  document.querySelectorAll('.js-open-upload').forEach(btn => {
    btn.addEventListener('click', () => {
      const key = btn.getAttribute('data-key') || '';
      const t   = btn.getAttribute('data-title') || 'Subir archivos';
      uploadKeyInput.value = key;
      uploadTitleEl.textContent = `Subir archivos · ${t}`;
      uploadModal.show();
    });
  });

  // Card modal (add/edit)
  const cardModalEl = document.getElementById('cardModal');
  const cardModal = new bootstrap.Modal(cardModalEl);
  const cardModalTitle = document.getElementById('cardModalTitle');
  const cardAction = document.getElementById('cardAction');
  const cardId = document.getElementById('cardId');
  const cardTitle = document.getElementById('cardTitle');
  const cardSub = document.getElementById('cardSub');
  const cardIcon = document.getElementById('cardIcon');
  const cardSaveBtn = document.getElementById('cardSaveBtn');

  document.getElementById('btnAddCard').addEventListener('click', () => {
    cardModalTitle.textContent = 'Agregar tarjeta';
    cardAction.value = 'add_card';
    cardId.value = '';
    cardTitle.value = '';
    cardSub.value = '';
    cardIcon.value = '';
    cardSaveBtn.textContent = 'Crear';
    cardModal.show();
  });

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
