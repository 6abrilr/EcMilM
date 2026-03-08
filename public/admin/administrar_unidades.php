<?php
// public/admin/administrar_unidades.php — CRUD Unidades (solo SUPERADMIN) con storage filesystem
declare(strict_types=1);

require_once __DIR__ . '/../../auth/bootstrap.php';
require_login();

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/ui.php';

if (!function_exists('e')) {
  function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}
function norm_dni(string $dni): string { return preg_replace('/\D+/', '', $dni); }

/* ========= PDO ========= */
if (!isset($pdo) || !($pdo instanceof PDO)) {
  http_response_code(500);
  echo "No hay conexión a base de datos (PDO). Revisá config/db.php.";
  exit;
}

/* ==========================================================
   BASE WEB robusta (porque estás dentro de /public/admin)
   ========================================================== */
$SELF_WEB        = (string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
$BASE_ADMIN_WEB  = rtrim(str_replace('\\','/', dirname($SELF_WEB)), '/');            // /ea/public/admin
$BASE_PUBLIC_WEB = rtrim(str_replace('\\','/', dirname($BASE_ADMIN_WEB)), '/');      // /ea/public
$BASE_APP_WEB    = rtrim(str_replace('\\','/', dirname($BASE_PUBLIC_WEB)), '/');     // /ea
$ASSET_WEB       = $BASE_PUBLIC_WEB . '../../assets';                                    // /ea/public/assets

$THEME_CSS = $ASSET_WEB . '/css/theme-602.css';
$FAVICON   = $ASSET_WEB . '/img/ecmilm.png';
$IMG_BG    = $ASSET_WEB . '/img/fondo.png';

$DEFAULT_LOGO   = $ASSET_WEB . '/img/EA.png';
$DEFAULT_ESCUDO = $ASSET_WEB . '/img/EA.png';
$DEFAULT_BG     = $ASSET_WEB . '/img/fondo.png';

/* ==========================================================
   BASE FS (root del proyecto)
   - Este archivo está en: ea/public/admin/...
   - ROOT del proyecto es: ea/
   ========================================================== */
$ROOT_FS = realpath(__DIR__ . '/../../'); // .../ea
if (!$ROOT_FS) {
  http_response_code(500);
  echo "No se pudo resolver ROOT del proyecto.";
  exit;
}
$STORAGE_FS = $ROOT_FS . DIRECTORY_SEPARATOR . 'storage';

// Para mantener tu código igual
$GLOBALS['ROOT_DIR']    = $ROOT_FS;
$GLOBALS['STORAGE_DIR'] = $STORAGE_FS;

/* ========= permisos: SOLO SUPERADMIN ========= */
$user = function_exists('current_user') ? current_user() : ($_SESSION['user'] ?? null);
$dniNorm = norm_dni((string)($user['dni'] ?? $user['username'] ?? ''));

$personalId = 0;
$unidadPropia = 0;
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
      $personalId   = (int)($r['id'] ?? 0);
      $unidadPropia = (int)($r['unidad_id'] ?? 0);
      $fullNameDB   = (string)($r['nombre_comp'] ?? '');
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

$esSuperAdmin = ($roleCodigo === 'SUPERADMIN');
if (!$esSuperAdmin) {
  http_response_code(403);
  echo "Acceso restringido. Solo SUPERADMIN.";
  exit;
}

/* ========= helpers ========= */
function sanitize_slug(string $slug): string {
  $slug = trim(mb_strtolower($slug, 'UTF-8'));
  $slug = str_replace(' ', '_', $slug);
  $slug = preg_replace('/[^a-z0-9_-]+/i', '', $slug) ?? $slug;
  $slug = preg_replace('/_+/', '_', $slug) ?? $slug;
  return trim($slug, '_-');
}

/**
 * Canoniza slugs "especiales" para que la carpeta sea la que vos querés.
 * En particular, EC MIL M debe quedar SIEMPRE como: ecmilm
 */
function canonical_slug(string $input): string {
  $s = sanitize_slug($input);

  // Normalizaciones típicas
  $s2 = str_replace('-', '_', $s);
  $s2 = preg_replace('/_+/', '_', $s2) ?? $s2;
  $s2 = trim($s2, '_');

  // ✅ Forzado EC MIL M -> ecmilm (sin guiones ni underscores)
  $mapToEcmilm = ['ec_mil_m', 'ecmil_m', 'ec_milm', 'ecmil m', 'ecmilm', 'ecmil'];
  if (in_array($s2, $mapToEcmilm, true)) return 'ecmilm';

  // Por defecto, devolvemos el slug normalizado (con _ si corresponde)
  return $s2;
}

function ensure_dir(string $path): void {
  if (is_dir($path)) return;
  if (!mkdir($path, 0775, true) && !is_dir($path)) {
    throw new RuntimeException("No se pudo crear el directorio: $path");
  }
}

function read_uploaded_image(string $fieldName, int $maxBytes = 3145728): array {
  if (!isset($_FILES[$fieldName]) || !is_array($_FILES[$fieldName])) return [null, null, null];
  $f = $_FILES[$fieldName];

  if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return [null, null, null];
  if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    throw new RuntimeException("Error al subir $fieldName (código " . (int)$f['error'] . ")");
  }

  $size = (int)($f['size'] ?? 0);
  if ($size <= 0 || $size > $maxBytes) {
    $mb = round($maxBytes / 1024 / 1024, 1);
    throw new RuntimeException("El archivo $fieldName supera el límite de {$mb}MB (o está vacío).");
  }

  $tmp = (string)($f['tmp_name'] ?? '');
  if ($tmp === '' || !is_uploaded_file($tmp)) {
    throw new RuntimeException("Archivo temporal inválido en $fieldName.");
  }

  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mime = (string)($finfo->file($tmp) ?: '');

  $allow = ['image/png','image/jpeg','image/webp'];
  if (!in_array($mime, $allow, true)) {
    throw new RuntimeException("Formato no permitido en $fieldName ($mime). Usá PNG/JPG/WEBP.");
  }

  $bin = file_get_contents($tmp);
  if ($bin === false || $bin === '') {
    throw new RuntimeException("No se pudo leer el archivo de $fieldName.");
  }

  $ext = match ($mime) {
    'image/png'  => 'png',
    'image/jpeg' => 'jpg',
    'image/webp' => 'webp',
    default      => 'png',
  };

  return [$bin, $mime, $ext];
}

function web_path(string $baseAppWeb, ?string $rel): string {
  $rel = (string)$rel;
  $rel = ltrim(str_replace('\\','/', $rel), '/');
  if ($rel === '') return '';
  return rtrim($baseAppWeb, '/') . '/' . $rel;
}

/* ========= mensajes ========= */
$mensaje = '';
$tipoMsg = 'success';

/* ========= acciones ========= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  $accion = (string)($_POST['accion'] ?? '');

  try {
    if ($accion === 'crear') {
      $slugRaw  = (string)($_POST['slug'] ?? '');
      $slug     = canonical_slug($slugRaw);

      $nc    = trim((string)($_POST['nombre_corto'] ?? ''));
      $ncomp = trim((string)($_POST['nombre_completo'] ?? ''));
      $subn  = trim((string)($_POST['subnombre'] ?? ''));
      $activa = (int)($_POST['activa'] ?? 1);

      if ($slug === '')  throw new RuntimeException("El SLUG es obligatorio (ej: ecmilm).");
      if ($nc === '')    throw new RuntimeException("El nombre corto es obligatorio.");

      // slug único
      $chk = $pdo->prepare("SELECT COUNT(*) FROM unidades WHERE slug = ?");
      $chk->execute([$slug]);
      if ((int)$chk->fetchColumn() > 0) throw new RuntimeException("Ya existe una unidad con ese SLUG.");

      // crear carpeta branding (✅ va a quedar storage/unidades/ecmilm/branding)
      $brandDirFs = $GLOBALS['STORAGE_DIR'] . DIRECTORY_SEPARATOR . 'unidades' . DIRECTORY_SEPARATOR . $slug . DIRECTORY_SEPARATOR . 'branding';
      ensure_dir($brandDirFs);

      // uploads (opcionales)
      [$logoBin,  , $logoExt]   = read_uploaded_image('logo',  3 * 1024 * 1024);
      [$escBin,   , $escExt]    = read_uploaded_image('escudo',3 * 1024 * 1024);
      [$bgBin,    , $bgExt]     = read_uploaded_image('bg',    6 * 1024 * 1024);

      $logoRel = null; $escRel = null; $bgRel = null;

      if ($logoBin !== null) {
        $logoRel = "storage/unidades/$slug/branding/logo.$logoExt";
        file_put_contents($GLOBALS['ROOT_DIR'] . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $logoRel), $logoBin);
      }
      if ($escBin !== null) {
        $escRel = "storage/unidades/$slug/branding/escudo.$escExt";
        file_put_contents($GLOBALS['ROOT_DIR'] . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $escRel), $escBin);
      }
      if ($bgBin !== null) {
        $bgRel = "storage/unidades/$slug/branding/bg.$bgExt";
        file_put_contents($GLOBALS['ROOT_DIR'] . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $bgRel), $bgBin);
      }

      $ins = $pdo->prepare("
        INSERT INTO unidades (slug, nombre_corto, nombre_completo, subnombre, logo_path, escudo_path, bg_path, activa)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
      ");
      $ins->execute([
        $slug,
        $nc,
        ($ncomp !== '' ? $ncomp : null),
        ($subn !== '' ? $subn : null),
        $logoRel,
        $escRel,
        $bgRel,
        ($activa ? 1 : 0),
      ]);

      $mensaje = "Unidad creada correctamente. Slug final: {$slug}";
      $tipoMsg = 'success';

    } elseif ($accion === 'editar') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id <= 0) throw new RuntimeException("Unidad inválida.");

      $st = $pdo->prepare("SELECT * FROM unidades WHERE id = ? LIMIT 1");
      $st->execute([$id]);
      $cur = $st->fetch(PDO::FETCH_ASSOC);
      if (!$cur) throw new RuntimeException("No existe la unidad.");

      $slugOld = (string)$cur['slug'];

      $slugRaw = (string)($_POST['slug'] ?? $slugOld);
      $slug    = canonical_slug($slugRaw);

      $nc    = trim((string)($_POST['nombre_corto'] ?? ''));
      $ncomp = trim((string)($_POST['nombre_completo'] ?? ''));
      $subn  = trim((string)($_POST['subnombre'] ?? ''));
      $activa = (int)($_POST['activa'] ?? 1);

      if ($slug === '')  throw new RuntimeException("El SLUG es obligatorio.");
      if ($nc === '')    throw new RuntimeException("El nombre corto es obligatorio.");

      if ($slug !== $slugOld) {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM unidades WHERE slug = ? AND id <> ?");
        $chk->execute([$slug, $id]);
        if ((int)$chk->fetchColumn() > 0) throw new RuntimeException("Ya existe otra unidad con ese SLUG.");
      }

      // Migrar carpeta si cambia slug
      $dirOld = $GLOBALS['STORAGE_DIR'] . DIRECTORY_SEPARATOR . 'unidades' . DIRECTORY_SEPARATOR . $slugOld;
      $dirNew = $GLOBALS['STORAGE_DIR'] . DIRECTORY_SEPARATOR . 'unidades' . DIRECTORY_SEPARATOR . $slug;

      if ($slug !== $slugOld) {
        if (is_dir($dirOld) && !is_dir($dirNew)) {
          ensure_dir(dirname($dirNew));
          @rename($dirOld, $dirNew);
        }
      }

      $brandDirNew = $dirNew . DIRECTORY_SEPARATOR . 'branding';
      ensure_dir($brandDirNew);

      // uploads opcionales
      [$logoBin,  , $logoExt]   = read_uploaded_image('logo',  3 * 1024 * 1024);
      [$escBin,   , $escExt]    = read_uploaded_image('escudo',3 * 1024 * 1024);
      [$bgBin,    , $bgExt]     = read_uploaded_image('bg',    6 * 1024 * 1024);

      $logoRel = (string)($cur['logo_path'] ?? '');
      $escRel  = (string)($cur['escudo_path'] ?? '');
      $bgRel   = (string)($cur['bg_path'] ?? '');

      if ($slug !== $slugOld) {
        if ($logoRel !== '') $logoRel = preg_replace("#^storage/unidades/".preg_quote($slugOld,'#')."/#","storage/unidades/$slug/",$logoRel) ?? $logoRel;
        if ($escRel  !== '') $escRel  = preg_replace("#^storage/unidades/".preg_quote($slugOld,'#')."/#","storage/unidades/$slug/",$escRel) ?? $escRel;
        if ($bgRel   !== '') $bgRel   = preg_replace("#^storage/unidades/".preg_quote($slugOld,'#')."/#","storage/unidades/$slug/",$bgRel) ?? $bgRel;
      }

      if ($logoBin !== null) {
        $logoRel = "storage/unidades/$slug/branding/logo.$logoExt";
        file_put_contents($GLOBALS['ROOT_DIR'] . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $logoRel), $logoBin);
      }
      if ($escBin !== null) {
        $escRel = "storage/unidades/$slug/branding/escudo.$escExt";
        file_put_contents($GLOBALS['ROOT_DIR'] . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $escRel), $escBin);
      }
      if ($bgBin !== null) {
        $bgRel = "storage/unidades/$slug/branding/bg.$bgExt";
        file_put_contents($GLOBALS['ROOT_DIR'] . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $bgRel), $bgBin);
      }

      $upd = $pdo->prepare("
        UPDATE unidades
        SET slug = ?, nombre_corto = ?, nombre_completo = ?, subnombre = ?,
            logo_path = ?, escudo_path = ?, bg_path = ?, activa = ?
        WHERE id = ?
        LIMIT 1
      ");
      $upd->execute([
        $slug,
        $nc,
        ($ncomp !== '' ? $ncomp : null),
        ($subn !== '' ? $subn : null),
        ($logoRel !== '' ? $logoRel : null),
        ($escRel  !== '' ? $escRel  : null),
        ($bgRel   !== '' ? $bgRel   : null),
        ($activa ? 1 : 0),
        $id
      ]);

      $mensaje = "Unidad actualizada correctamente. Slug final: {$slug}";
      $tipoMsg = 'success';

    } elseif ($accion === 'eliminar') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id <= 0) throw new RuntimeException("Unidad inválida.");

      try {
        $del = $pdo->prepare("DELETE FROM unidades WHERE id = ? LIMIT 1");
        $del->execute([$id]);
        $mensaje = "Unidad eliminada correctamente.";
        $tipoMsg = 'success';
      } catch (Throwable $ex) {
        $upd = $pdo->prepare("UPDATE unidades SET activa = 0 WHERE id = ? LIMIT 1");
        $upd->execute([$id]);
        $mensaje = "No se pudo eliminar por relaciones (FK). Se desactivó la unidad (activa=0).";
        $tipoMsg = 'warning';
      }

    } else {
      throw new RuntimeException("Acción no válida.");
    }

  } catch (Throwable $ex) {
    $mensaje = "Error: " . $ex->getMessage();
    $tipoMsg = 'danger';
  }
}

/* ========= listado ========= */
$rows = [];
try {
  $st = $pdo->query("
    SELECT id, slug, nombre_corto, nombre_completo, subnombre, logo_path, escudo_path, bg_path, activa
    FROM unidades
    ORDER BY nombre_corto ASC, id ASC
  ");
  $rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Throwable $e) {
  $rows = [];
}

/* ========= UI ========= */
ui_header('Administración de Unidades', ['container'=>'xl', 'show_brand'=>false]);
?>
<link rel="stylesheet" href="<?= e($THEME_CSS) ?>">
<link rel="icon" type="image/png" href="<?= e($FAVICON) ?>">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

<style>
  body{
    background: url("<?= e($IMG_BG) ?>") no-repeat center center fixed;
    background-size: cover;
    background-attachment: fixed;
    background-color:#0f1117;
    color:#e9eef5;
    font-family:system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,sans-serif;
  }
  .panel{
    background: rgba(15,17,23,.95);
    border:1px solid rgba(255,255,255,.10);
    border-radius:16px;
    padding:16px;
    margin-top:18px;
    box-shadow:0 10px 24px rgba(0,0,0,.40);
  }
  .title{ font-weight:900; font-size:1.05rem; margin:0 0 .4rem 0; }
  .help{ opacity:.88; font-size:.85rem; }
  .mini{ font-size:.82rem; opacity:.86; }

  .thumb{
    width:46px; height:46px; border-radius:10px; object-fit:cover;
    border:1px solid rgba(255,255,255,.14);
    background:rgba(255,255,255,.05);
  }
  .thumb-wide{
    width:84px; height:46px; border-radius:10px; object-fit:cover;
    border:1px solid rgba(255,255,255,.14);
    background:rgba(255,255,255,.05);
  }

  table.tbl{ width:100%; border-collapse:collapse; font-size:.88rem; }
  .tbl th,.tbl td{ padding:.5rem .6rem; border-bottom:1px solid rgba(255,255,255,.10); vertical-align:middle; }
  .tbl th{ font-weight:900; white-space:nowrap; }

  .badge-soft{
    border:1px solid rgba(255,255,255,.18);
    background:rgba(255,255,255,.06);
    padding:.15rem .55rem;
    border-radius:999px;
    font-size:.78rem;
    font-weight:800;
  }

  .header-back{ margin-left:auto; margin-right:17px; margin-top:4px; }

  .modal{ z-index: 50000 !important; }
  .modal-backdrop{ z-index: 49990 !important; pointer-events:none !important; }
  .modal, .modal *{ pointer-events:auto !important; }
</style>

<div class="container mt-3">
  <div class="d-flex align-items-center">
    <h2 class="h5 mb-0">Administración de unidades</h2>
    <div class="header-back">
      <a href="<?= e($BASE_ADMIN_WEB) ?>/administrar_gestiones.php"
         class="btn btn-success btn-sm"
         style="font-weight:700; padding:.35rem .9rem;">
        Volver
      </a>
    </div>
  </div>

  <?php if ($mensaje !== ''): ?>
    <div class="alert alert-<?= e($tipoMsg) ?> mt-3 py-2"><?= e($mensaje) ?></div>
  <?php endif; ?>

  <div class="panel">
    <h3 class="title">Crear nueva unidad</h3>
    <div class="help">
      Branding en filesystem: <code>storage/unidades/&lt;slug&gt;/branding</code>.
      <br><span class="mini">Nota: “EC MIL M” se fuerza a <code>ecmilm</code>.</span>
    </div>

    <form method="post" enctype="multipart/form-data" class="mt-3">
      <?= csrf_input() ?>
      <input type="hidden" name="accion" value="crear">

      <div class="row g-2">
        <div class="col-md-4">
          <label class="form-label">Slug *</label>
          <input type="text" name="slug" class="form-control form-control-sm" required placeholder="ej: ecmilm">
          <div class="mini mt-1">Para EC MIL M: escribas como lo escribas, quedará <code>ecmilm</code>.</div>
        </div>

        <div class="col-md-4">
          <label class="form-label">Nombre corto *</label>
          <input type="text" name="nombre_corto" class="form-control form-control-sm" required placeholder="ej: EC MIL M">
        </div>

        <div class="col-md-4">
          <label class="form-label">Activa</label>
          <select name="activa" class="form-select form-select-sm">
            <option value="1" selected>SI</option>
            <option value="0">NO</option>
          </select>
        </div>

        <div class="col-md-6">
          <label class="form-label">Nombre completo</label>
          <input type="text" name="nombre_completo" class="form-control form-control-sm" placeholder="Escuela Militar de Montaña">
        </div>

        <div class="col-md-6">
          <label class="form-label">Subnombre / lema</label>
          <input type="text" name="subnombre" class="form-control form-control-sm" placeholder="“La montaña nos une”">
        </div>

        <div class="col-md-4">
          <label class="form-label">Logo (opcional)</label>
          <input type="file" name="logo" class="form-control form-control-sm" accept="image/png,image/jpeg,image/webp">
          <div class="mini mt-1">Límite 3MB.</div>
        </div>

        <div class="col-md-4">
          <label class="form-label">Escudo/Icono (opcional)</label>
          <input type="file" name="escudo" class="form-control form-control-sm" accept="image/png,image/jpeg,image/webp">
          <div class="mini mt-1">Límite 3MB.</div>
        </div>

        <div class="col-md-4">
          <label class="form-label">Fondo (opcional)</label>
          <input type="file" name="bg" class="form-control form-control-sm" accept="image/png,image/jpeg,image/webp">
          <div class="mini mt-1">Límite 6MB.</div>
        </div>
      </div>

      <div class="mt-3 text-end">
        <button class="btn btn-primary btn-sm" type="submit">➕ Crear unidad</button>
      </div>
    </form>
  </div>

  <div class="panel">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h3 class="title mb-0">Unidades existentes</h3>
      <span class="badge-soft"><?= count($rows) ?> unidad(es)</span>
    </div>

    <div class="table-responsive">
      <table class="tbl">
        <thead>
          <tr>
            <th>Logo</th>
            <th>Escudo</th>
            <th>Fondo</th>
            <th>Slug</th>
            <th>Nombre corto</th>
            <th>Nombre completo</th>
            <th>Activa</th>
            <th class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="8" class="text-muted">No hay unidades cargadas.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $r): ?>
            <?php
              $id = (int)$r['id'];
              $slug = (string)($r['slug'] ?? '');

              $logoUrl = ($r['logo_path']   ? web_path($BASE_APP_WEB, (string)$r['logo_path'])   : $DEFAULT_LOGO);
              $escUrl  = ($r['escudo_path'] ? web_path($BASE_APP_WEB, (string)$r['escudo_path']) : $DEFAULT_ESCUDO);
              $bgUrl   = ($r['bg_path']     ? web_path($BASE_APP_WEB, (string)$r['bg_path'])     : $DEFAULT_BG);

              $activa  = (int)($r['activa'] ?? 1) === 1;
              $mid = "editU_$id";
            ?>
            <tr>
              <td><img class="thumb" src="<?= e($logoUrl) ?>" alt="logo" onerror="this.onerror=null;this.src='<?= e($DEFAULT_LOGO) ?>';"></td>
              <td><img class="thumb" src="<?= e($escUrl) ?>" alt="escudo" onerror="this.onerror=null;this.src='<?= e($DEFAULT_ESCUDO) ?>';"></td>
              <td><img class="thumb-wide" src="<?= e($bgUrl) ?>" alt="bg" onerror="this.onerror=null;this.src='<?= e($DEFAULT_BG) ?>';"></td>

              <td><span class="badge-soft"><?= e($slug) ?></span></td>
              <td><b><?= e((string)$r['nombre_corto']) ?></b></td>
              <td><?= e((string)($r['nombre_completo'] ?? '')) ?></td>
              <td><?= $activa ? 'SI' : 'NO' ?></td>

              <td class="text-end">
                <button class="btn btn-outline-light btn-sm" data-bs-toggle="modal" data-bs-target="#<?= e($mid) ?>">
                  ✏️ Editar
                </button>

                <form method="post" style="display:inline-block" class="js-confirm-form"
                      data-title="Eliminar unidad"
                      data-text="Si hay personal/destinos/documentos vinculados, no se podrá eliminar y se desactivará."
                      data-icon="warning"
                      data-confirm="Sí, continuar">
                  <?= csrf_input() ?>
                  <input type="hidden" name="accion" value="eliminar">
                  <input type="hidden" name="id" value="<?= $id ?>">
                  <button class="btn btn-outline-danger btn-sm" type="submit">🗑 Eliminar</button>
                </form>
              </td>
            </tr>

            <div class="modal fade" id="<?= e($mid) ?>" tabindex="-1" aria-hidden="true">
              <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content" style="background:#0f1117; color:#e9eef5; border:1px solid rgba(255,255,255,.12); border-radius:16px;">
                  <div class="modal-header" style="border-bottom:1px solid rgba(255,255,255,.10);">
                    <h5 class="modal-title">Editar unidad: <?= e((string)$r['nombre_corto']) ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                  </div>

                  <form method="post" enctype="multipart/form-data">
                    <div class="modal-body">
                      <?= csrf_input() ?>
                      <input type="hidden" name="accion" value="editar">
                      <input type="hidden" name="id" value="<?= $id ?>">

                      <div class="row g-2">
                        <div class="col-md-4">
                          <label class="form-label">Slug *</label>
                          <input type="text" name="slug" class="form-control form-control-sm" required value="<?= e($slug) ?>">
                          <div class="mini mt-1">EC MIL M se fuerza a <code>ecmilm</code>.</div>
                        </div>

                        <div class="col-md-4">
                          <label class="form-label">Nombre corto *</label>
                          <input type="text" name="nombre_corto" class="form-control form-control-sm" required value="<?= e((string)$r['nombre_corto']) ?>">
                        </div>

                        <div class="col-md-4">
                          <label class="form-label">Activa</label>
                          <select name="activa" class="form-select form-select-sm">
                            <option value="1" <?= $activa ? 'selected' : '' ?>>SI</option>
                            <option value="0" <?= !$activa ? 'selected' : '' ?>>NO</option>
                          </select>
                        </div>

                        <div class="col-md-6">
                          <label class="form-label">Nombre completo</label>
                          <input type="text" name="nombre_completo" class="form-control form-control-sm" value="<?= e((string)($r['nombre_completo'] ?? '')) ?>">
                        </div>

                        <div class="col-md-6">
                          <label class="form-label">Subnombre / lema</label>
                          <input type="text" name="subnombre" class="form-control form-control-sm" value="<?= e((string)($r['subnombre'] ?? '')) ?>">
                        </div>

                        <div class="col-md-4">
                          <label class="form-label">Reemplazar Logo</label>
                          <input type="file" name="logo" class="form-control form-control-sm" accept="image/png,image/jpeg,image/webp">
                        </div>

                        <div class="col-md-4">
                          <label class="form-label">Reemplazar Escudo</label>
                          <input type="file" name="escudo" class="form-control form-control-sm" accept="image/png,image/jpeg,image/webp">
                        </div>

                        <div class="col-md-4">
                          <label class="form-label">Reemplazar Fondo</label>
                          <input type="file" name="bg" class="form-control form-control-sm" accept="image/png,image/jpeg,image/webp">
                        </div>
                      </div>

                      <div class="mt-3">
                        <div class="mini mb-1">Vista previa del fondo actual:</div>
                        <img src="<?= e($bgUrl) ?>" alt="preview fondo"
                             onerror="this.onerror=null;this.src='<?= e($DEFAULT_BG) ?>';"
                             style="width:100%; max-height:220px; object-fit:cover; border-radius:14px; border:1px solid rgba(255,255,255,.12);">
                      </div>

                    </div>
                    <div class="modal-footer" style="border-top:1px solid rgba(255,255,255,.10);">
                      <button type="button" class="btn btn-outline-light btn-sm" data-bs-dismiss="modal">Cerrar</button>
                      <button type="submit" class="btn btn-primary btn-sm">💾 Guardar</button>
                    </div>
                  </form>

                </div>
              </div>
            </div>

          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function(){
  document.querySelectorAll('form.js-confirm-form').forEach(function(form){
    form.addEventListener('submit', function(ev){
      ev.preventDefault();
      const title   = form.getAttribute('data-title') || '¿Confirmar?';
      const text    = form.getAttribute('data-text') || '¿Deseas continuar?';
      const icon    = form.getAttribute('data-icon') || 'warning';
      const confirm = form.getAttribute('data-confirm') || 'Sí, confirmar';

      Swal.fire({
        title, text, icon,
        showCancelButton: true,
        confirmButtonText: confirm,
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
        focusCancel: true
      }).then((r) => {
        if (r.isConfirmed) form.submit();
      });
    });
  });
})();
</script>

<?php ui_footer(); ?>
