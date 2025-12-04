<?php
// public/save_s3_educacion.php
declare(strict_types=1);

require_once __DIR__ . '/../auth/bootstrap.php';
require_login();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/s3_educacion_tables_helper.php';

/** @var PDO $pdo */
s3_ensure_tables($pdo);

if (function_exists('csrf_verify')) {
    csrf_verify();
}

// Sección: 'clases', 'trabajos', 'alocuciones', 'cursos'
$section = $_POST['section'] ?? '';
$validSections = ['clases', 'trabajos', 'alocuciones', 'cursos'];

if (!in_array($section, $validSections, true)) {
    http_response_code(400);
    echo 'Sección inválida';
    exit;
}

// Autosave solo se usa en CLASES (via fetch)
$isAutosave  = ($section === 'clases')
    && isset($_POST['autosave'])
    && $_POST['autosave'] === '1';
$handleFiles = !$isAutosave;

/**
 * Carpeta base para evidencias de S-3 Educación
 * → /var/www/html/inspeccion/storage/s3_educacion
 * En la BD se guarda algo como: storage/s3_educacion/...
 */
$BASE_REL = 'storage/s3_educacion';
$BASE_DIR = realpath(__DIR__ . '/../' . $BASE_REL);

if ($BASE_DIR === false) {
    $BASE_DIR = __DIR__ . '/../' . $BASE_REL;
    if (!is_dir($BASE_DIR)) {
        @mkdir($BASE_DIR, 0775, true);
    }
}

// Subcarpetas específicas dentro de storage/s3_educacion
$CLASES_DOC_SUBDIR   = 'clases_docs';
$CLASES_PDF_SUBDIR   = 'clases_participantes';
$TRABAJOS_SUBDIR     = 'trabajos_docs';
$ALOC_SUBDIR         = 'alocuciones_docs';
$CURSOS_DOC_SUBDIR   = 'cursos_docs';
$CURSOS_PDF_SUBDIR   = 'cursos_participantes';

foreach ([$CLASES_DOC_SUBDIR, $CLASES_PDF_SUBDIR, $TRABAJOS_SUBDIR, $ALOC_SUBDIR, $CURSOS_DOC_SUBDIR, $CURSOS_PDF_SUBDIR] as $sub) {
    if (!is_dir($BASE_DIR . '/' . $sub)) {
        @mkdir($BASE_DIR . '/' . $sub, 0775, true);
    }
}

$pdo->beginTransaction();

try {

    /* =========================================================
     * SECCIÓN CLASES
     * =======================================================*/
    if ($section === 'clases') {

        // Arrays con los datos enviados
        $semana       = $_POST['clases_semana']        ?? [];
        $fecha        = $_POST['clases_fecha']         ?? [];
        $claseTrab    = $_POST['clases_clase_trabajo'] ?? [];
        $tema         = $_POST['clases_tema']          ?? [];
        $responsable  = $_POST['clases_responsable']   ?? [];
        $lugar        = $_POST['clases_lugar']         ?? [];
        $cumplio      = $_POST['clases_cumplio']       ?? [];

        $docActual    = $_POST['clases_doc_actual']    ?? [];
        $pdfActual    = $_POST['clases_pdf_actual']    ?? [];

        $ids = array_keys($semana);

        $sql = "UPDATE s3_clases
                SET
                  semana            = :semana,
                  fecha             = :fecha,
                  clase_trabajo     = :clase_trabajo,
                  tema              = :tema,
                  responsable       = :responsable,
                  lugar             = :lugar,
                  cumplio           = :cumplio,
                  documento         = :documento,
                  participantes_pdf = :participantes_pdf
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

            // Rutas actuales que vienen del hidden
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

                    $destRel = $BASE_REL . '/' . $CLASES_DOC_SUBDIR . '/' . $safeName;
                    $destAbs = $BASE_DIR . '/' . $CLASES_DOC_SUBDIR . '/' . $safeName;

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

                    $destRel = $BASE_REL . '/' . $CLASES_PDF_SUBDIR . '/' . $safeName;
                    $destAbs = $BASE_DIR . '/' . $CLASES_PDF_SUBDIR . '/' . $safeName;

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
    }

    /* =========================================================
     * SECCIÓN TRABAJOS DE GABINETE
     * =======================================================*/
    if ($section === 'trabajos') {

        $tgCumplio = $_POST['tg_cumplio'] ?? [];
        $tgDoc     = $_POST['tg_doc']     ?? [];

        $ids = array_keys($tgCumplio);

        $sqlTrab = "UPDATE s3_trabajos_gabinete
                    SET cumplio = :cumplio,
                        documento = :documento
                    WHERE id = :id";
        $stmtTrab = $pdo->prepare($sqlTrab);

        foreach ($ids as $id) {
            $idInt = (int)$id;
            if ($idInt <= 0) {
                continue;
            }

            $cum    = trim((string)($tgCumplio[$id] ?? ''));
            $docStr = trim((string)($tgDoc[$id]     ?? ''));

            $docPath = $docStr;

            if ($handleFiles &&
                isset($_FILES['tg_doc_file']['error'][$id]) &&
                $_FILES['tg_doc_file']['error'][$id] === UPLOAD_ERR_OK) {

                $origName = (string)$_FILES['tg_doc_file']['name'][$id];
                $tmpName  = (string)$_FILES['tg_doc_file']['tmp_name'][$id];

                $safeName = 'trabajo_'.$idInt.'_doc_'.time().'_'.
                    preg_replace('/[^A-Za-z0-9_.-]/','_', basename($origName));

                $destRel = $BASE_REL . '/' . $TRABAJOS_SUBDIR . '/' . $safeName;
                $destAbs = $BASE_DIR . '/' . $TRABAJOS_SUBDIR . '/' . $safeName;

                if (move_uploaded_file($tmpName, $destAbs)) {
                    $docPath = $destRel;
                }
            }

            $stmtTrab->execute([
                ':cumplio'   => $cum !== '' ? $cum : null,
                ':documento' => $docPath !== '' ? $docPath : null,
                ':id'        => $idInt,
            ]);
        }

        $pdo->commit();

        header('Location: s3_educacion_trabajos.php?saved=1');
        exit;
    }

    /* =========================================================
     * SECCIÓN ALOCUCIONES
     * =======================================================*/
    if ($section === 'alocuciones') {

        $alocCumplio = $_POST['aloc_cumplio'] ?? [];
        $alocDoc     = $_POST['aloc_doc']     ?? [];

        $ids = array_keys($alocCumplio);

        $sqlAloc = "UPDATE s3_alocuciones
                    SET cumplio = :cumplio,
                        documento = :documento
                    WHERE id = :id";
        $stmtAloc = $pdo->prepare($sqlAloc);

        foreach ($ids as $id) {
            $idInt = (int)$id;
            if ($idInt <= 0) continue;

            $cum    = trim((string)($alocCumplio[$id] ?? ''));
            $docStr = trim((string)($alocDoc[$id]     ?? ''));
            $docPath = $docStr;

            if ($handleFiles &&
                isset($_FILES['aloc_doc_file']['error'][$id]) &&
                $_FILES['aloc_doc_file']['error'][$id] === UPLOAD_ERR_OK) {

                $origName = (string)$_FILES['aloc_doc_file']['name'][$id];
                $tmpName  = (string)$_FILES['aloc_doc_file']['tmp_name'][$id];

                $safeName = 'aloc_'.$idInt.'_doc_'.time().'_'.
                    preg_replace('/[^A-Za-z0-9_.-]/','_', basename($origName));

                $destRel = $BASE_REL . '/' . $ALOC_SUBDIR . '/' . $safeName;
                $destAbs = $BASE_DIR . '/' . $ALOC_SUBDIR . '/' . $safeName;

                if (move_uploaded_file($tmpName, $destAbs)) {
                    $docPath = $destRel;
                }
            }

            $stmtAloc->execute([
                ':cumplio'   => $cum !== '' ? $cum : null,
                ':documento' => $docPath !== '' ? $docPath : null,
                ':id'        => $idInt,
            ]);
        }

        $pdo->commit();
        header('Location: s3_educacion_alocuciones.php?saved=1');
        exit;
    }

    /* =========================================================
     * SECCIÓN CURSOS REGULARES
     * =======================================================*/
    if ($section === 'cursos') {

        $cursoSigla   = $_POST['curso_sigla']         ?? [];
        $cursoDenom   = $_POST['curso_denominacion']  ?? [];
        $cursoDesde   = $_POST['curso_desde']         ?? [];
        $cursoHasta   = $_POST['curso_hasta']         ?? [];
        $cursoCumplio = $_POST['curso_cumplio']       ?? [];
        $cursoDoc     = $_POST['curso_doc']           ?? [];
        $pdfActual    = $_POST['cursos_pdf_actual']   ?? [];

        $ids = array_keys($cursoCumplio);

        $sqlCurso = "UPDATE s3_cursos_regulares
                     SET sigla             = :sigla,
                         denominacion      = :denominacion,
                         desde             = :desde,
                         hasta             = :hasta,
                         cumplio           = :cumplio,
                         documento         = :documento,
                         participantes_pdf = :participantes_pdf
                     WHERE id = :id";

        $stmtCurso = $pdo->prepare($sqlCurso);

        foreach ($ids as $id) {
            $idInt = (int)$id;
            if ($idInt <= 0) continue;

            $sigla  = trim((string)($cursoSigla[$id]        ?? ''));
            $denom  = trim((string)($cursoDenom[$id]        ?? ''));
            $desdeS = trim((string)($cursoDesde[$id]        ?? ''));
            $hastaS = trim((string)($cursoHasta[$id]        ?? ''));
            $cum    = trim((string)($cursoCumplio[$id]      ?? ''));
            $docStr = trim((string)($cursoDoc[$id]          ?? ''));
            $pdfStr = trim((string)($pdfActual[$id]         ?? ''));

            $desdeDb = null;
            if ($desdeS !== '') {
                $ts = strtotime(str_replace(['/','.'], '-', $desdeS));
                if ($ts !== false) {
                    $desdeDb = date('Y-m-d', $ts);
                }
            }

            $hastaDb = null;
            if ($hastaS !== '') {
                $ts = strtotime(str_replace(['/','.'], '-', $hastaS));
                if ($ts !== false) {
                    $hastaDb = date('Y-m-d', $ts);
                }
            }

            $docPath = $docStr;
            $pdfPath = $pdfStr;

            if ($handleFiles) {
                // Documento general del curso
                if (isset($_FILES['curso_doc_file']['error'][$id]) &&
                    $_FILES['curso_doc_file']['error'][$id] === UPLOAD_ERR_OK) {

                    $origName = (string)$_FILES['curso_doc_file']['name'][$id];
                    $tmpName  = (string)$_FILES['curso_doc_file']['tmp_name'][$id];

                    $safeName = 'curso_'.$idInt.'_doc_'.time().'_'.
                        preg_replace('/[^A-Za-z0-9_.-]/','_', basename($origName));

                    $destRel = $BASE_REL . '/' . $CURSOS_DOC_SUBDIR . '/' . $safeName;
                    $destAbs = $BASE_DIR . '/' . $CURSOS_DOC_SUBDIR . '/' . $safeName;

                    if (move_uploaded_file($tmpName, $destAbs)) {
                        $docPath = $destRel;
                    }
                }

                // PDF de participantes
                if (isset($_FILES['cursos_pdf']['error'][$id]) &&
                    $_FILES['cursos_pdf']['error'][$id] === UPLOAD_ERR_OK) {

                    $origName = (string)$_FILES['cursos_pdf']['name'][$id];
                    $tmpName  = (string)$_FILES['cursos_pdf']['tmp_name'][$id];

                    $safeName = 'curso_'.$idInt.'_part_'.time().'_'.
                        preg_replace('/[^A-Za-z0-9_.-]/','_', basename($origName));

                    $destRel = $BASE_REL . '/' . $CURSOS_PDF_SUBDIR . '/' . $safeName;
                    $destAbs = $BASE_DIR . '/' . $CURSOS_PDF_SUBDIR . '/' . $safeName;

                    if (move_uploaded_file($tmpName, $destAbs)) {
                        $pdfPath = $destRel;
                    }
                }
            }

            $stmtCurso->execute([
                ':sigla'             => $sigla !== '' ? $sigla : null,
                ':denominacion'      => $denom !== '' ? $denom : null,
                ':desde'             => $desdeDb,
                ':hasta'             => $hastaDb,
                ':cumplio'           => $cum !== '' ? $cum : null,
                ':documento'         => $docPath !== '' ? $docPath : null,
                ':participantes_pdf' => $pdfPath !== '' ? $pdfPath : null,
                ':id'                => $idInt,
            ]);
        }

        $pdo->commit();
        header('Location: s3_educacion_cursos.php?saved=1');
        exit;
    }

} catch (Throwable $e) {
    $pdo->rollBack();

    if ($isAutosave && $section === 'clases') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        exit;
    }

    echo "Error al guardar: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    exit;
}
