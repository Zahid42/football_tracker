<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
$pageTitle  = 'My account';
$activePage = 'account.php';
$m = current_member();
if (!$m) { redirect('signin.php'); }
$pid = (int)$m['player_id'];
$fields = [
    ['name' => 'full_name', 'label' => 'Full Name', 'required' => true, 'max' => 120],
    ['name' => 'nickname', 'label' => 'Nickname', 'max' => 60],
    ['name' => 'phone', 'label' => 'Phone (optional)', 'max' => 30],
    ['name' => 'date_of_birth', 'label' => 'Date of Birth', 'type' => 'date'],
    ['name' => 'preferred_position', 'label' => 'Preferred Position', 'type' => 'select', 'nullable' => true, 'options' => POSITIONS],
    ['name' => 'strong_foot', 'label' => 'Strong Foot', 'type' => 'select', 'nullable' => true, 'options' => ['Right', 'Left', 'Both']],
    ['name' => 'jersey_number', 'label' => 'Jersey Number', 'type' => 'number', 'min' => 0, 'max' => 99],
    ['name' => 'height_cm', 'label' => 'Height (cm)', 'type' => 'number', 'min' => 100, 'max' => 250],
    ['name' => 'weight_kg', 'label' => 'Weight (kg)', 'type' => 'number', 'min' => 30, 'max' => 200, 'step' => '0.1'],
    ['name' => 'photo', 'label' => 'Photo', 'type' => 'image', 'dir' => 'profile', 'col' => 12],
    ['name' => 'bio', 'label' => 'About you', 'type' => 'textarea', 'rows' => 4, 'max' => 1500],
];
$errors = [];
$cur = [];
if ($pid) { $q = db()->prepare('SELECT * FROM players WHERE id = ?'); $q->execute([$pid]); $cur = $q->fetch() ?: []; }

if (is_post()) {
    csrf_require();
    $op = post('op');
    if ($op === 'signout') { unset($_SESSION['member_id']); session_regenerate_id(true); redirect('index.php'); }
    if ($op === 'password') {
        $old = (string)($_POST['old'] ?? ''); $new = (string)($_POST['new'] ?? '');
        if (!password_verify($old, (string)db()->query('SELECT password_hash FROM members WHERE id = ' . (int)$m['id'])->fetchColumn())) { $errors[] = 'Current password is wrong.'; }
        elseif ($e = ft_password_error($new)) { $errors[] = $e; }
        else { db()->prepare('UPDATE members SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $m['id']]); flash_set('success', 'Password changed.'); redirect('account.php'); }
    }
    if ($op === 'profile' && $pid) {
        [$data, $errors, $uploaded] = crud_collect($fields, $cur);
        if ($errors) { foreach ($uploaded as $u) { delete_upload($u); } }
        else {
            $sets = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($data)));
            db()->prepare("UPDATE players SET $sets WHERE id = ?")->execute([...array_values($data), $pid]);
            if (!empty($data['photo']) && !empty($cur['photo']) && $data['photo'] !== $cur['photo']) { delete_upload($cur['photo']); }
            flash_set('success', 'Profile saved.'); redirect('account.php');
        }
    }
}
$status = $m['approval_status'] ?? 'pending';
require __DIR__ . '/includes/header.php';
?>
<div class="container py-4" style="max-width:820px">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h2 mb-0"><i class="bi bi-person-circle"></i> My account</h1>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="op" value="signout"><button class="btn btn-outline-theme btn-sm"><i class="bi bi-box-arrow-right"></i> Sign out</button></form>
  </div>
  <?= flash_render() ?>
  <?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
  <div class="ft-card p-3 mb-3 d-flex flex-wrap align-items-center gap-3">
    <div><div class="text-muted-ft small">Signed in as</div><b><?= e($m['email']) ?></b></div>
    <div class="ms-auto"><?php if ($status === 'approved'): ?><span class="badge bg-success">Approved</span> <a class="btn btn-sm btn-accent" href="<?= e(url('index.php?player=' . (int)$pid)) ?>">View my page</a>
      <?php elseif ($status === 'rejected'): ?><span class="badge bg-danger">Not approved</span>
      <?php else: ?><span class="badge bg-warning text-dark">Waiting for admin approval</span><?php endif; ?></div>
  </div>
  <?php if ($pid): ?>
  <form method="post" enctype="multipart/form-data" class="ft-card p-4 mb-3">
    <?= csrf_field() ?><input type="hidden" name="op" value="profile">
    <h2 class="h5 mb-3">Your player profile</h2>
    <?= crud_render_fields($fields, $cur) ?>
    <button class="btn btn-accent mt-3" type="submit"><i class="bi bi-check2"></i> Save profile</button>
  </form>
  <?php endif; ?>
  <form method="post" class="ft-card p-4">
    <?= csrf_field() ?><input type="hidden" name="op" value="password">
    <h2 class="h5 mb-3">Change password</h2>
    <div class="row g-3"><div class="col-md-6"><label class="form-label">Current password</label><input class="form-control" type="password" name="old" required autocomplete="current-password"></div>
    <div class="col-md-6"><label class="form-label">New password</label><input class="form-control" type="password" name="new" required minlength="8" autocomplete="new-password"></div></div>
    <button class="btn btn-outline-theme mt-3" type="submit">Change password</button>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php';
