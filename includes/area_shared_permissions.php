<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/bootstrap.php';
require_once __DIR__ . '/../config/db.php';

function area_shared_perm_norm_dni(string $value): string {
    return preg_replace('/\D+/', '', $value) ?? '';
}

function area_shared_perm_norm_user(string $value): string {
    $value = strtolower(trim(str_replace('/', '\\', $value)));
    if ($value === '') return '';
    if (str_contains($value, '\\')) {
        $parts = explode('\\', $value);
        $value = (string)end($parts);
    }
    if (str_contains($value, '@')) {
        $value = (string)explode('@', $value, 2)[0];
    }
    return preg_replace('/[^a-z0-9._-]+/', '', $value) ?? '';
}

function area_shared_perm_code_for_slug(string $slug, string $fallbackCode = ''): string {
    $slug = strtoupper(trim($slug));
    $map = [
        'PERSONAL' => 'S1',
        'INTELIGENCIA' => 'S2',
        'OPERACIONES' => 'S3',
        'MATERIALES' => 'S4',
        'INTENDENCIA' => 'S5',
        'SAF' => 'SAF',
        'INFORMATICA' => 'INF',
        'SANIDAD' => 'SAN',
        'IGE' => 'IGE',
    ];
    $code = $map[$slug] ?? strtoupper(trim($fallbackCode));
    return $code !== '' ? $code : $slug;
}

function area_shared_perm_ensure_schema(PDO $pdo): void {
    static $done = false;
    if ($done) return;

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS area_shared_permissions (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          unidad_id INT NOT NULL DEFAULT 1,
          area_code VARCHAR(30) NOT NULL,
          area_slug VARCHAR(120) NOT NULL,
          personal_id INT NULL,
          dni VARCHAR(20) NULL,
          domain_username VARCHAR(120) NULL,
          display_name VARCHAR(255) NULL,
          position_label VARCHAR(80) NULL,
          permission ENUM('read','write','admin') NOT NULL DEFAULT 'read',
          active TINYINT(1) NOT NULL DEFAULT 1,
          created_by_id INT NULL,
          created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY idx_area_shared_area (unidad_id, area_code, active),
          KEY idx_area_shared_personal (personal_id),
          KEY idx_area_shared_dni (dni),
          KEY idx_area_shared_domain (domain_username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $done = true;
}

function area_shared_perm_current_identity(PDO $pdo): array {
    $user = function_exists('current_user') ? current_user() : ($_SESSION['user'] ?? []);
    $user = is_array($user) ? $user : [];
    $dni = area_shared_perm_norm_dni((string)($user['dni'] ?? $user['username'] ?? ''));
    $sessionUser = area_shared_perm_norm_user((string)($user['username'] ?? ''));

    $identity = [
        'personal_id' => 0,
        'unidad_id' => (int)($_SESSION['unidad_id'] ?? 1),
        'dni' => $dni,
        'domain_users' => array_values(array_filter([$sessionUser])),
        'role_code' => strtoupper(trim((string)($user['rol_app'] ?? $user['role_app'] ?? 'USUARIO'))),
        'is_superadmin' => ($dni === '41742406' || strtolower(trim((string)($user['username'] ?? ''))) === 'nesrojas'),
    ];

    try {
        if ($dni !== '') {
            $st = $pdo->prepare("
                SELECT pu.id, pu.unidad_id, pu.usuario_intranet, pu.usuario_gde, pu.role_id, r.codigo AS role_code
                FROM personal_unidad pu
                LEFT JOIN roles r ON r.id = pu.role_id
                WHERE REPLACE(REPLACE(REPLACE(pu.dni,'.',''),'-',''),' ','') = :dni
                LIMIT 1
            ");
            $st->execute([':dni' => $dni]);
            if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                $identity['personal_id'] = (int)($row['id'] ?? 0);
                $identity['unidad_id'] = (int)($row['unidad_id'] ?? $identity['unidad_id']);
                $dbRole = strtoupper(trim((string)($row['role_code'] ?? '')));
                if ($dbRole !== '') $identity['role_code'] = $dbRole;
                foreach (['usuario_intranet', 'usuario_gde'] as $col) {
                    $u = area_shared_perm_norm_user((string)($row[$col] ?? ''));
                    if ($u !== '') $identity['domain_users'][] = $u;
                }
            }
        }

        if ($identity['personal_id'] > 0 && $identity['role_code'] === 'USUARIO') {
            $st = $pdo->prepare("
                SELECT r.codigo
                FROM usuario_roles ur
                INNER JOIN roles r ON r.id = ur.role_id
                WHERE ur.personal_id = :pid
                  AND (ur.unidad_id IS NULL OR ur.unidad_id = :uid)
                ORDER BY CASE r.codigo WHEN 'SUPERADMIN' THEN 3 WHEN 'ADMIN' THEN 2 ELSE 1 END DESC,
                         ur.created_at DESC, ur.id DESC
                LIMIT 1
            ");
            $st->execute([':pid' => $identity['personal_id'], ':uid' => $identity['unidad_id']]);
            $role = $st->fetchColumn();
            if (is_string($role) && $role !== '') $identity['role_code'] = strtoupper($role);
        }
    } catch (Throwable $e) {
        error_log('[EA][shared_permissions] identity error: ' . $e->getMessage());
    }

    $identity['domain_users'] = array_values(array_unique(array_filter($identity['domain_users'])));
    if ($identity['is_superadmin']) $identity['role_code'] = 'SUPERADMIN';
    return $identity;
}

function area_shared_perm_is_admin(PDO $pdo): bool {
    $identity = area_shared_perm_current_identity($pdo);
    return in_array((string)$identity['role_code'], ['ADMIN', 'SUPERADMIN'], true);
}

function area_shared_perm_area_has_rules(PDO $pdo, int $unidadId, string $areaCode): bool {
    area_shared_perm_ensure_schema($pdo);
    $st = $pdo->prepare("
        SELECT COUNT(*)
        FROM area_shared_permissions
        WHERE unidad_id = :uid AND area_code = :area AND active = 1
    ");
    $st->execute([':uid' => $unidadId, ':area' => strtoupper($areaCode)]);
    return (int)$st->fetchColumn() > 0;
}

function area_shared_perm_can_access(PDO $pdo, string $areaSlug, string $areaCode = '', string $required = 'read'): bool {
    area_shared_perm_ensure_schema($pdo);
    $identity = area_shared_perm_current_identity($pdo);
    if (in_array((string)$identity['role_code'], ['ADMIN', 'SUPERADMIN'], true)) return true;

    $areaCode = area_shared_perm_code_for_slug($areaSlug, $areaCode);
    $unidadId = (int)$identity['unidad_id'];
    if (!area_shared_perm_area_has_rules($pdo, $unidadId, $areaCode)) {
        return true;
    }

    $rank = ['read' => 1, 'write' => 2, 'admin' => 3];
    $need = $rank[$required] ?? 1;
    $params = [
        ':uid' => $unidadId,
        ':area' => $areaCode,
        ':need' => $need,
        ':pid' => (int)$identity['personal_id'],
        ':dni' => (string)$identity['dni'],
    ];

    $domainWhere = '';
    $domainUsers = $identity['domain_users'];
    if (!empty($domainUsers)) {
        $parts = [];
        foreach ($domainUsers as $i => $domainUser) {
            $key = ':du' . $i;
            $parts[] = "domain_username = {$key}";
            $params[$key] = $domainUser;
        }
        $domainWhere = ' OR ' . implode(' OR ', $parts);
    }

    $st = $pdo->prepare("
        SELECT COUNT(*)
        FROM area_shared_permissions
        WHERE unidad_id = :uid
          AND area_code = :area
          AND active = 1
          AND CASE permission WHEN 'admin' THEN 3 WHEN 'write' THEN 2 ELSE 1 END >= :need
          AND (
            (personal_id IS NOT NULL AND personal_id = :pid)
            OR (dni IS NOT NULL AND dni <> '' AND dni = :dni)
            {$domainWhere}
          )
    ");
    $st->execute($params);
    return (int)$st->fetchColumn() > 0;
}

