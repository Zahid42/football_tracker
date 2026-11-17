<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_login();
ft_comp_ensure(); ft_auction_ensure();
$cid = admin_comp_id();
if (!$cid) { flash_set('warning', 'Create a competition first.'); redirect('admin/competitions.php'); }
$comp = comp_row($cid);
$a = auc_get($cid);

if (is_post()) {
    csrf_require();
    $op = post('op');
    if ($op === 'setup') {
        $budget = max(1, (int)post('budget')); $size = max(1, min(30, (int)post('squad_size'))); $step = max(1, (int)post('bid_step')); $min = max(1, (int)post('min_price'));
        if ($min * $size > $budget) { flash_set('danger', 'Budget is too small: ' . $size . ' players at the minimum price of ' . $min . ' needs ' . ($min * $size) . '.'); redirect('admin/comp-auction.php'); }
        if ($a) {
            db()->prepare('UPDATE auctions SET budget = ?, squad_size = ?, bid_step = ?, min_price = ? WHERE id = ?')->execute([$budget, $size, $step, $min, $a['id']]);
        } else {
            db()->prepare('INSERT INTO auctions (competition_id, budget, squad_size, bid_step, min_price) VALUES (?,?,?,?,?)')->execute([$cid, $budget, $size, $step, $min]);
        }
        flash_set('success', 'Auction rules saved.');
    } elseif ($a && $op === 'addpool') {
        $base = max(1, (int)post('base_price')) ?: (int)$a['min_price'];
        $ids = array_map('intval', (array)($_POST['players'] ?? []));
        $ins = db()->prepare('INSERT IGNORE INTO auction_pool (auction_id, player_id, category, base_price) SELECT ?, id, preferred_position, ? FROM players WHERE id = ?');
        $n = 0; foreach ($ids as $pid) { if ($pid) { $ins->execute([$a['id'], max($base, (int)$a['min_price']), $pid]); $n += $ins->rowCount(); } }
        flash_set($n ? 'success' : 'warning', $n . ' player(s) added to the auction pool.');
    } elseif ($a && $op === 'baseprice') {
        db()->prepare("UPDATE auction_pool SET base_price = ? WHERE id = ? AND auction_id = ? AND status = 'pending'")->execute([max((int)$a['min_price'], (int)post('base_price')), (int)post('pool'), $a['id']]);
        flash_set('success', 'Base price updated.');
    } elseif ($a) {
        [$t, $m] = auc_action($a, $op, $_POST);
        flash_set($t, $m);
    }
    redirect('admin/comp-auction.php');
}

$pageTitle = 'Player auction';
require ROOT_PATH . '/includes/admin_header.php';
comp_admin_bar('comp-auction.php');
?>
<h1 class="page-title h3 mb-1"><i class="bi bi-hammer"></i> Player auction</h1>
<p class="text-muted-ft">Each team gets the same budget to buy a fixed number of players. Players are grouped by playing position. When you accept a bid the player moves to that team (and into its squad) at the price he was sold for.</p>

<form method="post" class="ft-card p-3 mb-3"><?= csrf_field() ?><input type="hidden" name="op" value="setup">
  <div class="fw-semibold mb-2">Rules</div>
  <div class="row g-2">
    <div class="col-6 col-md-3"><label class="form-label small">Budget per team</label><input class="form-control" type="number" min="1" name="budget" required value="<?= (int)($a['budget'] ?? 1000) ?>"></div>
    <div class="col-6 col-md-3"><label class="form-label small">Players each team buys</label><input class="form-control" type="number" min="1" max="30" name="squad_size" required value="<?= (int)($a['squad_size'] ?? 5) ?>"></div>
    <div class="col-6 col-md-3"><label class="form-label small">Minimum price</label><input class="form-control" type="number" min="1" name="min_price" required value="<?= (int)($a['min_price'] ?? 10) ?>"></div>
    <div class="col-6 col-md-3"><label class="form-label small">Bid step (raise by)</label><input class="form-control" type="number" min="1" name="bid_step" required value="<?= (int)($a['bid_step'] ?? 10) ?>"></div>
  </div>
  <div class="form-text">A team must always keep enough money to still buy its remaining players at the minimum price.</div>
  <button class="btn btn-accent mt-2"><i class="bi bi-check-lg"></i> <?= $a ? 'Save rules' : 'Create auction' ?></button>
</form>
<?php if (!$a): require ROOT_PATH . '/includes/admin_footer.php'; exit; endif;
$teams = auc_teams($a); $pool = auc_pool((int)$a['id']);
$cur = null; foreach ($pool as $r) { if ((int)$r['id'] === (int)$a['current_pool_id']) { $cur = $r; } }
$total = count($pool); $need = count($teams) * (int)$a['squad_size']; $pend = count(array_filter($pool, fn($r) => $r['status'] === 'pending'));
$by = []; foreach ($pool as $r) { $by[$r['category'] ?: 'Other'][] = $r; }
$badge = ['setup' => 'secondary', 'live' => 'success', 'closed' => 'dark'][$a['status']];
?>
<div class="ft-card p-3 mb-3 d-flex flex-wrap align-items-center gap-3">
  <div>Status: <span class="badge text-bg-<?= $badge ?> text-uppercase"><?= e($a['status']) ?></span></div>
  <div class="small text-muted-ft"><?= count($teams) ?> teams &times; <?= (int)$a['squad_size'] ?> players = <b><?= $need ?></b> needed &middot; <b><?= $total ?></b> in pool (<?= $pend ?> waiting)<?= $total < $need ? ' <span class="text-warning">- add at least ' . ($need - $total) . ' more</span>' : '' ?></div>
  <form method="post" class="ms-auto d-flex gap-2"><?= csrf_field() ?><input type="hidden" name="op" value="status">
    <?php if ($a['status'] !== 'live'): ?><button class="btn btn-accent btn-sm" name="to" value="live"><i class="bi bi-play-fill"></i> <?= $a['status'] === 'closed' ? 'Re-open' : 'Start auction' ?></button><?php endif; ?>
    <?php if ($a['status'] === 'live'): ?><button class="btn btn-outline-theme btn-sm" name="to" value="closed" onclick="return confirm('Close the auction?')"><i class="bi bi-stop-fill"></i> Close</button><?php endif; ?>
  </form>
</div>

<?php if ($a['status'] === 'live'): ?>
<div class="ft-card p-3 mb-3" id="live">
  <div class="fw-semibold mb-2"><i class="bi bi-megaphone"></i> Live bidding</div>
  <?php if ($cur): ?>
    <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
      <img src="<?= e(upload_url($cur['photo'])) ?>" alt="" style="width:72px;height:72px;border-radius:14px;object-fit:cover">
      <div><div class="h5 mb-0"><?= e($cur['full_name']) ?></div><div class="text-muted-ft small"><?= e(position_name($cur['category'])) ?> &middot; base <?= (int)$cur['base_price'] ?></div></div>
      <div class="ms-auto text-end"><div class="small text-muted-ft">Current bid</div><div class="display-6 fw-bold"><?= $a['current_team'] !== null ? (int)$a['current_bid'] : '-' ?></div><div class="small"><?= $a['current_team'] !== null ? e($a['current_team']) : 'No bids yet' ?></div></div>
    </div>
    <div class="row g-2 mb-3">
    <?php foreach ($teams as $t): $next = $a['current_team'] === null ? (int)$cur['base_price'] : (int)$a['current_bid'] + (int)$a['bid_step']; $can = $t['slots'] > 0 && $next <= $t['max_bid'] && $t['name'] !== $a['current_team']; ?>
      <div class="col-6 col-md-4 col-lg-3"><form method="post" class="border rounded p-2 h-100" style="border-color:var(--card-border)!important"><?= csrf_field() ?><input type="hidden" name="op" value="bid"><input type="hidden" name="team" value="<?= e($t['name']) ?>">
        <div class="fw-semibold small"><?= e($t['name']) ?></div>
        <div class="small text-muted-ft mb-1">Left <?= (int)$t['left'] ?> &middot; <?= (int)$t['count'] ?>/<?= (int)$a['squad_size'] ?> players</div>
        <div class="input-group input-group-sm"><input class="form-control" type="number" name="amount" min="1" placeholder="<?= $next ?>" aria-label="Bid amount"><button class="btn btn-accent" <?= $can || $t['slots'] > 0 ? '' : 'disabled' ?>>Bid</button></div>
      </form></div>
    <?php endforeach; ?>
    </div>
    <form method="post" class="d-flex flex-wrap gap-2"><?= csrf_field() ?>
      <button class="btn btn-success" name="op" value="sold" <?= $a['current_team'] === null ? 'disabled' : '' ?>><i class="bi bi-check2-circle"></i> SOLD<?= $a['current_team'] !== null ? ' to ' . e($a['current_team']) . ' for ' . (int)$a['current_bid'] : '' ?></button>
      <button class="btn btn-outline-danger" name="op" value="unsold"><i class="bi bi-x-circle"></i> Unsold</button>
    </form>
    <div class="form-text">Leave the amount blank to bid the next step; type an amount for a bigger jump.</div>
  <?php else: ?>
    <form method="post" class="row g-2 align-items-end"><?= csrf_field() ?><input type="hidden" name="op" value="block">
      <div class="col-md-6"><label class="form-label small">Next player up for bidding</label>
        <select class="form-select" name="pool" required><option value="">- choose -</option>
        <?php foreach ($by as $cat => $rows): $w = array_filter($rows, fn($r) => $r['status'] === 'pending'); if (!$w) { continue; } ?>
          <optgroup label="<?= e(position_name($cat)) ?>"><?php foreach ($w as $r): ?><option value="<?= (int)$r['id'] ?>"><?= e($r['full_name']) ?> (base <?= (int)$r['base_price'] ?>)</option><?php endforeach; ?></optgroup>
        <?php endforeach; ?></select></div>
      <div class="col-auto"><button class="btn btn-accent"><i class="bi bi-hammer"></i> Put up for bidding</button></div>
    </form>
    <?php if (!$pend): ?><div class="text-muted-ft small mt-2">No players are waiting in the pool.</div><?php endif; ?>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="row g-3 mb-3">
  <div class="col-lg-7">
    <h2 class="h6 section-title">Teams &amp; budgets</h2>
    <div class="table-responsive"><table class="table ft-table align-middle"><thead><tr><th>Team</th><th class="text-end">Spent</th><th class="text-end">Left</th><th class="text-center">Players</th></tr></thead><tbody>
    <?php foreach ($teams as $t): ?><tr><td class="fw-semibold"><?= e($t['name']) ?></td><td class="text-end"><?= (int)$t['spent'] ?></td><td class="text-end"><?= (int)$t['left'] ?></td><td class="text-center"><?= (int)$t['count'] ?>/<?= (int)$a['squad_size'] ?></td></tr>
      <?php if ($t['players']): ?><tr><td colspan="4" class="small text-muted-ft"><?php foreach ($t['players'] as $r): ?><span class="me-3"><?= e($r['full_name']) ?> <b><?= (int)$r['sold_price'] ?></b></span><?php endforeach; ?></td></tr><?php endif; ?>
    <?php endforeach; if (!$teams): ?><tr><td colspan="4" class="text-muted-ft">Add teams in the Teams &amp; groups tab first.</td></tr><?php endif; ?></tbody></table></div>
  </div>
  <div class="col-lg-5">
    <h2 class="h6 section-title">Add players to the pool</h2>
    <form method="post" class="ft-card p-3"><?= csrf_field() ?><input type="hidden" name="op" value="addpool">
      <?php $in = array_column($pool, 'player_id'); $allp = db()->query("SELECT id, full_name, preferred_position FROM players WHERE is_active = 1 AND approval_status = 'approved' ORDER BY FIELD(preferred_position,'GK','CB','LB','RB','CDM','CM','CAM','LM','RM','LW','RW','ST','CF'), full_name")->fetchAll(); ?>
      <div style="max-height:240px;overflow:auto" class="mb-2">
      <?php $lastc = null; foreach ($allp as $p): if (in_array((int)$p['id'], array_map('intval', $in), true)) { continue; } if ($lastc !== $p['preferred_position']) { $lastc = $p['preferred_position']; echo '<div class="small fw-bold text-muted-ft mt-2">' . e(position_name($lastc)) . '</div>'; } ?>
        <label class="d-block small"><input class="form-check-input me-1" type="checkbox" name="players[]" value="<?= (int)$p['id'] ?>"> <?= e($p['full_name']) ?></label>
      <?php endforeach; if ($lastc === null): ?><div class="small text-muted-ft">All active players are already in the pool.</div><?php endif; ?></div>
      <div class="input-group input-group-sm"><span class="input-group-text">Base price</span><input class="form-control" type="number" min="1" name="base_price" value="<?= (int)$a['min_price'] ?>"><button class="btn btn-accent">Add selected</button></div>
    </form>
  </div>
</div>

<h2 class="h6 section-title">Pool by position</h2>
<?php if (!$pool): ?><div class="ft-card p-3 text-muted-ft">No players in the pool yet.</div><?php endif; ?>
<?php foreach ($by as $cat => $rows): ?>
  <div class="ft-card p-3 mb-2"><div class="fw-semibold mb-2"><?= e(position_name($cat)) ?> <span class="badge text-bg-secondary"><?= count($rows) ?></span></div>
  <?php foreach ($rows as $r): ?>
    <div class="d-flex flex-wrap align-items-center gap-2 py-1 border-top" style="border-color:var(--card-border)!important">
      <img src="<?= e(upload_url($r['photo'])) ?>" alt="" style="width:30px;height:30px;border-radius:8px;object-fit:cover">
      <span class="fw-semibold"><?= e($r['full_name']) ?></span>
      <?php if ($r['status'] === 'sold'): ?><span class="badge text-bg-success">Sold to <?= e($r['sold_team']) ?> for <?= (int)$r['sold_price'] ?></span>
      <?php elseif ($r['status'] === 'unsold'): ?><span class="badge text-bg-danger">Unsold</span>
      <?php else: ?><form method="post" class="d-inline-flex gap-1 ms-1"><?= csrf_field() ?><input type="hidden" name="op" value="baseprice"><input type="hidden" name="pool" value="<?= (int)$r['id'] ?>"><span class="small text-muted-ft align-self-center">base</span><input class="form-control form-control-sm" style="width:80px" type="number" name="base_price" value="<?= (int)$r['base_price'] ?>"><button class="btn btn-sm btn-outline-theme" title="Save base price"><i class="bi bi-check"></i></button></form><?php endif; ?>
      <form method="post" class="ms-auto d-inline-flex gap-1"><?= csrf_field() ?><input type="hidden" name="pool" value="<?= (int)$r['id'] ?>">
        <?php if ($r['status'] !== 'pending'): ?><button class="btn btn-sm btn-outline-theme" name="op" value="return" title="Back to pool"><i class="bi bi-arrow-counterclockwise"></i></button><?php endif; ?>
        <button class="btn btn-sm btn-outline-danger" name="op" value="remove" onclick="return confirm('Remove from the auction?')" title="Remove"><i class="bi bi-trash"></i></button></form>
    </div>
  <?php endforeach; ?></div>
<?php endforeach;
require ROOT_PATH . '/includes/admin_footer.php';
