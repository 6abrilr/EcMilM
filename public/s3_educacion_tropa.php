<?php
// public/s3_educacion_tropa.php — Panel S-3 Educación de la tropa
declare(strict_types=1);

$OFFLINE_MODE = false;

require_once __DIR__ . '/../auth/bootstrap.php';
if (!$OFFLINE_MODE) require_login();
require_once __DIR__ . '/../config/db.php';

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* ===== Volver dinámico (S1 / S3) ===== */
$from = strtolower(trim((string)($_GET['from'] ?? '')));

if (!in_array($from, ['s1','s3'], true)) {
  $from = '';
}

// fallback por referer si no vino el parámetro
if ($from === '' && !empty($_SERVER['HTTP_REFERER'])) {
  $refPath = parse_url((string)$_SERVER['HTTP_REFERER'], PHP_URL_PATH) ?: '';
  $refBase = basename($refPath);

  if ($refBase === 'areas_s1.php') $from = 's1';
  if ($refBase === 'areas_s3.php') $from = 's3';
}

if ($from === '') $from = 's3'; // default

$backHref  = ($from === 's1') ? 'areas_s1.php' : 'areas_s3.php';
$backLabel = ($from === 's1') ? '⬅ Volver a S-1' : '⬅ Volver a S-3';
$kickerTxt = ($from === 's1') ? 'S-1 · PERSONAL' : 'S-3 · OPERACIONES';

/* ===== Assets ===== */
$PUBLIC_URL = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$APP_URL    = rtrim(dirname($PUBLIC_URL), '/');
$ASSETS     = ($APP_URL === '' ? '' : $APP_URL) . '/assets';
$IMG_BG     = $ASSETS . '/img/fondo.png';
$ESCUDO     = $ASSETS . '/img/escudo_bcom602.png';

/*
 * ===========================
 *  MODO DEMO — KPIs por ciclo
 * ===========================
 * (luego esto se saca de la BD)
 */

/* CICLO I */
$totalCiclo1 = 15;
$cumplido1   = 7;
$porc1       = $totalCiclo1 > 0 ? round($cumplido1 * 100 / $totalCiclo1, 1) : 0;

/* CICLO II */
$totalCiclo2 = 18;
$cumplido2   = 11;
$porc2       = $totalCiclo2 > 0 ? round($cumplido2 * 100 / $totalCiclo2, 1) : 0;

/* CICLO III */
$totalCiclo3 = 20;
$cumplido3   = 9;
$porc3       = $totalCiclo3 > 0 ? round($cumplido3 * 100 / $totalCiclo3, 1) : 0;

/* N.I.A. */
$totalNIA    = 12;
$cumplidoNIA = 4;
$porcNIA     = $totalNIA > 0 ? round($cumplidoNIA * 100 / $totalNIA, 1) : 0;
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>S-3 · Educación operacional de la tropa · B Com 602</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/theme-602.css">
<link rel="icon" type="image/png" href="../assets/img/bcom602.png">
<style>
  :root{
    --bg-dark: #020617;
    --card-bg: rgba(15,23,42,.94);
    --text-main: #e5e7eb;
    --text-muted: #9ca3af;
    --accent: #22c55e;
  }

  *{ box-sizing:border-box; }

  body{
    min-height:100vh;
    margin:0;
    font-family:system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,sans-serif;
    background:
      radial-gradient(circle at top left, rgba(56,189,248,.22), transparent 60%),
      radial-gradient(circle at bottom right, rgba(34,197,94,.18), transparent 55%),
      url("<?= e($IMG_BG) ?>") center/cover fixed;
    background-color:var(--bg-dark);
    color:var(--text-main);
    position:relative;
    overflow-x:hidden;
  }

  body::before{
    content:"";
    position:fixed;
    inset:0;
    background:radial-gradient(circle at top, rgba(15,23,42,.75), rgba(15,23,42,.95));
    pointer-events:none;
    z-index:-1;
  }

  .page-wrap{
    padding:24px 16px 32px;
  }

  .container-main{
    max-width:1200px;
    margin:0 auto;
  }

  /* ===== Header ===== */

  header.brand-hero{
    padding:14px 0 6px;
  }

  .hero-inner{
    max-width:1200px;
    margin:0 auto;
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:16px;
  }

  .brand-left{
    display:flex;
    align-items:center;
    gap:14px;
  }

  .brand-logo{
    height:56px;
    width:auto;
    filter:drop-shadow(0 0 10px rgba(0,0,0,.8));
  }

  .brand-title{
    font-weight:800;
    font-size:1.1rem;
    letter-spacing:.03em;
  }

  .brand-sub{
    font-size:.8rem;
    color:#cbd5f5;
  }

  .header-back{
    display:flex;
    flex-wrap:wrap;
    gap:8px;
  }

  .btn-ghost{
    border-radius:999px;
    border:1px solid rgba(148,163,184,.55);
    background:rgba(15,23,42,.8);
    color:var(--text-main);
    font-size:.8rem;
    font-weight:600;
    padding:.35rem 1rem;
    box-shadow:0 10px 30px rgba(0,0,0,.55);
  }
  .btn-ghost:hover{
    background:rgba(30,64,175,.9);
    border-color:rgba(129,140,248,.9);
    color:white;
  }

  /* ===== Título sección ===== */

  .section-header{
    margin-bottom:22px;
  }

  .section-kicker{
    margin-bottom:4px;
  }

  .section-kicker .sk-text{
    font-size:1.05rem;
    font-weight:900;
    letter-spacing:.18em;
    text-transform:uppercase;
    background:linear-gradient(90deg,#38bdf8,#22c55e);
    -webkit-background-clip:text;
    -webkit-text-fill-color:transparent;
    filter:drop-shadow(0 0 6px rgba(30,58,138,.55));
    padding-bottom:3px;
    border-bottom:2px solid rgba(34,197,94,.45);
    display:inline-block;
  }

  .section-title{
    font-size:1.6rem;
    font-weight:800;
    margin-top:2px;
  }

  .section-sub{
    font-size:.9rem;
    color:#cbd5f5;
    max-width:540px;
  }

  /* ===== Tarjetas ===== */

  .modules-grid{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:18px;
  }
  @media (max-width:991px){
    .modules-grid{ grid-template-columns:repeat(2,minmax(0,1fr)); }
  }
  @media (max-width:575px){
    .modules-grid{ grid-template-columns:1fr; }
  }

  .card-s3{
    position:relative;
    border-radius:22px;
    padding:18px 18px 16px;
    background:
      radial-gradient(circle at top left, rgba(56,189,248,.22), transparent 60%),
      radial-gradient(circle at bottom right, rgba(34,197,94,.18), transparent 70%),
      var(--card-bg);
    border:1px solid rgba(15,23,42,.9);
    box-shadow:
      0 22px 40px rgba(0,0,0,.85),
      0 0 0 1px rgba(148,163,184,.35);
    backdrop-filter:blur(12px);
    transition:
      transform .18s ease-out,
      box-shadow .18s ease-out,
      border-color .18s ease-out,
      background .18s ease-out;
    overflow:hidden;

    height:100%;
    display:flex;
    flex-direction:column;
    justify-content:space-between;
  }

  .card-s3::before{
    content:"";
    position:absolute;
    inset:0;
    background:linear-gradient(135deg, rgba(56,189,248,.3), rgba(34,197,94,.1), transparent);
    opacity:0;
    transition:opacity .25s ease-out;
    pointer-events:none;
  }

  .card-s3:hover{
    transform:translateY(-4px) scale(1.01);
    box-shadow:
      0 28px 60px rgba(0,0,0,.9),
      0 0 0 1px rgba(129,140,248,.65);
    border-color:rgba(129,140,248,.9);
  }
  .card-s3:hover::before{ opacity:1; }

  .card-link{
    text-decoration:none;
    color:inherit;
    display:block;
    height:100%;
  }

  .card-topline{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:10px;
  }

  .card-icon{
    font-size:1.4rem;
    line-height:1;
    filter:drop-shadow(0 0 6px rgba(0,0,0,.7));
  }

  .card-title{
    font-weight:750;
    font-size:.95rem;
    margin-bottom:2px;
  }

  .card-sub{
    font-size:.76rem;
    color:var(--text-muted);
  }

  .card-pill{
    font-size:.68rem;
    text-transform:uppercase;
    letter-spacing:.16em;
    padding:.15rem .55rem;
    border-radius:999px;
    border:1px solid rgba(148,163,184,.6);
    color:#e5e7eb;
    background:rgba(15,23,42,.4);
    white-space:nowrap;
  }

  .card-footer{
    margin-top:14px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:8px;
  }

  .kpi-label{
    font-size:.7rem;
    text-transform:uppercase;
    letter-spacing:.16em;
    color:var(--text-muted);
    font-weight:700;
  }

  .kpi-num{
    font-size:1.6rem;
    font-weight:850;
    color:var(--accent);
  }

  .kpi-progress{
    position:relative;
    flex:1;
    height:7px;
    border-radius:999px;
    background:rgba(15,23,42,.9);
    overflow:hidden;
    box-shadow:inset 0 0 0 1px rgba(31,41,55,.8);
  }
  .kpi-progress span{
    display:block;
    height:100%;
    border-radius:999px;
    background:linear-gradient(90deg,#22c55e,#a3e635);
    box-shadow:0 0 14px rgba(34,197,94,.9);
    transition:width .35s ease-out;
  }

  .kpi-tag{
    font-size:.7rem;
    color:var(--text-muted);
    text-align:right;
  }
</style>
</head>
<body>

<header class="brand-hero">
  <div class="hero-inner">
    <div class="brand-left">
      <img src="<?= e($ESCUDO) ?>" class="brand-logo" alt="Escudo B Com 602">
      <div>
        <div class="brand-title">Batallón de Comunicaciones 602</div>
        <div class="brand-sub">“Hogar de las Comunicaciones Fijas del Ejército”</div>
      </div>
    </div>
    <div class="header-back">
      <a href="areas_s3.php" class="btn btn-ghost">⬅ Volver a S-3</a>
      <a href="areas.php" class="btn btn-ghost">Volver a Áreas</a>
    </div>
  </div>
</header>

<div class="page-wrap">
  <div class="container-main">

    <div class="section-header">
      <div class="section-kicker">
        <span class="sk-text">S-3 · OPERACIONES</span>
      </div>
      <div class="section-title">Educación operacional de la tropa</div>
      <p class="section-sub mb-0">
        Ciclos de instrucción de tropa (I, II, III y N.I.A.). Seleccione un ciclo
        para ver el detalle de actividades y adjuntar evidencias.
      </p>
    </div>

    <div class="modules-grid">

      <!-- Ciclo I -->
      <div>
        <a href="s3_educacion_tropa_ciclo1.php" class="card-link">
          <article class="card-s3">
            <div class="card-topline">
              <div>
                <div class="card-title">Ciclo I</div>
                <div class="card-sub">Instrucción básica de la tropa.</div>
              </div>
              <div class="d-flex flex-column align-items-end gap-1">
                <div class="card-icon">I</div>
                <span class="card-pill">Básico</span>
              </div>
            </div>
            <div class="card-footer">
              <div>
                <div class="kpi-label">Cumplimiento</div>
                <div class="kpi-num"><?= e($porc1) ?>%</div>
              </div>
              <div class="kpi-progress">
                <span style="width: <?= e(max(0,min(100,$porc1))) ?>%;"></span>
              </div>
              <div class="kpi-tag">Ver ciclo</div>
            </div>
          </article>
        </a>
      </div>

      <!-- Ciclo II -->
      <div>
        <a href="s3_educacion_tropa_ciclo2.php" class="card-link">
          <article class="card-s3">
            <div class="card-topline">
              <div>
                <div class="card-title">Ciclo II</div>
                <div class="card-sub">Instrucción técnica y táctica intermedia.</div>
              </div>
              <div class="d-flex flex-column align-items-end gap-1">
                <div class="card-icon">II</div>
                <span class="card-pill">Intermedio</span>
              </div>
            </div>
            <div class="card-footer">
              <div>
                <div class="kpi-label">Cumplimiento</div>
                <div class="kpi-num"><?= e($porc2) ?>%</div>
              </div>
              <div class="kpi-progress">
                <span style="width: <?= e(max(0,min(100,$porc2))) ?>%;"></span>
              </div>
              <div class="kpi-tag">Ver ciclo</div>
            </div>
          </article>
        </a>
      </div>

      <!-- Ciclo III -->
      <div>
        <a href="s3_educacion_tropa_ciclo3.php" class="card-link">
          <article class="card-s3">
            <div class="card-topline">
              <div>
                <div class="card-title">Ciclo III</div>
                <div class="card-sub">Instrucción avanzada y ejercicios de campaña.</div>
              </div>
              <div class="d-flex flex-column align-items-end gap-1">
                <div class="card-icon">III</div>
                <span class="card-pill">Avanzado</span>
              </div>
            </div>
            <div class="card-footer">
              <div>
                <div class="kpi-label">Cumplimiento</div>
                <div class="kpi-num"><?= e($porc3) ?>%</div>
              </div>
              <div class="kpi-progress">
                <span style="width: <?= e(max(0,min(100,$porc3))) ?>%;"></span>
              </div>
              <div class="kpi-tag">Ver ciclo</div>
            </div>
          </article>
        </a>
      </div>

      <!-- N.I.A. -->
      <div>
        <a href="s3_educacion_tropa_nia.php" class="card-link">
          <article class="card-s3">
            <div class="card-topline">
              <div>
                <div class="card-title">N.I.A.</div>
                <div class="card-sub">No Instrucción Asignada / actividades especiales.</div>
              </div>
              <div class="d-flex flex-column align-items-end gap-1">
                <div class="card-icon">★</div>
                <span class="card-pill">Especial</span>
              </div>
            </div>
            <div class="card-footer">
              <div>
                <div class="kpi-label">Cumplimiento</div>
                <div class="kpi-num"><?= e($porcNIA) ?>%</div>
              </div>
              <div class="kpi-progress">
                <span style="width: <?= e(max(0,min(100,$porcNIA))) ?>%;"></span>
              </div>
              <div class="kpi-tag">Ver ciclo</div>
            </div>
          </article>
        </a>
      </div>

    </div>

  </div>
</div>

</body>
</html>
