<?php
// public/operaciones/operaciones_condiciones.php - Matriz de condiciones de tiro
declare(strict_types=1);

$OFFLINE_MODE = false;
require_once __DIR__ . '/../../auth/bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/operaciones_helper.php';
require_once __DIR__ . '/s3_tiro_tables_helper.php';

if (!$OFFLINE_MODE) {
    operaciones_require_login();
}

s3_tiro_ensure_tables($pdo);

function e($v) { return operaciones_e($v); }

function s3_tiro_ensure_condiciones_table(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS s3_tiro_condiciones_personal (
            id INT AUTO_INCREMENT PRIMARY KEY,
            unidad_id INT NOT NULL,
            personal_id INT NOT NULL,
            armamento VARCHAR(40) NOT NULL,
            condicion VARCHAR(40) NOT NULL,
            estado VARCHAR(20) NOT NULL DEFAULT '',
            actualizado_por VARCHAR(120) NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_tiro_cond_personal (unidad_id, personal_id, armamento, condicion),
            KEY idx_tiro_cond_unidad_arma (unidad_id, armamento)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}

function s3_tiro_unidad_activa(PDO $pdo): int
{
    $personal = operaciones_get_personal_actual($pdo);
    $unidadId = (int)($personal['unidad_id'] ?? 0);
    if ($unidadId > 0) {
        return $unidadId;
    }

    $sessionUnidad = (int)($_SESSION['unidad_id'] ?? ($_SESSION['user']['unidad_id'] ?? 0));
    if ($sessionUnidad > 0) {
        return $sessionUnidad;
    }

    try {
        $st = $pdo->query("
            SELECT id
            FROM unidades
            WHERE slug = 'ecmilm'
               OR nombre_completo LIKE '%Escuela Militar de Monta%'
            ORDER BY id ASC
            LIMIT 1
        ");
        $id = (int)($st->fetchColumn() ?: 0);
        if ($id > 0) {
            return $id;
        }
    } catch (Throwable $e) {
    }

    return 1;
}

function s3_tiro_estado_label(string $estado): string
{
    return [
        '' => 'Pendiente',
        'apto' => 'Apto',
        'no_apto' => 'No apto',
        'sin_rendir' => 'Sin rendir',
    ][$estado] ?? 'Pendiente';
}

$armamentos = [
    'fal' => [
        'label' => 'FAL',
        'condiciones' => [
            'diagnostico' => 'Diagnostico',
            'b1' => 'B1',
            'b2' => 'B2',
            'b3' => 'B3',
            'b4' => 'B4',
            'b5' => 'B5',
        ],
    ],
    'pistola' => [
        'label' => 'Pistola',
        'condiciones' => [
            'diagnostico' => 'Diagnostico',
            'p1' => 'P1',
            'p2' => 'P2',
            'p3' => 'P3',
        ],
    ],
    'fap' => [
        'label' => 'FAP',
        'condiciones' => [
            'diagnostico' => 'Diagnostico',
            'fap1' => 'FAP 1',
            'fap2' => 'FAP 2',
            'fap3' => 'FAP 3',
        ],
    ],
    'mag' => [
        'label' => 'MAG',
        'condiciones' => [
            'diagnostico' => 'Diagnostico',
            'mag1' => 'MAG 1',
            'mag2' => 'MAG 2',
            'mag3' => 'MAG 3',
        ],
    ],
    'escopeta' => [
        'label' => 'Escopeta',
        'condiciones' => [
            'diagnostico' => 'Diagnostico',
            'e1' => 'E1',
            'e2' => 'E2',
            'e3' => 'E3',
        ],
    ],
];

$armamentoActual = strtolower(trim((string)($_GET['arma'] ?? $_POST['armamento'] ?? 'fal')));
if (!isset($armamentos[$armamentoActual])) {
    $armamentoActual = 'fal';
}

$condicionesActuales = $armamentos[$armamentoActual]['condiciones'];
$esAdmin = operaciones_es_admin($pdo);
$unidadActiva = s3_tiro_unidad_activa($pdo);
$usuario = (string)($_SESSION['user']['username'] ?? '');
$mensajeError = '';

$ASSET_WEB = operaciones_assets_url();
$IMG_BG = operaciones_assets_url('img/fondo.png');
$ESCUDO = operaciones_assets_url('img/ecmilm.png');

s3_tiro_ensure_condiciones_table($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (function_exists('csrf_verify')) {
        csrf_verify();
    }

    if (!$esAdmin) {
        $mensajeError = 'Acceso restringido. Solo administradores pueden guardar condiciones.';
    } else {
        $personalIds = $_POST['personal_ids'] ?? [];
        $estados = $_POST['estado'] ?? [];

        if (!is_array($personalIds)) {
            $personalIds = [];
        }
        if (!is_array($estados)) {
            $estados = [];
        }

        $permitidos = ['', 'apto', 'no_apto', 'sin_rendir'];
        $stmt = $pdo->prepare("
            INSERT INTO s3_tiro_condiciones_personal
                (unidad_id, personal_id, armamento, condicion, estado, actualizado_por)
            VALUES
                (:unidad_id, :personal_id, :armamento, :condicion, :estado, :actualizado_por)
            ON DUPLICATE KEY UPDATE
                estado = VALUES(estado),
                actualizado_por = VALUES(actualizado_por),
                updated_at = CURRENT_TIMESTAMP
        ");

        try {
            $pdo->beginTransaction();
            foreach ($personalIds as $pidRaw) {
                $pid = (int)$pidRaw;
                if ($pid <= 0) {
                    continue;
                }

                foreach ($condicionesActuales as $condKey => $condLabel) {
                    $estado = (string)($estados[$pid][$condKey] ?? '');
                    if (!in_array($estado, $permitidos, true)) {
                        $estado = '';
                    }
                    $stmt->execute([
                        ':unidad_id' => $unidadActiva,
                        ':personal_id' => $pid,
                        ':armamento' => $armamentoActual,
                        ':condicion' => $condKey,
                        ':estado' => $estado,
                        ':actualizado_por' => $usuario !== '' ? $usuario : null,
                    ]);
                }
            }
            $pdo->commit();

            header('Location: operaciones_condiciones.php?arma=' . rawurlencode($armamentoActual) . '&saved=1');
            exit;
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $mensajeError = $ex->getMessage();
        }
    }
}

$sqlOrdenJerarquia = "CASE pu.jerarquia
    WHEN 'OFICIAL' THEN 1
    WHEN 'SUBOFICIAL' THEN 2
    WHEN 'SOLDADO' THEN 3
    WHEN 'AGENTE_CIVIL' THEN 4
    ELSE 5
END";

$sqlOrdenGrado = "CASE pu.grado
    WHEN 'TG' THEN 9
    WHEN 'GD' THEN 10
    WHEN 'GB' THEN 11
    WHEN 'CR' THEN 12
    WHEN 'TC' THEN 13
    WHEN 'MY' THEN 14
    WHEN 'CT' THEN 15
    WHEN 'TP' THEN 16
    WHEN 'TT' THEN 17
    WHEN 'ST' THEN 18
    WHEN 'ST EC' THEN 19
    WHEN 'SM' THEN 20
    WHEN 'SP' THEN 21
    WHEN 'SA' THEN 22
    WHEN 'SI' THEN 23
    WHEN 'SG' THEN 24
    WHEN 'CI' THEN 25
    WHEN 'CI EC' THEN 26
    WHEN 'CI Art 11' THEN 27
    WHEN 'CB' THEN 28
    WHEN 'CB EC' THEN 29
    WHEN 'CB Art 11' THEN 30
    WHEN 'VP' THEN 31
    WHEN 'VS' THEN 32
    WHEN 'VS EC' THEN 33
    WHEN 'SV' THEN 34
    WHEN 'AC' THEN 35
    ELSE 99
END";

$personal = [];
try {
    $st = $pdo->prepare("
        SELECT
            pu.id,
            pu.jerarquia,
            pu.grado,
            pu.arma,
            pu.apellido_nombre,
            pu.dni,
            di.nombre AS destino_interno
        FROM personal_unidad pu
        LEFT JOIN destino_interno di ON di.id = pu.destino_interno
        WHERE pu.unidad_id = :unidad_id
        ORDER BY {$sqlOrdenJerarquia}, {$sqlOrdenGrado}, pu.apellido_nombre ASC
    ");
    $st->execute([':unidad_id' => $unidadActiva]);
    $personal = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $ex) {
    $mensajeError = $mensajeError !== '' ? $mensajeError : $ex->getMessage();
}

$estadoMap = [];
try {
    $st = $pdo->prepare("
        SELECT personal_id, condicion, estado
        FROM s3_tiro_condiciones_personal
        WHERE unidad_id = :unidad_id
          AND armamento = :armamento
    ");
    $st->execute([
        ':unidad_id' => $unidadActiva,
        ':armamento' => $armamentoActual,
    ]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $estadoMap[(int)$row['personal_id']][(string)$row['condicion']] = (string)$row['estado'];
    }
} catch (Throwable $ex) {
    $mensajeError = $mensajeError !== '' ? $mensajeError : $ex->getMessage();
}

$totalPersonal = count($personal);
$totalCeldas = $totalPersonal * count($condicionesActuales);
$totalAptos = 0;
$totalNoAptos = 0;
$totalCargados = 0;
foreach ($personal as $p) {
    $pid = (int)$p['id'];
    foreach ($condicionesActuales as $condKey => $label) {
        $estado = (string)($estadoMap[$pid][$condKey] ?? '');
        if ($estado !== '') {
            $totalCargados++;
        }
        if ($estado === 'apto') {
            $totalAptos++;
        }
        if ($estado === 'no_apto') {
            $totalNoAptos++;
        }
    }
}
$avance = $totalCeldas > 0 ? round(($totalCargados * 100) / $totalCeldas, 1) : 0;
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Condiciones de tiro por armamento</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= e($ASSET_WEB) ?>/css/theme-602.css">
<link rel="icon" href="<?= e($ESCUDO) ?>">
<style>
  :root{
    --bg-dark:#020617;
    --card-bg:rgba(15,23,42,.94);
    --card-border:rgba(148,163,184,.45);
    --text-main:#f8fafc;
    --text-soft:#cbd5e1;
    --text-muted:#94a3b8;
    --accent:#22c55e;
    --info:#38bdf8;
  }
  *{box-sizing:border-box;}
  body{
    min-height:100vh;
    margin:0;
    font-family:system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,sans-serif;
    background:
      radial-gradient(circle at top left, rgba(56,189,248,.22), transparent 60%),
      radial-gradient(circle at bottom right, rgba(34,197,94,.18), transparent 55%),
      url("<?= e($IMG_BG) ?>") center/cover fixed;
    background-color:var(--bg-dark);
    color:var(--text-main);
    overflow-x:hidden;
  }
  body::before{
    content:"";
    position:fixed;
    inset:0;
    background:radial-gradient(circle at top, rgba(15,23,42,.70), rgba(15,23,42,.95));
    pointer-events:none;
    z-index:-1;
  }
  .page-wrap{padding:24px 16px 42px;}
  .container-main{max-width:1280px;margin:0 auto;}
  header.brand-hero{padding:14px 0 6px;}
  .hero-inner{
    max-width:1280px;
    margin:0 auto;
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:16px;
    padding:0 16px;
  }
  .brand-left{display:flex;align-items:center;gap:14px;}
  .brand-logo{height:56px;width:auto;filter:drop-shadow(0 0 10px rgba(0,0,0,.8));}
  .brand-title{font-weight:850;font-size:1.15rem;letter-spacing:.02em;color:#fff;}
  .brand-sub{font-size:.82rem;color:#cbd5f5;}
  .btn-ghost{
    border-radius:999px;
    border:1px solid rgba(148,163,184,.55);
    background:rgba(15,23,42,.82);
    color:#f8fafc;
    font-size:.82rem;
    font-weight:750;
    padding:.42rem 1rem;
    box-shadow:0 10px 30px rgba(0,0,0,.55);
    text-decoration:none;
  }
  .btn-ghost:hover{background:rgba(30,64,175,.9);border-color:rgba(129,140,248,.9);color:#fff;}
  .section-header{margin-bottom:18px;}
  .section-kicker .sk-text{
    font-size:1rem;
    font-weight:950;
    letter-spacing:.18em;
    text-transform:uppercase;
    background:linear-gradient(90deg,#38bdf8,#22c55e);
    -webkit-background-clip:text;
    -webkit-text-fill-color:transparent;
    filter:drop-shadow(0 0 6px rgba(30,58,138,.55));
    padding-bottom:3px;
    border-bottom:2px solid rgba(34,197,94,.45);
    display:inline-block;
  }
  .section-title{font-size:1.75rem;font-weight:900;margin-top:6px;color:#fff;}
  .section-sub{font-size:.92rem;color:var(--text-soft);max-width:780px;}
  .panel{
    border-radius:22px;
    background:
      radial-gradient(circle at top left, rgba(56,189,248,.16), transparent 60%),
      radial-gradient(circle at bottom right, rgba(34,197,94,.12), transparent 70%),
      var(--card-bg);
    border:1px solid rgba(15,23,42,.9);
    box-shadow:0 24px 50px rgba(0,0,0,.82),0 0 0 1px rgba(148,163,184,.28);
    backdrop-filter:blur(12px);
    overflow:hidden;
  }
  .toolbar{
    display:flex;
    align-items:flex-end;
    justify-content:space-between;
    gap:14px;
    flex-wrap:wrap;
    padding:18px;
    border-bottom:1px solid rgba(148,163,184,.22);
  }
  .weapon-tabs{display:flex;flex-wrap:wrap;gap:8px;}
  .weapon-tab{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:38px;
    padding:.45rem .95rem;
    border-radius:999px;
    border:1px solid rgba(148,163,184,.38);
    background:rgba(15,23,42,.72);
    color:#e2e8f0;
    text-decoration:none;
    font-weight:850;
    font-size:.82rem;
  }
  .weapon-tab:hover{color:#fff;border-color:rgba(56,189,248,.75);}
  .weapon-tab.active{
    background:linear-gradient(135deg,rgba(34,197,94,.96),rgba(14,165,233,.86));
    border-color:rgba(187,247,208,.75);
    color:#02140b;
    box-shadow:0 0 22px rgba(34,197,94,.25);
  }
  .stats{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:10px;
    min-width:min(100%,520px);
  }
  .stat{
    border:1px solid rgba(148,163,184,.25);
    background:rgba(2,6,23,.52);
    border-radius:14px;
    padding:9px 11px;
  }
  .stat-label{font-size:.68rem;text-transform:uppercase;letter-spacing:.12em;color:var(--text-muted);font-weight:850;}
  .stat-num{font-size:1.2rem;font-weight:950;color:#fff;line-height:1.05;}
  .table-shell{overflow:auto;max-height:calc(100vh - 310px);}
  .matrix{
    width:100%;
    min-width:980px;
    border-collapse:separate;
    border-spacing:0;
    color:#f8fafc;
  }
  .matrix th,
  .matrix td{
    border-bottom:1px solid rgba(148,163,184,.18);
    padding:10px 12px;
    vertical-align:middle;
    background:rgba(15,23,42,.78);
  }
  .matrix thead th{
    position:sticky;
    top:0;
    z-index:5;
    background:rgba(15,23,42,.98);
    color:#dbeafe;
    font-size:.74rem;
    text-transform:uppercase;
    letter-spacing:.08em;
    font-weight:900;
    border-bottom:1px solid rgba(56,189,248,.35);
  }
  .matrix .sticky-col{
    position:sticky;
    left:0;
    z-index:4;
    background:rgba(8,13,30,.98);
  }
  .matrix thead .sticky-col{z-index:7;}
  .grade-cell{width:84px;color:#bae6fd;font-weight:900;font-size:.78rem;}
  .name-cell{min-width:250px;}
  .name-main{font-weight:900;color:#fff;}
  .name-sub{font-size:.74rem;color:var(--text-muted);margin-top:2px;}
  .jer-row td{
    position:sticky;
    left:0;
    z-index:3;
    background:linear-gradient(90deg,rgba(30,41,59,.98),rgba(15,23,42,.96)) !important;
    color:#7dd3fc;
    font-weight:950;
    letter-spacing:.10em;
    text-transform:uppercase;
    font-size:.75rem;
    border-top:1px solid rgba(56,189,248,.32);
    border-bottom:1px solid rgba(56,189,248,.32);
  }
  .cond-cell{text-align:center;min-width:142px;}
  .estado-select{
    width:100%;
    min-height:36px;
    border-radius:12px;
    border:1px solid rgba(148,163,184,.36);
    background:#101827;
    color:#f8fafc;
    font-size:.78rem;
    font-weight:850;
    padding:.35rem .5rem;
  }
  .estado-select:focus{outline:none;border-color:#38bdf8;box-shadow:0 0 0 3px rgba(56,189,248,.16);}
  .estado-apto{border-color:rgba(34,197,94,.75);background:rgba(20,83,45,.74);}
  .estado-no_apto{border-color:rgba(248,113,113,.75);background:rgba(127,29,29,.70);}
  .estado-sin_rendir{border-color:rgba(251,191,36,.72);background:rgba(113,63,18,.70);}
  .estado-badge{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:92px;
    min-height:30px;
    border-radius:999px;
    padding:.25rem .65rem;
    font-size:.72rem;
    font-weight:900;
    color:#f8fafc;
    background:rgba(71,85,105,.75);
    border:1px solid rgba(148,163,184,.4);
  }
  .estado-badge.apto{background:rgba(22,101,52,.85);border-color:rgba(34,197,94,.75);}
  .estado-badge.no_apto{background:rgba(127,29,29,.85);border-color:rgba(248,113,113,.75);}
  .estado-badge.sin_rendir{background:rgba(113,63,18,.85);border-color:rgba(251,191,36,.75);}
  .empty-state{padding:34px;text-align:center;color:#cbd5e1;}
  .save-bar{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    flex-wrap:wrap;
    padding:14px 18px;
    border-top:1px solid rgba(148,163,184,.22);
    background:rgba(2,6,23,.48);
  }
  .save-note{font-size:.82rem;color:var(--text-soft);}
  .btn-save{
    border:0;
    border-radius:999px;
    background:linear-gradient(135deg,#22c55e,#16a34a);
    color:#03140a;
    font-weight:950;
    padding:.55rem 1.15rem;
    box-shadow:0 14px 28px rgba(34,197,94,.22);
  }
  .btn-save:hover{filter:brightness(1.08);}
  .alert-soft{
    border:1px solid rgba(34,197,94,.35);
    background:rgba(20,83,45,.45);
    color:#dcfce7;
    border-radius:16px;
    padding:10px 14px;
    margin-bottom:14px;
    font-weight:750;
  }
  .alert-error{
    border:1px solid rgba(248,113,113,.45);
    background:rgba(127,29,29,.48);
    color:#fee2e2;
    border-radius:16px;
    padding:10px 14px;
    margin-bottom:14px;
    font-weight:750;
  }
  @media (max-width:780px){
    .hero-inner{align-items:flex-start;}
    .stats{grid-template-columns:repeat(2,minmax(0,1fr));}
    .section-title{font-size:1.4rem;}
    .table-shell{max-height:calc(100vh - 360px);}
  }
</style>
</head>
<body>

<header class="brand-hero">
  <div class="hero-inner">
    <div class="brand-left">
      <img src="<?= e($ESCUDO) ?>" class="brand-logo" alt="Escudo ECMILM" onerror="this.onerror=null;this.src='<?= e($ASSET_WEB) ?>/img/EA.png';">
      <div>
        <div class="brand-title">Escuela Militar de Monta&ntilde;a</div>
        <div class="brand-sub">&ldquo;La monta&ntilde;a nos une&rdquo;</div>
      </div>
    </div>
    <a href="operaciones_tiro.php" class="btn-ghost">Volver a Tiro</a>
  </div>
</header>

<main class="page-wrap">
  <div class="container-main">
    <section class="section-header">
      <div class="section-kicker"><span class="sk-text">S-3 &middot; TIRO</span></div>
      <h1 class="section-title">Condiciones de tiro por armamento</h1>
      <p class="section-sub mb-0">
        Tabla general del personal de la unidad con carga directa de condiciones.
        Cambie de armamento para ver y guardar su matriz correspondiente.
      </p>
    </section>

    <?php if (isset($_GET['saved'])): ?>
      <div class="alert-soft">Condiciones guardadas correctamente.</div>
    <?php endif; ?>
    <?php if ($mensajeError !== ''): ?>
      <div class="alert-error"><?= e($mensajeError) ?></div>
    <?php endif; ?>
    <?php if (!$esAdmin): ?>
      <div class="alert-soft">Vista de consulta: solo administradores pueden modificar las condiciones.</div>
    <?php endif; ?>

    <form method="post" class="panel">
      <?php if (function_exists('csrf_input')) echo csrf_input(); ?>
      <input type="hidden" name="armamento" value="<?= e($armamentoActual) ?>">

      <div class="toolbar">
        <div>
          <div class="weapon-tabs" aria-label="Cambiar armamento">
            <?php foreach ($armamentos as $key => $arma): ?>
              <a class="weapon-tab <?= $key === $armamentoActual ? 'active' : '' ?>" href="operaciones_condiciones.php?arma=<?= e($key) ?>">
                <?= e($arma['label']) ?>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="stats">
          <div class="stat">
            <div class="stat-label">Personal</div>
            <div class="stat-num"><?= e($totalPersonal) ?></div>
          </div>
          <div class="stat">
            <div class="stat-label">Avance</div>
            <div class="stat-num"><?= e($avance) ?>%</div>
          </div>
          <div class="stat">
            <div class="stat-label">Aptos</div>
            <div class="stat-num"><?= e($totalAptos) ?></div>
          </div>
          <div class="stat">
            <div class="stat-label">No aptos</div>
            <div class="stat-num"><?= e($totalNoAptos) ?></div>
          </div>
        </div>
      </div>

      <?php if (empty($personal)): ?>
        <div class="empty-state">No hay personal cargado para esta unidad.</div>
      <?php else: ?>
        <div class="table-shell">
          <table class="matrix">
            <thead>
              <tr>
                <th class="sticky-col grade-cell">Grado</th>
                <th>Personal</th>
                <th>Arma / Destino</th>
                <?php foreach ($condicionesActuales as $condLabel): ?>
                  <th class="cond-cell"><?= e($condLabel) ?></th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <?php
                $jerActual = null;
                $jerLabels = [
                    'OFICIAL' => 'Oficiales',
                    'SUBOFICIAL' => 'Suboficiales',
                    'SOLDADO' => 'Soldados',
                    'AGENTE_CIVIL' => 'Agentes civiles',
                ];
              ?>
              <?php foreach ($personal as $p): ?>
                <?php
                  $pid = (int)$p['id'];
                  $jer = (string)($p['jerarquia'] ?? '');
                  if ($jer !== $jerActual):
                    $jerActual = $jer;
                ?>
                  <tr class="jer-row">
                    <td colspan="<?= 3 + count($condicionesActuales) ?>"><?= e($jerLabels[$jer] ?? ($jer !== '' ? $jer : 'Sin jerarquia')) ?></td>
                  </tr>
                <?php endif; ?>
                <tr>
                  <td class="sticky-col grade-cell">
                    <?= e($p['grado'] ?? '') ?>
                    <input type="hidden" name="personal_ids[]" value="<?= $pid ?>">
                  </td>
                  <td class="name-cell">
                    <div class="name-main"><?= e($p['apellido_nombre'] ?? '') ?></div>
                    <div class="name-sub">DNI <?= e($p['dni'] ?? '-') ?></div>
                  </td>
                  <td>
                    <div class="name-main"><?= e($p['arma'] ?? '-') ?></div>
                    <div class="name-sub"><?= e($p['destino_interno'] ?? 'Sin destino interno') ?></div>
                  </td>
                  <?php foreach ($condicionesActuales as $condKey => $condLabel): ?>
                    <?php $estado = (string)($estadoMap[$pid][$condKey] ?? ''); ?>
                    <td class="cond-cell">
                      <?php if ($esAdmin): ?>
                        <select
                          class="estado-select <?= $estado !== '' ? 'estado-' . e($estado) : '' ?>"
                          name="estado[<?= $pid ?>][<?= e($condKey) ?>]"
                          data-estado-select
                        >
                          <option value="" <?= $estado === '' ? 'selected' : '' ?>>Pendiente</option>
                          <option value="apto" <?= $estado === 'apto' ? 'selected' : '' ?>>Apto</option>
                          <option value="no_apto" <?= $estado === 'no_apto' ? 'selected' : '' ?>>No apto</option>
                          <option value="sin_rendir" <?= $estado === 'sin_rendir' ? 'selected' : '' ?>>Sin rendir</option>
                        </select>
                      <?php else: ?>
                        <span class="estado-badge <?= e($estado) ?>"><?= e(s3_tiro_estado_label($estado)) ?></span>
                      <?php endif; ?>
                    </td>
                  <?php endforeach; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <div class="save-bar">
        <div class="save-note">
          Armamento activo: <strong><?= e($armamentos[$armamentoActual]['label']) ?></strong>.
          Las condiciones se guardan por personal y por armamento.
        </div>
        <?php if ($esAdmin && !empty($personal)): ?>
          <button type="submit" class="btn-save">Guardar condiciones</button>
        <?php endif; ?>
      </div>
    </form>
  </div>
</main>

<script>
document.querySelectorAll('[data-estado-select]').forEach((select) => {
  const paint = () => {
    select.classList.remove('estado-apto', 'estado-no_apto', 'estado-sin_rendir');
    if (select.value) {
      select.classList.add('estado-' + select.value);
    }
  };
  select.addEventListener('change', paint);
  paint();
});
</script>
<?php operaciones_render_chat_widget($pdo); ?>
</body>
</html>
