<?php
declare(strict_types=1);

require_once __DIR__ . '/../../auth/bootstrap.php';
require_login();
require_once __DIR__ . '/../../config/db.php';

function doc_e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function doc_norm_rel(string $path): ?string {
    $path = trim(str_replace('\\', '/', $path));
    if ($path === '' || $path === '/') return '';
    $parts = array_values(array_filter(explode('/', $path), static fn($p) => $p !== '' && $p !== '.'));
    $safe = [];
    foreach ($parts as $part) {
        if ($part === '..' || str_contains($part, "\0")) return null;
        $safe[] = $part;
    }
    return implode('/', $safe);
}

function doc_is_under(string $path, string $base): bool {
    $path = rtrim(str_replace('\\', '/', $path), '/') . '/';
    $base = rtrim(str_replace('\\', '/', $base), '/') . '/';
    return strncmp($path, $base, strlen($base)) === 0;
}

function doc_size(int $bytes): string {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return number_format($bytes / 1024, 1, ',', '.') . ' KB';
    if ($bytes < 1073741824) return number_format($bytes / 1048576, 2, ',', '.') . ' MB';
    return number_format($bytes / 1073741824, 2, ',', '.') . ' GB';
}

function doc_mime(string $file): string {
    $ext = strtolower((string)pathinfo($file, PATHINFO_EXTENSION));
    return match ($ext) {
        'pdf' => 'application/pdf',
        'txt', 'log', 'md', 'csv' => 'text/plain; charset=UTF-8',
        'jpg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'zip' => 'application/zip',
        'rar' => 'application/vnd.rar',
        '7z' => 'application/x-7z-compressed',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        default => 'application/octet-stream',
    };
}

function doc_can_inline(string $file): bool {
    return in_array(strtolower((string)pathinfo($file, PATHINFO_EXTENSION)), ['pdf', 'txt', 'log', 'md', 'csv', 'jpg', 'jpeg', 'png', 'webp', 'gif'], true);
}

function doc_icon(string $ext, bool $isDir): string {
    if ($isDir) return 'bi-folder-fill';
    return match (strtolower($ext)) {
        'pdf' => 'bi-file-earmark-pdf-fill',
        'zip', 'rar', '7z' => 'bi-file-earmark-zip-fill',
        'doc', 'docx' => 'bi-file-earmark-word-fill',
        'xls', 'xlsx', 'csv' => 'bi-file-earmark-spreadsheet-fill',
        'ppt', 'pptx' => 'bi-file-earmark-slides-fill',
        'jpg', 'jpeg', 'png', 'webp', 'gif' => 'bi-file-earmark-image-fill',
        default => 'bi-file-earmark-fill',
    };
}

function doc_send_file(string $file, bool $inline): void {
    if (!is_file($file) || !is_readable($file)) {
        http_response_code(404);
        exit('Archivo no disponible.');
    }

    $name = basename($file);
    $size = (int)@filesize($file);
    $mime = doc_mime($file);
    $disposition = ($inline && doc_can_inline($file)) ? 'inline' : 'attachment';

    header('X-Content-Type-Options: nosniff');
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . $size);
    header('Content-Disposition: ' . $disposition . '; filename="' . rawurlencode($name) . '"');
    header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    @readfile($file);
    exit;
}

$ROOT = realpath(__DIR__ . '/../..') ?: dirname(__DIR__, 2);
$unidadSlug = 'ecmilm';
if (function_exists('unidad_context')) {
    $ctx = unidad_context($pdo);
    $slug = trim((string)($ctx['slug'] ?? ''));
    if ($slug !== '') $unidadSlug = $slug;
}

$BASE_DIR = $ROOT . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'unidades' . DIRECTORY_SEPARATOR . $unidadSlug . DIRECTORY_SEPARATOR . 'INFORMATICA' . DIRECTORY_SEPARATOR . 'DOCUMENTACION INFORMATICA EA';
$BASE_REAL = realpath($BASE_DIR);
if (!$BASE_REAL || !is_dir($BASE_REAL)) {
    $fallback = $ROOT . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'unidades' . DIRECTORY_SEPARATOR . 'ecmilm' . DIRECTORY_SEPARATOR . 'INFORMATICA' . DIRECTORY_SEPARATOR . 'DOCUMENTACION INFORMATICA EA';
    $BASE_REAL = realpath($fallback);
}

if (!$BASE_REAL || !is_dir($BASE_REAL)) {
    http_response_code(404);
    exit('No existe la carpeta de documentación rectora del área.');
}

$fileRel = doc_norm_rel((string)($_GET['file'] ?? ''));
if ($fileRel !== null && $fileRel !== '' && isset($_GET['open'])) {
    $target = realpath($BASE_REAL . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $fileRel));
    if (!$target || !doc_is_under($target, $BASE_REAL) || !is_file($target)) {
        http_response_code(404);
        exit('Archivo no encontrado.');
    }
    doc_send_file($target, true);
}

if ($fileRel !== null && $fileRel !== '' && isset($_GET['download'])) {
    $target = realpath($BASE_REAL . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $fileRel));
    if (!$target || !doc_is_under($target, $BASE_REAL) || !is_file($target)) {
        http_response_code(404);
        exit('Archivo no encontrado.');
    }
    doc_send_file($target, false);
}

$dirRel = doc_norm_rel((string)($_GET['dir'] ?? ''));
if ($dirRel === null) $dirRel = '';
$currentReal = $dirRel === ''
    ? $BASE_REAL
    : realpath($BASE_REAL . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $dirRel));
if (!$currentReal || !is_dir($currentReal) || !doc_is_under($currentReal, $BASE_REAL)) {
    $dirRel = '';
    $currentReal = $BASE_REAL;
}

$entries = [];
$rawItems = @scandir($currentReal);
if (is_array($rawItems)) {
    foreach ($rawItems as $item) {
        if ($item === '.' || $item === '..') continue;
        $abs = $currentReal . DIRECTORY_SEPARATOR . $item;
        $real = realpath($abs);
        if (!$real || !doc_is_under($real, $BASE_REAL)) continue;
        $isDir = is_dir($real);
        $rel = $dirRel === '' ? $item : ($dirRel . '/' . $item);
        $entries[] = [
            'name' => $item,
            'rel' => $rel,
            'is_dir' => $isDir,
            'size' => $isDir ? 0 : (int)@filesize($real),
            'mtime' => (int)@filemtime($real),
            'ext' => $isDir ? '' : strtolower((string)pathinfo($item, PATHINFO_EXTENSION)),
        ];
    }
}

usort($entries, static function (array $a, array $b): int {
    if ($a['is_dir'] !== $b['is_dir']) return $a['is_dir'] ? -1 : 1;
    return strcasecmp((string)$a['name'], (string)$b['name']);
});

$allFiles = 0;
$allDirs = 0;
$allBytes = 0;
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($BASE_REAL, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);
foreach ($iterator as $node) {
    if ($node->isDir()) {
        $allDirs++;
    } elseif ($node->isFile()) {
        $allFiles++;
        $allBytes += (int)$node->getSize();
    }
}

$segments = [];
if ($dirRel !== '') {
    $acc = [];
    foreach (explode('/', $dirRel) as $seg) {
        $acc[] = $seg;
        $segments[] = ['name' => $seg, 'rel' => implode('/', $acc)];
    }
}
$parentRel = '';
if ($dirRel !== '') {
    $parts = explode('/', $dirRel);
    array_pop($parts);
    $parentRel = implode('/', $parts);
}

$SELF = (string)($_SERVER['PHP_SELF'] ?? 'info_documentacion.php');
$backUrl = 'informatica.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Documentación rectora · Informática</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
  :root{--bg:#07111f;--panel:#0f172a;--panel2:#111827;--line:#334155;--text:#e5e7eb;--muted:#94a3b8;--accent:#38bdf8;--ok:#22c55e;}
  *{box-sizing:border-box}
  body{margin:0;min-height:100vh;background:linear-gradient(160deg,#020617,#111827 48%,#07111f);color:var(--text);font-family:system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,sans-serif;}
  .wrap{max-width:1320px;margin:0 auto;padding:20px;}
  .top{display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap;padding:12px 0 18px;}
  .kicker{font-size:.76rem;text-transform:uppercase;letter-spacing:.16em;color:#7dd3fc;font-weight:950;}
  h1{font-size:1.55rem;margin:.2rem 0;font-weight:950;}
  .sub{color:#cbd5e1;max-width:860px;font-size:.92rem;}
  .actions{display:flex;gap:8px;flex-wrap:wrap;}
  .btnx{display:inline-flex;align-items:center;gap:8px;border:1px solid #475569;border-radius:10px;padding:.55rem .82rem;color:#e5e7eb;background:rgba(15,23,42,.88);text-decoration:none;font-weight:850;}
  .btnx:hover{background:#1e293b;color:#fff}
  .btnx.ok{background:rgba(34,197,94,.16);border-color:rgba(34,197,94,.45);color:#bbf7d0}
  .stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin:4px 0 16px;}
  .stat{background:rgba(15,23,42,.78);border:1px solid rgba(148,163,184,.28);border-radius:12px;padding:12px;}
  .stat b{display:block;font-size:1.2rem;color:#fff}
  .stat span{font-size:.78rem;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);font-weight:900}
  .path{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:0 0 12px;color:#cbd5e1;}
  .path a{color:#bae6fd;text-decoration:none;font-weight:900;}
  .panel{border:1px solid rgba(148,163,184,.32);border-radius:14px;background:rgba(15,23,42,.82);overflow:hidden;box-shadow:0 22px 50px rgba(0,0,0,.42);}
  .table-wrap{overflow:auto;}
  table{width:100%;border-collapse:collapse;min-width:880px;}
  th,td{padding:.78rem .9rem;border-bottom:1px solid rgba(148,163,184,.18);vertical-align:middle;}
  th{background:rgba(30,41,59,.92);color:#bfdbfe;font-size:.76rem;text-transform:uppercase;letter-spacing:.08em;}
  tr:hover td{background:rgba(14,165,233,.08);}
  .name{display:flex;align-items:center;gap:11px;min-width:0;}
  .ico{width:38px;height:38px;border-radius:10px;display:inline-flex;align-items:center;justify-content:center;background:rgba(56,189,248,.14);color:#7dd3fc;flex:0 0 auto;font-size:1.15rem;}
  .file-link{color:#f8fafc;text-decoration:none;font-weight:900;word-break:break-word;}
  .file-link:hover{color:#7dd3fc;}
  .muted{color:var(--muted);font-size:.82rem;}
  .badge-ext{display:inline-flex;align-items:center;border:1px solid rgba(148,163,184,.32);background:rgba(15,23,42,.9);border-radius:999px;padding:.18rem .55rem;font-size:.74rem;font-weight:900;color:#dbeafe;}
  .row-actions{display:flex;gap:7px;flex-wrap:wrap;}
  .mini{display:inline-flex;align-items:center;gap:6px;border-radius:9px;border:1px solid rgba(148,163,184,.32);background:rgba(15,23,42,.72);color:#e5e7eb;text-decoration:none;font-size:.78rem;font-weight:850;padding:.4rem .62rem;}
  .mini:hover{background:#1e293b;color:#fff}
  .mini.primary{border-color:rgba(56,189,248,.5);color:#bae6fd}
  .empty{padding:38px 16px;text-align:center;color:#cbd5e1;}
  @media (max-width:720px){.wrap{padding:14px}.stats{grid-template-columns:1fr}.top{display:block}.actions{margin-top:12px}}
</style>
</head>
<body>
<div class="wrap">
  <header class="top">
    <div>
      <div class="kicker">Informática · documentación rectora</div>
      <h1>Directivas y documentación crítica del área</h1>
      <div class="sub">Acceso interno protegido. Los archivos se abren mediante esta página para no publicar rutas reales del servidor.</div>
    </div>
    <div class="actions">
      <?php if ($dirRel !== ''): ?>
        <a class="btnx" href="<?= doc_e($SELF) ?><?= $parentRel !== '' ? ('?dir=' . doc_e(rawurlencode($parentRel))) : '' ?>"><i class="bi bi-arrow-left"></i> Volver</a>
      <?php endif; ?>
      <a class="btnx" href="<?= doc_e($SELF) ?>"><i class="bi bi-house"></i> Raíz</a>
      <a class="btnx ok" href="<?= doc_e($backUrl) ?>"><i class="bi bi-arrow-return-left"></i> Informática</a>
    </div>
  </header>

  <section class="stats" aria-label="Resumen">
    <div class="stat"><span>Archivos</span><b><?= (int)$allFiles ?></b></div>
    <div class="stat"><span>Carpetas</span><b><?= (int)$allDirs ?></b></div>
    <div class="stat"><span>Tamaño total</span><b><?= doc_e(doc_size($allBytes)) ?></b></div>
  </section>

  <nav class="path" aria-label="Ruta">
    <a href="<?= doc_e($SELF) ?>">Documentación Informática EA</a>
    <?php foreach ($segments as $seg): ?>
      <span>/</span>
      <a href="<?= doc_e($SELF) ?>?dir=<?= doc_e(rawurlencode((string)$seg['rel'])) ?>"><?= doc_e((string)$seg['name']) ?></a>
    <?php endforeach; ?>
  </nav>

  <section class="panel">
    <?php if (!$entries): ?>
      <div class="empty">No hay archivos en esta carpeta.</div>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Nombre</th>
              <th style="width:120px;">Tipo</th>
              <th style="width:130px;">Tamaño</th>
              <th style="width:170px;">Modificado</th>
              <th style="width:220px;">Acción</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($entries as $entry): ?>
              <?php
                $isDir = (bool)$entry['is_dir'];
                $rel = (string)$entry['rel'];
                $ext = (string)$entry['ext'];
                $dirHref = $SELF . '?dir=' . rawurlencode($rel);
                $openHref = $SELF . '?open=1&file=' . rawurlencode($rel);
                $downHref = $SELF . '?download=1&file=' . rawurlencode($rel);
              ?>
              <tr>
                <td>
                  <div class="name">
                    <span class="ico"><i class="bi <?= doc_e(doc_icon($ext, $isDir)) ?>"></i></span>
                    <div>
                      <a class="file-link" href="<?= doc_e($isDir ? $dirHref : (doc_can_inline((string)$entry['name']) ? $openHref : $downHref)) ?>">
                        <?= doc_e((string)$entry['name']) ?>
                      </a>
                      <div class="muted"><?= doc_e($rel) ?></div>
                    </div>
                  </div>
                </td>
                <td><?= $isDir ? '<span class="badge-ext">Carpeta</span>' : '<span class="badge-ext">' . doc_e(strtoupper($ext ?: 'Archivo')) . '</span>' ?></td>
                <td><?= $isDir ? '-' : doc_e(doc_size((int)$entry['size'])) ?></td>
                <td><?= date('d/m/Y H:i', (int)$entry['mtime']) ?></td>
                <td>
                  <div class="row-actions">
                    <?php if ($isDir): ?>
                      <a class="mini primary" href="<?= doc_e($dirHref) ?>"><i class="bi bi-box-arrow-in-right"></i> Entrar</a>
                    <?php else: ?>
                      <?php if (doc_can_inline((string)$entry['name'])): ?>
                        <a class="mini primary" href="<?= doc_e($openHref) ?>" target="_blank" rel="noopener"><i class="bi bi-eye"></i> Abrir</a>
                      <?php endif; ?>
                      <a class="mini" href="<?= doc_e($downHref) ?>"><i class="bi bi-download"></i> Descargar</a>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</div>
</body>
</html>
