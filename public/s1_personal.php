<?php
// public/s1_personal.php — Gestión del personal de la unidad (tabla personal_unidad)
declare(strict_types=1);

require_once __DIR__ . '/../auth/bootstrap.php';
require_login();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$user = function_exists('current_user') ? current_user() : null;

/**
 * Intenta obtener un identificador corto del usuario logueado para updated_by
 */
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

/* ===== Assets & rutas ===== */
$PUBLIC_URL = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'])), '/');
$APP_URL    = rtrim(str_replace('\\','/', dirname($PUBLIC_URL)), '/');
$ASSETS_URL = ($APP_URL === '' ? '' : $APP_URL) . '/assets';
$IMG_BG     = $ASSETS_URL . '/img/fondo.png';
$ESCUDO     = $ASSETS_URL . '/img/escudo_bcom602.png';

$mensajeOk = '';
$mensajeError = '';

/* ===== Procesamiento de acciones (POST) ===== */
$accion = $_POST['accion'] ?? '';

try {
    if ($accion === 'subir_excel') {
        if (!isset($_FILES['archivo_excel']) || $_FILES['archivo_excel']['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('No se recibió el archivo Excel o hubo un error en la subida.');
        }

        $fileTmp  = $_FILES['archivo_excel']['tmp_name'];
        $fileName = $_FILES['archivo_excel']['name'] ?? 'listado.xlsx';
        $ext      = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (!in_array($ext, ['xls','xlsx'], true)) {
            throw new RuntimeException('El archivo debe ser Excel (.xls o .xlsx).');
        }

        // Cargamos el Excel con PhpSpreadsheet
        $spreadsheet = IOFactory::load($fileTmp);
        $sheet = $spreadsheet->getActiveSheet();

        $pdo->beginTransaction();

        // Usamos ON DUPLICATE KEY UPDATE por DNI para actualizar sin duplicar.
        $highestRow = $sheet->getHighestRow();

        $sqlInsert = "INSERT INTO personal_unidad
            (grado, arma, nombre_apellido, dni, fecha_nacimiento,
             peso_kg, altura_cm, destino, updated_at, updated_by)
            VALUES (:grado, :arma, :nombre_apellido, :dni, :fecha_nacimiento,
                    :peso_kg, :altura_cm, :destino, NOW(), :updated_by)
            ON DUPLICATE KEY UPDATE
                grado           = VALUES(grado),
                arma            = VALUES(arma),
                nombre_apellido = VALUES(nombre_apellido),
                fecha_nacimiento= VALUES(fecha_nacimiento),
                peso_kg         = VALUES(peso_kg),
                altura_cm       = VALUES(altura_cm),
                destino         = VALUES(destino),
                updated_at      = NOW(),
                updated_by      = VALUES(updated_by)";

        $stmtIns = $pdo->prepare($sqlInsert);
        $userAudit = current_username_for_audit();
        $procesadas = 0;

        for ($row = 2; $row <= $highestRow; $row++) {
            $grado   = trim((string)$sheet->getCell("A{$row}")->getValue());
            $arma    = trim((string)$sheet->getCell("B{$row}")->getValue());
            $nombre  = trim((string)$sheet->getCell("C{$row}")->getValue());
            $dni     = trim((string)$sheet->getCell("D{$row}")->getValue());
            $fnRaw   = $sheet->getCell("E{$row}")->getValue();
            $peso    = trim((string)$sheet->getCell("F{$row}")->getValue());
            $altura  = trim((string)$sheet->getCell("G{$row}")->getValue());
            $destino = trim((string)$sheet->getCell("H{$row}")->getValue());

            // Si no hay nombre, se asume que la fila está vacía
            if ($nombre === '') {
                continue;
            }

            // Fecha de nacimiento: puede venir como serial de Excel o texto
            $fechaNac = null;
            if ($fnRaw !== null && $fnRaw !== '') {
                if (is_numeric($fnRaw)) {
                    $dt = ExcelDate::excelToDateTimeObject((float)$fnRaw);
                    $fechaNac = $dt->format('Y-m-d');
                } else {
                    // Intenta parsear un texto tipo dd/mm/yyyy o yyyy-mm-dd
                    $txt = str_replace(['/','.'], '-', (string)$fnRaw);
                    $ts  = strtotime($txt);
                    if ($ts !== false) {
                        $fechaNac = date('Y-m-d', $ts);
                    }
                }
            }

            // Peso y altura
            $pesoKg   = ($peso === '' ? null : (float)$peso);
            $alturaCm = ($altura === '' ? null : (int)$altura);

            $stmtIns->execute([
                ':grado'           => $grado !== '' ? $grado : null,
                ':arma'            => $arma  !== '' ? $arma  : null,
                ':nombre_apellido' => $nombre,
                ':dni'             => $dni   !== '' ? $dni   : null,
                ':fecha_nacimiento'=> $fechaNac,
                ':peso_kg'         => $pesoKg,
                ':altura_cm'       => $alturaCm,
                ':destino'         => $destino !== '' ? $destino : null,
                ':updated_by'      => $userAudit,
            ]);

            $procesadas++;
        }

        $pdo->commit();
        $mensajeOk = "Importación completada. Filas procesadas: {$procesadas}. (Se actualiza por DNI, sin duplicar personal)";

    } elseif ($accion === 'guardar_nuevo') {
        $grado   = trim((string)($_POST['grado'] ?? ''));
        $arma    = trim((string)($_POST['arma'] ?? ''));
        $nombre  = trim((string)($_POST['nombre_apellido'] ?? ''));
        $dni     = trim((string)($_POST['dni'] ?? ''));
        $fn      = trim((string)($_POST['fecha_nacimiento'] ?? ''));
        $peso    = trim((string)($_POST['peso_kg'] ?? ''));
        $altura  = trim((string)($_POST['altura_cm'] ?? ''));
        $destino = trim((string)($_POST['destino'] ?? ''));

        if ($nombre === '') {
            throw new RuntimeException('El campo "Nombre y Apellido" es obligatorio.');
        }

        $fechaNac = null;
        if ($fn !== '') {
            $txt = str_replace(['/','.'], '-', $fn);
            $ts  = strtotime($txt);
            if ($ts !== false) {
                $fechaNac = date('Y-m-d', $ts);
            }
        }

        $pesoKg   = ($peso === '' ? null : (float)$peso);
        $alturaCm = ($altura === '' ? null : (int)$altura);
        $userAudit = current_username_for_audit();

        $sql = "INSERT INTO personal_unidad
                (grado, arma, nombre_apellido, dni, fecha_nacimiento,
                 peso_kg, altura_cm, destino, updated_at, updated_by)
                VALUES (:grado, :arma, :nombre_apellido, :dni, :fecha_nacimiento,
                        :peso_kg, :altura_cm, :destino, NOW(), :updated_by)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':grado'           => $grado !== '' ? $grado : null,
            ':arma'            => $arma  !== '' ? $arma  : null,
            ':nombre_apellido' => $nombre,
            ':dni'             => $dni   !== '' ? $dni   : null,
            ':fecha_nacimiento'=> $fechaNac,
            ':peso_kg'         => $pesoKg,
            ':altura_cm'       => $alturaCm,
            ':destino'         => $destino !== '' ? $destino : null,
            ':updated_by'      => $userAudit,
        ]);

        $mensajeOk = 'Personal agregado correctamente.';

    } elseif ($accion === 'guardar_edicion') {
        $id      = (int)($_POST['id'] ?? 0);
        $grado   = trim((string)($_POST['grado'] ?? ''));
        $arma    = trim((string)($_POST['arma'] ?? ''));
        $nombre  = trim((string)($_POST['nombre_apellido'] ?? ''));
        $dni     = trim((string)($_POST['dni'] ?? ''));
        $fn      = trim((string)($_POST['fecha_nacimiento'] ?? ''));
        $peso    = trim((string)($_POST['peso_kg'] ?? ''));
        $altura  = trim((string)($_POST['altura_cm'] ?? ''));
        $destino = trim((string)($_POST['destino'] ?? ''));

        if ($id <= 0) {
            throw new RuntimeException('ID inválido para edición.');
        }
        if ($nombre === '') {
            throw new RuntimeException('El campo "Nombre y Apellido" es obligatorio.');
        }

        $fechaNac = null;
        if ($fn !== '') {
            $txt = str_replace(['/','.'], '-', $fn);
            $ts  = strtotime($txt);
            if ($ts !== false) {
                $fechaNac = date('Y-m-d', $ts);
            }
        }

        $pesoKg   = ($peso === '' ? null : (float)$peso);
        $alturaCm = ($altura === '' ? null : (int)$altura);
        $userAudit = current_username_for_audit();

        $sql = "UPDATE personal_unidad
                SET grado = :grado,
                    arma  = :arma,
                    nombre_apellido = :nombre_apellido,
                    dni   = :dni,
                    fecha_nacimiento = :fecha_nacimiento,
                    peso_kg   = :peso_kg,
                    altura_cm = :altura_cm,
                    destino   = :destino,
                    updated_at= NOW(),
                    updated_by= :updated_by
                WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':grado'           => $grado !== '' ? $grado : null,
            ':arma'            => $arma  !== '' ? $arma  : null,
            ':nombre_apellido' => $nombre,
            ':dni'             => $dni   !== '' ? $dni   : null,
            ':fecha_nacimiento'=> $fechaNac,
            ':peso_kg'         => $pesoKg,
            ':altura_cm'       => $alturaCm,
            ':destino'         => $destino !== '' ? $destino : null,
            ':updated_by'      => $userAudit,
            ':id'              => $id,
        ]);

        $mensajeOk = 'Datos del personal actualizados correctamente.';

    } elseif ($accion === 'borrar_individual') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            throw new RuntimeException('ID inválido para eliminar.');
        }
        $stmt = $pdo->prepare("DELETE FROM personal_unidad WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $mensajeOk = 'Registro de personal eliminado correctamente.';

    } elseif ($accion === 'borrar_todo') {
        // Eliminar TODOS los registros de personal_unidad
        $pdo->exec("TRUNCATE TABLE personal_unidad");
        $mensajeOk = 'Se eliminó la lista completa de personal (tabla vacía).';
    }

} catch (Throwable $ex) {
    $mensajeError = $ex->getMessage();
}

/* ===== Filtros GET ===== */
$busqueda   = trim((string)($_GET['q'] ?? ''));
$f_destino  = trim((string)($_GET['destino'] ?? ''));

/* ===== Destinos para filtro ===== */
$destinosStmt = $pdo->query("SELECT DISTINCT destino FROM personal_unidad WHERE destino IS NOT NULL AND destino <> '' ORDER BY destino");
$destinos = $destinosStmt->fetchAll(PDO::FETCH_COLUMN);

/* ===== Listado principal ===== */
$listadoError = '';
$filas = [];

try {
    $sql = "SELECT
                pu.id,
                pu.grado,
                pu.arma,
                pu.nombre_apellido,
                pu.dni,
                pu.fecha_nacimiento,
                pu.peso_kg,
                pu.altura_cm,
                pu.destino,
                pu.anios_en_destino,
                pu.fecha_ultimo_anexo27,
                pu.observaciones,
                pu.updated_at,
                pu.updated_by,
                (SELECT COUNT(*) 
                   FROM sanidad_partes_enfermo spe
                  WHERE spe.personal_id = pu.id
                ) AS partes_total,
                (SELECT COUNT(*)
                   FROM sanidad_partes_enfermo spe
                  WHERE spe.personal_id = pu.id
                    AND (spe.fecha_fin IS NULL OR spe.fecha_fin >= CURDATE())
                ) AS partes_vigentes
            FROM personal_unidad pu
            WHERE 1=1";
    $params = [];

    if ($busqueda !== '') {
        $sql .= " AND (pu.nombre_apellido LIKE :q OR pu.dni LIKE :q)";
        $params[':q'] = '%' . $busqueda . '%';
    }

    if ($f_destino !== '') {
        $sql .= " AND pu.destino = :destino";
        $params[':destino'] = $f_destino;
    }

    /* ORDEN por escalafón argentino */
    $sql .= " ORDER BY
      CASE pu.grado
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
        WHEN 'VN' THEN 23

        -- Agente civil
        WHEN 'A/C' THEN 24

        ELSE 999
      END,
      pu.nombre_apellido";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $ex) {
    $listadoError = $ex->getMessage();
    $filas = [];
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>S-1 Personal · Gestión de personal de la unidad · Batallón de Comunicaciones 602</title>
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

  .panel-title{
    font-size:1.05rem;
    font-weight:800;
    margin-bottom:4px;
  }

  .panel-sub{
    font-size:.86rem;
    color:#cbd5f5;
    margin-bottom:14px;
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
    margin-left:auto;
    margin-right:20px;
    margin-top:4px;
    display:flex;
    gap:8px;
  }

  .brand-title{
    font-weight:800;
    font-size:1rem;
  }
  .brand-sub{
    font-size:.8rem;
    color:#9ca3af;
  }

  .table-dark-custom {
    --bs-table-bg: rgba(15,23,42,.9);
    --bs-table-striped-bg: rgba(30,64,175,.25);
    --bs-table-border-color: rgba(148,163,184,.4);
    color:#e5e7eb;
  }

  .search-box{
    max-width:320px;
  }

  .filter-box{
    max-width:260px;
  }

  .form-label{
    font-size:.8rem;
  }

  .modal-content{
    background:rgba(15,23,42,.98);
    color:#e5e7eb;
    border-radius:16px;
    border:1px solid rgba(148,163,184,.6);
  }
  .modal-header{
    border-bottom:1px solid rgba(55,65,81,.9);
  }
  .modal-footer{
    border-top:1px solid rgba(55,65,81,.9);
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
        <div class="brand-sub">“Hogar de las Comunicaciones Fijas del Ejército”</div>
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
          <div class="panel-title">S-1 · Personal de la unidad (tabla <code>personal_unidad</code>)</div>
          <div class="panel-sub mb-0">
            Gestión centralizada del personal (grado, arma, destino, datos físicos y resumen de sanidad/documentación).
          </div>
        </div>

        <div class="d-flex flex-column flex-sm-row gap-2">

          <!-- Filtro destino -->
          <form method="get" class="d-flex filter-box">
            <select name="destino" class="form-select form-select-sm">
              <option value="">Todos los destinos</option>
              <?php foreach ($destinos as $dest): ?>
                <option value="<?= e($dest) ?>" <?= $f_destino === $dest ? 'selected' : '' ?>><?= e($dest) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if ($busqueda !== ''): ?>
              <input type="hidden" name="q" value="<?= e($busqueda) ?>">
            <?php endif; ?>
            <button class="btn btn-sm btn-outline-light ms-2" type="submit">Filtrar</button>
          </form>

          <!-- Búsqueda -->
          <form method="get" class="d-flex search-box">
            <input
              type="text"
              name="q"
              class="form-control form-control-sm"
              placeholder="Buscar por nombre o DNI..."
              value="<?= e($busqueda) ?>"
            >
            <?php if ($f_destino !== ''): ?>
              <input type="hidden" name="destino" value="<?= e($f_destino) ?>">
            <?php endif; ?>
            <button class="btn btn-sm btn-success ms-2" type="submit">Buscar</button>
          </form>

        </div>
      </div>

      <!-- Alertas -->
      <?php if ($mensajeOk !== ''): ?>
        <div class="alert alert-success py-2"><?= e($mensajeOk) ?></div>
      <?php endif; ?>
      <?php if ($mensajeError !== ''): ?>
        <div class="alert alert-danger py-2"><?= e($mensajeError) ?></div>
      <?php endif; ?>
      <?php if ($listadoError !== ''): ?>
        <div class="alert alert-danger py-2">
          Error al obtener el listado de personal: <code><?= e($listadoError) ?></code>
        </div>
      <?php endif; ?>

      <div class="d-flex justify-content-between flex-wrap gap-2 mb-2">
        <div class="small text-muted">
          Registros mostrados: <?= e(count($filas)) ?>
          <?= $f_destino   !== '' ? ' | Destino: '.e($f_destino) : '' ?>
          <?= $busqueda    !== '' ? ' | Búsqueda: "'.e($busqueda).'"' : '' ?>
        </div>
        <div class="d-flex gap-2">
          <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#modalNuevo">
            + Nuevo personal
          </button>
          <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#modalExcel">
            Importar / Actualizar desde Excel
          </button>

          <!-- Botón: Eliminar lista completa -->
          <form method="post" onsubmit="return confirm('¿Eliminar TODOS los registros de personal? Esta acción no se puede deshacer.');">
            <?php if (function_exists('csrf_input')) csrf_input(); ?>
            <input type="hidden" name="accion" value="borrar_todo">
            <button type="submit" class="btn btn-sm btn-outline-danger">
              Eliminar lista completa
            </button>
          </form>
        </div>
      </div>

      <div class="table-responsive mt-2">
        <table class="table table-sm table-dark table-striped table-dark-custom align-middle">
          <thead>
            <tr>
              <th scope="col">#</th>
              <th scope="col">Grado</th>
              <th scope="col">Arma</th>
              <th scope="col">Nombre y Apellido</th>
              <th scope="col">DNI</th>
              <th scope="col">F. Nac.</th>
              <th scope="col">Peso (kg)</th>
              <th scope="col">Altura (cm)</th>
              <th scope="col">Destino</th>
              <th scope="col">Años dest.</th>
              <th scope="col">Últ. Anexo 27</th>
              <th scope="col">Partes (tot/vig)</th>
              <th scope="col" class="text-end">Acciones</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$filas): ?>
            <tr>
              <td colspan="13" class="text-center text-muted py-4">
                No se encontraron registros de personal.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($filas as $idx => $row): ?>
              <?php
                $fn = $row['fecha_nacimiento'] ?? null;
                $fnFmt = $fn ? date('d/m/Y', strtotime($fn)) : '';

                $aniosDest = $row['anios_en_destino'] ?? null;
                $anx       = $row['fecha_ultimo_anexo27'] ?? null;
                $anxFmt    = $anx ? date('d/m/Y', strtotime($anx)) : '';

                $pt = (int)($row['partes_total'] ?? 0);
                $pv = (int)($row['partes_vigentes'] ?? 0);

                $classParts = 'badge bg-secondary';
                if ($pv > 0) {
                    $classParts = 'badge bg-danger';
                } elseif ($pt > 0) {
                    $classParts = 'badge bg-warning text-dark';
                } else {
                    $classParts = 'badge bg-success';
                }
              ?>
              <tr>
                <td><?= e($idx + 1) ?></td>
                <td><?= e($row['grado'] ?? '') ?></td>
                <td><?= e($row['arma'] ?? '') ?></td>
                <td><?= e($row['nombre_apellido'] ?? '') ?></td>
                <td><?= e($row['dni'] ?? '') ?></td>
                <td><?= e($fnFmt) ?></td>
                <td><?= e($row['peso_kg'] !== null ? $row['peso_kg'] : '') ?></td>
                <td><?= e($row['altura_cm'] !== null ? $row['altura_cm'] : '') ?></td>
                <td><?= e($row['destino'] ?? '') ?></td>
                <td><?= $aniosDest !== null ? e((string)$aniosDest) : '' ?></td>
                <td><?= $anxFmt !== '' ? e($anxFmt) : '' ?></td>
                <td>
                  <span class="<?= e($classParts) ?>">
                    <?= e($pt . ' / ' . $pv) ?>
                  </span>
                </td>
                <td class="text-end">
                  <div class="d-inline-flex gap-1">
                    <!-- Ficha -->
                    <a
                      href="s1_documentos.php?id=<?= e($row['id']) ?>"
                      class="btn btn-sm btn-outline-info"
                    >
                      Ficha
                    </a>

                    <!-- Editar -->
                    <button
                      type="button"
                      class="btn btn-sm btn-outline-light btn-edit"
                      data-id="<?= e($row['id']) ?>"
                      data-grado="<?= e($row['grado'] ?? '') ?>"
                      data-arma="<?= e($row['arma'] ?? '') ?>"
                      data-nombre="<?= e($row['nombre_apellido'] ?? '') ?>"
                      data-dni="<?= e($row['dni'] ?? '') ?>"
                      data-fecha_nac="<?= e($fn ?: '') ?>"
                      data-peso="<?= e($row['peso_kg'] !== null ? $row['peso_kg'] : '') ?>"
                      data-altura="<?= e($row['altura_cm'] !== null ? $row['altura_cm'] : '') ?>"
                      data-destino="<?= e($row['destino'] ?? '') ?>"
                    >
                      Editar
                    </button>

                    <!-- Eliminar -->
                    <form method="post" class="d-inline"
                          onsubmit="return confirm('¿Eliminar este registro de personal?');">
                      <?php if (function_exists('csrf_input')) csrf_input(); ?>
                      <input type="hidden" name="accion" value="borrar_individual">
                      <input type="hidden" name="id" value="<?= e($row['id']) ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger">
                        Eliminar
                      </button>
                    </form>
                  </div>
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

<!-- MODAL: Nuevo personal -->
<div class="modal fade" id="modalNuevo" tabindex="-1" aria-labelledby="modalNuevoLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="accion" value="guardar_nuevo">
        <?php if (function_exists('csrf_input')) csrf_input(); ?>
        <div class="modal-header">
          <h5 class="modal-title" id="modalNuevoLabel">Nuevo personal</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div class="row g-2">
            <div class="col-md-3">
              <label class="form-label">Grado</label>
              <input type="text" name="grado" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
              <label class="form-label">Arma</label>
              <input type="text" name="arma" class="form-control form-control-sm">
            </div>
            <div class="col-md-6">
              <label class="form-label">Nombre y Apellido *</label>
              <input type="text" name="nombre_apellido" class="form-control form-control-sm" required>
            </div>

            <div class="col-md-3">
              <label class="form-label">DNI</label>
              <input type="text" name="dni" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
              <label class="form-label">Fecha nacimiento</label>
              <input type="date" name="fecha_nacimiento" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
              <label class="form-label">Peso (kg)</label>
              <input type="number" step="0.01" name="peso_kg" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
              <label class="form-label">Altura (cm)</label>
              <input type="number" name="altura_cm" class="form-control form-control-sm">
            </div>

            <div class="col-md-6">
              <label class="form-label">Destino interno (área/compañía)</label>
              <input type="text" name="destino" class="form-control form-control-sm">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-success btn-sm">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- MODAL: Editar personal -->
<div class="modal fade" id="modalEditar" tabindex="-1" aria-labelledby="modalEditarLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="accion" value="guardar_edicion">
        <input type="hidden" name="id" id="editId">
        <?php if (function_exists('csrf_input')) csrf_input(); ?>
        <div class="modal-header">
          <h5 class="modal-title" id="modalEditarLabel">Editar personal</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div class="row g-2">
            <div class="col-md-3">
              <label class="form-label">Grado</label>
              <input type="text" name="grado" id="editGrado" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
              <label class="form-label">Arma</label>
              <input type="text" name="arma" id="editArma" class="form-control form-control-sm">
            </div>
            <div class="col-md-6">
              <label class="form-label">Nombre y Apellido *</label>
              <input type="text" name="nombre_apellido" id="editNombre" class="form-control form-control-sm" required>
            </div>

            <div class="col-md-3">
              <label class="form-label">DNI</label>
              <input type="text" name="dni" id="editDni" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
              <label class="form-label">Fecha nacimiento</label>
              <input type="date" name="fecha_nacimiento" id="editFechaNac" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
              <label class="form-label">Peso (kg)</label>
              <input type="number" step="0.01" name="peso_kg" id="editPeso" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
              <label class="form-label">Altura (cm)</label>
              <input type="number" name="altura_cm" id="editAltura" class="form-control form-control-sm">
            </div>

            <div class="col-md-6">
              <label class="form-label">Destino interno (área/compañía)</label>
              <input type="text" name="destino" id="editDestino" class="form-control form-control-sm">
            </div>

          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-success btn-sm">Guardar cambios</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- MODAL: Importar Excel -->
<div class="modal fade" id="modalExcel" tabindex="-1" aria-labelledby="modalExcelLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="accion" value="subir_excel">
        <?php if (function_exists('csrf_input')) csrf_input(); ?>
        <div class="modal-header">
          <h5 class="modal-title" id="modalExcelLabel">Importar / Actualizar listado desde Excel</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <p class="small text-muted">
            El archivo debe ser .xls o .xlsx.  
            Se espera el siguiente orden de columnas (fila 1 = encabezados, datos desde la fila 2):
          </p>
          <ul class="small">
            <li>A: Grado</li>
            <li>B: Arma</li>
            <li>C: Nombre y Apellido</li>
            <li>D: DNI (se usa para evitar duplicados y actualizar datos)</li>
            <li>E: Fecha nacimiento</li>
            <li>F: Peso (kg)</li>
            <li>G: Altura (cm)</li>
            <li>H: Destino interno</li>
          </ul>
          <div class="mb-2">
            <input type="file" name="archivo_excel" class="form-control form-control-sm" accept=".xls,.xlsx" required>
          </div>
          <p class="small text-warning mb-0">
            Si un DNI ya existe en la base, los datos se actualizan en lugar de crear un registro nuevo.
          </p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-info btn-sm">Importar / Actualizar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Completar modal de edición con los data-* del botón
document.querySelectorAll('.btn-edit').forEach(btn => {
  btn.addEventListener('click', () => {
    const modal = document.getElementById('modalEditar');

    document.getElementById('editId').value        = btn.dataset.id || '';
    document.getElementById('editGrado').value     = btn.dataset.grado || '';
    document.getElementById('editArma').value      = btn.dataset.arma || '';
    document.getElementById('editNombre').value    = btn.dataset.nombre || '';
    document.getElementById('editDni').value       = btn.dataset.dni || '';

    const fn = btn.dataset.fechaNac || btn.dataset.fecha_nac || '';
    document.getElementById('editFechaNac').value  = fn;

    document.getElementById('editPeso').value      = btn.dataset.peso || '';
    document.getElementById('editAltura').value    = btn.dataset.altura || '';
    document.getElementById('editDestino').value   = btn.dataset.destino || '';

    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
  });
});
</script>
</body>
</html>
