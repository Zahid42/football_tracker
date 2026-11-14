<?php
/**
 * Shared add/edit match form: validation, saving (match + my stats + squad + photos) and rendering.
 */
declare(strict_types=1);

const MATCH_TYPES = ['League', 'Tournament', 'Friendly', 'Training', 'Knockout', 'Final', 'Other'];
const HOME_AWAY   = ['Home', 'Away', 'Neutral'];

function match_form_defaults(): array
{
    $cur = get_current_season();
    $team = get_player(ctx_pid())['current_team_id'] ?? '';
    return [
        'match_date' => date('Y-m-d'), 'kickoff_time' => '', 'season_id' => $cur['id'] ?? '', 'competition_id' => '', 'venue_id' => '',
        'match_type' => 'Friendly', 'home_away' => 'Neutral', 'team_id' => $team ?: '', 'opponent_name' => '', 'team_score' => 0, 'opponent_score' => 0,
        'formation' => '4-2-3-1', 'notes' => '', 'photo' => null, 'team_photo' => null,
    ];
}

const PERF_FIELDS = ['minutes_played' => ['Min', 0, 200], 'goals' => ['G', 0, 30], 'assists' => ['A', 0, 30], 'yellow_cards' => ['YC', 0, 2], 'red_cards' => ['RC', 0, 1],
                     'own_goals' => ['OG', 0, 10], 'penalty_goals' => ['Pen', 0, 30], 'fouls' => ['Fouls', 0, 30],
                     'tackles' => ['Tkl', 0, 50], 'interceptions' => ['Int', 0, 50], 'clearances' => ['Clr', 0, 50], 'blocks' => ['Blk', 0, 50], 'recoveries' => ['Rec', 0, 50]];

/** Existing per-player stats of a match, keyed by player id. */
function load_perf(int $matchId): array
{
    $st = db()->prepare('SELECT * FROM match_statistics WHERE match_id = ?');
    $st->execute([$matchId]);
    $out = [];
    foreach ($st->fetchAll() as $r) { $out[(int)$r['player_id']] = ['played' => 1] + $r; }
    return $out;
}

function perf_default(array $player): array
{
    return ['played' => 0, 'position' => $player['preferred_position'] ?? '', 'started' => 1, 'minutes_played' => 70, 'goals' => 0, 'assists' => 0, 'yellow_cards' => 0, 'red_cards' => 0,
            'own_goals' => 0, 'penalty_goals' => 0, 'fouls' => 0, 'tackles' => 0, 'interceptions' => 0, 'clearances' => 0, 'blocks' => 0, 'recoveries' => 0, 'rating' => '', 'is_captain' => 0, 'is_motm' => 0];
}

/** Read the squad posted by the form. Returns list of rows for save_squad(). */
function read_posted_squad(string $formation, array &$errors, array $playersById): array
{
    $names = $_POST['squad_name'] ?? []; $nos = $_POST['squad_no'] ?? []; $poss = $_POST['squad_pos'] ?? []; $pids = $_POST['squad_player'] ?? [];
    $cap = (int)($_POST['squad_cap'] ?? 0);
    $slots = formation_slots($formation ?: '4-4-2');
    $rows = []; $usedPlayers = [];
    for ($slot = 1; $slot <= 11 + MAX_SUBS; $slot++) {
        $name = trim((string)($names[$slot] ?? ''));
        $no = trim((string)($nos[$slot] ?? ''));
        $pid = (int)($pids[$slot] ?? 0);
        if ($pid && !isset($playersById[$pid])) { $errors[] = "Squad spot $slot: unknown player."; continue; }
        if ($pid && isset($usedPlayers[$pid])) { $errors[] = $playersById[$pid]['full_name'] . ' is in the squad twice.'; continue; }
        if ($pid) {
            $usedPlayers[$pid] = true;
            if ($name === '') { $name = player_short_name($playersById[$pid]); }
            if ($no === '' && $playersById[$pid]['jersey_number'] !== null) { $no = (string)$playersById[$pid]['jersey_number']; }
        }
        if ($name === '') { continue; }
        if (mb_strlen($name) > 80) { $errors[] = "Squad spot $slot: name is too long."; continue; }
        if ($no !== '' && (!ctype_digit($no) || (int)$no > 99)) { $errors[] = "Squad spot $slot: shirt number must be 0-99."; continue; }
        $pos = $slot <= 11 ? $slots[$slot - 1]['pos'] : (string)($poss[$slot] ?? '');
        if ($slot > 11 && !in_array($pos, POSITIONS, true)) { $pos = ''; }
        $rows[] = ['slot' => $slot, 'name' => $name, 'shirt' => $no === '' ? null : (int)$no, 'pos' => $pos ?: null, 'player_id' => $pid ?: null, 'is_captain' => $slot === $cap];
    }
    return $rows;
}

/**
 * Validate + save. Returns [values, errors]; on success redirects to matches.php.
 */
function process_match_form(?array $existing): array
{
    $v = match_form_defaults();
    if ($existing) {
        $v = array_merge($v, $existing, ['opponent_name' => $existing['opponent_name']]);
    }
    $playersById = [];
    foreach (players_list(false) as $pl) { $playersById[(int)$pl['id']] = $pl; }
    $perfOld = $existing ? load_perf((int)$existing['id']) : [];
    if (!is_post()) {
        $v['_perf'] = $perfOld;
        if (!$existing && ctx_pid() && isset($playersById[ctx_pid()])) { $v['_perf'][ctx_pid()] = array_merge(perf_default($playersById[ctx_pid()]), ['played' => 1]); }
        return [$v, []];
    }
    csrf_require();

    $L = lookups();
    $errors = [];
    $int = function (string $k, string $label, int $min, int $max) use (&$errors): int {
        $raw = post($k, '0');
        if ($raw === '') { $raw = '0'; }
        if (!ctype_digit($raw) || (int)$raw < $min || (int)$raw > $max) { $errors[] = "$label must be a whole number between $min and $max."; return 0; }
        return (int)$raw;
    };
    $v['match_date'] = post('match_date');
    $d = DateTime::createFromFormat('Y-m-d', $v['match_date']);
    if (!$d || $d->format('Y-m-d') !== $v['match_date']) { $errors[] = 'Match date is not valid.'; }
    $v['kickoff_time'] = post('kickoff_time');
    if ($v['kickoff_time'] !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v['kickoff_time'])) { $errors[] = 'Kickoff time is not valid.'; }
    $v['season_id'] = (int)post('season_id');
    if (!isset($L['seasons'][$v['season_id']])) { $errors[] = 'Please choose a season.'; }
    $v['competition_id'] = (int)post('competition_id') ?: '';
    if ($v['competition_id'] !== '' && !isset($L['competitions'][$v['competition_id']])) { $errors[] = 'Invalid competition.'; }
    $v['venue_id'] = (int)post('venue_id') ?: '';
    if ($v['venue_id'] !== '' && !isset($L['venues'][$v['venue_id']])) { $errors[] = 'Invalid venue.'; }
    $v['team_id'] = (int)post('team_id');
    if (!isset($L['teams'][$v['team_id']])) { $errors[] = 'Please choose your team.'; }
    $v['opponent_name'] = post('opponent_name');
    if ($v['opponent_name'] === '' || mb_strlen($v['opponent_name']) > 100) { $errors[] = 'Opponent is required (max 100 characters).'; }
    $v['match_type'] = post('match_type');
    if (!in_array($v['match_type'], MATCH_TYPES, true)) { $errors[] = 'Invalid match type.'; }
    $v['home_away'] = post('home_away');
    if (!in_array($v['home_away'], HOME_AWAY, true)) { $errors[] = 'Invalid home/away value.'; }
    $v['formation'] = post('formation');
    if ($v['formation'] !== '' && !isset(FORMATIONS[$v['formation']])) { $errors[] = 'Invalid formation.'; }
    $v['team_score'] = $int('team_score', 'My team score', 0, 99);
    $v['opponent_score'] = $int('opponent_score', 'Opponent score', 0, 99);
    $v['notes'] = post('notes');
    if (mb_strlen($v['notes']) > 5000) { $errors[] = 'Notes are too long.'; }

    // ---- per-player performances
    $perf = [];
    $sumGoals = 0;
    foreach (($_POST['perf'] ?? []) as $pid => $row) {
        $pid = (int)$pid;
        if (!isset($playersById[$pid]) || !is_array($row)) { continue; }
        $name = player_short_name($playersById[$pid]);
        $r = ['played' => isset($row['played']) ? 1 : 0, 'position' => (string)($row['position'] ?? ''), 'started' => isset($row['started']) ? 1 : 0,
              'is_captain' => isset($row['is_captain']) ? 1 : 0, 'is_motm' => isset($row['is_motm']) ? 1 : 0, 'rating' => trim((string)($row['rating'] ?? ''))];
        foreach (PERF_FIELDS as $k => [$lab, $min, $max]) {
            $raw = trim((string)($row[$k] ?? '0')); if ($raw === '') { $raw = '0'; }
            if ($r['played'] && (!ctype_digit($raw) || (int)$raw < $min || (int)$raw > $max)) { $errors[] = "$name - $lab must be a whole number between $min and $max."; $raw = '0'; }
            $r[$k] = (int)$raw;
        }
        if ($r['position'] !== '' && !in_array($r['position'], POSITIONS, true)) { $errors[] = "$name - invalid position."; $r['position'] = ''; }
        if ($r['rating'] !== '' && (!is_numeric($r['rating']) || $r['rating'] < 0 || $r['rating'] > 10)) { $errors[] = "$name - rating must be between 0 and 10."; $r['rating'] = ''; }
        if ($r['played']) {
            if ($r['goals'] > $v['team_score']) { $errors[] = "$name - goals cannot be more than the team score."; }
            if ($r['assists'] > $v['team_score']) { $errors[] = "$name - assists cannot be more than the team score."; }
            if ($r['penalty_goals'] > $r['goals']) { $errors[] = "$name - penalty goals cannot be more than goals."; }
            $sumGoals += $r['goals'];
        }
        $perf[$pid] = $r;
    }
    if ($sumGoals > $v['team_score']) { $errors[] = 'The players\' goals add up to ' . $sumGoals . ', more than the team score (' . $v['team_score'] . ').'; }
    $squad = read_posted_squad($v['formation'], $errors, $playersById);

    $uploaded = [];
    if (!$errors) {
        foreach (['photo' => 'matches', 'team_photo' => 'matches'] as $field => $dir) {
            try {
                $path = handle_image_upload($field, $dir);
            } catch (RuntimeException $ex) {
                $errors[] = ($field === 'photo' ? 'Match photo' : 'Team photo') . ': ' . $ex->getMessage();
                foreach ($uploaded as $u) { delete_upload($u); }
                $uploaded = [];
                break;
            }
            if ($path) { $v[$field . '_new'] = $path; $uploaded[] = $path; }
        }
    }
    if ($errors) {
        foreach ($uploaded as $u) { delete_upload($u); }
        $v['_squad_posted'] = $squad; $v['_perf'] = $perf;
        return [$v, $errors];
    }

    $pdo = db();
    try {
        $pdo->beginTransaction();
        $oppId = opponent_get_or_create($v['opponent_name']);
        $photo = $v['photo_new'] ?? ($existing['photo'] ?? null);
        $teamPhoto = $v['team_photo_new'] ?? ($existing['team_photo'] ?? null);
        $m = [$v['season_id'], $v['competition_id'] ?: null, $v['venue_id'] ?: null, $v['team_id'], $oppId, $v['match_date'],
              $v['kickoff_time'] !== '' ? $v['kickoff_time'] . ':00' : null, $v['match_type'], $v['home_away'], $v['formation'] ?: null,
              $v['team_score'], $v['opponent_score'], $v['notes'] ?: null, $photo, $teamPhoto];
        if ($existing) {
            $pdo->prepare('UPDATE matches SET season_id=?, competition_id=?, venue_id=?, team_id=?, opponent_id=?, match_date=?, kickoff_time=?, match_type=?, home_away=?, formation=?,
                           team_score=?, opponent_score=?, notes=?, photo=?, team_photo=? WHERE id=?')->execute([...$m, $existing['id']]);
            $id = (int)$existing['id'];
        } else {
            $pdo->prepare('INSERT INTO matches (season_id, competition_id, venue_id, team_id, opponent_id, match_date, kickoff_time, match_type, home_away, formation,
                           team_score, opponent_score, notes, photo, team_photo) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute($m);
            $id = (int)$pdo->lastInsertId();
        }
        $up = $pdo->prepare('INSERT INTO match_statistics (match_id, player_id, position, started, minutes_played, goals, assists, yellow_cards, red_cards, own_goals, penalty_goals, fouls, tackles, interceptions, clearances, blocks, recoveries, rating, is_captain, is_motm)
                       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                       ON DUPLICATE KEY UPDATE position=VALUES(position), started=VALUES(started), minutes_played=VALUES(minutes_played), goals=VALUES(goals), assists=VALUES(assists),
                       yellow_cards=VALUES(yellow_cards), red_cards=VALUES(red_cards), own_goals=VALUES(own_goals), penalty_goals=VALUES(penalty_goals), fouls=VALUES(fouls), tackles=VALUES(tackles), interceptions=VALUES(interceptions), clearances=VALUES(clearances), blocks=VALUES(blocks), recoveries=VALUES(recoveries),
                       rating=VALUES(rating), is_captain=VALUES(is_captain), is_motm=VALUES(is_motm)');
        $del = $pdo->prepare('DELETE FROM match_statistics WHERE match_id = ? AND player_id = ?');
        foreach ($perf as $pid => $r) {
            if ($r['played']) {
                $up->execute([$id, $pid, $r['position'] ?: null, $r['started'], $r['minutes_played'], $r['goals'], $r['assists'], $r['yellow_cards'], $r['red_cards'], $r['own_goals'],
                              $r['penalty_goals'], $r['fouls'], $r['tackles'], $r['interceptions'], $r['clearances'], $r['blocks'], $r['recoveries'], $r['rating'] === '' ? null : $r['rating'], $r['is_captain'], $r['is_motm']]);
            } else {
                $del->execute([$id, $pid]);
            }
        }
        save_squad($id, $squad);
        $pdo->commit();
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        foreach ($uploaded as $u) { delete_upload($u); }
        $errors[] = 'Database error while saving the match.' . (APP_DEBUG ? ' ' . $ex->getMessage() : '');
        $v['_squad_posted'] = $squad; $v['_perf'] = $perf;
        return [$v, $errors];
    }
    if ($existing) {
        if (isset($v['photo_new']) && !empty($existing['photo'])) { delete_upload($existing['photo']); }
        if (isset($v['team_photo_new']) && !empty($existing['team_photo'])) { delete_upload($existing['team_photo']); }
    }
    flash_set('success', $existing ? 'Match updated.' : 'Match added.');
    redirect('admin/matches.php');
}

function num_input(string $name, string $label, $val, int $min, int $max, int $col = 3): string
{
    return '<div class="col-6 col-md-' . $col . '"><label class="form-label" for="f_' . $name . '">' . e($label) . '</label><input type="number" class="form-control" id="f_' . $name . '" name="' . $name
         . '" value="' . e($val) . '" min="' . $min . '" max="' . $max . '"></div>';
}

function render_match_form(array $v, array $errors, ?array $existing): string
{
    $L = lookups();
    $h = '';
    foreach ($errors as $er) { $h .= '<div class="alert alert-danger py-2">' . e($er) . '</div>'; }
    $h .= '<form method="post" enctype="multipart/form-data" class="vstack gap-4">' . csrf_field();

    $h .= '<div class="ft-card p-4"><h2 class="section-title h6 mb-3"><i class="bi bi-calendar-event"></i> Match details</h2><div class="row g-3">'
        . '<div class="col-6 col-md-3"><label class="form-label" for="f_match_date">Match Date <span class="text-danger">*</span></label><input type="date" class="form-control" id="f_match_date" name="match_date" value="' . e($v['match_date']) . '" required></div>'
        . '<div class="col-6 col-md-3"><label class="form-label" for="f_kickoff_time">Kickoff Time</label><input type="time" class="form-control" id="f_kickoff_time" name="kickoff_time" value="' . e($v['kickoff_time'] ? substr((string)$v['kickoff_time'], 0, 5) : '') . '"></div>'
        . '<div class="col-6 col-md-3"><label class="form-label" for="f_season_id">Season <span class="text-danger">*</span></label><select class="form-select" id="f_season_id" name="season_id" required>' . sel_opts($L['seasons'], $v['season_id'], '-- choose --') . '</select></div>'
        . '<div class="col-6 col-md-3"><label class="form-label" for="f_competition_id">Competition</label><select class="form-select" id="f_competition_id" name="competition_id">' . sel_opts($L['competitions'], $v['competition_id'], '-- none --') . '</select></div>'
        . '<div class="col-6 col-md-3"><label class="form-label" for="f_venue_id">Venue</label><select class="form-select" id="f_venue_id" name="venue_id">' . sel_opts($L['venues'], $v['venue_id'], '-- none --') . '</select></div>'
        . '<div class="col-6 col-md-3"><label class="form-label" for="f_match_type">Match Type</label><select class="form-select" id="f_match_type" name="match_type">' . sel_opts(MATCH_TYPES, $v['match_type'], '-- choose --') . '</select></div>'
        . '<div class="col-6 col-md-3"><label class="form-label" for="f_home_away">Home / Away</label><select class="form-select" id="f_home_away" name="home_away">' . sel_opts(HOME_AWAY, $v['home_away'], '-- choose --') . '</select></div>'
        . '<div class="col-6 col-md-3"><label class="form-label" for="f_formation">Formation</label><select class="form-select" id="f_formation" name="formation">' . sel_opts(array_keys(FORMATIONS), $v['formation'], '-- none --') . '</select></div>'
        . '<div class="col-12 col-md-4"><label class="form-label" for="f_team_id">My Team <span class="text-danger">*</span></label><select class="form-select" id="f_team_id" name="team_id" required>' . sel_opts($L['teams'], $v['team_id'], '-- choose --') . '</select></div>'
        . '<div class="col-12 col-md-4"><label class="form-label" for="f_opponent_name">Opponent <span class="text-danger">*</span></label><input class="form-control" id="f_opponent_name" name="opponent_name" list="oppList" value="' . e($v['opponent_name']) . '" maxlength="100" required>'
        . '<datalist id="oppList">' . implode('', array_map(fn($n) => '<option value="' . e($n) . '">', $L['opponents'])) . '</datalist><div class="form-text">Pick an existing opponent or type a new name.</div></div>'
        . num_input('team_score', 'My Team Score', $v['team_score'], 0, 99, 2) . num_input('opponent_score', 'Opponent Score', $v['opponent_score'], 0, 99, 2)
        . '<div class="col-12"><div class="form-text"><i class="bi bi-info-circle"></i> The result (Win / Draw / Loss) is worked out automatically from the score.</div></div>'
        . '</div></div>';

    // ---- Player performances
    $players = players_list(false);
    $perf = $v['_perf'] ?? [];
    $h .= '<div class="ft-card p-4"><h2 class="section-title h6 mb-1"><i class="bi bi-person-badge"></i> Player performances</h2>'
        . '<p class="text-muted-ft small">Tick <b>Played</b> for every player who took part and fill in his numbers. Players left unticked get no record for this match. '
        . 'Add players from <a href="' . e(url('admin/players.php?action=new')) . '">Players</a>.</p>'
        . '<div class="table-responsive"><table class="table ft-table align-middle perf-table mb-0"><thead><tr><th>Played</th><th>Player</th><th>Pos</th><th>Start</th>';
    foreach (PERF_FIELDS as [$lab]) { $h .= '<th>' . e($lab) . '</th>'; }
    $h .= '<th>Rating</th><th>C</th><th>MOTM</th></tr></thead><tbody>';
    foreach ($players as $pl) {
        $pid = (int)$pl['id'];
        if (!$pl['is_active'] && !isset($perf[$pid])) { continue; }
        $r = $perf[$pid] ?? perf_default($pl);
        $n = 'perf[' . $pid . ']';
        $h .= '<tr data-pid="' . $pid . '"><td><input class="form-check-input perf-played" type="checkbox" name="' . $n . '[played]" value="1"' . (!empty($r['played']) ? ' checked' : '') . '></td>'
            . '<td class="fw-semibold text-nowrap">' . e($pl['full_name']) . ($pl['is_active'] ? '' : ' <span class="badge bg-secondary">archived</span>') . '</td>'
            . '<td><select class="form-select form-select-sm perf-pos" name="' . $n . '[position]">' . sel_opts(POSITIONS, $r['position'] ?? '', '-') . '</select></td>'
            . '<td><input class="form-check-input" type="checkbox" name="' . $n . '[started]" value="1"' . (!empty($r['started']) ? ' checked' : '') . '></td>';
        foreach (PERF_FIELDS as $k => [$lab, $min, $max]) {
            $h .= '<td><input type="number" class="form-control form-control-sm" style="min-width:64px" name="' . $n . '[' . $k . ']" value="' . e($r[$k] ?? 0) . '" min="' . $min . '" max="' . $max . '"></td>';
        }
        $h .= '<td><input type="number" step="0.1" min="0" max="10" class="form-control form-control-sm" style="min-width:72px" name="' . $n . '[rating]" value="' . e($r['rating'] ?? '') . '"></td>'
            . '<td><input class="form-check-input" type="checkbox" name="' . $n . '[is_captain]" value="1"' . (!empty($r['is_captain']) ? ' checked' : '') . '></td>'
            . '<td><input class="form-check-input" type="checkbox" name="' . $n . '[is_motm]" value="1"' . (!empty($r['is_motm']) ? ' checked' : '') . '></td></tr>';
    }
    if (!$players) { $h .= '<tr><td colspan="14" class="text-center text-muted-ft">No players yet.</td></tr>'; }
    $h .= '</tbody></table></div></div>';

    // ---- Squad editor
    $squad = $v['_squad_posted'] ?? null;
    $bySlot = [];
    if ($squad !== null) {
        foreach ($squad as $r) { $bySlot[$r['slot']] = ['player_name' => $r['name'], 'shirt_number' => $r['shirt'], 'position' => $r['pos'], 'player_id' => $r['player_id'], 'is_captain' => $r['is_captain']]; }
    } elseif ($existing) {
        foreach (get_squad((int)$existing['id']) as $r) { $bySlot[(int)$r['slot']] = $r; }
    }
    $slots = formation_slots($v['formation'] ?: '4-4-2');
    $playerSelect = function (int $slot, $sel) use ($players): string {
        $o = '<select class="form-select form-select-sm squad-player" name="squad_player[' . $slot . ']"><option value="">- other -</option>';
        foreach ($players as $pl) {
            $o .= '<option value="' . (int)$pl['id'] . '" data-name="' . e(player_short_name($pl)) . '" data-no="' . e($pl['jersey_number'] ?? '') . '"' . ((int)$sel === (int)$pl['id'] ? ' selected' : '') . '>' . e($pl['full_name']) . '</option>';
        }
        return $o . '</select>';
    };
    $h .= '<div class="ft-card p-4"><h2 class="section-title h6 mb-1"><i class="bi bi-people"></i> Squad / line-up (pitch view)</h2>'
        . '<p class="text-muted-ft small">Fill the starting XI in order: goalkeeper first, then each line from defence to attack, left to right. Positions update with the formation. '
        . 'Choose a registered player from the list (name and shirt are filled in automatically) or leave it on "other" and type any name. Tick <b>C</b> for the captain.</p>'
        . '<div class="table-responsive"><table class="table ft-table align-middle squad-edit mb-2"><thead><tr><th>#</th><th>Pos</th><th>Registered player</th><th>Name</th><th>Shirt</th><th class="text-center">C</th></tr></thead><tbody>';
    foreach ($slots as $s) {
        $r = $bySlot[$s['slot']] ?? [];
        $h .= '<tr><td>' . $s['slot'] . '</td><td><span class="badge bg-success" data-slot-pos="' . ($s['slot'] - 1) . '">' . e($s['pos']) . '</span></td><td>' . $playerSelect($s['slot'], $r['player_id'] ?? 0) . '</td>'
            . '<td><input class="form-control form-control-sm squad-name" name="squad_name[' . $s['slot'] . ']" value="' . e($r['player_name'] ?? '') . '" maxlength="80"></td>'
            . '<td style="width:90px"><input type="number" class="form-control form-control-sm squad-no" name="squad_no[' . $s['slot'] . ']" value="' . e($r['shirt_number'] ?? '') . '" min="0" max="99"></td>'
            . '<td class="text-center"><input class="form-check-input" type="radio" name="squad_cap" value="' . $s['slot'] . '"' . (!empty($r['is_captain']) ? ' checked' : '') . '></td></tr>';
    }
    for ($slot = 12; $slot <= 11 + MAX_SUBS; $slot++) {
        $r = $bySlot[$slot] ?? [];
        $h .= '<tr class="sub-row"><td>' . $slot . '</td><td><select class="form-select form-select-sm" name="squad_pos[' . $slot . ']">' . sel_opts(POSITIONS, $r['position'] ?? '', 'Sub') . '</select></td><td>' . $playerSelect($slot, $r['player_id'] ?? 0) . '</td>'
            . '<td><input class="form-control form-control-sm squad-name" name="squad_name[' . $slot . ']" value="' . e($r['player_name'] ?? '') . '" maxlength="80" placeholder="Substitute"></td>'
            . '<td><input type="number" class="form-control form-control-sm squad-no" name="squad_no[' . $slot . ']" value="' . e($r['shirt_number'] ?? '') . '" min="0" max="99"></td>'
            . '<td class="text-center"><input class="form-check-input" type="radio" name="squad_cap" value="' . $slot . '"' . (!empty($r['is_captain']) ? ' checked' : '') . '></td></tr>';
    }
    $h .= '</tbody></table></div></div>';

    $h .= '<div class="ft-card p-4"><h2 class="section-title h6 mb-3"><i class="bi bi-journal-text"></i> Notes &amp; photos</h2><div class="row g-3">'
        . '<div class="col-12"><label class="form-label" for="f_notes">Match Notes</label><textarea class="form-control" id="f_notes" name="notes" rows="3">' . e($v['notes']) . '</textarea></div>'
        . '';
    foreach (['photo' => 'Match Photo', 'team_photo' => 'Team Photo'] as $k => $label) {
        $h .= '<div class="col-12 col-md-6"><label class="form-label" for="f_' . $k . '">' . $label . '</label>'
            . (!empty($v[$k]) ? '<div class="mb-2"><img class="img-thumb" src="' . e(upload_url($v[$k])) . '" alt=""></div>' : '')
            . '<input type="file" class="form-control" id="f_' . $k . '" name="' . $k . '" accept=".jpg,.jpeg,.png,.webp"><div class="form-text">JPG/PNG/WEBP, max ' . (MAX_UPLOAD_BYTES / 1048576) . ' MB.</div></div>';
    }
    $h .= '</div></div>';
    $h .= '<div class="d-flex gap-2"><button class="btn btn-accent btn-lg" type="submit"><i class="bi bi-check-lg"></i> ' . ($existing ? 'Update Match' : 'Save Match') . '</button>'
        . '<a class="btn btn-outline-theme btn-lg" href="' . e(url('admin/matches.php')) . '">Cancel</a></div></form>';

    $formJson = json_encode(array_map(fn($f) => array_column(formation_slots($f), 'pos'), array_combine(array_keys(FORMATIONS), array_keys(FORMATIONS))));
    $h .= '<script>(function(){var F=' . $formJson . ';var sel=document.getElementById("f_formation");if(!sel)return;'
        . 'function upd(){var p=F[sel.value]||F["4-4-2"];document.querySelectorAll("[data-slot-pos]").forEach(function(el){el.textContent=p[+el.dataset.slotPos]||"";});}'
        . 'sel.addEventListener("change",upd);upd();'
        . 'document.querySelectorAll(".squad-player").forEach(function(ps){ps.addEventListener("change",function(){var row=ps.closest("tr"),o=ps.options[ps.selectedIndex];'
        . 'if(!ps.value)return;var n=row.querySelector(".squad-name"),no=row.querySelector(".squad-no");if(!n.value)n.value=o.dataset.name||"";if(!no.value)no.value=o.dataset.no||"";'
        . 'var pr=document.querySelector(\'tr[data-pid="\'+ps.value+\'"]\');if(pr){pr.querySelector(".perf-played").checked=true;var pos=row.querySelector("[data-slot-pos]"),sp=pr.querySelector(".perf-pos");if(pos&&sp&&!sp.value)sp.value=pos.textContent;}});});})();</script>';
    return $h;
}
