-- Optional. The website applies this itself the first time any page opens.
INSERT INTO settings (setting_key, setting_value) VALUES ('home_sections','about,teams,achievements,fixtures,squads'),('home_layout_v','3')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
