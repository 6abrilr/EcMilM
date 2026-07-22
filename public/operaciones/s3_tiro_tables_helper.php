<?php
declare(strict_types=1);

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    require_once __DIR__ . '/../../auth/bootstrap.php';
    require_login();
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/operaciones_tiro_tables_helper.php';