<?php
// public/s3_educacion_cursos.php — S-3 Educación · Cursos regulares
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

/* ===== Base de storage para evidencias (cursos) ===== */
/* OJO: solo documentos en filesystem, NADA en base de datos */
$BASE_REL      = 'storage/s3_educacion';
$DOC_SUBDIR    = 'cursos_docs';
$PDF_SUBDIR    = 'cursos_participantes';
$BASE_DIR      = realpath(__DIR__ . '/../' . $BASE_REL);
if ($BASE_DIR === false) {
    $BASE_DIR = __DIR__ . '/../' . $BASE_REL;
}
if (!is_dir($BASE_DIR)) {
    @mkdir($BASE_DIR, 0775, true);
}
if (!is_dir($BASE_DIR . '/' . $DOC_SUBDIR)) {
    @mkdir($BASE_DIR . '/' . $DOC_SUBDIR, 0775, true);
}
if (!is_dir($BASE_DIR . '/' . $PDF_SUBDIR)) {
    @mkdir($BASE_DIR . '/' . $PDF_SUBDIR, 0775, true);
}

/* ===== Procesar altas / bajas rápidas (POST local a este mismo archivo) ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    /* Alta rápida de nuevo curso regular */
    if ($accion === 'nuevo_curso') {
        $sigla        = trim((string)($_POST['nueva_sigla'] ?? ''));
        $denominacion = trim((string)($_POST['nueva_denominacion'] ?? ''));
        $desde        = trim((string)($_POST['nuevo_desde'] ?? ''));
        $hasta        = trim((string)($_POST['nuevo_hasta'] ?? ''));

        // Participantes: vienen como array participantes_nombres[]
        $participantesArr = [];
        if (isset($_POST['participantes_nombres']) && is_array($_POST['participantes_nombres'])) {
            foreach ($_POST['participantes_nombres'] as $pNombre) {
                $pNombre = trim((string)$pNombre);
                if ($pNombre !== '') {
                    $participantesArr[] = $pNombre;
                }
            }
        }
        $participantes = implode("\n", $participantesArr);

        // Normalizar fechas (YYYY-MM-DD o null)
        $desdeDb = null;
        if ($desde !== '') {
            $ts = strtotime(str_replace(['/','.'], '-', $desde));
            if ($ts !== false) {
                $desdeDb = date('Y-m-d', $ts);
            }
        }
        $hastaDb = null;
        if ($hasta !== '') {
            $ts = strtotime(str_replace(['/','.'], '-', $hasta));
            if ($ts !== false) {
                $hastaDb = date('Y-m-d', $ts);
            }
        }

        // Solo inserto si tiene algo cargado
        if ($sigla !== '' || $denominacion !== '' || !empty($participantesArr)) {
            $sqlIns = "
                INSERT INTO s3_cursos_regulares
                    (sigla, denominacion, participantes, desde, hasta, cumplio, participantes_pdf)
                VALUES
                    (:sigla, :denominacion, :participantes, :desde, :hasta, NULL, NULL)
            ";
            $stmtIns = $pdo->prepare($sqlIns);
            $stmtIns->execute([
                ':sigla'         => $sigla !== '' ? $sigla : null,
                ':denominacion'  => $denominacion !== '' ? $denominacion : null,
                ':participantes' => $participantes !== '' ? $participantes : null,
                ':desde'         => $desdeDb,
                ':hasta'         => $hastaDb,
            ]);
        }

        header('Location: s3_educacion_cursos.php?saved=1');
        exit;
    }

    /* Borrar curso seleccionado (desde este mismo archivo) */
    if ($accion === 'borrar_curso') {
        $delId = isset($_POST['curso_id']) ? (int)$_POST['curso_id'] : 0;
        if ($delId > 0) {
            $stmtDel = $pdo->prepare("DELETE FROM s3_cursos_regulares WHERE id = :id");
            $stmtDel->execute([':id' => $delId]);

            // Borrar también evidencias del filesystem asociadas a ese curso
            $pattern = $BASE_DIR . '/' . $DOC_SUBDIR . '/curso_' . $delId . '_doc_*';
            $found   = glob($pattern) ?: [];
            foreach ($found as $absFile) {
                if (is_file($absFile)) {
                    @unlink($absFile);
                }
            }
        }
        header('Location: s3_educacion_cursos.php?saved=1');
        exit;
    }
}

/* ===== Assets ===== */
$PUBLIC_URL = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'])), '/');
$APP_URL    = rtrim(str_replace('\\','/', dirname($PUBLIC_URL)), '/');
$ASSETS_URL = ($APP_URL === '' ? '' : $APP_URL) . '/assets';
$IMG_BG     = $ASSETS_URL . '/img/fondo.png';
$ESCUDO     = $ASSETS_URL . '/img/escudo_bcom602.png';

/* ===== Filtros por Sigla / Denominación / Participantes (GET) ===== */
$filtroSigla        = trim((string)($_GET['sigla'] ?? ''));
$filtroDenominacion = trim((string)($_GET['denominacion'] ?? ''));
$filtroParticip     = trim((string)($_GET['participantes'] ?? ''));

/* ===== Datos de cursos (aplicando filtros) ===== */
$sqlCursos = "SELECT * FROM s3_cursos_regulares WHERE 1=1";
$params    = [];

if ($filtroSigla !== '') {
    $sqlCursos .= " AND sigla LIKE :sigla";
    $params[':sigla'] = '%' . $filtroSigla . '%';
}
if ($filtroDenominacion !== '') {
    $sqlCursos .= " AND denominacion LIKE :denom";
    $params[':denom'] = '%' . $filtroDenominacion . '%';
}
if ($filtroParticip !== '') {
    $sqlCursos .= " AND participantes LIKE :part";
    $params[':part'] = '%' . $filtroParticip . '%';
}

$sqlCursos .= " ORDER BY sigla, id";

$stmtCur = $pdo->prepare($sqlCursos);
$stmtCur->execute($params);
$cursosRegulares = $stmtCur->fetchAll(PDO::FETCH_ASSOC);

/* ===== KPI simple de cursos (general, sin filtros) ===== */
/* Se guarda el estado curso x curso en la tabla (columna cumplio),
   el KPI se calcula cada vez que se entra a la página */
$row = $pdo->query("
    SELECT
      COUNT(*) AS total,
      SUM(cumplio = 'si') AS ok
    FROM s3_cursos_regulares
")->fetch(PDO::FETCH_ASSOC) ?: ['total'=>0,'ok'=>0];

$totalCursos      = (int)$row['total'];
$cursosCumplidos  = (int)$row['ok'];
$cursosPendientes = max($totalCursos - $cursosCumplidos, 0);
$porcCursos       = $totalCursos > 0 ? round($cursosCumplidos * 100.0 / $totalCursos, 1) : 0.0;

/* ===== Listado de personal para autocomplete (igual que en s3_educacion_clases.php) ===== */
$personalUnidad = [];
try {
    $personalUnidad = $pdo->query("
        SELECT
          grado,
          arma_espec      AS arma,
          apellido_nombre AS nombre_apellido
        FROM personal_unidad
        WHERE apellido_nombre IS NOT NULL AND apellido_nombre <> ''
        ORDER BY apellido_nombre
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $personalUnidad = [];
}

$savedFlag = ($_GET['saved'] ?? '') === '1';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Cursos regulares · Educación operacional · S-3 · B Com 602</title>
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
    margin-left:auto;
    margin-right:17px;
    margin-top:4px;
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

  .search-panel .btn-sm{
    padding:.25rem .6rem;
    font-size:.76rem;
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

  /* Participantes como texto bloqueado */
  .participantes-text {
    white-space: pre-wrap;
    font-size:.78rem;
    color:#e5e7eb;
  }

  /* Columna acciones */
  .acciones-col .btn {
    padding:.15rem .45rem;
    font-size:.75rem;
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
  <div class="hero-inner container-main d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center gap-3">
      <img class="brand-logo" src="<?= e($ESCUDO) ?>" alt="Escudo 602" style="height:52px; width:auto;">
      <div>
        <div class="brand-title">Batallón de Comunicaciones 602</div>
        <div class="brand-sub">“Hogar de las Comunicaciones Fijas del Ejército”</div>
      </div>
    </div>
    <div class="header-back">
      <a href="s3_educacion_cuadros.php" class="btn btn-success btn-sm" style="font-weight:700; padding:.35rem .9rem;">
        ⬅ Volver a Educación de cuadros
      </a>
      <a href="areas.php" class="btn btn-success btn-sm" style="font-weight:700; padding:.35rem .9rem;">
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
        <button form="cursosForm" class="btn btn-success btn-sm" style="font-weight:700;">
          💾 Guardar cambios
        </button>
      </div>
    </div>

    <!-- KPI Cursos regulares -->
    <div class="kpi-card">
      <div class="kpi-title">Cursos regulares</div>
      <div class="kpi-main">
        <span id="kpi_cumplidos"><?= e($cursosCumplidos) ?></span> /
        <span id="kpi_total"><?= e($totalCursos) ?></span> cursos cumplidos
      </div>
      <div class="kpi-sub">
        Cumplimiento: <span id="kpi_porc"><?= e($porcCursos) ?></span>% ·
        Pendientes: <span id="kpi_pend"><?= e($cursosPendientes) ?></span>
      </div>
      <div class="progress mt-2" style="height:6px;">
        <div class="progress-bar bg-success"
             id="kpi_bar"
             role="progressbar"
             style="width: <?= e($porcCursos) ?>%;"></div>
      </div>
    </div>

    <div class="panel">
      <div class="panel-title">Buscar Cursos regulares</div>

      <!-- Datalist con el personal de la unidad -->
      <datalist id="personalUnidadList">
        <?php foreach ($personalUnidad as $p): ?>
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

      <!-- Filtros Sigla / Denominación con botones al lado -->
      <div class="search-panel mb-3">
        <form method="get" class="row g-2 align-items-end">
          <!-- Sigla -->
          <div class="col-sm-4 col-md-3">
            <label class="form-label">Sigla</label>
            <div class="input-group input-group-sm">
              <input type="text"
                     name="sigla"
                     class="form-control form-control-sm"
                     placeholder="SIGLA"
                     value="<?= e($filtroSigla) ?>">
            </div>
          </div>

          <!-- Denominación + Buscar + Limpiar -->
          <div class="col-sm-8 col-md-5">
            <label class="form-label">Denominación</label>
            <div class="input-group input-group-sm">
              <input type="text"
                     name="denominacion"
                     class="form-control form-control-sm"
                     placeholder="Nombre del curso"
                     value="<?= e($filtroDenominacion) ?>">
              <button type="submit"
                      class="btn btn-success"
                      style="font-weight:700;">
                Buscar
              </button>
              <a href="s3_educacion_cursos.php"
                 class="btn btn-outline-success"
                 style="font-weight:600;">
                Limpiar
              </a>
            </div>
          </div>

          <!-- Eliminar curso -->
          <div class="col-12 col-md-4 mt-2 mt-md-0">
            <div class="d-flex justify-content-end">
              <button type="button"
                      id="btnEliminarCurso"
                      class="btn btn-outline-danger btn-sm"
                      style="font-weight:700; min-width:140px;">
                Eliminar curso
              </button>
            </div>
          </div>
        </form>
      </div>

      <div class="panel-title">Crear Cursos regulares · Educación operacional de cuadros</div>
      <div class="panel-sub">
        Registro de los cursos regulares que impactan en la educación del personal de la unidad.
        Podés actualizar los datos, listar los participantes y adjuntar la evidencia (orden, plan de curso,
        certificados, etc.).
      </div>

      <!-- Alta rápida de nuevo curso -->
      <form method="post" class="row g-2 align-items-end mb-3">
        <?php if (function_exists('csrf_input')) { echo csrf_input(); } ?>
        <input type="hidden" name="accion" value="nuevo_curso">

        <!-- Primera fila: Sigla, Denominación, Cantidad -->
        <div class="col-sm-2 col-md-2">
          <label class="form-label text-muted" style="font-size:.78rem;">Sigla</label>
          <input type="text"
                 name="nueva_sigla"
                 class="form-control form-control-sm"
                 placeholder="SIGLA">
        </div>

        <div class="col-sm-5 col-md-5">
          <label class="form-label text-muted" style="font-size:.78rem;">Denominación</label>
          <input type="text"
                 name="nueva_denominacion"
                 class="form-control form-control-sm"
                 placeholder="Nombre del curso">
        </div>

        <div class="col-sm-3 col-md-2">
          <label class="form-label text-muted" style="font-size:.78rem;">Cant. participantes</label>
          <input type="number"
                 id="nuevo_cant_participantes"
                 name="nuevo_cant_participantes"
                 class="form-control form-control-sm"
                 min="0"
                 max="50"
                 placeholder="0">
        </div>

        <!-- Desde / Hasta / Botón -->
        <div class="col-sm-3 col-md-1">
          <label class="form-label text-muted" style="font-size:.78rem;">Desde</label>
          <input type="date"
                 name="nuevo_desde"
                 class="form-control form-control-sm">
        </div>

        <div class="col-sm-3 col-md-1">
          <label class="form-label text-muted" style="font-size:.78rem;">Hasta</label>
          <input type="date"
                 name="nuevo_hasta"
                 class="form-control form-control-sm">
        </div>

        <div class="col-sm-3 col-md-1 d-flex align-items-end">
          <button type="submit"
                  class="btn btn-primary btn-sm w-100"
                  style="font-weight:700;">
            + Agregar
          </button>
        </div>

        <!-- Campos dinámicos de participantes -->
        <div class="col-12 mt-2">
          <label class="form-label text-muted" style="font-size:.78rem;">Participantes</label>
          <div class="row g-2" id="nuevo_participantes_campos">
            <!-- JS genera los inputs acá -->
          </div>
          <small class="text-secondary d-block mt-1" style="font-size:.72rem;">
            Ingrese el apellido y seleccione del listado sugerido del personal de la unidad.
          </small>
        </div>
      </form>
      <!-- Form principal de edición -->
      <form id="cursosForm" action="save_s3_educacion.php" method="post" enctype="multipart/form-data">
        <?php if (function_exists('csrf_input')) { echo csrf_input(); } ?>
        <input type="hidden" name="section" value="cursos">

        <div class="table-responsive">
          <table id="cursosTable" class="table table-sm table-dark align-middle">
            <thead>
              <tr>
                <th class="col-sel-header" style="width:40px;">Sel</th>
                <th style="width:80px;">Sigla</th>
                <th>Denominación</th>
                <th style="width:220px;">Participantes</th>
                <th style="width:90px;">Desde</th>
                <th style="width:90px;">Hasta</th>
                <th style="width:120px;">Se cumplió</th>
                <th style="width:260px;">Evidencia</th>
                <th style="width:110px;">Acciones</th>
              </tr>
            </thead>
            <tbody>
            <?php if (empty($cursosRegulares)): ?>
              <tr><td colspan="9" class="text-center text-muted">Sin registros de cursos regulares.</td></tr>
            <?php else: ?>
              <?php foreach ($cursosRegulares as $c): ?>
                <?php
                  $id      = (int)$c['id'];
                  $cumplio = (string)($c['cumplio'] ?? '');
                  $doc     = isset($c['documento']) ? (string)$c['documento'] : '';
                  $partTxt = (string)($c['participantes'] ?? '');

                  // Buscar TODA la evidencia del curso en el storage (múltiples archivos)
                  $evFiles = [];
                  if (is_dir($BASE_DIR . '/' . $DOC_SUBDIR)) {
                      $pattern = $BASE_DIR . '/' . $DOC_SUBDIR . '/curso_' . $id . '_doc_*';
                      $found   = glob($pattern) ?: [];
                      foreach ($found as $absFile) {
                          if (!is_file($absFile)) continue;
                          $relPath   = $BASE_REL . '/' . $DOC_SUBDIR . '/' . basename($absFile);
                          $evFiles[] = $relPath;
                      }
                  }
                ?>
                <tr data-curso-id="<?= $id ?>">
                  <!-- Selección para borrar -->
                  <td class="text-center col-sel-cell">
                    <input type="radio"
                           name="curso_sel"
                           value="<?= e($id) ?>">
                  </td>

                  <!-- Sigla -->
                  <td>
                    <input type="text"
                           name="cursos_sigla[<?= $id ?>]"
                           class="form-control form-control-sm"
                           value="<?= e($c['sigla']) ?>">
                  </td>

                  <!-- Denominación -->
                  <td>
                    <input type="text"
                           name="cursos_denominacion[<?= $id ?>]"
                           class="form-control form-control-sm"
                           value="<?= e($c['denominacion']) ?>">
                  </td>

                  <!-- Participantes (texto bloqueado + hidden) -->
                  <td>
                    <input type="hidden"
                           name="cursos_participantes[<?= $id ?>]"
                           value="<?= e($partTxt) ?>">
                    <div class="participantes-text">
                      <?= nl2br(e($partTxt)) ?>
                    </div>
                    <small class="text-secondary d-block mt-1" style="font-size:.72rem;">
                      Participantes cargados al crear/editar el curso. Se modifican desde el botón ✏️.
                    </small>
                  </td>

                  <!-- Desde -->
                  <td>
                    <input type="date"
                           name="cursos_desde[<?= $id ?>]"
                           class="form-control form-control-sm"
                           value="<?= e($c['desde']) ?>">
                  </td>

                  <!-- Hasta -->
                  <td>
                    <input type="date"
                           name="cursos_hasta[<?= $id ?>]"
                           class="form-control form-control-sm"
                           value="<?= e($c['hasta']) ?>">
                  </td>

                  <!-- Se cumplió -->
                  <td>
                    <select name="cursos_cumplio[<?= $id ?>]"
                            class="form-select form-select-sm">
                      <option value="" <?= $cumplio===''?'selected':'' ?>>—</option>
                      <option value="si" <?= $cumplio==='si'?'selected':'' ?>>Sí</option>
                      <option value="no" <?= $cumplio==='no'?'selected':'' ?>>No</option>
                      <option value="en_ejecucion" <?= $cumplio==='en_ejecucion'?'selected':'' ?>>En ejecución</option>
                    </select>
                  </td>

                  <!-- Evidencia (múltiples docs) -->
                  <td>
                    <input type="hidden"
                           name="cursos_doc_actual[<?= $id ?>]"
                           value="<?= e($doc) ?>">

                    <div class="ev-current small mb-1" id="ev-current-<?= $id ?>">
                      <?php if (!empty($evFiles)): ?>
                        <div class="d-flex flex-wrap gap-1">
                          <?php foreach ($evFiles as $idx => $path): ?>
                            <?php $label = basename($path); ?>
                            <?php
                              $delUrl = 's3_educacion_delete_doc.php?tipo=curso&id='
                                        . $id . '&file=' . urlencode(basename($path));
                            ?>
                            <div class="btn-group btn-group-sm mb-1" role="group">
                              <a class="btn btn-outline-info"
                                 href="../<?= e($path) ?>"
                                 target="_blank">
                                <?= e($label) ?>
                              </a>
                              <a href="#"
                                 class="btn btn-outline-danger btn-sm btn-ev-del"
                                 data-delete-url="<?= e($delUrl) ?>"
                                 data-file-name="<?= e($label) ?>">
                                &times;
                              </a>
                            </div>
                          <?php endforeach; ?>
                        </div>
                      <?php else: ?>
                        <span class="text-secondary">Sin documentos</span>
                      <?php endif; ?>
                    </div>

                    <input type="file"
                           name="cursos_file[<?= $id ?>]"
                           class="form-control form-control-sm ev-input"
                           data-row="curso-<?= $id ?>">
                    <small class="text-secondary d-block mt-1" style="font-size:.72rem;">
                      Orden, plan de curso, certificados u otra evidencia. Podés subir más de un archivo en guardados sucesivos.
                    </small>

                    <div class="ev-selected small text-info mt-1" id="ev-selected-curso-<?= $id ?>"></div>
                  </td>

                  <!-- Acciones -->
                  <td class="acciones-col text-center">
                    <div class="btn-group" role="group">
                      <button type="button"
                              class="btn btn-outline-warning btn-sm btn-curso-edit"
                              data-id="<?= $id ?>">
                        ✏️
                      </button>
                      <button type="button"
                              class="btn btn-outline-danger btn-sm btn-curso-borrar"
                              data-id="<?= $id ?>"
                              data-denom="<?= e($c['denominacion'] ?? '') ?>">
                        🗑️
                      </button>
                    </div>
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

<!-- Form oculto para borrar curso -->
<form id="deleteCursoForm" method="post" class="d-none">
  <?php if (function_exists('csrf_input')) { echo csrf_input(); } ?>
  <input type="hidden" name="accion" value="borrar_curso">
  <input type="hidden" name="curso_id" id="deleteCursoId" value="">
</form>

<!-- Modal tipo tarjeta para confirmar eliminación de curso -->
<div class="modal fade" id="modalConfirmDelete" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content"
         style="background:rgba(15,17,23,.95);
                border-radius:18px;
                border:1px solid rgba(148,163,184,.45);
                box-shadow:0 18px 40px rgba(0,0,0,.8);">
      <div class="modal-header border-0">
        <h5 class="modal-title" style="font-weight:800; font-size:.95rem;">
          Eliminar curso
        </h5>
        <button type="button" class="btn-close btn-close-white"
                data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <p id="modalDeleteText" class="mb-0" style="font-size:.9rem; color:#bfdbfe;">
          ¿Seguro que desea eliminar el curso seleccionado?
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

<!-- Modal para editar curso completo -->
<div class="modal fade" id="modalEditCurso" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content"
         style="background:rgba(15,17,23,.95);
                border-radius:18px;
                border:1px solid rgba(148,163,184,.45);
                box-shadow:0 18px 40px rgba(0,0,0,.8);">
      <div class="modal-header border-0">
        <h5 class="modal-title" style="font-weight:800; font-size:.95rem;">
          Editar curso regular
        </h5>
        <button type="button" class="btn-close btn-close-white"
                data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="edit_curso_id" value="">
        <div class="row g-2 mb-2">
          <div class="col-sm-3">
            <label class="form-label text-muted" style="font-size:.8rem;">Sigla</label>
            <input type="text" id="edit_sigla" class="form-control form-control-sm">
          </div>
          <div class="col-sm-9">
            <label class="form-label text-muted" style="font-size:.8rem;">Denominación</label>
            <input type="text" id="edit_denominacion" class="form-control form-control-sm">
          </div>
        </div>

        <div class="mb-2">
          <label class="form-label text-muted" style="font-size:.8rem;">Participantes</label>
          <div id="edit_participantes_list">
            <!-- JS arma las filas de participantes (input + ❌) -->
          </div>
          <button type="button"
                  id="btnEditAddPart"
                  class="btn btn-outline-info btn-sm mt-2"
                  style="font-weight:600;">
            + Agregar participante
          </button>
          <small class="text-secondary d-block mt-1" style="font-size:.72rem;">
            Cada participante se muestra en un campo separado. Podés eliminarlo con la ❌
            o agregar más con el botón <strong>“+ Agregar participante”</strong>.
            Usá el listado del personal de la unidad para seleccionar cada nombre.
          </small>
        </div>

        <div class="row g-2 mb-2">
          <div class="col-sm-3">
            <label class="form-label text-muted" style="font-size:.8rem;">Desde</label>
            <input type="date" id="edit_desde" class="form-control form-control-sm">
          </div>
          <div class="col-sm-3">
            <label class="form-label text-muted" style="font-size:.8rem;">Hasta</label>
            <input type="date" id="edit_hasta" class="form-control form-control-sm">
          </div>
          <div class="col-sm-6">
            <label class="form-label text-muted" style="font-size:.8rem;">Se cumplió</label>
            <select id="edit_cumplio" class="form-select form-select-sm">
              <option value="">—</option>
              <option value="si">Sí</option>
              <option value="no">No</option>
              <option value="en_ejecucion">En ejecución</option>
            </select>
          </div>
        </div>

        <small class="text-secondary" style="font-size:.75rem;">
          Al guardar, se actualizará la fila correspondiente en la tabla. Luego podés usar el botón
          <strong>“Guardar cambios”</strong> o esperar al autoguardado.
        </small>
      </div>
      <div class="modal-footer border-0">
        <button type="button"
                class="btn btn-outline-light btn-sm"
                data-bs-dismiss="modal"
                style="font-weight:600; padding:.3rem .9rem;">
          Cancelar
        </button>
        <button type="button"
                id="btnEditGuardar"
                class="btn btn-success btn-sm"
                style="font-weight:700; padding:.3rem .9rem;">
          Guardar cambios
        </button>
      </div>
    </div>
  </div>
</div>

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
  const form = document.getElementById('cursosForm');
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

// ===== Recalcular KPI de cursos en vivo según los selects "Se cumplió" =====
function recalcKpiCursos() {
  const selects = document.querySelectorAll('select[name^="cursos_cumplio["]');
  let total = 0;
  let ok    = 0;

  selects.forEach(function (sel) {
    total++;
    if (sel.value === 'si') {
      ok++;
    }
  });

  const pend = Math.max(total - ok, 0);
  const porc = total > 0 ? Math.round(ok * 1000 / total) / 10 : 0.0; // 1 decimal

  const elCumplidos = document.getElementById('kpi_cumplidos');
  const elTotal     = document.getElementById('kpi_total');
  const elPend      = document.getElementById('kpi_pend');
  const elPorc      = document.getElementById('kpi_porc');
  const elBar       = document.getElementById('kpi_bar');

  if (elCumplidos) elCumplidos.textContent = ok;
  if (elTotal)     elTotal.textContent     = total;
  if (elPend)      elPend.textContent      = pend;
  if (elPorc)      elPorc.textContent      = porc.toFixed(1);
  if (elBar)       elBar.style.width       = porc + '%';
}

document.addEventListener('DOMContentLoaded', function () {
  // Ejecutar una vez al cargar (por si el PHP ya trae algo)
  recalcKpiCursos();

  // Volver a calcular cada vez que cambia un "Se cumplió" desde la tabla
  document.querySelectorAll('select[name^="cursos_cumplio["]')
    .forEach(function (sel) {
      sel.addEventListener('change', recalcKpiCursos);
    });
});

// ===== Eliminar curso seleccionado (modo selección + modal) Y botón por fila =====
document.addEventListener('DOMContentLoaded', function(){
  const btnDel   = document.getElementById('btnEliminarCurso');
  const formDel  = document.getElementById('deleteCursoForm');
  const inputId  = document.getElementById('deleteCursoId');
  const table    = document.getElementById('cursosTable');

  const modalEl  = document.getElementById('modalConfirmDelete');
  const modalTxt = document.getElementById('modalDeleteText');
  const btnModal = document.getElementById('btnConfirmDeleteModal');
  const bsModal  = modalEl && window.bootstrap
                   ? new bootstrap.Modal(modalEl)
                   : null;

  if (!formDel || !inputId || !table || !bsModal) return;

  let deleteMode = false;

  // Botón general "Eliminar curso" (modo selección por radio)
  if (btnDel) {
    btnDel.addEventListener('click', function(){
      if (!deleteMode) {
        deleteMode = true;
        table.classList.add('modo-eliminar');
        btnDel.classList.remove('btn-outline-danger');
        btnDel.classList.add('btn-danger');
        btnDel.textContent = 'Confirmar eliminar';
        return;
      }

      const sel = document.querySelector('input[name="curso_sel"]:checked');

      if (!sel) {
        modalTxt.textContent = 'Primero seleccione un curso en la columna "Sel".';
        btnModal.style.display = 'none';
        bsModal.show();
        return;
      }

      modalTxt.textContent = '¿Seguro que desea eliminar el curso seleccionado?';
      btnModal.style.display = '';
      inputId.value = sel.value;
      bsModal.show();
    });
  }

  // Botón "🗑️" por fila
  document.querySelectorAll('.btn-curso-borrar').forEach(function(btn){
    btn.addEventListener('click', function(){
      const id    = this.getAttribute('data-id') || '';
      const denom = this.getAttribute('data-denom') || '';
      if (!id) return;
      inputId.value = id;
      modalTxt.textContent = denom
        ? '¿Seguro que desea eliminar el curso "' + denom + '"?'
        : '¿Seguro que desea eliminar este curso?';
      btnModal.style.display = '';
      bsModal.show();
    });
  });

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

// ===== Campos dinámicos de participantes (ALTA NUEVO CURSO) =====
(function(){
  const inputCant = document.getElementById('nuevo_cant_participantes');
  const cont      = document.getElementById('nuevo_participantes_campos');
  if (!inputCant || !cont) return;

  function renderCampos() {
    let n = parseInt(inputCant.value, 10);
    if (isNaN(n) || n < 0) n = 0;
    if (n > 50) n = 50;

    const oldVals = [];
    cont.querySelectorAll('input[name="participantes_nombres[]"]').forEach(function(inp){
      oldVals.push(inp.value);
    });

    cont.innerHTML = '';

    for (let i = 0; i < n; i++) {
      const col = document.createElement('div');
      col.className = 'col-sm-6 col-md-4';
      col.innerHTML = `
        <label class="form-label text-muted" style="font-size:.75rem;">Participante ${i+1}</label>
        <input type="text"
               name="participantes_nombres[]"
               class="form-control form-control-sm"
               placeholder="Buscar personal por apellido / nombre"
               list="personalUnidadList"
               autocomplete="off">
      `;
      cont.appendChild(col);
    }

    const newInputs = cont.querySelectorAll('input[name="participantes_nombres[]"]');
    newInputs.forEach(function(inp, idx){
      if (idx < oldVals.length) {
        inp.value = oldVals[idx];
      }
    });
  }

  inputCant.addEventListener('input', renderCampos);
})();

// ===== Modal de edición de curso completo (con participantes separados) =====
document.addEventListener('DOMContentLoaded', function(){
  const modalEl = document.getElementById('modalEditCurso');
  if (!modalEl || !window.bootstrap) return;

  const bsEdit      = new bootstrap.Modal(modalEl);
  const editId      = document.getElementById('edit_curso_id');
  const eSigla      = document.getElementById('edit_sigla');
  const eDenom      = document.getElementById('edit_denominacion');
  const eDesde      = document.getElementById('edit_desde');
  const eHasta      = document.getElementById('edit_hasta');
  const eCumplio    = document.getElementById('edit_cumplio');
  const btnSave     = document.getElementById('btnEditGuardar');
  const partList    = document.getElementById('edit_participantes_list');
  const btnAddPart  = document.getElementById('btnEditAddPart');

  function triggerInput(el){
    if (!el) return;
    el.dispatchEvent(new Event('input', {bubbles:true}));
  }

  function escapeHtml(str){
    return String(str).replace(/[&<>"']/g, function(m){
      return ({
        '&':'&amp;',
        '<':'&lt;',
        '>':'&gt;',
        '"':'&quot;',
        "'":'&#39;'
      })[m] || m;
    });
  }

  function clearParticipantsModal(){
    if (partList) {
      partList.innerHTML = '';
    }
  }

  // Crea una fila de participante (input + ❌)
  function addParticipantRow(value){
    if (!partList) return;

    const row = document.createElement('div');
    row.className = 'input-group input-group-sm mb-1 edit-participant-item';

    row.innerHTML = `
      <input type="text"
             class="form-control form-control-sm edit-participante-input"
             placeholder="Buscar personal por apellido / nombre"
             list="personalUnidadList"
             autocomplete="off">
      <button type="button"
              class="btn btn-outline-danger btn-sm btn-remove-part">
        &times;
      </button>
    `;

    const inp = row.querySelector('.edit-participante-input');
    if (inp) {
      inp.value = value || '';
    }

    const btnDel = row.querySelector('.btn-remove-part');
    if (btnDel) {
      btnDel.addEventListener('click', function(){
        row.remove();
      });
    }

    partList.appendChild(row);
  }

  // Carga la lista de participantes en el modal desde el texto con saltos de línea
  function loadParticipantsToModal(text){
    clearParticipantsModal();
    const lines = (text || '')
      .split(/\r?\n/)
      .map(function(l){ return l.trim(); })
      .filter(function(l){ return l !== ''; });

    if (!lines.length) {
      addParticipantRow('');
    } else {
      lines.forEach(function(l){ addParticipantRow(l); });
    }
  }

  // Botón + agregar participante en el modal
  if (btnAddPart) {
    btnAddPart.addEventListener('click', function(){
      addParticipantRow('');
    });
  }

  // Cuando hago clic en ✏️ de una fila
  document.querySelectorAll('.btn-curso-edit').forEach(function(btn){
    btn.addEventListener('click', function(){
      const id  = this.getAttribute('data-id') || '';
      if (!id) return;
      const row = document.querySelector('tr[data-curso-id="'+id+'"]');
      if (!row) return;

      const nameSig   = 'cursos_sigla['+id+']';
      const nameDenom = 'cursos_denominacion['+id+']';
      const namePart  = 'cursos_participantes['+id+']';
      const nameDesde = 'cursos_desde['+id+']';
      const nameHasta = 'cursos_hasta['+id+']';
      const nameCum   = 'cursos_cumplio['+id+']';

      const rSig   = row.querySelector('input[name="'+nameSig+'"]');
      const rDenom = row.querySelector('input[name="'+nameDenom+'"]');
      const rPart  = row.querySelector('input[name="'+namePart+'"]');
      const rDesde = row.querySelector('input[name="'+nameDesde+'"]');
      const rHasta = row.querySelector('input[name="'+nameHasta+'"]');
      const rCum   = row.querySelector('select[name="'+nameCum+'"]');

      editId.value   = id;
      eSigla.value   = rSig   ? rSig.value   : '';
      eDenom.value   = rDenom ? rDenom.value : '';
      eDesde.value   = rDesde ? rDesde.value : '';
      eHasta.value   = rHasta ? rHasta.value : '';
      eCumplio.value = rCum   ? rCum.value   : '';

      // Cargar participantes en el modal en filas separadas
      loadParticipantsToModal(rPart ? rPart.value : '');

      bsEdit.show();
    });
  });

  // Guardar cambios desde el modal
  btnSave.addEventListener('click', function(){
    const id = editId.value;
    if (!id) return;

    const row = document.querySelector('tr[data-curso-id="'+id+'"]');
    if (!row) return;

    const nameSig   = 'cursos_sigla['+id+']';
    const nameDenom = 'cursos_denominacion['+id+']';
    const namePart  = 'cursos_participantes['+id+']';
    const nameDesde = 'cursos_desde['+id+']';
    const nameHasta = 'cursos_hasta['+id+']';
    const nameCum   = 'cursos_cumplio['+id+']';

    const rSig      = row.querySelector('input[name="'+nameSig+'"]');
    const rDenom    = row.querySelector('input[name="'+nameDenom+'"]');
    const rPart     = row.querySelector('input[name="'+namePart+'"]');
    const rDesde    = row.querySelector('input[name="'+nameDesde+'"]');
    const rHasta    = row.querySelector('input[name="'+nameHasta+'"]');
    const rCum      = row.querySelector('select[name="'+nameCum+'"]');
    const rPartText = row.querySelector('.participantes-text');

    if (rSig)   { rSig.value   = eSigla.value;   triggerInput(rSig); }
    if (rDenom) { rDenom.value = eDenom.value;   triggerInput(rDenom); }
    if (rDesde) { rDesde.value = eDesde.value;   triggerInput(rDesde); }
    if (rHasta) { rHasta.value = eHasta.value;   triggerInput(rHasta); }
    if (rCum)   {
      rCum.value = eCumplio.value;
      triggerInput(rCum);              // para autoguardado
      rCum.dispatchEvent(new Event('change', {bubbles:true})); // <- ACTUALIZA KPI
    }

    // Tomar los participantes del modal (uno por input) y pasarlos al hidden
    const parts = [];
    partList.querySelectorAll('.edit-participante-input').forEach(function(inp){
      const v = inp.value.trim();
      if (v !== '') parts.push(v);
    });

    const joined = parts.join('\n');

    if (rPart) {
      rPart.value = joined;
      triggerInput(rPart);   // para que salte el autoguardado
    }

    if (rPartText) {
      if (!parts.length) {
        rPartText.innerHTML = '<span class="text-secondary">Sin participantes</span>';
      } else {
        rPartText.innerHTML = parts
          .map(function(l){ return escapeHtml(l); })
          .join('<br>');
      }
    }

    row.classList.add('table-active');
    setTimeout(function(){ row.classList.remove('table-active'); }, 1500);

    bsEdit.hide();
  });
});
</script>
</body>
</html>
