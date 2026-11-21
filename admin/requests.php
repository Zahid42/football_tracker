<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_login();
$self = 'requests.php';
$view = in_array(get_str('view') ?: post('view'), ['pending', 'approved', 'rejected', 'all'], true) ? (get_str('view') ?: post('view')) : 'pending';

if (is_post()) {
    csrf_require();
    $op = post('op');
    if ($op === 'bulk_delete') {   // clear answered requests from the history
        $n = 0; $del = db()->prepare("DELETE FROM member_requests WHERE id = ? AND status <> 'pending'");
        foreach (array_unique(array_map('intval', (array)($_POST['ids'] ?? []))) as $id) { $del->execute([$id]); $n += $del->rowCount(); }
        flash_set($n ? 'success' : 'info', $n ? $n . ' request' . ($n === 1 ? '' : 's') . ' removed from the history.' : 'Tick answered requests to remove them (waiting ones stay).');
        redirect('admin/' . $self . '?view=' . $view);
    }
    $q = db()->prepare("SELECT * FROM member_requests WHERE id = ? AND status = 'pending'"); $q->execute([(int)post('id')]); $r = $q->fetch();
    if (!$r) { flash_set('warning', 'That request was already answered.'); redirect('admin/' . $self); }
    $reply = mb_substr(trim(post('reply')), 0, 500);
    if ($op === 'approve') {
        $dupe = null;
        if ($r['kind'] === 'team') { $x = db()->prepare('SELECT COUNT(*) FROM teams WHERE LOWER(name) = LOWER(?)'); $x->execute([$r['title']]); $dupe = (int)$x->fetchColumn(); }
        elseif ($r['kind'] === 'competition') { $x = db()->prepare('SELECT COUNT(*) FROM competitions WHERE LOWER(name) = LOWER(?)'); $x->execute([$r['title']]); $dupe = (int)$x->fetchColumn(); }
        else { $x = db()->prepare("SELECT COUNT(*) FROM players WHERE approval_status = 'approved' AND LOWER(TRIM(full_name)) = ?"); $x->execute([ft_name_key($r['title'])]); $dupe = (int)$x->fetchColumn(); }
        if ($dupe) { flash_set('danger', '"' . $r['title'] . '" already exists on the site, so nothing was created. Reject the request with a short reply instead.'); redirect('admin/' . $self); }
        try { [$rid, $code, $msg] = request_apply($r); }
        catch (Throwable $ex) { flash_set('danger', 'Could not create it: ' . $ex->getMessage()); redirect('admin/' . $self); }
        db()->prepare("UPDATE member_requests SET status = 'approved', admin_reply = ?, result_id = ?, decided_at = NOW() WHERE id = ?")->execute([$reply !== '' ? $reply : null, $rid, $r['id']]);
        $r['admin_reply'] = $reply; request_notify($r, 'approved', $code);
        flash_set('success', $msg . ' The member was informed (ID ' . $code . ').');
    } elseif ($op === 'reject') {
        db()->prepare("UPDATE member_requests SET status = 'rejected', admin_reply = ?, decided_at = NOW() WHERE id = ?")->execute([$reply !== '' ? $reply : null, $r['id']]);
        $r['admin_reply'] = $reply; request_notify($r, 'rejected');
        flash_set('success', 'Request rejected and the member was informed.');
    }
    redirect('admin/' . $self . '?view=' . $view);
}

$where = $view === 'all' ? '1' : 'r.status = ' . db()->quote($view);
$rows = db()->query("SELECT r.*, m.email, p.full_name AS member_name, p.public_code AS member_code FROM member_requests r LEFT JOIN members m ON m.id = r.member_id LEFT JOIN players p ON p.id = m.player_id WHERE $where ORDER BY (r.status = 'pending') DESC, r.id DESC LIMIT 200")->fetchAll();
$counts = db()->query('SELECT status, COUNT(*) c FROM member_requests GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
$teams = lookup('SELECT id, name FROM teams'); $seasons = lookup('SELECT id, name FROM seasons');
$pageTitle = 'Requests';
require ROOT_PATH . '/includes/admin_header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <h1 class="page-title h3 mb-0"><i class="bi bi-inbox"></i> Requests</h1>
  <div class="btn-group btn-group-sm"><?php foreach (['pending' => 'Waiting', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $k => $lab): ?>
    <a class="btn btn-<?= $view === $k ? 'accent' : 'outline-theme' ?>" href="<?= e(url('admin/requests.php?view=' . $k)) ?>"><?= e($lab) ?><?= $k !== 'all' && !empty($counts[$k]) ? ' (' . (int)$counts[$k] . ')' : '' ?></a><?php endforeach; ?></div>
</div>
<p class="text-muted-ft">Signed-up members can ask for a new player, team or competition. Approving creates it at once and e-mails the member its ID; rejecting sends your reply.</p>
<?= flash_render() ?>
<?php if (!$rows): ?><div class="ft-card p-4 text-center text-muted-ft">Nothing here.</div><?php else:
  $answered = (bool)array_filter($rows, fn($r) => $r['status'] !== 'pending'); ?>
<?php if ($answered): ?><form id="bulkForm" method="post" class="bulk-bar d-flex flex-wrap align-items-center gap-2 mb-2" data-confirm="Remove the ticked answered requests from the history?"><?= csrf_field() ?><input type="hidden" name="op" value="bulk_delete">
  <label class="form-check mb-0 d-flex align-items-center gap-2"><input class="form-check-input mt-0" type="checkbox" id="bulkAll"> <span class="small">Select all</span></label><span class="small text-muted-ft"><b id="bulkCount">0</b> selected</span>
  <button class="btn btn-sm btn-outline-danger" id="bulkDel" disabled><i class="bi bi-trash"></i> Remove selected</button></form><?php endif; ?>
<div class="row g-3">
<?php foreach ($rows as $r): $d = request_details($r); ?>
  <div class="col-12"><div class="ft-card p-3">
    <div class="d-flex flex-wrap align-items-start gap-2">
      <?php if ($r['status'] !== 'pending'): ?><input class="form-check-input bulk-cb mt-1" type="checkbox" name="ids[]" value="<?= (int)$r['id'] ?>" form="bulkForm" aria-label="Select"><?php endif; ?>
      <div class="flex-grow-1">
        <div class="fw-bold fs-6"><span class="badge text-bg-secondary"><?= e(MEMBER_REQUEST_KINDS[$r['kind']] ?? $r['kind']) ?></span> <?= e($r['title']) ?></div>
        <div class="small text-muted-ft">From <a href="<?= e(url('admin/members.php?id=' . (int)$r['member_id'])) ?>"><?= e($r['member_name'] ?: ($r['email'] ?: 'a deleted member')) ?></a><?= $r['member_code'] ? ' (ID ' . e($r['member_code']) . ')' : '' ?> &middot; <?= e(fmt_date(substr((string)$r['created_at'], 0, 10))) ?></div>
        <div class="small mt-1"><?php
          $bits = [];
          if ($r['kind'] === 'player') { if (!empty($d['position'])) { $bits[] = 'Position: ' . position_name($d['position']); } if (($d['jersey'] ?? '') !== '') { $bits[] = 'Jersey #' . (int)$d['jersey']; } if (!empty($d['dob'])) { $bits[] = 'Born ' . fmt_date($d['dob']); } if (!empty($d['team_id'])) { $bits[] = 'Team: ' . ($teams[(int)$d['team_id']] ?? '?'); } }
          elseif ($r['kind'] === 'team') { if (!empty($d['short_name'])) { $bits[] = 'Short name: ' . $d['short_name']; } if (!empty($d['founded'])) { $bits[] = 'Founded ' . $d['founded']; } }
          else { $bits[] = ($d['type'] ?? 'Tournament') . ', ' . ($d['format'] ?? 'League') . ' table'; if (!empty($d['season_id'])) { $bits[] = 'Season: ' . ($seasons[(int)$d['season_id']] ?? '?'); } if (!empty($d['organizer'])) { $bits[] = 'Organizer: ' . $d['organizer']; } }
          echo e(implode(' · ', $bits)); ?></div>
        <?php if (!empty($d['note'])): ?><div class="small mt-1 fst-italic">"<?= e($d['note']) ?>"</div><?php endif; ?>
      </div>
      <?php if ($r['status'] === 'approved'): ?><span class="badge bg-success">Approved</span><?php elseif ($r['status'] === 'rejected'): ?><span class="badge bg-danger">Rejected</span><?php endif; ?>
    </div>
    <?php if ($r['status'] === 'pending'): ?>
    <form method="post" class="d-flex flex-wrap gap-2 align-items-center mt-3"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="view" value="<?= e($view) ?>">
      <input class="form-control form-control-sm flex-grow-1" style="min-width:200px" name="reply" maxlength="500" placeholder="Message to the member (optional)">
      <button class="btn btn-sm btn-accent" name="op" value="approve"><i class="bi bi-check-lg"></i> Approve and create</button>
      <button class="btn btn-sm btn-outline-danger" name="op" value="reject" onclick="return confirm('Reject this request?')"><i class="bi bi-x-lg"></i> Reject</button>
    </form>
    <?php elseif ($r['admin_reply']): ?><div class="small mt-2 text-muted-ft">Your reply: <?= e($r['admin_reply']) ?></div><?php endif; ?>
  </div></div>
<?php endforeach; ?></div>
<?php if ($answered) { echo bulk_select_script(); } ?>
<?php endif;
require ROOT_PATH . '/includes/admin_footer.php';
