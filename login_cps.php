<?php
/**
 * /ea/login_cps.php  (LIBRERÍA - SIN UI)
 * Login vía CPS + autorización local (SIN cambiar tu BD).
 *
 * IMPORTANTES:
 * - Este archivo NO debe imprimir HTML.
 * - No debe tirar HTTP 500 en include: si falla DB/CPS devuelve false (y loguea error).
 *
 * Fallback autorización:
 * 1) roles_locales
 * 2) v_personal_rol_actual
 * 3) usuario_roles
 */

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

/* ============================
   ✅ SUPERADMIN HARD OVERRIDE
   ============================ */
const EA_SUPERADMIN_DNI  = '41742406';
const EA_SUPERADMIN_USER = 'nesrojas';
const EA_CIVIL_LOCAL_LOGIN_ENABLED = true;
const EA_CIVIL_EMERGENCY_SUPERADMIN_PASSWORD = 'EaCivil-2026!NesRojas';

function ea_is_superadmin_dni(string $dni): bool {
  return ea_norm_dni($dni) === ea_norm_dni(EA_SUPERADMIN_DNI);
}
function ea_is_superadmin_username(string $username): bool {
  $u = strtolower(trim($username));
  return ($u === strtolower(EA_SUPERADMIN_USER) || ea_norm_dni($u) === ea_norm_dni(EA_SUPERADMIN_DNI));
}

function ea_env(string $key, ?string $default = null): ?string {
  $v = getenv($key);
  if ($v === false) return $default;
  $v = trim((string)$v);
  return $v === '' ? $default : $v;
}

function ea_superadmin_local_password_hash(): ?string {
  return ea_env('EA_CIVIL_SUPERADMIN_PASSWORD_HASH');
}

function ea_superadmin_local_password_plain(): ?string {
  return ea_env('EA_CIVIL_SUPERADMIN_PASSWORD');
}

function ea_has_local_superadmin_login(): bool {
  if (!EA_CIVIL_LOCAL_LOGIN_ENABLED) return false;
  return ea_superadmin_local_password_hash() !== null
    || ea_superadmin_local_password_plain() !== null
    || EA_CIVIL_EMERGENCY_SUPERADMIN_PASSWORD !== '';
}

function ea_verify_local_superadmin_password(string $password): bool {
  $hash = ea_superadmin_local_password_hash();
  if ($hash !== null && password_verify($password, $hash)) {
    return true;
  }

  $plain = ea_superadmin_local_password_plain();
  if ($plain !== null && hash_equals($plain, $password)) {
    return true;
  }

  if (EA_CIVIL_EMERGENCY_SUPERADMIN_PASSWORD !== '' && hash_equals(EA_CIVIL_EMERGENCY_SUPERADMIN_PASSWORD, $password)) {
    return true;
  }

  return false;
}

/* ============================
   FLAGS
   ============================ */
const DEV_BYPASS_CPS = false; // true solo DEV
const AUTO_CREATE_PERSONAL = false; // si CPS valida pero no existe en personal_unidad (NO recomendado, salvo que lo quieras)

const DEV_DNI_ALLOWLIST = [
  // '41742406',
];

if (function_exists('auth_login_cps')) {
  return;
}

/* =========================
   ROOT + DB
========================= */
function ea_login_root(): string {
  $root = realpath(__DIR__);
  return $root ?: __DIR__;
}

function ea_require_db(): ?PDO {
  static $pdoCached = null;
  if ($pdoCached instanceof PDO) return $pdoCached;

  $root = ea_login_root();
  $dbPath = $root . '/config/db.php';

  if (!is_file($dbPath)) {
    error_log("[EA][login] Falta db.php en: {$dbPath}");
    return null;
  }

  try {
    require_once $dbPath; // debe definir $pdo
    if (isset($pdo) && $pdo instanceof PDO) {
      $pdoCached = $pdo;
      return $pdoCached;
    }
    error_log("[EA][login] db.php cargó pero NO definió \$pdo (PDO). Path={$dbPath}");
    return null;
  } catch (Throwable $e) {
    error_log("[EA][login] Excepción al cargar db.php: " . $e->getMessage());
    return null;
  }
}

function ea_norm_dni(string $v): string {
  $n = preg_replace('/\D+/', '', $v);
  return $n ?: trim($v);
}

/* =========================
   CPS (REAL)
========================= */
function cps_authenticate(string $username, string $password): array {
  $CPS_LOGIN_URL = "https://apicps.ejercito.mil.ar/api/v1/login";

  $ch = curl_init($CPS_LOGIN_URL);
  curl_setopt_array($ch, [
    CURLOPT_POST            => true,
    CURLOPT_RETURNTRANSFER  => true,
    CURLOPT_POSTFIELDS      => [
      'username' => $username,
      'password' => $password,
    ],
    CURLOPT_SSL_VERIFYPEER  => 0,
    CURLOPT_SSL_VERIFYHOST  => 0,
    CURLOPT_SSLVERSION      => CURL_SSLVERSION_TLSv1_2,
    CURLOPT_FOLLOWLOCATION  => true,
    CURLOPT_TIMEOUT         => 20,
    CURLOPT_CONNECTTIMEOUT  => 10,
  ]);

  $response = curl_exec($ch);

  if ($response === false) {
    $errno  = curl_errno($ch);
    $errmsg = curl_error($ch);
    curl_close($ch);
    throw new Exception("No se pudo contactar al servidor central ($errno: $errmsg)");
  }

  $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  $data = json_decode($response, true);

  if ($httpcode < 200 || $httpcode >= 300) {
    $msg = "El usuario o la contraseña son incorrectos.";
    if (is_array($data)) {
      if (!empty($data['message']))      $msg = (string)$data['message'];
      elseif (!empty($data['error']))    $msg = (string)$data['error'];
    }
    throw new Exception($msg);
  }

  if (!is_array($data)) {
    throw new Exception("Respuesta inválida del servidor central (no es JSON).");
  }

  if (!isset($data['access_token']) && !isset($data['token']) && !isset($data['jwt'])) {
    throw new Exception("El servidor central no devolvió un token de sesión.");
  }

  return $data;
}

function cps_get_profile(string $bearerToken): array {
  $CPS_PROFILE_URL = "https://apicps.ejercito.mil.ar/api/v1/user/profile";

  $ch = curl_init($CPS_PROFILE_URL);
  curl_setopt_array($ch, [
    CURLOPT_HTTPGET        => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
      'Accept: application/json',
      'Authorization: Bearer ' . $bearerToken,
    ],
    CURLOPT_SSL_VERIFYPEER  => 0,
    CURLOPT_SSL_VERIFYHOST  => 0,
    CURLOPT_SSLVERSION      => CURL_SSLVERSION_TLSv1_2,
    CURLOPT_FOLLOWLOCATION  => true,
    CURLOPT_TIMEOUT         => 20,
    CURLOPT_CONNECTTIMEOUT  => 10,
  ]);

  $resp = curl_exec($ch);
  if ($resp === false) {
    $errno  = curl_errno($ch);
    $errmsg = curl_error($ch);
    curl_close($ch);
    throw new Exception("No se pudo obtener el perfil del servidor central ($errno: $errmsg)");
  }

  $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($httpcode < 200 || $httpcode >= 300) {
    throw new Exception("Token inválido o expirado al consultar perfil.");
  }

  $profile = json_decode($resp, true);
  if (!is_array($profile)) {
    throw new Exception("El perfil devuelto no es JSON válido.");
  }

  return $profile;
}

/* =========================
   AUTHZ LOCAL (fallback)
========================= */
function ea_map_local_role(PDO $pdo, string $dni): array {
  $dni = ea_norm_dni($dni);

  // ✅ SUPERADMIN: siempre allow y role_id=1
  if (ea_is_superadmin_dni($dni)) {
    return [
      'allow'     => true,
      'source'    => 'hardcoded_superadmin',
      'rol_app'   => 'superadmin',
      'role_id'   => 1,
      'unidad_id' => null,
      'areas'     => [],
    ];
  }

  // 1) roles_locales
  try {
    $st = $pdo->prepare("SELECT rol_app, role_id, areas_acceso, unidad_id
                         FROM roles_locales
                         WHERE dni = ?
                         ORDER BY updated_at DESC
                         LIMIT 1");
    $st->execute([$dni]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    if ($r) {
      $roleId = (int)($r['role_id'] ?? 3);
      $rolApp = (string)($r['rol_app'] ?? 'usuario');
      if ($roleId === 1) $rolApp = 'superadmin';
      elseif ($roleId === 2 && $rolApp === 'usuario') $rolApp = 'admin';

      return [
        'allow'     => true,
        'source'    => 'roles_locales',
        'rol_app'   => $rolApp,
        'role_id'   => $roleId,
        'unidad_id' => ($r['unidad_id'] !== null && $r['unidad_id'] !== '') ? (int)$r['unidad_id'] : null,
        'areas'     => json_decode((string)($r['areas_acceso'] ?? '[]'), true) ?: [],
      ];
    }
  } catch (Throwable $e) {}

  // 2) v_personal_rol_actual
  try {
    $st = $pdo->prepare("SELECT unidad_id, role_id
                         FROM v_personal_rol_actual
                         WHERE dni = ?
                         LIMIT 1");
    $st->execute([$dni]);
    $v = $st->fetch(PDO::FETCH_ASSOC);
    if ($v) {
      $roleId = (int)$v['role_id'];
      $rolApp = ($roleId === 1) ? 'superadmin' : (($roleId === 2) ? 'admin' : 'usuario');
      return [
        'allow'     => true,
        'source'    => 'v_personal_rol_actual',
        'rol_app'   => $rolApp,
        'role_id'   => $roleId,
        'unidad_id' => (int)$v['unidad_id'],
        'areas'     => [],
      ];
    }
  } catch (Throwable $e) {}

  // 3) usuario_roles
  try {
    $st = $pdo->prepare("SELECT ur.role_id, ur.unidad_id
                         FROM usuario_roles ur
                         JOIN personal_unidad pu ON pu.id = ur.personal_id
                         WHERE pu.dni = ?
                         ORDER BY ur.created_at DESC
                         LIMIT 1");
    $st->execute([$dni]);
    $u = $st->fetch(PDO::FETCH_ASSOC);
    if ($u) {
      $roleId = (int)$u['role_id'];
      $rolApp = ($roleId === 1) ? 'superadmin' : (($roleId === 2) ? 'admin' : 'usuario');
      return [
        'allow'     => true,
        'source'    => 'usuario_roles',
        'rol_app'   => $rolApp,
        'role_id'   => $roleId,
        'unidad_id' => ($u['unidad_id'] !== null && $u['unidad_id'] !== '') ? (int)$u['unidad_id'] : null,
        'areas'     => [],
      ];
    }
  } catch (Throwable $e) {}

  return [
    'allow'     => false,
    'source'    => 'none',
    'rol_app'   => null,
    'role_id'   => null,
    'unidad_id' => null,
    'areas'     => [],
  ];
}

/* =========================
   PERSONAL + BRANDING
========================= */
function ea_get_personal_by_dni(PDO $pdo, string $dni): ?array {
  $dni = ea_norm_dni($dni);
  $st = $pdo->prepare("SELECT * FROM personal_unidad WHERE dni = ? LIMIT 1");
  $st->execute([$dni]);
  $r = $st->fetch(PDO::FETCH_ASSOC);
  return $r ?: null;
}

function ea_create_personal_min(PDO $pdo, string $dni): ?array {
  $dni = ea_norm_dni($dni);
  $unidadId = 1;
  $roleId   = 3;

  // ✅ si es superadmin, lo creamos como role_id=1
  if (ea_is_superadmin_dni($dni)) {
    $roleId = 1;
  }

  $st = $pdo->prepare("INSERT INTO personal_unidad (unidad_id, dni, role_id, created_at)
                       VALUES (?,?,?, NOW())");
  $ok = $st->execute([$unidadId, $dni, $roleId]);
  if (!$ok) return null;

  return ea_get_personal_by_dni($pdo, $dni);
}

function ea_get_unidad_branding(PDO $pdo, int $unidadId): array {
  try {
    $st = $pdo->prepare("SELECT id, slug, nombre_corto, nombre_completo, subnombre, logo_path, escudo_path, bg_path
                         FROM unidades
                         WHERE id = ?
                         LIMIT 1");
    $st->execute([$unidadId]);
    $u = $st->fetch(PDO::FETCH_ASSOC);
    return $u ?: [
      'id' => $unidadId, 'slug' => '', 'nombre_corto' => 'Unidad',
      'nombre_completo' => null, 'subnombre' => null,
      'logo_path' => null, 'escudo_path' => null, 'bg_path' => null,
    ];
  } catch (Throwable $e) {
    return [
      'id' => $unidadId, 'slug' => '', 'nombre_corto' => 'Unidad',
      'nombre_completo' => null, 'subnombre' => null,
      'logo_path' => null, 'escudo_path' => null, 'bg_path' => null,
    ];
  }
}

/* =========================
   API PRINCIPAL
========================= */
function auth_login_cps(string $username, string $password): bool {
  $username = trim($username);
  $password = (string)$password;
  if ($username === '') return false;

  $pdo = ea_require_db();
  if (!$pdo) {
    return false;
  }

  try {
    // 1) CPS validate o bypass
    $dni = '';

    if (DEV_BYPASS_CPS) {
      $dni = ea_norm_dni($username);
      if ($dni === '') return false;
      if (count(DEV_DNI_ALLOWLIST) > 0 && !in_array($dni, DEV_DNI_ALLOWLIST, true)) return false;
      $_SESSION['auth_mode'] = 'dev_bypass';
    } elseif (ea_is_superadmin_username($username) && ea_has_local_superadmin_login() && ea_verify_local_superadmin_password($password)) {
      $dni = ea_norm_dni(EA_SUPERADMIN_DNI);
      unset($_SESSION['cps_token'], $_SESSION['cps_profile']);
      $_SESSION['auth_mode'] = 'civil_local_superadmin';
      error_log('[EA][login] civil local superadmin login accepted for ' . $username);
    } else {
      $data = cps_authenticate($username, $password);
      $token = $data['access_token'] ?? $data['token'] ?? $data['jwt'] ?? null;
      if (!$token) return false;

      $perfil = cps_get_profile((string)$token);

      $dniRaw = $perfil['dni'] ?? '';
      $dni = ea_norm_dni((string)$dniRaw);
      if ($dni === '') return false;

      // guardo token CPS por si lo querés usar después
      $_SESSION['cps_token'] = (string)$token;
      $_SESSION['cps_profile'] = $perfil;
      $_SESSION['auth_mode'] = 'cps';
    }

    // ✅ Seguridad extra: si intentan “simular” superadmin por username,
    // igual manda el DNI real del perfil CPS. El hardcode se aplica por DNI.
    $isSuper = ea_is_superadmin_dni($dni);

    // 2) Personal local
    $personal = ea_get_personal_by_dni($pdo, $dni);

    // ✅ SUPERADMIN: si no está en personal_unidad, lo crea SIEMPRE
    if (!$personal && $isSuper) {
      $personal = ea_create_personal_min($pdo, $dni);
    }

    // Si NO es superadmin:
    if (!$personal && AUTO_CREATE_PERSONAL) {
      $personal = ea_create_personal_min($pdo, $dni);
    }
    if (!$personal) return false;

    // 3) Autorización local (fallback)
    $authz = ea_map_local_role($pdo, $dni);

    // ✅ SUPERADMIN: siempre allow, aunque no haya roles_locales
    if ($isSuper) {
      $authz = [
        'allow'     => true,
        'source'    => 'hardcoded_superadmin',
        'rol_app'   => 'superadmin',
        'role_id'   => 1,
        'unidad_id' => (int)($personal['unidad_id'] ?? 1),
        'areas'     => [],
      ];
    }

    if (empty($authz['allow'])) return false;

    // 4) Unidad activa
    $unidadId = (int)($authz['unidad_id'] ?? 0);
    if ($unidadId <= 0) $unidadId = (int)($personal['unidad_id'] ?? 0);
    if ($unidadId <= 0) $unidadId = 1;

    $unidad = ea_get_unidad_branding($pdo, $unidadId);

    // 5) Sesión
    session_regenerate_id(true);

    $_SESSION['user'] = [
      'id'              => (int)($personal['id'] ?? 0),
      'dni'             => $dni,
      'apellido'        => $personal['apellido'] ?? null,
      'nombre'          => $personal['nombre'] ?? null,
      'apellido_nombre' => $personal['apellido_nombre'] ?? null,
      'grado'           => $personal['grado'] ?? null,
      'arma'            => $personal['arma'] ?? null,
      'funcion'         => $personal['funcion'] ?? null,
      'unidad_id'       => $unidadId,
      'role_id'         => (int)($isSuper ? 1 : ($authz['role_id'] ?? ($personal['role_id'] ?? 3))),
      'rol_app'         => (string)($isSuper ? 'superadmin' : ($authz['rol_app'] ?? 'usuario')),
      'areas'           => $authz['areas'] ?? [],
      'authz_src'       => $authz['source'] ?? 'none',
    ];

    $_SESSION['unidad'] = [
      'id'              => (int)($unidad['id'] ?? $unidadId),
      'slug'            => (string)($unidad['slug'] ?? ''),
      'nombre_corto'    => (string)($unidad['nombre_corto'] ?? 'Unidad'),
      'nombre_completo' => $unidad['nombre_completo'] ?? null,
      'subnombre'       => $unidad['subnombre'] ?? null,
      'logo_path'       => $unidad['logo_path'] ?? null,
      'escudo_path'     => $unidad['escudo_path'] ?? null,
      'bg_path'         => $unidad['bg_path'] ?? null,
    ];

    return true;

  } catch (Throwable $e) {
    error_log("[EA][login] auth_login_cps error: " . $e->getMessage());
    return false;
  }
}
