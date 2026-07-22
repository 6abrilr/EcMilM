<?php
declare(strict_types=1);

if (PHP_SAPI === 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
  ini_set('session.save_path', sys_get_temp_dir());
}

require_once __DIR__ . '/../../auth/bootstrap.php';
if (PHP_SAPI !== 'cli') {
  require_login();
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Usá POST.'], JSON_UNESCAPED_UNICODE);
    exit;
  }
  csrf_verify();
}
require_once __DIR__ . '/../../config/db.php';

if (PHP_SAPI !== 'cli') {
  $syncUser = current_user() ?: [];
  if (strtolower(trim((string)($syncUser['username'] ?? ''))) !== 'nesrojas') {
    http_response_code(403);
    sync_json(['ok' => false, 'error' => 'Acceso restringido.']);
  }
}

function sync_json(array $payload): void {
  if (PHP_SAPI !== 'cli') header('Content-Type: application/json; charset=utf-8');
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  if (PHP_SAPI === 'cli') echo PHP_EOL;
  exit;
}

function sync_norm_mac(?string $value): string {
  $hex = strtolower(preg_replace('/[^0-9a-f]/i', '', (string)$value));
  if (strlen($hex) < 12) return '';
  $hex = substr($hex, 0, 12);
  return implode(':', str_split($hex, 2));
}

function sync_norm_name(?string $value): string {
  $name = strtoupper(trim((string)$value));
  $name = preg_replace('/\..*$/', '', $name) ?? $name;
  $name = preg_replace('/\s+/', '', $name) ?? $name;
  return $name;
}

function sync_valid_ip(?string $value): string {
  $ip = trim((string)$value);
  return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? $ip : '';
}

function sync_is_bad_name(string $name): bool {
  return $name === '' || in_array($name, ['LOADING', 'LOADING...', 'DESCONOCIDO', 'UNKNOWN', 'LOCALHOST'], true);
}

function sync_scanner_to_inventory(PDO $pdo, bool $dryRun = false): array {
  $scannerDbPath = realpath(__DIR__ . '/network-manager/data/network.db');
  if (!$scannerDbPath || !is_file($scannerDbPath)) {
    throw new RuntimeException('No se encontró la base del scanner.');
  }

  $unidadId = function_exists('unidad_activa_id') ? unidad_activa_id() : 1;

  $sqlite = new PDO('sqlite:' . $scannerDbPath);
  $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $devices = $sqlite->query("
    SELECT id, hostname, custom_name, ip, mac, device_type, is_online, last_seen
    FROM devices
    WHERE mac IS NOT NULL AND mac <> ''
    ORDER BY is_online DESC, last_seen DESC, id DESC
  ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

  $byName = [];
  $nameCounts = [];
  $scannerCount = 0;
  foreach ($devices as $device) {
    $mac = sync_norm_mac((string)($device['mac'] ?? ''));
    $ip = sync_valid_ip((string)($device['ip'] ?? ''));
    if ($mac === '') continue;
    $scannerCount++;

    $names = [
      sync_norm_name((string)($device['custom_name'] ?? '')),
      sync_norm_name((string)($device['hostname'] ?? '')),
    ];
    foreach (array_unique($names) as $name) {
      if (sync_is_bad_name($name)) continue;
      $nameCounts[$name] = ($nameCounts[$name] ?? 0) + 1;
      if (!isset($byName[$name])) {
        $byName[$name] = [
          'scanner_id' => (int)$device['id'],
          'name' => $name,
          'hostname' => (string)($device['hostname'] ?? ''),
          'custom_name' => (string)($device['custom_name'] ?? ''),
          'mac' => $mac,
          'ip' => $ip,
          'device_type' => (string)($device['device_type'] ?? ''),
          'last_seen' => (string)($device['last_seen'] ?? ''),
        ];
      }
    }
  }

  foreach ($nameCounts as $name => $count) {
    if ($count > 1) unset($byName[$name]);
  }

  $st = $pdo->prepare("
    SELECT id, equipo_nombre, mac, ip, dispositivo_tipo
    FROM it_activos
    WHERE unidad_id = :uid
      AND categoria = 'informatica'
      AND condicion <> 'deposito'
      AND equipo_nombre IS NOT NULL
      AND equipo_nombre <> ''
    ORDER BY id ASC
  ");
  $st->execute([':uid' => $unidadId]);
  $activos = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  $upd = $pdo->prepare("
    UPDATE it_activos
    SET mac = CASE WHEN (mac IS NULL OR mac = '') THEN :mac ELSE mac END,
        ip = CASE WHEN (ip IS NULL OR ip = '') THEN :ip ELSE ip END,
        actualizado_en = NOW()
    WHERE id = :id
      AND unidad_id = :uid
      AND (
        (mac IS NULL OR mac = '')
        OR (ip IS NULL OR ip = '')
      )
    LIMIT 1
  ");

  $updated = [];
  $skipped = [];
  $matches = 0;
  foreach ($activos as $activo) {
    $name = sync_norm_name((string)($activo['equipo_nombre'] ?? ''));
    if ($name === '' || !isset($byName[$name])) continue;
    $matches++;

    $device = $byName[$name];
    $oldMac = sync_norm_mac((string)($activo['mac'] ?? ''));
    $oldIp = sync_valid_ip((string)($activo['ip'] ?? ''));
    $newMac = $device['mac'];
    $newIp = $device['ip'];

    if ($oldMac !== '' && $oldMac !== $newMac) {
      $skipped[] = [
        'id' => (int)$activo['id'],
        'equipo' => (string)$activo['equipo_nombre'],
        'motivo' => 'MAC existente distinta',
        'mac_inventario' => $oldMac,
        'mac_scanner' => $newMac,
      ];
      continue;
    }

    if ($oldMac !== '' && ($oldIp !== '' || $newIp === '')) continue;
    if ($newMac === '') continue;

    if (!$dryRun) {
      $upd->execute([
        ':mac' => $newMac,
        ':ip' => $newIp !== '' ? $newIp : null,
        ':id' => (int)$activo['id'],
        ':uid' => $unidadId,
      ]);
    }

    $updated[] = [
      'id' => (int)$activo['id'],
      'equipo' => (string)$activo['equipo_nombre'],
      'mac_antes' => $oldMac,
      'mac_nueva' => $newMac,
      'ip_antes' => $oldIp,
      'ip_nueva' => $newIp,
      'scanner_hostname' => $device['hostname'],
      'scanner_id' => $device['scanner_id'],
    ];
  }

  return [
    'ok' => true,
    'dry_run' => $dryRun,
    'unidad_id' => $unidadId,
    'scanner_devices_con_mac' => $scannerCount,
    'scanner_nombres_unicos' => count($byName),
    'activos_revisados' => count($activos),
    'coincidencias_por_nombre' => $matches,
    'actualizados' => count($updated),
    'omitidos' => count($skipped),
    'detalle_actualizados' => $updated,
    'detalle_omitidos' => $skipped,
  ];
}

function sync_store_remote_scan(PDO $pdo): array {
  $point = trim((string)($_POST['scan_point'] ?? ''));
  $computer = substr(trim((string)($_POST['computer_name'] ?? '')), 0, 120);
  $decoded = json_decode((string)($_POST['devices_json'] ?? ''), true);
  if ($point === '' || !is_array($decoded)) throw new RuntimeException('Faltan ubicación o resultados del agente.');
  if (strlen($point) > 160 || count($decoded) > 4096) throw new RuntimeException('Escaneo fuera de los límites permitidos.');

  $pdo->exec("CREATE TABLE IF NOT EXISTS it_network_sightings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    unidad_id INT NOT NULL,
    scan_point VARCHAR(160) NOT NULL,
    scanner_computer VARCHAR(120) NOT NULL DEFAULT '',
    mac VARCHAR(17) NOT NULL,
    ip VARCHAR(45) NOT NULL DEFAULT '',
    hostname VARCHAR(255) NOT NULL DEFAULT '',
    vendor VARCHAR(255) NOT NULL DEFAULT '',
    device_type VARCHAR(120) NOT NULL DEFAULT '',
    is_online TINYINT(1) NOT NULL DEFAULT 0,
    first_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_network_sighting (unidad_id, scan_point, mac),
    KEY ix_network_point (unidad_id, scan_point),
    KEY ix_network_mac (mac)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $unidadId = function_exists('unidad_activa_id') ? unidad_activa_id() : 1;
  $up = $pdo->prepare("INSERT INTO it_network_sightings
    (unidad_id, scan_point, scanner_computer, mac, ip, hostname, vendor, device_type, is_online)
    VALUES (:uid,:point,:computer,:mac,:ip,:hostname,:vendor,:dtype,:online)
    ON DUPLICATE KEY UPDATE scanner_computer=VALUES(scanner_computer), ip=VALUES(ip),
      hostname=VALUES(hostname), vendor=VALUES(vendor), device_type=VALUES(device_type),
      is_online=VALUES(is_online), last_seen=NOW()");
  $markPointOffline = $pdo->prepare("UPDATE it_network_sightings SET is_online=0
    WHERE unidad_id=:uid AND scan_point=:point");
  $markPointOffline->execute([':uid'=>$unidadId, ':point'=>$point]);
  $saved = 0;
  $remoteByName = [];
  $remoteByMac = [];
  foreach ($decoded as $device) {
    if (!is_array($device)) continue;
    $mac = sync_norm_mac((string)($device['mac'] ?? ''));
    if ($mac === '') continue;
    $up->execute([':uid'=>$unidadId, ':point'=>$point, ':computer'=>$computer, ':mac'=>$mac,
      ':ip'=>sync_valid_ip((string)($device['ip'] ?? '')), ':hostname'=>substr(trim((string)($device['hostname'] ?? '')),0,255),
      ':vendor'=>substr(trim((string)($device['vendor'] ?? '')),0,255), ':dtype'=>substr(trim((string)($device['device_type'] ?? '')),0,120),
      ':online'=>!empty($device['is_online']) ? 1 : 0]);
    $remoteByMac[$mac] = ['mac'=>$mac, 'ip'=>sync_valid_ip((string)($device['ip'] ?? ''))];
    foreach (array_unique([
      sync_norm_name((string)($device['custom_name'] ?? '')),
      sync_norm_name((string)($device['hostname'] ?? '')),
    ]) as $normalizedName) {
      if (!sync_is_bad_name($normalizedName)) {
        if (isset($remoteByName[$normalizedName])) $remoteByName[$normalizedName] = null;
        else $remoteByName[$normalizedName] = ['mac'=>$mac, 'ip'=>sync_valid_ip((string)($device['ip'] ?? ''))];
      }
    }
    $saved++;
  }

  // La comparación se hace en el servidor central. Así los agentes remotos no
  // necesitan conectarse directamente a MySQL ni conocer sus credenciales.
  $inventory = $pdo->prepare("SELECT a.id, a.equipo_nombre, a.mac, a.ip, a.dispositivo_tipo,
      COALESCE(NULLIF(a.propietario_nombre,''), NULLIF(pu.apellido_nombre,''), NULLIF(a.usuario_asignado,''), '') AS responsable,
      COALESCE(NULLIF(adi.nombre,''), NULLIF(a.sector_red,''), '') AS area_nombre
    FROM it_activos a
    LEFT JOIN personal_unidad pu ON pu.id=a.asignado_personal_id
    LEFT JOIN destino_interno adi ON adi.id=a.area_id
    WHERE a.unidad_id=:uid AND a.categoria='informatica' AND a.condicion<>'deposito'
      AND a.equipo_nombre IS NOT NULL AND a.equipo_nombre<>''");
  $inventory->execute([':uid'=>$unidadId]);
  $update = $pdo->prepare("UPDATE it_activos SET
    mac=CASE WHEN mac IS NULL OR mac='' THEN :mac ELSE mac END,
    ip=CASE WHEN ip IS NULL OR ip='' OR ip NOT REGEXP '^[0-9]{1,3}(\\.[0-9]{1,3}){3}$' THEN :ip ELSE ip END,
    actualizado_en=NOW()
    WHERE id=:id AND unidad_id=:uid AND ((mac IS NULL OR mac='') OR (ip IS NULL OR ip='')) LIMIT 1");
  $matches = 0;
  $updated = 0;
  $enrichments = [];
  foreach ($inventory->fetchAll(PDO::FETCH_ASSOC) ?: [] as $asset) {
    $name = sync_norm_name((string)$asset['equipo_nombre']);
    $assetMac = sync_norm_mac((string)($asset['mac'] ?? ''));
    $hit = $assetMac !== '' ? ($remoteByMac[$assetMac] ?? null) : null;
    if (!is_array($hit)) $hit = $remoteByName[$name] ?? null;
    if (!is_array($hit)) continue;
    $enrichments[$hit['mac']] = [
      'inventory_match'=>true,
      'inventory_display_name'=>(string)$asset['equipo_nombre'],
      'inventory_owner_display'=>(string)$asset['responsable'],
      'inventory_area'=>(string)$asset['area_nombre'],
      'inventory_type'=>(string)$asset['dispositivo_tipo'],
    ];
    $matches++;
    $oldMac = sync_norm_mac((string)($asset['mac'] ?? ''));
    if ($oldMac !== '' && $oldMac !== $hit['mac']) continue; // nunca pisar una MAC distinta
    $update->execute([':mac'=>$hit['mac'], ':ip'=>$hit['ip'] !== '' ? $hit['ip'] : null,
      ':id'=>(int)$asset['id'], ':uid'=>$unidadId]);
    if ($update->rowCount() > 0) $updated++;
  }

  return ['ok'=>true, 'guardados'=>$saved, 'punto'=>$point,
    'coincidencias_por_nombre'=>$matches, 'actualizados'=>$updated,
    'enriquecimientos'=>$enrichments];
}

try {
  if (PHP_SAPI !== 'cli' && isset($_POST['devices_json'])) sync_json(sync_store_remote_scan($pdo));
  $dryRun = PHP_SAPI !== 'cli'
    ? ((string)($_POST['dry_run'] ?? '') === '1')
    : in_array('--dry-run', $argv ?? [], true);
  sync_json(sync_scanner_to_inventory($pdo, $dryRun));
} catch (Throwable $e) {
  sync_json(['ok' => false, 'error' => $e->getMessage()]);
}
