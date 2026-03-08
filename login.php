<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/bootstrap.php';
require_once __DIR__ . '/login_cps.php';

if (!function_exists('h')) {
  function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}

/* ============================
   ✅ SUPERADMIN HARD OVERRIDE
   ============================ */
const EA_SUPERADMIN_DNI  = '41742406';
const EA_SUPERADMIN_USER = 'nesrojas';

function is_superadmin_login(string $username): bool {
  $u = strtolower(trim($username));
  return ($u === strtolower(EA_SUPERADMIN_USER) || $u === EA_SUPERADMIN_DNI);
}

/*
 * Base de la app y home post-login.
 * Ej: /ea/login.php => APP_BASE=/ea
 */
$APP_BASE = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
if ($APP_BASE === '/' || $APP_BASE === '\\') $APP_BASE = '';

$HOME_AFTER_LOGIN = $APP_BASE . '/public/inicio.php';

/* Sanitizar "next" (sólo paths locales) */
$next = $_GET['next'] ?? $_POST['next'] ?? $HOME_AFTER_LOGIN;
if (!is_string($next) || !preg_match('#^/[^:]*$#', $next)) $next = $HOME_AFTER_LOGIN;

$error = '';

/* ===== POST: Login real con CPS ===== */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  csrf_verify();

  $username = trim((string)($_POST['username'] ?? ''));
  $pass     = (string)($_POST['password'] ?? '');

  try {
    // 1) Login normal (tu lógica actual)
    $ok = auth_login_cps($username, $pass);

    // 2) ✅ Si falla, pero es superadmin, intentar "forzar" (igual valida CPS)
    if (!$ok && is_superadmin_login($username)) {
      if (function_exists('auth_login_cps_force_superadmin')) {
        $ok = auth_login_cps_force_superadmin($username, $pass, [
          'dni'      => EA_SUPERADMIN_DNI,
          'username' => EA_SUPERADMIN_USER,
        ]);
      } else {
        // Si no existe la función en login_cps.php, no podemos forzar sin romper seguridad.
        // Mantenemos el rechazo para no abrir backdoor.
        $ok = false;
      }
    }

    if ($ok) {
      // Si querés respetar next:
      // header('Location: ' . $next);
      // pero vos venías usando HOME fijo:
      header('Location: ' . $HOME_AFTER_LOGIN);
      exit;
    }

    $error = 'No tienes autorización para ingresar.';
  } catch (Throwable $e) {
    error_log("[EA][login] login.php error: " . $e->getMessage());
    $error = 'No tienes autorización para ingresar.';
  }
}

/* ===== Assets ===== */
$IMG_BASE = $APP_BASE . '/assets/img';

/**
 * Prioridades:
 * - Medallón (centro de card): login.jpg -> login.png -> ecmilm.png
 * - Icono (favicon + header + loader): login.jpg -> login.png -> ecmilm.png
 */
$ASSET_LOGIN_JPG_FS = __DIR__ . '/assets/img/login.jpg';
$ASSET_LOGIN_PNG_FS = __DIR__ . '/assets/img/login.png';
$ASSET_ECMILM_PNG_FS = __DIR__ . '/assets/img/ecmilm.png';

$ASSET_LOGIN_JPG_URL = $IMG_BASE . '/login.jpg';
$ASSET_LOGIN_PNG_URL = $IMG_BASE . '/login.png';
$ASSET_ECMILM_PNG_URL = $IMG_BASE . '/ecmilm.png';

/* Medallón (centro) */
if (is_file($ASSET_LOGIN_JPG_FS)) {
  $MEDALLON_URL = $ASSET_LOGIN_JPG_URL;
} elseif (is_file($ASSET_LOGIN_PNG_FS)) {
  $MEDALLON_URL = $ASSET_LOGIN_PNG_URL;
} else {
  $MEDALLON_URL = $ASSET_ECMILM_PNG_URL;
}

/* Icono general (favicon/header/loader) */
if (is_file($ASSET_LOGIN_JPG_FS)) {
  $ICON_URL = $ASSET_LOGIN_JPG_URL;
} elseif (is_file($ASSET_LOGIN_PNG_FS)) {
  $ICON_URL = $ASSET_LOGIN_PNG_URL;
} else {
  $ICON_URL = $ASSET_ECMILM_PNG_URL;
}

/* ✅ Fondo lateral: ecmilm2026.png (a ambos costados) */
$SIDE_FILE_PNG = __DIR__ . '/assets/img/ecmilm2026.png';
$SIDE_URL = is_file($SIDE_FILE_PNG) ? ($IMG_BASE . '/ecmilm2026.png') : '';

/* ✅ Logo GDE */
$GDE_FILE_PNG = __DIR__ . '/assets/img/logogde.png';
$GDE_FILE_SVG = __DIR__ . '/assets/img/logogde.svg';
if (is_file($GDE_FILE_PNG)) {
  $GDE_ICON_URL = $IMG_BASE . '/logogde.png';
} elseif (is_file($GDE_FILE_SVG)) {
  $GDE_ICON_URL = $IMG_BASE . '/logogde.svg';
} else {
  $GDE_ICON_URL = '';
}

/* ✅ Sol de Mayo (Intranet y Recuperación) */
$SOL_FILES = [
  __DIR__ . '/assets/img/sol_mayo.png' => $IMG_BASE . '/sol_mayo.png',
  __DIR__ . '/assets/img/sol_mayo.jpg' => $IMG_BASE . '/sol_mayo.jpg',
  __DIR__ . '/assets/img/solmayo.png'  => $IMG_BASE . '/solmayo.png',
  __DIR__ . '/assets/img/solmayo.jpg'  => $IMG_BASE . '/solmayo.jpg',
];
$SOL_MAYO_URL = '';
foreach ($SOL_FILES as $fs => $url) {
  if (is_file($fs)) { $SOL_MAYO_URL = $url; break; }
}

/* ✅ SITM3 icon */
$SITM3_FILES = [
  __DIR__ . '/assets/img/sitm3.png' => $IMG_BASE . '/sitm3.png',
  __DIR__ . '/assets/img/sitm3.jpg' => $IMG_BASE . '/sitm3.jpg',
  __DIR__ . '/assets/img/sitm3.svg' => $IMG_BASE . '/sitm3.svg',
];
$SITM3_ICON_URL = '';
foreach ($SITM3_FILES as $fs => $url) {
  if (is_file($fs)) { $SITM3_ICON_URL = $url; break; }
}

/* Links header */
$LINK_INTRANET = 'https://intranet.ejercito.mil.ar/';
$LINK_SITM3    = 'https://sitm3.ejercito.mil.ar/';
$LINK_PASS     = 'https://recuperacion.ejercito.mil.ar/';
$LINK_GDE      = 'https://cas.gde.gob.ar/';
$LINK_IG       = 'https://www.instagram.com/escuelamilitarbrc';

$CACHE_BUST = (string)@filemtime(__FILE__);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Escuela Militar de Montaña</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- ✅ Favicon -->
  <link rel="icon" href="<?= h($ICON_URL) ?>?v=<?= h($CACHE_BUST) ?>">
  <link rel="shortcut icon" href="<?= h($ICON_URL) ?>?v=<?= h($CACHE_BUST) ?>">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    /* ==========================
       THEME TOKENS (Light / Dark)
       ========================== */

    :root{
      --bg:#e9edf3;
      --bg2:#f5f6f9;
      --card1:#f9fafc;
      --card2:#f2f4fb;
      --text:#0f172a;
      --muted:#64748b;
      --line: rgba(15,23,42,.12);
      --shadow: 0 22px 60px rgba(2,6,23,.14);
      --primary:#1d4ed8;
      --primary2:#1e40af;
      --ring: rgba(29,78,216,.18);
      --header-bg: rgba(255,255,255,.84);
      --header-line: rgba(15,23,42,.10);
      --icon-bg: rgba(15,23,42,.03);
      --icon-br: rgba(15,23,42,.12);
      --icon-fg: rgba(15,23,42,.82);
      --input-bg:#ffffff;
      --input-br: rgba(15,23,42,.14);

      /* Loader */
      --loader-bg: radial-gradient(circle at 30% 20%, rgba(29,78,216,.16), transparent 45%),
                   radial-gradient(circle at 70% 10%, rgba(2,6,23,.10), transparent 40%),
                   linear-gradient(180deg, #f8fafc, #eef2f7);
      --loader-text:#0f172a;
      --loader-sub:#334155;
      --spin: rgba(29,78,216,.55);

      --header-h: 56px;
    }

    html[data-theme="dark"]{
      --bg:#0b1220;
      --bg2:#0f172a;
      --card1:#121b2e;
      --card2:#0f172a;
      --text:#e5e7eb;
      --muted:#94a3b8;
      --line: rgba(148,163,184,.22);
      --shadow: 0 26px 80px rgba(0,0,0,.50);
      --primary:#3b82f6;
      --primary2:#2563eb;
      --ring: rgba(59,130,246,.22);
      --header-bg: rgba(15,23,42,.72);
      --header-line: rgba(148,163,184,.18);
      --icon-bg: rgba(148,163,184,.08);
      --icon-br: rgba(148,163,184,.22);
      --icon-fg: rgba(226,232,240,.92);
      --input-bg:#0b1220;
      --input-br: rgba(148,163,184,.24);

      --loader-bg: radial-gradient(circle at 30% 20%, rgba(59,130,246,.18), transparent 45%),
                   radial-gradient(circle at 70% 10%, rgba(2,6,23,.45), transparent 40%),
                   linear-gradient(180deg, #0b1220, #0f172a);
      --loader-text:#e5e7eb;
      --loader-sub:#a8b2c3;
      --spin: rgba(59,130,246,.65);
    }

    html,body{ height:100%; overflow:hidden; }
    body{ margin:0; background: var(--bg); color: var(--text); }

    .bg-layer{
      position:fixed; inset:0; z-index:-3; pointer-events:none;
      background:
        radial-gradient(circle at 16% 18%, rgba(15,23,42,.10), transparent 54%),
        radial-gradient(circle at 84% 22%, rgba(15,23,42,.08), transparent 58%),
        linear-gradient(180deg, var(--bg2), var(--bg));
    }

    /* Arte lateral (centrado igual en ambos lados) */
    .side-art{ position: fixed; inset: 0; z-index: -2; pointer-events: none; }
    .side{
      position:absolute;
      top: var(--header-h);
      bottom: 0;
      width: min(380px, 28vw);
      display:flex;
      align-items:center;
      justify-content:center;
      opacity: .22;
      filter: grayscale(.05) contrast(1.02);
    }
    html[data-theme="dark"] .side{ opacity: .14; filter: grayscale(.15) contrast(1.05) brightness(.92); }

    .side img{
      max-width: 100%;
      max-height: 78vh;
      object-fit: contain;
      object-position: center center;
      user-select:none;
      -webkit-user-drag:none;
      display:block;
      margin: 0 auto;
    }
    .side-left{ left:0; padding-left: 18px; }
    .side-right{ right:0; padding-right: 18px; }
    .side-right img{ transform: scaleX(-1); }

    @media (max-width: 900px){ .side{ display:none; } }

    /* Header */
    .topbar{
      position:fixed; top:0; left:0; right:0; z-index:20;
      height: var(--header-h);
      display:flex; align-items:center;
      backdrop-filter: blur(10px);
      background: var(--header-bg);
      border-bottom: 1px solid var(--header-line);
    }
    .topbar-inner{
      width:100%;
      max-width: 1060px;
      margin:0 auto;
      padding:0 14px;
      display:flex;
      align-items:center;
      gap:12px;
    }
    .topbar-brand{ display:flex; align-items:center; gap:10px; min-width:240px; }
    .topbar-brand img{ width:26px; height:26px; object-fit:contain; border-radius:7px; filter: drop-shadow(0 6px 18px rgba(0,0,0,.18)); }
    .topbar-title{ color: var(--text); font-weight:900; letter-spacing:.02em; font-size:13px; line-height:1.1; }
    .topbar-sub{ color: color-mix(in oklab, var(--text) 65%, transparent); font-size:11px; margin-top:2px; }

    .topbar-links{ margin-left:auto; display:flex; align-items:center; gap:10px; }

    .icon-link{
      display:inline-flex; align-items:center; justify-content:center;
      width:36px; height:36px;
      border-radius:12px;
      border:1px solid var(--icon-br);
      background: var(--icon-bg);
      text-decoration:none;
      transition: transform .12s ease, opacity .12s ease;
    }
    .icon-link:hover{ transform: translateY(-1px); opacity:.98; }
    .icon-link svg{ width:18px; height:18px; display:block; color: var(--icon-fg); }
    .icon-link img{ width:18px; height:18px; object-fit:contain; display:block; filter: drop-shadow(0 6px 16px rgba(0,0,0,.18)); }

    /* Layout */
    .shell{
      height: calc(100vh - var(--header-h));
      margin-top: var(--header-h);
      display:flex; align-items:center; justify-content:center;
      padding: 10px 16px;
    }

    /* Card */
    .card-login{
      width:100%;
      max-width:480px;
      background: linear-gradient(180deg, var(--card1), var(--card2));
      border: 1px solid var(--line);
      border-radius:22px;
      box-shadow: var(--shadow);
      padding:16px 18px 14px;
      position:relative;
    }

    .medallon{
      display:flex; align-items:center; justify-content:center;
      margin: 8px 0 10px;
      position:relative;
    }
    .medallon::before{
      content:"";
      position:absolute;
      width: 260px;
      height: 170px;
      border-radius: 18px;
      background: radial-gradient(circle at 50% 40%, rgba(255,255,255,.22), transparent 62%);
      opacity: 0;
      pointer-events:none;
    }
    html[data-theme="dark"] .medallon::before{ opacity: .55; }

    .medallon img{
      width:220px;
      height:auto;
      max-width:100%;
      user-select:none;
      -webkit-user-drag:none;
      filter: drop-shadow(0 16px 34px rgba(0,0,0,.14));
    }
    html[data-theme="dark"] .medallon img{
      filter:
        drop-shadow(0 18px 38px rgba(0,0,0,.45))
        brightness(1.06)
        contrast(1.04);
    }

    .form-label{
      font-size:.70rem;
      text-transform:uppercase;
      letter-spacing:.10em;
      color: var(--muted);
      margin-bottom:.30rem;
      font-weight:800;
    }

    .form-control{
      border-radius:12px;
      padding:.58rem .78rem;
      border:1px solid var(--input-br);
      background: var(--input-bg);
      color: var(--text);
      transition: box-shadow .12s ease, border-color .12s ease, background .12s ease;
    }
    .form-control:hover{ border-color: color-mix(in oklab, var(--input-br) 65%, var(--text)); }
    .form-control:focus{
      border-color: color-mix(in oklab, var(--primary) 65%, #ffffff);
      box-shadow: 0 0 0 .25rem var(--ring);
    }

    .btn-primary{
      background: var(--primary);
      border-color: var(--primary);
      border-radius:12px;
      font-weight:800;
      padding:.62rem .90rem;
      letter-spacing:.02em;
    }
    .btn-primary:hover{ background: var(--primary2); border-color: var(--primary2); }

    .toggle-pass-btn{
      border-top-left-radius:0;
      border-bottom-left-radius:0;
      border-top-right-radius:12px;
      border-bottom-right-radius:12px;
      background: var(--input-bg);
      border: 1px solid var(--input-br);
      border-left:0;
      color: var(--text);
    }

    @media (max-width: 576px){
      .topbar-brand{ min-width:unset; }
      .topbar-sub{ display:none; }
      .card-login{ max-width:410px; padding:14px 14px 12px; border-radius:18px; }
      .medallon img{ width: 200px; }
      .shell{ padding: 8px 12px; }
    }

    /* Loader */
    body.is-loading{ overflow:hidden !important; }
    .loader-screen{
      position:fixed; inset:0; z-index:9999;
      display:none; opacity:0;
      flex-direction:column; align-items:center; justify-content:center;
      background: var(--loader-bg);
      color: var(--loader-text);
      text-align:center;
      transition:opacity .25s ease;
      pointer-events: all;
    }
    .loader-badge{
      width:118px; height:118px; border-radius:999px;
      display:flex; align-items:center; justify-content:center;
      background: color-mix(in oklab, var(--bg) 35%, transparent);
      box-shadow: 0 0 0 1px color-mix(in oklab, var(--line) 60%, transparent) inset, 0 26px 70px rgba(0,0,0,.18);
      margin-bottom:14px;
      position:relative;
      overflow:hidden;
    }
    .loader-badge img{
      width:100%; height:100%;
      object-fit:cover;
      border-radius:999px;
      animation: spinImg 1.15s linear infinite;
      will-change: transform;
    }
    @keyframes spinImg{ from{ transform:rotate(0deg); } to{ transform:rotate(360deg); } }
    .loader-badge::after{
      content:""; position:absolute; inset:-10px;
      border-radius:999px;
      border:3px solid var(--spin);
      border-left-color: transparent;
      border-bottom-color: color-mix(in oklab, var(--spin) 65%, transparent);
      animation: spinRing .95s linear infinite;
    }
    @keyframes spinRing{ from{ transform:rotate(0deg); } to{ transform:rotate(360deg); } }

    .loader-text{
      font-size:.92rem;
      letter-spacing:.10em;
      text-transform:uppercase;
      font-weight:900;
      margin:0;
    }
    .loader-sub{
      margin-top:6px;
      font-size:.85rem;
      color: var(--loader-sub);
    }
  </style>
</head>

<body>
  <div class="bg-layer"></div>

  <?php if ($SIDE_URL !== ''): ?>
    <div class="side-art" aria-hidden="true">
      <div class="side side-left">
        <img src="<?= h($SIDE_URL) ?>?v=<?= h($CACHE_BUST) ?>" alt="">
      </div>
      <div class="side side-right">
        <img src="<?= h($SIDE_URL) ?>?v=<?= h($CACHE_BUST) ?>" alt="">
      </div>
    </div>
  <?php endif; ?>

  <header class="topbar">
    <div class="topbar-inner">
      <div class="topbar-brand">
        <img src="<?= h($ICON_URL) ?>?v=<?= h($CACHE_BUST) ?>" alt="Login">
        <div>
          <div class="topbar-title">ESCUELA MILITAR DE MONTAÑA</div>
          <div class="topbar-sub">"LA MONTAÑA NOS UNE"</div>
        </div>
      </div>

      <nav class="topbar-links" aria-label="Accesos">
        <?php
          $SOL_HTML = ($SOL_MAYO_URL !== '')
            ? '<img src="'.h($SOL_MAYO_URL).'?v='.h($CACHE_BUST).'" alt="">'
            : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                 <circle cx="12" cy="12" r="10"></circle><path d="M2 12h20"></path>
                 <path d="M12 2a15 15 0 0 1 0 20"></path><path d="M12 2a15 15 0 0 0 0 20"></path>
               </svg>';

          $SITM3_HTML = ($SITM3_ICON_URL !== '')
            ? '<img src="'.h($SITM3_ICON_URL).'?v='.h($CACHE_BUST).'" alt="">'
            : $SOL_HTML;
        ?>

        <a class="icon-link" href="<?= h($LINK_INTRANET) ?>" target="_blank" rel="noopener noreferrer" title="Intranet" aria-label="Intranet">
          <?= $SOL_HTML ?>
        </a>

        <a class="icon-link" href="<?= h($LINK_SITM3) ?>" target="_blank" rel="noopener noreferrer" title="SITM3" aria-label="SITM3">
          <?= $SITM3_HTML ?>
        </a>

        <a class="icon-link" href="<?= h($LINK_PASS) ?>" target="_blank" rel="noopener noreferrer" title="Recuperación de contraseña" aria-label="Recuperación de contraseña">
          <?= $SOL_HTML ?>
        </a>

        <a class="icon-link" href="<?= h($LINK_GDE) ?>" target="_blank" rel="noopener noreferrer" title="GDE" aria-label="GDE">
          <?php if ($GDE_ICON_URL !== ''): ?>
            <img src="<?= h($GDE_ICON_URL) ?>?v=<?= h($CACHE_BUST) ?>" alt="">
          <?php else: ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
              <path d="M14 2v6h6"></path>
            </svg>
          <?php endif; ?>
        </a>

        <a class="icon-link" href="<?= h($LINK_IG) ?>" target="_blank" rel="noopener noreferrer" title="Instagram" aria-label="Instagram">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <rect x="3" y="3" width="18" height="18" rx="5" ry="5"></rect>
            <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path>
            <path d="M17.5 6.5h.01"></path>
          </svg>
        </a>
      </nav>
    </div>
  </header>

  <!-- Loader -->
  <div id="boot-loader" class="loader-screen" aria-hidden="true">
    <div class="loader-badge">
      <img src="<?= h($ICON_URL) ?>?v=<?= h($CACHE_BUST) ?>" alt="Login">
    </div>
    <p class="loader-text">Cargando...</p>
    <div class="loader-sub">Verificando credenciales</div>
  </div>

  <div class="position-fixed top-0 start-50 translate-middle-x p-3" style="z-index: 11000;">
    <div id="loginToast" class="toast border-0 shadow text-bg-success"
         role="alert" aria-live="assertive" aria-atomic="true"
         data-bs-delay="3500">
      <div class="d-flex">
        <div class="toast-body" id="loginToastMsg">Mensaje</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto"
                data-bs-dismiss="toast" aria-label="Cerrar"></button>
      </div>
    </div>
  </div>

  <main class="shell">
    <div class="card-login">
      <div class="medallon">
        <img src="<?= h($MEDALLON_URL) ?>?v=<?= h($CACHE_BUST) ?>" alt="Escuela Militar de Montaña">
      </div>

      <form id="login-form" method="post" action="<?= h((string)($_SERVER['PHP_SELF'] ?? '')) ?>" autocomplete="off">
        <?= csrf_input() ?>
        <input type="hidden" name="next" value="<?= h($next) ?>">

        <div class="mb-3">
          <label class="form-label">Usuario</label>
          <input name="username" type="text" class="form-control" autofocus required autocomplete="username">
        </div>

        <div class="mb-2">
          <label class="form-label">Contraseña</label>
          <div class="input-group">
            <input id="passwordField" name="password" type="password" class="form-control" required autocomplete="current-password">
            <button class="btn btn-outline-secondary toggle-pass-btn" type="button" id="togglePassBtn" aria-label="Mostrar u ocultar contraseña">
              <span id="togglePassIcon">👁️</span>
            </button>
          </div>
        </div>

        <div class="d-grid mt-3">
          <button id="btnSubmit" class="btn btn-primary" type="submit">Ingresar</button>
        </div>
      </form>
    </div>
  </main>

  <script>
    (function(){
      // Mostrar/ocultar password
      const passInput = document.getElementById('passwordField');
      const btn = document.getElementById('togglePassBtn');
      const icon = document.getElementById('togglePassIcon');
      if(btn && passInput && icon){
        btn.addEventListener('click', function(){
          const visible = passInput.type === 'text';
          passInput.type = visible ? 'password' : 'text';
          icon.textContent = visible ? '👁️' : '🔒';
        });
      }

      // Loader
      const loader = document.getElementById('boot-loader');
      const form = document.getElementById('login-form');
      const submitBtn = document.getElementById('btnSubmit');

      function hideLoaderHard(){
        if(!loader) return;
        loader.style.display = 'none';
        loader.style.opacity = '0';
        loader.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('is-loading');
        if(submitBtn) submitBtn.disabled = false;
      }
      function showLoader(){
        if(!loader) return;
        document.body.classList.add('is-loading');
        loader.style.display = 'flex';
        loader.setAttribute('aria-hidden', 'false');
        void loader.offsetWidth;
        loader.style.opacity = '1';
        if(submitBtn) submitBtn.disabled = true;
      }

      window.addEventListener('pageshow', function(){ hideLoaderHard(); });
      document.addEventListener('DOMContentLoaded', function(){ hideLoaderHard(); });

      if(form){
        form.addEventListener('submit', function(){
          if (typeof form.checkValidity === 'function' && !form.checkValidity()) return;
          showLoader();
        });
      }
    })();
  </script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <script>
  document.addEventListener('DOMContentLoaded', function(){
    const toastEl  = document.getElementById('loginToast');
    const toastMsg = document.getElementById('loginToastMsg');
    if (!toastEl || !toastMsg || !window.bootstrap) return;

    let show = false;

    <?php if(!empty($_GET['out'])): ?>
      toastMsg.textContent = "Sesión cerrada correctamente.";
      toastEl.classList.remove('text-bg-danger');
      toastEl.classList.add('text-bg-success');
      show = true;
    <?php endif; ?>

    <?php if(!empty($_GET['denied'])): ?>
      toastMsg.textContent = "No tienes autorización para ingresar.";
      toastEl.classList.remove('text-bg-success');
      toastEl.classList.add('text-bg-danger');
      show = true;
    <?php endif; ?>

    <?php if(!empty($error)): ?>
      toastMsg.textContent = <?= json_encode($error, JSON_UNESCAPED_UNICODE) ?>;
      toastEl.classList.remove('text-bg-success');
      toastEl.classList.add('text-bg-danger');
      show = true;
    <?php endif; ?>

    if (show) {
      const t = new bootstrap.Toast(toastEl);
      t.show();
    }
  });
  </script>
</body>
</html>