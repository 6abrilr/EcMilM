<?php
// public/s3_tiro_municion.php — Consumo de munición
declare(strict_types=1);

$OFFLINE_MODE = false;
require_once __DIR__ . '/../auth/bootstrap.php';
if(!$OFFLINE_MODE) require_login();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/s3_tiro_tables_helper.php';

s3_tiro_ensure_tables($pdo);

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$user = $_SESSION['user']['username'] ?? null;

/* Alta rápida */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (function_exists('csrf_verify')) csrf_verify();

    $cal   = trim((string)($_POST['calibre'] ?? ''));
    $cant  = (int)($_POST['cantidad'] ?? 0);
    $fecha = trim((string)($_POST['fecha'] ?? ''));
    $uso   = trim((string)($_POST['uso'] ?? ''));
    $doc   = trim((string)($_POST['documento'] ?? ''));

    if ($cal !== '' && $cant > 0) {
        $st = $pdo->prepare("
            INSERT INTO s3_tiro_municion
            (calibre,cantidad,fecha,uso,documento,actualizado_por)
            VALUES (?,?,?,?,?,?)
        ");
        $st->execute([
            $cal,
            $cant,
            $fecha !== '' ? $fecha : null,
            $uso !== '' ? $uso : null,
            $doc !== '' ? $doc : null,
            $user,
        ]);
    }
    header('Location: s3_tiro_municion.php');
    exit;
}

/* Datos */
$municion = $pdo->query("SELECT * FROM s3_tiro_municion ORDER BY fecha DESC, calibre")
                ->fetchAll(PDO::FETCH_ASSOC);

/* Assets */
$PUBLIC_URL = rtrim(str_replace("\\","/", dirname($_SERVER["SCRIPT_NAME"] ?? "")), "/");
$APP_URL    = rtrim(dirname($PUBLIC_URL), "/");
$ASSETS     = ($APP_URL==="" ? "" : $APP_URL)."/assets";
$IMG_BG     = $ASSETS."/img/fondo.png";
$ESCUDO     = $ASSETS."/img/escudo_bcom602.png";
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Consumo de munición · S-3 Tiro</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="../assets/css/theme-602.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{
  background:url("<?= e($IMG_BG) ?>") center/cover fixed;
  background-color:#020617; color:#e5e7eb;
}
.page-wrap{ padding:22px 16px 32px; }
.container-main{ max-width:1200px; margin:0 auto; }
.panel{
  background:rgba(15,23,42,.9);
  border-radius:18px;
  padding:20px;
  border:1px solid rgba(148,163,184,.45);
  margin-bottom:18px;
}
.table-sm th,.table-sm td{ font-size:.82rem; }
</style>
</head>
<body>

<header class="brand-hero">
  <div class="container-main d-flex justify-content-between py-2">
    <div class="d-flex gap-3 align-items-center">
      <img src="<?= e($ESCUDO) ?>" style="height:55px;">
      <div>
        <div class="brand-title">Batallón de Comunicaciones 602</div>
        <div class="brand-sub">S-3 · Tiro · Consumo de munición</div>
      </div>
    </div>
    <a href="s3_tiro.php" class="btn btn-secondary btn-sm fw-bold">Volver</a>
  </div>
</header>

<div class="page-wrap">
<div class="container-main">

<h3 class="fw-bold mb-3">Consumo de munición</h3>

<div class="panel">
  <h6 class="fw-bold mb-2">Registro rápido</h6>
  <form method="post" class="row g-2">
    <?php if (function_exists('csrf_input')) echo csrf_input(); ?>

    <div class="col-md-2">
      <label class="form-label form-label-sm">Calibre</label>
      <input name="calibre" class="form-control form-control-sm" placeholder="9mm / 5.56" required>
    </div>
    <div class="col-md-2">
      <label class="form-label form-label-sm">Cantidad</label>
      <input type="number" name="cantidad" class="form-control form-control-sm" min="1" required>
    </div>
    <div class="col-md-2">
      <label class="form-label form-label-sm">Fecha</label>
      <input type="date" name="fecha" class="form-control form-control-sm">
    </div>
    <div class="col-md-3">
      <label class="form-label form-label-sm">Uso</label>
      <input name="uso" class="form-control form-control-sm" placeholder="Práctica AMI, Ejercicios B9, etc.">
    </div>
    <div class="col-md-3">
      <label class="form-label form-label-sm">Documento</label>
      <input name="documento" class="form-control form-control-sm" placeholder="remito_...pdf">
    </div>
    <div class="col-md-2 d-flex align-items-end">
      <button class="btn btn-success btn-sm w-100 fw-bold">➕ Agregar</button>
    </div>
  </form>
</div>

<div class="panel">
  <div class="table-responsive">
    <table class="table table-dark table-sm align-middle">
      <thead>
        <tr>
          <th>Calibre</th><th>Cantidad</th><th>Fecha</th><th>Uso</th><th>Documento</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($municion)): ?>
        <tr><td colspan="5" class="text-center text-muted">Sin registros.</td></tr>
      <?php else: ?>
        <?php foreach($municion as $m): ?>
        <tr>
          <td><?= e($m['calibre']) ?></td>
          <td><?= e((string)$m['cantidad']) ?></td>
          <td><?= e($m['fecha'] ?? '') ?></td>
          <td><?= e($m['uso'] ?? '') ?></td>
          <td><?= $m['documento'] ? e($m['documento']) : '<span class="text-muted">Pendiente</span>' ?></td>
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
