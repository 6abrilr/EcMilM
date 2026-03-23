<?php
// public/s3_educacion_alocuciones.php — S-3 Educación · Alocuciones
declare(strict_types=1);

// ========= MODO PRODUCCIÓN / EJÉRCITO =========
$OFFLINE_MODE = false;
// ===================================

require_once __DIR__ . '/../auth/bootstrap.php';
if (!$OFFLINE_MODE) {
    require_login();
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/operaciones_educacion_tables_helper.php';

/** @var PDO $pdo */
s3_ensure_tables($pdo);

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

/* ===== Procesar altas / bajas rápidas (POST local a este mismo archivo) ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    /* Alta rápida de nueva alocución */
    if ($accion === 'nueva_alocucion') {
        $nro             = trim((string)($_POST['nuevo_nro'] ?? ''));
        $fecha           = trim((string)($_POST['nueva_fecha'] ?? ''));
        $acontecimiento  = trim((string)($_POST['nuevo_acontecimiento'] ?? ''));
        $responsableNuevo= trim((string)($_POST['nuevo_responsable'] ?? ''));

        // Si hay algo cargado, damos de alta
        if ($acontecimiento !== '' || $responsableNuevo !== '' || $nro !== '') {
            $sqlIns = "
                INSERT INTO s3_alocuciones (nro, fecha, acontecimiento, responsable)
                VALUES (:nro, :fecha, :acontecimiento, :responsable)
            ";
            $stmtIns = $pdo->prepare($sqlIns);
            $stmtIns->execute([
                ':nro'            => $nro !== '' ? $nro : null,
                ':fecha'          => $fecha !== '' ? $fecha : null,
                ':acontecimiento' => $acontecimiento !== '' ? $acontecimiento : null,
                ':responsable'    => $responsableNuevo !== '' ? $responsableNuevo : null,
            ]);
        }

        header('Location: s3_educacion_alocuciones.php?saved=1');
        exit;
    }

    /* Borrar alocución seleccionada */
    if ($accion === 'borrar_alocucion') {
        $delId = isset($_POST['aloc_id']) ? (int)$_POST['aloc_id'] : 0;
        if ($delId > 0) {
            $stmtDel = $pdo->prepare("DELETE FROM s3_alocuciones WHERE id = :id");
            $stmtDel->execute([':id' => $delId]);
        }
        header('Location: s3_educacion_alocuciones.php?saved=1');
        exit;
    }
}

/* ===== Assets ===== */
$PUBLIC_URL = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'])), '/');
$APP_URL    = rtrim(str_replace('\\','/', dirname($PUBLIC_URL)), '/');
$ASSETS_URL = ($APP_URL === '' ? '' : $APP_URL) . '/assets';
$IMG_BG     = $ASSETS_URL . '/img/fondo.png';
$ESCUDO     = $ASSETS_URL . '/img/escudo_bcom602.png';

/* ===== Base de storage para evidencias (alocuciones) ===== */
$BASE_REL   = 'storage/s3_educacion';
$DOC_SUBDIR = 'alocuciones_docs';
$BASE_DIR   = realpath(__DIR__ . '/../' . $BASE_REL);
if ($BASE_DIR === false) {
    $BASE_DIR = __DIR__ . '/../' . $BASE_REL;
}

/* ===== Filtros por Acontecimiento / Responsable (GET) ===== */
$filtroAcont = trim((string)($_GET['acontecimiento'] ?? ''));
$filtroResp  = trim((string)($_GET['responsable'] ?? ''));

/* ===== Datos de alocuciones (aplicando filtros) ===== */
$sqlAloc = "SELECT * FROM s3_alocuciones WHERE 1=1";
$params  = [];

if ($filtroAcont !== '') {
    $sqlAloc .= " AND acontecimiento LIKE :acont";
    $params[':acont'] = '%' . $filtroAcont . '%';
}
if ($filtroResp !== '') {
    $sqlAloc .= " AND responsable LIKE :resp";
    $params[':resp'] = '%' . $filtroResp . '%';
}

$sqlAloc .= " ORDER BY nro, id";

$stmtAl = $pdo->prepare($sqlAloc);
$stmtAl->execute($params);
$alocuciones = $stmtAl->fetchAll(PDO::FETCH_ASSOC);

/* ===== KPI simple de alocuciones (general, sin filtros) ===== */
$row = $pdo->query("
    SELECT
      COUNT(*) AS total,
      SUM(cumplio = 'si') AS ok
    FROM s3_alocuciones
")->fetch(PDO::FETCH_ASSOC) ?: ['total'=>0,'ok'=>0];

$totalAloc      = (int)$row['total'];
$alocCumplidas  = (int)$row['ok'];
$alocPendientes = max($totalAloc - $alocCumplidas, 0);
$porcAloc       = $totalAloc > 0 ? round($alocCumplidas * 100.0 / $totalAloc, 1) : 0.0;

$savedFlag = ($_GET['saved'] ?? '') === '1';

/* ===== Listado de personal (para campo Responsable) ===== */
$personal = $pdo->query("
    SELECT
      grado,
      arma_espec      AS arma,
      apellido_nombre AS nombre_apellido
    FROM personal_unidad
    WHERE apellido_nombre IS NOT NULL AND apellido_nombre <> ''
    ORDER BY apellido_nombre
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Alocuciones · Educación operacional · S-3 · B Com 602</title>
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
  .container-main{ max_width:1400px; margin:auto; }

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

  .doc-actual{
    font-size:.75rem;
    color:#bfdbfe;
  }

  .search-panel label{
    font-size:.8rem;
    color:#bfdbfe;
    margin-bottom:2px;
  }

  .text-muted{
    color:#bfdbfe !important;
  }
  .text-secondary{
    color:#bfdbfe !important;
  }
  label.form-label{
    color:#bfdbfe;
  }

  .col-sel-header,
  .col-sel-cell{
    display:none;
  }
  .modo-eliminar .col-sel-header,
  .modo-eliminar .col-sel-cell{
    display:table-cell;
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
        <button form="alocForm" class="btn btn-success btn-sm" style="font-weight:700;">
          💾 Guardar cambios
        </button>
      </div>
    </div>

    <!-- KPI Alocuciones -->
    <div class="kpi-card">
      <div class="kpi-title">Alocuciones</div>
      <div class="kpi-main">
        <?= e($alocCumplidas) ?>/<?= e($totalAloc) ?> alocuciones cumplidas
      </div>
      <div class="kpi-sub">
        Cumplimiento: <?= e($porcAloc) ?>% · Pendientes: <?= e($alocPendientes) ?>
      </div>
      <div class="progress mt-2" style="height:6px;">
        <div class="progress-bar bg-success" role="progressbar" style="width: <?= e($porcAloc) ?>%;"></div>
      </div>
    </div>

    <div class="panel">
      <div class="panel-title">Buscar</div>
     
      <!-- Filtros Acontecimiento / Responsable + botón Eliminar -->
      <div class="search-panel mb-3">
        <form method="get" class="row g-2 align-items-end">
          <div class="col-sm-4 col-md-4">
            <label class="form-label">Buscar por acontecimiento</label>
            <input type="text"
                   name="acontecimiento"
                   class="form-control form-control-sm"
                   placeholder="Acontecimiento / motivo"
                   value="<?= e($filtroAcont) ?>">
          </div>
          <div class="col-sm-4 col-md-4">
            <label class="form-label">Responsable</label>
            <input type="text"
                   name="responsable"
                   class="form-control form-control-sm"
                   list="responsablesList"
                   autocomplete="off"
                   placeholder="Responsable..."
                   value="<?= e($filtroResp) ?>">
          </div>
          <div class="col-sm-4 col-md-4 d-flex gap-1">
            <button type="submit"
                    class="btn btn-success btn-sm w-100"
                    style="font-weight:700;">
              Filtrar
            </button>
            <a href="s3_educacion_alocuciones.php"
               class="btn btn-outline-success btn-sm w-100"
               style="font-weight:600;">
              Limpiar
            </a>
            <button type="button"
                    id="btnEliminarAloc"
                    class="btn btn-outline-danger btn-sm w-100"
                    style="font-weight:700;">
              Eliminar alocución
            </button>
          </div>
        </form>
      </div>
      <div class="panel-title">Alocuciones · Cargar una nueva alocución</div>
       <div class="panel-sub">
        Registro de las alocuciones previstas y realizadas. Podés actualizar todos los campos,
        marcar si se cumplió y adjuntar la evidencia (orden, discurso, foto, informe, etc.).
      </div>
      <!-- Alta rápida de nueva alocución -->
      <form method="post" class="row g-2 align-items-end mb-3">
        <?php if (function_exists('csrf_input')) { echo csrf_input(); } ?>
        <input type="hidden" name="accion" value="nueva_alocucion">

        <div class="col-sm-2 col-md-1">
          <label class="form-label text-muted" style="font-size:.78rem;">Nº</label>
          <input type="text"
                 name="nuevo_nro"
                 class="form-control form-control-sm"
                 placeholder="#">
        </div>

        <div class="col-sm-3 col-md-2">
          <label class="form-label text-muted" style="font-size:.78rem;">Fecha</label>
          <input type="date"
                 name="nueva_fecha"
                 class="form-control form-control-sm">
        </div>

        <div class="col-sm-6 col-md-4">
          <label class="form-label text-muted" style="font-size:.78rem;">Acontecimiento</label>
          <input type="text"
                 name="nuevo_acontecimiento"
                 class="form-control form-control-sm"
                 placeholder="Motivo / evento de la alocución">
        </div>

        <div class="col-sm-6 col-md-3">
          <label class="form-label text-muted" style="font-size:.78rem;">Responsable</label>
          <input type="text"
                 name="nuevo_responsable"
                 class="form-control form-control-sm"
                 list="responsablesList"
                 autocomplete="off"
                 placeholder="Responsable...">
        </div>

        <div class="col-sm-3 col-md-2 d-flex align-items-end">
          <button type="submit"
                  class="btn btn-primary btn-sm w-100"
                  style="font-weight:700;">
            + Agregar
          </button>
        </div>
      </form>

      <!-- Form principal de edición -->
      <form id="alocForm" action="save_s3_educacion.php" method="post" enctype="multipart/form-data">
        <?php if (function_exists('csrf_input')) { echo csrf_input(); } ?>
        <input type="hidden" name="section" value="alocuciones">

        <div class="table-responsive">
          <table id="alocTable" class="table table-sm table-dark align-middle">
            <thead>
              <tr>
                <th class="col-sel-header" style="width:40px;">Sel</th>
                <th style="width:60px;">Nº</th>
                <th style="width:90px;">Fecha</th>
                <th>Acontecimiento</th>
                <th style="width:180px;">Responsable</th>
                <th style="width:120px;">Se cumplió</th>
                <th style="width:260px;">Evidencia</th>
              </tr>
            </thead>
            <tbody>
            <?php if (empty($alocuciones)): ?>
              <tr><td colspan="7" class="text-center text-muted">Sin registros de alocuciones.</td></tr>
            <?php else: ?>
              <?php foreach ($alocuciones as $a): ?>
                <?php
                  $id      = (int)$a['id'];
                  $cumplio = (string)$a['cumplio'];
                  $doc     = isset($a['documento']) ? (string)$a['documento'] : '';

                  // Buscar TODA la evidencia de la alocución en el storage (múltiples archivos)
                  $evFiles = [];
                  if (is_dir($BASE_DIR . '/' . $DOC_SUBDIR)) {
                      $pattern = $BASE_DIR . '/' . $DOC_SUBDIR . '/aloc_' . $id . '_doc_*';
                      $found   = glob($pattern) ?: [];
                      foreach ($found as $absFile) {
                          if (!is_file($absFile)) continue;
                          $relPath   = $BASE_REL . '/' . $DOC_SUBDIR . '/' . basename($absFile);
                          $evFiles[] = $relPath;
                      }
                  }
                ?>
                <tr>
                  <!-- Selección para borrar -->
                  <td class="text-center col-sel-cell">
                    <input type="radio"
                           name="aloc_sel"
                           value="<?= e($id) ?>">
                  </td>

                  <!-- Nro -->
                  <td>
                    <input type="text"
                           name="aloc_nro[<?= $id ?>]"
                           class="form-control form-control-sm"
                           value="<?= e($a['nro']) ?>">
                  </td>

                  <!-- Fecha -->
                  <td>
                    <input type="date"
                           name="aloc_fecha[<?= $id ?>]"
                           class="form-control form-control-sm"
                           value="<?= e($a['fecha']) ?>">
                  </td>

                  <!-- Acontecimiento -->
                  <td>
                    <input type="text"
                           name="aloc_acontecimiento[<?= $id ?>]"
                           class="form-control form-control-sm"
                           value="<?= e($a['acontecimiento']) ?>">
                  </td>

                  <!-- Responsable -->
                  <td>
                    <input type="text"
                           name="aloc_responsable[<?= $id ?>]"
                           class="form-control form-control-sm"
                           list="responsablesList"
                           autocomplete="off"
                           placeholder="Responsable..."
                           value="<?= e($a['responsable']) ?>">
                  </td>

                  <!-- Se cumplió -->
                  <td>
                    <select name="aloc_cumplio[<?= $id ?>]"
                            class="form-select form-select-sm">
                      <option value="" <?= $cumplio===''?'selected':'' ?>>—</option>
                      <option value="si" <?= $cumplio==='si'?'selected':'' ?>>Sí</option>
                      <option value="no" <?= $cumplio==='no'?'selected':'' ?>>No</option>
                      <option value="en_ejecucion" <?= $cumplio==='en_ejecucion'?'selected':'' ?>>En ejecución</option>
                    </select>
                  </td>

                  <!-- Evidencia (múltiples docs) -->
                  <td>
                    <!-- Valor actual en BD (compatibilidad con save_s3_educacion.php) -->
                    <input type="hidden"
                           name="aloc_doc_actual[<?= $id ?>]"
                           value="<?= e($doc) ?>">

                    <!-- Archivos ya cargados en el storage -->
                    <div class="ev-current small mb-1" id="ev-current-<?= $id ?>">
                      <?php if (!empty($evFiles)): ?>
                        <div class="d-flex flex-wrap gap-1">
                          <?php foreach ($evFiles as $idx => $path): ?>
                            <?php $label = basename($path); ?>
                            <?php
                              $delUrl = 's3_educacion_delete_doc.php?tipo=alocucion&id='
                                        . $id . '&file=' . urlencode(basename($path));
                            ?>
                            <div class="btn-group btn-group-sm mb-1" role="group">
                              <a class="btn btn-outline-info"
                                 href="../<?= e($path) ?>"
                                 target="_blank">
                                <?= e($label) ?>
                              </a>
                              <a
                                href="#"
                                class="btn btn-outline-danger btn-sm btn-ev-del"
                                data-delete-url="<?= e($delUrl) ?>"
                                data-file-name="<?= e($label) ?>"
                              >
                                &times;
                              </a>
                            </div>
                          <?php endforeach; ?>
                        </div>
                      <?php else: ?>
                        <span class="text-secondary">Sin documentos</span>
                      <?php endif; ?>
                    </div>

                    <!-- Archivo nuevo -->
                    <input type="file"
                           name="aloc_file[<?= $id ?>]"
                           class="form-control form-control-sm ev-input"
                           data-row="aloc-<?= $id ?>">
                    <small class="text-secondary d-block mt-1" style="font-size:.72rem;">
                      Orden, discurso, foto u otra evidencia. Podés subir más de un archivo en guardados sucesivos.
                    </small>

                    <!-- Archivos recién seleccionados (sin guardar aún) -->
                    <div class="ev-selected small text-info mt-1" id="ev-selected-aloc-<?= $id ?>"></div>
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

<!-- Form oculto para borrar alocución -->
<form id="deleteAlocForm" method="post" class="d-none">
  <?php if (function_exists('csrf_input')) { echo csrf_input(); } ?>
  <input type="hidden" name="accion" value="borrar_alocucion">
  <input type="hidden" name="aloc_id" id="deleteAlocId" value="">
</form>

<!-- Modal tipo tarjeta para confirmar eliminación de alocución -->
<div class="modal fade" id="modalConfirmDelete" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content"
         style="background:rgba(15,17,23,.95);
                border-radius:18px;
                border:1px solid rgba(148,163,184,.45);
                box-shadow:0 18px 40px rgba(0,0,0,.8);">
      <div class="modal-header border-0">
        <h5 class="modal-title" style="font-weight:800; font-size:.95rem;">
          Eliminar alocución
        </h5>
        <button type="button" class="btn-close btn-close-white"
                data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <p id="modalDeleteText" class="mb-0" style="font-size:.9rem; color:#bfdbfe;">
          ¿Seguro que desea eliminar la alocución seleccionada?
        </p>
      </div>
      <div class="modal-footer border-0">
        <button type="button"
                class="btn btn-outline-light btn-sm"
                data-bs-dismiss="modal"
                style="font-weight:600; padding:.3rem .9rem;">
          Cancelar
        </button>
        <button type="button"
                id="btnConfirmDeleteModal"
                class="btn btn-danger btn-sm"
                style="font-weight:700; padding:.3rem .9rem;">
          Eliminar
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal para eliminar archivo de evidencia -->
<div class="modal fade" id="modalDeleteEvid" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content"
         style="background:rgba(15,17,23,.95);
                border-radius:18px;
                border:1px solid rgba(148,163,184,.45);
                box-shadow:0 18px 40px rgba(0,0,0,.8);">
      <div class="modal-header border-0">
        <h5 class="modal-title" style="font-weight:800; font-size:.95rem;">
          Eliminar archivo de evidencia
        </h5>
        <button type="button" class="btn-close btn-close-white"
                data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <p class="mb-1" style="font-size:.9rem; color:#bfdbfe;">
          ¿Seguro que desea eliminar el siguiente archivo?
        </p>
        <p id="deleteEvidFileName"
           class="fw-bold mb-0"
           style="font-size:.9rem; color:#e5e7eb;"></p>
      </div>
      <div class="modal-footer border-0">
        <button type="button"
                class="btn btn-outline-light btn-sm"
                data-bs-dismiss="modal"
                style="font-weight:600; padding:.3rem .9rem;">
          Cancelar
        </button>
        <button type="button"
                id="btnConfirmDeleteEvid"
                class="btn btn-danger btn-sm"
                style="font-weight:700; padding:.3rem .9rem;">
          Eliminar
        </button>
      </div>
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
  const form = document.getElementById('alocForm');
  if (!form) return;

  const AUTOSAVE_MS = 8000;
  let timer = null;
  let lastPayload = '';

  function scheduleAutosave() {
    if (timer) clearTimeout(timer);
    timer = setTimeout(runAutosave, AUTOSAVE_MS);
  }

  function runAutosave() {
    const fd = new FormData(form);
    fd.append('autosave', '1');

    const plain = [];
    fd.forEach((v, k) => {
      if (k === 'section' || k === 'csrf_token' || k === 'autosave') return;
      if (v instanceof File) return;
      plain.push(k + '=' + v);
    });
    const payload = plain.sort().join('&');
    if (payload === lastPayload) return;
    lastPayload = payload;

    fetch('save_s3_educacion.php', {
      method: 'POST',
      body: fd,
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).catch(function(){});
  }

  form.querySelectorAll('input:not([type="file"]), select, textarea')
      .forEach(function(el){
        el.addEventListener('input', scheduleAutosave);
        el.addEventListener('change', scheduleAutosave);
      });
})();

// ===== Eliminar alocución seleccionada (modo selección + modal) =====
document.addEventListener('DOMContentLoaded', function(){
  const btnDel   = document.getElementById('btnEliminarAloc');
  const formDel  = document.getElementById('deleteAlocForm');
  const inputId  = document.getElementById('deleteAlocId');
  const table    = document.getElementById('alocTable');

  const modalEl  = document.getElementById('modalConfirmDelete');
  const modalTxt = document.getElementById('modalDeleteText');
  const btnModal = document.getElementById('btnConfirmDeleteModal');
  const bsModal  = modalEl && window.bootstrap
                   ? new bootstrap.Modal(modalEl)
                   : null;

  if (!btnDel || !formDel || !inputId || !table || !bsModal) return;

  let deleteMode = false;

  btnDel.addEventListener('click', function(){
    if (!deleteMode) {
      // 1er click: activamos modo selección y mostramos la columna "Sel"
      deleteMode = true;
      table.classList.add('modo-eliminar');
      btnDel.classList.remove('btn-outline-danger');
      btnDel.classList.add('btn-danger');
      btnDel.textContent = 'Confirmar eliminar';
      return;
    }

    // 2do click: abrimos el modal
    const sel = document.querySelector('input[name="aloc_sel"]:checked');

    if (!sel) {
      modalTxt.textContent = 'Primero seleccione una alocución en la columna "Sel".';
      btnModal.style.display = 'none';
      bsModal.show();
      return;
    }

    modalTxt.textContent = '¿Seguro que desea eliminar la alocución seleccionada?';
    btnModal.style.display = '';
    inputId.value = sel.value;   // dejamos listo el id para borrar
    bsModal.show();
  });

  // Confirmar eliminación desde el modal
  btnModal.addEventListener('click', function(){
    if (!inputId.value) return;
    formDel.submit();
  });
});

// === Mostrar nombres de archivos seleccionados (sin guardar aún) ===
(function(){
  function escapeHtml(str){
    return String(str).replace(/[&<>"']/g, function(m){
      return ({
        '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
      })[m] || m;
    });
  }

  document.querySelectorAll('.ev-input').forEach(function(input){
    input.addEventListener('change', function(){
      var rowKey = this.getAttribute('data-row') || '';
      if (!rowKey) return;

      var box = document.getElementById('ev-selected-' + rowKey);
      if (!box) return;

      var files = Array.from(this.files || []);
      if (!files.length) {
        box.innerHTML = '';
        return;
      }

      var html = '<div class="text-info">Archivos seleccionados (pendiente de guardar):</div>';
      html += '<ul class="mb-0 ps-3">';
      files.forEach(function(f){
        html += '<li>' + escapeHtml(f.name) + '</li>';
      });
      html += '</ul>';
      html += '<div class="text-muted mt-1" style="font-size:.75rem;">Recordá presionar "Guardar" para subirlos.</div>';

      box.innerHTML = html;
    });
  });
})();

// ===== Modal para borrar archivos de evidencia (.btn-ev-del) =====
document.addEventListener('DOMContentLoaded', function () {
  const btns = document.querySelectorAll('.btn-ev-del');
  const modalEl = document.getElementById('modalDeleteEvid');
  const fileNameEl = document.getElementById('deleteEvidFileName');
  const btnConfirm = document.getElementById('btnConfirmDeleteEvid');

  if (!btns.length || !modalEl || !fileNameEl || !btnConfirm || !window.bootstrap) {
    return;
  }

  const bsModal = new bootstrap.Modal(modalEl);
  let currentUrl = null;

  btns.forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      currentUrl = this.getAttribute('data-delete-url') || '#';
      const fname = this.getAttribute('data-file-name') || '';
      fileNameEl.textContent = fname;
      bsModal.show();
    });
  });

  btnConfirm.addEventListener('click', function () {
    if (!currentUrl || currentUrl === '#') return;
    window.location.href = currentUrl;
  });
});
</script>
</body>
</html>
