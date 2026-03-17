<?php
// public/chat.php — Chat interno por unidad (general + privado + notas + adjuntos + borrado + embebido)
declare(strict_types=1);

require_once __DIR__ . '/../auth/bootstrap.php';
require_login();
require_once __DIR__ . '/../config/db.php';

function e($v): string {
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function norm_dni(string $dni): string {
  return preg_replace('/\D+/', '', $dni) ?? '';
}

function app_root_abs(): string {
  return dirname(__DIR__);
}

function app_base_web(): string {
  $self = (string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
  $basePublic = rtrim(str_replace('\\', '/', dirname($self)), '/');
  $baseApp = rtrim(str_replace('\\', '/', dirname($basePublic)), '/');
  return $baseApp;
}

function json_out(array $data, int $status = 200): never {
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

function nombre_personal(array $r): string {
  $grado = trim((string)($r['grado'] ?? ''));
  $arma  = trim((string)($r['arma'] ?? ''));

  $apellido = trim((string)($r['apellido'] ?? ''));
  $nombre   = trim((string)($r['nombre'] ?? ''));
  $apelNom  = trim((string)($r['apellido_nombre'] ?? ''));

  $nombreBase = trim($apellido . ' ' . $nombre);
  if ($nombreBase === '') {
    $nombreBase = $apelNom;
  }

  $final = trim(implode(' ', array_filter([
    $grado,
    $arma,
    $nombreBase,
  ])));

  return $final !== '' ? preg_replace('/\s+/', ' ', $final) : ('DNI ' . (string)($r['dni'] ?? ''));
}

function get_personal_actual(PDO $pdo, array $user): array {
  $dniNorm = norm_dni((string)($user['dni'] ?? $user['username'] ?? ''));
  if ($dniNorm === '') {
    return [
      'id' => 0,
      'unidad_id' => 0,
      'dni' => '',
      'grado' => '',
      'arma' => '',
      'apellido' => '',
      'nombre' => '',
      'apellido_nombre' => '',
    ];
  }

  $st = $pdo->prepare("
    SELECT id, unidad_id, dni, grado, arma, apellido, nombre, apellido_nombre
    FROM personal_unidad
    WHERE REPLACE(REPLACE(REPLACE(dni,'.',''),'-',''),' ','') = :dni
    LIMIT 1
  ");
  $st->execute([':dni' => $dniNorm]);
  $r = $st->fetch(PDO::FETCH_ASSOC);

  return $r ?: [
    'id' => 0,
    'unidad_id' => 0,
    'dni' => '',
    'grado' => '',
    'arma' => '',
    'apellido' => '',
    'nombre' => '',
    'apellido_nombre' => '',
  ];
}

function get_role_code(PDO $pdo, int $personalId, int $unidadId): string {
  $roleCodigo = 'USUARIO';

  try {
    if ($personalId > 0) {
      $st = $pdo->prepare("
        SELECT r.codigo
        FROM personal_unidad pu
        INNER JOIN roles r ON r.id = pu.role_id
        WHERE pu.id = :pid
        LIMIT 1
      ");
      $st->execute([':pid' => $personalId]);
      $c = $st->fetchColumn();
      if (is_string($c) && $c !== '') {
        return strtoupper($c);
      }
    }
  } catch (Throwable $e) {}

  try {
    if ($personalId > 0) {
      $st = $pdo->prepare("
        SELECT r.codigo
        FROM usuario_roles ur
        INNER JOIN roles r ON r.id = ur.role_id
        WHERE ur.personal_id = :pid
          AND (ur.unidad_id IS NULL OR ur.unidad_id = :uid)
        ORDER BY
          CASE r.codigo
            WHEN 'SUPERADMIN' THEN 3
            WHEN 'ADMIN' THEN 2
            ELSE 1
          END DESC,
          ur.created_at DESC,
          ur.id DESC
        LIMIT 1
      ");
      $st->execute([':pid' => $personalId, ':uid' => $unidadId]);
      $c = $st->fetchColumn();
      if (is_string($c) && $c !== '') {
        return strtoupper($c);
      }
    }
  } catch (Throwable $e) {}

  return $roleCodigo;
}

function is_admin_role(string $roleCodigo): bool {
  return in_array(strtoupper($roleCodigo), ['ADMIN', 'SUPERADMIN'], true);
}

function can_write_general(string $roleCodigo): bool {
  return is_admin_role($roleCodigo);
}

function get_unidad_slug(PDO $pdo, int $unidadId): string {
  try {
    $st = $pdo->prepare("SELECT slug FROM unidades WHERE id = :id LIMIT 1");
    $st->execute([':id' => $unidadId]);
    $slug = trim((string)$st->fetchColumn());
    if ($slug !== '') {
      return preg_replace('/[^a-zA-Z0-9_-]+/', '_', $slug);
    }
  } catch (Throwable $e) {}
  return 'unidad_' . $unidadId;
}

function ensure_dir(string $dir): void {
  if (!is_dir($dir)) {
    @mkdir($dir, 0775, true);
  }
}

function build_chat_storage(PDO $pdo, int $unidadId, int $conversationId): array {
  $slug = get_unidad_slug($pdo, $unidadId);
  $year = date('Y');
  $month = date('m');

  $relDir = 'storage/unidades/' . $slug . '/chat/' . $conversationId . '/' . $year . '/' . $month;
  $absDir = app_root_abs() . '/' . $relDir;
  $webDir = app_base_web() . '/' . str_replace('\\', '/', $relDir);

  ensure_dir($absDir);

  return [
    'rel_dir' => str_replace('\\', '/', $relDir),
    'abs_dir' => str_replace('\\', '/', $absDir),
    'web_dir' => str_replace('\\', '/', $webDir),
  ];
}

function safe_file_name(string $name): string {
  $name = basename($name);
  $name = preg_replace('/[^\pL\pN._ -]+/u', '_', $name) ?? 'archivo';
  $name = trim($name);
  return $name !== '' ? $name : 'archivo';
}

function detect_mime_type(string $tmpPath): string {
  if (function_exists('finfo_open')) {
    $f = finfo_open(FILEINFO_MIME_TYPE);
    if ($f) {
      $m = finfo_file($f, $tmpPath);
      finfo_close($f);
      if (is_string($m) && $m !== '') return $m;
    }
  }
  if (function_exists('mime_content_type')) {
    $m = mime_content_type($tmpPath);
    if (is_string($m) && $m !== '') return $m;
  }
  return 'application/octet-stream';
}

function ensure_general_conversation(PDO $pdo, int $unidadId): int {
  $st = $pdo->prepare("
    SELECT id
    FROM chat_conversaciones
    WHERE unidad_id = :uid
      AND tipo = 'general'
    ORDER BY id ASC
    LIMIT 1
  ");
  $st->execute([':uid' => $unidadId]);
  $id = (int)$st->fetchColumn();
  if ($id > 0) return $id;

  $st = $pdo->prepare("
    INSERT INTO chat_conversaciones (unidad_id, tipo, titulo)
    VALUES (:uid, 'general', 'Chat General')
  ");
  $st->execute([':uid' => $unidadId]);

  return (int)$pdo->lastInsertId();
}

function has_conversation_access(PDO $pdo, int $conversationId, int $personalId, int $unidadId): bool {
  $st = $pdo->prepare("
    SELECT id, unidad_id, tipo
    FROM chat_conversaciones
    WHERE id = :id
    LIMIT 1
  ");
  $st->execute([':id' => $conversationId]);
  $c = $st->fetch(PDO::FETCH_ASSOC);

  if (!$c) return false;

  if ((string)$c['tipo'] === 'general') {
    return (int)$c['unidad_id'] === $unidadId;
  }

  $st = $pdo->prepare("
    SELECT 1
    FROM chat_participantes
    WHERE conversacion_id = :cid
      AND personal_id = :pid
    LIMIT 1
  ");
  $st->execute([
    ':cid' => $conversationId,
    ':pid' => $personalId
  ]);

  return (bool)$st->fetchColumn();
}

function get_conversation_row(PDO $pdo, int $conversationId): ?array {
  $st = $pdo->prepare("
    SELECT id, unidad_id, tipo, titulo, creado_por_personal_id
    FROM chat_conversaciones
    WHERE id = :id
    LIMIT 1
  ");
  $st->execute([':id' => $conversationId]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  return $row ?: null;
}

function get_attachment_map(PDO $pdo, array $messageIds): array {
  if (!$messageIds) return [];

  $messageIds = array_values(array_unique(array_map('intval', $messageIds)));
  if (!$messageIds) return [];

  $placeholders = implode(',', array_fill(0, count($messageIds), '?'));
  $st = $pdo->prepare("
    SELECT id, mensaje_id, nombre_original, ruta_relativa, mime_type, tamano_bytes
    FROM chat_archivos
    WHERE mensaje_id IN ($placeholders)
    ORDER BY id ASC
  ");
  $st->execute($messageIds);

  $map = [];
  $baseWeb = app_base_web();

  while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
    $mid = (int)$r['mensaje_id'];
    if (!isset($map[$mid])) $map[$mid] = [];

    $map[$mid][] = [
      'id' => (int)$r['id'],
      'name' => (string)$r['nombre_original'],
      'url' => $baseWeb . '/' . ltrim((string)$r['ruta_relativa'], '/'),
      'mime' => (string)($r['mime_type'] ?? ''),
      'size' => (int)($r['tamano_bytes'] ?? 0),
    ];
  }

  return $map;
}

function remove_file_by_relative_path(string $relativePath): void {
  $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
  if ($relativePath === '') return;

  $full = app_root_abs() . '/' . $relativePath;
  $full = str_replace('\\', '/', $full);

  $root = str_replace('\\', '/', app_root_abs());
  if (strpos($full, $root) !== 0) return;

  if (is_file($full)) {
    @unlink($full);
  }
}

function delete_message_attachments(PDO $pdo, int $messageId): void {
  $st = $pdo->prepare("
    SELECT ruta_relativa
    FROM chat_archivos
    WHERE mensaje_id = :mid
  ");
  $st->execute([':mid' => $messageId]);

  while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
    remove_file_by_relative_path((string)$r['ruta_relativa']);
  }

  $st = $pdo->prepare("DELETE FROM chat_archivos WHERE mensaje_id = :mid");
  $st->execute([':mid' => $messageId]);
}

function delete_conversation_files(PDO $pdo, int $conversationId): void {
  $st = $pdo->prepare("
    SELECT a.ruta_relativa
    FROM chat_archivos a
    INNER JOIN chat_mensajes m ON m.id = a.mensaje_id
    WHERE m.conversacion_id = :cid
  ");
  $st->execute([':cid' => $conversationId]);

  while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
    remove_file_by_relative_path((string)$r['ruta_relativa']);
  }
}

$user = function_exists('current_user') ? current_user() : ($_SESSION['user'] ?? null);
if (!$user || !is_array($user)) {
  http_response_code(403);
  exit('Sesión inválida.');
}

$me = get_personal_actual($pdo, $user);
$personalId   = (int)($me['id'] ?? 0);
$unidadPropia = (int)($me['unidad_id'] ?? 0);
$fullNameDB   = nombre_personal($me);
$roleCodigo   = get_role_code($pdo, $personalId, $unidadPropia);
$isAdmin      = is_admin_role($roleCodigo);

if ($personalId <= 0 || $unidadPropia <= 0) {
  if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    json_out(['ok' => false, 'error' => 'No se pudo vincular el usuario logueado con personal_unidad.'], 403);
  }
  http_response_code(403);
  exit('No se pudo vincular el usuario logueado con personal_unidad.');
}

$generalConversationId = ensure_general_conversation($pdo, $unidadPropia);

/* ==========================================================
   AJAX
   ========================================================== */
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
  $action = trim((string)($_GET['action'] ?? $_POST['action'] ?? ''));

  if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_verify();
  }

  try {
    if ($action === 'list_users') {
      $st = $pdo->prepare("
        SELECT id, dni, grado, arma, apellido, nombre, apellido_nombre
        FROM personal_unidad
        WHERE unidad_id = :uid
        ORDER BY
          CASE WHEN id = :me THEN 0 ELSE 1 END ASC,
          COALESCE(NULLIF(apellido,''), NULLIF(apellido_nombre,''), '') ASC,
          nombre ASC,
          grado ASC
      ");
      $st->execute([
        ':uid' => $unidadPropia,
        ':me'  => $personalId
      ]);

      $items = [];
      while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        $isSelf = ((int)$r['id'] === $personalId);
        $items[] = [
          'id' => (int)$r['id'],
          'dni' => (string)$r['dni'],
          'display_name' => $isSelf ? '📝 Mis notas' : nombre_personal($r),
          'is_self' => $isSelf,
        ];
      }

      json_out(['ok' => true, 'items' => $items]);
    }

    if ($action === 'list_conversations') {
      $items = [];

      $stLast = $pdo->prepare("
        SELECT id, mensaje, created_at, personal_id
        FROM chat_mensajes
        WHERE conversacion_id = :cid
        ORDER BY id DESC
        LIMIT 1
      ");
      $stLast->execute([':cid' => $generalConversationId]);
      $lastGeneral = $stLast->fetch(PDO::FETCH_ASSOC) ?: null;

      $items[] = [
        'id' => $generalConversationId,
        'type' => 'general',
        'title' => 'Chat General',
        'subtitle' => 'Unidad',
        'last_message' => (string)($lastGeneral['mensaje'] ?? ''),
        'last_at' => (string)($lastGeneral['created_at'] ?? ''),
        'last_message_id' => (int)($lastGeneral['id'] ?? 0),
        'last_from_me' => ((int)($lastGeneral['personal_id'] ?? 0) === $personalId),
        'deletable' => false,
      ];

      $st = $pdo->prepare("
        SELECT
          c.id,
          c.titulo,
          p2.id AS other_id,
          p2.dni,
          p2.grado,
          p2.arma,
          p2.apellido,
          p2.nombre,
          p2.apellido_nombre,
          lm.last_message_id,
          lm.last_message,
          lm.last_at,
          lm.last_personal_id
        FROM chat_conversaciones c
        INNER JOIN chat_participantes cp1
          ON cp1.conversacion_id = c.id
         AND cp1.personal_id = :me
        LEFT JOIN chat_participantes cp2
          ON cp2.conversacion_id = c.id
         AND cp2.personal_id <> :me
        LEFT JOIN personal_unidad p2
          ON p2.id = cp2.personal_id
        LEFT JOIN (
          SELECT m1.conversacion_id,
                 m1.id AS last_message_id,
                 m1.mensaje AS last_message,
                 m1.created_at AS last_at,
                 m1.personal_id AS last_personal_id
          FROM chat_mensajes m1
          INNER JOIN (
            SELECT conversacion_id, MAX(id) AS max_id
            FROM chat_mensajes
            GROUP BY conversacion_id
          ) x ON x.max_id = m1.id
        ) lm ON lm.conversacion_id = c.id
        WHERE c.tipo = 'privado'
          AND c.unidad_id = :uid
        ORDER BY COALESCE(lm.last_at, c.updated_at, c.created_at) DESC, c.id DESC
      ");
      $st->execute([
        ':me'  => $personalId,
        ':uid' => $unidadPropia
      ]);

      while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        $isSelf = empty($r['other_id']);
        $items[] = [
          'id' => (int)$r['id'],
          'type' => 'private',
          'title' => $isSelf
            ? ((trim((string)($r['titulo'] ?? '')) !== '') ? (string)$r['titulo'] : 'Mis notas')
            : nombre_personal($r),
          'subtitle' => $isSelf ? 'Notas personales' : 'Chat privado',
          'other_id' => (int)($r['other_id'] ?? 0),
          'dni' => (string)($r['dni'] ?? ''),
          'last_message' => (string)($r['last_message'] ?? ''),
          'last_at' => (string)($r['last_at'] ?? ''),
          'last_message_id' => (int)($r['last_message_id'] ?? 0),
          'last_from_me' => ((int)($r['last_personal_id'] ?? 0) === $personalId),
          'is_self' => $isSelf,
          'deletable' => true,
        ];
      }

      json_out(['ok' => true, 'items' => $items]);
    }

    if ($action === 'start_private') {
      $targetId = (int)($_POST['target_id'] ?? 0);
      if ($targetId <= 0) {
        json_out(['ok' => false, 'error' => 'Usuario inválido.'], 422);
      }

      if ($targetId === $personalId) {
        $st = $pdo->prepare("
          SELECT c.id
          FROM chat_conversaciones c
          INNER JOIN chat_participantes cp1
            ON cp1.conversacion_id = c.id
           AND cp1.personal_id = :me
          LEFT JOIN chat_participantes cp2
            ON cp2.conversacion_id = c.id
           AND cp2.personal_id <> :me
          WHERE c.tipo = 'privado'
            AND c.unidad_id = :uid
            AND cp2.personal_id IS NULL
          ORDER BY c.id ASC
          LIMIT 1
        ");
        $st->execute([
          ':me'  => $personalId,
          ':uid' => $unidadPropia
        ]);
        $existingId = (int)$st->fetchColumn();

        if ($existingId > 0) {
          json_out([
            'ok' => true,
            'conversation_id' => $existingId,
            'title' => 'Mis notas'
          ]);
        }

        $pdo->beginTransaction();

        $st = $pdo->prepare("
          INSERT INTO chat_conversaciones (unidad_id, tipo, titulo, creado_por_personal_id)
          VALUES (:uid, 'privado', 'Mis notas', :me)
        ");
        $st->execute([
          ':uid' => $unidadPropia,
          ':me'  => $personalId
        ]);
        $conversationId = (int)$pdo->lastInsertId();

        $st = $pdo->prepare("
          INSERT INTO chat_participantes (conversacion_id, personal_id)
          VALUES (:cid, :pid)
        ");
        $st->execute([':cid' => $conversationId, ':pid' => $personalId]);

        $pdo->commit();

        json_out([
          'ok' => true,
          'conversation_id' => $conversationId,
          'title' => 'Mis notas'
        ]);
      }

      $st = $pdo->prepare("
        SELECT id, unidad_id, dni, grado, arma, apellido, nombre, apellido_nombre
        FROM personal_unidad
        WHERE id = :id
        LIMIT 1
      ");
      $st->execute([':id' => $targetId]);
      $target = $st->fetch(PDO::FETCH_ASSOC);

      if (!$target) {
        json_out(['ok' => false, 'error' => 'El usuario destino no existe.'], 404);
      }
      if ((int)$target['unidad_id'] !== $unidadPropia) {
        json_out(['ok' => false, 'error' => 'Solo se permiten chats privados dentro de la misma unidad.'], 403);
      }

      $st = $pdo->prepare("
        SELECT c.id
        FROM chat_conversaciones c
        INNER JOIN chat_participantes p1
          ON p1.conversacion_id = c.id
         AND p1.personal_id = :me
        INNER JOIN chat_participantes p2
          ON p2.conversacion_id = c.id
         AND p2.personal_id = :other
        WHERE c.tipo = 'privado'
          AND c.unidad_id = :uid
        ORDER BY c.id ASC
        LIMIT 1
      ");
      $st->execute([
        ':me'    => $personalId,
        ':other' => $targetId,
        ':uid'   => $unidadPropia
      ]);
      $existingId = (int)$st->fetchColumn();

      if ($existingId > 0) {
        json_out([
          'ok' => true,
          'conversation_id' => $existingId,
          'title' => nombre_personal($target)
        ]);
      }

      $pdo->beginTransaction();

      $st = $pdo->prepare("
        INSERT INTO chat_conversaciones (unidad_id, tipo, titulo, creado_por_personal_id)
        VALUES (:uid, 'privado', NULL, :me)
      ");
      $st->execute([
        ':uid' => $unidadPropia,
        ':me'  => $personalId
      ]);
      $conversationId = (int)$pdo->lastInsertId();

      $st = $pdo->prepare("
        INSERT INTO chat_participantes (conversacion_id, personal_id)
        VALUES (:cid, :pid)
      ");
      $st->execute([':cid' => $conversationId, ':pid' => $personalId]);
      $st->execute([':cid' => $conversationId, ':pid' => $targetId]);

      $pdo->commit();

      json_out([
        'ok' => true,
        'conversation_id' => $conversationId,
        'title' => nombre_personal($target)
      ]);
    }

    if ($action === 'get_messages') {
      $conversationId = (int)($_GET['conversation_id'] ?? $_POST['conversation_id'] ?? 0);
      if ($conversationId <= 0) {
        json_out(['ok' => false, 'error' => 'Conversación inválida.'], 422);
      }

      if (!has_conversation_access($pdo, $conversationId, $personalId, $unidadPropia)) {
        json_out(['ok' => false, 'error' => 'No tenés acceso a esta conversación.'], 403);
      }

      $st = $pdo->prepare("
        SELECT
          m.id,
          m.conversacion_id,
          m.personal_id,
          m.mensaje,
          m.created_at,
          p.dni,
          p.grado,
          p.arma,
          p.apellido,
          p.nombre,
          p.apellido_nombre
        FROM chat_mensajes m
        INNER JOIN personal_unidad p ON p.id = m.personal_id
        WHERE m.conversacion_id = :cid
        ORDER BY m.id ASC
        LIMIT 200
      ");
      $st->execute([':cid' => $conversationId]);

      $rows = [];
      $messageIds = [];

      while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        $rows[] = $r;
        $messageIds[] = (int)$r['id'];
      }

      $attachmentMap = get_attachment_map($pdo, $messageIds);

      $items = [];
      foreach ($rows as $r) {
        $mid = (int)$r['id'];
        $mine = ((int)$r['personal_id'] === $personalId);

        $items[] = [
          'id' => $mid,
          'conversation_id' => (int)$r['conversacion_id'],
          'personal_id' => (int)$r['personal_id'],
          'author' => nombre_personal($r),
          'message' => (string)$r['mensaje'],
          'created_at' => (string)$r['created_at'],
          'created_hm' => date('H:i', strtotime((string)$r['created_at'])),
          'mine' => $mine,
          'can_delete' => ($mine || $isAdmin),
          'attachments' => $attachmentMap[$mid] ?? [],
        ];
      }

      json_out(['ok' => true, 'items' => $items]);
    }

    if ($action === 'send_message') {
      $conversationId = (int)($_POST['conversation_id'] ?? 0);
      $mensaje = trim((string)($_POST['message'] ?? ''));

      if ($conversationId <= 0) {
        json_out(['ok' => false, 'error' => 'Conversación inválida.'], 422);
      }

      if (!has_conversation_access($pdo, $conversationId, $personalId, $unidadPropia)) {
        json_out(['ok' => false, 'error' => 'No tenés acceso a esta conversación.'], 403);
      }

      if ($conversationId === $generalConversationId && !can_write_general($roleCodigo)) {
        json_out(['ok' => false, 'error' => 'No tenés permiso para escribir en el chat general.'], 403);
      }

      $hasFiles = (
        isset($_FILES['files']) &&
        isset($_FILES['files']['name']) &&
        is_array($_FILES['files']['name'])
      );

      if ($mensaje === '' && !$hasFiles) {
        json_out(['ok' => false, 'error' => 'Escribí un mensaje o adjuntá al menos un archivo.'], 422);
      }

      if (mb_strlen($mensaje) > 4000) {
        json_out(['ok' => false, 'error' => 'El mensaje supera los 4000 caracteres.'], 422);
      }

      $blockedExt = ['php','phtml','phar','exe','bat','cmd','com','js','vbs','ps1','msi','sh'];
      $maxBytes = 15 * 1024 * 1024;

      $pdo->beginTransaction();

      $st = $pdo->prepare("
        INSERT INTO chat_mensajes (conversacion_id, personal_id, mensaje)
        VALUES (:cid, :pid, :msg)
      ");
      $st->execute([
        ':cid' => $conversationId,
        ':pid' => $personalId,
        ':msg' => $mensaje
      ]);
      $messageId = (int)$pdo->lastInsertId();

      if ($hasFiles) {
        $names    = $_FILES['files']['name'] ?? [];
        $tmpNames = $_FILES['files']['tmp_name'] ?? [];
        $errors   = $_FILES['files']['error'] ?? [];
        $sizes    = $_FILES['files']['size'] ?? [];

        $count = is_array($names) ? count($names) : 0;
        if ($count > 10) {
          throw new RuntimeException('Podés adjuntar hasta 10 archivos por mensaje.');
        }

        $storage = build_chat_storage($pdo, $unidadPropia, $conversationId);

        for ($i = 0; $i < $count; $i++) {
          $err = (int)($errors[$i] ?? UPLOAD_ERR_NO_FILE);
          if ($err === UPLOAD_ERR_NO_FILE) {
            continue;
          }
          if ($err !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Error subiendo uno de los archivos.');
          }

          $orig = (string)($names[$i] ?? 'archivo');
          $tmp  = (string)($tmpNames[$i] ?? '');
          $size = (int)($sizes[$i] ?? 0);

          if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new RuntimeException('Archivo inválido.');
          }
          if ($size <= 0) {
            throw new RuntimeException('Uno de los archivos está vacío.');
          }
          if ($size > $maxBytes) {
            throw new RuntimeException('Uno de los archivos supera el límite de 15 MB.');
          }

          $safeOriginal = safe_file_name($orig);
          $ext = strtolower((string)pathinfo($safeOriginal, PATHINFO_EXTENSION));

          if ($ext !== '' && in_array($ext, $blockedExt, true)) {
            throw new RuntimeException('Tipo de archivo no permitido: ' . $safeOriginal);
          }

          $storedName = date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . ($ext !== '' ? ('.' . $ext) : '');
          $absDest = $storage['abs_dir'] . '/' . $storedName;
          $relDest = $storage['rel_dir'] . '/' . $storedName;

          if (!move_uploaded_file($tmp, $absDest)) {
            throw new RuntimeException('No se pudo guardar el archivo adjunto.');
          }

          $mime = detect_mime_type($absDest);

          $st = $pdo->prepare("
            INSERT INTO chat_archivos
              (mensaje_id, nombre_original, nombre_guardado, ruta_relativa, mime_type, tamano_bytes)
            VALUES
              (:mid, :no, :ng, :rr, :mt, :tb)
          ");
          $st->execute([
            ':mid' => $messageId,
            ':no'  => $safeOriginal,
            ':ng'  => $storedName,
            ':rr'  => $relDest,
            ':mt'  => $mime,
            ':tb'  => $size,
          ]);
        }
      }

      $st = $pdo->prepare("
        UPDATE chat_conversaciones
        SET updated_at = NOW()
        WHERE id = :id
      ");
      $st->execute([':id' => $conversationId]);

      $pdo->commit();

      json_out(['ok' => true]);
    }

    if ($action === 'delete_message') {
      $messageId = (int)($_POST['message_id'] ?? 0);
      if ($messageId <= 0) {
        json_out(['ok' => false, 'error' => 'Mensaje inválido.'], 422);
      }

      $st = $pdo->prepare("
        SELECT id, conversacion_id, personal_id
        FROM chat_mensajes
        WHERE id = :id
        LIMIT 1
      ");
      $st->execute([':id' => $messageId]);
      $msg = $st->fetch(PDO::FETCH_ASSOC);

      if (!$msg) {
        json_out(['ok' => false, 'error' => 'El mensaje no existe.'], 404);
      }

      $conversationId = (int)$msg['conversacion_id'];

      if (!has_conversation_access($pdo, $conversationId, $personalId, $unidadPropia)) {
        json_out(['ok' => false, 'error' => 'No tenés acceso a esta conversación.'], 403);
      }

      $isMine = ((int)$msg['personal_id'] === $personalId);
      if (!$isMine && !$isAdmin) {
        json_out(['ok' => false, 'error' => 'No tenés permiso para borrar este mensaje.'], 403);
      }

      $pdo->beginTransaction();

      delete_message_attachments($pdo, $messageId);

      $st = $pdo->prepare("DELETE FROM chat_mensajes WHERE id = :id");
      $st->execute([':id' => $messageId]);

      $st = $pdo->prepare("
        UPDATE chat_conversaciones
        SET updated_at = NOW()
        WHERE id = :id
      ");
      $st->execute([':id' => $conversationId]);

      $pdo->commit();

      json_out(['ok' => true]);
    }

    if ($action === 'delete_conversation') {
      $conversationId = (int)($_POST['conversation_id'] ?? 0);
      if ($conversationId <= 0) {
        json_out(['ok' => false, 'error' => 'Conversación inválida.'], 422);
      }

      $c = get_conversation_row($pdo, $conversationId);
      if (!$c) {
        json_out(['ok' => false, 'error' => 'La conversación no existe.'], 404);
      }

      if ((string)$c['tipo'] === 'general') {
        json_out(['ok' => false, 'error' => 'El chat general no se puede borrar.'], 403);
      }

      if (!has_conversation_access($pdo, $conversationId, $personalId, $unidadPropia)) {
        json_out(['ok' => false, 'error' => 'No tenés acceso a esta conversación.'], 403);
      }

      $pdo->beginTransaction();

      delete_conversation_files($pdo, $conversationId);

      $st = $pdo->prepare("
        DELETE a
        FROM chat_archivos a
        INNER JOIN chat_mensajes m ON m.id = a.mensaje_id
        WHERE m.conversacion_id = :cid
      ");
      $st->execute([':cid' => $conversationId]);

      $st = $pdo->prepare("DELETE FROM chat_mensajes WHERE conversacion_id = :cid");
      $st->execute([':cid' => $conversationId]);

      $st = $pdo->prepare("DELETE FROM chat_participantes WHERE conversacion_id = :cid");
      $st->execute([':cid' => $conversationId]);

      $st = $pdo->prepare("DELETE FROM chat_conversaciones WHERE id = :cid");
      $st->execute([':cid' => $conversationId]);

      $pdo->commit();

      json_out([
        'ok' => true,
        'next_conversation_id' => $generalConversationId
      ]);
    }

    json_out(['ok' => false, 'error' => 'Acción no válida.'], 400);

  } catch (Throwable $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    json_out([
      'ok' => false,
      'error' => 'Error interno: ' . $e->getMessage()
    ], 500);
  }
}

/* ==========================================================
   RENDER
   ========================================================== */
$SELF_WEB        = (string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
$BASE_PUBLIC_WEB = rtrim(str_replace('\\','/', dirname($SELF_WEB)), '/');
$BASE_APP_WEB    = rtrim(str_replace('\\','/', dirname($BASE_PUBLIC_WEB)), '/');
$ASSET_WEB       = $BASE_APP_WEB . '/assets';

$IMG_BG   = $ASSET_WEB . '/img/fondo.png';
$ESCUDO   = $ASSET_WEB . '/img/ecmilm.png';
$FAVICON  = $ASSET_WEB . '/img/ecmilm.png';

$CHAT_BG_FS_UPPER = __DIR__ . '/../assets/img/ecmilm2026.PNG';
$CHAT_BG_FS_LOWER = __DIR__ . '/../assets/img/ecmilm2026.png';
if (is_file($CHAT_BG_FS_UPPER)) {
  $CHAT_BG = $ASSET_WEB . '/img/ecmilm2026.PNG';
} elseif (is_file($CHAT_BG_FS_LOWER)) {
  $CHAT_BG = $ASSET_WEB . '/img/ecmilm2026.png';
} else {
  $CHAT_BG = $IMG_BG;
}

$embeddedMode = (
  (isset($_GET['embebido']) && $_GET['embebido'] === '1') ||
  (isset($_GET['embed']) && $_GET['embed'] === '1')
);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Chat</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= e($ASSET_WEB) ?>/css/theme-602.css">
<link rel="icon" type="image/png" href="<?= e($FAVICON) ?>">

<style>
  html, body { height: 100%; }
  body{
    margin:0;
    color:#e5e7eb;
    background:#000;
    font-family:system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,sans-serif;
  }

  .page-bg{
    position:fixed; inset:0; z-index:-2; pointer-events:none;
    background:
      linear-gradient(160deg, rgba(0,0,0,.68) 0%, rgba(0,0,0,.42) 55%, rgba(0,0,0,.70) 100%),
      url("<?= e($IMG_BG) ?>") center/cover no-repeat;
    background-attachment: fixed, fixed;
  }

  .container-main{ max-width:1450px; margin:auto; padding:18px; }

  .panel{
    background:rgba(6,10,18,.46);
    border:1px solid rgba(148,163,184,.34);
    border-radius:18px;
    padding:16px;
    box-shadow:0 18px 40px rgba(0,0,0,.55), inset 0 1px 0 rgba(255,255,255,.05);
    backdrop-filter:blur(8px);
  }

  .chat-layout{
    display:grid;
    grid-template-columns: 340px 1fr;
    gap:16px;
    min-height: calc(100vh - 150px);
  }

  @media (max-width: 992px){
    .chat-layout{ grid-template-columns: 1fr; }
  }

  .sidebar, .chat-main{
    background:rgba(2,6,23,.36);
    border:1px solid rgba(148,163,184,.28);
    border-radius:16px;
    overflow:hidden;
    backdrop-filter: blur(10px);
  }

  .sidebar-head, .chat-head{
    padding:14px 16px;
    border-bottom:1px solid rgba(148,163,184,.18);
    background:rgba(8,12,22,.38);
    backdrop-filter: blur(8px);
  }

  .chat-head-top{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
  }

  .head-actions{
    display:flex;
    gap:8px;
    align-items:center;
  }

  .sidebar-title, .chat-title{
    font-weight:900;
    letter-spacing:.03em;
  }

  .sidebar-body{ padding:12px; }

  .list-scroll{
    max-height: calc(100vh - 280px);
    overflow:auto;
    padding-right:4px;
  }

  .conv-item, .user-item{
    width:100%;
    text-align:left;
    border:1px solid rgba(148,163,184,.14);
    background:rgba(7,11,20,.46);
    color:#e5e7eb;
    border-radius:14px;
    padding:10px 12px;
    margin-bottom:10px;
    transition:.18s ease;
  }

  .conv-item:hover, .user-item:hover{
    background:rgba(34,197,94,.10);
    border-color:rgba(34,197,94,.28);
    color:#fff;
  }

  .conv-item.active{
    background:rgba(34,197,94,.18);
    border-color:rgba(34,197,94,.45);
    box-shadow:0 0 0 1px rgba(34,197,94,.12) inset;
  }

  .conv-title, .user-title{
    font-weight:800;
    font-size:.92rem;
  }

  .conv-sub, .conv-last, .user-sub{
    color:#c4d0e2;
    font-size:.78rem;
  }

  .conv-badges{
    display:flex;
    gap:6px;
    align-items:center;
    flex-wrap:wrap;
  }

  .chat-main{
    display:flex;
    flex-direction:column;
    min-height: calc(100vh - 190px);
  }

  .chat-head small{ color:#c4d0e2; }

  .messages-box{
    position:relative;
    flex:1;
    overflow:auto;
    padding:18px 18px 16px;
    background:
      linear-gradient(180deg, rgba(2,6,23,.54), rgba(2,6,23,.44)),
      rgba(0,8,28,.18);
  }

  .messages-box::before{
    content:"";
    position:absolute;
    inset:0;
    background:
      radial-gradient(circle at center, rgba(255,255,255,.04), transparent 58%),
      linear-gradient(180deg, rgba(2,6,23,.10), rgba(2,6,23,.22)),
      url("<?= e($CHAT_BG) ?>") center 42% / min(72%, 860px) auto no-repeat;
    opacity:.16;
    pointer-events:none;
  }

  .messages-box > *{
    position:relative;
    z-index:1;
  }

  .empty-box{
    height:100%;
    display:flex;
    align-items:center;
    justify-content:center;
    color:#d8e2f0;
    text-align:center;
    font-weight:700;
    opacity:.92;
  }

  .msg-row{
    display:flex;
    flex-direction:column;
    margin-bottom:14px;
  }

  .msg-row.me{ align-items:flex-end; }
  .msg-row.other{ align-items:flex-start; }

  .msg-meta{
    display:flex;
    align-items:center;
    gap:8px;
    font-size:.72rem;
    color:#d7dfec;
    margin-bottom:4px;
    font-weight:700;
  }

  .msg-actions{
    display:inline-flex;
    gap:6px;
    margin-left:6px;
  }

  .msg-action-btn{
    border:none;
    border-radius:999px;
    padding:.1rem .45rem;
    font-size:.68rem;
    font-weight:800;
    color:#fff;
    background:rgba(127,29,29,.78);
  }

  .msg-pack{
    max-width:min(78%, 760px);
  }

  .bubble{
    padding:11px 14px;
    border-radius:16px;
    line-height:1.48;
    white-space:pre-wrap;
    word-break:break-word;
    font-size:.92rem;
    border:1px solid rgba(148,163,184,.18);
    display:inline-block;
    box-shadow:0 8px 18px rgba(0,0,0,.18);
  }

  .bubble.me{
    background:linear-gradient(180deg, rgba(34,197,94,.92), rgba(22,163,74,.92));
    color:#08130b;
    border-top-right-radius:6px;
    font-weight:700;
  }

  .bubble.other{
    background:rgba(9,13,24,.48);
    color:#e5e7eb;
    border-top-left-radius:6px;
  }

  .msg-files{
    margin-top:6px;
    display:flex;
    flex-direction:column;
    gap:6px;
  }

  .file-chip{
    display:inline-flex;
    align-items:center;
    gap:8px;
    width:max-content;
    max-width:100%;
    text-decoration:none;
    color:#eef2f7;
    background:rgba(15,23,42,.56);
    border:1px solid rgba(148,163,184,.18);
    border-radius:12px;
    padding:8px 10px;
    font-size:.82rem;
  }

  .file-chip:hover{
    background:rgba(34,197,94,.14);
    color:#fff;
    border-color:rgba(34,197,94,.25);
  }

  .file-chip small{
    display:block;
    color:#b8c5d9;
  }

  .chat-foot{
    border-top:1px solid rgba(148,163,184,.18);
    background:rgba(8,12,22,.34);
    padding:12px;
    backdrop-filter: blur(8px);
  }

  .readonly-box{
    border-top:1px solid rgba(148,163,184,.18);
    background:rgba(127,29,29,.18);
    color:#fecaca;
    padding:12px 16px;
    text-align:center;
    font-weight:800;
  }

  .top-actions{
    display:flex;
    gap:8px;
    align-items:center;
    justify-content:space-between;
  }

  .search-box{
    margin:10px 0 12px;
  }

  .badge-role{
    display:inline-block;
    padding:.18rem .55rem;
    border-radius:999px;
    background:rgba(148,163,184,.18);
    border:1px solid rgba(148,163,184,.22);
    font-size:.72rem;
    font-weight:800;
    color:#e5e7eb;
  }

  .badge-new{
    display:inline-block;
    padding:.18rem .55rem;
    border-radius:999px;
    background:rgba(220,38,38,.88);
    border:1px solid rgba(254,202,202,.22);
    font-size:.70rem;
    font-weight:900;
    color:#fff;
  }

  .btn-top{
    font-weight:700;
    padding:.35rem .9rem;
  }

  .composer{
    display:flex;
    gap:10px;
    align-items:flex-end;
  }

  .composer-grow{
    flex:1;
  }

  .composer-tools{
    display:flex;
    align-items:center;
    gap:8px;
    margin-top:8px;
    flex-wrap:wrap;
  }

  .file-counter{
    font-size:.78rem;
    color:#d5dfed;
    font-weight:700;
  }

  body.embedded-mode{
    background:transparent;
  }

  body.embedded-mode .page-bg,
  body.embedded-mode .brand-hero{
    display:none;
  }

  body.embedded-mode .container-main{
    max-width:none;
    padding:0;
    height:100%;
  }

  body.embedded-mode .panel{
    height:100%;
    padding:10px;
    border-radius:0;
    background:rgba(6,10,18,.82);
    border:none;
    box-shadow:none;
  }

  body.embedded-mode .chat-layout{
    grid-template-columns:1fr;
    min-height:100%;
    height:100%;
  }

  body.embedded-mode .sidebar .list-scroll{
    max-height:210px;
  }

  body.embedded-mode .chat-main{
    min-height:0;
    height:100%;
  }
</style>
</head>
<body class="<?= $embeddedMode ? 'embedded-mode' : '' ?>">
<div class="page-bg"></div>

<?php if (!$embeddedMode): ?>
<header class="brand-hero">
  <div class="hero-inner container-main" style="padding-top:0; padding-bottom:0; display:flex; align-items:center;">
    <img class="brand-logo" src="<?= e($ESCUDO) ?>" alt="EA" style="height:52px;width:auto;"
         onerror="this.onerror=null;this.src='<?= e($ASSET_WEB) ?>/img/EA.png';">

    <div>
      <div class="brand-title">Escuela Militar de Montaña</div>
      <div class="brand-sub">"La Montaña nos une"</div>
    </div>

    <div style="margin-left:auto; margin-right:17px; text-align:right; font-size:.85rem;">
      <div><strong><?= e($fullNameDB) ?></strong></div>
      <div class="mt-2 d-flex gap-2 justify-content-end">
        <a href="inicio.php" class="btn btn-success btn-sm btn-top">Volver</a>
        <a href="<?= e($BASE_APP_WEB) ?>/logout.php" class="btn btn-success btn-sm btn-top">Cerrar sesión</a>
      </div>
    </div>
  </div>
</header>
<?php endif; ?>

<div class="container-main">
  <div class="panel">
    <div class="chat-layout">

      <aside class="sidebar">
        <div class="sidebar-head">
          <div class="top-actions">
            <div>
              <div class="sidebar-title">Conversaciones</div>
              <small class="text-muted">Unidad actual</small>
            </div>
            <div class="d-flex gap-2">
              <button id="btnMyNotes" type="button" class="btn btn-success btn-sm btn-top">Mis notas</button>
              <button id="btnToggleUsers" type="button" class="btn btn-success btn-sm btn-top">Nuevo chat</button>
            </div>
          </div>
        </div>

        <div class="sidebar-body">
          <div id="usersPane" class="d-none">
            <div class="search-box">
              <input type="text" id="userSearch" class="form-control form-control-sm" placeholder="Buscar usuario...">
            </div>
            <div id="usersList" class="list-scroll"></div>
            <hr style="border-color:rgba(148,163,184,.18);">
          </div>

          <div id="conversationsList" class="list-scroll"></div>
        </div>
      </aside>

      <section class="chat-main">
        <div class="chat-head">
          <div class="chat-head-top">
            <div>
              <div class="chat-title" id="chatTitle">Cargando...</div>
              <small id="chatSubtitle">Espere un momento</small>
            </div>
            <div class="head-actions">
              <button type="button" id="btnDeleteConversation" class="btn btn-danger btn-sm d-none">Borrar chat</button>
            </div>
          </div>
        </div>

        <div id="messagesBox" class="messages-box">
          <div class="empty-box">Cargando mensajes...</div>
        </div>

        <div id="readOnlyBox" class="readonly-box d-none">
          Solo ADMIN y SUPERADMIN pueden escribir en el chat general.
        </div>

        <div class="chat-foot" id="chatFoot">
          <form id="formSend" class="composer" enctype="multipart/form-data" autocomplete="off">
            <div class="composer-grow">
              <input type="text" id="messageInput" class="form-control" maxlength="4000" placeholder="Escribí un mensaje...">
              <div class="composer-tools">
                <input type="file" id="fileInput" class="d-none" multiple>
                <button type="button" id="btnAttach" class="btn btn-outline-light btn-sm">Adjuntar</button>
                <span id="fileCounter" class="file-counter">Sin archivos</span>
              </div>
            </div>
            <button type="submit" class="btn btn-success btn-top">Enviar</button>
          </form>
        </div>
      </section>

    </div>
  </div>
</div>

<script>
const CHAT_URL = 'chat.php';
const CAN_WRITE_GENERAL = <?= can_write_general($roleCodigo) ? 'true' : 'false' ?>;
const CSRF_TOKEN = <?= json_encode(csrf_token(), JSON_UNESCAPED_UNICODE) ?>;
const EMBEDDED_MODE = <?= $embeddedMode ? 'true' : 'false' ?>;
const MY_PERSONAL_ID = <?= (int)$personalId ?>;
const BASE_TITLE = document.title;

const state = {
  selectedConversationId: null,
  conversations: [],
  users: [],
  pollingHandle: null,
  seenMap: {},
  unreadMap: {},
  baselineLoaded: false,
};

function escapeHtml(str) {
  return String(str ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function bytesToText(bytes) {
  const n = Number(bytes || 0);
  if (n < 1024) return `${n} B`;
  if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`;
  return `${(n / (1024 * 1024)).toFixed(1)} MB`;
}

function notifyParentUnread() {
  const unreadCount = Object.keys(state.unreadMap).length;
  document.title = unreadCount > 0 ? `(${unreadCount}) ${BASE_TITLE}` : BASE_TITLE;

  if (EMBEDDED_MODE && window.parent && window.parent !== window) {
    window.parent.postMessage({
      type: 'ea_chat_unread',
      unread: unreadCount
    }, '*');
  }
}

function markConversationSeen(conversationId) {
  const c = state.conversations.find(x => Number(x.id) === Number(conversationId));
  if (!c) return;

  state.seenMap[conversationId] = Number(c.last_message_id || 0);
  delete state.unreadMap[conversationId];
  notifyParentUnread();
}

function processUnread() {
  if (!state.baselineLoaded) {
    state.conversations.forEach(c => {
      state.seenMap[c.id] = Number(c.last_message_id || 0);
    });
    state.baselineLoaded = true;
    notifyParentUnread();
    return;
  }

  state.conversations.forEach(c => {
    const currentId = Number(c.last_message_id || 0);
    const seenId = Number(state.seenMap[c.id] || 0);
    const isSelected = Number(c.id) === Number(state.selectedConversationId);

    if (currentId > seenId) {
      if (isSelected) {
        state.seenMap[c.id] = currentId;
        delete state.unreadMap[c.id];
      } else if (!c.last_from_me && currentId > 0) {
        state.unreadMap[c.id] = true;
      } else {
        state.seenMap[c.id] = currentId;
      }
    }
  });

  notifyParentUnread();
}

async function apiGet(action, params = {}) {
  const qs = new URLSearchParams({ ajax: '1', action, ...params });
  const res = await fetch(`${CHAT_URL}?${qs.toString()}`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  });
  return await res.json();
}

async function apiPost(action, params = {}) {
  const body = new URLSearchParams({ action, _csrf: CSRF_TOKEN, ...params });
  const res = await fetch(`${CHAT_URL}?ajax=1`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
      'X-Requested-With': 'XMLHttpRequest'
    },
    body: body.toString()
  });
  return await res.json();
}

async function apiPostFormData(action, formData) {
  formData.append('action', action);
  formData.append('_csrf', CSRF_TOKEN);

  const res = await fetch(`${CHAT_URL}?ajax=1`, {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    body: formData
  });
  return await res.json();
}

function selectedConversation() {
  return state.conversations.find(c => Number(c.id) === Number(state.selectedConversationId)) || null;
}

function updateFileCounter() {
  const input = document.getElementById('fileInput');
  const counter = document.getElementById('fileCounter');
  const total = input?.files?.length || 0;
  counter.textContent = total ? `${total} archivo(s) listo(s)` : 'Sin archivos';
}

function renderFileList(files = []) {
  if (!files.length) return '';
  return `
    <div class="msg-files">
      ${files.map(f => `
        <a class="file-chip" href="${escapeHtml(f.url || '#')}" target="_blank" rel="noopener noreferrer">
          <span>📎</span>
          <span>
            ${escapeHtml(f.name || 'Archivo')}
            <small>${escapeHtml(bytesToText(f.size || 0))}</small>
          </span>
        </a>
      `).join('')}
    </div>
  `;
}

function setChatHeader() {
  const c = selectedConversation();
  const title = document.getElementById('chatTitle');
  const sub = document.getElementById('chatSubtitle');
  const readOnlyBox = document.getElementById('readOnlyBox');
  const chatFoot = document.getElementById('chatFoot');
  const btnDeleteConversation = document.getElementById('btnDeleteConversation');

  if (!c) {
    title.textContent = 'Sin conversación';
    sub.textContent = '';
    readOnlyBox.classList.add('d-none');
    chatFoot.classList.remove('d-none');
    btnDeleteConversation.classList.add('d-none');
    return;
  }

  title.textContent = c.title || 'Conversación';
  sub.textContent = c.type === 'general'
    ? 'Mensajes generales de la unidad'
    : (c.is_self ? 'Tus notas personales' : 'Conversación privada');

  const isReadOnly = (c.type === 'general' && !CAN_WRITE_GENERAL);
  readOnlyBox.classList.toggle('d-none', !isReadOnly);
  chatFoot.classList.toggle('d-none', isReadOnly);

  btnDeleteConversation.classList.toggle('d-none', !(c.deletable));
}

function renderConversations() {
  const box = document.getElementById('conversationsList');
  if (!state.conversations.length) {
    box.innerHTML = `<div class="text-muted">No hay conversaciones.</div>`;
    return;
  }

  box.innerHTML = state.conversations.map(c => `
    <button type="button"
            class="conv-item ${Number(c.id) === Number(state.selectedConversationId) ? 'active' : ''}"
            data-id="${Number(c.id)}">
      <div class="d-flex justify-content-between align-items-start gap-2">
        <div>
          <div class="conv-title">${escapeHtml(c.title || 'Conversación')}</div>
          <div class="conv-sub">${escapeHtml(c.subtitle || '')}</div>
        </div>
        <div class="conv-badges">
          ${state.unreadMap[c.id] ? `<span class="badge-new">NUEVO</span>` : ''}
          <span class="badge-role">${c.type === 'general' ? 'GENERAL' : (c.is_self ? 'NOTAS' : 'PRIVADO')}</span>
        </div>
      </div>
      <div class="conv-last mt-2">${escapeHtml(c.last_message || 'Sin mensajes todavía')}</div>
    </button>
  `).join('');

  box.querySelectorAll('.conv-item').forEach(btn => {
    btn.addEventListener('click', async () => {
      state.selectedConversationId = Number(btn.dataset.id);
      markConversationSeen(state.selectedConversationId);
      renderConversations();
      setChatHeader();
      await loadMessages(true);
    });
  });
}

function renderUsers(filter = '') {
  const q = String(filter || '').trim().toLowerCase();
  const box = document.getElementById('usersList');

  const items = state.users.filter(u => {
    const txt = `${u.display_name || ''} ${u.dni || ''}`.toLowerCase();
    return txt.includes(q);
  });

  if (!items.length) {
    box.innerHTML = `<div class="text-muted">No hay usuarios para mostrar.</div>`;
    return;
  }

  box.innerHTML = items.map(u => `
    <button type="button" class="user-item" data-id="${Number(u.id)}">
      <div class="user-title">${escapeHtml(u.display_name || 'Usuario')}</div>
      <div class="user-sub">${u.is_self ? 'Anotador personal' : ('DNI: ' + escapeHtml(u.dni || ''))}</div>
    </button>
  `).join('');

  box.querySelectorAll('.user-item').forEach(btn => {
    btn.addEventListener('click', async () => {
      const targetId = Number(btn.dataset.id);
      const r = await apiPost('start_private', { target_id: targetId });
      if (!r.ok) {
        alert(r.error || 'No se pudo iniciar el chat.');
        return;
      }
      await loadConversations(Number(r.conversation_id));
      document.getElementById('usersPane').classList.add('d-none');
      await loadMessages(true);
    });
  });
}

async function loadUsers() {
  const r = await apiGet('list_users');
  if (!r.ok) {
    alert(r.error || 'No se pudo cargar el personal.');
    return;
  }
  state.users = r.items || [];
  renderUsers(document.getElementById('userSearch').value);
}

async function loadConversations(preferId = null) {
  const r = await apiGet('list_conversations');
  if (!r.ok) {
    alert(r.error || 'No se pudieron cargar las conversaciones.');
    return;
  }

  state.conversations = r.items || [];

  if (preferId) {
    state.selectedConversationId = Number(preferId);
  } else if (!state.selectedConversationId && state.conversations.length) {
    state.selectedConversationId = Number(state.conversations[0].id);
  } else {
    const exists = state.conversations.some(c => Number(c.id) === Number(state.selectedConversationId));
    if (!exists && state.conversations.length) {
      state.selectedConversationId = Number(state.conversations[0].id);
    }
  }

  processUnread();
  renderConversations();
  setChatHeader();
}

async function loadMessages(scrollBottom = false) {
  if (!state.selectedConversationId) return;

  const r = await apiGet('get_messages', { conversation_id: state.selectedConversationId });
  const box = document.getElementById('messagesBox');

  if (!r.ok) {
    box.innerHTML = `<div class="empty-box">${escapeHtml(r.error || 'No se pudieron cargar los mensajes.')}</div>`;
    return;
  }

  const items = r.items || [];

  if (!items.length) {
    box.innerHTML = `<div class="empty-box">No hay mensajes todavía.</div>`;
    markConversationSeen(state.selectedConversationId);
    return;
  }

  box.innerHTML = items.map(m => `
    <div class="msg-row ${m.mine ? 'me' : 'other'}">
      <div class="msg-meta">
        <span>${escapeHtml(m.mine ? 'Yo' : m.author)} · ${escapeHtml(m.created_hm || '')}</span>
        ${(m.can_delete) ? `
          <span class="msg-actions">
            <button type="button" class="msg-action-btn btn-delete-message" data-id="${Number(m.id)}">Borrar</button>
          </span>
        ` : ''}
      </div>

      <div class="msg-pack">
        ${m.message ? `<div class="bubble ${m.mine ? 'me' : 'other'}">${escapeHtml(m.message || '')}</div>` : ''}
        ${renderFileList(m.attachments || [])}
      </div>
    </div>
  `).join('');

  box.querySelectorAll('.btn-delete-message').forEach(btn => {
    btn.addEventListener('click', async () => {
      const id = Number(btn.dataset.id);
      if (!confirm('¿Borrar este mensaje?')) return;

      const r = await apiPost('delete_message', { message_id: id });
      if (!r.ok) {
        alert(r.error || 'No se pudo borrar el mensaje.');
        return;
      }

      await loadMessages(false);
      await loadConversations(state.selectedConversationId);
    });
  });

  markConversationSeen(state.selectedConversationId);
  renderConversations();

  if (scrollBottom) {
    box.scrollTop = box.scrollHeight;
  }
}

async function sendMessage(ev) {
  ev.preventDefault();

  const input = document.getElementById('messageInput');
  const fileInput = document.getElementById('fileInput');
  const text = input.value.trim();
  const files = fileInput.files;

  if (!state.selectedConversationId) return;
  if (!text && (!files || !files.length)) return;

  const fd = new FormData();
  fd.append('conversation_id', String(state.selectedConversationId));
  fd.append('message', text);

  if (files && files.length) {
    for (const f of files) {
      fd.append('files[]', f);
    }
  }

  const r = await apiPostFormData('send_message', fd);

  if (!r.ok) {
    alert(r.error || 'No se pudo enviar el mensaje.');
    return;
  }

  input.value = '';
  fileInput.value = '';
  updateFileCounter();

  await loadMessages(true);
  await loadConversations(state.selectedConversationId);
}

async function openMyNotes() {
  const r = await apiPost('start_private', { target_id: MY_PERSONAL_ID });
  if (!r.ok) {
    alert(r.error || 'No se pudo abrir Mis notas.');
    return;
  }
  await loadConversations(Number(r.conversation_id));
  document.getElementById('usersPane').classList.add('d-none');
  await loadMessages(true);
}

async function deleteCurrentConversation() {
  const c = selectedConversation();
  if (!c || !c.deletable) return;

  const txt = c.is_self
    ? '¿Borrar tus notas personales?'
    : '¿Borrar este chat privado?';

  if (!confirm(txt)) return;

  const r = await apiPost('delete_conversation', {
    conversation_id: state.selectedConversationId
  });

  if (!r.ok) {
    alert(r.error || 'No se pudo borrar la conversación.');
    return;
  }

  state.selectedConversationId = Number(r.next_conversation_id || 0);
  delete state.unreadMap[c.id];
  await loadConversations(state.selectedConversationId);
  await loadMessages(true);
}

document.addEventListener('DOMContentLoaded', async () => {
  document.getElementById('btnToggleUsers').addEventListener('click', async () => {
    const pane = document.getElementById('usersPane');
    pane.classList.toggle('d-none');
    if (!pane.classList.contains('d-none') && !state.users.length) {
      await loadUsers();
    }
  });

  document.getElementById('btnMyNotes').addEventListener('click', openMyNotes);
  document.getElementById('btnDeleteConversation').addEventListener('click', deleteCurrentConversation);

  document.getElementById('userSearch').addEventListener('input', (e) => {
    renderUsers(e.target.value);
  });

  document.getElementById('btnAttach').addEventListener('click', () => {
    document.getElementById('fileInput').click();
  });

  document.getElementById('fileInput').addEventListener('change', updateFileCounter);
  document.getElementById('formSend').addEventListener('submit', sendMessage);

  await loadConversations();
  await loadMessages(true);

  state.pollingHandle = setInterval(async () => {
    if (!state.selectedConversationId) return;
    await loadConversations(state.selectedConversationId);
    await loadMessages(false);
  }, 3000);
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
