-- Create the materiel_history table to track state changes
CREATE TABLE IF NOT EXISTS `materiel_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `numserie` varchar(255) NOT NULL,
  `prev_state` varchar(50) DEFAULT NULL,
  `new_state` varchar(50) NOT NULL,
  `date_change` datetime NOT NULL,
  `user_id` varchar(255) DEFAULT NULL,
  `cause` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `numserie` (`numserie`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add foreign key constraints (optional - uncomment if you want to enforce referential integrity)
-- ALTER TABLE `materiel_history` ADD CONSTRAINT `fk_materiel_history_materiel` FOREIGN KEY (`numserie`) REFERENCES `materiel` (`NumSerie`) ON DELETE CASCADE ON UPDATE CASCADE;
-- ALTER TABLE `materiel_history` ADD CONSTRAINT `fk_materiel_history_utilisateur` FOREIGN KEY (`user_id`) REFERENCES `utilisateur` (`Compte`) ON DELETE SET NULL ON UPDATE CASCADE;
