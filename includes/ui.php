<?php
if (!function_exists('e')) {
  function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

function ui_header($title='Bitácora de Inspección', $opts=[]){
  $show_brand = $opts['show_brand'] ?? true;
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title><?= e($title) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="icon" type="image/png" href="../assets/img/bcom602.png">
  <style>
    body {
      background: radial-gradient(circle at 20% 30%, #1E2A52, #0E153A 80%);
      color:#fff; font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu; margin:0;
      padding:2rem; min-height:100vh;
    }
    .app-card{background:rgba(255,255,255,.08);border-radius:16px;backdrop-filter:blur(6px);padding:1rem}
    .btn-602{background:#1E2A52;border:none;color:#fff;border-radius:12px;font-weight:600}
    .btn-602:hover{background:#2A3C73}
  </style>
</head>
<body>
  <?php if($show_brand): ?>
  <header class="mb-4">
    <div class="d-flex align-items-center gap-3">
      <img src="../assets/img/logo.png" alt="Logo" height="60">
      <div>
        <h4 class="m-0">Batallón de Comunicaciones 602</h4>
        <div class="text-light small">“Hogar de las Comunicaciones fijas del Ejército”</div>
      </div>
    </div>
  </header>
  <?php endif; ?>
<?php
}

function ui_footer(){
  echo "</body></html>";
}
