/* Generic chart renderer. Specs come from window.CH.charts (built in PHP). */
(function () {
  'use strict';
  if (!window.Chart || !window.CH || !CH.charts) { return; }
  FT.initChartDefaults();
  var C = { green: FT.css('--win'), blue: FT.css('--blue'), amber: FT.css('--draw'), red: FT.css('--loss'), purple: FT.css('--purple') };
  function col(c) { return C[c] || c; }

  CH.charts.forEach(function (s) {
    var el = document.getElementById(s.id);
    if (!el) { return; }
    var pie = s.type === 'doughnut' || s.type === 'pie';
    var datasets = s.datasets.map(function (d) {
      var colour = Array.isArray(d.color) ? d.color.map(col) : col(d.color || 'green');
      var o = { label: d.label, data: d.data };
      if (pie) { o.backgroundColor = colour; o.borderWidth = 0; }
      else if (s.type === 'line') {
        o.borderColor = colour; o.backgroundColor = d.fill ? colour + '33' : colour; o.fill = !!d.fill;
        o.tension = 0.3; o.pointRadius = 3; o.spanGaps = true;
      } else { o.backgroundColor = colour; o.borderRadius = 6; }
      return o;
    });
    var opts = { plugins: { legend: { display: s.legend !== false, position: 'bottom' } } };
    if (pie) {
      opts.cutout = '62%';
      var total = (s.datasets[0].data || []).reduce(function (a, b) { return a + (+b || 0); }, 0);
      if (!total) { datasets[0].data = [1]; datasets[0].backgroundColor = [FT.css('--card-border')]; opts.plugins.tooltip = { enabled: false }; }
    } else {
      opts.scales = { y: { beginAtZero: true } };
      if (s.yMax) { opts.scales.y.max = s.yMax; }
    }
    new Chart(el, { type: s.type, data: { labels: s.labels, datasets: datasets }, options: opts });
  });
})();
