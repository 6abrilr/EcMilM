<?php
// public/s3_adiestramiento_2023.php — Adiestramiento 2023 · S-3
declare(strict_types=1);

$OFFLINE_MODE = false;

require_once __DIR__ . '/../auth/bootstrap.php';
if (!$OFFLINE_MODE) require_login();
require_once __DIR__ . '/../config/db.php';

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* Assets */
$PUBLIC_URL = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$APP_URL    = rtrim(dirname($PUBLIC_URL), '/');
$ASSETS     = ($APP_URL === '' ? '' : $APP_URL) . '/assets';
$IMG_BG     = $ASSETS.'/img/fondo.png';
$ESCUDO     = $ASSETS.'/img/escudo_bcom602.png';

/* ====== DEMO 2023 ====== */
$anio = 2023;

$total = 55;
$aprobo = 29;
$pend = $total - $aprobo;
$porc = round($aprobo * 100 / $total, 1);

$pa = [
    ['grado'=>'Soldado','nombre'=>'FERNÁNDEZ Pablo','esp'=>'Inf','resultado'=>'NO APROBÓ','obs'=>'Inasistencia','doc'=>''],
    ['grado'=>'Cbo 1ro','nombre'=>'MARTÍNEZ Luis','esp'=>'Trans','resultado'=>'APROBÓ','obs'=>'','doc'=>''],
];
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Adiestramiento <?= $anio ?> · S-3 · B Com 602</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/theme-602.css">
<link rel="icon" href="../assets/img/bcom602.png">
<style>
body{
  background:url("<?= e($IMG_BG) ?>") center/cover fixed;
  background-color:#020617; color:#e5e7eb;
  font-family:system-ui;
}
.page-wrap{ padding:20px; }
.container-main{ max-width:1200px; margin:auto; }

.header-back{ display:flex; gap:8px; }
.panel{
  background:rgba(15,23,42,.9);
  border:1px solid rgba(148,163,184,.45);
  border-radius:18px;
  padding:18px;
  margin-bottom:18px;
  box-shadow:0 18px 40px rgba(0,0,0,.85);
  backdrop-filter:blur(8px);
}
.panel-title{ font-size:1rem; font-weight:800; }
.panel-sub{ font-size:.86rem; color:#cbd5f5; margin-bottom:12px; }

.badge-ok{ background:#16a34a; }
.badge-no{ background:#dc2626; }

.table-sm th,.table-sm td{ padding:.35rem .5rem; font-size:.8rem; }
</style>
</head>
<body>

<?php include __DIR__."/s3_adiestramiento_header.php"; ?>

<div class="page-wrap">
<div class="container-main">

  <h4 class="fw-bold mb-3">Adiestramiento físico-militar · Año <?= $anio ?></h4>

  <div class="panel">
    <div class="panel-title">Resumen general <?= $anio ?></div>
    <div class="panel-sub">Evaluación PAFB del personal de la unidad.</div>

    <div>
      <strong><?= $aprobo ?>/<?= $total ?></strong> aprobados —
      Cumplimiento: <strong><?= $porc ?>%</strong>
    </div>

    <div class="progress mt-2" style="height:7px;">
      <div class="progress-bar bg-success" style="width:<?= $porc ?>%"></div>
    </div>
  </div>

  <div class="panel">
    <div class="panel-title">Resultados del personal · <?= $anio ?></div>

    <div class="table-responsive">
      <table class="table table-dark table-sm align-middle">
        <thead>
          <tr>
            <th>Grado</th><th>Nombre</th><th>Especialidad</th>
            <th>Resultado</th><th>Observaciones</th><th>Documento</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach($pa as $p): ?>
          <tr>
            <td><?= e($p['grado']) ?></td>
            <td><?= e($p['nombre']) ?></td>
            <td><?= e($p['esp']) ?></td>
            <td>
              <?php if ($p['resultado']==='APROBÓ'): ?>
                <span class="badge badge-ok">APROBÓ</span>
              <?php else: ?>
                <span class="badge badge-no">NO APROBÓ</span>
              <?php endif; ?>
            </td>
            <td><?= e($p['obs']) ?></td>
            <td><?= empty($p['doc']) ? '<span class="text-muted">Pendiente</span>' : e($p['doc']) ?></td>
          </tr>
        <?php endforeach ?>
        </tbody>
      </table>
    </div>

  </div>

</div>
</div>

</body>
</html>
