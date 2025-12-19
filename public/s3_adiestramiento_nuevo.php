<?php
// public/s3_adiestramiento_nuevo.php — Crear nuevo año de Adiestramiento (PAFB)
declare(strict_types=1);

$OFFLINE_MODE = false;
require_once __DIR__ . '/../auth/bootstrap.php';
if (!$OFFLINE_MODE) require_login();

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$PUBLIC_URL = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$APP_URL    = rtrim(dirname($PUBLIC_URL), '/');
$ASSETS     = ($APP_URL===''?'':$APP_URL).'/assets';

$IMG_BG     = $ASSETS.'/img/fondo.png';

/* ==========================
   Año sugerido: próximo año disponible (no pasado)
   ========================== */
function next_available_year(string $dirAbs): int {
  $years = [];
  foreach (glob($dirAbs . '/s3_adiestramiento_*.php') ?: [] as $f) {
    if (preg_match('/s3_adiestramiento_(\d{4})\.php$/', basename($f), $m)) {
      $years[] = (int)$m[1];
    }
  }
  $current = (int)date('Y');
  if (empty($years)) return $current + 1;

  $max = max($years);
  return max($current + 1, $max + 1);
}

/* ==========================
   Elegir archivo plantilla (el año más nuevo existente)
   ========================== */
function latest_year_file(string $dirAbs): ?string {
  $bestYear = null;
  $bestFile = null;

  foreach (glob($dirAbs . '/s3_adiestramiento_*.php') ?: [] as $f) {
    $base = basename($f);
    if (preg_match('/^s3_adiestramiento_(\d{4})\.php$/', $base, $m)) {
      $y = (int)$m[1];
      if ($bestYear === null || $y > $bestYear) {
        $bestYear = $y;
        $bestFile = $f;
      }
    }
  }
  return $bestFile;
}

/* ==========================
   Construir contenido del año nuevo:
   - Copia el último año existente (para mantener MISMO formato y funcionalidades)
   - Aplica parches mínimos si detecta YEAR/CSRF hardcodeados
   ========================== */
function build_year_template_from_latest(int $anio, string $dirAbs): string {
  $src = latest_year_file($dirAbs);

  if ($src && is_file($src)) {
    $code = @file_get_contents($src);
    if ($code !== false && $code !== '') {

      // 1) Actualizar comentario/header si aparece el nombre del archivo con otro año (solo 1ra vez)
      $code = preg_replace(
        '/s3_adiestramiento_\d{4}\.php/',
        's3_adiestramiento_'.$anio.'.php',
        $code,
        1
      );

      // 2) Si el template tiene $YEAR hardcodeado, lo reemplazamos por cálculo desde filename
      //    (solo si NO existe ya el bloque "basename(__FILE__)" con regex)
      $hasFilenameYear = (bool)preg_match('/basename\(__FILE__\)/', $code);

      if (!$hasFilenameYear) {
        // Reemplazar una asignación $YEAR = 2025; (si existe)
        $code = preg_replace(
          '/\$YEAR\s*=\s*\d{4}\s*;/',
          "\$YEAR = (int)date('Y');\n\$base = basename(__FILE__);\nif (preg_match('/^s3_adiestramiento_(\\d{4})\\.php\$/', \$base, \$m)) \$YEAR = (int)\$m[1];",
          $code,
          1
        );
      }

      // 3) Si el CSRF key estuviera hardcodeado tipo 'csrf_s3_pafb_2025', lo parcheamos a $YEAR
      $code = preg_replace(
        "/\\\$csrfKey\\s*=\\s*'csrf_s3_pafb_\\d{4}'\\s*;/",
        "\$csrfKey = 'csrf_s3_pafb_' . \$YEAR;",
        $code,
        1
      );

      return $code;
    }
  }

  // Fallback ultra seguro (si no existe ningún año todavía):
  return <<<PHP
<?php
// public/s3_adiestramiento_{$anio}.php — PAFB (plantilla mínima)
declare(strict_types=1);

\$OFFLINE_MODE = false;
require_once __DIR__ . '/../auth/bootstrap.php';
if (!\$OFFLINE_MODE) require_login();

function e(\$v){ return htmlspecialchars((string)\$v, ENT_QUOTES, 'UTF-8'); }

\$YEAR = (int)date('Y');
\$base = basename(__FILE__);
if (preg_match('/^s3_adiestramiento_(\\d{4})\\.php\$/', \$base, \$m)) \$YEAR = (int)\$m[1];

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>S-3 · PAFB <?= e((string)\$YEAR) ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/theme-602.css">
</head>
<body class="p-4">
  <h3>PAFB <?= e((string)\$YEAR) ?></h3>
  <p>Plantilla mínima creada. Copiá contenido desde un año existente si querés el formato completo.</p>
  <a class="btn btn-success" href="s3_adiestramiento.php">Volver</a>
</body>
</html>
PHP;
}

/* ==========================
   Estado inicial
   ========================== */
$anioNuevo  = next_available_year(__DIR__);
$successMsg = '';
$errorMsg   = '';

/* ==========================
   POST: crear archivo
   ========================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $anioGenerar = isset($_POST['anio']) ? (int)$_POST['anio'] : $anioNuevo;
  $anioActual  = (int)date('Y');

  if ($anioGenerar <= $anioActual) {
    $errorMsg = 'Solo se permite crear años futuros (mínimo: ' . ($anioActual + 1) . ').';
  } elseif ($anioGenerar < 2000 || $anioGenerar > 2100) {
    $errorMsg = 'Año inválido.';
  } else {
    $targetDir  = __DIR__; // public/
    $targetFile = $targetDir . '/s3_adiestramiento_' . $anioGenerar . '.php';

    if (!is_dir($targetDir)) {
      $errorMsg = 'No existe el directorio destino: ' . $targetDir;
    } elseif (file_exists($targetFile)) {
      $sug = next_available_year($targetDir);
      $errorMsg = 'Ya existe el archivo para el año ' . $anioGenerar . ' (s3_adiestramiento_' . $anioGenerar . '.php).'
                . ' Sugerencia: ' . $sug . '.';
    } elseif (!is_writable($targetDir)) {
      $errorMsg = 'No se puede escribir en: ' . $targetDir . ' (permisos).'
                . ' El servidor web (www-data) no tiene permisos para crear archivos ahí.';
    } else {
      $template = build_year_template_from_latest($anioGenerar, $targetDir);
      $bytes = @file_put_contents($targetFile, $template, LOCK_EX);

      if ($bytes === false) {
        $errorMsg = 'No se pudo crear el archivo s3_adiestramiento_' . $anioGenerar . '.php. Verificá permisos de escritura en public/.';
      } else {
        $successMsg = 'Se creó correctamente el archivo: s3_adiestramiento_' . $anioGenerar . '.php';
        $anioNuevo  = next_available_year(__DIR__); // refresca sugerencia
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
    body::before{
      content:"";position:fixed;inset:0;
      background:radial-gradient(circle at top, rgba(15,23,42,.75), rgba(15,23,42,.95));
      pointer-events:none;z-index:-1;
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

    /* Botón volver (mismo estilo) */
    .header-back{
      margin-top:10px;
      display:flex;
      justify-content:flex-end;
    }
    .btn-back{
      display:inline-block;
      border-radius:999px;
      border:1px solid rgba(148,163,184,.55);
      background:rgba(15,23,42,.8);
      color:#e5e7eb;
      font-size:.85rem;
      font-weight:800;
      padding:.45rem 1rem;
      text-decoration:none;
      box-shadow:0 10px 30px rgba(0,0,0,.55);
      white-space:nowrap;
    }
    .btn-back:hover{
      background:rgba(30,64,175,.9);
      border-color:rgba(129,140,248,.9);
      color:white;
    }

    /* ✅ FIX: alinear input + botón (misma altura y centrado) */
    .year-input-group .form-control,
    .year-input-group .btn{
      height:44px;
    }
    .year-input-group .btn{
      display:flex;
      align-items:center;
      gap:.45rem;
      white-space:nowrap;
    }
    .year-input-group .form-control{
      max-width:180px; /* como en tu captura */
    }
  </style>
</head>
<body>

<?php
// Si tu header existe, lo incluimos. Si no, no rompemos la página.
$hdr = __DIR__ . '/s3_adiestramiento_header.php';
if (is_file($hdr)) { include $hdr; }
?>

<div class="page-wrap">
  <div class="container-main">

    <h3 class="fw-bold mb-3">Crear un nuevo período de Adiestramiento</h3>

    <div class="header-back mb-3">
      <a href="s3_adiestramiento.php" class="btn-back">⬅ Volver a PAFB</a>
    </div>

    <?php if ($successMsg): ?>
      <div class="alert alert-success">
        <?= e($successMsg) ?><br>
        Ya debería aparecer automáticamente la tarjeta del año en <code>s3_adiestramiento.php</code> (se detecta por <code>glob()</code>).
      </div>
    <?php endif; ?>

    <?php if ($errorMsg): ?>
      <div class="alert alert-danger">
        <?= e($errorMsg) ?>
      </div>
    <?php endif; ?>

    <div class="panel">
      <p class="mb-2">
        Elegí manualmente el año a generar (No se pueden generar años que ya pasaron).
        Sugerencia automática:
      </p>
      <h2 class="text-success fw-bold mb-3"><?= e((string)$anioNuevo) ?></h2>

      <form method="post">
        <div class="mb-1">
          <label class="form-label fw-bold mb-1">Año a generar</label>

          <div class="input-group year-input-group">
            <input type="number"
                   name="anio"
                   class="form-control"
                   min="<?= e((string)((int)date('Y') + 1)) ?>"
                   max="2100"
                   value="<?= e((string)$anioNuevo) ?>"
                   required>

            <button type="submit" class="btn btn-success fw-bold">
              ➕ Generar
            </button>
          </div>

          <div class="form-text text-light" style="opacity:.75">
            Solo futuro (mínimo <?= e((string)((int)date('Y') + 1)) ?>).
          </div>
        </div>
      </form>

      <div class="text-light mt-3" style="opacity:.85">
        Si el año ya existe, no se podrá crear y te sugiere el siguiente disponible.
      </div>
    </div>

  </div>
</div>

</body>
</html>
