/* Football Tracker - shared JS: theme toggle + chart defaults */
(function () {
  'use strict';

  function currentTheme() {
    return document.documentElement.getAttribute('data-theme') || 'dark';
  }

  document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var order = ['dark', 'light', 'ocean'];
      var next = order[(order.indexOf(currentTheme()) + 1) % order.length];
      document.documentElement.setAttribute('data-theme', next);
      try { localStorage.setItem('ft-theme', next); } catch (e) { /* ignore */ }
      // Charts read colours at creation; reload keeps things simple and correct.
      if (window.Chart) { window.location.reload(); }
    });
  });

  // Confirm dialogs for destructive actions: <form data-confirm="Delete this?">
  document.addEventListener('submit', function (ev) {
    var msg = ev.target.getAttribute && ev.target.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) { ev.preventDefault(); }
  });

  // Shared chart colours (read from CSS variables so light/dark both work)
  window.FT = {
    css: function (name) {
      return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    },
    initChartDefaults: function () {
      if (!window.Chart) { return; }
      Chart.defaults.color = this.css('--muted');
      Chart.defaults.borderColor = this.css('--card-border');
      Chart.defaults.font.family = "'Segoe UI', system-ui, sans-serif";
      Chart.defaults.maintainAspectRatio = false;
      Chart.defaults.plugins.legend.labels.usePointStyle = true;
    }
  };
})();

document.addEventListener('click',function(e){var c=e.target.closest('[data-copy]');if(!c){return;}var t=location.origin+c.getAttribute('data-copy');try{navigator.clipboard.writeText(t);c.dataset.old=c.dataset.old||c.innerHTML;c.innerHTML='<i class="bi bi-check2"></i> Copied';setTimeout(function(){c.innerHTML=c.dataset.old},1200)}catch(x){prompt('Copy this link',t)}});

/* PWA: register the service worker and offer "Install app" where the browser supports it */
(function () {
  if ('serviceWorker' in navigator && window.FT_SW) {
    window.addEventListener('load', function () { navigator.serviceWorker.register(window.FT_SW).catch(function () {}); });
  }
  var deferred = null, btn = document.getElementById('appInstall');
  window.addEventListener('beforeinstallprompt', function (e) { e.preventDefault(); deferred = e; if (btn) { btn.hidden = false; } });
  if (btn) { btn.addEventListener('click', function () { if (!deferred) { return; } deferred.prompt(); deferred.userChoice.finally(function () { deferred = null; btn.hidden = true; }); }); }
  window.addEventListener('appinstalled', function () { if (btn) { btn.hidden = true; } });
})();
