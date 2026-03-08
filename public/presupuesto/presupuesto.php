<?php
// public/presupuesto/presupuesto.php — Presupuesto / Gestión Presupuestaria (PM Especial)
declare(strict_types=1);

// ========= MODO PRODUCCIÓN =========
$OFFLINE_MODE = false;
// ===================================

// Desde /ea/public/presupuesto => /ea/auth y /ea/config
require_once __DIR__ . '/../../auth/bootstrap.php';
if (!$OFFLINE_MODE) { require_login(); }
require_once __DIR__ . '/../../config/db.php';

/** @var PDO $pdo */
function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$user = function_exists('current_user') ? current_user() : null;

/* ==========================================================
   BASE WEB robusta
   ========================================================== */
$SELF_WEB        = (string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
$BASE_DIR_WEB    = rtrim(str_replace('\\','/', dirname($SELF_WEB)), '/');            // /ea/public/presupuesto
$BASE_PUBLIC_WEB = rtrim(str_replace('\\','/', dirname($BASE_DIR_WEB)), '/');        // /ea/public
$BASE_APP_WEB    = rtrim(str_replace('\\','/', dirname($BASE_PUBLIC_WEB)), '/');     // /ea
$ASSET_WEB       = $BASE_APP_WEB . '/assets';                                        // /ea/assets

$IMG_BG  = $ASSET_WEB . '/img/fondo.png';
$ESCUDO  = $ASSET_WEB . '/img/ecmilm.png';
$FAVICON = $ASSET_WEB . '/img/ecmilm.png';

/* ==========================================================
   Helpers DB (NO asume schema: verifica si existe tabla/col)
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
  } catch (Throwable $e) { return false; }
}
function db_column_exists(PDO $pdo, string $table, string $column): bool {
  try {
    $sql = "SELECT 1
            FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c
            LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([':t' => $table, ':c' => $column]);
    return (bool)$st->fetchColumn();
  } catch (Throwable $e) { return false; }
}

/* ==========================================================
   KPIs (robustos / sin suposiciones)
   - Si aún no existen tablas, muestra "—".
   ========================================================== */

/**
 * Si ya tenés tablas para presupuesto, acá las conectamos.
 * Por ahora (robusto):
 * - "calendario_tareas" como “tareas” (si existe)
 * - "documentos" como “docs” (si existe)
 */
$kpiTareasTotal = null;   // int|null
$kpiDocsTotal   = null;   // int|null

try {
  if (db_table_exists($pdo, 'calendario_tareas')) {
    $st = $pdo->query("SELECT COUNT(*) FROM calendario_tareas");
    $kpiTareasTotal = (int)($st->fetchColumn() ?: 0);
  }
} catch (Throwable $e) { $kpiTareasTotal = null; }

try {
  if (db_table_exists($pdo, 'documentos')) {
    $st = $pdo->query("SELECT COUNT(*) FROM documentos");
    $kpiDocsTotal = (int)($st->fetchColumn() ?: 0);
  }
} catch (Throwable $e) { $kpiDocsTotal = null; }

/* Demo (hasta definir schema específico) */
$kpiUEP      = 0;
$kpiEjec     = 0;
$kpiRend     = 0;
$kpiVisitas  = 0;

$kpiTotalDemo = max($kpiUEP + $kpiEjec + $kpiRend + $kpiVisitas, 0);
$porcUEP   = $kpiTotalDemo > 0 ? round($kpiUEP     * 100 / $kpiTotalDemo, 1) : 0.0;
$porcEjec  = $kpiTotalDemo > 0 ? round($kpiEjec    * 100 / $kpiTotalDemo, 1) : 0.0;
$porcRend  = $kpiTotalDemo > 0 ? round($kpiRend    * 100 / $kpiTotalDemo, 1) : 0.0;
$porcVis   = $kpiTotalDemo > 0 ? round($kpiVisitas * 100 / $kpiTotalDemo, 1) : 0.0;

$porcGlobal = $kpiTotalDemo > 0 ? $porcEjec : 0.0; // “Ejecución” como principal demo
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Presupuesto · Gestión Presupuestaria</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= e($ASSET_WEB) ?>/css/theme-602.css">
<link rel="icon" href="<?= e($FAVICON) ?>">

<style>
  html,body{ height:100%; }
  body{
    margin:0;
    color:#e5e7eb;
    background:#000;
    font-family:system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,sans-serif;
  }

  .page-bg{
    position:fixed;
    inset:0;
    z-index:-2;
    pointer-events:none;
    background:
      linear-gradient(160deg, rgba(0,0,0,.85) 0%, rgba(0,0,0,.65) 55%, rgba(0,0,0,.85) 100%),
      url("<?= e($IMG_BG) ?>") center/cover no-repeat;
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

  .page-wrap{ padding:18px; position:relative; z-index:2; }
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
    font-weight:900;
    margin-bottom:6px;
    display:flex;
    align-items:center;
    gap:.55rem;
  }
  .panel-title .badge{
    font-weight:800;
    letter-spacing:.04em;
  }

  .panel-sub{
    font-size:.86rem;
    color:#cbd5f5;
    margin-bottom:18px;
  }

  /* Header */
  .brand-hero{ padding-top:10px; padding-bottom:10px; position:relative; z-index:3; }
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
  .brand-title{ font-weight:900; font-size:1.15rem; line-height:1.1; color:#e5e7eb; }
  .brand-sub{ font-size:.9rem; color:#cbd5f5; opacity:.9; margin-top:2px; }

  .header-back{
    margin-left:auto;
    margin-right:17px; /* tu config preferida */
    margin-top:4px;
    display:flex;
    gap:8px;
  }

  /* Layout */
  .layout-s-row{ display:flex; flex-wrap:wrap; gap:18px; }
  .layout-s-sidebar{ flex:0 0 280px; max-width:360px; }
  .layout-s-main{ flex:1 1 0; min-width:0; }
  @media (max-width: 768px){
    .layout-s-sidebar, .layout-s-main{ flex:1 1 100%; max-width:100%; }
  }

  /* Sidebar */
  .s-sidebar-box{
    background:rgba(15,23,42,.95);
    border-radius:16px;
    border:1px solid rgba(148,163,184,.45);
    padding:14px 14px 10px;
    box-shadow:0 10px 28px rgba(0,0,0,.75);
  }
  .s-sidebar-title{
    font-size:.88rem;
    font-weight:800;
    letter-spacing:.05em;
    text-transform:uppercase;
    color:#9ca3af;
    margin-bottom:10px;
    display:flex;
    align-items:center;
    gap:.5rem;
  }

  .accordion-s .accordion-item{ background:transparent; border:none; border-radius:12px; margin-bottom:6px; overflow:hidden; }
  .accordion-s .accordion-button{
    background:radial-gradient(circle at left, rgba(234,179,8,.28), transparent 60%);
    border:none;
    color:#e5e7eb;
    font-size:.86rem;
    font-weight:800;
    padding:.55rem .75rem;
    box-shadow:0 6px 14px rgba(0,0,0,.65);
  }
  .accordion-s .accordion-button:not(.collapsed){
    background:radial-gradient(circle at left, rgba(234,179,8,.42), transparent 70%);
    color:#fffbeb;
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
    gap:.45rem;
    padding:.45rem 1.1rem;
    border-radius:999px;
    border:none;
    font-size:.82rem;
    font-weight:900;
    text-decoration:none;
    background:#eab308;
    color:#1f1600;
    box-shadow:0 8px 22px rgba(234,179,8,.55);
  }
  .gest-btn:hover{ background:#facc15; color:#1f1600; }
  .gest-btn.disabled,
  .gest-btn[aria-disabled="true"]{
    opacity:.45;
    pointer-events:none;
    filter:grayscale(.4);
  }

  .s-main-text{ font-size:.9rem; color:#cbd5f5; }

  /* KPIs */
  .s-kpi-grid{ display:flex; flex-wrap:wrap; gap:10px; margin-top:10px; }
  .s-kpi-card{
    flex:1 1 200px;
    min-width:180px;
    background:rgba(15,23,42,.96);
    border-radius:14px;
    border:1px solid rgba(148,163,184,.45);
    padding:10px 12px;
    font-size:.78rem;
  }
  .s-kpi-title{ text-transform:uppercase; letter-spacing:.06em; color:#9ca3af; font-weight:800; margin-bottom:4px; }
  .s-kpi-main{ font-size:1.05rem; font-weight:900; display:flex; align-items:center; gap:.45rem; }
  .s-kpi-sub{ font-size:.78rem; color:#cbd5f5; }
  .progress{ background:rgba(15,23,42,.9); }

  /* Donut */
  .s-pie-wrapper{ display:flex; justify-content:center; align-items:center; padding:8px 0; }
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
  .s-pie-perc{ font-size:1.6rem; font-weight:900; }
  .s-pie-label{
    font-size:.75rem;
    color:#9ca3af;
    text-transform:uppercase;
    letter-spacing:.09em;
    margin-top:4px;
  }

  /* Caja doctrinaria */
  .p-doctrina{
    background:rgba(2,6,23,.55);
    border:1px dashed rgba(148,163,184,.45);
    border-radius:14px;
    padding:12px 12px;
    margin-top:10px;
  }
  .p-doctrina h6{
    margin:0 0 8px;
    font-weight:900;
    font-size:.88rem;
    color:#e5e7eb;
    display:flex;
    align-items:center;
    gap:.45rem;
  }
  .p-doctrina ul{
    margin:0;
    padding-left:18px;
    color:#cbd5f5;
    font-size:.84rem;
  }
  .p-doctrina li{ margin:6px 0; }
</style>
</head>
<body>

<div class="page-bg"></div>

<header class="brand-hero">
  <div class="hero-inner container-main">
    <div class="d-flex align-items-center gap-3">
      <img class="brand-logo" src="<?= e($ESCUDO) ?>" alt="EC MIL M"
           onerror="this.onerror=null; this.src='<?= e($ASSET_WEB) ?>/img/EA.png';">
      <div>
        <div class="brand-title">Escuela Militar de Montaña</div>
        <div class="brand-sub">“La montaña nos une”</div>
      </div>
    </div>

    <div class="header-back">
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
        <i class="bi bi-cash-coin"></i>
        Gestión Presupuestaria
        <span class="badge text-bg-warning">PME</span>
      </div>

      <div class="panel-sub">
        Panel para planificación, seguimiento y control de ejecución presupuestaria (UEP, ejecuciones, rendiciones y visitas).
        Los KPIs se conectan automáticamente solo si existen tablas compatibles en tu base.
      </div>

      <div class="layout-s-row">
        <!-- Sidebar -->
        <aside class="layout-s-sidebar">
          <div class="s-sidebar-box">
            <div class="s-sidebar-title"><i class="bi bi-grid-3x3-gap"></i> Módulos</div>

            <div class="accordion accordion-s" id="accordionP">

              <div class="accordion-item">
                <h2 class="accordion-header" id="p-h-plan">
                  <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#p-plan" aria-expanded="true" aria-controls="p-plan">
                    <i class="bi bi-calendar2-check me-1"></i> Planificación (UEP)
                  </button>
                </h2>
                <div id="p-plan" class="accordion-collapse collapse show" aria-labelledby="p-h-plan" data-bs-parent="#accordionP">
                  <div class="accordion-body">
                    Gestión de Unidad Ejecutora Presupuestaria: previsión y plan anual. (Próximamente)
                    <div class="mt-2">
                      <a href="#" class="gest-btn disabled" aria-disabled="true"><i class="bi bi-hourglass-split"></i> Próximamente</a>
                    </div>
                  </div>
                </div>
              </div>

              <div class="accordion-item">
                <h2 class="accordion-header" id="p-h-ejec">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#p-ejec" aria-expanded="false" aria-controls="p-ejec">
                    <i class="bi bi-graph-up-arrow me-1"></i> Ejecución
                  </button>
                </h2>
                <div id="p-ejec" class="accordion-collapse collapse" aria-labelledby="p-h-ejec" data-bs-parent="#accordionP">
                  <div class="accordion-body">
                    Seguimiento de ejecución: devengado/pagado y estado de partidas. (Próximamente)
                    <div class="mt-2">
                      <a href="#" class="gest-btn disabled" aria-disabled="true"><i class="bi bi-hourglass-split"></i> Próximamente</a>
                    </div>
                  </div>
                </div>
              </div>

              <div class="accordion-item">
                <h2 class="accordion-header" id="p-h-rend">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#p-rend" aria-expanded="false" aria-controls="p-rend">
                    <i class="bi bi-receipt-cutoff me-1"></i> Rendiciones
                  </button>
                </h2>
                <div id="p-rend" class="accordion-collapse collapse" aria-labelledby="p-h-rend" data-bs-parent="#accordionP">
                  <div class="accordion-body">
                    Rendiciones y control documental de gasto. (Próximamente)
                    <div class="mt-2">
                      <a href="#" class="gest-btn disabled" aria-disabled="true"><i class="bi bi-hourglass-split"></i> Próximamente</a>
                    </div>
                  </div>
                </div>
              </div>

              <div class="accordion-item">
                <h2 class="accordion-header" id="p-h-vis">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#p-vis" aria-expanded="false" aria-controls="p-vis">
                    <i class="bi bi-clipboard2-check me-1"></i> Visitas / auditorías EM
                  </button>
                </h2>
                <div id="p-vis" class="accordion-collapse collapse" aria-labelledby="p-h-vis" data-bs-parent="#accordionP">
                  <div class="accordion-body">
                    Visitas de Estado Mayor: planificación, registros y hallazgos. (Próximamente)
                    <div class="mt-2">
                      <a href="#" class="gest-btn disabled" aria-disabled="true"><i class="bi bi-hourglass-split"></i> Próximamente</a>
                    </div>
                  </div>
                </div>
              </div>

            </div><!-- /accordion -->
          </div>
        </aside>

        <!-- Main -->
        <section class="layout-s-main">
          <div class="row g-3 align-items-start">
            <div class="col-md-7">
              <div class="s-main-text">
                <p>
                  Este panel está preparado para la función de <strong>gestión presupuestaria</strong>.
                  Si ya estás usando <code>calendario_tareas</code>, podés cargar ahí hitos (vencimientos / entregas / auditorías).
                </p>
                <p class="mb-2">
                  La estructura de presupuesto “real” (partidas, expedientes, compromisos, devengado/pagado, rendiciones)
                  la armamos cuando definas el esquema o me compartas tus tablas.
                </p>
              </div>

              <div class="p-doctrina">
                <h6><i class="bi bi-compass"></i> Rol (base doctrinaria)</h6>
                <ul>
                  <li>Asesora al Cte/Dir y a JJ Un/Elem sobre <b>planificación y ejecución presupuestaria</b>.</li>
                  <li>Responsable de la <b>UEP</b> (según corresponda) y del seguimiento de su ejecución.</li>
                  <li>Planifica/ejecuta <b>visitas de EM</b> y cumple directivas.</li>
                  <li>Optimiza planeamiento con SAF-UD/CE/CRE y difunde información presupuestaria.</li>
                </ul>
              </div>

              <div class="s-kpi-grid mt-3">
                <div class="s-kpi-card">
                  <div class="s-kpi-title">Tareas (calendario)</div>
                  <div class="s-kpi-main"><i class="bi bi-calendar-event"></i> <?= e($kpiTareasTotal ?? '—') ?></div>
                  <div class="s-kpi-sub">Se calcula si existe <code>calendario_tareas</code>.</div>
                </div>

                <div class="s-kpi-card">
                  <div class="s-kpi-title">Documentos (sistema)</div>
                  <div class="s-kpi-main"><i class="bi bi-files"></i> <?= e($kpiDocsTotal ?? '—') ?></div>
                  <div class="s-kpi-sub">Se calcula si existe <code>documentos</code>.</div>
                </div>

                <div class="s-kpi-card">
                  <div class="s-kpi-title">UEP (demo)</div>
                  <div class="s-kpi-main"><?= e($kpiUEP) ?></div>
                  <div class="s-kpi-sub"><?= e($porcUEP) ?>% (demo).</div>
                  <div class="progress mt-1" style="height:6px;">
                    <div class="progress-bar bg-warning" style="width:<?= e($porcUEP) ?>%"></div>
                  </div>
                </div>

                <div class="s-kpi-card">
                  <div class="s-kpi-title">Ejecución (demo)</div>
                  <div class="s-kpi-main"><?= e($kpiEjec) ?></div>
                  <div class="s-kpi-sub"><?= e($porcEjec) ?>% (demo).</div>
                  <div class="progress mt-1" style="height:6px;">
                    <div class="progress-bar bg-warning" style="width:<?= e($porcEjec) ?>%"></div>
                  </div>
                </div>

                <div class="s-kpi-card">
                  <div class="s-kpi-title">Rendiciones (demo)</div>
                  <div class="s-kpi-main"><?= e($kpiRend) ?></div>
                  <div class="s-kpi-sub"><?= e($porcRend) ?>% (demo).</div>
                  <div class="progress mt-1" style="height:6px;">
                    <div class="progress-bar bg-warning" style="width:<?= e($porcRend) ?>%"></div>
                  </div>
                </div>

                <div class="s-kpi-card">
                  <div class="s-kpi-title">Visitas EM (demo)</div>
                  <div class="s-kpi-main"><?= e($kpiVisitas) ?></div>
                  <div class="s-kpi-sub"><?= e($porcVis) ?>% (demo).</div>
                  <div class="progress mt-1" style="height:6px;">
                    <div class="progress-bar bg-warning" style="width:<?= e($porcVis) ?>%"></div>
                  </div>
                </div>

              </div>
            </div>

            <div class="col-md-5">
              <div class="s-pie-wrapper">
                <div class="s-pie"
                     style="background: conic-gradient(
                         #eab308 0 <?= e($porcGlobal) ?>%,
                         rgba(120,53,15,.55) <?= e($porcGlobal) ?>% 100%
                     );">
                  <div class="s-pie-inner">
                    <div class="s-pie-perc"><?= e($porcGlobal) ?>%</div>
                    <div class="s-pie-label">Ejecución</div>
                    <div style="font-size:.7rem; color:#9ca3af; margin-top:4px;">
                      <?= e($kpiEjec) ?>/<?= e($kpiTotalDemo) ?> (demo)
                    </div>
                  </div>
                </div>
              </div>

              <div class="alert alert-dark mt-2" style="background:rgba(15,23,42,.9); border:1px solid rgba(148,163,184,.35); color:#cbd5f5;">
                <div style="font-weight:900; margin-bottom:6px;"><i class="bi bi-wrench-adjustable-circle"></i> Próximo paso</div>
                <div style="font-size:.86rem;">
                  Si querés que quede <b>operativo</b>, definimos el schema mínimo:
                  <code>pres_partidas</code>, <code>pres_movimientos</code>, <code>pres_expedientes</code>, <code>pres_rendiciones</code>
                  (con estados y adjuntos).
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

