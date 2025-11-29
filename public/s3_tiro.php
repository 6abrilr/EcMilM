<?php
// public/s3_tiro.php — Panel principal de Tiro · S-3
declare(strict_types=1);

$OFFLINE_MODE = false;

require_once __DIR__ . '/../auth/bootstrap.php';
if (!$OFFLINE_MODE) require_login();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/s3_tiro_tables_helper.php';

s3_tiro_ensure_tables($pdo);

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* Assets */
$PUBLIC_URL = rtrim(str_replace("\\","/", dirname($_SERVER["SCRIPT_NAME"] ?? "")), "/");
$APP_URL    = rtrim(dirname($PUBLIC_URL), "/");
$ASSETS     = ($APP_URL==="" ? "" : $APP_URL)."/assets";
$IMG_BG     = $ASSETS."/img/fondo.png";
$ESCUDO     = $ASSETS."/img/escudo_bcom602.png";

/* ========== KPIs ========= */
function kpi_resultado(PDO $pdo, string $table): float {
    $sql = "SELECT 
                COUNT(*) AS total,
                SUM(resultado = 'APROBO') AS aprob
            FROM {$table}";
    $row = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC) ?: ['total'=>0,'aprob'=>0];
    $total = (int)$row['total'];
    $aprob = (int)$row['aprob'];
    if ($total === 0) return 0.0;
    return round($aprob * 100.0 / $total, 1);
}

$pctAmi = kpi_resultado($pdo, 's3_tiro_ami');
$pctB9  = kpi_resultado($pdo, 's3_tiro_b9');

?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>S-3 · Tiro · Batallón de Comunicaciones 602</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="../assets/css/theme-602.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body{
    background:url("<?= e($IMG_BG) ?>") center/cover fixed;
    background-color:#020617;
    color:#e5e7eb;
    font-family:system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,sans-serif;
    margin:0;
}
.page-wrap{ padding:24px 16px 32px; }
.container-main{ max-width:1200px; margin:0 auto; }

.section-kicker .sk-text{
    font-size:1.05rem;
    font-weight:900;
    letter-spacing:.18em;
    text-transform:uppercase;
    background:linear-gradient(90deg,#38bdf8,#22c55e);
    -webkit-background-clip:text;
    -webkit-text-fill-color:transparent;
    border-bottom:2px solid rgba(34,197,94,.45);
    display:inline-block;
    padding-bottom:3px;
}

.card-s3{
    background:rgba(15,23,42,.92);
    border:1px solid rgba(148,163,184,.45);
    border-radius:18px;
    padding:18px;
    transition:.2s;
    box-shadow:0 0 22px rgba(0,0,0,.55);
}
.card-s3:hover{
    transform:translateY(-4px);
    box-shadow:0 0 32px rgba(0,0,0,.8);
}
.card-title{ font-weight:700; }

.kpi-num{
    font-size:1.6rem;
    font-weight:800;
    color:#22c55e;
}
</style>
</head>
<body>

<header class="brand-hero">
  <div class="container-main d-flex justify-content-between align-items-center py-2">
    <div class="d-flex gap-3 align-items-center">
      <img src="<?= e($ESCUDO) ?>" style="height:56px;">
      <div>
        <div class="brand-title fw-bold">Batallón de Comunicaciones 602</div>
        <div class="brand-sub text-muted">“Hogar de las Comunicaciones Fijas del Ejército”</div>
      </div>
    </div>
    <a href="areas_s3.php" class="btn btn-secondary btn-sm fw-bold">Volver a S-3</a>
  </div>
</header>

<div class="page-wrap">
<div class="container-main">

  <div class="section-kicker mb-2">
    <span class="sk-text">S-3 · OPERACIONES</span>
  </div>
  <h3 class="fw-bold mb-3">Tiro</h3>
  <p class="text-muted">Seleccione un módulo para cargar o consultar información.</p>

  <div class="row g-4">

    <!-- AMI -->
    <div class="col-md-6 col-lg-3">
      <a href="s3_tiro_ami.php" class="text-decoration-none text-light">
        <div class="card-s3 h-100">
          <h6 class="card-title">AMI asignada</h6>
          <p class="small text-muted mb-2">Evaluación individual AMI</p>
          <div class="kpi-num"><?= e($pctAmi) ?>%</div>
        </div>
      </a>
    </div>

    <!-- B9 -->
    <div class="col-md-6 col-lg-3">
      <a href="s3_tiro_b9.php" class="text-decoration-none text-light">
        <div class="card-s3 h-100">
          <h6 class="card-title">Condición B9</h6>
          <p class="small text-muted mb-2">Apto para Servicios de Vigilancia</p>
          <div class="kpi-num"><?= e($pctB9) ?>%</div>
        </div>
      </a>
    </div>

    <!-- Consumo munición -->
    <div class="col-md-6 col-lg-3">
      <a href="s3_tiro_municion.php" class="text-decoration-none text-light">
        <div class="card-s3 h-100">
          <h6 class="card-title">Consumo de munición</h6>
          <p class="small text-muted mb-2">Registro por calibre y uso</p>
          <div class="kpi-num">📦</div>
        </div>
      </a>
    </div>

    <!-- Ejercicios -->
    <div class="col-md-6 col-lg-3">
      <a href="s3_tiro_ejercicios.php" class="text-decoration-none text-light">
        <div class="card-s3 h-100">
          <h6 class="card-title">Ejercicios de tiro</h6>
          <p class="small text-muted mb-2">Resultados por ejercicio</p>
          <div class="kpi-num">🎯</div>
        </div>
      </a>
    </div>

  </div>

</div>
</div>

</body>
</html>
