<?php
declare(strict_types=1);

function personal_civil_normalize_dni(string $value): string
{
    return preg_replace('/\D+/', '', trim($value)) ?? '';
}

function personal_civil_normalize_text(?string $value): string
{
    $value = trim((string)$value);
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    return $value;
}

function personal_civil_key(string $value): string
{
    $value = mb_strtolower(personal_civil_normalize_text($value), 'UTF-8');
    $map = [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
        'ñ' => 'n',
    ];
    return strtr($value, $map);
}

function personal_civil_excel_date($value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }
    if (is_numeric($value)) {
        $base = new DateTimeImmutable('1899-12-30 00:00:00');
        return $base->modify('+' . (int)round((float)$value) . ' days')->format('Y-m-d');
    }
    $raw = trim((string)$value);
    foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $fmt) {
        $dt = DateTimeImmutable::createFromFormat($fmt, $raw);
        if ($dt instanceof DateTimeImmutable) {
            return $dt->format('Y-m-d');
        }
    }
    $ts = strtotime($raw);
    return $ts !== false ? date('Y-m-d', $ts) : null;
}

function personal_civil_excel_datetime($value, ?string $fallback = null): ?string
{
    if ($value !== null && $value !== '' && is_numeric($value)) {
        $serial = (float)$value;
        $days = (int)floor($serial);
        $seconds = (int)round(($serial - $days) * 86400);
        $base = new DateTimeImmutable('1899-12-30 00:00:00');
        return $base->modify("+{$days} days")->modify("+{$seconds} seconds")->format('Y-m-d H:i:s');
    }
    if ($fallback) {
        $ts = strtotime($fallback);
        if ($ts !== false) {
            return date('Y-m-d H:i:s', $ts);
        }
    }
    if ($value !== null && $value !== '') {
        $ts = strtotime((string)$value);
        if ($ts !== false) {
            return date('Y-m-d H:i:s', $ts);
        }
    }
    return null;
}

function personal_civil_ensure_tables(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS personal_civil_padron (
            id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            unidad_id INT NOT NULL,
            personal_id INT DEFAULT NULL,
            dni VARCHAR(20) NOT NULL,
            apellido VARCHAR(120) DEFAULT NULL,
            nombre VARCHAR(120) DEFAULT NULL,
            apellido_nombre VARCHAR(255) DEFAULT NULL,
            fecha_nac DATE DEFAULT NULL,
            destino_interno VARCHAR(255) DEFAULT NULL,
            destino_id INT DEFAULT NULL,
            horario_referencia VARCHAR(120) DEFAULT NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            origen_archivo VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            updated_by_id INT DEFAULT NULL,
            UNIQUE KEY uq_personal_civil_padron (unidad_id, dni),
            KEY idx_personal_civil_padron_activo (unidad_id, activo),
            KEY idx_personal_civil_padron_personal (personal_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS personal_civil_registros (
            id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            unidad_id INT NOT NULL,
            personal_id INT DEFAULT NULL,
            dni VARCHAR(20) NOT NULL,
            nombre_detectado VARCHAR(255) DEFAULT NULL,
            fecha_hora DATETIME NOT NULL,
            fecha DATE NOT NULL,
            hora TIME NOT NULL,
            campo_3 VARCHAR(30) DEFAULT NULL,
            campo_4 VARCHAR(30) DEFAULT NULL,
            campo_5 VARCHAR(30) DEFAULT NULL,
            campo_6 VARCHAR(30) DEFAULT NULL,
            linea_original TEXT,
            origen_archivo VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_personal_civil_registro (unidad_id, dni, fecha_hora),
            KEY idx_personal_civil_registro_fecha (unidad_id, fecha),
            KEY idx_personal_civil_registro_personal (personal_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS personal_civil_resumen_manual (
            id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            unidad_id INT NOT NULL,
            dni VARCHAR(20) NOT NULL,
            fecha DATE NOT NULL,
            ingreso_manual DATETIME DEFAULT NULL,
            egreso_manual DATETIME DEFAULT NULL,
            observacion VARCHAR(255) DEFAULT NULL,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            updated_by_id INT DEFAULT NULL,
            UNIQUE KEY uq_personal_civil_manual (unidad_id, dni, fecha),
            KEY idx_personal_civil_manual_fecha (unidad_id, fecha)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

function personal_civil_xlsx_rows(string $path): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('La extensión ZipArchive no está disponible en PHP.');
    }
    if (!is_file($path)) {
        throw new RuntimeException('No se encontró el archivo Excel a importar.');
    }

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('No se pudo abrir el archivo Excel.');
    }

    $shared = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedXml !== false) {
        $sx = simplexml_load_string($sharedXml);
        if ($sx !== false) {
            $sx->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            foreach ($sx->xpath('//a:si') ?: [] as $si) {
                $si->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                $text = '';
                foreach ($si->xpath('.//a:t') ?: [] as $t) {
                    $text .= (string)$t;
                }
                $shared[] = $text;
            }
        }
    }

    $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
    $wbXml   = $zip->getFromName('xl/workbook.xml');
    if ($relsXml === false || $wbXml === false) {
        $zip->close();
        throw new RuntimeException('El archivo Excel no tiene la estructura esperada.');
    }

    $rels = simplexml_load_string($relsXml);
    $wb   = simplexml_load_string($wbXml);
    if ($rels === false || $wb === false) {
        $zip->close();
        throw new RuntimeException('No se pudo leer la estructura interna del Excel.');
    }

    $rels->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/package/2006/relationships');
    $wb->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $relMap = [];
    foreach ($rels->xpath('//r:Relationship') ?: [] as $rel) {
        $relMap[(string)$rel['Id']] = 'xl/' . ltrim((string)$rel['Target'], '/');
    }

    $result = [];
    foreach ($wb->xpath('//a:sheets/a:sheet') ?: [] as $sheet) {
        $name = (string)$sheet['name'];
        $rid = (string)$sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
        $target = $relMap[$rid] ?? '';
        if ($target === '') {
            continue;
        }
        $sheetXml = $zip->getFromName($target);
        if ($sheetXml === false) {
            continue;
        }
        $sheetDoc = simplexml_load_string($sheetXml);
        if ($sheetDoc === false) {
            continue;
        }
        $sheetDoc->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rows = [];
        foreach ($sheetDoc->xpath('//a:sheetData/a:row') ?: [] as $row) {
            $row->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $vals = [];
            foreach ($row->xpath('./a:c') ?: [] as $cell) {
                $cell->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                $ref = (string)$cell['r'];
                $col = preg_replace('/\d+/', '', $ref) ?? '';
                $type = (string)$cell['t'];
                $value = '';
                if ($type === 'inlineStr') {
                    foreach ($cell->xpath('.//a:t') ?: [] as $t) {
                        $value .= (string)$t;
                    }
                } else {
                    $v = (string)($cell->v ?? '');
                    if ($type === 's' && $v !== '') {
                        $idx = (int)$v;
                        $value = $shared[$idx] ?? '';
                    } else {
                        $value = $v;
                    }
                }
                if ($col !== '') {
                    $vals[$col] = $value;
                }
            }
            if ($vals !== []) {
                $rows[] = $vals;
            }
        }
        $result[] = ['name' => $name, 'rows' => $rows];
    }
    $zip->close();
    return $result;
}

function personal_civil_destino_map(PDO $pdo, int $unidadId): array
{
    $map = [];
    $stmt = $pdo->prepare("SELECT id, nombre, codigo FROM destino WHERE unidad_id = :u ORDER BY nombre");
    $stmt->execute([':u' => $unidadId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        foreach ([(string)$row['nombre'], (string)$row['codigo']] as $candidate) {
            $key = personal_civil_key($candidate);
            if ($key !== '') {
                $map[$key] = (int)$row['id'];
            }
        }
    }
    return $map;
}

function personal_civil_attlog_rows(string $path): array
{
    if (!is_file($path)) {
        throw new RuntimeException('No se encontró el archivo .dat a importar.');
    }

    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        throw new RuntimeException('No se pudo leer el archivo .dat del lector.');
    }

    $rows = [];
    foreach ($lines as $line) {
        $raw = trim((string)$line);
        if ($raw === '') {
            continue;
        }

        $parts = preg_split('/\t+/', $raw) ?: [];
        if (count($parts) < 2) {
            continue;
        }

        $dni = personal_civil_normalize_dni((string)($parts[0] ?? ''));
        $fechaHora = trim((string)($parts[1] ?? ''));
        if ($dni === '' || $fechaHora === '') {
            continue;
        }

        $rows[] = [
            'A' => $dni,
            'B' => '',
            'C' => '',
            'F' => (string)($parts[2] ?? ''),
            'G' => (string)($parts[3] ?? ''),
            'H' => (string)($parts[4] ?? ''),
            'I' => (string)($parts[5] ?? ''),
            'J' => $fechaHora,
        ];
    }

    return $rows;
}

function personal_civil_import_padron(PDO $pdo, int $unidadId, int $updatedById, string $path, string $sourceName = ''): array
{
    personal_civil_ensure_tables($pdo);
    $sheets = personal_civil_xlsx_rows($path);
    if ($sheets === []) {
        throw new RuntimeException('El padrón de personal civil no contiene hojas legibles.');
    }
    $rows = $sheets[0]['rows'] ?? [];
    if (count($rows) < 2) {
        throw new RuntimeException('El padrón de personal civil no tiene filas de datos.');
    }

    $destinoMap = personal_civil_destino_map($pdo, $unidadId);
    $activeDnis = [];
    $count = 0;

    $upsertPersonal = $pdo->prepare(
        "INSERT INTO personal_unidad
            (unidad_id, dni, fecha_nac, destino_interno, jerarquia, grado, arma, apellido_nombre, apellido, nombre, destino_id, observaciones, role_id, updated_at, updated_by_id, extra_json)
         VALUES
            (:unidad_id, :dni, :fecha_nac, :destino_interno, 'AGENTE_CIVIL', 'AC', NULL, :apellido_nombre, :apellido, :nombre, :destino_id, :observaciones, 3, NOW(), :updated_by_id, :extra_json)
         ON DUPLICATE KEY UPDATE
            fecha_nac = VALUES(fecha_nac),
            destino_interno = VALUES(destino_interno),
            jerarquia = VALUES(jerarquia),
            grado = VALUES(grado),
            apellido_nombre = VALUES(apellido_nombre),
            apellido = VALUES(apellido),
            nombre = VALUES(nombre),
            destino_id = VALUES(destino_id),
            observaciones = VALUES(observaciones),
            role_id = VALUES(role_id),
            updated_at = NOW(),
            updated_by_id = VALUES(updated_by_id),
            extra_json = VALUES(extra_json)"
    );
    $getPersonalId = $pdo->prepare("SELECT id FROM personal_unidad WHERE unidad_id = :u AND dni = :dni LIMIT 1");
    $upsertPadron = $pdo->prepare(
        "INSERT INTO personal_civil_padron
            (unidad_id, personal_id, dni, apellido, nombre, apellido_nombre, fecha_nac, destino_interno, destino_id, horario_referencia, activo, origen_archivo, updated_by_id)
         VALUES
            (:unidad_id, :personal_id, :dni, :apellido, :nombre, :apellido_nombre, :fecha_nac, :destino_interno, :destino_id, :horario_referencia, 1, :origen_archivo, :updated_by_id)
         ON DUPLICATE KEY UPDATE
            personal_id = VALUES(personal_id),
            apellido = VALUES(apellido),
            nombre = VALUES(nombre),
            apellido_nombre = VALUES(apellido_nombre),
            fecha_nac = VALUES(fecha_nac),
            destino_interno = VALUES(destino_interno),
            destino_id = VALUES(destino_id),
            horario_referencia = VALUES(horario_referencia),
            activo = 1,
            origen_archivo = VALUES(origen_archivo),
            updated_by_id = VALUES(updated_by_id),
            updated_at = NOW()"
    );

    $pdo->beginTransaction();
    try {
        foreach (array_slice($rows, 1) as $row) {
            $dni = personal_civil_normalize_dni((string)($row['D'] ?? ''));
            if ($dni === '') {
                continue;
            }
            $nombre = personal_civil_normalize_text((string)($row['B'] ?? ''));
            $apellido = personal_civil_normalize_text((string)($row['C'] ?? ''));
            $apellidoNombre = trim($apellido . ' ' . $nombre);
            $fechaNac = personal_civil_excel_date($row['E'] ?? null);
            $destinoInterno = personal_civil_normalize_text((string)($row['F'] ?? ''));
            $horario = personal_civil_normalize_text((string)($row['G'] ?? ''));
            $destinoId = $destinoMap[personal_civil_key($destinoInterno)] ?? null;
            $extraJson = json_encode([
                'jerarquia' => 'AGENTE_CIVIL',
                'jerarquia_label' => 'AGENTES CIVILES',
                'horario_referencia' => $horario,
            ], JSON_UNESCAPED_UNICODE);

            $upsertPersonal->execute([
                ':unidad_id' => $unidadId,
                ':dni' => $dni,
                ':fecha_nac' => $fechaNac,
                ':destino_interno' => $destinoInterno !== '' ? $destinoInterno : null,
                ':apellido_nombre' => $apellidoNombre !== '' ? $apellidoNombre : null,
                ':apellido' => $apellido !== '' ? $apellido : null,
                ':nombre' => $nombre !== '' ? $nombre : null,
                ':destino_id' => $destinoId,
                ':observaciones' => $horario !== '' ? ('Horario: ' . $horario) : null,
                ':updated_by_id' => $updatedById ?: null,
                ':extra_json' => $extraJson,
            ]);

            $getPersonalId->execute([':u' => $unidadId, ':dni' => $dni]);
            $personalId = (int)($getPersonalId->fetchColumn() ?: 0);

            $upsertPadron->execute([
                ':unidad_id' => $unidadId,
                ':personal_id' => $personalId ?: null,
                ':dni' => $dni,
                ':apellido' => $apellido !== '' ? $apellido : null,
                ':nombre' => $nombre !== '' ? $nombre : null,
                ':apellido_nombre' => $apellidoNombre !== '' ? $apellidoNombre : null,
                ':fecha_nac' => $fechaNac,
                ':destino_interno' => $destinoInterno !== '' ? $destinoInterno : null,
                ':destino_id' => $destinoId,
                ':horario_referencia' => $horario !== '' ? $horario : null,
                ':origen_archivo' => $sourceName !== '' ? $sourceName : basename($path),
                ':updated_by_id' => $updatedById ?: null,
            ]);

            $activeDnis[] = $dni;
            $count++;
        }

        if ($activeDnis !== []) {
            $placeholders = implode(',', array_fill(0, count($activeDnis), '?'));
            $params = array_merge([$unidadId], $activeDnis);
            $sql = "UPDATE personal_civil_padron
                    SET activo = 0, updated_at = NOW(), updated_by_id = ?
                    WHERE unidad_id = ? AND dni NOT IN ($placeholders)";
            $params = array_merge([$updatedById ?: null, $unidadId], $activeDnis);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        } else {
            $stmt = $pdo->prepare("UPDATE personal_civil_padron SET activo = 0, updated_at = NOW(), updated_by_id = :u WHERE unidad_id = :unidad");
            $stmt->execute([':u' => $updatedById ?: null, ':unidad' => $unidadId]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return ['processed' => $count, 'active_dnis' => count($activeDnis)];
}

function personal_civil_import_registros(PDO $pdo, int $unidadId, int $updatedById, string $path, string $sourceName = ''): array
{
    personal_civil_ensure_tables($pdo);
    $ext = strtolower((string)pathinfo($sourceName !== '' ? $sourceName : $path, PATHINFO_EXTENSION));
    if ($ext === 'dat') {
        $rows = personal_civil_attlog_rows($path);
        if ($rows === []) {
            throw new RuntimeException('El archivo .dat no contiene marcas legibles.');
        }
    } else {
        $sheets = personal_civil_xlsx_rows($path);
        if ($sheets === []) {
            throw new RuntimeException('El Excel de registros no contiene hojas legibles.');
        }

        $rows = $sheets[0]['rows'] ?? [];
        if (count($rows) < 2) {
            throw new RuntimeException('El Excel de registros no tiene filas de datos.');
        }
        $rows = array_slice($rows, 1);
    }

    $getPersonalId = $pdo->prepare("SELECT id FROM personal_unidad WHERE unidad_id = :u AND dni = :dni LIMIT 1");
    $insert = $pdo->prepare(
        "INSERT INTO personal_civil_registros
            (unidad_id, personal_id, dni, nombre_detectado, fecha_hora, fecha, hora, campo_3, campo_4, campo_5, campo_6, linea_original, origen_archivo)
         VALUES
            (:unidad_id, :personal_id, :dni, :nombre_detectado, :fecha_hora, :fecha, :hora, :campo_3, :campo_4, :campo_5, :campo_6, :linea_original, :origen_archivo)
         ON DUPLICATE KEY UPDATE
            personal_id = VALUES(personal_id),
            nombre_detectado = VALUES(nombre_detectado),
            campo_3 = VALUES(campo_3),
            campo_4 = VALUES(campo_4),
            campo_5 = VALUES(campo_5),
            campo_6 = VALUES(campo_6),
            linea_original = VALUES(linea_original),
            origen_archivo = VALUES(origen_archivo)"
    );

    $count = 0;
    $pdo->beginTransaction();
    try {
        foreach ($rows as $row) {
            $dni = personal_civil_normalize_dni((string)($row['A'] ?? ''));
            if ($dni === '') {
                continue;
            }
            $fechaHora = personal_civil_excel_datetime($row['C'] ?? null, (string)($row['J'] ?? ''));
            if ($fechaHora === null) {
                continue;
            }
            $getPersonalId->execute([':u' => $unidadId, ':dni' => $dni]);
            $personalId = (int)($getPersonalId->fetchColumn() ?: 0);
            $insert->execute([
                ':unidad_id' => $unidadId,
                ':personal_id' => $personalId ?: null,
                ':dni' => $dni,
                ':nombre_detectado' => personal_civil_normalize_text((string)($row['B'] ?? '')) ?: null,
                ':fecha_hora' => $fechaHora,
                ':fecha' => substr($fechaHora, 0, 10),
                ':hora' => substr($fechaHora, 11, 8),
                ':campo_3' => personal_civil_normalize_text((string)($row['F'] ?? '')) ?: null,
                ':campo_4' => personal_civil_normalize_text((string)($row['G'] ?? '')) ?: null,
                ':campo_5' => personal_civil_normalize_text((string)($row['H'] ?? '')) ?: null,
                ':campo_6' => personal_civil_normalize_text((string)($row['I'] ?? '')) ?: null,
                ':linea_original' => personal_civil_normalize_text((string)($row['J'] ?? '')) ?: null,
                ':origen_archivo' => $sourceName !== '' ? $sourceName : basename($path),
            ]);
            $count++;
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return ['processed' => $count];
}
