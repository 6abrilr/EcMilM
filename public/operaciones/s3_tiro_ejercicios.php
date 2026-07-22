<?php
declare(strict_types=1);

require_once __DIR__ . '/../../auth/bootstrap.php';
require_login();


header('Location: operaciones_tiro_ejercicios.php', true, 302);
exit;
