<?php
// /ea/public/calendario.php — Calendario / Tareas / Diario (EC MIL M themed)
declare(strict_types=1);

require_once __DIR__ . '/../auth/bootstrap.php';
require_login();

require_once __DIR__ . '/../config/db.php';
/** @var PDO $pdo */

if (!function_exists('e')) {
  function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

$user = function_exists('current_user') ? current_user() : null;
$unidad_id     = (int)($user['unidad_id'] ?? 1);
$creado_por    = trim((string)($user['apellido_nombre'] ?? $user['nombre'] ?? $user['dni'] ?? ''));
$creado_por_id = (int)($user['id'] ?? 0);

// =====================================================
// BASE WEB + ASSETS (estilo como Informatica.php)
// calendario.php vive en /ea/public
// =====================================================
$SELF_WEB        = (string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
$PUBLIC_DIR_WEB  = rtrim(str_replace('\\','/', dirname($SELF_WEB)), '/');        // /ea/public
$APP_URL         = rtrim((string)preg_replace('#/public$#', '', $PUBLIC_DIR_WEB), '/'); // /ea
$ASSETS_URL      = ($APP_URL === '' ? '' : $APP_URL) . '/../assets';            // /ea/../assets

$IMG_BG   = $ASSETS_URL . '/img/fondo.png';
$ESCUDO   = $ASSETS_URL . '/img/ecmilm.png';
$FAVICON  = $ASSETS_URL . '/img/ecmilm.png';

$NOMBRE  = 'Escuela Militar de Montaña';
$LEYENDA = 'La Montaña Nos Une';

// Helpers URL (para links internos consistentes)
function url_public(string $path): string {
  global $PUBLIC_DIR_WEB;
  return $PUBLIC_DIR_WEB . $path;
}
function url_root(string $path): string {
  global $APP_URL;
  return $APP_URL . $path;
}

// -------------------------
// INPUTS
// -------------------------
$view = $_GET['view'] ?? 'calendario'; // calendario | tareas | reportes | diario
$view = is_string($view) ? $view : 'calendario';
if (!in_array($view, ['calendario','tareas','reportes','diario'], true)) $view = 'calendario';

$area = $_GET['area'] ?? 'ALL';
$area = is_string($area) ? trim($area) : 'ALL';
if ($area === '') $area = 'ALL';

$today = new DateTimeImmutable('today');

$ym = $_GET['ym'] ?? $today->format('Y-m');
$ym = is_string($ym) ? $ym : $today->format('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $ym)) $ym = $today->format('Y-m');

$monthStart = DateTimeImmutable::createFromFormat('Y-m-d', $ym . '-01') ?: $today->modify('first day of this month');
$monthEnd   = $monthStart->modify('last day of this month');

$selectedDay = $_GET['day'] ?? $today->format('Y-m-d');
$selectedDay = is_string($selectedDay) ? $selectedDay : $today->format('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDay)) $selectedDay = $today->format('Y-m-d');

// Si el day no está dentro del mes que estás viendo, lo igualamos al 1ro del mes (consistencia)
if (substr($selectedDay, 0, 7) !== $ym) $selectedDay = $monthStart->format('Y-m-d');

$err = '';
$ok  = '';

// -------------------------
// ENDPOINT: Export PDF (HTML imprimible)
// -------------------------
if (($_GET['action'] ?? '') === 'export_pdf') {
  try {
    $stT = $pdo->prepare("
      SELECT id, area_code, titulo, descripcion, estado, prioridad, inicio, fin, fecha_vencimiento, asignado_a, creado_por
      FROM calendario_tareas
      WHERE unidad_id = ?
        AND (
          (inicio IS NOT NULL AND DATE(inicio) BETWEEN ? AND ?)
          OR (fecha_vencimiento IS NOT NULL AND fecha_vencimiento BETWEEN ? AND ?)
        )
        " . ($area !== 'ALL' ? " AND area_code = ? " : "") . "
      ORDER BY COALESCE(inicio, CONCAT(fecha_vencimiento,' 00:00:00')) ASC
    ");
    $paramsT = [
      $unidad_id,
      $monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d'),
      $monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d'),
    ];
    if ($area !== 'ALL') $paramsT[] = $area;
    $stT->execute($paramsT);
    $tareas = $stT->fetchAll(PDO::FETCH_ASSOC);

    $stD = $pdo->prepare("
      SELECT id, area_code, fecha, detalle, creado_por
      FROM calendario_diario
      WHERE unidad_id = ?
        AND fecha BETWEEN ? AND ?
        " . ($area !== 'ALL' ? " AND area_code = ? " : "") . "
      ORDER BY fecha DESC
    ");
    $paramsD = [$unidad_id, $monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d')];
    if ($area !== 'ALL') $paramsD[] = $area;
    $stD->execute($paramsD);
    $diario = $stD->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/html; charset=utf-8');
    ?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Export - Calendario <?= e($ym) ?></title>
  <style>
    body{font-family: Arial, sans-serif; margin:24px; color:#111;}
    .hdr{display:flex; align-items:center; gap:12px; border-bottom:1px solid #ddd; padding-bottom:12px; margin-bottom:14px;}
    .hdr img{width:56px; height:56px; object-fit:contain;}
    h1{margin:0; font-size:18px;}
    .meta{color:#444; margin-top:4px; font-size:12px;}
    h2{margin:18px 0 8px; border-bottom:1px solid #ddd; padding-bottom:6px; font-size:14px;}
    table{border-collapse:collapse; width:100%; margin-top:8px;}
    th,td{border:1px solid #ddd; padding:8px; font-size:11px; vertical-align:top;}
    th{background:#f3f3f3;}
    .badge{display:inline-block; padding:2px 8px; border:1px solid #999; border-radius:999px; font-size:10px;}
  </style>
</head>
<body>
  <div class="hdr">
    <img src="<?= e($ESCUDO) ?>" alt="Escudo">
    <div>
      <h1>Calendario / Tareas / Diario</h1>
      <div class="meta">
        Mes: <b><?= e($ym) ?></b> (<?= e($NOMBRE) ?>) · Área: <b><?= e($area) ?></b> · Unidad ID: <b><?= (int)$unidad_id ?></b>
      </div>
    </div>
  </div>

  <h2>Tareas</h2>
  <table>
    <thead>
      <tr>
        <th>ID</th><th>Área</th><th>Título</th><th>Estado</th><th>Prioridad</th><th>Inicio</th><th>Fin</th><th>Venc.</th><th>Asignado</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$tareas): ?>
        <tr><td colspan="9">Sin datos</td></tr>
      <?php else: foreach ($tareas as $t): ?>
        <tr>
          <td><?= (int)$t['id'] ?></td>
          <td><?= e($t['area_code']) ?></td>
          <td><b><?= e($t['titulo']) ?></b><br><?= nl2br(e($t['descripcion'] ?? '')) ?></td>
          <td><span class="badge"><?= e($t['estado']) ?></span></td>
          <td><?= e($t['prioridad']) ?></td>
          <td><?= e((string)$t['inicio']) ?></td>
          <td><?= e((string)$t['fin']) ?></td>
          <td><?= e((string)$t['fecha_vencimiento']) ?></td>
          <td><?= e((string)$t['asignado_a']) ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>

  <h2>Diario</h2>
  <table>
    <thead>
      <tr><th>ID</th><th>Fecha</th><th>Área</th><th>Detalle</th><th>Creado por</th></tr>
    </thead>
    <tbody>
      <?php if (!$diario): ?>
        <tr><td colspan="5">Sin datos</td></tr>
      <?php else: foreach ($diario as $d): ?>
        <tr>
          <td><?= (int)$d['id'] ?></td>
          <td><?= e($d['fecha']) ?></td>
          <td><?= e($d['area_code']) ?></td>
          <td><?= nl2br(e($d['detalle'])) ?></td>
          <td><?= e((string)$d['creado_por']) ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>

  <script>window.print();</script>
</body>
</html>
    <?php
  } catch (Throwable $ex) {
    http_response_code(500);
    echo "Error exportando: " . e($ex->getMessage());
  }
  exit;
}

// -------------------------
// POST handlers (crear tarea / crear diario / cambiar estado)
// -------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  try {
    $action = $_POST['action'] ?? '';
    $action = is_string($action) ? $action : '';

    if ($unidad_id <= 0) throw new RuntimeException('Unidad inválida.');

    if ($action === 'crear_tarea') {
      $area_code   = trim((string)($_POST['area_code'] ?? ''));
      $titulo      = trim((string)($_POST['titulo'] ?? ''));
      $descripcion = trim((string)($_POST['descripcion'] ?? ''));
      $prioridad   = (string)($_POST['prioridad'] ?? 'MEDIA');
      $inicio      = trim((string)($_POST['inicio'] ?? ''));
      $fin         = trim((string)($_POST['fin'] ?? ''));
      $venc        = trim((string)($_POST['fecha_vencimiento'] ?? ''));
      $asignado_a  = trim((string)($_POST['asignado_a'] ?? ''));

      if ($area_code === '' || $titulo === '') throw new RuntimeException('Área y título son obligatorios.');
      if (!in_array($prioridad, ['BAJA','MEDIA','ALTA'], true)) $prioridad = 'MEDIA';

      $inicioDb = $inicio !== '' ? $inicio : null;
      $finDb    = $fin   !== '' ? $fin   : null;
      $vencDb   = $venc  !== '' ? $venc  : null;

      $st = $pdo->prepare("
        INSERT INTO calendario_tareas
          (unidad_id, area_code, titulo, descripcion, estado, prioridad, inicio, fin, fecha_vencimiento, asignado_a, creado_por, creado_por_id)
        VALUES
          (?, ?, ?, ?, 'POR_HACER', ?, ?, ?, ?, ?, ?, ?)
      ");
      $st->execute([
        $unidad_id,
        $area_code,
        $titulo,
        ($descripcion !== '' ? $descripcion : null),
        $prioridad,
        $inicioDb,
        $finDb,
        $vencDb,
        ($asignado_a !== '' ? $asignado_a : null),
        ($creado_por !== '' ? $creado_por : null),
        ($creado_por_id ?: null),
      ]);

      $ok = 'Tarea creada.';
      $view = 'tareas';
    }

    if ($action === 'cambiar_estado') {
      $id     = (int)($_POST['id'] ?? 0);
      $estado = (string)($_POST['estado'] ?? 'POR_HACER');
      if (!in_array($estado, ['POR_HACER','EN_PROCESO','REALIZADA'], true)) $estado = 'POR_HACER';

      $st = $pdo->prepare("UPDATE calendario_tareas SET estado = ? WHERE id = ? AND unidad_id = ?");
      $st->execute([$estado, $id, $unidad_id]);

      $ok = 'Estado actualizado.';
      $view = 'tareas';
    }

    if ($action === 'crear_diario') {
      $area_code = trim((string)($_POST['area_code'] ?? ''));
      $fecha     = trim((string)($_POST['fecha'] ?? ''));
      $detalle   = trim((string)($_POST['detalle'] ?? ''));

      if ($area_code === '' || $fecha === '' || $detalle === '') {
        throw new RuntimeException('Área, fecha y detalle son obligatorios.');
      }
      if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) throw new RuntimeException('Fecha inválida.');

      $st = $pdo->prepare("
        INSERT INTO calendario_diario (unidad_id, area_code, fecha, detalle, creado_por, creado_por_id)
        VALUES (?, ?, ?, ?, ?, ?)
      ");
      $st->execute([
        $unidad_id,
        $area_code,
        $fecha,
        $detalle,
        ($creado_por !== '' ? $creado_por : null),
        ($creado_por_id ?: null),
      ]);

      $ok = 'Entrada de diario guardada.';
      $view = 'diario';
    }

  } catch (Throwable $ex) {
    $err = $ex->getMessage();
  }
}

// -------------------------
// Cargar áreas
// -------------------------
$areas = [];
try {
  $st = $pdo->prepare("SELECT codigo, nombre FROM destino WHERE unidad_id = ? AND activo = 1 ORDER BY codigo ASC");
  $st->execute([$unidad_id]);
  $areas = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $ex) {
  // no cortar la página
}

// -------------------------
// Consultas
// -------------------------
$tareasMes = [];
$diarioMes = [];
$stats  = ['por_hacer'=>0,'en_proceso'=>0,'realizada'=>0];

// Para el calendario: agregados por día
$mapTareasPorDia = []; // YYYY-MM-DD => count
$mapDiarioPorDia = []; // YYYY-MM-DD => count

// Para panel de día seleccionado
$tareasDia = [];
$diarioDia = [];

try {
  // ===== TAREAS DEL MES
  $sqlT = "
    SELECT id, unidad_id, area_code, titulo, descripcion, estado, prioridad, inicio, fin, fecha_vencimiento, asignado_a, creado_por
    FROM calendario_tareas
    WHERE unidad_id = ?
      AND (
        (inicio IS NOT NULL AND DATE(inicio) BETWEEN ? AND ?)
        OR (fecha_vencimiento IS NOT NULL AND fecha_vencimiento BETWEEN ? AND ?)
      )
      " . ($area !== 'ALL' ? " AND area_code = ? " : "") . "
    ORDER BY COALESCE(inicio, CONCAT(fecha_vencimiento,' 00:00:00')) ASC, id DESC
  ";
  $paramsT = [
    $unidad_id,
    $monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d'),
    $monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d'),
  ];
  if ($area !== 'ALL') $paramsT[] = $area;

  $st = $pdo->prepare($sqlT);
  $st->execute($paramsT);
  $tareasMes = $st->fetchAll(PDO::FETCH_ASSOC);

  foreach ($tareasMes as $t) {
    $es = (string)($t['estado'] ?? '');
    if ($es === 'POR_HACER')  $stats['por_hacer']++;
    if ($es === 'EN_PROCESO') $stats['en_proceso']++;
    if ($es === 'REALIZADA')  $stats['realizada']++;

    // Día “clave” para mostrar en calendario:
    $dia = '';
    if (!empty($t['inicio'])) {
      $dia = substr((string)$t['inicio'], 0, 10);
    } elseif (!empty($t['fecha_vencimiento'])) {
      $dia = (string)$t['fecha_vencimiento'];
    }
    if ($dia !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dia)) {
      $mapTareasPorDia[$dia] = ($mapTareasPorDia[$dia] ?? 0) + 1;
    }
  }

  // ===== DIARIO DEL MES
  $sqlD = "
    SELECT id, unidad_id, area_code, fecha, detalle, creado_por
    FROM calendario_diario
    WHERE unidad_id = ?
      AND fecha BETWEEN ? AND ?
      " . ($area !== 'ALL' ? " AND area_code = ? " : "") . "
    ORDER BY fecha DESC, id DESC
  ";
  $paramsD = [$unidad_id, $monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d')];
  if ($area !== 'ALL') $paramsD[] = $area;

  $st = $pdo->prepare($sqlD);
  $st->execute($paramsD);
  $diarioMes = $st->fetchAll(PDO::FETCH_ASSOC);

  foreach ($diarioMes as $d) {
    $dia = (string)($d['fecha'] ?? '');
    if ($dia !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dia)) {
      $mapDiarioPorDia[$dia] = ($mapDiarioPorDia[$dia] ?? 0) + 1;
    }
  }

  // ===== PANEL DÍA SELECCIONADO
  $st = $pdo->prepare("
    SELECT id, area_code, titulo, descripcion, estado, prioridad, inicio, fin, fecha_vencimiento, asignado_a
    FROM calendario_tareas
    WHERE unidad_id = ?
      " . ($area !== 'ALL' ? " AND area_code = ? " : "") . "
      AND (
        (inicio IS NOT NULL AND DATE(inicio) = ?)
        OR (fecha_vencimiento IS NOT NULL AND fecha_vencimiento = ?)
      )
    ORDER BY COALESCE(inicio, CONCAT(fecha_vencimiento,' 00:00:00')) ASC, id DESC
  ");
  $paramsTD = [$unidad_id];
  if ($area !== 'ALL') $paramsTD[] = $area;
  $paramsTD[] = $selectedDay;
  $paramsTD[] = $selectedDay;
  $st->execute($paramsTD);
  $tareasDia = $st->fetchAll(PDO::FETCH_ASSOC);

  $st = $pdo->prepare("
    SELECT id, area_code, fecha, detalle
    FROM calendario_diario
    WHERE unidad_id = ?
      " . ($area !== 'ALL' ? " AND area_code = ? " : "") . "
      AND fecha = ?
    ORDER BY id DESC
  ");
  $paramsDD = [$unidad_id];
  if ($area !== 'ALL') $paramsDD[] = $area;
  $paramsDD[] = $selectedDay;
  $st->execute($paramsDD);
  $diarioDia = $st->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $ex) {
  $err = $err ?: $ex->getMessage();
}

// -------------------------
// Helpers calendario
// -------------------------
function month_label_es(string $ym): string {
  static $m = [1=>'Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
  $y = (int)substr($ym,0,4);
  $mo = (int)substr($ym,5,2);
  return ($m[$mo] ?? 'Mes') . ' ' . $y;
}
function build_qs(array $base, array $over = []): string {
  $q = array_merge($base, $over);
  return http_build_query($q);
}

$prevYm = $monthStart->modify('-1 month')->format('Y-m');
$nextYm = $monthStart->modify('+1 month')->format('Y-m');

// -------------------------
// Render
// -------------------------
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Calendario · <?= e($NOMBRE) ?></title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="icon" href="<?= e($FAVICON) ?>">
  <link rel="stylesheet" href="<?= e($ASSETS_URL) ?>/css/theme-602.css">

  <style>
    html,body{height:100%;}
    body{
      margin:0;
      color:#e5e7eb;
      background:#000;
      font-family:system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,sans-serif;
    }

    /* Fondo oscuro EC MIL M */
    .page-bg{
      position:fixed;
      inset:0;
      z-index:-2;
      pointer-events:none;
      background:
        linear-gradient(160deg, rgba(0,0,0,.88) 0%, rgba(0,0,0,.68) 55%, rgba(0,0,0,.88) 100%),
        url("<?= e($IMG_BG) ?>") center/cover no-repeat;
      background-attachment: fixed, fixed;
      filter:saturate(1.05);
    }
    .page-bg::before{
      content:"";
      position:absolute;
      inset:0;
      z-index:-1;
      opacity:.16;
      background-image:
        radial-gradient(1.4px 1.4px at 18% 22%, #9cd1ff 20%, transparent 60%),
        radial-gradient(1.2px 1.2px at 63% 48%, #b7ddff 20%, transparent 60%),
        radial-gradient(1.2px 1.2px at 82% 70%, #b7ddff 20%, transparent 60%),
        radial-gradient(1.6px 1.6px at 34% 76%, #cbe8ff 20%, transparent 60%),
        radial-gradient(1.1px 1.1px at 72% 16%, #a7d6ff 20%, transparent 60%);
      background-repeat:no-repeat;
      background-size: 1200px 800px, 1400px 900px, 1100px 900px, 1400px 1000px, 1300px 800px;
      background-position: 0 0, 30% 40%, 80% 60%, 10% 90%, 70% 10%;
    }

    .container-main{ max-width:1400px; margin:auto; }
    .page-wrap{ padding:18px; }

    /* Header */
    .brand-hero{
      padding:12px 0;
      border-bottom:1px solid rgba(148,163,184,.22);
      background:rgba(2,6,23,.55);
      backdrop-filter: blur(6px);
    }
    .hero-inner{
      display:flex;
      align-items:center;
      gap:14px;
      padding:10px 14px;
    }
    .brand-logo{
      width:56px;height:56px; object-fit:contain;
      filter:drop-shadow(0 2px 14px rgba(124,196,255,.25));
    }
    .brand-title{
      font-weight:900;
      font-size:1.1rem;
      line-height:1.1;
    }
    .brand-sub{
      font-size:.9rem;
      opacity:.9;
      color:#cbd5f5;
      margin-top:2px;
    }
    .user-info{
      margin-left:auto;
      text-align:right;
      font-size:.85rem;
      color:#cbd5f5;
    }
    .header-actions{
      margin-left:14px;
      display:flex;
      gap:8px;
      align-items:center;
    }

    /* Panel */
    .panel{
      background:rgba(15,17,23,.94);
      border:1px solid rgba(148,163,184,.35);
      border-radius:18px;
      padding:18px 18px 16px;
      box-shadow:0 18px 40px rgba(0,0,0,.55), inset 0 1px 0 rgba(255,255,255,.04);
      backdrop-filter:blur(8px);
    }
    .panel-title{
      font-size:1.05rem;
      font-weight:900;
      margin-bottom:6px;
      display:flex;
      align-items:center;
      gap:10px;
    }
    .panel-sub{
      font-size:.88rem;
      color:#cbd5f5;
      opacity:.9;
      margin-bottom:14px;
    }

    /* Inputs dark */
    .form-control, .form-select{
      background: rgba(255,255,255,.06);
      border:1px solid rgba(148,163,184,.28);
      color:#e5e7eb;
    }
    .form-control:focus, .form-select:focus{
      background: rgba(255,255,255,.08);
      color:#fff;
      border-color: rgba(120,170,255,.55);
      box-shadow: 0 0 0 .2rem rgba(90,140,255,.15);
    }

    /* Chips / badges */
    .chip{
      display:inline-flex;
      align-items:center;
      gap:.45rem;
      border:1px solid rgba(148,163,184,.28);
      border-radius:999px;
      padding:.25rem .65rem;
      font-size:.82rem;
      background: rgba(2,6,23,.55);
      color:#dbeafe;
    }
    .chip i{opacity:.9;}

    /* Buttons */
    .btn-soft{
      border:1px solid rgba(148,163,184,.28);
      background: rgba(255,255,255,.06);
      color:#e5e7eb;
      font-weight:800;
      border-radius:12px;
    }
    .btn-soft:hover{background: rgba(255,255,255,.10); color:#fff;}
    .btn-pill{
      display:inline-flex;
      align-items:center;
      gap:.45rem;
      padding:.55rem 1.05rem;
      border-radius:999px;
      border:none;
      font-size:.86rem;
      font-weight:900;
      text-decoration:none;
      background:#0ea5e9;
      color:#021827;
      box-shadow:0 8px 22px rgba(14,165,233,.55);
    }
    .btn-pill:hover{ background:#38bdf8; color:#021827; }

    /* Table */
    .table{ color:#e5e7eb; }
    .table th{ color:#cfe0ff; border-color:rgba(148,163,184,.22)!important; }
    .table td{ border-color:rgba(148,163,184,.16)!important; }
    .table-responsive{border-radius:14px; overflow:hidden;}

    /* Calendar */
    .cal-grid{
      display:grid;
      grid-template-columns: repeat(7, 1fr);
      gap:10px;
    }
    .dow{
      font-size:.78rem;
      letter-spacing:.08em;
      text-transform:uppercase;
      color:rgba(233,239,255,.65);
      padding:0 6px;
    }
    .day{
      background: rgba(2,6,23,.55);
      border:1px solid rgba(148,163,184,.22);
      border-radius:16px;
      min-height:92px;
      padding:10px 10px 8px;
      position:relative;
      transition: transform .10s ease, border-color .10s ease, background .10s ease;
      cursor:pointer;
      text-decoration:none;
      color:inherit;
      box-shadow:0 10px 24px rgba(0,0,0,.35);
    }
    .day:hover{
      transform: translateY(-1px);
      border-color: rgba(120,170,255,.45);
      background: rgba(255,255,255,.06);
    }
    .day.off{opacity:.32; cursor:default;}
    .day.sel{
      outline:2px solid rgba(90,140,255,.55);
      background: rgba(90,140,255,.10);
    }
    .dnum{font-weight:900; font-size:1rem;}
    .dots{display:flex; gap:6px; margin-top:8px; flex-wrap:wrap;}
    .dot{width:8px; height:8px; border-radius:50%;}
    .dot.t{background:#38bdf8;}
    .dot.d{background:#22c55e;}
    .mini{
      margin-top:6px;
      font-size:.78rem;
      color:rgba(233,239,255,.85);
      white-space:nowrap;
      overflow:hidden;
      text-overflow:ellipsis;
    }
    .small-muted{ font-size:.82rem; color:#9ca3af; }
  </style>
</head>

<body>
<div class="page-bg"></div>

<header class="brand-hero">
  <div class="hero-inner container-main">
    <img class="brand-logo" src="<?= e($ESCUDO) ?>" alt="Escudo">
    <div>
      <div class="brand-title"><?= e($NOMBRE) ?> · Calendario</div>
      <div class="brand-sub">“<?= e($LEYENDA) ?>”</div>
    </div>

    <div class="user-info">
      <div><strong><?= e((string)($user['display_name'] ?? $user['nombre_completo'] ?? $user['full_name'] ?? '')) ?></strong></div>
      <div class="small-muted">Unidad: <?= (int)$unidad_id ?> · Mes: <?= e(month_label_es($ym)) ?></div>
    </div>

    <div class="header-actions">
      <div class="header-back">
        <a class="btn btn-success btn-sm" style="font-weight:700; padding:.35rem .9rem;" href="<?= e(url_public('/inicio.php')) ?>">
          <i class="bi bi-arrow-left-circle me-1"></i> Volver
        </a>
      </div>
      <a class="btn btn-success btn-sm" style="font-weight:700; padding:.35rem .9rem;" href="<?= e(url_root('/logout.php')) ?>">
        <i class="bi bi-box-arrow-right me-1"></i> Cerrar sesión
      </a>
    </div>
  </div>
</header>

<div class="page-wrap">
  <div class="container-main">

    <?php if ($err): ?>
      <div class="alert alert-danger"><b>Error:</b> <?= e($err) ?></div>
    <?php endif; ?>
    <?php if ($ok): ?>
      <div class="alert alert-success"><?= e($ok) ?></div>
    <?php endif; ?>

    <!-- PANEL: controles -->
    <div class="panel mb-3">
      <div class="panel-title">
        <i class="bi bi-calendar2-week"></i>
        Calendario · Gestión
      </div>
      <div class="panel-sub">
        Unidad <b><?= (int)$unidad_id ?></b> · Mes <b><?= e($ym) ?></b> (<?= e(month_label_es($ym)) ?>) · Día <b><?= e($selectedDay) ?></b>
      </div>

      <form class="row g-2 align-items-end" method="get" action="">
        <div class="col-12 col-md-4 col-xl-3">
          <label class="form-label small-muted">Área</label>
          <select class="form-select" name="area">
            <option value="ALL" <?= $area==='ALL'?'selected':'' ?>>Todas las áreas</option>
            <?php foreach ($areas as $a): $code=(string)($a['codigo'] ?? ''); ?>
              <option value="<?= e($code) ?>" <?= $area===$code?'selected':'' ?>>
                <?= e($code) ?> · <?= e((string)($a['nombre'] ?? $code)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-6 col-md-2 col-xl-2">
          <label class="form-label small-muted">Mes (YYYY-MM)</label>
          <input class="form-control" name="ym" value="<?= e($ym) ?>" title="YYYY-MM">
        </div>

        <div class="col-6 col-md-3 col-xl-2">
          <label class="form-label small-muted">Día (YYYY-MM-DD)</label>
          <input class="form-control" name="day" value="<?= e($selectedDay) ?>" title="YYYY-MM-DD">
        </div>

        <div class="col-12 col-md-3 col-xl-2 d-grid">
          <input type="hidden" name="view" value="<?= e($view) ?>">
          <button class="btn btn-soft" type="submit"><i class="bi bi-funnel me-1"></i> Aplicar</button>
        </div>

        <div class="col-12 col-xl-3 d-flex flex-wrap gap-2 justify-content-xl-end">
          <a class="btn btn-soft" href="<?= e(url_public('/calendario.php?'.build_qs(['view'=>'calendario','area'=>$area,'ym'=>$ym,'day'=>$selectedDay]))) ?>">
            <i class="bi bi-grid-3x3-gap me-1"></i> Calendario
          </a>
          <a class="btn btn-soft" href="<?= e(url_public('/calendario.php?'.build_qs(['view'=>'tareas','area'=>$area,'ym'=>$ym,'day'=>$selectedDay]))) ?>">
            <i class="bi bi-check2-square me-1"></i> Tareas
          </a>
          <a class="btn btn-soft" href="<?= e(url_public('/calendario.php?'.build_qs(['view'=>'diario','area'=>$area,'ym'=>$ym,'day'=>$selectedDay]))) ?>">
            <i class="bi bi-journal-text me-1"></i> Diario
          </a>
          <a class="btn btn-soft" href="<?= e(url_public('/calendario.php?'.build_qs(['view'=>'reportes','area'=>$area,'ym'=>$ym,'day'=>$selectedDay]))) ?>">
            <i class="bi bi-bar-chart-line me-1"></i> Reportes
          </a>
        </div>
      </form>

      <div class="mt-3 d-flex flex-wrap gap-2">
        <span class="chip"><i class="bi bi-hourglass-split"></i> POR HACER: <b><?= (int)$stats['por_hacer'] ?></b></span>
        <span class="chip"><i class="bi bi-play-circle"></i> EN PROCESO: <b><?= (int)$stats['en_proceso'] ?></b></span>
        <span class="chip"><i class="bi bi-check-circle"></i> REALIZADAS: <b><?= (int)$stats['realizada'] ?></b></span>

        <a class="btn-pill ms-auto"
           href="<?= e(url_public('/calendario.php?'.build_qs(['action'=>'export_pdf','area'=>$area,'ym'=>$ym,'view'=>$view,'day'=>$selectedDay]))) ?>">
          <i class="bi bi-printer"></i> Exportar PDF
        </a>
      </div>
    </div>

    <div class="row g-3">

      <!-- LEFT: navegación de meses (solo en calendario) + ayuda -->
      <div class="col-12 col-lg-4">
        <div class="panel">
          <div class="panel-title"><i class="bi bi-info-circle"></i> Resumen</div>
          <div class="panel-sub">Filtrado por área <b><?= e($area) ?></b>. Mes <b><?= e(month_label_es($ym)) ?></b>.</div>

          <?php if ($view === 'calendario'): ?>
            <div class="d-flex gap-2">
              <a class="btn btn-soft w-50"
                 href="<?= e(url_public('/calendario.php?'.build_qs(['view'=>'calendario','area'=>$area,'ym'=>$prevYm,'day'=>$prevYm.'-01']))) ?>">
                <i class="bi bi-arrow-left"></i> Mes anterior
              </a>
              <a class="btn btn-soft w-50"
                 href="<?= e(url_public('/calendario.php?'.build_qs(['view'=>'calendario','area'=>$area,'ym'=>$nextYm,'day'=>$nextYm.'-01']))) ?>">
                Mes siguiente <i class="bi bi-arrow-right"></i>
              </a>
            </div>
            <div class="mt-3 small-muted">
              Tips:
              <ul class="mb-0">
                <li>Click en un día para ver el detalle a la derecha.</li>
                <li>Puntos: <span class="dot t" style="display:inline-block; vertical-align:middle;"></span> tareas, <span class="dot d" style="display:inline-block; vertical-align:middle;"></span> diario.</li>
                <li>Para cargar rápido: entrá a <b>Tareas</b> o <b>Diario</b>.</li>
              </ul>
            </div>
          <?php else: ?>
            <div class="small-muted">
              Estás en vista <b><?= e($view) ?></b>. Usá el panel superior para cambiar de vista o aplicar filtros.
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- RIGHT: contenido -->
      <div class="col-12 col-lg-8">

        <?php if ($view === 'calendario'): ?>

          <div class="panel mb-3">
            <div class="panel-title"><i class="bi bi-calendar3"></i> Calendario mensual</div>
            <div class="panel-sub">Seleccioná un día para ver tareas/diario del día. (<?= e(month_label_es($ym)) ?>)</div>

            <div class="mt-2 cal-grid">
              <?php
                $firstDow = (int)$monthStart->format('N'); // 1..7 (lun..dom)
                $daysInMonth = (int)$monthEnd->format('j');

                $dows = ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'];
                foreach ($dows as $dw) echo '<div class="dow">'.$dw.'</div>';

                // celdas previas (del mes anterior)
                $pad = $firstDow - 1;
                $prevMonth = $monthStart->modify('-1 month');
                $prevDays = (int)$prevMonth->modify('last day of this month')->format('j');
                for ($i=$pad; $i>0; $i--) {
                  $d = $prevDays - $i + 1;
                  echo '<div class="day off">';
                  echo '<div class="dnum">'.(int)$d.'</div>';
                  echo '</div>';
                }

                // mes actual
                for ($d=1; $d<=$daysInMonth; $d++) {
                  $date = $ym . '-' . str_pad((string)$d, 2, '0', STR_PAD_LEFT);
                  $isSel = ($date === $selectedDay);

                  $ctT = $mapTareasPorDia[$date] ?? 0;
                  $ctD = $mapDiarioPorDia[$date] ?? 0;

                  $href = url_public('/calendario.php?'.build_qs([
                    'view'=>'calendario','area'=>$area,'ym'=>$ym,'day'=>$date
                  ]));

                  echo '<a class="day '.($isSel?'sel':'').'" href="'.e($href).'">';
                  echo '<div class="dnum">'.$d.'</div>';

                  echo '<div class="dots">';
                  if ($ctT>0) echo '<span class="dot t" title="Tareas: '.(int)$ctT.'"></span>';
                  if ($ctD>0) echo '<span class="dot d" title="Diario: '.(int)$ctD.'"></span>';
                  echo '</div>';

                  $mini = '';
                  foreach ($tareasMes as $t) {
                    $key = '';
                    if (!empty($t['inicio'])) $key = substr((string)$t['inicio'],0,10);
                    elseif (!empty($t['fecha_vencimiento'])) $key = (string)$t['fecha_vencimiento'];
                    if ($key === $date) { $mini = (string)($t['titulo'] ?? ''); break; }
                  }
                  if ($mini !== '') echo '<div class="mini"><i class="bi bi-dot"></i> '.e($mini).'</div>';

                  echo '</a>';
                }

                // completar grilla
                $totalCells = $pad + $daysInMonth;
                $tail = (7 - ($totalCells % 7)) % 7;
                for ($i=1; $i<=$tail; $i++) {
                  echo '<div class="day off"><div class="dnum">'.$i.'</div></div>';
                }
              ?>
            </div>
          </div>

          <div class="panel">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
              <div>
                <div class="panel-title mb-0"><i class="bi bi-calendar-event"></i> Día seleccionado</div>
                <div class="small-muted"><?= e($selectedDay) ?> · Área: <?= e($area) ?></div>
              </div>
              <div class="d-flex gap-2">
                <a class="btn-pill" href="<?= e(url_public('/calendario.php?'.build_qs(['view'=>'tareas','area'=>$area,'ym'=>$ym,'day'=>$selectedDay]))) ?>">
                  <i class="bi bi-plus-circle"></i> Nueva tarea
                </a>
                <a class="btn btn-soft" href="<?= e(url_public('/calendario.php?'.build_qs(['view'=>'diario','area'=>$area,'ym'=>$ym,'day'=>$selectedDay]))) ?>">
                  <i class="bi bi-plus-circle"></i> Nuevo diario
                </a>
              </div>
            </div>

            <hr class="border border-light border-opacity-10 my-3">

            <div class="fw-bold mb-2"><i class="bi bi-check2-square me-1"></i> Tareas</div>
            <?php if (!$tareasDia): ?>
              <div class="small-muted">Sin tareas para este día.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                  <thead>
                    <tr><th>ID</th><th>Área</th><th>Título</th><th>Estado</th><th>Venc.</th></tr>
                  </thead>
                  <tbody>
                  <?php foreach ($tareasDia as $t): ?>
                    <tr>
                      <td><?= (int)$t['id'] ?></td>
                      <td><?= e($t['area_code']) ?></td>
                      <td>
                        <b><?= e($t['titulo']) ?></b>
                        <?php if(!empty($t['descripcion'])): ?>
                          <div class="small-muted"><?= nl2br(e($t['descripcion'])) ?></div>
                        <?php endif; ?>
                      </td>
                      <td><span class="chip"><i class="bi bi-flag"></i> <?= e($t['estado']) ?></span></td>
                      <td class="small"><?= e((string)($t['fecha_vencimiento'] ?? '')) ?></td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>

            <hr class="border border-light border-opacity-10 my-3">

            <div class="fw-bold mb-2"><i class="bi bi-journal-text me-1"></i> Diario</div>
            <?php if (!$diarioDia): ?>
              <div class="small-muted">Sin registros de diario para este día.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm mb-0">
                  <thead><tr><th>ID</th><th>Área</th><th>Detalle</th></tr></thead>
                  <tbody>
                    <?php foreach ($diarioDia as $d): ?>
                      <tr>
                        <td style="width:80px;"><?= (int)$d['id'] ?></td>
                        <td style="width:120px;"><?= e($d['area_code']) ?></td>
                        <td><?= nl2br(e($d['detalle'])) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>

        <?php elseif ($view === 'tareas'): ?>

          <div class="panel mb-3">
            <div class="panel-title"><i class="bi bi-plus-square"></i> Nueva tarea</div>
            <div class="panel-sub">Cargá una tarea para el mes actual. (Filtro actual: área <?= e($area) ?>)</div>

            <form method="post" class="row g-2">
              <input type="hidden" name="action" value="crear_tarea">
              <div class="col-md-4">
                <label class="form-label small-muted">Área</label>
                <select name="area_code" class="form-select" required>
                  <?php foreach ($areas as $a): $code=(string)($a['codigo'] ?? ''); ?>
                    <option value="<?= e($code) ?>"><?= e($code) ?> · <?= e((string)($a['nombre'] ?? $code)) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-8">
                <label class="form-label small-muted">Título</label>
                <input name="titulo" class="form-control" required maxlength="120">
              </div>
              <div class="col-12">
                <label class="form-label small-muted">Descripción</label>
                <textarea name="descripcion" class="form-control" rows="3"></textarea>
              </div>
              <div class="col-md-4">
                <label class="form-label small-muted">Prioridad</label>
                <select name="prioridad" class="form-select">
                  <option>BAJA</option><option selected>MEDIA</option><option>ALTA</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label small-muted">Inicio (YYYY-MM-DD HH:MM)</label>
                <input name="inicio" class="form-control" placeholder="2026-02-20 09:00">
              </div>
              <div class="col-md-4">
                <label class="form-label small-muted">Fin (YYYY-MM-DD HH:MM)</label>
                <input name="fin" class="form-control" placeholder="2026-02-20 11:00">
              </div>
              <div class="col-md-4">
                <label class="form-label small-muted">Vencimiento (YYYY-MM-DD)</label>
                <input name="fecha_vencimiento" class="form-control" value="<?= e($selectedDay) ?>">
              </div>
              <div class="col-md-8">
                <label class="form-label small-muted">Asignado a</label>
                <input name="asignado_a" class="form-control" placeholder="ST SCD ROJAS">
              </div>
              <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle me-1"></i> Guardar</button>
                <a class="btn btn-soft"
                   href="<?= e(url_public('/calendario.php?'.build_qs(['view'=>'tareas','area'=>$area,'ym'=>$ym,'day'=>$selectedDay]))) ?>">
                  <i class="bi bi-arrow-clockwise me-1"></i> Recargar
                </a>
              </div>
            </form>
          </div>

          <div class="panel">
            <div class="panel-title"><i class="bi bi-list-check"></i> Tareas del mes (<?= e($ym) ?>)</div>
            <div class="panel-sub">Editá estado desde la tabla. Ordenado por inicio/vencimiento.</div>

            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead>
                  <tr>
                    <th>ID</th><th>Área</th><th>Título</th><th>Estado</th><th>Prior.</th><th>Inicio</th><th>Venc.</th><th>Acción</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!$tareasMes): ?>
                    <tr><td colspan="8" class="small-muted">Sin tareas para este filtro.</td></tr>
                  <?php else: foreach ($tareasMes as $t): ?>
                    <tr>
                      <td><?= (int)$t['id'] ?></td>
                      <td><?= e($t['area_code']) ?></td>
                      <td>
                        <b><?= e($t['titulo']) ?></b>
                        <?php if (!empty($t['descripcion'])): ?>
                          <div class="small-muted"><?= nl2br(e($t['descripcion'])) ?></div>
                        <?php endif; ?>
                      </td>
                      <td><span class="chip"><i class="bi bi-flag"></i> <?= e($t['estado']) ?></span></td>
                      <td><?= e($t['prioridad']) ?></td>
                      <td class="small"><?= e((string)$t['inicio']) ?></td>
                      <td class="small"><?= e((string)$t['fecha_vencimiento']) ?></td>
                      <td>
                        <form method="post" class="d-flex gap-1">
                          <input type="hidden" name="action" value="cambiar_estado">
                          <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                          <select name="estado" class="form-select" style="width:170px;">
                            <option value="POR_HACER"  <?= $t['estado']==='POR_HACER'?'selected':'' ?>>POR_HACER</option>
                            <option value="EN_PROCESO" <?= $t['estado']==='EN_PROCESO'?'selected':'' ?>>EN_PROCESO</option>
                            <option value="REALIZADA"  <?= $t['estado']==='REALIZADA'?'selected':'' ?>>REALIZADA</option>
                          </select>
                          <button class="btn btn-soft" type="submit"><i class="bi bi-check-lg"></i></button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; endif; ?>
                </tbody>
              </table>
            </div>
          </div>

        <?php elseif ($view === 'diario'): ?>

          <div class="panel mb-3">
            <div class="panel-title"><i class="bi bi-journal-plus"></i> Nuevo registro en Diario</div>
            <div class="panel-sub">Dejá asentado actividad/parte diaria por área.</div>

            <form method="post" class="row g-2">
              <input type="hidden" name="action" value="crear_diario">
              <div class="col-md-4">
                <label class="form-label small-muted">Área</label>
                <select name="area_code" class="form-select" required>
                  <?php foreach ($areas as $a): $code=(string)($a['codigo'] ?? ''); ?>
                    <option value="<?= e($code) ?>"><?= e($code) ?> · <?= e((string)($a['nombre'] ?? $code)) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label small-muted">Fecha</label>
                <input name="fecha" class="form-control" value="<?= e($selectedDay) ?>" required>
              </div>
              <div class="col-12">
                <label class="form-label small-muted">Detalle</label>
                <textarea name="detalle" class="form-control" rows="4" required></textarea>
              </div>
              <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle me-1"></i> Guardar</button>
                <a class="btn btn-soft"
                   href="<?= e(url_public('/calendario.php?'.build_qs(['view'=>'diario','area'=>$area,'ym'=>$ym,'day'=>$selectedDay]))) ?>">
                  <i class="bi bi-arrow-clockwise me-1"></i> Recargar
                </a>
              </div>
            </form>
          </div>

          <div class="panel">
            <div class="panel-title"><i class="bi bi-journal-text"></i> Diario del mes (<?= e($ym) ?>)</div>
            <div class="panel-sub">Registros en orden descendente por fecha.</div>

            <div class="table-responsive">
              <table class="table table-sm mb-0">
                <thead><tr><th>Fecha</th><th>Área</th><th>Detalle</th></tr></thead>
                <tbody>
                  <?php if (!$diarioMes): ?>
                    <tr><td colspan="3" class="small-muted">Sin registros para este filtro.</td></tr>
                  <?php else: foreach ($diarioMes as $d): ?>
                    <tr>
                      <td style="width:130px;"><?= e($d['fecha']) ?></td>
                      <td style="width:120px;"><?= e($d['area_code']) ?></td>
                      <td><?= nl2br(e($d['detalle'])) ?></td>
                    </tr>
                  <?php endforeach; endif; ?>
                </tbody>
              </table>
            </div>
          </div>

        <?php else: /* reportes */ ?>

          <div class="panel">
            <div class="panel-title"><i class="bi bi-bar-chart-line"></i> Reportes</div>
            <div class="panel-sub">Opciones rápidas y próximos agregados.</div>

            <div class="small-muted">
              <div class="mb-2">• Exportar PDF mensual (tareas + diario): botón <b>Exportar PDF</b> arriba.</div>
              <div class="mb-2">• Resumen por estado: chips en el panel superior.</div>
              <div>Si querés lo siguiente lo armamos: vencidas, por asignado, por área, y export XLSX (PhpSpreadsheet).</div>
            </div>
          </div>

        <?php endif; ?>

      </div>
    </div>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
