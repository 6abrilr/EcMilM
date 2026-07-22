<?php
// public/departamento_educacion/departamento_educacion.php - Departamento Educacion
declare(strict_types=1);

require_once __DIR__ . '/../../auth/bootstrap.php';
require_login();
require_once __DIR__ . '/../../config/db.php';

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function table_count(PDO $pdo, string $table): ?int {
  try {
    $st = $pdo->prepare("
      SELECT 1
      FROM information_schema.tables
      WHERE table_schema = DATABASE() AND table_name = :t
      LIMIT 1
    ");
    $st->execute([':t' => $table]);
    if (!$st->fetchColumn()) return null;
    $q = $pdo->query("SELECT COUNT(*) FROM `$table`");
    return (int)($q->fetchColumn() ?: 0);
  } catch (Throwable $e) {
    return null;
  }
}

$SELF_WEB        = (string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
$BASE_DIR_WEB    = rtrim(str_replace('\\', '/', dirname($SELF_WEB)), '/');
$BASE_PUBLIC_WEB = rtrim(str_replace('\\', '/', dirname($BASE_DIR_WEB)), '/');
$BASE_APP_WEB    = rtrim(str_replace('\\', '/', dirname($BASE_PUBLIC_WEB)), '/');
$ASSET_WEB       = $BASE_APP_WEB . '/assets';

$IMG_BG  = $ASSET_WEB . '/img/fondo.png';
$ESCUDO  = $ASSET_WEB . '/img/ecmilm.png';
$FAVICON = $ESCUDO;

$modules = [
  [
    'id' => 'cuadros',
    'icon' => 'bi-mortarboard',
    'title' => 'Educacion de Cuadros',
    'desc' => 'Seguimiento de PEU, ordenes de instruccion, ejercicios, MAPE y actividades de cuadros.',
    'url' => '../operaciones/operaciones_educacion_cuadros.php',
    'enabled' => true,
  ],
  [
    'id' => 'tropa',
    'icon' => 'bi-people',
    'title' => 'Educacion de Tropa',
    'desc' => 'Planes de educacion de fracciones, partes y control de instruccion de tropa.',
    'url' => '../operaciones/operaciones_educacion_tropa.php',
    'enabled' => true,
  ],
  [
    'id' => 'cursos',
    'icon' => 'bi-journal-bookmark',
    'title' => 'Cursos',
    'desc' => 'Gestion y consulta de cursos vinculados al area de educacion.',
    'url' => '../operaciones/operaciones_educacion_cursos.php',
    'enabled' => true,
  ],
  [
    'id' => 'complementarios',
    'icon' => 'bi-patch-plus',
    'title' => 'Cursos complementarios',
    'desc' => 'Registro de cursos complementarios y documentacion asociada.',
    'url' => '../operaciones/operaciones_educacion_cursos_complementarios.php',
    'enabled' => true,
  ],
  [
    'id' => 'clases',
    'icon' => 'bi-easel2',
    'title' => 'Clases',
    'desc' => 'Planificacion, carga y control de clases.',
    'url' => '../operaciones/operaciones_educacion_clases.php',
    'enabled' => true,
  ],
  [
    'id' => 'alocuciones',
    'icon' => 'bi-megaphone',
    'title' => 'Alocuciones',
    'desc' => 'Registro y administracion de alocuciones.',
    'url' => '../operaciones/operaciones_educacion_alocuciones.php',
    'enabled' => true,
  ],
  [
    'id' => 'trabajos',
    'icon' => 'bi-file-earmark-text',
    'title' => 'Trabajos',
    'desc' => 'Seguimiento de trabajos, entregas y documentacion.',
    'url' => '../operaciones/operaciones_educacion_trabajos.php',
    'enabled' => true,
  ],
];

$kpis = [
  ['icon' => 'bi-mortarboard', 'title' => 'Cuadros', 'value' => table_count($pdo, 'operaciones_educacion_cuadros')],
  ['icon' => 'bi-people', 'title' => 'Tropa', 'value' => table_count($pdo, 'operaciones_educacion_tropa')],
  ['icon' => 'bi-journal-bookmark', 'title' => 'Cursos', 'value' => table_count($pdo, 'operaciones_educacion_cursos')],
  ['icon' => 'bi-easel2', 'title' => 'Clases', 'value' => table_count($pdo, 'operaciones_educacion_clases')],
];
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Departamento Educacion</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= e($ASSET_WEB) ?>/css/theme-602.css">
<link rel="icon" href="<?= e($FAVICON) ?>">
<style>
  html,body{ height:100%; }
  body{ margin:0; color:#e5e7eb; background:#000; font-family:system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,sans-serif; }
  .page-bg{ position:fixed; inset:0; z-index:-2; pointer-events:none; background:linear-gradient(160deg, rgba(0,0,0,.85) 0%, rgba(0,0,0,.65) 55%, rgba(0,0,0,.85) 100%), url("<?= e($IMG_BG) ?>") center/cover no-repeat fixed; filter:saturate(1.05); }
  .page-bg::before{ content:""; position:absolute; inset:0; z-index:-1; opacity:.18; background-image:radial-gradient(1.4px 1.4px at 18% 22%, #9cd1ff 20%, transparent 60%),radial-gradient(1.2px 1.2px at 63% 48%, #b7ddff 20%, transparent 60%),radial-gradient(1.2px 1.2px at 82% 70%, #b7ddff 20%, transparent 60%); background-repeat:no-repeat; }
  .page-wrap{ padding:18px; position:relative; z-index:2; }
  .container-main{ max-width:1400px; margin:auto; }
  .panel{ background:rgba(15,17,23,.94); border:1px solid rgba(148,163,184,.40); border-radius:18px; padding:18px 22px 22px; box-shadow:0 18px 40px rgba(0,0,0,.75), inset 0 1px 0 rgba(255,255,255,.05); backdrop-filter:blur(8px); }
  .panel-title{ font-size:1.05rem; font-weight:900; margin-bottom:6px; display:flex; align-items:center; gap:.55rem; }
  .panel-sub{ font-size:.86rem; color:#cbd5f5; margin-bottom:18px; }
  .brand-hero{ padding:10px 0; position:relative; z-index:3; }
  .brand-hero .hero-inner{ align-items:center; display:flex; justify-content:space-between; gap:12px; }
  .brand-logo{ width:58px; height:58px; object-fit:contain; filter:drop-shadow(0 10px 18px rgba(0,0,0,.55)); }
  .brand-title{ font-weight:900; font-size:1.15rem; line-height:1.1; color:#e5e7eb; }
  .brand-sub{ font-size:.9rem; color:#cbd5f5; opacity:.9; margin-top:2px; }
  .header-back{ margin-left:auto; margin-right:17px; margin-top:4px; display:flex; gap:8px; }
  .layout-row{ display:flex; flex-wrap:wrap; gap:18px; }
  .layout-sidebar{ flex:0 0 300px; max-width:380px; }
  .layout-main{ flex:1 1 0; min-width:0; }
  @media (max-width:768px){ .layout-sidebar,.layout-main{ flex:1 1 100%; max-width:100%; } }
  .sidebar-box{ background:rgba(15,23,42,.95); border-radius:16px; border:1px solid rgba(148,163,184,.45); padding:14px 14px 10px; box-shadow:0 10px 28px rgba(0,0,0,.75); }
  .sidebar-title{ font-size:.88rem; font-weight:800; letter-spacing:.05em; text-transform:uppercase; color:#9ca3af; margin-bottom:10px; display:flex; align-items:center; gap:.5rem; }
  .accordion-area .accordion-item{ background:transparent; border:none; border-radius:12px; margin-bottom:6px; overflow:hidden; }
  .accordion-area .accordion-button{ background:radial-gradient(circle at left, rgba(34,197,94,.35), transparent 60%); border:none; color:#e5e7eb; font-size:.86rem; font-weight:900; padding:.55rem .75rem; box-shadow:0 6px 14px rgba(0,0,0,.65); }
  .accordion-area .accordion-button:not(.collapsed){ background:radial-gradient(circle at left, rgba(34,197,94,.52), transparent 70%); color:#ecfdf5; }
  .accordion-area .accordion-button::after{ filter:invert(1) brightness(1.5); }
  .accordion-area .accordion-body{ background:rgba(15,23,42,.96); font-size:.84rem; color:#cbd5f5; border-top:1px solid rgba(148,163,184,.35); }
  .gest-btn{ display:inline-flex; align-items:center; justify-content:center; gap:.45rem; padding:.45rem 1.1rem; border-radius:999px; border:none; font-size:.82rem; font-weight:900; text-decoration:none; background:#22c55e; color:#052e16; box-shadow:0 8px 22px rgba(22,163,74,.7); }
  .gest-btn:hover{ background:#4ade80; color:#052e16; }
  .main-text{ background:rgba(15,23,42,.86); border:1px solid rgba(148,163,184,.35); border-radius:16px; padding:14px 16px; color:#dbeafe; line-height:1.55; }
  .kpi-grid{ display:flex; flex-wrap:wrap; gap:10px; margin-top:14px; }
  .kpi-card{ flex:1 1 180px; min-width:170px; background:rgba(15,23,42,.96); border-radius:14px; border:1px solid rgba(148,163,184,.45); padding:10px 12px; font-size:.78rem; }
  .kpi-title{ text-transform:uppercase; letter-spacing:.06em; color:#9ca3af; font-weight:800; margin-bottom:4px; }
  .kpi-main{ font-size:1.05rem; font-weight:900; display:flex; align-items:center; gap:.45rem; }
  .doctrina{ margin-top:14px; background:rgba(5,46,22,.55); border:1px solid rgba(34,197,94,.35); border-radius:16px; padding:14px 16px; color:#dcfce7; }
  .doctrina h6{ font-weight:900; margin-bottom:8px; }
  .doctrina li{ margin:6px 0; }
</style>
</head>
<body>
<div class="page-bg"></div>

<header class="brand-hero">
  <div class="hero-inner container-main">
    <div class="d-flex align-items-center gap-3">
      <img class="brand-logo" src="<?= e($ESCUDO) ?>" alt="EC MIL M" onerror='this.onerror=null; this.src="<?= e($ASSET_WEB) ?>/img/EA.png";'>
      <div>
        <div class="brand-title">Escuela Militar de Monta&ntilde;a</div>
        <div class="brand-sub">&ldquo;La monta&ntilde;a nos une&rdquo;</div>
      </div>
    </div>
    <div class="header-back">
      <a href="../inicio.php" class="btn btn-success btn-sm" style="font-weight:700; padding:.35rem .9rem;"><i class="bi bi-house-door"></i> Inicio</a>
    </div>
  </div>
</header>

<div class="page-wrap">
  <div class="container-main">
    <div class="panel">
      <div class="panel-title">
        <i class="bi bi-mortarboard-fill"></i>
        Departamento Educaci&oacute;n
        <span class="badge text-bg-success">EDUC</span>
      </div>
      <div class="panel-sub">
        Seleccion&aacute; el m&oacute;dulo correspondiente. Este panel concentra accesos y res&uacute;menes del Departamento Educaci&oacute;n.
      </div>

      <div class="layout-row">
        <aside class="layout-sidebar">
          <div class="sidebar-box">
            <div class="sidebar-title"><i class="bi bi-grid-3x3-gap"></i> M&oacute;dulos</div>
            <div class="accordion accordion-area" id="accordionEducacion">
              <?php $first = true; foreach ($modules as $m): ?>
                <div class="accordion-item">
                  <h2 class="accordion-header" id="edu-h-<?= e($m['id']) ?>">
                    <button class="accordion-button <?= $first ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#edu-<?= e($m['id']) ?>" aria-expanded="<?= $first ? 'true' : 'false' ?>" aria-controls="edu-<?= e($m['id']) ?>">
                      <i class="bi <?= e($m['icon']) ?> me-1"></i> <?= e($m['title']) ?>
                    </button>
                  </h2>
                  <div id="edu-<?= e($m['id']) ?>" class="accordion-collapse collapse <?= $first ? 'show' : '' ?>" aria-labelledby="edu-h-<?= e($m['id']) ?>" data-bs-parent="#accordionEducacion">
                    <div class="accordion-body">
                      <?= e($m['desc']) ?>
                      <div class="mt-2"><a href="<?= e($m['url']) ?>" class="gest-btn"><i class="bi bi-box-arrow-in-right"></i> Entrar</a></div>
                    </div>
                  </div>
                </div>
              <?php $first = false; endforeach; ?>
            </div>
          </div>
        </aside>

        <section class="layout-main">
          <div class="main-text">
            <p>Este tablero re&uacute;ne la gesti&oacute;n educativa de cuadros, tropa, cursos, clases, alocuciones y trabajos.</p>
            <p class="mb-0">Los accesos conectan con los m&oacute;dulos existentes de S-3 Educaci&oacute;n para mantener una sola fuente operativa.</p>
          </div>

          <div class="kpi-grid">
            <?php foreach ($kpis as $kpi): ?>
              <div class="kpi-card">
                <div class="kpi-title"><?= e($kpi['title']) ?></div>
                <div class="kpi-main"><i class="bi <?= e($kpi['icon']) ?>"></i> <?= e($kpi['value'] ?? '-') ?></div>
                <div class="text-muted">Registros cargados en el sistema.</div>
              </div>
            <?php endforeach; ?>
          </div>

          <div class="doctrina">
            <h6><i class="bi bi-compass"></i> Enfoque del departamento</h6>
            <ul class="mb-0">
              <li>Centralizar la planificaci&oacute;n, ejecuci&oacute;n y control de la educaci&oacute;n de la unidad.</li>
              <li>Dar seguimiento a cursos, clases, trabajos y documentaci&oacute;n educativa.</li>
              <li>Consolidar informaci&oacute;n para inspecciones, informes y toma de decisiones.</li>
            </ul>
          </div>
        </section>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
