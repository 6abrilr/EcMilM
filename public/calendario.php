<?php
// /ea/public/calendario.php
declare(strict_types=1);

require_once __DIR__ . '/../auth/bootstrap.php';
require_login();
require_once __DIR__ . '/../config/db.php';
/** @var PDO $pdo */

if (!function_exists('e')) {
    function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

$user          = function_exists('current_user') ? current_user() : null;
$unidad_id     = (int)($user['unidad_id'] ?? 1);
$creado_por    = trim((string)($user['apellido_nombre'] ?? $user['nombre'] ?? $user['dni'] ?? ''));
$creado_por_id = (int)($user['id'] ?? 0);

$roleCodigo = 'USUARIO'; $esAdmin = false; $userAreaCode = '';
try {
    if ($user) {
        $dni = preg_replace('/\D+/', '', (string)($user['dni'] ?? $user['username'] ?? ''));
        if ($dni !== '') {
            $st = $pdo->prepare("SELECT r.codigo AS role_codigo, d.codigo AS destino_codigo
                                 FROM personal_unidad pu
                                 LEFT JOIN roles r ON r.id = pu.role_id
                                 LEFT JOIN destino d ON d.id = pu.destino_id
                                 WHERE REPLACE(REPLACE(REPLACE(pu.dni,'.',''),'-',''),' ','') = :dni LIMIT 1");
            $st->execute([':dni' => $dni]);
            if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                $roleCodigo   = strtoupper((string)($row['role_codigo']    ?? 'USUARIO'));
                $userAreaCode = strtoupper(trim((string)($row['destino_codigo'] ?? '')));
            }
        }
    }
} catch (Throwable $e) {}
$esAdmin = ($roleCodigo === 'ADMIN' || $roleCodigo === 'SUPERADMIN');

// ── FILTRO DE ÁREA ──────────────────────────────────────────────────────
if ($esAdmin) {
    $area = trim((string)($_GET['area'] ?? 'ALL'));
    if ($area === '') $area = 'ALL';
} else {
    $area = $userAreaCode !== '' ? $userAreaCode : '__NONE__';
}

// BASE WEB
$SELF_WEB       = (string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
$PUBLIC_DIR_WEB = rtrim(str_replace('\\','/', dirname($SELF_WEB)), '/');
$APP_URL        = rtrim((string)preg_replace('#/public$#', '', $PUBLIC_DIR_WEB), '/');
$ASSETS_URL     = $APP_URL . '/assets';
$IMG_BG  = $ASSETS_URL . '/img/fondo.png';
$ESCUDO  = $ASSETS_URL . '/img/ecmilm.png';
$NOMBRE  = 'Escuela Militar de Montaña';
$LEYENDA = 'La Montaña Nos Une';

function url_public(string $path): string { global $PUBLIC_DIR_WEB; return $PUBLIC_DIR_WEB . $path; }

// INPUTS
$today = new DateTimeImmutable('today');
$ym    = preg_match('/^\d{4}-\d{2}$/', (string)($_GET['ym'] ?? '')) ? $_GET['ym'] : $today->format('Y-m');

$monthStart = DateTimeImmutable::createFromFormat('Y-m-d', $ym.'-01') ?: $today->modify('first day of this month');
$monthEnd   = $monthStart->modify('last day of this month');

$selectedDay = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['day'] ?? '')) ? $_GET['day'] : $today->format('Y-m-d');
if (substr($selectedDay, 0, 7) !== $ym) $selectedDay = $monthStart->format('Y-m-d');

$err = ''; $ok = '';

// HELPERS
function month_label_es(string $ym): string {
    static $m = [1=>'Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    return ($m[(int)substr($ym,5,2)] ?? 'Mes').' '.(int)substr($ym,0,4);
}
function build_qs(array $base, array $over=[]): string { return http_build_query(array_merge($base,$over)); }

$prevYm = $monthStart->modify('-1 month')->format('Y-m');
$nextYm = $monthStart->modify('+1 month')->format('Y-m');

// ── AJAX: respuestas JSON para los modales ──────────────────────────────
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// EXPORT PDF — parámetros recibidos desde el modal
if (($_GET['action'] ?? '') === 'export_pdf') {

    // ── PARÁMETROS ────────────────────────────────────────────────────
    $pdfTipo    = trim((string)($_GET['pdf_tipo']      ?? 'tareas'));   // tareas | resumen | pes
    $pdfUnidad  = trim((string)($_GET['pdf_unidad']    ?? $NOMBRE));
    $pdfSubUnit = trim((string)($_GET['pdf_subunidad'] ?? ''));          // ej: "Sección Informática"
    $pdfAnio    = trim((string)($_GET['pdf_anio']      ?? '"AÑO DE LA GRANDEZA ARGENTINA"'));
    $pdfTitulo  = trim((string)($_GET['pdf_titulo']    ?? ''));
    $pdfLugar   = trim((string)($_GET['pdf_lugar']     ?? 'San Carlos de Bariloche'));
    $pdfFirm1   = trim((string)($_GET['pdf_firmante']  ?? ''));
    $pdfFunc1   = trim((string)($_GET['pdf_funcion']   ?? ''));
    $pdfFirm2   = trim((string)($_GET['pdf_firmante2'] ?? ''));
    $pdfFunc2   = trim((string)($_GET['pdf_funcion2']  ?? ''));

    // ── HELPERS ───────────────────────────────────────────────────────
    $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

    // Fecha militar: 16MAR26
    function fecha_mil(string $fecha): string {
        static $meses = [
            '01'=>'ENE','02'=>'FEB','03'=>'MAR','04'=>'ABR','05'=>'MAY','06'=>'JUN',
            '07'=>'JUL','08'=>'AGO','09'=>'SEP','10'=>'OCT','11'=>'NOV','12'=>'DIC'
        ];
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $fecha, $m)) return $fecha;
        return ltrim($m[3],'0') . ($meses[$m[2]] ?? $m[2]) . substr($m[1],2);
    }

    // Estado legible español
    function estado_es(string $e): string {
        return match($e) {
            'POR_HACER'  => 'Pendiente',
            'EN_PROCESO' => 'En proceso',
            'REALIZADA'  => 'Realizada',
            default      => $e,
        };
    }

    // Prioridad legible
    function prio_es(string $p): string {
        return match(strtoupper($p)) {
            'ALTA'  => 'Alta',
            'MEDIA' => 'Media',
            'BAJA'  => 'Baja',
            default => $p,
        };
    }

    // Fecha escrita: "15 de marzo de 2026"
    function fecha_larga(string $lugar, string $ym, string $dia): string {
        static $meses = [
            '01'=>'enero','02'=>'febrero','03'=>'marzo','04'=>'abril','05'=>'mayo','06'=>'junio',
            '07'=>'julio','08'=>'agosto','09'=>'septiembre','10'=>'octubre','11'=>'noviembre','12'=>'diciembre'
        ];
        preg_match('/^(\d{4})-(\d{2})$/', $ym, $m);
        $anio = $m[1] ?? date('Y');
        $mes  = $meses[$m[2] ?? ''] ?? '';
        return "$lugar, $dia de $mes del $anio.";
    }

    // ── DATOS ─────────────────────────────────────────────────────────
    $anPdf = [];
    try {
        $stA = $pdo->prepare("SELECT codigo, nombre FROM destino WHERE unidad_id=? AND activo=1");
        $stA->execute([$unidad_id]);
        foreach ($stA->fetchAll(PDO::FETCH_ASSOC) as $a) $anPdf[(string)$a['codigo']] = (string)$a['nombre'];
    } catch (Throwable $ignored) {}

    $wtf    = $area !== 'ALL' ? " AND area_code=? " : "";
    $tareas = []; $diario = [];

    // Filtro por tipo:
    // - tareas: todas las tareas, sin diario
    // - resumen: solo diario (lo que se hizo) sin tabla de tareas
    // - pes: tareas POR_HACER+EN_PROCESO, sin diario, sin columna Estado
    $estadoFilter = '';
    if ($pdfTipo === 'pes') {
        $estadoFilter = " AND estado IN ('POR_HACER','EN_PROCESO') ";
    }

    // Tareas solo para 'tareas' y 'pes'
    if ($pdfTipo !== 'resumen') {
        $pt = [$unidad_id,$monthStart->format('Y-m-d'),$monthEnd->format('Y-m-d'),$monthStart->format('Y-m-d'),$monthEnd->format('Y-m-d')];
        if ($area !== 'ALL') $pt[] = $area;
        $st = $pdo->prepare("SELECT * FROM calendario_tareas
            WHERE unidad_id=? AND (
                (inicio IS NOT NULL AND DATE(inicio) BETWEEN ? AND ?)
                OR (fecha_vencimiento IS NOT NULL AND fecha_vencimiento BETWEEN ? AND ?)
            ) $wtf $estadoFilter ORDER BY COALESCE(inicio, CONCAT(fecha_vencimiento,' 00:00:00')) ASC");
        $st->execute($pt);
        $tareas = $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // Diario solo para 'resumen'
    if ($pdfTipo === 'resumen') {
        $pd = [$unidad_id, $monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d')];
        if ($area !== 'ALL') $pd[] = $area;
        $st = $pdo->prepare("SELECT * FROM calendario_diario
            WHERE unidad_id=? AND fecha BETWEEN ? AND ? $wtf ORDER BY fecha ASC, id ASC");
        $st->execute($pd);
        $diario = $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── TÍTULOS POR DEFECTO ───────────────────────────────────────────
    $areaDisplay = $area === 'ALL'
        ? ($pdfSubUnit ?: 'Todas las áreas')
        : ($pdfSubUnit ?: ($anPdf[$area] ?? $area));

    if ($pdfTitulo === '') {
        $pdfTitulo = match($pdfTipo) {
            'resumen' => 'Resumen — ' . month_label_es($ym),
            'pes'     => 'Programa especial semanal — ' . month_label_es($ym),
            default   => 'Tareas de ' . strtolower($areaDisplay),
        };
    }

    $diaHoy     = date('j');
    $lugarFecha = fecha_larga($pdfLugar, $ym, $diaHoy);

    header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title><?= $h($pdfTitulo) ?></title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: "Times New Roman", Times, serif;
      color: #000;
      background: #fff;
      font-size: 10pt;
      margin: 18mm 20mm 22mm;
    }

    /* ── ENCABEZADO MILITAR ── */
    .hdr {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 18px;
    }
    .hdr-left { display: flex; align-items: flex-start; gap: 12px; }
    .hdr-escudo { width: 60px; height: 60px; object-fit: contain; }
    .hdr-org { line-height: 1.4; }
    .hdr-org .org-principal { font-size: 12pt; font-weight: bold; }
    .hdr-org .org-sub       { font-size: 10pt; font-style: italic; }
    .hdr-right {
      font-size: 9pt; font-style: italic;
      text-align: right; max-width: 220px;
    }

    /* ── TÍTULO CENTRADO ── */
    .doc-titulo {
      text-align: center;
      font-size: 11pt;
      font-weight: bold;
      text-decoration: underline;
      margin: 24px 0 18px;
    }

    /* ── LUGAR Y FECHA (va después de la tabla) ── */
    .lugar-fecha {
      text-align: right;
      font-size: 9.5pt;
      margin-top: 10px;
      margin-bottom: 18px;
    }

    /* ── TABLA MILITAR ── */
    table {
      border-collapse: collapse;
      width: 100%;
      font-size: 9pt;
    }
    th {
      background: #d0d0d0;
      border: 1px solid #555;
      padding: 5px 6px;
      text-align: center;
      font-weight: bold;
      font-size: 9pt;
    }
    td {
      border: 1px solid #777;
      padding: 5px 6px;
      vertical-align: top;
    }
    td.center { text-align: center; vertical-align: middle; }
    td.num    { text-align: center; vertical-align: middle; font-weight: bold; width: 28px; }
    .tarea-titulo    { font-weight: bold; }
    .tarea-desc      { font-size: 8pt; color: #333; font-style: italic; }
    tr:nth-child(even) td { background: #f8f8f8; }

    /* ── DIARIO (para resumen) ── */
    .diario-bloque {
      margin-bottom: 10px;
      border: 1px solid #bbb;
      padding: 6px 8px;
      border-radius: 2px;
    }
    .diario-fecha   { font-weight: bold; font-size: 9pt; margin-bottom: 3px; }
    .diario-detalle { font-size: 9.5pt; white-space: pre-wrap; }

    /* ── FIRMAS ── */
    .firmas-wrap {
      display: flex;
      justify-content: space-around;
      margin-top: 60px;
      flex-wrap: wrap;
      gap: 40px;
    }
    .firma-bloque   { text-align: center; min-width: 200px; }
    .firma-espacio  { height: 50px; }
    .firma-linea    { border-top: 1px solid #000; padding-top: 5px; font-size: 10pt; font-weight: bold; }
    .firma-cargo    { font-size: 9pt; font-style: italic; margin-top: 2px; }

    /* ── SIN DATOS ── */
    .sin-datos { font-style: italic; color: #777; font-size: 9pt; padding: 6px 0; }

    /* ── PRINT ── */
    @media print {
      body { margin: 0; }
      .no-print { display: none; }
      table { page-break-inside: auto; }
      tr    { page-break-inside: avoid; }
    }
  </style>
</head>
<body>

  <!-- ENCABEZADO MILITAR -->
  <div class="hdr">
    <div class="hdr-left">
      <div class="hdr-org">
        <div class="org-principal">Ejército Argentino</div>
        <div class="org-sub"><?= $h($pdfUnidad) ?></div>
      </div>
    </div>
    <?php if ($pdfAnio !== ''): ?>
    <div class="hdr-right"><?= $h($pdfAnio) ?></div>
    <?php endif; ?>
  </div>

  <!-- TÍTULO -->
  <div class="doc-titulo"><?= $h($pdfTitulo) ?></div>

  <!-- ══════════════════════════════════════════
       TIPO: tareas / pes — TABLA DE TAREAS
       ══════════════════════════════════════════ -->
  <?php if ($pdfTipo !== 'resumen'): ?>
  <?php if ($tareas): ?>
  <table>
    <thead>
      <tr>
        <th style="width:26px;">Nro</th>
        <th style="width:<?= $pdfTipo==='pes' ? '32%' : '28%' ?>;">Título</th>
        <?php if ($pdfTipo !== 'pes'): ?>
        <th style="width:9%;">Estado</th>
        <?php endif; ?>
        <th style="width:7%;">Prioridad</th>
        <th style="width:<?= $pdfTipo==='pes' ? '18%' : '16%' ?>;">Asignado a</th>
        <th style="width:<?= $pdfTipo==='pes' ? '18%' : '16%' ?>;">Ordenado por</th>
        <th style="width:9%;">Inicio</th>
        <th style="width:9%;">Finalización</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($tareas as $i => $t):
          $inicioFmt = !empty($t['inicio'])
              ? fecha_mil(substr((string)$t['inicio'], 0, 10)) : '';
          $vencFmt   = !empty($t['fecha_vencimiento'])
              ? fecha_mil((string)$t['fecha_vencimiento']) : '';
          $asignado  = (string)($t['asignado_a']   ?? '');
          $ordenado  = (string)($t['ordenado_por'] ?? '');
      ?>
      <tr>
        <td class="num"><?= $i+1 ?></td>
        <td>
          <div class="tarea-titulo"><?= $h($t['titulo']) ?></div>
          <?php if (!empty($t['descripcion'])): ?>
            <div class="tarea-desc"><?= $h($t['descripcion']) ?></div>
          <?php endif; ?>
        </td>
        <?php if ($pdfTipo !== 'pes'): ?>
        <td class="center"><?= $h(estado_es($t['estado'])) ?></td>
        <?php endif; ?>
        <td class="center"><?= $h(prio_es($t['prioridad'])) ?></td>
        <td><?= $h($asignado) ?></td>
        <td><?= $h($ordenado) ?></td>
        <td class="center"><?= $h($inicioFmt) ?></td>
        <td class="center"><?= $h($vencFmt) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
    <p class="sin-datos">Sin tareas para este período y área.</p>
  <?php endif; ?>

  <!-- Lugar y fecha debajo de la tabla -->
  <div class="lugar-fecha"><?= $h($lugarFecha) ?></div>

  <?php endif; // fin bloque tareas/pes ?>

  <!-- ══════════════════════════════════════════
       TIPO: resumen — DIARIO DE ACTIVIDADES
       ══════════════════════════════════════════ -->
  <?php if ($pdfTipo === 'resumen'): ?>
  <?php if ($diario): ?>
    <?php foreach ($diario as $d):
        $areaNom = isset($anPdf[$d['area_code']])
            ? $d['area_code'].' · '.$anPdf[$d['area_code']] : $d['area_code'];
    ?>
    <div class="diario-bloque">
      <div class="diario-fecha">
        <?= $h(fecha_mil($d['fecha'])) ?>
        <span style="font-weight:normal;font-size:8.5pt;margin-left:8px;"><?= $h($areaNom) ?></span>
      </div>
      <div class="diario-detalle"><?= $h($d['detalle']) ?></div>
    </div>
    <?php endforeach; ?>
  <?php else: ?>
    <p class="sin-datos">Sin registros de diario para este período.</p>
  <?php endif; ?>

  <!-- Lugar y fecha debajo del diario -->
  <div class="lugar-fecha"><?= $h($lugarFecha) ?></div>

  <?php endif; // fin bloque resumen ?>

  <!-- ══════════════════════════════════════════
       FIRMAS
       ══════════════════════════════════════════ -->
  <?php if ($pdfFirm1 !== '' || $pdfFirm2 !== ''): ?>
  <div class="firmas-wrap">
    <?php if ($pdfFirm1 !== ''): ?>
    <div class="firma-bloque">
      <div class="firma-espacio"></div>
      <div class="firma-linea"><?= $h($pdfFirm1) ?></div>
      <?php if ($pdfFunc1 !== ''): ?>
        <div class="firma-cargo"><?= $h($pdfFunc1) ?></div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if ($pdfFirm2 !== ''): ?>
    <div class="firma-bloque">
      <div class="firma-espacio"></div>
      <div class="firma-linea"><?= $h($pdfFirm2) ?></div>
      <?php if ($pdfFunc2 !== ''): ?>
        <div class="firma-cargo"><?= $h($pdfFunc2) ?></div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <script>window.print();</script>
</body>
</html>
<?php
    exit;
}

// POST — devuelve JSON
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $action = (string)($_POST['action'] ?? '');
        if ($unidad_id <= 0) throw new RuntimeException('Unidad inválida.');

        /* ── CREAR TAREA ── */
        if ($action === 'crear_tarea') {
            $area_code   = $esAdmin ? trim((string)($_POST['area_code'] ?? '')) : $userAreaCode;
            $titulo      = trim((string)($_POST['titulo'] ?? ''));
            $descripcion = trim((string)($_POST['descripcion'] ?? ''));
            $prioridad   = (string)($_POST['prioridad'] ?? 'MEDIA');
            $inicio      = trim((string)($_POST['inicio'] ?? ''));
            $fin         = trim((string)($_POST['fin'] ?? ''));
            $venc        = trim((string)($_POST['fecha_vencimiento'] ?? ''));
            $asignado_a  = trim((string)($_POST['asignado_a'] ?? ''));
            if ($area_code===''||$titulo==='') throw new RuntimeException('Área y título son obligatorios.');
            if (!in_array($prioridad,['BAJA','MEDIA','ALTA'],true)) $prioridad='MEDIA';
            $pdo->prepare("INSERT INTO calendario_tareas(unidad_id,area_code,titulo,descripcion,estado,prioridad,inicio,fin,fecha_vencimiento,asignado_a,creado_por,creado_por_id) VALUES(?,?,?,?,'POR_HACER',?,?,?,?,?,?,?)")->execute([
                $unidad_id,$area_code,$titulo,
                $descripcion!==''?$descripcion:null,$prioridad,
                $inicio!==''?$inicio:null,$fin!==''?$fin:null,$venc!==''?$venc:null,
                $asignado_a!==''?$asignado_a:null,$creado_por!==''?$creado_por:null,$creado_por_id?:null
            ]);
            echo json_encode(['ok'=>true,'msg'=>'Tarea creada correctamente.']); exit;
        }

        /* ── EDITAR TAREA ── */
        if ($action === 'editar_tarea') {
            $id          = (int)($_POST['id'] ?? 0);
            $titulo      = trim((string)($_POST['titulo'] ?? ''));
            $descripcion = trim((string)($_POST['descripcion'] ?? ''));
            $prioridad   = (string)($_POST['prioridad'] ?? 'MEDIA');
            $estado      = (string)($_POST['estado'] ?? 'POR_HACER');
            $inicio      = trim((string)($_POST['inicio'] ?? ''));
            $fin         = trim((string)($_POST['fin'] ?? ''));
            $venc        = trim((string)($_POST['fecha_vencimiento'] ?? ''));
            $asignado_a  = trim((string)($_POST['asignado_a'] ?? ''));
            if ($titulo === '') throw new RuntimeException('El título es obligatorio.');
            if (!in_array($prioridad,['BAJA','MEDIA','ALTA'],true)) $prioridad='MEDIA';
            if (!in_array($estado,['POR_HACER','EN_PROCESO','REALIZADA'],true)) $estado='POR_HACER';
            $sql = "UPDATE calendario_tareas SET titulo=?,descripcion=?,prioridad=?,estado=?,inicio=?,fin=?,fecha_vencimiento=?,asignado_a=?,updated_at=NOW()
                    WHERE id=? AND unidad_id=?";
            $p = [
                $titulo,
                $descripcion!==''?$descripcion:null,$prioridad,$estado,
                $inicio!==''?$inicio:null,$fin!==''?$fin:null,$venc!==''?$venc:null,
                $asignado_a!==''?$asignado_a:null,$id,$unidad_id
            ];
            // No-admin solo puede editar sus propias tareas de área
            if (!$esAdmin && $userAreaCode!=='') { $sql.=' AND area_code=?'; $p[]=$userAreaCode; }
            $pdo->prepare($sql)->execute($p);
            echo json_encode(['ok'=>true,'msg'=>'Tarea actualizada.']); exit;
        }

        /* ── ELIMINAR TAREA ── */
        if ($action === 'eliminar_tarea') {
            $id  = (int)($_POST['id'] ?? 0);
            $sql = "DELETE FROM calendario_tareas WHERE id=? AND unidad_id=?";
            $p   = [$id, $unidad_id];
            if (!$esAdmin && $userAreaCode!=='') { $sql.=' AND area_code=?'; $p[]=$userAreaCode; }
            $pdo->prepare($sql)->execute($p);
            echo json_encode(['ok'=>true,'msg'=>'Tarea eliminada.']); exit;
        }

        /* ── CAMBIAR ESTADO ── */
        if ($action === 'cambiar_estado') {
            $id     = (int)($_POST['id'] ?? 0);
            $estado = (string)($_POST['estado'] ?? 'POR_HACER');
            if (!in_array($estado,['POR_HACER','EN_PROCESO','REALIZADA'],true)) $estado='POR_HACER';
            $sql='UPDATE calendario_tareas SET estado=? WHERE id=? AND unidad_id=?'; $p=[$estado,$id,$unidad_id];
            if (!$esAdmin && $userAreaCode!=='') { $sql.=' AND area_code=?'; $p[]=$userAreaCode; }
            $pdo->prepare($sql)->execute($p);
            echo json_encode(['ok'=>true,'msg'=>'Estado actualizado.']); exit;
        }

        /* ── CREAR DIARIO ── */
        if ($action === 'crear_diario') {
            $area_code = $esAdmin ? trim((string)($_POST['area_code'] ?? '')) : $userAreaCode;
            $fecha     = trim((string)($_POST['fecha']   ?? ''));
            $detalle   = trim((string)($_POST['detalle'] ?? ''));
            if ($area_code===''||$fecha===''||$detalle==='') throw new RuntimeException('Área, fecha y detalle son obligatorios.');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$fecha)) throw new RuntimeException('Fecha inválida.');
            $pdo->prepare("INSERT INTO calendario_diario(unidad_id,area_code,fecha,detalle,creado_por,creado_por_id) VALUES(?,?,?,?,?,?)")->execute([
                $unidad_id,$area_code,$fecha,$detalle,
                $creado_por!==''?$creado_por:null,$creado_por_id?:null
            ]);
            echo json_encode(['ok'=>true,'msg'=>'Registro de diario guardado.']); exit;
        }

        /* ── EDITAR DIARIO ── */
        if ($action === 'editar_diario') {
            $id      = (int)($_POST['id'] ?? 0);
            $fecha   = trim((string)($_POST['fecha']   ?? ''));
            $detalle = trim((string)($_POST['detalle'] ?? ''));
            if ($fecha===''||$detalle==='') throw new RuntimeException('Fecha y detalle son obligatorios.');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$fecha)) throw new RuntimeException('Fecha inválida.');
            $sql = "UPDATE calendario_diario SET fecha=?,detalle=?,updated_at=NOW() WHERE id=? AND unidad_id=?";
            $p   = [$fecha,$detalle,$id,$unidad_id];
            if (!$esAdmin && $userAreaCode!=='') { $sql.=' AND area_code=?'; $p[]=$userAreaCode; }
            $pdo->prepare($sql)->execute($p);
            echo json_encode(['ok'=>true,'msg'=>'Registro actualizado.']); exit;
        }

        /* ── ELIMINAR DIARIO ── */
        if ($action === 'eliminar_diario') {
            $id  = (int)($_POST['id'] ?? 0);
            $sql = "DELETE FROM calendario_diario WHERE id=? AND unidad_id=?";
            $p   = [$id, $unidad_id];
            if (!$esAdmin && $userAreaCode!=='') { $sql.=' AND area_code=?'; $p[]=$userAreaCode; }
            $pdo->prepare($sql)->execute($p);
            echo json_encode(['ok'=>true,'msg'=>'Registro eliminado.']); exit;
        }

        /* ── GET TAREA (para modal de edición) ── */
        if ($action === 'get_tarea') {
            $id  = (int)($_POST['id'] ?? 0);
            $sql = "SELECT * FROM calendario_tareas WHERE id=? AND unidad_id=?";
            $p   = [$id, $unidad_id];
            if (!$esAdmin && $userAreaCode!=='') { $sql.=' AND area_code=?'; $p[]=$userAreaCode; }
            $st  = $pdo->prepare($sql);
            $st->execute($p);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new RuntimeException('Tarea no encontrada.');
            echo json_encode(['ok'=>true,'data'=>$row]); exit;
        }

        /* ── GET DIARIO (para modal de edición) ── */
        if ($action === 'get_diario') {
            $id  = (int)($_POST['id'] ?? 0);
            $sql = "SELECT * FROM calendario_diario WHERE id=? AND unidad_id=?";
            $p   = [$id, $unidad_id];
            if (!$esAdmin && $userAreaCode!=='') { $sql.=' AND area_code=?'; $p[]=$userAreaCode; }
            $st  = $pdo->prepare($sql);
            $st->execute($p);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new RuntimeException('Registro no encontrado.');
            echo json_encode(['ok'=>true,'data'=>$row]); exit;
        }

        echo json_encode(['ok'=>false,'msg'=>'Acción desconocida.']); exit;
    } catch (Throwable $ex) {
        echo json_encode(['ok'=>false,'msg'=>$ex->getMessage()]); exit;
    }
}

// ÁREAS
$areas=[]; $areaNames=[];
try {
    $st=$pdo->prepare("SELECT codigo,nombre FROM destino WHERE unidad_id=? AND activo=1 ORDER BY codigo ASC");
    $st->execute([$unidad_id]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $a) $areaNames[(string)$a['codigo']]=(string)$a['nombre'];
    if ($esAdmin) $areas=array_map(fn($k,$v)=>['codigo'=>$k,'nombre'=>$v], array_keys($areaNames), $areaNames);
} catch (Throwable $ex) {}

function area_label(?string $code): string {
    global $areaNames;
    $code=strtoupper(trim((string)$code));
    return $code==='' ? '' : (isset($areaNames[$code]) ? $code.' · '.$areaNames[$code] : $code);
}

// PERSONAL DE LA UNIDAD (para selectores en modales)
$personalLista = [];
try {
    $st = $pdo->prepare("
        SELECT id,
               CONCAT_WS(' ', grado, arma, apellido_nombre) AS nombre_completo,
               apellido_nombre, grado, arma, destino_id
        FROM personal_unidad
        WHERE unidad_id = ?
        ORDER BY jerarquia DESC, grado, apellido_nombre ASC
    ");
    $st->execute([$unidad_id]);
    $personalLista = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $ex) {}

$personalJson = json_encode(
    array_map(fn($p) => [
        'id'     => (int)$p['id'],
        'nombre' => trim((string)$p['nombre_completo']),
    ], $personalLista),
    JSON_UNESCAPED_UNICODE
);

// CONSULTAS
$tareasMes=[]; $diarioMes=[]; $tareasDia=[]; $diarioDia=[];
$stats=['por_hacer'=>0,'en_proceso'=>0,'realizada'=>0];
$mapT=[]; $mapD=[];
$wtf = $area!=='ALL' ? " AND area_code=? " : "";

try {
    $pt=[$unidad_id,$monthStart->format('Y-m-d'),$monthEnd->format('Y-m-d'),$monthStart->format('Y-m-d'),$monthEnd->format('Y-m-d')];
    if($area!=='ALL')$pt[]=$area;
    $st=$pdo->prepare("SELECT * FROM calendario_tareas WHERE unidad_id=? AND ((inicio IS NOT NULL AND DATE(inicio) BETWEEN ? AND ?) OR (fecha_vencimiento IS NOT NULL AND fecha_vencimiento BETWEEN ? AND ?)) $wtf ORDER BY COALESCE(inicio,CONCAT(fecha_vencimiento,' 00:00:00')) ASC,id DESC");
    $st->execute($pt); $tareasMes=$st->fetchAll(PDO::FETCH_ASSOC);

    foreach ($tareasMes as $t) {
        $es=(string)($t['estado']??'');
        if($es==='POR_HACER')$stats['por_hacer']++;
        if($es==='EN_PROCESO')$stats['en_proceso']++;
        if($es==='REALIZADA')$stats['realizada']++;
        $dia=!empty($t['inicio'])?substr((string)$t['inicio'],0,10):(string)($t['fecha_vencimiento']??'');
        if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$dia))$mapT[$dia]=($mapT[$dia]??0)+1;
    }

    $pd=[$unidad_id,$monthStart->format('Y-m-d'),$monthEnd->format('Y-m-d')];
    if($area!=='ALL')$pd[]=$area;
    $st=$pdo->prepare("SELECT * FROM calendario_diario WHERE unidad_id=? AND fecha BETWEEN ? AND ? $wtf ORDER BY fecha DESC,id DESC");
    $st->execute($pd); $diarioMes=$st->fetchAll(PDO::FETCH_ASSOC);
    foreach($diarioMes as $d){ $dia=(string)($d['fecha']??''); if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$dia))$mapD[$dia]=($mapD[$dia]??0)+1; }

    $ptd=[$unidad_id]; if($area!=='ALL')$ptd[]=$area; $ptd[]=$selectedDay; $ptd[]=$selectedDay;
    $st=$pdo->prepare("SELECT * FROM calendario_tareas WHERE unidad_id=? $wtf AND ((inicio IS NOT NULL AND DATE(inicio)=?) OR (fecha_vencimiento IS NOT NULL AND fecha_vencimiento=?)) ORDER BY COALESCE(inicio,CONCAT(fecha_vencimiento,' 00:00:00')) ASC");
    $st->execute($ptd); $tareasDia=$st->fetchAll(PDO::FETCH_ASSOC);

    $pdd=[$unidad_id]; if($area!=='ALL')$pdd[]=$area; $pdd[]=$selectedDay;
    $st=$pdo->prepare("SELECT * FROM calendario_diario WHERE unidad_id=? $wtf AND fecha=? ORDER BY id DESC");
    $st->execute($pdd); $diarioDia=$st->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $ex) { $err=$err?:$ex->getMessage(); }

$areaLabel = $esAdmin
    ? ($area==='ALL' ? 'Todas las áreas' : area_label($area))
    : ($userAreaCode!=='' ? area_label($userAreaCode) : 'Sin área asignada');

// Para JS: opciones de área en el modal
$areasJson = json_encode($areas, JSON_UNESCAPED_UNICODE);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Calendario · <?= e($NOMBRE) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="icon" href="<?= e($ESCUDO) ?>">
  <link rel="stylesheet" href="<?= e($ASSETS_URL) ?>/css/theme-602.css">
  <style>
    :root {
      --c-bg:     #020617;
      --c-surf:   rgba(15,17,23,.94);
      --c-surf2:  rgba(15,23,42,.96);
      --c-border: rgba(148,163,184,.35);
      --c-text:   #e5e7eb;
      --c-muted:  #9ca3af;
      --c-sub:    #cbd5f5;
      --c-green:  #22c55e;
      --c-blue:   #0ea5e9;
    }
    html,body{height:100%;}
    body{margin:0;color:var(--c-text);background:var(--c-bg);font-family:system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,sans-serif;}

    .page-bg{position:fixed;inset:0;z-index:-2;pointer-events:none;
      background:linear-gradient(160deg,rgba(0,0,0,.88) 0%,rgba(0,0,0,.68) 55%,rgba(0,0,0,.88) 100%),
                 url("<?= e($IMG_BG) ?>") center/cover no-repeat;
      background-attachment:fixed,fixed;}

    .container-main{max-width:1400px;margin:auto;}
    .page-wrap{padding:18px;}

    /* Header */
    .brand-hero{padding:10px 0;border-bottom:1px solid rgba(148,163,184,.22);background:rgba(2,6,23,.55);backdrop-filter:blur(6px);}
    .hero-inner{display:flex;align-items:center;gap:14px;padding:8px 16px;}
    .brand-logo{width:52px;height:52px;object-fit:contain;transition:transform .18s;}
    .brand-logo:hover{transform:scale(1.06);}
    .brand-title{font-weight:900;font-size:1.05rem;line-height:1.1;}
    .brand-sub{font-size:.82rem;color:var(--c-muted);}
    .header-actions{margin-left:auto;display:flex;gap:8px;align-items:center;}

    /* Panel */
    .panel{background:var(--c-surf);border:1px solid var(--c-border);border-radius:18px;padding:16px 18px 18px;
      box-shadow:0 18px 40px rgba(0,0,0,.55),inset 0 1px 0 rgba(255,255,255,.04);backdrop-filter:blur(8px);}
    .panel-title{font-size:1rem;font-weight:900;margin-bottom:4px;display:flex;align-items:center;gap:.5rem;}
    .panel-sub{font-size:.85rem;color:var(--c-sub);margin-bottom:12px;}

    /* Badges / chips */
    .area-badge{display:inline-flex;align-items:center;gap:.4rem;padding:.25rem .7rem;border-radius:999px;font-size:.8rem;font-weight:900;
      background:rgba(34,197,94,.15);border:1px solid rgba(34,197,94,.45);color:#86efac;}
    .chip{display:inline-flex;align-items:center;gap:.4rem;border:1px solid rgba(148,163,184,.28);border-radius:999px;
      padding:.22rem .6rem;font-size:.8rem;background:rgba(2,6,23,.55);color:#dbeafe;}
    .estado-badge{display:inline-block;padding:.18rem .55rem;border-radius:6px;font-size:.72rem;font-weight:900;letter-spacing:.04em;}
    .estado-POR_HACER{background:rgba(245,158,11,.18);color:#fcd34d;border:1px solid rgba(245,158,11,.35);}
    .estado-EN_PROCESO{background:rgba(14,165,233,.18);color:#7dd3fc;border:1px solid rgba(14,165,233,.35);}
    .estado-REALIZADA{background:rgba(34,197,94,.18);color:#86efac;border:1px solid rgba(34,197,94,.35);}

    /* Botones */
    .btn-soft{border:1px solid rgba(148,163,184,.28);background:rgba(255,255,255,.06);color:var(--c-text);font-weight:800;border-radius:12px;transition:background .15s;}
    .btn-soft:hover{background:rgba(255,255,255,.10);color:#fff;}
    .btn-soft.active{border-color:rgba(34,197,94,.55);background:rgba(34,197,94,.10);color:#86efac;}
    .btn-pill{display:inline-flex;align-items:center;gap:.4rem;padding:.45rem .95rem;border-radius:999px;border:none;
      font-size:.84rem;font-weight:900;text-decoration:none;cursor:pointer;
      background:var(--c-blue);color:#021827;box-shadow:0 6px 18px rgba(14,165,233,.45);transition:background .15s,transform .15s;}
    .btn-pill:hover{background:#38bdf8;transform:translateY(-1px);}
    .btn-pill.green{background:var(--c-green);color:#052e16;box-shadow:0 6px 18px rgba(34,197,94,.4);}
    .btn-pill.green:hover{background:#4ade80;}

    /* Inputs dark */
    .form-control,.form-select{background:rgba(255,255,255,.06);border:1px solid rgba(148,163,184,.28);color:var(--c-text);}
    .form-control:focus,.form-select:focus{background:rgba(255,255,255,.09);color:#fff;border-color:rgba(120,170,255,.55);box-shadow:0 0 0 .18rem rgba(90,140,255,.15);}
    .form-select option{background:#0f172a;color:var(--c-text);}
    .form-label{font-size:.82rem;color:var(--c-muted);margin-bottom:.3rem;}

    /* Tabla */
    /* Tabla — colores oscuros explícitos */
    .table{color:#e5e7eb !important;font-size:.84rem;}
    .table th{
      color:#93c5fd !important;
      border-color:rgba(148,163,184,.25) !important;
      font-size:.75rem;font-weight:900;letter-spacing:.05em;text-transform:uppercase;
      background:rgba(2,6,23,.75) !important;
      padding:10px 12px;
    }
    .table td{
      border-color:rgba(148,163,184,.12) !important;
      vertical-align:middle;
      color:#e5e7eb !important;
      background:rgba(15,23,42,.55) !important;
      padding:9px 12px;
    }
    .table tbody tr:hover td{background:rgba(255,255,255,.05) !important;}
    .table-responsive{border-radius:14px;overflow:hidden;border:1px solid rgba(148,163,184,.18);}
    .estado-select{
      background:rgba(2,6,23,.8) !important;
      border:1px solid rgba(148,163,184,.3) !important;
      color:#e5e7eb !important;
      font-size:.75rem;font-weight:700;
    }
    .estado-select option{background:#0f172a;}

    /* Calendario */
    .cal-nav{display:flex;align-items:center;gap:10px;margin-bottom:14px;}
    .cal-nav .month-label{font-size:1.05rem;font-weight:900;min-width:160px;text-align:center;}
    .cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:6px;}
    .dow{font-size:.72rem;letter-spacing:.08em;text-transform:uppercase;color:rgba(200,215,255,.5);padding:2px 0 6px;text-align:center;}
    .cal-day{background:rgba(2,6,23,.55);border:1px solid rgba(148,163,184,.2);border-radius:12px;min-height:82px;
      padding:8px 8px 6px;transition:transform .12s,border-color .12s,background .12s;
      cursor:pointer;text-decoration:none;color:inherit;display:block;}
    .cal-day:hover{transform:translateY(-2px);border-color:rgba(120,170,255,.4);background:rgba(255,255,255,.05);color:inherit;}
    .cal-day.off{opacity:.25;pointer-events:none;}
    .cal-day.sel{outline:2px solid rgba(34,197,94,.6);background:rgba(34,197,94,.07);}
    .cal-day.today-mark{border-color:rgba(14,165,233,.5);}
    .dnum{font-weight:900;font-size:.92rem;}
    .cal-dots{display:flex;gap:4px;margin-top:6px;flex-wrap:wrap;}
    .dot-t{width:7px;height:7px;border-radius:50%;background:#38bdf8;}
    .dot-d{width:7px;height:7px;border-radius:50%;background:#22c55e;}
    .cal-mini{margin-top:5px;font-size:.7rem;color:rgba(200,215,255,.75);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}

    /* Panel lateral del día */
    .day-panel{background:var(--c-surf2);border:1px solid rgba(148,163,184,.3);border-radius:16px;padding:14px 16px;}
    .day-panel-title{font-size:.95rem;font-weight:900;margin-bottom:3px;}
    .day-panel-sub{font-size:.8rem;color:var(--c-muted);margin-bottom:12px;}

    /* Modales dark */
    .modal-content{background:rgba(10,14,26,.97);border:1px solid rgba(148,163,184,.3);border-radius:18px;color:var(--c-text);}
    .modal-header{border-bottom:1px solid rgba(148,163,184,.2);padding:14px 18px;}
    .modal-footer{border-top:1px solid rgba(148,163,184,.2);padding:12px 18px;}
    .modal-title{font-weight:900;font-size:1rem;}
    .btn-close{filter:invert(1) brightness(1.5);}
    .modal-backdrop{backdrop-filter:blur(4px);}

    /* Toast de notificación */
    #toastWrap{position:fixed;bottom:24px;right:24px;z-index:9999;}
    .toast-msg{display:flex;align-items:center;gap:.6rem;padding:.7rem 1rem;border-radius:12px;font-size:.86rem;font-weight:700;
      box-shadow:0 8px 28px rgba(0,0,0,.6);margin-top:8px;animation:slideIn .25s ease;min-width:240px;}
    .toast-msg.ok{background:rgba(34,197,94,.2);border:1px solid rgba(34,197,94,.5);color:#86efac;}
    .toast-msg.err{background:rgba(239,68,68,.2);border:1px solid rgba(239,68,68,.5);color:#fca5a5;}
    @keyframes slideIn{from{opacity:0;transform:translateX(30px)}to{opacity:1;transform:translateX(0)}}

    .small-muted{font-size:.82rem;color:var(--c-muted);}
    .divider{border-color:rgba(148,163,184,.15);}
  </style>
</head>
<body>
<div class="page-bg"></div>

<!-- TOAST -->
<div id="toastWrap"></div>

<!-- HEADER -->
<header class="brand-hero">
  <div class="hero-inner container-main">
    <img class="brand-logo" src="<?= e($ESCUDO) ?>" alt="Escudo"
         onerror="this.src='<?= e($ASSETS_URL) ?>/img/EA.png'">
    <div>
      <div class="brand-title"><?= e($NOMBRE) ?> · Calendario</div>
      <div class="brand-sub">"<?= e($LEYENDA) ?>"</div>
    </div>
    <div class="header-actions">
      <span class="area-badge"><i class="bi bi-geo-alt-fill"></i> <?= e($areaLabel) ?></span>
      <a class="btn btn-success btn-sm fw-bold px-3" href="<?= e(url_public('/inicio.php')) ?>">
        <i class="bi bi-arrow-left-circle me-1"></i> Volver
      </a>
    </div>
  </div>
</header>

<div class="page-wrap"><div class="container-main">

  <!-- BARRA SUPERIOR -->
  <div class="panel mb-3">
    <form class="row g-2 align-items-end" method="get" action="" id="filterForm">
      <?php if ($esAdmin): ?>
      <div class="col-12 col-sm-6 col-md-3 col-xl-2">
        <label class="form-label">Área</label>
        <select class="form-select form-select-sm" name="area" onchange="this.form.submit()">
          <option value="ALL" <?= $area==='ALL'?'selected':'' ?>>Todas</option>
          <?php foreach ($areas as $a): $c=(string)$a['codigo']; ?>
            <option value="<?= e($c) ?>" <?= $area===$c?'selected':'' ?>><?= e($c) ?> · <?= e($a['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <div class="col-6 col-md-2 col-xl-2">
        <label class="form-label">Mes</label>
        <input class="form-control form-control-sm" name="ym" value="<?= e($ym) ?>" placeholder="YYYY-MM">
      </div>
      <div class="col-6 col-md-2 col-xl-2 d-grid">
        <label class="form-label">&nbsp;</label>
        <button class="btn btn-soft btn-sm" type="submit"><i class="bi bi-funnel me-1"></i> Aplicar</button>
      </div>
      <div class="col-12 col-xl-auto ms-xl-auto d-flex flex-wrap gap-2 align-items-end">
        <span class="chip"><i class="bi bi-hourglass-split"></i> Por hacer: <b><?= (int)$stats['por_hacer'] ?></b></span>
        <span class="chip"><i class="bi bi-play-circle"></i> En proceso: <b><?= (int)$stats['en_proceso'] ?></b></span>
        <span class="chip"><i class="bi bi-check-circle"></i> Realizadas: <b><?= (int)$stats['realizada'] ?></b></span>
        <button type="button" class="btn-pill ms-2" onclick="openModalPdf()">
          <i class="bi bi-printer"></i> Exportar PDF
        </button>
      </div>
    </form>
  </div>

  <!-- LAYOUT PRINCIPAL -->
  <div class="row g-3">

    <!-- COLUMNA CALENDARIO -->
    <div class="col-12 col-xl-8">
      <div class="panel">
        <!-- Navegación mes -->
        <div class="cal-nav">
          <a class="btn btn-soft btn-sm px-2"
             href="<?= e(url_public('/calendario.php?'.build_qs(['ym'=>$prevYm,'area'=>$area,'day'=>$prevYm.'-01']))) ?>">
            <i class="bi bi-chevron-left"></i>
          </a>
          <div class="month-label"><?= e(month_label_es($ym)) ?></div>
          <a class="btn btn-soft btn-sm px-2"
             href="<?= e(url_public('/calendario.php?'.build_qs(['ym'=>$nextYm,'area'=>$area,'day'=>$nextYm.'-01']))) ?>">
            <i class="bi bi-chevron-right"></i>
          </a>
          <div class="ms-auto d-flex gap-2">
            <button class="btn-pill green" onclick="openModalTarea('<?= e($selectedDay) ?>')">
              <i class="bi bi-plus-circle"></i> Nueva tarea
            </button>
            <button class="btn-pill" onclick="openModalDiario('<?= e($selectedDay) ?>')">
              <i class="bi bi-journal-plus"></i> Diario
            </button>
          </div>
        </div>

        <!-- Grilla -->
        <div class="cal-grid">
          <?php
          foreach (['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'] as $dw)
              echo '<div class="dow">'.$dw.'</div>';

          $firstDow    = (int)$monthStart->format('N');
          $daysInMonth = (int)$monthEnd->format('j');
          $prevDays    = (int)$monthStart->modify('-1 month')->modify('last day of this month')->format('j');
          $todayStr    = $today->format('Y-m-d');

          for ($i=$firstDow-1; $i>0; $i--)
              echo '<div class="cal-day off"><div class="dnum">'.($prevDays-$i+1).'</div></div>';

          for ($d=1; $d<=$daysInMonth; $d++) {
              $date  = $ym.'-'.str_pad((string)$d,2,'0',STR_PAD_LEFT);
              $isSel = ($date===$selectedDay);
              $isTod = ($date===$todayStr);
              $ctT   = $mapT[$date]??0;
              $ctD   = $mapD[$date]??0;

              $cls = 'cal-day';
              if ($isSel) $cls.=' sel';
              if ($isTod) $cls.=' today-mark';

              $href = e(url_public('/calendario.php?'.build_qs(['ym'=>$ym,'area'=>$area,'day'=>$date])));
              echo '<a class="'.$cls.'" href="'.$href.'">';
              echo '<div class="dnum">'.$d.($isTod?' <span style="font-size:.55rem;color:var(--c-blue);">hoy</span>':'').'</div>';
              echo '<div class="cal-dots">';
              if ($ctT>0) echo '<span class="dot-t" title="'.(int)$ctT.' tarea(s)"></span>';
              if ($ctD>0) echo '<span class="dot-d" title="'.(int)$ctD.' diario"></span>';
              echo '</div>';
              foreach ($tareasMes as $t) {
                  $key=!empty($t['inicio'])?substr((string)$t['inicio'],0,10):(string)($t['fecha_vencimiento']??'');
                  if ($key===$date){echo '<div class="cal-mini">'.e($t['titulo']).'</div>';break;}
              }
              echo '</a>';
          }

          $tail=(7-(($firstDow-1+$daysInMonth)%7))%7;
          for ($i=1;$i<=$tail;$i++)
              echo '<div class="cal-day off"><div class="dnum">'.$i.'</div></div>';
          ?>
        </div>
      </div>

      <!-- LISTA DE TAREAS DEL MES -->
      <div class="panel mt-3">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <div>
            <div class="panel-title mb-0"><i class="bi bi-list-check"></i> Tareas — <?= e(month_label_es($ym)) ?></div>
            <div class="small-muted">Área: <?= e($areaLabel) ?></div>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0" id="tablaTareas">
            <thead>
              <tr>
                <th>Área</th><th>Título</th><th>Estado</th><th>Prior.</th><th>Venc.</th><th>Asignado</th><th></th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$tareasMes): ?>
                <tr><td colspan="7" class="text-center small-muted py-4">Sin tareas para este mes.</td></tr>
              <?php else: foreach ($tareasMes as $t): ?>
                <tr id="row-<?= (int)$t['id'] ?>">
                  <td><span style="font-size:.75rem;font-weight:700;color:var(--c-sub);"><?= e($t['area_code']) ?></span></td>
                  <td>
                    <div style="font-weight:700;"><?= e($t['titulo']) ?></div>
                    <?php if (!empty($t['descripcion'])): ?>
                      <div class="small-muted"><?= nl2br(e($t['descripcion'])) ?></div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <select class="form-select form-select-sm estado-select"
                            data-id="<?= (int)$t['id'] ?>" style="width:130px;font-size:.75rem;">
                      <option value="POR_HACER"  <?= $t['estado']==='POR_HACER'?'selected':'' ?>>POR_HACER</option>
                      <option value="EN_PROCESO" <?= $t['estado']==='EN_PROCESO'?'selected':'' ?>>EN_PROCESO</option>
                      <option value="REALIZADA"  <?= $t['estado']==='REALIZADA'?'selected':'' ?>>REALIZADA</option>
                    </select>
                  </td>
                  <td><span class="small-muted"><?= e($t['prioridad']) ?></span></td>
                  <td><span class="small-muted"><?= e((string)$t['fecha_vencimiento']) ?></span></td>
                  <td><span class="small-muted"><?= e((string)$t['asignado_a']) ?></span></td>
                  <td>
                    <div class="d-flex gap-1">
                      <button class="btn btn-soft btn-sm px-2 save-estado" data-id="<?= (int)$t['id'] ?>" title="Guardar estado">
                        <i class="bi bi-check-lg"></i>
                      </button>
                      <button class="btn btn-soft btn-sm px-2 btn-editar-tarea"
                              data-id="<?= (int)$t['id'] ?>" title="Editar tarea"
                              style="border-color:rgba(14,165,233,.4);color:#7dd3fc;">
                        <i class="bi bi-pencil"></i>
                      </button>
                      <button class="btn btn-soft btn-sm px-2 btn-eliminar-tarea"
                              data-id="<?= (int)$t['id'] ?>" data-titulo="<?= e($t['titulo']) ?>" title="Eliminar tarea"
                              style="border-color:rgba(239,68,68,.4);color:#fca5a5;">
                        <i class="bi bi-trash3"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- PANEL LATERAL DÍA -->
    <div class="col-12 col-xl-4">
      <div class="day-panel sticky-top" style="top:18px;">
        <div class="d-flex align-items-start justify-content-between mb-3">
          <div>
            <div class="day-panel-title"><i class="bi bi-calendar-event me-1"></i> <?= e($selectedDay) ?></div>
            <div class="day-panel-sub">Área: <?= e($areaLabel) ?></div>
          </div>
          <div class="d-flex gap-2">
            <button class="btn-pill green" style="font-size:.76rem;padding:.3rem .7rem;"
                    onclick="openModalTarea('<?= e($selectedDay) ?>')">
              <i class="bi bi-plus"></i> Tarea
            </button>
            <button class="btn-pill" style="font-size:.76rem;padding:.3rem .7rem;"
                    onclick="openModalDiario('<?= e($selectedDay) ?>')">
              <i class="bi bi-journal-plus"></i>
            </button>
          </div>
        </div>

        <hr class="divider">

        <div class="fw-bold mb-2" style="font-size:.85rem;"><i class="bi bi-check2-square me-1 text-info"></i> Tareas del día</div>
        <?php if (!$tareasDia): ?>
          <div class="small-muted mb-3">Sin tareas.</div>
        <?php else: ?>
          <?php foreach ($tareasDia as $t): ?>
            <div style="background:rgba(255,255,255,.04);border:1px solid rgba(148,163,184,.18);border-radius:10px;padding:8px 10px;margin-bottom:6px;">
              <div class="d-flex justify-content-between align-items-start gap-2">
                <div style="font-weight:700;font-size:.84rem;"><?= e($t['titulo']) ?></div>
                <div class="d-flex gap-1 flex-shrink-0">
                  <button class="btn btn-soft btn-sm px-1 py-0 btn-editar-tarea"
                          data-id="<?= (int)$t['id'] ?>" title="Editar"
                          style="font-size:.7rem;border-color:rgba(14,165,233,.4);color:#7dd3fc;">
                    <i class="bi bi-pencil"></i>
                  </button>
                  <button class="btn btn-soft btn-sm px-1 py-0 btn-eliminar-tarea"
                          data-id="<?= (int)$t['id'] ?>" data-titulo="<?= e($t['titulo']) ?>" title="Eliminar"
                          style="font-size:.7rem;border-color:rgba(239,68,68,.4);color:#fca5a5;">
                    <i class="bi bi-trash3"></i>
                  </button>
                </div>
              </div>
              <?php if(!empty($t['descripcion'])): ?>
                <div class="small-muted"><?= nl2br(e($t['descripcion'])) ?></div>
              <?php endif; ?>
              <div class="d-flex gap-2 mt-1 flex-wrap align-items-center">
                <span class="estado-badge estado-<?= e($t['estado']) ?>"><?= e($t['estado']) ?></span>
                <span class="small-muted"><?= e($t['prioridad']) ?></span>
                <?php if (!empty($t['fecha_vencimiento'])): ?>
                  <span class="small-muted"><i class="bi bi-calendar2"></i> <?= e((string)$t['fecha_vencimiento']) ?></span>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
          <div class="mb-3"></div>
        <?php endif; ?>

        <hr class="divider">

        <div class="fw-bold mb-2" style="font-size:.85rem;"><i class="bi bi-journal-text me-1 text-success"></i> Diario del día</div>
        <?php if (!$diarioDia): ?>
          <div class="small-muted">Sin registros.</div>
        <?php else: ?>
          <?php foreach ($diarioDia as $d): ?>
            <div style="background:rgba(34,197,94,.05);border:1px solid rgba(34,197,94,.2);border-radius:10px;padding:8px 10px;margin-bottom:6px;font-size:.84rem;">
              <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                <span class="small-muted" style="font-size:.75rem;"><?= e($d['fecha']) ?></span>
                <div class="d-flex gap-1 flex-shrink-0">
                  <button class="btn btn-soft btn-sm px-1 py-0 btn-editar-diario"
                          data-id="<?= (int)$d['id'] ?>" title="Editar"
                          style="font-size:.7rem;border-color:rgba(14,165,233,.4);color:#7dd3fc;">
                    <i class="bi bi-pencil"></i>
                  </button>
                  <button class="btn btn-soft btn-sm px-1 py-0 btn-eliminar-diario"
                          data-id="<?= (int)$d['id'] ?>" title="Eliminar"
                          style="font-size:.7rem;border-color:rgba(239,68,68,.4);color:#fca5a5;">
                    <i class="bi bi-trash3"></i>
                  </button>
                </div>
              </div>
              <?= nl2br(e($d['detalle'])) ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <!-- DIARIO DEL MES (accordion) -->
        <div class="accordion mt-3" id="accDiario">
          <div class="accordion-item" style="background:transparent;border:1px solid rgba(148,163,184,.2);border-radius:10px;overflow:hidden;">
            <h2 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#colDiario"
                      style="background:rgba(2,6,23,.55);color:var(--c-text);font-size:.84rem;font-weight:900;padding:.55rem .75rem;">
                <i class="bi bi-journal-text me-2"></i> Diario del mes (<?= count($diarioMes) ?>)
              </button>
            </h2>
            <div id="colDiario" class="accordion-collapse collapse" data-bs-parent="#accDiario">
              <div class="accordion-body" style="background:rgba(15,23,42,.96);padding:10px;max-height:260px;overflow-y:auto;">
                <?php if (!$diarioMes): ?>
                  <div class="small-muted">Sin registros.</div>
                <?php else: foreach ($diarioMes as $d): ?>
                  <div style="border-bottom:1px solid rgba(148,163,184,.12);padding:6px 0;font-size:.82rem;">
                    <div class="d-flex justify-content-between align-items-center">
                      <div style="font-weight:700;color:var(--c-sub);">
                        <?= e($d['fecha']) ?>
                        <span style="opacity:.6;font-size:.75rem;"><?= e($d['area_code']) ?></span>
                      </div>
                      <div class="d-flex gap-1">
                        <button class="btn btn-soft btn-sm px-1 py-0 btn-editar-diario"
                                data-id="<?= (int)$d['id'] ?>" title="Editar"
                                style="font-size:.7rem;border-color:rgba(14,165,233,.4);color:#7dd3fc;">
                          <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-soft btn-sm px-1 py-0 btn-eliminar-diario"
                                data-id="<?= (int)$d['id'] ?>" title="Eliminar"
                                style="font-size:.7rem;border-color:rgba(239,68,68,.4);color:#fca5a5;">
                          <i class="bi bi-trash3"></i>
                        </button>
                      </div>
                    </div>
                    <div style="color:var(--c-text);margin-top:3px;"><?= nl2br(e($d['detalle'])) ?></div>
                  </div>
                <?php endforeach; endif; ?>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>

  </div><!-- /row -->
</div></div><!-- /container/page-wrap -->


<!-- ═══════════════════════════════════════════
     MODAL: Nueva Tarea
     ═══════════════════════════════════════════ -->
<div class="modal fade" id="modalTarea" tabindex="-1" aria-labelledby="modalTareaLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalTareaLabel"><i class="bi bi-check2-square me-2"></i> Nueva tarea</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="tareaAlert" class="alert d-none mb-3"></div>
        <form id="formTarea" class="row g-3">
          <input type="hidden" name="action" value="crear_tarea">

          <!-- Fila 1: Área + Título -->
          <?php if ($esAdmin): ?>
          <div class="col-md-3">
            <label class="form-label">Área</label>
            <select name="area_code" class="form-select" required>
              <?php foreach ($areas as $a): $c=(string)$a['codigo']; ?>
                <option value="<?= e($c) ?>" <?= $area===$c?'selected':'' ?>><?= e($c) ?> · <?= e($a['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-9">
          <?php else: ?>
          <input type="hidden" name="area_code" value="<?= e($userAreaCode) ?>">
          <div class="col-12">
          <?php endif; ?>
            <label class="form-label">Título <span class="text-danger">*</span></label>
            <input name="titulo" class="form-control" required maxlength="120" placeholder="Descripción de la tarea...">
          </div>

          <!-- Fila 2: Descripción -->
          <div class="col-12">
            <label class="form-label">Observaciones / Detalle</label>
            <textarea name="descripcion" class="form-control" rows="2" placeholder="Detalles, materiales, condiciones especiales..."></textarea>
          </div>

          <!-- Fila 3: Prioridad + Fechas -->
          <div class="col-md-2">
            <label class="form-label">Prioridad</label>
            <select name="prioridad" class="form-select">
              <option value="BAJA">BAJA</option>
              <option value="MEDIA" selected>MEDIA</option>
              <option value="ALTA">ALTA</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Inicio</label>
            <input name="inicio" id="tareaInicio" class="form-control" placeholder="2026-03-15 09:00">
          </div>
          <div class="col-md-3">
            <label class="form-label">Fin</label>
            <input name="fin" class="form-control" placeholder="2026-03-15 11:00">
          </div>
          <div class="col-md-4">
            <label class="form-label">Vencimiento</label>
            <input name="fecha_vencimiento" id="tareaVenc" class="form-control" placeholder="YYYY-MM-DD">
          </div>

          <!-- Fila 4: Personal -->
          <div class="col-md-4">
            <label class="form-label"><i class="bi bi-person-check me-1"></i> Asignado a (ejecuta)</label>
            <select name="asignado_a" id="selectAsignado" class="form-select">
              <option value="">— Sin asignar —</option>
              <?php foreach ($personalLista as $p): ?>
                <option value="<?= e(trim((string)$p['nombre_completo'])) ?>">
                  <?= e(trim((string)$p['nombre_completo'])) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label"><i class="bi bi-person-up me-1"></i> Ordenado por</label>
            <select name="ordenado_por" id="selectOrdenado" class="form-select">
              <option value="">— Opcional —</option>
              <?php foreach ($personalLista as $p): ?>
                <option value="<?= e(trim((string)$p['nombre_completo'])) ?>">
                  <?= e(trim((string)$p['nombre_completo'])) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label"><i class="bi bi-person-lines-fill me-1"></i> Participantes</label>
            <input name="participantes" class="form-control" placeholder="CB FLORES, CB PEREZ...">
          </div>

        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn-pill green" id="btnGuardarTarea">
          <i class="bi bi-check2-circle"></i> Guardar tarea
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════
     MODAL: Nuevo registro Diario
     ═══════════════════════════════════════════ -->
<div class="modal fade" id="modalDiario" tabindex="-1" aria-labelledby="modalDiarioLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalDiarioLabel"><i class="bi bi-journal-plus me-2"></i> Nuevo registro de diario</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="diarioAlert" class="alert d-none mb-3"></div>
        <form id="formDiario" class="row g-3">
          <input type="hidden" name="action" value="crear_diario">
          <?php if ($esAdmin): ?>
          <div class="col-md-6">
            <label class="form-label">Área</label>
            <select name="area_code" class="form-select" required>
              <?php foreach ($areas as $a): $c=(string)$a['codigo']; ?>
                <option value="<?= e($c) ?>" <?= $area===$c?'selected':'' ?>><?= e($c) ?> · <?= e($a['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php else: ?>
          <input type="hidden" name="area_code" value="<?= e($userAreaCode) ?>">
          <?php endif; ?>
          <div class="col-md-<?= $esAdmin?'6':'12' ?>">
            <label class="form-label">Fecha <span class="text-danger">*</span></label>
            <input name="fecha" id="diarioFecha" class="form-control" required placeholder="YYYY-MM-DD">
          </div>
          <div class="col-12">
            <label class="form-label">Detalle <span class="text-danger">*</span></label>
            <textarea name="detalle" class="form-control" rows="5" required placeholder="Actividades del día..."></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn-pill" id="btnGuardarDiario">
          <i class="bi bi-check2-circle"></i> Guardar registro
        </button>
      </div>
    </div>
  </div>
</div>



<!-- ═══════════════════════════════════════════
     MODAL: Editar Tarea
     ═══════════════════════════════════════════ -->
<div class="modal fade" id="modalEditTarea" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i> Editar tarea</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="editTareaAlert" class="alert d-none mb-3"></div>
        <form id="formEditTarea" class="row g-3">
          <input type="hidden" name="action" value="editar_tarea">
          <input type="hidden" name="id"     id="editTareaId">
          <div class="col-12">
            <label class="form-label">Título <span class="text-danger">*</span></label>
            <input name="titulo" id="editTareaTitulo" class="form-control" required maxlength="120">
          </div>
          <div class="col-12">
            <label class="form-label">Observaciones / Detalle</label>
            <textarea name="descripcion" id="editTareaDesc" class="form-control" rows="2"></textarea>
          </div>
          <div class="col-md-2">
            <label class="form-label">Prioridad</label>
            <select name="prioridad" id="editTareaPrio" class="form-select">
              <option value="BAJA">BAJA</option>
              <option value="MEDIA">MEDIA</option>
              <option value="ALTA">ALTA</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">Estado</label>
            <select name="estado" id="editTareaEstado" class="form-select">
              <option value="POR_HACER">PENDIENTE</option>
              <option value="EN_PROCESO">EN PROCESO</option>
              <option value="REALIZADA">REALIZADA</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Inicio</label>
            <input name="inicio" id="editTareaInicio" class="form-control" placeholder="2026-03-15 09:00">
          </div>
          <div class="col-md-4">
            <label class="form-label">Vencimiento</label>
            <input name="fecha_vencimiento" id="editTareaVenc" class="form-control" placeholder="YYYY-MM-DD">
          </div>
          <div class="col-md-6">
            <label class="form-label"><i class="bi bi-person-check me-1"></i> Asignado a</label>
            <select name="asignado_a" id="editTareaAsignado" class="form-select">
              <option value="">— Sin asignar —</option>
              <?php foreach ($personalLista as $p): ?>
                <option value="<?= e(trim((string)$p['nombre_completo'])) ?>">
                  <?= e(trim((string)$p['nombre_completo'])) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Fin</label>
            <input name="fin" id="editTareaFin" class="form-control" placeholder="2026-03-15 11:00">
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn-pill green" id="btnGuardarEditTarea">
          <i class="bi bi-check2-circle"></i> Guardar cambios
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════
     MODAL: Editar Diario
     ═══════════════════════════════════════════ -->
<div class="modal fade" id="modalEditDiario" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-journal-pen me-2"></i> Editar registro de diario</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="editDiarioAlert" class="alert d-none mb-3"></div>
        <form id="formEditDiario" class="row g-3">
          <input type="hidden" name="action" value="editar_diario">
          <input type="hidden" name="id"     id="editDiarioId">
          <div class="col-12">
            <label class="form-label">Fecha <span class="text-danger">*</span></label>
            <input name="fecha" id="editDiarioFecha" class="form-control" required placeholder="YYYY-MM-DD">
          </div>
          <div class="col-12">
            <label class="form-label">Detalle <span class="text-danger">*</span></label>
            <textarea name="detalle" id="editDiarioDetalle" class="form-control" rows="5" required></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn-pill" id="btnGuardarEditDiario">
          <i class="bi bi-check2-circle"></i> Guardar cambios
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════
     MODAL: Confirmar Eliminación
     ═══════════════════════════════════════════ -->
<div class="modal fade" id="modalConfirm" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header" style="border-bottom:1px solid rgba(239,68,68,.3);">
        <h5 class="modal-title" style="color:#fca5a5;"><i class="bi bi-exclamation-triangle me-2"></i> Confirmar</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="confirmMsg" class="small-muted">¿Seguro que querés eliminar este elemento?</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" id="btnConfirmOk" class="btn btn-danger btn-sm fw-bold">
          <i class="bi bi-trash3 me-1"></i> Eliminar
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════
     MODAL: Exportar PDF
     ═══════════════════════════════════════════ -->
<div class="modal fade" id="modalPdf" tabindex="-1" aria-labelledby="modalPdfLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalPdfLabel"><i class="bi bi-printer me-2"></i> Exportar documento — <?= e(month_label_es($ym)) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">

          <!-- TIPO DE DOCUMENTO -->
          <div class="col-12">
            <label class="form-label fw-bold">Tipo de documento</label>
            <div class="d-flex gap-2 flex-wrap mt-1">
              <label class="pdf-tipo-opt" style="flex:1;min-width:140px;">
                <input type="radio" name="pdfTipo" value="tareas" checked>
                <span class="pdf-tipo-label">
                  <i class="bi bi-list-check"></i>
                  <strong>Tareas</strong>
                  <small>Todas las tareas del período</small>
                </span>
              </label>
              <label class="pdf-tipo-opt" style="flex:1;min-width:140px;">
                <input type="radio" name="pdfTipo" value="resumen">
                <span class="pdf-tipo-label">
                  <i class="bi bi-check-circle"></i>
                  <strong>Resumen</strong>
                  <small>Solo tareas realizadas</small>
                </span>
              </label>
              <label class="pdf-tipo-opt" style="flex:1;min-width:140px;">
                <input type="radio" name="pdfTipo" value="pes">
                <span class="pdf-tipo-label">
                  <i class="bi bi-calendar-week"></i>
                  <strong>PES</strong>
                  <small>Programa especial semanal</small>
                </span>
              </label>
            </div>
          </div>

          <!-- ENCABEZADO -->
          <div class="col-12"><hr class="divider"><label class="form-label fw-bold">Encabezado</label></div>
          <div class="col-md-5">
            <label class="form-label">Unidad superior</label>
            <input type="text" id="pdfUnidad" class="form-control" value="Escuela Militar de Montaña"
                   placeholder="Ej: Escuela Militar de Montaña">
          </div>
          <div class="col-md-4">
            <label class="form-label">Sección / Área del documento</label>
            <input type="text" id="pdfSubunidad" class="form-control"
                   value="<?= e($areaLabel) ?>"
                   placeholder="Ej: Sección Informática">
          </div>
          <div class="col-md-3">
            <label class="form-label">Año / Lema derecha</label>
            <input type="text" id="pdfAnio" class="form-control"
                   value='"AÑO DE LA GRANDEZA ARGENTINA"'>
          </div>

          <!-- TÍTULO Y LUGAR -->
          <div class="col-md-7">
            <label class="form-label">Título del documento <span class="small-muted">(se genera automático si lo dejás vacío)</span></label>
            <input type="text" id="pdfTitulo" class="form-control" placeholder="Ej: Tareas de informática">
          </div>
          <div class="col-md-5">
            <label class="form-label">Lugar</label>
            <input type="text" id="pdfLugar" class="form-control" value="San Carlos de Bariloche">
          </div>

          <!-- FIRMAS -->
          <div class="col-12"><hr class="divider"><label class="form-label fw-bold">Firma / Autorización</label></div>
          <div class="col-md-5">
            <label class="form-label">Firmante</label>
            <select id="pdfFirmante" class="form-select">
              <option value="">— Sin firma —</option>
              <?php foreach ($personalLista as $p): ?>
                <option value="<?= e(trim((string)$p['nombre_completo'])) ?>"><?= e(trim((string)$p['nombre_completo'])) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Cargo / Función</label>
            <input type="text" id="pdfFuncion" class="form-control" placeholder="Ej: Jefe S-INF">
          </div>
          <div class="col-md-4">
            <label class="form-label">2° Firmante (opcional)</label>
            <select id="pdfFirmante2" class="form-select">
              <option value="">— Sin 2° firma —</option>
              <?php foreach ($personalLista as $p): ?>
                <option value="<?= e(trim((string)$p['nombre_completo'])) ?>"><?= e(trim((string)$p['nombre_completo'])) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-5 offset-md-3">
            <label class="form-label">Cargo 2° firmante</label>
            <input type="text" id="pdfFuncion2" class="form-control" placeholder="Ej: VB° Jefe de Sección">
          </div>

        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn-pill" id="btnGenerarPdf">
          <i class="bi bi-file-earmark-pdf"></i> Generar PDF
        </button>
      </div>
    </div>
  </div>
</div>
<style>
  .pdf-tipo-opt { cursor:pointer; }
  .pdf-tipo-opt input[type=radio] { display:none; }
  .pdf-tipo-label {
    display:flex; flex-direction:column; align-items:center; gap:3px;
    padding:10px 8px; border-radius:12px; text-align:center;
    border:1px solid rgba(148,163,184,.3); background:rgba(255,255,255,.04);
    transition:border-color .15s, background .15s; cursor:pointer;
  }
  .pdf-tipo-label i { font-size:1.4rem; color:var(--c-muted); }
  .pdf-tipo-label small { font-size:.72rem; color:var(--c-muted); }
  .pdf-tipo-opt input:checked + .pdf-tipo-label {
    border-color:rgba(14,165,233,.7);
    background:rgba(14,165,233,.1);
  }
  .pdf-tipo-opt input:checked + .pdf-tipo-label i { color:#38bdf8; }
  .pdf-tipo-opt input:checked + .pdf-tipo-label strong { color:#7dd3fc; }
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<style>
  @keyframes spin { to { transform: rotate(360deg); } }
  .spin { display:inline-block; animation: spin .7s linear infinite; }
</style>
<script>
(function(){
  'use strict';

  const SELF_URL = <?= json_encode(url_public('/calendario.php'), JSON_UNESCAPED_SLASHES) ?>;
  const AREA     = <?= json_encode($area, JSON_UNESCAPED_UNICODE) ?>;
  const YM       = <?= json_encode($ym) ?>;

  // ── Toast ──────────────────────────────────────────────────────────
  function toast(msg, isErr=false) {
    const wrap = document.getElementById('toastWrap');
    const el   = document.createElement('div');
    el.className = 'toast-msg ' + (isErr ? 'err' : 'ok');
    el.innerHTML = `<i class="bi ${isErr?'bi-exclamation-circle':'bi-check-circle'}"></i> ${msg}`;
    wrap.appendChild(el);
    setTimeout(() => el.remove(), 3500);
  }

  // ── POST AJAX ──────────────────────────────────────────────────────
  async function post(params) {
    const body = new URLSearchParams(params);
    const r    = await fetch(SELF_URL + '?ym=' + YM + '&area=' + encodeURIComponent(AREA), {
      method:  'POST',
      headers: { 'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest' },
      body:    body.toString()
    });
    return r.json();
  }

  async function postForm(form) {
    return post(Object.fromEntries(new URLSearchParams(new FormData(form))));
  }

  // ── Helper modal ───────────────────────────────────────────────────
  function showModal(id)  { new bootstrap.Modal(document.getElementById(id)).show(); }
  function hideModal(id)  {
    const m = bootstrap.Modal.getInstance(document.getElementById(id));
    if (m) m.hide();
  }
  function setAlert(id, msg, type='danger') {
    const el = document.getElementById(id);
    el.className = `alert alert-${type}`;
    el.textContent = msg;
  }
  function clearAlert(id) { document.getElementById(id).className = 'alert d-none'; }

  // ── Spinner en botón ───────────────────────────────────────────────
  function btnLoading(btn, text='Guardando...') {
    btn.disabled = true;
    btn._orig = btn.innerHTML;
    btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span> ${text}`;
  }
  function btnReset(btn) {
    btn.disabled = false;
    btn.innerHTML = btn._orig;
  }

  // ════════════════════════════════════════════
  //  NUEVA TAREA
  // ════════════════════════════════════════════
  window.openModalTarea = function(fecha) {
    document.getElementById('formTarea').reset();
    clearAlert('tareaAlert');
    if (fecha) {
      document.getElementById('tareaVenc').value   = fecha;
      document.getElementById('tareaInicio').value = fecha + ' 08:00';
    }
    showModal('modalTarea');
  };

  document.getElementById('btnGuardarTarea').addEventListener('click', async function() {
    const form = document.getElementById('formTarea');
    if (!form.checkValidity()) { form.reportValidity(); return; }
    btnLoading(this);
    try {
      const r = await postForm(form);
      if (r.ok) { hideModal('modalTarea'); toast(r.msg); setTimeout(() => location.reload(), 600); }
      else setAlert('tareaAlert', r.msg);
    } catch(e) { setAlert('tareaAlert', 'Error de conexión.'); }
    finally    { btnReset(this); }
  });

  // ════════════════════════════════════════════
  //  EDITAR TAREA
  // ════════════════════════════════════════════
  async function openEditTarea(id) {
    clearAlert('editTareaAlert');
    showModal('modalEditTarea');
    const r = await post({ action:'get_tarea', id });
    if (!r.ok) { setAlert('editTareaAlert', r.msg); return; }
    const d = r.data;
    document.getElementById('editTareaId').value      = d.id;
    document.getElementById('editTareaTitulo').value  = d.titulo || '';
    document.getElementById('editTareaDesc').value    = d.descripcion || '';
    document.getElementById('editTareaPrio').value    = d.prioridad || 'MEDIA';
    document.getElementById('editTareaEstado').value  = d.estado || 'POR_HACER';
    document.getElementById('editTareaInicio').value  = d.inicio ? d.inicio.slice(0,16) : '';
    document.getElementById('editTareaFin').value     = d.fin    ? d.fin.slice(0,16)    : '';
    document.getElementById('editTareaVenc').value    = d.fecha_vencimiento || '';
    // Seleccionar asignado
    const sel = document.getElementById('editTareaAsignado');
    for (let o of sel.options) o.selected = (o.value === (d.asignado_a || ''));
  }

  document.getElementById('btnGuardarEditTarea').addEventListener('click', async function() {
    const form = document.getElementById('formEditTarea');
    if (!form.checkValidity()) { form.reportValidity(); return; }
    btnLoading(this);
    try {
      const r = await postForm(form);
      if (r.ok) { hideModal('modalEditTarea'); toast(r.msg); setTimeout(() => location.reload(), 600); }
      else setAlert('editTareaAlert', r.msg);
    } catch(e) { setAlert('editTareaAlert', 'Error de conexión.'); }
    finally    { btnReset(this); }
  });

  // ════════════════════════════════════════════
  //  NUEVA ENTRADA DIARIO
  // ════════════════════════════════════════════
  window.openModalDiario = function(fecha) {
    document.getElementById('formDiario').reset();
    clearAlert('diarioAlert');
    if (fecha) document.getElementById('diarioFecha').value = fecha;
    showModal('modalDiario');
  };

  document.getElementById('btnGuardarDiario').addEventListener('click', async function() {
    const form = document.getElementById('formDiario');
    if (!form.checkValidity()) { form.reportValidity(); return; }
    btnLoading(this);
    try {
      const r = await postForm(form);
      if (r.ok) { hideModal('modalDiario'); toast(r.msg); setTimeout(() => location.reload(), 600); }
      else setAlert('diarioAlert', r.msg);
    } catch(e) { setAlert('diarioAlert', 'Error de conexión.'); }
    finally    { btnReset(this); }
  });

  // ════════════════════════════════════════════
  //  EDITAR DIARIO
  // ════════════════════════════════════════════
  async function openEditDiario(id) {
    clearAlert('editDiarioAlert');
    showModal('modalEditDiario');
    const r = await post({ action:'get_diario', id });
    if (!r.ok) { setAlert('editDiarioAlert', r.msg); return; }
    const d = r.data;
    document.getElementById('editDiarioId').value     = d.id;
    document.getElementById('editDiarioFecha').value  = d.fecha || '';
    document.getElementById('editDiarioDetalle').value= d.detalle || '';
  }

  document.getElementById('btnGuardarEditDiario').addEventListener('click', async function() {
    const form = document.getElementById('formEditDiario');
    if (!form.checkValidity()) { form.reportValidity(); return; }
    btnLoading(this);
    try {
      const r = await postForm(form);
      if (r.ok) { hideModal('modalEditDiario'); toast(r.msg); setTimeout(() => location.reload(), 600); }
      else setAlert('editDiarioAlert', r.msg);
    } catch(e) { setAlert('editDiarioAlert', 'Error de conexión.'); }
    finally    { btnReset(this); }
  });

  // ════════════════════════════════════════════
  //  ELIMINAR (confirmación genérica)
  // ════════════════════════════════════════════
  let _confirmCallback = null;

  function confirmDelete(msg, cb) {
    document.getElementById('confirmMsg').textContent = msg;
    _confirmCallback = cb;
    showModal('modalConfirm');
  }

  document.getElementById('btnConfirmOk').addEventListener('click', async function() {
    if (!_confirmCallback) return;
    btnLoading(this, 'Eliminando...');
    try {
      const r = await _confirmCallback();
      hideModal('modalConfirm');
      if (r.ok) { toast(r.msg); setTimeout(() => location.reload(), 600); }
      else toast(r.msg, true);
    } catch(e) { toast('Error de conexión.', true); }
    finally    { btnReset(this); _confirmCallback = null; }
  });

  // ════════════════════════════════════════════
  //  CAMBIAR ESTADO INLINE
  // ════════════════════════════════════════════
  document.querySelectorAll('.save-estado').forEach(btn => {
    btn.addEventListener('click', async function() {
      const id     = this.dataset.id;
      const select = document.querySelector(`.estado-select[data-id="${id}"]`);
      const estado = select.value;
      const icon   = this.querySelector('i');
      icon.className = 'bi bi-arrow-repeat spin';
      this.disabled  = true;
      try {
        const r = await post({ action:'cambiar_estado', id, estado });
        toast(r.ok ? r.msg : r.msg, !r.ok);
      } catch(e) { toast('Error de conexión.', true); }
      finally {
        icon.className = 'bi bi-check-lg';
        this.disabled  = false;
      }
    });
  });

  // ════════════════════════════════════════════
  //  DELEGACIÓN DE EVENTOS: editar/eliminar
  // ════════════════════════════════════════════
  document.addEventListener('click', function(e) {
    // Editar tarea
    const btnET = e.target.closest('.btn-editar-tarea');
    if (btnET) { openEditTarea(btnET.dataset.id); return; }

    // Eliminar tarea
    const btnDT = e.target.closest('.btn-eliminar-tarea');
    if (btnDT) {
      confirmDelete(
        `¿Eliminás la tarea "${btnDT.dataset.titulo || '#'+btnDT.dataset.id}"? Esta acción no se puede deshacer.`,
        () => post({ action:'eliminar_tarea', id: btnDT.dataset.id })
      ); return;
    }

    // Editar diario
    const btnED = e.target.closest('.btn-editar-diario');
    if (btnED) { openEditDiario(btnED.dataset.id); return; }

    // Eliminar diario
    const btnDD = e.target.closest('.btn-eliminar-diario');
    if (btnDD) {
      confirmDelete(
        '¿Eliminás este registro de diario? Esta acción no se puede deshacer.',
        () => post({ action:'eliminar_diario', id: btnDD.dataset.id })
      ); return;
    }
  });

  // ════════════════════════════════════════════
  //  EXPORTAR PDF
  // ════════════════════════════════════════════
  window.openModalPdf = function() { showModal('modalPdf'); };

  document.getElementById('btnGenerarPdf').addEventListener('click', function() {
    const tipo = document.querySelector('input[name="pdfTipo"]:checked')?.value || 'tareas';
    const qs = new URLSearchParams({
      action:        'export_pdf',
      area:          AREA,
      ym:            YM,
      pdf_tipo:      tipo,
      pdf_unidad:    document.getElementById('pdfUnidad').value,
      pdf_subunidad: document.getElementById('pdfSubunidad').value,
      pdf_anio:      document.getElementById('pdfAnio').value,
      pdf_titulo:    document.getElementById('pdfTitulo').value,
      pdf_lugar:     document.getElementById('pdfLugar').value,
      pdf_firmante:  document.getElementById('pdfFirmante').value,
      pdf_funcion:   document.getElementById('pdfFuncion').value,
      pdf_firmante2: document.getElementById('pdfFirmante2').value,
      pdf_funcion2:  document.getElementById('pdfFuncion2').value,
    });
    const url = SELF_URL + '?' + qs.toString();
    const win = window.open(url, '_blank');
    if (!win) window.location.href = url;
    hideModal('modalPdf');
  });

})();
</script>

</body>
</html>