<?php
declare(strict_types=1);
require_once __DIR__ . '/../../auth/bootstrap.php';
require_login();
$user = current_user() ?: [];
header('Content-Type: application/json; charset=utf-8');
if (strtolower(trim((string)($user['username'] ?? ''))) !== 'nesrojas') {
  http_response_code(403);
  echo json_encode(['ok'=>false, 'error'=>'Acceso restringido.']); exit;
}
require_once __DIR__ . '/../../config/db.php';
$uid = function_exists('unidad_activa_id') ? unidad_activa_id() : 1;
try {
  $q=$pdo->prepare("SELECT s.mac,s.ip,s.hostname,s.vendor,s.device_type,s.is_online,
      s.scan_point,s.scanner_computer,s.first_seen,s.last_seen,
      COALESCE(NULLIF(a.equipo_nombre,''),NULLIF(s.hostname,''),s.ip) AS equipo_nombre,
      COALESCE(NULLIF(a.propietario_nombre,''),NULLIF(pu.apellido_nombre,''),NULLIF(a.usuario_asignado,''),'') COLLATE utf8mb4_general_ci AS responsable,
      COALESCE(NULLIF(di.nombre,''),NULLIF(a.sector_red,''),'') COLLATE utf8mb4_general_ci AS area_nombre,
      a.id AS inventario_id
    FROM it_network_sightings s
    LEFT JOIN it_activos a ON a.unidad_id=s.unidad_id AND a.categoria='informatica' AND (
      LOWER(REPLACE(REPLACE(COALESCE(a.mac,''),':',''),'-',''))=LOWER(REPLACE(s.mac,':',''))
      OR (a.ip<>'' AND SUBSTRING_INDEX(a.ip,'(',1)=s.ip)
      OR (a.equipo_nombre<>'' AND UPPER(a.equipo_nombre)=UPPER(s.hostname))
    )
    LEFT JOIN personal_unidad pu ON pu.id=a.asignado_personal_id
    LEFT JOIN destino_interno di ON di.id=a.area_id
    WHERE s.unidad_id=:uid1
    UNION ALL
    SELECT COALESCE(a.mac,''),COALESCE(SUBSTRING_INDEX(a.ip,'(',1),''),COALESCE(a.equipo_nombre,''),
      COALESCE(a.marca,''),COALESCE(a.dispositivo_tipo,''),0,
      'Carga manual','',a.creado_en,COALESCE(a.actualizado_en,a.creado_en),
      COALESCE(NULLIF(a.equipo_nombre,''),a.descripcion),
      COALESCE(NULLIF(a.propietario_nombre,''),NULLIF(pu.apellido_nombre,''),NULLIF(a.usuario_asignado,''),'') COLLATE utf8mb4_general_ci,
      COALESCE(NULLIF(di.nombre,''),NULLIF(a.sector_red,''),'') COLLATE utf8mb4_general_ci,a.id
    FROM it_activos a
    LEFT JOIN personal_unidad pu ON pu.id=a.asignado_personal_id
    LEFT JOIN destino_interno di ON di.id=a.area_id
    WHERE a.unidad_id=:uid2 AND a.categoria='informatica' AND a.condicion<>'deposito'
      AND NOT EXISTS (SELECT 1 FROM it_network_sightings sx WHERE sx.unidad_id=a.unidad_id AND (
        (a.mac<>'' AND LOWER(REPLACE(REPLACE(a.mac,':',''),'-',''))=LOWER(REPLACE(sx.mac,':','')))
        OR (a.ip<>'' AND SUBSTRING_INDEX(a.ip,'(',1)=sx.ip)
        OR (a.equipo_nombre<>'' AND UPPER(a.equipo_nombre)=UPPER(sx.hostname))))
    ORDER BY is_online DESC,INET_ATON(ip),last_seen DESC");
  $q->execute([':uid1'=>$uid, ':uid2'=>$uid]);
  echo json_encode(['ok'=>true,'devices'=>$q->fetchAll(PDO::FETCH_ASSOC)],JSON_UNESCAPED_UNICODE);
} catch(Throwable $e) {
  http_response_code(500); echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);
}
