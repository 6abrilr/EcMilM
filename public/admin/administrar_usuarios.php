<?php
// admin/administrar_usuarios.php — CRUD usuarios (personal_unidad) + roles (tabla roles) + destino (id/texto)
// - Alta por DNI
// - Edición de rol y destino (destino_id y/o destino_interno)
// - Multi-unidad: SUPERADMIN (unidad activa o todas), ADMIN (solo su unidad)
//
// ✅ FIXES aplicados (según tu esquema real):
// 1) BUG INSERT: se eliminó el llamado incorrecto a set_updated_fields() dentro del INSERT.
// 2) BUG permisos: fallback de nivel si roles.nivel está vacío/0 para ADMIN/SUPERADMIN.
// 3) Update masivo más seguro: si NO es superadmin, se actualiza con WHERE id AND unidad_id.
// 4) Destino mostrado: usa destino join (destino_id) y fallback a destino_interno.
//
// Requisitos esperados:
// - personal_unidad: role_id, destino_id, destino_interno (según tu SQL SI existen)
// - roles: id, codigo, nombre, nivel (si no existe, usa fallback mínimo)
// - destino: id, codigo, nombre, unidad_id (si no existe unidad_id, el combo por unidad puede quedar vacío)
//
// Nota: NO modifica DB. Solo corrige lógica.

declare(strict_types=1);

require_once __DIR__ . '/../../auth/bootstrap.php';
require_login();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/ui.php';

if (!function_exists('e')) {
  function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}
function norm_dni(string $dni): string { return preg_replace('/\D+/', '', $dni) ?? ''; }
function qi(string $name): string { return '`' . str_replace('`','``', $name) . '`'; }

function csrf_if_exists(): void {
  if (function_exists('csrf_input')) {
    $out = csrf_input();
    if (is_string($out) && $out !== '') echo $out;
  }
}
function csrf_verify_if_exists(): void {
  if (function_exists('csrf_verify')) csrf_verify();
}

function table_has_column(PDO $pdo, string $table, string $col): bool {
  try {
    $st = $pdo->prepare("SHOW COLUMNS FROM ".qi($table)." LIKE ?");
    $st->execute([$col]);
    return (bool)$st->fetch(PDO::FETCH_ASSOC);
  } catch (Throwable $e) {
    return false;
  }
}

/* ==========================================================
   0) Detectar columnas reales del esquema
   ========================================================== */
$col_role_id         = table_has_column($pdo, 'personal_unidad', 'role_id');
$col_destino_id      = table_has_column($pdo, 'personal_unidad', 'destino_id');
$col_destino_interno = table_has_column($pdo, 'personal_unidad', 'destino_interno');

$col_apellido_nombre = table_has_column($pdo, 'personal_unidad', 'apellido_nombre');
$col_apellido        = table_has_column($pdo, 'personal_unidad', 'apellido');
$col_nombre          = table_has_column($pdo, 'personal_unidad', 'nombre');

$col_updated_at      = table_has_column($pdo, 'personal_unidad', 'updated_at');
$col_updated_by      = table_has_column($pdo, 'personal_unidad', 'updated_by_id');
$col_created_at      = table_has_column($pdo, 'personal_unidad', 'created_at');
$col_created_by      = table_has_column($pdo, 'personal_unidad', 'created_by_id');

/* ==========================================================
   1) Usuario actual y permisos (ADMIN / SUPERADMIN)
   ========================================================== */
$user = function_exists('current_user') ? current_user() : ($_SESSION['user'] ?? []);
$dniNorm = norm_dni((string)($user['dni'] ?? $user['username'] ?? ''));

if ($dniNorm === '') {
  http_response_code(401);
  echo "Sesión inválida (sin DNI).";
  exit;
}

// Traer mi registro en personal_unidad
$me = null;
try {
  $nameExpr = $col_apellido_nombre
    ? "pu.apellido_nombre"
    : (($col_apellido && $col_nombre) ? "CONCAT_WS(' ', pu.apellido, pu.nombre)" : "''");

  $st = $pdo->prepare("
    SELECT pu.id, pu.unidad_id, ".($col_role_id ? "pu.role_id" : "NULL AS role_id").",
           {$nameExpr} AS nombre_show
    FROM personal_unidad pu
    WHERE REPLACE(REPLACE(REPLACE(pu.dni,'.',''),'-',''),' ','') = :dni
    LIMIT 1
  ");
  $st->execute([':dni' => $dniNorm]);
  $me = $st->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) {
  $me = null;
}

if (!$me) {
  http_response_code(403);
  echo "No se encontró tu usuario en personal_unidad (DNI {$dniNorm}).";
  exit;
}

$myPersonalId = (int)$me['id'];
$myUnidadId   = (int)$me['unidad_id'];
$myRoleId     = (int)($me['role_id'] ?? 0);

// Resolver rol efectivo por roles.codigo (si existe tabla roles)
$myRoleCodigo = 'USUARIO';
$myRoleNivel  = 0;

try {
  if ($myRoleId > 0) {
    $st = $pdo->prepare("SELECT codigo, nivel FROM roles WHERE id = :id LIMIT 1");
    $st->execute([':id'=>$myRoleId]);
    if ($r = $st->fetch(PDO::FETCH_ASSOC)) {
      $myRoleCodigo = (string)($r['codigo'] ?? 'USUARIO');
      $myRoleNivel  = (int)($r['nivel'] ?? 0);
    }
  }
} catch (Throwable $e) {}

// Fallback por usuario_roles si existe (opcional)
try {
  $pdo->query("SELECT 1 FROM usuario_roles LIMIT 1");
  $has_ur = true;
} catch (Throwable $e) {
  $has_ur = false;
}

if ($myRoleCodigo === 'USUARIO' && $has_ur) {
  try {
    $st = $pdo->prepare("
      SELECT r.codigo, r.nivel
      FROM usuario_roles ur
      INNER JOIN roles r ON r.id = ur.role_id
      WHERE ur.personal_id = :pid
        AND (ur.unidad_id IS NULL OR ur.unidad_id = :uid)
      ORDER BY r.nivel DESC, ur.created_at DESC, ur.id DESC
      LIMIT 1
    ");
    $st->execute([':pid'=>$myPersonalId, ':uid'=>$myUnidadId]);
    if ($r = $st->fetch(PDO::FETCH_ASSOC)) {
      $myRoleCodigo = (string)($r['codigo'] ?? 'USUARIO');
      $myRoleNivel  = (int)($r['nivel'] ?? 0);
    }
  } catch (Throwable $e) {}
}

/* ✅ FIX: fallback de nivel si roles.nivel no está seteado */
if ($myRoleNivel <= 0) {
  if ($myRoleCodigo === 'SUPERADMIN') $myRoleNivel = 100;
  elseif ($myRoleCodigo === 'ADMIN')  $myRoleNivel = 50;
  else                                $myRoleNivel = 10;
}

$esSuperAdmin = ($myRoleCodigo === 'SUPERADMIN');
$esAdmin      = ($myRoleCodigo === 'ADMIN') || $esSuperAdmin;

if (!$esAdmin) {
  http_response_code(403);
  echo "Acceso restringido. Solo ADMIN/SUPERADMIN.";
  exit;
}

/* ==========================================================
   2) Unidad activa (SUPERADMIN puede elegir)
   ========================================================== */
$unidadActiva = $myUnidadId;
if ($esSuperAdmin) {
  $uSel = (int)($_SESSION['unidad_id'] ?? 0);
  if ($uSel > 0) $unidadActiva = $uSel;
}

/* ==========================================================
   3) Roles disponibles (tabla roles)
   ========================================================== */
$roles = [];
try {
  $st = $pdo->query("SELECT id, codigo, nombre, nivel FROM roles ORDER BY nivel DESC, id ASC");
  $roles = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
  // fallback mínimo si no existe tabla roles
  $roles = [
    ['id'=>1,'codigo'=>'SUPERADMIN','nombre'=>'Superadministrador','nivel'=>100],
    ['id'=>2,'codigo'=>'ADMIN','nombre'=>'Administrador','nivel'=>50],
    ['id'=>3,'codigo'=>'USUARIO','nombre'=>'Usuario','nivel'=>10],
  ];
}

$rolesById = [];
$roleIdUsuario = 0;
foreach ($roles as $r) {
  $rolesById[(int)$r['id']] = $r;
  if (($r['codigo'] ?? '') === 'USUARIO') $roleIdUsuario = (int)$r['id'];
}
if ($roleIdUsuario === 0 && !empty($roles)) $roleIdUsuario = (int)$roles[count($roles)-1]['id'];

function can_assign_role(bool $esSuperAdmin, int $myNivel, array $targetRoleRow): bool {
  $codigo = (string)($targetRoleRow['codigo'] ?? '');
  $nivel  = (int)($targetRoleRow['nivel'] ?? 0);
  if ($esSuperAdmin) return true;
  if ($codigo === 'SUPERADMIN') return false;
  return $nivel <= $myNivel;
}

/* ==========================================================
   4) Filtros GET
   ========================================================== */
$searchDni = trim((string)($_GET['dni'] ?? ''));
$searchNom = trim((string)($_GET['nombre'] ?? ''));
$verTodas  = $esSuperAdmin && (isset($_GET['all']) && $_GET['all'] === '1');

/* ==========================================================
   5) Destinos (por unidad)
   ========================================================== */
$destinosByUnidad = [];
try {
  if ($verTodas) {
    // Se arma después de obtener $rows
  } else {
    $st = $pdo->prepare("
      SELECT id, unidad_id, codigo, nombre, orden, activo
      FROM destino
      WHERE unidad_id = :uid
      ORDER BY orden ASC, codigo ASC, id ASC
    ");
    $st->execute([':uid'=>$unidadActiva]);
    $destinosByUnidad[$unidadActiva] = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }
} catch (Throwable $e) {
  $destinosByUnidad = [];
}

/* ==========================================================
   6) Helpers de auditoría
   ========================================================== */
function set_updated_fields(PDO $pdo, array &$sets, array &$params, int $myPersonalId): void {
  if (table_has_column($pdo, 'personal_unidad', 'updated_at')) {
    $sets[] = "updated_at = NOW()";
  }
  if (table_has_column($pdo, 'personal_unidad', 'updated_by_id')) {
    $sets[] = "updated_by_id = :updated_by_id";
    $params[':updated_by_id'] = $myPersonalId;
  }
}
function set_created_fields(PDO $pdo, array &$cols, array &$vals, array &$params, int $myPersonalId): void {
  if (table_has_column($pdo, 'personal_unidad', 'created_at')) {
    $cols[] = "created_at";
    $vals[] = "NOW()";
  }
  if (table_has_column($pdo, 'personal_unidad', 'created_by_id')) {
    $cols[] = "created_by_id";
    $vals[] = ":created_by_id";
    $params[':created_by_id'] = $myPersonalId;
  }
}

/* ==========================================================
   7) POST acciones:
      - add_user (alta)
      - save_all (edición masiva)
   ========================================================== */
$mensaje = '';
$mensaje_tipo = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    csrf_verify_if_exists();

    /* ---------- ALTA DE USUARIO ---------- */
    if (isset($_POST['add_user'])) {
      $dniNew = norm_dni((string)($_POST['new_dni'] ?? ''));
      $nomNew = trim((string)($_POST['new_nombre'] ?? ''));
      $rolNew = (int)($_POST['new_role_id'] ?? 0);
      $desNew = (int)($_POST['new_destino_id'] ?? 0);

      if ($dniNew === '' || strlen($dniNew) < 6) {
        throw new RuntimeException("DNI inválido.");
      }

      if ($rolNew <= 0 || !isset($rolesById[$rolNew])) $rolNew = $roleIdUsuario;
      if (!can_assign_role($esSuperAdmin, $myRoleNivel, $rolesById[$rolNew])) {
        $rolNew = $roleIdUsuario;
      }

      // Si ya existe ese DNI en la unidad, no insertamos: actualizamos rol/destino
      $st = $pdo->prepare("
        SELECT id, unidad_id
        FROM personal_unidad
        WHERE REPLACE(REPLACE(REPLACE(dni,'.',''),'-',''),' ','') = :dni
          AND unidad_id = :uid
        LIMIT 1
      ");
      $st->execute([':dni'=>$dniNew, ':uid'=>$unidadActiva]);
      $ex = $st->fetch(PDO::FETCH_ASSOC);

      if ($ex) {
        $pid = (int)$ex['id'];

        $sets = [];
        $params = [':id'=>$pid, ':uid'=>$unidadActiva];

        if ($col_role_id) {
          $sets[] = "role_id = :role_id";
          $params[':role_id'] = $rolNew;
        }
        if ($col_destino_id) {
          $sets[] = "destino_id = :destino_id";
          $params[':destino_id'] = ($desNew > 0 ? $desNew : null);
        }

        // si hay destino_interno, lo sincronizamos desde destino.nombre
        if ($col_destino_interno) {
          $destName = null;
          if ($desNew > 0) {
            $stD = $pdo->prepare("SELECT nombre FROM destino WHERE id = :id LIMIT 1");
            $stD->execute([':id'=>$desNew]);
            $destName = $stD->fetchColumn();
          }
          $sets[] = "destino_interno = :destino_interno";
          $params[':destino_interno'] = ($destName !== false && $destName !== null) ? (string)$destName : null;
        }

        set_updated_fields($pdo, $sets, $params, $myPersonalId);

        if (!$sets) throw new RuntimeException("No hay columnas editables (role_id/destino_id/destino_interno no existen).");

        $sql = "UPDATE personal_unidad SET ".implode(', ', $sets)." WHERE id = :id AND unidad_id = :uid LIMIT 1";
        $pdo->prepare($sql)->execute($params);

        $mensaje = "El usuario ya existía en la unidad. Se actualizó rol/destino.";
        $mensaje_tipo = "success";
      } else {
        // INSERT mínimo según columnas disponibles
        $cols = ['unidad_id', 'dni'];
        $vals = [':unidad_id', ':dni'];
        $params = [':unidad_id'=>$unidadActiva, ':dni'=>$dniNew];

        if ($col_apellido_nombre) {
          $cols[] = 'apellido_nombre';
          $vals[] = ':apellido_nombre';
          $params[':apellido_nombre'] = ($nomNew !== '' ? $nomNew : 'SIN NOMBRE');
        } elseif ($col_apellido && $col_nombre) {
          $cols[] = 'apellido';
          $vals[] = ':apellido';
          $params[':apellido'] = ($nomNew !== '' ? $nomNew : 'SIN');
          $cols[] = 'nombre';
          $vals[] = ':nombre';
          $params[':nombre'] = ($nomNew !== '' ? '' : 'NOMBRE');
        }

        if ($col_role_id) {
          $cols[] = 'role_id';
          $vals[] = ':role_id';
          $params[':role_id'] = $rolNew;
        }

        if ($col_destino_id) {
          $cols[] = 'destino_id';
          $vals[] = ':destino_id';
          $params[':destino_id'] = ($desNew > 0 ? $desNew : null);
        }

        if ($col_destino_interno) {
          $destName = null;
          if ($desNew > 0) {
            $stD = $pdo->prepare("SELECT nombre FROM destino WHERE id = :id LIMIT 1");
            $stD->execute([':id'=>$desNew]);
            $destName = $stD->fetchColumn();
          }
          $cols[] = 'destino_interno';
          $vals[] = ':destino_interno';
          $params[':destino_interno'] = ($destName !== false && $destName !== null) ? (string)$destName : null;
        }

        set_created_fields($pdo, $cols, $vals, $params, $myPersonalId);
        // ✅ FIX: NO llamar set_updated_fields() en INSERT

        $sql = "INSERT INTO personal_unidad (".implode(',', array_map('qi', $cols)).") VALUES (".implode(',', $vals).")";
        $pdo->prepare($sql)->execute($params);

        $mensaje = "Usuario creado correctamente en personal_unidad (unidad {$unidadActiva}).";
        $mensaje_tipo = "success";
      }
    }

    /* ---------- EDICIÓN MASIVA ---------- */
    if (isset($_POST['save_all'])) {
      $arrRol  = $_POST['role_id'] ?? [];
      $arrDest = $_POST['destino_id'] ?? [];
      $idsForm = $_POST['ids'] ?? [];

      if (!is_array($arrRol))  $arrRol = [];
      if (!is_array($arrDest)) $arrDest = [];
      if (!is_array($idsForm)) $idsForm = [];

      $pdo->beginTransaction();

      foreach ($idsForm as $idRaw) {
        $pid = (int)$idRaw;
        if ($pid <= 0) continue;

        $newRoleId = (int)($arrRol[$pid] ?? 0);
        $newDestId = (int)($arrDest[$pid] ?? 0);

        // si ADMIN (no super), restringir a unidad activa
        if (!$esSuperAdmin) {
          $stS = $pdo->prepare("SELECT unidad_id FROM personal_unidad WHERE id = :id LIMIT 1");
          $stS->execute([':id'=>$pid]);
          $uRow = $stS->fetchColumn();
          if (!is_numeric($uRow) || (int)$uRow !== $unidadActiva) continue;
        }

        if ($newRoleId <= 0 || !isset($rolesById[$newRoleId])) $newRoleId = $roleIdUsuario;
        if (!can_assign_role($esSuperAdmin, $myRoleNivel, $rolesById[$newRoleId])) $newRoleId = $roleIdUsuario;

        $sets = [];
        $params = [':id'=>$pid];

        if ($col_role_id) {
          $sets[] = "role_id = :role_id";
          $params[':role_id'] = $newRoleId;
        }

        if ($col_destino_id) {
          $sets[] = "destino_id = :destino_id";
          $params[':destino_id'] = ($newDestId > 0 ? $newDestId : null);
        }

        if ($col_destino_interno) {
          $destName = null;
          if ($newDestId > 0) {
            $stD = $pdo->prepare("SELECT nombre FROM destino WHERE id = :id LIMIT 1");
            $stD->execute([':id'=>$newDestId]);
            $destName = $stD->fetchColumn();
          }
          $sets[] = "destino_interno = :destino_interno";
          $params[':destino_interno'] = ($destName !== false && $destName !== null) ? (string)$destName : null;
        }

        if (!$sets) continue;

        if ($col_updated_at) $sets[] = "updated_at = NOW()";
        if ($col_updated_by) { $sets[] = "updated_by_id = :updated_by_id"; $params[':updated_by_id'] = $myPersonalId; }

        // ✅ FIX: update más seguro: si no es superadmin, exigir unidad_id
        $sql = "UPDATE personal_unidad SET ".implode(', ', $sets)." WHERE id = :id";
        if (!$esSuperAdmin) { $sql .= " AND unidad_id = :uid"; $params[':uid'] = $unidadActiva; }
        $sql .= " LIMIT 1";

        $pdo->prepare($sql)->execute($params);
      }

      $pdo->commit();

      $mensaje = "Cambios guardados.";
      $mensaje_tipo = "success";
    }

  } catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    $mensaje = "Error: ".$e->getMessage();
    $mensaje_tipo = "danger";
  }
}

/* ==========================================================
   8) Listado de personal (usuarios)
   ========================================================== */
$nameExpr = $col_apellido_nombre
  ? "pu.apellido_nombre"
  : (($col_apellido && $col_nombre) ? "CONCAT_WS(' ', pu.apellido, pu.nombre)" : "''");

$sql = "
  SELECT
    pu.id,
    pu.unidad_id,
    pu.dni,
    pu.grado,
    pu.arma,
    {$nameExpr} AS nombre_show,
    ".($col_role_id ? "pu.role_id" : "NULL AS role_id").",
    ".($col_destino_id ? "pu.destino_id" : "NULL AS destino_id").",
    ".($col_destino_interno ? "pu.destino_interno" : "NULL AS destino_interno").",
    u.nombre_corto AS unidad_nombre,
    d.codigo AS destino_codigo,
    d.nombre AS destino_nombre
  FROM personal_unidad pu
  INNER JOIN unidades u ON u.id = pu.unidad_id
  LEFT JOIN destino d ON (".($col_destino_id ? "d.id = pu.destino_id" : "1=0").")
";

$conds = [];
$params = [];

if (!$verTodas) {
  $conds[] = "pu.unidad_id = ?";
  $params[] = $unidadActiva;
}

if ($searchDni !== '') {
  $conds[] = "REPLACE(REPLACE(REPLACE(pu.dni,'.',''),'-',''),' ','') LIKE ?";
  $params[] = '%' . norm_dni($searchDni) . '%';
}
if ($searchNom !== '') {
  if ($col_apellido_nombre) {
    $conds[] = "pu.apellido_nombre LIKE ?";
    $params[] = '%' . $searchNom . '%';
  } elseif ($col_apellido && $col_nombre) {
    $conds[] = "CONCAT_WS(' ', pu.apellido, pu.nombre) LIKE ?";
    $params[] = '%' . $searchNom . '%';
  }
}

if ($conds) $sql .= " WHERE ".implode(' AND ', $conds);
$sql .= " ORDER BY nombre_show ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

/* ==========================================================
   9) Cargar destinos por unidad (si verTodas)
   ========================================================== */
if ($verTodas) {
  try {
    $uids = array_values(array_unique(array_map(fn($r)=>(int)$r['unidad_id'], $rows)));
    if ($uids) {
      $place = implode(',', array_fill(0, count($uids), '?'));
      $st = $pdo->prepare("
        SELECT id, unidad_id, codigo, nombre, orden, activo
        FROM destino
        WHERE unidad_id IN ($place)
        ORDER BY unidad_id ASC, orden ASC, codigo ASC, id ASC
      ");
      $st->execute($uids);
      while ($d = $st->fetch(PDO::FETCH_ASSOC)) {
        $destinosByUnidad[(int)$d['unidad_id']][] = $d;
      }
    }
  } catch (Throwable $e) {
    // noop
  }
}

/* ==========================================================
   UI
   ========================================================== */
ui_header('Gestión de usuarios', ['container'=>'xl', 'show_brand'=>false]);
?>
<link rel="stylesheet" href="../../assets/css/theme-602.css">
<link rel="icon" href="../../assets/img/ecmilm.png">

<style>
  body{
    background:url("../../assets/img/fondo.png") no-repeat center center fixed;
    background-size:cover;
    background-color:#0f1117;
    color:#e9eef5;
    font-family:system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,sans-serif;
  }
  .panel{
    background:rgba(15,17,23,.95);
    border:1px solid rgba(255,255,255,.10);
    border-radius:16px;
    padding:16px;
    margin-top:18px;
    box-shadow:0 10px 24px rgba(0,0,0,.40);
  }
  table.tbl{ width:100%; border-collapse:collapse; font-size:.85rem; }
  .tbl th,.tbl td{ padding:.45rem .55rem; border-bottom:1px solid rgba(255,255,255,.10); vertical-align:middle; }
  .tbl th{ font-weight:900; white-space:nowrap; text-transform:uppercase; letter-spacing:.06em; font-size:.75rem; color:#f8fafc; }
  .tbl-wrap{ overflow:auto; border-radius:14px; border:1px solid rgba(148,163,184,.25); }
  .tbl thead th{ position:sticky; top:0; background:rgba(15,23,42,.98); z-index:2; }
  .form-control,.form-select{
    background:rgba(2,6,23,.70)!important;
    border:1px solid rgba(148,163,184,.25)!important;
    color:#f1f5f9!important;
  }
  .form-control:focus,.form-select:focus{ box-shadow:none!important; border-color:rgba(34,197,94,.55)!important; }
  .badge-soft{
    border:1px solid rgba(255,255,255,.18);
    background:rgba(255,255,255,.06);
    padding:.15rem .55rem;
    border-radius:999px;
    font-size:.78rem;
    color:#e5e7eb;
  }
  .header-back { margin-left:auto; margin-right:17px; margin-top:4px; }
</style>

<div class="container mt-3">

  <div class="d-flex align-items-center">
    <h2 class="h5 mb-0">Gestión de usuarios</h2>
    <div class="header-back">
      <a href="../admin/administrar_gestiones.php" class="btn btn-success btn-sm" style="font-weight:700; padding:.35rem .9rem;">Volver</a>
    </div>
  </div>

  <div class="mt-2">
    <span class="badge-soft">Unidad activa: <?= (int)$unidadActiva ?></span>
    <span class="badge-soft">Rol: <?= e($myRoleCodigo) ?></span>
    <?php if ($verTodas): ?><span class="badge-soft">Viendo TODAS</span><?php endif; ?>
    <?php if (!$col_destino_id && $col_destino_interno): ?><span class="badge-soft">Destino por texto (destino_interno)</span><?php endif; ?>
  </div>

  <div class="panel">
    <?php if ($mensaje !== ''): ?>
      <div class="alert alert-<?= e($mensaje_tipo) ?> py-2 mb-3"><?= e($mensaje) ?></div>
    <?php endif; ?>

    <!-- Alta usuario -->
    <form method="post" class="row g-2 align-items-end mb-3">
      <?php csrf_if_exists(); ?>
      <div class="col-md-2">
        <label class="form-label small mb-1">Nuevo DNI</label>
        <input class="form-control form-control-sm" name="new_dni" placeholder="Ej: 41742406" required>
      </div>
      <div class="col-md-4">
        <label class="form-label small mb-1">Nombre (opcional)</label>
        <input class="form-control form-control-sm" name="new_nombre" placeholder="Apellido y Nombre">
      </div>
      <div class="col-md-3">
        <label class="form-label small mb-1">Rol</label>
        <select class="form-select form-select-sm" name="new_role_id">
          <?php foreach ($roles as $rr): ?>
            <?php
              $rid = (int)$rr['id'];
              $allowed = can_assign_role($esSuperAdmin, $myRoleNivel, $rr);
              if (!$allowed) continue;
              $sel = ($rid === $roleIdUsuario) ? 'selected' : '';
              $label = trim(($rr['codigo'] ?? '').' · '.($rr['nombre'] ?? ''));
            ?>
            <option value="<?= $rid ?>" <?= $sel ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label small mb-1">Destino</label>
        <select class="form-select form-select-sm" name="new_destino_id">
          <option value="0">— Sin asignar —</option>
          <?php foreach (($destinosByUnidad[$unidadActiva] ?? []) as $d): ?>
            <?php
              $did = (int)$d['id'];
              $lbl = trim(((string)($d['codigo'] ?? '')!=='' ? $d['codigo'].' - ' : '').($d['nombre'] ?? ''));
            ?>
            <option value="<?= $did ?>"><?= e($lbl) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 d-flex justify-content-end">
        <button class="btn btn-outline-success btn-sm" name="add_user" style="font-weight:900;">Crear / Actualizar usuario</button>
      </div>
    </form>

    <!-- Filtros -->
    <form method="get" class="row g-2 align-items-end mb-3">
      <div class="col-md-3">
        <label class="form-label small mb-1">Buscar por DNI</label>
        <input class="form-control form-control-sm" name="dni" value="<?= e($searchDni) ?>">
      </div>
      <div class="col-md-5">
        <label class="form-label small mb-1">Buscar por nombre</label>
        <input class="form-control form-control-sm" name="nombre" value="<?= e($searchNom) ?>">
      </div>
      <?php if ($esSuperAdmin): ?>
      <div class="col-md-2">
        <label class="form-label small mb-1">Alcance</label>
        <select class="form-select form-select-sm" name="all">
          <option value="0" <?= $verTodas ? '' : 'selected' ?>>Solo unidad activa</option>
          <option value="1" <?= $verTodas ? 'selected' : '' ?>>Todas</option>
        </select>
      </div>
      <?php endif; ?>
      <div class="col-md-2 d-flex gap-2">
        <button class="btn btn-success btn-sm w-100" style="font-weight:900;">Filtrar</button>
        <a class="btn btn-outline-light btn-sm" href="administrar_usuarios.php">Limpiar</a>
      </div>
    </form>

    <!-- Tabla + edición -->
    <form method="post">
      <?php csrf_if_exists(); ?>

      <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
        <div class="small text-muted">Registros: <b><?= (int)count($rows) ?></b></div>
        <button type="submit" name="save_all" class="btn btn-success btn-sm" style="font-weight:900; padding:.35rem .9rem;">
          Guardar cambios
        </button>
      </div>

      <div class="tbl-wrap">
        <table class="tbl">
          <thead>
            <tr>
              <th>ID</th>
              <th>Unidad</th>
              <th>DNI</th>
              <th>Nombre</th>
              <th>Rol</th>
              <th>Destino (actual)</th>
              <th>Asignar destino</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">No hay registros.</td></tr>
          <?php else: ?>
            <?php foreach ($rows as $r): ?>
              <?php
                $pid = (int)$r['id'];
                $uid = (int)$r['unidad_id'];
                $currentRoleId = (int)($r['role_id'] ?? 0);
                $destinoId = (int)($r['destino_id'] ?? 0);

                $destTxt = '';
                if (!empty($r['destino_codigo']) || !empty($r['destino_nombre'])) {
                  $destTxt = trim(((string)$r['destino_codigo'] !== '' ? $r['destino_codigo'].' - ' : '').(string)$r['destino_nombre']);
                } elseif (!empty($r['destino_interno'])) {
                  $destTxt = (string)$r['destino_interno'];
                } else {
                  $destTxt = 'Sin asignar';
                }

                $destList = $destinosByUnidad[$uid] ?? ($destinosByUnidad[$unidadActiva] ?? []);
                $unidadNombre = (string)($r['unidad_nombre'] ?? ('#'.$uid));
              ?>
              <tr>
                <td>
                  <?= $pid ?>
                  <input type="hidden" name="ids[]" value="<?= $pid ?>">
                </td>
                <td><span class="badge-soft"><?= e($unidadNombre) ?></span></td>
                <td><?= e($r['dni'] ?? '') ?></td>
                <td><?= e($r['nombre_show'] ?? '') ?></td>

                <td>
                  <?php if ($col_role_id): ?>
                    <select class="form-select form-select-sm" name="role_id[<?= $pid ?>]" style="min-width:220px; font-weight:800;">
                      <?php foreach ($roles as $rr): ?>
                        <?php
                          $rid = (int)$rr['id'];
                          $allowed = can_assign_role($esSuperAdmin, $myRoleNivel, $rr);
                          $isCurrent = ($rid === $currentRoleId);
                          $disabled = (!$allowed && !$isCurrent) ? 'disabled' : '';
                          $selected = $isCurrent ? 'selected' : '';
                          $label = trim(($rr['codigo'] ?? '').' · '.($rr['nombre'] ?? ''));
                        ?>
                        <option value="<?= $rid ?>" <?= $selected ?> <?= $disabled ?>><?= e($label) ?></option>
                      <?php endforeach; ?>
                    </select>
                  <?php else: ?>
                    <span class="badge-soft">role_id no existe</span>
                  <?php endif; ?>
                </td>

                <td><span class="badge-soft"><?= e($destTxt) ?></span></td>

                <td>
                  <?php if ($col_destino_id): ?>
                    <select class="form-select form-select-sm" name="destino_id[<?= $pid ?>]" style="min-width:320px; font-weight:700;">
                      <option value="0">— Sin asignar —</option>
                      <?php foreach ($destList as $d): ?>
                        <?php
                          $did = (int)$d['id'];
                          $lbl = trim(((string)($d['codigo'] ?? '')!=='' ? $d['codigo'].' - ' : '').($d['nombre'] ?? ''));
                        ?>
                        <option value="<?= $did ?>" <?= ($did===$destinoId?'selected':'') ?>><?= e($lbl) ?></option>
                      <?php endforeach; ?>
                    </select>
                  <?php else: ?>
                    <span class="badge-soft">No hay destino_id (usa destino_interno)</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>

    </form>
  </div>
</div>

<?php ui_footer(); ?>
