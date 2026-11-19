/* Formation picker for every pitch view: re-arranges the players on the pitch for the chosen formation. */
(function () {
  'use strict';
  var GROUP = { GK: 'g', LB: 'd', CB: 'd', RB: 'd', CDM: 'm', CM: 'm', CAM: 'm', LM: 'm', RM: 'm', LW: 'f', ST: 'f', CF: 'f', RW: 'f' };
  var SIDE = { LB: -1, LM: -1, LW: -1, RB: 1, RM: 1, RW: 1 };

  function slotsFor(key, horizontal) {
    var lines = (window.FT_FORMATIONS || {})[key]; if (!lines) { return null; }
    var n = lines.length, out = [];
    lines.forEach(function (line, i) {
      var y = 90 - i * (78 / (n - 1)), c = line.length;
      line.forEach(function (pos, j) {
        var x = (j + 1) / (c + 1) * 100;
        out.push({ pos: pos, left: horizontal ? 100 - y : x, top: horizontal ? x : y, side: j / Math.max(1, c - 1) });
      });
    });
    return out;
  }
  function score(playerPos, slot) {
    if (slot.pos === 'GK' || playerPos === 'GK') { return slot.pos === playerPos ? 20 : -20; }
    var s = 0;
    if (playerPos === slot.pos) { s += 10; }
    else if (GROUP[playerPos] && GROUP[playerPos] === GROUP[slot.pos]) { s += 6; }
    else if (GROUP[playerPos]) { s += 1; }
    if (SIDE[playerPos] && SIDE[playerPos] === (slot.side < 0.34 ? -1 : slot.side > 0.66 ? 1 : 0)) { s += 2; }
    return s;
  }
  function assignByPosition(players, slots) {
    var pairs = [];
    players.forEach(function (p, i) { slots.forEach(function (s, j) { pairs.push([score(p.getAttribute('data-pos') || '', s), i, j]); }); });
    pairs.sort(function (a, b) { return b[0] - a[0] || a[1] - b[1] || a[2] - b[2]; });
    var pu = {}, su = {}, res = [];
    pairs.forEach(function (t) { if (!pu[t[1]] && !su[t[2]]) { pu[t[1]] = su[t[2]] = 1; res[t[1]] = t[2]; } });
    return res;
  }

  function init(box) {
    var sel = box.querySelector('.fp-select'), pitch = box.querySelector('.pitch');
    if (!sel || !pitch) { return; }
    var mode = box.getAttribute('data-fp'), horizontal = box.getAttribute('data-h') === '1';
    var pls = [].slice.call(pitch.querySelectorAll('.pl'));
    var orig = pls.map(function (el) { var pp = el.querySelector('.ppos'); return { l: el.style.left, t: el.style.top, pos: pp ? pp.textContent : '' }; });
    var head = box.querySelector('.sq-form'), headOrig = head ? head.textContent : '';
    sel.addEventListener('change', function () {
      if (!sel.value) {
        pls.forEach(function (el, i) { el.style.left = orig[i].l; el.style.top = orig[i].t; var pp = el.querySelector('.ppos'); if (pp) { pp.textContent = orig[i].pos; } });
        if (head) { head.textContent = headOrig; }
        return;
      }
      var slots = slotsFor(sel.value, horizontal); if (!slots) { return; }
      var order = pls.map(function (_, i) { return i; }), map;
      if (mode === 'match') {   // keep line-up order: slot 1 = goalkeeper, then defence to attack
        order.sort(function (a, b) { return (+pls[a].getAttribute('data-slot') || 0) - (+pls[b].getAttribute('data-slot') || 0); });
        map = []; order.forEach(function (pi, k) { map[pi] = k; });
      } else { map = assignByPosition(pls, slots); }
      pls.forEach(function (el, i) {
        var s = slots[map[i]]; if (!s) { return; }
        el.style.left = s.left + '%'; el.style.top = s.top + '%';
        var pp = el.querySelector('.ppos'); if (pp) { pp.textContent = s.pos; }
        el.classList.toggle('is-gk', s.pos === 'GK');
      });
      if (head) { head.textContent = sel.value; }
    });
  }
  function sizePicker() {   // "Players on pitch": reload this page with ?pitch=N and come back to the squad
    [].forEach.call(document.querySelectorAll('[data-pitch-size]'), function (sel) {
      sel.addEventListener('change', function () {
        var u = new URL(window.location.href); u.searchParams.set('pitch', sel.value); u.hash = 'squad'; window.location.href = u.toString();
      });
    });
  }
  function boot() { sizePicker(); [].forEach.call(document.querySelectorAll('.squad-view[data-fp]'), init); }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
})();
