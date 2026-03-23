<?php
declare(strict_types=1);

if (!isset($AREA_TITLE, $AREA_CODE, $AREA_SLUG, $AREA_ROOT_ABS, $BACK_LINK)) {
    http_response_code(500);
    exit('Configuracion incompleta del explorador.');
}

function area_shared_e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function area_shared_starts_with(string $haystack, string $needle): bool {
    return substr($haystack, 0, strlen($needle)) === $needle;
}

function area_shared_normalize_rel_path(string $path): ?string {
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

function area_shared_human_size(int $bytes): string {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1024 * 1024) return number_format($bytes / 1024, 1) . ' KB';
    if ($bytes < 1024 * 1024 * 1024) return number_format($bytes / 1024 / 1024, 2) . ' MB';
    return number_format($bytes / 1024 / 1024 / 1024, 2) . ' GB';
}

function area_shared_format_dt(?int $ts): string {
    if (!$ts || $ts <= 0) return '-';
    return date('d/m/Y H:i', $ts);
}

function area_shared_mime_for_ext(string $ext): string {
    $ext = strtolower($ext);
    return match ($ext) {
        'pdf' => 'application/pdf',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'xls' => 'application/vnd.ms-excel',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'jpg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'txt', 'log' => 'text/plain; charset=UTF-8',
        'csv' => 'text/csv; charset=UTF-8',
        'zip' => 'application/zip',
        default => 'application/octet-stream',
    };
}

function area_shared_send_file(string $absPath, string $downloadName, string $mime): void {
    if (!is_file($absPath) || !is_readable($absPath)) {
        http_response_code(404);
        exit('Archivo no disponible.');
    }
    $size = (int)@filesize($absPath);
    header('X-Content-Type-Options: nosniff');
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . $size);
    header('Content-Disposition: inline; filename="' . rawurlencode($downloadName) . '"');
    header('Cache-Control: private, max-age=0, no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    @readfile($absPath);
    exit;
}

function area_shared_scan_dir(string $baseAbs, string $relative): array {
    $baseReal = realpath($baseAbs);
    if ($baseReal === false) return ['ok' => false, 'error' => 'No existe la carpeta compartida del area.', 'current' => '', 'entries' => []];
    $relative = area_shared_normalize_rel_path($relative);
    if ($relative === null) return ['ok' => false, 'error' => 'Ruta invalida.', 'current' => '', 'entries' => []];
    $targetAbs = $relative === '' ? $baseReal : ($baseReal . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));
    $targetReal = realpath($targetAbs);
    if ($targetReal === false || !is_dir($targetReal) || !area_shared_starts_with($targetReal, $baseReal)) {
        return ['ok' => false, 'error' => 'La carpeta solicitada no existe.', 'current' => $relative, 'entries' => []];
    }
    $items = @scandir($targetReal);
    if (!is_array($items)) return ['ok' => false, 'error' => 'No se pudo leer la carpeta.', 'current' => $relative, 'entries' => []];

    $entries = [];
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $abs = $targetReal . DIRECTORY_SEPARATOR . $item;
        $isDir = is_dir($abs);
        $entries[] = [
            'name' => $item,
            'rel' => $relative === '' ? $item : ($relative . '/' . $item),
            'is_dir' => $isDir,
            'size' => $isDir ? null : (int)@filesize($abs),
            'mtime' => (int)@filemtime($abs),
            'ext' => $isDir ? '' : strtolower((string)pathinfo($item, PATHINFO_EXTENSION)),
        ];
    }

    usort($entries, static function (array $a, array $b): int {
        if ($a['is_dir'] !== $b['is_dir']) return $a['is_dir'] ? -1 : 1;
        return strcasecmp((string)$a['name'], (string)$b['name']);
    });

    return ['ok' => true, 'error' => '', 'current' => $relative, 'entries' => $entries];
}

$SELF_WEB = (string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
$BASE_DIR_WEB = rtrim(str_replace('\\', '/', dirname($SELF_WEB)), '/');
$BASE_PUBLIC_WEB = rtrim(str_replace('\\', '/', dirname($BASE_DIR_WEB)), '/');
$BASE_APP_WEB = rtrim(str_replace('\\', '/', dirname($BASE_PUBLIC_WEB)), '/');
$ASSET_WEB = $BASE_APP_WEB . '/assets';
$IMG_BG = $ASSET_WEB . '/img/fondo.png';
$ESCUDO = $ASSET_WEB . '/img/ecmilm.png';
$FAVICON = $ESCUDO;

if (isset($_GET['download']) && (string)($_GET['download']) === '1') {
    $rel = area_shared_normalize_rel_path((string)($_GET['path'] ?? ''));
    $base = realpath($AREA_ROOT_ABS);
    if ($rel === null || $rel === '' || !$base) {
        http_response_code(400);
        exit('Ruta invalida.');
    }
    $abs = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    $realAbs = realpath($abs);
    if (!$realAbs || !is_file($realAbs) || !area_shared_starts_with($realAbs, $base)) {
        http_response_code(404);
        exit('Archivo no encontrado.');
    }
    area_shared_send_file($realAbs, basename($realAbs), area_shared_mime_for_ext((string)pathinfo($realAbs, PATHINFO_EXTENSION)));
}

$browseRel = area_shared_normalize_rel_path((string)($_GET['dir'] ?? ''));
if ($browseRel === null) $browseRel = '';
$sharedState = area_shared_scan_dir($AREA_ROOT_ABS, $browseRel);
$segments = [];
if ($sharedState['ok'] && $sharedState['current'] !== '') {
    $acc = [];
    foreach (explode('/', (string)$sharedState['current']) as $seg) {
        $acc[] = $seg;
        $segments[] = ['name' => $seg, 'rel' => implode('/', $acc)];
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title><?= area_shared_e($AREA_TITLE) ?> · Carpeta compartida</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="icon" href="<?= area_shared_e($FAVICON) ?>">
<style>
  html,body{min-height:100%;}
  body{margin:0;color:#e5e7eb;background:linear-gradient(160deg, rgba(0,0,0,.84), rgba(2,6,23,.92)),url("<?= area_shared_e($IMG_BG) ?>") center/cover fixed no-repeat;font-family:system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,sans-serif;}
  .container-main{max-width:1420px;margin:auto;padding:18px;}
  .hero,.panel-box{background:rgba(15,23,42,.9);border:1px solid rgba(148,163,184,.22);border-radius:22px;box-shadow:0 18px 40px rgba(0,0,0,.35);}
  .hero{padding:22px;margin-bottom:18px;}
  .hero-top,.panel-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;}
  .hero-brand{display:flex;gap:14px;align-items:center;}
  .hero-brand img{width:58px;height:58px;object-fit:contain;border-radius:16px;background:rgba(255,255,255,.05);padding:6px;}
  .hero-kicker{font-size:.72rem;letter-spacing:.12em;text-transform:uppercase;color:#bfdbfe;font-weight:900;}
  .hero-title{font-size:1.8rem;font-weight:900;margin-top:4px;}
  .hero-sub,.panel-sub{max-width:760px;color:#cbd5f5;margin-top:8px;line-height:1.55;}
  .hero-actions{display:flex;gap:10px;flex-wrap:wrap;}
  .btn-soft{display:inline-flex;align-items:center;gap:8px;padding:.58rem .95rem;border-radius:12px;background:rgba(15,23,42,.76);color:#eef2ff;text-decoration:none;border:1px solid rgba(148,163,184,.24);font-weight:800;}
  .btn-soft:hover{color:#fff;border-color:rgba(96,165,250,.45);background:rgba(30,41,59,.86);}
  .panel-box{padding:18px;}
  .panel-title{font-size:1.12rem;font-weight:900;display:flex;align-items:center;gap:10px;}
  .pathbar{display:flex;align-items:center;gap:8px;flex-wrap:wrap;color:#dbe7f6;font-size:.88rem;margin-bottom:14px;}
  .pathbar a{color:#93c5fd;text-decoration:none;font-weight:800;}
  .table-wrap{overflow:auto;border-radius:18px;}
  table{width:100%;border-collapse:collapse;min-width:820px;background:rgba(15,23,42,.46);}
  th,td{padding:.78rem .85rem;border-bottom:1px solid rgba(148,163,184,.10);vertical-align:middle;}
  th{background:rgba(15,23,42,.98);color:#bfdbfe;font-size:.77rem;text-transform:uppercase;letter-spacing:.08em;}
  tr:hover td{background:rgba(59,130,246,.08);}
  .item-name{display:flex;align-items:center;gap:12px;min-width:0;}
  .item-icon{width:38px;height:38px;border-radius:12px;display:inline-flex;align-items:center;justify-content:center;background:rgba(15,23,42,.92);border:1px solid rgba(148,163,184,.16);font-size:1.15rem;flex:0 0 auto;}
  .item-link{color:#eef2ff;text-decoration:none;font-weight:800;}
  .item-link:hover{color:#93c5fd;}
  .item-meta{color:#94a3b8;font-size:.76rem;margin-top:2px;}
  .type-pill{display:inline-flex;align-items:center;justify-content:center;padding:.22rem .56rem;border-radius:999px;background:rgba(148,163,184,.12);border:1px solid rgba(148,163,184,.20);color:#dbe7f6;font-size:.72rem;font-weight:900;}
  .empty-state{padding:28px 16px;text-align:center;color:#c3d0e2;border:1px dashed rgba(148,163,184,.24);border-radius:18px;background:rgba(15,23,42,.36);}
</style>
</head>
<body>
<div class="container-main">
  <section class="hero">
    <div class="hero-top">
      <div class="hero-brand">
        <img src="<?= area_shared_e($ESCUDO) ?>" alt="EC MIL M" onerror="this.style.display='none'">
        <div>
          <div class="hero-kicker"><?= area_shared_e($AREA_CODE) ?> <?= area_shared_e($AREA_TITLE) ?></div>
          <div class="hero-title">Carpeta compartida</div>
          <div class="hero-sub">Navegá los archivos físicos del área en <code><?= area_shared_e(str_replace('\\', '/', $AREA_ROOT_ABS)) ?></code>.</div>
        </div>
      </div>
      <div class="hero-actions">
        <a class="btn-soft" href="<?= area_shared_e($BACK_LINK) ?>"><i class="bi bi-arrow-left"></i> Volver</a>
        <a class="btn-soft" href="<?= area_shared_e($BASE_PUBLIC_WEB) ?>/inicio.php"><i class="bi bi-house-door"></i> Inicio</a>
      </div>
    </div>
  </section>
  <section class="panel-box">
    <div class="panel-head">
      <div>
        <div class="panel-title"><i class="bi bi-folder2-open"></i> Explorador de archivos</div>
        <div class="panel-sub">Mostramos carpetas primero y archivos después, sin salir del árbol permitido.</div>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <?php if ($sharedState['current'] !== ''): ?>
          <?php $partsParent = explode('/', (string)$sharedState['current']); array_pop($partsParent); $parentRel = implode('/', $partsParent); ?>
          <a class="btn-soft" href="<?= area_shared_e($SELF_WEB) ?><?= $parentRel !== '' ? ('?dir=' . area_shared_e(rawurlencode($parentRel))) : '' ?>"><i class="bi bi-arrow-left"></i> Volver</a>
        <?php endif; ?>
        <a class="btn-soft" href="<?= area_shared_e($SELF_WEB) ?>"><i class="bi bi-house"></i> Raiz</a>
      </div>
    </div>
    <?php if (!$sharedState['ok']): ?>
      <div class="alert alert-warning mb-0"><?= area_shared_e((string)$sharedState['error']) ?></div>
    <?php else: ?>
      <div class="pathbar">
        <span><b>Ruta actual:</b></span>
        <a href="<?= area_shared_e($SELF_WEB) ?>"><?= area_shared_e($AREA_SLUG) ?></a>
        <?php foreach ($segments as $seg): ?>
          <span>/</span>
          <a href="<?= area_shared_e($SELF_WEB) ?>?dir=<?= area_shared_e(rawurlencode((string)$seg['rel'])) ?>"><?= area_shared_e((string)$seg['name']) ?></a>
        <?php endforeach; ?>
      </div>
      <?php if (empty($sharedState['entries'])): ?>
        <div class="empty-state">Esta carpeta está vacía.</div>
      <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Nombre</th>
                <th style="width:130px;">Tipo</th>
                <th style="width:170px;">Modificado</th>
                <th style="width:130px;">Tamaño</th>
                <th style="width:140px;">Acción</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($sharedState['entries'] as $entry): ?>
              <?php $isDir = (bool)$entry['is_dir']; $rel = (string)$entry['rel']; $href = $isDir ? ($SELF_WEB . '?dir=' . rawurlencode($rel)) : ($SELF_WEB . '?download=1&path=' . rawurlencode($rel)); ?>
              <tr>
                <td>
                  <div class="item-name">
                    <div class="item-icon"><?= $isDir ? '📁' : '📄' ?></div>
                    <div style="min-width:0;">
                      <a class="item-link" <?= $isDir ? '' : 'target="_blank" rel="noopener"' ?> href="<?= area_shared_e($href) ?>"><?= area_shared_e((string)$entry['name']) ?></a>
                      <div class="item-meta"><?= area_shared_e($rel) ?></div>
                    </div>
                  </div>
                </td>
                <td><span class="type-pill"><?= $isDir ? 'Carpeta' : area_shared_e(strtoupper((string)($entry['ext'] !== '' ? $entry['ext'] : 'archivo'))) ?></span></td>
                <td><?= area_shared_e(area_shared_format_dt((int)$entry['mtime'])) ?></td>
                <td><?= $isDir ? '-' : area_shared_e(area_shared_human_size((int)($entry['size'] ?? 0))) ?></td>
                <td><a class="btn btn-outline-light btn-sm" <?= $isDir ? '' : 'target="_blank" rel="noopener"' ?> href="<?= area_shared_e($href) ?>"><?= $isDir ? 'Entrar' : 'Abrir' ?></a></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </section>
</div>
</body>
</html>
