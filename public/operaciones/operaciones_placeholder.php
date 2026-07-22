<?php
declare(strict_types=1);

require_once __DIR__ . '/../../auth/bootstrap.php';
require_login();

function operaciones_placeholder_e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function operaciones_placeholder_render(string $title, string $subtitle = 'Pagina pendiente de implementacion.'): void
{
    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
    $public = rtrim(str_replace('\\', '/', dirname($dir)), '/');
    $app = rtrim(str_replace('\\', '/', dirname($public)), '/');
    $assets = $app . '/assets';
    $bg = $assets . '/img/fondo.png';
    $icon = $assets . '/img/ecmilm.png';
    ?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title><?= operaciones_placeholder_e($title) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="<?= operaciones_placeholder_e($icon) ?>">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
html, body { min-height: 100%; }
body {
    margin: 0;
    color: #e5e7eb;
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, Ubuntu, sans-serif;
    background:
        linear-gradient(160deg, rgba(2,6,23,.88), rgba(15,23,42,.80)),
        url("<?= operaciones_placeholder_e($bg) ?>") center/cover fixed;
}
.page-wrap {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
}
.panel {
    width: min(720px, 100%);
    background: rgba(15, 23, 42, .94);
    border: 1px solid rgba(148, 163, 184, .38);
    border-radius: 14px;
    box-shadow: 0 20px 45px rgba(0, 0, 0, .55);
    padding: 28px;
}
.eyebrow {
    color: #86efac;
    font-size: .78rem;
    font-weight: 800;
    letter-spacing: .08em;
    text-transform: uppercase;
    margin-bottom: 8px;
}
h1 {
    font-size: clamp(1.45rem, 4vw, 2.2rem);
    font-weight: 900;
    margin: 0 0 10px;
}
p {
    color: #cbd5e1;
    margin: 0;
}
.actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 24px;
}
.btn-nav {
    font-weight: 800;
    border-radius: 8px;
    padding: .58rem 1rem;
}
</style>
</head>
<body>
<main class="page-wrap">
  <section class="panel">
    <div class="eyebrow">S-3 Operaciones</div>
    <h1><?= operaciones_placeholder_e($title) ?></h1>
    <p><?= operaciones_placeholder_e($subtitle) ?></p>
    <div class="actions">
      <a class="btn btn-success btn-nav" href="operaciones.php">Volver a Operaciones</a>
      <a class="btn btn-outline-light btn-nav" href="../inicio.php">Volver a Inicio</a>
    </div>
  </section>
</main>
</body>
</html>
<?php
}
