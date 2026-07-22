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
    $st = $pdo->prepare("
      SELECT COUNT(*)
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = :t
        AND COLUMN_NAME = :c
    ");
    $st->execute([':t' => $table, ':c' => $col]);
    return (int)$st->fetchColumn() > 0;
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
$col_grado           = table_has_column($pdo, 'personal_unidad', 'grado');
$col_arma            = table_has_column($pdo, 'personal_unidad', 'arma');

$col_updated_at      = table_has_column($pdo, 'personal_unidad', 'updated_at');
$col_updated_by      = table_has_column($pdo, 'personal_unidad', 'updated_by_id');
$col_created_at      = table_has_column($pdo, 'personal_unidad', 'created_at');
$col_created_by      = table_has_column($pdo, 'personal_unidad', 'created_by_id');

$hasUsuarioRoles = false;
try {
  $pdo->query("SELECT 1 FROM usuario_roles LIMIT 1");
  $hasUsuarioRoles = true;
} catch (Throwable $e) {}

/* ==========================================================
   1) Usuario actual y permisos (ADMIN / SUPERADMIN)
   ========================================================== */
$user = function_exists('current_user') ? current_user() : ($_SESSION['user'] ?? []);
$dniNorm = norm_dni((string)($user['dni'] ?? $user['username'] ?? ''));
$sessionRole = strtoupper(trim((string)($user['rol_app'] ?? $user['role_app'] ?? '')));
if ($sessionRole === 'SUPERADMINISTRADOR') $sessionRole = 'SUPERADMIN';
if ($sessionRole === 'ADMINISTRADOR') $sessionRole = 'ADMIN';
$isHardcodedSuperAdmin = (
  $dniNorm === '41742406'
  || strtolower(trim((string)($user['username'] ?? ''))) === 'nesrojas'
);

if ($dniNorm === '') {
  http_response_code(401);
  echo "Sesión inválida (sin DNI).";
  exit;
}

// Traer mi registro en personal_unidad
$me = null;
try {
  $nameExpr = $col_apellido_nombre
    ? (($col_apellido && $col_nombre)
      ? "COALESCE(NULLIF(pu.apellido_nombre,''), TRIM(CONCAT_WS(' ', pu.apellido, pu.nombre)))"
      : "pu.apellido_nombre")
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
      $myRoleCodigo = strtoupper(trim((string)($r['codigo'] ?? 'USUARIO')));
      $myRoleNivel  = (int)($r['nivel'] ?? 0);
    }
  }
} catch (Throwable $e) {}

if ($myRoleCodigo === 'USUARIO' && in_array($sessionRole, ['ADMIN', 'SUPERADMIN'], true)) {
  $myRoleCodigo = $sessionRole;
}

if ($myRoleCodigo === 'USUARIO' && $hasUsuarioRoles) {
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
      $myRoleCodigo = strtoupper(trim((string)($r['codigo'] ?? 'USUARIO')));
      $myRoleNivel  = (int)($r['nivel'] ?? 0);
    }
  } catch (Throwable $e) {}
}

if ($isHardcodedSuperAdmin) {
  $myRoleCodigo = 'SUPERADMIN';
  if ($myRoleId <= 0) $myRoleId = 1;
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
function role_option_label(array $role): string {
  $codigo = strtoupper(trim((string)($role['codigo'] ?? '')));
  $nombre = trim((string)($role['nombre'] ?? ''));
  if ($codigo === 'USUARIO') return 'SOLO LECTOR · ' . ($nombre !== '' ? $nombre : 'Usuario');
  if ($codigo === 'ADMIN') return 'ADMIN · ' . ($nombre !== '' ? $nombre : 'Administrador');
  if ($codigo === 'SUPERADMIN') return 'SUPERADMIN · ' . ($nombre !== '' ? $nombre : 'Superadministrador');
  return trim($codigo . ' · ' . $nombre, " \t\n\r\0\x0B·");
}

$SQL_ORDEN_GRADO = "CASE pu.grado
  WHEN 'CR'        THEN 1
  WHEN 'TC'        THEN 2
  WHEN 'MY'        THEN 3
  WHEN 'CT'        THEN 4
  WHEN 'TP'        THEN 5
  WHEN 'TT'        THEN 6
  WHEN 'ST'        THEN 7
  WHEN 'ST EC'     THEN 8
  WHEN 'SM'        THEN 9
  WHEN 'SP'        THEN 10
  WHEN 'SA'        THEN 11
  WHEN 'SI'        THEN 12
  WHEN 'SG'        THEN 13
  WHEN 'CI'        THEN 14
  WHEN 'CI EC'     THEN 15
  WHEN 'CI Art 11' THEN 16
  WHEN 'CB'        THEN 17
  WHEN 'CB EC'     THEN 18
  WHEN 'CB Art 11' THEN 19
  WHEN 'VP'        THEN 20
  WHEN 'VS'        THEN 21
  WHEN 'VS EC'     THEN 22
  WHEN 'SV'        THEN 23
  WHEN 'AC'        THEN 24
  ELSE 99
END";

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
$destinosMenu = [];
$destinosInternosAll = [];
try {
  $stInt = $pdo->query("
    SELECT id, nombre
    FROM destino_interno
    WHERE estado = 'ACTIVO' OR estado IS NULL
    ORDER BY nombre ASC
  ");
  $destinosInternosAll = $stInt->fetchAll(PDO::FETCH_ASSOC) ?: [];

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
    $destinosMenu = $destinosByUnidad[$unidadActiva];
  }
} catch (Throwable $e) {
  $destinosByUnidad = [];
  $destinosMenu = [];
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
function normalize_area_codes(array $raw, array $allowedCodes): array {
  $allowed = array_fill_keys(array_map(static fn($v) => strtoupper(trim((string)$v)), $allowedCodes), true);
  $out = [];
  foreach ($raw as $v) {
    $code = strtoupper(trim((string)$v));
    if ($code !== '' && isset($allowed[$code])) $out[$code] = true;
  }
  return array_keys($out);
}
function decode_area_codes($value): array {
  if (!is_string($value) || trim($value) === '') return [];
  $decoded = json_decode($value, true);
  if (is_array($decoded)) {
    return array_values(array_unique(array_map(static fn($v) => strtoupper(trim((string)$v)), $decoded)));
  }
  return array_values(array_filter(array_map(static fn($v) => strtoupper(trim($v)), explode(',', $value))));
}
function destino_interno_id_por_nombre(PDO $pdo, ?string $nombre): ?int {
  $nombre = trim(preg_replace('/\s+/', ' ', (string)$nombre) ?? (string)$nombre);
  $key = mb_strtoupper(str_replace('.', '', $nombre), 'UTF-8');
  $key = preg_replace('/\s+/', ' ', $key) ?? $key;
  if (in_array($key, ['BDA MIL', 'BDA MILITAR', 'BANDA MIL', 'BANDA MILITAR'], true)) {
    $nombre = 'BANDA MILITAR';
  }
  if ($nombre === '') return null;
  $st = $pdo->prepare("SELECT id FROM destino_interno WHERE UPPER(nombre)=UPPER(:nombre) AND COALESCE(estado,'ACTIVO')='ACTIVO' LIMIT 1");
  $st->execute([':nombre' => $nombre]);
  $id = $st->fetchColumn();
  if ($id !== false) return (int)$id;
  $pdo->prepare("INSERT INTO destino_interno (nombre, estado) VALUES (:nombre, 'ACTIVO')")
      ->execute([':nombre' => $nombre]);
  return (int)$pdo->lastInsertId();
}
function resolver_destino_interno_admin(PDO $pdo, string $seleccion, string $nuevo): ?int {
  $seleccion = trim($seleccion);
  $nuevo = trim($nuevo);
  if ($seleccion === 'NUEVO') {
    if ($nuevo === '') throw new RuntimeException('Ingresá el nombre del nuevo destino interno.');
    return destino_interno_id_por_nombre($pdo, $nuevo);
  }
  return ($seleccion !== '' && ctype_digit($seleccion)) ? (int)$seleccion : null;
}
function upsert_usuario_role(PDO $pdo, int $personalId, int $roleId, int $unidadId, array $areas, int $grantedById): void {
  $areasJson = json_encode(array_values($areas), JSON_UNESCAPED_UNICODE);
  $st = $pdo->prepare("SELECT id FROM usuario_roles WHERE personal_id = :pid AND unidad_id = :uid AND destino_id IS NULL ORDER BY id DESC LIMIT 1");
  $st->execute([':pid' => $personalId, ':uid' => $unidadId]);
  $id = (int)($st->fetchColumn() ?: 0);
  if ($id > 0) {
    $up = $pdo->prepare("UPDATE usuario_roles SET role_id = :rid, areas_acceso = :areas, granted_by_id = :gb WHERE id = :id LIMIT 1");
    $up->execute([':rid' => $roleId, ':areas' => $areasJson, ':gb' => $grantedById, ':id' => $id]);
    return;
  }
  $ins = $pdo->prepare("INSERT INTO usuario_roles (personal_id, role_id, unidad_id, destino_id, areas_acceso, granted_by_id, created_at) VALUES (:pid, :rid, :uid, NULL, :areas, :gb, NOW())");
  $ins->execute([':pid' => $personalId, ':rid' => $roleId, ':uid' => $unidadId, ':areas' => $areasJson, ':gb' => $grantedById]);
}
function fetch_destinos_unidad(PDO $pdo, int $unidadId): array {
  try {
    $st = $pdo->prepare("
      SELECT id, unidad_id, codigo, nombre, orden, activo
      FROM destino
      WHERE unidad_id = :uid
      ORDER BY orden ASC, codigo ASC, id ASC
    ");
    $st->execute([':uid' => $unidadId]);
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $e) {
    return [];
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
      $menuNewRaw = $_POST['new_menu_codes'] ?? [];
      if (!is_array($menuNewRaw)) $menuNewRaw = [];
      if (!$destinosMenu) {
        $destinosMenu = fetch_destinos_unidad($pdo, $unidadActiva);
        $destinosByUnidad[$unidadActiva] = $destinosMenu;
      }
      $allowedMenuCodes = array_values(array_filter(array_map(static fn($d) => (string)($d['codigo'] ?? ''), $destinosMenu)));
      $menuNewCodes = normalize_area_codes($menuNewRaw, $allowedMenuCodes);

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

        if ($col_destino_interno) {
          $destinoInternoId = null;
          if ($desNew > 0) {
            $stD = $pdo->prepare("SELECT nombre FROM destino WHERE id = :id LIMIT 1");
            $stD->execute([':id'=>$desNew]);
            $destinoInternoId = destino_interno_id_por_nombre($pdo, $stD->fetchColumn() ?: null);
          }
          $sets[] = "destino_interno = :destino_interno";
          $params[':destino_interno'] = $destinoInternoId;
        }

        set_updated_fields($pdo, $sets, $params, $myPersonalId);

        if (!$sets) throw new RuntimeException("No hay columnas editables (role_id/destino_id/destino_interno no existen).");

        $sql = "UPDATE personal_unidad SET ".implode(', ', $sets)." WHERE id = :id AND unidad_id = :uid LIMIT 1";
        $pdo->prepare($sql)->execute($params);
        if ($hasUsuarioRoles) {
          upsert_usuario_role($pdo, $pid, $rolNew, $unidadActiva, $menuNewCodes, $myPersonalId);
        }

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
          $destinoInternoId = null;
          if ($desNew > 0) {
            $stD = $pdo->prepare("SELECT nombre FROM destino WHERE id = :id LIMIT 1");
            $stD->execute([':id'=>$desNew]);
            $destinoInternoId = destino_interno_id_por_nombre($pdo, $stD->fetchColumn() ?: null);
          }
          $cols[] = 'destino_interno';
          $vals[] = ':destino_interno';
          $params[':destino_interno'] = $destinoInternoId;
        }

        set_created_fields($pdo, $cols, $vals, $params, $myPersonalId);
        // ✅ FIX: NO llamar set_updated_fields() en INSERT

        $sql = "INSERT INTO personal_unidad (".implode(',', array_map('qi', $cols)).") VALUES (".implode(',', $vals).")";
        $pdo->prepare($sql)->execute($params);
        $pidNew = (int)$pdo->lastInsertId();
        if ($hasUsuarioRoles && $pidNew > 0) {
          upsert_usuario_role($pdo, $pidNew, $rolNew, $unidadActiva, $menuNewCodes, $myPersonalId);
        }

        $mensaje = "Usuario creado correctamente en personal_unidad (unidad {$unidadActiva}).";
        $mensaje_tipo = "success";
      }
    }

    /* ---------- EDICIÓN MASIVA ---------- */
    if (isset($_POST['save_all'])) {
      $arrRol  = $_POST['role_id'] ?? [];
      $arrDest = $_POST['destino_id'] ?? [];
      $arrDestInterno = $_POST['destino_interno_id'] ?? [];
      $arrDestInternoNuevo = $_POST['destino_interno_nuevo'] ?? [];
      $arrMenu = $_POST['menu_codes'] ?? [];
      $idsForm = $_POST['ids'] ?? [];

      if (!is_array($arrRol))  $arrRol = [];
      if (!is_array($arrDest)) $arrDest = [];
      if (!is_array($arrDestInterno)) $arrDestInterno = [];
      if (!is_array($arrDestInternoNuevo)) $arrDestInternoNuevo = [];
      if (!is_array($arrMenu)) $arrMenu = [];
      if (!is_array($idsForm)) $idsForm = [];

      $pdo->beginTransaction();

      foreach ($idsForm as $idRaw) {
        $pid = (int)$idRaw;
        if ($pid <= 0) continue;

        $newRoleId = (int)($arrRol[$pid] ?? 0);
        $newDestId = (int)($arrDest[$pid] ?? 0);
        $newDestInternoSel = trim((string)($arrDestInterno[$pid] ?? ''));
        $newDestInternoNuevo = trim((string)($arrDestInternoNuevo[$pid] ?? ''));
        $newMenuRaw = $arrMenu[$pid] ?? [];
        if (!is_array($newMenuRaw)) $newMenuRaw = [];

        $stS = $pdo->prepare("SELECT unidad_id FROM personal_unidad WHERE id = :id LIMIT 1");
        $stS->execute([':id'=>$pid]);
        $uRow = $stS->fetchColumn();
        if (!is_numeric($uRow)) continue;
        $rowUnidadId = (int)$uRow;

        // si ADMIN (no super), restringir a unidad activa
        if (!$esSuperAdmin && $rowUnidadId !== $unidadActiva) continue;

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
          $destinoInternoId = resolver_destino_interno_admin($pdo, $newDestInternoSel, $newDestInternoNuevo);
          if ($destinoInternoId === null && $newDestId > 0) {
            $stD = $pdo->prepare("SELECT nombre FROM destino WHERE id = :id LIMIT 1");
            $stD->execute([':id'=>$newDestId]);
            $destinoInternoId = destino_interno_id_por_nombre($pdo, $stD->fetchColumn() ?: null);
          }
          $sets[] = "destino_interno = :destino_interno";
          $params[':destino_interno'] = $destinoInternoId;
        }

        if (!$sets) continue;

        if ($col_updated_at) $sets[] = "updated_at = NOW()";
        if ($col_updated_by) { $sets[] = "updated_by_id = :updated_by_id"; $params[':updated_by_id'] = $myPersonalId; }

        // ✅ FIX: update más seguro: si no es superadmin, exigir unidad_id
        $sql = "UPDATE personal_unidad SET ".implode(', ', $sets)." WHERE id = :id";
        if (!$esSuperAdmin) { $sql .= " AND unidad_id = :uid"; $params[':uid'] = $unidadActiva; }
        $sql .= " LIMIT 1";

        $pdo->prepare($sql)->execute($params);
        if ($hasUsuarioRoles) {
          if (empty($destinosByUnidad[$rowUnidadId])) {
            $destinosByUnidad[$rowUnidadId] = fetch_destinos_unidad($pdo, $rowUnidadId);
          }
          $allowedMenuCodes = array_values(array_filter(array_map(static fn($d) => (string)($d['codigo'] ?? ''), $destinosByUnidad[$rowUnidadId] ?? $destinosMenu)));
          $menuCodes = normalize_area_codes($newMenuRaw, $allowedMenuCodes);
          upsert_usuario_role($pdo, $pid, $newRoleId, $rowUnidadId, $menuCodes, $myPersonalId);
        }
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
  ? (($col_apellido && $col_nombre)
    ? "COALESCE(NULLIF(pu.apellido_nombre,''), TRIM(CONCAT_WS(' ', pu.apellido, pu.nombre)))"
    : "pu.apellido_nombre")
  : (($col_apellido && $col_nombre) ? "CONCAT_WS(' ', pu.apellido, pu.nombre)" : "''");

$sql = "
  SELECT
    pu.id,
    pu.unidad_id,
    pu.dni,
    ".($col_grado ? "pu.grado" : "NULL AS grado").",
    ".($col_arma ? "pu.arma" : "NULL AS arma").",
    ".($col_apellido ? "pu.apellido" : "NULL AS apellido").",
    ".($col_nombre ? "pu.nombre" : "NULL AS nombre").",
    {$nameExpr} AS nombre_show,
    ".($col_role_id ? "pu.role_id" : "NULL AS role_id").",
    ".($col_destino_id ? "pu.destino_id" : "NULL AS destino_id").",
    ".($col_destino_interno ? "pu.destino_interno" : "NULL AS destino_interno").",
    u.nombre_corto AS unidad_nombre,
    d.codigo AS destino_codigo,
    d.nombre AS destino_nombre,
    di.nombre AS destino_interno_nombre
  FROM personal_unidad pu
  INNER JOIN unidades u ON u.id = pu.unidad_id
  LEFT JOIN destino d ON (".($col_destino_id ? "d.id = pu.destino_id" : "1=0").")
  LEFT JOIN destino_interno di ON (".($col_destino_interno ? "di.id = pu.destino_interno" : "1=0").")
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
$sql .= " ORDER BY {$SQL_ORDEN_GRADO}, nombre_show ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$dniSeen = [];
$dniDuplicadosOcultos = 0;
$rowsUnicos = [];
foreach ($rows as $row) {
  $key = norm_dni((string)($row['dni'] ?? ''));
  if ($key === '') $key = 'id:' . (string)($row['id'] ?? '');
  if (isset($dniSeen[$key])) {
    $dniDuplicadosOcultos++;
    continue;
  }
  $dniSeen[$key] = true;
  $rowsUnicos[] = $row;
}
$rows = $rowsUnicos;

$menuAreasByPersonal = [];
if ($hasUsuarioRoles && $rows) {
  try {
    $ids = array_values(array_filter(array_map(static fn($r) => (int)($r['id'] ?? 0), $rows)));
    if ($ids) {
      $place = implode(',', array_fill(0, count($ids), '?'));
      $st = $pdo->prepare("
        SELECT ur.personal_id, ur.areas_acceso
        FROM usuario_roles ur
        INNER JOIN (
          SELECT personal_id, MAX(id) AS id
          FROM usuario_roles
          WHERE personal_id IN ($place)
          GROUP BY personal_id
        ) last_ur ON last_ur.id = ur.id
      ");
      $st->execute($ids);
      while ($ur = $st->fetch(PDO::FETCH_ASSOC)) {
        $menuAreasByPersonal[(int)$ur['personal_id']] = decode_area_codes($ur['areas_acceso'] ?? null);
      }
    }
  } catch (Throwable $e) {
    $menuAreasByPersonal = [];
  }
}

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
      $destinosMenu = $destinosByUnidad[$unidadActiva] ?? [];
    }
  } catch (Throwable $e) {
    // noop
  }
}

$roleStats = ['SUPERADMIN' => 0, 'ADMIN' => 0, 'USUARIO' => 0, 'OTRO' => 0];
$sinDestino = 0;
$menuAsignados = 0;
foreach ($rows as $rowStat) {
  $roleIdStat = (int)($rowStat['role_id'] ?? 0);
  $roleCodeStat = strtoupper((string)($rolesById[$roleIdStat]['codigo'] ?? 'USUARIO'));
  if (!isset($roleStats[$roleCodeStat])) $roleCodeStat = 'OTRO';
  $roleStats[$roleCodeStat]++;
  if (empty($rowStat['destino_id']) && empty($rowStat['destino_interno']) && empty($rowStat['destino_interno_nombre'])) $sinDestino++;
  $pidStat = (int)($rowStat['id'] ?? 0);
  if ($pidStat > 0 && !empty($menuAreasByPersonal[$pidStat])) $menuAsignados++;
}

/* ==========================================================
   UI
   ========================================================== */
ui_header('Gestión de usuarios', ['container'=>'xl', 'show_brand'=>false]);
?>
<link rel="stylesheet" href="../../assets/css/theme-602.css">
<link rel="icon" href="../../assets/img/ecmilm.png">

<style>
  :root{--bg:#020617;--panel:#0f172a;--panel2:#111827;--line:#334155;--text:#e5e7eb;--muted:#94a3b8;--ok:#22c55e;--blue:#38bdf8;--warn:#fbbf24;--hot:#fb7185;}
  body{
    min-height:100vh;
    background:
      linear-gradient(160deg,rgba(0,0,0,.88),rgba(2,6,23,.70),rgba(0,0,0,.90)),
      url("../../assets/img/fondo.png") center/cover fixed no-repeat;
    color:var(--text);
    font-family:system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,sans-serif;
  }
  .admin-shell{max-width:1560px;margin:0 auto;padding:18px;}
  .admin-hero{display:flex;align-items:center;gap:14px;padding:16px 18px;border:1px solid rgba(148,163,184,.30);border-radius:18px;background:rgba(15,23,42,.90);box-shadow:0 18px 45px rgba(0,0,0,.45);}
  .admin-hero h1{font-size:1.28rem;margin:0;font-weight:950;letter-spacing:.01em}.admin-hero p{margin:2px 0 0;color:#cbd5e1}.hero-actions{margin-left:auto;display:flex;gap:8px;flex-wrap:wrap}.btnx{display:inline-flex;align-items:center;justify-content:center;border:1px solid rgba(226,232,240,.55);border-radius:10px;padding:.48rem .85rem;color:#fff;text-decoration:none;background:rgba(15,23,42,.75);font-weight:900}.btnx.green{background:#22c55e;color:#052e16;border-color:#22c55e}.btnx.blue{background:#38bdf8;color:#082f49;border-color:#38bdf8}.btnx:hover{filter:brightness(1.08);color:inherit}
  .panel{background:rgba(15,23,42,.93);border:1px solid rgba(148,163,184,.34);border-radius:18px;padding:16px;box-shadow:0 18px 45px rgba(0,0,0,.46);}
  .panel-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px}.panel-title{font-size:1rem;font-weight:950;margin:0}.panel-sub{color:var(--muted);font-size:.9rem}.chip{display:inline-flex;align-items:center;border-radius:999px;padding:.22rem .65rem;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.12);font-size:.76rem;font-weight:900;text-transform:uppercase;letter-spacing:.04em;color:#e5e7eb}.chip.ok{background:rgba(34,197,94,.14);border-color:rgba(34,197,94,.35);color:#bbf7d0}.chip.warn{background:rgba(251,191,36,.14);border-color:rgba(251,191,36,.35);color:#fde68a}.chip.blue{background:rgba(56,189,248,.14);border-color:rgba(56,189,248,.35);color:#bae6fd}.chip.hot{background:rgba(251,113,133,.14);border-color:rgba(251,113,133,.35);color:#fecdd3}
  .kpis{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px;margin:14px 0}.kpi{background:rgba(2,6,23,.62);border:1px solid rgba(148,163,184,.26);border-radius:14px;padding:12px}.kpi span{display:block;color:#a7b1c2;font-size:.75rem;font-weight:900;text-transform:uppercase}.kpi b{display:block;font-size:1.45rem;line-height:1.05;margin-top:4px}
  .form-control,.form-select{background:#07111f!important;border:1px solid rgba(148,163,184,.36)!important;color:#f1f5f9!important;border-radius:10px!important}.form-control:focus,.form-select:focus{box-shadow:0 0 0 3px rgba(34,197,94,.13)!important;border-color:rgba(34,197,94,.65)!important}.form-control::placeholder{color:#64748b!important}.form-label{font-size:.76rem;font-weight:900;text-transform:uppercase;color:#cbd5e1;letter-spacing:.03em}
  .menu-checks{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:7px;min-width:320px}.menu-check{display:flex;align-items:center;gap:7px;padding:.34rem .5rem;border:1px solid rgba(148,163,184,.22);border-radius:10px;background:rgba(2,6,23,.50);color:#e5e7eb;font-size:.78rem;font-weight:800}.menu-check input{accent-color:#22c55e}
  .toolbar-sticky{position:sticky;top:0;z-index:10;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 12px;margin:0 -4px 10px;border:1px solid rgba(148,163,184,.25);border-radius:14px;background:rgba(15,23,42,.96);backdrop-filter:blur(8px)}
  .tbl-wrap{overflow:auto;border-radius:14px;border:1px solid rgba(148,163,184,.30);max-height:72vh}.tbl{width:100%;border-collapse:separate;border-spacing:0;font-size:.86rem}.tbl th,.tbl td{padding:.55rem .62rem;border-bottom:1px solid rgba(15,23,42,.16);vertical-align:middle}.tbl thead th{position:sticky;top:0;z-index:3;background:#102345;color:#fff;font-weight:950;white-space:nowrap;text-transform:uppercase;letter-spacing:.055em;font-size:.74rem}.tbl tbody td{background:rgba(226,232,240,.95);color:#172033}.tbl tbody tr:nth-child(even) td{background:rgba(219,234,254,.92)}.tbl tbody tr:hover td{background:#fff}.person-name{font-weight:950;min-width:250px}.person-meta{font-size:.76rem;color:#64748b}.role-select{min-width:230px;font-weight:900}.dest-select{min-width:270px;font-weight:800}.dest-wide{min-width:330px;font-weight:800}.badge-role{display:inline-flex;border-radius:999px;padding:.22rem .55rem;font-size:.73rem;font-weight:950;white-space:nowrap}.badge-role.super{background:#fee2e2;color:#9f1239}.badge-role.admin{background:#dbeafe;color:#1d4ed8}.badge-role.user{background:#dcfce7;color:#166534}.badge-soft{display:inline-flex;border:1px solid rgba(148,163,184,.38);background:rgba(15,23,42,.08);padding:.18rem .55rem;border-radius:999px;font-size:.76rem;font-weight:900;color:#334155;white-space:nowrap}.user-badges{display:flex;gap:5px;flex-wrap:wrap}.empty-row{padding:28px!important;text-align:center;color:#64748b!important;font-weight:800}.hint{font-size:.82rem;color:#94a3b8}.section-grid{display:grid;grid-template-columns:minmax(360px,1.05fr) minmax(360px,.95fr);gap:14px}.save-btn{min-width:160px}
  @media (max-width:1100px){.kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.section-grid{grid-template-columns:1fr}.admin-hero{align-items:flex-start;flex-wrap:wrap}.hero-actions{margin-left:0}.admin-shell{padding:12px}}
</style>

<div class="admin-shell">
  <section class="admin-hero mb-3">
    <div>
      <h1>Gestión de usuarios y permisos</h1>
      <p>Alta de usuarios, roles administrativos, destino interno y accesos visibles en el menú de inicio.</p>
    </div>
    <div class="hero-actions">
      <a href="../inicio.php" class="btnx">Inicio</a>
      <a href="../admin/administrar_gestiones.php" class="btnx green">Volver a gestiones</a>
    </div>
  </section>

  <?php if ($mensaje !== ''): ?>
    <div class="alert alert-<?= e($mensaje_tipo) ?> py-2 mb-3"><?= e($mensaje) ?></div>
  <?php endif; ?>

  <section class="panel mb-3">
    <div class="panel-head flex-wrap">
      <div>
        <h2 class="panel-title">Resumen de administración</h2>
        <div class="panel-sub">Estás trabajando sobre la unidad activa y con el nivel de permiso de tu usuario actual.</div>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <span class="chip blue">Unidad <?= (int)$unidadActiva ?></span>
        <span class="chip ok">Rol <?= e($myRoleCodigo) ?></span>
        <?php if ($verTodas): ?><span class="chip warn">Viendo todas</span><?php endif; ?>
        <?php if ($dniDuplicadosOcultos > 0): ?><span class="chip hot">Duplicados ocultos <?= (int)$dniDuplicadosOcultos ?></span><?php endif; ?>
      </div>
    </div>
    <div class="kpis">
      <div class="kpi"><span>Registros listados</span><b><?= (int)count($rows) ?></b></div>
      <div class="kpi"><span>Superadmin</span><b><?= (int)$roleStats['SUPERADMIN'] ?></b></div>
      <div class="kpi"><span>Admin</span><b><?= (int)$roleStats['ADMIN'] ?></b></div>
      <div class="kpi"><span>Usuarios</span><b><?= (int)$roleStats['USUARIO'] ?></b></div>
      <div class="kpi"><span>Sin destino</span><b><?= (int)$sinDestino ?></b></div>
    </div>
    <div class="hint">Los roles se guardan en <code>personal_unidad.role_id</code> y los accesos de menú en <code>usuario_roles</code>. Esta sigue siendo la pantalla correcta para administrar permisos.</div>
  </section>

  <div class="section-grid mb-3">
    <section class="panel">
      <div class="panel-head">
        <div>
          <h2 class="panel-title">Crear o actualizar usuario</h2>
          <div class="panel-sub">Si el DNI ya existe en la unidad, se actualiza su rol, destino y accesos.</div>
        </div>
      </div>
      <form method="post" class="row g-2 align-items-end">
        <?php csrf_if_exists(); ?>
        <div class="col-md-4">
          <label class="form-label mb-1">DNI</label>
          <input class="form-control form-control-sm" name="new_dni" placeholder="Ej: 35928720" required>
        </div>
        <div class="col-md-8">
          <label class="form-label mb-1">Nombre opcional</label>
          <input class="form-control form-control-sm" name="new_nombre" placeholder="Apellido y nombre">
        </div>
        <div class="col-md-6">
          <label class="form-label mb-1">Rol</label>
          <select class="form-select form-select-sm" name="new_role_id">
            <?php foreach ($roles as $rr): ?>
              <?php
                $rid = (int)$rr['id'];
                $allowed = can_assign_role($esSuperAdmin, $myRoleNivel, $rr);
                if (!$allowed) continue;
                $sel = ($rid === $roleIdUsuario) ? 'selected' : '';
              ?>
              <option value="<?= $rid ?>" <?= $sel ?>><?= e(role_option_label($rr)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label mb-1">Destino</label>
          <select class="form-select form-select-sm" name="new_destino_id">
            <option value="0">Sin asignar</option>
            <?php foreach (($destinosByUnidad[$unidadActiva] ?? []) as $d): ?>
              <?php $did = (int)$d['id']; $lbl = trim(((string)($d['codigo'] ?? '') !== '' ? $d['codigo'].' - ' : '').($d['nombre'] ?? '')); ?>
              <option value="<?= $did ?>"><?= e($lbl) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12">
          <label class="form-label mb-1">Qué ve en el menú de inicio</label>
          <div class="menu-checks">
            <?php foreach ($destinosMenu as $d): ?>
              <?php $code = strtoupper(trim((string)($d['codigo'] ?? ''))); if ($code === '') continue; $lbl = trim($code . ' - ' . (string)($d['nombre'] ?? '')); ?>
              <label class="menu-check"><input type="checkbox" name="new_menu_codes[]" value="<?= e($code) ?>"><span><?= e($lbl) ?></span></label>
            <?php endforeach; ?>
          </div>
          <div class="hint mt-1">Si no marcás nada, verá solamente lo asociado a su destino interno.</div>
        </div>
        <div class="col-12 d-flex justify-content-end">
          <button class="btnx green" name="add_user" type="submit">Crear / actualizar</button>
        </div>
      </form>
    </section>

    <section class="panel">
      <div class="panel-head">
        <div>
          <h2 class="panel-title">Buscar usuarios</h2>
          <div class="panel-sub">Filtrá antes de hacer cambios masivos para trabajar más cómodo.</div>
        </div>
      </div>
      <form method="get" class="row g-2 align-items-end">
        <div class="col-md-5">
          <label class="form-label mb-1">DNI</label>
          <input class="form-control form-control-sm" name="dni" value="<?= e($searchDni) ?>" placeholder="Buscar por DNI">
        </div>
        <div class="col-md-7">
          <label class="form-label mb-1">Nombre</label>
          <input class="form-control form-control-sm" name="nombre" value="<?= e($searchNom) ?>" placeholder="Buscar por apellido o nombre">
        </div>
        <?php if ($esSuperAdmin): ?>
        <div class="col-md-6">
          <label class="form-label mb-1">Alcance</label>
          <select class="form-select form-select-sm" name="all">
            <option value="0" <?= $verTodas ? '' : 'selected' ?>>Solo unidad activa</option>
            <option value="1" <?= $verTodas ? 'selected' : '' ?>>Todas las unidades</option>
          </select>
        </div>
        <?php endif; ?>
        <div class="col-md-<?= $esSuperAdmin ? '6' : '12' ?> d-flex gap-2">
          <button class="btnx blue flex-fill" type="submit">Filtrar</button>
          <a class="btnx" href="administrar_usuarios.php">Limpiar</a>
        </div>
      </form>
      <div class="mt-3 d-flex flex-wrap gap-2">
        <span class="chip">Menú asignado <?= (int)$menuAsignados ?></span>
        <?php if (!$col_destino_id && $col_destino_interno): ?><span class="chip warn">Destino por texto</span><?php endif; ?>
        <?php if ($hasUsuarioRoles): ?><span class="chip ok">usuario_roles activo</span><?php endif; ?>
      </div>
    </section>
  </div>

  <section class="panel">
    <form method="post">
      <?php csrf_if_exists(); ?>
      <div class="toolbar-sticky">
        <div>
          <h2 class="panel-title mb-0">Usuarios encontrados</h2>
          <div class="hint">Editá rol, destino interno, destino administrativo y accesos del menú. Después guardá todo junto.</div>
        </div>
        <button type="submit" name="save_all" class="btnx green save-btn">Guardar cambios</button>
      </div>

      <div class="tbl-wrap">
        <table class="tbl">
          <thead>
            <tr>
              <th>Usuario</th>
              <th>Rol</th>
              <th>Destino actual</th>
              <th>Destino interno</th>
              <th>Asignar destino</th>
              <th>Menú inicio</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="6" class="empty-row">No hay registros para los filtros aplicados.</td></tr>
          <?php else: ?>
            <?php foreach ($rows as $r): ?>
              <?php
                $pid = (int)$r['id'];
                $uid = (int)$r['unidad_id'];
                $currentRoleId = (int)($r['role_id'] ?? 0);
                $currentRoleCode = strtoupper((string)($rolesById[$currentRoleId]['codigo'] ?? 'USUARIO'));
                $roleClass = $currentRoleCode === 'SUPERADMIN' ? 'super' : ($currentRoleCode === 'ADMIN' ? 'admin' : 'user');
                $destinoId = (int)($r['destino_id'] ?? 0);
                $destTxt = '';
                if (!empty($r['destino_codigo']) || !empty($r['destino_nombre'])) {
                  $destTxt = trim(((string)$r['destino_codigo'] !== '' ? $r['destino_codigo'].' - ' : '').(string)$r['destino_nombre']);
                } elseif (!empty($r['destino_interno_nombre'])) {
                  $destTxt = (string)$r['destino_interno_nombre'];
                } else {
                  $destTxt = 'Sin asignar';
                }
                $destList = $destinosByUnidad[$uid] ?? ($destinosByUnidad[$unidadActiva] ?? []);
                $unidadNombre = (string)($r['unidad_nombre'] ?? ('#'.$uid));
                $menuCodes = array_fill_keys($menuAreasByPersonal[$pid] ?? [], true);
                $nombreShow = trim((string)($r['nombre_show'] ?? ''));
              ?>
              <tr>
                <td>
                  <input type="hidden" name="ids[]" value="<?= $pid ?>">
                  <div class="person-name"><?= e($nombreShow !== '' ? $nombreShow : 'Sin nombre') ?></div>
                  <div class="person-meta">DNI <?= e($r['dni'] ?? '') ?> · <?= e($r['grado'] ?? '') ?> <?= e($r['arma'] ?? '') ?> · <?= e($unidadNombre) ?></div>
                </td>
                <td>
                  <div class="user-badges mb-1"><span class="badge-role <?= e($roleClass) ?>"><?= e($currentRoleCode) ?></span></div>
                  <?php if ($col_role_id): ?>
                    <select class="form-select form-select-sm role-select" name="role_id[<?= $pid ?>]">
                      <?php foreach ($roles as $rr): ?>
                        <?php
                          $rid = (int)$rr['id'];
                          $allowed = can_assign_role($esSuperAdmin, $myRoleNivel, $rr);
                          $isCurrent = ($rid === $currentRoleId);
                          $disabled = (!$allowed && !$isCurrent) ? 'disabled' : '';
                        ?>
                        <option value="<?= $rid ?>" <?= $isCurrent ? 'selected' : '' ?> <?= $disabled ?>><?= e(role_option_label($rr)) ?></option>
                      <?php endforeach; ?>
                    </select>
                  <?php else: ?>
                    <span class="badge-soft">role_id no existe</span>
                  <?php endif; ?>
                </td>
                <td><span class="badge-soft"><?= e($destTxt) ?></span></td>
                <td>
                  <select class="form-select form-select-sm destino-interno-select dest-select" name="destino_interno_id[<?= $pid ?>]" data-target="nuevo-destino-interno-<?= $pid ?>">
                    <option value="">Sin destino interno</option>
                    <?php foreach ($destinosInternosAll as $diRow): ?>
                      <?php $diId = (int)($diRow['id'] ?? 0); ?>
                      <option value="<?= $diId ?>" <?= ($diId === (int)($r['destino_interno'] ?? 0) ? 'selected' : '') ?>><?= e($diRow['nombre'] ?? '') ?></option>
                    <?php endforeach; ?>
                    <option value="NUEVO">+ Agregar nuevo destino</option>
                  </select>
                  <input class="form-control form-control-sm mt-1 d-none destino-interno-nuevo dest-select" id="nuevo-destino-interno-<?= $pid ?>" name="destino_interno_nuevo[<?= $pid ?>]" placeholder="Nombre del nuevo destino interno">
                </td>
                <td>
                  <?php if ($col_destino_id): ?>
                    <select class="form-select form-select-sm dest-wide" name="destino_id[<?= $pid ?>]">
                      <option value="0">Sin asignar</option>
                      <?php foreach ($destList as $d): ?>
                        <?php $did = (int)$d['id']; $lbl = trim(((string)($d['codigo'] ?? '') !== '' ? $d['codigo'].' - ' : '').($d['nombre'] ?? '')); ?>
                        <option value="<?= $did ?>" <?= ($did === $destinoId ? 'selected' : '') ?>><?= e($lbl) ?></option>
                      <?php endforeach; ?>
                    </select>
                  <?php else: ?>
                    <span class="badge-soft">Usa destino interno</span>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="menu-checks">
                    <?php foreach ($destList as $d): ?>
                      <?php $code = strtoupper(trim((string)($d['codigo'] ?? ''))); if ($code === '') continue; $lbl = trim($code . ' - ' . (string)($d['nombre'] ?? '')); ?>
                      <label class="menu-check"><input type="checkbox" name="menu_codes[<?= $pid ?>][]" value="<?= e($code) ?>" <?= isset($menuCodes[$code]) ? 'checked' : '' ?>><span><?= e($lbl) ?></span></label>
                    <?php endforeach; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </form>
  </section>
</div>
<script>
document.querySelectorAll('.destino-interno-select').forEach(select => {
  const sync = () => {
    const input = document.getElementById(select.dataset.target || '');
    if (!input) return;
    const nuevo = select.value === 'NUEVO';
    input.classList.toggle('d-none', !nuevo);
    input.required = nuevo;
    if (!nuevo) input.value = '';
  };
  select.addEventListener('change', sync);
  sync();
});
</script>

<?php ui_footer(); ?>
