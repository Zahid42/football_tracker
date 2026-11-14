<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
$pageTitle = 'Calendar'; $activePage = 'calendar.php';
require __DIR__ . '/includes/header.php';
$today = date('Y-m-d');
$rows = [];
foreach (search_matches(match_filters([])) as $m) {
    if ($m['match_date'] > $today) { continue; }
    $rows[] = ['date' => $m['match_date'], 'time' => '', 'title' => $m['team_name'] . ' vs ' . $m['opponent_name'], 'kind' => 'Played', 'score' => (int)$m['team_score'] . ' - ' . (int)$m['opponent_score'], 'result' => $m['result'], 'where' => '', 'url' => purl('match-details.php?id=' . $m['id'])];
}
$ev = db()->prepare('SELECT * FROM calendar_events WHERE player_id IS NULL OR player_id = ?');
$ev->execute([ctx_pid()]);
foreach ($ev->fetchAll() as $r) {
    $time = $r['start_time'] ? substr((string)$r['start_time'], 0, 5) . ($r['end_time'] ? ' - ' . substr((string)$r['end_time'], 0, 5) : '') : '';
    $rows[] = ['date' => $r['event_date'], 'time' => $time, 'title' => $r['title'], 'kind' => $r['event_type'] === 'Match' ? 'Fixture' : $r['event_type'], 'score' => '', 'result' => '', 'where' => (string)$r['location'], 'url' => '', 'notes' => (string)$r['notes']];
}
$up = array_filter($rows, fn($r) => $r['date'] >= $today); usort($up, fn($a, $b) => [$a['date'], $a['time']] <=> [$b['date'], $b['time']]);
$past = array_filter($rows, fn($r) => $r['date'] < $today); usort($past, fn($a, $b) => [$b['date'], $b['time']] <=> [$a['date'], $a['time']]);
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
echo public_page_title('Calendar', 'Upcoming fixtures, training and events, then past matches');
?>
<div class="container py-4">
  <div class="section-head"><h2>Upcoming</h2></div>
  <?php if (!$up): ?><p class="text-muted-ft">Nothing scheduled yet.</p><?php else: ?><div class="row-list"><?php cal_rows($up); ?></div><?php endif; ?>
  <div class="section-head mt-5"><h2>Past</h2></div>
  <?php if (!$past): ?><p class="text-muted-ft">No past entries.</p><?php else: ?><div class="row-list"><?php cal_rows($past); ?></div><?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php';
