<?php
// public/s1_personal.php — Gestión del personal de la unidad (tabla personal_unidad)
declare(strict_types=1);

require_once __DIR__ . '/../auth/bootstrap.php';
require_login();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$user = function_exists('current_user') ? current_user() : null;

/** @var PDO $pdo */
// $pdo viene desde config/db.php; si por algún motivo no está, armamos uno de respaldo:
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $dsn   = 'mysql:host=127.0.0.1;dbname=inspecciones;charset=utf8mb4';
    $userDb = 'root';
    $passDb = '';
    $pdo = new PDO($dsn, $userDb, $passDb, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
}

/**
 * Identificador corto del usuario logueado para updated_by
 */
function current_username_for_audit(): string {
    if (function_exists('current_user')) {
        $u = current_user();
        if (is_array($u)) {
            if (!empty($u['usuario']))         return (string)$u['usuario'];
            if (!empty($u['username']))        return (string)$u['username'];
            if (!empty($u['dni']))             return (string)$u['dni'];
            if (!empty($u['nombre_apellido'])) return (string)$u['nombre_apellido'];
            if (!empty($u['apellido_nombre'])) return (string)$u['apellido_nombre'];
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

/* Ruta base del proyecto y archivo Excel maestro */
$PROJECT_BASE = realpath(__DIR__ . '/..');
$MASTER_XLSX  = $PROJECT_BASE . '/storage/evidencias/Lista Personal B COM 602.xlsx';

$mensajeOk    = '';
$mensajeError = '';

/* ===== Helper fechas Excel/texto → Y-m-d ===== */
function parse_excel_or_text_date($value): ?string {
    if ($value === null || $value === '') {
        return null;
    }
    if (is_numeric($value)) {
        try {
            $dt = ExcelDate::excelToDateTimeObject((float)$value);
            return $dt->format('Y-m-d');
        } catch (Throwable $e) {
            // sigue abajo al parseo por texto
        }
    }
    $txt = str_replace(['/', '.'], '-', (string)$value);
    $ts  = strtotime($txt);
    if ($ts !== false) {
        return date('Y-m-d', $ts);
    }
    return null;
}

/* =========================================================
 *  Procesamiento de acciones (POST)
 * ======================================================= */
$accion = $_POST['accion'] ?? '';

try {
    if ($accion === 'subir_excel') {
        /* ---------- Importar / actualizar desde Excel ---------- */
        if (!isset($_FILES['archivo_excel']) || $_FILES['archivo_excel']['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('No se recibió el archivo Excel o hubo un error en la subida.');
        }

        $fileTmp  = $_FILES['archivo_excel']['tmp_name'];
        $fileName = $_FILES['archivo_excel']['name'] ?? 'listado.xlsx';
        $ext      = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (!in_array($ext, ['xls','xlsx'], true)) {
            throw new RuntimeException('El archivo debe ser Excel (.xls o .xlsx).');
        }

        /**
         * ORDEN ACTUAL DEL EXCEL (según lo que pasaste):
         *
         * A: Grado
         * B: Arma/Espec
         * C: Apellido y Nombre
         * D: DNI
         * E: CUIL
         * F: Fecha nac
         * G: Peso
         * H: Altura
         * I: Sexo
         * J: Domicilio
         * K: Estado civil
         * L: Hijos
         * M: NOU
         * N: Nro Cta
         * O: CBU
         * P: ALIAS (por ahora se ignora aquí; se puede cargar luego en ficha individual)
         * Q: Fecha último Anexo 27
         * R: Tiene parte de enfermo
         * S: Parte enfermo desde
         * T: Parte enfermo hasta
         * U: Cantidad de partes de enfermo
         * V: Destino interno
         * W: Rol
         * X: Años en destino
         * Y: Fracc
         * Z: Observaciones
         *
         * Clave única: DNI (uq_personal_unidad_dni)
         */

        $spreadsheet = IOFactory::load($fileTmp);
        $sheet       = $spreadsheet->getActiveSheet();
        $highestRow  = $sheet->getHighestRow();

        $sqlInsert = "INSERT INTO personal_unidad
            (
                grado,
                arma_espec,
                apellido_nombre,
                dni,
                cuil,
                fecha_nac,
                peso,
                altura,
                sexo,
                domicilio,
                estado_civil,
                hijos,
                nou,
                nro_cta,
                cbu,
                fecha_ultimo_anexo27,
                tiene_parte_enfermo,
                parte_enfermo_desde,
                parte_enfermo_hasta,
                cantidad_parte_enfermo,
                destino_interno,
                rol,
                anios_en_destino,
                fracc,
                observaciones,
                updated_at,
                updated_by
            )
            VALUES
            (
                :grado,
                :arma_espec,
                :apellido_nombre,
                :dni,
                :cuil,
                :fecha_nac,
                :peso,
                :altura,
                :sexo,
                :domicilio,
                :estado_civil,
                :hijos,
                :nou,
                :nro_cta,
                :cbu,
                :fecha_ultimo_anexo27,
                :tiene_parte_enfermo,
                :parte_enfermo_desde,
                :parte_enfermo_hasta,
                :cantidad_parte_enfermo,
                :destino_interno,
                :rol,
                :anios_en_destino,
                :fracc,
                :observaciones,
                NOW(),
                :updated_by
            )
            ON DUPLICATE KEY UPDATE
                grado                = VALUES(grado),
                arma_espec           = VALUES(arma_espec),
                apellido_nombre      = VALUES(apellido_nombre),
                cuil                 = VALUES(cuil),
                fecha_nac            = VALUES(fecha_nac),
                peso                 = VALUES(peso),
                altura               = VALUES(altura),
                sexo                 = VALUES(sexo),
                domicilio            = VALUES(domicilio),
                estado_civil         = VALUES(estado_civil),
                hijos                = VALUES(hijos),
                nou                  = VALUES(nou),
                nro_cta              = VALUES(nro_cta),
                cbu                  = VALUES(cbu),
                fecha_ultimo_anexo27 = VALUES(fecha_ultimo_anexo27),
                tiene_parte_enfermo  = VALUES(tiene_parte_enfermo),
                parte_enfermo_desde  = VALUES(parte_enfermo_desde),
                parte_enfermo_hasta  = VALUES(parte_enfermo_hasta),
                cantidad_parte_enfermo = VALUES(cantidad_parte_enfermo),
                destino_interno      = VALUES(destino_interno),
                rol                  = VALUES(rol),
                anios_en_destino     = VALUES(anios_en_destino),
                fracc                = VALUES(fracc),
                observaciones        = VALUES(observaciones),
                updated_at           = NOW(),
                updated_by           = VALUES(updated_by)";

        $pdo->beginTransaction();
        $stmtIns   = $pdo->prepare($sqlInsert);
        $userAudit = current_username_for_audit();
        $procesadas = 0;

        for ($row = 2; $row <= $highestRow; $row++) {
            $grado          = trim((string)$sheet->getCell("A{$row}")->getValue());
            $armaEspec      = trim((string)$sheet->getCell("B{$row}")->getValue());
            $apellidoNombre = trim((string)$sheet->getCell("C{$row}")->getValue());
            $dni            = trim((string)$sheet->getCell("D{$row}")->getValue());
            $cuil           = trim((string)$sheet->getCell("E{$row}")->getValue());
            $fnRaw          = $sheet->getCell("F{$row}")->getValue();
            $pesoRaw        = $sheet->getCell("G{$row}")->getValue();
            $alturaRaw      = $sheet->getCell("H{$row}")->getValue();
            $sexo           = trim((string)$sheet->getCell("I{$row}")->getValue());
            $domicilio      = trim((string)$sheet->getCell("J{$row}")->getValue());
            $estadoCivil    = trim((string)$sheet->getCell("K{$row}")->getValue());
            $hijosRaw       = $sheet->getCell("L{$row}")->getValue();
            $nou            = trim((string)$sheet->getCell("M{$row}")->getValue());
            $nroCta         = trim((string)$sheet->getCell("N{$row}")->getValue());
            $cbu            = trim((string)$sheet->getCell("O{$row}")->getValue());
            $aliasExcel     = trim((string)$sheet->getCell("P{$row}")->getValue()); // por ahora ignorado aquí
            $anexoRaw       = $sheet->getCell("Q{$row}")->getValue();
            $tieneParteRaw  = trim((string)$sheet->getCell("R{$row}")->getValue());
            $parteDesdeRaw  = $sheet->getCell("S{$row}")->getValue();
            $parteHastaRaw  = $sheet->getCell("T{$row}")->getValue();
            $cantPartesRaw  = $sheet->getCell("U{$row}")->getValue();
            $destinoInterno = trim((string)$sheet->getCell("V{$row}")->getValue());
            $rol            = trim((string)$sheet->getCell("W{$row}")->getValue());
            $aniosRaw       = $sheet->getCell("X{$row}")->getValue();
            $fracc          = trim((string)$sheet->getCell("Y{$row}")->getValue());
            $obs            = trim((string)$sheet->getCell("Z{$row}")->getValue());

            // Si la fila está vacía (sin nombre ni DNI ni grado) la salteamos
            if ($apellidoNombre === '' && $dni === '' && $grado === '') {
                continue;
            }

            $fechaNac   = parse_excel_or_text_date($fnRaw);
            $fechaAnx   = parse_excel_or_text_date($anexoRaw);
            $parteDesde = parse_excel_or_text_date($parteDesdeRaw);
            $parteHasta = parse_excel_or_text_date($parteHastaRaw);

            $peso   = ($pesoRaw === null || $pesoRaw === '') ? null : (float)$pesoRaw;
            $altura = ($alturaRaw === null || $alturaRaw === '') ? null : (float)$alturaRaw;
            $hijos  = ($hijosRaw === null || $hijosRaw === '') ? null : (int)$hijosRaw;
            $aniosEnDestino = ($aniosRaw === null || $aniosRaw === '') ? null : (float)$aniosRaw;

            $tieneParte = null;
            if ($tieneParteRaw !== '') {
                $t = mb_strtoupper($tieneParteRaw, 'UTF-8');
                if (in_array($t, ['SI','S','1'], true)) {
                    $tieneParte = 1;
                } elseif (in_array($t, ['NO','N','0'], true)) {
                    $tieneParte = 0;
                }
            }

            if ($tieneParte === null) {
                $tieneParte = 0;
            }

            $cantPartes = ($cantPartesRaw === null || $cantPartesRaw === '') ? null : (int)$cantPartesRaw;

            $stmtIns->execute([
                ':grado'               => $grado !== '' ? $grado : null,
                ':arma_espec'          => $armaEspec !== '' ? $armaEspec : null,
                ':apellido_nombre'     => $apellidoNombre,
                ':dni'                 => $dni !== '' ? $dni : null,
                ':cuil'                => $cuil !== '' ? $cuil : null,
                ':fecha_nac'           => $fechaNac,
                ':peso'                => $peso,
                ':altura'              => $altura,
                ':sexo'                => $sexo !== '' ? $sexo : null,
                ':domicilio'           => $domicilio !== '' ? $domicilio : null,
                ':estado_civil'        => $estadoCivil !== '' ? $estadoCivil : null,
                ':hijos'               => $hijos,
                ':nou'                 => $nou !== '' ? $nou : null,
                ':nro_cta'             => $nroCta !== '' ? $nroCta : null,
                ':cbu'                 => $cbu !== '' ? $cbu : null,
                ':fecha_ultimo_anexo27'=> $fechaAnx,
                ':tiene_parte_enfermo' => $tieneParte,
                ':parte_enfermo_desde' => $parteDesde,
                ':parte_enfermo_hasta' => $parteHasta,
                ':cantidad_parte_enfermo' => $cantPartes,
                ':destino_interno'     => $destinoInterno !== '' ? $destinoInterno : null,
                ':rol'                 => $rol !== '' ? $rol : null,
                ':anios_en_destino'    => $aniosEnDestino,
                ':fracc'               => $fracc !== '' ? $fracc : null,
                ':observaciones'       => $obs !== '' ? $obs : null,
                ':updated_by'          => $userAudit,
            ]);

            $procesadas++;
        }

        $pdo->commit();

        // Guardar copia del Excel importado como "maestro" en storage/evidencias
        try {
            $evidDir = $PROJECT_BASE . '/storage/evidencias';
            if (!is_dir($evidDir)) {
                @mkdir($evidDir, 0775, true);
            }
            @copy($fileTmp, $MASTER_XLSX);
        } catch (Throwable $e) {
            // si falla, no rompe nada
        }

        $mensajeOk = "Importación completada. Filas procesadas: {$procesadas}. (Se actualiza por DNI, sin duplicar personal)";

    } elseif ($accion === 'exportar_excel') {
        // ===== Exportar listado completo a Excel usando el archivo maestro =====

        $sql = "
            SELECT
                grado,
                arma_espec,
                apellido_nombre,
                dni,
                cuil,
                fecha_nac,
                peso,
                altura,
                sexo,
                domicilio,
                estado_civil,
                hijos,
                nou,
                nro_cta,
                cbu,
                alias_banco,
                fecha_ultimo_anexo27,
                tiene_parte_enfermo,
                parte_enfermo_desde,
                parte_enfermo_hasta,
                cantidad_parte_enfermo,
                destino_interno,
                rol,
                anios_en_destino,
                fracc,
                observaciones
            FROM personal_unidad
            ORDER BY id ASC
        ";
        $stmt = $pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Si existe el archivo maestro, lo abrimos; si no, creamos uno nuevo
        if (is_file($MASTER_XLSX)) {
            $spreadsheet = IOFactory::load($MASTER_XLSX);
        } else {
            $spreadsheet = new Spreadsheet();
        }

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Personal');

        // Encabezados (solo el texto, no toco formato)
        $sheet->setCellValue('A1', 'GRADO');
        $sheet->setCellValue('B1', 'ARMA/ESPEC');
        $sheet->setCellValue('C1', 'APELLIDO Y NOMBRE');
        $sheet->setCellValue('D1', 'DNI');
        $sheet->setCellValue('E1', 'CUIL');
        $sheet->setCellValue('F1', 'FECHA NAC');
        $sheet->setCellValue('G1', 'PESO');
        $sheet->setCellValue('H1', 'ALTURA');
        $sheet->setCellValue('I1', 'SEXO');
        $sheet->setCellValue('J1', 'DOMICILIO');
        $sheet->setCellValue('K1', 'ESTADO CIVIL');
        $sheet->setCellValue('L1', 'HIJOS');
        $sheet->setCellValue('M1', 'NOU');
        $sheet->setCellValue('N1', 'NRO CTA BANCO');
        $sheet->setCellValue('O1', 'CBU BANCO');
        $sheet->setCellValue('P1', 'ALIAS BANCO');
        $sheet->setCellValue('Q1', 'FECHA ULTIMO ANEXO 27');
        $sheet->setCellValue('R1', 'TIENE PARTE DE ENFERMO');
        $sheet->setCellValue('S1', 'DESDE');
        $sheet->setCellValue('T1', 'HASTA');
        $sheet->setCellValue('U1', 'CANTIDAD DE PARTE DE ENFERMO');
        $sheet->setCellValue('V1', 'DESTINO INTERNO');
        $sheet->setCellValue('W1', 'ROL');
        $sheet->setCellValue('X1', 'AÑOS EN DESTINO');
        $sheet->setCellValue('Y1', 'FRACC');
        $sheet->setCellValue('Z1', 'OBSERVACIONES');

        // Limpiar datos viejos (A2:Z última fila) sin tocar formato
        $highestRow = $sheet->getHighestDataRow('A');
        if ($highestRow < 2) {
            $highestRow = 2;
        }
        for ($r = 2; $r <= $highestRow; $r++) {
            foreach (range('A', 'Z') as $col) {
                $sheet->setCellValue($col . $r, '');
            }
        }

        // Volcar los datos de la BD
        $rowNum = 2;
        foreach ($rows as $r) {
            $sheet->setCellValue('A' . $rowNum, $r['grado'] ?? '');
            $sheet->setCellValue('B' . $rowNum, $r['arma_espec'] ?? '');
            $sheet->setCellValue('C' . $rowNum, $r['apellido_nombre'] ?? '');
            $sheet->setCellValue('D' . $rowNum, $r['dni'] ?? '');
            $sheet->setCellValue('E' . $rowNum, $r['cuil'] ?? '');
            $sheet->setCellValue(
                'F' . $rowNum,
                !empty($r['fecha_nac']) ? date('d/m/Y', strtotime($r['fecha_nac'])) : ''
            );
            $sheet->setCellValue('G' . $rowNum, $r['peso'] ?? '');
            $sheet->setCellValue('H' . $rowNum, $r['altura'] ?? '');
            $sheet->setCellValue('I' . $rowNum, $r['sexo'] ?? '');
            $sheet->setCellValue('J' . $rowNum, $r['domicilio'] ?? '');
            $sheet->setCellValue('K' . $rowNum, $r['estado_civil'] ?? '');
            $sheet->setCellValue('L' . $rowNum, $r['hijos'] ?? '');
            $sheet->setCellValue('M' . $rowNum, $r['nou'] ?? '');
            $sheet->setCellValue('N' . $rowNum, $r['nro_cta'] ?? '');
            $sheet->setCellValue('O' . $rowNum, $r['cbu'] ?? '');
            $sheet->setCellValue('P' . $rowNum, $r['alias_banco'] ?? '');
            $sheet->setCellValue(
                'Q' . $rowNum,
                !empty($r['fecha_ultimo_anexo27']) ? date('d/m/Y', strtotime($r['fecha_ultimo_anexo27'])) : ''
            );

            $tieneParte = isset($r['tiene_parte_enfermo']) && (int)$r['tiene_parte_enfermo'] === 1 ? 'SI' : 'NO';
            $sheet->setCellValue('R' . $rowNum, $tieneParte);

            $sheet->setCellValue(
                'S' . $rowNum,
                !empty($r['parte_enfermo_desde']) ? date('d/m/Y', strtotime($r['parte_enfermo_desde'])) : ''
            );
            $sheet->setCellValue(
                'T' . $rowNum,
                !empty($r['parte_enfermo_hasta']) ? date('d/m/Y', strtotime($r['parte_enfermo_hasta'])) : ''
            );

            $sheet->setCellValue('U' . $rowNum, $r['cantidad_parte_enfermo'] ?? '');
            $sheet->setCellValue('V' . $rowNum, $r['destino_interno'] ?? '');
            $sheet->setCellValue('W' . $rowNum, $r['rol'] ?? '');
            $sheet->setCellValue('X' . $rowNum, $r['anios_en_destino'] ?? '');
            $sheet->setCellValue('Y' . $rowNum, $r['fracc'] ?? '');
            $sheet->setCellValue('Z' . $rowNum, $r['observaciones'] ?? '');

            $rowNum++;
        }

        // Guardar maestro actualizado (opcional)
        try {
            $writerMaster = new Xlsx($spreadsheet);
            $writerMaster->save($MASTER_XLSX);
        } catch (Throwable $e) {
            // si falla, seguimos igual
        }

        // Descargar al navegador
        $filename = 'personal_unidad_completo_' . date('Ymd_His') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;

    } elseif ($accion === 'guardar_nuevo' || $accion === 'guardar_edicion') {

        /* ---------- Alta / Edición manual ---------- */
        $id       = (int)($_POST['id'] ?? 0);

        $grado           = trim((string)($_POST['grado'] ?? ''));
        $armaEspec       = trim((string)($_POST['arma_espec'] ?? ''));
        $apellidoNombre  = trim((string)($_POST['apellido_nombre'] ?? ''));
        $dni             = trim((string)($_POST['dni'] ?? ''));
        $cuil            = trim((string)($_POST['cuil'] ?? ''));
        $fechaNacRaw     = trim((string)($_POST['fecha_nac'] ?? ''));
        $pesoRaw         = trim((string)($_POST['peso'] ?? ''));
        $alturaRaw       = trim((string)($_POST['altura'] ?? ''));
        $sexo            = trim((string)($_POST['sexo'] ?? ''));
        $domicilio       = trim((string)($_POST['domicilio'] ?? ''));
        $estadoCivil     = trim((string)($_POST['estado_civil'] ?? ''));
        $hijosRaw        = trim((string)($_POST['hijos'] ?? ''));
        $nou             = trim((string)($_POST['nou'] ?? ''));
        $nroCta          = trim((string)($_POST['nro_cta'] ?? ''));
        $cbu             = trim((string)($_POST['cbu'] ?? ''));
        $destinoInterno  = trim((string)($_POST['destino_interno'] ?? ''));
        $rol             = trim((string)($_POST['rol'] ?? ''));
        $aniosRaw        = trim((string)($_POST['anios_en_destino'] ?? ''));
        $fracc           = trim((string)($_POST['fracc'] ?? ''));
        $anexoRaw        = trim((string)($_POST['fecha_ultimo_anexo27'] ?? ''));
        $tieneParte      = isset($_POST['tiene_parte_enfermo']) ? 1 : 0;
        $parteDesdeRaw   = trim((string)($_POST['parte_enfermo_desde'] ?? ''));
        $parteHastaRaw   = trim((string)($_POST['parte_enfermo_hasta'] ?? ''));
        $cantPartesRaw   = trim((string)($_POST['cantidad_parte_enfermo'] ?? ''));
        $observaciones   = trim((string)($_POST['observaciones'] ?? ''));

        if ($apellidoNombre === '') {
            throw new RuntimeException('El campo "Apellido y Nombre" es obligatorio.');
        }

        $fechaNac   = $fechaNacRaw !== '' ? date('Y-m-d', strtotime($fechaNacRaw)) : null;
        $fechaAnx   = $anexoRaw   !== '' ? date('Y-m-d', strtotime($anexoRaw))   : null;
        $parteDesde = $parteDesdeRaw !== '' ? date('Y-m-d', strtotime($parteDesdeRaw)) : null;
        $parteHasta = $parteHastaRaw !== '' ? date('Y-m-d', strtotime($parteHastaRaw)) : null;

        $peso   = ($pesoRaw === '') ? null : (float)$pesoRaw;
        $altura = ($alturaRaw === '') ? null : (float)$alturaRaw;
        $hijos  = ($hijosRaw === '') ? null : (int)$hijosRaw;
        $aniosEnDestino = ($aniosRaw === '') ? null : (float)$aniosRaw;
        $cantPartes     = ($cantPartesRaw === '') ? null : (int)$cantPartesRaw;

        $userAudit = current_username_for_audit();

        if ($accion === 'guardar_nuevo') {
            $sql = "INSERT INTO personal_unidad
                    (
                        grado,
                        arma_espec,
                        apellido_nombre,
                        dni,
                        cuil,
                        fecha_nac,
                        peso,
                        altura,
                        sexo,
                        domicilio,
                        estado_civil,
                        hijos,
                        nou,
                        nro_cta,
                        cbu,
                        destino_interno,
                        rol,
                        anios_en_destino,
                        fracc,
                        fecha_ultimo_anexo27,
                        tiene_parte_enfermo,
                        parte_enfermo_desde,
                        parte_enfermo_hasta,
                        cantidad_parte_enfermo,
                        observaciones,
                        updated_at,
                        updated_by
                    )
                    VALUES
                    (
                        :grado,
                        :arma_espec,
                        :apellido_nombre,
                        :dni,
                        :cuil,
                        :fecha_nac,
                        :peso,
                        :altura,
                        :sexo,
                        :domicilio,
                        :estado_civil,
                        :hijos,
                        :nou,
                        :nro_cta,
                        :cbu,
                        :destino_interno,
                        :rol,
                        :anios_en_destino,
                        :fracc,
                        :fecha_ultimo_anexo27,
                        :tiene_parte_enfermo,
                        :parte_enfermo_desde,
                        :parte_enfermo_hasta,
                        :cantidad_parte_enfermo,
                        :observaciones,
                        NOW(),
                        :updated_by
                    )";
        } else {
            if ($id <= 0) {
                throw new RuntimeException('ID inválido para edición.');
            }
            $sql = "UPDATE personal_unidad
                    SET
                        grado                = :grado,
                        arma_espec           = :arma_espec,
                        apellido_nombre      = :apellido_nombre,
                        dni                  = :dni,
                        cuil                 = :cuil,
                        fecha_nac            = :fecha_nac,
                        peso                 = :peso,
                        altura               = :altura,
                        sexo                 = :sexo,
                        domicilio            = :domicilio,
                        estado_civil         = :estado_civil,
                        hijos                = :hijos,
                        nou                  = :nou,
                        nro_cta              = :nro_cta,
                        cbu                  = :cbu,
                        destino_interno      = :destino_interno,
                        rol                  = :rol,
                        anios_en_destino     = :anios_en_destino,
                        fracc                = :fracc,
                        fecha_ultimo_anexo27 = :fecha_ultimo_anexo27,
                        tiene_parte_enfermo  = :tiene_parte_enfermo,
                        parte_enfermo_desde  = :parte_enfermo_desde,
                        parte_enfermo_hasta  = :parte_enfermo_hasta,
                        cantidad_parte_enfermo = :cantidad_parte_enfermo,
                        observaciones        = :observaciones,
                        updated_at           = NOW(),
                        updated_by           = :updated_by
                    WHERE id = :id";
        }

        $params = [
            ':grado'               => $grado !== '' ? $grado : null,
            ':arma_espec'          => $armaEspec !== '' ? $armaEspec : null,
            ':apellido_nombre'     => $apellidoNombre,
            ':dni'                 => $dni !== '' ? $dni : null,
            ':cuil'                => $cuil !== '' ? $cuil : null,
            ':fecha_nac'           => $fechaNac,
            ':peso'                => $peso,
            ':altura'              => $altura,
            ':sexo'                => $sexo !== '' ? $sexo : null,
            ':domicilio'           => $domicilio !== '' ? $domicilio : null,
            ':estado_civil'        => $estadoCivil !== '' ? $estadoCivil : null,
            ':hijos'               => $hijos,
            ':nou'                 => $nou !== '' ? $nou : null,
            ':nro_cta'             => $nroCta !== '' ? $nroCta : null,
            ':cbu'                 => $cbu !== '' ? $cbu : null,
            ':destino_interno'     => $destinoInterno !== '' ? $destinoInterno : null,
            ':rol'                 => $rol !== '' ? $rol : null,
            ':anios_en_destino'    => $aniosEnDestino,
            ':fracc'               => $fracc !== '' ? $fracc : null,
            ':fecha_ultimo_anexo27'=> $fechaAnx,
            ':tiene_parte_enfermo' => $tieneParte,
            ':parte_enfermo_desde' => $parteDesde,
            ':parte_enfermo_hasta' => $parteHasta,
            ':cantidad_parte_enfermo' => $cantPartes,
            ':observaciones'       => $observaciones !== '' ? $observaciones : null,
            ':updated_by'          => $userAudit,
        ];
        if ($accion === 'guardar_edicion') {
            $params[':id'] = $id;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $mensajeOk = ($accion === 'guardar_nuevo')
            ? 'Personal agregado correctamente.'
            : 'Datos del personal actualizados correctamente.';

    } elseif ($accion === 'borrar_individual') {
        /* ---------- Borrar un registro + vinculados ---------- */
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            throw new RuntimeException('ID inválido para eliminar.');
        }

        $pdo->beginTransaction();

        $stDoc = $pdo->prepare("DELETE FROM personal_documentos WHERE personal_id = :id");
        $stDoc->execute([':id' => $id]);

        $stSan = $pdo->prepare("DELETE FROM sanidad_partes_enfermo WHERE personal_id = :id");
        $stSan->execute([':id' => $id]);

        $stPers = $pdo->prepare("DELETE FROM personal_unidad WHERE id = :id");
        $stPers->execute([':id' => $id]);

        $pdo->commit();

        $mensajeOk = 'Registro de personal y sus datos asociados fueron eliminados correctamente.';

    } elseif ($accion === 'borrar_todo') {
        /* ---------- Borrar toda la tabla + hijas ---------- */
        $pdo->beginTransaction();

        $pdo->exec("DELETE FROM sanidad_partes_enfermo");
        $pdo->exec("DELETE FROM personal_documentos");
        $pdo->exec("DELETE FROM personal_unidad");

        $pdo->commit();

        $mensajeOk = 'Se eliminaron todos los registros de personal y sus datos asociados.';
    }

} catch (Throwable $ex) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $mensajeError = $ex->getMessage();
}

/* =========================================================
 *  Filtros GET
 * ======================================================= */
$busqueda   = trim((string)($_GET['q'] ?? ''));
$f_destino  = trim((string)($_GET['destino'] ?? ''));

/* Destinos para combo */
try {
    $destinosStmt = $pdo->query("
        SELECT DISTINCT destino_interno
        FROM personal_unidad
        WHERE destino_interno IS NOT NULL AND destino_interno <> ''
        ORDER BY destino_interno
    ");
    $destinos = $destinosStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $ex) {
    $destinos = [];
}

/* =========================================================
 *  Listado principal
 * ======================================================= */
$listadoError = '';
$filas = [];

try {
    $sql = "SELECT
                pu.id,
                pu.grado,
                pu.arma_espec,
                pu.apellido_nombre,
                pu.dni,
                pu.cuil,
                pu.fecha_nac,
                pu.peso,
                pu.altura,
                pu.destino_interno,
                pu.rol,
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
        $sql .= " AND (pu.apellido_nombre LIKE :q OR pu.dni LIKE :q OR pu.cuil LIKE :q)";
        $params[':q'] = '%' . $busqueda . '%';
    }

    if ($f_destino !== '') {
        $sql .= " AND pu.destino_interno = :destino";
        $params[':destino'] = $f_destino;
    }

    // Orden por ID (orden de carga)
    $sql .= " ORDER BY pu.id ASC";

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

<!-- Bootstrap 5 por CDN (igual que rol_combate) -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Tema 602 -->
<link rel="stylesheet" href="<?= e($ASSETS_URL) ?>/css/theme-602.css">

<!-- Favicon -->
<link rel="icon" type="image/png" href="<?= e($ASSETS_URL) ?>/img/escudo_bcom602.png">

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
    color:#bfdbfe;
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
    margin-right:17px;
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
    color:#bfdbfe;
  }

  .table-dark-custom {
    --bs-table-bg: rgba(15,23,42,.9);
    --bs-table-striped-bg: rgba(30,64,175,.25);
    --bs-table-border-color: rgba(148,163,184,.4);
    color:#e5e7eb;
    font-size:.80rem;
  }
  .table-dark-custom th,
  .table-dark-custom td{
    padding:.30rem .35rem;
  }

  .search-panel{
    background:rgba(15,23,42,.95);
    border-radius:12px;
    border:1px solid rgba(148,163,184,.35);
    padding:10px 12px;
    margin-bottom:10px;
  }
  .search-panel label{
    font-size:.8rem;
    margin-bottom:2px;
    color:#bfdbfe;
  }
  .search-panel .form-text{
    font-size:.7rem;
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
  #modalEditar .modal-body{
    max-height: 70vh;
    overflow-y: auto;
  }
    #modalNuevo .modal-body{
    max-height: 70vh;
    overflow-y: auto;
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
        <div class="brand-sub">“Hogar de las Comunicaciones Fijas del Ejército” · S-1 Personal</div>
      </div>
    </div>
    <div class="header-back">
      <button type="button"
              class="btn btn-success btn-sm"
              style="font-weight:700; padding:.35rem .9rem;"
              onclick="window.location.href='areas_s1.php'">
        Volver a áreas
      </button>
      <button type="button"
              class="btn btn-success btn-sm"
              style="font-weight:700; padding:.35rem .9rem;"
              onclick="if (window.history.length > 1) { window.history.back(); } else { window.location.href='areas_s1.php'; }">
        Volver
      </button>
    </div>
  </div>
</header>

<div class="page-wrap">
  <div class="container-main">
    <div class="panel">

      <!-- ===== Filtros superiores (buscador + destino) ===== -->
      <div class="search-panel mb-3">
        <form method="get" class="row g-2 align-items-end">
          <div class="col-md-6 col-lg-7">
            <label class="form-label">Buscar por nombre, DNI</label>
            <div class="input-group input-group-sm">
              <span class="input-group-text">Ej: NOMBRE O APELLIDO / 12345678</span>
              <input
                type="text"
                name="q"
                class="form-control"
                value="<?= e($busqueda) ?>"
              >
            </div>
          </div>

          <div class="col-md-4 col-lg-3">
            <label class="form-label">Destino interno (área/compañía)</label>
            <select name="destino" class="form-select form-select-sm">
              <option value="">Todos los destinos</option>
              <?php foreach ($destinos as $dest): ?>
                <option value="<?= e($dest) ?>" <?= $f_destino === $dest ? 'selected' : '' ?>>
                  <?= e($dest) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-2 col-lg-2 d-flex gap-1">
            <button type="submit"
                    class="btn btn-success btn-sm w-100"
                    style="font-weight:700;">
              Filtrar
            </button>
            <a href="s1_personal.php"
               class="btn btn-outline-light btn-sm"
               style="font-weight:600;">
              Limpiar
            </a>
          </div>
        </form>
      </div>

      <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
        <div>
          <div class="panel-title">S-1 · Personal de la unidad</div>
          <div class="panel-sub mb-0">
            Gestión centralizada del personal (grado, destino, datos físicos y resumen de sanidad/documentación).
          </div>
          <div class="small text-muted mt-1">
            Registros mostrados: <?= e((string)count($filas)) ?>
            <?= $f_destino !== '' ? ' · Destino: '.e($f_destino) : '' ?>
            <?= $busqueda  !== '' ? ' · Búsqueda: "'.e($busqueda).'"' : '' ?>
          </div>
        </div>

        <div class="d-flex flex-column flex-sm-row gap-2">
          <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#modalNuevo"
                  style="font-weight:600; padding:.35rem .9rem;">
            + Nuevo personal
          </button>

          <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#modalExcel"
                  style="font-weight:600; padding:.35rem .9rem;">
            Importar / Actualizar desde Excel
          </button>

          <form method="post">
            <?php if (function_exists('csrf_input')) csrf_input(); ?>
            <input type="hidden" name="accion" value="exportar_excel">
            <button type="submit" class="btn btn-sm btn-outline-warning"
                    style="font-weight:600; padding:.35rem .9rem;">
              Exportar Excel completo
            </button>
          </form>

          <form method="post"
                onsubmit="return confirm('¿Eliminar TODOS los registros de personal? Esta acción no se puede deshacer.');">
            <?php if (function_exists('csrf_input')) csrf_input(); ?>
            <input type="hidden" name="accion" value="borrar_todo">
            <button type="submit" class="btn btn-sm btn-outline-danger"
                    style="font-weight:600; padding:.35rem .9rem;">
              Eliminar lista completa
            </button>
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

      <!-- ===== Tabla principal ===== -->
      <div class="table-responsive mt-2">
        <table class="table table-sm table-dark table-striped table-dark-custom align-middle">
          <thead>
            <tr>
              <th scope="col">#</th>
              <th scope="col">Grado</th>
              <th scope="col">Arma/Espec</th>
              <th scope="col">Apellido y Nombre</th>
              <th scope="col">DNI</th>
              <th scope="col">CUIL</th>
              <th scope="col">F. Nac.</th>
              <th scope="col">Peso</th>
              <th scope="col">Altura</th>
              <th scope="col">Destino interno</th>
              <th scope="col">Rol</th>
              <th scope="col">Años dest.</th>
              <th scope="col">Últ. Anexo 27</th>
              <th scope="col">Partes (tot/vig)</th>
              <th scope="col" class="text-end">Acciones</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$filas): ?>
            <tr>
              <td colspan="15" class="text-center text-muted py-4">
                No se encontraron registros de personal.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($filas as $idx => $row): ?>
              <?php
                $fn    = $row['fecha_nac'] ?? null;
                $fnFmt = $fn ? date('d/m/Y', strtotime($fn)) : '';

                $aniosDest = $row['anios_en_destino'] ?? null;
                $anx       = $row['fecha_ultimo_anexo27'] ?? null;
                $anxFmt    = $anx ? date('d/m/Y', strtotime($anx)) : '';

                $pt = (int)($row['partes_total'] ?? 0);
                $pv = (int)($row['partes_vigentes'] ?? 0);

                if     ($pv > 0)         $classParts = 'badge bg-danger';
                elseif ($pt > 0)         $classParts = 'badge bg-warning text-dark';
                elseif ($pt === 0)       $classParts = 'badge bg-success';
                else                     $classParts = 'badge bg-secondary';
              ?>
              <tr>
                <td><?= e($idx + 1) ?></td>
                <td><?= e($row['grado'] ?? '') ?></td>
                <td><?= e($row['arma_espec'] ?? '') ?></td>
                <td><?= e($row['apellido_nombre'] ?? '') ?></td>
                <td><?= e($row['dni'] ?? '') ?></td>
                <td><?= e($row['cuil'] ?? '') ?></td>
                <td><?= e($fnFmt) ?></td>
                <td><?= e($row['peso'] !== null ? $row['peso'] : '') ?></td>
                <td><?= e($row['altura'] !== null ? $row['altura'] : '') ?></td>
                <td><?= e($row['destino_interno'] ?? '') ?></td>
                <td><?= e($row['rol'] ?? '') ?></td>
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
                    <a href="s1_documentos.php?id=<?= e($row['id']) ?>"
                       class="btn btn-sm btn-outline-info">
                      Ficha
                    </a>

                    <!-- Editar -->
                    <button
                      type="button"
                      class="btn btn-sm btn-outline-light btn-edit"
                      data-id="<?= e($row['id']) ?>"
                      data-grado="<?= e($row['grado'] ?? '') ?>"
                      data-arma_espec="<?= e($row['arma_espec'] ?? '') ?>"
                      data-nombre="<?= e($row['apellido_nombre'] ?? '') ?>"
                      data-dni="<?= e($row['dni'] ?? '') ?>"
                      data-cuil="<?= e($row['cuil'] ?? '') ?>"
                      data-fecha_nac="<?= e($fn ?: '') ?>"
                      data-peso="<?= e($row['peso'] !== null ? $row['peso'] : '') ?>"
                      data-altura="<?= e($row['altura'] !== null ? $row['altura'] : '') ?>"
                      data-destino_interno="<?= e($row['destino_interno'] ?? '') ?>"
                      data-rol="<?= e($row['rol'] ?? '') ?>"
                      data-anios="<?= e($aniosDest !== null ? (string)$aniosDest : '') ?>"
                      data-anexo="<?= e($anx ?: '') ?>"
                      data-observaciones="<?= e($row['observaciones'] ?? '') ?>"
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
              <label class="form-label">Arma/Espec</label>
              <input type="text" name="arma_espec" class="form-control form-control-sm">
            </div>
            <div class="col-md-6">
              <label class="form-label">Apellido y Nombre *</label>
              <input type="text" name="apellido_nombre" class="form-control form-control-sm" required>
            </div>

            <div class="col-md-3">
              <label class="form-label">DNI</label>
              <input type="text" name="dni" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
              <label class="form-label">CUIL</label>
              <input type="text" name="cuil" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
              <label class="form-label">Fecha nacimiento</label>
              <input type="date" name="fecha_nac" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
              <label class="form-label">Sexo</label>
              <input type="text" name="sexo" class="form-control form-control-sm" placeholder="M/F">
            </div>

            <div class="col-md-3">
              <label class="form-label">Peso</label>
              <input type="number" step="0.01" name="peso" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
              <label class="form-label">Altura</label>
              <input type="number" step="0.01" name="altura" class="form-control form-control-sm">
            </div>
            <div class="col-md-6">
              <label class="form-label">Domicilio</label>
              <input type="text" name="domicilio" class="form-control form-control-sm">
            </div>

            <div class="col-md-3">
              <label class="form-label">Estado civil</label>
              <input type="text" name="estado_civil" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
              <label class="form-label">Hijos</label>
              <input type="number" name="hijos" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
              <label class="form-label">NOU</label>
              <input type="text" name="nou" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
              <label class="form-label">Fracción</label>
              <input type="text" name="fracc" class="form-control form-control-sm">
            </div>

            <div class="col-md-4">
              <label class="form-label">Nro Cta Banco</label>
              <input type="text" name="nro_cta" class="form-control form-control-sm">
            </div>
            <div class="col-md-4">
              <label class="form-label">CBU Banco</label>
              <input type="text" name="cbu" class="form-control form-control-sm">
            </div>
            <div class="col-md-4">
              <!-- Alias se gestiona desde ficha individual si querés -->
            </div>

            <div class="col-md-6">
              <label class="form-label">Destino interno (área/compañía)</label>
              <input type="text" name="destino_interno" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
              <label class="form-label">Rol</label>
              <input type="text" name="rol" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
              <label class="form-label">Años en destino</label>
              <input type="number" step="0.1" name="anios_en_destino" class="form-control form-control-sm">
            </div>

            <div class="col-md-4">
              <label class="form-label">Fecha último Anexo 27</label>
              <input type="date" name="fecha_ultimo_anexo27" class="form-control form-control-sm">
            </div>
            <div class="col-md-4 d-flex align-items-center">
              <div class="form-check mt-4">
                <input class="form-check-input" type="checkbox" name="tiene_parte_enfermo" id="nuevoTieneParte">
                <label class="form-check-label" for="nuevoTieneParte">
                  Tiene parte de enfermo
                </label>
              </div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Cant. partes enfermo</label>
              <input type="number" name="cantidad_parte_enfermo" class="form-control form-control-sm">
            </div>

            <div class="col-md-4">
              <label class="form-label">Parte desde</label>
              <input type="date" name="parte_enfermo_desde" class="form-control form-control-sm">
            </div>
            <div class="col-md-4">
              <label class="form-label">Parte hasta</label>
              <input type="date" name="parte_enfermo_hasta" class="form-control form-control-sm">
            </div>

            <div class="col-12">
              <label class="form-label">Observaciones</label>
              <textarea name="observaciones" rows="2" class="form-control form-control-sm"></textarea>
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
              <label class="form-label">Arma/Espec</label>
              <input type="text" name="arma_espec" id="editArmaEspec" class="form-control form-control-sm">
            </div>
            <div class="col-md-6">
              <label class="form-label">Apellido y Nombre *</label>
              <input type="text" name="apellido_nombre" id="editNombre" class="form-control form-control-sm" required>
            </div>

            <div class="col-md-3">
              <label class="form-label">DNI</label>
              <input type="text" name="dni" id="editDni" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
              <label class="form-label">CUIL</label>
              <input type="text" name="cuil" id="editCuil" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
              <label class="form-label">Fecha nacimiento</label>
              <input type="date" name="fecha_nac" id="editFechaNac" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
              <label class="form-label">Sexo</label>
              <input type="text" name="sexo" id="editSexo" class="form-control form-control-sm">
            </div>

            <div class="col-md-3">
              <label class="form-label">Peso</label>
              <input type="number" step="0.01" name="peso" id="editPeso" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
              <label class="form-label">Altura</label>
              <input type="number" step="0.01" name="altura" id="editAltura" class="form-control form-control-sm">
            </div>
            <div class="col-md-6">
              <label class="form-label">Domicilio</label>
              <input type="text" name="domicilio" id="editDomicilio" class="form-control form-control-sm">
            </div>

            <div class="col-md-3">
              <label class="form-label">Estado civil</label>
              <input type="text" name="estado_civil" id="editEstadoCivil" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
              <label class="form-label">Hijos</label>
              <input type="number" name="hijos" id="editHijos" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
              <label class="form-label">NOU</label>
              <input type="text" name="nou" id="editNou" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
              <label class="form-label">Fracción</label>
              <input type="text" name="fracc" id="editFracc" class="form-control form-control-sm">
            </div>

            <div class="col-md-4">
              <label class="form-label">Nro Cta Banco</label>
              <input type="text" name="nro_cta" id="editNroCta" class="form-control form-control-sm">
            </div>
            <div class="col-md-4">
              <label class="form-label">CBU Banco</label>
              <input type="text" name="cbu" id="editCbu" class="form-control form-control-sm">
            </div>
            <div class="col-md-4">
              <!-- Alias desde ficha individual -->
            </div>

            <div class="col-md-6">
              <label class="form-label">Destino interno (área/compañía)</label>
              <input type="text" name="destino_interno" id="editDestinoInterno" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
              <label class="form-label">Rol</label>
              <input type="text" name="rol" id="editRol" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
              <label class="form-label">Años en destino</label>
              <input type="number" step="0.1" name="anios_en_destino" id="editAnios" class="form-control form-control-sm">
            </div>

            <div class="col-md-4">
              <label class="form-label">Fecha último Anexo 27</label>
              <input type="date" name="fecha_ultimo_anexo27" id="editAnexo" class="form-control form-control-sm">
            </div>
            <div class="col-md-4 d-flex align-items-center">
              <div class="form-check mt-4">
                <input class="form-check-input" type="checkbox" name="tiene_parte_enfermo" id="editTieneParte">
                <label class="form-check-label" for="editTieneParte">
                  Tiene parte de enfermo
                </label>
              </div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Cant. partes enfermo</label>
              <input type="number" name="cantidad_parte_enfermo" id="editCantPartes" class="form-control form-control-sm">
            </div>

            <div class="col-md-4">
              <label class="form-label">Parte desde</label>
              <input type="date" name="parte_enfermo_desde" id="editParteDesde" class="form-control form-control-sm">
            </div>
            <div class="col-md-4">
              <label class="form-label">Parte hasta</label>
              <input type="date" name="parte_enfermo_hasta" id="editParteHasta" class="form-control form-control-sm">
            </div>

            <div class="col-12">
              <label class="form-label">Observaciones</label>
              <textarea name="observaciones" id="editObservaciones" rows="2" class="form-control form-control-sm"></textarea>
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
          <h5 class="modal-title" id="modalExcelLabel">Importar / Actualizar listado desde Excel (A–Z)</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <p class="small text-muted mb-1">
            El archivo debe ser .xls o .xlsx. Se espera el siguiente orden de columnas
            (fila 1 = encabezados, datos desde la fila 2):
          </p>
          <ul class="small mb-2">
            <li>A: Grado</li>
            <li>B: Arma / Especialidad</li>
            <li>C: Apellido y Nombre</li>
            <li>D: DNI (clave para evitar duplicados y actualizar)</li>
            <li>E: CUIL</li>
            <li>F: Fecha de nacimiento</li>
            <li>G: Peso</li>
            <li>H: Altura</li>
            <li>I: Sexo</li>
            <li>J: Domicilio</li>
            <li>K: Estado civil</li>
            <li>L: Hijos</li>
            <li>M: NOU</li>
            <li>N: Nro Cta Banco</li>
            <li>O: CBU Banco</li>
            <li>P: ALIAS BANCO (se puede cargar luego en ficha individual)</li>
            <li>Q: Fecha último Anexo 27</li>
            <li>R: Tiene parte de enfermo (SI/NO/1/0)</li>
            <li>S: Parte enfermo desde</li>
            <li>T: Parte enfermo hasta</li>
            <li>U: Cantidad de partes de enfermo</li>
            <li>V: Destino interno</li>
            <li>W: Rol</li>
            <li>X: Años en destino</li>
            <li>Y: Fracción</li>
            <li>Z: Observaciones</li>
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

    document.getElementById('editId').value           = btn.dataset.id || '';
    document.getElementById('editGrado').value        = btn.dataset.grado || '';
    document.getElementById('editArmaEspec').value    = btn.dataset.arma_espec || '';
    document.getElementById('editNombre').value       = btn.dataset.nombre || '';
    document.getElementById('editDni').value          = btn.dataset.dni || '';
    document.getElementById('editCuil').value         = btn.dataset.cuil || '';

    const fn = btn.dataset.fecha_nac || '';
    document.getElementById('editFechaNac').value     = fn;

    document.getElementById('editPeso').value         = btn.dataset.peso || '';
    document.getElementById('editAltura').value       = btn.dataset.altura || '';
    document.getElementById('editDestinoInterno').value = btn.dataset.destino_interno || '';
    document.getElementById('editRol').value          = btn.dataset.rol || '';
    document.getElementById('editAnios').value        = btn.dataset.anios || '';
    document.getElementById('editAnexo').value        = btn.dataset.anexo || '';
    document.getElementById('editObservaciones').value = btn.dataset.observaciones || '';

    // El checkbox "tiene parte" no lo tenemos en los data-*,
    // lo podés activar manualmente desde la ficha o completando luego.

    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
  });
});
</script>
</body>
</html>
