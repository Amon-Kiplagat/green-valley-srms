(function () {
  'use strict';
  if ('serviceWorker' in navigator && location.protocol !== 'file:') {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register('service-worker.js').catch(function (err) {
        console.warn('[PWA] Service worker registration failed:', err);
      });
    });
  }
  function initDrawer() {
    var sidebar = document.getElementById('sidebar');
    var toggle = document.querySelector('.nav-toggle');
    if (!sidebar || !toggle) return;

    var backdrop = document.createElement('div');
    backdrop.className = 'nav-backdrop';
    document.body.appendChild(backdrop);

    function openNav() {
      sidebar.classList.add('open');
      backdrop.classList.add('show');
      document.body.classList.add('nav-open');
      toggle.setAttribute('aria-expanded', 'true');
    }
    function closeNav() {
      sidebar.classList.remove('open');
      backdrop.classList.remove('show');
      document.body.classList.remove('nav-open');
      toggle.setAttribute('aria-expanded', 'false');
    }
    function isOpen() {
      return sidebar.classList.contains('open');
    }

    toggle.addEventListener('click', function () {
      isOpen() ? closeNav() : openNav();
    });
    backdrop.addEventListener('click', closeNav);

    sidebar.addEventListener('click', function (e) {
      if (e.target.closest('a')) closeNav();
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && isOpen()) closeNav();
    });

    window.addEventListener('resize', function () {
      if (window.innerWidth > 860 && isOpen()) closeNav();
    });
  }
  function initLogoutPurge() {
    document.addEventListener('click', function (e) {
      var a = e.target.closest('a[href]');
      if (!a) return;
      var href = a.getAttribute('href') || '';
      if (/logout\.php(?:$|[?#])/.test(href)) {
        if (navigator.serviceWorker && navigator.serviceWorker.controller) {
          navigator.serviceWorker.controller.postMessage({ type: 'CLEAR_PAGES' });
        }
      }
    });
  }
  function initInstallButton() {
    var deferredPrompt = null;
    var btn = null;

    function makeButton() {
      if (btn) return btn;
      btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'pwa-install';
      btn.setAttribute('hidden', '');
      btn.innerHTML =
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" ' +
        'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
        '<path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/></svg>' +
        '<span>Install app</span>';
      btn.addEventListener('click', function () {
        if (!deferredPrompt) return;
        deferredPrompt.prompt();
        deferredPrompt.userChoice.finally(function () {
          deferredPrompt = null;
          hide();
        });
      });
      document.body.appendChild(btn);
      return btn;
    }
    function show() { makeButton().removeAttribute('hidden'); }
    function hide() { if (btn) btn.setAttribute('hidden', ''); }

    window.addEventListener('beforeinstallprompt', function (e) {
      e.preventDefault();      // stop Chrome's default mini-infobar
      deferredPrompt = e;      // stash it to trigger from our own button
      show();
    });
    window.addEventListener('appinstalled', function () {
      deferredPrompt = null;
      hide();
    });
  }
  function init() {
    initDrawer();
    initLogoutPurge();
    initInstallButton();
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
