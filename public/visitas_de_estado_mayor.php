<?php
// public/visitas_de_estado_mayor.php — Listado de XLSX dentro de /storage/visitas_de_estado_mayor

require_once __DIR__ . '/../auth/bootstrap.php';
require_login();

require_once __DIR__ . '/../config/db.php';

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* ===== Helpers de áreas ===== */
function get_user_areas_visitas(PDO $pdo): array {
    $user = function_exists('current_user') ? current_user() : null;
    if (!$user) return [];

    $dni = $user['dni'] ?? null;
    if (!$dni) return [];

    $st = $pdo->prepare("SELECT areas_acceso FROM roles_locales WHERE dni = ? LIMIT 1");
    $st->execute([$dni]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row || empty($row['areas_acceso'])) return [];

    $areas = json_decode($row['areas_acceso'], true);
    return is_array($areas) ? $areas : [];
}

/**
 * Devuelve un "código" de área según el nombre de la carpeta:
 * - "Aspectos Generales"  => GRAL
 * - algo con "(S-1)"      => S1
 * - algo con "(S-2)"      => S2
 * - algo con "(S-3)"      => S3
 * - algo con "(S-4)"      => S4
 * - nada de eso           => null
 */
function folder_code_from_name(string $name): ?string {
    $low = mb_strtolower($name, 'UTF-8');

    if (strpos($low, 'aspectos generales') !== false) {
        return 'GRAL';
    }

    if (preg_match('/\(S-(\d+)\)/', $name, $m)) {
        $num = (int)$m[1];
        if ($num >= 1 && $num <= 4) {
            return 'S' . $num;
        }
    }
    return null;
}

/**
 * Decide si el usuario puede ver una carpeta según su código
 * y los códigos de áreas cargados en roles_locales.
 */
function user_can_see_folder(?string $code, array $userAreasRaw): bool {
    // Sin configuración => asumimos GRAL (acceso total)
    if (empty($userAreasRaw)) {
        return true;
    }

    // Si tiene GRAL siempre ve todo
    if (in_array('GRAL', $userAreasRaw, true)) {
        return true;
    }

    // Para "Aspectos Generales"
    if ($code === 'GRAL') {
        // La regla pedida: S3 también ve Aspectos Generales
        return in_array('S3', $userAreasRaw, true);
    }

    // Para carpetas S1..S4
    if ($code === 'S1' || $code === 'S2' || $code === 'S3' || $code === 'S4') {
        return in_array($code, $userAreasRaw, true);
    }

    // Carpetas raras / sin código solo para GRAL, y GRAL ya lo manejamos arriba
    return false;
}

/* ===== Config ===== */
const BASE_PREFIX = 'visitas_de_estado_mayor';

$projectBase = realpath(__DIR__ . '/..');
$baseDir     = realpath($projectBase . '/storage/' . BASE_PREFIX);
if(!$baseDir){ http_response_code(404); echo "No existe /storage/".BASE_PREFIX; exit; }

/* Áreas del usuario */
$userAreasRaw = get_user_areas_visitas($pdo);

/* Subcarpeta opcional (tabs) */
$sub = $_GET['sub'] ?? '';
$sub = trim(str_replace(['..','\\'], ['','/'], $sub), '/');

$root = $baseDir;
if ($sub !== '') {
  $try = realpath($baseDir . '/' . $sub);
  if ($try && str_starts_with($try, $baseDir)) $root = $try;
}

/* Tabs (primer nivel) ORDENADAS POR S-1, S-2, S-3, S-4,
   y filtradas según las áreas permitidas */
$tabs = ['' => '(Todas)'];

$dirsS     = []; // con patrón (S-x)
$dirsOther = []; // cualquier otra carpeta

foreach (new DirectoryIterator($baseDir) as $entry){
  if ($entry->isDir() && !$entry->isDot()) {
    $name = $entry->getFilename();
    $code = folder_code_from_name($name);

    // Si el usuario no puede ver esta carpeta, la salteamos
    if (!user_can_see_folder($code, $userAreasRaw)) {
        continue;
    }

    if (preg_match('/\(S-(\d+)\)/', $name, $m)) {
      $dirsS[] = ['name' => $name, 'snum' => (int)$m[1]];
    } else {
      $dirsOther[] = $name;
    }
  }
}

// Ordenar primero las que tienen (S-x) por número
usort($dirsS, function($a, $b){
  return $a['snum'] <=> $b['snum'];
});

// El resto alfabético
sort($dirsOther, SORT_NATURAL | SORT_FLAG_CASE);

// Armar $tabs: (Todas) + S-1..S-n + otras
foreach ($dirsS as $d) {
  $tabs[$d['name']] = $d['name'];
}
foreach ($dirsOther as $d) {
  $tabs[$d] = $d;
}

/* Si el usuario pidió un tab que no está permitido, lo mando a "(Todas)" */
if ($sub !== '' && !array_key_exists($sub, $tabs)) {
    $sub = '';
    $root = $baseDir;
}

/* ===== Recorrer archivos XLSX ===== */
$rows=[];
$rii = new RecursiveIteratorIterator(
  new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
  RecursiveIteratorIterator::SELF_FIRST
);
foreach($rii as $f){
  if(!$f->isFile()) continue;
  if(strtolower($f->getExtension())!=='xlsx') continue;

  $abs = $f->getPathname();

  // Determinar carpeta top-level respecto de /storage/visitas_de_estado_mayor
  $relFromBase = substr($abs, strlen($baseDir)+1);
  $relFromBase = str_replace('\\','/',$relFromBase);
  $parts = explode('/', $relFromBase);
  $topFolder = $parts[0] ?? '';

  $codeTop = folder_code_from_name($topFolder);
  if (!user_can_see_folder($codeTop, $userAreasRaw)) {
      // El usuario no debería ver este archivo
      continue;
  }

  $rel = str_replace('\\','/', substr($abs, strlen($projectBase)+1));

  $subPath = str_replace('\\','/', substr($abs, strlen($root)+1));
  $loc = dirname($subPath);
  if($loc === '.' || $loc === DIRECTORY_SEPARATOR) $loc = '';

  $rows[] = [
    'name'=>$f->getFilename(),
    'rel'=>$rel,
    'loc'=>$loc ? $loc.'/' : '',
    'mtime'=>$f->getMTime(),
  ];
}
usort($rows, fn($a,$b)=>strcasecmp($a['loc'].$a['name'], $b['loc'].$b['name']));

/* ===== Progreso (DB checklist) ===== */
$stTot = $pdo->prepare("SELECT COUNT(*) FROM checklist WHERE file_rel = ?");
$stOk  = $pdo->prepare("SELECT COUNT(*) FROM checklist WHERE file_rel = ? AND estado='si'");
function pct_class($p){ return $p>=90?'ok':($p>=75?'warn':'bad'); }

/* ===== Assets ===== */
$PUBLIC_URL = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'])), '/');
$APP_URL    = rtrim(str_replace('\\','/', dirname($PUBLIC_URL)), '/');
$ASSETS_URL = ($APP_URL === '' ? '' : $APP_URL) . '/assets';
$IMG_BG     = $ASSETS_URL . '/img/fondo.png';
$ESCUDO     = $ASSETS_URL . '/img/escudo_bcom602.png';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Visitas de Estado Mayor - IGE</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/theme-602.css">
<link rel="icon" type="image/png" href="../assets/img/bcom602.png">
<style>
  body{
    background: url("<?= e($IMG_BG) ?>") no-repeat center center fixed;
    background-size: cover; background-attachment: fixed;
    background-color:#0f1117; color:#e9eef5;
    font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,sans-serif;
  }
  .page-wrap{padding:20px;}
  .table-wrap{
    background:rgba(15,17,23,.88);
    border:1px solid rgba(255,255,255,.12);
    border-radius:16px;
    padding:8px;
    backdrop-filter: blur(6px);
  }
  table{width:100%;border-collapse:collapse;}
  th,td{padding:10px;border-bottom:1px solid rgba(255,255,255,.12);vertical-align:middle;}
  thead th{background:#11151d;text-transform:uppercase;letter-spacing:.04em;font-weight:800;position:sticky;top:0;z-index:2;}
  .group-row{background:#0f2018;font-weight:700;}
  .cell-trunc{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:420px;}

  .prog{display:flex;align-items:center;gap:.6rem;}
  .track{flex:1;height:12px;background:#1b222c;border-radius:999px;overflow:hidden;border:1px solid #2a3140;}
  .fill{height:100%;background:linear-gradient(90deg,#1cd259,#15a34a);}
  .b-pill{padding:.25rem .55rem;border-radius:999px;font-weight:800;font-size:.8rem;border:1px solid;}
  .b-ok{background:#052e1b;color:#22c55e;border-color:#14532d;}
  .b-warn{background:#2a1a00;color:#fbbf24;border-color:#b45309;}
  .b-bad{background:#2a1113;color:#fca5a5;border-color:#7f1d1d;}
</style>
</head>
<body>

<header class="brand-hero">
  <div class="hero-inner container-fluid">
    <img class="brand-logo" src="<?= e($ESCUDO) ?>" alt="Escudo 602">
    <div>
      <div class="brand-title">Batallón de Comunicaciones 602</div>
      <div class="brand-sub">“Hogar de las Comunicaciones Fijas del Ejército”</div>
    </div>
  </div>
</header>

<div class="page-wrap container-fluid">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="tabs">
      <?php foreach($tabs as $k=>$label): ?>
        <a class="btn btn-outline-light <?= $sub===$k?'active':'' ?>"
           href="?sub=<?= e(urlencode($k)) ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </div>
    <a class="btn btn-success" href="index.php?scope=visitas_de_estado_mayor">Volver</a>
  </div>

  <div class="table-wrap shadow-lg">
    <div style="overflow:auto;max-height:80vh;">
      <table>
        <thead>
          <tr>
            <th>Archivo</th>
            <th>Ubicación</th>
            <th style="width:220px">Progreso</th>
            <th style="width:180px">Modificado</th>
            <th style="width:100px">Acción</th>
          </tr>
        </thead>
        <tbody>
<?php
$grp=null;
foreach($rows as $r):
  if($r['loc'] !== $grp):
    $grp=$r['loc'];
    echo '<tr class="group-row"><td colspan="5">'.e($grp===''?'(Raíz)/':$grp).'</td></tr>';
  endif;

  $fileRel=$r['rel'];
  $stTot->execute([$fileRel]); $tot=(int)$stTot->fetchColumn();
  $stOk->execute([$fileRel]);  $ok =(int)$stOk->fetchColumn();

  $pct=$tot?round($ok*100.0/$tot,1):0;
  $cls=pct_class($pct);
  $badge = $cls==='ok'?'b-ok':($cls==='warn'?'b-warn':'b-bad');
  $qs='p='.rawurlencode($r['rel']);
?>
<tr>
  <td class="cell-trunc"><?= e($r['name']) ?></td>
  <td><?= e($r['loc']) ?></td>
  <td>
    <div class="prog">
      <div class="track"><div class="fill" style="width:<?= $pct ?>%"></div></div>
      <span class="b-pill <?= $badge ?>"><?= $pct ?>%</span>
    </div>
  </td>
  <td><?= e(date('d/m/Y H:i',$r['mtime'])) ?></td>
  <td><a class="btn btn-info btn-sm" href="ver_tabla.php?<?= $qs ?>">Editar</a></td>
</tr>
<?php endforeach; ?>
<?php if(empty($rows)): ?>
<tr><td colspan="5" class="text-muted">No hay archivos XLSX en esta carpeta.</td></tr>
<?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

</body>
</html>
