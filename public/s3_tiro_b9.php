<?php
// public/s3_tiro_b9.php — Condición B9
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

    $grado  = trim((string)($_POST['grado'] ?? ''));
    $nombre = trim((string)($_POST['nombre'] ?? ''));
    $ej     = trim((string)($_POST['ejercicio'] ?? ''));
    $res    = ($_POST['resultado'] ?? 'NO_APROBO') === 'APROBO' ? 'APROBO' : 'NO_APROBO';
    $obs    = trim((string)($_POST['observaciones'] ?? ''));
    $fecha  = trim((string)($_POST['fecha'] ?? ''));
    $doc    = trim((string)($_POST['documento'] ?? ''));

    if ($grado !== '' && $nombre !== '' && $ej !== '') {
        $st = $pdo->prepare("
            INSERT INTO s3_tiro_b9
            (grado,nombre,ejercicio,resultado,observaciones,fecha,documento,actualizado_por)
            VALUES (?,?,?,?,?,?,?,?)
        ");
        $st->execute([
            $grado,
            $nombre,
            $ej,
            $res,
            $obs !== '' ? $obs : null,
            $fecha !== '' ? $fecha : null,
            $doc !== '' ? $doc : null,
            $user,
        ]);
    }

    header('Location: s3_tiro_b9.php');
    exit;
}

/* Datos */
$b9 = $pdo->query("SELECT * FROM s3_tiro_b9 ORDER BY fecha DESC, grado, nombre")
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
<title>Condición B9 · S-3 Tiro</title>
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
  background:rgba(15,23,42,.92);
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
        <div class="brand-sub">S-3 · Tiro · Condición B9</div>
      </div>
    </div>
    <a href="s3_tiro.php" class="btn btn-secondary btn-sm fw-bold">Volver</a>
  </div>
</header>

<div class="page-wrap">
<div class="container-main">

<h3 class="fw-bold mb-3">Condición B9 · Aptos para Servicios de Vigilancia</h3>

<div class="panel">
  <h6 class="fw-bold mb-2">Alta rápida</h6>
  <form method="post" class="row g-2">
    <?php if (function_exists('csrf_input')) echo csrf_input(); ?>

    <div class="col-md-2">
      <label class="form-label form-label-sm">Grado</label>
      <input name="grado" class="form-control form-control-sm" required>
    </div>
    <div class="col-md-3">
      <label class="form-label form-label-sm">Apellido y Nombre</label>
      <input name="nombre" class="form-control form-control-sm" required>
    </div>
    <div class="col-md-2">
      <label class="form-label form-label-sm">Ejercicio</label>
      <input name="ejercicio" class="form-control form-control-sm" placeholder="B9 - FUSA" required>
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
      <label class="form-label form-label-sm">Observaciones</label>
      <input name="observaciones" class="form-control form-control-sm">
    </div>
    <div class="col-md-3">
      <label class="form-label form-label-sm">Documento</label>
      <input name="documento" class="form-control form-control-sm" placeholder="acta_b9_2025.pdf">
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
          <th>Grado</th><th>Apellido y Nombre</th><th>Ejercicio</th>
          <th>Resultado</th><th>Observaciones</th><th>Fecha</th><th>Documento</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($b9)): ?>
        <tr><td colspan="7" class="text-center text-muted">Sin registros.</td></tr>
      <?php else: ?>
        <?php foreach($b9 as $r): ?>
        <tr>
          <td><?= e($r['grado']) ?></td>
          <td><?= e($r['nombre']) ?></td>
          <td><?= e($r['ejercicio']) ?></td>
          <td>
            <?php if ($r['resultado'] === 'APROBO'): ?>
              <span class="badge badge-ok">APROBÓ</span>
            <?php else: ?>
              <span class="badge badge-no">NO APROBÓ</span>
            <?php endif; ?>
          </td>
          <td><?= e($r['observaciones'] ?? '') ?></td>
          <td><?= e($r['fecha'] ?? '') ?></td>
          <td><?= $r['documento'] ? e($r['documento']) : '<span class="text-muted">Pendiente</span>' ?></td>
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
