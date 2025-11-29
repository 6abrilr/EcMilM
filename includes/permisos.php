<?php
// includes/permisos.php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

/**
 * Devuelve el usuario actual (compat con current_user o $_SESSION['user'])
 */
function app_current_user(): array {
    if (function_exists('current_user')) {
        $u = current_user();
        return is_array($u) ? $u : [];
    }
    return isset($_SESSION['user']) && is_array($_SESSION['user'])
        ? $_SESSION['user']
        : [];
}

/**
 * Busca el rol local del usuario en la tabla roles_locales por DNI.
 */
function app_get_local_role(): ?array {
    $user = app_current_user();
    $dni  = preg_replace('/\D+/', '', (string)($user['dni'] ?? ''));

    if ($dni === '') {
        return null;
    }

    $db = db(); // asumiendo que config/db.php expone db() → mysqli
    $sql = "SELECT * FROM roles_locales WHERE dni = ? LIMIT 1";
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $dni);
    if (!$stmt->execute()) {
        $stmt->close();
        return null;
    }

    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

/**
 * Devuelve las áreas permitidas para el usuario (S1, S3, S4, S5, GRAL, etc).
 */
function app_user_areas_permitidas(): array {
    $rol = app_get_local_role();
    if (!$rol) {
        return [];
    }

    $raw = (string)($rol['areas_acceso'] ?? '[]');
    $areas = json_decode($raw, true);

    if (!is_array($areas)) {
        $areas = [];
    }

    $areas = array_values(array_unique(array_map(
        static fn($a) => strtoupper(trim((string)$a)),
        $areas
    )));

    return $areas;
}

/**
 * ¿El usuario tiene rol_app = 'admin'?
 */
function app_user_es_admin_app(): bool {
    $rol = app_get_local_role();
    if (!$rol) return false;
    return strtolower((string)$rol['rol_app']) === 'admin';
}

/**
 * Dado un código de área (S1, S2, S3, S4, S5, GRAL),
 * ¿puede editar?
 *
 * Regla:
 *   - Si está el área explícita → puede.
 *   - Si tiene GRAL → puede.
 *   - Si es admin_app → puede todo.
 */
function app_user_puede_editar_area(string $codigoArea): bool {
    $codigoArea = strtoupper(trim($codigoArea));
    if ($codigoArea === '') return false;

    $areas = app_user_areas_permitidas();

    // sin fila en roles_locales → sólo lectura
    if (!$areas && !app_user_es_admin_app()) {
        return false;
    }

    if (in_array('GRAL', $areas, true)) {
        return true;
    }

    if (in_array($codigoArea, $areas, true)) {
        return true;
    }

    if (app_user_es_admin_app()) {
        // admin_app sin GRAL → igual puede
        return true;
    }

    return false;
}

/**
 * Intenta deducir el código de área (S1, S2, S3, S4, S5, GRAL)
 * a partir del file_rel de checklist/ultima_inspeccion/visitas.
 */
function app_area_desde_file_rel(string $fileRel): ?string {
    $path = str_replace('\\', '/', $fileRel);
    $parts = explode('/', $path);

    // storage/listas_control/S3/...
    $idx = array_search('listas_control', $parts, true);
    if ($idx !== false && isset($parts[$idx + 1])) {
        return strtoupper($parts[$idx + 1]); // S1, S3, etc.
    }

    // storage/ultima_inspeccion/Personal (S-1)/...
    $idx = array_search('ultima_inspeccion', $parts, true);
    if ($idx !== false && isset($parts[$idx + 1])) {
        $folder = $parts[$idx + 1];

        if (preg_match('/S-?\s*([1-5])/', $folder, $m)) {
            return 'S' . $m[1];
        }

        // Caso "Aspectos Generales" u otros → lo tratamos como GRAL
        return 'GRAL';
    }

    // storage/visitas_de_estado_mayor/Operaciones (S-3)/...
    $idx = array_search('visitas_de_estado_mayor', $parts, true);
    if ($idx !== false && isset($parts[$idx + 1])) {
        $folder = $parts[$idx + 1];
        if (preg_match('/S-?\s*([1-5])/', $folder, $m)) {
            return 'S' . $m[1];
        }
        return 'GRAL';
    }

    return null;
}

/**
 * Versión directa: le pasás file_rel y te dice si el usuario puede editar eso.
 */
function app_user_puede_editar_file_rel(string $fileRel): bool {
    $area = app_area_desde_file_rel($fileRel);
    if ($area === null) {
        // Si no pudimos deducir área, solo dejamos admin_app
        return app_user_es_admin_app();
    }
    return app_user_puede_editar_area($area);
}
