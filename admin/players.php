<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
$admin = require_login();
$self = 'players.php';

$fields = [
    ['name' => 'photo', 'label' => 'Player Photo', 'type' => 'image', 'dir' => 'profile', 'col' => 12],
    ['name' => 'full_name', 'label' => 'Full Name', 'required' => true, 'max' => 120],
    ['name' => 'nickname', 'label' => 'Nickname', 'max' => 60],
    ['name' => 'date_of_birth', 'label' => 'Date of Birth', 'type' => 'date'],
    ['name' => 'career_start_date', 'label' => 'Football Career Start Date', 'type' => 'date'],
    ['name' => 'preferred_position', 'label' => 'Preferred Position', 'type' => 'select', 'nullable' => true, 'options' => POSITIONS],
    ['name' => 'secondary_position', 'label' => 'Secondary Position', 'type' => 'select', 'nullable' => true, 'options' => POSITIONS],
    ['name' => 'strong_foot', 'label' => 'Strong Foot', 'type' => 'select', 'nullable' => true, 'options' => ['Right', 'Left', 'Both']],
    ['name' => 'jersey_number', 'label' => 'Jersey Number', 'type' => 'number', 'min' => 0, 'max' => 99],
    ['name' => 'height_cm', 'label' => 'Height (cm)', 'type' => 'number', 'min' => 100, 'max' => 250],
    ['name' => 'weight_kg', 'label' => 'Weight (kg)', 'type' => 'number', 'min' => 30, 'max' => 200, 'step' => '0.1'],
    ['name' => 'playing_style', 'label' => 'Playing Style', 'max' => 255, 'col' => 12],
    ['name' => 'bio', 'label' => 'Short Biography', 'type' => 'textarea', 'rows' => 5],
    ['name' => 'is_featured', 'label' => 'Show this player on the homepage', 'type' => 'checkbox', 'col' => 6],
    ['name' => 'is_active', 'label' => 'Active (untick to archive - hidden from the public site)', 'type' => 'checkbox', 'col' => 6, 'default' => 1],
];
$teamOptions = lookup('SELECT id, name FROM teams ORDER BY name');
$action = get_str('action', 'list');
$id = (int)($_GET['id'] ?? 0);
$errors = [];
$row = [];
$ptRows = [];

/** Make $id the homepage player (exactly one at a time). */
function set_featured(int $id): void
{
    db()->exec('UPDATE players SET is_featured = 0');
    db()->prepare('UPDATE players SET is_featured = 1, is_active = 1 WHERE id = ?')->execute([$id]);
}

if (is_post()) {
    csrf_require();
    $op = post('op');
    if ($op === 'feature') {
        $pl = get_player((int)post('id'));
        if ($pl) { set_featured((int)$pl['id']); flash_set('success', $pl['full_name'] . ' is now shown on the homepage.'); }
        redirect('admin/' . $self);
    }
    if ($op === 'approve' || $op === 'reject') {
        $pl = get_player((int)post('id'));
        if ($pl && $pl['approval_status'] === 'pending') {
            if ($op === 'approve') {
                db()->prepare("UPDATE players SET approval_status='approved', is_active=1 WHERE id=?")->execute([$pl['id']]);
                flash_set('success', $pl['full_name'] . ' approved and now visible on the site.');
            } else {
                db()->prepare('DELETE FROM players WHERE id = ?')->execute([$pl['id']]);
                delete_upload($pl['photo']);
                flash_set('success', 'Registration from ' . $pl['full_name'] . ' rejected and removed.');
            }
        }
        redirect('admin/' . $self . (pending_players() ? '?view=pending' : ''));
    }
    if ($op === 'delete') {
        $pl = get_player((int)post('id'));
        $count = (int)db()->query('SELECT COUNT(*) FROM players')->fetchColumn();
        if (!$pl) { redirect('admin/' . $self); }
        if ($count <= 1) { flash_set('danger', 'You cannot delete the last player.'); redirect('admin/' . $self); }
        db()->prepare('DELETE FROM players WHERE id = ?')->execute([$pl['id']]);   // stats, goals, assists, trophies cascade
        delete_upload($pl['photo']);
        if ($pl['is_featured']) { set_featured(featured_player_id()); }
        if ((int)($_SESSION['admin_player_id'] ?? 0) === (int)$pl['id']) { unset($_SESSION['admin_player_id']); }
        flash_set('success', 'Player deleted together with all of his match records.');
        redirect('admin/' . $self);
    }
    if ($op === 'save') {
        $existing = (int)post('id') ? get_player((int)post('id')) : null;
        [$data, $errors, $uploaded] = crud_collect($fields, $existing ?? []);
        // teams played for
        $pt = [];
        $tm = $_POST['pt_team'] ?? []; $ts = $_POST['pt_start'] ?? []; $te = $_POST['pt_end'] ?? []; $cur = (int)($_POST['pt_current'] ?? -1);
        foreach ($tm as $i => $tid) {
            $tid = (int)$tid;
            if (!$tid) { continue; }
            if (!isset($teamOptions[$tid])) { $errors[] = 'Unknown team in "Teams played for".'; continue; }
            $st = trim((string)($ts[$i] ?? '')); $en = trim((string)($te[$i] ?? ''));
            foreach ([$st, $en] as $d) { if ($d !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) { $errors[] = 'A team date is not valid.'; } }
            if ($st !== '' && $en !== '' && $en < $st) { $errors[] = $teamOptions[$tid] . ': end date is before the start date.'; }
            $pt[] = ['team' => $tid, 'start' => $st ?: null, 'end' => $en ?: null, 'current' => (int)$i === $cur ? 1 : 0];
        }
        if (!$errors && !empty($data['is_featured']) === false && $existing && $existing['is_featured'] && empty($data['is_featured'])) {
            // un-ticking the featured player is not allowed: choose another player instead
            $data['is_featured'] = 1;
            flash_set('info', 'A player must always be shown on the homepage - tick "Show on homepage" on another player to change it.');
        }
        if ($errors) {
            foreach ($uploaded as $u) { delete_upload($u); }
            $row = array_merge($existing ?? [], $_POST, $data);
            $ptRows = array_map(fn($r) => ['team_id' => $r['team'], 'start_date' => $r['start'], 'end_date' => $r['end'], 'is_current' => $r['current']], $pt);
            $action = $existing ? 'edit' : 'new'; $id = (int)($existing['id'] ?? 0);
        } else {
            $pdo = db();
            $pdo->beginTransaction();
            if ($existing) {
                $set = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($data)));
                $pdo->prepare("UPDATE players SET $set WHERE id = ?")->execute([...array_values($data), $existing['id']]);
                if (!empty($data['photo']) && !empty($existing['photo'])) { delete_upload($existing['photo']); }
                $pid = (int)$existing['id'];
            } else {
                $data['admin_id'] = $admin['id'];
                $cols = implode(', ', array_map(fn($k) => "`$k`", array_keys($data)));
                $pdo->prepare("INSERT INTO players ($cols) VALUES (" . implode(',', array_fill(0, count($data), '?')) . ')')->execute(array_values($data));
                $pid = (int)$pdo->lastInsertId();
            }
            $pdo->prepare('DELETE FROM player_teams WHERE player_id = ?')->execute([$pid]);
            $ins = $pdo->prepare('INSERT INTO player_teams (player_id, team_id, start_date, end_date, is_current) VALUES (?,?,?,?,?)');
            $currentTeam = null;
            foreach ($pt as $r) {
                $ins->execute([$pid, $r['team'], $r['start'], $r['end'], $r['current']]);
                if ($r['current']) { $currentTeam = $r['team']; }
            }
            if ($currentTeam === null) { // fall back to the latest team without an end date
                foreach ($pt as $r) { if ($r['end'] === null) { $currentTeam = $r['team']; } }
            }
            $pdo->prepare('UPDATE players SET current_team_id = ? WHERE id = ?')->execute([$currentTeam, $pid]);
            $pdo->commit();
            if (!empty($data['is_featured'])) { set_featured($pid); }
            if (!$existing) { $_SESSION['admin_player_id'] = $pid; }
            flash_set('success', 'Player saved.' . (!$existing ? ' You are now managing ' . $data['full_name'] . '.' : ''));
            redirect('admin/' . $self);
        }
    }
}

if (($action === 'edit') && $id && !$row) {
    $row = get_player($id);
    if (!$row) { flash_set('danger', 'Player not found.'); redirect('admin/' . $self); }
}
if (($action === 'edit') && $id && !$ptRows) {
    $st = db()->prepare('SELECT * FROM player_teams WHERE player_id = ? ORDER BY is_current DESC, start_date DESC'); $st->execute([$id]); $ptRows = $st->fetchAll();
}
if ($action === 'new' && !$row) { $row = ['is_active' => 1]; }

$pageTitle = 'Players';
require ROOT_PATH . '/includes/admin_header.php';

if ($action === 'new' || $action === 'edit'):
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="page-title h3"><i class="bi bi-person-badge"></i> <?= $action === 'new' ? 'Add Player' : 'Edit Player' ?></h1>
  <a class="btn btn-outline-theme" href="<?= e(url('admin/' . $self)) ?>"><i class="bi bi-arrow-left"></i> Back</a>
</div>
<?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" enctype="multipart/form-data" class="vstack gap-4">
  <?= csrf_field() ?><input type="hidden" name="op" value="save"><input type="hidden" name="id" value="<?= (int)($row['id'] ?? 0) ?>">
  <div class="ft-card p-4"><h2 class="section-title h6 mb-3">Player details</h2><?= crud_render_fields($fields, $row) ?></div>
  <div class="ft-card p-4">
    <h2 class="section-title h6 mb-1">Teams played for</h2>
    <p class="text-muted-ft small">Shown as the career timeline on the player's profile. Choose the team, when he joined/left, and mark the current one.</p>
    <div class="table-responsive"><table class="table ft-table align-middle mb-0"><thead><tr><th>Team</th><th>From</th><th>To</th><th class="text-center">Current</th></tr></thead><tbody>
      <?php $n = max(4, count($ptRows) + 2); for ($i = 0; $i < $n; $i++): $r = $ptRows[$i] ?? []; ?>
        <tr>
          <td><select class="form-select form-select-sm" name="pt_team[<?= $i ?>]"><?= sel_opts($teamOptions, $r['team_id'] ?? '', '- none -') ?></select></td>
          <td><input type="date" class="form-control form-control-sm" name="pt_start[<?= $i ?>]" value="<?= e($r['start_date'] ?? '') ?>"></td>
          <td><input type="date" class="form-control form-control-sm" name="pt_end[<?= $i ?>]" value="<?= e($r['end_date'] ?? '') ?>"></td>
          <td class="text-center"><input class="form-check-input" type="radio" name="pt_current" value="<?= $i ?>" <?= !empty($r['is_current']) ? 'checked' : '' ?>></td>
        </tr>
      <?php endfor; ?>
    </tbody></table></div>
    <div class="form-text mt-2">Need a team that is not listed? <a href="<?= e(url('admin/teams.php?action=new')) ?>">Add it first</a>, then come back.</div>
  </div>
  <div class="d-flex gap-2"><button class="btn btn-accent btn-lg" type="submit"><i class="bi bi-check-lg"></i> Save Player</button><a class="btn btn-outline-theme btn-lg" href="<?= e(url('admin/' . $self)) ?>">Cancel</a></div>
</form>
<?php elseif (get_str('view') === 'pending'):
    $pending = pending_players();
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div><h1 class="page-title h3 mb-0"><i class="bi bi-person-check"></i> Registration approvals</h1>
    <p class="text-muted-ft mb-0">Players who registered from the public "Join" page. Nothing is published until you approve.</p></div>
  <a class="btn btn-outline-theme" href="<?= e(url('admin/' . $self)) ?>"><i class="bi bi-people"></i> All players</a>
</div>
<?php if (!$pending): ?><div class="ft-card p-4 text-center text-muted-ft">No pending registrations.</div><?php endif; ?>
<div class="req-list">
<?php foreach ($pending as $p): ?>
  <div class="req-row">
    <img class="req-photo" src="<?= e(upload_url($p['photo'])) ?>" alt="">
    <div class="req-main">
      <div class="req-name"><?= e($p['full_name']) ?><?= $p['nickname'] ? ' <small>&ldquo;' . e($p['nickname']) . '&rdquo;</small>' : '' ?></div>
      <div class="req-meta"><span class="req-pill"><?= e(position_name($p['preferred_position'])) ?></span><span><i class="bi bi-envelope"></i> <?= e($p['email']) ?></span><?= $p['phone'] ? '<span><i class="bi bi-telephone"></i> ' . e($p['phone']) . '</span>' : '' ?><span><i class="bi bi-clock"></i> <?= e(fmt_date(substr((string)$p['registered_at'], 0, 10))) ?></span></div>
      <?php if ($p['bio']): ?><div class="req-bio" title="<?= e($p['bio']) ?>"><?= e($p['bio']) ?></div><?php endif; ?>
    </div>
    <div class="req-actions">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="op" value="approve"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn btn-sm btn-accent" title="Approve"><i class="bi bi-check-lg"></i> Approve</button></form>
      <a class="btn btn-sm btn-outline-theme" title="Edit first" href="<?= e(url('admin/' . $self . '?action=edit&id=' . $p['id'])) ?>"><i class="bi bi-pencil"></i></a>
      <form method="post" data-confirm="Reject and delete this registration?"><?= csrf_field() ?><input type="hidden" name="op" value="reject"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn btn-sm btn-outline-danger" title="Reject"><i class="bi bi-x-lg"></i></button></form>
    </div>
  </div>
<?php endforeach; ?>
</div>
<?php else:
    $players = players_list(false);
    $current = ctx_pid();
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div><h1 class="page-title h3 mb-0"><i class="bi bi-people"></i> Players</h1>
    <p class="text-muted-ft mb-0">Choose who is shown on the homepage, and which player you are managing in the admin.</p></div>
  <a class="btn btn-accent" href="<?= e(url('admin/' . $self . '?action=new')) ?>"><i class="bi bi-plus-lg"></i> Add Player</a>
</div>
<?php if ($np = count(pending_players())): ?><div class="alert alert-warning d-flex justify-content-between align-items-center"><span><i class="bi bi-person-exclamation"></i> <b><?= $np ?></b> registration<?= $np > 1 ? 's' : '' ?> waiting for approval.</span><a class="btn btn-sm btn-accent" href="<?= e(url('admin/' . $self . '?view=pending')) ?>">Review</a></div><?php endif; ?>
<div class="pl-list">
<?php foreach ($players as $p):
    $s = with_player((int)$p['id'], fn() => get_overall_stats()); ?>
  <div class="pl-row<?= $p['is_featured'] ? ' featured' : '' ?><?= (int)$p['id'] === $current ? ' managing' : '' ?>">
    <img class="pl-photo" src="<?= e(upload_url($p['photo'])) ?>" alt="">
    <div class="pl-main">
      <div class="pl-name"><?= e($p['full_name']) ?>
        <?php if ($p['is_featured']): ?><span class="badge bg-warning text-dark"><i class="bi bi-house-fill"></i> Homepage</span><?php endif; ?>
        <?php if ((int)$p['id'] === $current): ?><span class="badge bg-success">Managing</span><?php endif; ?>
        <?php if (!$p['is_active']): ?><span class="badge bg-secondary">Archived</span><?php endif; ?></div>
      <div class="pl-meta"><?= e(position_name($p['preferred_position'])) ?><?= $p['jersey_number'] ? ' &middot; #' . (int)$p['jersey_number'] : '' ?><?= $p['team_name'] ? ' &middot; ' . e($p['team_name']) : '' ?> &middot; <a class="id-chip" href="<?= e(player_url_by_id((int)$p['id'])) ?>" target="_blank" rel="noopener" title="Open public page">ID <?= e((string)$p['public_code']) ?></a></div>
    </div>
    <div class="pl-stats">
      <div><b><?= $s['matches'] ?></b><small>Matches</small></div>
      <div><b><?= $s['goals'] ?></b><small>Goals</small></div>
      <div><b><?= $s['assists'] ?></b><small>Assists</small></div>
      <div><b><?= e($s['avg_rating'] !== null ? fmt_num($s['avg_rating']) : '-') ?></b><small>Rating</small></div>
    </div>
    <div class="pl-actions">
      <?php if (!$p['is_featured']): ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="op" value="feature"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <button class="btn btn-sm btn-accent" title="Show on homepage"><i class="bi bi-house"></i> Homepage</button></form>
      <?php endif; ?>
      <form method="post" action="<?= e(url('admin/switch-player.php')) ?>"><?= csrf_field() ?><input type="hidden" name="player_id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="return" value="<?= e(url('admin/index.php')) ?>">
        <button class="btn btn-sm btn-outline-theme"><i class="bi bi-speedometer2"></i> Manage</button></form>
      <a class="btn btn-sm btn-outline-theme" title="Edit" href="<?= e(url('admin/' . $self . '?action=edit&id=' . $p['id'])) ?>"><i class="bi bi-pencil"></i></a>
      <a class="btn btn-sm btn-outline-theme" title="View" href="<?= e(player_url_by_id((int)$p['id'])) ?>" target="_blank" rel="noopener"><i class="bi bi-eye"></i></a>
      <form method="post" data-confirm="Delete <?= e($p['full_name']) ?> and ALL his match records, goals, assists and trophies? This cannot be undone."><?= csrf_field() ?>
        <input type="hidden" name="op" value="delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button></form>
    </div>
  </div>
<?php endforeach; ?>
</div>
<?php endif;
require ROOT_PATH . '/includes/admin_footer.php';
