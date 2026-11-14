<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
$pageTitle  = 'Player Registration';
$activePage = 'register.php';

$fields = [
    ['name' => 'full_name', 'label' => 'Full Name', 'required' => true, 'max' => 120],
    ['name' => 'nickname', 'label' => 'Nickname', 'max' => 60],
    ['name' => 'email', 'label' => 'Your E-mail', 'required' => true, 'max' => 120, 'help' => 'Only the admin sees this.'],
    ['name' => 'phone', 'label' => 'Phone (optional)', 'max' => 30],
    ['name' => 'date_of_birth', 'label' => 'Date of Birth', 'type' => 'date'],
    ['name' => 'preferred_position', 'label' => 'Preferred Position', 'type' => 'select', 'nullable' => true, 'options' => POSITIONS],
    ['name' => 'strong_foot', 'label' => 'Strong Foot', 'type' => 'select', 'nullable' => true, 'options' => ['Right', 'Left', 'Both']],
    ['name' => 'jersey_number', 'label' => 'Preferred Jersey Number', 'type' => 'number', 'min' => 0, 'max' => 99],
    ['name' => 'height_cm', 'label' => 'Height (cm)', 'type' => 'number', 'min' => 100, 'max' => 250],
    ['name' => 'weight_kg', 'label' => 'Weight (kg)', 'type' => 'number', 'min' => 30, 'max' => 200, 'step' => '0.1'],
    ['name' => 'photo', 'label' => 'Photo', 'type' => 'image', 'dir' => 'profile', 'col' => 12],
    ['name' => 'bio', 'label' => 'About you', 'type' => 'textarea', 'rows' => 4, 'max' => 1500],
];
$open = setting('registration_open', '1') === '1';
$errors = []; $done = false; $vals = [];

if ($open && is_post()) {
    csrf_require();
    $vals = $_POST;
    if (post('website') !== '') { $done = true; }                                   // honeypot: pretend success
    elseif (isset($_SESSION['reg_last']) && time() - (int)$_SESSION['reg_last'] < 60) { $errors[] = 'Please wait a minute before sending another request.'; }
    elseif ((int)db()->query("SELECT COUNT(*) FROM players WHERE approval_status='pending'")->fetchColumn() >= 100) { $errors[] = 'Registrations are temporarily full. Please try again later.'; }
    else {
        [$data, $errors, $uploaded] = crud_collect($fields);
        if (!$errors && !filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL)) { $errors[] = 'Please enter a valid e-mail address.'; }
        if ($errors) { foreach ($uploaded as $u) { delete_upload($u); } }
        else {
            $data['approval_status'] = 'pending'; $data['is_active'] = 0; $data['is_featured'] = 0; $data['registered_at'] = date('Y-m-d H:i:s');
            $cols = implode(', ', array_map(fn($k) => "`$k`", array_keys($data)));
            db()->prepare("INSERT INTO players ($cols) VALUES (" . implode(',', array_fill(0, count($data), '?')) . ')')->execute(array_values($data));
            $_SESSION['reg_last'] = time();
            $done = true;
        }
    }
}
require __DIR__ . '/includes/header.php';
?>
<div class="container py-4" style="max-width:820px">
  <h1 class="h2"><i class="bi bi-person-plus"></i> Player Registration</h1>
  <?php if (!$open): ?>
    <div class="alert alert-warning">Registration is closed at the moment.</div>
  <?php elseif ($done): ?>
    <div class="alert alert-success"><b>Thank you!</b> Your request was sent. The admin will review it, and your profile appears on the site once it is approved.</div>
    <a class="btn btn-accent" href="<?= e(url('index.php')) ?>">Back to home</a>
  <?php else: ?>
    <p class="text-muted-ft">Want to be listed on the site? Fill in your details. The admin reviews every request before anything is published.</p>
    <?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
    <form method="post" enctype="multipart/form-data" class="ft-card p-4">
      <?= csrf_field() ?>
      <div style="position:absolute;left:-9999px" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>
      <?= crud_render_fields($fields, $vals) ?>
      <button class="btn btn-accent btn-lg mt-4" type="submit"><i class="bi bi-send"></i> Send registration</button>
    </form>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php';
