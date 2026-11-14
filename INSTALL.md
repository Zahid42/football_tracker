# Football Tracker - install (Windows + XAMPP)

1. Install XAMPP (PHP 8+) from apachefriends.org.
2. Open XAMPP Control Panel and Start **Apache** and **MySQL**.
3. Create the folder `C:\xampp\htdocs\football_tracker`.
4. Copy ALL files from this ZIP into that folder (so `index.php` sits directly inside it).
5. Open http://localhost/phpmyadmin
6. Click **Import** -> choose `database.sql` -> **Import**. (This REPLACES any earlier football_tracker database - the new version adds squads/formations.)
7. Check `config/database.php` (XAMPP default root / empty password needs no change).
8. Open http://localhost/football_tracker/
9. Open http://localhost/football_tracker/admin/
10. Login `admin` / `Admin@12345` - you must set a new password on first login.

## What is in the admin
Profile (photo, bio, details) - Matches (add/edit/delete, search, filter, sort, photos, **squad + formation editor**) - Teams (with dates = "teams played for") -
Competitions - Seasons - Venues - Goals - Assists - Trophies - Gallery - Statistics (overall/yearly/competition/position/performance) -
Calendar - Reports (career/season/competition/opponent/performance, Print / Save as PDF) - CSV export - Settings.

## How squads work
In Add/Edit Match choose a formation, type the starting XI (goalkeeper first, then defence -> attack, left -> right), tick **Me** on your row, **C** for captain, add substitutes.
The pitch view appears on the homepage ("Match Squads"), and on every match page.

Sample data is marked `is_sample = 1`; delete it with the SQL at the bottom of `database.sql`. Set `APP_DEBUG` to false in `config/config.php` when finished.


## Multi-player, registration and homepage control
- Re-import `database.sql` (phpMyAdmin > Import) when upgrading - it recreates all tables with sample data.
- Admin > Players: add/edit players, approve or reject registrations (Registration approvals), and press "Show on homepage" to choose the homepage player.
- Admin > Homepage: choose which sections appear on the homepage. Admin > Settings: open/close the public "Join" page.
- Public registration page: http://localhost/football_tracker/register.php (nothing is published until you approve).
- Admin > Teams: a team can be deleted; its matches are deleted with it (a confirmation is shown).

## Defensive contributions + editable calendar (update)
- Existing install with your own data: run `migration_defense_calendar.sql` in phpMyAdmin > SQL (database `football_tracker`). Do NOT re-import `database.sql` (it wipes data). Fresh install: just import `database.sql`.
- Match form > Player performances now has Tkl / Int / Clr / Blk / Rec columns (tackles, interceptions, clearances, blocks, recoveries). Totals show on Statistics as "Defensive Contributions".
- Admin > Calendar: add/edit/delete training, fixtures, events and reminders (click "+" on a day). They show on the public Calendar page.
