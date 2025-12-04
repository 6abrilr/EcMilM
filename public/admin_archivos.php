<?php
// public/admin_archivos.php — Explorador y gestión de archivos de /storage
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

// Aliases para S1..S5 (para listas_control)
$AREA_ALIASES = [
    'S1' => 'Personal (S-1)',
    'S2' => 'Inteligencia (S-2)',
    'S3' => 'Operaciones (S-3)',
    'S4' => 'Materiales (S-4)',
    'S5' => 'Presupuesto (S-5)',
];

// Mapa inverso para volver de alias a S1..S5
$AREA_ALIASES_REV = array_flip($AREA_ALIASES);

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

/* ===== Mensajes ===== */
$mensaje = '';
$mensaje_tipo = 'success';

/* ===== Helpers de seguridad ===== */
function is_path_under(string $child, string $parent): bool {
    $parent = rtrim(str_replace('\\','/',$parent), '/') . '/';
    $child  = str_replace('\\','/',$child);
    return strncmp($child, $parent, strlen($parent)) === 0;
}

/* ===== Procesar acciones POST (eliminar / subir) ANTES de listar ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    // Eliminar archivo
    if ($accion === 'delete') {
        $rel = $_POST['rel'] ?? '';
        $rel = str_replace(['..','\\'], ['','/'], $rel);
        $abs = realpath($projectBase . '/' . $rel);

        if ($abs && is_file($abs) && is_path_under($abs, $projectBase . '/storage')) {
            if (@unlink($abs)) {
                $mensaje = 'Archivo eliminado correctamente.';
                $mensaje_tipo = 'success';
            } else {
                $mensaje = 'No se pudo eliminar el archivo (permiso o bloqueo).';
                $mensaje_tipo = 'danger';
            }
        } else {
            $mensaje = 'Ruta inválida o fuera de /storage.';
            $mensaje_tipo = 'danger';
        }
    }

    // Subir archivo
    if ($accion === 'upload') {
        if ($tipoSel === 'todos' || $carpetaSel === 'todas') {
            $mensaje = 'Elegí un tipo específico y una carpeta para subir archivos.';
            $mensaje_tipo = 'warning';
        } elseif (!isset($SOURCES[$tipoSel])) {
            $mensaje = 'Tipo de sistema inválido.';
            $mensaje_tipo = 'danger';
        } elseif (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            $mensaje = 'Error al recibir el archivo. Verificá el tamaño o intentá de nuevo.';
            $mensaje_tipo = 'danger';
        } else {
            // Directorio base del tipo elegido
            $baseDir = realpath($projectBase . '/storage/' . $tipoSel);
            if (!$baseDir || !is_dir($baseDir)) {
                $mensaje = 'Directorio base no encontrado para el tipo seleccionado.';
                $mensaje_tipo = 'danger';
            } else {
                // Resolver carpeta interna a partir de la etiqueta
                $internalDir = '';

                if ($carpetaSel === '(Raíz)') {
                    $internalDir = '';
                } else {
                    // Para listas_control hay que devolver S1..S5 en el primer segmento
                    if ($tipoSel === 'listas_control') {
                        $parts = explode('/', $carpetaSel);
                        if (!empty($parts[0]) && isset($AREA_ALIASES_REV[$parts[0]])) {
                            $parts[0] = $AREA_ALIASES_REV[$parts[0]];
                        }
                        $internalDir = implode('/', $parts);
                    } else {
                        $internalDir = $carpetaSel;
                    }
                }

                $targetDir = $baseDir;
                if ($internalDir !== '') {
                    $targetDir = $targetDir . '/' . $internalDir;
                }

                // Normalizar y asegurar que está bajo /storage/tipoSel
                $targetDirReal = realpath($targetDir);
                if (!$targetDirReal || !is_dir($targetDirReal) || !is_path_under($targetDirReal, $baseDir)) {
                    $mensaje = 'Carpeta destino inválida o inexistente.';
                    $mensaje_tipo = 'danger';
                } else {
                    $nombre = basename($_FILES['archivo']['name']);
                    $nombre = str_replace(['\\','/'], '_', $nombre);
                    $dest   = $targetDirReal . '/' . $nombre;

                    if (file_exists($dest)) {
                        $mensaje = 'Ya existe un archivo con ese nombre en esa carpeta.';
                        $mensaje_tipo = 'warning';
                    } else {
                        if (@move_uploaded_file($_FILES['archivo']['tmp_name'], $dest)) {
                            $mensaje = 'Archivo subido correctamente.';
                            $mensaje_tipo = 'success';
                        } else {
                            $mensaje = 'No se pudo guardar el archivo en disco.';
                            $mensaje_tipo = 'danger';
                        }
                    }
                }
            }
        }
    }
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
            // Para listas_control, reemplazamos S1..S5 por alias amigable
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

// Label para el manual según tipo seleccionado
$manualTipoLabel = ($tipoSel !== 'todos' && isset($SOURCES[$tipoSel])) ? $SOURCES[$tipoSel] : null;
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

  .toolbar-upload{
    display:flex;
    flex-wrap:wrap;
    align-items:center;
    gap:.75rem;
    margin-top:8px;
    margin-bottom:8px;
    padding-top:8px;
    border-top:1px dashed rgba(148,163,184,.45);
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
    display:inline-block;
  }
  .btn-open:hover{
    background:#4ade80;
    color:#022c16;
  }

  .btn-open-delete{
    background:#ef4444;
    color:#fee2e2;
  }
  .btn-open-delete:hover{
    background:#f97373;
    color:#fff;
  }

  .btn-actions{
    display:flex;
    flex-wrap:wrap;
    gap:4px;
  }

  .upload-input{
    background:#020617;
    border-radius:999px;
    border:1px solid rgba(148,163,184,.7);
    color:#e5e7eb;
    font-size:.82rem;
    padding:.25rem .6rem;
  }
  .upload-input:focus{
    outline:none;
    box-shadow:0 0 0 1px #22c55e;
    border-color:#22c55e;
  }

  .btn-upload{
    padding:.28rem .9rem;
    border-radius:999px;
    border:none;
    background:#22c55e;
    color:#022c16;
    font-size:.8rem;
    font-weight:800;
  }
  .btn-upload[disabled]{
    opacity:.4;
    cursor:not-allowed;
  }

  .manual-box{
    margin-top:6px;
    margin-bottom:10px;
    padding:10px 12px;
    border-radius:12px;
    border:1px solid rgba(56,189,248,.6);
    background:rgba(15,23,42,.95);
    font-size:.8rem;
  }
  .manual-box-title{
    font-weight:800;
    font-size:.9rem;
    margin-bottom:4px;
    color:#e0f2fe;
  }
  .manual-box-body p{
    margin-bottom:4px;
  }
  .manual-box-body ol{
    margin:0 0 4px 1.1rem;
    padding:0;
  }
    .brand-hero{
    padding-top:10px;
    padding-bottom:10px;
  }

  .brand-hero .hero-inner{
    align-items:center;
    display:flex;
    justify-content:space-between;
    gap:12px;
  }

  .brand-title{
    font-weight:800;
    font-size:1rem;
  }

  .brand-sub{
    font-size:.8rem;
    color:#9ca3af;
  }

  .header-back{
    margin-left:auto;
    margin-right:17px;
    margin-top:4px;
    display:flex;
    gap:8px;
  }

</style>
</head>
<body>

<header class="brand-hero">
  <div class="hero-inner container-main">
    <div class="d-flex align-items-center gap-3">
      <img class="brand-logo" src="<?= e($ESCUDO) ?>" alt="Escudo 602" style="height:52px; width:auto;">
      <div>
        <div class="brand-title">Batallón de Comunicaciones 602</div>
        <div class="brand-sub">“Hogar de las Comunicaciones Fijas del Ejército”</div>
      </div>
    </div>

    <div class="header-back">
      <a href="gestiones.php"
         class="btn btn-success btn-sm"
         style="font-weight:700; padding:.35rem .9rem;">
        Volver
      </a>
    </div>
  </div>
</header>

<div class="page-wrap">
  <div class="container-main">
    <div class="panel">

      <?php if ($mensaje !== ''): ?>
        <div class="alert alert-<?= e($mensaje_tipo) ?> py-2 mb-3">
          <?= e($mensaje) ?>
        </div>
      <?php endif; ?>

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
              if ($tipoSel !== 'todos' && isset($carpetasPorTipo[$tipoSel])) {
                  foreach($carpetasPorTipo[$tipoSel] as $cLab){
                      $sel = ($carpetaSel === $cLab) ? 'selected' : '';
                      echo '<option value="'.e($cLab).'" '.$sel.'>'.e($cLab).'</option>';
                  }
              } else {
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

      <!-- Subida de archivos -->
      <form method="post" enctype="multipart/form-data" class="toolbar-upload">
        <input type="hidden" name="accion" value="upload">
        <div style="font-size:.8rem; color:#9ca3af; max-width:420px;">
          Destino:
          <?php if ($tipoSel === 'todos' || $carpetaSel === 'todas'): ?>
            <strong>Seleccioná primero un tipo y una carpeta.</strong>
          <?php else: ?>
            <strong><?= e($SOURCES[$tipoSel] ?? $tipoSel) ?></strong>
            /
            <strong><?= e($carpetaSel) ?></strong>
          <?php endif; ?>
        </div>
        <div>
          <input type="file" name="archivo" class="upload-input" <?= ($tipoSel==='todos' || $carpetaSel==='todas')?'disabled':'' ?>>
        </div>
        <div>
          <button type="submit" class="btn-upload" <?= ($tipoSel==='todos' || $carpetaSel==='todas')?'disabled':'' ?>>
            Subir archivo
          </button>
        </div>
      </form>

      <!-- Manual de usuario para el Excel A/B/C -->
      <?php if ($manualTipoLabel): ?>
        <div class="manual-box">
          <div class="manual-box-title">Manual para preparar el Excel de <?= e($manualTipoLabel) ?></div>
          <div class="manual-box-body">
            <p>
              Para que el sistema lea correctamente las observaciones, subí un archivo
              Excel (.xlsx) con la siguiente estructura mínima:
            </p>
            <ol>
              <li><strong>Columna A</strong>: Número de ítem (1, 2, 3, ...).</li>
              <li><strong>Columna B</strong>: Texto de la observación / hallazgo.</li>
              <li>
                <strong>Columna C</strong>: "BOTON FLOTANTE" =
                acción correctiva, referencia normativa u orientación para el
                cumplimiento.
              </li>
            </ol>
            <p>
              La primera fila va con los encabezados. Los datos comienzan en la fila 2.
              El sistema solo utiliza las columnas A, B y C. El resto de las columnas,
              si existen, se ignoran.
            </p>
            <p>
              Si la unidad usa un modelo en PDF para la misma lista, cargalo en la misma
              carpeta y con el mismo nombre base que el Excel
              (por ejemplo: <code>lista_S1.xlsx</code> y <code>lista_S1.pdf</code>)
              para que quede claramente asociado y se vea ordenado en los módulos que lo consultan.
            </p>
          </div>
        </div>
      <?php endif; ?>

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
              <th style="width:150px;">Acción</th>
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
                if ($r['tipo_slug'] === 'ultima_inspeccion')            $tagClass = 'tag-tipo--ultima';
                elseif ($r['tipo_slug'] === 'listas_control')          $tagClass = 'tag-tipo--listas';
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
                  <div class="btn-actions">
                    <?php if ($r['url']): ?>
                      <a class="btn-open" href="<?= e($r['url']) ?>" <?= ($extLower==='pdf' ? 'target="_blank"' : '') ?>>
                        Abrir
                      </a>
                    <?php else: ?>
                      <span class="text-muted" style="font-size:.78rem;">Sin visor</span>
                    <?php endif; ?>

                    <form method="post" style="display:inline;" onsubmit="return confirm('¿Eliminar el archivo \"<?= e($r['archivo']) ?>\"?');">
                      <input type="hidden" name="accion" value="delete">
                      <input type="hidden" name="rel" value="<?= e($r['rel']) ?>">
                      <button type="submit" class="btn-open btn-open-delete">
                        Eliminar
                      </button>
                    </form>
                  </div>
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
