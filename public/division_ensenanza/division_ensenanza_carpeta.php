<?php
// public/division_ensenanza/division_ensenanza_carpeta.php
declare(strict_types=1);

require_once __DIR__ . '/../../auth/bootstrap.php';
require_login();
require_once __DIR__ . '/../../config/db.php';

$root = realpath(__DIR__ . '/../..');
if ($root === false) $root = dirname(__DIR__, 2);

$folder = 'DIVISION-ENSE' . "\xC3\x91" . 'ANZA';
$AREA_TITLE = 'Division Ensenanza';
$AREA_CODE = 'ENS';
$AREA_SLUG = $folder . DIRECTORY_SEPARATOR . 'Drive Div Ens';
$AREA_ROOT_ABS = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'unidades' . DIRECTORY_SEPARATOR . 'ecmilm' . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . 'Drive Div Ens';
$AREA_ALLOW_UPLOAD = true;
$BACK_LINK = 'division_ensenanza.php';

if (!is_dir($AREA_ROOT_ABS)) {
    mkdir($AREA_ROOT_ABS, 0775, true);
}

require_once __DIR__ . '/../../includes/area_shared_browser.php';
