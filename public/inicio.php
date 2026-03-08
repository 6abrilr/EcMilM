<?php
// public/inicio.php — Home único (tablero por DESTINO)
declare(strict_types=1);

require_once __DIR__ . '/../auth/bootstrap.php';
require_login();
require_once __DIR__ . '/../config/db.php';

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function norm_dni(string $dni): string { return preg_replace('/\D+/', '', $dni); }

$user = function_exists('current_user') ? current_user() : ($_SESSION['user'] ?? null);
$dniNorm = norm_dni((string)($user['dni'] ?? $user['username'] ?? ''));

/* ==========================================================
   BASE WEB robusta
   - Este archivo está en /ea/public/inicio.php
   - /ea = BASE_APP_WEB
   - assets reales: /ea/assets/...
   ========================================================== */
$SELF_WEB        = (string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? ''); // /ea/public/inicio.php
$BASE_PUBLIC_WEB = rtrim(str_replace('\\','/', dirname($SELF_WEB)), '/');            // /ea/public
$BASE_APP_WEB    = rtrim(str_replace('\\','/', dirname($BASE_PUBLIC_WEB)), '/');     // /ea
$ASSET_WEB       = $BASE_APP_WEB . '/assets';                                       // /ea/assets

/* ===== Assets (EA/ASSETS) ===== */
$IMG_BG   = $ASSET_WEB . '/img/fondo.png';
$ESCUDO   = $ASSET_WEB . '/img/ecmilm.png';
$FAVICON  = $ASSET_WEB . '/img/ecmilm.png';
$IMG_EC   = $ASSET_WEB . '/img/ecmilm2026.png';

/* ==========================================================
   1) Obtener personal_id + unidad propia (personal_unidad)
   ========================================================== */
$personalId   = 0;
$unidadPropia = 1; // fallback (EC MIL M)
$fullNameDB   = '';

try {
  if ($dniNorm !== '') {
    $st = $pdo->prepare("
      SELECT
        id,
        unidad_id,
        CONCAT_WS(' ', grado, arma, apellido, nombre) AS nombre_comp
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
   2) Rol del usuario (prioridad: personal_unidad.role_id)
      y fallback a usuario_roles (histórico)
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
          CASE r.codigo
            WHEN 'SUPERADMIN' THEN 3
            WHEN 'ADMIN' THEN 2
            ELSE 1
          END DESC,
          ur.created_at DESC,
          ur.id DESC
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

/* ==========================================================
   3) Unidad activa en UI
   ========================================================== */
$unidadActiva = $unidadPropia;
if ($esSuperAdmin) {
  $uSel = (int)($_SESSION['unidad_id'] ?? 0);
  if ($uSel > 0) $unidadActiva = $uSel;
}

/* ==========================================================
   4) Datos de unidad para header + centro
   ========================================================== */
$unidadInfo = [
  'id'              => $unidadActiva,
  'nombre_corto'    => 'EC MIL M',
  'nombre_completo' => 'Escuela Militar de Montaña',
  'subnombre'       => '“La montaña nos une”',
];

try {
  $st = $pdo->prepare("
    SELECT id, nombre_corto, nombre_completo, subnombre
    FROM unidades
    WHERE id = :id
    LIMIT 1
  ");
  $st->execute([':id' => $unidadActiva]);
  if ($u = $st->fetch(PDO::FETCH_ASSOC)) {
    $unidadInfo = array_merge($unidadInfo, $u);
  }
} catch (Throwable $e) {}

/* ==========================================================
   5) DESTINOS según unidad activa (SIN 'orden')
   ========================================================== */
$destinos = [];
$destinosErr = '';

try {
  $st = $pdo->prepare("
    SELECT id, codigo, nombre, ruta, activo
    FROM destino
    WHERE unidad_id = :uid
      AND activo = 1
    ORDER BY id ASC, codigo ASC
  ");
  $st->execute([':uid' => $unidadActiva]);
  $destinos = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
  $destinosErr = $e->getMessage();
  $destinos = [];
}

/* ==========================================================
   6) Lista de unidades (solo SUPERADMIN)
   ========================================================== */
$unidades = [];
if ($esSuperAdmin) {
  try {
    $st = $pdo->query("
      SELECT id, nombre_corto, nombre_completo
      FROM unidades
      WHERE activa = 1
      ORDER BY nombre_corto ASC
    ");
    $unidades = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $e) {}
}

/* ==========================================================
   7) Link helper destino -> página destino
   ========================================================== */
function destino_link(array $d): string {
  $ruta = trim((string)($d['ruta'] ?? ''));

  if ($ruta !== '') {
    $ruta = str_replace('\\', '/', $ruta);
    $ruta = ltrim($ruta, '/');
    $ruta = str_replace('..', '', $ruta);
    $ruta = preg_replace('#^.*?/public/#', '', $ruta) ?? $ruta; // reduce "ea/public/xxx.php"
    return $ruta; // relativo a /public/
  }

  $id = (int)($d['id'] ?? 0);
  return 'admin/administrar_destino.php?id=' . $id;
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Inicio</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- TU THEME (EA/assets) -->
<link rel="stylesheet" href="<?= e($ASSET_WEB) ?>/css/theme-602.css">

<link rel="icon" type="image/png" href="<?= e($FAVICON) ?>">

<style>
  html,body{ height:100%; }
  body{
    margin:0; color:#e5e7eb; background:#000;
    font-family:system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,sans-serif;
  }
  .page-bg{
    position:fixed; inset:0; z-index:-2; pointer-events:none;
    background:
      linear-gradient(160deg, rgba(0,0,0,.85) 0%, rgba(0,0,0,.65) 55%, rgba(0,0,0,.85) 100%),
      url("<?= e($IMG_BG) ?>") center/cover no-repeat;
    background-attachment: fixed, fixed;
    filter:saturate(1.05);
  }
  .page-bg::before{
    content:""; position:absolute; inset:0; z-index:-1; opacity:.18;
    background-image:
      radial-gradient(1.4px 1.4px at 18% 22%, #9cd1ff 20%, transparent 60%),
      radial-gradient(1.2px 1.2px at 63% 48%, #b7ddff 20%, transparent 60%),
      radial-gradient(1.2px 1.2px at 82% 70%, #b7ddff 20%, transparent 60%),
      radial-gradient(1.6px 1.6px at 34% 76%, #cbe8ff 20%, transparent 60%),
      radial-gradient(1.1px 1.1px at 72% 16%, #a7d6ff 20%, transparent 60%);
    background-repeat:no-repeat;
  }

  .container-main{ max-width:1400px; margin:auto; padding:18px; }

  .panel{
    background:rgba(15,17,23,.94);
    border:1px solid rgba(148,163,184,.40);
    border-radius:18px;
    padding:16px;
    box-shadow:0 18px 40px rgba(0,0,0,.75), inset 0 1px 0 rgba(255,255,255,.05);
    backdrop-filter:blur(8px);
  }

  .layout{
    display:grid;
    grid-template-columns: 340px 1fr;
    gap:16px;
    align-items:start;
  }
  @media (max-width: 992px){
    .layout{ grid-template-columns: 1fr; }
  }

  /* Sidebar */
  .sidebar{
    background:rgba(2,6,23,.78);
    border:1px solid rgba(148,163,184,.35);
    border-radius:16px;
    padding:12px;
  }
  .sidebar-title{
    font-weight:900;
    font-size:.9rem;
    letter-spacing:.08em;
    text-transform:uppercase;
    color:#cbd5f5;
    margin:6px 6px 10px;
  }

  .accordion-item{
    background:transparent;
    border:1px solid rgba(148,163,184,.28);
    border-radius:14px !important;
    overflow:hidden;
    margin-bottom:10px;
  }
  .accordion-button{
    background:rgba(15,17,23,.92);
    color:#e5e7eb;
    font-weight:900;
    padding:.85rem .95rem;
  }
  .accordion-button:not(.collapsed){
    background:rgba(17,24,39,.95);
    color:#e5e7eb;
    box-shadow:none;
  }
  .accordion-button:focus{ box-shadow:none; }
  .accordion-body{ background:rgba(2,6,23,.85); }

  .nav-link-card{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    padding:.55rem .6rem;
    border-radius:12px;
    color:#cbd5f5;
    text-decoration:none;
    border:1px solid rgba(148,163,184,.16);
    background:rgba(15,17,23,.55);
    margin-bottom:8px;
    font-weight:800;
    font-size:.88rem;
  }
  .nav-link-card:hover{
    background:rgba(34,197,94,.14);
    border-color:rgba(34,197,94,.35);
    color:#eafff3;
  }
  .pill{
    font-size:.72rem;
    padding:.15rem .55rem;
    border-radius:999px;
    background:rgba(148,163,184,.18);
    border:1px solid rgba(148,163,184,.20);
    color:#e5e7eb;
    white-space:nowrap;
  }

  /* Main */
  .main{
    background:rgba(2,6,23,.72);
    border:1px solid rgba(148,163,184,.35);
    border-radius:16px;
    padding:18px;
    text-align:center;
  }
  .unit-img{
    display:block;
    margin:6px auto 14px;
    width:min(340px, 88%);
    height:auto;
    border-radius:18px;
    border:1px solid rgba(148,163,184,.35);
    box-shadow:0 16px 40px rgba(0,0,0,.55);
  }
  .welcome{ font-weight:900; font-size:1.25rem; margin:6px 0 8px; }
  .unit-sub{ color:#9ca3af; font-size:.9rem; margin-bottom:12px; }
  .unit-text{
    text-align:left;
    max-width:760px;
    margin:0 auto 12px;
    color:#cbd5f5;
    line-height:1.55;
    font-size:.95rem;
  }

  .brand-hero{ padding:10px 0; }
  .brand-hero .hero-inner{ display:flex; align-items:center; }
  .user-info{
    margin-left:auto;
    margin-right:17px;
    text-align:right;
    font-size:.85rem;
  }
  .text-muted{ color:#b7c3d6 !important; }
</style>
</head>
<body>
<div class="page-bg"></div>

<header class="brand-hero">
  <div class="hero-inner container-main" style="padding-top:0; padding-bottom:0;">
    <img class="brand-logo" src="<?= e($ESCUDO) ?>" alt="EA" style="height:52px;width:auto;"
         onerror="this.onerror=null;this.src='<?= e($ASSET_WEB) ?>/img/EA.png';">
    <div>
      <div class="brand-title"><?= e($unidadInfo['nombre_completo'] ?? 'Unidad') ?></div>
      <div class="brand-sub"><?= e($unidadInfo['subnombre'] ?? '') ?></div>
    </div>

    <?php if ($user): ?>
      <div class="user-info">
        <div><strong><?= e($fullNameDB !== '' ? $fullNameDB : ($user['nombre_completo'] ?? $user['display_name'] ?? '')) ?></strong></div>
        <div class="text-muted">
          Unidad ID: <?= (int)$unidadActiva ?> · Rol: <?= e($roleCodigo) ?>
          <?php if ($esSuperAdmin): ?> · <strong>SUPERADMIN</strong><?php endif; ?>
        </div>
        <div class="mt-1">
          <a href="<?= e($BASE_APP_WEB) ?>/logout.php" class="btn btn-success btn-sm" style="font-weight:700; padding:.35rem .9rem;">
            Cerrar sesión
          </a>
        </div>
      </div>
    <?php endif; ?>
  </div>
</header>

<div class="container-main">
  <div class="panel">
    <div class="layout">

      <!-- Sidebar -->
      <aside class="sidebar">
        <div class="sidebar-title">Tablero</div>

        <div class="accordion" id="navAcc">

          <!-- DESTINOS -->
          <div class="accordion-item">
            <h2 class="accordion-header" id="hDest">
              <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#cDest" aria-expanded="true" aria-controls="cDest">
                Destinos / Áreas de trabajo
              </button>
            </h2>
            <div id="cDest" class="accordion-collapse collapse show" aria-labelledby="hDest" data-bs-parent="#navAcc">
              <div class="accordion-body">
                <?php if (empty($destinos)): ?>
                  <div class="text-muted" style="font-size:.9rem;">No hay destinos cargados para esta unidad.</div>

                  <?php if ($esAdmin && $destinosErr !== ''): ?>
                    <div class="alert alert-warning mt-2 py-2" style="font-size:.85rem;">
                      <b>Error SQL destinos:</b> <?= e($destinosErr) ?>
                    </div>
                  <?php endif; ?>

                <?php else: ?>
                  <?php foreach ($destinos as $d): ?>
                    <?php
                      $did = (int)($d['id'] ?? 0);
                      $cod = (string)($d['codigo'] ?? '');
                      $nom = (string)($d['nombre'] ?? $cod);
                      if ($did <= 0) continue;
                    ?>
                    <a class="nav-link-card" href="<?= e(destino_link($d)) ?>">
                      <?= e($nom) ?> <span class="pill"><?= e($cod !== '' ? $cod : ('ID '.$did)) ?></span>
                    </a>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- Administración -->
          <?php if ($esAdmin): ?>
          <div class="accordion-item">
            <h2 class="accordion-header" id="hAdm">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#cAdm" aria-expanded="false" aria-controls="cAdm">
                Administración
              </button>
            </h2>
            <div id="cAdm" class="accordion-collapse collapse" aria-labelledby="hAdm" data-bs-parent="#navAcc">
              <div class="accordion-body">
                <a class="nav-link-card" href="admin/administrar_gestiones.php">
                  Panel de Administración <span class="pill"><?= e($roleCodigo) ?></span>
                </a>
              </div>
            </div>
          </div>
          <?php endif; ?>

        </div>
      </aside>

      <!-- Main -->
      <main class="main">
        <img src="<?= e($IMG_EC) ?>" class="unit-img" alt="Unidad"
             onerror="this.onerror=null;this.style.display='none';">

        <div class="welcome">Bienvenido</div>
        <div class="unit-sub">
          <?= e($unidadInfo['nombre_completo'] ?? '') ?> — Unidad ID <?= (int)($unidadInfo['id'] ?? 0) ?>
        </div>

        <div class="unit-text">
          <p style="margin-top:0;">
            Desde este tablero podés acceder a los destinos internos (áreas de trabajo) y a los módulos del sistema según tus permisos.
          </p>
          
        </div>
      </main>

    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
