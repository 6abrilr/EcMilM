<?php
/**
 * operaciones_educacion_tropa.php
 * Panel Educación Operacional de la Tropa:
 * - HOME: tarjetas Ciclo I/II/III/NIA + 5ta tarjeta "Personal"
 * - CICLO: CRUD de actividades por ciclo
 * - PERSONAL: matriz (personal_unidad) con cumplimiento por ciclo (I/II/III/NIA) + "Cumplido" total
 *
 * Tablas (auto-crea):
 * - educacion_tropa_actividades
 * - educacion_tropa_personal_ciclos
 *
 * ✅ Personal (MATRIZ):
 * - SOLO SOLDADOS (personal_unidad.jerarquia LIKE 'SOLDAD%')  // ✅ robusto: SOLDADO / SOLDADOS / variantes
 */

declare(strict_types=1);

$OFFLINE_MODE = false;
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

/* =========================
   Utils
========================= */
function find_first_existing(array $candidates): string {
  foreach ($candidates as $p) {
    if (is_file($p)) return $p;
  }
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Falta archivo requerido. Probé:\n" . implode("\n", $candidates);
  exit;
}
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function qi(string $name): string { return '`' . str_replace('`','``',$name) . '`'; }

function current_db(PDO $pdo): string {
  try { $v = $pdo->query("SELECT DATABASE()")->fetchColumn(); return is_string($v) ? $v : ''; }
  catch (Throwable $e) { return ''; }
}
function table_exists(PDO $pdo, string $table): bool {
  try { $st = $pdo->prepare("SHOW TABLES LIKE ?"); $st->execute([$table]); return (bool)$st->fetchColumn(); }
  catch (Throwable $e) { return false; }
}
function build_url(string $basePath, array $params = []): string {
  $q = http_build_query($params);
  return $q ? ($basePath . '?' . $q) : $basePath;
}

/* =========================
   Boot/Auth/DB — prioridad /ea
========================= */
$BOOT = find_first_existing([
  __DIR__ . '/../../../auth/bootstrap.php',
  __DIR__ . '/../../auth/bootstrap.php',
  __DIR__ . '/../auth/bootstrap.php',
  __DIR__ . '/../../../../auth/bootstrap.php',
]);
require_once $BOOT;

if (!$OFFLINE_MODE && function_exists('require_login')) {
  require_login();
}

$DB = find_first_existing([
  __DIR__ . '/../../../config/db.php',
  __DIR__ . '/../../config/db.php',
  __DIR__ . '/../config/db.php',
  __DIR__ . '/../../../../config/db.php',
]);
require_once $DB;

/** @var PDO|null $pdo */
if (!isset($pdo) || !($pdo instanceof PDO)) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo "No se encontró \$pdo (PDO) en config/db.php";
  exit;
}

/* =========================
   Contexto usuario / unidad
========================= */
$user = function_exists('current_user') ? (current_user() ?: []) : ($_SESSION['user'] ?? []);
$CURRENT_UNIDAD_ID = (int)($user['unidad_id'] ?? $_SESSION['unidad_id'] ?? 0);
$DB_NAME = current_db($pdo);

/* =========================
   Volver dinámico (S1 / S3)
========================= */
$from = strtolower(trim((string)($_GET['from'] ?? '')));
if (!in_array($from, ['s1','s3'], true)) $from = '';
if ($from === '' && !empty($_SERVER['HTTP_REFERER'])) {
  $refPath = parse_url((string)$_SERVER['HTTP_REFERER'], PHP_URL_PATH) ?: '';
  $refBase = basename($refPath);
  if ($refBase === 'personal.php') $from = 's1';
  if ($refBase === 'operaciones.php') $from = 's3';
}
if ($from === '') $from = 's3';

$backHref  = ($from === 's1') ? 'personal.php' : 'operaciones.php';
$backLabel = ($from === 's1') ? '⬅ Volver a Personal' : '⬅ Volver a Operaciones';
$kickerTxt = ($from === 's1') ? 'S-1 · PERSONAL' : 'S-3 · OPERACIONES';

/* =========================
   Assets
========================= */
$PUBLIC_URL   = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$WEB_ROOT_URL = rtrim(str_replace('\\','/', dirname(dirname($PUBLIC_URL))), '/');
if ($WEB_ROOT_URL === '/') $WEB_ROOT_URL = '';
$ASSETS_URL = ($WEB_ROOT_URL === '' ? '' : $WEB_ROOT_URL) . '/assets';

$IMG_BG = $ASSETS_URL . '/img/fondo.png';
$ESCUDO = $ASSETS_URL . '/img/ecmilm.png';
$FAVICO = $ASSETS_URL . '/img/ecmilm.png';

/* Path real del script (con carpeta) para action de forms */
$SELF_PATH = (string)($_SERVER['PHP_SELF'] ?? 'operaciones_educacion_tropa.php');

/* =========================
   Router
   - HOME (default)
   - CICLO: ?ciclo=1|2|3|nia
   - PERSONAL: ?tab=personal
========================= */
$cicloParam = strtolower(trim((string)($_GET['ciclo'] ?? '')));
$tabParam   = strtolower(trim((string)($_GET['tab'] ?? '')));

$view = 'home';
if (in_array($cicloParam, ['1','2','3','nia'], true)) $view = 'ciclo';
if ($tabParam === 'personal') $view = 'personal';

/* =========================
   CSRF
========================= */
if (empty($_SESSION['csrf_edu_tropa'])) $_SESSION['csrf_edu_tropa'] = bin2hex(random_bytes(16));
$CSRF = (string)$_SESSION['csrf_edu_tropa'];

/* =========================
   Tablas del módulo (auto-crea)
========================= */
function ensure_tables(PDO $pdo): void {
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS `educacion_tropa_actividades` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      `unidad_id` INT NOT NULL DEFAULT 0,
      `ciclo` VARCHAR(10) NOT NULL,
      `sem` VARCHAR(20) NOT NULL DEFAULT '',
      `fecha` DATE NULL,
      `tema` VARCHAR(200) NOT NULL,
      `responsable` VARCHAR(200) NOT NULL DEFAULT '',
      `participantes` VARCHAR(200) NOT NULL DEFAULT '',
      `lugar` VARCHAR(200) NOT NULL DEFAULT '',
      `cumplio` TINYINT(1) NOT NULL DEFAULT 0,
      `doc` VARCHAR(255) NOT NULL DEFAULT '',
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` DATETIME NULL DEFAULT NULL,
      `created_by` BIGINT NULL DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `idx_ciclo_unidad` (`unidad_id`,`ciclo`),
      KEY `idx_fecha` (`fecha`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
  ");

  $pdo->exec("
    CREATE TABLE IF NOT EXISTS `educacion_tropa_personal_ciclos` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      `unidad_id` INT NOT NULL DEFAULT 0,
      `personal_unidad_id` BIGINT NOT NULL,
      `ciclo` VARCHAR(10) NOT NULL,
      `cumplido` TINYINT(1) NOT NULL DEFAULT 0,
      `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_by` BIGINT NULL DEFAULT NULL,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uq_personal_ciclo_unidad` (`unidad_id`,`personal_unidad_id`,`ciclo`),
      KEY `idx_ciclo_unidad` (`unidad_id`,`ciclo`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
  ");
}
ensure_tables($pdo);

/* =========================
   Meta ciclos
========================= */
$CICLOS = [
  '1'   => ['kicker'=>'CICLO I',   'pill'=>'Básico',     'icon'=>'I',   'sub'=>'Instrucción básica de la tropa.'],
  '2'   => ['kicker'=>'CICLO II',  'pill'=>'Intermedio', 'icon'=>'II',  'sub'=>'Instrucción técnica y táctica intermedia.'],
  '3'   => ['kicker'=>'CICLO III', 'pill'=>'Avanzado',   'icon'=>'III', 'sub'=>'Instrucción avanzada y ejercicios de campaña.'],
  'nia' => ['kicker'=>'N.I.A.',    'pill'=>'Especial',   'icon'=>'★',   'sub'=>'No Instrucción Asignada / actividades especiales.'],
];

/* =========================
   Columnas de personal_unidad (con diagnóstico)
========================= */
function get_table_columns_verbose(PDO $pdo, string $table): array {
  $out = ['cols'=>[], 'error'=>''];
  try {
    $st = $pdo->query("SHOW COLUMNS FROM " . qi($table));
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $cols = [];
    foreach ($rows as $r) $cols[] = (string)($r['Field'] ?? '');
    $out['cols'] = array_values(array_filter($cols, fn($c)=>$c!=='' ));
    return $out;
  } catch (Throwable $e) {
    $out['error'] = $e->getMessage();
    return $out;
  }
}
function pick_first_existing(array $candidates, array $cols): string {
  foreach ($candidates as $c) if (in_array($c, $cols, true)) return $c;
  return '';
}

$puInfo = get_table_columns_verbose($pdo, 'personal_unidad');
$PU_COLS = $puInfo['cols'];
$PU_ERR  = $puInfo['error'];
$PU_TABLE_EXISTS = table_exists($pdo, 'personal_unidad');

$PU_ID     = in_array('id', $PU_COLS, true) ? 'id' : ($PU_COLS[0] ?? 'id');
$PU_UNIDAD = pick_first_existing(['unidad_id','id_unidad','unidad'], $PU_COLS);
$PU_DNI    = pick_first_existing(['dni','documento','nro_documento','dni_num','nro_doc'], $PU_COLS);
$PU_GRADO  = pick_first_existing(['grado'], $PU_COLS);

$PU_APYN   = pick_first_existing(['apellido_nombre','apellido_y_nombre','apellidoynombre','aynom','nombre_completo'], $PU_COLS);
$PU_APE    = pick_first_existing(['apellido','apellidos'], $PU_COLS);
$PU_NOM    = pick_first_existing(['nombre','nombres'], $PU_COLS);

$HAS_JERARQUIA = in_array('jerarquia', $PU_COLS, true);

/* expresión nombre */
$NAME_EXPR_SQL = '';
if ($PU_APYN !== '') {
  $NAME_EXPR_SQL = qi($PU_APYN);
} elseif ($PU_APE !== '' && $PU_NOM !== '') {
  $NAME_EXPR_SQL = "CONCAT(" . qi($PU_APE) . ", ' ', " . qi($PU_NOM) . ")";
} elseif ($PU_APE !== '') {
  $NAME_EXPR_SQL = qi($PU_APE);
} elseif ($PU_NOM !== '') {
  $NAME_EXPR_SQL = qi($PU_NOM);
} else {
  $NAME_EXPR_SQL = qi($PU_ID);
}

/* =========================
   Helpers DB
========================= */
function fetch_actividades(PDO $pdo, string $ciclo, int $unidadId): array {
  $sql = "SELECT * FROM `educacion_tropa_actividades`
          WHERE `ciclo` = :ciclo AND `unidad_id` = :unidad
          ORDER BY COALESCE(`fecha`, '9999-12-31') ASC, `id` ASC";
  $st = $pdo->prepare($sql);
  $st->execute([':ciclo'=>$ciclo, ':unidad'=>$unidadId]);
  return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
function calc_kpi_from_db(PDO $pdo, string $ciclo, int $unidadId): array {
  $sql = "SELECT COUNT(*) AS total,
                 SUM(CASE WHEN cumplio=1 THEN 1 ELSE 0 END) AS ok
          FROM `educacion_tropa_actividades`
          WHERE `ciclo` = :ciclo AND `unidad_id` = :unidad";
  $st = $pdo->prepare($sql);
  $st->execute([':ciclo'=>$ciclo, ':unidad'=>$unidadId]);
  $r = $st->fetch(PDO::FETCH_ASSOC) ?: ['total'=>0,'ok'=>0];
  $total = (int)($r['total'] ?? 0);
  $ok    = (int)($r['ok'] ?? 0);
  $pct   = $total > 0 ? round($ok * 100 / $total, 1) : 0.0;
  return [$total, $ok, $pct];
}

/**
 * ✅ Personal (SOLO SOLDADOS):
 * - Filtra por personal_unidad.jerarquia LIKE 'SOLDAD%' (case-insensitive con UPPER/TRIM)
 *   Esto cubre: SOLDADO / SOLDADOS / variaciones tipo "SOLDADOS ..." si existieran.
 * - Filtra por unidad si aplica
 */
function fetch_personal_list(
  PDO $pdo,
  array $PU_COLS,
  string $PU_ID,
  string $PU_UNIDAD,
  string $NAME_EXPR_SQL,
  string $PU_DNI,
  string $PU_GRADO,
  int $unidadId
): array {
  if (empty($PU_COLS)) return [];

  $PU_JER = in_array('jerarquia', $PU_COLS, true) ? 'jerarquia' : '';
  if ($PU_JER === '') return []; // no invento si no existe

  $fields = [];
  $fields[] = qi($PU_ID) . " AS pid";
  if ($PU_GRADO !== '') $fields[] = qi($PU_GRADO) . " AS grado";
  $fields[] = $NAME_EXPR_SQL . " AS nombre";
  if ($PU_DNI !== '') $fields[] = qi($PU_DNI) . " AS dni";

  $sql = "SELECT " . implode(", ", $fields) . " FROM " . qi('personal_unidad');
  $where = [];
  $params = [];

  if ($unidadId > 0 && $PU_UNIDAD !== '') {
    $where[] = qi($PU_UNIDAD) . " = ?";
    $params[] = $unidadId;
  }

  // ✅ SOLO SOLDADOS (robusto)
  $where[] = "UPPER(TRIM(" . qi($PU_JER) . ")) LIKE 'SOLDAD%'";

  if (!empty($where)) $sql .= " WHERE " . implode(" AND ", $where);

  if ($PU_GRADO !== '') $sql .= " ORDER BY grado ASC, nombre ASC";
  else $sql .= " ORDER BY nombre ASC";

  $st = $pdo->prepare($sql);
  $st->execute($params);
  return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** Trae mapa [pid][ciclo]=cumplido (para 1,2,3,nia) */
function fetch_personal_ciclos_matrix(PDO $pdo, int $unidadId): array {
  $st = $pdo->prepare("
    SELECT personal_unidad_id, ciclo, cumplido
    FROM educacion_tropa_personal_ciclos
    WHERE unidad_id = ?
      AND ciclo IN ('1','2','3','nia')
  ");
  $st->execute([$unidadId]);
  $m = [];
  foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
    $pid = (int)($r['personal_unidad_id'] ?? 0);
    $c   = (string)($r['ciclo'] ?? '');
    $v   = (int)($r['cumplido'] ?? 0);
    if ($pid > 0 && $c !== '') $m[$pid][$c] = $v;
  }
  return $m;
}

/* =========================
   Flash
========================= */
$flashOk = '';
$flashErr = '';

/* =========================
   POST handlers
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = (string)($_POST['action'] ?? '');
  $token  = (string)($_POST['csrf'] ?? '');

  if (!hash_equals($CSRF, $token)) {
    $flashErr = 'Acción bloqueada (CSRF inválido).';
  } else {
    try {
      /* ===== CRUD actividades ===== */
      if (in_array($action, ['add_act','edit_act','delete_act'], true)) {
        $ciclo = strtolower(trim((string)($_POST['ciclo'] ?? '')));
        if (!in_array($ciclo, ['1','2','3','nia'], true)) {
          $flashErr = 'Ciclo inválido.';
        } else {
          if ($action === 'delete_act') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) $flashErr = 'ID inválido.';
            else {
              $st = $pdo->prepare("DELETE FROM educacion_tropa_actividades WHERE id=? AND unidad_id=? AND ciclo=?");
              $st->execute([$id, $CURRENT_UNIDAD_ID, $ciclo]);
              header("Location: " . build_url($SELF_PATH, ['ciclo'=>$ciclo,'from'=>$from,'ok'=>'Actividad eliminada.']));
              exit;
            }
          }

          if (in_array($action, ['add_act','edit_act'], true) && $flashErr === '') {
            $id    = (int)($_POST['id'] ?? 0);
            $sem   = trim((string)($_POST['sem'] ?? ''));
            $fecha = trim((string)($_POST['fecha'] ?? ''));
            $tema  = trim((string)($_POST['tema'] ?? ''));
            $resp  = trim((string)($_POST['responsable'] ?? ''));
            $part  = trim((string)($_POST['participantes'] ?? ''));
            $lugar = trim((string)($_POST['lugar'] ?? ''));
            $cumplio = ((int)($_POST['cumplio'] ?? 0) === 1) ? 1 : 0;
            $doc   = trim((string)($_POST['doc'] ?? ''));

            if ($tema === '') $flashErr = 'El campo "Tema" es obligatorio.';

            $fechaDb = null;
            if ($flashErr === '' && $fecha !== '') {
              if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) !== 1) $flashErr = 'Fecha inválida (usar YYYY-MM-DD).';
              else $fechaDb = $fecha;
            }

            if ($flashErr === '') {
              if ($action === 'add_act') {
                $st = $pdo->prepare("
                  INSERT INTO educacion_tropa_actividades
                    (unidad_id, ciclo, sem, fecha, tema, responsable, participantes, lugar, cumplio, doc, created_by)
                  VALUES
                    (:unidad, :ciclo, :sem, :fecha, :tema, :resp, :part, :lugar, :cumplio, :doc, :created_by)
                ");
                $st->execute([
                  ':unidad' => $CURRENT_UNIDAD_ID,
                  ':ciclo'  => $ciclo,
                  ':sem'    => $sem,
                  ':fecha'  => $fechaDb,
                  ':tema'   => $tema,
                  ':resp'   => $resp,
                  ':part'   => $part,
                  ':lugar'  => $lugar,
                  ':cumplio'=> $cumplio,
                  ':doc'    => $doc,
                  ':created_by' => (int)($user['id'] ?? 0) ?: null,
                ]);
                header("Location: " . build_url($SELF_PATH, ['ciclo'=>$ciclo,'from'=>$from,'ok'=>'Actividad agregada.']));
                exit;
              } else {
                if ($id <= 0) $flashErr = 'ID inválido.';
                else {
                  $st = $pdo->prepare("
                    UPDATE educacion_tropa_actividades
                    SET sem=:sem, fecha=:fecha, tema=:tema, responsable=:resp, participantes=:part, lugar=:lugar,
                        cumplio=:cumplio, doc=:doc, updated_at=NOW()
                    WHERE id=:id AND unidad_id=:unidad AND ciclo=:ciclo
                  ");
                  $st->execute([
                    ':sem'=>$sem, ':fecha'=>$fechaDb, ':tema'=>$tema, ':resp'=>$resp, ':part'=>$part, ':lugar'=>$lugar,
                    ':cumplio'=>$cumplio, ':doc'=>$doc, ':id'=>$id, ':unidad'=>$CURRENT_UNIDAD_ID, ':ciclo'=>$ciclo
                  ]);
                  header("Location: " . build_url($SELF_PATH, ['ciclo'=>$ciclo,'from'=>$from,'ok'=>'Actividad actualizada.']));
                  exit;
                }
              }
            }
          }
        }
      }

      /* ===== Matriz personal (5ta tarjeta) ===== */
      if ($action === 'save_personal_matrix' && $flashErr === '') {
        $allIds = $_POST['personal_ids'] ?? [];
        if (!is_array($allIds)) $allIds = [];

        $c1   = $_POST['c1'] ?? [];   if (!is_array($c1)) $c1 = [];
        $c2   = $_POST['c2'] ?? [];   if (!is_array($c2)) $c2 = [];
        $c3   = $_POST['c3'] ?? [];   if (!is_array($c3)) $c3 = [];
        $cnia = $_POST['cnia'] ?? []; if (!is_array($cnia)) $cnia = [];

        $set1 = []; foreach ($c1 as $v) $set1[(int)$v] = true;
        $set2 = []; foreach ($c2 as $v) $set2[(int)$v] = true;
        $set3 = []; foreach ($c3 as $v) $set3[(int)$v] = true;
        $setN = []; foreach ($cnia as $v) $setN[(int)$v] = true;

        $up = $pdo->prepare("
          INSERT INTO educacion_tropa_personal_ciclos
            (unidad_id, personal_unidad_id, ciclo, cumplido, updated_at, updated_by)
          VALUES
            (:unidad, :pid, :ciclo, :cumplido, NOW(), :by)
          ON DUPLICATE KEY UPDATE
            cumplido=VALUES(cumplido),
            updated_at=NOW(),
            updated_by=VALUES(updated_by)
        ");

        foreach ($allIds as $pidRaw) {
          $pid = (int)$pidRaw;
          if ($pid <= 0) continue;

          $pairs = [
            ['1',   isset($set1[$pid]) ? 1 : 0],
            ['2',   isset($set2[$pid]) ? 1 : 0],
            ['3',   isset($set3[$pid]) ? 1 : 0],
            ['nia', isset($setN[$pid]) ? 1 : 0],
          ];

          foreach ($pairs as $pair) {
            $ciclo = (string)$pair[0];
            $cum   = (int)$pair[1];

            $up->execute([
              ':unidad' => $CURRENT_UNIDAD_ID,
              ':pid'    => $pid,
              ':ciclo'  => $ciclo,
              ':cumplido' => $cum,
              ':by' => (int)($user['id'] ?? 0) ?: null,
            ]);
          }
        }

        header("Location: " . build_url($SELF_PATH, ['tab'=>'personal','from'=>$from,'ok'=>'Matriz de personal guardada.']));
        exit;
      }

    } catch (Throwable $ex) {
      $flashErr = "Error: " . $ex->getMessage();
    }
  }
}

/* Mensajes por querystring */
if (isset($_GET['ok']))  $flashOk  = (string)$_GET['ok'];
if (isset($_GET['err'])) $flashErr = (string)$_GET['err'];

/* =========================
   Datos para HOME
========================= */
[$t1,$ok1,$p1] = calc_kpi_from_db($pdo, '1',   $CURRENT_UNIDAD_ID);
[$t2,$ok2,$p2] = calc_kpi_from_db($pdo, '2',   $CURRENT_UNIDAD_ID);
[$t3,$ok3,$p3] = calc_kpi_from_db($pdo, '3',   $CURRENT_UNIDAD_ID);
[$tN,$okN,$pN] = calc_kpi_from_db($pdo, 'nia', $CURRENT_UNIDAD_ID);

/* KPI Personal (porcentaje que cumplió los 4 ciclos) */
$personalPct = 0.0;
$personalTotal = 0;
$personalFull  = 0;

$personalListHome = [];
$matrixHome = [];
if (!empty($PU_COLS) && $HAS_JERARQUIA) {
  $personalListHome = fetch_personal_list($pdo, $PU_COLS, $PU_ID, $PU_UNIDAD, $NAME_EXPR_SQL, $PU_DNI, $PU_GRADO, $CURRENT_UNIDAD_ID);
  $matrixHome = fetch_personal_ciclos_matrix($pdo, $CURRENT_UNIDAD_ID);

  $personalTotal = count($personalListHome);
  foreach ($personalListHome as $p) {
    $pid = (int)($p['pid'] ?? 0);
    if ($pid <= 0) continue;
    $m = $matrixHome[$pid] ?? [];
    $full = ((int)($m['1'] ?? 0) === 1) && ((int)($m['2'] ?? 0) === 1) && ((int)($m['3'] ?? 0) === 1) && ((int)($m['nia'] ?? 0) === 1);
    if ($full) $personalFull++;
  }
  $personalPct = $personalTotal > 0 ? round($personalFull * 100 / $personalTotal, 1) : 0.0;
}

/* =========================
   Datos para vista CICLO
========================= */
$CTX = null;
$rows = [];
$total = 0; $ok = 0; $pct = 0.0;

if ($view === 'ciclo') {
  $CTX = $CICLOS[$cicloParam] ?? null;
  if (!$CTX) $view = 'home';
  else {
    $rows = fetch_actividades($pdo, $cicloParam, $CURRENT_UNIDAD_ID);
    [$total,$ok,$pct] = calc_kpi_from_db($pdo, $cicloParam, $CURRENT_UNIDAD_ID);
  }
}

/* =========================
   Datos para vista PERSONAL (matriz)
========================= */
$personalList = [];
$matrix = [];

if ($view === 'personal' && !empty($PU_COLS) && $HAS_JERARQUIA) {
  $personalList = fetch_personal_list($pdo, $PU_COLS, $PU_ID, $PU_UNIDAD, $NAME_EXPR_SQL, $PU_DNI, $PU_GRADO, $CURRENT_UNIDAD_ID);
  $matrix = fetch_personal_ciclos_matrix($pdo, $CURRENT_UNIDAD_ID);
}

/* URLs */
$URL_HOME     = build_url($SELF_PATH, ['from'=>$from]);
$URL_PERSONAL = build_url($SELF_PATH, ['tab'=>'personal','from'=>$from]);

$URL_C1   = build_url($SELF_PATH, ['ciclo'=>'1','from'=>$from]);
$URL_C2   = build_url($SELF_PATH, ['ciclo'=>'2','from'=>$from]);
$URL_C3   = build_url($SELF_PATH, ['ciclo'=>'3','from'=>$from]);
$URL_NIA  = build_url($SELF_PATH, ['ciclo'=>'nia','from'=>$from]);

?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Educación operacional de la tropa · <?= e(($from === 's1') ? 'S-1' : 'S-3') ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= e($ASSETS_URL) ?>/css/theme-602.css">
<link rel="icon" type="image/png" href="<?= e($FAVICO) ?>">

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
  :root{
    --bg-dark:#020617;
    --card-bg:rgba(15,23,42,.94);
    --text-main:#e5e7eb;
    --text-muted:#9ca3af;
    --accent:#22c55e;
    --danger:#ef4444;
    --warn:#fbbf24;
    --info:#38bdf8;
  }
  *{ box-sizing:border-box; }
  body{
    min-height:100vh;margin:0;
    font-family:system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,sans-serif;
    background:
      radial-gradient(circle at top left, rgba(56,189,248,.22), transparent 60%),
      radial-gradient(circle at bottom right, rgba(34,197,94,.18), transparent 55%),
      url("<?= e($IMG_BG) ?>") center/cover fixed;
    background-color:var(--bg-dark);
    color:var(--text-main);
    overflow-x:hidden;
  }
  body::before{
    content:"";position:fixed;inset:0;
    background:radial-gradient(circle at top, rgba(15,23,42,.75), rgba(15,23,42,.95));
    pointer-events:none;z-index:-1;
  }
  .page-wrap{ padding:24px 16px 32px; }
  .container-main{ max-width:1250px;margin:0 auto; }

  header.brand-hero{ padding:14px 0 6px; }
  .hero-inner{
    max-width:1250px;margin:0 auto;
    display:flex;justify-content:space-between;align-items:center;gap:16px;
  }
  .brand-left{ display:flex;align-items:center;gap:14px; }
  .brand-logo{ height:56px;width:auto;filter:drop-shadow(0 0 10px rgba(0,0,0,.8)); }
  .brand-title{ font-weight:900;font-size:1.1rem;letter-spacing:.03em; }
  .brand-sub{ font-size:.82rem;color:#cbd5f5; }
  .header-actions{ display:flex;flex-wrap:wrap;gap:8px; align-items:center; justify-content:flex-end; }

  .btn-ghost{
    border-radius:999px;border:1px solid rgba(148,163,184,.55);
    background:rgba(15,23,42,.8);color:var(--text-main);
    font-size:.82rem;font-weight:700;padding:.38rem 1rem;
    box-shadow:0 10px 30px rgba(0,0,0,.55);
    text-decoration:none;white-space:nowrap;
  }
  .btn-ghost:hover{ background:rgba(30,64,175,.9);border-color:rgba(129,140,248,.9);color:white; }

  .btn-add{
    border-radius:999px;border:1px solid rgba(56,189,248,.55);
    background:rgba(56,189,248,.14);color:#dbeafe;
    font-size:.82rem;font-weight:900;padding:.38rem 1rem;
    box-shadow:0 10px 30px rgba(0,0,0,.55);white-space:nowrap;
  }
  .btn-add:hover{ background:rgba(56,189,248,.24); border-color:rgba(56,189,248,.85); color:#fff; }

  .section-header{ margin-bottom:18px; }
  .section-kicker{ margin-bottom:4px; }
  .section-kicker .sk-text{
    font-size:1.05rem;font-weight:900;letter-spacing:.18em;text-transform:uppercase;
    background:linear-gradient(90deg,#38bdf8,#22c55e);
    -webkit-background-clip:text;-webkit-text-fill-color:transparent;
    filter:drop-shadow(0 0 6px rgba(30,58,138,.55));
    padding-bottom:3px;border-bottom:2px solid rgba(34,197,94,.45);
    display:inline-block;
  }
  .section-title{ font-size:1.65rem;font-weight:950;margin-top:2px; }
  .section-sub{ font-size:.92rem;color:#cbd5f5;max-width:880px; }

  .modules-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));
    gap:18px;
    align-items:stretch;
  }
  .modules-grid > div{ display:flex; }
  .card-link{ text-decoration:none;color:inherit;display:flex;flex:1;width:100%;height:100%; }

  .card-s3{
    position:relative;border-radius:22px;padding:18px 18px 16px;
    background:
      radial-gradient(circle at top left, rgba(56,189,248,.22), transparent 60%),
      radial-gradient(circle at bottom right, rgba(34,197,94,.18), transparent 70%),
      var(--card-bg);
    border:1px solid rgba(15,23,42,.9);
    box-shadow:0 22px 40px rgba(0,0,0,.85), 0 0 0 1px rgba(148,163,184,.35);
    backdrop-filter:blur(12px);
    transition:transform .18s ease-out, box-shadow .18s ease-out, border-color .18s ease-out;
    overflow:hidden;
    width:100%;
    display:flex; flex-direction:column; justify-content:space-between;
  }
  .card-s3:hover{
    transform:translateY(-4px) scale(1.01);
    box-shadow:0 28px 60px rgba(0,0,0,.9), 0 0 0 1px rgba(129,140,248,.65);
    border-color:rgba(129,140,248,.9);
  }
  .card-topline{ display:flex;align-items:flex-start;justify-content:space-between;gap:10px; }
  .card-icon{ font-size:1.4rem;line-height:1;filter:drop-shadow(0 0 6px rgba(0,0,0,.7)); }
  .card-title{ font-weight:900;font-size:1rem;margin:0; }
  .card-sub{ font-size:.78rem;color:var(--text-muted);margin-top:4px; }
  .card-pill{
    font-size:.68rem;text-transform:uppercase;letter-spacing:.16em;
    padding:.15rem .55rem;border-radius:999px;
    border:1px solid rgba(148,163,184,.6);color:#e5e7eb;
    background:rgba(15,23,42,.4);
    display:inline-flex;align-items:center;gap:6px;white-space:nowrap;
  }
  .card-footer{
    margin-top:14px;
    display:flex;align-items:center;justify-content:space-between;gap:10px;
  }
  .kpi-label{ font-size:.7rem;text-transform:uppercase;letter-spacing:.16em;color:var(--text-muted);font-weight:900; }
  .kpi-num{ font-size:1.65rem;font-weight:950;color:var(--accent); line-height:1; }
  .kpi-progress{
    flex:1;height:7px;border-radius:999px;background:rgba(15,23,42,.9);
    overflow:hidden; box-shadow:inset 0 0 0 1px rgba(31,41,55,.8);
  }
  .kpi-progress span{
    display:block;height:100%;border-radius:999px;
    background:linear-gradient(90deg,#22c55e,#a3e635);
    box-shadow:0 0 14px rgba(34,197,94,.9);
  }
  .kpi-tag{ font-size:.75rem;color:#cbd5f5;opacity:.85;white-space:nowrap; }

  .panel{
    background:rgba(15,23,42,.94);
    border-radius:18px;
    border:1px solid rgba(148,163,184,.45);
    padding:18px 18px 20px;
    box-shadow:0 18px 40px rgba(0,0,0,.80);
    backdrop-filter:blur(8px);
    margin-bottom:18px;
  }
  .panel-title{ font-size:1rem; font-weight:950; margin-bottom:4px; }
  .panel-sub{ font-size:.86rem; color:#cbd5f5; margin-bottom:10px; }

  .kpi-row{ display:flex; align-items:center; gap:18px; flex-wrap:wrap; }
  .kpi-main{ font-size:1.8rem; font-weight:950; color:#22c55e; }
  .kpi-desc{ font-size:.92rem; color:#cbd5f5; }
  .progress{ height:7px; border-radius:999px; background:rgba(15,23,42,.9); }
  .progress-bar{ border-radius:999px; }

  .table-wrap{
    border-radius:16px;
    overflow:hidden;
    border:1px solid rgba(148,163,184,.25);
    background:rgba(2,6,23,.30);
    box-shadow:0 16px 40px rgba(0,0,0,.55);
  }
  table.table-glass{
    margin:0;width:100%;
    border-collapse:separate;border-spacing:0;
  }
  .table-glass thead th{
    position:sticky;top:0;z-index:2;
    background:linear-gradient(180deg, rgba(15,23,42,.95), rgba(15,23,42,.78));
    border-bottom:1px solid rgba(148,163,184,.25);
    font-size:.78rem;text-transform:uppercase;letter-spacing:.12em;
    color:#dbeafe;padding:.55rem .6rem;white-space:nowrap;
  }
  .table-glass tbody td{
    padding:.55rem .6rem;
    border-bottom:1px solid rgba(148,163,184,.12);
    vertical-align:middle;font-size:.88rem;
  }
  .table-glass tbody tr:nth-child(odd) td{ background:rgba(2,6,23,.18); }
  .table-glass tbody tr:hover td{ background:rgba(56,189,248,.08); }
  .td-muted{ color:#cbd5f5; opacity:.9; }
  .td-small{ font-size:.82rem; color:#cbd5f5; opacity:.95; }

  .btn-mini{
    border-radius:10px;padding:.35rem .6rem;font-weight:900;font-size:.78rem;
    border:1px solid rgba(148,163,184,.35);
    background:rgba(15,23,42,.55); color:#e5e7eb;
    text-decoration:none; white-space:nowrap;
  }
  .btn-mini:hover{ background:rgba(30,64,175,.35); border-color:rgba(129,140,248,.45); color:#fff; }
  .btn-danger-mini{
    border-radius:10px;padding:.35rem .6rem;font-weight:900;font-size:.78rem;
    border:1px solid rgba(239,68,68,.45);
    background:rgba(239,68,68,.12); color:#fecaca; white-space:nowrap;
  }
  .btn-danger-mini:hover{ background:rgba(239,68,68,.20); border-color:rgba(239,68,68,.70); color:#fff; }

  .toolbar{
    display:flex; gap:10px; flex-wrap:wrap; align-items:center; justify-content:space-between;
    margin-bottom:10px;
  }
  .searchbox{
    max-width:520px;
    background:rgba(15,23,42,.70);
    border:1px solid rgba(148,163,184,.35);
    color:#e5e7eb;
    border-radius:999px;
    padding:.45rem .9rem;
    outline:none;
  }

  .pill-ok{
    display:inline-flex;align-items:center;gap:.35rem;
    padding:.18rem .55rem;border-radius:999px;
    border:1px solid rgba(34,197,94,.55);
    background:rgba(34,197,94,.10);
    color:#bbf7d0;
    font-size:.74rem;
    white-space:nowrap;
  }
  .pill-wait{
    display:inline-flex;align-items:center;gap:.35rem;
    padding:.18rem .55rem;border-radius:999px;
    border:1px solid rgba(251,191,36,.55);
    background:rgba(251,191,36,.10);
    color:#fde68a;
    font-size:.74rem;
    white-space:nowrap;
  }

  .chk{
    width:18px;height:18px;
    accent-color:#22c55e;
    cursor:pointer;
  }
</style>
</head>

<body>
<header class="brand-hero">
  <div class="hero-inner">
    <div class="brand-left">
      <img src="<?= e($ESCUDO) ?>" class="brand-logo" alt="Escudo">
      <div>
        <div class="brand-title">Escuela Militar de Montaña</div>
        <div class="brand-sub">“La montaña nos une”</div>
      </div>
    </div>

    <div class="header-actions">
      <a href="<?= e($backHref) ?>" class="btn-ghost"><?= e($backLabel) ?></a>

      <?php if ($view !== 'home'): ?>
        <a href="<?= e($URL_HOME) ?>" class="btn-ghost">⬅ Volver a Educación tropa</a>
      <?php endif; ?>

      <?php if ($view === 'ciclo'): ?>
        <button type="button" class="btn-add" id="btnAddAct">➕ Agregar actividad</button>
      <?php endif; ?>
    </div>
  </div>
</header>

<div class="page-wrap">
  <div class="container-main">

    <?php if ($flashOk): ?><div class="alert alert-success"><?= e($flashOk) ?></div><?php endif; ?>
    <?php if ($flashErr): ?><div class="alert alert-danger"><?= e($flashErr) ?></div><?php endif; ?>

    <?php if ($view === 'home'): ?>

      <div class="section-header">
        <div class="section-kicker"><span class="sk-text"><?= e($kickerTxt) ?></span></div>
        <div class="section-title">Educación operacional de la tropa</div>
        <p class="section-sub mb-0">
          Ciclos de instrucción de tropa (I, II, III y N.I.A.). Además, la tarjeta “Personal” permite ver y marcar
          el cumplimiento por ciclos (I/II/III/NIA) para cada <b>soldado</b>.
        </p>
      </div>

      <div class="modules-grid">
        <!-- Ciclos -->
        <div>
          <a class="card-link" href="<?= e($URL_C1) ?>">
            <article class="card-s3">
              <div class="card-topline">
                <div>
                  <div class="card-title">Ciclo I</div>
                  <div class="card-sub"><?= e($CICLOS['1']['sub']) ?></div>
                </div>
                <div class="d-flex flex-column align-items-end gap-1">
                  <div class="card-icon"><?= e($CICLOS['1']['icon']) ?></div>
                  <span class="card-pill"><?= e($CICLOS['1']['pill']) ?></span>
                </div>
              </div>
              <div class="card-footer">
                <div>
                  <div class="kpi-label">Cumplimiento</div>
                  <div class="kpi-num"><?= e($p1) ?>%</div>
                </div>
                <div class="kpi-progress"><span style="width:<?= e(max(0,min(100,$p1))) ?>%"></span></div>
                <div class="kpi-tag">Ver ciclo</div>
              </div>
            </article>
          </a>
        </div>

        <div>
          <a class="card-link" href="<?= e($URL_C2) ?>">
            <article class="card-s3">
              <div class="card-topline">
                <div>
                  <div class="card-title">Ciclo II</div>
                  <div class="card-sub"><?= e($CICLOS['2']['sub']) ?></div>
                </div>
                <div class="d-flex flex-column align-items-end gap-1">
                  <div class="card-icon"><?= e($CICLOS['2']['icon']) ?></div>
                  <span class="card-pill"><?= e($CICLOS['2']['pill']) ?></span>
                </div>
              </div>
              <div class="card-footer">
                <div>
                  <div class="kpi-label">Cumplimiento</div>
                  <div class="kpi-num"><?= e($p2) ?>%</div>
                </div>
                <div class="kpi-progress"><span style="width:<?= e(max(0,min(100,$p2))) ?>%"></span></div>
                <div class="kpi-tag">Ver ciclo</div>
              </div>
            </article>
          </a>
        </div>

        <div>
          <a class="card-link" href="<?= e($URL_C3) ?>">
            <article class="card-s3">
              <div class="card-topline">
                <div>
                  <div class="card-title">Ciclo III</div>
                  <div class="card-sub"><?= e($CICLOS['3']['sub']) ?></div>
                </div>
                <div class="d-flex flex-column align-items-end gap-1">
                  <div class="card-icon"><?= e($CICLOS['3']['icon']) ?></div>
                  <span class="card-pill"><?= e($CICLOS['3']['pill']) ?></span>
                </div>
              </div>
              <div class="card-footer">
                <div>
                  <div class="kpi-label">Cumplimiento</div>
                  <div class="kpi-num"><?= e($p3) ?>%</div>
                </div>
                <div class="kpi-progress"><span style="width:<?= e(max(0,min(100,$p3))) ?>%"></span></div>
                <div class="kpi-tag">Ver ciclo</div>
              </div>
            </article>
          </a>
        </div>

        <div>
          <a class="card-link" href="<?= e($URL_NIA) ?>">
            <article class="card-s3">
              <div class="card-topline">
                <div>
                  <div class="card-title">N.I.A.</div>
                  <div class="card-sub"><?= e($CICLOS['nia']['sub']) ?></div>
                </div>
                <div class="d-flex flex-column align-items-end gap-1">
                  <div class="card-icon"><?= e($CICLOS['nia']['icon']) ?></div>
                  <span class="card-pill"><?= e($CICLOS['nia']['pill']) ?></span>
                </div>
              </div>
              <div class="card-footer">
                <div>
                  <div class="kpi-label">Cumplimiento</div>
                  <div class="kpi-num"><?= e($pN) ?>%</div>
                </div>
                <div class="kpi-progress"><span style="width:<?= e(max(0,min(100,$pN))) ?>%"></span></div>
                <div class="kpi-tag">Ver ciclo</div>
              </div>
            </article>
          </a>
        </div>

        <!-- ✅ 5ta tarjeta: PERSONAL -->
        <div>
          <a class="card-link" href="<?= e($URL_PERSONAL) ?>">
            <article class="card-s3">
              <div class="card-topline">
                <div>
                  <div class="card-title">Personal</div>
                  <div class="card-sub">Cumplimiento por ciclos (I/II/III/NIA) · <b>Solo SOLDADOS</b>.</div>
                </div>
                <div class="d-flex flex-column align-items-end gap-1">
                  <div class="card-icon">👤</div>
                  <span class="card-pill">Matriz</span>
                </div>
              </div>
              <div class="card-footer">
                <div>
                  <div class="kpi-label">Cumplieron todo</div>
                  <div class="kpi-num"><?= e($personalPct) ?>%</div>
                </div>
                <div class="kpi-progress"><span style="width:<?= e(max(0,min(100,$personalPct))) ?>%"></span></div>
                <div class="kpi-tag"><?= (int)$personalFull ?> / <?= (int)$personalTotal ?></div>
              </div>
            </article>
          </a>
        </div>

      </div>

      <?php if (!empty($PU_COLS) && !$HAS_JERARQUIA): ?>
        <div class="alert alert-warning mt-3 mb-0">
          <b>Atención:</b> No existe la columna <code>jerarquia</code> en <code>personal_unidad</code>.
          La tarjeta “Personal” está configurada para filtrar <b>solo SOLDADOS</b> por esa columna.
        </div>
      <?php endif; ?>

    <?php elseif ($view === 'ciclo'): ?>

      <div class="section-header">
        <div class="section-kicker"><span class="sk-text"><?= e($kickerTxt) ?> · <?= e($CTX['kicker']) ?></span></div>
        <div class="section-title"><?= e($CTX['kicker'] . ' · Educación operacional de la tropa') ?></div>
        <p class="section-sub mb-0">
          Administrá actividades (alta/edición/baja). El cumplimiento del personal se gestiona desde la tarjeta “Personal”.
        </p>
      </div>

      <div class="panel">
        <div class="panel-title">Resumen de avance</div>
        <div class="panel-sub">KPI = actividades con “Cumplió = Sí” / total de actividades del ciclo.</div>
        <div class="kpi-row">
          <div>
            <div class="kpi-label">Cumplimiento</div>
            <div class="kpi-main"><?= e($pct) ?>%</div>
          </div>
          <div style="min-width:260px; flex:1;">
            <div class="kpi-desc"><?= e($ok) ?> actividades cumplidas sobre <?= e($total) ?> cargadas.</div>
            <div class="progress mt-2"><div class="progress-bar bg-success" style="width:<?= e($pct) ?>%"></div></div>
          </div>
        </div>
      </div>

      <div class="panel">
        <div class="toolbar">
          <div>
            <div class="panel-title mb-1">Actividades · <?= e($CTX['kicker']) ?></div>
            <div class="panel-sub mb-0">Alta / edición / baja. (Evidencia textual por ahora en “Doc”).</div>
          </div>
          <input id="actSearch" class="searchbox" type="text" placeholder="Buscar en actividades (tema, responsable, lugar)…">
        </div>

        <div class="table-wrap">
          <div class="table-responsive" style="max-height:520px;">
            <table class="table-glass">
              <thead>
                <tr>
                  <th style="min-width:70px;">Sem</th>
                  <th style="min-width:120px;">Fecha</th>
                  <th style="min-width:260px;">Tema</th>
                  <th style="min-width:200px;">Responsable</th>
                  <th style="min-width:160px;">Participantes</th>
                  <th style="min-width:160px;">Lugar</th>
                  <th style="min-width:110px;">Cumplió</th>
                  <th style="min-width:220px;">Doc</th>
                  <th style="min-width:160px;">Acciones</th>
                </tr>
              </thead>
              <tbody id="actTbody">
                <?php if (empty($rows)): ?>
                  <tr><td colspan="9" class="td-muted" style="padding:14px;">Sin actividades aún. Usá “Agregar actividad”.</td></tr>
                <?php else: ?>
                  <?php foreach ($rows as $r): ?>
                    <?php
                      $rid = (int)$r['id'];
                      $cum = (int)($r['cumplio'] ?? 0) === 1;
                      $fecha = (string)($r['fecha'] ?? '');
                      $fechaShow = $fecha !== '' ? date('d/m/Y', strtotime($fecha)) : '—';
                      $actUrl = build_url($SELF_PATH, ['ciclo'=>$cicloParam,'from'=>$from]);
                    ?>
                    <tr class="act-row"
                        data-sem="<?= e((string)($r['sem'] ?? '')) ?>"
                        data-fecha="<?= e((string)($r['fecha'] ?? '')) ?>"
                        data-tema="<?= e((string)($r['tema'] ?? '')) ?>"
                        data-responsable="<?= e((string)($r['responsable'] ?? '')) ?>"
                        data-participantes="<?= e((string)($r['participantes'] ?? '')) ?>"
                        data-lugar="<?= e((string)($r['lugar'] ?? '')) ?>"
                        data-cumplio="<?= e((string)($r['cumplio'] ?? '0')) ?>"
                        data-doc="<?= e((string)($r['doc'] ?? '')) ?>"
                        data-id="<?= (int)$rid ?>">
                      <td class="td-small"><?= e((string)($r['sem'] ?? '')) ?></td>
                      <td class="td-small"><?= e($fechaShow) ?></td>
                      <td><?= e((string)($r['tema'] ?? '')) ?></td>
                      <td class="td-small"><?= e((string)($r['responsable'] ?? '')) ?></td>
                      <td class="td-small"><?= e((string)($r['participantes'] ?? '')) ?></td>
                      <td class="td-small"><?= e((string)($r['lugar'] ?? '')) ?></td>
                      <td><?= $cum ? '<span class="pill-ok">✅ Sí</span>' : '<span class="pill-wait">⏳ No</span>' ?></td>
                      <td class="td-small"><?= ($r['doc'] ?? '') !== '' ? e((string)$r['doc']) : '<span class="td-muted">—</span>' ?></td>
                      <td>
                        <button type="button" class="btn-mini js-edit-act">Editar</button>
                        <form method="post" action="<?= e($actUrl) ?>" class="d-inline js-del-act" style="margin-left:6px;">
                          <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
                          <input type="hidden" name="action" value="delete_act">
                          <input type="hidden" name="ciclo" value="<?= e($cicloParam) ?>">
                          <input type="hidden" name="id" value="<?= (int)$rid ?>">
                          <button type="submit" class="btn-danger-mini">Eliminar</button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="mt-3 td-muted">
          👉 Para marcar cumplimiento de personal por ciclos: ir a <a class="text-info" href="<?= e($URL_PERSONAL) ?>">tarjeta Personal</a>.
        </div>
      </div>

    <?php else: /* PERSONAL */ ?>

      <div class="section-header">
        <div class="section-kicker"><span class="sk-text"><?= e($kickerTxt) ?> · PERSONAL</span></div>
        <div class="section-title">Personal que cumplió con los ciclos</div>
        <p class="section-sub mb-0">
          Tabla única (matriz). Marcá por persona el cumplimiento de Ciclo I, II, III y NIA.
          La columna “Cumplido” es SI cuando están los 4 en SI.
          <b>Se muestran solo SOLDADOS por personal_unidad.jerarquia</b>.
        </p>
      </div>

      <div class="panel">
        <div class="toolbar">
          <div>
            <div class="panel-title mb-1">Matriz de cumplimiento (SOLO SOLDADOS)</div>
            <div class="panel-sub mb-0">
              Fuente: <code>personal_unidad</code> (filtro: <code>jerarquia LIKE 'SOLDAD%'</code>). Guarda en <code>educacion_tropa_personal_ciclos</code>.
            </div>
          </div>
          <input id="perSearch" class="searchbox" type="text" placeholder="Buscar (grado, apellido, nombre, DNI)…">
        </div>

        <?php if (empty($PU_COLS)): ?>
          <div class="alert alert-warning mb-0">
            <b>No pude leer columnas de <code>personal_unidad</code>.</b><br>
            <div class="mt-2" style="font-size:.9rem;">
              <div>DB actual: <code><?= e($DB_NAME !== '' ? $DB_NAME : '(desconocida)') ?></code></div>
              <div>¿Existe tabla <code>personal_unidad</code>?: <b><?= $PU_TABLE_EXISTS ? 'SÍ' : 'NO' ?></b></div>
              <?php if ($PU_ERR !== ''): ?><div class="mt-2">Error MySQL: <code><?= e($PU_ERR) ?></code></div><?php endif; ?>
            </div>
          </div>
        <?php elseif (!$HAS_JERARQUIA): ?>
          <div class="alert alert-warning mb-0">
            <b>Falta columna <code>jerarquia</code> en <code>personal_unidad</code>.</b><br>
            Esta pantalla está configurada para traer <b>solo SOLDADOS</b> por esa columna.
          </div>
        <?php else: ?>

          <?php $saveUrl = build_url($SELF_PATH, ['tab'=>'personal','from'=>$from]); ?>

          <form method="post" action="<?= e($saveUrl) ?>" id="formPersonalMatrix">
            <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
            <input type="hidden" name="action" value="save_personal_matrix">

            <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-2">
              <div class="td-muted">
                Unidad: <b><?= (int)$CURRENT_UNIDAD_ID ?></b>
                <?php if ($CURRENT_UNIDAD_ID === 0): ?><span class="ms-2">(sin filtro por unidad_id)</span><?php endif; ?>
              </div>
              <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn-mini" id="btnAllI">I todos</button>
                <button type="button" class="btn-mini" id="btnAllII">II todos</button>
                <button type="button" class="btn-mini" id="btnAllIII">III todos</button>
                <button type="button" class="btn-mini" id="btnAllNIA">NIA todos</button>
                <button type="button" class="btn-mini" id="btnClearAll">Limpiar</button>
                <button type="submit" class="btn-add">💾 Guardar</button>
              </div>
            </div>

            <div class="table-wrap">
              <div class="table-responsive" style="max-height:560px;">
                <table class="table-glass" id="tblPersonal">
                  <thead>
                    <tr>
                      <th style="min-width:70px;">ID</th>
                      <th style="min-width:90px;">Grado</th>
                      <th style="min-width:340px;">Nombre y apellido</th>
                      <?php if ($PU_DNI !== ''): ?><th style="min-width:140px;">DNI</th><?php endif; ?>
                      <th style="min-width:90px;">Ciclo I</th>
                      <th style="min-width:90px;">Ciclo II</th>
                      <th style="min-width:90px;">Ciclo III</th>
                      <th style="min-width:90px;">NIA</th>
                      <th style="min-width:120px;">Cumplido</th>
                    </tr>
                  </thead>
                  <tbody id="perTbody">
                    <?php if (empty($personalList)): ?>
                      <tr><td colspan="<?= ($PU_DNI!==''?9:8) ?>" class="td-muted" style="padding:14px;">Sin SOLDADOS para mostrar (jerarquia LIKE 'SOLDAD%').</td></tr>
                    <?php else: ?>
                      <?php foreach ($personalList as $p): ?>
                        <?php
                          $pid = (int)($p['pid'] ?? 0);
                          $grado = (string)($p['grado'] ?? '');
                          $nombre = (string)($p['nombre'] ?? '');
                          $dni = (string)($p['dni'] ?? '');

                          $m = $matrix[$pid] ?? [];
                          $v1 = ((int)($m['1'] ?? 0) === 1);
                          $v2 = ((int)($m['2'] ?? 0) === 1);
                          $v3 = ((int)($m['3'] ?? 0) === 1);
                          $vN = ((int)($m['nia'] ?? 0) === 1);

                          $full = ($v1 && $v2 && $v3 && $vN);
                          $blob = mb_strtolower(trim($grado.' '.$nombre.' '.$dni), 'UTF-8');
                        ?>
                        <tr class="per-row" data-text="<?= e($blob) ?>">
                          <td class="td-small">
                            <?= (int)$pid ?>
                            <input type="hidden" name="personal_ids[]" value="<?= (int)$pid ?>">
                          </td>
                          <td class="td-small"><?= e($grado) ?></td>
                          <td><?= e($nombre) ?></td>
                          <?php if ($PU_DNI !== ''): ?><td class="td-small"><?= e($dni) ?></td><?php endif; ?>

                          <td class="text-center">
                            <input class="chk chk-c1" type="checkbox" name="c1[]" value="<?= (int)$pid ?>" <?= $v1 ? 'checked' : '' ?>>
                          </td>
                          <td class="text-center">
                            <input class="chk chk-c2" type="checkbox" name="c2[]" value="<?= (int)$pid ?>" <?= $v2 ? 'checked' : '' ?>>
                          </td>
                          <td class="text-center">
                            <input class="chk chk-c3" type="checkbox" name="c3[]" value="<?= (int)$pid ?>" <?= $v3 ? 'checked' : '' ?>>
                          </td>
                          <td class="text-center">
                            <input class="chk chk-nia" type="checkbox" name="cnia[]" value="<?= (int)$pid ?>" <?= $vN ? 'checked' : '' ?>>
                          </td>

                          <td>
                            <?= $full ? '<span class="pill-ok">✅ SI</span>' : '<span class="pill-wait">⏳ NO</span>' ?>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>

          </form>

        <?php endif; ?>
      </div>

    <?php endif; ?>

  </div>
</div>

<!-- MODAL Actividad (solo para vista ciclo) -->
<?php if ($view === 'ciclo'): ?>
<?php $actUrl = build_url($SELF_PATH, ['ciclo'=>$cicloParam,'from'=>$from]); ?>
<div class="modal fade" id="actModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content" style="background:rgba(15,23,42,.96); color:#e5e7eb; border:1px solid rgba(148,163,184,.25); border-radius:16px;">
      <div class="modal-header" style="border-color:rgba(148,163,184,.18);">
        <h5 class="modal-title fw-bold" id="actModalTitle">Agregar actividad</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <form method="post" action="<?= e($actUrl) ?>" id="actForm">
        <div class="modal-body">
          <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
          <input type="hidden" name="action" id="actAction" value="add_act">
          <input type="hidden" name="ciclo" value="<?= e($cicloParam) ?>">
          <input type="hidden" name="id" id="actId" value="0">

          <div class="row g-3">
            <div class="col-md-2">
              <label class="form-label fw-bold">Sem</label>
              <input type="text" name="sem" id="actSem" class="form-control" maxlength="20" placeholder="Ej: 1">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-bold">Fecha</label>
              <input type="date" name="fecha" id="actFecha" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-bold">Cumplió</label>
              <select name="cumplio" id="actCumplio" class="form-select">
                <option value="0">No</option>
                <option value="1">Sí</option>
              </select>
            </div>

            <div class="col-12">
              <label class="form-label fw-bold">Tema *</label>
              <input type="text" name="tema" id="actTema" class="form-control" maxlength="200" required placeholder="Ej: Marcha táctica / Primeros auxilios / ...">
            </div>

            <div class="col-md-6">
              <label class="form-label fw-bold">Responsable</label>
              <input type="text" name="responsable" id="actResp" class="form-control" maxlength="200" placeholder="Ej: Instructor / Jefe de Curso">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-bold">Participantes</label>
              <input type="text" name="participantes" id="actPart" class="form-control" maxlength="200" placeholder="Ej: Tropa / Sección / ...">
            </div>

            <div class="col-md-6">
              <label class="form-label fw-bold">Lugar</label>
              <input type="text" name="lugar" id="actLugar" class="form-control" maxlength="200" placeholder="Ej: Aula / Campo / Polígono">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-bold">Doc (texto / referencia)</label>
              <input type="text" name="doc" id="actDoc" class="form-control" maxlength="255" placeholder="Ej: Acta 12/2026 - PDF en carpeta ...">
            </div>
          </div>

          <div class="form-text text-light mt-2" style="opacity:.75">
            * Más adelante lo conectamos a evidencias reales (filesystem) como venís haciendo.
          </div>
        </div>

        <div class="modal-footer" style="border-color:rgba(148,163,184,.18);">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-success fw-bold" id="actSaveBtn">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {

  // Confirm delete actividad
  document.querySelectorAll('form.js-del-act').forEach(form => {
    form.addEventListener('submit', (ev) => {
      ev.preventDefault();
      Swal.fire({
        title: 'Eliminar actividad',
        html: `<div style="text-align:left">¿Seguro que querés eliminar esta actividad?<br><span style="opacity:.85">Esta acción no se puede deshacer.</span></div>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
        focusCancel: true,
        confirmButtonColor: '#ef4444'
      }).then(res => { if (res.isConfirmed) form.submit(); });
    });
  });

  // Modal actividad
  const btnAddAct = document.getElementById('btnAddAct');
  const modalEl = document.getElementById('actModal');
  let modal = null;
  if (modalEl) modal = new bootstrap.Modal(modalEl);

  const actModalTitle = document.getElementById('actModalTitle');
  const actAction = document.getElementById('actAction');
  const actId = document.getElementById('actId');
  const actSem = document.getElementById('actSem');
  const actFecha = document.getElementById('actFecha');
  const actTema = document.getElementById('actTema');
  const actResp = document.getElementById('actResp');
  const actPart = document.getElementById('actPart');
  const actLugar = document.getElementById('actLugar');
  const actCumplio = document.getElementById('actCumplio');
  const actDoc = document.getElementById('actDoc');
  const actSaveBtn = document.getElementById('actSaveBtn');

  if (btnAddAct && modal) {
    btnAddAct.addEventListener('click', () => {
      actModalTitle.textContent = 'Agregar actividad';
      actAction.value = 'add_act';
      actId.value = '0';
      actSem.value = '';
      actFecha.value = '';
      actTema.value = '';
      actResp.value = '';
      actPart.value = '';
      actLugar.value = '';
      actCumplio.value = '0';
      actDoc.value = '';
      actSaveBtn.textContent = 'Crear';
      modal.show();
    });
  }

  document.querySelectorAll('.js-edit-act').forEach(btn => {
    btn.addEventListener('click', () => {
      const tr = btn.closest('tr.act-row');
      if (!tr) return;

      actModalTitle.textContent = 'Editar actividad';
      actAction.value = 'edit_act';
      actId.value = tr.getAttribute('data-id') || '0';

      actSem.value = tr.getAttribute('data-sem') || '';
      actFecha.value = tr.getAttribute('data-fecha') || '';
      actTema.value = tr.getAttribute('data-tema') || '';
      actResp.value = tr.getAttribute('data-responsable') || '';
      actPart.value = tr.getAttribute('data-participantes') || '';
      actLugar.value = tr.getAttribute('data-lugar') || '';
      actCumplio.value = (tr.getAttribute('data-cumplio') || '0') === '1' ? '1' : '0';
      actDoc.value = tr.getAttribute('data-doc') || '';

      actSaveBtn.textContent = 'Guardar cambios';
      if (modal) modal.show();
    });
  });

  // Search actividades
  const actSearch = document.getElementById('actSearch');
  if (actSearch) {
    actSearch.addEventListener('input', () => {
      const q = (actSearch.value || '').toLowerCase().trim();
      document.querySelectorAll('#actTbody tr.act-row').forEach(tr => {
        const blob = (
          (tr.getAttribute('data-sem')||'') + ' ' +
          (tr.getAttribute('data-fecha')||'') + ' ' +
          (tr.getAttribute('data-tema')||'') + ' ' +
          (tr.getAttribute('data-responsable')||'') + ' ' +
          (tr.getAttribute('data-participantes')||'') + ' ' +
          (tr.getAttribute('data-lugar')||'') + ' ' +
          (tr.getAttribute('data-doc')||'')
        ).toLowerCase();
        tr.style.display = (q === '' || blob.includes(q)) ? '' : 'none';
      });
    });
  }

  // Search personal
  const perSearch = document.getElementById('perSearch');
  if (perSearch) {
    perSearch.addEventListener('input', () => {
      const q = (perSearch.value || '').toLowerCase().trim();
      document.querySelectorAll('#perTbody tr.per-row').forEach(tr => {
        const blob = tr.getAttribute('data-text') || '';
        tr.style.display = (q === '' || blob.includes(q)) ? '' : 'none';
      });
    });
  }

  // Botones matriz personal
  const checkAll = (selector) => document.querySelectorAll(selector).forEach(ch => ch.checked = true);
  const clearAll = () => document.querySelectorAll('#perTbody input[type="checkbox"]').forEach(ch => ch.checked = false);

  const b1 = document.getElementById('btnAllI');
  const b2 = document.getElementById('btnAllII');
  const b3 = document.getElementById('btnAllIII');
  const bN = document.getElementById('btnAllNIA');
  const bc = document.getElementById('btnClearAll');

  if (b1) b1.addEventListener('click', () => checkAll('#perTbody .chk-c1'));
  if (b2) b2.addEventListener('click', () => checkAll('#perTbody .chk-c2'));
  if (b3) b3.addEventListener('click', () => checkAll('#perTbody .chk-c3'));
  if (bN) bN.addEventListener('click', () => checkAll('#perTbody .chk-nia'));
  if (bc) bc.addEventListener('click', clearAll);

});
</script>

</body>
</html>
