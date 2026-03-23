<?php
declare(strict_types=1);

require_once __DIR__ . '/../../auth/bootstrap.php';
require_login();

$AREA_TITLE = 'Personal';
$AREA_CODE = 'S-1';
$AREA_SLUG = 'PERSONAL';
$BACK_LINK = './personal.php';
$ROOT_FS = realpath(__DIR__ . '/../../');
$AREA_ROOT_ABS = ($ROOT_FS !== false)
    ? ($ROOT_FS . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'unidades' . DIRECTORY_SEPARATOR . 'ecmilm' . DIRECTORY_SEPARATOR . $AREA_SLUG)
    : '';

require_once __DIR__ . '/../../includes/area_shared_browser.php';
