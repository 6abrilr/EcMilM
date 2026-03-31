<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config/db.php';

use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

function out(string $msg): void
{
    fwrite(STDOUT, $msg . PHP_EOL);
}

function norm(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    return trim($value);
}

function normDni(string $value): string
{
    return preg_replace('/\D+/', '', $value) ?? '';
}

function decodeXmlText(string $value): string
{
    $value = html_entity_decode($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    return trim($value);
}

function excelSerialToDate(?string $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    if (preg_match('/^\d+$/', $value)) {
        try {
            return ExcelDate::excelToDateTimeObject((float)$value)->format('Y-m-d');
        } catch (Throwable $e) {
            return null;
        }
    }

    $ts = strtotime($value);
    return $ts !== false ? date('Y-m-d', $ts) : null;
}

function extractSharedStrings(ZipArchive $zip): array
{
    $xml = $zip->getFromName('xl/sharedStrings.xml');
    if ($xml === false) {
        return [];
    }

    preg_match_all('/<si.*?>(.*?)<\/si>/su', $xml, $matches);
    $shared = [];
    foreach ($matches[1] as $siXml) {
        preg_match_all('/<t[^>]*>(.*?)<\/t>/su', $siXml, $tMatches);
        $shared[] = decodeXmlText(implode('', $tMatches[1] ?? []));
    }
    return $shared;
}

function extractRowsFromWorkbook(string $excelPath): array
{
    $zip = new ZipArchive();
    if ($zip->open($excelPath) !== true) {
        throw new RuntimeException('No se pudo abrir el archivo XLSX.');
    }

    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if ($sheetXml === false) {
        $zip->close();
        throw new RuntimeException('No se encontro xl/worksheets/sheet1.xml.');
    }

    $shared = extractSharedStrings($zip);
    $zip->close();

    preg_match_all('/<row[^>]*r="(\d+)"[^>]*>(.*?)<\/row>/su', $sheetXml, $rowMatches, PREG_SET_ORDER);
    $rows = [];
    foreach ($rowMatches as $rowMatch) {
        $rowNum = (int)$rowMatch[1];
        $body = $rowMatch[2];
        preg_match_all('/<c[^>]*r="([A-Z]+)\d+"([^>]*)>(.*?)<\/c>/su', $body, $cellMatches, PREG_SET_ORDER);
        $row = ['_row' => $rowNum];
        foreach ($cellMatches as $cellMatch) {
            $col = $cellMatch[1];
            $attrs = $cellMatch[2];
            $inner = $cellMatch[3];
            $type = '';
            if (preg_match('/\st="([^"]+)"/u', $attrs, $typeMatch)) {
                $type = $typeMatch[1];
            }

            $value = '';
            if (preg_match('/<v>(.*?)<\/v>/su', $inner, $vMatch)) {
                $raw = decodeXmlText($vMatch[1]);
                if ($type === 's') {
                    $idx = (int)$raw;
                    $value = $shared[$idx] ?? '';
                } else {
                    $value = $raw;
                }
            } elseif (preg_match('/<t[^>]*>(.*?)<\/t>/su', $inner, $tMatch)) {
                $value = decodeXmlText($tMatch[1]);
            }

            $row[$col] = $value;
        }
        $rows[] = $row;
    }

    return $rows;
}

$excelPath = $argv[1] ?? '';
$unidadId  = isset($argv[2]) ? (int)$argv[2] : 1;
$ciclo     = isset($argv[3]) ? (int)$argv[3] : 2026;
$apply     = in_array('--apply', $argv, true);

if ($excelPath === '' || !is_file($excelPath)) {
    out('Uso: php import_soldados_excel.php "C:\ruta\archivo.xlsx" [unidad_id] [ciclo] [--apply]');
    exit(1);
}

$sheetRows = extractRowsFromWorkbook($excelPath);
$headerRow = null;
foreach ($sheetRows as $row) {
    $headers = array_map(
        static fn($v): string => mb_strtoupper(norm((string)$v), 'UTF-8'),
        [
            $row['C'] ?? '',
            $row['D'] ?? '',
            $row['E'] ?? '',
            $row['F'] ?? '',
            $row['G'] ?? '',
            $row['H'] ?? '',
            $row['I'] ?? '',
        ]
    );
    if (in_array('GRADO', $headers, true) && in_array('DNI', $headers, true)) {
        $headerRow = (int)$row['_row'];
        break;
    }
}

if ($headerRow === null) {
    throw new RuntimeException('No se encontro la fila de encabezados.');
}

$stFind = $pdo->prepare("
    SELECT id, grado, apellido_nombre, fecha_alta, destino_interno, observaciones
    FROM personal_unidad
    WHERE unidad_id = :uid
      AND REPLACE(REPLACE(REPLACE(dni,'.',''),'-',''),' ','') = :dni
    LIMIT 1
");

$stInsert = $pdo->prepare("
    INSERT INTO personal_unidad (
        unidad_id,
        dni,
        grado,
        apellido_nombre,
        fecha_alta,
        destino_interno,
        observaciones,
        jerarquia,
        updated_at
    ) VALUES (
        :uid,
        :dni,
        :grado,
        :apellido_nombre,
        :fecha_alta,
        :destino_interno,
        :observaciones,
        'SOLDADO',
        NOW()
    )
");

$stUpdate = $pdo->prepare("
    UPDATE personal_unidad
       SET grado = :grado,
           apellido_nombre = :apellido_nombre,
           fecha_alta = :fecha_alta,
           destino_interno = :destino_interno,
           observaciones = :observaciones,
           jerarquia = 'SOLDADO',
           updated_at = NOW()
     WHERE id = :id
");

$stCiclo = $pdo->prepare("
    INSERT INTO personal_ciclos (unidad_id, ciclo, personal_id, estado)
    VALUES (:uid, :ciclo, :pid, 'activo')
    ON DUPLICATE KEY UPDATE estado = 'activo', updated_at = NOW()
");

 $rows = [];
foreach ($sheetRows as $row) {
    $rowNum = (int)($row['_row'] ?? 0);
    if ($rowNum <= $headerRow) {
        continue;
    }

    $grado = norm((string)($row['D'] ?? ''));
    $nombre = norm((string)($row['E'] ?? ''));
    $dni = normDni((string)($row['F'] ?? ''));
    $fechaAlta = excelSerialToDate((string)($row['G'] ?? ''));
    $puesto = norm((string)($row['H'] ?? ''));
    $obs = norm((string)($row['I'] ?? ''));

    if ($grado === '' && $nombre === '' && $dni === '') {
        continue;
    }
    if ($dni === '' || $nombre === '') {
        continue;
    }

    $rows[] = [
        'row' => $rowNum,
        'grado' => $grado,
        'apellido_nombre' => $nombre,
        'dni' => $dni,
        'fecha_alta' => $fechaAlta,
        'destino_interno' => $puesto !== '' ? $puesto : null,
        'observaciones' => $obs !== '' ? $obs : null,
    ];
}

$inserted = 0;
$updated = 0;
$linked = 0;

if ($apply) {
    $pdo->beginTransaction();
}

try {
    foreach ($rows as $item) {
        $stFind->execute([
            ':uid' => $unidadId,
            ':dni' => $item['dni'],
        ]);
        $found = $stFind->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($found) {
            if ($apply) {
                $stUpdate->execute([
                    ':id' => (int)$found['id'],
                    ':grado' => $item['grado'] !== '' ? $item['grado'] : ($found['grado'] ?: null),
                    ':apellido_nombre' => $item['apellido_nombre'] !== '' ? $item['apellido_nombre'] : ($found['apellido_nombre'] ?: null),
                    ':fecha_alta' => $item['fecha_alta'] ?? ($found['fecha_alta'] ?: null),
                    ':destino_interno' => $item['destino_interno'] ?? ($found['destino_interno'] ?: null),
                    ':observaciones' => $item['observaciones'] ?? ($found['observaciones'] ?: null),
                ]);
            }
            $personalId = (int)$found['id'];
            $updated++;
        } else {
            if ($apply) {
                $stInsert->execute([
                    ':uid' => $unidadId,
                    ':dni' => $item['dni'],
                    ':grado' => $item['grado'] !== '' ? $item['grado'] : null,
                    ':apellido_nombre' => $item['apellido_nombre'],
                    ':fecha_alta' => $item['fecha_alta'],
                    ':destino_interno' => $item['destino_interno'],
                    ':observaciones' => $item['observaciones'],
                ]);
                $personalId = (int)$pdo->lastInsertId();
            } else {
                $personalId = 0;
            }
            $inserted++;
        }

        if ($apply && $personalId > 0) {
            $stCiclo->execute([
                ':uid' => $unidadId,
                ':ciclo' => $ciclo,
                ':pid' => $personalId,
            ]);
            $linked++;
        }
    }

    if ($apply && $pdo->inTransaction()) {
        $pdo->commit();
    }
} catch (Throwable $e) {
    if ($apply && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

out('Archivo: ' . $excelPath);
out('Unidad: ' . $unidadId . ' | Ciclo: ' . $ciclo . ' | Aplicar: ' . ($apply ? 'si' : 'no'));
out('Filas utiles: ' . count($rows));
out('Nuevos: ' . $inserted);
out('Actualizados: ' . $updated);
out('Vinculados a ciclo: ' . $linked);
