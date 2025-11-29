<?php
// public/s3_educacion_alocuciones.php — S-3 Educación · Alocuciones
declare(strict_types=1);

$OFFLINE_MODE = false;

require_once __DIR__ . '/../auth/bootstrap.php';
if (!$OFFLINE_MODE) {
    require_login();
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/s3_educacion_tables_helper.php';

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* ===== Usuario ===== */
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

/* ===== Crear tablas si faltan ===== */
s3_ensure_tables($pdo);

/* ===== Obtener alocuciones ===== */
$alocuciones = $pdo->query("SELECT * FROM s3_alocuciones ORDER BY nro, id")->fetchAll(PDO::FETCH_ASSOC);

/* ===== KPI ===== */
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

[$totalAloc,$alocCumplidas,$alocPend,$porcAloc] = kpi_from_table($pdo,'s3_alocuciones');

$savedFlag = ($_GET['saved'] ?? '') === '1';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Alocuciones · S-3 · B Com 602</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/theme-602.css">
<link rel="icon" type="image/png" href="../assets/img/bcom602.png">
<style>
  body{
    background: url("<?= e($IMG_BG) ?>") no-repeat center center fixed;
    background-size: cover;
    background-color:#020617;
    color:#e5e7eb;
    font-family:system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,sans-serif;
  }
  .page-wrap{ padding:18px; }
  .container-main{ max-width:1400px; margin:auto; }

  .panel{
    background:rgba(15,17,23,.94);
    border:1px solid rgba(148,163,184,.40);
    border-radius:18px;
    padding:18px 22px 22px;
    backdrop-filter:blur(8px);
    margin-bottom:18px;
  }

  .panel-title{ font-size:1.1rem; font-weight:800; margin-bottom:6px; }
  .panel-sub{ font-size:.86rem; color:#cbd5f5; margin-bottom:14px; }

  .brand-hero{
    padding-top:12px;
    padding-bottom:12px;
  }
  .brand-hero .hero-inner{
    display:flex; justify-content:space-between; align-items:center;
  }
  .header-back{ display:flex; gap:8px; flex-wrap:wrap; }

  .kpi-card{
    flex:1 1 260px;
    background:radial-gradient(circle at top left, rgba(30,144,255,.18), transparent 65%);
    border-radius:14px;
    border:1px solid rgba(148,163,184,.45);
    padding:10px 14px;
  }
  .kpi-title{
    text-transform:uppercase; font-size:.78rem;
    font-weight:700; color:#9ca3af; margin-bottom:4px;
  }
  .kpi-main{ font-size:1.2rem; font-weight:800; }
  .kpi-sub{ font-size:.8rem; color:#cbd5f5; }

  .table-sm td, .table-sm th{ font-size:.82rem; padding:.35rem .45rem; }

  .doc-link{
    font-size:.8rem; max-width:180px;
    display:inline-block; white-space:nowrap;
    overflow:hidden; text-overflow:ellipsis;
  }
</style>
</head>
<body>

<!-- Toast éxito -->
<div class="position-fixed top-0 start-50 translate-middle-x p-3" style="z-index:11000;">
  <div id="saveToast" class="toast text-bg-success shadow border-0" data-bs-delay="2500">
    <div class="d-flex">
      <div class="toast-body">✅ Cambios guardados correctamente.</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>

<header class="brand-hero">
  <div class="hero-inner container-main">
    <div class="d-flex align-items-center gap-3">
      <img src="<?= e($ESCUDO) ?>" class="brand-logo" style="height:52px;">
      <div>
        <div class="brand-title fw-bold">Batallón de Comunicaciones 602</div>
        <div class="brand-sub">“Hogar de las Comunicaciones Fijas del Ejército”</div>
      </div>
    </div>

    <div class="header-back">
      <a href="s3_educacion_cuadros.php" class="btn btn-outline-light btn-sm fw-bold">⬅ Educación de cuadros</a>
      <a href="areas_s3.php" class="btn btn-outline-light btn-sm fw-bold">Volver a S-3</a>
      <a href="areas.php" class="btn btn-secondary btn-sm fw-bold">Volver a Áreas</a>
    </div>
  </div>
</header>

<div class="page-wrap">
  <div class="container-main">

    <!-- Top bar -->
    <div class="d-flex justify-content-between flex-wrap mb-3">
      <span class="small text-muted">
        Editor: <strong><?= e(user_display_name()) ?></strong>
      </span>
      <button form="s3AlocForm" class="btn btn-success btn-sm fw-bold">💾 Guardar cambios</button>
    </div>

    <!-- KPIs -->
    <div class="panel">
      <div class="panel-title">Alocuciones · Resumen</div>
      <div class="panel-sub">
        Estado general de cumplimiento de las alocuciones registradas.
      </div>

      <div class="kpi-card">
        <div class="kpi-title">Alocuciones</div>
        <div class="kpi-main"><?= e($alocCumplidas) ?>/<?= e($totalAloc) ?> cumplidas</div>
        <div class="kpi-sub">
          Cumplimiento: <?= e($porcAloc) ?>% · Pendientes: <?= e($alocPend) ?>
        </div>
        <div class="progress mt-2" style="height:6px;">
          <div class="progress-bar bg-success" style="width:<?= e($porcAloc) ?>%;"></div>
        </div>
      </div>
    </div>

    <!-- Tabla principal -->
    <form id="s3AlocForm" action="save_s3_educacion.php" method="post" enctype="multipart/form-data">
      <?php if (function_exists('csrf_input')) echo csrf_input(); ?>
      <input type="hidden" name="section" value="alocuciones">

      <div class="panel">
        <div class="panel-title">Alocuciones</div>
        <div class="panel-sub">
          Registro de alocuciones y evidencias de cumplimiento.
        </div>

        <div class="table-responsive">
          <table class="table table-sm table-dark table-bordered align-middle">
            <thead>
              <tr>
                <th>Nro</th>
                <th>Fecha</th>
                <th>Acontecimiento</th>
                <th>Responsable</th>
                <th>Se cumplió</th>
                <th style="width:260px;">Documento / evidencia</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($alocuciones)): ?>
                <tr><td colspan="6" class="text-center text-muted">Sin registros.</td></tr>
              <?php else: ?>
                <?php foreach ($alocuciones as $a): ?>
                <tr>
                  <td><?= e($a['nro']) ?></td>
                  <td><?= e($a['fecha']) ?></td>
                  <td><?= e($a['acontecimiento']) ?></td>
                  <td><?= e($a['responsable']) ?></td>
                  <td>
                    <select name="aloc_cumplio[<?= (int)$a['id'] ?>]" class="form-select form-select-sm">
                      <?php
                        $v = (string)$a['cumplio'];
                        $opts = [
                          '' => '—',
                          'si' => 'Sí',
                          'no' => 'No',
                          'en_ejecucion' => 'En ejecución',
                        ];
                        foreach ($opts as $val=>$lab):
                      ?>
                        <option value="<?= e($val) ?>" <?= $v===$val ? 'selected':'' ?>><?= e($lab) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                  <td>
                    <div class="mb-1">
                      <input type="text"
                             name="aloc_doc[<?= (int)$a['id'] ?>]"
                             class="form-control form-control-sm"
                             placeholder="Nombre o ruta"
                             value="<?= e($a['documento']) ?>">
                    </div>

                    <div class="d-flex align-items-center gap-2">
                      <input type="file"
                             name="aloc_doc_file[<?= (int)$a['id'] ?>]"
                             class="form-control form-control-sm"
                             accept=".pdf,image/*">
                      <?php if (!empty($a['documento'])): ?>
                        <a href="<?= e($a['documento']) ?>" target="_blank"
                           class="btn btn-outline-info btn-sm">📎 Ver</a>
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

      <div class="text-end mb-3">
        <button class="btn btn-success btn-sm fw-bold">💾 Guardar cambios</button>
      </div>
    </form>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
  const wasSaved = <?= $savedFlag ? 'true' : 'false' ?>;
  if (wasSaved && window.bootstrap){
    const t = new bootstrap.Toast(document.getElementById('saveToast'));
    t.show();
    const url = new URL(window.location.href);
    url.searchParams.delete('saved');
    window.history.replaceState({}, '', url);
  }
})();
</script>
</body>
</html>
