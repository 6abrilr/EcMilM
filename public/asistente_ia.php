<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/bootstrap.php';
require_login();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/asistente_ia_helper.php';

ia_ensure_tables($pdo);

$ctx = ia_get_context($pdo);
$unidadId = (int)$ctx['unidad_id'];
$personalId = (int)$ctx['personal_id'];
$isAdmin = in_array((string)$ctx['role'], ['ADMIN', 'SUPERADMIN'], true);
$paths = ia_storage_paths($ctx);
$syncStats = ia_sync_documentacion_pdfs($pdo, $ctx);

$BASE_PUBLIC_WEB = ia_base_public_web();
$BASE_APP_WEB = ia_base_app_web();
$ASSET_WEB = $BASE_APP_WEB . '/assets';
$IMG_BG = $ASSET_WEB . '/img/fondo.png';
$ESCUDO = $ASSET_WEB . '/img/ecmilm.png';
$csrf = csrf_token();

if (isset($_GET['download']) && (int)$_GET['download'] === 1) {
    $id = (int)($_GET['id'] ?? 0);
    $st = $pdo->prepare("SELECT archivo_original, ruta_rel, mime FROM ia_reglamentos WHERE id = :id AND unidad_id = :uid LIMIT 1");
    $st->execute([':id' => $id, ':uid' => $unidadId]);
    $doc = $st->fetch(PDO::FETCH_ASSOC);
    if (!$doc) {
        http_response_code(404);
        exit('Reglamento no disponible.');
    }
    $root = realpath(__DIR__ . '/..');
    $abs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string)$doc['ruta_rel']);
    $real = realpath($abs);
    $storageRoot = realpath($paths['abs_dir']);
    if (!$real || !$storageRoot || !str_starts_with($real, $storageRoot) || !is_file($real)) {
        http_response_code(404);
        exit('Archivo no disponible.');
    }
    header('X-Content-Type-Options: nosniff');
    header('Content-Type: ' . ((string)$doc['mime'] ?: 'application/pdf'));
    header('Content-Disposition: inline; filename="' . rawurlencode((string)$doc['archivo_original']) . '"');
    header('Content-Length: ' . (string)filesize($real));
    readfile($real);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    csrf_verify();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'upload') {
        if (empty($_FILES['reglamento']) || !is_array($_FILES['reglamento'])) {
            ia_json(['ok' => false, 'error' => 'Seleccioná un PDF.']);
        }
        $file = $_FILES['reglamento'];
        if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            ia_json(['ok' => false, 'error' => 'No se pudo subir el archivo.']);
        }
        $original = ia_safe_filename((string)($file['name'] ?? 'reglamento.pdf'));
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            ia_json(['ok' => false, 'error' => 'Por ahora el asistente indexa reglamentos en PDF.']);
        }
        $size = (int)($file['size'] ?? 0);
        if ($size <= 0 || $size > 45 * 1024 * 1024) {
            ia_json(['ok' => false, 'error' => 'El PDF debe pesar menos de 45 MB.']);
        }

        $tmp = (string)($file['tmp_name'] ?? '');
        $sha = @sha1_file($tmp);
        if (!is_string($sha) || $sha === '') {
            ia_json(['ok' => false, 'error' => 'No se pudo leer el PDF subido.']);
        }

        $stDup = $pdo->prepare("SELECT id, titulo FROM ia_reglamentos WHERE unidad_id = :uid AND sha1_hash = :sha LIMIT 1");
        $stDup->execute([':uid' => $unidadId, ':sha' => $sha]);
        if ($dup = $stDup->fetch(PDO::FETCH_ASSOC)) {
            ia_json(['ok' => true, 'message' => 'Ese reglamento ya estaba cargado.', 'id' => (int)$dup['id']]);
        }

        $stored = date('Ymd_His') . '_' . $original;
        $abs = $paths['abs_dir'] . DIRECTORY_SEPARATOR . $stored;
        if (!@move_uploaded_file($tmp, $abs)) {
            ia_json(['ok' => false, 'error' => 'No se pudo guardar el PDF en storage.']);
        }

        $text = ia_pdf_extract_text($abs);
        if (mb_strlen($text, 'UTF-8') < 120) {
            @unlink($abs);
            ia_json([
                'ok' => false,
                'error' => 'El PDF se subió, pero no pude extraer texto suficiente. Puede ser un escaneo en imagen; para consultarlo hace falta OCR o una versión con texto seleccionable.',
            ]);
        }

        $titulo = trim((string)($_POST['titulo'] ?? ''));
        if ($titulo === '') {
            $titulo = preg_replace('/\.pdf$/i', '', str_replace('_', ' ', $original)) ?? $original;
        }
        $rel = $paths['rel_dir'] . '/' . $stored;

        try {
            $pdo->beginTransaction();
            $st = $pdo->prepare("
                INSERT INTO ia_reglamentos
                    (unidad_id, titulo, archivo_original, ruta_rel, mime, size_bytes, sha1_hash, texto, estado, uploaded_by)
                VALUES
                    (:uid, :titulo, :orig, :ruta, 'application/pdf', :size, :sha, :texto, 'indexado', :uploaded_by)
            ");
            $st->execute([
                ':uid' => $unidadId,
                ':titulo' => $titulo,
                ':orig' => $original,
                ':ruta' => $rel,
                ':size' => $size,
                ':sha' => $sha,
                ':texto' => $text,
                ':uploaded_by' => $personalId > 0 ? $personalId : null,
            ]);
            $regId = (int)$pdo->lastInsertId();
            ia_index_reglamento($pdo, $regId, $unidadId, $text);
            $pdo->commit();
            ia_json(['ok' => true, 'message' => 'Reglamento cargado e indexado.', 'id' => $regId]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            @unlink($abs);
            ia_json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    if ($action === 'ask') {
        $question = trim((string)($_POST['question'] ?? ''));
        if ($question === '') {
            ia_json(['ok' => false, 'error' => 'Escribí una pregunta.']);
        }
        $docIds = $_POST['doc_ids'] ?? [];
        if (!is_array($docIds)) {
            $docIds = [];
        }
        $docIds = array_values(array_filter(array_map('intval', $docIds), static fn($v) => $v > 0));
        if (ia_is_inventory_question($question)) {
            $answer = ia_answer_inventory($pdo, $unidadId, $question, $docIds);
        } else {
            $fragments = ia_search_fragments($pdo, $unidadId, $question, $docIds, 5);
            $answer = ia_build_answer($question, $fragments);
        }
        ia_json(['ok' => true, 'answer' => $answer['answer'], 'sources' => $answer['sources']]);
    }

    if ($action === 'delete') {
        if (!$isAdmin) {
            ia_json(['ok' => false, 'error' => 'Solo ADMIN/SUPERADMIN puede quitar reglamentos del índice.']);
        }
        $id = (int)($_POST['id'] ?? 0);
        $st = $pdo->prepare("SELECT ruta_rel FROM ia_reglamentos WHERE id = :id AND unidad_id = :uid LIMIT 1");
        $st->execute([':id' => $id, ':uid' => $unidadId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            ia_json(['ok' => false, 'error' => 'Reglamento no encontrado.']);
        }
        $pdo->prepare("DELETE FROM ia_reglamento_fragmentos WHERE reglamento_id = :id")->execute([':id' => $id]);
        $pdo->prepare("DELETE FROM ia_reglamentos WHERE id = :id AND unidad_id = :uid")->execute([':id' => $id, ':uid' => $unidadId]);
        ia_json(['ok' => true, 'message' => 'Reglamento quitado del índice. El PDF no se borró de DOCUMENTACION.']);
    }

    ia_json(['ok' => false, 'error' => 'Acción inválida.']);
}

$docs = [];
try {
    $st = $pdo->prepare("
        SELECT r.id, r.titulo, r.archivo_original, r.size_bytes, r.estado, r.error, r.ruta_rel, r.created_at,
               COUNT(f.id) AS fragmentos
        FROM ia_reglamentos r
        LEFT JOIN ia_reglamento_fragmentos f ON f.reglamento_id = r.id
        WHERE r.unidad_id = :uid
        GROUP BY r.id
        ORDER BY r.created_at DESC
    ");
    $st->execute([':uid' => $unidadId]);
    $docs = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $docs = [];
}

function ia_size(int $bytes): string
{
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return number_format($bytes / 1024, 1) . ' KB';
    return number_format($bytes / 1048576, 2) . ' MB';
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Asistente IA sobre reglamentos</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= ia_e($ASSET_WEB) ?>/css/theme-602.css">
<link rel="icon" href="<?= ia_e($ESCUDO) ?>">
<style>
  :root{
    --bg:#020617; --panel:rgba(15,23,42,.92); --panel2:rgba(2,6,23,.72);
    --line:rgba(148,163,184,.28); --text:#f8fafc; --soft:#cbd5e1; --muted:#94a3b8;
    --green:#22c55e; --cyan:#38bdf8; --danger:#ef4444;
  }
  *{box-sizing:border-box}
  body{
    min-height:100vh;margin:0;color:var(--text);
    font-family:system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,sans-serif;
    background:
      radial-gradient(circle at top left, rgba(56,189,248,.22), transparent 58%),
      radial-gradient(circle at bottom right, rgba(34,197,94,.16), transparent 58%),
      url("<?= ia_e($IMG_BG) ?>") center/cover fixed;
    background-color:var(--bg);
  }
  body::before{content:"";position:fixed;inset:0;background:rgba(2,6,23,.68);z-index:-1}
  .container-main{max-width:1380px;margin:0 auto;padding:18px}
  .brand{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:10px 18px}
  .brand-left{display:flex;align-items:center;gap:14px}
  .brand img{height:54px;filter:drop-shadow(0 0 10px #000)}
  .brand-title{font-weight:900;font-size:1.1rem}.brand-sub{color:var(--soft);font-size:.82rem}
  .btn-soft{border:1px solid var(--line);background:rgba(15,23,42,.82);color:#fff;border-radius:999px;padding:.5rem 1rem;text-decoration:none;font-weight:850}
  .btn-soft:hover{color:#fff;border-color:rgba(56,189,248,.7);background:rgba(30,41,59,.9)}
  .layout{display:grid;grid-template-columns:330px 1fr;gap:16px;height:calc(100vh - 96px);min-height:560px}
  @media(max-width:980px){.layout{grid-template-columns:1fr}}
  .panel{background:var(--panel);border:1px solid var(--line);border-radius:22px;box-shadow:0 22px 48px rgba(0,0,0,.7);overflow:hidden}
  .side{display:flex;flex-direction:column;min-height:0;height:100%}
  .side-head,.chat-head{padding:16px 18px;border-bottom:1px solid var(--line);background:rgba(2,6,23,.34)}
  .kicker{font-size:.74rem;font-weight:950;letter-spacing:.16em;text-transform:uppercase;color:var(--cyan)}
  h1,h2{margin:4px 0 0;font-weight:950}.sub{color:var(--soft);font-size:.88rem;margin-top:5px}
  .upload{padding:12px 18px;border-bottom:1px solid var(--line)}
  .form-control{background:#0f172a;border-color:rgba(148,163,184,.32);color:#fff}
  .form-control:focus{background:#111c31;color:#fff;border-color:var(--cyan);box-shadow:0 0 0 3px rgba(56,189,248,.16)}
  .btn-green{border:0;background:linear-gradient(135deg,#22c55e,#16a34a);color:#02140b;border-radius:12px;font-weight:950;padding:.58rem .9rem}
  .doc-list{padding:10px 12px;overflow:auto;min-height:0;flex:1}
  .doc-card{border:1px solid rgba(148,163,184,.22);border-radius:14px;background:rgba(2,6,23,.46);padding:10px;margin-bottom:8px}
  .doc-top{display:flex;align-items:flex-start;gap:9px}.doc-title{font-weight:900;color:#fff;line-height:1.2}.doc-meta{font-size:.75rem;color:var(--muted);margin-top:5px}
  .doc-actions{display:flex;gap:7px;flex-wrap:wrap;margin-top:10px}.mini-btn{font-size:.76rem;border-radius:999px;padding:.25rem .62rem;border:1px solid var(--line);background:rgba(15,23,42,.72);color:#e2e8f0;text-decoration:none}
  .mini-btn:hover{color:#fff;border-color:var(--cyan)}.mini-btn.danger{border-color:rgba(239,68,68,.45);color:#fecaca}
  .chat{display:grid;grid-template-rows:auto 1fr auto;min-height:0;height:100%}
  .messages{padding:18px;overflow:auto;min-height:0}
  .msg{max-width:86%;margin-bottom:14px}.msg.user{margin-left:auto}.bubble{border:1px solid var(--line);border-radius:18px;padding:12px 14px;white-space:pre-wrap;line-height:1.45}
  .user .bubble{background:rgba(34,197,94,.16);border-color:rgba(34,197,94,.35)}.bot .bubble{background:rgba(15,23,42,.76)}
  .msg-label{font-size:.72rem;color:var(--muted);font-weight:850;margin:0 0 4px 4px}.user .msg-label{text-align:right;margin-right:4px}
  .composer{padding:14px;border-top:1px solid var(--line);background:rgba(2,6,23,.58)}
  .composer-row{display:grid;grid-template-columns:1fr auto;gap:10px}
  .sources{margin-top:10px;display:flex;flex-wrap:wrap;gap:7px}.source-pill{font-size:.72rem;border:1px solid rgba(56,189,248,.32);background:rgba(14,165,233,.10);color:#dff6ff;border-radius:999px;padding:.22rem .55rem}
  .empty{padding:20px;text-align:center;color:var(--soft);border:1px dashed var(--line);border-radius:18px;background:rgba(15,23,42,.35)}
  .toastline{display:none;margin-top:10px;border-radius:12px;padding:9px 11px;font-size:.84rem;font-weight:800}
  .toastline.show{display:block}.ok{background:rgba(22,101,52,.6);color:#dcfce7;border:1px solid rgba(34,197,94,.35)}.err{background:rgba(127,29,29,.6);color:#fee2e2;border:1px solid rgba(248,113,113,.35)}
  @media(max-width:980px){.layout{height:auto}.side,.chat{height:auto}.doc-list{max-height:360px}.messages{min-height:360px}}
  @media(max-width:640px){.composer-row{grid-template-columns:1fr}.msg{max-width:100%}.brand{align-items:flex-start}.brand-left{align-items:flex-start}}
</style>
</head>
<body>
<header class="brand">
  <div class="brand-left">
    <img src="<?= ia_e($ESCUDO) ?>" alt="Escudo" onerror="this.onerror=null;this.src='<?= ia_e($ASSET_WEB) ?>/img/EA.png';">
    <div>
      <div class="brand-title"><?= ia_e($ctx['unidad']['nombre_completo']) ?></div>
      <div class="brand-sub"><?= ia_e($ctx['unidad']['subnombre']) ?></div>
    </div>
  </div>
  <a class="btn-soft" href="<?= ia_e($BASE_PUBLIC_WEB) ?>/inicio.php">Volver a Inicio</a>
</header>

<main class="container-main">
  <div class="layout">
    <aside class="panel side">
      <div class="side-head">
        <div class="kicker">Reglamentos</div>
        <h2>Base de consulta</h2>
        <div class="sub">Base: <code>DOCUMENTACION</code>. PDFs detectados: <?= (int)$syncStats['found'] ?>.</div>
      </div>

      <form id="uploadForm" class="upload">
        <input type="hidden" name="ajax" value="1">
        <input type="hidden" name="action" value="upload">
        <input type="hidden" name="_csrf" value="<?= ia_e($csrf) ?>">
        <label class="form-label small fw-bold text-light">Título del reglamento</label>
        <input class="form-control form-control-sm mb-2" name="titulo" placeholder="Ej: RFP 70-01">
        <label class="form-label small fw-bold text-light">PDF</label>
        <input class="form-control form-control-sm mb-2" type="file" name="reglamento" accept="application/pdf,.pdf" required>
        <button class="btn-green w-100" type="submit">Cargar en DOCUMENTACION e indexar</button>
        <div id="uploadMsg" class="toastline"></div>
      </form>

      <div class="doc-list" id="docList">
        <?php if (!$docs): ?>
          <div class="empty">Todavía no hay reglamentos cargados.</div>
        <?php else: ?>
          <?php foreach ($docs as $doc): ?>
            <article class="doc-card" data-doc-id="<?= (int)$doc['id'] ?>">
              <div class="doc-top">
                <input class="form-check-input doc-check" type="checkbox" value="<?= (int)$doc['id'] ?>" <?= ((string)$doc['estado'] === 'indexado' && (int)$doc['fragmentos'] > 0) ? 'checked' : 'disabled' ?>>
                <div style="min-width:0">
                  <div class="doc-title"><?= ia_e($doc['titulo']) ?></div>
                  <div class="doc-meta">
                    <?= ia_e(ia_size((int)$doc['size_bytes'])) ?> · <?= (int)$doc['fragmentos'] ?> fragmentos · <?= ia_e((string)$doc['estado']) ?> · <?= ia_e(date('d/m/Y H:i', strtotime((string)$doc['created_at']))) ?>
                  </div>
                  <?php if (!empty($doc['error'])): ?>
                    <div class="doc-meta" style="color:#fecaca;"><?= ia_e($doc['error']) ?></div>
                  <?php endif; ?>
                  <div class="doc-meta"><?= ia_e($doc['ruta_rel']) ?></div>
                </div>
              </div>
              <div class="doc-actions">
                <a class="mini-btn" target="_blank" rel="noopener" href="<?= ia_e($BASE_PUBLIC_WEB) ?>/asistente_ia.php?download=1&id=<?= (int)$doc['id'] ?>">Abrir PDF</a>
              </div>
            </article>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </aside>

    <section class="panel chat">
      <div class="chat-head">
        <div class="kicker">Asistente IA</div>
        <h1>Preguntale a tus reglamentos</h1>
        <div class="sub">Las respuestas salen de los PDFs indexados en DOCUMENTACION y muestran de qué reglamento salieron.</div>
      </div>

      <div id="messages" class="messages">
        <div class="msg bot">
          <div class="msg-label">Asistente</div>
          <div class="bubble">Estoy usando como biblioteca la carpeta DOCUMENTACION de la unidad. Podés preguntarme si un reglamento está cargado, pedirme una lista, o hacer una consulta de contenido. Ejemplos: “¿tenés cargado el RFP 51-01?”, “listame los RFP cargados”, “¿qué establece sobre seguridad en instrucción?”.</div>
        </div>
      </div>

      <form id="askForm" class="composer">
        <input type="hidden" name="ajax" value="1">
        <input type="hidden" name="action" value="ask">
        <input type="hidden" name="_csrf" value="<?= ia_e($csrf) ?>">
        <div class="composer-row">
          <input id="questionInput" class="form-control" name="question" autocomplete="off" placeholder="Escribí tu pregunta sobre los reglamentos..." required>
          <button class="btn-green" type="submit">Preguntar</button>
        </div>
      </form>
    </section>
  </div>
</main>

<script>
const csrf = <?= json_encode($csrf, JSON_UNESCAPED_UNICODE) ?>;
const endpoint = <?= json_encode($BASE_PUBLIC_WEB . '/asistente_ia.php', JSON_UNESCAPED_SLASHES) ?>;
const messages = document.getElementById('messages');
const askForm = document.getElementById('askForm');
const uploadForm = document.getElementById('uploadForm');
const uploadMsg = document.getElementById('uploadMsg');

function esc(s){
  return String(s ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]));
}
function addMsg(role, text, sources = []){
  const div = document.createElement('div');
  div.className = 'msg ' + (role === 'user' ? 'user' : 'bot');
  const sourceHtml = sources.length ? '<div class="sources">' + sources.map(s => `<span class="source-pill">${esc(s.titulo)} · frag. ${esc(s.fragmento)}</span>`).join('') + '</div>' : '';
  div.innerHTML = `<div class="msg-label">${role === 'user' ? 'Vos' : 'Asistente'}</div><div class="bubble">${esc(text)}${sourceHtml}</div>`;
  messages.appendChild(div);
  messages.scrollTop = messages.scrollHeight;
}
function selectedDocs(){
  return Array.from(document.querySelectorAll('.doc-check:checked')).map(x => x.value);
}
function showUpload(kind, text){
  uploadMsg.className = 'toastline show ' + (kind === 'ok' ? 'ok' : 'err');
  uploadMsg.textContent = text;
}

askForm.addEventListener('submit', async (ev) => {
  ev.preventDefault();
  const input = document.getElementById('questionInput');
  const question = input.value.trim();
  if (!question) return;
  addMsg('user', question);
  input.value = '';
  addMsg('bot', 'Buscando en los reglamentos seleccionados...');
  const loading = messages.lastElementChild;

  const body = new URLSearchParams();
  body.set('ajax', '1');
  body.set('action', 'ask');
  body.set('_csrf', csrf);
  body.set('question', question);
  selectedDocs().forEach(id => body.append('doc_ids[]', id));

  try {
    const r = await fetch(endpoint, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'}, body});
    const data = await r.json();
    loading.remove();
    if (!data.ok) {
      addMsg('bot', data.error || 'No pude responder la pregunta.');
      return;
    }
    addMsg('bot', data.answer || '', data.sources || []);
  } catch (e) {
    loading.remove();
    addMsg('bot', 'Hubo un error consultando el asistente.');
  }
});

uploadForm.addEventListener('submit', async (ev) => {
  ev.preventDefault();
  showUpload('ok', 'Subiendo e indexando PDF...');
  const fd = new FormData(uploadForm);
  try {
    const r = await fetch(endpoint, {method:'POST', body:fd});
    const data = await r.json();
    if (!data.ok) {
      showUpload('err', data.error || 'No se pudo cargar.');
      return;
    }
    showUpload('ok', data.message || 'Reglamento cargado.');
    setTimeout(() => location.reload(), 800);
  } catch (e) {
    showUpload('err', 'No se pudo cargar el PDF.');
  }
});

</script>
</body>
</html>
