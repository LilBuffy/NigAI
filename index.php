<?php
/**
 * index.php
 * ---------------------------------------------------------
 * Renders the chat UI. No login, no accounts — the chat is
 * usable the instant the page loads. All AI provider communication
 * happens later via fetch() calls to api.php.
 * ---------------------------------------------------------
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1">
<title>NigAI</title>
<link rel="stylesheet" href="style.css">
<link rel="icon" type="image/x-icon" href="favicon.ico">
<script>
(function () {
  try {
    var t = localStorage.getItem('nigai-theme');
    if (!t) t = 'dark';
    document.documentElement.setAttribute('data-theme', t);
  } catch (e) {
    document.documentElement.setAttribute('data-theme', 'dark');
  }
})();
</script>
</head>
<body>

<div class="app">

  <header class="topbar">
    <div class="brand">
      <span class="brand-mark"></span>
      <span class="brand-text">Nigai Chatbot</span>
    </div>
    <div class="topbar-actions">
      <button id="themeToggleBtn" class="icon-btn icon-btn-theme" title="Toggle theme" onclick="
        var html = document.documentElement;
        var cur = html.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
        var next = cur === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-theme', next);
        try { localStorage.setItem('nigai-theme', next); } catch (e) {}
      ">
        <svg class="icon-sun" width="15" height="15" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="12" cy="12" r="4.2" stroke="currentColor" stroke-width="1.6"/>
          <path d="M12 2.5V5M12 19V21.5M4.5 12H2M22 12H19.5M5.6 5.6L7.4 7.4M18.4 18.4L16.6 16.6M18.4 5.6L16.6 7.4M5.6 18.4L7.4 16.6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
        </svg>
        <svg class="icon-moon" width="15" height="15" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M20 14.5A8.5 8.5 0 1 1 9.5 4a7 7 0 0 0 10.5 10.5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
        </svg>
      </button>
      <button id="newChatBtn" class="icon-btn" title="New conversation">New</button>
      <button id="clearChatBtn" class="icon-btn" title="Clear conversation">Clear</button>
    </div>
  </header>

  <div class="model-bar">
    <select id="providerSelect" class="model-select" title="AI provider"></select>
    <select id="modelSelect" class="model-select" title="Model"></select>
  </div>

  <main class="chat-wrap">
    <div id="chatLog" class="chat-log">
      <div id="emptyState" class="empty-state">
        <pre class="ascii-art" aria-hidden="true">
 /$$   /$$ /$$$$$$  /$$$$$$   /$$$$$$  /$$$$$$
| $$$ | $$|_  $$_/ /$$__  $$ /$$__  $$|_  $$_/
| $$$$| $$  | $$  | $$  \__/| $$  \ $$  | $$  
| $$ $$ $$  | $$  | $$ /$$$$| $$$$$$$$  | $$  
| $$  $$$$  | $$  | $$|_  $$| $$__  $$  | $$  
| $$\  $$$  | $$  | $$  \ $$| $$  | $$  | $$  
| $$ \  $$ /$$$$$$|  $$$$$$/| $$  | $$ /$$$$$$
|__/  \__/|______/ \______/ |__/  |__/|______/
        </pre>
        <p class="empty-title">Start a conversation</p>
        <p class="empty-sub">Type a message below to begin</p>
      </div>
    </div>

    <div id="errorBanner" class="error-banner hidden"></div>

    <form id="chatForm" class="chat-form" autocomplete="off">
      <textarea
        id="chatInput"
        class="chat-input"
        placeholder="Message..."
        rows="1"
        maxlength="8000"
      ></textarea>
      <button type="submit" id="sendBtn" class="send-btn" title="Send">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M4 12L20 4L13 20L11 13L4 12Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" stroke-linecap="round"/>
        </svg>
      </button>
    </form>
    <div class="hint-row">
      <span>Nigai can make mistakes. Check important info.</span>
    </div>
  </main>
</div>

<script src="script.js"></script>
</body>
</html>
