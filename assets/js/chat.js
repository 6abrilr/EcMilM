// assets/js/chat.js
// Chat widget logic used by various pages (Personal, Operaciones, etc.).
// Relies on window.EA_CHAT.config being defined before this script is loaded.

(function(){
  if (typeof window === 'undefined') return;
  const cfg = window.EA_CHAT && window.EA_CHAT.config;
  if (!cfg) return;

  const CHAT_URL = cfg.ajaxUrl;
  const CAN_WRITE_GENERAL = Boolean(cfg.canWriteGeneral);
  const CSRF_TOKEN = cfg.csrfToken || '';
  const MY_PERSONAL_ID = Number(cfg.personalId || 0);

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

  const STORAGE_KEY = `ea_chat_seen_${MY_PERSONAL_ID}`;

  try {
    state.seenMap = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}') || {};
  } catch (e) {
    state.seenMap = {};
  }

  function saveSeenMap() {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(state.seenMap));
    } catch (e) {
      // ignore
    }
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
      Object.entries(params).forEach(([k, v]) => {
        if (v === undefined || v === null) return;
        url.searchParams.set(k, String(v));
      });
      return url.toString();
    } catch (e) {
      const qs = new URLSearchParams(params).toString();
      return `${base}${base.includes('?') ? '&' : '?'}${qs}`;
    }
  }

  async function apiGet(action, params = {}) {
    const url = buildUrl(CHAT_URL, { ajax: '1', action, ...params });
    const res = await fetch(url, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    return res.json();
  }

  async function apiPost(action, params = {}) {
    const body = new URLSearchParams({ action, _csrf: CSRF_TOKEN, ...params });
    const url = buildUrl(CHAT_URL, { ajax: '1' });
    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: body.toString()
    });
    return res.json();
  }

  function updateUnreadBadges() {
    const total = Object.keys(state.unreadMap).length;
    launcherBadge.textContent = String(total);
    dockBadge.textContent = String(total);
    launcherBadge.classList.toggle('show', total > 0);
    dockBadge.classList.toggle('show', total > 0);
    if (total > 0) {
      document.title = `(${total}) ` + (document.title.replace(/^\(\d+\)\s*/, ''));
    } else {
      document.title = document.title.replace(/^\(\d+\)\s*/, '');
    }
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

    saveSeenMap();
    updateUnreadBadges();
  }

  function selectedConversation() {
    return state.conversations.find(c => Number(c.id) === Number(state.selectedConversationId)) || null;
  }

  function renderConversations() {
    if (!convList) return;

    if (!state.conversations.length) {
      convList.innerHTML = '<div class="chat-empty">No hay conversaciones.</div>';
      return;
    }

    convList.innerHTML = state.conversations.map(c => {
      const isActive = String(c.id) === String(state.selectedConversationId);
      const unread = state.unreadMap[c.id] ? 'chat-conv-unread' : '';
      return `
        <button type="button" class="chat-conv-item ${isActive ? 'active' : ''} ${unread}" data-id="${c.id}">
          <div class="chat-conv-title">${escapeHtml(c.title || '—')}</div>
          <div class="chat-conv-sub">${escapeHtml(c.subtitle || '')}</div>
        </button>
      `;
    }).join('');

    const buttons = convList.querySelectorAll('.chat-conv-item');
    buttons.forEach(btn => {
      btn.addEventListener('click', () => {
        const id = btn.getAttribute('data-id');
        if (!id) return;
        state.selectedConversationId = id;
        renderConversations();
        loadMessages();
      });
    });
  }

  function setHeader() {
    const c = selectedConversation();
    if (!c) return;
    threadTitle.textContent = c.title || 'Chat';
    threadSub.textContent = c.subtitle || '';

    const isReadOnly = (c.type === 'general' && !CAN_WRITE_GENERAL);
    readonlyBox.classList.toggle('d-none', !isReadOnly);
    compose.classList.toggle('d-none', isReadOnly);
  }

  function renderMessages(messages) {
    if (!messagesBox) return;

    if (!messages.length) {
      messagesBox.innerHTML = '<div class="chat-empty">No hay mensajes en esta conversación.</div>';
      return;
    }

    messagesBox.innerHTML = messages.map(m => {
      const fromMe = Number(m.personal_id) === MY_PERSONAL_ID;
      const when = m.created_at ? `<span class="chat-msg-meta">${escapeHtml(m.created_at)}</span>` : '';
      const text = escapeHtml(m.mensaje || '');
      return `
        <div class="chat-msg ${fromMe ? 'chat-msg-self' : 'chat-msg-other'}">
          <div class="chat-msg-content">${text}</div>
          <div class="chat-msg-meta-row">${when}</div>
        </div>
      `;
    }).join('');

    messagesBox.scrollTop = messagesBox.scrollHeight;
  }

  async function loadConversations() {
    const res = await apiGet('list_conversations');
    if (!res || !res.ok) return;
    state.conversations = res.items || [];
    processUnread();
    if (!state.selectedConversationId && state.conversations.length) {
      state.selectedConversationId = state.conversations[0].id;
    }
    renderConversations();
    setHeader();
    if (state.selectedConversationId) {
      loadMessages();
    }
  }

  async function loadMessages() {
    const c = selectedConversation();
    if (!c || !c.id) return;
    setHeader();
    const res = await apiGet('get_messages', { conversation_id: c.id });
    if (!res || !res.ok) return;
    renderMessages(res.messages || []);
  }

  async function sendMessage(text) {
    const c = selectedConversation();
    if (!c || !c.id) return;
    const res = await apiPost('send_message', { conversation_id: c.id, message: text });
    if (!res || !res.ok) return;
    input.value = '';
    await loadMessages();
    await loadConversations();
  }

  function poll() {
    loadConversations();
  }

  function init() {
    if (!dock || !launcher) return;

    launcher.classList.remove('chat-hidden');

    launcher.addEventListener('click', () => {
      dock.classList.toggle('chat-open');
    });

    closeBtn?.addEventListener('click', () => {
      dock.classList.remove('chat-open');
    });

    compose?.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const text = input?.value?.trim();
      if (!text) return;
      await sendMessage(text);
    });

    if (openFull) {
      openFull.setAttribute('href', cfg.fullUrl || '');
      openFull.setAttribute('target', '_blank');
    }

    loadConversations();
    state.pollingHandle = setInterval(poll, 15000);
  }

  document.addEventListener('DOMContentLoaded', init);
})();
