<?php
declare(strict_types=1);

require_once __DIR__ . '/../../auth/bootstrap.php';
require_login();
require_once __DIR__ . '/../../config/db.php';

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function norm_dni_dir(string $dni): string {
    return preg_replace('/\D+/', '', $dni);
}

function dir_post_string(string $key, int $max = 5000): string {
    $value = trim((string)($_POST[$key] ?? ''));
    if (mb_strlen($value, 'UTF-8') > $max) {
        $value = mb_substr($value, 0, $max, 'UTF-8');
    }
    return $value;
}

function dir_valid_date(?string $value): ?string {
    $value = trim((string)$value);
    if ($value === '') return null;
    $dt = DateTime::createFromFormat('Y-m-d', $value);
    return ($dt && $dt->format('Y-m-d') === $value) ? $value : null;
}

function dir_estado_label(string $estado): string {
    return match ($estado) {
        'cumplida' => 'Cumplida',
        'anulada' => 'Anulada',
        default => 'Pendiente',
    };
}

function dir_prioridad_label(string $prioridad): string {
    return match ($prioridad) {
        'urgente' => 'Urgente',
        'alta' => 'Alta',
        'baja' => 'Baja',
        default => 'Normal',
    };
}

function dir_badge_class(string $value): string {
    return match ($value) {
        'orden' => 'badge-order',
        'urgente', 'alta' => 'badge-hot',
        'cumplida' => 'badge-ok',
        'anulada' => 'badge-muted',
        default => 'badge-soft',
    };
}

function dir_ensure_tables(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS direccion_comunicaciones (
            id INT AUTO_INCREMENT PRIMARY KEY,
            unidad_id INT NOT NULL DEFAULT 1,
            tipo ENUM('aviso','orden') NOT NULL DEFAULT 'aviso',
            origen ENUM('direccion','jefe','protocolo') NOT NULL DEFAULT 'direccion',
            prioridad ENUM('baja','normal','alta','urgente') NOT NULL DEFAULT 'normal',
            estado ENUM('pendiente','cumplida','anulada') NOT NULL DEFAULT 'pendiente',
            titulo VARCHAR(180) NOT NULL,
            detalle TEXT NOT NULL,
            destinatario VARCHAR(160) NOT NULL DEFAULT 'Todo el personal',
            vence DATE NULL,
            creado_por VARCHAR(180) NULL,
            creado_dni VARCHAR(20) NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_dir_com_unidad_estado (unidad_id, estado),
            INDEX idx_dir_com_tipo_origen (tipo, origen),
            INDEX idx_dir_com_vence (vence)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

dir_ensure_tables($pdo);

$user = function_exists('current_user') ? current_user() : ($_SESSION['user'] ?? []);
$displayUser = trim((string)($user['nombre_completo'] ?? $user['display_name'] ?? $user['full_name'] ?? $user['username'] ?? 'Usuario'));
$dniNorm = norm_dni_dir((string)($user['dni'] ?? $user['username'] ?? ''));
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
$UNIDAD_SUB = '';
if (function_exists('unidad_context')) {
    $ctx = unidad_context($pdo, $unidadId);
    $IMG_BG = (string)$ctx['bg_url'];
    $ESCUDO = (string)$ctx['escudo_url'];
    $FAVICON = (string)$ctx['icon_url'];
    $UNIDAD_NOMBRE = (string)$ctx['nombre_completo'];
    $UNIDAD_SUB = (string)$ctx['subnombre'];
}

$flashOk = '';
$flashErr = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $accion = (string)($_POST['accion'] ?? '');

    try {
        if ($accion === 'crear') {
            $tipo = in_array((string)($_POST['tipo'] ?? ''), ['aviso', 'orden'], true) ? (string)$_POST['tipo'] : 'aviso';
            $origen = in_array((string)($_POST['origen'] ?? ''), ['direccion', 'jefe', 'protocolo'], true) ? (string)$_POST['origen'] : 'direccion';
            $prioridad = in_array((string)($_POST['prioridad'] ?? ''), ['baja', 'normal', 'alta', 'urgente'], true) ? (string)$_POST['prioridad'] : 'normal';
            $titulo = dir_post_string('titulo', 180);
            $detalle = dir_post_string('detalle', 6000);
            $destinatario = dir_post_string('destinatario', 160);
            $vence = dir_valid_date((string)($_POST['vence'] ?? ''));

            if ($titulo === '') throw new RuntimeException('El título es obligatorio.');
            if ($detalle === '') throw new RuntimeException('El detalle es obligatorio.');
            if ($destinatario === '') $destinatario = 'Todo el personal';

            $st = $pdo->prepare("
                INSERT INTO direccion_comunicaciones
                    (unidad_id, tipo, origen, prioridad, estado, titulo, detalle, destinatario, vence, creado_por, creado_dni)
                VALUES
                    (:unidad_id, :tipo, :origen, :prioridad, 'pendiente', :titulo, :detalle, :destinatario, :vence, :creado_por, :creado_dni)
            ");
            $st->execute([
                ':unidad_id' => $unidadId,
                ':tipo' => $tipo,
                ':origen' => $origen,
                ':prioridad' => $prioridad,
                ':titulo' => $titulo,
                ':detalle' => $detalle,
                ':destinatario' => $destinatario,
                ':vence' => $vence,
                ':creado_por' => $displayUser,
                ':creado_dni' => $dniNorm,
            ]);
            $flashOk = ($tipo === 'orden') ? 'Orden registrada correctamente.' : 'Aviso publicado correctamente.';
        } elseif ($accion === 'estado') {
            $id = (int)($_POST['id'] ?? 0);
            $estado = in_array((string)($_POST['estado'] ?? ''), ['pendiente', 'cumplida', 'anulada'], true) ? (string)$_POST['estado'] : 'pendiente';
            if ($id <= 0) throw new RuntimeException('Registro inválido.');

            $st = $pdo->prepare("
                UPDATE direccion_comunicaciones
                SET estado = :estado
                WHERE id = :id AND unidad_id = :unidad_id
                LIMIT 1
            ");
            $st->execute([':estado' => $estado, ':id' => $id, ':unidad_id' => $unidadId]);
            $flashOk = 'Estado actualizado.';
        }
    } catch (Throwable $ex) {
        $flashErr = $ex->getMessage();
    }
}

$filtroTipo = in_array((string)($_GET['tipo'] ?? ''), ['aviso', 'orden'], true) ? (string)$_GET['tipo'] : '';
$filtroOrigen = in_array((string)($_GET['origen'] ?? ''), ['direccion', 'jefe', 'protocolo'], true) ? (string)$_GET['origen'] : '';
$filtroEstado = in_array((string)($_GET['estado'] ?? ''), ['pendiente', 'cumplida', 'anulada'], true) ? (string)$_GET['estado'] : 'pendiente';

$where = ['unidad_id = :unidad_id'];
$params = [':unidad_id' => $unidadId];
if ($filtroTipo !== '') {
    $where[] = 'tipo = :tipo';
    $params[':tipo'] = $filtroTipo;
}
if ($filtroOrigen !== '') {
    $where[] = 'origen = :origen';
    $params[':origen'] = $filtroOrigen;
}
if ($filtroEstado !== '') {
    $where[] = 'estado = :estado';
    $params[':estado'] = $filtroEstado;
}

$st = $pdo->prepare("
    SELECT *
    FROM direccion_comunicaciones
    WHERE " . implode(' AND ', $where) . "
    ORDER BY
      CASE prioridad WHEN 'urgente' THEN 1 WHEN 'alta' THEN 2 WHEN 'normal' THEN 3 ELSE 4 END,
      COALESCE(vence, '2999-12-31') ASC,
      id DESC
    LIMIT 200
");
$st->execute($params);
$items = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

$kpi = ['avisos' => 0, 'ordenes' => 0, 'vencidas' => 0, 'urgentes' => 0];
$stK = $pdo->prepare("
    SELECT
      SUM(tipo = 'aviso' AND estado = 'pendiente') AS avisos,
      SUM(tipo = 'orden' AND estado = 'pendiente') AS ordenes,
      SUM(estado = 'pendiente' AND vence IS NOT NULL AND vence < CURDATE()) AS vencidas,
      SUM(estado = 'pendiente' AND prioridad = 'urgente') AS urgentes
    FROM direccion_comunicaciones
    WHERE unidad_id = :unidad_id
");
$stK->execute([':unidad_id' => $unidadId]);
if ($row = $stK->fetch(PDO::FETCH_ASSOC)) {
    foreach ($kpi as $key => $_) $kpi[$key] = (int)($row[$key] ?? 0);
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Dirección · Avisos y órdenes</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="icon" href="<?= e($FAVICON) ?>">
<style>
  :root{--bg:#030712;--panel:#0f172a;--line:#334155;--text:#e5e7eb;--muted:#94a3b8;--blue:#38bdf8;--green:#22c55e;--red:#ef4444;--amber:#f59e0b;}
  *{box-sizing:border-box}
  body{margin:0;min-height:100vh;color:var(--text);font-family:system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,sans-serif;background:#020617;}
  body::before{content:"";position:fixed;inset:0;z-index:-2;background:linear-gradient(160deg,rgba(2,6,23,.94),rgba(15,23,42,.88)),url("<?= e($IMG_BG) ?>") center 24px / min(420px,45vw) auto no-repeat;opacity:.82}
  .wrap{max-width:1380px;margin:0 auto;padding:20px;}
  .hero{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;padding:8px 0 18px;}
  .brand{display:flex;align-items:center;gap:13px}
  .brand img{height:54px;width:auto;filter:drop-shadow(0 10px 24px rgba(0,0,0,.55))}
  .kicker{font-size:.76rem;text-transform:uppercase;letter-spacing:.16em;color:#93c5fd;font-weight:950}
  h1{font-size:1.55rem;margin:2px 0;font-weight:950}
  .sub{color:#cbd5e1;font-size:.9rem}
  .top-actions{display:flex;gap:8px;flex-wrap:wrap}
  .btnx{display:inline-flex;align-items:center;gap:8px;border:1px solid rgba(148,163,184,.42);border-radius:10px;background:rgba(15,23,42,.82);color:#e5e7eb;text-decoration:none;font-weight:850;padding:.52rem .78rem}
  .btnx:hover{background:#1e293b;color:#fff}
  .grid{display:grid;grid-template-columns:390px 1fr;gap:16px;align-items:start}
  .panel{background:rgba(15,23,42,.88);border:1px solid rgba(148,163,184,.32);border-radius:14px;box-shadow:0 22px 52px rgba(0,0,0,.38);overflow:hidden}
  .panel-pad{padding:16px}
  .panel-head{padding:14px 16px;border-bottom:1px solid rgba(148,163,184,.22);display:flex;justify-content:space-between;gap:12px;align-items:center}
  .panel-title{font-size:.92rem;text-transform:uppercase;letter-spacing:.11em;font-weight:950;color:#dbeafe}
  .stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:16px}
  .stat{background:rgba(15,23,42,.78);border:1px solid rgba(148,163,184,.26);border-radius:12px;padding:12px}
  .stat span{display:block;color:var(--muted);font-size:.72rem;text-transform:uppercase;letter-spacing:.1em;font-weight:900}
  .stat b{display:block;font-size:1.35rem;color:#fff;margin-top:2px}
  label{font-size:.78rem;color:#cbd5e1;text-transform:uppercase;letter-spacing:.08em;font-weight:900;margin-bottom:6px}
  .form-control,.form-select{background:#020617;border:1px solid #334155;color:#e5e7eb;border-radius:10px}
  .form-control:focus,.form-select:focus{background:#020617;color:#fff;border-color:#38bdf8;box-shadow:0 0 0 .2rem rgba(56,189,248,.14)}
  .form-control::placeholder{color:#64748b}
  .submit{width:100%;border:0;border-radius:10px;background:linear-gradient(180deg,#2563eb,#1d4ed8);color:#fff;font-weight:950;padding:.72rem 1rem}
  .filters{display:flex;gap:8px;flex-wrap:wrap}
  .filters select{max-width:160px}
  .list{display:grid;gap:10px;padding:14px}
  .com{border:1px solid rgba(148,163,184,.24);border-radius:13px;background:rgba(2,6,23,.54);padding:13px}
  .com-top{display:flex;justify-content:space-between;gap:10px;align-items:flex-start}
  .com h2{font-size:1rem;margin:7px 0 6px;font-weight:950;color:#f8fafc}
  .com p{margin:0;color:#d1d5db;white-space:pre-wrap;line-height:1.45}
  .badges{display:flex;gap:6px;flex-wrap:wrap}
  .badge2{display:inline-flex;align-items:center;border-radius:999px;padding:.17rem .52rem;font-size:.72rem;font-weight:950;border:1px solid rgba(148,163,184,.3);background:rgba(15,23,42,.9);color:#e5e7eb}
  .badge-order{border-color:rgba(56,189,248,.45);background:rgba(56,189,248,.14);color:#bae6fd}
  .badge-hot{border-color:rgba(239,68,68,.55);background:rgba(239,68,68,.15);color:#fecaca}
  .badge-ok{border-color:rgba(34,197,94,.5);background:rgba(34,197,94,.14);color:#bbf7d0}
  .badge-muted{border-color:rgba(148,163,184,.3);background:rgba(71,85,105,.22);color:#cbd5e1}
  .meta{display:flex;gap:10px;flex-wrap:wrap;color:#94a3b8;font-size:.78rem;margin-top:10px}
  .state-form{display:flex;gap:7px;align-items:center;margin-top:12px}
  .state-form select{max-width:140px}
  .mini-btn{border:1px solid rgba(148,163,184,.4);background:rgba(15,23,42,.8);color:#e5e7eb;border-radius:9px;font-weight:850;padding:.35rem .58rem}
  .empty{padding:38px 14px;text-align:center;color:#cbd5e1}
  .alert{border-radius:12px}
  @media (max-width:1000px){.grid{grid-template-columns:1fr}.stats{grid-template-columns:repeat(2,minmax(0,1fr))}}
  @media (max-width:620px){.wrap{padding:14px}.stats{grid-template-columns:1fr}.com-top{display:block}.filters select{max-width:none;width:100%}}
</style>
</head>
<body>
<div class="wrap">
  <header class="hero">
    <div class="brand">
      <img src="<?= e($ESCUDO) ?>" alt="Escudo" onerror="this.style.display='none'">
      <div>
        <div class="kicker">Dirección · Jefe · Protocolo</div>
        <h1>Avisos y órdenes</h1>
        <div class="sub"><?= e($UNIDAD_NOMBRE) ?><?= $UNIDAD_SUB !== '' ? ' · ' . e($UNIDAD_SUB) : '' ?></div>
      </div>
    </div>
    <div class="top-actions">
      <a class="btnx" href="../inicio.php"><i class="bi bi-house"></i> Inicio</a>
      <a class="btnx" href="<?= e($BASE_APP_WEB) ?>/logout.php"><i class="bi bi-box-arrow-right"></i> Cerrar sesión</a>
    </div>
  </header>

  <?php if ($flashOk !== ''): ?><div class="alert alert-success"><?= e($flashOk) ?></div><?php endif; ?>
  <?php if ($flashErr !== ''): ?><div class="alert alert-danger"><?= e($flashErr) ?></div><?php endif; ?>

  <section class="stats">
    <div class="stat"><span>Avisos pendientes</span><b><?= (int)$kpi['avisos'] ?></b></div>
    <div class="stat"><span>Órdenes pendientes</span><b><?= (int)$kpi['ordenes'] ?></b></div>
    <div class="stat"><span>Vencidas</span><b><?= (int)$kpi['vencidas'] ?></b></div>
    <div class="stat"><span>Urgentes</span><b><?= (int)$kpi['urgentes'] ?></b></div>
  </section>

  <div class="grid">
    <aside class="panel">
      <div class="panel-head">
        <div class="panel-title">Nueva comunicación</div>
      </div>
      <form class="panel-pad" method="post" autocomplete="off">
        <?= csrf_input() ?>
        <input type="hidden" name="accion" value="crear">

        <div class="row g-2">
          <div class="col-6">
            <label for="tipo">Tipo</label>
            <select class="form-select" id="tipo" name="tipo">
              <option value="aviso">Aviso</option>
              <option value="orden">Orden</option>
            </select>
          </div>
          <div class="col-6">
            <label for="origen">Origen</label>
            <select class="form-select" id="origen" name="origen">
              <option value="direccion">Dirección</option>
              <option value="jefe">Jefe</option>
              <option value="protocolo">Protocolo</option>
            </select>
          </div>
          <div class="col-6">
            <label for="prioridad">Prioridad</label>
            <select class="form-select" id="prioridad" name="prioridad">
              <option value="normal">Normal</option>
              <option value="alta">Alta</option>
              <option value="urgente">Urgente</option>
              <option value="baja">Baja</option>
            </select>
          </div>
          <div class="col-6">
            <label for="vence">Vence</label>
            <input class="form-control" type="date" id="vence" name="vence">
          </div>
        </div>

        <div class="mt-3">
          <label for="destinatario">Destinatario</label>
          <input class="form-control" id="destinatario" name="destinatario" maxlength="160" placeholder="Todo el personal, S-1, Protocolo, Jefes de área...">
        </div>

        <div class="mt-3">
          <label for="titulo">Título</label>
          <input class="form-control" id="titulo" name="titulo" maxlength="180" required placeholder="Ej: Formación, reunión, directiva, visita...">
        </div>

        <div class="mt-3">
          <label for="detalle">Detalle</label>
          <textarea class="form-control" id="detalle" name="detalle" rows="7" maxlength="6000" required placeholder="Escribí el aviso u orden con los datos necesarios."></textarea>
        </div>

        <button class="submit mt-3" type="submit"><i class="bi bi-send-fill"></i> Publicar</button>
      </form>
    </aside>

    <main class="panel">
      <div class="panel-head">
        <div class="panel-title">Tablero de seguimiento</div>
        <form class="filters" method="get">
          <select class="form-select form-select-sm" name="tipo" onchange="this.form.submit()">
            <option value="">Todo tipo</option>
            <option value="aviso" <?= $filtroTipo === 'aviso' ? 'selected' : '' ?>>Avisos</option>
            <option value="orden" <?= $filtroTipo === 'orden' ? 'selected' : '' ?>>Órdenes</option>
          </select>
          <select class="form-select form-select-sm" name="origen" onchange="this.form.submit()">
            <option value="">Todo origen</option>
            <option value="direccion" <?= $filtroOrigen === 'direccion' ? 'selected' : '' ?>>Dirección</option>
            <option value="jefe" <?= $filtroOrigen === 'jefe' ? 'selected' : '' ?>>Jefe</option>
            <option value="protocolo" <?= $filtroOrigen === 'protocolo' ? 'selected' : '' ?>>Protocolo</option>
          </select>
          <select class="form-select form-select-sm" name="estado" onchange="this.form.submit()">
            <option value="pendiente" <?= $filtroEstado === 'pendiente' ? 'selected' : '' ?>>Pendientes</option>
            <option value="cumplida" <?= $filtroEstado === 'cumplida' ? 'selected' : '' ?>>Cumplidas</option>
            <option value="anulada" <?= $filtroEstado === 'anulada' ? 'selected' : '' ?>>Anuladas</option>
            <option value="" <?= $filtroEstado === '' ? 'selected' : '' ?>>Todas</option>
          </select>
        </form>
      </div>

      <?php if (!$items): ?>
        <div class="empty">No hay comunicaciones para el filtro seleccionado.</div>
      <?php else: ?>
        <div class="list">
          <?php foreach ($items as $it): ?>
            <?php
              $tipo = (string)$it['tipo'];
              $prioridad = (string)$it['prioridad'];
              $estado = (string)$it['estado'];
              $vence = (string)($it['vence'] ?? '');
              $vencida = $estado === 'pendiente' && $vence !== '' && $vence < date('Y-m-d');
            ?>
            <article class="com">
              <div class="com-top">
                <div class="badges">
                  <span class="badge2 <?= e(dir_badge_class($tipo)) ?>"><?= $tipo === 'orden' ? 'Orden' : 'Aviso' ?></span>
                  <span class="badge2 <?= e(dir_badge_class($prioridad)) ?>"><?= e(dir_prioridad_label($prioridad)) ?></span>
                  <span class="badge2 <?= e(dir_badge_class($estado)) ?>"><?= e(dir_estado_label($estado)) ?></span>
                  <?php if ($vencida): ?><span class="badge2 badge-hot">Vencida</span><?php endif; ?>
                </div>
                <div class="muted">#<?= (int)$it['id'] ?></div>
              </div>

              <h2><?= e($it['titulo'] ?? '') ?></h2>
              <p><?= e($it['detalle'] ?? '') ?></p>

              <div class="meta">
                <span><i class="bi bi-building"></i> <?= e(ucfirst((string)$it['origen'])) ?></span>
                <span><i class="bi bi-people"></i> <?= e($it['destinatario'] ?? '') ?></span>
                <?php if ($vence !== ''): ?><span><i class="bi bi-calendar-event"></i> Vence: <?= date('d/m/Y', strtotime($vence)) ?></span><?php endif; ?>
                <span><i class="bi bi-person"></i> <?= e($it['creado_por'] ?? '') ?></span>
                <span><i class="bi bi-clock"></i> <?= date('d/m/Y H:i', strtotime((string)$it['created_at'])) ?></span>
              </div>

              <form class="state-form" method="post">
                <?= csrf_input() ?>
                <input type="hidden" name="accion" value="estado">
                <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                <select class="form-select form-select-sm" name="estado">
                  <option value="pendiente" <?= $estado === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                  <option value="cumplida" <?= $estado === 'cumplida' ? 'selected' : '' ?>>Cumplida</option>
                  <option value="anulada" <?= $estado === 'anulada' ? 'selected' : '' ?>>Anulada</option>
                </select>
                <button class="mini-btn" type="submit"><i class="bi bi-check2-circle"></i> Guardar</button>
              </form>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </main>
  </div>
</div>
</body>
</html>
