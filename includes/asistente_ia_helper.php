<?php
declare(strict_types=1);

function ia_e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function ia_norm_dni(string $dni): string
{
    return preg_replace('/\D+/', '', $dni) ?? '';
}

function ia_json(array $data): void
{
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ia_base_public_web(): string
{
    $self = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? ''));
    return rtrim(str_replace('\\', '/', dirname($self)), '/');
}

function ia_base_app_web(): string
{
    return rtrim(str_replace('\\', '/', dirname(ia_base_public_web())), '/');
}

function ia_safe_filename(string $name): string
{
    $name = trim(str_replace('\\', '/', $name));
    $name = basename($name);
    $name = preg_replace('/[^A-Za-z0-9._ -]+/', '_', $name) ?? 'reglamento.pdf';
    $name = preg_replace('/\s+/', '_', $name) ?? $name;
    return $name !== '' ? $name : 'reglamento.pdf';
}

function ia_get_context(PDO $pdo): array
{
    $user = function_exists('current_user') ? current_user() : ($_SESSION['user'] ?? null);
    $dni = ia_norm_dni((string)($user['dni'] ?? $user['username'] ?? ''));

    $ctx = [
        'user' => $user,
        'personal_id' => 0,
        'unidad_id' => 1,
        'role' => strtoupper((string)($user['rol_app'] ?? $user['role_app'] ?? 'USUARIO')),
        'unidad' => [
            'nombre_completo' => 'Escuela Militar de Montaña',
            'subnombre' => 'La montaña nos une',
            'slug' => 'ecmilm',
        ],
    ];

    try {
        if ($dni !== '') {
            $st = $pdo->prepare("
                SELECT pu.id, pu.unidad_id, r.codigo AS role_codigo
                FROM personal_unidad pu
                LEFT JOIN roles r ON r.id = pu.role_id
                WHERE REPLACE(REPLACE(REPLACE(pu.dni,'.',''),'-',''),' ','') = :dni
                LIMIT 1
            ");
            $st->execute([':dni' => $dni]);
            if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                $ctx['personal_id'] = (int)($row['id'] ?? 0);
                $ctx['unidad_id'] = (int)($row['unidad_id'] ?? 1);
                if (!empty($row['role_codigo'])) {
                    $ctx['role'] = strtoupper((string)$row['role_codigo']);
                }
            }
        }
    } catch (Throwable $e) {
    }

    if (ia_norm_dni((string)($user['dni'] ?? $user['username'] ?? '')) === '41742406'
        || strtolower((string)($user['username'] ?? '')) === 'nesrojas') {
        $ctx['role'] = 'SUPERADMIN';
    }

    try {
        $st = $pdo->prepare("SELECT nombre_completo, subnombre, slug FROM unidades WHERE id = :id LIMIT 1");
        $st->execute([':id' => $ctx['unidad_id']]);
        if ($u = $st->fetch(PDO::FETCH_ASSOC)) {
            foreach (['nombre_completo', 'subnombre', 'slug'] as $k) {
                if (!empty($u[$k])) {
                    $ctx['unidad'][$k] = (string)$u[$k];
                }
            }
        }
    } catch (Throwable $e) {
    }

    if (trim((string)$ctx['unidad']['slug']) === '') {
        $ctx['unidad']['slug'] = 'ecmilm';
    }

    return $ctx;
}

function ia_ensure_tables(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ia_reglamentos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            unidad_id INT NOT NULL,
            titulo VARCHAR(255) NOT NULL,
            archivo_original VARCHAR(255) NOT NULL,
            ruta_rel VARCHAR(500) NOT NULL,
            mime VARCHAR(120) NOT NULL DEFAULT 'application/pdf',
            size_bytes INT NOT NULL DEFAULT 0,
            sha1_hash CHAR(40) NOT NULL,
            texto LONGTEXT NULL,
            estado VARCHAR(30) NOT NULL DEFAULT 'indexado',
            error TEXT NULL,
            uploaded_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_ia_reg_unidad (unidad_id),
            KEY idx_ia_reg_hash (sha1_hash)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ia_reglamento_fragmentos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            reglamento_id INT NOT NULL,
            unidad_id INT NOT NULL,
            chunk_index INT NOT NULL,
            texto MEDIUMTEXT NOT NULL,
            keywords TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_ia_frag (reglamento_id, chunk_index),
            KEY idx_ia_frag_unidad (unidad_id),
            FULLTEXT KEY ft_ia_frag_texto (texto, keywords)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}

function ia_storage_paths(array $ctx): array
{
    $root = realpath(__DIR__ . '/..');
    if ($root === false) {
        $root = dirname(__DIR__);
    }
    $slug = ia_safe_filename((string)$ctx['unidad']['slug']);
    $relDir = 'storage/unidades/' . $slug . '/DOCUMENTACION';
    $absDir = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relDir);
    if (!is_dir($absDir)) {
        @mkdir($absDir, 0777, true);
    }
    return ['root' => $root, 'rel_dir' => $relDir, 'abs_dir' => $absDir];
}

function ia_pdf_unescape_literal(string $s): string
{
    $s = preg_replace_callback('/\\\\([0-7]{1,3})/', static fn($m) => chr(octdec($m[1])), $s) ?? $s;
    $map = [
        '\\n' => "\n", '\\r' => "\r", '\\t' => "\t", '\\b' => "\b", '\\f' => "\f",
        '\\(' => '(', '\\)' => ')', '\\\\' => '\\',
    ];
    return strtr($s, $map);
}

function ia_decode_pdf_text_piece(string $s): string
{
    $s = ia_pdf_unescape_literal($s);
    if (str_starts_with($s, "\xFE\xFF")) {
        $out = @iconv('UTF-16BE', 'UTF-8//IGNORE', substr($s, 2));
        return is_string($out) ? $out : $s;
    }
    if (substr_count(substr($s, 0, 40), "\x00") > 4) {
        $out = @iconv('UTF-16BE', 'UTF-8//IGNORE', $s);
        return is_string($out) ? $out : $s;
    }
    return $s;
}

function ia_extract_text_from_pdf_stream(string $stream): string
{
    $text = '';
    if (preg_match_all('/\((?:\\\\.|[^\\\\()])*\)/s', $stream, $matches)) {
        foreach ($matches[0] as $raw) {
            $piece = substr($raw, 1, -1);
            $decoded = ia_decode_pdf_text_piece($piece);
            if (preg_match('/[A-Za-zÁÉÍÓÚÜÑáéíóúüñ0-9]{2,}/u', $decoded)) {
                $text .= ' ' . $decoded;
            }
        }
    }
    if (preg_match_all('/<([0-9A-Fa-f\s]{4,})>/', $stream, $hexMatches)) {
        foreach ($hexMatches[1] as $hex) {
            $hex = preg_replace('/\s+/', '', $hex) ?? '';
            if ($hex === '' || strlen($hex) % 2 !== 0) {
                continue;
            }
            $bin = @hex2bin($hex);
            if (!is_string($bin)) {
                continue;
            }
            $decoded = ia_decode_pdf_text_piece($bin);
            if (preg_match('/[A-Za-zÁÉÍÓÚÜÑáéíóúüñ0-9]{2,}/u', $decoded)) {
                $text .= ' ' . $decoded;
            }
        }
    }
    return $text;
}

function ia_pdf_extract_text(string $path): string
{
    $bin = @file_get_contents($path);
    if (!is_string($bin) || $bin === '') {
        return '';
    }

    $out = '';
    if (preg_match_all('/<<(.*?)>>\s*stream\r?\n?(.*?)\r?\n?endstream/s', $bin, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $dict = $m[1];
            $stream = $m[2];
            if (stripos($dict, 'FlateDecode') !== false) {
                $decoded = @gzuncompress($stream);
                if (!is_string($decoded)) {
                    $decoded = @gzinflate(substr($stream, 2));
                }
                if (is_string($decoded)) {
                    $stream = $decoded;
                }
            }
            if (stripos($stream, 'BT') !== false || preg_match('/\((?:\\\\.|[^\\\\()]){2,}\)/s', $stream)) {
                $out .= ' ' . ia_extract_text_from_pdf_stream($stream);
            }
        }
    }

    if (mb_strlen($out, 'UTF-8') < 80) {
        $out .= ' ' . ia_extract_text_from_pdf_stream($bin);
    }

    $out = preg_replace('/[^\P{C}\t\r\n]+/u', ' ', $out) ?? $out;
    $out = preg_replace('/\s+/u', ' ', $out) ?? $out;
    return trim($out);
}

function ia_make_chunks(string $text, int $size = 1800, int $overlap = 220): array
{
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    if ($text === '') {
        return [];
    }
    $len = mb_strlen($text, 'UTF-8');
    $chunks = [];
    for ($start = 0; $start < $len; $start += max(1, $size - $overlap)) {
        $chunk = mb_substr($text, $start, $size, 'UTF-8');
        if (mb_strlen(trim($chunk), 'UTF-8') > 80) {
            $chunks[] = trim($chunk);
        }
    }
    return $chunks;
}

function ia_keywords(string $text): array
{
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? $text;
    $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $stop = array_flip(['tenes','tienes','cargado','cargada','cargados','cargadas','existe','estan','esta','hay','para','como','cuando','donde','desde','hasta','este','esta','estos','estas','todo','toda','todos','todas','sobre','segun','deber','debe','deben','sera','seran','entre','ante','con','por','del','las','los','una','uno','que','cual','cuales','quien','quienes','reglamento','reglamentos']);
    $freq = [];
    foreach ($words as $w) {
        if (mb_strlen($w, 'UTF-8') < 4 || isset($stop[$w])) {
            continue;
        }
        $freq[$w] = ($freq[$w] ?? 0) + 1;
    }
    arsort($freq);
    return array_slice(array_keys($freq), 0, 28);
}

function ia_norm_search_text(string $text): string
{
    $text = mb_strtolower($text, 'UTF-8');
    $from = ['á','é','í','ó','ú','ü','ñ','–','—','_'];
    $to = ['a','e','i','o','u','u','n','-','-',' '];
    $text = str_replace($from, $to, $text);
    $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text) ?? $text;
    return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
}

function ia_extract_regulation_codes(string $text): array
{
    $norm = ia_norm_search_text($text);
    $codes = [];
    if (preg_match_all('/\b(rfp|rfd|mfp|mpf|rob|rop|roe|rff|rfa|rc|ca|fal|mag)\s*(\d{1,3})\s*(\d{1,3})\b/u', $norm, $m, PREG_SET_ORDER)) {
        foreach ($m as $row) {
            $codes[] = strtoupper($row[1]) . ' ' . str_pad($row[2], 2, '0', STR_PAD_LEFT) . ' ' . str_pad($row[3], 2, '0', STR_PAD_LEFT);
        }
    }
    if ($codes) {
        return array_values(array_unique($codes));
    }
    if (preg_match_all('/\b(rfp|rfd|mfp|mpf|rob|rop|roe|rff|rfa|rc|ca|fal|mag)\s*(\d{1,3})\b/u', $norm, $m2, PREG_SET_ORDER)) {
        foreach ($m2 as $row) {
            $codes[] = strtoupper($row[1]) . ' ' . str_pad($row[2], 2, '0', STR_PAD_LEFT);
        }
    }
    return array_values(array_unique($codes));
}

function ia_doc_search_blob(array $doc): string
{
    return ia_norm_search_text(
        (string)($doc['titulo'] ?? '') . ' ' .
        (string)($doc['archivo_original'] ?? '') . ' ' .
        (string)($doc['ruta_rel'] ?? '')
    );
}

function ia_doc_matches_code(array $doc, string $code): bool
{
    $blob = ia_doc_search_blob($doc);
    $needle = ia_norm_search_text($code);
    $compactBlob = str_replace(' ', '', $blob);
    $compactNeedle = str_replace(' ', '', $needle);
    return $needle !== '' && (
        str_contains($blob, $needle)
        || ($compactNeedle !== '' && str_contains($compactBlob, $compactNeedle))
    );
}

function ia_find_docs(PDO $pdo, int $unidadId, string $question, array $docIds = []): array
{
    $params = [':uid' => $unidadId];
    $docSql = '';
    if ($docIds) {
        $in = [];
        foreach ($docIds as $i => $id) {
            $key = ':doc_find_' . $i;
            $in[] = $key;
            $params[$key] = (int)$id;
        }
        $docSql = ' AND id IN (' . implode(',', $in) . ') ';
    }

    $st = $pdo->prepare("
        SELECT id, titulo, archivo_original, ruta_rel, estado, error, size_bytes, created_at
        FROM ia_reglamentos
        WHERE unidad_id = :uid
        {$docSql}
        ORDER BY titulo ASC
    ");
    $st->execute($params);
    $docs = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $codes = ia_extract_regulation_codes($question);
    if ($codes) {
        $matches = [];
        foreach ($docs as $doc) {
            foreach ($codes as $code) {
                if (ia_doc_matches_code($doc, $code)) {
                    $doc['matched_code'] = $code;
                    $matches[] = $doc;
                    break;
                }
            }
        }
        return $matches;
    }

    $tokens = ia_keywords($question);
    if (!$tokens) {
        return [];
    }
    $scored = [];
    foreach ($docs as $doc) {
        $blob = ia_doc_search_blob($doc);
        $score = 0;
        foreach ($tokens as $t) {
            $t = ia_norm_search_text($t);
            if ($t !== '' && str_contains($blob, $t)) {
                $score += 1;
            }
        }
        if ($score > 0) {
            $doc['score'] = $score;
            $scored[] = $doc;
        }
    }
    usort($scored, static fn($a, $b) => ($b['score'] <=> $a['score']) ?: strcmp((string)$a['titulo'], (string)$b['titulo']));
    return array_slice($scored, 0, 10);
}

function ia_is_inventory_question(string $question): bool
{
    $q = ia_norm_search_text($question);
    if (preg_match('/\b(tenes|tienes|hay|existe|estan|esta|cargad|subid|disponible|lista|listame|mostrar|mostrame|que reglamentos)\b/u', $q)) {
        return true;
    }
    return (bool)ia_extract_regulation_codes($question) && !preg_match('/\b(que dice|establece|resumi|explica|articulo|capitulo|procedimiento|obligacion|mision)\b/u', $q);
}

function ia_answer_inventory(PDO $pdo, int $unidadId, string $question, array $docIds = []): array
{
    $matches = ia_find_docs($pdo, $unidadId, $question, $docIds);
    $codes = ia_extract_regulation_codes($question);

    if ($codes && !$matches) {
        return [
            'answer' => "No, no encontré cargado un reglamento que coincida con " . implode(', ', $codes) . " en DOCUMENTACION.\n\nPuede estar con otro nombre de archivo, o puede faltar cargarlo en la carpeta de documentación.",
            'sources' => [],
        ];
    }

    if (!$matches) {
        $st = $pdo->prepare("
            SELECT COUNT(*) AS total,
                   SUM(estado = 'indexado') AS indexados,
                   SUM(estado <> 'indexado') AS sin_texto
            FROM ia_reglamentos
            WHERE unidad_id = :uid
        ");
        $st->execute([':uid' => $unidadId]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'indexados' => 0, 'sin_texto' => 0];
        return [
            'answer' => "Tengo " . (int)$row['total'] . " PDFs detectados en DOCUMENTACION: " . (int)$row['indexados'] . " indexados para consulta y " . (int)$row['sin_texto'] . " sin texto extraíble.\n\nPreguntame por un código concreto, por ejemplo: “tenés cargado el RFP 51-01?” o “qué dice el RFP 70-01 sobre guardia?”.",
            'sources' => [],
        ];
    }

    $lines = [];
    $lines[] = $codes ? "Sí, encontré coincidencias para " . implode(', ', $codes) . ":" : "Encontré estos reglamentos relacionados:";
    $sources = [];
    foreach ($matches as $i => $doc) {
        $state = (string)$doc['estado'];
        $usable = $state === 'indexado' ? 'listo para preguntar' : 'detectado, pero sin texto extraíble';
        $lines[] = "";
        $lines[] = ($i + 1) . ". " . (string)$doc['titulo'];
        $lines[] = "   Estado: {$usable}.";
        $lines[] = "   Ruta: " . (string)$doc['ruta_rel'];
        if (!empty($doc['error'])) {
            $lines[] = "   Nota: " . (string)$doc['error'];
        }
        $sources[] = [
            'reglamento_id' => (int)$doc['id'],
            'titulo' => (string)$doc['titulo'],
            'fragmento' => 0,
        ];
    }

    $lines[] = "";
    $lines[] = "Si querés, preguntame ahora algo puntual de ese reglamento y lo busco dentro del texto indexado.";
    return ['answer' => implode("\n", $lines), 'sources' => $sources];
}

function ia_index_reglamento(PDO $pdo, int $regId, int $unidadId, string $text): void
{
    $pdo->prepare("DELETE FROM ia_reglamento_fragmentos WHERE reglamento_id = :id")->execute([':id' => $regId]);
    $stmt = $pdo->prepare("
        INSERT INTO ia_reglamento_fragmentos (reglamento_id, unidad_id, chunk_index, texto, keywords)
        VALUES (:rid, :uid, :idx, :texto, :keywords)
    ");
    foreach (ia_make_chunks($text) as $i => $chunk) {
        $stmt->execute([
            ':rid' => $regId,
            ':uid' => $unidadId,
            ':idx' => $i,
            ':texto' => $chunk,
            ':keywords' => implode(' ', ia_keywords($chunk)),
        ]);
    }
}

function ia_sync_documentacion_pdfs(PDO $pdo, array $ctx): array
{
    $paths = ia_storage_paths($ctx);
    $baseReal = realpath($paths['abs_dir']);
    if ($baseReal === false || !is_dir($baseReal)) {
        return ['found' => 0, 'indexed' => 0, 'skipped' => 0, 'errors' => 0];
    }

    $unidadId = (int)$ctx['unidad_id'];
    $personalId = (int)$ctx['personal_id'];

    $knownHashes = [];
    $knownRoutes = [];
    try {
        $st = $pdo->prepare("SELECT sha1_hash, ruta_rel FROM ia_reglamentos WHERE unidad_id = :uid");
        $st->execute([':uid' => $unidadId]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $knownHashes[(string)$row['sha1_hash']] = true;
            $knownRoutes[(string)$row['ruta_rel']] = true;
        }
    } catch (Throwable $e) {
    }

    $stats = ['found' => 0, 'indexed' => 0, 'skipped' => 0, 'errors' => 0];
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($baseReal, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($it as $fileInfo) {
        if (!$fileInfo->isFile() || strtolower($fileInfo->getExtension()) !== 'pdf') {
            continue;
        }

        $stats['found']++;
        $abs = $fileInfo->getPathname();
        $relInside = ltrim(str_replace('\\', '/', substr($abs, strlen($baseReal))), '/');
        $rutaRel = rtrim($paths['rel_dir'], '/') . '/' . $relInside;

        if (isset($knownRoutes[$rutaRel])) {
            $stats['skipped']++;
            continue;
        }

        $sha = @sha1_file($abs);
        if (!is_string($sha) || $sha === '') {
            $stats['errors']++;
            continue;
        }
        if (isset($knownHashes[$sha])) {
            $stats['skipped']++;
            $knownRoutes[$rutaRel] = true;
            continue;
        }

        $original = $fileInfo->getFilename();
        $titulo = preg_replace('/\.pdf$/i', '', str_replace(['_', '-'], ' ', $original)) ?? $original;
        $text = ia_pdf_extract_text($abs);
        $estado = mb_strlen($text, 'UTF-8') >= 120 ? 'indexado' : 'sin_texto';
        $error = $estado === 'sin_texto'
            ? 'No se pudo extraer texto suficiente. Puede ser un PDF escaneado en imagen.'
            : null;

        try {
            $pdo->beginTransaction();
            $st = $pdo->prepare("
                INSERT INTO ia_reglamentos
                    (unidad_id, titulo, archivo_original, ruta_rel, mime, size_bytes, sha1_hash, texto, estado, error, uploaded_by)
                VALUES
                    (:uid, :titulo, :orig, :ruta, 'application/pdf', :size, :sha, :texto, :estado, :error, :uploaded_by)
            ");
            $st->execute([
                ':uid' => $unidadId,
                ':titulo' => trim($titulo),
                ':orig' => $original,
                ':ruta' => $rutaRel,
                ':size' => (int)$fileInfo->getSize(),
                ':sha' => $sha,
                ':texto' => $text !== '' ? $text : null,
                ':estado' => $estado,
                ':error' => $error,
                ':uploaded_by' => $personalId > 0 ? $personalId : null,
            ]);
            $regId = (int)$pdo->lastInsertId();
            if ($estado === 'indexado') {
                ia_index_reglamento($pdo, $regId, $unidadId, $text);
                $stats['indexed']++;
            } else {
                $stats['errors']++;
            }
            $pdo->commit();
            $knownHashes[$sha] = true;
            $knownRoutes[$rutaRel] = true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $stats['errors']++;
        }
    }

    return $stats;
}

function ia_search_fragments(PDO $pdo, int $unidadId, string $question, array $docIds = [], int $limit = 6): array
{
    $codedDocs = ia_find_docs($pdo, $unidadId, $question, $docIds);
    if ($codedDocs && ia_extract_regulation_codes($question)) {
        $docIds = array_map(static fn($doc) => (int)$doc['id'], array_filter($codedDocs, static fn($doc) => (string)($doc['estado'] ?? '') === 'indexado'));
    }

    $tokens = ia_keywords($question);
    if (!$tokens) {
        $tokens = array_values(array_filter(preg_split('/\s+/u', mb_strtolower($question, 'UTF-8')) ?: [], static fn($w) => mb_strlen($w, 'UTF-8') >= 3));
    }

    $params = [':uid' => $unidadId];
    $docSql = '';
    if ($docIds) {
        $in = [];
        foreach ($docIds as $i => $id) {
            $key = ':doc' . $i;
            $in[] = $key;
            $params[$key] = (int)$id;
        }
        $docSql = ' AND r.id IN (' . implode(',', $in) . ') ';
    }

    $st = $pdo->prepare("
        SELECT f.id, f.reglamento_id, f.chunk_index, f.texto, f.keywords, r.titulo
        FROM ia_reglamento_fragmentos f
        INNER JOIN ia_reglamentos r ON r.id = f.reglamento_id
        WHERE f.unidad_id = :uid
          AND r.estado = 'indexado'
          {$docSql}
        ORDER BY r.created_at DESC, f.chunk_index ASC
        LIMIT 1200
    ");
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $scored = [];
    foreach ($rows as $row) {
        $titleBlob = ia_norm_search_text((string)$row['titulo']);
        $hay = ia_norm_search_text((string)$row['titulo'] . ' ' . (string)$row['keywords'] . ' ' . (string)$row['texto']);
        $score = 0;
        foreach ($tokens as $t) {
            $t = ia_norm_search_text((string)$t);
            if ($t === '') {
                continue;
            }
            $count = mb_substr_count($hay, $t, 'UTF-8');
            if ($count > 0) {
                $score += 2 + min(8, $count);
            }
            if (str_contains($titleBlob, $t)) {
                $score += 10;
            }
        }
        if ($score > 0) {
            $row['score'] = $score;
            $scored[] = $row;
        }
    }

    usort($scored, static fn($a, $b) => ($b['score'] <=> $a['score']) ?: strcmp((string)$a['titulo'], (string)$b['titulo']));
    return array_slice($scored, 0, $limit);
}

function ia_best_sentences(string $text, array $tokens, int $max = 3): array
{
    $sentences = preg_split('/(?<=[\.\?\!;:])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $ranked = [];
    foreach ($sentences as $sentence) {
        $s = trim($sentence);
        if (mb_strlen($s, 'UTF-8') < 35) {
            continue;
        }
        $hay = mb_strtolower($s, 'UTF-8');
        $score = 0;
        foreach ($tokens as $t) {
            $score += mb_substr_count($hay, mb_strtolower($t, 'UTF-8'), 'UTF-8');
        }
        if ($score > 0) {
            $ranked[] = ['s' => $s, 'score' => $score];
        }
    }
    usort($ranked, static fn($a, $b) => $b['score'] <=> $a['score']);
    return array_map(static fn($x) => $x['s'], array_slice($ranked, 0, $max));
}

function ia_build_answer(string $question, array $fragments): array
{
    if (!$fragments) {
        return [
            'answer' => "No encontré una coincidencia clara en los reglamentos cargados. Probá con términos más específicos o cargá el PDF correspondiente.",
            'sources' => [],
        ];
    }

    $tokens = ia_keywords($question);
    $lines = [];
    $lines[] = "Según los reglamentos cargados, encontré estas referencias relevantes:";
    $sources = [];

    foreach ($fragments as $i => $f) {
        $sentences = ia_best_sentences((string)$f['texto'], $tokens, 2);
        if (!$sentences) {
            $sentences = [mb_substr((string)$f['texto'], 0, 360, 'UTF-8') . '...'];
        }
        $n = $i + 1;
        $title = (string)$f['titulo'];
        $lines[] = "";
        $lines[] = "{$n}. {$title}";
        foreach ($sentences as $s) {
            $lines[] = "- " . trim($s);
        }
        $sources[] = [
            'reglamento_id' => (int)$f['reglamento_id'],
            'titulo' => $title,
            'fragmento' => (int)$f['chunk_index'] + 1,
        ];
    }

    $lines[] = "";
    $lines[] = "Lectura rápida: usá esas referencias como base normativa. Si querés una respuesta más cerrada, preguntame con el término exacto, por ejemplo \"qué establece sobre ...\".";

    return ['answer' => implode("\n", $lines), 'sources' => $sources];
}
