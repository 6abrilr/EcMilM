<?php
declare(strict_types=1);

require_once __DIR__ . '/../../auth/bootstrap.php';
require_login();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/personal_civil_helper.php';

/** @var PDO $pdo */
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function norm_dni_local(string $d): string { return preg_replace('/\D+/', '', $d) ?? ''; }
function fmt_hours(?int $seconds): string {
    if ($seconds === null || $seconds <= 0) return 'Faltan datos';
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    return sprintf('%02d:%02d', $hours, $minutes);
}
function fmt_time(?string $dt): string {
    if (!$dt) return '';
    return substr($dt, 11, 5);
}
function time_to_datetime(?string $date, ?string $time): ?string {
    $date = trim((string)$date);
    $time = trim((string)$time);
    if ($date === '' || $time === '') return null;
    return $date . ' ' . (strlen($time) === 5 ? $time . ':00' : $time);
}
function iso_week_start(string $value): ?string {
    $value = trim($value);
    if ($value === '') return null;
    if (preg_match('/^(\d{4})-W(\d{2})$/', $value, $m)) {
        $dt = new DateTimeImmutable();
        $dt = $dt->setISODate((int)$m[1], (int)$m[2], 1);
        return $dt->format('Y-m-d');
    }
    return null;
}
function iso_week_value(string $date): string {
    $dt = new DateTimeImmutable($date);
    return $dt->format('o-\\WW');
}

personal_civil_ensure_tables($pdo);

$user = function_exists('current_user') ? current_user() : ($_SESSION['user'] ?? null);
$dniUsuario = norm_dni_local((string)($user['dni'] ?? $user['username'] ?? ''));
$personalId = 0;
$unidadPropia = 1;
$roleCodigo = 'USUARIO';

try {
    if ($dniUsuario !== '') {
        $st = $pdo->prepare("SELECT id, unidad_id FROM personal_unidad WHERE REPLACE(REPLACE(REPLACE(dni,'.',''),'-',''),' ','') = :dni LIMIT 1");
        $st->execute([':dni' => $dniUsuario]);
        if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $personalId = (int)$row['id'];
            $unidadPropia = (int)$row['unidad_id'];
        }
    }
    if ($personalId > 0) {
        $st = $pdo->prepare("SELECT r.codigo FROM personal_unidad pu INNER JOIN roles r ON r.id = pu.role_id WHERE pu.id = :pid LIMIT 1");
        $st->execute([':pid' => $personalId]);
        $roleCodigo = strtoupper((string)($st->fetchColumn() ?: 'USUARIO'));
    }
} catch (Throwable $e) {}

$esSuperAdmin = $roleCodigo === 'SUPERADMIN';
$esAdmin = $esSuperAdmin || $roleCodigo === 'ADMIN';

$NOMBRE = 'Escuela Militar de Montana';
$LEYENDA = '';
try {
    $st = $pdo->prepare("SELECT nombre_completo, subnombre FROM unidades WHERE id = :id LIMIT 1");
    $st->execute([':id' => $unidadPropia]);
    if ($u = $st->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($u['nombre_completo'])) $NOMBRE = (string)$u['nombre_completo'];
        if (!empty($u['subnombre'])) $LEYENDA = trim((string)$u['subnombre']);
    }
} catch (Throwable $e) {}

$SELF_WEB      = (string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
$BASE_DIR_WEB  = rtrim(str_replace('\\', '/', dirname($SELF_WEB)), '/');
$BASE_PUB_WEB  = rtrim(str_replace('\\', '/', dirname($BASE_DIR_WEB)), '/');
$BASE_APP_WEB  = rtrim(str_replace('\\', '/', dirname($BASE_PUB_WEB)), '/');
$ASSETS_WEB    = $BASE_APP_WEB . '/assets';
$IMG_BG        = $ASSETS_WEB . '/img/fondo.png';
$ESCUDO        = $ASSETS_WEB . '/img/ecmilm.png';

$requestYear = (string)($_POST['year'] ?? $_GET['year'] ?? date('Y'));
$selectedYear = preg_match('/^\d{4}$/', $requestYear) ? (int)$requestYear : (int)date('Y');
$q = trim((string)($_POST['q'] ?? $_GET['q'] ?? ''));
$dateFrom = trim((string)($_POST['desde'] ?? $_GET['desde'] ?? ''));
$dateTo = trim((string)($_POST['hasta'] ?? $_GET['hasta'] ?? ''));
$selectedDate = trim((string)($_POST['fecha'] ?? $_GET['fecha'] ?? ''));
$personExport = trim((string)($_GET['persona_dni'] ?? '__ALL__'));
$weekExport = trim((string)($_GET['semana'] ?? ''));
$weeklySummaryWeek = trim((string)($_GET['resumen_semana'] ?? ''));

$flashOk = '';
$flashErr = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $accion = (string)($_POST['accion_excel'] ?? '');
        if ($accion === 'guardar_manual') {
            if (!$esAdmin) throw new RuntimeException('Acceso restringido. Solo ADMIN o SUPERADMIN puede editar horarios.');
            $dni = norm_dni_local((string)($_POST['dni'] ?? ''));
            $fecha = trim((string)($_POST['fecha'] ?? ''));
            $bloqueado = (string)($_POST['permite_manual'] ?? '') !== '1';
            if ($bloqueado) throw new RuntimeException('Solo se puede editar cuando falta ingreso/egreso o los horarios estÃ¡n repetidos.');
            if ($dni === '' || $fecha === '') throw new RuntimeException('Faltan datos para guardar el ajuste manual.');
            $ingresoManual = time_to_datetime($fecha, (string)($_POST['ingreso_manual'] ?? ''));
            $egresoManual = time_to_datetime($fecha, (string)($_POST['egreso_manual'] ?? ''));
            $obs = trim((string)($_POST['observacion_manual'] ?? ''));
            $st = $pdo->prepare(
                "INSERT INTO personal_civil_resumen_manual
                    (unidad_id, dni, fecha, ingreso_manual, egreso_manual, observacion, updated_by_id)
                 VALUES
                    (:u, :dni, :fecha, :ingreso, :egreso, :obs, :by)
                 ON DUPLICATE KEY UPDATE
                    ingreso_manual = VALUES(ingreso_manual),
                    egreso_manual = VALUES(egreso_manual),
                    observacion = VALUES(observacion),
                    updated_by_id = VALUES(updated_by_id),
                    updated_at = CURRENT_TIMESTAMP"
            );
            $st->execute([
                ':u' => $unidadPropia,
                ':dni' => $dni,
                ':fecha' => $fecha,
                ':ingreso' => $ingresoManual,
                ':egreso' => $egresoManual,
                ':obs' => $obs !== '' ? $obs : null,
                ':by' => $personalId ?: null,
            ]);
            $flashOk = 'Horario manual actualizado correctamente.';
        } elseif ($accion === 'importar_padron' || $accion === 'importar_registros') {
            if (!$esAdmin) throw new RuntimeException('Acceso restringido. Solo ADMIN o SUPERADMIN puede importar archivos.');
            if (!isset($_FILES['archivo_excel']) || (int)$_FILES['archivo_excel']['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Seleccione un archivo Excel vÃ¡lido.');
            $tmp = (string)$_FILES['archivo_excel']['tmp_name'];
            $name = (string)$_FILES['archivo_excel']['name'];
            $ext = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, ['xlsx', 'xls'], true)) throw new RuntimeException('El archivo debe ser .xlsx o .xls.');
            if ($accion === 'importar_padron') {
                $res = personal_civil_import_padron($pdo, $unidadPropia, $personalId, $tmp, $name);
                $flashOk = 'PadrÃ³n civil importado. Registros procesados: ' . (int)$res['processed'] . '.';
            } else {
                $res = personal_civil_import_registros($pdo, $unidadPropia, $personalId, $tmp, $name);
                $flashOk = 'Registros de asistencia importados. Marcas procesadas: ' . (int)$res['processed'] . '.';
            }
        }
    } catch (Throwable $e) {
        $flashErr = $e->getMessage();
    }
}

$availableYears = [];
$activeCivilCount = 0;
$unmatchedCount = 0;
$padron = [];
$summaryRows = [];
$personTotals = [];
$latestLoadedDate = '';

try {
    $st = $pdo->prepare("SELECT DISTINCT YEAR(fecha) AS anio FROM personal_civil_registros WHERE unidad_id = :u ORDER BY anio DESC");
    $st->execute([':u' => $unidadPropia]);
    $availableYears = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
    if (!in_array($selectedYear, $availableYears, true)) {
        $availableYears[] = $selectedYear;
        rsort($availableYears);
    }

    $st = $pdo->prepare(
        "SELECT MAX(r.fecha)
         FROM personal_civil_registros r
         INNER JOIN personal_civil_padron pp ON pp.unidad_id=r.unidad_id AND pp.dni=r.dni AND pp.activo=1
         WHERE r.unidad_id = :u AND YEAR(r.fecha) = :anio"
    );
    $st->execute([':u' => $unidadPropia, ':anio' => $selectedYear]);
    $latestLoadedDate = (string)($st->fetchColumn() ?: '');
    if ($selectedDate === '' && $dateFrom === '' && $dateTo === '' && $latestLoadedDate !== '') {
        $selectedDate = $latestLoadedDate;
    }
    if ($selectedDate !== '') {
        $dateFrom = $selectedDate;
        $dateTo = $selectedDate;
    }
    if ($dateFrom === '') $dateFrom = sprintf('%04d-01-01', $selectedYear);
    if ($dateTo === '') $dateTo = sprintf('%04d-12-31', $selectedYear);
    if ($weekExport === '') {
        $weekExport = iso_week_value($selectedDate !== '' ? $selectedDate : ($latestLoadedDate !== '' ? $latestLoadedDate : sprintf('%04d-01-01', $selectedYear)));
    }
    if ($weeklySummaryWeek === '') {
        $weeklySummaryWeek = $weekExport;
    }

    $st = $pdo->prepare("SELECT COUNT(*) FROM personal_civil_padron WHERE unidad_id = :u AND activo = 1");
    $st->execute([':u' => $unidadPropia]);
    $activeCivilCount = (int)($st->fetchColumn() ?: 0);

    $st = $pdo->prepare("SELECT dni, apellido_nombre, destino_interno, horario_referencia FROM personal_civil_padron WHERE unidad_id = :u AND activo = 1 ORDER BY apellido_nombre");
    $st->execute([':u' => $unidadPropia]);
    $padron = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $params = [':u' => $unidadPropia, ':desde' => $dateFrom, ':hasta' => $dateTo, ':anio' => $selectedYear];
    $whereExtra = '';
    if ($q !== '') {
        $whereExtra = " AND (pp.apellido_nombre LIKE :q OR ds.dni LIKE :q OR pp.destino_interno LIKE :q)";
        $params[':q'] = '%' . $q . '%';
    }

    $sqlSummary =
        "SELECT ds.dni,
                COALESCE(pp.apellido_nombre, pu.apellido_nombre, ds.nombre_detectado) AS persona,
                COALESCE(pp.destino_interno, pu.destino_interno, '') AS destino_interno,
                COALESCE(pp.horario_referencia, '') AS horario_referencia,
                ds.fecha,
                COALESCE(m.ingreso_manual, ds.ingreso) AS ingreso,
                COALESCE(m.egreso_manual, ds.egreso) AS egreso,
                ds.marcas,
                m.observacion AS observacion_manual,
                CASE
                    WHEN COALESCE(m.ingreso_manual, ds.ingreso) IS NOT NULL
                     AND COALESCE(m.egreso_manual, ds.egreso) IS NOT NULL
                     AND COALESCE(m.ingreso_manual, ds.ingreso) <> COALESCE(m.egreso_manual, ds.egreso)
                    THEN TIMESTAMPDIFF(SECOND, COALESCE(m.ingreso_manual, ds.ingreso), COALESCE(m.egreso_manual, ds.egreso))
                    ELSE NULL
                END AS segundos_trabajados,
                CASE
                    WHEN COALESCE(m.ingreso_manual, ds.ingreso) IS NULL
                      OR COALESCE(m.egreso_manual, ds.egreso) IS NULL
                      OR COALESCE(m.ingreso_manual, ds.ingreso) = COALESCE(m.egreso_manual, ds.egreso)
                    THEN 1 ELSE 0
                END AS permite_manual
         FROM (
            SELECT r.unidad_id, r.dni, r.fecha, MIN(r.fecha_hora) AS ingreso, MAX(r.fecha_hora) AS egreso, COUNT(*) AS marcas, MAX(r.nombre_detectado) AS nombre_detectado
            FROM personal_civil_registros r
            WHERE r.unidad_id = :u AND YEAR(r.fecha) = :anio AND r.fecha BETWEEN :desde AND :hasta
            GROUP BY r.unidad_id, r.dni, r.fecha
         ) ds
         INNER JOIN personal_civil_padron pp ON pp.unidad_id = ds.unidad_id AND pp.dni = ds.dni AND pp.activo = 1
         LEFT JOIN personal_unidad pu ON pu.id = pp.personal_id
         LEFT JOIN personal_civil_resumen_manual m ON m.unidad_id = ds.unidad_id AND m.dni = ds.dni AND m.fecha = ds.fecha
         WHERE 1=1" . $whereExtra .
        " ORDER BY ds.fecha DESC, persona ASC";
    $st = $pdo->prepare($sqlSummary);
    $st->execute($params);
    $summaryRows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $weeklyStart = iso_week_start($weeklySummaryWeek);
    if ($weeklyStart !== null) {
        $weeklyEnd = (new DateTimeImmutable($weeklyStart))->modify('+6 days')->format('Y-m-d');
        $weeklyParams = [':u' => $unidadPropia, ':anio' => $selectedYear, ':desde' => $weeklyStart, ':hasta' => $weeklyEnd];
        $weeklyWhereExtra = '';
        if ($q !== '') {
            $weeklyWhereExtra = " AND (pp.apellido_nombre LIKE :q OR ds.dni LIKE :q OR pp.destino_interno LIKE :q)";
            $weeklyParams[':q'] = '%' . $q . '%';
        }
        $sqlTotals = "SELECT persona, dni, COUNT(*) AS dias_con_marca, SUM(COALESCE(segundos_trabajados,0)) AS segundos_periodo FROM (
            SELECT ds.dni,
                   COALESCE(pp.apellido_nombre, pu.apellido_nombre, ds.nombre_detectado) AS persona,
                   COALESCE(pp.destino_interno, pu.destino_interno, '') AS destino_interno,
                   CASE
                       WHEN COALESCE(m.ingreso_manual, ds.ingreso) IS NOT NULL
                        AND COALESCE(m.egreso_manual, ds.egreso) IS NOT NULL
                        AND COALESCE(m.ingreso_manual, ds.ingreso) <> COALESCE(m.egreso_manual, ds.egreso)
                       THEN TIMESTAMPDIFF(SECOND, COALESCE(m.ingreso_manual, ds.ingreso), COALESCE(m.egreso_manual, ds.egreso))
                       ELSE NULL
                   END AS segundos_trabajados
            FROM (
                SELECT r.unidad_id, r.dni, r.fecha, MIN(r.fecha_hora) AS ingreso, MAX(r.fecha_hora) AS egreso, COUNT(*) AS marcas, MAX(r.nombre_detectado) AS nombre_detectado
                FROM personal_civil_registros r
                WHERE r.unidad_id = :u AND YEAR(r.fecha) = :anio AND r.fecha BETWEEN :desde AND :hasta
                GROUP BY r.unidad_id, r.dni, r.fecha
            ) ds
            INNER JOIN personal_civil_padron pp ON pp.unidad_id = ds.unidad_id AND pp.dni = ds.dni AND pp.activo = 1
            LEFT JOIN personal_unidad pu ON pu.id = pp.personal_id
            LEFT JOIN personal_civil_resumen_manual m ON m.unidad_id = ds.unidad_id AND m.dni = ds.dni AND m.fecha = ds.fecha
            WHERE 1=1" . $weeklyWhereExtra . "
        ) x GROUP BY dni, persona ORDER BY persona ASC";
        $st = $pdo->prepare($sqlTotals);
        $st->execute($weeklyParams);
        $personTotals = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } else {
        $personTotals = [];
    }

    $st = $pdo->prepare("SELECT COUNT(DISTINCT r.dni) FROM personal_civil_registros r LEFT JOIN personal_civil_padron pp ON pp.unidad_id = r.unidad_id AND pp.dni = r.dni AND pp.activo = 1 WHERE r.unidad_id = :u AND YEAR(r.fecha)=:anio AND r.fecha BETWEEN :desde AND :hasta AND pp.id IS NULL");
    $st->execute([':u' => $unidadPropia, ':anio' => $selectedYear, ':desde' => $dateFrom, ':hasta' => $dateTo]);
    $unmatchedCount = (int)($st->fetchColumn() ?: 0);
} catch (Throwable $e) {
    $flashErr = $flashErr !== '' ? $flashErr : $e->getMessage();
}

if (isset($_GET['export']) && $_GET['export'] === 'xls') {
    $weekStart = iso_week_start($weekExport);
    if ($weekStart === null) {
        http_response_code(400);
        exit('Falta la semana para exportar.');
    }
    $weekEnd = (new DateTimeImmutable($weekStart))->modify('+6 days')->format('Y-m-d');
    $allPeopleExport = $personExport === '' || $personExport === '__ALL__';
    $dniWhere = $allPeopleExport ? '' : ' AND r.dni = :dni ';
    $sqlExport = "SELECT ds.fecha, ds.dni, COALESCE(pp.apellido_nombre, pu.apellido_nombre, ds.nombre_detectado) AS persona, COALESCE(pp.destino_interno, pu.destino_interno, '') AS destino_interno, COALESCE(pp.horario_referencia, '') AS horario_referencia, COALESCE(m.ingreso_manual, ds.ingreso) AS ingreso, COALESCE(m.egreso_manual, ds.egreso) AS egreso, ds.marcas, m.observacion AS observacion_manual, CASE WHEN COALESCE(m.ingreso_manual, ds.ingreso) IS NOT NULL AND COALESCE(m.egreso_manual, ds.egreso) IS NOT NULL AND COALESCE(m.ingreso_manual, ds.ingreso) <> COALESCE(m.egreso_manual, ds.egreso) THEN TIMESTAMPDIFF(SECOND, COALESCE(m.ingreso_manual, ds.ingreso), COALESCE(m.egreso_manual, ds.egreso)) ELSE NULL END AS segundos_trabajados FROM (SELECT r.unidad_id, r.dni, r.fecha, MIN(r.fecha_hora) AS ingreso, MAX(r.fecha_hora) AS egreso, COUNT(*) AS marcas, MAX(r.nombre_detectado) AS nombre_detectado FROM personal_civil_registros r WHERE r.unidad_id = :u " . $dniWhere . " AND r.fecha BETWEEN :desde AND :hasta GROUP BY r.unidad_id, r.dni, r.fecha) ds INNER JOIN personal_civil_padron pp ON pp.unidad_id = ds.unidad_id AND pp.dni = ds.dni AND pp.activo = 1 LEFT JOIN personal_unidad pu ON pu.id = pp.personal_id LEFT JOIN personal_civil_resumen_manual m ON m.unidad_id = ds.unidad_id AND m.dni = ds.dni AND m.fecha = ds.fecha ORDER BY persona ASC, ds.fecha ASC";
    $exportParams = [':u' => $unidadPropia, ':desde' => $weekStart, ':hasta' => $weekEnd];
    if (!$allPeopleExport) {
        $exportParams[':dni'] = norm_dni_local($personExport);
    }
    $st = $pdo->prepare($sqlExport);
    $st->execute($exportParams);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $exportSuffix = $allPeopleExport ? 'completo' : norm_dni_local($personExport);
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="personal_civil_' . $exportSuffix . '_' . $weekStart . '.xls"');
    echo "\xEF\xBB\xBF";
    echo '<html><head><meta charset="UTF-8"></head><body>';
    echo '<table border="1"><tr><th>Fecha</th><th>DNI</th><th>Personal</th><th>Destino</th><th>Horario referencia</th><th>Ingreso</th><th>Egreso</th><th>Marcas</th><th>Horas</th><th>Observacion manual</th></tr>';
    foreach ($rows as $row) {
        echo '<tr>';
        echo '<td>' . e($row['fecha']) . '</td>';
        echo '<td>' . e($row['dni']) . '</td>';
        echo '<td>' . e($row['persona']) . '</td>';
        echo '<td>' . e($row['destino_interno']) . '</td>';
        echo '<td>' . e($row['horario_referencia']) . '</td>';
        echo '<td>' . e(fmt_time($row['ingreso'])) . '</td>';
        echo '<td>' . e(fmt_time($row['egreso'])) . '</td>';
        echo '<td>' . e((string)$row['marcas']) . '</td>';
        echo '<td>' . e(fmt_hours(isset($row['segundos_trabajados']) ? (int)$row['segundos_trabajados'] : null)) . '</td>';
        echo '<td>' . e((string)($row['observacion_manual'] ?? '')) . '</td>';
        echo '</tr>';
    }
    echo '</table></body></html>';
    exit;
}
?>
<!doctype html>
<html lang="es"><head><meta charset="utf-8"><title>Ingreso y egreso de personal civil</title><meta name="viewport" content="width=device-width, initial-scale=1"><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"><link rel="icon" href="<?= e($ESCUDO) ?>"><style>:root{--bg-dark:#020617;--card-bg:rgba(15,23,42,.94);--card-border:rgba(148,163,184,.40);--text-main:#e5e7eb;--text-muted:#9ca3af}*{box-sizing:border-box}body{min-height:100vh;margin:0;color:var(--text-main);background:linear-gradient(160deg, rgba(0,0,0,.82), rgba(2,6,23,.88)),url("<?= e($IMG_BG) ?>") center/cover fixed;background-color:var(--bg-dark);font-family:system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,sans-serif}body::before{content:"";position:fixed;inset:0;background:radial-gradient(circle at top, rgba(15,23,42,.72), rgba(15,23,42,.95));pointer-events:none;z-index:-1}.page-wrap{padding:24px 16px 32px}.container-main{max-width:1500px;margin:0 auto}.brand-hero{padding:14px 0 10px}.hero-inner{display:flex;align-items:center;justify-content:space-between;gap:16px}.brand-left{display:flex;align-items:center;gap:14px}.brand-logo{height:56px;width:auto;filter:drop-shadow(0 0 10px rgba(0,0,0,.8))}.brand-title{font-weight:800;font-size:1.1rem;letter-spacing:.03em}.brand-sub{font-size:.8rem;color:#cbd5f5}.header-back{display:flex;flex-wrap:wrap;gap:8px}.btn-ghost{border-radius:999px;border:1px solid rgba(148,163,184,.55);background:rgba(15,23,42,.8);color:var(--text-main);font-size:.8rem;font-weight:700;padding:.35rem 1rem;text-decoration:none;box-shadow:0 10px 30px rgba(0,0,0,.55)}.btn-ghost:hover{background:rgba(30,64,175,.9);color:#fff}.section-header{margin-bottom:22px}.section-kicker .sk-text{font-size:1.05rem;font-weight:900;letter-spacing:.18em;text-transform:uppercase;background:linear-gradient(90deg,#38bdf8,#22c55e);-webkit-background-clip:text;-webkit-text-fill-color:transparent;filter:drop-shadow(0 0 6px rgba(30,58,138,.55));padding-bottom:3px;border-bottom:2px solid rgba(34,197,94,.45);display:inline-block}.section-title{font-size:1.7rem;font-weight:800;margin-top:4px}.section-sub{font-size:.92rem;color:#cbd5f5;max-width:820px}.panel{background:var(--card-bg);border:1px solid var(--card-border);border-radius:18px;padding:18px 22px 22px;box-shadow:0 18px 40px rgba(0,0,0,.75), inset 0 1px 0 rgba(255,255,255,.05);margin-bottom:18px;backdrop-filter:blur(8px)}.panel-title{font-size:1.05rem;font-weight:900;margin-bottom:8px}.panel-sub{font-size:.86rem;color:#cbd5f5;margin-bottom:14px}.stats-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-bottom:18px}.stat-card{border-radius:16px;padding:14px 16px;background:radial-gradient(circle at top left, rgba(56,189,248,.20), transparent 65%), rgba(15,23,42,.86);border:1px solid rgba(148,163,184,.35)}.stat-label{color:var(--text-muted);font-size:.78rem;text-transform:uppercase;letter-spacing:.1em}.stat-value{font-size:1.5rem;font-weight:850;margin-top:4px}.table-dark{--bs-table-bg:rgba(15,23,42,.78);--bs-table-border-color:rgba(148,163,184,.18)}.table thead th{color:#bfdbfe;font-size:.78rem;text-transform:uppercase;letter-spacing:.06em}.small-muted{color:#94a3b8;font-size:.78rem}.pill{display:inline-flex;align-items:center;gap:.35rem;padding:.2rem .55rem;border-radius:999px;border:1px solid rgba(148,163,184,.28);background:rgba(30,41,59,.65);font-size:.76rem}.inline-form input[type=time]{min-width:100px}details>summary{cursor:pointer;list-style:none}details>summary::-webkit-details-marker{display:none}</style></head><body>
<header class="brand-hero"><div class="hero-inner container-main"><div class="brand-left"><img src="<?= e($ESCUDO) ?>" class="brand-logo" alt="Escudo" onerror="this.style.display='none'"><div><div class="brand-title"><?= e($NOMBRE) ?></div><?php if ($LEYENDA !== ''): ?><div class="brand-sub"><?= e($LEYENDA) ?></div><?php endif; ?></div></div><div class="header-back"><a href="personal.php" class="btn-ghost">Volver a Personal</a><a href="personal_lista.php" class="btn-ghost">Ver lista de personal</a></div></div></header>
<div class="page-wrap"><div class="container-main"><div class="section-header"><div class="section-kicker"><span class="sk-text">S-1 Â· PERSONAL</span></div><div class="section-title">Ingreso y egreso de personal civil</div><p class="section-sub mb-0">Por defecto se muestra el Último dí­a cargado del año seleccionado. Podes cambiar a otro dí­a o trabajar por rango. Solo se habilita edición manual cuando falta ingreso/egreso o cuando ambos horarios quedaron iguales.</p></div><?php if ($flashOk !== ''): ?><div class="alert alert-success"><?= e($flashOk) ?></div><?php endif; ?><?php if ($flashErr !== ''): ?><div class="alert alert-danger"><?= e($flashErr) ?></div><?php endif; ?><div class="stats-grid"><div class="stat-card"><div class="stat-label">AÃ±o del resumen</div><div class="stat-value"><?= e((string)$selectedYear) ?></div></div><div class="stat-card"><div class="stat-label">Civiles activos en padrón</div><div class="stat-value"><?= e((string)$activeCivilCount) ?></div></div></div>
<div class="row g-3"><div class="col-lg-6"><div class="panel"><div class="panel-title">Importar padrón civil</div><div class="panel-sub">Se guarda en base de datos, tabla <code>personal_civil_padron</code>.</div><form method="post" enctype="multipart/form-data" class="row g-2 align-items-end"><input type="hidden" name="accion_excel" value="importar_padron"><input type="hidden" name="year" value="<?= e((string)$selectedYear) ?>"><input type="hidden" name="fecha" value="<?= e($selectedDate) ?>"><input type="hidden" name="desde" value="<?= e($dateFrom) ?>"><input type="hidden" name="hasta" value="<?= e($dateTo) ?>"><input type="hidden" name="q" value="<?= e($q) ?>"><div class="col-12"><label class="form-label">Archivo Excel</label><input type="file" name="archivo_excel" class="form-control" accept=".xlsx,.xls" required></div><div class="col-12"><button type="submit" class="btn btn-success">Importar padrón</button></div></form></div></div><div class="col-lg-6"><div class="panel"><div class="panel-title">Importar registros de ingreso/egreso</div><div class="panel-sub">Las marcas crudas se guardan en <code>personal_civil_registros</code>. Los ajustes manuales se guardan en <code>personal_civil_resumen_manual</code>.</div><form method="post" enctype="multipart/form-data" class="row g-2 align-items-end"><input type="hidden" name="accion_excel" value="importar_registros"><input type="hidden" name="year" value="<?= e((string)$selectedYear) ?>"><input type="hidden" name="fecha" value="<?= e($selectedDate) ?>"><input type="hidden" name="desde" value="<?= e($dateFrom) ?>"><input type="hidden" name="hasta" value="<?= e($dateTo) ?>"><input type="hidden" name="q" value="<?= e($q) ?>"><div class="col-12"><label class="form-label">Archivo Excel</label><input type="file" name="archivo_excel" class="form-control" accept=".xlsx,.xls" required></div><div class="col-12"><button type="submit" class="btn btn-primary">Importar registros</button></div></form></div></div></div>
<div class="panel"><div class="panel-title">Filtros del resumen</div><form method="get" class="row g-2 align-items-end"><div class="col-md-2"><label class="form-label">Año</label><select name="year" class="form-select"><?php foreach ($availableYears as $year): ?><option value="<?= e((string)$year) ?>" <?= $year === $selectedYear ? 'selected' : '' ?>><?= e((string)$year) ?></option><?php endforeach; ?></select></div><div class="col-md-2"><label class="form-label">Dí­a</label><input type="date" name="fecha" value="<?= e($selectedDate) ?>" class="form-control"></div><div class="col-md-2"><label class="form-label">Desde</label><input type="date" name="desde" value="<?= e($selectedDate === '' ? $dateFrom : '') ?>" class="form-control"></div><div class="col-md-2"><label class="form-label">Hasta</label><input type="date" name="hasta" value="<?= e($selectedDate === '' ? $dateTo : '') ?>" class="form-control"></div><div class="col-md-3"><label class="form-label">Buscar</label><input type="text" name="q" value="<?= e($q) ?>" class="form-control" placeholder="DNI, apellido o destino"></div><div class="col-md-1 d-grid"><button type="submit" class="btn btn-outline-light">Aplicar</button></div></form><div class="small-muted mt-2">Ãšltimo dÃ­a cargado en <?= e((string)$selectedYear) ?>: <strong><?= e($latestLoadedDate !== '' ? $latestLoadedDate : 'sin datos') ?></strong></div></div>
<div class="row g-3"><div class="col-12"><div class="panel"><div class="panel-title">Resumen diario <?= e((string)$selectedYear) ?></div><div class="panel-sub">Si elegís un dÍ­a, la tabla muestra solamente ese dÍ­a. Si dejas el dí­a vací­o y cargas desde/hasta, te muestra el rango.</div><div class="table-responsive"><table class="table table-dark table-hover align-middle"><thead><tr><th>Fecha</th><th>DNI</th><th>Personal</th><th>Destino</th><th>Horario ref.</th><th>Ingreso</th><th>Egreso</th><th>Marcas</th><th>Horas</th><?php if ($esAdmin): ?><th>Ajuste manual</th><?php endif; ?></tr></thead><tbody><?php if ($summaryRows === []): ?><tr><td colspan="<?= $esAdmin ? '10' : '9' ?>" class="text-center text-secondary">No hay registros para el filtro seleccionado.</td></tr><?php else: ?><?php foreach ($summaryRows as $row): ?><tr><td><?= e((string)$row['fecha']) ?></td><td><?= e((string)$row['dni']) ?></td><td><?= e((string)$row['persona']) ?></td><td><?= e((string)$row['destino_interno']) ?></td><td><span class="pill"><?= e((string)$row['horario_referencia']) ?></span></td><td><?= e(fmt_time((string)$row['ingreso'])) ?></td><td><?= e(fmt_time((string)$row['egreso'])) ?></td><td><?= e((string)$row['marcas']) ?></td><td><strong><?= e(fmt_hours(isset($row['segundos_trabajados']) ? (int)$row['segundos_trabajados'] : null)) ?></strong></td><?php if ($esAdmin): ?><td style="min-width:310px;"><?php if ((int)$row['permite_manual'] === 1): ?><form method="post" class="inline-form d-flex flex-wrap gap-2 align-items-end"><input type="hidden" name="accion_excel" value="guardar_manual"><input type="hidden" name="year" value="<?= e((string)$selectedYear) ?>"><input type="hidden" name="fecha" value="<?= e($selectedDate) ?>"><input type="hidden" name="desde" value="<?= e($dateFrom) ?>"><input type="hidden" name="hasta" value="<?= e($dateTo) ?>"><input type="hidden" name="q" value="<?= e($q) ?>"><input type="hidden" name="dni" value="<?= e((string)$row['dni']) ?>"><input type="hidden" name="permite_manual" value="1"><input type="hidden" name="fecha_fila" value="<?= e((string)$row['fecha']) ?>"><div><label class="small-muted d-block">Ingreso</label><input type="time" name="ingreso_manual" value="<?= e(fmt_time((string)$row['ingreso'])) ?>" class="form-control form-control-sm"></div><div><label class="small-muted d-block">Egreso</label><input type="time" name="egreso_manual" value="<?= e(fmt_time((string)$row['egreso'])) ?>" class="form-control form-control-sm"></div><div class="flex-grow-1"><label class="small-muted d-block">Obs.</label><input type="text" name="observacion_manual" value="<?= e((string)($row['observacion_manual'] ?? '')) ?>" class="form-control form-control-sm" placeholder="Opcional"></div><div><button type="submit" class="btn btn-sm btn-success">Guardar</button></div></form><?php else: ?><span class="small-muted">Bloqueado: ya tiene ingreso y egreso validos.</span><?php endif; ?></td><?php endif; ?></tr><?php endforeach; ?><?php endif; ?></tbody></table></div></div></div></div><div class="row g-3"><div class="col-12"><details class="panel"><summary class="panel-title">Exportar por semana y persona</summary><div class="panel-sub">Podés exportar toda la semana completa o una sola persona en formato Excel (.xls).</div><form method="get" class="row g-2 align-items-end"><input type="hidden" name="export" value="xls"><input type="hidden" name="year" value="<?= e((string)$selectedYear) ?>"><div class="col-md-6"><label class="form-label">Persona</label><select name="persona_dni" class="form-select"><option value="__ALL__" <?= ($personExport === '__ALL__' || $personExport === '') ? 'selected' : '' ?>>Todo el personal civil</option><?php foreach ($padron as $row): ?><option value="<?= e((string)$row['dni']) ?>" <?= $personExport === (string)$row['dni'] ? 'selected' : '' ?>><?= e((string)$row['apellido_nombre']) ?> Â· <?= e((string)$row['dni']) ?></option><?php endforeach; ?></select></div><div class="col-md-4"><label class="form-label">Semana</label><input type="week" name="semana" value="<?= e($weekExport) ?>" class="form-control" required></div><div class="col-md-2 d-grid"><button type="submit" class="btn btn-outline-success">Exportar XLS</button></div></form></details></div><div class="col-12"><details class="panel"><summary class="panel-title">Horas por persona</summary><div class="panel-sub">Resumen semanal desplegable. Elegí la semana y te muestra días con marca y horas trabajadas por cada persona.</div><form method="get" class="row g-2 align-items-end mb-3"><input type="hidden" name="year" value="<?= e((string)$selectedYear) ?>"><input type="hidden" name="fecha" value="<?= e($selectedDate) ?>"><input type="hidden" name="desde" value="<?= e($selectedDate === '' ? $dateFrom : '') ?>"><input type="hidden" name="hasta" value="<?= e($selectedDate === '' ? $dateTo : '') ?>"><input type="hidden" name="q" value="<?= e($q) ?>"><div class="col-md-4"><label class="form-label">Semana a resumir</label><input type="week" name="resumen_semana" value="<?= e($weeklySummaryWeek) ?>" class="form-control"></div><div class="col-md-2 d-grid"><button type="submit" class="btn btn-outline-light">Ver resumen semanal</button></div></form><div class="table-responsive"><table class="table table-dark align-middle"><thead><tr><th>Personal</th><th>Días</th><th>Horas</th></tr></thead><tbody><?php if ($personTotals === []): ?><tr><td colspan="3" class="text-center text-secondary">Sin datos para la semana elegida.</td></tr><?php else: ?><?php foreach ($personTotals as $row): ?><tr><td><div><?= e((string)$row['persona']) ?></div><div class="small-muted"><?= e((string)$row['dni']) ?></div></td><td><?= e((string)$row['dias_con_marca']) ?></td><td><strong><?= e(fmt_hours((int)$row['segundos_periodo'])) ?></strong></td></tr><?php endforeach; ?><?php endif; ?></tbody></table></div></details></div><div class="col-12"><details class="panel"><summary class="panel-title">Padrón civil activo</summary><div class="panel-sub">DNI sin padrón activo detectados en <?= e((string)$selectedYear) ?>: <strong><?= e((string)$unmatchedCount) ?></strong></div><div class="table-responsive"><table class="table table-dark align-middle"><thead><tr><th>DNI</th><th>Personal</th><th>Destino</th></tr></thead><tbody><?php if ($padron === []): ?><tr><td colspan="3" class="text-center text-secondary">TodavÃ­a no hay padrÃ³n civil importado.</td></tr><?php else: ?><?php foreach ($padron as $row): ?><tr><td><?= e((string)$row['dni']) ?></td><td><div><?= e((string)$row['apellido_nombre']) ?></div><div class="small-muted"><?= e((string)$row['horario_referencia']) ?></div></td><td><?= e((string)$row['destino_interno']) ?></td></tr><?php endforeach; ?><?php endif; ?></tbody></table></div></details></div></div></body></html>
