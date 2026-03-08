<?php
// public/s3_tiro_ejercicios.php — Ejercicios de tiro
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

    $cod   = trim((string)($_POST['codigo'] ?? ''));
    $desc  = trim((string)($_POST['descripcion'] ?? ''));
    $part  = trim((string)($_POST['participantes'] ?? ''));
    $res   = ($_POST['resultado'] ?? 'NO_APROBO') === 'APROBO' ? 'APROBO' : 'NO_APROBO';
    $fecha = trim((string)($_POST['fecha'] ?? ''));
    $doc   = trim((string)($_POST['documento'] ?? ''));

    if ($cod !== '' && $desc !== '') {
        $st = $pdo->prepare("
            INSERT INTO s3_tiro_ejercicios
            (codigo,descripcion,participantes,resultado,fecha,documento,actualizado_por)
            VALUES (?,?,?,?,?,?,?)
        ");
        $st->execute([
            $cod,
            $desc,
            $part !== '' ? $part : null,
            $res,
            $fecha !== '' ? $fecha : null,
            $doc !== '' ? $doc : null,
            $user,
        ]);
    }

    header('Location: s3_tiro_ejercicios.php');
    exit;
}

/* Datos */
$ej = $pdo->query("SELECT * FROM s3_tiro_ejercicios ORDER BY fecha DESC, codigo")
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
<title>Ejercicios de tiro · S-3 Tiro</title>
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
.badge-ok{ background:#16a34a; }
.badge-no{ background:#dc2626; }
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
        <div class="brand-sub">S-3 · Tiro · Ejercicios</div>
      </div>
    </div>
    <a href="s3_tiro.php" class="btn btn-secondary btn-sm fw-bold">Volver</a>
  </div>
</header>

<div class="page-wrap">
<div class="container-main">

<h3 class="fw-bold mb-3">Ejercicios de tiro realizados</h3>

<div class="panel">
  <h6 class="fw-bold mb-2">Alta rápida</h6>
  <form method="post" class="row g-2">
    <?php if (function_exists('csrf_input')) echo csrf_input(); ?>

    <div class="col-md-2">
      <label class="form-label form-label-sm">Código</label>
      <input name="codigo" class="form-control form-control-sm" placeholder="EJ-04" required>
    </div>
    <div class="col-md-3">
      <label class="form-label form-label-sm">Descripción</label>
      <input name="descripcion" class="form-control form-control-sm" required>
    </div>
    <div class="col-md-2">
      <label class="form-label form-label-sm">Participantes</label>
      <input name="participantes" class="form-control form-control-sm" placeholder="Cuadros / Tropa">
    </div>
    <div class="col-md-2">
      <label class="form-label form-label-sm">Resultado</label>
      <select name="resultado" class="form-select form-select-sm">
        <option value="APROBO">APROBÓ</option>
        <option value="NO_APROBO">NO APROBÓ</option>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label form-label-sm">Fecha</label>
      <input type="date" name="fecha" class="form-control form-control-sm">
    </div>
    <div class="col-md-3">
      <label class="form-label form-label-sm">Documento</label>
      <input name="documento" class="form-control form-control-sm" placeholder="planilla_ej04.pdf">
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
          <th>Ejercicio</th><th>Descripción</th><th>Participantes</th>
          <th>Resultado</th><th>Fecha</th><th>Documento</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($ej)): ?>
        <tr><td colspan="6" class="text-center text-muted">Sin registros.</td></tr>
      <?php else: ?>
        <?php foreach($ej as $x): ?>
        <tr>
          <td><?= e($x['codigo']) ?></td>
          <td><?= e($x['descripcion']) ?></td>
          <td><?= e($x['participantes'] ?? '') ?></td>
          <td>
            <?php if ($x['resultado'] === 'APROBO'): ?>
              <span class="badge badge-ok">APROBÓ</span>
            <?php else: ?>
              <span class="badge badge-no">NO APROBÓ</span>
            <?php endif; ?>
          </td>
          <td><?= e($x['fecha'] ?? '') ?></td>
          <td><?= $x['documento'] ? e($x['documento']) : '<span class="text-muted">Pendiente</span>' ?></td>
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
