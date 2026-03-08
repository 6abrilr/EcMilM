<?php
// ea/public/operaciones/operaciones.php — Área S-3 Operaciones (Sección III - 2.023)
declare(strict_types=1);

// ========= MODO PRODUCCIÓN =========
$OFFLINE_MODE = false;
// ===================================

require_once __DIR__ . '/../../auth/bootstrap.php';
if (!$OFFLINE_MODE) {
  require_login();
}
require_once __DIR__ . '/../../config/db.php';

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$user = function_exists('current_user') ? current_user() : null;

/** @var PDO $pdo */
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

/* ==========================================================
   BASE WEB robusta según tu estructura:
   - Estás en: /ea/public/operaciones/operaciones.php
   - Assets: /ea/assets/css y /ea/assets/img
   ========================================================== */
$SELF_WEB        = (string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
$BASE_DIR_WEB    = rtrim(str_replace('\\','/', dirname($SELF_WEB)), '/');            // /ea/public/operaciones
$BASE_PUBLIC_WEB = rtrim(str_replace('\\','/', dirname($BASE_DIR_WEB)), '/');        // /ea/public
$BASE_APP_WEB    = rtrim(str_replace('\\','/', dirname($BASE_PUBLIC_WEB)), '/');     // /ea
$ASSET_WEB       = $BASE_APP_WEB . '/assets';                                        // /ea/assets

$IMG_BG  = $ASSET_WEB . '/img/fondo.png';
$ESCUDO  = $ASSET_WEB . '/img/ecmilm.png';
$FAVICON = $ASSET_WEB . '/img/ecmilm.png';

/* ==========================================================
   Helpers DB (NO asume schema: verifica si existe tabla)
   ========================================================== */
function db_table_exists(PDO $pdo, string $table): bool {
  try {
    $sql = "SELECT 1
            FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name = :t
            LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([':t' => $table]);
    return (bool)$st->fetchColumn();
  } catch (Throwable $e) {
    return false;
  }
}

function safe_count(PDO $pdo, string $table): int {
  if (!db_table_exists($pdo, $table)) return 0;
  try {
    $st = $pdo->query("SELECT COUNT(*) FROM {$table}");
    return (int)($st->fetchColumn() ?: 0);
  } catch (Throwable $e) {
    return 0;
  }
}

/* ==========================================================
   Resumen S-3 (sin inventar)
   - Totales: si existen tablas, cuenta. Si no, 0.
   - "Hechas": requiere campo real (estado/cumplida), NO se inventa.
   ========================================================== */
// Tablas candidatas (ajustá si tus nombres reales difieren)
$totalCuadros = safe_count($pdo, 'operaciones_educacion_cuadros');
$totalTropa   = safe_count($pdo, 'operaciones_educacion_tropa');
$totalAdies   = safe_count($pdo, 'operaciones_adiestramiento');
$totalTiro    = safe_count($pdo, 'operaciones_tiro');

// Cumplidas = DEMO hasta que confirmes la columna real
$hechasCuadros = 0;
$hechasTropa   = 0;
$hechasAdies   = 0;
$hechasTiro    = 0;

$totalActiv  = $totalCuadros + $totalTropa + $totalAdies + $totalTiro;
$hechasActiv = $hechasCuadros + $hechasTropa + $hechasAdies + $hechasTiro;

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
<title>Operaciones</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= e($ASSET_WEB) ?>/css/theme-602.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="icon" type="image/png" href="<?= e($FAVICON) ?>">

<style>
  html,body{ height:100%; }
  body{
    background:
      linear-gradient(160deg, rgba(0,0,0,.82) 0%, rgba(0,0,0,.62) 55%, rgba(0,0,0,.85) 100%),
      url("<?= e($IMG_BG) ?>") no-repeat center center fixed;
    background-size: cover;
    background-attachment: fixed;
    background-color:#020617;
    color:#e5e7eb;
    font-family:system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,sans-serif;
    margin:0;
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
    font-weight:900;
    margin-bottom:6px;
    display:flex;
    align-items:center;
    gap:.55rem;
  }
  .panel-title .badge{ font-weight:900; letter-spacing:.04em; }
  .panel-sub{
    font-size:.86rem;
    color:#cbd5f5;
    margin-bottom:18px;
  }

  /* Header */
  .brand-hero{ padding-top:10px; padding-bottom:10px; }
  .brand-hero .hero-inner{
    align-items:center;
    display:flex;
    justify-content:space-between;
    gap:12px;
  }
  .brand-logo{
    width:58px;
    height:58px;
    object-fit:contain;
    filter: drop-shadow(0 10px 18px rgba(0,0,0,.55));
  }
  .brand-title{ font-weight:900; font-size:1.05rem; line-height:1.1; }
  .brand-sub{ font-size:.82rem; color:#9ca3af; }

  .header-back{
    margin-left:auto;
    margin-right:17px;
    margin-top:4px;
    display:flex;
    gap:8px;
  }

  /* Layout */
  .layout-s3-row{ display:flex; flex-wrap:wrap; gap:18px; }
  .layout-s3-sidebar{ flex:0 0 300px; max-width:380px; }
  .layout-s3-main{ flex:1 1 0; min-width:0; }
  @media (max-width: 768px){
    .layout-s3-sidebar, .layout-s3-main{ flex:1 1 100%; max-width:100%; }
  }

  /* Sidebar */
  .s3-sidebar-box{
    background:rgba(15,23,42,.95);
    border-radius:16px;
    border:1px solid rgba(148,163,184,.45);
    padding:14px 14px 10px;
    box-shadow:0 10px 28px rgba(0,0,0,.75);
  }
  .s3-sidebar-title{
    font-size:.88rem;
    font-weight:900;
    letter-spacing:.05em;
    text-transform:uppercase;
    color:#9ca3af;
    margin-bottom:10px;
    display:flex;
    align-items:center;
    gap:.5rem;
  }

  .accordion-s3 .accordion-item{ background:transparent; border:none; border-radius:12px; margin-bottom:6px; overflow:hidden; }
  .accordion-s3 .accordion-button{
    background:radial-gradient(circle at left, rgba(34,197,94,.35), transparent 60%);
    border:none;
    color:#e5e7eb;
    font-size:.86rem;
    font-weight:900;
    padding:.55rem .75rem;
    box-shadow:0 6px 14px rgba(0,0,0,.65);
  }
  .accordion-s3 .accordion-button:not(.collapsed){
    background:radial-gradient(circle at left, rgba(34,197,94,.52), transparent 70%);
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
    gap:.45rem;
    padding:.45rem 1.1rem;
    border-radius:999px;
    border:none;
    font-size:.82rem;
    font-weight:900;
    text-decoration:none;
    background:#22c55e;
    color:#052e16;
    box-shadow:0 8px 22px rgba(22,163,74,.7);
  }
  .gest-btn:hover{ background:#4ade80; color:#052e16; }
  .gest-btn.disabled, .gest-btn[aria-disabled="true"]{
    opacity:.45;
    pointer-events:none;
    filter:grayscale(.35);
  }

  .s3-main-text{ font-size:.9rem; color:#cbd5f5; }

  /* Doctrina box */
  .s3-doctrina{
    background:rgba(2,6,23,.55);
    border:1px dashed rgba(148,163,184,.45);
    border-radius:14px;
    padding:12px 12px;
    margin-top:10px;
  }
  .s3-doctrina h6{
    margin:0 0 8px;
    font-weight:900;
    font-size:.88rem;
    color:#e5e7eb;
    display:flex;
    align-items:center;
    gap:.45rem;
  }
  .s3-doctrina ul{
    margin:0;
    padding-left:18px;
    color:#cbd5f5;
    font-size:.84rem;
  }
  .s3-doctrina li{ margin:6px 0; }

  /* KPIs */
  .s3-kpi-grid{ display:flex; flex-wrap:wrap; gap:10px; margin-top:10px; }
  .s3-kpi-card{
    flex:1 1 200px;
    min-width:180px;
    background:rgba(15,23,42,.96);
    border-radius:14px;
    border:1px solid rgba(148,163,184,.45);
    padding:10px 12px;
    font-size:.78rem;
  }
  .s3-kpi-title{ text-transform:uppercase; letter-spacing:.06em; color:#9ca3af; font-weight:900; margin-bottom:4px; }
  .s3-kpi-main{ font-size:1.05rem; font-weight:900; display:flex; align-items:center; gap:.45rem; }
  .s3-kpi-sub{ font-size:.78rem; color:#cbd5f5; }
  .progress{ background:rgba(15,23,42,.9); }

  /* Donut */
  .s3-pie-wrapper{ display:flex; justify-content:center; align-items:center; padding:8px 0; }
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
  .s3-pie-perc{ font-size:1.6rem; font-weight:900; }
  .s3-pie-label{
    font-size:.75rem;
    color:#9ca3af;
    text-transform:uppercase;
    letter-spacing:.09em;
    margin-top:4px;
  }
</style>
</head>
<body>

<header class="brand-hero">
  <div class="hero-inner container-main">
    <div class="d-flex align-items-center gap-3">
      <img class="brand-logo"
           src="<?= e($ESCUDO) ?>"
           alt="Escudo 602"
           onerror="this.onerror=null; this.src='<?= e($ASSET_WEB) ?>/img/ecmilm.png';">
      <div>
        <div class="brand-title">Escuela Militar de Montaña</div>
        <div class="brand-sub">“La montaña nos une”</div>
      </div>
    </div>

    <div class="header-back">
            <!-- Estás en /public/operaciones, volvés a /public/inicio.php -->
      <a href="../inicio.php" class="btn btn-success btn-sm" style="font-weight:700; padding:.35rem .9rem;">
        <i class="bi bi-house-door"></i> Inicio
      </a>
    </div>
  </div>
</header>

<div class="page-wrap">
  <div class="container-main">
    <div class="panel">
      <div class="panel-title">
        <i class="bi bi-diagram-3-fill"></i>
        Área S-3 · Operaciones
        <span class="badge text-bg-success">PM</span>
      </div>

      <div class="panel-sub">
        Seleccioná el eje de trabajo correspondiente. Este panel se alinea a la Sección III (S-3):
        organización, educación, operaciones, movimientos y doctrina (PON, SILEAP, reglamentos).
      </div>

      <div class="layout-s3-row">
        <!-- Sidebar -->
        <aside class="layout-s3-sidebar">
          <div class="s3-sidebar-box">
            <div class="s3-sidebar-title"><i class="bi bi-grid-3x3-gap"></i> Ejes doctrinarios S-3</div>

            <div class="accordion accordion-s3" id="accordionS3">

              <div class="accordion-item">
                <h2 class="accordion-header" id="s3-h-org">
                  <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#s3-org" aria-expanded="true" aria-controls="s3-org">
                    <i class="bi bi-diagram-2 me-1"></i> Organización (CO / estados)
                  </button>
                </h2>
                <div id="s3-org" class="accordion-collapse collapse show" aria-labelledby="s3-h-org" data-bs-parent="#accordionS3">
                  <div class="accordion-body">
                    CO actualizado, ajustes, prioridades; estados de personal/armas/vehículos.
                    <div class="mt-2">
                      <a href="#" class="gest-btn disabled" aria-disabled="true">
                        <i class="bi bi-hourglass-split"></i> Próximamente
                      </a>
                    </div>
                  </div>
                </div>
              </div>

              <div class="accordion-item">
                <h2 class="accordion-header" id="s3-h-cuadros">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#s3-cuadros" aria-expanded="false" aria-controls="s3-cuadros">
                    <i class="bi bi-mortarboard me-1"></i> Educación · Cuadros
                  </button>
                </h2>
                <div id="s3-cuadros" class="accordion-collapse collapse" aria-labelledby="s3-h-cuadros" data-bs-parent="#accordionS3">
                  <div class="accordion-body">
                    PEU, órdenes de instrucción, ejercicios, MAPE, aulas/pistas/campos, visitas/inspecciones educativas.
                    <div class="mt-2">
                      <a href="./operaciones_educacion_cuadros.php" class="gest-btn">
                        <i class="bi bi-box-arrow-in-right"></i> Entrar
                      </a>
                    </div>
                  </div>
                </div>
              </div>

              <div class="accordion-item">
                <h2 class="accordion-header" id="s3-h-tropa">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#s3-tropa" aria-expanded="false" aria-controls="s3-tropa">
                    <i class="bi bi-people me-1"></i> Educación · Tropa
                  </button>
                </h2>
                <div id="s3-tropa" class="accordion-collapse collapse" aria-labelledby="s3-h-tropa" data-bs-parent="#accordionS3">
                  <div class="accordion-body">
                    Planes de educación de fracciones, análisis de partes, asesoramiento y control.
                    <div class="mt-2">
                      <a href="./operaciones_educacion_tropa.php" class="gest-btn">
                        <i class="bi bi-box-arrow-in-right"></i> Entrar
                      </a>
                    </div>
                  </div>
                </div>
              </div>

              <div class="accordion-item">
                <h2 class="accordion-header" id="s3-h-adies">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#s3-adies" aria-expanded="false" aria-controls="s3-adies">
                    <i class="bi bi-heart-pulse me-1"></i> Adiestramiento físico-militar
                  </button>
                </h2>
                <div id="s3-adies" class="accordion-collapse collapse" aria-labelledby="s3-h-adies" data-bs-parent="#accordionS3">
                  <div class="accordion-body">
                    Planificación y registro del adiestramiento físico-militar.
                    <div class="mt-2">
                      <a href="./operaciones_adiestramiento.php" class="gest-btn">
                        <i class="bi bi-box-arrow-in-right"></i> Entrar
                      </a>
                    </div>
                  </div>
                </div>
              </div>

              <div class="accordion-item">
                <h2 class="accordion-header" id="s3-h-tiro">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#s3-tiro" aria-expanded="false" aria-controls="s3-tiro">
                    <i class="bi bi-bullseye me-1"></i> Tiro
                  </button>
                </h2>
                <div id="s3-tiro" class="accordion-collapse collapse" aria-labelledby="s3-h-tiro" data-bs-parent="#accordionS3">
                  <div class="accordion-body">
                    Planes de tiro, resultados y seguimiento de calificaciones.
                    <div class="mt-2">
                      <a href="./operaciones_tiro.php" class="gest-btn">
                        <i class="bi bi-box-arrow-in-right"></i> Entrar
                      </a>
                    </div>
                  </div>
                </div>
              </div>

              <div class="accordion-item">
                <h2 class="accordion-header" id="s3-h-ops">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#s3-ops" aria-expanded="false" aria-controls="s3-ops">
                    <i class="bi bi-compass me-1"></i> Operaciones (planes/órdenes)
                  </button>
                </h2>
                <div id="s3-ops" class="accordion-collapse collapse" aria-labelledby="s3-h-ops" data-bs-parent="#accordionS3">
                  <div class="accordion-body">
                    Apreciación de situación; planes/órdenes y supervisión; apoyo; zonas de descanso; alistamiento;
                    defensa/recuperación y PON de seguridad (base estudio S-2).
                    <div class="mt-2">
                      <a href="#" class="gest-btn disabled" aria-disabled="true">
                        <i class="bi bi-hourglass-split"></i> Próximamente
                      </a>
                    </div>
                  </div>
                </div>
              </div>

              <div class="accordion-item">
                <h2 class="accordion-header" id="s3-h-mov">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#s3-mov" aria-expanded="false" aria-controls="s3-mov">
                    <i class="bi bi-truck me-1"></i> Movimientos de tropa
                  </button>
                </h2>
                <div id="s3-mov" class="accordion-collapse collapse" aria-labelledby="s3-h-mov" data-bs-parent="#accordionS3">
                  <div class="accordion-body">
                    Transporte con S-1/S-4; organización de marcha; prioridades; puntos terminales; horarios/descansos/caminos;
                    seguridad; órdenes preparatorias/de marcha; profundidades.
                    <div class="mt-2">
                      <a href="#" class="gest-btn disabled" aria-disabled="true">
                        <i class="bi bi-hourglass-split"></i> Próximamente
                      </a>
                    </div>
                  </div>
                </div>
              </div>

              <div class="accordion-item">
                <h2 class="accordion-header" id="s3-h-doc">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#s3-doc" aria-expanded="false" aria-controls="s3-doc">
                    <i class="bi bi-journal-text me-1"></i> Doctrina / SILEAP / Reglamentos
                  </button>
                </h2>
                <div id="s3-doc" class="accordion-collapse collapse" aria-labelledby="s3-h-doc" data-bs-parent="#accordionS3">
                  <div class="accordion-body">
                    Modificaciones reglamentarias; experimentación; lecciones aprendidas (SILEAP);
                    inventario/publicaciones con cargo y clasificación; reglamentos actualizados disponibles.
                    <div class="mt-2">
                      <a href="#" class="gest-btn disabled" aria-disabled="true">
                        <i class="bi bi-hourglass-split"></i> Próximamente
                      </a>
                    </div>
                  </div>
                </div>
              </div>

              <div class="accordion-item">
                <h2 class="accordion-header" id="s3-h-rolc">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#s3-rolc" aria-expanded="false" aria-controls="s3-rolc">
                    <i class="bi bi-shield-check me-1"></i> Rol de Combate
                  </button>
                </h2>
                <div id="s3-rolc" class="accordion-collapse collapse" aria-labelledby="s3-h-rolc" data-bs-parent="#accordionS3">
                  <div class="accordion-body">
                    Visualización del Rol de Combate (por jefatura, plana mayor y compañías).
                    <div class="mt-2">
                      <a href="../rol_combate.php" class="gest-btn">
                        <i class="bi bi-box-arrow-in-right"></i> Entrar
                      </a>
                    </div>
                  </div>
                </div>
              </div>

            </div><!-- /accordion -->
          </div>
        </aside>

        <!-- Main -->
        <section class="layout-s3-main">
          <div class="row g-3 align-items-start">
            <div class="col-md-7">
              <div class="s3-main-text">
                <p class="mb-2">
                  Este panel consolida el estado general de los componentes de <strong>S-3</strong>.
                  Los totales se cuentan desde tablas si existen; el “cumplidas” queda en <strong>modo demo</strong>
                  hasta definir el campo real que marca cumplimiento/estado.
                </p>
                <p class="mb-2">
                  Incluye el enfoque completo de Sección III: <b>Organización</b>, <b>Educación</b>,
                  <b>Operaciones</b>, <b>Movimientos</b>, <b>Doctrina</b> y <b>PON</b>.
                </p>
              </div>

              <div class="s3-doctrina">
                <h6><i class="bi bi-compass"></i> Qué hace S-3 (Sección III · 2.023)</h6>
                <ul>
                  <li><b>Organización:</b> CO actualizado; ajustes según órdenes; prioridades de personal/material; estados de personal/armas/vehículos.</li>
                  <li><b>Educación:</b> PEU; órdenes de instrucción; ejercicios; MAPE; aulas/pistas/campos; consumo munición con S-4; inspecciones educativas; archivo/biblioteca de reglamentos.</li>
                  <li><b>Operaciones:</b> apreciación de situación; planes/órdenes; apoyo; zonas de descanso; seguridad (con S-2 CI y con S-1 seguridad contra accidentes); ubicación del PC; exploración/reconocimientos; alistamiento; plan defensa/recuperación + PON de seguridad (base estudio S-2).</li>
                  <li><b>Movimientos:</b> transporte con S-1/S-4; organización de marcha; prioridades; puntos terminales; tiempos/horarios/descansos/caminos; seguridad; órdenes preparatorias/de marcha; profundidades.</li>
                  <li><b>Doctrina:</b> modificaciones reglamentarias; experimentación; lecciones aprendidas (SILEAP); inventario/publicaciones con cargo y clasificación; reglamentos actualizados disponibles.</li>
                  <li><b>Varios:</b> redacta/actualiza PON sobre organización, educación y operaciones.</li>
                </ul>
              </div>

              <div class="s3-kpi-grid mt-3">
                <div class="s3-kpi-card">
                  <div class="s3-kpi-title">Educación Cuadros</div>
                  <div class="s3-kpi-main"><i class="bi bi-mortarboard"></i> <?= e($hechasCuadros) ?>/<?= e($totalCuadros) ?></div>
                  <div class="s3-kpi-sub">Cumplimiento (demo): <?= e($porcCuadros) ?>%</div>
                  <div class="progress mt-1" style="height:6px;">
                    <div class="progress-bar bg-success" style="width:<?= e($porcCuadros) ?>%"></div>
                  </div>
                </div>

                <div class="s3-kpi-card">
                  <div class="s3-kpi-title">Educación Tropa</div>
                  <div class="s3-kpi-main"><i class="bi bi-people"></i> <?= e($hechasTropa) ?>/<?= e($totalTropa) ?></div>
                  <div class="s3-kpi-sub">Cumplimiento (demo): <?= e($porcTropa) ?>%</div>
                  <div class="progress mt-1" style="height:6px;">
                    <div class="progress-bar bg-success" style="width:<?= e($porcTropa) ?>%"></div>
                  </div>
                </div>

                <div class="s3-kpi-card">
                  <div class="s3-kpi-title">Adiestramiento</div>
                  <div class="s3-kpi-main"><i class="bi bi-heart-pulse"></i> <?= e($hechasAdies) ?>/<?= e($totalAdies) ?></div>
                  <div class="s3-kpi-sub">Cumplimiento (demo): <?= e($porcAdies) ?>%</div>
                  <div class="progress mt-1" style="height:6px;">
                    <div class="progress-bar bg-success" style="width:<?= e($porcAdies) ?>%"></div>
                  </div>
                </div>

                <div class="s3-kpi-card">
                  <div class="s3-kpi-title">Tiro</div>
                  <div class="s3-kpi-main"><i class="bi bi-bullseye"></i> <?= e($hechasTiro) ?>/<?= e($totalTiro) ?></div>
                  <div class="s3-kpi-sub">Cumplimiento (demo): <?= e($porcTiro) ?>%</div>
                  <div class="progress mt-1" style="height:6px;">
                    <div class="progress-bar bg-success" style="width:<?= e($porcTiro) ?>%"></div>
                  </div>
                </div>
              </div>

              <div class="alert alert-dark mt-3" style="background:rgba(15,23,42,.9); border:1px solid rgba(148,163,184,.35); color:#cbd5f5;">
                <div style="font-weight:900; margin-bottom:6px;">
                  <i class="bi bi-wrench-adjustable-circle"></i> Para hacerlo “REAL”
                </div>
                <div style="font-size:.86rem;">
                  Para calcular “cumplidas” (no demo), necesito el nombre exacto del campo en cada tabla
                  que indique estado/cumplimiento (ej: <code>estado</code>, <code>cumplida</code>, <code>finalizada</code>, etc.).
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
                    <div class="s3-pie-label">Cumplimiento global S-3</div>
                    <div style="font-size:.7rem; color:#9ca3af; margin-top:4px;">
                      <?= e($hechasActiv) ?>/<?= e($totalActiv) ?> actividades (demo)
                    </div>
                  </div>
                </div>
              </div>

              <div class="alert alert-dark mt-2" style="background:rgba(2,6,23,.55); border:1px dashed rgba(148,163,184,.45); color:#cbd5f5;">
                <div style="font-weight:900; margin-bottom:6px;">
                  <i class="bi bi-shield-check"></i> Seguridad (coordinación obligatoria)
                </div>
                <div style="font-size:.86rem;">
                  En operaciones, S-3 coordina medidas de seguridad con:
                  <ul style="margin:8px 0 0; padding-left:18px;">
                    <li><b>S-2</b> (Contrainteligencia / seguridad operativa)</li>
                    <li><b>S-1</b> (Seguridad contra accidentes)</li>
                    <li><b>S-4</b> (Apoyo logístico y consumo munición para instrucción)</li>
                  </ul>
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
