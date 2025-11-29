<?php
// public/explorador_storage.php — Explorador de archivos de /storage con filtros por tipo y carpeta
declare(strict_types=1);

require_once __DIR__ . '/../auth/bootstrap.php';
require_login();
require_once __DIR__ . '/../config/db.php';

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* ===== Restricción de acceso: solo admin de la app ===== */
$user    = function_exists('current_user') ? current_user() : null;
$roleApp = $user['role_app'] ?? 'usuario';

if ($roleApp !== 'admin') {
    http_response_code(403);
    echo 'Acceso restringido. Solo administradores.';
    exit;
}

/* ===== Config básica ===== */

// Prefijos de los tres sistemas que ya usás
$SOURCES = [
    'ultima_inspeccion'       => 'Última inspección',
    'listas_control'          => 'Listas de control',
    'visitas_de_estado_mayor' => 'Visitas de Estado Mayor',
];

// Aliases para S1..S4 (para listas_control)
$AREA_ALIASES = [
    'S1' => 'Personal (S-1)',
    'S2' => 'Inteligencia (S-2)',
    'S3' => 'Operaciones (S-3)',
    'S4' => 'Materiales (S-4)',
];

/* ===== Filtros desde GET ===== */
$tipoSel    = $_GET['tipo']    ?? 'todos'; // todos | clave del array $SOURCES
$carpetaSel = $_GET['carpeta'] ?? 'todas'; // label de carpeta o "todas"

/* ===== Paths base ===== */
$projectBase = realpath(__DIR__ . '/..');
if (!$projectBase) {
    http_response_code(500);
    echo "No se pudo resolver la ruta base del proyecto.";
    exit;
}

/* ===== Recolección de archivos ===== */

$rows = [];                 // filas finales
$carpetasPorTipo = [];      // para armar combo de carpetas filtrables

foreach ($SOURCES as $slug => $labelTipo) {
    $baseDir = realpath($projectBase . '/storage/' . $slug);
    if (!$baseDir || !is_dir($baseDir)) {
        continue;
    }

    $rii = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($baseDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($rii as $f) {
        if (!$f->isFile()) continue;

        $ext = strtolower($f->getExtension());
        // Mostramos XLSX, CSV, PDF. Si querés más, agregalos acá.
        if (!in_array($ext, ['xlsx','csv','pdf'], true)) continue;

        $abs = $f->getPathname();
        $rel = str_replace('\\','/', substr($abs, strlen($projectBase)+1)); // relativo al proyecto

        // Carpeta interna (lo que mostrás en la columna CARPETA)
        $internal = substr($abs, strlen($baseDir)+1);
        $internal = str_replace('\\','/',$internal);
        $dirName  = trim(dirname($internal), '/.');
        if ($dirName === '') {
            $carpetaLabel = '(Raíz)';
        } else {
            // Para listas_control, reemplazamos S1..S4 por alias amigable
            if ($slug === 'listas_control') {
                $parts = explode('/', $dirName);
                if (!empty($parts[0]) && isset($AREA_ALIASES[$parts[0]])) {
                    $parts[0] = $AREA_ALIASES[$parts[0]];
                }
                $carpetaLabel = implode('/', $parts);
            } else {
                $carpetaLabel = $dirName;
            }
        }

        // Guardamos carpetas únicas por tipo para el combo
        $carpetasPorTipo[$slug][$carpetaLabel] = true;

        // Link para abrir
        $urlVer = '';
        if (in_array($ext, ['xlsx','csv'], true)) {
            // Lo mandamos a ver_tabla.php con ?p=rel
            $urlVer = 'ver_tabla.php?p=' . rawurlencode($rel);
        } elseif ($ext === 'pdf') {
            // Lo abrimos directo
            $urlVer = '../' . $rel;
        }

        $rows[] = [
            'tipo_slug'   => $slug,
            'tipo_label'  => $labelTipo,
            'archivo'     => $f->getFilename(),
            'carpeta'     => $carpetaLabel,
            'ext'         => strtoupper($ext),
            'size_bytes'  => $f->getSize(),
            'mtime'       => $f->getMTime(),
            'rel'         => $rel,
            'url'         => $urlVer,
        ];
    }
}

// Normalizar arrays de carpetas
foreach ($carpetasPorTipo as $slug => $arr) {
    $labs = array_keys($arr);
    sort($labs, SORT_NATURAL | SORT_FLAG_CASE);
    $carpetasPorTipo[$slug] = $labs;
}

/* ===== Aplicar filtros ===== */
$rowsFiltradas = array_filter($rows, function(array $r) use ($tipoSel, $carpetaSel){
    if ($tipoSel !== 'todos' && $r['tipo_slug'] !== $tipoSel) return false;
    if ($carpetaSel !== 'todas' && $r['carpeta'] !== $carpetaSel) return false;
    return true;
});

// Orden general: tipo, carpeta, archivo
usort($rowsFiltradas, function($a,$b){
    $k1 = $a['tipo_label'] . '|' . $a['carpeta'] . '|' . $a['archivo'];
    $k2 = $b['tipo_label'] . '|' . $b['carpeta'] . '|' . $b['archivo'];
    return strcasecmp($k1,$k2);
});

/* ===== Helpers visuales ===== */
function format_size($bytes): string {
    $bytes = (float)$bytes;
    if ($bytes >= 1048576) return round($bytes/1048576, 1) . ' MB';
    if ($bytes >= 1024)    return round($bytes/1024, 1) . ' KB';
    return $bytes . ' B';
}

/* ===== Assets ===== */
$PUBLIC_URL = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'])), '/');
$APP_URL    = rtrim(str_replace('\\','/', dirname($PUBLIC_URL)), '/');
$ASSETS_URL = ($APP_URL === '' ? '' : $APP_URL) . '/assets';
$IMG_BG     = $ASSETS_URL . '/img/fondo.png';
$ESCUDO     = $ASSETS_URL . '/img/escudo_bcom602.png';

$totalArchivos = count($rowsFiltradas);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Explorador de archivos · Batallón de Comunicaciones 602</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/theme-602.css">
<link rel="icon" type="image/png" href="../assets/img/bcom602.png">
<style>
  body{
    background: url("<?= e($IMG_BG) ?>") no-repeat center center fixed;
    background-size: cover;
    background-attachment: fixed;
    background-color:#020617;
    color:#e5e7eb;
    font-family:system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,sans-serif;
    margin:0; padding:0;
  }
  .page-wrap{ padding:18px; }
  .container-main{ max-width:1600px; margin:auto; }

  .panel{
    background:rgba(15,17,23,.92);
    border:1px solid rgba(148,163,184,.35);
    border-radius:18px;
    padding:10px 14px 12px;
    box-shadow:0 18px 40px rgba(0,0,0,.7), inset 0 1px 0 rgba(255,255,255,.05);
    backdrop-filter:blur(8px);
  }

  .toolbar-top{
    display:flex;
    flex-wrap:wrap;
    align-items:center;
    gap:.75rem;
    margin-bottom:12px;
  }

  .tag-tipo{
    display:inline-flex;
    align-items:center;
    padding:.18rem .55rem;
    border-radius:999px;
    border:1px solid rgba(148,163,184,.65);
    font-size:.72rem;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.06em;
    background:#020617;
    color:#e5e7eb;
  }
  .tag-tipo--ultima{ border-color:#22c55e; color:#bbf7d0; background:rgba(21,128,61,.35); }
  .tag-tipo--listas{ border-color:#38bdf8; color:#e0f2fe; background:rgba(8,47,73,.60); }
  .tag-tipo--visitas{ border-color:#f97316; color:#ffedd5; background:rgba(88,28,10,.70); }

  .filter-label{ font-size:.8rem; font-weight:700; color:#9ca3af; text-transform:uppercase; letter-spacing:.08em; }
  .filter-select{
    background:#020617;
    border-radius:999px;
    border:1px solid rgba(148,163,184,.7);
    color:#e5e7eb;
    font-size:.85rem;
    font-weight:600;
    padding:.28rem .9rem;
    min-width:200px;
  }
  .filter-select:focus{ outline:none; box-shadow:0 0 0 1px #22c55e; border-color:#22c55e; }

  .summary-text{ font-size:.8rem; color:#9ca3af; }

  table.tbl{ width:100%; border-collapse:collapse; font-size:.86rem; }
  .tbl thead th{
    position:sticky; top:0; z-index:3;
    background:#020617;
    border-bottom:1px solid rgba(148,163,184,.6);
    padding:.55rem .75rem;
    text-transform:uppercase;
    letter-spacing:.06em;
    font-weight:800;
    font-size:.78rem;
  }
  .tbl tbody td{
    padding:.5rem .75rem;
    border-bottom:1px solid rgba(31,41,55,.85);
    vertical-align:middle;
  }
  .tbl tbody tr:nth-child(even){ background:rgba(15,23,42,.60); }
  .tbl tbody tr:nth-child(odd){ background:rgba(15,23,42,.35); }
  .tbl tbody tr:hover{ background:rgba(30,64,175,.55); }

  .cell-file{
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
    max-width:360px;
  }

  .badge-ext{
    font-size:.72rem;
    padding:.2rem .54rem;
    border-radius:999px;
    border:1px solid rgba(148,163,184,.75);
    background:#020617;
    font-weight:700;
  }
  .badge-ext-xlsx{ border-color:#22c55e; color:#bbf7d0; }
  .badge-ext-pdf{ border-color:#f97316; color:#fed7aa; }

  .btn-open{
    padding:.25rem .7rem;
    border-radius:999px;
    border:none;
    background:#22c55e;
    color:#022c16;
    font-size:.8rem;
    font-weight:800;
    text-decoration:none;
  }
  .btn-open:hover{
    background:#4ade80;
    color:#022c16;
  }
</style>
</head>
<body>

<header class="brand-hero">
  <div class="hero-inner container-main">
    <img class="brand-logo" src="<?= e($ESCUDO) ?>" alt="Escudo 602">
    <div>
      <div class="brand-title">Batallón de Comunicaciones 602</div>
      <div class="brand-sub">“Hogar de las Comunicaciones Fijas del Ejército”</div>
    </div>
    <div class="ms-auto">
      <a href="index.php" class="btn btn-success btn-sm" style="font-weight:700;">Volver al dashboard</a>
    </div>
  </div>
</header>

<div class="page-wrap">
  <div class="container-main">
    <div class="panel">

      <!-- Filtros -->
      <form method="get" class="toolbar-top">
        <div>
          <div class="filter-label">Filtrar por tipo</div>
          <select name="tipo" class="filter-select" onchange="this.form.submit()">
            <option value="todos" <?= $tipoSel==='todos'?'selected':'' ?>>Todos</option>
            <?php foreach($SOURCES as $slug => $lab): ?>
              <option value="<?= e($slug) ?>" <?= $tipoSel===$slug?'selected':'' ?>><?= e($lab) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <div class="filter-label">Carpeta</div>
          <select name="carpeta" class="filter-select" onchange="this.form.submit()">
            <option value="todas" <?= $carpetaSel==='todas'?'selected':'' ?>>Todas</option>
            <?php
              // Opciones de carpeta según tipo actual (si hay uno específico seleccionado)
              if ($tipoSel !== 'todos' && isset($carpetasPorTipo[$tipoSel])) {
                  foreach($carpetasPorTipo[$tipoSel] as $cLab){
                      $sel = ($carpetaSel === $cLab) ? 'selected' : '';
                      echo '<option value="'.e($cLab).'" '.$sel.'>'.e($cLab).'</option>';
                  }
              } else {
                  // Si está en "todos", mostramos todas las carpetas únicas de todos los tipos
                  $allCarps = [];
                  foreach($carpetasPorTipo as $slug => $arr){
                      foreach($arr as $cLab){
                          $allCarps[$cLab] = true;
                      }
                  }
                  $allList = array_keys($allCarps);
                  sort($allList, SORT_NATURAL | SORT_FLAG_CASE);
                  foreach($allList as $cLab){
                      $sel = ($carpetaSel === $cLab) ? 'selected' : '';
                      echo '<option value="'.e($cLab).'" '.$sel.'>'.e($cLab).'</option>';
                  }
              }
            ?>
          </select>
        </div>

        <div class="ms-auto summary-text">
          Total archivos: <strong><?= (int)$totalArchivos ?></strong>
        </div>
      </form>

      <!-- Tabla -->
      <div class="table-responsive" style="max-height:75vh; border-radius:14px; overflow:hidden;">
        <table class="tbl">
          <thead>
            <tr>
              <th style="width:140px;">Tipo</th>
              <th>Archivo</th>
              <th>Carpeta</th>
              <th style="width:80px;">Ext</th>
              <th style="width:110px;">Tamaño</th>
              <th style="width:170px;">Modificado</th>
              <th style="width:110px;">Acción</th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($rowsFiltradas)): ?>
            <tr>
              <td colspan="7" class="text-center text-muted py-3">
                No se encontraron archivos con ese filtro.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach($rowsFiltradas as $r): ?>
              <?php
                $tagClass = '';
                if ($r['tipo_slug'] === 'ultima_inspeccion')       $tagClass = 'tag-tipo--ultima';
                elseif ($r['tipo_slug'] === 'listas_control')      $tagClass = 'tag-tipo--listas';
                elseif ($r['tipo_slug'] === 'visitas_de_estado_mayor') $tagClass = 'tag-tipo--visitas';

                $extLower = strtolower($r['ext']);
                $extClass = '';
                if ($extLower === 'xlsx' || $extLower === 'csv') $extClass = 'badge-ext-xlsx';
                elseif ($extLower === 'pdf')                     $extClass = 'badge-ext-pdf';
              ?>
              <tr>
                <td>
                  <span class="tag-tipo <?= $tagClass ?>">
                    <?= e($r['tipo_label']) ?>
                  </span>
                </td>
                <td class="cell-file" title="<?= e($r['archivo']) ?>">
                  <?= e($r['archivo']) ?>
                </td>
                <td><?= e($r['carpeta']) ?></td>
                <td>
                  <span class="badge-ext <?= $extClass ?>"><?= e($r['ext']) ?></span>
                </td>
                <td><?= e(format_size($r['size_bytes'])) ?></td>
                <td><?= e(date('d/m/Y H:i', $r['mtime'])) ?></td>
                <td>
                  <?php if ($r['url']): ?>
                    <a class="btn-open" href="<?= e($r['url']) ?>" <?= ($extLower==='pdf' ? 'target="_blank"' : '') ?>>
                      Abrir
                    </a>
                  <?php else: ?>
                    <span class="text-muted" style="font-size:.78rem;">Sin visor</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>

    </div>
  </div>
</div>

</body>
</html>
