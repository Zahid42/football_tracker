/* Premium page snapshot: captures the page content, frames it with a heading, lets the user edit the heading, download or share. */
(function () {
  'use strict';
  var fab = document.getElementById('snapFab');
  if (!fab) { return; }
  var cap = null, modal = null, headingInput = null, preview = null, busy = false, timer = null;

  function cssv(n, d) { var v = getComputedStyle(document.documentElement).getPropertyValue(n).trim(); return v || d; }
  function pageHeading() {
    var h = document.querySelector('.page-hero h1, .admin-content h1, main h1, h1');
    var t = h ? h.textContent.replace(/\s+/g, ' ').trim() : '';
    return t || document.title.split('|')[0].trim() || 'Snapshot';
  }
  function pageSub() {
    var s = document.querySelector('.page-hero p');
    return s ? s.textContent.replace(/\s+/g, ' ').trim() : '';
  }
  function rr(c, x, y, w, h, r) { c.beginPath(); c.moveTo(x + r, y); c.arcTo(x + w, y, x + w, y + h, r); c.arcTo(x + w, y + h, x, y + h, r); c.arcTo(x, y + h, x, y, r); c.arcTo(x, y, x + w, y, r); c.closePath(); }
  function wrap(c, text, maxW, maxLines) {
    var words = text.split(' '), lines = [], line = '';
    words.forEach(function (w) { var t = line ? line + ' ' + w : w; if (c.measureText(t).width > maxW && line) { lines.push(line); line = w; } else { line = t; } });
    if (line) { lines.push(line); }
    if (lines.length > maxLines) { lines = lines.slice(0, maxLines); lines[maxLines - 1] = lines[maxLines - 1].replace(/\s*\S*$/, '') + '…'; }
    return lines;
  }

  function compose(heading) {
    var s = cap.scale, shot = cap.canvas, font = getComputedStyle(document.body).fontFamily;
    var accent = cssv('--accent', '#22c55e'), arg = cssv('--accent-rgb', '34,197,94'), bg = cssv('--bg', '#0b1210'), card = cssv('--card', '#14221d'),
        text = cssv('--text', '#e8f1ec'), muted = cssv('--muted', '#8fa89b'), line = cssv('--card-border', '#1f3329');
    var M = 36 * s, headH = 190 * s, footH = 70 * s, w = shot.width + 2 * M, cw = shot.width;
    var tmp = document.createElement('canvas').getContext('2d');
    tmp.font = '800 ' + 46 * s + 'px ' + font;
    var hl = wrap(tmp, heading, cw - 80 * s, 2);
    var sub = pageSub();
    var hh = headH + (hl.length > 1 ? 52 * s : 0) - (sub ? 0 : 20 * s);
    var h = M + hh + shot.height + footH + M;
    var c = document.createElement('canvas'); c.width = w; c.height = h; var x = c.getContext('2d');
    // outer backdrop
    var g = x.createLinearGradient(0, 0, w, h); g.addColorStop(0, bg); g.addColorStop(1, card); x.fillStyle = g; x.fillRect(0, 0, w, h);
    var rg = x.createRadialGradient(w * 0.85, 0, 0, w * 0.85, 0, w * 0.7); rg.addColorStop(0, 'rgba(' + arg + ',.28)'); rg.addColorStop(1, 'rgba(' + arg + ',0)'); x.fillStyle = rg; x.fillRect(0, 0, w, h);
    // card with shadow
    x.save(); x.shadowColor = 'rgba(0,0,0,.45)'; x.shadowBlur = 40 * s; x.shadowOffsetY = 14 * s; rr(x, M, M, cw, h - 2 * M, 26 * s); x.fillStyle = bg; x.fill(); x.restore();
    x.save(); rr(x, M, M, cw, h - 2 * M, 26 * s); x.clip();
    // header band
    var hg = x.createLinearGradient(M, M, M + cw, M + hh); hg.addColorStop(0, card); hg.addColorStop(1, bg); x.fillStyle = hg; x.fillRect(M, M, cw, hh);
    var hr = x.createRadialGradient(M + cw, M, 0, M + cw, M, cw * 0.6); hr.addColorStop(0, 'rgba(' + arg + ',.35)'); hr.addColorStop(1, 'rgba(' + arg + ',0)'); x.fillStyle = hr; x.fillRect(M, M, cw, hh);
    // decorative pitch circle
    x.strokeStyle = 'rgba(' + arg + ',.14)'; x.lineWidth = 3 * s; x.beginPath(); x.arc(M + cw - 60 * s, M + hh - 20 * s, 120 * s, 0, Math.PI * 2); x.stroke(); x.beginPath(); x.arc(M + cw - 60 * s, M + hh - 20 * s, 60 * s, 0, Math.PI * 2); x.stroke();
    // brand chip
    var px = M + 40 * s, py = M + 36 * s;
    x.fillStyle = accent; x.beginPath(); x.arc(px + 15 * s, py + 15 * s, 15 * s, 0, Math.PI * 2); x.fill();
    x.fillStyle = bg; x.beginPath(); x.arc(px + 15 * s, py + 15 * s, 6 * s, 0, Math.PI * 2); x.fill();
    x.fillStyle = muted; x.font = '700 ' + 15 * s + 'px ' + font; x.textBaseline = 'middle';
    x.fillText(String(window.FT_SNAP.site).toUpperCase().split('').join(String.fromCharCode(8202)), px + 42 * s, py + 15 * s);
    // heading
    x.fillStyle = text; x.font = '800 ' + 46 * s + 'px ' + font; x.textBaseline = 'alphabetic';
    var ty = py + 100 * s;
    hl.forEach(function (l, i) { x.fillText(l, px, ty + i * 52 * s); });
    var yy = ty + (hl.length - 1) * 52 * s;
    if (sub) { x.fillStyle = accent; x.font = '600 ' + 19 * s + 'px ' + font; x.fillText(wrap(x, sub, cw - 80 * s, 1)[0], px, yy + 36 * s); }
    // accent rule
    var ag = x.createLinearGradient(M, 0, M + cw, 0); ag.addColorStop(0, accent); ag.addColorStop(1, 'rgba(' + arg + ',0)'); x.fillStyle = ag; x.fillRect(M, M + hh - 4 * s, cw, 4 * s);
    // content
    x.drawImage(shot, M, M + hh);
    // footer
    var fy = M + hh + shot.height; x.fillStyle = card; x.fillRect(M, fy, cw, footH);
    x.fillStyle = 'rgba(' + arg + ',.5)'; x.fillRect(M, fy, cw, 1.5 * s);
    x.textBaseline = 'middle'; x.fillStyle = muted; x.font = '500 ' + 16 * s + 'px ' + font;
    var d = new Date(); var stamp = d.toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }) + ' · ' + d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
    x.fillText(stamp, M + 40 * s, fy + footH / 2);
    x.textAlign = 'right'; x.fillStyle = accent; x.font = '700 ' + 16 * s + 'px ' + font; x.fillText(window.FT_SNAP.host || window.FT_SNAP.site, M + cw - 40 * s, fy + footH / 2); x.textAlign = 'left';
    x.restore();
    // card border
    x.strokeStyle = line; x.lineWidth = 1.5 * s; rr(x, M, M, cw, h - 2 * M, 26 * s); x.stroke();
    return c;
  }

  function render() {
    if (!cap) { return; }
    var c = compose(headingInput.value.trim() || pageHeading());
    preview.innerHTML = ''; c.className = 'snap-canvas'; preview.appendChild(c); modal._out = c;
  }

  function build() {
    modal = document.createElement('div');
    modal.className = 'snap-modal'; modal.setAttribute('role', 'dialog'); modal.setAttribute('aria-modal', 'true'); modal.setAttribute('aria-label', 'Page snapshot');
    modal.innerHTML = '<div class="snap-panel"><div class="snap-top"><div class="snap-ttl"><i class="bi bi-camera"></i> Snapshot</div>' +
      '<button type="button" class="snap-x" aria-label="Close"><i class="bi bi-x-lg"></i></button></div>' +
      '<label class="snap-lab" for="snapHead">Heading</label><input id="snapHead" class="snap-head" type="text" maxlength="90" autocomplete="off">' +
      '<div class="snap-prev" id="snapPrev"><div class="snap-load"><span></span>Capturing page&hellip;</div></div>' +
      '<div class="snap-acts"><button type="button" class="btn btn-accent" data-a="dl"><i class="bi bi-download"></i> Download PNG</button>' +
      '<button type="button" class="btn btn-outline-theme" data-a="share"><i class="bi bi-share"></i> Share</button>' +
      '<button type="button" class="btn btn-outline-theme" data-a="retake"><i class="bi bi-arrow-repeat"></i> Retake</button></div></div>';
    document.body.appendChild(modal);
    headingInput = modal.querySelector('#snapHead'); preview = modal.querySelector('#snapPrev');
    headingInput.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(render, 120); });
    modal.addEventListener('click', function (e) {
      if (e.target === modal || e.target.closest('.snap-x')) { close(); return; }
      var b = e.target.closest('[data-a]'); if (!b) { return; }
      var a = b.getAttribute('data-a');
      if (a === 'retake') { start(); } else if (a === 'dl') { download(); } else if (a === 'share') { share(); }
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && modal.classList.contains('open')) { close(); } });
    if (!(navigator.canShare && window.File)) { modal.querySelector('[data-a=share]').hidden = true; }
  }
  function close() { modal.classList.remove('open'); document.documentElement.classList.remove('snap-lock'); }
  function fname() { return (headingInput.value || 'snapshot').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 50) + '-' + new Date().toISOString().slice(0, 10) + '.png'; }
  function blob(cb) { modal._out.toBlob(cb, 'image/png'); }
  function download() { blob(function (b) { var a = document.createElement('a'); a.href = URL.createObjectURL(b); a.download = fname(); document.body.appendChild(a); a.click(); a.remove(); setTimeout(function () { URL.revokeObjectURL(a.href); }, 2000); }); }
  function share() { blob(function (b) { var f = new File([b], fname(), { type: 'image/png' }); if (navigator.canShare({ files: [f] })) { navigator.share({ files: [f], title: headingInput.value }).catch(function (err) { if (window.console) { console.error('Snapshot failed', err); }}); } else { download(); } }); }

  function start() {
    if (busy) { return; }
    if (!window.html2canvas) { alert('Snapshot is still loading, try again in a moment.'); return; }
    busy = true;
    if (!modal) { build(); }
    headingInput.value = pageHeading();
    preview.innerHTML = '<div class="snap-load"><span></span>Capturing page&hellip;</div>';
    modal.classList.add('open'); document.documentElement.classList.add('snap-lock');
    var target = document.querySelector('.admin-content') || document.querySelector('main') || document.body;
    var rect = target.getBoundingClientRect();
    var scale = Math.min(2, Math.max(1, window.devicePixelRatio || 1));
    while (rect.height * scale > 14000 && scale > 0.5) { scale -= 0.25; }
    var prevScroll = window.scrollY;
    (document.fonts && document.fonts.ready ? document.fonts.ready : Promise.resolve()).then(function () {
      return window.html2canvas(target, {
        scale: scale, useCORS: true, logging: false, backgroundColor: cssv('--bg', '#0b1210'),
        scrollX: 0, scrollY: -window.scrollY, windowWidth: document.documentElement.clientWidth,
        ignoreElements: function (el) { return el.classList && (el.classList.contains('snap-fab') || el.classList.contains('snap-modal') || el.classList.contains('page-hero') || el.classList.contains('app-tabbar') || el.classList.contains('app-install')); }
      });
    }).then(function (canvas) {
      cap = { canvas: canvas, scale: scale }; render();
    }).catch(function (err) { if (window.console) { console.error('Snapshot failed', err); }
      preview.innerHTML = '<div class="snap-load err">Could not capture this page.</div>';
    }).then(function () { busy = false; window.scrollTo(0, prevScroll); });
  }
  fab.addEventListener('click', start);
})();
