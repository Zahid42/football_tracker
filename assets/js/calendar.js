/* Monthly match calendar. Events: window.CH.events [{date, title, result, url}] */
(function () {
  'use strict';
  var events = (window.CH && CH.events) || [];
  var byDate = {};
  events.forEach(function (e) { (byDate[e.date] = byDate[e.date] || []).push(e); });
  var latest = events.length ? events.reduce(function (a, b) { return a.date > b.date ? a : b; }).date : null;
  var now = new Date();
  var cur = new Date(now.getFullYear(), now.getMonth(), 1);
  if (latest && !byDate[now.toISOString().slice(0, 7) + '-01'] && !events.some(function (e) { return e.date.slice(0, 7) === now.toISOString().slice(0, 7); })) {
    var d = new Date(latest + 'T00:00:00'); cur = new Date(d.getFullYear(), d.getMonth(), 1);
  }
  var grid = document.getElementById('calGrid'), title = document.getElementById('calTitle');
  var months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
  var days = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
  function render() {
    title.textContent = months[cur.getMonth()] + ' ' + cur.getFullYear();
    var html = days.map(function (d) { return '<div class="cal-dow">' + d + '</div>'; }).join('');
    var first = new Date(cur.getFullYear(), cur.getMonth(), 1);
    var offset = (first.getDay() + 6) % 7;
    var total = new Date(cur.getFullYear(), cur.getMonth() + 1, 0).getDate();
    for (var i = 0; i < offset; i++) { html += '<div class="cal-cell empty"></div>'; }
    for (var day = 1; day <= total; day++) {
      var key = cur.getFullYear() + '-' + pad(cur.getMonth() + 1) + '-' + pad(day);
      var isToday = key === now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate());
      html += '<div class="cal-cell' + (isToday ? ' today' : '') + '"><span class="cal-day">' + day + '</span>' + (CH.addUrl ? '<a class="cal-add" href="' + esc(CH.addUrl + key) + '" title="Add event" aria-label="Add event on ' + key + '">+</a>' : '');
      (byDate[key] || []).forEach(function (e) {
        var c = e.kind ? 'kind-' + e.kind.toLowerCase() : 'result-' + ({ W: 'win', D: 'draw', L: 'loss' }[e.result] || 'draw');
        var tip = e.tip || e.title;
        html += e.url ? '<a class="cal-ev ' + c + '" href="' + esc(e.url) + '" title="' + esc(tip) + '">' + esc(e.title) + '</a>' : '<span class="cal-ev ' + c + '" title="' + esc(tip) + '">' + esc(e.title) + '</span>';
      });
      html += '</div>';
    }
    grid.innerHTML = html;
  }
  document.getElementById('calPrev').onclick = function () { cur = new Date(cur.getFullYear(), cur.getMonth() - 1, 1); render(); };
  document.getElementById('calNext').onclick = function () { cur = new Date(cur.getFullYear(), cur.getMonth() + 1, 1); render(); };
  document.getElementById('calToday').onclick = function () { cur = new Date(now.getFullYear(), now.getMonth(), 1); render(); };
  render();
})();
