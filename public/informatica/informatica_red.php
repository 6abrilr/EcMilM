<?php
declare(strict_types=1);

require_once __DIR__ . '/../../auth/bootstrap.php';
require_login();
require_once __DIR__ . '/../../config/db.php';

function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function red_table_exists(PDO $pdo, string $table): bool {
    $st = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t");
    $st->execute([':t' => $table]);
    return (int)$st->fetchColumn() > 0;
}
function red_asset_path(string $archivo): ?string {
    $archivo = basename(trim($archivo));
    if ($archivo === '') return null;
    $candidates = [
        __DIR__ . '/../../storage/unidades/ecmilm/INFORMATICA/red/planos/' . $archivo,
        __DIR__ . '/../../storage/red/planos/' . $archivo,
    ];
    foreach ($candidates as $path) {
        $real = realpath($path);
        if ($real && is_file($real)) return $real;
    }
    return null;
}
function red_planos_dir(): string {
    return dirname(__DIR__, 2) . '/storage/unidades/ecmilm/INFORMATICA/red/planos';
}
function red_allowed_ext(string $name): string {
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    return in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'pdf'], true) ? $ext : '';
}
function red_send_plano(PDO $pdo, int $planoId, int $unidadId): void {
    $st = $pdo->prepare("
        SELECT p.archivo
        FROM red_planos p
        INNER JOIN red_pisos pi ON pi.id = p.piso_id
        INNER JOIN red_edificios e ON e.id = pi.edificio_id
        WHERE p.id = :id AND (e.unidad_id = :uid OR e.unidad_id IS NULL)
        LIMIT 1
    ");
    $st->execute([':id' => $planoId, ':uid' => $unidadId]);
    $archivo = (string)($st->fetchColumn() ?: '');
    $path = red_asset_path($archivo);
    if (!$path) { http_response_code(404); exit('Plano no encontrado.'); }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $mime = match ($ext) { 'jpg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'pdf' => 'application/pdf', default => 'image/png' };
    header('Content-Type: ' . $mime);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=3600');
    readfile($path);
    exit;
}
function red_plano_row(PDO $pdo, int $planoId, int $unidadId): ?array {
    $st = $pdo->prepare("
        SELECT p.id, p.piso_id, p.ancho, p.alto, pi.edificio_id
        FROM red_planos p
        INNER JOIN red_pisos pi ON pi.id = p.piso_id
        INNER JOIN red_edificios e ON e.id = pi.edificio_id
        WHERE p.id = :id AND (e.unidad_id = :uid OR e.unidad_id IS NULL)
        LIMIT 1
    ");
    $st->execute([':id' => $planoId, ':uid' => $unidadId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}
function red_redirect(int $edificioId, int $planoId): void {
    header('Location: informatica_red.php?edificio_id=' . $edificioId . '&plano_id=' . $planoId);
    exit;
}

$user = function_exists('current_user') ? current_user() : ($_SESSION['user'] ?? []);
$unidadId = function_exists('unidad_activa_id') ? unidad_activa_id() : (int)($user['unidad_id'] ?? 1);
if (isset($_GET['plano_img'])) red_send_plano($pdo, (int)$_GET['plano_img'], $unidadId);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_verify();
    $accion = (string)($_POST['accion'] ?? '');
    if ($accion === 'crear_edificio') {
        $nombreEdificio = trim((string)($_POST['edificio_nombre'] ?? ''));
        $numeroEdificio = trim((string)($_POST['edificio_numero'] ?? ''));
        $descripcionEdificio = trim((string)($_POST['edificio_descripcion'] ?? ''));
        $pisoNombre = trim((string)($_POST['piso_nombre'] ?? 'PB'));
        $anchoManual = max(1, (int)($_POST['ancho'] ?? 1280));
        $altoManual = max(1, (int)($_POST['alto'] ?? 900));
        if ($nombreEdificio === '') { http_response_code(400); exit('El nombre del edificio es obligatorio.'); }
        if ($pisoNombre === '') $pisoNombre = 'PB';

        $file = $_FILES['plano_archivo'] ?? null;
        if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            http_response_code(400);
            exit('Selecciona un plano inicial para el edificio.');
        }
        $ext = red_allowed_ext((string)$file['name']);
        if ($ext === '') { http_response_code(400); exit('Formato no permitido. Usa PDF, PNG, JPG o WEBP.'); }
        $tmp = (string)$file['tmp_name'];
        $size = (int)($file['size'] ?? 0);
        if ($size <= 0 || $size > 25 * 1024 * 1024) { http_response_code(400); exit('Archivo invalido o demasiado grande.'); }

        $dimW = $anchoManual;
        $dimH = $altoManual;
        if ($ext !== 'pdf') {
            $info = @getimagesize($tmp);
            if (!is_array($info)) { http_response_code(400); exit('La imagen no es valida.'); }
            $dimW = max(1, (int)$info[0]);
            $dimH = max(1, (int)$info[1]);
        }

        $dir = red_planos_dir();
        if (!is_dir($dir) && !mkdir($dir, 0775, true)) { http_response_code(500); exit('No se pudo crear la carpeta de planos.'); }
        $filename = 'ed_new_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest = $dir . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($tmp, $dest)) { http_response_code(500); exit('No se pudo guardar el plano.'); }

        $pdo->beginTransaction();
        $st = $pdo->prepare("INSERT INTO red_edificios (unidad_id, numero, nombre, descripcion) VALUES (:uid, :numero, :nombre, :descripcion)");
        $st->execute([
            ':uid' => $unidadId,
            ':numero' => $numeroEdificio !== '' ? (int)$numeroEdificio : null,
            ':nombre' => $nombreEdificio,
            ':descripcion' => $descripcionEdificio !== '' ? $descripcionEdificio : null,
        ]);
        $nuevoEdificioId = (int)$pdo->lastInsertId();
        $st = $pdo->prepare("INSERT INTO red_pisos (edificio_id, nombre) VALUES (:edificio, :nombre)");
        $st->execute([':edificio' => $nuevoEdificioId, ':nombre' => $pisoNombre]);
        $nuevoPisoId = (int)$pdo->lastInsertId();
        $filenameFinal = preg_replace('/^ed_new_/', 'ed_' . $nuevoEdificioId . '_', $filename);
        @rename($dest, $dir . DIRECTORY_SEPARATOR . $filenameFinal);
        $st = $pdo->prepare("INSERT INTO red_planos (piso_id, archivo, ancho, alto) VALUES (:piso, :archivo, :ancho, :alto)");
        $st->execute([':piso' => $nuevoPisoId, ':archivo' => $filenameFinal, ':ancho' => $dimW, ':alto' => $dimH]);
        $nuevoPlanoId = (int)$pdo->lastInsertId();
        $pdo->commit();
        red_redirect($nuevoEdificioId, $nuevoPlanoId);
    }

    if ($accion === 'subir_plano') {
        $edificioUploadId = (int)($_POST['edificio_id'] ?? 0);
        $pisoId = (int)($_POST['piso_id'] ?? 0);
        $pisoNuevo = trim((string)($_POST['piso_nuevo'] ?? ''));
        $reemplazarId = (int)($_POST['reemplazar_plano_id'] ?? 0);
        $anchoManual = max(1, (int)($_POST['ancho'] ?? 1280));
        $altoManual = max(1, (int)($_POST['alto'] ?? 900));

        $stEd = $pdo->prepare("SELECT id FROM red_edificios WHERE id = :id AND (unidad_id = :uid OR unidad_id IS NULL) LIMIT 1");
        $stEd->execute([':id' => $edificioUploadId, ':uid' => $unidadId]);
        if (!$stEd->fetchColumn()) { http_response_code(400); exit('Edificio invalido.'); }

        if ($pisoNuevo !== '') {
            $st = $pdo->prepare("INSERT INTO red_pisos (edificio_id, nombre) VALUES (:edificio, :nombre)");
            $st->execute([':edificio' => $edificioUploadId, ':nombre' => $pisoNuevo]);
            $pisoId = (int)$pdo->lastInsertId();
        } else {
            $stPiso = $pdo->prepare("SELECT id FROM red_pisos WHERE id = :id AND edificio_id = :edificio LIMIT 1");
            $stPiso->execute([':id' => $pisoId, ':edificio' => $edificioUploadId]);
            if (!$stPiso->fetchColumn()) { http_response_code(400); exit('Piso invalido.'); }
        }

        $file = $_FILES['plano_archivo'] ?? null;
        if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            http_response_code(400);
            exit('Selecciona un archivo de plano.');
        }
        $ext = red_allowed_ext((string)$file['name']);
        if ($ext === '') { http_response_code(400); exit('Formato no permitido. Usa PDF, PNG, JPG o WEBP.'); }
        $tmp = (string)$file['tmp_name'];
        $size = (int)($file['size'] ?? 0);
        if ($size <= 0 || $size > 25 * 1024 * 1024) { http_response_code(400); exit('Archivo invalido o demasiado grande.'); }

        $dimW = $anchoManual;
        $dimH = $altoManual;
        if ($ext !== 'pdf') {
            $info = @getimagesize($tmp);
            if (!is_array($info)) { http_response_code(400); exit('La imagen no es valida.'); }
            $dimW = max(1, (int)$info[0]);
            $dimH = max(1, (int)$info[1]);
        }

        $dir = red_planos_dir();
        if (!is_dir($dir) && !mkdir($dir, 0775, true)) { http_response_code(500); exit('No se pudo crear la carpeta de planos.'); }
        $filename = 'ed_' . $edificioUploadId . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest = $dir . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($tmp, $dest)) { http_response_code(500); exit('No se pudo guardar el plano.'); }

        if ($reemplazarId > 0) {
            $row = red_plano_row($pdo, $reemplazarId, $unidadId);
            if (!$row || (int)$row['edificio_id'] !== $edificioUploadId) { http_response_code(400); exit('Plano a reemplazar invalido.'); }
            $st = $pdo->prepare("UPDATE red_planos SET piso_id = :piso, archivo = :archivo, ancho = :ancho, alto = :alto WHERE id = :id LIMIT 1");
            $st->execute([':piso' => $pisoId, ':archivo' => $filename, ':ancho' => $dimW, ':alto' => $dimH, ':id' => $reemplazarId]);
            red_redirect($edificioUploadId, $reemplazarId);
        }

        $st = $pdo->prepare("INSERT INTO red_planos (piso_id, archivo, ancho, alto) VALUES (:piso, :archivo, :ancho, :alto)");
        $st->execute([':piso' => $pisoId, ':archivo' => $filename, ':ancho' => $dimW, ':alto' => $dimH]);
        red_redirect($edificioUploadId, (int)$pdo->lastInsertId());
    }

    $postPlanoId = (int)($_POST['plano_id'] ?? 0);
    $planoRow = $postPlanoId > 0 ? red_plano_row($pdo, $postPlanoId, $unidadId) : null;
    if (!$planoRow) { http_response_code(400); exit('Plano invalido.'); }
    $postEdificioId = (int)$planoRow['edificio_id'];
    $maxX = max(1, (int)($planoRow['ancho'] ?? 1280));
    $maxY = max(1, (int)($planoRow['alto'] ?? 720));
    $x = max(0, min($maxX, (float)($_POST['x'] ?? 0)));
    $y = max(0, min($maxY, (float)($_POST['y'] ?? 0)));

    if ($accion === 'crear_punto') {
        $tipo = trim((string)($_POST['tipo'] ?? 'pc'));
        $nombre = trim((string)($_POST['nombre'] ?? ''));
        $ip = trim((string)($_POST['ip'] ?? ''));
        $mac = trim((string)($_POST['mac'] ?? ''));
        $nota = trim((string)($_POST['nota'] ?? ''));
        if ($nombre === '') { http_response_code(400); exit('El nombre es obligatorio.'); }
        if (!in_array($tipo, ['pc','notebook','impresora','switch','router','ap','servidor','otro'], true)) $tipo = 'pc';
        $pdo->beginTransaction();
        $st = $pdo->prepare("INSERT INTO red_dispositivos (piso_id, tipo, nombre, ip, mac, nota) VALUES (:piso, :tipo, :nombre, :ip, :mac, :nota)");
        $st->execute([
            ':piso' => (int)$planoRow['piso_id'],
            ':tipo' => $tipo,
            ':nombre' => $nombre,
            ':ip' => $ip !== '' ? $ip : null,
            ':mac' => $mac !== '' ? $mac : null,
            ':nota' => $nota !== '' ? $nota : null,
        ]);
        $dispositivoId = (int)$pdo->lastInsertId();
        $st = $pdo->prepare("INSERT INTO red_posiciones (dispositivo_id, plano_id, x, y, rot) VALUES (:id, :plano, :x, :y, 0)");
        $st->execute([':id' => $dispositivoId, ':plano' => $postPlanoId, ':x' => $x, ':y' => $y]);
        $pdo->commit();
        red_redirect($postEdificioId, $postPlanoId);
    }

    if ($accion === 'editar_punto') {
        $dispositivoId = (int)($_POST['dispositivo_id'] ?? 0);
        $tipo = trim((string)($_POST['tipo'] ?? 'pc'));
        $nombre = trim((string)($_POST['nombre'] ?? ''));
        $ip = trim((string)($_POST['ip'] ?? ''));
        $mac = trim((string)($_POST['mac'] ?? ''));
        $nota = trim((string)($_POST['nota'] ?? ''));
        if ($dispositivoId <= 0 || $nombre === '') { http_response_code(400); exit('Datos invalidos.'); }
        if (!in_array($tipo, ['pc','notebook','impresora','switch','router','ap','servidor','otro'], true)) $tipo = 'pc';
        $st = $pdo->prepare("
            UPDATE red_dispositivos
            SET tipo = :tipo, nombre = :nombre, ip = :ip, mac = :mac, nota = :nota
            WHERE id = :id AND EXISTS (
                SELECT 1 FROM red_posiciones p WHERE p.dispositivo_id = red_dispositivos.id AND p.plano_id = :plano
            )
            LIMIT 1
        ");
        $st->execute([
            ':tipo' => $tipo,
            ':nombre' => $nombre,
            ':ip' => $ip !== '' ? $ip : null,
            ':mac' => $mac !== '' ? $mac : null,
            ':nota' => $nota !== '' ? $nota : null,
            ':id' => $dispositivoId,
            ':plano' => $postPlanoId,
        ]);
        red_redirect($postEdificioId, $postPlanoId);
    }

    if ($accion === 'mover_punto') {
        $dispositivoId = (int)($_POST['dispositivo_id'] ?? 0);
        if ($dispositivoId <= 0) { http_response_code(400); exit('Punto invalido.'); }
        $st = $pdo->prepare("UPDATE red_posiciones SET x = :x, y = :y WHERE dispositivo_id = :id AND plano_id = :plano LIMIT 1");
        $st->execute([':x' => $x, ':y' => $y, ':id' => $dispositivoId, ':plano' => $postPlanoId]);
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
            exit;
        }
        red_redirect($postEdificioId, $postPlanoId);
    }

    if ($accion === 'quitar_punto') {
        $dispositivoId = (int)($_POST['dispositivo_id'] ?? 0);
        if ($dispositivoId <= 0) { http_response_code(400); exit('Punto invalido.'); }
        $pdo->prepare("DELETE FROM red_enlaces WHERE plano_id = :plano AND (origen_id = :id_origen OR destino_id = :id_destino)")
            ->execute([':plano' => $postPlanoId, ':id_origen' => $dispositivoId, ':id_destino' => $dispositivoId]);
        $pdo->prepare("DELETE FROM red_posiciones_ext WHERE plano_id = :plano AND dispositivo_id = :id")
            ->execute([':plano' => $postPlanoId, ':id' => $dispositivoId]);
        $pdo->prepare("DELETE FROM red_posiciones WHERE plano_id = :plano AND dispositivo_id = :id")
            ->execute([':plano' => $postPlanoId, ':id' => $dispositivoId]);
        $pdo->prepare("DELETE FROM red_dispositivos WHERE id = :id AND NOT EXISTS (SELECT 1 FROM red_posiciones WHERE dispositivo_id = :id_check)")
            ->execute([':id' => $dispositivoId, ':id_check' => $dispositivoId]);
        red_redirect($postEdificioId, $postPlanoId);
    }
}

$SELF = (string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? 'informatica_red.php');
$BASE_DIR = rtrim(str_replace('\\', '/', dirname($SELF)), '/');
$BASE_PUBLIC = rtrim(str_replace('\\', '/', dirname($BASE_DIR)), '/');
$BASE_APP = rtrim(str_replace('\\', '/', dirname($BASE_PUBLIC)), '/');
$ASSET_WEB = $BASE_APP . '/assets';
$IMG_BG = $ASSET_WEB . '/img/fondo.png';
$ESCUDO = $ASSET_WEB . '/img/ecmilm.png';
$FAVICON = $ESCUDO;
$UNIDAD_NOMBRE = 'Unidad';
$UNIDAD_SUB = '';
if (function_exists('unidad_context')) {
    $ctx = unidad_context($pdo, $unidadId);
    $IMG_BG = (string)$ctx['bg_url'];
    $ESCUDO = (string)$ctx['escudo_url'];
    $FAVICON = (string)$ctx['icon_url'];
    $UNIDAD_NOMBRE = (string)$ctx['nombre_completo'];
    $UNIDAD_SUB = (string)$ctx['subnombre'];
}

$edificios = [];
if (red_table_exists($pdo, 'red_edificios')) {
    $st = $pdo->prepare("
        SELECT e.id, e.numero, e.nombre, e.descripcion,
               COALESCE(m.max_dispositivos, 0) AS max_dispositivos,
               COALESCE(m.ip_desde, '') AS ip_desde,
               COALESCE(m.ip_hasta, '') AS ip_hasta,
               COALESCE(m.nota, '') AS nota,
               (
                 SELECT p.id
                 FROM red_planos p
                 INNER JOIN red_pisos pi2 ON pi2.id = p.piso_id
                 WHERE pi2.edificio_id = e.id
                 ORDER BY p.id DESC
                 LIMIT 1
               ) AS preview_plano_id,
               (
                 SELECT p.archivo
                 FROM red_planos p
                 INNER JOIN red_pisos pi2 ON pi2.id = p.piso_id
                 WHERE pi2.edificio_id = e.id
                 ORDER BY p.id DESC
                 LIMIT 1
               ) AS preview_archivo,
               (
                 SELECT pi2.nombre
                 FROM red_planos p
                 INNER JOIN red_pisos pi2 ON pi2.id = p.piso_id
                 WHERE pi2.edificio_id = e.id
                 ORDER BY p.id DESC
                 LIMIT 1
               ) AS preview_piso
        FROM red_edificios e
        LEFT JOIN red_edificio_meta m ON m.edificio_id = e.id AND m.unidad_id = :uid_meta
        WHERE e.unidad_id = :uid OR e.unidad_id IS NULL
        ORDER BY COALESCE(e.numero, 9999), e.nombre
    ");
    $st->execute([':uid' => $unidadId, ':uid_meta' => $unidadId]);
    $edificios = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$edificioId = (int)($_GET['edificio_id'] ?? 0);
$edificioActual = null;
foreach ($edificios as $ed) {
    if ((int)$ed['id'] === $edificioId) {
        $edificioActual = $ed;
        break;
    }
}

$planos = [];
$pisos = [];
if ($edificioId > 0) {
    $st = $pdo->prepare("SELECT id, nombre FROM red_pisos WHERE edificio_id = :edificio_id ORDER BY id");
    $st->execute([':edificio_id' => $edificioId]);
    $pisos = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $st = $pdo->prepare("
        SELECT p.id, p.archivo, p.ancho, p.alto, pi.nombre AS piso, e.nombre AS edificio
        FROM red_planos p
        INNER JOIN red_pisos pi ON pi.id = p.piso_id
        INNER JOIN red_edificios e ON e.id = pi.edificio_id
        WHERE pi.edificio_id = :edificio_id AND (e.unidad_id = :uid OR e.unidad_id IS NULL)
        ORDER BY pi.id, p.id
    ");
    $st->execute([':edificio_id' => $edificioId, ':uid' => $unidadId]);
    $planos = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
$planoId = (int)($_GET['plano_id'] ?? 0);
if ($planoId <= 0 && $planos) $planoId = (int)$planos[count($planos) - 1]['id'];

$st = $pdo->prepare("
    SELECT COALESCE(a.edificio_id, 0) AS edificio_id, COALESCE(e.nombre, 'Sin edificio') AS edificio,
           COUNT(*) AS total,
           SUM(a.dispositivo_tipo IN ('PC','NOTEBOOK','SERVIDOR')) AS computadoras,
           SUM(a.dispositivo_tipo = 'IMPRESORA') AS impresoras,
           SUM(a.dispositivo_tipo IN ('SWITCH','ROUTER','AP','MODEM')) AS red,
           SUM(NULLIF(TRIM(a.ip), '') IS NOT NULL) AS con_ip,
           SUM(NULLIF(TRIM(a.mac), '') IS NOT NULL) AS con_mac
    FROM it_activos a
    LEFT JOIN red_edificios e ON e.id = a.edificio_id
    WHERE a.unidad_id = :uid AND a.categoria = 'informatica' AND a.condicion <> 'deposito'
    GROUP BY COALESCE(a.edificio_id, 0), COALESCE(e.nombre, 'Sin edificio')
    ORDER BY edificio
");
$st->execute([':uid' => $unidadId]);
$resumen = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

$whereActivos = "a.unidad_id = :uid AND a.categoria = 'informatica' AND a.condicion <> 'deposito'";
$paramsActivos = [':uid' => $unidadId];
if ($edificioId > 0) { $whereActivos .= " AND a.edificio_id = :edificio_id"; $paramsActivos[':edificio_id'] = $edificioId; }
$st = $pdo->prepare("
    SELECT a.id, a.equipo_nombre, a.descripcion, a.dispositivo_tipo, a.ip, a.mac,
           a.switch_puerto, a.patchera_puerto, a.sector_red, a.vlan, a.ubicacion_detalle,
           COALESCE(di.nombre, '') AS area_nombre, COALESCE(e.nombre, 'Sin edificio') AS edificio_nombre
    FROM it_activos a
    LEFT JOIN destino_interno di ON di.id = a.area_id
    LEFT JOIN red_edificios e ON e.id = a.edificio_id
    WHERE $whereActivos
    ORDER BY e.nombre, di.nombre, a.dispositivo_tipo, COALESCE(a.equipo_nombre, a.descripcion)
");
$st->execute($paramsActivos);
$activos = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

$puntos = [];
$enlaces = [];
if ($planoId > 0) {
    $st = $pdo->prepare("
        SELECT d.id, d.tipo, d.nombre, d.ip, d.mac, d.nota, p.x, p.y, COALESCE(px.scale, 1) AS scale
        FROM red_posiciones p
        INNER JOIN red_dispositivos d ON d.id = p.dispositivo_id
        LEFT JOIN red_posiciones_ext px ON px.dispositivo_id = d.id AND px.plano_id = p.plano_id
        WHERE p.plano_id = :plano_id
        ORDER BY d.tipo, d.nombre
    ");
    $st->execute([':plano_id' => $planoId]);
    $puntos = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $st = $pdo->prepare("SELECT id, tipo, etiqueta, origen_id, destino_id FROM red_enlaces WHERE plano_id = :plano_id ORDER BY id");
    $st->execute([':plano_id' => $planoId]);
    $enlaces = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$totalEquipos = $edificioId > 0 ? count($activos) : array_sum(array_map(static fn($r) => (int)$r['total'], $resumen));
$totalComputadoras = $edificioId > 0
    ? count(array_filter($activos, static fn($a) => in_array((string)$a['dispositivo_tipo'], ['PC', 'NOTEBOOK', 'SERVIDOR'], true)))
    : array_sum(array_map(static fn($r) => (int)$r['computadoras'], $resumen));
$totalConIp = $edificioId > 0
    ? count(array_filter($activos, static fn($a) => trim((string)$a['ip']) !== ''))
    : array_sum(array_map(static fn($r) => (int)$r['con_ip'], $resumen));
$totalRed = $edificioId > 0
    ? count(array_filter($activos, static fn($a) => in_array((string)$a['dispositivo_tipo'], ['SWITCH', 'ROUTER', 'AP', 'MODEM'], true)))
    : array_sum(array_map(static fn($r) => (int)$r['red'], $resumen));
$planoActual = null;
foreach ($planos as $p) if ((int)$p['id'] === $planoId) { $planoActual = $p; break; }
$planoActualExt = $planoActual ? strtolower(pathinfo((string)$planoActual['archivo'], PATHINFO_EXTENSION)) : '';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Red e IP por computadora - Informatica</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<link rel="icon" href="<?= e($FAVICON) ?>">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  :root{--panel:#0f172a;--line:#334155;--text:#e5e7eb;--muted:#94a3b8;--ok:#22c55e;--blue:#38bdf8;--amber:#fbbf24;}
  *{box-sizing:border-box} body{margin:0;min-height:100vh;background:#000;color:var(--text);font-family:system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,sans-serif;}
  .page-bg{position:fixed;inset:0;z-index:-2;background:linear-gradient(160deg,rgba(0,0,0,.90),rgba(2,6,23,.72),rgba(0,0,0,.92)),url("<?= e($IMG_BG) ?>") center/cover no-repeat;background-attachment:fixed;filter:saturate(1.04);}
  .hero{display:flex;gap:14px;align-items:center;padding:14px 18px;border-bottom:1px solid rgba(148,163,184,.25);background:rgba(2,6,23,.62);backdrop-filter:blur(7px);} .hero img{width:54px;height:54px;object-fit:contain}.hero h1{font-size:1.1rem;margin:0;font-weight:950}.hero p{margin:2px 0 0;color:#cbd5e1;font-size:.9rem}.spacer{flex:1}
  .wrap{max-width:1460px;margin:0 auto;padding:18px}.btnx{display:inline-flex;align-items:center;justify-content:center;border:1px solid rgba(226,232,240,.55);border-radius:10px;padding:.46rem .8rem;color:#fff;text-decoration:none;background:rgba(15,23,42,.75);font-weight:850}.btnx.green{background:#22c55e;color:#052e16;border-color:#22c55e}.btnx:hover{filter:brightness(1.08);color:inherit}
  .panel{background:rgba(15,23,42,.92);border:1px solid rgba(148,163,184,.34);border-radius:18px;padding:16px;box-shadow:0 18px 45px rgba(0,0,0,.48)}.chip{display:inline-flex;align-items:center;border-radius:999px;padding:.22rem .65rem;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.12);font-size:.76rem;font-weight:900;text-transform:uppercase;letter-spacing:.04em}.muted{color:var(--muted)}.section-title{font-size:1rem;font-weight:950;margin:0 0 10px}
  .kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.kpi{background:rgba(2,6,23,.58);border:1px solid rgba(148,163,184,.26);border-radius:14px;padding:12px}.kpi b{display:block;font-size:1.55rem;line-height:1}.kpi span{font-size:.78rem;color:#a7b1c2;text-transform:uppercase;font-weight:850}
  .form-select,.form-control{background:#07111f!important;border:1px solid rgba(148,163,184,.38)!important;color:#e5e7eb!important;border-radius:10px!important}.form-control::placeholder{color:#64748b}.btn-small{border:1px solid rgba(226,232,240,.55);border-radius:10px;padding:.38rem .65rem;color:#fff;background:rgba(15,23,42,.75);font-weight:850}.btn-small.green{background:#22c55e;color:#052e16;border-color:#22c55e}.btn-small.red{background:#ef4444;color:#fff;border-color:#ef4444}.table-wrap{overflow:auto;border:1px solid rgba(148,163,184,.28);border-radius:14px}.table{margin:0}.table thead th{background:#102345;color:#fff;border-color:#2d4d76;font-size:.78rem;text-transform:uppercase;white-space:nowrap}.table tbody td{background:rgba(226,232,240,.94);color:#172033;border-color:#cbd5e1;vertical-align:top}.table tbody tr:nth-child(even) td{background:rgba(219,234,254,.92)}.badge-net{display:inline-flex;border-radius:999px;padding:.22rem .55rem;font-size:.74rem;font-weight:900;background:#dbeafe;color:#1d4ed8;white-space:nowrap}
  .planbox{position:relative;overflow:auto;max-height:660px;background:#07111f;border:1px solid rgba(148,163,184,.28);border-radius:14px;padding:12px}.plan-stage{position:relative;display:inline-block;min-width:360px;background:#0b1220;border-radius:10px}.plan-stage img{display:block;max-width:100%;height:auto;border-radius:10px;user-select:none}.node{position:absolute;transform:translate(-50%,-50%);min-width:28px;height:28px;border-radius:999px;border:2px solid #fff;background:#22c55e;color:#04130a;font-weight:950;font-size:.72rem;display:flex;align-items:center;justify-content:center;box-shadow:0 8px 18px rgba(0,0,0,.45);cursor:move}.node.switch{background:#38bdf8}.node.router,.node.ap{background:#fbbf24}.node.impresora{background:#fb7185;color:#fff}.node.selected{outline:3px solid #fbbf24;z-index:5}.legend{display:flex;flex-wrap:wrap;gap:8px}.legend span{display:inline-flex;align-items:center;gap:6px;color:#cbd5e1;font-size:.82rem}.dot{width:12px;height:12px;border-radius:999px;background:#22c55e}.dot.switch{background:#38bdf8}.dot.router{background:#fbbf24}.dot.impresora{background:#fb7185}.point-editor{background:rgba(2,6,23,.58);border:1px solid rgba(148,163,184,.26);border-radius:14px;padding:12px}.plan-gallery{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:12px}.plan-card{display:block;text-decoration:none;color:#e5e7eb;background:rgba(2,6,23,.68);border:1px solid rgba(148,163,184,.28);border-radius:14px;overflow:hidden}.plan-card.active{border-color:#22c55e;box-shadow:0 0 0 2px rgba(34,197,94,.22)}.plan-thumb{height:138px;background:#07111f;display:flex;align-items:center;justify-content:center;overflow:hidden}.plan-thumb img{width:100%;height:100%;object-fit:cover}.plan-pdf{font-size:2rem;font-weight:950;color:#fca5a5}.plan-meta{padding:10px}.plan-meta b{display:block}.plan-meta span{font-size:.78rem;color:#94a3b8}
  @media (max-width:980px){.kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.hero{align-items:flex-start;flex-wrap:wrap}.wrap{padding:12px}}
</style>
</head>
<body>
<div class="page-bg"></div>
<header class="hero"><img src="<?= e($ESCUDO) ?>" alt="Escudo"><div><h1><?= e($UNIDAD_NOMBRE) ?> - Red e IP por computadora</h1><p><?= e($UNIDAD_SUB) ?> - Planos, edificios, IP, MAC, patchera, switch y equipos por area.</p></div><div class="spacer"></div><a class="btnx" href="informatica.php">Volver</a><a class="btnx green" href="informatica_inventarios.php">Inventario</a></header>
<main class="wrap">
  <?php if ($edificioId <= 0): ?>
  <section class="panel mb-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <div>
        <h2 class="section-title mb-1">Edificios y planos</h2>
        <div class="muted">Elegí un edificio desde su tarjeta o creá uno nuevo con su plano inicial.</div>
      </div>
      <span class="chip"><?= count($edificios) ?> edificios</span>
    </div>
    <div class="plan-gallery mb-3">
      <?php foreach ($edificios as $ed):
        $previewId = (int)($ed['preview_plano_id'] ?? 0);
        $previewExt = strtolower(pathinfo((string)($ed['preview_archivo'] ?? ''), PATHINFO_EXTENSION));
        $url = 'informatica_red.php?edificio_id=' . (int)$ed['id'] . ($previewId > 0 ? '&plano_id=' . $previewId : '');
      ?>
        <a class="plan-card <?= (int)$ed['id'] === $edificioId ? 'active' : '' ?>" href="<?= e($url) ?>">
          <div class="plan-thumb">
            <?php if ($previewId > 0 && $previewExt === 'pdf'): ?>
              <div class="plan-pdf">PDF</div>
            <?php elseif ($previewId > 0): ?>
              <img src="?plano_img=<?= $previewId ?>" alt="Plano <?= e((string)$ed['nombre']) ?>">
            <?php else: ?>
              <div class="plan-pdf">SIN PLANO</div>
            <?php endif; ?>
          </div>
          <div class="plan-meta">
            <b><?= e((string)$ed['nombre']) ?></b>
            <span><?= $previewId > 0 ? ('Plano #' . $previewId . ' · ' . e((string)($ed['preview_piso'] ?? ''))) : 'Sin plano cargado' ?></span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
    <form method="post" enctype="multipart/form-data" class="row g-2">
      <?= csrf_input() ?>
      <input type="hidden" name="accion" value="crear_edificio">
      <div class="col-md-2"><input class="form-control" name="edificio_numero" placeholder="Nro"></div>
      <div class="col-md-3"><input class="form-control" name="edificio_nombre" required placeholder="Nombre del edificio"></div>
      <div class="col-md-2"><input class="form-control" name="piso_nombre" value="PB" placeholder="Piso"></div>
      <div class="col-md-3"><input class="form-control" type="file" name="plano_archivo" accept=".pdf,.png,.jpg,.jpeg,.webp,application/pdf,image/png,image/jpeg,image/webp" required></div>
      <div class="col-md-1"><input class="form-control" name="ancho" value="1280" title="Ancho PDF"></div>
      <div class="col-md-1"><input class="form-control" name="alto" value="900" title="Alto PDF"></div>
      <div class="col-md-10"><input class="form-control" name="edificio_descripcion" placeholder="Descripcion / observaciones del edificio"></div>
      <div class="col-md-2 d-grid"><button class="btn-small green" type="submit">Crear edificio</button></div>
    </form>
  </section>
  <?php endif; ?>
  <section class="panel mb-3"><div class="d-flex flex-wrap align-items-center gap-2 mb-3"><span class="chip">Mapa de red</span><span class="muted"><?= $edificioId > 0 ? 'Edificio: ' . e((string)($edificioActual['nombre'] ?? 'Seleccionado')) : 'Datos tomados de inventario y tablas de planos.' ?></span><div class="spacer"></div><?php if ($edificioId > 0): ?><a class="btn-small" href="informatica_red.php">Volver a edificios</a><?php endif; ?></div><div class="kpis"><div class="kpi"><span>Activos informaticos</span><b><?= (int)$totalEquipos ?></b></div><div class="kpi"><span>Computadoras/servidores</span><b><?= (int)$totalComputadoras ?></b></div><div class="kpi"><span>Con IP cargada</span><b><?= (int)$totalConIp ?></b></div><div class="kpi"><span>Dispositivos de red</span><b><?= (int)$totalRed ?></b></div></div></section>
  <?php if ($edificioId > 0): ?>
  <div class="row g-3"><div class="col-xl-4"><section class="panel mb-3"><h2 class="section-title">Edificio y plano</h2><form method="get" class="row g-2"><div class="col-12"><label class="form-label small text-uppercase fw-bold text-secondary">Edificio</label><select class="form-select" name="edificio_id" onchange="this.form.submit()"><?php foreach ($edificios as $ed): ?><option value="<?= (int)$ed['id'] ?>" <?= (int)$ed['id'] === $edificioId ? 'selected' : '' ?>><?= e((string)$ed['nombre']) ?></option><?php endforeach; ?></select></div><div class="col-12"><label class="form-label small text-uppercase fw-bold text-secondary">Plano</label><select class="form-select" name="plano_id" onchange="this.form.submit()"><?php if (!$planos): ?><option value="0">Sin planos cargados</option><?php endif; ?><?php foreach ($planos as $pl): ?><option value="<?= (int)$pl['id'] ?>" <?= (int)$pl['id'] === $planoId ? 'selected' : '' ?>><?= e((string)$pl['piso']) ?> - Plano #<?= (int)$pl['id'] ?></option><?php endforeach; ?></select></div></form></section><section class="panel mb-3"><h2 class="section-title">Cargar o cambiar plano</h2><form method="post" enctype="multipart/form-data" class="row g-2"><?= csrf_input() ?><input type="hidden" name="accion" value="subir_plano"><input type="hidden" name="edificio_id" value="<?= (int)$edificioId ?>"><div class="col-12"><label class="form-label small text-uppercase fw-bold text-secondary">Guardar como</label><select class="form-select" name="reemplazar_plano_id"><option value="0">Plano nuevo</option><?php foreach ($planos as $pl): ?><option value="<?= (int)$pl['id'] ?>" <?= (int)$pl['id'] === $planoId ? 'selected' : '' ?>>Reemplazar #<?= (int)$pl['id'] ?> - <?= e((string)$pl['piso']) ?></option><?php endforeach; ?></select></div><div class="col-12"><label class="form-label small text-uppercase fw-bold text-secondary">Piso</label><select class="form-select" name="piso_id"><?php foreach ($pisos as $piso): ?><option value="<?= (int)$piso['id'] ?>" <?= $planoActual && (string)$planoActual['piso'] === (string)$piso['nombre'] ? 'selected' : '' ?>><?= e((string)$piso['nombre']) ?></option><?php endforeach; ?></select></div><div class="col-12"><label class="form-label small text-uppercase fw-bold text-secondary">O crear piso nuevo</label><input class="form-control" name="piso_nuevo" placeholder="Ej: 2do piso / Subsuelo"></div><div class="col-12"><label class="form-label small text-uppercase fw-bold text-secondary">Archivo PDF o imagen</label><input class="form-control" type="file" name="plano_archivo" accept=".pdf,.png,.jpg,.jpeg,.webp,application/pdf,image/png,image/jpeg,image/webp" required></div><div class="col-6"><label class="form-label small text-uppercase fw-bold text-secondary">Ancho PDF</label><input class="form-control" name="ancho" value="<?= e((string)($planoActual['ancho'] ?? 1280)) ?>"></div><div class="col-6"><label class="form-label small text-uppercase fw-bold text-secondary">Alto PDF</label><input class="form-control" name="alto" value="<?= e((string)($planoActual['alto'] ?? 900)) ?>"></div><div class="col-12 d-grid"><button class="btn-small green" type="submit">Guardar plano</button></div><div class="col-12 muted small">En imagen se toman las medidas reales. En PDF se usan ancho/alto para ubicar puntos.</div></form></section><section class="panel mb-3"><h2 class="section-title">Editar plano</h2><?php if ($planoActual): ?><form method="post" class="row g-2" id="createPointForm"><?= csrf_input() ?><input type="hidden" name="accion" value="crear_punto"><input type="hidden" name="plano_id" value="<?= (int)$planoId ?>"><div class="col-6"><label class="form-label small text-uppercase fw-bold text-secondary">Tipo</label><select class="form-select" name="tipo"><option value="pc">PC</option><option value="notebook">Notebook</option><option value="impresora">Impresora</option><option value="switch">Switch</option><option value="router">Router</option><option value="ap">AP</option><option value="servidor">Servidor</option><option value="otro">Otro</option></select></div><div class="col-6"><label class="form-label small text-uppercase fw-bold text-secondary">Nombre</label><input class="form-control" name="nombre" required placeholder="Ej: U2285-CE-001"></div><div class="col-6"><label class="form-label small text-uppercase fw-bold text-secondary">X</label><input class="form-control" id="newX" name="x" value="80" inputmode="decimal"></div><div class="col-6"><label class="form-label small text-uppercase fw-bold text-secondary">Y</label><input class="form-control" id="newY" name="y" value="80" inputmode="decimal"></div><div class="col-6"><label class="form-label small text-uppercase fw-bold text-secondary">IP</label><input class="form-control" name="ip" placeholder="10.21.209.x"></div><div class="col-6"><label class="form-label small text-uppercase fw-bold text-secondary">MAC</label><input class="form-control" name="mac" placeholder="AA:BB:CC:DD:EE:FF"></div><div class="col-12"><label class="form-label small text-uppercase fw-bold text-secondary">Nota</label><input class="form-control" name="nota" placeholder="Oficina, boca, observacion"></div><div class="col-12 d-grid"><button class="btn-small green" type="submit">Agregar punto</button></div><div class="col-12 muted small">Tip: hacé clic sobre el plano para completar X/Y. Arrastrá un punto para moverlo.</div></form><?php else: ?><div class="muted">Seleccioná o cargá un plano para agregar puntos.</div><?php endif; ?></section><section class="panel"><h2 class="section-title">Resumen por edificio</h2><div class="table-wrap"><table class="table table-sm"><thead><tr><th>Edificio</th><th>Total</th><th>PC</th><th>IP</th><th>Red</th></tr></thead><tbody><?php foreach ($resumen as $r): ?><tr><td><strong><?= e((string)$r['edificio']) ?></strong></td><td><?= (int)$r['total'] ?></td><td><?= (int)$r['computadoras'] ?></td><td><?= (int)$r['con_ip'] ?></td><td><?= (int)$r['red'] ?></td></tr><?php endforeach; ?></tbody></table></div></section></div>
    <div class="col-xl-8"><section class="panel mb-3"><div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2"><h2 class="section-title mb-0">Plano del edificio</h2><div class="legend"><span><i class="dot"></i> PC</span><span><i class="dot switch"></i> Switch</span><span><i class="dot router"></i> Router/AP</span><span><i class="dot impresora"></i> Impresora</span></div></div><div class="planbox"><?php if ($planoActual): ?><?php $naturalW=max(1,(int)($planoActual['ancho'] ?? 1280)); $naturalH=max(1,(int)($planoActual['alto'] ?? 720)); ?><div class="plan-stage" id="planStage" data-plano-id="<?= (int)$planoId ?>" data-natural-w="<?= $naturalW ?>" data-natural-h="<?= $naturalH ?>" style="width:<?= $naturalW ?>px;max-width:100%;"><?php if ($planoActualExt === 'pdf'): ?><object data="?plano_img=<?= (int)$planoActual['id'] ?>" type="application/pdf" style="display:block;width:100%;height:<?= $naturalH ?>px;border-radius:10px;background:#111827;"></object><?php else: ?><img src="?plano_img=<?= (int)$planoActual['id'] ?>" alt="Plano <?= e((string)$planoActual['piso']) ?>" draggable="false"><?php endif; ?><?php foreach ($puntos as $p): $left=((float)$p['x']/$naturalW)*100; $top=((float)$p['y']/$naturalH)*100; $tipo=strtolower((string)$p['tipo']); $class=str_contains($tipo,'switch')?'switch':(str_contains($tipo,'router')?'router':(str_contains($tipo,'ap')?'ap':(str_contains($tipo,'imp')?'impresora':'pc'))); $title=trim((string)$p['nombre'].' '.(string)$p['ip']); ?><span class="node <?= e($class) ?>" data-device-id="<?= (int)$p['id'] ?>" data-x="<?= e((string)$p['x']) ?>" data-y="<?= e((string)$p['y']) ?>" style="left:<?= number_format($left,3,'.','') ?>%;top:<?= number_format($top,3,'.','') ?>%;" title="<?= e($title) ?>"><?= e(mb_strtoupper(mb_substr((string)$p['tipo'],0,1,'UTF-8'),'UTF-8')) ?></span><?php endforeach; ?></div><?php else: ?><div class="text-center muted py-5">No hay plano cargado para este edificio.</div><?php endif; ?></div><div class="muted small mt-2">Puntos cargados en el plano: <?= count($puntos) ?> - Enlaces registrados: <?= count($enlaces) ?></div></section></div></div>
  <section class="panel mt-3"><div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2"><h2 class="section-title mb-0">Puntos del plano</h2><span class="muted"><?= count($puntos) ?> puntos</span></div><div class="table-wrap"><table class="table table-sm"><thead><tr><th>Datos</th><th>Posicion</th><th>Accion</th></tr></thead><tbody><?php if (!$puntos): ?><tr><td colspan="3" class="text-center py-4">Todavia no hay puntos cargados en este plano.</td></tr><?php endif; ?><?php foreach ($puntos as $p): ?><tr><td><form method="post" class="row g-1 align-items-end"><?= csrf_input() ?><input type="hidden" name="accion" value="editar_punto"><input type="hidden" name="plano_id" value="<?= (int)$planoId ?>"><input type="hidden" name="dispositivo_id" value="<?= (int)$p['id'] ?>"><div class="col-md-2"><select class="form-select form-select-sm" name="tipo"><?php foreach (['pc','notebook','impresora','switch','router','ap','servidor','otro'] as $tipoOpt): ?><option value="<?= e($tipoOpt) ?>" <?= $tipoOpt === (string)$p['tipo'] ? 'selected' : '' ?>><?= e($tipoOpt) ?></option><?php endforeach; ?></select></div><div class="col-md-3"><input class="form-control form-control-sm" name="nombre" value="<?= e((string)$p['nombre']) ?>" required></div><div class="col-md-2"><input class="form-control form-control-sm" name="ip" value="<?= e((string)$p['ip']) ?>" placeholder="IP"></div><div class="col-md-2"><input class="form-control form-control-sm" name="mac" value="<?= e((string)$p['mac']) ?>" placeholder="MAC"></div><div class="col-md-2"><input class="form-control form-control-sm" name="nota" value="<?= e((string)$p['nota']) ?>" placeholder="Nota"></div><div class="col-md-1"><button class="btn-small green" type="submit">OK</button></div></form></td><td><form method="post" class="d-flex gap-1 align-items-center"><?= csrf_input() ?><input type="hidden" name="accion" value="mover_punto"><input type="hidden" name="plano_id" value="<?= (int)$planoId ?>"><input type="hidden" name="dispositivo_id" value="<?= (int)$p['id'] ?>"><input class="form-control form-control-sm" style="width:80px" name="x" value="<?= e((string)round((float)$p['x'], 1)) ?>"><input class="form-control form-control-sm" style="width:80px" name="y" value="<?= e((string)round((float)$p['y'], 1)) ?>"><button class="btn-small" type="submit">Mover</button></form></td><td><form method="post" onsubmit="return confirm('Quitar este punto del plano?')"><?= csrf_input() ?><input type="hidden" name="accion" value="quitar_punto"><input type="hidden" name="plano_id" value="<?= (int)$planoId ?>"><input type="hidden" name="dispositivo_id" value="<?= (int)$p['id'] ?>"><button class="btn-small red" type="submit">Quitar</button></form></td></tr><?php endforeach; ?></tbody></table></div></section>
  <?php endif; ?>
  <section class="panel mt-3"><div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2"><h2 class="section-title mb-0"><?= $edificioId > 0 ? 'Computadoras y dispositivos del edificio' : 'Computadoras y dispositivos generales' ?></h2><span class="muted"><?= count($activos) ?> registros</span></div><div class="table-wrap"><table class="table table-sm"><thead><tr><th>Equipo</th><th>Tipo</th><th>Area</th><th>Ubicacion</th><th>IP</th><th>MAC</th><th>Switch</th><th>Patchera</th><th>VLAN</th></tr></thead><tbody><?php if (!$activos): ?><tr><td colspan="9" class="text-center py-4"><?= $edificioId > 0 ? 'No hay activos cargados para este edificio.' : 'No hay activos cargados.' ?></td></tr><?php endif; ?><?php foreach ($activos as $a): $nombre=trim((string)($a['equipo_nombre'] ?: $a['descripcion'])); ?><tr><td><strong><?= e($nombre !== '' ? $nombre : ('Activo #' . (int)$a['id'])) ?></strong></td><td><span class="badge-net"><?= e((string)$a['dispositivo_tipo']) ?></span></td><td><?= e((string)$a['area_nombre']) ?></td><td><?= e((string)$a['ubicacion_detalle']) ?></td><td><?= e((string)$a['ip']) ?></td><td><?= e((string)$a['mac']) ?></td><td><?= e((string)$a['switch_puerto']) ?></td><td><?= e((string)$a['patchera_puerto']) ?></td><td><?= e((string)$a['vlan']) ?></td></tr><?php endforeach; ?></tbody></table></div></section>
</main>
<script>
(() => {
  const stage = document.getElementById('planStage');
  const xInput = document.getElementById('newX');
  const yInput = document.getElementById('newY');
  const csrf = <?= json_encode(csrf_token(), JSON_UNESCAPED_UNICODE) ?>;
  if (!stage) return;

  const naturalW = Number(stage.dataset.naturalW || 1);
  const naturalH = Number(stage.dataset.naturalH || 1);
  const planoId = stage.dataset.planoId || '';

  function coordsFromEvent(ev) {
    const rect = stage.getBoundingClientRect();
    const x = Math.max(0, Math.min(naturalW, ((ev.clientX - rect.left) / rect.width) * naturalW));
    const y = Math.max(0, Math.min(naturalH, ((ev.clientY - rect.top) / rect.height) * naturalH));
    return { x, y };
  }

  function setCreateCoords(x, y) {
    if (xInput) xInput.value = x.toFixed(1);
    if (yInput) yInput.value = y.toFixed(1);
  }

  stage.addEventListener('click', (ev) => {
    if (ev.target.closest('.node')) return;
    const pos = coordsFromEvent(ev);
    setCreateCoords(pos.x, pos.y);
  });

  let dragging = null;
  stage.querySelectorAll('.node').forEach((node) => {
    node.addEventListener('pointerdown', (ev) => {
      ev.preventDefault();
      dragging = node;
      stage.querySelectorAll('.node.selected').forEach(n => n.classList.remove('selected'));
      node.classList.add('selected');
      node.setPointerCapture(ev.pointerId);
    });
  });

  stage.addEventListener('pointermove', (ev) => {
    if (!dragging) return;
    const pos = coordsFromEvent(ev);
    dragging.style.left = ((pos.x / naturalW) * 100).toFixed(3) + '%';
    dragging.style.top = ((pos.y / naturalH) * 100).toFixed(3) + '%';
    dragging.dataset.x = pos.x.toFixed(1);
    dragging.dataset.y = pos.y.toFixed(1);
    setCreateCoords(pos.x, pos.y);
  });

  stage.addEventListener('pointerup', async () => {
    if (!dragging) return;
    const node = dragging;
    dragging = null;
    const form = new FormData();
    form.append('_csrf', csrf);
    form.append('ajax', '1');
    form.append('accion', 'mover_punto');
    form.append('plano_id', planoId);
    form.append('dispositivo_id', node.dataset.deviceId || '');
    form.append('x', node.dataset.x || '0');
    form.append('y', node.dataset.y || '0');
    try {
      const res = await fetch('informatica_red.php', { method: 'POST', body: form, credentials: 'same-origin' });
      if (!res.ok) throw new Error('No se pudo guardar');
    } catch (err) {
      alert(err.message || 'No se pudo guardar la posicion.');
      location.reload();
    }
  });
})();
</script>
</body>
</html>
