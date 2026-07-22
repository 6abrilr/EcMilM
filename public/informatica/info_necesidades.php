<?php
declare(strict_types=1);

require_once __DIR__ . '/../../auth/bootstrap.php';
require_login();
require_once __DIR__ . '/../../config/db.php';

function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function norm_dni_nec(string $dni): string { return preg_replace('/\D+/', '', $dni); }
function post_text_nec(string $key, int $max = 5000): string {
    $value = trim((string)($_POST[$key] ?? ''));
    return mb_strlen($value, 'UTF-8') > $max ? mb_substr($value, 0, $max, 'UTF-8') : $value;
}
function valid_date_nec(string $value): ?string {
    $value = trim($value);
    if ($value === '') return null;
    $dt = DateTime::createFromFormat('Y-m-d', $value);
    return ($dt && $dt->format('Y-m-d') === $value) ? $value : null;
}
function option_nec(string $value, array $allowed, string $default): string {
    return in_array($value, $allowed, true) ? $value : $default;
}
function label_nec(string $value): string {
    return match ($value) {
        'equipo' => 'Equipo',
        'repuesto' => 'Repuesto',
        'servicio' => 'Servicio',
        'conectividad' => 'Conectividad',
        'software' => 'Software',
        'mantenimiento' => 'Mantenimiento',
        'urgente' => 'Urgente',
        'alta' => 'Alta',
        'media' => 'Media',
        'baja' => 'Baja',
        'solicitada' => 'Solicitada',
        'evaluacion' => 'En evaluación',
        'aprobada' => 'Aprobada',
        'en_proceso' => 'En proceso',
        'resuelta' => 'Resuelta',
        'rechazada' => 'Rechazada',
        default => 'Otro',
    };
}
function badge_nec(string $value): string {
    return match ($value) {
        'urgente', 'alta', 'rechazada' => 'badge-hot',
        'resuelta', 'aprobada' => 'badge-ok',
        'en_proceso', 'evaluacion' => 'badge-work',
        default => 'badge-soft',
    };
}

function ensure_necesidades_tables(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS it_necesidades_area (
            id INT AUTO_INCREMENT PRIMARY KEY,
            unidad_id INT NOT NULL DEFAULT 1,
            area_id INT NULL,
            activo_id INT NULL,
            tipo ENUM('equipo','repuesto','servicio','conectividad','software','mantenimiento','otro') NOT NULL DEFAULT 'equipo',
            prioridad ENUM('baja','media','alta','urgente') NOT NULL DEFAULT 'media',
            estado ENUM('solicitada','evaluacion','aprobada','en_proceso','resuelta','rechazada') NOT NULL DEFAULT 'solicitada',
            titulo VARCHAR(180) NOT NULL,
            detalle TEXT NOT NULL,
            justificacion TEXT NULL,
            cantidad INT NOT NULL DEFAULT 1,
            fecha_necesidad DATE NULL,
            solicitante_nombre VARCHAR(180) NULL,
            solicitante_dni VARCHAR(20) NULL,
            responsable VARCHAR(160) NULL,
            respuesta TEXT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_nec_unidad_area (unidad_id, area_id),
            INDEX idx_nec_estado_prioridad (estado, prioridad),
            INDEX idx_nec_activo (activo_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function current_role_nec(PDO $pdo, array $user, int $unidadId): string {
    $role = strtoupper(trim((string)($user['rol_app'] ?? $user['role_app'] ?? 'USUARIO')));
    $dni = norm_dni_nec((string)($user['dni'] ?? $user['username'] ?? ''));
    if ($dni !== '') {
        try {
            $st = $pdo->prepare("
                SELECT r.codigo
                FROM personal_unidad pu
                LEFT JOIN roles r ON r.id = pu.role_id
                WHERE pu.unidad_id = :uid
                  AND REPLACE(REPLACE(REPLACE(pu.dni,'.',''),'-',''),' ','') = :dni
                ORDER BY pu.id DESC
                LIMIT 1
            ");
            $st->execute([':uid' => $unidadId, ':dni' => $dni]);
            $dbRole = strtoupper(trim((string)$st->fetchColumn()));
            if ($dbRole !== '') $role = $dbRole;
        } catch (Throwable $ignored) {}
    }
    if ($dni === '41742406' || strtolower(trim((string)($user['username'] ?? ''))) === 'nesrojas') return 'SUPERADMIN';
    return $role !== '' ? $role : 'USUARIO';
}

ensure_necesidades_tables($pdo);

$user = function_exists('current_user') ? current_user() : ($_SESSION['user'] ?? []);
$unidadId = function_exists('unidad_activa_id') ? unidad_activa_id() : (int)($user['unidad_id'] ?? 1);
$displayUser = trim((string)($user['nombre_completo'] ?? $user['display_name'] ?? $user['full_name'] ?? $user['username'] ?? 'Usuario'));
$dniUser = norm_dni_nec((string)($user['dni'] ?? $user['username'] ?? ''));
$roleCode = current_role_nec($pdo, (array)$user, $unidadId);

$personalAreaId = 0;
$personalAreaName = '';
if ($dniUser !== '') {
    $st = $pdo->prepare("
        SELECT pu.destino_interno_id, pu.destino_interno, di.nombre
        FROM personal_unidad pu
        LEFT JOIN destino_interno di ON di.id = COALESCE(pu.destino_interno_id, pu.destino_interno)
        WHERE pu.unidad_id = :uid
          AND REPLACE(REPLACE(REPLACE(pu.dni,'.',''),'-',''),' ','') = :dni
        ORDER BY pu.id DESC
        LIMIT 1
    ");
    $st->execute([':uid' => $unidadId, ':dni' => $dniUser]);
    $rowMe = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    $personalAreaId = (int)($rowMe['destino_interno_id'] ?? $rowMe['destino_interno'] ?? 0);
    $personalAreaName = trim((string)($rowMe['nombre'] ?? ''));
}

$isAdmin = in_array($roleCode, ['ADMIN', 'SUPERADMIN'], true);
$isInformatica = $isAdmin || stripos($personalAreaName, 'INFORMATICA') !== false || stripos($personalAreaName, 'INFORMÁTICA') !== false;
$canManage = $isInformatica;

$areas = $pdo->query("SELECT id, nombre FROM destino_interno WHERE estado = 'ACTIVO' ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$areaById = [];
foreach ($areas as $area) $areaById[(int)$area['id']] = (string)$area['nombre'];
$areasFormulario = $canManage
    ? $areas
    : array_values(array_filter($areas, static fn(array $area): bool => (int)$area['id'] === $personalAreaId));

$activos = [];
$st = $pdo->prepare("
    SELECT id, equipo_nombre, descripcion, dispositivo_tipo, area_id, estado
    FROM it_activos
    WHERE unidad_id = :uid AND categoria = 'informatica' AND condicion <> 'deposito'
    ORDER BY COALESCE(equipo_nombre, descripcion), id
");
$st->execute([':uid' => $unidadId]);
$activosAll = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
$activos = $canManage
    ? $activosAll
    : array_values(array_filter($activosAll, static fn(array $activo): bool => (int)($activo['area_id'] ?? 0) === $personalAreaId));

$flashOk = '';
$flashErr = '';
$requestMethod = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($requestMethod === 'POST') {
    csrf_verify();
    $accion = (string)($_POST['accion'] ?? '');
    try {
        if ($accion === 'crear') {
            $areaId = (int)($_POST['area_id'] ?? 0);
            if ($areaId <= 0 || !isset($areaById[$areaId])) throw new RuntimeException('Seleccioná un área válida.');
            if (!$canManage) {
                if ($personalAreaId <= 0) throw new RuntimeException('Tu usuario no tiene un área interna asignada. Pedí que te la carguen en Personal.');
                if ($areaId !== $personalAreaId) throw new RuntimeException('Solo podés registrar necesidades para tu propia área.');
            }
            $activoId = (int)($_POST['activo_id'] ?? 0);
            if ($activoId <= 0) $activoId = null;
            if ($activoId !== null) {
                $stActivo = $pdo->prepare("SELECT area_id FROM it_activos WHERE id = :id AND unidad_id = :uid AND categoria = 'informatica' AND condicion <> 'deposito' LIMIT 1");
                $stActivo->execute([':id' => $activoId, ':uid' => $unidadId]);
                $activoAreaId = (int)($stActivo->fetchColumn() ?: 0);
                if ($activoAreaId <= 0) throw new RuntimeException('El activo relacionado no existe o no pertenece al inventario activo.');
                if (!$canManage && $activoAreaId !== $personalAreaId) throw new RuntimeException('No podés relacionar pedidos con activos de otra área.');
            }
            $tipo = option_nec((string)($_POST['tipo'] ?? ''), ['equipo','repuesto','servicio','conectividad','software','mantenimiento','otro'], 'equipo');
            $prioridad = option_nec((string)($_POST['prioridad'] ?? ''), ['baja','media','alta','urgente'], 'media');
            $titulo = post_text_nec('titulo', 180);
            $detalle = post_text_nec('detalle', 8000);
            $justificacion = post_text_nec('justificacion', 8000);
            $cantidad = max(1, min(999, (int)($_POST['cantidad'] ?? 1)));
            $fecha = valid_date_nec((string)($_POST['fecha_necesidad'] ?? ''));
            if ($titulo === '') throw new RuntimeException('El título es obligatorio.');
            if ($detalle === '') throw new RuntimeException('El detalle de la necesidad es obligatorio.');

            $st = $pdo->prepare("
                INSERT INTO it_necesidades_area
                    (unidad_id, area_id, activo_id, tipo, prioridad, estado, titulo, detalle, justificacion, cantidad, fecha_necesidad, solicitante_nombre, solicitante_dni)
                VALUES
                    (:uid, :area_id, :activo_id, :tipo, :prioridad, 'solicitada', :titulo, :detalle, :justificacion, :cantidad, :fecha, :solicitante, :dni)
            ");
            $st->execute([
                ':uid' => $unidadId,
                ':area_id' => $areaId,
                ':activo_id' => $activoId,
                ':tipo' => $tipo,
                ':prioridad' => $prioridad,
                ':titulo' => $titulo,
                ':detalle' => $detalle,
                ':justificacion' => $justificacion !== '' ? $justificacion : null,
                ':cantidad' => $cantidad,
                ':fecha' => $fecha,
                ':solicitante' => $displayUser,
                ':dni' => $dniUser,
            ]);
            $flashOk = 'Necesidad registrada correctamente.';
        } elseif ($accion === 'gestionar') {
            if (!$canManage) throw new RuntimeException('No tenés permisos para gestionar necesidades.');
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new RuntimeException('Registro inválido.');
            $estado = option_nec((string)($_POST['estado'] ?? ''), ['solicitada','evaluacion','aprobada','en_proceso','resuelta','rechazada'], 'solicitada');
            $prioridad = option_nec((string)($_POST['prioridad'] ?? ''), ['baja','media','alta','urgente'], 'media');
            $responsable = post_text_nec('responsable', 160);
            $respuesta = post_text_nec('respuesta', 8000);
            $st = $pdo->prepare("
                UPDATE it_necesidades_area
                SET estado = :estado, prioridad = :prioridad, responsable = :responsable, respuesta = :respuesta
                WHERE id = :id AND unidad_id = :uid
                LIMIT 1
            ");
            $st->execute([
                ':estado' => $estado,
                ':prioridad' => $prioridad,
                ':responsable' => $responsable !== '' ? $responsable : null,
                ':respuesta' => $respuesta !== '' ? $respuesta : null,
                ':id' => $id,
                ':uid' => $unidadId,
            ]);
            $flashOk = 'Seguimiento actualizado.';
        }
    } catch (Throwable $ex) {
        $flashErr = $ex->getMessage();
    }
}

$fArea = (int)($_GET['area_id'] ?? 0);
$fEstado = option_nec((string)($_GET['estado'] ?? ''), ['','solicitada','evaluacion','aprobada','en_proceso','resuelta','rechazada'], '');
$fPrioridad = option_nec((string)($_GET['prioridad'] ?? ''), ['','baja','media','alta','urgente'], '');
$fQ = trim((string)($_GET['q'] ?? ''));

$where = ['n.unidad_id = :uid'];
$params = [':uid' => $unidadId];
if (!$canManage) {
    $where[] = 'n.area_id = :my_area_id';
    $params[':my_area_id'] = $personalAreaId > 0 ? $personalAreaId : -1;
}
if ($fArea > 0) { $where[] = 'n.area_id = :area_id'; $params[':area_id'] = $fArea; }
if ($fEstado !== '') { $where[] = 'n.estado = :estado'; $params[':estado'] = $fEstado; }
if ($fPrioridad !== '') { $where[] = 'n.prioridad = :prioridad'; $params[':prioridad'] = $fPrioridad; }
if ($fQ !== '') {
    $where[] = '(n.titulo LIKE :q OR n.detalle LIKE :q OR n.solicitante_nombre LIKE :q OR a.equipo_nombre LIKE :q OR a.descripcion LIKE :q)';
    $params[':q'] = '%' . $fQ . '%';
}

$sql = "
    SELECT n.*, di.nombre AS area_nombre, a.equipo_nombre, a.descripcion AS activo_desc, a.dispositivo_tipo
    FROM it_necesidades_area n
    LEFT JOIN destino_interno di ON di.id = n.area_id
    LEFT JOIN it_activos a ON a.id = n.activo_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY
      FIELD(n.estado, 'solicitada','evaluacion','aprobada','en_proceso','rechazada','resuelta'),
      FIELD(n.prioridad, 'urgente','alta','media','baja'),
      n.created_at DESC
    LIMIT 300
";
$st = $pdo->prepare($sql);
$st->execute($params);
$necesidades = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

$st = $pdo->prepare("
    SELECT
      di.id AS area_id,
      di.nombre AS area_nombre,
      COALESCE(inv.total_activos, 0) AS total_activos,
      COALESCE(inv.computadoras, 0) AS computadoras,
      COALESCE(inv.impresoras, 0) AS impresoras,
      COALESCE(nec.abiertas, 0) AS abiertas,
      COALESCE(nec.urgentes, 0) AS urgentes,
      COALESCE(nec.resueltas, 0) AS resueltas
    FROM destino_interno di
    LEFT JOIN (
      SELECT area_id,
        COUNT(*) AS total_activos,
        SUM(dispositivo_tipo IN ('PC','NOTEBOOK','SERVIDOR')) AS computadoras,
        SUM(dispositivo_tipo = 'IMPRESORA') AS impresoras
      FROM it_activos
      WHERE unidad_id = :uid_inv AND categoria = 'informatica' AND condicion <> 'deposito'
      GROUP BY area_id
    ) inv ON inv.area_id = di.id
    LEFT JOIN (
      SELECT area_id,
        SUM(estado <> 'resuelta') AS abiertas,
        SUM(prioridad = 'urgente' AND estado <> 'resuelta') AS urgentes,
        SUM(estado = 'resuelta') AS resueltas
      FROM it_necesidades_area
      WHERE unidad_id = :uid_nec
      GROUP BY area_id
    ) nec ON nec.area_id = di.id
    WHERE di.estado = 'ACTIVO'
      " . (!$canManage ? "AND di.id = :my_summary_area" : "") . "
    ORDER BY abiertas DESC, urgentes DESC, di.nombre
");
$summaryParams = [':uid_inv' => $unidadId, ':uid_nec' => $unidadId];
if (!$canManage) $summaryParams[':my_summary_area'] = $personalAreaId > 0 ? $personalAreaId : -1;
$st->execute($summaryParams);
$resumenAreas = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

$totales = ['abiertas' => 0, 'urgentes' => 0, 'resueltas' => 0, 'activos' => 0];
foreach ($resumenAreas as $r) {
    $totales['abiertas'] += (int)$r['abiertas'];
    $totales['urgentes'] += (int)$r['urgentes'];
    $totales['resueltas'] += (int)$r['resueltas'];
    $totales['activos'] += (int)$r['total_activos'];
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
<title>Necesidades de las áreas · Informática</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="icon" href="<?= e($FAVICON) ?>">
<style>
  :root{--bg:#020617;--panel:#0f172a;--panel2:#111827;--line:#334155;--text:#e5e7eb;--muted:#94a3b8;--accent:#fbbf24;--ok:#22c55e;--blue:#38bdf8;--hot:#fb7185;}
  *{box-sizing:border-box}
  body{margin:0;min-height:100vh;background:#000;color:var(--text);font-family:system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,sans-serif;}
  .page-bg{position:fixed;inset:0;z-index:-2;background:linear-gradient(160deg,rgba(0,0,0,.90),rgba(2,6,23,.72),rgba(0,0,0,.92)),url("<?= e($IMG_BG) ?>") center/cover no-repeat;background-attachment:fixed;filter:saturate(1.04);}
  .wrap{max-width:1440px;margin:0 auto;padding:18px;}
  .hero{display:flex;gap:14px;align-items:center;padding:14px 18px;border-bottom:1px solid rgba(148,163,184,.25);background:rgba(2,6,23,.62);backdrop-filter:blur(7px);}
  .hero img{width:54px;height:54px;object-fit:contain}.hero h1{font-size:1.1rem;margin:0;font-weight:950}.hero p{margin:2px 0 0;color:#cbd5e1;font-size:.9rem}.spacer{flex:1}
  .btnx{display:inline-flex;align-items:center;justify-content:center;border:1px solid rgba(226,232,240,.55);border-radius:10px;padding:.46rem .8rem;color:#fff;text-decoration:none;background:rgba(15,23,42,.75);font-weight:850}
  .btnx.primary{background:#fbbf24;color:#111827;border-color:#fbbf24}.btnx.green{background:#22c55e;color:#052e16;border-color:#22c55e}.btnx:hover{filter:brightness(1.08);color:inherit}
  .panel{background:rgba(15,23,42,.92);border:1px solid rgba(148,163,184,.34);border-radius:18px;padding:16px;box-shadow:0 18px 45px rgba(0,0,0,.48)}
  .chip{display:inline-flex;align-items:center;border-radius:999px;padding:.22rem .65rem;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.12);font-size:.76rem;font-weight:900;text-transform:uppercase;letter-spacing:.04em;color:#e5e7eb}
  .muted{color:var(--muted)}.section-title{font-size:1rem;font-weight:950;margin:0 0 10px}
  .kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.kpi{background:rgba(2,6,23,.58);border:1px solid rgba(148,163,184,.26);border-radius:14px;padding:12px}.kpi b{display:block;font-size:1.55rem;line-height:1}.kpi span{font-size:.78rem;color:#a7b1c2;text-transform:uppercase;font-weight:850}
  .form-control,.form-select,textarea{background:#07111f!important;border:1px solid rgba(148,163,184,.38)!important;color:#e5e7eb!important;border-radius:10px!important}.form-control::placeholder,textarea::placeholder{color:#64748b!important}.form-label{font-size:.78rem;font-weight:900;text-transform:uppercase;color:#cbd5e1}
  .table-wrap{overflow:auto;border:1px solid rgba(148,163,184,.28);border-radius:14px}.table{margin:0;color:#e5e7eb}.table thead th{background:#102345;color:#fff;border-color:#2d4d76;font-size:.78rem;text-transform:uppercase;white-space:nowrap}.table tbody td{background:rgba(226,232,240,.94);color:#172033;border-color:#cbd5e1;vertical-align:top}.table tbody tr:nth-child(even) td{background:rgba(219,234,254,.92)}
  .badge-soft,.badge-hot,.badge-ok,.badge-work{display:inline-flex;border-radius:999px;padding:.22rem .55rem;font-size:.74rem;font-weight:900;white-space:nowrap}.badge-soft{background:#e2e8f0;color:#334155}.badge-hot{background:#fee2e2;color:#9f1239}.badge-ok{background:#dcfce7;color:#166534}.badge-work{background:#dbeafe;color:#1d4ed8}
  .need-card{background:rgba(2,6,23,.62);border:1px solid rgba(148,163,184,.28);border-radius:14px;padding:12px;height:100%}
  .small-input{min-width:120px}.textarea-mini{min-width:260px;min-height:42px}
  @media (max-width:980px){.kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.hero{align-items:flex-start;flex-wrap:wrap}.wrap{padding:12px}}
</style>
</head>
<body>
<div class="page-bg"></div>
<header class="hero">
  <img src="<?= e($ESCUDO) ?>" alt="Escudo">
  <div>
    <h1><?= e($UNIDAD_NOMBRE) ?> · Necesidades de las áreas</h1>
    <p><?= e($UNIDAD_SUB) ?> · Pedidos, prioridades y seguimiento vinculado al inventario.</p>
  </div>
  <div class="spacer"></div>
  <a class="btnx" href="informatica.php">Volver</a>
  <a class="btnx green" href="informatica_inventarios.php">Inventario</a>
</header>

<main class="wrap">
  <?php if ($flashOk !== ''): ?><div class="alert alert-success py-2"><?= e($flashOk) ?></div><?php endif; ?>
  <?php if ($flashErr !== ''): ?><div class="alert alert-danger py-2"><?= e($flashErr) ?></div><?php endif; ?>

  <section class="panel mb-3">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
      <span class="chip">Resumen general</span>
      <span class="muted">Unidad ID <?= (int)$unidadId ?> · Rol <?= e($roleCode) ?><?= $canManage ? ' · Gestión habilitada para todas las áreas' : ' · Solicitudes solo para tu área' ?></span>
    </div>
    <div class="kpis">
      <div class="kpi"><span>Necesidades abiertas</span><b><?= (int)$totales['abiertas'] ?></b></div>
      <div class="kpi"><span>Urgentes abiertas</span><b><?= (int)$totales['urgentes'] ?></b></div>
      <div class="kpi"><span>Necesidades resueltas</span><b><?= (int)$totales['resueltas'] ?></b></div>
      <div class="kpi"><span>Activos informáticos</span><b><?= (int)$totales['activos'] ?></b></div>
    </div>
  </section>

  <div class="row g-3">
    <div class="col-xl-4">
      <section class="panel h-100">
        <h2 class="section-title">Cargar necesidad</h2>
        <form method="post" class="row g-2">
          <?= csrf_input() ?>
          <input type="hidden" name="accion" value="crear">
          <div class="col-12">
            <label class="form-label">Área solicitante</label>
            <select class="form-select" name="area_id" required <?= !$canManage ? 'readonly' : '' ?>>
              <option value="">Seleccionar...</option>
              <?php foreach ($areasFormulario as $area): $aid = (int)$area['id']; ?>
                <option value="<?= $aid ?>" <?= $aid === $personalAreaId ? 'selected' : '' ?>><?= e((string)$area['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if (!$canManage && $personalAreaId <= 0): ?>
              <div class="text-warning small mt-1">Tu usuario no tiene área interna asignada; no vas a poder registrar pedidos hasta corregir eso en Personal.</div>
            <?php endif; ?>
          </div>
          <div class="col-md-6">
            <label class="form-label">Tipo</label>
            <select class="form-select" name="tipo">
              <?php foreach (['equipo','repuesto','servicio','conectividad','software','mantenimiento','otro'] as $tipo): ?>
                <option value="<?= e($tipo) ?>"><?= e(label_nec($tipo)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Prioridad</label>
            <select class="form-select" name="prioridad">
              <option value="media">Media</option>
              <option value="alta">Alta</option>
              <option value="urgente">Urgente</option>
              <option value="baja">Baja</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Cantidad</label>
            <input class="form-control" type="number" min="1" max="999" name="cantidad" value="1">
          </div>
          <div class="col-md-6">
            <label class="form-label">Fecha requerida</label>
            <input class="form-control" type="date" name="fecha_necesidad">
          </div>
          <div class="col-12">
            <label class="form-label">Activo relacionado</label>
            <select class="form-select" name="activo_id">
              <option value="">Sin activo puntual</option>
              <?php foreach ($activos as $a):
                $label = trim((string)($a['equipo_nombre'] ?: $a['descripcion']));
                if ($label === '') $label = 'Activo #' . (int)$a['id'];
              ?>
                <option value="<?= (int)$a['id'] ?>"><?= e($label) ?> · <?= e((string)$a['dispositivo_tipo']) ?> · <?= e($areaById[(int)$a['area_id']] ?? 'Sin área') ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Título</label>
            <input class="form-control" name="titulo" maxlength="180" required placeholder="Ej: Se necesita una impresora para Operaciones">
          </div>
          <div class="col-12">
            <label class="form-label">Detalle</label>
            <textarea class="form-control" name="detalle" rows="4" required placeholder="Qué se necesita, dónde, para quién y qué problema resuelve."></textarea>
          </div>
          <div class="col-12">
            <label class="form-label">Justificación</label>
            <textarea class="form-control" name="justificacion" rows="3" placeholder="Impacto operativo, urgencia, equipo roto, falta de capacidad, etc."></textarea>
          </div>
          <div class="col-12 d-grid mt-2">
            <button class="btnx primary" type="submit">Registrar necesidad</button>
          </div>
        </form>
      </section>
    </div>

    <div class="col-xl-8">
      <section class="panel mb-3">
        <div class="d-flex flex-wrap justify-content-between gap-2 align-items-center mb-2">
          <h2 class="section-title mb-0">Necesidades por área</h2>
          <span class="muted">Comparado con activos del inventario</span>
        </div>
        <div class="table-wrap">
          <table class="table table-sm">
            <thead>
              <tr>
                <th>Área</th><th>Activos</th><th>Computadoras</th><th>Impresoras</th><th>Abiertas</th><th>Urgentes</th><th>Resueltas</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($resumenAreas as $r): ?>
                <tr>
                  <td><strong><?= e((string)$r['area_nombre']) ?></strong></td>
                  <td><?= (int)$r['total_activos'] ?></td>
                  <td><?= (int)$r['computadoras'] ?></td>
                  <td><?= (int)$r['impresoras'] ?></td>
                  <td><?= (int)$r['abiertas'] ?></td>
                  <td><?= (int)$r['urgentes'] ?></td>
                  <td><?= (int)$r['resueltas'] ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>

      <section class="panel">
        <h2 class="section-title">Seguimiento</h2>
        <form method="get" class="row g-2 mb-3">
          <div class="col-md-3">
            <select class="form-select" name="area_id" <?= !$canManage ? 'disabled' : '' ?>>
              <option value="0">Todas las áreas</option>
              <?php foreach ($areas as $area): $aid = (int)$area['id']; ?>
                <option value="<?= $aid ?>" <?= $aid === $fArea ? 'selected' : '' ?>><?= e((string)$area['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if (!$canManage): ?><input type="hidden" name="area_id" value="<?= (int)$personalAreaId ?>"><?php endif; ?>
          </div>
          <div class="col-md-2">
            <select class="form-select" name="estado">
              <option value="">Todo estado</option>
              <?php foreach (['solicitada','evaluacion','aprobada','en_proceso','resuelta','rechazada'] as $estado): ?>
                <option value="<?= e($estado) ?>" <?= $estado === $fEstado ? 'selected' : '' ?>><?= e(label_nec($estado)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <select class="form-select" name="prioridad">
              <option value="">Toda prioridad</option>
              <?php foreach (['urgente','alta','media','baja'] as $prioridad): ?>
                <option value="<?= e($prioridad) ?>" <?= $prioridad === $fPrioridad ? 'selected' : '' ?>><?= e(label_nec($prioridad)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3"><input class="form-control" name="q" value="<?= e($fQ) ?>" placeholder="Buscar..."></div>
          <div class="col-md-2 d-grid"><button class="btnx" type="submit">Filtrar</button></div>
        </form>

        <div class="table-wrap">
          <table class="table table-sm">
            <thead>
              <tr>
                <th>Pedido</th><th>Área</th><th>Tipo</th><th>Cant.</th><th>Prioridad</th><th>Estado</th><th>Inventario</th><th>Seguimiento</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$necesidades): ?>
                <tr><td colspan="8" class="text-center py-4">Todavía no hay necesidades cargadas.</td></tr>
              <?php endif; ?>
              <?php foreach ($necesidades as $n):
                $activoLabel = trim((string)($n['equipo_nombre'] ?: $n['activo_desc']));
              ?>
                <tr>
                  <td style="min-width:260px">
                    <strong><?= e((string)$n['titulo']) ?></strong>
                    <div class="small"><?= nl2br(e((string)$n['detalle'])) ?></div>
                    <?php if (trim((string)$n['justificacion']) !== ''): ?><div class="small mt-1"><strong>Justificación:</strong> <?= nl2br(e((string)$n['justificacion'])) ?></div><?php endif; ?>
                    <div class="small text-muted mt-1">Solicitó: <?= e((string)$n['solicitante_nombre']) ?> · <?= e((string)$n['created_at']) ?></div>
                  </td>
                  <td><?= e((string)($n['area_nombre'] ?? 'Sin área')) ?></td>
                  <td><span class="badge-soft"><?= e(label_nec((string)$n['tipo'])) ?></span></td>
                  <td><?= (int)$n['cantidad'] ?></td>
                  <td><span class="<?= e(badge_nec((string)$n['prioridad'])) ?>"><?= e(label_nec((string)$n['prioridad'])) ?></span></td>
                  <td><span class="<?= e(badge_nec((string)$n['estado'])) ?>"><?= e(label_nec((string)$n['estado'])) ?></span></td>
                  <td style="min-width:170px">
                    <?php if ($activoLabel !== ''): ?>
                      <strong><?= e($activoLabel) ?></strong><br><span class="small"><?= e((string)$n['dispositivo_tipo']) ?></span>
                    <?php else: ?>
                      <span class="text-muted">Sin activo puntual</span>
                    <?php endif; ?>
                  </td>
                  <td style="min-width:360px">
                    <?php if ($canManage): ?>
                      <form method="post" class="d-flex flex-wrap gap-1 align-items-start">
                        <?= csrf_input() ?>
                        <input type="hidden" name="accion" value="gestionar">
                        <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
                        <select class="form-select form-select-sm small-input" name="estado">
                          <?php foreach (['solicitada','evaluacion','aprobada','en_proceso','resuelta','rechazada'] as $estado): ?>
                            <option value="<?= e($estado) ?>" <?= $estado === (string)$n['estado'] ? 'selected' : '' ?>><?= e(label_nec($estado)) ?></option>
                          <?php endforeach; ?>
                        </select>
                        <select class="form-select form-select-sm small-input" name="prioridad">
                          <?php foreach (['urgente','alta','media','baja'] as $prioridad): ?>
                            <option value="<?= e($prioridad) ?>" <?= $prioridad === (string)$n['prioridad'] ? 'selected' : '' ?>><?= e(label_nec($prioridad)) ?></option>
                          <?php endforeach; ?>
                        </select>
                        <input class="form-control form-control-sm small-input" name="responsable" value="<?= e((string)$n['responsable']) ?>" placeholder="Responsable">
                        <textarea class="form-control form-control-sm textarea-mini" name="respuesta" placeholder="Respuesta / seguimiento"><?= e((string)$n['respuesta']) ?></textarea>
                        <button class="btnx green" type="submit">Guardar</button>
                      </form>
                    <?php else: ?>
                      <?php if (trim((string)$n['responsable']) !== ''): ?><div class="small"><strong>Resp.:</strong> <?= e((string)$n['responsable']) ?></div><?php endif; ?>
                      <?php if (trim((string)$n['respuesta']) !== ''): ?><div class="small"><?= nl2br(e((string)$n['respuesta'])) ?></div><?php else: ?><span class="text-muted">Sin respuesta todavía.</span><?php endif; ?>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
