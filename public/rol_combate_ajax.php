<?php
// public/rol_combate_ajax.php
declare(strict_types=1);

require_once __DIR__ . '/../auth/bootstrap.php';
require_login();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

function jexit(array $data): void {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jexit(['ok' => false, 'error' => 'Método no permitido']);
}

/* ===== Leer y sanear parámetros ===== */
$personal_id   = isset($_POST['personal_id'])   ? (int)$_POST['personal_id']   : 0;
$rol_id        = isset($_POST['rol_id'])        ? (int)$_POST['rol_id']        : 0;
$asignacion_id = isset($_POST['asignacion_id']) ? (int)$_POST['asignacion_id'] : 0;
$seccion       = isset($_POST['seccion'])       ? (int)$_POST['seccion']       : 0;
$campo         = $_POST['campo'] ?? '';
$valor         = isset($_POST['valor']) ? trim((string)$_POST['valor']) : '';

if ($personal_id <= 0) {
    jexit(['ok' => false, 'error' => 'personal_id inválido']);
}

/* Campos que se permiten tocar */
$permitidos_rca = [
    'armamento_principal',
    'ni_armamento_principal',
    'armamento_secundario',
    'ni_armamento_secundario',
    'rol_administrativo',
    'vehiculo',
];

$es_campo_rc   = ($campo === 'rol_combate' || $campo === 'seccion');
$es_campo_rca  = in_array($campo, $permitidos_rca, true);

if (!$es_campo_rc && !$es_campo_rca) {
    jexit(['ok' => false, 'error' => 'Campo no permitido']);
}

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->beginTransaction();

    /* ===== 1) Asegurar que exista rol_combate (rc) ===== */

    if ($rol_id <= 0) {
        // para crear un rol, necesito una sección válida
        if ($seccion < 1 || $seccion > 8) {
            $pdo->rollBack();
            jexit(['ok' => false, 'error' => 'Sección (Elemento) inválida para crear el rol.']);
        }

        // Orden dentro de la sección
        $stmtOrd = $pdo->prepare("
            SELECT COALESCE(MAX(orden), 0) + 1 AS next_ord
              FROM rol_combate
             WHERE seccion = :sec
        ");
        $stmtOrd->execute([':sec' => $seccion]);
        $nextOrd = (int)$stmtOrd->fetchColumn();

        $puesto = null;
        if ($campo === 'rol_combate' && $valor !== '') {
            $puesto = $valor;
        }

        $stmtInsRc = $pdo->prepare("
            INSERT INTO rol_combate (seccion, hoja, grupo, subgrupo, puesto, orden, observaciones)
            VALUES (:sec, NULL, NULL, NULL, :puesto, :orden, NULL)
        ");
        $stmtInsRc->execute([
            ':sec'    => $seccion,
            ':puesto' => $puesto,
            ':orden'  => $nextOrd,
        ]);

        $rol_id = (int)$pdo->lastInsertId();
    } else {
        // Si ya hay rol y cambiaron la sección, se actualiza
        if ($campo === 'seccion') {
            if ($seccion < 1 || $seccion > 8) {
                $pdo->rollBack();
                jexit(['ok' => false, 'error' => 'Sección (Elemento) inválida.']);
            }
            $stmtUpdSec = $pdo->prepare("
                UPDATE rol_combate
                   SET seccion = :sec
                 WHERE id = :id
            ");
            $stmtUpdSec->execute([
                ':sec' => $seccion,
                ':id'  => $rol_id,
            ]);
        }
    }

    /* ===== 2) Actualizar campos de rol_combate si corresponde ===== */

    if ($campo === 'rol_combate') {
        $stmtUpdRc = $pdo->prepare("
            UPDATE rol_combate
               SET puesto = :puesto
             WHERE id = :id
        ");
        $stmtUpdRc->execute([
            ':puesto' => ($valor !== '' ? $valor : null),
            ':id'     => $rol_id,
        ]);
    } elseif ($campo === 'seccion') {
        // ya se hizo arriba
    }

    /* ===== 3) Asegurar "UN SOLO rol por persona" en rol_combate_asignaciones ===== */

    if ($es_campo_rca) {

        // 3.1 Traigo todas las asignaciones activas de esa persona
        $stmtAct = $pdo->prepare("
            SELECT id, rol_combate_id
              FROM rol_combate_asignaciones
             WHERE personal_id = :per
               AND (hasta IS NULL OR hasta >= CURDATE())
             ORDER BY id ASC
        ");
        $stmtAct->execute([':per' => $personal_id]);
        $activos = $stmtAct->fetchAll(PDO::FETCH_ASSOC);

        if ($activos) {
            // Me quedo con la primera asignación como la "oficial"
            $asign_principal_id = (int)$activos[0]['id'];

            // Cierro cualquier otra asignación activa extra
            if (count($activos) > 1) {
                $stmtCerrar = $pdo->prepare("
                    UPDATE rol_combate_asignaciones
                       SET hasta = CURDATE()
                     WHERE id = :id
                ");
                for ($i = 1; $i < count($activos); $i++) {
                    $stmtCerrar->execute([':id' => (int)$activos[$i]['id']]);
                }
            }

            $asignacion_id = $asign_principal_id;

            // Si la asignación principal apunta a otro rol, la muevo al rol actual
            if ((int)$activos[0]['rol_combate_id'] !== $rol_id) {
                $stmtMover = $pdo->prepare("
                    UPDATE rol_combate_asignaciones
                       SET rol_combate_id = :rol
                     WHERE id = :id
                ");
                $stmtMover->execute([
                    ':rol' => $rol_id,
                    ':id'  => $asignacion_id,
                ]);
            }

        } else {
            // 3.2 No tenía ninguna asignación activa → creo una nueva
            $stmtInsRca = $pdo->prepare("
                INSERT INTO rol_combate_asignaciones
                    (rol_combate_id, personal_id, desde, hasta,
                     armamento_principal, ni_armamento_principal,
                     armamento_secundario, ni_armamento_secundario,
                     rol_administrativo, vehiculo, observaciones)
                VALUES
                    (:rol, :per, CURDATE(), NULL,
                     NULL, NULL,
                     NULL, NULL,
                     NULL, NULL, NULL)
            ");
            $stmtInsRca->execute([
                ':rol' => $rol_id,
                ':per' => $personal_id,
            ]);
            $asignacion_id = (int)$pdo->lastInsertId();
        }

        // 3.3 Ahora sí, actualizo el campo pedido (AP, NI, AS, rol admin, vehículo)

        if ($campo === 'armamento_principal' || $campo === 'armamento_secundario') {
            $valorNorm = strtoupper($valor);
            $validos = ['FAL','PISTOLA','ESCOPETA'];
            if (!in_array($valorNorm, $validos, true)) {
                $valorNorm = null;
            }
            $valor = $valorNorm;
        }

        $sqlUpdRca = "
            UPDATE rol_combate_asignaciones
               SET {$campo} = :valor
             WHERE id = :id
        ";
        $stmtUpdRca = $pdo->prepare($sqlUpdRca);
        $stmtUpdRca->execute([
            ':valor' => ($valor !== '' ? $valor : null),
            ':id'    => $asignacion_id,
        ]);
    }

    $pdo->commit();

    jexit([
        'ok'             => true,
        'rol_id'         => $rol_id,
        'asignacion_id'  => $asignacion_id,
        'seccion'        => ($seccion > 0 ? $seccion : null),
    ]);

} catch (Throwable $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jexit([
        'ok'    => false,
        'error' => $ex->getMessage(),
    ]);
}
