<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
$admin = require_login();
ft_comp_ensure();

if (isset($_GET['sample'])) {
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="teams-sample.txt"');
    echo "# Team name, Group (optional - only for competitions with groups)\n# Lines starting with # are ignored.\nOffice Warriors, Group A\nRiver Hawks, Group A\nCity Strikers, Group B\nUnited FC\n";
    exit;
}

/** Parse the list and work out, for the chosen competition, what would happen to each line. */
function ctimp_parse(string $text, array $comp): array
{
    if (str_starts_with($text, "\xEF\xBB\xBF")) { $text = substr($text, 3); }
    if (!mb_check_encoding($text, 'UTF-8')) { $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252'); }
    $cid = (int)$comp['id']; $isGroups = ($comp['format'] ?? '') === 'Groups';
    $groups = comp_group_names($comp); $per = (int)$comp['teams_per_group']; $max = (int)$comp['max_teams'];
    $in = db()->prepare('SELECT team_name, group_name FROM comp_teams WHERE competition_id = ?'); $in->execute([$cid]);
    $already = []; $gcount = []; $total = 0;
    foreach ($in->fetchAll() as $r) { $already[mb_strtolower($r['team_name'])] = 1; $total++; if ($r['group_name'] !== null) { $gcount[$r['group_name']] = ($gcount[$r['group_name']] ?? 0) + 1; } }
    $known = [];   // lower-case name => canonical name already in the overall list
    foreach (db()->query('SELECT name FROM teams ORDER BY id DESC')->fetchAll(PDO::FETCH_COLUMN) as $n) { $known[mb_strtolower($n)] = $n; }
    $rows = []; $seen = [];
    foreach (preg_split('/\r\n|\r|\n/', $text) as $i => $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') { continue; }
        $delim = null; $best = PHP_INT_MAX;
        foreach (["\t", '|', ';', ','] as $d) { $p = strpos($line, $d); if ($p !== false && $p < $best) { $best = $p; $delim = $d; } }
        $c = array_map('trim', $delim ? explode($delim, $line) : [$line]);
        if (!$rows && in_array(mb_strtolower($c[0]), ['team', 'team name', 'name', 'teams'], true)) { continue; }   // header row
        $name = trim(preg_replace('/\s+/u', ' ', preg_replace('/[\x00-\x1F\x7F]/u', '', $c[0])));
        $r = ['line' => $i + 1, 'name' => $name, 'group' => null, 'new' => false, 'status' => 'ok', 'msg' => []];
        $skip = function (string $m) use (&$r) { $r['status'] = 'skip'; $r['msg'][] = $m; };
        if (count($rows) >= 200) { $r['name'] = $line; $skip('Limit of 200 teams per import reached.'); $rows[] = $r; break; }
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) { $skip('Team name must be 2-100 characters.'); $rows[] = $r; continue; }
        $low = mb_strtolower($name);
        if (isset($known[$low])) { $r['name'] = $known[$low]; } else { $r['new'] = true; }
        if (isset($seen[$low])) { $skip('Repeated in this list.'); }
        elseif (isset($already[$low])) { $skip('Already in this competition.'); }
        elseif ($max && $total >= $max) { $skip('Competition is full (' . $max . ' teams).'); }
        else {
            if ($isGroups) {
                $g = $c[1] ?? '';
                if ($g !== '') {
                    $match = null;
                    foreach ($groups as $gn) { if (mb_strtolower($gn) === mb_strtolower($g) || mb_strtolower(substr($gn, 6)) === mb_strtolower($g)) { $match = $gn; } }
                    if ($match === null) { $skip('"' . $g . '" is not a group of this competition (' . implode(', ', $groups) . ').'); }
                    elseif (($gcount[$match] ?? 0) >= $per) { $skip($match . ' is full (' . $per . ' teams).'); }
                    else { $r['group'] = $match; }
                } else {
                    foreach ($groups as $gn) { if (($gcount[$gn] ?? 0) < $per) { $r['group'] = $gn; $r['msg'][] = 'Placed in ' . $gn . ' automatically.'; break; } }
                    if ($r['group'] === null) { $skip('All groups are full.'); }
                }
            }
            if ($r['status'] === 'ok') { $total++; if ($r['group'] !== null) { $gcount[$r['group']] = ($gcount[$r['group']] ?? 0) + 1; } $already[$low] = 1; }
        }
        $seen[$low] = 1;
        if ($r['status'] === 'ok' && $r['new']) { $known[$low] = $name; }
        $rows[] = $r;
    }
    return $rows;
}

$comps = lookup('SELECT id, name FROM competitions ORDER BY name');
if (!$comps) { flash_set('danger', 'Create a competition first.'); redirect('admin/competitions.php'); }
$cid = admin_comp_id();
if (is_post()) {
    csrf_require();
    $op = post('op');
    if ($op === 'preview') {
        $cid = (int)post('comp'); if (!isset($comps[$cid])) { flash_set('danger', 'Choose a competition.'); redirect('admin/comp-teams-import.php'); }
        $text = trim((string)($_POST['list'] ?? ''));
        if (!empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
            $f = $_FILES['file'];
            if ($f['size'] > 512 * 1024) { flash_set('danger', 'The file is too large (max 512 KB).'); redirect('admin/comp-teams-import.php'); }
            if (!preg_match('/\.(txt|csv|tsv|list)$/i', (string)$f['name'])) { flash_set('danger', 'Please upload a .txt or .csv file.'); redirect('admin/comp-teams-import.php'); }
            $text = (string)file_get_contents($f['tmp_name']);
        }
        if (trim($text) === '') { flash_set('danger', 'Choose a text file or paste a list of teams.'); redirect('admin/comp-teams-import.php'); }
        $_SESSION['ct_import'] = ['comp' => $cid, 'rows' => ctimp_parse($text, comp_row($cid))];
        redirect('admin/comp-teams-import.php?step=preview');
    }
    if ($op === 'confirm') {
        $imp = $_SESSION['ct_import'] ?? null; unset($_SESSION['ct_import']);
        if (!$imp || !isset($comps[(int)$imp['comp']])) { flash_set('danger', 'The preview expired. Upload the list again.'); redirect('admin/comp-teams-import.php'); }
        $cid = (int)$imp['comp']; $pdo = db(); $nt = 0; $nc = 0;
        $find = $pdo->prepare('SELECT COUNT(*) FROM teams WHERE LOWER(name) = LOWER(?)');
        $newT = $pdo->prepare("INSERT INTO teams (name, status, public_code) VALUES (?, 'active', ?)");
        $link = $pdo->prepare("INSERT IGNORE INTO comp_teams (competition_id, team_name, group_name, status, point_adjust) VALUES (?, ?, ?, 'active', 0)");
        foreach ($imp['rows'] as $r) {
            if ($r['status'] !== 'ok') { continue; }
            $find->execute([$r['name']]);
            if (!(int)$find->fetchColumn()) { $newT->execute([$r['name'], ft_new_code()]); $nt++; }   // joins the overall team list, not tied to any player
            $link->execute([$cid, $r['name'], $r['group']]);
            $nc += $link->rowCount();
        }
        flash_set('success', $nc . ' team' . ($nc === 1 ? '' : 's') . ' added to ' . $comps[$cid] . ($nt ? ' (' . $nt . ' new team' . ($nt === 1 ? '' : 's') . ' also added to the overall team list)' : '') . '.');
        redirect('admin/comp-teams.php?comp=' . $cid);
    }
}

$pageTitle = 'Import teams';
require ROOT_PATH . '/includes/admin_header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <h1 class="page-title h3 mb-0"><i class="bi bi-file-earmark-arrow-up"></i> Import teams from a list</h1>
  <a class="btn btn-outline-theme" href="<?= e(url('admin/comp-teams.php')) ?>"><i class="bi bi-arrow-left"></i> Competition teams</a>
</div>
<?php if (($_GET['step'] ?? '') === 'preview' && !empty($_SESSION['ct_import'])): $imp = $_SESSION['ct_import']; $ok = count(array_filter($imp['rows'], fn($r) => $r['status'] === 'ok')); $newc = count(array_filter($imp['rows'], fn($r) => $r['status'] === 'ok' && $r['new'])); ?>
  <div class="ft-card p-3 mb-3"><b><?= $ok ?></b> of <?= count($imp['rows']) ?> lines will join <b><?= e($comps[(int)$imp['comp']] ?? '') ?></b><?= $newc ? '; <b>' . $newc . '</b> of them are new and will also be added to the overall team list' : '' ?>. Nothing is saved until you confirm.</div>
  <div class="table-responsive"><table class="table ft-table align-middle"><thead><tr><th>#</th><th>Team</th><th>Group</th><th>Overall list</th><th>Result</th></tr></thead><tbody>
  <?php foreach ($imp['rows'] as $r): ?>
    <tr class="<?= $r['status'] === 'skip' ? 'text-muted-ft' : '' ?>"><td><?= (int)$r['line'] ?></td><td class="fw-semibold"><?= e($r['name']) ?></td><td><?= e($r['group'] ?? '-') ?></td>
    <td><?= $r['status'] === 'ok' ? ($r['new'] ? 'New team added' : 'Already listed') : '-' ?></td>
    <td><?= $r['status'] === 'ok' ? '<span class="badge text-bg-success">Add</span>' : '<span class="badge text-bg-secondary">Skipped</span>' ?><?php if ($r['msg']): ?><div class="small"><?= e(implode(' ', $r['msg'])) ?></div><?php endif; ?></td></tr>
  <?php endforeach; ?></tbody></table></div>
  <form method="post" class="d-flex gap-2"><?= csrf_field() ?>
    <button class="btn btn-accent" name="op" value="confirm" <?= $ok ? '' : 'disabled' ?>><i class="bi bi-check-lg"></i> Add <?= $ok ?> team<?= $ok === 1 ? '' : 's' ?></button>
    <a class="btn btn-outline-theme" href="<?= e(url('admin/comp-teams-import.php')) ?>">Cancel</a></form>
<?php else: ?>
  <p class="text-muted-ft">Upload a text file (or paste a list) with <b>one team per line</b>. The teams are entered into the competition you choose, and any team that is not yet in the overall team list is added there too (not linked to any player).</p>
  <div class="row g-3"><div class="col-lg-7">
  <form method="post" enctype="multipart/form-data" class="ft-card p-4"><?= csrf_field() ?><input type="hidden" name="op" value="preview">
    <div class="mb-3"><label class="form-label" for="comp">Competition</label><select class="form-select" id="comp" name="comp" required><?= sel_opts($comps, (string)$cid, '- choose a competition -') ?></select></div>
    <div class="mb-3"><label class="form-label" for="file">Text file (.txt or .csv)</label><input class="form-control" type="file" id="file" name="file" accept=".txt,.csv,.tsv,text/plain,text/csv"></div>
    <div class="mb-3"><label class="form-label" for="list">...or paste the list here</label><textarea class="form-control" id="list" name="list" rows="7" placeholder="Office Warriors, Group A&#10;River Hawks, Group A&#10;City Strikers, Group B"></textarea></div>
    <button class="btn btn-accent"><i class="bi bi-eye"></i> Preview</button>
  </form></div>
  <div class="col-lg-5"><div class="ft-card p-4"><h2 class="h6 section-title">File format</h2>
    <p class="small mb-2">Each line: <code>Team name, Group</code></p>
    <ul class="small mb-2"><li>Only the <b>team name</b> is required.</li><li>The group (<code>Group A</code> or just <code>A</code>) is only used in group competitions. If you leave it out, the team goes into the first group with a free place.</li><li>Teams already in the competition are skipped. The competition's team limit and group sizes are respected.</li><li>Up to 200 per import.</li></ul>
    <pre class="small mb-2" style="white-space:pre-wrap">Office Warriors, Group A
River Hawks, Group A
City Strikers, B
United FC</pre>
    <a class="btn btn-sm btn-outline-theme" href="<?= e(url('admin/comp-teams-import.php?sample=1')) ?>"><i class="bi bi-download"></i> Download a sample file</a></div></div></div>
<?php endif;
require ROOT_PATH . '/includes/admin_footer.php';
