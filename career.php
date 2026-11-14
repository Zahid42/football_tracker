<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
$pageTitle = 'Career'; $activePage = 'career.php';
require __DIR__ . '/includes/header.php';
$p = get_profile(); $teams = get_team_history(); $years = get_grouped_stats('year'); $comps = get_grouped_stats('competition'); $opps = get_grouped_stats('opponent');
$first = db()->query('SELECT MIN(match_date) FROM matches')->fetchColumn(); $last = db()->query('SELECT MAX(match_date) FROM matches')->fetchColumn();
$firstGoal = db()->query('SELECT m.match_date, o.name FROM goals g JOIN matches m ON m.id=g.match_id JOIN opponents o ON o.id=m.opponent_id ORDER BY m.match_date, g.minute LIMIT 1')->fetch();
$bestMatch = db()->query('SELECT m.id, m.match_date, o.name, s.rating FROM matches m JOIN opponents o ON o.id=m.opponent_id JOIN match_statistics s ON s.match_id=m.id WHERE s.rating IS NOT NULL ORDER BY s.rating DESC, m.match_date DESC LIMIT 1')->fetch();
echo public_page_title('Career', 'Teams, seasons and milestones');
?>
<div class="container py-4">
  <div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><?= stat_card('First recorded match', $first ? fmt_date($first, 'd M Y') : '-', 'bi-flag', 'c-blue') ?></div>
    <div class="col-6 col-lg-3"><?= stat_card('Latest match', $last ? fmt_date($last, 'd M Y') : '-', 'bi-calendar-check', 'c-green') ?></div>
    <div class="col-6 col-lg-3"><?= stat_card('First goal', $firstGoal ? fmt_date($firstGoal['match_date'], 'd M Y') : '-', 'bi-bullseye', 'c-amber') ?></div>
    <div class="col-6 col-lg-3"><?= stat_card('Best rating', $bestMatch ? fmt_num($bestMatch['rating']) . ' v ' . $bestMatch['name'] : '-', 'bi-star-fill', 'c-purple') ?></div>
  </div>
  <h2 class="section-title h4 mb-3">Teams</h2>
  <div class="timeline mb-5"><?php foreach ($teams as $t) { echo team_timeline_item($t); } ?></div>
  <h2 class="section-title h4 mb-3">Season by season</h2><div class="ft-card p-3 mb-4"><?= render_stats_table($years, 'Season') ?></div>
  <h2 class="section-title h4 mb-3">By competition</h2><div class="ft-card p-3 mb-4"><?= render_stats_table($comps, 'Competition') ?></div>
  <h2 class="section-title h4 mb-3">Against opponents</h2><div class="ft-card p-3"><?= render_stats_table($opps, 'Opponent') ?></div>
</div>
<?php require __DIR__ . '/includes/footer.php';
