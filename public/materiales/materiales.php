<?php
// public/materiales/materiales.php — Área S-4 Materiales
declare(strict_types=1);

// ========= MODO PRODUCCIÓN =========
$OFFLINE_MODE = false;
// ===================================

// Desde /ea/public/materiales => /ea/auth y /ea/config
require_once __DIR__ . '/../../auth/bootstrap.php';
if (!$OFFLINE_MODE) {
  require_login();
}
require_once __DIR__ . '/../../config/db.php';

/** @var PDO $pdo */
function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$user = function_exists('current_user') ? current_user() : null;

/* ==========================================================
   - Estás en: /ea/public/materiales/materiales.php
   - Assets están en: /ea/assets/img
   ========================================================== */
$SELF_WEB        = (string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
$BASE_DIR_WEB    = rtrim(str_replace('\\','/', dirname($SELF_WEB)), '/');            // /ea/public/materiales
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
  } catch (Throwable $e) {
    return false;
  }
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
  } catch (Throwable $e) {
    return false;
  }
}

/* ==========================================================
   Resumen S-4 (robusto / sin suponer tablas)
   ========================================================== */

/**
 * KPIs sugeridos (sin romper):
 * - "items" (si existe): total ítems
 * - "documentos" (si existe): total docs
 * - "checklist" (si existe): total checklists (inventario / inspecciones)
 *
 * Si no existen tablas, muestra "—" y listo.
 */
$kpiItemsTotal      = null; // int|null
$kpiDocsTotal       = null; // int|null
$kpiChecklistTotal  = null; // int|null;

try {
  if (db_table_exists($pdo, 'items')) {
    $st = $pdo->query("SELECT COUNT(*) FROM items");
    $kpiItemsTotal = (int)($st->fetchColumn() ?: 0);
  }
} catch (Throwable $e) { $kpiItemsTotal = null; }

try {
  if (db_table_exists($pdo, 'documentos')) {
    $st = $pdo->query("SELECT COUNT(*) FROM documentos");
    $kpiDocsTotal = (int)($st->fetchColumn() ?: 0);
  }
} catch (Throwable $e) { $kpiDocsTotal = null; }

try {
  if (db_table_exists($pdo, 'checklist')) {
    $st = $pdo->query("SELECT COUNT(*) FROM checklist");
    $kpiChecklistTotal = (int)($st->fetchColumn() ?: 0);
  }
} catch (Throwable $e) { $kpiChecklistTotal = null; }

/**
 * Distribución “demo” (hasta que confirmemos campos/tablas reales):
 * - Abastecimiento / Mantenimiento / Transporte / Construcciones
 */
$kpiAbastecimiento = 0;
$kpiMantenimiento  = 0;
$kpiTransporte     = 0;
$kpiConstrucciones = 0;

$kpiTotalDemo = max($kpiAbastecimiento + $kpiMantenimiento + $kpiTransporte + $kpiConstrucciones, 0);
$porcAbast = $kpiTotalDemo > 0 ? round($kpiAbastecimiento * 100 / $kpiTotalDemo, 1) : 0.0;
$porcMant  = $kpiTotalDemo > 0 ? round($kpiMantenimiento  * 100 / $kpiTotalDemo, 1) : 0.0;
$porcTrans = $kpiTotalDemo > 0 ? round($kpiTransporte     * 100 / $kpiTotalDemo, 1) : 0.0;
$porcCons  = $kpiTotalDemo > 0 ? round($kpiConstrucciones * 100 / $kpiTotalDemo, 1) : 0.0;

// Para el donut, usamos “Abastecimiento” como “principal” demo
$porcGlobal = $kpiTotalDemo > 0 ? $porcAbast : 0.0;

?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Materiales · S-4</title>
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
    background:radial-gradient(circle at left, rgba(59,130,246,.30), transparent 60%);
    border:none;
    color:#e5e7eb;
    font-size:.86rem;
    font-weight:800;
    padding:.55rem .75rem;
    box-shadow:0 6px 14px rgba(0,0,0,.65);
  }
  .accordion-s .accordion-button:not(.collapsed){
    background:radial-gradient(circle at left, rgba(59,130,246,.45), transparent 70%);
    color:#eff6ff;
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
    background:#3b82f6;
    color:#071a33;
    box-shadow:0 8px 22px rgba(59,130,246,.55);
  }
  .gest-btn:hover{ background:#60a5fa; color:#071a33; }
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

  /* Caja doctrinaria S-4 */
  .s4-doctrina{
    background:rgba(2,6,23,.55);
    border:1px dashed rgba(148,163,184,.45);
    border-radius:14px;
    padding:12px 12px;
    margin-top:10px;
  }
  .s4-doctrina h6{
    margin:0 0 8px;
    font-weight:900;
    font-size:.88rem;
    color:#e5e7eb;
    display:flex;
    align-items:center;
    gap:.45rem;
  }
  .s4-doctrina ul{
    margin:0;
    padding-left:18px;
    color:#cbd5f5;
    font-size:.84rem;
  }
  .s4-doctrina li{ margin:6px 0; }
  .quick-access-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit, minmax(210px, 1fr));
    gap:12px;
    margin:16px 0 18px;
  }
  .quick-access-card{
    display:flex;
    align-items:flex-start;
    gap:12px;
    padding:14px 16px;
    border-radius:16px;
    text-decoration:none;
    background:rgba(15,23,42,.82);
    border:1px solid rgba(148,163,184,.28);
    color:#e5e7eb;
    transition:transform .18s ease, border-color .18s ease, box-shadow .18s ease;
  }
  .quick-access-card:hover{
    transform:translateY(-2px);
    border-color:rgba(59,130,246,.45);
    box-shadow:0 14px 30px rgba(0,0,0,.32);
    color:#f8fafc;
  }
  .quick-access-icon{
    width:48px;
    height:48px;
    flex:0 0 48px;
    display:flex;
    align-items:center;
    justify-content:center;
    border-radius:14px;
    background:rgba(59,130,246,.18);
    color:#bfdbfe;
    font-size:1.35rem;
  }
  .quick-access-title{
    font-size:.92rem;
    font-weight:900;
    margin-bottom:2px;
  }
  .quick-access-desc{
    font-size:.8rem;
    color:#cbd5f5;
    line-height:1.45;
  }
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
        <i class="bi bi-box-seam-fill"></i>
        Área S-4 · Materiales
        <span class="badge text-bg-primary">PM</span>
      </div>

      <div class="panel-sub">
        Seleccioná el módulo correspondiente. Este panel consolida información del área de <strong>Material</strong> (S-4)
        y deja preparados accesos a funciones típicas: abastecimiento, mantenimiento, transporte y construcciones.
      </div>

      <div class="quick-access-grid">
        <a class="quick-access-card" href="./materialescarpetacompartida.php">
          <div class="quick-access-icon"><i class="bi bi-folder2-open"></i></div>
          <div>
            <div class="quick-access-title">Carpeta compartida</div>
            <div class="quick-access-desc">Abrí el explorador de archivos del área Materiales en una vista separada.</div>
          </div>
        </a>
      </div>

      <div class="layout-s-row">
        <!-- Sidebar -->
        <aside class="layout-s-sidebar">
          <div class="s-sidebar-box">
            <div class="s-sidebar-title"><i class="bi bi-grid-3x3-gap"></i> Módulos S-4</div>

            <div class="accordion accordion-s" id="accordionS4">

              <div class="accordion-item">
                <h2 class="accordion-header" id="s4-h-inv">
                  <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#s4-inv" aria-expanded="true" aria-controls="s4-inv">
                    <i class="bi bi-clipboard-data me-1"></i> Inventarios / existencias
                  </button>
                </h2>
                <div id="s4-inv" class="accordion-collapse collapse show" aria-labelledby="s4-h-inv" data-bs-parent="#accordionS4">
                  <div class="accordion-body">
                    Control de existencias, altas/bajas, ubicación en depósitos. (Próximamente)
                    <div class="mt-2">
                      <a href="#" class="gest-btn disabled" aria-disabled="true"><i class="bi bi-hourglass-split"></i> Próximamente</a>
                    </div>
                  </div>
                </div>
              </div>

              <div class="accordion-item">
                <h2 class="accordion-header" id="s4-h-abast">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#s4-abast" aria-expanded="false" aria-controls="s4-abast">
                    <i class="bi bi-truck me-1"></i> Abastecimiento
                  </button>
                </h2>
                <div id="s4-abast" class="accordion-collapse collapse" aria-labelledby="s4-h-abast" data-bs-parent="#accordionS4">
                  <div class="accordion-body">
                    Necesidades, pedidos, almacenamiento, distribución y documentación. (Próximamente)
                    <div class="mt-2">
                      <a href="#" class="gest-btn disabled" aria-disabled="true"><i class="bi bi-hourglass-split"></i> Próximamente</a>
                    </div>
                  </div>
                </div>
              </div>

              <div class="accordion-item">
                <h2 class="accordion-header" id="s4-h-mant">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#s4-mant" aria-expanded="false" aria-controls="s4-mant">
                    <i class="bi bi-tools me-1"></i> Mantenimiento
                  </button>
                </h2>
                <div id="s4-mant" class="accordion-collapse collapse" aria-labelledby="s4-h-mant" data-bs-parent="#accordionS4">
                  <div class="accordion-body">
                    Reparación, inspecciones, pruebas y evacuación/reunión/clasificación. (Próximamente)
                    <div class="mt-2">
                      <a href="#" class="gest-btn disabled" aria-disabled="true"><i class="bi bi-hourglass-split"></i> Próximamente</a>
                    </div>
                  </div>
                </div>
              </div>

              <div class="accordion-item">
                <h2 class="accordion-header" id="s4-h-trans">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#s4-trans" aria-expanded="false" aria-controls="s4-trans">
                    <i class="bi bi-signpost-2 me-1"></i> Transporte y movimientos
                  </button>
                </h2>
                <div id="s4-trans" class="accordion-collapse collapse" aria-labelledby="s4-h-trans" data-bs-parent="#accordionS4">
                  <div class="accordion-body">
                    Planeamiento/ejecución de transporte y control de movimientos. (Próximamente)
                    <div class="mt-2">
                      <a href="#" class="gest-btn disabled" aria-disabled="true"><i class="bi bi-hourglass-split"></i> Próximamente</a>
                    </div>
                  </div>
                </div>
              </div>

              <div class="accordion-item">
                <h2 class="accordion-header" id="s4-h-cons">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#s4-cons" aria-expanded="false" aria-controls="s4-cons">
                    <i class="bi bi-building-gear me-1"></i> Construcciones / instalaciones
                  </button>
                </h2>
                <div id="s4-cons" class="accordion-collapse collapse" aria-labelledby="s4-h-cons" data-bs-parent="#accordionS4">
                  <div class="accordion-body">
                    Mantenimiento y reparación de estructuras/instalaciones. (Próximamente)
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
                  Este panel integra, en forma consolidada, información del área <strong>S-4 Material</strong>.
                  Los KPIs muestran valores reales solo si existen tablas compatibles en tu base.
                </p>
                <p class="mb-2">
                  La distribución por “abastecimiento / mantenimiento / transporte / construcciones” queda en
                  <strong>modo demo</strong> hasta que definamos qué campos o tablas representan esas categorías.
                </p>
              </div>

              <div class="s4-doctrina">
                <h6><i class="bi bi-compass"></i> Qué hace S-4 (base doctrinaria)</h6>
                <ul>
                  <li>Responsabilidad primaria sobre el <b>apoyo logístico de material</b>.</li>
                  <li><b>Abastecimiento</b>: necesidades, pedidos/obtención, almacenamiento, seguridad, distribución y documentación.</li>
                  <li><b>Mantenimiento</b>: planear y controlar reparación, inspección, prueba/servicio y evacuación.</li>
                  <li><b>Transporte/movimientos</b>: planear, dirigir y controlar transporte y movimientos; anexos a órdenes de marcha.</li>
                  <li><b>Construcciones</b>: mantenimiento y reparación de estructuras e instalaciones.</li>
                </ul>
              </div>

              <div class="s-kpi-grid mt-3">
                <div class="s-kpi-card">
                  <div class="s-kpi-title">Ítems (tabla items)</div>
                  <div class="s-kpi-main"><i class="bi bi-box"></i> <?= e($kpiItemsTotal ?? '—') ?></div>
                  <div class="s-kpi-sub">Se calcula si existe <code>items</code>.</div>
                </div>

                <div class="s-kpi-card">
                  <div class="s-kpi-title">Documentos (tabla documentos)</div>
                  <div class="s-kpi-main"><i class="bi bi-files"></i> <?= e($kpiDocsTotal ?? '—') ?></div>
                  <div class="s-kpi-sub">Se calcula si existe <code>documentos</code>.</div>
                </div>

                <div class="s-kpi-card">
                  <div class="s-kpi-title">Checklist (tabla checklist)</div>
                  <div class="s-kpi-main"><i class="bi bi-list-check"></i> <?= e($kpiChecklistTotal ?? '—') ?></div>
                  <div class="s-kpi-sub">Se calcula si existe <code>checklist</code>.</div>
                </div>

                <div class="s-kpi-card">
                  <div class="s-kpi-title">Abastecimiento (demo)</div>
                  <div class="s-kpi-main"><?= e($kpiAbastecimiento) ?></div>
                  <div class="s-kpi-sub"><?= e($porcAbast) ?>% (demo).</div>
                  <div class="progress mt-1" style="height:6px;">
                    <div class="progress-bar bg-primary" style="width:<?= e($porcAbast) ?>%"></div>
                  </div>
                </div>

                <div class="s-kpi-card">
                  <div class="s-kpi-title">Mantenimiento (demo)</div>
                  <div class="s-kpi-main"><?= e($kpiMantenimiento) ?></div>
                  <div class="s-kpi-sub"><?= e($porcMant) ?>% (demo).</div>
                  <div class="progress mt-1" style="height:6px;">
                    <div class="progress-bar bg-primary" style="width:<?= e($porcMant) ?>%"></div>
                  </div>
                </div>

                <div class="s-kpi-card">
                  <div class="s-kpi-title">Transporte (demo)</div>
                  <div class="s-kpi-main"><?= e($kpiTransporte) ?></div>
                  <div class="s-kpi-sub"><?= e($porcTrans) ?>% (demo).</div>
                  <div class="progress mt-1" style="height:6px;">
                    <div class="progress-bar bg-primary" style="width:<?= e($porcTrans) ?>%"></div>
                  </div>
                </div>

                <div class="s-kpi-card">
                  <div class="s-kpi-title">Construcciones (demo)</div>
                  <div class="s-kpi-main"><?= e($kpiConstrucciones) ?></div>
                  <div class="s-kpi-sub"><?= e($porcCons) ?>% (demo).</div>
                  <div class="progress mt-1" style="height:6px;">
                    <div class="progress-bar bg-primary" style="width:<?= e($porcCons) ?>%"></div>
                  </div>
                </div>

              </div>
            </div>

            <div class="col-md-5">
              <div class="s-pie-wrapper">
                <div class="s-pie"
                     style="background: conic-gradient(
                         #3b82f6 0 <?= e($porcGlobal) ?>%,
                         rgba(30,64,175,.55) <?= e($porcGlobal) ?>% 100%
                     );">
                  <div class="s-pie-inner">
                    <div class="s-pie-perc"><?= e($porcGlobal) ?>%</div>
                    <div class="s-pie-label">Abastecimiento</div>
                    <div style="font-size:.7rem; color:#9ca3af; margin-top:4px;">
                      <?= e($kpiAbastecimiento) ?>/<?= e($kpiTotalDemo) ?> (demo)
                    </div>
                  </div>
                </div>
              </div>

              <div class="alert alert-dark mt-2" style="background:rgba(15,23,42,.9); border:1px solid rgba(148,163,184,.35); color:#cbd5f5;">
                <div style="font-weight:900; margin-bottom:6px;"><i class="bi bi-wrench-adjustable-circle"></i> Próximo paso</div>
                <div style="font-size:.86rem;">
                  Para dejar de lado el “demo” y calcular KPIs reales por <b>abastecimiento / mantenimiento / transporte / construcciones</b>,
                  indicame qué tabla/campo(s) usás (nombre exacto) o si querés que armemos un schema S-4 (inventario + movimientos + depósitos).
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
