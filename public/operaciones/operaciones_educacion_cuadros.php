<?php
// public/s3_educacion_cuadros.php Ã¢â‚¬â€ Panel principal S-3 EducaciÃƒÂ³n
declare(strict_types=1);

$OFFLINE_MODE = false;

require_once __DIR__ . '/../../auth/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../../includes/operaciones_helper.php';
require_once __DIR__ . '/operaciones_educacion_tables_helper.php';

if (!$OFFLINE_MODE) {
    operaciones_require_login();
}

operaciones_educacion_ensure_tables($pdo);

$esAdmin = operaciones_es_admin($pdo);
$modoResumido = !$esAdmin;

$ASSET_WEB = operaciones_assets_url();
$IMG_BG    = operaciones_assets_url('img/fondo.png');
$ESCUDO    = operaciones_assets_url('../../assets/img/ecmilm.png');

function e($v){ return operaciones_e($v); }

/* ===== KPIs ===== */
function kpi(PDO $p, string $tbl): float {
    try {
        $sql = "SELECT SUM(cumplio = 'si') AS ok, COUNT(*) AS tot FROM {$tbl}";
        $row = $p->query($sql)->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return 0.0;
        }
        if ((int)$row['tot'] === 0) return 0.0;
        return round(((int)$row['ok'] * 100) / (int)$row['tot'], 1);
    } catch (Throwable $e) {
        // Si la tabla no existe o hay cualquier error, no rompemos la pÃƒÂ¡gina.
        return 0.0;
    }
}

$pctClases       = kpi($pdo, 's3_clases');
$pctGabinete     = kpi($pdo, 's3_trabajos_gabinete');
$pctAloc         = kpi($pdo, 's3_alocuciones');
$pctCursos       = kpi($pdo, 's3_cursos_regulares');
$pctCursosCompl  = kpi($pdo, 's3_cursos_complementarios'); // NUEVO KPI

?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Educaci&oacute;n operacional de cuadros</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/theme-602.css">
<link rel="icon" href="../../assets/img/ecmilm.png">
<style>
  :root{
    --bg-dark: #020617;
    --card-bg: rgba(15,23,42,.94);
    --card-border: rgba(148,163,184,.45);
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

  /* ===== Header / Brand ===== */

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

  /* ===== Titulo seccion ===== */

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
    .modules-grid{
      grid-template-columns:repeat(2,minmax(0,1fr));
    }
  }
  @media (max-width:575px){
    .modules-grid{
      grid-template-columns:1fr;
    }
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

    /* Para que todas las tarjetas tengan el mismo alto */
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
  .card-s3:hover::before{
    opacity:1;
  }

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
      <img src="<?= e($ESCUDO) ?>" class="brand-logo" alt="Escudo Ec Mil M">
      <div>
        <div class="brand-title">Escuela Militar de Monta&ntilde;a</div>
        <div class="brand-sub">&ldquo;La monta&ntilde;a nos une&rdquo;</div>
      </div>
    </div>
    <div class="header-back">
      <a href="areas.php" class="btn btn-ghost">
        &larr; Volver a &Aacute;reas
      </a>
    </div>
  </div>
</header>

<div class="page-wrap">
  <div class="container-main">

    <div class="section-header">
      <div class="section-kicker">
        <span class="sk-text">S-3 &middot; OPERACIONES</span>
      </div>
      <div class="section-title">Educaci&oacute;n operacional de cuadros</div>
      <p class="section-sub mb-0">
        Visi&oacute;n r&aacute;pida del avance en clases, trabajos de gabinete, alocuciones,
        cursos regulares y cursos complementarios. Seleccione un m&oacute;dulo para
        editar el detalle y adjuntar evidencias.
      </p>
    </div>

    <div class="modules-grid">

      <!-- Clases -->
      <div>
        <a href="operaciones_educacion_clases.php" class="card-link">
          <article class="card-s3">
            <div class="card-topline">
              <div>
                <div class="card-title">Clases (Programa anual)</div>
                <div class="card-sub">Plan anual de educaci&oacute;n de la unidad.</div>
              </div>
              <div class="d-flex flex-column align-items-end gap-1">
                <div class="card-icon">&#128218;</div>
                <span class="card-pill">Programa</span>
              </div>
            </div>
            <div class="card-footer">
              <div>
                <div class="kpi-label">Cumplimiento</div>
                <div class="kpi-num"><?= e($pctClases) ?>%</div>
              </div>
              <div class="kpi-progress">
                <span style="width: <?= e(max(0,min(100,$pctClases))) ?>%;"></span>
              </div>
              <div class="kpi-tag">Ver detalle</div>
            </div>
          </article>
        </a>
      </div>

      <!-- Trabajos de gabinete -->
      <div>
        <a href="operaciones_educacion_trabajos.php" class="card-link">
          <article class="card-s3">
            <div class="card-topline">
              <div>
                <div class="card-title">Trabajos de gabinete</div>
                <div class="card-sub">Investigaci&oacute;n y trabajos asignados a cuadros.</div>
              </div>
              <div class="d-flex flex-column align-items-end gap-1">
                <div class="card-icon">&#129504;</div>
                <span class="card-pill">Estudio</span>
              </div>
            </div>
            <div class="card-footer">
              <div>
                <div class="kpi-label">Cumplimiento</div>
                <div class="kpi-num"><?= e($pctGabinete) ?>%</div>
              </div>
              <div class="kpi-progress">
                <span style="width: <?= e(max(0,min(100,$pctGabinete))) ?>%;"></span>
              </div>
              <div class="kpi-tag">Ver detalle</div>
            </div>
          </article>
        </a>
      </div>

      <!-- Alocuciones -->
      <div>
        <a href="operaciones_educacion_alocuciones.php" class="card-link">
          <article class="card-s3">
            <div class="card-topline">
              <div>
                <div class="card-title">Alocuciones</div>
                <div class="card-sub">Acontecimientos, efem&eacute;rides y responsables.</div>
              </div>
              <div class="d-flex flex-column align-items-end gap-1">
                <div class="card-icon">&#127897;</div>
                <span class="card-pill">Formaciones</span>
              </div>
            </div>
            <div class="card-footer">
              <div>
                <div class="kpi-label">Cumplimiento</div>
                <div class="kpi-num"><?= e($pctAloc) ?>%</div>
              </div>
              <div class="kpi-progress">
                <span style="width: <?= e(max(0,min(100,$pctAloc))) ?>%;"></span>
              </div>
              <div class="kpi-tag">Ver detalle</div>
            </div>
          </article>
        </a>
      </div>

      <!-- Cursos regulares -->
      <div>
        <a href="operaciones_educacion_cursos.php" class="card-link">
          <article class="card-s3">
            <div class="card-topline">
              <div>
                <div class="card-title">Cursos regulares</div>
                <div class="card-sub">CBPM, CAEMD y otros cursos de perfeccionamiento.</div>
              </div>
              <div class="d-flex flex-column align-items-end gap-1">
                <div class="card-icon">&#127891;</div>
              </div>
            </div>
            <div class="card-footer">
              <div>
                <div class="kpi-label">Cumplimiento</div>
                <div class="kpi-num"><?= e($pctCursos) ?>%</div>
              </div>
              <div class="kpi-progress">
                <span style="width: <?= e(max(0,min(100,$pctCursos))) ?>%;"></span>
              </div>
              <div class="kpi-tag">Ver detalle</div>
            </div>
          </article>
        </a>
      </div>

      <!-- Cursos complementarios -->
      <div>
        <a href="operaciones_educacion_cursos_complementarios.php" class="card-link">
          <article class="card-s3">
            <div class="card-topline">
              <div>
                <div class="card-title">Cursos complementarios</div>
                <div class="card-sub">Seminarios, cursos externos y capacitaci&oacute;n adicional.</div>
              </div>
              <div class="d-flex flex-column align-items-end gap-1">
                <div class="card-icon">&#127891;</div>
              </div>
            </div>
            <div class="card-footer">
              <div>
                <div class="kpi-label">Cumplimiento</div>
                <div class="kpi-num"><?= e($pctCursosCompl) ?>%</div>
              </div>
              <div class="kpi-progress">
                <span style="width: <?= e(max(0,min(100,$pctCursosCompl))) ?>%;"></span>
              </div>
              <div class="kpi-tag">Ver detalle</div>
            </div>
          </article>
        </a>
      </div>

    </div>

  </div>
</div>

</body>
</html>
