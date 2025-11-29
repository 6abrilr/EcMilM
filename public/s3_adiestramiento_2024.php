<?php
// public/s3_adiestramiento_2024.php — Adiestramiento 2024
declare(strict_types=1);

$OFFLINE_MODE = false;
require_once __DIR__ . '/../auth/bootstrap.php';
if (!$OFFLINE_MODE) require_login();
require_once __DIR__ . '/../config/db.php';

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$PUBLIC_URL = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$APP_URL    = rtrim(dirname($PUBLIC_URL), '/');
$ASSETS     = ($APP_URL===''?'':$APP_URL).'/assets';
$IMG_BG     = $ASSETS.'/img/fondo.png';
$ESCUDO     = $ASSETS.'/img/escudo_bcom602.png';

$anio = 2024;
$total = 58;
$aprobo = 37;
$porc = round($aprobo*100/$total,1);

$pa = [
  ['grado'=>'SG 1ra','nombre'=>'LÓPEZ Juan','esp'=>'Com','resultado'=>'NO APROBÓ','obs'=>'Lesión','doc'=>''],
  ['grado'=>'CI Mec','nombre'=>'GÓMEZ Daniel','esp'=>'Sist','resultado'=>'APROBÓ','obs'=>'','doc'=>''],
];
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Adiestramiento <?= $anio ?> · B Com 602</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/theme-602.css">
<link rel="icon" href="../assets/img/bcom602.png">
<style>
<?php include __DIR__.'/s3_adiestramiento_style.php'; ?>
</style>
</head>
<body>

<?php include __DIR__."/s3_adiestramiento_header.php"; ?>

<div class="page-wrap">
<div class="container-main">

<h4 class="fw-bold mb-3">Adiestramiento físico-militar · Año <?= $anio ?></h4>

<div class="panel">
  <div class="panel-title">Resumen general <?= $anio ?></div>
  <div class="panel-sub">Evaluación PAFB del personal.</div>

  <strong><?= $aprobo ?>/<?= $total ?></strong> aprobados — Cumplimiento:
  <strong><?= $porc ?>%</strong>

  <div class="progress mt-2" style="height:7px;">
    <div class="progress-bar bg-success" style="width:<?= $porc ?>%"></div>
  </div>
</div>

<div class="panel">
  <div class="panel-title">Resultados del personal</div>

  <div class="table-responsive">
    <table class="table table-dark table-sm">
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
            <?php if($p['resultado']==='APROBÓ'): ?>
              <span class="badge badge-ok">APROBÓ</span>
            <?php else: ?>
              <span class="badge badge-no">NO APROBÓ</span>
            <?php endif; ?>
          </td>
          <td><?= e($p['obs']) ?></td>
          <td><?= empty($p['doc']) ? "<span class='text-muted'>Pendiente</span>" : e($p['doc']) ?></td>
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
