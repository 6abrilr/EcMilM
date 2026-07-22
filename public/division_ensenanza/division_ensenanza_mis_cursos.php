<?php
// public/division_ensenanza/division_ensenanza_mis_cursos.php
declare(strict_types=1);

require_once __DIR__ . '/../../auth/bootstrap.php';
require_login();
require_once __DIR__ . '/../../config/db.php';

function mc_e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function mc_norm_dni(string $dni): string { return preg_replace('/\D+/', '', $dni) ?? ''; }
function mc_col(PDO $pdo, string $table, string $col): bool {
    $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c");
    $st->execute([':t' => $table, ':c' => $col]);
    return (int)$st->fetchColumn() > 0;
}
function mc_schema(PDO $pdo): void {
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
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ens_calificaciones (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          unidad_id INT NOT NULL DEFAULT 1,
          curso_id INT UNSIGNED NOT NULL,
          cursante_id INT UNSIGNED NOT NULL,
          actividad VARCHAR(190) NOT NULL,
          nota DECIMAL(5,2) NOT NULL,
          fecha DATE NULL,
          observaciones TEXT NULL,
          created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY idx_ens_calif_curso (curso_id),
          KEY idx_ens_calif_cursante (cursante_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    foreach ([
        'fuerza' => "ALTER TABLE ens_cursantes ADD COLUMN fuerza VARCHAR(80) NULL AFTER dni",
        'unidad_origen' => "ALTER TABLE ens_cursantes ADD COLUMN unidad_origen VARCHAR(160) NULL AFTER fuerza",
        'destino' => "ALTER TABLE ens_cursantes ADD COLUMN destino VARCHAR(160) NULL AFTER unidad_origen",
        'rol_curso' => "ALTER TABLE ens_cursantes ADD COLUMN rol_curso VARCHAR(120) NULL AFTER destino",
        'observaciones' => "ALTER TABLE ens_cursantes ADD COLUMN observaciones TEXT NULL AFTER estado",
    ] as $col => $sql) {
        if (!mc_col($pdo, 'ens_cursantes', $col)) $pdo->exec($sql);
    }
    if (!mc_col($pdo, 'ens_clases', 'visible_cursante')) {
        $pdo->exec("ALTER TABLE ens_clases ADD COLUMN visible_cursante TINYINT(1) NOT NULL DEFAULT 1 AFTER uploaded_by");
    }
}

$SELF_WEB = (string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
$BASE_DIR_WEB = rtrim(str_replace('\\', '/', dirname($SELF_WEB)), '/');
$BASE_PUBLIC_WEB = rtrim(str_replace('\\', '/', dirname($BASE_DIR_WEB)), '/');
$BASE_APP_WEB = rtrim(str_replace('\\', '/', dirname($BASE_PUBLIC_WEB)), '/');
$ASSET_WEB = $BASE_APP_WEB . '/assets';
$ESCUDO = $ASSET_WEB . '/img/ecmilm.png';

$user = function_exists('current_user') ? current_user() : ($_SESSION['user'] ?? []);
$user = is_array($user) ? $user : [];
$dni = mc_norm_dni((string)($user['dni'] ?? $user['username'] ?? ''));
$displayName = trim((string)($user['apellido_nombre'] ?? $user['nombre_completo'] ?? $user['nombre'] ?? $user['username'] ?? 'Cursante'));
$initials = '';
foreach (preg_split('/\s+/', $displayName) ?: [] as $part) {
    if ($part !== '') $initials .= mb_strtoupper(mb_substr($part, 0, 1, 'UTF-8'), 'UTF-8');
    if (mb_strlen($initials, 'UTF-8') >= 2) break;
}
if ($initials === '') $initials = 'AL';

$unidadId = (int)($_SESSION['unidad_id'] ?? 1);
$anio = (int)($_GET['anio'] ?? date('Y'));
if ($anio < 2000 || $anio > 2100) $anio = (int)date('Y');
$q = trim((string)($_GET['q'] ?? ''));
$cursoId = (int)($_GET['curso_id'] ?? 0);
$sort = (string)($_GET['sort'] ?? 'nombre');

mc_schema($pdo);

$personalId = 0;
if ($dni !== '') {
    try {
        $st = $pdo->prepare("SELECT id FROM personal_unidad WHERE REPLACE(REPLACE(REPLACE(dni,'.',''),'-',''),' ','') = :dni LIMIT 1");
        $st->execute([':dni' => $dni]);
        $personalId = (int)($st->fetchColumn() ?: 0);
    } catch (Throwable $ex) {}
}

$where = "c.unidad_id = :uid AND c.anio = :anio AND c.estado <> 'archivado'";
$params = [':uid' => $unidadId, ':anio' => $anio];
$studentWhere = '';
if ($dni !== '' || $personalId > 0) {
    $studentWhere = " AND (cur.id IS NOT NULL)";
}
if ($q !== '') {
    $where .= " AND (c.nombre LIKE :q OR c.descripcion LIKE :q)";
    $params[':q'] = '%' . $q . '%';
}
$order = $sort === 'fecha' ? "COALESCE(c.fecha_inicio, c.created_at) DESC, c.nombre ASC" : "c.nombre ASC";

$sql = "
    SELECT c.*, cur.id AS cursante_id, cur.estado AS estado_cursante, cur.rol_curso,
           COUNT(DISTINCT cl.id) AS clases_count,
           COUNT(DISTINCT ca.id) AS notas_count,
           AVG(ca.nota) AS promedio
    FROM ens_cursos c
    LEFT JOIN ens_cursantes cur ON cur.curso_id = c.id AND cur.unidad_id = c.unidad_id
        AND (
          (:dni_has <> '' AND REPLACE(REPLACE(REPLACE(COALESCE(cur.dni,''),'.',''),'-',''),' ','') = :dni_match)
          OR (:pid_has > 0 AND cur.personal_id = :pid_match)
        )
    LEFT JOIN ens_clases cl ON cl.curso_id = c.id AND cl.unidad_id = c.unidad_id AND COALESCE(cl.visible_cursante,1) = 1
    LEFT JOIN ens_calificaciones ca ON ca.curso_id = c.id AND ca.cursante_id = cur.id AND ca.unidad_id = c.unidad_id
    WHERE {$where}{$studentWhere}
    GROUP BY c.id, cur.id
    ORDER BY {$order}
";
$params[':dni_has'] = $dni;
$params[':dni_match'] = $dni;
$params[':pid_has'] = $personalId;
$params[':pid_match'] = $personalId;
$st = $pdo->prepare($sql);
$st->execute($params);
$courses = $st->fetchAll(PDO::FETCH_ASSOC);

if ($cursoId <= 0 && !empty($courses)) $cursoId = (int)$courses[0]['id'];
$activeCourse = null;
foreach ($courses as $course) {
    if ((int)$course['id'] === $cursoId) { $activeCourse = $course; break; }
}

$clases = [];
$notas = [];
if ($activeCourse) {
    $st = $pdo->prepare("SELECT * FROM ens_clases WHERE unidad_id = :uid AND curso_id = :curso AND COALESCE(visible_cursante,1) = 1 ORDER BY COALESCE(fecha, DATE(created_at)) DESC, id DESC");
    $st->execute([':uid' => $unidadId, ':curso' => $cursoId]);
    $clases = $st->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($activeCourse['cursante_id'])) {
        $st = $pdo->prepare("SELECT * FROM ens_calificaciones WHERE unidad_id = :uid AND curso_id = :curso AND cursante_id = :cur ORDER BY COALESCE(fecha, DATE(created_at)) DESC, id DESC");
        $st->execute([':uid' => $unidadId, ':curso' => $cursoId, ':cur' => (int)$activeCourse['cursante_id']]);
        $notas = $st->fetchAll(PDO::FETCH_ASSOC);
    }
}

$years = range((int)date('Y') + 1, (int)date('Y') - 5);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Mis cursos</title>
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
  .panel{background:#fff;border:1px solid #e2e5e8;box-shadow:0 1px 2px rgba(0,0,0,.03);padding:30px 30px 36px}.panel-title{font-size:1.15rem;font-weight:700;margin:34px 0 22px}.panel-title::after{content:"";display:block;width:74px;height:2px;background:var(--gold);margin-top:10px}
  .controls{display:grid;grid-template-columns:110px minmax(220px,360px) 1fr 285px 120px;gap:18px;align-items:center;margin-bottom:30px}.btn-darkish,.selectish{height:40px;background:#5a646b;color:#fff;border:0;border-radius:0;box-shadow:0 2px 5px rgba(0,0,0,.22);padding:0 18px}.search{height:42px;border:0;border-bottom:1px solid #cbd1d5;background:transparent;padding:0 10px;color:#4b5560}.search-x{width:34px;height:34px;border-radius:50%;background:#f1f2f3;color:#8b9298;display:grid;place-items:center;margin-left:-64px;z-index:2}
  .cards{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px}.card-course{background:#fff;border:1px solid #e1e4e7;border-radius:2px;box-shadow:0 3px 10px rgba(0,0,0,.12);overflow:hidden;color:#344248;text-decoration:none;min-height:278px;display:flex;flex-direction:column}.course-art{height:176px;background:linear-gradient(150deg,#ffb000,#ffd34c 54%,#ff8c22);position:relative;overflow:hidden}.course-art::before{content:"";position:absolute;inset:0;background:repeating-linear-gradient(0deg,rgba(255,255,255,.16) 0 2px,transparent 2px 5px),radial-gradient(circle at 20% 34%,rgba(238,91,20,.75) 0 18px,transparent 19px),radial-gradient(circle at 76% 22%,rgba(255,255,255,.9) 0 2px,transparent 3px);opacity:.45}.course-art strong{position:absolute;left:26px;top:54px;color:#d86720;font-size:1.68rem;line-height:1.05;text-transform:uppercase;max-width:210px}.wifi{position:absolute;right:14px;bottom:24px;color:#fff;font-size:2.4rem}
  .card-body{padding:16px 14px 12px;position:relative;flex:1}.card-title{font-size:1rem;font-weight:700;text-align:center;line-height:1.45;min-height:46px}.card-sub{font-size:.9rem;color:#9199a0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.kebab{position:absolute;right:10px;bottom:10px;width:36px;height:36px;border-radius:50%;background:#e4e9ed;color:#6d747b;display:grid;place-items:center}
  .detail{margin-top:22px;display:grid;grid-template-columns:1fr;gap:18px}.box{background:#fff;border:1px solid #e2e5e8;padding:18px}.box h2{font-size:1.15rem;font-weight:700;margin:0 0 14px}.detail aside.box{display:none}.item{display:flex;justify-content:space-between;gap:12px;border-bottom:1px solid #edf0f2;padding:12px 0}.item:last-child{border-bottom:0}.muted{color:#7b858d}.pill{display:inline-flex;align-items:center;padding:3px 8px;border-radius:999px;background:#eef1f2;color:#59646c;font-size:.75rem;font-weight:700}
  .empty{padding:26px;text-align:center;color:#6b747c;border:1px dashed #c8d0d5;background:#fafafa}
  @media (max-width:1100px){.cards{grid-template-columns:repeat(2,minmax(0,1fr))}.controls{grid-template-columns:1fr 1fr}.detail{grid-template-columns:1fr}.mast{height:auto;padding:20px 18px}.brand-title{font-size:1.8rem}.nav{overflow:auto;padding:0 12px}}
  @media (max-width:650px){.cards{grid-template-columns:1fr}.controls{grid-template-columns:1fr}.panel{padding:18px}.userbar .name{display:none}.avatar{width:54px;height:54px}.brand-sub{display:none}}
</style>
</head>
<body>
<div class="topline"></div>
<header class="mast">
  <div class="brand">
    <img src="<?= mc_e($ESCUDO) ?>" alt="ECMILM">
    <div class="brand-title">ECMILM</div>
    <div class="brand-sub">Escuela Militar de Monta&ntilde;a</div>
  </div>
  <div class="userbar">
    <div class="round"><i class="bi bi-bell"></i></div>
    <div class="round"><i class="bi bi-chat-dots"></i><span class="badge-dot">0</span></div>
    <div class="name"><?= mc_e(mb_strtoupper($displayName, 'UTF-8')) ?></div>
    <div class="avatar"><?= mc_e($initials) ?></div>
  </div>
</header>
<nav class="nav">
  <a href="../inicio.php">P&aacute;gina Principal</a>
  <a class="active" href="division_ensenanza_mis_cursos.php?anio=<?= (int)$anio ?>">Mis cursos</a>
  <a href="../calendario.php?area=ENS">Calendario</a>
  <a href="#" class="ms-auto"><i class="bi bi-search"></i></a>
</nav>
<main class="page">
  <h1>Mis cursos</h1>
  <section class="panel">
    <div class="panel-title">Vista general de curso</div>
    <form class="controls" method="get">
      <select class="btn-darkish" name="anio" onchange="this.form.submit()">
        <?php foreach ($years as $y): ?><option value="<?= (int)$y ?>" <?= $y === $anio ? 'selected' : '' ?>><?= (int)$y ?></option><?php endforeach; ?>
      </select>
      <input class="search" name="q" value="<?= mc_e($q) ?>" placeholder="Buscar">
      <a class="search-x" href="?anio=<?= (int)$anio ?>"><i class="bi bi-x-lg"></i></a>
      <select class="selectish" name="sort" onchange="this.form.submit()">
        <option value="nombre" <?= $sort === 'nombre' ? 'selected' : '' ?>>Ordenar por nombre del curso</option>
        <option value="fecha" <?= $sort === 'fecha' ? 'selected' : '' ?>>Ordenar por fecha</option>
      </select>
      <button class="selectish" type="submit">Tarjeta</button>
    </form>

    <?php if (empty($courses)): ?>
      <div class="empty">No ten&eacute;s cursos asignados para este a&ntilde;o. Si corresponde, Divisi&oacute;n Ense&ntilde;anza debe cargarte como cursante.</div>
    <?php else: ?>
      <div class="cards">
        <?php foreach ($courses as $course): ?>
          <a class="card-course" href="?anio=<?= (int)$anio ?>&curso_id=<?= (int)$course['id'] ?>&q=<?= mc_e(rawurlencode($q)) ?>&sort=<?= mc_e($sort) ?>">
            <div class="course-art">
              <strong><?= mc_e($anio >= 2026 ? 'Primer cuatrimestre' : 'Ciclo lectivo') ?></strong>
              <i class="bi bi-wifi wifi"></i>
            </div>
            <div class="card-body">
              <div class="card-title"><?= mc_e($course['nombre']) ?></div>
              <div class="card-sub">ECMILM - <?= mc_e($course['descripcion'] ?: 'Divisi&oacute;n Ense&ntilde;anza') ?></div>
              <div class="mt-2"><span class="pill"><?= (int)$course['clases_count'] ?> clases</span> <span class="pill"><?= mc_e($course['estado_cursante'] ?? 'inscripto') ?></span></div>
              <div class="kebab"><i class="bi bi-three-dots-vertical"></i></div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <?php if ($activeCourse): ?>
    <section class="detail">
      <div class="box">
        <h2><?= mc_e($activeCourse['nombre']) ?></h2>
        <?php if (empty($clases)): ?>
          <div class="empty">Todav&iacute;a no hay clases cargadas para esta materia.</div>
        <?php else: ?>
          <?php foreach ($clases as $cl): ?>
            <div class="item">
              <div>
                <strong><?= mc_e($cl['titulo']) ?></strong>
                <div class="muted"><?= mc_e($cl['descripcion'] ?? '') ?></div>
              </div>
              <div class="text-end">
                <div><span class="pill"><?= mc_e($cl['tipo']) ?></span></div>
                <?php if (!empty($cl['archivo_rel'])): ?><a class="btn btn-sm btn-outline-secondary mt-2" target="_blank" href="division_ensenanza_carpeta.php?download=1&path=<?= mc_e(rawurlencode((string)$cl['archivo_rel'])) ?>">Abrir</a><?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <aside class="box">
        <h2>Calificaciones</h2>
        <?php if (empty($notas)): ?>
          <div class="muted">Sin calificaciones publicadas.</div>
        <?php else: ?>
          <?php foreach ($notas as $nota): ?>
            <div class="item">
              <div><strong><?= mc_e($nota['actividad']) ?></strong><div class="muted"><?= mc_e($nota['fecha'] ?: '-') ?></div></div>
              <strong><?= mc_e(number_format((float)$nota['nota'], 2, ',', '.')) ?></strong>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </aside>
    </section>
  <?php endif; ?>
</main>
</body>
</html>
