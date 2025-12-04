<?php
// public/save_check_bulk.php
declare(strict_types=1);

require_once __DIR__ . '/../auth/bootstrap.php';
require_login();
require_once __DIR__ . '/../config/db.php';

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function starts_with($h,$n){ return substr($h,0,strlen($n)) === $n; }

if (!function_exists('user_display_name')) {
  function user_display_name(): string {
    $u = $_SESSION['user'] ?? [];

    // ← ACÁ ESTABA EL ERROR (espacio entre $ y u)
    if (isset($u['grado'], $u['arma'], $u['nombre_completo'])) {
      return trim($u['grado'].' '.$u['arma'].' '.$u['nombre_completo']);
    }
    if (isset($u['display_name']))    return trim((string)$u['display_name']);
    if (isset($u['nombre_completo'])) return trim((string)$u['nombre_completo']);
    if (isset($u['username']))        return strtoupper((string)$u['username']);

    return 'Usuario';
  }
}

ini_set('display_errors','1');
ini_set('display_startup_errors','1');
error_reporting(E_ALL);

/* ===== Verificar método ===== */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo "Método no permitido";
  exit;
}

/* ===== Parámetros básicos ===== */
$file_rel  = $_POST['file_rel']  ?? '';
$sheet     = isset($_POST['sheet']) ? (int)$_POST['sheet'] : 0;
$showcolor = ($_POST['showcolor'] ?? '0') === '1';
$perPage   = isset($_POST['pp'])   ? (int)$_POST['pp'] : 20;
$page      = isset($_POST['page']) ? (int)$_POST['page'] : 1;
$fmt       = $_POST['fmt'] ?? '';
$area      = $_POST['area'] ?? '';

if ($file_rel === '') {
  http_response_code(400);
  echo "Falta parámetro file_rel";
  exit;
}

/* ===== Validar que el archivo exista en rutas permitidas ===== */
$projectBase = realpath(__DIR__ . '/..');
if (!$projectBase) {
  http_response_code(500);
  echo "No se pudo resolver el directorio base del proyecto.";
  exit;
}

$absFile = realpath($projectBase . '/' . $file_rel);
if (!$absFile || !is_file($absFile)) {
  http_response_code(400);
  echo "Archivo de origen inválido.";
  exit;
}

$roots = [
  'listas_control'          => realpath($projectBase.'/storage/listas_control'),
  'ultima_inspeccion'       => realpath($projectBase.'/storage/ultima_inspeccion'),
  'visitas_de_estado_mayor' => realpath($projectBase.'/storage/visitas_de_estado_mayor'),
];

$inScope = null;
foreach ($roots as $slug => $root) {
  if ($root && starts_with($absFile, $root)) {
    $inScope = $slug;
    break;
  }
}
if (!$inScope) {
  http_response_code(400);
  echo "Ruta fuera de las carpetas permitidas.";
  exit;
}

/* ===== Usuario que actualiza ===== */
$updatedBy = user_display_name();

/* ===== Datos recibidos del formulario ===== */
$estadoArr      = isset($_POST['estado'])      && is_array($_POST['estado'])      ? $_POST['estado']      : [];
$obsArr         = isset($_POST['observacion']) && is_array($_POST['observacion']) ? $_POST['observacion'] : [];
$criticidadArr  = isset($_POST['criticidad'])  && is_array($_POST['criticidad'])  ? $_POST['criticidad']  : [];
$formKeyArr     = isset($_POST['form_key'])    && is_array($_POST['form_key'])    ? $_POST['form_key']    : [];
$formValArr     = isset($_POST['form_val'])    && is_array($_POST['form_val'])    ? $_POST['form_val']    : [];

/* ===== Archivos de evidencia (múltiples por fila) ===== */
$files = $_FILES['evidencia'] ?? null;

/* ===== Asegurar tablas necesarias ===== */
$pdo->exec("CREATE TABLE IF NOT EXISTS checklist (
  id INT AUTO_INCREMENT PRIMARY KEY,
  file_rel VARCHAR(512) NOT NULL,
  row_idx INT NOT NULL,
  nro VARCHAR(100) NULL,
  descripcion TEXT NULL,
  caracter VARCHAR(100) NULL,
  accion_correctiva TEXT NULL,
  estado ENUM('si','no') NULL,
  observacion TEXT NULL,
  evidencia_path VARCHAR(512) NULL,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  updated_by VARCHAR(100) NULL,
  UNIQUE KEY uq_file_row (file_rel,row_idx)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

try { $pdo->exec("ALTER TABLE checklist ADD COLUMN caracter VARCHAR(100) NULL"); } catch (Throwable $e) { /* ignore */ }
try { $pdo->exec("ALTER TABLE checklist ADD COLUMN updated_by VARCHAR(100) NULL"); } catch (Throwable $e) { /* ignore */ }

$pdo->exec("CREATE TABLE IF NOT EXISTS checklist_form (
  id INT AUTO_INCREMENT PRIMARY KEY,
  file_rel VARCHAR(512) NOT NULL,
  row_idx INT NOT NULL,
  field_key VARCHAR(128) NOT NULL,
  field_value TEXT NULL,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_file_row_field (file_rel,row_idx,field_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

/* ===== Helpers ===== */
function evidencia_to_array($ev): array {
  $files = [];
  if ($ev === null || $ev === '') return $files;

  $decoded = json_decode((string)$ev, true);
  if (is_array($decoded)) {
    foreach ($decoded as $p) {
      $p = trim((string)$p);
      if ($p !== '') $files[] = $p;
    }
    return $files;
  }

  $ev = (string)$ev;
  $sep = null;
  if (strpos($ev,'|') !== false)      $sep = '|';
  elseif (strpos($ev,';') !== false) $sep = ';';
  elseif (strpos($ev,',') !== false) $sep = ',';

  if ($sep !== null) {
    foreach (explode($sep,$ev) as $p) {
      $p = trim($p);
      if ($p !== '') $files[] = $p;
    }
  } else {
    $p = trim($ev);
    if ($p !== '') $files[] = $p;
  }

  return $files;
}

/* Prepared statements */
$stSelChecklist = $pdo->prepare("SELECT evidencia_path FROM checklist WHERE file_rel=? AND row_idx=?");

$stInsertChecklist = $pdo->prepare("
  INSERT INTO checklist (file_rel,row_idx,caracter,estado,observacion,evidencia_path,updated_at,updated_by)
  VALUES (?,?,?,?,?,?,NOW(),?)
");

$stUpdateChecklist = $pdo->prepare("
  UPDATE checklist
     SET caracter       = ?,
         estado         = ?,
         observacion    = ?,
         evidencia_path = ?,
         updated_at     = NOW(),
         updated_by     = ?
   WHERE file_rel = ? AND row_idx = ?
");

$stUpsertForm = $pdo->prepare("
  INSERT INTO checklist_form (file_rel,row_idx,field_key,field_value,updated_at)
  VALUES (?,?,?,?,NOW())
  ON DUPLICATE KEY UPDATE
    field_value = VALUES(field_value),
    updated_at  = NOW()
");

/* ===== Determinar todos los row_idx a procesar ===== */
$rowIds = [];

foreach ([$estadoArr, $obsArr, $criticidadArr, $formKeyArr, $formValArr] as $arr) {
  foreach ($arr as $k => $_) {
    $k = (int)$k;
    if ($k > 0) $rowIds[$k] = true;
  }
}

if ($files && isset($files['name']) && is_array($files['name'])) {
  foreach ($files['name'] as $k => $_sub) {   // $_sub es array de múltiples archivos
    $k = (int)$k;
    if ($k > 0) $rowIds[$k] = true;
  }
}

$rowIds = array_keys($rowIds);
sort($rowIds);

/* ===== Directorio de evidencias (AÑO / MES) ===== */
/* Base: /storage/evidencias */
$evidBaseRoot = $projectBase . '/storage/evidencias';
if (!is_dir($evidBaseRoot)) {
  @mkdir($evidBaseRoot, 0775, true);
}

/* Subcarpetas por año y mes: /storage/evidencias/AAAA/MM */
$year  = date('Y');
$month = date('m');

$evidBaseYear = $evidBaseRoot . '/' . $year;
if (!is_dir($evidBaseYear)) {
  @mkdir($evidBaseYear, 0775, true);
}

$evidBase = $evidBaseYear . '/' . $month;
if (!is_dir($evidBase)) {
  @mkdir($evidBase, 0775, true);
}

/* Ruta relativa base que se guarda en BD */
$relBaseEvid = 'storage/evidencias/' . $year . '/' . $month . '/';

/* ===== Procesar cada fila ===== */
foreach ($rowIds as $idx) {
  $rowIdx = (int)$idx;
  if ($rowIdx <= 0) continue;

  // Criticidad
  $criticidad = $criticidadArr[$rowIdx] ?? '';
  $criticidad = trim((string)$criticidad);
  if ($criticidad === '') $criticidad = null;

  // Estado
  $estado = $estadoArr[$rowIdx] ?? '';
  $estado = trim((string)$estado);
  if ($estado !== 'si' && $estado !== 'no') $estado = null;

  // Observación
  $obs = isset($obsArr[$rowIdx]) ? trim((string)$obsArr[$rowIdx]) : '';

  // Evidencia: traer lo que ya había
  $stSelChecklist->execute([$file_rel, $rowIdx]);
  $rowDb = $stSelChecklist->fetch(PDO::FETCH_ASSOC);
  $currentFiles = evidencia_to_array($rowDb['evidencia_path'] ?? null);

  // Procesar archivos nuevos para ESTA fila (múltiples)
  if ($files && isset($files['name'][$rowIdx]) && is_array($files['name'][$rowIdx])) {
    foreach ($files['name'][$rowIdx] as $i => $origName) {
      $origName = (string)$origName;
      if ($origName === '') continue;

      $err = $files['error'][$rowIdx][$i] ?? UPLOAD_ERR_NO_FILE;
      if ($err === UPLOAD_ERR_NO_FILE) continue;
      if ($err !== UPLOAD_ERR_OK) continue;

      $tmpName = $files['tmp_name'][$rowIdx][$i] ?? '';
      if ($tmpName === '' || !is_uploaded_file($tmpName)) continue;

      $ext      = pathinfo($origName, PATHINFO_EXTENSION);
      $safeName = preg_replace('/[^A-Za-z0-9_\-\.]/','_', pathinfo($origName, PATHINFO_FILENAME));
      if ($safeName === '') $safeName = 'evidencia';

      // nombre único
      $finalName = $safeName . '_' . date('Ymd_His') . '_' . $rowIdx . '_' . sprintf('%03d',$i);
      if ($ext) $finalName .= '.'.$ext;

      // Ruta física dentro de /storage/evidencias/AAAA/MM
      $destAbs = $evidBase . '/' . $finalName;

      if (@move_uploaded_file($tmpName, $destAbs)) {
        // Ruta relativa que se guarda en BD
        $relPath = $relBaseEvid . $finalName;
        $currentFiles[] = $relPath;
      }
    }
  }

  // Convertir a JSON o NULL
  $evToSave = null;
  if (!empty($currentFiles)) {
    $currentFiles = array_values(array_unique($currentFiles));
    $evToSave = json_encode($currentFiles, JSON_UNESCAPED_SLASHES);
  }

  // Insertar o actualizar checklist
  if ($rowDb) {
    $stUpdateChecklist->execute([
      $criticidad,
      $estado,
      $obs,
      $evToSave,
      $updatedBy,
      $file_rel,
      $rowIdx
    ]);
  } else {
    $stInsertChecklist->execute([
      $file_rel,
      $rowIdx,
      $criticidad,
      $estado,
      $obs,
      $evToSave,
      $updatedBy
    ]);
  }

  // Campos formulario (si vienen)
  if (isset($formKeyArr[$rowIdx])) {
    $fk = trim((string)$formKeyArr[$rowIdx]);
    if ($fk !== '') {
      $fv = isset($formValArr[$rowIdx]) ? (string)$formValArr[$rowIdx] : '';
      $stUpsertForm->execute([$file_rel, $rowIdx, $fk, $fv]);
    }
  }
}

/* ===== Volver a ver_tabla con el mismo contexto + flag de guardado ===== */
$qs = 'p=' . rawurlencode($file_rel)
    . '&s=' . (int)$sheet
    . '&pp=' . (int)$perPage
    . '&page=' . (int)$page;

if ($showcolor) $qs .= '&showcolor=1';
if ($fmt !== '') $qs .= '&fmt=' . rawurlencode($fmt);
if ($area !== '') $qs .= '&area=' . rawurlencode($area);

$qs .= '&saved=1';

header('Location: ver_tabla.php?' . $qs);
exit;
