<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
$admin = require_login();

$overall = get_overall_stats();
$season  = get_current_season();
$seasonStats = $season ? get_overall_stats((int)$season['id']) : null;
$recent  = get_recent_matches(5);
$chartData = get_chart_data($overall);

$pageTitle = 'Dashboard';
require __DIR__ . '/../includes/admin_header.php';

$cards = [
    ['Total Matches', $overall['matches'],                'bi-calendar-event', 'c-blue'],
    ['Wins',          $overall['wins'],                   'bi-emoji-smile',    'c-green'],
    ['Draws',         $overall['draws'],                  'bi-emoji-neutral',  'c-amber'],
    ['Losses',        $overall['losses'],                 'bi-emoji-frown',    'c-red'],
    ['Goals',         $overall['goals'],                  'bi-bullseye',       'c-green'],
    ['Assists',       $overall['assists'],                'bi-arrow-left-right','c-blue'],
    ['Average Rating', $overall['avg_rating'] !== null ? fmt_num($overall['avg_rating']) : '-', 'bi-star', 'c-amber'],
    ['Current Season', $season['name'] ?? '-',            'bi-calendar3',      'c-purple'],
];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div>
    <h1 class="page-title">Football Tracker Dashboard</h1>
    <p class="text-muted-ft mb-0">Welcome back, <?= e($admin['username']) ?>.</p>
  </div>
</div>

<!-- Stat cards -->
<div class="row g-3 mb-4">
  <?php foreach ($cards as [$label, $value, $icon, $color]): ?>
    <div class="col-6 col-md-4 col-xl-3">
      <div class="stat-card <?= e($color) ?>">
        <div class="stat-icon"><i class="bi <?= e($icon) ?>"></i></div>
        <div>
          <div class="stat-value"><?= e($value) ?></div>
          <div class="stat-label"><?= e($label) ?></div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Quick actions -->
<div class="ft-card p-3 mb-4">
  <h2 class="section-title h5 mb-3">Quick Actions</h2>
  <div class="d-flex flex-wrap gap-2">
    <?php
    $actions = [
        ['+ Add Match',          'admin/match-add.php'],
        ['+ Add Team',           'admin/teams.php?action=new'],
        ['+ Add Competition',    'admin/competitions.php?action=new'],
        ['+ Add Venue',          'admin/venues.php?action=new'],
        ['+ Add Player', 'admin/players.php?action=new'],
    ];
    foreach ($actions as [$label, $href]): ?>
      <a class="btn btn-outline-theme" href="<?= e(url($href)) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </div>
</div>

<div class="row g-3 mb-4">
  <!-- Charts -->
  <div class="col-lg-4">
    <div class="ft-card p-3 h-100">
      <h2 class="section-title h6">Results</h2>
      <div class="chart-box"><canvas id="chartResults"></canvas></div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="ft-card p-3 h-100">
      <h2 class="section-title h6">Goals &amp; Assists by Season</h2>
      <div class="chart-box"><canvas id="chartSeasons"></canvas></div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="ft-card p-3 h-100">
      <h2 class="section-title h6">Rating - Last 10 Matches</h2>
      <div class="chart-box"><canvas id="chartRating"></canvas></div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <!-- Recent matches -->
  <div class="col-xl-8">
    <div class="ft-card p-3 h-100">
      <h2 class="section-title h5 mb-3">Recent Matches</h2>
      <?php if (!$recent): ?>
        <p class="text-muted-ft mb-0">No matches yet.</p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table ft-table align-middle mb-0">
            <thead><tr><th>Date</th><th>Match</th><th>Score</th><th>Result</th><th class="text-end">G</th><th class="text-end">A</th><th class="text-end">Rating</th></tr></thead>
            <tbody>
              <?php foreach ($recent as $m): ?>
                <tr>
                  <td class="text-nowrap"><?= e(fmt_date($m['match_date'])) ?></td>
                  <td>
                    <strong><?= e($m['team_name']) ?></strong> vs <?= e($m['opponent_name']) ?>
                    <div class="small text-muted-ft"><?= e($m['competition_name'] ?? 'Friendly') ?></div>
                  </td>
                  <td class="fw-bold text-nowrap"><?= (int)$m['team_score'] ?> - <?= (int)$m['opponent_score'] ?></td>
                  <td><?= result_badge($m['result']) ?></td>
                  <td class="text-end"><?= (int)$m['goals'] ?></td>
                  <td class="text-end"><?= (int)$m['assists'] ?></td>
                  <td class="text-end"><?= e(fmt_num($m['rating'])) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Current season -->
  <div class="col-xl-4">
    <div class="ft-card p-3 h-100">
      <h2 class="section-title h5 mb-3">Current Season<?= $season ? ' - ' . e($season['name']) : '' ?></h2>
      <?php if (!$seasonStats): ?>
        <p class="text-muted-ft mb-0">No season created yet.</p>
      <?php else: ?>
        <ul class="list-unstyled season-list mb-0">
          <li><span>Matches</span><strong><?= $seasonStats['matches'] ?></strong></li>
          <li><span>Record (W-D-L)</span><strong><?= $seasonStats['wins'] ?>-<?= $seasonStats['draws'] ?>-<?= $seasonStats['losses'] ?></strong></li>
          <li><span>Win %</span><strong><?= e(fmt_num($seasonStats['win_pct'])) ?>%</strong></li>
          <li><span>Goals</span><strong><?= $seasonStats['goals'] ?></strong></li>
          <li><span>Assists</span><strong><?= $seasonStats['assists'] ?></strong></li>
          <li><span>Minutes</span><strong><?= $seasonStats['minutes'] ?></strong></li>
          <li><span>Avg rating</span><strong><?= e(fmt_num($seasonStats['avg_rating'])) ?></strong></li>
        </ul>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php
$json = json_encode($chartData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$extraScripts = '<script>window.DASH = ' . $json . ';</script>'
              . '<script src="' . e(url('assets/js/dashboard.js')) . '"></script>';
require __DIR__ . '/../includes/admin_footer.php';
