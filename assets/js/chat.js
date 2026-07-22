// assets/js/chat.js
// Chat widget logic shared by pages that call operaciones_render_chat_widget().

(function () {
  'use strict';

  if (typeof window === 'undefined') return;
  const cfg = window.EA_CHAT && window.EA_CHAT.config;
  if (!cfg) return;

  const CHAT_URL = cfg.ajaxUrl || '';
  const FULL_URL = cfg.fullUrl || '';
  const CAN_WRITE_GENERAL = Boolean(cfg.canWriteGeneral);
  const CSRF_TOKEN = cfg.csrfToken || '';
  const MY_PERSONAL_ID = Number(cfg.personalId || 0);
  const STORAGE_KEY = `ea_chat_seen_${MY_PERSONAL_ID}`;
  const POLL_INTERVAL_MS = 5000;
  const MAX_VISIBLE_MSGS = 25;

  const dock = document.getElementById('chatDock');
  const launcher = document.getElementById('chatLauncher');
  const launcherBadge = document.getElementById('chatLauncherBadge');
  const dockBadge = document.getElementById('chatDockBadge');
  const closeBtn = document.getElementById('chatCloseBtn');
  const convList = document.getElementById('chatConvList');
  const messagesBox = document.getElementById('chatMessages');
  const threadTitle = document.getElementById('chatThreadTitle');
  const threadSub = document.getElementById('chatThreadSub');
  const compose = document.getElementById('chatCompose');
  const input = document.getElementById('chatInput');
  const readonlyBox = document.getElementById('chatReadonly');
  const openFull = document.getElementById('chatOpenFull');

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
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function buildUrl(base, params = {}) {
    try {
      const url = new URL(base, window.location.href);
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null) url.searchParams.set(key, String(value));
      });
      return url.toString();
    } catch (_) {
      const qs = new URLSearchParams(params).toString();
      return `${base}${base.includes('?') ? '&' : '?'}${qs}`;
    }
  }

  async function parseResponse(res) {
    const text = await res.text();
    try { return JSON.parse(text); } catch (_) { return { ok: false, raw: text }; }
  }

  async function apiGet(action, params = {}) {
    const res = await fetch(buildUrl(CHAT_URL, { ajax: '1', action, ...params }), {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    });
    return parseResponse(res);
  }

  async function apiPost(action, params = {}) {
    const body = new URLSearchParams({ action, _csrf: CSRF_TOKEN, ...params });
    const res = await fetch(buildUrl(CHAT_URL, { ajax: '1' }), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: body.toString(),
    });
    return parseResponse(res);
  }

  function selectedConversation() {
    return state.conversations.find(c => Number(c.id) === Number(state.selectedConversationId)) || null;
  }

  function updateUnreadBadges() {
    const total = Object.keys(state.unreadMap).length;
    if (launcherBadge) {
      launcherBadge.textContent = String(total);
      launcherBadge.classList.toggle('show', total > 0);
    }
    if (dockBadge) {
      dockBadge.textContent = String(total);
      dockBadge.classList.toggle('show', total > 0);
    }
    document.title = total > 0
      ? `(${total}) ${document.title.replace(/^\(\d+\)\s*/, '')}`
      : document.title.replace(/^\(\d+\)\s*/, '');
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
      const currentId = Number(c.last_message_id || 0);
      const seenId = Number(state.seenMap[c.id] || 0);
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
      if (threadTitle) threadTitle.textContent = 'Sin conversacion';
      if (threadSub) threadSub.textContent = '';
      if (compose) compose.classList.remove('chat-hidden');
      if (readonlyBox) readonlyBox.classList.remove('show');
      return;
    }

    if (threadTitle) threadTitle.textContent = c.title || 'Conversacion';
    if (threadSub) {
      threadSub.textContent = c.type === 'general'
        ? 'Mensajes generales de la unidad'
        : (c.is_self ? 'Tus notas personales' : 'Conversacion privada');
    }

    const readOnly = c.type === 'general' && !CAN_WRITE_GENERAL;
    if (compose) compose.classList.toggle('chat-hidden', readOnly);
    if (readonlyBox) readonlyBox.classList.toggle('show', readOnly);
  }

  function renderConversations() {
    if (!convList) return;
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
    const res = await apiGet('list_conversations');
    if (!res || !res.ok) {
      if (convList) convList.innerHTML = '<div class="chat-empty">No se pudieron cargar las conversaciones.</div>';
      return;
    }

    state.conversations = Array.isArray(res.items) ? res.items : [];
    if (preferId) {
      state.selectedConversationId = Number(preferId);
    } else if (!state.selectedConversationId && state.conversations.length) {
      state.selectedConversationId = Number(state.conversations[0].id);
    } else if (!state.conversations.some(c => Number(c.id) === Number(state.selectedConversationId)) && state.conversations.length) {
      state.selectedConversationId = Number(state.conversations[0].id);
    }

    processUnread();
    renderConversations();
    setThreadHeader();
  }

  async function loadMessages(scrollBottom = false) {
    if (!state.selectedConversationId || !messagesBox) return;

    const res = await apiGet('get_messages', { conversation_id: state.selectedConversationId });
    if (!res || !res.ok) {
      messagesBox.innerHTML = '<div class="chat-empty">No se pudieron cargar los mensajes.</div>';
      return;
    }

    const items = Array.isArray(res.items) ? res.items : (Array.isArray(res.messages) ? res.messages : []);
    const viewItems = items.slice(-MAX_VISIBLE_MSGS);
    if (!viewItems.length) {
      messagesBox.innerHTML = '<div class="chat-empty">No hay mensajes en esta conversacion.</div>';
      markConversationSeen(state.selectedConversationId);
      return;
    }

    messagesBox.innerHTML = viewItems.map(m => {
      const mine = Boolean(m.mine) || Number(m.personal_id) === MY_PERSONAL_ID;
      const author = mine ? 'Yo' : (m.author || '');
      const time = m.created_hm || m.created_at || '';
      const text = m.message || m.mensaje || '';
      return `
        <div class="msg-row ${mine ? 'me' : 'other'}">
          <div class="msg-meta">${escapeHtml(author)}${time ? ' - ' + escapeHtml(time) : ''}</div>
          <div class="msg-bubble ${mine ? 'me' : 'other'}">${escapeHtml(text)}</div>
        </div>`;
    }).join('');

    markConversationSeen(state.selectedConversationId);
    renderConversations();
    if (scrollBottom) messagesBox.scrollTop = messagesBox.scrollHeight;
  }

  async function sendMessage(ev) {
    ev.preventDefault();
    const text = input ? input.value.trim() : '';
    const c = selectedConversation();
    if (!c || !text) return;

    const res = await apiPost('send_message', { conversation_id: c.id, message: text });
    if (!res || !res.ok) {
      alert((res && res.error) || 'No se pudo enviar el mensaje.');
      return;
    }
    input.value = '';
    await loadMessages(true);
    await loadConversations(c.id);
  }

  function closeDock() {
    if (!dock || !launcher) return;
    dock.classList.add('chat-hidden');
    launcher.classList.remove('chat-hidden');
    launcher.classList.add('show');
  }

  function openDock() {
    if (!dock || !launcher) return;
    launcher.classList.remove('show');
    launcher.classList.add('chat-hidden');
    dock.classList.remove('chat-hidden');
  }

  function init() {
    if (!dock || !launcher) return;

    dock.classList.add('chat-hidden');
    launcher.classList.remove('chat-hidden');
    launcher.classList.add('show');

    launcher.addEventListener('click', openDock);
    if (closeBtn) closeBtn.addEventListener('click', closeDock);
    if (compose) compose.addEventListener('submit', sendMessage);
    if (openFull) openFull.setAttribute('href', FULL_URL);
    document.addEventListener('keydown', ev => {
      if (ev.key === 'Escape' && !dock.classList.contains('chat-hidden')) closeDock();
    });

    loadConversations().then(() => loadMessages(true));
    state.pollingHandle = setInterval(async () => {
      const current = state.selectedConversationId;
      await loadConversations(current);
      await loadMessages(false);
    }, POLL_INTERVAL_MS);
  }

  document.addEventListener('DOMContentLoaded', init);
})();
