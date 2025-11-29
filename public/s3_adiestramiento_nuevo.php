<?php
// public/s3_adiestramiento_nuevo.php — Crear nuevo año de Adiestramiento
declare(strict_types=1);

$OFFLINE_MODE = false;
require_once __DIR__ . '/../auth/bootstrap.php';
if(!$OFFLINE_MODE) require_login();

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$PUBLIC_URL = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$APP_URL    = rtrim(dirname($PUBLIC_URL), '/');
$ASSETS     = ($APP_URL===''?'':$APP_URL).'/assets';
$IMG_BG     = $ASSETS.'/img/fondo.png';
$ESCUDO     = $ASSETS.'/img/escudo_bcom602.png';

$anioNuevo = date('Y') + 1; // próximo año automático
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Nuevo año de Adiestramiento · S-3</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="../assets/css/theme-602.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{
  background:url("<?= e($IMG_BG) ?>") center/cover fixed;
  background-color:#020617;
  color:#e5e7eb;
  font-family:system-ui;
}
.page-wrap{ padding:20px; }
.container-main{ max-width:800px; margin:auto; }

.panel{
  background:rgba(15,23,42,.92);
  border-radius:18px;
  border:1px solid rgba(148,163,184,.45);
  padding:20px;
  box-shadow:0 20px 45px rgba(0,0,0,.85);
}
</style>
</head>
<body>

<?php include __DIR__.'/s3_adiestramiento_header.php'; ?>

<div class="page-wrap">
  <div class="container-main">

    <h3 class="fw-bold mb-4">Crear un nuevo período de Adiestramiento</h3>

    <div class="panel">
      <p class="mb-3">Se generará un archivo PHP base para el año:</p>
      <h2 class="text-success fw-bold"><?= $anioNuevo ?></h2>

      <p class="text-muted">Luego podrás modificarlo para agregar personal, aprobados, documentos, etc.</p>

      <a class="btn btn-success fw-bold mt-3" href="#">
        ➕ Generar año <?= $anioNuevo ?>
      </a>
    </div>

  </div>
</div>

</body>
</html>
