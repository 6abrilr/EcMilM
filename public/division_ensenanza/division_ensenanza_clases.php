<?php
// public/division_ensenanza/division_ensenanza_clases.php
// Panel administrador de Division Ensenanza con estilo similar a â€œMis cursosâ€.
declare(strict_types=1);

require_once __DIR__ . '/../../auth/bootstrap.php';
require_login();
require_once __DIR__ . '/../../config/db.php';

function de_e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function de_norm(string $v): string {
    $v = trim($v);
    $v = preg_replace('/[^\pL\pN\s._()\-\/]+/u', '', $v) ?? '';
    $v = preg_replace('/\s+/', ' ', $v) ?? '';
    return trim($v);
}
function de_dni(string $v): string { return preg_replace('/\D+/', '', $v) ?? ''; }
function de_col(PDO $pdo, string $table, string $col): bool {
    $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c");
    $st->execute([':t'=>$table, ':c'=>$col]);
    return (int)$st->fetchColumn() > 0;
}
function de_safe_file(string $name): string {
    $base = pathinfo($name, PATHINFO_FILENAME);
    $ext  = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
    $base = de_norm($base);
    $base = preg_replace('/\s+/', '_', $base) ?: 'archivo';
    $base = trim($base, '._-');
    if ($base === '') $base = 'archivo';
    return $base . '_' . date('Ymd_His') . ($ext !== '' ? '.' . $ext : '');
}
function de_mkdir(string $dir): void {
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('No se pudo crear la carpeta de almacenamiento.');
    }
}
function de_schema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS ens_cursos (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        unidad_id INT NOT NULL DEFAULT 1,
        anio SMALLINT NOT NULL,
        nombre VARCHAR(190) NOT NULL,
        descripcion TEXT NULL,
        fecha_inicio DATE NULL,
        fecha_fin DATE NULL,
        estado ENUM('planificado','en_curso','finalizado','archivado') NOT NULL DEFAULT 'planificado',
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY(id), KEY idx_ens_cursos_anio(unidad_id, anio, estado)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS ens_profesores (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        unidad_id INT NOT NULL DEFAULT 1,
        anio SMALLINT NULL,
        personal_id INT NULL,
        grado VARCHAR(40) NULL,
        apellido_nombre VARCHAR(190) NOT NULL,
        dni VARCHAR(30) NULL,
        fuerza VARCHAR(80) NULL,
        unidad_origen VARCHAR(160) NULL,
        destino VARCHAR(160) NULL,
        especialidad VARCHAR(160) NULL,
        cargo VARCHAR(160) NULL,
        telefono VARCHAR(80) NULL,
        email VARCHAR(160) NULL,
        activo TINYINT(1) NOT NULL DEFAULT 1,
        observaciones TEXT NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY(id), KEY idx_ens_profesores_unidad(unidad_id, activo), KEY idx_ens_profesores_anio(anio)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS ens_profesor_cursos (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        unidad_id INT NOT NULL DEFAULT 1,
        profesor_id INT UNSIGNED NOT NULL,
        curso_id INT UNSIGNED NOT NULL,
        rol VARCHAR(120) NULL,
        activo TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(id), UNIQUE KEY uq_ens_prof_curso(unidad_id, profesor_id, curso_id),
        KEY idx_prof(profesor_id), KEY idx_curso(curso_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS ens_cursantes (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        unidad_id INT NOT NULL DEFAULT 1,
        curso_id INT UNSIGNED NOT NULL,
        personal_id INT NULL,
        grado VARCHAR(40) NULL,
        apellido_nombre VARCHAR(190) NOT NULL,
        dni VARCHAR(30) NULL,
        fuerza VARCHAR(80) NULL,
        unidad_origen VARCHAR(160) NULL,
        destino VARCHAR(160) NULL,
        rol_curso VARCHAR(120) NULL,
        estado ENUM('inscripto','regular','aprobado','desaprobado','baja') NOT NULL DEFAULT 'inscripto',
        observaciones TEXT NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY(id), KEY idx_ens_cursantes_curso(curso_id), KEY idx_ens_cursantes_personal(personal_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    foreach ([
        'fuerza' => "ALTER TABLE ens_cursantes ADD COLUMN fuerza VARCHAR(80) NULL AFTER dni",
        'unidad_origen' => "ALTER TABLE ens_cursantes ADD COLUMN unidad_origen VARCHAR(160) NULL AFTER fuerza",
        'destino' => "ALTER TABLE ens_cursantes ADD COLUMN destino VARCHAR(160) NULL AFTER unidad_origen",
        'rol_curso' => "ALTER TABLE ens_cursantes ADD COLUMN rol_curso VARCHAR(120) NULL AFTER destino",
        'observaciones' => "ALTER TABLE ens_cursantes ADD COLUMN observaciones TEXT NULL AFTER estado"
    ] as $col => $sql) if (!de_col($pdo, 'ens_cursantes', $col)) $pdo->exec($sql);

    $pdo->exec("CREATE TABLE IF NOT EXISTS ens_clases (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        unidad_id INT NOT NULL DEFAULT 1,
        curso_id INT UNSIGNED NULL,
        anio SMALLINT NOT NULL,
        titulo VARCHAR(190) NOT NULL,
        tipo VARCHAR(40) NOT NULL DEFAULT 'Material',
        fecha DATE NULL,
        descripcion TEXT NULL,
        archivo_rel VARCHAR(500) NULL,
        archivo_nombre VARCHAR(255) NULL,
        uploaded_by VARCHAR(190) NULL,
        visible_cursante TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(id), KEY idx_ens_clases_curso(curso_id), KEY idx_ens_clases_anio(unidad_id, anio, fecha)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    if (!de_col($pdo, 'ens_clases', 'visible_cursante')) $pdo->exec("ALTER TABLE ens_clases ADD COLUMN visible_cursante TINYINT(1) NOT NULL DEFAULT 1 AFTER uploaded_by");

    $pdo->exec("CREATE TABLE IF NOT EXISTS ens_calificaciones (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        unidad_id INT NOT NULL DEFAULT 1,
        curso_id INT UNSIGNED NOT NULL,
        cursante_id INT UNSIGNED NOT NULL,
        actividad VARCHAR(190) NOT NULL,
        nota DECIMAL(5,2) NOT NULL,
        fecha DATE NULL,
        observaciones TEXT NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(id), KEY idx_calif_curso(curso_id), KEY idx_calif_cursante(cursante_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
function de_user_name(): string {
    $u = function_exists('current_user') ? current_user() : ($_SESSION['user'] ?? []);
    $u = is_array($u) ? $u : [];
    return trim((string)($u['apellido_nombre'] ?? $u['nombre_completo'] ?? $u['nombre'] ?? $u['username'] ?? 'Usuario'));
}
function de_initials(string $name): string {
    $ini = '';
    foreach (preg_split('/\s+/', trim($name)) ?: [] as $p) {
        if ($p !== '') $ini .= mb_strtoupper(mb_substr($p,0,1,'UTF-8'),'UTF-8');
        if (mb_strlen($ini,'UTF-8') >= 2) break;
    }
    return $ini ?: 'AD';
}
function de_count(PDO $pdo, string $sql, array $params): int {
    $st = $pdo->prepare($sql); $st->execute($params); return (int)($st->fetchColumn() ?: 0);
}
function de_current_dni(): string {
    $u = function_exists('current_user') ? current_user() : ($_SESSION['user'] ?? []);
    $u = is_array($u) ? $u : [];
    return de_dni((string)($u['dni'] ?? $u['username'] ?? ''));
}
function de_is_ens_admin(string $dni): bool {
    return in_array($dni, ['37087925','38046559','35311578','41366154'], true);
}

$unidadId = (int)($_SESSION['unidad_id'] ?? 1);
$anio = (int)($_GET['anio'] ?? date('Y'));
if ($anio < 2000 || $anio > 2100) $anio = (int)date('Y');
$cursoId = (int)($_GET['curso_id'] ?? 0);
$q = trim((string)($_GET['q'] ?? ''));
$sort = (string)($_GET['sort'] ?? 'nombre');
$tab = (string)($_GET['tab'] ?? 'resumen');
$csrf = function_exists('csrf_token') ? (string)csrf_token() : '';

$ROOT_FS = realpath(__DIR__ . '/../..') ?: dirname(__DIR__, 2);
$areaFolder = 'DIVISION-ENSE' . "\xC3\x91" . 'ANZA';
$storageRoot = $ROOT_FS . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'unidades' . DIRECTORY_SEPARATOR . 'ecmilm' . DIRECTORY_SEPARATOR . $areaFolder;
de_mkdir($storageRoot);
foreach (['Cursos','Clases PowerPoint','Cursantes','Calificaciones','Documentacion general'] as $f) de_mkdir($storageRoot . DIRECTORY_SEPARATOR . $f);

de_schema($pdo);

$currentDni = de_current_dni();
$isEnsAdmin = de_is_ens_admin($currentDni);
if (!$isEnsAdmin) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        http_response_code(403);
        exit('No tenes permisos de administrador en Division Ensenanza.');
    }
    $qs = http_build_query(['anio' => $anio, 'curso_id' => $cursoId, 'q' => $q, 'sort' => $sort]);
    header('Location: division_ensenanza_mis_cursos.php?' . $qs);
    exit;
}
if (!in_array($tab, ['mis_cursos','cursos','instructores','cursantes'], true)) $tab = 'mis_cursos';

$ok = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (function_exists('csrf_verify')) csrf_verify();
        $action = (string)($_POST['action'] ?? '');
        if (!in_array($action, ['curso_add','curso_delete','profesor_add','profesor_asignar','profesor_delete','cursante_add'], true)) {
            throw new RuntimeException('Accion no permitida para este panel.');
        }
        $anioPost = (int)($_POST['anio'] ?? $anio);
        if ($anioPost < 2000 || $anioPost > 2100) $anioPost = $anio;

        if ($action === 'curso_add') {
            $nombre = de_norm((string)($_POST['nombre'] ?? ''));
            if ($nombre === '') throw new RuntimeException('El curso necesita un nombre.');
            $st = $pdo->prepare("INSERT INTO ens_cursos (unidad_id, anio, nombre, descripcion, fecha_inicio, fecha_fin, estado) VALUES (:uid,:anio,:nom,:des,:fi,:ff,:est)");
            $st->execute([':uid'=>$unidadId, ':anio'=>$anioPost, ':nom'=>$nombre, ':des'=>trim((string)($_POST['descripcion'] ?? '')) ?: null, ':fi'=>$_POST['fecha_inicio'] ?: null, ':ff'=>$_POST['fecha_fin'] ?: null, ':est'=>$_POST['estado'] ?? 'planificado']);
            $cursoId = (int)$pdo->lastInsertId(); $ok = 'Curso creado correctamente.'; $tab = 'cursos';
        }
        if ($action === 'profesor_add') {
            $nombre = de_norm((string)($_POST['apellido_nombre'] ?? ''));
            if ($nombre === '') throw new RuntimeException('El instructor necesita apellido y nombre.');
            $st = $pdo->prepare("INSERT INTO ens_profesores (unidad_id, anio, grado, apellido_nombre, dni, fuerza, unidad_origen, destino, especialidad, cargo, telefono, email, activo, observaciones) VALUES (:uid,:anio,:grado,:nom,:dni,:fuerza,:uo,:dest,:esp,:cargo,:tel,:email,1,:obs)");
            $st->execute([':uid'=>$unidadId, ':anio'=>$anioPost, ':grado'=>de_norm($_POST['grado'] ?? '') ?: null, ':nom'=>$nombre, ':dni'=>de_dni($_POST['dni'] ?? '') ?: null, ':fuerza'=>de_norm($_POST['fuerza'] ?? '') ?: null, ':uo'=>de_norm($_POST['unidad_origen'] ?? '') ?: null, ':dest'=>de_norm($_POST['destino'] ?? '') ?: null, ':esp'=>de_norm($_POST['especialidad'] ?? '') ?: null, ':cargo'=>de_norm($_POST['cargo'] ?? 'Instructor') ?: 'Instructor', ':tel'=>de_norm($_POST['telefono'] ?? '') ?: null, ':email'=>filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL) ?: null, ':obs'=>trim((string)($_POST['observaciones'] ?? '')) ?: null]);
            $profesorId = (int)$pdo->lastInsertId();
            $cursoPost = (int)($_POST['curso_id'] ?? 0);
            if ($cursoPost > 0) {
                $st = $pdo->prepare("INSERT IGNORE INTO ens_profesor_cursos (unidad_id, profesor_id, curso_id, rol, activo) VALUES (:uid,:pid,:cid,:rol,1)");
                $st->execute([':uid'=>$unidadId, ':pid'=>$profesorId, ':cid'=>$cursoPost, ':rol'=>de_norm($_POST['rol'] ?? 'Instructor') ?: 'Instructor']);
                $cursoId = $cursoPost;
            }
            $ok = 'Instructor agregado correctamente.'; $tab = 'instructores';
        }
        if ($action === 'profesor_asignar') {
            $profesorId = (int)($_POST['profesor_id'] ?? 0); $cursoPost = (int)($_POST['curso_id'] ?? 0);
            if ($profesorId <= 0 || $cursoPost <= 0) throw new RuntimeException('Selecciona instructor y curso.');
            $st = $pdo->prepare("INSERT INTO ens_profesor_cursos (unidad_id, profesor_id, curso_id, rol, activo) VALUES (:uid,:pid,:cid,:rol,1) ON DUPLICATE KEY UPDATE rol=VALUES(rol), activo=1");
            $st->execute([':uid'=>$unidadId, ':pid'=>$profesorId, ':cid'=>$cursoPost, ':rol'=>de_norm($_POST['rol'] ?? 'Instructor') ?: 'Instructor']);
            $cursoId = $cursoPost; $ok = 'Instructor asignado al curso.'; $tab = 'instructores';
        }
        if ($action === 'profesor_delete') {
            $profesorId = (int)($_POST['profesor_id'] ?? 0);
            if ($profesorId <= 0) throw new RuntimeException('Selecciona un instructor.');
            $pdo->prepare("UPDATE ens_profesores SET activo=0 WHERE id=:id AND unidad_id=:uid LIMIT 1")->execute([':id'=>$profesorId, ':uid'=>$unidadId]);
            $pdo->prepare("UPDATE ens_profesor_cursos SET activo=0 WHERE profesor_id=:id AND unidad_id=:uid")->execute([':id'=>$profesorId, ':uid'=>$unidadId]);
            $ok = 'Instructor eliminado de la lista disponible.'; $tab = 'instructores';
        }
        if ($action === 'curso_delete') {
            $cursoPost = (int)($_POST['curso_id'] ?? 0);
            if ($cursoPost <= 0) throw new RuntimeException('Selecciona un curso.');
            $pdo->prepare("UPDATE ens_cursos SET estado='archivado' WHERE unidad_id=:uid AND id=:cid LIMIT 1")->execute([':uid'=>$unidadId, ':cid'=>$cursoPost]);
            $cursoId = 0; $ok = 'Curso archivado correctamente. Las listas asociadas no fueron eliminadas.'; $tab = 'cursos';
        }
        if ($action === 'cursante_add') {
            $cursoPost = (int)($_POST['curso_id'] ?? 0); if ($cursoPost <= 0) throw new RuntimeException('Selecciona un curso.');
            $personalId = (int)($_POST['personal_id'] ?? 0);
            $grado = de_norm($_POST['grado'] ?? ''); $nombre = de_norm($_POST['apellido_nombre'] ?? ''); $dni = de_dni($_POST['dni'] ?? '');
            if ($personalId > 0) {
                $stp = $pdo->prepare("SELECT id, grado, apellido_nombre, dni FROM personal_unidad WHERE id=:id LIMIT 1");
                $stp->execute([':id'=>$personalId]);
                if ($p = $stp->fetch(PDO::FETCH_ASSOC)) { $grado = (string)($p['grado'] ?? $grado); $nombre = (string)($p['apellido_nombre'] ?? $nombre); $dni = de_dni((string)($p['dni'] ?? $dni)); }
            }
            if ($nombre === '') throw new RuntimeException('El cursante necesita apellido y nombre.');
            $st = $pdo->prepare("INSERT INTO ens_cursantes (unidad_id, curso_id, personal_id, grado, apellido_nombre, dni, fuerza, unidad_origen, destino, rol_curso, estado, observaciones) VALUES (:uid,:cid,:pid,:grado,:nom,:dni,:fuerza,:uo,:dest,:rol,:est,:obs)");
            $st->execute([':uid'=>$unidadId, ':cid'=>$cursoPost, ':pid'=>$personalId ?: null, ':grado'=>$grado ?: null, ':nom'=>$nombre, ':dni'=>$dni ?: null, ':fuerza'=>de_norm($_POST['fuerza'] ?? '') ?: null, ':uo'=>de_norm($_POST['unidad_origen'] ?? '') ?: null, ':dest'=>de_norm($_POST['destino'] ?? '') ?: null, ':rol'=>de_norm($_POST['rol_curso'] ?? '') ?: null, ':est'=>$_POST['estado'] ?? 'inscripto', ':obs'=>trim((string)($_POST['observaciones'] ?? '')) ?: null]);
            $cursoId = $cursoPost; $ok = 'Cursante agregado.'; $tab = 'cursantes';
        }
        if ($action === 'clase_add') {
            $cursoPost = (int)($_POST['curso_id'] ?? 0);
            $titulo = de_norm($_POST['titulo'] ?? ''); if ($titulo === '') throw new RuntimeException('El material necesita un titulo.');
            $rel = null; $fileName = null;
            if (!empty($_FILES['archivo']['name']) && is_uploaded_file((string)$_FILES['archivo']['tmp_name'])) {
                $allowed = ['ppt','pptx','pdf','doc','docx','xls','xlsx','jpg','jpeg','png','zip','rar'];
                $ext = strtolower((string)pathinfo((string)$_FILES['archivo']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed, true)) throw new RuntimeException('Formato no permitido.');
                $folderRel = 'Clases PowerPoint/' . $anioPost . ($cursoPost > 0 ? '/Curso_' . $cursoPost : '');
                $dir = $storageRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $folderRel);
                de_mkdir($dir); $fileName = de_safe_file((string)$_FILES['archivo']['name']);
                if (!move_uploaded_file((string)$_FILES['archivo']['tmp_name'], $dir . DIRECTORY_SEPARATOR . $fileName)) throw new RuntimeException('No se pudo guardar el archivo.');
                $rel = $folderRel . '/' . $fileName;
            }
            $st = $pdo->prepare("INSERT INTO ens_clases (unidad_id, curso_id, anio, titulo, tipo, fecha, descripcion, archivo_rel, archivo_nombre, uploaded_by, visible_cursante) VALUES (:uid,:cid,:anio,:tit,:tipo,:fecha,:des,:rel,:file,:usr,:vis)");
            $st->execute([':uid'=>$unidadId, ':cid'=>$cursoPost ?: null, ':anio'=>$anioPost, ':tit'=>$titulo, ':tipo'=>de_norm($_POST['tipo'] ?? 'Material') ?: 'Material', ':fecha'=>$_POST['fecha'] ?: null, ':des'=>trim((string)($_POST['descripcion'] ?? '')) ?: null, ':rel'=>$rel, ':file'=>$fileName, ':usr'=>de_user_name(), ':vis'=>isset($_POST['visible_cursante']) ? 1 : 0]);
            $cursoId = $cursoPost; $ok = 'Contenido cargado correctamente.'; $tab = 'contenidos';
        }
        if ($action === 'calificacion_add') {
            $cursoPost = (int)($_POST['curso_id'] ?? 0); $cursanteId = (int)($_POST['cursante_id'] ?? 0);
            $actividad = de_norm($_POST['actividad'] ?? ''); $nota = (float)str_replace(',', '.', (string)($_POST['nota'] ?? '0'));
            if ($cursoPost <= 0 || $cursanteId <= 0) throw new RuntimeException('Selecciona curso y cursante.');
            if ($actividad === '') throw new RuntimeException('La calificacion necesita actividad.');
            if ($nota < 0 || $nota > 10) throw new RuntimeException('La nota debe estar entre 0 y 10.');
            $st = $pdo->prepare("INSERT INTO ens_calificaciones (unidad_id, curso_id, cursante_id, actividad, nota, fecha, observaciones) VALUES (:uid,:cid,:cur,:act,:nota,:fecha,:obs)");
            $st->execute([':uid'=>$unidadId, ':cid'=>$cursoPost, ':cur'=>$cursanteId, ':act'=>$actividad, ':nota'=>$nota, ':fecha'=>$_POST['fecha'] ?: null, ':obs'=>trim((string)($_POST['observaciones'] ?? '')) ?: null]);
            $cursoId = $cursoPost; $ok = 'Calificacion registrada.'; $tab = 'calificaciones';
        }
        $anio = $anioPost;
    } catch (Throwable $ex) { $err = $ex->getMessage(); }
}

$where = "unidad_id=:uid AND anio=:anio AND estado <> 'archivado'"; $params = [':uid'=>$unidadId, ':anio'=>$anio];
if ($q !== '') { $where .= " AND (nombre LIKE :q OR descripcion LIKE :q)"; $params[':q'] = '%' . $q . '%'; }
$order = $sort === 'fecha' ? "COALESCE(fecha_inicio, created_at) DESC, nombre ASC" : "nombre ASC";
$st = $pdo->prepare("SELECT c.*, (SELECT COUNT(*) FROM ens_cursantes cu WHERE cu.curso_id=c.id AND cu.unidad_id=c.unidad_id) cursantes_count, (SELECT COUNT(*) FROM ens_clases cl WHERE cl.curso_id=c.id AND cl.unidad_id=c.unidad_id) clases_count, (SELECT COUNT(*) FROM ens_profesor_cursos pc WHERE pc.curso_id=c.id AND pc.unidad_id=c.unidad_id AND pc.activo=1) instructores_count FROM ens_cursos c WHERE $where ORDER BY $order");
$st->execute($params); $cursos = $st->fetchAll(PDO::FETCH_ASSOC);
$currentPersonalId = 0;
try {
    if ($currentDni !== '') {
        $st = $pdo->prepare("SELECT id FROM personal_unidad WHERE REPLACE(REPLACE(REPLACE(dni,'.',''),'-',''),' ','')=:dni LIMIT 1");
        $st->execute([':dni'=>$currentDni]);
        $currentPersonalId = (int)($st->fetchColumn() ?: 0);
    }
} catch (Throwable $ex) {}
$currentProfesorId = 0;
try {
    $st = $pdo->prepare("SELECT id FROM ens_profesores WHERE unidad_id=:uid AND activo=1 AND ((:pid > 0 AND personal_id=:pid) OR (:dni <> '' AND REPLACE(REPLACE(REPLACE(COALESCE(dni,''),'.',''),'-',''),' ','')=:dni)) ORDER BY id DESC LIMIT 1");
    $st->execute([':uid'=>$unidadId, ':pid'=>$currentPersonalId, ':dni'=>$currentDni]);
    $currentProfesorId = (int)($st->fetchColumn() ?: 0);
} catch (Throwable $ex) {}
$misCursos = [];
if ($currentProfesorId > 0) {
    $st = $pdo->prepare("SELECT c.*, (SELECT COUNT(*) FROM ens_cursantes cu WHERE cu.curso_id=c.id AND cu.unidad_id=c.unidad_id) cursantes_count, (SELECT COUNT(*) FROM ens_clases cl WHERE cl.curso_id=c.id AND cl.unidad_id=c.unidad_id) clases_count, (SELECT COUNT(*) FROM ens_profesor_cursos pc2 WHERE pc2.curso_id=c.id AND pc2.unidad_id=c.unidad_id AND pc2.activo=1) instructores_count FROM ens_profesor_cursos pc INNER JOIN ens_cursos c ON c.id=pc.curso_id AND c.unidad_id=pc.unidad_id WHERE pc.unidad_id=:uid AND pc.profesor_id=:pid AND pc.activo=1 AND c.anio=:anio AND c.estado <> 'archivado' ORDER BY $order");
    $st->execute([':uid'=>$unidadId, ':pid'=>$currentProfesorId, ':anio'=>$anio]);
    $misCursos = $st->fetchAll(PDO::FETCH_ASSOC);
}
$cardCursos = ($tab === 'mis_cursos') ? $misCursos : $cursos;
$misCursoIds = array_map('intval', array_column($misCursos, 'id'));
if ($tab === 'mis_cursos' && $cursoId > 0 && !in_array($cursoId, $misCursoIds, true)) {
    $cursoId = $cardCursos ? (int)$cardCursos[0]['id'] : 0;
}
if ($cursoId <= 0 && $cardCursos) $cursoId = (int)$cardCursos[0]['id'];
$cursoActual = null; foreach ($cursos as $c) if ((int)$c['id'] === $cursoId) { $cursoActual = $c; break; }

$profesores = $pdo->prepare("SELECT * FROM ens_profesores WHERE unidad_id=:uid AND activo=1 ORDER BY apellido_nombre ASC");
$profesores->execute([':uid'=>$unidadId]); $profesores = $profesores->fetchAll(PDO::FETCH_ASSOC);

$cursantes = []; $profCurso = []; $calificaciones = []; $contenidosCurso = [];
if ($cursoId > 0) {
    $st = $pdo->prepare("SELECT * FROM ens_cursantes WHERE unidad_id=:uid AND curso_id=:cid ORDER BY apellido_nombre ASC");
    $st->execute([':uid'=>$unidadId, ':cid'=>$cursoId]); $cursantes = $st->fetchAll(PDO::FETCH_ASSOC);
    $st = $pdo->prepare("SELECT p.*, pc.rol FROM ens_profesor_cursos pc INNER JOIN ens_profesores p ON p.id=pc.profesor_id WHERE pc.unidad_id=:uid AND pc.curso_id=:cid AND pc.activo=1 AND p.activo=1 ORDER BY p.apellido_nombre ASC");
    $st->execute([':uid'=>$unidadId, ':cid'=>$cursoId]); $profCurso = $st->fetchAll(PDO::FETCH_ASSOC);
    $st = $pdo->prepare("SELECT ca.*, cur.grado, cur.apellido_nombre FROM ens_calificaciones ca INNER JOIN ens_cursantes cur ON cur.id=ca.cursante_id WHERE ca.unidad_id=:uid AND ca.curso_id=:cid ORDER BY COALESCE(ca.fecha, DATE(ca.created_at)) DESC, ca.id DESC");
    $st->execute([':uid'=>$unidadId, ':cid'=>$cursoId]); $calificaciones = $st->fetchAll(PDO::FETCH_ASSOC);
    $st = $pdo->prepare("SELECT * FROM ens_clases WHERE unidad_id=:uid AND curso_id=:cid ORDER BY COALESCE(fecha, DATE(created_at)) DESC, id DESC");
    $st->execute([':uid'=>$unidadId, ':cid'=>$cursoId]); $contenidosCurso = $st->fetchAll(PDO::FETCH_ASSOC);
}

$personal = [];
try {
    $st = $pdo->prepare("SELECT id, grado, arma, apellido_nombre, dni FROM personal_unidad WHERE unidad_id=:uid AND apellido_nombre IS NOT NULL AND apellido_nombre<>'' ORDER BY apellido_nombre ASC LIMIT 800");
    $st->execute([':uid'=>$unidadId]); $personal = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $ex) { $personal = []; }

$clasesAnio = $pdo->prepare("SELECT cl.*, c.nombre curso_nombre FROM ens_clases cl LEFT JOIN ens_cursos c ON c.id=cl.curso_id WHERE cl.unidad_id=:uid AND cl.anio=:anio ORDER BY cl.id DESC LIMIT 30");
$clasesAnio->execute([':uid'=>$unidadId, ':anio'=>$anio]); $clasesAnio = $clasesAnio->fetchAll(PDO::FETCH_ASSOC);
$cursantesAnio = de_count($pdo, "SELECT COUNT(*) FROM ens_cursantes cu INNER JOIN ens_cursos c ON c.id=cu.curso_id WHERE cu.unidad_id=:uid AND c.anio=:anio", [':uid'=>$unidadId, ':anio'=>$anio]);
$califAnio = de_count($pdo, "SELECT COUNT(*) FROM ens_calificaciones ca INNER JOIN ens_cursos c ON c.id=ca.curso_id WHERE ca.unidad_id=:uid AND c.anio=:anio", [':uid'=>$unidadId, ':anio'=>$anio]);
$years = range((int)date('Y') + 1, (int)date('Y') - 5);

$SELF_WEB = (string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
$BASE_DIR_WEB = rtrim(str_replace('\\','/', dirname($SELF_WEB)), '/');
$BASE_PUBLIC_WEB = rtrim(str_replace('\\','/', dirname($BASE_DIR_WEB)), '/');
$BASE_APP_WEB = rtrim(str_replace('\\','/', dirname($BASE_PUBLIC_WEB)), '/');
$ESCUDO = $BASE_APP_WEB . '/assets/img/ecmilm.png';
$userName = de_user_name(); $initials = de_initials($userName);
function de_url(array $extra = []): string { global $anio, $cursoId, $q, $sort, $tab; return '?' . http_build_query(array_merge(['anio'=>$anio,'curso_id'=>$cursoId,'q'=>$q,'sort'=>$sort,'tab'=>$tab], $extra)); }
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Division Ensenanza - Clases</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
:root{--nav:#344248;--gold:#e5aa13;--ink:#394650;--muted:#77828b;--line:#e2e6e9;}
body{min-height:100vh;margin:0;background:#eef1f2;color:var(--ink);font-family:Arial,Helvetica,sans-serif}body::before{content:"";position:fixed;inset:0;z-index:-1;opacity:.42;background-image:radial-gradient(circle at 8px 8px,rgba(52,66,72,.12) 0 1.2px,transparent 1.4px);background-size:22px 22px}.topline{height:2px;background:#60a5fa}.mast{height:84px;background:#050505;display:flex;align-items:center;padding:0 5.2%;box-shadow:0 1px 0 rgba(96,165,250,.7);position:relative;overflow:hidden}.brand{display:flex;align-items:center;gap:14px;position:relative;z-index:1}.brand img{width:56px;height:56px;object-fit:contain;filter:drop-shadow(0 0 12px rgba(125,174,255,.28))}.brand-title{font-size:1.22rem;font-weight:900;color:#eef2ff;line-height:1.05;text-shadow:0 0 14px rgba(96,165,250,.45)}.brand-sub{font-size:.86rem;color:#cbd5f5;line-height:1.15;margin-top:5px}.userbar{display:none}.round{display:none}.badge-dot{display:none}.avatar{display:none}.nav-main{height:56px;background:var(--nav);display:flex;align-items:stretch;padding:0 5.1%;gap:0}.nav-main a{display:flex;align-items:center;padding:0 12px;color:#fff;text-decoration:none;font-size:.94rem}.nav-main a.active{border-bottom:3px solid var(--gold);background:rgba(0,0,0,.06)}.nav-main .nav-spacer{flex:1}.nav-main .nav-action{align-self:center;height:34px;margin-left:8px;border:1px solid rgba(255,255,255,.35);border-radius:4px;font-weight:700}.nav-main .logout{background:#198754;border-color:#198754}.page{max-width:1218px;margin:30px auto 60px;padding:0 16px}.page h1{font-size:2rem;margin:0 0 36px;color:#3e4a52;font-weight:700}.panel{background:#fff;border:1px solid var(--line);box-shadow:0 1px 2px rgba(0,0,0,.03);padding:30px}.panel-title{font-size:1.15rem;font-weight:700;margin:0 0 18px}.panel-title::after{content:"";display:block;width:74px;height:2px;background:var(--gold);margin-top:10px}.controls{display:grid;grid-template-columns:110px minmax(220px,360px) 1fr 285px 120px;gap:18px;align-items:center;margin-bottom:28px}.btn-darkish,.selectish{height:40px;background:#5a646b;color:#fff;border:0;border-radius:0;box-shadow:0 2px 5px rgba(0,0,0,.22);padding:0 18px}.search{height:42px;border:0;border-bottom:1px solid #cbd1d5;background:transparent;padding:0 10px;color:#4b5560}.search-x{width:34px;height:34px;border-radius:50%;background:#f1f2f3;color:#8b9298;display:grid;place-items:center;margin-left:-64px;z-index:2;text-decoration:none}.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:22px}.stat{border:1px solid var(--line);background:#fafafa;padding:16px}.stat strong{font-size:1.7rem;display:block}.muted{color:var(--muted)}.cards{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px}.card-course{background:#fff;border:1px solid #e1e4e7;border-radius:2px;box-shadow:0 3px 10px rgba(0,0,0,.12);overflow:hidden;color:#344248;text-decoration:none;min-height:278px;display:flex;flex-direction:column}.card-course.active{outline:3px solid rgba(229,170,19,.55)}.course-art{height:154px;background:linear-gradient(150deg,#ffb000,#ffd34c 54%,#ff8c22);position:relative;overflow:hidden}.course-art::before{content:"";position:absolute;inset:0;background:repeating-linear-gradient(0deg,rgba(255,255,255,.16) 0 2px,transparent 2px 5px),radial-gradient(circle at 20% 34%,rgba(238,91,20,.75) 0 18px,transparent 19px);opacity:.45}.course-art strong{position:absolute;left:22px;top:45px;color:#d86720;font-size:1.5rem;line-height:1.05;text-transform:uppercase;max-width:210px}.wifi{position:absolute;right:14px;bottom:22px;color:#fff;font-size:2.2rem}.card-body{padding:16px 14px 12px;position:relative;flex:1}.card-title{font-size:1rem;font-weight:700;text-align:center;line-height:1.35;min-height:42px}.card-sub{font-size:.88rem;color:#9199a0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.pill{display:inline-flex;align-items:center;padding:3px 8px;border-radius:999px;background:#eef1f2;color:#59646c;font-size:.75rem;font-weight:700}.kebab{position:absolute;right:10px;bottom:10px;width:36px;height:36px;border-radius:50%;background:#e4e9ed;color:#6d747b;display:grid;place-items:center}.grid2{display:grid;grid-template-columns:1fr 1fr;gap:18px}.grid3{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.box{border:1px solid var(--line);background:#fff;padding:18px}.box h2{font-size:1.12rem;font-weight:700;margin:0 0 14px}.form-control,.form-select{border-radius:0}.btn-admin{background:#5a646b;color:#fff;border:0;border-radius:0;box-shadow:0 2px 5px rgba(0,0,0,.18);padding:.55rem 1rem}.btn-admin:hover{background:#48525a;color:#fff}.table{font-size:.92rem}.empty{padding:22px;text-align:center;color:#6b747c;border:1px dashed #c8d0d5;background:#fafafa}.alert{border-radius:0}.item{display:flex;justify-content:space-between;gap:12px;border-bottom:1px solid #edf0f2;padding:11px 0}.item:last-child{border-bottom:0}@media(max-width:1100px){.cards{grid-template-columns:repeat(2,1fr)}.controls{grid-template-columns:1fr 1fr}.stats{grid-template-columns:repeat(2,1fr)}.grid2,.grid3{grid-template-columns:1fr}.mast{height:auto;padding:20px 18px}.nav-main{overflow:auto;padding:0 12px}.userbar{flex-wrap:wrap;justify-content:flex-end}}@media(max-width:650px){.cards,.stats{grid-template-columns:1fr}.controls{grid-template-columns:1fr}.panel{padding:18px}.userbar .name{display:none}.avatar{width:54px;height:54px}.brand-sub{display:none}}
</style>
</head>
<body>
<div class="topline"></div>
<header class="mast">
  <div class="brand"><img src="<?= de_e($ESCUDO) ?>" alt="ECMILM"><div><div class="brand-title">Escuela Militar de Monta&ntilde;a</div><div class="brand-sub">&ldquo;La monta&ntilde;a nos une&rdquo;</div></div></div>
  <div class="userbar"><div class="round"><i class="bi bi-bell"></i></div><div class="round"><i class="bi bi-chat-dots"></i><span class="badge-dot">0</span></div><div class="name"><?= de_e(mb_strtoupper($userName,'UTF-8')) ?></div><div class="avatar"><?= de_e($initials) ?></div></div>
</header>
<nav class="nav-main">
  <?php foreach(['mis_cursos'=>'Mis cursos','cursos'=>'Administrar cursos','instructores'=>'Administrar instructores','cursantes'=>'Administrar cursantes'] as $k=>$label): ?><a class="<?= $tab===$k?'active':'' ?>" href="<?= de_url(['tab'=>$k]) ?>"><?= de_e($label) ?></a><?php endforeach; ?>
  <span class="nav-spacer"></span>
  <a class="nav-action" href="division_ensenanza.php" onclick="if (window.history.length > 1) { history.back(); return false; }">Volver</a>
  <a class="nav-action logout" href="<?= de_e($BASE_APP_WEB) ?>/logout.php">Cerrar sesi&oacute;n</a>
</nav>
<main class="page">
<h1>Divisi&oacute;n Ense&ntilde;anza</h1>
<section class="panel">
  <div class="panel-title">Panel administrador de cursos</div>
  <?php if ($ok): ?><div class="alert alert-success"><?= de_e($ok) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger"><?= de_e($err) ?></div><?php endif; ?>
  <form class="controls" method="get">
    <select class="btn-darkish" name="anio" onchange="this.form.submit()"><?php foreach($years as $y): ?><option value="<?= (int)$y ?>" <?= $y===$anio?'selected':'' ?>><?= (int)$y ?></option><?php endforeach; ?></select>
    <input class="search" name="q" value="<?= de_e($q) ?>" placeholder="Buscar curso">
    <a class="search-x" href="?anio=<?= (int)$anio ?>"><i class="bi bi-x-lg"></i></a>
    <select class="selectish" name="sort" onchange="this.form.submit()"><option value="nombre" <?= $sort==='nombre'?'selected':'' ?>>Ordenar por nombre del curso</option><option value="fecha" <?= $sort==='fecha'?'selected':'' ?>>Ordenar por fecha</option></select>
    <button class="selectish" type="submit">Tarjeta</button>
  </form>
  <div class="stats"><div class="stat"><span class="muted">Cursos</span><strong><?= $tab === 'mis_cursos' ? count($misCursos) : count($cursos) ?></strong></div><div class="stat"><span class="muted">Instructores</span><strong><?= $tab === 'mis_cursos' ? count($profCurso) : count($profesores) ?></strong></div><div class="stat"><span class="muted">Cursantes</span><strong><?= $tab === 'mis_cursos' ? count($cursantes) : $cursantesAnio ?></strong></div><div class="stat"><span class="muted"><?= $tab === 'mis_cursos' ? 'Contenidos' : 'Calificaciones' ?></span><strong><?= $tab === 'mis_cursos' ? count($contenidosCurso) : $califAnio ?></strong></div></div>
  <?php if (!$cardCursos): ?><div class="empty"><?= $tab === 'mis_cursos' ? 'No tenes cursos asignados como instructor para este a&ntilde;o.' : 'No hay cursos cargados para este a&ntilde;o. Usa la opcion Nuevo curso.' ?></div><?php else: ?>
  <div class="cards"><?php foreach($cardCursos as $c): ?><a class="card-course <?= (int)$c['id']===$cursoId?'active':'' ?>" href="<?= de_url(['curso_id'=>(int)$c['id']]) ?>"><div class="course-art"><strong><?= de_e($c['estado']==='en_curso'?'En curso':'Ciclo lectivo') ?></strong><i class="bi bi-wifi wifi"></i></div><div class="card-body"><div class="card-title"><?= de_e($c['nombre']) ?></div><div class="card-sub">ECMILM - <?= de_e($c['descripcion'] ?: 'Divisi&oacute;n Ense&ntilde;anza') ?></div><div class="mt-2"><span class="pill"><?= (int)$c['cursantes_count'] ?> cursantes</span> <span class="pill"><?= (int)$c['instructores_count'] ?> instr.</span> <span class="pill"><?= (int)$c['clases_count'] ?> cont.</span></div><div class="kebab"><i class="bi bi-three-dots-vertical"></i></div></div></a><?php endforeach; ?></div>
  <?php endif; ?>
  <?php if ($tab === 'mis_cursos'): ?>
    <div class="grid2"><div class="box"><h2>Curso seleccionado</h2><?php if($cursoActual): ?><strong><?= de_e($cursoActual['nombre']) ?></strong><p class="muted mb-2"><?= de_e($cursoActual['descripcion'] ?: 'Sin descripcion.') ?></p><div><span class="pill"><?= de_e($cursoActual['estado']) ?></span> <span class="pill"><?= count($cursantes) ?> cursantes</span> <span class="pill"><?= count($profCurso) ?> instructores</span> <span class="pill"><?= count($contenidosCurso) ?> contenidos</span></div><?php else: ?><div class="empty">No tenes cursos asignados como instructor para este a&ntilde;o.</div><?php endif; ?></div><div class="box"><h2>Contenidos del curso</h2><?php if(!$cursoActual): ?><div class="muted">Selecciona un curso asignado.</div><?php elseif(!$contenidosCurso): ?><div class="muted">Sin contenidos cargados.</div><?php else: foreach(array_slice($contenidosCurso,0,6) as $cl): ?><div class="item"><div><strong><?= de_e($cl['titulo']) ?></strong><div class="muted"><?= de_e($cl['descripcion'] ?? '') ?></div></div><span class="pill"><?= de_e($cl['tipo']) ?></span></div><?php endforeach; endif; ?></div></div>
  <?php elseif ($tab === 'cursos'): ?>
    <div class="grid2"><div class="box"><h2>Agregar nuevo curso</h2><form method="post"><input type="hidden" name="_csrf" value="<?= de_e($csrf) ?>"><input type="hidden" name="action" value="curso_add"><input type="hidden" name="anio" value="<?= (int)$anio ?>"><label class="form-label">Nombre del curso</label><input class="form-control mb-2" name="nombre" required><label class="form-label">Descripci&oacute;n</label><textarea class="form-control mb-2" name="descripcion" rows="3"></textarea><div class="grid3"><div><label class="form-label">Inicio</label><input type="date" class="form-control" name="fecha_inicio"></div><div><label class="form-label">Fin</label><input type="date" class="form-control" name="fecha_fin"></div><div><label class="form-label">Estado</label><select class="form-select" name="estado"><option value="planificado">Planificado</option><option value="en_curso">En curso</option><option value="finalizado">Finalizado</option><option value="archivado">Archivado</option></select></div></div><button class="btn-admin mt-3">Guardar curso</button></form></div><div class="box"><h2>Listado de cursos</h2><div class="table-responsive"><table class="table"><thead><tr><th>Curso</th><th>Estado</th><th>Cursantes</th><th>Accion</th></tr></thead><tbody><?php foreach($cursos as $c): ?><tr><td><?= de_e($c['nombre']) ?></td><td><?= de_e($c['estado']) ?></td><td><?= (int)$c['cursantes_count'] ?></td><td><form method="post" onsubmit="return confirm('Archivar este curso? No se eliminaran sus listas asociadas.')"><input type="hidden" name="_csrf" value="<?= de_e($csrf) ?>"><input type="hidden" name="action" value="curso_delete"><input type="hidden" name="anio" value="<?= (int)$anio ?>"><input type="hidden" name="curso_id" value="<?= (int)$c['id'] ?>"><button class="btn btn-sm btn-outline-danger" type="submit">Archivar</button></form></td></tr><?php endforeach; ?></tbody></table></div></div></div>
  <?php elseif ($tab === 'instructores'): ?>
    <div class="grid2"><div class="box"><h2>Nuevo instructor</h2><form method="post"><input type="hidden" name="_csrf" value="<?= de_e($csrf) ?>"><input type="hidden" name="action" value="profesor_add"><input type="hidden" name="anio" value="<?= (int)$anio ?>"><div class="grid3"><input class="form-control" name="grado" placeholder="Grado"><input class="form-control" name="apellido_nombre" placeholder="Apellido y nombre" required><input class="form-control" name="dni" placeholder="DNI"></div><div class="grid3 mt-2"><input class="form-control" name="fuerza" placeholder="Fuerza"><input class="form-control" name="especialidad" placeholder="Especialidad"><input class="form-control" name="cargo" value="Instructor" placeholder="Cargo"></div><div class="grid3 mt-2"><input class="form-control" name="telefono" placeholder="Tel&eacute;fono"><input class="form-control" name="email" placeholder="Email"><input class="form-control" name="unidad_origen" placeholder="Unidad origen"></div><label class="form-label mt-2">Asignar a curso</label><select class="form-select" name="curso_id"><option value="0">Sin asignar</option><?php foreach($cursos as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$c['id']===$cursoId?'selected':'' ?>><?= de_e($c['nombre']) ?></option><?php endforeach; ?></select><button class="btn-admin mt-3">Guardar instructor</button></form><hr><h2>Asignar instructor existente</h2><form method="post" class="grid3"><input type="hidden" name="_csrf" value="<?= de_e($csrf) ?>"><input type="hidden" name="action" value="profesor_asignar"><input type="hidden" name="anio" value="<?= (int)$anio ?>"><select class="form-select" name="profesor_id" required><option value="">Instructor</option><?php foreach($profesores as $p): ?><option value="<?= (int)$p['id'] ?>"><?= de_e(($p['grado'] ? $p['grado'].' ' : '') . $p['apellido_nombre']) ?></option><?php endforeach; ?></select><select class="form-select" name="curso_id" required><?php foreach($cursos as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$c['id']===$cursoId?'selected':'' ?>><?= de_e($c['nombre']) ?></option><?php endforeach; ?></select><input class="form-control" name="rol" value="Instructor"><button class="btn-admin">Asignar</button></form></div><div class="box"><h2>Listado de instructores</h2><div class="table-responsive"><table class="table"><thead><tr><th>Instructor</th><th>Especialidad</th><th>Contacto</th><th>Accion</th></tr></thead><tbody><?php foreach($profesores as $p): ?><tr><td><?= de_e(($p['grado'] ? $p['grado'].' ' : '') . $p['apellido_nombre']) ?></td><td><?= de_e($p['especialidad'] ?? '') ?></td><td><?= de_e($p['email'] ?? $p['telefono'] ?? '') ?></td><td><form method="post" onsubmit="return confirm('Eliminar este instructor de la lista disponible?')"><input type="hidden" name="_csrf" value="<?= de_e($csrf) ?>"><input type="hidden" name="action" value="profesor_delete"><input type="hidden" name="anio" value="<?= (int)$anio ?>"><input type="hidden" name="profesor_id" value="<?= (int)$p['id'] ?>"><button class="btn btn-sm btn-outline-danger" type="submit">Eliminar</button></form></td></tr><?php endforeach; ?></tbody></table></div><h2 class="mt-3">Instructores del curso</h2><?php if(!$profCurso): ?><div class="muted">Sin instructores asignados.</div><?php else: foreach($profCurso as $p): ?><div class="item"><strong><?= de_e(($p['grado'] ? $p['grado'].' ' : '') . $p['apellido_nombre']) ?></strong><span class="pill"><?= de_e($p['rol']) ?></span></div><?php endforeach; endif; ?></div></div>
  <?php elseif ($tab === 'cursantes'): ?>
    <div class="grid2"><div class="box"><h2>Agregar cursante</h2><form method="post"><input type="hidden" name="_csrf" value="<?= de_e($csrf) ?>"><input type="hidden" name="action" value="cursante_add"><input type="hidden" name="anio" value="<?= (int)$anio ?>"><label class="form-label">Curso</label><select class="form-select mb-2" name="curso_id" required><?php foreach($cursos as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$c['id']===$cursoId?'selected':'' ?>><?= de_e($c['nombre']) ?></option><?php endforeach; ?></select><?php if($personal): ?><label class="form-label">Buscar desde personal_unidad opcional</label><select class="form-select mb-2" name="personal_id"><option value="0">Carga manual</option><?php foreach($personal as $p): ?><option value="<?= (int)$p['id'] ?>"><?= de_e(($p['grado'] ? $p['grado'].' ' : '') . $p['apellido_nombre'] . ' - ' . ($p['dni'] ?? '')) ?></option><?php endforeach; ?></select><?php endif; ?><div class="grid3"><input class="form-control" name="grado" placeholder="Grado"><input class="form-control" name="apellido_nombre" placeholder="Apellido y nombre"><input class="form-control" name="dni" placeholder="DNI"></div><div class="grid3 mt-2"><input class="form-control" name="fuerza" placeholder="Fuerza"><input class="form-control" name="unidad_origen" placeholder="Unidad origen"><select class="form-select" name="estado"><option value="inscripto">Inscripto</option><option value="regular">Regular</option><option value="aprobado">Aprobado</option><option value="desaprobado">Desaprobado</option><option value="baja">Baja</option></select></div><button class="btn-admin mt-3">Agregar cursante</button></form></div><div class="box"><h2>Listado de cursantes del curso</h2><?php if(!$cursoActual): ?><div class="empty">Seleccion&aacute; un curso.</div><?php elseif(!$cursantes): ?><div class="empty">Este curso no tiene cursantes.</div><?php else: ?><div class="table-responsive"><table class="table"><thead><tr><th>Grado</th><th>Apellido y nombre</th><th>DNI</th><th>Estado</th></tr></thead><tbody><?php foreach($cursantes as $cu): ?><tr><td><?= de_e($cu['grado']) ?></td><td><?= de_e($cu['apellido_nombre']) ?></td><td><?= de_e($cu['dni']) ?></td><td><span class="pill"><?= de_e($cu['estado']) ?></span></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div></div>
  <?php elseif ($tab === 'contenidos'): ?>
    <div class="grid2"><div class="box"><h2>Cargar contenido / material</h2><form method="post" enctype="multipart/form-data"><input type="hidden" name="_csrf" value="<?= de_e($csrf) ?>"><input type="hidden" name="action" value="clase_add"><input type="hidden" name="anio" value="<?= (int)$anio ?>"><label class="form-label">Curso</label><select class="form-select mb-2" name="curso_id"><?php foreach($cursos as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$c['id']===$cursoId?'selected':'' ?>><?= de_e($c['nombre']) ?></option><?php endforeach; ?></select><input class="form-control mb-2" name="titulo" placeholder="T&iacute;tulo del contenido" required><div class="grid3"><input class="form-control" name="tipo" value="PowerPoint"><input type="date" class="form-control" name="fecha"><input type="file" class="form-control" name="archivo"></div><textarea class="form-control mt-2" name="descripcion" rows="3" placeholder="Descripci&oacute;n"></textarea><label class="mt-2"><input type="checkbox" name="visible_cursante" checked> Visible para cursantes</label><br><button class="btn-admin mt-3">Subir contenido</button></form></div><div class="box"><h2>Contenidos del curso</h2><?php if(!$contenidosCurso): ?><div class="empty">Sin contenidos cargados.</div><?php else: foreach($contenidosCurso as $cl): ?><div class="item"><div><strong><?= de_e($cl['titulo']) ?></strong><div class="muted"><?= de_e($cl['descripcion'] ?? '') ?></div></div><div class="text-end"><span class="pill"><?= de_e($cl['tipo']) ?></span><br><span class="muted"><?= $cl['visible_cursante'] ? 'Visible' : 'Oculto' ?></span></div></div><?php endforeach; endif; ?></div></div>
  <?php elseif ($tab === 'calificaciones'): ?>
    <div class="grid2"><div class="box"><h2>Cargar calificaci&oacute;n</h2><form method="post"><input type="hidden" name="_csrf" value="<?= de_e($csrf) ?>"><input type="hidden" name="action" value="calificacion_add"><input type="hidden" name="anio" value="<?= (int)$anio ?>"><label class="form-label">Curso</label><select class="form-select mb-2" name="curso_id"><?php foreach($cursos as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$c['id']===$cursoId?'selected':'' ?>><?= de_e($c['nombre']) ?></option><?php endforeach; ?></select><label class="form-label">Cursante</label><select class="form-select mb-2" name="cursante_id" required><option value="">Seleccionar</option><?php foreach($cursantes as $cu): ?><option value="<?= (int)$cu['id'] ?>"><?= de_e(($cu['grado'] ? $cu['grado'].' ' : '') . $cu['apellido_nombre']) ?></option><?php endforeach; ?></select><div class="grid3"><input class="form-control" name="actividad" placeholder="Actividad / Evaluaci&oacute;n" required><input class="form-control" name="nota" placeholder="Nota 0 a 10" required><input type="date" class="form-control" name="fecha"></div><textarea class="form-control mt-2" name="observaciones" rows="3" placeholder="Observaciones"></textarea><button class="btn-admin mt-3">Guardar calificaci&oacute;n</button></form></div><div class="box"><h2>Calificaciones del curso</h2><?php if(!$calificaciones): ?><div class="empty">Sin calificaciones cargadas.</div><?php else: ?><div class="table-responsive"><table class="table"><thead><tr><th>Cursante</th><th>Actividad</th><th>Nota</th><th>Fecha</th></tr></thead><tbody><?php foreach($calificaciones as $ca): ?><tr><td><?= de_e(($ca['grado'] ? $ca['grado'].' ' : '') . $ca['apellido_nombre']) ?></td><td><?= de_e($ca['actividad']) ?></td><td><strong><?= de_e(number_format((float)$ca['nota'],2,',','.')) ?></strong></td><td><?= de_e($ca['fecha']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div></div>
  <?php endif; ?>
</section>
</main>
</body>
</html>

