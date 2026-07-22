<?php
// public/division_ensenanza/division_ensenanza_instructor.php
declare(strict_types=1);

require_once __DIR__ . '/../../auth/bootstrap.php';
require_login();
require_once __DIR__ . '/../../config/db.php';

function ins_e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function ins_clean(string $v): string {
    $v = trim($v);
    $v = preg_replace('/[^\pL\pN\s._@,+()-]+/u', '', $v) ?? '';
    return trim(preg_replace('/\s+/', ' ', $v) ?? '');
}
function ins_norm_dni(string $dni): string { return preg_replace('/\D+/', '', $dni) ?? ''; }
function ins_col(PDO $pdo, string $table, string $col): bool {
    $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c");
    $st->execute([':t' => $table, ':c' => $col]);
    return (int)$st->fetchColumn() > 0;
}
function ins_mkdir(string $dir): void {
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('No se pudo crear la carpeta.');
    }
}
function ins_safe_file(string $name): string {
    $base = pathinfo($name, PATHINFO_FILENAME);
    $ext = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
    $base = ins_clean($base);
    $base = preg_replace('/\s+/', '_', $base) ?? 'contenido';
    $base = trim($base, '._-') ?: 'contenido';
    return $base . '_' . date('Ymd_His') . ($ext !== '' ? '.' . $ext : '');
}
function ins_schema(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ens_cursos (
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
          PRIMARY KEY (id),
          KEY idx_ens_cursos_anio (unidad_id, anio, estado)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ens_profesores (
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
          PRIMARY KEY (id),
          KEY idx_ens_prof_unidad (unidad_id, activo),
          KEY idx_ens_prof_anio (anio)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ens_profesor_cursos (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          unidad_id INT NOT NULL DEFAULT 1,
          profesor_id INT UNSIGNED NOT NULL,
          curso_id INT UNSIGNED NOT NULL,
          rol VARCHAR(120) NULL,
          activo TINYINT(1) NOT NULL DEFAULT 1,
          created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          UNIQUE KEY uq_ens_prof_curso (unidad_id, profesor_id, curso_id),
          KEY idx_ens_prof_cursos_prof (profesor_id),
          KEY idx_ens_prof_cursos_curso (curso_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ens_cursantes (
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
          PRIMARY KEY (id),
          KEY idx_ens_cursantes_curso (curso_id),
          KEY idx_ens_cursantes_personal (personal_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ens_clases (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          unidad_id INT NOT NULL DEFAULT 1,
          curso_id INT UNSIGNED NULL,
          anio SMALLINT NOT NULL,
          titulo VARCHAR(190) NOT NULL,
          tipo VARCHAR(40) NOT NULL DEFAULT 'PowerPoint',
          fecha DATE NULL,
          descripcion TEXT NULL,
          archivo_rel VARCHAR(500) NULL,
          archivo_nombre VARCHAR(255) NULL,
          uploaded_by VARCHAR(190) NULL,
          visible_cursante TINYINT(1) NOT NULL DEFAULT 1,
          created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY idx_ens_clases_curso (curso_id),
          KEY idx_ens_clases_anio (unidad_id, anio, fecha)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    if (!ins_col($pdo, 'ens_clases', 'visible_cursante')) {
        $pdo->exec("ALTER TABLE ens_clases ADD COLUMN visible_cursante TINYINT(1) NOT NULL DEFAULT 1 AFTER uploaded_by");
    }
    foreach ([
        'fuerza' => "ALTER TABLE ens_cursantes ADD COLUMN fuerza VARCHAR(80) NULL AFTER dni",
        'unidad_origen' => "ALTER TABLE ens_cursantes ADD COLUMN unidad_origen VARCHAR(160) NULL AFTER fuerza",
        'destino' => "ALTER TABLE ens_cursantes ADD COLUMN destino VARCHAR(160) NULL AFTER unidad_origen",
        'rol_curso' => "ALTER TABLE ens_cursantes ADD COLUMN rol_curso VARCHAR(120) NULL AFTER destino",
        'observaciones' => "ALTER TABLE ens_cursantes ADD COLUMN observaciones TEXT NULL AFTER estado",
    ] as $col => $sql) {
        if (!ins_col($pdo, 'ens_cursantes', $col)) $pdo->exec($sql);
    }
}

$SELF_WEB = (string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
$BASE_DIR_WEB = rtrim(str_replace('\\', '/', dirname($SELF_WEB)), '/');
$BASE_PUBLIC_WEB = rtrim(str_replace('\\', '/', dirname($BASE_DIR_WEB)), '/');
$BASE_APP_WEB = rtrim(str_replace('\\', '/', dirname($BASE_PUBLIC_WEB)), '/');
$ASSET_WEB = $BASE_APP_WEB . '/assets';
$IMG_BG = $ASSET_WEB . '/img/fondo.png';
$ESCUDO = $ASSET_WEB . '/img/ecmilm.png';
$unidadId = (int)($_SESSION['unidad_id'] ?? 1);
$anio = (int)($_GET['anio'] ?? $_POST['anio'] ?? date('Y'));
if ($anio < 2000 || $anio > 2100) $anio = (int)date('Y');

$root = realpath(__DIR__ . '/../..');
if ($root === false) $root = dirname(__DIR__, 2);
$areaFolder = 'DIVISION-ENSE' . "\xC3\x91" . 'ANZA';
$sharedRoot = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'unidades' . DIRECTORY_SEPARATOR . 'ecmilm' . DIRECTORY_SEPARATOR . $areaFolder;
ins_mkdir($sharedRoot . DIRECTORY_SEPARATOR . 'Clases PowerPoint');

ins_schema($pdo);

$user = function_exists('current_user') ? current_user() : ($_SESSION['user'] ?? []);
$user = is_array($user) ? $user : [];
$dni = ins_norm_dni((string)($user['dni'] ?? $user['username'] ?? ''));
$display = trim((string)($user['apellido_nombre'] ?? $user['nombre_completo'] ?? $user['nombre'] ?? $user['username'] ?? 'Instructor'));
$initials = '';
foreach (preg_split('/\s+/', $display) ?: [] as $part) {
    if ($part !== '') $initials .= mb_strtoupper(mb_substr($part, 0, 1, 'UTF-8'), 'UTF-8');
    if (mb_strlen($initials, 'UTF-8') >= 2) break;
}
if ($initials === '') $initials = 'IN';
$personalId = 0;
if ($dni !== '') {
    try {
        $st = $pdo->prepare("SELECT id FROM personal_unidad WHERE REPLACE(REPLACE(REPLACE(dni,'.',''),'-',''),' ','') = :dni LIMIT 1");
        $st->execute([':dni' => $dni]);
        $personalId = (int)($st->fetchColumn() ?: 0);
    } catch (Throwable $ex) {}
}

$profesor = null;
$st = $pdo->prepare("
    SELECT *
    FROM ens_profesores
    WHERE unidad_id = :uid AND activo = 1
      AND (
        (:dni_has <> '' AND REPLACE(REPLACE(REPLACE(COALESCE(dni,''),'.',''),'-',''),' ','') = :dni_match)
        OR (:pid_has > 0 AND personal_id = :pid_match)
      )
    ORDER BY id DESC
    LIMIT 1
");
$st->execute([':uid' => $unidadId, ':dni_has' => $dni, ':dni_match' => $dni, ':pid_has' => $personalId, ':pid_match' => $personalId]);
$profesor = $st->fetch(PDO::FETCH_ASSOC) ?: null;

$ok = '';
$err = '';
$cursoId = (int)($_GET['curso_id'] ?? $_POST['curso_id'] ?? 0);
$q = trim((string)($_GET['q'] ?? ''));
$sort = (string)($_GET['sort'] ?? 'nombre');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (function_exists('csrf_verify')) csrf_verify();
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'upload') {
            $cursoPost = (int)($_POST['curso_id'] ?? 0);
            if ($cursoPost <= 0) throw new RuntimeException('Seleccione un curso.');
            $titulo = ins_clean((string)($_POST['titulo'] ?? ''));
            if ($titulo === '') throw new RuntimeException('El contenido necesita un titulo.');
            $tipo = ins_clean((string)($_POST['tipo'] ?? 'Clase'));
            $relPath = null;
            $fileName = null;
            if (!empty($_FILES['archivo']['name']) && is_uploaded_file((string)$_FILES['archivo']['tmp_name'])) {
                $allowed = ['ppt','pptx','pdf','doc','docx','xls','xlsx','jpg','jpeg','png','zip','mp4'];
                $ext = strtolower((string)pathinfo((string)$_FILES['archivo']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed, true)) throw new RuntimeException('Formato no permitido.');
                $folderRel = 'Clases PowerPoint/' . $anio . '/Curso_' . $cursoPost;
                $targetDir = $sharedRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $folderRel);
                ins_mkdir($targetDir);
                $fileName = ins_safe_file((string)$_FILES['archivo']['name']);
                if (!move_uploaded_file((string)$_FILES['archivo']['tmp_name'], $targetDir . DIRECTORY_SEPARATOR . $fileName)) {
                    throw new RuntimeException('No se pudo guardar el archivo.');
                }
                $relPath = $folderRel . '/' . $fileName;
            }
            $st = $pdo->prepare("
                INSERT INTO ens_clases
                  (unidad_id, curso_id, anio, titulo, tipo, fecha, descripcion, archivo_rel, archivo_nombre, uploaded_by, visible_cursante)
                VALUES
                  (:uid,:curso,:anio,:titulo,:tipo,:fecha,:descripcion,:rel,:nombre,:usuario,:visible)
            ");
            $st->execute([
                ':uid' => $unidadId,
                ':curso' => $cursoPost,
                ':anio' => $anio,
                ':titulo' => $titulo,
                ':tipo' => $tipo ?: 'Clase',
                ':fecha' => trim((string)($_POST['fecha'] ?? '')) ?: null,
                ':descripcion' => trim((string)($_POST['descripcion'] ?? '')) ?: null,
                ':rel' => $relPath,
                ':nombre' => $fileName,
                ':usuario' => $display,
                ':visible' => !empty($_POST['visible_cursante']) ? 1 : 0,
            ]);
            $cursoId = $cursoPost;
            $ok = 'Contenido publicado. Los cursantes lo veran en Mis cursos.';
        } elseif ($action === 'toggle_visible') {
            $id = (int)($_POST['id'] ?? 0);
            $pdo->prepare("UPDATE ens_clases SET visible_cursante = IF(visible_cursante=1,0,1) WHERE id = :id AND unidad_id = :uid")->execute([':id' => $id, ':uid' => $unidadId]);
            $ok = 'Visibilidad actualizada.';
        }
    } catch (Throwable $ex) {
        $err = $ex->getMessage();
    }
}

$courses = [];
$courseWhere = "";
$courseParams = [':uid' => $unidadId, ':anio' => $anio];
if ($q !== '') {
    $courseWhere = " AND (c.nombre LIKE :q OR c.descripcion LIKE :q)";
    $courseParams[':q'] = '%' . $q . '%';
}
$order = $sort === 'fecha' ? "COALESCE(c.fecha_inicio, c.created_at) DESC, c.nombre ASC" : "c.nombre ASC";
if ($profesor) {
    $st = $pdo->prepare("
        SELECT c.*, pc.rol,
               (SELECT COUNT(*) FROM ens_clases cl WHERE cl.unidad_id = c.unidad_id AND cl.curso_id = c.id) AS contenidos_count,
               (SELECT COUNT(*) FROM ens_cursantes cur WHERE cur.unidad_id = c.unidad_id AND cur.curso_id = c.id) AS cursantes_count
        FROM ens_profesor_cursos pc
        INNER JOIN ens_cursos c ON c.id = pc.curso_id
        WHERE pc.unidad_id = :uid AND pc.profesor_id = :prof AND pc.activo = 1 AND c.anio = :anio{$courseWhere}
        ORDER BY {$order}
    ");
    $params = $courseParams;
    $params[':prof'] = (int)$profesor['id'];
    $st->execute($params);
    $courses = $st->fetchAll(PDO::FETCH_ASSOC);
}
if (!$profesor || empty($courses)) {
    $st = $pdo->prepare("
        SELECT c.*, 'Instructor' AS rol,
               (SELECT COUNT(*) FROM ens_clases cl WHERE cl.unidad_id = c.unidad_id AND cl.curso_id = c.id) AS contenidos_count,
               (SELECT COUNT(*) FROM ens_cursantes cur WHERE cur.unidad_id = c.unidad_id AND cur.curso_id = c.id) AS cursantes_count
        FROM ens_cursos c
        WHERE c.unidad_id = :uid AND c.anio = :anio{$courseWhere}
        ORDER BY {$order}
    ");
    $st->execute($courseParams);
    $courses = $st->fetchAll(PDO::FETCH_ASSOC);
}
if ($cursoId <= 0 && !empty($courses)) $cursoId = (int)$courses[0]['id'];
$activeCourse = null;
foreach ($courses as $course) {
    if ((int)$course['id'] === $cursoId) { $activeCourse = $course; break; }
}

$contenidos = [];
$cursantes = [];
if ($cursoId > 0) {
    $st = $pdo->prepare("SELECT * FROM ens_clases WHERE unidad_id = :uid AND curso_id = :curso ORDER BY COALESCE(fecha, DATE(created_at)) DESC, id DESC");
    $st->execute([':uid' => $unidadId, ':curso' => $cursoId]);
    $contenidos = $st->fetchAll(PDO::FETCH_ASSOC);
    $st = $pdo->prepare("SELECT * FROM ens_cursantes WHERE unidad_id = :uid AND curso_id = :curso ORDER BY apellido_nombre ASC");
    $st->execute([':uid' => $unidadId, ':curso' => $cursoId]);
    $cursantes = $st->fetchAll(PDO::FETCH_ASSOC);
}
$years = range((int)date('Y') + 1, (int)date('Y') - 5);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Modo instructor</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
  :root{--nav:#344248;--gold:#e5aa13;--ink:#394650;--muted:#87919a;}
  body{min-height:100vh;margin:0;background:#eef1f2;color:var(--ink);font-family:Arial,Helvetica,sans-serif;}
  body::before{content:"";position:fixed;inset:0;z-index:-1;opacity:.42;background-image:radial-gradient(circle at 8px 8px, rgba(52,66,72,.12) 0 1.2px, transparent 1.4px);background-size:22px 22px;}
  .topline{height:5px;background:var(--gold);}
  .mast{height:135px;background:#fff;display:flex;align-items:center;justify-content:space-between;padding:0 5.6%;box-shadow:0 1px 0 rgba(0,0,0,.04);}
  .brand{display:flex;align-items:center;gap:16px}.brand img{width:68px;height:68px;object-fit:contain}.brand-title{font-size:2.2rem;font-weight:400;color:#62676c;line-height:1}.brand-sub{font-size:1.25rem;color:#6b7177;line-height:1.05;text-transform:uppercase;max-width:230px}
  .userbar{display:flex;align-items:center;gap:14px;font-weight:700;color:#344248}.round{width:38px;height:38px;border-radius:50%;background:#5b6770;color:#fff;display:grid;place-items:center;position:relative}.badge-dot{position:absolute;right:-2px;top:-5px;background:#d94355;color:#fff;border-radius:2px;font-size:.68rem;padding:0 3px}.avatar{width:76px;height:76px;border-radius:50%;background:#f0f0f0;color:#8a8f94;display:grid;place-items:center;font-size:1.8rem;font-weight:700}
  .nav{height:56px;background:var(--nav);display:flex;align-items:stretch;padding:0 5.1%;gap:0}.nav a{display:flex;align-items:center;padding:0 12px;color:#fff;text-decoration:none;font-size:.94rem}.nav a.active{border-bottom:3px solid var(--gold);background:rgba(0,0,0,.06)}
  .page{max-width:1218px;margin:34px auto 60px;padding:0 16px}.page h1{font-size:2rem;margin:0 0 36px;color:#3e4a52;font-weight:700}
  .panel{background:#fff;border:1px solid #e2e5e8;box-shadow:0 1px 2px rgba(0,0,0,.03);padding:30px 30px 36px;margin-bottom:22px}.panel-title{font-size:1.15rem;font-weight:700;margin:34px 0 22px}.panel-title:first-child{margin-top:0}.panel-title::after{content:"";display:block;width:74px;height:2px;background:var(--gold);margin-top:10px}
  .controls{display:grid;grid-template-columns:110px minmax(220px,360px) 1fr 285px 120px;gap:18px;align-items:center;margin-bottom:30px}.btn-darkish,.selectish{height:40px;background:#5a646b;color:#fff;border:0;border-radius:0;box-shadow:0 2px 5px rgba(0,0,0,.22);padding:0 18px;text-decoration:none;display:inline-flex;align-items:center;justify-content:center}.search{height:42px;border:0;border-bottom:1px solid #cbd1d5;background:transparent;padding:0 10px;color:#4b5560}.search-x{width:34px;height:34px;border-radius:50%;background:#f1f2f3;color:#8b9298;display:grid;place-items:center;margin-left:-64px;z-index:2;text-decoration:none}
  .cards{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px}.card-course{background:#fff;border:1px solid #e1e4e7;border-radius:2px;box-shadow:0 3px 10px rgba(0,0,0,.12);overflow:hidden;color:#344248;text-decoration:none;min-height:278px;display:flex;flex-direction:column}.card-course.active{outline:3px solid var(--gold);outline-offset:0}.course-art{height:176px;background:linear-gradient(150deg,#ffb000,#ffd34c 54%,#ff8c22);position:relative;overflow:hidden}.course-art::before{content:"";position:absolute;inset:0;background:repeating-linear-gradient(0deg,rgba(255,255,255,.16) 0 2px,transparent 2px 5px),radial-gradient(circle at 20% 34%,rgba(238,91,20,.75) 0 18px,transparent 19px),radial-gradient(circle at 76% 22%,rgba(255,255,255,.9) 0 2px,transparent 3px);opacity:.45}.course-art strong{position:absolute;left:26px;top:54px;color:#d86720;font-size:1.68rem;line-height:1.05;text-transform:uppercase;max-width:210px}.wifi{position:absolute;right:14px;bottom:24px;color:#fff;font-size:2.4rem}
  .card-body{padding:16px 14px 12px;position:relative;flex:1}.card-title{font-size:1rem;font-weight:700;text-align:center;line-height:1.45;min-height:46px}.card-sub{font-size:.9rem;color:#9199a0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.kebab{position:absolute;right:10px;bottom:10px;width:36px;height:36px;border-radius:50%;background:#e4e9ed;color:#6d747b;display:grid;place-items:center}
  .detail{margin-top:22px;display:grid;grid-template-columns:1fr 380px;gap:18px}.box{background:#fff;border:1px solid #e2e5e8;padding:18px;margin-bottom:18px}.box h2{font-size:1.15rem;font-weight:700;margin:0 0 14px}.item{display:flex;justify-content:space-between;gap:12px;border-bottom:1px solid #edf0f2;padding:12px 0}.item:last-child{border-bottom:0}.muted{color:#7b858d}.pill{display:inline-flex;align-items:center;padding:3px 8px;border-radius:999px;background:#eef1f2;color:#59646c;font-size:.75rem;font-weight:700}.empty{padding:26px;text-align:center;color:#6b747c;border:1px dashed #c8d0d5;background:#fafafa}
  .form-label{font-size:.82rem;font-weight:700;color:#3e4a52}.form-control,.form-select{border-radius:2px;border:1px solid #cbd1d5;color:#394650}.btn-action{height:38px;border:0;background:#5a646b;color:#fff;padding:0 16px;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:7px}.btn-action:hover{color:#fff;background:#47525a}.table-wrap{overflow:auto;border:1px solid #e2e5e8}.tableish{width:100%;min-width:880px;border-collapse:collapse}.tableish th,.tableish td{padding:.72rem .8rem;border-bottom:1px solid #edf0f2;vertical-align:middle}.tableish th{background:#f5f7f8;color:#59646c;text-transform:uppercase;letter-spacing:.06em;font-size:.72rem}.tableish tr:last-child td{border-bottom:0}
  @media (max-width:1100px){.cards{grid-template-columns:repeat(2,minmax(0,1fr))}.controls{grid-template-columns:1fr 1fr}.detail{grid-template-columns:1fr}.mast{height:auto;padding:20px 18px}.brand-title{font-size:1.8rem}.nav{overflow:auto;padding:0 12px}}
  @media (max-width:650px){.cards{grid-template-columns:1fr}.controls{grid-template-columns:1fr}.panel{padding:18px}.userbar .name{display:none}.avatar{width:54px;height:54px}.brand-sub{display:none}}
</style>
</head>
<body>
<div class="topline"></div>
<header class="mast">
  <div class="brand">
    <img src="<?= ins_e($ESCUDO) ?>" alt="ECMILM">
    <div class="brand-title">ECMILM</div>
    <div class="brand-sub">Escuela Militar de Monta&ntilde;a</div>
  </div>
  <div class="userbar">
    <div class="round"><i class="bi bi-bell"></i></div>
    <div class="round"><i class="bi bi-chat-dots"></i><span class="badge-dot">0</span></div>
    <div class="name"><?= ins_e(mb_strtoupper($display, 'UTF-8')) ?></div>
    <div class="avatar"><?= ins_e($initials) ?></div>
  </div>
</header>
<nav class="nav">
  <a href="../inicio.php">P&aacute;gina Principal</a>
  <a class="active" href="division_ensenanza_instructor.php?anio=<?= (int)$anio ?>">Mis cursos</a>
  <a href="../calendario.php?area=ENS">Calendario</a>
  <a href="division_ensenanza.php?anio=<?= (int)$anio ?>" class="ms-auto">Divisi&oacute;n Ense&ntilde;anza</a>
  <a href="#"><i class="bi bi-search"></i></a>
</nav>
<main class="page">
  <h1>Mis cursos</h1>
  <?php if ($ok !== ''): ?><div class="alert alert-success"><?= ins_e($ok) ?></div><?php endif; ?>
  <?php if ($err !== ''): ?><div class="alert alert-danger"><?= ins_e($err) ?></div><?php endif; ?>
  <section class="panel">
    <div class="panel-title">Vista general de curso</div>
    <form class="controls" method="get">
      <select class="btn-darkish" name="anio" onchange="this.form.submit()">
        <?php foreach ($years as $y): ?><option value="<?= (int)$y ?>" <?= $y === $anio ? 'selected' : '' ?>><?= (int)$y ?></option><?php endforeach; ?>
      </select>
      <input class="search" name="q" value="<?= ins_e($q) ?>" placeholder="Buscar">
      <a class="search-x" href="?anio=<?= (int)$anio ?>"><i class="bi bi-x-lg"></i></a>
      <select class="selectish" name="sort" onchange="this.form.submit()">
        <option value="nombre" <?= $sort === 'nombre' ? 'selected' : '' ?>>Ordenar por nombre del curso</option>
        <option value="fecha" <?= $sort === 'fecha' ? 'selected' : '' ?>>Ordenar por fecha</option>
      </select>
      <button class="selectish" type="submit">Tarjeta</button>
    </form>

    <?php if (empty($courses)): ?>
      <div class="empty">No ten&eacute;s materias asignadas para este a&ntilde;o.</div>
    <?php else: ?>
      <div class="cards">
        <?php foreach ($courses as $course): ?>
          <a class="card-course <?= (int)$course['id'] === $cursoId ? 'active' : '' ?>" href="?anio=<?= (int)$anio ?>&curso_id=<?= (int)$course['id'] ?>&q=<?= ins_e(rawurlencode($q)) ?>&sort=<?= ins_e($sort) ?>">
            <div class="course-art">
              <strong><?= ins_e($anio >= 2026 ? 'Primer cuatrimestre' : 'Ciclo lectivo') ?></strong>
              <i class="bi bi-wifi wifi"></i>
            </div>
            <div class="card-body">
              <div class="card-title"><?= ins_e($course['nombre']) ?></div>
              <div class="card-sub">ECMILM - <?= ins_e($course['rol'] ?: 'Instructor') ?></div>
              <div class="mt-2"><span class="pill"><?= (int)$course['contenidos_count'] ?> contenidos</span> <span class="pill"><?= (int)$course['cursantes_count'] ?> cursantes</span></div>
              <div class="kebab"><i class="bi bi-three-dots-vertical"></i></div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <?php if ($activeCourse): ?>
    <section class="detail">
      <div>
      <div class="box">
        <h2><?= ins_e($activeCourse['nombre']) ?></h2>
        <div class="panel-title">Subir contenido</div>
          <form class="row g-2" method="post" enctype="multipart/form-data">
            <?php if (function_exists('csrf_input')) echo csrf_input(); ?>
            <input type="hidden" name="action" value="upload">
            <input type="hidden" name="anio" value="<?= (int)$anio ?>">
            <input type="hidden" name="curso_id" value="<?= (int)$cursoId ?>">
            <div class="col-md-5"><label class="form-label">Titulo</label><input class="form-control" name="titulo" required placeholder="Clase 1 - Introduccion"></div>
            <div class="col-md-3"><label class="form-label">Tipo</label><select class="form-select" name="tipo"><option>Clase</option><option>PowerPoint</option><option>PDF</option><option>Trabajo practico</option><option>Video</option><option>Otro</option></select></div>
            <div class="col-md-2"><label class="form-label">Fecha</label><input class="form-control" type="date" name="fecha" value="<?= ins_e(date('Y-m-d')) ?>"></div>
            <div class="col-md-2 d-flex align-items-end"><label class="d-flex gap-2 align-items-center fw-bold"><input type="checkbox" name="visible_cursante" value="1" checked> Visible</label></div>
            <div class="col-md-8"><label class="form-label">Archivo</label><input class="form-control" type="file" name="archivo" accept=".ppt,.pptx,.pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.zip,.mp4"></div>
            <div class="col-md-4 d-flex align-items-end"><button class="btn-action w-100" type="submit"><i class="bi bi-cloud-arrow-up"></i> Publicar</button></div>
            <div class="col-12"><label class="form-label">Descripcion / consigna</label><textarea class="form-control" rows="3" name="descripcion"></textarea></div>
          </form>
      </div>
      <div class="box">
        <h2>Contenidos publicados</h2>
        <div class="table-wrap"><table class="tableish"><thead><tr><th>Contenido</th><th>Tipo</th><th>Fecha</th><th>Visible</th><th>Archivo</th><th>Accion</th></tr></thead><tbody>
          <?php foreach ($contenidos as $ct): ?>
            <tr>
              <td><strong><?= ins_e($ct['titulo']) ?></strong><div class="muted small"><?= ins_e($ct['descripcion'] ?? '') ?></div></td>
              <td><span class="pill"><?= ins_e($ct['tipo']) ?></span></td>
              <td><?= ins_e($ct['fecha'] ?: '-') ?></td>
              <td><span class="pill"><?= (int)($ct['visible_cursante'] ?? 1) === 1 ? 'Si' : 'No' ?></span></td>
              <td><?php if (!empty($ct['archivo_rel'])): ?><a class="btn btn-outline-secondary btn-sm" target="_blank" href="division_ensenanza_carpeta.php?download=1&path=<?= ins_e(rawurlencode((string)$ct['archivo_rel'])) ?>">Abrir</a><?php else: ?><span class="muted">Sin archivo</span><?php endif; ?></td>
              <td><form method="post"><?php if (function_exists('csrf_input')) echo csrf_input(); ?><input type="hidden" name="action" value="toggle_visible"><input type="hidden" name="id" value="<?= (int)$ct['id'] ?>"><input type="hidden" name="curso_id" value="<?= (int)$cursoId ?>"><input type="hidden" name="anio" value="<?= (int)$anio ?>"><button class="btn btn-outline-secondary btn-sm" type="submit">Cambiar</button></form></td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($contenidos)): ?><tr><td colspan="6" class="muted">No hay contenidos publicados en este curso.</td></tr><?php endif; ?>
        </tbody></table></div>
      </div>
      </div>
      <aside class="box">
        <h2>Cursantes</h2>
        <?php if (empty($cursantes)): ?>
          <div class="empty">No hay cursantes cargados para esta materia.</div>
        <?php else: ?>
          <?php foreach ($cursantes as $c): ?>
            <div class="item">
              <div>
                <strong><?= ins_e(trim(($c['grado'] ?? '') . ' ' . $c['apellido_nombre'])) ?></strong>
                <div class="muted"><?= ins_e(trim(($c['fuerza'] ?? '-') . ' - ' . ($c['unidad_origen'] ?? '-') . ' - ' . ($c['destino'] ?? '-'))) ?></div>
              </div>
              <div class="text-end">
                <span class="pill"><?= ins_e($c['estado'] ?? 'inscripto') ?></span>
                <div class="muted small mt-1"><?= ins_e($c['rol_curso'] ?? 'Cursante') ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </aside>
    </section>
  <?php endif; ?>
</main>
</body>
</html>
