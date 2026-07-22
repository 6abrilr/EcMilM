<?php
declare(strict_types=1);

require_once __DIR__ . '/../../auth/bootstrap.php';
require_login();
require_once __DIR__ . '/../../config/db.php';

function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function pp_norm_dni(string $dni): string { return preg_replace('/\D+/', '', $dni) ?? ''; }
function pp_valid_date(string $value): ?string {
    $value = trim($value);
    $dt = DateTime::createFromFormat('Y-m-d', $value);
    return ($dt && $dt->format('Y-m-d') === $value) ? $value : null;
}
function pp_post_text(string $key, int $max = 1000): string {
    $value = trim((string)($_POST[$key] ?? ''));
    return mb_strlen($value, 'UTF-8') > $max ? mb_substr($value, 0, $max, 'UTF-8') : $value;
}
function pp_table_column(PDO $pdo, string $table, string $col): bool {
    $st = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c");
    $st->execute([':t' => $table, ':c' => $col]);
    return (int)$st->fetchColumn() > 0;
}

function pp_ensure_tables(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS personal_partes_presentes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            unidad_id INT NOT NULL DEFAULT 1,
            destino_id INT NULL,
            fecha DATE NOT NULL,
            estado ENUM('borrador','elevado','archivado') NOT NULL DEFAULT 'borrador',
            observaciones TEXT NULL,
            creado_por_id INT NULL,
            elevado_at DATETIME NULL,
            archivado_at DATETIME NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_parte_destino_fecha (unidad_id, destino_id, fecha),
            INDEX idx_parte_fecha_estado (unidad_id, fecha, estado),
            INDEX idx_parte_destino (destino_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS personal_partes_presentes_detalle (
            id INT AUTO_INCREMENT PRIMARY KEY,
            parte_id INT NOT NULL,
            personal_id INT NOT NULL,
            situacion ENUM('presente','ausente') NOT NULL DEFAULT 'presente',
            causa ENUM('','servicio','comision','licencia','parte_enfermo','franco','curso','guardia','sin_justificar','otro') NOT NULL DEFAULT '',
            observaciones VARCHAR(500) NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_parte_personal (parte_id, personal_id),
            INDEX idx_det_personal (personal_id),
            CONSTRAINT fk_pp_det_parte FOREIGN KEY (parte_id) REFERENCES personal_partes_presentes(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function pp_role(PDO $pdo, array $user, int $unidadId): array {
    $dni = pp_norm_dni((string)($user['dni'] ?? $user['username'] ?? ''));
    $role = strtoupper(trim((string)($user['rol_app'] ?? $user['role_app'] ?? 'USUARIO')));
    $me = null;
    if ($dni !== '') {
        $st = $pdo->prepare("
            SELECT pu.id, pu.unidad_id, pu.destino_interno, pu.destino_interno_id, pu.destino_id,
                   COALESCE(di.nombre, d.nombre, '') AS destino_nombre,
                   r.codigo AS role_codigo
            FROM personal_unidad pu
            LEFT JOIN roles r ON r.id = pu.role_id
            LEFT JOIN destino_interno di ON di.id = COALESCE(pu.destino_interno_id, pu.destino_interno)
            LEFT JOIN destino d ON d.id = pu.destino_id
            WHERE pu.unidad_id = :uid
              AND REPLACE(REPLACE(REPLACE(pu.dni,'.',''),'-',''),' ','') = :dni
            ORDER BY pu.id DESC
            LIMIT 1
        ");
        $st->execute([':uid' => $unidadId, ':dni' => $dni]);
        $me = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($me && !empty($me['role_codigo'])) $role = strtoupper((string)$me['role_codigo']);
    }
    if ($dni === '41742406' || strtolower(trim((string)($user['username'] ?? ''))) === 'nesrojas') $role = 'SUPERADMIN';
    return ['dni' => $dni, 'role' => $role ?: 'USUARIO', 'me' => $me];
}

function pp_estado_label(string $estado): string {
    return match ($estado) {
        'elevado' => 'Elevado a Personal',
        'archivado' => 'Archivado',
        default => 'Borrador',
    };
}
function pp_causa_label(string $causa): string {
    return match ($causa) {
        'servicio' => 'Servicio',
        'comision' => 'Comisión',
        'licencia' => 'Licencia',
        'parte_enfermo' => 'Parte enfermo',
        'franco' => 'Franco',
        'curso' => 'Curso',
        'guardia' => 'Guardia',
        'sin_justificar' => 'Sin justificar',
        'otro' => 'Otro',
        default => '',
    };
}

pp_ensure_tables($pdo);

$user = function_exists('current_user') ? current_user() : ($_SESSION['user'] ?? []);
$unidadId = function_exists('unidad_activa_id') ? unidad_activa_id() : (int)($user['unidad_id'] ?? 1);
$auth = pp_role($pdo, (array)$user, $unidadId);
$roleCode = (string)$auth['role'];
$me = is_array($auth['me']) ? $auth['me'] : [];
$myPersonalId = (int)($me['id'] ?? 0);
$myDestinoId = (int)($me['destino_interno_id'] ?? $me['destino_interno'] ?? 0);
$myDestinoNombre = trim((string)($me['destino_nombre'] ?? ''));
$isAdmin = in_array($roleCode, ['ADMIN', 'SUPERADMIN'], true);
$isPersonal = $isAdmin || stripos($myDestinoNombre, 'PERSONAL') !== false;
$canSeeAll = $isAdmin || $isPersonal;

$fecha = pp_valid_date((string)($_GET['fecha'] ?? $_POST['fecha'] ?? date('Y-m-d'))) ?? date('Y-m-d');
$destinoId = (int)($_GET['destino_id'] ?? $_POST['destino_id'] ?? 0);
if (!$canSeeAll) $destinoId = $myDestinoId;

$destinos = $pdo->query("SELECT id, nombre FROM destino_interno WHERE estado = 'ACTIVO' ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$destinoById = [];
foreach ($destinos as $d) $destinoById[(int)$d['id']] = (string)$d['nombre'];
if ($destinoId <= 0 && $canSeeAll && $destinos) $destinoId = (int)$destinos[0]['id'];

$flashOk = '';
$flashErr = '';
$allowedSituaciones = ['presente', 'ausente'];
$allowedCausas = ['', 'servicio', 'comision', 'licencia', 'parte_enfermo', 'franco', 'curso', 'guardia', 'sin_justificar', 'otro'];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    try {
        $accion = (string)($_POST['accion'] ?? '');
        $fechaPost = pp_valid_date((string)($_POST['fecha'] ?? ''));
        if (!$fechaPost) throw new RuntimeException('Fecha inválida.');
        $destinoPost = (int)($_POST['destino_id'] ?? 0);
        if (!$canSeeAll) $destinoPost = $myDestinoId;
        if ($destinoPost <= 0 || !isset($destinoById[$destinoPost])) throw new RuntimeException('Dependencia inválida.');
        if (!$canSeeAll && $destinoPost !== $myDestinoId) throw new RuntimeException('Solo podés cargar el parte de tu dependencia.');

        $nuevoEstado = match ($accion) {
            'elevar' => 'elevado',
            'archivar' => $canSeeAll ? 'archivado' : throw new RuntimeException('Solo Personal o administrador puede archivar.'),
            default => 'borrador',
        };

        $ids = array_map('intval', $_POST['personal_id'] ?? []);
        if (!$ids) throw new RuntimeException('No hay personal para guardar en este parte.');

        $pdo->beginTransaction();
        $st = $pdo->prepare("
            INSERT INTO personal_partes_presentes
                (unidad_id, destino_id, fecha, estado, observaciones, creado_por_id, elevado_at, archivado_at)
            VALUES
                (:uid, :destino, :fecha, :estado, :obs, :creador, :elevado, :archivado)
            ON DUPLICATE KEY UPDATE
                estado = VALUES(estado),
                observaciones = VALUES(observaciones),
                creado_por_id = COALESCE(creado_por_id, VALUES(creado_por_id)),
                elevado_at = CASE WHEN VALUES(estado) IN ('elevado','archivado') THEN COALESCE(elevado_at, NOW()) ELSE elevado_at END,
                archivado_at = CASE WHEN VALUES(estado) = 'archivado' THEN COALESCE(archivado_at, NOW()) ELSE archivado_at END
        ");
        $st->execute([
            ':uid' => $unidadId,
            ':destino' => $destinoPost,
            ':fecha' => $fechaPost,
            ':estado' => $nuevoEstado,
            ':obs' => pp_post_text('observaciones', 5000) ?: null,
            ':creador' => $myPersonalId > 0 ? $myPersonalId : null,
            ':elevado' => in_array($nuevoEstado, ['elevado','archivado'], true) ? date('Y-m-d H:i:s') : null,
            ':archivado' => $nuevoEstado === 'archivado' ? date('Y-m-d H:i:s') : null,
        ]);
        $st = $pdo->prepare("SELECT id FROM personal_partes_presentes WHERE unidad_id = :uid AND destino_id = :destino AND fecha = :fecha LIMIT 1");
        $st->execute([':uid' => $unidadId, ':destino' => $destinoPost, ':fecha' => $fechaPost]);
        $parteId = (int)$st->fetchColumn();
        if ($parteId <= 0) throw new RuntimeException('No se pudo crear el parte.');

        $stCheck = $pdo->prepare("
            SELECT id FROM personal_unidad
            WHERE unidad_id = :uid
              AND COALESCE(destino_interno_id, destino_interno) = :destino
              AND id = :pid
            LIMIT 1
        ");
        $stDet = $pdo->prepare("
            INSERT INTO personal_partes_presentes_detalle
                (parte_id, personal_id, situacion, causa, observaciones)
            VALUES
                (:parte_id, :personal_id, :situacion, :causa, :observaciones)
            ON DUPLICATE KEY UPDATE
                situacion = VALUES(situacion),
                causa = VALUES(causa),
                observaciones = VALUES(observaciones)
        ");
        foreach ($ids as $pid) {
            if ($pid <= 0) continue;
            $stCheck->execute([':uid' => $unidadId, ':destino' => $destinoPost, ':pid' => $pid]);
            if (!$stCheck->fetchColumn()) continue;
            $situacion = (string)($_POST['situacion'][$pid] ?? 'presente');
            if (!in_array($situacion, $allowedSituaciones, true)) $situacion = 'presente';
            $causa = (string)($_POST['causa'][$pid] ?? '');
            if (!in_array($causa, $allowedCausas, true)) $causa = '';
            if ($situacion === 'presente') $causa = '';
            $obs = trim((string)($_POST['obs'][$pid] ?? ''));
            if (mb_strlen($obs, 'UTF-8') > 500) $obs = mb_substr($obs, 0, 500, 'UTF-8');
            $stDet->execute([
                ':parte_id' => $parteId,
                ':personal_id' => $pid,
                ':situacion' => $situacion,
                ':causa' => $causa,
                ':observaciones' => $obs !== '' ? $obs : null,
            ]);
        }
        $pdo->commit();
        $flashOk = $nuevoEstado === 'borrador' ? 'Parte guardado como borrador.' : ($nuevoEstado === 'elevado' ? 'Parte elevado a Personal.' : 'Parte archivado.');
        $fecha = $fechaPost;
        $destinoId = $destinoPost;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $flashErr = $ex->getMessage();
    }
}

$parte = null;
$detalleByPersonal = [];
if ($destinoId > 0) {
    $st = $pdo->prepare("SELECT * FROM personal_partes_presentes WHERE unidad_id = :uid AND destino_id = :destino AND fecha = :fecha LIMIT 1");
    $st->execute([':uid' => $unidadId, ':destino' => $destinoId, ':fecha' => $fecha]);
    $parte = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($parte) {
        $st = $pdo->prepare("SELECT * FROM personal_partes_presentes_detalle WHERE parte_id = :pid");
        $st->execute([':pid' => (int)$parte['id']]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) $detalleByPersonal[(int)$row['personal_id']] = $row;
    }
}

$personal = [];
if ($destinoId > 0) {
    $st = $pdo->prepare("
        SELECT id, dni, grado, apellido_nombre, apellido, nombre, tiene_parte_enfermo
        FROM personal_unidad
        WHERE unidad_id = :uid
          AND COALESCE(destino_interno_id, destino_interno) = :destino
        ORDER BY
          CASE grado
            WHEN 'CR' THEN 1 WHEN 'TC' THEN 2 WHEN 'MY' THEN 3 WHEN 'CT' THEN 4 WHEN 'TP' THEN 5 WHEN 'TT' THEN 6
            WHEN 'ST' THEN 7 WHEN 'SM' THEN 8 WHEN 'SP' THEN 9 WHEN 'SA' THEN 10 WHEN 'SI' THEN 11 WHEN 'SG' THEN 12
            WHEN 'CI' THEN 13 WHEN 'CB' THEN 14 ELSE 99 END,
          apellido_nombre, apellido, nombre
    ");
    $st->execute([':uid' => $unidadId, ':destino' => $destinoId]);
    $personal = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$whereHist = ['p.unidad_id = :uid'];
$paramsHist = [':uid' => $unidadId];
if (!$canSeeAll) {
    $whereHist[] = 'p.destino_id = :my_destino';
    $paramsHist[':my_destino'] = $myDestinoId > 0 ? $myDestinoId : -1;
}
$st = $pdo->prepare("
    SELECT p.*, di.nombre AS destino_nombre,
           SUM(d.situacion = 'presente') AS presentes,
           SUM(d.situacion = 'ausente') AS ausentes,
           COUNT(d.id) AS cargados
    FROM personal_partes_presentes p
    LEFT JOIN destino_interno di ON di.id = p.destino_id
    LEFT JOIN personal_partes_presentes_detalle d ON d.parte_id = p.id
    WHERE " . implode(' AND ', $whereHist) . "
    GROUP BY p.id
    ORDER BY p.fecha DESC, p.created_at DESC
    LIMIT 80
");
$st->execute($paramsHist);
$historial = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

$presentes = 0;
$ausentes = 0;
foreach ($personal as $p) {
    $det = $detalleByPersonal[(int)$p['id']] ?? null;
    $sit = (string)($det['situacion'] ?? 'presente');
    if ($sit === 'ausente') $ausentes++; else $presentes++;
}

$SELF_WEB = (string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
$BASE_DIR_WEB = rtrim(str_replace('\\', '/', dirname($SELF_WEB)), '/');
$BASE_PUBLIC_WEB = rtrim(str_replace('\\', '/', dirname($BASE_DIR_WEB)), '/');
$BASE_APP_WEB = rtrim(str_replace('\\', '/', dirname($BASE_PUBLIC_WEB)), '/');
$ASSET_WEB = $BASE_APP_WEB . '/assets';
$IMG_BG = $ASSET_WEB . '/img/fondo.png';
$ESCUDO = $ASSET_WEB . '/img/ecmilm.png';
$FAVICON = $ESCUDO;
$UNIDAD_NOMBRE = 'Unidad';
$UNIDAD_SUB = '';
if (function_exists('unidad_context')) {
    $ctx = unidad_context($pdo, $unidadId);
    $IMG_BG = (string)$ctx['bg_url'];
    $ESCUDO = (string)$ctx['escudo_url'];
    $FAVICON = (string)$ctx['icon_url'];
    $UNIDAD_NOMBRE = (string)$ctx['nombre_completo'];
    $UNIDAD_SUB = (string)$ctx['subnombre'];
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Parte de presentes diario · <?= e($UNIDAD_NOMBRE) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="icon" href="<?= e($FAVICON) ?>">
<style>
  :root{--panel:#0f172a;--line:#334155;--text:#e5e7eb;--muted:#94a3b8;--ok:#22c55e;--warn:#fbbf24;--hot:#fb7185;--blue:#38bdf8}
  *{box-sizing:border-box} body{margin:0;min-height:100vh;background:#000;color:var(--text);font-family:system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,sans-serif}
  .page-bg{position:fixed;inset:0;z-index:-2;background:linear-gradient(160deg,rgba(0,0,0,.90),rgba(2,6,23,.72),rgba(0,0,0,.92)),url("<?= e($IMG_BG) ?>") center/cover no-repeat;background-attachment:fixed}
  .hero{display:flex;gap:14px;align-items:center;padding:14px 18px;border-bottom:1px solid rgba(148,163,184,.25);background:rgba(2,6,23,.62);backdrop-filter:blur(7px)}
  .hero img{width:54px;height:54px;object-fit:contain}.hero h1{font-size:1.12rem;margin:0;font-weight:950}.hero p{margin:2px 0 0;color:#cbd5e1;font-size:.9rem}.spacer{flex:1}
  .wrap{max-width:1500px;margin:0 auto;padding:18px}.panel{background:rgba(15,23,42,.93);border:1px solid rgba(148,163,184,.34);border-radius:18px;padding:16px;box-shadow:0 18px 45px rgba(0,0,0,.48)}
  .btnx,.btn-small{display:inline-flex;align-items:center;justify-content:center;border:1px solid rgba(226,232,240,.55);border-radius:10px;padding:.46rem .8rem;color:#fff;text-decoration:none;background:rgba(15,23,42,.75);font-weight:850}.btnx.green,.btn-small.green{background:#22c55e;color:#052e16;border-color:#22c55e}.btn-small.warn{background:#fbbf24;color:#111827;border-color:#fbbf24}.btn-small.blue{background:#38bdf8;color:#082f49;border-color:#38bdf8}.btnx:hover,.btn-small:hover{filter:brightness(1.08);color:inherit}
  .chip{display:inline-flex;align-items:center;border-radius:999px;padding:.22rem .65rem;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.12);font-size:.76rem;font-weight:900;text-transform:uppercase;letter-spacing:.04em}.muted{color:var(--muted)}.section-title{font-size:1rem;font-weight:950;margin:0 0 10px}
  .kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.kpi{background:rgba(2,6,23,.58);border:1px solid rgba(148,163,184,.26);border-radius:14px;padding:12px}.kpi b{display:block;font-size:1.55rem;line-height:1}.kpi span{font-size:.78rem;color:#a7b1c2;text-transform:uppercase;font-weight:850}
  .form-control,.form-select{background:#07111f!important;border:1px solid rgba(148,163,184,.38)!important;color:#e5e7eb!important;border-radius:10px!important}.form-label{font-size:.76rem;font-weight:900;text-transform:uppercase;color:#cbd5e1}
  .table-wrap{overflow:auto;border:1px solid rgba(148,163,184,.28);border-radius:14px}.table{margin:0}.table thead th{background:#102345;color:#fff;border-color:#2d4d76;font-size:.78rem;text-transform:uppercase;white-space:nowrap}.table tbody td{background:rgba(226,232,240,.94);color:#172033;border-color:#cbd5e1;vertical-align:middle}.table tbody tr:nth-child(even) td{background:rgba(219,234,254,.92)}
  .badge-state{display:inline-flex;border-radius:999px;padding:.22rem .55rem;font-size:.74rem;font-weight:900;white-space:nowrap;background:#e2e8f0;color:#334155}.badge-state.elevado{background:#dbeafe;color:#1d4ed8}.badge-state.archivado{background:#dcfce7;color:#166534}.badge-state.ausente{background:#fee2e2;color:#9f1239}.badge-state.presente{background:#dcfce7;color:#166534}
  .radio-group{display:flex;gap:8px;flex-wrap:wrap}.radio-pill{display:inline-flex;align-items:center;gap:5px;padding:.28rem .55rem;border-radius:999px;background:#f8fafc;border:1px solid #cbd5e1;font-weight:850;font-size:.8rem}.obs-input{min-width:210px}
  @media print{.page-bg,.hero,.no-print,.btnx,.btn-small,.filters,.historial{display:none!important}body{background:#fff;color:#111}.wrap{padding:0;max-width:none}.panel{box-shadow:none;border:0;background:#fff;color:#111}.table tbody td,.table thead th{background:#fff!important;color:#111!important;border-color:#888!important}.form-select,.form-control{border:0!important;background:#fff!important;color:#111!important;padding:0!important}.radio-pill input:not(:checked)+span{display:none}.radio-pill{border:0;background:#fff;padding:0}.chip,.badge-state{border:1px solid #777;color:#111;background:#fff}}
  @media (max-width:980px){.kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.hero{align-items:flex-start;flex-wrap:wrap}.wrap{padding:12px}}
</style>
</head>
<body>
<div class="page-bg"></div>
<header class="hero">
  <img src="<?= e($ESCUDO) ?>" alt="Escudo">
  <div>
    <h1><?= e($UNIDAD_NOMBRE) ?> · Parte de presentes diario</h1>
    <p><?= e($UNIDAD_SUB) ?> · Carga digital por dependencia y elevación al área Personal.</p>
  </div>
  <div class="spacer"></div>
  <a class="btnx" href="informatica.php">Volver</a>
  <a class="btnx green" href="../personal/personal.php">Personal</a>
</header>

<main class="wrap">
  <?php if ($flashOk !== ''): ?><div class="alert alert-success py-2"><?= e($flashOk) ?></div><?php endif; ?>
  <?php if ($flashErr !== ''): ?><div class="alert alert-danger py-2"><?= e($flashErr) ?></div><?php endif; ?>

  <section class="panel mb-3 filters">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-md-2">
        <label class="form-label">Fecha</label>
        <input class="form-control" type="date" name="fecha" value="<?= e($fecha) ?>">
      </div>
      <div class="col-md-5">
        <label class="form-label">Dependencia</label>
        <select class="form-select" name="destino_id" <?= !$canSeeAll ? 'disabled' : '' ?>>
          <?php foreach ($destinos as $d): ?>
            <option value="<?= (int)$d['id'] ?>" <?= (int)$d['id'] === $destinoId ? 'selected' : '' ?>><?= e((string)$d['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if (!$canSeeAll): ?><input type="hidden" name="destino_id" value="<?= (int)$destinoId ?>"><?php endif; ?>
      </div>
      <div class="col-md-2 d-grid"><button class="btn-small blue" type="submit">Ver parte</button></div>
      <div class="col-md-3 d-grid"><button class="btn-small" type="button" onclick="window.print()">Imprimir / PDF</button></div>
    </form>
  </section>

  <section class="panel mb-3">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
      <span class="chip"><?= e($destinoById[$destinoId] ?? 'Dependencia') ?></span>
      <span class="muted">Fecha <?= e(date('d/m/Y', strtotime($fecha))) ?> · <?= $parte ? e(pp_estado_label((string)$parte['estado'])) : 'Sin parte guardado' ?></span>
    </div>
    <div class="kpis">
      <div class="kpi"><span>Personal listado</span><b><?= count($personal) ?></b></div>
      <div class="kpi"><span>Presentes</span><b><?= (int)$presentes ?></b></div>
      <div class="kpi"><span>Ausentes</span><b><?= (int)$ausentes ?></b></div>
      <div class="kpi"><span>Estado</span><b style="font-size:1.05rem"><?= $parte ? e(pp_estado_label((string)$parte['estado'])) : 'Nuevo' ?></b></div>
    </div>
  </section>

  <form method="post">
    <?= csrf_input() ?>
    <input type="hidden" name="fecha" value="<?= e($fecha) ?>">
    <input type="hidden" name="destino_id" value="<?= (int)$destinoId ?>">
    <section class="panel mb-3">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <h2 class="section-title mb-0">Detalle del parte</h2>
        <div class="d-flex gap-2 no-print">
          <button class="btn-small" name="accion" value="guardar" type="submit">Guardar borrador</button>
          <button class="btn-small warn" name="accion" value="elevar" type="submit">Elevar a Personal</button>
          <?php if ($canSeeAll): ?><button class="btn-small green" name="accion" value="archivar" type="submit">Archivar</button><?php endif; ?>
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label">Observaciones generales</label>
        <input class="form-control" name="observaciones" value="<?= e((string)($parte['observaciones'] ?? '')) ?>" placeholder="Novedades generales de la dependencia">
      </div>
      <div class="table-wrap">
        <table class="table table-sm">
          <thead><tr><th>Personal</th><th>DNI</th><th>Situación</th><th>Causa</th><th>Observaciones</th></tr></thead>
          <tbody>
          <?php if (!$personal): ?>
            <tr><td colspan="5" class="text-center py-4">No hay personal asignado a esta dependencia. Revisá Personal / destinos internos.</td></tr>
          <?php endif; ?>
          <?php foreach ($personal as $p):
              $pid = (int)$p['id'];
              $det = $detalleByPersonal[$pid] ?? [];
              $situacion = (string)($det['situacion'] ?? 'presente');
              $causa = (string)($det['causa'] ?? '');
              if ((int)($p['tiene_parte_enfermo'] ?? 0) === 1 && !$det) { $situacion = 'ausente'; $causa = 'parte_enfermo'; }
              $nombre = trim((string)($p['apellido_nombre'] ?: trim((string)$p['apellido'] . ' ' . (string)$p['nombre'])));
          ?>
            <tr>
              <td>
                <input type="hidden" name="personal_id[]" value="<?= $pid ?>">
                <strong><?= e(trim((string)$p['grado'] . ' ' . $nombre)) ?></strong>
              </td>
              <td><?= e((string)$p['dni']) ?></td>
              <td>
                <div class="radio-group">
                  <label class="radio-pill"><input type="radio" name="situacion[<?= $pid ?>]" value="presente" <?= $situacion !== 'ausente' ? 'checked' : '' ?>> <span>Presente</span></label>
                  <label class="radio-pill"><input type="radio" name="situacion[<?= $pid ?>]" value="ausente" <?= $situacion === 'ausente' ? 'checked' : '' ?>> <span>Ausente</span></label>
                </div>
              </td>
              <td>
                <select class="form-select form-select-sm" name="causa[<?= $pid ?>]">
                  <?php foreach ($allowedCausas as $c): ?>
                    <option value="<?= e($c) ?>" <?= $c === $causa ? 'selected' : '' ?>><?= e($c === '' ? 'Sin causa' : pp_causa_label($c)) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td><input class="form-control form-control-sm obs-input" name="obs[<?= $pid ?>]" value="<?= e((string)($det['observaciones'] ?? '')) ?>" placeholder="Detalle"></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  </form>

  <section class="panel historial">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
      <h2 class="section-title mb-0">Archivo de partes</h2>
      <span class="muted"><?= count($historial) ?> registros recientes</span>
    </div>
    <div class="table-wrap">
      <table class="table table-sm">
        <thead><tr><th>Fecha</th><th>Dependencia</th><th>Estado</th><th>Presentes</th><th>Ausentes</th><th>Acción</th></tr></thead>
        <tbody>
        <?php if (!$historial): ?><tr><td colspan="6" class="text-center py-4">Todavía no hay partes archivados.</td></tr><?php endif; ?>
        <?php foreach ($historial as $h): ?>
          <tr>
            <td><?= e(date('d/m/Y', strtotime((string)$h['fecha']))) ?></td>
            <td><strong><?= e((string)$h['destino_nombre']) ?></strong></td>
            <td><span class="badge-state <?= e((string)$h['estado']) ?>"><?= e(pp_estado_label((string)$h['estado'])) ?></span></td>
            <td><?= (int)$h['presentes'] ?></td>
            <td><?= (int)$h['ausentes'] ?></td>
            <td><a class="btn-small" href="?fecha=<?= e((string)$h['fecha']) ?>&destino_id=<?= (int)$h['destino_id'] ?>">Abrir</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>
</body>
</html>
