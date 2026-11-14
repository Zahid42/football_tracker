-- =====================================================================
-- Football Tracker - database.sql
-- Import via phpMyAdmin (http://localhost/phpmyadmin -> Import).
-- Requires MySQL 5.7+ / MariaDB 10.2+ (uses a generated "result" column).
--
-- Default admin:   username: admin     password: Admin@12345
-- (you are forced to change the password on first login)
--
-- Rows with is_sample = 1 are demo data. Delete them later with the
-- statements at the very bottom of this file (or from the admin panel).
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `football_tracker`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `football_tracker`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `calendar_events`, `match_squad`, `assists`, `goals`, `match_statistics`, `matches`, `gallery`,
  `trophies`, `competitions`, `venues`, `opponents`, `player_teams`, `players`, `profile`, `teams`,
  `seasons`, `settings`, `admins`;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- admins  (single admin now; table is ready for multiple admins later)
-- ---------------------------------------------------------------------
CREATE TABLE `admins` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`            VARCHAR(50)  NOT NULL,
  `email`               VARCHAR(120) NOT NULL,
  `password_hash`       VARCHAR(255) NOT NULL,
  `must_change_password` TINYINT(1)  NOT NULL DEFAULT 0,
  `remember_selector`   VARCHAR(32)  DEFAULT NULL,
  `remember_hash`       CHAR(64)     DEFAULT NULL,
  `remember_expires`    DATETIME     DEFAULT NULL,
  `last_login_at`       DATETIME     DEFAULT NULL,
  `created_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_username` (`username`),
  UNIQUE KEY `uq_admin_email` (`email`),
  KEY `idx_admin_selector` (`remember_selector`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- seasons
-- ---------------------------------------------------------------------
CREATE TABLE `seasons` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(30)  NOT NULL,
  `start_date` DATE         DEFAULT NULL,
  `end_date`   DATE         DEFAULT NULL,
  `is_current` TINYINT(1)   NOT NULL DEFAULT 0,
  `is_sample`  TINYINT(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_season_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- teams  (teams I play FOR)
-- ---------------------------------------------------------------------
CREATE TABLE `teams` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100) NOT NULL,
  `short_name`  VARCHAR(10)  DEFAULT NULL,
  `logo`        VARCHAR(255) DEFAULT NULL,
  `founded_year` SMALLINT UNSIGNED DEFAULT NULL,
  `description` TEXT         DEFAULT NULL,
  `status`      ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `public_code` CHAR(6)      DEFAULT NULL,
  `is_sample`   TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_team_name` (`name`),
  UNIQUE KEY `uq_teams_code` (`public_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- opponents  (teams I play AGAINST; separate so head-to-head is easy later)
-- ---------------------------------------------------------------------
CREATE TABLE `opponents` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`      VARCHAR(100) NOT NULL,
  `logo`      VARCHAR(255) DEFAULT NULL,
  `is_sample` TINYINT(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_opponent_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- players  (one row per player; exactly one is "featured" = shown on the homepage)
-- ---------------------------------------------------------------------
CREATE TABLE `players` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id`          INT UNSIGNED DEFAULT NULL,
  `full_name`         VARCHAR(120) NOT NULL,
  `nickname`          VARCHAR(60)  DEFAULT NULL,
  `date_of_birth`     DATE         DEFAULT NULL,
  `preferred_position` VARCHAR(5)  DEFAULT NULL,
  `secondary_position` VARCHAR(5)  DEFAULT NULL,
  `strong_foot`       ENUM('Right','Left','Both') DEFAULT NULL,
  `height_cm`         SMALLINT UNSIGNED DEFAULT NULL,
  `weight_kg`         DECIMAL(5,1) DEFAULT NULL,
  `current_team_id`   INT UNSIGNED DEFAULT NULL,
  `jersey_number`     TINYINT UNSIGNED DEFAULT NULL,
  `playing_style`     VARCHAR(255) DEFAULT NULL,
  `bio`               TEXT         DEFAULT NULL,
  `career_start_date` DATE         DEFAULT NULL,
  `photo`             VARCHAR(255) DEFAULT NULL,
  `is_featured`       TINYINT(1)   NOT NULL DEFAULT 0,
  `is_active`         TINYINT(1)   NOT NULL DEFAULT 1,
  `approval_status`   ENUM('approved','pending','rejected') NOT NULL DEFAULT 'approved',
  `email`             VARCHAR(120) DEFAULT NULL,
  `phone`             VARCHAR(30)  DEFAULT NULL,
  `registration_note` VARCHAR(500) DEFAULT NULL,
  `registered_at`     DATETIME     DEFAULT NULL,
  `public_code`       CHAR(6)      DEFAULT NULL,
  `is_sample`         TINYINT(1)   NOT NULL DEFAULT 0,
  `updated_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_players_code` (`public_code`),
  KEY `idx_player_status` (`approval_status`),
  KEY `idx_player_admin` (`admin_id`),
  KEY `idx_player_featured` (`is_featured`),
  CONSTRAINT `fk_player_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_player_team`  FOREIGN KEY (`current_team_id`) REFERENCES `teams`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- player_teams  (the teams each player has played for, with dates)
-- ---------------------------------------------------------------------
CREATE TABLE `player_teams` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `player_id`  INT UNSIGNED NOT NULL,
  `team_id`    INT UNSIGNED NOT NULL,
  `start_date` DATE DEFAULT NULL,
  `end_date`   DATE DEFAULT NULL,
  `is_current` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_pt_player` (`player_id`),
  KEY `idx_pt_team` (`team_id`),
  CONSTRAINT `fk_pt_player` FOREIGN KEY (`player_id`) REFERENCES `players`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pt_team`   FOREIGN KEY (`team_id`)   REFERENCES `teams`(`id`)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- competitions
-- ---------------------------------------------------------------------
CREATE TABLE `competitions` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(150) NOT NULL,
  `season_id`   INT UNSIGNED DEFAULT NULL,
  `type`        ENUM('Tournament','League','Friendly','Training','Other') NOT NULL DEFAULT 'Tournament',
  `organizer`   VARCHAR(150) DEFAULT NULL,
  `description` TEXT         DEFAULT NULL,
  `logo`        VARCHAR(255) DEFAULT NULL,
  `is_sample`   TINYINT(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_comp_season` (`season_id`),
  CONSTRAINT `fk_comp_season` FOREIGN KEY (`season_id`) REFERENCES `seasons`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- venues
-- ---------------------------------------------------------------------
CREATE TABLE `venues` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(150) NOT NULL,
  `location`   VARCHAR(255) DEFAULT NULL,
  `city`       VARCHAR(80)  DEFAULT NULL,
  `country`    VARCHAR(80)  DEFAULT 'Bangladesh',
  `pitch_type` ENUM('Grass','Artificial Turf','Futsal','Concrete','Indoor','Other') DEFAULT NULL,
  `notes`      TEXT         DEFAULT NULL,
  `is_sample`  TINYINT(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- matches  (the match itself)
-- `result` is a STORED generated column: it can never disagree with the score.
-- ---------------------------------------------------------------------
CREATE TABLE `matches` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `season_id`      INT UNSIGNED NOT NULL,
  `competition_id` INT UNSIGNED DEFAULT NULL,
  `venue_id`       INT UNSIGNED DEFAULT NULL,
  `team_id`        INT UNSIGNED NOT NULL,
  `opponent_id`    INT UNSIGNED NOT NULL,
  `match_date`     DATE         NOT NULL,
  `kickoff_time`   TIME         DEFAULT NULL,
  `match_type`     ENUM('League','Tournament','Friendly','Training','Knockout','Final','Other') NOT NULL DEFAULT 'Friendly',
  `home_away`      ENUM('Home','Away','Neutral') NOT NULL DEFAULT 'Neutral',
  `formation`      VARCHAR(10)  DEFAULT NULL,
  `team_score`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `opponent_score` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `result`         CHAR(1) GENERATED ALWAYS AS (
                     CASE WHEN `team_score` > `opponent_score` THEN 'W'
                          WHEN `team_score` < `opponent_score` THEN 'L'
                          ELSE 'D' END) STORED,
  `notes`          TEXT         DEFAULT NULL,
  `photo`          VARCHAR(255) DEFAULT NULL,
  `team_photo`     VARCHAR(255) DEFAULT NULL,
  `is_sample`      TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_match_date`   (`match_date`),
  KEY `idx_match_season` (`season_id`),
  KEY `idx_match_comp`   (`competition_id`),
  KEY `idx_match_team`   (`team_id`),
  KEY `idx_match_opp`    (`opponent_id`),
  KEY `idx_match_venue`  (`venue_id`),
  KEY `idx_match_result` (`result`),
  CONSTRAINT `fk_match_season` FOREIGN KEY (`season_id`)      REFERENCES `seasons`(`id`)      ON DELETE RESTRICT,
  CONSTRAINT `fk_match_comp`   FOREIGN KEY (`competition_id`) REFERENCES `competitions`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_match_venue`  FOREIGN KEY (`venue_id`)       REFERENCES `venues`(`id`)       ON DELETE SET NULL,
  CONSTRAINT `fk_match_team`   FOREIGN KEY (`team_id`)        REFERENCES `teams`(`id`)        ON DELETE RESTRICT,
  CONSTRAINT `fk_match_opp`    FOREIGN KEY (`opponent_id`)    REFERENCES `opponents`(`id`)    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- match_statistics  (one row per player per match)
-- ---------------------------------------------------------------------
CREATE TABLE `match_statistics` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `match_id`      INT UNSIGNED NOT NULL,
  `player_id`     INT UNSIGNED NOT NULL,
  `position`      VARCHAR(5)   DEFAULT NULL,
  `started`       TINYINT(1)   NOT NULL DEFAULT 1,
  `minutes_played` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `goals`         TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `assists`       TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `yellow_cards`  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `red_cards`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `own_goals`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `penalty_goals` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `fouls`         TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `tackles`       TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `interceptions` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `clearances`    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `blocks`        TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `recoveries`    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `rating`        DECIMAL(3,1) DEFAULT NULL,
  `is_captain`    TINYINT(1)   NOT NULL DEFAULT 0,
  `is_motm`       TINYINT(1)   NOT NULL DEFAULT 0,
  `remarks`       VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_stats_match_player` (`match_id`,`player_id`),
  KEY `idx_stats_player` (`player_id`),
  KEY `idx_stats_position` (`position`),
  CONSTRAINT `fk_stats_match` FOREIGN KEY (`match_id`) REFERENCES `matches`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_stats_player` FOREIGN KEY (`player_id`) REFERENCES `players`(`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_rating` CHECK (`rating` IS NULL OR (`rating` >= 0 AND `rating` <= 10))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- goals
-- ---------------------------------------------------------------------
CREATE TABLE `goals` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `match_id`    INT UNSIGNED NOT NULL,
  `player_id`   INT UNSIGNED NOT NULL,
  `minute`      TINYINT UNSIGNED DEFAULT NULL,
  `goal_type`   ENUM('Open Play','Header','Penalty','Free Kick','Long Range','Tap In','Counter Attack','Other') NOT NULL DEFAULT 'Open Play',
  `assisted_by` VARCHAR(100) DEFAULT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `is_sample`   TINYINT(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_goal_match` (`match_id`),
  KEY `idx_goal_type`  (`goal_type`),
  KEY `idx_goal_player` (`player_id`),
  CONSTRAINT `fk_goal_match` FOREIGN KEY (`match_id`) REFERENCES `matches`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_goal_player` FOREIGN KEY (`player_id`) REFERENCES `players`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- assists
-- ---------------------------------------------------------------------
CREATE TABLE `assists` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `match_id`     INT UNSIGNED NOT NULL,
  `player_id`    INT UNSIGNED NOT NULL,
  `minute`       TINYINT UNSIGNED DEFAULT NULL,
  `assist_type`  ENUM('Through Ball','Cross','Corner','Free Kick','Cut Back','Pass','Set Piece','Other') NOT NULL DEFAULT 'Pass',
  `goal_scorer`  VARCHAR(100) DEFAULT NULL,
  `description`  VARCHAR(255) DEFAULT NULL,
  `is_sample`    TINYINT(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_assist_match` (`match_id`),
  KEY `idx_assist_player` (`player_id`),
  CONSTRAINT `fk_assist_match` FOREIGN KEY (`match_id`) REFERENCES `matches`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assist_player` FOREIGN KEY (`player_id`) REFERENCES `players`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- trophies
-- ---------------------------------------------------------------------
CREATE TABLE `trophies` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `player_id`       INT UNSIGNED NOT NULL,
  `tournament_name` VARCHAR(150) NOT NULL,
  `year`            SMALLINT UNSIGNED NOT NULL,
  `team_id`         INT UNSIGNED DEFAULT NULL,
  `achievement`     ENUM('Champion','Runner-up','Third Place','Best Player','Top Scorer','Top Assist','Other') NOT NULL DEFAULT 'Champion',
  `position`        VARCHAR(30)  DEFAULT NULL,
  `description`     TEXT         DEFAULT NULL,
  `image`           VARCHAR(255) DEFAULT NULL,
  `is_sample`       TINYINT(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_trophy_year` (`year`),
  KEY `idx_trophy_player` (`player_id`),
  KEY `idx_trophy_team` (`team_id`),
  CONSTRAINT `fk_trophy_player` FOREIGN KEY (`player_id`) REFERENCES `players`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_trophy_team` FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- match_squad  (my team's line-up for a match: slots 1-11 = starting XI in
-- formation order (GK first), slots 12+ = substitutes)
-- ---------------------------------------------------------------------
CREATE TABLE `match_squad` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `match_id`     INT UNSIGNED NOT NULL,
  `slot`         TINYINT UNSIGNED NOT NULL,
  `player_name`  VARCHAR(80)  NOT NULL,
  `shirt_number` TINYINT UNSIGNED DEFAULT NULL,
  `position`     VARCHAR(5)   DEFAULT NULL,
  `player_id`    INT UNSIGNED DEFAULT NULL,
  `is_captain`   TINYINT(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_squad_slot` (`match_id`,`slot`),
  KEY `idx_squad_player` (`player_id`),
  CONSTRAINT `fk_squad_match` FOREIGN KEY (`match_id`) REFERENCES `matches`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_squad_player` FOREIGN KEY (`player_id`) REFERENCES `players`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- gallery
-- ---------------------------------------------------------------------
CREATE TABLE `gallery` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `player_id`  INT UNSIGNED DEFAULT NULL,
  `category`   ENUM('Match Photos','Team Photos','Tournament Photos','Training Photos','Trophies','Personal') NOT NULL DEFAULT 'Match Photos',
  `caption`    VARCHAR(255) DEFAULT NULL,
  `image`      VARCHAR(255) NOT NULL,
  `photo_date` DATE         DEFAULT NULL,
  `match_id`   INT UNSIGNED DEFAULT NULL,
  `is_sample`  TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_gallery_cat` (`category`),
  KEY `idx_gallery_match` (`match_id`),
  KEY `idx_gallery_player` (`player_id`),
  CONSTRAINT `fk_gallery_player` FOREIGN KEY (`player_id`) REFERENCES `players`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_gallery_match` FOREIGN KEY (`match_id`) REFERENCES `matches`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- calendar_events  (editable from Admin > Calendar; player_id NULL = shown for everyone)
-- ---------------------------------------------------------------------
CREATE TABLE `calendar_events` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `player_id`   INT UNSIGNED DEFAULT NULL,
  `title`       VARCHAR(150) NOT NULL,
  `event_type`  ENUM('Match','Training','Event','Reminder','Other') NOT NULL DEFAULT 'Event',
  `event_date`  DATE NOT NULL,
  `start_time`  TIME DEFAULT NULL,
  `end_time`    TIME DEFAULT NULL,
  `location`    VARCHAR(150) DEFAULT NULL,
  `notes`       TEXT DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cal_date` (`event_date`),
  CONSTRAINT `fk_cal_player` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- settings  (key/value)
-- ---------------------------------------------------------------------
CREATE TABLE `settings` (
  `setting_key`   VARCHAR(60) NOT NULL,
  `setting_value` TEXT        DEFAULT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- DATA
-- =====================================================================

-- Default admin (password: Admin@12345) - must change on first login
INSERT INTO `admins` (`id`,`username`,`email`,`password_hash`,`must_change_password`) VALUES
(1,'admin','admin@example.com','$2y$10$6WAbNPGYwN7mg23GkAfSieGQZOc.H5SUjKIYoJaNtugv3x1fM3Yg.',1);

INSERT INTO `settings` (`setting_key`,`setting_value`) VALUES
('site_title','Football Tracker'),
('site_tagline','Personal Football Profile & Match Tracker'),
('rating_scale_max','10'),
('home_show_players','1'),
('registration_open','1'),
('home_sections','about,teams,achievements,fixtures,squads'),
('home_layout_v','3'),
('home_chips','foot,height,age,matches,goals,assists'),
('about_text','This website is my personal football record: every match I have played, the teams I played for, my statistics, trophies and photos. Edit this text from Admin > Settings.'),
('contact_email','');

-- ---- SAMPLE DATA (is_sample = 1) ------------------------------------
INSERT INTO `seasons` (`id`,`name`,`start_date`,`end_date`,`is_current`,`is_sample`) VALUES
(1,'2025','2025-01-01','2025-12-31',0,1),
(2,'2026','2026-01-01','2026-12-31',1,1);

INSERT INTO `teams` (`id`,`name`,`short_name`,`founded_year`,`description`,`status`,`is_sample`) VALUES
(1,'Team Zahid','TZD',2024,'Sample team - main side.','active',1),
(2,'Riverside FC','RFC',2019,'Sample team - played here in 2025.','inactive',1),
(3,'Office Warriors','OWR',2022,'Sample team - corporate league side.','active',1);

INSERT INTO `opponents` (`id`,`name`,`is_sample`) VALUES
(1,'Team A',1),(2,'Dhaka Strikers',1),(3,'Blue Hawks',1),(4,'Red Lions',1),
(5,'Gulshan United',1),(6,'Thunder FC',1),(7,'Metro Boys',1);

INSERT INTO `competitions` (`id`,`name`,`season_id`,`type`,`organizer`,`description`,`is_sample`) VALUES
(1,'JBPLC Football Tournament',NULL,'Tournament','JBPLC','Sample tournament.',1),
(2,'Corporate Football League',NULL,'League','Corporate Sports Board','Sample league.',1),
(3,'Office Tournament',2,'Tournament','HR Department','Sample office cup.',1);

INSERT INTO `venues` (`id`,`name`,`location`,`city`,`country`,`pitch_type`,`notes`,`is_sample`) VALUES
(1,'Green Field Arena','Banani','Dhaka','Bangladesh','Artificial Turf','Sample venue.',1),
(2,'City Sports Complex','Uttara','Dhaka','Bangladesh','Grass','Sample venue.',1);

INSERT INTO `players` (`id`,`admin_id`,`full_name`,`nickname`,`date_of_birth`,`preferred_position`,`secondary_position`,`strong_foot`,`height_cm`,`weight_kg`,`current_team_id`,`jersey_number`,`playing_style`,`bio`,`career_start_date`,`is_featured`,`is_active`,`is_sample`) VALUES
(1,1,'Kazi Zahidul Islam','Zahid','1995-01-01','CAM','CM','Right',172,68.0,1,10,'Creative playmaker with a good eye for the final pass.',
 'Attacking midfielder who loves to create chances and arrive late in the box. Edit this biography from Admin > Players.','2024-01-01',1,1,1),
(2,1,'Rafiqul Ahmed','Rafi','1997-05-14','LW','RW','Left',169,64.0,1,7,'Quick winger who cuts inside onto his stronger foot.',
 'Sample player: pacey left winger and Zahid''s favourite partner on the left side.','2024-06-01',0,1,1),
(3,1,'Imran Hossain','Imran','1994-09-30','CB','LB','Right',181,77.0,1,4,'Commanding centre-back, strong in the air.',
 'Sample player: reliable defender and dead-ball threat at set pieces.','2024-03-01',0,1,1);

INSERT INTO `player_teams` (`player_id`,`team_id`,`start_date`,`end_date`,`is_current`) VALUES
(1,2,'2025-01-01','2025-12-31',0),
(1,3,'2025-09-01',NULL,0),
(1,1,'2025-01-01',NULL,1),
(2,1,'2025-02-01',NULL,1),
(3,1,'2025-03-01',NULL,1);

-- 10 matches. Match ids 1..10 are referenced by goals / assists below.
INSERT INTO `matches` (`id`,`season_id`,`competition_id`,`venue_id`,`team_id`,`opponent_id`,`match_date`,`kickoff_time`,`match_type`,`home_away`,`formation`,`team_score`,`opponent_score`,`notes`,`is_sample`) VALUES
(1,1,NULL,1,2,1,'2025-03-15','17:00:00','Friendly','Neutral','4-3-3',2,1,'Sample: comfortable friendly win.',1),
(2,1,1,1,1,2,'2025-05-10','16:30:00','Tournament','Neutral','4-2-3-1',3,3,'Sample: wild draw, equalised late.',1),
(3,1,1,2,1,3,'2025-08-23','18:00:00','Tournament','Neutral','4-2-3-1',1,2,'Sample: tough loss.',1),
(4,1,2,1,3,4,'2025-11-08','19:00:00','League','Home','4-2-3-1',4,0,'Sample: best performance of the year.',1),
(5,2,2,1,3,5,'2026-01-17','19:00:00','League','Away','4-3-3',2,2,'Sample: late equaliser conceded.',1),
(6,2,1,2,1,6,'2026-03-14','17:30:00','Tournament','Neutral','4-2-3-1',3,1,'Sample: quarter-final win.',1),
(7,2,NULL,1,1,7,'2026-05-02','16:00:00','Friendly','Home','4-4-2',5,2,'Sample: played up front, two goals two assists.',1),
(8,2,1,2,1,2,'2026-07-18','18:00:00','Tournament','Neutral','4-2-3-1',0,1,'Sample: unlucky loss.',1),
(9,2,3,1,3,1,'2026-09-12','17:00:00','Tournament','Neutral','4-2-3-1',2,0,'Sample: clean sheet win.',1),
(10,2,1,2,1,3,'2026-10-31','17:00:00','Final','Neutral','4-2-3-1',3,2,'Sample: tournament final win.',1);

INSERT INTO `match_statistics` (`match_id`,`player_id`,`position`,`started`,`minutes_played`,`goals`,`assists`,`yellow_cards`,`red_cards`,`own_goals`,`penalty_goals`,`fouls`,`rating`,`is_captain`,`is_motm`) VALUES
(1,1,'CM',1,60,0,1,0,0,0,0,1,7.2,0,0),
(2,1,'CAM',1,70,1,1,0,0,0,0,2,7.8,0,0),
(3,1,'CAM',1,70,0,0,0,0,0,0,3,6.4,0,0),
(4,1,'CAM',1,60,2,1,0,0,0,1,0,8.9,0,1),
(5,1,'CM',1,60,1,0,0,0,0,0,2,7.0,0,0),
(6,1,'CAM',1,70,1,1,0,0,0,0,1,8.2,0,0),
(7,1,'ST',1,50,2,2,0,0,0,0,0,9.0,1,1),
(8,1,'CAM',1,70,0,0,1,0,0,0,4,6.1,0,0),
(9,1,'CAM',1,70,1,1,0,0,0,0,1,8.0,0,0),
(10,1,'CAM',1,70,1,1,0,0,0,0,1,8.2,1,1),
(1,3,'CB',1,70,0,1,0,0,0,0,2,7.0,0,0),
(2,2,'LM',1,70,1,0,0,0,0,0,1,7.6,0,0),
(3,3,'CB',1,70,0,0,0,0,0,0,2,6.3,0,0),
(4,3,'CB',1,70,1,0,0,0,0,0,2,7.8,0,0),
(4,2,'LM',1,70,1,1,0,0,0,0,1,8.0,0,0),
(5,2,'LW',1,70,0,1,0,0,0,0,1,7.4,0,0),
(6,3,'CB',1,70,1,0,0,0,0,0,2,7.5,0,0),
(7,2,'LM',1,70,1,1,0,0,0,0,1,8.2,0,0),
(8,3,'CB',1,70,0,0,0,0,0,0,2,6.6,0,0),
(9,2,'LM',1,70,1,0,0,0,0,0,1,7.7,0,0),
(10,3,'CB',1,70,0,0,0,0,0,0,2,7.1,0,0),
(10,2,'LM',1,70,0,1,0,0,0,0,1,7.3,0,0);

INSERT INTO `match_squad` (`match_id`,`slot`,`player_name`,`shirt_number`,`position`,`player_id`,`is_captain`) VALUES
(1,1,'Limon',1,'GK',NULL,0),
(1,2,'Bappy',3,'LB',NULL,0),
(1,3,'Imran',4,'CB',3,0),
(1,4,'Asif',8,'CB',NULL,0),
(1,5,'Fahim',17,'RB',NULL,0),
(1,6,'Zahid',10,'CM',1,0),
(1,7,'Masud',24,'CM',NULL,0),
(1,8,'Tamim',13,'CM',NULL,0),
(1,9,'Arif',19,'LW',NULL,0),
(1,10,'Nayeem',15,'ST',NULL,0),
(1,11,'Naim',28,'RW',NULL,0),
(1,12,'Rony',12,'GK',NULL,0),
(1,13,'Joy',6,'CB',NULL,0),
(1,14,'Sabbir',27,'CM',NULL,0),
(1,15,'Sakib',23,'LW',NULL,0),
(1,16,'Rubel',5,'ST',NULL,0),
(2,1,'Asif',1,'GK',NULL,0),
(2,2,'Rony',11,'LB',NULL,0),
(2,3,'Tamim',9,'CB',NULL,0),
(2,4,'Bappy',23,'CB',NULL,0),
(2,5,'Sabbir',13,'RB',NULL,0),
(2,6,'Joy',17,'CDM',NULL,0),
(2,7,'Tanvir',22,'CDM',NULL,0),
(2,8,'Rafi',7,'LM',2,0),
(2,9,'Zahid',10,'CAM',1,0),
(2,10,'Limon',27,'RM',NULL,0),
(2,11,'Hasan',20,'ST',NULL,0),
(2,12,'Shuvo',12,'GK',NULL,0),
(2,13,'Sakib',16,'CB',NULL,0),
(2,14,'Mahin',19,'CM',NULL,0),
(2,15,'Nayeem',29,'LW',NULL,0),
(2,16,'Fahim',28,'ST',NULL,0),
(3,1,'Masud',1,'GK',NULL,0),
(3,2,'Nayeem',29,'LB',NULL,0),
(3,3,'Imran',4,'CB',3,0),
(3,4,'Shuvo',14,'CB',NULL,0),
(3,5,'Rubel',6,'RB',NULL,0),
(3,6,'Joy',15,'CDM',NULL,0),
(3,7,'Hasan',28,'CDM',NULL,0),
(3,8,'Mahin',22,'LM',NULL,0),
(3,9,'Zahid',10,'CAM',1,0),
(3,10,'Tanvir',16,'RM',NULL,0),
(3,11,'Limon',26,'ST',NULL,0),
(3,12,'Sakib',12,'GK',NULL,0),
(3,13,'Sabbir',5,'CB',NULL,0),
(3,14,'Arif',18,'CM',NULL,0),
(3,15,'Rony',17,'LW',NULL,0),
(3,16,'Bappy',24,'ST',NULL,0),
(4,1,'Fahim',1,'GK',NULL,0),
(4,2,'Tamim',16,'LB',NULL,0),
(4,3,'Imran',4,'CB',3,0),
(4,4,'Sabbir',20,'CB',NULL,0),
(4,5,'Arif',3,'RB',NULL,0),
(4,6,'Nayeem',24,'CDM',NULL,0),
(4,7,'Shuvo',21,'CDM',NULL,0),
(4,8,'Rafi',7,'LM',2,0),
(4,9,'Zahid',10,'CAM',1,0),
(4,10,'Asif',28,'RM',NULL,0),
(4,11,'Joy',19,'ST',NULL,0),
(4,12,'Sakib',12,'GK',NULL,0),
(4,13,'Tanvir',25,'CB',NULL,0),
(4,14,'Naim',23,'CM',NULL,0),
(4,15,'Bappy',29,'LW',NULL,0),
(4,16,'Rubel',9,'ST',NULL,0),
(5,1,'Tamim',1,'GK',NULL,0),
(5,2,'Rony',11,'LB',NULL,0),
(5,3,'Asif',9,'CB',NULL,0),
(5,4,'Sakib',20,'CB',NULL,0),
(5,5,'Arif',25,'RB',NULL,0),
(5,6,'Zahid',10,'CM',1,0),
(5,7,'Nayeem',29,'CM',NULL,0),
(5,8,'Limon',24,'CM',NULL,0),
(5,9,'Rafi',7,'LW',2,0),
(5,10,'Joy',28,'ST',NULL,0),
(5,11,'Fahim',17,'RW',NULL,0),
(5,12,'Masud',12,'GK',NULL,0),
(5,13,'Shuvo',5,'CB',NULL,0),
(5,14,'Bappy',23,'CM',NULL,0),
(5,15,'Hasan',8,'LW',NULL,0),
(5,16,'Sabbir',21,'ST',NULL,0),
(6,1,'Nayeem',1,'GK',NULL,0),
(6,2,'Fahim',25,'LB',NULL,0),
(6,3,'Imran',4,'CB',3,0),
(6,4,'Shuvo',29,'CB',NULL,0),
(6,5,'Limon',23,'RB',NULL,0),
(6,6,'Arif',14,'CDM',NULL,0),
(6,7,'Sakib',18,'CDM',NULL,0),
(6,8,'Sabbir',21,'LM',NULL,0),
(6,9,'Zahid',10,'CAM',1,0),
(6,10,'Tanvir',9,'RM',NULL,0),
(6,11,'Hasan',13,'ST',NULL,0),
(6,12,'Naim',12,'GK',NULL,0),
(6,13,'Rony',19,'CB',NULL,0),
(6,14,'Joy',2,'CM',NULL,0),
(6,15,'Rubel',15,'LW',NULL,0),
(6,16,'Mahin',28,'ST',NULL,0),
(7,1,'Naim',1,'GK',NULL,0),
(7,2,'Nayeem',29,'LB',NULL,0),
(7,3,'Tamim',9,'CB',NULL,0),
(7,4,'Mahin',6,'CB',NULL,0),
(7,5,'Asif',8,'RB',NULL,0),
(7,6,'Rafi',7,'LM',2,0),
(7,7,'Tanvir',24,'CM',NULL,0),
(7,8,'Rubel',16,'CM',NULL,0),
(7,9,'Rony',17,'RM',NULL,0),
(7,10,'Zahid',10,'ST',1,1),
(7,11,'Fahim',3,'ST',NULL,0),
(7,12,'Limon',12,'GK',NULL,0),
(7,13,'Sabbir',20,'CB',NULL,0),
(7,14,'Bappy',23,'CM',NULL,0),
(7,15,'Shuvo',15,'LW',NULL,0),
(7,16,'Sakib',22,'ST',NULL,0),
(8,1,'Rubel',1,'GK',NULL,0),
(8,2,'Limon',18,'LB',NULL,0),
(8,3,'Imran',4,'CB',3,0),
(8,4,'Asif',20,'CB',NULL,0),
(8,5,'Rony',3,'RB',NULL,0),
(8,6,'Masud',23,'CDM',NULL,0),
(8,7,'Fahim',13,'CDM',NULL,0),
(8,8,'Tanvir',9,'LM',NULL,0),
(8,9,'Zahid',10,'CAM',1,0),
(8,10,'Bappy',5,'RM',NULL,0),
(8,11,'Joy',28,'ST',NULL,0),
(8,12,'Sabbir',12,'GK',NULL,0),
(8,13,'Hasan',6,'CB',NULL,0),
(8,14,'Naim',22,'CM',NULL,0),
(8,15,'Tamim',16,'LW',NULL,0),
(8,16,'Arif',8,'ST',NULL,0),
(9,1,'Fahim',1,'GK',NULL,0),
(9,2,'Shuvo',17,'LB',NULL,0),
(9,3,'Masud',2,'CB',NULL,0),
(9,4,'Rubel',29,'CB',NULL,0),
(9,5,'Limon',15,'RB',NULL,0),
(9,6,'Naim',26,'CDM',NULL,0),
(9,7,'Arif',13,'CDM',NULL,0),
(9,8,'Rafi',7,'LM',2,0),
(9,9,'Zahid',10,'CAM',1,0),
(9,10,'Sakib',22,'RM',NULL,0),
(9,11,'Asif',16,'ST',NULL,0),
(9,12,'Joy',12,'GK',NULL,0),
(9,13,'Mahin',24,'CB',NULL,0),
(9,14,'Tanvir',23,'CM',NULL,0),
(9,15,'Tamim',19,'LW',NULL,0),
(9,16,'Rony',11,'ST',NULL,0),
(10,1,'Fahim',1,'GK',NULL,0),
(10,2,'Tanvir',16,'LB',NULL,0),
(10,3,'Imran',4,'CB',3,0),
(10,4,'Bappy',21,'CB',NULL,0),
(10,5,'Tamim',29,'RB',NULL,0),
(10,6,'Mahin',15,'CDM',NULL,0),
(10,7,'Hasan',18,'CDM',NULL,0),
(10,8,'Rafi',7,'LM',2,0),
(10,9,'Zahid',10,'CAM',1,1),
(10,10,'Joy',2,'RM',NULL,0),
(10,11,'Limon',22,'ST',NULL,0),
(10,12,'Sabbir',12,'GK',NULL,0),
(10,13,'Nayeem',8,'CB',NULL,0),
(10,14,'Shuvo',13,'CM',NULL,0),
(10,15,'Arif',19,'LW',NULL,0),
(10,16,'Asif',25,'ST',NULL,0);

-- 9 goals (matches the per-match goal totals above)
INSERT INTO `goals` (`match_id`,`player_id`,`minute`,`goal_type`,`assisted_by`,`description`,`is_sample`) VALUES
(2,1,34,'Open Play','Rafi','Sample: low finish from the edge of the box.',1),
(4,1,12,'Tap In',NULL,'Sample: rebound after a shot.',1),
(4,1,55,'Penalty',NULL,'Sample: penalty, bottom left.',1),
(5,1,41,'Free Kick',NULL,'Sample: curled over the wall.',1),
(6,1,67,'Long Range','Sabbir','Sample: 25-yard strike.',1),
(7,1,18,'Header','Imran','Sample: header from a corner.',1),
(7,1,44,'Counter Attack',NULL,'Sample: finished a fast break.',1),
(9,1,29,'Open Play','Rafi','Sample: slotted past the keeper.',1),
(10,1,58,'Open Play','Tanvir','Sample: winner in the final.',1);

-- 8 assists (matches the per-match assist totals above)
INSERT INTO `assists` (`match_id`,`player_id`,`minute`,`assist_type`,`goal_scorer`,`description`,`is_sample`) VALUES
(1,1,40,'Through Ball','Rafi','Sample: split the defence.',1),
(2,1,62,'Cross','Sabbir','Sample: whipped cross.',1),
(4,1,30,'Pass','Imran','Sample: simple pass, great finish.',1),
(6,1,20,'Corner','Tanvir','Sample: corner to the far post.',1),
(7,1,22,'Cut Back','Rafi','Sample: cut back from the byline.',1),
(7,1,38,'Through Ball','Sabbir','Sample: through ball for a tap-in.',1),
(9,1,50,'Free Kick','Imran','Sample: free kick headed in.',1),
(10,1,80,'Through Ball','Tanvir','Sample: late through ball.',1);

INSERT INTO `trophies` (`player_id`,`tournament_name`,`year`,`team_id`,`achievement`,`position`,`description`,`is_sample`) VALUES
(1,'JBPLC Football Tournament',2026,1,'Champion','1st','Sample trophy: won the final 3-2.',1),
(1,'Corporate Football League',2025,3,'Runner-up','2nd','Sample trophy.',1),
(1,'Office Tournament',2026,3,'Third Place','3rd','Sample trophy.',1),
(2,'JBPLC Football Tournament',2026,1,'Champion','1st','Sample trophy: won the final 3-2.',1),
(3,'JBPLC Football Tournament',2026,1,'Champion','1st','Sample trophy: won the final 3-2.',1),
(3,'Corporate Football League',2025,3,'Best Player','MVP','Sample trophy: defender of the season.',1);

-- 10 sample gallery rows (placeholder images are in uploads/gallery/sample_XX.png)
INSERT INTO `gallery` (`category`,`caption`,`image`,`photo_date`,`match_id`,`is_sample`) VALUES
('Match Photos','Sample photo 1','gallery/sample_01.png','2026-10-31',10,1),
('Match Photos','Sample photo 2','gallery/sample_02.png','2026-05-02',7,1),
('Match Photos','Sample photo 3','gallery/sample_03.png','2025-11-08',4,1),
('Team Photos','Sample photo 4','gallery/sample_04.png','2026-03-14',NULL,1),
('Team Photos','Sample photo 5','gallery/sample_05.png','2025-01-20',NULL,1),
('Tournament Photos','Sample photo 6','gallery/sample_06.png','2026-10-31',10,1),
('Tournament Photos','Sample photo 7','gallery/sample_07.png','2026-03-14',6,1),
('Training Photos','Sample photo 8','gallery/sample_08.png','2026-02-10',NULL,1),
('Trophies','Sample photo 9','gallery/sample_09.png','2026-10-31',NULL,1),
('Personal','Sample photo 10','gallery/sample_10.png','2026-01-01',NULL,1);

-- Defensive contributions + calendar events (sample)
UPDATE `match_statistics` SET `tackles` = 1 + (`id` MOD 4), `interceptions` = `id` MOD 3, `clearances` = (`id` MOD 5), `blocks` = (`id` MOD 2), `recoveries` = 3 + (`id` MOD 6);
UPDATE `match_statistics` s JOIN `players` p ON p.id = s.player_id SET s.`tackles` = s.`tackles` + 4, s.`interceptions` = s.`interceptions` + 3, s.`clearances` = s.`clearances` + 6, s.`blocks` = s.`blocks` + 2 WHERE p.preferred_position IN ('CB','LB','RB','CDM');
INSERT INTO `calendar_events` (`player_id`,`title`,`event_type`,`event_date`,`start_time`,`end_time`,`location`,`notes`) VALUES
(NULL,'Team training session','Training','2026-11-16','17:00:00','18:30:00','Local Ground','Bring boots and water.'),
(NULL,'Friendly vs Riverside FC','Match','2026-11-21','16:00:00','17:30:00','Central Park Turf','Upcoming fixture - add the result after the game.'),
(NULL,'Fitness test','Event','2026-11-28','09:00:00',NULL,'Sports Complex',NULL),
(NULL,'Renew team registration','Reminder','2026-12-05',NULL,NULL,NULL,'Deadline for the new season.');

-- =====================================================================
-- DELETE SAMPLE DATA LATER (run in phpMyAdmin > SQL):
--   DELETE FROM gallery  WHERE is_sample = 1;
--   DELETE FROM trophies WHERE is_sample = 1;
--   DELETE FROM matches  WHERE is_sample = 1;  -- cascades to goals/assists/stats
--   DELETE FROM goals    WHERE is_sample = 1;
--   DELETE FROM assists  WHERE is_sample = 1;
--   DELETE FROM competitions WHERE is_sample = 1;
--   DELETE FROM venues   WHERE is_sample = 1;
--   DELETE FROM opponents WHERE is_sample = 1;
--   UPDATE players SET current_team_id = NULL;
--   DELETE FROM teams    WHERE is_sample = 1;
--   DELETE FROM seasons  WHERE is_sample = 1;
-- =====================================================================
