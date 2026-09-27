<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');

$configPath = __DIR__ . '/config.php';
$appName = 'NigAI';
if (file_exists($configPath)) {
    $config = require $configPath;
    $appName = $config['app_name'] ?? 'NigAI';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title><?php echo htmlspecialchars($appName, ENT_QUOTES, 'UTF-8'); ?></title>
<link rel="stylesheet" href="assets/css/app.css">
<link rel="icon" type="image/x-icon" href="favicon.ico">
<script>
(function () {
  try {
    var t = localStorage.getItem('nigai-theme');
    if (t !== 'light' && t !== 'dark') t = 'dark';
    document.documentElement.setAttribute('data-theme', t);
  } catch (e) {
    document.documentElement.setAttribute('data-theme', 'dark');
  }
})();
</script>
</head>
<body>

<div class="app" id="app">

  <div class="sidebar-scrim" id="sidebarScrim"></div>

  <aside class="sidebar" id="sidebar">
    <div class="sidebar-head">
      <div class="brand">
        <span class="brand-mark" aria-hidden="true"></span>
        <span class="brand-text"><?php echo htmlspecialchars($appName, ENT_QUOTES, 'UTF-8'); ?></span>
      </div>
      <button type="button" class="icon-btn sidebar-close" id="sidebarCloseBtn" title="Close sidebar" aria-label="Close sidebar">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M6 6L18 18M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
      </button>
    </div>

    <button type="button" class="new-chat-btn" id="newChatBtn">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M12 5V19M5 12H19" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
      <span>New chat</span>
    </button>

    <nav class="chat-history" id="chatHistory" aria-label="Chat history"></nav>

    <div class="sidebar-foot">
      <button type="button" class="sidebar-foot-btn" id="settingsBtn">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z" stroke="currentColor" stroke-width="1.6"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1.08-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 8.91a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z" stroke="currentColor" stroke-width="1.2"/></svg>
        <span>Settings</span>
      </button>
      <button type="button" class="sidebar-foot-btn" id="aboutBtn">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.6"/><path d="M12 11v5.5M12 8v.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        <span>About</span>
      </button>
    </div>
  </aside>

  <div class="main">
    <header class="topbar">
      <button type="button" class="icon-btn sidebar-open" id="sidebarOpenBtn" title="Open sidebar" aria-label="Open sidebar">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M4 6H20M4 12H20M4 18H20" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
      </button>

      <div class="model-bar">
        <select id="providerSelect" class="model-select" title="AI provider" aria-label="AI provider"></select>
        <select id="modelSelect" class="model-select" title="Model" aria-label="Model"></select>
      </div>

      <button type="button" id="themeToggleBtn" class="icon-btn icon-btn-theme" title="Toggle theme" aria-label="Toggle theme">
        <svg class="icon-sun" width="16" height="16" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="4.2" stroke="currentColor" stroke-width="1.6"/><path d="M12 2.5V5M12 19V21.5M4.5 12H2M22 12H19.5M5.6 5.6L7.4 7.4M18.4 18.4L16.6 16.6M18.4 5.6L16.6 7.4M5.6 18.4L7.4 16.6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
        <svg class="icon-moon" width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M20 14.5A8.5 8.5 0 1 1 9.5 4a7 7 0 0 0 10.5 10.5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
      </button>
    </header>

    <main class="chat-wrap">
      <div id="chatLog" class="chat-log">
        <div class="chat-log-inner" id="chatLogInner">
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
      </div>

      <div id="errorBanner" class="error-banner hidden" role="alert"></div>

      <form id="chatForm" class="chat-form" autocomplete="off">
        <textarea
          id="chatInput"
          class="chat-input"
          placeholder="Message NigAI..."
          rows="1"
          maxlength="8000"
          aria-label="Message"
        ></textarea>
        <button type="submit" id="sendBtn" class="send-btn" title="Send" aria-label="Send message">
          <svg class="send-icon" width="17" height="17" viewBox="0 0 24 24" fill="none"><path d="M4 12L20 4L13 20L11 13L4 12Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" stroke-linecap="round"/></svg>
          <svg class="stop-icon" width="14" height="14" viewBox="0 0 24 24" fill="none"><rect x="5" y="5" width="14" height="14" rx="2" fill="currentColor"/></svg>
        </button>
      </form>
      <div class="hint-row">
        <span>NigAI can make mistakes. Check important info.</span>
      </div>
    </main>
  </div>
</div>

<div class="dialog-scrim hidden" id="dialogScrim"></div>

<div class="dialog hidden" id="settingsDialog" role="dialog" aria-modal="true" aria-labelledby="settingsTitle">
  <div class="dialog-head">
    <h2 id="settingsTitle">Settings</h2>
    <button type="button" class="icon-btn dialog-close" data-close-dialog aria-label="Close">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M6 6L18 18M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
    </button>
  </div>
  <div class="dialog-body">

    <section class="settings-section">
      <h3>Appearance</h3>
      <div class="settings-row">
        <div>
          <p class="settings-label">Theme</p>
          <p class="settings-sub">Switch between light and dark</p>
        </div>
        <div class="segmented" id="themeSegmented">
          <button type="button" data-theme-choice="dark" class="segmented-btn">Dark</button>
          <button type="button" data-theme-choice="light" class="segmented-btn">Light</button>
        </div>
      </div>
      <div class="settings-row">
        <div>
          <p class="settings-label">Animations</p>
          <p class="settings-sub">Interface motion and transitions</p>
        </div>
        <label class="switch">
          <input type="checkbox" id="animationsToggle" checked>
          <span class="switch-track"><span class="switch-thumb"></span></span>
        </label>
      </div>
      <div class="settings-row">
        <div>
          <p class="settings-label">Compact messages</p>
          <p class="settings-sub">Reduce spacing between messages</p>
        </div>
        <label class="switch">
          <input type="checkbox" id="compactToggle">
          <span class="switch-track"><span class="switch-thumb"></span></span>
        </label>
      </div>
    </section>

    <section class="settings-section">
      <h3>Data</h3>
      <div class="settings-row">
        <div>
          <p class="settings-label">Local conversations</p>
          <p class="settings-sub">Stored only in this browser</p>
        </div>
        <button type="button" class="danger-btn" id="clearAllDataBtn">Clear all data</button>
      </div>
    </section>

    <section class="settings-section">
      <h3>Keyboard shortcuts</h3>
      <div class="shortcut-row"><span>Send message</span><span class="kbd-group"><kbd>Enter</kbd></span></div>
      <div class="shortcut-row"><span>New line</span><span class="kbd-group"><kbd>Shift</kbd><kbd>Enter</kbd></span></div>
      <div class="shortcut-row"><span>Focus message box</span><span class="kbd-group"><kbd>Ctrl/Cmd</kbd><kbd>K</kbd></span></div>
      <div class="shortcut-row"><span>Close dialogs</span><span class="kbd-group"><kbd>Esc</kbd></span></div>
    </section>

  </div>
</div>

<div class="dialog dialog-narrow hidden" id="aboutDialog" role="dialog" aria-modal="true" aria-labelledby="aboutTitle">
  <div class="dialog-head">
    <h2 id="aboutTitle">About NigAI</h2>
    <button type="button" class="icon-btn dialog-close" data-close-dialog aria-label="Close">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M6 6L18 18M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
    </button>
  </div>
  <div class="dialog-body">
    <p>NigAI is a local-first AI chat interface made by MEEEEE (John David Zamora), also known as Zacuia. Walang account needed, and nothing gets sent anywhere except your messages to the AI provider you choose.</p>
    <p>Your conversations stay only in this browser's local storage. Clear your browser data or use another device, and ayun, fresh start ulit. Walang magic backup sa langit.</p>
    <p>NigAI can keep multiple API keys and AI providers ready in case one hits a rate limit. Kapag may isang bumigay, quietly lilipat siya sa next available one para tuloy lang ang usapan.</p>
  </div>
</div>

<script src="assets/js/vendor/marked.min.js"></script>
<script src="assets/js/vendor/purify.min.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
