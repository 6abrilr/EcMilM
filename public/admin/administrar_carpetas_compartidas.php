<?php
declare(strict_types=1);

require_once __DIR__ . '/../../auth/bootstrap.php';
require_login();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/area_shared_permissions.php';

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function norm_dni_admin(string $value): string { return preg_replace('/\D+/', '', $value) ?? ''; }

if (!area_shared_perm_is_admin($pdo)) {
    http_response_code(403);
    exit('Acceso restringido. Solo ADMIN/SUPERADMIN.');
}

area_shared_perm_ensure_schema($pdo);

$user = function_exists('current_user') ? current_user() : ($_SESSION['user'] ?? []);
$identity = area_shared_perm_current_identity($pdo);
$unidadActiva = (int)($identity['unidad_id'] ?? 1);

$ROOT_FS = realpath(__DIR__ . '/../../');
if (!$ROOT_FS) {
    http_response_code(500);
    exit('No se pudo resolver el root del proyecto.');
}

$storageBase = realpath($ROOT_FS . '/storage/unidades/ecmilm');
if (!$storageBase || !is_dir($storageBase)) {
    http_response_code(500);
    exit('No existe storage/unidades/ecmilm.');
}

$SELF_WEB = (string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
$BASE_ADMIN_WEB = rtrim(str_replace('\\', '/', dirname($SELF_WEB)), '/');
$BASE_PUBLIC_WEB = rtrim(str_replace('\\', '/', dirname($BASE_ADMIN_WEB)), '/');
$BASE_APP_WEB = rtrim(str_replace('\\', '/', dirname($BASE_PUBLIC_WEB)), '/');
$ASSET_WEB = $BASE_APP_WEB . '/assets';

$AREA_LABELS = [
    'PERSONAL' => ['code' => 'S1', 'name' => 'Personal'],
    'INTELIGENCIA' => ['code' => 'S2', 'name' => 'Inteligencia'],
    'OPERACIONES' => ['code' => 'S3', 'name' => 'Operaciones'],
    'MATERIALES' => ['code' => 'S4', 'name' => 'Materiales'],
    'INTENDENCIA' => ['code' => 'S5', 'name' => 'Intendencia'],
    'SAF' => ['code' => 'SAF', 'name' => 'SAF'],
    'INFORMATICA' => ['code' => 'INF', 'name' => 'Informatica'],
    'SANIDAD' => ['code' => 'SAN', 'name' => 'Sanidad'],
    'IGE' => ['code' => 'IGE', 'name' => 'IGE'],
];

$areas = [];
try {
    foreach (new DirectoryIterator($storageBase) as $item) {
        if ($item->isDot() || !$item->isDir()) continue;
        $slug = strtoupper($item->getFilename());
        if ($slug === '' || $slug[0] === '.') continue;
        $code = area_shared_perm_code_for_slug($slug, $AREA_LABELS[$slug]['code'] ?? '');
        $areas[$slug] = [
            'slug' => $slug,
            'code' => $code,
            'name' => $AREA_LABELS[$slug]['name'] ?? ucwords(strtolower(str_replace(['_', '-'], ' ', $slug))),
        ];
    }
} catch (Throwable $e) {}
ksort($areas, SORT_NATURAL | SORT_FLAG_CASE);

$areaSlug = strtoupper(trim((string)($_GET['area'] ?? $_POST['area_slug'] ?? 'PERSONAL')));
if (!isset($areas[$areaSlug])) {
    $areaSlug = array_key_first($areas) ?: 'PERSONAL';
}
$areaCode = area_shared_perm_code_for_slug($areaSlug, $areas[$areaSlug]['code'] ?? '');

$msg = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = (string)($_POST['action'] ?? '');
    $areaSlug = strtoupper(trim((string)($_POST['area_slug'] ?? $areaSlug)));
    if (!isset($areas[$areaSlug])) $areaSlug = array_key_first($areas) ?: 'PERSONAL';
    $areaCode = area_shared_perm_code_for_slug($areaSlug, $areas[$areaSlug]['code'] ?? '');

    try {
        if ($action === 'add') {
            $personalId = (int)($_POST['personal_id'] ?? 0);
            $manualDomain = area_shared_perm_norm_user((string)($_POST['domain_username'] ?? ''));
            $position = trim((string)($_POST['position_label'] ?? ''));
            $permission = (string)($_POST['permission'] ?? 'read');
            if (!in_array($permission, ['read', 'write', 'admin'], true)) $permission = 'read';

            $person = null;
            if ($personalId > 0) {
                $st = $pdo->prepare("
                    SELECT id, unidad_id, dni, grado, arma, apellido, nombre, apellido_nombre, usuario_intranet, usuario_gde
                    FROM personal_unidad
                    WHERE id = :id AND unidad_id = :uid
                    LIMIT 1
                ");
                $st->execute([':id' => $personalId, ':uid' => $unidadActiva]);
                $person = $st->fetch(PDO::FETCH_ASSOC) ?: null;
            }

            if (!$person && $manualDomain === '') {
                throw new RuntimeException('Elegi una persona o cargá un usuario de dominio.');
            }

            $dni = $person ? norm_dni_admin((string)($person['dni'] ?? '')) : null;
            $domain = $manualDomain;
            if ($domain === '' && $person) {
                $domain = area_shared_perm_norm_user((string)($person['usuario_intranet'] ?? ''));
            }
            if ($domain === '' && $person) {
                $domain = area_shared_perm_norm_user((string)($person['usuario_gde'] ?? ''));
            }

            $display = '';
            if ($person) {
                $display = trim((string)($person['apellido_nombre'] ?? ''));
                if ($display === '') {
                    $display = trim(implode(' ', array_filter([
                        (string)($person['grado'] ?? ''),
                        (string)($person['arma'] ?? ''),
                        (string)($person['apellido'] ?? ''),
                        (string)($person['nombre'] ?? ''),
                    ])));
                }
            }
            if ($display === '') $display = $domain;

            $st = $pdo->prepare("
                INSERT INTO area_shared_permissions
                  (unidad_id, area_code, area_slug, personal_id, dni, domain_username, display_name, position_label, permission, active, created_by_id)
                VALUES
                  (:uid, :area_code, :area_slug, :personal_id, :dni, :domain_username, :display_name, :position_label, :permission, 1, :created_by)
            ");
            $st->execute([
                ':uid' => $unidadActiva,
                ':area_code' => $areaCode,
                ':area_slug' => $areaSlug,
                ':personal_id' => $person ? (int)$person['id'] : null,
                ':dni' => $dni !== '' ? $dni : null,
                ':domain_username' => $domain !== '' ? $domain : null,
                ':display_name' => $display !== '' ? $display : null,
                ':position_label' => $position !== '' ? $position : null,
                ':permission' => $permission,
                ':created_by' => (int)($identity['personal_id'] ?? 0) ?: null,
            ]);
            $msg = 'Permiso agregado.';
        } elseif ($action === 'toggle' || $action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new RuntimeException('Permiso invalido.');
            if ($action === 'toggle') {
                $st = $pdo->prepare("
                    UPDATE area_shared_permissions
                    SET active = CASE WHEN active = 1 THEN 0 ELSE 1 END
                    WHERE id = :id AND unidad_id = :uid
                ");
                $st->execute([':id' => $id, ':uid' => $unidadActiva]);
                $msg = 'Permiso actualizado.';
            } else {
                $st = $pdo->prepare("DELETE FROM area_shared_permissions WHERE id = :id AND unidad_id = :uid");
                $st->execute([':id' => $id, ':uid' => $unidadActiva]);
                $msg = 'Permiso eliminado.';
            }
        }
    } catch (Throwable $e) {
        $msg = $e->getMessage();
        $msgType = 'danger';
    }
}

$people = [];
try {
    $st = $pdo->prepare("
        SELECT id, dni, grado, arma, apellido, nombre, apellido_nombre, usuario_intranet, usuario_gde
        FROM personal_unidad
        WHERE unidad_id = :uid
        ORDER BY apellido_nombre ASC, apellido ASC, nombre ASC
    ");
    $st->execute([':uid' => $unidadActiva]);
    $people = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {}

$permissions = [];
try {
    $st = $pdo->prepare("
        SELECT *
        FROM area_shared_permissions
        WHERE unidad_id = :uid AND area_code = :area
        ORDER BY active DESC, FIELD(position_label, 'Jefe', 'Encargado', 'Auxiliar'), display_name ASC, id DESC
    ");
    $st->execute([':uid' => $unidadActiva, ':area' => $areaCode]);
    $permissions = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {}

$hasActiveRules = area_shared_perm_area_has_rules($pdo, $unidadActiva, $areaCode);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Permisos de carpetas compartidas</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="icon" href="<?= e($ASSET_WEB) ?>/img/ecmilm.png">
<style>
  body{margin:0;min-height:100vh;background:#101827;color:#e5e7eb;font-family:system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,sans-serif;}
  .wrap{max-width:1320px;margin:auto;padding:18px;}
  .top,.panel{background:#172033;border:1px solid rgba(148,163,184,.22);border-radius:14px;box-shadow:0 18px 44px rgba(0,0,0,.28);}
  .top{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:18px;margin-bottom:16px;flex-wrap:wrap;}
  .title{font-size:1.35rem;font-weight:900;margin:0;}
  .muted{color:#b8c4d8;}
  .panel{padding:16px;margin-bottom:16px;}
  .grid{display:grid;grid-template-columns:minmax(260px,360px) 1fr;gap:16px;}
  .area-list{display:grid;gap:8px;}
  .area-link{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:.72rem .85rem;border-radius:10px;background:#101827;color:#e5e7eb;text-decoration:none;border:1px solid rgba(148,163,184,.18);font-weight:800;}
  .area-link.active{background:#1d4ed8;border-color:#60a5fa;}
  .pill{font-size:.76rem;border:1px solid rgba(148,163,184,.24);background:rgba(148,163,184,.14);border-radius:999px;padding:.16rem .5rem;color:#dbeafe;white-space:nowrap;}
  .form-control,.form-select{background:#0f172a;border-color:#334155;color:#e5e7eb;}
  .form-control:focus,.form-select:focus{background:#0f172a;color:#fff;border-color:#60a5fa;box-shadow:0 0 0 .2rem rgba(96,165,250,.16);}
  .table{--bs-table-bg:transparent;--bs-table-color:#e5e7eb;--bs-table-border-color:rgba(148,163,184,.18);}
  .table thead th{color:#bfdbfe;text-transform:uppercase;font-size:.76rem;letter-spacing:.06em;}
  .status-open{border-left:4px solid #f59e0b;}
  .status-locked{border-left:4px solid #22c55e;}
  @media (max-width: 900px){.grid{grid-template-columns:1fr;}}
</style>
</head>
<body>
<div class="wrap">
  <header class="top">
    <div>
      <h1 class="title">Permisos de carpetas compartidas</h1>
      <div class="muted">Unidad <?= (int)$unidadActiva ?> · <?= e((string)($identity['role_code'] ?? '')) ?></div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a class="btn btn-outline-light btn-sm" href="administrar_gestiones.php"><i class="bi bi-arrow-left"></i> Volver</a>
      <a class="btn btn-success btn-sm" href="../inicio.php"><i class="bi bi-house"></i> Inicio</a>
    </div>
  </header>

  <?php if ($msg !== ''): ?>
    <div class="alert alert-<?= e($msgType) ?>"><?= e($msg) ?></div>
  <?php endif; ?>

  <div class="grid">
    <aside class="panel">
      <h2 class="h6 fw-bold mb-3">Areas</h2>
      <div class="area-list">
        <?php foreach ($areas as $a): ?>
          <a class="area-link <?= $a['slug'] === $areaSlug ? 'active' : '' ?>" href="?area=<?= e($a['slug']) ?>">
            <span><?= e($a['name']) ?></span>
            <span class="pill"><?= e($a['code']) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </aside>

    <main>
      <section class="panel <?= $hasActiveRules ? 'status-locked' : 'status-open' ?>">
        <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
          <div>
            <h2 class="h5 fw-bold mb-1"><?= e($areas[$areaSlug]['name'] ?? $areaSlug) ?></h2>
            <div class="muted">Storage: storage/unidades/ecmilm/<?= e($areaSlug) ?></div>
          </div>
          <span class="pill"><?= $hasActiveRules ? 'Cerrada por permisos' : 'Sin permisos cargados: acceso abierto' ?></span>
        </div>
      </section>

      <section class="panel">
        <h2 class="h6 fw-bold mb-3">Agregar autorizado</h2>
        <form method="post" class="row g-3">
          <?= csrf_input() ?>
          <input type="hidden" name="action" value="add">
          <input type="hidden" name="area_slug" value="<?= e($areaSlug) ?>">
          <div class="col-md-5">
            <label class="form-label">Personal</label>
            <select name="personal_id" class="form-select">
              <option value="0">Seleccionar...</option>
              <?php foreach ($people as $p): ?>
                <?php
                  $label = trim((string)($p['apellido_nombre'] ?? ''));
                  if ($label === '') $label = trim(implode(' ', array_filter([(string)($p['grado'] ?? ''), (string)($p['arma'] ?? ''), (string)($p['apellido'] ?? ''), (string)($p['nombre'] ?? '')])));
                  $domain = area_shared_perm_norm_user((string)($p['usuario_intranet'] ?? ''));
                ?>
                <option value="<?= (int)$p['id'] ?>"><?= e($label) ?><?= $domain !== '' ? ' · ' . e($domain) : '' ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Usuario dominio</label>
            <input class="form-control" name="domain_username" placeholder="DOMINIO\\usuario">
          </div>
          <div class="col-md-2">
            <label class="form-label">Funcion</label>
            <select name="position_label" class="form-select">
              <option>Jefe</option>
              <option>Encargado</option>
              <option>Auxiliar</option>
              <option>Otro</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">Permiso</label>
            <select name="permission" class="form-select">
              <option value="read">Lectura</option>
              <option value="write">Edicion</option>
              <option value="admin">Admin</option>
            </select>
          </div>
          <div class="col-12">
            <button class="btn btn-primary fw-bold" type="submit"><i class="bi bi-plus-circle"></i> Agregar permiso</button>
          </div>
        </form>
      </section>

      <section class="panel">
        <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap mb-2">
          <h2 class="h6 fw-bold mb-0">Autorizados</h2>
          <span class="pill"><?= count($permissions) ?> registros</span>
        </div>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead>
              <tr>
                <th>Persona</th>
                <th>Dominio</th>
                <th>Funcion</th>
                <th>Permiso</th>
                <th>Estado</th>
                <th class="text-end">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$permissions): ?>
                <tr><td colspan="6" class="muted">Todavia no hay permisos para esta area.</td></tr>
              <?php endif; ?>
              <?php foreach ($permissions as $row): ?>
                <tr>
                  <td>
                    <div class="fw-bold"><?= e($row['display_name'] ?? '') ?></div>
                    <div class="muted small">DNI <?= e($row['dni'] ?? '-') ?></div>
                  </td>
                  <td><?= e($row['domain_username'] ?? '-') ?></td>
                  <td><?= e($row['position_label'] ?? '-') ?></td>
                  <td><?= e($row['permission'] ?? 'read') ?></td>
                  <td><span class="pill"><?= ((int)$row['active'] === 1) ? 'Activo' : 'Inactivo' ?></span></td>
                  <td class="text-end">
                    <form method="post" class="d-inline">
                      <?= csrf_input() ?>
                      <input type="hidden" name="area_slug" value="<?= e($areaSlug) ?>">
                      <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                      <button class="btn btn-outline-light btn-sm" name="action" value="toggle" type="submit"><?= ((int)$row['active'] === 1) ? 'Desactivar' : 'Activar' ?></button>
                    </form>
                    <form method="post" class="d-inline" onsubmit="return confirm('Eliminar este permiso?');">
                      <?= csrf_input() ?>
                      <input type="hidden" name="area_slug" value="<?= e($areaSlug) ?>">
                      <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                      <button class="btn btn-outline-danger btn-sm" name="action" value="delete" type="submit">Eliminar</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    </main>
  </div>
</div>
</body>
</html>

