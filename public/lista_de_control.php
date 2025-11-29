<?php
// public/lista_de_control.php — Listado por área S1–S4 en /storage/listas_control (progreso desde DB)

require_once __DIR__ . '/../auth/bootstrap.php';
require_login();

require_once __DIR__ . '/../config/db.php';

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

    $areas = json_decode($row['areas_acceso'], true);
    return is_array($areas) ? $areas : [];
}

/* ===== Config ===== */
const BASE_PREFIX = 'listas_control';
$AREAS   = ['S1','S2','S3','S4','S5'];
$ALIASES = [
    'S1'=>'Personal (S-1)',
    'S2'=>'Inteligencia (S-2)',
    'S3'=>'Operaciones (S-3)',
    'S4'=>'Material (S-4)',
    'S5'=>'Presupuesto (S-5)'
];

/* ÁREAS PERMITIDAS AL USUARIO */
$userAreas = get_user_areas_lista($pdo);

if (empty($userAreas) || in_array('GRAL', $userAreas, true)) {
    // Sin configuración o con GRAL: ve todas las áreas
    $ALLOWED_AREAS = $AREAS;
} else {
    // Solo las Sx que tenga el usuario
    $ALLOWED_AREAS = array_values(array_filter($AREAS, fn($a) => in_array($a, $userAreas, true)));
}

// Si no tiene ninguna área asignada, mostramos mensaje sencillo
if (empty($ALLOWED_AREAS)) {
    http_response_code(403);
    echo "No tenés áreas asignadas para Listas de control.";
    exit;
}

/* Área seleccionada por GET, forzada a las permitidas */
$area = $_GET['area'] ?? $ALLOWED_AREAS[0];
if (!in_array($area, $AREAS, true)) {
    $area = $ALLOWED_AREAS[0];
}
if (!in_array($area, $ALLOWED_AREAS, true)) {
    // si pidió algo que no le corresponde, lo mando a la primera permitida
    $area = $ALLOWED_AREAS[0];
}

/* ===== Paths ===== */
$projectBase = realpath(__DIR__ . '/..');
$baseDir     = realpath($projectBase . '/storage/' . BASE_PREFIX);
$root        = $baseDir ? realpath($baseDir . '/' . $area) : false;
if(!$baseDir || !$root || strncmp($root,$baseDir,strlen($baseDir))!==0){
  http_response_code(404); echo "Carpeta no encontrada"; exit;
}

/* ===== Recorrer XLSX del área (solo archivos .xlsx) ===== */
$rows=[];
$rii = new RecursiveIteratorIterator(
  new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
  RecursiveIteratorIterator::SELF_FIRST
);
foreach($rii as $f){
  if(!$f->isFile()) continue;
  if(strtolower($f->getExtension())!=='xlsx') continue;
  $abs = $f->getPathname();
  $rel = str_replace('\\','/', substr($abs, strlen($projectBase)+1)); // ruta relativa al proyecto
  $sub = substr($abs, strlen($root)+1);
  $loc = str_replace('\\','/', dirname($sub));
  if($loc === '.' ) $loc='';
  $rows[] = [
    'name'=>$f->getFilename(),
    'rel'=>$rel,
    'loc'=>($loc? $loc.'/' : ''),
    'mtime'=>$f->getMTime(),
  ];
}
usort($rows, fn($a,$b)=>strcasecmp($a['loc'].$a['name'], $b['loc'].$b['name']));

/* ===== Progreso por archivo (desde DB checklist) ===== */
$stTot = $pdo->prepare("SELECT COUNT(*) FROM checklist WHERE file_rel = ?");
$stOk  = $pdo->prepare("SELECT COUNT(*) FROM checklist WHERE file_rel = ? AND estado='si'");
function pct_class($p){ return $p>=90?'ok':($p>=75?'warn':'bad'); }

/* ===== Assets (coherentes con index) ===== */
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
<title>Batallón de Comunicaciones 602 – Documentos Área <?= e($ALIASES[$area] ?? $area) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/theme-602.css">
<link rel="icon" type="image/png" href="../assets/img/bcom602.png">
<style>
  body{
    background: url("<?= e($IMG_BG) ?>") no-repeat center center fixed;
    background-size: cover; background-attachment: fixed;
    background-color:#0f1117; color:#e9eef5; margin:0; padding:0;
    font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,sans-serif;
  }
  .page-wrap{padding:20px;}
  .container-fluid{max-width:1600px;margin:auto;}
  .table-wrap{
    background:rgba(15,17,23,.88); border:1px solid rgba(255,255,255,.12);
    border-radius:16px; padding:8px; backdrop-filter: blur(6px);
    box-shadow:0 10px 24px rgba(0,0,0,.35), inset 0 1px 0 rgba(255,255,255,.05);
  }
  table{width:100%;border-collapse:collapse;}
  th,td{padding:10px;border-bottom:1px solid rgba(255,255,255,.12);vertical-align:middle;}
  thead th{background:#11151d;text-transform:uppercase;letter-spacing:.04em;font-weight:800;position:sticky;top:0;z-index:2;}
  .group-row{background:#0f2018;font-weight:700;}
  .cell-trunc{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:520px;}

  .prog{display:flex;align-items:center;gap:.6rem;}
  .track{flex:1;height:12px;background:#1b222c;border-radius:999px;overflow:hidden;border:1px solid #2a3140;}
  .fill{height:100%;background:linear-gradient(90deg,#1cd259,#15a34a);}
  .b-pill{padding:.25rem .55rem;border-radius:999px;font-weight:800;font-size:.8rem;border:1px solid;}
  .b-ok{background:#052e1b;color:#22c55e;border-color:#14532d;}
  .b-warn{background:#2a1a00;color:#fbbf24;border-color:#b45309;}
  .b-bad{background:#2a1113;color:#fca5a5;border-color:#7f1d1d;}
  .pct{width:62px;text-align:right;color:#bfe8cb;font-weight:800;}

  .btn-ed{background:#0ea5e9;border:none;color:#001018;font-weight:800;border-radius:10px;padding:.32rem .56rem;}
  .btn-ed:hover{background:#38bdf8;}
  .btn-acc{background:#16a34a;border:none;color:#fff;border-radius:12px;font-weight:800;padding:.45rem .9rem;}
  .btn-acc-sm{font-size:.85rem;padding:.32rem .56rem;border-radius:10px;}

  .tabs .btn{border-radius:10px;margin-right:.4rem;}
  .tabs .btn.active{background:#16a34a;border-color:#16a34a;}
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
        <?php foreach($ALLOWED_AREAS as $a): ?>
          <a class="btn btn-outline-light <?= $area===$a?'active':'' ?>" href="?area=<?= e($a) ?>"><?= e($ALIASES[$a] ?? $a) ?></a>
        <?php endforeach; ?>
      </div>
      <a class="btn btn-acc btn-acc-sm" href="index.php?scope=lista_de_control">Volver al dashboard</a>
    </div>

    <div class="table-wrap shadow-lg">
      <div style="overflow:auto;max-height:80vh;">
        <table>
          <thead>
            <tr>
              <th>ARCHIVO</th>
              <th>UBICACIÓN</th>
              <th style="width:220px">PROGRESO</th>
              <th style="width:160px">MODIFICADO</th>
              <th style="width:130px">ACCIÓN</th>
            </tr>
          </thead>
          <tbody>
          <?php
            $grp=null;
            foreach($rows as $r):
              if($r['loc']!==$grp){
                $grp=$r['loc'];
                echo '<tr class="group-row"><td colspan="7">'.e($grp===''?'(Raíz)/':$grp).'</td></tr>';
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
                  <div class="track"><div class="fill" style="width:<?= $pct ?>%"></div></div>
                  <span class="b-pill <?= $badge ?> pct"><?= $pct ?>%</span>
                </div>
              </td>
              <td><?= e(date('d/m/Y H:i',$r['mtime'])) ?></td>
              <td class="d-flex gap-2">
                <a class="btn btn-ed btn-acc-sm" href="ver_tabla.php?<?= $qs ?>">Editar</a>
              </td>
            </tr>
          <?php endforeach; if(empty($rows)): ?>
            <tr><td colspan="7" class="text-muted">No se encontraron archivos XLSX en esta área.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</body>
</html>
