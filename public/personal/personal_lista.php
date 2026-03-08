<?php declare(strict_types=1);
/**
 * public/personal/personal_lista.php
 * CRUD completo de Personal (A–Z) + Import/Export Excel (A–Z) + Export PDF + Borrado total
 *
 * ✅ MEJORAS (pedido):
 * - Parte de enfermo INCREMENTAL (no manual): cada carga de PARTE (con 1+ archivos) suma +1 (ATÓMICO).
 * - Subida de archivos para PARTE/ALTA -> personal_documentos.tipo = 'parte_enfermo' / 'alta_parte_enfermo'
 * - Se actualiza resumen en personal_unidad.
 * - Se registra historial en sanidad_partes_enfermo (no bloquea si falla).
 *
 * ✅ Excel con separadores por jerarquía:
 *   filas con texto en A: "OFICIALES", "SUBOFICIALES", "SOLDADOS", "AGENTES CIVILES"
 *   -> NO se importan como personas, solo etiqueta a las filas siguientes.
 *
 * ✅ Jerarquía (FIX REAL para tu BD):
 * - En tu tabla personal_unidad.jerarquia es ENUM: ('OFICIAL','SUBOFICIAL','SOLDADO','AGENTE_CIVIL')
 * - La UI muestra plural, pero el VALUE que se guarda es el ENUM (singular).
 * - Extra_json guarda ambos (enum + label) para compatibilidad.
 *
 * ✅ FIX IMPORT EXCEL:
 * - Si falta ZipArchive (ext-zip), no se puede leer .xlsx (PhpSpreadsheet lo requiere).
 *   Mensaje claro para habilitar extension=zip en php.ini (XAMPP).
 *
 * ✅ FIX PERMISOS (pedido):
 * - DNI 41742406 SIEMPRE es SUPERADMIN aunque se borre la lista completa.
 * - Si falta su registro en personal_unidad, lo recrea mínimo para no romper módulos.
 * - “Eliminar lista completa” NO borra al superadmin.
 * - Permite importar al S-1 si CPS/Session trae roles (sin necesitar estar cargado todavía).
 *
 * ✅ ORDEN (pedido):
 * - Lista ordenada por Jerarquía y por Grado según orden custom:
 *   TG, GD, GB, CY, CR, TC, MY, CT, TP, TT, ST, SM, SP, SA, SI, SG, CI, CB, VP, VS, VS EC, A/C
 *   y luego por Apellido y Nombre.
 */

require_once __DIR__ . '/../../auth/bootstrap.php';
require_login();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// PDF libs (opcionales)
use Dompdf\Dompdf;
use Dompdf\Options;
use Mpdf\Mpdf;

/** @var PDO $pdo */
if (!isset($pdo) || !($pdo instanceof PDO)) {
  http_response_code(500);
  exit("No hay conexión PDO. Revisá config/db.php.");
}

/* ==========================================================
   CONFIGURACIÓN
   ========================================================== */
$FICHA_URL = 'personal_ficha.php'; // Ej: personal_ficha.php?id=123

/* ==========================================================
   Helpers básicos
   ========================================================== */
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function norm_dni(string $dni): string { return preg_replace('/\D+/', '', $dni) ?? ''; }
function qi(string $name): string { return '`' . str_replace('`','``', $name) . '`'; }

function csrf_if_exists(): void {
  if (function_exists('csrf_input')) {
    $out = csrf_input(); // a veces retorna string, a veces hace echo
    if (is_string($out) && $out !== '') echo $out;
  }
}

function parse_excel_or_text_date($value): ?string {
  if ($value === null || $value === '') return null;

  // Excel serial
  if (is_numeric($value)) {
    try {
      $dt = ExcelDate::excelToDateTimeObject((float)$value);
      return $dt->format('Y-m-d');
    } catch (Throwable $e) {}
  }

  $txt = trim((string)$value);
  if ($txt === '') return null;

  // normaliza separadores
  $txt = str_replace(['/', '.'], '-', $txt);

  // intenta strtotime
  $ts  = strtotime($txt);
  return ($ts !== false) ? date('Y-m-d', $ts) : null;
}
function date_or_null_from_post(string $v): ?string {
  $v = trim($v);
  if ($v === '') return null;
  $ts = strtotime($v);
  return ($ts !== false) ? date('Y-m-d', $ts) : null;
}
function to_float_or_null($v): ?float {
  if ($v === null || $v === '') return null;
  $s = trim((string)$v);
  $s = str_replace(',', '.', $s);
  return is_numeric($s) ? (float)$s : null;
}
function to_int_or_null($v): ?int {
  if ($v === null || $v === '') return null;
  $s = trim((string)$v);
  return is_numeric($s) ? (int)$s : null;
}
function parse_si_no_excel($txt): int {
  $t = mb_strtoupper(trim((string)$txt), 'UTF-8');
  return in_array($t, ['SI','S','1','TRUE','VERDADERO','YES','Y'], true) ? 1 : 0;
}

/** Normalización (para resolver destino_id por texto) */
function norm_text(string $s): string {
  $s = trim($s);
  if ($s === '') return '';
  $s = mb_strtoupper($s, 'UTF-8');
  $map = [
    'Á'=>'A','À'=>'A','Ä'=>'A','Â'=>'A',
    'É'=>'E','È'=>'E','Ë'=>'E','Ê'=>'E',
    'Í'=>'I','Ì'=>'I','Ï'=>'I','Î'=>'I',
    'Ó'=>'O','Ò'=>'O','Ö'=>'O','Ô'=>'O',
    'Ú'=>'U','Ù'=>'U','Ü'=>'U','Û'=>'U',
    'Ñ'=>'N'
  ];
  $s = strtr($s, $map);
  $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
  return $s;
}
function resolve_destino_id_from_text(string $txt, array $mapNom, array $mapCod): ?int {
  $n = norm_text($txt);
  if ($n === '') return null;
  if (isset($mapCod[$n])) return (int)$mapCod[$n];
  if (isset($mapNom[$n])) return (int)$mapNom[$n];
  foreach ($mapCod as $k=>$id)  if ($k !== '' && mb_strpos($n, $k) !== false) return (int)$id;
  foreach ($mapNom as $k=>$id)  if ($k !== '' && mb_strpos($n, $k) !== false) return (int)$id;
  return null;
}

function table_has_column(PDO $pdo, string $table, string $col): bool {
  try {
    $st = $pdo->prepare("SHOW COLUMNS FROM " . qi($table) . " LIKE ?");
    $st->execute([$col]);
    return (bool)$st->fetch(PDO::FETCH_ASSOC);
  } catch (Throwable $e) {
    return false;
  }
}

/* ==========================================================
   Jerarquía helpers (ENUM en DB, plural en UI)
   ========================================================== */
const JER_OPTS = [
  'OFICIAL'      => 'OFICIALES',
  'SUBOFICIAL'   => 'SUBOFICIALES',
  'SOLDADO'      => 'SOLDADOS',
  'AGENTE_CIVIL' => 'AGENTES CIVILES',
];

function jer_label(string $enum): string {
  return JER_OPTS[$enum] ?? $enum;
}

/**
 * Devuelve SIEMPRE el ENUM de DB: OFICIAL|SUBOFICIAL|SOLDADO|AGENTE_CIVIL
 * Acepta entradas plurales/singulares/variantes.
 */
function normalize_jerarquia_label(string $s): string {
  $t = norm_text($s);

  // Normaliza variantes comunes
  $t = str_replace(['AGENTES_CIVILES','AGENTE CIVIL','AGENTES CIVILES'], 'AGENTE_CIVIL', $t);

  if ($t === 'OFICIALES' || $t === 'OFICIAL') return 'OFICIAL';
  if ($t === 'SUBOFICIALES' || $t === 'SUBOFICIAL') return 'SUBOFICIAL';
  if ($t === 'SOLDADOS' || $t === 'SOLDADO') return 'SOLDADO';
  if ($t === 'AGENTE_CIVIL') return 'AGENTE_CIVIL';

  return '';
}

/* Fallback por grado -> ENUM */
function jerarquia_from_grado(string $grado): string {
  $g = norm_text($grado);
  if ($g === '') return 'SOLDADO';

  if (str_starts_with($g, 'AG')) return 'AGENTE_CIVIL';
  if (str_contains($g, 'CIVIL')) return 'AGENTE_CIVIL';

  // Oficiales (incluye tu orden)
  $of = ['TG','GD','GB','CY','CR','TC','MY','CT','TP','TT','ST'];
  if (in_array($g, $of, true)) return 'OFICIAL';

  // Suboficiales
  $sub = ['SM','SP','SA','SI','SG','CI','CI ART 11','CB','CB EC'];
  if (in_array($g, $sub, true)) return 'SUBOFICIAL';

  // Soldados
  if ($g === 'VP') return 'SOLDADO';
  if ($g === 'VS') return 'SOLDADO';
  if ($g === 'VS EC' || $g === 'VSEC' || $g === 'VS "EC"') return 'SOLDADO';

  // Agente civil
  if ($g === 'A/C' || $g === 'AC' || $g === 'A C') return 'AGENTE CIVIL';

  return 'SOLDADO';
}

/**
 * Compat: guardo enum + label para que no se rompan otros módulos
 */
function json_for_jerarquia(string $jerEnum): ?string {
  $jerEnum = normalize_jerarquia_label($jerEnum) ?: $jerEnum;
  if ($jerEnum === '') return null;

  $payload = [
    'jerarquia'       => $jerEnum,            // enum
    'jerarquia_label' => jer_label($jerEnum), // plural UI
  ];
  $j = json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  return is_string($j) ? $j : null;
}

/* ==========================================================
   Rutas web robustas (assets en /ea/assets)
   ========================================================== */
$SELF_WEB         = (string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
$BASE_DIR_WEB     = rtrim(str_replace('\\','/', dirname($SELF_WEB)), '/');           // /ea/public/personal
$BASE_PUBLIC_WEB  = rtrim(str_replace('\\','/', dirname($BASE_DIR_WEB)), '/');      // /ea/public
$BASE_APP_WEB     = rtrim(str_replace('\\','/', dirname($BASE_PUBLIC_WEB)), '/');   // /ea
$ASSETS_WEB       = $BASE_APP_WEB . '/assets';

$IMG_BG  = $ASSETS_WEB . '/img/fondo.png';
$ESCUDO  = $ASSETS_WEB . '/img/ecmilm.png';
$FAVICON = $ASSETS_WEB . '/img/ecmilm.png';

/* ==========================================================
   FS para storage/evidencias
   ========================================================== */
$ROOT_FS = realpath(__DIR__ . '/../../..');
if (!$ROOT_FS) {
  http_response_code(500);
  exit("No se pudo resolver ROOT del proyecto.");
}
$EVID_DIR    = $ROOT_FS . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'evidencias';
$MASTER_XLSX = $EVID_DIR . DIRECTORY_SEPARATOR . 'Lista Personal.xlsx';
if (!is_dir($EVID_DIR)) @mkdir($EVID_DIR, 0775, true);

/* ==========================================================
   Usuario -> personal_id + unidad + rol
   ========================================================== */
$user = function_exists('current_user') ? current_user() : ($_SESSION['user'] ?? null);
$dniNorm = norm_dni((string)($user['dni'] ?? $user['username'] ?? ''));

/* ==========================================================
   ✅ SUPERADMIN FIJO por DNI (pedido)
   ========================================================== */
const SUPERADMIN_DNI = '41742406';

/* ==========================================================
   Detectar columnas opcionales (jerarquia + extra_json)
   ========================================================== */
$hasJerarquiaCol = table_has_column($pdo, 'personal_unidad', 'jerarquia');
$hasExtraJsonCol = table_has_column($pdo, 'personal_unidad', 'extra_json');

/* ==========================================================
   Resolver personalId + unidadPropia (si existe)
   ========================================================== */
$personalId   = 0;
$unidadPropia = 1;
$fullNameDB   = '';

try {
  if ($dniNorm !== '') {
    $st = $pdo->prepare("
      SELECT id, unidad_id,
             CONCAT_WS(' ', grado, arma, apellido, nombre, apellido_nombre) AS nombre_comp
      FROM personal_unidad
      WHERE REPLACE(REPLACE(REPLACE(dni,'.',''),'-',''),' ','') = :dni
      LIMIT 1
    ");
    $st->execute([':dni' => $dniNorm]);
    if ($r = $st->fetch(PDO::FETCH_ASSOC)) {
      $personalId   = (int)($r['id'] ?? 0);
      $unidadPropia = (int)($r['unidad_id'] ?? 1);
      $fullNameDB   = (string)($r['nombre_comp'] ?? '');
    }
  }
} catch (Throwable $e) {}

/* ==========================================================
   Rol por BD (si existe personalId)
   ========================================================== */
$roleCodigo = 'USUARIO';

try {
  if ($personalId > 0) {
    $st = $pdo->prepare("
      SELECT r.codigo
      FROM personal_unidad pu
      INNER JOIN roles r ON r.id = pu.role_id
      WHERE pu.id = :pid
      LIMIT 1
    ");
    $st->execute([':pid'=>$personalId]);
    $c = $st->fetchColumn();
    if (is_string($c) && $c !== '') $roleCodigo = $c;
  }
} catch (Throwable $e) {}

/* Fallback por usuario_roles */
if ($roleCodigo === 'USUARIO') {
  try {
    if ($personalId > 0) {
      $st = $pdo->prepare("
        SELECT r.codigo
        FROM usuario_roles ur
        INNER JOIN roles r ON r.id = ur.role_id
        WHERE ur.personal_id = :pid
          AND (ur.unidad_id IS NULL OR ur.unidad_id = :uid)
        ORDER BY
          CASE r.codigo WHEN 'SUPERADMIN' THEN 3 WHEN 'ADMIN' THEN 2 ELSE 1 END DESC,
          ur.created_at DESC, ur.id DESC
        LIMIT 1
      ");
      $st->execute([':pid'=>$personalId, ':uid'=>$unidadPropia]);
      $c = $st->fetchColumn();
      if (is_string($c) && $c !== '') $roleCodigo = $c;
    }
  } catch (Throwable $e) {}
}

$esSuperAdmin = ($roleCodigo === 'SUPERADMIN');
$esAdmin      = ($roleCodigo === 'ADMIN') || $esSuperAdmin;

/* ✅ Forzar SUPERADMIN por DNI aunque se haya borrado personal_unidad */
if ($dniNorm === SUPERADMIN_DNI) {
  $roleCodigo = 'SUPERADMIN';
  $esSuperAdmin = true;
  $esAdmin = true;
}

/* Unidad activa */
$unidadActiva = $unidadPropia;
if ($esSuperAdmin) {
  $uSel = (int)($_SESSION['unidad_id'] ?? 0);
  if ($uSel > 0) $unidadActiva = $uSel;
}

/* ✅ Auto-recrear fila mínima del superadmin en la unidad activa si falta */
if ($dniNorm === SUPERADMIN_DNI) {
  try {
    $st = $pdo->prepare("
      SELECT id
      FROM personal_unidad
      WHERE unidad_id = :uid
        AND REPLACE(REPLACE(REPLACE(dni,'.',''),'-',''),' ','') = :dni
      LIMIT 1
    ");
    $st->execute([':uid'=>$unidadActiva, ':dni'=>SUPERADMIN_DNI]);
    $exists = (int)($st->fetchColumn() ?: 0);

    if ($exists <= 0) {
      $roleId = null;
      try {
        $stR = $pdo->prepare("SELECT id FROM roles WHERE codigo='SUPERADMIN' LIMIT 1");
        $stR->execute();
        $roleId = $stR->fetchColumn();
        $roleId = is_numeric($roleId) ? (int)$roleId : null;
      } catch (Throwable $e) {}

      $display = trim((string)($user['apellido_nombre'] ?? $user['display_name'] ?? $user['nombre'] ?? 'SUPERADMIN'));
      if ($display === '') $display = 'SUPERADMIN';

      $cols = ['unidad_id','dni','apellido_nombre','updated_at','updated_by_id'];
      $vals = [':uid',':dni',':ap','NOW()','NULL'];

      // Campos opcionales si existen en tu schema
      if (table_has_column($pdo, 'personal_unidad', 'rol')) { $cols[]='rol'; $vals[]=':rol'; }
      if (table_has_column($pdo, 'personal_unidad', 'role_id')) { $cols[]='role_id'; $vals[]=':role_id'; }
      if ($hasJerarquiaCol) { $cols[]='jerarquia'; $vals[]=':jer'; }
      if ($hasExtraJsonCol) { $cols[]='extra_json'; $vals[]=':xj'; }

      $sqlIns = "INSERT INTO personal_unidad (".implode(',',$cols).") VALUES (".implode(',',$vals).")";
      $pdo->prepare($sqlIns)->execute([
        ':uid'=>$unidadActiva,
        ':dni'=>SUPERADMIN_DNI,
        ':ap'=>$display,
        ':rol'=>'SUPERADMIN',
        ':role_id'=>$roleId,
        ':jer'=>'OFICIAL',
        ':xj'=>json_for_jerarquia('OFICIAL'),
      ]);

      // refrescar personalId si querés
      try {
        $st2 = $pdo->prepare("SELECT id FROM personal_unidad WHERE unidad_id=:uid AND dni=:dni LIMIT 1");
        $st2->execute([':uid'=>$unidadActiva, ':dni'=>SUPERADMIN_DNI]);
        $personalId = (int)($st2->fetchColumn() ?: $personalId);
      } catch (Throwable $e) {}
    }
  } catch (Throwable $e) {}
}

/* ==========================================================
   Permisos para importar (S-1 por claims de CPS/Session)
   ========================================================== */
function user_can_import(array $user, string $dniNorm, bool $esAdmin, bool $esSuperAdmin): bool {
  if ($dniNorm === SUPERADMIN_DNI) return true;
  if ($esSuperAdmin || $esAdmin) return true;

  // Ajustá según lo que te entregue CPS/Session
  $cpsRoles = $user['roles'] ?? ($_SESSION['roles'] ?? ($_SESSION['cps_roles'] ?? null));
  if (is_string($cpsRoles)) $cpsRoles = [$cpsRoles];

  if (is_array($cpsRoles)) {
    $upper = array_map(fn($x)=>mb_strtoupper(trim((string)$x),'UTF-8'), $cpsRoles);
    $allow = ['ADMIN','SUPERADMIN','S1','S-1','S_1','S-1 PERSONAL','PERSONAL','S1_PERSONAL','S1PERSONAL'];
    foreach ($upper as $r) {
      if (in_array($r, $allow, true)) return true;
    }
  }
  return false;
}

/* Branding */
$NOMBRE  = 'Unidad';
$LEYENDA = '';
try {
  $st = $pdo->prepare("SELECT nombre_completo, subnombre FROM unidades WHERE id = :id LIMIT 1");
  $st->execute([':id'=>$unidadActiva]);
  if ($u = $st->fetch(PDO::FETCH_ASSOC)) {
    if (!empty($u['nombre_completo'])) $NOMBRE = (string)$u['nombre_completo'];
    if (!empty($u['subnombre'])) $LEYENDA = trim((string)$u['subnombre']);
  }
} catch (Throwable $e) {}

/* ==========================================================
   Storage de documentos por unidad (PARTE/ALTA)
   ========================================================== */
$unidadSlug = 'unidad_' . $unidadActiva;
try {
  $hasSlug = table_has_column($pdo, 'unidades', 'slug');
  if ($hasSlug) {
    $st2 = $pdo->prepare("SELECT slug FROM unidades WHERE id=:id LIMIT 1");
    $st2->execute([':id'=>$unidadActiva]);
    $slug = $st2->fetchColumn();
    if (is_string($slug) && trim($slug) !== '') $unidadSlug = trim($slug);
  }
} catch (Throwable $e) {}

$DOCS_REL_DIR = 'storage/unidades/' . $unidadSlug . '/personal_docs';
$DOCS_ABS_DIR = $ROOT_FS . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $DOCS_REL_DIR);
if (!is_dir($DOCS_ABS_DIR)) @mkdir($DOCS_ABS_DIR, 0775, true);

/* ==========================================================
   Upload helpers
   ========================================================== */
function detect_mime(string $tmpFile): string {
  try {
    if (class_exists('finfo')) {
      $finfo = new finfo(FILEINFO_MIME_TYPE);
      $m = $finfo->file($tmpFile);
      return is_string($m) ? $m : 'application/octet-stream';
    }
  } catch (Throwable $e) {}
  $m = @mime_content_type($tmpFile);
  return is_string($m) ? $m : 'application/octet-stream';
}

function upload_docs_tipo(
  PDO $pdo,
  string $rootFs,
  string $docsRelDir,
  int $unidadId,
  int $personalTargetId,
  int $createdById,
  string $inputName,
  string $tipoDoc,
  string $tituloDoc
): int {

  if (!isset($_FILES[$inputName])) return 0;

  $files = $_FILES[$inputName];
  $names = $files['name'] ?? [];
  $tmp   = $files['tmp_name'] ?? [];
  $errs  = $files['error'] ?? [];
  $sizes = $files['size'] ?? [];

  if (!is_array($names)) {
    $names = [$names]; $tmp = [$tmp]; $errs = [$errs]; $sizes = [$sizes];
  }

  $fechaDoc = null;
  $desde = trim((string)($_POST['parte_enfermo_desde'] ?? ''));
  $hasta = trim((string)($_POST['parte_enfermo_hasta'] ?? ''));

  if ($tipoDoc === 'parte_enfermo' && $desde !== '') {
    $ts = strtotime($desde);
    if ($ts !== false) $fechaDoc = date('Y-m-d', $ts);
  } elseif ($tipoDoc === 'alta_parte_enfermo' && $hasta !== '') {
    $ts = strtotime($hasta);
    if ($ts !== false) $fechaDoc = date('Y-m-d', $ts);
  }
  if ($fechaDoc === null) $fechaDoc = date('Y-m-d');

  $allowedExt = ['pdf','jpg','jpeg','png','webp','doc','docx'];
  $allowedMime = [
    'application/pdf',
    'image/jpeg','image/png','image/webp',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/octet-stream',
  ];

  $carpRel = rtrim($docsRelDir,'/') . '/' . $personalTargetId;
  $carpAbs = $rootFs . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $carpRel);
  if (!is_dir($carpAbs)) @mkdir($carpAbs, 0775, true);

  $subidos = 0;

  foreach ($names as $i => $origName) {
    $err = $errs[$i] ?? UPLOAD_ERR_NO_FILE;
    if ($err === UPLOAD_ERR_NO_FILE) continue;
    if ($err !== UPLOAD_ERR_OK) throw new RuntimeException('Error subiendo archivo (código ' . (int)$err . ').');

    $size = (int)($sizes[$i] ?? 0);
    if ($size > 20 * 1024 * 1024) throw new RuntimeException('Archivo supera 20MB.');

    $tmpName = (string)($tmp[$i] ?? '');
    if ($tmpName === '' || !is_file($tmpName)) throw new RuntimeException('Archivo temporal inválido.');

    $ext = strtolower(pathinfo((string)$origName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
      throw new RuntimeException('Extensión no permitida: .' . $ext . ' (permitidos: ' . implode(', ', $allowedExt) . ')');
    }

    $mime = detect_mime($tmpName);
    if (!in_array($mime, $allowedMime, true)) {
      if (!in_array($ext, ['doc','docx'], true)) {
        throw new RuntimeException('Tipo MIME no permitido: ' . $mime);
      }
    }

    $safeName = preg_replace('/[^A-Za-z0-9_\.-]/', '_', (string)$origName);
    if ($safeName === '') $safeName = $tipoDoc . '.' . $ext;

    $filename = time() . '_' . $personalTargetId . '_' . $i . '_' . $safeName;
    $destRel  = $carpRel . '/' . $filename;
    $destAbs  = $carpAbs . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($tmpName, $destAbs)) {
      throw new RuntimeException('No se pudo mover el archivo subido.');
    }

    try {
      $st = $pdo->prepare("
        INSERT INTO personal_documentos
          (unidad_id, personal_id, tipo, titulo, path, nota, fecha, created_at, created_by_id)
        VALUES
          (:uid, :pid, :tipo, :titulo, :path, :nota, :fecha, NOW(), :cbid)
      ");
      $st->execute([
        ':uid'   => $unidadId,
        ':pid'   => $personalTargetId,
        ':tipo'  => $tipoDoc,
        ':titulo'=> $tituloDoc,
        ':path'  => $destRel,
        ':nota'  => null,
        ':fecha' => $fechaDoc,
        ':cbid'  => ($createdById > 0 ? $createdById : null),
      ]);
    } catch (Throwable $ex) {
      @unlink($destAbs);
      throw $ex;
    }

    $subidos++;
  }

  return $subidos;
}

/* ==========================================================
   Sanidad incremental (ATÓMICO)
   ========================================================== */
function aplicar_incremento_parte_evento(PDO $pdo, int $unidadId, int $personalId, ?string $desde, ?string $hasta, int $updatedById): void {
  $desdeFinal = $desde ?: null;
  $hastaFinal = $hasta ?: null;

  $st = $pdo->prepare("
    UPDATE personal_unidad
    SET
      tiene_parte_enfermo = 1,
      parte_enfermo_desde = COALESCE(:desde, parte_enfermo_desde, CURDATE()),
      parte_enfermo_hasta = COALESCE(:hasta, parte_enfermo_hasta),
      cantidad_parte_enfermo = COALESCE(cantidad_parte_enfermo, 0) + 1,
      updated_at = NOW(),
      updated_by_id = :ub
    WHERE id = :id AND unidad_id = :uid
    LIMIT 1
  ");
  $st->execute([
    ':desde' => $desdeFinal,
    ':hasta' => $hastaFinal,
    ':ub'    => ($updatedById > 0 ? $updatedById : null),
    ':id'    => $personalId,
    ':uid'   => $unidadId,
  ]);

  $st2 = $pdo->prepare("
    SELECT cantidad_parte_enfermo, parte_enfermo_desde, parte_enfermo_hasta
    FROM personal_unidad
    WHERE id=:id AND unidad_id=:uid
    LIMIT 1
  ");
  $st2->execute([':id'=>$personalId, ':uid'=>$unidadId]);
  $cur = $st2->fetch(PDO::FETCH_ASSOC);
  if (!$cur) return;

  $cantFinal = (int)($cur['cantidad_parte_enfermo'] ?? 0);
  $iniFinal  = $cur['parte_enfermo_desde'] ?? null;
  $finFinal  = $cur['parte_enfermo_hasta'] ?? null;

  try {
    $st3 = $pdo->prepare("
      INSERT INTO sanidad_partes_enfermo
        (unidad_id, personal_id, tiene_parte, inicio, fin, cantidad, observaciones, updated_at, updated_by_id)
      VALUES
        (:uid, :pid, 'si', :ini, :fin, :cant, :obs, NOW(), :ub)
    ");
    $st3->execute([
      ':uid'=>$unidadId,
      ':pid'=>$personalId,
      ':ini'=>$iniFinal,
      ':fin'=>$finFinal,
      ':cant'=>$cantFinal,
      ':obs'=>null,
      ':ub'=>($updatedById>0?$updatedById:null),
    ]);
  } catch (Throwable $e) {}
}

function aplicar_alta_evento(PDO $pdo, int $unidadId, int $personalId, ?string $hasta, int $updatedById): void {
  $hastaFinal = $hasta ?: date('Y-m-d');

  $st = $pdo->prepare("
    SELECT
      COALESCE(cantidad_parte_enfermo,0) AS cant,
      parte_enfermo_desde
    FROM personal_unidad
    WHERE id=:id AND unidad_id=:uid
    LIMIT 1
  ");
  $st->execute([':id'=>$personalId, ':uid'=>$unidadId]);
  $cur = $st->fetch(PDO::FETCH_ASSOC);
  if (!$cur) return;

  $cant = (int)($cur['cant'] ?? 0);
  $desde = $cur['parte_enfermo_desde'] ?? null;

  $st = $pdo->prepare("
    UPDATE personal_unidad
    SET
      tiene_parte_enfermo = 0,
      parte_enfermo_hasta = :hasta,
      updated_at = NOW(),
      updated_by_id = :ub
    WHERE id=:id AND unidad_id=:uid
    LIMIT 1
  ");
  $st->execute([
    ':hasta'=>$hastaFinal,
    ':ub'=>($updatedById>0?$updatedById:null),
    ':id'=>$personalId,
    ':uid'=>$unidadId,
  ]);

  try {
    $st = $pdo->prepare("
      INSERT INTO sanidad_partes_enfermo
        (unidad_id, personal_id, tiene_parte, inicio, fin, cantidad, observaciones, updated_at, updated_by_id)
      VALUES
        (:uid, :pid, 'no', :ini, :fin, :cant, :obs, NOW(), :ub)
    ");
    $st->execute([
      ':uid'=>$unidadId,
      ':pid'=>$personalId,
      ':ini'=>$desde,
      ':fin'=>$hastaFinal,
      ':cant'=>$cant,
      ':obs'=>null,
      ':ub'=>($updatedById>0?$updatedById:null),
    ]);
  } catch (Throwable $e) {}
}

/* ==========================================================
   Validación de esquema (solo columnas críticas)
   ========================================================== */
$requiredCols = [
  'id','unidad_id','dni','destino_id','updated_at','updated_by_id',
  'grado','arma','apellido_nombre','cuil','fecha_nac','peso','altura','sexo','domicilio',
  'estado_civil','hijos','nou','nro_cta','cbu','alias_banco','fecha_ultimo_anexo27',
  'tiene_parte_enfermo','parte_enfermo_desde','parte_enfermo_hasta','cantidad_parte_enfermo',
  'destino_interno','rol','anios_en_destino','fracc','observaciones'
];

$missingCols = [];
try {
  $st = $pdo->prepare("
    SELECT COLUMN_NAME
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'personal_unidad'
  ");
  $st->execute();
  $existing = $st->fetchAll(PDO::FETCH_COLUMN) ?: [];
  $existingMap = array_fill_keys($existing, true);
  foreach ($requiredCols as $c) {
    if (!isset($existingMap[$c])) $missingCols[] = $c;
  }
} catch (Throwable $e) {}

/* ==========================================================
   Precargar destinos (combos y resolver destino_id)
   ========================================================== */
$destinosCombo = [];
$destinoMapNorm = [];
$destinoMapCod  = [];
$destinoIdToNombre = [];

try {
  $st = $pdo->prepare("SELECT id, codigo, nombre FROM destino WHERE unidad_id=:uid AND activo=1 ORDER BY id ASC");
  $st->execute([':uid'=>$unidadActiva]);
  $destinosCombo = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  foreach ($destinosCombo as $d) {
    $id = (int)$d['id'];
    $nom = (string)($d['nombre'] ?? '');
    $cod = (string)($d['codigo'] ?? '');
    $destinoIdToNombre[$id] = $nom;

    $nn = norm_text($nom);
    if ($nn !== '') $destinoMapNorm[$nn] = $id;

    $cc = norm_text($cod);
    if ($cc !== '') $destinoMapCod[$cc] = $id;
  }
} catch (Throwable $e) {}

/* ==========================================================
   Mensajes
   ========================================================== */
$mensajeOk = '';
$mensajeError = '';
$listadoError = '';

/* ==========================================================
   ACCIONES POST
   ========================================================== */
$accion = (string)($_POST['accion'] ?? '');

try {

  /* ---------- IMPORTAR / ACTUALIZAR EXCEL (A–Z) ---------- */
  if ($accion === 'subir_excel') {
    if (!user_can_import((array)$user, $dniNorm, $esAdmin, $esSuperAdmin)) {
      throw new RuntimeException("Acceso restringido. Solo ADMIN/SUPERADMIN (o S-1 autorizado) puede importar.");
    }

    if (!isset($_FILES['archivo_excel']) || ($_FILES['archivo_excel']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
      throw new RuntimeException('No se recibió el archivo Excel o hubo un error en la subida.');
    }

    $fileTmp  = (string)$_FILES['archivo_excel']['tmp_name'];
    $fileName = (string)($_FILES['archivo_excel']['name'] ?? 'listado.xlsx');
    $ext      = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if (!in_array($ext, ['xls','xlsx'], true)) {
      throw new RuntimeException('El archivo debe ser Excel (.xls o .xlsx).');
    }

    // ✅ Fix: .xlsx requiere ZipArchive (ext-zip)
    if ($ext === 'xlsx' && !class_exists('ZipArchive')) {
      throw new RuntimeException(
        "No se puede importar .xlsx porque falta la extensión ZIP (ZipArchive).\n".
        "En XAMPP: Apache > Config > php.ini -> habilitar: extension=zip y reiniciar Apache."
      );
    }

    $spreadsheet = IOFactory::load($fileTmp);
    $sheet       = $spreadsheet->getActiveSheet();
    $highestRow  = (int)$sheet->getHighestRow();

    // SQL dinámico: incluye jerarquia si existe, extra_json si existe
    $cols = [
      'unidad_id','dni',
      'grado','arma','apellido_nombre','cuil','fecha_nac','peso','altura','sexo','domicilio',
      'estado_civil','hijos','nou','nro_cta','cbu','alias_banco','fecha_ultimo_anexo27',
      'tiene_parte_enfermo','parte_enfermo_desde','parte_enfermo_hasta','cantidad_parte_enfermo',
      'destino_id','destino_interno',
      'rol','anios_en_destino','fracc','observaciones'
    ];

    if ($hasJerarquiaCol) $cols[] = 'jerarquia';
    if ($hasExtraJsonCol) $cols[] = 'extra_json';

    $cols[] = 'updated_at';
    $cols[] = 'updated_by_id';

    $placeholders = array_map(fn($c)=>($c==='updated_at'?'NOW()':':'.$c), $cols);

    // DUPLICATE KEY UPDATE
    $upd = [
      "grado = VALUES(grado)",
      "arma = VALUES(arma)",
      "apellido_nombre = VALUES(apellido_nombre)",
      "cuil = VALUES(cuil)",
      "fecha_nac = VALUES(fecha_nac)",
      "peso = VALUES(peso)",
      "altura = VALUES(altura)",
      "sexo = VALUES(sexo)",
      "domicilio = VALUES(domicilio)",
      "estado_civil = VALUES(estado_civil)",
      "hijos = VALUES(hijos)",
      "nou = VALUES(nou)",
      "nro_cta = VALUES(nro_cta)",
      "cbu = VALUES(cbu)",
      "alias_banco = VALUES(alias_banco)",
      "fecha_ultimo_anexo27 = VALUES(fecha_ultimo_anexo27)",
      "tiene_parte_enfermo = VALUES(tiene_parte_enfermo)",
      "parte_enfermo_desde = VALUES(parte_enfermo_desde)",
      "parte_enfermo_hasta = VALUES(parte_enfermo_hasta)",
      // ✅ NO pisa el conteo incremental si ya existe (solo llena si está NULL)
      "cantidad_parte_enfermo = CASE WHEN cantidad_parte_enfermo IS NULL THEN VALUES(cantidad_parte_enfermo) ELSE cantidad_parte_enfermo END",
      "destino_id = VALUES(destino_id)",
      "destino_interno = VALUES(destino_interno)",
      "rol = VALUES(rol)",
      "anios_en_destino = VALUES(anios_en_destino)",
      "fracc = VALUES(fracc)",
      "observaciones = VALUES(observaciones)"
    ];

    if ($hasJerarquiaCol) {
      $upd[] = "jerarquia = VALUES(jerarquia)";
    }
    if ($hasExtraJsonCol) {
      // merge extra_json sin pisar el resto
      $upd[] = "extra_json =
        CASE
          WHEN extra_json IS NULL THEN VALUES(extra_json)
          ELSE
            CASE
              WHEN VALUES(extra_json) IS NULL THEN extra_json
              ELSE JSON_MERGE_PATCH(extra_json, VALUES(extra_json))
            END
        END";
    }

    $upd[] = "updated_at = NOW()";
    $upd[] = "updated_by_id = VALUES(updated_by_id)";

    $sql = "
      INSERT INTO personal_unidad
      (".implode(',', $cols).")
      VALUES
      (".implode(',', $placeholders).")
      ON DUPLICATE KEY UPDATE
      ".implode(",\n", $upd)."
    ";

    $pdo->beginTransaction();
    $stmt = $pdo->prepare($sql);
    $procesadas = 0;

    // ✅ Excel con separadores: trackea jerarquía por fila (ENUM)
    $currentJer = '';

    for ($row = 2; $row <= $highestRow; $row++) {
      $gradoRaw = trim((string)$sheet->getCell("A{$row}")->getValue());
      $arma           = trim((string)$sheet->getCell("B{$row}")->getValue());
      $apNom          = trim((string)$sheet->getCell("C{$row}")->getValue());
      $dni            = trim((string)$sheet->getCell("D{$row}")->getValue());

      // Detecta separador jerárquico: "OFICIALES", etc. -> devuelve ENUM
      $maybeJerEnum = normalize_jerarquia_label($gradoRaw);
      if ($maybeJerEnum !== '' && $arma === '' && $apNom === '' && $dni === '') {
        $currentJer = $maybeJerEnum;
        continue;
      }

      $cuil           = trim((string)$sheet->getCell("E{$row}")->getValue());
      $fechaNac       = parse_excel_or_text_date($sheet->getCell("F{$row}")->getValue());
      $peso           = to_float_or_null($sheet->getCell("G{$row}")->getValue());
      $altura         = to_float_or_null($sheet->getCell("H{$row}")->getValue());
      $sexo           = trim((string)$sheet->getCell("I{$row}")->getValue());
      $domicilio      = trim((string)$sheet->getCell("J{$row}")->getValue());
      $estadoCivil    = trim((string)$sheet->getCell("K{$row}")->getValue());
      $hijos          = to_int_or_null($sheet->getCell("L{$row}")->getValue());
      $nou            = trim((string)$sheet->getCell("M{$row}")->getValue());
      $nroCta         = trim((string)$sheet->getCell("N{$row}")->getValue());
      $cbu            = trim((string)$sheet->getCell("O{$row}")->getValue());
      $aliasBanco     = trim((string)$sheet->getCell("P{$row}")->getValue());
      $fechaAnexo27   = parse_excel_or_text_date($sheet->getCell("Q{$row}")->getValue());
      $tieneParte     = parse_si_no_excel($sheet->getCell("R{$row}")->getValue());
      $parteDesde     = parse_excel_or_text_date($sheet->getCell("S{$row}")->getValue());
      $parteHasta     = parse_excel_or_text_date($sheet->getCell("T{$row}")->getValue());
      $cantPartes     = to_int_or_null($sheet->getCell("U{$row}")->getValue()); // carga inicial (si viene)
      $destinoInterno = trim((string)$sheet->getCell("V{$row}")->getValue());
      $rolExcel       = trim((string)$sheet->getCell("W{$row}")->getValue());
      $aniosDestino   = to_float_or_null($sheet->getCell("X{$row}")->getValue());
      $fracc          = trim((string)$sheet->getCell("Y{$row}")->getValue());
      $obs            = trim((string)$sheet->getCell("Z{$row}")->getValue());

      // fila totalmente vacía -> saltar
      if ($dni === '' && $apNom === '' && $gradoRaw === '' && $arma === '') continue;

      $dni = norm_dni($dni);
      if ($dni === '') continue;

      // Jerarquía final (ENUM): separador si existe; sino por grado
      $jerEnum = ($currentJer !== '') ? $currentJer : jerarquia_from_grado($gradoRaw);
      $jerEnum = normalize_jerarquia_label($jerEnum) ?: 'SOLDADO';

      $extraJson = $hasExtraJsonCol ? json_for_jerarquia($jerEnum) : null;

      $destinoId = null;
      if ($destinoInterno !== '') {
        $destinoId = resolve_destino_id_from_text($destinoInterno, $destinoMapNorm, $destinoMapCod);
      }
      if (($destinoInterno === '' || $destinoInterno === null) && $destinoId !== null) {
        $destinoInterno = (string)($destinoIdToNombre[$destinoId] ?? '');
      }

      $params = [
        ':unidad_id' => $unidadActiva,
        ':dni' => $dni,

        ':grado' => ($gradoRaw !== '' ? $gradoRaw : null),
        ':arma' => ($arma !== '' ? $arma : null),
        ':apellido_nombre' => ($apNom !== '' ? $apNom : null),
        ':cuil' => ($cuil !== '' ? $cuil : null),
        ':fecha_nac' => $fechaNac,
        ':peso' => $peso,
        ':altura' => $altura,
        ':sexo' => ($sexo !== '' ? $sexo : null),
        ':domicilio' => ($domicilio !== '' ? $domicilio : null),

        ':estado_civil' => ($estadoCivil !== '' ? $estadoCivil : null),
        ':hijos' => $hijos,
        ':nou' => ($nou !== '' ? $nou : null),
        ':nro_cta' => ($nroCta !== '' ? $nroCta : null),
        ':cbu' => ($cbu !== '' ? $cbu : null),
        ':alias_banco' => ($aliasBanco !== '' ? $aliasBanco : null),
        ':fecha_ultimo_anexo27' => $fechaAnexo27,

        ':tiene_parte_enfermo' => $tieneParte,
        ':parte_enfermo_desde' => $parteDesde,
        ':parte_enfermo_hasta' => $parteHasta,
        ':cantidad_parte_enfermo' => $cantPartes,

        ':destino_id' => $destinoId,
        ':destino_interno' => ($destinoInterno !== '' ? $destinoInterno : null),

        ':rol' => ($rolExcel !== '' ? $rolExcel : null),
        ':anios_en_destino' => $aniosDestino,
        ':fracc' => ($fracc !== '' ? $fracc : null),
        ':observaciones' => ($obs !== '' ? $obs : null),

        ':updated_by_id' => ($personalId > 0 ? $personalId : null),
      ];

      if ($hasJerarquiaCol) $params[':jerarquia'] = $jerEnum;
      if ($hasExtraJsonCol) $params[':extra_json'] = $extraJson;

      $stmt->execute($params);
      $procesadas++;
    }

    $pdo->commit();
    @copy($fileTmp, $MASTER_XLSX);

    $mensajeOk = "Importación completada. Filas procesadas: {$procesadas}. (Actualiza por UNIQUE: unidad_id + dni).";
  }

  /* ---------- ALTA / EDICIÓN MANUAL (A–Z) + UPLOAD PARTE/ALTA + INCREMENTAL ---------- */
  if ($accion === 'guardar_nuevo' || $accion === 'guardar_edicion') {
    if (!$esAdmin) throw new RuntimeException("Acceso restringido. Solo ADMIN/SUPERADMIN puede guardar.");

    $id = (int)($_POST['id'] ?? 0);

    // A–Z
    $grado = trim((string)($_POST['grado'] ?? ''));
    $arma  = trim((string)($_POST['arma'] ?? ''));
    $apellidoNombre = trim((string)($_POST['apellido_nombre'] ?? ''));
    $dni   = norm_dni((string)($_POST['dni'] ?? ''));
    $cuil  = trim((string)($_POST['cuil'] ?? ''));
    $fechaNac = date_or_null_from_post((string)($_POST['fecha_nac'] ?? ''));
    $peso   = to_float_or_null($_POST['peso'] ?? null);
    $altura = to_float_or_null($_POST['altura'] ?? null);
    $sexo   = trim((string)($_POST['sexo'] ?? ''));
    $domicilio = trim((string)($_POST['domicilio'] ?? ''));
    $estadoCivil = trim((string)($_POST['estado_civil'] ?? ''));
    $hijos = to_int_or_null($_POST['hijos'] ?? null);
    $nou = trim((string)($_POST['nou'] ?? ''));
    $nroCta = trim((string)($_POST['nro_cta'] ?? ''));
    $cbu = trim((string)($_POST['cbu'] ?? ''));
    $aliasBanco = trim((string)($_POST['alias_banco'] ?? ''));
    $fechaAnexo27 = date_or_null_from_post((string)($_POST['fecha_ultimo_anexo27'] ?? ''));

    // Jerarquía (UI) -> ENUM DB
    $jerPost = normalize_jerarquia_label((string)($_POST['jerarquia'] ?? ''));
    if ($jerPost === '') $jerPost = jerarquia_from_grado($grado);
    $jerPost = normalize_jerarquia_label($jerPost) ?: 'SOLDADO';
    $extraJson = $hasExtraJsonCol ? json_for_jerarquia($jerPost) : null;

    // Sanidad (resumen editable), PERO cantidad NO se toma del POST.
    $tieneParte = isset($_POST['tiene_parte_enfermo']) ? 1 : 0;
    $parteDesde = date_or_null_from_post((string)($_POST['parte_enfermo_desde'] ?? ''));
    $parteHasta = date_or_null_from_post((string)($_POST['parte_enfermo_hasta'] ?? ''));

    $destinoInterno = trim((string)($_POST['destino_interno'] ?? ''));
    $rolExcel = trim((string)($_POST['rol'] ?? ''));
    $aniosDestino = to_float_or_null($_POST['anios_en_destino'] ?? null);
    $fracc = trim((string)($_POST['fracc'] ?? ''));
    $obs = trim((string)($_POST['observaciones'] ?? ''));

    // destino_id (select)
    $destinoIdSel = (int)($_POST['destino_id'] ?? 0);
    $destinoId = ($destinoIdSel > 0) ? $destinoIdSel : null;

    if ($dni === '') throw new RuntimeException("DNI es obligatorio.");
    if ($apellidoNombre === '') throw new RuntimeException("Apellido y Nombre es obligatorio.");

    if ($destinoId === null && $destinoInterno !== '') {
      $destinoId = resolve_destino_id_from_text($destinoInterno, $destinoMapNorm, $destinoMapCod);
    }
    if (($destinoInterno === '' || $destinoInterno === null) && $destinoId !== null) {
      $destinoInterno = (string)($destinoIdToNombre[$destinoId] ?? '');
    }

    if ($accion === 'guardar_nuevo') {

      // INSERT dinámico
      $cols = [
        'unidad_id','dni',
        'grado','arma','apellido_nombre','cuil','fecha_nac','peso','altura','sexo','domicilio',
        'estado_civil','hijos','nou','nro_cta','cbu','alias_banco','fecha_ultimo_anexo27',
        'tiene_parte_enfermo','parte_enfermo_desde','parte_enfermo_hasta','cantidad_parte_enfermo',
        'destino_id','destino_interno',
        'rol','anios_en_destino','fracc','observaciones'
      ];
      if ($hasJerarquiaCol) $cols[] = 'jerarquia';
      if ($hasExtraJsonCol) $cols[] = 'extra_json';
      $cols[] = 'updated_at';
      $cols[] = 'updated_by_id';

      $vals = array_map(fn($c)=>($c==='updated_at'?'NOW()':':'.$c), $cols);

      $sql = "INSERT INTO personal_unidad (".implode(',', $cols).") VALUES (".implode(',', $vals).")";

      $params = [
        ':unidad_id'=>$unidadActiva, ':dni'=>$dni,
        ':grado'=>($grado!==''?$grado:null),
        ':arma'=>($arma!==''?$arma:null),
        ':apellido_nombre'=>$apellidoNombre,
        ':cuil'=>($cuil!==''?$cuil:null),
        ':fecha_nac'=>$fechaNac,
        ':peso'=>$peso, ':altura'=>$altura,
        ':sexo'=>($sexo!==''?$sexo:null),
        ':domicilio'=>($domicilio!==''?$domicilio:null),
        ':estado_civil'=>($estadoCivil!==''?$estadoCivil:null),
        ':hijos'=>$hijos,
        ':nou'=>($nou!==''?$nou:null),
        ':nro_cta'=>($nroCta!==''?$nroCta:null),
        ':cbu'=>($cbu!==''?$cbu:null),
        ':alias_banco'=>($aliasBanco!==''?$aliasBanco:null),
        ':fecha_ultimo_anexo27'=>$fechaAnexo27,
        ':tiene_parte_enfermo'=>$tieneParte,
        ':parte_enfermo_desde'=>$parteDesde,
        ':parte_enfermo_hasta'=>$parteHasta,
        ':cantidad_parte_enfermo'=>null, // incremental
        ':destino_id'=>$destinoId,
        ':destino_interno'=>($destinoInterno!==''?$destinoInterno:null),
        ':rol'=>($rolExcel!==''?$rolExcel:null),
        ':anios_en_destino'=>$aniosDestino,
        ':fracc'=>($fracc!==''?$fracc:null),
        ':observaciones'=>($obs!==''?$obs:null),
        ':updated_by_id'=>($personalId>0?$personalId:null),
      ];
      if ($hasJerarquiaCol) $params[':jerarquia'] = $jerPost;       // ENUM
      if ($hasExtraJsonCol) $params[':extra_json'] = $extraJson;

      $st = $pdo->prepare($sql);
      $st->execute($params);

      $targetId = (int)$pdo->lastInsertId();

      $nParteDocs = upload_docs_tipo(
        $pdo, $ROOT_FS, $DOCS_REL_DIR,
        $unidadActiva, $targetId, (int)$personalId,
        'parte_archivos',
        'parte_enfermo',
        'Parte de enfermo'
      );

      if ($nParteDocs > 0) {
        aplicar_incremento_parte_evento($pdo, $unidadActiva, $targetId, $parteDesde, $parteHasta, (int)$personalId);
      }

      $nAltaDocs = upload_docs_tipo(
        $pdo, $ROOT_FS, $DOCS_REL_DIR,
        $unidadActiva, $targetId, (int)$personalId,
        'alta_archivos',
        'alta_parte_enfermo',
        'Alta parte de enfermo'
      );

      if ($nAltaDocs > 0) {
        aplicar_alta_evento($pdo, $unidadActiva, $targetId, $parteHasta, (int)$personalId);
      }

      $msgExtra = [];
      if ($nParteDocs > 0) $msgExtra[] = "Parte: {$nParteDocs} archivo(s) (cantidad +1)";
      if ($nAltaDocs > 0)  $msgExtra[] = "Alta: {$nAltaDocs} archivo(s)";

      $mensajeOk = 'Personal agregado correctamente.' . (!empty($msgExtra) ? ' (' . implode(' · ', $msgExtra) . ')' : '');

    } else {
      if ($id <= 0) throw new RuntimeException("ID inválido para edición.");

      // UPDATE dinámico (jerarquia / extra_json opcional)
      $set = [
        "dni = :dni",
        "grado = :grado",
        "arma = :arma",
        "apellido_nombre = :apellido_nombre",
        "cuil = :cuil",
        "fecha_nac = :fecha_nac",
        "peso = :peso",
        "altura = :altura",
        "sexo = :sexo",
        "domicilio = :domicilio",
        "estado_civil = :estado_civil",
        "hijos = :hijos",
        "nou = :nou",
        "nro_cta = :nro_cta",
        "cbu = :cbu",
        "alias_banco = :alias_banco",
        "fecha_ultimo_anexo27 = :fecha_ultimo_anexo27",
        "tiene_parte_enfermo = :tiene_parte_enfermo",
        "parte_enfermo_desde = :parte_enfermo_desde",
        "parte_enfermo_hasta = :parte_enfermo_hasta",
        "destino_id = :destino_id",
        "destino_interno = :destino_interno",
        "rol = :rol",
        "anios_en_destino = :anios_en_destino",
        "fracc = :fracc",
        "observaciones = :observaciones"
      ];

      if ($hasJerarquiaCol) $set[] = "jerarquia = :jerarquia";

      if ($hasExtraJsonCol) {
        $set[] = "extra_json =
          CASE
            WHEN extra_json IS NULL THEN :extra_json
            ELSE
              CASE
                WHEN :extra_json IS NULL THEN extra_json
                ELSE JSON_MERGE_PATCH(extra_json, :extra_json)
              END
          END";
      }

      $set[] = "updated_at = NOW()";
      $set[] = "updated_by_id = :updated_by_id";

      $sql = "
        UPDATE personal_unidad
        SET ".implode(",\n", $set)."
        WHERE id = :id AND unidad_id = :unidad_id
        LIMIT 1
      ";

      $params = [
        ':id'=>$id, ':unidad_id'=>$unidadActiva,
        ':dni'=>$dni,
        ':grado'=>($grado!==''?$grado:null),
        ':arma'=>($arma!==''?$arma:null),
        ':apellido_nombre'=>$apellidoNombre,
        ':cuil'=>($cuil!==''?$cuil:null),
        ':fecha_nac'=>$fechaNac,
        ':peso'=>$peso, ':altura'=>$altura,
        ':sexo'=>($sexo!==''?$sexo:null),
        ':domicilio'=>($domicilio!==''?$domicilio:null),
        ':estado_civil'=>($estadoCivil!==''?$estadoCivil:null),
        ':hijos'=>$hijos,
        ':nou'=>($nou!==''?$nou:null),
        ':nro_cta'=>($nroCta!==''?$nroCta:null),
        ':cbu'=>($cbu!==''?$cbu:null),
        ':alias_banco'=>($aliasBanco!==''?$aliasBanco:null),
        ':fecha_ultimo_anexo27'=>$fechaAnexo27,
        ':tiene_parte_enfermo'=>$tieneParte,
        ':parte_enfermo_desde'=>$parteDesde,
        ':parte_enfermo_hasta'=>$parteHasta,
        ':destino_id'=>$destinoId,
        ':destino_interno'=>($destinoInterno!==''?$destinoInterno:null),
        ':rol'=>($rolExcel!==''?$rolExcel:null),
        ':anios_en_destino'=>$aniosDestino,
        ':fracc'=>($fracc!==''?$fracc:null),
        ':observaciones'=>($obs!==''?$obs:null),
        ':updated_by_id'=>($personalId>0?$personalId:null),
      ];
      if ($hasJerarquiaCol) $params[':jerarquia'] = $jerPost;      // ENUM
      if ($hasExtraJsonCol) $params[':extra_json'] = $extraJson;

      $st = $pdo->prepare($sql);
      $st->execute($params);

      $nParteDocs = upload_docs_tipo(
        $pdo, $ROOT_FS, $DOCS_REL_DIR,
        $unidadActiva, $id, (int)$personalId,
        'parte_archivos',
        'parte_enfermo',
        'Parte de enfermo'
      );

      if ($nParteDocs > 0) {
        aplicar_incremento_parte_evento($pdo, $unidadActiva, $id, $parteDesde, $parteHasta, (int)$personalId);
      }

      $nAltaDocs = upload_docs_tipo(
        $pdo, $ROOT_FS, $DOCS_REL_DIR,
        $unidadActiva, $id, (int)$personalId,
        'alta_archivos',
        'alta_parte_enfermo',
        'Alta parte de enfermo'
      );

      if ($nAltaDocs > 0) {
        aplicar_alta_evento($pdo, $unidadActiva, $id, $parteHasta, (int)$personalId);
      }

      $msgExtra = [];
      if ($nParteDocs > 0) $msgExtra[] = "Parte: {$nParteDocs} archivo(s) (cantidad +1)";
      if ($nAltaDocs > 0)  $msgExtra[] = "Alta: {$nAltaDocs} archivo(s)";

      $mensajeOk = 'Personal actualizado correctamente.' . (!empty($msgExtra) ? ' (' . implode(' · ', $msgExtra) . ')' : '');
    }
  }

  /* ---------- BORRAR INDIVIDUAL ---------- */
  if ($accion === 'borrar_individual') {
    if (!$esAdmin) throw new RuntimeException("Acceso restringido. Solo ADMIN/SUPERADMIN puede eliminar.");

    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) throw new RuntimeException("ID inválido para eliminar.");

    // No permitir borrar el superadmin por accidente
    try {
      $st = $pdo->prepare("SELECT dni FROM personal_unidad WHERE id=:id AND unidad_id=:uid LIMIT 1");
      $st->execute([':id'=>$id, ':uid'=>$unidadActiva]);
      $dniRow = norm_dni((string)($st->fetchColumn() ?: ''));
      if ($dniRow === SUPERADMIN_DNI) throw new RuntimeException("No se puede eliminar el SUPERADMIN.");
    } catch (RuntimeException $re) {
      throw $re;
    } catch (Throwable $e) {}

    try {
      $st = $pdo->prepare("SELECT path FROM personal_documentos WHERE unidad_id=:uid AND personal_id=:pid AND path IS NOT NULL");
      $st->execute([':uid'=>$unidadActiva, ':pid'=>$id]);
      $paths = $st->fetchAll(PDO::FETCH_COLUMN) ?: [];
      foreach ($paths as $p) {
        $p = (string)$p;
        if ($p === '') continue;
        if (strpos($p, 'storage/') !== 0) continue;
        $abs = $ROOT_FS . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $p);
        if (is_file($abs)) @unlink($abs);
      }
    } catch (Throwable $e) {}

    $pdo->beginTransaction();

    $pdo->prepare("DELETE FROM personal_documentos WHERE unidad_id=:uid AND personal_id=:pid")
        ->execute([':uid'=>$unidadActiva, ':pid'=>$id]);
    $pdo->prepare("DELETE FROM sanidad_partes_enfermo WHERE unidad_id=:uid AND personal_id=:pid")
        ->execute([':uid'=>$unidadActiva, ':pid'=>$id]);

    $pdo->prepare("DELETE FROM personal_unidad WHERE id=:id AND unidad_id=:uid LIMIT 1")
        ->execute([':id'=>$id, ':uid'=>$unidadActiva]);

    $pdo->commit();
    $mensajeOk = "Registro eliminado correctamente.";
  }

  /* ---------- BORRAR LISTA COMPLETA (unidad activa) ---------- */
  if ($accion === 'borrar_todo') {
    if (!$esAdmin) throw new RuntimeException("Acceso restringido. Solo ADMIN/SUPERADMIN puede eliminar.");

    // ⚠️ IMPORTANTE: no borra al superadmin (DNI fijo)
    try {
      $st = $pdo->prepare("
        SELECT path
        FROM personal_documentos
        WHERE unidad_id=:uid AND path IS NOT NULL
          AND personal_id IN (
            SELECT id FROM personal_unidad
            WHERE unidad_id=:uid2
              AND REPLACE(REPLACE(REPLACE(dni,'.',''),'-',''),' ','') <> :sdni
          )
      ");
      $st->execute([':uid'=>$unidadActiva, ':uid2'=>$unidadActiva, ':sdni'=>SUPERADMIN_DNI]);
      $paths = $st->fetchAll(PDO::FETCH_COLUMN) ?: [];
      foreach ($paths as $p) {
        $p = (string)$p;
        if ($p === '') continue;
        if (strpos($p, 'storage/') !== 0) continue;
        $abs = $ROOT_FS . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $p);
        if (is_file($abs)) @unlink($abs);
      }
    } catch (Throwable $e) {}

    $pdo->beginTransaction();

    $pdo->prepare("
      DELETE FROM sanidad_partes_enfermo
      WHERE unidad_id=:uid
        AND personal_id IN (
          SELECT id FROM personal_unidad
          WHERE unidad_id=:uid2
            AND REPLACE(REPLACE(REPLACE(dni,'.',''),'-',''),' ','') <> :sdni
        )
    ")->execute([':uid'=>$unidadActiva, ':uid2'=>$unidadActiva, ':sdni'=>SUPERADMIN_DNI]);

    $pdo->prepare("
      DELETE FROM personal_documentos
      WHERE unidad_id=:uid
        AND personal_id IN (
          SELECT id FROM personal_unidad
          WHERE unidad_id=:uid2
            AND REPLACE(REPLACE(REPLACE(dni,'.',''),'-',''),' ','') <> :sdni
        )
    ")->execute([':uid'=>$unidadActiva, ':uid2'=>$unidadActiva, ':sdni'=>SUPERADMIN_DNI]);

    $pdo->prepare("
      DELETE FROM personal_unidad
      WHERE unidad_id=:uid
        AND REPLACE(REPLACE(REPLACE(dni,'.',''),'-',''),' ','') <> :sdni
    ")->execute([':uid'=>$unidadActiva, ':sdni'=>SUPERADMIN_DNI]);

    $pdo->commit();
    $mensajeOk = "Se eliminó la lista completa de personal (unidad_id={$unidadActiva}) sin borrar el SUPERADMIN.";
  }

  /* ---------- EXPORTAR EXCEL (A–Z) (con separadores por jerarquía) ---------- */
  if ($accion === 'exportar_excel') {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Personal');

    $headers = [
      'A1'=>'GRADO','B1'=>'ARMA/ESPEC','C1'=>'APELLIDO Y NOMBRE','D1'=>'DNI','E1'=>'CUIL','F1'=>'FECHA NAC',
      'G1'=>'PESO','H1'=>'ALTURA','I1'=>'SEXO','J1'=>'DOMICILIO','K1'=>'ESTADO CIVIL','L1'=>'HIJOS','M1'=>'NOU',
      'N1'=>'NRO CTA BANCO','O1'=>'CBU BANCO','P1'=>'ALIAS BANCO','Q1'=>'FECHA ULTIMO ANEXO 27','R1'=>'TIENE PARTE DE ENFERMO',
      'S1'=>'DESDE','T1'=>'HASTA','U1'=>'CANTIDAD DE PARTE DE ENFERMO','V1'=>'DESTINO INTERNO',
      'W1'=>'ROL','X1'=>'AÑOS EN DESTINO','Y1'=>'FRACC','Z1'=>'OBSERVACIONES'
    ];
    foreach ($headers as $cell=>$val) $sheet->setCellValue($cell, $val);

    // fallback por grado si no hay jerarquia persistida (devuelve ENUM)
    $ofList = "'TG','GD','GB','CY','CR','TC','MY','CT','TP','TT','ST'";
    $subList = "'SM','SP','SA','SI','SG','CI','CI EC','CI Art 11','CB','CB EC','CB Art 11'";
    $jerCaseFallback = "
      CASE
        WHEN UPPER(TRIM(COALESCE(pu.grado,''))) LIKE 'AG%' THEN 'AGENTE_CIVIL'
        WHEN UPPER(TRIM(COALESCE(pu.grado,''))) LIKE '%CIVIL%' THEN 'AGENTE_CIVIL'
        WHEN UPPER(TRIM(COALESCE(pu.grado,''))) IN ({$ofList}) THEN 'OFICIAL'
        WHEN UPPER(TRIM(COALESCE(pu.grado,''))) IN ({$subList}) THEN 'SUBOFICIAL'
        ELSE 'SOLDADO'
      END
    ";

    $jerExpr = $hasJerarquiaCol
      ? "CASE
           WHEN pu.jerarquia IN ('OFICIAL','SUBOFICIAL','SOLDADO','AGENTE_CIVIL') THEN pu.jerarquia
           ELSE {$jerCaseFallback}
         END"
      : $jerCaseFallback;

    // ✅ Orden custom de grados (normalizado)
    $gradoNorm = "UPPER(TRIM(REPLACE(REPLACE(COALESCE(pu.grado,''), '\"',''), '“','')))";

    $st = $pdo->prepare("
      SELECT pu.*, {$jerExpr} AS jerarquia_calc
      FROM personal_unidad pu
      WHERE pu.unidad_id = :uid
      ORDER BY
        FIELD({$jerExpr}, 'OFICIAL','SUBOFICIAL','SOLDADO','AGENTE_CIVIL'),
        FIELD({$gradoNorm},
          'TG','GD','GB','CY','CR','TC','MY','CT','TP','TT','ST',
          'SM','SP','SA','SI','SG','CI','CI Art 11','CB','CB EC','CB Art 11', 
          'VP','VS','VS EC','VSEC','A/C','A C','AC'
        ),
        pu.apellido_nombre ASC, pu.id ASC
    ");
    $st->execute([':uid'=>$unidadActiva]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $r = 2;
    $current = '';
    foreach ($rows as $x) {
      $jerEnum = (string)($x['jerarquia_calc'] ?? '');
      if ($jerEnum === '') $jerEnum = jerarquia_from_grado((string)($x['grado'] ?? ''));

      if ($jerEnum !== $current) {
        $current = $jerEnum;
        // separador en A: plural como esperás en el Excel
        $sheet->setCellValue('A'.$r, jer_label($current));
        $sheet->getStyle("A{$r}:Z{$r}")->getFont()->setBold(true);
        $r++;
      }

      $fn  = !empty($x['fecha_nac']) ? date('d/m/Y', strtotime((string)$x['fecha_nac'])) : '';
      $anx = !empty($x['fecha_ultimo_anexo27']) ? date('d/m/Y', strtotime((string)$x['fecha_ultimo_anexo27'])) : '';
      $pd  = !empty($x['parte_enfermo_desde']) ? date('d/m/Y', strtotime((string)$x['parte_enfermo_desde'])) : '';
      $ph  = !empty($x['parte_enfermo_hasta']) ? date('d/m/Y', strtotime((string)$x['parte_enfermo_hasta'])) : '';
      $tp  = ((int)($x['tiene_parte_enfermo'] ?? 0) === 1) ? 'SI' : 'NO';

      $sheet->setCellValue('A'.$r, $x['grado'] ?? '');
      $sheet->setCellValue('B'.$r, $x['arma'] ?? '');
      $sheet->setCellValue('C'.$r, $x['apellido_nombre'] ?? '');
      $sheet->setCellValue('D'.$r, $x['dni'] ?? '');
      $sheet->setCellValue('E'.$r, $x['cuil'] ?? '');
      $sheet->setCellValue('F'.$r, $fn);
      $sheet->setCellValue('G'.$r, $x['peso'] ?? '');
      $sheet->setCellValue('H'.$r, $x['altura'] ?? '');
      $sheet->setCellValue('I'.$r, $x['sexo'] ?? '');
      $sheet->setCellValue('J'.$r, $x['domicilio'] ?? '');
      $sheet->setCellValue('K'.$r, $x['estado_civil'] ?? '');
      $sheet->setCellValue('L'.$r, $x['hijos'] ?? '');
      $sheet->setCellValue('M'.$r, $x['nou'] ?? '');
      $sheet->setCellValue('N'.$r, $x['nro_cta'] ?? '');
      $sheet->setCellValue('O'.$r, $x['cbu'] ?? '');
      $sheet->setCellValue('P'.$r, $x['alias_banco'] ?? '');
      $sheet->setCellValue('Q'.$r, $anx);
      $sheet->setCellValue('R'.$r, $tp);
      $sheet->setCellValue('S'.$r, $pd);
      $sheet->setCellValue('T'.$r, $ph);
      $sheet->setCellValue('U'.$r, $x['cantidad_parte_enfermo'] ?? '');
      $sheet->setCellValue('V'.$r, $x['destino_interno'] ?? '');
      $sheet->setCellValue('W'.$r, $x['rol'] ?? '');
      $sheet->setCellValue('X'.$r, $x['anios_en_destino'] ?? '');
      $sheet->setCellValue('Y'.$r, $x['fracc'] ?? '');
      $sheet->setCellValue('Z'.$r, $x['observaciones'] ?? '');

      $r++;
    }

    try { (new Xlsx($spreadsheet))->save($MASTER_XLSX); } catch (Throwable $e) {}

    $filename = 'personal_unidad_' . date('Ymd_His') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="'.$filename.'"');
    header('Cache-Control: max-age=0');
    (new Xlsx($spreadsheet))->save('php://output');
    exit;
  }

  /* ---------- EXPORTAR PDF ---------- */
  if ($accion === 'exportar_pdf') {
    $st = $pdo->prepare("
      SELECT
        grado, arma, apellido_nombre, dni, cuil, fecha_nac, peso, altura, sexo, domicilio, estado_civil, hijos,
        nou, nro_cta, cbu, alias_banco, fecha_ultimo_anexo27, tiene_parte_enfermo, parte_enfermo_desde, parte_enfermo_hasta,
        cantidad_parte_enfermo, destino_interno, rol, anios_en_destino, fracc, observaciones
      FROM personal_unidad
      WHERE unidad_id = :uid
      ORDER BY id ASC
    ");
    $st->execute([':uid'=>$unidadActiva]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $html = '<html><head><meta charset="utf-8"><style>
      body{ font-family: DejaVu Sans, Arial, sans-serif; font-size: 9px; }
      h2{ margin:0 0 6px 0; }
      .sub{ margin:0 0 10px 0; color:#555; }
      table{ border-collapse:collapse; width:100%; }
      th,td{ border:1px solid #999; padding:3px; vertical-align:top; }
      th{ background:#eee; }
    </style></head><body>';

    $html .= '<h2>Listado de Personal - '.e($NOMBRE).'</h2>';
    $html .= '<div class="sub">Unidad ID: '.(int)$unidadActiva.' - Generado: '.date('d/m/Y H:i').'</div>';

    $html .= '<table><thead><tr>
      <th>N°</th>
      <th>Grado</th><th>Arma/Espec</th><th>Apellido y Nombre</th><th>DNI</th><th>CUIL</th><th>F. Nac</th>
      <th>Peso</th><th>Altura</th><th>Sexo</th><th>Domicilio</th><th>Estado civil</th><th>Hijos</th><th>NOU</th>
      <th>Nro Cta</th><th>CBU</th><th>Alias</th><th>Últ. Anexo 27</th><th>Parte</th><th>Desde</th><th>Hasta</th><th>Cant</th>
      <th>Destino Interno</th><th>Rol</th><th>Años</th><th>Fracción</th><th>Obs</th>
    </tr></thead><tbody>';

    $i = 1;
    foreach ($rows as $r) {
      $fn  = !empty($r['fecha_nac']) ? date('d/m/Y', strtotime((string)$r['fecha_nac'])) : '';
      $anx = !empty($r['fecha_ultimo_anexo27']) ? date('d/m/Y', strtotime((string)$r['fecha_ultimo_anexo27'])) : '';
      $pd  = !empty($r['parte_enfermo_desde']) ? date('d/m/Y', strtotime((string)$r['parte_enfermo_desde'])) : '';
      $ph  = !empty($r['parte_enfermo_hasta']) ? date('d/m/Y', strtotime((string)$r['parte_enfermo_hasta'])) : '';
      $tp  = ((int)($r['tiene_parte_enfermo'] ?? 0) === 1) ? 'SI' : 'NO';

      $html .= '<tr>'.
        '<td>'.e($i).'</td>'.
        '<td>'.e($r['grado'] ?? '').'</td>'.
        '<td>'.e($r['arma'] ?? '').'</td>'.
        '<td>'.e($r['apellido_nombre'] ?? '').'</td>'.
        '<td>'.e($r['dni'] ?? '').'</td>'.
        '<td>'.e($r['cuil'] ?? '').'</td>'.
        '<td>'.e($fn).'</td>'.
        '<td>'.e($r['peso'] ?? '').'</td>'.
        '<td>'.e($r['altura'] ?? '').'</td>'.
        '<td>'.e($r['sexo'] ?? '').'</td>'.
        '<td>'.e($r['domicilio'] ?? '').'</td>'.
        '<td>'.e($r['estado_civil'] ?? '').'</td>'.
        '<td>'.e($r['hijos'] ?? '').'</td>'.
        '<td>'.e($r['nou'] ?? '').'</td>'.
        '<td>'.e($r['nro_cta'] ?? '').'</td>'.
        '<td>'.e($r['cbu'] ?? '').'</td>'.
        '<td>'.e($r['alias_banco'] ?? '').'</td>'.
        '<td>'.e($anx).'</td>'.
        '<td>'.e($tp).'</td>'.
        '<td>'.e($pd).'</td>'.
        '<td>'.e($ph).'</td>'.
        '<td>'.e($r['cantidad_parte_enfermo'] ?? '').'</td>'.
        '<td>'.e($r['destino_interno'] ?? '').'</td>'.
        '<td>'.e($r['rol'] ?? '').'</td>'.
        '<td>'.e($r['anios_en_destino'] ?? '').'</td>'.
        '<td>'.e($r['fracc'] ?? '').'</td>'.
        '<td>'.e($r['observaciones'] ?? '').'</td>'.
      '</tr>';
      $i++;
    }

    $html .= '</tbody></table></body></html>';
    $filename = 'personal_unidad_' . date('Ymd_His') . '.pdf';

    if (class_exists(Dompdf::class)) {
      $opt = new Options();
      $opt->set('isRemoteEnabled', true);
      $dompdf = new Dompdf($opt);
      $dompdf->loadHtml($html, 'UTF-8');
      $dompdf->setPaper('A4', 'landscape');
      $dompdf->render();

      header('Content-Type: application/pdf');
      header('Content-Disposition: attachment; filename="'.$filename.'"');
      echo $dompdf->output();
      exit;
    }

    if (class_exists(Mpdf::class)) {
      $mpdf = new Mpdf(['format'=>'A4-L']);
      $mpdf->WriteHTML($html);
      header('Content-Type: application/pdf');
      header('Content-Disposition: attachment; filename="'.$filename.'"');
      echo $mpdf->Output($filename, 'S');
      exit;
    }

    throw new RuntimeException("Exportar PDF: no hay librería instalada. Instalá dompdf/dompdf o mpdf/mpdf (composer).");
  }

} catch (Throwable $ex) {
  if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
  $mensajeError = $ex->getMessage();
}

/* ==========================================================
   FILTROS GET + LISTADO (incluye jerarquía) + ORDEN CUSTOM
   ========================================================== */
$q = trim((string)($_GET['q'] ?? ''));
$destId = (int)($_GET['destino_id'] ?? 0);
$jer = normalize_jerarquia_label((string)($_GET['jer'] ?? ''));

$filas = [];
try {
  // fallback por grado si no hay jerarquia guardada (ENUM)
  $ofList = "'TG','GD','GB','CY','CR','TC','MY','CT','TP','TT','ST'";
  $subList = "'SM','SP','SA','SI','SG','CI','CI ART 11','CB','CB EC','CB ART 11'";

  $jerCaseFallback = "
    CASE
      WHEN UPPER(TRIM(COALESCE(pu.grado,''))) LIKE 'AG%' THEN 'AGENTE_CIVIL'
      WHEN UPPER(TRIM(COALESCE(pu.grado,''))) LIKE '%CIVIL%' THEN 'AGENTE_CIVIL'
      WHEN UPPER(TRIM(COALESCE(pu.grado,''))) IN ({$ofList}) THEN 'OFICIAL'
      WHEN UPPER(TRIM(COALESCE(pu.grado,''))) IN ({$subList}) THEN 'SUBOFICIAL'
      ELSE 'SOLDADO'
    END
  ";

  $jerExpr = $hasJerarquiaCol
    ? "CASE
         WHEN pu.jerarquia IN ('OFICIAL','SUBOFICIAL','SOLDADO','AGENTE_CIVIL') THEN pu.jerarquia
         ELSE {$jerCaseFallback}
       END"
    : $jerCaseFallback;

  // ✅ Orden custom de grados (normalizado)
  $gradoNorm = "UPPER(TRIM(REPLACE(REPLACE(COALESCE(pu.grado,''), '\"',''), '“','')))";

  $sql = "
    SELECT
      pu.*,
      d.codigo AS destino_codigo,
      d.nombre AS destino_nombre,
      {$jerExpr} AS jerarquia_calc
    FROM personal_unidad pu
    LEFT JOIN destino d ON d.id = pu.destino_id
    WHERE pu.unidad_id = :uid
  ";
  $params = [':uid'=>$unidadActiva];

  if ($q !== '') {
    $sql .= " AND (
      pu.apellido_nombre LIKE :q OR
      pu.dni LIKE :q OR
      pu.cuil LIKE :q OR
      pu.destino_interno LIKE :q OR
      pu.grado LIKE :q OR
      pu.arma LIKE :q
    )";
    $params[':q'] = '%' . $q . '%';
  }
  if ($destId > 0) {
    $sql .= " AND pu.destino_id = :did";
    $params[':did'] = $destId;
  }
  if ($jer !== '') {
    $sql .= " AND ({$jerExpr}) = :jer";
    $params[':jer'] = $jer;
  }

  // ✅ ORDEN FINAL
  $sql .= "
    ORDER BY
      FIELD({$jerExpr}, 'OFICIAL','SUBOFICIAL','SOLDADO','AGENTE_CIVIL'),
      FIELD({$gradoNorm},
        'TG','GD','GB','CY','CR','TC','MY','CT','TP','TT','ST',
        'SM','SP','SA','SI','SG','CI','CI EC','CI ART 11','CB','CB EC','CB ART 11',
        'VP','VS','VS EC','VSEC','A/C','A C','AC'
      ),
      pu.apellido_nombre ASC,
      pu.id ASC
  ";

  $st = $pdo->prepare($sql);
  $st->execute($params);
  $filas = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
  $listadoError = $e->getMessage();
  $filas = [];
}

/* ==========================================================
   JSON de filas para editar
   ========================================================== */
function row_json_attr(array $row): string {
  $json = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  if (!is_string($json)) $json = '{}';
  return htmlspecialchars($json, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Personal · Lista</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= e($ASSETS_WEB) ?>/css/theme-602.css">
<link rel="icon" type="image/png" href="<?= e($FAVICON) ?>">

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
  html, body{ height:100%; }
  body{
    background: url("<?= e($IMG_BG) ?>") no-repeat center center fixed;
    background-size: cover;
    background-attachment: fixed;
    background-color:#020617;
    color:#e5e7eb;
    font-family:system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,sans-serif;
    margin:0; padding:0;
    overflow:auto;
  }

  .page-wrap{ padding:18px; overflow:auto; }
  .container-main{ max-width: 1600px; margin:auto; }

  .panel{
    background:rgba(15,17,23,.94);
    border:1px solid rgba(148,163,184,.40);
    border-radius:18px;
    padding:18px 22px 22px;
    box-shadow:0 18px 40px rgba(0,0,0,.75), inset 0 1px 0 rgba(255,255,255,.05);
  }

  .brand-hero{ padding-top:10px; padding-bottom:10px; }
  .brand-hero .hero-inner{ align-items:center; display:flex; justify-content:space-between; gap:12px; }
  .header-back{
    margin-left:auto;
    margin-right:17px;
    margin-top:4px;
    display:flex;
    gap:8px;
  }

  .search-panel{
    background:rgba(15,23,42,.95);
    border-radius:12px;
    border:1px solid rgba(148,163,184,.35);
    padding:10px 12px;
    margin-bottom:10px;
  }
  .search-panel label{ font-size:.8rem; margin-bottom:2px; color:#bfdbfe; }
  .help-small{ font-size:.75rem; color:#b7c3d6; }

  .table-wrap{
    max-height: calc(100vh - 370px);
    overflow:auto;
    border-radius:14px;
    border:1px solid rgba(148,163,184,.25);
    background: rgba(2,6,23,.25);
  }

  .table-dark-custom {
    --bs-table-bg: rgba(15,23,42,.9);
    --bs-table-striped-bg: rgba(30,64,175,.25);
    --bs-table-border-color: rgba(148,163,184,.4);
    color:#e5e7eb;
    font-size:.78rem;
    margin:0;
    min-width: 1100px;
  }
  .table-dark-custom th, .table-dark-custom td{
    padding:.30rem .45rem;
    white-space:nowrap;
  }

  .table-wrap thead th{
    position: sticky;
    top: 0;
    z-index: 2;
    background: rgba(15,23,42,.98);
  }

  table.table-mode-compact .col-order,
  table.table-mode-compact .col-extra { display:none; }

  table.table-mode-full .col-order,
  table.table-mode-full .col-extra { display:table-cell; }

  .modal-content{
    background:rgba(15,23,42,.98);
    color:#e5e7eb;
    border-radius:16px;
    border:1px solid rgba(148,163,184,.6);
  }
  .modal-header{ border-bottom:1px solid rgba(55,65,81,.9); }
  .modal-footer{ border-top:1px solid rgba(55,65,81,.9); }
  .modal .form-label{ font-size:.80rem; color:#bfdbfe; }
  .table-dark-custom thead th{ color:#fff !important; }

  .pill-jer{
    display:inline-flex; align-items:center; gap:.35rem;
    padding:.12rem .45rem; border-radius:999px;
    border:1px solid rgba(148,163,184,.45);
    background:rgba(15,23,42,.55);
    font-weight:800; font-size:.72rem;
  }

  /* ✅ FIX: scroll en modal largo */
  .modal-dialog.modal-dialog-scrollable .modal-content{
    max-height: calc(100vh - 60px);
  }
  .modal-dialog.modal-dialog-scrollable .modal-body{
    overflow: auto !important;
    max-height: calc(100vh - 190px);
    padding-bottom: 18px;
  }
  @media (max-width: 576px){
    .modal-dialog.modal-dialog-scrollable .modal-content{ max-height: calc(100vh - 20px); }
    .modal-dialog.modal-dialog-scrollable .modal-body{ max-height: calc(100vh - 160px); }
  }
</style>
</head>

<body>
<header class="brand-hero">
  <div class="hero-inner container-main">
    <div class="d-flex align-items-center gap-3">
      <img src="<?= e($ESCUDO) ?>" alt="Escudo" style="height:52px;width:auto;"
           onerror="this.onerror=null;this.style.display='none';">
      <div>
        <div style="font-weight:900; font-size:1.05rem;"><?= e($NOMBRE) ?></div>
        <div style="opacity:.9; color:#cbd5f5; font-size:.85rem;"><?= e($LEYENDA) ?></div>
        <div style="opacity:.85; font-size:.8rem;">
          Usuario: <b><?= e($fullNameDB !== '' ? $fullNameDB : ($user['display_name'] ?? '')) ?></b> ·
          Rol: <b><?= e($roleCodigo) ?></b> · Unidad ID: <b><?= (int)$unidadActiva ?></b>
        </div>
      </div>
    </div>

    <div class="header-back">
      <a href="personal.php" class="btn btn-success btn-sm" style="font-weight:700; padding:.35rem .9rem;">Volver</a>
      <a href="../inicio.php" class="btn btn-success btn-sm" style="font-weight:700; padding:.35rem .9rem;">Inicio</a>
    </div>
  </div>
</header>

<div class="page-wrap">
  <div class="container-main">
    <div class="panel">

      <?php if (!empty($missingCols)): ?>
        <div class="alert alert-danger">
          <b>Faltan columnas en <code>personal_unidad</code></b> (no puedo garantizar el CRUD hasta que estén):
          <div class="mt-2"><code><?= e(implode(', ', $missingCols)) ?></code></div>
        </div>
      <?php endif; ?>

      <?php if ($mensajeOk !== ''): ?>
        <div class="alert alert-success py-2"><?= e($mensajeOk) ?></div>
      <?php endif; ?>
      <?php if ($mensajeError !== ''): ?>
        <div class="alert alert-danger py-2" style="white-space:pre-wrap;"><?= e($mensajeError) ?></div>
      <?php endif; ?>
      <?php if ($listadoError !== ''): ?>
        <div class="alert alert-danger py-2">Error listado: <code><?= e($listadoError) ?></code></div>
      <?php endif; ?>

      <div class="search-panel mb-3">
        <form method="get" class="row g-2 align-items-end">
          <div class="col-md-5 col-lg-6">
            <label class="form-label">Buscar (Apellido y Nombre / DNI / CUIL / Destino / Grado / Arma)</label>
            <input class="form-control form-control-sm" name="q" value="<?= e($q) ?>"
                   placeholder="Ej: ROJAS / DNI / 24... / Informática">
          </div>

          <div class="col-md-3 col-lg-2">
            <label class="form-label">Jerarquía</label>
            <select class="form-select form-select-sm" name="jer">
              <option value="">Todas</option>
              <?php foreach (JER_OPTS as $val => $label): ?>
                <option value="<?= e($val) ?>" <?= ($jer===$val?'selected':'') ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-3 col-lg-2">
            <label class="form-label">Destino (por tabla destino)</label>
            <select class="form-select form-select-sm" name="destino_id">
              <option value="0">Todos</option>
              <?php foreach ($destinosCombo as $d): ?>
                <?php $idOpt=(int)$d['id']; $lbl=trim(($d['codigo']? $d['codigo'].' - ':'').$d['nombre']); ?>
                <option value="<?= $idOpt ?>" <?= $destId===$idOpt?'selected':'' ?>><?= e($lbl) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-1 col-lg-2 d-flex gap-1">
            <button class="btn btn-success btn-sm w-100" style="font-weight:800;">Filtrar</button>
            <a class="btn btn-outline-light btn-sm" href="personal_lista.php">Limpiar</a>
          </div>
        </form>
      </div>

      <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
        <div>
          <div style="font-weight:900; font-size:1.05rem;">Personal · Lista</div>
          <div class="help-small">
            Registros: <b><?= (int)count($filas) ?></b>
            <?= $jer!=='' ? ' · Jerarquía: <b>'.e(jer_label($jer)).'</b>' : '' ?>
            <?= $destId>0 ? ' · Destino ID: <b>'.(int)$destId.'</b>' : '' ?>
            <?= $q!=='' ? ' · Búsqueda: <b>"'.e($q).'"</b>' : '' ?>
          </div>
        </div>

        <?php if ($esAdmin || user_can_import((array)$user, $dniNorm, $esAdmin, $esSuperAdmin)): ?>
        <div class="d-flex flex-column flex-sm-row gap-2">
          <?php if ($esAdmin): ?>
          <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#modalNuevo"
                  style="font-weight:700; padding:.35rem .9rem;">
            + Nuevo personal
          </button>
          <?php endif; ?>

          <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#modalExcel"
                  style="font-weight:700; padding:.35rem .9rem;">
            Importar / Actualizar desde Excel
          </button>

          <form method="post">
            <?php csrf_if_exists(); ?>
            <input type="hidden" name="accion" value="exportar_excel">
            <button type="submit" class="btn btn-sm btn-outline-warning" style="font-weight:700; padding:.35rem .9rem;">
              Exportar Excel
            </button>
          </form>

          <form method="post">
            <?php csrf_if_exists(); ?>
            <input type="hidden" name="accion" value="exportar_pdf">
            <button type="submit" class="btn btn-sm btn-outline-light" style="font-weight:700; padding:.35rem .9rem;">
              Exportar PDF
            </button>
          </form>

          <?php if ($esAdmin): ?>
          <form method="post" class="form-delete-all">
            <?php csrf_if_exists(); ?>
            <input type="hidden" name="accion" value="borrar_todo">
            <button type="submit" class="btn btn-sm btn-outline-danger" style="font-weight:700; padding:.35rem .9rem;">
              Eliminar lista completa
            </button>
          </form>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>

      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" role="switch" id="toggleFull">
          <label class="form-check-label" for="toggleFull" style="font-weight:700;">
            Ver 27 datos (N° + A–Z)
          </label>
        </div>
      </div>

      <div class="table-wrap">
        <table id="tablaPersonal" class="table table-sm table-dark table-striped table-dark-custom align-middle table-mode-compact">
          <thead>
            <tr>
              <th class="col-order">N°</th>

              <th class="col-main">Jerarquía</th>
              <th class="col-main">Grado</th>
              <th class="col-main">Arma/Espec</th>
              <th class="col-main">Apellido y Nombre</th>
              <th class="col-main">DNI</th>
              <th class="col-main">Destino Interno</th>

              <th class="col-extra">CUIL</th>
              <th class="col-extra">F. Nac</th>
              <th class="col-extra">Peso</th>
              <th class="col-extra">Altura</th>
              <th class="col-extra">Sexo</th>
              <th class="col-extra">Domicilio</th>
              <th class="col-extra">Estado civil</th>
              <th class="col-extra">Hijos</th>
              <th class="col-extra">NOU</th>
              <th class="col-extra">Nro Cta</th>
              <th class="col-extra">CBU</th>
              <th class="col-extra">Alias</th>
              <th class="col-extra">Últ. Anexo 27</th>
              <th class="col-extra">Parte</th>
              <th class="col-extra">Desde</th>
              <th class="col-extra">Hasta</th>
              <th class="col-extra">Cant</th>
              <th class="col-extra">Rol</th>
              <th class="col-extra">Años</th>
              <th class="col-extra">Fracción</th>
              <th class="col-extra">Obs</th>

              <th class="text-end col-main">Acciones</th>
            </tr>
          </thead>

          <tbody>
          <?php if (!$filas): ?>
            <tr>
              <td colspan="29" class="text-center text-muted py-4">No hay registros.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($filas as $i=>$r): ?>
              <?php
                $nro = $i + 1;
                $fn  = !empty($r['fecha_nac']) ? date('d/m/Y', strtotime((string)$r['fecha_nac'])) : '';
                $anx = !empty($r['fecha_ultimo_anexo27']) ? date('d/m/Y', strtotime((string)$r['fecha_ultimo_anexo27'])) : '';
                $pd  = !empty($r['parte_enfermo_desde']) ? date('d/m/Y', strtotime((string)$r['parte_enfermo_desde'])) : '';
                $ph  = !empty($r['parte_enfermo_hasta']) ? date('d/m/Y', strtotime((string)$r['parte_enfermo_hasta'])) : '';
                $tp  = ((int)($r['tiene_parte_enfermo'] ?? 0) === 1) ? 'SI' : 'NO';
                $destInterno = (string)($r['destino_interno'] ?? '');
                $rowJson = row_json_attr($r);
                $jerEnum = (string)($r['jerarquia_calc'] ?? '');
                if ($jerEnum === '') $jerEnum = jerarquia_from_grado((string)($r['grado'] ?? ''));
              ?>
              <tr>
                <td class="col-order"><?= e($nro) ?></td>

                <td class="col-main"><span class="pill-jer"><?= e(jer_label($jerEnum)) ?></span></td>
                <td class="col-main"><?= e($r['grado'] ?? '') ?></td>
                <td class="col-main"><?= e($r['arma'] ?? '') ?></td>
                <td class="col-main"><?= e($r['apellido_nombre'] ?? '') ?></td>
                <td class="col-main"><?= e($r['dni'] ?? '') ?></td>
                <td class="col-main"><?= e($destInterno) ?></td>

                <td class="col-extra"><?= e($r['cuil'] ?? '') ?></td>
                <td class="col-extra"><?= e($fn) ?></td>
                <td class="col-extra"><?= e($r['peso'] ?? '') ?></td>
                <td class="col-extra"><?= e($r['altura'] ?? '') ?></td>
                <td class="col-extra"><?= e($r['sexo'] ?? '') ?></td>
                <td class="col-extra"><?= e($r['domicilio'] ?? '') ?></td>
                <td class="col-extra"><?= e($r['estado_civil'] ?? '') ?></td>
                <td class="col-extra"><?= e($r['hijos'] ?? '') ?></td>
                <td class="col-extra"><?= e($r['nou'] ?? '') ?></td>
                <td class="col-extra"><?= e($r['nro_cta'] ?? '') ?></td>
                <td class="col-extra"><?= e($r['cbu'] ?? '') ?></td>
                <td class="col-extra"><?= e($r['alias_banco'] ?? '') ?></td>
                <td class="col-extra"><?= e($anx) ?></td>
                <td class="col-extra"><?= e($tp) ?></td>
                <td class="col-extra"><?= e($pd) ?></td>
                <td class="col-extra"><?= e($ph) ?></td>
                <td class="col-extra"><?= e($r['cantidad_parte_enfermo'] ?? '') ?></td>
                <td class="col-extra"><?= e($r['rol'] ?? '') ?></td>
                <td class="col-extra"><?= e($r['anios_en_destino'] ?? '') ?></td>
                <td class="col-extra"><?= e($r['fracc'] ?? '') ?></td>
                <td class="col-extra"><?= e($r['observaciones'] ?? '') ?></td>

                <td class="text-end col-main">
                  <div class="d-inline-flex gap-1">
                    <a class="btn btn-sm btn-outline-info"
                       href="<?= e($FICHA_URL) ?>?id=<?= e($r['id']) ?>">
                      Ficha
                    </a>

                    <?php if ($esAdmin): ?>
                      <button type="button"
                        class="btn btn-sm btn-outline-light btn-edit"
                        data-bs-toggle="modal"
                        data-bs-target="#modalEditar"
                        data-row="<?= $rowJson ?>">
                        Editar
                      </button>

                      <form method="post" class="d-inline form-delete-one">
                        <?php csrf_if_exists(); ?>
                        <input type="hidden" name="accion" value="borrar_individual">
                        <input type="hidden" name="id" value="<?= e($r['id']) ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                      </form>
                    <?php else: ?>
                      <span class="text-muted">Sin permisos</span>
                    <?php endif; ?>
                  </div>
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

<?php if ($esAdmin): ?>
<!-- =========================
     MODAL: NUEVO PERSONAL (A–Z)
     ========================= -->
<div class="modal fade" id="modalNuevo" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <form method="post" enctype="multipart/form-data">
        <?php csrf_if_exists(); ?>
        <input type="hidden" name="accion" value="guardar_nuevo">

        <div class="modal-header">
          <h5 class="modal-title" style="font-weight:900;">Nuevo personal (A–Z)</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <div class="row g-2">

            <div class="col-md-3">
              <label class="form-label">Jerarquía *</label>
              <select class="form-select form-select-sm" name="jerarquia" required>
                <option value="">— Seleccionar —</option>
                <?php foreach (JER_OPTS as $val=>$label): ?>
                  <option value="<?= e($val) ?>"><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="help-small mt-1">
                Se guarda en <code>personal_unidad.jerarquia</code>
                <?= $hasJerarquiaCol ? '' : ' (⚠️ la columna no existe; usa fallback).' ?>
              </div>
            </div>

            <div class="col-md-2"><label class="form-label">A · Grado</label><input class="form-control form-control-sm" name="grado"></div>
            <div class="col-md-3"><label class="form-label">B · Arma / Especialidad</label><input class="form-control form-control-sm" name="arma"></div>
            <div class="col-md-4"><label class="form-label">C · Apellido y Nombre *</label><input class="form-control form-control-sm" name="apellido_nombre" required></div>

            <div class="col-md-2"><label class="form-label">D · DNI *</label><input class="form-control form-control-sm" name="dni" required></div>
            <div class="col-md-3"><label class="form-label">E · CUIL</label><input class="form-control form-control-sm" name="cuil"></div>
            <div class="col-md-3"><label class="form-label">F · Fecha de nacimiento</label><input type="date" class="form-control form-control-sm" name="fecha_nac"></div>
            <div class="col-md-2"><label class="form-label">G · Peso</label><input type="number" step="0.01" class="form-control form-control-sm" name="peso"></div>
            <div class="col-md-2"><label class="form-label">H · Altura</label><input type="number" step="0.01" class="form-control form-control-sm" name="altura"></div>
            <div class="col-md-2"><label class="form-label">I · Sexo</label><input class="form-control form-control-sm" name="sexo" placeholder="M/F"></div>

            <div class="col-md-6"><label class="form-label">J · Domicilio</label><input class="form-control form-control-sm" name="domicilio"></div>
            <div class="col-md-3"><label class="form-label">K · Estado civil</label><input class="form-control form-control-sm" name="estado_civil"></div>
            <div class="col-md-3"><label class="form-label">L · Hijos</label><input type="number" class="form-control form-control-sm" name="hijos"></div>

            <div class="col-md-3"><label class="form-label">M · NOU</label><input class="form-control form-control-sm" name="nou"></div>
            <div class="col-md-3"><label class="form-label">N · Nro Cta Banco</label><input class="form-control form-control-sm" name="nro_cta"></div>
            <div class="col-md-3"><label class="form-label">O · CBU Banco</label><input class="form-control form-control-sm" name="cbu"></div>
            <div class="col-md-3"><label class="form-label">P · Alias Banco</label><input class="form-control form-control-sm" name="alias_banco"></div>

            <div class="col-md-3"><label class="form-label">Q · Fecha último Anexo 27</label><input type="date" class="form-control form-control-sm" name="fecha_ultimo_anexo27"></div>

            <div class="col-md-3 d-flex align-items-center">
              <div class="form-check mt-4">
                <input class="form-check-input" type="checkbox" name="tiene_parte_enfermo" id="nuevoTieneParte">
                <label class="form-check-label" for="nuevoTieneParte">R · Tiene parte de enfermo</label>
              </div>
            </div>

            <div class="col-md-3"><label class="form-label">S · Parte enfermo desde</label><input type="date" class="form-control form-control-sm" name="parte_enfermo_desde"></div>
            <div class="col-md-3"><label class="form-label">T · Parte enfermo hasta</label><input type="date" class="form-control form-control-sm" name="parte_enfermo_hasta"></div>

            <div class="col-md-3">
              <label class="form-label">U · Cantidad de partes (automático)</label>
              <input type="number" class="form-control form-control-sm" value="0" disabled>
              <div class="help-small mt-1">Automático: cada vez que subís un PARTE (con evidencia), suma +1.</div>
            </div>

            <div class="col-md-6">
              <label class="form-label">📌 Parte(s) de enfermo (PDF/IMG/DOC/DOCX)</label>
              <input type="file" class="form-control form-control-sm"
                     name="parte_archivos[]" multiple
                     accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx">
              <div class="help-small mt-1">
                Opcional. Si subís al menos 1 archivo, la cantidad suma +1 y se guarda evidencia.
              </div>
            </div>

            <div class="col-md-6">
              <label class="form-label">✅ Alta(s) de parte (PDF/IMG/DOC/DOCX)</label>
              <input type="file" class="form-control form-control-sm"
                     name="alta_archivos[]" multiple
                     accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx">
              <div class="help-small mt-1">
                Opcional. Si subís alta, se marca SIN PARTE y se actualiza “Hasta”.
              </div>
            </div>

            <div class="col-md-3">
              <label class="form-label">Destino (relación / FK)</label>
              <select class="form-select form-select-sm" name="destino_id" id="nuevoDestinoId">
                <option value="0">— Sin asignar —</option>
                <?php foreach ($destinosCombo as $d): ?>
                  <?php $idOpt=(int)$d['id']; $lbl=trim(($d['codigo']? $d['codigo'].' - ':'').$d['nombre']); ?>
                  <option value="<?= $idOpt ?>"><?= e($lbl) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="help-small mt-1">Esto llena <code>destino_id</code>. El Excel trae V como texto.</div>
            </div>

            <div class="col-md-5"><label class="form-label">V · Destino interno (texto Excel)</label><input class="form-control form-control-sm" name="destino_interno" id="nuevoDestinoInterno"></div>
            <div class="col-md-2"><label class="form-label">W · Rol (Excel)</label><input class="form-control form-control-sm" name="rol"></div>
            <div class="col-md-2"><label class="form-label">X · Años en destino</label><input type="number" step="0.01" class="form-control form-control-sm" name="anios_en_destino"></div>
            <div class="col-md-2"><label class="form-label">Y · Fracción</label><input class="form-control form-control-sm" name="fracc"></div>

            <div class="col-md-10"><label class="form-label">Z · Observaciones</label><textarea class="form-control form-control-sm" rows="2" name="observaciones"></textarea></div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-light btn-sm" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-success btn-sm" style="font-weight:900;">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- =========================
     MODAL: EDITAR PERSONAL (A–Z)
     ========================= -->
<div class="modal fade" id="modalEditar" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <form method="post" enctype="multipart/form-data">
        <?php csrf_if_exists(); ?>
        <input type="hidden" name="accion" value="guardar_edicion">
        <input type="hidden" name="id" id="editId">

        <div class="modal-header">
          <h5 class="modal-title" style="font-weight:900;">Editar personal (A–Z)</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <div class="row g-2">

            <div class="col-md-3">
              <label class="form-label">Jerarquía *</label>
              <select class="form-select form-select-sm" name="jerarquia" id="editJerarquia" required>
                <option value="">— Seleccionar —</option>
                <?php foreach (JER_OPTS as $val=>$label): ?>
                  <option value="<?= e($val) ?>"><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="help-small mt-1">Se guarda en <code>personal_unidad.jerarquia</code>.</div>
            </div>

            <div class="col-md-2"><label class="form-label">A · Grado</label><input class="form-control form-control-sm" name="grado" id="editGrado"></div>
            <div class="col-md-3"><label class="form-label">B · Arma / Especialidad</label><input class="form-control form-control-sm" name="arma" id="editArma"></div>
            <div class="col-md-4"><label class="form-label">C · Apellido y Nombre *</label><input class="form-control form-control-sm" name="apellido_nombre" id="editApellidoNombre" required></div>

            <div class="col-md-2"><label class="form-label">D · DNI *</label><input class="form-control form-control-sm" name="dni" id="editDni" required></div>

            <div class="col-md-3"><label class="form-label">E · CUIL</label><input class="form-control form-control-sm" name="cuil" id="editCuil"></div>
            <div class="col-md-3"><label class="form-label">F · Fecha de nacimiento</label><input type="date" class="form-control form-control-sm" name="fecha_nac" id="editFechaNac"></div>
            <div class="col-md-2"><label class="form-label">G · Peso</label><input type="number" step="0.01" class="form-control form-control-sm" name="peso" id="editPeso"></div>
            <div class="col-md-2"><label class="form-label">H · Altura</label><input type="number" step="0.01" class="form-control form-control-sm" name="altura" id="editAltura"></div>
            <div class="col-md-2"><label class="form-label">I · Sexo</label><input class="form-control form-control-sm" name="sexo" id="editSexo"></div>

            <div class="col-md-6"><label class="form-label">J · Domicilio</label><input class="form-control form-control-sm" name="domicilio" id="editDomicilio"></div>
            <div class="col-md-3"><label class="form-label">K · Estado civil</label><input class="form-control form-control-sm" name="estado_civil" id="editEstadoCivil"></div>
            <div class="col-md-3"><label class="form-label">L · Hijos</label><input type="number" class="form-control form-control-sm" name="hijos" id="editHijos"></div>

            <div class="col-md-3"><label class="form-label">M · NOU</label><input class="form-control form-control-sm" name="nou" id="editNou"></div>
            <div class="col-md-3"><label class="form-label">N · Nro Cta Banco</label><input class="form-control form-control-sm" name="nro_cta" id="editNroCta"></div>
            <div class="col-md-3"><label class="form-label">O · CBU Banco</label><input class="form-control form-control-sm" name="cbu" id="editCbu"></div>
            <div class="col-md-3"><label class="form-label">P · Alias Banco</label><input class="form-control form-control-sm" name="alias_banco" id="editAliasBanco"></div>

            <div class="col-md-3"><label class="form-label">Q · Fecha último Anexo 27</label><input type="date" class="form-control form-control-sm" name="fecha_ultimo_anexo27" id="editFechaAnexo27"></div>

            <div class="col-md-3 d-flex align-items-center">
              <div class="form-check mt-4">
                <input class="form-check-input" type="checkbox" name="tiene_parte_enfermo" id="editTieneParte">
                <label class="form-check-label" for="editTieneParte">R · Tiene parte de enfermo</label>
              </div>
            </div>

            <div class="col-md-3"><label class="form-label">S · Parte enfermo desde</label><input type="date" class="form-control form-control-sm" name="parte_enfermo_desde" id="editParteDesde"></div>
            <div class="col-md-3"><label class="form-label">T · Parte enfermo hasta</label><input type="date" class="form-control form-control-sm" name="parte_enfermo_hasta" id="editParteHasta"></div>

            <div class="col-md-3">
              <label class="form-label">U · Cantidad de partes (automático)</label>
              <input type="number" class="form-control form-control-sm" id="editCantPartes" value="0" disabled>
              <div class="help-small mt-1">No se edita: cada submit con PARTE subido incrementa +1.</div>
            </div>

            <div class="col-md-6">
              <label class="form-label">📌 Agregar PARTE (PDF/IMG/DOC/DOCX)</label>
              <input type="file" class="form-control form-control-sm"
                     name="parte_archivos[]" multiple
                     accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx">
              <div class="help-small mt-1">
                Si subís al menos 1 archivo de PARTE, la cantidad sube +1 automáticamente.
              </div>
            </div>

            <div class="col-md-6">
              <label class="form-label">✅ Agregar ALTA (PDF/IMG/DOC/DOCX)</label>
              <input type="file" class="form-control form-control-sm"
                     name="alta_archivos[]" multiple
                     accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx">
              <div class="help-small mt-1">
                Si subís al menos 1 archivo de ALTA, se marca SIN PARTE y se actualiza “Hasta”.
              </div>
            </div>

            <div class="col-md-3">
              <label class="form-label">Destino (relación / FK)</label>
              <select class="form-select form-select-sm" name="destino_id" id="editDestinoId">
                <option value="0">— Sin asignar —</option>
                <?php foreach ($destinosCombo as $d): ?>
                  <?php $idOpt=(int)$d['id']; $lbl=trim(($d['codigo']? $d['codigo'].' - ':'').$d['nombre']); ?>
                  <option value="<?= $idOpt ?>"><?= e($lbl) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-5"><label class="form-label">V · Destino interno (texto Excel)</label><input class="form-control form-control-sm" name="destino_interno" id="editDestinoInterno"></div>
            <div class="col-md-2"><label class="form-label">W · Rol (Excel)</label><input class="form-control form-control-sm" name="rol" id="editRolExcel"></div>
            <div class="col-md-2"><label class="form-label">X · Años en destino</label><input type="number" step="0.01" class="form-control form-control-sm" name="anios_en_destino" id="editAniosDestino"></div>
            <div class="col-md-2"><label class="form-label">Y · Fracción</label><input class="form-control form-control-sm" name="fracc" id="editFracc"></div>

            <div class="col-md-10"><label class="form-label">Z · Observaciones</label><textarea class="form-control form-control-sm" rows="2" name="observaciones" id="editObs"></textarea></div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-light btn-sm" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-success btn-sm" style="font-weight:900;">Guardar cambios</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- =========================
     MODAL: IMPORTAR EXCEL (ADMIN/SUPERADMIN/S-1 autorizado)
     ========================= -->
<div class="modal fade" id="modalExcel" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="post" enctype="multipart/form-data">
        <?php csrf_if_exists(); ?>
        <input type="hidden" name="accion" value="subir_excel">

        <div class="modal-header">
          <h5 class="modal-title" style="font-weight:900;">Importar / Actualizar desde Excel (A–Z)</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <p class="small text-muted mb-1">
            El archivo debe ser <b>.xls</b> o <b>.xlsx</b>. Se acepta tu formato con separadores por jerarquía:
            filas con <b>OFICIALES / SUBOFICIALES / SOLDADOS / AGENTES CIVILES</b> en la columna A.
          </p>

          <div class="alert alert-info py-2 small mb-2">
            <b>Si te aparece “Class ZipArchive not found”</b>: habilitá <code>extension=zip</code> en tu <code>php.ini</code> (XAMPP) y reiniciá Apache.
          </div>

          <ul class="small mb-2">
            <li><b>A:</b> Grado (o separador jerarquía)</li><li><b>B:</b> Arma / Especialidad</li><li><b>C:</b> Apellido y Nombre</li><li><b>D:</b> DNI</li>
            <li><b>E:</b> CUIL</li><li><b>F:</b> Fecha de nacimiento</li><li><b>G:</b> Peso</li><li><b>H:</b> Altura</li>
            <li><b>I:</b> Sexo</li><li><b>J:</b> Domicilio</li><li><b>K:</b> Estado civil</li><li><b>L:</b> Hijos</li>
            <li><b>M:</b> NOU</li><li><b>N:</b> Nro Cta Banco</li><li><b>O:</b> CBU Banco</li><li><b>P:</b> Alias Banco</li>
            <li><b>Q:</b> Fecha último Anexo 27</li><li><b>R:</b> Tiene parte de enfermo (SI/NO/1/0)</li>
            <li><b>S:</b> Desde</li><li><b>T:</b> Hasta</li><li><b>U:</b> Cantidad de parte de enfermo</li>
            <li><b>V:</b> Destino interno</li><li><b>W:</b> Rol</li><li><b>X:</b> Años en destino</li><li><b>Y:</b> Fracción</li><li><b>Z:</b> Observaciones</li>
          </ul>

          <div class="mb-2">
            <input type="file" name="archivo_excel" class="form-control form-control-sm" accept=".xls,.xlsx" required>
          </div>

          <p class="small text-warning mb-0">
            Se actualiza por UNIQUE <code>(unidad_id, dni)</code>: si el DNI existe en la unidad, se actualiza; si no existe, se crea.<br>
            <b>Nota:</b> La columna U (cantidad) no pisa el conteo incremental si ya hay un valor en DB.<br>
            La jerarquía se guarda en <code>jerarquia</code> usando el ENUM real: <code>OFICIAL/SUBOFICIAL/SOLDADO/AGENTE_CIVIL</code>.
          </p>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-light btn-sm" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-info btn-sm" style="font-weight:900;">Importar / Actualizar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
(function(){
  const table = document.getElementById('tablaPersonal');
  const toggle = document.getElementById('toggleFull');

  function setMode(full){
    if (!table) return;
    table.classList.remove('table-mode-compact','table-mode-full');
    table.classList.add(full ? 'table-mode-full' : 'table-mode-compact');
  }
  setMode(false);

  if (toggle){
    toggle.addEventListener('change', () => setMode(toggle.checked));
  }

  document.querySelectorAll('.form-delete-one').forEach(form => {
    form.addEventListener('submit', (ev) => {
      ev.preventDefault();
      Swal.fire({
        title: '¿Eliminar este registro?',
        text: 'Esta acción no se puede deshacer.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
      }).then(res => { if (res.isConfirmed) form.submit(); });
    });
  });

  document.querySelectorAll('.form-delete-all').forEach(form => {
    form.addEventListener('submit', (ev) => {
      ev.preventDefault();
      Swal.fire({
        title: '¿Eliminar TODA la lista?',
        html: 'Se eliminará <b>todo el personal</b> de la unidad activa (excepto SUPERADMIN).<br><br><span style="opacity:.9">Esta acción no se puede deshacer.</span>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar todo',
        cancelButtonText: 'Cancelar'
      }).then(res => { if (res.isConfirmed) form.submit(); });
    });
  });

  document.querySelectorAll('.btn-edit').forEach(btn => {
    btn.addEventListener('click', () => {
      let row = {};
      try { row = JSON.parse(btn.dataset.row || '{}'); } catch(e){ row = {}; }

      const set = (id, val) => { const el=document.getElementById(id); if(el) el.value = (val ?? ''); };

      set('editId', row.id);
      set('editGrado', row.grado);
      set('editArma', row.arma);
      set('editApellidoNombre', row.apellido_nombre);
      set('editDni', row.dni);
      set('editCuil', row.cuil);
      set('editFechaNac', row.fecha_nac);
      set('editPeso', row.peso);
      set('editAltura', row.altura);
      set('editSexo', row.sexo);
      set('editDomicilio', row.domicilio);
      set('editEstadoCivil', row.estado_civil);
      set('editHijos', row.hijos);
      set('editNou', row.nou);
      set('editNroCta', row.nro_cta);
      set('editCbu', row.cbu);
      set('editAliasBanco', row.alias_banco);
      set('editFechaAnexo27', row.fecha_ultimo_anexo27);
      set('editParteDesde', row.parte_enfermo_desde);
      set('editParteHasta', row.parte_enfermo_hasta);
      set('editCantPartes', row.cantidad_parte_enfermo ?? 0);
      set('editDestinoInterno', row.destino_interno);
      set('editRolExcel', row.rol);
      set('editAniosDestino', row.anios_en_destino);
      set('editFracc', row.fracc);
      set('editObs', row.observaciones);

      // Jerarquía: ENUM (OFICIAL/SUBOFICIAL/SOLDADO/AGENTE_CIVIL)
      const jer = (row.jerarquia_calc || row.jerarquia || '').toString();
      set('editJerarquia', jer);

      const chk = document.getElementById('editTieneParte');
      if (chk) chk.checked = (parseInt(row.tiene_parte_enfermo || '0', 10) === 1);

      const sel = document.getElementById('editDestinoId');
      if (sel) {
        const did = parseInt(row.destino_id || '0', 10);
        sel.value = String(did > 0 ? did : 0);
      }
    });
  });

  const nuevoSel = document.getElementById('nuevoDestinoId');
  const nuevoTxt = document.getElementById('nuevoDestinoInterno');
  if (nuevoSel && nuevoTxt){
    nuevoSel.addEventListener('change', () => {
      if (nuevoTxt.value.trim() !== '') return;
      const opt = nuevoSel.options[nuevoSel.selectedIndex];
      if (!opt) return;
      const label = (opt.textContent || '').trim();
      const parts = label.split(' - ');
      nuevoTxt.value = (parts.length > 1 ? parts.slice(1).join(' - ') : label).trim();
    });
  }
})();
</script>
</body>
</html>