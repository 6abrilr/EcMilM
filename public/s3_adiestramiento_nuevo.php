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

$anioNuevo = (int)date('Y') + 1; // próximo año automático

// Mensajes para mostrar en la vista
$successMsg = '';
$errorMsg   = '';

// ==== LÓGICA DE GENERACIÓN DE ARCHIVO ====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Podrías permitir elegir el año, pero por ahora usamos el próximo
    $anioGenerar = isset($_POST['anio']) ? (int)$_POST['anio'] : $anioNuevo;
    if ($anioGenerar < 2000 || $anioGenerar > 2100) {
        $errorMsg = 'Año inválido.';
    } else {
        // Ruta donde se creará el archivo del año
        $targetFile = __DIR__ . '/s3_adiestramiento_' . $anioGenerar . '.php';

        if (file_exists($targetFile)) {
            $errorMsg = 'Ya existe el archivo para el año ' . $anioGenerar . ' (s3_adiestramiento_' . $anioGenerar . '.php).';
        } else {

            // Contenido base del archivo del año
            $template = <<<PHP
<?php
// public/s3_adiestramiento_{$anioGenerar}.php — PAFB {$anioGenerar}
declare(strict_types=1);

\$OFFLINE_MODE = false;

require_once __DIR__ . '/../auth/bootstrap.php';
if (!\$OFFLINE_MODE) require_login();
require_once __DIR__ . '/../config/db.php';

function e(\$v){ return htmlspecialchars((string)\$v, ENT_QUOTES, 'UTF-8'); }

\$PUBLIC_URL = rtrim(str_replace('\\\\','/', dirname(\$_SERVER['SCRIPT_NAME'] ?? '')), '/');
\$APP_URL    = rtrim(dirname(\$PUBLIC_URL), '/');
\$ASSETS     = (\$APP_URL === '' ? '' : \$APP_URL) . '/assets';
\$IMG_BG     = \$ASSETS . '/img/fondo.png';
\$ESCUDO     = \$ASSETS . '/img/escudo_bcom602.png';

// DEMO: acá después vas a reemplazar por datos reales desde la BD
\$total = 0;
\$aprob = 0;
\$porc  = 0;
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>S-3 · Adiestramiento físico-militar {$anioGenerar} · B Com 602</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/theme-602.css">
<link rel="icon" type="image/png" href="../assets/img/bcom602.png">
</head>
<body>

<?php include __DIR__ . '/s3_adiestramiento_header.php'; ?>

<div class="container py-4">
  <h1 class="h4 text-light mb-3">PAFB {$anioGenerar}</h1>
  <p class="text-muted">
    Pantalla generada automáticamente. Completá aquí el detalle del año {$anioGenerar}
    (personal, aprobados, documentación, etc.).
  </p>

  <!-- Acá después podés poner la tabla por persona, carga de planillas, etc. -->
</div>

</body>
</html>
PHP;

            // Intentar escribir el archivo
            $bytes = @file_put_contents($targetFile, $template);

            if ($bytes === false) {
                $errorMsg = 'No se pudo crear el archivo s3_adiestramiento_' . $anioGenerar . '.php. Verificá permisos de escritura en la carpeta public/.';
            } else {
                $successMsg = 'Se creó correctamente el archivo: s3_adiestramiento_' . $anioGenerar . '.php';
            }
        }
    }
}
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
  font-family:system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,sans-serif;
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

    <?php if ($successMsg): ?>
      <div class="alert alert-success">
        <?= e($successMsg) ?><br>
        Ahora agregá este año en <code>s3_adiestramiento.php</code> dentro del array <code>$aniosRaw</code> para que aparezca la tarjeta.
      </div>
    <?php endif; ?>

    <?php if ($errorMsg): ?>
      <div class="alert alert-danger">
        <?= e($errorMsg) ?>
      </div>
    <?php endif; ?>

    <div class="panel">
      <p class="mb-3">Se generará un archivo PHP base para el año:</p>
      <h2 class="text-success fw-bold"><?= e($anioNuevo) ?></h2>

      <p class="text-muted">Luego podrás modificarlo para agregar personal, aprobados, documentos, etc.</p>

      <form method="post" class="mt-3">
        <input type="hidden" name="anio" value="<?= e($anioNuevo) ?>">
        <button type="submit" class="btn btn-success fw-bold">
          ➕ Generar año <?= e($anioNuevo) ?>
        </button>
      </form>
    </div>

  </div>
</div>

</body>
</html>
