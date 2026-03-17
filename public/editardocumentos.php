<?php
declare(strict_types=1);

/**
 * public/editordocumentos.php
 * Centro de herramientas documentales estilo iLovePDF (base)
 *
 * Funciones:
 * - Unir PDF
 * - Imágenes a PDF
 * - Word a PDF
 * - PDF a Word (requiere LibreOffice; resultado depende del PDF)
 * - PDF a imágenes
 * - Rotar PDF
 * - Agregar marca de agua de texto
 *
 * Requisitos:
 * - PHP 8+
 * - Composer:
 *     composer require setasign/fpdf setasign/fpdi phpoffice/phpword
 * - Sistema:
 *     libreoffice, imagemagick, ghostscript, poppler-utils, php-imagick
 *
 * IMPORTANTE:
 * - "Editar PDF" acá significa operaciones documentales.
 * - Editar texto interno de un PDF como Word/Acrobat NO es realista en puro PHP.
 */

use setasign\Fpdi\Fpdi;

$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($vendorAutoload)) {
    require_once $vendorAutoload;
}

define('PDF_TOOLKIT_AVAILABLE', class_exists(Fpdi::class) && class_exists('FPDF'));

ini_set('display_errors', '1');
error_reporting(E_ALL);
set_time_limit(300);

session_start();

/* =========================================================
   CONFIG
   ========================================================= */
const APP_NAME = 'Editor de Documentos';
const MAX_FILE_SIZE = 50 * 1024 * 1024; // 50 MB por archivo
const MAX_FILES = 20;

$BASE_DIR   = realpath(__DIR__ . '/../') ?: dirname(__DIR__);
$STORAGE    = $BASE_DIR . '/storage/documentos_tools';
$DIR_INPUT  = $STORAGE . '/input';
$DIR_OUTPUT = $STORAGE . '/output';
$DIR_TEMP   = $STORAGE . '/temp';

ensureDir($DIR_INPUT);
ensureDir($DIR_OUTPUT);
ensureDir($DIR_TEMP);

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

/* =========================================================
   HELPERS
   ========================================================= */
function e(mixed $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function ensureDir(string $dir): void {
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
}

function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function redirectSelf(): never {
    header('Location: ' . strtok($_SERVER['REQUEST_URI'] ?? 'editordocumentos.php', '?'));
    exit;
}
function requirePdfToolkit(): void {
    if (!PDF_TOOLKIT_AVAILABLE) {
        throw new RuntimeException(
            'Las herramientas PDF avanzadas no están disponibles porque faltan las librerías FPDI/FPDF. ' .
            'Instalá: composer require setasign/fpdf setasign/fpdi'
        );
    }
}

function safeBaseName(string $name): string {
    $name = preg_replace('/[^\w.\-]+/u', '_', $name) ?? 'archivo';
    return trim($name, '._-') ?: 'archivo';
}

function uniqueName(string $prefix, string $ext): string {
    return $prefix . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . ltrim($ext, '.');
}

function rrmdir(string $dir): void {
    if (!is_dir($dir)) return;
    $items = scandir($dir);
    if ($items === false) return;
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            rrmdir($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}

function mimeFromPath(string $path): string {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if (!$finfo) return 'application/octet-stream';
    $mime = finfo_file($finfo, $path) ?: 'application/octet-stream';
    finfo_close($finfo);
    return $mime;
}

function downloadFileResponse(string $path, ?string $downloadName = null): never {
    if (!is_file($path)) {
        http_response_code(404);
        exit('Archivo no encontrado.');
    }

    $downloadName ??= basename($path);
    $mime = mimeFromPath($path);

    header('Content-Description: File Transfer');
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . rawurlencode($downloadName) . '"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    readfile($path);
    exit;
}

function normalizeFilesArray(array $fileField): array {
    $files = [];
    if (!isset($fileField['name'])) return $files;

    if (is_array($fileField['name'])) {
        $count = count($fileField['name']);
        for ($i = 0; $i < $count; $i++) {
            $files[] = [
                'name'     => $fileField['name'][$i] ?? '',
                'type'     => $fileField['type'][$i] ?? '',
                'tmp_name' => $fileField['tmp_name'][$i] ?? '',
                'error'    => $fileField['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size'     => $fileField['size'][$i] ?? 0,
            ];
        }
    } else {
        $files[] = $fileField;
    }

    return $files;
}

function moveUploadedFiles(array $files, string $targetDir, array $allowedExts): array {
    $saved = [];

    if (count($files) > MAX_FILES) {
        throw new RuntimeException('Se superó el máximo de archivos permitidos.');
    }

    foreach ($files as $file) {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            continue;
        }

        $name = (string)($file['name'] ?? 'archivo');
        $tmp  = (string)($file['tmp_name'] ?? '');
        $size = (int)($file['size'] ?? 0);
        $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if ($size <= 0) {
            throw new RuntimeException("El archivo {$name} está vacío.");
        }
        if ($size > MAX_FILE_SIZE) {
            throw new RuntimeException("El archivo {$name} supera el límite permitido.");
        }
        if (!in_array($ext, $allowedExts, true)) {
            throw new RuntimeException("Extensión no permitida en {$name}.");
        }
        if (!is_uploaded_file($tmp)) {
            throw new RuntimeException("Archivo inválido: {$name}.");
        }

        $newName = uniqueName(pathinfo(safeBaseName($name), PATHINFO_FILENAME), $ext);
        $dest = rtrim($targetDir, '/') . '/' . $newName;

        if (!move_uploaded_file($tmp, $dest)) {
            throw new RuntimeException("No se pudo mover {$name}.");
        }

        $saved[] = [
            'original_name' => $name,
            'path' => $dest,
            'ext' => $ext,
            'size' => $size,
        ];
    }

    if (!$saved) {
        throw new RuntimeException('No se subió ningún archivo válido.');
    }

    return $saved;
}

function runCommand(array $command, ?string $cwd = null): array {
    $cmd = implode(' ', array_map('escapeshellarg', $command));
    $output = [];
    $exitCode = 0;

    exec(($cwd ? 'cd ' . escapeshellarg($cwd) . ' && ' : '') . $cmd . ' 2>&1', $output, $exitCode);

    return [
        'ok' => $exitCode === 0,
        'code' => $exitCode,
        'output' => implode("\n", $output),
    ];
}

/* =========================================================
   PDF OPS
   ========================================================= */
if (PDF_TOOLKIT_AVAILABLE) {
    class PdfTool extends Fpdi
    {
        public float $angle = 0.0;

        public function Rotate(float $angle, float $x = -1, float $y = -1): void
        {
            if ($x === -1) $x = $this->x;
            if ($y === -1) $y = $this->y;

            if ($this->angle !== 0.0) {
                $this->_out('Q');
            }

            $this->angle = $angle;
            if ($angle !== 0.0) {
                $angle *= M_PI / 180;
                $c = cos($angle);
                $s = sin($angle);
                $cx = $x * $this->k;
                $cy = ($this->h - $y) * $this->k;
                $this->_out(sprintf(
                    'q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm',
                    $c, $s, -$s, $c, $cx, $cy, -$cx, -$cy
                ));
            }
        }

        public function _endpage(): void
        {
            if ($this->angle !== 0.0) {
                $this->angle = 0.0;
                $this->_out('Q');
            }
            parent::_endpage();
        }
    }
} else {
    class PdfTool {}
}

function mergePdfs(array $pdfPaths, string $outputPath): void {
    requirePdfToolkit();
    $pdf = new Fpdi();

    foreach ($pdfPaths as $path) {
        $pageCount = $pdf->setSourceFile($path);
        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $tpl = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($tpl);

            $orientation = ($size['width'] > $size['height']) ? 'L' : 'P';
            $pdf->AddPage($orientation, [$size['width'], $size['height']]);
            $pdf->useTemplate($tpl);
        }
    }

    $pdf->Output('F', $outputPath);
}

function imagesToPdf(array $imagePaths, string $outputPath): void {
    requirePdfToolkit();
    $pdf = new \FPDF();

    foreach ($imagePaths as $img) {
        [$wPx, $hPx] = getimagesize($img);
        if (!$wPx || !$hPx) {
            throw new RuntimeException('No se pudo leer una de las imágenes.');
        }

        $wMm = $wPx * 0.264583;
        $hMm = $hPx * 0.264583;
        $orientation = $wMm > $hMm ? 'L' : 'P';

        $pdf->AddPage($orientation, [$wMm, $hMm]);
        $pdf->Image($img, 0, 0, $wMm, $hMm);
    }

    $pdf->Output('F', $outputPath);
}

function rotatePdf(string $inputPdf, string $outputPdf, int $degrees): void {
    requirePdfToolkit();
    $degrees = (($degrees % 360) + 360) % 360;

    $pdf = new PdfTool();
    $pageCount = $pdf->setSourceFile($inputPdf);

    for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
        $tpl = $pdf->importPage($pageNo);
        $size = $pdf->getTemplateSize($tpl);

        $w = (float)$size['width'];
        $h = (float)$size['height'];

        $swap = in_array($degrees, [90, 270], true);
        $pageW = $swap ? $h : $w;
        $pageH = $swap ? $w : $h;
        $orientation = $pageW > $pageH ? 'L' : 'P';

        $pdf->AddPage($orientation, [$pageW, $pageH]);

        if ($degrees === 0) {
            $pdf->useTemplate($tpl, 0, 0, $w, $h);
        } elseif ($degrees === 90) {
            $pdf->Rotate(90, $pageW / 2, $pageH / 2);
            $pdf->useTemplate($tpl, 0, -($pageW - $pageH), $w, $h);
            $pdf->Rotate(0);
        } elseif ($degrees === 180) {
            $pdf->Rotate(180, $pageW / 2, $pageH / 2);
            $pdf->useTemplate($tpl, 0, 0, $w, $h);
            $pdf->Rotate(0);
        } elseif ($degrees === 270) {
            $pdf->Rotate(270, $pageW / 2, $pageH / 2);
            $pdf->useTemplate($tpl, -($pageH - $pageW), 0, $w, $h);
            $pdf->Rotate(0);
        }
    }

    $pdf->Output('F', $outputPdf);
}

function addTextWatermark(string $inputPdf, string $outputPdf, string $text): void {
    requirePdfToolkit();
    $pdf = new PdfTool();
    $pageCount = $pdf->setSourceFile($inputPdf);

    for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
        $tpl = $pdf->importPage($pageNo);
        $size = $pdf->getTemplateSize($tpl);

        $orientation = ($size['width'] > $size['height']) ? 'L' : 'P';
        $pdf->AddPage($orientation, [$size['width'], $size['height']]);
        $pdf->useTemplate($tpl);

        $pdf->SetFont('Arial', 'B', 26);
        $pdf->SetTextColor(180, 180, 180);

        $centerX = $size['width'] / 2;
        $centerY = $size['height'] / 2;

        $pdf->Rotate(45, $centerX, $centerY);
        $pdf->SetXY($centerX - 60, $centerY);
        $pdf->Cell(120, 10, mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');
        $pdf->Rotate(0);
    }

    $pdf->Output('F', $outputPdf);
}

/* =========================================================
   LIBREOFFICE OPS
   ========================================================= */
function convertWithLibreOffice(string $inputPath, string $outDir, string $targetFormat): string {
    $cmd = [
        'libreoffice',
        '--headless',
        '--convert-to', $targetFormat,
        '--outdir', $outDir,
        $inputPath
    ];

    $result = runCommand($cmd);

    if (!$result['ok']) {
        throw new RuntimeException("Error al convertir con LibreOffice:\n" . $result['output']);
    }

    $base = pathinfo($inputPath, PATHINFO_FILENAME);

    $expectedExt = match ($targetFormat) {
        'pdf' => 'pdf',
        'docx' => 'docx',
        default => strtolower($targetFormat),
    };

    $outPath = rtrim($outDir, '/') . '/' . $base . '.' . $expectedExt;

    if (!is_file($outPath)) {
        throw new RuntimeException('La conversión aparentemente terminó, pero no se encontró el archivo de salida.');
    }

    return $outPath;
}

/* =========================================================
   IMAGICK OPS
   ========================================================= */
function pdfToImages(string $inputPdf, string $outputZipPath, string $tempDir, string $format = 'png', int $density = 150): void {
    if (!extension_loaded('imagick')) {
        throw new RuntimeException('La extensión Imagick no está instalada.');
    }

    $img = new Imagick();
    $img->setResolution($density, $density);
    $img->readImage($inputPdf);

    $format = strtolower($format) === 'jpg' ? 'jpg' : 'png';
    $pageNum = 1;
    $generated = [];

    foreach ($img as $page) {
        $page->setImageFormat($format);
        $filename = $tempDir . '/pagina_' . str_pad((string)$pageNum, 3, '0', STR_PAD_LEFT) . '.' . $format;
        $page->writeImage($filename);
        $generated[] = $filename;
        $pageNum++;
    }

    $img->clear();
    $img->destroy();

    if (!$generated) {
        throw new RuntimeException('No se generaron imágenes.');
    }

    $zip = new ZipArchive();
    if ($zip->open($outputZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('No se pudo crear el ZIP de salida.');
    }

    foreach ($generated as $file) {
        $zip->addFile($file, basename($file));
    }
    $zip->close();
}

/* =========================================================
   DOWNLOAD ROUTE
   ========================================================= */
if (isset($_GET['download'])) {
    $name = basename((string)$_GET['download']);
    $path = $DIR_OUTPUT . '/' . $name;
    downloadFileResponse($path, $name);
}

/* =========================================================
   HANDLE POST
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tool = trim((string)($_POST['tool'] ?? ''));

    $jobDir = $DIR_TEMP . '/job_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
    ensureDir($jobDir);

    try {
        switch ($tool) {
            case 'merge_pdf': {
                $files = normalizeFilesArray($_FILES['files'] ?? []);
                $saved = moveUploadedFiles($files, $jobDir, ['pdf']);
                $pdfs = array_map(fn($f) => $f['path'], $saved);

                $outName = uniqueName('pdf_unido', 'pdf');
                $outPath = $DIR_OUTPUT . '/' . $outName;

                mergePdfs($pdfs, $outPath);

                setFlash('success', 'PDF unido correctamente.');
                $_SESSION['last_output'] = $outName;
                break;
            }

            case 'images_to_pdf': {
                $files = normalizeFilesArray($_FILES['files'] ?? []);
                $saved = moveUploadedFiles($files, $jobDir, ['jpg', 'jpeg', 'png', 'webp']);
                $images = array_map(fn($f) => $f['path'], $saved);

                $outName = uniqueName('imagenes_a_pdf', 'pdf');
                $outPath = $DIR_OUTPUT . '/' . $outName;

                imagesToPdf($images, $outPath);

                setFlash('success', 'PDF generado desde imágenes.');
                $_SESSION['last_output'] = $outName;
                break;
            }

            case 'word_to_pdf': {
                $files = normalizeFilesArray($_FILES['files'] ?? []);
                $saved = moveUploadedFiles($files, $jobDir, ['doc', 'docx', 'odt']);

                if (count($saved) !== 1) {
                    throw new RuntimeException('Debés subir un solo archivo Word/ODT.');
                }

                $converted = convertWithLibreOffice($saved[0]['path'], $jobDir, 'pdf');
                $outName = uniqueName('word_a_pdf', 'pdf');
                $outPath = $DIR_OUTPUT . '/' . $outName;

                if (!copy($converted, $outPath)) {
                    throw new RuntimeException('No se pudo copiar el PDF generado.');
                }

                setFlash('success', 'Word convertido a PDF.');
                $_SESSION['last_output'] = $outName;
                break;
            }

            case 'pdf_to_word': {
                $files = normalizeFilesArray($_FILES['files'] ?? []);
                $saved = moveUploadedFiles($files, $jobDir, ['pdf']);

                if (count($saved) !== 1) {
                    throw new RuntimeException('Debés subir un solo PDF.');
                }

                $converted = convertWithLibreOffice($saved[0]['path'], $jobDir, 'docx');
                $outName = uniqueName('pdf_a_word', 'docx');
                $outPath = $DIR_OUTPUT . '/' . $outName;

                if (!copy($converted, $outPath)) {
                    throw new RuntimeException('No se pudo copiar el DOCX generado.');
                }

                setFlash('success', 'PDF convertido a Word. Revisá el formato, porque depende del PDF original.');
                $_SESSION['last_output'] = $outName;
                break;
            }

            case 'pdf_to_images': {
                $files = normalizeFilesArray($_FILES['files'] ?? []);
                $saved = moveUploadedFiles($files, $jobDir, ['pdf']);

                if (count($saved) !== 1) {
                    throw new RuntimeException('Debés subir un solo PDF.');
                }

                $format = strtolower((string)($_POST['image_format'] ?? 'png'));
                $density = (int)($_POST['density'] ?? 150);
                $density = max(72, min(300, $density));

                $outName = uniqueName('pdf_a_imagenes', 'zip');
                $outPath = $DIR_OUTPUT . '/' . $outName;

                pdfToImages($saved[0]['path'], $outPath, $jobDir, $format, $density);

                setFlash('success', 'PDF convertido a imágenes dentro de un ZIP.');
                $_SESSION['last_output'] = $outName;
                break;
            }

            case 'rotate_pdf': {
                $files = normalizeFilesArray($_FILES['files'] ?? []);
                $saved = moveUploadedFiles($files, $jobDir, ['pdf']);

                if (count($saved) !== 1) {
                    throw new RuntimeException('Debés subir un solo PDF.');
                }

                $degrees = (int)($_POST['degrees'] ?? 90);
                if (!in_array($degrees, [90, 180, 270], true)) {
                    throw new RuntimeException('Los grados permitidos son 90, 180 o 270.');
                }

                $outName = uniqueName('pdf_rotado', 'pdf');
                $outPath = $DIR_OUTPUT . '/' . $outName;

                rotatePdf($saved[0]['path'], $outPath, $degrees);

                setFlash('success', 'PDF rotado correctamente.');
                $_SESSION['last_output'] = $outName;
                break;
            }

            case 'watermark_pdf': {
                $files = normalizeFilesArray($_FILES['files'] ?? []);
                $saved = moveUploadedFiles($files, $jobDir, ['pdf']);

                if (count($saved) !== 1) {
                    throw new RuntimeException('Debés subir un solo PDF.');
                }

                $text = trim((string)($_POST['watermark_text'] ?? 'CONFIDENCIAL'));
                if ($text === '') {
                    throw new RuntimeException('La marca de agua no puede estar vacía.');
                }

                $outName = uniqueName('pdf_marca_agua', 'pdf');
                $outPath = $DIR_OUTPUT . '/' . $outName;

                addTextWatermark($saved[0]['path'], $outPath, $text);

                setFlash('success', 'Marca de agua agregada correctamente.');
                $_SESSION['last_output'] = $outName;
                break;
            }

            default:
                throw new RuntimeException('Herramienta no válida.');
        }
    } catch (Throwable $ex) {
        setFlash('danger', $ex->getMessage());
    } finally {
        rrmdir($jobDir);
    }

    redirectSelf();
}

$lastOutput = $_SESSION['last_output'] ?? null;
if ($lastOutput && !is_file($DIR_OUTPUT . '/' . basename($lastOutput))) {
    $lastOutput = null;
    unset($_SESSION['last_output']);
}
$SELF_WEB = (string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
$BASE_PUBLIC_WEB = rtrim(str_replace('\\', '/', dirname($SELF_WEB)), '/');
$BASE_APP_WEB = rtrim(str_replace('\\', '/', dirname($BASE_PUBLIC_WEB)), '/');
$ASSET_WEB = $BASE_APP_WEB . '/assets';
$IMG_BG = $ASSET_WEB . '/img/fondo.png';
$ESCUDO = $ASSET_WEB . '/img/ecmilm.png';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title><?= e(APP_NAME) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
    html,body{ min-height:100%; }
    body{
        margin:0;
        background: url("<?= e($IMG_BG) ?>") no-repeat center center fixed;
        background-size: cover;
        background-attachment: fixed;
        background-color:#020617;
        color:#e5eefb;
        font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;
    }
    .page-wrap{ padding:18px; position:relative; z-index:2; }
    .container-main{ max-width:1400px; margin:auto; }
    .panel{
        background:rgba(15,17,23,.94);
        border:1px solid rgba(148,163,184,.40);
        border-radius:18px;
        padding:18px 22px 22px;
        box-shadow:0 18px 40px rgba(0,0,0,.75), inset 0 1px 0 rgba(255,255,255,.05);
    }
    .brand-hero{ padding-top:10px; padding-bottom:10px; position:relative; z-index:3; }
    .brand-hero .hero-inner{ display:flex; align-items:center; justify-content:space-between; gap:12px; }
    .brand-title{ font-weight:900; font-size:1.1rem; line-height:1.1; color:#e5e7eb; }
    .brand-sub{ font-size:.9rem; color:#cbd5f5; opacity:.9; margin-top:2px; }
    .header-back{
        margin-left:auto;
        margin-right:17px;
        margin-top:4px;
        display:flex;
        gap:8px;
    }
    .hero{
        background:linear-gradient(180deg, rgba(15,23,42,.96), rgba(12,18,30,.94));
        border:1px solid rgba(148,163,184,.24);
        border-radius:20px;
        padding:22px;
        margin-bottom:18px;
        box-shadow:0 14px 32px rgba(0,0,0,.45);
    }
    .hero h1{
        font-size:1.9rem;
        font-weight:900;
        margin:0 0 8px;
    }
    .hero p{
        margin:0;
        color:#b9c6db;
    }
    .hero-badges{
        display:flex;
        flex-wrap:wrap;
        gap:10px;
        margin-top:14px;
    }
    .hero-badge{
        display:inline-flex;
        align-items:center;
        gap:.45rem;
        padding:.35rem .7rem;
        border-radius:999px;
        background:rgba(15,23,42,.92);
        border:1px solid rgba(148,163,184,.24);
        color:#cbd5f5;
        font-size:.8rem;
        font-weight:800;
    }
    .grid-tools{
        display:grid;
        grid-template-columns:repeat(auto-fit,minmax(320px,1fr));
        gap:16px;
    }
    .tool-card{
        background:rgba(15,23,42,.95);
        border:1px solid rgba(148,163,184,.28);
        border-radius:20px;
        padding:18px;
        box-shadow:0 12px 28px rgba(0,0,0,.38);
    }
    .tool-title{
        font-size:1.1rem;
        font-weight:900;
        margin-bottom:8px;
    }
    .tool-desc{
        color:#aebcd2;
        font-size:.95rem;
        min-height:48px;
        margin-bottom:12px;
    }
    .form-label{
        font-weight:700;
        color:#dbe8fb;
    }
    .form-control, .form-select{
        background:#0f172a;
        border:1px solid rgba(148,163,184,.25);
        color:#e5eefb;
        border-radius:14px;
    }
    .form-control:focus, .form-select:focus{
        background:#0f172a;
        color:#fff;
        border-color:#22c55e;
        box-shadow:0 0 0 .18rem rgba(34,197,94,.18);
    }
    .btn-main{
        background:#16a34a;
        border:none;
        border-radius:14px;
        font-weight:800;
        padding:.72rem 1rem;
    }
    .btn-main:hover{
        background:#4ade80;
        color:#052e16;
    }
    .note{
        background:rgba(15,23,42,.86);
        border:1px solid rgba(148,163,184,.28);
        border-radius:16px;
        padding:14px;
        margin-top:18px;
        color:#dbe7f6;
    }
    .download-box{
        background:rgba(20,83,45,.16);
        border:1px solid rgba(34,197,94,.25);
        color:#dcfce7;
        border-radius:16px;
        padding:14px;
        margin-bottom:18px;
    }
    .small-help{
        color:#9fb0c9;
        font-size:.88rem;
    }
    .alert{
        border-radius:16px;
        border:none;
    }
    .tool-card input:disabled,
    .tool-card select:disabled,
    .tool-card button:disabled{
        opacity:.6;
        cursor:not-allowed;
    }
</style>
</head>
<body>
<header class="brand-hero">
    <div class="hero-inner container-main">
        <div class="d-flex align-items-center gap-3">
            <img src="<?= e($ESCUDO) ?>" alt="EC MIL M" style="height:52px;width:auto;"
                 onerror="this.onerror=null;this.style.display='none';">
            <div>
                <div class="brand-title">Escuela Militar de Montaña</div>
                <div class="brand-sub">Herramientas documentales</div>
            </div>
        </div>
        <div class="header-back">
            <a href="<?= e($BASE_PUBLIC_WEB) ?>/inicio.php" class="btn btn-success btn-sm" style="font-weight:700; padding:.35rem .9rem;">
                <i class="bi bi-house-door"></i> Inicio
            </a>
        </div>
    </div>
</header>
<div class="page-wrap">
<div class="container-main">
<div class="panel">

    <div class="hero">
        <h1>Editor de Documentos</h1>
        <p>
            Herramientas documentales en PHP para PDF, Word e imágenes.
            Base tipo iLovePDF para integrar a tu sistema.
        </p>
        <div class="hero-badges">
            <div class="hero-badge"><i class="bi bi-file-earmark-pdf"></i> PDF</div>
            <div class="hero-badge"><i class="bi bi-file-earmark-word"></i> Word</div>
            <div class="hero-badge"><i class="bi bi-images"></i> Imágenes</div>
            <div class="hero-badge"><i class="bi bi-shield-check"></i> Integrado al sistema</div>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>"><?= nl2br(e($flash['message'])) ?></div>
    <?php endif; ?>

    <?php if (!PDF_TOOLKIT_AVAILABLE): ?>
        <div class="alert alert-warning">
            Las herramientas que usan FPDI/FPDF están deshabilitadas en este servidor.
            Instalá <code>setasign/fpdf</code> y <code>setasign/fpdi</code> con Composer para habilitarlas.
        </div>
    <?php endif; ?>

    <?php if ($lastOutput): ?>
        <div class="download-box">
            Último archivo generado:
            <strong><?= e($lastOutput) ?></strong><br>
            <a class="btn btn-success btn-sm mt-2" href="?download=<?= urlencode($lastOutput) ?>">Descargar</a>
        </div>
    <?php endif; ?>

    <div class="grid-tools">

        <div class="tool-card">
            <div class="tool-title">Unir PDF</div>
            <div class="tool-desc">Subí varios PDF y generá un solo archivo final.</div>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="tool" value="merge_pdf">
                <label class="form-label">PDF</label>
                <input class="form-control" type="file" name="files[]" accept=".pdf" multiple required <?= PDF_TOOLKIT_AVAILABLE ? '' : 'disabled' ?>>
                <div class="small-help mt-2">Podés subir varios archivos.</div>
                <button class="btn btn-main text-white mt-3 w-100" type="submit" <?= PDF_TOOLKIT_AVAILABLE ? '' : 'disabled' ?>>Unir PDF</button>
            </form>
        </div>

        <div class="tool-card">
            <div class="tool-title">Imágenes a PDF</div>
            <div class="tool-desc">Convertí JPG, PNG o WEBP a un PDF multipágina.</div>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="tool" value="images_to_pdf">
                <label class="form-label">Imágenes</label>
                <input class="form-control" type="file" name="files[]" accept=".jpg,.jpeg,.png,.webp" multiple required <?= PDF_TOOLKIT_AVAILABLE ? '' : 'disabled' ?>>
                <button class="btn btn-main text-white mt-3 w-100" type="submit" <?= PDF_TOOLKIT_AVAILABLE ? '' : 'disabled' ?>>Convertir a PDF</button>
            </form>
        </div>

        <div class="tool-card">
            <div class="tool-title">Word a PDF</div>
            <div class="tool-desc">Convierte DOC, DOCX u ODT a PDF usando LibreOffice.</div>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="tool" value="word_to_pdf">
                <label class="form-label">Archivo Word/ODT</label>
                <input class="form-control" type="file" name="files[]" accept=".doc,.docx,.odt" required>
                <button class="btn btn-main text-white mt-3 w-100" type="submit">Word a PDF</button>
            </form>
        </div>

        <div class="tool-card">
            <div class="tool-title">PDF a Word</div>
            <div class="tool-desc">Convierte PDF a DOCX. El resultado depende mucho del PDF original.</div>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="tool" value="pdf_to_word">
                <label class="form-label">PDF</label>
                <input class="form-control" type="file" name="files[]" accept=".pdf" required <?= PDF_TOOLKIT_AVAILABLE ? '' : 'disabled' ?>>
                <button class="btn btn-main text-white mt-3 w-100" type="submit" <?= PDF_TOOLKIT_AVAILABLE ? '' : 'disabled' ?>>PDF a Word</button>
            </form>
        </div>

        <div class="tool-card">
            <div class="tool-title">PDF a Imágenes</div>
            <div class="tool-desc">Extrae cada página del PDF como imagen y devuelve un ZIP.</div>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="tool" value="pdf_to_images">
                <label class="form-label">PDF</label>
                <input class="form-control" type="file" name="files[]" accept=".pdf" required <?= PDF_TOOLKIT_AVAILABLE ? '' : 'disabled' ?>>

                <div class="row mt-3">
                    <div class="col-md-6">
                        <label class="form-label">Formato</label>
                        <select name="image_format" class="form-select" <?= PDF_TOOLKIT_AVAILABLE ? '' : 'disabled' ?>>
                            <option value="png">PNG</option>
                            <option value="jpg">JPG</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Calidad (DPI)</label>
                        <select name="density" class="form-select" <?= PDF_TOOLKIT_AVAILABLE ? '' : 'disabled' ?>>
                            <option value="100">100</option>
                            <option value="150" selected>150</option>
                            <option value="200">200</option>
                            <option value="300">300</option>
                        </select>
                    </div>
                </div>

                <button class="btn btn-main text-white mt-3 w-100" type="submit" <?= PDF_TOOLKIT_AVAILABLE ? '' : 'disabled' ?>>PDF a Imágenes</button>
            </form>
        </div>

        <div class="tool-card">
            <div class="tool-title">Rotar PDF</div>
            <div class="tool-desc">Rota todas las páginas del PDF 90°, 180° o 270°.</div>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="tool" value="rotate_pdf">
                <label class="form-label">PDF</label>
                <input class="form-control" type="file" name="files[]" accept=".pdf" required <?= PDF_TOOLKIT_AVAILABLE ? '' : 'disabled' ?>>

                <label class="form-label mt-3">Grados</label>
                <select name="degrees" class="form-select" <?= PDF_TOOLKIT_AVAILABLE ? '' : 'disabled' ?>>
                    <option value="90">90°</option>
                    <option value="180">180°</option>
                    <option value="270">270°</option>
                </select>

                <button class="btn btn-main text-white mt-3 w-100" type="submit" <?= PDF_TOOLKIT_AVAILABLE ? '' : 'disabled' ?>>Rotar PDF</button>
            </form>
        </div>

        <div class="tool-card">
            <div class="tool-title">Marca de agua</div>
            <div class="tool-desc">Agrega un texto diagonal tipo CONFIDENCIAL o BORRADOR.</div>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="tool" value="watermark_pdf">
                <label class="form-label">PDF</label>
                <input class="form-control" type="file" name="files[]" accept=".pdf" required <?= PDF_TOOLKIT_AVAILABLE ? '' : 'disabled' ?>>

                <label class="form-label mt-3">Texto</label>
                <input class="form-control" type="text" name="watermark_text" value="CONFIDENCIAL" maxlength="60" required <?= PDF_TOOLKIT_AVAILABLE ? '' : 'disabled' ?>>

                <button class="btn btn-main text-white mt-3 w-100" type="submit" <?= PDF_TOOLKIT_AVAILABLE ? '' : 'disabled' ?>>Agregar marca de agua</button>
            </form>
        </div>

    </div>

    <div class="note">
        <strong>Importante:</strong><br>
        1. Este módulo resuelve muy bien tareas documentales comunes.<br>
        2. La conversión <strong>PDF a Word</strong> nunca queda perfecta en todos los casos; depende de cómo fue creado el PDF.<br>
        3. Si querés un “editor PDF” más completo, lo normal es sumar herramientas externas o separar en módulos:
        unir, dividir, extraer páginas, comprimir, firmar, OCR, etc.
    </div>

</div>
</div>
</div>
</body>
</html>
