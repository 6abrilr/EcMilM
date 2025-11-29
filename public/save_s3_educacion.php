<?php
// public/save_s3_educacion.php
declare(strict_types=1);

require_once __DIR__ . '/../auth/bootstrap.php';
require_login();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/s3_educacion_tables_helper.php';

s3_ensure_tables($pdo);

if (function_exists('csrf_verify')) {
    csrf_verify();
}

$section = $_POST['section'] ?? '';
if ($section !== 'clases') {
    http_response_code(400);
    echo 'Sección inválida';
    exit;
}

// Si viene desde autosave (fetch) no redirigimos, respondemos JSON
$isAutosave  = isset($_POST['autosave']) && $_POST['autosave'] === '1';
$handleFiles = !$isAutosave;

/**
 * Carpeta base para evidencias / PDFs de participantes.
 * Cambiá "storage/s3_educacion" si usás otra ruta.
 */
$BASE_REL = 'storage/s3_educacion';
$BASE_DIR = realpath(__DIR__ . '/../' . $BASE_REL);

if ($BASE_DIR === false) {
    $BASE_DIR = __DIR__ . '/../' . $BASE_REL;
    if (!is_dir($BASE_DIR)) {
        mkdir($BASE_DIR, 0775, true);
    }
}

// Subcarpetas
$DOC_SUBDIR = 'clases_docs';
$PDF_SUBDIR = 'clases_participantes';

if (!is_dir($BASE_DIR . '/' . $DOC_SUBDIR)) {
    @mkdir($BASE_DIR . '/' . $DOC_SUBDIR, 0775, true);
}
if (!is_dir($BASE_DIR . '/' . $PDF_SUBDIR)) {
    @mkdir($BASE_DIR . '/' . $PDF_SUBDIR, 0775, true);
}

// Arrays con los datos enviados
$semana       = $_POST['clases_semana']       ?? [];
$fecha        = $_POST['clases_fecha']        ?? [];
$claseTrab    = $_POST['clases_clase_trabajo']?? [];
$tema         = $_POST['clases_tema']         ?? [];
$responsable  = $_POST['clases_responsable']  ?? [];
$lugar        = $_POST['clases_lugar']        ?? [];
$cumplio      = $_POST['clases_cumplio']      ?? [];

$docActual    = $_POST['clases_doc_actual']   ?? [];
$pdfActual    = $_POST['clases_pdf_actual']   ?? [];

$ids = array_keys($semana);

$pdo->beginTransaction();

try {
    $sql = "UPDATE s3_clases
            SET
              semana           = :semana,
              fecha            = :fecha,
              clase_trabajo    = :clase_trabajo,
              tema             = :tema,
              responsable      = :responsable,
              lugar            = :lugar,
              cumplio          = :cumplio,
              documento        = :documento,
              participantes_pdf= :participantes_pdf
            WHERE id = :id";

    $stmt = $pdo->prepare($sql);

    foreach ($ids as $id) {
        $idInt = (int)$id;
        if ($idInt <= 0) {
            continue;
        }

        // Campos de texto
        $sem   = trim((string)($semana[$id]      ?? ''));
        $fec   = trim((string)($fecha[$id]       ?? ''));
        $cl    = trim((string)($claseTrab[$id]   ?? ''));
        $tm    = trim((string)($tema[$id]        ?? ''));
        $resp  = trim((string)($responsable[$id] ?? ''));
        $lug   = trim((string)($lugar[$id]       ?? ''));
        $cum   = trim((string)($cumplio[$id]     ?? ''));

        // Normalizar fecha (YYYY-MM-DD o vacío)
        $fecDb = null;
        if ($fec !== '') {
            $ts = strtotime(str_replace(['/','.'], '-', $fec));
            if ($ts !== false) {
                $fecDb = date('Y-m-d', $ts);
            }
        }

        // Documento actual
        $docPath = (string)($docActual[$id] ?? '');
        $pdfPath = (string)($pdfActual[$id] ?? '');

        // ==== Manejo de archivos SOLO si no es autosave ====
        if ($handleFiles) {
            // Documento principal
            if (isset($_FILES['clases_file']['error'][$id]) &&
                $_FILES['clases_file']['error'][$id] === UPLOAD_ERR_OK) {

                $origName = (string)$_FILES['clases_file']['name'][$id];
                $tmpName  = (string)$_FILES['clases_file']['tmp_name'][$id];

                $safeName = 'clase_'.$idInt.'_doc_'.time().'_'.
                    preg_replace('/[^A-Za-z0-9_.-]/','_', basename($origName));

                $destRel = $BASE_REL . '/' . $DOC_SUBDIR . '/' . $safeName;
                $destAbs = $BASE_DIR . '/' . $DOC_SUBDIR . '/' . $safeName;

                if (move_uploaded_file($tmpName, $destAbs)) {
                    $docPath = $destRel;
                }
            }

            // PDF de participantes
            if (isset($_FILES['clases_pdf']['error'][$id]) &&
                $_FILES['clases_pdf']['error'][$id] === UPLOAD_ERR_OK) {

                $origName = (string)$_FILES['clases_pdf']['name'][$id];
                $tmpName  = (string)$_FILES['clases_pdf']['tmp_name'][$id];

                $safeName = 'clase_'.$idInt.'_part_'.time().'_'.
                    preg_replace('/[^A-Za-z0-9_.-]/','_', basename($origName));

                $destRel = $BASE_REL . '/' . $PDF_SUBDIR . '/' . $safeName;
                $destAbs = $BASE_DIR . '/' . $PDF_SUBDIR . '/' . $safeName;

                if (move_uploaded_file($tmpName, $destAbs)) {
                    $pdfPath = $destRel;
                }
            }
        }

        $stmt->execute([
            ':semana'            => $sem !== '' ? $sem : null,
            ':fecha'             => $fecDb,
            ':clase_trabajo'     => $cl !== '' ? $cl : null,
            ':tema'              => $tm !== '' ? $tm : null,
            ':responsable'       => $resp !== '' ? $resp : null,
            ':lugar'             => $lug !== '' ? $lug : null,
            ':cumplio'           => $cum !== '' ? $cum : null,
            ':documento'         => $docPath !== '' ? $docPath : null,
            ':participantes_pdf' => $pdfPath !== '' ? $pdfPath : null,
            ':id'                => $idInt,
        ]);
    }

    $pdo->commit();

    if ($isAutosave) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true]);
        exit;
    }

    header('Location: s3_educacion_clases.php?saved=1');
    exit;

} catch (Throwable $e) {
    $pdo->rollBack();

    if ($isAutosave) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        exit;
    }

    echo "Error al guardar: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    exit;
}
