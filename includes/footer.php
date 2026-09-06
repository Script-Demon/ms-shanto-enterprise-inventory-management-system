</main>
<script>
/* ---- Installable app ----
   Registers the service worker and remembers Chrome's install prompt so the
   button on the Settings page can trigger it later. */
window.APP_INSTALL = { prompt: null, installed: false };
(function () {
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register(<?= json_encode(url('sw.js')) ?>, { scope: <?= json_encode(url('')) ?> })
        .catch(function () { /* needs HTTPS (or localhost); ignore otherwise */ });
    });
  }
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    window.APP_INSTALL.prompt = e;
    document.dispatchEvent(new CustomEvent('app-install-ready'));
  });
  window.addEventListener('appinstalled', function () {
    window.APP_INSTALL.prompt = null;
    window.APP_INSTALL.installed = true;
    document.dispatchEvent(new CustomEvent('app-installed'));
  });
})();

/* ---- Light / dark theme ---- */
(function () {
  var KEY = 'shanto-theme';
  var root = document.documentElement;

  function activeTheme() {
    var saved = null;
    try { saved = localStorage.getItem(KEY); } catch (e) {}
    if (saved === 'dark' || saved === 'light') return saved;
    return 'light';
  }

  document.querySelectorAll('.theme-toggle').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var next = activeTheme() === 'dark' ? 'light' : 'dark';
      root.setAttribute('data-theme', next);
      try { localStorage.setItem(KEY, next); } catch (e) {}
      var meta = document.querySelector('meta[name="theme-color"]');
      if (meta) meta.setAttribute('content', next === 'dark' ? '#171a23' : '#4f46e5');
    });
  });
})();

/* ---- Mobile navigation drawer ---- */
(function () {
  var drawer = document.getElementById('navDrawer');
  var backdrop = document.getElementById('drawerBackdrop');
  if (!drawer || !backdrop) return;

  // Anything that should open the full menu: the app-bar hamburger and the
  // bottom-bar "Menu" tab.
  var openers = [];
  var toggle = document.getElementById('menuToggle');
  if (toggle) openers.push(toggle);
  Array.prototype.push.apply(openers, document.querySelectorAll('.menu-open'));

  function open() {
    drawer.hidden = false;
    backdrop.hidden = false;
    document.body.classList.add('menu-open-lock');
    requestAnimationFrame(function () {
      drawer.classList.add('is-open');
      backdrop.classList.add('is-open');
    });
    if (toggle) toggle.setAttribute('aria-expanded', 'true');
  }

  function close() {
    drawer.classList.remove('is-open');
    backdrop.classList.remove('is-open');
    document.body.classList.remove('menu-open-lock');
    if (toggle) toggle.setAttribute('aria-expanded', 'false');
    setTimeout(function () {
      drawer.hidden = true;
      backdrop.hidden = true;
    }, 220);
  }

  openers.forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (drawer.hidden) { open(); } else { close(); }
    });
  });

  var closeBtn = document.getElementById('drawerClose');
  if (closeBtn) closeBtn.addEventListener('click', close);
  backdrop.addEventListener('click', close);
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !drawer.hidden) close();
  });
})();
</script>
</body>
</html>
