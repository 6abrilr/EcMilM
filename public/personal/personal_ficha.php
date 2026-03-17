<?php declare(strict_types=1);
/**
 * public/personal/personal_ficha.php
 * Ficha individual — versión mejorada
 * Cambios:
 *   - Fotos en storage/unidades/ecmilm/PERSONAL/fotos/{nombre}_{fecha}_{id}.ext
 *   - Sin foto: storage/unidades/ecmilm/PERSONAL/fotos/sinfoto.png
 *   - Tab Eventos mejorado con rol operacional y badges por tipo
 *   - Ficha datos incluye jerarquía + selector de destino
 */

$ROOT = realpath(__DIR__ . '/../../');
if (!$ROOT) { http_response_code(500); exit('No se pudo resolver ROOT.'); }

$BOOT = $ROOT . '/auth/bootstrap.php';
$DB   = $ROOT . '/config/db.php';
if (!is_file($BOOT)) { http_response_code(500); exit('Falta: ' . $BOOT); }
if (!is_file($DB))   { http_response_code(500); exit('Falta: ' . $DB); }

require_once $BOOT;
require_login();
require_once $DB;
/** @var PDO $pdo */

/* ═══════════════════════ HELPERS ═══════════════════════════════════════ */
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function norm_dni(string $d): string { return preg_replace('/\D+/', '', $d) ?? ''; }
function csrf_if_exists(): void { if (function_exists('csrf_input')) { $o=csrf_input(); if(is_string($o)&&$o!=='') echo $o; } }
function table_exists(PDO $pdo, string $t): bool {
    $s=$pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t");
    $s->execute([':t'=>$t]); return ((int)$s->fetchColumn())>0;
}
function columns(PDO $pdo, string $t): array {
    $s=$pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t");
    $s->execute([':t'=>$t]); $m=[]; foreach($s->fetchAll(PDO::FETCH_COLUMN) as $c) $m[$c]=true; return $m;
}
function date_or_null(string $v): ?string {
    $v=trim($v); if($v==='') return null; $ts=strtotime($v); return $ts!==false?date('Y-m-d',$ts):null;
}
function detect_mime(string $f): string {
    try { if(class_exists('finfo')){ $i=new finfo(FILEINFO_MIME_TYPE); $m=$i->file($f); return is_string($m)?$m:'application/octet-stream'; } } catch(Throwable $e){}
    $m=@mime_content_type($f); return is_string($m)?$m:'application/octet-stream';
}
function fmt_date(?string $y): string {
    if(!$y) return '—'; $ts=strtotime($y); return $ts!==false?date('d/m/Y',$ts):'—';
}
function fmt_bytes(?int $b): string {
    if(!$b||$b<=0) return '—';
    $u=['B','KB','MB','GB']; $i=0; $v=(float)$b;
    while($v>=1024&&$i<3){$v/=1024;$i++;} return rtrim(rtrim(number_format($v,2,'.',''),'0'),'.') .' '.$u[$i];
}

/** Nombre de archivo para foto: APELLIDO_NOMBRE_YYYYMMDD_id.ext */
function foto_filename(string $apellidoNombre, int $pid, string $ext): string {
    $safe = strtoupper(trim($apellidoNombre));
    $safe = preg_replace('/[^A-ZÁÉÍÓÚÜÑ0-9]/i', '_', $safe);
    $safe = preg_replace('/_+/', '_', $safe);
    $safe = trim($safe, '_');
    if ($safe === '') $safe = 'PERSONAL';
    return $safe . '_' . date('Ymd') . '_' . $pid . '.' . $ext;
}

/* ═══════════════════════ BASE URLs ══════════════════════════════════════ */
$SELF_WEB        = (string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
$BASE_DIR_WEB    = rtrim(str_replace('\\','/',dirname($SELF_WEB)),'/');
$BASE_PUBLIC_WEB = rtrim(str_replace('\\','/',dirname($BASE_DIR_WEB)),'/');
$BASE_APP_WEB    = rtrim(str_replace('\\','/',dirname($BASE_PUBLIC_WEB)),'/');
$ASSETS_WEB      = $BASE_APP_WEB . '/assets';
$IMG_BG  = $ASSETS_WEB . '/img/fondo.png';
$ESCUDO  = $ASSETS_WEB . '/img/ecmilm.png';

/* ═══════════════════════ USUARIO / ROL ══════════════════════════════════ */
$user = function_exists('current_user') ? current_user() : ($_SESSION['user'] ?? null);
$dniNormUser = norm_dni((string)($user['dni'] ?? $user['username'] ?? ''));

$personalId = 0; $unidadPropia = 1; $fullNameDB = '';
try {
    if ($dniNormUser !== '') {
        $st = $pdo->prepare("SELECT id, unidad_id, CONCAT_WS(' ', grado, arma, apellido_nombre) AS nc
                             FROM personal_unidad
                             WHERE REPLACE(REPLACE(REPLACE(dni,'.',''),'-',''),' ','')=:d LIMIT 1");
        $st->execute([':d'=>$dniNormUser]);
        if ($r=$st->fetch(PDO::FETCH_ASSOC)) {
            $personalId=(int)$r['id']; $unidadPropia=(int)$r['unidad_id']; $fullNameDB=(string)$r['nc'];
        }
    }
} catch(Throwable $e){}

$roleCodigo='USUARIO';
try {
    if ($personalId>0) {
        $st=$pdo->prepare("SELECT r.codigo FROM personal_unidad pu INNER JOIN roles r ON r.id=pu.role_id WHERE pu.id=:p LIMIT 1");
        $st->execute([':p'=>$personalId]);
        $c=$st->fetchColumn(); if(is_string($c)&&$c!=='') $roleCodigo=$c;
    }
} catch(Throwable $e){}

$esSuperAdmin = ($roleCodigo==='SUPERADMIN');
$esAdmin      = ($roleCodigo==='ADMIN')||$esSuperAdmin;
$unidadActiva = $unidadPropia;
if ($esSuperAdmin) { $uSel=(int)($_SESSION['unidad_id']??0); if($uSel>0) $unidadActiva=$uSel; }

/* Branding */
$NOMBRE='Unidad'; $LEYENDA='';
try {
    $st=$pdo->prepare("SELECT nombre_completo, subnombre FROM unidades WHERE id=:id LIMIT 1");
    $st->execute([':id'=>$unidadActiva]);
    if ($u=$st->fetch(PDO::FETCH_ASSOC)) {
        if(!empty($u['nombre_completo'])) $NOMBRE=(string)$u['nombre_completo'];
        if(!empty($u['subnombre']))       $LEYENDA=trim((string)$u['subnombre']);
    }
} catch(Throwable $e){}

/* ═══════════════════════ RUTAS DE STORAGE ═══════════════════════════════
 * Fotos: storage/unidades/ecmilm/PERSONAL/fotos/
 * Docs:  storage/unidades/{slug}/personal_docs/{pid}/
 */
$colsPD  = columns($pdo,'personal_documentos');
$colsSan = columns($pdo,'sanidad_partes_enfermo');
$colsPE  = table_exists($pdo,'personal_eventos') ? columns($pdo,'personal_eventos') : [];

// Obtener slug de la unidad
$unidadSlug = 'unidad_' . $unidadActiva;
try {
    $st=$pdo->prepare("SELECT slug FROM unidades WHERE id=:id LIMIT 1");
    $st->execute([':id'=>$unidadActiva]);
    $s=$st->fetchColumn(); if(is_string($s)&&trim($s)!=='') $unidadSlug=trim($s);
} catch(Throwable $e){}

// Ruta docs genéricos
$DOCS_REL_DIR = 'storage/unidades/' . $unidadSlug . '/personal_docs';
$DOCS_ABS_DIR = $ROOT . '/' . $DOCS_REL_DIR;
if (!is_dir($DOCS_ABS_DIR)) @mkdir($DOCS_ABS_DIR, 0775, true);

// Ruta FOTOS — siempre en ecmilm/PERSONAL/fotos
$FOTOS_REL_DIR = 'storage/unidades/' . $unidadSlug . '/PERSONAL/fotos';
$FOTOS_ABS_DIR = $ROOT . '/' . $FOTOS_REL_DIR;
if (!is_dir($FOTOS_ABS_DIR)) @mkdir($FOTOS_ABS_DIR, 0775, true);

// Sin foto placeholder en la misma carpeta
$SINFOTO_ABS = $FOTOS_ABS_DIR . '/sinfoto.png';
$SINFOTO_URL = $BASE_APP_WEB . '/' . $FOTOS_REL_DIR . '/sinfoto.png';
// Fallback al assets si no existe el custom
if (!is_file($SINFOTO_ABS)) {
    $SINFOTO_URL = $ASSETS_WEB . '/img/sinfoto.png';
    if (!is_file($ROOT . '/assets/img/sinfoto.png'))
        $SINFOTO_URL = ''; // se mostrará placeholder CSS
}

/* ═══════════════════════ PARAMS ═════════════════════════════════════════ */
$id  = (int)($_GET['id']  ?? 0);
$q   = trim((string)($_GET['q'] ?? ''));
$tab = trim((string)($_GET['tab'] ?? 'ficha'));

$mensajeOk = ''; $mensajeError = '';

/* ═══════════════════════ ACCIONES POST ══════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $accion=(string)($_POST['accion']??'');
    try {
        if (!$esAdmin) throw new RuntimeException('Acceso restringido. Solo ADMIN/SUPERADMIN.');

        /* ── Guardar datos personales ── */
        if ($accion==='guardar_personal') {
            $pid=(int)($_POST['personal_id']??0);
            if($pid<=0) throw new RuntimeException('ID inválido.');

            $grado    = trim((string)($_POST['grado']       ??''));
            $arma     = trim((string)($_POST['arma']        ??''));
            $apnom    = trim((string)($_POST['apellido_nombre']??''));
            $dni      = norm_dni((string)($_POST['dni']     ??''));
            $cuil     = trim((string)($_POST['cuil']        ??''));
            $fnac     = date_or_null((string)($_POST['fecha_nac']??''));
            $sexo     = trim((string)($_POST['sexo']        ??''));
            $dom      = trim((string)($_POST['domicilio']   ??''));
            $ec       = trim((string)($_POST['estado_civil']??''));
            $hijos    = ($_POST['hijos']??'')===''?null:(int)$_POST['hijos'];
            $dest     = trim((string)($_POST['destino_interno']??''));
            $destId   = ($_POST['destino_id']??'')===''?null:(int)$_POST['destino_id'];
            $jerarq   = trim((string)($_POST['jerarquia']   ??''));
            $func     = trim((string)($_POST['funcion']     ??''));
            $tel      = trim((string)($_POST['telefono']    ??''));
            $cor      = trim((string)($_POST['correo']      ??''));
            $obs      = trim((string)($_POST['observaciones']??''));
            $fnacAlt  = date_or_null((string)($_POST['fecha_alta']??''));

            if($apnom==='') throw new RuntimeException('Apellido y Nombre es obligatorio.');
            if($dni==='')   throw new RuntimeException('DNI es obligatorio.');

            $pdo->prepare("
                UPDATE personal_unidad SET
                  grado=:grado, arma=:arma, apellido_nombre=:apnom, dni=:dni, cuil=:cuil,
                  fecha_nac=:fnac, sexo=:sexo, domicilio=:dom, estado_civil=:ec, hijos=:hijos,
                  destino_interno=:dest, destino_id=:destId, jerarquia=:jer,
                  funcion=:fun, telefono=:tel, correo=:cor,
                  fecha_alta=:falta, observaciones=:obs,
                  updated_at=NOW(), updated_by_id=:ubid
                WHERE id=:id AND unidad_id=:uid LIMIT 1
            ")->execute([
                ':grado'=>$grado?:null, ':arma'=>$arma?:null, ':apnom'=>$apnom,
                ':dni'=>$dni, ':cuil'=>$cuil?:null, ':fnac'=>$fnac,
                ':sexo'=>$sexo?:null, ':dom'=>$dom?:null, ':ec'=>$ec?:null,
                ':hijos'=>$hijos, ':dest'=>$dest?:null, ':destId'=>$destId,
                ':jer'=>$jerarq?:null, ':fun'=>$func?:null,
                ':tel'=>$tel?:null, ':cor'=>$cor?:null,
                ':falta'=>$fnacAlt, ':obs'=>$obs?:null,
                ':ubid'=>$personalId?:null, ':id'=>$pid, ':uid'=>$unidadActiva,
            ]);
            $mensajeOk='Datos actualizados correctamente.'; $id=$pid; $tab='ficha';
        }

        /* ── Subir foto ── */
        if ($accion==='subir_foto') {
            $pid=(int)($_POST['personal_id']??0);
            if($pid<=0) throw new RuntimeException('ID inválido.');
            if(!isset($_FILES['foto_archivo'])||$_FILES['foto_archivo']['error']===UPLOAD_ERR_NO_FILE)
                throw new RuntimeException('Seleccioná una foto.');
            $file=$_FILES['foto_archivo'];
            if($file['error']!==UPLOAD_ERR_OK) throw new RuntimeException('Error al subir (cód '.$file['error'].').');
            if((int)$file['size']>8*1024*1024) throw new RuntimeException('La foto supera 8MB.');

            $origName=(string)($file['name']??'foto');
            $ext=strtolower(pathinfo($origName,PATHINFO_EXTENSION));
            if(!in_array($ext,['jpg','jpeg','png','webp'],true)) throw new RuntimeException('Solo JPG/PNG/WEBP para fotos.');

            // Nombre del archivo con apellido_nombre
            $st=$pdo->prepare("SELECT apellido_nombre FROM personal_unidad WHERE id=:id AND unidad_id=:uid LIMIT 1");
            $st->execute([':id'=>$pid,':uid'=>$unidadActiva]);
            $row=$st->fetch(PDO::FETCH_ASSOC);
            $apnom=(string)($row['apellido_nombre']??'PERSONAL');

            $filename  = foto_filename($apnom, $pid, $ext);
            $destAbs   = $FOTOS_ABS_DIR . '/' . $filename;
            $destRel   = $FOTOS_REL_DIR . '/' . $filename;

            if(!move_uploaded_file((string)$file['tmp_name'], $destAbs))
                throw new RuntimeException('No se pudo guardar la foto.');

            // Registrar en personal_documentos (tipo=foto_perfil)
            // Primero marcar la foto anterior como deleted si existe deleted_at
            $whereDel = isset($colsPD['deleted_at']) ? " AND deleted_at IS NULL" : "";
            if(isset($colsPD['deleted_at'])) {
                $pdo->prepare("UPDATE personal_documentos SET deleted_at=NOW()
                               WHERE unidad_id=:uid AND personal_id=:pid AND tipo=:tp $whereDel")
                    ->execute([':uid'=>$unidadActiva,':pid'=>$pid,':tp'=>'foto_perfil']);
            } else {
                $pdo->prepare("DELETE FROM personal_documentos WHERE unidad_id=:uid AND personal_id=:pid AND tipo=:tp")
                    ->execute([':uid'=>$unidadActiva,':pid'=>$pid,':tp'=>'foto_perfil']);
            }

            $mime  = detect_mime($destAbs);
            $bytes = @filesize($destAbs);
            $sha   = function_exists('hash_file') ? @hash_file('sha256',$destAbs) : null;

            $fields=['unidad_id','personal_id','tipo','titulo','path','fecha','created_at','created_by_id'];
            $vals=[':uid',':pid',':tipofoto',':tit',':path',':fecha','NOW()',':cbid'];
            $params=[':uid'=>$unidadActiva,':pid'=>$pid,':tipofoto'=>'foto_perfil',
                     ':tit'=>$filename,':path'=>$destRel,
                     ':fecha'=>date('Y-m-d'),':cbid'=>$personalId?:null];
            if(isset($colsPD['original_name'])){$fields[]='original_name';$vals[]=':on';$params[':on']=$origName;}
            if(isset($colsPD['mime'])){$fields[]='mime';$vals[]=':mm';$params[':mm']=$mime;}
            if(isset($colsPD['bytes'])){$fields[]='bytes';$vals[]=':by';$params[':by']=$bytes!==false?(int)$bytes:null;}
            if(isset($colsPD['sha256'])){$fields[]='sha256';$vals[]=':sh';$params[':sh']=is_string($sha)?$sha:null;}

            $sql="INSERT INTO personal_documentos (".implode(',',$fields).") VALUES (".implode(',',$vals).")";
            $pdo->prepare($sql)->execute($params);

            $mensajeOk='Foto actualizada.'; $id=$pid; $tab='ficha';
        }

        /* ── Subir documento genérico ── */
        if ($accion==='subir_documento') {
            $pid=(int)($_POST['personal_id']??0);
            if($pid<=0) throw new RuntimeException('ID inválido.');
            if(!isset($_FILES['archivo'])||$_FILES['archivo']['error']===UPLOAD_ERR_NO_FILE)
                throw new RuntimeException('Seleccioná un archivo.');
            $file=$_FILES['archivo'];
            if($file['error']!==UPLOAD_ERR_OK) throw new RuntimeException('Error al subir (cód '.$file['error'].').');
            if((int)$file['size']>20*1024*1024) throw new RuntimeException('El archivo supera 20MB.');

            $origName=(string)($file['name']??'doc');
            $ext=strtolower(pathinfo($origName,PATHINFO_EXTENSION));
            if(!in_array($ext,['pdf','jpg','jpeg','png','webp','doc','docx'],true))
                throw new RuntimeException('Extensión no permitida: .'.$ext);

            $tipo   = trim((string)($_POST['tipo']  ??'otros'));
            $titulo = trim((string)($_POST['titulo']??''));
            $nota   = trim((string)($_POST['nota']  ??''));
            $fecha  = date_or_null((string)($_POST['fecha']??''));
            $evId   = isset($colsPD['evento_id'])?(int)($_POST['evento_id']??0):0;
            if($evId<=0) $evId=null;

            $carpRel = $DOCS_REL_DIR . '/' . $pid;
            $carpAbs = $ROOT . '/' . $carpRel;
            if(!is_dir($carpAbs)) @mkdir($carpAbs,0775,true);

            $safe    = preg_replace('/[^A-Za-z0-9_\.-]/','_',$origName);
            $destRel = $carpRel . '/' . time() . '_' . $safe;
            $destAbs = $ROOT . '/' . $destRel;

            if(!move_uploaded_file((string)$file['tmp_name'],$destAbs))
                throw new RuntimeException('No se pudo mover el archivo.');

            $mime=$det=detect_mime($destAbs);
            $bytes=@filesize($destAbs);
            $sha=function_exists('hash_file')?@hash_file('sha256',$destAbs):null;

            $fields=['unidad_id','personal_id','tipo','titulo','path','nota','fecha','created_at','created_by_id'];
            $vals=[':uid',':pid',':tipo',':tit',':path',':nota',':fecha','NOW()',':cbid'];
            $params=[':uid'=>$unidadActiva,':pid'=>$pid,':tipo'=>$tipo?:null,
                     ':tit'=>$titulo?:null,':path'=>$destRel,':nota'=>$nota?:null,
                     ':fecha'=>$fecha,':cbid'=>$personalId?:null];
            if(isset($colsPD['evento_id'])){$fields[]='evento_id';$vals[]=':eid';$params[':eid']=$evId;}
            if(isset($colsPD['original_name'])){$fields[]='original_name';$vals[]=':on';$params[':on']=$origName;}
            if(isset($colsPD['mime'])){$fields[]='mime';$vals[]=':mm';$params[':mm']=$mime;}
            if(isset($colsPD['bytes'])){$fields[]='bytes';$vals[]=':by';$params[':by']=$bytes!==false?(int)$bytes:null;}
            if(isset($colsPD['sha256'])){$fields[]='sha256';$vals[]=':sh';$params[':sh']=is_string($sha)?$sha:null;}

            $pdo->prepare("INSERT INTO personal_documentos (".implode(',',$fields).") VALUES (".implode(',',$vals).")")
                ->execute($params);

            $mensajeOk='Documento subido.'; $id=$pid; $tab='docs';
        }

        /* ── Guardar sanidad ── */
        if ($accion==='guardar_sanidad') {
            $pid=(int)($_POST['personal_id']??0);
            if($pid<=0) throw new RuntimeException('ID inválido.');

            $tiene = (string)($_POST['tiene_parte']??'no')==='si'?'si':'no';
            $inicio = date_or_null((string)($_POST['inicio']??''));
            $fin    = date_or_null((string)($_POST['fin']??''));
            $obsSan = trim((string)($_POST['observaciones_sanidad']??''));

            $hayEvid=false;
            if(isset($_FILES['sanidad_evidencias'])) {
                $e0=$_FILES['sanidad_evidencias']['error']??UPLOAD_ERR_NO_FILE;
                if(is_array($e0)){foreach($e0 as $er){if((int)$er!==UPLOAD_ERR_NO_FILE){$hayEvid=true;break;}}}
                else{$hayEvid=((int)$e0!==UPLOAD_ERR_NO_FILE);}
            }

            $pdo->beginTransaction();
            $stCur=$pdo->prepare("SELECT COALESCE(cantidad_parte_enfermo,0) AS cant,
                                  parte_enfermo_desde, parte_enfermo_hasta
                                  FROM personal_unidad WHERE id=:p AND unidad_id=:u LIMIT 1");
            $stCur->execute([':p'=>$pid,':u'=>$unidadActiva]);
            $cur=$stCur->fetch(PDO::FETCH_ASSOC);
            if(!$cur) throw new RuntimeException('No se encontró el registro en personal_unidad.');

            $cantActual=(int)($cur['cant']??0);
            $iniFinal=$inicio?:($cur['parte_enfermo_desde']??null)?:($tiene==='si'?date('Y-m-d'):null);
            $finFinal=$fin?:($cur['parte_enfermo_hasta']??null)?:null;

            // Determinar si hay archivo a subir
            $allowedExt=['pdf','jpg','jpeg','png','webp','doc','docx'];

            if(!$hayEvid) {
                // Solo ajustar/crear registro sin incrementar
                $stSel=$pdo->prepare("SELECT id FROM sanidad_partes_enfermo
                                      WHERE unidad_id=:u AND personal_id=:p ORDER BY id DESC LIMIT 1");
                $stSel->execute([':u'=>$unidadActiva,':p'=>$pid]);
                $sid=(int)($stSel->fetchColumn()?:0);

                if($sid>0) {
                    $pdo->prepare("UPDATE sanidad_partes_enfermo
                                   SET tiene_parte=:t, inicio=:i, fin=:f, cantidad=:c, observaciones=:o,
                                       updated_at=NOW(), updated_by_id=:ub
                                   WHERE id=:id AND unidad_id=:u AND personal_id=:p LIMIT 1")
                        ->execute([':t'=>$tiene,':i'=>$iniFinal,':f'=>$finFinal,':c'=>$cantActual,
                                   ':o'=>$obsSan?:null,':ub'=>$personalId?:null,
                                   ':id'=>$sid,':u'=>$unidadActiva,':p'=>$pid]);
                } else {
                    $ev=$tiene==='si'?'parte':'alta';
                    $fields=['unidad_id','personal_id','tiene_parte','inicio','fin','cantidad','observaciones','updated_at','updated_by_id'];
                    $vals=[':u',':p',':t',':i',':f',':c',':o','NOW()',':ub'];
                    $params=[':u'=>$unidadActiva,':p'=>$pid,':t'=>$tiene,':i'=>$iniFinal,
                             ':f'=>$finFinal,':c'=>$cantActual,':o'=>$obsSan?:null,':ub'=>$personalId?:null];
                    if(isset($colsSan['evento'])){$fields[]='evento';$vals[]=':ev';$params[':ev']=$ev;}
                    if(isset($colsSan['created_at'])){$fields[]='created_at';$vals[]='NOW()';}
                    if(isset($colsSan['created_by_id'])){$fields[]='created_by_id';$vals[]=':cb';$params[':cb']=$personalId?:null;}
                    $pdo->prepare("INSERT INTO sanidad_partes_enfermo (".implode(',',$fields).") VALUES (".implode(',',$vals).")")
                        ->execute($params);
                }

                // Sync canónico
                _sync_sanidad($pdo,$colsPD,$colsSan,$unidadActiva,$pid,$personalId?:null);
                $pdo->commit();
                $mensajeOk='Sanidad actualizada (sin evidencia).';
            } else {
                // Con evidencia: nuevo evento
                if($tiene==='si') {
                    $pdo->prepare("UPDATE personal_unidad
                                   SET tiene_parte_enfermo=1,
                                       parte_enfermo_desde=COALESCE(:i, parte_enfermo_desde, CURDATE()),
                                       parte_enfermo_hasta=COALESCE(:f, parte_enfermo_hasta),
                                       cantidad_parte_enfermo=COALESCE(cantidad_parte_enfermo,0)+1,
                                       updated_at=NOW(), updated_by_id=:ub
                                   WHERE id=:p AND unidad_id=:u LIMIT 1")
                        ->execute([':i'=>$iniFinal,':f'=>$finFinal,':ub'=>$personalId?:null,':p'=>$pid,':u'=>$unidadActiva]);

                    $stRe=$pdo->prepare("SELECT COALESCE(cantidad_parte_enfermo,0) FROM personal_unidad WHERE id=:p AND unidad_id=:u LIMIT 1");
                    $stRe->execute([':p'=>$pid,':u'=>$unidadActiva]);
                    $cantFinal=(int)($stRe->fetchColumn()?:$cantActual+1);

                    $fields=['unidad_id','personal_id','tiene_parte','inicio','fin','cantidad','observaciones','updated_at','updated_by_id'];
                    $vals=[':u',':p','si',':i',':f',':c',':o','NOW()',':ub'];
                    $params=[':u'=>$unidadActiva,':p'=>$pid,':i'=>$iniFinal,':f'=>$finFinal,
                             ':c'=>$cantFinal,':o'=>$obsSan?:null,':ub'=>$personalId?:null];
                    if(isset($colsSan['evento'])){$fields[]='evento';$vals[]='parte';}
                    if(isset($colsSan['created_at'])){$fields[]='created_at';$vals[]='NOW()';}
                    if(isset($colsSan['created_by_id'])){$fields[]='created_by_id';$vals[]=':cb';$params[':cb']=$personalId?:null;}
                    $pdo->prepare("INSERT INTO sanidad_partes_enfermo (".implode(',',$fields).") VALUES (".implode(',',$vals).")")
                        ->execute($params);
                    $sanidadId=(int)$pdo->lastInsertId();

                    $nUp=_upload_evid($pdo,$colsPD,$ROOT,$DOCS_REL_DIR,$unidadActiva,$pid,$personalId,
                                      'sanidad_evidencias','parte_enfermo','Parte de enfermo',$iniFinal,$obsSan?:null,$sanidadId);
                    _sync_sanidad($pdo,$colsPD,$colsSan,$unidadActiva,$pid,$personalId?:null);
                    $pdo->commit();
                    $mensajeOk="Parte cargado. Evidencias: {$nUp}. Total partes: {$cantFinal}.";
                } else {
                    $finAlta=$finFinal?:date('Y-m-d');
                    $pdo->prepare("UPDATE personal_unidad SET tiene_parte_enfermo=0, parte_enfermo_hasta=:f,
                                   updated_at=NOW(), updated_by_id=:ub WHERE id=:p AND unidad_id=:u LIMIT 1")
                        ->execute([':f'=>$finAlta,':ub'=>$personalId?:null,':p'=>$pid,':u'=>$unidadActiva]);

                    $fields=['unidad_id','personal_id','tiene_parte','inicio','fin','cantidad','observaciones','updated_at','updated_by_id'];
                    $vals=[':u',':p','no',':i',':f',':c',':o','NOW()',':ub'];
                    $params=[':u'=>$unidadActiva,':p'=>$pid,':i'=>$iniFinal,':f'=>$finAlta,
                             ':c'=>$cantActual,':o'=>$obsSan?:null,':ub'=>$personalId?:null];
                    if(isset($colsSan['evento'])){$fields[]='evento';$vals[]='alta';}
                    if(isset($colsSan['created_at'])){$fields[]='created_at';$vals[]='NOW()';}
                    if(isset($colsSan['created_by_id'])){$fields[]='created_by_id';$vals[]=':cb';$params[':cb']=$personalId?:null;}
                    $pdo->prepare("INSERT INTO sanidad_partes_enfermo (".implode(',',$fields).") VALUES (".implode(',',$vals).")")
                        ->execute($params);
                    $sanidadId=(int)$pdo->lastInsertId();

                    $nUp=_upload_evid($pdo,$colsPD,$ROOT,$DOCS_REL_DIR,$unidadActiva,$pid,$personalId,
                                      'sanidad_evidencias','alta_parte_enfermo','Alta parte de enfermo',$finAlta,$obsSan?:null,$sanidadId);
                    _sync_sanidad($pdo,$colsPD,$colsSan,$unidadActiva,$pid,$personalId?:null);
                    $pdo->commit();
                    $mensajeOk="Alta cargada. Evidencias: {$nUp}.";
                }
            }
            $id=$pid; $tab='sanidad';
        }

        /* ── Crear evento ── */
        if ($accion==='crear_evento' && table_exists($pdo,'personal_eventos')) {
            $pid=(int)($_POST['personal_id']??0);
            if($pid<=0) throw new RuntimeException('ID inválido.');

            $tipo  = trim((string)($_POST['ev_tipo'] ??''));
            $desde = date_or_null((string)($_POST['ev_desde']??''));
            $hasta = date_or_null((string)($_POST['ev_hasta']??''));
            $est   = trim((string)($_POST['ev_estado']??''));
            $tit   = trim((string)($_POST['ev_titulo']??''));
            $desc  = trim((string)($_POST['ev_desc']??''));

            if($tipo==='') throw new RuntimeException('El tipo de evento es obligatorio.');

            // data_json: campos extra según tipo
            $dataJson=null;
            if(!empty($colsPE['data_json'])) {
                $raw=trim((string)($_POST['ev_json']??''));
                if($raw!==''){
                    $t=json_decode($raw,true);
                    if(json_last_error()!==JSON_ERROR_NONE) throw new RuntimeException('JSON inválido.');
                    $dataJson=$raw;
                }
            }

            $fields=['unidad_id','personal_id','tipo','desde','hasta','estado','titulo','descripcion','created_at','created_by_id'];
            $vals=[':u',':p',':t',':de',':ha',':es',':ti',':ds','NOW()',':cb'];
            $params=[':u'=>$unidadActiva,':p'=>$pid,':t'=>$tipo,':de'=>$desde,':ha'=>$hasta,
                     ':es'=>$est?:null,':ti'=>$tit?:null,':ds'=>$desc?:null,':cb'=>$personalId?:null];
            if(!empty($colsPE['data_json'])){$fields[]='data_json';$vals[]=':js';$params[':js']=$dataJson;}

            $pdo->prepare("INSERT INTO personal_eventos (".implode(',',$fields).") VALUES (".implode(',',$vals).")")
                ->execute($params);

            $mensajeOk='Evento creado.'; $id=$pid; $tab='eventos';
        }

        /* ── Eliminar evento ── */
        if ($accion==='eliminar_evento' && table_exists($pdo,'personal_eventos')) {
            $eid=(int)($_POST['evento_id']??0);
            $pid=(int)($_POST['personal_id']??0);
            if($eid<=0||$pid<=0) throw new RuntimeException('Parámetros inválidos.');
            $pdo->prepare("DELETE FROM personal_eventos WHERE id=:id AND unidad_id=:u AND personal_id=:p LIMIT 1")
                ->execute([':id'=>$eid,':u'=>$unidadActiva,':p'=>$pid]);
            $mensajeOk='Evento eliminado.'; $id=$pid; $tab='eventos';
        }

        /* ── Eliminar documento ── */
        if ($accion==='eliminar_documento') {
            $docId=(int)($_POST['doc_id']??0);
            $pid=(int)($_POST['personal_id']??0);
            if($docId<=0||$pid<=0) throw new RuntimeException('Parámetros inválidos.');

            $st=$pdo->prepare("SELECT id, path, sanidad_id FROM personal_documentos
                               WHERE id=:id AND unidad_id=:u AND personal_id=:p LIMIT 1");
            $st->execute([':id'=>$docId,':u'=>$unidadActiva,':p'=>$pid]);
            $doc=$st->fetch(PDO::FETCH_ASSOC);
            if(!$doc) throw new RuntimeException('Documento no encontrado.');

            $path=(string)($doc['path']??'');
            $sid=isset($colsPD['sanidad_id'])?(int)($doc['sanidad_id']??0):0;

            $pdo->beginTransaction();
            if(isset($colsPD['deleted_at'])) {
                $pdo->prepare("UPDATE personal_documentos SET deleted_at=NOW()
                               WHERE id=:id AND unidad_id=:u AND personal_id=:p LIMIT 1")
                    ->execute([':id'=>$docId,':u'=>$unidadActiva,':p'=>$pid]);
            } else {
                $pdo->prepare("DELETE FROM personal_documentos WHERE id=:id AND unidad_id=:u AND personal_id=:p LIMIT 1")
                    ->execute([':id'=>$docId,':u'=>$unidadActiva,':p'=>$pid]);
            }
            if($sid>0) _sync_sanidad($pdo,$colsPD,$colsSan,$unidadActiva,$pid,$personalId?:null);
            $pdo->commit();
            if($path!=='') { $abs=$ROOT.'/'.ltrim($path,'/'); if(is_file($abs)) @unlink($abs); }
            $mensajeOk='Documento eliminado.'; $id=$pid;
        }

    } catch(Throwable $ex) {
        if($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        $mensajeError=$ex->getMessage();
    }
}

/* ═══════════════════════ FUNCIONES INTERNAS ═════════════════════════════ */
function _upload_evid(PDO $pdo, array $colsPD, string $root, string $docsRelDir,
    int $uid, int $pid, int $cbid, string $inputName, string $tipo, string $titulo,
    ?string $fecha, ?string $nota, ?int $sanidadId): int
{
    if(!isset($_FILES[$inputName])) return 0;
    $files=$_FILES[$inputName];
    $names=$files['name']??[]; $tmp=$files['tmp_name']??[]; $errs=$files['error']??[]; $sizes=$files['size']??[];
    if(!is_array($names)){$names=[$names];$tmp=[$tmp];$errs=[$errs];$sizes=[$sizes];}
    $allowedExt=['pdf','jpg','jpeg','png','webp','doc','docx'];
    $carpRel=rtrim($docsRelDir,'/').'/'.$pid;
    $carpAbs=$root.'/'.$carpRel;
    if(!is_dir($carpAbs)) @mkdir($carpAbs,0775,true);
    $n=0; $fecha=$fecha?:date('Y-m-d');
    foreach($names as $i=>$origName){
        $err=$errs[$i]??UPLOAD_ERR_NO_FILE;
        if($err===UPLOAD_ERR_NO_FILE) continue;
        if($err!==UPLOAD_ERR_OK) throw new RuntimeException('Error subiendo evidencia (cód '.$err.').');
        $size=(int)($sizes[$i]??0);
        if($size>20*1024*1024) throw new RuntimeException('Evidencia supera 20MB.');
        $tmpName=(string)($tmp[$i]??'');
        if($tmpName===''||!is_file($tmpName)) throw new RuntimeException('Archivo temporal inválido.');
        $ext=strtolower(pathinfo((string)$origName,PATHINFO_EXTENSION));
        if(!in_array($ext,$allowedExt,true)) throw new RuntimeException('Extensión no permitida: .'.$ext);
        $safe=preg_replace('/[^A-Za-z0-9_\.-]/','_',(string)$origName)?:$tipo.'.'.$ext;
        $filename=time().'_'.$pid.'_'.$i.'_'.$safe;
        $destRel=$carpRel.'/'.$filename;
        $destAbs=$root.'/'.$destRel;
        if(!move_uploaded_file($tmpName,$destAbs)) throw new RuntimeException('No se pudo mover evidencia.');
        $mime=detect_mime($destAbs);
        $bytes=@filesize($destAbs);
        $sha=function_exists('hash_file')?@hash_file('sha256',$destAbs):null;
        $fields=['unidad_id','personal_id','tipo','titulo','path','nota','fecha','created_at','created_by_id'];
        $vals=[':u',':p',':ti',':tit',':pa',':no',':fe','NOW()',':cb'];
        $params=[':u'=>$uid,':p'=>$pid,':ti'=>$tipo,':tit'=>$titulo,':pa'=>$destRel,
                 ':no'=>$nota?:null,':fe'=>$fecha,':cb'=>$cbid>0?$cbid:null];
        if(isset($colsPD['sanidad_id'])){$fields[]='sanidad_id';$vals[]=':sid';$params[':sid']=$sanidadId;}
        if(isset($colsPD['original_name'])){$fields[]='original_name';$vals[]=':on';$params[':on']=(string)$origName;}
        if(isset($colsPD['mime'])){$fields[]='mime';$vals[]=':mm';$params[':mm']=$mime;}
        if(isset($colsPD['bytes'])){$fields[]='bytes';$vals[]=':by';$params[':by']=$bytes!==false?(int)$bytes:null;}
        if(isset($colsPD['sha256'])){$fields[]='sha256';$vals[]=':sh';$params[':sh']=is_string($sha)?$sha:null;}
        $pdo->prepare("INSERT INTO personal_documentos (".implode(',',$fields).") VALUES (".implode(',',$vals).")")
            ->execute($params);
        $n++;
    }
    return $n;
}

function _sync_sanidad(PDO $pdo, array $colsPD, array $colsSan, int $uid, int $pid, ?int $ubid): void {
    $orderParts=[];
    if(isset($colsSan['created_at'])) $orderParts[]="created_at DESC";
    $orderParts[]="updated_at DESC"; $orderParts[]="id DESC";
    $orderBy=implode(', ',$orderParts);
    $st=$pdo->prepare("SELECT * FROM sanidad_partes_enfermo WHERE unidad_id=:u AND personal_id=:p ORDER BY $orderBy LIMIT 1");
    $st->execute([':u'=>$uid,':p'=>$pid]);
    $last=$st->fetch(PDO::FETCH_ASSOC);
    if(!$last){
        $pdo->prepare("UPDATE personal_unidad SET tiene_parte_enfermo=0, parte_enfermo_desde=NULL, parte_enfermo_hasta=NULL,
                       updated_at=NOW(), updated_by_id=:ub WHERE id=:p AND unidad_id=:u LIMIT 1")
            ->execute([':ub'=>$ubid,':p'=>$pid,':u'=>$uid]);
        return;
    }
    $ev=null;
    if(isset($colsSan['evento'])&&!empty($last['evento'])) $ev=(string)$last['evento'];
    else $ev=((string)($last['tiene_parte']??'no')==='si')?'parte':'alta';
    $ini=!empty($last['inicio'])?(string)$last['inicio']:null;
    $fin=!empty($last['fin'])?(string)$last['fin']:null;
    $tiene=0;
    if($ev==='parte'){
        if(isset($colsPD['sanidad_id'])){
            $whereDel=isset($colsPD['deleted_at'])?" AND deleted_at IS NULL ":'';
            $stE=$pdo->prepare("SELECT COUNT(*) FROM personal_documentos WHERE unidad_id=:u AND personal_id=:p AND sanidad_id=:s $whereDel");
            $stE->execute([':u'=>$uid,':p'=>$pid,':s'=>(int)$last['id']]);
            $tiene=(int)$stE->fetchColumn()>0?1:0;
        } else { $tiene=1; }
    }
    $cant=isset($last['cantidad'])?(int)$last['cantidad']:null;
    if($tiene===1){
        $pdo->prepare("UPDATE personal_unidad SET tiene_parte_enfermo=1, parte_enfermo_desde=:i, parte_enfermo_hasta=:f,
                       cantidad_parte_enfermo=COALESCE(:c, cantidad_parte_enfermo), updated_at=NOW(), updated_by_id=:ub
                       WHERE id=:p AND unidad_id=:u LIMIT 1")
            ->execute([':i'=>$ini,':f'=>$fin,':c'=>$cant,':ub'=>$ubid,':p'=>$pid,':u'=>$uid]);
    } else {
        $hasta=($ev==='alta')?($fin?:date('Y-m-d')):null;
        $pdo->prepare("UPDATE personal_unidad SET tiene_parte_enfermo=0, parte_enfermo_hasta=:hasta,
                       parte_enfermo_desde=CASE WHEN :ev='alta' THEN parte_enfermo_desde ELSE NULL END,
                       cantidad_parte_enfermo=COALESCE(:c, cantidad_parte_enfermo), updated_at=NOW(), updated_by_id=:ub
                       WHERE id=:p AND unidad_id=:u LIMIT 1")
            ->execute([':hasta'=>$hasta,':ev'=>$ev,':c'=>$cant,':ub'=>$ubid,':p'=>$pid,':u'=>$uid]);
    }
}

/* ═══════════════════════ CARGA DE DATOS ═════════════════════════════════ */
$persona=null; $fotoUrl=''; $listado=[];
$docs=[]; $sanidadUltimo=null; $sanidadHist=[]; $evidBySanidad=[]; $eventos=[];
$destinosAll=[]; // Para el select de destino en ficha

try {
    // Destinos de la unidad (para el selector)
    $st=$pdo->prepare("SELECT id, codigo, nombre FROM destino WHERE unidad_id=:u AND activo=1 ORDER BY nombre ASC");
    $st->execute([':u'=>$unidadActiva]);
    $destinosAll=$st->fetchAll(PDO::FETCH_ASSOC)?:[];

    if ($id<=0) {
        // Mismo orden militar que personal_lista
        $SQL_JER_L = "CASE jerarquia WHEN 'OFICIAL' THEN 1 WHEN 'SUBOFICIAL' THEN 2 WHEN 'SOLDADO' THEN 3 WHEN 'AGENTE_CIVIL' THEN 4 ELSE 5 END";
        $SQL_GRD_L = "CASE grado WHEN 'TG' THEN 9 WHEN 'GD' THEN 10 WHEN 'GB' THEN 11 WHEN 'CR' THEN 12 WHEN 'TC' THEN 13 WHEN 'MY' THEN 14 WHEN 'CT' THEN 15 WHEN 'TP' THEN 16 WHEN 'TT' THEN 17 WHEN 'ST' THEN 18 WHEN 'ST EC' THEN 19 WHEN 'SM' THEN 20 WHEN 'SP' THEN 21 WHEN 'SA' THEN 22 WHEN 'SI' THEN 23 WHEN 'SG' THEN 24 WHEN 'CI' THEN 25 WHEN 'CI EC' THEN 26 WHEN 'CI Art 11' THEN 27 WHEN 'CB' THEN 28 WHEN 'CB EC' THEN 29 WHEN 'CB Art 11' THEN 30 WHEN 'VP' THEN 31 WHEN 'VS' THEN 32 WHEN 'VS EC' THEN 33 WHEN 'SV' THEN 34 WHEN 'AC' THEN 35 ELSE 99 END";

        $sql="SELECT id, jerarquia, grado, arma, apellido_nombre, dni, destino_interno, tiene_parte_enfermo
              FROM personal_unidad WHERE unidad_id=:u";
        $params=[':u'=>$unidadActiva];
        if($q!==''){$sql.=" AND (apellido_nombre LIKE :q OR dni LIKE :q)";$params[':q']='%'.$q.'%';}
        $sql.=" ORDER BY $SQL_JER_L, $SQL_GRD_L, apellido_nombre ASC";
        $st=$pdo->prepare($sql);$st->execute($params);
        $listado=$st->fetchAll(PDO::FETCH_ASSOC)?:[];
    } else {
        $st=$pdo->prepare("SELECT * FROM personal_unidad WHERE id=:id AND unidad_id=:u LIMIT 1");
        $st->execute([':id'=>$id,':u'=>$unidadActiva]);
        $persona=$st->fetch(PDO::FETCH_ASSOC);
        if(!$persona) throw new RuntimeException("No se encontró el personal (ID={$id}).");

        $whereDel=isset($colsPD['deleted_at'])?" AND deleted_at IS NULL":"";
        $st=$pdo->prepare("SELECT * FROM personal_documentos WHERE unidad_id=:u AND personal_id=:p $whereDel
                           ORDER BY IF(tipo=:tfp,0,1), fecha DESC, id DESC");
        $st->execute([':u'=>$unidadActiva,':p'=>$id,':tfp'=>'foto_perfil']);
        $docs=$st->fetchAll(PDO::FETCH_ASSOC)?:[];

        // Foto
        $fotoPath='';
        foreach($docs as $d){if(($d['tipo']??'')==='foto_perfil'&&!empty($d['path'])){$fotoPath=(string)$d['path'];break;}}
        $fotoUrl=$fotoPath!==''?($BASE_APP_WEB.'/'.ltrim($fotoPath,'/')):$SINFOTO_URL;

        // Sanidad
        $order=[];
        if(isset($colsSan['created_at'])) $order[]="created_at DESC";
        $order[]="updated_at DESC"; $order[]="id DESC";
        $ob=implode(', ',$order);
        $st=$pdo->prepare("SELECT * FROM sanidad_partes_enfermo WHERE unidad_id=:u AND personal_id=:p ORDER BY $ob LIMIT 1");
        $st->execute([':u'=>$unidadActiva,':p'=>$id]);
        $sanidadUltimo=$st->fetch(PDO::FETCH_ASSOC)?:null;

        $st=$pdo->prepare("SELECT * FROM sanidad_partes_enfermo WHERE unidad_id=:u AND personal_id=:p ORDER BY $ob LIMIT 10");
        $st->execute([':u'=>$unidadActiva,':p'=>$id]);
        $sanidadHist=$st->fetchAll(PDO::FETCH_ASSOC)?:[];

        // Evidencias por sanidad_id
        if(!empty($sanidadHist)&&isset($colsPD['sanidad_id'])){
            $ids=array_values(array_unique(array_filter(array_map(fn($s)=>(int)($s['id']??0),$sanidadHist))));
            if($ids){
                $in=implode(',',array_fill(0,count($ids),'?'));
                $st=$pdo->prepare("SELECT * FROM personal_documentos WHERE unidad_id=? AND personal_id=? AND sanidad_id IN ($in) $whereDel ORDER BY fecha DESC,id DESC");
                $st->execute(array_merge([$unidadActiva,$id],$ids));
                foreach($st->fetchAll(PDO::FETCH_ASSOC)?:[] as $r){
                    $sid=(int)($r['sanidad_id']??0);
                    if($sid>0){if(!isset($evidBySanidad[$sid]))$evidBySanidad[$sid]=[];$evidBySanidad[$sid][]=$r;}
                }
            }
        }

        // Eventos
        if(table_exists($pdo,'personal_eventos')){
            $st=$pdo->prepare("SELECT * FROM personal_eventos WHERE unidad_id=:u AND personal_id=:p
                               ORDER BY COALESCE(desde,'9999-12-31') DESC, id DESC LIMIT 50");
            $st->execute([':u'=>$unidadActiva,':p'=>$id]);
            $eventos=$st->fetchAll(PDO::FETCH_ASSOC)?:[];
        }
    }
} catch(Throwable $ex){ $mensajeError=$ex->getMessage(); }

/* ═══════════════════════ HELPERS PARA LA VISTA ══════════════════════════ */
// Badge de color por tipo de evento
function evento_badge(string $tipo): string {
    $tipo = strtolower(trim($tipo));
    $cfg = [
        'rol_operacional' => ['bg'=>'rgba(14,165,233,.2)','border'=>'rgba(14,165,233,.6)','color'=>'#7dd3fc','label'=>'ROL OPERACIONAL','icon'=>'bi-shield-fill'],
        'vacaciones'      => ['bg'=>'rgba(34,197,94,.2)', 'border'=>'rgba(34,197,94,.6)', 'color'=>'#86efac','label'=>'VACACIONES','icon'=>'bi-sun'],
        'licencia'        => ['bg'=>'rgba(251,191,36,.2)','border'=>'rgba(251,191,36,.6)','color'=>'#fcd34d','label'=>'LICENCIA','icon'=>'bi-calendar-check'],
        'comision'        => ['bg'=>'rgba(168,85,247,.2)','border'=>'rgba(168,85,247,.6)','color'=>'#d8b4fe','label'=>'COMISIÓN','icon'=>'bi-geo-alt'],
        'plan_llamada'    => ['bg'=>'rgba(239,68,68,.2)', 'border'=>'rgba(239,68,68,.6)', 'color'=>'#fca5a5','label'=>'PLAN LLAMADA','icon'=>'bi-telephone'],
        'retiro'          => ['bg'=>'rgba(100,116,139,.2)','border'=>'rgba(100,116,139,.6)','color'=>'#94a3b8','label'=>'RETIRO','icon'=>'bi-door-open'],
    ];
    $c = $cfg[$tipo] ?? ['bg'=>'rgba(148,163,184,.15)','border'=>'rgba(148,163,184,.4)','color'=>'#94a3b8','label'=>strtoupper($tipo),'icon'=>'bi-tag'];
    return "<span style=\"display:inline-flex;align-items:center;gap:.3rem;padding:.2rem .55rem;border-radius:6px;font-size:.72rem;font-weight:900;
             background:{$c['bg']};border:1px solid {$c['border']};color:{$c['color']};\">"
          ."<i class=\"bi {$c['icon']}\"></i> ".e($c['label'])."</span>";
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Personal · Ficha</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= e($ASSETS_WEB) ?>/css/theme-602.css">
<link rel="icon" href="<?= e($ESCUDO) ?>">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
  body {
    background: url("<?= e($IMG_BG) ?>") no-repeat center center fixed;
    background-size:cover; background-color:#020617;
    color:#e5e7eb; font-family:system-ui,-apple-system,"Segoe UI",sans-serif; margin:0; padding:0;
  }
  .page-wrap { padding:16px; }
  .container-main { max-width:1500px; margin:auto; }
  .panel {
    background:rgba(15,17,23,.94); border:1px solid rgba(148,163,184,.38);
    border-radius:18px; padding:18px 22px;
    box-shadow:0 18px 40px rgba(0,0,0,.75),inset 0 1px 0 rgba(255,255,255,.04);
  }
  .brand-hero { padding:10px 0; }
  .hero-inner  { display:flex; align-items:center; justify-content:space-between; gap:12px; }
  .help-small  { font-size:.78rem; color:#b7c3d6; }
  .card-sub {
    background:rgba(15,23,42,.96); border-radius:14px;
    border:1px solid rgba(148,163,184,.32); padding:12px 14px; margin-bottom:12px;
  }
  .section-title { font-size:.92rem; font-weight:800; margin-bottom:4px; }
  .section-sub   { font-size:.78rem; color:#9ca3af; margin-bottom:10px; }
  .tbl { --bs-table-bg:rgba(15,23,42,.9); --bs-table-striped-bg:rgba(30,64,175,.22);
         --bs-table-border-color:rgba(148,163,184,.38); color:#e5e7eb; font-size:.82rem; }
  .tbl th, .tbl td { white-space:nowrap; }

  /* Foto */
  .foto-wrap {
    width:180px; height:180px; border-radius:12px; overflow:hidden;
    border:1px solid rgba(148,163,184,.6); background:#020617;
    display:flex; align-items:center; justify-content:center; position:relative;
    box-shadow:0 0 0 1px rgba(15,23,42,1),0 8px 20px rgba(0,0,0,.7);
  }
  .foto-wrap img { width:100%; height:100%; object-fit:cover; display:block; }
  .foto-ph { font-size:.75rem; color:#6b7280; text-align:center; padding:6px; }
  .foto-overlay {
    position:absolute; bottom:0; left:0; right:0; background:rgba(15,23,42,.8);
    font-size:.65rem; color:#e5e7eb; text-align:center; padding:3px 4px; pointer-events:none;
  }

  /* Tabs */
  .tab-pill {
    display:inline-flex; align-items:center; gap:6px;
    padding:.42rem .85rem; border-radius:999px; border:1px solid rgba(148,163,184,.32);
    background:rgba(15,23,42,.7); color:#e5e7eb; font-weight:800; font-size:.78rem;
    text-decoration:none; transition:border-color .15s;
  }
  .tab-pill.active { border-color:rgba(34,197,94,.65); box-shadow:0 0 0 1px rgba(34,197,94,.2); }
  .tab-pill:hover  { border-color:rgba(148,163,184,.6); color:#fff; }

  /* Inputs dark */
  .form-control,.form-select {
    background:rgba(255,255,255,.06); border:1px solid rgba(148,163,184,.28); color:#e5e7eb;
  }
  .form-control:focus,.form-select:focus {
    background:rgba(255,255,255,.09); color:#fff;
    border-color:rgba(120,170,255,.5); box-shadow:0 0 0 .18rem rgba(90,140,255,.12);
  }
  .form-select option { background:#0f172a; color:#e5e7eb; }
  .form-label { font-size:.78rem; color:#9ca3af; margin-bottom:.3rem; }

  /* Evento card */
  .ev-card {
    background:rgba(15,23,42,.8); border:1px solid rgba(148,163,184,.22);
    border-radius:10px; padding:10px 12px; margin-bottom:8px;
  }
  .ev-card:hover { border-color:rgba(148,163,184,.4); }

  code { color:#dbeafe; }
  /* Badges jerarquía/grado (usados en el listado) */
  .badge-jer { display:inline-block; padding:.15rem .45rem; border-radius:4px; font-size:.65rem; font-weight:800; letter-spacing:.03em; }
  .badge-of  { background:rgba(99,102,241,.25); border:1px solid rgba(99,102,241,.5); color:#a5b4fc; }
  .badge-sof { background:rgba(245,158,11,.2);  border:1px solid rgba(245,158,11,.4); color:#fcd34d; }
  .badge-sol { background:rgba(34,197,94,.15);  border:1px solid rgba(34,197,94,.35); color:#86efac; }
  .badge-cv  { background:rgba(148,163,184,.15);border:1px solid rgba(148,163,184,.3);color:#cbd5e1; }
  .fila-parte td { background:rgba(239,68,68,.06) !important; }
</style>
</head>
<body>

<header class="brand-hero">
  <div class="hero-inner container-main px-3">
    <div class="d-flex align-items-center gap-3">
      <img src="<?= e($ESCUDO) ?>" alt="Escudo" style="height:50px;width:auto;" onerror="this.style.display='none'">
      <div>
        <div style="font-weight:900;font-size:1.05rem;"><?= e($NOMBRE) ?></div>
        <div style="color:#cbd5f5;font-size:.82rem;"><?= e($LEYENDA) ?></div>
      </div>
    </div>
    <div class="d-flex gap-2">
      <a href="personal_lista.php" class="btn btn-success btn-sm fw-bold">Volver</a>
      <a href="../inicio.php"      class="btn btn-success btn-sm fw-bold">Inicio</a>
    </div>
  </div>
</header>

<div class="page-wrap"><div class="container-main">
<div class="panel">

<?php if($mensajeOk!==''): ?>
  <div class="alert alert-success py-2 mb-3"><?= e($mensajeOk) ?></div>
<?php endif; ?>
<?php if($mensajeError!==''): ?>
  <div class="alert alert-danger py-2 mb-3"><?= e($mensajeError) ?></div>
<?php endif; ?>

<?php if($id<=0): /* ════ LISTADO ════ */ ?>

  <div class="d-flex justify-content-between align-items-end flex-wrap gap-2 mb-3">
    <div>
      <div style="font-weight:900;font-size:1.05rem;">Seleccionar personal</div>
      <div class="help-small">Buscá y hacé click en "Ver ficha".</div>
    </div>
    <form method="get" class="d-flex gap-2" style="max-width:380px;">
      <input class="form-control form-control-sm" name="q" value="<?= e($q) ?>" placeholder="Nombre o DNI...">
      <button class="btn btn-sm btn-success" type="submit">Buscar</button>
    </form>
  </div>
  <div class="table-responsive">
    <table class="table table-sm table-dark tbl align-middle" style="min-width:700px;">
      <thead>
        <tr style="background:rgba(30,41,59,.98)!important;border-bottom:2px solid rgba(59,130,246,.4)!important;">
          <th style="color:#93c5fd;font-size:.74rem;">#</th>
          <th style="color:#93c5fd;font-size:.74rem;white-space:nowrap;">Grado</th>
          <th style="color:#93c5fd;font-size:.74rem;white-space:nowrap;">Arma</th>
          <th style="color:#93c5fd;font-size:.74rem;">Apellido y Nombre</th>
          <th style="color:#93c5fd;font-size:.74rem;white-space:nowrap;">DNI</th>
          <th style="color:#93c5fd;font-size:.74rem;white-space:nowrap;">Destino</th>
          <th style="color:#93c5fd;font-size:.74rem;white-space:nowrap;">Parte</th>
          <th class="text-end" style="color:#93c5fd;font-size:.74rem;">Acción</th>
        </tr>
      </thead>
      <tbody>
      <?php if(!$listado): ?>
        <tr><td colspan="8" class="text-center text-muted py-4">Sin registros.</td></tr>
      <?php else:
        $jerActualL = null; $nroL = 0;
        $jerBadgeL  = ['OFICIAL'=>'badge-of','SUBOFICIAL'=>'badge-sof','SOLDADO'=>'badge-sol','AGENTE_CIVIL'=>'badge-cv'];
        $jerLabelsL = ['OFICIAL'=>'OFICIALES','SUBOFICIAL'=>'SUBOFICIALES','SOLDADO'=>'SOLDADOS','AGENTE_CIVIL'=>'AGENTES CIVILES'];
        foreach($listado as $p):
          $jerL = $p['jerarquia'] ?? '';
          $tienePL = (int)($p['tiene_parte_enfermo']??0) === 1;
          $badgeClassL = $jerBadgeL[$jerL] ?? 'badge-cv';
          if($jerL !== $jerActualL):
            $jerActualL = $jerL; $nroL = 0;
      ?>
        <tr style="background:rgba(30,41,59,.96)!important;border-top:2px solid rgba(59,130,246,.3)!important;">
          <td colspan="8" style="padding:.4rem .7rem!important;">
            <span class="badge-jer <?= $badgeClassL ?>" style="font-size:.7rem;font-weight:900;letter-spacing:.05em;">
              <?= e($jerLabelsL[$jerL] ?? strtoupper($jerL)) ?>
            </span>
          </td>
        </tr>
      <?php endif; $nroL++; ?>
        <tr class="<?= $tienePL ? 'fila-parte' : '' ?>">
          <td style="color:#6b7280;font-size:.72rem;text-align:center;"><?= $nroL ?></td>
          <td><span class="badge-jer <?= $badgeClassL ?>"><?= e($p['grado']??'—') ?></span></td>
          <td style="color:#94a3b8;font-size:.78rem;"><?= e($p['arma']??'') ?></td>
          <td>
            <a style="color:#e5e7eb;font-weight:700;text-decoration:none;"
               onmouseover="this.style.color='#7dd3fc'" onmouseout="this.style.color='#e5e7eb'"
               href="?id=<?= (int)$p['id'] ?>&tab=ficha">
              <?= e($p['apellido_nombre']??'') ?>
            </a>
          </td>
          <td style="font-family:monospace;font-size:.75rem;color:#94a3b8;"><?= e($p['dni']??'') ?></td>
          <td style="font-size:.76rem;color:#b7c3d6;"><?= e($p['destino_interno']??'') ?: '<span style="color:#374151;">—</span>' ?></td>
          <td>
            <?php if($tienePL): ?>
              <span style="background:rgba(239,68,68,.2);border:1px solid rgba(239,68,68,.5);color:#fca5a5;border-radius:4px;padding:.1rem .4rem;font-size:.65rem;font-weight:800;">PARTE</span>
            <?php else: ?>
              <span style="color:#374151;font-size:.75rem;">—</span>
            <?php endif; ?>
          </td>
          <td class="text-end">
            <a class="btn btn-sm btn-outline-info py-0 px-2" style="font-size:.75rem;"
               href="?id=<?= (int)$p['id'] ?>&tab=ficha">Ver ficha</a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

<?php else: /* ════ FICHA ════ */
    $linea = trim(($persona['grado']??'').' '.($persona['arma']??'').' '.($persona['apellido_nombre']??''));
    $tieneParte  = ((int)($persona['tiene_parte_enfermo']??0)===1);
    $parteIni    = $persona['parte_enfermo_desde']??null;
    $parteFin    = $persona['parte_enfermo_hasta']??null;
    $cantParte   = (int)($persona['cantidad_parte_enfermo']??0);
    $destinoId   = (int)($persona['destino_id']??0);
?>

  <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
    <div>
      <div style="font-weight:900;font-size:1.1rem;">Ficha de personal</div>
      <div class="help-small">
        <?= e($linea) ?> · DNI: <b><?= e($persona['dni']??'') ?></b>
        <?php if(!empty($persona['destino_interno'])): ?> · Destino: <b><?= e($persona['destino_interno']) ?></b><?php endif; ?>
      </div>
    </div>
  </div>

  <!-- TABS -->
  <div class="d-flex flex-wrap gap-2 my-3">
    <a class="tab-pill <?= $tab==='ficha'?'active':'' ?>" href="?id=<?= $id ?>&tab=ficha"><i class="bi bi-person-vcard"></i> Datos</a>
    <a class="tab-pill <?= $tab==='sanidad'?'active':'' ?>" href="?id=<?= $id ?>&tab=sanidad"><i class="bi bi-heart-pulse"></i> Sanidad</a>
    <a class="tab-pill <?= $tab==='docs'?'active':'' ?>" href="?id=<?= $id ?>&tab=docs"><i class="bi bi-folder2"></i> Documentos</a>
    <?php if(table_exists($pdo,'personal_eventos')): ?>
    <a class="tab-pill <?= $tab==='eventos'?'active':'' ?>" href="?id=<?= $id ?>&tab=eventos"><i class="bi bi-calendar-event"></i> Eventos y Roles</a>
    <?php endif; ?>
  </div>

  <!-- ┌─ FOTO ─────────────────────────────────────────────────────────────┐ -->
  <div class="d-flex flex-column align-items-center gap-2 mb-4">
    <div class="foto-wrap">
      <?php if($fotoUrl): ?>
        <img src="<?= e($fotoUrl) ?>" alt="Foto de <?= e($linea) ?>">
        <div class="foto-overlay">4×4</div>
      <?php else: ?>
        <div class="foto-ph"><i class="bi bi-person-circle" style="font-size:3rem;opacity:.3;display:block;"></i>Sin foto</div>
      <?php endif; ?>
    </div>
    <?php if($esAdmin): ?>
    <form method="post" enctype="multipart/form-data" class="text-center" style="max-width:300px;">
      <?php csrf_if_exists(); ?>
      <input type="hidden" name="accion" value="subir_foto">
      <input type="hidden" name="personal_id" value="<?= $id ?>">
      <input type="file" name="foto_archivo" class="form-control form-control-sm mb-2"
             accept="image/jpeg,image/png,image/webp" required style="font-size:.76rem;">
      <button class="btn btn-sm btn-outline-success w-100" type="submit">
        <i class="bi bi-camera me-1"></i> Actualizar foto
      </button>
      <div class="help-small mt-1">Se guarda como <code>APELLIDO_NOMBRE_YYYYMMDD_id.ext</code></div>
    </form>
    <?php endif; ?>
  </div>
  <!-- └──────────────────────────────────────────────────────────────────── -->

  <!-- ══════════════════════════ TAB: DATOS ══════════════════════════════ -->
  <?php if($tab==='ficha'): ?>
  <div class="card-sub">
    <div class="section-title"><i class="bi bi-person-lines-fill me-1 text-info"></i> Datos del personal</div>

    <?php if($esAdmin): ?>
    <form method="post" class="row g-2">
      <?php csrf_if_exists(); ?>
      <input type="hidden" name="accion" value="guardar_personal">
      <input type="hidden" name="personal_id" value="<?= $id ?>">

      <!-- Jerarquía / Grado / Arma -->
      <div class="col-md-3">
        <label class="form-label">Jerarquía</label>
        <select name="jerarquia" class="form-select form-select-sm">
          <option value="">— —</option>
          <?php foreach(['OFICIAL','SUBOFICIAL','SOLDADO','AGENTE_CIVIL'] as $j): ?>
            <option value="<?= $j ?>" <?= ($persona['jerarquia']??'')===$j?'selected':'' ?>><?= $j ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Grado</label>
        <input class="form-control form-control-sm" name="grado" value="<?= e($persona['grado']??'') ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label">Arma / Cuerpo</label>
        <input class="form-control form-control-sm" name="arma" value="<?= e($persona['arma']??'') ?>">
      </div>
      <div class="col-md-5">
        <label class="form-label">Apellido y Nombre <span class="text-danger">*</span></label>
        <input class="form-control form-control-sm" name="apellido_nombre" required value="<?= e($persona['apellido_nombre']??'') ?>">
      </div>

      <!-- DNI / CUIL / Fecha nac / Sexo -->
      <div class="col-md-2">
        <label class="form-label">DNI <span class="text-danger">*</span></label>
        <input class="form-control form-control-sm" name="dni" required value="<?= e($persona['dni']??'') ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">CUIL</label>
        <input class="form-control form-control-sm" name="cuil" value="<?= e($persona['cuil']??'') ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Fecha de nacimiento</label>
        <input type="date" class="form-control form-control-sm" name="fecha_nac" value="<?= e($persona['fecha_nac']??'') ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label">Sexo</label>
        <select class="form-select form-select-sm" name="sexo">
          <option value="">—</option>
          <option value="M" <?= ($persona['sexo']??'')==='M'?'selected':'' ?>>Masculino</option>
          <option value="F" <?= ($persona['sexo']??'')==='F'?'selected':'' ?>>Femenino</option>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Estado civil</label>
        <input class="form-control form-control-sm" name="estado_civil" value="<?= e($persona['estado_civil']??'') ?>">
      </div>

      <!-- Destino / Función -->
      <div class="col-md-3">
        <label class="form-label">Área / Destino</label>
        <select class="form-select form-select-sm" name="destino_id">
          <option value="">— Sin asignar —</option>
          <?php foreach($destinosAll as $dst): ?>
            <option value="<?= (int)$dst['id'] ?>" <?= $destinoId===(int)$dst['id']?'selected':'' ?>>
              <?= e($dst['codigo']??'') ?> · <?= e($dst['nombre']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Destino interno / Dependencia</label>
        <input class="form-control form-control-sm" name="destino_interno" value="<?= e($persona['destino_interno']??'') ?>">
      </div>
      <div class="col-md-5">
        <label class="form-label">Función</label>
        <input class="form-control form-control-sm" name="funcion" value="<?= e($persona['funcion']??'') ?>">
      </div>

      <!-- Domicilio / Hijos / Fecha alta -->
      <div class="col-md-6">
        <label class="form-label">Domicilio</label>
        <input class="form-control form-control-sm" name="domicilio" value="<?= e($persona['domicilio']??'') ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label">Hijos</label>
        <input type="number" class="form-control form-control-sm" name="hijos" min="0" value="<?= e($persona['hijos']??'') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Fecha de alta en la unidad</label>
        <input type="date" class="form-control form-control-sm" name="fecha_alta" value="<?= e($persona['fecha_alta']??'') ?>">
      </div>

      <!-- Teléfono / Correo -->
      <div class="col-md-4">
        <label class="form-label">Teléfono</label>
        <input class="form-control form-control-sm" name="telefono" value="<?= e($persona['telefono']??'') ?>">
      </div>
      <div class="col-md-8">
        <label class="form-label">Correo electrónico</label>
        <input class="form-control form-control-sm" name="correo" value="<?= e($persona['correo']??'') ?>">
      </div>

      <!-- Observaciones -->
      <div class="col-12">
        <label class="form-label">Observaciones</label>
        <textarea class="form-control form-control-sm" name="observaciones" rows="2"><?= e($persona['observaciones']??'') ?></textarea>
      </div>

      <div class="col-12 text-end">
        <button class="btn btn-sm btn-success fw-bold" type="submit">
          <i class="bi bi-floppy me-1"></i> Guardar datos
        </button>
      </div>
    </form>
    <?php else: ?>
      <div class="help-small">Solo lectura (sin permisos de edición).</div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- ══════════════════════════ TAB: SANIDAD ═════════════════════════════ -->
  <?php if($tab==='sanidad'): ?>
  <div class="card-sub">
    <div class="section-title"><i class="bi bi-heart-pulse me-1 text-danger"></i> Sanidad · Estado actual</div>
    <div class="d-flex flex-wrap gap-3 mb-3 align-items-center">
      <?php if($tieneParte): ?>
        <span class="badge bg-warning text-dark px-3 py-2 fs-6">🩺 TIENE PARTE</span>
      <?php else: ?>
        <span class="badge bg-success px-3 py-2 fs-6">✓ SIN PARTE</span>
      <?php endif; ?>
      <span class="help-small">Inicio: <b><?= e(fmt_date($parteIni)) ?></b></span>
      <span class="help-small">Fin: <b><?= e(fmt_date($parteFin)) ?></b></span>
      <span class="help-small">Total partes: <b><?= $cantParte ?></b></span>
    </div>

    <?php if($esAdmin): ?>
    <form method="post" enctype="multipart/form-data" class="row g-2">
      <?php csrf_if_exists(); ?>
      <input type="hidden" name="accion" value="guardar_sanidad">
      <input type="hidden" name="personal_id" value="<?= $id ?>">
      <div class="col-md-4">
        <label class="form-label">Acción</label>
        <select class="form-select form-select-sm" name="tiene_parte">
          <option value="si">Registrar parte de enfermo</option>
          <option value="no">Registrar alta de parte</option>
        </select>
        <div class="help-small mt-1">Sin evidencia: actualiza el último evento sin incrementar contador.</div>
      </div>
      <div class="col-md-3">
        <label class="form-label">Inicio</label>
        <input type="date" class="form-control form-control-sm" name="inicio">
      </div>
      <div class="col-md-3">
        <label class="form-label">Fin</label>
        <input type="date" class="form-control form-control-sm" name="fin">
      </div>
      <div class="col-md-2">&nbsp;</div>
      <div class="col-12">
        <label class="form-label">Observaciones</label>
        <input class="form-control form-control-sm" name="observaciones_sanidad" placeholder="Diagnóstico, detalle...">
      </div>
      <div class="col-12">
        <label class="form-label">Evidencia (PDF / imagen — opcional)</label>
        <input type="file" class="form-control form-control-sm" name="sanidad_evidencias[]" multiple
               accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx">
      </div>
      <div class="col-12 text-end">
        <button class="btn btn-sm btn-outline-success fw-bold" type="submit">
          <i class="bi bi-floppy me-1"></i> Guardar sanidad
        </button>
      </div>
    </form>
    <?php else: ?>
      <div class="help-small">Sin permisos de modificación.</div>
    <?php endif; ?>
  </div>

  <div class="card-sub">
    <div class="section-title">Historial de sanidad (últimos 10)</div>
    <?php if(!$sanidadHist): ?>
      <div class="text-muted">Sin historial.</div>
    <?php else: ?>
    <div class="accordion" id="accSan">
      <?php foreach($sanidadHist as $s):
          $sid=(int)($s['id']??0);
          $ev=isset($colsSan['evento'])&&!empty($s['evento'])?(string)$s['evento']:
              ((string)($s['tiene_parte']??'no')==='si'?'parte':'alta');
          $badge=$ev==='parte'
              ? '<span class="badge bg-warning text-dark">PARTE</span>'
              : '<span class="badge bg-info text-dark">ALTA</span>';
          $evids=$evidBySanidad[$sid]??[];
      ?>
      <div class="accordion-item" style="background:rgba(15,23,42,.85);border:1px solid rgba(148,163,184,.22);">
        <h2 class="accordion-header">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                  data-bs-target="#san<?= $sid ?>" style="background:rgba(15,23,42,.92);color:#e5e7eb;">
            <?= $badge ?>&nbsp;
            <span style="font-weight:900;"><?= e(fmt_date($s['inicio']??null)) ?> → <?= e(fmt_date($s['fin']??null)) ?></span>
            <span class="help-small ms-2">· Cant: <?= (int)($s['cantidad']??0) ?></span>
            <span class="help-small ms-2">· <?= count($evids) ?> evidencia(s)</span>
          </button>
        </h2>
        <div id="san<?= $sid ?>" class="accordion-collapse collapse">
          <div class="accordion-body" style="color:#e5e7eb;">
            <?php if(!empty($s['observaciones'])): ?><div class="help-small mb-2"><b>Obs:</b> <?= e($s['observaciones']) ?></div><?php endif; ?>
            <?php if(!$evids): ?>
              <div class="text-muted small">Sin evidencias vinculadas.</div>
            <?php else: ?>
            <div class="table-responsive">
              <table class="table table-sm table-dark tbl align-middle mb-0">
                <thead><tr><th>Tipo</th><th>Título</th><th>Fecha</th><th>Tamaño</th><th>Ver</th><th class="text-end">Acción</th></tr></thead>
                <tbody>
                  <?php foreach($evids as $d):
                    $url=!empty($d['path'])?($BASE_APP_WEB.'/'.ltrim($d['path'],'/')):null;
                  ?>
                  <tr>
                    <td><?= e($d['tipo']??'') ?></td>
                    <td><?= e($d['titulo']??'(s/t)') ?></td>
                    <td><?= e(fmt_date($d['fecha']??null)) ?></td>
                    <td><?= e(fmt_bytes(isset($d['bytes'])?(int)$d['bytes']:null)) ?></td>
                    <td><?php if($url): ?><a class="btn btn-sm btn-outline-light py-0 px-2" href="<?= e($url) ?>" target="_blank">Ver</a><?php else: ?>—<?php endif; ?></td>
                    <td class="text-end">
                      <?php if($esAdmin): ?>
                      <form method="post" class="d-inline form-del-doc">
                        <?php csrf_if_exists(); ?>
                        <input type="hidden" name="accion" value="eliminar_documento">
                        <input type="hidden" name="personal_id" value="<?= $id ?>">
                        <input type="hidden" name="doc_id" value="<?= (int)($d['id']??0) ?>">
                        <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 btn-del">Eliminar</button>
                      </form>
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

  <!-- ══════════════════════════ TAB: DOCUMENTOS ══════════════════════════ -->
  <?php if($tab==='docs'): ?>
  <div class="card-sub">
    <div class="section-title"><i class="bi bi-folder2-open me-1 text-warning"></i> Documentos del personal</div>
    <?php if($esAdmin): ?>
    <form method="post" enctype="multipart/form-data" class="row g-2 mb-4">
      <?php csrf_if_exists(); ?>
      <input type="hidden" name="accion" value="subir_documento">
      <input type="hidden" name="personal_id" value="<?= $id ?>">
      <div class="col-md-4">
        <label class="form-label">Tipo</label>
        <select class="form-select form-select-sm" name="tipo">
          <option value="anexo27">Anexo 27</option>
          <option value="administrativo">Administrativo</option>
          <option value="otros" selected>Otros</option>
        </select>
      </div>
      <div class="col-md-8">
        <label class="form-label">Título</label>
        <input class="form-control form-control-sm" name="titulo" placeholder="Ej: Certificado médico, Nota...">
      </div>
      <div class="col-md-4">
        <label class="form-label">Fecha</label>
        <input type="date" class="form-control form-control-sm" name="fecha">
      </div>
      <div class="col-md-8">
        <label class="form-label">Nota</label>
        <input class="form-control form-control-sm" name="nota" placeholder="Observación breve...">
      </div>
      <?php if(isset($colsPD['evento_id'])&&table_exists($pdo,'personal_eventos')&&$eventos): ?>
      <div class="col-12">
        <label class="form-label">Vincular a evento (opcional)</label>
        <select class="form-select form-select-sm" name="evento_id">
          <option value="0">(sin evento)</option>
          <?php foreach($eventos as $ev): ?>
            <option value="<?= (int)$ev['id'] ?>">
              <?= e(($ev['tipo']??'').' · '.fmt_date($ev['desde']??null).' → '.fmt_date($ev['hasta']??null).' · '.($ev['titulo']??'')) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <div class="col-12">
        <label class="form-label">Archivo (PDF / Word / Imagen)</label>
        <input type="file" class="form-control form-control-sm" name="archivo" required accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx">
      </div>
      <div class="col-12 text-end">
        <button class="btn btn-sm btn-outline-success fw-bold" type="submit">
          <i class="bi bi-upload me-1"></i> Subir documento
        </button>
      </div>
    </form>
    <?php endif; ?>
    <div class="table-responsive">
      <table class="table table-sm table-dark tbl align-middle">
        <thead><tr><th>Tipo</th><th>Título</th><th>Fecha</th><th>Tamaño</th><th>Ver</th><th class="text-end">Acción</th></tr></thead>
        <tbody>
        <?php if(!$docs): ?>
          <tr><td colspan="6" class="text-center text-muted py-3">Sin documentos.</td></tr>
        <?php else: foreach($docs as $d):
          $url=!empty($d['path'])?($BASE_APP_WEB.'/'.ltrim($d['path'],'/')):null;
        ?>
          <tr>
            <td><?= e($d['tipo']??'') ?></td>
            <td>
              <?= e($d['titulo']??'(s/t)') ?>
              <?php if(!empty($d['nota'])): ?><div class="help-small"><?= e($d['nota']) ?></div><?php endif; ?>
              <?php if(isset($d['sanidad_id'])&&(int)$d['sanidad_id']>0): ?>
                <div class="help-small" style="color:#f87171;">Sanidad #<?= (int)$d['sanidad_id'] ?></div>
              <?php endif; ?>
            </td>
            <td><?= e(fmt_date($d['fecha']??null)) ?></td>
            <td><?= e(fmt_bytes(isset($d['bytes'])?(int)$d['bytes']:null)) ?></td>
            <td><?php if($url): ?><a class="btn btn-sm btn-outline-light py-0 px-2" href="<?= e($url) ?>" target="_blank">Ver</a><?php else: ?>—<?php endif; ?></td>
            <td class="text-end">
              <?php if($esAdmin): ?>
              <form method="post" class="d-inline form-del-doc">
                <?php csrf_if_exists(); ?>
                <input type="hidden" name="accion" value="eliminar_documento">
                <input type="hidden" name="personal_id" value="<?= $id ?>">
                <input type="hidden" name="doc_id" value="<?= (int)($d['id']??0) ?>">
                <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 btn-del">Eliminar</button>
              </form>
              <?php else: ?>—<?php endif; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <!-- ══════════════════════════ TAB: EVENTOS Y ROLES ═════════════════════ -->
  <?php if($tab==='eventos' && table_exists($pdo,'personal_eventos')): ?>
  <div class="card-sub">
    <div class="section-title"><i class="bi bi-calendar-event me-1 text-info"></i> Eventos y roles operacionales</div>
    <div class="section-sub">
      Tipos disponibles: <code>rol_operacional</code>, <code>vacaciones</code>, <code>licencia</code>,
      <code>comision</code>, <code>plan_llamada</code>, <code>retiro</code>, o cualquier texto libre.
    </div>

    <?php if($esAdmin): ?>
    <form method="post" class="row g-2 mb-4 p-3" style="background:rgba(2,6,23,.5);border-radius:10px;">
      <?php csrf_if_exists(); ?>
      <input type="hidden" name="accion" value="crear_evento">
      <input type="hidden" name="personal_id" value="<?= $id ?>">

      <div class="col-12"><div style="font-size:.82rem;font-weight:800;color:#93c5fd;margin-bottom:4px;">Nuevo evento / rol</div></div>

      <div class="col-md-3">
        <label class="form-label">Tipo <span class="text-danger">*</span></label>
        <input class="form-control form-control-sm" name="ev_tipo" list="tiposEvento" placeholder="rol_operacional..." required>
        <datalist id="tiposEvento">
          <option value="rol_operacional">
          <option value="vacaciones">
          <option value="licencia">
          <option value="comision">
          <option value="plan_llamada">
          <option value="retiro">
        </datalist>
      </div>
      <div class="col-md-3">
        <label class="form-label">Título / Rol</label>
        <input class="form-control form-control-sm" name="ev_titulo" placeholder="Ej: Jefe de Guardia, Auxiliar S-1...">
      </div>
      <div class="col-md-2">
        <label class="form-label">Desde</label>
        <input type="date" class="form-control form-control-sm" name="ev_desde">
      </div>
      <div class="col-md-2">
        <label class="form-label">Hasta</label>
        <input type="date" class="form-control form-control-sm" name="ev_hasta">
      </div>
      <div class="col-md-2">
        <label class="form-label">Estado</label>
        <input class="form-control form-control-sm" name="ev_estado" placeholder="activo / cumplido...">
      </div>
      <div class="col-12">
        <label class="form-label">Descripción / Detalle</label>
        <input class="form-control form-control-sm" name="ev_desc" placeholder="Detalles adicionales, observaciones...">
      </div>
      <?php if(!empty($colsPE['data_json'])): ?>
      <div class="col-12">
        <label class="form-label">Datos extra (JSON opcional)</label>
        <textarea class="form-control form-control-sm" name="ev_json" rows="2"
                  placeholder='{"puesto":"Jefe","turno":"mañana"}'></textarea>
      </div>
      <?php endif; ?>
      <div class="col-12 text-end">
        <button class="btn btn-sm btn-success fw-bold" type="submit">
          <i class="bi bi-plus-circle me-1"></i> Crear evento
        </button>
      </div>
    </form>
    <?php endif; ?>

    <!-- Lista de eventos agrupados por tipo -->
    <?php if(!$eventos): ?>
      <div class="text-muted small">Sin eventos registrados.</div>
    <?php else:
      // Agrupar por tipo
      $grupos = [];
      foreach($eventos as $ev) {
          $t = strtolower(trim((string)($ev['tipo']??'otros')));
          $grupos[$t][] = $ev;
      }
      // Orden de display
      $tiposOrden=['rol_operacional','plan_llamada','comision','vacaciones','licencia','retiro'];
      foreach($tiposOrden as $to) if(!array_key_exists($to,$grupos)) {} // solo para orden
      uksort($grupos, function($a,$b) use($tiposOrden){
          $ia=array_search($a,$tiposOrden); $ib=array_search($b,$tiposOrden);
          if($ia===false) $ia=99; if($ib===false) $ib=99; return $ia<=>$ib;
      });
    ?>
    <?php foreach($grupos as $tipoGrupo => $evList): ?>
      <div class="mb-3">
        <div class="d-flex align-items-center gap-2 mb-2">
          <?= evento_badge($tipoGrupo) ?>
          <span class="help-small"><?= count($evList) ?> registro<?= count($evList)!==1?'s':'' ?></span>
        </div>
        <?php foreach($evList as $ev): ?>
        <div class="ev-card">
          <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
              <?php if(!empty($ev['titulo'])): ?>
                <div style="font-weight:700;font-size:.88rem;"><?= e($ev['titulo']) ?></div>
              <?php endif; ?>
              <div class="d-flex flex-wrap gap-2 mt-1">
                <?php if($ev['desde']||$ev['hasta']): ?>
                  <span class="help-small">
                    <i class="bi bi-calendar3"></i>
                    <?= e(fmt_date($ev['desde'])) ?> → <?= e(fmt_date($ev['hasta'])) ?>
                  </span>
                <?php endif; ?>
                <?php if(!empty($ev['estado'])): ?>
                  <span style="font-size:.72rem;padding:.15rem .45rem;border-radius:4px;background:rgba(34,197,94,.15);border:1px solid rgba(34,197,94,.4);color:#86efac;">
                    <?= e($ev['estado']) ?>
                  </span>
                <?php endif; ?>
              </div>
              <?php if(!empty($ev['descripcion'])): ?>
                <div class="help-small mt-1"><?= e($ev['descripcion']) ?></div>
              <?php endif; ?>
              <?php if(!empty($ev['data_json'])): ?>
                <div class="help-small mt-1" style="color:#a5b4fc;">
                  <code style="font-size:.7rem;"><?= e((string)$ev['data_json']) ?></code>
                </div>
              <?php endif; ?>
            </div>
            <?php if($esAdmin): ?>
            <form method="post" class="d-inline form-del-ev flex-shrink-0">
              <?php csrf_if_exists(); ?>
              <input type="hidden" name="accion" value="eliminar_evento">
              <input type="hidden" name="personal_id" value="<?= $id ?>">
              <input type="hidden" name="evento_id" value="<?= (int)($ev['id']??0) ?>">
              <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 btn-del-ev">
                <i class="bi bi-trash3"></i>
              </button>
            </form>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; endif; ?>
  </div>
  <?php endif; ?>

<?php endif; /* fin FICHA */ ?>

</div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  // Confirmar eliminación de documentos
  document.querySelectorAll('.form-del-doc .btn-del').forEach(btn => {
    btn.addEventListener('click', e => {
      e.preventDefault();
      const form = btn.closest('form');
      Swal.fire({
        title:'¿Eliminar documento?', text:'Esta acción no se puede deshacer.',
        icon:'warning', showCancelButton:true,
        confirmButtonText:'Sí, eliminar', cancelButtonText:'Cancelar',
        confirmButtonColor:'#dc3545', cancelButtonColor:'#6c757d',
        background:'#0f172a', color:'#e5e7eb'
      }).then(r => { if(r.isConfirmed) form.submit(); });
    });
  });
  // Confirmar eliminación de eventos
  document.querySelectorAll('.form-del-ev .btn-del-ev').forEach(btn => {
    btn.addEventListener('click', e => {
      e.preventDefault();
      const form = btn.closest('form');
      Swal.fire({
        title:'¿Eliminar evento?', text:'Se desvinculan documentos asociados (si los hay).',
        icon:'warning', showCancelButton:true,
        confirmButtonText:'Sí, eliminar', cancelButtonText:'Cancelar',
        confirmButtonColor:'#dc3545', cancelButtonColor:'#6c757d',
        background:'#0f172a', color:'#e5e7eb'
      }).then(r => { if(r.isConfirmed) form.submit(); });
    });
  });
});
</script>
</body>
</html>