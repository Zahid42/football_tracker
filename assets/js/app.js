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

/* Search boxes: live suggestions by name or 6-digit ID, filtered by the "Players / Teams / Competitions" dropdown */
document.querySelectorAll('[data-ft-search]').forEach(function (form) {
  var input = form.querySelector('input[name=q]'), box = form.querySelector('.nav-suggest'), type = form.querySelector('select[name=type]');
  if (!input || !box || !window.FT_SUGGEST) { return; }
  var timer = null, seq = 0, items = [], active = -1;
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
  function close() { box.hidden = true; active = -1; }
  function render() {
    if (!items.length) { box.innerHTML = '<div class="ns-empty">No matches</div>'; box.hidden = false; return; }
    box.innerHTML = items.map(function (it, i) {
      return '<a class="ns-item' + (i === active ? ' active' : '') + '" role="option" href="' + esc(it.url) + '"><i class="bi ' + esc(it.icon) + '"></i><span><b>' + esc(it.title) + '</b><small>' + esc(it.type) + (it.sub ? ' &middot; ' + esc(it.sub) : '') + '</small></span></a>';
    }).join('');
    box.hidden = false;
  }
  function lookup() {
    clearTimeout(timer); var q = input.value.trim();
    if (q.length < 2) { close(); return; }
    timer = setTimeout(function () {
      var my = ++seq;
      fetch(window.FT_SUGGEST + '?type=' + encodeURIComponent(type ? type.value : 'all') + '&q=' + encodeURIComponent(q), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) { if (my !== seq) { return; } items = j; active = -1; render(); }).catch(close);
    }, 200);
  }
  input.addEventListener('input', lookup);
  if (type) { type.addEventListener('change', function () { lookup(); input.focus(); }); }
  input.addEventListener('keydown', function (e) {
    if (box.hidden || !items.length) { return; }
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); active = (active + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length; render(); }
    else if (e.key === 'Enter' && active >= 0) { e.preventDefault(); window.location.href = items[active].url; }
    else if (e.key === 'Escape') { close(); }
  });
  document.addEventListener('click', function (e) { if (!form.contains(e.target)) { close(); } });
});
