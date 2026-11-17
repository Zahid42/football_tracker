<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_login();
ft_comp_ensure();
$cid = admin_comp_id();
if (!$cid) { flash_set('warning', 'Create a competition first.'); redirect('admin/competitions.php'); }
$comp = comp_row($cid);

if (is_post()) {
    csrf_require();
    $act = (string)post('do');
    if ($act === 'generate') {
        $err = comp_ko_generate($cid);
        $err ? flash_set('danger', $err) : flash_set('success', 'Bracket generated from the table results.');
    } elseif ($act === 'clear') {
        db()->prepare('DELETE FROM comp_ko WHERE competition_id = ?')->execute([$cid]);
        flash_set('success', 'Bracket cleared.');
    } elseif ($act === 'save') {
        $n = fn($v) => ($v === '' || $v === null || !is_numeric($v)) ? null : max(0, min(99, (int)$v));
        $upd = db()->prepare('UPDATE comp_ko SET score_a = ?, score_b = ?, pen_a = ?, pen_b = ?, match_date = ? WHERE id = ? AND competition_id = ?');
        $err = null;
        foreach ((array)($_POST['t'] ?? []) as $id => $row) {
            $sa = $n($row['sa'] ?? null); $sb = $n($row['sb'] ?? null); $pa = $n($row['pa'] ?? null); $pb = $n($row['pb'] ?? null);
            if (($sa === null) !== ($sb === null)) { $err = 'Enter both scores of a tie, or leave both blank.'; continue; }
            if ($sa !== null && $sa === $sb && ($pa === null || $pb === null || $pa === $pb)) { $err = 'A drawn knockout tie needs a penalty shoot-out result with a winner.'; continue; }
            if ($sa !== $sb) { $pa = $pb = null; }
            $d = (string)($row['d'] ?? ''); $d = preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d : null;
            $upd->execute([$sa, $sb, $pa, $pb, $d, (int)$id, $cid]);
        }
        comp_ko_propagate($cid);
        $err ? flash_set('warning', 'Saved, but some ties were skipped: ' . $err) : flash_set('success', 'Scores saved. Winners moved to the next round.');
    }
    redirect('admin/comp-knockout.php');
}

$pageTitle = 'Knockout bracket';
require ROOT_PATH . '/includes/admin_header.php';
comp_admin_bar('comp-knockout.php');
$ko = comp_ko_rows($cid);
$q = comp_qualifiers($cid);
$isGroups = $comp['format'] === 'Groups';
?>
<h1 class="page-title h3 mb-1"><i class="bi bi-diagram-3"></i> Knockout bracket</h1>
<p class="text-muted-ft">The bracket is built from the <?= $isGroups ? 'group stage: the top ' . (int)$comp['qualify_per_group'] . ' of each group qualify (A1 v B2, B1 v A2 ...)' : 'league table: the top ' . (int)$comp['ko_teams'] . ' teams qualify (1 plays the lowest seed)' ?>. Suspended teams never qualify. Winners move on automatically.</p>
<?php if (!$comp['knockout']): ?>
  <div class="alert alert-warning">This competition has no knockout stage yet. Enable "Has a knockout stage" in <a href="<?= e(url('admin/competitions.php?action=edit&id=' . $cid)) ?>">Rules</a>.</div>
<?php else: ?>
<div class="ft-card p-3 mb-3">
  <div class="fw-semibold mb-2">Qualified now (<?= count($q) ?>)</div>
  <div class="d-flex flex-wrap gap-2 mb-3"><?php foreach ($q as $x): ?><span class="badge text-bg-secondary"><?= e(($x['group'] ? $x['group'] . ' #' : '#') . $x['rank'] . ' ' . $x['team']) ?></span><?php endforeach; if (!$q): ?><span class="text-muted-ft small">No teams in the table yet.</span><?php endif; ?></div>
  <form method="post" class="d-flex flex-wrap gap-2"><?= csrf_field() ?>
    <button class="btn btn-accent" name="do" value="generate" <?= $ko ? 'onclick="return confirm(\'Regenerate the bracket? Existing knockout scores will be lost.\')"' : '' ?>><i class="bi bi-magic"></i> <?= $ko ? 'Regenerate' : 'Generate' ?> bracket from <?= $isGroups ? 'group' : 'league' ?> results</button>
    <?php if ($ko): ?><button class="btn btn-outline-danger" name="do" value="clear" onclick="return confirm('Delete the whole bracket?')"><i class="bi bi-trash"></i> Clear</button><?php endif; ?>
  </form>
</div>
<?php if ($ko): $rounds = count($ko); ?>
<form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="save">
<?php foreach ($ko as $r => $ties): ?>
  <h2 class="h6 section-title mt-3"><?= e(comp_ko_round_name((int)$r, $rounds)) ?></h2>
  <div class="row g-2">
  <?php foreach ($ties as $t): $ready = $t['team_a'] && $t['team_b'] && $t['team_a'] !== 'BYE' && $t['team_b'] !== 'BYE'; ?>
    <div class="col-12 col-lg-6"><div class="ft-card p-2 px-3">
      <div class="d-flex justify-content-between small fw-semibold"><span><?= e($t['team_a'] ?? 'TBD') ?></span><span class="text-muted-ft">vs</span><span><?= e($t['team_b'] ?? 'TBD') ?></span></div>
      <?php if ($ready): ?>
      <div class="d-flex flex-wrap gap-2 align-items-center mt-2">
        <input class="form-control form-control-sm" style="width:64px" type="number" min="0" max="99" name="t[<?= (int)$t['id'] ?>][sa]" value="<?= e((string)$t['score_a']) ?>" aria-label="Score <?= e($t['team_a']) ?>">
        <span>-</span>
        <input class="form-control form-control-sm" style="width:64px" type="number" min="0" max="99" name="t[<?= (int)$t['id'] ?>][sb]" value="<?= e((string)$t['score_b']) ?>" aria-label="Score <?= e($t['team_b']) ?>">
        <span class="small text-muted-ft ms-2">Pens</span>
        <input class="form-control form-control-sm" style="width:58px" type="number" min="0" max="99" name="t[<?= (int)$t['id'] ?>][pa]" value="<?= e((string)$t['pen_a']) ?>" aria-label="Penalties <?= e($t['team_a']) ?>">
        <span>-</span>
        <input class="form-control form-control-sm" style="width:58px" type="number" min="0" max="99" name="t[<?= (int)$t['id'] ?>][pb]" value="<?= e((string)$t['pen_b']) ?>" aria-label="Penalties <?= e($t['team_b']) ?>">
        <input class="form-control form-control-sm ms-auto" style="width:150px" type="date" name="t[<?= (int)$t['id'] ?>][d]" value="<?= e((string)$t['match_date']) ?>" aria-label="Date">
      </div>
      <?php else: ?><div class="small text-muted-ft mt-1"><?= ($t['team_a'] === 'BYE' || $t['team_b'] === 'BYE') ? 'Bye: goes straight through.' : 'Waiting for the previous round.' ?></div><?php endif; ?>
    </div></div>
  <?php endforeach; ?>
  </div>
<?php endforeach; ?>
  <button class="btn btn-accent mt-3"><i class="bi bi-check-lg"></i> Save scores</button>
</form>
<h2 class="h6 section-title mt-4">Preview</h2>
<?= comp_bracket_html($cid) ?>
<?php endif; endif; ?>
<?php require ROOT_PATH . '/includes/admin_footer.php';
