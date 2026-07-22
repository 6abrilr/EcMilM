<?php
declare(strict_types=1);

require_once __DIR__ . '/../../auth/bootstrap.php';
require_login();
require_once __DIR__ . '/../../config/db.php';

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function san_norm_dni(string $dni): string {
    return preg_replace('/\D+/', '', $dni);
}

function san_str(string $key, int $max = 4000): string {
    $value = trim((string)($_POST[$key] ?? ''));
    return mb_strlen($value, 'UTF-8') > $max ? mb_substr($value, 0, $max, 'UTF-8') : $value;
}

function san_date(?string $value): ?string {
    $value = trim((string)$value);
    if ($value === '') return null;
    $dt = DateTime::createFromFormat('Y-m-d', $value);
    return ($dt && $dt->format('Y-m-d') === $value) ? $value : null;
}

function san_fmt_date($value): string {
    $value = trim((string)$value);
    if ($value === '') return '-';
    $ts = strtotime($value);
    return $ts ? date('d/m/Y', $ts) : $value;
}

function san_estado_label(string $estado): string {
    return match ($estado) {
        'cerrado' => 'Cerrado',
        'derivado' => 'Derivado',
        default => 'Activo',
    };
}

function san_tipo_label(string $tipo): string {
    return match ($tipo) {
        'parte_enfermo' => 'Parte enfermo',
        'alta_medica' => 'Alta médica',
        'situacion_especial' => 'Situación especial',
        'junta_medica' => 'Junta médica',
        'control' => 'Control sanitario',
        'derivacion' => 'Derivación',
        default => 'Otro',
    };
}

function san_badge(string $value): string {
    return match ($value) {
        'urgente', 'parte_enfermo' => 'hot',
        'alta_medica', 'cerrado' => 'ok',
        'junta_medica', 'derivado' => 'blue',
        'situacion_especial' => 'amber',
        default => 'soft',
    };
}

function san_ensure_tables(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sanidad_novedades (
            id INT AUTO_INCREMENT PRIMARY KEY,
            unidad_id INT NOT NULL,
            personal_id INT NOT NULL,
            tipo ENUM('situacion_especial','junta_medica','control','derivacion','otro') NOT NULL DEFAULT 'situacion_especial',
            prioridad ENUM('normal','alta','urgente') NOT NULL DEFAULT 'normal',
            estado ENUM('activo','cerrado','derivado') NOT NULL DEFAULT 'activo',
            titulo VARCHAR(180) NOT NULL,
            detalle TEXT NULL,
            fecha_inicio DATE NULL,
            fecha_fin DATE NULL,
            proxima_accion VARCHAR(220) NULL,
            creado_por VARCHAR(180) NULL,
            creado_dni VARCHAR(20) NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_san_nov_unidad_estado (unidad_id, estado),
            INDEX idx_san_nov_personal (personal_id),
            INDEX idx_san_nov_tipo (tipo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

san_ensure_tables($pdo);

$ROOT_FS = realpath(__DIR__ . '/../..') ?: dirname(__DIR__, 2);

$user = function_exists('current_user') ? current_user() : ($_SESSION['user'] ?? []);
$displayUser = trim((string)($user['nombre_completo'] ?? $user['display_name'] ?? $user['full_name'] ?? $user['username'] ?? 'Usuario'));
$dniNorm = san_norm_dni((string)($user['dni'] ?? $user['username'] ?? ''));
$unidadId = function_exists('unidad_activa_id') ? unidad_activa_id() : (int)($user['unidad_id'] ?? 1);

$SELF_WEB = (string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
$BASE_DIR_WEB = rtrim(str_replace('\\', '/', dirname($SELF_WEB)), '/');
$BASE_PUBLIC_WEB = rtrim(str_replace('\\', '/', dirname($BASE_DIR_WEB)), '/');
$BASE_APP_WEB = rtrim(str_replace('\\', '/', dirname($BASE_PUBLIC_WEB)), '/');
$ASSET_WEB = $BASE_APP_WEB . '/assets';

$IMG_BG = $ASSET_WEB . '/img/fondo.png';
$ESCUDO = $ASSET_WEB . '/img/ecmilm.png';
$FAVICON = $ESCUDO;
$UNIDAD_NOMBRE = 'Unidad';
if (function_exists('unidad_context')) {
    $ctx = unidad_context($pdo, $unidadId);
    $IMG_BG = (string)$ctx['bg_url'];
    $ESCUDO = (string)$ctx['escudo_url'];
    $FAVICON = (string)$ctx['icon_url'];
    $UNIDAD_NOMBRE = (string)$ctx['nombre_completo'];
}

$personalIdActual = 0;
try {
    if ($dniNorm !== '') {
        $st = $pdo->prepare("
            SELECT id
            FROM personal_unidad
            WHERE unidad_id = :uid
              AND REPLACE(REPLACE(REPLACE(dni,'.',''),'-',''),' ','') = :dni
            LIMIT 1
        ");
        $st->execute([':uid' => $unidadId, ':dni' => $dniNorm]);
        $personalIdActual = (int)($st->fetchColumn() ?: 0);
    }
} catch (Throwable $e) {
    $personalIdActual = 0;
}

$flashOk = '';
$flashErr = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $accion = (string)($_POST['accion'] ?? '');

    try {
        if ($accion === 'registrar') {
            $personalId = (int)($_POST['personal_id'] ?? 0);
            $tipo = (string)($_POST['tipo'] ?? '');
            $prioridad = in_array((string)($_POST['prioridad'] ?? ''), ['normal', 'alta', 'urgente'], true) ? (string)$_POST['prioridad'] : 'normal';
            $titulo = san_str('titulo', 180);
            $detalle = san_str('detalle', 5000);
            $fechaInicio = san_date((string)($_POST['fecha_inicio'] ?? ''));
            $fechaFin = san_date((string)($_POST['fecha_fin'] ?? ''));
            $proximaAccion = san_str('proxima_accion', 220);

            if ($personalId <= 0) throw new RuntimeException('Seleccioná una persona.');
            $st = $pdo->prepare("SELECT COUNT(*) FROM personal_unidad WHERE id = :pid AND unidad_id = :uid");
            $st->execute([':pid' => $personalId, ':uid' => $unidadId]);
            if ((int)$st->fetchColumn() <= 0) throw new RuntimeException('La persona seleccionada no pertenece a esta unidad.');

            if ($tipo === 'parte_enfermo') {
                $fechaInicio = $fechaInicio ?: date('Y-m-d');
                $st = $pdo->prepare("
                    INSERT INTO sanidad_partes_enfermo
                        (unidad_id, personal_id, tiene_parte, evento, inicio, fin, cantidad, observaciones, created_by_id, updated_by_id)
                    VALUES
                        (:uid, :pid, 'si', 'parte', :inicio, :fin, 1, :obs, :by, :by)
                ");
                $st->execute([
                    ':uid' => $unidadId,
                    ':pid' => $personalId,
                    ':inicio' => $fechaInicio,
                    ':fin' => $fechaFin,
                    ':obs' => $detalle,
                    ':by' => $personalIdActual ?: null,
                ]);
                $up = $pdo->prepare("
                    UPDATE personal_unidad
                    SET tiene_parte_enfermo = 1,
                        parte_enfermo_desde = COALESCE(:inicio, parte_enfermo_desde, CURDATE()),
                        parte_enfermo_hasta = :fin,
                        cantidad_parte_enfermo = COALESCE(cantidad_parte_enfermo, 0) + 1,
                        updated_at = NOW(),
                        updated_by_id = :by
                    WHERE id = :pid AND unidad_id = :uid
                ");
                $up->execute([':inicio' => $fechaInicio, ':fin' => $fechaFin, ':by' => $personalIdActual ?: null, ':pid' => $personalId, ':uid' => $unidadId]);
                $flashOk = 'Parte enfermo registrado y sincronizado con Personal.';
            } elseif ($tipo === 'alta_medica') {
                $fechaFin = $fechaFin ?: date('Y-m-d');
                $st = $pdo->prepare("
                    INSERT INTO sanidad_partes_enfermo
                        (unidad_id, personal_id, tiene_parte, evento, inicio, fin, cantidad, observaciones, created_by_id, updated_by_id)
                    VALUES
                        (:uid, :pid, 'no', 'alta', :inicio, :fin, 0, :obs, :by, :by)
                ");
                $st->execute([
                    ':uid' => $unidadId,
                    ':pid' => $personalId,
                    ':inicio' => $fechaInicio,
                    ':fin' => $fechaFin,
                    ':obs' => $detalle,
                    ':by' => $personalIdActual ?: null,
                ]);
                $up = $pdo->prepare("
                    UPDATE personal_unidad
                    SET tiene_parte_enfermo = 0,
                        parte_enfermo_hasta = :fin,
                        updated_at = NOW(),
                        updated_by_id = :by
                    WHERE id = :pid AND unidad_id = :uid
                ");
                $up->execute([':fin' => $fechaFin, ':by' => $personalIdActual ?: null, ':pid' => $personalId, ':uid' => $unidadId]);
                $flashOk = 'Alta médica registrada y sincronizada con Personal.';
            } else {
                if (!in_array($tipo, ['situacion_especial', 'junta_medica', 'control', 'derivacion', 'otro'], true)) {
                    $tipo = 'situacion_especial';
                }
                if ($titulo === '') $titulo = san_tipo_label($tipo);
                $st = $pdo->prepare("
                    INSERT INTO sanidad_novedades
                        (unidad_id, personal_id, tipo, prioridad, estado, titulo, detalle, fecha_inicio, fecha_fin, proxima_accion, creado_por, creado_dni)
                    VALUES
                        (:uid, :pid, :tipo, :prioridad, 'activo', :titulo, :detalle, :inicio, :fin, :prox, :creado, :dni)
                ");
                $st->execute([
                    ':uid' => $unidadId,
                    ':pid' => $personalId,
                    ':tipo' => $tipo,
                    ':prioridad' => $prioridad,
                    ':titulo' => $titulo,
                    ':detalle' => $detalle,
                    ':inicio' => $fechaInicio,
                    ':fin' => $fechaFin,
                    ':prox' => $proximaAccion !== '' ? $proximaAccion : null,
                    ':creado' => $displayUser,
                    ':dni' => $dniNorm,
                ]);
                $flashOk = 'Novedad sanitaria registrada.';
            }
        } elseif ($accion === 'estado_novedad') {
            $id = (int)($_POST['id'] ?? 0);
            $estado = in_array((string)($_POST['estado'] ?? ''), ['activo', 'cerrado', 'derivado'], true) ? (string)$_POST['estado'] : 'activo';
            $st = $pdo->prepare("UPDATE sanidad_novedades SET estado = :estado WHERE id = :id AND unidad_id = :uid LIMIT 1");
            $st->execute([':estado' => $estado, ':id' => $id, ':uid' => $unidadId]);
            $flashOk = 'Estado de novedad actualizado.';
        }
    } catch (Throwable $ex) {
        $flashErr = $ex->getMessage();
    }
}

$q = trim((string)($_GET['q'] ?? ''));
$filtroTipo = (string)($_GET['tipo'] ?? '');
$filtroEstado = (string)($_GET['estado'] ?? 'activo');

$personalSql = "
    SELECT id, dni, grado, arma, apellido_nombre, destino_interno, funcion,
           tiene_parte_enfermo, parte_enfermo_desde, parte_enfermo_hasta, cantidad_parte_enfermo
    FROM personal_unidad
    WHERE unidad_id = :uid
";
$personalParams = [':uid' => $unidadId];
if ($q !== '') {
    $personalSql .= " AND (apellido_nombre LIKE :q OR dni LIKE :q OR grado LIKE :q OR arma LIKE :q)";
    $personalParams[':q'] = '%' . $q . '%';
}
$personalSql .= " ORDER BY tiene_parte_enfermo DESC, apellido_nombre ASC LIMIT 350";
$st = $pdo->prepare($personalSql);
$st->execute($personalParams);
$personal = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

$st = $pdo->prepare("
    SELECT COUNT(*) AS total,
           SUM(tiene_parte_enfermo = 1) AS con_parte,
           SUM(fecha_ultimo_anexo27 IS NOT NULL) AS anexo27
    FROM personal_unidad
    WHERE unidad_id = :uid
");
$st->execute([':uid' => $unidadId]);
$kpiP = $st->fetch(PDO::FETCH_ASSOC) ?: [];

$st = $pdo->prepare("
    SELECT COUNT(*) AS total,
           SUM(tipo = 'junta_medica' AND estado = 'activo') AS juntas,
           SUM(tipo = 'situacion_especial' AND estado = 'activo') AS especiales,
           SUM(prioridad = 'urgente' AND estado = 'activo') AS urgentes
    FROM sanidad_novedades
    WHERE unidad_id = :uid
");
$st->execute([':uid' => $unidadId]);
$kpiN = $st->fetch(PDO::FETCH_ASSOC) ?: [];

$where = ['n.unidad_id = :uid'];
$params = [':uid' => $unidadId];
if (in_array($filtroTipo, ['situacion_especial', 'junta_medica', 'control', 'derivacion', 'otro'], true)) {
    $where[] = 'n.tipo = :tipo';
    $params[':tipo'] = $filtroTipo;
}
if (in_array($filtroEstado, ['activo', 'cerrado', 'derivado'], true)) {
    $where[] = 'n.estado = :estado';
    $params[':estado'] = $filtroEstado;
}
$st = $pdo->prepare("
    SELECT n.*, pu.grado, pu.arma, pu.apellido_nombre, pu.dni
    FROM sanidad_novedades n
    INNER JOIN personal_unidad pu ON pu.id = n.personal_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY CASE n.prioridad WHEN 'urgente' THEN 1 WHEN 'alta' THEN 2 ELSE 3 END,
             COALESCE(n.fecha_fin, '2999-12-31') ASC,
             n.id DESC
    LIMIT 160
");
$st->execute($params);
$novedades = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

$st = $pdo->prepare("
    SELECT s.*, pu.grado, pu.arma, pu.apellido_nombre, pu.dni
    FROM sanidad_partes_enfermo s
    INNER JOIN personal_unidad pu ON pu.id = s.personal_id
    WHERE s.unidad_id = :uid
    ORDER BY s.created_at DESC, s.id DESC
    LIMIT 12
");
$st->execute([':uid' => $unidadId]);
$histPartes = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Sanidad · <?= e($UNIDAD_NOMBRE) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="icon" href="<?= e($FAVICON) ?>">
<style>
  :root{--bg:#020617;--panel:#0f172a;--line:#334155;--text:#e5e7eb;--muted:#94a3b8;--red:#ef4444;--green:#22c55e;--blue:#38bdf8;--amber:#f59e0b;}
  *{box-sizing:border-box}
  body{margin:0;min-height:100vh;background:#020617;color:var(--text);font-family:system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,sans-serif}
  body::before{content:"";position:fixed;inset:0;z-index:-2;background:linear-gradient(160deg,rgba(2,6,23,.94),rgba(15,23,42,.90)),url("<?= e($IMG_BG) ?>") center 20px / min(420px,45vw) auto no-repeat;opacity:.78}
  .wrap{max-width:1440px;margin:0 auto;padding:20px}
  .hero{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;padding:8px 0 18px}
  .brand{display:flex;align-items:center;gap:13px}
  .brand img{height:54px;width:auto;filter:drop-shadow(0 10px 24px rgba(0,0,0,.55))}
  .kicker{font-size:.76rem;text-transform:uppercase;letter-spacing:.16em;color:#fecaca;font-weight:950}
  h1{font-size:1.55rem;margin:2px 0;font-weight:950}
  .sub{color:#cbd5e1;font-size:.9rem}
  .actions{display:flex;gap:8px;flex-wrap:wrap}
  .btnx{display:inline-flex;align-items:center;gap:8px;border:1px solid rgba(148,163,184,.42);border-radius:10px;background:rgba(15,23,42,.82);color:#e5e7eb;text-decoration:none;font-weight:850;padding:.52rem .78rem}
  .btnx:hover{background:#1e293b;color:#fff}
  .stats{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px;margin-bottom:16px}
  .stat{background:rgba(15,23,42,.84);border:1px solid rgba(148,163,184,.28);border-radius:12px;padding:12px}
  .stat span{display:block;color:var(--muted);font-size:.72rem;text-transform:uppercase;letter-spacing:.09em;font-weight:900}
  .stat b{display:block;font-size:1.35rem;color:#fff;margin-top:2px}
  .layout{display:grid;grid-template-columns:420px 1fr;gap:16px;align-items:start}
  .panel{background:rgba(15,23,42,.88);border:1px solid rgba(148,163,184,.32);border-radius:14px;box-shadow:0 22px 52px rgba(0,0,0,.38);overflow:hidden}
  .panel-head{padding:14px 16px;border-bottom:1px solid rgba(148,163,184,.22);display:flex;justify-content:space-between;gap:12px;align-items:center}
  .panel-title{font-size:.9rem;text-transform:uppercase;letter-spacing:.11em;font-weight:950;color:#fee2e2}
  .panel-pad{padding:16px}
  label{font-size:.76rem;color:#cbd5e1;text-transform:uppercase;letter-spacing:.08em;font-weight:900;margin-bottom:6px}
  .form-control,.form-select{background:#020617;border:1px solid #334155;color:#e5e7eb;border-radius:10px}
  .form-control:focus,.form-select:focus{background:#020617;color:#fff;border-color:#ef4444;box-shadow:0 0 0 .2rem rgba(239,68,68,.15)}
  .form-control::placeholder{color:#64748b}
  .submit{width:100%;border:0;border-radius:10px;background:linear-gradient(180deg,#dc2626,#991b1b);color:#fff;font-weight:950;padding:.72rem 1rem}
  .tabs{display:flex;gap:8px;flex-wrap:wrap;padding:12px 14px;border-bottom:1px solid rgba(148,163,184,.2)}
  .pilltab{border:1px solid rgba(148,163,184,.3);background:rgba(2,6,23,.45);border-radius:999px;color:#e5e7eb;text-decoration:none;font-weight:850;padding:.35rem .72rem;font-size:.82rem}
  .pilltab.active{background:rgba(239,68,68,.16);border-color:rgba(239,68,68,.52);color:#fecaca}
  .list{display:grid;gap:10px;padding:14px}
  .cardx{border:1px solid rgba(148,163,184,.24);border-radius:13px;background:rgba(2,6,23,.52);padding:13px}
  .cardx h2{font-size:1rem;margin:7px 0 6px;font-weight:950;color:#fff}
  .cardx p{white-space:pre-wrap;margin:0;color:#d1d5db;line-height:1.45}
  .badges{display:flex;gap:6px;flex-wrap:wrap}
  .badge2{display:inline-flex;align-items:center;border-radius:999px;padding:.17rem .52rem;font-size:.72rem;font-weight:950;border:1px solid rgba(148,163,184,.3);background:rgba(15,23,42,.9);color:#e5e7eb}
  .badge2.hot{border-color:rgba(239,68,68,.55);background:rgba(239,68,68,.15);color:#fecaca}
  .badge2.ok{border-color:rgba(34,197,94,.5);background:rgba(34,197,94,.14);color:#bbf7d0}
  .badge2.blue{border-color:rgba(56,189,248,.5);background:rgba(56,189,248,.14);color:#bae6fd}
  .badge2.amber{border-color:rgba(245,158,11,.55);background:rgba(245,158,11,.14);color:#fde68a}
  .meta{display:flex;gap:10px;flex-wrap:wrap;color:#94a3b8;font-size:.78rem;margin-top:10px}
  .table-wrap{overflow:auto}
  table{width:100%;border-collapse:collapse;min-width:850px}
  th,td{padding:.72rem .78rem;border-bottom:1px solid rgba(148,163,184,.16);vertical-align:middle}
  th{background:rgba(30,41,59,.84);font-size:.74rem;text-transform:uppercase;letter-spacing:.08em;color:#fecaca}
  tr:hover td{background:rgba(239,68,68,.07)}
  .name{font-weight:900;color:#fff}
  .smallmuted{font-size:.78rem;color:#94a3b8}
  .empty{padding:34px 14px;text-align:center;color:#cbd5e1}
  .mission{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
  .mission .cardx{background:rgba(15,23,42,.62)}
  .state-form{display:flex;gap:7px;align-items:center;margin-top:12px}
  .state-form select{max-width:140px}
  .mini-btn{border:1px solid rgba(148,163,184,.4);background:rgba(15,23,42,.8);color:#e5e7eb;border-radius:9px;font-weight:850;padding:.35rem .58rem}
  @media(max-width:1050px){.layout{grid-template-columns:1fr}.stats{grid-template-columns:repeat(2,minmax(0,1fr))}.mission{grid-template-columns:1fr}}
  @media(max-width:620px){.wrap{padding:14px}.stats{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="wrap">
  <header class="hero">
    <div class="brand">
      <img src="<?= e($ESCUDO) ?>" alt="Escudo" onerror="this.style.display='none'">
      <div>
        <div class="kicker">Sanidad · Personal</div>
        <h1>Sanidad</h1>
        <div class="sub"><?= e($UNIDAD_NOMBRE) ?> · Partes enfermos, altas, situaciones especiales y juntas médicas</div>
      </div>
    </div>
    <div class="actions">
      <a class="btnx" href="../personal/personal_lista.php"><i class="bi bi-people"></i> Personal</a>
      <a class="btnx" href="../inicio.php"><i class="bi bi-house"></i> Inicio</a>
    </div>
  </header>

  <?php if ($flashOk !== ''): ?><div class="alert alert-success"><?= e($flashOk) ?></div><?php endif; ?>
  <?php if ($flashErr !== ''): ?><div class="alert alert-danger"><?= e($flashErr) ?></div><?php endif; ?>

  <section class="stats">
    <div class="stat"><span>Personal</span><b><?= (int)($kpiP['total'] ?? 0) ?></b></div>
    <div class="stat"><span>Con parte</span><b><?= (int)($kpiP['con_parte'] ?? 0) ?></b></div>
    <div class="stat"><span>Juntas activas</span><b><?= (int)($kpiN['juntas'] ?? 0) ?></b></div>
    <div class="stat"><span>Situaciones especiales</span><b><?= (int)($kpiN['especiales'] ?? 0) ?></b></div>
    <div class="stat"><span>Urgentes</span><b><?= (int)($kpiN['urgentes'] ?? 0) ?></b></div>
  </section>

  <div class="layout">
    <aside class="panel">
      <div class="panel-head"><div class="panel-title">Registrar novedad</div></div>
      <form class="panel-pad" method="post" autocomplete="off">
        <?= csrf_input() ?>
        <input type="hidden" name="accion" value="registrar">

        <div>
          <label for="personal_id">Personal</label>
          <select class="form-select" id="personal_id" name="personal_id" required>
            <option value="">Seleccionar...</option>
            <?php foreach ($personal as $p): ?>
              <option value="<?= (int)$p['id'] ?>">
                <?= e(trim((string)($p['grado'] ?? '') . ' ' . (string)($p['arma'] ?? '') . ' ' . (string)($p['apellido_nombre'] ?? ''))) ?> · DNI <?= e($p['dni'] ?? '') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="row g-2 mt-1">
          <div class="col-7">
            <label for="tipo">Tipo</label>
            <select class="form-select" id="tipo" name="tipo">
              <option value="parte_enfermo">Parte enfermo</option>
              <option value="alta_medica">Alta médica</option>
              <option value="situacion_especial">Situación especial</option>
              <option value="junta_medica">Junta médica</option>
              <option value="control">Control sanitario</option>
              <option value="derivacion">Derivación</option>
              <option value="otro">Otro</option>
            </select>
          </div>
          <div class="col-5">
            <label for="prioridad">Prioridad</label>
            <select class="form-select" id="prioridad" name="prioridad">
              <option value="normal">Normal</option>
              <option value="alta">Alta</option>
              <option value="urgente">Urgente</option>
            </select>
          </div>
          <div class="col-6">
            <label for="fecha_inicio">Inicio</label>
            <input class="form-control" type="date" id="fecha_inicio" name="fecha_inicio">
          </div>
          <div class="col-6">
            <label for="fecha_fin">Fin / vencimiento</label>
            <input class="form-control" type="date" id="fecha_fin" name="fecha_fin">
          </div>
        </div>

        <div class="mt-3">
          <label for="titulo">Título</label>
          <input class="form-control" id="titulo" name="titulo" maxlength="180" placeholder="Ej: Junta médica, control, situación especial...">
        </div>
        <div class="mt-3">
          <label for="detalle">Detalle</label>
          <textarea class="form-control" id="detalle" name="detalle" rows="6" maxlength="5000" placeholder="Diagnóstico, restricción, indicación, documento, autoridad interviniente..."></textarea>
        </div>
        <div class="mt-3">
          <label for="proxima_accion">Próxima acción</label>
          <input class="form-control" id="proxima_accion" name="proxima_accion" maxlength="220" placeholder="Ej: elevar informe, citar a control, esperar junta...">
        </div>
        <button class="submit mt-3" type="submit"><i class="bi bi-heart-pulse-fill"></i> Guardar en Sanidad</button>
      </form>
    </aside>

    <main class="panel">
      <div class="tabs">
        <a class="pilltab <?= $filtroTipo === '' ? 'active' : '' ?>" href="?">Novedades</a>
        <a class="pilltab <?= $filtroTipo === 'junta_medica' ? 'active' : '' ?>" href="?tipo=junta_medica">Juntas médicas</a>
        <a class="pilltab <?= $filtroTipo === 'situacion_especial' ? 'active' : '' ?>" href="?tipo=situacion_especial">Situaciones especiales</a>
        <a class="pilltab" href="#partes">Partes recientes</a>
      </div>

      <div class="panel-pad">
        <form class="row g-2 mb-3" method="get">
          <div class="col-md-5">
            <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Buscar personal por apellido, DNI, grado...">
          </div>
          <div class="col-md-3">
            <select class="form-select" name="estado">
              <option value="activo" <?= $filtroEstado === 'activo' ? 'selected' : '' ?>>Activas</option>
              <option value="cerrado" <?= $filtroEstado === 'cerrado' ? 'selected' : '' ?>>Cerradas</option>
              <option value="derivado" <?= $filtroEstado === 'derivado' ? 'selected' : '' ?>>Derivadas</option>
              <option value="" <?= $filtroEstado === '' ? 'selected' : '' ?>>Todas</option>
            </select>
          </div>
          <div class="col-md-2">
            <button class="btnx w-100 justify-content-center" type="submit"><i class="bi bi-search"></i> Filtrar</button>
          </div>
          <div class="col-md-2">
            <a class="btnx w-100 justify-content-center" href="sanidad.php"><i class="bi bi-x-lg"></i> Limpiar</a>
          </div>
        </form>
      </div>

      <?php if (!$novedades): ?>
        <div class="empty">No hay novedades sanitarias para el filtro seleccionado.</div>
      <?php else: ?>
        <div class="list">
          <?php foreach ($novedades as $n): ?>
            <?php
              $tipo = (string)$n['tipo'];
              $estado = (string)$n['estado'];
              $prioridad = (string)$n['prioridad'];
              $nombre = trim((string)($n['grado'] ?? '') . ' ' . (string)($n['arma'] ?? '') . ' ' . (string)($n['apellido_nombre'] ?? ''));
            ?>
            <article class="cardx">
              <div class="badges">
                <span class="badge2 <?= e(san_badge($tipo)) ?>"><?= e(san_tipo_label($tipo)) ?></span>
                <span class="badge2 <?= e(san_badge($prioridad)) ?>"><?= e(ucfirst($prioridad)) ?></span>
                <span class="badge2 <?= e(san_badge($estado)) ?>"><?= e(san_estado_label($estado)) ?></span>
              </div>
              <h2><?= e($n['titulo'] ?? san_tipo_label($tipo)) ?></h2>
              <p><?= e($n['detalle'] ?? '') ?></p>
              <div class="meta">
                <span><i class="bi bi-person"></i> <?= e($nombre) ?></span>
                <span><i class="bi bi-credit-card"></i> DNI <?= e($n['dni'] ?? '') ?></span>
                <span><i class="bi bi-calendar-event"></i> <?= e(san_fmt_date($n['fecha_inicio'] ?? '')) ?> - <?= e(san_fmt_date($n['fecha_fin'] ?? '')) ?></span>
                <?php if (!empty($n['proxima_accion'])): ?><span><i class="bi bi-arrow-right-circle"></i> <?= e($n['proxima_accion']) ?></span><?php endif; ?>
              </div>
              <form class="state-form" method="post">
                <?= csrf_input() ?>
                <input type="hidden" name="accion" value="estado_novedad">
                <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
                <select class="form-select form-select-sm" name="estado">
                  <option value="activo" <?= $estado === 'activo' ? 'selected' : '' ?>>Activo</option>
                  <option value="cerrado" <?= $estado === 'cerrado' ? 'selected' : '' ?>>Cerrado</option>
                  <option value="derivado" <?= $estado === 'derivado' ? 'selected' : '' ?>>Derivado</option>
                </select>
                <button class="mini-btn" type="submit"><i class="bi bi-check2-circle"></i> Guardar</button>
              </form>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div id="partes" class="panel-head mt-2">
        <div class="panel-title">Partes enfermos y altas recientes</div>
      </div>
      <?php if (!$histPartes): ?>
        <div class="empty">Sin partes enfermos cargados.</div>
      <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Personal</th><th>Evento</th><th>Inicio</th><th>Fin</th><th>Observaciones</th><th>Ficha</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($histPartes as $s): ?>
                <?php
                  $ev = (string)($s['evento'] ?? (((string)($s['tiene_parte'] ?? 'no') === 'si') ? 'parte' : 'alta'));
                  $nombre = trim((string)($s['grado'] ?? '') . ' ' . (string)($s['arma'] ?? '') . ' ' . (string)($s['apellido_nombre'] ?? ''));
                ?>
                <tr>
                  <td><div class="name"><?= e($nombre) ?></div><div class="smallmuted">DNI <?= e($s['dni'] ?? '') ?></div></td>
                  <td><span class="badge2 <?= $ev === 'parte' ? 'hot' : 'ok' ?>"><?= $ev === 'parte' ? 'Parte' : 'Alta' ?></span></td>
                  <td><?= e(san_fmt_date($s['inicio'] ?? '')) ?></td>
                  <td><?= e(san_fmt_date($s['fin'] ?? '')) ?></td>
                  <td><?= e($s['observaciones'] ?? '') ?></td>
                  <td><a class="btnx" href="../personal/personal_ficha.php?id=<?= (int)$s['personal_id'] ?>&tab=sanidad">Abrir</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </main>
  </div>
</div>
</body>
</html>
