<?php
// public/s1_documentos.php — Ficha individual: Datos personales / Administrativos / Sanidad / Rol de combate
declare(strict_types=1);

require_once __DIR__ . '/../auth/bootstrap.php';
require_login();
require_once __DIR__ . '/../config/db.php';

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$user = function_exists('current_user') ? current_user() : null;

function current_username_for_audit(): string {
    if (function_exists('current_user')) {
        $u = current_user();
        if (is_array($u)) {
            if (!empty($u['usuario']))         return (string)$u['usuario'];
            if (!empty($u['username']))        return (string)$u['username'];
            if (!empty($u['dni']))             return (string)$u['dni'];
            if (!empty($u['nombre_apellido'])) return (string)$u['nombre_apellido'];
        }
    }
    return 'web';
}

/* ===== Rutas / assets ===== */
$PUBLIC_URL   = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'])), '/');
$APP_URL      = rtrim(str_replace('\\','/', dirname($PUBLIC_URL)), '/');
$ASSETS_URL   = ($APP_URL === '' ? '' : $APP_URL) . '/assets';
$IMG_BG       = $ASSETS_URL . '/img/fondo.png';
$ESCUDO       = $ASSETS_URL . '/img/escudo_bcom602.png';
$PROJECT_BASE = realpath(__DIR__ . '/..'); // raíz del proyecto (para subir archivos)

$mensajeOk    = '';
$mensajeError = '';

/* ===== Modo búsqueda general (cuando NO viene id) ===== */
$id       = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$busqueda = trim((string)($_GET['q'] ?? ''));

if ($id <= 0) {
    // Listado básico de personal para elegir a quién ver
    $sql = "SELECT id, grado, arma, nombre_apellido, dni, destino
            FROM personal_unidad
            WHERE 1=1";
    $params = [];
    if ($busqueda !== '') {
        $sql .= " AND (nombre_apellido LIKE :q OR dni LIKE :q)";
        $params[':q'] = '%' . $busqueda . '%';
    }
    $sql .= " ORDER BY nombre_apellido";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $personas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <!doctype html>
    <html lang="es">
    <head>
    <meta charset="utf-8">
    <title>S-1 · Ficha de personal · Selección · Batallón de Comunicaciones 602</title>
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
      .container-main{ max-width:1200px; margin:auto; }
      .panel{
        background:rgba(15,17,23,.94);
        border:1px solid rgba(148,163,184,.40);
        border-radius:18px;
        padding:18px 22px 22px;
        box-shadow:0 18px 40px rgba(0,0,0,.75), inset 0 1px 0 rgba(255,255,255,.05);
      }
      .panel-title{ font-size:1.05rem; font-weight:800; margin-bottom:4px; }
      .panel-sub{ font-size:.86rem; color:#cbd5f5; margin-bottom:14px; }
      .brand-hero{
        padding-top:10px; padding-bottom:10px;
      }
      .brand-hero .hero-inner{
        align-items:center; display:flex; justify-content:space-between; gap:12px;
      }
      .header-back{
        margin-left:auto; margin-right:20px; margin-top:4px;
        display:flex; gap:8px;
      }
      .brand-title{ font-weight:800; font-size:1rem; }
      .brand-sub{ font-size:.8rem; color:#9ca3af; }
      .table-dark-custom{
        --bs-table-bg: rgba(15,23,42,.9);
        --bs-table-striped-bg: rgba(30,64,175,.25);
        --bs-table-border-color: rgba(148,163,184,.4);
        color:#e5e7eb;
      }
    </style>
    </head>
    <body>
    <header class="brand-hero">
      <div class="hero-inner container-main">
        <div class="d-flex align-items-center gap-3">
          <img class="brand-logo" src="<?= e($ESCUDO) ?>" alt="Escudo 602" style="height:52px; width:auto;">
          <div>
            <div class="brand-title">Batallón de Comunicaciones 602</div>
            <div class="brand-sub">S-1 · Ficha de personal (selección)</div>
          </div>
        </div>
        <div class="header-back">
          <a href="areas_s1.php" class="btn btn-secondary btn-sm" style="font-weight:600; padding:.35rem .9rem;">
            Volver a S-1
          </a>
        </div>
      </div>
    </header>

    <div class="page-wrap">
      <div class="container-main">
        <div class="panel">
          <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
            <div>
              <div class="panel-title">Seleccionar personal</div>
              <div class="panel-sub mb-0">
                Elegí a la persona para ver su ficha individual
                (datos personales, administrativos, sanidad y rol de combate).
              </div>
            </div>
            <form method="get" class="d-flex" style="max-width:320px;">
              <input type="text" name="q" class="form-control form-control-sm"
                     placeholder="Buscar por nombre o DNI..."
                     value="<?= e($busqueda) ?>">
              <button class="btn btn-sm btn-success ms-2" type="submit">Buscar</button>
            </form>
          </div>

          <div class="table-responsive mt-3">
            <table class="table table-sm table-dark table-striped table-dark-custom align-middle">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Grado</th>
                  <th>Arma</th>
                  <th>Nombre y Apellido</th>
                  <th>DNI</th>
                  <th>Destino interno</th>
                  <th class="text-end">Acciones</th>
                </tr>
              </thead>
              <tbody>
              <?php if(!$personas): ?>
                <tr>
                  <td colspan="7" class="text-center text-muted py-4">
                    No se encontraron registros. Ajustá la búsqueda.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach($personas as $i => $p): ?>
                  <tr>
                    <td><?= e($i+1) ?></td>
                    <td><?= e($p['grado'] ?? '') ?></td>
                    <td><?= e($p['arma'] ?? '') ?></td>
                    <td><?= e($p['nombre_apellido'] ?? '') ?></td>
                    <td><?= e($p['dni'] ?? '') ?></td>
                    <td><?= e($p['destino'] ?? '') ?></td>
                    <td class="text-end">
                      <a href="s1_documentos.php?id=<?= e($p['id']) ?>"
                         class="btn btn-sm btn-outline-info">
                        Ver ficha
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
              </tbody>
            </table>
          </div>

        </div>
      </div>
    </div>
    </body>
    </html>
    <?php
    exit;
}

/* ===== Desde acá: vista individual con id ===== */

try {

    // Datos de la persona
    $stmt = $pdo->prepare("SELECT * FROM personal_unidad WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $persona = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$persona) {
        throw new RuntimeException("No se encontró el registro de personal (ID={$id}).");
    }

    /* ==== Procesamiento POST para la persona seleccionada ==== */
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $accion    = $_POST['accion'] ?? '';
        $userAudit = current_username_for_audit();

        if ($accion === 'actualizar_personal') {
            // ===== Datos personales =====
            $grado   = trim((string)($_POST['grado'] ?? ''));
            $arma    = trim((string)($_POST['arma'] ?? ''));
            $nombre  = trim((string)($_POST['nombre_apellido'] ?? ''));
            $dni     = trim((string)($_POST['dni'] ?? ''));
            $cuil    = trim((string)($_POST['cuil'] ?? ''));
            $fn      = trim((string)($_POST['fecha_nacimiento'] ?? ''));
            $peso    = trim((string)($_POST['peso_kg'] ?? ''));
            $altura  = trim((string)($_POST['altura_cm'] ?? ''));
            $domicilio        = trim((string)($_POST['domicilio'] ?? ''));
            $destino          = trim((string)($_POST['destino'] ?? ''));
            $anios_en_destino = trim((string)($_POST['anios_en_destino'] ?? ''));

            // ===== Datos administrativos =====
            $nou           = trim((string)($_POST['nou'] ?? ''));
            $nro_cta_banco = trim((string)($_POST['nro_cta_banco'] ?? ''));
            $cbu_banco     = trim((string)($_POST['cbu_banco'] ?? ''));
            $alias_banco   = trim((string)($_POST['alias_banco'] ?? ''));

            // ===== Sanidad / Anexo 27 (se guarda en personal_unidad) =====
            $fecha_ultimo_anexo27 = trim((string)($_POST['fecha_ultimo_anexo27'] ?? ''));

            // ===== Observaciones generales =====
            $observaciones = trim((string)($_POST['observaciones'] ?? ''));

            if ($nombre === '') {
                throw new RuntimeException('El campo "Nombre y Apellido" es obligatorio.');
            }

            // Normalizar fecha nacimiento
            $fechaNac = null;
            if ($fn !== '') {
                $txt = str_replace(['/','.'], '-', $fn);
                $ts  = strtotime($txt);
                if ($ts !== false) {
                    $fechaNac = date('Y-m-d', $ts);
                }
            }

            // Normalizar fecha último Anexo 27
            $fechaAnexo27 = null;
            if ($fecha_ultimo_anexo27 !== '') {
                $txt = str_replace(['/','.'], '-', $fecha_ultimo_anexo27);
                $ts  = strtotime($txt);
                if ($ts !== false) {
                    $fechaAnexo27 = date('Y-m-d', $ts);
                }
            }

            $pesoKg    = ($peso === '' ? null : (float)$peso);
            $alturaCm  = ($altura === '' ? null : (int)$altura);
            $aniosDest = ($anios_en_destino === '' ? null : (float)$anios_en_destino);

            $sql = "UPDATE personal_unidad
                    SET grado = :grado,
                        arma  = :arma,
                        nombre_apellido = :nombre_apellido,
                        dni   = :dni,
                        cuil  = :cuil,
                        fecha_nacimiento = :fecha_nacimiento,
                        peso_kg = :peso_kg,
                        altura_cm = :altura_cm,
                        domicilio = :domicilio,
                        destino   = :destino,
                        anios_en_destino = :anios_en_destino,
                        nou = :nou,
                        nro_cta_banco = :nro_cta_banco,
                        cbu_banco = :cbu_banco,
                        alias_banco = :alias_banco,
                        fecha_ultimo_anexo27 = :fecha_ultimo_anexo27,
                        observaciones = :observaciones,
                        updated_at = NOW(),
                        updated_by = :updated_by
                    WHERE id = :id";
            $upd = $pdo->prepare($sql);
            $upd->execute([
                ':grado' => $grado !== '' ? $grado : null,
                ':arma'  => $arma  !== '' ? $arma  : null,
                ':nombre_apellido' => $nombre,
                ':dni'   => $dni   !== '' ? $dni   : null,
                ':cuil'  => $cuil  !== '' ? $cuil  : null,
                ':fecha_nacimiento' => $fechaNac,
                ':peso_kg' => $pesoKg,
                ':altura_cm' => $alturaCm,
                ':domicilio' => $domicilio !== '' ? $domicilio : null,
                ':destino'   => $destino   !== '' ? $destino   : null,
                ':anios_en_destino' => $aniosDest,
                ':nou'  => $nou !== '' ? $nou : null,
                ':nro_cta_banco' => $nro_cta_banco !== '' ? $nro_cta_banco : null,
                ':cbu_banco'     => $cbu_banco     !== '' ? $cbu_banco     : null,
                ':alias_banco'   => $alias_banco   !== '' ? $alias_banco   : null,
                ':fecha_ultimo_anexo27' => $fechaAnexo27,
                ':observaciones' => $observaciones !== '' ? $observaciones : null,
                ':updated_by'    => $userAudit,
                ':id'            => $id,
            ]);

            $mensajeOk = 'Datos del personal actualizados correctamente.';

            // refrescar datos en memoria
            $stmt = $pdo->prepare("SELECT * FROM personal_unidad WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $persona = $stmt->fetch(PDO::FETCH_ASSOC);

        } elseif ($accion === 'agregar_parte_enfermo') {
            // Sanidad: nuevo parte de enfermo
            $fi    = trim((string)($_POST['fecha_inicio'] ?? ''));
            $ff    = trim((string)($_POST['fecha_fin'] ?? ''));
            $motivo= trim((string)($_POST['motivo'] ?? ''));       // se guarda en diagnostico
            $obs   = trim((string)($_POST['observacion'] ?? ''));  // se guarda en detalle

            if ($fi === '') {
                throw new RuntimeException('La fecha de inicio del parte de enfermo es obligatoria.');
            }

            $fechaInicio = null;
            $fechaFin    = null;

            $txt = str_replace(['/','.'], '-', $fi);
            $ts  = strtotime($txt);
            if ($ts !== false) {
                $fechaInicio = date('Y-m-d', $ts);
            }

            if ($ff !== '') {
                $txt = str_replace(['/','.'], '-', $ff);
                $ts  = strtotime($txt);
                if ($ts !== false) {
                    $fechaFin = date('Y-m-d', $ts);
                }
            }

            $ins = $pdo->prepare(
                "INSERT INTO sanidad_partes_enfermo
                 (personal_id, fecha_inicio, fecha_fin, diagnostico, detalle, creado_en, creado_por)
                 VALUES (:pid, :fi, :ff, :diag, :detalle, NOW(), :user)"
            );
            $ins->execute([
                ':pid'    => $id,
                ':fi'     => $fechaInicio,
                ':ff'     => $fechaFin,
                ':diag'   => $motivo !== '' ? $motivo : null,
                ':detalle'=> $obs   !== '' ? $obs   : null,
                ':user'   => $userAudit,
            ]);

            $mensajeOk = 'Parte de enfermo cargado correctamente.';

        } elseif ($accion === 'agregar_doc_parte') {
            // Sanidad: documento asociado a parte de enfermo
            if (!isset($_FILES['archivo_parte']) || $_FILES['archivo_parte']['error'] === UPLOAD_ERR_NO_FILE) {
                throw new RuntimeException('Debe seleccionar un archivo de parte de enfermo.');
            }

            $file = $_FILES['archivo_parte'];

            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Error al subir el archivo (código ' . $file['error'] . ').');
            }

            $descDoc  = trim((string)($_POST['descripcion_doc_parte'] ?? 'Parte de enfermo'));
            $fechaDoc = trim((string)($_POST['fecha_doc_parte'] ?? ''));

            $fechaDocumento = null;
            if ($fechaDoc !== '') {
                $txt = str_replace(['/','.'], '-', $fechaDoc);
                $ts  = strtotime($txt);
                if ($ts !== false) {
                    $fechaDocumento = date('Y-m-d', $ts);
                }
            }

            // Carpeta de destino
            $uploadRel = 'storage/personal_sanidad';
            $uploadDir = $PROJECT_BASE . '/' . $uploadRel;
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0775, true);
            }

            $safeName = preg_replace('/[^A-Za-z0-9_\.-]/', '_', (string)$file['name']);
            if ($safeName === '') {
                $safeName = 'parte_enfermo.pdf';
            }

            $destRel  = $uploadRel . '/' . time() . '_' . $id . '_' . $safeName;
            $destPath = $PROJECT_BASE . '/' . $destRel;

            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                throw new RuntimeException('No se pudo mover el archivo subido.');
            }

            $hash = @sha1_file($destPath) ?: null;

            $ins = $pdo->prepare(
                "INSERT INTO personal_documentos
                 (personal_id, tipo, descripcion, nombre_archivo, ruta, hash_sha1, fecha_documento,
                  creado_en, creado_por)
                 VALUES (:pid, :tipo, :desc, :nombre, :ruta, :hash, :fecha_doc, NOW(), :creado_por)"
            );
            $ins->execute([
                ':pid'        => $id,
                ':tipo'       => 'parte_enfermo',
                ':desc'       => $descDoc !== '' ? $descDoc : 'Parte de enfermo',
                ':nombre'     => $file['name'],
                ':ruta'       => $destRel,
                ':hash'       => $hash,
                ':fecha_doc'  => $fechaDocumento,
                ':creado_por' => $userAudit,
            ]);

            $mensajeOk = 'Documento de parte de enfermo cargado correctamente.';
        }
    }

    /* ===== Sanidad: resumen partes de enfermo ===== */
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS cant,
                MAX(COALESCE(fecha_fin, fecha_inicio)) AS ult_parte
         FROM sanidad_partes_enfermo
         WHERE personal_id = :pid"
    );
    $stmt->execute([':pid' => $id]);
    $sanidadResumen = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['cant'=>0,'ult_parte'=>null];
    $tieneParte = ($sanidadResumen['cant'] ?? 0) > 0;

    /* ===== Documentos de sanidad (partes de enfermo) ===== */
    $docsStmt = $pdo->prepare(
        "SELECT id, descripcion, nombre_archivo, ruta, fecha_documento, creado_en
         FROM personal_documentos
         WHERE personal_id = :pid AND tipo = 'parte_enfermo'
         ORDER BY fecha_documento DESC, id DESC"
    );
    $docsStmt->execute([':pid' => $id]);
    $docsPartes = $docsStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $ex) {
    $mensajeError = $ex->getMessage();
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>S-1 · Ficha de personal · Batallón de Comunicaciones 602</title>
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
  }
  .panel-title{ font-size:1.05rem; font-weight:800; margin-bottom:4px; }
  .panel-sub{ font-size:.86rem; color:#cbd5f5; margin-bottom:14px; }
  .brand-hero{
    padding-top:10px; padding-bottom:10px;
  }
  .brand-hero .hero-inner{
    align-items:center; display:flex; justify-content:space-between; gap:12px;
  }
  .header-back{
    margin-left:auto; margin-right:20px; margin-top:4px;
    display:flex; gap:8px;
  }
  .brand-title{ font-weight:800; font-size:1rem; }
  .brand-sub{ font-size:.8rem; color:#9ca3af; }
  .section-title{
    font-size:.9rem;
    font-weight:700;
    margin-bottom:6px;
  }
  .section-sub{ font-size:.78rem; color:#9ca3af; margin-bottom:8px; }
  .card-subpanel{
    background:rgba(15,23,42,.96);
    border-radius:14px;
    border:1px solid rgba(148,163,184,.35);
    padding:10px 12px 12px;
    margin-bottom:10px;
  }
  .badge-pill{
    border-radius:999px;
    padding:.20rem .55rem;
    font-size:.72rem;
  }
  .rol-btn {
    font-size:.78rem;
    padding:.3rem .75rem;
  }
</style>
</head>
<body>

<header class="brand-hero">
  <div class="hero-inner container-main">
    <div class="d-flex align-items-center gap-3">
      <img class="brand-logo" src="<?= e($ESCUDO) ?>" alt="Escudo 602" style="height:52px; width:auto;">
      <div>
        <div class="brand-title">Batallón de Comunicaciones 602</div>
        <div class="brand-sub">S-1 · Ficha de personal (datos personales / administrativos / sanidad)</div>
      </div>
    </div>
    <div class="header-back">
      <a href="areas_s1.php" class="btn btn-secondary btn-sm" style="font-weight:600; padding:.35rem .9rem;">
        Volver a S-1
      </a>
      <a href="s1_personal.php" class="btn btn-outline-light btn-sm" style="font-weight:600; padding:.35rem .9rem;">
        Volver al listado de personal
      </a>
    </div>
  </div>
</header>

<div class="page-wrap">
  <div class="container-main">
    <div class="panel">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
          <div class="panel-title mb-1">
            Ficha individual · Datos personales / Administrativos / Sanidad
          </div>
          <div class="panel-sub mb-1">
            Los cambios se reflejan en el listado general de <code>personal_unidad</code>.
          </div>
          <?php if ($persona): ?>
          <div class="small text-muted">
            ID #<?= e($persona['id']) ?> · Última actualización:
            <?= isset($persona['updated_at']) && $persona['updated_at'] ? e($persona['updated_at']) : '—' ?>
            <?php if (!empty($persona['updated_by'])): ?>
              por <?= e($persona['updated_by']) ?>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
        <?php if ($persona): ?>
        <div class="d-flex flex-column align-items-end gap-1">
          <div class="small text-end">
            <strong><?= e($persona['grado'] ?? '') . ' ' . e($persona['arma'] ?? '') ?></strong><br>
            <?= e($persona['nombre_apellido'] ?? '') ?>
          </div>
          <a href="s1_rol_combate.php?id=<?= e($persona['id']) ?>"
             class="btn btn-warning btn-sm rol-btn">
            Ver Rol de combate
          </a>
        </div>
        <?php endif; ?>
      </div>

      <?php if ($mensajeOk !== ''): ?>
        <div class="alert alert-success py-2 mt-2"><?= e($mensajeOk) ?></div>
      <?php endif; ?>
      <?php if ($mensajeError !== ''): ?>
        <div class="alert alert-danger py-2 mt-2"><?= e($mensajeError) ?></div>
      <?php endif; ?>

      <?php if ($persona): ?>

      <div class="row g-3 mt-2">
        <!-- Columna izquierda: datos personales + administrativos + observaciones -->
        <div class="col-lg-7">
          <form method="post" class="mb-0">
            <?php if (function_exists('csrf_input')) csrf_input(); ?>
            <input type="hidden" name="accion" value="actualizar_personal">

            <div class="card-subpanel mb-3">
              <div class="section-title">Datos personales</div>
              <div class="row g-2">
                <div class="col-md-3">
                  <label class="form-label form-label-sm">Grado</label>
                  <input type="text" name="grado" class="form-control form-control-sm"
                         value="<?= e($persona['grado'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                  <label class="form-label form-label-sm">Arma</label>
                  <input type="text" name="arma" class="form-control form-control-sm"
                         value="<?= e($persona['arma'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label form-label-sm">Nombre y Apellido *</label>
                  <input type="text" name="nombre_apellido" required
                         class="form-control form-control-sm"
                         value="<?= e($persona['nombre_apellido'] ?? '') ?>">
                </div>

                <div class="col-md-3">
                  <label class="form-label form-label-sm">DNI</label>
                  <input type="text" name="dni" class="form-control form-control-sm"
                         value="<?= e($persona['dni'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                  <label class="form-label form-label-sm">CUIL</label>
                  <input type="text" name="cuil" class="form-control form-control-sm"
                         value="<?= e($persona['cuil'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                  <label class="form-label form-label-sm">Fecha nacimiento</label>
                  <input type="date" name="fecha_nacimiento" class="form-control form-control-sm"
                         value="<?= isset($persona['fecha_nacimiento']) && $persona['fecha_nacimiento'] ? e($persona['fecha_nacimiento']) : '' ?>">
                </div>
                <div class="col-md-3">
                  <label class="form-label form-label-sm">Peso (kg)</label>
                  <input type="number" step="0.01" name="peso_kg"
                         class="form-control form-control-sm"
                         value="<?= e($persona['peso_kg'] ?? '') ?>">
                </div>

                <div class="col-md-3">
                  <label class="form-label form-label-sm">Altura (cm)</label>
                  <input type="number" name="altura_cm"
                         class="form-control form-control-sm"
                         value="<?= e($persona['altura_cm'] ?? '') ?>">
                </div>
                <div class="col-md-9">
                  <label class="form-label form-label-sm">Domicilio</label>
                  <input type="text" name="domicilio" class="form-control form-control-sm"
                         value="<?= e($persona['domicilio'] ?? '') ?>">
                </div>

                <div class="col-md-6">
                  <label class="form-label form-label-sm">Destino interno (compañía / área)</label>
                  <input type="text" name="destino" class="form-control form-control-sm"
                         value="<?= e($persona['destino'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label form-label-sm">Años en destino</label>
                  <input type="number" step="0.1" name="anios_en_destino"
                         class="form-control form-control-sm"
                         value="<?= e($persona['anios_en_destino'] ?? '') ?>">
                </div>
              </div>
            </div>

            <div class="card-subpanel mb-3">
              <div class="section-title">Datos administrativos / bancarios</div>
              <div class="row g-2">
                <div class="col-md-3">
                  <label class="form-label form-label-sm">NOU</label>
                  <input type="text" name="nou" class="form-control form-control-sm"
                         value="<?= e($persona['nou'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                  <label class="form-label form-label-sm">Nro. cuenta banco</label>
                  <input type="text" name="nro_cta_banco" class="form-control form-control-sm"
                         value="<?= e($persona['nro_cta_banco'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                  <label class="form-label form-label-sm">CBU banco</label>
                  <input type="text" name="cbu_banco" class="form-control form-control-sm"
                         value="<?= e($persona['cbu_banco'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                  <label class="form-label form-label-sm">Alias banco</label>
                  <input type="text" name="alias_banco" class="form-control form-control-sm"
                         value="<?= e($persona['alias_banco'] ?? '') ?>">
                </div>
              </div>
            </div>

            <div class="card-subpanel mb-3">
              <div class="section-title">Sanidad · Resumen personal</div>
              <div class="row g-2">
                <div class="col-md-4">
                  <label class="form-label form-label-sm d-block">Tiene parte de enfermo</label>
                  <div class="small">
                    <?php if ($tieneParte): ?>
                      <span class="badge bg-success badge-pill">Sí</span>
                    <?php else: ?>
                      <span class="badge bg-secondary badge-pill">No</span>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="col-md-4">
                  <label class="form-label form-label-sm">Cant. de partes</label>
                  <div class="small">
                    <span class="badge bg-info badge-pill">
                      <?= e($sanidadResumen['cant'] ?? 0) ?>
                    </span>
                  </div>
                </div>
                <div class="col-md-4">
                  <label class="form-label form-label-sm">Fecha último parte</label>
                  <div class="small">
                    <span class="badge bg-dark badge-pill">
                      <?= isset($sanidadResumen['ult_parte']) && $sanidadResumen['ult_parte']
                          ? date('d/m/Y', strtotime($sanidadResumen['ult_parte']))
                          : '—'; ?>
                    </span>
                  </div>
                </div>

                <div class="col-md-4 mt-2">
                  <label class="form-label form-label-sm">Fecha último Anexo 27</label>
                  <input type="date" name="fecha_ultimo_anexo27"
                         class="form-control form-control-sm"
                         value="<?= isset($persona['fecha_ultimo_anexo27']) && $persona['fecha_ultimo_anexo27'] ? e($persona['fecha_ultimo_anexo27']) : '' ?>">
                </div>
              </div>
            </div>

            <div class="card-subpanel mb-3">
              <div class="section-title">Observaciones generales</div>
              <textarea name="observaciones" rows="3"
                        class="form-control form-control-sm"
                        placeholder="Observaciones generales sobre el personal (documentación, situación, sanidad, etc.)."><?= e($persona['observaciones'] ?? '') ?></textarea>
            </div>

            <div class="text-end">
              <button type="submit" class="btn btn-success btn-sm">
                Guardar cambios generales
              </button>
            </div>
          </form>
        </div>

        <!-- Columna derecha: sanidad / partes de enfermo + documentos -->
        <div class="col-lg-5">
          <div class="card-subpanel mb-3">
            <div class="section-title">Sanidad · Partes de enfermo</div>
            <div class="section-sub">
              Gestión de partes de enfermo individuales. Al cargar un parte se actualiza el resumen de arriba.
            </div>

            <div class="d-flex flex-wrap gap-2 mb-2 small">
              <div>
                <span class="text-muted">Tiene parte de enfermo:</span>
                <?php if ($tieneParte): ?>
                  <span class="badge bg-success badge-pill">Sí</span>
                <?php else: ?>
                  <span class="badge bg-secondary badge-pill">No</span>
                <?php endif; ?>
              </div>
              <div>
                <span class="text-muted">Cant. de partes:</span>
                <span class="badge bg-info badge-pill">
                  <?= e($sanidadResumen['cant'] ?? 0) ?>
                </span>
              </div>
              <div>
                <span class="text-muted">Último parte:</span>
                <span class="badge bg-dark badge-pill">
                  <?= isset($sanidadResumen['ult_parte']) && $sanidadResumen['ult_parte']
                      ? date('d/m/Y', strtotime($sanidadResumen['ult_parte']))
                      : '—'; ?>
                </span>
              </div>
            </div>

            <hr class="border-secondary-subtle my-2">

            <!-- Nuevo parte de enfermo -->
            <form method="post" class="mt-2">
              <?php if (function_exists('csrf_input')) csrf_input(); ?>
              <input type="hidden" name="accion" value="agregar_parte_enfermo">

              <div class="small text-muted mb-2">
                Cargar nuevo parte de enfermo
              </div>

              <div class="row g-2 mb-2">
                <div class="col-md-6">
                  <label class="form-label form-label-sm">Fecha inicio *</label>
                  <input type="date" name="fecha_inicio" class="form-control form-control-sm" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label form-label-sm">Fecha fin</label>
                  <input type="date" name="fecha_fin" class="form-control form-control-sm">
                </div>
              </div>

              <div class="mb-2">
                <label class="form-label form-label-sm">Motivo</label>
                <input type="text" name="motivo" class="form-control form-control-sm"
                       placeholder="Ej: Enfermedad común, accidente, etc.">
              </div>

              <div class="mb-2">
                <label class="form-label form-label-sm">Observación</label>
                <textarea name="observacion" rows="3"
                          class="form-control form-control-sm"
                          placeholder="Detalle de la afección, indicaciones médicas, etc."></textarea>
              </div>

              <div class="text-end">
                <button type="submit" class="btn btn-outline-info btn-sm">
                  Registrar parte de enfermo
                </button>
              </div>
            </form>
          </div>

          <div class="card-subpanel mb-3">
            <div class="section-title">Sanidad · Documentos de partes</div>
            <div class="section-sub">
              Subir documentos asociados a partes de enfermo (certificados, Anexo 27, etc.).
            </div>

            <!-- Subir documento -->
            <form method="post" enctype="multipart/form-data" class="mb-3">
              <?php if (function_exists('csrf_input')) csrf_input(); ?>
              <input type="hidden" name="accion" value="agregar_doc_parte">

              <div class="row g-2 mb-2">
                <div class="col-md-6">
                  <label class="form-label form-label-sm">Fecha del documento</label>
                  <input type="date" name="fecha_doc_parte"
                         class="form-control form-control-sm">
                </div>
                <div class="col-md-6">
                  <label class="form-label form-label-sm">Descripción</label>
                  <input type="text" name="descripcion_doc_parte"
                         class="form-control form-control-sm"
                         placeholder="Ej: Certificado médico, Anexo 27">
                </div>
              </div>

              <div class="mb-2">
                <label class="form-label form-label-sm">Archivo</label>
                <input type="file" name="archivo_parte" class="form-control form-control-sm" required>
                <div class="form-text text-muted">
                  Sugerido: PDF o imagen. Se guarda en el legajo de sanidad del personal.
                </div>
              </div>

              <div class="text-end">
                <button type="submit" class="btn btn-outline-success btn-sm">
                  Subir documento
                </button>
              </div>
            </form>

            <?php if ($docsPartes): ?>
              <div class="small text-muted mb-1">Documentos cargados:</div>
              <ul class="list-group list-group-flush small">
                <?php foreach ($docsPartes as $d): ?>
                  <li class="list-group-item bg-transparent text-light border-secondary-subtle py-1 px-2">
                    <div class="d-flex justify-content-between align-items-start">
                      <div>
                        <strong><?= e($d['descripcion'] ?? $d['nombre_archivo']) ?></strong><br>
                        <span class="text-muted">
                          <?= isset($d['fecha_documento']) && $d['fecha_documento']
                              ? 'Fecha doc: ' . date('d/m/Y', strtotime($d['fecha_documento']))
                              : 'Fecha doc: —' ?>
                          &nbsp; · &nbsp;
                          <?= isset($d['creado_en']) && $d['creado_en']
                              ? 'Cargado: ' . e($d['creado_en'])
                              : '' ?>
                        </span>
                      </div>
                      <?php if (!empty($d['ruta'])): ?>
                        <div>
                          <a href="../<?= e($d['ruta']) ?>" target="_blank"
                             class="btn btn-sm btn-outline-light py-0 px-2">
                            Ver
                          </a>
                        </div>
                      <?php endif; ?>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <div class="small text-muted">
                Aún no hay documentos de partes de enfermo cargados para esta persona.
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <?php endif; ?>

    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
