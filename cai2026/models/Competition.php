<?php
declare(strict_types=1);
final class Competition{
 public static function state():array{
  $sql="SELECT p.id,p.numero,p.nombre,p.unidad,p.color,p.estado,t.fecha_hora_salida,t.fecha_hora_llegada,
  CASE WHEN t.fecha_hora_salida IS NULL THEN NULL ELSE TIMESTAMPDIFF(SECOND,t.fecha_hora_salida,COALESCE(t.fecha_hora_llegada,NOW(6))) END bruto_segundos,
  COALESCE(SUM(pe.segundos),0) penalizaciones_segundos
  FROM patrullas p LEFT JOIN tiempos t ON t.patrulla_id=p.id LEFT JOIN penalizaciones pe ON pe.patrulla_id=p.id
  WHERE p.competencia_id=1 AND p.activa=1 GROUP BY p.id,t.fecha_hora_salida,t.fecha_hora_llegada ORDER BY p.numero";
  $rows=db()->query($sql)->fetchAll();
  foreach($rows as &$r){$r['bruto_segundos']=$r['bruto_segundos']===null?null:(int)$r['bruto_segundos'];$r['penalizaciones_segundos']=(int)$r['penalizaciones_segundos'];$r['final_segundos']=$r['bruto_segundos']===null?null:$r['bruto_segundos']+$r['penalizaciones_segundos'];}
  $ranking=array_values(array_filter($rows,fn($r)=>$r['fecha_hora_llegada']!==null));
  usort($ranking,fn($a,$b)=>$a['final_segundos']<=>$b['final_segundos']);foreach($ranking as $i=>&$r)$r['posicion']=$i+1;
  $clock=db()->query("SELECT *,DATE_FORMAT(NOW(6),'%Y-%m-%dT%H:%i:%s.%f') server_now FROM cronometro_global WHERE competencia_id=1")->fetch();
  $comp=db()->query("SELECT * FROM competencias WHERE id=1")->fetch();
  return ['server_now'=>$clock['server_now'],'competition'=>$comp,'clock'=>$clock,'patrols'=>$rows,'ranking'=>$ranking];
 }
 public static function recalculate(int $id):void{
  $q=db()->prepare("INSERT INTO resultados(patrulla_id,tiempo_bruto_segundos,penalizaciones_segundos,tiempo_final_segundos,actualizado_en)
   SELECT p.id,IF(t.fecha_hora_llegada IS NULL,NULL,TIMESTAMPDIFF(SECOND,t.fecha_hora_salida,t.fecha_hora_llegada)),COALESCE(SUM(pe.segundos),0),
   IF(t.fecha_hora_llegada IS NULL,NULL,TIMESTAMPDIFF(SECOND,t.fecha_hora_salida,t.fecha_hora_llegada)+COALESCE(SUM(pe.segundos),0)),NOW()
   FROM patrullas p LEFT JOIN tiempos t ON t.patrulla_id=p.id LEFT JOIN penalizaciones pe ON pe.patrulla_id=p.id WHERE p.id=? GROUP BY p.id
   ON DUPLICATE KEY UPDATE tiempo_bruto_segundos=VALUES(tiempo_bruto_segundos),penalizaciones_segundos=VALUES(penalizaciones_segundos),tiempo_final_segundos=VALUES(tiempo_final_segundos),actualizado_en=NOW()");
  $q->execute([$id]);
 }
}

