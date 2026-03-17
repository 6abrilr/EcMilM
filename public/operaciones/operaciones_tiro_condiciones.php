<?php
// public/operaciones/operaciones_tiro_condiciones.php — Resumen de condiciones de tiro
declare(strict_types=1);

$OFFLINE_MODE = false;

require_once __DIR__ . '/../../auth/bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/operaciones_helper.php';
require_once __DIR__ . '/operaciones_tiro_tables_helper.php';

if (!$OFFLINE_MODE) {
    operaciones_require_login();
}

s3_tiro_ensure_tables($pdo);

$esAdmin = operaciones_es_admin($pdo);
$modoResumido = !$esAdmin;

$ASSET_WEB = operaciones_assets_url();
$IMG_BG    = operaciones_assets_url('img/fondo.png');
$ESCUDO    = operaciones_assets_url('img/escudo_bcom602.png');

// Compatibility helper used in templates
function e($v){ return operaciones_e($v); }

// Cargar datos:
$sql = "
  SELECT
    'B9' AS tipo,
    grado,
    nombre,
    ejercicio,
    resultado,
    observaciones,
    fecha,
    documento,
    actualizado_por,
    creado_en
  FROM s3_tiro_b9
  UNION ALL
  SELECT
    'AMI' AS tipo,
    grado,
    nombre,
    ejercicio,
    resultado,
    observaciones,
    fecha,
    documento,
    actualizado_por,
    creado_en
  FROM s3_tiro_ami
  ORDER BY fecha DESC, tipo, grado, nombre
";

$condiciones = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Resumen de condiciones de tiro · S-3</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="<?= e($ASSET_WEB) ?>/css/theme-602.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{
    background:url("<?= e($IMG_BG) ?>") center/cover fixed;
    background-color:#020617;
    color:#e5e7eb;
}
.page-wrap{ padding:22px 16px 32px; }
.container-main{ max-width:1200px; margin:0 auto; }
.panel{
  background:rgba(15,23,42,.92);
  border-radius:18px;
  border:1px solid rgba(148,163,184,.45);
  padding:20px;
  margin-bottom:18px;
  box-shadow:0 18px 40px rgba(0,0,0,.85);
}
.table-sm th,.table-sm td{ font-size:.82rem; }
</style>
</head>
<body>

<header class="brand-hero">
  <div class="container-main d-flex justify-content-between align-items-center py-2">
    <div class="d-flex gap-3 align-items-center">
      <img src="<?= e($ESCUDO) ?>" style="height:55px;">
      <div>
        <div class="brand-title">Batallón de Comunicaciones 602</div>
        <div class="brand-sub text-muted">S-3 · Resumen condiciones de tiro</div>
      </div>
    </div>
    <a href="operaciones_tiro.php" class="btn btn-secondary btn-sm fw-bold">Volver</a>
  </div>
</header>

<div class="page-wrap">
<div class="container-main">

<?php if ($modoResumido): ?>
  <div class="alert alert-warning">
    <strong>Vista resumida:</strong> la información puede estar limitada para usuarios no administradores.
  </div>
<?php endif; ?>

<div class="panel">
  <div class="table-responsive">
    <table class="table table-dark table-sm align-middle">
      <thead>
        <tr>
          <th>Tipo</th>
          <th>Grado</th>
          <th>Apellido y Nombre</th>
          <th>Ejercicio</th>
          <th>Resultado</th>
          <th>Fecha</th>
          <th>Documento</th>
          <th>Actualizado por</th>
          <th>Creado</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($condiciones)): ?>
        <tr><td colspan="9" class="text-center text-muted">Sin registros.</td></tr>
      <?php else: ?>
        <?php foreach ($condiciones as $r): ?>
        <tr>
          <td><?= e($r['tipo']) ?></td>
          <td><?= e($r['grado']) ?></td>
          <td><?= e($r['nombre']) ?></td>
          <td><?= e($r['ejercicio']) ?></td>
          <td><?= e($r['resultado']) ?></td>
          <td><?= e($r['fecha']) ?></td>
          <td><?= $r['documento'] ? e($r['documento']) : '<span class="text-muted">(sin)</span>' ?></td>
          <td><?= e($r['actualizado_por']) ?></td>
          <td><?= e($r['creado_en']) ?></td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php operaciones_render_chat_widget($pdo); ?>
</body>
</html>
