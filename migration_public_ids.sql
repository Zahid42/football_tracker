-- Optional: adds the 6-digit public ID columns now.
-- (If you skip this file the website adds them automatically the first time any page opens.)
-- Run ONCE. IDs are filled in automatically by the website on the next page load.
ALTER TABLE players ADD COLUMN public_code CHAR(6) NULL, ADD UNIQUE KEY uq_players_code (public_code);
ALTER TABLE teams   ADD COLUMN public_code CHAR(6) NULL, ADD UNIQUE KEY uq_teams_code (public_code);

-- Teams may share a name (each player can keep his own copy)
ALTER TABLE teams DROP INDEX uq_team_name;
