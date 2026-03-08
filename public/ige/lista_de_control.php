<?php
// public/ige/lista_de_control.php — Listado por área S1–S5 en /storage/unidades/ecmilm/ige (progreso desde DB)
// Estilo unificado EC MIL M (igual a ige.php)

declare(strict_types=1);

require_once __DIR__ . '/../../auth/bootstrap.php';
require_login();

require_once __DIR__ . '/../../config/db.php';

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* ===== Helper: áreas del usuario desde roles_locales ===== */
function get_user_areas_lista(PDO $pdo): array {
  $user = function_exists('current_user') ? current_user() : null;
  if (!$user) return [];

  $dni = $user['dni'] ?? null;
  if (!$dni) return [];

  $st = $pdo->prepare("SELECT areas_acceso FROM roles_locales WHERE dni = ? LIMIT 1");
  $st->execute([$dni]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!$row || empty($row['areas_acceso'])) return [];

  $areas = json_decode((string)$row['areas_acceso'], true);
  return is_array($areas) ? $areas : [];
}

/* ===== Config ===== */
$AREAS   = ['S1','S2','S3','S4','S5'];
$ALIASES = [
  'S1'=>'Personal (S-1)',
  'S2'=>'Inteligencia (S-2)',
  'S3'=>'Operaciones (S-3)',
  'S4'=>'Material (S-4)',
  'S5'=>'Presupuesto (S-5)'
];

/* ✅ NUEVA RUTA REAL (según tu captura) */
const UNIT_SLUG     = 'ecmilm';
const BASE_IGE_REL  = 'storage/unidades/' . UNIT_SLUG . '/ige'; // => /ea/storage/unidades/ecmilm/ige

/* ÁREAS PERMITIDAS AL USUARIO */
$userAreas = get_user_areas_lista($pdo);

if (empty($userAreas) || in_array('GRAL', $userAreas, true)) {
  $ALLOWED_AREAS = $AREAS;
} else {
  $ALLOWED_AREAS = array_values(array_filter($AREAS, fn($a) => in_array($a, $userAreas, true)));
}

if (empty($ALLOWED_AREAS)) {
  http_response_code(403);
  echo "No tenés áreas asignadas para Listas de control.";
  exit;
}

/* Área seleccionada por GET */
$area = $_GET['area'] ?? $ALLOWED_AREAS[0];
if (!in_array($area, $AREAS, true)) $area = $ALLOWED_AREAS[0];
if (!in_array($area, $ALLOWED_AREAS, true)) $area = $ALLOWED_AREAS[0];

/* ===== Paths (FS) =====
// __DIR__ = C:\xampp\htdocs\ea\public\ige  ->  projectRoot = C:\xampp\htdocs\ea
$projectRoot = realpath(__DIR__ . '/../..'); // ✅ /ea
if (!$projectRoot) {
  $projectRoot = dirname(__DIR__, 2);
}
$projectRoot = rtrim(str_replace('\\','/', $projectRoot), '/');


/* ===== Paths (FS) =====
   __DIR__ = C:\xampp\htdocs\ea\public\ige
   projectRoot debe ser C:\xampp\htdocs\ea
*/
$projectRoot = realpath(__DIR__ . '/../..'); // ✅ /ea
if (!$projectRoot) {
  $projectRoot = dirname(__DIR__, 2);
}
$projectRoot = rtrim(str_replace('\\','/', $projectRoot), '/');

/* ✅ Base real: /ea/storage/unidades/ecmilm/ige (o /storage/unidades/... si está afuera) */
$baseCandidates = [
  $projectRoot . '/' . BASE_IGE_REL,          // /ea/storage/...
  dirname($projectRoot) . '/' . BASE_IGE_REL, // /storage/... (un nivel arriba)
];

$baseDir = false;
foreach ($baseCandidates as $cand) {
  $rp = realpath($cand);
  if ($rp && is_dir($rp)) { $baseDir = $rp; break; }
}

$root = $baseDir ? realpath($baseDir . '/' . $area) : false;


$baseDir = false;
foreach ($baseCandidates as $cand) {
  $rp = realpath($cand);
  if ($rp && is_dir($rp)) { $baseDir = $rp; break; }
}
$root    = $baseDir ? realpath($baseDir . '/' . $area) : false;

if (!$baseDir || !$root || strncmp($root, $baseDir, strlen($baseDir)) !== 0) {
  http_response_code(404);
  header('Content-Type:text/plain; charset=utf-8');
  echo "Carpeta no encontrada\n\n";
  echo "Esperado:\n";
  echo "  " . $projectRoot . "/" . BASE_IGE_REL . "/" . $area . "\n\n";
  echo "Debug:\n";
  echo "projectRoot = {$projectRoot}\n";
  echo "baseDir     = " . ($baseDir ?: 'FALSE') . "\n";
  echo "root        = " . ($root ?: 'FALSE') . "\n";
  exit;
}

/* ===== Recorrer XLSX del área (solo archivos .xlsx) ===== */
$rows = [];
$rii = new RecursiveIteratorIterator(
  new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
  RecursiveIteratorIterator::SELF_FIRST
);

foreach ($rii as $f) {
  if(!$f->isFile()) continue;
  if(strtolower($f->getExtension()) !== 'xlsx') continue;

  $abs = $f->getPathname();

  // ✅ rel para DB: "storage/unidades/ecmilm/ige/S1/.../archivo.xlsx"
  $rel = str_replace('\\','/', substr($abs, strlen($projectRoot) + 1));

  $sub = substr($abs, strlen($root) + 1);
  $loc = str_replace('\\','/', dirname($sub));
  if ($loc === '.' ) $loc = '';

  $rows[] = [
    'name'  => $f->getFilename(),
    'rel'   => $rel,
    'loc'   => ($loc ? $loc.'/' : ''),
    'mtime' => $f->getMTime(),
  ];
}
usort($rows, fn($a,$b)=>strcasecmp($a['loc'].$a['name'], $b['loc'].$b['name']));

/* ===== Progreso por archivo (desde DB checklist) ===== */
$stTot = $pdo->prepare("SELECT COUNT(*) FROM checklist WHERE file_rel = ?");
$stOk  = $pdo->prepare("SELECT COUNT(*) FROM checklist WHERE file_rel = ? AND estado='si'");
function pct_class($p){ return $p>=90?'ok':($p>=75?'warn':'bad'); }

/* ===== Assets (desde /ea/public/ige) ===== */
$ASSETS_URL = '../../assets';
$IMG_BG  = $ASSETS_URL . '/img/fondo.png';
$ESCUDO  = $ASSETS_URL . '/img/ecmilm.png';
$FAVICON = $ASSETS_URL . '/img/ecmilm.png';

$user = function_exists('current_user') ? current_user() : null;
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>IGE · Listas de control — <?= e($ALIASES[$area] ?? $area) ?> · EC MIL M</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= e($ASSETS_URL) ?>/css/theme-602.css">
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

  .page-bg{
    position:fixed; inset:0; z-index:-2; pointer-events:none;
    background:
      linear-gradient(160deg, rgba(0,0,0,.86) 0%, rgba(0,0,0,.68) 55%, rgba(0,0,0,.86) 100%),
      url("<?= e($IMG_BG) ?>") center/cover no-repeat;
    background-attachment: fixed, fixed;
    filter:saturate(1.05);
  }
  .container-main{ max-width: var(--container-max); margin: 0 auto; padding: 0 14px; }

  .brand-hero{ position:relative; padding:12px 0; }
  .hero-inner{
    max-width: var(--container-max);
    margin: 0 auto;
    padding: 0 14px;
    display:flex;
    align-items:center;
    gap:14px;
  }
  .brand-logo{ width:56px; height:56px; object-fit:contain; flex:0 0 auto; }
  .brand-title{ font-weight:900; letter-spacing:.4px; font-size:22px; line-height:1.15; }
  .brand-sub{ font-size:14px; opacity:.9; border-top:2px solid rgba(124,196,255,.25); display:inline-block; padding-top:4px; margin-top:2px; color:#cbd5f5; }
  .brand-kicker{ font-size:.85rem; color:#9aa4b2; margin-top:6px; }
  .userbox{ margin-left:auto; text-align:right; font-size:.9rem; }
  .userbox .meta{ color:#9aa4b2; }

  .tabs{ display:flex; gap:8px; flex-wrap:wrap; }
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

  .table-wrap{
    background: rgba(15,17,23,.86);
    border:1px solid rgba(255,255,255,.10);
    border-radius:16px;
    padding:8px;
    backdrop-filter: blur(8px);
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
    display:flex; justify-content:space-between; align-items:center;
    gap:10px; flex-wrap:wrap;
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
         onerror="this.onerror=null;this.src='<?= e($ASSETS_URL) ?>/img/EA.png';">
    <div>
      <div class="brand-title">Escuela Militar de Montaña</div>
      <div class="brand-sub">“La Montaña Nos Une”</div>
      <div class="brand-kicker">IGE · Listas de control — <?= e($ALIASES[$area] ?? $area) ?></div>
    </div>

    <?php if ($user): ?>
      <div class="userbox">
        <div><strong><?= e($user['rank'] ?? '') ?> <?= e($user['full_name'] ?? '') ?></strong></div>
        <?php if (!empty($user['unit'])): ?>
          <div class="meta"><?= e($user['unit']) ?></div>
        <?php endif; ?>
        <div class="mt-2">
          <a href="ige.php" class="btn btn-outline-light btn-sm" style="font-weight:900; margin-bottom:4px;">
            Volver a paneles
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
    <?php foreach($ALLOWED_AREAS as $a): ?>
      <a class="tab-btn <?= $area===$a?'active':'' ?>" href="?area=<?= e($a) ?>">
        <?= e($ALIASES[$a] ?? $a) ?>
      </a>
    <?php endforeach; ?>
  </div>

  <a class="btn-acc" href="ige.php?scope=lista_de_control">Volver al dashboard</a>
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
          $grp = null;
          foreach($rows as $r):
            if($r['loc'] !== $grp){
              $grp = $r['loc'];
              echo '<tr class="group-row"><td colspan="5">'.e($grp===''?'(Raíz)/':$grp).'</td></tr>';
            }

            $fileRel = $r['rel'];
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
            <td><a class="btn-ed" href="ver_tabla.php?<?= $qs ?>">Editar</a></td>
          </tr>
        <?php endforeach; if(empty($rows)): ?>
          <tr><td colspan="5" class="text-muted">No se encontraron archivos XLSX en esta área.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

</body>
</html>
