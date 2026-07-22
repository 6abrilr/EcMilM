<?php
declare(strict_types=1);

require_once __DIR__ . '/../../auth/bootstrap.php';
require_login();


require_once __DIR__ . '/operaciones_placeholder.php';
operaciones_placeholder_render('Educacion - Trabajos');
