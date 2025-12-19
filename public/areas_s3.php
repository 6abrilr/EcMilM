<?php
// public/areas_s3.php — Área S-3 Operaciones
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

/** @var PDO $pdo */
// Igual que en S-1: si por algún motivo no hay $pdo, lo armamos de respaldo
if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (function_exists('getDB')) {
        $pdo = getDB();
    } else {
        $dsn    = 'mysql:host=127.0.0.1;dbname=inspecciones;charset=utf8mb4';
        $userDb = 'root';
        $passDb = '';
        $pdo = new PDO($dsn, $userDb, $passDb, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
}

/* ===== Assets ===== */
$PUBLIC_URL = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'])), '/');
$APP_URL    = rtrim(str_replace('\\','/', dirname($PUBLIC_URL)), '/');
$ASSETS_URL = ($APP_URL === '' ? '' : $APP_URL) . '/assets';
$IMG_BG     = $ASSETS_URL . '/img/fondo.png';
$ESCUDO     = $ASSETS_URL . '/img/escudo_bcom602.png';

/*
 * Resumen S-3
 * Por ahora NO tenemos todavía un modelo real de:
 *   - total de actividades de cuadros
 *   - total de actividades de tropa
 *   - total de adiestramiento físico-militar
 *   - total de tiro
 *
 * Para no “inventar” datos, dejamos todos los totales y cumplidas en 0,
 * hasta que los vinculemos con las tablas reales (s3_educacion_*, s3_adiestramiento, s3_tiro, etc.).
 */

// Cuadros
$totalCuadros  = 0;
$hechasCuadros = 0;

// Tropa
$totalTropa  = 0;
$hechasTropa = 0;

// Adiestramiento físico-militar
$totalAdies  = 0;
$hechasAdies = 0;

// Tiro
$totalTiro  = 0;
$hechasTiro = 0;

// Totales globales
$totalActiv  = $totalCuadros + $totalTropa + $totalAdies + $totalTiro;
$hechasActiv = $hechasCuadros + $hechasTropa + $hechasAdies + $hechasTiro;

// Porcentajes (quedan todos en 0 hasta que tengamos datos reales)
$porcCuadros = $totalCuadros > 0 ? round($hechasCuadros * 100 / $totalCuadros, 1) : 0.0;
$porcTropa   = $totalTropa   > 0 ? round($hechasTropa   * 100 / $totalTropa,   1) : 0.0;
$porcAdies   = $totalAdies   > 0 ? round($hechasAdies   * 100 / $totalAdies,   1) : 0.0;
$porcTiro    = $totalTiro    > 0 ? round($hechasTiro    * 100 / $totalTiro,    1) : 0.0;

$porcGlobal  = $totalActiv   > 0 ? round($hechasActiv   * 100 / $totalActiv,   1) : 0.0;
?>

<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>S-3 Operaciones · Batallón de Comunicaciones 602</title>
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
    margin-bottom:8px;
  }

  .panel-sub{
    font-size:.86rem;
    color:#cbd5f5;
    margin-bottom:18px;
  }

  /* ===== Layout 2 columnas ===== */
  .layout-s3-row{
    display:flex;
    flex-wrap:wrap;
    gap:18px;
  }
  .layout-s3-sidebar{
    flex:0 0 260px;
    max-width:320px;
  }
  .layout-s3-main{
    flex:1 1 0;
    min-width:0;
  }
  @media (max-width: 768px){
    .layout-s3-sidebar,
    .layout-s3-main{
      flex:1 1 100%;
      max-width:100%;
    }
  }

  /* ===== Sidebar / accordion ===== */
  .s3-sidebar-box{
    background:rgba(15,23,42,.95);
    border-radius:16px;
    border:1px solid rgba(148,163,184,.45);
    padding:14px 14px 10px;
    box-shadow:0 10px 28px rgba(0,0,0,.75);
  }
  .s3-sidebar-title{
    font-size:.88rem;
    font-weight:700;
    letter-spacing:.05em;
    text-transform:uppercase;
    color:#9ca3af;
    margin-bottom:10px;
  }

  .accordion-s3 .accordion-item{
    background:transparent;
    border:none;
    border-radius:12px;
    margin-bottom:6px;
    overflow:hidden;
  }
  .accordion-s3 .accordion-button{
    background:radial-gradient(circle at left, rgba(34,197,94,.35), transparent 60%);
    border:none;
    color:#e5e7eb;
    font-size:.86rem;
    font-weight:700;
    padding:.55rem .75rem;
    box-shadow:0 6px 14px rgba(0,0,0,.65);
  }
  .accordion-s3 .accordion-button:not(.collapsed){
    background:radial-gradient(circle at left, rgba(34,197,94,.5), transparent 70%);
    color:#ecfdf5;
  }
  .accordion-s3 .accordion-body{
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
    margin-left:0;
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

  .s3-main-text{
    font-size:.9rem;
    color:#cbd5f5;
  }

  /* ===== KPIs centro S-3 ===== */
  .s3-kpi-grid{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
    margin-top:10px;
  }
  .s3-kpi-card{
    flex:1 1 180px;
    min-width:160px;
    background:rgba(15,23,42,.96);
    border-radius:14px;
    border:1px solid rgba(148,163,184,.45);
    padding:8px 10px;
    font-size:.78rem;
  }
  .s3-kpi-title{
    text-transform:uppercase;
    letter-spacing:.06em;
    color:#9ca3af;
    font-weight:700;
    margin-bottom:4px;
  }
  .s3-kpi-main{
    font-size:1rem;
    font-weight:800;
  }
  .s3-kpi-sub{
    font-size:.78rem;
    color:#cbd5f5;
  }

  .progress{
    background:rgba(15,23,42,.9);
  }

  /* ===== Donut / gráfico de torta ===== */
  .s3-pie-wrapper{
    display:flex;
    justify-content:center;
    align-items:center;
    padding:8px 0;
  }
  .s3-pie{
    width:220px;
    aspect-ratio:1 / 1;
    border-radius:50%;
    position:relative;
    box-shadow:0 16px 35px rgba(0,0,0,.9);
  }
  .s3-pie-inner{
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
  .s3-pie-perc{
    font-size:1.6rem;
    font-weight:800;
  }
  .s3-pie-label{
    font-size:.75rem;
    color:#9ca3af;
    text-transform:uppercase;
    letter-spacing:.09em;
    margin-top:4px;
  }

  @media (max-width: 768px){
    .s3-pie-wrapper{ margin-top:12px; }
  }

  .header-back{
    margin-left:auto;
    margin-right:20px;
    margin-top:4px;
    display:flex;
    gap:8px;
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
      <div class="panel-title">Área S-3 · Operaciones</div>
      <div class="panel-sub">
        Seleccioná el eje de trabajo correspondiente. Este resumen muestra un avance global
        estimado de las actividades de educación, adiestramiento físico-militar y tiro.
      </div>

      <div class="layout-s3-row">
        <!-- Sidebar izquierdo desplegable -->
        <aside class="layout-s3-sidebar">
          <div class="s3-sidebar-box">
            <div class="s3-sidebar-title">Ejes de trabajo S-3</div>

            <div class="accordion accordion-s3" id="accordionS3">

              <div class="accordion-item">
                <h2 class="accordion-header" id="s3-h-cuadros">
                  <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#s3-cuadros" aria-expanded="true" aria-controls="s3-cuadros">
                    Educación operacional de cuadros
                  </button>
                </h2>
                <div id="s3-cuadros" class="accordion-collapse collapse show" aria-labelledby="s3-h-cuadros" data-bs-parent="#accordionS3">
                  <div class="accordion-body">
                    Programas, cursos y actividades para oficiales y suboficiales.
                    <div class="mt-2">
                      <a href="s3_educacion_cuadros.php" class="gest-btn">Entrar</a>
                    </div>
                  </div>
                </div>
              </div>

              <div class="accordion-item">
                <h2 class="accordion-header" id="s3-h-tropa">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#s3-tropa" aria-expanded="false" aria-controls="s3-tropa">
                    Educación operacional de la tropa
                  </button>
                </h2>
                <div id="s3-tropa" class="accordion-collapse collapse" aria-labelledby="s3-h-tropa" data-bs-parent="#accordionS3">
                  <div class="accordion-body">
                    Actividades de instrucción para el personal de tropa, por objetivos y períodos.
                    <div class="mt-2">
                      <a href="s3_educacion_tropa.php?from=s3" class="btn btn-ghost">Educación operacional de la tropa</a>
                    </div>
                  </div>
                </div>
              </div>

              <div class="accordion-item">
                <h2 class="accordion-header" id="s3-h-adies">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#s3-adies" aria-expanded="false" aria-controls="s3-adies">
                    Adiestramiento físico-militar
                  </button>
                </h2>
                <div id="s3-adies" class="accordion-collapse collapse" aria-labelledby="s3-h-adies" data-bs-parent="#accordionS3">
                  <div class="accordion-body">
                    Planificación y registro del adiestramiento físico-militar de la unidad.
                    <div class="mt-2">
                      <a href="s3_adiestramiento.php" class="gest-btn">Entrar</a>
                    </div>
                  </div>
                </div>
              </div>

              <div class="accordion-item">
                <h2 class="accordion-header" id="s3-h-tiro">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#s3-tiro" aria-expanded="false" aria-controls="s3-tiro">
                    Tiro
                  </button>
                </h2>
                <div id="s3-tiro" class="accordion-collapse collapse" aria-labelledby="s3-h-tiro" data-bs-parent="#accordionS3">
                  <div class="accordion-body">
                    Planes de tiro, resultados y seguimiento de calificaciones de tiro.
                    <div class="mt-2">
                      <a href="s3_tiro.php" class="gest-btn">Entrar</a>
                    </div>
                  </div>
                </div>
              </div>

              <!-- ===== NUEVO ITEM: ROL DE COMBATE ===== -->
              <div class="accordion-item">
                <h2 class="accordion-header" id="s3-h-rolc">
                  <button class="accordion-button collapsed" type="button"
                          data-bs-toggle="collapse" data-bs-target="#s3-rolc"
                          aria-expanded="false" aria-controls="s3-rolc">
                    Rol de Combate
                  </button>
                </h2>
                <div id="s3-rolc" class="accordion-collapse collapse"
                     aria-labelledby="s3-h-rolc" data-bs-parent="#accordionS3">
                  <div class="accordion-body">
                    Visualización del Rol de Combate del Batallón de Comunicaciones 602
                    por Jefatura, Plana Mayor y compañías.
                    <div class="mt-2">
                      <a href="rol_combate.php" class="gest-btn">Entrar</a>
                    </div>
                  </div>
                </div>
              </div>
              <!-- ======================================== -->

            </div><!-- /accordion -->
          </div>
        </aside>

        <!-- Columna principal derecha: resumen + gráfico de torta -->
        <section class="layout-s3-main">
          <div class="row g-3 align-items-center">
            <div class="col-md-7">
              <div class="s3-main-text">
                <p>
                  Este resumen integra, en forma sintética, el estado de cumplimiento de los
                  principales componentes del área <strong>S-3 Operaciones</strong>:
                  educación de cuadros, educación de la tropa, adiestramiento físico-militar y tiro.
                </p>
                <p>
                  Los valores son ilustrativos en esta instancia (modo demo) y permiten mostrar
                  el potencial del sistema para consolidar la información requerida por el
                  <strong>Área de Operaciones</strong>.
                </p>
              </div>

              <div class="s3-kpi-grid mt-2">
                <div class="s3-kpi-card">
                  <div class="s3-kpi-title">Cuadros</div>
                  <div class="s3-kpi-main">
                    <?= e($hechasCuadros) ?>/<?= e($totalCuadros) ?>
                  </div>
                  <div class="s3-kpi-sub">
                    Cumplimiento: <?= e($porcCuadros) ?>%
                  </div>
                  <div class="progress mt-1" style="height:6px;">
                    <div class="progress-bar bg-success" style="width:<?= e($porcCuadros) ?>%"></div>
                  </div>
                </div>

                <div class="s3-kpi-card">
                  <div class="s3-kpi-title">Tropa</div>
                  <div class="s3-kpi-main">
                    <?= e($hechasTropa) ?>/<?= e($totalTropa) ?>
                  </div>
                  <div class="s3-kpi-sub">
                    Cumplimiento: <?= e($porcTropa) ?>%
                  </div>
                  <div class="progress mt-1" style="height:6px;">
                    <div class="progress-bar bg-success" style="width:<?= e($porcTropa) ?>%"></div>
                  </div>
                </div>

                <div class="s3-kpi-card">
                  <div class="s3-kpi-title">Adiestramiento Físico</div>
                  <div class="s3-kpi-main">
                    <?= e($hechasAdies) ?>/<?= e($totalAdies) ?>
                  </div>
                  <div class="s3-kpi-sub">
                    Cumplimiento: <?= e($porcAdies) ?>%
                  </div>
                  <div class="progress mt-1" style="height:6px;">
                    <div class="progress-bar bg-success" style="width:<?= e($porcAdies) ?>%"></div>
                  </div>
                </div>

                <div class="s3-kpi-card">
                  <div class="s3-kpi-title">Tiro</div>
                  <div class="s3-kpi-main">
                    <?= e($hechasTiro) ?>/<?= e($totalTiro) ?>
                  </div>
                  <div class="s3-kpi-sub">
                    Cumplimiento: <?= e($porcTiro) ?>%
                  </div>
                  <div class="progress mt-1" style="height:6px;">
                    <div class="progress-bar bg-success" style="width:<?= e($porcTiro) ?>%"></div>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-md-5">
              <div class="s3-pie-wrapper">
                <div class="s3-pie"
                     style="background: conic-gradient(
                         #22c55e 0 <?= e($porcGlobal) ?>%,
                         rgba(30,64,175,.6) <?= e($porcGlobal) ?>% 100%
                     );">
                  <div class="s3-pie-inner">
                    <div class="s3-pie-perc"><?= e($porcGlobal) ?>%</div>
                    <div class="s3-pie-label">
                      Cumplimiento global S-3
                    </div>
                    <div style="font-size:.7rem; color:#9ca3af; margin-top:4px;">
                      <?= e($hechasActiv) ?>/<?= e($totalActiv) ?> actividades
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
