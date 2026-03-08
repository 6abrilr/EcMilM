<?php
// public/admin/administrar_destino.php — CRUD de destino (solo ADMIN/SUPERADMIN)
// - SIN columna "orden"
// - RUTA permite subcarpetas dentro de /public (ej: operaciones/operaciones.php)

declare(strict_types=1);

require_once __DIR__ . '/../../auth/bootstrap.php';
require_login();
require_once __DIR__ . '/../../config/db.php';

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function norm_dni(string $dni): string { return preg_replace('/\D+/', '', $dni); }

/* ==========================================================
   BASE WEB robusta (estás dentro de /public/admin)
   Assets reales: /ea/assets/...
   ========================================================== */
$SELF_WEB        = (string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? ''); // /ea/public/admin/administrar_destino.php
$BASE_ADMIN_WEB  = rtrim(str_replace('\\','/', dirname($SELF_WEB)), '/');            // /ea/public/admin
$BASE_PUBLIC_WEB = rtrim(str_replace('\\','/', dirname($BASE_ADMIN_WEB)), '/');      // /ea/public
$BASE_APP_WEB    = rtrim(str_replace('\\','/', dirname($BASE_PUBLIC_WEB)), '/');     // /ea
$ASSET_WEB       = $BASE_APP_WEB . '/assets';                                        // /ea/assets

$IMG_BG   = $ASSET_WEB . '/img/fondo.png';
$ESCUDO   = $ASSET_WEB . '/img/ecmilm.png';
$FAVICON  = $ASSET_WEB . '/img/ecmilm.png';

/* ==========================================================
   FS roots (para validar existencia de archivos en /public)
   ========================================================== */
$ROOT_FS   = realpath(__DIR__ . '/../../'); // .../ea
$PUBLIC_FS = $ROOT_FS ? ($ROOT_FS . DIRECTORY_SEPARATOR . 'public') : '';

/* ==========================================================
   Resolver personal_id + unidad propia
   ========================================================== */
$user    = function_exists('current_user') ? current_user() : ($_SESSION['user'] ?? null);
$dniNorm = norm_dni((string)($user['dni'] ?? $user['username'] ?? ''));

$personalId   = 0;
$unidadPropia = 1;
$fullNameDB   = '';

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

/* ==========================================================
   Rol actual: personal_unidad.role_id -> roles.codigo
   ========================================================== */
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

$esSuperAdmin = ($roleCodigo === 'SUPERADMIN');
$esAdmin      = ($roleCodigo === 'ADMIN') || $esSuperAdmin;

if (!$esAdmin) {
  http_response_code(403);
  echo "Acceso restringido. Solo administradores.";
  exit;
}

/* ==========================================================
   Unidad activa (SUPERADMIN puede cambiarla en sesión)
   ========================================================== */
$unidadActiva = $unidadPropia;
if ($esSuperAdmin) {
  $uSel = (int)($_SESSION['unidad_id'] ?? 0);
  if ($uSel > 0) $unidadActiva = $uSel;
}

/* ===== Branding ===== */
$NOMBRE  = 'Escuela Militar de Montaña';
$LEYENDA = 'La montaña nos une';

try {
  $st = $pdo->prepare("SELECT nombre_completo, subnombre FROM unidades WHERE id = :id LIMIT 1");
  $st->execute([':id' => $unidadActiva]);
  if ($u = $st->fetch(PDO::FETCH_ASSOC)) {
    if (!empty($u['nombre_completo'])) $NOMBRE = (string)$u['nombre_completo'];
    if (!empty($u['subnombre'])) $LEYENDA = trim((string)$u['subnombre'], "“”\"");
  }
} catch (Throwable $e) {}

/* ==========================================================
   Helpers: normalizar ruta (PERMITE subcarpetas)
   Ej válidos:
     - personal/personal.php
     - operaciones/operaciones.php
     - operaciones/partes/parte_diario.php
   Si pegás:
     - ea/public/operaciones/operaciones.php -> operaciones/operaciones.php
     - /ea/public/operaciones/operaciones.php -> operaciones/operaciones.php
   Bloquea:
     - ../
     - rutas con ":" o "http"
   ========================================================== */
function sanitize_ruta(string $ruta): string {
  $ruta = trim($ruta);
  if ($ruta === '') return '';

  $ruta = str_replace('\\', '/', $ruta);
  $ruta = preg_replace('#/+#', '/', $ruta) ?? $ruta;

  // quitar prefijos si pegan ruta completa
  $ruta = preg_replace('#^.*?/public/#i', '', $ruta) ?? $ruta;
  $ruta = ltrim($ruta, '/');

  // bloquear cosas peligrosas
  if (str_contains($ruta, '..')) return '';
  if (str_contains($ruta, ':'))  return '';
  if (preg_match('#^https?://#i', $ruta)) return '';

  // permitir solo a-zA-Z0-9 . _ - y /
  if (!preg_match('#^[a-zA-Z0-9._/\-]+$#', $ruta)) return '';

  // evita dobles slashes por si acaso
  $ruta = preg_replace('#/+#', '/', $ruta) ?? $ruta;

  return $ruta;
}

/* ==========================================================
   Acciones (add / update / toggle / delete)
   ========================================================== */
$msgOk = '';
$msgErr = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = (string)($_POST['action'] ?? '');

  try {

    if ($action === 'add') {
      $codigo = trim((string)($_POST['codigo'] ?? ''));
      $nombre = trim((string)($_POST['nombre'] ?? ''));
      $ruta   = sanitize_ruta((string)($_POST['ruta'] ?? ''));
      $activo = (int)($_POST['activo'] ?? 1);

      if ($nombre === '') throw new RuntimeException("El nombre es obligatorio.");
      if ((string)($_POST['ruta'] ?? '') !== '' && $ruta === '') {
        throw new RuntimeException("La ruta es inválida. Usá por ejemplo: operaciones/operaciones.php");
      }

      $st = $pdo->prepare("
        INSERT INTO destino (unidad_id, codigo, nombre, ruta, activo)
        VALUES (:uid, :codigo, :nombre, :ruta, :activo)
      ");
      $st->execute([
        ':uid'    => $unidadActiva,
        ':codigo' => ($codigo !== '' ? $codigo : null),
        ':nombre' => $nombre,
        ':ruta'   => ($ruta !== '' ? $ruta : null),
        ':activo' => ($activo ? 1 : 0),
      ]);

      $msgOk = "Destino agregado correctamente.";
    }

    if ($action === 'update') {
      $id     = (int)($_POST['id'] ?? 0);
      $codigo = trim((string)($_POST['codigo'] ?? ''));
      $nombre = trim((string)($_POST['nombre'] ?? ''));
      $ruta   = sanitize_ruta((string)($_POST['ruta'] ?? ''));
      $activo = (int)($_POST['activo'] ?? 1);

      if ($id <= 0) throw new RuntimeException("ID inválido.");
      if ($nombre === '') throw new RuntimeException("El nombre es obligatorio.");
      if ((string)($_POST['ruta'] ?? '') !== '' && $ruta === '') {
        throw new RuntimeException("La ruta es inválida. Usá por ejemplo: operaciones/operaciones.php");
      }

      $st = $pdo->prepare("
        UPDATE destino
        SET codigo = :codigo,
            nombre = :nombre,
            ruta   = :ruta,
            activo = :activo
        WHERE id = :id AND unidad_id = :uid
        LIMIT 1
      ");
      $st->execute([
        ':codigo' => ($codigo !== '' ? $codigo : null),
        ':nombre' => $nombre,
        ':ruta'   => ($ruta !== '' ? $ruta : null),
        ':activo' => ($activo ? 1 : 0),
        ':id'     => $id,
        ':uid'    => $unidadActiva,
      ]);

      $msgOk = "Destino actualizado.";
    }

    if ($action === 'toggle') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id <= 0) throw new RuntimeException("ID inválido.");

      $st = $pdo->prepare("
        UPDATE destino
        SET activo = CASE WHEN activo = 1 THEN 0 ELSE 1 END
        WHERE id = :id AND unidad_id = :uid
        LIMIT 1
      ");
      $st->execute([':id' => $id, ':uid' => $unidadActiva]);

      $msgOk = "Estado actualizado.";
    }

    if ($action === 'delete') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id <= 0) throw new RuntimeException("ID inválido.");

      $st = $pdo->prepare("SELECT COUNT(*) FROM destino WHERE id = :id AND unidad_id = :uid");
      $st->execute([':id' => $id, ':uid' => $unidadActiva]);
      if ((int)$st->fetchColumn() === 0) {
        throw new RuntimeException("No podés eliminar un destino fuera de la unidad activa.");
      }

      $st = $pdo->prepare("SELECT COUNT(*) FROM personal_unidad WHERE destino_id = :id");
      $st->execute([':id' => $id]);
      $usoPersonal = (int)$st->fetchColumn();

      $st = $pdo->prepare("SELECT COUNT(*) FROM documentos WHERE destino_id = :id");
      $st->execute([':id' => $id]);
      $usoDocs = (int)$st->fetchColumn();

      $st = $pdo->prepare("SELECT COUNT(*) FROM usuario_roles WHERE destino_id = :id");
      $st->execute([':id' => $id]);
      $usoRoles = (int)$st->fetchColumn();

      if ($usoPersonal > 0 || $usoDocs > 0 || $usoRoles > 0) {
        throw new RuntimeException(
          "No se puede eliminar: el destino está en uso. " .
          "Personal={$usoPersonal}, Documentos={$usoDocs}, Roles={$usoRoles}. " .
          "Primero reasigná o poné en NULL esas referencias."
        );
      }

      $st = $pdo->prepare("DELETE FROM destino WHERE id = :id AND unidad_id = :uid LIMIT 1");
      $st->execute([':id' => $id, ':uid' => $unidadActiva]);

      $msgOk = "Destino eliminado.";
    }

  } catch (Throwable $e) {
    $msgErr = $e->getMessage();
  }
}

/* ==========================================================
   Listado — ordenar por id
   ========================================================== */
$destinos = [];
$stats = ['total'=>0,'on'=>0,'off'=>0];

try {
  $st = $pdo->prepare("
    SELECT id, codigo, nombre, ruta, activo
    FROM destino
    WHERE unidad_id = :uid
    ORDER BY id ASC
  ");
  $st->execute([':uid' => $unidadActiva]);
  $destinos = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  $stats['total'] = count($destinos);
  foreach ($destinos as $d) {
    if ((int)($d['activo'] ?? 0) === 1) $stats['on']++;
    else $stats['off']++;
  }
} catch (Throwable $e) {
  $msgErr = $msgErr ?: ("No se pudieron cargar destinos: " . $e->getMessage());
}

/* ==========================================================
   Helper UI: chequear existencia de ruta dentro de /public
   ========================================================== */
function ruta_existe_en_public(string $publicFs, string $ruta): bool {
  if ($publicFs === '' || $ruta === '') return true; // no molestamos
  $full = $publicFs . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $ruta);
  $rp = realpath($full);
  if (!$rp) return false;

  $publicFsNorm = rtrim(str_replace('\\','/', $publicFs), '/') . '/';
  $rpNorm = str_replace('\\','/', $rp);

  return strncmp($rpNorm, $publicFsNorm, strlen($publicFsNorm)) === 0;
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Administrar destinos</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= e($ASSET_WEB) ?>/css/theme-602.css">
<link rel="icon" href="<?= e($FAVICON) ?>">

<style>
  html,body{ height:100%; }
  body{ margin:0; color:#e5e7eb; background:#000; font-family:system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,sans-serif; }

  .page-bg{ position:fixed; inset:0; z-index:-2; pointer-events:none;
    background: linear-gradient(160deg, rgba(0,0,0,.88) 0%, rgba(0,0,0,.68) 55%, rgba(0,0,0,.88) 100%),
    url("<?= e($IMG_BG) ?>") center/cover no-repeat;
    background-attachment: fixed, fixed;
    filter:saturate(1.05);
  }

  .container-main{ max-width:1400px; margin:auto; padding:18px; position:relative; z-index:1; }

  .panel{
    background:rgba(15,17,23,.94);
    border:1px solid rgba(148,163,184,.40);
    border-radius:18px;
    padding:18px 22px 22px;
    box-shadow:0 18px 40px rgba(0,0,0,.75), inset 0 1px 0 rgba(255,255,255,.05);
    backdrop-filter:blur(8px);
  }

  .brand-hero{ padding:10px 0; position:relative; z-index:2; }
  .brand-hero .hero-inner{ align-items:center; display:flex; gap:14px; }
  .brand-logo{ width:58px; height:58px; object-fit:contain; filter: drop-shadow(0 10px 18px rgba(0,0,0,.55)); }
  .brand-title{ font-size:1.15rem; font-weight:900; line-height:1.1; color:#f8fafc; }
  .brand-sub{ font-size:.9rem; color:#cbd5f5; opacity:.92; margin-top:2px; }
  .header-back{ margin-left:auto; margin-right:17px; margin-top:4px; }

  .text-muted{ color:#b7c3d6 !important; }
  label.form-label{ color:#e5e7eb !important; font-weight:800; }

  .box{ border:1px solid rgba(148,163,184,.25); background:rgba(2,6,23,.62); border-radius:16px; padding:14px; }

  .form-control, .form-select{
    background:rgba(2,6,23,.78) !important;
    border:1px solid rgba(148,163,184,.32) !important;
    color:#f1f5f9 !important;
  }
  .form-control::placeholder{ color:rgba(203,213,245,.65) !important; }
  .form-control:focus, .form-select:focus{ box-shadow:none !important; border-color:rgba(34,197,94,.55) !important; }

  .table{ --bs-table-bg: transparent; }
  .table thead th{
    color:#f8fafc !important;
    border-color:rgba(148,163,184,.28) !important;
    font-weight:900;
    text-transform:uppercase;
    font-size:.78rem;
    letter-spacing:.06em;
  }
  .table td{ color:#e5e7eb !important; border-color:rgba(148,163,184,.18) !important; vertical-align:middle; }
  .table tbody tr:hover td{ background:rgba(34,197,94,.08) !important; }

  .badge-on{ background:rgba(34,197,94,.22); border:1px solid rgba(34,197,94,.35); color:#d1fae5; }
  .badge-off{ background:rgba(148,163,184,.18); border:1px solid rgba(148,163,184,.25); color:#e5e7eb; }

  .btn{ border-radius:12px; font-weight:900; }
  .btn-outline-light{ border-color:rgba(226,232,240,.45) !important; color:#f8fafc !important; }

  .modal-backdrop{ pointer-events:none !important; z-index: 49990 !important; }
  .modal, .modal *{ pointer-events:auto !important; }
  .modal{ z-index: 50000 !important; }
  .modal-content{
    background:#0b1220 !important;
    color:#e5e7eb !important;
    border:1px solid rgba(148,163,184,.25) !important;
    border-radius:16px;
  }
  .modal-header, .modal-footer{ border-color:rgba(148,163,184,.15) !important; }
</style>
</head>

<body>
<div class="page-bg"></div>

<header class="brand-hero">
  <div class="hero-inner container-main" style="padding-top:0; padding-bottom:0;">
    <img class="brand-logo" src="<?= e($ESCUDO) ?>" alt="Escudo" onerror="this.onerror=null;this.src='<?= e($ASSET_WEB) ?>/img/EA.png';">
    <div>
      <div class="brand-title"><?= e($NOMBRE) ?></div>
      <div class="brand-sub">“<?= e($LEYENDA) ?>”</div>
      <div class="text-muted" style="font-size:.85rem;">
        Usuario: <strong><?= e($fullNameDB !== '' ? $fullNameDB : ($user['display_name'] ?? '')) ?></strong> ·
        Rol: <strong><?= e($roleCodigo) ?></strong> · Unidad ID: <strong><?= (int)$unidadActiva ?></strong>
        &nbsp;·&nbsp; Total destinos: <strong><?= (int)$stats['total'] ?></strong>
      </div>
    </div>

    <div class="header-back">
      <a href="<?= e($BASE_ADMIN_WEB) ?>/administrar_gestiones.php"
         class="btn btn-success btn-sm"
         style="font-weight:700; padding:.35rem .9rem;">
        Volver
      </a>
    </div>
  </div>
</header>

<div class="container-main">
  <div class="panel">
    <h5 class="mb-3" style="font-weight:900; color:#f8fafc;">Administrar destinos</h5>

    <?php if ($msgOk !== ''): ?>
      <div class="alert alert-success"><?= e($msgOk) ?></div>
    <?php endif; ?>
    <?php if ($msgErr !== ''): ?>
      <div class="alert alert-danger"><?= e($msgErr) ?></div>
    <?php endif; ?>

    <div class="row g-3">
      <div class="col-12 col-xl-5">
        <div class="box">
          <div class="mb-2" style="font-weight:900;">Agregar destino</div>

          <form method="post">
            <input type="hidden" name="action" value="add">

            <div class="row g-2">
              <div class="col-4">
                <label class="form-label">Código</label>
                <input class="form-control" name="codigo" placeholder="S1 / S2 / S3">
              </div>
              <div class="col-8">
                <label class="form-label">Nombre *</label>
                <input class="form-control" name="nombre" required placeholder="Personal / Inteligencia / Operaciones">
              </div>

              <div class="col-12">
                <label class="form-label">Ruta del módulo (dentro de /public)</label>
                <input class="form-control" name="ruta" placeholder="Ej: operaciones/operaciones.php / personal/personal.php">
                <div class="text-muted" style="font-size:.85rem; margin-top:6px;">
                  Ahora podés guardar subcarpetas. Ej: <code>operaciones/operaciones.php</code>.
                  Si pegás <code>ea/public/operaciones/operaciones.php</code>, se normaliza.
                </div>
              </div>

              <div class="col-12">
                <label class="form-label">¿Visible como módulo?</label>
                <select class="form-select" name="activo">
                  <option value="1" selected>SI (visible)</option>
                  <option value="0">NO (oculto)</option>
                </select>
              </div>
            </div>

            <div class="mt-3">
              <button class="btn btn-success" type="submit">Guardar destino</button>
            </div>
          </form>
        </div>
      </div>

      <div class="col-12 col-xl-7">
        <div class="box">
          <div class="mb-2" style="font-weight:900;">Listado</div>

          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead>
                <tr>
                  <th style="width:70px;">ID</th>
                  <th style="width:120px;">Código</th>
                  <th>Nombre</th>
                  <th style="width:260px;">Ruta</th>
                  <th style="width:110px;">Visible</th>
                  <th style="width:300px;">Acciones</th>
                </tr>
              </thead>
              <tbody>
              <?php if (empty($destinos)): ?>
                <tr><td colspan="6" class="text-muted">No hay destinos cargados para unidad_id <?= (int)$unidadActiva ?>.</td></tr>
              <?php else: ?>
                <?php foreach ($destinos as $d): ?>
                  <?php
                    $id  = (int)$d['id'];
                    $cod = (string)($d['codigo'] ?? '');
                    $nom = (string)($d['nombre'] ?? '');
                    $rut = (string)($d['ruta'] ?? '');
                    $act = (int)($d['activo'] ?? 1);
                    $mid = 'editDest' . $id;

                    $rutaOk = ($rut === '') ? true : ruta_existe_en_public($PUBLIC_FS, $rut);
                  ?>
                  <tr>
                    <td><?= $id ?></td>
                    <td><?= e($cod) ?></td>
                    <td><?= e($nom) ?></td>
                    <td class="text-muted">
                      <?= e($rut) ?>
                      <?php if ($rut !== '' && !$rutaOk): ?>
                        <div style="color:#fbbf24; font-size:.78rem; font-weight:900;">⚠ No existe en /public</div>
                      <?php endif; ?>
                    </td>
                    <td>
                      <span class="badge <?= $act ? 'badge-on' : 'badge-off' ?>">
                        <?= $act ? 'SI' : 'NO' ?>
                      </span>
                    </td>
                    <td>
                      <form method="post" style="display:inline;">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <button class="btn btn-sm btn-outline-light" type="submit">
                          <?= $act ? 'Ocultar' : 'Mostrar' ?>
                        </button>
                      </form>

                      <button class="btn btn-sm btn-success" type="button" data-bs-toggle="modal" data-bs-target="#<?= e($mid) ?>">
                        Editar
                      </button>

                      <form method="post" style="display:inline;"
                            onsubmit="return confirm('¿Eliminar destino #<?= (int)$id ?> (<?= e($nom) ?>)?\n\nSolo se puede borrar si NO está en uso.');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <button class="btn btn-sm btn-danger" type="submit">
                          Eliminar
                        </button>
                      </form>

                      <div class="modal fade" id="<?= e($mid) ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                          <div class="modal-content">
                            <div class="modal-header">
                              <h5 class="modal-title" style="font-weight:900;">Editar destino #<?= $id ?></h5>
                              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                            </div>

                            <form method="post">
                              <div class="modal-body">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="id" value="<?= $id ?>">

                                <div class="row g-2">
                                  <div class="col-4">
                                    <label class="form-label">Código</label>
                                    <input class="form-control" name="codigo" value="<?= e($cod) ?>">
                                  </div>
                                  <div class="col-8">
                                    <label class="form-label">Nombre *</label>
                                    <input class="form-control" name="nombre" required value="<?= e($nom) ?>">
                                  </div>

                                  <div class="col-12">
                                    <label class="form-label">Ruta del módulo (dentro de /public)</label>
                                    <input class="form-control" name="ruta" value="<?= e($rut) ?>"
                                           placeholder="Ej: operaciones/operaciones.php">
                                    <div class="text-muted" style="font-size:.85rem; margin-top:6px;">
                                      Guardá rutas con carpeta si corresponde: <code>operaciones/operaciones.php</code>.
                                    </div>
                                  </div>

                                  <div class="col-12">
                                    <label class="form-label">Visible</label>
                                    <select class="form-select" name="activo">
                                      <option value="1" <?= $act ? 'selected' : '' ?>>SI</option>
                                      <option value="0" <?= !$act ? 'selected' : '' ?>>NO</option>
                                    </select>
                                  </div>
                                </div>
                              </div>

                              <div class="modal-footer">
                                <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancelar</button>
                                <button class="btn btn-success" type="submit">Guardar</button>
                              </div>
                            </form>

                          </div>
                        </div>
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
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  document.addEventListener('shown.bs.modal', function (ev) {
    const modal = ev.target;
    const first = modal.querySelector('input:not([disabled]), select:not([disabled]), textarea:not([disabled])');
    if(first) first.focus();
  });
</script>
</body>
</html>
