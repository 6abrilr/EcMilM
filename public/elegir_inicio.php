<?php
// public/elegir_inicio.php — Pantalla intermedia: elegir módulo
declare(strict_types=1);

require_once __DIR__ . '/../auth/bootstrap.php';
require_login();
require_once __DIR__ . '/../config/db.php';

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$user = function_exists('current_user') ? current_user() : null;

// Rol de app (como en usuarios.php)
$roleApp = $user['role_app'] ?? 'usuario';
$esAdmin = ($roleApp === 'admin');

/* ===== Assets ===== */
$PUBLIC_URL = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'])), '/');
$APP_URL    = rtrim(str_replace('\\','/', dirname($PUBLIC_URL)), '/');
$ASSETS_URL = ($APP_URL === '' ? '' : $APP_URL) . '/assets';
$IMG_BG     = $ASSETS_URL . '/img/fondo.png';
$ESCUDO     = $ASSETS_URL . '/img/escudo_bcom602.png';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Inicio · Módulos IGE · Batallón de Comunicaciones 602</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/theme-602.css">
<link rel="icon" type="image/png" href="../assets/img/bcom602.png">
<style>
  body{
    background: url("<?= e($IMG_BG) ?>") no-repeat center center fixed;
    background-size: cover;
    background-attachment: fixed;
    background-color:#020617;
    color:#e5e7eb;
    font-family:system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,sans-serif;
    margin:0; padding:0;
  }
  .page-wrap{ padding:18px; }
  .container-main{ max-width:1400px; margin:auto; }

  .panel{
    background:rgba(15,17,23,.94);
    border:1px solid rgba(148,163,184,.40);
    border-radius:18px;
    padding:18px 22px 22px;
    box-shadow:0 18px 40px rgba(0,0,0,.75), inset 0 1px 0 rgba(255,255,255,.05);
    backdrop-filter:blur(8px);
  }

  .panel-title{
    font-size:1.05rem;
    font-weight:800;
    margin-bottom:16px;
  }

  .gest-grid{
    display:flex;
    flex-wrap:wrap;
    gap:18px;
    justify-content:center;
  }

  .gest-card{
    flex:1 1 320px;
    max-width:500px;
    background:radial-gradient(circle at top left, rgba(34,197,94,.16), transparent 55%);
    background-color:#020617;
    border-radius:18px;
    border:1px solid rgba(148,163,184,.40);
    padding:22px 18px 20px;
    text-align:center;
    box-shadow:0 14px 30px rgba(0,0,0,.65);
  }

  .gest-card--blue{
    background:radial-gradient(circle at top left, rgba(56,189,248,.18), transparent 55%);
    background-color:#020617;
  }

  .gest-card--admin{
    background:radial-gradient(circle at top left, rgba(248,250,252,.18), transparent 55%);
    background-color:#020617;
    border-color:rgba(251,191,36,.7);
  }

  .gest-title{
    font-size:1rem;
    font-weight:800;
    margin-bottom:4px;
  }

  .gest-sub{
    font-size:.78rem;
    text-transform:uppercase;
    letter-spacing:.08em;
    color:#9ca3af;
    margin-bottom:10px;
  }

  .gest-text{
    font-size:.86rem;
    color:#cbd5f5;
    margin-bottom:16px;
  }

  .gest-btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:.35rem;
    padding:.55rem 1.4rem;
    border-radius:999px;
    border:none;
    font-size:.86rem;
    font-weight:800;
    text-decoration:none;
    background:#22c55e;
    color:#052e16;
    box-shadow:0 8px 22px rgba(22,163,74,.7);
  }
  .gest-btn:hover{
    background:#4ade80;
    color:#052e16;
  }

  .gest-btn--blue{
    background:#0ea5e9;
    color:#021827;
    box-shadow:0 8px 22px rgba(14,165,233,.7);
  }
  .gest-btn--blue:hover{
    background:#38bdf8;
    color:#021827;
  }

  .gest-btn--admin{
    background:#fbbf24;
    color:#78350f;
    box-shadow:0 8px 22px rgba(251,191,36,.7);
  }
  .gest-btn--admin:hover{
    background:#facc15;
    color:#78350f;
  }

  .brand-hero{
    padding-top:10px;
    padding-bottom:10px;
  }
  .brand-hero .hero-inner{
    align-items:center;
    display:flex;
  }
  .user-info{
    margin-left:auto;
    margin-right:17px;
    text-align:right;
    font-size:.85rem;
  }
</style>
</head>
<body>

<header class="brand-hero">
  <div class="hero-inner container-main">
    <img class="brand-logo" src="<?= e($ESCUDO) ?>" alt="Escudo 602">
    <div>
      <div class="brand-title">Batallón de Comunicaciones 602</div>
      <div class="brand-sub">“Hogar de las Comunicaciones Fijas del Ejército”</div>
    </div>
    <?php if ($user): ?>
      <div class="user-info">
        <div><strong><?= e($user['rank'] ?? '') ?> <?= e($user['full_name'] ?? '') ?></strong></div>
        <?php if (!empty($user['unit'])): ?>
          <div class="text-muted"><?= e($user['unit']) ?></div>
        <?php endif; ?>
        <div class="mt-1">
          <a href="../logout.php" class="btn btn-success btn-sm" style="font-weight:700; padding:.35rem .9rem;">
            Cerrar sesión
          </a>
        </div>
      </div>
    <?php endif; ?>
  </div>
</header>

<div class="page-wrap">
  <div class="container-main">
    <div class="panel">
      <div class="panel-title">Elegir módulo de trabajo</div>

      <div class="gest-grid">

        <div class="gest-card">
          <div class="gest-title">Inspecciones IGE</div>
          <div class="gest-sub">Presentación y control</div>
          <div class="gest-text">
            Acceso al dashboard de inspecciones, listas de control y visitas de Estado Mayor.
          </div>
          <a href="index.php" class="gest-btn">
            Ir a Inspecciones
          </a>
        </div>

        <div class="gest-card gest-card--blue">
          <div class="gest-title">Áreas (S-1 a S-5)</div>
          <div class="gest-sub">Ejes por área</div>
          <div class="gest-text">
            Acceso al módulo por áreas S-1, S-2, S-3, S-4 y S-5.
          </div>
          <a href="areas.php" class="gest-btn gest-btn--blue">
            Ir a Áreas
          </a>
        </div>

        <?php if ($esAdmin): ?>
        <div class="gest-card gest-card--admin">
          <div class="gest-title">Gestión de usuarios</div>
          <div class="gest-sub">Solo administradores</div>
          <div class="gest-text">
            Configuración de permisos locales, áreas de acceso y roles de aplicación para todos los usuarios.
          </div>
          <a href="gestiones.php" class="gest-btn gest-btn--admin">
            Ir a Usuarios
          </a>
        </div>
        <?php endif; ?>

      </div>
    </div>
  </div>
</div>

</body>
</html>
