<?php
// public/rol_combate.php — Rol de Combate B Com 602 (vista por secciones)
declare(strict_types=1);

require_once __DIR__ . '/../auth/bootstrap.php';
require_login();
require_once __DIR__ . '/../config/db.php';

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* ===== Usuario / permisos ===== */
$user = function_exists('current_user') ? current_user() : ($_SESSION['user'] ?? []);

// ADAPTAR ESTAS CLAVES A TU CPS SI FUERA NECESARIO (para el botón "Volver a áreas")
$areaSesion = strtoupper(trim((string)($user['area'] ?? '')));   // ej: 'S1' o 'S3'
$esS1       = ($areaSesion === 'S1');
$esS3       = ($areaSesion === 'S3');

// -------- Permisos reales tomados de roles_locales ----------
$rolApp      = '';
$areasAcceso = '';

try {
    // ADAPTAR: si tu campo en roles_locales no es usuario_id o tu sesión no usa 'id',
    // cambiá estas 2 cosas por las reales.
    $usuarioId = (int)($user['id'] ?? 0);

    if ($usuarioId > 0 && isset($pdo)) {
        $sqlRoles = "SELECT rol_app, areas_acceso
                     FROM roles_locales
                     WHERE usuario_id = :uid
                     LIMIT 1";
        $stmtRoles = $pdo->prepare($sqlRoles);
        $stmtRoles->execute([':uid' => $usuarioId]);
        $rowRol = $stmtRoles->fetch(PDO::FETCH_ASSOC);

        if ($rowRol) {
            $rolApp      = strtoupper(trim((string)($rowRol['rol_app'] ?? '')));
            $areasAcceso = strtoupper(trim((string)($rowRol['areas_acceso'] ?? '')));
        }
    }
} catch (Throwable $e) {
    // En caso de error, se cae a solo lectura (fail-safe)
    $rolApp = '';
    $areasAcceso = '';
}

// Normalizamos flags de permiso
$esAdminApp = ($rolApp === 'ADMIN');

// si áreas_acceso es un string tipo "S1,S3,GRAL" o un JSON con esos textos,
// igual usamos strpos para detectar GRAL / S3.
$tieneGral  = (strpos($areasAcceso, 'GRAL') !== false);
$tieneS3Acc = (strpos($areasAcceso, 'S3')   !== false);

/*
 * 🔐 LÓGICA DE PERMISOS:
 *  - ADMIN de la app
 *  - Cualquiera que tenga GRAL en áreas_acceso
 *  - Cualquiera que tenga S3 en áreas_acceso
 *  - O que su área de sesión sea S3
 */
$puedeEditar = $esAdminApp || $tieneGral || $tieneS3Acc || $esS3;
$soloLectura = !$puedeEditar;

// URL de "Volver a áreas" según el área principal de la sesión
if ($esS1) {
    $areasUrl = 'areas_s1.php';
} elseif ($esS3) {
    $areasUrl = 'areas_s3.php';
} else {
    $areasUrl = 'elegir_inicio.php';
}

/* ===== Rutas / assets ===== */
$PUBLIC_URL = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'])), '/');
$APP_URL    = rtrim(str_replace('\\','/', dirname($PUBLIC_URL)), '/');
$ASSETS_URL = ($APP_URL === '' ? '' : $APP_URL) . '/assets';
$IMG_BG     = $ASSETS_URL . '/img/fondo.png';
$ESCUDO     = $ASSETS_URL . '/img/escudo_bcom602.png';

/* ===== Secciones / elementos ===== */
$RC_ELEMENTOS = [
    1 => 'Rol de Combate de la Jefatura',
    2 => 'Rol de Combate de la Plana Mayor',
    3 => 'Rol de Combate de la Compañía Comando y Servicio',
    4 => 'Rol de Combate de la Compañía CCIG del EMGE',
    5 => 'Rol de Combate de la Compañía Redes y Sistemas',
    6 => 'Rol de Combate de la Compañía Infraestructura de Red',
    7 => 'Rol de Combate de la Compañía Telepuerto Satelital',
    8 => 'Personal en Comisión',
];
$RC_SECCIONES = $RC_ELEMENTOS + [
    9 => 'Listado completo de personal (todos los elementos)',
];

/* ===== Parámetros ===== */
$seccion   = isset($_GET['seccion']) ? (int)$_GET['seccion'] : 0;
$personaId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// origen opcional
$from = $_GET['from'] ?? '';
if ($from !== 's1' && $from !== 's3') {
    $from = 's1';
}

if ($seccion < 1 || $seccion > 9) {
    $seccion = 0;
}

/* ===== Buscador superior (por DNI / Nombre) ===== */
$busqDni   = trim((string)($_GET['dni'] ?? ''));
$busqNom   = trim((string)($_GET['nombre'] ?? ''));
$searchResults = [];

if ($busqDni !== '' || $busqNom !== '') {
    try {
        // Busca personas con asignación de rol de combate
        $sqlSearch = "
            SELECT
                p.id              AS personal_id,
                p.dni,
                p.grado,
                p.arma,
                p.nombre_apellido,
                rc.id             AS rol_id,
                rc.seccion,
                rc.puesto         AS rol_combate,
                rc.grupo,
                rc.subgrupo
            FROM rol_combate_asignaciones rca
            LEFT JOIN rol_combate rc
              ON rc.id = rca.rol_combate_id
            INNER JOIN personal_unidad p
              ON p.id = rca.personal_id
            WHERE (rca.hasta IS NULL OR rca.hasta >= CURDATE())
        ";

        $conds = [];
        $params = [];

        if ($busqDni !== '') {
            $conds[] = "p.dni LIKE :dni";
            $params[':dni'] = '%' . $busqDni . '%';
        }

        if ($busqNom !== '') {
            $conds[] = "p.nombre_apellido LIKE :nom";
            $params[':nom'] = '%' . $busqNom . '%';
        }

        if ($conds) {
            $sqlSearch .= " AND " . implode(" AND ", $conds);
        }

        $sqlSearch .= "
            ORDER BY
              p.grado,
              p.nombre_apellido,
              rc.seccion,
              rc.grupo,
              rc.subgrupo,
              rc.orden
            LIMIT 100
        ";

        $stmtSearch = $pdo->prepare($sqlSearch);
        $stmtSearch->execute($params);
        $searchResults = $stmtSearch->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        // Si falla la búsqueda no frenamos la página
        $searchResults = [];
    }
}

/* ===== Mensajes simples (para futuro POST normal) ===== */
$mensajeOk = '';
$mensajeError = '';

/* ===== Consulta a BD cuando hay sección seleccionada ===== */
$filasRol = [];
$listadoError = '';

if ($seccion > 0) {
    try {

        if ($seccion === 9) {
            // Tarjeta 9: listado completo de todo el personal (sin A/C)
            $sqlFallback = "
                SELECT
                    NULL                AS rol_id,
                    NULL                AS seccion,
                    NULL                AS hoja,
                    NULL                AS grupo,
                    NULL                AS subgrupo,
                    NULL                AS rol_combate,
                    NULL                AS orden,
                    NULL                AS obs_rol,

                    NULL                AS asignacion_id,
                    NULL                AS desde,
                    NULL                AS hasta,
                    NULL                AS obs_asignacion,
                    NULL                AS armamento_principal,
                    NULL                AS ni_armamento_principal,
                    NULL                AS armamento_secundario,
                    NULL                AS ni_armamento_secundario,
                    NULL                AS rol_administrativo,
                    NULL                AS vehiculo,
                    p.id                AS personal_id,

                    p.grado,
                    p.arma,
                    p.nombre_apellido
                FROM personal_unidad p
                WHERE (p.grado IS NULL OR p.grado <> 'A/C')
                ORDER BY
                  CASE p.grado
                    -- Oficiales
                    WHEN 'TG' THEN 1
                    WHEN 'GD' THEN 2
                    WHEN 'GB' THEN 3
                    WHEN 'CY' THEN 4
                    WHEN 'CR' THEN 5
                    WHEN 'TC' THEN 6
                    WHEN 'MY' THEN 7
                    WHEN 'CT' THEN 8
                    WHEN 'TP' THEN 9
                    WHEN 'TT' THEN 10
                    WHEN 'ST' THEN 11

                    -- Suboficiales
                    WHEN 'SM' THEN 12
                    WHEN 'SP' THEN 13
                    WHEN 'SA' THEN 14
                    WHEN 'SI' THEN 15
                    WHEN 'SG' THEN 16
                    WHEN 'CI' THEN 17
                    WHEN 'CB' THEN 18

                    -- Soldados
                    WHEN 'SV' THEN 19
                    WHEN 'VP' THEN 20
                    WHEN 'VS' THEN 21
                    WHEN 'VN' THEN 22

                    -- Agente civil u otros
                    WHEN 'A/C' THEN 998
                    ELSE 999
                  END,
                  p.nombre_apellido
            ";
            $stmt = $pdo->query($sqlFallback);
            $filasRol = $stmt->fetchAll(PDO::FETCH_ASSOC);

        } else {
            // Secciones 1–8: primero intento traer datos reales del Rol de Combate
            $sql = "SELECT
                        rc.id              AS rol_id,
                        rc.seccion,
                        rc.hoja,
                        rc.grupo,
                        rc.subgrupo,
                        rc.puesto          AS rol_combate,
                        rc.orden,
                        rc.observaciones   AS obs_rol,

                        rca.id             AS asignacion_id,
                        rca.desde,
                        rca.hasta,
                        rca.observaciones  AS obs_asignacion,
                        rca.armamento_principal,
                        rca.ni_armamento_principal,
                        rca.armamento_secundario,
                        rca.ni_armamento_secundario,
                        rca.rol_administrativo,
                        rca.vehiculo,
                        rca.personal_id,

                        p.grado,
                        p.arma,
                        p.nombre_apellido
                    FROM rol_combate rc
                    LEFT JOIN rol_combate_asignaciones rca
                      ON rca.rol_combate_id = rc.id
                      AND (rca.hasta IS NULL OR rca.hasta >= CURDATE())
                    LEFT JOIN personal_unidad p
                      ON p.id = rca.personal_id
                    WHERE rc.seccion = :sec
                    ORDER BY rc.orden ASC, p.grado ASC, p.nombre_apellido ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([':sec' => $seccion]);
            $filasRol = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Si no hay nada cargado todavía, muestro TODO el personal (excepto A/C)
            if (!$filasRol) {
                $sqlFallback = "
                    SELECT
                        NULL                AS rol_id,
                        :sec                AS seccion,
                        NULL                AS hoja,
                        NULL                AS grupo,
                        NULL                AS subgrupo,
                        NULL                AS rol_combate,
                        NULL                AS orden,
                        NULL                AS obs_rol,

                        NULL                AS asignacion_id,
                        NULL                AS desde,
                        NULL                AS hasta,
                        NULL                AS obs_asignacion,
                        NULL                AS armamento_principal,
                        NULL                AS ni_armamento_principal,
                        NULL                AS armamento_secundario,
                        NULL                AS ni_armamento_secundario,
                        NULL                AS rol_administrativo,
                        NULL                AS vehiculo,
                        p.id                AS personal_id,

                        p.grado,
                        p.arma,
                        p.nombre_apellido
                    FROM personal_unidad p
                    WHERE (p.grado IS NULL OR p.grado <> 'A/C')
                    ORDER BY
                      CASE p.grado
                        -- Oficiales
                        WHEN 'TG' THEN 1
                        WHEN 'GD' THEN 2
                        WHEN 'GB' THEN 3
                        WHEN 'CY' THEN 4
                        WHEN 'CR' THEN 5
                        WHEN 'TC' THEN 6
                        WHEN 'MY' THEN 7
                        WHEN 'CT' THEN 8
                        WHEN 'TP' THEN 9
                        WHEN 'TT' THEN 10
                        WHEN 'ST' THEN 11

                        -- Suboficiales
                        WHEN 'SM' THEN 12
                        WHEN 'SP' THEN 13
                        WHEN 'SA' THEN 14
                        WHEN 'SI' THEN 15
                        WHEN 'SG' THEN 16
                        WHEN 'CI' THEN 17
                        WHEN 'CB' THEN 18

                        -- Soldados
                        WHEN 'SV' THEN 19
                        WHEN 'VP' THEN 20
                        WHEN 'VS' THEN 21
                        WHEN 'VN' THEN 22

                        -- Agente civil u otros
                        WHEN 'A/C' THEN 998
                        ELSE 999
                      END,
                      p.nombre_apellido
                ";
                $stmtFallback = $pdo->prepare($sqlFallback);
                $stmtFallback->execute([':sec' => $seccion]);
                $filasRol = $stmtFallback->fetchAll(PDO::FETCH_ASSOC);
            }
        }

    } catch (Throwable $ex) {
        $listadoError = $ex->getMessage();
        $filasRol = [];
    }
}

?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Rol de Combate · Batallón de Comunicaciones 602</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/theme-602.css">
<link rel="icon" type="image/png" href="../assets/img/bcom602.png">

<style>
  body{
    background:url("<?= e($IMG_BG) ?>") no-repeat center center fixed;
    background-size:cover;
    background-attachment:fixed;
    background-color:#020617;
    color:#e5e7eb;
    font-family:system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,sans-serif;
    margin:0; padding:0;
  }
  .page-wrap{ padding:18px; }

  /* usar ancho completo de la ventana */
  .container-main{
    max-width:100%;
    margin:0 auto;
  }

  .panel{
    background:rgba(15,17,23,.94);
    border:1px solid rgba(148,163,184,.40);
    border-radius:18px;
    padding:16px 18px 18px;
    box-shadow:0 18px 40px rgba(0,0,0,.75), inset 0 1px 0 rgba(255,255,255,.05);
  }

  .panel-title{
    font-size:1.05rem;
    font-weight:800;
    margin-bottom:4px;
  }

  .panel-sub{
    font-size:.86rem;
    color:#cbd5f5;
    margin-bottom:14px;
  }

  .brand-hero{
    padding-top:10px;
    padding-bottom:10px;
  }
  .brand-hero .hero-inner{
    align-items:center;
    display:flex;
    justify-content:space-between;
    gap:12px;
  }

  .header-back{
    margin-left:auto;
    margin-right:20px;
    margin-top:4px;
    display:flex;
    gap:8px;
  }

  .brand-title{
    font-weight:800;
    font-size:1rem;
  }
  .brand-sub{
    font-size:.8rem;
    color:#9ca3af;
  }

  .table-dark-custom {
    --bs-table-bg: rgba(15,23,42,.9);
    --bs-table-striped-bg: rgba(30,64,175,.25);
    --bs-table-border-color: rgba(148,163,184,.4);
    color:#e5e7eb;
    font-size: .80rem;
  }

  .table-dark-custom th,
  .table-dark-custom td{
    padding: .30rem .35rem;
  }

  /* columnas con ancho fijo para que no se separen tanto */
  .table-rol-combate col.col-nro      { width: 4%;  }
  .table-rol-combate col.col-grado    { width: 6%;  }
  .table-rol-combate col.col-arma     { width: 7%;  }
  .table-rol-combate col.col-nombre   { width: 20%; }
  .table-rol-combate col.col-rol      { width: 10%; }
  .table-rol-combate col.col-ap       { width: 9%;  }
  .table-rol-combate col.col-niap     { width: 7%;  }
  .table-rol-combate col.col-as       { width: 9%;  }
  .table-rol-combate col.col-nias     { width: 7%;  }
  .table-rol-combate col.col-roladm   { width: 10%; }
  .table-rol-combate col.col-vehiculo { width: 6%;  }
  .table-rol-combate col.col-elemento { width: 5%;  }

  .card-section{
    background:rgba(15,23,42,.96);
    border-radius:14px;
    border:1px solid rgba(148,163,184,.35);
    padding:14px;
  }
  .card-section-title{
    font-weight:700;
    font-size:.9rem;
    color:#e2e8f0;
  }
  .card-section-sub{
    font-size:.78rem;
    color:#94a3b8;
  }
  .row-resaltada{
    background:rgba(34,197,94,.20)!important;
  }

  /* inputs/select más angostos para que entren sin scroll */
  .rc-input{
    min-width:80px;
    max-width:150px;
    font-size:.80rem;
  }

  .search-panel{
    background:rgba(15,23,42,.95);
    border-radius:12px;
    border:1px solid rgba(148,163,184,.35);
    padding:10px 12px;
    margin-bottom:10px;
  }
  .search-panel label{
    font-size:.8rem;
    margin-bottom:2px;
  }
  .search-results small{
    font-size:.78rem;
  }

  /* ====== ESTILO TARJETAS PRINCIPALES ====== */
  .rc-grid{
    display:grid;
    grid-template-columns: repeat(auto-fit,minmax(260px,1fr));
    gap:16px;
    margin-top:10px;
  }

  .rc-card{
    position:relative;
    padding:16px 14px 14px;
    border-radius:16px;
    border:1px solid rgba(148,163,184,.32);
    background:
      radial-gradient(circle at top left, rgba(34,197,94,.14), transparent 55%),
      radial-gradient(circle at bottom right, rgba(59,130,246,.16), transparent 55%),
      #020617;
    box-shadow:0 14px 30px rgba(0,0,0,.7);
    overflow:hidden;
    min-height:110px;
    display:flex;
    flex-direction:column;
    justify-content:center;
    align-items:center;
    text-align:center;
    transition:transform .12s ease, box-shadow .12s ease, border-color .12s ease, background .12s ease;
  }

  .rc-card:hover{
    transform:translateY(-2px);
    box-shadow:0 22px 40px rgba(0,0,0,.85);
    border-color:rgba(96,165,250,.9);
  }

  .rc-card--hero{
    grid-column:1 / -1;
    align-items:center;
    padding:18px 20px;
  }

  .rc-pill{
    display:inline-flex;
    align-items:center;
    gap:.35rem;
    padding:.22rem .75rem;
    border-radius:999px;
    background:rgba(15,23,42,.85);
    border:1px solid rgba(148,163,184,.55);
    font-size:.7rem;
    text-transform:uppercase;
    letter-spacing:.12em;
    color:#a5b4fc;
    margin-bottom:.45rem;
  }

  .rc-btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:.38rem 1rem;
    border-radius:999px;
    border:none;
    font-size:.82rem;
    font-weight:700;
    text-decoration:none;
    background:#38bdf8;
    color:#02131f;
    box-shadow:0 10px 24px rgba(56,189,248,.55);
  }
  .rc-btn:hover{
    background:#0ea5e9;
    color:#02131f;
  }
</style>
</head>

<body>

<header class="brand-hero">
  <div class="hero-inner container-main">
    <div class="d-flex align-items-center gap-3">
      <img class="brand-logo" src="<?= e($ESCUDO) ?>" alt="Escudo 602" style="height:52px; width:auto;">
      <div>
        <div class="brand-title">Batallón de Comunicaciones 602</div>
        <div class="brand-sub">
          “Hogar de las Comunicaciones Fijas del Ejército”
          <?php if ($seccion): ?>
            · <span class="text-warning"><?= e($RC_SECCIONES[$seccion] ?? '') ?></span>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="header-back">
      <!-- Botón volver a áreas -->
      <?php if (!empty($areasUrl)): ?>
        <button type="button"
                class="btn btn-outline-light btn-sm"
                style="font-weight:600; padding:.35rem .9rem;"
                onclick="window.location.href='<?= e($areasUrl) ?>'">
          Volver a áreas
        </button>
      <?php endif; ?>

      <!-- Botón volver normal (historial) -->
      <button type="button"
              class="btn btn-secondary btn-sm"
              style="font-weight:600; padding:.35rem .9rem;"
              onclick="if (window.history.length > 1) { window.history.back(); } else { window.location.href='index.php'; }">
        Volver
      </button>
    </div>
  </div>
</header>

<div class="page-wrap">
  <div class="container-main">
    <div class="panel">

      <!-- ===== BUSCADOR SUPERIOR POR DNI / NOMBRE ===== -->
      <div class="search-panel mb-3">
        <form method="get" class="row g-2 align-items-end">
          <!-- mantenemos seccion/personaId si estaban -->
          <?php if ($seccion > 0): ?>
            <input type="hidden" name="seccion" value="<?= e((string)$seccion) ?>">
          <?php endif; ?>
          <?php if ($personaId > 0): ?>
            <input type="hidden" name="id" value="<?= e((string)$personaId) ?>">
          <?php endif; ?>
          <input type="hidden" name="from" value="<?= e($from) ?>">

          <div class="col-sm-3 col-md-2">
            <label class="form-label text-muted">DNI</label>
            <input type="text"
                   name="dni"
                   class="form-control form-control-sm"
                   placeholder="Dni sin puntos"
                   value="<?= e($busqDni) ?>">
          </div>

          <div class="col-sm-5 col-md-4">
            <label class="form-label text-muted">Nombre y apellido</label>
            <input type="text"
                   name="nombre"
                   class="form-control form-control-sm"
                   placeholder="Nombre o apellido"
                   value="<?= e($busqNom) ?>">
          </div>

          <div class="col-sm-3 col-md-2">
            <button type="submit"
                    class="btn btn-success btn-sm w-100"
                    style="font-weight:700;">
              Buscar rol
            </button>
          </div>
        </form>

        <?php if ($busqDni !== '' || $busqNom !== ''): ?>
          <div class="search-results mt-2">
            <?php if (empty($searchResults)): ?>
              <div class="alert alert-warning py-1 px-2 mb-1" style="font-size:.8rem;">
                No se encontraron roles asignados para los criterios ingresados.
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm table-dark table-striped align-middle mb-1" style="font-size:.78rem;">
                  <thead>
                    <tr>
                      <th>DNI</th>
                      <th>Grado</th>
                      <th>Arma</th>
                      <th>Nombre y apellido</th>
                      <th>Elemento</th>
                      <th>Rol de Combate</th>
                      <th>Acción</th>
                    </tr>
                  </thead>
                  <tbody>
                  <?php foreach ($searchResults as $r):
                    $sec = (int)($r['seccion'] ?? 0);
                    $elem = ($sec >= 1 && $sec <= 8)
                      ? ($RC_ELEMENTOS[$sec] ?? ('Elemento '.$sec))
                      : 'Sin sección';
                    $link = 'rol_combate.php?seccion=' . (int)$sec
                          . '&id=' . (int)$r['personal_id']
                          . '&from=' . urlencode($from);
                  ?>
                    <tr>
                      <td><?= e($r['dni'] ?? '') ?></td>
                      <td><?= e($r['grado'] ?? '') ?></td>
                      <td><?= e($r['arma'] ?? '') ?></td>
                      <td><?= e($r['nombre_apellido'] ?? '') ?></td>
                      <td><?= e($elem) ?></td>
                      <td><?= e($r['rol_combate'] ?? '') ?></td>
                      <td>
                        <?php if ($sec >= 1 && $sec <= 8): ?>
                          <a href="<?= e($link) ?>" class="btn btn-outline-info btn-sm">
                            Ver en Rol de Combate
                          </a>
                        <?php else: ?>
                          <span class="text-muted">Sin sección</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
      <!-- ===== FIN BUSCADOR SUPERIOR ===== -->

      <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
        <div>
          <div class="panel-title">Rol de Combate · B Com 602</div>
          <div class="panel-sub mb-0">
            Vista por secciones (elementos). Cada fila vincula personal de la unidad con su función y medios asignados.
          </div>
        </div>

        <?php if ($seccion > 0): ?>
        <div class="d-flex flex-column flex-sm-row gap-2">
          <a href="rol_combate.php?from=<?= e($from) ?>"
             class="btn btn-sm btn-outline-light"
             style="font-weight:600; padding:.35rem .9rem;">
            Volver al menú de secciones
          </a>
          <a href="s1_personal.php"
             class="btn btn-sm btn-outline-success"
             style="font-weight:600; padding:.35rem .9rem;">
            Agregar persona (S-1)
          </a>
          <?php if ($puedeEditar): ?>
          <button id="btnGuardarTodo"
                  type="button"
                  class="btn btn-sm btn-success"
                  style="font-weight:600; padding:.35rem .9rem;">
            Guardar cambios
          </button>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>

      <?php if ($personaId > 0): ?>
        <div class="alert alert-warning py-2">
          Resaltando filas donde aparece el personal ID <strong>#<?= e($personaId) ?></strong>.
        </div>
      <?php endif; ?>

      <?php if ($listadoError !== ''): ?>
        <div class="alert alert-danger py-2">
          Error al obtener el Rol de Combate: <code><?= e($listadoError) ?></code>
        </div>
      <?php endif; ?>

<?php if ($seccion === 0): ?>
  <!-- ===== Grid principal de tarjetas ===== -->
  <div class="rc-grid">

    <!-- Card hero: todos los roles -->
    <div class="rc-card rc-card--hero">
      <div class="text-center">
        <div class="rc-pill">
          Vista general
        </div>
        <div class="card-section-title" style="font-size:1.02rem;">
          Todos los roles de combate
        </div>
        <div class="card-section-sub mt-1">
          Listado completo de personal y sus roles de combate asignados.
        </div>
        <div class="mt-2">
          <a href="rol_combate.php?seccion=9&from=<?= e($from) ?>" class="rc-btn">
            Ver todos los roles
          </a>
        </div>
      </div>
    </div>

    <!-- Tarjetas simples por rol de combate (1–8) -->
    <?php foreach ($RC_ELEMENTOS as $num => $label): ?>
      <a href="rol_combate.php?seccion=<?= e($num) ?>&from=<?= e($from) ?>"
         class="text-decoration-none text-reset">
        <div class="rc-card">
          <div class="card-section-title">
            <?= e($label) ?>
          </div>
        </div>
      </a>
    <?php endforeach; ?>

  </div>

<?php else: ?>


        <!-- ==================== TABLA DE UNA SECCIÓN ==================== -->
        <div class="d-flex justify-content-between flex-wrap gap-2 mb-2 mt-2">
          <div class="small text-muted">
            <?php if ($seccion === 9): ?>
              Vista 9: <?= e($RC_SECCIONES[$seccion] ?? '') ?> · Registros: <?= e(count($filasRol)) ?>
            <?php else: ?>
              Elemento <?= e($seccion) ?>:
              <?= e($RC_SECCIONES[$seccion] ?? '') ?>  
              · Registros: <?= e(count($filasRol)) ?>
            <?php endif; ?>
          </div>
        </div>

        <div class="table-responsive mt-2">
          <table class="table table-sm table-dark table-striped table-dark-custom table-rol-combate align-middle w-100">
            <colgroup>
              <col class="col-nro">
              <col class="col-grado">
              <col class="col-arma">
              <col class="col-nombre">
              <col class="col-rol">
              <col class="col-ap">
              <col class="col-niap">
              <col class="col-as">
              <col class="col-nias">
              <col class="col-roladm">
              <col class="col-vehiculo">
              <col class="col-elemento">
            </colgroup>
            <thead>
              <tr>
                <th scope="col">NRO</th>
                <th scope="col">Grado</th>
                <th scope="col">Arma</th>
                <th scope="col">Nombre y Apellido</th>
                <th scope="col">Rol Combate</th>
                <th scope="col">Armamento Principal</th>
                <th scope="col">NI AP</th>
                <th scope="col">Armamento Secundario</th>
                <th scope="col">NI AS</th>
                <th scope="col">Rol Administrativo</th>
                <th scope="col">Vehículo</th>
                <th scope="col">Elemento</th>
              </tr>
            </thead>

            <tbody>
            <?php if (!$filasRol): ?>
              <tr>
                <td colspan="12" class="text-center text-muted py-4">
                  No hay datos cargados para esta vista.
                </td>
              </tr>
            <?php else: ?>
              <?php
                $nroReal = 0;
                foreach ($filasRol as $row):
                  // En secciones 1–8 oculto filas sin persona asignada
                  $personalIdRow = (int)($row['personal_id'] ?? 0);
                  if ($seccion !== 9 && $personalIdRow === 0) {
                      continue;
                  }
                  $nroReal++;

                  $esPersonaMarcada = ($personaId > 0 && $personalIdRow === $personaId);
                  $secRow = isset($row['seccion']) ? (int)$row['seccion'] : 0;
                  $elementoTexto = ($secRow >= 1 && $secRow <= 8)
                      ? ($RC_ELEMENTOS[$secRow] ?? ('Elemento '.$secRow))
                      : 'Sin asignar';

                  $ap = trim((string)($row['armamento_principal']   ?? ''));
                  $as = trim((string)($row['armamento_secundario']  ?? ''));
                  $optsArm = ['FAL','PISTOLA','ESCOPETA'];
              ?>
                <tr
                  class="<?= $esPersonaMarcada ? 'row-resaltada' : '' ?>"

                  data-personal-id="<?= e((string)$personalIdRow) ?>"
                  data-rol-id="<?= e((string)($row['rol_id'] ?? '')) ?>"
                  data-asignacion-id="<?= e((string)($row['asignacion_id'] ?? '')) ?>"
                  data-seccion="<?= $secRow >= 1 && $secRow <= 8 ? e((string)$secRow) : '' ?>"
                >
                  <td><?= e($nroReal) ?></td>
                  <td><?= e($row['grado'] ?? '') ?></td>
                  <td><?= e($row['arma'] ?? '') ?></td>
                  <td><?= e($row['nombre_apellido'] ?? '') ?></td>

                  <!-- Rol Combate (texto editable) -->
                  <td>
                    <input
                      type="text"
                      class="form-control form-control-sm rc-input"
                      data-field="rol_combate"
                      value="<?= e($row['rol_combate'] ?? '') ?>"
                      <?= $soloLectura ? 'disabled' : '' ?>>
                  </td>

                  <!-- Armamento Principal (select) -->
                  <td>
                    <select
                      class="form-select form-select-sm rc-input"
                      data-field="armamento_principal"
                      <?= $soloLectura ? 'disabled' : '' ?>>
                      <option value="">(Sin asignar)</option>
                      <?php foreach ($optsArm as $op): ?>
                        <option value="<?= e($op) ?>" <?= $ap === $op ? 'selected' : '' ?>>
                          <?= e($op) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </td>

                  <!-- NI AP (texto editable) -->
                  <td>
                    <input
                      type="text"
                      class="form-control form-control-sm rc-input"
                      data-field="ni_armamento_principal"
                      value="<?= e($row['ni_armamento_principal'] ?? '') ?>"
                      <?= $soloLectura ? 'disabled' : '' ?>>
                  </td>

                  <!-- Armamento Secundario (select como AP) -->
                  <td>
                    <select
                      class="form-select form-select-sm rc-input"
                      data-field="armamento_secundario"
                      <?= $soloLectura ? 'disabled' : '' ?>>
                      <option value="">(Sin asignar)</option>
                      <?php foreach ($optsArm as $op): ?>
                        <option value="<?= e($op) ?>" <?= $as === $op ? 'selected' : '' ?>>
                          <?= e($op) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </td>

                  <!-- NI AS (texto editable) -->
                  <td>
                    <input
                      type="text"
                      class="form-control form-control-sm rc-input"
                      data-field="ni_armamento_secundario"
                      value="<?= e($row['ni_armamento_secundario'] ?? '') ?>"
                      <?= $soloLectura ? 'disabled' : '' ?>>
                  </td>

                  <!-- Rol Administrativo (texto editable) -->
                  <td>
                    <input
                      type="text"
                      class="form-control form-control-sm rc-input"
                      data-field="rol_administrativo"
                      value="<?= e($row['rol_administrativo'] ?? '') ?>"
                      <?= $soloLectura ? 'disabled' : '' ?>>
                  </td>

                  <!-- Vehículo (texto editable) -->
                  <td>
                    <input
                      type="text"
                      class="form-control form-control-sm rc-input"
                      data-field="vehiculo"
                      value="<?= e($row['vehiculo'] ?? '') ?>"
                      <?= $soloLectura ? 'disabled' : '' ?>>
                  </td>

                  <!-- Elemento (select 1–8) -->
                  <td>
                    <select
                      class="form-select form-select-sm rc-input"
                      data-field="seccion"
                      <?= $soloLectura ? 'disabled' : '' ?>>
                      <option value="">(Elegir elemento)</option>
                      <?php foreach ($RC_ELEMENTOS as $num => $label): ?>
                        <option value="<?= e((string)$num) ?>"
                          <?= ($secRow === $num ? 'selected' : '') ?>>
                          <?= e($label) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
          </table>
        </div>

      <?php endif; ?>

    </div>
  </div>
</div>
<script>
const PUEDE_EDITAR = <?= $puedeEditar ? 'true' : 'false' ?>;

// Guardar cambios por AJAX
document.addEventListener('DOMContentLoaded', () => {

  // Si no puede editar (no admin / no GRAL / no S3), no enganchamos nada
  if (!PUEDE_EDITAR) {
    return;
  }

  // ahora acepta un 3er parámetro: desdeBoton = true/false
  async function saveCampo(row, ctrl, desdeBoton = false) {
    const campo = ctrl.dataset.field;
    if (!campo) return;

    const personalId   = row.dataset.personalId;
    const rolId        = row.dataset.rolId || '';
    const asignacionId = row.dataset.asignacionId || '';
    let   seccion      = row.dataset.seccion || '';

    if (!personalId) return;

    // si NO tiene sección:
    if (campo !== 'seccion' && (!seccion || seccion === '')) {
      // si viene del botón "Guardar cambios", simplemente la salteamos
      if (desdeBoton) {
        return;
      }
      // si viene de editar un campo puntual, sí obligamos a elegir elemento
      alert('Primero seleccione un Elemento para esta persona.');
      return;
    }

    if (campo === 'seccion') {
      seccion = ctrl.value;
      if (!seccion) {
        // sólo mostramos alerta cuando el usuario toca el select directamente
        if (!desdeBoton) {
          alert('Debe seleccionar un Elemento (1 a 8).');
        }
        return;
      }
    }

    const valor = ctrl.value;

    const fd = new FormData();
    fd.append('personal_id', personalId);
    fd.append('rol_id', rolId);
    fd.append('asignacion_id', asignacionId);
    fd.append('seccion', seccion);
    fd.append('campo', campo);
    fd.append('valor', valor);

    try {
      const resp = await fetch('rol_combate_ajax.php', {
        method: 'POST',
        body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });

      const data = await resp.json();

      if (data && data.ok) {
        if (data.rol_id)        row.dataset.rolId = data.rol_id;
        if (data.asignacion_id) row.dataset.asignacionId = data.asignacion_id;
        if (data.seccion)       row.dataset.seccion = data.seccion;
      } else {
        console.error(data);
        alert('No se pudo guardar el cambio: ' + (data && data.error ? data.error : 'Error desconocido'));
      }
    } catch (err) {
      console.error(err);
      alert('Error de comunicación al guardar el dato.');
    }
  }

  // autosave por campo
  document.querySelectorAll('table tbody tr').forEach(row => {
    const personalId = row.dataset.personalId;
    if (!personalId) return; // sin personal no editamos

    row.querySelectorAll('.rc-input').forEach(ctrl => {
      const handler = () => saveCampo(row, ctrl, false);
      ctrl.addEventListener('change', handler);
      if (ctrl.tagName === 'INPUT') {
        ctrl.addEventListener('blur', handler);
      }
    });
  });

  // Botón "Guardar cambios" ahora saltea filas sin elemento
  const btnGuardar = document.getElementById('btnGuardarTodo');
  if (btnGuardar) {
    btnGuardar.addEventListener('click', async () => {
      const filas = Array.from(document.querySelectorAll('table tbody tr'));

      for (const row of filas) {
        const ctrls = row.querySelectorAll('.rc-input');
        for (const ctrl of ctrls) {
          // acá pasamos true para que no tire el alerta y saltee filas sin sección
          await saveCampo(row, ctrl, true);
        }
      }

      alert('Se enviaron los cambios del listado (solo filas con Elemento asignado).');
    });
  }

});
</script>

</body>
</html>
