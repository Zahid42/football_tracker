<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
$pageTitle = 'Calendar'; $activePage = 'calendar.php';
require __DIR__ . '/includes/header.php';
$events = [];
foreach (search_matches(match_filters([])) as $m) {
    $events[] = ['id' => (int)$m['id'], 'date' => $m['match_date'], 'title' => $m['team_name'] . ' ' . $m['team_score'] . '-' . $m['opponent_score'] . ' ' . $m['opponent_name'], 'result' => $m['result'], 'url' => purl('match-details.php?id=' . $m['id'])];
}
$ev = db()->prepare('SELECT * FROM calendar_events WHERE player_id IS NULL OR player_id = ? ORDER BY event_date, start_time');
$ev->execute([ctx_pid()]);
foreach ($ev->fetchAll() as $r) {
    $time = $r['start_time'] ? substr((string)$r['start_time'], 0, 5) . ($r['end_time'] ? '-' . substr((string)$r['end_time'], 0, 5) : '') : '';
    $events[] = ['id' => 0, 'date' => $r['event_date'], 'title' => ($r['start_time'] ? substr((string)$r['start_time'], 0, 5) . ' ' : '') . $r['title'], 'kind' => $r['event_type'], 'url' => null,
                 'tip' => $r['event_type'] . ': ' . $r['title'] . ($time ? ' · ' . $time : '') . ($r['location'] ? ' · ' . $r['location'] : '') . ($r['notes'] ? ' - ' . $r['notes'] : '')];
}
$today = date('Y-m-d');
$upRows = [];
$ev2 = db()->prepare('SELECT * FROM calendar_events WHERE event_date >= ? AND (player_id IS NULL OR player_id = ?) ORDER BY event_date, start_time');
$ev2->execute([$today, ctx_pid()]);
foreach ($ev2->fetchAll() as $r) {
    $time = $r['start_time'] ? substr((string)$r['start_time'], 0, 5) . ($r['end_time'] ? ' - ' . substr((string)$r['end_time'], 0, 5) : '') : '';
    $upRows[] = ['date' => $r['event_date'], 'time' => $time, 'title' => $r['title'], 'kind' => $r['event_type'] === 'Match' ? 'Fixture' : $r['event_type'], 'score' => '', 'result' => '', 'where' => (string)$r['location'], 'url' => '', 'notes' => (string)$r['notes']];
}
function cal_rows(array $list): void {
    $cur = '';
    foreach ($list as $r) {
        $mo = fmt_date($r['date'], 'F Y');
        if ($mo !== $cur) { $cur = $mo; echo '<div class="row-month">' . e($mo) . '</div>'; }
        $tag = $r['url'] ? 'a' : 'div';
        echo '<' . $tag . ' class="row-item"' . ($r['url'] ? ' href="' . e($r['url']) . '"' : '') . '>'
           . '<span class="ri-date"><b>' . e(fmt_date($r['date'], 'd')) . '</b><small>' . e(fmt_date($r['date'], 'D')) . '</small></span>'
           . '<span class="ri-main"><b>' . e($r['title']) . '</b><small>' . ($r['time'] ? '<i class="bi bi-clock"></i> ' . e($r['time']) . ' ' : '') . ($r['where'] ? '<i class="bi bi-geo-alt"></i> ' . e($r['where']) . ' ' : '') . (!empty($r['notes']) ? e($r['notes']) : '') . '</small></span>'
           . ($r['score'] !== '' ? '<span class="ri-score">' . e($r['score']) . '</span>' . result_badge($r['result']) : '<span class="ri-pill k-' . e(strtolower($r['kind'])) . '">' . e($r['kind']) . '</span>')
           . '</' . $tag . '>';
    }
}
echo public_page_title('Calendar', 'Matches played, upcoming fixtures, training and events');
?>
<div class="container py-4">
  <div class="ft-card p-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <button class="btn btn-outline-theme" id="calPrev" aria-label="Previous month"><i class="bi bi-chevron-left"></i></button>
      <h2 class="h4 mb-0 section-title" id="calTitle"></h2>
      <div class="d-flex gap-2"><button class="btn btn-outline-theme" id="calToday">Today</button><button class="btn btn-outline-theme" id="calNext" aria-label="Next month"><i class="bi bi-chevron-right"></i></button></div>
    </div>
    <div class="cal-grid" id="calGrid"></div>
    <div class="cal-legend"><span><i style="background:var(--win)"></i>Win</span><span><i style="background:var(--draw)"></i>Draw</span><span><i style="background:var(--loss)"></i>Loss</span><span><i style="background:#7c3aed"></i>Fixture</span><span><i style="background:#2563eb"></i>Training</span><span><i style="background:#0891b2"></i>Event</span><span><i style="background:#d97706"></i>Reminder</span></div>
  </div>
  <div class="section-head mt-4"><h2>Upcoming</h2></div>
  <?php if (!$upRows): ?><p class="text-muted-ft">Nothing scheduled yet.</p><?php else: ?><div class="row-list"><?php cal_rows($upRows); ?></div><?php endif; ?>
</div>
<?php $extraScripts = chart_scripts(['events' => $events], 'calendar.js'); require __DIR__ . '/includes/footer.php';
