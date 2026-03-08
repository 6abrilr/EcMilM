<?php
// public/rol_combate.php — Rol de Combate (tabla única con filtros) — Compatible con DB "unidad" actual
declare(strict_types=1);

require_once __DIR__ . '/../auth/bootstrap.php';
require_login();
require_once __DIR__ . '/../config/db.php';

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function norm_dni(string $dni): string { return preg_replace('/\D+/', '', $dni) ?? ''; }

/* ===== Usuario / permisos ===== */
$user = function_exists('current_user') ? current_user() : ($_SESSION['user'] ?? []);

// Unidad activa (según tu bootstrap / sesión). Fallback a 1.
$unidadActiva = (int)($_SESSION['unidad_id_activa'] ?? ($_SESSION['unidad_id'] ?? 1));
if ($unidadActiva <= 0) $unidadActiva = 1;

// ADAPTAR ESTAS CLAVES A TU CPS SI FUERA NECESARIO (para el botón "Volver")
$areaSesion = strtoupper(trim((string)($user['area'] ?? '')));   // ej: 'S1' o 'S3'
$esS1       = ($areaSesion === 'S1');
$esS3       = ($areaSesion === 'S3');

// -------- Permisos reales tomados de roles_locales ----------
$rolApp         = 'usuario';
$areasAccesoArr = [];

try {
    $dniUser = norm_dni((string)($user['dni'] ?? $user['username'] ?? ''));
    if ($dniUser !== '' && isset($pdo)) {
        // Preferimos el registro de la unidad; si no existe, tomamos el de unidad_id NULL
        $sqlRoles = "
          SELECT rol_app, areas_acceso
          FROM roles_locales
          WHERE dni = :dni
            AND (unidad_id = :uid OR unidad_id IS NULL)
          ORDER BY CASE WHEN unidad_id = :uid THEN 1 ELSE 2 END
          LIMIT 1
        ";
        $stmtRoles = $pdo->prepare($sqlRoles);
        $stmtRoles->execute([':dni' => $dniUser, ':uid'=>$unidadActiva]);

        if ($rowRol = $stmtRoles->fetch(PDO::FETCH_ASSOC)) {
            $rolApp = strtolower(trim((string)($rowRol['rol_app'] ?? 'usuario')));

            // areas_acceso viene como JSON: ["S1","S3",...]
            $rawAreas = $rowRol['areas_acceso'] ?? '[]';
            $tmp = json_decode((string)$rawAreas, true);
            if (is_array($tmp)) {
                $areasAccesoArr = array_map(
                    static fn($x) => strtoupper(trim((string)$x)),
                    $tmp
                );
            }
        }
    }
} catch (Throwable $e) {
    $rolApp         = 'usuario';
    $areasAccesoArr = [];
}

// Solo tiene permiso de edición el que tenga S3 en sus áreas
$tieneS3Acc   = in_array('S3', $areasAccesoArr, true);
$puedeEditar  = $tieneS3Acc;
$soloLectura  = !$puedeEditar;

// URL de "Volver a áreas" según el área principal de la sesión
if ($esS1) {
    $areasUrl = 'areas_s1.php';
} elseif ($esS3) {
    $areasUrl = 'areas_s3.php';
} else {
    $areasUrl = 'inicio.php';
}

/* ===== Rutas / assets ===== */
$PUBLIC_URL = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'])), '/');
$APP_URL    = rtrim(str_replace('\\','/', dirname($PUBLIC_URL)), '/');
$ASSETS_URL = ($APP_URL === '' ? '' : $APP_URL) . '/assets';
$IMG_BG     = $ASSETS_URL . '/img/fondo.png';
$ESCUDO     = $ASSETS_URL . '/img/escudo_bcom602.png';

/* ===== Secciones / elementos (1–8) ===== */
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

/* ==========================================================
   Helpers: Nota JSON (guardamos campos extra dentro de rca.nota)
   ========================================================== */
function nota_to_array(?string $nota): array {
    if (!$nota) return [];
    $nota = trim($nota);
    if ($nota === '') return [];
    $j = json_decode($nota, true);
    return is_array($j) ? $j : [];
}
function array_to_nota(array $a): string {
    // guardamos un JSON compacto
    $j = json_encode($a, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return is_string($j) ? $j : '{}';
}

/* ==========================================================
   Asegurar que existan los 8 "elementos" en rol_combate (sin cambiar esquema)
   - Usamos rol_combate.orden = 1..8 como "sección"
   - rol_combate.rol = nombre del elemento (solo para catálogo)
   ========================================================== */
try {
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $st = $pdo->prepare("SELECT id, orden FROM rol_combate WHERE unidad_id=:uid");
    $st->execute([':uid'=>$unidadActiva]);
    $exist = $st->fetchAll() ?: [];
    $mapOrdenToId = [];
    foreach ($exist as $r) {
        $ord = (int)($r['orden'] ?? 0);
        $id  = (int)($r['id'] ?? 0);
        if ($ord >= 1 && $ord <= 8 && $id > 0) $mapOrdenToId[$ord] = $id;
    }

    // Insertar faltantes (no rompe si ya están)
    foreach ($RC_ELEMENTOS as $ord => $label) {
        if (!isset($mapOrdenToId[$ord])) {
            $ins = $pdo->prepare("
              INSERT INTO rol_combate (unidad_id, categoria, rol, orden)
              VALUES (:uid, :cat, :rol, :ord)
            ");
            $ins->execute([
                ':uid'=>$unidadActiva,
                ':cat'=>'elemento',
                ':rol'=>$label,
                ':ord'=>$ord
            ]);
        }
    }
} catch (Throwable $e) {
    // si falla, seguimos igual (solo catálogo)
}

/* ==========================================================
   AJAX: Guardar cambios (sin rol_combate_ajax.php)
   - Guarda los campos extra dentro de rca.nota (JSON)
   - Cambiar "Elemento" actualiza rca.rol_id apuntando al rol_combate con ese orden
   ========================================================== */
$isAjax = ($_SERVER['REQUEST_METHOD'] === 'POST')
    && (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');

if ($isAjax) {
    if (function_exists('csrf_verify')) csrf_verify();

    header('Content-Type: application/json; charset=utf-8');

    try {
        if (!$puedeEditar) {
            throw new RuntimeException('Sin permisos para editar (requiere acceso S3).');
        }

        $personalId   = (int)($_POST['personal_id'] ?? 0);
        $asignacionId = (int)($_POST['asignacion_id'] ?? 0);
        $rolId        = (int)($_POST['rol_id'] ?? 0);
        $seccion      = (int)($_POST['seccion'] ?? 0);
        $campo        = trim((string)($_POST['campo'] ?? ''));
        $valor        = trim((string)($_POST['valor'] ?? ''));

        if ($personalId <= 0) throw new RuntimeException('personal_id inválido.');
        if ($campo === '') throw new RuntimeException('campo inválido.');

        // Validar que el personal pertenezca a la unidad activa
        $stP = $pdo->prepare("SELECT id FROM personal_unidad WHERE id=:pid AND unidad_id=:uid LIMIT 1");
        $stP->execute([':pid'=>$personalId, ':uid'=>$unidadActiva]);
        if (!$stP->fetchColumn()) throw new RuntimeException('Personal no pertenece a la unidad activa.');

        // Resolver rol_combate (elemento) por seccion (orden)
        $resolverRolPorSeccion = function(int $sec) use ($pdo, $unidadActiva): int {
            if ($sec < 1 || $sec > 8) return 0;
            $st = $pdo->prepare("SELECT id FROM rol_combate WHERE unidad_id=:uid AND orden=:o LIMIT 1");
            $st->execute([':uid'=>$unidadActiva, ':o'=>$sec]);
            return (int)($st->fetchColumn() ?: 0);
        };

        // Buscar asignación activa si no viene asignacion_id
        if ($asignacionId <= 0) {
            $stA = $pdo->prepare("
              SELECT id, rol_id, nota
              FROM rol_combate_asignaciones
              WHERE unidad_id=:uid AND personal_id=:pid
                AND (hasta IS NULL OR hasta > CURDATE())
              ORDER BY id DESC
              LIMIT 1
            ");
            $stA->execute([':uid'=>$unidadActiva, ':pid'=>$personalId]);
            if ($rowA = $stA->fetch(PDO::FETCH_ASSOC)) {
                $asignacionId = (int)$rowA['id'];
                $rolId = (int)($rowA['rol_id'] ?? 0);
            }
        }

        // 1) Cambiar elemento (seccion) => cambia rca.rol_id
        if ($campo === 'seccion') {
            if ($seccion < 1 || $seccion > 8) {
                throw new RuntimeException('Sección inválida (1..8).');
            }

            $rolElementoId = $resolverRolPorSeccion($seccion);
            if ($rolElementoId <= 0) throw new RuntimeException('No se pudo resolver rol_combate para esa sección.');

            if ($asignacionId > 0) {
                $up = $pdo->prepare("
                  UPDATE rol_combate_asignaciones
                  SET rol_id=:rid
                  WHERE id=:id AND unidad_id=:uid AND personal_id=:pid
                  LIMIT 1
                ");
                $up->execute([':rid'=>$rolElementoId, ':id'=>$asignacionId, ':uid'=>$unidadActiva, ':pid'=>$personalId]);
            } else {
                $ins = $pdo->prepare("
                  INSERT INTO rol_combate_asignaciones
                    (unidad_id, rol_id, personal_id, desde, hasta, nota, created_at, created_by_id)
                  VALUES
                    (:uid, :rid, :pid, CURDATE(), NULL, NULL, NOW(), :cb)
                ");
                $ins->execute([
                    ':uid'=>$unidadActiva,
                    ':rid'=>$rolElementoId,
                    ':pid'=>$personalId,
                    ':cb'=> (int)($_SESSION['user_personal_id'] ?? 0) ?: null
                ]);
                $asignacionId = (int)$pdo->lastInsertId();
            }

            echo json_encode([
                'ok'=>true,
                'asignacion_id'=>$asignacionId,
                'rol_id'=>$rolElementoId,
                'seccion'=>$seccion,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Para cualquier otro campo: necesitamos seccion válida
        if ($seccion < 1 || $seccion > 8) {
            // si no vino, intentamos deducirla por rolId actual
            if ($rolId > 0) {
                $st = $pdo->prepare("SELECT orden FROM rol_combate WHERE id=:id AND unidad_id=:uid LIMIT 1");
                $st->execute([':id'=>$rolId, ':uid'=>$unidadActiva]);
                $seccion = (int)($st->fetchColumn() ?: 0);
            }
        }
        if ($seccion < 1 || $seccion > 8) {
            throw new RuntimeException('Primero seleccione un Elemento (1..8).');
        }

        // Si no hay asignación, la creamos con rol_id según sección
        if ($asignacionId <= 0) {
            $rolElementoId = $resolverRolPorSeccion($seccion);
            if ($rolElementoId <= 0) throw new RuntimeException('No se pudo resolver rol_combate para la sección.');
            $ins = $pdo->prepare("
              INSERT INTO rol_combate_asignaciones
                (unidad_id, rol_id, personal_id, desde, hasta, nota, created_at, created_by_id)
              VALUES
                (:uid, :rid, :pid, CURDATE(), NULL, NULL, NOW(), :cb)
            ");
            $ins->execute([
                ':uid'=>$unidadActiva,
                ':rid'=>$rolElementoId,
                ':pid'=>$personalId,
                ':cb'=> (int)($_SESSION['user_personal_id'] ?? 0) ?: null
            ]);
            $asignacionId = (int)$pdo->lastInsertId();
            $rolId = $rolElementoId;
        }

        // Leer nota actual
        $stN = $pdo->prepare("SELECT nota FROM rol_combate_asignaciones WHERE id=:id AND unidad_id=:uid AND personal_id=:pid LIMIT 1");
        $stN->execute([':id'=>$asignacionId, ':uid'=>$unidadActiva, ':pid'=>$personalId]);
        $notaRaw = (string)($stN->fetchColumn() ?? '');

        $notaArr = nota_to_array($notaRaw);

        // Campos soportados (guardados en JSON dentro de nota)
        $allowed = [
            'rol_combate',
            'armamento_principal',
            'ni_armamento_principal',
            'armamento_secundario',
            'ni_armamento_secundario',
            'rol_administrativo',
            'vehiculo',
        ];
        if (!in_array($campo, $allowed, true)) {
            throw new RuntimeException('Campo no permitido.');
        }

        // Set / unset
        if ($valor === '') {
            unset($notaArr[$campo]);
        } else {
            $notaArr[$campo] = $valor;
        }

        $notaJson = array_to_nota($notaArr);

        $up = $pdo->prepare("
          UPDATE rol_combate_asignaciones
          SET nota=:nota
          WHERE id=:id AND unidad_id=:uid AND personal_id=:pid
          LIMIT 1
        ");
        $up->execute([':nota'=>$notaJson, ':id'=>$asignacionId, ':uid'=>$unidadActiva, ':pid'=>$personalId]);

        echo json_encode([
            'ok'=>true,
            'asignacion_id'=>$asignacionId,
            'rol_id'=>$rolId,
            'seccion'=>$seccion,
        ], JSON_UNESCAPED_UNICODE);
        exit;

    } catch (Throwable $ex) {
        http_response_code(400);
        echo json_encode(['ok'=>false, 'error'=>$ex->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

/* ===== Parámetros de filtros ===== */
$personaId      = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$from           = $_GET['from'] ?? 's1';
if ($from !== 's1' && $from !== 's3') $from = 's1';

$filtroDni      = trim((string)($_GET['dni'] ?? ''));
$filtroNombre   = trim((string)($_GET['nombre'] ?? ''));
$filtroSeccion  = isset($_GET['seccion']) ? trim((string)$_GET['seccion']) : ''; // '', '1'..'8'

/* ===== Consulta principal (tabla) ===== */
$filasRol = [];
$listadoError = '';

try {
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $sql = "
        SELECT
            p.id              AS personal_id,
            p.dni,
            p.grado,
            p.arma            AS arma,
            p.apellido_nombre AS nombre_apellido,

            rc.id             AS rol_id,
            rc.orden          AS seccion,
            rc.rol            AS elemento_label,

            rca.id            AS asignacion_id,
            rca.nota          AS nota_json
        FROM personal_unidad p
        LEFT JOIN rol_combate_asignaciones rca
               ON p.id = rca.personal_id
              AND rca.unidad_id = p.unidad_id
              AND (rca.hasta IS NULL OR rca.hasta > CURDATE())
        LEFT JOIN rol_combate rc
               ON rc.id = rca.rol_id
              AND rc.unidad_id = p.unidad_id
        WHERE p.unidad_id = :uid
          AND (p.grado IS NULL OR p.grado <> 'A/C')
    ";

    $params = [':uid'=>$unidadActiva];

    if ($filtroDni !== '') {
        $sql .= " AND p.dni LIKE :dni";
        $params[':dni'] = '%' . $filtroDni . '%';
    }

    if ($filtroNombre !== '') {
        $sql .= " AND p.apellido_nombre LIKE :nom";
        $params[':nom'] = '%' . $filtroNombre . '%';
    }

    if ($filtroSeccion !== '' && ctype_digit($filtroSeccion)) {
        $secNum = (int)$filtroSeccion;
        if ($secNum >= 1 && $secNum <= 8) {
            $sql .= " AND rc.orden = :sec";
            $params[':sec'] = $secNum;
        }
    }

    $sql .= " ORDER BY p.id ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $raw = $stmt->fetchAll() ?: [];

    // Expandir nota_json a columnas esperadas por la UI (sin tocar esquema)
    foreach ($raw as $r) {
        $notaArr = nota_to_array($r['nota_json'] ?? null);

        $r['rol_combate'] = (string)($notaArr['rol_combate'] ?? '');
        $r['armamento_principal'] = (string)($notaArr['armamento_principal'] ?? '');
        $r['ni_armamento_principal'] = (string)($notaArr['ni_armamento_principal'] ?? '');
        $r['armamento_secundario'] = (string)($notaArr['armamento_secundario'] ?? '');
        $r['ni_armamento_secundario'] = (string)($notaArr['ni_armamento_secundario'] ?? '');
        $r['rol_administrativo'] = (string)($notaArr['rol_administrativo'] ?? '');
        $r['vehiculo'] = (string)($notaArr['vehiculo'] ?? '');

        $filasRol[] = $r;
    }

} catch (Throwable $ex) {
    $listadoError = $ex->getMessage();
    $filasRol = [];
}

// CSRF token (si existe) para AJAX
$csrfToken = function_exists('csrf_token') ? (string)csrf_token() : '';
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
    color:#bfdbfe;
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
    margin-right:17px;
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
    color:#bfdbfe;
  }

  .text-muted{
    color:#bfdbfe !important;
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

  .table-dark-custom thead th{ color:#fff !important; }

  .table-rol-combate col.col-nro      { width: 4%;  }
  .table-rol-combate col.col-dni      { width: 8%;  }
  .table-rol-combate col.col-grado    { width: 6%;  }
  .table-rol-combate col.col-arma     { width: 7%;  }
  .table-rol-combate col.col-nombre   { width: 18%; }
  .table-rol-combate col.col-rol      { width: 11%; }
  .table-rol-combate col.col-ap       { width: 9%;  }
  .table-rol-combate col.col-niap     { width: 7%;  }
  .table-rol-combate col.col-as       { width: 9%;  }
  .table-rol-combate col.col-nias     { width: 7%;  }
  .table-rol-combate col.col-roladm   { width: 10%; }
  .table-rol-combate col.col-vehiculo { width: 6%;  }
  .table-rol-combate col.col-elemento { width: 8%;  }

  .row-resaltada{
    background:rgba(34,197,94,.20)!important;
  }

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
    color:#bfdbfe;
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
          “Hogar de las Comunicaciones Fijas del Ejército” · Rol de Combate (tabla única)
        </div>
      </div>
    </div>
    <div class="header-back">
      <?php if (!empty($areasUrl)): ?>
        <button type="button"
                class="btn btn-success btn-sm"
                style="font-weight:700; padding:.35rem .9rem;"
                onclick="window.location.href='<?= e($areasUrl) ?>'">
          Volver al inicio
        </button>
      <?php endif; ?>

      <button type="button"
              class="btn btn-success btn-sm"
              style="font-weight:700; padding:.35rem .9rem;"
              onclick="if (window.history.length > 1) { window.history.back(); } else { window.location.href='personal.php'; }">
        Volver
      </button>
    </div>
  </div>
</header>

<div class="page-wrap">
  <div class="container-main">
    <div class="panel">

      <!-- ===== FILTROS SUPERIORES ===== -->
      <div class="search-panel mb-3">
        <form method="get" class="row g-2 align-items-end">
          <input type="hidden" name="from" value="<?= e($from) ?>">

          <div class="col-sm-3 col-md-2">
            <label class="form-label">DNI</label>
            <input type="text"
                   name="dni"
                   class="form-control form-control-sm"
                   placeholder="DNI sin puntos"
                   value="<?= e($filtroDni) ?>">
          </div>

          <div class="col-sm-4 col-md-3">
            <label class="form-label">Nombre y apellido</label>
            <input type="text"
                   name="nombre"
                   class="form-control form-control-sm"
                   placeholder="Nombre o apellido"
                   value="<?= e($filtroNombre) ?>">
          </div>

          <div class="col-sm-4 col-md-3">
            <label class="form-label">Elemento (Rol de Combate)</label>
            <select name="seccion" class="form-select form-select-sm">
              <option value="">Todos los elementos</option>
              <?php foreach ($RC_ELEMENTOS as $num => $label): ?>
                <option value="<?= e((string)$num) ?>"
                  <?= ($filtroSeccion !== '' && (int)$filtroSeccion === $num) ? 'selected' : '' ?>>
                  <?= e($label) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-sm-3 col-md-2 d-flex gap-1">
            <button type="submit"
                    class="btn btn-success btn-sm w-100"
                    style="font-weight:700;">
              Filtrar
            </button>
            <a href="rol_combate.php?from=<?= e($from) ?>"
               class="btn btn-outline-success btn-sm"
               style="font-weight:600;">
              Limpiar
            </a>
          </div>
        </form>
      </div>
      <!-- ===== FIN FILTROS ===== -->

      <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
        <div>
          <div class="panel-title">Rol de Combate · B Com 602</div>
          <div class="panel-sub mb-0">
            Tabla única (compat DB actual). Datos “extra” se guardan en <code>rol_combate_asignaciones.nota</code> como JSON.
          </div>
          <div class="small text-muted mt-1">
            Unidad ID: <?= e((string)$unidadActiva) ?> · Registros mostrados: <?= e((string)count($filasRol)) ?>
            <?php if ($filtroSeccion !== '' && ctype_digit($filtroSeccion)): ?>
              · Elemento seleccionado: <?= e($RC_ELEMENTOS[(int)$filtroSeccion] ?? ('Sección ' . $filtroSeccion)) ?>
            <?php endif; ?>
          </div>
        </div>

        <div class="d-flex flex-column flex-sm-row gap-2">
          <button id="btnExportPdf"
                  type="button"
                  class="btn btn-sm btn-outline-light"
                  style="font-weight:600; padding:.35rem .9rem;">
            Exportar PDF
          </button>
          <?php if ($puedeEditar): ?>
          <button id="btnGuardarTodo"
                  type="button"
                  class="btn btn-sm btn-success"
                  style="font-weight:600; padding:.35rem .9rem;">
            Guardar cambios
          </button>
          <?php endif; ?>
        </div>
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

      <!-- ==================== TABLA ==================== -->
      <div class="table-responsive mt-2">
        <table class="table table-sm table-dark table-striped table-dark-custom table-rol-combate align-middle w-100">
          <colgroup>
            <col class="col-nro">
            <col class="col-dni">
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
              <th scope="col">DNI</th>
              <th scope="col">Grado</th>
              <th scope="col">Arma/Espec</th>
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
              <td colspan="13" class="text-center text-muted py-4">
                No hay datos para los filtros seleccionados.
              </td>
            </tr>
          <?php else: ?>
            <?php
              $nroReal = 0;
              foreach ($filasRol as $row):
                $personalIdRow = (int)($row['personal_id'] ?? 0);
                if ($personalIdRow === 0) continue;
                $nroReal++;

                $esPersonaMarcada = ($personaId > 0 && $personalIdRow === $personaId);
                $secRow           = isset($row['seccion']) ? (int)$row['seccion'] : 0;

                $ap = trim((string)($row['armamento_principal']   ?? ''));
                $as = trim((string)($row['armamento_secundario']  ?? ''));
                $optsArm = ['FAL','PISTOLA','ESCOPETA'];

                $soloLecturaFila = $soloLectura;
            ?>
              <tr
                class="<?= $esPersonaMarcada ? 'row-resaltada' : '' ?>"
                data-personal-id="<?= e((string)$personalIdRow) ?>"
                data-rol-id="<?= e((string)($row['rol_id'] ?? '')) ?>"
                data-asignacion-id="<?= e((string)($row['asignacion_id'] ?? '')) ?>"
                data-seccion="<?= ($secRow>=1 && $secRow<=8) ? e((string)$secRow) : '' ?>"
              >
                <td><?= e($nroReal) ?></td>
                <td><?= e($row['dni'] ?? '') ?></td>
                <td><?= e($row['grado'] ?? '') ?></td>
                <td><?= e($row['arma'] ?? '') ?></td>
                <td><?= e($row['nombre_apellido'] ?? '') ?></td>

                <!-- Rol Combate -->
                <td>
                  <input
                    type="text"
                    class="form-control form-control-sm rc-input"
                    data-field="rol_combate"
                    value="<?= e($row['rol_combate'] ?? '') ?>"
                    <?= $soloLecturaFila ? 'disabled' : '' ?>>
                </td>

                <!-- Armamento Principal -->
                <td>
                  <select
                    class="form-select form-select-sm rc-input"
                    data-field="armamento_principal"
                    <?= $soloLecturaFila ? 'disabled' : '' ?>>
                    <option value="">(Sin asignar)</option>
                    <?php foreach ($optsArm as $op): ?>
                      <option value="<?= e($op) ?>" <?= $ap === $op ? 'selected' : '' ?>>
                        <?= e($op) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </td>

                <!-- NI AP -->
                <td>
                  <input
                    type="text"
                    class="form-control form-control-sm rc-input"
                    data-field="ni_armamento_principal"
                    value="<?= e($row['ni_armamento_principal'] ?? '') ?>"
                    <?= $soloLecturaFila ? 'disabled' : '' ?>>
                </td>

                <!-- Armamento Secundario -->
                <td>
                  <select
                    class="form-select form-select-sm rc-input"
                    data-field="armamento_secundario"
                    <?= $soloLecturaFila ? 'disabled' : '' ?>>
                    <option value="">(Sin asignar)</option>
                    <?php foreach ($optsArm as $op): ?>
                      <option value="<?= e($op) ?>" <?= $as === $op ? 'selected' : '' ?>>
                        <?= e($op) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </td>

                <!-- NI AS -->
                <td>
                  <input
                    type="text"
                    class="form-control form-control-sm rc-input"
                    data-field="ni_armamento_secundario"
                    value="<?= e($row['ni_armamento_secundario'] ?? '') ?>"
                    <?= $soloLecturaFila ? 'disabled' : '' ?>>
                </td>

                <!-- Rol Administrativo -->
                <td>
                  <input
                    type="text"
                    class="form-control form-control-sm rc-input"
                    data-field="rol_administrativo"
                    value="<?= e($row['rol_administrativo'] ?? '') ?>"
                    <?= $soloLecturaFila ? 'disabled' : '' ?>>
                </td>

                <!-- Vehículo -->
                <td>
                  <input
                    type="text"
                    class="form-control form-control-sm rc-input"
                    data-field="vehiculo"
                    value="<?= e($row['vehiculo'] ?? '') ?>"
                    <?= $soloLecturaFila ? 'disabled' : '' ?>>
                </td>

                <!-- Elemento -->
                <td>
                  <select
                    class="form-select form-select-sm rc-input"
                    data-field="seccion"
                    <?= $soloLecturaFila ? 'disabled' : '' ?>>
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

    </div>
  </div>
</div>

<script>
const PUEDE_EDITAR = <?= $puedeEditar ? 'true' : 'false' ?>;
const LOGO_URL     = '<?= e($ESCUDO) ?>';
const CSRF_TOKEN   = '<?= e($csrfToken) ?>';

// ===== Exportar tabla a PDF (impresión) =====
function exportTablaPDF() {
  const tabla = document.querySelector('.table-rol-combate');
  if (!tabla) { alert('No se encontró la tabla para exportar.'); return; }

  const win = window.open('', '_blank');
  if (!win) { alert('El navegador bloqueó la ventana emergente.'); return; }

  const titulo = 'Rol de Combate · B Com 602';

  const html = `
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>${titulo}</title>
<style>
  @page { size: A4 landscape; margin: 15mm 15mm 15mm 20mm; }
  body{ font-family:"Times New Roman", serif; font-size:10px; margin:0; color:#111; }
  h1{ font-size:14pt; text-align:center; margin:6px 0 2px; }
  .sub{ font-size:11pt; text-align:center; margin-bottom:8px; }
  table{ width:100%; border-collapse:collapse; }
  th,td{ border:1px solid #000; padding:2px 3px; }
  th{ background:#e5e7eb; }
  .enc-cab td{ border:none !important; padding:0; }
</style>
</head>
<body>

<div style="margin:0; padding:0; line-height:1;">
  <table width="100%" style="border:none; border-collapse:collapse; border-spacing:0;">
    <tr class="enc-cab">
      <td style="text-align:left;">
        <p style="font-family:'Times New Roman',serif; font-size:16pt; margin:0 0 0 40px;">Ejército Argentino</p>
        <p style="font-family:'Times New Roman',serif; font-size:14pt; margin:0;">Batallón de Comunicaciones 602</p>
      </td>
      <td style="text-align:right; vertical-align:middle;">
        <img src="${LOGO_URL}" style="height:70px;">
      </td>
    </tr>
  </table>
</div>

<h1>Rol de Combate</h1>
<div class="sub">Batallón de Comunicaciones 602</div>

<table>
  ${tabla.tHead.outerHTML}
  ${tabla.tBodies[0].outerHTML}
</table>

</body>
</html>`;

  win.document.open();
  win.document.write(html);
  win.document.close();
  win.focus();
  win.print();
}

document.addEventListener('DOMContentLoaded', () => {
  const btnPdf = document.getElementById('btnExportPdf');
  if (btnPdf) btnPdf.addEventListener('click', exportTablaPDF);

  if (!PUEDE_EDITAR) return;

  async function saveCampo(row, ctrl, desdeBoton = false) {
    const campo = ctrl.dataset.field;
    if (!campo) return;

    const personalId   = row.dataset.personalId;
    const rolId        = row.dataset.rolId || '';
    const asignacionId = row.dataset.asignacionId || '';
    let   seccion      = row.dataset.seccion || '';

    if (!personalId) return;

    if (campo !== 'seccion' && (!seccion || seccion === '')) {
      if (!desdeBoton) showToast('Primero seleccione un Elemento para esta persona.', 'error');
      return;
    }

    if (campo === 'seccion') {
      seccion = ctrl.value;
      if (!seccion) {
        if (!desdeBoton) showToast('Debe seleccionar un Elemento (1 a 8).', 'error');
        return;
      }
    }

    const valor = ctrl.value;

    const fd = new FormData();
    if (CSRF_TOKEN) fd.append('_csrf', CSRF_TOKEN);

    fd.append('personal_id', personalId);
    fd.append('rol_id', rolId);
    fd.append('asignacion_id', asignacionId);
    fd.append('seccion', seccion);
    fd.append('campo', campo);
    fd.append('valor', valor);

    try {
      const resp = await fetch('rol_combate.php', {
        method: 'POST',
        body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      const data = await resp.json();

      if (data && data.ok) {
        if (data.rol_id !== undefined)        row.dataset.rolId        = String(data.rol_id);
        if (data.asignacion_id !== undefined) row.dataset.asignacionId = String(data.asignacion_id);
        if (data.seccion !== undefined)       row.dataset.seccion      = String(data.seccion);

        if (!desdeBoton) showToast('Cambios guardados correctamente.', 'success');
      } else {
        const msg = (data && data.error) ? data.error : 'Error desconocido';
        showToast('No se pudo guardar el cambio: ' + msg, 'error');
      }
    } catch (err) {
      console.error(err);
      showToast('Error de comunicación al guardar el dato.', 'error');
    }
  }

  document.querySelectorAll('table.table-rol-combate tbody tr').forEach(row => {
    const personalId = row.dataset.personalId;
    if (!personalId) return;

    row.querySelectorAll('.rc-input').forEach(ctrl => {
      const handler = () => saveCampo(row, ctrl, false);
      ctrl.addEventListener('change', handler);
      if (ctrl.tagName === 'INPUT') ctrl.addEventListener('blur', handler);
    });
  });

  const btnGuardar = document.getElementById('btnGuardarTodo');
  if (btnGuardar) {
    btnGuardar.addEventListener('click', async () => {
      const filas = Array.from(document.querySelectorAll('table.table-rol-combate tbody tr'));
      for (const row of filas) {
        const ctrls = row.querySelectorAll('.rc-input');
        for (const ctrl of ctrls) {
          await saveCampo(row, ctrl, true);
        }
      }
      showToast('Se enviaron los cambios del listado (solo filas con Elemento asignado).', 'success');
    });
  }
});
</script>

<!-- Toast notificaciones -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1080;">
  <div id="liveToast" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body" id="toastMsg"></div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
  let toastEl   = document.getElementById('liveToast');
  let toastMsg  = document.getElementById('toastMsg');
  let toastInst = toastEl ? new bootstrap.Toast(toastEl) : null;

  function showToast(message, tipo = 'success') {
    if (!toastEl || !toastInst) { alert(message); return; }
    toastMsg.textContent = message;
    toastEl.classList.remove('text-bg-success', 'text-bg-danger');
    toastEl.classList.add(tipo === 'error' ? 'text-bg-danger' : 'text-bg-success');
    toastInst.show();
  }
</script>

</body>
</html>
