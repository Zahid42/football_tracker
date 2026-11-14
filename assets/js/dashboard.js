/* Admin dashboard charts. Data comes from window.DASH (set in admin/index.php). */
(function () {
  'use strict';
  if (!window.Chart || !window.DASH) { return; }
  FT.initChartDefaults();

  var green = FT.css('--win'), amber = FT.css('--draw'), red = FT.css('--loss'), blue = FT.css('--blue');
  var D = window.DASH;

  // Results doughnut
  var total = D.results.reduce(function (a, b) { return a + b; }, 0);
  new Chart(document.getElementById('chartResults'), {
    type: 'doughnut',
    data: {
      labels: ['Wins', 'Draws', 'Losses'],
      datasets: [{ data: total ? D.results : [1], backgroundColor: total ? [green, amber, red] : [FT.css('--card-border')], borderWidth: 0 }]
    },
    options: { cutout: '65%', plugins: { legend: { position: 'bottom' }, tooltip: { enabled: total > 0 } } }
  });

  // Goals & assists by season
  new Chart(document.getElementById('chartSeasons'), {
    type: 'bar',
    data: {
      labels: D.seasons.labels,
      datasets: [
        { label: 'Goals', data: D.seasons.goals, backgroundColor: green, borderRadius: 6 },
        { label: 'Assists', data: D.seasons.assists, backgroundColor: blue, borderRadius: 6 }
      ]
    },
    options: { scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }, plugins: { legend: { position: 'bottom' } } }
  });

  // Rating line
  new Chart(document.getElementById('chartRating'), {
    type: 'line',
    data: {
      labels: D.rating.labels,
      datasets: [{ label: 'Rating', data: D.rating.values, borderColor: amber, backgroundColor: amber, tension: .3, spanGaps: true, pointRadius: 4 }]
    },
    options: { scales: { y: { min: 0, max: 10 } }, plugins: { legend: { display: false } } }
  });
})();
