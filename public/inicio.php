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
   ========================================================== */
$SELF_WEB        = (string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
$BASE_PUBLIC_WEB = rtrim(str_replace('\\','/', dirname($SELF_WEB)), '/');
$BASE_APP_WEB    = rtrim(str_replace('\\','/', dirname($BASE_PUBLIC_WEB)), '/');
$ASSET_WEB       = $BASE_APP_WEB . '/assets';

/* ===== Assets ===== */
$IMG_BG   = $ASSET_WEB . '/img/fondo.png';
$ESCUDO   = $ASSET_WEB . '/img/ecmilm.png';
$FAVICON  = $ASSET_WEB . '/img/ecmilm.png';
$IMG_EC   = $ASSET_WEB . '/img/ecmilm2026.png';

/* ===== Chat ===== */
$CHAT_FULL_URL = $BASE_PUBLIC_WEB . '/chat.php';
$CHAT_AJAX_URL = $BASE_PUBLIC_WEB . '/chat.php?ajax=1';
$CHAT_CSRF     = csrf_token();

/* ==========================================================
   1) Obtener personal_id + unidad propia
   ========================================================== */
$personalId   = 0;
$unidadPropia = 1;
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
   2) Rol del usuario + área asignada
   ========================================================== */
$roleCodigo     = 'USUARIO';
$userAreaCode   = '';
$userAreaName   = '';

try {
  if ($personalId > 0) {
    $st = $pdo->prepare("
      SELECT r.codigo, d.codigo AS destino_codigo, d.nombre AS destino_nombre
      FROM personal_unidad pu
      LEFT JOIN roles r ON r.id = pu.role_id
      LEFT JOIN destino d ON d.id = pu.destino_id
      WHERE pu.id = :pid
      LIMIT 1
    ");
    $st->execute([':pid' => $personalId]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    if (is_array($r)) {
      $roleCodigo   = strtoupper((string)($r['codigo'] ?? 'USUARIO'));
      $userAreaCode = strtoupper(trim((string)($r['destino_codigo'] ?? '')));
      $userAreaName = (string)($r['destino_nombre'] ?? '');
    }
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
      if (is_string($c) && $c !== '') $roleCodigo = strtoupper($c);
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
   4) Datos de unidad
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
   5) DESTINOS según unidad activa
   ========================================================== */
$destinos = [];
$destinosErr = '';

// KPI básico de tareas por estado (para el área del usuario, o todas si es ADMIN)
$kpiTareas = ['POR_HACER'=>0, 'EN_PROCESO'=>0, 'REALIZADA'=>0];
$kpiArea = $esAdmin ? null : ($userAreaCode ?: null);

try {
  $sql = "
    SELECT id, codigo, nombre, ruta, activo
    FROM destino
    WHERE unidad_id = :uid
      AND activo = 1
  ";
  $params = [':uid' => $unidadActiva];
  if (!$esAdmin && $userAreaCode !== '') {
    $sql .= " AND codigo = :codigo ";
    $params[':codigo'] = $userAreaCode;
  }
  $sql .= " ORDER BY id ASC, codigo ASC";

  $st = $pdo->prepare($sql);
  $st->execute($params);
  $destinos = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
  $destinosErr = $e->getMessage();
  $destinos = [];
}

try {
  $sql = "SELECT estado, COUNT(*) AS cnt FROM calendario_tareas WHERE unidad_id = :uid";
  $params = [':uid' => $unidadActiva];
  if ($kpiArea !== null) {
    $sql .= " AND area_code = :area";
    $params[':area'] = $kpiArea;
  }
  $sql .= " GROUP BY estado";

  $st = $pdo->prepare($sql);
  $st->execute($params);
  while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
    $e = (string)($r['estado'] ?? '');
    $c = (int)($r['cnt'] ?? 0);
    if (isset($kpiTareas[$e])) $kpiTareas[$e] = $c;
  }
} catch (Throwable $e) {
  // fallamos silenciosamente
}

/* ==========================================================
   6) Link helper destino -> página destino
   ========================================================== */
function destino_link(array $d): string {
  $ruta = trim((string)($d['ruta'] ?? ''));

  if ($ruta !== '') {
    $ruta = str_replace('\\', '/', $ruta);
    $ruta = ltrim($ruta, '/');
    $ruta = str_replace('..', '', $ruta);
    $ruta = preg_replace('#^.*?/public/#', '', $ruta) ?? $ruta;
    return $ruta;
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
  .nav-link-card.placeholder{
    border-style:dashed;
    color:#cbd5e1;
    background:rgba(148,163,184,.06);
  }
  .nav-link-card.placeholder:hover{
    background:rgba(148,163,184,.12);
    border-color:rgba(148,163,184,.35);
    color:#f8fafc;
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

  /* ===== Chat dock fijo ===== */
  .chat-launcher{
    position:fixed;
    right:12px;
    bottom:0;
    width:min(250px, calc(100vw - 12px));
    height:52px;
    border-radius:16px 16px 0 0;
    background:rgba(7,11,20,.96);
    border:1px solid rgba(148,163,184,.28);
    border-bottom:none;
    display:none;
    align-items:center;
    justify-content:space-between;
    padding:0 14px;
    z-index:9998;
    cursor:pointer;
    box-shadow:0 -6px 18px rgba(0,0,0,.35);
  }
  .chat-launcher.show{ display:flex; }
  .chat-launcher-title{
    font-weight:900;
    color:#eef2f7;
    letter-spacing:.02em;
  }

  .chat-total-badge{
    display:none;
    min-width:24px;
    height:24px;
    padding:0 7px;
    border-radius:999px;
    background:#dc2626;
    color:#fff;
    font-size:.78rem;
    font-weight:900;
    align-items:center;
    justify-content:center;
  }
  .chat-total-badge.show{ display:inline-flex; }

  .chat-dock{
    position:fixed;
    right:12px;
    bottom:0;
    width:min(540px, calc(100vw - 12px));
    height:430px;
    border-radius:18px 18px 0 0;
    overflow:hidden;
    border:1px solid rgba(148,163,184,.28);
    border-bottom:none;
    background:rgba(6,10,18,.96);
    backdrop-filter:blur(14px);
    box-shadow:0 -12px 28px rgba(0,0,0,.48);
    z-index:9999;
  }

  .chat-dock-head{
    height:54px;
    padding:0 12px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    background:rgba(9,14,24,.98);
    border-bottom:1px solid rgba(148,163,184,.18);
  }

  .chat-dock-title-wrap{
    display:flex;
    align-items:center;
    gap:8px;
  }

  .chat-dock-title{
    font-weight:900;
    color:#eef2f7;
    letter-spacing:.02em;
  }

  .chat-dock-actions{
    display:flex;
    align-items:center;
    gap:8px;
  }

  .chat-btn{
    border:none;
    border-radius:10px;
    padding:.42rem .82rem;
    font-weight:800;
    cursor:pointer;
    text-decoration:none;
    display:inline-flex;
    align-items:center;
    justify-content:center;
  }

  .chat-btn-open{
    background:rgba(148,163,184,.18);
    color:#eef2f7;
  }

  .chat-btn-close{
    background:#16a34a;
    color:#fff;
  }

  .chat-dock-body{
    display:grid;
    grid-template-columns: 185px 1fr;
    height:calc(100% - 54px);
  }

  .chat-conv-pane{
    border-right:1px solid rgba(148,163,184,.16);
    background:rgba(5,10,18,.72);
    display:flex;
    flex-direction:column;
    min-width:0;
  }

  .chat-conv-pane-head{
    padding:10px;
    border-bottom:1px solid rgba(148,163,184,.12);
    font-size:.78rem;
    font-weight:900;
    letter-spacing:.06em;
    color:#d7dfec;
    text-transform:uppercase;
  }

  .chat-conv-list{
    flex:1;
    overflow:auto;
    padding:10px;
  }

  .chat-conv-item{
    width:100%;
    text-align:left;
    border:1px solid rgba(148,163,184,.16);
    background:rgba(7,11,20,.46);
    color:#e5e7eb;
    border-radius:12px;
    padding:10px 10px;
    margin-bottom:8px;
    transition:.16s ease;
  }
  .chat-conv-item:hover{
    background:rgba(34,197,94,.12);
    border-color:rgba(34,197,94,.30);
  }
  .chat-conv-item.active{
    background:rgba(34,197,94,.18);
    border-color:rgba(34,197,94,.44);
  }

  .chat-conv-top{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:8px;
    margin-bottom:4px;
  }
  .chat-conv-name{
    font-weight:900;
    font-size:.86rem;
    line-height:1.1;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
  }
  .chat-conv-type{
    font-size:.64rem;
    font-weight:900;
    padding:.14rem .42rem;
    border-radius:999px;
    background:rgba(148,163,184,.18);
    border:1px solid rgba(148,163,184,.18);
    color:#eef2f7;
    white-space:nowrap;
  }
  .chat-conv-last{
    font-size:.74rem;
    color:#c3d0e2;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
  }

  .chat-conv-badge{
    display:none;
    margin-left:6px;
    font-size:.64rem;
    font-weight:900;
    padding:.14rem .42rem;
    border-radius:999px;
    background:#dc2626;
    color:#fff;
  }
  .chat-conv-badge.show{ display:inline-block; }

  .chat-thread{
    display:flex;
    flex-direction:column;
    min-width:0;
    background:rgba(2,6,23,.40);
  }

  .chat-thread-head{
    padding:12px 14px;
    border-bottom:1px solid rgba(148,163,184,.14);
    background:rgba(9,14,24,.40);
  }
  .chat-thread-title{
    font-weight:900;
    font-size:1rem;
    color:#f3f6fb;
  }
  .chat-thread-sub{
    color:#b8c5d9;
    font-size:.80rem;
    margin-top:2px;
  }

  .chat-messages{
    flex:1;
    overflow:auto;
    padding:12px;
    background:
      linear-gradient(180deg, rgba(2,6,23,.18), rgba(2,6,23,.24)),
      url("<?= e($IMG_EC) ?>") center/cover no-repeat;
    background-blend-mode:overlay;
  }

  .chat-empty{
    height:100%;
    display:flex;
    align-items:center;
    justify-content:center;
    text-align:center;
    color:#dbe7f6;
    font-weight:800;
  }

  .msg-row{
    display:flex;
    flex-direction:column;
    margin-bottom:10px;
  }
  .msg-row.me{ align-items:flex-end; }
  .msg-row.other{ align-items:flex-start; }

  .msg-meta{
    font-size:.70rem;
    color:#d7dfec;
    margin-bottom:4px;
    font-weight:800;
  }

  .msg-bubble{
    max-width:92%;
    display:inline-block;
    padding:8px 11px;
    border-radius:14px;
    font-size:.84rem;
    line-height:1.35;
    white-space:pre-wrap;
    word-break:break-word;
    border:1px solid rgba(148,163,184,.16);
  }
  .msg-bubble.me{
    background:linear-gradient(180deg, rgba(34,197,94,.92), rgba(22,163,74,.92));
    color:#08130b;
    border-top-right-radius:6px;
    font-weight:800;
  }
  .msg-bubble.other{
    background:rgba(9,13,24,.66);
    color:#eef2f7;
    border-top-left-radius:6px;
  }

  .chat-compose{
    border-top:1px solid rgba(148,163,184,.14);
    background:rgba(9,14,24,.70);
    padding:10px;
  }
  .chat-compose-row{
    display:flex;
    gap:8px;
  }
  .chat-compose input{
    flex:1;
  }

  .chat-readonly{
    border-top:1px solid rgba(148,163,184,.14);
    background:rgba(127,29,29,.22);
    color:#fecaca;
    padding:10px 12px;
    font-weight:800;
    text-align:center;
    font-size:.82rem;
    display:none;
  }
  .chat-readonly.show{ display:block; }

  .chat-hidden{
    display:none !important;
  }

  @media (max-width: 768px){
    .chat-dock{
      right:4px;
      width:calc(100vw - 8px);
      height:min(72vh, 500px);
    }
    .chat-launcher{
      right:4px;
      width:calc(100vw - 8px);
    }
    .chat-dock-body{
      grid-template-columns: 1fr;
      grid-template-rows: 130px 1fr;
    }
    .chat-conv-pane{
      border-right:none;
      border-bottom:1px solid rgba(148,163,184,.16);
    }
    .chat-conv-list{
      display:flex;
      gap:8px;
      overflow:auto;
      padding:10px;
    }
    .chat-conv-item{
      min-width:180px;
      margin-bottom:0;
    }
    .chat-dock-actions .chat-btn{
      padding:.36rem .66rem;
      font-size:.82rem;
    }
  }
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

      <aside class="sidebar">
        <div class="sidebar-title">Tablero</div>

        <div class="accordion" id="navAcc">

          <div class="accordion-item">
            <h2 class="accordion-header" id="hDest">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#cDest" aria-expanded="false" aria-controls="cDest">
                Destinos / Áreas de trabajo
              </button>
            </h2>
            <div id="cDest" class="accordion-collapse collapse" aria-labelledby="hDest" data-bs-parent="#navAcc">
              <div class="accordion-body">
                <?php if (empty($destinos)): ?>
                  <div class="text-muted" style="font-size:.9rem;">No hay destinos cargados para esta unidad.</div>

                  <?php if ($userAreaCode !== ''): ?>
                    <a class="nav-link-card" href="<?= e($BASE_PUBLIC_WEB) ?>/calendario.php?area=<?= e($userAreaCode) ?>">
                      Ir a tu área: <span class="pill"><?= e($userAreaCode) ?></span>
                    </a>
                  <?php endif; ?>

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

          <div class="accordion-item">
            <h2 class="accordion-header" id="hQuick">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#cQuick" aria-expanded="false" aria-controls="cQuick">
                Utilidades
              </button>
            </h2>
            <div id="cQuick" class="accordion-collapse collapse" aria-labelledby="hQuick" data-bs-parent="#navAcc">
              <div class="accordion-body">

                <?php $calendarArea = $esAdmin ? 'ALL' : ($userAreaCode ?: 'ALL'); ?>
                
                <a class="nav-link-card" href="<?= e($BASE_PUBLIC_WEB) ?>/CHAT.php">
                  CHAT <span class="pill">Ver</span>
                </a>

                <a class="nav-link-card" href="<?= e($BASE_PUBLIC_WEB) ?>/calendario.php?area=<?= e($calendarArea) ?>">
                  Calendario <span class="pill">Ver</span>
                </a>

                <a class="nav-link-card" href="<?= e($BASE_PUBLIC_WEB) ?>/documentacion.php">
                  Buscador de documentación <span class="pill">DOCUMENTACIÓN</span>
                </a>

                <a class="nav-link-card" href="<?= e($BASE_PUBLIC_WEB) ?>/editardocumentos.php">
                  Convertir PDF a Word o imágenes <span class="pill">Herramienta</span>
                </a>

                <a class="nav-link-card" href="javascript:void(0)">
                  Asistente IA sobre archivos <span class="pill">A implementar</span>
                </a>

              

              </div>
            </div>
          </div>

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

      <main class="main">
        <img src="<?= e($IMG_EC) ?>" class="unit-img" alt="Unidad"
             onerror="this.onerror=null;this.style.display='none';">

        <div class="welcome">Bienvenido</div>

        <div class="unit-text">
          <p style="margin-top:0;">
            Desde este tablero podés acceder a los destinos internos (áreas de trabajo) y a los módulos del sistema según tus permisos.
          </p>
        </div>
      </main>

    </div>
  </div>
</div>

<!-- Launcher mínimo -->
<div id="chatLauncher" class="chat-launcher show">
  <div class="chat-launcher-title">Chat interno</div>
  <span id="chatLauncherBadge" class="chat-total-badge">0</span>
</div>

<!-- Dock fijo -->
<div id="chatDock" class="chat-dock chat-hidden">
  <div class="chat-dock-head">
    <div class="chat-dock-title-wrap">
      <div class="chat-dock-title">Chat interno</div>
      <span id="chatDockBadge" class="chat-total-badge">0</span>
    </div>

    <div class="chat-dock-actions">
      <a id="chatOpenFull" href="<?= e($CHAT_FULL_URL) ?>" class="chat-btn chat-btn-open">Agrandar</a>
      <button type="button" id="chatCloseBtn" class="chat-btn chat-btn-close">Cerrar</button>
    </div>
  </div>

  <div class="chat-dock-body">
    <div class="chat-conv-pane">
      <div class="chat-conv-pane-head">Conversaciones</div>
      <div id="chatConvList" class="chat-conv-list">
        <div class="chat-empty">Cargando...</div>
      </div>
    </div>

    <div class="chat-thread">
      <div class="chat-thread-head">
        <div id="chatThreadTitle" class="chat-thread-title">Chat General</div>
        <div id="chatThreadSub" class="chat-thread-sub">Mensajes generales de la unidad</div>
      </div>

      <div id="chatMessages" class="chat-messages">
        <div class="chat-empty">Cargando mensajes...</div>
      </div>

      <div id="chatReadonly" class="chat-readonly">
        Solo ADMIN y SUPERADMIN pueden escribir en el chat general.
      </div>

      <form id="chatCompose" class="chat-compose">
        <div class="chat-compose-row">
          <input type="text" id="chatInput" class="form-control" maxlength="4000" placeholder="Escribí un mensaje...">
          <button type="submit" class="btn btn-success btn-sm" style="font-weight:800;">Enviar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
  const CHAT_AJAX_URL = <?= json_encode($CHAT_AJAX_URL, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  const CHAT_FULL_URL = <?= json_encode($CHAT_FULL_URL, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  const CSRF_TOKEN    = <?= json_encode($CHAT_CSRF, JSON_UNESCAPED_UNICODE) ?>;
  const CAN_WRITE_GENERAL = <?= $esAdmin ? 'true' : 'false' ?>;
  const STORAGE_KEY = 'ea_chat_seen_<?= (int)$personalId ?>';

  const dock = document.getElementById('chatDock');
  const launcher = document.getElementById('chatLauncher');
  const launcherBadge = document.getElementById('chatLauncherBadge');
  const dockBadge = document.getElementById('chatDockBadge');
  const closeBtn = document.getElementById('chatCloseBtn');
  const openChatBtn = document.getElementById('openChatBtn');

  const convList = document.getElementById('chatConvList');
  const messagesBox = document.getElementById('chatMessages');
  const threadTitle = document.getElementById('chatThreadTitle');
  const threadSub = document.getElementById('chatThreadSub');
  const compose = document.getElementById('chatCompose');
  const input = document.getElementById('chatInput');
  const readonlyBox = document.getElementById('chatReadonly');
  const openFull = document.getElementById('chatOpenFull');

  const state = {
    conversations: [],
    selectedConversationId: null,
    unreadMap: {},
    seenMap: {},
    baselineLoaded: false,
    pollingHandle: null
  };

  try {
    state.seenMap = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}') || {};
  } catch (e) {
    state.seenMap = {};
  }

  function saveSeenMap() {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(state.seenMap));
    } catch (e) {}
  }

  function escapeHtml(str) {
    return String(str ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function apiGet(action, params = {}) {
    const qs = new URLSearchParams({ action, ...params });
    return fetch(`${CHAT_AJAX_URL}&${qs.toString()}`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).then(r => r.json());
  }

  function apiPost(action, params = {}) {
    const body = new URLSearchParams({ action, _csrf: CSRF_TOKEN, ...params });
    return fetch(CHAT_AJAX_URL, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: body.toString()
    }).then(r => r.json());
  }

  function selectedConversation() {
    return state.conversations.find(c => Number(c.id) === Number(state.selectedConversationId)) || null;
  }

  function updateUnreadBadges() {
    const total = Object.keys(state.unreadMap).length;

    launcherBadge.textContent = String(total);
    dockBadge.textContent = String(total);

    launcherBadge.classList.toggle('show', total > 0);
    dockBadge.classList.toggle('show', total > 0);

    document.title = total > 0 ? `(${total}) Inicio` : 'Inicio';
  }

  function processUnread() {
    if (!state.baselineLoaded) {
      state.conversations.forEach(c => {
        state.seenMap[c.id] = Number(c.last_message_id || 0);
      });
      state.baselineLoaded = true;
      saveSeenMap();
      updateUnreadBadges();
      return;
    }

    state.conversations.forEach(c => {
      const currentId = Number(c.last_message_id || 0);
      const seenId = Number(state.seenMap[c.id] || 0);
      const isSelected = Number(c.id) === Number(state.selectedConversationId);

      if (currentId > seenId) {
        if (isSelected) {
          state.seenMap[c.id] = currentId;
          delete state.unreadMap[c.id];
        } else if (!c.last_from_me && currentId > 0) {
          state.unreadMap[c.id] = true;
        } else {
          state.seenMap[c.id] = currentId;
        }
      }
    });

    saveSeenMap();
    updateUnreadBadges();
  }

  function markConversationSeen(conversationId) {
    const c = state.conversations.find(x => Number(x.id) === Number(conversationId));
    if (!c) return;

    state.seenMap[conversationId] = Number(c.last_message_id || 0);
    delete state.unreadMap[conversationId];
    saveSeenMap();
    updateUnreadBadges();
  }

  function setThreadHeader() {
    const c = selectedConversation();
    if (!c) {
      threadTitle.textContent = 'Sin conversación';
      threadSub.textContent = '';
      compose.classList.remove('chat-hidden');
      readonlyBox.classList.remove('show');
      openFull.href = CHAT_FULL_URL;
      return;
    }

    threadTitle.textContent = c.title || 'Conversación';
    threadSub.textContent = c.type === 'general'
      ? 'Mensajes generales de la unidad'
      : (c.is_self ? 'Tus notas personales' : 'Conversación privada');

    const readOnly = (c.type === 'general' && !CAN_WRITE_GENERAL);
    compose.classList.toggle('chat-hidden', readOnly);
    readonlyBox.classList.toggle('show', readOnly);
    openFull.href = CHAT_FULL_URL;
  }

  function renderConversations() {
    if (!state.conversations.length) {
      convList.innerHTML = `<div class="chat-empty">No hay conversaciones.</div>`;
      return;
    }

    convList.innerHTML = state.conversations.map(c => {
      const typeText = c.type === 'general' ? 'GENERAL' : (c.is_self ? 'NOTAS' : 'PRIVADO');
      const isNew = !!state.unreadMap[c.id];

      return `
        <button type="button"
                class="chat-conv-item ${Number(c.id) === Number(state.selectedConversationId) ? 'active' : ''}"
                data-id="${Number(c.id)}">
          <div class="chat-conv-top">
            <div class="chat-conv-name">${escapeHtml(c.title || 'Conversación')}</div>
            <div style="display:flex; align-items:center;">
              <span class="chat-conv-badge ${isNew ? 'show' : ''}">NUEVO</span>
              <span class="chat-conv-type">${escapeHtml(typeText)}</span>
            </div>
          </div>
          <div class="chat-conv-last">${escapeHtml(c.last_message || 'Sin mensajes todavía')}</div>
        </button>
      `;
    }).join('');

    convList.querySelectorAll('.chat-conv-item').forEach(btn => {
      btn.addEventListener('click', async () => {
        state.selectedConversationId = Number(btn.dataset.id);
        markConversationSeen(state.selectedConversationId);
        renderConversations();
        setThreadHeader();
        await loadMessages(true);
      });
    });
  }

  async function loadConversations(preferId = null) {
    const r = await apiGet('list_conversations');
    if (!r.ok) {
      convList.innerHTML = `<div class="chat-empty">No se pudieron cargar las conversaciones.</div>`;
      return;
    }

    state.conversations = Array.isArray(r.items) ? r.items : [];

    if (preferId) {
      state.selectedConversationId = Number(preferId);
    } else if (!state.selectedConversationId && state.conversations.length) {
      state.selectedConversationId = Number(state.conversations[0].id);
    } else {
      const exists = state.conversations.some(c => Number(c.id) === Number(state.selectedConversationId));
      if (!exists && state.conversations.length) {
        state.selectedConversationId = Number(state.conversations[0].id);
      }
    }

    processUnread();
    renderConversations();
    setThreadHeader();
  }

  async function loadMessages(scrollBottom = false) {
    if (!state.selectedConversationId) return;

    const r = await apiGet('get_messages', {
      conversation_id: state.selectedConversationId
    });

    if (!r.ok) {
      messagesBox.innerHTML = `<div class="chat-empty">${escapeHtml(r.error || 'No se pudieron cargar los mensajes.')}</div>`;
      return;
    }

    const items = Array.isArray(r.items) ? r.items : [];
    const viewItems = items.slice(-25);

    if (!viewItems.length) {
      messagesBox.innerHTML = `<div class="chat-empty">No hay mensajes todavía.</div>`;
      markConversationSeen(state.selectedConversationId);
      return;
    }

    messagesBox.innerHTML = viewItems.map(m => `
      <div class="msg-row ${m.mine ? 'me' : 'other'}">
        <div class="msg-meta">${escapeHtml(m.mine ? 'Yo' : m.author)} · ${escapeHtml(m.created_hm || '')}</div>
        <div class="msg-bubble ${m.mine ? 'me' : 'other'}">${escapeHtml(m.message || '')}</div>
      </div>
    `).join('');

    markConversationSeen(state.selectedConversationId);
    renderConversations();

    if (scrollBottom) {
      messagesBox.scrollTop = messagesBox.scrollHeight;
    }
  }

  async function sendMessage(ev) {
    ev.preventDefault();

    const text = input.value.trim();
    const c = selectedConversation();
    if (!c || !text) return;

    const r = await apiPost('send_message', {
      conversation_id: c.id,
      message: text
    });

    if (!r.ok) {
      alert(r.error || 'No se pudo enviar el mensaje.');
      return;
    }

    input.value = '';
    await loadMessages(true);
    await loadConversations(c.id);
  }

  function closeDock() {
    dock.classList.add('chat-hidden');
    launcher.classList.remove('chat-hidden');
    launcher.classList.add('show');
  }

  function openDock() {
    launcher.classList.remove('show');
    launcher.classList.add('chat-hidden');
    dock.classList.remove('chat-hidden');
  }

  closeBtn.addEventListener('click', closeDock);
  launcher.addEventListener('click', openDock);
  if (openChatBtn) {
    openChatBtn.addEventListener('click', function(ev){ ev.preventDefault(); openDock(); });
  }
  compose.addEventListener('submit', sendMessage);

  document.addEventListener('keydown', function(ev){
    if (ev.key === 'Escape' && !dock.classList.contains('chat-hidden')) {
      closeDock();
    }
  });

  loadConversations().then(() => loadMessages(true));

  state.pollingHandle = setInterval(async () => {
    const current = state.selectedConversationId;
    await loadConversations(current);
    await loadMessages(false);
  }, 5000);
})();
</script>
</body>
</html>