<?php
// public/ultima_inspeccion.php — Listado de XLSX dentro de /storage/ultima_inspeccion
// Estilo unificado EC MIL M (igual a lista_de_control.php y ige.php)

require_once __DIR__ . '/../auth/bootstrap.php';
require_login();

require_once __DIR__ . '/../config/db.php';

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* ===== Helpers de áreas ===== */

// devuelve ['S1','S3','GRAL'] según la tabla roles_locales. Si no hay dato, sin restricciones.
function get_user_areas(PDO $pdo): array {
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
 * Indica si un usuario con ciertas áreas puede ver la carpeta de primer nivel
 */
function can_user_see_folder(string $folderName, array $areas): bool {
    $folderName = trim($folderName);

    // sin configuración: no restringimos
    if (empty($areas)) return true;

    // GRAL ve todo
    if (in_array('GRAL', $areas, true)) return true;

    // Aspectos Generales visible para cualquiera que tenga alguna Sx
    if ($folderName === 'Aspectos Generales') {
        foreach (['S1','S2','S3','S4'] as $sx) {
            if (in_array($sx, $areas, true)) return true;
        }
        return false;
    }

    $code = null;
    if ($folderName === 'Personal (S-1)')         $code = 'S1';
    elseif ($folderName === 'Inteligencia (S-2)') $code = 'S2';
    elseif ($folderName === 'Operaciones (S-3)')  $code = 'S3';
    elseif ($folderName === 'Materiales (S-4)')   $code = 'S4';

    if ($code === null) return false;
    return in_array($code, $areas, true);
}

/* ===== Config ===== */
const BASE_PREFIX     = 'ultima_inspeccion';
const STORAGE_PREFIX  = 'storage/' . BASE_PREFIX . '/';

$projectBase = realpath(__DIR__ . '/..');
$baseDir     = realpath($projectBase . '/storage/' . BASE_PREFIX);
if(!$baseDir){ http_response_code(404); echo "No existe /storage/".BASE_PREFIX; exit; }

/* Áreas del usuario */
$userAreas = get_user_areas($pdo);

/* Subcarpeta (primer nivel) opcional para tabs */
$sub = $_GET['sub'] ?? '';
$sub = trim(str_replace(['..','\\'], ['','/'], (string)$sub), '/');

/* ===== Tabs: todas las subcarpetas de primer nivel ===== */
$tabsRaw = [];
$it = new DirectoryIterator($baseDir);
foreach ($it as $entry) {
  if($entry->isDot()) continue;
  if($entry->isDir()){
    $name = $entry->getFilename();
    $tabsRaw[$name] = $name;
  }
}

$priority = [
  'Aspectos Generales'  => 1,
  'Personal (S-1)'      => 2,
  'Inteligencia (S-2)'  => 3,
  'Operaciones (S-3)'   => 4,
  'Materiales (S-4)'    => 999,
];

uksort($tabsRaw, function(string $a, string $b) use ($priority){
  $pa = $priority[$a] ?? 50;
  $pb = $priority[$b] ?? 50;
  if ($pa === $pb) return strcasecmp($a, $b);
  return $pa <=> $pb;
});

/* Filtrado de tabs según áreas del usuario */
$tabs = ['' => '(Todos)'];
foreach ($tabsRaw as $name => $label) {
    if (can_user_see_folder($name, $userAreas)) $tabs[$name] = $label;
}

/* Validar sub: si no tiene acceso => (Todos) */
if ($sub !== '' && !array_key_exists($sub, $tabs)) $sub = '';

/* Root efectivo según sub permitido */
$root = $baseDir;
if ($sub !== '') {
  $try = realpath($baseDir . '/' . $sub);
  if ($try && str_starts_with($try, $baseDir)) $root = $try;
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
  $rel = str_replace('\\','/', substr($abs, strlen($projectBase)+1));

  // detectar carpeta de primer nivel para aplicar permisos también a nivel archivo
  $topFolder = '';
  if (str_starts_with($rel, STORAGE_PREFIX)) {
      $relFromStorage = substr($rel, strlen(STORAGE_PREFIX));
      $parts = explode('/', $relFromStorage, 2);
      $topFolder = $parts[0] ?? '';
  }
  if ($topFolder !== '' && !can_user_see_folder($topFolder, $userAreas)) continue;

  $subPath = str_replace('\\','/', substr($abs, strlen($root)+1));
  $loc = dirname($subPath);
  if($loc === '.' || $loc === DIRECTORY_SEPARATOR) $loc = '';

  $rows[] = [
    'name'=>$f->getFilename(),
    'rel'=>$rel,
    'loc'=>($sub ? $sub.'/' : '').($loc ? $loc.'/' : ''),
    'mtime'=>$f->getMTime(),
  ];
}
usort($rows, fn($a,$b)=>strcasecmp($a['loc'].$a['name'], $b['loc'].$b['name']));

/* ===== Progreso por archivo (DB checklist) ===== */
$stTot = $pdo->prepare("SELECT COUNT(*) FROM checklist WHERE file_rel = ?");
$stOk  = $pdo->prepare("SELECT COUNT(*) FROM checklist WHERE file_rel = ? AND estado='si'");
function pct_class($p){ return $p>=90?'ok':($p>=75?'warn':'bad'); }

/* ===== Assets (EC MIL M) ===== */
$PUBLIC_URL = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'])), '/'); // /ea/public
$APP_URL    = rtrim(str_replace('\\','/', dirname($PUBLIC_URL)), '/'); // /ea
$ASSETS_URL = ($APP_URL === '' ? '' : $APP_URL) . '/assets';

$IMG_BG  = $ASSETS_URL . '/img/fondo.png';
$ESCUDO  = $ASSETS_URL . '/img/ecmilm.png';
$FAVICON = $ASSETS_URL . '/img/ecmilm.png';

$user = function_exists('current_user') ? current_user() : null;
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>IGE · Última inspección · EC MIL M</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/theme-602.css">
<link rel="icon" href="<?= e($FAVICON) ?>">

<style>
  :root{
    --container-max: 1280px;
    --ok:#16a34a; --warn:#f59e0b; --bad:#ef4444; --mut:#9aa4b2;
  }

  html,body{ height:100%; }
  body{
    margin:0;
    color:#e5e7eb;
    background:#000;
    font-family:system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,sans-serif;
  }

  /* Fondo principal oscurecido (igual a ige.php / lista_de_control.php) */
  .page-bg{
    position:fixed;
    inset:0;
    z-index:-2;
    pointer-events:none;
    background:
      linear-gradient(160deg, rgba(0,0,0,.86) 0%, rgba(0,0,0,.68) 55%, rgba(0,0,0,.86) 100%),
      url("<?= e($IMG_BG) ?>") center/cover no-repeat;
    background-attachment: fixed, fixed;
    filter:saturate(1.05);
  }
  .page-bg::before{
    content:"";
    position:absolute;
    inset:0;
    z-index:-1;
    opacity:.18;
    background-image:
      radial-gradient(1.4px 1.4px at 18% 22%, #9cd1ff 20%, transparent 60%),
      radial-gradient(1.2px 1.2px at 63% 48%, #b7ddff 20%, transparent 60%),
      radial-gradient(1.2px 1.2px at 82% 70%, #b7ddff 20%, transparent 60%),
      radial-gradient(1.6px 1.6px at 34% 76%, #cbe8ff 20%, transparent 60%),
      radial-gradient(1.1px 1.1px at 72% 16%, #a7d6ff 20%, transparent 60%);
    background-repeat:no-repeat;
    background-size: 1200px 800px, 1400px 900px, 1100px 900px, 1400px 1000px, 1300px 800px;
    background-position: 0 0, 30% 40%, 80% 60%, 10% 90%, 70% 10%;
  }

  .container-main{
    max-width: var(--container-max);
    margin: 0 auto;
    padding: 0 14px;
  }

  /* Header EC MIL M */
  .brand-hero{ position:relative; padding:12px 0; }
  .hero-inner{
    max-width: var(--container-max);
    margin: 0 auto;
    padding: 0 14px;
    display:flex;
    align-items:center;
    gap:14px;
  }
  .brand-logo{
    width:56px; height:56px;
    object-fit:contain;
    flex:0 0 auto;
    filter:drop-shadow(0 2px 10px rgba(124,196,255,.25));
  }
  .brand-title{
    font-weight:900;
    letter-spacing:.4px;
    font-size:22px;
    line-height:1.15;
    text-shadow:0 2px 16px rgba(30,123,220,.35);
  }
  .brand-sub{
    font-size:14px;
    opacity:.9;
    border-top:2px solid rgba(124,196,255,.25);
    display:inline-block;
    padding-top:4px;
    margin-top:2px;
    color:#cbd5f5;
  }
  .brand-kicker{
    font-size:.85rem;
    color:#9aa4b2;
    margin-top:6px;
  }

  .userbox{
    margin-left:auto;
    text-align:right;
    font-size:.9rem;
  }
  .userbox .meta{ color:#9aa4b2; }

  /* Tabs */
  .tabs{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
  }
  .tab-btn{
    border:1px solid rgba(255,255,255,.18);
    background:rgba(15,17,23,.55);
    color:#e9eef5;
    padding:.45rem .8rem;
    border-radius:12px;
    font-weight:900;
    font-size:.9rem;
    text-decoration:none;
  }
  .tab-btn:hover{ background:rgba(255,255,255,.10); }
  .tab-btn.active{ background:#16a34a; border-color:#16a34a; color:#08140c; }

  /* Tabla */
  .table-wrap{
    background: rgba(15,17,23,.86);
    border:1px solid rgba(255,255,255,.10);
    border-radius:16px;
    padding:8px;
    backdrop-filter: blur(8px);
    box-shadow:0 10px 24px rgba(0,0,0,.35), inset 0 1px 0 rgba(255,255,255,.05);
  }
  table{width:100%;border-collapse:collapse;}
  th,td{padding:10px;border-bottom:1px solid rgba(255,255,255,.10);vertical-align:middle;}
  thead th{
    background:#0e1116;
    text-transform:uppercase;
    letter-spacing:.04em;
    font-weight:900;
    position:sticky;
    top:0;
    z-index:2;
  }
  .group-row{background:rgba(22,163,74,.12); font-weight:800;}
  .cell-trunc{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:520px;}

  .prog{display:flex;align-items:center;gap:.6rem;}
  .track{flex:1;height:12px;background:#1b222c;border-radius:999px;overflow:hidden;border:1px solid #2a3140;}
  .fill{height:100%;background:linear-gradient(90deg,#1cd259,#15a34a);}
  .b-pill{padding:.25rem .55rem;border-radius:999px;font-weight:900;font-size:.8rem;border:1px solid;}
  .b-ok{background:#052e1b;color:#22c55e;border-color:#14532d;}
  .b-warn{background:#2a1a00;color:#fbbf24;border-color:#b45309;}
  .b-bad{background:#2a1113;color:#fca5a5;border-color:#7f1d1d;}
  .pct{width:62px;text-align:right;color:#bfe8cb;font-weight:900;}

  .btn-ed{
    background:#0ea5e9;border:none;color:#001018;font-weight:900;
    border-radius:10px;padding:.32rem .56rem;text-decoration:none; display:inline-block;
  }
  .btn-ed:hover{background:#38bdf8;}
  .btn-acc{
    background:#16a34a;border:none;color:#fff;border-radius:12px;font-weight:900;padding:.45rem .9rem;
    text-decoration:none; display:inline-block;
  }
  .btn-acc:hover{ background:#22c55e; }

  .top-actions{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:10px;
    flex-wrap:wrap;
    margin: 14px auto 12px;
    max-width: var(--container-max);
    padding: 0 14px;
  }
</style>
</head>

<body>
<div class="page-bg"></div>

<header class="brand-hero">
  <div class="hero-inner">
    <img class="brand-logo" src="<?= e($ESCUDO) ?>" alt="EC MIL M"
         onerror="this.onerror=null;this.src='../assets/img/EA.png';">
    <div>
      <div class="brand-title">Escuela Militar de Montaña</div>
      <div class="brand-sub">“La Montaña Nos Une”</div>
      <div class="brand-kicker">IGE · Última inspección<?= ($sub!=='' ? ' — '.e($sub) : '') ?></div>
    </div>

    <?php if ($user): ?>
      <div class="userbox">
        <div><strong><?= e($user['rank'] ?? '') ?> <?= e($user['full_name'] ?? '') ?></strong></div>
        <?php if (!empty($user['unit'])): ?>
          <div class="meta"><?= e($user['unit']) ?></div>
        <?php endif; ?>
        <div class="mt-2">
          <a href="elegir_inicio.php" class="btn btn-outline-light btn-sm" style="font-weight:900; margin-bottom:4px;">
            Volver a inicio
          </a>
        </div>
        <div>
          <a href="../logout.php" class="btn btn-success btn-sm" style="font-weight:900;">
            Cerrar sesión
          </a>
        </div>
      </div>
    <?php endif; ?>
  </div>
</header>

<div class="top-actions">
  <div class="tabs">
    <?php foreach($tabs as $k=>$label): ?>
      <a class="tab-btn <?= ($sub===$k ? 'active' : '') ?>"
         href="?sub=<?= e(rawurlencode((string)$k)) ?>">
        <?= e($label) ?>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- ✅ Dashboard actualizado a ige.php -->
  <a class="btn-acc" href="ige.php?scope=ultima_inspeccion">Volver al dashboard</a>
</div>

<div class="container-main">
  <div class="table-wrap">
    <div style="overflow:auto;max-height:80vh;">
      <table>
        <thead>
          <tr>
            <th>ARCHIVO</th>
            <th>UBICACIÓN</th>
            <th style="width:240px">PROGRESO</th>
            <th style="width:170px">MODIFICADO</th>
            <th style="width:130px">ACCIÓN</th>
          </tr>
        </thead>
        <tbody>
        <?php
          $grp=null;
          foreach($rows as $r):
            if($r['loc']!==$grp){
              $grp=$r['loc'];
              echo '<tr class="group-row"><td colspan="5">'.e($grp===''?'(Raíz)/':$grp).'</td></tr>';
            }

            $fileRel=$r['rel'];
            $stTot->execute([$fileRel]); $tot=(int)$stTot->fetchColumn();
            $stOk->execute([$fileRel]);  $ok =(int)$stOk->fetchColumn();
            $pct=$tot?round($ok*100.0/$tot,1):0;

            $cls=pct_class($pct);
            $badge = $cls==='ok'?'b-ok':($cls==='warn'?'b-warn':'b-bad');

            $qs='p='.rawurlencode($r['rel']);
        ?>
          <tr>
            <td class="cell-trunc" title="<?= e($r['name']) ?>"><?= e($r['name']) ?></td>
            <td class="cell-trunc"><?= e($r['loc']) ?></td>
            <td>
              <div class="prog">
                <div class="track"><div class="fill" style="width:<?= (float)$pct ?>%"></div></div>
                <span class="b-pill <?= e($badge) ?> pct"><?= (float)$pct ?>%</span>
              </div>
            </td>
            <td><?= e(date('d/m/Y H:i',$r['mtime'])) ?></td>
            <td>
              <a class="btn-ed" href="ver_tabla.php?<?= $qs ?>">Editar</a>
            </td>
          </tr>
        <?php endforeach; if(empty($rows)): ?>
          <tr><td colspan="5" class="text-muted">No se encontraron archivos XLSX.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

</body>
</html>
