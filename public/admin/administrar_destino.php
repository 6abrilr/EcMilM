<?php
// public/admin/administrar_destino.php — CRUD de destino (solo ADMIN/SUPERADMIN)
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

function destino_column_exists(PDO $pdo, string $column): bool {
  $st = $pdo->prepare("
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'destino'
      AND COLUMN_NAME = :col
  ");
  $st->execute([':col' => $column]);
  return (int)$st->fetchColumn() > 0;
}

function destino_ensure_inicio_columns(PDO $pdo): void {
  if (!destino_column_exists($pdo, 'orden')) {
    $pdo->exec("ALTER TABLE destino ADD COLUMN orden INT NOT NULL DEFAULT 0 AFTER activo");
    $pdo->exec("UPDATE destino SET orden = id WHERE orden = 0");
  }
}

destino_ensure_inicio_columns($pdo);

function inicio_menu_ensure_tables_admin(PDO $pdo): void {
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS inicio_menu_secciones (
      id INT AUTO_INCREMENT PRIMARY KEY,
      unidad_id INT NOT NULL,
      section_key VARCHAR(40) NOT NULL,
      titulo VARCHAR(120) NOT NULL,
      orden INT NOT NULL DEFAULT 0,
      visible TINYINT(1) NOT NULL DEFAULT 1,
      UNIQUE KEY uq_inicio_sec (unidad_id, section_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  ");
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS inicio_menu_items (
      id INT AUTO_INCREMENT PRIMARY KEY,
      unidad_id INT NOT NULL,
      section_key VARCHAR(40) NOT NULL,
      etiqueta VARCHAR(160) NOT NULL,
      href VARCHAR(255) NOT NULL,
      pill VARCHAR(60) NULL,
      orden INT NOT NULL DEFAULT 0,
      visible TINYINT(1) NOT NULL DEFAULT 1,
      admin_only TINYINT(1) NOT NULL DEFAULT 0,
      UNIQUE KEY uq_inicio_item (unidad_id, etiqueta, href)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  ");
}

function inicio_menu_seed_admin(PDO $pdo, int $unidadId): void {
  $sections = [
    ['destinos', 'Destinos / Áreas de trabajo', 10, 1],
    ['educacion', 'Educacion', 20, 1],
    ['utilidades', 'Utilidades', 30, 1],
    ['administracion', 'Administración', 40, 1],
  ];
  $st = $pdo->prepare("
    INSERT INTO inicio_menu_secciones (unidad_id, section_key, titulo, orden, visible)
    VALUES (:uid, :k, :t, :o, :v)
    ON DUPLICATE KEY UPDATE titulo = titulo
  ");
  foreach ($sections as $s) {
    $st->execute([':uid'=>$unidadId, ':k'=>$s[0], ':t'=>$s[1], ':o'=>$s[2], ':v'=>$s[3]]);
  }

  $items = [
    ['educacion', 'Departamento Educacion', 'departamento_educacion/departamento_educacion.php', 'EDUC', 10, 1, 0],
    ['educacion', 'Division Ensenanza', 'division_ensenanza/division_ensenanza.php', 'ENS', 20, 1, 0],
    ['utilidades', 'CHAT', 'CHAT.php', 'Ver', 10, 1, 0],
    ['utilidades', 'Calendario', 'calendario.php?area={AREA}', 'Ver', 20, 1, 0],
    ['utilidades', 'Buscador de documentación', 'documentacion.php', 'DOCUMENTACIÓN', 30, 1, 0],
    ['utilidades', 'Convertir PDF a Word o imágenes', 'editardocumentos.php', 'Herramienta', 40, 1, 0],
    ['utilidades', 'Asistente IA sobre archivos', 'asistente_ia.php', 'Reglamentos', 50, 1, 0],
    ['administracion', 'Panel de Administración', 'admin/administrar_gestiones.php', '{ROLE}', 10, 1, 1],
  ];
  $st = $pdo->prepare("
    INSERT INTO inicio_menu_items (unidad_id, section_key, etiqueta, href, pill, orden, visible, admin_only)
    VALUES (:uid, :sec, :etq, :href, :pill, :orden, :visible, :admin)
    ON DUPLICATE KEY UPDATE etiqueta = etiqueta
  ");
  foreach ($items as $it) {
    $st->execute([
      ':uid'=>$unidadId, ':sec'=>$it[0], ':etq'=>$it[1], ':href'=>$it[2], ':pill'=>$it[3],
      ':orden'=>$it[4], ':visible'=>$it[5], ':admin'=>$it[6],
    ]);
  }
}

inicio_menu_ensure_tables_admin($pdo);
inicio_menu_seed_admin($pdo, (int)$unidadActiva);

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
      $orden  = max(0, (int)($_POST['orden'] ?? 0));

      if ($nombre === '') throw new RuntimeException("El nombre es obligatorio.");
      if ((string)($_POST['ruta'] ?? '') !== '' && $ruta === '') {
        throw new RuntimeException("La ruta es inválida. Usá por ejemplo: operaciones/operaciones.php");
      }

      $st = $pdo->prepare("
        INSERT INTO destino (unidad_id, codigo, nombre, ruta, activo, orden)
        VALUES (:uid, :codigo, :nombre, :ruta, :activo, :orden)
      ");
      $st->execute([
        ':uid'    => $unidadActiva,
        ':codigo' => ($codigo !== '' ? $codigo : null),
        ':nombre' => $nombre,
        ':ruta'   => ($ruta !== '' ? $ruta : null),
        ':activo' => ($activo ? 1 : 0),
        ':orden'  => $orden,
      ]);

      $msgOk = "Destino agregado correctamente.";
    }

    if ($action === 'update') {
      $id     = (int)($_POST['id'] ?? 0);
      $codigo = trim((string)($_POST['codigo'] ?? ''));
      $nombre = trim((string)($_POST['nombre'] ?? ''));
      $ruta   = sanitize_ruta((string)($_POST['ruta'] ?? ''));
      $activo = (int)($_POST['activo'] ?? 1);
      $orden  = max(0, (int)($_POST['orden'] ?? 0));

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
            activo = :activo,
            orden  = :orden
        WHERE id = :id AND unidad_id = :uid
        LIMIT 1
      ");
      $st->execute([
        ':codigo' => ($codigo !== '' ? $codigo : null),
        ':nombre' => $nombre,
        ':ruta'   => ($ruta !== '' ? $ruta : null),
        ':activo' => ($activo ? 1 : 0),
        ':orden'  => $orden,
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

    if ($action === 'update_inicio_sections') {
      $ids = $_POST['section_ids'] ?? [];
      if (!is_array($ids)) $ids = [];
      $titulos = $_POST['section_titulo'] ?? [];
      $ordenes = $_POST['section_orden'] ?? [];
      $visibles = $_POST['section_visible'] ?? [];
      $st = $pdo->prepare("
        UPDATE inicio_menu_secciones
        SET titulo = :titulo, orden = :orden, visible = :visible
        WHERE id = :id AND unidad_id = :uid
        LIMIT 1
      ");
      foreach ($ids as $idRaw) {
        $id = (int)$idRaw;
        if ($id <= 0) continue;
        $st->execute([
          ':titulo' => trim((string)($titulos[$id] ?? '')),
          ':orden' => (int)($ordenes[$id] ?? 0),
          ':visible' => isset($visibles[$id]) ? 1 : 0,
          ':id' => $id,
          ':uid' => $unidadActiva,
        ]);
      }
      $msgOk = "Apartados de Inicio actualizados.";
    }

    if ($action === 'update_inicio_items') {
      $ids = $_POST['item_ids'] ?? [];
      if (!is_array($ids)) $ids = [];
      $section = $_POST['item_section'] ?? [];
      $etiqueta = $_POST['item_etiqueta'] ?? [];
      $href = $_POST['item_href'] ?? [];
      $pill = $_POST['item_pill'] ?? [];
      $orden = $_POST['item_orden'] ?? [];
      $visible = $_POST['item_visible'] ?? [];
      $adminOnly = $_POST['item_admin_only'] ?? [];
      $allowedSections = ['destinos','educacion','utilidades','administracion'];
      $st = $pdo->prepare("
        UPDATE inicio_menu_items
        SET section_key = :section_key,
            etiqueta = :etiqueta,
            href = :href,
            pill = :pill,
            orden = :orden,
            visible = :visible,
            admin_only = :admin_only
        WHERE id = :id AND unidad_id = :uid
        LIMIT 1
      ");
      foreach ($ids as $idRaw) {
        $id = (int)$idRaw;
        if ($id <= 0) continue;
        $sec = (string)($section[$id] ?? 'utilidades');
        if (!in_array($sec, $allowedSections, true)) $sec = 'utilidades';
        $label = trim((string)($etiqueta[$id] ?? ''));
        $url = sanitize_ruta((string)($href[$id] ?? ''));
        if (str_contains((string)($href[$id] ?? ''), '?')) {
          $url = trim(str_replace('\\', '/', (string)$href[$id]));
          $url = preg_replace('#^.*?/public/#i', '', $url) ?? $url;
          $url = ltrim($url, '/');
          if (str_contains($url, '..') || str_contains($url, ':')) $url = '';
        }
        if ($label === '' || $url === '') continue;
        $st->execute([
          ':section_key' => $sec,
          ':etiqueta' => $label,
          ':href' => $url,
          ':pill' => trim((string)($pill[$id] ?? '')) ?: null,
          ':orden' => (int)($orden[$id] ?? 0),
          ':visible' => isset($visible[$id]) ? 1 : 0,
          ':admin_only' => isset($adminOnly[$id]) ? 1 : 0,
          ':id' => $id,
          ':uid' => $unidadActiva,
        ]);
      }
      $msgOk = "Botones de Inicio actualizados.";
    }

    if ($action === 'move_inicio_item') {
      $id = (int)($_POST['item_id'] ?? 0);
      $sec = (string)($_POST['section_key'] ?? 'utilidades');
      $allowedSections = ['destinos','educacion','utilidades','administracion'];
      if ($id <= 0) throw new RuntimeException("Botón inválido.");
      if (!in_array($sec, $allowedSections, true)) $sec = 'utilidades';

      $st = $pdo->prepare("
        UPDATE inicio_menu_items
        SET section_key = :section_key
        WHERE id = :id AND unidad_id = :uid
        LIMIT 1
      ");
      $st->execute([':section_key' => $sec, ':id' => $id, ':uid' => $unidadActiva]);
      $msgOk = "Botón movido correctamente.";
    }

    if ($action === 'toggle_inicio_item') {
      $id = (int)($_POST['item_id'] ?? 0);
      if ($id <= 0) throw new RuntimeException("Botón inválido.");
      $st = $pdo->prepare("
        UPDATE inicio_menu_items
        SET visible = CASE WHEN visible = 1 THEN 0 ELSE 1 END
        WHERE id = :id AND unidad_id = :uid
        LIMIT 1
      ");
      $st->execute([':id' => $id, ':uid' => $unidadActiva]);
      $msgOk = "Visibilidad del botón actualizada.";
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
    SELECT id, codigo, nombre, ruta, activo, orden
    FROM destino
    WHERE unidad_id = :uid
    ORDER BY orden ASC, id ASC
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

$inicioSections = [];
$inicioItems = [];
try {
  $st = $pdo->prepare("
    SELECT id, section_key, titulo, orden, visible
    FROM inicio_menu_secciones
    WHERE unidad_id = :uid
    ORDER BY orden ASC, id ASC
  ");
  $st->execute([':uid' => $unidadActiva]);
  $inicioSections = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  $st = $pdo->prepare("
    SELECT id, section_key, etiqueta, href, pill, orden, visible, admin_only
    FROM inicio_menu_items
    WHERE unidad_id = :uid
    ORDER BY section_key ASC, orden ASC, id ASC
  ");
  $st->execute([':uid' => $unidadActiva]);
  $inicioItems = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
  $msgErr = $msgErr ?: ("No se pudo cargar el menú de Inicio: " . $e->getMessage());
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

function destino_link_admin_preview(array $d): string {
  $ruta = trim((string)($d['ruta'] ?? ''));
  if ($ruta !== '') {
    $ruta = str_replace('\\', '/', $ruta);
    $ruta = ltrim($ruta, '/');
    $ruta = str_replace('..', '', $ruta);
    $ruta = preg_replace('#^.*?/public/#', '', $ruta) ?? $ruta;
    return $ruta;
  }
  return 'admin/administrar_destino.php?id=' . (int)($d['id'] ?? 0);
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
  label.form-label{ color:#ffffff !important; font-weight:900; }

  .box{ border:1px solid rgba(148,163,184,.25); background:rgba(2,6,23,.62); border-radius:16px; padding:14px; }

  .form-control, .form-select{
    background:rgba(2,6,23,.78) !important;
    border:1px solid rgba(148,163,184,.32) !important;
    color:#f1f5f9 !important;
  }
  .form-control::placeholder{ color:rgba(203,213,245,.65) !important; }
  .form-control:focus, .form-select:focus{ box-shadow:none !important; border-color:rgba(34,197,94,.55) !important; }

  .table{
    --bs-table-bg: transparent;
    --bs-table-color:#f8fafc;
    --bs-table-border-color:rgba(203,213,225,.24);
  }
  .table thead th{
    color:#ffffff !important;
    background:rgba(15,23,42,.96) !important;
    border-color:rgba(203,213,225,.34) !important;
    font-weight:900;
    text-transform:uppercase;
    font-size:.78rem;
    letter-spacing:.06em;
  }
  .table td{ color:#f8fafc !important; border-color:rgba(203,213,225,.22) !important; vertical-align:middle; }
  .table tbody tr:hover td{ background:rgba(34,197,94,.08) !important; }

  .badge-on{ background:rgba(34,197,94,.22); border:1px solid rgba(34,197,94,.35); color:#d1fae5; }
  .badge-off{ background:rgba(148,163,184,.18); border:1px solid rgba(148,163,184,.25); color:#e5e7eb; }
  .preview-grid{ display:grid; grid-template-columns:repeat(auto-fill,minmax(190px,1fr)); gap:10px; }
  .preview-card{
    display:flex; align-items:center; justify-content:space-between; gap:10px;
    padding:10px 12px; border-radius:14px;
    background:rgba(15,23,42,.82);
    border:1px solid rgba(148,163,184,.22);
    color:#f8fafc; text-decoration:none;
  }
  .preview-card:hover{ color:#fff; border-color:rgba(34,197,94,.45); background:rgba(34,197,94,.12); }
  .preview-name{ min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-weight:900; }
  .preview-empty{ color:#b7c3d6; border:1px dashed rgba(148,163,184,.28); border-radius:14px; padding:12px; }
  .menu-grid{ display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:12px; }
  .menu-card{
    border:1px solid rgba(203,213,225,.24);
    background:rgba(15,23,42,.72);
    border-radius:16px;
    padding:12px;
  }
  .menu-card-title{ color:#fff; font-weight:950; margin-bottom:10px; display:flex; align-items:center; justify-content:space-between; gap:8px; }
  .menu-card-sub{ color:#cbd5e1; font-size:.8rem; margin-top:-4px; margin-bottom:10px; }
  .menu-item-card{
    border:1px solid rgba(203,213,225,.22);
    background:rgba(2,6,23,.62);
    border-radius:16px;
    padding:12px;
    margin-bottom:10px;
  }
  .menu-item-head{ color:#fff; font-weight:950; margin-bottom:10px; display:flex; align-items:center; justify-content:space-between; gap:8px; }
  .switch-line{ display:flex; align-items:center; gap:8px; color:#fff; font-weight:850; font-size:.84rem; }
  .panel > .box.mt-3 .table-responsive{ overflow:visible; }
  .panel > .box.mt-3 table,
  .panel > .box.mt-3 thead,
  .panel > .box.mt-3 tbody,
  .panel > .box.mt-3 tr,
  .panel > .box.mt-3 td{
    display:block;
  }
  .panel > .box.mt-3 thead{ display:none; }
  .panel > .box.mt-3 tbody{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(280px,1fr));
    gap:12px;
  }
  .panel > .box.mt-3 tr{
    border:1px solid rgba(203,213,225,.26);
    background:rgba(15,23,42,.72);
    border-radius:16px;
    padding:12px;
  }
  .panel > .box.mt-3 td{
    border:0 !important;
    padding:5px 0 !important;
  }
  .panel > .box.mt-3 td:first-child::before{
    content:"Nombre";
    display:block;
    color:#fff;
    font-weight:950;
    font-size:.78rem;
    margin-bottom:4px;
  }
  .panel > .box.mt-3 td:nth-child(2)::before{
    content:"Apartado / clave";
    display:block;
    color:#fff;
    font-weight:950;
    font-size:.78rem;
    margin-bottom:4px;
  }
  .panel > .box.mt-3 td:nth-child(3)::before{
    content:"Ruta / orden";
    display:block;
    color:#fff;
    font-weight:950;
    font-size:.78rem;
    margin-bottom:4px;
  }
  .panel > .box.mt-3 td:nth-child(4)::before{
    content:"Pill / visible";
    display:block;
    color:#fff;
    font-weight:950;
    font-size:.78rem;
    margin-bottom:4px;
  }

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
      <div class="col-12">
        <div class="box">
          <div class="mb-2" style="font-weight:900;">Agregar módulo visible en Inicio</div>

          <form method="post">
            <input type="hidden" name="action" value="add">

            <div class="row g-2">
              <div class="col-4">
                <label class="form-label">Código</label>
                <input class="form-control" name="codigo" placeholder="S1 / S2 / S3">
              </div>
              <div class="col-6">
                <label class="form-label">Nombre *</label>
                <input class="form-control" name="nombre" required placeholder="Personal / Inteligencia / Operaciones">
              </div>
              <div class="col-2">
                <label class="form-label">Orden</label>
                <input class="form-control" type="number" min="0" name="orden" placeholder="10">
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

        <div class="box mt-3 d-none">
          <div class="mb-2" style="font-weight:900;">Vista previa de Inicio</div>
          <div class="text-muted mb-3" style="font-size:.88rem;">
            Así se ve el apartado <b>Destinos / Áreas de trabajo</b> para usuarios con permiso.
          </div>
          <div class="preview-grid">
            <?php $visiblesPreview = array_values(array_filter($destinos, static fn($d) => (int)($d['activo'] ?? 0) === 1)); ?>
            <?php if (!$visiblesPreview): ?>
              <div class="preview-empty">No hay módulos visibles en Inicio.</div>
            <?php else: ?>
              <?php foreach ($visiblesPreview as $pv): ?>
                <a class="preview-card" href="<?= e($BASE_PUBLIC_WEB . '/' . destino_link_admin_preview($pv)) ?>" target="_blank" rel="noopener">
                  <span class="preview-name"><?= e($pv['nombre'] ?? '') ?></span>
                  <span class="pill"><?= e(($pv['codigo'] ?? '') !== '' ? (string)$pv['codigo'] : ('ID ' . (int)$pv['id'])) ?></span>
                </a>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="col-12">
        <div class="box">
          <div class="mb-2" style="font-weight:900;">Listado general de Inicio</div>
          <div class="text-muted mb-2" style="font-size:.88rem;">
            Acá ves todo lo que puede aparecer en Inicio. Los botones se pueden mover de apartado con <b>Mover a</b>.
          </div>

          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead>
                <tr>
                  <th style="width:90px;">Tipo</th>
                  <th style="width:85px;">Orden</th>
                  <th style="width:120px;">Código</th>
                  <th>Nombre</th>
                  <th style="width:260px;">Ruta</th>
                  <th style="width:110px;">Visible</th>
                  <th style="width:340px;">Acciones</th>
                </tr>
              </thead>
              <tbody>
              <?php if (empty($destinos)): ?>
                <tr><td colspan="7" class="text-muted">No hay destinos cargados para unidad_id <?= (int)$unidadActiva ?>.</td></tr>
              <?php else: ?>
                <?php foreach ($destinos as $d): ?>
                  <?php
                    $id  = (int)$d['id'];
                    $cod = (string)($d['codigo'] ?? '');
                    $nom = (string)($d['nombre'] ?? '');
                    $rut = (string)($d['ruta'] ?? '');
                    $act = (int)($d['activo'] ?? 1);
                    $ord = (int)($d['orden'] ?? 0);
                    $mid = 'editDest' . $id;

                    $rutaOk = ($rut === '') ? true : ruta_existe_en_public($PUBLIC_FS, $rut);
                  ?>
                  <tr>
                    <td>DEST <?= $id ?></td>
                    <td><?= $ord ?></td>
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
                                  <div class="col-6">
                                    <label class="form-label">Nombre *</label>
                                    <input class="form-control" name="nombre" required value="<?= e($nom) ?>">
                                  </div>
                                  <div class="col-2">
                                    <label class="form-label">Orden</label>
                                    <input class="form-control" type="number" min="0" name="orden" value="<?= $ord ?>">
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
              <?php foreach ($inicioItems as $item): ?>
                <?php
                  $iid = (int)$item['id'];
                  $sec = (string)($item['section_key'] ?? '');
                  $act = (int)($item['visible'] ?? 1);
                ?>
                <tr>
                  <td>BTN <?= $iid ?></td>
                  <td><?= (int)($item['orden'] ?? 0) ?></td>
                  <td><span class="pill"><?= e($sec) ?></span></td>
                  <td><?= e($item['etiqueta'] ?? '') ?></td>
                  <td class="text-muted"><?= e($item['href'] ?? '') ?></td>
                  <td>
                    <span class="badge <?= $act ? 'badge-on' : 'badge-off' ?>">
                      <?= $act ? 'SI' : 'NO' ?>
                    </span>
                  </td>
                  <td>
                    <form method="post" class="d-inline-flex gap-2 align-items-center flex-wrap">
                      <input type="hidden" name="action" value="move_inicio_item">
                      <input type="hidden" name="item_id" value="<?= $iid ?>">
                      <select class="form-select form-select-sm" name="section_key" style="width:155px;">
                        <?php foreach ($inicioSections as $secOpt): ?>
                          <option value="<?= e($secOpt['section_key']) ?>" <?= $sec === (string)$secOpt['section_key'] ? 'selected' : '' ?>>
                            <?= e($secOpt['titulo']) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                      <button class="btn btn-sm btn-success" type="submit">Mover a</button>
                    </form>
                    <form method="post" style="display:inline;">
                      <input type="hidden" name="action" value="toggle_inicio_item">
                      <input type="hidden" name="item_id" value="<?= $iid ?>">
                      <button class="btn btn-sm btn-outline-light" type="submit">
                        <?= $act ? 'Ocultar' : 'Mostrar' ?>
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>

        </div>
      </div>

    </div>

    <div class="box mt-3 d-none">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
          <div style="font-weight:900;">Menú de Inicio</div>
          <div class="text-muted" style="font-size:.88rem;">
            Desde acá movés botones entre apartados, ocultás secciones completas o cambiás el orden del tablero.
          </div>
        </div>
        <a class="btn btn-sm btn-outline-light" href="<?= e($BASE_PUBLIC_WEB) ?>/inicio.php" target="_blank" rel="noopener">Ver Inicio</a>
      </div>

      <form method="post" class="mb-4">
        <input type="hidden" name="action" value="update_inicio_sections">
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead>
              <tr>
                <th>Apartado</th>
                <th style="width:120px;">Clave</th>
                <th style="width:90px;">Orden</th>
                <th style="width:110px;">Visible</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($inicioSections as $sec): ?>
                <?php $sid = (int)$sec['id']; ?>
                <tr>
                  <td>
                    <input type="hidden" name="section_ids[]" value="<?= $sid ?>">
                    <input class="form-control form-control-sm" name="section_titulo[<?= $sid ?>]" value="<?= e($sec['titulo'] ?? '') ?>">
                  </td>
                  <td><span class="pill"><?= e($sec['section_key'] ?? '') ?></span></td>
                  <td><input class="form-control form-control-sm" type="number" name="section_orden[<?= $sid ?>]" value="<?= (int)($sec['orden'] ?? 0) ?>"></td>
                  <td class="text-center">
                    <input class="form-check-input" type="checkbox" name="section_visible[<?= $sid ?>]" value="1" <?= ((int)($sec['visible'] ?? 0) === 1) ? 'checked' : '' ?>>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <button class="btn btn-success btn-sm" type="submit">Guardar apartados</button>
      </form>

      <form method="post">
        <input type="hidden" name="action" value="update_inicio_items">
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead>
              <tr>
                <th style="min-width:210px;">Botón</th>
                <th style="width:155px;">Apartado</th>
                <th style="min-width:230px;">Ruta</th>
                <th style="width:120px;">Pill</th>
                <th style="width:85px;">Orden</th>
                <th style="width:90px;">Visible</th>
                <th style="width:90px;">Admin</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($inicioItems as $item): ?>
                <?php $iid = (int)$item['id']; ?>
                <tr>
                  <td>
                    <input type="hidden" name="item_ids[]" value="<?= $iid ?>">
                    <input class="form-control form-control-sm" name="item_etiqueta[<?= $iid ?>]" value="<?= e($item['etiqueta'] ?? '') ?>">
                  </td>
                  <td>
                    <select class="form-select form-select-sm" name="item_section[<?= $iid ?>]">
                      <?php foreach ($inicioSections as $sec): ?>
                        <option value="<?= e($sec['section_key']) ?>" <?= (string)$item['section_key'] === (string)$sec['section_key'] ? 'selected' : '' ?>>
                          <?= e($sec['titulo']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                  <td><input class="form-control form-control-sm" name="item_href[<?= $iid ?>]" value="<?= e($item['href'] ?? '') ?>"></td>
                  <td><input class="form-control form-control-sm" name="item_pill[<?= $iid ?>]" value="<?= e($item['pill'] ?? '') ?>"></td>
                  <td><input class="form-control form-control-sm" type="number" name="item_orden[<?= $iid ?>]" value="<?= (int)($item['orden'] ?? 0) ?>"></td>
                  <td class="text-center"><input class="form-check-input" type="checkbox" name="item_visible[<?= $iid ?>]" value="1" <?= ((int)($item['visible'] ?? 0) === 1) ? 'checked' : '' ?>></td>
                  <td class="text-center"><input class="form-check-input" type="checkbox" name="item_admin_only[<?= $iid ?>]" value="1" <?= ((int)($item['admin_only'] ?? 0) === 1) ? 'checked' : '' ?>></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <button class="btn btn-success btn-sm" type="submit">Guardar botones</button>
      </form>
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
