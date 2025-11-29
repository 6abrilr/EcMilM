<?php
// public/s3_educacion_trabajos.php — S-3 Educación · Trabajos de gabinete
declare(strict_types=1);

$OFFLINE_MODE = false;

require_once __DIR__ . '/../auth/bootstrap.php';
if (!$OFFLINE_MODE) {
    require_login();
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/s3_educacion_tables_helper.php';

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$user = function_exists('current_user') ? current_user() : null;
if (!function_exists('user_display_name')) {
    function user_display_name(): string {
        $u = $_SESSION['user'] ?? [];
        if (isset($u['grado'], $u['arma'], $u['nombre_completo'])) {
            return trim($u['grado'].' '.$u['arma'].' '.$u['nombre_completo']);
        }
        if (isset($u['display_name']))    return trim((string)$u['display_name']);
        if (isset($u['nombre_completo'])) return trim((string)$u['nombre_completo']);
        if (isset($u['username']))        return strtoupper((string)$u['username']);
        return 'USUARIO';
    }
}

/* ===== Assets ===== */
$PUBLIC_URL = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$APP_URL    = rtrim(str_replace('\\','/', dirname($PUBLIC_URL)), '/');
$ASSETS_URL = ($APP_URL === '' ? '' : $APP_URL) . '/assets';
$IMG_BG     = $ASSETS_URL . '/img/fondo.png';
$ESCUDO     = $ASSETS_URL . '/img/escudo_bcom602.png';

/* ===== Tablas S-3 aseguradas ===== */
s3_ensure_tables($pdo);

/* ===== Datos ===== */
$trabajosGabinete = $pdo->query("SELECT * FROM s3_trabajos_gabinete ORDER BY semana, id")->fetchAll(PDO::FETCH_ASSOC);

/* ===== KPI propio ===== */
function kpi_from_table(PDO $pdo, string $table): array {
    $row = $pdo->query("
        SELECT
          COUNT(*) AS total,
          SUM(cumplio = 'si') AS ok
        FROM {$table}
    ")->fetch(PDO::FETCH_ASSOC) ?: ['total'=>0,'ok'=>0];
    $total = (int)$row['total'];
    $ok    = (int)$row['ok'];
    $pend  = max($total - $ok, 0);
    $pct   = $total > 0 ? round($ok * 100.0 / $total, 1) : 0.0;
    return [$total,$ok,$pend,$pct];
}
[$totalTrabajos,$trabajosCumplidos,$trabajosPendientes,$porcTrabajos] = kpi_from_table($pdo,'s3_trabajos_gabinete');

$savedFlag = ($_GET['saved'] ?? '') === '1';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Trabajos de gabinete · S-3 · B Com 602</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/theme-602.css">
<link rel="icon" type="image/png" href="../assets/img/bcom602.png">
<style>
  body{
    background: url("<?= e($IMG_BG) ?>") no-repeat center center fixed;
    background-size: cover;
    background-attachment: fixed;
    background-color:#020617;
    color:#e5e7eb;
    font-family:system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,sans-serif;
    margin:0; padding:0;
  }
  .page-wrap{ padding:18px; }
  .container-main{ max-width:1400px; margin:auto; }

  .panel{
    background:rgba(15,17,23,.94);
    border:1px solid rgba(148,163,184,.40);
    border-radius:18px;
    padding:18px 22px 22px;
    box-shadow:0 18px 40px rgba(0,0,0,.75), inset 0 1px 0 rgba(255,255,255,.05);
    backdrop-filter:blur(8px);
    margin-bottom:16px;
  }

  .panel-title{
    font-size:1.05rem;
    font-weight:800;
    margin-bottom:4px;
  }

  .panel-sub{
    font-size:.86rem;
    color:#cbd5f5;
    margin-bottom:12px;
  }

  .brand-hero{
    padding-top:10px;
    padding-bottom:10px;
  }
  .brand-hero .hero-inner{
    align-items:center;
    display:flex;
    justify-content:space-between;
    gap:12px;
  }
  .header-back{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
  }

  .brand-title{
    font-weight:800;
    font-size:1rem;
  }
  .brand-sub{
    font-size:.8rem;
    color:#9ca3af;
  }

  .table-sm th,
  .table-sm td{
    padding:.3rem .4rem;
    font-size:.8rem;
  }

  .badge-si{ background:#16a34a; }
  .badge-no{ background:#dc2626; }
  .badge-ejec{ background:#0ea5e9; }
  .badge-pend{ background:#6b7280; }

  .top-actions{
    margin-bottom:10px;
    display:flex;
    justify-content:space-between;
    gap:10px;
    flex-wrap:wrap;
  }

  .kpi-card{
    background:radial-gradient(circle at top left, rgba(34,197,94,.18), transparent 65%);
    border-radius:14px;
    border:1px solid rgba(148,163,184,.45);
    padding:10px 14px;
    font-size:.85rem;
  }
  .kpi-title{
    font-weight:700;
    font-size:.8rem;
    text-transform:uppercase;
    letter-spacing:.08em;
    color:#9ca3af;
    margin-bottom:4px;
  }
  .kpi-main{
    font-size:1.2rem;
    font-weight:800;
  }
  .kpi-sub{
    font-size:.8rem;
    color:#cbd5f5;
  }

  .doc-link{
    font-size:.8rem;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
    max-width:180px;
    display:inline-block;
  }
</style>
</head>
<body>

<!-- Toast de confirmación -->
<div class="position-fixed top-0 start-50 translate-middle-x p-3" style="z-index: 11000;">
  <div id="saveToast" class="toast align-items-center text-bg-success border-0 shadow"
       role="alert" aria-live="assertive" aria-atomic="true"
       data-bs-delay="2500">
    <div class="d-flex">
      <div class="toast-body">
        ✅ Cambios guardados correctamente.
      </div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto"
              data-bs-dismiss="toast" aria-label="Cerrar"></button>
    </div>
  </div>
</div>

<header class="brand-hero">
  <div class="hero-inner container-main">
    <div class="d-flex align-items-center gap-3">
      <img class="brand-logo" src="<?= e($ESCUDO) ?>" alt="Escudo 602" style="height:52px; width:auto;">
      <div>
        <div class="brand-title">Batallón de Comunicaciones 602</div>
        <div class="brand-sub">“Hogar de las Comunicaciones Fijas del Ejército”</div>
      </div>
    </div>
    <div class="header-back">
      <a href="s3_educacion_cuadros.php" class="btn btn-outline-light btn-sm" style="font-weight:700; padding:.35rem .9rem;">
        ⬅ Educación de cuadros
      </a>
      <a href="areas_s3.php" class="btn btn-outline-light btn-sm" style="font-weight:700; padding:.35rem .9rem;">
        Volver a S-3
      </a>
      <a href="areas.php" class="btn btn-secondary btn-sm" style="font-weight:700; padding:.35rem .9rem;">
        Volver a Áreas
      </a>
    </div>
  </div>
</header>

<div class="page-wrap">
  <div class="container-main">

    <div class="top-actions">
      <div class="text-muted small">
        Editor: <strong><?= e(user_display_name()) ?></strong>
      </div>
      <div>
        <button form="s3TrabajosForm" class="btn btn-success btn-sm" style="font-weight:700;">
          💾 Guardar cambios
        </button>
      </div>
    </div>

    <form id="s3TrabajosForm" action="save_s3_educacion.php" method="post" enctype="multipart/form-data">
      <?php if (function_exists('csrf_input')) { echo csrf_input(); } ?>
      <input type="hidden" name="section" value="trabajos">

      <!-- Resumen de avance trabajos de gabinete -->
      <div class="panel mb-3">
        <div class="panel-title">Trabajos de gabinete · Resumen</div>
        <div class="panel-sub">
          Cumplimiento general de los trabajos de gabinete asignados a cuadros.
        </div>
        <div class="kpi-card">
          <div class="kpi-title">Trabajos de gabinete</div>
          <div class="kpi-main">
            <?= e($trabajosCumplidos) ?>/<?= e($totalTrabajos) ?> trabajos cumplidos
          </div>
          <div class="kpi-sub">
            Cumplimiento: <?= e($porcTrabajos) ?>% · Pendientes: <?= e($trabajosPendientes) ?>
          </div>
          <div class="progress mt-2" style="height:6px;">
            <div class="progress-bar bg-success" role="progressbar" style="width: <?= e($porcTrabajos) ?>%;"></div>
          </div>
        </div>
      </div>

      <!-- Tabla de trabajos -->
      <div class="panel">
        <div class="panel-title">Trabajos de gabinete</div>
        <div class="panel-sub">
          Listado de trabajos de gabinete asignados, con seguimiento de cumplimiento y documentación de respaldo.
        </div>

        <div class="table-responsive">
          <table class="table table-sm table-dark align-middle">
            <thead>
              <tr>
                <th>Sem</th>
                <th>Tema</th>
                <th>Grado</th>
                <th>Apellido y nombre</th>
                <th>Se cumplió</th>
                <th style="width:260px;">Evidencia / documento</th>
              </tr>
            </thead>
            <tbody>
            <?php if (empty($trabajosGabinete)): ?>
              <tr><td colspan="6" class="text-center text-muted">Sin registros.</td></tr>
            <?php else: ?>
              <?php foreach ($trabajosGabinete as $t): ?>
              <tr>
                <td><?= e($t['semana']) ?></td>
                <td><?= e($t['tema']) ?></td>
                <td><?= e($t['responsable_grado']) ?></td>
                <td><?= e($t['responsable_nombre']) ?></td>
                <td>
                  <select name="tg_cumplio[<?= (int)$t['id'] ?>]" class="form-select form-select-sm">
                    <?php
                      $v = (string)$t['cumplio'];
                      $opts = [
                        '' => '—',
                        'si' => 'Sí',
                        'no' => 'No',
                        'en_ejecucion' => 'En ejecución',
                      ];
                      foreach ($opts as $val=>$lab):
                    ?>
                      <option value="<?= e($val) ?>" <?= $v === $val ? 'selected' : '' ?>><?= e($lab) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td>
                  <!-- Campo de texto para nombre/ruta (compatible con lo que ya tenías) -->
                  <div class="mb-1">
                    <input type="text"
                           name="tg_doc[<?= (int)$t['id'] ?>]"
                           class="form-control form-control-sm"
                           value="<?= e($t['documento']) ?>"
                           placeholder="Nombre o ruta de documento">
                  </div>

                  <!-- Input de archivo para subir evidencia (PDF / imagen) -->
                  <div class="d-flex align-items-center gap-2">
                    <input type="file"
                           name="tg_doc_file[<?= (int)$t['id'] ?>]"
                           class="form-control form-control-sm"
                           accept=".pdf,image/*">
                    <?php if (!empty($t['documento'])): ?>
                      <a href="<?= e($t['documento']) ?>" target="_blank"
                         class="btn btn-outline-info btn-sm"
                         title="Abrir documento">
                        📎 Ver
                      </a>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="text-end mb-2">
        <button class="btn btn-success btn-sm" style="font-weight:700;">
          💾 Guardar cambios
        </button>
      </div>
    </form>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
  const wasSaved = <?= $savedFlag ? 'true' : 'false' ?>;
  if (wasSaved && window.bootstrap) {
    const toastEl = document.getElementById('saveToast');
    if (toastEl) {
      const toast = new bootstrap.Toast(toastEl);
      toast.show();
      try {
        const url = new URL(window.location.href);
        url.searchParams.delete('saved');
        window.history.replaceState({}, '', url);
      } catch (e) {}
    }
  }
})();
</script>
</body>
</html>
