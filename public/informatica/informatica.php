<?php
// public/Informatica.php — Panel de Informática (EC MIL M)
declare(strict_types=1);

require_once __DIR__ . '/../../auth/bootstrap.php';
require_login();
require_once __DIR__ . '/../../config/db.php';

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$user = function_exists('current_user') ? current_user() : null;

/* =========================
   Mono-unidad (EC MIL M)
   ========================= */
$unidadActiva = 2;

// Branding fijo (como venís trabajando para EC MIL M)
$PUBLIC_URL = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'])), '/'); // /ea/public
$APP_URL    = rtrim(str_replace('\\','/', dirname($PUBLIC_URL)), '/'); // /ea
$ASSETS_URL = ($APP_URL === '' ? '' : $APP_URL) . '/../assets'; // ✅ FIX (antes ../assets)

$IMG_BG   = $ASSETS_URL . '/img/fondo.png';
$ESCUDO   = $ASSETS_URL . '/img/ecmilm.png';
$FAVICON  = $ASSETS_URL . '/img/ecmilm.png';

$NOMBRE  = 'Escuela Militar de Montaña';
$LEYENDA = 'La Montaña Nos Une';

// Info usuario (tolerante)
$display = (string)($user['display_name'] ?? $user['nombre_completo'] ?? $user['full_name'] ?? '');
$dni     = (string)($user['dni'] ?? $user['username'] ?? '');
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Informática · <?= e($NOMBRE) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="icon" href="<?= e($FAVICON) ?>">

<!-- ✅ FIX: usar assets calculado -->
<link rel="stylesheet" href="<?= e($ASSETS_URL) ?>/css/theme-602.css">

<style>
  html,body{height:100%;}
  body{
    margin:0;
    color:#e5e7eb;
    background:#000;
    font-family:system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,sans-serif;
  }

  /* Fondo oscuro como login/menu */
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
  }
  .btn-std{
    font-weight:700;
    padding:.35rem .9rem;
    border-radius:10px;
  }

  /* Panels / cards */
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
    margin-bottom:10px;
  }
  .panel-sub{
    font-size:.88rem;
    color:#cbd5f5;
    opacity:.9;
    margin-bottom:14px;
  }

  .kpi-grid{
    display:flex; flex-wrap:wrap; gap:12px;
  }
  .kpi{
    flex:1 1 220px;
    min-width:220px;
    background:rgba(2,6,23,.65);
    border:1px solid rgba(148,163,184,.30);
    border-radius:16px;
    padding:12px 12px;
  }
  .kpi-title{
    text-transform:uppercase;
    letter-spacing:.08em;
    font-size:.72rem;
    color:#9ca3af;
    font-weight:800;
  }
  .kpi-main{
    font-size:1.15rem;
    font-weight:900;
    margin-top:4px;
  }
  .kpi-sub{
    font-size:.82rem;
    color:#cbd5f5;
    opacity:.9;
  }

  .grid{
    display:flex;
    flex-wrap:wrap;
    gap:14px;
    justify-content:center;
  }

  .cardx{
    flex:1 1 320px;
    max-width:520px;
    border-radius:18px;
    padding:18px 16px 16px;
    border:1px solid rgba(148,163,184,.35);
    background:rgba(2,6,23,.75);
    box-shadow:0 14px 30px rgba(0,0,0,.55);
  }
  .cardx--green{ background:radial-gradient(circle at top left, rgba(34,197,94,.16), transparent 55%), rgba(2,6,23,.75); }
  .cardx--blue { background:radial-gradient(circle at top left, rgba(56,189,248,.18), transparent 55%), rgba(2,6,23,.75); }
  .cardx--amber{ background:radial-gradient(circle at top left, rgba(251,191,36,.16), transparent 55%), rgba(2,6,23,.75); }

  .cardx-title{ font-weight:900; font-size:1rem; margin-bottom:4px; }
  .cardx-sub{ font-size:.78rem; text-transform:uppercase; letter-spacing:.08em; color:#9ca3af; margin-bottom:10px; }
  .cardx-text{ font-size:.88rem; color:#cbd5f5; opacity:.92; min-height:42px; }

  .btn-pill{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:.35rem;
    padding:.55rem 1.2rem;
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

  .btn-pill--green{ background:#22c55e; color:#052e16; box-shadow:0 8px 22px rgba(34,197,94,.45); }
  .btn-pill--green:hover{ background:#4ade80; color:#052e16; }

  .btn-pill--amber{ background:#fbbf24; color:#78350f; box-shadow:0 8px 22px rgba(251,191,36,.45); }
  .btn-pill--amber:hover{ background:#facc15; color:#78350f; }

  .small-muted{ font-size:.82rem; color:#9ca3af; }
</style>
</head>

<body>
<div class="page-bg"></div>

<header class="brand-hero">
  <div class="hero-inner container-main">
    <img class="brand-logo" src="<?= e($ESCUDO) ?>" alt="Escudo">
    <div>
      <div class="brand-title"><?= e($NOMBRE) ?> · Informática</div>
      <div class="brand-sub">“<?= e($LEYENDA) ?>”</div>
    </div>

    <div class="user-info">
      <?php if ($display !== ''): ?>
        <div><strong><?= e($display) ?></strong></div>
      <?php endif; ?>
      <?php if ($dni !== ''): ?>
        <div class="small-muted">DNI: <?= e($dni) ?> · Unidad: <?= (int)$unidadActiva ?></div>
      <?php endif; ?>
    </div>

    <div class="header-actions">
      <a class="btn btn-success btn-sm btn-std" href="../inicio.php">Volver</a>
      <a class="btn btn-success btn-sm btn-std" href="../logout.php">Cerrar sesión</a>
    </div>
  </div>
</header>

<div class="page-wrap">
  <div class="container-main">

    <div class="panel mb-3">
      <div class="panel-title">Centro de control · Informática</div>
      <div class="panel-sub">
        Panel unificado para documentación, inventarios, red, conectividad, control de ingreso/egreso y partes.
      </div>

      <div class="kpi-grid">
        <div class="kpi">
          <div class="kpi-title">Computadoras (Total)</div>
          <div class="kpi-main">—</div>
          <div class="kpi-sub">Por implementar: activos + depósito.</div>
        </div>
        <div class="kpi">
          <div class="kpi-title">Herramientas (Inventario)</div>
          <div class="kpi-main">—</div>
          <div class="kpi-sub">Stock, préstamos, estado.</div>
        </div>
        <div class="kpi">
          <div class="kpi-title">Internet / Enlaces</div>
          <div class="kpi-main">—</div>
          <div class="kpi-sub">Proveedores por edificio/área.</div>
        </div>
        <div class="kpi">
          <div class="kpi-title">Actividades Pendientes</div>
          <div class="kpi-main">—</div>
          <div class="kpi-sub">Tipo calendario + tareas.</div>
        </div>
      </div>
    </div>

    <div class="panel">
      <div class="panel-title">Módulos</div>
      <div class="panel-sub">Entrá a cada módulo. Después los vamos construyendo uno por uno (sin mezclar datos).</div>

      <div class="grid">

        <div class="cardx cardx--blue">
          <div class="cardx-title">Documentación técnica</div>
          <div class="cardx-sub">Procedimientos · Manuales · Notas</div>
          <div class="cardx-text">
            Repositorio interno de documentación de informática: políticas, instructivos, actas y evidencias.
          </div>
          <a class="btn-pill" href="info_documentacion.php">Abrir documentación</a>
        </div>

        <div class="cardx cardx--green">
          <div class="cardx-title">Inventarios</div>
          <div class="cardx-sub">Activos · Depósito · Estado</div>
          <div class="cardx-text">
            Alta/edición de equipos, ubicación por edificio/área, estado, asignación, y depósito.
          </div>
          <a class="btn-pill btn-pill--green" href="informatica_inventarios.php">Abrir inventario</a>
        </div>

        <div class="cardx cardx--green">
          <div class="cardx-title">Red e IP por computadora</div>
          <div class="cardx-sub">Direcciones · Edificios · Planos</div>
          <div class="cardx-text">
            Mapa de red: IP asignada por equipo, rangos por edificio, plano/diagrama y enlaces.
          </div>

          <!-- ✅ CAMBIO: apunta al nuevo módulo -->
          <a class="btn-pill btn-pill--green" href="informatica_red.php">Abrir red</a>
        </div>

        <div class="cardx cardx--blue">
          <div class="cardx-title">Inventario de herramientas</div>
          <div class="cardx-sub">Stock · Préstamos · Mantenimiento</div>
          <div class="cardx-text">
            Control de herramientas: disponibilidad, préstamos a personal, estado y reposición.
          </div>
          <a class="btn-pill" href="info_herramientas.php">Abrir herramientas</a>
        </div>

        <div class="cardx cardx--blue">
          <div class="cardx-title">Internet y proveedores</div>
          <div class="cardx-sub">Qué internet hay por lugar</div>
          <div class="cardx-text">
            Proveedores, tipo de enlace, velocidad, respaldo, y cobertura por edificio/área.
          </div>
          <a class="btn-pill" href="info_internet.php">Abrir conectividad</a>
        </div>

        <div class="cardx cardx--amber">
          <div class="cardx-title">Necesidades de las áreas</div>
          <div class="cardx-sub">Pedidos · Prioridades · Seguimiento</div>
          <div class="cardx-text">
            Registro de requerimientos: necesidades por área, prioridad, responsable y estado.
          </div>
          <a class="btn-pill btn-pill--amber" href="info_necesidades.php">Abrir necesidades</a>
        </div>

        <div class="cardx cardx--amber">
          <div class="cardx-title">Huella digital (ingreso/egreso)</div>
          <div class="cardx-sub">Agentes civiles · Control</div>
          <div class="cardx-text">
            Registro y consulta de marcaciones. Integración futura con el dispositivo/lector o carga manual.
          </div>
          <a class="btn-pill btn-pill--amber" href="info_huella.php">Abrir control</a>
        </div>

        <div class="cardx cardx--green">
          <div class="cardx-title">Actividades & Calendario</div>
          <div class="cardx-sub">Pendientes · Realizadas</div>
          <div class="cardx-text">
            Agenda de tareas: por hacer, en proceso, realizadas, con vista calendario y reportes.
          </div>
          <a class="btn-pill btn-pill--green" href="../calendario.php">Abrir calendario</a>
        </div>

        <div class="cardx cardx--green">
          <div class="cardx-title">Parte de presentes (PDF)</div>
          <div class="cardx-sub">Innovador · Imprimible</div>
          <div class="cardx-text">
            Generar parte desde el listado real de personal. Selección rápida y exportación a PDF.
          </div>
          <a class="btn-pill btn-pill--green" href="info_parte_presentes.php">Crear parte</a>
        </div>

        <div class="cardx cardx--blue">
          <div class="cardx-title">Gestión del sistema</div>
          <div class="cardx-sub">Accesos administrativos</div>
          <div class="cardx-text">
            Accesos directos a administración: usuarios, permisos, tablas, documentos, configuración.
          </div>
          <a class="btn-pill" href="../admin/administrar_gestiones.php">Abrir gestiones</a>
        </div>

      </div>

      <div class="mt-3 small-muted">
        Nota: los módulos <code>info_*.php</code> están pensados para crearlos como páginas separadas, así tu panel queda limpio y ordenado.
      </div>
    </div>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
