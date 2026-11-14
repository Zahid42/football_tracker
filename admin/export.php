<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_login();
$type = get_str('type', 'matches');
$rows = []; $head = [];
switch ($type) {
    case 'matches':
        $f = match_filters($_GET);
        $head = ['Date','Kickoff','Season','Competition','Venue','Type','Team','Opponent','Team Score','Opponent Score','Result','Position','Minutes','Goals','Assists','Yellow','Red','Own Goals','Penalty Goals','Fouls','Tackles','Interceptions','Clearances','Blocks','Recoveries','Defensive Total','Rating','Captain','MOTM','Notes'];
        foreach (search_matches($f, 0, 0, true) as $m) {
            $rows[] = [$m['match_date'], $m['kickoff_time'], $m['season_name'], $m['competition_name'], $m['venue_name'], $m['match_type'], $m['team_name'], $m['opponent_name'], $m['team_score'], $m['opponent_score'],
                       result_label($m['result']), $m['position'], $m['minutes_played'], $m['goals'], $m['assists'], $m['yellow_cards'], $m['red_cards'], $m['own_goals'], $m['penalty_goals'], $m['fouls'], $m['tackles'], $m['interceptions'], $m['clearances'], $m['blocks'], $m['recoveries'], $m['tackles'] + $m['interceptions'] + $m['clearances'] + $m['blocks'] + $m['recoveries'],
                       $m['rating'], $m['is_captain'] ? 'Yes' : 'No', $m['is_motm'] ? 'Yes' : 'No', $m['notes']];
        }
        break;
    case 'goals':
        $head = ['Player','Date','Match','Minute','Type','Assisted by','Description'];
        foreach (db()->query("SELECT g.*, p.full_name pn, m.match_date, t.name tn, o.name o FROM goals g JOIN players p ON p.id=g.player_id JOIN matches m ON m.id=g.match_id JOIN teams t ON t.id=m.team_id JOIN opponents o ON o.id=m.opponent_id ORDER BY m.match_date, g.minute") as $r) {
            $rows[] = [$r['pn'], $r['match_date'], $r['tn'] . ' vs ' . $r['o'], $r['minute'], $r['goal_type'], $r['assisted_by'], $r['description']];
        }
        break;
    case 'assists':
        $head = ['Player','Date','Match','Minute','Type','Goal scorer','Description'];
        foreach (db()->query("SELECT a.*, p.full_name pn, m.match_date, t.name tn, o.name o FROM assists a JOIN players p ON p.id=a.player_id JOIN matches m ON m.id=a.match_id JOIN teams t ON t.id=m.team_id JOIN opponents o ON o.id=m.opponent_id ORDER BY m.match_date, a.minute") as $r) {
            $rows[] = [$r['pn'], $r['match_date'], $r['tn'] . ' vs ' . $r['o'], $r['minute'], $r['assist_type'], $r['goal_scorer'], $r['description']];
        }
        break;
    case 'trophies':
        $head = ['Player','Year','Tournament','Team','Achievement','Position','Description'];
        $pn = get_player(ctx_pid())['full_name'] ?? '';
        foreach (get_trophies() as $r) { $rows[] = [$pn, $r['year'], $r['tournament_name'], $r['team_name'], $r['achievement'], $r['position'], $r['description']]; }
        break;
    case 'squads':
        $head = ['Date','Match','Formation','Slot','Player','Shirt','Position','Registered player','Captain'];
        foreach (db()->query("SELECT q.*, pl.full_name reg, m.match_date, m.formation, t.name tn, o.name o FROM match_squad q LEFT JOIN players pl ON pl.id=q.player_id JOIN matches m ON m.id=q.match_id JOIN teams t ON t.id=m.team_id JOIN opponents o ON o.id=m.opponent_id ORDER BY m.match_date, q.slot") as $r) {
            $rows[] = [$r['match_date'], $r['tn'] . ' vs ' . $r['o'], $r['formation'], $r['slot'], $r['player_name'], $r['shirt_number'], $r['position'], $r['reg'] ?? '', $r['is_captain'] ? 'Yes' : ''];
        }
        break;
    default:
        http_response_code(400); exit('Unknown export type.');
}
// Neutralise spreadsheet formula injection
$safe = fn($v) => (is_string($v) && $v !== '' && strpos("=+-@\t\r", $v[0]) !== false) ? "'" . $v : $v;
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="football_' . $type . '_' . date('Ymd') . '.csv"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, $head);
foreach ($rows as $r) { fputcsv($out, array_map($safe, $r)); }
fclose($out);
