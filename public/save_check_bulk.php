<?php
// public/save_check_bulk.php
declare(strict_types=1);

require_once __DIR__ . '/../auth/bootstrap.php';
require_login();
require_once __DIR__ . '/../config/db.php';

function starts_with($h, $n): bool { return substr($h, 0, strlen($n)) === $n; }

if (!function_exists('user_display_name')) {
  function user_display_name(): string {
    $u = $_SESSION['user'] ?? [];
    if (isset($u['grado'], $u['arma'], $u['nombre_completo'])) {
      return trim($u['grado'].' '.$u['arma'].' '.$u['nombre_completo']);
    }
    if (isset($u['display_name']))    return trim((string)$u['display_name']);
    if (isset($u['nombre_completo'])) return trim((string)$u['nombre_completo']);
    if (isset($u['username']))        return strtoupper((string)$u['username']);
    return 'Usuario';
  }
}

function is_ajax(): bool {
  $xrw = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
  if (strcasecmp($xrw, 'XMLHttpRequest') === 0) return true;

  $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
  if (stripos($accept, 'application/json') !== false) return true;

  // también AJAX si se pide modo fila
  return (($_POST['mode'] ?? '') === 'row');
}

function respond_json(int $code, array $payload): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=UTF-8');
  header('Cache-Control: no-store');
  echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}

/**
 * Pantalla "linda" (sin CDN) para cuando NO es AJAX.
 * Muestra un modal simple y permite volver (y auto-redirige).
 */
function respond_popup_html(int $code, string $title, string $message, string $backUrl): void {
  http_response_code($code);
  header('Content-Type: text/html; charset=UTF-8');
  header('Cache-Control: no-store');

  $titleEsc = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
  $msgEsc   = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
  $backEsc  = htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8');

  echo "<!doctype html>
<html lang='es'>
<head>
  <meta charset='utf-8'>
  <meta name='viewport' content='width=device-width,initial-scale=1'>
  <title>{$titleEsc}</title>
  <style>
    :root{
      --bg:#0b1220; --card:#111a2e; --txt:#e8eefc; --muted:#a9b7d0;
      --danger:#ff5a5f; --btn:#22c55e; --btn2:#16a34a;
      --shadow: 0 12px 30px rgba(0,0,0,.45);
      --radius: 18px;
    }
    body{margin:0; font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif; background:linear-gradient(120deg,#070b14,#0b1220 40%,#08122a); color:var(--txt);}
    .wrap{min-height:100vh; display:flex; align-items:center; justify-content:center; padding:18px;}
    .card{width:min(640px, 100%); background:rgba(17,26,46,.92); border:1px solid rgba(255,255,255,.08); border-radius:var(--radius); box-shadow:var(--shadow); overflow:hidden;}
    .head{display:flex; gap:12px; align-items:center; padding:18px 18px 10px;}
    .icon{width:42px;height:42px;border-radius:12px;background:rgba(255,90,95,.15);display:grid;place-items:center;border:1px solid rgba(255,90,95,.35);}
    .icon svg{width:22px;height:22px; fill:var(--danger);}
    h1{font-size:18px; margin:0;}
    .body{padding:0 18px 18px;}
    .msg{margin:10px 0 0; color:var(--muted); line-height:1.45; font-size:14px;}
    .actions{display:flex; gap:10px; padding:0 18px 18px;}
    a.btn{display:inline-flex; align-items:center; justify-content:center; padding:10px 14px; border-radius:12px; text-decoration:none; font-weight:700; background:var(--btn); color:#06210f;}
    a.btn:hover{background:var(--btn2);}
    .hint{padding:0 18px 18px; color:rgba(169,183,208,.85); font-size:12px;}
    .bar{height:4px;background:linear-gradient(90deg,var(--danger),#ffb703,#22c55e);}
  </style>
</head>
<body>
  <div class='bar'></div>
  <div class='wrap'>
    <div class='card' role='dialog' aria-modal='true'>
      <div class='head'>
        <div class='icon' aria-hidden='true'>
          <svg viewBox='0 0 24 24'><path d='M12 2 1 21h22L12 2zm0 6.8c.6 0 1 .4 1 1v5.6c0 .6-.4 1-1 1s-1-.4-1-1V9.8c0-.6.4-1 1-1zm0 10.7a1.2 1.2 0 1 1 0-2.4 1.2 1.2 0 0 1 0 2.4z'/></svg>
        </div>
        <div>
          <h1>{$titleEsc}</h1>
          <div class='msg'>{$msgEsc}</div>
        </div>
      </div>
      <div class='actions'>
        <a class='btn' href='{$backEsc}'>Volver</a>
      </div>
      <div class='hint'>Si vuelve a ocurrir, reduzca el tamaño del/los archivos o contacte al administrador para ampliar los límites del servidor.</div>
    </div>
  </div>
  <script>
    // Auto-redirección suave (opcional)
    setTimeout(function(){ window.location.href = ".json_encode($backUrl)."; }, 6500);
  </script>
</body>
</html>";
  exit;
}

function fail(int $code, string $userMsg, string $errCode = 'ERROR', array $extra = []): void {
  if (is_ajax()) {
    respond_json($code, array_merge([
      'ok' => false,
      'code' => $errCode,
      'msg' => $userMsg,
    ], $extra));
  }

  // Back: intentamos volver al ver_tabla o al referer
  $back = (string)($_SERVER['HTTP_REFERER'] ?? 'ver_tabla.php');
  respond_popup_html($code, 'No se pudo completar la acción', $userMsg, $back);
}

/** Convierte tamaños tipo "128M" a bytes */
function ini_to_bytes(string $val): int {
  $val = trim($val);
  if ($val === '') return 0;
  $last = strtolower($val[strlen($val)-1]);
  $num = (int)$val;
  return match ($last) {
    'g' => $num * 1024 * 1024 * 1024,
    'm' => $num * 1024 * 1024,
    'k' => $num * 1024,
    default => (int)$val,
  };
}

/** Formatea bytes para log/mensaje */
function fmt_bytes(int $b): string {
  if ($b <= 0) return '0 B';
  $u = ['B','KB','MB','GB','TB'];
  $i = 0; $x = (float)$b;
  while ($x >= 1024 && $i < count($u)-1) { $x /= 1024; $i++; }
  $s = number_format($x, 2, '.', '');
  $s = rtrim(rtrim($s, '0'), '.');
  return $s.' '.$u[$i];
}

function upload_err_msg(int $err): string {
  return match($err) {
    UPLOAD_ERR_INI_SIZE   => 'El archivo supera el límite del servidor (upload_max_filesize).',
    UPLOAD_ERR_FORM_SIZE  => 'El archivo supera el límite permitido por el formulario.',
    UPLOAD_ERR_PARTIAL    => 'El archivo se subió parcialmente.',
    UPLOAD_ERR_NO_FILE    => 'No se recibió ningún archivo.',
    UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal del servidor (upload_tmp_dir).',
    UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir el archivo en disco.',
    UPLOAD_ERR_EXTENSION  => 'Una extensión de PHP bloqueó la subida.',
    default               => 'Error desconocido al subir el archivo.',
  };
}

function evidencia_to_array($ev): array {
  $out = [];
  if ($ev === null || $ev === '') return $out;

  $decoded = json_decode((string)$ev, true);
  if (is_array($decoded)) {
    foreach ($decoded as $p) {
      $p = trim((string)$p);
      if ($p !== '') $out[] = $p;
    }
    return $out;
  }

  $ev = (string)$ev;
  $sep = null;
  if (strpos($ev,'|') !== false)      $sep = '|';
  elseif (strpos($ev,';') !== false) $sep = ';';
  elseif (strpos($ev,',') !== false) $sep = ',';

  if ($sep !== null) {
    foreach (explode($sep,$ev) as $p) {
      $p = trim($p);
      if ($p !== '') $out[] = $p;
    }
  } else {
    $p = trim($ev);
    if ($p !== '') $out[] = $p;
  }
  return $out;
}

function is_allowed_ext(string $filename): bool {
  $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
  $allowed = ['pdf','doc','docx','xls','xlsx','csv','ppt','pptx','rar','zip','7z','jpg','jpeg','png','webp'];
  return $ext !== '' && in_array($ext, $allowed, true);
}

function build_qs_back(string $file_rel, int $sheet, int $perPage, int $page, bool $showcolor, string $fmt, string $area): string {
  $qs = 'p=' . rawurlencode($file_rel)
      . '&s=' . (int)$sheet
      . '&pp=' . (int)$perPage
      . '&page=' . (int)$page;

  if ($showcolor) $qs .= '&showcolor=1';
  if ($fmt !== '') $qs .= '&fmt=' . rawurlencode($fmt);
  if ($area !== '') $qs .= '&area=' . rawurlencode($area);
  return $qs;
}

/* ===== Debug (apagalo en prod) ===== */
ini_set('display_errors','1');
ini_set('display_startup_errors','1');
error_reporting(E_ALL);

/* ===== Verificar método ===== */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  fail(405, 'Método no permitido.', 'METHOD_NOT_ALLOWED');
}

/**
 * ===== Diagnóstico clave =====
 * Si CONTENT_LENGTH > 0 pero PHP deja $_POST y $_FILES vacíos => post_max_size (o body del servidor web) excedido.
 */
$cl = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
$ct = (string)($_SERVER['CONTENT_TYPE'] ?? '');
$ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
$ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');

$postMax = (string)ini_get('post_max_size');
$upMax   = (string)ini_get('upload_max_filesize');
$postMaxB = ini_to_bytes($postMax);
$upMaxB   = ini_to_bytes($upMax);

if ($cl > 0 && empty($_POST) && empty($_FILES)) {
  $msg = "Tu carga no pudo procesarse porque el tamaño total excede un límite del servidor.\n\n"
       . "Tamaño enviado: ".fmt_bytes($cl)."\n"
       . "Límite del servidor (post_max_size): {$postMax}\n"
       . "Límite por archivo (upload_max_filesize): {$upMax}\n\n"
       . "Solución: reducì el tamaño del/los archivos o avisá al administrador para ampliar esos límites.";

  error_log("[save_check_bulk] POST vacio por limite. IP=$ip CL=$cl CT=$ct UA=$ua post_max_size=$postMax upload_max_filesize=$upMax");

  fail(413, $msg, 'POST_TOO_LARGE', [
    'content_length' => $cl,
    'post_max_size' => $postMax,
    'upload_max_filesize' => $upMax,
  ]);
}

/* ===== PDO ===== */
if (!isset($pdo) || !$pdo instanceof PDO) {
  $pdo = getDB();
}

/* ===== Parámetros básicos ===== */
$file_rel  = $_POST['file_rel']  ?? '';
$sheet     = isset($_POST['sheet']) ? (int)$_POST['sheet'] : 0;
$showcolor = ($_POST['showcolor'] ?? '0') === '1';
$perPage   = isset($_POST['pp'])   ? (int)$_POST['pp'] : 20;
$page      = isset($_POST['page']) ? (int)$_POST['page'] : 1;
$fmt       = (string)($_POST['fmt'] ?? '');
$area      = (string)($_POST['area'] ?? '');

$mode      = (string)($_POST['mode'] ?? '');     // '' | 'row'
$rowOnly   = isset($_POST['row_idx']) ? (int)$_POST['row_idx'] : 0;

if ($file_rel === '') {
  fail(400, 'Falta parámetro file_rel.', 'MISSING_FILE_REL');
}

/* ===== Validar que el archivo exista en rutas permitidas ===== */
$projectBase = realpath(__DIR__ . '/..');
if (!$projectBase) {
  fail(500, 'No se pudo resolver el directorio base del proyecto.', 'PROJECT_BASE_ERROR');
}

$absFile = realpath($projectBase . '/' . $file_rel);
if (!$absFile || !is_file($absFile)) {
  fail(400, 'Archivo de origen inválido.', 'INVALID_SOURCE_FILE');
}

$roots = [
  'listas_control'          => realpath($projectBase.'/storage/listas_control'),
  'ultima_inspeccion'       => realpath($projectBase.'/storage/ultima_inspeccion'),
  'visitas_de_estado_mayor' => realpath($projectBase.'/storage/visitas_de_estado_mayor'),
];

$inScope = null;
foreach ($roots as $slug => $root) {
  if ($root && starts_with($absFile, $root)) { $inScope = $slug; break; }
}
if (!$inScope) {
  fail(400, 'Ruta fuera de las carpetas permitidas.', 'OUT_OF_SCOPE');
}

/* ===== Usuario ===== */
$updatedBy = user_display_name();

/* ===== Datos recibidos ===== */
$estadoArr      = (isset($_POST['estado'])      && is_array($_POST['estado']))       ? $_POST['estado']       : [];
$obsArr         = (isset($_POST['observacion']) && is_array($_POST['observacion']))  ? $_POST['observacion']  : [];
$criticidadArr  = (isset($_POST['criticidad'])  && is_array($_POST['criticidad']))   ? $_POST['criticidad']   : [];
$formKeyArr     = (isset($_POST['form_key'])    && is_array($_POST['form_key']))     ? $_POST['form_key']     : [];
$formValArr     = (isset($_POST['form_val'])    && is_array($_POST['form_val']))     ? $_POST['form_val']     : [];

/* ===== Evidencias (múltiples por fila) ===== */
$files = $_FILES['evidencia'] ?? null;

/* ===== Asegurar tablas ===== */
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
  evidencia_path TEXT NULL,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  updated_by VARCHAR(100) NULL,
  UNIQUE KEY uq_file_row (file_rel,row_idx)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$pdo->exec("CREATE TABLE IF NOT EXISTS checklist_form (
  id INT AUTO_INCREMENT PRIMARY KEY,
  file_rel VARCHAR(512) NOT NULL,
  row_idx INT NOT NULL,
  field_key VARCHAR(128) NOT NULL,
  field_value TEXT NULL,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_file_row_field (file_rel,row_idx,field_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// compat
try { $pdo->exec("ALTER TABLE checklist ADD COLUMN caracter VARCHAR(100) NULL"); } catch (Throwable $e) {}
try { $pdo->exec("ALTER TABLE checklist ADD COLUMN updated_by VARCHAR(100) NULL"); } catch (Throwable $e) {}
try { $pdo->exec("ALTER TABLE checklist MODIFY evidencia_path TEXT NULL"); } catch (Throwable $e) {}

/* ===== Prepared statements ===== */
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

/* ===== Determinar filas a procesar ===== */
$rowIds = [];

if ($mode === 'row' && $rowOnly > 0) {
  $rowIds[$rowOnly] = true;
} else {
  foreach ([$estadoArr, $obsArr, $criticidadArr, $formKeyArr, $formValArr] as $arr) {
    foreach ($arr as $k => $_) {
      $k = (int)$k;
      if ($k > 0) $rowIds[$k] = true;
    }
  }

  if ($files && isset($files['name']) && is_array($files['name'])) {
    foreach ($files['name'] as $k => $_sub) {
      $k = (int)$k;
      if ($k > 0) $rowIds[$k] = true;
    }
  }
}

$rowIds = array_keys($rowIds);
sort($rowIds);

/* ===== Directorio evidencias (AÑO/MES) ===== */
$evidBaseRoot = $projectBase . '/storage/evidencias';
if (!is_dir($evidBaseRoot) && !@mkdir($evidBaseRoot, 0775, true)) {
  error_log("[save_check_bulk] No pudo crear $evidBaseRoot");
}

$year  = date('Y');
$month = date('m');

$evidBaseYear = $evidBaseRoot . '/' . $year;
if (!is_dir($evidBaseYear) && !@mkdir($evidBaseYear, 0775, true)) {
  error_log("[save_check_bulk] No pudo crear $evidBaseYear");
}

$evidBase = $evidBaseYear . '/' . $month;
if (!is_dir($evidBase) && !@mkdir($evidBase, 0775, true)) {
  error_log("[save_check_bulk] No pudo crear $evidBase");
}

$relBaseEvid = 'storage/evidencias/' . $year . '/' . $month . '/';

/* ===== Procesar ===== */
$savedCount = 0;
$returnRowIdx = ($mode === 'row' && $rowOnly > 0) ? $rowOnly : 0;
$returnFiles = null;

try {
  foreach ($rowIds as $idx) {
    $rowIdx = (int)$idx;
    if ($rowIdx <= 0) continue;

    $criticidad = trim((string)($criticidadArr[$rowIdx] ?? ''));
    if ($criticidad === '') $criticidad = null;

    $estado = trim((string)($estadoArr[$rowIdx] ?? ''));
    if ($estado !== 'si' && $estado !== 'no') $estado = null;

    $obs = isset($obsArr[$rowIdx]) ? trim((string)$obsArr[$rowIdx]) : '';

    // Evidencia actual
    $stSelChecklist->execute([$file_rel, $rowIdx]);
    $rowDb = $stSelChecklist->fetch(PDO::FETCH_ASSOC);
    $currentFiles = evidencia_to_array($rowDb['evidencia_path'] ?? null);

    // Archivos nuevos (múltiples) para esta fila
    if ($files && isset($files['name'][$rowIdx]) && is_array($files['name'][$rowIdx])) {
      foreach ($files['name'][$rowIdx] as $i => $origName) {
        $origName = (string)$origName;
        if ($origName === '') continue;

        $err = $files['error'][$rowIdx][$i] ?? UPLOAD_ERR_NO_FILE;
        if ($err === UPLOAD_ERR_NO_FILE) continue;

        if ($err !== UPLOAD_ERR_OK) {
          $detail = upload_err_msg((int)$err);
          error_log("[save_check_bulk] Upload error file={$origName} row={$rowIdx} i={$i} err={$err} {$detail} post_max_size=".ini_get('post_max_size')." upload_max_filesize=".ini_get('upload_max_filesize'));

          if ($mode === 'row' && $returnRowIdx === $rowIdx && is_ajax()) {
            respond_json(413, [
              'ok' => false,
              'code' => 'UPLOAD_ERROR',
              'msg' => "No se pudo subir '{$origName}'. {$detail} (upload_max_filesize=".ini_get('upload_max_filesize').", post_max_size=".ini_get('post_max_size').")",
              'row_idx' => $rowIdx,
            ]);
          }
          continue;
        }

        $tmpName = $files['tmp_name'][$rowIdx][$i] ?? '';
        if ($tmpName === '' || !is_uploaded_file($tmpName)) continue;
        if (!is_allowed_ext($origName)) continue;

        $ext      = pathinfo($origName, PATHINFO_EXTENSION);
        $safeName = preg_replace('/[^A-Za-z0-9_\-\.]/','_', pathinfo($origName, PATHINFO_FILENAME));
        if ($safeName === '') $safeName = 'evidencia';

        $finalName = $safeName . '_' . date('Ymd_His') . '_' . $rowIdx . '_' . sprintf('%03d',(int)$i);
        if ($ext) $finalName .= '.'.$ext;

        $destAbs = $evidBase . '/' . $finalName;

        if (@move_uploaded_file($tmpName, $destAbs)) {
          $relPath = $relBaseEvid . $finalName;
          $currentFiles[] = $relPath;
        } else {
          error_log("[save_check_bulk] move_uploaded_file fallo. row={$rowIdx} i={$i} orig={$origName} dest={$destAbs}");
          if ($mode === 'row' && $returnRowIdx === $rowIdx && is_ajax()) {
            respond_json(500, [
              'ok' => false,
              'code' => 'MOVE_UPLOAD_FAILED',
              'msg' => "No se pudo guardar el archivo '{$origName}' en el servidor (move_uploaded_file falló).",
              'row_idx' => $rowIdx,
            ]);
          }
        }
      }
    }

    $evToSave = null;
    if (!empty($currentFiles)) {
      $currentFiles = array_values(array_unique($currentFiles));
      $evToSave = json_encode($currentFiles, JSON_UNESCAPED_SLASHES);
    }

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

    if (isset($formKeyArr[$rowIdx])) {
      $fk = trim((string)$formKeyArr[$rowIdx]);
      if ($fk !== '') {
        $fv = isset($formValArr[$rowIdx]) ? (string)$formValArr[$rowIdx] : '';
        $stUpsertForm->execute([$file_rel, $rowIdx, $fk, $fv]);
      }
    }

    $savedCount++;

    if ($returnRowIdx === $rowIdx) {
      $returnFiles = $currentFiles;
    }
  }
} catch (Throwable $e) {
  error_log("[save_check_bulk] ERROR: ".$e->getMessage());
  fail(500, 'Error al guardar (ver logs).', 'SAVE_ERROR');
}

/* ===== Respuesta AJAX ===== */
if (is_ajax()) {
  $qsBack = build_qs_back($file_rel, $sheet, $perPage, $page, $showcolor, $fmt, $area);

  $payload = [
    'ok' => true,
    'saved_rows' => $savedCount,
    'updated_by' => $updatedBy,
    'qs_back' => $qsBack,
  ];

  if ($returnRowIdx > 0) {
    $payload['row_idx'] = $returnRowIdx;
    if (is_array($returnFiles)) $payload['files'] = $returnFiles;
  }

  respond_json(200, $payload);
}

/* ===== Volver a ver_tabla (modo normal) ===== */
$qs = build_qs_back($file_rel, $sheet, $perPage, $page, $showcolor, $fmt, $area);
$qs .= '&saved=1';
header('Location: ver_tabla.php?' . $qs);
exit;
