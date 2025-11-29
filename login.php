<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/bootstrap.php'; // session_start(), csrf_input(), csrf_verify()
require_once __DIR__ . '/login_cps.php';

if (!function_exists('h')) {
    function h(string $s): string {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

/*
 * Calcula la base de la app y el home post-login.
 * Ej: si la app está en /inspeccion/login.php → base = /inspeccion
 * HOME_AFTER_LOGIN = /inspeccion/public/elegir_inicio.php
 */
$APP_BASE = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
if ($APP_BASE === '/' || $APP_BASE === '\\') {
    $APP_BASE = '';
}

// AHORA el home post-login es la página intermedia
$HOME_AFTER_LOGIN = $APP_BASE . '/public/elegir_inicio.php';

// Sanitizar "next"
$next = $_GET['next'] ?? $_POST['next'] ?? $HOME_AFTER_LOGIN;
if (!is_string($next) || !preg_match('#^/[^:]*$#', $next)) {
    $next = $HOME_AFTER_LOGIN;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $username = trim((string)($_POST['username'] ?? ''));
    $pass     = (string)($_POST['password'] ?? '');

    try {
        $ok = auth_login_cps($username, $pass);

        if ($ok) {
            header('Location: ' . $HOME_AFTER_LOGIN);
            exit;
        }

        $error = 'No tienes autorización para ingresar.';
    } catch (Exception $e) {
        $error = 'No tienes autorización para ingresar.';
    }
}

/*
 * Rutas de imágenes
 * C:\xampp\htdocs\inspeccion\assets\img → /inspeccion/assets/img
 */
$IMG_BASE   = $APP_BASE . '/assets/img';
$ESCUDO_URL = $IMG_BASE . '/bcom602.png';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Iniciar sesión – B Com 602</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Favicon usando el escudo correcto -->
  <link rel="icon" type="image/png" href="<?= h($ESCUDO_URL) ?>">
  <link rel="shortcut icon" href="<?= h($ESCUDO_URL) ?>">

  <!-- Bootstrap CDN -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    :root{
      --ink:#0b1326; --deep:#0a1830; --glow:#1e7bdc;
      --mesh-opacity:.70; --glow-strength:.55;
      --card-bg:#fff; --card-border:#e9ecef; --shadow:0 8px 24px rgba(33,37,41,.06);
      --primary:#0d6efd; --primary-2:#0b5ed7; --ring:#86b7fe;
      --container-max: 1280px;
    }
    html,body{ height:100%; }
    body{ margin:0; color:#212529; background:#000; }

    /* === FONDO PRINCIPAL: usa fondo.png === */
    .page-bg{
      position:fixed;
      inset:0;
      z-index:-2;
      pointer-events:none;
      background:
        linear-gradient(160deg, rgba(0,0,0,.85) 0%, rgba(0,0,0,.65) 55%, rgba(0,0,0,.85) 100%),
        url("<?= h($IMG_BASE) ?>/fondo.png") center/cover no-repeat;
      background-attachment: fixed, fixed;
      filter:saturate(1.05);
    }
    .page-bg::before{
      content:"";
      position:absolute;
      inset:0;
      z-index:-1;
      opacity:.18;
      background-image:
        radial-gradient(1.4px 1.4px at 18% 22%, #9cd1ff 20%, transparent 60%),
        radial-gradient(1.2px 1.2px at 63% 48%, #b7ddff 20%, transparent 60%),
        radial-gradient(1.2px 1.2px at 82% 70%, #b7ddff 20%, transparent 60%),
        radial-gradient(1.6px 1.6px at 34% 76%, #cbe8ff 20%, transparent 60%),
        radial-gradient(1.1px 1.1px at 72% 16%, #a7d6ff 20%, transparent 60%);
      background-repeat:no-repeat;
      background-size: 1200px 800px, 1400px 900px, 1100px 900px, 1400px 1000px, 1300px 800px;
      background-position: 0 0, 30% 40%, 80% 60%, 10% 90%, 70% 10%;
    }

    .mesh{
      position:fixed; right:-220px; top:-140px; width:1400px; height:900px; z-index:-1; opacity:.70;
      background:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='1400' height='900' viewBox='0 0 1400 900'%3E%3Cg fill='none' stroke='%23a6c9ff' stroke-opacity='.40' stroke-width='1.1'%3E%3Cpath d='M860 60 L1120 180 L980 300 L1260 360 L1360 240'/%3E%3Cpath d='M1020 520 L1240 430 L1360 580'/%3E%3Cpath d='M900 240 L1120 360 L1280 260'/%3E%3Cpath d='M940 720 L1200 600 L1340 740'/%3E%3C/g%3E%3Cg fill='%23e9f4ff' fill-opacity='.95'%3E%3Ccircle cx='860' cy='60' r='3'/%3E%3Ccircle cx='1120' cy='180' r='2.5'/%3E%3Ccircle cx='980' cy='300' r='2.5'/%3E%3Ccircle cx='1260' cy='360' r='3'/%3E%3Ccircle cx='1360' cy='240' r='2.5'/%3E%3Ccircle cx='1020' cy='520' r='2.6'/%3E%3Ccircle cx='1240' cy='430' r='2.4'/%3E%3Ccircle cx='1360' cy='580' r='2.6'/%3E%3Ccircle cx='900' cy='240' r='2.5'/%3E%3Ccircle cx='1120' cy='360' r='2.4'/%3E%3Ccircle cx='1280' cy='260' r='2.8'/%3E%3Ccircle cx='940' cy='720' r='2.4'/%3E%3Ccircle cx='1200' cy='600' r='2.8'/%3E%3Ccircle cx='1340' cy='740' r='2.5'/%3E%3C/g%3E%3C/svg%3E") no-repeat center/contain;
      mix-blend-mode:screen; filter:drop-shadow(0 0 35px rgba(124,196,255,.25)); pointer-events:none;
    }
    .mesh.mesh--left{ left:-260px; top:180px; right:auto; transform:scaleX(-1) rotate(3deg); }

    .brand-hero{ position:relative; padding:28px 0 70px; color:#e9f2ff; isolation:isolate; }
    .hero-inner{ display:flex; align-items:center; gap:14px; }
    .brand-logo{ width:56px; height:56px; object-fit:contain; flex:0 0 auto; filter:drop-shadow(0 2px 10px rgba(124,196,255,.30)); }
    .brand-title{ font-weight:800; letter-spacing:.4px; font-size:28px; line-height:1.1; text-shadow:0 2px 16px rgba(30,123,220,.45); }
    .brand-sub{ font-size:16px; opacity:.9; border-top:2px solid rgba(124,196,255,.35); display:inline-block; padding-top:4px; margin-top:2px; }

    .login-wrap{ margin-top:-30px; display:flex; align-items:flex-start; justify-content:center; }
    .card{ border-radius:14px; border:1px solid #e9ecef; box-shadow:0 8px 24px rgba(33,37,41,.06); background:#fff; }
    .card .card-body{ padding:20px; }
    .form-label{ font-size:.9rem; text-transform:uppercase; letter-spacing:.04em; color:#495057; }
    .btn{ border-radius:10px; }
    .btn-primary{ background:#0d6efd; border-color:#0d6efd; }
    .btn-primary:hover{ background:#0b5ed7; border-color:#0b5ed7; }
    @media (min-width:1200px){ .container{ max-width:1280px !important; } }
    .toggle-pass-btn {
      border-top-left-radius:0;
      border-bottom-left-radius:0;
    }

    /* ==== LOADER INICIAL ==== */
    .loader-screen{
      position:fixed;
      inset:0;
      z-index:9999;
      display:none;
      opacity:0;
      flex-direction:column;
      align-items:center;
      justify-content:center;
      background:#020617;
      color:#e5e7eb;
      text-align:center;
      transition:opacity .3s ease;
    }
    .loader-text{
      margin-bottom:1.5rem;
      font-size:.9rem;
      letter-spacing:.08em;
      text-transform:uppercase;
      color:#cbd5f5;
    }
    .loader-orbit-wrapper{
      position:relative;
      width:260px;
      height:260px;
    }
    @media (min-width:768px){
      .loader-orbit-wrapper{
        width:300px;
        height:300px;
      }
    }

    .loader-orbit-spin{
      position:absolute;
      inset:0;
      animation: orbit-spin 16s linear infinite;
      transform-origin:center center;
    }
    @keyframes orbit-spin{
      from{ transform:rotate(0deg); }
      to  { transform:rotate(360deg); }
    }

    .loader-orbit-item{
      position:absolute;
      display:flex;
      align-items:center;
      justify-content:center;
    }
    .loader-orbit-item img{
      object-fit:contain;
      transform:scale(1.15);
    }

    .loader-orbit-pulse{
      animation:orbit-pulse 2.2s ease-in-out infinite;
    }
    @keyframes orbit-pulse{
      0%,100%{ transform:scale(0.9); opacity:0.6; }
      50%   { transform:scale(1.05); opacity:1; }
    }

    .loader-center{
      position:absolute;
      inset:0;
      display:flex;
      align-items:center;
      justify-content:center;
    }
    .loader-center-inner{
      position:relative;
    }
    .loader-center-halo{
      position:absolute;
      inset:0;
      border-radius:999px;
      filter:blur(20px);
      background:rgba(59,130,246,.35);
      animation:pulse-center 2.2s ease-in-out infinite;
    }
    @keyframes pulse-center{
      0%,100%{ opacity:.5; }
      50%   { opacity:1; }
    }
    .loader-center-inner img{
      position:relative;
      width:120px;
      height:140px;
      object-fit:contain;
      filter:drop-shadow(0 0 22px rgba(59,130,246,0.5));
    }
    @media (min-width:768px){
      .loader-center-inner img{
        width:130px;
        height:150px;
      }
    }
  </style>
</head>
<body>

  <!-- LOADER: se muestra cuando se envía el formulario -->
  <div id="boot-loader" class="loader-screen">
    <p class="loader-text">Cargando...</p>

    <div class="loader-orbit-wrapper">

      <!-- Logos que giran alrededor -->
      <div class="loader-orbit-spin">

        <!-- ARRIBA -->
        <div class="loader-orbit-item loader-orbit-pulse"
             style="top:2%; left:40%; transform:translate(-50%,-50%);">
          <img src="<?= h($IMG_BASE) ?>/Imagen1.png" alt="Logo 1" width="90" height="90">
        </div>

        <!-- ARRIBA-DERECHA -->
        <div class="loader-orbit-item loader-orbit-pulse"
             style="top:22%; right:0%; transform:translate(50%,-50%);">
          <img src="<?= h($IMG_BASE) ?>/Imagen2.png" alt="Logo 2" width="90" height="90">
        </div>

        <!-- ABAJO-DERECHA -->
        <div class="loader-orbit-item loader-orbit-pulse"
             style="bottom:4%; right:12%; transform:translate(50%,50%);">
          <img src="<?= h($IMG_BASE) ?>/Imagen3.png" alt="Logo 3" width="90" height="90">
        </div>

        <!-- ABAJO-IZQUIERDA -->
        <div class="loader-orbit-item loader-orbit-pulse"
             style="bottom:4%; left:12%; transform:translate(-50%,50%);">
          <img src="<?= h($IMG_BASE) ?>/Imagen4.png" alt="Logo 4" width="90" height="90">
        </div>

        <!-- ARRIBA-IZQUIERDA (Imagen5 más chica para que quede pareja) -->
        <div class="loader-orbit-item loader-orbit-pulse"
             style="top:22%; left:0%; transform:translate(-40%,-90%);">
          <img src="<?= h($IMG_BASE) ?>/Imagen5.png" alt="Logo 5" width="72" height="72">
        </div>

      </div>

      <!-- Escudo central -->
      <div class="loader-center">
        <div class="loader-center-inner">
          <div class="loader-center-halo"></div>
          <img src="<?= h($IMG_BASE) ?>/bcom602.png"
               alt="Batallón de Comunicaciones 602">
        </div>
      </div>

    </div>
  </div>

  <!-- Fondo -->
  <div class="page-bg"></div>
  <span class="mesh"></span>
  <span class="mesh mesh--left"></span>

  <header class="brand-hero">
    <div class="hero-inner container">
      <img class="brand-logo" src="<?= h($ESCUDO_URL) ?>" alt="Escudo 602">
      <div>
        <div class="brand-title">Batallón de Comunicaciones 602</div>
        <div class="brand-sub">“Hogar de las Comunicaciones fijas del Ejército”</div>
      </div>
    </div>
  </header>

  <!-- Toast de notificaciones -->
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

  <main class="login-wrap">
    <div class="container" style="max-width:520px">
      <div class="card">
        <div class="card-body">
          <h4 class="mb-3">Iniciar sesión</h4>

          <!-- Formulario -->
          <form id="login-form" method="post" action="<?= h($_SERVER['PHP_SELF']) ?>">
            <?= csrf_input() ?>
            <input type="hidden" name="next" value="<?= h($next) ?>">

            <div class="mb-3">
              <label class="form-label">Usuario Ejército</label>
              <input name="username" type="text" class="form-control" autofocus required>
            </div>

            <div class="mb-3">
              <label class="form-label">Contraseña</label>
              <div class="input-group">
                <input id="passwordField" name="password" type="password" class="form-control" required>
                <button class="btn btn-outline-secondary toggle-pass-btn" type="button" id="togglePassBtn" aria-label="Mostrar u ocultar contraseña">
                  <span id="togglePassIcon">👁️</span>
                </button>
              </div>
            </div>

            <div class="d-flex align-items-center justify-content-between">
              <button class="btn btn-primary" type="submit">Entrar</button>
            </div>
          </form>

        </div>
      </div>
    </div>
  </main>

  <script>
    (function(){
      // Mostrar / ocultar contraseña
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

      // Mostrar loader cuando se envía el formulario
      const form = document.getElementById('login-form');
      const loader = document.getElementById('boot-loader');
      if(form && loader){
        form.addEventListener('submit', function(){
          loader.style.display = 'flex';
          void loader.offsetWidth;
          loader.style.opacity = '1';
        });
      }
    })();
  </script>

  <!-- Bootstrap JS (para Toast) -->
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
