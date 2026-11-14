-- Run this in phpMyAdmin > SQL (choose the football_tracker database) if you already have data
-- and do NOT want to re-import database.sql. Safe to run once.
ALTER TABLE `match_statistics`
  ADD COLUMN `tackles`       TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `fouls`,
  ADD COLUMN `interceptions` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `tackles`,
  ADD COLUMN `clearances`    TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `interceptions`,
  ADD COLUMN `blocks`        TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `clearances`,
  ADD COLUMN `recoveries`    TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `blocks`;

CREATE TABLE IF NOT EXISTS `calendar_events` (
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
