<?php
// public/admin/administrar_archivos.php
// Explorador nivel 2 para /storage/unidades/ecmilm (carpetas + archivos)
declare(strict_types=1);

require_once __DIR__ . '/../../auth/bootstrap.php';
require_login();
require_once __DIR__ . '/../../config/db.php';

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function norm_dni(string $dni): string { return preg_replace('/\D+/', '', $dni); }

/* ==========================================================
   BASE WEB (robusto)
   ========================================================== */
$SELF_WEB        = (string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? ''); // /ea/public/admin/administrar_archivos.php
$BASE_ADMIN_WEB  = rtrim(str_replace('\\','/', dirname($SELF_WEB)), '/');            // /ea/public/admin
$BASE_PUBLIC_WEB = rtrim(str_replace('\\','/', dirname($BASE_ADMIN_WEB)), '/');      // /ea/public
$BASE_APP_WEB    = rtrim(str_replace('\\','/', dirname($BASE_PUBLIC_WEB)), '/');     // /ea
$ASSET_WEB       = $BASE_PUBLIC_WEB . '/assets';                                    // /ea/public/assets

/* ==========================================================
   BASE FS (root del proyecto ea/)
   ========================================================== */
$ROOT_FS = realpath(__DIR__ . '/../../'); // .../ea
if (!$ROOT_FS) {
  http_response_code(500);
  exit("No se pudo resolver ROOT del proyecto.");
}

/* ===== storage objetivo ===== */
$storageBase = realpath($ROOT_FS . '/storage/unidades/ecmilm');
if (!$storageBase || !is_dir($storageBase)) {
  http_response_code(500);
  exit("No existe /storage/unidades/ecmilm o no es directorio.");
}

/* ==========================================================
   Restricción de acceso: SOLO roles ADMIN o SUPERADMIN (tabla roles)
   ========================================================== */
$user    = function_exists('current_user') ? current_user() : ($_SESSION['user'] ?? null);
$dniNorm = norm_dni((string)($user['dni'] ?? $user['username'] ?? ''));

$personalId   = 0;
$unidadPropia = 0;

try {
  if ($dniNorm !== '') {
    $st = $pdo->prepare("
      SELECT id, unidad_id
      FROM personal_unidad
      WHERE REPLACE(REPLACE(REPLACE(dni,'.',''),'-',''),' ','') = :dni
      LIMIT 1
    ");
    $st->execute([':dni' => $dniNorm]);
    if ($r = $st->fetch(PDO::FETCH_ASSOC)) {
      $personalId   = (int)($r['id'] ?? 0);
      $unidadPropia = (int)($r['unidad_id'] ?? 0);
    }
  }
} catch (Throwable $e) {}

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
    if (is_string($c) && $c !== '') $roleCodigo = $c;
  }
} catch (Throwable $e) {}

// fallback histórico a usuario_roles
if ($roleCodigo === 'USUARIO') {
  try {
    if ($personalId > 0) {
      $st = $pdo->prepare("
        SELECT r.codigo
        FROM usuario_roles ur
        INNER JOIN roles r ON r.id = ur.role_id
        WHERE ur.personal_id = :pid
          AND (ur.unidad_id IS NULL OR ur.unidad_id = :uid)
        ORDER BY
          CASE r.codigo WHEN 'SUPERADMIN' THEN 3 WHEN 'ADMIN' THEN 2 ELSE 1 END DESC,
          ur.created_at DESC, ur.id DESC
        LIMIT 1
      ");
      $st->execute([':pid' => $personalId, ':uid' => $unidadPropia]);
      $c = $st->fetchColumn();
      if (is_string($c) && $c !== '') $roleCodigo = $c;
    }
  } catch (Throwable $e) {}
}

$esAdmin = in_array($roleCodigo, ['ADMIN','SUPERADMIN'], true);
if (!$esAdmin) {
  http_response_code(403);
  echo 'Acceso restringido. Solo ADMIN/SUPERADMIN.';
  exit;
}

/* ========================
   HELPERS SEGURIDAD / UI
   ======================== */
function norm_slash(string $p): string {
  $p = str_replace('\\','/',$p);
  $p = preg_replace('#/+#','/',$p) ?? $p;
  return $p;
}
function is_path_under(string $child, string $parent): bool {
  $parent = rtrim(norm_slash($parent), '/') . '/';
  $child  = norm_slash($child);
  return strncmp($child, $parent, strlen($parent)) === 0;
}
function safe_rel(string $rel): string {
  $rel = norm_slash($rel);
  $rel = str_replace(['..', "\0"], ['', ''], $rel);
  $rel = ltrim($rel, '/');
  return $rel;
}
function format_size($bytes): string {
  $bytes = (float)$bytes;
  if ($bytes >= 1048576) return round($bytes/1048576, 1) . ' MB';
  if ($bytes >= 1024)    return round($bytes/1024, 1) . ' KB';
  return (string)$bytes . ' B';
}
function sanitize_folder_name(string $name): string {
  $name = trim($name);
  $name = str_replace(['\\','/'], '_', $name);
  $name = preg_replace('/[^a-zA-Z0-9 _-]/u', '', $name) ?? '';
  $name = preg_replace('/\s+/u', ' ', $name) ?? $name;
  return trim($name);
}
function sanitize_file_name(string $name): string {
  $name = trim($name);
  $name = str_replace(['\\','/'], '_', $name);
  $name = preg_replace('/[^a-zA-Z0-9 _.\-]/u', '', $name) ?? '';
  $name = preg_replace('/\s+/u', ' ', $name) ?? $name;
  $name = ltrim($name, '.');
  return trim($name);
}

/* ========================
   MENSAJES
   ======================== */
$mensaje = '';
$mensaje_tipo = 'success';

/* ==========================================================
   TIPOS: carpetas primer nivel dentro de ecmilm
   ========================================================== */
$SOURCES = [];
try {
  $it = new DirectoryIterator($storageBase);
  foreach ($it as $f) {
    if ($f->isDot()) continue;
    if (!$f->isDir()) continue;
    $slug = $f->getFilename();
    if ($slug === '' || $slug[0] === '.') continue;

    $label = str_replace(['_','-'], ' ', $slug);
    $label = mb_strtoupper(mb_substr($label,0,1),'UTF-8') . mb_substr($label,1);
    $SOURCES[$slug] = $label;
  }
} catch (Throwable $e) {}
ksort($SOURCES, SORT_NATURAL | SORT_FLAG_CASE);

/* ==========================================================
   NAVEGACIÓN (nivel 2)
   ========================================================== */
$tipoSel = (string)($_GET['tipo'] ?? 'todos');
$dirSel  = safe_rel((string)($_GET['dir'] ?? ''));

if ($tipoSel !== 'todos' && !isset($SOURCES[$tipoSel])) $tipoSel = 'todos';
if ($tipoSel === 'todos') $dirSel = '';

$tipoBase = ($tipoSel === 'todos') ? $storageBase : realpath($storageBase . '/' . $tipoSel);
if ($tipoBase === false || !is_dir($tipoBase)) {
  $tipoSel  = 'todos';
  $tipoBase = $storageBase;
  $dirSel   = '';
}

$currentAbs = $tipoBase;
if ($tipoSel !== 'todos' && $dirSel !== '') {
  $tmp = realpath($tipoBase . '/' . $dirSel);
  if ($tmp && is_dir($tmp) && is_path_under($tmp, $tipoBase)) {
    $currentAbs = $tmp;
  } else {
    $dirSel = '';
    $currentAbs = $tipoBase;
  }
}

$crumbParts = [];
if ($tipoSel !== 'todos' && $dirSel !== '') {
  $crumbParts = explode('/', $dirSel);
  $crumbParts = array_values(array_filter(array_map('trim', $crumbParts), 'strlen'));
}

/* ==========================================================
   ENDPOINT: ABRIR SEGURO (INLINE)  ✅
   - ?open=1&rel=storage/unidades/ecmilm/<tipo>/<...>/<file>
   ========================================================== */
if (isset($_GET['open'], $_GET['rel']) && (string)$_GET['open'] === '1') {
  $rel = safe_rel((string)$_GET['rel']);
  $abs = realpath($ROOT_FS . '/' . $rel);

  if (!$abs || !is_file($abs) || !is_path_under($abs, $storageBase)) {
    http_response_code(404);
    exit('Archivo no encontrado.');
  }

  $filename = basename($abs);

  $mime = 'application/octet-stream';
  if (function_exists('mime_content_type')) {
    $m = @mime_content_type($abs);
    if (is_string($m) && $m !== '') $mime = $m;
  }

  // Inline para tipos que el browser suele mostrar
  $inline = false;
  if (str_starts_with($mime, 'image/')) $inline = true;
  if ($mime === 'application/pdf') $inline = true;
  if (str_starts_with($mime, 'text/')) $inline = true;

  header('Content-Type: ' . $mime);
  header('Content-Length: ' . (string)filesize($abs));
  header('X-Content-Type-Options: nosniff');
  header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . str_replace('"','', $filename) . '"');
  readfile($abs);
  exit;
}

/* ==========================================================
   ENDPOINT: DESCARGA SEGURA
   ========================================================== */
if (isset($_GET['download'], $_GET['rel']) && (string)$_GET['download'] === '1') {
  $rel = safe_rel((string)$_GET['rel']);
  $abs = realpath($ROOT_FS . '/' . $rel);

  if (!$abs || !is_file($abs) || !is_path_under($abs, $storageBase)) {
    http_response_code(404);
    exit('Archivo no encontrado.');
  }

  $filename = basename($abs);
  $mime = 'application/octet-stream';
  if (function_exists('mime_content_type')) {
    $m = @mime_content_type($abs);
    if (is_string($m) && $m !== '') $mime = $m;
  }

  header('Content-Type: ' . $mime);
  header('Content-Length: ' . (string)filesize($abs));
  header('Content-Disposition: attachment; filename="' . str_replace('"','', $filename) . '"');
  header('X-Content-Type-Options: nosniff');
  readfile($abs);
  exit;
}

/* ==========================================================
   ACCIONES POST
   ========================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $accion = (string)($_POST['accion'] ?? '');

  $tipoPost = (string)($_POST['tipo'] ?? $tipoSel);
  $dirPost  = safe_rel((string)($_POST['dir'] ?? $dirSel));

  if ($tipoPost !== 'todos' && !isset($SOURCES[$tipoPost])) $tipoPost = $tipoSel;
  if ($tipoPost === 'todos') $dirPost = '';

  $basePost = ($tipoPost === 'todos') ? $storageBase : realpath($storageBase . '/' . $tipoPost);
  if (!$basePost || !is_dir($basePost)) $basePost = $storageBase;

  $currentPostAbs = $basePost;
  if ($tipoPost !== 'todos' && $dirPost !== '') {
    $tmp = realpath($basePost . '/' . $dirPost);
    if ($tmp && is_dir($tmp) && is_path_under($tmp, $basePost)) $currentPostAbs = $tmp;
  }

  $tipoEspecifico = ($tipoPost !== 'todos' && isset($SOURCES[$tipoPost]));

  try {

    if ($accion === 'delete_file') {
      if (!$tipoEspecifico) throw new RuntimeException("Elegí un tipo (no 'Todos') para operar.");

      $rel = safe_rel((string)($_POST['rel'] ?? ''));
      $abs = realpath($ROOT_FS . '/' . $rel);

      if (!$abs || !is_file($abs) || !is_path_under($abs, $storageBase) || !is_path_under($abs, $basePost)) {
        throw new RuntimeException("Ruta inválida o fuera de la carpeta permitida.");
      }
      if (!@unlink($abs)) throw new RuntimeException("No se pudo eliminar (permiso o bloqueo).");

      $mensaje = "Archivo eliminado correctamente.";
      $mensaje_tipo = "success";
    }

    if ($accion === 'upload_file') {
      if (!$tipoEspecifico) throw new RuntimeException("Elegí un tipo (no 'Todos') para subir.");

      if (!isset($_FILES['archivo']) || !is_array($_FILES['archivo']) || ($_FILES['archivo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException("Error al recibir el archivo.");
      }

      $targetDirReal = realpath($currentPostAbs);
      if (!$targetDirReal || !is_dir($targetDirReal) || !is_path_under($targetDirReal, $basePost)) {
        throw new RuntimeException("Directorio destino inválido.");
      }

      $nombre = sanitize_file_name((string)($_FILES['archivo']['name'] ?? 'archivo'));
      if ($nombre === '') throw new RuntimeException("Nombre de archivo inválido.");

      $dest = $targetDirReal . '/' . $nombre;
      if (file_exists($dest)) throw new RuntimeException("Ya existe un archivo con ese nombre en esta carpeta.");

      if (!@move_uploaded_file((string)($_FILES['archivo']['tmp_name']), $dest)) {
        throw new RuntimeException("No se pudo guardar el archivo en disco.");
      }

      $mensaje = "Archivo subido correctamente.";
      $mensaje_tipo = "success";
    }

    if ($accion === 'create_dir') {
      if (!$tipoEspecifico) throw new RuntimeException("Elegí un tipo (no 'Todos') para crear carpetas.");

      $newName = sanitize_folder_name((string)($_POST['new_folder'] ?? ''));
      if ($newName === '') throw new RuntimeException("Nombre de carpeta inválido.");

      $dest = $currentPostAbs . '/' . $newName;
      $destNorm = norm_slash($dest);

      if (!is_path_under($destNorm, $basePost)) throw new RuntimeException("Ruta fuera del tipo.");
      if (file_exists($dest)) throw new RuntimeException("Ya existe una carpeta/archivo con ese nombre.");
      if (!@mkdir($dest, 0775, false)) throw new RuntimeException("No se pudo crear la carpeta (permisos).");

      $mensaje = "Carpeta creada correctamente.";
      $mensaje_tipo = "success";
    }

  } catch (Throwable $ex) {
    $mensaje = "Error: " . $ex->getMessage();
    $mensaje_tipo = "danger";
  }

  header('Location: ' . $SELF_WEB . '?' . http_build_query(['tipo'=>$tipoPost, 'dir'=>$dirPost]));
  exit;
}

/* ==========================================================
   LISTADO NIVEL 2 (DIRECTORIO ACTUAL)
   ========================================================== */

// Subcarpetas inmediatas (incluye vacías)
$subDirs = [];
if ($tipoSel === 'todos') {
  foreach ($SOURCES as $slug => $lab) {
    $subDirs[] = ['name' => $lab, 'rel' => '', 'tipo' => $slug, 'isType' => true];
  }
} else {
  try {
    $it = new DirectoryIterator($currentAbs);
    foreach ($it as $node) {
      if ($node->isDot()) continue;
      if (!$node->isDir()) continue;
      $name = $node->getFilename();
      if ($name === '' || $name[0] === '.') continue;

      $rel = ($dirSel === '') ? $name : ($dirSel . '/' . $name);
      $subDirs[] = ['name' => $name, 'rel' => $rel, 'tipo' => $tipoSel, 'isType' => false];
    }
  } catch (Throwable $e) {}
  usort($subDirs, fn($a,$b) => strcasecmp($a['name'], $b['name']));
}

// Archivos inmediatos (del directorio actual)
$files = [];
if ($tipoSel !== 'todos') {
  try {
    $it = new DirectoryIterator($currentAbs);
    foreach ($it as $node) {
      if ($node->isDot()) continue;
      if (!$node->isFile()) continue;

      $abs = $node->getPathname();
      if (!is_path_under($abs, $storageBase)) continue;

      $ext = strtolower((string)$node->getExtension());
      $filename = $node->getFilename();

      // rel al root del proyecto (ea/)
      $relToProject = norm_slash(substr($abs, strlen($ROOT_FS) + 1)); // storage/unidades/ecmilm/...

      // Abrir:
      // - xlsx/csv -> ver_tabla en /public
      // - resto -> open seguro del mismo script
      if (in_array($ext, ['xlsx','csv'], true)) {
        $urlOpen = $BASE_PUBLIC_WEB . '/ver_tabla.php?p=' . rawurlencode($relToProject);
      } else {
        $urlOpen = $SELF_WEB . '?open=1&rel=' . rawurlencode($relToProject);
      }

      // Descargar
      $urlDownload = $SELF_WEB . '?download=1&rel=' . rawurlencode($relToProject);

      $files[] = [
        'archivo' => $filename,
        'ext' => strtoupper($ext ?: ''),
        'size' => (int)$node->getSize(),
        'mtime' => (int)$node->getMTime(),
        'rel' => $relToProject,
        'open' => $urlOpen,
        'download' => $urlDownload,
      ];
    }
  } catch (Throwable $e) {}

  usort($files, fn($a,$b) => strcasecmp($a['archivo'], $b['archivo']));
}

/* ===== Assets UI (correctos, sin ../../../) ===== */
$IMG_BG = $ASSET_WEB . '../../../assets/img/fondo.png';
$ESCUDO = $ASSET_WEB . '../../../assets/img/ecmilm.png';
$FAVICON = $ASSET_WEB . '../../../assets/img/ecmilm.png';

$tipoLabelUI = ($tipoSel !== 'todos' && isset($SOURCES[$tipoSel])) ? ($SOURCES[$tipoSel] . " ($tipoSel)") : 'Todos';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Administrar documentos</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= e($ASSET_WEB) ?>../../../css/theme-602.css">
<link rel="icon" type="image/png" href="<?= e($FAVICON) ?>">

<style>
  :root{
    --bg0:#000;
    --panel: rgba(15,17,23,.92);
    --panel2: rgba(2,6,23,.58);
    --line: rgba(148,163,184,.35);
    --line2: rgba(148,163,184,.22);
    --text: #e5e7eb;
    --muted:#a9b6c9;
    --muted2:#9ca3af;
    --ok:#22c55e;
    --ok2:#4ade80;
    --danger:#ef4444;
    --danger2:#f97373;
    --blue:#38bdf8;
  }
  html,body{ height:100%; }
  body{ margin:0; color:var(--text); background:var(--bg0); font-family:system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,sans-serif; }

  .page-bg{
    position:fixed; inset:0; z-index:-2; pointer-events:none;
    background:
      linear-gradient(160deg, rgba(0,0,0,.86) 0%, rgba(0,0,0,.68) 55%, rgba(0,0,0,.86) 100%),
      url("<?= e($IMG_BG) ?>") center/cover no-repeat;
    background-attachment: fixed, fixed;
  }
  .container-main{ max-width:1600px; margin:auto; padding:18px; position:relative; z-index:1; }

  .brand-hero{ padding:10px 0; position:relative; z-index:2; }
  .hero-inner{ display:flex; align-items:center; gap:12px; }
  .brand-logo{ height:52px; width:auto; filter: drop-shadow(0 10px 18px rgba(0,0,0,.55)); }
  .brand-title{ font-weight:950; font-size:1.05rem; line-height:1.1; color:#f8fafc; }
  .brand-sub{ font-size:.85rem; color:var(--muted2); margin-top:2px; }
  .header-back{ margin-left:auto; margin-right:17px; margin-top:4px; }

  .panel{
    background:var(--panel);
    border:1px solid var(--line);
    border-radius:18px;
    padding:14px 14px 12px;
    box-shadow:0 18px 40px rgba(0,0,0,.7), inset 0 1px 0 rgba(255,255,255,.05);
    backdrop-filter:blur(8px);
  }

  .topbar{ display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end; justify-content:space-between; margin-bottom:12px; }
  .filters{ display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end; }
  .filter-label{ font-size:.72rem; font-weight:900; color:var(--muted2); text-transform:uppercase; letter-spacing:.08em; margin-bottom:6px; }
  .filter-select{
    background:rgba(2,6,23,.85);
    border-radius:14px;
    border:1px solid rgba(148,163,184,.45);
    color:var(--text);
    font-size:.9rem;
    font-weight:800;
    padding:.5rem .75rem;
    min-width:340px;
  }
  .filter-select:focus{ outline:none; box-shadow:none; border-color:rgba(34,197,94,.55); }

  .kpis{ display:flex; gap:8px; flex-wrap:wrap; align-items:center; justify-content:flex-end; }
  .kpi{
    padding:.18rem .65rem;
    border-radius:999px;
    border:1px solid rgba(148,163,184,.24);
    background:rgba(148,163,184,.12);
    color:#e5e7eb;
    font-weight:900;
    font-size:.78rem;
    white-space:nowrap;
  }
  .kpi.ok{ border-color: rgba(34,197,94,.35); background: rgba(34,197,94,.12); }
  .kpi.blue{ border-color: rgba(56,189,248,.35); background: rgba(56,189,248,.12); }

  .crumb{
    display:flex; flex-wrap:wrap; gap:8px; align-items:center;
    margin:8px 0 12px;
    padding:10px 12px;
    border-radius:16px;
    border:1px solid var(--line2);
    background:rgba(2,6,23,.55);
    color:var(--muted);
    font-size:.9rem;
  }
  .crumb a{
    color:#eafff3; text-decoration:none; font-weight:900;
    border:1px solid rgba(34,197,94,.22);
    background:rgba(34,197,94,.10);
    padding:.12rem .5rem;
    border-radius:999px;
  }
  .crumb a:hover{ background:rgba(34,197,94,.16); }
  .crumb b{ color:#f8fafc; font-weight:950; }
  .crumb code{
    color:#f8fafc;
    background:rgba(148,163,184,.12);
    border:1px solid rgba(148,163,184,.18);
    padding:.1rem .45rem;
    border-radius:999px;
    font-weight:900;
  }

  .grid2{ display:grid; grid-template-columns: 380px 1fr; gap:12px; }
  @media (max-width: 1100px){ .grid2{ grid-template-columns: 1fr; } }

  .box{ border:1px solid rgba(148,163,184,.22); background:rgba(2,6,23,.45); border-radius:16px; padding:12px; }
  .box-title{ font-weight:950; color:#f8fafc; display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:10px; }
  .mini{ color:var(--muted2); font-size:.82rem; }

  .chips{ display:flex; flex-wrap:wrap; gap:8px; }
  .chip{
    display:inline-flex; align-items:center; gap:8px;
    padding:.35rem .65rem;
    border-radius:999px;
    border:1px solid rgba(148,163,184,.22);
    background:rgba(15,17,23,.55);
    color:#e5e7eb;
    text-decoration:none;
    font-weight:900;
    font-size:.86rem;
    max-width:100%;
  }
  .chip:hover{ border-color:rgba(34,197,94,.28); background:rgba(34,197,94,.10); color:#eafff3; }
  .chip .mut{ color:var(--muted2); font-weight:800; }
  .chip .dot{ width:8px; height:8px; border-radius:50%; background:rgba(56,189,248,.8); box-shadow:0 0 0 2px rgba(56,189,248,.12); }
  .chip.folder .dot{ background:rgba(34,197,94,.9); box-shadow:0 0 0 2px rgba(34,197,94,.12); }

  .bar{ display:flex; flex-wrap:wrap; gap:8px; align-items:center; justify-content:space-between; margin-top:10px; }
  .upload-input{ background:rgba(2,6,23,.85); border-radius:999px; border:1px solid rgba(148,163,184,.45); color:var(--text); font-size:.84rem; padding:.35rem .7rem; min-width: 260px; }

  .btn-pill{
    border:none; border-radius:999px;
    font-weight:950; padding:.42rem .95rem;
    box-shadow:0 10px 24px rgba(0,0,0,.25);
    white-space:nowrap;
  }
  .btn-pill.ok{ background:var(--ok); color:#022c16; }
  .btn-pill.ok:hover{ background:var(--ok2); color:#022c16; }
  .btn-pill.ghost{ background:rgba(148,163,184,.18); border:1px solid rgba(148,163,184,.28); color:#e5e7eb; box-shadow:none; }
  .btn-pill.danger{ background:var(--danger); color:#fff; }
  .btn-pill.danger:hover{ background:var(--danger2); color:#fff; }

  .table-wrap{ border-radius:16px; overflow:hidden; border:1px solid rgba(148,163,184,.22); background:rgba(2,6,23,.35); max-height:72vh; }
  table.tbl{ width:100%; border-collapse:collapse; font-size:.88rem; }
  .tbl thead th{
    position:sticky; top:0; z-index:3;
    background:rgba(2,6,23,.95);
    border-bottom:1px solid rgba(148,163,184,.35);
    padding:.65rem .75rem;
    text-transform:uppercase;
    letter-spacing:.06em;
    font-weight:950;
    font-size:.76rem;
    color:#f8fafc;
  }
  .tbl tbody td{ padding:.6rem .75rem; border-bottom:1px solid rgba(31,41,55,.75); vertical-align:middle; color:#e5e7eb; }
  .tbl tbody tr:nth-child(even){ background:rgba(15,23,42,.45); }
  .tbl tbody tr:nth-child(odd){ background:rgba(15,23,42,.25); }
  .tbl tbody tr:hover{ background:rgba(34,197,94,.10); }

  .cell-file{ white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:520px; font-weight:900; }

  .badge-ext{ font-size:.72rem; padding:.18rem .55rem; border-radius:999px; border:1px solid rgba(148,163,184,.35); background:rgba(2,6,23,.8); font-weight:900; letter-spacing:.04em; }
  .badge-xlsx{ border-color: rgba(34,197,94,.45); color:#bbf7d0; }
  .badge-pdf{ border-color: rgba(249,115,22,.55); color:#fed7aa; }
  .badge-oth{ border-color: rgba(56,189,248,.40); color:#e0f2fe; }

  .btn-open{ padding:.28rem .78rem; border-radius:999px; border:none; background:var(--ok); color:#022c16; font-size:.82rem; font-weight:950; text-decoration:none; display:inline-block; white-space:nowrap; }
  .btn-open:hover{ background:var(--ok2); color:#022c16; }
  .btn-open-delete{ background:var(--danger); color:#fff; }
  .btn-open-delete:hover{ background:var(--danger2); color:#fff; }
  .btn-actions{ display:flex; flex-wrap:wrap; gap:6px; }

  .mini-form{ display:flex; flex-wrap:wrap; gap:8px; align-items:center; margin-top:10px; padding-top:10px; border-top:1px dashed rgba(148,163,184,.22); }
  .mini-inp{ background:rgba(2,6,23,.85); border-radius:12px; border:1px solid rgba(148,163,184,.35); color:var(--text); font-size:.84rem; padding:.45rem .6rem; min-width: 180px; }
</style>
</head>

<body>
<div class="page-bg"></div>

<header class="brand-hero">
  <div class="hero-inner container-main" style="padding-top:0; padding-bottom:0;">
    <img class="brand-logo" src="<?= e($ESCUDO) ?>" alt="Escudo">
    <div>
      <div class="brand-title">Administrar documentos</div>
      <div class="brand-sub">Base: <b>/storage/unidades/ecmilm</b> · Rol: <b><?= e($roleCodigo) ?></b></div>
    </div>

    <div class="header-back">
      <a href="<?= e($BASE_ADMIN_WEB) ?>/./administrar_gestiones.php"
         class="btn btn-success btn-sm"
         style="font-weight:700; padding:.35rem .9rem;">
        Volver
      </a>
    </div>
  </div>
</header>

<div class="container-main">  
  <div class="panel">

    <?php if ($mensaje !== ''): ?>
      <div class="alert alert-<?= e($mensaje_tipo) ?> py-2 mb-3"><?= e($mensaje) ?></div>
    <?php endif; ?>

    <div class="topbar">
      <form method="get" class="filters" action="<?= e($SELF_WEB) ?>">
        <div>
          <div class="filter-label">Tipo (carpeta dentro de ecmilm)</div>
          <select name="tipo" class="filter-select" onchange="this.form.submit()">
            <option value="todos" <?= $tipoSel==='todos'?'selected':'' ?>>Todos</option>
            <?php foreach($SOURCES as $slug => $lab): ?>
              <option value="<?= e($slug) ?>" <?= $tipoSel===$slug?'selected':'' ?>><?= e($lab) ?> (<?= e($slug) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <input type="hidden" name="dir" value="<?= e($dirSel) ?>">
      </form>

      <div class="kpis">
        <span class="kpi blue">Tipo: <?= e($tipoLabelUI) ?></span>
        <span class="kpi ok">Carpetas: <?= (int)count($subDirs) ?></span>
        <span class="kpi ok">Archivos: <?= (int)count($files) ?></span>
      </div>
    </div>

    <div class="crumb">
      <span class="mini">Ubicación:</span>
      <b>storage</b> / <b>unidades</b> / <b>ecmilm</b>

      <?php if ($tipoSel === 'todos'): ?>
        <span class="mini">·</span> <code>Todos</code>
      <?php else: ?>
        <span class="mini">·</span>
        <a href="<?= e($SELF_WEB) ?>?<?= e(http_build_query(['tipo'=>$tipoSel,'dir'=>''])) ?>">/<?= e($tipoSel) ?></a>

        <?php
          $acc = '';
          foreach ($crumbParts as $p):
            $acc = ($acc === '') ? $p : ($acc . '/' . $p);
        ?>
          <span class="mini">/</span>
          <a href="<?= e($SELF_WEB) ?>?<?= e(http_build_query(['tipo'=>$tipoSel,'dir'=>$acc])) ?>"><?= e($p) ?></a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div class="grid2">

      <!-- CARPETAS -->
      <div class="box">
        <div class="box-title">
          <div>📁 Carpetas</div>
          <div class="mini"><?= $tipoSel==='todos' ? 'Elegí un tipo' : ($dirSel==='' ? '(Raíz)' : $dirSel) ?></div>
        </div>

        <div class="chips">
          <?php if ($tipoSel !== 'todos' && $dirSel !== ''): ?>
            <?php
              $parent = '';
              if (str_contains($dirSel, '/')) $parent = substr($dirSel, 0, (int)strrpos($dirSel, '/'));
            ?>
            <a class="chip folder" href="<?= e($SELF_WEB) ?>?<?= e(http_build_query(['tipo'=>$tipoSel,'dir'=>$parent])) ?>">
              <span class="dot folder"></span> .. <span class="mut">(subir)</span>
            </a>
          <?php endif; ?>

          <?php if (empty($subDirs)): ?>
            <div class="mini">No hay subcarpetas en este nivel.</div>
          <?php else: ?>
            <?php foreach ($subDirs as $d): ?>
              <?php if (!empty($d['isType'])): ?>
                <a class="chip folder" href="<?= e($SELF_WEB) ?>?<?= e(http_build_query(['tipo'=>$d['tipo'],'dir'=>''])) ?>">
                  <span class="dot folder"></span> <?= e($d['name']) ?> <span class="mut">(<?= e($d['tipo']) ?>)</span>
                </a>
              <?php else: ?>
                <a class="chip folder" href="<?= e($SELF_WEB) ?>?<?= e(http_build_query(['tipo'=>$tipoSel,'dir'=>$d['rel']])) ?>">
                  <span class="dot folder"></span> <?= e($d['name']) ?>
                </a>
              <?php endif; ?>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <?php if ($tipoSel !== 'todos'): ?>
          <form method="post" class="mini-form" action="<?= e($SELF_WEB) ?>">
            <input type="hidden" name="accion" value="create_dir">
            <input type="hidden" name="tipo" value="<?= e($tipoSel) ?>">
            <input type="hidden" name="dir" value="<?= e($dirSel) ?>">
            <input class="mini-inp" type="text" name="new_folder" placeholder="Nueva carpeta (ej: 2026 / actas)">
            <button class="btn-pill ghost" type="submit">➕ Crear</button>
            <div class="mini">Se crea dentro del directorio actual.</div>
          </form>
        <?php endif; ?>
      </div>

      <!-- ARCHIVOS -->
      <div class="box">
        <div class="box-title">
          <div>📄 Archivos</div>
          <div class="mini">
            <?= $tipoSel==='todos' ? 'Seleccioná un tipo para ver archivos.' : ('Directorio: ' . ($dirSel==='' ? '(Raíz)' : $dirSel)) ?>
          </div>
        </div>

        <?php if ($tipoSel !== 'todos'): ?>
          <form method="post" enctype="multipart/form-data" class="bar" action="<?= e($SELF_WEB) ?>">
            <input type="hidden" name="accion" value="upload_file">
            <input type="hidden" name="tipo" value="<?= e($tipoSel) ?>">
            <input type="hidden" name="dir" value="<?= e($dirSel) ?>">
            <div class="mini">Subir al directorio actual.</div>
            <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center; justify-content:flex-end;">
              <input type="file" name="archivo" class="upload-input" required>
              <button class="btn-pill ok" type="submit">Subir</button>
            </div>
          </form>
        <?php endif; ?>

        <div class="table-wrap mt-2">
          <table class="tbl">
            <thead>
              <tr>
                <th>Archivo</th>
                <th style="width:90px;">Ext</th>
                <th style="width:110px;">Tamaño</th>
                <th style="width:170px;">Modificado</th>
                <th style="width:340px;">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($tipoSel === 'todos'): ?>
                <tr><td colspan="5" class="text-center" style="color:var(--muted2); padding:18px;">Elegí un tipo para ver archivos.</td></tr>
              <?php elseif (empty($files)): ?>
                <tr><td colspan="5" class="text-center" style="color:var(--muted2); padding:18px;">No hay archivos en este directorio.</td></tr>
              <?php else: ?>
                <?php foreach ($files as $f): ?>
                  <?php
                    $extLower = strtolower((string)$f['ext']);
                    $badge = 'badge-oth';
                    if (in_array($extLower, ['xlsx','csv'], true)) $badge = 'badge-xlsx';
                    if ($extLower === 'pdf') $badge = 'badge-pdf';
                  ?>
                  <tr>
                    <td class="cell-file" title="<?= e($f['archivo']) ?>"><?= e($f['archivo']) ?></td>
                    <td><span class="badge-ext <?= e($badge) ?>"><?= e($f['ext'] ?: '-') ?></span></td>
                    <td><?= e(format_size($f['size'])) ?></td>
                    <td><?= e(date('d/m/Y H:i', (int)$f['mtime'])) ?></td>
                    <td>
                      <div class="btn-actions">
                        <a class="btn-open" href="<?= e($f['open']) ?>" target="_blank">Abrir</a>
                        <a class="btn-open" href="<?= e($f['download']) ?>">Descargar</a>

                        <form method="post" style="display:inline;" onsubmit="return confirm('¿Eliminar el archivo \"<?= e($f['archivo']) ?>\"?');" action="<?= e($SELF_WEB) ?>">
                          <input type="hidden" name="accion" value="delete_file">
                          <input type="hidden" name="tipo" value="<?= e($tipoSel) ?>">
                          <input type="hidden" name="dir" value="<?= e($dirSel) ?>">
                          <input type="hidden" name="rel" value="<?= e($f['rel']) ?>">
                          <button type="submit" class="btn-open btn-open-delete">Eliminar</button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

      </div>
    </div>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
