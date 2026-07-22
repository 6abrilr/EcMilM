<?php
// public/division_ensenanza/division_ensenanza_cursantes.php
declare(strict_types=1);

require_once __DIR__ . '/../../auth/bootstrap.php';
require_login();
require_once __DIR__ . '/../../config/db.php';

function ens_c_e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function ens_c_clean(string $v): string {
    $v = trim($v);
    $v = preg_replace('/[^\pL\pN\s._@,+()-]+/u', '', $v) ?? '';
    return trim(preg_replace('/\s+/', ' ', $v) ?? '');
}
function ens_c_col(PDO $pdo, string $table, string $col): bool {
    $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c");
    $st->execute([':t' => $table, ':c' => $col]);
    return (int)$st->fetchColumn() > 0;
}
function ens_c_schema(PDO $pdo): void {
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
    foreach ([
        'fuerza' => "ALTER TABLE ens_cursantes ADD COLUMN fuerza VARCHAR(80) NULL AFTER dni",
        'unidad_origen' => "ALTER TABLE ens_cursantes ADD COLUMN unidad_origen VARCHAR(160) NULL AFTER fuerza",
        'destino' => "ALTER TABLE ens_cursantes ADD COLUMN destino VARCHAR(160) NULL AFTER unidad_origen",
        'rol_curso' => "ALTER TABLE ens_cursantes ADD COLUMN rol_curso VARCHAR(120) NULL AFTER destino",
        'observaciones' => "ALTER TABLE ens_cursantes ADD COLUMN observaciones TEXT NULL AFTER estado",
    ] as $col => $sql) {
        if (!ens_c_col($pdo, 'ens_cursantes', $col)) $pdo->exec($sql);
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
$anio = (int)($_GET['anio'] ?? date('Y'));
if ($anio < 2000 || $anio > 2100) $anio = (int)date('Y');

ens_c_schema($pdo);
$ok = '';
$err = '';
$cursoId = (int)($_GET['curso_id'] ?? $_POST['curso_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (function_exists('csrf_verify')) csrf_verify();
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'add') {
            $cursoId = (int)($_POST['curso_id'] ?? 0);
            if ($cursoId <= 0) throw new RuntimeException('Seleccione un curso.');
            $personalId = (int)($_POST['personal_id'] ?? 0);
            $grado = ens_c_clean((string)($_POST['grado'] ?? ''));
            $nombre = ens_c_clean((string)($_POST['apellido_nombre'] ?? ''));
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
            if ($nombre === '') throw new RuntimeException('Cargue apellido y nombre del cursante.');
            $st = $pdo->prepare("
                INSERT INTO ens_cursantes
                  (unidad_id, curso_id, personal_id, grado, apellido_nombre, dni, fuerza, unidad_origen, destino, rol_curso, estado, observaciones)
                VALUES
                  (:uid,:curso,:pid,:grado,:nombre,:dni,:fuerza,:unidad_origen,:destino,:rol,:estado,:obs)
            ");
            $st->execute([
                ':uid' => $unidadId,
                ':curso' => $cursoId,
                ':pid' => $personalId > 0 ? $personalId : null,
                ':grado' => $grado ?: null,
                ':nombre' => $nombre,
                ':dni' => $dni ?: null,
                ':fuerza' => ens_c_clean((string)($_POST['fuerza'] ?? '')) ?: null,
                ':unidad_origen' => ens_c_clean((string)($_POST['unidad_origen'] ?? '')) ?: null,
                ':destino' => ens_c_clean((string)($_POST['destino'] ?? '')) ?: null,
                ':rol' => ens_c_clean((string)($_POST['rol_curso'] ?? '')) ?: null,
                ':estado' => (string)($_POST['estado'] ?? 'inscripto'),
                ':obs' => trim((string)($_POST['observaciones'] ?? '')) ?: null,
            ]);
            $ok = 'Cursante agregado.';
        } elseif ($action === 'estado') {
            $id = (int)($_POST['id'] ?? 0);
            $estado = (string)($_POST['estado'] ?? 'inscripto');
            $pdo->prepare("UPDATE ens_cursantes SET estado = :estado WHERE id = :id AND unidad_id = :uid")->execute([':estado' => $estado, ':id' => $id, ':uid' => $unidadId]);
            $ok = 'Estado actualizado.';
        }
    } catch (Throwable $ex) {
        $err = $ex->getMessage();
    }
}

$st = $pdo->prepare("SELECT * FROM ens_cursos WHERE unidad_id = :uid AND anio = :anio ORDER BY FIELD(estado,'en_curso','planificado','finalizado','archivado'), nombre ASC");
$st->execute([':uid' => $unidadId, ':anio' => $anio]);
$cursos = $st->fetchAll(PDO::FETCH_ASSOC);
if ($cursoId <= 0 && !empty($cursos)) $cursoId = (int)$cursos[0]['id'];

$personal = [];
try {
    $st = $pdo->prepare("SELECT id, grado, arma, apellido_nombre, dni FROM personal_unidad WHERE unidad_id = :uid AND apellido_nombre IS NOT NULL AND apellido_nombre <> '' ORDER BY apellido_nombre ASC LIMIT 900");
    $st->execute([':uid' => $unidadId]);
    $personal = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $ex) {}

$cursantes = [];
if ($cursoId > 0) {
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
<title>Modo cursantes</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
  body{min-height:100vh;margin:0;color:#fff;background:linear-gradient(160deg,rgba(0,0,0,.84),rgba(2,6,23,.78)),url("<?= ens_c_e($IMG_BG) ?>") center/cover fixed no-repeat;font-family:system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,sans-serif;}
  .wrap{max-width:1480px;margin:auto;padding:18px}.top,.box{background:rgba(15,23,42,.9);border:1px solid rgba(148,163,184,.34);border-radius:18px;box-shadow:0 18px 45px rgba(0,0,0,.42)}
  .top{padding:20px;margin-bottom:16px;display:flex;justify-content:space-between;gap:14px;flex-wrap:wrap}.brand{display:flex;gap:14px;align-items:center}.brand img{width:58px;height:58px;object-fit:contain}.kicker{color:#67e8f9;font-size:.75rem;letter-spacing:.14em;text-transform:uppercase;font-weight:900}.title{font-size:2rem;font-weight:950}.muted{color:#dbeafe}
  .btn-main,.btn-ghost{display:inline-flex;align-items:center;justify-content:center;gap:8px;border-radius:14px;padding:.62rem .95rem;font-weight:900;text-decoration:none;border:1px solid rgba(148,163,184,.3)}.btn-main{background:#22c55e;color:#04130a;border-color:#22c55e}.btn-ghost{background:rgba(2,6,23,.55);color:#fff}.btn-ghost:hover{color:#fff;border-color:#60a5fa}
  .box{padding:18px;margin-bottom:16px}.box-title{font-weight:950;font-size:1.12rem;margin-bottom:12px}.form-control,.form-select{background:rgba(2,6,23,.82)!important;border:1px solid rgba(148,163,184,.36)!important;color:#fff!important;border-radius:12px}.form-control::placeholder{color:#94a3b8}.form-label{color:#fff;font-weight:900;font-size:.84rem}
  .table-wrap{overflow:auto;border-radius:14px;border:1px solid rgba(148,163,184,.24)}table{width:100%;min-width:1120px;border-collapse:collapse;background:rgba(2,6,23,.45)}th,td{padding:.72rem .8rem;border-bottom:1px solid rgba(148,163,184,.18);color:#fff;vertical-align:middle}th{background:rgba(15,23,42,.96);color:#bfdbfe;text-transform:uppercase;letter-spacing:.08em;font-size:.73rem}.pill{padding:.24rem .56rem;border-radius:999px;background:rgba(148,163,184,.18);border:1px solid rgba(148,163,184,.28);font-weight:900;font-size:.72rem}
</style>
</head>
<body>
<main class="wrap">
  <section class="top">
    <div class="brand">
      <img src="<?= ens_c_e($ESCUDO) ?>" alt="ECMILM">
      <div><div class="kicker">Division Ensenanza</div><div class="title">Modo cursantes</div><div class="muted">Listas por curso con fuerza, unidad, destino, rol y estado.</div></div>
    </div>
    <div class="d-flex gap-2 flex-wrap align-items-start">
      <a class="btn-ghost" href="division_ensenanza.php?anio=<?= (int)$anio ?>&curso_id=<?= (int)$cursoId ?>"><i class="bi bi-arrow-left"></i> Volver</a>
      <a class="btn-main" href="division_ensenanza_profesores.php?anio=<?= (int)$anio ?>"><i class="bi bi-person-video3"></i> Modo profesores</a>
    </div>
  </section>
  <?php if ($ok !== ''): ?><div class="alert alert-success"><?= ens_c_e($ok) ?></div><?php endif; ?>
  <?php if ($err !== ''): ?><div class="alert alert-danger"><?= ens_c_e($err) ?></div><?php endif; ?>
  <section class="box">
    <form class="row g-2" method="get">
      <div class="col-md-2"><label class="form-label">A&ntilde;o</label><select class="form-select" name="anio" onchange="this.form.submit()"><?php foreach ($years as $y): ?><option value="<?= (int)$y ?>" <?= $y === $anio ? 'selected' : '' ?>><?= (int)$y ?></option><?php endforeach; ?></select></div>
      <div class="col-md-8"><label class="form-label">Curso</label><select class="form-select" name="curso_id" onchange="this.form.submit()"><?php foreach ($cursos as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === $cursoId ? 'selected' : '' ?>><?= ens_c_e($c['nombre']) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2 d-flex align-items-end"><button class="btn-main w-100" type="submit">Abrir</button></div>
    </form>
  </section>
  <section class="box">
    <div class="box-title">Agregar cursante</div>
    <?php if (empty($cursos)): ?>
      <div class="muted">Primero crea un curso desde la pantalla principal.</div>
    <?php else: ?>
      <form class="row g-2" method="post">
        <?php if (function_exists('csrf_input')) echo csrf_input(); ?>
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="curso_id" value="<?= (int)$cursoId ?>">
        <div class="col-md-5"><label class="form-label">Personal existente</label><select class="form-select" name="personal_id"><option value="0">Carga manual</option><?php foreach ($personal as $p): ?><option value="<?= (int)$p['id'] ?>"><?= ens_c_e(trim(($p['grado'] ?? '') . ' ' . ($p['arma'] ?? '') . ' ' . ($p['apellido_nombre'] ?? '') . ' - DNI ' . ($p['dni'] ?? ''))) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><label class="form-label">Grado</label><input class="form-control" name="grado"></div>
        <div class="col-md-3"><label class="form-label">Apellido y nombre</label><input class="form-control" name="apellido_nombre"></div>
        <div class="col-md-2"><label class="form-label">DNI</label><input class="form-control" name="dni"></div>
        <div class="col-md-2"><label class="form-label">Fuerza</label><input class="form-control" name="fuerza" placeholder="EA"></div>
        <div class="col-md-3"><label class="form-label">Unidad</label><input class="form-control" name="unidad_origen"></div>
        <div class="col-md-3"><label class="form-label">Destino</label><input class="form-control" name="destino"></div>
        <div class="col-md-2"><label class="form-label">Rol</label><input class="form-control" name="rol_curso" placeholder="Cursante"></div>
        <div class="col-md-2"><label class="form-label">Estado</label><select class="form-select" name="estado"><option value="inscripto">Inscripto</option><option value="regular">Regular</option><option value="aprobado">Aprobado</option><option value="desaprobado">Desaprobado</option><option value="baja">Baja</option></select></div>
        <div class="col-md-8"><label class="form-label">Observaciones</label><input class="form-control" name="observaciones"></div>
        <div class="col-md-2 d-flex align-items-end"><button class="btn-main w-100" type="submit"><i class="bi bi-plus-lg"></i> Agregar</button></div>
      </form>
    <?php endif; ?>
  </section>
  <section class="box">
    <div class="box-title">Lista de cursantes</div>
    <div class="table-wrap"><table><thead><tr><th>Grado</th><th>Cursante</th><th>DNI</th><th>Fuerza</th><th>Unidad</th><th>Destino</th><th>Rol</th><th>Estado</th><th>Observaciones</th><th>Actualizar</th></tr></thead><tbody>
      <?php foreach ($cursantes as $c): ?>
        <tr>
          <td><?= ens_c_e($c['grado'] ?? '-') ?></td>
          <td><strong><?= ens_c_e($c['apellido_nombre']) ?></strong></td>
          <td><?= ens_c_e($c['dni'] ?? '-') ?></td>
          <td><?= ens_c_e($c['fuerza'] ?? '-') ?></td>
          <td><?= ens_c_e($c['unidad_origen'] ?? '-') ?></td>
          <td><?= ens_c_e($c['destino'] ?? '-') ?></td>
          <td><?= ens_c_e($c['rol_curso'] ?? '-') ?></td>
          <td><span class="pill"><?= ens_c_e($c['estado']) ?></span></td>
          <td><?= ens_c_e($c['observaciones'] ?? '-') ?></td>
          <td><form class="d-flex gap-1" method="post"><?php if (function_exists('csrf_input')) echo csrf_input(); ?><input type="hidden" name="action" value="estado"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="curso_id" value="<?= (int)$cursoId ?>"><select class="form-select form-select-sm" name="estado"><option value="inscripto">Inscripto</option><option value="regular">Regular</option><option value="aprobado">Aprobado</option><option value="desaprobado">Desaprobado</option><option value="baja">Baja</option></select><button class="btn btn-outline-light btn-sm" type="submit">OK</button></form></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($cursantes)): ?><tr><td colspan="10" class="muted">No hay cursantes cargados para este curso.</td></tr><?php endif; ?>
    </tbody></table></div>
  </section>
</main>
</body>
</html>
