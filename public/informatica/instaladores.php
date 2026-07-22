<?php
declare(strict_types=1);

require_once __DIR__ . '/../../auth/bootstrap.php';
require_login();

$ROOT = realpath(__DIR__ . '/../..') ?: dirname(__DIR__, 2);
$BASE_DIR = $ROOT . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'unidades' . DIRECTORY_SEPARATOR . 'ecmilm' . DIRECTORY_SEPARATOR . 'INFORMATICA' . DIRECTORY_SEPARATOR . 'APLICACIONES Y SOFTWARE INSTALADORES';
$BASE_REAL = realpath($BASE_DIR);

function inst_e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function inst_norm_rel(string $path): ?string {
    $path = trim(str_replace('\\', '/', $path));
    if ($path === '' || $path === '/') return '';
    $parts = array_values(array_filter(explode('/', $path), static fn($p) => $p !== '' && $p !== '.'));
    $safe = [];
    foreach ($parts as $part) {
        if ($part === '..') return null;
        $safe[] = $part;
    }
    return implode('/', $safe);
}

function inst_under(string $path, string $base): bool {
    $path = rtrim(str_replace('\\', '/', $path), '/') . '/';
    $base = rtrim(str_replace('\\', '/', $base), '/') . '/';
    return strncmp($path, $base, strlen($base)) === 0;
}

function inst_size(int $bytes): string {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return number_format($bytes / 1024, 1) . ' KB';
    if ($bytes < 1073741824) return number_format($bytes / 1048576, 2) . ' MB';
    return number_format($bytes / 1073741824, 2) . ' GB';
}

function inst_mime(string $ext): string {
    return match (strtolower($ext)) {
        'exe', 'msi' => 'application/octet-stream',
        'iso' => 'application/x-iso9660-image',
        'zip' => 'application/zip',
        'rar' => 'application/vnd.rar',
        '7z' => 'application/x-7z-compressed',
        'pdf' => 'application/pdf',
        'txt', 'log' => 'text/plain; charset=UTF-8',
        default => 'application/octet-stream',
    };
}

function inst_send_file(string $file): void {
    if (!is_file($file) || !is_readable($file)) {
        http_response_code(404);
        exit('Archivo no disponible.');
    }
    $name = basename($file);
    $size = (int)@filesize($file);
    header('X-Content-Type-Options: nosniff');
    header('Content-Type: ' . inst_mime((string)pathinfo($file, PATHINFO_EXTENSION)));
    header('Content-Length: ' . $size);
    header('Content-Disposition: attachment; filename="' . rawurlencode($name) . '"');
    header('Cache-Control: private, max-age=0, no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    @readfile($file);
    exit;
}

if (!$BASE_REAL || !is_dir($BASE_REAL)) {
    http_response_code(500);
    exit('No existe la carpeta de instaladores.');
}

if (isset($_GET['download']) && (string)$_GET['download'] === '1') {
    $rel = inst_norm_rel((string)($_GET['file'] ?? ''));
    if ($rel === null || $rel === '') {
        http_response_code(404);
        exit('Archivo no encontrado.');
    }
    $target = realpath($BASE_REAL . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel));
    if (!$target || !inst_under($target, $BASE_REAL) || !is_file($target)) {
        http_response_code(404);
        exit('Archivo no encontrado.');
    }
    inst_send_file($target);
}

$dirRel = inst_norm_rel((string)($_GET['dir'] ?? ''));
if ($dirRel === null) $dirRel = '';
$currentAbs = $dirRel === '' ? $BASE_REAL : ($BASE_REAL . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $dirRel));
$currentReal = realpath($currentAbs);
if (!$currentReal || !is_dir($currentReal) || !inst_under($currentReal, $BASE_REAL)) {
    $dirRel = '';
    $currentReal = $BASE_REAL;
}

$items = @scandir($currentReal);
$entries = [];
if (is_array($items)) {
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $abs = $currentReal . DIRECTORY_SEPARATOR . $item;
        $isDir = is_dir($abs);
        $rel = $dirRel === '' ? $item : ($dirRel . '/' . $item);
        $entries[] = [
            'name' => $item,
            'rel' => $rel,
            'is_dir' => $isDir,
            'size' => $isDir ? null : (int)@filesize($abs),
            'mtime' => (int)@filemtime($abs),
            'ext' => $isDir ? '' : strtoupper((string)pathinfo($item, PATHINFO_EXTENSION)),
        ];
    }
}
usort($entries, static function (array $a, array $b): int {
    if ($a['is_dir'] !== $b['is_dir']) return $a['is_dir'] ? -1 : 1;
    return strcasecmp((string)$a['name'], (string)$b['name']);
});

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
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Instaladores - Informatica</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
  body{margin:0;min-height:100vh;background:#fff;color:#111827;font-family:system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,sans-serif;}
  .wrap{max-width:1180px;margin:0 auto;padding:20px;}
  .top{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;padding:18px 0 20px;}
  .kicker{font-size:.75rem;text-transform:uppercase;letter-spacing:.12em;color:#1d4ed8;font-weight:900;}
  h1{font-size:1.75rem;margin:3px 0 0;font-weight:950;}
  .actions{display:flex;gap:8px;flex-wrap:wrap;}
  .btn-soft{display:inline-flex;align-items:center;gap:8px;border:1px solid #cbd5e1;border-radius:10px;padding:.55rem .85rem;color:#111827;background:#f8fafc;text-decoration:none;font-weight:800;}
  .btn-soft:hover{background:#e5e7eb;color:#111827;}
  .path{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:0 0 14px;color:#374151;}
  .path a{color:#1d4ed8;text-decoration:none;font-weight:800;}
  .panel{border:1px solid #d1d5db;border-radius:14px;overflow:hidden;background:#fff;}
  table{width:100%;border-collapse:collapse;min-width:760px;}
  th,td{padding:.78rem .9rem;border-bottom:1px solid #e5e7eb;vertical-align:middle;}
  th{background:#f3f4f6;color:#111827;font-size:.78rem;text-transform:uppercase;letter-spacing:.08em;}
  tr:hover td{background:#eff6ff;}
  .table-wrap{overflow:auto;}
  .name{display:flex;align-items:center;gap:10px;min-width:0;}
  .ico{width:34px;height:34px;border-radius:10px;display:inline-flex;align-items:center;justify-content:center;background:#e0f2fe;color:#075985;flex:0 0 auto;}
  .file-link{color:#111827;text-decoration:none;font-weight:800;word-break:break-word;}
  .file-link:hover{color:#1d4ed8;}
  .muted{color:#6b7280;font-size:.84rem;}
  .empty{padding:34px 16px;text-align:center;color:#374151;}
</style>
</head>
<body>
<div class="wrap">
  <header class="top">
    <div>
      <div class="kicker">Informatica</div>
      <h1>Aplicaciones e instaladores</h1>
    </div>
    <div class="actions">
      <?php if ($dirRel !== ''): ?>
        <a class="btn-soft" href="<?= inst_e($_SERVER['PHP_SELF'] ?? 'instaladores.php') ?><?= $parentRel !== '' ? ('?dir=' . inst_e(rawurlencode($parentRel))) : '' ?>"><i class="bi bi-arrow-left"></i> Volver</a>
      <?php endif; ?>
      <a class="btn-soft" href="<?= inst_e($_SERVER['PHP_SELF'] ?? 'instaladores.php') ?>"><i class="bi bi-house"></i> Inicio</a>
    </div>
  </header>

  <nav class="path">
    <a href="<?= inst_e($_SERVER['PHP_SELF'] ?? 'instaladores.php') ?>">Instaladores</a>
    <?php foreach ($segments as $seg): ?>
      <span>/</span>
      <a href="<?= inst_e($_SERVER['PHP_SELF'] ?? 'instaladores.php') ?>?dir=<?= inst_e(rawurlencode((string)$seg['rel'])) ?>"><?= inst_e((string)$seg['name']) ?></a>
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
              <th style="width:110px;">Tipo</th>
              <th style="width:130px;">Tamano</th>
              <th style="width:170px;">Modificado</th>
              <th style="width:150px;">Accion</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($entries as $entry): ?>
              <?php
                $isDir = (bool)$entry['is_dir'];
                $rel = (string)$entry['rel'];
                $href = $isDir
                    ? ((string)($_SERVER['PHP_SELF'] ?? 'instaladores.php') . '?dir=' . rawurlencode($rel))
                    : ((string)($_SERVER['PHP_SELF'] ?? 'instaladores.php') . '?download=1&file=' . rawurlencode($rel));
              ?>
              <tr>
                <td>
                  <div class="name">
                    <span class="ico"><i class="bi <?= $isDir ? 'bi-folder-fill' : 'bi-file-earmark-arrow-down' ?>"></i></span>
                    <div>
                      <a class="file-link" href="<?= inst_e($href) ?>"><?= inst_e((string)$entry['name']) ?></a>
                      <div class="muted"><?= inst_e($rel) ?></div>
                    </div>
                  </div>
                </td>
                <td><?= $isDir ? 'Carpeta' : inst_e((string)($entry['ext'] ?: 'Archivo')) ?></td>
                <td><?= $isDir ? '-' : inst_e(inst_size((int)$entry['size'])) ?></td>
                <td><?= date('d/m/Y H:i', (int)$entry['mtime']) ?></td>
                <td>
                  <a class="btn btn-sm <?= $isDir ? 'btn-outline-light' : 'btn-success' ?>" href="<?= inst_e($href) ?>">
                    <i class="bi <?= $isDir ? 'bi-box-arrow-in-right' : 'bi-download' ?>"></i>
                    <?= $isDir ? 'Entrar' : 'Descargar' ?>
                  </a>
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
