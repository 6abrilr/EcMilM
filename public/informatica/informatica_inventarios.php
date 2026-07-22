<?php
/**
 * ea/public/informatica/informatica_inventarios.php
 * INFORMÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚ÂTICA ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â· INVENTARIOS (unificado con Red) ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ADAPTADO A TU TABLA it_activos REAL
 *
 * ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¦ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¦ NUEVO (PRO):
 * - Modal dinÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡mico por dispositivo_tipo:
 *   - PC/NOTEBOOK/SERVIDOR -> SO/CPU/RAM/Disco/Monitor/PerifÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©ricos + (opc) antivirus/office/serial + Red avanzada
 *   - IMPRESORA -> IP/MAC/IP fija/Modelo/UbicaciÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³n
 *   - SWITCH/ROUTER/MODEM/AP -> IP/MAC/IP fija/UbicaciÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³n/Modelo/Observaciones + Red avanzada
 * - ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œSin rellenar campos que no aplicanÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â: al guardar, se limpian campos no aplicables => NULL
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
function norm_dni_inv(string $dni): string { return preg_replace('/\D+/', '', $dni) ?? ''; }
function table_exists_inv(PDO $pdo, string $table): bool {
  try {
    $st = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
    $st->execute([$table]);
    return (int)$st->fetchColumn() > 0;
  } catch (Throwable $ex) { return false; }
}
function column_exists_inv(PDO $pdo, string $table, string $column): bool {
  try {
    $st = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
    $st->execute([$table, $column]);
    return (int)$st->fetchColumn() > 0;
  } catch (Throwable $ex) { return false; }
}
function excel_text_inv($v): string {
  $s = trim((string)($v ?? ''));
  if ($s === '' || strtoupper($s) === 'NAN') return '';
  return $s;
}
function excel_pc_code_inv(string $equipo): string {
  if (preg_match('/U2285-(CE|CP|SE|RA|SW|EN|TI|AP|IM|PT)-\d{3}/i', $equipo, $m)) return strtoupper($m[1]);
  return '';
}
function excel_device_type_inv(string $tipoPc, string $equipo): string {
  $code = excel_pc_code_inv($equipo);
  $t = mb_strtoupper(trim($tipoPc), 'UTF-8');
  if ($code === 'CP' || str_contains($t, 'NOTEBOOK') || str_contains($t, 'PORTATIL') || str_contains($t, 'PORTÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚ÂTIL')) return 'NOTEBOOK';
  if ($code === 'CE' || str_contains($t, 'ESCRITORIO') || str_contains($t, 'ALL-IN-ONE') || str_contains($t, 'SENTEY')) return 'PC';
  return 'OTRO';
}
function parse_gb_inv(string $raw): ?float {
  if ($raw === '') return null;
  if (!preg_match('/(\d+(?:[,.]\d+)?)/', $raw, $m)) return null;
  return (float)str_replace(',', '.', $m[1]);
}
function parse_disk_type_inv(string $raw): ?string {
  $u = mb_strtoupper($raw, 'UTF-8');
  if (str_contains($u, 'NVME')) return 'NVME';
  if (str_contains($u, 'SSD')) return 'SSD';
  if (str_contains($u, 'HDD')) return 'HDD';
  if (str_contains($u, 'EMMC')) return 'EMMC';
  return $raw !== '' ? 'OTRO' : null;
}
function normalize_destino_interno_inv(string $nombre): string {
  $nombre = trim(preg_replace('/\s+/', ' ', $nombre) ?? $nombre);
  $key = mb_strtoupper(str_replace('.', '', $nombre), 'UTF-8');
  $key = preg_replace('/\s+/', ' ', $key) ?? $key;
  return in_array($key, ['BDA MIL', 'BDA MILITAR', 'BANDA MIL', 'BANDA MILITAR'], true) ? 'BANDA MILITAR' : $nombre;
}
function ensure_destino_interno_inv(PDO $pdo, string $nombre): ?int {
  $nombre = normalize_destino_interno_inv($nombre);
  if ($nombre === '') return null;
  $st = $pdo->prepare("SELECT id FROM destino_interno WHERE UPPER(nombre)=UPPER(?) AND COALESCE(estado,'ACTIVO')='ACTIVO' LIMIT 1");
  $st->execute([$nombre]);
  $id = $st->fetchColumn();
  if ($id) return (int)$id;
  $st = $pdo->prepare("INSERT INTO destino_interno (nombre, estado) VALUES (?, 'ACTIVO')");
  $st->execute([$nombre]);
  return (int)$pdo->lastInsertId();
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
$ESCUDO     = $ASSETS_URL . '/img/ecmilm.png'; // ajustÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡ si corresponde
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

try {
  if (!column_exists_inv($pdo, 'personal_unidad', 'dependencia_informatica')) {
    $pdo->exec("ALTER TABLE personal_unidad ADD COLUMN dependencia_informatica VARCHAR(120) NULL AFTER fracc");
  }
} catch (Throwable $ex) {}

try {
  $pdo->exec("
    UPDATE personal_unidad pu
    JOIN (
      SELECT asignado_personal_id, unidad_id, MAX(NULLIF(sector_red,'')) AS dep
      FROM it_activos
      WHERE asignado_personal_id IS NOT NULL AND sector_red IS NOT NULL AND sector_red <> ''
      GROUP BY asignado_personal_id, unidad_id
    ) a ON a.asignado_personal_id = pu.id AND a.unidad_id = pu.unidad_id
    SET pu.dependencia_informatica = a.dep
    WHERE pu.dependencia_informatica IS NULL OR pu.dependencia_informatica = ''
  ");
} catch (Throwable $ex) {}

try {
  if (!column_exists_inv($pdo, 'it_activos', 'propiedad')) {
    $pdo->exec("ALTER TABLE it_activos ADD COLUMN propiedad VARCHAR(20) NOT NULL DEFAULT 'unidad' AFTER categoria");
  }
  if (!column_exists_inv($pdo, 'it_activos', 'propietario_nombre')) {
    $pdo->exec("ALTER TABLE it_activos ADD COLUMN propietario_nombre VARCHAR(160) NULL AFTER propiedad");
  }
  if (!column_exists_inv($pdo, 'it_activos', 'propietario_dni')) {
    $pdo->exec("ALTER TABLE it_activos ADD COLUMN propietario_dni VARCHAR(20) NULL AFTER propietario_nombre");
  }
  if (!column_exists_inv($pdo, 'it_activos', 'autorizacion_estado')) {
    $pdo->exec("ALTER TABLE it_activos ADD COLUMN autorizacion_estado VARCHAR(30) NOT NULL DEFAULT 'pendiente' AFTER propietario_dni");
  }
  if (!column_exists_inv($pdo, 'it_activos', 'autorizacion_fecha')) {
    $pdo->exec("ALTER TABLE it_activos ADD COLUMN autorizacion_fecha DATE NULL AFTER autorizacion_estado");
  }
  if (!column_exists_inv($pdo, 'it_activos', 'autorizado_por')) {
    $pdo->exec("ALTER TABLE it_activos ADD COLUMN autorizado_por VARCHAR(160) NULL AFTER autorizacion_fecha");
  }
  if (!column_exists_inv($pdo, 'it_activos', 'autorizacion_observaciones')) {
    $pdo->exec("ALTER TABLE it_activos ADD COLUMN autorizacion_observaciones TEXT NULL AFTER autorizado_por");
  }
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

function default_edificio_id(PDO $pdo, int $unidad_id): int {
  try {
    $st = $pdo->prepare("SELECT id FROM red_edificios WHERE (unidad_id=? OR unidad_id IS NULL) ORDER BY id LIMIT 1");
    $st->execute([$unidad_id]);
    return (int)($st->fetchColumn() ?: 0);
  } catch (Throwable $ex) {
    return 0;
  }
}

/* ============ Helpers: ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡reas / personal (tolerantes) ============ */
function get_areas(PDO $pdo, int $unidad_id): array {
  if (table_exists_inv($pdo, 'destino_interno')) {
    try {
      $st = $pdo->query("SELECT id, nombre FROM destino_interno WHERE COALESCE(estado,'ACTIVO')='ACTIVO' ORDER BY nombre");
      $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
      return array_map(fn($r)=>['id'=>(int)$r['id'],'nombre'=>(string)$r['nombre']], $rows);
    } catch (Throwable $ex) {}
  }
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
    "SELECT id, dni, apellido, nombre, apellido_nombre, grado, destino_interno FROM personal_unidad WHERE unidad_id=? ORDER BY CASE jerarquia WHEN 'OFICIAL' THEN 1 WHEN 'SUBOFICIAL' THEN 2 WHEN 'SOLDADO' THEN 3 WHEN 'AGENTE_CIVIL' THEN 4 ELSE 5 END, CASE grado WHEN 'TG' THEN 9 WHEN 'GD' THEN 10 WHEN 'GB' THEN 11 WHEN 'CR' THEN 12 WHEN 'TC' THEN 13 WHEN 'MY' THEN 14 WHEN 'CT' THEN 15 WHEN 'TP' THEN 16 WHEN 'TT' THEN 17 WHEN 'ST' THEN 18 WHEN 'ST EC' THEN 19 WHEN 'SM' THEN 20 WHEN 'SP' THEN 21 WHEN 'SA' THEN 22 WHEN 'SI' THEN 23 WHEN 'SG' THEN 24 WHEN 'CI' THEN 25 WHEN 'CI EC' THEN 26 WHEN 'CI Art 11' THEN 27 WHEN 'CB' THEN 28 WHEN 'CB EC' THEN 29 WHEN 'CB Art 11' THEN 30 WHEN 'VP' THEN 31 WHEN 'VS' THEN 32 WHEN 'VS EC' THEN 33 WHEN 'SV' THEN 34 WHEN 'AC' THEN 35 ELSE 99 END, apellido_nombre, apellido, nombre",
    "SELECT id, dni, apellido, nombre, apellido_nombre, grado, destino_interno FROM personal_unidad WHERE unidad_id=? ORDER BY apellido_nombre, apellido, nombre",
    "SELECT id, dni, apellido, nombre, apellido_nombre, grado FROM personal_unidad WHERE unidad_id=? ORDER BY CASE jerarquia WHEN 'OFICIAL' THEN 1 WHEN 'SUBOFICIAL' THEN 2 WHEN 'SOLDADO' THEN 3 WHEN 'AGENTE_CIVIL' THEN 4 ELSE 5 END, apellido_nombre, apellido, nombre",
    "SELECT id, dni, apellido, nombre, apellido_nombre, grado FROM personal_unidad WHERE unidad_id=? ORDER BY apellido_nombre, apellido, nombre",
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
        $apellido = (string)($r['apellido'] ?? '');
        $nombre = (string)($r['nombre'] ?? '');
        $apnom = trim((string)($r['apellido_nombre'] ?? ''));
        if ($apnom !== '' && $apellido === '' && $nombre === '') $apellido = $apnom;
        $out[] = [
          'id'      => (int)($r['id'] ?? 0),
          'dni'     => (string)($r['dni'] ?? ''),
          'apellido'=> $apellido,
          'nombre'  => $nombre,
          'grado'   => (string)($r['grado'] ?? ''),
          'destino_interno' => (int)($r['destino_interno'] ?? 0),
          'label'   => trim(((string)($r['grado'] ?? '')).' '.$apellido.($nombre !== '' ? ', '.$nombre : '').' (DNI '.((string)($r['dni'] ?? '')).')')
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
  if (!empty($filters['pc_code'])) {
    $w[] = "UPPER(COALESCE(a.equipo_nombre,'')) LIKE :pc";
    $p[':pc'] = '%-'.strtoupper((string)$filters['pc_code']).'-%';
  }
  if (!empty($filters['dependencia'])) {
    $w[] = "UPPER(COALESCE(a.sector_red,'')) = UPPER(:dep)";
    $p[':dep'] = (string)$filters['dependencia'];
  }

  $where = implode(' AND ', $w);
  $hasAreas = table_exists_inv($pdo, 'areas');
  $areaSelect = $hasAreas ? "COALESCE(di.nombre, ar.nombre, '') AS area_nombre" : "COALESCE(di.nombre, '') AS area_nombre";
  $areaJoin = $hasAreas ? "LEFT JOIN areas ar ON ar.id = a.area_id" : "";

  $sql = "
    SELECT
      a.*,
      e.nombre AS edificio_nombre,
      $areaSelect,
      COALESCE(NULLIF(a.sector_red,''), '') AS dependencia_nombre,
      CONCAT_WS(' ', COALESCE(pu.grado,''), COALESCE(NULLIF(pu.apellido_nombre,''), CONCAT_WS(', ', NULLIF(pu.apellido,''), NULLIF(pu.nombre,'')))) AS asignado_label,
      pu.dni AS asignado_dni,
      pu.usuario_intranet AS asignado_usuario_intranet,
      pu.usuario_gde AS asignado_usuario_gde
    FROM it_activos a
    LEFT JOIN red_edificios e ON e.id = a.edificio_id
    LEFT JOIN destino_interno di ON di.id = a.area_id
    $areaJoin
    LEFT JOIN personal_unidad pu ON pu.id = a.asignado_personal_id
    WHERE $where
    ORDER BY
      CASE
        WHEN UPPER(COALESCE(a.equipo_nombre,'')) REGEXP '^U2285-CE-[0-9]{3}$' THEN 1
        WHEN UPPER(COALESCE(a.equipo_nombre,'')) REGEXP '^U2285-CP-[0-9]{3}$' THEN 2
        ELSE 3
      END,
      CAST(SUBSTRING_INDEX(COALESCE(a.equipo_nombre,''), '-', -1) AS UNSIGNED),
      a.equipo_nombre,
      a.id DESC
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

function personal_inventario_rows(PDO $pdo, int $unidad_id, array $filters = []): array {
  $defaultEdificio = default_edificio_id($pdo, $unidad_id);
  $w = ["pu.unidad_id = :u"];
  $p = [':u'=>$unidad_id];
  $asignacion = (string)($filters['asignacion'] ?? 'todos');

  if (!empty($filters['edificio_id'])) { $w[] = "a.edificio_id = :e"; $p[':e'] = (int)$filters['edificio_id']; }
  if (!empty($filters['area_id'])) {
    $areaFilterId = (int)$filters['area_id'];
    $w[] = "(
      pu.destino_interno = :ar_personal
      OR a.area_id = :ar_activo
      OR UPPER(COALESCE(pu.dependencia_informatica,'')) = (
        SELECT UPPER(nombre) FROM destino_interno WHERE id = :ar_nombre LIMIT 1
      )
    )";
    $p[':ar_personal'] = $areaFilterId;
    $p[':ar_activo'] = $areaFilterId;
    $p[':ar_nombre'] = $areaFilterId;
  }
  if (!empty($filters['personal_id'])) { $w[] = "pu.id = :pp"; $p[':pp'] = (int)$filters['personal_id']; }
  if (!empty($filters['pc_code'])) {
    $w[] = "a.id IS NOT NULL AND UPPER(COALESCE(a.equipo_nombre,'')) LIKE :pc";
    $p[':pc'] = '%-'.strtoupper((string)$filters['pc_code']).'-%';
  }
  if (!empty($filters['dependencia'])) {
    $w[] = "UPPER(COALESCE(NULLIF(a.sector_red,''), pu.dependencia_informatica, '')) = UPPER(:dep)";
    $p[':dep'] = (string)$filters['dependencia'];
  }
  if (!empty($filters['personal_grupo'])) {
    $grupo = (string)$filters['personal_grupo'];
    if ($grupo === 'oficiales') {
      $w[] = "(pu.jerarquia = 'OFICIAL' OR UPPER(TRIM(COALESCE(pu.grado,''))) IN ('ST','TT','TP','CT','MY','TC','CR','GB','GD','TG'))";
    } elseif ($grupo === 'suboficiales') {
      $w[] = "(pu.jerarquia = 'SUBOFICIAL' OR UPPER(TRIM(COALESCE(pu.grado,''))) IN ('SM','SP','SA','SI','SG','CI','CI EC','CI ART 11','CB','CB EC','CB ART 11'))";
    } elseif ($grupo === 'soldados') {
      $w[] = "(pu.jerarquia = 'SOLDADO' OR UPPER(TRIM(COALESCE(pu.grado,''))) IN ('VP','VS','VS EC','SV','SLD','SOLD','SOLDADO'))";
    } elseif ($grupo === 'civiles') {
      $w[] = "(pu.jerarquia = 'AGENTE_CIVIL' OR UPPER(TRIM(COALESCE(pu.grado,''))) = 'AC')";
    }
  }
  if ($asignacion === 'con_pc') {
    $w[] = "a.id IS NOT NULL";
  } elseif ($asignacion === 'sin_pc') {
    $w[] = "a.id IS NULL";
  }

  $where = implode(' AND ', $w);
  $defaultEdificioSql = (int)$defaultEdificio;

  $sql = "
    SELECT
      COALESCE(a.id, 0) AS id,
      pu.unidad_id AS unidad_id,
      COALESCE(a.categoria, 'informatica') AS categoria,
      COALESCE(a.tipo, 'pc') AS tipo,
      a.etiqueta,
      a.descripcion,
      a.marca,
      a.modelo,
      a.nro_serie,
      COALESCE(a.estado, 'operativo') AS estado,
      COALESCE(a.condicion, 'activo') AS condicion,
      COALESCE(a.edificio_id, $defaultEdificioSql) AS edificio_id,
      COALESCE(a.area_id, pu.destino_interno, 0) AS area_id,
      COALESCE(a.ubicacion_detalle, dip.nombre, '') AS ubicacion_detalle,
      pu.id AS asignado_personal_id,
      a.fecha_alta,
      a.observaciones,
      a.dispositivo_tipo,
      a.equipo_nombre,
      a.usuario_asignado,
      a.sistema_operativo,
      a.cpu,
      a.ram_gb,
      a.disco_tipo,
      a.disco_gb,
      a.monitor,
      a.perifericos,
      a.mac,
      a.ip,
      COALESCE(a.ip_fija, 0) AS ip_fija,
      a.antivirus,
      a.office_version,
      a.serial_windows,
      a.ip_gateway,
      a.dns1,
      a.dns2,
      a.switch_puerto,
      a.patchera_puerto,
      COALESCE(NULLIF(a.sector_red,''), pu.dependencia_informatica) AS sector_red,
      a.vlan,
      COALESCE(a.propiedad, 'unidad') AS propiedad,
      a.propietario_nombre,
      a.propietario_dni,
      COALESCE(a.autorizacion_estado, 'pendiente') AS autorizacion_estado,
      a.autorizacion_fecha,
      a.autorizado_por,
      a.autorizacion_observaciones,
      COALESCE(e.nombre, '') AS edificio_nombre,
      COALESCE(di.nombre, dip.nombre, '') AS area_nombre,
      COALESCE(NULLIF(a.sector_red,''), pu.dependencia_informatica, '') AS dependencia_nombre,
      CONCAT_WS(' ', COALESCE(pu.grado,''), COALESCE(NULLIF(pu.apellido_nombre,''), CONCAT_WS(', ', NULLIF(pu.apellido,''), NULLIF(pu.nombre,'')))) AS asignado_label,
      pu.jerarquia AS asignado_jerarquia,
      pu.grado AS asignado_grado,
      pu.dni AS asignado_dni,
      pu.usuario_intranet AS asignado_usuario_intranet,
      pu.usuario_gde AS asignado_usuario_gde,
      CASE WHEN a.id IS NULL THEN 1 ELSE 0 END AS sin_pc
    FROM personal_unidad pu
    LEFT JOIN it_activos a
      ON a.asignado_personal_id = pu.id
      AND a.unidad_id = pu.unidad_id
      AND (
        a.tipo IN ('pc','otro')
        OR UPPER(COALESCE(a.dispositivo_tipo,'')) IN ('PC','NOTEBOOK','SERVIDOR','RACK','SWITCH','ROUTER','TELEFONO IP','AP','IMPRESORA','PUESTO')
        OR UPPER(COALESCE(a.equipo_nombre,'')) LIKE 'U2285-%'
      )
    LEFT JOIN red_edificios e ON e.id = a.edificio_id
    LEFT JOIN destino_interno di ON di.id = a.area_id
    LEFT JOIN destino_interno dip ON dip.id = pu.destino_interno
    WHERE $where
    ORDER BY
      CASE
        WHEN a.id IS NULL THEN 4
        WHEN UPPER(COALESCE(a.equipo_nombre,'')) REGEXP '^U2285-CE-[0-9]{3}$' THEN 1
        WHEN UPPER(COALESCE(a.equipo_nombre,'')) REGEXP '^U2285-CP-[0-9]{3}$' THEN 2
        WHEN UPPER(COALESCE(a.equipo_nombre,'')) REGEXP '^U2285-SE-[0-9]{3}$' THEN 3
        WHEN UPPER(COALESCE(a.equipo_nombre,'')) REGEXP '^U2285-RA-[0-9]{3}$' THEN 4
        WHEN UPPER(COALESCE(a.equipo_nombre,'')) REGEXP '^U2285-SW-[0-9]{3}$' THEN 5
        WHEN UPPER(COALESCE(a.equipo_nombre,'')) REGEXP '^U2285-EN-[0-9]{3}$' THEN 6
        WHEN UPPER(COALESCE(a.equipo_nombre,'')) REGEXP '^U2285-TI-[0-9]{3}$' THEN 7
        WHEN UPPER(COALESCE(a.equipo_nombre,'')) REGEXP '^U2285-AP-[0-9]{3}$' THEN 8
        WHEN UPPER(COALESCE(a.equipo_nombre,'')) REGEXP '^U2285-IM-[0-9]{3}$' THEN 9
        WHEN UPPER(COALESCE(a.equipo_nombre,'')) REGEXP '^U2285-PT-[0-9]{3}$' THEN 10
        ELSE 11
      END,
      CAST(SUBSTRING_INDEX(COALESCE(a.equipo_nombre,''), '-', -1) AS UNSIGNED),
      a.equipo_nombre,
      CASE pu.jerarquia
        WHEN 'OFICIAL' THEN 1
        WHEN 'SUBOFICIAL' THEN 2
        WHEN 'SOLDADO' THEN 3
        WHEN 'AGENTE_CIVIL' THEN 4
        ELSE 5
      END,
      CASE pu.grado
        WHEN 'TG' THEN 9
        WHEN 'GD' THEN 10
        WHEN 'GB' THEN 11
        WHEN 'CR' THEN 12
        WHEN 'TC' THEN 13
        WHEN 'MY' THEN 14
        WHEN 'CT' THEN 15
        WHEN 'TP' THEN 16
        WHEN 'TT' THEN 17
        WHEN 'ST' THEN 18
        WHEN 'ST EC' THEN 19
        WHEN 'SM' THEN 20
        WHEN 'SP' THEN 21
        WHEN 'SA' THEN 22
        WHEN 'SI' THEN 23
        WHEN 'SG' THEN 24
        WHEN 'CI' THEN 25
        WHEN 'CI EC' THEN 26
        WHEN 'CI Art 11' THEN 27
        WHEN 'CB' THEN 28
        WHEN 'CB EC' THEN 29
        WHEN 'CB Art 11' THEN 30
        WHEN 'VP' THEN 31
        WHEN 'VS' THEN 32
        WHEN 'VS EC' THEN 33
        WHEN 'SV' THEN 34
        WHEN 'AC' THEN 35
        ELSE 99
      END,
      pu.apellido_nombre,
      pu.apellido,
      pu.nombre
  ";

  try {
    $st = $pdo->prepare($sql);
    $st->execute($p);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if ($asignacion !== 'sin_pc' && empty($filters['personal_id']) && empty($filters['personal_grupo'])) {
      $wu = ["a.unidad_id = :uu", "(a.asignado_personal_id IS NULL OR pu2.id IS NULL)"];
      $pu = [':uu'=>$unidad_id];
      if (!empty($filters['edificio_id'])) { $wu[] = "a.edificio_id = :ue"; $pu[':ue'] = (int)$filters['edificio_id']; }
      if (!empty($filters['area_id']))     { $wu[] = "a.area_id = :uar"; $pu[':uar'] = (int)$filters['area_id']; }
      if (!empty($filters['pc_code'])) {
        $wu[] = "UPPER(COALESCE(a.equipo_nombre,'')) LIKE :upc";
        $pu[':upc'] = '%-'.strtoupper((string)$filters['pc_code']).'-%';
      }
      if (!empty($filters['dependencia'])) {
        $wu[] = "UPPER(COALESCE(a.sector_red,'')) = UPPER(:udep)";
        $pu[':udep'] = (string)$filters['dependencia'];
      }
      $whereUnassigned = implode(' AND ', $wu);
      $sqlUnassigned = "
        SELECT
          a.*,
          e.nombre AS edificio_nombre,
          COALESCE(di.nombre, '') AS area_nombre,
          COALESCE(NULLIF(a.sector_red,''), '') AS dependencia_nombre,
          '' AS asignado_label,
          '' AS asignado_jerarquia,
          '' AS asignado_grado,
          '' AS asignado_dni,
          '' AS asignado_usuario_intranet,
          '' AS asignado_usuario_gde,
          0 AS sin_pc
        FROM it_activos a
        LEFT JOIN personal_unidad pu2 ON pu2.id = a.asignado_personal_id AND pu2.unidad_id = a.unidad_id
        LEFT JOIN red_edificios e ON e.id = a.edificio_id
        LEFT JOIN destino_interno di ON di.id = a.area_id
        WHERE $whereUnassigned
      ";
      $stU = $pdo->prepare($sqlUnassigned);
      $stU->execute($pu);
      $rows = array_merge($rows, $stU->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    return $rows;
  } catch (Throwable $ex) {
    return activos_with_joins($pdo, $unidad_id, $filters);
  }
}

function inventario_resumen_areas(PDO $pdo, int $unidad_id): array {
  $sql = "
    SELECT
      COALESCE(a.area_id, 0) AS area_id,
      COALESCE(di.nombre, 'Sin area') AS area_nombre,
      SUM(CASE WHEN UPPER(COALESCE(a.equipo_nombre,'')) REGEXP '^U2285-CE-' OR UPPER(COALESCE(a.dispositivo_tipo,'')) = 'PC' THEN 1 ELSE 0 END) AS ce,
      SUM(CASE WHEN UPPER(COALESCE(a.equipo_nombre,'')) REGEXP '^U2285-CP-' OR UPPER(COALESCE(a.dispositivo_tipo,'')) = 'NOTEBOOK' THEN 1 ELSE 0 END) AS cp,
      SUM(CASE WHEN UPPER(COALESCE(a.equipo_nombre,'')) REGEXP '^U2285-SE-' OR UPPER(COALESCE(a.dispositivo_tipo,'')) = 'SERVIDOR' THEN 1 ELSE 0 END) AS se,
      SUM(CASE WHEN UPPER(COALESCE(a.equipo_nombre,'')) REGEXP '^U2285-RA-' OR UPPER(COALESCE(a.dispositivo_tipo,'')) = 'RACK' THEN 1 ELSE 0 END) AS ra,
      SUM(CASE WHEN UPPER(COALESCE(a.equipo_nombre,'')) REGEXP '^U2285-SW-' OR UPPER(COALESCE(a.dispositivo_tipo,'')) = 'SWITCH' THEN 1 ELSE 0 END) AS sw,
      SUM(CASE WHEN UPPER(COALESCE(a.equipo_nombre,'')) REGEXP '^U2285-EN-' OR UPPER(COALESCE(a.dispositivo_tipo,'')) = 'ROUTER' THEN 1 ELSE 0 END) AS en,
      SUM(CASE WHEN UPPER(COALESCE(a.equipo_nombre,'')) REGEXP '^U2285-TI-' OR UPPER(COALESCE(a.dispositivo_tipo,'')) = 'TELEFONO IP' THEN 1 ELSE 0 END) AS ti,
      SUM(CASE WHEN UPPER(COALESCE(a.equipo_nombre,'')) REGEXP '^U2285-AP-' OR UPPER(COALESCE(a.dispositivo_tipo,'')) = 'AP' THEN 1 ELSE 0 END) AS ap,
      SUM(CASE WHEN UPPER(COALESCE(a.equipo_nombre,'')) REGEXP '^U2285-IM-' OR UPPER(COALESCE(a.dispositivo_tipo,'')) = 'IMPRESORA' THEN 1 ELSE 0 END) AS im,
      SUM(CASE WHEN UPPER(COALESCE(a.equipo_nombre,'')) REGEXP '^U2285-PT-' OR UPPER(COALESCE(a.dispositivo_tipo,'')) = 'PUESTO' THEN 1 ELSE 0 END) AS pt,
      SUM(CASE
        WHEN COALESCE(a.propiedad,'unidad') = 'personal'
        THEN 1 ELSE 0 END) AS personales,
      COUNT(*) AS total_dispositivos,
      SUM(CASE WHEN COALESCE(a.propiedad,'unidad') = 'personal' AND COALESCE(a.autorizacion_estado,'pendiente') = 'autorizado' THEN 1 ELSE 0 END) AS autorizados,
      SUM(CASE WHEN COALESCE(a.propiedad,'unidad') = 'personal' AND COALESCE(a.autorizacion_estado,'pendiente') <> 'autorizado' THEN 1 ELSE 0 END) AS pendientes
    FROM it_activos a
    LEFT JOIN destino_interno di ON di.id = a.area_id
    WHERE a.unidad_id = ?
    GROUP BY COALESCE(a.area_id, 0), COALESCE(di.nombre, 'Sin area')
    ORDER BY area_nombre
  ";
  $st = $pdo->prepare($sql);
  $st->execute([$unidad_id]);
  return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$mensajeImport = '';
$mensajeImportError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_import_excel'])) {
  try {
    $autoload = $ROOT . '/vendor/autoload.php';
    if (!is_file($autoload)) throw new RuntimeException('Falta vendor/autoload.php para leer Excel.');
    require_once $autoload;
    if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
      throw new RuntimeException('No esta disponible PhpSpreadsheet.');
    }
    if (!isset($_FILES['excel_archivo']) || ($_FILES['excel_archivo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
      throw new RuntimeException('Selecciona el archivo Excel de inventario.');
    }
    $file = $_FILES['excel_archivo'];
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) throw new RuntimeException('Error al subir el archivo.');
    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['xls','xlsx'], true)) throw new RuntimeException('El archivo debe ser .xls o .xlsx.');

    $stEd = $pdo->prepare("SELECT id FROM red_edificios WHERE (unidad_id=? OR unidad_id IS NULL) ORDER BY id LIMIT 1");
    $stEd->execute([$UNIDAD_ID]);
    $defaultEdificio = (int)($stEd->fetchColumn() ?: 0);
    if ($defaultEdificio <= 0) throw new RuntimeException('No hay edificio disponible para asociar los activos.');

    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name']);
    $sheet = $spreadsheet->getActiveSheet();
    $highestRow = (int)$sheet->getHighestRow();
    $headerRow = 6;

    $pdo->beginTransaction();
    $equiposProcesados = 0;
    $equiposCreados = 0;
    $equiposActualizados = 0;
    $personasCreadas = 0;
    $personasActualizadas = 0;
    $filasOmitidas = 0;

    for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
      $grado = excel_text_inv($sheet->getCell("B{$row}")->getValue());
      $fraccion = excel_text_inv($sheet->getCell("C{$row}")->getValue());
      $arma = excel_text_inv($sheet->getCell("D{$row}")->getValue());
      $apellido = excel_text_inv($sheet->getCell("E{$row}")->getValue());
      $nombre = excel_text_inv($sheet->getCell("F{$row}")->getValue());
      $dni = norm_dni_inv(excel_text_inv($sheet->getCell("G{$row}")->getValue()));
      $usuarioIntranet = excel_text_inv($sheet->getCell("H{$row}")->getValue());
      $usuarioGde = excel_text_inv($sheet->getCell("I{$row}")->getValue());
      $dependencia = excel_text_inv($sheet->getCell("J{$row}")->getValue());
      $subdependencia = excel_text_inv($sheet->getCell("K{$row}")->getValue());
      $tipoPc = excel_text_inv($sheet->getCell("L{$row}")->getValue());
      $nombrePc = strtoupper(excel_text_inv($sheet->getCell("M{$row}")->getValue()));
      $so = excel_text_inv($sheet->getCell("N{$row}")->getValue());
      $procesador = excel_text_inv($sheet->getCell("O{$row}")->getValue());
      $ramRaw = excel_text_inv($sheet->getCell("P{$row}")->getValue());
      $discoRaw = excel_text_inv($sheet->getCell("Q{$row}")->getValue());
      $ip = excel_text_inv($sheet->getCell("R{$row}")->getValue());
      $mac = excel_text_inv($sheet->getCell("S{$row}")->getValue());
      $monitor = excel_text_inv($sheet->getCell("T{$row}")->getValue());
      $perifericos = excel_text_inv($sheet->getCell("U{$row}")->getValue());
      $observaciones = excel_text_inv($sheet->getCell("V{$row}")->getValue());

      if (strtoupper($dni) === 'DNI' || strtoupper($nombrePc) === 'NOMBRE PC') { $filasOmitidas++; continue; }
      if ($dni === '' && $apellido === '' && $nombre === '' && $nombrePc === '') { continue; }

      $personalId = null;
      if ($dni !== '') {
        $stP = $pdo->prepare("SELECT id FROM personal_unidad WHERE unidad_id=? AND REPLACE(REPLACE(REPLACE(dni,'.',''),'-',''),' ','')=? LIMIT 1");
        $stP->execute([$UNIDAD_ID, $dni]);
        $personalId = $stP->fetchColumn();
        $apellidoNombre = trim($apellido . ($nombre !== '' ? ', ' . $nombre : ''));
        if ($personalId) {
          $set = [
            'grado=?', 'arma=?', 'apellido=?', 'nombre=?', 'apellido_nombre=?',
            'fracc=?', 'usuario_intranet=?', 'usuario_gde=?', 'updated_at=NOW()'
          ];
          $params = [
            ($grado !== '' ? $grado : null),
            ($arma !== '' ? $arma : null),
            ($apellido !== '' ? $apellido : null),
            ($nombre !== '' ? $nombre : null),
            ($apellidoNombre !== '' ? $apellidoNombre : null),
            ($fraccion !== '' ? $fraccion : null),
            ($usuarioIntranet !== '' ? $usuarioIntranet : null),
            ($usuarioGde !== '' ? $usuarioGde : null),
            (int)$personalId,
            $UNIDAD_ID
          ];
          $stUpd = $pdo->prepare("UPDATE personal_unidad SET ".implode(',', $set)." WHERE id=? AND unidad_id=?");
          $stUpd->execute($params);
          $personasActualizadas++;
        } else {
          $stIns = $pdo->prepare("
            INSERT INTO personal_unidad
              (unidad_id, dni, grado, arma, apellido, nombre, apellido_nombre, fracc, usuario_intranet, usuario_gde, role_id, updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,3,NOW())
          ");
          $stIns->execute([
            $UNIDAD_ID,
            $dni,
            ($grado !== '' ? $grado : null),
            ($arma !== '' ? $arma : null),
            ($apellido !== '' ? $apellido : null),
            ($nombre !== '' ? $nombre : null),
            ($apellidoNombre !== '' ? $apellidoNombre : null),
            ($fraccion !== '' ? $fraccion : null),
            ($usuarioIntranet !== '' ? $usuarioIntranet : null),
            ($usuarioGde !== '' ? $usuarioGde : null),
          ]);
          $personalId = (int)$pdo->lastInsertId();
          $personasCreadas++;
        }
      }

      $subdepId = null;
      if ($subdependencia !== '' || $dependencia !== '') {
        $subdepId = ensure_destino_interno_inv($pdo, $subdependencia !== '' ? $subdependencia : $dependencia);
        if ($dependencia !== '') ensure_destino_interno_inv($pdo, $dependencia);
      }
      if ($personalId) {
        $stLoc = $pdo->prepare("
          UPDATE personal_unidad
          SET dependencia_informatica=?, destino_interno=COALESCE(?, destino_interno), updated_at=NOW()
          WHERE id=? AND unidad_id=?
        ");
        $stLoc->execute([
          ($dependencia !== '' ? $dependencia : null),
          ($subdepId ? (int)$subdepId : null),
          (int)$personalId,
          $UNIDAD_ID
        ]);
      }

      if ($nombrePc === '') { $filasOmitidas++; continue; }
      if (!preg_match('/^U2285-(CE|CP)-\d{3}$/i', $nombrePc)) { $filasOmitidas++; continue; }

      $device = excel_device_type_inv($tipoPc, $nombrePc);
      $descripcion = ($device === 'NOTEBOOK' ? 'Computadora portatil ' : 'Computadora de escritorio ') . $nombrePc;
      $ubicacion = trim($dependencia . ($subdependencia !== '' ? ' / ' . $subdependencia : ''));
      $ramGb = parse_gb_inv($ramRaw);
      $diskType = parse_disk_type_inv($discoRaw);
      $diskGb = parse_gb_inv($discoRaw);
      $diskGbInt = $diskGb !== null ? (int)$diskGb : null;
      $usuariosActivos = [];
      if ($usuarioIntranet !== '') $usuariosActivos[] = 'Intranet: ' . $usuarioIntranet;
      if ($usuarioGde !== '') $usuariosActivos[] = 'GDE: ' . $usuarioGde;
      $usuarioAsignado = trim(implode(' / ', $usuariosActivos) . (($apellido !== '' || $nombre !== '') ? ' - '.trim($apellido.' '.$nombre) : ''));

      $stA = $pdo->prepare("SELECT id FROM it_activos WHERE unidad_id=? AND UPPER(equipo_nombre)=UPPER(?) LIMIT 1");
      $stA->execute([$UNIDAD_ID, $nombrePc]);
      $activoId = $stA->fetchColumn();

      $params = [
        'informatica',
        'pc',
        $nombrePc,
        $descripcion,
        ($tipoPc !== '' ? $tipoPc : null),
        null,
        null,
        'operativo',
        'activo',
        $defaultEdificio,
        $subdepId,
        ($ubicacion !== '' ? $ubicacion : null),
        ($personalId ? (int)$personalId : null),
        ($observaciones !== '' ? $observaciones : null),
        $device,
        $nombrePc,
        ($usuarioAsignado !== '' ? $usuarioAsignado : null),
        ($so !== '' ? $so : null),
        ($procesador !== '' ? $procesador : null),
        $ramGb,
        $diskType,
        $diskGbInt,
        ($monitor !== '' ? $monitor : null),
        ($perifericos !== '' ? $perifericos : null),
        ($mac !== '' ? $mac : null),
        ($ip !== '' ? $ip : null),
        ($dependencia !== '' ? $dependencia : null),
      ];

      if ($activoId) {
        $stUpdA = $pdo->prepare("
          UPDATE it_activos SET
            categoria=?, tipo=?, etiqueta=?, descripcion=?, marca=?, modelo=?, nro_serie=?,
            estado=?, condicion=?, edificio_id=?, area_id=?, ubicacion_detalle=?,
            asignado_personal_id=?, observaciones=?, dispositivo_tipo=?, equipo_nombre=?,
            usuario_asignado=?, sistema_operativo=?, cpu=?, ram_gb=?, disco_tipo=?, disco_gb=?,
            monitor=?, perifericos=?, mac=?, ip=?, sector_red=?, actualizado_en=NOW()
          WHERE id=? AND unidad_id=?
        ");
        $stUpdA->execute([...$params, (int)$activoId, $UNIDAD_ID]);
        $equiposActualizados++;
      } else {
        $stInsA = $pdo->prepare("
          INSERT INTO it_activos (
            categoria,tipo,etiqueta,descripcion,marca,modelo,nro_serie,estado,condicion,
            edificio_id,area_id,ubicacion_detalle,asignado_personal_id,observaciones,
            dispositivo_tipo,equipo_nombre,usuario_asignado,sistema_operativo,cpu,ram_gb,
            disco_tipo,disco_gb,monitor,perifericos,mac,ip,sector_red,unidad_id,creado_en
          ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())
        ");
        $stInsA->execute([...$params, $UNIDAD_ID]);
        $equiposCreados++;
      }
      $equiposProcesados++;
    }

    $pdo->commit();
    $mensajeImport = "Importacion lista: {$equiposProcesados} equipos procesados ({$equiposCreados} nuevos, {$equiposActualizados} actualizados). Personal: {$personasCreadas} nuevos, {$personasActualizadas} actualizados por DNI. Filas omitidas: {$filasOmitidas}.";
  } catch (Throwable $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $mensajeImportError = $ex->getMessage();
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

    if ($api === 'dependencias') {
      $st = $pdo->prepare("
        SELECT nombre
        FROM (
          SELECT DISTINCT sector_red AS nombre
          FROM it_activos
          WHERE unidad_id=? AND sector_red IS NOT NULL AND sector_red <> ''
          UNION
          SELECT DISTINCT dependencia_informatica AS nombre
          FROM personal_unidad
          WHERE unidad_id=? AND dependencia_informatica IS NOT NULL AND dependencia_informatica <> ''
        ) deps
        ORDER BY nombre
      ");
      $st->execute([$UNIDAD_ID, $UNIDAD_ID]);
      $rows = [];
      foreach (($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r) {
        $rows[] = ['nombre'=>(string)$r['nombre']];
      }
      json_out(['ok'=>true,'rows'=>$rows]);
    }

    if ($api === 'personal') {
      json_out(['ok'=>true,'rows'=>get_personal($pdo, $UNIDAD_ID)]);
    }

    if ($api === 'transfer_options') {
      $st = $pdo->prepare("
        SELECT
          a.id,
          a.equipo_nombre,
          a.asignado_personal_id,
          CONCAT_WS(' ', COALESCE(pu.grado,''), COALESCE(NULLIF(pu.apellido_nombre,''), CONCAT_WS(', ', NULLIF(pu.apellido,''), NULLIF(pu.nombre,'')))) AS asignado_label
        FROM it_activos a
        LEFT JOIN personal_unidad pu ON pu.id = a.asignado_personal_id AND pu.unidad_id = a.unidad_id
        WHERE a.unidad_id=? AND a.equipo_nombre IS NOT NULL AND a.equipo_nombre <> ''
        ORDER BY
          CASE
            WHEN UPPER(a.equipo_nombre) REGEXP '^U2285-CE-[0-9]{3}$' THEN 1
            WHEN UPPER(a.equipo_nombre) REGEXP '^U2285-CP-[0-9]{3}$' THEN 2
            WHEN UPPER(a.equipo_nombre) REGEXP '^U2285-SE-[0-9]{3}$' THEN 3
            WHEN UPPER(a.equipo_nombre) REGEXP '^U2285-RA-[0-9]{3}$' THEN 4
            WHEN UPPER(a.equipo_nombre) REGEXP '^U2285-SW-[0-9]{3}$' THEN 5
            WHEN UPPER(a.equipo_nombre) REGEXP '^U2285-EN-[0-9]{3}$' THEN 6
            WHEN UPPER(a.equipo_nombre) REGEXP '^U2285-TI-[0-9]{3}$' THEN 7
            WHEN UPPER(a.equipo_nombre) REGEXP '^U2285-AP-[0-9]{3}$' THEN 8
            WHEN UPPER(a.equipo_nombre) REGEXP '^U2285-IM-[0-9]{3}$' THEN 9
            WHEN UPPER(a.equipo_nombre) REGEXP '^U2285-PT-[0-9]{3}$' THEN 10
            ELSE 11
          END,
          CAST(SUBSTRING_INDEX(a.equipo_nombre, '-', -1) AS UNSIGNED),
          a.equipo_nombre
      ");
      $st->execute([$UNIDAD_ID]);
      json_out(['ok'=>true,'activos'=>$st->fetchAll(PDO::FETCH_ASSOC) ?: [], 'personal'=>get_personal($pdo, $UNIDAD_ID)]);
    }

    if ($api === 'activos_transfer' && $_SERVER['REQUEST_METHOD']==='POST') {
      $in = json_decode(file_get_contents('php://input'), true) ?: [];
      $activo_id = (int)($in['activo_id'] ?? 0);
      $equipo_nombre = strtoupper(trim((string)($in['equipo_nombre'] ?? '')));
      $destino_personal_id = (int)($in['destino_personal_id'] ?? 0);
      if ($destino_personal_id <= 0) json_out(['ok'=>false,'error'=>'Selecciona el personal destino.'], 400);

      $stP = $pdo->prepare("
        SELECT id, grado, apellido_nombre, dni, usuario_intranet, usuario_gde, destino_interno, dependencia_informatica
        FROM personal_unidad
        WHERE id=? AND unidad_id=?
        LIMIT 1
      ");
      $stP->execute([$destino_personal_id, $UNIDAD_ID]);
      $persona = $stP->fetch(PDO::FETCH_ASSOC);
      if (!$persona) json_out(['ok'=>false,'error'=>'Personal destino no encontrado.'], 404);

      if ($activo_id > 0) {
        $stA = $pdo->prepare("SELECT * FROM it_activos WHERE id=? AND unidad_id=? LIMIT 1");
        $stA->execute([$activo_id, $UNIDAD_ID]);
      } else {
        $stA = $pdo->prepare("SELECT * FROM it_activos WHERE UPPER(equipo_nombre)=? AND unidad_id=? LIMIT 1");
        $stA->execute([$equipo_nombre, $UNIDAD_ID]);
      }
      $activo = $stA->fetch(PDO::FETCH_ASSOC);
      if (!$activo) json_out(['ok'=>false,'error'=>'Activo no encontrado.'], 404);

      $usuarioParts = [];
      if (!empty($persona['usuario_intranet'])) $usuarioParts[] = 'Intranet: '.$persona['usuario_intranet'];
      if (!empty($persona['usuario_gde'])) $usuarioParts[] = 'GDE: '.$persona['usuario_gde'];
      $usuarioAsignado = implode(' / ', $usuarioParts);
      $areaDestino = (int)($persona['destino_interno'] ?? 0);
      $dependenciaDestino = trim((string)($persona['dependencia_informatica'] ?? ''));

      $st = $pdo->prepare("
        UPDATE it_activos
        SET asignado_personal_id=?,
            usuario_asignado=?,
            area_id=COALESCE(NULLIF(?,0), area_id),
            sector_red=COALESCE(NULLIF(?,''), sector_red),
            actualizado_en=NOW()
        WHERE id=? AND unidad_id=?
      ");
      $st->execute([
        $destino_personal_id,
        ($usuarioAsignado !== '' ? $usuarioAsignado : null),
        $areaDestino,
        $dependenciaDestino,
        (int)$activo['id'],
        $UNIDAD_ID
      ]);

      json_out([
        'ok'=>true,
        'activo_id'=>(int)$activo['id'],
        'equipo_nombre'=>(string)($activo['equipo_nombre'] ?? ''),
        'destino_label'=>trim(((string)($persona['grado'] ?? '')).' '.((string)($persona['apellido_nombre'] ?? '')))
      ]);
    }

    if ($api === 'personal_usuarios_save' && $_SERVER['REQUEST_METHOD']==='POST') {
      $in = json_decode(file_get_contents('php://input'), true) ?: [];
      $personal_id = (int)($in['personal_id'] ?? 0);
      if ($personal_id <= 0) json_out(['ok'=>false,'error'=>'personal_id requerido'], 400);

      $usuario_intranet = trim((string)($in['usuario_intranet'] ?? ''));
      $usuario_gde = trim((string)($in['usuario_gde'] ?? ''));

      $st = $pdo->prepare("
        UPDATE personal_unidad
        SET usuario_intranet=?, usuario_gde=?, updated_at=NOW()
        WHERE id=? AND unidad_id=?
      ");
      $st->execute([
        ($usuario_intranet !== '' ? $usuario_intranet : null),
        ($usuario_gde !== '' ? $usuario_gde : null),
        $personal_id,
        $UNIDAD_ID
      ]);
      json_out(['ok'=>true]);
    }

    if ($api === 'personal_ubicacion_save' && $_SERVER['REQUEST_METHOD']==='POST') {
      $in = json_decode(file_get_contents('php://input'), true) ?: [];
      $personal_id = (int)($in['personal_id'] ?? 0);
      if ($personal_id <= 0) json_out(['ok'=>false,'error'=>'personal_id requerido'], 400);

      $area_id = (int)($in['area_id'] ?? 0);
      if ($area_id > 0) {
        try {
          $stChk = $pdo->prepare("SELECT COUNT(*) FROM destino_interno WHERE id=?");
          $stChk->execute([$area_id]);
          if ((int)$stChk->fetchColumn() === 0) $area_id = 0;
        } catch (Throwable $ex) { $area_id = 0; }
      }

      $dependencia = trim((string)($in['dependencia'] ?? ''));

      $st = $pdo->prepare("
        UPDATE personal_unidad
        SET destino_interno=?, dependencia_informatica=?, updated_at=NOW()
        WHERE id=? AND unidad_id=?
      ");
      $st->execute([
        ($area_id > 0 ? $area_id : null),
        ($dependencia !== '' ? $dependencia : null),
        $personal_id,
        $UNIDAD_ID
      ]);

      $stA = $pdo->prepare("
        UPDATE it_activos
        SET area_id=?,
            sector_red=?,
            actualizado_en=NOW()
        WHERE asignado_personal_id=? AND unidad_id=?
      ");
      $stA->execute([
        ($area_id > 0 ? $area_id : null),
        ($dependencia !== '' ? $dependencia : null),
        $personal_id,
        $UNIDAD_ID
      ]);

      json_out(['ok'=>true,'personal_actualizado'=>$st->rowCount(),'activos_actualizados'=>$stA->rowCount()]);
    }

    /* ===== Activos (por edificio) ===== */
    if ($api === 'activos_list') {
      $edificio_id = (int)($_GET['edificio_id'] ?? 0);
      if ($edificio_id<=0) json_out(['ok'=>false,'error'=>'edificio_id requerido'], 400);
      if (!edificio_permitido($pdo, $UNIDAD_ID, $edificio_id)) json_out(['ok'=>false,'error'=>'Edificio no vÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡lido'], 403);

      $rows = activos_with_joins($pdo, $UNIDAD_ID, ['edificio_id'=>$edificio_id]);
      json_out(['ok'=>true,'rows'=>$rows]);
    }

    /* ===== Activos (toda la unidad / filtros) ===== */
    if ($api === 'activos_list_all') {
      $area_id = (int)($_GET['area_id'] ?? 0);
      $personal_id = (int)($_GET['personal_id'] ?? 0);
      $edificio_id = (int)($_GET['edificio_id'] ?? 0);
      $pc_code = strtoupper(trim((string)($_GET['pc_code'] ?? '')));
      $dependencia = trim((string)($_GET['dependencia'] ?? ''));
      $asignacion = trim((string)($_GET['asignacion'] ?? 'todos'));
      $personal_grupo = trim((string)($_GET['personal_grupo'] ?? ''));
      if (!in_array($asignacion, ['todos','con_pc','sin_pc'], true)) $asignacion = 'todos';

      $filters = ['asignacion'=>$asignacion];
      if ($area_id>0) $filters['area_id'] = $area_id;
      if ($personal_id>0) $filters['personal_id'] = $personal_id;
      if (in_array($pc_code, ['CE','CP','SE','RA','SW','EN','TI','AP','IM','PT'], true)) $filters['pc_code'] = $pc_code;
      if (in_array($personal_grupo, ['oficiales','suboficiales','soldados','civiles'], true)) $filters['personal_grupo'] = $personal_grupo;
      if ($dependencia !== '') $filters['dependencia'] = $dependencia;
      if ($edificio_id>0) {
        if (!edificio_permitido($pdo, $UNIDAD_ID, $edificio_id)) json_out(['ok'=>false,'error'=>'Edificio no vÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡lido'], 403);
        $filters['edificio_id'] = $edificio_id;
      }

      $rows = personal_inventario_rows($pdo, $UNIDAD_ID, $filters);
      json_out(['ok'=>true,'rows'=>$rows]);
    }

    if ($api === 'inventario_resumen_areas') {
      json_out(['ok'=>true,'rows'=>inventario_resumen_areas($pdo, $UNIDAD_ID)]);
    }

    /* ===== Guardar Activo (adaptado a tu schema + PRO) ===== */
    if ($api === 'activos_save' && $_SERVER['REQUEST_METHOD']==='POST') {
      $in = json_decode(file_get_contents('php://input'), true) ?: [];
      $id = (int)($in['id'] ?? 0);
      $edificio_id = (int)($in['edificio_id'] ?? 0);

      if ($edificio_id<=0) json_out(['ok'=>false,'error'=>'edificio_id requerido'], 400);
      if (!edificio_permitido($pdo, $UNIDAD_ID, $edificio_id)) json_out(['ok'=>false,'error'=>'Edificio no vÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡lido'], 403);

      // BÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡sico
      $categoria = trim((string)($in['categoria'] ?? 'informatica'));
      $tipo = trim((string)($in['tipo'] ?? 'otro')); // enum tuyo: pc/camara/herramienta/mueble/insumo/otro
      $etiqueta = trim((string)($in['etiqueta'] ?? ''));
      $descripcion = trim((string)($in['descripcion'] ?? ''));

      if ($descripcion === '' && $etiqueta === '') {
        json_out(['ok'=>false,'error'=>'DebÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©s cargar al menos Etiqueta o DescripciÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³n'], 400);
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

      $propiedad = trim((string)($in['propiedad'] ?? 'unidad'));
      if (!in_array($propiedad, ['unidad','personal'], true)) $propiedad = 'unidad';
      $propietario_nombre = trim((string)($in['propietario_nombre'] ?? ''));
      $propietario_dni = norm_dni_inv(trim((string)($in['propietario_dni'] ?? '')));
      $autorizacion_estado = trim((string)($in['autorizacion_estado'] ?? 'pendiente'));
      if (!in_array($autorizacion_estado, ['pendiente','autorizado','rechazado','vencido'], true)) $autorizacion_estado = 'pendiente';
      $autorizacion_fecha = trim((string)($in['autorizacion_fecha'] ?? ''));
      $autorizado_por = trim((string)($in['autorizado_por'] ?? ''));
      $autorizacion_observaciones = trim((string)($in['autorizacion_observaciones'] ?? ''));

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
          if (table_exists_inv($pdo, 'destino_interno')) {
            $stChk = $pdo->prepare("SELECT COUNT(*) FROM destino_interno WHERE id=?");
            $stChk->execute([$area_id]);
          } else {
            $stChk = $pdo->prepare("SELECT COUNT(*) FROM areas WHERE id=? AND (unidad_id=? OR unidad_id IS NULL)");
            $stChk->execute([$area_id, $UNIDAD_ID]);
          }
          if ((int)$stChk->fetchColumn() === 0) $area_id = 0;
        } catch (Throwable $ex) {}
      }

      // fecha_alta null si vacÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â­a
      $fechaAltaVal = null;
      if ($fecha_alta !== '') $fechaAltaVal = $fecha_alta;
      $autorizacionFechaVal = null;
      if ($autorizacion_fecha !== '') $autorizacionFechaVal = $autorizacion_fecha;

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
            propiedad=?,
            propietario_nombre=?,
            propietario_dni=?,
            autorizacion_estado=?,
            autorizacion_fecha=?,
            autorizado_por=?,
            autorizacion_observaciones=?,

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
          $propiedad,
          ($propietario_nombre!==''?$propietario_nombre:null),
          ($propietario_dni!==''?$propietario_dni:null),
          $autorizacion_estado,
          $autorizacionFechaVal,
          ($autorizado_por!==''?$autorizado_por:null),
          ($autorizacion_observaciones!==''?$autorizacion_observaciones:null),

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
            propiedad,
            propietario_nombre,
            propietario_dni,
            autorizacion_estado,
            autorizacion_fecha,
            autorizado_por,
            autorizacion_observaciones,

            creado_en
          ) VALUES (
            ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,
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
          $propiedad,
          ($propietario_nombre!==''?$propietario_nombre:null),
          ($propietario_dni!==''?$propietario_dni:null),
          $autorizacion_estado,
          $autorizacionFechaVal,
          ($autorizado_por!==''?$autorizado_por:null),
          ($autorizacion_observaciones!==''?$autorizacion_observaciones:null),
        ]);
        $id = (int)$pdo->lastInsertId();
      }

      json_out(['ok'=>true,'id'=>$id]);
    }

    if ($api === 'activos_delete' && $_SERVER['REQUEST_METHOD']==='POST') {
      $in = json_decode(file_get_contents('php://input'), true) ?: [];
      $id = (int)($in['id'] ?? 0);
      $edificio_id = (int)($in['edificio_id'] ?? 0);
      if ($id<=0 || $edificio_id<=0) json_out(['ok'=>false,'error'=>'Datos invÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡lidos'], 400);
      if (!edificio_permitido($pdo, $UNIDAD_ID, $edificio_id)) json_out(['ok'=>false,'error'=>'Edificio no vÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡lido'], 403);

      $st = $pdo->prepare("DELETE FROM it_activos WHERE id=? AND unidad_id=? AND edificio_id=?");
      $st->execute([$id, $UNIDAD_ID, $edificio_id]);
      json_out(['ok'=>true]);
    }

    /* ===== Internet ===== */
    if ($api === 'internet_list') {
      $edificio_id = (int)($_GET['edificio_id'] ?? 0);
      if ($edificio_id<=0) json_out(['ok'=>false,'error'=>'edificio_id requerido'], 400);
      if (!edificio_permitido($pdo, $UNIDAD_ID, $edificio_id)) json_out(['ok'=>false,'error'=>'Edificio no vÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡lido'], 403);

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
      if (!edificio_permitido($pdo, $UNIDAD_ID, $edificio_id)) json_out(['ok'=>false,'error'=>'Edificio no vÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡lido'], 403);

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
      if ($id<=0 || $edificio_id<=0) json_out(['ok'=>false,'error'=>'Datos invÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡lidos'], 400);
      if (!edificio_permitido($pdo, $UNIDAD_ID, $edificio_id)) json_out(['ok'=>false,'error'=>'Edificio no vÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡lido'], 403);

      $st = $pdo->prepare("DELETE FROM it_internet WHERE id=? AND unidad_id=? AND edificio_id=?");
      $st->execute([$id, $UNIDAD_ID, $edificio_id]);
      json_out(['ok'=>true]);
    }

    /* ===== Mantenimientos ===== */
    if ($api === 'mant_list') {
      $edificio_id = (int)($_GET['edificio_id'] ?? 0);
      if ($edificio_id<=0) json_out(['ok'=>false,'error'=>'edificio_id requerido'], 400);
      if (!edificio_permitido($pdo, $UNIDAD_ID, $edificio_id)) json_out(['ok'=>false,'error'=>'Edificio no vÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡lido'], 403);

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
      if (!edificio_permitido($pdo, $UNIDAD_ID, $edificio_id)) json_out(['ok'=>false,'error'=>'Edificio no vÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡lido'], 403);

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
      if ($id<=0 || $edificio_id<=0) json_out(['ok'=>false,'error'=>'Datos invÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡lidos'], 400);
      if (!edificio_permitido($pdo, $UNIDAD_ID, $edificio_id)) json_out(['ok'=>false,'error'=>'Edificio no vÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡lido'], 403);

      $st = $pdo->prepare("DELETE FROM it_mantenimientos WHERE id=? AND unidad_id=? AND edificio_id=?");
      $st->execute([$id, $UNIDAD_ID, $edificio_id]);
      json_out(['ok'=>true]);
    }

    /* ===== Aux: activos para combo mantenimiento ===== */
    if ($api === 'activos_combo') {
      $edificio_id = (int)($_GET['edificio_id'] ?? 0);
      if ($edificio_id<=0) json_out(['ok'=>false,'error'=>'edificio_id requerido'], 400);
      if (!edificio_permitido($pdo, $UNIDAD_ID, $edificio_id)) json_out(['ok'=>false,'error'=>'Edificio no vÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡lido'], 403);

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
   Modo pÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡gina
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

$edificiosImport = [];
try {
  $stImp = $pdo->prepare("SELECT id, nombre FROM red_edificios WHERE (unidad_id=? OR unidad_id IS NULL) ORDER BY nombre");
  $stImp->execute([$UNIDAD_ID]);
  $edificiosImport = $stImp->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $ex) {}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Informatica - Inventarios</title>
  <link rel="icon" href="<?= e($FAVICON) ?>">
  <link rel="icon" href="<?= e($ESCUDO) ?>">
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
      border:1px solid rgba(59,130,246,.24);
      border-radius:14px;
      overflow:hidden;
      background: linear-gradient(180deg,#f8fbff 0%,#eef5ff 100%);
      box-shadow: 0 14px 32px rgba(15,23,42,.18);
    }
    .table{
      margin:0;
      color: #111827;
      table-layout: auto;
    }
    .table thead th{
      background: linear-gradient(180deg,#12315b 0%,#0f2342 100%) !important;
      color: #f8fafc !important;
      border-color: rgba(191,219,254,.18) !important;
      font-size: .74rem;
      font-weight: 900;
      letter-spacing: .02em;
      text-transform: uppercase;
      position: sticky;
      top: 0;
      z-index: 1;
      white-space: nowrap;
      text-shadow: 0 1px 0 rgba(0,0,0,.32);
    }
    .table td, .table th {
      vertical-align: middle;
      border-color: #c7d7ea !important;
      font-size: .80rem;
      background: #f8fbff;
      color: #172033;
      padding: .34rem .52rem !important;
      line-height: 1.25;
    }
    .table td[data-col="equipo"], .table th[data-col="equipo"]{ min-width:150px; }
    .table td[data-col="asignado"], .table th[data-col="asignado"]{ min-width:210px; }
    .table td[data-col="dependencia"], .table th[data-col="dependencia"]{ min-width:145px; }
    .table td[data-col="subdependencia"], .table th[data-col="subdependencia"]{ min-width:155px; }
    .table td[data-col="usuario_intranet"], .table th[data-col="usuario_intranet"],
    .table td[data-col="usuario_gde"], .table th[data-col="usuario_gde"]{ min-width:145px; }
    .table tbody tr:nth-child(odd) td{ background:#f8fbff; }
    .table tbody tr:nth-child(even) td{ background:#edf4fc; }
    .table tbody tr.row-no-device td{ background:#fff7ed; }
    .table tbody tr.row-device-only td{ background:#ecfdf5; }
    .table tbody tr:hover td{
      background: #dbeafe;
      color:#0f172a;
    }
    .table .badge-soft{
      background:#e0f2fe;
      border-color:#7dd3fc;
      color:#0c4a6e;
      font-size:.72rem;
      padding:.16rem .42rem;
      box-shadow: inset 0 1px 0 rgba(255,255,255,.75);
    }
    .table-compact-input,
    .table-compact-select{
      width:100%;
      min-width:86px;
      border:1px solid #b7c9df;
      border-radius:8px;
      background:#f9fcff;
      color:#142033;
      font-size:.78rem;
      line-height:1.2;
      padding:.22rem .35rem;
      box-shadow: inset 0 1px 2px rgba(15,23,42,.05);
      transition: border-color .15s ease, box-shadow .15s ease, background .15s ease;
    }
    .table-compact-input:focus,
    .table-compact-select:focus{
      outline:none;
      border-color:#2563eb;
      background:#ffffff;
      box-shadow: 0 0 0 2px rgba(37,99,235,.16);
    }
    .table-compact-input[type="number"]{ min-width:48px; }
    .table-compact-select{ min-width:110px; }
    .table-compact-action{
      font-size:.74rem;
      padding:.18rem .38rem;
      border-radius:6px;
      white-space:nowrap;
    }
    .autosave-status{
      font-size:.72rem;
      font-weight:800;
      white-space:nowrap;
      color:#475569;
    }
    .autosave-status.saving{ color:#2563eb; }
    .autosave-status.saved{ color:#15803d; }
    .autosave-status.error{ color:#b91c1c; }
    .column-toolbar{
      display:flex;
      align-items:center;
      gap:.35rem;
      flex-wrap:wrap;
      margin-bottom:.5rem;
    }
    .column-toggle{
      border:1px solid rgba(125,211,252,.70);
      background:#e0f2fe;
      color:#0c4a6e;
      border-radius:999px;
      font-size:.74rem;
      font-weight:800;
      padding:.18rem .48rem;
      box-shadow: inset 0 1px 0 rgba(255,255,255,.70);
    }
    .column-toggle.is-hidden{
      background:#334155;
      border-color:#475569;
      color:#f8fafc;
      opacity:.72;
    }
    .col-hidden{ display:none !important; }
    .table-responsive{ max-height:70vh; }
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
        <h1>Informatica - Inventarios</h1>
        <div class="sub">
          <?= $modo_edificio ? ('Edificio: <b>' . e($edificio_nombre) . '</b>') : 'Inventarios de toda la unidad + filtros' ?>
          <span class="ms-2 badge-soft">Unidad ID: <?= (int)$UNIDAD_ID ?></span>
        </div>
        <?php if ($modo_edificio): ?>
          <div class="small-help mt-1">
            <?php if ($meta['ip_desde'] || $meta['ip_hasta']): ?>
              Rango IP: <span class="kbd mono"><?= e((string)$meta['ip_desde']) ?></span> hasta <span class="kbd mono"><?= e((string)$meta['ip_hasta']) ?></span>
            <?php endif; ?>
            <?php if ($meta['max_dispositivos'] !== null && $meta['max_dispositivos'] !== ''): ?>
              <span class="ms-2">Max disp.: <span class="kbd"><?= e((string)$meta['max_dispositivos']) ?></span></span>
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
            &lt;- Cambiar edificio / Unidad
          </a>
        <?php endif; ?>
        <a class="btn btn-success btn-sm" style="font-weight:700; padding:.35rem .9rem;" href="<?= e($URL_VOLVER) ?>">Volver</a>
      </div>
    </div>

    <?php if (!$modo_edificio): ?>
      <!-- MODO UNIDAD -->
      <?php if ($mensajeImport !== ''): ?>
        <div class="alert alert-success border-0" style="background:rgba(34,197,94,.14);color:#dcfce7;">
          <?= e($mensajeImport) ?>
        </div>
      <?php endif; ?>
      <?php if ($mensajeImportError !== ''): ?>
        <div class="alert alert-danger border-0" style="background:rgba(239,68,68,.14);color:#fee2e2;">
          <?= e($mensajeImportError) ?>
        </div>
      <?php endif; ?>
      <div class="cardx mb-3">
        <div class="cardx-h">
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="chip">Acciones</span>
            <span class="muted">Importar, transferir, generar carteles y exportar inventario.</span>
          </div>
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <button class="btn btn-outline-light btn-sm btn-std" type="button" data-bs-toggle="collapse" data-bs-target="#excelPanel" aria-expanded="false" aria-controls="excelPanel">Importar Excel</button>
            <button class="btn btn-primary btn-sm btn-std" type="button" id="btnAddUnidadDevice">Agregar dispositivo</button>
            <button class="btn btn-outline-light btn-sm btn-std" type="button" data-bs-toggle="collapse" data-bs-target="#transferPanel" aria-expanded="false" aria-controls="transferPanel">Transferencias</button>
            <button class="btn btn-outline-light btn-sm btn-std" type="button" data-bs-toggle="collapse" data-bs-target="#labelsPanel" aria-expanded="false" aria-controls="labelsPanel">Carteles</button>
            <button class="btn btn-success btn-sm btn-std" type="button" id="btnExportExcel">Exportar Excel</button>
            <button class="btn btn-danger btn-sm btn-std" type="button" id="btnExportPdf">Exportar PDF</button>
            <button class="btn btn-outline-light btn-sm btn-std" type="button" id="btnReloadUnit">Recargar</button>
          </div>
        </div>
        <div class="collapse" id="excelPanel">
        <div class="p-3 border-top border-secondary-subtle">
          <div class="d-flex align-items-center gap-2 flex-wrap mb-3">
            <span class="chip">Excel 2026</span>
            <span class="muted">Importa toda la escuela por Nombre PC y vincula personal por DNI.</span>
          </div>
          <form method="post" enctype="multipart/form-data" class="row g-3 align-items-end">
            <input type="hidden" name="accion_import_excel" value="1">
            <div class="col-12 col-md-4">
              <label class="form-label">Archivo Excel</label>
              <input type="file" name="excel_archivo" class="form-control" accept=".xls,.xlsx" required>
            </div>
            <div class="col-12 col-md-8">
              <button class="btn btn-primary btn-std" type="submit">Importar inventario</button>
            </div>
          </form>
        </div>
        </div>
        <div class="collapse" id="transferPanel">
        <div class="p-3 border-top border-secondary-subtle">
          <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-3">
            <div class="d-flex align-items-center gap-2 flex-wrap">
              <span class="chip">Transferencias</span>
              <span class="muted">Mover un equipo de una persona a otra sin duplicar la computadora.</span>
            </div>
            <button class="btn btn-outline-light btn-sm btn-std" type="button" id="btnReloadTransfer">Actualizar listas</button>
          </div>
          <div class="row g-3 align-items-end">
            <div class="col-12 col-lg-5">
              <label class="form-label">Equipo a transferir</label>
              <select class="form-select" id="transfer_activo_id">
                <option value="0">Seleccionar equipo</option>
              </select>
            </div>
            <div class="col-12 col-lg-5">
              <label class="form-label">Personal destino</label>
              <select class="form-select" id="transfer_personal_id">
                <option value="0">Seleccionar personal</option>
              </select>
            </div>
            <div class="col-12 col-lg-2 d-grid">
              <button class="btn btn-primary btn-std" type="button" id="btnTransferActivo">Transferir</button>
            </div>
            <div class="col-12">
              <span class="muted" id="transferStatus"></span>
            </div>
          </div>
        </div>
        </div>
        <div class="collapse" id="labelsPanel">
        <div class="p-3 border-top border-secondary-subtle">
          <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-3">
            <div class="d-flex align-items-center gap-2 flex-wrap">
              <span class="chip">Carteles</span>
              <span class="muted">Genera fichas para pegar en computadoras, impresoras y otros dispositivos.</span>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
              <button class="btn btn-primary btn-sm btn-std" type="button" id="btnPrintOneLabel">Imprimir carteles seleccionados</button>
              <button class="btn btn-outline-light btn-sm btn-std" type="button" id="btnPrintVisibleLabels">Imprimir visibles</button>
            </div>
          </div>
          <div class="row g-3 align-items-end">
            <div class="col-12 col-lg-6">
              <label class="form-label">Cartel 1</label>
              <select class="form-select" id="label_activo_id">
                <option value="0">Seleccionar equipo o dispositivo</option>
              </select>
            </div>
            <div class="col-12 col-lg-6">
              <label class="form-label">Cartel 2</label>
              <select class="form-select" id="label_activo_id_2">
                <option value="0">Seleccionar equipo o dispositivo</option>
              </select>
            </div>
            <div class="col-12 col-lg-4 d-none">
              <label class="form-label">Formato</label>
              <select class="form-select" id="label_format">
                <option value="detalle">Detalle PC / Dispositivo</option>
              </select>
            </div>
          </div>
        </div>
        </div>
      </div>
      <div class="cardx mb-3">
        <div class="cardx-h">
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="chip">Control por area</span>
            <span class="muted">Resumen y autorizaciones de dispositivos personales.</span>
          </div>
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <button class="btn btn-outline-light btn-sm btn-std" type="button" data-bs-toggle="collapse" data-bs-target="#areaSummaryPanel" aria-expanded="false" aria-controls="areaSummaryPanel">Resumen por area</button>
            <button class="btn btn-outline-light btn-sm btn-std" type="button" data-bs-toggle="collapse" data-bs-target="#personalDevicesPanel" aria-expanded="false" aria-controls="personalDevicesPanel">Dispositivos personales</button>
          </div>
        </div>
        <div class="collapse" id="areaSummaryPanel">
          <div class="p-3 border-top border-secondary-subtle">
            <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
              <span class="chip">Resumen por area</span>
              <button class="btn btn-outline-light btn-sm btn-std" type="button" id="btnReloadAreaSummary">Actualizar</button>
            </div>
            <div class="column-toolbar" id="areaSummaryColumnToolbar"></div>
            <div class="table-wrap">
              <div class="table-responsive" style="max-height:360px;">
                <table class="table table-hover align-middle">
                  <thead>
                    <tr id="areaSummaryHeadRow"></tr>
                  </thead>
                  <tbody id="tbAreaSummary"></tbody>
                </table>
              </div>
            </div>
            <div id="areaSummaryEmpty" class="muted mt-2" style="display:none;">Sin activos para resumir.</div>
          </div>
        </div>
        <div class="collapse" id="personalDevicesPanel">
          <div class="p-3 border-top border-secondary-subtle">
            <div class="d-flex align-items-center justify-content-between gap-2 mb-2 flex-wrap">
              <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="chip">Dispositivos personales</span>
                <span class="muted" id="personalDevicesCount"></span>
              </div>
              <button class="btn btn-primary btn-sm btn-std" type="button" id="btnAddPersonalDevice">Cargar dispositivo personal</button>
            </div>
            <div class="table-wrap">
              <div class="table-responsive" style="max-height:360px;">
                <table class="table table-hover align-middle">
                  <thead>
                    <tr>
                      <th>Dueno</th>
                      <th>Area</th>
                      <th>Dispositivo</th>
                      <th>Marca/Modelo</th>
                      <th>MAC</th>
                      <th>Estado</th>
                      <th style="width:90px;">Accion</th>
                    </tr>
                  </thead>
                  <tbody id="tbPersonalDevices"></tbody>
                </table>
              </div>
            </div>
            <div id="personalDevicesEmpty" class="muted mt-2" style="display:none;">Sin dispositivos personales cargados.</div>
          </div>
        </div>
      </div>
      <div class="cardx mb-3">
       
        <div class="p-3">
          <div class="row g-3 mb-3 align-items-end">
            <div class="col-12 col-lg-7">
              <label class="form-label">Buscar</label>
              <input class="form-control" id="f_q" placeholder="Nombre PC, dependencia, usuario intranet, usuario GDE, DNI, IP, MAC...">
            </div>
            <div class="col-12 col-lg-5 d-flex align-items-end gap-2 flex-wrap">
              <button class="btn btn-primary btn-std" id="btnApplyFilters">Buscar</button>
              <button class="btn btn-outline-light btn-std" type="button" data-bs-toggle="collapse" data-bs-target="#unitFilters" aria-expanded="false" aria-controls="unitFilters">Filtros</button>
              <button class="btn btn-outline-light btn-std" id="btnClearFilters">Limpiar</button>
            </div>
          </div>

          <div class="collapse" id="unitFilters">
            <div class="row g-3 mb-3">
              <div class="col-12 col-md-4">
                <label class="form-label">Mostrar</label>
                <select class="form-select" id="f_asignacion">
                  <option value="todos">Todo el personal y dispositivos</option>
                  <option value="con_pc">Solo personal con computadora</option>
                  <option value="sin_pc">Solo personal sin computadora</option>
                </select>
              </div>
              <div class="col-12 col-md-4">
                <label class="form-label">Edificio</label>
                <select class="form-select" id="f_edificio">
                  <option value="0">Todos</option>
                </select>
              </div>
              <div class="col-12 col-md-4">
                <label class="form-label">Tipo de dispositivo</label>
                <select class="form-select" id="f_pc_code">
                  <option value="">Todos</option>
                  <option value="CE">CE - Computadora de Escritorio</option>
                  <option value="CP">CP - Computadora Portatil</option>
                  <option value="SE">SE - Servidores</option>
                  <option value="RA">RA - Racks</option>
                  <option value="SW">SW - Switchs</option>
                  <option value="EN">EN - Enrutadores</option>
                  <option value="TI">TI - Telefonos IP</option>
                  <option value="AP">AP - Enrutadores Inalambricos</option>
                  <option value="IM">IM - Impresoras de Red</option>
                  <option value="PT">PT - Puesto de Trabajo</option>
                </select>
              </div>
              <div class="col-12 col-md-4">
                <label class="form-label">Tipo de personal</label>
                <select class="form-select" id="f_personal_grupo">
                  <option value="">Todos</option>
                  <option value="oficiales">Oficiales</option>
                  <option value="suboficiales">Suboficiales</option>
                  <option value="soldados">Soldados</option>
                  <option value="civiles">Agentes civiles</option>
                </select>
              </div>
              <div class="col-12 col-md-4">
                <label class="form-label">Dependencia</label>
                <select class="form-select" id="f_dependencia">
                  <option value="">Todas</option>
                </select>
              </div>
              <div class="col-12 col-md-4">
                <label class="form-label">Subdependencia</label>
                <select class="form-select" id="f_area">
                  <option value="0">Todas</option>
                </select>
              </div>
              <div class="col-12 col-md-4">
                <label class="form-label">Personal</label>
                <select class="form-select" id="f_personal">
                  <option value="0">Todos</option>
                </select>
              </div>
            </div>
          </div>

          <div class="column-toolbar" id="columnToolbar"></div>

          <div class="table-wrap">
            <div class="table-responsive">
              <table class="table table-hover align-middle">
                <thead>
                  <tr id="unidadHeadRow"></tr>
                </thead>
                <tbody id="tbUnidad"></tbody>
              </table>
            </div>
          </div>
          <div id="unidadEmpty" class="muted mt-2" style="display:none;">Sin activos cargados en la unidad (o no hay coincidencias).</div>
        </div>
      </div>


    <?php else: ?>
      <!-- MODO EDIFICIO -->
      <div class="cardx mb-3">
        <div class="cardx-h">
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="chip">GestiÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³n por edificio</span>
            <span class="muted">Activos ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â· Internet ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â· Mantenimientos</span>
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
                        <th>DescripciÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³n</th>
                        <th>Tipo</th>
                        <th>Dispositivo</th>
                        <th>Marca/Modelo</th>
                        <th>Serie</th>
                        <th>Estado</th>
                        <th>CondiciÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³n</th>
                        <th>ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Ârea</th>
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
                        <th>IP PÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Âºblica</th>
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
                <div class="muted">Tip: en ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œActivo asociadoÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â solo aparecen activos del edificio.</div>
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

  <div class="modal fade" id="mdlPersonalDevice" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <div class="chip">Dispositivo personal</div>
            <div class="muted mt-1">Celular, impresora, notebook, PC u otro equipo personal para registrar y autorizar.</div>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="pd_id" value="0">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Tipo</label>
              <select class="form-select" id="pd_dispositivo_tipo">
                <option value="NOTEBOOK">Notebook</option>
                <option value="PC">PC de escritorio</option>
                <option value="CELULAR">Celular</option>
                <option value="IMPRESORA">Impresora</option>
                <option value="TABLET">Tablet</option>
                <option value="OTRO">Otro</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Personal que la usa</label>
              <select class="form-select" id="pd_personal_id">
                <option value="0">Sin vincular</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Area</label>
              <select class="form-select" id="pd_area_id">
                <option value="0">Sin area</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Dueno</label>
              <input class="form-control" id="pd_propietario_nombre">
            </div>
            <div class="col-md-2">
              <label class="form-label">DNI dueno</label>
              <input class="form-control mono" id="pd_propietario_dni">
            </div>
            <div class="col-md-3">
              <label class="form-label">Dependencia</label>
              <input class="form-control" id="pd_sector_red">
            </div>
            <div class="col-md-3">
              <label class="form-label">Ubicacion</label>
              <input class="form-control" id="pd_ubicacion">
            </div>
            <div class="col-md-3">
              <label class="form-label">Marca</label>
              <input class="form-control" id="pd_marca">
            </div>
            <div class="col-md-3">
              <label class="form-label">Modelo</label>
              <input class="form-control" id="pd_modelo">
            </div>
            <div class="col-md-3">
              <label class="form-label">Serie</label>
              <input class="form-control mono" id="pd_nro_serie">
            </div>
            <div class="col-md-3">
              <label class="form-label">MAC</label>
              <input class="form-control mono" id="pd_mac" placeholder="AA:BB:CC:DD:EE:FF">
            </div>
            <div class="col-md-3">
              <label class="form-label">IP</label>
              <input class="form-control mono" id="pd_ip">
            </div>
            <div class="col-md-3">
              <label class="form-label">Sistema operativo</label>
              <input class="form-control" id="pd_sistema_operativo">
            </div>
            <div class="col-md-3 pd-computer-field">
              <label class="form-label">CPU</label>
              <input class="form-control" id="pd_cpu">
            </div>
            <div class="col-md-2 pd-computer-field">
              <label class="form-label">RAM GB</label>
              <input class="form-control mono" id="pd_ram_gb">
            </div>
            <div class="col-md-2 pd-computer-field">
              <label class="form-label">Disco</label>
              <select class="form-select" id="pd_disco_tipo">
                <option value="">-</option>
                <option value="HDD">HDD</option>
                <option value="SSD">SSD</option>
                <option value="NVME">NVME</option>
                <option value="EMMC">EMMC</option>
                <option value="OTRO">OTRO</option>
              </select>
            </div>
            <div class="col-md-2 pd-computer-field">
              <label class="form-label">Disco GB</label>
              <input class="form-control mono" id="pd_disco_gb">
            </div>
            <div class="col-md-3">
              <label class="form-label">Autorizacion</label>
              <select class="form-select" id="pd_autorizacion_estado">
                <option value="pendiente">Pendiente</option>
                <option value="autorizado">Autorizado</option>
                <option value="rechazado">Rechazado</option>
                <option value="vencido">Vencido</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Fecha autorizacion</label>
              <input type="date" class="form-control" id="pd_autorizacion_fecha">
            </div>
            <div class="col-md-4">
              <label class="form-label">Autorizado por</label>
              <input class="form-control" id="pd_autorizado_por">
            </div>
            <div class="col-12">
              <label class="form-label">Observaciones de autorizacion</label>
              <textarea class="form-control" id="pd_autorizacion_observaciones" rows="2"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-light btn-std" data-bs-dismiss="modal">Cancelar</button>
          <button type="button" class="btn btn-primary btn-std" id="btnSavePersonalDevice">Guardar</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="mdlUnidadDevice" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <div class="chip">Nuevo dispositivo</div>
            <div class="muted mt-1">Para impresoras, switches, routers, racks, telefonos IP y otros dispositivos sin personal a cargo.</div>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Tipo</label>
              <select class="form-select" id="ud_code">
                <option value="IM">IM - Impresora de Red</option>
                <option value="SW">SW - Switch</option>
                <option value="EN">EN - Enrutador</option>
                <option value="AP">AP - Enrutador Inalambrico</option>
                <option value="TI">TI - Telefono IP</option>
                <option value="RA">RA - Rack</option>
                <option value="SE">SE - Servidor</option>
                <option value="PT">PT - Puesto de Trabajo</option>
                <option value="CE">CE - Computadora de Escritorio</option>
                <option value="CP">CP - Computadora Portatil</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Nombre / Codigo</label>
              <input class="form-control mono" id="ud_equipo_nombre" placeholder="Ej: U2285-IM-001">
            </div>
            <div class="col-md-4">
              <label class="form-label">Estado</label>
              <select class="form-select" id="ud_estado">
                <option value="operativo">operativo</option>
                <option value="mantenimiento">mantenimiento</option>
                <option value="roto">roto</option>
                <option value="baja">baja</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Dependencia</label>
              <input class="form-control" id="ud_dependencia" placeholder="Ej: SUBDIRECCION / BDA MIL">
            </div>
            <div class="col-md-6">
              <label class="form-label">Subdependencia / Area</label>
              <select class="form-select" id="ud_area_id">
                <option value="0">Sin subdependencia</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Marca</label>
              <input class="form-control" id="ud_marca" placeholder="Ej: HP">
            </div>
            <div class="col-md-4">
              <label class="form-label">Modelo</label>
              <input class="form-control" id="ud_modelo" placeholder="Ej: LaserJet M404dn">
            </div>
            <div class="col-md-4">
              <label class="form-label">Serie</label>
              <input class="form-control" id="ud_nro_serie">
            </div>
            <div class="col-md-4">
              <label class="form-label">IP</label>
              <input class="form-control mono" id="ud_ip">
            </div>
            <div class="col-md-4">
              <label class="form-label">MAC</label>
              <input class="form-control mono" id="ud_mac">
            </div>
            <div class="col-md-4">
              <label class="form-label">Ubicacion</label>
              <input class="form-control" id="ud_ubicacion" placeholder="Ej: Mesa entrada / Rack">
            </div>
            <div class="col-12">
              <label class="form-label">Observaciones</label>
              <input class="form-control" id="ud_observaciones">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline-light btn-std" data-bs-dismiss="modal">Cancelar</button>
          <button class="btn btn-primary btn-std" id="btnSaveUnidadDevice">Guardar dispositivo</button>
        </div>
      </div>
    </div>
  </div>

  <!-- MODAL ACTIVO (PRO por tipo de dispositivo) -->
  <div class="modal fade" id="mdlActivo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <div class="chip">Activo</div>
            <div class="muted mt-1">Inventario por edificio (campos aparecen segÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Âºn <b>Dispositivo</b>).</div>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>

        <div class="modal-body">
          <input type="hidden" id="a_id" value="0">

          <div class="grid-2">
            <!-- IZQUIERDA -->
            <div>
              <div class="chip mb-2">IdentificaciÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³n</div>
              <div class="row g-3">

                <div class="col-md-4">
                  <label class="form-label">CategorÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â­a</label>
                  <select class="form-select" id="a_categoria">
                    <option value="informatica">InformÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡tica</option>
                    <option value="redes">Redes</option>
                    <option value="perifericos">PerifÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©ricos</option>
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
                    <option value="">ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â</option>
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
                  <label class="form-label">DescripciÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³n *</label>
                  <input class="form-control" id="a_descripcion" maxlength="255" placeholder="Ej: PC escritorio S3 ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â HP ProDesk">
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
                  <label class="form-label">NÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â° Serie</label>
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
                  <label class="form-label">CondiciÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³n</label>
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
                  <label class="form-label">UbicaciÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³n</label>
                  <input class="form-control" id="a_ubicacion_detalle" maxlength="190" placeholder="Ej: Oficina S3 / Rack / Mesa 1">
                </div>

                <div class="col-md-6">
                  <label class="form-label">ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Ârea</label>
                  <select class="form-select" id="a_area_id">
                    <option value="0">ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â Sin ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡rea ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â</option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Asignado a (personal)</label>
                  <select class="form-select" id="a_asignado_personal_id">
                    <option value="0">ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â Sin asignar ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â</option>
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
              <div class="chip mb-2">Datos segÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Âºn dispositivo</div>

              <!-- ===== PC/NOTEBOOK/SERVIDOR ===== -->
              <div id="blkPC" style="display:none;">
                <div class="chip mb-2" style="background:rgba(34,197,94,.12); border-color:rgba(34,197,94,.25);">PC ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â· NOTEBOOK ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â· SERVIDOR</div>
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
                      <option value="">ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â</option>
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
                    <label class="form-label">PerifÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©ricos</label>
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
                    <input class="form-control mono" id="a_serial_windows" maxlength="120" placeholder="(si decidÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â­s guardarlo)">
                    <div class="small-help mt-1">RecomendaciÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³n: dejar vacÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â­o si no hace falta.</div>
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
                      Para impresoras: completÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡ <b>Modelo</b>, <b>UbicaciÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³n</b>, <b>IP/MAC</b> e <b>IP fija</b> si corresponde.
                    </div>
                  </div>
                </div>
                <div class="sep"></div>
              </div>

              <!-- ===== SWITCH/ROUTER/MODEM/AP ===== -->
              <div id="blkRed" style="display:none;">
                <div class="chip mb-2" style="background:rgba(14,165,233,.12); border-color:rgba(14,165,233,.25);">SWITCH ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â· ROUTER ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â· MODEM ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â· AP</div>
                <div class="row g-3">
                  <div class="col-md-12">
                    <div class="small-help">
                      Para equipos de red: completÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡ <b>Modelo</b>, <b>UbicaciÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³n</b>, <b>IP/MAC</b>, <b>IP fija</b> y <b>Observaciones</b> si hace falta.
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
                      <option value="1">SÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â­</option>
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
                    <input class="form-control" id="a_sector_red" maxlength="120" placeholder="Ej: S3 / AdministraciÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³n / Sala Servidores">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">VLAN</label>
                    <input class="form-control" id="a_vlan" maxlength="40" placeholder="Ej: 10 / VLAN-ADM">
                  </div>
                </div>
              </div>

              <div class="sep"></div>
              <div class="small-help" id="hintAuto" style="display:none;">
                El formulario se ajusta automÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡ticamente segÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Âºn el <b>Dispositivo</b>. Los campos que no aplican se guardan como <b>NULL</b>.
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
              <label class="form-label">IP PÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Âºblica</label>
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
                <option value="instalacion">InstalaciÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³n</option>
                <option value="red">Red</option>
                <option value="otros">Otros</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Activo asociado</label>
              <select class="form-select" id="m_activo_id">
                <option value="0">ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â Sin asociar ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â</option>
              </select>
              <div class="small-help mt-1">Si no aparece, cargalo primero en ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œActivosÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â.</div>
            </div>

            <div class="col-md-12">
              <label class="form-label">Detalle *</label>
              <textarea class="form-control" id="m_detalle" rows="3" maxlength="65000" placeholder="QuÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â© se hizo, quÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â© se cambiÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³, diagnÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³stico..."></textarea>
            </div>

            <div class="col-md-8">
              <label class="form-label">Realizado por</label>
              <input class="form-control" id="m_realizado_por" maxlength="120" placeholder="Ej: Taller informÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡tica / TÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©cnico X">
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
      el.textContent = (a || b) ? ((a || '-') + ' hasta ' + (b || '-')) : '-';
    }

    function escapeHtml(s){
      return String(s)
        .replaceAll('&','&amp;')
        .replaceAll('<','&lt;')
        .replaceAll('>','&gt;')
        .replaceAll('"','&quot;')
        .replaceAll("'","&#039;");
    }

    function fixMojibakeText(s){
      const map = {
        'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡':'a','ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©':'e','ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â­':'i','ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³':'o','ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Âº':'u','ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â±':'n',
        'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡':'a','ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©':'e','ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â­':'i','ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³':'o','ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Âº':'u','ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â±':'n',
        'ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡':'a','ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©':'e','ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â­':'i','ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³':'o','ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Âº':'u','ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â±':'n',
        'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â':'A','ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â':'A','ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â':'A',
        'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â·':' - ','ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â·':' - ','ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â·':' - ',
        'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â°':'ro','ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â°':'ro','ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â°':'ro',
        'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¿':'Ãƒâ€šÃ‚Â¿','ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¿':'Ãƒâ€šÃ‚Â¿','ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¿':'Ãƒâ€šÃ‚Â¿',
        'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â':'-','ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â':'-','ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â':'-',
        'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢':' hasta ','ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢':' hasta ','ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢':' hasta ',
        'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â':'<-','ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â':'<-','ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â Ãƒâ€šÃ‚Â':'<-',
        'ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œ':'"','ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â':'"','ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã¢â‚¬Å“':'"','ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â':'"',
        'ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢':"'",'ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢':"'"
      };
      let out = String(s);
      for (const [bad, good] of Object.entries(map)) out = out.split(bad).join(good);
      return out;
    }

    function fixVisibleMojibake(root=document.body){
      const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
        acceptNode(node){
          return node.parentElement && ['SCRIPT','STYLE'].includes(node.parentElement.tagName)
            ? NodeFilter.FILTER_REJECT
            : NodeFilter.FILTER_ACCEPT;
        }
      });
      const nodes = [];
      while (walker.nextNode()) nodes.push(walker.currentNode);
      for (const node of nodes) node.nodeValue = fixMojibakeText(node.nodeValue);
      for (const el of root.querySelectorAll('input[placeholder], textarea[placeholder], [title]')){
        if (el.placeholder) el.placeholder = fixMojibakeText(el.placeholder);
        if (el.title) el.title = fixMojibakeText(el.title);
      }
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

      // IP/MAC: aplica a IMPRESORA y EQUIPOS DE RED, y opcional a PC (lo dejamos visible tambiÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©n para PC)
      showBlock('blkNetCore', isPCType(d) || isPrinterType(d) || isNetDeviceType(d));

      // Red avanzada: PC + equipos de red
      showBlock('blkRedAv', isPCType(d) || isNetDeviceType(d));

      const hint = document.getElementById('hintAuto');
      if (hint) hint.style.display = (d ? '' : 'none');

      setIpHint();
    }

    // Limpia campos NO aplicables => el backend los guardarÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡ como NULL
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

      // OTRO / vacÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â­o: limpia todo lo PRO
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
    let areasCache = [];
    let personalCache = [];
    let transferActivosCache = [];
    let transferPersonalCache = [];
    let unidadDefaultEdificioId = 0;
    let personalDeviceEditingName = '';
    let areaSummaryRows = [];
    const unidadDeviceModal = !MODO_EDIFICIO ? new bootstrap.Modal(document.getElementById('mdlUnidadDevice')) : null;
    const personalDeviceModal = !MODO_EDIFICIO ? new bootstrap.Modal(document.getElementById('mdlPersonalDevice')) : null;
    const areaSummaryColumns = [
      {key:'area_nombre', label:'Area'},
      {key:'ce', label:'CE'},
      {key:'cp', label:'CP'},
      {key:'se', label:'SE'},
      {key:'ra', label:'RA'},
      {key:'sw', label:'SW'},
      {key:'en', label:'EN'},
      {key:'ti', label:'TI'},
      {key:'ap', label:'AP'},
      {key:'im', label:'IM'},
      {key:'pt', label:'PT'},
      {key:'personales', label:'Personales'},
      {key:'total_dispositivos', label:'Total'},
      {key:'autorizados', label:'Aut.'},
      {key:'pendientes', label:'Pend.'}
    ];
    const areaSummaryColumnLabels = {
      ce:'CE Escritorio',
      cp:'CP Portatil',
      se:'SE Servidores',
      ra:'RA Racks',
      sw:'SW Switchs',
      en:'EN Enrutadores',
      ti:'TI Telefonos IP',
      ap:'AP Inalambricos',
      im:'IM Impresoras',
      pt:'PT Puesto Trabajo',
      personales:'Personales',
      total_dispositivos:'Total',
      autorizados:'Autorizados',
      pendientes:'Pendientes'
    };
    const hiddenAreaSummaryColumns = new Set([]);
    const unidadAllColumns = [
      {key:'equipo', label:'Nombre/Codigo'},
      {key:'clase', label:'Clase'},
      {key:'dependencia', label:'Dependencia'},
      {key:'subdependencia', label:'Subdependencia'},
      {key:'asignado', label:'Asignado a'},
      {key:'dni', label:'DNI'},
      {key:'usuario_intranet', label:'Usuario Intranet'},
      {key:'usuario_gde', label:'Usuario GDE'},
      {key:'marca', label:'Marca'},
      {key:'modelo', label:'Modelo'},
      {key:'serie', label:'Serie'},
      {key:'ubicacion', label:'Ubicacion'},
      {key:'so', label:'S.O'},
      {key:'cpu', label:'Procesador'},
      {key:'ram', label:'RAM'},
      {key:'disco', label:'Disco'},
      {key:'ip', label:'IP'},
      {key:'mac', label:'MAC'},
      {key:'monitor', label:'Monitor'},
      {key:'perifericos', label:'Perifericos'},
      {key:'observaciones', label:'Observaciones'},
      {key:'estado', label:'Estado'}
    ];
    const unidadColumnProfiles = {
      pc: ['equipo','clase','dependencia','subdependencia','asignado','dni','usuario_intranet','usuario_gde','ip','mac','so','cpu','ram','disco','monitor','perifericos','observaciones','estado'],
      impresora: ['equipo','clase','dependencia','subdependencia','marca','modelo','serie','ubicacion','ip','mac','observaciones','estado'],
      red: ['equipo','clase','dependencia','subdependencia','marca','modelo','serie','ubicacion','ip','mac','observaciones','estado'],
      general: ['equipo','clase','dependencia','subdependencia','asignado','dni','marca','modelo','serie','ubicacion','ip','mac','observaciones','estado']
    };
    const unidadColumnsByKey = Object.fromEntries(unidadAllColumns.map(c => [c.key, c]));
    const hiddenUnidadColumns = new Set([]);

    function currentUnidadProfile(){
      const code = (document.getElementById('f_pc_code')?.value || '').toUpperCase();
      if (code === 'IM') return 'impresora';
      if (['SW','EN','AP','RA','TI','SE','PT'].includes(code)) return 'red';
      if (['CE','CP'].includes(code)) return 'pc';
      return 'pc';
    }

    function isUnidadGeneralPeopleView(){
      const code = (document.getElementById('f_pc_code')?.value || '').trim();
      const asignacion = (document.getElementById('f_asignacion')?.value || 'todos').trim();
      return !code && asignacion !== 'con_pc';
    }

    function getUnidadColumns(){
      return (unidadColumnProfiles[currentUnidadProfile()] || unidadColumnProfiles.general)
        .map(k => unidadColumnsByKey[k])
        .filter(Boolean);
    }

    function renderColumnToolbar(){
      const bar = document.getElementById('columnToolbar');
      if (!bar) return;
      bar.innerHTML = '<span class="muted me-1">Columnas:</span>';
      for (const col of getUnidadColumns().filter(c => !['equipo','estado'].includes(c.key))){
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'column-toggle' + (hiddenUnidadColumns.has(col.key) ? ' is-hidden' : '');
        btn.dataset.colKey = col.key;
        btn.textContent = col.label;
        btn.addEventListener('click', () => {
          if (hiddenUnidadColumns.has(col.key)) hiddenUnidadColumns.delete(col.key);
          else hiddenUnidadColumns.add(col.key);
          renderUnidadTable();
        });
        bar.appendChild(btn);
      }
    }

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
            if (!unidadDefaultEdificioId) unidadDefaultEdificioId = Number(r.id || 0);
          }
        }
      }catch(_){}

      try{
        const ar = await apiGet({api:'areas'});
        areasCache = ar.rows || [];
        const sel = document.getElementById('f_area');
        if (sel){
          for (const r of (ar.rows||[])){
            const opt = document.createElement('option');
            opt.value = String(r.id);
            opt.textContent = (r.nombre || ('Area ' + r.id));
            sel.appendChild(opt);
          }
        }
      }catch(_){}

      try{
        const deps = await apiGet({api:'dependencias'});
        const sel = document.getElementById('f_dependencia');
        if (sel){
          for (const r of (deps.rows||[])){
            const opt = document.createElement('option');
            opt.value = r.nombre || '';
            opt.textContent = r.nombre || '';
            sel.appendChild(opt);
          }
        }
      }catch(_){}

      try{
        const pe = await apiGet({api:'personal'});
        personalCache = pe.rows || [];
        const sel = document.getElementById('f_personal');
        if (sel){
          for (const r of (pe.rows||[])){
            const opt = document.createElement('option');
            opt.value = String(r.id);
            opt.textContent = r.label || (`ID ${r.id}`);
            sel.appendChild(opt);
          }
        }
        populatePersonalDeviceControls();
      }catch(_){}
    }

    function populatePersonalDeviceControls(){
      const selA = document.getElementById('pd_area_id');
      if (selA){
        const cur = selA.value || '0';
        selA.innerHTML = '<option value="0">Sin area</option>';
        for (const a of areasCache){
          const opt = document.createElement('option');
          opt.value = String(a.id);
          opt.textContent = a.nombre || ('Area ' + a.id);
          selA.appendChild(opt);
        }
        selA.value = cur;
      }

      const selP = document.getElementById('pd_personal_id');
      if (selP){
        const cur = selP.value || '0';
        selP.innerHTML = '<option value="0">Sin vincular</option>';
        for (const p of personalCache){
          const opt = document.createElement('option');
          opt.value = String(p.id);
          opt.textContent = p.label || ('ID ' + p.id);
          opt.dataset.dni = p.dni || '';
          opt.dataset.areaId = p.destino_interno || '';
          selP.appendChild(opt);
        }
        selP.value = cur;
      }
    }

    function unidadDeviceLabel(code){
      return {
        CE:'Computadora de Escritorio',
        CP:'Computadora Portatil',
        SE:'Servidor',
        RA:'Rack',
        SW:'Switch',
        EN:'Enrutador',
        TI:'Telefono IP',
        AP:'Enrutador Inalambrico',
        IM:'Impresora de Red',
        PT:'Puesto de Trabajo'
      }[String(code || '').toUpperCase()] || 'OTRO';
    }

    function unidadDeviceDbType(code){
      return {
        CE:'PC',
        CP:'NOTEBOOK',
        SE:'SERVIDOR',
        SW:'SWITCH',
        EN:'ROUTER',
        AP:'AP',
        IM:'IMPRESORA'
      }[String(code || '').toUpperCase()] || 'OTRO';
    }

    function nextUnidadDeviceName(code){
      const prefix = `U2285-${String(code || '').toUpperCase()}-`;
      const nums = [];
      for (const r of [...unidadRows, ...transferActivosCache]){
        const name = String(r.equipo_nombre || '').toUpperCase();
        if (name.startsWith(prefix)){
          const n = Number(name.slice(prefix.length));
          if (Number.isFinite(n)) nums.push(n);
        }
      }
      const next = (nums.length ? Math.max(...nums) + 1 : 1);
      return prefix + String(next).padStart(3, '0');
    }

    function populateUnidadDeviceAreas(){
      const sel = document.getElementById('ud_area_id');
      if (!sel) return;
      const cur = sel.value || '0';
      sel.innerHTML = '<option value="0">Sin subdependencia</option>';
      for (const a of areasCache){
        const opt = document.createElement('option');
        opt.value = String(a.id);
        opt.textContent = a.nombre || ('Area ' + a.id);
        sel.appendChild(opt);
      }
      sel.value = cur;
    }

    function openUnidadDeviceModal(){
      if (!unidadDeviceModal) return;
      populateUnidadDeviceAreas();
      const code = (document.getElementById('f_pc_code')?.value || 'IM') || 'IM';
      document.getElementById('ud_code').value = ['CE','CP','SE','RA','SW','EN','TI','AP','IM','PT'].includes(code) ? code : 'IM';
      document.getElementById('ud_equipo_nombre').value = nextUnidadDeviceName(document.getElementById('ud_code').value);
      document.getElementById('ud_estado').value = 'operativo';
      document.getElementById('ud_dependencia').value = document.getElementById('f_dependencia')?.value || '';
      document.getElementById('ud_area_id').value = document.getElementById('f_area')?.value || '0';
      document.getElementById('ud_marca').value = '';
      document.getElementById('ud_modelo').value = '';
      document.getElementById('ud_nro_serie').value = '';
      document.getElementById('ud_ip').value = '';
      document.getElementById('ud_mac').value = '';
      document.getElementById('ud_ubicacion').value = '';
      document.getElementById('ud_observaciones').value = '';
      unidadDeviceModal.show();
    }

    async function saveUnidadDevice(){
      const code = document.getElementById('ud_code').value;
      const equipo = document.getElementById('ud_equipo_nombre').value.trim().toUpperCase();
      const dep = document.getElementById('ud_dependencia').value.trim();
      const areaId = Number(document.getElementById('ud_area_id').value || 0);
      const edificioId = Number(document.getElementById('f_edificio')?.value || 0) || unidadDefaultEdificioId;
      if (!edificioId){
        Swal.fire({icon:'warning', title:'Falta edificio base', text:'No hay edificio cargado para asociar el dispositivo.'});
        return;
      }
      if (!equipo){
        Swal.fire({icon:'warning', title:'Falta nombre', text:'Carga el nombre/codigo del dispositivo.'});
        return;
      }
      if (!dep && !areaId){
        Swal.fire({icon:'warning', title:'Falta ubicacion', text:'Carga dependencia o subdependencia.'});
        return;
      }

      const labelDispositivo = unidadDeviceLabel(code);
      const dispositivo = unidadDeviceDbType(code);
      const descripcion = `${labelDispositivo} ${equipo}`.trim();
      const payload = {
        id: 0,
        edificio_id: edificioId,
        categoria: ['SW','EN','AP','RA','TI'].includes(code) ? 'redes' : 'informatica',
        tipo: ['CE','CP'].includes(code) ? 'pc' : 'otro',
        dispositivo_tipo: dispositivo,
        etiqueta: equipo,
        descripcion,
        marca: document.getElementById('ud_marca').value.trim(),
        modelo: document.getElementById('ud_modelo').value.trim(),
        nro_serie: document.getElementById('ud_nro_serie').value.trim(),
        estado: document.getElementById('ud_estado').value,
        condicion: 'activo',
        fecha_alta: '',
        ubicacion_detalle: document.getElementById('ud_ubicacion').value.trim(),
        area_id: areaId,
        asignado_personal_id: 0,
        observaciones: document.getElementById('ud_observaciones').value.trim(),
        equipo_nombre: equipo,
        usuario_asignado: '',
        sistema_operativo: '',
        cpu: '',
        ram_gb: '',
        disco_tipo: '',
        disco_gb: '',
        monitor: '',
        perifericos: '',
        mac: document.getElementById('ud_mac').value.trim(),
        ip: document.getElementById('ud_ip').value.trim(),
        ip_fija: 0,
        antivirus: '',
        office_version: '',
        serial_windows: '',
        ip_gateway: '',
        dns1: '',
        dns2: '',
        switch_puerto: '',
        patchera_puerto: '',
        sector_red: dep,
        vlan: '',
        propiedad: 'unidad',
        propietario_nombre: '',
        propietario_dni: '',
        autorizacion_estado: 'pendiente',
        autorizacion_fecha: '',
        autorizado_por: '',
        autorizacion_observaciones: ''
      };

      try{
        await apiPost('activos_save', payload);
        unidadDeviceModal.hide();
        await fillTransferControls();
        await loadUnidadActivos();
        await loadAreaSummary();
        Swal.fire({icon:'success', title:'Dispositivo agregado', timer:1000, showConfirmButton:false});
      }catch(err){
        Swal.fire({icon:'error', title:'No se pudo guardar', text: err.message});
      }
    }

    async function loadAreaSummary(){
      const tb = document.getElementById('tbAreaSummary');
      const empty = document.getElementById('areaSummaryEmpty');
      if (!tb || !empty) return;
      tb.innerHTML = '';
      empty.style.display = 'none';
      try{
        const data = await apiGet({api:'inventario_resumen_areas'});
        areaSummaryRows = data.rows || [];
        renderAreaSummaryColumnToolbar();
        renderAreaSummaryTable();
      }catch(err){
        empty.style.display = '';
        empty.textContent = 'Error: ' + err.message;
      }
    }

    function renderAreaSummaryColumnToolbar(){
      const bar = document.getElementById('areaSummaryColumnToolbar');
      if (!bar) return;
      bar.innerHTML = '<span class="muted me-1">Columnas:</span>';
      for (const col of areaSummaryColumns.filter(c => c.key !== 'area_nombre')){
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'column-toggle' + (hiddenAreaSummaryColumns.has(col.key) ? ' is-hidden' : '');
        btn.dataset.colKey = col.key;
        btn.textContent = areaSummaryColumnLabels[col.key] || col.label;
        btn.addEventListener('click', () => {
          if (hiddenAreaSummaryColumns.has(col.key)) hiddenAreaSummaryColumns.delete(col.key);
          else hiddenAreaSummaryColumns.add(col.key);
          renderAreaSummaryTable();
          renderAreaSummaryColumnToolbar();
        });
        bar.appendChild(btn);
      }
    }

    function areaSummaryVisibleColumns(){
      return areaSummaryColumns.filter(c => !hiddenAreaSummaryColumns.has(c.key));
    }

    function areaSummaryValue(row, key){
      if (key === 'area_nombre') return row.area_nombre || 'Sin area';
      return Number(row[key] || 0);
    }

    function renderAreaSummaryTable(){
      const tb = document.getElementById('tbAreaSummary');
      const head = document.getElementById('areaSummaryHeadRow');
      const empty = document.getElementById('areaSummaryEmpty');
      if (!tb || !head || !empty) return;
      tb.innerHTML = '';
      empty.style.display = 'none';
      const cols = areaSummaryVisibleColumns();
      head.innerHTML = cols
        .map(c => `<th data-col="${escapeHtml(c.key)}">${escapeHtml(c.label)}</th>`)
        .join('');
      if (!areaSummaryRows.length){
        empty.style.display = '';
        return;
      }
      const totals = {area_nombre:'TOTAL'};
      for (const col of areaSummaryColumns){
        if (col.key === 'area_nombre') continue;
        totals[col.key] = areaSummaryRows.reduce((sum, row) => sum + Number(row[col.key] || 0), 0);
      }
      for (const r of [...areaSummaryRows, totals]){
        const tr = document.createElement('tr');
        if (r === totals) tr.style.fontWeight = '900';
        tr.innerHTML = cols.map(c => {
          const v = areaSummaryValue(r, c.key);
          return c.key === 'area_nombre'
            ? `<td>${escapeHtml(v)}</td>`
            : `<td class="mono">${escapeHtml(v)}</td>`;
        }).join('');
        tb.appendChild(tr);
      }
    }

    function isPersonalDevice(r){
      return String(r.propiedad || '').toLowerCase() === 'personal';
    }

    function personalDeviceLabel(r){
      const raw = String(r.dispositivo_tipo || '').toUpperCase();
      const desc = String(r.descripcion || '').toUpperCase();
      if (desc.includes('CELULAR')) return 'CELULAR';
      if (desc.includes('TABLET')) return 'TABLET';
      if (raw === 'OTRO' && desc.includes('OTRO')) return 'OTRO';
      return raw || 'OTRO';
    }

    function applyPersonalDeviceUi(){
      const tipo = document.getElementById('pd_dispositivo_tipo')?.value || 'NOTEBOOK';
      const isComputer = ['NOTEBOOK','PC'].includes(tipo);
      document.querySelectorAll('.pd-computer-field').forEach(el => {
        el.style.display = isComputer ? '' : 'none';
      });
      if (!isComputer){
        document.getElementById('pd_cpu').value = '';
        document.getElementById('pd_ram_gb').value = '';
        document.getElementById('pd_disco_tipo').value = '';
        document.getElementById('pd_disco_gb').value = '';
      }
    }

    function renderPersonalDevices(){
      const tb = document.getElementById('tbPersonalDevices');
      const empty = document.getElementById('personalDevicesEmpty');
      const count = document.getElementById('personalDevicesCount');
      if (!tb || !empty) return;
      tb.innerHTML = '';
      empty.style.display = 'none';
      const rows = unidadRows.filter(isPersonalDevice);
      if (count) count.textContent = rows.length ? `${rows.length} cargados` : '';
      if (!rows.length){
        empty.style.display = '';
        return;
      }
      for (const r of rows){
        const marcaModelo = [r.marca || '', r.modelo || ''].filter(Boolean).join(' ');
        const estado = r.autorizacion_estado || 'pendiente';
        const label = personalDeviceLabel(r);
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${escapeHtml(r.propietario_nombre || (r.asignado_label || '').replace(/^, /,'') || '')}</td>
          <td>${escapeHtml(r.area_nombre || '')}</td>
          <td><span class="badge-soft">${escapeHtml(label)}</span>${r.equipo_nombre ? ` <span class="mono">${escapeHtml(r.equipo_nombre || '')}</span>` : ''}</td>
          <td>${escapeHtml(marcaModelo)}</td>
          <td class="mono">${escapeHtml(r.mac || '')}</td>
          <td><span class="badge-soft">${escapeHtml(estado)}</span></td>
          <td><button class="btn btn-sm btn-outline-info btn-std" type="button">Editar</button></td>
        `;
        tr.querySelector('button').addEventListener('click', () => openPersonalDeviceModal(r));
        tb.appendChild(tr);
      }
    }

    function resetPersonalDeviceForm(){
      const today = new Date();
      const yyyy = today.getFullYear();
      const mm = String(today.getMonth()+1).padStart(2,'0');
      const dd = String(today.getDate()).padStart(2,'0');
      document.getElementById('pd_id').value = '0';
      document.getElementById('pd_dispositivo_tipo').value = 'NOTEBOOK';
      document.getElementById('pd_personal_id').value = document.getElementById('f_personal')?.value || '0';
      document.getElementById('pd_area_id').value = document.getElementById('f_area')?.value || '0';
      document.getElementById('pd_propietario_nombre').value = '';
      document.getElementById('pd_propietario_dni').value = '';
      document.getElementById('pd_sector_red').value = document.getElementById('f_dependencia')?.value || '';
      document.getElementById('pd_ubicacion').value = '';
      document.getElementById('pd_marca').value = '';
      document.getElementById('pd_modelo').value = '';
      document.getElementById('pd_nro_serie').value = '';
      document.getElementById('pd_mac').value = '';
      document.getElementById('pd_ip').value = '';
      document.getElementById('pd_sistema_operativo').value = '';
      document.getElementById('pd_cpu').value = '';
      document.getElementById('pd_ram_gb').value = '';
      document.getElementById('pd_disco_tipo').value = '';
      document.getElementById('pd_disco_gb').value = '';
      document.getElementById('pd_autorizacion_estado').value = 'pendiente';
      document.getElementById('pd_autorizacion_fecha').value = `${yyyy}-${mm}-${dd}`;
      document.getElementById('pd_autorizado_por').value = '';
      document.getElementById('pd_autorizacion_observaciones').value = '';
      personalDeviceEditingName = '';
      applyPersonalDeviceUi();
      syncPersonalOwnerFromSelect(false);
    }

    function syncPersonalOwnerFromSelect(overwrite=true){
      const sel = document.getElementById('pd_personal_id');
      const opt = sel?.selectedOptions?.[0];
      if (!opt || !Number(sel.value || 0)) return;
      const owner = document.getElementById('pd_propietario_nombre');
      const dni = document.getElementById('pd_propietario_dni');
      const area = document.getElementById('pd_area_id');
      if (owner && (overwrite || !owner.value)) owner.value = opt.textContent || '';
      if (dni && (overwrite || !dni.value)) dni.value = opt.dataset.dni || '';
      if (area && (overwrite || area.value === '0') && opt.dataset.areaId) area.value = opt.dataset.areaId;
    }

    function openPersonalDeviceModal(row=null){
      if (!personalDeviceModal) return;
      populatePersonalDeviceControls();
      resetPersonalDeviceForm();
      if (row){
        personalDeviceEditingName = row.equipo_nombre || '';
        document.getElementById('pd_id').value = String(row.id || 0);
        const label = personalDeviceLabel(row);
        document.getElementById('pd_dispositivo_tipo').value = ['NOTEBOOK','PC','CELULAR','IMPRESORA','TABLET','OTRO'].includes(label) ? label : 'OTRO';
        document.getElementById('pd_personal_id').value = String(row.asignado_personal_id || 0);
        document.getElementById('pd_area_id').value = String(row.area_id || 0);
        document.getElementById('pd_propietario_nombre').value = row.propietario_nombre || '';
        document.getElementById('pd_propietario_dni').value = row.propietario_dni || '';
        document.getElementById('pd_sector_red').value = row.sector_red || row.dependencia_nombre || '';
        document.getElementById('pd_ubicacion').value = row.ubicacion_detalle || '';
        document.getElementById('pd_marca').value = row.marca || '';
        document.getElementById('pd_modelo').value = row.modelo || '';
        document.getElementById('pd_nro_serie').value = row.nro_serie || '';
        document.getElementById('pd_mac').value = row.mac || '';
        document.getElementById('pd_ip').value = row.ip || '';
        document.getElementById('pd_sistema_operativo').value = row.sistema_operativo || '';
        document.getElementById('pd_cpu').value = row.cpu || '';
        document.getElementById('pd_ram_gb').value = row.ram_gb || '';
        document.getElementById('pd_disco_tipo').value = row.disco_tipo || '';
        document.getElementById('pd_disco_gb').value = row.disco_gb || '';
        document.getElementById('pd_autorizacion_estado').value = row.autorizacion_estado || 'pendiente';
        document.getElementById('pd_autorizacion_fecha').value = row.autorizacion_fecha || '';
        document.getElementById('pd_autorizado_por').value = row.autorizado_por || '';
        document.getElementById('pd_autorizacion_observaciones').value = row.autorizacion_observaciones || '';
        applyPersonalDeviceUi();
      }
      personalDeviceModal.show();
    }

    async function savePersonalDevice(){
      const personalId = Number(document.getElementById('pd_personal_id').value || 0);
      const tipo = document.getElementById('pd_dispositivo_tipo').value;
      const tipoDb = ({CELULAR:'OTRO', TABLET:'OTRO'}[tipo] || tipo);
      const owner = document.getElementById('pd_propietario_nombre').value.trim();
      const marca = document.getElementById('pd_marca').value.trim();
      const modelo = document.getElementById('pd_modelo').value.trim();
      const mac = document.getElementById('pd_mac').value.trim();
      const edificioId = Number(document.getElementById('f_edificio')?.value || 0) || unidadDefaultEdificioId;
      if (!edificioId){
        Swal.fire({icon:'warning', title:'Falta edificio base', text:'No hay edificio cargado para asociar el equipo.'});
        return;
      }
      if (!owner){
        Swal.fire({icon:'warning', title:'Falta dueno', text:'Carga el dueno del equipo personal.'});
        return;
      }
      if (!marca && !modelo && !mac){
        Swal.fire({icon:'warning', title:'Faltan datos del equipo', text:'Carga al menos marca, modelo o MAC.'});
        return;
      }
      const ownerDni = document.getElementById('pd_propietario_dni').value.trim();
      const desc = `${tipo} personal ${owner}`.trim();
      const generatedName = '';
      const payload = {
        id: Number(document.getElementById('pd_id').value || 0),
        edificio_id: edificioId,
        categoria: 'informatica',
        tipo: ['NOTEBOOK','PC'].includes(tipo) ? 'pc' : 'otro',
        dispositivo_tipo: tipoDb,
        etiqueta: '',
        descripcion: desc,
        marca,
        modelo,
        nro_serie: document.getElementById('pd_nro_serie').value.trim(),
        estado: 'operativo',
        condicion: 'activo',
        fecha_alta: '',
        ubicacion_detalle: document.getElementById('pd_ubicacion').value.trim(),
        area_id: Number(document.getElementById('pd_area_id').value || 0),
        asignado_personal_id: personalId,
        observaciones: '',
        equipo_nombre: generatedName,
        usuario_asignado: '',
        sistema_operativo: document.getElementById('pd_sistema_operativo').value.trim(),
        cpu: document.getElementById('pd_cpu').value.trim(),
        ram_gb: document.getElementById('pd_ram_gb').value.trim(),
        disco_tipo: document.getElementById('pd_disco_tipo').value,
        disco_gb: document.getElementById('pd_disco_gb').value.trim(),
        monitor: '',
        perifericos: '',
        mac,
        ip: document.getElementById('pd_ip').value.trim(),
        ip_fija: 0,
        antivirus: '',
        office_version: '',
        serial_windows: '',
        ip_gateway: '',
        dns1: '',
        dns2: '',
        switch_puerto: '',
        patchera_puerto: '',
        sector_red: document.getElementById('pd_sector_red').value.trim(),
        vlan: '',
        propiedad: 'personal',
        propietario_nombre: owner,
        propietario_dni: ownerDni,
        autorizacion_estado: document.getElementById('pd_autorizacion_estado').value,
        autorizacion_fecha: document.getElementById('pd_autorizacion_fecha').value,
        autorizado_por: document.getElementById('pd_autorizado_por').value.trim(),
        autorizacion_observaciones: document.getElementById('pd_autorizacion_observaciones').value.trim()
      };
      try{
        await apiPost('activos_save', payload);
        personalDeviceModal.hide();
        await loadUnidadActivos();
        await loadAreaSummary();
        Swal.fire({icon:'success', title:'Dispositivo personal guardado', timer:1000, showConfirmButton:false});
      }catch(err){
        Swal.fire({icon:'error', title:'No se pudo guardar', text: err.message});
      }
    }

    function setTransferStatus(text, state=''){
      const el = document.getElementById('transferStatus');
      if (!el) return;
      el.textContent = text || '';
      el.style.color = state === 'error' ? '#fecaca' : (state === 'ok' ? '#bbf7d0' : '');
    }

    async function fillTransferControls(){
      try{
        const data = await apiGet({api:'transfer_options'});
        transferActivosCache = data.activos || [];
        transferPersonalCache = data.personal || [];

        const selA = document.getElementById('transfer_activo_id');
        if (selA){
          const cur = selA.value;
          selA.innerHTML = '<option value="0">Seleccionar equipo</option>';
          for (const r of transferActivosCache){
            const opt = document.createElement('option');
            opt.value = String(r.id);
            opt.dataset.equipo = r.equipo_nombre || '';
            opt.textContent = `${r.equipo_nombre || 'Sin nombre'}${r.asignado_label ? ' - ' + r.asignado_label : ' - Sin asignar'}`;
            selA.appendChild(opt);
          }
          selA.value = cur || '0';
        }

        const selP = document.getElementById('transfer_personal_id');
        if (selP){
          const cur = selP.value;
          selP.innerHTML = '<option value="0">Seleccionar personal</option>';
          for (const r of transferPersonalCache){
            const opt = document.createElement('option');
            opt.value = String(r.id);
            opt.textContent = r.label || (`ID ${r.id}`);
            selP.appendChild(opt);
          }
          selP.value = cur || '0';
        }
        setTransferStatus('');
      }catch(err){
        setTransferStatus('Error cargando listas: ' + err.message, 'error');
      }
    }

    async function transferActivoManual(){
      const activoId = Number(document.getElementById('transfer_activo_id')?.value || 0);
      const personalId = Number(document.getElementById('transfer_personal_id')?.value || 0);
      if (!activoId || !personalId){
        Swal.fire({icon:'warning', title:'Faltan datos', text:'Selecciona el equipo y el personal destino.'});
        return;
      }
      try{
        setTransferStatus('Transfiriendo...');
        const res = await apiPost('activos_transfer', {activo_id: activoId, destino_personal_id: personalId});
        setTransferStatus(`${res.equipo_nombre || 'Equipo'} transferido a ${res.destino_label || 'destino'}.`, 'ok');
        await fillTransferControls();
        await loadUnidadActivos();
      }catch(err){
        setTransferStatus('Error: ' + err.message, 'error');
        Swal.fire({icon:'error', title:'No se pudo transferir', text: err.message});
      }
    }

    let unidadRows = [];

    const unidadEquipoParts = (name) => {
      const order = {CE:1, CP:2, SE:3, RA:4, SW:5, EN:6, TI:7, AP:8, IM:9, PT:10};
      const m = String(name || '').toUpperCase().match(/^U2285-(CE|CP|SE|RA|SW|EN|TI|AP|IM|PT)-(\d{3})$/);
      if (!m) return {group: 99, num: 999999, name: String(name || '')};
      return {group: order[m[1]] || 99, num: Number(m[2]), name: String(name || '')};
    };

    const unidadJerarquiaOrden = (jerarquia) => ({OFICIAL:1, SUBOFICIAL:2, SOLDADO:3, AGENTE_CIVIL:4}[String(jerarquia || '').toUpperCase()] || 5);
    const unidadGradoOrden = (grado) => ({
      TG:9, GD:10, GB:11, CR:12, TC:13, MY:14, CT:15, TP:16, TT:17, ST:18,
      'ST EC':19, SM:20, SP:21, SA:22, SI:23, SG:24, CI:25, 'CI EC':26,
      'CI ART 11':27, CB:28, 'CB EC':29, 'CB ART 11':30, VP:31, VS:32,
      'VS EC':33, SV:34, AC:35
    }[String(grado || '').toUpperCase()] || 99);

    const unidadClaseEquipo = (name) => {
      const labels = {
        CE: 'Escritorio',
        CP: 'Portatil',
        SE: 'Servidor',
        RA: 'Rack',
        SW: 'Switch',
        EN: 'Enrutador',
        TI: 'Telefono IP',
        AP: 'AP',
        IM: 'Impresora',
        PT: 'Puesto'
      };
      const m = String(name || '').toUpperCase().match(/^U2285-(CE|CP|SE|RA|SW|EN|TI|AP|IM|PT)-/);
      if (!m) return '';
      return labels[m[1]] || m[1];
    };

    function getUnidadDisplayRows(){
      const q = (document.getElementById('f_q')?.value || '').trim().toLowerCase();
      let rows = unidadRows.slice();
      if (q){
        rows = rows.filter(r=>{
          const blob = [
            r.edificio_nombre, r.etiqueta, r.descripcion, r.tipo, r.dispositivo_tipo,
            r.marca, r.modelo, r.nro_serie, r.estado, r.condicion,
            r.area_nombre, r.asignado_label, r.asignado_dni,
            r.dependencia_nombre,
            r.equipo_nombre, r.usuario_asignado, r.asignado_usuario_intranet, r.asignado_usuario_gde,
            r.sistema_operativo, r.cpu, r.ram_gb, r.disco_tipo, r.disco_gb,
            r.monitor, r.perifericos,
            r.ip, r.mac,
            r.antivirus, r.office_version, r.serial_windows,
            r.ip_gateway, r.dns1, r.dns2, r.switch_puerto, r.patchera_puerto, r.sector_red, r.vlan
          ].join(' ').toLowerCase();
          return blob.includes(q);
        });
      }
      rows.sort((a,b) => {
        if (isUnidadGeneralPeopleView()){
          const hasPersonA = Number(a.asignado_personal_id || 0) > 0;
          const hasPersonB = Number(b.asignado_personal_id || 0) > 0;
          if (hasPersonA !== hasPersonB) return hasPersonA ? -1 : 1;
          if (hasPersonA && hasPersonB){
            const ja = unidadJerarquiaOrden(a.asignado_jerarquia);
            const jb = unidadJerarquiaOrden(b.asignado_jerarquia);
            if (ja !== jb) return ja - jb;
            const ga = unidadGradoOrden(a.asignado_grado);
            const gb = unidadGradoOrden(b.asignado_grado);
            if (ga !== gb) return ga - gb;
            const la = (a.asignado_label || a.equipo_nombre || '').replace(/^, /,'');
            const lb = (b.asignado_label || b.equipo_nombre || '').replace(/^, /,'');
            const nameCompare = la.localeCompare(lb, 'es');
            if (nameCompare !== 0) return nameCompare;
          }
        }
        const pa = unidadEquipoParts(a.equipo_nombre);
        const pb = unidadEquipoParts(b.equipo_nombre);
        if (pa.group !== pb.group) return pa.group - pb.group;
        if (pa.num !== pb.num) return pa.num - pb.num;
        const ja = unidadJerarquiaOrden(a.asignado_jerarquia);
        const jb = unidadJerarquiaOrden(b.asignado_jerarquia);
        if (ja !== jb) return ja - jb;
        const ga = unidadGradoOrden(a.asignado_grado);
        const gb = unidadGradoOrden(b.asignado_grado);
        if (ga !== gb) return ga - gb;
        return (pa.name || a.asignado_label || '').localeCompare((pb.name || b.asignado_label || ''), 'es');
      });
      return rows;
    }

    function getUnidadVisibleColumns(){
      return getUnidadColumns().filter(c => !hiddenUnidadColumns.has(c.key) && c.key !== 'estado');
    }

    function unidadExportValue(r, key){
      const ram = r.ram_gb ? String(r.ram_gb).replace(/\.00$/,'') : '';
      const discoGb = r.disco_gb ? String(r.disco_gb) : '';
      const values = {
        equipo: r.equipo_nombre || '',
        clase: unidadClaseEquipo(r.equipo_nombre) || r.dispositivo_tipo || (Number(r.sin_pc || 0) ? 'Sin PC' : ''),
        dependencia: r.dependencia_nombre || r.sector_red || '',
        subdependencia: r.area_nombre || '',
        asignado: (r.asignado_label || '').replace(/^, /,''),
        dni: r.asignado_dni || '',
        usuario_intranet: r.asignado_usuario_intranet || '',
        usuario_gde: r.asignado_usuario_gde || '',
        marca: r.marca || '',
        modelo: r.modelo || '',
        serie: r.nro_serie || '',
        ubicacion: r.ubicacion_detalle || '',
        so: r.sistema_operativo || '',
        cpu: r.cpu || '',
        ram,
        disco: [r.disco_tipo || '', discoGb].filter(Boolean).join(' '),
        ip: r.ip || '',
        mac: r.mac || '',
        monitor: r.monitor || '',
        perifericos: r.perifericos || '',
        observaciones: r.observaciones || ''
      };
      return values[key] ?? '';
    }

    async function loadUnidadActivos(){
      const tb = document.getElementById('tbUnidad');
      const empty = document.getElementById('unidadEmpty');
      if (!tb || !empty) return;
      tb.innerHTML = '';
      empty.style.display = 'none';

      const edificio_id = Number(document.getElementById('f_edificio')?.value || 0);
      const area_id = Number(document.getElementById('f_area')?.value || 0);
      const personal_id = Number(document.getElementById('f_personal')?.value || 0);
      const pc_code = (document.getElementById('f_pc_code')?.value || '').trim();
      const dependencia = (document.getElementById('f_dependencia')?.value || '').trim();
      const asignacion = (document.getElementById('f_asignacion')?.value || 'todos').trim();
      const personal_grupo = (document.getElementById('f_personal_grupo')?.value || '').trim();

      let data;
      try{
        data = await apiGet({
          api:'activos_list_all',
          edificio_id: edificio_id || 0,
          area_id: area_id || 0,
          personal_id: personal_id || 0,
          pc_code,
          dependencia,
          asignacion,
          personal_grupo
        });
      }catch(err){
        empty.style.display = '';
        empty.textContent = 'Error: ' + err.message;
        return;
      }

      unidadRows = data.rows || [];
      renderUnidadTable();
      renderPersonalDevices();
      fillLabelDeviceSelect();
    }

    function renderUnidadTable(){
      const tb = document.getElementById('tbUnidad');
      const empty = document.getElementById('unidadEmpty');
      const head = document.getElementById('unidadHeadRow');
      document.querySelectorAll('.column-toggle[data-col-key]').forEach(btn => {
        btn.classList.toggle('is-hidden', hiddenUnidadColumns.has(btn.dataset.colKey));
      });

      tb.innerHTML = '';
      if (head) {
        const cols = getUnidadColumns();
        head.innerHTML = cols
          .filter(c => !hiddenUnidadColumns.has(c.key))
          .map(c => `<th data-col="${escapeHtml(c.key)}">${escapeHtml(c.label)}</th>`)
          .join('');
      }
      empty.style.display = 'none';

      const rows = getUnidadDisplayRows();

      if (!rows.length){
        empty.style.display = '';
        empty.textContent = 'Sin personal o dispositivos para mostrar (o no hay coincidencias).';
        return;
      }

      for (const r of rows){
        const tr = document.createElement('tr');
        tr.classList.toggle('row-no-device', Number(r.sin_pc || 0) === 1);
        tr.classList.toggle('row-device-only', Number(r.asignado_personal_id || 0) === 0 && Number(r.id || 0) > 0);
        const rowId = `u_${String(r.id).replace(/[^a-zA-Z0-9_-]/g, '')}`;
        const ram = r.ram_gb ? String(r.ram_gb).replace(/\.00$/,'') : '';
        const discoGb = r.disco_gb ? String(r.disco_gb) : '';
        const cells = {
          equipo: `<td data-col="equipo"><input class="table-compact-input mono" data-field="equipo_nombre" value="${escapeHtml(r.equipo_nombre || '')}"></td>`,
          clase: `<td data-col="clase"><span class="badge-soft">${escapeHtml(unidadClaseEquipo(r.equipo_nombre) || r.dispositivo_tipo || (Number(r.sin_pc || 0) ? 'Sin PC' : ''))}</span></td>`,
          dependencia: `<td data-col="dependencia"><input class="table-compact-input" data-field="sector_red" value="${escapeHtml(r.dependencia_nombre || r.sector_red || '')}"></td>`,
          subdependencia: `<td data-col="subdependencia">
            <select class="table-compact-select" data-field="area_id">
              <option value="0">Sin area</option>
              ${areasCache.map(a => `<option value="${escapeHtml(String(a.id))}" ${Number(a.id)===Number(r.area_id||0) ? 'selected' : ''}>${escapeHtml(a.nombre || ('Area '+a.id))}</option>`).join('')}
            </select>
          </td>`,
          asignado: `<td data-col="asignado">${escapeHtml((r.asignado_label || '').replace(/^, /,''))}</td>`,
          dni: `<td data-col="dni" class="mono">${escapeHtml(r.asignado_dni || '')}</td>`,
          usuario_intranet: `<td data-col="usuario_intranet"><input class="table-compact-input" data-field="usuario_intranet" value="${escapeHtml(r.asignado_usuario_intranet || '')}"></td>`,
          usuario_gde: `<td data-col="usuario_gde"><input class="table-compact-input" data-field="usuario_gde" value="${escapeHtml(r.asignado_usuario_gde || '')}"></td>`,
          marca: `<td data-col="marca"><input class="table-compact-input" data-field="marca" value="${escapeHtml(r.marca || '')}"></td>`,
          modelo: `<td data-col="modelo"><input class="table-compact-input" data-field="modelo" value="${escapeHtml(r.modelo || '')}"></td>`,
          serie: `<td data-col="serie"><input class="table-compact-input" data-field="nro_serie" value="${escapeHtml(r.nro_serie || '')}"></td>`,
          ubicacion: `<td data-col="ubicacion"><input class="table-compact-input" data-field="ubicacion_detalle" value="${escapeHtml(r.ubicacion_detalle || '')}"></td>`,
          so: `<td data-col="so"><input class="table-compact-input" data-field="sistema_operativo" value="${escapeHtml(r.sistema_operativo || '')}"></td>`,
          cpu: `<td data-col="cpu"><input class="table-compact-input" data-field="cpu" value="${escapeHtml(r.cpu || '')}"></td>`,
          ram: `<td data-col="ram"><input class="table-compact-input mono" data-field="ram_gb" value="${escapeHtml(ram)}"></td>`,
          disco: `<td data-col="disco">
            <select class="table-compact-select" data-field="disco_tipo">
              ${['','HDD','SSD','NVME','EMMC','OTRO'].map(v => `<option value="${v}" ${String(r.disco_tipo||'')===v ? 'selected' : ''}>${v || '-'}</option>`).join('')}
            </select>
            <input class="table-compact-input mono mt-1" data-field="disco_gb" value="${escapeHtml(discoGb)}">
          </td>`,
          ip: `<td data-col="ip"><input class="table-compact-input mono" data-field="ip" value="${escapeHtml(r.ip || '')}"></td>`,
          mac: `<td data-col="mac"><input class="table-compact-input mono" data-field="mac" value="${escapeHtml(r.mac || '')}"></td>`,
          monitor: `<td data-col="monitor"><input class="table-compact-input" data-field="monitor" value="${escapeHtml(r.monitor || '')}"></td>`,
          perifericos: `<td data-col="perifericos"><input class="table-compact-input" data-field="perifericos" value="${escapeHtml(r.perifericos || '')}"></td>`,
          observaciones: `<td data-col="observaciones"><input class="table-compact-input" data-field="observaciones" value="${escapeHtml(r.observaciones || '')}"></td>`,
          estado: `<td data-col="estado"><span class="autosave-status">${Number(r.id || 0) > 0 ? 'Guardado' : 'Sin PC'}</span></td>`
        };
        tr.innerHTML = getUnidadColumns()
          .filter(c => !hiddenUnidadColumns.has(c.key))
          .map(c => cells[c.key] || '')
          .join('');
        tr.dataset.rowId = rowId;
        tr.querySelectorAll('[data-field]').forEach(el => {
          const eventName = el.tagName === 'SELECT' ? 'change' : 'input';
          el.addEventListener(eventName, () => scheduleUnidadAutosave(tr, r));
          if (['sector_red','area_id'].includes(el.dataset.field || '')) {
            el.addEventListener('blur', () => {
              clearTimeout(tr._autosaveTimer);
              saveUnidadRow(tr, r, {silent:true});
            });
          }
        });
        tb.appendChild(tr);
      }
      fixVisibleMojibake(tb);
    }

    function unidadExportTableHtml(rows, columns){
      const head = columns.map(c => `<th>${escapeHtml(c.label)}</th>`).join('');
      const body = rows.map(r => `<tr>${columns.map(c => `<td>${escapeHtml(unidadExportValue(r, c.key))}</td>`).join('')}</tr>`).join('');
      return `<table><thead><tr>${head}</tr></thead><tbody>${body}</tbody></table>`;
    }

    function unidadExportFileName(ext){
      const d = new Date();
      const pad = n => String(n).padStart(2, '0');
      return `inventario_informatica_${d.getFullYear()}${pad(d.getMonth()+1)}${pad(d.getDate())}_${pad(d.getHours())}${pad(d.getMinutes())}.${ext}`;
    }

    function downloadUnidadBlob(content, mime, fileName){
      try{
        const blob = new Blob([content], {type:mime});
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = fileName;
        document.body.appendChild(a);
        a.click();
        a.remove();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
      }catch(err){
        Swal.fire({icon:'error', title:'No se pudo exportar', text: err.message});
      }
    }

    function exportUnidadExcel(){
      const rows = getUnidadDisplayRows();
      if (!rows.length){
        Swal.fire({icon:'info', title:'Sin datos', text:'No hay filas visibles para exportar. Revisa los filtros o agrega el dispositivo primero.'});
        return;
      }
      const columns = getUnidadVisibleColumns();
      const html = `<!doctype html><html><head><meta charset="utf-8"></head><body>${unidadExportTableHtml(rows, columns)}</body></html>`;
      downloadUnidadBlob('\ufeff' + html, 'application/vnd.ms-excel', unidadExportFileName('xls'));
    }

    function exportUnidadPdf(){
      const rows = getUnidadDisplayRows();
      if (!rows.length){
        Swal.fire({icon:'info', title:'Sin datos', text:'No hay filas visibles para exportar. Revisa los filtros o agrega el dispositivo primero.'});
        return;
      }
      const columns = getUnidadVisibleColumns();
      const html = unidadExportTableHtml(rows, columns);
      const w = window.open('', '_blank');
      if (!w){
        Swal.fire({icon:'warning', title:'No se pudo abrir', text:'El navegador bloqueo la ventana de impresion.'});
        return;
      }
      w.document.write(`<!doctype html>
        <html>
        <head>
          <meta charset="utf-8">
          <title>Inventario informatica</title>
          <style>
            body{font-family:Arial,sans-serif;color:#111;margin:18px;}
            h1{font-size:18px;margin:0 0 4px;}
            .meta{font-size:11px;color:#555;margin-bottom:12px;}
            table{width:100%;border-collapse:collapse;font-size:9px;}
            th,td{border:1px solid #999;padding:4px 5px;vertical-align:top;}
            th{background:#e5e7eb;text-align:left;}
            @page{size:landscape;margin:10mm;}
          </style>
        </head>
        <body>
          <h1>Inventario informatica</h1>
          <div class="meta">Exportado: ${escapeHtml(new Date().toLocaleString('es-AR'))} - Filas: ${rows.length}</div>
          ${html}
          <script>window.onload=function(){window.print();};<\/script>
        </body>
        </html>`);
      w.document.close();
    }

    function unidadDeviceCode(r){
      const name = String(r.equipo_nombre || '').toUpperCase();
      return (name.match(/^U2285-(CE|CP|SE|RA|SW|EN|TI|AP|IM|PT)-/) || [])[1] || '';
    }

    function isLabelableRow(r){
      return Number(r.id || 0) > 0 && Boolean(String(r.equipo_nombre || r.dispositivo_tipo || r.marca || r.modelo || '').trim());
    }

    function labelableVisibleRows(){
      return getUnidadDisplayRows().filter(isLabelableRow);
    }

    function labelCleanPersonName(r){
      let name = String(r.asignado_label || '').replace(/^, /,'').trim();
      const grade = String(r.asignado_grado || '').trim();
      if (grade && name.toUpperCase().startsWith(grade.toUpperCase() + ' ')) {
        name = name.slice(grade.length).trim();
      }
      return name;
    }

    function labelPlace(r){
      const parts = [r.dependencia_nombre || r.sector_red || '', r.area_nombre || '', r.ubicacion_detalle || '']
        .map(v => String(v || '').trim())
        .filter(Boolean);
      return [...new Set(parts)].join(' / ');
    }

    function labelDeviceType(r){
      const code = unidadDeviceCode(r);
      const byCode = {
        CE:'Computadora de Escritorio',
        CP:'Computadora Portatil',
        SE:'Servidor',
        RA:'Rack',
        SW:'Switch',
        EN:'Enrutador',
        TI:'Telefono IP',
        AP:'Enrutador Inalambrico',
        IM:'Impresora de Red',
        PT:'Puesto de Trabajo'
      };
      return byCode[code] || r.dispositivo_tipo || unidadClaseEquipo(r.equipo_nombre) || 'Dispositivo';
    }

    function labelDisco(r){
      const tipo = String(r.disco_tipo || '').trim();
      const gb = String(r.disco_gb || '').replace(/\.00$/,'').trim();
      return [tipo, gb ? `${gb} GB` : ''].filter(Boolean).join(' ');
    }

    function labelUsuario(r){
      const intranet = String(r.asignado_usuario_intranet || r.usuario_asignado || '').trim();
      const gde = String(r.asignado_usuario_gde || '').trim();
      if (intranet && gde && intranet.toUpperCase() !== gde.toUpperCase()) return `${intranet} / ${gde}`;
      return intranet || gde || '';
    }

    function labelRowsForDevice(r){
      const code = unidadDeviceCode(r);
      const isPc = ['CE','CP'].includes(code) || ['PC','NOTEBOOK'].includes(String(r.dispositivo_tipo || '').toUpperCase());
      if (isPc) {
        return [
          ['LUGAR', labelPlace(r)],
          ['GRADO', r.asignado_grado || ''],
          ['NOMBRE Y APELLIDO', labelCleanPersonName(r)],
          ['NOMBRE PC', r.equipo_nombre || ''],
          ['USUARIO', labelUsuario(r)],
          ['SISTEMA OPERATIVO', r.sistema_operativo || ''],
          ['MICRO', r.cpu || ''],
          ['RAM', r.ram_gb ? `${String(r.ram_gb).replace(/\.00$/,'')} GB` : ''],
          ['DISCO', labelDisco(r)]
        ];
      }
      return [
        ['LUGAR', labelPlace(r)],
        ['TIPO', labelDeviceType(r)],
        ['NOMBRE / CODIGO', r.equipo_nombre || r.etiqueta || ''],
        ['MARCA', r.marca || ''],
        ['MODELO', r.modelo || ''],
        ['SERIE', r.nro_serie || ''],
        ['IP', r.ip || ''],
        ['MAC', r.mac || ''],
        ['UBICACION', r.ubicacion_detalle || r.area_nombre || ''],
        ['OBSERVACIONES', r.observaciones || '']
      ];
    }

    function labelHtml(r){
      const rows = labelRowsForDevice(r)
        .map(([k,v]) => `<tr><td class="label-key" contenteditable="true">${escapeHtml(k)}</td><td contenteditable="true">${escapeHtml(v || '')}</td></tr>`)
        .join('');
      return `<table class="asset-label"><tbody>${rows}<tr><td colspan="2" class="label-blank" contenteditable="true">&nbsp;</td></tr></tbody></table>`;
    }

    function labelsPagesHtml(rows){
      const pages = [];
      for (let i = 0; i < rows.length; i += 2){
        const first = labelHtml(rows[i]);
        const second = rows[i + 1] ? labelHtml(rows[i + 1]) : '<div class="asset-label label-placeholder"></div>';
        pages.push(`<section class="label-page">${first}${second}</section>`);
      }
      return pages.join('');
    }

    function fillLabelDeviceSelect(){
      const selects = [document.getElementById('label_activo_id'), document.getElementById('label_activo_id_2')].filter(Boolean);
      if (!selects.length) return;
      const currents = new Map(selects.map(sel => [sel.id, sel.value]));
      const rows = unidadRows.filter(isLabelableRow);
      for (const sel of selects){
        sel.innerHTML = '<option value="0">Seleccionar equipo o dispositivo</option>';
        for (const r of rows){
          const opt = document.createElement('option');
          opt.value = String(r.id);
          const owner = labelCleanPersonName(r);
          opt.textContent = [r.equipo_nombre || r.etiqueta || `ID ${r.id}`, labelDeviceType(r), owner].filter(Boolean).join(' - ');
          sel.appendChild(opt);
        }
        const current = currents.get(sel.id);
        if ([...sel.options].some(o => o.value === current)) sel.value = current;
      }
    }

    function printLabels(rows){
      rows = rows.filter(isLabelableRow);
      if (!rows.length){
        Swal.fire({icon:'info', title:'Sin datos', text:'No hay equipos o dispositivos visibles para generar carteles.'});
        return;
      }
      const w = window.open('', '_blank');
      if (!w){
        Swal.fire({icon:'warning', title:'No se pudo abrir', text:'El navegador bloqueo la ventana de impresion.'});
        return;
      }
      const pages = labelsPagesHtml(rows);
      w.document.write(`<!doctype html>
        <html>
        <head>
          <meta charset="utf-8">
          <title>Carteles inventario</title>
          <style>
            *{box-sizing:border-box;}
            body{font-family:Arial,Helvetica,sans-serif;color:#000;margin:0;background:#f3f4f6;}
            .print-toolbar{position:sticky;top:0;z-index:10;display:flex;align-items:center;gap:10px;padding:10px 12px;background:#111827;color:#fff;box-shadow:0 2px 10px rgba(0,0,0,.18);}
            .print-toolbar button{border:0;border-radius:8px;background:#2563eb;color:#fff;font-weight:700;padding:8px 14px;cursor:pointer;}
            .print-toolbar span{font-size:12px;color:#d1d5db;}
            .label-page{width:190mm;min-height:277mm;margin:10mm auto;background:#fff;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:24mm;padding:0;page-break-after:always;break-after:page;}
            .asset-label{width:100mm;height:100mm;border-collapse:collapse;table-layout:fixed;break-inside:avoid;page-break-inside:avoid;font-size:8.5pt;line-height:1.12;margin:0;background:#fff;}
            .asset-label td{border:1px solid #000;padding:1.6mm 2mm;vertical-align:top;outline:none;overflow:hidden;}
            .asset-label .label-key{width:42%;font-weight:400;}
            .asset-label .label-blank{height:10mm;}
            .label-placeholder{border:0;width:100mm;height:100mm;}
            [contenteditable="true"]:focus{box-shadow:inset 0 0 0 2px #2563eb;}
            @media print{
              body{margin:0;background:#fff;}
              .print-toolbar{display:none;}
              .label-page{width:190mm;min-height:277mm;margin:0;box-shadow:none;gap:24mm;}
              .label-page:last-child{page-break-after:auto;break-after:auto;}
              @page{size:A4 portrait;margin:10mm;}
            }
          </style>
        </head>
        <body>
          <div class="print-toolbar">
            <button type="button" onclick="window.print()">Imprimir</button>
            <span>Hace click en cualquier celda para editar antes de imprimir. Salen 2 carteles de 10 x 10 cm por hoja, centrados para recortar.</span>
          </div>
          ${pages}
        </body>
        </html>`);
      w.document.close();
    }

    function printSelectedLabel(){
      const ids = [
        Number(document.getElementById('label_activo_id')?.value || 0),
        Number(document.getElementById('label_activo_id_2')?.value || 0)
      ].filter(Boolean);
      const uniqueIds = [...new Set(ids)];
      const rows = uniqueIds
        .map(id => unidadRows.find(r => Number(r.id || 0) === id))
        .filter(Boolean);
      if (!rows.length){
        Swal.fire({icon:'info', title:'Selecciona un equipo', text:'Elegi una computadora o dispositivo en Cartel 1 o Cartel 2.'});
        return;
      }
      printLabels(rows);
    }

    function printVisibleLabels(){
      printLabels(labelableVisibleRows());
    }

    function setAutosaveStatus(tr, text, state=''){
      const el = tr.querySelector('.autosave-status');
      if (!el) return;
      el.className = 'autosave-status' + (state ? ' ' + state : '');
      el.textContent = text;
    }

    function scheduleUnidadAutosave(tr, original){
      clearTimeout(tr._autosaveTimer);
      setAutosaveStatus(tr, 'Pendiente', 'saving');
      tr._autosaveTimer = setTimeout(() => saveUnidadRow(tr, original, {silent:true}), 650);
    }

    async function saveUnidadRow(tr, original, options = {}){
      const value = (field) => tr.querySelector(`[data-field="${field}"]`)?.value?.trim() ?? '';
      const equipo = value('equipo_nombre');
      const equipoUpper = equipo.toUpperCase();
      const deviceCode = (equipoUpper.match(/^U2285-(CE|CP|SE|RA|SW|EN|TI|AP|IM|PT)-/) || [])[1] || '';
      const deviceByCode = {
        CE: 'PC',
        CP: 'NOTEBOOK',
        SE: 'SERVIDOR',
        RA: 'RACK',
        SW: 'SWITCH',
        EN: 'ROUTER',
        TI: 'TELEFONO IP',
        AP: 'AP',
        IM: 'IMPRESORA',
        PT: 'PUESTO'
      };
      const dispositivo = deviceByCode[deviceCode] || original.dispositivo_tipo || 'OTRO';
      const usuarioIntranet = value('usuario_intranet');
      const usuarioGde = value('usuario_gde');
      const assetId = Number(original.id || 0);
      const shouldSaveAsset = assetId > 0 || equipo !== '';
      const payload = {
        id: assetId,
        edificio_id: Number(original.edificio_id || document.getElementById('f_edificio')?.value || 0),
        categoria: original.categoria || 'informatica',
        tipo: original.tipo || 'pc',
        dispositivo_tipo: dispositivo,
        etiqueta: equipo,
        descripcion: equipo,
        marca: value('marca') || original.marca || '',
        modelo: value('modelo') || original.modelo || '',
        nro_serie: value('nro_serie') || original.nro_serie || '',
        estado: original.estado || 'operativo',
        condicion: original.condicion || 'activo',
        fecha_alta: original.fecha_alta || '',
        ubicacion_detalle: value('ubicacion_detalle') || original.ubicacion_detalle || '',
        area_id: Number(value('area_id') || 0),
        asignado_personal_id: Number(original.asignado_personal_id || 0),
        observaciones: value('observaciones'),
        equipo_nombre: equipo,
        usuario_asignado: [usuarioIntranet, usuarioGde].filter(Boolean).join(' / '),
        sistema_operativo: value('sistema_operativo'),
        cpu: value('cpu'),
        ram_gb: value('ram_gb'),
        disco_tipo: value('disco_tipo'),
        disco_gb: value('disco_gb'),
        monitor: value('monitor'),
        perifericos: value('perifericos'),
        mac: value('mac'),
        ip: value('ip'),
        ip_fija: Number(original.ip_fija || 0),
        antivirus: original.antivirus || '',
        office_version: original.office_version || '',
        serial_windows: original.serial_windows || '',
        ip_gateway: original.ip_gateway || '',
        dns1: original.dns1 || '',
        dns2: original.dns2 || '',
        switch_puerto: original.switch_puerto || '',
        patchera_puerto: original.patchera_puerto || '',
        sector_red: value('sector_red'),
        vlan: original.vlan || '',
        propiedad: original.propiedad || 'unidad',
        propietario_nombre: original.propietario_nombre || '',
        propietario_dni: original.propietario_dni || '',
        autorizacion_estado: original.autorizacion_estado || 'pendiente',
        autorizacion_fecha: original.autorizacion_fecha || '',
        autorizado_por: original.autorizado_por || '',
        autorizacion_observaciones: original.autorizacion_observaciones || ''
      };

      if (shouldSaveAsset && !payload.edificio_id){
        setAutosaveStatus(tr, 'Error', 'error');
        if (!options.silent) Swal.fire({icon:'warning', title:'Falta edificio', text:'Este activo no tiene edificio asociado.'});
        return;
      }
      if (shouldSaveAsset && !payload.equipo_nombre && !payload.descripcion){
        setAutosaveStatus(tr, 'Error', 'error');
        if (!options.silent) Swal.fire({icon:'warning', title:'Falta nombre', text:'Carga al menos el Nombre PC.'});
        return;
      }

      try{
        setAutosaveStatus(tr, 'Guardando...', 'saving');
        let savedAsset = null;
        if (shouldSaveAsset) {
          savedAsset = await apiPost('activos_save', payload);
        }
        if (Number(original.asignado_personal_id || 0) > 0) {
          await apiPost('personal_usuarios_save', {
            personal_id: Number(original.asignado_personal_id),
            usuario_intranet: usuarioIntranet,
            usuario_gde: usuarioGde
          });
          await apiPost('personal_ubicacion_save', {
            personal_id: Number(original.asignado_personal_id),
            area_id: Number(payload.area_id || 0),
            dependencia: payload.sector_red
          });
        }
        if (shouldSaveAsset && savedAsset?.id) payload.id = Number(savedAsset.id);
        Object.assign(original, payload);
        original.dependencia_nombre = payload.sector_red;
        const selectedArea = areasCache.find(a => Number(a.id) === Number(payload.area_id || 0));
        original.area_nombre = selectedArea ? (selectedArea.nombre || '') : '';
        original.asignado_usuario_intranet = usuarioIntranet;
        original.asignado_usuario_gde = usuarioGde;
        original.sin_pc = shouldSaveAsset ? 0 : original.sin_pc;
        setAutosaveStatus(tr, 'Guardado', 'saved');
        if (assetId === 0 && shouldSaveAsset) await loadUnidadActivos();
      }catch(err){
        setAutosaveStatus(tr, 'Error', 'error');
        if (!options.silent) Swal.fire({icon:'error', title:'Error', text: err.message});
      }
    }

    /* =========================
       MODO EDIFICIO (CRUD)
    ========================= */
    const mdlActivo = MODO_EDIFICIO ? new bootstrap.Modal(document.getElementById('mdlActivo')) : null;
    const mdlInternet = MODO_EDIFICIO ? new bootstrap.Modal(document.getElementById('mdlInternet')) : null;
    const mdlMant = MODO_EDIFICIO ? new bootstrap.Modal(document.getElementById('mdlMant')) : null;

    async function loadAreasAndPersonal(){
      try{ const ar = await apiGet({api:'areas'}); areasCache = ar.rows || []; }catch(_){ areasCache = []; }
      try{ const pe = await apiGet({api:'personal'}); personalCache = pe.rows || []; }catch(_){ personalCache = []; }

      const selA = document.getElementById('a_area_id');
      if (selA){
        const cur = selA.value;
        selA.innerHTML = `<option value="0">Sin area</option>`;
        for (const r of areasCache){
          const opt = document.createElement('option');
          opt.value = String(r.id);
          opt.textContent = r.nombre || ('Area ' + r.id);
          selA.appendChild(opt);
        }
        selA.value = cur || '0';
      }

      const selP = document.getElementById('a_asignado_personal_id');
      if (selP){
        const cur = selP.value;
        selP.innerHTML = `<option value="0">Sin asignar</option>`;
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
          <td class="mono">${escapeHtml(r.ip || '')}${Number(r.ip_fija||0)===1 ? ' fija' : ''}</td>
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
      // ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¦ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¦ Limpia campos no aplicables (sin rellenar datos)
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
        Swal.fire({icon:'warning', title:'Falta descripciÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³n', text:'CargÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡ al menos ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œDescripciÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³nÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â o ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œEtiquetaÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â.'});
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
        html:`ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¿Eliminar <b>${escapeHtml(r.descripcion || r.etiqueta || '')}</b>?`,
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
        Swal.fire({icon:'warning', title:'Falta el proveedor', text:'El campo ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œProveedorÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â es obligatorio.'});
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
        html:`ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¿Eliminar <b>${escapeHtml(r.proveedor || '')}</b>?`,
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
      sel.innerHTML = `<option value="0">ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â Sin asociar ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â</option>`;
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
        html:`ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¿Eliminar el registro del <b>${escapeHtml(r.fecha || '')}</b>?`,
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
        const asignacionSelect = document.getElementById('f_asignacion');
        if (asignacionSelect) asignacionSelect.value = 'todos';
        renderColumnToolbar();
        await fillUnitFilters();
        await fillTransferControls();
        await loadAreaSummary();
        await loadUnidadActivos();

        document.getElementById('btnReloadUnit')?.addEventListener('click', loadUnidadActivos);
        document.getElementById('btnReloadAreaSummary')?.addEventListener('click', loadAreaSummary);
        document.getElementById('btnAddUnidadDevice')?.addEventListener('click', openUnidadDeviceModal);
        document.getElementById('btnSaveUnidadDevice')?.addEventListener('click', saveUnidadDevice);
        document.getElementById('btnAddPersonalDevice')?.addEventListener('click', () => openPersonalDeviceModal());
        document.getElementById('btnSavePersonalDevice')?.addEventListener('click', savePersonalDevice);
        document.getElementById('pd_personal_id')?.addEventListener('change', () => syncPersonalOwnerFromSelect(true));
        document.getElementById('pd_dispositivo_tipo')?.addEventListener('change', applyPersonalDeviceUi);
        document.getElementById('areaSummaryPanel')?.addEventListener('shown.bs.collapse', loadAreaSummary);
        document.getElementById('personalDevicesPanel')?.addEventListener('shown.bs.collapse', renderPersonalDevices);
        document.getElementById('ud_code')?.addEventListener('change', () => {
          document.getElementById('ud_equipo_nombre').value = nextUnidadDeviceName(document.getElementById('ud_code').value);
        });
        document.getElementById('btnReloadTransfer')?.addEventListener('click', fillTransferControls);
        document.getElementById('btnTransferActivo')?.addEventListener('click', transferActivoManual);
        document.getElementById('transferPanel')?.addEventListener('shown.bs.collapse', fillTransferControls);
        document.getElementById('btnExportExcel')?.addEventListener('click', exportUnidadExcel);
        document.getElementById('btnExportPdf')?.addEventListener('click', exportUnidadPdf);
        document.getElementById('btnPrintOneLabel')?.addEventListener('click', printSelectedLabel);
        document.getElementById('btnPrintVisibleLabels')?.addEventListener('click', printVisibleLabels);
        document.getElementById('btnApplyFilters')?.addEventListener('click', loadUnidadActivos);
        document.getElementById('btnClearFilters')?.addEventListener('click', () => {
          document.getElementById('f_asignacion').value = 'todos';
          document.getElementById('f_edificio').value = '0';
          document.getElementById('f_pc_code').value = '';
          document.getElementById('f_personal_grupo').value = '';
          document.getElementById('f_dependencia').value = '';
          document.getElementById('f_area').value = '0';
          document.getElementById('f_personal').value = '0';
          document.getElementById('f_q').value = '';
          loadUnidadActivos();
        });
        document.getElementById('f_pc_code')?.addEventListener('change', () => {
          renderColumnToolbar();
          renderUnidadTable();
        });
        document.getElementById('f_q')?.addEventListener('input', renderUnidadTable);
        fixVisibleMojibake();
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
      fixVisibleMojibake();
    });
  </script>
</body>
</html>
