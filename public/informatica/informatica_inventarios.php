<?php
/**
 * ea/public/informatica/informatica_inventarios.php
 * INFORMÁTICA · INVENTARIOS (unificado con Red) — ADAPTADO A TU TABLA it_activos REAL
 *
 * ✅ NUEVO (PRO):
 * - Modal dinámico por dispositivo_tipo:
 *   - PC/NOTEBOOK/SERVIDOR -> SO/CPU/RAM/Disco/Monitor/Periféricos + (opc) antivirus/office/serial + Red avanzada
 *   - IMPRESORA -> IP/MAC/IP fija/Modelo/Ubicación
 *   - SWITCH/ROUTER/MODEM/AP -> IP/MAC/IP fija/Ubicación/Modelo/Observaciones + Red avanzada
 * - “Sin rellenar campos que no aplican”: al guardar, se limpian campos no aplicables => NULL
 *
 * it_activos (tu schema + nuevos campos):
 * - tipo (enum: pc, camara, herramienta, mueble, insumo, otro)
 * - etiqueta, descripcion, marca, modelo, nro_serie
 * - estado (operativo, mantenimiento, baja, roto, prestamo)
 * - condicion (activo, deposito)
 * - edificio_id, area_id, asignado_personal_id
 * - ubicacion_detalle, observaciones, fecha_alta
 * - dispositivo_tipo, equipo_nombre, usuario_asignado, sistema_operativo, cpu, ram_gb,
 *   disco_tipo, disco_gb, monitor, perifericos, mac, ip, ip_fija, categoria
 * - NUEVOS (opcionales):
 *   antivirus, office_version, serial_windows,
 *   ip_gateway, dns1, dns2, switch_puerto, patchera_puerto, sector_red, vlan
 */

declare(strict_types=1);

$ROOT = realpath(__DIR__ . '/../../'); // /ea
if (!$ROOT) { http_response_code(500); exit('No se pudo resolver ROOT del proyecto.'); }

require_once $ROOT . '/auth/bootstrap.php';
require_login();
require_once $ROOT . '/config/db.php';

/** @var PDO $pdo */

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function json_out($data, int $code=200): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

/* ============ Contexto unidad/usuario ============ */
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
$user = function_exists('current_user') ? current_user() : ($_SESSION['user'] ?? []);
$UNIDAD_ID = (int)($user['unidad_id'] ?? $_SESSION['unidad_id'] ?? 1);

/* ============ Rutas ============ */
$PUBLIC_URL = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '')), '/'); // /ea/public/informatica
$APP_URL    = rtrim(dirname($PUBLIC_URL), '/'); // /ea/public
$APP_URL    = rtrim(dirname($APP_URL), '/');    // /ea
$ASSETS_URL = ($APP_URL === '' ? '' : $APP_URL) . '/assets';

$IMG_BG     = $ASSETS_URL . '/img/fondo.png';
$ESCUDO     = $ASSETS_URL . '/img/ecmilm.png'; // ajustá si corresponde
$URL_VOLVER = ($APP_URL === '' ? '' : $APP_URL) . '/public/informatica/informatica.php';

$FAVICON = $ASSETS_URL . '/img/favicon.ico';

/* ============ DB ============ */
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/**
 * Tu it_activos YA existe.
 * (Opcional) Auto-crea it_internet / it_mantenimientos si no existen.
 */

// it_internet (si no existe)
try {
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS it_internet (
      id INT AUTO_INCREMENT PRIMARY KEY,
      unidad_id INT NOT NULL,
      edificio_id INT NOT NULL,
      proveedor VARCHAR(120) NOT NULL,
      servicio VARCHAR(120) NULL,
      plan VARCHAR(120) NULL,
      velocidad VARCHAR(80) NULL,
      costo DECIMAL(12,2) NULL,
      ip_publica VARCHAR(60) NULL,
      nota VARCHAR(255) NULL,
      updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX idx_it_internet_unidad (unidad_id),
      INDEX idx_it_internet_edificio (edificio_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");
} catch (Throwable $ex) {}

try {
  $pdo->exec("ALTER TABLE it_internet
    ADD CONSTRAINT fk_it_internet_edificio
      FOREIGN KEY (edificio_id) REFERENCES red_edificios(id)
      ON DELETE CASCADE ON UPDATE CASCADE
  ");
} catch (Throwable $ex) {}

// it_mantenimientos (si no existe)
try {
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS it_mantenimientos (
      id INT AUTO_INCREMENT PRIMARY KEY,
      unidad_id INT NOT NULL,
      edificio_id INT NOT NULL,
      activo_id INT NULL,
      fecha DATE NOT NULL,
      tipo VARCHAR(80) NOT NULL DEFAULT 'preventivo',
      detalle TEXT NOT NULL,
      realizado_por VARCHAR(120) NULL,
      costo DECIMAL(12,2) NULL,
      created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_it_mant_unidad (unidad_id),
      INDEX idx_it_mant_edificio (edificio_id),
      INDEX idx_it_mant_activo (activo_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");
} catch (Throwable $ex) {}

try {
  $pdo->exec("ALTER TABLE it_mantenimientos
    ADD CONSTRAINT fk_it_mant_edificio
      FOREIGN KEY (edificio_id) REFERENCES red_edificios(id)
      ON DELETE CASCADE ON UPDATE CASCADE
  ");
} catch (Throwable $ex) {}

try {
  $pdo->exec("ALTER TABLE it_mantenimientos
    ADD CONSTRAINT fk_it_mant_activo
      FOREIGN KEY (activo_id) REFERENCES it_activos(id)
      ON DELETE SET NULL ON UPDATE CASCADE
  ");
} catch (Throwable $ex) {}

/* ============ Helpers: edificios / meta ============ */
function edificio_permitido(PDO $pdo, int $unidad_id, int $edificio_id): bool {
  $st = $pdo->prepare("SELECT COUNT(*) FROM red_edificios WHERE id=? AND (unidad_id=? OR unidad_id IS NULL)");
  $st->execute([$edificio_id, $unidad_id]);
  return (int)$st->fetchColumn() > 0;
}

function get_edificio_meta(PDO $pdo, int $unidad_id, int $edificio_id): array {
  try {
    $st = $pdo->prepare("
      SELECT max_dispositivos, ip_desde, ip_hasta, COALESCE(nota,'') AS nota
      FROM red_edificio_meta
      WHERE unidad_id=? AND edificio_id=?
      LIMIT 1
    ");
    $st->execute([$unidad_id, $edificio_id]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    if (!$r) return ['max_dispositivos'=>null,'ip_desde'=>null,'ip_hasta'=>null,'nota'=>''];
    return $r;
  } catch (Throwable $ex) {
    return ['max_dispositivos'=>null,'ip_desde'=>null,'ip_hasta'=>null,'nota'=>''];
  }
}

/* ============ Helpers: áreas / personal (tolerantes) ============ */
function get_areas(PDO $pdo, int $unidad_id): array {
  try {
    $st = $pdo->prepare("
      SELECT id, nombre
      FROM areas
      WHERE (unidad_id = :u OR unidad_id IS NULL)
      ORDER BY nombre
    ");
    $st->execute([':u'=>$unidad_id]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    return array_map(fn($r)=>['id'=>(int)$r['id'],'nombre'=>(string)$r['nombre']], $rows);
  } catch (Throwable $ex) {
    return [];
  }
}

function get_personal(PDO $pdo, int $unidad_id): array {
  $tries = [
    "SELECT id, dni, apellido, nombre, grado FROM personal_unidad WHERE unidad_id=? ORDER BY apellido, nombre",
    "SELECT id, dni, apellido, nombre FROM personal_unidad WHERE unidad_id=? ORDER BY apellido, nombre",
    "SELECT id, dni, apellidos AS apellido, nombres AS nombre, grado FROM personal_unidad WHERE unidad_id=? ORDER BY apellidos, nombres",
    "SELECT id, dni, apellidos AS apellido, nombres AS nombre FROM personal_unidad WHERE unidad_id=? ORDER BY apellidos, nombres",
  ];
  foreach ($tries as $sql) {
    try {
      $st = $pdo->prepare($sql);
      $st->execute([$unidad_id]);
      $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
      $out = [];
      foreach ($rows as $r) {
        $out[] = [
          'id'      => (int)($r['id'] ?? 0),
          'dni'     => (string)($r['dni'] ?? ''),
          'apellido'=> (string)($r['apellido'] ?? ''),
          'nombre'  => (string)($r['nombre'] ?? ''),
          'grado'   => (string)($r['grado'] ?? ''),
          'label'   => trim(((string)($r['grado'] ?? '')).' '.((string)($r['apellido'] ?? '')).', '.((string)($r['nombre'] ?? '')).' (DNI '.((string)($r['dni'] ?? '')).')')
        ];
      }
      return $out;
    } catch (Throwable $ex) {}
  }
  return [];
}

/* ============ Activos con joins (adaptado) ============ */
function activos_with_joins(PDO $pdo, int $unidad_id, array $filters = []): array {
  $w = ["a.unidad_id = :u"];
  $p = [':u'=>$unidad_id];

  if (!empty($filters['edificio_id'])) { $w[] = "a.edificio_id = :e"; $p[':e'] = (int)$filters['edificio_id']; }
  if (!empty($filters['area_id']))     { $w[] = "a.area_id = :ar"; $p[':ar'] = (int)$filters['area_id']; }
  if (!empty($filters['personal_id'])) { $w[] = "a.asignado_personal_id = :pp"; $p[':pp'] = (int)$filters['personal_id']; }

  $where = implode(' AND ', $w);

  $sql = "
    SELECT
      a.*,
      e.nombre AS edificio_nombre,
      ar.nombre AS area_nombre,
      CONCAT_WS(' ', COALESCE(pu.grado,''), COALESCE(pu.apellido,''), CONCAT(', ', COALESCE(pu.nombre,''))) AS asignado_label,
      pu.dni AS asignado_dni
    FROM it_activos a
    LEFT JOIN red_edificios e ON e.id = a.edificio_id
    LEFT JOIN areas ar ON ar.id = a.area_id
    LEFT JOIN personal_unidad pu ON pu.id = a.asignado_personal_id
    WHERE $where
    ORDER BY a.id DESC
  ";

  try {
    $st = $pdo->prepare($sql);
    $st->execute($p);
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $ex) {
    $sql2 = "SELECT a.* FROM it_activos a WHERE $where ORDER BY a.id DESC";
    $st = $pdo->prepare($sql2);
    $st->execute($p);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as &$r) {
      $r['edificio_nombre'] = '';
      $r['area_nombre'] = '';
      $r['asignado_label'] = '';
      $r['asignado_dni'] = '';
    }
    unset($r);
    return $rows;
  }
}

/* =========================
   API
========================= */
if (isset($_GET['api'])) {
  $api = (string)$_GET['api'];

  try {
    if ($api === 'edificios') {
      $st = $pdo->prepare("
        SELECT id, nombre, unidad_id
        FROM red_edificios
        WHERE (unidad_id = :u OR unidad_id IS NULL)
        ORDER BY nombre
      ");
      $st->execute([':u'=>$UNIDAD_ID]);
      $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

      foreach ($rows as &$r) {
        $ed = (int)$r['id'];
        $m = get_edificio_meta($pdo, $UNIDAD_ID, $ed);
        $r['max_dispositivos'] = $m['max_dispositivos'];
        $r['ip_desde'] = $m['ip_desde'];
        $r['ip_hasta'] = $m['ip_hasta'];
        $r['nota'] = $m['nota'];
      }
      unset($r);

      json_out(['ok'=>true,'unidad_id'=>$UNIDAD_ID,'edificios'=>$rows]);
    }

    if ($api === 'areas') {
      json_out(['ok'=>true,'rows'=>get_areas($pdo, $UNIDAD_ID)]);
    }

    if ($api === 'personal') {
      json_out(['ok'=>true,'rows'=>get_personal($pdo, $UNIDAD_ID)]);
    }

    /* ===== Activos (por edificio) ===== */
    if ($api === 'activos_list') {
      $edificio_id = (int)($_GET['edificio_id'] ?? 0);
      if ($edificio_id<=0) json_out(['ok'=>false,'error'=>'edificio_id requerido'], 400);
      if (!edificio_permitido($pdo, $UNIDAD_ID, $edificio_id)) json_out(['ok'=>false,'error'=>'Edificio no válido'], 403);

      $rows = activos_with_joins($pdo, $UNIDAD_ID, ['edificio_id'=>$edificio_id]);
      json_out(['ok'=>true,'rows'=>$rows]);
    }

    /* ===== Activos (toda la unidad / filtros) ===== */
    if ($api === 'activos_list_all') {
      $area_id = (int)($_GET['area_id'] ?? 0);
      $personal_id = (int)($_GET['personal_id'] ?? 0);
      $edificio_id = (int)($_GET['edificio_id'] ?? 0);

      $filters = [];
      if ($area_id>0) $filters['area_id'] = $area_id;
      if ($personal_id>0) $filters['personal_id'] = $personal_id;
      if ($edificio_id>0) {
        if (!edificio_permitido($pdo, $UNIDAD_ID, $edificio_id)) json_out(['ok'=>false,'error'=>'Edificio no válido'], 403);
        $filters['edificio_id'] = $edificio_id;
      }

      $rows = activos_with_joins($pdo, $UNIDAD_ID, $filters);
      json_out(['ok'=>true,'rows'=>$rows]);
    }

    /* ===== Guardar Activo (adaptado a tu schema + PRO) ===== */
    if ($api === 'activos_save' && $_SERVER['REQUEST_METHOD']==='POST') {
      $in = json_decode(file_get_contents('php://input'), true) ?: [];
      $id = (int)($in['id'] ?? 0);
      $edificio_id = (int)($in['edificio_id'] ?? 0);

      if ($edificio_id<=0) json_out(['ok'=>false,'error'=>'edificio_id requerido'], 400);
      if (!edificio_permitido($pdo, $UNIDAD_ID, $edificio_id)) json_out(['ok'=>false,'error'=>'Edificio no válido'], 403);

      // Básico
      $categoria = trim((string)($in['categoria'] ?? 'informatica'));
      $tipo = trim((string)($in['tipo'] ?? 'otro')); // enum tuyo: pc/camara/herramienta/mueble/insumo/otro
      $etiqueta = trim((string)($in['etiqueta'] ?? ''));
      $descripcion = trim((string)($in['descripcion'] ?? ''));

      if ($descripcion === '' && $etiqueta === '') {
        json_out(['ok'=>false,'error'=>'Debés cargar al menos Etiqueta o Descripción'], 400);
      }
      if ($descripcion === '') $descripcion = $etiqueta; // fallback

      $marca = trim((string)($in['marca'] ?? ''));
      $modelo = trim((string)($in['modelo'] ?? ''));
      $nro_serie = trim((string)($in['nro_serie'] ?? ''));
      $estado = trim((string)($in['estado'] ?? 'operativo'));
      $condicion = trim((string)($in['condicion'] ?? 'activo'));
      $ubicacion_detalle = trim((string)($in['ubicacion_detalle'] ?? ''));
      $observaciones = trim((string)($in['observaciones'] ?? ''));
      $fecha_alta = trim((string)($in['fecha_alta'] ?? ''));

      $area_id = (int)($in['area_id'] ?? 0);
      $asignado_personal_id = (int)($in['asignado_personal_id'] ?? 0);

      // Datos de PC/Red
      $dispositivo_tipo = trim((string)($in['dispositivo_tipo'] ?? ''));
      $equipo_nombre = trim((string)($in['equipo_nombre'] ?? ''));
      $usuario_asignado = trim((string)($in['usuario_asignado'] ?? ''));
      $sistema_operativo = trim((string)($in['sistema_operativo'] ?? ''));
      $cpu = trim((string)($in['cpu'] ?? ''));
      $ram_gb = $in['ram_gb'] ?? null;
      $disco_tipo = trim((string)($in['disco_tipo'] ?? ''));
      $disco_gb = $in['disco_gb'] ?? null;
      $monitor = trim((string)($in['monitor'] ?? ''));
      $perifericos = trim((string)($in['perifericos'] ?? ''));
      $mac = trim((string)($in['mac'] ?? ''));
      $ip = trim((string)($in['ip'] ?? ''));
      $ip_fija = !empty($in['ip_fija']) ? 1 : 0;

      // NUEVOS (opcionales PRO)
      $antivirus = trim((string)($in['antivirus'] ?? ''));
      $office_version = trim((string)($in['office_version'] ?? ''));
      $serial_windows = trim((string)($in['serial_windows'] ?? ''));

      $ip_gateway = trim((string)($in['ip_gateway'] ?? ''));
      $dns1 = trim((string)($in['dns1'] ?? ''));
      $dns2 = trim((string)($in['dns2'] ?? ''));
      $switch_puerto = trim((string)($in['switch_puerto'] ?? ''));
      $patchera_puerto = trim((string)($in['patchera_puerto'] ?? ''));
      $sector_red = trim((string)($in['sector_red'] ?? ''));
      $vlan = trim((string)($in['vlan'] ?? ''));

      $ramVal = null;
      if ($ram_gb !== null && $ram_gb !== '') $ramVal = (float)$ram_gb;

      $discoGbVal = null;
      if ($disco_gb !== null && $disco_gb !== '') $discoGbVal = (int)$disco_gb;

      // Validaciones suaves de FK
      if ($asignado_personal_id > 0) {
        try {
          $stChk = $pdo->prepare("SELECT COUNT(*) FROM personal_unidad WHERE id=? AND unidad_id=?");
          $stChk->execute([$asignado_personal_id, $UNIDAD_ID]);
          if ((int)$stChk->fetchColumn() === 0) $asignado_personal_id = 0;
        } catch (Throwable $ex) {}
      }
      if ($area_id > 0) {
        try {
          $stChk = $pdo->prepare("SELECT COUNT(*) FROM areas WHERE id=? AND (unidad_id=? OR unidad_id IS NULL)");
          $stChk->execute([$area_id, $UNIDAD_ID]);
          if ((int)$stChk->fetchColumn() === 0) $area_id = 0;
        } catch (Throwable $ex) {}
      }

      // fecha_alta null si vacía
      $fechaAltaVal = null;
      if ($fecha_alta !== '') $fechaAltaVal = $fecha_alta;

      if ($id > 0) {
        $st = $pdo->prepare("
          UPDATE it_activos
          SET
            categoria=?,
            tipo=?,
            etiqueta=?,
            descripcion=?,
            marca=?,
            modelo=?,
            nro_serie=?,
            estado=?,
            condicion=?,
            edificio_id=?,
            area_id=?,
            ubicacion_detalle=?,
            asignado_personal_id=?,
            fecha_alta=?,
            observaciones=?,

            dispositivo_tipo=?,
            equipo_nombre=?,
            usuario_asignado=?,
            sistema_operativo=?,
            cpu=?,
            ram_gb=?,
            disco_tipo=?,
            disco_gb=?,
            monitor=?,
            perifericos=?,
            mac=?,
            ip=?,
            ip_fija=?,

            antivirus=?,
            office_version=?,
            serial_windows=?,

            ip_gateway=?,
            dns1=?,
            dns2=?,
            switch_puerto=?,
            patchera_puerto=?,
            sector_red=?,
            vlan=?,

            actualizado_en=NOW()
          WHERE id=? AND unidad_id=? AND (edificio_id <=> ?)
        ");
        $st->execute([
          $categoria,
          $tipo,
          ($etiqueta!==''?$etiqueta:null),
          $descripcion,
          ($marca!==''?$marca:null),
          ($modelo!==''?$modelo:null),
          ($nro_serie!==''?$nro_serie:null),
          $estado,
          $condicion,
          $edificio_id,
          ($area_id>0?$area_id:null),
          ($ubicacion_detalle!==''?$ubicacion_detalle:null),
          ($asignado_personal_id>0?$asignado_personal_id:null),
          $fechaAltaVal,
          ($observaciones!==''?$observaciones:null),

          ($dispositivo_tipo!==''?$dispositivo_tipo:null),
          ($equipo_nombre!==''?$equipo_nombre:null),
          ($usuario_asignado!==''?$usuario_asignado:null),
          ($sistema_operativo!==''?$sistema_operativo:null),
          ($cpu!==''?$cpu:null),
          $ramVal,
          ($disco_tipo!==''?$disco_tipo:null),
          $discoGbVal,
          ($monitor!==''?$monitor:null),
          ($perifericos!==''?$perifericos:null),
          ($mac!==''?$mac:null),
          ($ip!==''?$ip:null),
          $ip_fija,

          ($antivirus!==''?$antivirus:null),
          ($office_version!==''?$office_version:null),
          ($serial_windows!==''?$serial_windows:null),

          ($ip_gateway!==''?$ip_gateway:null),
          ($dns1!==''?$dns1:null),
          ($dns2!==''?$dns2:null),
          ($switch_puerto!==''?$switch_puerto:null),
          ($patchera_puerto!==''?$patchera_puerto:null),
          ($sector_red!==''?$sector_red:null),
          ($vlan!==''?$vlan:null),

          $id, $UNIDAD_ID, $edificio_id
        ]);
      } else {
        $st = $pdo->prepare("
          INSERT INTO it_activos (
            unidad_id,
            categoria,
            tipo,
            etiqueta,
            descripcion,
            marca,
            modelo,
            nro_serie,
            estado,
            condicion,
            edificio_id,
            area_id,
            ubicacion_detalle,
            asignado_personal_id,
            fecha_alta,
            observaciones,

            dispositivo_tipo,
            equipo_nombre,
            usuario_asignado,
            sistema_operativo,
            cpu,
            ram_gb,
            disco_tipo,
            disco_gb,
            monitor,
            perifericos,
            mac,
            ip,
            ip_fija,

            antivirus,
            office_version,
            serial_windows,

            ip_gateway,
            dns1,
            dns2,
            switch_puerto,
            patchera_puerto,
            sector_red,
            vlan,

            creado_en
          ) VALUES (
            ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,
            ?,?,?,?,
            ?,?,?,?,?,?,?,?,
            NOW()
          )
        ");
        $st->execute([
          $UNIDAD_ID,
          $categoria,
          $tipo,
          ($etiqueta!==''?$etiqueta:null),
          $descripcion,
          ($marca!==''?$marca:null),
          ($modelo!==''?$modelo:null),
          ($nro_serie!==''?$nro_serie:null),
          $estado,
          $condicion,
          $edificio_id,
          ($area_id>0?$area_id:null),
          ($ubicacion_detalle!==''?$ubicacion_detalle:null),
          ($asignado_personal_id>0?$asignado_personal_id:null),
          $fechaAltaVal,
          ($observaciones!==''?$observaciones:null),

          ($dispositivo_tipo!==''?$dispositivo_tipo:null),
          ($equipo_nombre!==''?$equipo_nombre:null),
          ($usuario_asignado!==''?$usuario_asignado:null),
          ($sistema_operativo!==''?$sistema_operativo:null),
          ($cpu!==''?$cpu:null),
          $ramVal,
          ($disco_tipo!==''?$disco_tipo:null),
          $discoGbVal,
          ($monitor!==''?$monitor:null),
          ($perifericos!==''?$perifericos:null),
          ($mac!==''?$mac:null),
          ($ip!==''?$ip:null),
          $ip_fija,

          ($antivirus!==''?$antivirus:null),
          ($office_version!==''?$office_version:null),
          ($serial_windows!==''?$serial_windows:null),

          ($ip_gateway!==''?$ip_gateway:null),
          ($dns1!==''?$dns1:null),
          ($dns2!==''?$dns2:null),
          ($switch_puerto!==''?$switch_puerto:null),
          ($patchera_puerto!==''?$patchera_puerto:null),
          ($sector_red!==''?$sector_red:null),
          ($vlan!==''?$vlan:null),
        ]);
        $id = (int)$pdo->lastInsertId();
      }

      json_out(['ok'=>true,'id'=>$id]);
    }

    if ($api === 'activos_delete' && $_SERVER['REQUEST_METHOD']==='POST') {
      $in = json_decode(file_get_contents('php://input'), true) ?: [];
      $id = (int)($in['id'] ?? 0);
      $edificio_id = (int)($in['edificio_id'] ?? 0);
      if ($id<=0 || $edificio_id<=0) json_out(['ok'=>false,'error'=>'Datos inválidos'], 400);
      if (!edificio_permitido($pdo, $UNIDAD_ID, $edificio_id)) json_out(['ok'=>false,'error'=>'Edificio no válido'], 403);

      $st = $pdo->prepare("DELETE FROM it_activos WHERE id=? AND unidad_id=? AND edificio_id=?");
      $st->execute([$id, $UNIDAD_ID, $edificio_id]);
      json_out(['ok'=>true]);
    }

    /* ===== Internet ===== */
    if ($api === 'internet_list') {
      $edificio_id = (int)($_GET['edificio_id'] ?? 0);
      if ($edificio_id<=0) json_out(['ok'=>false,'error'=>'edificio_id requerido'], 400);
      if (!edificio_permitido($pdo, $UNIDAD_ID, $edificio_id)) json_out(['ok'=>false,'error'=>'Edificio no válido'], 403);

      $st = $pdo->prepare("
        SELECT *
        FROM it_internet
        WHERE unidad_id=? AND edificio_id=?
        ORDER BY id DESC
      ");
      $st->execute([$UNIDAD_ID, $edificio_id]);
      json_out(['ok'=>true,'rows'=>$st->fetchAll(PDO::FETCH_ASSOC) ?: []]);
    }

    if ($api === 'internet_save' && $_SERVER['REQUEST_METHOD']==='POST') {
      $in = json_decode(file_get_contents('php://input'), true) ?: [];
      $id = (int)($in['id'] ?? 0);
      $edificio_id = (int)($in['edificio_id'] ?? 0);

      if ($edificio_id<=0) json_out(['ok'=>false,'error'=>'edificio_id requerido'], 400);
      if (!edificio_permitido($pdo, $UNIDAD_ID, $edificio_id)) json_out(['ok'=>false,'error'=>'Edificio no válido'], 403);

      $proveedor = trim((string)($in['proveedor'] ?? ''));
      if ($proveedor==='') json_out(['ok'=>false,'error'=>'Proveedor requerido'], 400);

      $servicio = trim((string)($in['servicio'] ?? ''));
      $plan = trim((string)($in['plan'] ?? ''));
      $velocidad = trim((string)($in['velocidad'] ?? ''));
      $costo = $in['costo'] ?? null;
      $ip_publica = trim((string)($in['ip_publica'] ?? ''));
      $nota = trim((string)($in['nota'] ?? ''));

      $costoVal = null;
      if ($costo !== null && $costo !== '') $costoVal = (float)$costo;

      if ($id > 0) {
        $st = $pdo->prepare("
          UPDATE it_internet
          SET proveedor=?, servicio=?, plan=?, velocidad=?, costo=?, ip_publica=?, nota=?
          WHERE id=? AND unidad_id=? AND edificio_id=?
        ");
        $st->execute([
          $proveedor,
          ($servicio!==''?$servicio:null),
          ($plan!==''?$plan:null),
          ($velocidad!==''?$velocidad:null),
          $costoVal,
          ($ip_publica!==''?$ip_publica:null),
          ($nota!==''?$nota:null),
          $id, $UNIDAD_ID, $edificio_id
        ]);
      } else {
        $st = $pdo->prepare("
          INSERT INTO it_internet (unidad_id, edificio_id, proveedor, servicio, plan, velocidad, costo, ip_publica, nota)
          VALUES (?,?,?,?,?,?,?,?,?)
        ");
        $st->execute([
          $UNIDAD_ID,
          $edificio_id,
          $proveedor,
          ($servicio!==''?$servicio:null),
          ($plan!==''?$plan:null),
          ($velocidad!==''?$velocidad:null),
          $costoVal,
          ($ip_publica!==''?$ip_publica:null),
          ($nota!==''?$nota:null),
        ]);
        $id = (int)$pdo->lastInsertId();
      }

      json_out(['ok'=>true,'id'=>$id]);
    }

    if ($api === 'internet_delete' && $_SERVER['REQUEST_METHOD']==='POST') {
      $in = json_decode(file_get_contents('php://input'), true) ?: [];
      $id = (int)($in['id'] ?? 0);
      $edificio_id = (int)($in['edificio_id'] ?? 0);
      if ($id<=0 || $edificio_id<=0) json_out(['ok'=>false,'error'=>'Datos inválidos'], 400);
      if (!edificio_permitido($pdo, $UNIDAD_ID, $edificio_id)) json_out(['ok'=>false,'error'=>'Edificio no válido'], 403);

      $st = $pdo->prepare("DELETE FROM it_internet WHERE id=? AND unidad_id=? AND edificio_id=?");
      $st->execute([$id, $UNIDAD_ID, $edificio_id]);
      json_out(['ok'=>true]);
    }

    /* ===== Mantenimientos ===== */
    if ($api === 'mant_list') {
      $edificio_id = (int)($_GET['edificio_id'] ?? 0);
      if ($edificio_id<=0) json_out(['ok'=>false,'error'=>'edificio_id requerido'], 400);
      if (!edificio_permitido($pdo, $UNIDAD_ID, $edificio_id)) json_out(['ok'=>false,'error'=>'Edificio no válido'], 403);

      $st = $pdo->prepare("
        SELECT m.*,
               COALESCE(a.etiqueta, a.descripcion) AS activo_nombre
        FROM it_mantenimientos m
        LEFT JOIN it_activos a ON a.id = m.activo_id
        WHERE m.unidad_id=? AND m.edificio_id=?
        ORDER BY m.fecha DESC, m.id DESC
      ");
      $st->execute([$UNIDAD_ID, $edificio_id]);
      json_out(['ok'=>true,'rows'=>$st->fetchAll(PDO::FETCH_ASSOC) ?: []]);
    }

    if ($api === 'mant_save' && $_SERVER['REQUEST_METHOD']==='POST') {
      $in = json_decode(file_get_contents('php://input'), true) ?: [];
      $id = (int)($in['id'] ?? 0);
      $edificio_id = (int)($in['edificio_id'] ?? 0);

      if ($edificio_id<=0) json_out(['ok'=>false,'error'=>'edificio_id requerido'], 400);
      if (!edificio_permitido($pdo, $UNIDAD_ID, $edificio_id)) json_out(['ok'=>false,'error'=>'Edificio no válido'], 403);

      $fecha = trim((string)($in['fecha'] ?? ''));
      $tipo = trim((string)($in['tipo'] ?? 'preventivo'));
      $detalle = trim((string)($in['detalle'] ?? ''));
      if ($fecha==='') json_out(['ok'=>false,'error'=>'Fecha requerida'], 400);
      if ($detalle==='') json_out(['ok'=>false,'error'=>'Detalle requerido'], 400);

      $activo_id = (int)($in['activo_id'] ?? 0);
      $realizado_por = trim((string)($in['realizado_por'] ?? ''));
      $costo = $in['costo'] ?? null;

      $costoVal = null;
      if ($costo !== null && $costo !== '') $costoVal = (float)$costo;

      if ($activo_id > 0) {
        $stChk = $pdo->prepare("SELECT COUNT(*) FROM it_activos WHERE id=? AND unidad_id=? AND edificio_id=?");
        $stChk->execute([$activo_id, $UNIDAD_ID, $edificio_id]);
        if ((int)$stChk->fetchColumn() === 0) $activo_id = 0;
      }

      if ($id > 0) {
        $st = $pdo->prepare("
          UPDATE it_mantenimientos
          SET activo_id=?, fecha=?, tipo=?, detalle=?, realizado_por=?, costo=?
          WHERE id=? AND unidad_id=? AND edificio_id=?
        ");
        $st->execute([
          ($activo_id>0?$activo_id:null),
          $fecha,
          $tipo,
          $detalle,
          ($realizado_por!==''?$realizado_por:null),
          $costoVal,
          $id, $UNIDAD_ID, $edificio_id
        ]);
      } else {
        $st = $pdo->prepare("
          INSERT INTO it_mantenimientos (unidad_id, edificio_id, activo_id, fecha, tipo, detalle, realizado_por, costo)
          VALUES (?,?,?,?,?,?,?,?)
        ");
        $st->execute([
          $UNIDAD_ID,
          $edificio_id,
          ($activo_id>0?$activo_id:null),
          $fecha,
          $tipo,
          $detalle,
          ($realizado_por!==''?$realizado_por:null),
          $costoVal
        ]);
        $id = (int)$pdo->lastInsertId();
      }

      json_out(['ok'=>true,'id'=>$id]);
    }

    if ($api === 'mant_delete' && $_SERVER['REQUEST_METHOD']==='POST') {
      $in = json_decode(file_get_contents('php://input'), true) ?: [];
      $id = (int)($in['id'] ?? 0);
      $edificio_id = (int)($in['edificio_id'] ?? 0);
      if ($id<=0 || $edificio_id<=0) json_out(['ok'=>false,'error'=>'Datos inválidos'], 400);
      if (!edificio_permitido($pdo, $UNIDAD_ID, $edificio_id)) json_out(['ok'=>false,'error'=>'Edificio no válido'], 403);

      $st = $pdo->prepare("DELETE FROM it_mantenimientos WHERE id=? AND unidad_id=? AND edificio_id=?");
      $st->execute([$id, $UNIDAD_ID, $edificio_id]);
      json_out(['ok'=>true]);
    }

    /* ===== Aux: activos para combo mantenimiento ===== */
    if ($api === 'activos_combo') {
      $edificio_id = (int)($_GET['edificio_id'] ?? 0);
      if ($edificio_id<=0) json_out(['ok'=>false,'error'=>'edificio_id requerido'], 400);
      if (!edificio_permitido($pdo, $UNIDAD_ID, $edificio_id)) json_out(['ok'=>false,'error'=>'Edificio no válido'], 403);

      $st = $pdo->prepare("
        SELECT id, COALESCE(etiqueta, descripcion) AS nombre
        FROM it_activos
        WHERE unidad_id=? AND edificio_id=?
        ORDER BY nombre ASC
      ");
      $st->execute([$UNIDAD_ID, $edificio_id]);
      json_out(['ok'=>true,'rows'=>$st->fetchAll(PDO::FETCH_ASSOC) ?: []]);
    }

    json_out(['ok'=>false,'error'=>'API no encontrada'], 404);

  } catch (Throwable $ex) {
    json_out(['ok'=>false,'error'=>$ex->getMessage()], 500);
  }
}

/* =========================
   Modo página
========================= */
$edificio_id = (int)($_GET['edificio_id'] ?? 0);
$modo_edificio = $edificio_id > 0;

$edificio_nombre = '';
$meta = ['max_dispositivos'=>null,'ip_desde'=>null,'ip_hasta'=>null,'nota'=>''];

if ($modo_edificio) {
  if (!edificio_permitido($pdo, $UNIDAD_ID, $edificio_id)) {
    http_response_code(403);
    exit('Edificio no permitido.');
  }
  $st = $pdo->prepare("SELECT nombre FROM red_edificios WHERE id=? LIMIT 1");
  $st->execute([$edificio_id]);
  $edificio_nombre = (string)($st->fetchColumn() ?: '');
  $meta = get_edificio_meta($pdo, $UNIDAD_ID, $edificio_id);
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Informática · Inventarios</title>
  <link rel="icon" href="<?= e($FAVICON) ?>">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <style>
    :root{
      --glass: rgba(15,17,23,.92);
      --glass2: rgba(2,6,23,.68);
      --stroke: rgba(148,163,184,.28);
      --text: #e5e7eb;
      --muted: rgba(203,213,245,.88);
      --brand: #0ea5e9;
      --ok:#22c55e;
      --warn:#fbbf24;
      --danger:#ef4444;
    }
    html,body{height:100%;}
    body{
      margin:0;
      color:var(--text);
      font-family:system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,sans-serif;
      background:#000;
    }
    .page-bg{
      position:fixed; inset:0; z-index:-2; pointer-events:none;
      background:
        linear-gradient(160deg, rgba(0,0,0,.88) 0%, rgba(0,0,0,.60) 55%, rgba(0,0,0,.88) 100%),
        url("<?= e($IMG_BG) ?>") center/cover no-repeat;
      background-attachment: fixed, fixed;
    }
    .container-main{ max-width: 1700px; margin: auto; padding: 14px; }

    .hero{
      border:1px solid var(--stroke);
      background: rgba(2,6,23,.60);
      backdrop-filter: blur(8px);
      border-radius: 18px;
      box-shadow: 0 18px 40px rgba(0,0,0,.55), inset 0 1px 0 rgba(255,255,255,.04);
      padding: 12px 14px;
      display:flex; align-items:center; gap:12px;
    }
    .hero img{ width:52px; height:52px; object-fit:contain; }
    .hero h1{ font-size:1.05rem; font-weight:900; margin:0; letter-spacing:.3px; }
    .hero .sub{ font-size:.86rem; color:var(--muted); margin-top:2px; }
    .hero .right{ margin-left:auto; display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
    .btn-std{ font-weight:800; padding:.35rem .9rem; border-radius:10px; }

    .cardx{
      border:1px solid var(--stroke);
      background: var(--glass);
      border-radius: 18px;
      box-shadow: 0 18px 40px rgba(0,0,0,.55), inset 0 1px 0 rgba(255,255,255,.04);
      backdrop-filter: blur(8px);
    }
    .cardx-h{
      padding: 12px 14px;
      border-bottom: 1px solid rgba(148,163,184,.18);
      display:flex; align-items:center; justify-content:space-between; gap:10px;
      flex-wrap: wrap;
    }
    .chip{
      display:inline-flex; align-items:center; gap:.45rem;
      font-size:.74rem;
      padding:.25rem .65rem;
      border-radius:999px;
      background: rgba(255,255,255,.08);
      border:1px solid rgba(255,255,255,.10);
      letter-spacing:.08em;
      text-transform:uppercase;
      font-weight:900;
    }
    .muted{ color: var(--muted); font-size:.86rem; }
    .form-label{ color: var(--muted); font-weight:800; font-size:.82rem; margin-bottom:.35rem; }
    .form-control, .form-select, textarea{
      background: rgba(2,6,23,.80);
      border:1px solid rgba(148,163,184,.28);
      color: var(--text);
      border-radius: 12px;
    }
    .form-control:focus, .form-select:focus, textarea:focus{
      border-color: rgba(14,165,233,.85);
      box-shadow: 0 0 0 .2rem rgba(14,165,233,.18);
      background: rgba(2,6,23,.86);
      color: var(--text);
    }
    .btn-pill{
      display:inline-flex; align-items:center; justify-content:center;
      gap:.4rem;
      padding:.55rem 1rem;
      border-radius:999px;
      font-size:.86rem;
      font-weight:900;
      text-decoration:none;
      background: var(--brand);
      color:#021827;
      border:none;
      box-shadow: 0 8px 22px rgba(14,165,233,.45);
      white-space: nowrap;
    }
    .btn-pill:hover{ filter: brightness(1.05); }
    .btn-pill--green{ background: var(--ok); color:#052e16; box-shadow:0 8px 22px rgba(34,197,94,.35); }
    .btn-pill--amber{ background: var(--warn); color:#78350f; box-shadow:0 8px 22px rgba(251,191,36,.32); }
    .btn-pill--red{ background: var(--danger); color:#450a0a; box-shadow:0 8px 22px rgba(239,68,68,.30); }

    .ed-card{ padding: 14px; cursor:pointer; }
    .ed-card:hover{ border-color: rgba(14,165,233,.45); }

    .badge-soft{
      background: rgba(148,163,184,.16);
      border: 1px solid rgba(148,163,184,.22);
      color: var(--text);
      font-weight: 900;
      border-radius: 999px;
      padding: .2rem .55rem;
      font-size: .75rem;
    }
    .modal-content{
      border-radius:18px;
      background: rgba(15,17,23,.98);
      border:1px solid rgba(148,163,184,.25);
      color: var(--text);
    }
    .modal-header,.modal-footer{ border-color: rgba(148,163,184,.16); }

    .table-wrap{
      border:1px solid rgba(148,163,184,.18);
      border-radius:14px;
      overflow:hidden;
    }
    .table{
      margin:0;
      color: var(--text);
    }
    .table thead th{
      background: rgba(2,6,23,.92) !important;
      color: var(--text) !important;
      border-color: rgba(148,163,184,.20) !important;
      font-size: .82rem;
      font-weight: 900;
      letter-spacing: .02em;
      text-transform: uppercase;
      position: sticky;
      top: 0;
      z-index: 1;
      white-space: nowrap;
    }
    .table td, .table th {
      vertical-align: middle;
      border-color: rgba(148,163,184,.14) !important;
      font-size: .90rem;
      background: rgba(15,17,23,.70);
    }
    .table tbody tr:hover td{
      background: rgba(14,165,233,.10);
    }
    .kbd{
      font-family: ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace;
      font-size: .82rem;
      padding:.1rem .35rem;
      border-radius: 8px;
      background: rgba(255,255,255,.08);
      border: 1px solid rgba(255,255,255,.12);
      color: var(--text);
    }
    .sep{ height:1px; background: rgba(148,163,184,.18); margin: 10px 0; }
    .nav-pills .nav-link{
      color: var(--text);
      border: 1px solid rgba(148,163,184,.22);
      background: rgba(2,6,23,.55);
      font-weight: 900;
      border-radius: 999px;
      padding: .45rem .9rem;
      margin-right: .5rem;
      margin-bottom: .35rem;
    }
    .nav-pills .nav-link.active{
      background: var(--brand);
      color: #021827;
      border-color: rgba(14,165,233,.65);
      box-shadow: 0 8px 18px rgba(14,165,233,.25);
    }
    .small-help{ font-size:.84rem; color: var(--muted); }
    .mono{ font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
    .grid-2{ display:grid; grid-template-columns: 1fr 1fr; gap:12px; }
    @media (max-width: 992px){ .grid-2{ grid-template-columns: 1fr; } }
  </style>
</head>

<body>
  <div class="page-bg"></div>

  <div class="container-main">
    <div class="hero mb-3">
      <img src="<?= e($ESCUDO) ?>" alt="Escudo">
      <div>
        <h1>Informática · Inventarios</h1>
        <div class="sub">
          <?= $modo_edificio ? ('Edificio: <b>' . e($edificio_nombre) . '</b>') : 'Inventarios de toda la unidad + por edificio/área/personal' ?>
          <span class="ms-2 badge-soft">Unidad ID: <?= (int)$UNIDAD_ID ?></span>
        </div>
        <?php if ($modo_edificio): ?>
          <div class="small-help mt-1">
            <?php if ($meta['ip_desde'] || $meta['ip_hasta']): ?>
              Rango IP: <span class="kbd mono"><?= e((string)$meta['ip_desde']) ?></span> → <span class="kbd mono"><?= e((string)$meta['ip_hasta']) ?></span>
            <?php endif; ?>
            <?php if ($meta['max_dispositivos'] !== null && $meta['max_dispositivos'] !== ''): ?>
              <span class="ms-2">Máx disp.: <span class="kbd"><?= e((string)$meta['max_dispositivos']) ?></span></span>
            <?php endif; ?>
            <?php if (!empty($meta['nota'])): ?>
              <span class="ms-2">Nota: <span class="kbd"><?= e((string)$meta['nota']) ?></span></span>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="right">
        <?php if ($modo_edificio): ?>
          <a class="btn btn-outline-light btn-std" href="<?= e($PUBLIC_URL) ?>/informatica_inventarios.php">
            ← Cambiar edificio / Unidad
          </a>
        <?php endif; ?>
        <a class="btn btn-success btn-sm" style="font-weight:700; padding:.35rem .9rem;" href="<?= e($URL_VOLVER) ?>">Volver</a>
      </div>
    </div>

    <?php if (!$modo_edificio): ?>
      <!-- MODO UNIDAD -->
      <div class="cardx mb-3">
        <div class="cardx-h">
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="chip">Unidad</span>
            <span class="muted">Activos (toda la unidad) + filtros por edificio/área/personal.</span>
          </div>
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <button class="btn btn-outline-light btn-sm btn-std" id="btnReloadUnit">Recargar</button>
          </div>
        </div>
        <div class="p-3">
          <div class="row g-3 mb-2">
            <div class="col-12 col-md-4">
              <label class="form-label">Filtrar por edificio</label>
              <select class="form-select" id="f_edificio">
                <option value="0">— Todos —</option>
              </select>
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label">Filtrar por área</label>
              <select class="form-select" id="f_area">
                <option value="0">— Todas —</option>
              </select>
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label">Filtrar por personal</label>
              <select class="form-select" id="f_personal">
                <option value="0">— Todos —</option>
              </select>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Buscar</label>
              <input class="form-control" id="f_q" placeholder="Etiqueta, descripción, marca, modelo, serie, equipo, usuario, IP, MAC...">
              <div class="small-help mt-1">La búsqueda es local sobre la tabla cargada (rápida).</div>
            </div>
            <div class="col-12 col-md-6 d-flex align-items-end gap-2 flex-wrap">
              <button class="btn btn-primary btn-std" id="btnApplyFilters">Aplicar filtros</button>
              <button class="btn btn-outline-light btn-std" id="btnClearFilters">Limpiar</button>
            </div>
          </div>

          <div class="table-wrap">
            <div class="table-responsive">
              <table class="table table-hover align-middle">
                <thead>
                  <tr>
                    <th style="width:70px;">ID</th>
                    <th>Edificio</th>
                    <th>Etiqueta</th>
                    <th>Descripción</th>
                    <th>Tipo</th>
                    <th>Dispositivo</th>
                    <th>Marca/Modelo</th>
                    <th>Serie</th>
                    <th>Estado</th>
                    <th>Condición</th>
                    <th>Área</th>
                    <th>Asignado a</th>
                    <th>Equipo</th>
                    <th>Usuario</th>
                    <th>IP</th>
                    <th>MAC</th>
                  </tr>
                </thead>
                <tbody id="tbUnidad"></tbody>
              </table>
            </div>
          </div>
          <div id="unidadEmpty" class="muted mt-2" style="display:none;">Sin activos cargados en la unidad (o no hay coincidencias).</div>
        </div>
      </div>

      <div class="cardx">
        <div class="cardx-h">
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="chip">Edificios</span>
            <span class="muted">Se listan desde <span class="kbd">red_edificios</span> (misma base que Red).</span>
          </div>
          <div class="muted">Tip: cargá rangos IP en Red y acá se muestran.</div>
        </div>
        <div class="p-3">
          <div id="edificiosGrid" class="row g-3"></div>
          <div id="edEmpty" class="muted mt-2" style="display:none;">No hay edificios disponibles para esta unidad.</div>
        </div>
      </div>

    <?php else: ?>
      <!-- MODO EDIFICIO -->
      <div class="cardx mb-3">
        <div class="cardx-h">
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="chip">Gestión por edificio</span>
            <span class="muted">Activos · Internet · Mantenimientos</span>
          </div>
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <button class="btn-pill btn-pill--green" id="btnAddActivo">+ Activo</button>
            <button class="btn-pill btn-pill--amber" id="btnAddInternet">+ Internet</button>
            <button class="btn-pill" id="btnAddMant">+ Mantenimiento</button>
          </div>
        </div>

        <div class="p-3">
          <ul class="nav nav-pills mb-3" id="tabsInv" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active" id="tab-activos" data-bs-toggle="pill" data-bs-target="#pane-activos" type="button" role="tab">Activos</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="tab-internet" data-bs-toggle="pill" data-bs-target="#pane-internet" type="button" role="tab">Internet</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="tab-mant" data-bs-toggle="pill" data-bs-target="#pane-mant" type="button" role="tab">Mantenimientos</button>
            </li>
          </ul>

          <div class="tab-content">
            <!-- Activos -->
            <div class="tab-pane fade show active" id="pane-activos" role="tabpanel">
              <div class="table-wrap">
                <div class="table-responsive">
                  <table class="table table-hover align-middle">
                    <thead>
                      <tr>
                        <th style="width:70px;">ID</th>
                        <th>Etiqueta</th>
                        <th>Descripción</th>
                        <th>Tipo</th>
                        <th>Dispositivo</th>
                        <th>Marca/Modelo</th>
                        <th>Serie</th>
                        <th>Estado</th>
                        <th>Condición</th>
                        <th>Área</th>
                        <th>Asignado a</th>
                        <th>Equipo</th>
                        <th>Usuario</th>
                        <th>IP</th>
                        <th>MAC</th>
                        <th style="width:170px;">Acciones</th>
                      </tr>
                    </thead>
                    <tbody id="tbActivos"></tbody>
                  </table>
                </div>
              </div>
              <div id="activosEmpty" class="muted mt-2" style="display:none;">Sin activos cargados en este edificio.</div>
            </div>

            <!-- Internet -->
            <div class="tab-pane fade" id="pane-internet" role="tabpanel">
              <div class="table-wrap">
                <div class="table-responsive">
                  <table class="table table-hover align-middle">
                    <thead>
                      <tr>
                        <th style="width:70px;">ID</th>
                        <th>Proveedor</th>
                        <th>Servicio</th>
                        <th>Plan</th>
                        <th>Velocidad</th>
                        <th>Costo</th>
                        <th>IP Pública</th>
                        <th>Nota</th>
                        <th style="width:170px;">Acciones</th>
                      </tr>
                    </thead>
                    <tbody id="tbInternet"></tbody>
                  </table>
                </div>
              </div>
              <div id="internetEmpty" class="muted mt-2" style="display:none;">Sin registros de internet en este edificio.</div>
            </div>

            <!-- Mantenimientos -->
            <div class="tab-pane fade" id="pane-mant" role="tabpanel">
              <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                <div class="muted">Tip: en “Activo asociado” solo aparecen activos del edificio.</div>
                <button class="btn btn-outline-light btn-sm btn-std" id="btnReloadAll">Recargar</button>
              </div>
              <div class="table-wrap">
                <div class="table-responsive">
                  <table class="table table-hover align-middle">
                    <thead>
                      <tr>
                        <th style="width:70px;">ID</th>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Activo</th>
                        <th>Detalle</th>
                        <th>Realizado por</th>
                        <th>Costo</th>
                        <th style="width:170px;">Acciones</th>
                      </tr>
                    </thead>
                    <tbody id="tbMant"></tbody>
                  </table>
                </div>
              </div>
              <div id="mantEmpty" class="muted mt-2" style="display:none;">Sin mantenimientos registrados en este edificio.</div>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <!-- MODAL ACTIVO (PRO por tipo de dispositivo) -->
  <div class="modal fade" id="mdlActivo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <div class="chip">Activo</div>
            <div class="muted mt-1">Inventario por edificio (campos aparecen según <b>Dispositivo</b>).</div>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>

        <div class="modal-body">
          <input type="hidden" id="a_id" value="0">

          <div class="grid-2">
            <!-- IZQUIERDA -->
            <div>
              <div class="chip mb-2">Identificación</div>
              <div class="row g-3">

                <div class="col-md-4">
                  <label class="form-label">Categoría</label>
                  <select class="form-select" id="a_categoria">
                    <option value="informatica">Informática</option>
                    <option value="redes">Redes</option>
                    <option value="perifericos">Periféricos</option>
                    <option value="repuestos">Repuestos</option>
                    <option value="otros">Otros</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Tipo (tu enum)</label>
                  <select class="form-select" id="a_tipo">
                    <option value="pc">pc</option>
                    <option value="camara">camara</option>
                    <option value="herramienta">herramienta</option>
                    <option value="mueble">mueble</option>
                    <option value="insumo">insumo</option>
                    <option value="otro">otro</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Dispositivo</label>
                  <select class="form-select" id="a_dispositivo_tipo">
                    <option value="">—</option>
                    <option value="PC">PC</option>
                    <option value="NOTEBOOK">NOTEBOOK</option>
                    <option value="SERVIDOR">SERVIDOR</option>
                    <option value="IMPRESORA">IMPRESORA</option>
                    <option value="MODEM">MODEM</option>
                    <option value="ROUTER">ROUTER</option>
                    <option value="SWITCH">SWITCH</option>
                    <option value="AP">AP</option>
                    <option value="OTRO">OTRO</option>
                  </select>
                </div>

                <div class="col-md-4">
                  <label class="form-label">Etiqueta</label>
                  <input class="form-control" id="a_etiqueta" maxlength="120" placeholder="Ej: PC-S3-01">
                </div>
                <div class="col-md-8">
                  <label class="form-label">Descripción *</label>
                  <input class="form-control" id="a_descripcion" maxlength="255" placeholder="Ej: PC escritorio S3 — HP ProDesk">
                </div>

                <div class="col-md-4">
                  <label class="form-label">Marca</label>
                  <input class="form-control" id="a_marca" maxlength="190" placeholder="Ej: Dell">
                </div>
                <div class="col-md-4">
                  <label class="form-label">Modelo</label>
                  <input class="form-control" id="a_modelo" maxlength="190" placeholder="Ej: Optiplex 7060 / HP M404dn / Cisco 2960">
                </div>
                <div class="col-md-4">
                  <label class="form-label">N° Serie</label>
                  <input class="form-control" id="a_nro_serie" maxlength="190">
                </div>

                <div class="col-md-3">
                  <label class="form-label">Estado</label>
                  <select class="form-select" id="a_estado">
                    <option value="operativo">operativo</option>
                    <option value="mantenimiento">mantenimiento</option>
                    <option value="baja">baja</option>
                    <option value="roto">roto</option>
                    <option value="prestamo">prestamo</option>
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label">Condición</label>
                  <select class="form-select" id="a_condicion">
                    <option value="activo">activo</option>
                    <option value="deposito">deposito</option>
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label">Fecha alta</label>
                  <input type="date" class="form-control" id="a_fecha_alta">
                </div>
                <div class="col-md-3">
                  <label class="form-label">Ubicación</label>
                  <input class="form-control" id="a_ubicacion_detalle" maxlength="190" placeholder="Ej: Oficina S3 / Rack / Mesa 1">
                </div>

                <div class="col-md-6">
                  <label class="form-label">Área</label>
                  <select class="form-select" id="a_area_id">
                    <option value="0">— Sin área —</option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Asignado a (personal)</label>
                  <select class="form-select" id="a_asignado_personal_id">
                    <option value="0">— Sin asignar —</option>
                  </select>
                </div>

                <div class="col-md-12">
                  <label class="form-label">Observaciones</label>
                  <textarea class="form-control" id="a_observaciones" rows="2" maxlength="65000" placeholder="Notas generales..."></textarea>
                </div>

              </div>
            </div>

            <!-- DERECHA -->
            <div>
              <div class="chip mb-2">Datos según dispositivo</div>

              <!-- ===== PC/NOTEBOOK/SERVIDOR ===== -->
              <div id="blkPC" style="display:none;">
                <div class="chip mb-2" style="background:rgba(34,197,94,.12); border-color:rgba(34,197,94,.25);">PC · NOTEBOOK · SERVIDOR</div>
                <div class="row g-3">

                  <div class="col-md-6">
                    <label class="form-label">Nombre del equipo (hostname)</label>
                    <input class="form-control" id="a_equipo_nombre" maxlength="120" placeholder="Ej: ECMILM-S3-PC01">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Usuario asignado (texto)</label>
                    <input class="form-control" id="a_usuario_asignado" maxlength="160" placeholder="Ej: Oficina S3 / Civil X">
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">Sistema operativo</label>
                    <input class="form-control" id="a_sistema_operativo" maxlength="120" placeholder="Ej: Windows 11 Pro / Ubuntu 22.04">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">CPU</label>
                    <input class="form-control" id="a_cpu" maxlength="120" placeholder="Ej: i5-8500 / Ryzen 5 5600G">
                  </div>

                  <div class="col-md-4">
                    <label class="form-label">RAM (GB)</label>
                    <input class="form-control" id="a_ram_gb" placeholder="Ej: 8 o 16">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Disco tipo</label>
                    <select class="form-select" id="a_disco_tipo">
                      <option value="">—</option>
                      <option value="HDD">HDD</option>
                      <option value="SSD">SSD</option>
                      <option value="NVME">NVME</option>
                      <option value="EMMC">EMMC</option>
                      <option value="OTRO">OTRO</option>
                    </select>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Disco (GB)</label>
                    <input class="form-control" id="a_disco_gb" placeholder="Ej: 240 / 480 / 1000">
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">Monitor</label>
                    <input class="form-control" id="a_monitor" maxlength="120" placeholder="Ej: 24'' Samsung">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Periféricos</label>
                    <input class="form-control" id="a_perifericos" maxlength="65000" placeholder="Ej: Teclado + Mouse + UPS + Scanner">
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">Antivirus (opcional)</label>
                    <input class="form-control" id="a_antivirus" maxlength="120" placeholder="Ej: Defender / ESET">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Office (opcional)</label>
                    <input class="form-control" id="a_office_version" maxlength="120" placeholder="Ej: Office 2021 / M365">
                  </div>

                  <div class="col-md-12">
                    <label class="form-label">Serial Windows (opcional)</label>
                    <input class="form-control mono" id="a_serial_windows" maxlength="120" placeholder="(si decidís guardarlo)">
                    <div class="small-help mt-1">Recomendación: dejar vacío si no hace falta.</div>
                  </div>
                </div>

                <div class="sep"></div>
              </div>

              <!-- ===== IMPRESORA ===== -->
              <div id="blkImpresora" style="display:none;">
                <div class="chip mb-2" style="background:rgba(251,191,36,.12); border-color:rgba(251,191,36,.25);">IMPRESORA</div>
                <div class="row g-3">
                  <div class="col-md-12">
                    <div class="small-help">
                      Para impresoras: completá <b>Modelo</b>, <b>Ubicación</b>, <b>IP/MAC</b> e <b>IP fija</b> si corresponde.
                    </div>
                  </div>
                </div>
                <div class="sep"></div>
              </div>

              <!-- ===== SWITCH/ROUTER/MODEM/AP ===== -->
              <div id="blkRed" style="display:none;">
                <div class="chip mb-2" style="background:rgba(14,165,233,.12); border-color:rgba(14,165,233,.25);">SWITCH · ROUTER · MODEM · AP</div>
                <div class="row g-3">
                  <div class="col-md-12">
                    <div class="small-help">
                      Para equipos de red: completá <b>Modelo</b>, <b>Ubicación</b>, <b>IP/MAC</b>, <b>IP fija</b> y <b>Observaciones</b> si hace falta.
                    </div>
                  </div>
                </div>
                <div class="sep"></div>
              </div>

              <!-- ===== CAMPOS DE RED (visibles para impresora/equipos de red y opcional para PC) ===== -->
              <div id="blkNetCore" style="display:none;">
                <div class="chip mb-2">IP / MAC</div>
                <div class="row g-3">
                  <div class="col-md-4">
                    <label class="form-label">IP</label>
                    <input class="form-control mono" id="a_ip" maxlength="45" placeholder="Ej: 192.168.10.25">
                    <div class="small-help mt-1">Rango sugerido: <span class="kbd mono" id="ipHint"></span></div>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">MAC</label>
                    <input class="form-control mono" id="a_mac" maxlength="32" placeholder="AA:BB:CC:DD:EE:FF">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">IP fija</label>
                    <select class="form-select" id="a_ip_fija">
                      <option value="0">No</option>
                      <option value="1">Sí</option>
                    </select>
                  </div>
                </div>

                <div class="sep"></div>
              </div>

              <!-- ===== RED AVANZADA (PC + Equipos de red) ===== -->
              <div id="blkRedAv" style="display:none;">
                <div class="chip mb-2">Red avanzada (opcional)</div>
                <div class="row g-3">
                  <div class="col-md-4">
                    <label class="form-label">Gateway</label>
                    <input class="form-control mono" id="a_ip_gateway" maxlength="45" placeholder="Ej: 192.168.10.1">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">DNS 1</label>
                    <input class="form-control mono" id="a_dns1" maxlength="45" placeholder="Ej: 8.8.8.8 / interno">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">DNS 2</label>
                    <input class="form-control mono" id="a_dns2" maxlength="45">
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">Switch puerto</label>
                    <input class="form-control" id="a_switch_puerto" maxlength="60" placeholder="Ej: SW1 Gi1/0/12">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Patchera puerto</label>
                    <input class="form-control" id="a_patchera_puerto" maxlength="60" placeholder="Ej: PCH-02 / 12">
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">Sector red</label>
                    <input class="form-control" id="a_sector_red" maxlength="120" placeholder="Ej: S3 / Administración / Sala Servidores">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">VLAN</label>
                    <input class="form-control" id="a_vlan" maxlength="40" placeholder="Ej: 10 / VLAN-ADM">
                  </div>
                </div>
              </div>

              <div class="sep"></div>
              <div class="small-help" id="hintAuto" style="display:none;">
                El formulario se ajusta automáticamente según el <b>Dispositivo</b>. Los campos que no aplican se guardan como <b>NULL</b>.
              </div>

            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn btn-outline-light btn-std" data-bs-dismiss="modal">Cancelar</button>
          <button class="btn btn-primary btn-std" id="btnSaveActivo">Guardar</button>
        </div>
      </div>
    </div>
  </div>

  <!-- MODAL INTERNET -->
  <div class="modal fade" id="mdlInternet" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <div class="chip">Internet</div>
            <div class="muted mt-1">Proveedores/servicios por edificio.</div>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="i_id" value="0">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Proveedor *</label>
              <input class="form-control" id="i_proveedor" maxlength="120" placeholder="Ej: ARSAT / Movistar / Claro">
            </div>
            <div class="col-md-6">
              <label class="form-label">Servicio</label>
              <input class="form-control" id="i_servicio" maxlength="120" placeholder="Ej: Fibra / Radioenlace / Satelital">
            </div>
            <div class="col-md-6">
              <label class="form-label">Plan</label>
              <input class="form-control" id="i_plan" maxlength="120">
            </div>
            <div class="col-md-3">
              <label class="form-label">Velocidad</label>
              <input class="form-control" id="i_velocidad" maxlength="80" placeholder="Ej: 300/50 Mbps">
            </div>
            <div class="col-md-3">
              <label class="form-label">Costo</label>
              <input class="form-control" id="i_costo" placeholder="Ej: 150000">
              <div class="small-help mt-1">Se guarda como decimal (ARS).</div>
            </div>
            <div class="col-md-4">
              <label class="form-label">IP Pública</label>
              <input class="form-control mono" id="i_ip_publica" maxlength="60" placeholder="Ej: 190.x.x.x o fija">
            </div>
            <div class="col-md-8">
              <label class="form-label">Nota</label>
              <input class="form-control" id="i_nota" maxlength="255" placeholder="Observaciones, horario, corte, ticket...">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline-light btn-std" data-bs-dismiss="modal">Cancelar</button>
          <button class="btn btn-primary btn-std" id="btnSaveInternet">Guardar</button>
        </div>
      </div>
    </div>
  </div>

  <!-- MODAL MANTENIMIENTO -->
  <div class="modal fade" id="mdlMant" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <div class="chip">Mantenimiento</div>
            <div class="muted mt-1">Registro de tareas preventivas/correctivas.</div>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="m_id" value="0">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Fecha *</label>
              <input type="date" class="form-control" id="m_fecha">
            </div>
            <div class="col-md-4">
              <label class="form-label">Tipo</label>
              <select class="form-select" id="m_tipo">
                <option value="preventivo">Preventivo</option>
                <option value="correctivo">Correctivo</option>
                <option value="instalacion">Instalación</option>
                <option value="red">Red</option>
                <option value="otros">Otros</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Activo asociado</label>
              <select class="form-select" id="m_activo_id">
                <option value="0">— Sin asociar —</option>
              </select>
              <div class="small-help mt-1">Si no aparece, cargalo primero en “Activos”.</div>
            </div>

            <div class="col-md-12">
              <label class="form-label">Detalle *</label>
              <textarea class="form-control" id="m_detalle" rows="3" maxlength="65000" placeholder="Qué se hizo, qué se cambió, diagnóstico..."></textarea>
            </div>

            <div class="col-md-8">
              <label class="form-label">Realizado por</label>
              <input class="form-control" id="m_realizado_por" maxlength="120" placeholder="Ej: Taller informática / Técnico X">
            </div>
            <div class="col-md-4">
              <label class="form-label">Costo</label>
              <input class="form-control" id="m_costo" placeholder="Ej: 45000">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline-light btn-std" data-bs-dismiss="modal">Cancelar</button>
          <button class="btn btn-primary btn-std" id="btnSaveMant">Guardar</button>
        </div>
      </div>
    </div>
  </div>
  <script>
    const MODO_EDIFICIO = <?= $modo_edificio ? 'true' : 'false' ?>;
    const EDIFICIO_ID = <?= (int)$edificio_id ?>;
    const PUBLIC_URL = <?= json_encode($PUBLIC_URL, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;

    const meta = {
      ip_desde: <?= json_encode((string)($meta['ip_desde'] ?? ''), JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>,
      ip_hasta: <?= json_encode((string)($meta['ip_hasta'] ?? ''), JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>,
      max_dispositivos: <?= json_encode($meta['max_dispositivos'] ?? null, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>,
      nota: <?= json_encode((string)($meta['nota'] ?? ''), JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>
    };

    async function apiGet(params){
      const url = new URL(location.href);
      url.search = '';
      for (const [k,v] of Object.entries(params)) url.searchParams.set(k, v);
      const r = await fetch(url.toString(), {credentials:'same-origin'});
      const j = await r.json().catch(()=>null);
      if (!j || !j.ok) throw new Error((j && j.error) ? j.error : 'Error de API');
      return j;
    }
    async function apiPost(api, payload){
      const url = new URL(location.href);
      url.search = '';
      url.searchParams.set('api', api);
      const r = await fetch(url.toString(), {
        method:'POST',
        credentials:'same-origin',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify(payload || {})
      });
      const j = await r.json().catch(()=>null);
      if (!j || !j.ok) throw new Error((j && j.error) ? j.error : 'Error de API');
      return j;
    }

    function money(v){
      if (v === null || v === undefined || v === '') return '';
      const n = Number(v);
      if (Number.isNaN(n)) return String(v);
      return n.toLocaleString('es-AR');
    }

    function setIpHint(){
      const el = document.getElementById('ipHint');
      if (!el) return;
      const a = (meta.ip_desde || '').trim();
      const b = (meta.ip_hasta || '').trim();
      el.textContent = (a || b) ? `${a || '—'} → ${b || '—'}` : '—';
    }

    function escapeHtml(s){
      return String(s)
        .replaceAll('&','&amp;')
        .replaceAll('<','&lt;')
        .replaceAll('>','&gt;')
        .replaceAll('"','&quot;')
        .replaceAll("'","&#039;");
    }

    /* =========================
       UI PRO por dispositivo
    ========================= */
    function isPCType(d){ return ['PC','NOTEBOOK','SERVIDOR'].includes(String(d||'').toUpperCase()); }
    function isPrinterType(d){ return String(d||'').toUpperCase() === 'IMPRESORA'; }
    function isNetDeviceType(d){ return ['SWITCH','ROUTER','MODEM','AP'].includes(String(d||'').toUpperCase()); }

    function showBlock(id, on){
      const el = document.getElementById(id);
      if (!el) return;
      el.style.display = on ? '' : 'none';
    }

    function applyDeviceUI(){
      const d = (document.getElementById('a_dispositivo_tipo')?.value || '').toUpperCase();

      showBlock('blkPC', isPCType(d));
      showBlock('blkImpresora', isPrinterType(d));
      showBlock('blkRed', isNetDeviceType(d));

      // IP/MAC: aplica a IMPRESORA y EQUIPOS DE RED, y opcional a PC (lo dejamos visible también para PC)
      showBlock('blkNetCore', isPCType(d) || isPrinterType(d) || isNetDeviceType(d));

      // Red avanzada: PC + equipos de red
      showBlock('blkRedAv', isPCType(d) || isNetDeviceType(d));

      const hint = document.getElementById('hintAuto');
      if (hint) hint.style.display = (d ? '' : 'none');

      setIpHint();
    }

    // Limpia campos NO aplicables => el backend los guardará como NULL
    function clearNonApplicableFields(){
      const d = (document.getElementById('a_dispositivo_tipo')?.value || '').toUpperCase();
      const set = (id, v) => { const el=document.getElementById(id); if(el) el.value = v; };

      if (isPCType(d)){
        // PC: mantiene PC fields + net + red av
        // Nada extra que limpiar (impresora/red usan campos comunes).
        return;
      }

      if (isPrinterType(d)){
        // Impresora: limpia campos PC y red avanzada (por defecto)
        set('a_equipo_nombre','');
        set('a_usuario_asignado','');
        set('a_sistema_operativo','');
        set('a_cpu','');
        set('a_ram_gb','');
        set('a_disco_tipo','');
        set('a_disco_gb','');
        set('a_monitor','');
        set('a_perifericos','');

        set('a_antivirus','');
        set('a_office_version','');
        set('a_serial_windows','');

        set('a_ip_gateway','');
        set('a_dns1','');
        set('a_dns2','');
        set('a_switch_puerto','');
        set('a_patchera_puerto','');
        set('a_sector_red','');
        set('a_vlan','');
        return;
      }

      if (isNetDeviceType(d)){
        // Equipo de red: limpia PC + software PC
        set('a_equipo_nombre','');
        set('a_usuario_asignado','');
        set('a_sistema_operativo','');
        set('a_cpu','');
        set('a_ram_gb','');
        set('a_disco_tipo','');
        set('a_disco_gb','');
        set('a_monitor','');
        set('a_perifericos','');

        set('a_antivirus','');
        set('a_office_version','');
        set('a_serial_windows','');
        return;
      }

      // OTRO / vacío: limpia todo lo PRO
      set('a_equipo_nombre','');
      set('a_usuario_asignado','');
      set('a_sistema_operativo','');
      set('a_cpu','');
      set('a_ram_gb','');
      set('a_disco_tipo','');
      set('a_disco_gb','');
      set('a_monitor','');
      set('a_perifericos','');

      set('a_ip','');
      set('a_mac','');
      set('a_ip_fija','0');

      set('a_antivirus','');
      set('a_office_version','');
      set('a_serial_windows','');

      set('a_ip_gateway','');
      set('a_dns1','');
      set('a_dns2','');
      set('a_switch_puerto','');
      set('a_patchera_puerto','');
      set('a_sector_red','');
      set('a_vlan','');
    }

    /* =========================
       MODO UNIDAD (LISTAS)
    ========================= */
    async function fillUnitFilters(){
      try{
        const ed = await apiGet({api:'edificios'});
        const sel = document.getElementById('f_edificio');
        if (sel){
          for (const r of (ed.edificios||[])){
            const opt = document.createElement('option');
            opt.value = String(r.id);
            opt.textContent = (r.nombre || ('Edificio ' + r.id));
            sel.appendChild(opt);
          }
        }
      }catch(_){}

      try{
        const ar = await apiGet({api:'areas'});
        const sel = document.getElementById('f_area');
        if (sel){
          for (const r of (ar.rows||[])){
            const opt = document.createElement('option');
            opt.value = String(r.id);
            opt.textContent = (r.nombre || ('Área ' + r.id));
            sel.appendChild(opt);
          }
        }
      }catch(_){}

      try{
        const pe = await apiGet({api:'personal'});
        const sel = document.getElementById('f_personal');
        if (sel){
          for (const r of (pe.rows||[])){
            const opt = document.createElement('option');
            opt.value = String(r.id);
            opt.textContent = r.label || (`ID ${r.id}`);
            sel.appendChild(opt);
          }
        }
      }catch(_){}
    }

    let unidadRows = [];

    async function loadUnidadActivos(){
      const tb = document.getElementById('tbUnidad');
      const empty = document.getElementById('unidadEmpty');
      if (!tb || !empty) return;
      tb.innerHTML = '';
      empty.style.display = 'none';

      const edificio_id = Number(document.getElementById('f_edificio')?.value || 0);
      const area_id = Number(document.getElementById('f_area')?.value || 0);
      const personal_id = Number(document.getElementById('f_personal')?.value || 0);

      let data;
      try{
        data = await apiGet({
          api:'activos_list_all',
          edificio_id: edificio_id || 0,
          area_id: area_id || 0,
          personal_id: personal_id || 0
        });
      }catch(err){
        empty.style.display = '';
        empty.textContent = 'Error: ' + err.message;
        return;
      }

      unidadRows = data.rows || [];
      renderUnidadTable();
    }

    function renderUnidadTable(){
      const tb = document.getElementById('tbUnidad');
      const empty = document.getElementById('unidadEmpty');
      const q = (document.getElementById('f_q')?.value || '').trim().toLowerCase();

      tb.innerHTML = '';
      empty.style.display = 'none';

      let rows = unidadRows.slice();
      if (q){
        rows = rows.filter(r=>{
          const blob = [
            r.edificio_nombre, r.etiqueta, r.descripcion, r.tipo, r.dispositivo_tipo,
            r.marca, r.modelo, r.nro_serie, r.estado, r.condicion,
            r.area_nombre, r.asignado_label, r.asignado_dni,
            r.equipo_nombre, r.usuario_asignado,
            r.sistema_operativo, r.cpu, r.ram_gb, r.disco_tipo, r.disco_gb,
            r.monitor, r.perifericos,
            r.ip, r.mac,
            r.antivirus, r.office_version, r.serial_windows,
            r.ip_gateway, r.dns1, r.dns2, r.switch_puerto, r.patchera_puerto, r.sector_red, r.vlan
          ].join(' ').toLowerCase();
          return blob.includes(q);
        });
      }

      if (!rows.length){
        empty.style.display = '';
        empty.textContent = 'Sin activos cargados en la unidad (o no hay coincidencias).';
        return;
      }

      for (const r of rows){
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td class="mono">${escapeHtml(String(r.id))}</td>
          <td>${escapeHtml(r.edificio_nombre || '')}</td>
          <td>${escapeHtml(r.etiqueta || '')}</td>
          <td>${escapeHtml(r.descripcion || '')}</td>
          <td><span class="badge-soft">${escapeHtml(r.tipo || '')}</span></td>
          <td>${escapeHtml(r.dispositivo_tipo || '')}</td>
          <td>${escapeHtml([r.marca||'', r.modelo||''].filter(Boolean).join(' '))}</td>
          <td class="mono">${escapeHtml(r.nro_serie || '')}</td>
          <td>${escapeHtml(r.estado || '')}</td>
          <td>${escapeHtml(r.condicion || '')}</td>
          <td>${escapeHtml(r.area_nombre || '')}</td>
          <td>${escapeHtml((r.asignado_label || '').replace(/^, /,''))}</td>
          <td class="mono">${escapeHtml(r.equipo_nombre || '')}</td>
          <td>${escapeHtml(r.usuario_asignado || '')}</td>
          <td class="mono">${escapeHtml(r.ip || '')}</td>
          <td class="mono">${escapeHtml(r.mac || '')}</td>
        `;
        tb.appendChild(tr);
      }
    }

    /* =========================
       MODO LISTA EDIFICIOS (cards)
    ========================= */
    async function loadEdificios(){
      const grid = document.getElementById('edificiosGrid');
      const empty = document.getElementById('edEmpty');
      if (!grid || !empty) return;

      grid.innerHTML = '';
      empty.style.display = 'none';

      let data;
      try{
        data = await apiGet({api:'edificios'});
      }catch(err){
        empty.style.display = '';
        empty.textContent = 'Error: ' + err.message;
        return;
      }

      const rows = data.edificios || [];
      if (!rows.length){
        empty.style.display = '';
        return;
      }

      for (const r of rows){
        const ip = (r.ip_desde || r.ip_hasta) ? `${r.ip_desde || '—'} → ${r.ip_hasta || '—'}` : '';
        const maxd = (r.max_dispositivos !== null && r.max_dispositivos !== '') ? `Máx: ${r.max_dispositivos}` : '';
        const nota = (r.nota || '').trim();

        const col = document.createElement('div');
        col.className = 'col-12 col-md-6 col-xl-4';

        const card = document.createElement('div');
        card.className = 'cardx ed-card h-100';
        card.onclick = () => {
          location.href = `${PUBLIC_URL}/informatica_inventarios.php?edificio_id=${encodeURIComponent(r.id)}`;
        };

        card.innerHTML = `
          <div class="d-flex align-items-start justify-content-between gap-2">
            <div>
              <div class="chip mb-2">EDIFICIO</div>
              <div style="font-weight:1000; font-size:1.02rem;">${escapeHtml(r.nombre || '')}</div>
              <div class="muted mt-1">
                ${ip ? `Rango IP: <span class="kbd mono">${escapeHtml(ip)}</span>` : '<span class="kbd">Sin rango IP</span>'}
                ${maxd ? `<span class="ms-2 badge-soft">${escapeHtml(maxd)}</span>` : ''}
              </div>
              ${nota ? `<div class="muted mt-2">Nota: <span class="kbd">${escapeHtml(nota)}</span></div>` : ''}
            </div>
            <div class="badge-soft">ID: ${escapeHtml(String(r.id))}</div>
          </div>
          <div class="sep"></div>
          <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="muted">Abrir inventarios →</div>
            <div class="btn-pill">Gestionar</div>
          </div>
        `;

        col.appendChild(card);
        grid.appendChild(col);
      }
    }

    /* =========================
       MODO EDIFICIO (CRUD)
    ========================= */
    const mdlActivo = MODO_EDIFICIO ? new bootstrap.Modal(document.getElementById('mdlActivo')) : null;
    const mdlInternet = MODO_EDIFICIO ? new bootstrap.Modal(document.getElementById('mdlInternet')) : null;
    const mdlMant = MODO_EDIFICIO ? new bootstrap.Modal(document.getElementById('mdlMant')) : null;

    let areasCache = [];
    let personalCache = [];

    async function loadAreasAndPersonal(){
      try{ const ar = await apiGet({api:'areas'}); areasCache = ar.rows || []; }catch(_){ areasCache = []; }
      try{ const pe = await apiGet({api:'personal'}); personalCache = pe.rows || []; }catch(_){ personalCache = []; }

      const selA = document.getElementById('a_area_id');
      if (selA){
        const cur = selA.value;
        selA.innerHTML = `<option value="0">— Sin área —</option>`;
        for (const r of areasCache){
          const opt = document.createElement('option');
          opt.value = String(r.id);
          opt.textContent = r.nombre || ('Área ' + r.id);
          selA.appendChild(opt);
        }
        selA.value = cur || '0';
      }

      const selP = document.getElementById('a_asignado_personal_id');
      if (selP){
        const cur = selP.value;
        selP.innerHTML = `<option value="0">— Sin asignar —</option>`;
        for (const r of personalCache){
          const opt = document.createElement('option');
          opt.value = String(r.id);
          opt.textContent = r.label || (`ID ${r.id}`);
          selP.appendChild(opt);
        }
        selP.value = cur || '0';
      }
    }

    function activoFormReset(){
      document.getElementById('a_id').value = '0';
      document.getElementById('a_categoria').value = 'informatica';
      document.getElementById('a_tipo').value = 'otro';
      document.getElementById('a_dispositivo_tipo').value = '';
      document.getElementById('a_etiqueta').value = '';
      document.getElementById('a_descripcion').value = '';
      document.getElementById('a_marca').value = '';
      document.getElementById('a_modelo').value = '';
      document.getElementById('a_nro_serie').value = '';
      document.getElementById('a_estado').value = 'operativo';
      document.getElementById('a_condicion').value = 'activo';
      document.getElementById('a_fecha_alta').value = '';
      document.getElementById('a_ubicacion_detalle').value = '';
      document.getElementById('a_area_id').value = '0';
      document.getElementById('a_asignado_personal_id').value = '0';
      document.getElementById('a_observaciones').value = '';

      // PC
      document.getElementById('a_equipo_nombre').value = '';
      document.getElementById('a_usuario_asignado').value = '';
      document.getElementById('a_sistema_operativo').value = '';
      document.getElementById('a_cpu').value = '';
      document.getElementById('a_ram_gb').value = '';
      document.getElementById('a_disco_tipo').value = '';
      document.getElementById('a_disco_gb').value = '';
      document.getElementById('a_monitor').value = '';
      document.getElementById('a_perifericos').value = '';

      // NET CORE
      document.getElementById('a_mac').value = '';
      document.getElementById('a_ip').value = '';
      document.getElementById('a_ip_fija').value = '0';

      // PRO
      document.getElementById('a_antivirus').value = '';
      document.getElementById('a_office_version').value = '';
      document.getElementById('a_serial_windows').value = '';

      document.getElementById('a_ip_gateway').value = '';
      document.getElementById('a_dns1').value = '';
      document.getElementById('a_dns2').value = '';
      document.getElementById('a_switch_puerto').value = '';
      document.getElementById('a_patchera_puerto').value = '';
      document.getElementById('a_sector_red').value = '';
      document.getElementById('a_vlan').value = '';

      applyDeviceUI();
    }

    function internetFormReset(){
      document.getElementById('i_id').value = '0';
      document.getElementById('i_proveedor').value = '';
      document.getElementById('i_servicio').value = '';
      document.getElementById('i_plan').value = '';
      document.getElementById('i_velocidad').value = '';
      document.getElementById('i_costo').value = '';
      document.getElementById('i_ip_publica').value = '';
      document.getElementById('i_nota').value = '';
    }

    function mantFormReset(){
      document.getElementById('m_id').value = '0';
      const today = new Date();
      const yyyy = today.getFullYear();
      const mm = String(today.getMonth()+1).padStart(2,'0');
      const dd = String(today.getDate()).padStart(2,'0');
      document.getElementById('m_fecha').value = `${yyyy}-${mm}-${dd}`;
      document.getElementById('m_tipo').value = 'preventivo';
      document.getElementById('m_activo_id').value = '0';
      document.getElementById('m_detalle').value = '';
      document.getElementById('m_realizado_por').value = '';
      document.getElementById('m_costo').value = '';
    }

    async function loadActivos(){
      const tb = document.getElementById('tbActivos');
      const empty = document.getElementById('activosEmpty');
      tb.innerHTML = '';
      empty.style.display = 'none';

      let data;
      try{
        data = await apiGet({api:'activos_list', edificio_id: EDIFICIO_ID});
      }catch(err){
        empty.style.display = '';
        empty.textContent = 'Error: ' + err.message;
        return;
      }

      const rows = data.rows || [];
      if (!rows.length){
        empty.style.display = '';
        return;
      }

      for (const r of rows){
        const tr = document.createElement('tr');
        const marcaModelo = [r.marca||'', r.modelo||''].filter(Boolean).join(' ');
        tr.innerHTML = `
          <td class="mono">${escapeHtml(String(r.id))}</td>
          <td>${escapeHtml(r.etiqueta || '')}</td>
          <td>${escapeHtml(r.descripcion || '')}</td>
          <td><span class="badge-soft">${escapeHtml(r.tipo || '')}</span></td>
          <td>${escapeHtml(r.dispositivo_tipo || '')}</td>
          <td>${escapeHtml(marcaModelo)}</td>
          <td class="mono">${escapeHtml(r.nro_serie || '')}</td>
          <td>${escapeHtml(r.estado || '')}</td>
          <td>${escapeHtml(r.condicion || '')}</td>
          <td>${escapeHtml(r.area_nombre || '')}</td>
          <td>${escapeHtml(((r.asignado_label || '')).replace(/^, /,''))}</td>
          <td class="mono">${escapeHtml(r.equipo_nombre || '')}</td>
          <td>${escapeHtml(r.usuario_asignado || '')}</td>
          <td class="mono">${escapeHtml(r.ip || '')}${Number(r.ip_fija||0)===1 ? ' 🔒' : ''}</td>
          <td class="mono">${escapeHtml(r.mac || '')}</td>
          <td>
            <div class="d-flex gap-2 flex-wrap">
              <button class="btn btn-sm btn-outline-info btn-std" data-act="edit">Editar</button>
              <button class="btn btn-sm btn-outline-danger btn-std" data-act="del">Eliminar</button>
            </div>
          </td>
        `;
        tr.querySelector('[data-act="edit"]').onclick = () => openEditActivo(r);
        tr.querySelector('[data-act="del"]').onclick = () => deleteActivo(r);
        tb.appendChild(tr);
      }
    }

    function openEditActivo(r){
      activoFormReset();
      document.getElementById('a_id').value = String(r.id || 0);
      document.getElementById('a_categoria').value = r.categoria || 'informatica';
      document.getElementById('a_tipo').value = r.tipo || 'otro';
      document.getElementById('a_dispositivo_tipo').value = r.dispositivo_tipo || '';
      document.getElementById('a_etiqueta').value = r.etiqueta || '';
      document.getElementById('a_descripcion').value = r.descripcion || '';
      document.getElementById('a_marca').value = r.marca || '';
      document.getElementById('a_modelo').value = r.modelo || '';
      document.getElementById('a_nro_serie').value = r.nro_serie || '';
      document.getElementById('a_estado').value = r.estado || 'operativo';
      document.getElementById('a_condicion').value = r.condicion || 'activo';
      document.getElementById('a_fecha_alta').value = r.fecha_alta || '';
      document.getElementById('a_ubicacion_detalle').value = r.ubicacion_detalle || '';
      document.getElementById('a_area_id').value = String(r.area_id || 0);
      document.getElementById('a_asignado_personal_id').value = String(r.asignado_personal_id || 0);
      document.getElementById('a_observaciones').value = r.observaciones || '';

      document.getElementById('a_equipo_nombre').value = r.equipo_nombre || '';
      document.getElementById('a_usuario_asignado').value = r.usuario_asignado || '';
      document.getElementById('a_sistema_operativo').value = r.sistema_operativo || '';
      document.getElementById('a_cpu').value = r.cpu || '';
      document.getElementById('a_ram_gb').value = (r.ram_gb ?? '') !== null ? String(r.ram_gb ?? '') : '';
      document.getElementById('a_disco_tipo').value = r.disco_tipo || '';
      document.getElementById('a_disco_gb').value = (r.disco_gb ?? '') !== null ? String(r.disco_gb ?? '') : '';
      document.getElementById('a_monitor').value = r.monitor || '';
      document.getElementById('a_perifericos').value = r.perifericos || '';

      document.getElementById('a_mac').value = r.mac || '';
      document.getElementById('a_ip').value = r.ip || '';
      document.getElementById('a_ip_fija').value = String(Number(r.ip_fija||0));

      // PRO
      document.getElementById('a_antivirus').value = r.antivirus || '';
      document.getElementById('a_office_version').value = r.office_version || '';
      document.getElementById('a_serial_windows').value = r.serial_windows || '';

      document.getElementById('a_ip_gateway').value = r.ip_gateway || '';
      document.getElementById('a_dns1').value = r.dns1 || '';
      document.getElementById('a_dns2').value = r.dns2 || '';
      document.getElementById('a_switch_puerto').value = r.switch_puerto || '';
      document.getElementById('a_patchera_puerto').value = r.patchera_puerto || '';
      document.getElementById('a_sector_red').value = r.sector_red || '';
      document.getElementById('a_vlan').value = r.vlan || '';

      applyDeviceUI();
      mdlActivo.show();
    }

    async function saveActivo(){
      // ✅ Limpia campos no aplicables (sin rellenar datos)
      clearNonApplicableFields();

      const payload = {
        id: Number(document.getElementById('a_id').value || 0),
        edificio_id: EDIFICIO_ID,

        categoria: document.getElementById('a_categoria').value,
        tipo: document.getElementById('a_tipo').value,
        dispositivo_tipo: document.getElementById('a_dispositivo_tipo').value,

        etiqueta: document.getElementById('a_etiqueta').value.trim(),
        descripcion: document.getElementById('a_descripcion').value.trim(),
        marca: document.getElementById('a_marca').value.trim(),
        modelo: document.getElementById('a_modelo').value.trim(),
        nro_serie: document.getElementById('a_nro_serie').value.trim(),

        estado: document.getElementById('a_estado').value,
        condicion: document.getElementById('a_condicion').value,
        fecha_alta: document.getElementById('a_fecha_alta').value,
        ubicacion_detalle: document.getElementById('a_ubicacion_detalle').value.trim(),

        area_id: Number(document.getElementById('a_area_id').value || 0),
        asignado_personal_id: Number(document.getElementById('a_asignado_personal_id').value || 0),

        observaciones: document.getElementById('a_observaciones').value.trim(),

        equipo_nombre: document.getElementById('a_equipo_nombre').value.trim(),
        usuario_asignado: document.getElementById('a_usuario_asignado').value.trim(),
        sistema_operativo: document.getElementById('a_sistema_operativo').value.trim(),
        cpu: document.getElementById('a_cpu').value.trim(),
        ram_gb: document.getElementById('a_ram_gb').value.trim(),
        disco_tipo: document.getElementById('a_disco_tipo').value,
        disco_gb: document.getElementById('a_disco_gb').value.trim(),
        monitor: document.getElementById('a_monitor').value.trim(),
        perifericos: document.getElementById('a_perifericos').value.trim(),

        mac: document.getElementById('a_mac').value.trim(),
        ip: document.getElementById('a_ip').value.trim(),
        ip_fija: Number(document.getElementById('a_ip_fija').value || 0),

        // PRO
        antivirus: document.getElementById('a_antivirus').value.trim(),
        office_version: document.getElementById('a_office_version').value.trim(),
        serial_windows: document.getElementById('a_serial_windows').value.trim(),

        ip_gateway: document.getElementById('a_ip_gateway').value.trim(),
        dns1: document.getElementById('a_dns1').value.trim(),
        dns2: document.getElementById('a_dns2').value.trim(),
        switch_puerto: document.getElementById('a_switch_puerto').value.trim(),
        patchera_puerto: document.getElementById('a_patchera_puerto').value.trim(),
        sector_red: document.getElementById('a_sector_red').value.trim(),
        vlan: document.getElementById('a_vlan').value.trim()
      };

      if (!payload.descripcion && !payload.etiqueta){
        Swal.fire({icon:'warning', title:'Falta descripción', text:'Cargá al menos “Descripción” o “Etiqueta”.'});
        return;
      }

      try{
        await apiPost('activos_save', payload);
        mdlActivo.hide();
        await loadActivos();
        await loadActivosCombo();
        Swal.fire({icon:'success', title:'Guardado', timer:900, showConfirmButton:false});
      }catch(err){
        Swal.fire({icon:'error', title:'Error', text: err.message});
      }
    }

    async function deleteActivo(r){
      const res = await Swal.fire({
        icon:'warning',
        title:'Eliminar activo',
        html:`¿Eliminar <b>${escapeHtml(r.descripcion || r.etiqueta || '')}</b>?`,
        showCancelButton:true,
        confirmButtonText:'Eliminar',
        cancelButtonText:'Cancelar',
        confirmButtonColor:'#ef4444'
      });
      if (!res.isConfirmed) return;

      try{
        await apiPost('activos_delete', {id: Number(r.id), edificio_id: EDIFICIO_ID});
        await loadActivos();
        await loadActivosCombo();
        Swal.fire({icon:'success', title:'Eliminado', timer:900, showConfirmButton:false});
      }catch(err){
        Swal.fire({icon:'error', title:'Error', text: err.message});
      }
    }

    async function loadInternet(){
      const tb = document.getElementById('tbInternet');
      const empty = document.getElementById('internetEmpty');
      tb.innerHTML = '';
      empty.style.display = 'none';

      let data;
      try{
        data = await apiGet({api:'internet_list', edificio_id: EDIFICIO_ID});
      }catch(err){
        empty.style.display = '';
        empty.textContent = 'Error: ' + err.message;
        return;
      }

      const rows = data.rows || [];
      if (!rows.length){
        empty.style.display = '';
        return;
      }

      for (const r of rows){
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td class="mono">${escapeHtml(String(r.id))}</td>
          <td>${escapeHtml(r.proveedor || '')}</td>
          <td>${escapeHtml(r.servicio || '')}</td>
          <td>${escapeHtml(r.plan || '')}</td>
          <td>${escapeHtml(r.velocidad || '')}</td>
          <td class="mono">${escapeHtml(money(r.costo))}</td>
          <td class="mono">${escapeHtml(r.ip_publica || '')}</td>
          <td>${escapeHtml(r.nota || '')}</td>
          <td>
            <div class="d-flex gap-2 flex-wrap">
              <button class="btn btn-sm btn-outline-info btn-std" data-act="edit">Editar</button>
              <button class="btn btn-sm btn-outline-danger btn-std" data-act="del">Eliminar</button>
            </div>
          </td>
        `;
        tr.querySelector('[data-act="edit"]').onclick = () => openEditInternet(r);
        tr.querySelector('[data-act="del"]').onclick = () => deleteInternet(r);
        tb.appendChild(tr);
      }
    }

    function openEditInternet(r){
      internetFormReset();
      document.getElementById('i_id').value = String(r.id || 0);
      document.getElementById('i_proveedor').value = r.proveedor || '';
      document.getElementById('i_servicio').value = r.servicio || '';
      document.getElementById('i_plan').value = r.plan || '';
      document.getElementById('i_velocidad').value = r.velocidad || '';
      document.getElementById('i_costo').value = (r.costo ?? '') !== null ? String(r.costo ?? '') : '';
      document.getElementById('i_ip_publica').value = r.ip_publica || '';
      document.getElementById('i_nota').value = r.nota || '';
      mdlInternet.show();
    }

    async function saveInternet(){
      const payload = {
        id: Number(document.getElementById('i_id').value || 0),
        edificio_id: EDIFICIO_ID,
        proveedor: document.getElementById('i_proveedor').value.trim(),
        servicio: document.getElementById('i_servicio').value.trim(),
        plan: document.getElementById('i_plan').value.trim(),
        velocidad: document.getElementById('i_velocidad').value.trim(),
        costo: document.getElementById('i_costo').value.trim(),
        ip_publica: document.getElementById('i_ip_publica').value.trim(),
        nota: document.getElementById('i_nota').value.trim()
      };
      if (!payload.proveedor){
        Swal.fire({icon:'warning', title:'Falta el proveedor', text:'El campo “Proveedor” es obligatorio.'});
        return;
      }
      try{
        await apiPost('internet_save', payload);
        mdlInternet.hide();
        await loadInternet();
        Swal.fire({icon:'success', title:'Guardado', timer:900, showConfirmButton:false});
      }catch(err){
        Swal.fire({icon:'error', title:'Error', text: err.message});
      }
    }

    async function deleteInternet(r){
      const res = await Swal.fire({
        icon:'warning',
        title:'Eliminar registro',
        html:`¿Eliminar <b>${escapeHtml(r.proveedor || '')}</b>?`,
        showCancelButton:true,
        confirmButtonText:'Eliminar',
        cancelButtonText:'Cancelar',
        confirmButtonColor:'#ef4444'
      });
      if (!res.isConfirmed) return;

      try{
        await apiPost('internet_delete', {id: Number(r.id), edificio_id: EDIFICIO_ID});
        await loadInternet();
        Swal.fire({icon:'success', title:'Eliminado', timer:900, showConfirmButton:false});
      }catch(err){
        Swal.fire({icon:'error', title:'Error', text: err.message});
      }
    }

    async function loadMant(){
      const tb = document.getElementById('tbMant');
      const empty = document.getElementById('mantEmpty');
      tb.innerHTML = '';
      empty.style.display = 'none';

      let data;
      try{
        data = await apiGet({api:'mant_list', edificio_id: EDIFICIO_ID});
      }catch(err){
        empty.style.display = '';
        empty.textContent = 'Error: ' + err.message;
        return;
      }

      const rows = data.rows || [];
      if (!rows.length){
        empty.style.display = '';
        return;
      }

      for (const r of rows){
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td class="mono">${escapeHtml(String(r.id))}</td>
          <td class="mono">${escapeHtml(r.fecha || '')}</td>
          <td>${escapeHtml(r.tipo || '')}</td>
          <td>${escapeHtml(r.activo_nombre || '')}</td>
          <td>${escapeHtml(r.detalle || '')}</td>
          <td>${escapeHtml(r.realizado_por || '')}</td>
          <td class="mono">${escapeHtml(money(r.costo))}</td>
          <td>
            <div class="d-flex gap-2 flex-wrap">
              <button class="btn btn-sm btn-outline-info btn-std" data-act="edit">Editar</button>
              <button class="btn btn-sm btn-outline-danger btn-std" data-act="del">Eliminar</button>
            </div>
          </td>
        `;
        tr.querySelector('[data-act="edit"]').onclick = () => openEditMant(r);
        tr.querySelector('[data-act="del"]').onclick = () => deleteMant(r);
        tb.appendChild(tr);
      }
    }

    async function loadActivosCombo(){
      const sel = document.getElementById('m_activo_id');
      if (!sel) return;
      sel.innerHTML = `<option value="0">— Sin asociar —</option>`;
      try{
        const data = await apiGet({api:'activos_combo', edificio_id: EDIFICIO_ID});
        for (const r of (data.rows||[])){
          const opt = document.createElement('option');
          opt.value = String(r.id);
          opt.textContent = r.nombre || ('ID ' + r.id);
          sel.appendChild(opt);
        }
      }catch(_){}
    }

    function openEditMant(r){
      mantFormReset();
      document.getElementById('m_id').value = String(r.id || 0);
      document.getElementById('m_fecha').value = r.fecha || '';
      document.getElementById('m_tipo').value = r.tipo || 'preventivo';
      document.getElementById('m_activo_id').value = String(r.activo_id || 0);
      document.getElementById('m_detalle').value = r.detalle || '';
      document.getElementById('m_realizado_por').value = r.realizado_por || '';
      document.getElementById('m_costo').value = (r.costo ?? '') !== null ? String(r.costo ?? '') : '';
      mdlMant.show();
    }

    async function saveMant(){
      const payload = {
        id: Number(document.getElementById('m_id').value || 0),
        edificio_id: EDIFICIO_ID,
        fecha: document.getElementById('m_fecha').value,
        tipo: document.getElementById('m_tipo').value,
        activo_id: Number(document.getElementById('m_activo_id').value || 0),
        detalle: document.getElementById('m_detalle').value.trim(),
        realizado_por: document.getElementById('m_realizado_por').value.trim(),
        costo: document.getElementById('m_costo').value.trim()
      };
      if (!payload.fecha){
        Swal.fire({icon:'warning', title:'Falta fecha', text:'La fecha es obligatoria.'});
        return;
      }
      if (!payload.detalle){
        Swal.fire({icon:'warning', title:'Falta detalle', text:'El detalle es obligatorio.'});
        return;
      }

      try{
        await apiPost('mant_save', payload);
        mdlMant.hide();
        await loadMant();
        Swal.fire({icon:'success', title:'Guardado', timer:900, showConfirmButton:false});
      }catch(err){
        Swal.fire({icon:'error', title:'Error', text: err.message});
      }
    }

    async function deleteMant(r){
      const res = await Swal.fire({
        icon:'warning',
        title:'Eliminar mantenimiento',
        html:`¿Eliminar el registro del <b>${escapeHtml(r.fecha || '')}</b>?`,
        showCancelButton:true,
        confirmButtonText:'Eliminar',
        cancelButtonText:'Cancelar',
        confirmButtonColor:'#ef4444'
      });
      if (!res.isConfirmed) return;

      try{
        await apiPost('mant_delete', {id: Number(r.id), edificio_id: EDIFICIO_ID});
        await loadMant();
        Swal.fire({icon:'success', title:'Eliminado', timer:900, showConfirmButton:false});
      }catch(err){
        Swal.fire({icon:'error', title:'Error', text: err.message});
      }
    }

    /* =========================
       Init
    ========================= */
    document.addEventListener('DOMContentLoaded', async () => {
      if (!MODO_EDIFICIO){
        await fillUnitFilters();
        await loadUnidadActivos();
        await loadEdificios();

        document.getElementById('btnReloadUnit')?.addEventListener('click', loadUnidadActivos);
        document.getElementById('btnApplyFilters')?.addEventListener('click', loadUnidadActivos);
        document.getElementById('btnClearFilters')?.addEventListener('click', () => {
          document.getElementById('f_edificio').value = '0';
          document.getElementById('f_area').value = '0';
          document.getElementById('f_personal').value = '0';
          document.getElementById('f_q').value = '';
          loadUnidadActivos();
        });
        document.getElementById('f_q')?.addEventListener('input', renderUnidadTable);
        return;
      }

      // modo edificio
      await loadAreasAndPersonal();
      await loadActivos();
      await loadInternet();
      await loadMant();
      await loadActivosCombo();

      setIpHint();
      applyDeviceUI();

      document.getElementById('a_dispositivo_tipo')?.addEventListener('change', applyDeviceUI);

      document.getElementById('btnAddActivo')?.addEventListener('click', () => { activoFormReset(); mdlActivo.show(); });
      document.getElementById('btnAddInternet')?.addEventListener('click', () => { internetFormReset(); mdlInternet.show(); });
      document.getElementById('btnAddMant')?.addEventListener('click', () => { mantFormReset(); mdlMant.show(); });

      document.getElementById('btnSaveActivo')?.addEventListener('click', saveActivo);
      document.getElementById('btnSaveInternet')?.addEventListener('click', saveInternet);
      document.getElementById('btnSaveMant')?.addEventListener('click', saveMant);

      document.getElementById('btnReloadAll')?.addEventListener('click', async () => {
        await loadActivos();
        await loadInternet();
        await loadMant();
        await loadActivosCombo();
      });
    });
  </script>
</body>
</html>
