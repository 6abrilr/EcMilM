<?php
require_once __DIR__.'/../config/bootstrap.php';require_once __DIR__.'/../models/Competition.php';require_admin();
if($_SERVER['REQUEST_METHOD']!=='POST')json_error('Método no permitido',405);verify_csrf();
$action=(string)($_POST['action']??'');$id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT);$pdo=db();
try{$pdo->beginTransaction();
 if($action==='patrol_start'&&$id){$q=$pdo->prepare("INSERT INTO tiempos(patrulla_id,fecha_hora_salida)VALUES(?,NOW(6)) ON DUPLICATE KEY UPDATE fecha_hora_salida=IF(fecha_hora_salida IS NULL,NOW(6),fecha_hora_salida)");$q->execute([$id]);$pdo->prepare("UPDATE patrullas SET estado='en_curso' WHERE id=? AND estado='espera'")->execute([$id]);}
 elseif($action==='patrol_finish'&&$id){$q=$pdo->prepare("UPDATE tiempos SET fecha_hora_llegada=NOW(6) WHERE patrulla_id=? AND fecha_hora_salida IS NOT NULL AND fecha_hora_llegada IS NULL");$q->execute([$id]);if(!$q->rowCount())throw new DomainException('La patrulla no tiene una salida activa.');$pdo->prepare("UPDATE patrullas SET estado='finalizada' WHERE id=?")->execute([$id]);Competition::recalculate($id);}
 elseif(in_array($action,['clock_start','clock_pause','clock_resume','clock_finish','clock_reset'],true)){
  $clock=$pdo->query("SELECT * FROM cronometro_global WHERE competencia_id=1 FOR UPDATE")->fetch();
  if($action==='clock_start'&&$clock['estado']==='detenido'){$pdo->exec("UPDATE cronometro_global SET fecha_hora_inicio=NOW(6),fecha_hora_pausa=NULL,fecha_hora_fin=NULL,tiempo_pausado_acumulado=0,estado='en_curso' WHERE competencia_id=1");$pdo->exec("UPDATE competencias SET estado='en_curso' WHERE id=1");}
  elseif($action==='clock_pause'&&$clock['estado']==='en_curso'){$pdo->exec("UPDATE cronometro_global SET fecha_hora_pausa=NOW(6),estado='pausado' WHERE competencia_id=1");$pdo->exec("UPDATE competencias SET estado='pausada' WHERE id=1");}
  elseif($action==='clock_resume'&&$clock['estado']==='pausado'){$pdo->exec("UPDATE cronometro_global SET tiempo_pausado_acumulado=tiempo_pausado_acumulado+TIMESTAMPDIFF(MICROSECOND,fecha_hora_pausa,NOW(6)),fecha_hora_pausa=NULL,estado='en_curso' WHERE competencia_id=1");$pdo->exec("UPDATE competencias SET estado='en_curso' WHERE id=1");}
  elseif($action==='clock_finish'&&in_array($clock['estado'],['en_curso','pausado'],true)){$pdo->exec("UPDATE cronometro_global SET fecha_hora_fin=NOW(6),tiempo_pausado_acumulado=tiempo_pausado_acumulado+IF(estado='pausado',TIMESTAMPDIFF(MICROSECOND,fecha_hora_pausa,NOW(6)),0),fecha_hora_pausa=NULL,estado='finalizado' WHERE competencia_id=1");$pdo->exec("UPDATE competencias SET estado='finalizada' WHERE id=1");}
  elseif($action==='clock_reset'){$pdo->exec("UPDATE cronometro_global SET fecha_hora_inicio=NULL,fecha_hora_pausa=NULL,fecha_hora_fin=NULL,tiempo_pausado_acumulado=0,estado='detenido' WHERE competencia_id=1");$pdo->exec("UPDATE competencias SET estado='preparacion' WHERE id=1");}
  else throw new DomainException('Acción incompatible con el estado actual.');
 }
 else throw new DomainException('Acción inválida.');
 audit($action,$id?'patrullas':'cronometro_global',$id?:1);$pdo->commit();json_out(['ok'=>true]);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();json_error($e instanceof DomainException?$e->getMessage():'No se pudo completar la operación.',422);}
