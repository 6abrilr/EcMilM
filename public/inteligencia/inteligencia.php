<?php
// inteligencia.php — Área S-2 Inteligencia (Plana Mayor)
// Robusto para distintas ubicaciones dentro de /ea/public/*
declare(strict_types=1);

// ========= MODO PRODUCCIÓN =========
$OFFLINE_MODE = false;
// ===================================

/* ==========================================================
   Resolver ROOT del proyecto (sin asumir carpeta exacta)
   Busca /auth/bootstrap.php y /config/db.php hacia arriba.
   ========================================================== */
$ROOT = null;
$candidates = [
  realpath(__DIR__ . '/..'),
  realpath(__DIR__ . '/../..'),
  realpath(__DIR__ . '/../../..'),
  realpath(__DIR__ . '/../../../..'),
];

foreach ($candidates as $cand) {
  if ($cand && is_file($cand . '/auth/bootstrap.php') && is_file($cand . '/config/db.php')) {
    $ROOT = $cand;
    break;
  }
}
if (!$ROOT) {
  http_response_code(500);
  exit('No se pudo ubicar ROOT del proyecto (faltan auth/bootstrap.php o config/db.php).');
}

require_once $ROOT . '/auth/bootstrap.php';
if (!$OFFLINE_MODE) {
  require_login();
}
require_once $ROOT . '/config/db.php';

/** @var PDO $pdo */
function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$user = function_exists('current_user') ? current_user() : null;

/* ==========================================================
   BASE WEB robusta (assets /ea/assets)
   ========================================================== */
$SELF_WEB        = (string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
$BASE_DIR_WEB    = rtrim(str_replace('\\','/', dirname($SELF_WEB)), '/');        // /ea/public/... o /ea/public
$BASE_PUBLIC_WEB = rtrim(str_replace('\\','/', dirname($BASE_DIR_WEB)), '/');    // /ea/public
// Si el archivo está directo en /ea/public, dirname(/ea/public) => /ea
$BASE_APP_WEB    = rtrim(str_replace('\\','/', dirname($BASE_PUBLIC_WEB)), '/'); // /ea
if (substr($BASE_PUBLIC_WEB, -7) !== '/public') {
  // fallback: si la ruta no cae en /public por alguna razón, intentamos deducir /ea
  $BASE_APP_WEB = rtrim(str_replace('\\','/', dirname($BASE_DIR_WEB)), '/');
}
$ASSET_WEB       = $BASE_APP_WEB . '/assets';

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
   KPIs S-2 (robustos / no rompen)
   ========================================================== */

// KPIs genéricos (si hay tablas comunes del proyecto "unidad")
$kpiDocsTotal            = null; // int|null
$kpiDocsS2               = null; // int|null (si existe columna area/categoria)
$kpiChecklistsTotal      = null; // int|null
$kpiIncidentesTotal      = null; // int|null (si existe tabla seguridad_incidentes o similar)

// DEMO (hasta conectar tablas específicas de inteligencia)
$demoAmenazasActivas     = 0;
$demoEEIDefinidos        = 0;
$demoPedidosInfoPend     = 0;
$demoMedidasCIVigentes   = 0;

// Documentos (si existe)
try {
  if (db_table_exists($pdo, 'documentos')) {
    $st = $pdo->query("SELECT COUNT(*) FROM documentos");
    $kpiDocsTotal = (int)($st->fetchColumn() ?: 0);

    // Intento de filtro "S2" si existe columna area / seccion / tipo / categoria (no invento)
    $possibleCols = ['area', 'seccion', 'tipo', 'categoria', 'modulo', 'grupo'];
    $colFound = null;
    foreach ($possibleCols as $c) {
      if (db_column_exists($pdo, 'documentos', $c)) { $colFound = $c; break; }
    }
    if ($colFound) {
      $st2 = $pdo->prepare("SELECT COUNT(*) FROM documentos WHERE {$colFound} IN ('S2','S-2','INTELIGENCIA','Inteligencia')");
      $st2->execute();
      $kpiDocsS2 = (int)($st2->fetchColumn() ?: 0);
    }
  }
} catch (Throwable $e) {
  $kpiDocsTotal = null;
  $kpiDocsS2    = null;
}

// Checklists (si existe)
try {
  if (db_table_exists($pdo, 'checklist')) {
    $st = $pdo->query("SELECT COUNT(*) FROM checklist");
    $kpiChecklistsTotal = (int)($st->fetchColumn() ?: 0);
  }
} catch (Throwable $e) {
  $kpiChecklistsTotal = null;
}

// Incidentes de seguridad (si existe una tabla típica)
try {
  $incidentTables = ['seguridad_incidentes', 'incidentes_seguridad', 's2_incidentes', 'ciber_incidentes'];
  $found = null;
  foreach ($incidentTables as $t) {
    if (db_table_exists($pdo, $t)) { $found = $t; break; }
  }
  if ($found) {
    $st = $pdo->query("SELECT COUNT(*) FROM {$found}");
    $kpiIncidentesTotal = (int)($st->fetchColumn() ?: 0);
  }
} catch (Throwable $e) {
  $kpiIncidentesTotal = null;
}

?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Inteligencia · S-2</title>
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
    margin-right:17px;
    margin-top:4px;
    display:flex;
    gap:8px;
  }

  /* Layout */
  .layout-s-row{ display:flex; flex-wrap:wrap; gap:18px; }
  .layout-s-sidebar{ flex:0 0 310px; max-width:380px; }
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
    background:radial-gradient(circle at left, rgba(59,130,246,.35), transparent 60%);
    border:none;
    color:#e5e7eb;
    font-size:.86rem;
    font-weight:900;
    padding:.55rem .75rem;
    box-shadow:0 6px 14px rgba(0,0,0,.65);
  }
  .accordion-s .accordion-button:not(.collapsed){
    background:radial-gradient(circle at left, rgba(59,130,246,.55), transparent 70%);
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
    color:#0b1b3a;
    box-shadow:0 8px 22px rgba(59,130,246,.45);
  }
  .gest-btn:hover{ background:#60a5fa; color:#0b1b3a; }
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
    flex:1 1 220px;
    min-width:190px;
    background:rgba(15,23,42,.96);
    border-radius:14px;
    border:1px solid rgba(148,163,184,.45);
    padding:10px 12px;
    font-size:.78rem;
  }
  .s-kpi-title{ text-transform:uppercase; letter-spacing:.06em; color:#9ca3af; font-weight:800; margin-bottom:4px; }
  .s-kpi-main{ font-size:1.05rem; font-weight:900; display:flex; align-items:center; gap:.45rem; }
  .s-kpi-sub{ font-size:.78rem; color:#cbd5f5; }

  /* Caja doctrinaria S-2 */
  .s2-doctrina{
    background:rgba(2,6,23,.55);
    border:1px dashed rgba(148,163,184,.45);
    border-radius:14px;
    padding:12px 12px;
    margin-top:10px;
  }
  .s2-doctrina h6{
    margin:0 0 8px;
    font-weight:900;
    font-size:.88rem;
    color:#e5e7eb;
    display:flex;
    align-items:center;
    gap:.45rem;
  }
  .s2-doctrina ul{
    margin:0;
    padding-left:18px;
    color:#cbd5f5;
    font-size:.84rem;
  }
  .s2-doctrina li{ margin:6px 0; }

  .note{
    background:rgba(15,23,42,.9);
    border:1px solid rgba(148,163,184,.35);
    border-radius:14px;
    padding:12px;
    color:#cbd5f5;
  }
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
  code{ color:#e5e7eb; }
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
      <a href="<?= e($BASE_PUBLIC_WEB) ?>/inicio.php" class="btn btn-success btn-sm" style="font-weight:700; padding:.35rem .9rem;">
        <i class="bi bi-house-door"></i> Inicio
      </a>
    </div>
  </div>
</header>

<div class="page-wrap">
  <div class="container-main">
    <div class="panel">
      <div class="panel-title">
        <i class="bi bi-eye-fill"></i>
        Área S-2 · Inteligencia
        <span class="badge text-bg-primary">PM</span>
      </div>

      <div class="panel-sub">
        Panel de Inteligencia orientado a: enemigo/oponente, terreno y meteorología, producción y distribución de inteligencia,
        y medidas de seguridad de contrainteligencia (CI) para la protección del elemento.
      </div>

      <div class="quick-access-grid">
        <a class="quick-access-card" href="./inteligenciacarpetacompartida.php">
          <div class="quick-access-icon"><i class="bi bi-folder2-open"></i></div>
          <div>
            <div class="quick-access-title">Carpeta compartida</div>
            <div class="quick-access-desc">Abrí el explorador de archivos de Inteligencia desde una página separada.</div>
          </div>
        </a>
      </div>

      <div class="layout-s-row">
        <!-- Sidebar -->
        <aside class="layout-s-sidebar">
          <div class="s-sidebar-box">
            <div class="s-sidebar-title"><i class="bi bi-grid-3x3-gap"></i> Módulos S-2</div>

            <div class="accordion accordion-s" id="accordionS2">

              <div class="accordion-item">
                <h2 class="accordion-header" id="s2-h-situacion">
                  <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#s2-situacion" aria-expanded="true" aria-controls="s2-situacion">
                    <i class="bi bi-broadcast-pin me-1"></i> Situación (enemigo/oponente)
                  </button>
                </h2>
                <div id="s2-situacion" class="accordion-collapse collapse show" aria-labelledby="s2-h-situacion" data-bs-parent="#accordionS2">
                  <div class="accordion-body">
                    Actualización permanente: situación, capacidades y debilidades del oponente.
                    <div class="mt-2">
                      <a href="#" class="gest-btn disabled" aria-disabled="true"><i class="bi bi-hourglass-split"></i> Próximamente</a>
                    </div>
                  </div>
                </div>
              </div>

              <div class="accordion-item">
                <h2 class="accordion-header" id="s2-h-terreno">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#s2-terreno" aria-expanded="false" aria-controls="s2-terreno">
                    <i class="bi bi-map me-1"></i> Terreno / Meteorología / Imágenes
                  </button>
                </h2>
                <div id="s2-terreno" class="accordion-collapse collapse" aria-labelledby="s2-h-terreno" data-bs-parent="#accordionS2">
                  <div class="accordion-body">
                    Inteligencia del terreno y condiciones meteorológicas; cartografía e imágenes.
                    <div class="mt-2">
                      <a href="#" class="gest-btn disabled" aria-disabled="true"><i class="bi bi-hourglass-split"></i> Próximamente</a>
                    </div>
                  </div>
                </div>
              </div>

              <div class="accordion-item">
                <h2 class="accordion-header" id="s2-h-eei">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#s2-eei" aria-expanded="false" aria-controls="s2-eei">
                    <i class="bi bi-bullseye me-1"></i> EEI + Plan de obtención
                  </button>
                </h2>
                <div id="s2-eei" class="accordion-collapse collapse" aria-labelledby="s2-h-eei" data-bs-parent="#accordionS2">
                  <div class="accordion-body">
                    Propuesta de EEI, cancelación/modificación, plan de obtención y órdenes/pedidos.
                    <div class="mt-2">
                      <a href="#" class="gest-btn disabled" aria-disabled="true"><i class="bi bi-hourglass-split"></i> Próximamente</a>
                    </div>
                  </div>
                </div>
              </div>

              <div class="accordion-item">
                <h2 class="accordion-header" id="s2-h-prod">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#s2-prod" aria-expanded="false" aria-controls="s2-prod">
                    <i class="bi bi-graph-up-arrow me-1"></i> Producción y distribución
                  </button>
                </h2>
                <div id="s2-prod" class="accordion-collapse collapse" aria-labelledby="s2-h-prod" data-bs-parent="#accordionS2">
                  <div class="accordion-body">
                    Apreciación y análisis gráfico; procesamiento; distribución por “necesidad de saber”; anexo inteligencia.
                    <div class="mt-2">
                      <a href="#" class="gest-btn disabled" aria-disabled="true"><i class="bi bi-hourglass-split"></i> Próximamente</a>
                    </div>
                  </div>
                </div>
              </div>

              <div class="accordion-item">
                <h2 class="accordion-header" id="s2-h-ci">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#s2-ci" aria-expanded="false" aria-controls="s2-ci">
                    <i class="bi bi-shield-lock me-1"></i> Seguridad / Contrainteligencia
                  </button>
                </h2>
                <div id="s2-ci" class="accordion-collapse collapse" aria-labelledby="s2-h-ci" data-bs-parent="#accordionS2">
                  <div class="accordion-body">
                    Medidas CI, PON de inteligencia/CI, autorizados a abrir documentación clasificada.
                    <div class="mt-2">
                      <a href="#" class="gest-btn disabled" aria-disabled="true"><i class="bi bi-hourglass-split"></i> Próximamente</a>
                    </div>
                  </div>
                </div>
              </div>

              <div class="accordion-item">
                <h2 class="accordion-header" id="s2-h-estudio">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#s2-estudio" aria-expanded="false" aria-controls="s2-estudio">
                    <i class="bi bi-file-earmark-check me-1"></i> Estudio de seguridad del elemento
                  </button>
                </h2>
                <div id="s2-estudio" class="accordion-collapse collapse" aria-labelledby="s2-h-estudio" data-bs-parent="#accordionS2">
                  <div class="accordion-body">
                    Confección/actualización del estudio de seguridad y proposiciones para subsanar debilidades.
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
            <div class="col-lg-7">
              <div class="s-main-text">
                <p class="mb-2">
                  Este panel está orientado a la función S-2: producir inteligencia útil para la resolución del Jefe,
                  y asegurar la protección del elemento mediante medidas de <strong>seguridad y contrainteligencia</strong>.
                </p>
                <p class="mb-2">
                  Los indicadores “reales” solo se calculan si existen tablas/columnas en tu base.
                  Lo demás queda en modo DEMO hasta que definamos el esquema específico de S-2.
                </p>
              </div>

              <div class="s2-doctrina">
                <h6><i class="bi bi-compass"></i> Misiones y funciones S-2 (Sección III)</h6>
                <ul>
                  <li>Responsabilidad primaria sobre <b>enemigo/oponente</b>, <b>terreno</b> y <b>meteorología</b> para base de resolución y seguridad.</li>
                  <li>Asesorar al Jefe: situación actualizada, capacidades/debilidades, inteligencia del ambiente geográfico y efectos en operaciones.</li>
                  <li>Preparar/actualizar la <b>apreciación de inteligencia</b> y el <b>análisis gráfico</b>.</li>
                  <li>Proponer <b>EEI</b> (elementos esenciales de inteligencia), su cancelación o modificación.</li>
                  <li>Elaborar <b>plan de obtención</b> y preparar órdenes/pedidos de información; procesar lo obtenido.</li>
                  <li>Distribuir inteligencia/información según <b>“necesidad de saber”</b> y preparar el <b>anexo inteligencia</b>.</li>
                  <li>Coordinar con S-3: exploración, vigilancia de combate y obtención; instrucción en inteligencia/CI y “conciencia de CI”.</li>
                  <li>Proponer medidas de <b>contrainteligencia</b>; coordinar cartografía e imágenes.</li>
                  <li>Confeccionar/actualizar el <b>estudio de seguridad del elemento</b> y proponer correcciones.</li>
                  <li>Mantener enlace con inteligencia del escalón superior y vecinos.</li>
                  <li>Preparar PON de inteligencia/CI y proponer autorizados para abrir documentación clasificada por mesa de entrada/salida.</li>
                </ul>
              </div>

              <div class="s-kpi-grid mt-3">
                <div class="s-kpi-card">
                  <div class="s-kpi-title">Documentos (sistema)</div>
                  <div class="s-kpi-main"><i class="bi bi-files"></i> <?= e($kpiDocsTotal ?? '—') ?></div>
                  <div class="s-kpi-sub">Cuenta si existe <code>documentos</code>.</div>
                </div>

                <div class="s-kpi-card">
                  <div class="s-kpi-title">Documentos “S2” (si se puede filtrar)</div>
                  <div class="s-kpi-main"><i class="bi bi-filter-circle"></i> <?= e($kpiDocsS2 ?? '—') ?></div>
                  <div class="s-kpi-sub">Solo si hay columna tipo/área/categoría en <code>documentos</code>.</div>
                </div>

                <div class="s-kpi-card">
                  <div class="s-kpi-title">Checklists (sistema)</div>
                  <div class="s-kpi-main"><i class="bi bi-list-check"></i> <?= e($kpiChecklistsTotal ?? '—') ?></div>
                  <div class="s-kpi-sub">Cuenta si existe <code>checklist</code>.</div>
                </div>

                <div class="s-kpi-card">
                  <div class="s-kpi-title">Incidentes (si existe tabla)</div>
                  <div class="s-kpi-main"><i class="bi bi-exclamation-triangle"></i> <?= e($kpiIncidentesTotal ?? '—') ?></div>
                  <div class="s-kpi-sub">Detecta tablas típicas (si están).</div>
                </div>

                <!-- DEMO: hasta que definamos esquema S-2 -->
                <div class="s-kpi-card">
                  <div class="s-kpi-title">Amenazas activas (demo)</div>
                  <div class="s-kpi-main"><i class="bi bi-activity"></i> <?= e($demoAmenazasActivas) ?></div>
                  <div class="s-kpi-sub">Pendiente de modelo de datos S-2.</div>
                </div>

                <div class="s-kpi-card">
                  <div class="s-kpi-title">EEI definidos (demo)</div>
                  <div class="s-kpi-main"><i class="bi bi-bullseye"></i> <?= e($demoEEIDefinidos) ?></div>
                  <div class="s-kpi-sub">Pendiente de módulo EEI.</div>
                </div>

                <div class="s-kpi-card">
                  <div class="s-kpi-title">Pedidos info pendientes (demo)</div>
                  <div class="s-kpi-main"><i class="bi bi-inbox"></i> <?= e($demoPedidosInfoPend) ?></div>
                  <div class="s-kpi-sub">Pendiente de pedidos/órdenes de obtención.</div>
                </div>

                <div class="s-kpi-card">
                  <div class="s-kpi-title">Medidas CI vigentes (demo)</div>
                  <div class="s-kpi-main"><i class="bi bi-shield-lock"></i> <?= e($demoMedidasCIVigentes) ?></div>
                  <div class="s-kpi-sub">Pendiente de PON/medidas CI.</div>
                </div>
              </div>
            </div>

            <div class="col-lg-5">
              <div class="note">
                <div style="font-weight:900; margin-bottom:6px;">
                  <i class="bi bi-wrench-adjustable-circle"></i> Próximo paso (para hacerlo REAL)
                </div>
                <div style="font-size:.86rem;">
                  Para dejar de lado DEMO y armar los módulos S-2 “de verdad”, pasame:
                  <ul style="margin:8px 0 0; padding-left:18px;">
                    <li><code>SHOW TABLES;</code> (solo el listado)</li>
                    <li>y si ya tenés tablas S-2: <code>SHOW COLUMNS FROM &lt;tabla&gt;;</code></li>
                  </ul>
                  Con eso te diseño el esquema mínimo doctrinario:
                  EEI → Plan obtención → Pedidos → Procesamiento → Distribución (necesidad de saber) → Anexo,
                  más Estudio de Seguridad y medidas CI.
                </div>
              </div>

              <div class="note mt-3">
                <div style="font-weight:900; margin-bottom:6px;">
                  <i class="bi bi-shield-check"></i> Estudio de seguridad (plantilla de contenido)
                </div>
                <div style="font-size:.86rem;">
                  Cuando lo implementemos, el “Estudio de Seguridad del Elemento” va a guardar, mínimo:
                  <ul style="margin:8px 0 0; padding-left:18px;">
                    <li>Vulnerabilidades/déficits detectados</li>
                    <li>Impacto / probabilidad / prioridad</li>
                    <li>Medidas correctivas propuestas + responsable</li>
                    <li>Estado (abierto/en curso/cerrado) + fechas</li>
                    <li>Referencias (órdenes/PON/documentación)</li>
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
