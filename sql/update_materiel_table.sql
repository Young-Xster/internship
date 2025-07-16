-- Add damage_cause column to materiel table if it doesn't already exist
ALTER TABLE `materiel` ADD COLUMN IF NOT EXISTS `damage_cause` TEXT DEFAULT NULL AFTER `observation`;
