<?php declare(strict_types=1);
/**
 * public/personal/personal_ficha.php
 * Ficha individual (mejorada con DB nueva):
 * - personal_unidad
 * - sanidad_partes_enfermo (con evento 'parte'/'alta' si existe)
 * - personal_documentos (con sanidad_id / evento_id + metadata si existe)
 * - personal_eventos (vacaciones / plan_llamada / licencias / etc.)
 *
 * Cambios clave:
 * - Evidencias de sanidad se vinculan a sanidad_partes_enfermo.id (sanidad_id)
 * - Historial sanidad con evidencias asociadas
 * - Soft delete si existe deleted_at
 * - Sync del estado canónico (personal_unidad) basado en sanidad + evidencias reales
 */

$ROOT = realpath(__DIR__ . '/../../');
if (!$ROOT) { http_response_code(500); exit('No se pudo resolver ROOT del proyecto.'); }

$BOOT = $ROOT . '/auth/bootstrap.php';
$DB   = $ROOT . '/config/db.php';

if (!is_file($BOOT)) { http_response_code(500); exit('Falta: ' . $BOOT); }
if (!is_file($DB))   { http_response_code(500); exit('Falta: ' . $DB); }

require_once $BOOT;
require_login();
require_once $DB;

/** @var PDO $pdo */
if (!isset($pdo) || !($pdo instanceof PDO)) {
  http_response_code(500);
  exit('No hay conexión PDO. Revisá config/db.php.');
}

/* ========================= Helpers ========================= */
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function norm_dni(string $dni): string { return preg_replace('/\D+/', '', $dni) ?? ''; }

function csrf_if_exists(): void {
  if (function_exists('csrf_input')) {
    $out = csrf_input();
    if (is_string($out) && $out !== '') echo $out;
  }
}

function table_exists(PDO $pdo, string $table): bool {
  $st = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t");
  $st->execute([':t'=>$table]);
  return ((int)$st->fetchColumn()) > 0;
}

function columns(PDO $pdo, string $table): array {
  $st = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t");
  $st->execute([':t'=>$table]);
  $cols = $st->fetchAll(PDO::FETCH_COLUMN) ?: [];
  $map = [];
  foreach ($cols as $c) $map[(string)$c] = true;
  return $map;
}

function date_or_null(string $ymd): ?string {
  $ymd = trim($ymd);
  if ($ymd === '') return null;
  $ts = strtotime($ymd);
  return ($ts !== false) ? date('Y-m-d', $ts) : null;
}

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

function fmt_date(?string $ymd): string {
  if (!$ymd) return '—';
  $ts = strtotime($ymd);
  if ($ts === false) return '—';
  return date('d/m/Y', $ts);
}

function fmt_bytes(?int $b): string {
  if (!$b || $b <= 0) return '—';
  $u = ['B','KB','MB','GB','TB'];
  $i = 0;
  $v = (float)$b;
  while ($v >= 1024 && $i < count($u)-1) { $v /= 1024; $i++; }
  return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') . ' ' . $u[$i];
}

/**
 * Sube evidencias MULTI a filesystem e inserta en personal_documentos.
 * - Vincula a sanidad_id o evento_id si se pasan.
 * - Guarda metadata si existen columnas.
 * Retorna cantidad de archivos subidos.
 */
function upload_evidencias(
  PDO $pdo,
  array $colsPD,
  string $root,
  string $docsRelDir,
  int $unidadId,
  int $personalTargetId,
  int $createdById,
  string $inputName,         // 'sanidad_evidencias'
  string $tipoDoc,           // 'parte_enfermo' o 'alta_parte_enfermo' o etc
  string $tituloDoc,         // titulo fijo
  ?string $fechaDoc,         // Y-m-d o null
  ?string $notaDoc,          // nota/obs o null
  ?int $sanidadId = null,    // NUEVO: vínculo al evento de sanidad
  ?int $eventoId  = null     // NUEVO: vínculo a evento genérico (vacaciones/plan_llamada/etc)
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

  $allowedExt = ['pdf','jpg','jpeg','png','webp','doc','docx'];
  $allowedMime = [
    'application/pdf',
    'image/jpeg','image/png','image/webp',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/octet-stream',
  ];

  $carpRel = rtrim($docsRelDir,'/') . '/' . $personalTargetId;
  $carpAbs = $root . '/' . $carpRel;
  if (!is_dir($carpAbs)) @mkdir($carpAbs, 0775, true);

  $subidos = 0;
  $fechaDoc = $fechaDoc ?: date('Y-m-d');

  foreach ($names as $i => $origName) {
    $err = $errs[$i] ?? UPLOAD_ERR_NO_FILE;
    if ($err === UPLOAD_ERR_NO_FILE) continue;
    if ($err !== UPLOAD_ERR_OK) throw new RuntimeException('Error subiendo evidencia (código ' . (int)$err . ').');

    $size = (int)($sizes[$i] ?? 0);
    if ($size > 20 * 1024 * 1024) throw new RuntimeException('La evidencia supera 20MB.');

    $tmpName = (string)($tmp[$i] ?? '');
    if ($tmpName === '' || !is_file($tmpName)) throw new RuntimeException('Archivo temporal inválido (evidencia).');

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
    $destAbs  = $root . '/' . $destRel;

    if (!move_uploaded_file($tmpName, $destAbs)) {
      throw new RuntimeException('No se pudo mover la evidencia subida.');
    }

    $sha = null;
    $bytes = null;
    try {
      $bytes = @filesize($destAbs);
      if (function_exists('hash_file')) {
        $sha = @hash_file('sha256', $destAbs);
        if (!is_string($sha) || $sha === '') $sha = null;
      }
    } catch (Throwable $e) {}

    try {
      // Armado dinámico según columnas existentes
      $fields = ['unidad_id','personal_id','tipo','titulo','path','nota','fecha','created_at','created_by_id'];
      $params = [':uid'=>$unidadId, ':pid'=>$personalTargetId, ':tipo'=>$tipoDoc, ':titulo'=>$tituloDoc, ':path'=>$destRel,
        ':nota'=>($notaDoc !== '' ? $notaDoc : null),
        ':fecha'=>$fechaDoc,
        ':cbid'=>($createdById > 0 ? $createdById : null),
      ];

      if (isset($colsPD['sanidad_id'])) { $fields[] = 'sanidad_id'; $params[':sid'] = $sanidadId; }
      if (isset($colsPD['evento_id']))  { $fields[] = 'evento_id';  $params[':eid'] = $eventoId; }

      if (isset($colsPD['original_name'])) { $fields[] = 'original_name'; $params[':on'] = (string)$origName; }
      if (isset($colsPD['mime']))          { $fields[] = 'mime';          $params[':mm'] = $mime; }
      if (isset($colsPD['bytes']))         { $fields[] = 'bytes';         $params[':by'] = ($bytes !== false ? (int)$bytes : null); }
      if (isset($colsPD['sha256']))        { $fields[] = 'sha256';        $params[':sh'] = $sha; }

      $ph = [];
      foreach ($fields as $f) {
        if ($f === 'unidad_id') $ph[] = ':uid';
        elseif ($f === 'personal_id') $ph[] = ':pid';
        elseif ($f === 'tipo') $ph[] = ':tipo';
        elseif ($f === 'titulo') $ph[] = ':titulo';
        elseif ($f === 'path') $ph[] = ':path';
        elseif ($f === 'nota') $ph[] = ':nota';
        elseif ($f === 'fecha') $ph[] = ':fecha';
        elseif ($f === 'created_at') $ph[] = 'NOW()';
        elseif ($f === 'created_by_id') $ph[] = ':cbid';
        elseif ($f === 'sanidad_id') $ph[] = ':sid';
        elseif ($f === 'evento_id') $ph[] = ':eid';
        elseif ($f === 'original_name') $ph[] = ':on';
        elseif ($f === 'mime') $ph[] = ':mm';
        elseif ($f === 'bytes') $ph[] = ':by';
        elseif ($f === 'sha256') $ph[] = ':sh';
        else $ph[] = 'NULL';
      }

      $sql = "INSERT INTO personal_documentos (" . implode(',', $fields) . ") VALUES (" . implode(',', $ph) . ")";
      $st = $pdo->prepare($sql);
      $st->execute($params);

    } catch (Throwable $ex) {
      @unlink($destAbs);
      throw $ex;
    }

    $subidos++;
  }

  return $subidos;
}

/**
 * Sincroniza el estado canónico de sanidad en personal_unidad a partir de:
 * - último evento de sanidad (parte/alta)
 * - evidencias reales vinculadas (personal_documentos.sanidad_id), ignorando deleted_at si existe
 *
 * Regla:
 * - Si el último evento es 'parte' y tiene al menos 1 evidencia => TIENE PARTE
 * - Si el último evento es 'alta' => SIN PARTE
 * - Si el último evento es 'parte' pero SIN evidencia => SIN PARTE (evita estados fantasma)
 */
function sync_personal_sanidad(PDO $pdo, array $colsPD, array $colsSan, int $uid, int $pid, ?int $updatedById): void {
  // 1) Traer último evento sanidad (prioriza created_at si existe, luego updated_at, luego id)
  $order = [];
  if (isset($colsSan['created_at'])) $order[] = "created_at DESC";
  $order[] = "updated_at DESC";
  $order[] = "id DESC";
  $orderBy = implode(', ', $order);

  $st = $pdo->prepare("
    SELECT *
    FROM sanidad_partes_enfermo
    WHERE unidad_id=:uid AND personal_id=:pid
    ORDER BY $orderBy
    LIMIT 1
  ");
  $st->execute([':uid'=>$uid, ':pid'=>$pid]);
  $last = $st->fetch(PDO::FETCH_ASSOC);

  if (!$last) {
    // No hay historial: dejar limpio
    $upd = $pdo->prepare("
      UPDATE personal_unidad
      SET tiene_parte_enfermo=0,
          parte_enfermo_desde=NULL,
          parte_enfermo_hasta=NULL,
          updated_at=NOW(),
          updated_by_id=:ubid
      WHERE id=:pid AND unidad_id=:uid
      LIMIT 1
    ");
    $upd->execute([':ubid'=>$updatedById, ':pid'=>$pid, ':uid'=>$uid]);
    return;
  }

  // 2) Determinar tipo de evento: evento (nuevo) o tiene_parte (viejo)
  $evento = null;
  if (isset($colsSan['evento']) && !empty($last['evento'])) {
    $evento = (string)$last['evento']; // 'parte'|'alta'
  } else {
    // compat viejo: tiene_parte 'si'/'no'
    $evento = ((string)($last['tiene_parte'] ?? 'no') === 'si') ? 'parte' : 'alta';
  }

  $ini = !empty($last['inicio']) ? (string)$last['inicio'] : null;
  $fin = !empty($last['fin']) ? (string)$last['fin'] : null;

  // 3) Si es parte, debe tener evidencias reales
  $tiene = 0;
  if ($evento === 'parte') {
    // Cuenta evidencias vinculadas al sanidad_id del último evento
    if (isset($colsPD['sanidad_id'])) {
      $whereDel = '';
      if (isset($colsPD['deleted_at'])) $whereDel = " AND deleted_at IS NULL ";
      $stE = $pdo->prepare("
        SELECT COUNT(*)
        FROM personal_documentos
        WHERE unidad_id=:uid AND personal_id=:pid
          AND sanidad_id=:sid
          $whereDel
      ");
      $stE->execute([':uid'=>$uid, ':pid'=>$pid, ':sid'=>(int)$last['id']]);
      $c = (int)$stE->fetchColumn();
      $tiene = ($c > 0) ? 1 : 0;
    } else {
      // Sin sanidad_id (no migrado): fallback a tipo 'parte_enfermo' por fecha
      $whereDel = '';
      if (isset($colsPD['deleted_at'])) $whereDel = " AND deleted_at IS NULL ";
      $stE = $pdo->prepare("
        SELECT COUNT(*)
        FROM personal_documentos
        WHERE unidad_id=:uid AND personal_id=:pid
          AND tipo='parte_enfermo'
          $whereDel
      ");
      $stE->execute([':uid'=>$uid, ':pid'=>$pid]);
      $c = (int)$stE->fetchColumn();
      $tiene = ($c > 0) ? 1 : 0;
    }
  }

  // 4) Cantidad: mantener incremental (tomamos la del evento si existe, o la de personal_unidad si ya la tenías)
  $cant = null;
  if (isset($last['cantidad'])) $cant = (int)$last['cantidad'];

  // 5) Escribir canónico en personal_unidad
  if ($tiene === 1) {
    $upd = $pdo->prepare("
      UPDATE personal_unidad
      SET tiene_parte_enfermo=1,
          parte_enfermo_desde=:ini,
          parte_enfermo_hasta=:fin,
          cantidad_parte_enfermo=COALESCE(:cant, cantidad_parte_enfermo),
          updated_at=NOW(),
          updated_by_id=:ubid
      WHERE id=:pid AND unidad_id=:uid
      LIMIT 1
    ");
    $upd->execute([
      ':ini'=>$ini,
      ':fin'=>$fin,
      ':cant'=>$cant,
      ':ubid'=>$updatedById,
      ':pid'=>$pid,
      ':uid'=>$uid
    ]);
  } else {
    // SIN PARTE: si el último evento fue alta, usamos fin como hasta; si fue parte sin evidencia, limpiamos
    $hasta = ($evento === 'alta') ? ($fin ?: date('Y-m-d')) : null;

    $upd = $pdo->prepare("
      UPDATE personal_unidad
      SET tiene_parte_enfermo=0,
          parte_enfermo_hasta=:hasta,
          parte_enfermo_desde=CASE WHEN :evento='alta' THEN parte_enfermo_desde ELSE NULL END,
          cantidad_parte_enfermo=COALESCE(:cant, cantidad_parte_enfermo),
          updated_at=NOW(),
          updated_by_id=:ubid
      WHERE id=:pid AND unidad_id=:uid
      LIMIT 1
    ");
    $upd->execute([
      ':hasta'=>$hasta,
      ':evento'=>$evento,
      ':cant'=>$cant,
      ':ubid'=>$updatedById,
      ':pid'=>$pid,
      ':uid'=>$uid
    ]);
  }
}

/* ========================= Base URLs ========================= */
$SELF_WEB         = (string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
$BASE_DIR_WEB     = rtrim(str_replace('\\','/', dirname($SELF_WEB)), '/');         // /ea/public/personal
$BASE_PUBLIC_WEB  = rtrim(str_replace('\\','/', dirname($BASE_DIR_WEB)), '/');    // /ea/public
$BASE_APP_WEB     = rtrim(str_replace('\\','/', dirname($BASE_PUBLIC_WEB)), '/'); // /ea
$ASSETS_WEB       = $BASE_APP_WEB . '/assets';

$IMG_BG  = $ASSETS_WEB . '/img/fondo.png';
$ESCUDO  = $ASSETS_WEB . '/img/ecmilm.png';
$FAVICON = $ASSETS_WEB . '/img/ecmilm.png';

/* ========================= Usuario / Rol / Unidad activa ========================= */
$user = function_exists('current_user') ? current_user() : ($_SESSION['user'] ?? null);
$dniNormUser = norm_dni((string)($user['dni'] ?? $user['username'] ?? ''));

$personalId   = 0;
$unidadPropia = 1;
$fullNameDB   = '';

try {
  if ($dniNormUser !== '') {
    $st = $pdo->prepare("
      SELECT id, unidad_id, CONCAT_WS(' ', grado, arma, apellido, nombre) AS nombre_comp
      FROM personal_unidad
      WHERE REPLACE(REPLACE(REPLACE(dni,'.',''),'-',''),' ','') = :dni
      LIMIT 1
    ");
    $st->execute([':dni'=>$dniNormUser]);
    if ($r = $st->fetch(PDO::FETCH_ASSOC)) {
      $personalId   = (int)($r['id'] ?? 0);
      $unidadPropia = (int)($r['unidad_id'] ?? 1);
      $fullNameDB   = (string)($r['nombre_comp'] ?? '');
    }
  }
} catch (Throwable $e) {}

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

if ($roleCodigo === 'USUARIO' && table_exists($pdo, 'usuario_roles')) {
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

$unidadActiva = $unidadPropia;
if ($esSuperAdmin) {
  $uSel = (int)($_SESSION['unidad_id'] ?? 0);
  if ($uSel > 0) $unidadActiva = $uSel;
}

/* Branding */
$NOMBRE  = 'Unidad';
$LEYENDA = '';
try {
  $st = $pdo->prepare("SELECT nombre_completo, subnombre FROM unidades WHERE id=:id LIMIT 1");
  $st->execute([':id'=>$unidadActiva]);
  if ($u = $st->fetch(PDO::FETCH_ASSOC)) {
    if (!empty($u['nombre_completo'])) $NOMBRE = (string)$u['nombre_completo'];
    if (!empty($u['subnombre'])) $LEYENDA = trim((string)$u['subnombre']);
  }
} catch (Throwable $e) {}

/* ========================= Storage (por unidad) ========================= */
$colsUn = columns($pdo, 'unidades');
$colsPD = columns($pdo, 'personal_documentos');
$colsSan = columns($pdo, 'sanidad_partes_enfermo');
$colsPE = table_exists($pdo, 'personal_eventos') ? columns($pdo, 'personal_eventos') : [];

$unidadSlug = 'unidad_' . $unidadActiva;
if (isset($colsUn['slug'])) {
  try {
    $st = $pdo->prepare("SELECT slug FROM unidades WHERE id=:id LIMIT 1");
    $st->execute([':id'=>$unidadActiva]);
    $slug = $st->fetchColumn();
    if (is_string($slug) && trim($slug) !== '') $unidadSlug = trim($slug);
  } catch (Throwable $e) {}
}

$DOCS_REL_DIR = 'storage/unidades/' . $unidadSlug . '/personal_docs';
$DOCS_ABS_DIR = $ROOT . '/' . $DOCS_REL_DIR;
if (!is_dir($DOCS_ABS_DIR)) @mkdir($DOCS_ABS_DIR, 0775, true);

$FOTO_DEFAULT_REL = 'storage/personal_fotos/sinfoto.png';
$FOTO_DEFAULT_ABS = $ROOT . '/' . $FOTO_DEFAULT_REL;
$FOTO_DEFAULT_URL = $BASE_APP_WEB . '/' . $FOTO_DEFAULT_REL;

/* ========================= Params ========================= */
$id = (int)($_GET['id'] ?? 0);
$q  = trim((string)($_GET['q'] ?? ''));
$tab = trim((string)($_GET['tab'] ?? 'ficha')); // ficha|sanidad|docs|eventos

/* ========================= Mensajes ========================= */
$mensajeOk = '';
$mensajeError = '';

/* ==========================================================
   Acciones POST
   ========================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $accion = (string)($_POST['accion'] ?? '');

  try {
    if (!$esAdmin) throw new RuntimeException('Acceso restringido. Solo ADMIN/SUPERADMIN.');

    /* ---- Guardar personal (subset seguro) ---- */
    if ($accion === 'guardar_personal') {
      $pid = (int)($_POST['personal_id'] ?? 0);
      if ($pid <= 0) throw new RuntimeException('ID personal inválido.');

      $grado = trim((string)($_POST['grado'] ?? ''));
      $arma  = trim((string)($_POST['arma'] ?? ''));
      $apnom = trim((string)($_POST['apellido_nombre'] ?? ''));
      $dni   = norm_dni((string)($_POST['dni'] ?? ''));
      $cuil  = trim((string)($_POST['cuil'] ?? ''));
      $fnac  = date_or_null((string)($_POST['fecha_nac'] ?? ''));
      $sexo  = trim((string)($_POST['sexo'] ?? ''));
      $dom   = trim((string)($_POST['domicilio'] ?? ''));
      $ec    = trim((string)($_POST['estado_civil'] ?? ''));
      $hijos = ($_POST['hijos'] ?? '') === '' ? null : (int)$_POST['hijos'];
      $dest  = trim((string)($_POST['destino_interno'] ?? ''));
      $func  = trim((string)($_POST['funcion'] ?? ''));
      $tel   = trim((string)($_POST['telefono'] ?? ''));
      $cor   = trim((string)($_POST['correo'] ?? ''));
      $obs   = trim((string)($_POST['observaciones'] ?? ''));

      if ($apnom === '') throw new RuntimeException('Apellido y Nombre es obligatorio.');
      if ($dni === '') throw new RuntimeException('DNI es obligatorio.');

      $st = $pdo->prepare("
        UPDATE personal_unidad
        SET
          grado=:grado, arma=:arma, apellido_nombre=:apnom, dni=:dni, cuil=:cuil,
          fecha_nac=:fnac, sexo=:sexo, domicilio=:dom, estado_civil=:ec, hijos=:hijos,
          destino_interno=:dest, funcion=:fun, telefono=:tel, correo=:cor, observaciones=:obs,
          updated_at = NOW(), updated_by_id = :ubid
        WHERE id=:id AND unidad_id=:uid
        LIMIT 1
      ");
      $st->execute([
        ':grado'=>($grado!==''?$grado:null),
        ':arma'=>($arma!==''?$arma:null),
        ':apnom'=>$apnom,
        ':dni'=>$dni,
        ':cuil'=>($cuil!==''?$cuil:null),
        ':fnac'=>$fnac,
        ':sexo'=>($sexo!==''?$sexo:null),
        ':dom'=>($dom!==''?$dom:null),
        ':ec'=>($ec!==''?$ec:null),
        ':hijos'=>$hijos,
        ':dest'=>($dest!==''?$dest:null),
        ':fun'=>($func!==''?$func:null),
        ':tel'=>($tel!==''?$tel:null),
        ':cor'=>($cor!==''?$cor:null),
        ':obs'=>($obs!==''?$obs:null),
        ':ubid'=>($personalId>0?$personalId:null),
        ':id'=>$pid,
        ':uid'=>$unidadActiva,
      ]);

      $mensajeOk = 'Datos del personal actualizados.';
      $id = $pid;
      $tab = 'ficha';
    }

    /* ---- Guardar sanidad (evento + evidencia) ---- */
    if ($accion === 'guardar_sanidad') {
      $pid = (int)($_POST['personal_id'] ?? 0);
      if ($pid <= 0) throw new RuntimeException('ID personal inválido.');

      $tiene = (string)($_POST['tiene_parte'] ?? 'no');
      $tiene = ($tiene === 'si') ? 'si' : 'no';

      $inicio = date_or_null((string)($_POST['inicio'] ?? ''));
      $fin    = date_or_null((string)($_POST['fin'] ?? ''));
      $obsSan = trim((string)($_POST['observaciones_sanidad'] ?? ''));

      // ¿Hay evidencia?
      $hayEvid = false;
      if (isset($_FILES['sanidad_evidencias'])) {
        $err0 = $_FILES['sanidad_evidencias']['error'] ?? UPLOAD_ERR_NO_FILE;
        if (is_array($err0)) {
          foreach ($err0 as $er) { if ((int)$er !== UPLOAD_ERR_NO_FILE) { $hayEvid = true; break; } }
        } else {
          $hayEvid = ((int)$err0 !== UPLOAD_ERR_NO_FILE);
        }
      }

      $pdo->beginTransaction();

      // Estado actual en personal_unidad
      $stCur = $pdo->prepare("
        SELECT
          COALESCE(cantidad_parte_enfermo,0) AS cant,
          parte_enfermo_desde,
          parte_enfermo_hasta,
          tiene_parte_enfermo
        FROM personal_unidad
        WHERE id=:pid AND unidad_id=:uid
        LIMIT 1
      ");
      $stCur->execute([':pid'=>$pid, ':uid'=>$unidadActiva]);
      $cur = $stCur->fetch(PDO::FETCH_ASSOC);
      if (!$cur) throw new RuntimeException('No se pudo leer personal_unidad (sanidad).');

      $cantActual = (int)($cur['cant'] ?? 0);
      $desdePrev  = $cur['parte_enfermo_desde'] ?? null;
      $hastaPrev  = $cur['parte_enfermo_hasta'] ?? null;

      $iniFinal = $inicio ?: ($desdePrev ?: ($tiene === 'si' ? date('Y-m-d') : null));
      $finFinal = $fin    ?: ($hastaPrev ?: null);

      // Si NO hay evidencia: solo registrar/ajustar el último (sin incrementar)
      if (!$hayEvid) {
        // Upsert simple del último (manteniendo cantidad actual)
        $stSel = $pdo->prepare("
          SELECT id
          FROM sanidad_partes_enfermo
          WHERE unidad_id=:uid AND personal_id=:pid
          ORDER BY id DESC
          LIMIT 1
        ");
        $stSel->execute([':uid'=>$unidadActiva, ':pid'=>$pid]);
        $sid = (int)($stSel->fetchColumn() ?: 0);

        if ($sid > 0) {
          $sqlUpd = "
            UPDATE sanidad_partes_enfermo
            SET tiene_parte=:tiene, inicio=:ini, fin=:fin, cantidad=:cant, observaciones=:obs,
                updated_at=NOW(), updated_by_id=:ubid
            WHERE id=:id AND unidad_id=:uid AND personal_id=:pid
            LIMIT 1
          ";
          $stUpd = $pdo->prepare($sqlUpd);
          $stUpd->execute([
            ':tiene'=>$tiene,
            ':ini'=>$iniFinal,
            ':fin'=>$finFinal,
            ':cant'=>$cantActual,
            ':obs'=>($obsSan!==''?$obsSan:null),
            ':ubid'=>($personalId>0?$personalId:null),
            ':id'=>$sid,
            ':uid'=>$unidadActiva,
            ':pid'=>$pid,
          ]);
        } else {
          // Insert mínimo
          $fields = ['unidad_id','personal_id','tiene_parte','inicio','fin','cantidad','observaciones','updated_at','updated_by_id'];
          $vals   = [':uid',':pid',':tiene',':ini',':fin',':cant',':obs','NOW()',':ubid'];

          if (isset($colsSan['evento'])) { $fields[]='evento'; $vals[]=':ev'; }
          if (isset($colsSan['created_at'])) { $fields[]='created_at'; $vals[]='NOW()'; }
          if (isset($colsSan['created_by_id'])) { $fields[]='created_by_id'; $vals[]=':cbid'; }

          $sql = "INSERT INTO sanidad_partes_enfermo (" . implode(',', $fields) . ") VALUES (" . implode(',', $vals) . ")";
          $stIns = $pdo->prepare($sql);
          $params = [
            ':uid'=>$unidadActiva,
            ':pid'=>$pid,
            ':tiene'=>$tiene,
            ':ini'=>$iniFinal,
            ':fin'=>$finFinal,
            ':cant'=>$cantActual,
            ':obs'=>($obsSan!==''?$obsSan:null),
            ':ubid'=>($personalId>0?$personalId:null),
          ];
          if (isset($colsSan['evento'])) $params[':ev'] = ($tiene==='si'?'parte':'alta');
          if (isset($colsSan['created_by_id'])) $params[':cbid'] = ($personalId>0?$personalId:null);

          $stIns->execute($params);
        }

        // Sync canónico (sin evidencia => evita fantasmas)
        sync_personal_sanidad($pdo, $colsPD, $colsSan, $unidadActiva, $pid, ($personalId>0?$personalId:null));

        $pdo->commit();
        $mensajeOk = 'Sanidad actualizada (sin evidencia).';
        $id = $pid;
        $tab = 'sanidad';
      }

      // Hay evidencia: se crea evento nuevo (parte o alta) y se vincula evidencia al sanidad_id
      else {
        if ($tiene === 'si') {
          // Incremento canónico (+1)
          $stPU = $pdo->prepare("
            UPDATE personal_unidad
            SET
              tiene_parte_enfermo = 1,
              parte_enfermo_desde = COALESCE(:ini, parte_enfermo_desde, CURDATE()),
              parte_enfermo_hasta = COALESCE(:fin, parte_enfermo_hasta),
              cantidad_parte_enfermo = COALESCE(cantidad_parte_enfermo,0) + 1,
              updated_at = NOW(),
              updated_by_id = :ubid
            WHERE id=:pid AND unidad_id=:uid
            LIMIT 1
          ");
          $stPU->execute([
            ':ini'=>$iniFinal,
            ':fin'=>$finFinal,
            ':ubid'=>($personalId>0?$personalId:null),
            ':pid'=>$pid,
            ':uid'=>$unidadActiva
          ]);

          $stRe = $pdo->prepare("SELECT COALESCE(cantidad_parte_enfermo,0) FROM personal_unidad WHERE id=:pid AND unidad_id=:uid LIMIT 1");
          $stRe->execute([':pid'=>$pid, ':uid'=>$unidadActiva]);
          $cantFinal = (int)($stRe->fetchColumn() ?: ($cantActual+1));

          // Insert evento sanidad (PARTE)
          $fields = ['unidad_id','personal_id','tiene_parte','inicio','fin','cantidad','observaciones','updated_at','updated_by_id'];
          $vals   = [':uid',':pid','si',':ini',':fin',':cant',':obs','NOW()',':ubid'];

          if (isset($colsSan['evento'])) { $fields[]='evento'; $vals[]='parte'; }
          if (isset($colsSan['created_at'])) { $fields[]='created_at'; $vals[]='NOW()'; }
          if (isset($colsSan['created_by_id'])) { $fields[]='created_by_id'; $vals[]=':cbid'; }

          $sql = "INSERT INTO sanidad_partes_enfermo (" . implode(',', $fields) . ") VALUES (" . implode(',', $vals) . ")";
          $stIns = $pdo->prepare($sql);
          $params = [
            ':uid'=>$unidadActiva,
            ':pid'=>$pid,
            ':ini'=>$iniFinal,
            ':fin'=>$finFinal,
            ':cant'=>$cantFinal,
            ':obs'=>($obsSan!==''?$obsSan:null),
            ':ubid'=>($personalId>0?$personalId:null),
          ];
          if (isset($colsSan['created_by_id'])) $params[':cbid'] = ($personalId>0?$personalId:null);

          $stIns->execute($params);
          $sanidadId = (int)$pdo->lastInsertId();

          // Subir evidencias vinculadas
          $nUp = upload_evidencias(
            $pdo, $colsPD, $ROOT, $DOCS_REL_DIR,
            $unidadActiva, $pid, (int)$personalId,
            'sanidad_evidencias',
            'parte_enfermo',
            'Parte de enfermo',
            $iniFinal,
            ($obsSan !== '' ? $obsSan : null),
            $sanidadId,
            null
          );

          // Sync final (por si faltan evidencias por error)
          sync_personal_sanidad($pdo, $colsPD, $colsSan, $unidadActiva, $pid, ($personalId>0?$personalId:null));

          $pdo->commit();
          $mensajeOk = "Parte cargado. Evidencias: {$nUp}. Cantidad total de partes: {$cantFinal}.";
          $id = $pid;
          $tab = 'sanidad';
        } else {
          // ALTA
          $finAlta = $finFinal ?: date('Y-m-d');

          $stPU = $pdo->prepare("
            UPDATE personal_unidad
            SET
              tiene_parte_enfermo = 0,
              parte_enfermo_hasta = :fin,
              updated_at = NOW(),
              updated_by_id = :ubid
            WHERE id=:pid AND unidad_id=:uid
            LIMIT 1
          ");
          $stPU->execute([
            ':fin'=>$finAlta,
            ':ubid'=>($personalId>0?$personalId:null),
            ':pid'=>$pid,
            ':uid'=>$unidadActiva
          ]);

          // Insert evento sanidad (ALTA)
          $fields = ['unidad_id','personal_id','tiene_parte','inicio','fin','cantidad','observaciones','updated_at','updated_by_id'];
          $vals   = [':uid',':pid','no',':ini',':fin',':cant',':obs','NOW()',':ubid'];

          if (isset($colsSan['evento'])) { $fields[]='evento'; $vals[]='alta'; }
          if (isset($colsSan['created_at'])) { $fields[]='created_at'; $vals[]='NOW()'; }
          if (isset($colsSan['created_by_id'])) { $fields[]='created_by_id'; $vals[]=':cbid'; }

          $sql = "INSERT INTO sanidad_partes_enfermo (" . implode(',', $fields) . ") VALUES (" . implode(',', $vals) . ")";
          $stIns = $pdo->prepare($sql);
          $params = [
            ':uid'=>$unidadActiva,
            ':pid'=>$pid,
            ':ini'=>$iniFinal,
            ':fin'=>$finAlta,
            ':cant'=>$cantActual,
            ':obs'=>($obsSan!==''?$obsSan:null),
            ':ubid'=>($personalId>0?$personalId:null),
          ];
          if (isset($colsSan['created_by_id'])) $params[':cbid'] = ($personalId>0?$personalId:null);

          $stIns->execute($params);
          $sanidadId = (int)$pdo->lastInsertId();

          // Evidencias vinculadas a ALTA
          $nUp = upload_evidencias(
            $pdo, $colsPD, $ROOT, $DOCS_REL_DIR,
            $unidadActiva, $pid, (int)$personalId,
            'sanidad_evidencias',
            'alta_parte_enfermo',
            'Alta parte de enfermo',
            $finAlta,
            ($obsSan !== '' ? $obsSan : null),
            $sanidadId,
            null
          );

          sync_personal_sanidad($pdo, $colsPD, $colsSan, $unidadActiva, $pid, ($personalId>0?$personalId:null));

          $pdo->commit();
          $mensajeOk = "Alta cargada. Evidencias: {$nUp}.";
          $id = $pid;
          $tab = 'sanidad';
        }
      }
    }

    /* ---- Subir documento genérico ---- */
    if ($accion === 'subir_documento') {
      $pid = (int)($_POST['personal_id'] ?? 0);
      if ($pid <= 0) throw new RuntimeException('ID personal inválido.');

      if (!isset($_FILES['archivo']) || ($_FILES['archivo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Debe seleccionar un archivo.');
      }
      $file = $_FILES['archivo'];
      if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Error al subir archivo (código ' . (int)$file['error'] . ').');
      }
      if (!empty($file['size']) && (int)$file['size'] > 20*1024*1024) {
        throw new RuntimeException('El archivo supera 20MB.');
      }

      $origName = (string)($file['name'] ?? 'documento');
      $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
      $allowedExt = ['pdf','jpg','jpeg','png','webp','doc','docx'];
      if (!in_array($ext, $allowedExt, true)) {
        throw new RuntimeException('Extensión no permitida: .' . $ext);
      }

      $tipo   = trim((string)($_POST['tipo'] ?? 'otros'));
      $titulo = trim((string)($_POST['titulo'] ?? ''));
      $nota   = trim((string)($_POST['nota'] ?? ''));
      $fecha  = date_or_null((string)($_POST['fecha'] ?? ''));

      // Link opcional a evento genérico (si querés usarlo desde UI)
      $eventoId = isset($colsPD['evento_id']) ? (int)($_POST['evento_id'] ?? 0) : 0;
      if ($eventoId <= 0) $eventoId = null;

      $safeName = preg_replace('/[^A-Za-z0-9_\.-]/', '_', $origName);
      if ($safeName === '') $safeName = 'documento.' . $ext;

      $carpRel = $DOCS_REL_DIR . '/' . $pid;
      $carpAbs = $ROOT . '/' . $carpRel;
      if (!is_dir($carpAbs)) @mkdir($carpAbs, 0775, true);

      $destRel = $carpRel . '/' . time() . '_' . $safeName;
      $destAbs = $ROOT . '/' . $destRel;

      if (!move_uploaded_file((string)$file['tmp_name'], $destAbs)) {
        throw new RuntimeException('No se pudo mover el archivo subido.');
      }

      $mime = detect_mime($destAbs);
      $bytes = @filesize($destAbs);
      $sha = null;
      if (function_exists('hash_file')) {
        $sha = @hash_file('sha256', $destAbs);
        if (!is_string($sha) || $sha === '') $sha = null;
      }

      // Insert dinámico
      $fields = ['unidad_id','personal_id','tipo','titulo','path','nota','fecha','created_at','created_by_id'];
      $vals   = [':uid',':pid',':tipo',':titulo',':path',':nota',':fecha','NOW()',':cbid'];
      $params = [
        ':uid'=>$unidadActiva,
        ':pid'=>$pid,
        ':tipo'=>($tipo!==''?$tipo:null),
        ':titulo'=>($titulo!==''?$titulo:null),
        ':path'=>$destRel,
        ':nota'=>($nota!==''?$nota:null),
        ':fecha'=>$fecha,
        ':cbid'=>($personalId>0?$personalId:null),
      ];

      if (isset($colsPD['evento_id'])) { $fields[]='evento_id'; $vals[]=':eid'; $params[':eid']=$eventoId; }
      if (isset($colsPD['original_name'])) { $fields[]='original_name'; $vals[]=':on'; $params[':on']=$origName; }
      if (isset($colsPD['mime'])) { $fields[]='mime'; $vals[]=':mm'; $params[':mm']=$mime; }
      if (isset($colsPD['bytes'])) { $fields[]='bytes'; $vals[]=':by'; $params[':by']=($bytes!==false?(int)$bytes:null); }
      if (isset($colsPD['sha256'])) { $fields[]='sha256'; $vals[]=':sh'; $params[':sh']=$sha; }

      $sql = "INSERT INTO personal_documentos (" . implode(',', $fields) . ") VALUES (" . implode(',', $vals) . ")";
      $st = $pdo->prepare($sql);
      $st->execute($params);

      $mensajeOk = 'Documento cargado correctamente.';
      $id = $pid;
      $tab = 'docs';
    }

    /* ---- Crear evento genérico (personal_eventos) ---- */
    if ($accion === 'crear_evento' && table_exists($pdo, 'personal_eventos')) {
      $pid = (int)($_POST['personal_id'] ?? 0);
      if ($pid <= 0) throw new RuntimeException('ID personal inválido.');

      $tipo = trim((string)($_POST['ev_tipo'] ?? ''));
      if ($tipo === '') throw new RuntimeException('Tipo de evento obligatorio.');

      $desde = date_or_null((string)($_POST['ev_desde'] ?? ''));
      $hasta = date_or_null((string)($_POST['ev_hasta'] ?? ''));
      $estado = trim((string)($_POST['ev_estado'] ?? ''));
      $titulo = trim((string)($_POST['ev_titulo'] ?? ''));
      $desc = trim((string)($_POST['ev_desc'] ?? ''));

      // data_json opcional (texto JSON válido)
      $dataJson = null;
      if (!empty($colsPE['data_json'])) {
        $raw = trim((string)($_POST['ev_json'] ?? ''));
        if ($raw !== '') {
          $tmp = json_decode($raw, true);
          if (json_last_error() !== JSON_ERROR_NONE) throw new RuntimeException('JSON inválido en "Datos extra".');
          $dataJson = $raw;
        }
      }

      $fields = ['unidad_id','personal_id','tipo','desde','hasta','estado','titulo','descripcion','created_at','created_by_id'];
      $vals   = [':uid',':pid',':tipo',':de',':ha',':es',':ti',':ds','NOW()',':cb'];
      $params = [
        ':uid'=>$unidadActiva,
        ':pid'=>$pid,
        ':tipo'=>$tipo,
        ':de'=>$desde,
        ':ha'=>$hasta,
        ':es'=>($estado!==''?$estado:null),
        ':ti'=>($titulo!==''?$titulo:null),
        ':ds'=>($desc!==''?$desc:null),
        ':cb'=>($personalId>0?$personalId:null),
      ];

      if (!empty($colsPE['data_json'])) { $fields[]='data_json'; $vals[]=':js'; $params[':js']=$dataJson; }

      $sql = "INSERT INTO personal_eventos (" . implode(',', $fields) . ") VALUES (" . implode(',', $vals) . ")";
      $st = $pdo->prepare($sql);
      $st->execute($params);

      $mensajeOk = 'Evento creado.';
      $id = $pid;
      $tab = 'eventos';
    }

    /* ---- Eliminar evento genérico ---- */
    if ($accion === 'eliminar_evento' && table_exists($pdo, 'personal_eventos')) {
      $eid = (int)($_POST['evento_id'] ?? 0);
      $pid = (int)($_POST['personal_id'] ?? 0);
      if ($eid <= 0 || $pid <= 0) throw new RuntimeException('Parámetros inválidos.');

      // (Simple) borrar el evento. Docs vinculados quedan con evento_id NULL por FK ON DELETE SET NULL (si lo creaste así).
      $del = $pdo->prepare("
        DELETE FROM personal_eventos
        WHERE id=:id AND unidad_id=:uid AND personal_id=:pid
        LIMIT 1
      ");
      $del->execute([':id'=>$eid, ':uid'=>$unidadActiva, ':pid'=>$pid]);

      $mensajeOk = 'Evento eliminado.';
      $id = $pid;
      $tab = 'eventos';
    }

    /* ---- Eliminar documento (soft delete si existe) ---- */
    if ($accion === 'eliminar_documento') {
      $docId = (int)($_POST['doc_id'] ?? 0);
      $pid   = (int)($_POST['personal_id'] ?? 0);
      if ($docId <= 0 || $pid <= 0) throw new RuntimeException('Parámetros inválidos para eliminar.');

      $st = $pdo->prepare("
        SELECT id, path, tipo, sanidad_id, evento_id
        FROM personal_documentos
        WHERE id=:id AND unidad_id=:uid AND personal_id=:pid
        LIMIT 1
      ");
      $st->execute([':id'=>$docId, ':uid'=>$unidadActiva, ':pid'=>$pid]);
      $doc = $st->fetch(PDO::FETCH_ASSOC);
      if (!$doc) throw new RuntimeException('Documento no encontrado.');

      $path = (string)($doc['path'] ?? '');
      $sanidadId = isset($colsPD['sanidad_id']) ? (int)($doc['sanidad_id'] ?? 0) : 0;

      $pdo->beginTransaction();

      if (isset($colsPD['deleted_at'])) {
        $up = $pdo->prepare("
          UPDATE personal_documentos
          SET deleted_at = NOW()
          WHERE id=:id AND unidad_id=:uid AND personal_id=:pid
          LIMIT 1
        ");
        $up->execute([':id'=>$docId, ':uid'=>$unidadActiva, ':pid'=>$pid]);
      } else {
        $del = $pdo->prepare("
          DELETE FROM personal_documentos
          WHERE id=:id AND unidad_id=:uid AND personal_id=:pid
          LIMIT 1
        ");
        $del->execute([':id'=>$docId, ':uid'=>$unidadActiva, ':pid'=>$pid]);
      }

      // Si era evidencia de sanidad (o tenía sanidad_id), re-sync canónico
      if ($sanidadId > 0) {
        sync_personal_sanidad($pdo, $colsPD, $colsSan, $unidadActiva, $pid, ($personalId>0?$personalId:null));
      }

      $pdo->commit();

      // Archivo físico
      if ($path !== '') {
        $abs = $ROOT . '/' . ltrim($path, '/');
        if (is_file($abs)) @unlink($abs);
      }

      $mensajeOk = 'Documento eliminado.';
      $id = $pid;
      // conservar tab si venía
    }

  } catch (Throwable $ex) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    $mensajeError = $ex->getMessage();
  }
}

/* ==========================================================
   Carga de datos (listado o ficha)
   ========================================================== */
$persona = null;
$fotoUrl = '';
$listado = [];
$docs = [];
$sanidadUltimo = null;
$sanidadHist = [];
$evidBySanidad = [];
$eventos = [];

try {
  if ($id <= 0) {
    $sql = "
      SELECT id, grado, arma, apellido_nombre, dni, destino_interno
      FROM personal_unidad
      WHERE unidad_id = :uid
    ";
    $params = [':uid'=>$unidadActiva];

    if ($q !== '') {
      $sql .= " AND (apellido_nombre LIKE :q OR dni LIKE :q)";
      $params[':q'] = '%' . $q . '%';
    }

    $sql .= " ORDER BY apellido_nombre ASC";

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $listado = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } else {
    $st = $pdo->prepare("SELECT * FROM personal_unidad WHERE id=:id AND unidad_id=:uid LIMIT 1");
    $st->execute([':id'=>$id, ':uid'=>$unidadActiva]);
    $persona = $st->fetch(PDO::FETCH_ASSOC);
    if (!$persona) throw new RuntimeException("No se encontró personal (ID={$id}) en la unidad activa.");

    // Docs (ignora deleted_at si existe)
    $whereDel = '';
    if (isset($colsPD['deleted_at'])) $whereDel = " AND deleted_at IS NULL ";

    $st = $pdo->prepare("
      SELECT *
      FROM personal_documentos
      WHERE unidad_id=:uid AND personal_id=:pid
        $whereDel
      ORDER BY
        CASE WHEN tipo='foto_perfil' THEN 0 ELSE 1 END ASC,
        fecha DESC, id DESC
    ");
    $st->execute([':uid'=>$unidadActiva, ':pid'=>$id]);
    $docs = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $fotoPath = '';
    foreach ($docs as $d) {
      if (($d['tipo'] ?? '') === 'foto_perfil' && !empty($d['path'])) { $fotoPath = (string)$d['path']; break; }
    }
    if ($fotoPath !== '') $fotoUrl = $BASE_APP_WEB . '/' . ltrim($fotoPath, '/');
    else $fotoUrl = is_file($FOTO_DEFAULT_ABS) ? $FOTO_DEFAULT_URL : '';

    // Sanidad: último + historial (últimos 10)
    $order = [];
    if (isset($colsSan['created_at'])) $order[] = "created_at DESC";
    $order[] = "updated_at DESC";
    $order[] = "id DESC";
    $orderBy = implode(', ', $order);

    $st = $pdo->prepare("
      SELECT *
      FROM sanidad_partes_enfermo
      WHERE unidad_id=:uid AND personal_id=:pid
      ORDER BY $orderBy
      LIMIT 1
    ");
    $st->execute([':uid'=>$unidadActiva, ':pid'=>$id]);
    $sanidadUltimo = $st->fetch(PDO::FETCH_ASSOC) ?: null;

    $st = $pdo->prepare("
      SELECT *
      FROM sanidad_partes_enfermo
      WHERE unidad_id=:uid AND personal_id=:pid
      ORDER BY $orderBy
      LIMIT 10
    ");
    $st->execute([':uid'=>$unidadActiva, ':pid'=>$id]);
    $sanidadHist = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Evidencias por sanidad_id (si existe col sanidad_id)
    if (!empty($sanidadHist) && isset($colsPD['sanidad_id'])) {
      $ids = [];
      foreach ($sanidadHist as $s) { if (!empty($s['id'])) $ids[] = (int)$s['id']; }
      $ids = array_values(array_unique(array_filter($ids)));

      if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $sql = "
          SELECT *
          FROM personal_documentos
          WHERE unidad_id=? AND personal_id=? AND sanidad_id IN ($in)
            $whereDel
          ORDER BY fecha DESC, id DESC
        ";
        $st = $pdo->prepare($sql);
        $st->execute(array_merge([$unidadActiva, $id], $ids));
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $r) {
          $sid = (int)($r['sanidad_id'] ?? 0);
          if ($sid > 0) {
            if (!isset($evidBySanidad[$sid])) $evidBySanidad[$sid] = [];
            $evidBySanidad[$sid][] = $r;
          }
        }
      }
    }

    // Eventos genéricos (si existe tabla)
    if (table_exists($pdo, 'personal_eventos')) {
      $st = $pdo->prepare("
        SELECT *
        FROM personal_eventos
        WHERE unidad_id=:uid AND personal_id=:pid
        ORDER BY COALESCE(desde,'9999-12-31') DESC, id DESC
        LIMIT 20
      ");
      $st->execute([':uid'=>$unidadActiva, ':pid'=>$id]);
      $eventos = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
  }
} catch (Throwable $ex) {
  $mensajeError = $ex->getMessage();
}

/* ========================= UI ========================= */
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Personal · Ficha</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= e($ASSETS_WEB) ?>/css/theme-602.css">
<link rel="icon" type="image/png" href="<?= e($FAVICON) ?>">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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
  .container-main{ max-width:1500px; margin:auto; }
  .panel{
    background:rgba(15,17,23,.94);
    border:1px solid rgba(148,163,184,.40);
    border-radius:18px;
    padding:18px 22px 22px;
    box-shadow:0 18px 40px rgba(0,0,0,.75), inset 0 1px 0 rgba(255,255,255,.05);
  }
  .brand-hero{ padding:10px 0; }
  .brand-hero .hero-inner{ align-items:center; display:flex; justify-content:space-between; gap:12px; }
  .header-back{
    margin-left:auto; margin-right:17px; margin-top:4px;
    display:flex; gap:8px;
  }
  .help-small{ font-size:.78rem; color:#b7c3d6; }
  .card-subpanel{
    background:rgba(15,23,42,.96);
    border-radius:14px;
    border:1px solid rgba(148,163,184,.35);
    padding:10px 12px 12px;
    margin-bottom:10px;
  }
  .section-title{ font-size:.92rem; font-weight:800; margin-bottom:6px; }
  .section-sub{ font-size:.78rem; color:#9ca3af; margin-bottom:8px; }
  .table-dark-custom{
    --bs-table-bg: rgba(15,23,42,.9);
    --bs-table-striped-bg: rgba(30,64,175,.25);
    --bs-table-border-color: rgba(148,163,184,.4);
    color:#e5e7eb;
    font-size:.82rem;
  }
  .table-dark-custom th, .table-dark-custom td{ white-space:nowrap; }

  .ficha-foto-zone{ display:flex; flex-direction:column; align-items:center; gap:.45rem; margin:12px 0 16px; }
  .ficha-foto-wrapper{
    width:190px; height:190px; border-radius:12px; overflow:hidden;
    border:1px solid rgba(148,163,184,.8);
    box-shadow:0 0 0 1px rgba(15,23,42,1), 0 8px 20px rgba(0,0,0,.7);
    background:#020617;
    display:flex; align-items:center; justify-content:center;
    position:relative;
  }
  .ficha-foto-wrapper img{ width:100%; height:100%; object-fit:cover; display:block; }
  .ficha-foto-placeholder{ font-size:.7rem; color:#9ca3af; text-align:center; padding:4px; }
  .ficha-foto-overlay{
    position:absolute; left:0; right:0; bottom:0;
    background:rgba(15,23,42,.8);
    text-align:center; font-size:.65rem; padding:2px 4px;
    color:#e5e7eb; pointer-events:none;
  }

  .tabs a{
    text-decoration:none;
  }
  .tab-pill{
    display:inline-flex; align-items:center; gap:8px;
    padding:.45rem .8rem;
    border-radius:999px;
    border:1px solid rgba(148,163,184,.35);
    background:rgba(15,23,42,.75);
    color:#e5e7eb;
    font-weight:800;
    font-size:.78rem;
  }
  .tab-pill.active{
    border-color: rgba(34,197,94,.65);
    box-shadow: 0 0 0 1px rgba(34,197,94,.25);
  }
  code { color:#dbeafe; }
</style>
</head>

<body>
<header class="brand-hero">
  <div class="hero-inner container-main">
    <div class="d-flex align-items-center gap-3">
      <img src="<?= e($ESCUDO) ?>" alt="Escudo" style="height:52px;width:auto;" onerror="this.style.display='none';">
      <div>
        <div style="font-weight:900; font-size:1.05rem;"><?= e($NOMBRE) ?></div>
        <div style="opacity:.9; color:#cbd5f5; font-size:.85rem;"><?= e($LEYENDA) ?></div>
       
      </div>
    </div>

    <div class="header-back">
      <a href="personal_lista.php" class="btn btn-success btn-sm" style="font-weight:700; padding:.35rem .9rem;">Volver</a>
      <a href="../inicio.php" class="btn btn-success btn-sm" style="font-weight:700; padding:.35rem .9rem;">Inicio</a>
    </div>
  </div>
</header>

<div class="page-wrap">
  <div class="container-main">
    <div class="panel">

      <?php if ($mensajeOk !== ''): ?>
        <div class="alert alert-success py-2"><?= e($mensajeOk) ?></div>
      <?php endif; ?>
      <?php if ($mensajeError !== ''): ?>
        <div class="alert alert-danger py-2"><?= e($mensajeError) ?></div>
      <?php endif; ?>

      <?php if ($id <= 0): ?>
        <div class="d-flex justify-content-between align-items-end flex-wrap gap-2 mb-2">
          <div>
            <div style="font-weight:900; font-size:1.05rem;">Seleccionar personal</div>
            <div class="help-small">Elegí una persona para ver su ficha.</div>
          </div>

          <form method="get" class="d-flex" style="max-width:360px;">
            <input class="form-control form-control-sm" name="q" value="<?= e($q) ?>" placeholder="Buscar por nombre o DNI...">
            <button class="btn btn-sm btn-success ms-2" type="submit">Buscar</button>
          </form>
        </div>

        <div class="table-responsive mt-3">
          <table class="table table-sm table-dark table-striped table-dark-custom align-middle">
            <thead>
              <tr>
                <th>#</th>
                <th>Grado</th>
                <th>Arma</th>
                <th>Apellido y Nombre</th>
                <th>DNI</th>
                <th>Destino interno</th>
                <th class="text-end">Acciones</th>
              </tr>
            </thead>
            <tbody>
            <?php if (empty($listado)): ?>
              <tr><td colspan="7" class="text-center text-muted py-4">No hay registros.</td></tr>
            <?php else: ?>
              <?php foreach ($listado as $i => $p): ?>
                <tr>
                  <td><?= e($i+1) ?></td>
                  <td><?= e($p['grado'] ?? '') ?></td>
                  <td><?= e($p['arma'] ?? '') ?></td>
                  <td><?= e($p['apellido_nombre'] ?? '') ?></td>
                  <td><?= e($p['dni'] ?? '') ?></td>
                  <td><?= e($p['destino_interno'] ?? '') ?></td>
                  <td class="text-end">
                    <a class="btn btn-sm btn-outline-info"
                       href="personal_ficha.php?id=<?= e($p['id']) ?>&tab=ficha">Ver ficha</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
          </table>
        </div>

      <?php else: ?>
        <?php
          $linea = trim(($persona['grado'] ?? '') . ' ' . ($persona['arma'] ?? '') . ' ' . ($persona['apellido_nombre'] ?? ''));
          $tieneParte = ((int)($persona['tiene_parte_enfermo'] ?? 0) === 1);
          $ini  = $persona['parte_enfermo_desde'] ?? null;
          $fin  = $persona['parte_enfermo_hasta'] ?? null;
          $cant = (int)($persona['cantidad_parte_enfermo'] ?? 0);
        ?>

        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
          <div>
            <div style="font-weight:900; font-size:1.1rem;">Ficha de personal</div>
            <div class="help-small">
              <?= e($linea) ?> · DNI: <b><?= e($persona['dni'] ?? '') ?></b> · Destino: <b><?= e($persona['destino_interno'] ?? '') ?></b>
            </div>
          </div>
        </div>

        <div class="tabs d-flex flex-wrap gap-2 my-2">
          <a class="tab-pill <?= $tab==='ficha'?'active':'' ?>" href="personal_ficha.php?id=<?= (int)$id ?>&tab=ficha">Datos</a>
          <a class="tab-pill <?= $tab==='sanidad'?'active':'' ?>" href="personal_ficha.php?id=<?= (int)$id ?>&tab=sanidad">Sanidad</a>
          <a class="tab-pill <?= $tab==='docs'?'active':'' ?>" href="personal_ficha.php?id=<?= (int)$id ?>&tab=docs">Documentos</a>
          <?php if (table_exists($pdo,'personal_eventos')): ?>
            <a class="tab-pill <?= $tab==='eventos'?'active':'' ?>" href="personal_ficha.php?id=<?= (int)$id ?>&tab=eventos">Eventos</a>
          <?php endif; ?>
        </div>

        <div class="ficha-foto-zone">
          <div class="ficha-foto-wrapper">
            <?php if ($fotoUrl): ?>
              <img src="<?= e($fotoUrl) ?>" alt="Foto">
              <div class="ficha-foto-overlay">Foto 4x4</div>
            <?php else: ?>
              <div class="ficha-foto-placeholder">Sin foto</div>
            <?php endif; ?>
          </div>

          <?php if ($esAdmin): ?>
            <form method="post" enctype="multipart/form-data" class="text-center">
              <?php csrf_if_exists(); ?>
              <input type="hidden" name="accion" value="subir_documento">
              <input type="hidden" name="personal_id" value="<?= (int)$id ?>">
              <input type="hidden" name="tipo" value="foto_perfil">
              <input type="hidden" name="titulo" value="Foto 4x4">
              <input type="hidden" name="nota" value="">
              <input type="hidden" name="fecha" value="<?= e(date('Y-m-d')) ?>">
              <input type="file" name="archivo" class="form-control form-control-sm mb-2" accept="image/*" required style="max-width:340px; margin:0 auto;">
              <button class="btn btn-sm btn-outline-success" type="submit">Actualizar foto</button>
            </form>
          <?php endif; ?>
        </div>

        <?php if ($tab === 'ficha'): ?>
          <div class="card-subpanel">
            <div class="section-title">Datos personales</div>

            <?php if ($esAdmin): ?>
              <form method="post" class="row g-2">
                <?php csrf_if_exists(); ?>
                <input type="hidden" name="accion" value="guardar_personal">
                <input type="hidden" name="personal_id" value="<?= (int)$id ?>">

                <div class="col-md-3">
                  <label class="form-label form-label-sm">Grado</label>
                  <input class="form-control form-control-sm" name="grado" value="<?= e($persona['grado'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                  <label class="form-label form-label-sm">Arma</label>
                  <input class="form-control form-control-sm" name="arma" value="<?= e($persona['arma'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label form-label-sm">Apellido y Nombre *</label>
                  <input class="form-control form-control-sm" name="apellido_nombre" required value="<?= e($persona['apellido_nombre'] ?? '') ?>">
                </div>

                <div class="col-md-3">
                  <label class="form-label form-label-sm">DNI *</label>
                  <input class="form-control form-control-sm" name="dni" required value="<?= e($persona['dni'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                  <label class="form-label form-label-sm">CUIL</label>
                  <input class="form-control form-control-sm" name="cuil" value="<?= e($persona['cuil'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                  <label class="form-label form-label-sm">Fecha nac</label>
                  <input type="date" class="form-control form-control-sm" name="fecha_nac" value="<?= e($persona['fecha_nac'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                  <label class="form-label form-label-sm">Sexo</label>
                  <input class="form-control form-control-sm" name="sexo" value="<?= e($persona['sexo'] ?? '') ?>">
                </div>

                <div class="col-md-8">
                  <label class="form-label form-label-sm">Domicilio</label>
                  <input class="form-control form-control-sm" name="domicilio" value="<?= e($persona['domicilio'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label form-label-sm">Estado civil</label>
                  <input class="form-control form-control-sm" name="estado_civil" value="<?= e($persona['estado_civil'] ?? '') ?>">
                </div>

                <div class="col-md-2">
                  <label class="form-label form-label-sm">Hijos</label>
                  <input type="number" class="form-control form-control-sm" name="hijos" value="<?= e($persona['hijos'] ?? '') ?>">
                </div>
                <div class="col-md-5">
                  <label class="form-label form-label-sm">Destino interno</label>
                  <input class="form-control form-control-sm" name="destino_interno" value="<?= e($persona['destino_interno'] ?? '') ?>">
                </div>
                <div class="col-md-5">
                  <label class="form-label form-label-sm">Función</label>
                  <input class="form-control form-control-sm" name="funcion" value="<?= e($persona['funcion'] ?? '') ?>">
                </div>

                <div class="col-md-4">
                  <label class="form-label form-label-sm">Teléfono</label>
                  <input class="form-control form-control-sm" name="telefono" value="<?= e($persona['telefono'] ?? '') ?>">
                </div>
                <div class="col-md-8">
                  <label class="form-label form-label-sm">Correo</label>
                  <input class="form-control form-control-sm" name="correo" value="<?= e($persona['correo'] ?? '') ?>">
                </div>

                <div class="col-12">
                  <label class="form-label form-label-sm">Observaciones</label>
                  <textarea class="form-control form-control-sm" name="observaciones" rows="3"><?= e($persona['observaciones'] ?? '') ?></textarea>
                </div>

                <div class="col-12 text-end">
                  <button class="btn btn-sm btn-success" style="font-weight:800;">Guardar datos</button>
                </div>
              </form>
            <?php else: ?>
              <div class="help-small">Sin permisos de edición (solo lectura).</div>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <?php if ($tab === 'sanidad'): ?>
          <div class="card-subpanel">
            <div class="section-title">Sanidad · Estado canónico</div>
            <div class="section-sub">
              Estado: <?= $tieneParte ? '<span class="badge bg-warning text-dark">TIENE PARTE</span>' : '<span class="badge bg-success">SIN PARTE</span>' ?>
              &nbsp; | &nbsp; Inicio: <b><?= e(fmt_date($ini ? (string)$ini : null)) ?></b>
              &nbsp; | &nbsp; Fin: <b><?= e(fmt_date($fin ? (string)$fin : null)) ?></b>
              &nbsp; | &nbsp; Cant: <b><?= e((string)$cant) ?></b>
            </div>

            <?php if ($esAdmin): ?>
              <form method="post" enctype="multipart/form-data" class="row g-2">
                <?php csrf_if_exists(); ?>
                <input type="hidden" name="accion" value="guardar_sanidad">
                <input type="hidden" name="personal_id" value="<?= (int)$id ?>">

                <div class="col-md-4">
                  <label class="form-label form-label-sm">Acción</label>
                  <select class="form-select form-select-sm" name="tiene_parte">
                    <option value="si">Parte de enfermo</option>
                    <option value="no">Alta de parte de enfermo</option>
                  </select>
                  <div class="help-small mt-1">Sin evidencia: solo actualiza/ajusta el último evento.</div>
                </div>

                <div class="col-md-4">
                  <label class="form-label form-label-sm">Inicio</label>
                  <input type="date" class="form-control form-control-sm" name="inicio">
                </div>
                <div class="col-md-4">
                  <label class="form-label form-label-sm">Fin</label>
                  <input type="date" class="form-control form-control-sm" name="fin">
                </div>

                <div class="col-12">
                  <label class="form-label form-label-sm">Observaciones</label>
                  <input class="form-control form-control-sm" name="observaciones_sanidad" placeholder="Breve detalle...">
                </div>

                <div class="col-12">
                  <label class="form-label form-label-sm">Evidencia (PDF / Word / Imagen)</label>
                  <input type="file" class="form-control form-control-sm" name="sanidad_evidencias[]" multiple
                         accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx">
                  
                </div>

                <div class="col-12 text-end">
                  <button class="btn btn-sm btn-outline-success">Guardar sanidad</button>
                </div>
              </form>
            <?php else: ?>
              <div class="help-small">Sin permisos para modificar sanidad.</div>
            <?php endif; ?>
          </div>

          <div class="card-subpanel">
            <div class="section-title">Sanidad · Historial (últimos 10)</div>

            <?php if (!$sanidadHist): ?>
              <div class="text-muted py-2">Sin historial.</div>
            <?php else: ?>
              <div class="accordion" id="accSanidad">
                <?php foreach ($sanidadHist as $idx => $s): ?>
                  <?php
                    $sid = (int)($s['id'] ?? 0);
                    $ev = null;
                    if (isset($colsSan['evento']) && !empty($s['evento'])) $ev = (string)$s['evento'];
                    else $ev = ((string)($s['tiene_parte'] ?? 'no') === 'si') ? 'parte' : 'alta';

                    $badge = ($ev === 'parte')
                      ? '<span class="badge bg-warning text-dark">PARTE</span>'
                      : '<span class="badge bg-info text-dark">ALTA</span>';

                    $iniS = !empty($s['inicio']) ? (string)$s['inicio'] : null;
                    $finS = !empty($s['fin']) ? (string)$s['fin'] : null;
                    $cantS = (int)($s['cantidad'] ?? 0);
                    $obsS = (string)($s['observaciones'] ?? '');
                    $evids = $evidBySanidad[$sid] ?? [];
                  ?>
                  <div class="accordion-item" style="background:rgba(15,23,42,.85); border:1px solid rgba(148,163,184,.25);">
                    <h2 class="accordion-header" id="h<?= $sid ?>">
                      <button class="accordion-button collapsed" type="button"
                              data-bs-toggle="collapse" data-bs-target="#c<?= $sid ?>"
                              style="background:rgba(15,23,42,.92); color:#e5e7eb;">
                        <?= $badge ?>&nbsp;
                        <span style="font-weight:900;">
                          <?= e(fmt_date($iniS)) ?> → <?= e(fmt_date($finS)) ?>
                        </span>
                        &nbsp;<span class="help-small">· Cant: <?= e((string)$cantS) ?> · ID: <?= (int)$sid ?></span>
                      </button>
                    </h2>
                    <div id="c<?= $sid ?>" class="accordion-collapse collapse" data-bs-parent="#accSanidad">
                      <div class="accordion-body" style="color:#e5e7eb;">
                        <?php if ($obsS !== ''): ?>
                          <div class="help-small mb-2"><b>Obs:</b> <?= e($obsS) ?></div>
                        <?php endif; ?>

                        <div class="help-small mb-2"><b>Evidencias:</b> <?= count($evids) ?></div>

                        <?php if (!$evids): ?>
                          <div class="text-muted">Sin evidencias vinculadas.</div>
                        <?php else: ?>
                          <div class="table-responsive">
                            <table class="table table-sm table-dark table-striped table-dark-custom align-middle">
                              <thead>
                                <tr>
                                  <th>Tipo</th>
                                  <th>Título</th>
                                  <th>Fecha</th>
                                  <th>Tamaño</th>
                                  <th>Archivo</th>
                                  <th class="text-end">Acciones</th>
                                </tr>
                              </thead>
                              <tbody>
                                <?php foreach ($evids as $d): ?>
                                  <?php
                                    $path = (string)($d['path'] ?? '');
                                    $url  = $path !== '' ? ($BASE_APP_WEB . '/' . ltrim($path, '/')) : '';
                                    $fechaDoc = !empty($d['fecha']) ? date('d/m/Y', strtotime((string)$d['fecha'])) : '—';
                                    $titulo = (string)($d['titulo'] ?? '');
                                    if ($titulo === '') $titulo = '(sin título)';
                                    $bytes = isset($d['bytes']) ? (int)$d['bytes'] : null;
                                  ?>
                                  <tr>
                                    <td><?= e($d['tipo'] ?? '') ?></td>
                                    <td><?= e($titulo) ?></td>
                                    <td><?= e($fechaDoc) ?></td>
                                    <td><?= e(fmt_bytes($bytes)) ?></td>
                                    <td>
                                      <?php if ($url): ?>
                                        <a class="btn btn-sm btn-outline-light py-0 px-2" href="<?= e($url) ?>" target="_blank">Ver</a>
                                      <?php else: ?>
                                        <span class="text-muted">—</span>
                                      <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                      <?php if ($esAdmin): ?>
                                        <form method="post" class="d-inline form-del-doc">
                                          <?php csrf_if_exists(); ?>
                                          <input type="hidden" name="accion" value="eliminar_documento">
                                          <input type="hidden" name="personal_id" value="<?= (int)$id ?>">
                                          <input type="hidden" name="doc_id" value="<?= (int)($d['id'] ?? 0) ?>">
                                          <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 btn-del">Eliminar</button>
                                        </form>
                                      <?php else: ?>
                                        <span class="text-muted">—</span>
                                      <?php endif; ?>
                                    </td>
                                  </tr>
                                <?php endforeach; ?>
                              </tbody>
                            </table>
                          </div>
                        <?php endif; ?>

                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <?php if ($tab === 'docs'): ?>
          <div class="card-subpanel">
            <div class="section-title">Documentos del personal</div>

            <?php if ($esAdmin): ?>
              <form method="post" enctype="multipart/form-data" class="row g-2 mb-3">
                <?php csrf_if_exists(); ?>
                <input type="hidden" name="accion" value="subir_documento">
                <input type="hidden" name="personal_id" value="<?= (int)$id ?>">

                <div class="col-md-4">
                  <label class="form-label form-label-sm">Tipo</label>
                  <select class="form-select form-select-sm" name="tipo">
                    <option value="anexo27">anexo27</option>
                    <option value="administrativo">administrativo</option>
                    <option value="otros" selected>otros</option>
                  </select>
                </div>

                <div class="col-md-8">
                  <label class="form-label form-label-sm">Título</label>
                  <input class="form-control form-control-sm" name="titulo" placeholder="Ej: Certificado / Nota / Anexo...">
                </div>

                <div class="col-md-4">
                  <label class="form-label form-label-sm">Fecha</label>
                  <input type="date" class="form-control form-control-sm" name="fecha">
                </div>

                <div class="col-md-8">
                  <label class="form-label form-label-sm">Nota</label>
                  <input class="form-control form-control-sm" name="nota" placeholder="Observación breve...">
                </div>

                <?php if (isset($colsPD['evento_id']) && table_exists($pdo,'personal_eventos')): ?>
                  <div class="col-12">
                    <label class="form-label form-label-sm">Vincular a evento (opcional)</label>
                    <select class="form-select form-select-sm" name="evento_id">
                      <option value="0" selected>(sin evento)</option>
                      <?php foreach ($eventos as $ev): ?>
                        <option value="<?= (int)$ev['id'] ?>">
                          <?= e(($ev['tipo'] ?? 'evento') . ' · ' . fmt_date($ev['desde'] ?? null) . '→' . fmt_date($ev['hasta'] ?? null) . ' · ' . ($ev['titulo'] ?? '')) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <div class="help-small">Esto usa <code>personal_documentos.evento_id</code>.</div>
                  </div>
                <?php endif; ?>

                <div class="col-12">
                  <label class="form-label form-label-sm">Archivo (PDF/Word/Imagen)</label>
                  <input type="file" class="form-control form-control-sm" name="archivo" required
                         accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx">
                </div>

                <div class="col-12 text-end">
                  <button class="btn btn-sm btn-outline-success">Subir documento</button>
                </div>
              </form>
            <?php endif; ?>

            <div class="table-responsive">
              <table class="table table-sm table-dark table-striped table-dark-custom align-middle">
                <thead>
                  <tr>
                    <th>Tipo</th>
                    <th>Título</th>
                    <th>Fecha</th>
                    <th>Tamaño</th>
                    <th>Archivo</th>
                    <th class="text-end">Acciones</th>
                  </tr>
                </thead>
                <tbody>
                <?php if (!$docs): ?>
                  <tr><td colspan="6" class="text-center text-muted py-3">No hay documentos.</td></tr>
                <?php else: ?>
                  <?php foreach ($docs as $d): ?>
                    <?php
                      $path = (string)($d['path'] ?? '');
                      $url  = $path !== '' ? ($BASE_APP_WEB . '/' . ltrim($path, '/')) : '';
                      $fechaDoc = !empty($d['fecha']) ? date('d/m/Y', strtotime((string)$d['fecha'])) : '—';
                      $titulo = (string)($d['titulo'] ?? '');
                      if ($titulo === '') $titulo = '(sin título)';
                      $bytes = isset($d['bytes']) ? (int)$d['bytes'] : null;
                    ?>
                    <tr>
                      <td><?= e($d['tipo'] ?? '') ?></td>
                      <td>
                        <?= e($titulo) ?>
                        <?php if (!empty($d['nota'])): ?>
                          <div class="help-small"><?= e((string)$d['nota']) ?></div>
                        <?php endif; ?>
                        <?php if (isset($d['sanidad_id']) && (int)$d['sanidad_id'] > 0): ?>
                          <div class="help-small">Sanidad ID: <b><?= (int)$d['sanidad_id'] ?></b></div>
                        <?php endif; ?>
                        <?php if (isset($d['evento_id']) && (int)$d['evento_id'] > 0): ?>
                          <div class="help-small">Evento ID: <b><?= (int)$d['evento_id'] ?></b></div>
                        <?php endif; ?>
                      </td>
                      <td><?= e($fechaDoc) ?></td>
                      <td><?= e(fmt_bytes($bytes)) ?></td>
                      <td>
                        <?php if ($url): ?>
                          <a class="btn btn-sm btn-outline-light py-0 px-2" href="<?= e($url) ?>" target="_blank">Ver</a>
                        <?php else: ?>
                          <span class="text-muted">—</span>
                        <?php endif; ?>
                      </td>
                      <td class="text-end">
                        <?php if ($esAdmin): ?>
                          <form method="post" class="d-inline form-del-doc">
                            <?php csrf_if_exists(); ?>
                            <input type="hidden" name="accion" value="eliminar_documento">
                            <input type="hidden" name="personal_id" value="<?= (int)$id ?>">
                            <input type="hidden" name="doc_id" value="<?= (int)($d['id'] ?? 0) ?>">
                            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 btn-del">Eliminar</button>
                          </form>
                        <?php else: ?>
                          <span class="text-muted">—</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endif; ?>

        <?php if ($tab === 'eventos' && table_exists($pdo,'personal_eventos')): ?>
          <div class="card-subpanel">
            <div class="section-title">Eventos del personal</div>
            <div class="section-sub">
              Ejemplos: <code>vacaciones</code>, <code>plan_llamada</code>, <code>licencia</code>, <code>comision</code>. Documentos pueden vincularse por <code>evento_id</code>.
            </div>

            <?php if ($esAdmin): ?>
              <form method="post" class="row g-2 mb-3">
                <?php csrf_if_exists(); ?>
                <input type="hidden" name="accion" value="crear_evento">
                <input type="hidden" name="personal_id" value="<?= (int)$id ?>">

                <div class="col-md-3">
                  <label class="form-label form-label-sm">Tipo *</label>
                  <input class="form-control form-control-sm" name="ev_tipo" placeholder="vacaciones / plan_llamada ..." required>
                </div>
                <div class="col-md-3">
                  <label class="form-label form-label-sm">Desde</label>
                  <input type="date" class="form-control form-control-sm" name="ev_desde">
                </div>
                <div class="col-md-3">
                  <label class="form-label form-label-sm">Hasta</label>
                  <input type="date" class="form-control form-control-sm" name="ev_hasta">
                </div>
                <div class="col-md-3">
                  <label class="form-label form-label-sm">Estado</label>
                  <input class="form-control form-control-sm" name="ev_estado" placeholder="pendiente/aprobado/...">
                </div>

                <div class="col-md-6">
                  <label class="form-label form-label-sm">Título</label>
                  <input class="form-control form-control-sm" name="ev_titulo" placeholder="Breve...">
                </div>
                <div class="col-md-6">
                  <label class="form-label form-label-sm">Descripción</label>
                  <input class="form-control form-control-sm" name="ev_desc" placeholder="Detalle...">
                </div>

                <?php if (!empty($colsPE['data_json'])): ?>
                  <div class="col-12">
                    <label class="form-label form-label-sm">Datos extra (JSON opcional)</label>
                    <textarea class="form-control form-control-sm" name="ev_json" rows="2" placeholder='{"frecuencia":"semanal","canal":"telefono"}'></textarea>
                    <div class="help-small">Debe ser JSON válido.</div>
                  </div>
                <?php endif; ?>

                <div class="col-12 text-end">
                  <button class="btn btn-sm btn-outline-success">Crear evento</button>
                </div>
              </form>
            <?php endif; ?>

            <div class="table-responsive">
              <table class="table table-sm table-dark table-striped table-dark-custom align-middle">
                <thead>
                  <tr>
                    <th>Tipo</th>
                    <th>Fechas</th>
                    <th>Estado</th>
                    <th>Título</th>
                    <th class="text-end">Acciones</th>
                  </tr>
                </thead>
                <tbody>
                <?php if (!$eventos): ?>
                  <tr><td colspan="5" class="text-center text-muted py-3">Sin eventos.</td></tr>
                <?php else: ?>
                  <?php foreach ($eventos as $ev): ?>
                    <tr>
                      <td><?= e($ev['tipo'] ?? '') ?></td>
                      <td><?= e(fmt_date($ev['desde'] ?? null)) ?> → <?= e(fmt_date($ev['hasta'] ?? null)) ?></td>
                      <td><?= e($ev['estado'] ?? '') ?></td>
                      <td>
                        <?= e($ev['titulo'] ?? '') ?>
                        <?php if (!empty($ev['descripcion'])): ?>
                          <div class="help-small"><?= e($ev['descripcion']) ?></div>
                        <?php endif; ?>
                      </td>
                      <td class="text-end">
                        <?php if ($esAdmin): ?>
                          <form method="post" class="d-inline form-del-ev">
                            <?php csrf_if_exists(); ?>
                            <input type="hidden" name="accion" value="eliminar_evento">
                            <input type="hidden" name="personal_id" value="<?= (int)$id ?>">
                            <input type="hidden" name="evento_id" value="<?= (int)($ev['id'] ?? 0) ?>">
                            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 btn-del-ev">Eliminar</button>
                          </form>
                        <?php else: ?>
                          <span class="text-muted">—</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endif; ?>

      <?php endif; ?>

    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.form-del-doc .btn-del').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const form = btn.closest('form');
      if (!form) return;

      Swal.fire({
        title: '¿Eliminar documento?',
        text: 'Esta acción no se puede deshacer.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d'
      }).then(r => { if (r.isConfirmed) form.submit(); });
    });
  });

  document.querySelectorAll('.form-del-ev .btn-del-ev').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const form = btn.closest('form');
      if (!form) return;

      Swal.fire({
        title: '¿Eliminar evento?',
        text: 'Se desvincularán documentos asociados (si los hay).',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d'
      }).then(r => { if (r.isConfirmed) form.submit(); });
    });
  });
});
</script>
</body>
</html>
