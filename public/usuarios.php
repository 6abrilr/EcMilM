<?php
// public/usuarios.php — Gestión de roles_locales (Manual)
declare(strict_types=1);

require_once __DIR__ . '/../auth/bootstrap.php';
require_login();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/ui.php';

if (!function_exists('e')) {
  function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

// Usuario actual
$user    = function_exists('current_user') ? current_user() : null;
$roleApp = $user['role_app'] ?? 'usuario';

// Solo admin
if ($roleApp !== 'admin') {
  http_response_code(403);
  echo 'Acceso restringido. Solo administradores.';
  exit;
}

// 1. TRAER ÁREAS DISPONIBLES
$areasDisponibles = [];
try {
    $stmtAreas = $pdo->query("SELECT id, codigo, nombre FROM areas ORDER BY orden ASC");
    $areasDisponibles = $stmtAreas->fetchAll(PDO::FETCH_ASSOC);
    // Agregamos "Aspectos Generales" manual si no está en la tabla
    $areasDisponibles[] = ['codigo' => 'GRAL', 'nombre' => 'Aspectos Generales'];
} catch (Exception $ex) {
    // Fallback si la tabla areas no existe o falla
    $areasDisponibles = [
        ['codigo'=>'S1','nombre'=>'S1'], ['codigo'=>'S2','nombre'=>'S2'],
        ['codigo'=>'S3','nombre'=>'S3'], ['codigo'=>'S4','nombre'=>'S4'],
        ['codigo'=>'S5','nombre'=>'S5'], ['codigo'=>'GRAL','nombre'=>'Gral']
    ];
}

$mensaje      = '';
$mensaje_tipo = 'success';

// 2. MANEJO DE POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {

    // A) Borrar usuario
    if (isset($_POST['delete_user'])) {
      $delId = (int)($_POST['delete_id'] ?? 0);
      if ($delId > 0) {
        $st = $pdo->prepare("DELETE FROM roles_locales WHERE id = ?");
        if ($st->execute([$delId])) {
          $mensaje      = "Usuario eliminado correctamente.";
          $mensaje_tipo = 'success';
        } else {
          $mensaje      = "No se pudo eliminar.";
          $mensaje_tipo = 'danger';
        }
      }
    }

    // B) Guardar cambios en la lista (Edición masiva)
    if (isset($_POST['save_existing'])) {
      $arrRolApp = $_POST['rol_app']            ?? [];
      $arrCombat = $_POST['rol_combate']        ?? [];
      $arrRolAdm = $_POST['rol_administrativo'] ?? [];
      $arrGrado  = $_POST['grado']              ?? [];
      $arrNombre = $_POST['nombre_apellido']    ?? [];
      $arrAreas  = $_POST['areas']              ?? []; 

      if (is_array($arrRolApp)) {
        $stmt = $pdo->prepare("
          UPDATE roles_locales
          SET rol_combate = ?, rol_administrativo = ?, rol_app = ?, areas_acceso = ?, grado = ?, nombre_apellido = ?
          WHERE id = ?
        ");

        foreach ($arrRolApp as $id => $rolApp) {
          $idInt = (int)$id;
          if ($idInt <= 0) continue;

          // Datos simples
          $rolComb = trim((string)($arrCombat[$id] ?? ''));
          $rolAdm  = trim((string)($arrRolAdm[$id] ?? ''));
          $grado   = trim((string)($arrGrado[$id] ?? ''));
          $nombre  = trim((string)($arrNombre[$id] ?? ''));
          $rol     = ($rolApp === 'admin') ? 'admin' : 'usuario';

          // Áreas (Array -> JSON)
          $misAreas = $arrAreas[$id] ?? []; 
          $areasJson = json_encode($misAreas);

          // Guardar nulos si están vacíos para ahorrar espacio (opcional)
          $stmt->execute([
            $rolComb !== '' ? $rolComb : null,
            $rolAdm  !== '' ? $rolAdm  : null,
            $rol,
            $areasJson,
            $grado,
            $nombre,
            $idInt
          ]);
        }
      }
      $mensaje = 'Cambios guardados correctamente.';
    }

    // C) Crear nuevo usuario (MANUAL)
    if (isset($_POST['create_new'])) {
      $dni = trim((string)($_POST['new_dni'] ?? ''));
      
      if ($dni === '') {
        $mensaje      = 'El DNI es obligatorio.';
        $mensaje_tipo = 'danger';
      } else {
        // Chequear duplicado
        $chk = $pdo->prepare("SELECT id FROM roles_locales WHERE dni = ? LIMIT 1");
        $chk->execute([$dni]);
        
        if ($chk->fetchColumn()) {
          $mensaje      = 'Ya existe un usuario con ese DNI.';
          $mensaje_tipo = 'warning';
        } else {
          // Insertar
          $rol_combate        = trim((string)($_POST['new_rol_combate'] ?? ''));
          $grado              = trim((string)($_POST['new_grado'] ?? ''));
          $nombre_apellido    = trim((string)($_POST['new_nombre_apellido'] ?? ''));
          $rol_administrativo = trim((string)($_POST['new_rol_administrativo'] ?? ''));
          $rol_app_new        = ($_POST['new_rol_app'] ?? 'usuario') === 'admin' ? 'admin' : 'usuario';
          
          $new_areas    = $_POST['new_areas'] ?? [];
          $areasJsonNew = json_encode($new_areas);

          $ins = $pdo->prepare("
            INSERT INTO roles_locales
              (rol_combate, grado, nombre_apellido, dni, rol_administrativo, rol_app, areas_acceso)
            VALUES (?,?,?,?,?,?,?)
          ");
          $ins->execute([
            $rol_combate !== '' ? $rol_combate : null,
            $grado       !== '' ? $grado       : null,
            $nombre_apellido,
            $dni,
            $rol_administrativo !== '' ? $rol_administrativo : null,
            $rol_app_new,
            $areasJsonNew
          ]);
          $mensaje = 'Usuario creado correctamente.';
        }
      }
    }

  } catch (Throwable $e) {
    $mensaje      = 'Error: ' . $e->getMessage();
    $mensaje_tipo = 'danger';
  }
}

// 3. FILTROS DE BÚSQUEDA
$searchDni = trim((string)($_GET['dni'] ?? ''));
$searchNom = trim((string)($_GET['nombre'] ?? ''));

// 4. TRAER REGISTROS PARA LA TABLA (con filtros)
$rows   = [];
$sql    = "SELECT * FROM roles_locales";
$conds  = [];
$params = [];

if ($searchDni !== '') {
    $conds[]  = "dni LIKE ?";
    $params[] = '%' . $searchDni . '%';
}

if ($searchNom !== '') {
    $conds[]  = "nombre_apellido LIKE ?";
    $params[] = '%' . $searchNom . '%';
}

if ($conds) {
    $sql .= ' WHERE ' . implode(' AND ', $conds);
}

$sql .= " ORDER BY id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

ui_header('Gestión de usuarios', ['container'=>'xl', 'show_brand'=>false]);
?>
<link rel="stylesheet" href="../assets/css/theme-602.css">
<link rel="icon" type="image/png" href="../assets/img/bcom602.png">

<style>
  body{
    background: url("../assets/img/fondo.png") no-repeat center center fixed;
    background-size: cover;
    background-attachment: fixed;
    background-color:#0f1117;
    color:#e9eef5;
    font-family:system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,sans-serif;
  }
  .panel{
    background: rgba(15,17,23,.95);
    border:1px solid rgba(255,255,255,.1);
    border-radius:16px;
    padding:16px;
    margin-top:18px;
    box-shadow:0 10px 24px rgba(0,0,0,.4);
  }
  .title{ font-weight:800; font-size:1.05rem; margin:0 0 .4rem 0; }
  
  /* Tabla */
  table.tbl{ width:100%; border-collapse:collapse; font-size:.85rem; }
  .tbl th, .tbl td{ padding:.4rem .5rem; border-bottom:1px solid rgba(255,255,255,.1); vertical-align: middle; }
  .tbl th{ font-weight:800; white-space: nowrap; }

  /* Inputs dentro de la tabla */
  .tbl input[type="text"]{
    background:rgba(15,17,23,1);
    border:1px solid rgba(148,163,184,.6);
    border-radius:6px;
    color:#e5e7eb;
    font-size:.8rem;
    padding:.15rem .35rem;
    width:100%;
  }
  .tbl input[type="text"]:focus{ outline:none; border-color:#22c55e; }

  /* Checkboxes de Áreas */
  .areas-container {
      display: flex;
      flex-wrap: wrap;
      gap: 4px;
      max-width: 300px;
  }
  .area-check-label {
      font-size: 0.7rem;
      background: rgba(255,255,255,0.05);
      padding: 2px 6px;
      border-radius: 4px;
      cursor: pointer;
      user-select: none;
      border: 1px solid transparent;
  }
  .area-check-label:has(input:checked) {
      background: rgba(34, 197, 94, 0.2);
      border-color: rgba(34, 197, 94, 0.5);
      color: #4ade80;
  }
  .area-check-label input { display: none; }

  .role-select{ max-width:100px; font-weight:600; font-size: 0.8rem;}
  .role-select.role-admin{ border-color:#16a34a; color:#16a34a; }
  
  .btn-icon { padding: 2px 6px; font-size: 0.8rem; }
</style>

<div class="container mt-3">
  <div class="d-flex align-items-center justify-content-between">
    <h2 class="h5 mb-0">Gestión de usuarios</h2>
    <a href="gestiones.php" class="btn btn-sm btn-outline-light">← Volver</a>
  </div>

  <?php if ($mensaje !== ''): ?>
    <div class="alert alert-<?= e($mensaje_tipo) ?> mt-3 py-2">
      <?= e($mensaje) ?>
    </div>
  <?php endif; ?>

  <!-- Buscador por DNI y Nombre -->
  <form method="get" class="row g-2 align-items-end mt-3 mb-2">
    <div class="col-md-3">
      <label class="form-label">Buscar por DNI</label>
      <input 
        type="text" 
        name="dni" 
        class="form-control form-control-sm" 
        placeholder="DNI sin puntos" 
        value="<?= e($searchDni) ?>">
    </div>

    <div class="col-md-4">
      <label class="form-label">Buscar por nombre</label>
      <input 
        type="text" 
        name="nombre" 
        class="form-control form-control-sm" 
        placeholder="Nombre o apellido" 
        value="<?= e($searchNom) ?>">
    </div>

    <div class="col-md-3">
      <button type="submit" class="btn btn-primary btn-sm me-2">
        🔍 Buscar
      </button>
      <a href="usuarios.php" class="btn btn-outline-light btn-sm">
        ✖ Limpiar
      </a>
    </div>
  </form>

  <div class="panel">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h3 class="title">Lista de Usuarios</h3>
        <button form="form-list" type="submit" name="save_existing" class="btn btn-success btn-sm">
          💾 Guardar Cambios
        </button>
    </div>

    <form method="post" id="form-list">
      <div class="table-responsive">
        <table class="tbl">
          <thead>
            <tr>
              <th width="5%">DNI</th>
              <th width="20%">Grado / Nombre</th>
              <th width="15%">Rol Combate</th>
              <th width="15%">Rol Admin</th>
              <th width="30%">Áreas</th>
              <th width="10%">App</th>
              <th width="5%"></th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="7" class="text-muted text-center">No hay registros.</td></tr>
          <?php else: ?>
            <?php foreach($rows as $r): ?>
              <?php 
                $id = (int)$r['id']; 
                $userAreas = json_decode($r['areas_acceso'] ?? '[]', true);
                if (!is_array($userAreas)) $userAreas = [];
              ?>
              <tr>
                <td>
                    <?= e($r['dni']) ?>
                </td>
                
                <td>
                  <input type="text" name="grado[<?= $id ?>]" value="<?= e($r['grado'] ?? '') ?>" placeholder="Grado" style="width:30%; display:inline-block;">
                  <input type="text" name="nombre_apellido[<?= $id ?>]" value="<?= e($r['nombre_apellido'] ?? '') ?>" placeholder="Nombre" style="width:65%; display:inline-block;">
                </td>

                <td>
                  <input type="text" name="rol_combate[<?= $id ?>]" value="<?= e($r['rol_combate'] ?? '') ?>">
                </td>
                
                <td>
                  <input type="text" name="rol_administrativo[<?= $id ?>]" value="<?= e($r['rol_administrativo'] ?? '') ?>">
                </td>
                
                <td>
                    <div class="areas-container">
                        <?php foreach($areasDisponibles as $area): ?>
                            <?php 
                                $isChecked = in_array($area['codigo'], $userAreas); 
                                $checkId = "u_{$id}_{$area['codigo']}";
                            ?>
                            <label class="area-check-label" for="<?= $checkId ?>">
                                <input type="checkbox" 
                                       id="<?= $checkId ?>" 
                                       name="areas[<?= $id ?>][]" 
                                       value="<?= $area['codigo'] ?>" 
                                       <?= $isChecked ? 'checked' : '' ?>>
                                <?= e($area['codigo']) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </td>

                <td>
                  <select name="rol_app[<?= $id ?>]" class="form-select form-select-sm role-select <?= ($r['rol_app'] === 'admin' ? 'role-admin' : 'role-user') ?>">
                    <option value="usuario" <?= ($r['rol_app'] === 'usuario' ? 'selected' : '') ?>>user</option>
                    <option value="admin"   <?= ($r['rol_app'] === 'admin'   ? 'selected' : '') ?>>admin</option>
                  </select>
                </td>
                <td class="text-end">
                    <button type="submit" name="delete_user" value="1" onclick="this.form.delete_id.value='<?= $id ?>'; return confirm('¿Borrar?');" class="btn btn-icon btn-outline-danger">🗑️</button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
        <input type="hidden" name="delete_id" value="">
      </div>
    </form>
  </div>

  <div class="panel">
    <h3 class="title">Alta de nuevo usuario</h3>
    <p class="text-muted mb-2" style="font-size:0.8rem">
      Ingrese el DNI y asigne los permisos. El usuario validará su identidad al loguearse en CPS.
    </p>

    <form method="post" id="form-create">
      <div class="row g-2">
        
        <div class="col-md-2">
          <label class="form-label text-warning">DNI *</label>
          <input type="text" name="new_dni" class="form-control form-control-sm" required placeholder="Sin puntos">
        </div>

        <div class="col-md-2">
          <label classform-label">Grado</label>
          <input type="text" name="new_grado" class="form-control form-control-sm">
        </div>
        
        <div class="col-md-4">
          <label class="form-label">Nombre y apellido</label>
          <input type="text" name="new_nombre_apellido" class="form-control form-control-sm" placeholder="Opcional">
        </div>

        <div class="col-md-2">
            <label class="form-label">Rol App</label>
            <select name="new_rol_app" class="form-select form-select-sm">
                <option value="usuario">usuario</option>
                <option value="admin">admin</option>
            </select>
        </div>
        
        <div class="col-md-3">
          <label class="form-label">Rol de combate</label>
          <input type="text" name="new_rol_combate" class="form-control form-control-sm">
        </div>
        
        <div class="col-md-3">
          <label class="form-label">Rol administrativo</label>
          <input type="text" name="new_rol_administrativo" class="form-control form-control-sm">
        </div>

        <div class="col-md-6">
            <label class="form-label">Áreas permitidas</label>
            <div class="areas-container mt-1">
                <?php foreach($areasDisponibles as $area): ?>
                    <label class="area-check-label">
                        <input type="checkbox" name="new_areas[]" value="<?= $area['codigo'] ?>">
                        <?= e($area['nombre']) ?>
                    </label>
                <?php endforeach; ?>
                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size:0.7rem;" onclick="toggleAllAreas()">Todas</button>
            </div>
        </div>
      </div>

      <div class="mt-3 text-end">
        <button type="submit" name="create_new" class="btn btn-primary btn-sm">
          ➕ Crear usuario
        </button>
      </div>
    </form>
  </div>
</div>

<script>
// Función simple para marcar todas las areas en el alta
function toggleAllAreas() {
    const checks = document.querySelectorAll('#form-create input[name="new_areas[]"]');
    const allChecked = Array.from(checks).every(c => c.checked);
    checks.forEach(c => c.checked = !allChecked);
}
</script>

<?php ui_footer(); ?>
