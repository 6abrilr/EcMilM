<?php
// public/division_ensenanza/division_ensenanza.php - Portada Division Ensenanza
declare(strict_types=1);

require_once __DIR__ . '/../../auth/bootstrap.php';
require_login();
require_once __DIR__ . '/../../config/db.php';

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$user = function_exists('current_user') ? current_user() : ($_SESSION['user'] ?? null);

$SELF_WEB        = (string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
$BASE_DIR_WEB    = rtrim(str_replace('\\', '/', dirname($SELF_WEB)), '/');
$BASE_PUBLIC_WEB = rtrim(str_replace('\\', '/', dirname($BASE_DIR_WEB)), '/');
$BASE_APP_WEB    = rtrim(str_replace('\\', '/', dirname($BASE_PUBLIC_WEB)), '/');
$ASSET_WEB       = $BASE_APP_WEB . '/assets';

$IMG_BG  = $ASSET_WEB . '/img/fondo.png';
$ESCUDO  = $ASSET_WEB . '/img/ecmilm.png';
$FAVICON = $ASSET_WEB . '/img/ecmilm.png';

$fullName = '';
$dniNorm = '';
if (is_array($user)) {
  $dniNorm = preg_replace('/\D+/', '', (string)($user['dni'] ?? $user['username'] ?? '')) ?? '';
}

try {
  if ($dniNorm !== '') {
    $st = $pdo->prepare("
      SELECT CONCAT_WS(' ', grado, arma, apellido, nombre) AS nombre_comp
      FROM personal_unidad
      WHERE REPLACE(REPLACE(REPLACE(dni,'.',''),'-',''),' ','') = :dni
      LIMIT 1
    ");
    $st->execute([':dni' => $dniNorm]);
    $fullName = trim((string)($st->fetchColumn() ?: ''));
  }
} catch (Throwable $e) {}

if ($fullName === '' && is_array($user)) {
  $fullName = trim((string)($user['apellido_nombre'] ?? $user['nombre_completo'] ?? $user['display_name'] ?? $user['username'] ?? ''));
}

$modules = [
  [
    'icon' => 'bi-folder2-open',
    'title' => 'Carpeta compartida',
    'desc' => 'Abrir el explorador de archivos de Division Ensenanza.',
    'url' => 'division_ensenanza_carpeta.php',
  ],
  [
    'icon' => 'bi-easel2',
    'title' => 'Clases',
    'desc' => 'Gestionar cursos, instructores, cursantes, contenidos y calificaciones.',
    'url' => 'division_ensenanza_clases.php',
  ],
];
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Division Ensenanza</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= e($ASSET_WEB) ?>/css/theme-602.css">
<link rel="icon" href="<?= e($FAVICON) ?>">
<style>
  html,body{height:100%}
  body{margin:0;color:#e5e7eb;background:#000;font-family:system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,sans-serif}
  .page-bg{position:fixed;inset:0;z-index:-2;pointer-events:none;background:linear-gradient(160deg,rgba(0,0,0,.86),rgba(0,0,0,.66) 55%,rgba(0,0,0,.86)),url("<?= e($IMG_BG) ?>") center/cover fixed no-repeat;filter:saturate(1.05)}
  .page-bg::before{content:"";position:absolute;inset:0;z-index:-1;opacity:.16;background-image:radial-gradient(1.4px 1.4px at 18% 22%,#9cd1ff 20%,transparent 60%),radial-gradient(1.2px 1.2px at 63% 48%,#b7ddff 20%,transparent 60%),radial-gradient(1.2px 1.2px at 82% 70%,#b7ddff 20%,transparent 60%),radial-gradient(1.6px 1.6px at 34% 76%,#cbe8ff 20%,transparent 60%);background-repeat:no-repeat}
  .container-main{max-width:1180px;margin:auto;padding:18px}
  .brand-hero{padding:10px 0}.hero-inner{display:flex;align-items:center;gap:12px}
  .brand-logo{width:58px;height:58px;object-fit:contain;filter:drop-shadow(0 10px 18px rgba(0,0,0,.55))}
  .brand-title{font-weight:900;font-size:1.15rem;line-height:1.1;color:#e5e7eb}.brand-sub{font-size:.9rem;color:#cbd5f5;opacity:.9;margin-top:2px}
  .header-actions{margin-left:auto;display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}
  .panel{background:rgba(15,17,23,.94);border:1px solid rgba(148,163,184,.40);border-radius:18px;padding:18px 22px 22px;box-shadow:0 18px 40px rgba(0,0,0,.75),inset 0 1px 0 rgba(255,255,255,.05)}
  .panel-title{display:flex;align-items:center;gap:.55rem;margin-bottom:6px;font-size:1.05rem;font-weight:900}
  .panel-sub{max-width:780px;margin-bottom:18px;color:#cbd5f5;font-size:.9rem;line-height:1.5}
  .layout{display:grid;grid-template-columns:300px 1fr;gap:18px;align-items:start}
  .sidebar-box{background:rgba(15,23,42,.95);border:1px solid rgba(148,163,184,.45);border-radius:16px;padding:14px;box-shadow:0 10px 28px rgba(0,0,0,.75)}
  .sidebar-title{display:flex;align-items:center;gap:.5rem;margin-bottom:10px;color:#9ca3af;font-size:.88rem;font-weight:800;letter-spacing:.05em;text-transform:uppercase}
  .module-list{display:grid;gap:10px}
  .module-link{display:flex;align-items:center;gap:10px;padding:12px;color:#e5e7eb;text-decoration:none;background:rgba(2,6,23,.72);border:1px solid rgba(148,163,184,.28);border-radius:14px;transition:background .15s ease,border-color .15s ease,transform .15s ease}
  .module-link:hover{color:#fff;background:rgba(34,197,94,.15);border-color:rgba(34,197,94,.42);transform:translateY(-1px)}
  .module-icon{width:40px;height:40px;display:grid;place-items:center;flex:0 0 40px;border-radius:12px;background:rgba(34,197,94,.16);border:1px solid rgba(34,197,94,.28);color:#86efac;font-size:1.18rem}
  .module-title{font-weight:900;line-height:1.15}.module-desc{margin-top:3px;color:#b7c3d6;font-size:.82rem;line-height:1.35}
  .content-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
  .feature-card{min-height:190px;display:flex;flex-direction:column;justify-content:space-between;gap:16px;padding:18px;border-radius:16px;background:rgba(2,6,23,.70);border:1px solid rgba(148,163,184,.32);box-shadow:0 12px 28px rgba(0,0,0,.35)}
  .feature-top{display:flex;gap:12px;align-items:flex-start}.feature-icon{width:52px;height:52px;display:grid;place-items:center;flex:0 0 52px;border-radius:16px;background:rgba(34,197,94,.16);border:1px solid rgba(34,197,94,.34);color:#86efac;font-size:1.45rem}
  .feature-title{font-size:1.05rem;font-weight:900;margin-bottom:5px}.feature-desc{color:#cbd5f5;font-size:.9rem;line-height:1.45}
  .gest-btn{display:inline-flex;align-items:center;justify-content:center;gap:.45rem;width:max-content;border:0;border-radius:10px;padding:.58rem .95rem;background:#16a34a;color:#fff;font-weight:900;text-decoration:none}
  .gest-btn:hover{background:#22c55e;color:#052e16}.status-strip{margin-top:14px;padding:12px 14px;border-radius:14px;background:rgba(15,23,42,.82);border:1px solid rgba(148,163,184,.28);color:#cbd5f5;font-size:.88rem}
  @media (max-width:900px){.layout{grid-template-columns:1fr}.content-grid{grid-template-columns:1fr}.hero-inner{align-items:flex-start}.header-actions{margin-left:0}}
</style>
</head>
<body>
<div class="page-bg"></div>
<header class="brand-hero">
  <div class="hero-inner container-main" style="padding-top:0;padding-bottom:0;">
    <img class="brand-logo" src="<?= e($ESCUDO) ?>" alt="EC MIL M" onerror="this.onerror=null;this.src='<?= e($ASSET_WEB) ?>/img/EA.png';">
    <div>
      <div class="brand-title">Escuela Militar de Montana</div>
      <div class="brand-sub">Division Ensenanza</div>
    </div>
    <div class="header-actions">
      <a class="btn btn-outline-light btn-sm fw-bold" href="<?= e($BASE_PUBLIC_WEB) ?>/inicio.php">Inicio</a>
      <a class="btn btn-success btn-sm fw-bold" href="<?= e($BASE_APP_WEB) ?>/logout.php">Cerrar sesion</a>
    </div>
  </div>
</header>
<main class="container-main">
  <section class="panel">
    <div class="panel-title"><i class="bi bi-mortarboard-fill"></i> Division Ensenanza <span class="badge text-bg-success">ENS</span></div>
    <div class="panel-sub">Selecciona el modulo correspondiente para trabajar con documentacion compartida o administrar las clases de la Division Ensenanza.</div>
    <div class="layout">
      <aside class="sidebar-box">
        <div class="sidebar-title"><i class="bi bi-grid-3x3-gap"></i> Modulos ENS</div>
        <div class="module-list">
          <?php foreach ($modules as $m): ?>
            <a class="module-link" href="<?= e($m['url']) ?>">
              <span class="module-icon"><i class="bi <?= e($m['icon']) ?>"></i></span>
              <span><span class="module-title"><?= e($m['title']) ?></span><span class="module-desc d-block"><?= e($m['desc']) ?></span></span>
            </a>
          <?php endforeach; ?>
        </div>
      </aside>
      <section>
        <div class="content-grid">
          <?php foreach ($modules as $m): ?>
            <article class="feature-card">
              <div class="feature-top">
                <div class="feature-icon"><i class="bi <?= e($m['icon']) ?>"></i></div>
                <div><div class="feature-title"><?= e($m['title']) ?></div><div class="feature-desc"><?= e($m['desc']) ?></div></div>
              </div>
              <a class="gest-btn" href="<?= e($m['url']) ?>"><i class="bi bi-box-arrow-in-right"></i> Entrar</a>
            </article>
          <?php endforeach; ?>
        </div>
        <div class="status-strip"><strong>Usuario:</strong> <?= e($fullName !== '' ? $fullName : 'Sesion activa') ?></div>
      </section>
    </div>
  </section>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
