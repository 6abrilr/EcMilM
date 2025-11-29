<?php
// public/areas_s1.php — Área S-1 Personal
declare(strict_types=1);

// ========= MODO PRODUCCIÓN =========
$OFFLINE_MODE = false;
// ===================================

require_once __DIR__ . '/../auth/bootstrap.php';
if (!$OFFLINE_MODE) {
    require_login();
}
require_once __DIR__ . '/../config/db.php';

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$user = function_exists('current_user') ? current_user() : null;

/* ===== Assets ===== */
$PUBLIC_URL = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'])), '/');
$APP_URL    = rtrim(str_replace('\\','/', dirname($PUBLIC_URL)), '/');
$ASSETS_URL = ($APP_URL === '' ? '' : $APP_URL) . '/assets';
$IMG_BG     = $ASSETS_URL . '/img/fondo.png';
$ESCUDO     = $ASSETS_URL . '/img/escudo_bcom602.png';

/*
 * Resumen S-1 (MODO DEMO)
 * Después podemos reemplazar estos valores por consultas reales a:
 *  - personal_bcom602
 *  - otros módulos de S-1
 */
$totalPersonal      = 200;  // personal total
$personalActivo     = 180;  // en servicio
$personalLicencia   = 15;   // en LIC
$personalBaja       = 5;    // baja
$personalOtros      = $totalPersonal - ($personalActivo + $personalLicencia + $personalBaja);

$porcActivo   = $totalPersonal > 0 ? round($personalActivo   * 100 / $totalPersonal, 1) : 0;
$porcLicencia = $totalPersonal > 0 ? round($personalLicencia * 100 / $totalPersonal, 1) : 0;
$porcBaja     = $totalPersonal > 0 ? round($personalBaja     * 100 / $totalPersonal, 1) : 0;

$porcGlobal   = $totalPersonal > 0 ? round($personalActivo * 100 / $totalPersonal, 1) : 0;
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>S-1 Personal · Batallón de Comunicaciones 602</title>
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
  }

  .panel-title{
    font-size:1.05rem;
    font-weight:800;
    margin-bottom:8px;
  }

  .panel-sub{
    font-size:.86rem;
    color:#cbd5f5;
    margin-bottom:18px;
  }

  /* ===== Layout 2 columnas ===== */
  .layout-s-row{
    display:flex;
    flex-wrap:wrap;
    gap:18px;
  }
  .layout-s-sidebar{
    flex:0 0 260px;
    max-width:320px;
  }
  .layout-s-main{
    flex:1 1 0;
    min-width:0;
  }
  @media (max-width: 768px){
    .layout-s-sidebar,
    .layout-s-main{
      flex:1 1 100%;
      max-width:100%;
    }
  }

  /* ===== Sidebar / accordion ===== */
  .s-sidebar-box{
    background:rgba(15,23,42,.95);
    border-radius:16px;
    border:1px solid rgba(148,163,184,.45);
    padding:14px 14px 10px;
    box-shadow:0 10px 28px rgba(0,0,0,.75);
  }
  .s-sidebar-title{
    font-size:.88rem;
    font-weight:700;
    letter-spacing:.05em;
    text-transform:uppercase;
    color:#9ca3af;
    margin-bottom:10px;
  }

  .accordion-s .accordion-item{
    background:transparent;
    border:none;
    border-radius:12px;
    margin-bottom:6px;
    overflow:hidden;
  }
  .accordion-s .accordion-button{
    background:radial-gradient(circle at left, rgba(34,197,94,.35), transparent 60%);
    border:none;
    color:#e5e7eb;
    font-size:.86rem;
    font-weight:700;
    padding:.55rem .75rem;
    box-shadow:0 6px 14px rgba(0,0,0,.65);
  }
  .accordion-s .accordion-button:not(.collapsed){
    background:radial-gradient(circle at left, rgba(34,197,94,.5), transparent 70%);
    color:#ecfdf5;
  }
  .accordion-s .accordion-body{
    background:rgba(15,23,42,.96);
    font-size:.84rem;
    color:#cbd5f5;
    border-top:1px solid rgba(148,163,184,.35);
  }

  .gest-btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:.35rem;
    padding:.45rem 1.3rem;
    border-radius:999px;
    border:none;
    font-size:.82rem;
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

  /* ===== Header / botón volver ===== */
  .brand-hero{
    padding-top:10px;
    padding-bottom:10px;
  }
  .brand-hero .hero-inner{
    align-items:center;
    display:flex;
    justify-content:space-between;
    gap:12px;
  }
  .header-back{
    margin-left:auto;
    margin-right:20px;
    margin-top:4px;
    display:flex;
    gap:8px;
  }

  .brand-title{
    font-weight:800;
    font-size:1rem;
  }
  .brand-sub{
    font-size:.8rem;
    color:#9ca3af;
  }

  .s-main-text{
    font-size:.9rem;
    color:#cbd5f5;
  }

  /* ===== KPIs centro S-1 ===== */
  .s-kpi-grid{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
    margin-top:10px;
  }
  .s-kpi-card{
    flex:1 1 180px;
    min-width:160px;
    background:rgba(15,23,42,.96);
    border-radius:14px;
    border:1px solid rgba(148,163,184,.45);
    padding:8px 10px;
    font-size:.78rem;
  }
  .s-kpi-title{
    text-transform:uppercase;
    letter-spacing:.06em;
    color:#9ca3af;
    font-weight:700;
    margin-bottom:4px;
  }
  .s-kpi-main{
    font-size:1rem;
    font-weight:800;
  }
  .s-kpi-sub{
    font-size:.78rem;
    color:#cbd5f5;
  }

  .progress{
    background:rgba(15,23,42,.9);
  }

  /* ===== Donut / gráfico de torta ===== */
  .s-pie-wrapper{
    display:flex;
    justify-content:center;
    align-items:center;
    padding:8px 0;
  }
  .s-pie{
    width:220px;
    aspect-ratio:1 / 1;
    border-radius:50%;
    position:relative;
    box-shadow:0 16px 35px rgba(0,0,0,.9);
  }
  .s-pie-inner{
    position:absolute;
    inset:20px;
    border-radius:50%;
    background:rgba(15,23,42,.98);
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    text-align:center;
  }
  .s-pie-perc{
    font-size:1.6rem;
    font-weight:800;
  }
  .s-pie-label{
    font-size:.75rem;
    color:#9ca3af;
    text-transform:uppercase;
    letter-spacing:.09em;
    margin-top:4px;
  }

  @media (max-width: 768px){
    .s-pie-wrapper{ margin-top:12px; }
  }
</style>
</head>
<body>

<header class="brand-hero">
  <div class="hero-inner container-main">
    <div class="d-flex align-items-center gap-3">
      <img class="brand-logo" src="<?= e($ESCUDO) ?>" alt="Escudo 602" style="height:52px; width:auto;">
      <div>
        <div class="brand-title">Batallón de Comunicaciones 602</div>
        <div class="brand-sub">“Hogar de las Comunicaciones Fijas del Ejército”</div>
      </div>
    </div>
    <div class="header-back">
      <a href="areas.php" class="btn btn-secondary btn-sm" style="font-weight:600; padding:.35rem .9rem;">
        Volver a Áreas
      </a>
    </div>
  </div>
</header>

<div class="page-wrap">
  <div class="container-main">
    <div class="panel">
      <div class="panel-title">Área S-1 · Personal</div>
      <div class="panel-sub">
        Seleccioná el módulo correspondiente. Este resumen muestra, en forma sintética,
        la situación del personal del Batallón (en servicio, en licencia, baja, etc.).
      </div>

      <div class="layout-s-row">
        <!-- Sidebar izquierdo desplegable -->
        <aside class="layout-s-sidebar">
          <div class="s-sidebar-box">
            <div class="s-sidebar-title">Módulos S-1</div>

            <div class="accordion accordion-s" id="accordionS1">

              <div class="accordion-item">
                <h2 class="accordion-header" id="s1-h-personal">
                  <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#s1-personal" aria-expanded="true" aria-controls="s1-personal">
                    Personal del B Com 602
                  </button>
                </h2>
                <div id="s1-personal" class="accordion-collapse collapse show" aria-labelledby="s1-h-personal" data-bs-parent="#accordionS1">
                  <div class="accordion-body">
                    Base de datos centralizada del personal (grado, arma, destino, situación, etc.).
                    <div class="mt-2">
                      <!-- después apuntamos al CRUD real de personal -->
                      <a href="s1_personal.php" class="gest-btn">Entrar</a>
                    </div>
                  </div>
                </div>
              </div>

              <div class="accordion-item">
                <h2 class="accordion-header" id="s1-h-roles">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#s1-roles" aria-expanded="false" aria-controls="s1-roles">
                    Rol de combate
                  </button>
                </h2>
                <div id="s1-roles" class="accordion-collapse collapse" aria-labelledby="s1-h-roles" data-bs-parent="#accordionS1">
                  <div class="accordion-body">
                    Gestión de rol de combate.
                    <div class="mt-2">
                      <a href="rol_combate.php" class="gest-btn">Entrar</a>
                    </div>
                  </div>
                </div>
              </div>

              <div class="accordion-item">
                <h2 class="accordion-header" id="s1-h-doc">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#s1-doc" aria-expanded="false" aria-controls="s1-doc">
                    Documentación de personal
                  </button>
                </h2>
                <div id="s1-doc" class="accordion-collapse collapse" aria-labelledby="s1-h-doc" data-bs-parent="#accordionS1">
                  <div class="accordion-body">
                    Vinculación de legajos, GEDO, certificados médicos y otra documentación.
                    <div class="mt-2">
                      <a href="s1_documentos.php" class="gest-btn">Entrar</a>
                    </div>
                  </div>
                </div>
              </div>

              <div class="accordion-item">
                <h2 class="accordion-header" id="s1-h-estad">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#s1-estad" aria-expanded="false" aria-controls="s1-estad">
                    Estadísticas y reportes
                  </button>
                </h2>
                <div id="s1-estad" class="accordion-collapse collapse" aria-labelledby="s1-h-estad" data-bs-parent="#accordionS1">
                  <div class="accordion-body">
                    Tableros de control y reportes de personal (altas, bajas, LIC, etc.).
                    <div class="mt-2">
                      <a href="s1_estadisticas.php" class="gest-btn">Entrar</a>
                    </div>
                  </div>
                </div>
              </div>

            </div><!-- /accordion -->
          </div>
        </aside>

        <!-- Columna principal derecha: resumen + gráfico -->
        <section class="layout-s-main">
          <div class="row g-3 align-items-center">
            <div class="col-md-7">
              <div class="s-main-text">
                <p>
                  Este panel integra, en forma consolidada, la situación del <strong>personal
                  del Batallón de Comunicaciones 602</strong> según su condición de revista:
                  en servicio, en licencia, baja u otras situaciones.
                </p>
                <p>
                  Los valores mostrados se encuentran en <strong>modo demostración</strong>.
                  Más adelante se reemplazarán por datos reales provenientes de la base
                  <code>personal_bcom602</code> y otros registros del área <strong>S-1 Personal</strong>.
                </p>
              </div>

              <div class="s-kpi-grid mt-2">
                <div class="s-kpi-card">
                  <div class="s-kpi-title">Personal total</div>
                  <div class="s-kpi-main">
                    <?= e($totalPersonal) ?>
                  </div>
                  <div class="s-kpi-sub">
                    Incluye personal en todas las situaciones.
                  </div>
                </div>

                <div class="s-kpi-card">
                  <div class="s-kpi-title">En servicio</div>
                  <div class="s-kpi-main">
                    <?= e($personalActivo) ?>
                  </div>
                  <div class="s-kpi-sub">
                    <?= e($porcActivo) ?>% del total.
                  </div>
                  <div class="progress mt-1" style="height:6px;">
                    <div class="progress-bar bg-success" style="width:<?= e($porcActivo) ?>%"></div>
                  </div>
                </div>

                <div class="s-kpi-card">
                  <div class="s-kpi-title">En licencia</div>
                  <div class="s-kpi-main">
                    <?= e($personalLicencia) ?>
                  </div>
                  <div class="s-kpi-sub">
                    <?= e($porcLicencia) ?>% del total.
                  </div>
                  <div class="progress mt-1" style="height:6px;">
                    <div class="progress-bar bg-success" style="width:<?= e($porcLicencia) ?>%"></div>
                  </div>
                </div>

                <div class="s-kpi-card">
                  <div class="s-kpi-title">Bajas</div>
                  <div class="s-kpi-main">
                    <?= e($personalBaja) ?>
                  </div>
                  <div class="s-kpi-sub">
                    <?= e($porcBaja) ?>% del total.
                  </div>
                  <div class="progress mt-1" style="height:6px;">
                    <div class="progress-bar bg-success" style="width:<?= e($porcBaja) ?>%"></div>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-md-5">
              <div class="s-pie-wrapper">
                <div class="s-pie"
                     style="background: conic-gradient(
                         #22c55e 0 <?= e($porcGlobal) ?>%,
                         rgba(30,64,175,.6) <?= e($porcGlobal) ?>% 100%
                     );">
                  <div class="s-pie-inner">
                    <div class="s-pie-perc"><?= e($porcGlobal) ?>%</div>
                    <div class="s-pie-label">
                      Personal en servicio
                    </div>
                    <div style="font-size:.7rem; color:#9ca3af; margin-top:4px;">
                      <?= e($personalActivo) ?>/<?= e($totalPersonal) ?> efectivos
                    </div>
                  </div>
                </div>
              </div>
            </div>

          </div><!-- /row -->
        </section>
      </div><!-- /layout row -->

    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
