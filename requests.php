<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
$pageTitle  = 'My requests';
$activePage = 'account.php';
$m = current_member();
if (!$m) { redirect('signin.php'); }
$mid = (int)$m['id'];
$errors = []; $v = ['kind' => get_str('kind') ?: 'player'];
$teamOpts = lookup("SELECT id, name FROM teams WHERE status = 'active' ORDER BY name");
$seasonOpts = lookup('SELECT id, name FROM seasons ORDER BY name DESC');
const REQ_MAX_PENDING = 5;

if (is_post()) {
    csrf_require();
    $op = post('op');
    if ($op === 'cancel') {
        db()->prepare("DELETE FROM member_requests WHERE id = ? AND member_id = ? AND status = 'pending'")->execute([(int)post('id'), $mid]);
        flash_set('success', 'Request withdrawn.'); redirect('requests.php');
    }
    if ($op === 'new') {
        $kind = post('kind'); $v = ['kind' => $kind] + $_POST;
        $title = trim(post('title'));
        if (!isset(MEMBER_REQUEST_KINDS[$kind])) { $errors[] = 'Choose what you want to add.'; }
        if ($title === '' || mb_strlen($title) > 150) { $errors[] = 'Please enter a name (up to 150 characters).'; }
        if (post('website') !== '') { redirect('index.php'); }   // honeypot
        $pending = (int)db()->query("SELECT COUNT(*) FROM member_requests WHERE member_id = $mid AND status = 'pending'")->fetchColumn();
        if ($pending >= REQ_MAX_PENDING) { $errors[] = 'You already have ' . REQ_MAX_PENDING . ' requests waiting. Please wait for the admin to answer them first.'; }
        if (isset($_SESSION['req_last']) && time() - (int)$_SESSION['req_last'] < 10) { $errors[] = 'Please wait a few seconds before sending another request.'; }
        $d = ['note' => mb_substr(trim(post('note')), 0, 500)];
        if (!$errors) {
            if ($kind === 'player') {
                $pos = post('position'); $d += ['position' => in_array($pos, POSITIONS, true) ? $pos : '', 'jersey' => post('jersey') !== '' ? (string)max(0, min(99, (int)post('jersey'))) : '', 'dob' => '', 'team_id' => isset($teamOpts[(int)post('team_id')]) ? (int)post('team_id') : 0];
                if (post('dob') !== '') { $dt = DateTime::createFromFormat('Y-m-d', post('dob')); if ($dt && $dt->format('Y-m-d') === post('dob') && post('dob') < date('Y-m-d')) { $d['dob'] = post('dob'); } else { $errors[] = 'The date of birth is not valid.'; } }
                $ex = db()->prepare("SELECT public_code FROM players WHERE approval_status = 'approved' AND LOWER(TRIM(full_name)) = ? LIMIT 1"); $ex->execute([ft_name_key($title)]);
                if ($code = $ex->fetchColumn()) { $errors[] = 'A player with this name is already on the site (ID ' . $code . '). If it is you, sign in with that ID; if it is someone else, add a middle name or a detail to the name.'; }
            } elseif ($kind === 'team') {
                $d += ['short_name' => mb_substr(trim(post('short_name')), 0, 10), 'founded' => post('founded') !== '' ? (string)max(1850, min(2100, (int)post('founded'))) : ''];
                $ex = db()->prepare('SELECT COUNT(*) FROM teams WHERE LOWER(name) = LOWER(?)'); $ex->execute([$title]);
                if ((int)$ex->fetchColumn()) { $errors[] = 'A team with this name is already on the site.'; }
            } else {
                $d += ['type' => in_array(post('type'), ['Tournament', 'League', 'Friendly', 'Training', 'Other'], true) ? post('type') : 'Tournament', 'format' => post('format') === 'Groups' ? 'Groups' : 'League', 'season_id' => isset($seasonOpts[(int)post('season_id')]) ? (int)post('season_id') : 0, 'organizer' => mb_substr(trim(post('organizer')), 0, 150)];
                $ex = db()->prepare('SELECT COUNT(*) FROM competitions WHERE LOWER(name) = LOWER(?)'); $ex->execute([$title]);
                if ((int)$ex->fetchColumn()) { $errors[] = 'A competition with this name is already on the site.'; }
            }
            $ex = db()->prepare("SELECT COUNT(*) FROM member_requests WHERE kind = ? AND status = 'pending' AND LOWER(title) = LOWER(?)"); $ex->execute([$kind, $title]);
            if ((int)$ex->fetchColumn()) { $errors[] = 'A request for this ' . strtolower(MEMBER_REQUEST_KINDS[$kind] ?? '') . ' is already waiting for the admin.'; }
        }
        if (!$errors) {
            db()->prepare('INSERT INTO member_requests (member_id, kind, title, details, created_at) VALUES (?,?,?,?,NOW())')->execute([$mid, $kind, $title, json_encode($d, JSON_UNESCAPED_UNICODE)]);
            $_SESSION['req_last'] = time();
            flash_set('success', 'Request sent. The admin will look at it; you will see the answer here' . (ft_mail_configured() ? ' and get an e-mail.' : '.'));
            redirect('requests.php');
        }
    }
}
$mine = db()->prepare('SELECT * FROM member_requests WHERE member_id = ? ORDER BY id DESC LIMIT 100'); $mine->execute([$mid]); $mine = $mine->fetchAll();
$val = fn(string $k, string $d = '') => e((string)($v[$k] ?? $d));
require __DIR__ . '/includes/header.php';
?>
<div class="container py-4" style="max-width:820px">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h2 mb-0"><i class="bi bi-send-plus"></i> Requests</h1>
    <a class="btn btn-outline-theme btn-sm" href="<?= e(url('account.php')) ?>"><i class="bi bi-arrow-left"></i> My account</a>
  </div>
  <?= flash_render() ?>
  <?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
  <form method="post" class="ft-card p-4 mb-4" id="reqForm"><?= csrf_field() ?><input type="hidden" name="op" value="new">
    <div style="position:absolute;left:-9999px" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>
    <h2 class="h5 mb-1">Ask the admin to add something</h2>
    <p class="text-muted-ft small">Is a player, team or competition missing? Send a request. When the admin approves it, it appears on the site.</p>
    <div class="row g-3">
      <div class="col-md-4"><label class="form-label" for="kind">What do you want to add?</label>
        <select class="form-select" id="kind" name="kind"><?php foreach (MEMBER_REQUEST_KINDS as $k => $lab): ?><option value="<?= e($k) ?>"<?= ($v['kind'] ?? '') === $k ? ' selected' : '' ?>><?= e($lab) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-8"><label class="form-label" for="title" id="titleLabel">Name</label><input class="form-control" id="title" name="title" required maxlength="150" value="<?= $val('title') ?>"></div>

      <div class="col-md-4 k-player"><label class="form-label">Position</label><select class="form-select" name="position"><option value="">-</option><?php foreach (POSITIONS as $p): ?><option value="<?= e($p) ?>"<?= ($v['position'] ?? '') === $p ? ' selected' : '' ?>><?= e(position_name($p)) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-4 k-player"><label class="form-label">Jersey number</label><input class="form-control" type="number" min="0" max="99" name="jersey" value="<?= $val('jersey') ?>"></div>
      <div class="col-md-4 k-player"><label class="form-label">Date of birth</label><input class="form-control" type="date" name="dob" max="<?= e(date('Y-m-d')) ?>" value="<?= $val('dob') ?>"></div>
      <div class="col-12 k-player"><label class="form-label">Team (optional)</label><select class="form-select" name="team_id"><?= sel_opts($teamOpts, (string)($v['team_id'] ?? ''), '- no team -') ?></select></div>

      <div class="col-md-6 k-team"><label class="form-label">Short name (up to 10 letters)</label><input class="form-control" name="short_name" maxlength="10" value="<?= $val('short_name') ?>"></div>
      <div class="col-md-6 k-team"><label class="form-label">Founded (year)</label><input class="form-control" type="number" min="1850" max="2100" name="founded" value="<?= $val('founded') ?>"></div>

      <div class="col-md-4 k-competition"><label class="form-label">Type</label><select class="form-select" name="type"><?php foreach (['Tournament', 'League', 'Friendly', 'Training', 'Other'] as $t): ?><option<?= ($v['type'] ?? '') === $t ? ' selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-4 k-competition"><label class="form-label">Table format</label><select class="form-select" name="format"><option>League</option><option<?= ($v['format'] ?? '') === 'Groups' ? ' selected' : '' ?>>Groups</option></select></div>
      <div class="col-md-4 k-competition"><label class="form-label">Season</label><select class="form-select" name="season_id"><?= sel_opts($seasonOpts, (string)($v['season_id'] ?? ''), '- not sure -') ?></select></div>
      <div class="col-12 k-competition"><label class="form-label">Organizer</label><input class="form-control" name="organizer" maxlength="150" value="<?= $val('organizer') ?>"></div>

      <div class="col-12"><label class="form-label">Anything the admin should know (optional)</label><textarea class="form-control" name="note" rows="2" maxlength="500"><?= $val('note') ?></textarea></div>
    </div>
    <button class="btn btn-accent mt-3" type="submit"><i class="bi bi-send"></i> Send request</button>
  </form>

  <h2 class="h5 mb-2">My requests</h2>
  <?php if (!$mine): ?><p class="text-muted-ft">You have not sent any request yet.</p><?php else: ?>
  <div class="row-list mb-4"><?php foreach ($mine as $r): ?>
    <div class="row-item">
      <span class="ri-main"><b><?= e($r['title']) ?> <small class="text-muted-ft">(<?= e(strtolower(MEMBER_REQUEST_KINDS[$r['kind']] ?? $r['kind'])) ?>)</small></b>
        <small>Sent <?= e(fmt_date(substr((string)$r['created_at'], 0, 10))) ?><?= $r['admin_reply'] ? ' &middot; Admin: ' . e($r['admin_reply']) : '' ?></small></span>
      <?php if ($r['status'] === 'pending'): ?>
        <span class="badge bg-warning text-dark">Waiting</span>
        <form method="post" onsubmit="return confirm('Withdraw this request?')"><?= csrf_field() ?><input type="hidden" name="op" value="cancel"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-danger" title="Withdraw"><i class="bi bi-x-lg"></i></button></form>
      <?php elseif ($r['status'] === 'approved'): ?><span class="badge bg-success">Approved</span>
      <?php else: ?><span class="badge bg-danger">Not approved</span><?php endif; ?>
    </div><?php endforeach; ?></div><?php endif; ?>
</div>
<script>(function(){var k=document.getElementById("kind"),lab=document.getElementById("titleLabel"),names={player:"Player's full name",team:"Team name",competition:"Competition name"};
function sync(){document.querySelectorAll("#reqForm [class*=k-]").forEach(function(el){var m=el.className.match(/k-(player|team|competition)/);el.hidden=!m||m[1]!==k.value});lab.textContent=names[k.value]||"Name"}
k.addEventListener("change",sync);sync();})();</script>
<?php require __DIR__ . '/includes/footer.php';
