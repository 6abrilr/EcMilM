<?php
declare(strict_types=1);

require_once __DIR__ . '/../../auth/bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/operaciones_helper.php';

operaciones_require_login();

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function org_norm_dni(string $dni): string { return preg_replace('/\D+/', '', $dni) ?? ''; }
function org_column_exists(PDO $pdo, string $table, string $column): bool {
    try {
        $st = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c");
        $st->execute([':t' => $table, ':c' => $column]);
        return (int)$st->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}
function org_area_rank(string $area): int {
    $a = strtoupper(trim($area));
    if (strpos($a, 'SUBDIRECCI') !== false) return 1;
    if (strpos($a, 'DIRECCI') !== false) return 0;
    if (strpos($a, 'JEFATURA') !== false) return 2;
    return 10;
}

$SELF_WEB        = (string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
$BASE_DIR_WEB    = rtrim(str_replace('\\', '/', dirname($SELF_WEB)), '/');
$BASE_PUBLIC_WEB = rtrim(str_replace('\\', '/', dirname($BASE_DIR_WEB)), '/');
$BASE_APP_WEB    = rtrim(str_replace('\\', '/', dirname($BASE_PUBLIC_WEB)), '/');
$ASSET_WEB       = $BASE_APP_WEB . '/assets';
$IMG_BG          = $ASSET_WEB . '/img/fondo.png';
$ESCUDO          = $ASSET_WEB . '/img/ecmilm.png';

$user = operaciones_current_user();
$dniUsuario = org_norm_dni((string)($user['dni'] ?? $user['username'] ?? ''));
$unidadActiva = 1;
$personalId = 0;

try {
    if ($dniUsuario !== '') {
        $st = $pdo->prepare("
            SELECT id, unidad_id
            FROM personal_unidad
            WHERE REPLACE(REPLACE(REPLACE(dni,'.',''),'-',''),' ','') = :dni
            LIMIT 1
        ");
        $st->execute([':dni' => $dniUsuario]);
        if ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            $personalId = (int)($r['id'] ?? 0);
            $unidadActiva = (int)($r['unidad_id'] ?? $unidadActiva);
        }
    }
} catch (Throwable $ignored) {}

$roleCode = operaciones_get_role_code($pdo, $personalId, $unidadActiva);
if ($roleCode === 'SUPERADMIN') {
    $uSel = (int)($_SESSION['unidad_id'] ?? 0);
    if ($uSel > 0) $unidadActiva = $uSel;
}

$nombreUnidad = 'Unidad';
$subnombreUnidad = '';
try {
    $st = $pdo->prepare("SELECT nombre_completo, subnombre FROM unidades WHERE id = :id LIMIT 1");
    $st->execute([':id' => $unidadActiva]);
    if ($u = $st->fetch(PDO::FETCH_ASSOC)) {
        $nombreUnidad = (string)($u['nombre_completo'] ?: $nombreUnidad);
        $subnombreUnidad = trim((string)($u['subnombre'] ?? ''));
    }
} catch (Throwable $ignored) {}

$SQL_ORDEN_JERARQUIA = "CASE pu.jerarquia
    WHEN 'OFICIAL' THEN 1
    WHEN 'SUBOFICIAL' THEN 2
    WHEN 'SOLDADO' THEN 3
    WHEN 'AGENTE_CIVIL' THEN 4
    ELSE 5
END";
$SQL_ORDEN_GRADO = "CASE pu.grado
    WHEN 'TG' THEN 9 WHEN 'GD' THEN 10 WHEN 'GB' THEN 11 WHEN 'CR' THEN 12
    WHEN 'TC' THEN 13 WHEN 'MY' THEN 14 WHEN 'CT' THEN 15 WHEN 'TP' THEN 16
    WHEN 'TT' THEN 17 WHEN 'ST' THEN 18 WHEN 'SM' THEN 20 WHEN 'SP' THEN 21
    WHEN 'SA' THEN 22 WHEN 'SI' THEN 23 WHEN 'SG' THEN 24 WHEN 'CI' THEN 25
    WHEN 'CB' THEN 28 WHEN 'VP' THEN 31 WHEN 'VS' THEN 32 WHEN 'SV' THEN 34
    WHEN 'AC' THEN 35 ELSE 99
END";

$nameExpr = org_column_exists($pdo, 'personal_unidad', 'apellido_nombre')
    ? "NULLIF(TRIM(pu.apellido_nombre), '')"
    : "NULL";
$fallbackNameExpr = "TRIM(CONCAT_WS(' ', NULLIF(pu.apellido, ''), NULLIF(pu.nombre, '')))";
$hasFuncion = org_column_exists($pdo, 'personal_unidad', 'funcion');
$hasRolAdm = org_column_exists($pdo, 'personal_unidad', 'rol_administrativo');
$hasRolCombate = org_column_exists($pdo, 'personal_unidad', 'rol_combate');
$hasDestinoInterno = org_column_exists($pdo, 'personal_unidad', 'destino_interno');

$personal = [];
$mensajeError = '';
try {
    $joinDestinoInterno = $hasDestinoInterno ? "LEFT JOIN destino_interno di ON di.id = pu.destino_interno" : "";
    $areaExpr = $hasDestinoInterno ? "COALESCE(NULLIF(TRIM(di.nombre), ''), 'SIN AREA')" : "'SIN AREA'";
    $funcionExpr = $hasFuncion ? "pu.funcion" : "''";
    $rolAdmExpr = $hasRolAdm ? "pu.rol_administrativo" : "''";
    $rolCombateExpr = $hasRolCombate ? "pu.rol_combate" : "''";
    $sql = "
        SELECT
            pu.id,
            pu.jerarquia,
            pu.grado,
            pu.arma,
            COALESCE($nameExpr, NULLIF($fallbackNameExpr, ''), 'Sin nombre') AS apellido_nombre,
            pu.dni,
            $areaExpr AS area_nombre,
            $funcionExpr AS funcion,
            $rolAdmExpr AS rol_administrativo,
            $rolCombateExpr AS rol_combate
        FROM personal_unidad pu
        $joinDestinoInterno
        WHERE pu.unidad_id = :uid
        ORDER BY
            CASE
                WHEN UPPER($areaExpr) LIKE '%SUBDIRECCI%' THEN 1
                WHEN UPPER($areaExpr) LIKE '%DIRECCI%' THEN 0
                WHEN UPPER($areaExpr) LIKE '%JEFATURA%' THEN 2
                WHEN $areaExpr = 'SIN AREA' THEN 99
                ELSE 10
            END,
            $areaExpr ASC,
            $SQL_ORDEN_JERARQUIA,
            $SQL_ORDEN_GRADO,
            apellido_nombre ASC
    ";
    $st = $pdo->prepare($sql);
    $st->execute([':uid' => $unidadActiva]);
    $personal = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $ex) {
    $mensajeError = $ex->getMessage();
}

$areas = [];
$totalesJerarquia = ['OFICIAL' => 0, 'SUBOFICIAL' => 0, 'SOLDADO' => 0, 'AGENTE_CIVIL' => 0, 'OTROS' => 0];
foreach ($personal as $p) {
    $area = trim((string)($p['area_nombre'] ?? ''));
    if ($area === '') $area = 'SIN AREA';
    if (!isset($areas[$area])) {
        $areas[$area] = [
            'nombre' => $area,
            'personal' => [],
            'total' => 0,
            'jerarquias' => ['OFICIAL' => 0, 'SUBOFICIAL' => 0, 'SOLDADO' => 0, 'AGENTE_CIVIL' => 0, 'OTROS' => 0],
        ];
    }
    $jer = (string)($p['jerarquia'] ?? '');
    $jerKey = array_key_exists($jer, $totalesJerarquia) ? $jer : 'OTROS';
    $areas[$area]['personal'][] = $p;
    $areas[$area]['total']++;
    $areas[$area]['jerarquias'][$jerKey]++;
    $totalesJerarquia[$jerKey]++;
}
uasort($areas, static function (array $a, array $b): int {
    $ra = org_area_rank((string)$a['nombre']);
    $rb = org_area_rank((string)$b['nombre']);
    return $ra === $rb ? strcasecmp((string)$a['nombre'], (string)$b['nombre']) : ($ra <=> $rb);
});

$direccionAreas = array_filter($areas, static fn(array $a): bool => org_area_rank((string)$a['nombre']) === 0);
$areasSubordinadas = array_filter($areas, static fn(array $a): bool => org_area_rank((string)$a['nombre']) !== 0);
$totalGeneral = count($personal);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>CO - Cuadro de organizaci&oacute;n</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/png" href="<?= e($ESCUDO) ?>">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
:root{--bg:#020617;--panel:rgba(15,23,42,.94);--panel2:rgba(2,6,23,.72);--line:rgba(148,163,184,.35);--text:#e5e7eb;--muted:#94a3b8;--green:#22c55e}
html,body{min-height:100%}
body{margin:0;background:linear-gradient(160deg,rgba(0,0,0,.84),rgba(0,0,0,.68)),url("<?= e($IMG_BG) ?>") center/cover fixed;color:var(--text);font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
.wrap{max-width:1440px;margin:auto;padding:18px}
.topbar{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-bottom:16px}
.brand{display:flex;align-items:center;gap:12px}.brand img{width:58px;height:58px;object-fit:contain}.brand-title{font-weight:900;line-height:1.1}.brand-sub{font-size:.82rem;color:var(--muted)}
.panel{background:var(--panel);border:1px solid var(--line);border-radius:18px;box-shadow:0 18px 42px rgba(0,0,0,.72);padding:18px 20px;margin-bottom:16px;backdrop-filter:blur(8px)}
.title{display:flex;align-items:center;gap:.55rem;font-weight:900;font-size:1.12rem}.sub{color:#cbd5e1;font-size:.88rem;margin-top:5px}
.kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-top:14px}.kpi{background:var(--panel2);border:1px solid var(--line);border-radius:12px;padding:10px 12px}.kpi-label{font-size:.72rem;color:var(--muted);font-weight:900;text-transform:uppercase}.kpi-value{font-size:1.45rem;font-weight:900}
.tree{overflow:auto;padding:10px 0 2px}.tree-root{display:flex;justify-content:center}.node{display:inline-flex;align-items:center;gap:8px;border:1px solid rgba(34,197,94,.5);background:rgba(34,197,94,.14);border-radius:12px;padding:10px 14px;font-weight:900;white-space:nowrap}.node small{font-weight:800;color:#bbf7d0}
.tree-children{position:relative;display:flex;gap:12px;align-items:stretch;margin-top:34px;padding-top:22px}.tree-children:before{content:"";position:absolute;top:0;left:24px;right:24px;border-top:1px solid var(--line)}.tree-child{position:relative;min-width:190px;max-width:240px;flex:1}.tree-child:before{content:"";position:absolute;top:-22px;left:50%;height:22px;border-left:1px solid var(--line)}
.area-node{height:100%;display:flex;flex-direction:column;justify-content:center;gap:4px;background:rgba(15,23,42,.9);border:1px solid var(--line);border-radius:12px;padding:10px;text-align:center}.area-name{font-weight:900;font-size:.86rem}.area-count{color:var(--muted);font-size:.76rem}
.areas-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(360px,1fr));gap:14px}.area-card{background:var(--panel);border:1px solid var(--line);border-radius:14px;overflow:hidden}.area-head{display:flex;justify-content:space-between;gap:10px;padding:12px 14px;background:rgba(34,197,94,.09);border-bottom:1px solid var(--line)}.area-head h2{font-size:.98rem;margin:0;font-weight:900}.area-stats{font-size:.76rem;color:#cbd5e1}
.table{--bs-table-bg:transparent;--bs-table-color:#e5e7eb;--bs-table-border-color:rgba(148,163,184,.16);margin:0}.table th{color:#9ca3af;font-size:.72rem;text-transform:uppercase}.table td{font-size:.78rem;vertical-align:middle}.badge-rank{display:inline-flex;border-radius:999px;padding:.14rem .45rem;font-size:.72rem;font-weight:900;background:rgba(148,163,184,.14);color:#dbeafe}.muted{color:var(--muted)}.empty{padding:24px;text-align:center;color:#cbd5e1;background:rgba(2,6,23,.55);border:1px dashed var(--line);border-radius:14px}
@media (max-width:900px){.tree-children{display:grid;grid-template-columns:1fr 1fr}.tree-children:before,.tree-child:before{display:none}.areas-grid{grid-template-columns:1fr}.topbar{align-items:flex-start;flex-direction:column}}
@media print{body{background:#fff;color:#111}.wrap{max-width:none}.panel,.area-card{box-shadow:none;background:#fff;color:#111;border-color:#999}.btn{display:none}.table{--bs-table-color:#111}.muted,.sub,.brand-sub,.area-count{color:#444!important}}
</style>
</head>
<body>
<div class="wrap">
  <header class="topbar">
    <div class="brand">
      <img src="<?= e($ESCUDO) ?>" alt="Escudo" onerror="this.onerror=null;this.src='<?= e($ASSET_WEB) ?>/img/EA.png';">
      <div>
        <div class="brand-title"><?= e($nombreUnidad) ?></div>
        <div class="brand-sub"><?= e($subnombreUnidad !== '' ? $subnombreUnidad : 'Cuadro de organizacion') ?></div>
      </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <button type="button" class="btn btn-outline-light btn-sm fw-bold" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
      <a href="operaciones.php" class="btn btn-success btn-sm fw-bold"><i class="bi bi-arrow-left"></i> Volver a S-3</a>
    </div>
  </header>

  <section class="panel">
    <div class="title"><i class="bi bi-diagram-3-fill"></i> CO - Cuadro de organizaci&oacute;n</div>
    <div class="sub">Personal tomado de la lista de personal y agrupado por areas/destinos internos. Direccion encabeza el arbol organizacional.</div>
    <div class="kpis">
      <div class="kpi"><div class="kpi-label">Total</div><div class="kpi-value"><?= (int)$totalGeneral ?></div></div>
      <div class="kpi"><div class="kpi-label">Oficiales</div><div class="kpi-value"><?= (int)$totalesJerarquia['OFICIAL'] ?></div></div>
      <div class="kpi"><div class="kpi-label">Suboficiales</div><div class="kpi-value"><?= (int)$totalesJerarquia['SUBOFICIAL'] ?></div></div>
      <div class="kpi"><div class="kpi-label">Soldados</div><div class="kpi-value"><?= (int)$totalesJerarquia['SOLDADO'] ?></div></div>
      <div class="kpi"><div class="kpi-label">Areas</div><div class="kpi-value"><?= count($areas) ?></div></div>
    </div>
  </section>

  <?php if ($mensajeError !== ''): ?>
    <div class="alert alert-warning"><?= e($mensajeError) ?></div>
  <?php elseif (empty($areas)): ?>
    <div class="empty">No hay personal cargado para esta unidad.</div>
  <?php else: ?>
    <section class="panel">
      <div class="title"><i class="bi bi-share"></i> Mapa conceptual</div>
      <div class="tree">
        <div class="tree-root">
          <div class="node"><i class="bi bi-star-fill"></i> DIRECCION <small><?= array_sum(array_map(static fn($a) => (int)$a['total'], $direccionAreas)) ?> efectivos</small></div>
        </div>
        <div class="tree-children">
          <?php foreach ($areasSubordinadas as $area): ?>
            <div class="tree-child">
              <div class="area-node">
                <div class="area-name"><?= e($area['nombre']) ?></div>
                <div class="area-count"><?= (int)$area['total'] ?> efectivos</div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section class="areas-grid">
      <?php foreach ($areas as $area): ?>
        <article class="area-card">
          <div class="area-head">
            <div>
              <h2><i class="bi bi-folder2-open"></i> <?= e($area['nombre']) ?></h2>
              <div class="area-stats">
                OF <?= (int)$area['jerarquias']['OFICIAL'] ?> - SOF <?= (int)$area['jerarquias']['SUBOFICIAL'] ?> - Sold <?= (int)$area['jerarquias']['SOLDADO'] ?> - Civ <?= (int)$area['jerarquias']['AGENTE_CIVIL'] ?>
              </div>
            </div>
            <span class="badge-rank"><?= (int)$area['total'] ?></span>
          </div>
          <div class="table-responsive">
            <table class="table table-sm">
              <thead>
                <tr>
                  <th>Grado</th>
                  <th>Apellido y nombre</th>
                  <th>Funcion / rol</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($area['personal'] as $p): ?>
                <?php
                  $rol = trim((string)($p['funcion'] ?? ''));
                  if ($rol === '') $rol = trim((string)($p['rol_administrativo'] ?? ''));
                  if ($rol === '') $rol = trim((string)($p['rol_combate'] ?? ''));
                ?>
                <tr>
                  <td><span class="badge-rank"><?= e(trim((string)($p['grado'] ?? ''))) ?></span></td>
                  <td>
                    <div class="fw-bold"><?= e($p['apellido_nombre'] ?? '') ?></div>
                    <div class="muted"><?= e(trim((string)($p['arma'] ?? ''))) ?><?= !empty($p['dni']) ? ' - DNI ' . e($p['dni']) : '' ?></div>
                  </td>
                  <td><?= $rol !== '' ? e($rol) : '<span class="muted">-</span>' ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </article>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
