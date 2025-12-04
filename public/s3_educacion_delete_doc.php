<?php
// public/s3_educacion_delete_doc.php
declare(strict_types=1);

// Solo borramos archivo físico de evidencias de S3 Educación
// (clases_docs / trabajos_docs). No tocamos nada de checklist.

$tipo = $_GET['tipo'] ?? '';
$id   = isset($_GET['id'])   ? (int)$_GET['id']   : 0;
$file = $_GET['file'] ?? '';

if ($id <= 0 || $file === '' || ($tipo !== 'clase' && $tipo !== 'trabajo')) {
    http_response_code(400);
    echo "Parámetros inválidos";
    exit;
}

// Normalizamos sólo al nombre de archivo, por seguridad
$basename = basename($file);

// Rutas base (ajustadas a tu estructura):
//   /var/www/html/inspeccion
//   /var/www/html/inspeccion/public
//   /var/www/html/inspeccion/storage/s3_educacion/...
$projectBase = realpath(__DIR__ . '/..'); // subimos de /public a raíz del proyecto
if ($projectBase === false) {
    http_response_code(500);
    echo "No se pudo resolver la ruta base del proyecto";
    exit;
}

$BASE_REL = 'storage/s3_educacion';

if ($tipo === 'clase') {
    $subdir = 'clases_docs';
    // Los archivos reales son del estilo: clase_ID_doc_...
    // pero para borrar solo necesitamos el nombre que ya viene en ?file=
} else { // trabajo
    $subdir = 'trabajos_docs';
}

$baseDir = realpath($projectBase . DIRECTORY_SEPARATOR . $BASE_REL . DIRECTORY_SEPARATOR . $subdir);
if ($baseDir === false) {
    http_response_code(500);
    echo "No existe el directorio de evidencias";
    exit;
}

// Ruta absoluta al archivo que queremos borrar
$target = $baseDir . DIRECTORY_SEPARATOR . $basename;

// Protección: nos aseguramos que la ruta final siga dentro de $baseDir
$targetReal = realpath($target);
if ($targetReal !== false && strpos($targetReal, $baseDir) === 0 && is_file($targetReal)) {
    @unlink($targetReal);
}

// Redirección de vuelta a la pantalla correspondiente
$redirect = ($tipo === 'clase')
    ? 's3_educacion_clases.php?saved=1'
    : 's3_educacion_trabajos.php?saved=1';

// Si tenemos HTTP_REFERER, lo usamos para volver exactamente a donde estaba el usuario
if (!empty($_SERVER['HTTP_REFERER'])) {
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

header('Location: ' . $redirect);
exit;
