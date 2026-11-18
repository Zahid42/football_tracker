<?php
/**
 * Squads: formations, saving a line-up, and the pitch ("squad view") renderer.
 * Starting XI = slots 1-11 in formation order (goalkeeper first, then each line
 * from defence to attack, left to right). Substitutes = slots 12+.
 */
declare(strict_types=1);

/** Positions for one line of a formation. $i = line index (0 = defence), $n = number of lines, $c = players in the line. */
function ft_line_positions(int $i, int $n, int $c): array
{
    $def = [1 => ['CB'], 2 => ['CB', 'CB'], 3 => ['CB', 'CB', 'CB'], 4 => ['LB', 'CB', 'CB', 'RB'], 5 => ['LB', 'CB', 'CB', 'CB', 'RB'], 6 => ['LB', 'CB', 'CB', 'CB', 'CB', 'RB']];
    $att = [1 => ['ST'], 2 => ['ST', 'ST'], 3 => ['LW', 'ST', 'RW'], 4 => ['LW', 'ST', 'ST', 'RW'], 5 => ['LW', 'ST', 'ST', 'ST', 'RW']];
    if ($i === 0) { return $def[$c]; }
    if ($i === $n - 1) { return $att[$c]; }
    $mids = $n - 2; $k = $i - 1;                       // which midfield line this is
    if ($mids === 1) { return [1 => ['CM'], 2 => ['CM', 'CM'], 3 => ['CM', 'CM', 'CM'], 4 => ['LM', 'CM', 'CM', 'RM'], 5 => ['LM', 'CM', 'CDM', 'CM', 'RM'], 6 => ['LM', 'CM', 'CM', 'CM', 'CM', 'RM']][$c]; }
    $wide = fn(int $c) => array_merge(['LM'], array_fill(0, $c - 2, 'CM'), ['RM']);
    if ($k === 0) { return [1 => ['CDM'], 2 => ['CDM', 'CDM'], 3 => ['CM', 'CDM', 'CM'], 4 => ['LM', 'CM', 'CM', 'RM']][$c] ?? $wide($c); }
    if ($k === $mids - 1 && $c === 4) { return ['LM', 'CM', 'CM', 'RM']; }
    if ($k > 0 && $k < $mids - 1 && $c === 2) { return ['CM', 'CM']; }
    return [1 => ['CAM'], 2 => ['CAM', 'CAM'], 3 => ['LM', 'CAM', 'RM'], 4 => ['LM', 'CAM', 'CAM', 'RM']][$c] ?? $wide($c);
}

/** Every common formation: key "4-3-3" => lines of positions, goalkeeper first. */
function ft_build_formations(): array
{
    $keys = ['4-4-2', '4-3-3', '4-2-3-1', '4-1-4-1', '3-5-2', '3-4-3', '5-3-2',
        '4-5-1', '4-4-1-1', '4-3-2-1', '4-3-1-2', '4-2-2-2', '4-2-4', '4-2-1-3', '4-1-2-3', '4-1-2-1-2', '4-1-3-2',
        '3-4-1-2', '3-4-2-1', '3-6-1', '3-3-4', '3-3-3-1', '3-1-4-2', '3-2-4-1', '3-5-1-1', '3-2-5',
        '5-4-1', '5-2-3', '5-2-1-2', '5-3-1-1', '5-1-2-2', '2-3-5'];
    $out = [];
    foreach ($keys as $k) {
        $nums = array_map('intval', explode('-', $k)); $n = count($nums); $lines = [['GK']];
        foreach ($nums as $i => $c) { $lines[] = ft_line_positions($i, $n, $c); }
        $out[$k] = $lines;
    }
    return $out;
}
define('FORMATIONS', ft_build_formations());
const MAX_SUBS = 7;

/** Slots for a formation: list of ['slot'=>1..11,'pos'=>'GK','x'=>%,'y'=>%]. */
function formation_slots(?string $formation): array
{
    $lines = FORMATIONS[$formation ?? ''] ?? FORMATIONS['4-4-2'];
    $out = [];
    $slot = 1;
    $n = count($lines);
    foreach ($lines as $i => $line) {
        $y = 90 - $i * (78 / ($n - 1));
        $c = count($line);
        foreach ($line as $j => $pos) {
            $out[] = ['slot' => $slot++, 'pos' => $pos, 'x' => round(($j + 1) / ($c + 1) * 100, 1), 'y' => round($y, 1)];
        }
    }
    return $out;
}

function get_squad(int $matchId): array
{
    $st = db()->prepare('SELECT * FROM match_squad WHERE match_id = ? ORDER BY slot');
    $st->execute([$matchId]);
    return $st->fetchAll();
}

/**
 * Replace a match's squad. $rows: list of [slot, name, shirt(int|null), pos, player_id, is_captain]
 * Rows with an empty name are skipped. player_id links a squad spot to a registered player.
 */
function save_squad(int $matchId, array $rows): void
{
    $pdo = db();
    $pdo->prepare('DELETE FROM match_squad WHERE match_id = ?')->execute([$matchId]);
    $ins = $pdo->prepare('INSERT INTO match_squad (match_id, slot, player_name, shirt_number, position, player_id, is_captain)
                          VALUES (?,?,?,?,?,?,?)');
    foreach ($rows as $r) {
        if ($r['name'] === '') {
            continue;
        }
        $ins->execute([$matchId, $r['slot'], $r['name'], $r['shirt'], $r['pos'], $r['player_id'] ?: null, $r['is_captain'] ? 1 : 0]);
    }
}

/** The pitch. $match needs: formation, team_name, opponent_name, team_score, opponent_score. */
function render_pitch(array $match, array $squad, bool $horizontal = false): string
{
    $starters = [];
    $subs = [];
    foreach ($squad as $p) {
        if ((int)$p['slot'] <= 11) { $starters[(int)$p['slot']] = $p; } else { $subs[] = $p; }
    }
    if (!$starters) {
        return '<div class="ft-card p-4 text-center text-muted-ft"><i class="bi bi-people fs-3 d-block mb-2"></i>No squad recorded for this match yet.</div>';
    }
    $formation = $match['formation'] ?: '';
    $viewPid = ctx_pid();
    $isMe = fn(array $p) => !empty($p['player_id']) && (int)$p['player_id'] === $viewPid;
    $nameHtml = fn(array $p) => !empty($p['player_id'])
        ? '<a class="pname" href="' . e(player_url_by_id((int)$p['player_id'])) . '">' . e($p['player_name']) . '</a>'
        : '<span class="pname">' . e($p['player_name']) . '</span>';
    $h  = '<div class="squad-view" data-fp="match" data-h="' . ($horizontal ? 1 : 0) . '">';
    $h .= formation_picker('match', $formation);
    $h .= '<div class="squad-head"><span class="sq-team">' . e($match['team_name']) . '</span>'
        . '<span class="sq-form">' . e($formation ?: 'Line-up') . '</span>'
        . '<span class="sq-vs">vs ' . e($match['opponent_name']) . ' &middot; ' . (int)$match['team_score'] . '-' . (int)$match['opponent_score'] . '</span></div>';
    $h .= '<div class="pitch' . ($horizontal ? ' pitch-h' : '') . '">' . pitch_svg($horizontal);
    foreach (formation_slots($formation) as $s) {
        $p = $starters[$s['slot']] ?? null;
        if (!$p) { continue; }
        $cls = 'pl' . ($isMe($p) ? ' is-me' : '') . (!empty($p['player_id']) ? ' is-reg' : '') . ($s['pos'] === 'GK' ? ' is-gk' : '');
        $h .= '<div class="' . $cls . '" data-slot="' . $s['slot'] . '" style="left:' . ($horizontal ? 100 - $s['y'] : $s['x']) . '%;top:' . ($horizontal ? $s['x'] : $s['y']) . '%" title="' . e($p['player_name'] . ' - ' . position_name($s['pos'])) . '">'
            . '<span class="shirt">' . e($p['shirt_number'] ?? '') . ($p['is_captain'] ? '<i class="cap">C</i>' : '') . '</span>'
            . $nameHtml($p) . '<span class="ppos">' . e($s['pos']) . '</span></div>';
    }
    $h .= '</div>';
    if ($subs) {
        $h .= '<div class="bench"><div class="bench-title"><i class="bi bi-arrow-repeat"></i> Substitutes</div><div class="bench-list">';
        foreach ($subs as $p) {
            $h .= '<span class="bench-p' . ($isMe($p) ? ' is-me' : '') . '"><b>' . e($p['shirt_number'] ?? '-') . '</b> ' . e($p['player_name'])
                . ' <small>' . e($p['position']) . '</small></span>';
        }
        $h .= '</div></div>';
    }
    return $h . '</div>';
}

/** Formation picker shown above every pitch. $mode 'match' keeps the recorded line-up order; 'team' places players by their positions. */
function formation_picker(string $mode, string $current = ''): string
{
    static $loaded = false;
    $h = '<div class="fp-bar"><label class="fp-lab" for="fp-' . ($id = bin2hex(random_bytes(3))) . '"><i class="bi bi-diagram-3"></i> Formation</label><select class="form-select form-select-sm fp-select" id="fp-' . $id . '">';
    $h .= $mode === 'team' ? '<option value="">Auto (by player positions)</option>' : '<option value="">' . e($current !== '' ? $current . ' (as played)' : 'As recorded') . '</option>';
    foreach (array_keys(FORMATIONS) as $f) { $h .= '<option value="' . e($f) . '">' . e($f) . '</option>'; }
    $h .= '</select></div>';
    if (!$loaded) {
        $loaded = true;
        $h .= '<script>window.FT_FORMATIONS=' . json_encode(FORMATIONS) . ';</script><script src="' . e(url('assets/js/pitch.js') . '?v=' . (int)@filemtime(ROOT_PATH . '/assets/js/pitch.js')) . '" defer></script>';
    }
    return $h;
}

function pitch_svg(bool $horizontal = false): string
{
    if ($horizontal) {
        return '<svg class="pitch-lines" viewBox="0 0 140 100" preserveAspectRatio="none" aria-hidden="true"><g fill="none" stroke="rgba(255,255,255,.75)" stroke-width=".5">'
            . '<rect x="3" y="3" width="134" height="94"/><line x1="70" y1="3" x2="70" y2="97"/><circle cx="70" cy="50" r="10"/>'
            . '<rect x="3" y="22" width="19" height="56"/><rect x="3" y="36" width="7" height="28"/><path d="M22 40 A11 11 0 0 1 22 60"/>'
            . '<rect x="118" y="22" width="19" height="56"/><rect x="130" y="36" width="7" height="28"/><path d="M118 40 A11 11 0 0 0 118 60"/>'
            . '</g><g fill="rgba(255,255,255,.8)"><circle cx="70" cy="50" r=".8"/><circle cx="15" cy="50" r=".7"/><circle cx="125" cy="50" r=".7"/></g></svg>';
    }
    return '<svg class="pitch-lines" viewBox="0 0 100 140" aria-hidden="true"><g fill="none" stroke="rgba(255,255,255,.75)" stroke-width=".5">'
        . '<rect x="3" y="3" width="94" height="134"/><line x1="3" y1="70" x2="97" y2="70"/><circle cx="50" cy="70" r="10"/>'
        . '<rect x="22" y="3" width="56" height="19"/><rect x="36" y="3" width="28" height="7"/><path d="M40 22 A11 11 0 0 0 60 22"/>'
        . '<rect x="22" y="118" width="56" height="19"/><rect x="36" y="130" width="28" height="7"/><path d="M40 118 A11 11 0 0 1 60 118"/>'
        . '</g><g fill="rgba(255,255,255,.8)"><circle cx="50" cy="70" r=".8"/><circle cx="50" cy="15" r=".7"/><circle cx="50" cy="125" r=".7"/></g></svg>';
}
