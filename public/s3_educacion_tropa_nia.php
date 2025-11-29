<?php
// public/s3_educacion_tropa_nia.php — N.I.A. · Educación de la tropa
declare(strict_types=1);

$OFFLINE_MODE = false;

require_once __DIR__ . '/../auth/bootstrap.php';
if (!$OFFLINE_MODE) require_login();
require_once __DIR__ . '/../config/db.php';

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$PUBLIC_URL = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$APP_URL    = rtrim(dirname($PUBLIC_URL), '/');
$ASSETS     = ($APP_URL === '' ? '' : $APP_URL) . '/assets';
$IMG_BG     = $ASSETS . '/img/fondo.png';
$ESCUDO     = $ASSETS . '/img/escudo_bcom602.png';

/* Demo N.I.A. */
$ciclo = [
    ['sem'=>'5','fecha'=>'05 Abr','tema'=>'Formación en valores militares','resp'=>'Jefe de Unidad','part'=>'Tropa','lugar'=>'Aula 3','cumplio'=>'Sí','doc'=>''],
];

$total  = count($ciclo);
$ok     = 0;
foreach ($ciclo as $r) {
    if (strtolower($r['cumplio']) === 'sí' || strtolower($r['cumplio']) === 'si') $ok++;
}
$pct = $total > 0 ? round($ok * 100 / $total, 1) : 0.0;
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>S-3 · Educación tropa · N.I.A. · B Com 602</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/theme-602.css">
<link rel="icon" type="image/png" href="../assets/img/bcom602.png">
<style>
  body{
    background:url("<?= e($IMG_BG) ?>") center/cover fixed;
    background-color:#020617;
    color:#e5e7eb;
    font-family:system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,sans-serif;
  }
  .page-wrap{ padding:20px 16px 32px; }
  .container-main{ max-width:1200px; margin:0 auto; }

  header.brand-hero{ padding:14px 0 6px; }
  .hero-inner{
    max-width:1200px; margin:0 auto;
    display:flex; justify-content:space-between; align-items:center; gap:16px;
  }
  .brand-left{ display:flex; align-items:center; gap:12px; }
  .brand-logo{ height:52px; filter:drop-shadow(0 0 8px rgba(0,0,0,.8)); }
  .brand-title{ font-weight:800; font-size:1.05rem; letter-spacing:.03em; }
  .brand-sub{ font-size:.8rem; color:#cbd5f5; }

  .btn-ghost{
    border-radius:999px; border:1px solid rgba(148,163,184,.6);
    background:rgba(15,23,42,.9); color:#e5e7eb;
    font-size:.8rem; font-weight:600;
    padding:.35rem 1rem;
  }
  .btn-ghost:hover{
    background:rgba(30,64,175,.9); border-color:rgba(129,140,248,.9); color:#fff;
  }
  .header-back{ display:flex; flex-wrap:wrap; gap:8px; }

  .section-header{ margin-bottom:18px; }
  .section-kicker{
    font-size:.8rem; text-transform:uppercase; letter-spacing:.16em;
    color:#38bdf8; font-weight:700;
  }
  .section-title{ font-size:1.5rem; font-weight:800; }
  .section-sub{ font-size:.9rem; color:#cbd5f5; max-width:620px; }

  .panel{
    background:rgba(15,23,42,.94);
    border-radius:18px;
    border:1px solid rgba(148,163,184,.45);
    padding:18px 18px 20px;
    box-shadow:0 18px 40px rgba(0,0,0,.8);
    backdrop-filter:blur(8px);
    margin-bottom:18px;
  }
  .panel-title{ font-size:1rem; font-weight:800; margin-bottom:4px; }
  .panel-sub{ font-size:.86rem; color:#cbd5f5; margin-bottom:10px; }

  .kpi-row{ display:flex; align-items:center; gap:18px; flex-wrap:wrap; }
  .kpi-main{ font-size:1.6rem; font-weight:850; color:#22c55e; }
  .kpi-label{ font-size:.8rem; text-transform:uppercase; letter-spacing:.12em; color:#9ca3af; }
  .kpi-desc{ font-size:.85rem; color:#cbd5f5; }
  .progress{ height:7px; border-radius:999px; background:rgba(15,23,42,.9); }
  .progress-bar{ border-radius:999px; }

  .table-sm th,.table-sm td{ padding:.35rem .5rem; font-size:.8rem; }
  .badge-si{ background:#16a34a; }
  .badge-no{ background:#dc2626; }
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
      <a href="s3_educacion_tropa.php" class="btn-ghost">⬅ Volver a Educación tropa</a>
      <a href="areas_s3.php" class="btn-ghost">Volver a S-3</a>
    </div>
  </div>
</header>

<div class="page-wrap">
  <div class="container-main">

    <div class="section-header">
      <div class="section-kicker">N.I.A.</div>
      <div class="section-title">No Instrucción Asignada · Actividades especiales</div>
      <p class="section-sub mb-0">
        Actividades de formación y educación que no integran el plan regular de ciclos,
        pero impactan en la instrucción del personal de tropa.
      </p>
    </div>

    <div class="panel">
      <div class="panel-title">Resumen de avance</div>
      <div class="panel-sub">Datos de muestra (modo demo).</div>

      <div class="kpi-row">
        <div>
          <div class="kpi-label">Cumplimiento</div>
          <div class="kpi-main"><?= e($pct) ?>%</div>
        </div>
        <div style="min-width:200px; flex:1;">
          <div class="kpi-desc">
            <?= e($ok) ?> actividades cumplidas sobre <?= e($total) ?> registradas.
          </div>
          <div class="progress mt-2">
            <div class="progress-bar bg-success" style="width:<?= e($pct) ?>%"></div>
          </div>
        </div>
      </div>
    </div>

    <div class="panel">
      <div class="panel-title">Detalle de actividades · N.I.A.</div>
      <div class="panel-sub">Listado de actividades especiales.</div>

      <div class="table-responsive">
        <table class="table table-sm table-dark align-middle">
          <thead>
          <tr>
            <th>Sem</th>
            <th>Fecha</th>
            <th>Tema</th>
            <th>Responsable</th>
            <th>Participantes</th>
            <th>Lugar</th>
            <th>Cumplió</th>
            <th>Documento</th>
          </tr>
          </thead>
          <tbody>
          <?php if (empty($ciclo)): ?>
            <tr><td colspan="8" class="text-center text-muted">Sin registros (modo demo)</td></tr>
          <?php else: ?>
            <?php foreach ($ciclo as $d): ?>
            <tr>
              <td><?= e($d['sem']) ?></td>
              <td><?= e($d['fecha']) ?></td>
              <td><?= e($d['tema']) ?></td>
              <td><?= e($d['resp']) ?></td>
              <td><?= e($d['part']) ?></td>
              <td><?= e($d['lugar']) ?></td>
              <td>
                <?php
                  $si = (strcasecmp($d['cumplio'],'sí')===0 || strcasecmp($d['cumplio'],'si')===0);
                ?>
                <?php if ($si): ?>
                  <span class="badge rounded-pill badge-si">Sí</span>
                <?php else: ?>
                  <span class="badge rounded-pill badge-no">No</span>
                <?php endif; ?>
              </td>
              <td><?= empty($d['doc']) ? "<span class='text-muted'>Pendiente</span>" : e($d['doc']) ?></td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

</body>
</html>
