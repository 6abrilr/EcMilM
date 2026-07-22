<?php
// public/s3_tiro.php — Panel principal de Tiro · S-3
declare(strict_types=1);

$OFFLINE_MODE = false;

require_once __DIR__ . '/../../auth/bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/operaciones_helper.php';
require_once __DIR__ . '/operaciones_tiro_tables_helper.php';

if (!$OFFLINE_MODE) {
    operaciones_require_login();
}

s3_tiro_ensure_tables($pdo);

$esAdmin = operaciones_es_admin($pdo);
$modoResumido = !$esAdmin;

$ASSET_WEB = operaciones_assets_url();
$IMG_BG    = operaciones_assets_url('img/fondo.png');
$ESCUDO    = operaciones_assets_url('img/ecmilm.png');
$UNIDAD_NOMBRE = 'Escuela Militar de Monta&ntilde;a';
$UNIDAD_LEMA   = '&ldquo;La monta&ntilde;a nos une&rdquo;';
$BASE_PUBLIC_WEB = operaciones_app_public_web();
$CURRENT_URL = (string)($_SERVER['REQUEST_URI'] ?? 'operaciones_tiro.php');
$CHAT_FULL_URL = $BASE_PUBLIC_WEB . '/chat.php?return=' . rawurlencode($CURRENT_URL);
$CHAT_AJAX_URL = $BASE_PUBLIC_WEB . '/chat.php?ajax=1';
$CHAT_CSRF = csrf_token();
$personalActual = operaciones_get_personal_actual($pdo);
$personalId = (int)($personalActual['id'] ?? 0);

// Compatibility helper used in templates
function e($v){ return operaciones_e($v); }

/* ========== KPIs ========= */
function kpi_resultado(PDO $pdo, string $table): float {
    $sql = "SELECT 
                COUNT(*) AS total,
                SUM(resultado = 'APROBO') AS aprob
            FROM {$table}";
    $row = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC) ?: ['total'=>0,'aprob'=>0];
    $total = (int)$row['total'];
    $aprob = (int)$row['aprob'];
    if ($total === 0) return 0.0;
    return round($aprob * 100.0 / $total, 1);
}

$pctAmi = kpi_resultado($pdo, 's3_tiro_ami');
$pctB9  = kpi_resultado($pdo, 's3_tiro_b9');

?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>S-3 · Tiro · Escuela Militar de Montaña</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="<?= e($ASSET_WEB) ?>/css/theme-602.css">
<link rel="stylesheet" href="<?= e($BASE_PUBLIC_WEB) ?>/chat.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body{
    background:url("<?= e($IMG_BG) ?>") center/cover fixed;
    background-color:#020617;
    color:#e5e7eb;
    font-family:system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,sans-serif;
    margin:0;
}
body,
.text-muted,
.card-s3 p,
.brand-sub{
    color:#dbe7f6 !important;
}
.card-s3 .small,
.page-wrap p{
    color:#cbd5e1 !important;
}
.card-title,
h3,
.brand-title{
    color:#f8fafc !important;
}
.page-wrap{ padding:24px 16px 32px; }
.container-main{ max-width:1200px; margin:0 auto; }

.section-kicker .sk-text{
    font-size:1.05rem;
    font-weight:900;
    letter-spacing:.18em;
    text-transform:uppercase;
    background:linear-gradient(90deg,#38bdf8,#22c55e);
    -webkit-background-clip:text;
    -webkit-text-fill-color:transparent;
    border-bottom:2px solid rgba(34,197,94,.45);
    display:inline-block;
    padding-bottom:3px;
}

.card-s3{
    background:rgba(15,23,42,.92);
    border:1px solid rgba(148,163,184,.45);
    border-radius:18px;
    padding:18px;
    transition:.2s;
    box-shadow:0 0 22px rgba(0,0,0,.55);
}
.card-s3:hover{
    transform:translateY(-4px);
    box-shadow:0 0 32px rgba(0,0,0,.8);
}
.card-title{ font-weight:700; }

.kpi-num{
    font-size:1.6rem;
    font-weight:800;
    color:#22c55e;
}
.brand-title:not(.brand-title-fixed),
.brand-sub:not(.brand-sub-fixed){ display:none; }
.chat-dock{
    bottom:14px;
    height:min(430px, calc(100vh - 86px));
    max-height:calc(100vh - 86px);
    border-radius:18px;
}
.chat-conv-list,
.chat-messages{
    min-height:0;
    overflow-y:auto;
    scrollbar-width:thin;
}
.chat-conv-pane,
.chat-thread{
    min-height:0;
}
</style>
</head>
<body>

<header class="brand-hero">
  <div class="container-main d-flex justify-content-between align-items-center py-2">
    <div class="d-flex gap-3 align-items-center">
      <img src="<?= e($ESCUDO) ?>" alt="Escudo ECMILM" style="height:56px;"
           onerror="this.onerror=null;this.src='<?= e($ASSET_WEB) ?>/img/EA.png';">
      <div>
        <div class="brand-title brand-title-fixed fw-bold"><?= $UNIDAD_NOMBRE ?></div>
        <div class="brand-sub brand-sub-fixed text-muted"><?= $UNIDAD_LEMA ?></div>
        <div class="brand-title fw-bold"><?= $UNIDAD_NOMBRE ?></div>
        <div class="brand-sub text-muted"><?= $UNIDAD_LEMA ?></div>
      </div>
    </div>
    <a href="operaciones.php" class="btn btn-secondary btn-sm fw-bold">Volver a S-3</a>
  </div>
</header>

<div class="page-wrap">
<div class="container-main">

  <div class="section-kicker mb-2">
    <span class="sk-text">OPERACIONES</span>
  </div>
  <h3 class="fw-bold mb-3">Tiro</h3>
  <p class="text-muted">Seleccione un módulo para cargar o consultar información.</p>

  <?php if ($modoResumido): ?>
    <div class="alert alert-warning" role="alert">
      <strong>Vista resumida:</strong> la información se muestra de forma limitada para usuarios no administradores.
    </div>
  <?php endif; ?>

  <div class="row g-4">

    <!-- AMI -->
    <div class="col-md-6 col-lg-3">
      <a href="operaciones_ami.php" class="text-decoration-none text-light">
        <div class="card-s3 h-100">
          <h6 class="card-title">AMI asignada</h6>
          <p class="small text-muted mb-2">Evaluación individual AMI</p>
          <div class="kpi-num"><?= e($pctAmi) ?>%</div>
        </div>
      </a>
    </div>

    <!-- Condiciones -->
    <div class="col-md-6 col-lg-3">
      <a href="operaciones_condiciones.php" class="text-decoration-none text-light">
        <div class="card-s3 h-100">
          <h6 class="card-title">Condiciones</h6>
          <p class="small text-muted mb-2">Personal por armamento y condiciones de tiro</p>
          <div class="kpi-num"><?= e($pctB9) ?>%</div>
        </div>
      </a>
    </div>

    <!-- Condiciones -->
    <div class="col-md-6 col-lg-3">
      <a href="operaciones_tiro_condiciones.php" class="text-decoration-none text-light">
        <div class="card-s3 h-100">
          <h6 class="card-title">Resumen condiciones</h6>
          <p class="small text-muted mb-2">Todas las condiciones de tiro y quién las rindió</p>
          <div class="kpi-num">📋</div>
        </div>
      </a>
    </div>

    <!-- Consumo munición -->
    <div class="col-md-6 col-lg-3">
      <a href="operaciones_tiro_municion.php" class="text-decoration-none text-light">
        <div class="card-s3 h-100">
          <h6 class="card-title">Consumo de munición</h6>
          <p class="small text-muted mb-2">Registro por calibre y uso</p>
          <div class="kpi-num">📦</div>
        </div>
      </a>
    </div>

    <!-- Ejercicios -->
    <div class="col-md-6 col-lg-3">
      <a href="operaciones_tiro_ejercicios.php" class="text-decoration-none text-light">
        <div class="card-s3 h-100">
          <h6 class="card-title">Ejercicios de tiro</h6>
          <p class="small text-muted mb-2">Resultados por ejercicio</p>
          <div class="kpi-num">🎯</div>
        </div>
      </a>
    </div>

  </div>

</div>
</div>

<!-- =============================================
     CHAT DOCK
     ============================================= -->
<div id="chatLauncher" class="chat-launcher show">
  <div class="chat-launcher-title">Chat interno</div>
  <span id="chatLauncherBadge" class="chat-total-badge">0</span>
</div>

<div id="chatDock" class="chat-dock chat-hidden">
  <div class="chat-dock-head">
    <div class="chat-dock-title-wrap">
      <div class="chat-dock-title">Chat interno</div>
      <span id="chatDockBadge" class="chat-total-badge">0</span>
    </div>
    <div class="chat-dock-actions">
      <a id="chatOpenFull" href="<?= e($CHAT_FULL_URL) ?>" class="chat-btn chat-btn-open">Agrandar</a>
      <button type="button" id="chatCloseBtn" class="chat-btn chat-btn-close">Cerrar</button>
    </div>
  </div>

  <div class="chat-dock-body">
    <div class="chat-conv-pane">
      <div class="chat-conv-pane-head">Conversaciones</div>
      <div id="chatConvList" class="chat-conv-list">
        <div class="chat-empty">Cargando...</div>
      </div>
    </div>

    <div class="chat-thread">
      <div class="chat-thread-head">
        <div id="chatThreadTitle" class="chat-thread-title">Chat General</div>
        <div id="chatThreadSub" class="chat-thread-sub">Mensajes generales de la unidad</div>
      </div>
      <div id="chatMessages" class="chat-messages">
        <div class="chat-empty">Cargando mensajes...</div>
      </div>
      <div id="chatReadonly" class="chat-readonly">
        Solo ADMIN y SUPERADMIN pueden escribir en el chat general.
      </div>
      <form id="chatCompose" class="chat-compose">
        <div class="chat-compose-row">
          <input type="text" id="chatInput" class="form-control" maxlength="4000" placeholder="Escribi un mensaje...">
          <button type="submit" class="btn btn-success btn-sm fw-bold">Enviar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function () {
    'use strict';

    const CHAT_AJAX_URL      = <?= json_encode($CHAT_AJAX_URL, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const CHAT_FULL_URL      = <?= json_encode($CHAT_FULL_URL, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const CSRF_TOKEN         = <?= json_encode($CHAT_CSRF, JSON_UNESCAPED_UNICODE) ?>;
    const CAN_WRITE_GENERAL  = <?= $esAdmin ? 'true' : 'false' ?>;
    const STORAGE_KEY        = 'ea_chat_seen_<?= (int)$personalId ?>';
    const POLL_INTERVAL_MS   = 5000;
    const MAX_VISIBLE_MSGS   = 25;

    const dock          = document.getElementById('chatDock');
    const launcher      = document.getElementById('chatLauncher');
    const launcherBadge = document.getElementById('chatLauncherBadge');
    const dockBadge     = document.getElementById('chatDockBadge');
    const closeBtn      = document.getElementById('chatCloseBtn');
    const convList      = document.getElementById('chatConvList');
    const messagesBox   = document.getElementById('chatMessages');
    const threadTitle   = document.getElementById('chatThreadTitle');
    const threadSub     = document.getElementById('chatThreadSub');
    const compose       = document.getElementById('chatCompose');
    const input         = document.getElementById('chatInput');
    const readonlyBox   = document.getElementById('chatReadonly');
    const openFull      = document.getElementById('chatOpenFull');

    const state = {
        conversations: [],
        selectedConversationId: null,
        unreadMap: {},
        seenMap: {},
        baselineLoaded: false,
        pollingHandle: null,
    };

    try {
        state.seenMap = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}') || {};
    } catch (_) {
        state.seenMap = {};
    }

    function saveSeenMap() {
        try { localStorage.setItem(STORAGE_KEY, JSON.stringify(state.seenMap)); } catch (_) {}
    }

    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g,  '&amp;')
            .replace(/</g,  '&lt;')
            .replace(/>/g,  '&gt;')
            .replace(/"/g,  '&quot;')
            .replace(/'/g,  '&#039;');
    }

    function apiGet(action, params = {}) {
        const qs = new URLSearchParams({ action, ...params });
        return fetch(`${CHAT_AJAX_URL}&${qs}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        }).then(parseResponse);
    }

    function apiPost(action, params = {}) {
        const body = new URLSearchParams({ action, _csrf: CSRF_TOKEN, ...params });
        return fetch(CHAT_AJAX_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: body.toString(),
        }).then(parseResponse);
    }

    async function parseResponse(r) {
        const text = await r.text();
        try { return JSON.parse(text); }
        catch (_) { return { ok: false, error: 'Respuesta invalida del chat', raw: text }; }
    }

    function selectedConversation() {
        return state.conversations.find(c => Number(c.id) === Number(state.selectedConversationId)) || null;
    }

    function updateUnreadBadges() {
        const total = Object.keys(state.unreadMap).length;
        launcherBadge.textContent = String(total);
        dockBadge.textContent = String(total);
        launcherBadge.classList.toggle('show', total > 0);
        dockBadge.classList.toggle('show', total > 0);
        document.title = total > 0 ? `(${total}) Tiro` : 'S-3 - Tiro - Escuela Militar de Montaña';
    }

    function processUnread() {
        if (!state.baselineLoaded) {
            state.conversations.forEach(c => {
                state.seenMap[c.id] = Number(c.last_message_id || 0);
            });
            state.baselineLoaded = true;
            saveSeenMap();
            updateUnreadBadges();
            return;
        }

        state.conversations.forEach(c => {
            const currentId  = Number(c.last_message_id || 0);
            const seenId     = Number(state.seenMap[c.id]  || 0);
            const isSelected = Number(c.id) === Number(state.selectedConversationId);

            if (currentId > seenId) {
                if (isSelected || c.last_from_me || currentId <= 0) {
                    state.seenMap[c.id] = currentId;
                    delete state.unreadMap[c.id];
                } else {
                    state.unreadMap[c.id] = true;
                }
            }
        });

        saveSeenMap();
        updateUnreadBadges();
    }

    function markConversationSeen(conversationId) {
        const c = state.conversations.find(x => Number(x.id) === Number(conversationId));
        if (!c) return;
        state.seenMap[conversationId] = Number(c.last_message_id || 0);
        delete state.unreadMap[conversationId];
        saveSeenMap();
        updateUnreadBadges();
    }

    function setThreadHeader() {
        const c = selectedConversation();
        if (!c) {
            threadTitle.textContent = 'Sin conversacion';
            threadSub.textContent = '';
            compose.classList.remove('chat-hidden');
            readonlyBox.classList.remove('show');
            openFull.href = CHAT_FULL_URL;
            return;
        }
        threadTitle.textContent = c.title || 'Conversacion';
        threadSub.textContent = c.type === 'general'
            ? 'Mensajes generales de la unidad'
            : (c.is_self ? 'Tus notas personales' : 'Conversacion privada');

        const readOnly = c.type === 'general' && !CAN_WRITE_GENERAL;
        compose.classList.toggle('chat-hidden', readOnly);
        readonlyBox.classList.toggle('show', readOnly);
        openFull.href = CHAT_FULL_URL;
    }

    function renderConversations() {
        if (!state.conversations.length) {
            convList.innerHTML = '<div class="chat-empty">No hay conversaciones.</div>';
            return;
        }

        convList.innerHTML = state.conversations.map(c => {
            const typeText = c.type === 'general' ? 'GENERAL' : (c.is_self ? 'NOTAS' : 'PRIVADO');
            const isNew = !!state.unreadMap[c.id];
            const isActive = Number(c.id) === Number(state.selectedConversationId);

            return `
              <button type="button" class="chat-conv-item ${isActive ? 'active' : ''}" data-id="${Number(c.id)}">
                <div class="chat-conv-top">
                  <div class="chat-conv-name">${escapeHtml(c.title || 'Conversacion')}</div>
                  <div style="display:flex; align-items:center;">
                    <span class="chat-conv-badge ${isNew ? 'show' : ''}">NUEVO</span>
                    <span class="chat-conv-type">${escapeHtml(typeText)}</span>
                  </div>
                </div>
                <div class="chat-conv-last">${escapeHtml(c.last_message || 'Sin mensajes todavia')}</div>
              </button>`;
        }).join('');

        convList.querySelectorAll('.chat-conv-item').forEach(btn => {
            btn.addEventListener('click', async () => {
                state.selectedConversationId = Number(btn.dataset.id);
                markConversationSeen(state.selectedConversationId);
                renderConversations();
                setThreadHeader();
                await loadMessages(true);
            });
        });
    }

    async function loadConversations(preferId = null) {
        const r = await apiGet('list_conversations');
        if (!r.ok) {
            convList.innerHTML = `<div class="chat-empty">${escapeHtml(r.error || 'No se pudieron cargar las conversaciones.')}</div>`;
            return;
        }

        state.conversations = Array.isArray(r.items) ? r.items : [];

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
        setThreadHeader();
    }

    async function loadMessages(scrollBottom = false) {
        if (!state.selectedConversationId) return;

        const r = await apiGet('get_messages', { conversation_id: state.selectedConversationId });
        if (!r.ok) {
            messagesBox.innerHTML = `<div class="chat-empty">${escapeHtml(r.error || 'No se pudieron cargar los mensajes.')}</div>`;
            return;
        }

        const items = Array.isArray(r.items) ? r.items : [];
        const viewItems = items.slice(-MAX_VISIBLE_MSGS);

        if (!viewItems.length) {
            messagesBox.innerHTML = '<div class="chat-empty">No hay mensajes en esta conversacion.</div>';
            markConversationSeen(state.selectedConversationId);
            return;
        }

        messagesBox.innerHTML = viewItems.map(m => `
          <div class="msg-row ${m.mine ? 'me' : 'other'}">
            <div class="msg-meta">${escapeHtml(m.mine ? 'Yo' : m.author)} - ${escapeHtml(m.created_hm || '')}</div>
            <div class="msg-bubble ${m.mine ? 'me' : 'other'}">${escapeHtml(m.message || '')}</div>
          </div>`).join('');

        markConversationSeen(state.selectedConversationId);
        renderConversations();
        if (scrollBottom) messagesBox.scrollTop = messagesBox.scrollHeight;
    }

    async function sendMessage(ev) {
        ev.preventDefault();
        const text = input.value.trim();
        const c = selectedConversation();
        if (!c || !text) return;

        const r = await apiPost('send_message', { conversation_id: c.id, message: text });
        if (!r.ok) {
            alert(r.error || 'No se pudo enviar el mensaje.');
            return;
        }
        input.value = '';
        await loadMessages(true);
        await loadConversations(c.id);
    }

    function closeDock() {
        dock.classList.add('chat-hidden');
        launcher.classList.replace('chat-hidden', 'show') || launcher.classList.add('show');
    }
    function openDock() {
        launcher.classList.remove('show');
        launcher.classList.add('chat-hidden');
        dock.classList.remove('chat-hidden');
    }

    closeBtn.addEventListener('click', closeDock);
    launcher.addEventListener('click', openDock);
    compose.addEventListener('submit', sendMessage);
    document.addEventListener('keydown', ev => {
        if (ev.key === 'Escape' && !dock.classList.contains('chat-hidden')) closeDock();
    });

    loadConversations().then(() => loadMessages(true));

    state.pollingHandle = setInterval(async () => {
        const current = state.selectedConversationId;
        await loadConversations(current);
        await loadMessages(false);
    }, POLL_INTERVAL_MS);
})();
</script>
</body>
</html>
