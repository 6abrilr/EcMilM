<?php
// public/personal/personal.php — Área S-1 Personal
declare(strict_types=1);

// ========= MODO PRODUCCIÓN =========
$OFFLINE_MODE = false;
// ===================================

// Desde /ea/public/personal => /ea/auth y /ea/config
require_once __DIR__ . '/../../auth/bootstrap.php';
if (!$OFFLINE_MODE) {
  require_login();
}
require_once __DIR__ . '/../../config/db.php';

/** @var PDO $pdo */
function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$user = function_exists('current_user') ? current_user() : null;

/* ==========================================================
   BASE WEB robusta
   - Estás en: /ea/public/personal/personal.php
   - Assets están en: /ea/assets/img
   ========================================================== */
$SELF_WEB        = (string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
$BASE_DIR_WEB    = rtrim(str_replace('\\','/', dirname($SELF_WEB)), '/');            // /ea/public/personal
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
   Resumen S-1 (robusto)
   - Total personal: personal_unidad
   - KPIs extra: si existen tablas (sin romper si no están)
   ========================================================== */
$totalPersonal = 0;

try {
  if (db_table_exists($pdo, 'personal_unidad')) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM personal_unidad");
    $totalPersonal = (int)($stmt->fetchColumn() ?: 0);
  }
} catch (Throwable $e) {
  $totalPersonal = 0;
}

/**
 * Distribución por situación:
 * - MODO DEMO hasta que definamos los campos reales.
 * - (No asumo columnas. Cuando vos me digas qué campo usar, lo conecto.)
 */
$personalActivo   = 0;
$personalLicencia = 0;
$personalBaja     = 0;

$personalOtros  = max($totalPersonal - ($personalActivo + $personalLicencia + $personalBaja), 0);

$porcActivo   = $totalPersonal > 0 ? round($personalActivo   * 100 / $totalPersonal, 1) : 0.0;
$porcLicencia = $totalPersonal > 0 ? round($personalLicencia * 100 / $totalPersonal, 1) : 0.0;
$porcBaja     = $totalPersonal > 0 ? round($personalBaja     * 100 / $totalPersonal, 1) : 0.0;

$porcGlobal   = $totalPersonal > 0 ? round($personalActivo * 100 / $totalPersonal, 1) : 0.0;

/* KPIs extra (si existen tablas reales) */
$kpiDocumentosTotal = null;   // int|null
$kpiPartesTotal     = null;   // int|null
$kpiAltasTotal      = null;   // int|null

try {
  if (db_table_exists($pdo, 'personal_documentos')) {
    $st = $pdo->query("SELECT COUNT(*) FROM personal_documentos");
    $kpiDocumentosTotal = (int)($st->fetchColumn() ?: 0);
  }
} catch (Throwable $e) {
  $kpiDocumentosTotal = null;
}

try {
  // Tu esquema real menciona sanidad_partes_enfermo; lo trato como histórico.
  if (db_table_exists($pdo, 'sanidad_partes_enfermo')) {
    // Si existen columnas para distinguir “parte” vs “alta”, lo usamos; si no, mostramos total.
    $hasTieneParte = db_column_exists($pdo, 'sanidad_partes_enfermo', 'tiene_parte');
    if ($hasTieneParte) {
      // Asumo convención: tiene_parte=1 parte, tiene_parte=0 alta (si vos lo manejás distinto, lo ajustamos)
      $st1 = $pdo->query("SELECT COUNT(*) FROM sanidad_partes_enfermo WHERE tiene_parte = 1");
      $kpiPartesTotal = (int)($st1->fetchColumn() ?: 0);

      $st0 = $pdo->query("SELECT COUNT(*) FROM sanidad_partes_enfermo WHERE tiene_parte = 0");
      $kpiAltasTotal = (int)($st0->fetchColumn() ?: 0);
    } else {
      $st = $pdo->query("SELECT COUNT(*) FROM sanidad_partes_enfermo");
      $kpiPartesTotal = (int)($st->fetchColumn() ?: 0);
      $kpiAltasTotal  = null;
    }
  }
} catch (Throwable $e) {
  $kpiPartesTotal = null;
  $kpiAltasTotal  = null;
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Personal · S-1</title>
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
    background:radial-gradient(circle at left, rgba(34,197,94,.35), transparent 60%);
    border:none;
    color:#e5e7eb;
    font-size:.86rem;
    font-weight:800;
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

  /* Caja doctrinaria S-1 */
  .s1-doctrina{
    background:rgba(2,6,23,.55);
    border:1px dashed rgba(148,163,184,.45);
    border-radius:14px;
    padding:12px 12px;
    margin-top:10px;
  }
  .s1-doctrina h6{
    margin:0 0 8px;
    font-weight:900;
    font-size:.88rem;
    color:#e5e7eb;
    display:flex;
    align-items:center;
    gap:.45rem;
  }
  .s1-doctrina ul{
    margin:0;
    padding-left:18px;
    color:#cbd5f5;
    font-size:.84rem;
  }
  .s1-doctrina li{ margin:6px 0; }
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
        <i class="bi bi-people-fill"></i>
        Área S-1 · Personal
        <span class="badge text-bg-success">PM</span>
      </div>

      <div class="panel-sub">
        Seleccioná el módulo correspondiente. Este panel consolida información de personal y deja
        preparados accesos a funciones típicas de S-1 (documentación, mesa de entradas/salidas, disciplina).
      </div>

      <div class="layout-s-row">
        <!-- Sidebar -->
        <aside class="layout-s-sidebar">
          <div class="s-sidebar-box">
            <div class="s-sidebar-title"><i class="bi bi-grid-3x3-gap"></i> Módulos S-1</div>

            <div class="accordion accordion-s" id="accordionS1">

              <div class="accordion-item">
                <h2 class="accordion-header" id="s1-h-personal">
                  <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#s1-personal" aria-expanded="true" aria-controls="s1-personal">
                    <i class="bi bi-person-vcard me-1"></i> Personal (base central)
                  </button>
                </h2>
                <div id="s1-personal" class="accordion-collapse collapse show" aria-labelledby="s1-h-personal" data-bs-parent="#accordionS1">
                  <div class="accordion-body">
                    Base central del personal (grado, destino, situación, documentos, sanidad, etc.).
                    <div class="mt-2">
                      <a href="personal_lista.php" class="gest-btn"><i class="bi bi-box-arrow-in-right"></i> Entrar</a>
                    </div>
                  </div>
                </div>
              </div>

              <div class="accordion-item">
                <h2 class="accordion-header" id="s1-h-roles">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#s1-roles" aria-expanded="false" aria-controls="s1-roles">
                    <i class="bi bi-shield-check me-1"></i> Rol de combate
                  </button>
                </h2>
                <div id="s1-roles" class="accordion-collapse collapse" aria-labelledby="s1-h-roles" data-bs-parent="#accordionS1">
                  <div class="accordion-body">
                    Gestión del rol de combate (más adelante: asignación por persona).
                    <div class="mt-2">
                      <a href="../rol_combate.php" class="gest-btn"><i class="bi bi-box-arrow-in-right"></i> Entrar</a>
                    </div>
                  </div>
                </div>
              </div>

              <div class="accordion-item">
                <h2 class="accordion-header" id="s1-h-edutropa">
                  <button class="accordion-button collapsed" type="button"
                          data-bs-toggle="collapse"
                          data-bs-target="#s1-edutropa"
                          aria-expanded="false"
                          aria-controls="s1-edutropa">
                    <i class="bi bi-mortarboard me-1"></i> Educación operacional de la tropa
                  </button>
                </h2>
                <div id="s1-edutropa" class="accordion-collapse collapse"
                     aria-labelledby="s1-h-edutropa"
                     data-bs-parent="#accordionS1">
                  <div class="accordion-body">
                    Consulta y gestión de documentación / registros (vía S-3).
                    <div class="mt-2">
                      <a href="../operaciones
                      /operaciones_educacion_tropa.php" class="gest-btn"><i class="bi bi-box-arrow-in-right"></i> Entrar</a>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Preparado doctrinariamente para S-1 (sin romper: botones deshabilitados) -->
              <div class="accordion-item">
                <h2 class="accordion-header" id="s1-h-docu">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#s1-docu" aria-expanded="false" aria-controls="s1-docu">
                    <i class="bi bi-folder2-open me-1"></i> Documentación S-1
                  </button>
                </h2>
                <div id="s1-docu" class="accordion-collapse collapse" aria-labelledby="s1-h-docu" data-bs-parent="#accordionS1">
                  <div class="accordion-body">
                    Redacción/actualización/elevación de documentación. (Próximamente)
                    <div class="mt-2">
                      <a href="#" class="gest-btn disabled" aria-disabled="true"><i class="bi bi-hourglass-split"></i> Próximamente</a>
                    </div>
                  </div>
                </div>
              </div>

              <div class="accordion-item">
                <h2 class="accordion-header" id="s1-h-mesa">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#s1-mesa" aria-expanded="false" aria-controls="s1-mesa">
                    <i class="bi bi-inbox me-1"></i> Mesa de entradas / salidas
                  </button>
                </h2>
                <div id="s1-mesa" class="accordion-collapse collapse" aria-labelledby="s1-h-mesa" data-bs-parent="#accordionS1">
                  <div class="accordion-body">
                    Supervisión y control de plazos. (Próximamente)
                    <div class="mt-2">
                      <a href="#" class="gest-btn disabled" aria-disabled="true"><i class="bi bi-hourglass-split"></i> Próximamente</a>
                    </div>
                  </div>
                </div>
              </div>

              <div class="accordion-item">
                <h2 class="accordion-header" id="s1-h-disc">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#s1-disc" aria-expanded="false" aria-controls="s1-disc">
                    <i class="bi bi-journal-text me-1"></i> Disciplina (Consejo)
                  </button>
                </h2>
                <div id="s1-disc" class="accordion-collapse collapse" aria-labelledby="s1-h-disc" data-bs-parent="#accordionS1">
                  <div class="accordion-body">
                    Secretaría del consejo de disciplina guarnicional. (Próximamente)
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
                  Este panel integra, en forma consolidada, la situación del <strong>personal</strong>.
                  El <strong>total</strong> se obtiene desde <code>personal_unidad</code>.
                </p>
                <p class="mb-2">
                  La distribución por “en servicio / licencia / baja” queda en <strong>modo demo</strong>
                  hasta que confirmemos el/los campos reales para calcularlo sin suposiciones.
                </p>
              </div>

              <div class="s1-doctrina">
                <h6><i class="bi bi-compass"></i> Qué hace S-1 (base doctrinaria)</h6>
                <ul>
                  <li>Responsabilidad primaria sobre planeamiento, organización, coordinación y control del personal bajo control militar directo.</li>
                  <li>Redacción/actualización/elevación de documentación según publicaciones vigentes.</li>
                  <li>Supervisión de mesa de entradas y salidas.</li>
                  <li>Secretaría del consejo de disciplina guarnicional (según Código de Disciplina).</li>
                  <li>Coordinación con S-3 para propuestas reglamentarias y lecciones aprendidas del área.</li>
                </ul>
              </div>

              <div class="s-kpi-grid mt-3">
                <div class="s-kpi-card">
                  <div class="s-kpi-title">Personal total</div>
                  <div class="s-kpi-main"><i class="bi bi-people"></i> <?= e($totalPersonal) ?></div>
                  <div class="s-kpi-sub">Incluye personal en todas las situaciones.</div>
                </div>

                <div class="s-kpi-card">
                  <div class="s-kpi-title">Documentos (sistema)</div>
                  <div class="s-kpi-main"><i class="bi bi-files"></i> <?= e($kpiDocumentosTotal ?? '—') ?></div>
                  <div class="s-kpi-sub">Se calcula si existe <code>personal_documentos</code>.</div>
                </div>

                <div class="s-kpi-card">
                  <div class="s-kpi-title">Partes enfermo (histórico)</div>
                  <div class="s-kpi-main"><i class="bi bi-clipboard-pulse"></i> <?= e($kpiPartesTotal ?? '—') ?></div>
                  <div class="s-kpi-sub">Se calcula si existe <code>sanidad_partes_enfermo</code>.</div>
                </div>

                <div class="s-kpi-card">
                  <div class="s-kpi-title">Altas (histórico)</div>
                  <div class="s-kpi-main"><i class="bi bi-check2-circle"></i> <?= e($kpiAltasTotal ?? '—') ?></div>
                  <div class="s-kpi-sub">Solo si puede distinguirse por columna.</div>
                </div>

                <div class="s-kpi-card">
                  <div class="s-kpi-title">En servicio (demo)</div>
                  <div class="s-kpi-main"><?= e($personalActivo) ?></div>
                  <div class="s-kpi-sub"><?= e($porcActivo) ?>% del total.</div>
                  <div class="progress mt-1" style="height:6px;">
                    <div class="progress-bar bg-success" style="width:<?= e($porcActivo) ?>%"></div>
                  </div>
                </div>

                <div class="s-kpi-card">
                  <div class="s-kpi-title">En licencia (demo)</div>
                  <div class="s-kpi-main"><?= e($personalLicencia) ?></div>
                  <div class="s-kpi-sub"><?= e($porcLicencia) ?>% del total.</div>
                  <div class="progress mt-1" style="height:6px;">
                    <div class="progress-bar bg-success" style="width:<?= e($porcLicencia) ?>%"></div>
                  </div>
                </div>

                <div class="s-kpi-card">
                  <div class="s-kpi-title">Bajas (demo)</div>
                  <div class="s-kpi-main"><?= e($personalBaja) ?></div>
                  <div class="s-kpi-sub"><?= e($porcBaja) ?>% del total.</div>
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
                    <div class="s-pie-label">Personal en servicio</div>
                    <div style="font-size:.7rem; color:#9ca3af; margin-top:4px;">
                      <?= e($personalActivo) ?>/<?= e($totalPersonal) ?> efectivos (demo)
                    </div>
                  </div>
                </div>
              </div>

              <div class="alert alert-dark mt-2" style="background:rgba(15,23,42,.9); border:1px solid rgba(148,163,184,.35); color:#cbd5f5;">
                <div style="font-weight:900; margin-bottom:6px;"><i class="bi bi-wrench-adjustable-circle"></i> Próximo paso</div>
                <div style="font-size:.86rem;">
                  Para dejar de lado el “demo” y calcular <b>en servicio / licencia / baja</b>, necesito que me indiques
                  qué campo/s en <code>personal_unidad</code> representan la situación de revista (nombre exacto).
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
