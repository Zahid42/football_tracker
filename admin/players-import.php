<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
$admin = require_login();

if (isset($_GET['sample'])) {
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="players-sample.txt"');
    echo "# Name, Position, Jersey, Nickname, Date of birth\n# Only the name is required. Lines starting with # are ignored.\n# You can also group players under headings: a category (Goalkeeper, Defender, Midfielder, Forward)\n# and then a position (CB, LB, RB ...). Players below a heading get that position.\n\nGoalkeeper\nKarim Rahman, 1, Karim, 2001-04-12\n\nDefender\nCB\nSohel Rana, 4\nImran Hossain, 5\nLB\nTanvir Ahmed, 3\nRB\nRafiq Islam, 2\n\nMidfielder\nCM\nNayeem Uddin, 8\n\nForward\nST\nJahid Hasan, 9\n";
    exit;
}

/** Categories and the position a player gets when only the category is given. */
const IMPORT_CATEGORIES = [
    'goalkeeper' => ['Goalkeeper', 'GK'], 'goalkeepers' => ['Goalkeeper', 'GK'], 'keeper' => ['Goalkeeper', 'GK'], 'keepers' => ['Goalkeeper', 'GK'], 'gk' => ['Goalkeeper', 'GK'],
    'defender' => ['Defender', 'CB'], 'defenders' => ['Defender', 'CB'], 'defence' => ['Defender', 'CB'], 'defense' => ['Defender', 'CB'], 'back' => ['Defender', 'CB'], 'backs' => ['Defender', 'CB'],
    'midfielder' => ['Midfielder', 'CM'], 'midfielders' => ['Midfielder', 'CM'], 'midfield' => ['Midfielder', 'CM'],
    'forward' => ['Forward', 'ST'], 'forwards' => ['Forward', 'ST'], 'attacker' => ['Forward', 'ST'], 'attackers' => ['Forward', 'ST'], 'attack' => ['Forward', 'ST'],
];
const IMPORT_POS_CATEGORY = ['GK' => 'Goalkeeper', 'CB' => 'Defender', 'LB' => 'Defender', 'RB' => 'Defender', 'CDM' => 'Midfielder', 'CM' => 'Midfielder', 'CAM' => 'Midfielder', 'LM' => 'Midfielder', 'RM' => 'Midfielder', 'LW' => 'Forward', 'RW' => 'Forward', 'ST' => 'Forward', 'CF' => 'Forward'];

/** Exact position (code or name or a few synonyms); '' for empty, null for unknown. "Defender" alone means CB. */
function import_position(string $s, bool $compound = true): ?string
{
    $s = mb_strtolower(trim($s)); if ($s === '') { return ''; }
    foreach (POSITIONS as $c) { if (mb_strtolower($c) === $s) { return $c; } }
    foreach (POSITION_NAMES as $c => $n) { if (mb_strtolower($n) === $s) { return $c; } }
    $syn = ['keeper' => 'GK', 'goalie' => 'GK', 'goalkeeper' => 'GK', 'defender' => 'CB', 'centre back' => 'CB', 'center back' => 'CB', 'centre-back' => 'CB', 'center-back' => 'CB', 'central defender' => 'CB',
            'left back' => 'LB', 'right back' => 'RB', 'fullback' => 'LB', 'full back' => 'LB', 'midfielder' => 'CM', 'midfield' => 'CM', 'defensive midfielder' => 'CDM', 'attacking midfielder' => 'CAM',
            'forward' => 'ST', 'attacker' => 'ST', 'striker' => 'ST', 'winger' => 'LW', 'center forward' => 'CF', 'centre forward' => 'CF'];
    if (isset($syn[$s])) { return $syn[$s]; }
    if (!$compound) { return null; }
    // "Defender / LB", "Defender - Right Back", "Defender (CB)": the most specific part wins
    $parts = array_values(array_filter(array_map('trim', preg_split('~[/()\\\\|]|\s+-\s+|\s*:\s*~u', $s)), fn($x) => $x !== ''));
    if (count($parts) > 1) { for ($i = count($parts) - 1; $i >= 0; $i--) { $p = import_position($parts[$i]); if ($p) { return $p; } } }
    return null;
}

/** A heading line ("Defender", "CB:", "[Left Back]", "## Midfielders"): ['cat'=>..,'pos'=>..] or null for an ordinary line. */
function import_heading(string $line): ?array
{
    $t = trim(preg_replace('/^(#+|=+)\s*|\s*(=+|:|-+)$/u', '', $line));
    $t = trim($t, "[] \t*");
    if ($t === '' || mb_strlen($t) > 30) { return null; }
    $k = mb_strtolower($t);
    if (isset(IMPORT_CATEGORIES[$k])) { return ['cat' => IMPORT_CATEGORIES[$k][0], 'pos' => null, 'def' => IMPORT_CATEGORIES[$k][1]]; }
    $p = import_position($t, false);
    if ($p) { return ['cat' => IMPORT_POS_CATEGORY[$p] ?? null, 'pos' => $p, 'def' => $p]; }
    return null;
}

function import_date(string $s): ?string
{
    $s = trim($s); if ($s === '') { return ''; }
    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $s, $m)) { [$y, $mo, $d] = [$m[1], $m[2], $m[3]]; }
    elseif (preg_match('/^(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{4})$/', $s, $m)) { [$d, $mo, $y] = [$m[1], $m[2], $m[3]]; }
    else { return null; }
    return checkdate((int)$mo, (int)$d, (int)$y) && (int)$y > 1950 && sprintf('%04d-%02d-%02d', $y, $mo, $d) <= date('Y-m-d') ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : null;
}

function import_parse(string $text): array
{
    if (str_starts_with($text, "\xEF\xBB\xBF")) { $text = substr($text, 3); }
    if (!mb_check_encoding($text, 'UTF-8')) { $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252'); }
    $rows = []; $seen = []; $cat = null; $secPos = null; $secDef = null;
    $exists = db()->prepare('SELECT COUNT(*) FROM players WHERE LOWER(full_name) = LOWER(?) AND (date_of_birth <=> ? OR ? IS NULL OR date_of_birth IS NULL)');
    foreach (preg_split('/\r\n|\r|\n/', $text) as $i => $line) {
        $line = trim($line);
        if ($line === '') { continue; }
        $h = import_heading($line);
        if ($h) { $cat = $h['cat']; $secPos = $h['pos']; $secDef = $h['def']; continue; }   // section heading: players below get this position
        if ($line[0] === '#') { continue; }
        if (count($rows) >= 500) { $rows[] = ['line' => $i + 1, 'raw' => $line, 'status' => 'skip', 'msg' => 'Limit of 500 players per import reached.']; break; }
        $delim = null; $best = PHP_INT_MAX;
        foreach (["\t", '|', ';', ','] as $d) { $p = strpos($line, $d); if ($p !== false && $p < $best) { $best = $p; $delim = $d; } }
        $c = array_map('trim', $delim ? explode($delim, $line) : [$line]);
        if (!$rows && in_array(mb_strtolower($c[0]), ['name', 'full name', 'player', 'player name', 'fullname'], true)) { continue; }   // header row
        $name = trim(preg_replace('/\s+/u', ' ', preg_replace('/[\x00-\x1F\x7F]/u', '', $c[0])));
        $r = ['line' => $i + 1, 'name' => $name, 'pos' => '', 'jersey' => null, 'nick' => '', 'dob' => null, 'status' => 'ok', 'msg' => []];
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) { $r['status'] = 'skip'; $r['msg'][] = 'Name must be 2-100 characters.'; $rows[] = $r + ['raw' => $line]; continue; }
        if ($secDef !== null && isset($c[1]) && ctype_digit($c[1])) { array_splice($c, 1, 0, ['']); }   // under a heading the position may be left out: "Name, 4"
        if (!isset($c[1]) || $c[1] === '') { $r['pos'] = $secPos ?? $secDef ?? ''; }
        if (isset($c[1]) && $c[1] !== '') { $p = import_position($c[1]); if ($p === null) { $r['msg'][] = '"' . $c[1] . '" is not a known position (left empty).'; } else { $r['pos'] = $p; } }
        if (isset($c[2]) && $c[2] !== '') { if (ctype_digit($c[2]) && (int)$c[2] >= 0 && (int)$c[2] <= 99) { $r['jersey'] = (int)$c[2]; } else { $r['msg'][] = 'Jersey "' . $c[2] . '" ignored (use 0-99).'; } }
        if (isset($c[3])) { $r['nick'] = mb_substr($c[3], 0, 60); }
        if (isset($c[4]) && $c[4] !== '') { $d = import_date($c[4]); if ($d === null) { $r['msg'][] = 'Date of birth "' . $c[4] . '" ignored (use YYYY-MM-DD).'; } elseif ($d !== '') { $r['dob'] = $d; } }
        $key = mb_strtolower($name) . '|' . ($r['dob'] ?? '');
        $exists->execute([$name, $r['dob'], $r['dob']]);
        if (isset($seen[$key])) { $r['status'] = 'skip'; $r['msg'][] = 'Repeated in this list.'; }
        elseif ((int)$exists->fetchColumn()) { $r['status'] = 'skip'; $r['msg'][] = 'A player with this name already exists.'; }
        $seen[$key] = 1;
        $r['raw'] = $line; $rows[] = $r;
    }
    return $rows;
}

$teamOptions = lookup('SELECT id, name FROM teams ORDER BY name');
$preview = null;
if (is_post()) {
    csrf_require();
    $op = post('op');
    if ($op === 'preview') {
        $text = trim((string)($_POST['list'] ?? ''));
        if (!empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
            $f = $_FILES['file'];
            if ($f['size'] > 512 * 1024) { flash_set('danger', 'The file is too large (max 512 KB).'); redirect('admin/players-import.php'); }
            if (!preg_match('/\.(txt|csv|tsv|list)$/i', (string)$f['name'])) { flash_set('danger', 'Please upload a .txt or .csv file.'); redirect('admin/players-import.php'); }
            $text = (string)file_get_contents($f['tmp_name']);
        }
        if (trim($text) === '') { flash_set('danger', 'Choose a text file or paste a list of players.'); redirect('admin/players-import.php'); }
        $rows = import_parse($text);
        $_SESSION['pl_import'] = ['rows' => $rows, 'team' => (int)post('team_id')];
        redirect('admin/players-import.php?step=preview');
    }
    if ($op === 'confirm') {
        $imp = $_SESSION['pl_import'] ?? null; unset($_SESSION['pl_import']);
        if (!$imp) { flash_set('danger', 'The preview expired. Upload the list again.'); redirect('admin/players-import.php'); }
        $team = $imp['team'] && isset($teamOptions[$imp['team']]) ? (int)$imp['team'] : 0;
        $pdo = db(); $n = 0;
        $ins = $pdo->prepare("INSERT INTO players (full_name, nickname, date_of_birth, preferred_position, jersey_number, current_team_id, is_active, approval_status, public_code) VALUES (?,?,?,?,?,?,1,'approved',?)");
        $lnk = $pdo->prepare('INSERT INTO player_teams (player_id, team_id, is_current) VALUES (?, ?, 1)');
        foreach ($imp['rows'] as $r) {
            if ($r['status'] !== 'ok') { continue; }
            $ins->execute([$r['name'], $r['nick'] !== '' ? $r['nick'] : null, $r['dob'], $r['pos'] !== '' ? $r['pos'] : null, $r['jersey'], $team ?: null, ft_new_code()]);
            if ($team) { $lnk->execute([(int)$pdo->lastInsertId(), $team]); }
            $n++;
        }
        flash_set('success', $n . ' player' . ($n === 1 ? '' : 's') . ' added to the player list.');
        redirect('admin/players.php');
    }
}

$pageTitle = 'Import players';
require ROOT_PATH . '/includes/admin_header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <h1 class="page-title h3 mb-0"><i class="bi bi-file-earmark-arrow-up"></i> Import players from a list</h1>
  <a class="btn btn-outline-theme" href="<?= e(url('admin/players.php')) ?>"><i class="bi bi-arrow-left"></i> Players</a>
</div>
<?php if (($_GET['step'] ?? '') === 'preview' && !empty($_SESSION['pl_import'])): $imp = $_SESSION['pl_import']; $ok = count(array_filter($imp['rows'], fn($r) => $r['status'] === 'ok')); ?>
  <div class="ft-card p-3 mb-3"><b><?= $ok ?></b> of <?= count($imp['rows']) ?> lines will be added<?= $imp['team'] && isset($teamOptions[$imp['team']]) ? ' to team <b>' . e($teamOptions[$imp['team']]) . '</b>' : '' ?>. Lines marked "Skipped" are not imported. Nothing is saved until you confirm.</div>
  <div class="table-responsive"><table class="table ft-table align-middle"><thead><tr><th>#</th><th>Name</th><th>Position</th><th>Jersey</th><th>Nickname</th><th>Born</th><th>Result</th></tr></thead><tbody>
  <?php foreach ($imp['rows'] as $r): ?>
    <tr class="<?= $r['status'] === 'skip' ? 'text-muted-ft' : '' ?>"><td><?= (int)$r['line'] ?></td>
    <?php if (!isset($r['name'])): ?><td colspan="5"><?= e($r['raw']) ?></td>
    <?php else: ?><td class="fw-semibold"><?= e($r['name']) ?></td><td><?= e($r['pos'] ? position_name($r['pos']) : '-') ?></td><td><?= $r['jersey'] !== null ? '#' . (int)$r['jersey'] : '-' ?></td><td><?= e($r['nick'] ?: '-') ?></td><td><?= e($r['dob'] ?? '-') ?></td><?php endif; ?>
    <td><?= $r['status'] === 'ok' ? '<span class="badge text-bg-success">Add</span>' : '<span class="badge text-bg-secondary">Skipped</span>' ?><?php if ($r['msg']): ?><div class="small"><?= e(is_array($r['msg']) ? implode(' ', $r['msg']) : $r['msg']) ?></div><?php endif; ?></td></tr>
  <?php endforeach; ?></tbody></table></div>
  <form method="post" class="d-flex gap-2"><?= csrf_field() ?>
    <button class="btn btn-accent" name="op" value="confirm" <?= $ok ? '' : 'disabled' ?>><i class="bi bi-check-lg"></i> Add <?= $ok ?> player<?= $ok === 1 ? '' : 's' ?></button>
    <a class="btn btn-outline-theme" href="<?= e(url('admin/players-import.php')) ?>">Cancel</a></form>
<?php else: ?>
  <p class="text-muted-ft">Upload a text file (or paste a list) with <b>one player per line</b>. Imported players are added to the player list straight away and get their own 6-digit ID. Everything else (photo, biography, height...) can be edited later from the player list.</p>
  <div class="row g-3"><div class="col-lg-7">
  <form method="post" enctype="multipart/form-data" class="ft-card p-4"><?= csrf_field() ?><input type="hidden" name="op" value="preview">
    <div class="mb-3"><label class="form-label" for="file">Text file (.txt or .csv)</label><input class="form-control" type="file" id="file" name="file" accept=".txt,.csv,.tsv,text/plain,text/csv"></div>
    <div class="mb-3"><label class="form-label" for="list">...or paste the list here</label><textarea class="form-control" id="list" name="list" rows="7" placeholder="Karim Rahman, GK, 1&#10;Tanvir Ahmed, Striker, 9"></textarea></div>
    <div class="mb-3"><label class="form-label" for="team_id">Add all of them to a team (optional)</label><select class="form-select" id="team_id" name="team_id"><?= sel_opts($teamOptions, '', '- no team -') ?></select></div>
    <button class="btn btn-accent"><i class="bi bi-eye"></i> Preview</button>
  </form></div>
  <div class="col-lg-5"><div class="ft-card p-4"><h2 class="h6 section-title">File format</h2>
    <p class="small mb-2">Each line: <code>Name, Position, Jersey, Nickname, Date of birth</code></p>
    <ul class="small mb-2"><li>Only the <b>name</b> is required; add the others in this order if you have them.</li><li>Separate with commas, tabs, semicolons or <code>|</code>.</li><li>Position: <code>GK</code>, <code>CB</code>, <code>LB</code>, <code>ST</code>... or a word like Defender, Striker, Midfielder (<code>Defender / LB</code> also works).</li><li><b>Headings:</b> put a category line (<code>Defender</code>) and then a position line (<code>CB</code>, <code>LB</code>, <code>RB</code>...). Every player below gets that position, so you can leave the position out.</li><li>Date: <code>2001-04-12</code> or <code>12/04/2001</code>.</li><li>Players with a name already in the list are skipped. Up to 500 per import.</li></ul>
    <pre class="small mb-2" style="white-space:pre-wrap">Goalkeeper
Karim Rahman, 1, Karim, 2001-04-12

Defender
CB
Sohel Rana, 4
Imran Hossain, 5
LB
Tanvir Ahmed, 3
RB
Rafiq Islam, 2

Midfielder
Nayeem Uddin, CM, 8</pre>
    <a class="btn btn-sm btn-outline-theme" href="<?= e(url('admin/players-import.php?sample=1')) ?>"><i class="bi bi-download"></i> Download a sample file</a></div></div></div>
<?php endif;
require ROOT_PATH . '/includes/admin_footer.php';
