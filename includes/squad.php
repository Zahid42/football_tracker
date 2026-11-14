<?php
/**
 * Squads: formations, saving a line-up, and the pitch ("squad view") renderer.
 * Starting XI = slots 1-11 in formation order (goalkeeper first, then each line
 * from defence to attack, left to right). Substitutes = slots 12+.
 */
declare(strict_types=1);

const FORMATIONS = [
    '4-4-2'   => [['GK'], ['LB','CB','CB','RB'], ['LM','CM','CM','RM'], ['ST','ST']],
    '4-3-3'   => [['GK'], ['LB','CB','CB','RB'], ['CM','CM','CM'], ['LW','ST','RW']],
    '4-2-3-1' => [['GK'], ['LB','CB','CB','RB'], ['CDM','CDM'], ['LM','CAM','RM'], ['ST']],
    '4-1-4-1' => [['GK'], ['LB','CB','CB','RB'], ['CDM'], ['LM','CM','CM','RM'], ['ST']],
    '3-5-2'   => [['GK'], ['CB','CB','CB'], ['LM','CM','CDM','CM','RM'], ['ST','ST']],
    '3-4-3'   => [['GK'], ['CB','CB','CB'], ['LM','CM','CM','RM'], ['LW','ST','RW']],
    '5-3-2'   => [['GK'], ['LB','CB','CB','CB','RB'], ['CM','CM','CM'], ['ST','ST']],
];
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
    $h  = '<div class="squad-view">';
    $h .= '<div class="squad-head"><span class="sq-team">' . e($match['team_name']) . '</span>'
        . '<span class="sq-form">' . e($formation ?: 'Line-up') . '</span>'
        . '<span class="sq-vs">vs ' . e($match['opponent_name']) . ' &middot; ' . (int)$match['team_score'] . '-' . (int)$match['opponent_score'] . '</span></div>';
    $h .= '<div class="pitch' . ($horizontal ? ' pitch-h' : '') . '">' . pitch_svg($horizontal);
    foreach (formation_slots($formation) as $s) {
        $p = $starters[$s['slot']] ?? null;
        if (!$p) { continue; }
        $cls = 'pl' . ($isMe($p) ? ' is-me' : '') . (!empty($p['player_id']) ? ' is-reg' : '') . ($s['pos'] === 'GK' ? ' is-gk' : '');
        $h .= '<div class="' . $cls . '" style="left:' . ($horizontal ? 100 - $s['y'] : $s['x']) . '%;top:' . ($horizontal ? $s['x'] : $s['y']) . '%" title="' . e($p['player_name'] . ' - ' . position_name($s['pos'])) . '">'
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
