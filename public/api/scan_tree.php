<?php
@ini_set('max_execution_time', '0');
@ini_set('memory_limit', '1024M');
@set_time_limit(0);
@ignore_user_abort(true);

require_once __DIR__ . '/../../config/db.php';

$verbose = isset($_GET['verbose']);
function logv($m){ global $verbose; if($verbose){ echo $m, "\n"; @ob_flush(); @flush(); } }

$base = realpath(__DIR__ . '/../../');                 // .../inspeccion
$root = $base . '/storage/listas_control';
$pdfOK= $base . '/storage/pdf_ok';
$pdfER= $base . '/storage/pdf_err';

foreach([$root,$pdfOK,$pdfER] as $d){ if(!is_dir($d)) mkdir($d, 0777, true); }

/* ====== PhpSpreadsheet autoload opcional ====== */
$autoload = $base . '/vendor/autoload.php';
$hasSpreadsheet = is_file($autoload);
$zipOK = extension_loaded('zip');
$gdOK  = extension_loaded('gd'); // no es bloqueante para leer valores

header('Content-Type: text/plain; charset=utf-8');
logv("Inicio: ".date('Y-m-d H:i:s'));
logv("Base: $base");
logv("Listas: $root");
logv("vendor/autoload: ".($hasSpreadsheet?'ENCONTRADO':'NO'));
logv("ext-zip: ".($zipOK?'OK':'FALTA')." | ext-gd: ".($gdOK?'OK':'FALTA'));

if(!is_dir($root)){ echo "No existe: $root\n"; exit; }

/* Helpers */
function csv_read_rows($file){
  $rows=[]; $fh=@fopen($file,'r'); if(!$fh) return $rows;
  $first=fgets($fh); if($first===false){ fclose($fh); return $rows; }
  $sep = (substr_count($first,';')>substr_count($first,','))?';':',';
  rewind($fh);
  while(($d=fgetcsv($fh,0,$sep))!==false){ $rows[] = array_map(fn($v)=>trim((string)$v),$d); }
  fclose($fh);
  return array_values(array_filter($rows, fn($r)=>implode('',$r) !== ''));
}
function slug_code($name){
  $t = @iconv('UTF-8','ASCII//TRANSLIT',$name); if($t===false) $t=$name;
  $s = strtoupper(preg_replace('/[^A-Z0-9]/','',$t));
  return $s==='' ? 'AREA' : substr($s,0,10);
}
function parse_area_from_dir($dir){
  if(preg_match('/^\s*(S\d+)\s*[-_\.]\s*(.+)$/i',$dir,$m)) return [strtoupper($m[1]), trim($m[2])];
  if(preg_match('/^\s*(\d+)\s*[-_\.]\s*(.+)$/u',$dir,$m))  return ['A'.str_pad($m[1],2,'0',STR_PAD_LEFT), trim($m[2])];
  return [slug_code($dir), $dir];
}

/* SQL prepared */
$selAreaByCode = $pdo->prepare("SELECT id FROM areas WHERE codigo=?");
$insArea       = $pdo->prepare("INSERT INTO areas(codigo,nombre,orden) VALUES(?,?,?)");
$selMaxOrd     = $pdo->query("SELECT IFNULL(MAX(orden),0) m FROM areas"); $maxOrd=(int)$selMaxOrd->fetch()['m'];

$dupDocHash    = $pdo->prepare("SELECT id FROM documentos WHERE hash_sha1=?");
$insDoc        = $pdo->prepare("INSERT INTO documentos(area_id,nombre_archivo,ruta,hash_sha1,estado_ingesta) VALUES(?,?,?,?,?)");
$selDocByNameA = $pdo->prepare("SELECT id FROM documentos WHERE area_id=? AND nombre_archivo=?");

$insItem       = $pdo->prepare("INSERT INTO items(documento_id,nro,texto,obligatorio) VALUES(?,?,?,?)");
$dupItem       = $pdo->prepare("SELECT id FROM items WHERE documento_id=? AND nro=?");

/* Contadores */
$areas=0; $pdf_found=0; $pdf_moved=0; $pdf_dup=0; $pdf_err=0; $csv_ok=0; $csv_err=0; $xlsx_ok=0; $xlsx_skip_no=0; $xlsx_read_err=0;

if($hasSpreadsheet && $zipOK) { require_once $autoload; }
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/* Lector XLSX seguro */
function read_xlsx_rows($file){
  if(!class_exists(Xlsx::class)) return ['__NO_LIB__','autoload/zip faltante'];
  try{
    $r = new Xlsx(); $r->setReadDataOnly(true);
    $ss = $r->load($file); $sh=$ss->getSheet(0);
    $maxR=$sh->getHighestRow(); $maxC=Coordinate::columnIndexFromString($sh->getHighestColumn());
    $rows=[];
    for($i=1;$i<=$maxR;$i++){
      $line=[];
      for($c=1;$c<=$maxC;$c++){
        $v=$sh->getCellByColumnAndRow($c,$i)->getCalculatedValue();
        $line[] = is_scalar($v)? trim((string)$v):'';
      }
      if(implode('', $line) !== '') $rows[]=$line;
    }
    return $rows;
  }catch(Throwable $e){ return ['__READ_ERR__',$e->getMessage()]; }
}

/* Escaneo */
$iter = new DirectoryIterator($root);
foreach($iter as $areaDir){
  if($areaDir->isDot() || !$areaDir->isDir()) continue;

  [$code,$name] = parse_area_from_dir($areaDir->getFilename());
  $selAreaByCode->execute([$code]);
  $areaId = (int)$selAreaByCode->fetchColumn();
  if($areaId<=0){ $maxOrd++; $insArea->execute([$code,$name,$maxOrd]); $areaId=(int)$pdo->lastInsertId(); logv("Área creada: $code - $name"); }
  else { logv("Área: $code - $name"); }
  $areas++;

  $it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($areaDir->getPathname(), FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
  );
  foreach($it as $file){
    if($file->isDir()) continue;
    $ext = strtolower($file->getExtension());
    $path= $file->getPathname();

    // PDF
    if($ext==='pdf'){
      $pdf_found++;
      $hash=@sha1_file($path); if($hash===false){ $pdf_err++; logv("PDF hash error: ".$file->getFilename()); continue; }
      $dupDocHash->execute([$hash]); if($dupDocHash->fetchColumn()){ $pdf_dup++; @rename($path,$pdfER.'/'.$file->getFilename()); continue; }
      $destDir = $pdfOK.'/'.$code; if(!is_dir($destDir)) mkdir($destDir,0777,true);
      $dest = $destDir.'/'.$file->getFilename();
      if(!@rename($path,$dest)){ @copy($path,$dest) && @unlink($path); }
      $rutaRel='storage/pdf_ok/'.$code.'/'.$file->getFilename();
      $insDoc->execute([$areaId,$file->getFilename(),$rutaRel,$hash,'ok']);
      $pdf_moved++; logv("PDF importado: ".$file->getFilename());
      continue;
    }

    // CSV / XLSX -> items
    if($ext==='csv' || $ext==='xlsx') {
      $baseName = pathinfo($file->getFilename(), PATHINFO_FILENAME);
      $docName  = $baseName.'.pdf';
      $selDocByNameA->execute([$areaId, $docName]);
      $docId = (int)$selDocByNameA->fetchColumn();
      if($docId<=0){
        $rutaRel = 'storage/pdf_ok/'.$code.'/'.$docName; // puede no existir todavía
        $hash    = sha1($areaId.'|'.$docName.'|'.$rutaRel.'|'.microtime(true));
        $insDoc->execute([$areaId,$docName,$rutaRel,$hash,'ok']);
        $docId=(int)$pdo->lastInsertId();
        logv("Documento creado: $docName (area $code)");
      }

      if($ext==='csv'){ $rows=csv_read_rows($path); $src='CSV'; }
      else {
        $rows = read_xlsx_rows($path);
        if(is_array($rows) && isset($rows[0])){
          if($rows[0]==='__NO_LIB__'){ $xlsx_skip_no++; logv("XLSX omitido (sin librería/zip): ".$file->getFilename()); continue; }
          if($rows[0]==='__READ_ERR__'){ $xlsx_read_err++; logv("XLSX error: ".$file->getFilename()." - ".$rows[1]); continue; }
        }
        $src='XLSX';
      }

      if(!$rows){ $csv_err++; logv("Vacío: ".$file->getFilename()); continue; }

      $auto=1;
      foreach($rows as $r){
        $r = array_values($r);
        $nro=null; $txt=null; $obl=0;
        if(count($r)>=3){ $nro=(string)$r[0]; $txt=(string)$r[1]; $obl=(int)$r[2]; }
        elseif(count($r)==2){ $nro=(string)$r[0]; $txt=(string)$r[1]; }
        elseif(count($r)==1){ $txt=(string)$r[0]; }
        if($txt===null || $txt==='') continue;
        if($nro===null || $nro===''){ $nro=(string)$auto; }

        $dupItem->execute([$docId,$nro]);
        if($dupItem->fetchColumn()){
          do { $nro=(string)(++$auto); $dupItem->execute([$docId,$nro]); } while($dupItem->fetchColumn());
        }
        $insItem->execute([$docId,$nro,$txt,$obl?1:0]);
        $auto++;
      }

      if($src==='CSV'){ $csv_ok++; logv("Ítems importados (CSV): ".$file->getFilename()); }
      else { $xlsx_ok++; logv("Ítems importados (XLSX): ".$file->getFilename()); }
    }
  }
}

echo "Áreas procesadas: $areas\n";
echo "PDFs encontrados: $pdf_found\n";
echo "PDFs importados:  $pdf_moved\n";
echo "PDFs duplicados:  $pdf_dup\n";
echo "PDFs con error:   $pdf_err\n";
echo "CSV OK:           $csv_ok\n";
echo "CSV vacíos:       $csv_err\n";
echo "XLSX OK:          $xlsx_ok\n";
echo "XLSX sin librería:$xlsx_skip_no\n";
echo "XLSX error read.: $xlsx_read_err\n";
