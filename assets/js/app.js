(() => {
  'use strict';

  const CONVERSATIONS_KEY = 'nigai_conversations_v1';
  const SETTINGS_KEY = 'nigai_settings_v1';
  const SELECTION_KEY = 'nigai_selection_v1';
  const LEGACY_CONVERSATION_KEY = 'term_chat_conversation_v1';
  const LEGACY_SELECTION_KEY = 'term_chat_selection_v1';

  const sidebar = document.getElementById('sidebar');
  const sidebarScrim = document.getElementById('sidebarScrim');
  const sidebarOpenBtn = document.getElementById('sidebarOpenBtn');
  const sidebarCloseBtn = document.getElementById('sidebarCloseBtn');
  const newChatBtn = document.getElementById('newChatBtn');
  const chatHistoryEl = document.getElementById('chatHistory');
  const settingsBtn = document.getElementById('settingsBtn');
  const aboutBtn = document.getElementById('aboutBtn');

  const chatLog = document.getElementById('chatLog');
  const chatLogInner = document.getElementById('chatLogInner');
  const emptyState = document.getElementById('emptyState');
  const chatForm = document.getElementById('chatForm');
  const chatInput = document.getElementById('chatInput');
  const sendBtn = document.getElementById('sendBtn');
  const errorBanner = document.getElementById('errorBanner');
  const providerSelect = document.getElementById('providerSelect');
  const modelSelect = document.getElementById('modelSelect');
  const themeToggleBtn = document.getElementById('themeToggleBtn');

  const dialogScrim = document.getElementById('dialogScrim');
  const settingsDialog = document.getElementById('settingsDialog');
  const aboutDialog = document.getElementById('aboutDialog');
  const themeSegmented = document.getElementById('themeSegmented');
  const animationsToggle = document.getElementById('animationsToggle');
  const compactToggle = document.getElementById('compactToggle');
  const clearAllDataBtn = document.getElementById('clearAllDataBtn');

  let conversations = [];
  let activeId = null;
  let providers = [];
  let isSending = false;
  let activeAbortController = null;
  let settings = { theme: 'dark', animations: true, compact: false };

  function uid() {
    return Date.now().toString(36) + Math.random().toString(36).slice(2, 8);
  }

  function safeParse(raw, fallback) {
    try {
      const parsed = JSON.parse(raw);
      return parsed === null || parsed === undefined ? fallback : parsed;
    } catch (e) {
      return fallback;
    }
  }

  function loadSettings() {
    const raw = localStorage.getItem(SETTINGS_KEY);
    const parsed = safeParse(raw, null);
    const theme = document.documentElement.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
    settings = Object.assign({ theme: theme, animations: true, compact: false }, parsed || {});
  }

  function saveSettings() {
    try {
      localStorage.setItem(SETTINGS_KEY, JSON.stringify(settings));
    } catch (e) {}
  }

  function applySettings() {
    document.documentElement.setAttribute('data-theme', settings.theme);
    document.documentElement.classList.toggle('no-anim', !settings.animations);
    document.documentElement.classList.toggle('compact', !!settings.compact);
    animationsToggle.checked = !!settings.animations;
    compactToggle.checked = !!settings.compact;
    themeSegmented.querySelectorAll('.segmented-btn').forEach((btn) => {
      btn.classList.toggle('active', btn.dataset.themeChoice === settings.theme);
    });
    try { localStorage.setItem('nigai-theme', settings.theme); } catch (e) {}
  }

  function loadConversations() {
    const raw = localStorage.getItem(CONVERSATIONS_KEY);
    const parsed = safeParse(raw, null);

    if (Array.isArray(parsed) && parsed.length > 0) {
      conversations = parsed;
      return;
    }

    const legacyRaw = localStorage.getItem(LEGACY_CONVERSATION_KEY);
    const legacyMessages = safeParse(legacyRaw, null);
    if (Array.isArray(legacyMessages) && legacyMessages.length > 0) {
      conversations = [{
        id: uid(),
        title: titleFromMessages(legacyMessages),
        messages: legacyMessages,
        updatedAt: Date.now(),
      }];
      saveConversations();
      return;
    }

    conversations = [];
  }

  function saveConversations() {
    try {
      localStorage.setItem(CONVERSATIONS_KEY, JSON.stringify(conversations));
    } catch (e) {}
  }

  function titleFromMessages(messages) {
    const firstUser = messages.find((m) => m.role === 'user');
    if (!firstUser) return 'New chat';
    const trimmed = firstUser.text.trim().replace(/\s+/g, ' ');
    return trimmed.length > 42 ? trimmed.slice(0, 42) + '...' : (trimmed || 'New chat');
  }

  function getActiveConversation() {
    return conversations.find((c) => c.id === activeId) || null;
  }

  function ensureActiveConversation() {
    if (getActiveConversation()) return;
    if (conversations.length > 0) {
      activeId = conversations[0].id;
      return;
    }
    createConversation();
  }

  function createConversation() {
    const convo = { id: uid(), title: 'New chat', messages: [], updatedAt: Date.now() };
    conversations.unshift(convo);
    activeId = convo.id;
    saveConversations();
    return convo;
  }

  function touchConversation(convo) {
    convo.updatedAt = Date.now();
    conversations.sort((a, b) => b.updatedAt - a.updatedAt);
    saveConversations();
  }

  function renderSidebar() {
    chatHistoryEl.innerHTML = '';

    if (conversations.length === 0) {
      const empty = document.createElement('div');
      empty.className = 'chat-history-empty';
      empty.textContent = 'No conversations yet';
      chatHistoryEl.appendChild(empty);
      return;
    }

    conversations.forEach((convo) => {
      const item = document.createElement('div');
      item.className = 'chat-history-item' + (convo.id === activeId ? ' active' : '');
      item.setAttribute('role', 'button');
      item.setAttribute('tabindex', '0');

      const title = document.createElement('span');
      title.className = 'chat-history-title';
      title.textContent = convo.title || 'New chat';
      item.appendChild(title);

      const del = document.createElement('button');
      del.type = 'button';
      del.className = 'chat-history-delete';
      del.setAttribute('aria-label', 'Delete conversation');
      del.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M4 7h16M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2M6 7l1 13a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-13" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>';
      del.addEventListener('click', (e) => {
        e.stopPropagation();
        deleteConversation(convo.id);
      });
      item.appendChild(del);

      item.addEventListener('click', () => selectConversation(convo.id));
      item.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          selectConversation(convo.id);
        }
      });

      chatHistoryEl.appendChild(item);
    });
  }

  function selectConversation(id) {
    if (id === activeId) { closeSidebarOnMobile(); return; }
    stopStreaming();
    activeId = id;
    renderSidebar();
    renderActiveConversation();
    closeSidebarOnMobile();
  }

  function deleteConversation(id) {
    nigaiConfirm('Delete this conversation? This cannot be undone.', () => {
      const wasActive = id === activeId;
      conversations = conversations.filter((c) => c.id !== id);
      saveConversations();
      if (wasActive) {
        activeId = null;
        ensureActiveConversation();
        renderActiveConversation();
      }
      renderSidebar();
    });
  }

  function startNewConversation() {
    stopStreaming();
    createConversation();
    renderSidebar();
    renderActiveConversation();
    hideError();
    closeSidebarOnMobile();
    chatInput.focus();
  }

  function escapeHtml(str) {
    return str
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }

  function renderMarkdown(text) {
    let html;
    try {
      html = marked.parse(text, { gfm: true, breaks: false });
    } catch (e) {
      html = '<p>' + escapeHtml(text) + '</p>';
    }
    const clean = DOMPurify.sanitize(html, { ADD_ATTR: ['target'] });
    const wrapper = document.createElement('div');
    wrapper.innerHTML = clean;

    wrapper.querySelectorAll('pre').forEach((pre) => {
      const codeEl = pre.querySelector('code');
      if (!codeEl) return;
      const langMatch = (codeEl.className || '').match(/language-(\S+)/);
      const lang = langMatch ? langMatch[1] : 'text';

      const block = document.createElement('div');
      block.className = 'code-block';

      const header = document.createElement('div');
      header.className = 'code-block-header';
      const langLabel = document.createElement('span');
      langLabel.textContent = lang;
      const copyBtn = document.createElement('button');
      copyBtn.type = 'button';
      copyBtn.className = 'copy-btn';
      copyBtn.textContent = 'copy';
      header.appendChild(langLabel);
      header.appendChild(copyBtn);

      pre.parentNode.insertBefore(block, pre);
      block.appendChild(header);
      block.appendChild(pre);
    });

    wrapper.querySelectorAll('a').forEach((a) => {
      a.setAttribute('target', '_blank');
      a.setAttribute('rel', 'noopener noreferrer');
    });

    return wrapper.innerHTML;
  }

  chatLog.addEventListener('click', (e) => {
    const btn = e.target.closest('.copy-btn');
    if (!btn) return;
    const block = btn.closest('.code-block');
    const codeEl = block ? block.querySelector('code') : null;
    if (!codeEl) return;
    copyText(codeEl.textContent, btn, 'copy');
  });

  function copyText(text, btn, restoreLabel) {
    const done = () => {
      const original = btn.textContent;
      btn.textContent = 'copied';
      setTimeout(() => { btn.textContent = restoreLabel || original; }, 1400);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(done).catch(() => fallbackCopy(text, done));
    } else {
      fallbackCopy(text, done);
    }
  }

  function fallbackCopy(text, cb) {
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); } catch (e) {}
    document.body.removeChild(ta);
    cb();
  }

  function scrollToBottom(smooth) {
    chatLog.scrollTo({ top: chatLog.scrollHeight, behavior: smooth ? 'smooth' : 'auto' });
  }

  function isNearBottom() {
    return chatLog.scrollHeight - chatLog.scrollTop - chatLog.clientHeight < 120;
  }

  function updateEmptyState() {
    const convo = getActiveConversation();
    emptyState.style.display = (convo && convo.messages.length > 0) ? 'none' : 'block';
  }

  function buildMessageActions(role, msgEl, convo, index) {
    const actions = document.createElement('div');
    actions.className = 'msg-actions';

    const copyBtn = document.createElement('button');
    copyBtn.type = 'button';
    copyBtn.className = 'msg-action-btn';
    copyBtn.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none"><rect x="8" y="8" width="12" height="12" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2" stroke="currentColor" stroke-width="1.6"/></svg><span>Copy</span>';
    copyBtn.addEventListener('click', () => {
      const body = msgEl.querySelector('.msg-body');
      copyText(body.innerText, copyBtn, 'Copy');
    });
    actions.appendChild(copyBtn);

    if (role === 'user') {
      const editBtn = document.createElement('button');
      editBtn.type = 'button';
      editBtn.className = 'msg-action-btn';
      editBtn.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M4 20l4-1 11-11-3-3L5 16l-1 4Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg><span>Edit</span>';
      editBtn.addEventListener('click', () => beginEditMessage(convo, index, msgEl));
      actions.appendChild(editBtn);
    }

    if (role === 'model') {
      const regenBtn = document.createElement('button');
      regenBtn.type = 'button';
      regenBtn.className = 'msg-action-btn';
      regenBtn.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M4 12a8 8 0 0 1 14-5.3M20 12a8 8 0 0 1-14 5.3M18 4v4h-4M6 20v-4h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg><span>Regenerate</span>';
      regenBtn.addEventListener('click', () => regenerateFrom(convo, index));
      actions.appendChild(regenBtn);
    }

    return actions;
  }

  function appendMessageToDOM(role, text, convo, index) {
    const wrapper = document.createElement('div');
    wrapper.className = 'msg ' + (role === 'user' ? 'msg-user' : 'msg-ai');

    const roleLabel = document.createElement('span');
    roleLabel.className = 'msg-role';
    roleLabel.textContent = role === 'user' ? 'you' : 'nigai';
    wrapper.appendChild(roleLabel);

    const body = document.createElement('div');
    body.className = 'msg-body';
    if (role === 'user') {
      body.textContent = text;
    } else {
      body.innerHTML = renderMarkdown(text);
    }
    wrapper.appendChild(body);

    if (convo && typeof index === 'number') {
      wrapper.appendChild(buildMessageActions(role, wrapper, convo, index));
    }

    chatLogInner.appendChild(wrapper);
    return wrapper;
  }

  function beginEditMessage(convo, index, msgEl) {
    const original = convo.messages[index].text;
    const body = msgEl.querySelector('.msg-body');
    const actions = msgEl.querySelector('.msg-actions');
    body.style.display = 'none';
    if (actions) actions.style.display = 'none';

    const textarea = document.createElement('textarea');
    textarea.className = 'msg-edit-area';
    textarea.value = original;
    textarea.rows = Math.min(8, Math.max(2, original.split('\n').length));

    const editActions = document.createElement('div');
    editActions.className = 'msg-edit-actions';

    const cancelBtn = document.createElement('button');
    cancelBtn.type = 'button';
    cancelBtn.className = 'pill-btn';
    cancelBtn.textContent = 'Cancel';
    cancelBtn.addEventListener('click', () => {
      textarea.remove();
      editActions.remove();
      body.style.display = '';
      if (actions) actions.style.display = '';
    });

    const saveBtn = document.createElement('button');
    saveBtn.type = 'button';
    saveBtn.className = 'pill-btn primary';
    saveBtn.textContent = 'Save & submit';
    saveBtn.addEventListener('click', () => {
      const newText = textarea.value.trim();
      if (!newText) return;
      convo.messages = convo.messages.slice(0, index);
      renderActiveConversation();
      sendMessage(newText);
    });

    editActions.appendChild(cancelBtn);
    editActions.appendChild(saveBtn);
    msgEl.appendChild(textarea);
    msgEl.appendChild(editActions);
    textarea.focus();
  }

  function regenerateFrom(convo, index) {
    if (isSending) return;
    let userIndex = index - 1;
    while (userIndex >= 0 && convo.messages[userIndex].role !== 'user') userIndex--;
    if (userIndex < 0) return;
    const userText = convo.messages[userIndex].text;
    convo.messages = convo.messages.slice(0, userIndex);
    renderActiveConversation();
    sendMessage(userText);
  }

  function renderActiveConversation() {
    const convo = getActiveConversation();
    chatLogInner.innerHTML = '';
    chatLogInner.appendChild(emptyState);
    if (convo) {
      convo.messages.forEach((m, i) => appendMessageToDOM(m.role, m.text, convo, i));
    }
    updateEmptyState();
    scrollToBottom(false);
  }

  function addTypingIndicator() {
    const el = document.createElement('div');
    el.className = 'typing';
    el.id = 'typingIndicator';
    el.innerHTML = '<span></span><span></span><span></span>';
    chatLogInner.appendChild(el);
    scrollToBottom(true);
  }

  function removeTypingIndicator() {
    const el = document.getElementById('typingIndicator');
    if (el) el.remove();
  }

  function showError(message) {
    errorBanner.innerHTML = '';
    const span = document.createElement('span');
    span.textContent = message;
    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.textContent = 'dismiss';
    closeBtn.addEventListener('click', hideError);
    errorBanner.appendChild(span);
    errorBanner.appendChild(closeBtn);
    errorBanner.classList.remove('hidden');
  }

  function hideError() {
    errorBanner.classList.add('hidden');
    errorBanner.innerHTML = '';
  }

  function autoResizeTextarea() {
    chatInput.style.height = 'auto';
    chatInput.style.height = Math.min(chatInput.scrollHeight, 160) + 'px';
  }

  chatInput.addEventListener('input', autoResizeTextarea);

  chatInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      chatForm.requestSubmit();
    }
  });

  function saveSelection() {
    try {
      localStorage.setItem(SELECTION_KEY, JSON.stringify({
        provider: providerSelect.value,
        model: modelSelect.value,
      }));
    } catch (e) {}
  }

  function loadSelectionRaw() {
    const raw = localStorage.getItem(SELECTION_KEY) || localStorage.getItem(LEGACY_SELECTION_KEY);
    return safeParse(raw, null);
  }

  function populateModelSelect(providerId, preferredModel) {
    const provider = providers.find((p) => p.id === providerId);
    modelSelect.innerHTML = '';
    if (!provider) return;

    provider.models.forEach((m) => {
      const opt = document.createElement('option');
      opt.value = m.id;
      opt.textContent = m.label;
      modelSelect.appendChild(opt);
    });

    const hasPreferred = preferredModel && provider.models.some((m) => m.id === preferredModel);
    modelSelect.value = hasPreferred ? preferredModel : provider.default_model;
  }

  async function loadProviders() {
    try {
      const response = await fetch('api.php?action=meta');
      const data = await response.json();
      providers = data.providers || [];
    } catch (e) {
      providers = [];
    }

    providerSelect.innerHTML = '';

    if (providers.length === 0) {
      const opt = document.createElement('option');
      opt.textContent = 'no provider configured';
      providerSelect.appendChild(opt);
      providerSelect.disabled = true;
      modelSelect.disabled = true;
      sendBtn.disabled = true;
      showError('No AI providers are configured on the server. Add at least one API key in config.php.');
      return;
    }

    providers.forEach((p) => {
      const opt = document.createElement('option');
      opt.value = p.id;
      opt.textContent = p.label;
      providerSelect.appendChild(opt);
    });

    const saved = loadSelectionRaw();
    const savedProviderValid = saved && providers.some((p) => p.id === saved.provider);
    providerSelect.value = savedProviderValid ? saved.provider : providers[0].id;
    populateModelSelect(providerSelect.value, savedProviderValid ? saved.model : null);
    saveSelection();
  }

  providerSelect.addEventListener('change', () => {
    populateModelSelect(providerSelect.value, null);
    saveSelection();
  });

  modelSelect.addEventListener('change', saveSelection);

  function setSendingState(sending) {
    isSending = sending;
    sendBtn.classList.toggle('is-sending', sending);
  }

  function stopStreaming() {
    if (activeAbortController) {
      activeAbortController.abort();
      activeAbortController = null;
    }
  }

  async function sendMessage(userText) {
    if (isSending) return;

    const convo = getActiveConversation();
    if (!convo) return;

    hideError();

    const userIndex = convo.messages.length;
    convo.messages.push({ role: 'user', text: userText });
    if (convo.messages.filter((m) => m.role === 'user').length === 1) {
      convo.title = titleFromMessages(convo.messages);
    }
    appendMessageToDOM('user', userText, convo, userIndex);
    updateEmptyState();
    touchConversation(convo);
    renderSidebar();
    scrollToBottom(true);

    setSendingState(true);
    addTypingIndicator();

    const historyForRequest = convo.messages.slice(0, -1);
    const controller = new AbortController();
    activeAbortController = controller;

    let assistantEl = null;
    let assistantBody = null;
    let accumulated = '';

    try {
      const response = await fetch('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          provider: providerSelect.value,
          model: modelSelect.value,
          message: userText,
          history: historyForRequest,
        }),
        signal: controller.signal,
      });

      if (!response.ok || !response.body) {
        throw new Error('Request failed (HTTP ' + response.status + ').');
      }

      const reader = response.body.getReader();
      const decoder = new TextDecoder();
      let buffer = '';

      while (true) {
        const { done, value } = await reader.read();
        if (done) break;
        buffer += decoder.decode(value, { stream: true });

        let sepIndex;
        while ((sepIndex = buffer.indexOf('\n\n')) !== -1) {
          const frame = buffer.slice(0, sepIndex);
          buffer = buffer.slice(sepIndex + 2);
          if (!frame.startsWith('data:')) continue;

          const jsonStr = frame.slice(5).trim();
          if (!jsonStr) continue;

          let payload;
          try {
            payload = JSON.parse(jsonStr);
          } catch (e) {
            continue;
          }

          if (payload.error) {
            throw new Error(payload.error);
          }

          if (payload.text) {
            if (!assistantEl) {
              removeTypingIndicator();
              assistantEl = appendMessageToDOM('model', '', null, null);
              assistantBody = assistantEl.querySelector('.msg-body');
            }
            accumulated += payload.text;
            assistantBody.innerHTML = renderMarkdown(accumulated);
            if (isNearBottom()) scrollToBottom(false);
          }

          if (payload.done) {
            if (payload.provider) {
              const matched = providers.find((p) => p.label === payload.provider);
              if (matched && matched.id !== providerSelect.value) {
                providerSelect.value = matched.id;
                populateModelSelect(matched.id, payload.model);
                saveSelection();
              }
            }
          }
        }
      }
    } catch (err) {
      removeTypingIndicator();
      if (err.name !== 'AbortError') {
        showError(err.message || 'Something went wrong. Please try again.');
      }
    } finally {
      removeTypingIndicator();
      activeAbortController = null;
      setSendingState(false);

      if (accumulated) {
        convo.messages.push({ role: 'model', text: accumulated });
      }
      touchConversation(convo);
      renderSidebar();
      if (accumulated && assistantEl) {
        const newIndex = convo.messages.length - 1;
        assistantEl.appendChild(buildMessageActions('model', assistantEl, convo, newIndex));
      }
      if (!accumulated && !assistantEl) {
        saveConversations();
      }
    }
  }

  sendBtn.addEventListener('click', (e) => {
    if (isSending) {
      e.preventDefault();
      stopStreaming();
    }
  });

  chatForm.addEventListener('submit', (e) => {
    e.preventDefault();
    if (isSending) return;

    const text = chatInput.value.trim();
    if (!text) return;

    chatInput.value = '';
    autoResizeTextarea();
    sendMessage(text);
  });

  newChatBtn.addEventListener('click', startNewConversation);

  function openSidebar() {
    sidebar.classList.add('open');
    sidebarScrim.classList.add('open');
  }

  function closeSidebar() {
    sidebar.classList.remove('open');
    sidebarScrim.classList.remove('open');
  }

  function closeSidebarOnMobile() {
    if (window.matchMedia('(max-width: 860px)').matches) closeSidebar();
  }

  sidebarOpenBtn.addEventListener('click', openSidebar);
  sidebarCloseBtn.addEventListener('click', closeSidebar);
  sidebarScrim.addEventListener('click', closeSidebar);

  themeToggleBtn.addEventListener('click', () => {
    settings.theme = settings.theme === 'dark' ? 'light' : 'dark';
    applySettings();
    saveSettings();
  });

  function openDialog(dialog) {
    dialogScrim.classList.remove('hidden');
    dialog.classList.remove('hidden');
  }

  function closeDialogs() {
    dialogScrim.classList.add('hidden');
    settingsDialog.classList.add('hidden');
    aboutDialog.classList.add('hidden');
    const confirmDialog = document.getElementById('nigaiConfirmDialog');
    if (confirmDialog) confirmDialog.remove();
  }

  settingsBtn.addEventListener('click', () => { openDialog(settingsDialog); closeSidebarOnMobile(); });
  aboutBtn.addEventListener('click', () => { openDialog(aboutDialog); closeSidebarOnMobile(); });
  dialogScrim.addEventListener('click', closeDialogs);
  document.querySelectorAll('[data-close-dialog]').forEach((btn) => btn.addEventListener('click', closeDialogs));

  themeSegmented.querySelectorAll('.segmented-btn').forEach((btn) => {
    btn.addEventListener('click', () => {
      settings.theme = btn.dataset.themeChoice;
      applySettings();
      saveSettings();
    });
  });

  animationsToggle.addEventListener('change', () => {
    settings.animations = animationsToggle.checked;
    applySettings();
    saveSettings();
  });

  compactToggle.addEventListener('change', () => {
    settings.compact = compactToggle.checked;
    applySettings();
    saveSettings();
  });

  clearAllDataBtn.addEventListener('click', () => {
    nigaiConfirm('Clear every local conversation and reset settings? This cannot be undone.', () => {
      localStorage.removeItem(CONVERSATIONS_KEY);
      localStorage.removeItem(SETTINGS_KEY);
      localStorage.removeItem(SELECTION_KEY);
      localStorage.removeItem(LEGACY_CONVERSATION_KEY);
      localStorage.removeItem(LEGACY_SELECTION_KEY);
      conversations = [];
      activeId = null;
      ensureActiveConversation();
      loadSettings();
      applySettings();
      renderSidebar();
      renderActiveConversation();
      closeDialogs();
    });
  });

  function nigaiConfirm(message, onConfirm) {
    const existing = document.getElementById('nigaiConfirmDialog');
    if (existing) existing.remove();

    const dialog = document.createElement('div');
    dialog.id = 'nigaiConfirmDialog';
    dialog.className = 'dialog dialog-narrow';
    dialog.setAttribute('role', 'dialog');
    dialog.setAttribute('aria-modal', 'true');
    dialog.innerHTML = '<div class="dialog-head"><h2>Please confirm</h2></div>' +
      '<div class="dialog-body"><p>' + escapeHtml(message) + '</p>' +
      '<div class="msg-edit-actions"><button type="button" class="pill-btn" id="nigaiConfirmCancel">Cancel</button>' +
      '<button type="button" class="pill-btn primary" id="nigaiConfirmOk">Confirm</button></div></div>';

    document.body.appendChild(dialog);
    dialogScrim.classList.remove('hidden');

    document.getElementById('nigaiConfirmCancel').addEventListener('click', closeDialogs);
    document.getElementById('nigaiConfirmOk').addEventListener('click', () => {
      closeDialogs();
      onConfirm();
    });
  }

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      closeDialogs();
      closeSidebar();
    }
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
      e.preventDefault();
      chatInput.focus();
    }
  });

  loadSettings();
  applySettings();
  loadConversations();
  ensureActiveConversation();
  renderSidebar();
  renderActiveConversation();
  loadProviders();
  chatInput.focus();
})();
