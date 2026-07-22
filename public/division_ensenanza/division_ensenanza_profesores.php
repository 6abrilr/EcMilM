<?php
// public/division_ensenanza/division_ensenanza_profesores.php
declare(strict_types=1);

require_once __DIR__ . '/../../auth/bootstrap.php';
require_login();
require_once __DIR__ . '/../../config/db.php';

function ens_p_e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function ens_p_clean(string $v): string {
    $v = trim($v);
    $v = preg_replace('/[^\pL\pN\s._@,+()-]+/u', '', $v) ?? '';
    return trim(preg_replace('/\s+/', ' ', $v) ?? '');
}
function ens_p_col(PDO $pdo, string $table, string $col): bool {
    $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c");
    $st->execute([':t' => $table, ':c' => $col]);
    return (int)$st->fetchColumn() > 0;
}
function ens_p_schema(PDO $pdo): void {
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
    foreach ([
        'anio' => "ALTER TABLE ens_profesores ADD COLUMN anio SMALLINT NULL AFTER unidad_id",
        'personal_id' => "ALTER TABLE ens_profesores ADD COLUMN personal_id INT NULL AFTER anio",
        'fuerza' => "ALTER TABLE ens_profesores ADD COLUMN fuerza VARCHAR(80) NULL AFTER dni",
        'unidad_origen' => "ALTER TABLE ens_profesores ADD COLUMN unidad_origen VARCHAR(160) NULL AFTER fuerza",
        'destino' => "ALTER TABLE ens_profesores ADD COLUMN destino VARCHAR(160) NULL AFTER unidad_origen",
        'especialidad' => "ALTER TABLE ens_profesores ADD COLUMN especialidad VARCHAR(160) NULL AFTER destino",
        'cargo' => "ALTER TABLE ens_profesores ADD COLUMN cargo VARCHAR(160) NULL AFTER especialidad",
        'telefono' => "ALTER TABLE ens_profesores ADD COLUMN telefono VARCHAR(80) NULL AFTER cargo",
        'email' => "ALTER TABLE ens_profesores ADD COLUMN email VARCHAR(160) NULL AFTER telefono",
        'activo' => "ALTER TABLE ens_profesores ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1 AFTER email",
        'observaciones' => "ALTER TABLE ens_profesores ADD COLUMN observaciones TEXT NULL AFTER activo",
    ] as $col => $sql) {
        if (!ens_p_col($pdo, 'ens_profesores', $col)) $pdo->exec($sql);
    }

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
}

$SELF_WEB = (string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
$BASE_DIR_WEB = rtrim(str_replace('\\', '/', dirname($SELF_WEB)), '/');
$BASE_PUBLIC_WEB = rtrim(str_replace('\\', '/', dirname($BASE_DIR_WEB)), '/');
$BASE_APP_WEB = rtrim(str_replace('\\', '/', dirname($BASE_PUBLIC_WEB)), '/');
$ASSET_WEB = $BASE_APP_WEB . '/assets';
$IMG_BG = $ASSET_WEB . '/img/fondo.png';
$ESCUDO = $ASSET_WEB . '/img/ecmilm.png';
$unidadId = (int)($_SESSION['unidad_id'] ?? 1);
$anio = (int)($_GET['anio'] ?? date('Y'));
if ($anio < 2000 || $anio > 2100) $anio = (int)date('Y');

ens_p_schema($pdo);
$ok = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (function_exists('csrf_verify')) csrf_verify();
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'add') {
            $personalId = (int)($_POST['personal_id'] ?? 0);
            $grado = ens_p_clean((string)($_POST['grado'] ?? ''));
            $nombre = ens_p_clean((string)($_POST['apellido_nombre'] ?? ''));
            $dni = preg_replace('/\D+/', '', (string)($_POST['dni'] ?? '')) ?? '';
            if ($personalId > 0) {
                $st = $pdo->prepare("SELECT id, grado, apellido_nombre, dni FROM personal_unidad WHERE id = :id LIMIT 1");
                $st->execute([':id' => $personalId]);
                if ($p = $st->fetch(PDO::FETCH_ASSOC)) {
                    $grado = (string)($p['grado'] ?? $grado);
                    $nombre = (string)($p['apellido_nombre'] ?? $nombre);
                    $dni = preg_replace('/\D+/', '', (string)($p['dni'] ?? $dni)) ?? $dni;
                }
            }
            if ($nombre === '') throw new RuntimeException('Cargue apellido y nombre del profesor.');
            $st = $pdo->prepare("
                INSERT INTO ens_profesores
                  (unidad_id, anio, personal_id, grado, apellido_nombre, dni, fuerza, unidad_origen, destino, especialidad, cargo, telefono, email, activo, observaciones)
                VALUES
                  (:uid,:anio,:pid,:grado,:nombre,:dni,:fuerza,:unidad_origen,:destino,:especialidad,:cargo,:telefono,:email,1,:obs)
            ");
            $st->execute([
                ':uid' => $unidadId,
                ':anio' => (int)($_POST['anio'] ?? $anio),
                ':pid' => $personalId > 0 ? $personalId : null,
                ':grado' => $grado ?: null,
                ':nombre' => $nombre,
                ':dni' => $dni ?: null,
                ':fuerza' => ens_p_clean((string)($_POST['fuerza'] ?? '')) ?: null,
                ':unidad_origen' => ens_p_clean((string)($_POST['unidad_origen'] ?? '')) ?: null,
                ':destino' => ens_p_clean((string)($_POST['destino'] ?? '')) ?: null,
                ':especialidad' => ens_p_clean((string)($_POST['especialidad'] ?? '')) ?: null,
                ':cargo' => ens_p_clean((string)($_POST['cargo'] ?? '')) ?: null,
                ':telefono' => ens_p_clean((string)($_POST['telefono'] ?? '')) ?: null,
                ':email' => ens_p_clean((string)($_POST['email'] ?? '')) ?: null,
                ':obs' => trim((string)($_POST['observaciones'] ?? '')) ?: null,
            ]);
            $ok = 'Profesor agregado.';
        } elseif ($action === 'assign_course') {
            $profesorId = (int)($_POST['profesor_id'] ?? 0);
            $cursoId = (int)($_POST['curso_id'] ?? 0);
            if ($profesorId <= 0 || $cursoId <= 0) throw new RuntimeException('Seleccione profesor y curso.');
            $rol = ens_p_clean((string)($_POST['rol'] ?? 'Instructor'));
            $st = $pdo->prepare("
                INSERT INTO ens_profesor_cursos (unidad_id, profesor_id, curso_id, rol, activo)
                VALUES (:uid,:prof,:curso,:rol,1)
                ON DUPLICATE KEY UPDATE rol = VALUES(rol), activo = 1
            ");
            $st->execute([':uid' => $unidadId, ':prof' => $profesorId, ':curso' => $cursoId, ':rol' => $rol ?: 'Instructor']);
            $ok = 'Curso asignado al instructor.';
        } elseif ($action === 'unassign_course') {
            $id = (int)($_POST['id'] ?? 0);
            $pdo->prepare("UPDATE ens_profesor_cursos SET activo = 0 WHERE id = :id AND unidad_id = :uid")->execute([':id' => $id, ':uid' => $unidadId]);
            $ok = 'Asignacion desactivada.';
        } elseif ($action === 'toggle') {
            $id = (int)($_POST['id'] ?? 0);
            $pdo->prepare("UPDATE ens_profesores SET activo = IF(activo=1,0,1) WHERE id = :id AND unidad_id = :uid")->execute([':id' => $id, ':uid' => $unidadId]);
            $ok = 'Estado actualizado.';
        }
    } catch (Throwable $ex) {
        $err = $ex->getMessage();
    }
}

$personal = [];
try {
    $st = $pdo->prepare("SELECT id, grado, arma, apellido_nombre, dni FROM personal_unidad WHERE unidad_id = :uid AND apellido_nombre IS NOT NULL AND apellido_nombre <> '' ORDER BY apellido_nombre ASC LIMIT 900");
    $st->execute([':uid' => $unidadId]);
    $personal = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $ex) {}

$st = $pdo->prepare("SELECT * FROM ens_profesores WHERE unidad_id = :uid AND (anio IS NULL OR anio = :anio) ORDER BY activo DESC, apellido_nombre ASC");
$st->execute([':uid' => $unidadId, ':anio' => $anio]);
$profesores = $st->fetchAll(PDO::FETCH_ASSOC);
$st = $pdo->prepare("SELECT id, nombre FROM ens_cursos WHERE unidad_id = :uid AND anio = :anio ORDER BY nombre ASC");
$st->execute([':uid' => $unidadId, ':anio' => $anio]);
$cursos = $st->fetchAll(PDO::FETCH_ASSOC);
$st = $pdo->prepare("
    SELECT pc.*, c.nombre AS curso_nombre
    FROM ens_profesor_cursos pc
    INNER JOIN ens_cursos c ON c.id = pc.curso_id
    WHERE pc.unidad_id = :uid AND c.anio = :anio AND pc.activo = 1
    ORDER BY c.nombre ASC
");
$st->execute([':uid' => $unidadId, ':anio' => $anio]);
$assignRows = $st->fetchAll(PDO::FETCH_ASSOC);
$assignments = [];
foreach ($assignRows as $ar) {
    $assignments[(int)$ar['profesor_id']][] = $ar;
}
$years = range((int)date('Y') + 1, (int)date('Y') - 5);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Modo profesores</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
  body{min-height:100vh;margin:0;color:#fff;background:linear-gradient(160deg,rgba(0,0,0,.84),rgba(2,6,23,.78)),url("<?= ens_p_e($IMG_BG) ?>") center/cover fixed no-repeat;font-family:system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,sans-serif;}
  .wrap{max-width:1480px;margin:auto;padding:18px;}
  .top,.box{background:rgba(15,23,42,.9);border:1px solid rgba(148,163,184,.34);border-radius:18px;box-shadow:0 18px 45px rgba(0,0,0,.42);}
  .top{padding:20px;margin-bottom:16px;display:flex;justify-content:space-between;gap:14px;flex-wrap:wrap;}
  .brand{display:flex;gap:14px;align-items:center}.brand img{width:58px;height:58px;object-fit:contain}.kicker{color:#67e8f9;font-size:.75rem;letter-spacing:.14em;text-transform:uppercase;font-weight:900}.title{font-size:2rem;font-weight:950}
  .btn-main,.btn-ghost{display:inline-flex;align-items:center;justify-content:center;gap:8px;border-radius:14px;padding:.62rem .95rem;font-weight:900;text-decoration:none;border:1px solid rgba(148,163,184,.3)}
  .btn-main{background:#22c55e;color:#04130a;border-color:#22c55e}.btn-ghost{background:rgba(2,6,23,.55);color:#fff}.btn-ghost:hover{color:#fff;border-color:#60a5fa}
  .box{padding:18px;margin-bottom:16px}.box-title{font-weight:950;font-size:1.12rem;margin-bottom:12px}.muted{color:#dbeafe}
  .form-control,.form-select{background:rgba(2,6,23,.82)!important;border:1px solid rgba(148,163,184,.36)!important;color:#fff!important;border-radius:12px}.form-control::placeholder{color:#94a3b8}.form-label{color:#fff;font-weight:900;font-size:.84rem}
  .table-wrap{overflow:auto;border-radius:14px;border:1px solid rgba(148,163,184,.24)}table{width:100%;min-width:1120px;border-collapse:collapse;background:rgba(2,6,23,.45)}th,td{padding:.72rem .8rem;border-bottom:1px solid rgba(148,163,184,.18);color:#fff;vertical-align:middle}th{background:rgba(15,23,42,.96);color:#bfdbfe;text-transform:uppercase;letter-spacing:.08em;font-size:.73rem}.pill{padding:.24rem .56rem;border-radius:999px;background:rgba(148,163,184,.18);border:1px solid rgba(148,163,184,.28);font-weight:900;font-size:.72rem}
</style>
</head>
<body>
<main class="wrap">
  <section class="top">
    <div class="brand">
      <img src="<?= ens_p_e($ESCUDO) ?>" alt="ECMILM">
      <div><div class="kicker">Division Ensenanza</div><div class="title">Modo profesores</div><div class="muted">Listado docente con fuerza, unidad, destino, cargo y especialidad.</div></div>
    </div>
    <div class="d-flex gap-2 flex-wrap align-items-start">
      <a class="btn-ghost" href="division_ensenanza.php?anio=<?= (int)$anio ?>"><i class="bi bi-arrow-left"></i> Volver</a>
      <a class="btn-main" href="division_ensenanza_instructor.php?anio=<?= (int)$anio ?>"><i class="bi bi-easel2"></i> Modo instructor</a>
      <a class="btn-main" href="division_ensenanza_cursantes.php?anio=<?= (int)$anio ?>"><i class="bi bi-mortarboard"></i> Modo cursantes</a>
    </div>
  </section>
  <?php if ($ok !== ''): ?><div class="alert alert-success"><?= ens_p_e($ok) ?></div><?php endif; ?>
  <?php if ($err !== ''): ?><div class="alert alert-danger"><?= ens_p_e($err) ?></div><?php endif; ?>
  <section class="box">
    <div class="box-title">Agregar profesor</div>
    <form class="row g-2" method="post">
      <?php if (function_exists('csrf_input')) echo csrf_input(); ?>
      <input type="hidden" name="action" value="add">
      <div class="col-md-2"><label class="form-label">A&ntilde;o</label><select class="form-select" name="anio"><?php foreach ($years as $y): ?><option value="<?= (int)$y ?>" <?= $y === $anio ? 'selected' : '' ?>><?= (int)$y ?></option><?php endforeach; ?></select></div>
      <div class="col-md-5"><label class="form-label">Personal existente</label><select class="form-select" name="personal_id"><option value="0">Carga manual</option><?php foreach ($personal as $p): ?><option value="<?= (int)$p['id'] ?>"><?= ens_p_e(trim(($p['grado'] ?? '') . ' ' . ($p['arma'] ?? '') . ' ' . ($p['apellido_nombre'] ?? '') . ' - DNI ' . ($p['dni'] ?? ''))) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2"><label class="form-label">Grado</label><input class="form-control" name="grado"></div>
      <div class="col-md-3"><label class="form-label">DNI</label><input class="form-control" name="dni"></div>
      <div class="col-md-4"><label class="form-label">Apellido y nombre</label><input class="form-control" name="apellido_nombre" placeholder="Obligatorio si es carga manual"></div>
      <div class="col-md-2"><label class="form-label">Fuerza</label><input class="form-control" name="fuerza" placeholder="EA"></div>
      <div class="col-md-3"><label class="form-label">Unidad</label><input class="form-control" name="unidad_origen"></div>
      <div class="col-md-3"><label class="form-label">Destino</label><input class="form-control" name="destino"></div>
      <div class="col-md-4"><label class="form-label">Especialidad</label><input class="form-control" name="especialidad"></div>
      <div class="col-md-3"><label class="form-label">Cargo</label><input class="form-control" name="cargo"></div>
      <div class="col-md-2"><label class="form-label">Telefono</label><input class="form-control" name="telefono"></div>
      <div class="col-md-3"><label class="form-label">Email</label><input class="form-control" name="email"></div>
      <div class="col-md-10"><label class="form-label">Observaciones</label><input class="form-control" name="observaciones"></div>
      <div class="col-md-2 d-flex align-items-end"><button class="btn-main w-100" type="submit"><i class="bi bi-plus-lg"></i> Agregar</button></div>
    </form>
  </section>
  <section class="box">
    <div class="box-title">Lista de profesores</div>
    <div class="table-wrap"><table><thead><tr><th>Estado</th><th>Grado</th><th>Profesor</th><th>DNI</th><th>Fuerza</th><th>Unidad</th><th>Destino</th><th>Especialidad</th><th>Cargo</th><th>Cursos</th><th>Accion</th></tr></thead><tbody>
      <?php foreach ($profesores as $p): ?>
        <?php $pid = (int)$p['id']; ?>
        <tr>
          <td><span class="pill"><?= (int)$p['activo'] === 1 ? 'Activo' : 'Inactivo' ?></span></td>
          <td><?= ens_p_e($p['grado'] ?? '-') ?></td>
          <td><strong><?= ens_p_e($p['apellido_nombre']) ?></strong><div class="muted small"><?= ens_p_e($p['observaciones'] ?? '') ?></div></td>
          <td><?= ens_p_e($p['dni'] ?? '-') ?></td>
          <td><?= ens_p_e($p['fuerza'] ?? '-') ?></td>
          <td><?= ens_p_e($p['unidad_origen'] ?? '-') ?></td>
          <td><?= ens_p_e($p['destino'] ?? '-') ?></td>
          <td><?= ens_p_e($p['especialidad'] ?? '-') ?></td>
          <td><?= ens_p_e($p['cargo'] ?? '-') ?></td>
          <td>
            <div class="d-grid gap-1">
              <?php foreach (($assignments[$pid] ?? []) as $as): ?>
                <form class="d-flex gap-1 align-items-center" method="post">
                  <?php if (function_exists('csrf_input')) echo csrf_input(); ?>
                  <input type="hidden" name="action" value="unassign_course">
                  <input type="hidden" name="id" value="<?= (int)$as['id'] ?>">
                  <span class="pill"><?= ens_p_e($as['curso_nombre']) ?></span>
                  <button class="btn btn-outline-light btn-sm py-0" type="submit">Quitar</button>
                </form>
              <?php endforeach; ?>
              <form class="d-flex gap-1" method="post">
                <?php if (function_exists('csrf_input')) echo csrf_input(); ?>
                <input type="hidden" name="action" value="assign_course">
                <input type="hidden" name="profesor_id" value="<?= $pid ?>">
                <select class="form-select form-select-sm" name="curso_id" required>
                  <option value="">Asignar curso</option>
                  <?php foreach ($cursos as $curso): ?><option value="<?= (int)$curso['id'] ?>"><?= ens_p_e($curso['nombre']) ?></option><?php endforeach; ?>
                </select>
                <input class="form-control form-control-sm" name="rol" value="Instructor" style="max-width:120px;">
                <button class="btn btn-outline-light btn-sm" type="submit">OK</button>
              </form>
            </div>
          </td>
          <td><form method="post"><?php if (function_exists('csrf_input')) echo csrf_input(); ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn btn-outline-light btn-sm" type="submit"><?= (int)$p['activo'] === 1 ? 'Desactivar' : 'Activar' ?></button></form></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($profesores)): ?><tr><td colspan="11" class="muted">No hay profesores cargados.</td></tr><?php endif; ?>
    </tbody></table></div>
  </section>
</main>
</body>
</html>
