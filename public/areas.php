<?php
// public/areas.php — Menú de áreas S-1 a S-5 con filtro por roles_locales
declare(strict_types=1);

// ========= MODO DEMO LOCAL =========
// true  -> no pide login CPS y muestra todas las áreas
// false -> comportamiento normal (con require_login y filtro por roles_locales)
$OFFLINE_MODE = false; // <<< PRODUCCIÓN ACTIVADA
// ===================================

require_once __DIR__ . '/../auth/bootstrap.php';

if (!$OFFLINE_MODE) {
    require_login(); // pide login CPS
}

require_once __DIR__ . '/../config/db.php';

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Usuario actual de CPS
$user    = function_exists('current_user') ? current_user() : null;
$roleApp = $user['role_app'] ?? 'usuario';

// Áreas permitidas según roles_locales.areas_acceso
$areasUser = [];
$canSeeAll = false;

// Detectamos DNI (ajustado a cómo lo guarda tu bootstrap CPS)
$dniUser = trim((string)($user['dni'] ?? ''));

if (!$OFFLINE_MODE && $dniUser !== '') {
    $st = $pdo->prepare("SELECT areas_acceso, rol_app FROM roles_locales WHERE dni = ? LIMIT 1");
    $st->execute([$dniUser]);
    if ($rowLocal = $st->fetch(PDO::FETCH_ASSOC)) {
        $areasUser = json_decode($rowLocal['areas_acceso'] ?? '[]', true);
        if (!is_array($areasUser)) {
            $areasUser = [];
        }

        $roleLocal = $rowLocal['rol_app'] ?? $roleApp;

        // Admin con GRAL puede ver todas las áreas
        if ($roleLocal === 'admin' && in_array('GRAL', $areasUser, true)) {
            $canSeeAll = true;
        }
    }
}

// Definición de tarjetas por área
$CARDS = [
    'S1' => [
        'title' => 'S-1 · Personal',
        'sub'   => 'Administración de personal',
        'text'  => 'Procesos, documentación y control relacionados con el personal.',
        'href'  => 'areas_s1.php',
        'variant' => 'green',
    ],
    'S2' => [
        'title' => 'S-2 · Inteligencia',
        'sub'   => 'Información y análisis',
        'text'  => 'Productos de inteligencia, análisis y seguimiento específico.',
        'href'  => 'areas_s2.php',
        'variant' => 'blue',
    ],
    'S3' => [
        'title' => 'S-3 · Operaciones',
        'sub'   => 'Planificación y conducción',
        'text'  => 'Educación operacional, adiestramiento y tiro.',
        'href'  => 'areas_s3.php',
        'variant' => 'green',
    ],
    'S4' => [
        'title' => 'S-4 · Material',
        'sub'   => 'Logística y medios',
        'text'  => 'Gestión de materiales, recursos y apoyo logístico.',
        'href'  => 'areas_s4.php',
        'variant' => 'blue',
    ],
    'S5' => [
        'title' => 'S-5 · Presupuesto',
        'sub'   => 'Recursos financieros',
        'text'  => 'Programación presupuestaria y control de recursos.',
        'href'  => 'areas_s5.php',
        'variant' => 'green',
    ],
];

// Determinar qué áreas mostrar
$visibleAreas = [];

if ($OFFLINE_MODE) {
    $visibleAreas = array_keys($CARDS);
} else {
    if ($canSeeAll) {
        $visibleAreas = array_keys($CARDS);
    } else {
        foreach ($CARDS as $code => $_cfg) {
            if (in_array($code, $areasUser, true)) {
                $visibleAreas[] = $code;
            }
        }
    }
}

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
<title>Áreas IGE · Batallón de Comunicaciones 602</title>
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
    font-size:1.1rem;
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
    flex:1 1 260px;
    max-width:360px;
    background:radial-gradient(circle at top left, rgba(56,189,248,.18), transparent 55%);
    background-color:#020617;
    border-radius:18px;
    border:1px solid rgba(148,163,184,.40);
    padding:22px 18px 20px;
    text-align:center;
    box-shadow:0 14px 30px rgba(0,0,0,.65);
  }

  .gest-card--green{
    background:radial-gradient(circle at top left, rgba(34,197,94,.18), transparent 55%);
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
    background:#0ea5e9;
    color:#021827;
    box-shadow:0 8px 22px rgba(14,165,233,.7);
  }
  .gest-btn:hover{
    background:#38bdf8;
    color:#021827;
  }

  .brand-hero{
    padding-top:10px;
    padding-bottom:10px;
  }
  .brand-hero .hero-inner{
    align-items:center;
    display:flex;
  }

  .header-back{
    margin-left:auto;
    margin-right:17px;
    margin-top:4px;
  }

  .muted{
    color:#9ca3af;
    font-size:.9rem;
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
    <div class="header-back">
      <a href="elegir_inicio.php" class="btn btn-success btn-sm" style="font-weight:700; padding:.35rem .9rem;">
        Volver a inicio
      </a>
    </div>
  </div>
</header>

<div class="page-wrap">
  <div class="container-main">
    <div class="panel">
      <div class="panel-title">Áreas de trabajo (S-1 a S-5)</div>

      <?php if (empty($visibleAreas)): ?>
        <p class="muted">
          No tenés áreas asignadas para este módulo. Consultá con el administrador.
        </p>
      <?php else: ?>
        <div class="gest-grid">
          <?php foreach ($visibleAreas as $code): ?>
            <?php
              if (!isset($CARDS[$code])) continue;
              $cfg = $CARDS[$code];
              $cardClass = 'gest-card';
              if (($cfg['variant'] ?? '') === 'green') {
                  $cardClass .= ' gest-card--green';
              }
            ?>
            <div class="<?= e($cardClass) ?>">
              <div class="gest-title"><?= e($cfg['title']) ?></div>
              <div class="gest-sub"><?= e($cfg['sub']) ?></div>
              <div class="gest-text"><?= e($cfg['text']) ?></div>
              <a href="<?= e($cfg['href']) ?>" class="gest-btn">
                Entrar a <?= e($code) ?>
              </a>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    </div>
  </div>
</div>

</body>
</html>
