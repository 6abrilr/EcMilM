<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config/db.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

function out(string $msg): void
{
    fwrite(STDOUT, $msg . PHP_EOL);
}

function norm(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $map = [
        'Á' => 'A', 'À' => 'A', 'Ä' => 'A', 'Â' => 'A',
        'É' => 'E', 'È' => 'E', 'Ë' => 'E', 'Ê' => 'E',
        'Í' => 'I', 'Ì' => 'I', 'Ï' => 'I', 'Î' => 'I',
        'Ó' => 'O', 'Ò' => 'O', 'Ö' => 'O', 'Ô' => 'O',
        'Ú' => 'U', 'Ù' => 'U', 'Ü' => 'U', 'Û' => 'U',
        'Ñ' => 'N',
        'á' => 'A', 'à' => 'A', 'ä' => 'A', 'â' => 'A',
        'é' => 'E', 'è' => 'E', 'ë' => 'E', 'ê' => 'E',
        'í' => 'I', 'ì' => 'I', 'ï' => 'I', 'î' => 'I',
        'ó' => 'O', 'ò' => 'O', 'ö' => 'O', 'ô' => 'O',
        'ú' => 'U', 'ù' => 'U', 'ü' => 'U', 'û' => 'U',
        'ñ' => 'N',
    ];

    $value = strtr($value, $map);
    $value = mb_strtoupper($value, 'UTF-8');
    $value = preg_replace('/[^A-Z0-9 ]+/u', ' ', $value) ?? $value;
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    return trim($value);
}

function findHeaderRow(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $highestRow, int $highestCol): int
{
    for ($row = 1; $row <= min($highestRow, 20); $row++) {
        $headers = [];
        for ($col = 1; $col <= $highestCol; $col++) {
            $coord = Coordinate::stringFromColumnIndex($col) . $row;
            $headers[] = norm((string)$sheet->getCell($coord)->getFormattedValue());
        }
        if (in_array('GRADO', $headers, true) && in_array('APELLIDO Y NOMBRES', $headers, true)) {
            return $row;
        }
    }
    throw new RuntimeException('No se encontró la fila de encabezados.');
}

function buildHeaderMap(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $headerRow, int $highestCol): array
{
    $map = [];
    for ($col = 1; $col <= $highestCol; $col++) {
        $coord = Coordinate::stringFromColumnIndex($col) . $headerRow;
        $raw = (string)$sheet->getCell($coord)->getFormattedValue();
        $map[norm($raw)] = $col;
    }
    return $map;
}

function nameTokens(string $value): array
{
    $value = norm($value);
    return $value === '' ? [] : explode(' ', $value);
}

function findFuzzyCandidate(array $personalRows, string $gradeKey, string $armaKey, string $nameKey): ?array
{
    $tokens = nameTokens($nameKey);
    if (!$tokens) {
        return null;
    }

    $surname = $tokens[0];
    $scored = [];

    foreach ($personalRows as $row) {
        if (($row['_name_key'] ?? '') === '') {
            continue;
        }

        $rowTokens = nameTokens((string)$row['_name_key']);
        if (!$rowTokens || $rowTokens[0] !== $surname) {
            continue;
        }

        similar_text($nameKey, (string)$row['_name_key'], $percent);
        $score = $percent;

        if (($row['_grade_key'] ?? '') === $gradeKey) {
            $score += 8;
        }
        if ($armaKey !== '' && ($row['_arma_key'] ?? '') === $armaKey) {
            $score += 4;
        }

        $scored[] = ['score' => $score, 'row' => $row];
    }

    if (!$scored) {
        return null;
    }

    usort($scored, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
    $best = $scored[0];
    $second = $scored[1]['score'] ?? null;

    if ($best['score'] < 90) {
        return null;
    }
    if ($second !== null && ($best['score'] - $second) < 3) {
        return null;
    }

    return $best['row'];
}

$excelPath = $argv[1] ?? '';
$unidadId = isset($argv[2]) ? (int)$argv[2] : 1;
$apply = in_array('--apply', $argv, true);

if ($excelPath === '' || !is_file($excelPath)) {
    out('Uso: php import_roles_personal_excel.php "C:\ruta\archivo.xlsx" [unidad_id] [--apply]');
    exit(1);
}

$required = [
    'NIVEL 1',
    'ROL DE COMBATE',
    'GRADO',
    'ARM ESP',
    'APELLIDO Y NOMBRES',
    'ROL ADMINISTRATIVO',
];

$spreadsheet = IOFactory::load($excelPath);
$sheet = $spreadsheet->getSheetByName('Personal') ?? $spreadsheet->getSheet(0);
$highestRow = $sheet->getHighestDataRow();
$highestCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
$headerRow = findHeaderRow($sheet, $highestRow, $highestCol);
$headerMap = buildHeaderMap($sheet, $headerRow, $highestCol);

foreach ($required as $key) {
    if (!isset($headerMap[$key])) {
        throw new RuntimeException("Falta la columna requerida: {$key}");
    }
}

$stmtPersonal = $pdo->prepare("
    SELECT id, grado, arma, apellido_nombre, rol_combate, rol_administrativo, destino_interno
    FROM personal_unidad
    WHERE unidad_id = :uid
");
$stmtPersonal->execute([':uid' => $unidadId]);
$personalRowsRaw = $stmtPersonal->fetchAll();

$personalRows = [];
$byGradeName = [];
$byName = [];
foreach ($personalRowsRaw as $row) {
    $nameKey = norm((string)($row['apellido_nombre'] ?? ''));
    $gradeKey = norm((string)($row['grado'] ?? ''));
    $armaKey = norm((string)($row['arma'] ?? ''));
    $row['_name_key'] = $nameKey;
    $row['_grade_key'] = $gradeKey;
    $row['_arma_key'] = $armaKey;
    $personalRows[] = $row;
    $byGradeName[$gradeKey . '|' . $nameKey][] = $row;
    $byName[$nameKey][] = $row;
}

$updates = [];
$unmatched = [];
$ambiguous = [];
$ignored = 0;

for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
    $name = trim((string)$sheet->getCell(Coordinate::stringFromColumnIndex($headerMap['APELLIDO Y NOMBRES']) . $row)->getFormattedValue());
    $grade = trim((string)$sheet->getCell(Coordinate::stringFromColumnIndex($headerMap['GRADO']) . $row)->getFormattedValue());
    $arma = trim((string)$sheet->getCell(Coordinate::stringFromColumnIndex($headerMap['ARM ESP']) . $row)->getFormattedValue());
    $rolCombate = trim((string)$sheet->getCell(Coordinate::stringFromColumnIndex($headerMap['ROL DE COMBATE']) . $row)->getFormattedValue());
    $rolAdm = trim((string)$sheet->getCell(Coordinate::stringFromColumnIndex($headerMap['ROL ADMINISTRATIVO']) . $row)->getFormattedValue());
    $destino = trim((string)$sheet->getCell(Coordinate::stringFromColumnIndex($headerMap['NIVEL 1']) . $row)->getFormattedValue());

    if ($name === '' || $grade === '') {
        $ignored++;
        continue;
    }

    $nameKey = norm($name);
    $gradeKey = norm($grade);
    $armaKey = norm($arma);

    $candidates = $byGradeName[$gradeKey . '|' . $nameKey] ?? [];
    if (count($candidates) > 1 && $armaKey !== '') {
        $filtered = array_values(array_filter($candidates, static fn(array $item): bool => $item['_arma_key'] === $armaKey));
        if ($filtered) {
            $candidates = $filtered;
        }
    }

    if (!$candidates) {
        $nameCandidates = $byName[$nameKey] ?? [];
        if (count($nameCandidates) === 1) {
            $candidates = $nameCandidates;
        }
    }

    if (!$candidates) {
        $fuzzy = findFuzzyCandidate($personalRows, $gradeKey, $armaKey, $nameKey);
        if ($fuzzy !== null) {
            $candidates = [$fuzzy];
        }
    }

    if (!$candidates) {
        $unmatched[] = [
            'row' => $row,
            'grado' => $grade,
            'arma' => $arma,
            'nombre' => $name,
            'rol_combate' => $rolCombate,
            'rol_administrativo' => $rolAdm,
            'destino' => $destino,
        ];
        continue;
    }

    if (count($candidates) > 1) {
        $ambiguous[] = [
            'row' => $row,
            'grado' => $grade,
            'nombre' => $name,
            'matches' => array_map(
                static fn(array $item): array => [
                    'id' => $item['id'],
                    'grado' => $item['grado'],
                    'arma' => $item['arma'],
                    'apellido_nombre' => $item['apellido_nombre'],
                ],
                $candidates
            ),
        ];
        continue;
    }

    $match = $candidates[0];
    $id = (int)$match['id'];
    if (!isset($updates[$id])) {
        $updates[$id] = [
            'id' => $id,
            'grado' => $match['grado'],
            'arma' => $match['arma'],
            'apellido_nombre' => $match['apellido_nombre'],
            'rol_combate' => $rolCombate !== '' ? $rolCombate : null,
            'rol_administrativo' => $rolAdm !== '' ? $rolAdm : null,
            'destino_interno' => $destino !== '' ? $destino : null,
            'source_row' => $row,
        ];
        continue;
    }

    if ($updates[$id]['rol_combate'] === null && $rolCombate !== '') {
        $updates[$id]['rol_combate'] = $rolCombate;
    }
    if ($updates[$id]['rol_administrativo'] === null && $rolAdm !== '') {
        $updates[$id]['rol_administrativo'] = $rolAdm;
    }
    if ($updates[$id]['destino_interno'] === null && $destino !== '') {
        $updates[$id]['destino_interno'] = $destino;
    }
}

out('Archivo: ' . $excelPath);
out('Unidad: ' . $unidadId);
out('Modo: ' . ($apply ? 'APLICAR' : 'PREVIEW'));
out('Coincidencias únicas: ' . count($updates));
out('Sin match: ' . count($unmatched));
out('Ambiguas: ' . count($ambiguous));
out('Ignoradas: ' . $ignored);

if ($unmatched) {
    out('');
    out('Primeras sin match:');
    foreach (array_slice($unmatched, 0, 15) as $item) {
        out(sprintf(
            'Fila %d | %s | %s | %s',
            $item['row'],
            $item['grado'],
            $item['arma'],
            $item['nombre']
        ));
    }
}

if ($ambiguous) {
    out('');
    out('Primeras ambiguas:');
    foreach (array_slice($ambiguous, 0, 10) as $item) {
        out(sprintf('Fila %d | %s | %s', $item['row'], $item['grado'], $item['nombre']));
        foreach ($item['matches'] as $match) {
            out(sprintf(
                '  - ID %d | %s | %s | %s',
                $match['id'],
                $match['grado'],
                $match['arma'],
                $match['apellido_nombre']
            ));
        }
    }
}

if (!$apply) {
    out('');
    out('Primeras coincidencias listas para actualizar:');
    foreach (array_slice(array_values($updates), 0, 15) as $item) {
        out(sprintf(
            'ID %d | %s | RC=%s | RA=%s | Dest=%s',
            $item['id'],
            $item['apellido_nombre'],
            $item['rol_combate'] ?? '',
            $item['rol_administrativo'] ?? '',
            $item['destino_interno'] ?? ''
        ));
    }
    exit(0);
}

$pdo->beginTransaction();
try {
    $stmtUpdate = $pdo->prepare("
        UPDATE personal_unidad
        SET rol_combate = :rol_combate,
            rol_administrativo = :rol_administrativo,
            destino_interno = :destino_interno,
            updated_at = NOW()
        WHERE id = :id
          AND unidad_id = :uid
        LIMIT 1
    ");

    $applied = 0;
    foreach ($updates as $item) {
        $stmtUpdate->execute([
            ':rol_combate' => $item['rol_combate'],
            ':rol_administrativo' => $item['rol_administrativo'],
            ':destino_interno' => $item['destino_interno'],
            ':id' => $item['id'],
            ':uid' => $unidadId,
        ]);
        $applied += $stmtUpdate->rowCount();
    }

    $pdo->commit();
    out('');
    out('Actualizaciones aplicadas: ' . $applied);
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}
