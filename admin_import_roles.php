<?php
// admin_import_roles.php
declare(strict_types=1);

require_once __DIR__ . '/auth/bootstrap.php';
require_login();
require_once __DIR__ . '/config/db.php';

$user = current_user();

// Solo admin de la app
if (!$user || ($user['role_app'] ?? '') !== 'admin') {
    http_response_code(403);
    echo "Acceso restringido. Solo administradores.";
    exit;
}

// Rutas
$projectBase = realpath(__DIR__);
$autoload = $projectBase . '/vendor/autoload.php';

if (!is_file($autoload)) {
    http_response_code(500);
    echo "No encuentro vendor/autoload.php. Verificá Composer en este proyecto.";
    exit;
}

require_once $autoload;

// Helpers
function e($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function norm_header(string $s): string {
    $s = trim($s);
    $s = mb_strtoupper($s, 'UTF-8');
    $s = preg_replace('/\s+/u', '', $s);
    return $s;
}

$msg_ok = '';
$msg_err = '';
$preview = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['roles_file'])) {

    if ($_FILES['roles_file']['error'] !== UPLOAD_ERR_OK) {
        $msg_err = "Error al subir el archivo.";
    } else {
        $tmp  = $_FILES['roles_file']['tmp_name'];
        $name = $_FILES['roles_file']['name'];
        $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if (!in_array($ext, ['xlsx','xls'], true)) {
            $msg_err = "Formato no soportado. Subí un .xlsx o .xls";
        } else {
            try {
                $ss = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp);
                $sheet = $ss->getSheet(0);

                $highestRow = $sheet->getHighestDataRow();
                $highestCol = $sheet->getHighestDataColumn();
                $highestColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol);

                // Leer encabezados (fila 1)
                $dniCol = null;
                $rolCol = null;
                $notaCol = null;

                for ($c = 1; $c <= $highestColIndex; $c++) {
                    $raw = (string)$sheet->getCellByColumnAndRow($c, 1)->getValue();
                    $h = norm_header($raw);

                    if ($dniCol === null && in_array($h, ['DNI','NRODNI','NUMERODNI'], true)) {
                        $dniCol = $c;
                    }
                    if ($rolCol === null && in_array($h, ['ROLAPP','ROL','ROL_APP'], true)) {
                        $rolCol = $c;
                    }
                    if ($notaCol === null && in_array($h, ['NOTA','OBS','OBSERVACION','OBSERVACIONES'], true)) {
                        $notaCol = $c;
                    }
                }

                if ($dniCol === null) {
                    $msg_err = "No encontré columna DNI en la primera fila. Asegurate que una columna tenga encabezado 'DNI'.";
                } else {
                    $pdo->exec("CREATE TABLE IF NOT EXISTS roles_locales (
                        dni VARCHAR(20) NOT NULL PRIMARY KEY,
                        rol_app ENUM('admin','usuario') NOT NULL DEFAULT 'usuario',
                        nota VARCHAR(255) DEFAULT NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                    $stmt = $pdo->prepare("REPLACE INTO roles_locales (dni, rol_app, nota) VALUES (?, ?, ?)");

                    $count = 0;
                    $skipped = 0;
                    $preview = [];

                    for ($r = 2; $r <= $highestRow; $r++) {
                        $dniVal = $sheet->getCellByColumnAndRow($dniCol, $r)->getValue();
                        if ($dniVal === null || $dniVal === '') {
                            continue;
                        }

                        // Normalizar DNI a texto sin espacios
                        $dni = trim((string)$dniVal);
                        if ($dni === '') {
                            continue;
                        }

                        $rol = 'usuario';
                        if ($rolCol !== null) {
                            $rolRaw = $sheet->getCellByColumnAndRow($rolCol, $r)->getValue();
                            $rolTmp = strtolower(trim((string)$rolRaw));
                            if ($rolTmp === 'admin' || $rolTmp === 'usuario') {
                                $rol = $rolTmp;
                            }
                        }

                        $nota = null;
                        if ($notaCol !== null) {
                            $notaRaw = $sheet->getCellByColumnAndRow($notaCol, $r)->getValue();
                            $nota = trim((string)$notaRaw);
                            if ($nota === '') {
                                $nota = null;
                            }
                        }

                        try {
                            $stmt->execute([$dni, $rol, $nota]);
                            $count++;

                            if (count($preview) < 15) {
                                $preview[] = [
                                    'dni' => $dni,
                                    'rol' => $rol,
                                    'nota' => $nota,
                                ];
                            }
                        } catch (Throwable $e) {
                            $skipped++;
                        }
                    }

                    $msg_ok = "Importación completa. Filas grabadas: {$count}" .
                              ($skipped ? " | Filas con error: {$skipped}" : "");
                }

            } catch (Throwable $e) {
                $msg_err = "Error leyendo Excel: " . $e->getMessage();
            }
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Importar roles locales</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/theme-602.css">
</head>
<body class="bg-dark text-light">

<div class="container py-4">
  <h1 class="h3 mb-3">Importar roles locales desde Excel</h1>

  <p class="mb-2">
    Esta página carga la tabla <code>roles_locales</code> con los DNI y roles que tengas en el Excel.
  </p>
  <ul>
    <li>Debe existir una columna con encabezado <strong>DNI</strong>.</li>
    <li>Opcional: columna <strong>ROL_APP</strong> o <strong>ROL</strong> (valores: <code>admin</code> o <code>usuario</code>).</li>
    <li>Opcional: columna <strong>NOTA</strong> / <strong>Observaciones</strong>.</li>
    <li>Se usa la primera hoja del archivo.</li>
  </ul>

  <?php if ($msg_err): ?>
    <div class="alert alert-danger"><?= e($msg_err) ?></div>
  <?php endif; ?>

  <?php if ($msg_ok): ?>
    <div class="alert alert-success"><?= e($msg_ok) ?></div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="mb-4">
    <div class="mb-3">
      <label class="form-label">Archivo Excel (.xlsx / .xls)</label>
      <input type="file" name="roles_file" class="form-control" accept=".xlsx,.xls" required>
    </div>
    <button type="submit" class="btn btn-success">Importar</button>
    <a href="public/index.php" class="btn btn-secondary">Volver al Dashboard</a>
  </form>

  <?php if ($preview): ?>
    <h2 class="h5">Primeras filas importadas</h2>
    <div class="table-responsive">
      <table class="table table-sm table-dark table-striped align-middle">
        <thead>
          <tr>
            <th>DNI</th>
            <th>Rol</th>
            <th>Nota</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($preview as $row): ?>
            <tr>
              <td><?= e($row['dni']) ?></td>
              <td><?= e($row['rol']) ?></td>
              <td><?= e($row['nota'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

</div>

</body>
</html>
