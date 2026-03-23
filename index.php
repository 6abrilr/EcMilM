<?php
declare(strict_types=1);
$base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
if ($base === '') {
    $base = '/ea';
}

header('Location: ' . $base . '/login.php');
exit;
