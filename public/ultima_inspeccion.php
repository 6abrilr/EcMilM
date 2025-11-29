<?php
// public/ultima_inspeccion.php — Listado de XLSX dentro de /storage/ultima_inspeccion

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
 * (por ejemplo: "Aspectos Generales", "Personal (S-1)", etc).
 *
 * Reglas:
 * - Si no hay áreas configuradas -> sin restricciones.
 * - Si tiene GRAL -> ve todo.
 * - "Aspectos Generales" -> lo ve cualquiera que tenga S1/S2/S3/S4 o GRAL.
 * - S1 -> "Personal (S-1)"
 * - S2 -> "Inteligencia (S-2)"
 * - S3 -> "Operaciones (S-3)"
 * - S4 -> "Materiales (S-4)"
 * - Cualquier otra carpeta -> solo GRAL.
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
    if ($folderName === 'Personal (S-1)')      $code = 'S1';
    elseif ($folderName === 'Inteligencia (S-2)') $code = 'S2';
    elseif ($folderName === 'Operaciones (S-3)')  $code = 'S3';
    elseif ($folderName === 'Materiales (S-4)')   $code = 'S4';

    if ($code === null) {
        // carpeta rara: solo GRAL, pero ya lo chequeamos arriba
        return false;
    }

    return in_array($code, $areas, true);
}

/* ===== Config ===== */
const BASE_PREFIX     = 'ultima_inspeccion';             // carpeta en /storage
const STORAGE_PREFIX  = 'storage/' . BASE_PREFIX . '/';  // para sacar la carpeta top

$projectBase = realpath(__DIR__ . '/..');
$baseDir     = realpath($projectBase . '/storage/' . BASE_PREFIX);
if(!$baseDir){ http_response_code(404); echo "No existe /storage/".BASE_PREFIX; exit; }

/* Áreas del usuario */
$userAreas = get_user_areas($pdo);

/* Subcarpeta (primer nivel) opcional para tabs (se valida más abajo) */
$sub = $_GET['sub'] ?? '';
$sub = trim(str_replace(['..','\\'], ['','/'], $sub), '/');

/* ===== Tabs: todas las subcarpetas de primer nivel ===== */
$tabsRaw = []; // acá guardamos todas menos "(Todos)"
$it = new DirectoryIterator($baseDir);
foreach ($it as $entry) {
  if($entry->isDot()) continue;
  if($entry->isDir()){
    $name = $entry->getFilename();
    // primero juntamos todo, después filtramos por áreas
    $tabsRaw[$name] = $name;
  }
}

/*
 * Prioridad de orden:
 *   1) Aspectos Generales
 *   2) Personal (S-1)
 *   3) Inteligencia (S-2)
 *   4) Operaciones (S-3)
 *   999) Materiales (S-4)
 *   50) cualquier otro (en el medio, por si en el futuro agregás algo)
 */
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
  if ($pa === $pb) {
    return strcasecmp($a, $b); // si empatan, ordenar alfabéticamente
  }
  return $pa <=> $pb;
});

/* Filtrado de tabs según áreas del usuario */
$tabs = ['' => '(Todos)'];
foreach ($tabsRaw as $name => $label) {
    if (can_user_see_folder($name, $userAreas)) {
        $tabs[$name] = $label;
    }
}

/* Validar sub: si el usuario no tiene acceso, lo mandamos a (Todos) */
if ($sub !== '' && !array_key_exists($sub, $tabs)) {
    $sub = '';
}

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
  $rel = str_replace('\\','/', substr($abs, strlen($projectBase)+1)); // relativo al proyecto

  // detectar carpeta de primer nivel para aplicar permisos también a nivel archivo
  $topFolder = '';
  if (str_starts_with($rel, STORAGE_PREFIX)) {
      $relFromStorage = substr($rel, strlen(STORAGE_PREFIX)); // ej: "Personal (S-1)/archivo.xlsx"
      $parts = explode('/', $relFromStorage, 2);
      $topFolder = $parts[0] ?? '';
  }
  if ($topFolder !== '' && !can_user_see_folder($topFolder, $userAreas)) {
      continue; // no tiene permiso para esta carpeta
  }

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
<title>Batallón de Comunicaciones 602 – Última inspección</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/theme-602.css">
<link rel="icon" type="image/png" href="../assets/img/bcom602.png">
<style>
  body{
    background: url("<?= e($IMG_BG) ?>") no-repeat center center fixed;
    background-size: cover; background-attachment: fixed;
    background-color:#0f1117; color:#e9eef5;
    font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu; margin:0; padding:0;
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
  .btn-acc{background:#16a34a;border:none;color:#fff;border-radius:12px;font-weight:800;padding:.45rem .9rem;}
  .btn-acc-sm{font-size:.85rem;padding:.32rem .56rem;border-radius:10px;}
  .btn-ed{background:#0ea5e9;border:none;color:#001018;font-weight:800;border-radius:10px;padding:.32rem .56rem;}
  .btn-ed:hover{background:#38bdf8;}
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
        <?php foreach($tabs as $k=>$label): ?>
          <a class="btn btn-outline-light <?= $sub===$k?'active':'' ?>" href="?sub=<?= e(urlencode($k)) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
      </div>
      <a class="btn btn-acc btn-acc-sm" href="index.php?scope=ultima_inspeccion">Volver al dashboard</a>
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
              <th style="width:120px">ACCIÓN</th>
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
            <tr><td colspan="7" class="text-muted">No se encontraron archivos XLSX.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</body>
</html>
