<?php
// public/s3_educacion_clases.php — Clases (Programa anual) · S-3
declare(strict_types=1);

// ========= MODO PRODUCCIÓN / EJÉRCITO =========
$OFFLINE_MODE = false;
// ===================================

require_once __DIR__ . '/../auth/bootstrap.php';
if (!$OFFLINE_MODE) {
    require_login();
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/s3_educacion_tables_helper.php';

s3_ensure_tables($pdo);

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

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
$PUBLIC_URL = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'])), '/');
$APP_URL    = rtrim(str_replace('\\','/', dirname($PUBLIC_URL)), '/');
$ASSETS_URL = ($APP_URL === '' ? '' : $APP_URL) . '/assets';
$IMG_BG     = $ASSETS_URL . '/img/fondo.png';
$ESCUDO     = $ASSETS_URL . '/img/escudo_bcom602.png';

/* ===== Datos de clases ===== */
$clases = $pdo->query("SELECT * FROM s3_clases ORDER BY semana, id")->fetchAll(PDO::FETCH_ASSOC);

/* ===== KPI simple de clases ===== */
$row = $pdo->query("
    SELECT
      COUNT(*) AS total,
      SUM(cumplio = 'si') AS ok
    FROM s3_clases
")->fetch(PDO::FETCH_ASSOC) ?: ['total'=>0,'ok'=>0];

$totalClases      = (int)$row['total'];
$clasesCumplidas  = (int)$row['ok'];
$clasesPendientes = max($totalClases - $clasesCumplidas, 0);
$porcClases       = $totalClases > 0 ? round($clasesCumplidas * 100.0 / $totalClases, 1) : 0.0;

$savedFlag = ($_GET['saved'] ?? '') === '1';

/* ===== Listado de personal (para Responsable) ===== */
$personal = $pdo->query("
    SELECT grado, arma, nombre_apellido
    FROM personal_unidad
    WHERE nombre_apellido IS NOT NULL AND nombre_apellido <> ''
    ORDER BY
      CASE grado
        -- Oficiales
        WHEN 'TG' THEN 1
        WHEN 'GD' THEN 2
        WHEN 'GB' THEN 3
        WHEN 'CY' THEN 4
        WHEN 'CR' THEN 5
        WHEN 'TC' THEN 6
        WHEN 'MY' THEN 7
        WHEN 'CT' THEN 8
        WHEN 'TP' THEN 9
        WHEN 'TT' THEN 10
        WHEN 'ST' THEN 11
        -- Suboficiales
        WHEN 'SM' THEN 12
        WHEN 'SP' THEN 13
        WHEN 'SA' THEN 14
        WHEN 'SI' THEN 15
        WHEN 'SG' THEN 16
        WHEN 'CI' THEN 17
        WHEN 'CB' THEN 18
        -- Soldados
        WHEN 'SV' THEN 19
        WHEN 'VP' THEN 20
        WHEN 'VS' THEN 21
        WHEN 'VN' THEN 22
        -- Agente civil
        WHEN 'A/C' THEN 23
        ELSE 999
      END,
      nombre_apellido
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Clases · Educación operacional de cuadros · S-3 · B Com 602</title>
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

  .top-actions{
    margin-bottom:10px;
    display:flex;
    justify-content:space-between;
    gap:10px;
    flex-wrap:wrap;
  }

  .kpi-card{
    background:radial-gradient(circle at top left, rgba(34,197,94,.20), transparent 65%);
    border-radius:14px;
    border:1px solid rgba(148,163,184,.45);
    padding:10px 14px;
    font-size:.85rem;
    margin-bottom:10px;
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

  .table-sm th,
  .table-sm td{
    padding:.3rem .4rem;
    font-size:.8rem;
  }

  .badge-si{ background:#16a34a; }
  .badge-no{ background:#dc2626; }
  .badge-ejec{ background:#0ea5e9; }
  .badge-pend{ background:#6b7280; }

  .doc-actual{
    font-size:.75rem;
    color:#9ca3af;
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
        ⬅ Volver a Educación de cuadros
      </a>
      <a href="areas_s3.php" class="btn btn-secondary btn-sm" style="font-weight:700; padding:.35rem .9rem;">
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
        <button form="clasesForm" class="btn btn-success btn-sm" style="font-weight:700;">
          💾 Guardar cambios
        </button>
      </div>
    </div>

    <!-- KPI Clases -->
    <div class="kpi-card">
      <div class="kpi-title">Clases · Programa anual</div>
      <div class="kpi-main">
        <?= e($clasesCumplidas) ?>/<?= e($totalClases) ?> clases cumplidas
      </div>
      <div class="kpi-sub">
        Cumplimiento: <?= e($porcClases) ?>% · Pendientes: <?= e($clasesPendientes) ?>
      </div>
      <div class="progress mt-2" style="height:6px;">
        <div class="progress-bar bg-success" role="progressbar" style="width: <?= e($porcClases) ?>%;"></div>
      </div>
    </div>

    <div class="panel">
      <div class="panel-title">Clases · Programa de educación de la unidad</div>
      <div class="panel-sub">
        Registro de las clases planificadas para cuadros. Podés actualizar todos los campos,
        marcar si se cumplió y adjuntar la evidencia (orden, PE, listado de asistencia, informe, etc.).
      </div>

      <form id="clasesForm" action="save_s3_educacion.php" method="post" enctype="multipart/form-data">
        <?php if (function_exists('csrf_input')) { echo csrf_input(); } ?>
        <input type="hidden" name="section" value="clases">

        <div class="table-responsive">
          <table class="table table-sm table-dark align-middle">
            <thead>
              <tr>
                <th style="width:50px;">Sem</th>
                <th style="width:80px;">Fecha</th>
                <th style="width:110px;">Clase de trabajo</th>
                <th>Tema</th>
                <th style="width:170px;">Responsable</th>
                <th style="width:150px;">Participantes (PDF)</th>
                <th style="width:110px;">Lugar</th>
                <th style="width:120px;">Se cumplió</th>
                <th style="width:240px;">Evidencia</th>
              </tr>
            </thead>
            <tbody>
            <?php if (empty($clases)): ?>
              <tr><td colspan="9" class="text-center text-muted">Sin registros de clases.</td></tr>
            <?php else: ?>
              <?php foreach ($clases as $c): ?>
                <?php
                  $id      = (int)$c['id'];
                  $cumplio = (string)$c['cumplio'];
                  $doc     = isset($c['documento']) ? (string)$c['documento'] : '';
                  $pdf     = isset($c['participantes_pdf']) ? (string)$c['participantes_pdf'] : '';
                ?>
                <tr>
                  <!-- Semana -->
                  <td>
                    <input type="text"
                           name="clases_semana[<?= $id ?>]"
                           class="form-control form-control-sm"
                           value="<?= e($c['semana']) ?>">
                  </td>

                  <!-- Fecha -->
                  <td>
                    <input type="date"
                           name="clases_fecha[<?= $id ?>]"
                           class="form-control form-control-sm"
                           value="<?= e($c['fecha']) ?>">
                  </td>

                  <!-- Clase de trabajo -->
                  <td>
                    <input type="text"
                           name="clases_clase_trabajo[<?= $id ?>]"
                           class="form-control form-control-sm"
                           value="<?= e($c['clase_trabajo']) ?>">
                  </td>

                  <!-- Tema -->
                  <td>
                    <input type="text"
                           name="clases_tema[<?= $id ?>]"
                           class="form-control form-control-sm"
                           value="<?= e($c['tema']) ?>">
                  </td>

                  <!-- Responsable (datalist basado en personal_unidad, placeholder en el input) -->
                  <td>
                    <input type="text"
                           name="clases_responsable[<?= $id ?>]"
                           class="form-control form-control-sm"
                           list="responsablesList"
                           autocomplete="off"
                           placeholder="Responsable..."
                           value="<?= e($c['responsable']) ?>">
                  </td>

                  <!-- Participantes (PDF) -->
                  <td>
                    <input type="hidden"
                           name="clases_pdf_actual[<?= $id ?>]"
                           value="<?= e($pdf) ?>">

                    <?php if ($pdf !== ''): ?>
                      <div class="doc-actual mb-1">
                        <a href="../<?= e($pdf) ?>" target="_blank" class="link-light">
                          <?= e(basename($pdf)) ?>
                        </a>
                      </div>
                    <?php endif; ?>

                    <input type="file"
                           name="clases_pdf[<?= $id ?>]"
                           accept="application/pdf"
                           class="form-control form-control-sm">
                  </td>

                  <!-- Lugar -->
                  <td>
                    <input type="text"
                           name="clases_lugar[<?= $id ?>]"
                           class="form-control form-control-sm"
                           value="<?= e($c['lugar']) ?>">
                  </td>

                  <!-- Se cumplió -->
                  <td>
                    <select name="clases_cumplio[<?= $id ?>]"
                            class="form-select form-select-sm">
                      <option value="" <?= $cumplio===''?'selected':'' ?>>—</option>
                      <option value="si" <?= $cumplio==='si'?'selected':'' ?>>Sí</option>
                      <option value="no" <?= $cumplio==='no'?'selected':'' ?>>No</option>
                      <option value="en_ejecucion" <?= $cumplio==='en_ejecucion'?'selected':'' ?>>En ejecución</option>
                    </select>
                  </td>

                  <!-- Evidencia (solo 1 bloque, documento principal) -->
                  <td>
                    <input type="hidden"
                           name="clases_doc_actual[<?= $id ?>]"
                           value="<?= e($doc) ?>">

                    <?php if ($doc !== ''): ?>
                      <div class="doc-actual mb-1">
                        <a href="../<?= e($doc) ?>" target="_blank" class="link-light">
                          <?= e(basename($doc)) ?>
                        </a>
                      </div>
                    <?php else: ?>
                      <div class="doc-actual mb-1">
                        <span class="text-secondary">Sin documento</span>
                      </div>
                    <?php endif; ?>

                    <input type="file"
                           name="clases_file[<?= $id ?>]"
                           class="form-control form-control-sm">
                    <small class="text-secondary d-block mt-1" style="font-size:.72rem;">
                      Orden, PE, informe u otra evidencia (reemplaza al actual).
                    </small>
                  </td>

                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
          </table>
        </div>

        <div class="text-end mt-2">
          <button class="btn btn-success btn-sm" style="font-weight:700;">
            💾 Guardar cambios
          </button>
        </div>
      </form>
    </div>

  </div>
</div>

<!-- Datalist con el personal de la unidad para el campo Responsable -->
<datalist id="responsablesList">
  <?php foreach ($personal as $p): ?>
    <?php
      $grado = trim((string)($p['grado'] ?? ''));
      $arma  = trim((string)($p['arma'] ?? ''));
      $nom   = trim((string)($p['nombre_apellido'] ?? ''));
      $label = trim($grado.' '.$arma.' '.$nom);
    ?>
    <?php if ($label !== ''): ?>
      <option value="<?= e($label) ?>"></option>
    <?php endif; ?>
  <?php endforeach; ?>
</datalist>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Toast de "guardado correcto" cuando volvés de un POST normal
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

// ===== AUTOGUARDADO (solo campos de texto / selects) =====
(function(){
  const form = document.getElementById('clasesForm');
  if (!form) return;

  const AUTOSAVE_MS = 8000; // 8 segundos desde el último cambio
  let timer = null;
  let lastPayload = '';

  function scheduleAutosave() {
    if (timer) clearTimeout(timer);
    timer = setTimeout(runAutosave, AUTOSAVE_MS);
  }

  function runAutosave() {
    const fd = new FormData(form);

    // Marcamos que es autosave para que el PHP NO toque los archivos
    fd.append('autosave', '1');

    // Generamos una huella simple de los datos para no mandar si no cambió nada
    const plain = [];
    fd.forEach((v, k) => {
      // No nos importa csrf ni section para la huella
      if (k === 'section' || k === 'csrf_token' || k === 'autosave') return;
      // No incluimos archivos en la huella
      if (v instanceof File) return;
      plain.push(k + '=' + v);
    });
    const payload = plain.sort().join('&');
    if (payload === lastPayload) {
      return;
    }
    lastPayload = payload;

    fetch('save_s3_educacion.php', {
      method: 'POST',
      body: fd,
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).catch(function(){ /* silencioso */ });
  }

  // Disparamos autosave ante cambios en inputs (excepto archivos) y selects
  form.querySelectorAll('input:not([type="file"]), select, textarea')
      .forEach(function(el){
        el.addEventListener('input', scheduleAutosave);
        el.addEventListener('change', scheduleAutosave);
      });
})();
</script>
</body>
</html>
