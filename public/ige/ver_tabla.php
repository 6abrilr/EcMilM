<?php
/* public/ige/ver_tabla.php — Editor XLSX/CSV (EC MIL M) con paginación, criticidad, evidencia y auto-guardado */
declare(strict_types=1);

require_once __DIR__ . '/../../auth/bootstrap.php';
require_login();
require_once __DIR__ . '/../../config/db.php';

/** @var PDO $pdo */

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function starts_with($h,$n){ return substr($h,0,strlen($n)) === $n; }
function norm($s){ return preg_replace('/\s+/u','',mb_strtoupper(trim((string)$s),'UTF-8')); }

/* ===== Assets (igual a lista_de_control.php) ===== */
$ASSETS_URL = '../../assets';              // desde /ea/public/ige
$IMG_BG     = $ASSETS_URL . '/img/fondo.png';
$ESCUDO     = $ASSETS_URL . '/img/ecmilm.png';
$FAVICON    = $ASSETS_URL . '/img/ecmilm.png';

$user = function_exists('current_user') ? current_user() : null;

/* ===== Parámetros ===== */
$rel            = (string)($_GET['p'] ?? '');
$sheetIdx       = isset($_GET['s']) ? max(0,(int)$_GET['s']) : 0;
$debugShowColor = isset($_GET['showcolor']);
$areaParam      = (string)($_GET['area'] ?? '');
$savedFlag      = ((string)($_GET['saved'] ?? '')) === '1';

/* Paginación */
$allowedPP = [10,20,30,50,100];
$perPage = (int)($_GET['pp'] ?? 20);
if(!in_array($perPage,$allowedPP,true)) $perPage = 20;
$page = max(1,(int)($_GET['page'] ?? 1));

/* ===== Validación básica del parámetro p (anti traversal) ===== */
$rel = str_replace('\\','/', $rel);
$rel = ltrim($rel, '/');

if ($rel === '' || strpos($rel, "\0") !== false || strpos($rel, '../') !== false || strpos($rel, '..\\') !== false) {
  http_response_code(400);
  echo "Ruta inválida";
  exit;
}

/* ===== Paths base (FS) =====
   __DIR__ = /ea/public/ige
   projectBase debe ser /ea
*/
$projectBase = realpath(__DIR__ . '/../..'); // ✅ /ea
if(!$projectBase){
  http_response_code(500);
  echo "No se pudo resolver projectBase";
  exit;
}
$projectBase = rtrim(str_replace('\\','/',$projectBase), '/');

$abs = realpath($projectBase . '/' . $rel);
if(!$abs || !is_file($abs)){
  http_response_code(400);
  echo "Ruta inválida";
  exit;
}
$abs = str_replace('\\','/',$abs);

/* ===== Rutas permitidas (compat + ruta nueva por unidad) ===== */
const UNIT_SLUG = 'ecmilm';

$roots = [
  // NUEVO (tu ruta actual)
  'ige_unidad'               => realpath($projectBase . '/storage/unidades/' . UNIT_SLUG . '/ige'),

  // COMPAT (si todavía existen)
  'listas_control'           => realpath($projectBase . '/storage/listas_control'),
  'ultima_inspeccion'        => realpath($projectBase . '/storage/ultima_inspeccion'),
  'visitas_de_estado_mayor'  => realpath($projectBase . '/storage/visitas_de_estado_mayor'),
];

$inScope = null;
foreach($roots as $slug=>$root){
  if(!$root) continue;
  $rootN = rtrim(str_replace('\\','/',$root), '/');
  if(starts_with($abs, $rootN)){
    $inScope = $slug;
    break;
  }
}
if(!$inScope){
  http_response_code(400);
  echo "Ruta fuera de las carpetas permitidas";
  exit;
}

$scopeMeta = [
  'ige_unidad' => [
    'label'     => 'Listas de control (IGE)',
    'list_url'  => 'lista_de_control.php',
    'dash_scope'=> 'lista_de_control',
  ],
  'listas_control' => [
    'label'     => 'Lista de control',
    'list_url'  => 'lista_de_control.php',
    'dash_scope'=> 'lista_de_control',
  ],
  'ultima_inspeccion' => [
    'label'     => 'Última inspección',
    'list_url'  => 'ultima_inspeccion.php',
    'dash_scope'=> 'ultima_inspeccion',
  ],
  'visitas_de_estado_mayor' => [
    'label'     => 'Visitas de Estado Mayor',
    'list_url'  => 'visitas_de_estado_mayor.php',
    'dash_scope'=> 'visitas_de_estado_mayor',
  ],
];

$SCOPE = $scopeMeta[$inScope];

/* Tooltip de Acción Correctiva (si existe col 3) */
$hasAccionTooltip = in_array($inScope, ['visitas_de_estado_mayor','listas_control','ultima_inspeccion','ige_unidad'], true);

/* =========================================================
   ✅ FIX DB PREFS: asegurar columnas aunque la tabla exista vieja
========================================================= */
function table_has_column(PDO $pdo, string $table, string $col): bool {
  try{
    $st = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    $st->execute([$col]);
    return (bool)$st->fetch(PDO::FETCH_ASSOC);
  }catch(Throwable $e){
    return false;
  }
}

function ensure_xlsx_prefs(PDO $pdo): void {
  // crea si no existe
  $pdo->exec("CREATE TABLE IF NOT EXISTS xlsx_prefs (
    file_rel VARCHAR(512) PRIMARY KEY,
    mode_num_is ENUM('title','item') NOT NULL DEFAULT 'item',
    table_fmt  ENUM('classic','form') NOT NULL DEFAULT 'classic',
    updated_at TIMESTAMP NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  // si existe vieja, agrega columnas faltantes
  if(!table_has_column($pdo, 'xlsx_prefs', 'mode_num_is')){
    try { $pdo->exec("ALTER TABLE xlsx_prefs ADD COLUMN mode_num_is ENUM('title','item') NOT NULL DEFAULT 'item'"); } catch(Throwable $e){}
  }
  if(!table_has_column($pdo, 'xlsx_prefs', 'table_fmt')){
    try { $pdo->exec("ALTER TABLE xlsx_prefs ADD COLUMN table_fmt ENUM('classic','form') NOT NULL DEFAULT 'classic'"); } catch(Throwable $e){}
  }
  if(!table_has_column($pdo, 'xlsx_prefs', 'updated_at')){
    try { $pdo->exec("ALTER TABLE xlsx_prefs ADD COLUMN updated_at TIMESTAMP NULL"); } catch(Throwable $e){}
  }
}

ensure_xlsx_prefs($pdo);

/* ===== Preferencias por archivo (modo + formato) ===== */
if(isset($_GET['setmode'])){
  ensure_xlsx_prefs($pdo);
  $modeSet = (($_GET['setmode'] ?? '') === 'item') ? 'item' : 'title';

  $up = $pdo->prepare("INSERT INTO xlsx_prefs(file_rel,mode_num_is,updated_at)
                       VALUES(?,?,NOW())
                       ON DUPLICATE KEY UPDATE mode_num_is=VALUES(mode_num_is), updated_at=NOW()");
  $up->execute([$rel,$modeSet]);

  $qs = 'p='.rawurlencode($rel).'&s='.$sheetIdx.($debugShowColor?'&showcolor=1':'')."&pp=$perPage&page=$page";
  if(isset($_GET['fmt'])) $qs .= '&fmt='.rawurlencode((string)$_GET['fmt']);
  if($areaParam !== '') $qs .= '&area='.rawurlencode($areaParam);
  header("Location: ver_tabla.php?".$qs);
  exit;
}

if(isset($_GET['setfmt'])){
  ensure_xlsx_prefs($pdo);
  $fmtSet = (($_GET['setfmt'] ?? '') === 'form') ? 'form' : 'classic';

  $up = $pdo->prepare("INSERT INTO xlsx_prefs(file_rel,table_fmt,updated_at)
                       VALUES(?,?,NOW())
                       ON DUPLICATE KEY UPDATE table_fmt=VALUES(table_fmt), updated_at=NOW()");
  $up->execute([$rel,$fmtSet]);

  $qs = 'p='.rawurlencode($rel).'&s='.$sheetIdx.($debugShowColor?'&showcolor=1':'')."&pp=$perPage&page=$page";
  if($areaParam !== '') $qs .= '&area='.rawurlencode($areaParam);
  header("Location: ver_tabla.php?".$qs);
  exit;
}

/* Leer prefs sin romper si la tabla venía vieja */
$pref = ['mode_num_is'=>'item','table_fmt'=>'classic'];
try{
  ensure_xlsx_prefs($pdo);
  $stM = $pdo->prepare("SELECT mode_num_is, table_fmt FROM xlsx_prefs WHERE file_rel=?");
  $stM->execute([$rel]);
  $row = $stM->fetch(PDO::FETCH_ASSOC);
  if(is_array($row)) $pref = array_merge($pref, $row);
}catch(Throwable $e){
  // fallback silencioso
}

$mode     = $pref['mode_num_is'] ?? 'item';
$tableFmt = $pref['table_fmt']  ?? 'classic';

/* ===== PhpSpreadsheet ===== */
$autoload = $projectBase . '/vendor/autoload.php';
$ssAvail  = is_file($autoload);

$ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
if($ext==='xlsx' && !$ssAvail){
  $errFatal = "No encuentro PhpSpreadsheet (vendor/autoload.php). Instálalo con Composer.";
}

/* ===== Lectores ===== */
function read_csv_all($file){
  $rows=[]; $fh=@fopen($file,'r'); if(!$fh) return [$rows, ['CSV'], [], null];
  $first=fgets($fh); if($first===false){ fclose($fh); return [$rows, ['CSV'], [], null]; }
  $sep=(substr_count($first,';')>substr_count($first,','))?';':',';
  rewind($fh);
  while(($d=fgetcsv($fh,0,$sep))!==false){ $rows[] = array_map(fn($v)=>trim((string)$v),$d); }
  fclose($fh);
  $meta = array_fill(0, max(0,count($rows)-1), false);
  return [$rows, ['CSV'], $meta, null];
}

if(!isset($errFatal) && $ext==='xlsx'){
  require_once $autoload;

  function coord($c,$r){
    return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c).$r;
  }
  function cell_text($cell){
    if(!$cell) return '';
    $v = $cell->getValue();
    if($v instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText){
      return trim((string)$v->getPlainText());
    }
    $calc = $cell->getCalculatedValue();
    if(is_scalar($calc) && $calc!=='') return trim((string)$calc);
    return trim((string)$cell->getFormattedValue());
  }
  function sheet_used_bounds($sh){
    $maxR=$sh->getHighestRow();
    $maxC=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sh->getHighestColumn());
    $minR=$maxR; $minC=$maxC; $found=false;

    for($r=1;$r<=$maxR;$r++){
      for($c=1;$c<=$maxC;$c++){
        if(trim(cell_text($sh->getCell(coord($c,$r))))!==''){
          $found=true;
          if($r<$minR)$minR=$r;
          if($c<$minC)$minC=$c;
        }
      }
    }
    if(!$found) return [1,1,0,0];

    while($maxR>=$minR){
      $empty=true;
      for($c=$minC;$c<=$maxC;$c++){
        if(trim(cell_text($sh->getCell(coord($c,$maxR))))!==''){ $empty=false; break; }
      }
      if($empty) $maxR--; else break;
    }
    while($maxC>=$minC){
      $empty=true;
      for($r=$minR;$r<=$maxR;$r++){
        if(trim(cell_text($sh->getCell(coord($maxC,$r))))!==''){ $empty=false; break; }
      }
      if($empty) $maxC--; else break;
    }
    return [$minR,$minC,$maxR,$maxC];
  }
  function cell_has_fill($sh,$c,$r){
    $style = $sh->getStyle(coord($c,$r)); if(!$style) return false;
    $fill  = $style->getFill(); if(!$fill) return false;
    if($fill->getFillType() !== \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID) return false;
    $rgb = strtoupper($fill->getStartColor()->getRGB() ?: '');
    return !($rgb==='' || $rgb==='FFFFFF');
  }
  function expand_merged_values($sh,&$grid,$minR,$minC){
    foreach($sh->getMergeCells() as $range){
      [$tl,$br]=explode(':',$range);
      $tlC=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString(preg_replace('/\d+/','',$tl));
      $tlR=(int)preg_replace('/\D+/','',$tl);
      $brC=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString(preg_replace('/\d+/','',$br));
      $brR=(int)preg_replace('/\D+/','',$br);
      $val=cell_text($sh->getCell($tl));
      for($r=$tlR;$r<=$brR;$r++){
        for($c=$tlC;$c<=$brC;$c++){
          $ri=$r-$minR; $ci=$c-$minC;
          if(!isset($grid[$ri][$ci]) || $grid[$ri][$ci]==='') $grid[$ri][$ci]=$val;
        }
      }
    }
  }
  function read_xlsx_all($file,$sheetIdx=0,&$sheetNames=[],&$err=null){
    try{
      $reader=new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
      $ss=$reader->load($file);
      $sheetNames=[];
      $count=$ss->getSheetCount();
      for($i=0;$i<$count;$i++){ $sheetNames[]=$ss->getSheet($i)->getTitle(); }
      if($sheetIdx>=$count) $sheetIdx=0;

      $sh=$ss->getSheet($sheetIdx);
      [$minR,$minC,$maxR,$maxC]=sheet_used_bounds($sh);
      if($maxR<$minR||$maxC<$minC) return [[],[],[], null];

      $rows=[]; $rowFill=[];
      for($r=$minR;$r<=$maxR;$r++){
        $line=[]; $hasFill=false;
        for($c=1;$c<=$maxC;$c++){
          $line[] = cell_text($sh->getCell(coord($c,$r)));
          if(!$hasFill && cell_has_fill($sh,$c,$r)) $hasFill=true;
        }
        $rows[]=$line; $rowFill[]=$hasFill;
      }
      expand_merged_values($sh,$rows,$minR,$minC);
      return [$rows,$rowFill,$sheetNames,null];
    }catch(Throwable $e){
      $err=$e->getMessage();
      return [[],[],[], $e->getMessage()];
    }
  }
}

/* ===== CARGA ===== */
$rows=[]; $rowFill=[]; $err=null; $sheetNames=['Hoja'];
if(isset($errFatal)){ $rows=[]; $rowFill=[]; $err=$errFatal; }
else{
  if($ext==='csv'){ [$rows,$sheetNames,$rowFill,$err]=read_csv_all($abs); $sheetIdx=0; }
  else{ [$rows,$rowFill,$sheetNames,$err]=read_xlsx_all($abs,$sheetIdx,$sheetNames,$err); }
}

/* Copia cruda para tooltip (Acción Correctiva) */
$rawRows = $rows;

/* ===== Detección de header ===== */
function looks_like_header(array $first): bool {
  $a = isset($first[0]) ? norm((string)$first[0]) : '';
  $b = isset($first[1]) ? norm((string)$first[1]) : '';
  $isNro = in_array($a, ['NRO','Nº','NRO.','NUM','NUMERO','NRO/OBS','OBSNRO','OBSERVACIONNRO'], true);
  $isObs = ($b === 'OBSERVACIONES' || $b === 'OBSERVACION');
  return ($isNro && $isObs);
}

/* ===== Encabezados + recorte a 2 cols ===== */
$headers=[];
if($rows){
  $first=$rows[0];
  if(looks_like_header($first)){
    $headers = ['Nro','Observaciones'];
    array_shift($rows); if($rowFill) array_shift($rowFill);
    array_shift($rawRows);
  } else {
    $headers = ['Nro','Observaciones'];
  }
}
$MAX_TEXT_COLS = 2;
$headers = array_slice(array_pad(array_values($headers), $MAX_TEXT_COLS, ''), 0, $MAX_TEXT_COLS);
foreach ($rows as $i => $r) {
  $rows[$i] = array_slice(array_pad(array_values($r), $MAX_TEXT_COLS, ''), 0, $MAX_TEXT_COLS);
}

/* Filas-título */
function is_title_row(array $r, string $mode){
  $a=trim((string)($r[0]??'')); $b=trim((string)($r[1]??'')); $hasDigit = ($a!=='' && preg_match('/\d/',$a)===1);
  if($mode==='title' && $hasDigit) return true;
  if($mode==='item'  && $hasDigit) return false;
  if($a==='' && $b!=='') return true;
  if($b!=='' && mb_strtoupper($b,'UTF-8')===$b && mb_strlen($b,'UTF-8')<=120) return true;
  return false;
}

/* ===== Tablas DB ===== */
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

try { $pdo->exec("ALTER TABLE checklist ADD COLUMN caracter VARCHAR(100) NULL"); } catch (Throwable $e) {}
try { $pdo->exec("ALTER TABLE checklist ADD COLUMN updated_by VARCHAR(100) NULL"); } catch (Throwable $e) {}
try { $pdo->exec("ALTER TABLE checklist MODIFY evidencia_path TEXT NULL"); } catch (Throwable $e) {}

$pdo->exec("CREATE TABLE IF NOT EXISTS checklist_form (
  id INT AUTO_INCREMENT PRIMARY KEY,
  file_rel VARCHAR(512) NOT NULL,
  row_idx INT NOT NULL,
  field_key VARCHAR(128) NOT NULL,
  field_value TEXT NULL,
  updated_at TIMESTAMP NULL,
  UNIQUE KEY uq_file_row_field (file_rel,row_idx,field_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* Prefill */
$sel = $pdo->prepare("SELECT row_idx, caracter, estado, observacion, evidencia_path FROM checklist WHERE file_rel=?");
$sel->execute([$rel]);
$prefill = [];
foreach($sel as $r){ $prefill[(int)$r['row_idx']] = $r; }

/* PDF vecino */
$pdf_abs    = preg_replace('/\.(xlsx|csv)$/i', '.pdf', $abs);
$pdf_rel    = preg_replace('/\.(xlsx|csv)$/i', '.pdf', $rel);
$pdf_exists = is_file($pdf_abs);

/* Área label */
$area = strtoupper($SCOPE['label']);
if ($inScope==='ige_unidad' && preg_match('#^storage/unidades/'.preg_quote(UNIT_SLUG,'#').'/ige/(S1|S2|S3|S4|S5)/#i', $rel, $m)) {
  $area = strtoupper($m[1]);
} elseif ($inScope==='listas_control' && preg_match('#/storage/listas_control/(S1|S2|S3|S4)/#', $rel, $m)) {
  $area = strtoupper($m[1]);
}

/* Última actualización */
$stUpd = $pdo->prepare("
  SELECT updated_at, updated_by
  FROM checklist
  WHERE file_rel = ?
    AND updated_at IS NOT NULL
  ORDER BY updated_at DESC
  LIMIT 1
");
$stUpd->execute([$rel]);
$lastRow = $stUpd->fetch(PDO::FETCH_ASSOC) ?: null;

$lastUpd = $lastRow['updated_at'] ?? null;
$lastBy  = $lastRow['updated_by'] ?? null;

/* ===== Construcción visible + paginación ===== */
$visible = [];
$rowIndex=1; $lastSection=null;

foreach($rows as $i=>$r){
  if(!$debugShowColor && !empty($rowFill[$i])) continue;

  if(is_title_row($r,$mode)){
    $lastSection=['section'=>true,'title'=>trim(($r[0]??'').' '.($r[1]??''))];
    continue;
  }
  if($lastSection){ $visible[]=$lastSection; $lastSection=null; }

  $accion = '';
  if ($hasAccionTooltip) {
    $raw = $rawRows[$i] ?? [];
    $accion = trim((string)($raw[2] ?? ''));
  }

  $saved = $prefill[$rowIndex] ?? ['caracter'=>'','estado'=>'','observacion'=>'','evidencia_path'=>''];

  $visible[] = [
    'section'=>false,
    'row_idx'=>$rowIndex,
    'cols'=>$r,
    'criticidad'=>$saved['caracter'] ?? '',
    'estado'=>$saved['estado'] ?? '',
    'observacion'=>$saved['observacion'] ?? '',
    'ev'=>$saved['evidencia_path'] ?? '',
    'accion'=>$accion,
  ];
  $rowIndex++;
}

/* Estadísticas */
$cSi=$cNo=$cNull=0;
foreach($visible as $v){
  if(!empty($v['section'])) continue;
  $sv=$v['estado']??'';
  if($sv==='si') $cSi++;
  elseif($sv==='no') $cNo++;
  else $cNull++;
}
$mostradas = $cSi+$cNo+$cNull;
$pct = $mostradas ? round($cSi*100.0/$mostradas,1) : 0.0;
$pctInt = (int)round($pct);

/* Paginación */
$totalItems = 0;
foreach($visible as $v){ if(empty($v['section'])) $totalItems++; }

$totalPages = max(1, (int)ceil($totalItems / $perPage));
if($page > $totalPages) $page = $totalPages;

$startItem = ($page-1)*$perPage + 1;
$endItem   = min($totalItems, $page*$perPage);

$render = [];
$counter=0; $pendingSection=null;
for($i=0;$i<count($visible);$i++){
  $v = $visible[$i];
  if(!empty($v['section'])){ $pendingSection=$v; continue; }
  $counter++;
  if($counter < $startItem || $counter > $endItem) { $pendingSection=null; continue; }
  if($pendingSection){ $render[]=$pendingSection; $pendingSection=null; }
  $render[] = $v;
}

/* Helper QS base */
function base_qs($rel,$sheetIdx,$debugShowColor,$perPage,$fmt,$areaParam){
  $qs = 'p='.rawurlencode($rel)
      .'&s='.(int)$sheetIdx
      .($debugShowColor?'&showcolor=1':'')
      .'&pp='.(int)$perPage
      .'&fmt='.rawurlencode((string)$fmt);
  if($areaParam !== '') $qs .= '&area='.rawurlencode((string)$areaParam);
  return $qs;
}
$baseQS = base_qs($rel,$sheetIdx,$debugShowColor,$perPage,$tableFmt,$areaParam);

/* Para botón Volver */
$listBackUrl = $SCOPE['list_url'] . ($areaParam !== '' ? ('?area='.rawurlencode($areaParam)) : '');

/* evidencia_path a array */
function evidencia_to_array_local($ev): array {
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

/* Link helper hacia /ea/storage/... desde /ea/public/ige */
function href_from_public_ige(string $relPath): string {
  $relPath = ltrim(str_replace('\\','/',$relPath), '/');
  return '../../' . $relPath; // /ea/public/ige -> ../../ = /ea/
}

?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title><?= e(basename($abs)) ?> — <?= e($area) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= e($ASSETS_URL) ?>/css/theme-602.css">
<link rel="icon" href="<?= e($FAVICON) ?>">

<style>
  :root{
    --container-max: 1280px;
    --ok:#16a34a; --warn:#f59e0b; --bad:#ef4444; --mut:#9aa4b2;
  }

  html,body{ height:100%; }
  body{
    margin:0;
    color:#e5e7eb;
    background:#000;
    font-family:system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,sans-serif;
  }

  .page-bg{
    position:fixed; inset:0; z-index:-2; pointer-events:none;
    background:
      linear-gradient(160deg, rgba(0,0,0,.86) 0%, rgba(0,0,0,.68) 55%, rgba(0,0,0,.86) 100%),
      url("<?= e($IMG_BG) ?>") center/cover no-repeat;
    background-attachment: fixed, fixed;
    filter:saturate(1.05);
  }

  .brand-hero{ position:relative; padding:12px 0; }
  .hero-inner{
    max-width: var(--container-max);
    margin: 0 auto;
    padding: 0 14px;
    display:flex;
    align-items:center;
    gap:14px;
  }
  .brand-logo{
    width:56px; height:56px;
    object-fit:contain;
    flex:0 0 auto;
    filter:drop-shadow(0 2px 10px rgba(124,196,255,.25));
  }
  .brand-title{
    font-weight:900;
    letter-spacing:.4px;
    font-size:22px;
    line-height:1.15;
    text-shadow:0 2px 16px rgba(30,123,220,.35);
  }
  .brand-sub{
    font-size:14px;
    opacity:.9;
    border-top:2px solid rgba(124,196,255,.25);
    display:inline-block;
    padding-top:4px;
    margin-top:2px;
    color:#cbd5f5;
  }
  .brand-kicker{
    font-size:.85rem;
    color:#9aa4b2;
    margin-top:6px;
  }
  .userbox{
    margin-left:auto;
    text-align:right;
    font-size:.9rem;
  }
  .userbox .meta{ color:#9aa4b2; }

  .page{
    max-width: var(--container-max);
    margin: 0 auto;
    padding:16px 14px 24px;
  }

  .box{
    background:rgba(20,24,33,.80);
    border:1px solid rgba(255,255,255,.14);
    border-radius:16px;
    padding:12px;
    backdrop-filter:blur(6px)
  }

  .toolbar { gap: .6rem; flex-wrap: wrap; }
  .toolbar .left, .toolbar .right {
    display: flex;
    align-items: center;
    gap: .5rem;
    flex-wrap: wrap;
  }
  .badge-area {
    background: #0e1525;
    border: 1px solid rgba(255,255,255,.15);
    color: #d9e2ef;
    padding: .22rem .55rem;
    border-radius: 999px;
    font-weight: 800;
    font-size: .78rem;
  }

  .btnx {
    --padY: .42rem; --padX: .7rem; --radius: 10px; --border: rgba(255,255,255,.18);
    display: inline-flex;
    align-items: center;
    gap: .45rem;
    padding: var(--padY) var(--padX);
    border-radius: var(--radius);
    font-weight: 800;
    font-size: .86rem;
    line-height: 1;
    border: 1px solid var(--border);
    background: #0f1520;
    color: #e7ecf4;
    text-decoration: none;
  }
  .btnx:hover { background: #141c2b; color: #f3f7fb; border-color: rgba(255,255,255,.28); }
  .btnx--accent { background: #16a34a; color: #04110a; border-color: #13853e; }
  .btnx--accent:hover { background: #22c55e; border-color: #1ea152; color: #031007; }
  .btnx--muted  { background: #0b111a; border-color: rgba(255,255,255,.15); }

  .dropdown-menu-dark { --bs-dropdown-bg: #0e1525; --bs-dropdown-color: #e7ecf4; }
  .dropdown-item { font-weight: 700; font-size: .88rem; }
  .dropdown-header { color:#9fb3c8; font-weight:800; }

  table{ width:100%; border-collapse:separate; border-spacing:0; }
  thead th{
    position:sticky; top:0; z-index:3;
    background:#11151d; color:#e7ecf4;
    border-bottom:1px solid rgba(255,255,255,.2);
    padding:12px;
    text-transform:uppercase;
    letter-spacing:.04em;
    font-weight:800;
  }
  tbody td{
    padding:12px;
    border-bottom:1px solid rgba(255,255,255,.10);
    vertical-align:top;
    color:#d5deea;
  }
  tbody tr:nth-child(even){ background:rgba(255,255,255,.02) }
  tbody tr:hover{ background:rgba(255,255,255,.05) }
  .section{
    background:linear-gradient(90deg, rgba(34,197,94,.18), rgba(34,197,94,.10));
    color:#eaf7ee;
    font-weight:800;
  }

  select, input[type="text"]{
    width:100%;
    background:#0f1520;
    color:#e6edf7;
    border:1px solid rgba(255,255,255,.22);
    border-radius:10px;
    padding:.45rem .55rem;
  }
  input[type="file"]{
    background:#0f1520;
    color:#e6edf7;
    border:1px solid rgba(255,255,255,.22);
    border-radius:10px;
    padding:.35rem .55rem;
  }

  #overlay{
    position:fixed; inset:0;
    background:rgba(0,0,0,.55);
    display:none; align-items:center; justify-content:center;
    z-index:9999;
  }
  #overlay.show{ display:flex; }
  .spinner{
    width:72px; height:72px;
    border-radius:50%;
    border:6px solid rgba(255,255,255,.2);
    border-top-color:#22c55e;
    animation: spin 1s linear infinite;
  }
  @keyframes spin { to{ transform: rotate(360deg); } }

  .tip-dot{
    display:inline-flex;
    width:18px; height:18px;
    border-radius:999px;
    align-items:center; justify-content:center;
    font-size:.78rem; font-weight:900;
    color:#9CF3AC;
    background:#1b2a1e;
    border:1px solid #2a804c;
    cursor:help;
    user-select:none;
    outline:0;
  }
  .tip-box{
    position:absolute;
    left: 1.2rem; top:50%;
    transform: translateY(-50%);
    background:#0b1220;
    color:#eaf2ff;
    border:1px solid rgba(255,255,255,.15);
    padding:.48rem .62rem;
    border-radius:10px;
    max-width:440px;
    min-width:240px;
    box-shadow:0 10px 24px rgba(0,0,0,.45);
    font-size:.92rem;
    line-height:1.25;
    visibility:hidden;
    opacity:0;
    transition:opacity .12s ease;
    z-index: 10;
  }
  .tip{ position:relative; display:inline-flex; }
  .tip:hover .tip-box, .tip:focus-within .tip-box{ visibility:visible; opacity:1; }

  .td-criticidad{
    position: relative;
    transition: background-color .15s ease, border-color .15s ease, box-shadow .15s ease, color .15s ease;
    border-left: 4px solid transparent;
    box-shadow: inset 0 0 0 1px rgba(255,255,255,.08);
  }
  .td-criticidad select{
    background: #0f1520 !important;
    color: #e5e7eb !important;
    border: 1px solid rgba(255,255,255,.28);
    border-radius: 8px;
    font-weight: 700;
  }
  .td-criticidad.crit-baja{
    background: linear-gradient(90deg, rgba(22,163,74,.92), rgba(22,163,74,.65));
    border-left-color:#22c55e;
    color:#eafff3;
    box-shadow: 0 0 0 1px rgba(22,163,74,.7);
  }
  .td-criticidad.crit-media{
    background: linear-gradient(90deg, rgba(245,158,11,.95), rgba(245,158,11,.72));
    border-left-color:#f59e0b;
    color:#1f1300;
    box-shadow: 0 0 0 1px rgba(245,158,11,.7);
  }
  .td-criticidad.crit-alta{
    background: linear-gradient(90deg, rgba(239,68,68,.96), rgba(239,68,68,.78));
    border-left-color:#ef4444;
    color:#fff5f5;
    box-shadow: 0 0 0 1px rgba(239,68,68,.8);
  }

  .prog-mini{
    display:flex;
    flex-direction:column;
    gap:3px;
    min-width:190px;
  }
  .prog-mini-label{
    font-size:.72rem;
    font-weight:800;
    letter-spacing:.06em;
    text-transform:uppercase;
    color:#9fb3c8;
  }
  .prog-mini-bar{
    position:relative;
    height:7px;
    border-radius:999px;
    background:rgba(15,23,42,.9);
    overflow:hidden;
    border:1px solid rgba(148,163,184,.6);
  }
  .prog-mini-fill{
    position:absolute;
    inset:0;
    width:0;
    border-radius:inherit;
    background:linear-gradient(90deg,#22c55e,#16a34a);
  }
  .prog-mini-text{
    font-size:.78rem;
    font-weight:700;
    color:#e5e7eb;
  }

  .table-footer {
    margin-top: .75rem;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
    padding: .5rem .8rem;
    background: rgba(8, 12, 22, .9);
    border-radius: 12px;
    border: 1px solid rgba(148,163,184,.4);
    box-shadow: 0 8px 24px rgba(0,0,0,.6);
    font-size: .8rem;
  }
  .table-footer .pagination { margin-bottom: 0; }
  .table-footer .page-link {
    background: #020617;
    border-color: rgba(148,163,184,.6);
    color: #e5e7eb;
    font-weight: 600;
    padding: .22rem .55rem;
    border-radius: 999px !important;
  }
  .table-footer .page-item.active .page-link {
    background: #22c55e;
    border-color: #16a34a;
    color: #022c22;
  }
  .table-footer .page-item.disabled .page-link { opacity: .35; }

  .form-compact{
    display:inline-flex;
    align-items:center;
    gap:.35rem;
    flex-wrap:wrap;
    font-size:.8rem;
  }
  .form-compact label{
    margin:0;
    font-weight:700;
    color:#cbd5f5;
  }
  .form-compact select{
    width:auto;
    min-width:80px;
    padding:.22rem .35rem;
    font-size:.8rem;
  }

  .top-right-actions{
    position:fixed;
    top:10px;
    right:16px;
    display:flex;
    gap:.5rem;
    z-index:1100;
  }
  @media (max-width: 576px){
    .top-right-actions{
      top:8px;
      right:8px;
      flex-direction:column;
      align-items:flex-end;
    }
  }

  #floatingSave{
    position:fixed;
    right:16px;
    bottom:16px;
    z-index:1300;
    box-shadow:0 10px 24px rgba(0,0,0,.55);
  }

  .row-status{
    font-size:.78rem;
    font-weight:800;
    color:#a7f3d0;
  }
  .row-status.err{ color:#fecaca; }
  .row-status.muted{ color:#9fb3c8; }
</style>
</head>

<body>
<div class="page-bg"></div>

<header class="brand-hero">
  <div class="hero-inner">
    <img class="brand-logo" src="<?= e($ESCUDO) ?>" alt="EC MIL M"
         onerror="this.onerror=null;this.src='<?= e($ASSETS_URL) ?>/img/EA.png';">
    <div>
      <div class="brand-title">Escuela Militar de Montaña</div>
      <div class="brand-sub">“La Montaña Nos Une”</div>
      <div class="brand-kicker">IGE · Editor — <?= e($SCOPE['label']) ?> · <?= e(basename($abs)) ?></div>
    </div>

    <?php if (!empty($user)): ?>
      <div class="userbox">
        <div><strong><?= e($user['rank'] ?? '') ?> <?= e($user['full_name'] ?? '') ?></strong></div>
        <?php if (!empty($user['unit'])): ?>
          <div class="meta"><?= e($user['unit']) ?></div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</header>

<div class="top-right-actions">
  <a
    class="btnx btnx--muted"
    href="<?= e($listBackUrl) ?>"
    title="Volver al listado"
    onclick="if (window.history.length > 1) { history.back(); return false; }"
  >
    📁 Volver
  </a>
  <a class="btnx btnx--muted" href="ige.php?scope=<?= e($SCOPE['dash_scope']) ?>" title="Ir al inicio">
    🏠 Inicio
  </a>
</div>

<div class="position-fixed top-0 start-50 translate-middle-x p-3" style="z-index: 11000;">
  <div id="saveToast" class="toast align-items-center text-bg-success border-0 shadow"
       role="alert" aria-live="assertive" aria-atomic="true"
       data-bs-delay="2500">
    <div class="d-flex">
      <div class="toast-body">
        ✅ Cambios guardados correctamente.
      </div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto"
              data-bs-dismiss="toast" aria-label="Cerrar"></button>
    </div>
  </div>
</div>

<div class="page">

  <div class="d-flex align-items-center justify-content-between toolbar mb-3">
    <div class="left">

      <?php if ($lastUpd): ?>
        <span class="badge-area" id="lastUpdBadge"
              data-last-upd="<?= e((string)$lastUpd) ?>"
              data-last-by="<?= e((string)$lastBy) ?>">
          Última actualización:
          <b id="lastUpdTime"><?= e(date('d/m/Y H:i', strtotime((string)$lastUpd))) ?></b>
          <?php if ($lastBy): ?>
            — por <b id="lastUpdBy"><?= e((string)$lastBy) ?></b>
          <?php else: ?>
            — por <b id="lastUpdBy">—</b>
          <?php endif; ?>
        </span>
      <?php else: ?>
        <span class="badge-area" id="lastUpdBadge" style="opacity:.75">
          Última actualización: <b id="lastUpdTime">—</b> — por <b id="lastUpdBy">—</b>
        </span>
      <?php endif; ?>

      <?php if ($mostradas > 0): ?>
        <div class="prog-mini">
          <div class="prog-mini-label">Progreso tabla</div>
          <div class="prog-mini-bar">
            <div class="prog-mini-fill" style="width:<?= (int)$pctInt ?>%;"></div>
          </div>
          <div class="prog-mini-text">
            <?= (int)$pctInt ?>% (<?= (int)$cSi ?>/<?= (int)$mostradas ?> ítems cumplidos)
          </div>
        </div>
      <?php endif; ?>

      <?php if (!empty($sheetNames) && count($sheetNames)>1): ?>
        <form method="get" class="form-compact">
          <input type="hidden" name="p" value="<?= e($rel) ?>">
          <?php if($debugShowColor): ?><input type="hidden" name="showcolor" value="1"><?php endif; ?>
          <input type="hidden" name="pp" value="<?= (int)$perPage ?>">
          <input type="hidden" name="page" value="<?= (int)$page ?>">
          <input type="hidden" name="fmt" value="<?= e($tableFmt) ?>">
          <?php if($areaParam !== ''): ?>
            <input type="hidden" name="area" value="<?= e($areaParam) ?>">
          <?php endif; ?>
          <label>Hoja:</label>
          <select name="s" class="form-select form-select-sm" onchange="this.form.submit()">
            <?php foreach($sheetNames as $i=>$nm): ?>
              <option value="<?= (int)$i ?>" <?= $i===$sheetIdx?'selected':'' ?>><?= e($nm) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      <?php endif; ?>

      <form method="get" class="form-compact">
        <input type="hidden" name="p" value="<?= e($rel) ?>">
        <input type="hidden" name="s" value="<?= (int)$sheetIdx ?>">
        <?php if($debugShowColor): ?><input type="hidden" name="showcolor" value="1"><?php endif; ?>
        <?php if($tableFmt): ?><input type="hidden" name="fmt" value="<?= e($tableFmt) ?>"><?php endif; ?>
        <?php if($areaParam !== ''): ?><input type="hidden" name="area" value="<?= e($areaParam) ?>"><?php endif; ?>
        <label>Ítems por página:</label>
        <select name="pp" class="form-select form-select-sm" onchange="this.form.submit()">
          <?php foreach($allowedPP as $pp): ?>
            <option value="<?= (int)$pp ?>" <?= (int)$pp===$perPage?'selected':'' ?>><?= (int)$pp ?></option>
          <?php endforeach; ?>
        </select>
        <input type="hidden" name="page" value="1">
      </form>
    </div>

    <div class="right">
      <div class="dropdown">
        <button class="btnx dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
          🧩 Formato de tabla
        </button>
        <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end">
          <li class="dropdown-header">Interpretación del Nro</li>
          <li><a class="dropdown-item" href="ver_tabla.php?<?= $baseQS ?>&setmode=title&page=<?= (int)$page ?>">Nro como TÍTULO</a></li>
          <li><a class="dropdown-item" href="ver_tabla.php?<?= $baseQS ?>&setmode=item&page=<?= (int)$page ?>">Nro como ÍTEM</a></li>
          <li><hr class="dropdown-divider"></li>
          <li class="dropdown-header">Filas coloreadas (debug)</li>
          <?php if(!$debugShowColor): ?>
            <li><a class="dropdown-item" href="ver_tabla.php?<?= $baseQS ?>&page=<?= (int)$page ?>&showcolor=1">Ver color</a></li>
          <?php else: ?>
            <li><a class="dropdown-item" href="ver_tabla.php?<?= base_qs($rel,$sheetIdx,false,$perPage,$tableFmt,$areaParam) ?>&page=<?= (int)$page ?>">Ocultar color</a></li>
          <?php endif; ?>
          <li><hr class="dropdown-divider"></li>
          <li class="dropdown-header">Formato</li>
          <li><a class="dropdown-item<?= $tableFmt==='classic'?' active':'' ?>" href="ver_tabla.php?<?= base_qs($rel,$sheetIdx,$debugShowColor,$perPage,'classic',$areaParam) ?>&setfmt=classic&page=<?= (int)$page ?>">Clásico</a></li>
          <li><a class="dropdown-item<?= $tableFmt==='form'?' active':'' ?>" href="ver_tabla.php?<?= base_qs($rel,$sheetIdx,$debugShowColor,$perPage,'form',$areaParam) ?>&setfmt=form&page=<?= (int)$page ?>">Formulario</a></li>
        </ul>
      </div>

      <?php if($pdf_exists): ?>
        <a class="btnx" target="_blank" href="<?= e(href_from_public_ige($pdf_rel)) ?>" title="Abrir PDF adyacente">📄 PDF</a>
      <?php else: ?>
        <span class="btnx" style="opacity:.5; pointer-events:none;" title="No se encontró PDF">📄 PDF</span>
      <?php endif; ?>

      <button form="bulkForm" class="btnx btnx--accent" title="Guardar cambios de esta página">💾 Guardar</button>
    </div>
  </div>

  <?php if ($err): ?>
    <div class="alert alert-danger"><?= e($err) ?></div>
  <?php endif; ?>

  <div id="overlay" aria-hidden="true"><div class="spinner" role="status" aria-label="Guardando..."></div></div>

  <form id="bulkForm" action="save_check_bulk.php" method="post" enctype="multipart/form-data" class="box">
    <input type="hidden" name="file_rel" value="<?= e($rel) ?>">
    <input type="hidden" name="sheet" value="<?= (int)$sheetIdx ?>">
    <input type="hidden" name="showcolor" value="<?= $debugShowColor?'1':'0' ?>">
    <input type="hidden" name="pp" value="<?= (int)$perPage ?>">
    <input type="hidden" name="page" value="<?= (int)$page ?>">
    <input type="hidden" name="fmt" value="<?= e($tableFmt) ?>">
    <input type="hidden" name="area" value="<?= e($areaParam) ?>">

    <div style="overflow:auto; max-height:72vh;">
      <table>
        <thead>
          <tr>
            <?php foreach($headers as $h): ?><th><?= e($h) ?></th><?php endforeach; ?>
            <th>Criticidad</th>
            <th>Estado</th>
            <th>Observación</th>
            <th>Evidencia</th>
          </tr>
        </thead>
        <tbody>
        <?php
          if(empty($render)){
            $extraCols = 4;
            echo '<tr><td colspan="'.(count($headers)+$extraCols).'" class="text-muted">No hay filas para esta página.</td></tr>';
          } else {
            foreach($render as $v){
              if(!empty($v['section'])){
                $title = trim((string)($v['title'] ?? '')); if($title==='') $title='—';
                $extraCols = 4;
                echo '<tr class="section"><td colspan="'.count($headers).'">'.e($title).'</td><td colspan="'.$extraCols.'"></td></tr>';
                continue;
              }

              $r = $v['cols'];
              $rowIdx=(int)$v['row_idx'];
              $crit=(string)($v['criticidad'] ?? '');
              $est =(string)($v['estado'] ?? '');
              $obs =(string)($v['observacion'] ?? '');
              $ev  =(string)($v['ev'] ?? '');
              $accion = (string)($v['accion'] ?? '');

              $critClass = ($crit==='baja' ? 'crit-baja' : ($crit==='media' ? 'crit-media' : ($crit==='alta' ? 'crit-alta' : '')));

              echo '<tr data-row="'.$rowIdx.'">';

              echo '<td>'.e($r[0] ?? '').'</td>';

              echo '<td>';
              echo e($r[1] ?? '');
              if($hasAccionTooltip && $accion !== ''){
                echo '<span class="tip ms-1">';
                echo '  <span class="tip-dot" tabindex="0" aria-label="Acción correctiva">i</span>';
                echo '  <span class="tip-box"><b>Acción correctiva:</b><br>'.e($accion).'</span>';
                echo '</span>';
              }
              echo '</td>';

              echo '<td class="td-criticidad '.e($critClass).'">';
              echo '<select name="criticidad['.$rowIdx.']" class="form-select form-select-sm criticidad-select" data-row="'.$rowIdx.'">';
              $opts = [''=>'—','baja'=>'Baja','media'=>'Media','alta'=>'Alta'];
              foreach($opts as $val=>$lab){
                $selOpt = ($crit === $val) ? ' selected' : '';
                echo '<option value="'.e($val).'"'.$selOpt.'>'.e($lab).'</option>';
              }
              echo '</select>';
              echo '</td>';

              echo '<td><select name="estado['.$rowIdx.']" class="form-select form-select-sm" data-row="'.$rowIdx.'">';
              echo '<option value="" '.($est===''?'selected':'').'>—</option>';
              echo '<option value="si" '.($est==='si'?'selected':'').'>Sí</option>';
              echo '<option value="no" '.($est==='no'?'selected':'').'>No</option>';
              echo '</select></td>';

              echo '<td><input class="form-control form-control-sm" type="text" name="observacion['.$rowIdx.']" data-row="'.$rowIdx.'" value="'.e($obs).'" placeholder="Escribir..."></td>';

              echo '<td>';
              echo '<div class="d-flex flex-column gap-2 ev-cell" data-row="'.$rowIdx.'">';

              echo '<div>';
              echo '<input '
                  .'class="form-control form-control-sm ev-input" '
                  .'type="file" '
                  .'name="evidencia['.$rowIdx.'][]" '
                  .'data-row="'.$rowIdx.'" '
                  .'multiple '
                  .'accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.ppt,.pptx,.rar,.zip,.7z,.jpg,.jpeg,.png,.webp">';
              echo '</div>';

              $files = evidencia_to_array_local($ev);

              echo '<div class="ev-current small" id="ev-current-'.$rowIdx.'">';
              if ($files) {
                $qsBack = $baseQS.'&page='.(int)$page;
                echo '<div class="d-flex flex-wrap gap-1">';
                foreach ($files as $idx => $path) {
                  $label = basename($path);
                  $href  = href_from_public_ige($path);
                  $delUrl = 'delete_evidencia.php?row='.$rowIdx.'&file='.$idx.'&'.$qsBack;

                  echo '<div class="btn-group btn-group-sm mb-1" role="group" data-ev-item="1" data-ev-row="'.$rowIdx.'" data-ev-idx="'.$idx.'">';
                  echo '  <a class="btn btn-outline-info" href="'.e($href).'" target="_blank">'.e($label).'</a>';
                  echo '  <a class="btn btn-outline-danger ev-del" href="'.e($delUrl).'" '
                       .'data-del-url="'.e($delUrl).'" '
                       .'data-row="'.$rowIdx.'" data-idx="'.$idx.'" '
                       .'title="Eliminar">&times;</a>';
                  echo '</div>';
                }
                echo '</div>';
              } else {
                echo '<span class="text-muted">Sin archivos cargados…</span>';
              }
              echo '</div>';

              echo '<div class="ev-selected small text-info" id="ev-selected-'.$rowIdx.'"></div>';
              echo '<div class="row-status muted" id="row-status-'.$rowIdx.'"></div>';

              echo '</div>';
              echo '</td>';

              echo '</tr>';
            }
          }
        ?>
        </tbody>
      </table>
    </div>
  </form>

  <?php if ($totalItems > 0 || $totalPages > 1): ?>
    <div class="table-footer mt-2">
      <div>
        <?php if ($totalItems > 0): ?>
          Mostrando <strong><?= (int)$startItem ?></strong>–<strong><?= (int)$endItem ?></strong> de <strong><?= (int)$totalItems ?></strong> ítems
        <?php else: ?>
          Sin ítems para mostrar.
        <?php endif; ?>
      </div>

      <?php if($totalPages>1): ?>
        <nav>
          <ul class="pagination pagination-sm">
            <li class="page-item <?= $page<=1?'disabled':'' ?>">
              <a class="page-link" href="ver_tabla.php?<?= $baseQS ?>&page=<?= max(1,$page-1) ?>">«</a>
            </li>
            <?php
              $win=2;
              $from=max(1,$page-$win); $to=min($totalPages,$page+$win);
              if($from>1){
                echo '<li class="page-item"><a class="page-link" href="ver_tabla.php?'.$baseQS.'&page=1">1</a></li>';
                if($from>2) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
              }
              for($p=$from;$p<=$to;$p++){
                $act = $p===$page?' active':'';
                echo '<li class="page-item'.$act.'"><a class="page-link" href="ver_tabla.php?'.$baseQS.'&page='.$p.'">'.$p.'</a></li>';
              }
              if($to<$totalPages){
                if($to<$totalPages-1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                echo '<li class="page-item"><a class="page-link" href="ver_tabla.php?'.$baseQS.'&page='.$totalPages.'">'.$totalPages.'</a></li>';
              }
            ?>
            <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>">
              <a class="page-link" href="ver_tabla.php?<?= $baseQS ?>&page=<?= min($totalPages,$page+1) ?>">»</a>
            </li>
          </ul>
        </nav>
      <?php endif; ?>
    </div>
  <?php endif; ?>

</div>

<button id="floatingSave" type="button" class="btnx btnx--accent" title="Guardar cambios">
  💾 Guardar
</button>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
(function(){
  const form = document.getElementById('bulkForm');
  const overlay = document.getElementById('overlay');

  function showOverlay(){
    if(!overlay) return;
    overlay.classList.add('show');
    overlay.setAttribute('aria-hidden','false');
    document.body.setAttribute('aria-busy','true');
  }
  function hideOverlay(){
    if(!overlay) return;
    overlay.classList.remove('show');
    overlay.setAttribute('aria-hidden','true');
    document.body.removeAttribute('aria-busy');
  }

  function qsVal(name){
    const el = form ? form.querySelector('input[name="'+name+'"]') : null;
    return el ? el.value : '';
  }

  function setRowStatus(rowIdx, msg, kind){
    const el = document.getElementById('row-status-'+rowIdx);
    if(!el) return;
    el.classList.remove('err','muted');
    if(kind === 'err') el.classList.add('err');
    else if(kind === 'muted') el.classList.add('muted');
    el.textContent = msg || '';
  }

  function markRowSaving(rowIdx, saving){
    const tr = document.querySelector('tr[data-row="'+rowIdx+'"]');
    if(!tr) return;
    tr.style.opacity = saving ? '0.75' : '1';
  }

  function escapeHtml(str){
    return String(str).replace(/[&<>"']/g, function(m){
      return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m] || m;
    });
  }

  function nowFmt(){
    try{
      const d = new Date();
      return d.toLocaleString('es-AR', {
        year:'numeric', month:'2-digit', day:'2-digit',
        hour:'2-digit', minute:'2-digit'
      }).replace(',', '');
    }catch(e){
      return '';
    }
  }

  function updateLastUpdBadge(updatedBy){
    const t = document.getElementById('lastUpdTime');
    const b = document.getElementById('lastUpdBy');
    const badge = document.getElementById('lastUpdBadge');
    if(t) t.textContent = nowFmt() || '—';
    if(b) b.textContent = updatedBy ? String(updatedBy) : '—';
    if(badge) badge.style.opacity = '1';
  }

  async function readJsonOrText(res){
    const ct = (res.headers.get('content-type') || '').toLowerCase();
    if(ct.includes('application/json')){
      const j = await res.json().catch(()=>null);
      return { json: j, text: null };
    }
    const text = await res.text().catch(()=> '');
    return { json: null, text };
  }

  const floatingSave = document.getElementById('floatingSave');
  if (floatingSave && form) {
    floatingSave.addEventListener('click', function(){
      form.requestSubmit();
    });
  }

  if(form){
    form.addEventListener('submit', function(){
      showOverlay();
      const toDisable = document.querySelectorAll('button, a.btnx');
      toDisable.forEach(el => { if('disabled' in el) el.disabled = true; });
    });

    document.querySelectorAll('.pagination a.page-link').forEach(function(a){
      a.addEventListener('click', function(ev){
        const href = a.getAttribute('href');
        if(!href) return;
        ev.preventDefault();

        showOverlay();

        const fd = new FormData(form);
        fetch('save_check_bulk.php', {
          method: 'POST',
          body: fd,
          credentials: 'same-origin',
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(async function(res){
          if(!res.ok){
            const r = await readJsonOrText(res);
            const msg = (r.json && r.json.msg) ? r.json.msg : (r.text || ('HTTP '+res.status));
            throw new Error(msg);
          }
          window.location.href = href;
        }).catch(function(err){
          hideOverlay();
          alert('Error al guardar automáticamente:\n' + err.message);
        });
      });
    });
  }

  function applyCritClass(sel){
    const td = sel.closest('td');
    if(!td) return;
    td.classList.remove('crit-baja','crit-media','crit-alta');
    if(sel.value === 'baja') td.classList.add('crit-baja');
    else if(sel.value === 'media') td.classList.add('crit-media');
    else if(sel.value === 'alta') td.classList.add('crit-alta');
  }
  document.querySelectorAll('.criticidad-select').forEach(function(sel){
    applyCritClass(sel);
    sel.addEventListener('change', function(){ applyCritClass(sel); });
  });

  const wasSaved = <?= $savedFlag ? 'true' : 'false' ?>;
  if (wasSaved && window.bootstrap) {
    const toastEl = document.getElementById('saveToast');
    if (toastEl) {
      const toast = new bootstrap.Toast(toastEl);
      toast.show();
      try {
        const url = new URL(window.location.href);
        url.searchParams.delete('saved');
        window.history.replaceState({}, '', url);
      } catch (e) {}
    }
  }

  document.querySelectorAll('.ev-input').forEach(function(input){
    input.addEventListener('change', function(){
      const row = this.getAttribute('data-row');
      if(!row) return;
      const rowIdx = parseInt(row,10);

      const box = document.getElementById('ev-selected-'+rowIdx);
      if(!box) return;

      const files = Array.from(this.files || []);
      if(!files.length){
        box.innerHTML = '';
        return;
      }

      let html = '<div class="text-info">Archivos seleccionados (se subirán automáticamente):</div>';
      html += '<ul class="mb-0 ps-3">';
      files.forEach(f => { html += '<li>'+escapeHtml(f.name)+'</li>'; });
      html += '</ul><div class="text-muted mt-1">Subiendo…</div>';
      box.innerHTML = html;

      setRowStatus(rowIdx, 'Subiendo evidencia…', 'muted');
    });
  });

  const rowTimers = new Map();

  async function saveRow(rowIdx, includeFiles){
    if(!form) return;

    const fd = new FormData();
    fd.append('mode', 'row');
    fd.append('row_idx', String(rowIdx));

    fd.append('file_rel', qsVal('file_rel'));
    fd.append('sheet', qsVal('sheet'));
    fd.append('showcolor', qsVal('showcolor'));
    fd.append('pp', qsVal('pp'));
    fd.append('page', qsVal('page'));
    fd.append('fmt', qsVal('fmt'));
    fd.append('area', qsVal('area'));

    const crit = document.querySelector('select[name="criticidad['+rowIdx+']"]');
    const est  = document.querySelector('select[name="estado['+rowIdx+']"]');
    const obs  = document.querySelector('input[name="observacion['+rowIdx+']"]');

    if(crit) fd.append('criticidad['+rowIdx+']', crit.value);
    if(est)  fd.append('estado['+rowIdx+']', est.value);
    if(obs)  fd.append('observacion['+rowIdx+']', obs.value);

    if(includeFiles){
      const input = document.querySelector('input.ev-input[data-row="'+rowIdx+'"]');
      if(input && input.files && input.files.length){
        Array.from(input.files).forEach(f => fd.append('evidencia['+rowIdx+'][]', f, f.name));
      }
    }

    markRowSaving(rowIdx, true);
    setRowStatus(rowIdx, includeFiles ? 'Guardando evidencia…' : 'Guardando…', 'muted');

    const res = await fetch('save_check_bulk.php', {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });

    markRowSaving(rowIdx, false);

    if(!res.ok){
      const r = await readJsonOrText(res);
      const msg = (r.json && r.json.msg) ? r.json.msg : (r.text || ('HTTP '+res.status));
      throw new Error(msg);
    }

    const j = await res.json().catch(()=>null);
    if(!j || !j.ok) throw new Error((j && j.msg) ? j.msg : 'Error al guardar fila');

    updateLastUpdBadge(j.updated_by || '');

    if(includeFiles && j.files && Array.isArray(j.files)){
      const boxSel = document.getElementById('ev-selected-'+rowIdx);
      if(boxSel) boxSel.innerHTML = '<div class="text-success">✅ Evidencia guardada.</div>';

      const evCurrent = document.getElementById('ev-current-'+rowIdx);
      if(evCurrent){
        if(!j.files.length){
          evCurrent.innerHTML = '<span class="text-muted">Sin archivos cargados…</span>';
        } else {
          let html = '<div class="d-flex flex-wrap gap-1">';
          j.files.forEach((path, idx) => {
            const label = (path || '').split('/').pop();
            const href = '../../' + String(path || '').replace(/^\/+/, '');
            const delUrl = 'delete_evidencia.php?row='+rowIdx+'&file='+idx+'&' + (j.qs_back || '');
            html += '<div class="btn-group btn-group-sm mb-1" role="group" data-ev-item="1" data-ev-row="'+rowIdx+'" data-ev-idx="'+idx+'">';
            html += '  <a class="btn btn-outline-info" href="'+escapeHtml(href)+'" target="_blank">'+escapeHtml(label)+'</a>';
            html += '  <a class="btn btn-outline-danger ev-del" href="'+escapeHtml(delUrl)+'" data-del-url="'+escapeHtml(delUrl)+'" data-row="'+rowIdx+'" data-idx="'+idx+'" title="Eliminar">&times;</a>';
            html += '</div>';
          });
          html += '</div>';
          evCurrent.innerHTML = html;
        }
      }

      const input = document.querySelector('input.ev-input[data-row="'+rowIdx+'"]');
      if(input) input.value = '';
    }

    setRowStatus(rowIdx, '✅ Guardado', '');
    setTimeout(() => setRowStatus(rowIdx, '', 'muted'), 1200);
  }

  document.querySelectorAll('select[name^="criticidad["], select[name^="estado["]').forEach(function(el){
    el.addEventListener('change', function(){
      const m = this.name.match(/\[(\d+)\]/);
      if(!m) return;
      const rowIdx = parseInt(m[1],10);
      saveRow(rowIdx, false).catch(err => setRowStatus(rowIdx, '❌ '+err.message, 'err'));
    });
  });

  document.querySelectorAll('input[name^="observacion["]').forEach(function(el){
    el.addEventListener('input', function(){
      const m = this.name.match(/\[(\d+)\]/);
      if(!m) return;
      const rowIdx = parseInt(m[1],10);

      if(rowTimers.has(rowIdx)) clearTimeout(rowTimers.get(rowIdx));
      rowTimers.set(rowIdx, setTimeout(() => {
        saveRow(rowIdx, false).catch(err => setRowStatus(rowIdx, '❌ '+err.message, 'err'));
      }, 800));
    });
  });

  document.querySelectorAll('.ev-input').forEach(function(input){
    input.addEventListener('change', function(){
      const row = this.getAttribute('data-row');
      if(!row) return;
      const rowIdx = parseInt(row,10);
      saveRow(rowIdx, true).catch(err => setRowStatus(rowIdx, '❌ '+err.message, 'err'));
    });
  });

  document.addEventListener('click', function(ev){
    const a = ev.target && ev.target.closest ? ev.target.closest('a.ev-del') : null;
    if(!a) return;

    ev.preventDefault();

    const rowIdx = parseInt(a.getAttribute('data-row') || '0', 10);
    const url = a.getAttribute('data-del-url') || a.getAttribute('href');
    if(!rowIdx || !url) return;

    if(!confirm('¿Eliminar este archivo de evidencia?')) return;

    setRowStatus(rowIdx, 'Eliminando evidencia…', 'muted');

    fetch(url, {
      method: 'GET',
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).then(async (res) => {
      if(!res.ok){
        const r = await readJsonOrText(res);
        const msg = (r.json && (r.json.msg || r.json.error)) ? (r.json.msg || r.json.error) : (r.text || ('HTTP '+res.status));
        throw new Error(msg);
      }
      const j = await res.json().catch(()=>null);
      if(!j || !j.ok) throw new Error((j && (j.msg||j.error)) ? (j.msg||j.error) : 'Error al eliminar');

      updateLastUpdBadge(j.updated_by || '');

      const evCurrent = document.getElementById('ev-current-'+rowIdx);
      if(evCurrent && j.files && Array.isArray(j.files)){
        if(!j.files.length){
          evCurrent.innerHTML = '<span class="text-muted">Sin archivos cargados…</span>';
        } else {
          let html = '<div class="d-flex flex-wrap gap-1">';
          j.files.forEach((path, idx) => {
            const label = (path || '').split('/').pop();
            const href = '../../' + String(path || '').replace(/^\/+/, '');
            const delUrl = 'delete_evidencia.php?row='+rowIdx+'&file='+idx+'&' + (j.qs_back || '');
            html += '<div class="btn-group btn-group-sm mb-1" role="group" data-ev-item="1" data-ev-row="'+rowIdx+'" data-ev-idx="'+idx+'">';
            html += '  <a class="btn btn-outline-info" href="'+escapeHtml(href)+'" target="_blank">'+escapeHtml(label)+'</a>';
            html += '  <a class="btn btn-outline-danger ev-del" href="'+escapeHtml(delUrl)+'" data-del-url="'+escapeHtml(delUrl)+'" data-row="'+rowIdx+'" data-idx="'+idx+'" title="Eliminar">&times;</a>';
            html += '</div>';
          });
          html += '</div>';
          evCurrent.innerHTML = html;
        }
      }

      setRowStatus(rowIdx, '✅ Evidencia eliminada', '');
      setTimeout(() => setRowStatus(rowIdx, '', 'muted'), 1200);

    }).catch(err => {
      setRowStatus(rowIdx, '❌ '+err.message, 'err');
    });
  });

})();
</script>

</body>
</html>
