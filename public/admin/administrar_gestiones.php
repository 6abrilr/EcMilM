<?php
// public/gestiones.php — Panel de administración (solo ADMIN/SUPERADMIN)
declare(strict_types=1);

require_once __DIR__ . '/../../auth/bootstrap.php';
require_login();
require_once __DIR__ . '/../../config/db.php';

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function norm_dni(string $dni): string { return preg_replace('/\D+/', '', $dni); }

$user = function_exists('current_user') ? current_user() : ($_SESSION['user'] ?? null);
$dniNorm = norm_dni((string)($user['dni'] ?? $user['username'] ?? ''));

/* ===== Assets ===== */
$PUBLIC_URL = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'])), '/');
$APP_URL    = rtrim(str_replace('\\','/', dirname($PUBLIC_URL)), '/');
$ASSETS_URL = ($APP_URL === '' ? '' : $APP_URL) . '/assets';

$IMG_BG   = $ASSETS_URL . '/../../assets/img/fondo.png';
$ESCUDO   = $ASSETS_URL . '/../../assets/img/ecmilm.png';
$FAVICON  = $ASSETS_URL . '/../../assets/img/ecmilm.png';

/* ==========================================================
   Resolver personal_id + unidad propia
   ========================================================== */
$personalId   = 0;
$unidadPropia = 1; // fallback EC MIL M
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
   Fallback: usuario_roles
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
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Gestiones</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="../../assets/css/theme-602.css">
<link rel="icon" href="<?= e($FAVICON) ?>">

<style>
  html,body{ height:100%; }
  body{
    margin:0; color:#e5e7eb; background:#000;
    font-family:system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,sans-serif;
  }

  /* Fondo */
  .page-bg{
    position:fixed; inset:0; z-index:-2; pointer-events:none;
    background:
      linear-gradient(160deg, rgba(0,0,0,.86) 0%, rgba(0,0,0,.68) 55%, rgba(0,0,0,.86) 100%),
      url("<?= e($IMG_BG) ?>") center/cover no-repeat;
    background-attachment: fixed, fixed;
    filter:saturate(1.05);
  }
  .page-bg::before{
    content:""; position:absolute; inset:0; z-index:-1; opacity:.18; pointer-events:none;
    background-image:
      radial-gradient(1.4px 1.4px at 18% 22%, #9cd1ff 20%, transparent 60%),
      radial-gradient(1.2px 1.2px at 63% 48%, #b7ddff 20%, transparent 60%),
      radial-gradient(1.2px 1.2px at 82% 70%, #b7ddff 20%, transparent 60%),
      radial-gradient(1.6px 1.6px at 34% 76%, #cbe8ff 20%, transparent 60%),
      radial-gradient(1.1px 1.1px at 72% 16%, #a7d6ff 20%, transparent 60%);
    background-repeat:no-repeat;
  }

  .container-main{ max-width:1400px; margin:auto; padding:18px; position:relative; z-index:1; }

  .panel{
    background:rgba(15,17,23,.95);
    border:1px solid rgba(148,163,184,.40);
    border-radius:18px;
    padding:18px 22px 22px;
    box-shadow:0 18px 40px rgba(0,0,0,.78), inset 0 1px 0 rgba(255,255,255,.05);
    backdrop-filter:blur(8px);
  }

  /* Header */
  .brand-hero{ padding-top:10px; padding-bottom:10px; position:relative; z-index:2; }
  .brand-hero .hero-inner{ align-items:center; display:flex; gap:14px; }
  .brand-logo{ width:58px; height:58px; object-fit:contain; filter: drop-shadow(0 10px 18px rgba(0,0,0,.55)); }
  .brand-title{ font-size:1.15rem; font-weight:950; line-height:1.1; color:#f8fafc; }
  .brand-sub{ font-size:.9rem; color:#cbd5f5; opacity:.92; margin-top:2px; }
  .header-back{ margin-left:auto; margin-right:17px; margin-top:4px; }

  /* Títulos */
  .panel-title{
    display:flex; align-items:center; justify-content:space-between; gap:12px;
    font-size:1.05rem;
    font-weight:950;
    margin-bottom:14px;
  }
  .kpis{ display:flex; gap:8px; flex-wrap:wrap; }
  .kpi{
    padding:.18rem .6rem;
    border-radius:999px;
    border:1px solid rgba(148,163,184,.24);
    background:rgba(148,163,184,.12);
    color:#e5e7eb;
    font-weight:900;
    font-size:.78rem;
    white-space:nowrap;
  }

  /* Tarjetas */
  .quick{
    display:grid;
    grid-template-columns: repeat(12, 1fr);
    gap:12px;
  }
  .qcard{
    grid-column: span 6;
    background:rgba(2,6,23,.62);
    border:1px solid rgba(148,163,184,.26);
    border-radius:16px;
    padding:14px;
    box-shadow:0 10px 24px rgba(0,0,0,.35);
    transition:transform .14s ease, border-color .14s ease, background .14s ease;
  }
  .qcard:hover{
    transform: translateY(-2px);
    border-color: rgba(34,197,94,.35);
    background: rgba(2,6,23,.72);
  }
  @media (max-width: 992px){
    .qcard{ grid-column: span 12; }
  }
  .qhead{
    display:flex; align-items:center; justify-content:space-between; gap:10px;
    margin-bottom:10px;
  }
  .qtitle{ font-weight:950; letter-spacing:.02em; font-size:1rem; }
  .qdesc{ color:#b7c3d6; font-size:.88rem; margin:0 0 12px 0; line-height:1.35; }
  .qactions{ display:flex; gap:8px; flex-wrap:wrap; }

  .btn-cta{
    border-radius:999px;
    font-weight:950;
    padding:.48rem 1.05rem;
    box-shadow:0 10px 24px rgba(0,0,0,.25);
  }
  .btn-ghost{
    border-radius:999px;
    font-weight:900;
    padding:.48rem 1.05rem;
    border:1px solid rgba(226,232,240,.35) !important;
    color:#f8fafc !important;
    background:rgba(226,232,240,.06) !important;
  }
  .btn-ghost:hover{ background:rgba(226,232,240,.12) !important; }

  .pill{
    font-size:.72rem;
    padding:.15rem .55rem;
    border-radius:999px;
    background:rgba(148,163,184,.18);
    border:1px solid rgba(148,163,184,.20);
    color:#e5e7eb;
    white-space:nowrap;
  }

  .text-muted{ color:#b7c3d6 !important; }
</style>
</head>
<body>
<div class="page-bg"></div>

<header class="brand-hero">
  <div class="hero-inner container-main">
    <img class="brand-logo" src="<?= e($ESCUDO) ?>" alt="Escudo" onerror="this.onerror=null;this.src='../assets/img/EA.png';">
    <div>
      <div class="brand-title"><?= e($NOMBRE) ?></div>
      <div class="brand-sub">“<?= e($LEYENDA) ?>”</div>
      <div class="text-muted" style="font-size:.85rem;">
        Usuario: <strong><?= e($fullNameDB !== '' ? $fullNameDB : ($user['display_name'] ?? '')) ?></strong> ·
        Rol: <strong><?= e($roleCodigo) ?></strong> · Unidad ID: <strong><?= (int)$unidadActiva ?></strong>
      </div>
    </div>

    <div class="header-back">
      <a href="../inicio.php" class="btn btn-success btn-sm" style="font-weight:700; padding:.35rem .9rem;">
        Volver
      </a>
    </div>
  </div>
</header>

<div class="container-main">
  <div class="panel">

    <div class="panel-title">
      <div>Gestiones administrativas</div>
      <div class="kpis">
        <span class="kpi">Unidad activa: <?= (int)$unidadActiva ?></span>
        <span class="kpi">Rol: <?= e($roleCodigo) ?></span>
        <?php if ($esSuperAdmin): ?><span class="kpi">SUPERADMIN</span><?php endif; ?>
      </div>
    </div>

    <!-- ================= Tarjetas ================= -->
    <div class="quick">

      <div class="qcard">
        <div class="qhead">
          <div class="qtitle">👥 Usuarios</div>
          <span class="pill">administrar_usuarios.php</span>
        </div>
        <p class="qdesc">Administrar personal, rol y destino (por unidad o todas si sos SUPERADMIN).</p>
        <div class="qactions">
          <a class="btn btn-primary btn-cta" href="administrar_usuarios.php">Entrar</a>
        </div>
      </div>

      <div class="qcard">
        <div class="qhead">
          <div class="qtitle">🏷️ Destinos</div>
          <span class="pill">administrar_destino.php</span>
        </div>
        <p class="qdesc">Alta/edición de destinos, orden, visibilidad y ruta del módulo.</p>
        <div class="qactions">
          <a class="btn btn-success btn-cta" href="administrar_destino.php">Entrar</a>
        </div>
      </div>

      <div class="qcard">
        <div class="qhead">
          <div class="qtitle">📁 Documentación</div>
          <span class="pill">administrar_archivos.php</span>
        </div>
        <p class="qdesc">Gestión del storage y documentos asociados al sistema.</p>
        <div class="qactions">
          <a class="btn btn-primary btn-cta" href="./administrar_archivos.php">Entrar</a>
        </div>
      </div>

      <?php if ($esSuperAdmin): ?>
      <div class="qcard">
        <div class="qhead">
          <div class="qtitle">🏛️ Unidades</div>
          <span class="pill">SUPERADMIN</span>
        </div>
        <p class="qdesc">Crear/editar unidades (branding, nombres, slugs, etc.).</p>
        <div class="qactions">
          <a class="btn btn-warning btn-cta" href="administrar_unidades.php" style="font-weight:950;">Entrar</a>
        </div>
      </div>
      <?php endif; ?>

    </div><!-- /quick -->

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
