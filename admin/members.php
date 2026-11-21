<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_login();
ft_members_ensure();
$self = 'members.php';
$id = (int)get_str('id');
$views = ['all' => 'All', 'new' => 'New (14 days)', 'unverified' => 'Not verified', 'verified' => 'Verified', 'blocked' => 'Blocked', 'never' => 'Never signed in', 'activity' => 'Sign-in activity'];
$view = isset($views[get_str('view')]) ? get_str('view') : 'all';

/** One account with its profile. */
function mem_load(int $id): ?array
{
    $q = db()->prepare('SELECT m.*, p.full_name, p.public_code, p.approval_status, p.photo FROM members m LEFT JOIN players p ON p.id = m.player_id WHERE m.id = ?'); $q->execute([$id]);
    return $q->fetch() ?: null;
}
function mem_delete(int $id, bool $withProfile): void
{
    $m = mem_load($id); if (!$m) { return; }
    db()->prepare('DELETE FROM member_logins WHERE member_id = ?')->execute([$id]);
    db()->prepare('DELETE FROM member_requests WHERE member_id = ?')->execute([$id]);
    db()->prepare('DELETE FROM members WHERE id = ?')->execute([$id]);
    if ($withProfile && $m['player_id']) {
        $others = (int)db()->query("SELECT COUNT(*) FROM players WHERE id <> " . (int)$m['player_id'])->fetchColumn();
        if ($others > 0) { db()->prepare('DELETE FROM players WHERE id = ?')->execute([$m['player_id']]); delete_upload($m['photo']); }
    }
}

if (is_post()) {
    csrf_require();
    $op = post('op');
    if ($op === 'bulk') {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])))));
        $do = post('do'); $n = 0;
        if (!$ids || !in_array($do, ['block', 'unblock', 'verify', 'delete'], true)) { flash_set('info', 'Tick at least one member and choose an action.'); redirect('admin/' . $self . '?view=' . $view); }
        foreach ($ids as $mid) {
            $m = mem_load($mid); if (!$m) { continue; }
            if ($do === 'block') { db()->prepare('UPDATE members SET is_blocked = 1 WHERE id = ?')->execute([$mid]); }
            elseif ($do === 'unblock') { db()->prepare('UPDATE members SET is_blocked = 0 WHERE id = ?')->execute([$mid]); }
            elseif ($do === 'verify') { if (!$m['email_verified']) { member_mark_verified($mid); } else { continue; } }
            else { mem_delete($mid, false); }
            $n++;
        }
        flash_set($n ? 'success' : 'info', $n ? $n . ' account' . ($n === 1 ? '' : 's') . ' ' . ['block' => 'blocked', 'unblock' => 'unblocked', 'verify' => 'verified (player ID e-mailed)', 'delete' => 'deleted (their player profiles stay)'][$do] . '.' : 'Nothing to change for the ticked accounts.');
        redirect('admin/' . $self . '?view=' . $view);
    }
    $mid = (int)post('id'); $m = mem_load($mid);
    if (!$m) { flash_set('danger', 'Account not found.'); redirect('admin/' . $self); }
    $back = 'admin/' . $self . '?id=' . $mid;
    switch ($op) {
        case 'verify':
            if ($m['email_verified']) { flash_set('info', 'Already verified.'); }
            else { member_mark_verified($mid); flash_set('success', 'E-mail marked as verified and the player ID was e-mailed (check the mail log below if it did not arrive).'); }
            break;
        case 'resend_code':
            [$ok, $er] = member_send_verification($mid); flash_set($ok ? 'success' : 'danger', $ok ? 'A new verification code was e-mailed.' : 'Could not send: ' . $er); break;
        case 'send_id':
            [$ok, $er] = member_send_id($mid); flash_set($ok ? 'success' : 'danger', $ok ? 'The player ID was e-mailed to ' . $m['email'] . '.' : 'Could not send: ' . $er); break;
        case 'reset_password':
            $pw = '';
            if (post('new_password') !== '') {
                $pw = (string)($_POST['new_password'] ?? '');
                if ($e = ft_password_error($pw)) { flash_set('danger', $e); break; }
            } else { $abc = 'abcdefghjkmnpqrstuvwxyz'; $pw = ''; for ($i = 0; $i < 7; $i++) { $pw .= $abc[random_int(0, strlen($abc) - 1)]; } $pw .= random_int(100, 999); $pw = ucfirst($pw); }
            db()->prepare('UPDATE members SET password_hash = ? WHERE id = ?')->execute([password_hash($pw, PASSWORD_DEFAULT), $mid]);
            flash_set('success', 'New password set: ' . $pw . '  (shown only now; tell the member and ask them to change it in My account).'); break;
        case 'block': db()->prepare('UPDATE members SET is_blocked = 1 WHERE id = ?')->execute([$mid]); flash_set('success', 'Account blocked: the member is signed out and cannot sign in.'); break;
        case 'unblock': db()->prepare('UPDATE members SET is_blocked = 0 WHERE id = ?')->execute([$mid]); flash_set('success', 'Account unblocked.'); break;
        case 'note': db()->prepare('UPDATE members SET admin_note = ? WHERE id = ?')->execute([mb_substr(trim(post('admin_note')), 0, 500) ?: null, $mid]); flash_set('success', 'Note saved.'); break;
        case 'delete':
            mem_delete($mid, post('mode') === 'profile');
            flash_set('success', 'Account deleted' . (post('mode') === 'profile' ? ' together with the player profile.' : '; the player profile stays on the site.')); redirect('admin/' . $self);
    }
    redirect($back);
}

$pageTitle = 'Members';
require ROOT_PATH . '/includes/admin_header.php';
?>
<?php if ($id && ($m = mem_load($id))):
  $logins = db()->prepare('SELECT * FROM member_logins WHERE member_id = ? ORDER BY id DESC LIMIT 40'); $logins->execute([$id]); $logins = $logins->fetchAll();
  $reqs = db()->prepare('SELECT * FROM member_requests WHERE member_id = ? ORDER BY id DESC LIMIT 30'); $reqs->execute([$id]); $reqs = $reqs->fetchAll();
  $mails = db()->prepare('SELECT * FROM mail_log WHERE to_email = ? ORDER BY id DESC LIMIT 8'); $mails->execute([$m['email']]); $mails = $mails->fetchAll();
  $fails = (int)db()->query('SELECT COUNT(*) FROM member_logins WHERE ok = 0 AND member_id = ' . $id)->fetchColumn();
  $f = fn(string $op, string $label, string $icon, string $cls = 'outline-theme', string $confirm = '') => '<form method="post" class="d-inline">' . csrf_field() . '<input type="hidden" name="id" value="' . $id . '"><button class="btn btn-sm btn-' . $cls . '" name="op" value="' . $op . '"' . ($confirm ? ' onclick="return confirm(\'' . e($confirm) . '\')"' : '') . '><i class="bi ' . $icon . '"></i> ' . e($label) . '</button></form>'; ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <h1 class="page-title h3 mb-0"><i class="bi bi-person-lines-fill"></i> <?= e($m['full_name'] ?: $m['email']) ?></h1>
  <a class="btn btn-outline-theme btn-sm" href="<?= e(url('admin/members.php')) ?>"><i class="bi bi-arrow-left"></i> All members</a>
</div>
<?= flash_render() ?>
<div class="row g-3">
  <div class="col-lg-6"><div class="ft-card p-3 h-100">
    <h2 class="h6 section-title">Account</h2>
    <dl class="row mb-0 small">
      <dt class="col-5">Player ID</dt><dd class="col-7 fw-bold"><?= e((string)($m['public_code'] ?? '-')) ?></dd>
      <dt class="col-5">E-mail</dt><dd class="col-7 text-break"><?= e($m['email']) ?> <?= $m['email_verified'] ? '<span class="badge bg-success">verified</span>' : '<span class="badge bg-warning text-dark">not verified</span>' ?></dd>
      <dt class="col-5">Account</dt><dd class="col-7"><?= $m['is_blocked'] ? '<span class="badge bg-danger">Blocked</span>' : '<span class="badge bg-success">Active</span>' ?></dd>
      <dt class="col-5">Profile</dt><dd class="col-7"><?php $ap = (string)($m['approval_status'] ?? ''); echo $ap === 'approved' ? '<span class="badge bg-success">approved</span>' : ($ap === 'rejected' ? '<span class="badge bg-danger">rejected</span>' : ($ap === 'pending' ? '<span class="badge bg-warning text-dark">waiting</span>' : '-')); ?>
        <?php if ($m['player_id']): ?> <a href="<?= e(url('admin/players.php?view=pending')) ?>" class="small">approvals</a> &middot; <a href="<?= e(url('player-home.php?player=' . (int)$m['player_id'])) ?>" target="_blank" rel="noopener" class="small">open page</a><?php endif; ?></dd>
      <dt class="col-5">Signed up</dt><dd class="col-7"><?= e(fmt_date(substr((string)$m['created_at'], 0, 10))) ?> <?= e(substr((string)$m['created_at'], 11, 5)) ?></dd>
      <dt class="col-5">Last sign-in</dt><dd class="col-7"><?= $m['last_login_at'] ? e(fmt_date(substr((string)$m['last_login_at'], 0, 10))) . ' ' . e(substr((string)$m['last_login_at'], 11, 5)) : 'never' ?></dd>
      <dt class="col-5">Sign-ins</dt><dd class="col-7"><?= (int)$m['login_count'] ?> successful, <?= $fails ?> failed</dd>
    </dl>
    <form method="post" class="mt-3"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="op" value="note">
      <label class="form-label small mb-1">Your private note</label><div class="input-group input-group-sm"><input class="form-control" name="admin_note" maxlength="500" value="<?= e((string)$m['admin_note']) ?>" placeholder="Only you can see this"><button class="btn btn-outline-theme">Save</button></div></form>
  </div></div>
  <div class="col-lg-6"><div class="ft-card p-3 h-100">
    <h2 class="h6 section-title">Actions</h2>
    <div class="d-flex flex-wrap gap-2 mb-3">
      <?php if (!$m['email_verified']): ?><?= $f('verify', 'Mark e-mail verified', 'bi-patch-check', 'accent', 'Mark this e-mail as verified and e-mail the player ID?') ?><?= $f('resend_code', 'Resend code', 'bi-envelope-arrow-up') ?><?php endif; ?>
      <?= $f('send_id', 'E-mail the player ID', 'bi-envelope') ?>
      <?= $m['is_blocked'] ? $f('unblock', 'Unblock', 'bi-unlock', 'accent') : $f('block', 'Block', 'bi-slash-circle', 'outline-danger', 'Block this account? The member is signed out and cannot sign in.') ?>
    </div>
    <form method="post" class="mb-3"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="op" value="reset_password">
      <label class="form-label small mb-1">Reset password</label>
      <div class="input-group input-group-sm"><input class="form-control" type="text" name="new_password" autocomplete="off" placeholder="Type a new password, or leave empty to generate one"><button class="btn btn-outline-theme">Set</button></div></form>
    <form method="post" class="border-top pt-3" style="border-color:var(--card-border)!important" onsubmit="return confirm('Delete this account? This cannot be undone.')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="op" value="delete">
      <label class="form-label small mb-1 text-danger">Delete account</label>
      <div class="d-flex flex-wrap gap-2"><select class="form-select form-select-sm w-auto" name="mode"><option value="account">Account only (profile stays)</option><option value="profile">Account and player profile</option></select><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Delete</button></div></form>
  </div></div>

  <div class="col-lg-7"><div class="ft-card p-3">
    <h2 class="h6 section-title">Sign-in history</h2>
    <?php if (!$logins): ?><p class="small text-muted-ft mb-0">No sign-in attempts recorded yet.</p><?php else: ?>
    <div class="table-responsive"><table class="table ft-table table-sm mb-0"><thead><tr><th>When</th><th>Result</th><th>Used</th><th>IP</th><th>Device</th></tr></thead><tbody>
    <?php foreach ($logins as $l): ?><tr><td class="text-nowrap"><?= e(substr((string)$l['created_at'], 0, 16)) ?></td><td><?= $l['ok'] ? '<span class="badge bg-success">ok</span>' : '<span class="badge bg-danger">failed</span>' ?> <span class="small text-muted-ft"><?= e((string)$l['note']) ?></span></td><td class="small"><?= e($l['ident']) ?></td><td class="small"><?= e((string)$l['ip']) ?></td><td class="small text-truncate" style="max-width:160px" title="<?= e((string)$l['agent']) ?>"><?= e((string)$l['agent']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
  </div></div>
  <div class="col-lg-5">
    <div class="ft-card p-3 mb-3"><h2 class="h6 section-title">Requests <a class="small ms-2" href="<?= e(url('admin/requests.php?view=all')) ?>">open queue</a></h2>
      <?php if (!$reqs): ?><p class="small text-muted-ft mb-0">None.</p><?php endif; ?>
      <?php foreach ($reqs as $r): ?><div class="small py-1 border-top" style="border-color:var(--card-border)!important"><span class="badge text-bg-<?= $r['status'] === 'approved' ? 'success' : ($r['status'] === 'pending' ? 'warning' : 'danger') ?>"><?= e($r['status']) ?></span> <?= e(MEMBER_REQUEST_KINDS[$r['kind']] ?? $r['kind']) ?>: <b><?= e($r['title']) ?></b></div><?php endforeach; ?></div>
    <div class="ft-card p-3"><h2 class="h6 section-title">E-mails sent to this address</h2>
      <?php if (!$mails): ?><p class="small text-muted-ft mb-0">None yet.</p><?php endif; ?>
      <?php foreach ($mails as $l): ?><div class="small py-1 border-top" style="border-color:var(--card-border)!important"><span class="badge text-bg-<?= $l['ok'] ? 'success' : 'danger' ?>"><?= $l['ok'] ? 'sent' : 'failed' ?></span> <?= e($l['subject']) ?> <span class="text-muted-ft"><?= e(substr((string)$l['created_at'], 0, 16)) ?></span><?= !$l['ok'] && $l['error'] ? '<div class="text-danger">' . e($l['error']) . '</div>' : '' ?></div><?php endforeach; ?></div>
  </div>
</div>

<?php elseif ($view === 'activity'):
  $fOnly = get_str('failed') === '1';
  $acts = db()->query('SELECT l.*, p.full_name FROM member_logins l LEFT JOIN members m ON m.id = l.member_id LEFT JOIN players p ON p.id = m.player_id ' . ($fOnly ? 'WHERE l.ok = 0 ' : '') . 'ORDER BY l.id DESC LIMIT 200')->fetchAll(); ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><h1 class="page-title h3 mb-0"><i class="bi bi-person-lines-fill"></i> Members</h1></div>
<?= flash_render() ?>
<div class="btn-group btn-group-sm flex-wrap mb-3"><?php foreach ($views as $k => $lab): ?><a class="btn btn-<?= $view === $k ? 'accent' : 'outline-theme' ?>" href="<?= e(url('admin/members.php?view=' . $k)) ?>"><?= e($lab) ?></a><?php endforeach; ?></div>
<div class="mb-2"><a class="btn btn-sm btn-<?= $fOnly ? 'accent' : 'outline-theme' ?>" href="<?= e(url('admin/members.php?view=activity' . ($fOnly ? '' : '&failed=1'))) ?>"><i class="bi bi-exclamation-triangle"></i> Failed attempts only</a> <span class="small text-muted-ft ms-2">Latest 200, kept for 180 days.</span></div>
<div class="ft-card p-0"><div class="table-responsive"><table class="table ft-table table-hover align-middle mb-0"><thead><tr><th>When</th><th>Member</th><th>Used to sign in</th><th>Result</th><th>IP</th><th>Device</th></tr></thead><tbody>
<?php if (!$acts): ?><tr><td colspan="6" class="text-center text-muted-ft py-4">No sign-in activity yet.</td></tr><?php endif; ?>
<?php foreach ($acts as $l): ?><tr><td class="text-nowrap"><?= e(substr((string)$l['created_at'], 0, 16)) ?></td><td><?= $l['member_id'] ? '<a href="' . e(url('admin/members.php?id=' . (int)$l['member_id'])) . '">' . e($l['full_name'] ?: 'Member #' . $l['member_id']) . '</a>' : '<span class="text-muted-ft">unknown</span>' ?></td><td class="small"><?= e($l['ident']) ?></td><td><?= $l['ok'] ? '<span class="badge bg-success">ok</span>' : '<span class="badge bg-danger">failed</span>' ?> <span class="small text-muted-ft"><?= e((string)$l['note']) ?></span></td><td class="small"><?= e((string)$l['ip']) ?></td><td class="small text-truncate" style="max-width:200px"><?= e((string)$l['agent']) ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>

<?php else:
  $q = trim(get_str('q')); $page = max(1, (int)get_str('page')); $per = 40;
  $cond = ['1']; $par = [];
  if ($view === 'new') { $cond[] = 'm.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)'; }
  elseif ($view === 'unverified') { $cond[] = 'm.email_verified = 0'; }
  elseif ($view === 'verified') { $cond[] = 'm.email_verified = 1'; }
  elseif ($view === 'blocked') { $cond[] = 'm.is_blocked = 1'; }
  elseif ($view === 'never') { $cond[] = 'm.last_login_at IS NULL'; }
  if ($q !== '') { $cond[] = '(p.full_name LIKE ? OR m.email LIKE ? OR p.public_code = ?)'; $par = ['%' . $q . '%', '%' . $q . '%', $q]; }
  $where = implode(' AND ', $cond);
  $tot = db()->prepare("SELECT COUNT(*) FROM members m LEFT JOIN players p ON p.id = m.player_id WHERE $where"); $tot->execute($par); $total = (int)$tot->fetchColumn();
  $st = db()->prepare("SELECT m.*, p.full_name, p.public_code, p.approval_status, (SELECT COUNT(*) FROM member_requests r WHERE r.member_id = m.id AND r.status = 'pending') AS pend FROM members m LEFT JOIN players p ON p.id = m.player_id WHERE $where ORDER BY m.id DESC LIMIT $per OFFSET " . (($page - 1) * $per));
  $st->execute($par); $rows = $st->fetchAll();
  $s = db()->query("SELECT COUNT(*) t, SUM(email_verified = 1) v, SUM(email_verified = 0) u, SUM(is_blocked = 1) b, SUM(last_login_at IS NULL) nv, SUM(created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)) n, SUM(last_login_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) a FROM members")->fetch(); ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><h1 class="page-title h3 mb-0"><i class="bi bi-person-lines-fill"></i> Members</h1>
  <form class="d-flex gap-2" method="get"><input type="hidden" name="view" value="<?= e($view) ?>"><input class="form-control form-control-sm" type="search" name="q" value="<?= e($q) ?>" placeholder="Name, e-mail or ID"><button class="btn btn-sm btn-outline-theme"><i class="bi bi-search"></i></button></form></div>
<?= flash_render() ?>
<p class="text-muted-ft">People who signed up on the site. Open a member to verify the e-mail, resend the ID, reset the password, block, read the sign-in history or delete the account.</p>
<div class="row g-2 mb-3">
<?php foreach ([['Accounts', $s['t'], 'bi-people'], ['New (14 days)', $s['n'], 'bi-person-plus'], ['Not verified', $s['u'], 'bi-envelope-exclamation'], ['Active (30 days)', $s['a'], 'bi-box-arrow-in-right'], ['Blocked', $s['b'], 'bi-slash-circle']] as [$lab, $n, $ic]): ?>
  <div class="col-6 col-md"><div class="ft-card p-2 px-3 d-flex align-items-center gap-2"><i class="bi <?= $ic ?> fs-4" style="color:var(--accent)"></i><div><div class="fw-bold fs-5 lh-1"><?= (int)$n ?></div><div class="small text-muted-ft"><?= e($lab) ?></div></div></div></div>
<?php endforeach; ?></div>
<div class="btn-group btn-group-sm flex-wrap mb-3"><?php foreach ($views as $k => $lab): ?><a class="btn btn-<?= $view === $k ? 'accent' : 'outline-theme' ?>" href="<?= e(url('admin/members.php?view=' . $k . ($q !== '' ? '&q=' . rawurlencode($q) : ''))) ?>"><?= e($lab) ?></a><?php endforeach; ?></div>
<?php if ($rows): ?>
<form id="bulkForm" method="post" class="bulk-bar d-flex flex-wrap align-items-center gap-2 mb-2"><?= csrf_field() ?><input type="hidden" name="op" value="bulk">
  <label class="form-check mb-0 d-flex align-items-center gap-2"><input class="form-check-input mt-0" type="checkbox" id="bulkAll"> <span class="small">Select all on this page</span></label>
  <span class="small text-muted-ft"><b id="bulkCount">0</b> selected</span>
  <select class="form-select form-select-sm w-auto" name="do" aria-label="Action"><option value="verify">Mark e-mail verified</option><option value="block">Block</option><option value="unblock">Unblock</option><option value="delete">Delete accounts (profiles stay)</option></select>
  <button class="btn btn-sm btn-outline-theme" id="bulkDel" disabled onclick="if(document.querySelector('[name=do]').value==='delete'){return confirm('Delete the ticked accounts? The player profiles stay.')}"><i class="bi bi-check2-square"></i> Apply to selected</button></form>
<?php endif; ?>
<div class="ft-card p-0"><div class="table-responsive"><table class="table ft-table table-hover align-middle mb-0"><thead><tr><th style="width:1%"></th><th>Member</th><th>E-mail</th><th>Status</th><th>Signed up</th><th>Last sign-in</th><th class="text-end">Sign-ins</th><th></th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="8" class="text-center text-muted-ft py-4">No members here.</td></tr><?php endif; ?>
<?php foreach ($rows as $r): ?><tr>
  <td><input class="form-check-input bulk-cb" type="checkbox" name="ids[]" value="<?= (int)$r['id'] ?>" form="bulkForm" aria-label="Select"></td>
  <td style="min-width:150px"><a class="fw-semibold" href="<?= e(url('admin/members.php?id=' . (int)$r['id'])) ?>"><?= e($r['full_name'] ?: '(no profile)') ?></a><div class="small text-muted-ft">ID <?= e((string)($r['public_code'] ?? '-')) ?></div></td>
  <td class="small" style="min-width:190px;overflow-wrap:anywhere"><?= e($r['email']) ?></td>
  <td><?= $r['is_blocked'] ? '<span class="badge bg-danger">Blocked</span> ' : '' ?><?= $r['email_verified'] ? '<span class="badge bg-success">Verified</span>' : '<span class="badge bg-warning text-dark">Not verified</span>' ?>
      <?php $ap = (string)($r['approval_status'] ?? ''); if ($ap === 'pending'): ?> <span class="badge text-bg-secondary" title="Profile waiting for approval">Profile waiting</span><?php endif; ?>
      <?= (int)$r['pend'] ? ' <span class="badge text-bg-info" title="Requests waiting">' . (int)$r['pend'] . ' request' . ((int)$r['pend'] === 1 ? '' : 's') . '</span>' : '' ?></td>
  <td class="small text-nowrap"><?= e(fmt_date(substr((string)$r['created_at'], 0, 10))) ?></td>
  <td class="small text-nowrap"><?= $r['last_login_at'] ? e(fmt_date(substr((string)$r['last_login_at'], 0, 10))) : '<span class="text-muted-ft">never</span>' ?></td>
  <td class="text-end"><?= (int)$r['login_count'] ?></td>
  <td class="text-end"><a class="btn btn-sm btn-outline-theme" href="<?= e(url('admin/members.php?id=' . (int)$r['id'])) ?>">Manage</a></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<?= pagination($total, $per, $page, 'members.php?view=' . $view . ($q !== '' ? '&q=' . rawurlencode($q) : '')) ?>
<?php if ($rows) { echo bulk_select_script(); } ?>
<?php endif;
require ROOT_PATH . '/includes/admin_footer.php';
