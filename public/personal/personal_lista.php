<?php declare(strict_types=1);
/**
 * public/personal/personal_lista.php
 * Lista de personal con:
 *  - Orden por jerarquía y grado militar argentino
 *  - Filtros: búsqueda, jerarquía, destino, parte enfermo
 *  - Export PDF puro (HTML→print / tabla HTML sin librerías)
 *  - Export XLSX con PhpSpreadsheet (si disponible) o CSV como fallback
 *  - UI mejorada dark
 */

$ROOT = realpath(__DIR__ . '/../../');
if (!$ROOT) { http_response_code(500); exit('No se pudo resolver ROOT.'); }
require_once $ROOT . '/auth/bootstrap.php';
require_login();
require_once $ROOT . '/config/db.php';
/** @var PDO $pdo */

/* ════════════════════════════════════════ HELPERS ════════════════════════════════════════ */
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function norm_dni(string $d): string { return preg_replace('/\D+/', '', $d) ?? ''; }
function fmt_date(?string $y): string {
    if (!$y) return '-'; $ts = strtotime($y); return $ts !== false ? date('d/m/Y', $ts) : '-';
}
function table_exists(PDO $pdo, string $t): bool {
    $s = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t");
    $s->execute([':t' => $t]); return (int)$s->fetchColumn() > 0;
}
function column_exists(PDO $pdo, string $table, string $column): bool {
    $s = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t AND COLUMN_NAME=:c");
    $s->execute([':t' => $table, ':c' => $column]); return (int)$s->fetchColumn() > 0;
}
function normalizar_destino_interno_personal(string $nombre): string {
    $nombre = trim(preg_replace('/\s+/', ' ', $nombre) ?? $nombre);
    $key = mb_strtoupper(str_replace('.', '', $nombre), 'UTF-8');
    $key = preg_replace('/\s+/', ' ', $key) ?? $key;
    return in_array($key, ['BDA MIL', 'BDA MILITAR', 'BANDA MIL', 'BANDA MILITAR'], true) ? 'BANDA MILITAR' : $nombre;
}
function resolver_destino_interno_personal(PDO $pdo, string $nombre): ?int {
    $nombre = normalizar_destino_interno_personal($nombre);
    if ($nombre === '') return null;
    $st = $pdo->prepare("SELECT id FROM destino_interno WHERE UPPER(nombre)=UPPER(:nom) AND COALESCE(estado,'ACTIVO')='ACTIVO' LIMIT 1");
    $st->execute([':nom' => $nombre]);
    $id = $st->fetchColumn();
    if ($id !== false) return (int)$id;
    $st = $pdo->prepare("INSERT INTO destino_interno (nombre, estado) VALUES (:nom, 'ACTIVO')");
    $st->execute([':nom' => $nombre]);
    return (int)$pdo->lastInsertId();
}
function ensure_personal_role_columns(PDO $pdo): void {
    if (!table_exists($pdo, 'personal_unidad')) return;

    $hasRol = column_exists($pdo, 'personal_unidad', 'rol');
    if (!column_exists($pdo, 'personal_unidad', 'rol_combate')) {
        $pdo->exec("ALTER TABLE personal_unidad ADD COLUMN rol_combate VARCHAR(255) NULL AFTER destino_interno");
    }
    if (!column_exists($pdo, 'personal_unidad', 'rol_administrativo')) {
        $pdo->exec("ALTER TABLE personal_unidad ADD COLUMN rol_administrativo VARCHAR(255) NULL AFTER rol_combate");
    }
    if (!column_exists($pdo, 'personal_unidad', 'usuario_intranet')) {
        $pdo->exec("ALTER TABLE personal_unidad ADD COLUMN usuario_intranet VARCHAR(120) NULL AFTER alias_banco");
    }
    if (!column_exists($pdo, 'personal_unidad', 'usuario_gde')) {
        $pdo->exec("ALTER TABLE personal_unidad ADD COLUMN usuario_gde VARCHAR(120) NULL AFTER usuario_intranet");
    }

    if ($hasRol) {
        $pdo->exec("
            UPDATE personal_unidad
               SET rol_administrativo = rol
             WHERE (rol_administrativo IS NULL OR rol_administrativo = '')
               AND rol IS NOT NULL
               AND rol <> ''
        ");
    }
}

ensure_personal_role_columns($pdo);

/* ════════════════════════════════════ USUARIO / ROL ════════════════════════════════════ */
$user        = function_exists('current_user') ? current_user() : ($_SESSION['user'] ?? null);
$dniUsuario  = norm_dni((string)($user['dni'] ?? $user['username'] ?? ''));
$personalId  = 0; $unidadPropia = 1;

try {
    if ($dniUsuario !== '') {
        $st = $pdo->prepare("SELECT id, unidad_id FROM personal_unidad
                             WHERE REPLACE(REPLACE(REPLACE(dni,'.',''),'-',''),' ','')=:d LIMIT 1");
        $st->execute([':d' => $dniUsuario]);
        if ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            $personalId  = (int)$r['id'];
            $unidadPropia = (int)$r['unidad_id'];
        }
    }
} catch (Throwable $e) {}

$roleCodigo = 'USUARIO';
try {
    if ($personalId > 0) {
        $st = $pdo->prepare("SELECT r.codigo FROM personal_unidad pu
                             INNER JOIN roles r ON r.id=pu.role_id WHERE pu.id=:p LIMIT 1");
        $st->execute([':p' => $personalId]);
        $c = $st->fetchColumn(); if (is_string($c) && $c !== '') $roleCodigo = $c;
    }
} catch (Throwable $e) {}

$esSuperAdmin = ($roleCodigo === 'SUPERADMIN');
$esAdmin      = ($roleCodigo === 'ADMIN') || $esSuperAdmin;
$unidadActiva = $unidadPropia;
if ($esSuperAdmin) { $uSel = (int)($_SESSION['unidad_id'] ?? 0); if ($uSel > 0) $unidadActiva = $uSel; }

/* ════════════════════════════════════ BRANDING ════════════════════════════════════ */
$NOMBRE = 'Unidad'; $LEYENDA = ''; $UNIDAD_SLUG = 'ecmilm';
try {
    $st = $pdo->prepare("SELECT nombre_completo, subnombre, slug FROM unidades WHERE id=:id LIMIT 1");
    $st->execute([':id' => $unidadActiva]);
    if ($u = $st->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($u['nombre_completo'])) $NOMBRE = (string)$u['nombre_completo'];
        if (!empty($u['subnombre']))       $LEYENDA = trim((string)$u['subnombre']);
        if (!empty($u['slug']))            $UNIDAD_SLUG = (string)$u['slug'];
    }
} catch (Throwable $e) {}

$SELF_WEB       = (string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
$BASE_DIR_WEB   = rtrim(str_replace('\\', '/', dirname($SELF_WEB)), '/');
$BASE_PUB_WEB   = rtrim(str_replace('\\', '/', dirname($BASE_DIR_WEB)), '/');
$BASE_APP_WEB   = rtrim(str_replace('\\', '/', dirname($BASE_PUB_WEB)), '/');
$ASSETS_WEB     = $BASE_APP_WEB . '/assets';
$IMG_BG         = $ASSETS_WEB . '/img/fondo.png';
$ESCUDO         = $ASSETS_WEB . '/img/ecmilm.png';

/* ════════════════════════════════════ ORDEN MILITAR ════════════════════════════════════
 * Jerarquía: OFICIAL(1) → SUBOFICIAL(2) → SOLDADO(3) → AGENTE_CIVIL(4)
 * Grados: de mayor a menor rango dentro de cada jerarquía
 */
$SQL_ORDEN_JERARQUIA = "CASE jerarquia
    WHEN 'OFICIAL'       THEN 1
    WHEN 'SUBOFICIAL'    THEN 2
    WHEN 'SOLDADO'       THEN 3
    WHEN 'AGENTE_CIVIL'  THEN 4
    ELSE 5
END";

$SQL_ORDEN_GRADO = "CASE grado
    WHEN 'TG'        THEN 9
    WHEN 'GD'        THEN 10
    WHEN 'GB'        THEN 11
    WHEN 'CR'        THEN 12
    WHEN 'TC'        THEN 13
    WHEN 'MY'        THEN 14
    WHEN 'CT'        THEN 15
    WHEN 'TP'        THEN 16
    WHEN 'TT'        THEN 17
    WHEN 'ST'        THEN 18
    WHEN 'ST EC'     THEN 19
    WHEN 'SM'        THEN 20
    WHEN 'SP'        THEN 21
    WHEN 'SA'        THEN 22
    WHEN 'SI'        THEN 23
    WHEN 'SG'        THEN 24
    WHEN 'CI'        THEN 25
    WHEN 'CI EC'     THEN 26
    WHEN 'CI Art 11' THEN 27
    WHEN 'CB'        THEN 28
    WHEN 'CB EC'     THEN 29
    WHEN 'CB Art 11' THEN 30
    WHEN 'VP'        THEN 31
    WHEN 'VS'        THEN 32
    WHEN 'VS EC'     THEN 33
    WHEN 'SV'        THEN 34
    WHEN 'AC'        THEN 35
    ELSE 99
END";

/* ════════════════════════════════════ PARÁMETROS GET ════════════════════════════════════ */
$q         = trim((string)($_GET['q']         ?? ''));
$filtroJer = trim((string)($_GET['jerarquia'] ?? ''));
$filtroDst = trim((string)($_GET['destino']   ?? ($_GET['destino_id'] ?? '')));
$filtroPte = trim((string)($_GET['parte']     ?? ''));
$exportar  = trim((string)($_GET['export']    ?? ''));
$cicloGet  = trim((string)($_GET['ciclo']     ?? ''));


/* ════════════════════════════════════ AUTO-CREAR TABLA personal_ciclos ════════════════════════════════════ */
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS personal_ciclos (
      id int NOT NULL AUTO_INCREMENT,
      unidad_id int NOT NULL,
      ciclo year NOT NULL,
      personal_id int NOT NULL,
      estado enum('activo','baja','egreso','licencia_ext') NOT NULL DEFAULT 'activo',
      nota varchar(255) DEFAULT NULL,
      created_at timestamp NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
      created_by_id int DEFAULT NULL,
      PRIMARY KEY (id),
      UNIQUE KEY uk_ciclo_personal (unidad_id, ciclo, personal_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
} catch (Throwable $e) {}

/* ════════════════════════════════════ CICLO ACTIVO ════════════════════════════════════ */
$ciclosDisponibles = [];
try {
    $st = $pdo->prepare("SELECT DISTINCT ciclo FROM personal_ciclos WHERE unidad_id=:u ORDER BY ciclo DESC");
    $st->execute([':u' => $unidadActiva]);
    $ciclosDisponibles = $st->fetchAll(PDO::FETCH_COLUMN) ?: [];
} catch (Throwable $e) {}

$cicloFiltro = '';
if ($cicloGet === 'todos' || empty($ciclosDisponibles)) {
    $cicloFiltro = '';
} elseif ($cicloGet !== '' && is_numeric($cicloGet)) {
    $cicloFiltro = (int)$cicloGet;
} elseif (!empty($ciclosDisponibles)) {
    $cicloFiltro = (int)$ciclosDisponibles[0]; // año más reciente
}

/* ════════════════════════════════════ ACCIÓN POST: GUARDAR DESTINOS INTERNOS CON CLAVE FORÁNEA ════════════════════════════════════ */
$mensajeImport = ''; $mensajeImportError = '';
$mensajeDestino = ''; $mensajeDestinoError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_destinos'])) {
    if (!$esAdmin) {
        $mensajeDestinoError = 'Acceso restringido. Solo ADMIN/SUPERADMIN.';
    } else {
        try {
            $ids = $_POST['ids'] ?? [];
            $destinosSel = $_POST['destino_interno_id'] ?? [];
            $destinosNuevos = $_POST['destino_interno_nuevo'] ?? [];
            
            if (!is_array($ids)) $ids = [];
            if (!is_array($destinosSel)) $destinosSel = [];
            if (!is_array($destinosNuevos)) $destinosNuevos = [];

            $pdo->beginTransaction();
            $actualizados = 0;
            
            foreach ($ids as $idRaw) {
                $pid = (int)$idRaw;
                if ($pid <= 0) continue;
                
                $finalDestinoId = null;

                // Si eligió dar de alta un destino nuevo inline desde la interfaz web
                if (isset($destinosSel[$pid]) && $destinosSel[$pid] === 'NUEVO' && !empty($destinosNuevos[$pid])) {
                    $nuevoNombre = trim((string)$destinosNuevos[$pid]);
                    
                    $finalDestinoId = resolver_destino_interno_personal($pdo, $nuevoNombre);
                } elseif (!empty($destinosSel[$pid]) && $destinosSel[$pid] !== 'NUEVO') {
                    // Si seleccionó uno ya existente, usamos su ID numérico directamente
                    $finalDestinoId = (int)$destinosSel[$pid];
                }

                // Guardar la ID numérica del destino en la columna vinculada por la Clave Foránea
                $paramsUpd = [
                    ':id' => $pid,
                    ':uid' => $unidadActiva,
                    ':destino_interno' => $finalDestinoId // Pasa el ID numérico o null
                ];
                
                $sqlUpd = "UPDATE personal_unidad SET destino_interno = :destino_interno";
                if (column_exists($pdo, 'personal_unidad', 'updated_at')) {
                    $sqlUpd .= ", updated_at = NOW()";
                }
                if (column_exists($pdo, 'personal_unidad', 'updated_by_id')) {
                    $sqlUpd .= ", updated_by_id = :updated_by_id";
                    $paramsUpd[':updated_by_id'] = $personalId;
                }
                $sqlUpd .= " WHERE id = :id AND unidad_id = :uid LIMIT 1";
                
                $stUpd = $pdo->prepare($sqlUpd);
                $stUpd->execute($paramsUpd);
                $actualizados += $stUpd->rowCount();
            }
            $pdo->commit();
            $mensajeDestino = "Destinos internos guardados de forma segura. Efectivos actualizados: {$actualizados}.";
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $mensajeDestinoError = $ex->getMessage();
        }
    }
}

/* ════════════════════════════════════ ACCIÓN POST: IMPORTAR EXCEL ════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['accion_import'])) {
    $accionImport = (string)$_POST['accion_import'];
    if (!$esAdmin) {
        $mensajeImportError = 'Acceso restringido. Solo ADMIN/SUPERADMIN.';
    } else {
        try {
            $vendorAutoload = $ROOT . '/vendor/autoload.php';
            if (!is_file($vendorAutoload)) throw new RuntimeException('Falta vendor/autoload.php. Ejecutá: composer require phpoffice/phpspreadsheet');
            require_once $vendorAutoload;

            if (!isset($_FILES['excel_archivo']) || $_FILES['excel_archivo']['error'] === UPLOAD_ERR_NO_FILE)
                throw new RuntimeException('Seleccioná un archivo Excel.');
            $file = $_FILES['excel_archivo'];
            if ($file['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Error subida (cód '.(int)$file['error'].').');
            $extXls = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
            if (!in_array($extXls, ['xls','xlsx'], true)) throw new RuntimeException('El archivo debe ser .xls o .xlsx.');
            if ($extXls === 'xlsx' && !class_exists('ZipArchive'))
                throw new RuntimeException('Falta extensión PHP ZipArchive. En XAMPP: activá extension=zip en php.ini y reiniciá Apache.');

            $cicloImport = (int)($_POST['ciclo_import'] ?? date('Y'));
            if ($cicloImport < 2020 || $cicloImport > 2040) throw new RuntimeException('Año inválido ('.$cicloImport.').');

            if ($accionImport === 'reemplazar_ciclo') {
                if (($_POST['confirmacion_reemplazar'] ?? '') !== 'CONFIRMAR')
                    throw new RuntimeException('Para reemplazar escribí CONFIRMAR en el campo de confirmación.');
            }

            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name']);
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = (int)$sheet->getHighestRow();

            $jerMap = [
                'OFICIALES'=>'OFICIAL','OFICIAL'=>'OFICIAL',
                'SUBOFICIALES'=>'SUBOFICIAL','SUBOFICIAL'=>'SUBOFICIAL',
                'SOLDADOS'=>'SOLDADO','SOLDADO'=>'SOLDADO',
                'AGENTES CIVILES'=>'AGENTE_CIVIL','AGENTE_CIVIL'=>'AGENTE_CIVIL','AGENTE CIVIL'=>'AGENTE_CIVIL',
            ];

            $pdo->beginTransaction();

            if ($accionImport === 'reemplazar_ciclo') {
                $pdo->prepare("DELETE FROM personal_ciclos WHERE unidad_id=:u AND ciclo=:c")
                    ->execute([':u'=>$unidadActiva,':c'=>$cicloImport]);
            }

            $procesadas = 0; $errores = []; $currentJer = '';

            for ($row = 2; $row <= $highestRow; $row++) {
                $gradoRaw = trim((string)$sheet->getCell("A{$row}")->getValue());
                $arma     = trim((string)$sheet->getCell("B{$row}")->getValue());
                $apnom    = trim((string)$sheet->getCell("C{$row}")->getValue());
                $dniRaw   = trim((string)$sheet->getCell("D{$row}")->getValue());

                // Separador de jerarquía
                $gU = strtoupper($gradoRaw);
                if (isset($jerMap[$gU]) && $arma === '' && $apnom === '' && $dniRaw === '') {
                    $currentJer = $jerMap[$gU]; continue;
                }
                if ($dniRaw === '' && $apnom === '' && $gradoRaw === '') continue;

                $dni = preg_replace('/\D+/', '', $dniRaw) ?? '';
                if ($dni === '') { $errores[] = "Fila {$row}: sin DNI ({$apnom})"; continue; }

                $cuil    = trim((string)$sheet->getCell("E{$row}")->getValue());
                $sexo    = trim((string)$sheet->getCell("I{$row}")->getValue());
                $domicilio = trim((string)$sheet->getCell("J{$row}")->getValue());
                $destInt = trim((string)$sheet->getCell("V{$row}")->getValue());
                $rolCombate = trim((string)$sheet->getCell("W{$row}")->getValue());
                $rolAdministrativo = trim((string)$sheet->getCell("X{$row}")->getValue());
                $obs     = trim((string)$sheet->getCell("AA{$row}")->getValue());

                // Jerarquía
                $g2 = strtoupper(trim($gradoRaw));
                if ($currentJer !== '') {
                    $jerEnum = $currentJer;
                } elseif (str_starts_with($g2,'AG') || str_contains($g2,'CIVIL')) {
                    $jerEnum = 'AGENTE_CIVIL';
                } elseif (in_array($g2,['TG','GD','GB','CR','TC','MY','CT','TP','TT','ST'],true)) {
                    $jerEnum = 'OFICIAL';
                } elseif (in_array($g2,['SM','SP','SA','SI','SG','CI','CB'],true)) {
                    $jerEnum = 'SUBOFICIAL';
                } else {
                    $jerEnum = in_array($g2,['VP','VS','SV'],true) ? 'SOLDADO' : 'SOLDADO';
                }

                $extraJson = json_encode(['jerarquia'=>$jerEnum,'jerarquia_label'=>match($jerEnum){'OFICIAL'=>'OFICIALES','SUBOFICIAL'=>'SUBOFICIALES','SOLDADO'=>'SOLDADOS',default=>'AGENTES CIVILES'}]);

                // Interceptar texto de Excel para transformarlo en ID numérico válido para la FK
                $finalExcelDestId = null;
                if ($destInt !== '') {
                    $finalExcelDestId = resolver_destino_interno_personal($pdo, $destInt);
                }

                // Para 'solo_nuevos': no actualizar si ya existe
                if ($accionImport === 'solo_nuevos') {
                    $stChk = $pdo->prepare("SELECT id FROM personal_unidad WHERE unidad_id=:u AND REPLACE(REPLACE(REPLACE(dni,'.',''),'-',''),' ','')=:d LIMIT 1");
                    $stChk->execute([':u'=>$unidadActiva,':d'=>$dni]);
                    $existeId = (int)($stChk->fetchColumn() ?: 0);
                    if ($existeId > 0) {
                        try {
                            $pdo->prepare("INSERT IGNORE INTO personal_ciclos (unidad_id,ciclo,personal_id,estado,created_by_id) VALUES (:u,:c,:p,'activo',:cb)")
                                ->execute([':u'=>$unidadActiva,':c'=>$cicloImport,':p'=>$existeId,':cb'=>$personalId?:null]);
                        } catch (Throwable $e) {}
                        $procesadas++; continue;
                    }
                }

                $pdo->prepare("
                    INSERT INTO personal_unidad
                      (unidad_id,dni,grado,arma,apellido_nombre,cuil,sexo,domicilio,destino_interno,rol_combate,rol_administrativo,observaciones,jerarquia,extra_json,updated_at,updated_by_id)
                    VALUES
                      (:uid,:dni,:grado,:arma,:apnom,:cuil,:sexo,:dom,:dest,:rol_combate,:rol_administrativo,:obs,:jer,:xj,NOW(),:ubid)
                    ON DUPLICATE KEY UPDATE
                      grado=VALUES(grado),arma=VALUES(arma),apellido_nombre=VALUES(apellido_nombre),
                      cuil=IF(VALUES(cuil) IS NOT NULL AND VALUES(cuil)!='',VALUES(cuil),cuil),
                      sexo=IF(VALUES(sexo) IS NOT NULL AND VALUES(sexo)!='',VALUES(sexo),sexo),
                      domicilio=IF(VALUES(domicilio) IS NOT NULL AND VALUES(domicilio)!='',VALUES(domicilio),domicilio),
                      destino_interno=VALUES(destino_interno),
                      rol_combate=IF(VALUES(rol_combate) IS NOT NULL AND VALUES(rol_combate)!='',VALUES(rol_combate),rol_combate),
                      rol_administrativo=IF(VALUES(rol_administrativo) IS NOT NULL AND VALUES(rol_administrativo)!='',VALUES(rol_administrativo),rol_administrativo),
                      observaciones=IF(VALUES(observaciones) IS NOT NULL AND VALUES(observaciones)!='',VALUES(observaciones),observaciones),
                      jerarquia=VALUES(jerarquia),
                      extra_json=IF(extra_json IS NULL,VALUES(extra_json),JSON_MERGE_PATCH(extra_json,VALUES(extra_json))),
                      updated_at=NOW(),updated_by_id=VALUES(updated_by_id)
                ")->execute([
                    ':uid'=>$unidadActiva,':dni'=>$dni,
                    ':grado'=>$gradoRaw?:null,':arma'=>$arma?:null,':apnom'=>$apnom?:null,
                    ':cuil'=>$cuil?:null,':sexo'=>$sexo?:null,':dom'=>$domicilio?:null,
                    ':dest'=>$finalExcelDestId,':rol_combate'=>$rolCombate?:null,':rol_administrativo'=>$rolAdministrativo?:null,':obs'=>$obs?:null,
                    ':jer'=>$jerEnum,':xj'=>$extraJson,':ubid'=>$personalId?:null,
                ]);

                $stGid = $pdo->prepare("SELECT id FROM personal_unidad WHERE unidad_id=:u AND REPLACE(REPLACE(REPLACE(dni,'.',''),'-',''),' ','')=:d LIMIT 1");
                $stGid->execute([':u'=>$unidadActiva,':d'=>$dni]);
                $pid = (int)($stGid->fetchColumn() ?: 0);

                if ($pid > 0) {
                    $pdo->prepare("INSERT INTO personal_ciclos (unidad_id,ciclo,personal_id,estado,created_by_id)
                                   VALUES (:u,:c,:p,'activo',:cb)
                                   ON DUPLICATE KEY UPDATE estado='activo',updated_at=NOW()")
                        ->execute([':u'=>$unidadActiva,':c'=>$cicloImport,':p'=>$pid,':cb'=>$personalId?:null]);
                }
                $procesadas++;
            }

            $pdo->commit();
            $modoLabel = ['actualizar'=>'Actualizar/agregar','solo_nuevos'=>'Solo nuevos','reemplazar_ciclo'=>'Reemplazar ciclo'][$accionImport] ?? $accionImport;
            $mensajeImport = "✓ Importación completada — Modo: {$modoLabel} · Ciclo: {$cicloImport} · Procesadas: {$procesadas}"
                . (count($errores) ? ' · Avisos: '.implode('; ',array_slice($errores,0,5)) : '');

        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $mensajeImportError = $ex->getMessage();
        }
    }
}

/* ════════════════════════════════════ CARGA CATÁLOGO COMPLETO PARA LOS SELECTORES ════════════════════════════════════ */
$todosLosDestinosInternos = [];
try {
    $stDst = $pdo->query("SELECT id, nombre FROM destino_interno WHERE COALESCE(estado,'ACTIVO')='ACTIVO' ORDER BY nombre ASC");
    $todosLosDestinosInternos = $stDst->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {}

$destinos = [];
try {
    $st = $pdo->prepare("
        SELECT DISTINCT di.nombre
        FROM personal_unidad pu
        INNER JOIN destino_interno di ON di.id = pu.destino_interno
        WHERE pu.unidad_id = :u
        ORDER BY di.nombre ASC
    ");
    $st->execute([':u' => $unidadActiva]);
    $destinos = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {}

/* ════════════════════════════════════ QUERY PRINCIPAL AJUSTADA A LA LLAVE FORÁNEA ════════════════════════════════════ */
$where  = ["pu.unidad_id = :uid"];
$joinCiclo = '';
$params = [':uid' => $unidadActiva]; 
if ($cicloFiltro !== '') {
    $joinCiclo = "INNER JOIN personal_ciclos pc ON pc.personal_id = pu.id AND pc.unidad_id = pu.unidad_id AND pc.ciclo = :ciclo";
    $params[':ciclo'] = $cicloFiltro;
}

if ($q !== '') {
    $where[] = "(pu.apellido_nombre LIKE :q_nombre OR pu.dni LIKE :q_dni OR pu.grado LIKE :q_grado)";
    $params[':q_nombre'] = '%' . $q . '%';
    $params[':q_dni'] = '%' . $q . '%';
    $params[':q_grado'] = '%' . $q . '%';
}
if ($filtroJer !== '') {
    $where[] = "pu.jerarquia = :jer";
    $params[':jer'] = $filtroJer;
}
if ($filtroDst !== '' && $filtroDst !== '0') {
    $where[] = "di.nombre = :dst";
    $params[':dst'] = $filtroDst;
}
if ($filtroPte === '1') {
    $where[] = "pu.tiene_parte_enfermo = 1";
} elseif ($filtroPte === '0') {
    $where[] = "pu.tiene_parte_enfermo = 0";
}

$whereSQL = implode(' AND ', $where);

$sql = "
    SELECT
        pu.id, pu.jerarquia, pu.grado, pu.arma,
        pu.apellido_nombre, pu.dni, pu.cuil, pu.fecha_nac,
        pu.peso, pu.altura, pu.sexo, pu.domicilio,
        pu.estado_civil, pu.hijos, pu.nou,
        pu.nro_cta, pu.cbu, pu.alias_banco,
        pu.usuario_intranet, pu.usuario_gde,
        pu.fecha_ultimo_anexo27,
        pu.tiene_parte_enfermo, pu.parte_enfermo_desde, pu.parte_enfermo_hasta,
        pu.cantidad_parte_enfermo,
        pu.destino_interno AS destino_interno_id, pu.funcion,
        di.nombre AS destino_interno_nombre_rel,
        pu.telefono, pu.correo,
        pu.rol_combate, pu.rol_administrativo, pu.anios_en_destino, pu.fracc, pu.observaciones,
        pu.fecha_alta,
        d.codigo AS destino_codigo, d.nombre AS destino_nombre,
        pu.destino_id
    FROM personal_unidad pu
    LEFT JOIN destino d ON d.id = pu.destino_id
    LEFT JOIN destino_interno di ON di.id = pu.destino_interno
    $joinCiclo
    WHERE $whereSQL
    ORDER BY
        $SQL_ORDEN_JERARQUIA,
        $SQL_ORDEN_GRADO,
        pu.apellido_nombre ASC
";

$personal = [];
$mensajeError = '';
try {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $personal = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $ex) {
    $mensajeError = $ex->getMessage();
}

/* ════════════════════════════════════ TOTALES ════════════════════════════════════ */
$totalOficial    = 0; $totalSuboficial = 0; $totalSoldado = 0; $totalCivil = 0; $totalParte = 0;
foreach ($personal as $p) {
    switch ($p['jerarquia'] ?? '') {
        case 'OFICIAL':      $totalOficial++;    break;
        case 'SUBOFICIAL':   $totalSuboficial++; break;
        case 'SOLDADO':      $totalSoldado++;    break;
        case 'AGENTE_CIVIL': $totalCivil++;      break;
    }
    if ((int)($p['tiene_parte_enfermo'] ?? 0) === 1) $totalParte++;
}
$totalGeneral = count($personal);

/* ════════════════════════════════════ EXPORT XLSX ════════════════════════════════════ */
if ($exportar === 'xlsx') {
    $vendorAuto = $ROOT . '/vendor/autoload.php';
    if (!is_file($vendorAuto)) {
        die('Falta vendor/autoload.php. Ejecutá: composer require phpoffice/phpspreadsheet');
    }
    require_once $vendorAuto;

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Personal');

    // Encabezados
    $headers = [
        'GRADO','ARMA/ESPEC','APELLIDO Y NOMBRE','DNI','CUIL','FECHA NAC',
        'PESO','ALTURA','SEXO','DOMICILIO','ESTADO CIVIL','HIJOS','NOU',
        'NRO CTA BANCO','CBU BANCO','ALIAS BANCO','USUARIO INTRANET','USUARIO GDE','FECHA ULTIMO ANEXO 27',
        'TIENE PARTE DE ENFERMO','DESDE','HASTA','CANTIDAD DE PARTE DE ENFERMO',
        'DESTINO INTERNO','ROL COMBATE','ROL ADMINISTRATIVO','ANIOS EN DESTINO','FRACC','OBSERVACIONES'
    ];
    foreach ($headers as $ci => $h) {
        $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci + 1);
        $sheet->setCellValue($col . '1', $h);
        $sheet->getStyle($col . '1')->getFont()->setBold(true);
        $sheet->getStyle($col . '1')->getFill()
              ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
              ->getStartColor()->setARGB('FF1a1a2e');
        $sheet->getStyle($col . '1')->getFont()->getColor()->setARGB('FFFFFFFF');
    }
    $lastExportCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));

    $jerColors = [
        'OFICIAL'     => 'FFe8eaf6',
        'SUBOFICIAL'  => 'FFfff8e1',
        'SOLDADO'     => 'FFe8f5e9',
        'AGENTE_CIVIL'=> 'FFeceff1',
    ];

    $row = 2;
    $jerActualXlsx = null;
    foreach ($personal as $i => $p) {
        $jer = $p['jerarquia'] ?? '';

        if ($jer !== $jerActualXlsx) {
            $jerActualXlsx = $jer;
            $jerLabelsXlsx = ['OFICIAL'=>'OFICIALES','SUBOFICIAL'=>'SUBOFICIALES','SOLDADO'=>'SOLDADOS','AGENTE_CIVIL'=>'AGENTES CIVILES'];
            $sheet->setCellValue('A' . $row, $jerLabelsXlsx[$jer] ?? strtoupper($jer));
            $sheet->mergeCells('A' . $row . ':' . $lastExportCol . $row);
            $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(10);
            $sheet->getStyle('A' . $row . ':' . $lastExportCol . $row)->getFill()
                  ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                  ->getStartColor()->setARGB('FF2d3561');
            $sheet->getStyle('A' . $row)->getFont()->getColor()->setARGB('FFFFFFFF');
            $row++;
        }

        $tieneParte = (int)($p['tiene_parte_enfermo'] ?? 0) === 1;
        $fechaNac = !empty($p['fecha_nac']) ? fmt_date((string)$p['fecha_nac']) : '';
        $fechaAnexo27 = !empty($p['fecha_ultimo_anexo27']) ? fmt_date((string)$p['fecha_ultimo_anexo27']) : '';
        $parteDesde = !empty($p['parte_enfermo_desde']) ? fmt_date((string)$p['parte_enfermo_desde']) : '';
        $parteHasta = !empty($p['parte_enfermo_hasta']) ? fmt_date((string)$p['parte_enfermo_hasta']) : '';

        $rowData = [
            $p['grado'] ?? '',
            $p['arma'] ?? '',
            $p['apellido_nombre'] ?? '',
            $p['dni'] ?? '',
            $p['cuil'] ?? '',
            $fechaNac,
            $p['peso'] ?? '',
            $p['altura'] ?? '',
            $p['sexo'] ?? '',
            $p['domicilio'] ?? '',
            $p['estado_civil'] ?? '',
            $p['hijos'] ?? '',
            $p['nou'] ?? '',
            $p['nro_cta'] ?? '',
            $p['cbu'] ?? '',
            $p['alias_banco'] ?? '',
            $p['usuario_intranet'] ?? '',
            $p['usuario_gde'] ?? '',
            $fechaAnexo27,
            $tieneParte ? 'SI' : 'NO',
            $parteDesde,
            $parteHasta,
            $p['cantidad_parte_enfermo'] ?? '',
            $p['destino_interno_nombre_rel'] ?? '',
            $p['rol_combate'] ?? '',
            $p['rol_administrativo'] ?? '',
            $p['anios_en_destino'] ?? '',
            $p['fracc'] ?? '',
            $p['observaciones'] ?? '',
        ];

        foreach ($rowData as $ci => $val) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci + 1);
            $sheet->setCellValue($col . $row, $val);
        }

        $bgColor = $tieneParte ? 'FFfff3cd' : ($jerColors[$jer] ?? 'FFFFFFFF');
        $sheet->getStyle('A' . $row . ':' . $lastExportCol . $row)->getFill()
              ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
              ->getStartColor()->setARGB($bgColor);

        $row++;
    }

    $sheet->setCellValue('A' . $row, "Total: {$totalGeneral} efectivos — Oficiales: {$totalOficial} · Suboficiales: {$totalSuboficial} · Soldados: {$totalSoldado} · Ag. Civiles: {$totalCivil}");
    $sheet->mergeCells('A' . $row . ':' . $lastExportCol . $row);
    $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setItalic(true)->setSize(8);

    foreach (range(1, 27) as $ci) {
        $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci);
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    $filename = 'personal_' . date('Ymd_His') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

/* ════════════════════════════════════ EXPORT PDF ════════════════════════════════════ */
if ($exportar === 'pdf') {
    $fecha_doc = date('d/m/Y');
    $hora_doc  = date('H:i');
    $lugarFirma = 'San Carlos de Bariloche';

    ?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Nomina de Personal - <?= e($NOMBRE) ?></title>
<style>
  @page { size: A4 landscape; margin: 18mm 14mm 18mm 14mm; }
  * { box-sizing: border-box; }
  body { font-family: "Times New Roman", Times, serif; font-size: 12px; color: #000; background: #fff; margin: 0; }
  .topline { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 18px; }
  .topline-left { font-size: 13px; line-height: 1.2; }
  .topline-left strong { display: block; font-size: 16px; }
  .topline-left span { font-style: italic; }
  .topline-right { font-size: 13px; font-style: italic; text-align: right; white-space: nowrap; }
  .title-wrap { text-align: center; margin: 18px 0 18px; }
  .title-wrap h1 { margin: 0; font-size: 16px; text-decoration: underline; font-weight: 700; }
  .title-wrap .sub { margin-top: 4px; font-size: 12px; }
  table { width: 100%; border-collapse: collapse; margin-top: 8px; }
  th, td { border: 1px solid #666; padding: 6px 7px; vertical-align: top; }
  th { background: #d9d9d9; text-align: center; font-weight: 700; }
  td { font-size: 12px; }
  .nro { width: 48px; text-align: center; }
  .grado { width: 70px; text-align: center; }
  .dni { width: 90px; text-align: center; }
  .parte { width: 85px; text-align: center; }
  .obs { font-size: 11px; font-style: italic; color: #222; }
  .footer { margin-top: 10px; text-align: right; font-size: 12px; }
  .no-print { margin-bottom: 10px; padding: 10px 12px; background: #f1f5f9; font-family: Arial, Helvetica, sans-serif; }
  @media print {
    .no-print { display: none !important; }
    th { background: #d9d9d9 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    tr { page-break-inside: avoid; }
  }
</style>
</head>
<body>

<div class="no-print">
  <button onclick="window.print()" style="background:#16a34a;color:#fff;border:none;padding:8px 18px;font-size:13px;font-weight:bold;border-radius:6px;cursor:pointer;">
    Imprimir / Guardar como PDF
  </button>
  <span style="margin-left:10px;font-size:12px;">Ctrl+P -> Guardar como PDF</span>
  <a href="personal_lista.php" style="float:right;color:#0369a1;font-size:12px;">Volver a la lista</a>
</div>

<div class="topline">
  <div class="topline-left">
    <strong>Ejercito Argentino</strong>
    <span><?= e($NOMBRE) ?></span>
  </div>
  <div class="topline-right">
    "ANO DE LA GRANDEZA ARGENTINA"
  </div>
</div>

<div class="title-wrap">
  <h1>Nomina de personal</h1>
  <div class="sub"><?= e($LEYENDA !== '' ? $LEYENDA : $NOMBRE) ?></div>
</div>

<table>
  <thead>
    <tr>
      <th class="nro">Nro</th>
      <th>Grado</th>
      <th>Arma</th>
      <th>Apellido y nombre</th>
      <th>DNI</th>
      <th>Destino</th>
      <th>Funcion / destino interno</th>
      <th>Parte</th>
    </tr>
  </thead>
  <tbody>
  <?php
    $nroGlobal = 1;
    foreach ($personal as $p):
        $tieneParte = (int)($p['tiene_parte_enfermo'] ?? 0) === 1;
        $destLabel = trim(($p['destino_codigo'] ?? '') . ' ' . ($p['destino_nombre'] ?? ''));
        
        $lblDestInt = !empty($p['destino_interno_nombre_rel']) ? $p['destino_interno_nombre_rel'] : '';
        if ($destLabel === '') $destLabel = (string)($lblDestInt !== '' ? $lblDestInt : '-');
        
        $funcionDestino = trim((string)($p['funcion'] ?? ''));
        if ($lblDestInt !== '') {
            $funcionDestino = $funcionDestino !== '' ? $funcionDestino . ' / ' . $lblDestInt : (string)$lblDestInt;
        }
  ?>
    <tr>
      <td class="nro"><?= $nroGlobal ?></td>
      <td class="grado"><?= e($p['grado'] ?? '') ?></td>
      <td><?= e($p['arma'] ?? '') ?></td>
      <td><?= e($p['apellido_nombre'] ?? '') ?></td>
      <td class="dni"><?= e($p['dni'] ?? '') ?></td>
      <td><?= e($destLabel) ?></td>
      <td>
        <?= e($funcionDestino !== '' ? $funcionDestino : '-') ?>
        <?php if (!empty($p['observaciones'])): ?>
          <div class="obs"><?= e((string)$p['observaciones']) ?></div>
        <?php endif; ?>
      </td>
      <td class="parte">
        <?php if ($tieneParte): ?>
          CON PARTE
          <?php if (!empty($p['parte_enfermo_desde'])): ?>
            <div class="obs">desde <?= e(fmt_date((string)$p['parte_enfermo_desde'])) ?></div>
          <?php endif; ?>
        <?php else: ?>
          -
        <?php endif; ?>
      </td>
    </tr>
  <?php $nroGlobal++; endforeach; ?>
  </tbody>
</table>

<div class="footer">
  <?= e($lugarFirma) ?>, <?= e($fecha_doc) ?>.
</div>

</body>
</html>
<?php
    exit;
}

/* ════════════════════════════════════ HTML NORMAL ════════════════════════════════════ */
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Personal - Lista</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= e($ASSETS_WEB) ?>/css/theme-602.css">
<link rel="icon" href="<?= e($ESCUDO) ?>">
<style>
  body {
    background: url("<?= e($IMG_BG) ?>") no-repeat center center fixed;
    background-size: cover; background-color: #020617;
    color: #e5e7eb; font-family: system-ui,-apple-system,"Segoe UI",sans-serif; margin:0; padding:0;
  }
  .page-wrap { padding: 16px; }
  .container-main { max-width: 1700px; margin: auto; }
  .panel {
    background: rgba(15,17,23,.94); border: 1px solid rgba(148,163,184,.38);
    border-radius: 18px; padding: 18px 22px;
    box-shadow: 0 18px 40px rgba(0,0,0,.75), inset 0 1px 0 rgba(255,255,255,.04);
  }
  .brand-hero  { padding: 10px 0; }
  .hero-inner  { display:flex; align-items:center; justify-content:space-between; gap:12px; }
  .help-small  { font-size: .78rem; color: #b7c3d6; }

  /* Filtros */
  .filtro-bar {
    background: rgba(15,23,42,.9); border: 1px solid rgba(148,163,184,.28);
    border-radius: 12px; padding: 12px 14px; margin-bottom: 14px;
  }
  .form-control, .form-select {
    background: rgba(255,255,255,.07); border: 1px solid rgba(148,163,184,.28); color: #e5e7eb;
  }
  .form-control:focus, .form-select:focus {
    background: rgba(255,255,255,.1); color: #fff;
    border-color: rgba(120,170,255,.5); box-shadow: none;
  }
  .form-select option { background: #0f172a; color: #e5e7eb; }
  .form-label { font-size: .76rem; color: #94a3b8; margin-bottom: .25rem; }

  /* Estadisticas */
  .stat-card {
    background: rgba(15,23,42,.85); border: 1px solid rgba(148,163,184,.28);
    border-radius: 10px; padding: 10px 14px; text-align: center;
  }
  .stat-card .num { font-size: 1.6rem; font-weight: 900; line-height: 1; }
  .stat-card .lbl { font-size: .72rem; color: #9ca3af; margin-top: 2px; }

  /* Tabla */
  .tbl-wrap { overflow-x: auto; }
  .tbl {
    --bs-table-bg: rgba(15,23,42,.88);
    --bs-table-border-color: rgba(148,163,184,.25);
    color: #e5e7eb; font-size: .8rem; min-width: 900px;
  }
  .tbl thead th {
    background: rgba(30,41,59,.98) !important;
    color: #93c5fd; font-size: .74rem; font-weight: 800;
    border-bottom: 2px solid rgba(59,130,246,.4) !important;
    padding: .5rem .6rem; white-space: nowrap; position: sticky; top: 0; z-index: 2;
  }
  .tbl td { padding: .38rem .6rem; vertical-align: middle; border-color: rgba(148,163,184,.15) !important; }
  .tbl tbody tr:hover td { background: rgba(59,130,246,.08) !important; }
  .tbl th:nth-child(1), .tbl td:nth-child(1) { width: 44px; text-align: center; }
  .tbl th:nth-child(2), .tbl td:nth-child(2) { width: 110px; }
  .tbl th:nth-child(3), .tbl td:nth-child(3) { width: 150px; }
  .tbl th:nth-child(4), .tbl td:nth-child(4) { min-width: 290px; }
  .tbl th:nth-child(5), .tbl td:nth-child(5) { width: 120px; }
  .tbl th:nth-child(6), .tbl td:nth-child(6) { min-width: 340px; }
  .tbl th:last-child, .tbl td:last-child { width: 110px; }
  .tbl .dni-cell { font-size: .86rem; letter-spacing: .02em; }
  .tbl .acciones-cell { white-space: nowrap; }
  .destino-edit-select {
    min-width: 240px;
    font-size: .78rem;
    font-weight: 700;
    background-color: rgba(30, 41, 59, 0.95);
    color: #fff;
  }
  .destino-nuevo-inline {
    display: none;
    margin-top: 5px;
    font-size: .75rem;
    background: rgba(15, 23, 42, 0.95);
    border: 1px solid #f87171 !important;
    color: #fff;
  }

  /* Jerarquia separador */
  .jer-row td {
    background: rgba(30,41,59,.98) !important;
    color: #7dd3fc; font-weight: 900; font-size: .78rem;
    letter-spacing: .06em; border-top: 2px solid rgba(59,130,246,.4) !important;
    padding: .45rem .8rem !important;
  }

  /* Badges */
  .badge-jer {
    display: inline-block; padding: .15rem .45rem; border-radius: 4px;
    font-size: .65rem; font-weight: 800; letter-spacing: .03em;
  }
  .badge-of  { background: rgba(99,102,241,.25); border: 1px solid rgba(99,102,241,.5); color: #a5b4fc; }
  .badge-sof { background: rgba(245,158,11,.2);  border: 1px solid rgba(245,158,11,.4); color: #fcd34d; }
  .badge-sol { background: rgba(34,197,94,.15);  border: 1px solid rgba(34,197,94,.35); color: #86efac; }
  .badge-cv  { background: rgba(148,163,184,.15);border: 1px solid rgba(148,163,184,.3);color: #cbd5e1; }

  .badge-parte {
    background: rgba(239,68,68,.2); border: 1px solid rgba(239,68,68,.5);
    color: #fca5a5; border-radius: 4px; padding: .1rem .4rem;
    font-size: .65rem; font-weight: 800;
  }

  /* Con parte - fila destacada */
  .fila-parte td { background: rgba(239,68,68,.06) !important; }

  /* Nombre clickable */
  .nombre-link {
    color: #e5e7eb; font-weight: 700; text-decoration: none;
  }
  .nombre-link:hover { color: #7dd3fc; }

  /* Observaciones truncadas */
  .obs-cell { max-width: 160px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; cursor: help; }

  /* Botones de accion */
  .btn-accion {
    background: none; border: 1px solid rgba(148,163,184,.3); color: #94a3b8;
    border-radius: 6px; padding: .2rem .5rem; font-size: .72rem; cursor: pointer; transition: all .15s;
  }
  .btn-accion:hover { border-color: #7dd3fc; color: #7dd3fc; }
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
      <a href="../inicio.php" class="btn btn-success btn-sm fw-bold">Inicio</a>
    </div>
  </div>
</header>

<div class="page-wrap"><div class="container-main">
<div class="panel">

<?php if ($mensajeError !== ''): ?>
  <div class="alert alert-danger py-2"><?= e($mensajeError) ?></div>
<?php endif; ?>
<?php if ($mensajeImport !== ''): ?>
  <div class="alert alert-success py-2"><?= e($mensajeImport) ?></div>
<?php endif; ?>
<?php if ($mensajeImportError !== ''): ?>
  <div class="alert alert-danger py-2"><b>Error de importacion:</b> <?= e($mensajeImportError) ?></div>
<?php endif; ?>
<?php if ($mensajeDestino !== ''): ?>
  <div class="alert alert-success py-2"><?= e($mensajeDestino) ?></div>
<?php endif; ?>
<?php if ($mensajeDestinoError !== ''): ?>
  <div class="alert alert-danger py-2"><b>Error al guardar destinos:</b> <?= e($mensajeDestinoError) ?></div>
<?php endif; ?>

<!-- ENCABEZADO + ESTADISTICAS -->
<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
  <div>
    <div style="font-weight:900;font-size:1.1rem;"><i class="bi bi-people-fill me-2 text-info"></i>Nomina de personal</div>
    <div class="help-small">
      Ordenada por jerarquia y grado - <b><?= $totalGeneral ?></b> efectivos
      <?php if ($cicloFiltro !== ''): ?>
        - <span style="color:#7dd3fc;font-weight:700;">Ciclo <?= e($cicloFiltro) ?></span>
      <?php elseif (!empty($ciclosDisponibles)): ?>
        - <span style="color:#9ca3af;">Todos los ciclos</span>
      <?php endif; ?>
    </div>
  </div>
  <!-- Selector de ciclo + botones -->
  <div class="d-flex flex-wrap gap-2 align-items-center">

    <!-- Selector de ano/ciclo -->
    <?php if (!empty($ciclosDisponibles)): ?>
    <div class="d-flex align-items-center gap-1" style="background:rgba(15,23,42,.8);border:1px solid rgba(148,163,184,.3);border-radius:8px;padding:.3rem .6rem;">
      <i class="bi bi-calendar2-week text-info" style="font-size:.85rem;"></i>
      <span style="font-size:.75rem;color:#94a3b8;">Ciclo:</span>
      <div class="d-flex gap-1 flex-wrap">
        <a href="?<?= http_build_query(array_merge(array_filter($_GET,fn($k)=>$k!=='ciclo',ARRAY_FILTER_USE_KEY),['ciclo'=>'todos'])) ?>"
           class="<?= $cicloFiltro === '' ? 'btn btn-xs' : 'btn btn-xs' ?>"
           style="padding:.15rem .5rem;font-size:.7rem;font-weight:700;border-radius:4px;text-decoration:none;
                  <?= $cicloFiltro === '' ? 'background:rgba(59,130,246,.3);border:1px solid rgba(59,130,246,.6);color:#7dd3fc;' : 'background:rgba(255,255,255,.05);border:1px solid rgba(148,163,184,.2);color:#9ca3af;' ?>">
          Todos
        </a>
        <?php foreach($ciclosDisponibles as $cy): ?>
        <a href="?<?= http_build_query(array_merge(array_filter($_GET,fn($k)=>$k!=='ciclo',ARRAY_FILTER_USE_KEY),['ciclo'=>$cy])) ?>"
           style="padding:.15rem .5rem;font-size:.7rem;font-weight:700;border-radius:4px;text-decoration:none;
                  <?= $cicloFiltro == $cy ? 'background:rgba(59,130,246,.3);border:1px solid rgba(59,130,246,.6);color:#7dd3fc;' : 'background:rgba(255,255,255,.05);border:1px solid rgba(148,163,184,.2);color:#9ca3af;' ?>">
          <?= e($cy) ?>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php elseif(empty($ciclosDisponibles)): ?>
    
    <?php endif; ?>

    <?php if ($esAdmin): ?>
    <button class="btn btn-sm btn-outline-primary fw-bold" data-bs-toggle="modal" data-bs-target="#modalImport">
      <i class="bi bi-upload me-1"></i> Importar Excel
    </button>
    <a href="personal_ficha.php" class="btn btn-sm btn-outline-info fw-bold">
      <i class="bi bi-person-vcard me-1"></i> Fichas
    </a>
    <?php endif; ?>
    <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'xlsx'])) ?>"
       class="btn btn-sm btn-outline-success fw-bold">
      <i class="bi bi-file-earmark-excel me-1"></i> Excel
    </a>
    <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'pdf'])) ?>"
       target="_blank" class="btn btn-sm btn-outline-warning fw-bold">
      <i class="bi bi-filetype-pdf me-1"></i> PDF
    </a>
  </div>
</div>

<!-- ESTADISTICAS -->
<div class="row g-2 mb-3">
  <div class="col-6 col-sm-3 col-lg">
    <div class="stat-card">
      <div class="num" style="color:#a5b4fc;"><?= $totalOficial ?></div>
      <div class="lbl">Oficiales</div>
    </div>
  </div>
  <div class="col-6 col-sm-3 col-lg">
    <div class="stat-card">
      <div class="num" style="color:#fcd34d;"><?= $totalSuboficial ?></div>
      <div class="lbl">Suboficiales</div>
    </div>
  </div>
  <?php if ($totalSoldado > 0): ?>
  <div class="col-6 col-sm-3 col-lg">
    <div class="stat-card">
      <div class="num" style="color:#86efac;"><?= $totalSoldado ?></div>
      <div class="lbl">Soldados</div>
    </div>
  </div>
  <?php endif; ?>
  <?php if ($totalCivil > 0): ?>
  <div class="col-6 col-sm-3 col-lg">
    <div class="stat-card">
      <div class="num" style="color:#94a3b8;"><?= $totalCivil ?></div>
      <div class="lbl">Ag. Civiles</div>
    </div>
  </div>
  <?php endif; ?>
  <div class="col-6 col-sm-3 col-lg">
    <div class="stat-card">
      <div class="num" style="color:#fca5a5;"><?= $totalParte ?></div>
      <div class="lbl">Con parte</div>
    </div>
  </div>
  <div class="col-6 col-sm-3 col-lg">
    <div class="stat-card" style="border-color:rgba(34,197,94,.3);">
      <div class="num" style="color:#4ade80;"><?= $totalGeneral ?></div>
      <div class="lbl">Total</div>
    </div>
  </div>
</div>

<!-- FILTROS -->
<form method="get" class="filtro-bar">
  <div class="row g-2 align-items-end">
    <div class="col-md-4 col-lg-3">
      <label class="form-label"><i class="bi bi-search me-1"></i>Buscar</label>
      <input class="form-control form-control-sm" name="q" value="<?= e($q) ?>" placeholder="Nombre, DNI, grado...">
    </div>
    <div class="col-md-3 col-lg-2">
      <label class="form-label">Jerarquia</label>
      <select class="form-select form-select-sm" name="jerarquia">
        <option value="">Todas</option>
        <?php foreach (['OFICIAL','SUBOFICIAL','SOLDADO','AGENTE_CIVIL'] as $j): ?>
          <option value="<?= $j ?>" <?= $filtroJer === $j ? 'selected' : '' ?>><?= $j ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3 col-lg-2">
      <label class="form-label">Area / Destino</label>
      <select class="form-select form-select-sm" name="destino">
        <option value="">Todos</option>
        <?php foreach ($destinos as $dst): ?>
          <option value="<?= e($dst['nombre'] ?? '') ?>" <?= $filtroDst === (string)($dst['nombre'] ?? '') ? 'selected' : '' ?>>
            <?= e($dst['nombre'] ?? '') ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2 col-lg-2">
      <label class="form-label">Parte enfermo</label>
      <select class="form-select form-select-sm" name="parte">
        <option value="">Todos</option>
        <option value="1" <?= $filtroPte === '1' ? 'selected' : '' ?>>Con parte</option>
        <option value="0" <?= $filtroPte === '0' ? 'selected' : '' ?>>Sin parte</option>
      </select>
    </div>
    <div class="col-auto d-flex gap-2">
      <button class="btn btn-sm btn-success fw-bold" type="submit">
        <i class="bi bi-funnel me-1"></i> Filtrar
      </button>
      <a href="personal_lista.php" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-x-lg"></i>
      </a>
    </div>
  </div>
</form>

<!-- TABLA -->
<?php if (!$personal): ?>
  <div class="text-center text-muted py-5">
    <i class="bi bi-person-slash" style="font-size:2.5rem;opacity:.3;"></i>
    <div class="mt-2">No hay personal con los filtros aplicados.</div>
  </div>
<?php else: ?>

<?php if ($esAdmin): ?>
<form method="post">
  <div class="d-flex justify-content-end mb-2">
    <button type="submit" name="guardar_destinos" class="btn btn-sm btn-success fw-bold">
      <i class="bi bi-save me-1"></i> Guardar destinos internos
    </button>
  </div>
<?php endif; ?>

<div class="tbl-wrap">
  <table class="table table-sm tbl mb-0">
    <thead>
      <tr>
        <th style="width:32px;">#</th>
        <th>Grado</th>
        <th>Arma/Cuerpo</th>
        <th>Apellido y Nombre</th>
        <th>DNI</th>
        <th>Destino interno</th>
        <th>Funcion</th>
        <?php if ($esAdmin): ?><th class="text-end">Accion</th><?php endif; ?>
      </tr>
    </thead>
    <tbody>
    <?php
      $jerActual = null;
      $nro = 0;
      $jerBadgeMap = ['OFICIAL'=>'badge-of','SUBOFICIAL'=>'badge-sof','SOLDADO'=>'badge-sol','AGENTE_CIVIL'=>'badge-cv'];
      $jerLabels   = ['OFICIAL'=>'OFICIALES','SUBOFICIAL'=>'SUBOFICIALES','SOLDADO'=>'SOLDADOS','AGENTE_CIVIL'=>'AGENTES CIVILES'];
      $cols = 7 + ($esAdmin ? 1 : 0);

      foreach ($personal as $p):
          $jer       = $p['jerarquia'] ?? '';
          $tieneParte = (int)($p['tiene_parte_enfermo'] ?? 0) === 1;
          $funcDest = trim((string)($p['funcion'] ?? ''));
          $destinoInternoId = $p['destino_interno_id'];
          $badgeClass = $jerBadgeMap[$jer] ?? 'badge-cv';

          if ($jer !== $jerActual):
              $jerActual = $jer;
              $nro = 0;
    ?>
      <tr class="jer-row">
        <td colspan="<?= $cols ?>">
          <span class="badge-jer <?= $badgeClass ?>">
            <?= e($jerLabels[$jer] ?? strtoupper($jer)) ?>
          </span>
        </td>
      </tr>
    <?php endif; $nro++; ?>
      <tr class="<?= $tieneParte ? 'fila-parte' : '' ?>">
        <td style="color:#6b7280;font-size:.72rem;text-align:center;"><?= $nro ?></td>
        <td>
          <span class="badge-jer <?= $badgeClass ?>"><?= e($p['grado'] ?? '-') ?></span>
        </td>
        <td style="color:#94a3b8;"><?= e($p['arma'] ?? '') ?></td>
        <td>
          <a class="nombre-link" href="personal_ficha.php?id=<?= (int)$p['id'] ?>&tab=ficha">
            <?= e($p['apellido_nombre'] ?? '') ?>
          </a>
        </td>
        <td class="dni-cell" style="font-family:monospace;color:#94a3b8;"><?= e($p['dni'] ?? '') ?></td>
        
        <!-- SELECTOR DINÁMICO DE CATALOGO ASOCIADO POR ID A LA CLAVE FORÁNEA -->
        <td>
          <?php if ($esAdmin): ?>
            <input type="hidden" name="ids[]" value="<?= (int)$p['id'] ?>">
            <select name="destino_interno_id[<?= (int)$p['id'] ?>]" class="form-select form-select-sm destino-edit-select" onchange="toggleNuevoDestinoInline(this, <?= (int)$p['id'] ?>)">
              <option value="">-- Sin Destino Asignado --</option>
              <?php foreach ($todosLosDestinosInternos as $di): ?>
                <option value="<?= (int)$di['id'] ?>" <?= ((int)$destinoInternoId === (int)$di['id']) ? 'selected' : '' ?>>
                  <?= e($di['nombre']) ?>
                </option>
              <?php endforeach; ?>
              <option value="NUEVO" style="color:#f87171; font-weight:bold;">+ -- Agregar nuevo destino --</option>
            </select>
            <input type="text" id="input_nuevo_dst_<?= (int)$p['id'] ?>" name="destino_interno_nuevo[<?= (int)$p['id'] ?>]" class="form-control form-control-sm destino-nuevo-inline" placeholder="Nombre del nuevo destino militar...">
          <?php else: ?>
            <span style="font-size:.76rem;color:#b7c3d6;">
              <?= e($p['destino_interno_nombre_rel'] ?? '-') ?>
            </span>
          <?php endif; ?>
        </td>
        
        <td>
          <?php if ($funcDest !== ''): ?>
            <span style="font-size:.76rem;color:#b7c3d6;"><?= e($funcDest) ?></span>
          <?php else: ?>
            <span class="text-muted">-</span>
          <?php endif; ?>
        </td>
        <?php if ($esAdmin): ?>
        <td class="text-end acciones-cell">
          <a href="personal_ficha.php?id=<?= (int)$p['id'] ?>&tab=ficha" class="btn-accion" title="Ver ficha">
            <i class="bi bi-person-vcard"></i>
          </a>
          <a href="personal_ficha.php?id=<?= (int)$p['id'] ?>&tab=sanidad" class="btn-accion" title="Sanidad"
             style="<?= $tieneParte ? 'border-color:rgba(239,68,68,.5);color:#fca5a5;' : '' ?>">
            <i class="bi bi-heart-pulse"></i>
          </a>
          <a href="personal_ficha.php?id=<?= (int)$p['id'] ?>&tab=eventos" class="btn-accion" title="Eventos">
            <i class="bi bi-calendar-event"></i>
          </a>
        </td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
    <!-- TOTALES PIE -->
    <tfoot>
      <tr style="background:rgba(15,23,42,.98)!important;">
        <td colspan="<?= $cols - 2 ?>" style="color:#6b7280;font-size:.74rem;padding:.5rem .6rem;">
          Oficiales: <b><?= $totalOficial ?></b> -
          Suboficiales: <b><?= $totalSuboficial ?></b>
          <?php if ($totalSoldado): ?> - Soldados: <b><?= $totalSoldado ?></b><?php endif; ?>
          <?php if ($totalCivil): ?> - Ag. Civiles: <b><?= $totalCivil ?></b><?php endif; ?>
          <?php if ($totalParte): ?> - <span style="color:#fca5a5;">Con parte: <b><?= $totalParte ?></b></span><?php endif; ?>
        </td>
        <td colspan="2" style="text-align:right;color:#4ade80;font-weight:900;font-size:.84rem;padding:.5rem .6rem;">
          Total: <?= $totalGeneral ?>
        </td>
      </tr>
    </tfoot>
  </table>
</div>
<?php if ($esAdmin): ?>
</form>
<?php endif; ?>

<?php endif; ?>

</div></div></div>


<?php if ($esAdmin): ?>
<!-- ════════════════════════════════════ MODAL IMPORTAR EXCEL ════════════════════════════════════ -->
<div class="modal fade" id="modalImport" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content" style="background:rgba(15,23,42,.98);color:#e5e7eb;border:1px solid rgba(148,163,184,.5);border-radius:14px;">
      <form method="post" enctype="multipart/form-data" id="formImport">
        <div class="modal-header" style="border-bottom:1px solid rgba(55,65,81,.8);">
          <h5 class="modal-title fw-bold"><i class="bi bi-upload me-2 text-primary"></i>Importar personal desde Excel</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body" style="overflow-y:auto;max-height:70vh;">

          <!-- ANO DEL CICLO -->
          <div class="mb-3 p-3" style="background:rgba(59,130,246,.1);border:1px solid rgba(59,130,246,.3);border-radius:8px;">
            <label style="font-size:.8rem;font-weight:800;color:#93c5fd;display:block;margin-bottom:6px;">
              <i class="bi bi-calendar2-week me-1"></i> Ano del ciclo
            </label>
            <div class="d-flex align-items-center gap-3 flex-wrap">
              <input type="number" name="ciclo_import" id="cicloImport"
                     class="form-control form-control-sm" style="width:100px;"
                     value="<?= date('Y') ?>" min="2020" max="2040" required>
              <div>
                <div style="font-size:.75rem;color:#9ca3af;">Ciclos existentes para esta unidad:</div>
                <div class="d-flex gap-1 flex-wrap mt-1">
                  <?php if (empty($ciclosDisponibles)): ?>
                    <span style="font-size:.72rem;color:#6b7280;">Ninguno todavia</span>
                  <?php else: foreach($ciclosDisponibles as $cy): ?>
                    <button type="button" onclick="document.getElementById('cicloImport').value='<?= (int)$cy ?>'"
                            style="background:rgba(59,130,246,.2);border:1px solid rgba(59,130,246,.4);color:#93c5fd;border-radius:4px;padding:.15rem .5rem;font-size:.72rem;font-weight:700;cursor:pointer;">
                      <?= e($cy) ?>
                    </button>
                  <?php endforeach; endif; ?>
                </div>
              </div>
            </div>
          </div>

          <!-- MODO DE IMPORTACION -->
          <div class="mb-3">
            <label style="font-size:.8rem;font-weight:800;color:#e5e7eb;display:block;margin-bottom:8px;">
              <i class="bi bi-gear me-1"></i> Modo de importacion
            </label>
            <div class="d-flex flex-column gap-2">

              <label class="d-flex align-items-start gap-2 p-3 rounded" style="background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.25);cursor:pointer;">
                <input type="radio" name="accion_import" value="actualizar" checked style="margin-top:2px;">
                <div>
                  <div style="font-weight:800;color:#86efac;font-size:.83rem;">
                    <i class="bi bi-arrow-repeat me-1"></i> Actualizar / agregar
                  </div>
                  <div style="font-size:.74rem;color:#9ca3af;">
                    Crea nuevos y actualiza los existentes (por DNI).
                    <b>No borra</b> a nadie que no este en el Excel.
                    Ideal para actualizaciones parciales durante el ano.
                  </div>
                </div>
              </label>

              <label class="d-flex align-items-start gap-2 p-3 rounded" style="background:rgba(59,130,246,.08);border:1px solid rgba(59,130,246,.25);cursor:pointer;">
                <input type="radio" name="accion_import" value="solo_nuevos" style="margin-top:2px;">
                <div>
                  <div style="font-weight:800;color:#7dd3fc;font-size:.83rem;">
                    <i class="bi bi-person-plus me-1"></i> Solo nuevos (soldados ingresantes, etc.)
                  </div>
                  <div style="font-size:.74rem;color:#9ca3af;">
                    Inserta unicamente los DNI que <b>no existen</b> todavia. Los registros existentes no se modifican.
                    Usalo para incorporar soldados nuevos en mitad del ciclo.
                  </div>
                </div>
              </label>

              <label class="d-flex align-items-start gap-2 p-3 rounded modo-destructivo" style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.25);cursor:pointer;">
                <input type="radio" name="accion_import" value="reemplazar_ciclo" style="margin-top:2px;">
                <div>
                  <div style="font-weight:800;color:#fca5a5;font-size:.83rem;">
                    <i class="bi bi-exclamation-triangle me-1"></i> Reemplazar ciclo completo
                  </div>
                  <div style="font-size:.74rem;color:#9ca3af;">
                    Borra toda la relacion del ciclo elegido y la recarga desde el Excel.
                    Los datos en <code>personal_unidad</code> se actualizan pero <b>no se borran personas</b>.
                    Usalo al inicio de un ano nuevo (ej: 2027).
                  </div>
                  <!-- Campo de confirmacion - oculto hasta seleccionar -->
                  <div class="confirm-zone mt-2" style="display:none;">
                    <div style="font-size:.74rem;color:#f87171;margin-bottom:4px;">
                      Atencion: Escribi <b>CONFIRMAR</b> para continuar:
                    </div>
                    <input type="text" name="confirmacion_reemplazar" id="confirmInput"
                           class="form-control form-control-sm" placeholder="CONFIRMAR"
                           style="max-width:180px;background:rgba(239,68,68,.1);border-color:rgba(239,68,68,.4);color:#fca5a5;">
                  </div>
                </div>
              </label>

            </div>
          </div>

          <!-- ARCHIVO -->
          <div class="mb-3">
            <label style="font-size:.8rem;font-weight:800;color:#e5e7eb;display:block;margin-bottom:6px;">
              <i class="bi bi-file-earmark-excel me-1 text-success"></i> Archivo Excel (.xls / .xlsx)
            </label>
            <input type="file" name="excel_archivo" class="form-control form-control-sm"
                   accept=".xls,.xlsx" required>
            <div style="font-size:.73rem;color:#9ca3af;margin-top:4px;">
              El Excel debe venir con 26 columnas, de la A a la Z, y acepta filas separadoras de jerarquia
              (OFICIALES / SUBOFICIALES / SOLDADOS / AGENTES CIVILES).
            </div>
            <div class="mt-2 small" style="color:#cbd5e1;">
              <div><b>Columnas esperadas:</b></div>
              <div><b>A:</b> Grado (o separador de jerarquia)</div>
              <div><b>B:</b> Arma / Especialidad</div>
              <div><b>C:</b> Apellido y Nombre</div>
              <div><b>D:</b> DNI</div>
              <div><b>E:</b> CUIL</div>
              <div><b>F:</b> Fecha de nacimiento</div>
              <div><b>G:</b> Peso</div>
              <div><b>H:</b> Altura</div>
              <div><b>I:</b> Sexo</div>
              <div><b>J:</b> Domicilio</div>
              <div><b>K:</b> Estado civil</div>
              <div><b>L:</b> Hijos</div>
              <div><b>M:</b> NOU</div>
              <div><b>N:</b> Nro Cta Banco</div>
              <div><b>O:</b> CBU Banco</div>
              <div><b>P:</b> Alias Banco</div>
              <div><b>Q:</b> Fecha ultimo Anexo 27</div>
              <div><b>R:</b> Tiene parte de enfermo (SI/NO/1/0)</div>
              <div><b>S:</b> Desde</div>
              <div><b>T:</b> Hasta</div>
              <div><b>U:</b> Cantidad de parte de enfermo</div>
              <div><b>V:</b> Destino interno</div>
              <div><b>W:</b> Rol</div>
              <div><b>X:</b> Anos en destino</div>
              <div><b>Y:</b> Fraccion</div>
              <div><b>Z:</b> Observaciones</div>
            </div>
          </div>

          <!-- RESUMEN ciclo seleccionado -->
          <div id="cicloInfo" style="font-size:.74rem;color:#9ca3af;padding:.4rem .7rem;background:rgba(15,23,42,.6);border-radius:6px;border:1px solid rgba(148,163,184,.15);">
            <i class="bi bi-info-circle me-1"></i>
            Selecciona el ano arriba para ver si ya existe ese ciclo.
          </div>

        </div>
        <div class="modal-footer" style="border-top:1px solid rgba(55,65,81,.8);">
          <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-sm btn-primary fw-bold" id="btnImportarFinal">
            <i class="bi bi-upload me-1"></i> Importar
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// Datos de ciclos existentes para el info dinamico
const ciclosExistentes = <?= json_encode(array_map('intval', $ciclosDisponibles)) ?>;
const cicloConteos    = {}; // podria llenarse con AJAX, por ahora info basica

document.addEventListener('DOMContentLoaded', () => {
  const cicloInput   = document.getElementById('cicloImport');
  const cicloInfoBox = document.getElementById('cicloInfo');
  const confirmInput = document.getElementById('confirmInput');

  // Mostrar/ocultar campo de confirmacion segun radio seleccionado
  document.querySelectorAll('input[name="accion_import"]').forEach(radio => {
    radio.addEventListener('change', () => {
      document.querySelectorAll('.confirm-zone').forEach(z => z.style.display = 'none');
      if (radio.value === 'reemplazar_ciclo') {
        document.querySelector('.modo-destructivo .confirm-zone').style.display = 'block';
      }
    });
  });

  // Info dinamica del ciclo
  function updateCicloInfo() {
    const ciclo = parseInt(cicloInput?.value || 0);
    if (!cicloInfoBox) return;
    const existe = ciclosExistentes.includes(ciclo);
    if (existe) {
      cicloInfoBox.innerHTML = `<i class="bi bi-check-circle-fill text-warning me-1"></i>
        El ciclo <b>${ciclo}</b> ya existe para esta unidad.
        Con <b>Actualizar/agregar</b> se suman datos nuevos sin borrar nada.
        Con <b>Reemplazar</b> se borra y recarga el ciclo.`;
      cicloInfoBox.style.borderColor = 'rgba(251,191,36,.4)';
      cicloInfoBox.style.background  = 'rgba(251,191,36,.06)';
    } else {
      cicloInfoBox.innerHTML = `<i class="bi bi-plus-circle text-success me-1"></i>
        El ciclo <b>${ciclo}</b> es nuevo - se creara al importar.`;
      cicloInfoBox.style.borderColor = 'rgba(34,197,94,.3)';
      cicloInfoBox.style.background  = 'rgba(34,197,94,.05)';
    }
  }
  if (cicloInput) {
    cicloInput.addEventListener('input', updateCicloInfo);
    updateCicloInfo();
  }

  // Validar confirmacion antes de enviar
  document.getElementById('formImport')?.addEventListener('submit', (e) => {
    const modo = document.querySelector('input[name="accion_import"]:checked')?.value;
    if (modo === 'reemplazar_ciclo') {
      if (confirmInput?.value.trim() !== 'CONFIRMAR') {
        e.preventDefault();
        Swal.fire({
          title: 'Atencion: Confirmacion requerida',
          text: 'Escribi CONFIRMAR en el campo de texto para reemplazar el ciclo.',
          icon: 'warning', background: '#0f172a', color: '#e5e7eb'
        });
        return;
      }
      const ciclo = parseInt(cicloInput?.value || 0);
      e.preventDefault();
      Swal.fire({
        title: 'Reemplazar ciclo ' + ciclo + '?',
        html: `Se borraran <b>todas las relaciones</b> del ciclo ${ciclo} y se recargaran desde el Excel.<br><br>
               Los datos en la tabla de personal <b>no se borran</b>, solo la relacion con el ciclo.<br><br>
               <span style="color:#fca5a5;font-weight:bold;">Esta accion no se puede deshacer.</span>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Si, reemplazar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545',
        background: '#0f172a', color: '#e5e7eb'
      }).then(r => { if (r.isConfirmed) document.getElementById('formImport').submit(); });
    }
  });
});
</script>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Tooltip en observaciones truncadas
document.querySelectorAll('.obs-cell').forEach(el => {
    el.title = el.textContent.trim();
});
// Highlight busqueda en la tabla
const q = <?= json_encode($q) ?>;
if (q.trim() !== '') {
    const re = new RegExp('(' + q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
    document.querySelectorAll('.tbl tbody td').forEach(td => {
        if (td.classList.contains('text-end')) return;
        if (td.querySelector('a,span.badge-jer,span.badge-parte,select')) return; // skip action cells
        td.innerHTML = td.innerHTML.replace(re, '<mark style="background:rgba(251,191,36,.35);color:#fef3c7;border-radius:2px;padding:0 2px;">$1</mark>');
    });
}

/**
 * Control visual para desplegar u ocultar la caja de texto inline de nuevo destino
 */
function toggleNuevoDestinoInline(selectElement, id) {
    const inputNuevo = document.getElementById('input_nuevo_dst_' + id);
    if (inputNuevo) {
        if (selectElement.value === 'NUEVO') {
            inputNuevo.style.display = 'block';
            inputNuevo.required = true;
            inputNuevo.focus();
        } else {
            inputNuevo.style.display = 'none';
            inputNuevo.required = false;
            inputNuevo.value = '';
        }
    }
}
</script>
</body>
</html>
