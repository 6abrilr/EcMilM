<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/bootstrap.php';
require_login();
require_once __DIR__ . '/../config/db.php';

function norm_dni(string $dni): string { return preg_replace('/\D+/', '', $dni); }

$user = function_exists('current_user') ? current_user() : ($_SESSION['user'] ?? null);
$dni  = norm_dni((string)($user['dni'] ?? $user['username'] ?? ''));

$unidadId = (string)($_GET['unidad_id'] ?? '');
$unidadId = trim($unidadId);

if ($unidadId === '') {
  header('Location: elegir_inicio.php?denied=1');
  exit;
}

/* ===== Determinar rol del usuario desde BD ===== */
$roleCodigo = 'USUARIO';
try {
  $st = $pdo->prepare("
    SELECT role_codigo
    FROM usuario_roles
    WHERE REPLACE(REPLACE(REPLACE(dni,'.',''),'-',''),' ','') = :dni
    ORDER BY
      CASE role_codigo
        WHEN 'SUPERADMIN' THEN 3
        WHEN 'ADMIN' THEN 2
        ELSE 1
      END DESC,
      created_at DESC,
      id DESC
    LIMIT 1
  ");
  $st->execute([':dni' => $dni]);
  if ($r = $st->fetch(PDO::FETCH_ASSOC)) {
    $roleCodigo = (string)($r['role_codigo'] ?? 'USUARIO');
  }
} catch (Throwable $e) {}

$esSuperAdmin = ($roleCodigo === 'SUPERADMIN');
$esAdmin      = ($roleCodigo === 'ADMIN') || $esSuperAdmin;

/* ===== Si no es admin/superadmin, solo puede entrar a su propia unidad ===== */
$unidadPropia = '';
try {
  $st = $pdo->prepare("
    SELECT unidad_id
    FROM personal_unidad
    WHERE REPLACE(REPLACE(REPLACE(dni,'.',''),'-',''),' ','') = :dni
    LIMIT 1
  ");
  $st->execute([':dni' => $dni]);
  if ($r = $st->fetch(PDO::FETCH_ASSOC)) {
    $unidadPropia = (string)($r['unidad_id'] ?? '');
  }
} catch (Throwable $e) {}

if (!$esAdmin) {
  if ($unidadPropia === '' || $unidadId !== $unidadPropia) {
    header('Location: elegir_inicio.php?denied=1');
    exit;
  }
} else {
  // ADMIN: también lo limitamos a su propia unidad (según tu regla)
  if (!$esSuperAdmin && $unidadPropia !== '' && $unidadId !== $unidadPropia) {
    header('Location: elegir_inicio.php?denied=1');
    exit;
  }
}

/* ===== Validar que exista la unidad ===== */
try {
  $st = $pdo->prepare("SELECT id FROM unidades WHERE id = :id LIMIT 1");
  $st->execute([':id' => $unidadId]);
  if (!$st->fetchColumn()) {
    header('Location: elegir_inicio.php?denied=1');
    exit;
  }
} catch (Throwable $e) {
  header('Location: elegir_inicio.php?denied=1');
  exit;
}

/* ===== Guardar unidad seleccionada en sesión ===== */
$_SESSION['unidad_id'] = $unidadId;

// Si tu app usa esto en otras pantallas:
$_SESSION['user']['unidad_id'] = $unidadId;

header('Location: menu.php');
exit;
