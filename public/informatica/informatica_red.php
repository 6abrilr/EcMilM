<?php
/**
 * ea/public/informatica/calendario.php
 * INFORMÁTICA · CALENDARIO (tareas/eventos por unidad)
 *
 * ✅ Objetivo:
 * - Vista calendario (FullCalendar) con tareas/eventos.
 * - ABM por modal (crear/editar/borrar).
 * - Estados: pendiente / realizado.
 * - Multi-unidad (unidad_id desde sesión).
 *
 * ⚠️ Nota importante (para no inventar tu schema):
 * - Como no pegaste la estructura completa de `calendario_tareas` (en tu dump está truncada),
 *   este archivo crea una tabla compatible SI NO EXISTE.
 * - Si tu tabla ya existe con otros nombres de columnas, decime tu CREATE TABLE real y lo adapto 1:1.
 */

declare(strict_types=1);

$ROOT = realpath(__DIR__ . '/../../'); // /ea
if (!$ROOT) { http_response_code(500); exit('No se pudo resolver ROOT del proyecto.'); }

require_once $ROOT . '/auth/bootstrap.php';
require_login();
require_once $ROOT . '/config/db.php';

/** @var PDO $pdo */
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function json_out($data, int $code=200): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
$user = function_exists('current_user') ? current_user() : ($_SESSION['user'] ?? []);
$UNIDAD_ID = (int)($user['unidad_id'] ?? $_SESSION['unidad_id'] ?? 1);
$USER_DNI  = (string)($user['dni'] ?? $_SESSION['dni'] ?? '');

$PUBLIC_URL = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '')), '/'); // /ea/public/informatica
$APP_URL    = rtrim(dirname($PUBLIC_URL), '/'); // /ea/public
$APP_URL    = rtrim(dirname($APP_URL), '/');    // /ea
$ASSETS_URL = ($APP_URL === '' ? '' : $APP_URL) . '/assets';

$IMG_BG     = $ASSETS_URL . '/img/fondo.png';
$ESCUDO     = $ASSETS_URL . '/img/ecmilm.png';
$URL_VOLVER = ($APP_URL === '' ? '' : $APP_URL) . '/public/informatica/informatica.php';
$FAVICON    = $ASSETS_URL . '/img/favicon.ico';

/* =========================================================
   Tabla (fallback) si NO existe
   ========================================================= */
try {
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS calendario_tareas (
      id INT AUTO_INCREMENT PRIMARY KEY,
      unidad_id INT NOT NULL,
      titulo VARCHAR(160) NOT NULL,
      descripcion TEXT NULL,
      start_at DATETIME NOT NULL,
      end_at DATETIME NULL,
      all_day TINYINT(1) NOT NULL DEFAULT 0,
      estado ENUM('pendiente','realizado') NOT NULL DEFAULT 'pendiente',
      prioridad ENUM('baja','media','alta') NOT NULL DEFAULT 'media',
      creado_por_dni VARCHAR(24) NULL,
      created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX idx_unidad (unidad_id),
      INDEX idx_start (start_at),
      INDEX idx_estado (estado)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");
} catch (Throwable $ex) {
  // si falla por permisos/engine, seguimos; pero la API puede fallar si no existe.
}

/* =========================================================
   API
   ========================================================= */
if (isset($_GET['api'])) {
  $api = (string)$_GET['api'];

  try {
    if ($api === 'list') {
      // FullCalendar manda range opcional (start/end) en ISO
      $start = (string)($_GET['start'] ?? '');
      $end   = (string)($_GET['end'] ?? '');

      $where = "unidad_id = :u";
      $params = [':u' => $UNIDAD_ID];

      // Si viene rango, filtramos (mejor performance)
      if ($start !== '' && $end !== '') {
        $where .= " AND start_at < :end AND (end_at IS NULL OR end_at >= :start)";
        // Normalizamos a DATETIME (si viene con Z, lo dejamos como string; MySQL lo parsea en muchos casos)
        $params[':start'] = $start;
        $params[':end']   = $end;
      }

      $st = $pdo->prepare("
        SELECT id, titulo, descripcion, start_at, end_at, all_day, estado, prioridad, creado_por_dni
        FROM calendario_tareas
        WHERE $where
        ORDER BY start_at ASC, id ASC
      ");
      $st->execute($params);
      $rows = $st->fetchAll(PDO::FETCH_ASSOC);

      // Adaptar a formato FullCalendar
      $events = [];
      foreach ($rows as $r) {
        $events[] = [
          'id' => (string)$r['id'],
          'title' => (string)$r['titulo'],
          'start' => (string)$r['start_at'],
          'end' => $r['end_at'] ? (string)$r['end_at'] : null,
          'allDay' => ((int)$r['all_day'] === 1),
          'extendedProps' => [
            'descripcion' => (string)($r['descripcion'] ?? ''),
            'estado' => (string)$r['estado'],
            'prioridad' => (string)$r['prioridad'],
            'creado_por_dni' => (string)($r['creado_por_dni'] ?? ''),
          ],
        ];
      }

      json_out(['ok'=>true,'events'=>$events,'unidad_id'=>$UNIDAD_ID]);
    }

    if ($api === 'create' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
      $in = json_decode(file_get_contents('php://input'), true) ?: [];

      $titulo = trim((string)($in['titulo'] ?? ''));
      $descripcion = trim((string)($in['descripcion'] ?? ''));
      $start_at = trim((string)($in['start_at'] ?? ''));
      $end_at = trim((string)($in['end_at'] ?? ''));
      $all_day = (int)($in['all_day'] ?? 0);
      $prioridad = (string)($in['prioridad'] ?? 'media');

      if ($titulo === '') json_out(['ok'=>false,'error'=>'Título requerido'], 400);
      if ($start_at === '') json_out(['ok'=>false,'error'=>'Fecha/hora de inicio requerida'], 400);

      if (!in_array($prioridad, ['baja','media','alta'], true)) $prioridad = 'media';

      // Si all_day=1 y no hay end, no obligamos end. Si hay end, se usa.
      $st = $pdo->prepare("
        INSERT INTO calendario_tareas (unidad_id, titulo, descripcion, start_at, end_at, all_day, estado, prioridad, creado_por_dni)
        VALUES (?,?,?,?,?,?, 'pendiente', ?, ?)
      ");
      $st->execute([
        $UNIDAD_ID,
        $titulo,
        ($descripcion === '' ? null : $descripcion),
        $start_at,
        ($end_at === '' ? null : $end_at),
        ($all_day ? 1 : 0),
        $prioridad,
        ($USER_DNI !== '' ? $USER_DNI : null),
      ]);

      json_out(['ok'=>true,'id'=>(int)$pdo->lastInsertId()]);
    }

    if ($api === 'update' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
      $in = json_decode(file_get_contents('php://input'), true) ?: [];
      $id = (int)($in['id'] ?? 0);
      if ($id <= 0) json_out(['ok'=>false,'error'=>'id requerido'], 400);

      // Asegurar pertenencia a unidad
      $st0 = $pdo->prepare("SELECT COUNT(*) FROM calendario_tareas WHERE id=? AND unidad_id=?");
      $st0->execute([$id, $UNIDAD_ID]);
      if ((int)$st0->fetchColumn() <= 0) json_out(['ok'=>false,'error'=>'No permitido'], 403);

      $titulo = trim((string)($in['titulo'] ?? ''));
      $descripcion = trim((string)($in['descripcion'] ?? ''));
      $start_at = trim((string)($in['start_at'] ?? ''));
      $end_at = trim((string)($in['end_at'] ?? ''));
      $all_day = (int)($in['all_day'] ?? 0);
      $estado = (string)($in['estado'] ?? 'pendiente');
      $prioridad = (string)($in['prioridad'] ?? 'media');

      if ($titulo === '') json_out(['ok'=>false,'error'=>'Título requerido'], 400);
      if ($start_at === '') json_out(['ok'=>false,'error'=>'Inicio requerido'], 400);
      if (!in_array($estado, ['pendiente','realizado'], true)) $estado = 'pendiente';
      if (!in_array($prioridad, ['baja','media','alta'], true)) $prioridad = 'media';

      $st = $pdo->prepare("
        UPDATE calendario_tareas
        SET titulo=?, descripcion=?, start_at=?, end_at=?, all_day=?, estado=?, prioridad=?
        WHERE id=? AND unidad_id=?
      ");
      $st->execute([
        $titulo,
        ($descripcion === '' ? null : $descripcion),
        $start_at,
        ($end_at === '' ? null : $end_at),
        ($all_day ? 1 : 0),
        $estado,
        $prioridad,
        $id,
        $UNIDAD_ID
      ]);

      json_out(['ok'=>true]);
    }

    if ($api === 'move' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
      // Drag & drop desde FullCalendar: actualizar start/end/allDay
      $in = json_decode(file_get_contents('php://input'), true) ?: [];
      $id = (int)($in['id'] ?? 0);
      if ($id <= 0) json_out(['ok'=>false,'error'=>'id requerido'], 400);

      $st0 = $pdo->prepare("SELECT COUNT(*) FROM calendario_tareas WHERE id=? AND unidad_id=?");
      $st0->execute([$id, $UNIDAD_ID]);
      if ((int)$st0->fetchColumn() <= 0) json_out(['ok'=>false,'error'=>'No permitido'], 403);

      $start_at = trim((string)($in['start_at'] ?? ''));
      $end_at   = trim((string)($in['end_at'] ?? ''));
      $all_day  = (int)($in['all_day'] ?? 0);

      if ($start_at === '') json_out(['ok'=>false,'error'=>'start_at requerido'], 400);

      $st = $pdo->prepare("
        UPDATE calendario_tareas
        SET start_at=?, end_at=?, all_day=?
        WHERE id=? AND unidad_id=?
      ");
      $st->execute([$start_at, ($end_at===''?null:$end_at), ($all_day?1:0), $id, $UNIDAD_ID]);

      json_out(['ok'=>true]);
    }

    if ($api === 'delete' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
      $in = json_decode(file_get_contents('php://input'), true) ?: [];
      $id = (int)($in['id'] ?? 0);
      if ($id <= 0) json_out(['ok'=>false,'error'=>'id requerido'], 400);

      $st = $pdo->prepare("DELETE FROM calendario_tareas WHERE id=? AND unidad_id=?");
      $st->execute([$id, $UNIDAD_ID]);

      json_out(['ok'=>true]);
    }

    json_out(['ok'=>false,'error'=>'API no encontrada'], 404);

  } catch (Throwable $ex) {
    json_out(['ok'=>false,'error'=>$ex->getMessage()], 500);
  }
}

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Informática · Calendario</title>
  <link rel="icon" href="<?= e($FAVICON) ?>">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

  <!-- FullCalendar (CDN) -->
  <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>

  <style>
    :root{
      --glass: rgba(15,17,23,.92);
      --glass2: rgba(2,6,23,.68);
      --stroke: rgba(148,163,184,.28);
      --text: #e5e7eb;
      --muted: rgba(203,213,245,.88);
      --brand: #0ea5e9;
      --ok:#22c55e;
      --warn:#fbbf24;
      --danger:#ef4444;
    }
    html,body{height:100%;}
    body{
      margin:0;
      color:var(--text);
      font-family:system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,sans-serif;
      background:#000;
    }
    .page-bg{
      position:fixed; inset:0; z-index:-2; pointer-events:none;
      background:
        linear-gradient(160deg, rgba(0,0,0,.88) 0%, rgba(0,0,0,.60) 55%, rgba(0,0,0,.88) 100%),
        url("<?= e($IMG_BG) ?>") center/cover no-repeat;
      background-attachment: fixed, fixed;
    }
    .container-main{ max-width: 1600px; margin: auto; padding: 14px; }
    .hero{
      border:1px solid var(--stroke);
      background: rgba(2,6,23,.60);
      backdrop-filter: blur(8px);
      border-radius: 18px;
      box-shadow: 0 18px 40px rgba(0,0,0,.55), inset 0 1px 0 rgba(255,255,255,.04);
      padding: 12px 14px;
      display:flex; align-items:center; gap:12px;
    }
    .hero img{ width:52px; height:52px; object-fit:contain; }
    .hero h1{ font-size:1.05rem; font-weight:900; margin:0; letter-spacing:.3px; }
    .hero .sub{ font-size:.86rem; color:var(--muted); margin-top:2px; }
    .hero .right{ margin-left:auto; display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
    .btn-std{ font-weight:800; padding:.35rem .9rem; border-radius:10px; }

    .cardx{
      border:1px solid var(--stroke);
      background: var(--glass);
      border-radius: 18px;
      box-shadow: 0 18px 40px rgba(0,0,0,.55), inset 0 1px 0 rgba(255,255,255,.04);
      backdrop-filter: blur(8px);
    }
    .cardx-h{
      padding: 12px 14px;
      border-bottom: 1px solid rgba(148,163,184,.18);
      display:flex; align-items:center; justify-content:space-between; gap:10px;
      flex-wrap: wrap;
    }
    .chip{
      display:inline-flex; align-items:center; gap:.45rem;
      font-size:.74rem;
      padding:.25rem .65rem;
      border-radius:999px;
      background: rgba(255,255,255,.08);
      border:1px solid rgba(255,255,255,.10);
      letter-spacing:.08em;
      text-transform:uppercase;
      font-weight:900;
    }
    .muted{ color: var(--muted); font-size:.86rem; }

    .form-label{ color: var(--muted); font-weight:800; font-size:.82rem; margin-bottom:.35rem; }
    .form-control, .form-select, textarea{
      background: rgba(2,6,23,.80);
      border:1px solid rgba(148,163,184,.28);
      color: var(--text);
      border-radius: 12px;
    }
    .form-control:focus, .form-select:focus, textarea:focus{
      border-color: rgba(14,165,233,.85);
      box-shadow: 0 0 0 .2rem rgba(14,165,233,.18);
      background: rgba(2,6,23,.86);
      color: var(--text);
    }

    .btn-pill{
      display:inline-flex; align-items:center; justify-content:center;
      gap:.4rem;
      padding:.55rem 1rem;
      border-radius:999px;
      font-size:.86rem;
      font-weight:900;
      text-decoration:none;
      background: var(--brand);
      color:#021827;
      border:none;
      box-shadow: 0 8px 22px rgba(14,165,233,.45);
      white-space: nowrap;
    }
    .btn-pill:hover{ filter: brightness(1.05); }
    .btn-pill--green{ background: var(--ok); color:#052e16; box-shadow:0 8px 22px rgba(34,197,94,.35); }
    .btn-pill--amber{ background: var(--warn); color:#78350f; box-shadow:0 8px 22px rgba(251,191,36,.32); }
    .btn-pill--red{ background: var(--danger); color:#450a0a; box-shadow:0 8px 22px rgba(239,68,68,.30); }

    /* FullCalendar dark-ish */
    .fc{ color: var(--text); }
    .fc .fc-toolbar-title{ font-size: 1.05rem; font-weight: 1000; letter-spacing:.2px; }
    .fc .fc-button{
      border-radius: 12px !important;
      font-weight: 900 !important;
      border: 1px solid rgba(148,163,184,.30) !important;
      background: rgba(2,6,23,.70) !important;
      color: var(--text) !important;
    }
    .fc .fc-button-primary:not(:disabled).fc-button-active{
      background: rgba(14,165,233,.22) !important;
      border-color: rgba(14,165,233,.55) !important;
    }
    .fc-theme-standard .fc-scrollgrid{
      border: 1px solid rgba(148,163,184,.20);
      border-radius: 16px;
      overflow: hidden;
      background: rgba(2,6,23,.40);
    }
    .fc-theme-standard td, .fc-theme-standard th{
      border-color: rgba(148,163,184,.14);
    }
    .fc .fc-daygrid-day-number{ color: rgba(229,231,235,.85); font-weight: 900; }
    .fc .fc-col-header-cell-cushion{ color: rgba(203,213,245,.90); font-weight: 900; }

    .modal-content{
      border-radius:18px;
      background: rgba(15,17,23,.98);
      border:1px solid rgba(148,163,184,.25);
      color: var(--text);
    }
    .modal-header,.modal-footer{ border-color: rgba(148,163,184,.16); }

    .pill{
      display:inline-flex; align-items:center; gap:.35rem;
      border-radius:999px;
      padding:.2rem .55rem;
      font-size:.76rem;
      font-weight: 1000;
      border:1px solid rgba(148,163,184,.20);
      background: rgba(255,255,255,.06);
    }
    .pill--ok{ border-color: rgba(34,197,94,.35); background: rgba(34,197,94,.12); }
    .pill--warn{ border-color: rgba(251,191,36,.35); background: rgba(251,191,36,.12); }
    .pill--muted{ color: rgba(203,213,245,.90); }
  </style>
</head>

<body>
<div class="page-bg"></div>

<div class="container-main">
  <div class="hero mb-3">
    <img src="<?= e($ESCUDO) ?>" alt="Escudo">
    <div>
      <h1>INFORMÁTICA · CALENDARIO</h1>
      <div class="sub">Tareas/eventos por unidad · Click en un día para crear · Click en evento para editar · Arrastrar para mover</div>
    </div>
    <div class="right">
      <a class="btn btn-success btn-sm btn-std" href="<?= e($URL_VOLVER) ?>">Volver</a>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-12 col-xl-3">
      <div class="cardx">
        <div class="cardx-h">
          <div>
            <div style="font-weight:1000; letter-spacing:.03em;">Acciones</div>
            <div class="muted">Creá/gestioná eventos</div>
          </div>
          <span class="chip">UNIDAD <?= (int)$UNIDAD_ID ?></span>
        </div>
        <div class="p-3">
          <div class="d-grid gap-2">
            <button class="btn-pill btn-pill--green" onclick="openCreateNow()">+ Nueva tarea (ahora)</button>
            <button class="btn-pill" onclick="calendar.today()">Ir a hoy</button>
          </div>

          <hr style="border-color: rgba(148,163,184,.18); opacity:1;" class="my-3">

          <div style="font-weight:1000;">Leyenda</div>
          <div class="mt-2 d-flex flex-column gap-2">
            <div class="pill pill--warn"><span>●</span> Pendiente</div>
            <div class="pill pill--ok"><span>●</span> Realizado</div>
            <div class="pill pill--muted">Prioridad: baja / media / alta</div>
          </div>

          <div class="muted mt-3">
            Tip: arrastrá un evento para reprogramar. Si querés “todo el día”, marcá All-day.
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-9">
      <div class="cardx">
        <div class="cardx-h">
          <div>
            <div style="font-weight:1000; letter-spacing:.03em;">Calendario</div>
            <div class="muted">Vista mensual/semanal/diaria</div>
          </div>
          <span class="chip">FullCalendar</span>
        </div>
        <div class="p-3">
          <div id="cal"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal Crear/Editar -->
<div class="modal fade" id="modalEvt" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="evtTitle">Tarea</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="evt_id" value="0">

        <div class="row g-2">
          <div class="col-12">
            <label class="form-label">Título (obligatorio)</label>
            <input class="form-control" id="evt_titulo" placeholder="Ej: Revisar switch / Parte semanal / Backup VM">
          </div>

          <div class="col-12">
            <label class="form-label">Descripción</label>
            <textarea class="form-control" id="evt_desc" rows="4" placeholder="Notas, responsables, enlace, etc."></textarea>
          </div>

          <div class="col-12 col-md-6">
            <label class="form-label">Inicio</label>
            <input class="form-control" id="evt_start" type="datetime-local">
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">Fin (opcional)</label>
            <input class="form-control" id="evt_end" type="datetime-local">
          </div>

          <div class="col-12 col-md-4">
            <label class="form-label">All-day</label>
            <select class="form-select" id="evt_allDay">
              <option value="0">No</option>
              <option value="1">Sí</option>
            </select>
          </div>

          <div class="col-12 col-md-4">
            <label class="form-label">Estado</label>
            <select class="form-select" id="evt_estado">
              <option value="pendiente">Pendiente</option>
              <option value="realizado">Realizado</option>
            </select>
          </div>

          <div class="col-12 col-md-4">
            <label class="form-label">Prioridad</label>
            <select class="form-select" id="evt_prioridad">
              <option value="baja">Baja</option>
              <option value="media" selected>Media</option>
              <option value="alta">Alta</option>
            </select>
          </div>
        </div>

        <div class="muted mt-2">
          Si marcás “All-day”, el evento se muestra como día completo. El fin es opcional.
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-light" type="button" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-danger" type="button" onclick="deleteEvt()" id="btnEvtDelete" style="display:none;">Eliminar</button>
        <button class="btn btn-success" type="button" onclick="saveEvt()">Guardar</button>
      </div>
    </div>
  </div>
</div>

<script>
const API = (name, params={}) => {
  const url = new URL(window.location.href);
  url.searchParams.set('api', name);
  for (const [k,v] of Object.entries(params)) url.searchParams.set(k, v);
  return url.toString();
};
async function jget(url){
  const r = await fetch(url, {credentials:'same-origin'});
  const j = await r.json();
  if(!j.ok) throw new Error(j.error || 'Error');
  return j;
}
async function jpost(url, data){
  const r = await fetch(url, {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify(data || {}),
    credentials:'same-origin'
  });
  const j = await r.json();
  if(!j.ok) throw new Error(j.error || 'Error');
  return j;
}

function toLocalInputValue(dateObj){
  const pad = (n) => String(n).padStart(2,'0');
  const y = dateObj.getFullYear();
  const m = pad(dateObj.getMonth()+1);
  const d = pad(dateObj.getDate());
  const hh = pad(dateObj.getHours());
  const mm = pad(dateObj.getMinutes());
  return `${y}-${m}-${d}T${hh}:${mm}`;
}

function colorFor(estado, prioridad){
  // No fijamos paleta exacta; usamos tonos por estado/prioridad con rgba
  if(estado === 'realizado'){
    return 'rgba(34,197,94,.30)';
  }
  // pendiente
  if(prioridad === 'alta') return 'rgba(239,68,68,.35)';
  if(prioridad === 'baja') return 'rgba(14,165,233,.30)';
  return 'rgba(251,191,36,.30)'; // media
}

let calendar = null;

function openModal(){
  const m = new bootstrap.Modal(document.getElementById('modalEvt'));
  m.show();
}
function closeModal(){
  const el = document.getElementById('modalEvt');
  const inst = bootstrap.Modal.getInstance(el);
  if(inst) inst.hide();
}

function fillModalForCreate(startDate, endDate=null, allDay=false){
  document.getElementById('evtTitle').textContent = 'Nueva tarea';
  document.getElementById('evt_id').value = '0';
  document.getElementById('btnEvtDelete').style.display = 'none';

  document.getElementById('evt_titulo').value = '';
  document.getElementById('evt_desc').value = '';
  document.getElementById('evt_estado').value = 'pendiente';
  document.getElementById('evt_prioridad').value = 'media';
  document.getElementById('evt_allDay').value = allDay ? '1' : '0';

  document.getElementById('evt_start').value = toLocalInputValue(startDate);
  document.getElementById('evt_end').value = endDate ? toLocalInputValue(endDate) : '';
}

function fillModalForEdit(evt){
  document.getElementById('evtTitle').textContent = 'Editar tarea';
  document.getElementById('evt_id').value = String(evt.id || '0');
  document.getElementById('btnEvtDelete').style.display = 'inline-block';

  document.getElementById('evt_titulo').value = evt.title || '';
  document.getElementById('evt_desc').value = (evt.extendedProps && evt.extendedProps.descripcion) ? evt.extendedProps.descripcion : '';
  document.getElementById('evt_estado').value = (evt.extendedProps && evt.extendedProps.estado) ? evt.extendedProps.estado : 'pendiente';
  document.getElementById('evt_prioridad').value = (evt.extendedProps && evt.extendedProps.prioridad) ? evt.extendedProps.prioridad : 'media';
  document.getElementById('evt_allDay').value = evt.allDay ? '1' : '0';

  const s = evt.start ? new Date(evt.start) : new Date();
  const e = evt.end ? new Date(evt.end) : null;

  document.getElementById('evt_start').value = toLocalInputValue(s);
  document.getElementById('evt_end').value = e ? toLocalInputValue(e) : '';
}

function openCreateNow(){
  const now = new Date();
  const end = new Date(now.getTime() + 60*60*1000);
  fillModalForCreate(now, end, false);
  openModal();
}

async function saveEvt(){
  const id = parseInt(document.getElementById('evt_id').value || '0', 10);

  const titulo = (document.getElementById('evt_titulo').value || '').trim();
  const descripcion = (document.getElementById('evt_desc').value || '').trim();
  const start_at = (document.getElementById('evt_start').value || '').trim();
  const end_at = (document.getElementById('evt_end').value || '').trim();
  const all_day = parseInt(document.getElementById('evt_allDay').value || '0', 10) ? 1 : 0;
  const estado = (document.getElementById('evt_estado').value || 'pendiente').trim();
  const prioridad = (document.getElementById('evt_prioridad').value || 'media').trim();

  if(!titulo){ alert('Título requerido'); return; }
  if(!start_at){ alert('Inicio requerido'); return; }

  try{
    if(id > 0){
      await jpost(API('update'), {id, titulo, descripcion, start_at, end_at, all_day, estado, prioridad});
    } else {
      await jpost(API('create'), {titulo, descripcion, start_at, end_at, all_day, prioridad});
    }
    closeModal();
    calendar.refetchEvents();
  }catch(err){
    alert('Error: ' + err.message);
  }
}

async function deleteEvt(){
  const id = parseInt(document.getElementById('evt_id').value || '0', 10);
  if(id <= 0){ alert('Evento inválido'); return; }
  if(!confirm('¿Eliminar esta tarea?')) return;

  try{
    await jpost(API('delete'), {id});
    closeModal();
    calendar.refetchEvents();
  }catch(err){
    alert('Error: ' + err.message);
  }
}

document.addEventListener('DOMContentLoaded', () => {
  const calEl = document.getElementById('cal');

  calendar = new FullCalendar.Calendar(calEl, {
    initialView: 'dayGridMonth',
    height: 'auto',
    locale: 'es',
    firstDay: 1,
    headerToolbar: {
      left: 'prev,next today',
      center: 'title',
      right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
    },
    selectable: true,
    editable: true,
    eventStartEditable: true,
    eventDurationEditable: true,

    // Fuente de eventos (API)
    events: async (info, success, failure) => {
      try{
        const j = await jget(API('list', {start: info.startStr, end: info.endStr}));
        const evs = (j.events || []).map(ev => {
          const estado = ev.extendedProps?.estado || 'pendiente';
          const prioridad = ev.extendedProps?.prioridad || 'media';
          return {
            ...ev,
            backgroundColor: colorFor(estado, prioridad),
            borderColor: 'rgba(148,163,184,.25)',
            textColor: 'rgba(229,231,235,.95)',
          };
        });
        success(evs);
      }catch(err){
        failure(err);
      }
    },

    dateClick: (info) => {
      // crear all-day en ese día
      const start = new Date(info.date);
      start.setHours(9,0,0,0);
      const end = new Date(start.getTime() + 60*60*1000);
      fillModalForCreate(start, end, false);
      openModal();
    },

    select: (selInfo) => {
      // selección de rango (all-day o timegrid)
      const start = new Date(selInfo.start);
      const end = new Date(selInfo.end);
      fillModalForCreate(start, end, selInfo.allDay);
      openModal();
    },

    eventClick: (clickInfo) => {
      fillModalForEdit(clickInfo.event);
      openModal();
    },

    eventDrop: async (dropInfo) => {
      try{
        await jpost(API('move'), {
          id: parseInt(dropInfo.event.id,10),
          start_at: dropInfo.event.startStr,
          end_at: dropInfo.event.endStr || '',
          all_day: dropInfo.event.allDay ? 1 : 0
        });
      }catch(err){
        alert('Error al mover: ' + err.message);
        dropInfo.revert();
      }
    },

    eventResize: async (resizeInfo) => {
      try{
        await jpost(API('move'), {
          id: parseInt(resizeInfo.event.id,10),
          start_at: resizeInfo.event.startStr,
          end_at: resizeInfo.event.endStr || '',
          all_day: resizeInfo.event.allDay ? 1 : 0
        });
      }catch(err){
        alert('Error al redimensionar: ' + err.message);
        resizeInfo.revert();
      }
    }
  });

  calendar.render();
});
</script>
</body>
</html>
