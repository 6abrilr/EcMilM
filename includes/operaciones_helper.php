<?php
// includes/operaciones_helper.php
// Helpers comunes para los módulos de Operaciones.
// Objetivos:
// - Normalizar rutas y assets.
// - Proveer funciones para control de acceso (login / admin / área).
// - Reutilizar lógicas comunes (tabla existe, ruta, user, etc.).

declare(strict_types=1);

// Ensure basic auth helpers are available.
require_once __DIR__ . '/../auth/bootstrap.php';

/**
 * Base URL of the app (the folder above /public).
 * e.g. /ea
 */
function operaciones_app_base_web(): string {
    $self = (string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
    $public = rtrim(str_replace('\\','/', dirname($self)), '/'); // /ea/public/...
    return rtrim(str_replace('\\','/', dirname($public)), '/'); // /ea
}

/**
 * Base URL of the public folder.
 * e.g. /ea/public
 */
function operaciones_app_public_web(): string {
    $self = (string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
    return rtrim(str_replace('\\','/', dirname($self)), '/');
}

/**
 * URL to the assets folder.
 */
function operaciones_assets_url(string $relative = ''): string {
    $base = operaciones_app_base_web() . '/assets';
    $relative = ltrim($relative, '/');
    return $relative === '' ? $base : $base . '/' . $relative;
}

/**
 * Build an absolute URL inside the app (relative to /ea).
 * If $path is already absolute (starts with /) or is a URL, it is returned unchanged.
 */
function operaciones_url(string $path): string {
    if (preg_match('#^[a-zA-Z]+://#', $path)) {
        return $path;
    }
    if (strpos($path, '/') === 0) {
        return $path;
    }
    return rtrim(operaciones_app_base_web(), '/') . '/' . ltrim($path, '/');
}

/**
 * Ensure user is logged in (delegates to auth/bootstrap.php).
 */
function operaciones_require_login(): void {
    if (function_exists('require_login')) {
        require_login();
    }
}

/**
 * Returns current user array (same as current_user()).
 */
function operaciones_current_user(): ?array {
    if (function_exists('current_user')) {
        return current_user();
    }
    return isset($_SESSION['user']) && is_array($_SESSION['user']) ? $_SESSION['user'] : null;
}

/**
 * Normaliza DNI (solo dígitos) para búsquedas.
 */
function operaciones_norm_dni(string $dni): string {
    return preg_replace('/\D+/', '', $dni) ?? '';
}

/**
 * Devuelve una fila de personal_unidad asociada al usuario actual (por DNI/username).
 * Retorna array vacío si no puede encontrarse.
 */
function operaciones_get_personal_actual(PDO $pdo): array {
    $user = operaciones_current_user();
    $dni = '';

    if (is_array($user)) {
        if (!empty($user['dni'])) {
            $dni = operaciones_norm_dni((string)$user['dni']);
        } elseif (!empty($user['username'])) {
            $dni = operaciones_norm_dni((string)$user['username']);
        }
    }

    if ($dni === '') {
        return [];
    }

    try {
        $st = $pdo->prepare("SELECT id, unidad_id, grado, arma, apellido, nombre, apellido_nombre, role_id FROM personal_unidad WHERE REPLACE(REPLACE(REPLACE(dni,'.',''),'-',''),' ','') = :dni LIMIT 1");
        $st->execute([':dni' => $dni]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Devuelve el código de rol más elevado del usuario (SUPERADMIN/ADMIN/USUARIO).
 */
function operaciones_get_role_code(PDO $pdo, int $personalId, int $unidadId): string {
    $roleCodigo = 'USUARIO';

    try {
        if ($personalId > 0) {
            $st = $pdo->prepare("SELECT r.codigo FROM personal_unidad pu INNER JOIN roles r ON r.id = pu.role_id WHERE pu.id = :pid LIMIT 1");
            $st->execute([':pid' => $personalId]);
            $c = $st->fetchColumn();
            if (is_string($c) && $c !== '') {
                return strtoupper($c);
            }
        }
    } catch (Throwable $e) {
    }

    try {
        if ($personalId > 0) {
            $st = $pdo->prepare("SELECT r.codigo FROM usuario_roles ur INNER JOIN roles r ON r.id = ur.role_id WHERE ur.personal_id = :pid AND (ur.unidad_id IS NULL OR ur.unidad_id = :uid) ORDER BY CASE r.codigo WHEN 'SUPERADMIN' THEN 3 WHEN 'ADMIN' THEN 2 ELSE 1 END DESC, ur.created_at DESC, ur.id DESC LIMIT 1");
            $st->execute([':pid' => $personalId, ':uid' => $unidadId]);
            $c = $st->fetchColumn();
            if (is_string($c) && $c !== '') {
                return strtoupper($c);
            }
        }
    } catch (Throwable $e) {
    }

    return $roleCodigo;
}

/**
 * ¿Es el usuario ADMIN o SUPERADMIN?
 */
function operaciones_es_admin(PDO $pdo): bool {
    $personal = operaciones_get_personal_actual($pdo);
    if (empty($personal)) {
        return false;
    }
    $personalId = (int)($personal['id'] ?? 0);
    $unidadId   = (int)($personal['unidad_id'] ?? 0);
    $roleCode   = operaciones_get_role_code($pdo, $personalId, $unidadId);
    return in_array($roleCode, ['ADMIN', 'SUPERADMIN'], true);
}

/**
 * Retorna el código de área (destino.codigo) asociado al usuario.
 */
function operaciones_get_user_area_code(PDO $pdo): string {
    $personal = operaciones_get_personal_actual($pdo);
    if (empty($personal)) {
        return '';
    }

    $unidadId = (int)($personal['unidad_id'] ?? 0);
    if ($unidadId <= 0) {
        return '';
    }

    try {
        $st = $pdo->prepare("SELECT codigo FROM destino WHERE unidad_id = :uid AND activo = 1 ORDER BY id ASC LIMIT 1");
        $st->execute([':uid' => $unidadId]);
        $c = $st->fetchColumn();
        return is_string($c) ? strtoupper(trim($c)) : '';
    } catch (Throwable $e) {
        return '';
    }
}

/**
 * Comprueba si existe una tabla en la base de datos actual.
 */
function operaciones_db_table_exists(PDO $pdo, string $table): bool {
    try {
        $sql = "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :t LIMIT 1";
        $st = $pdo->prepare($sql);
        $st->execute([':t' => $table]);
        return (bool)$st->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Devuelve el total de filas en una tabla si existe.
 */
function operaciones_safe_count(PDO $pdo, string $table): int {
    if (!operaciones_db_table_exists($pdo, $table)) {
        return 0;
    }
    try {
        $st = $pdo->query("SELECT COUNT(*) FROM {$table}");
        return (int)($st->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * One-liner for safe escaping.
 */
function operaciones_e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/**
 * Outputs the required CSS link for the embedded chat widget.
 */
function operaciones_chat_styles(): void {
    echo '<link rel="stylesheet" href="' . operaciones_url('public/chat.css') . '">\n';
}

/**
 * Renders the chat widget used across the app.
 * Must be called after the page has loaded its layout.
 */
function operaciones_render_chat_widget(PDO $pdo): void {
    $basePublic = operaciones_app_public_web();
    $chatFull = $basePublic . '/chat.php';
    $chatAjax = $basePublic . '/chat.php';
    $csrf     = function_exists('csrf_token') ? csrf_token() : '';
    $personal = operaciones_get_personal_actual($pdo);
    $personalId = (int)($personal['id'] ?? 0);
    $isAdmin = operaciones_es_admin($pdo);

    echo "\n";
    ?>

<!-- Chat widget -->
<div id="chatLauncher" class="chat-launcher chat-hidden">
  <div class="chat-launcher-title">Chat interno</div>
  <span id="chatLauncherBadge" class="chat-total-badge">0</span>
</div>

<div id="chatDock" class="chat-dock">
  <div class="chat-dock-head">
    <div class="chat-dock-title-wrap">
      <div class="chat-dock-title">Chat interno</div>
      <span id="chatDockBadge" class="chat-total-badge">0</span>
    </div>

    <div class="chat-dock-actions">
      <a id="chatOpenFull" href="<?= operaciones_e($chatFull) ?>" class="chat-btn chat-btn-open">Agrandar</a>
      <button type="button" id="chatCloseBtn" class="chat-btn chat-btn-close">Cerrar</button>
    </div>
  </div>

  <div class="chat-dock-body">
    <div class="chat-conv-pane">
      <div class="chat-conv-pane-head">Conversaciones</div>
      <div id="chatConvList" class="chat-conv-list">
        <div class="chat-empty">Cargando...</div>
      </div>
    </div>

    <div class="chat-thread">
      <div class="chat-thread-head">
        <div id="chatThreadTitle" class="chat-thread-title">Chat General</div>
        <div id="chatThreadSub" class="chat-thread-sub">Mensajes generales de la unidad</div>
      </div>

      <div id="chatMessages" class="chat-messages">
        <div class="chat-empty">Cargando mensajes...</div>
      </div>

      <div id="chatReadonly" class="chat-readonly">
        Solo ADMIN y SUPERADMIN pueden escribir en el chat general.
      </div>

      <form id="chatCompose" class="chat-compose">
        <div class="chat-compose-row">
          <input type="text" id="chatInput" class="form-control" maxlength="4000" placeholder="Escribí un mensaje...">
          <button type="submit" class="btn btn-success btn-sm" style="font-weight:800;">Enviar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
window.EA_CHAT = window.EA_CHAT || {};
window.EA_CHAT.config = {
  ajaxUrl: <?= json_encode($chatAjax, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
  fullUrl: <?= json_encode($chatFull, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
  csrfToken: <?= json_encode($csrf, JSON_UNESCAPED_UNICODE) ?>,
  canWriteGeneral: <?= $isAdmin ? 'true' : 'false' ?>,
  personalId: <?= json_encode($personalId, JSON_UNESCAPED_UNICODE) ?>
};
</script>

<script src="<?= operaciones_url('assets/js/chat.js') ?>"></script>

<?php
}
