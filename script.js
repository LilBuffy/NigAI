/**
 * script.js
 * ---------------------------------------------------------
 * All client-side chat logic. This file NEVER talks to any AI
 * provider directly — it only calls our own api.php, which
 * holds the real API key(s) server-side.
 * ---------------------------------------------------------
 */

(() => {
  'use strict';

  const STORAGE_KEY    = 'term_chat_conversation_v1';
  const SELECTION_KEY  = 'term_chat_selection_v1';

  const chatLog       = document.getElementById('chatLog');
  const emptyState    = document.getElementById('emptyState');
  const chatForm      = document.getElementById('chatForm');
  const chatInput     = document.getElementById('chatInput');
  const sendBtn       = document.getElementById('sendBtn');
  const newChatBtn    = document.getElementById('newChatBtn');
  const clearChatBtn  = document.getElementById('clearChatBtn');
  const errorBanner   = document.getElementById('errorBanner');
  const providerSelect = document.getElementById('providerSelect');
  const modelSelect    = document.getElementById('modelSelect');

  /** In-memory conversation: [{role: 'user'|'model', text: '...'}] */
  let conversation = [];
  let isSending = false;
  let providers = []; // filled from GET api.php?action=meta

  // -----------------------------------------------------------------
  // Persistence
  // -----------------------------------------------------------------
  function saveConversation() {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(conversation));
    } catch (e) {
      // localStorage might be full or disabled — fail silently, chat still works.
      console.warn('Could not save conversation to localStorage:', e);
    }
  }

  function loadConversation() {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      if (!raw) return [];
      const parsed = JSON.parse(raw);
      return Array.isArray(parsed) ? parsed : [];
    } catch (e) {
      return [];
    }
  }

  // -----------------------------------------------------------------
  // Rendering helpers
  // -----------------------------------------------------------------
  function escapeHtml(str) {
    return str
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  /**
   * Turns plain text (possibly containing ```code``` fences and `inline code`)
   * into safe HTML. Everything outside code is escaped; code content is
   * also escaped (so no injected HTML can execute), just displayed in <pre><code>.
   */
  function renderMessageContent(text) {
    const fenceRegex = /```(\w*)\n?([\s\S]*?)```/g;
    let lastIndex = 0;
    let html = '';
    let match;
    let blockCount = 0;

    while ((match = fenceRegex.exec(text)) !== null) {
      const before = text.slice(lastIndex, match.index);
      if (before) {
        html += formatPlainSegment(before);
      }

      const lang = (match[1] || 'code').trim();
      const code = match[2];
      blockCount++;
      const blockId = 'code-' + Date.now() + '-' + blockCount + '-' + Math.floor(Math.random() * 10000);

      html += `
        <div class="code-block">
          <div class="code-block-header">
            <span>${escapeHtml(lang || 'code')}</span>
            <button type="button" class="copy-btn" data-target="${blockId}">copy</button>
          </div>
          <pre><code id="${blockId}">${escapeHtml(code.replace(/\n$/, ''))}</code></pre>
        </div>
      `;

      lastIndex = fenceRegex.lastIndex;
    }

    const rest = text.slice(lastIndex);
    if (rest) {
      html += formatPlainSegment(rest);
    }

    return html;
  }

  /** Escapes a plain (non-fenced) segment and turns `inline code` into <code>. */
  function formatPlainSegment(segment) {
    const escaped = escapeHtml(segment);
    return escaped.replace(/`([^`\n]+)`/g, '<code class="inline">$1</code>');
  }

  function scrollToBottom() {
    chatLog.scrollTop = chatLog.scrollHeight;
  }

  function updateEmptyState() {
    emptyState.style.display = conversation.length === 0 ? 'block' : 'none';
  }

  function appendMessageToDOM(role, text) {
    const wrapper = document.createElement('div');
    wrapper.className = 'msg ' + (role === 'user' ? 'msg-user' : 'msg-ai');

    const roleLabel = document.createElement('span');
    roleLabel.className = 'msg-role';
    roleLabel.textContent = role === 'user' ? 'you' : 'ai';
    wrapper.appendChild(roleLabel);

    const body = document.createElement('div');
    body.className = 'msg-body';
    body.innerHTML = renderMessageContent(text);
    wrapper.appendChild(body);

    chatLog.appendChild(wrapper);
    return wrapper;
  }

  function renderFullConversation() {
    // Clear log except empty state node.
    chatLog.innerHTML = '';
    chatLog.appendChild(emptyState);

    conversation.forEach((m) => appendMessageToDOM(m.role, m.text));
    updateEmptyState();
    scrollToBottom();
  }

  function addTypingIndicator() {
    const el = document.createElement('div');
    el.className = 'typing';
    el.id = 'typingIndicator';
    el.innerHTML = '<span></span><span></span><span></span>';
    chatLog.appendChild(el);
    scrollToBottom();
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

  // -----------------------------------------------------------------
  // Copy-to-clipboard for code blocks (event delegation)
  // -----------------------------------------------------------------
  chatLog.addEventListener('click', (e) => {
    const btn = e.target.closest('.copy-btn');
    if (!btn) return;

    const targetId = btn.getAttribute('data-target');
    const codeEl = document.getElementById(targetId);
    if (!codeEl) return;

    const text = codeEl.textContent;

    const done = () => {
      const original = btn.textContent;
      btn.textContent = 'copied!';
      setTimeout(() => { btn.textContent = original; }, 1500);
    };

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(done).catch(() => fallbackCopy(text, done));
    } else {
      fallbackCopy(text, done);
    }
  });

  function fallbackCopy(text, cb) {
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); } catch (e) { /* ignore */ }
    document.body.removeChild(ta);
    cb();
  }

  // -----------------------------------------------------------------
  // Textarea auto-resize
  // -----------------------------------------------------------------
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
    // Shift+Enter: let default behavior insert a newline.
  });

  // -----------------------------------------------------------------
  // Provider / model selection
  // -----------------------------------------------------------------
  function saveSelection() {
    try {
      localStorage.setItem(SELECTION_KEY, JSON.stringify({
        provider: providerSelect.value,
        model: modelSelect.value,
      }));
    } catch (e) { /* ignore */ }
  }

  function loadSelection() {
    try {
      const raw = localStorage.getItem(SELECTION_KEY);
      return raw ? JSON.parse(raw) : null;
    } catch (e) {
      return null;
    }
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

    const saved = loadSelection();
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

  // -----------------------------------------------------------------
  // Sending messages
  // -----------------------------------------------------------------
  async function sendMessage(userText) {
    isSending = true;
    sendBtn.disabled = true;
    hideError();

    conversation.push({ role: 'user', text: userText });
    appendMessageToDOM('user', userText);
    updateEmptyState();
    saveConversation();
    scrollToBottom();

    addTypingIndicator();

    try {
      const response = await fetch('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          provider: providerSelect.value,
          model: modelSelect.value,
          message: userText,
          // Send prior history (excluding the message we just added, server appends it).
          history: conversation.slice(0, -1),
        }),
      });

      let data;
      try {
        data = await response.json();
      } catch (parseErr) {
        throw new Error('Server returned an unexpected response.');
      }

      removeTypingIndicator();

      if (!response.ok || data.error) {
        showError(data.error || ('Request failed (HTTP ' + response.status + ').'));
        return;
      }

      const reply = data.reply || '(empty response)';
      conversation.push({ role: 'model', text: reply });
      appendMessageToDOM('model', reply);
      saveConversation();
      scrollToBottom();

    } catch (err) {
      removeTypingIndicator();
      showError('Network error: could not reach the server. Check your connection and try again.');
      console.error(err);
    } finally {
      isSending = false;
      sendBtn.disabled = false;
    }
  }

  chatForm.addEventListener('submit', (e) => {
    e.preventDefault();
    if (isSending) return;

    const text = chatInput.value.trim();
    if (!text) return;

    chatInput.value = '';
    autoResizeTextarea();
    sendMessage(text);
  });

  // -----------------------------------------------------------------
  // New / Clear conversation
  // -----------------------------------------------------------------
  function startNewConversation() {
    if (conversation.length > 0) {
      const ok = confirm('Start a new conversation? Current chat will be cleared.');
      if (!ok) return;
    }
    conversation = [];
    saveConversation();
    renderFullConversation();
    hideError();
    chatInput.focus();
  }

  function clearConversation() {
    if (conversation.length === 0) return;
    const ok = confirm('Clear the entire conversation? This cannot be undone.');
    if (!ok) return;
    conversation = [];
    saveConversation();
    renderFullConversation();
    hideError();
  }

  newChatBtn.addEventListener('click', startNewConversation);
  clearChatBtn.addEventListener('click', clearConversation);

  // -----------------------------------------------------------------
  // Init
  // -----------------------------------------------------------------
  conversation = loadConversation();
  renderFullConversation();
  loadProviders();
  chatInput.focus();
})();
