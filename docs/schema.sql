-- Database schema of the Palapeli web (structure only, no data).
-- Generated from the production copy with mysqldump --no-data; MariaDB 10.4.

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ciphers` (
  `year` tinyint(4) NOT NULL,
  `checkpoint_number` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `cipher_description` varchar(4095) DEFAULT NULL,
  `cipher_image_id` int(11) DEFAULT NULL,
  `solution_description` varchar(4095) DEFAULT NULL,
  `solution_image_id` int(11) DEFAULT NULL,
  `pdf_file_id` int(11) DEFAULT NULL,
  `solution` varchar(1023) DEFAULT NULL,
  `dead_solution` varchar(1023) DEFAULT NULL COMMENT 'text that is displayed to team after he asks for solution during game',
  `code` varchar(255) DEFAULT NULL,
  `checkpoint_close_time` time DEFAULT NULL,
  `specification` varchar(255) DEFAULT NULL,
  `has_password_solution` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`year`,`checkpoint_number`),
  KEY `year` (`year`),
  KEY `cipher_image_id` (`cipher_image_id`),
  KEY `solution_image_id` (`solution_image_id`),
  KEY `pdf_file_id` (`pdf_file_id`),
  CONSTRAINT `fk_cipher` FOREIGN KEY (`cipher_image_id`) REFERENCES `files` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_pdf` FOREIGN KEY (`pdf_file_id`) REFERENCES `files` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_solution` FOREIGN KEY (`solution_image_id`) REFERENCES `files` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_year` FOREIGN KEY (`year`) REFERENCES `years` (`year`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `discussion` (
  `id` int(7) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `team_id` int(4) DEFAULT NULL,
  `unlogged_team_name` varchar(255) DEFAULT NULL,
  `created` timestamp NOT NULL DEFAULT current_timestamp(),
  `message` varchar(5000) NOT NULL,
  `thread` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `team_id` (`team_id`),
  CONSTRAINT `discussion_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `path` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team_id` int(11) DEFAULT NULL,
  `year` int(11) DEFAULT NULL,
  `checkpoint_number` int(11) DEFAULT NULL,
  `time` datetime NOT NULL DEFAULT current_timestamp(),
  `message` varchar(511) DEFAULT NULL,
  `type_id` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `log_type` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `year` tinyint(4) NOT NULL,
  `link` varchar(1023) NOT NULL,
  `team_id` int(4) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `description` varchar(1023) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `team_id` (`team_id`),
  KEY `year` (`year`),
  CONSTRAINT `fk_reports_year` FOREIGN KEY (`year`) REFERENCES `years` (`year`),
  CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `results` (
  `team_id` int(4) NOT NULL,
  `year` tinyint(4) NOT NULL,
  `checkpoint_number` tinyint(4) NOT NULL,
  `entry_time` time DEFAULT NULL,
  `exit_time` time DEFAULT NULL,
  `used_hint` tinyint(2) DEFAULT NULL,
  `entry_datetime` datetime DEFAULT NULL,
  `exit_datetime` datetime DEFAULT NULL,
  PRIMARY KEY (`team_id`,`checkpoint_number`,`year`),
  KEY `team_id` (`team_id`),
  KEY `year` (`year`),
  CONSTRAINT `fk_results_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_results_year` FOREIGN KEY (`year`) REFERENCES `years` (`year`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `teams` (
  `id` int(4) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `password` varchar(150) NOT NULL,
  `phone1` varchar(20) NOT NULL,
  `phone2` varchar(20) NOT NULL,
  `email1` varchar(150) NOT NULL,
  `email2` varchar(150) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `teamsyear` (
  `team_id` int(4) NOT NULL,
  `year` tinyint(4) NOT NULL,
  `paid` tinyint(1) NOT NULL DEFAULT 0,
  `member1` varchar(150) DEFAULT NULL,
  `member2` varchar(150) DEFAULT NULL,
  `member3` varchar(150) DEFAULT NULL,
  `member4` varchar(150) DEFAULT NULL,
  `registered` timestamp NULL DEFAULT current_timestamp(),
  `end_time` time DEFAULT NULL,
  `finalized` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`team_id`,`year`),
  KEY `year` (`year`),
  KEY `team_id` (`team_id`),
  CONSTRAINT `fk_teamsyear_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_teamsyear_year` FOREIGN KEY (`year`) REFERENCES `years` (`year`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `updates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `message` varchar(4095) NOT NULL,
  `year` tinyint(4) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `year` (`year`),
  CONSTRAINT `fk_updates` FOREIGN KEY (`year`) REFERENCES `years` (`year`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_team_start_datetime` AS SELECT
 1 AS `team_id`,
  1 AS `year`,
  1 AS `start_datetime` */;
SET character_set_client = @saved_cs_client;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `years` (
  `year` tinyint(4) NOT NULL,
  `calendar_year` smallint(6) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `checkpoint_count` int(11) DEFAULT NULL,
  `game_start` datetime DEFAULT NULL,
  `game_end` datetime DEFAULT NULL,
  `results_public` tinyint(1) NOT NULL DEFAULT 0,
  `results_final` tinyint(1) NOT NULL DEFAULT 0,
  `word_numbering` varchar(63) NOT NULL,
  `registration_start` datetime DEFAULT NULL,
  `registration_end` datetime DEFAULT NULL,
  `entry_fee` int(11) DEFAULT NULL,
  `entry_fee_deadline` datetime DEFAULT NULL,
  `entry_fee_return_deadline` datetime DEFAULT NULL,
  `entry_fee_account` varchar(63) DEFAULT NULL,
  `last_info_time` datetime DEFAULT NULL,
  `team_limit` int(11) DEFAULT NULL,
  `show_tester_notification` tinyint(1) NOT NULL DEFAULT 0,
  `is_current` tinyint(1) DEFAULT 0,
  `afterparty_location` varchar(255) DEFAULT NULL,
  `afterparty_time` time DEFAULT NULL,
  `finish_location` varchar(255) DEFAULT NULL,
  `finish_open_time` time DEFAULT NULL,
  `has_finish_cipher` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'whether the teams must solve a special cipher when they reach the finish',
  `hint_for_start_exists` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50001 DROP VIEW IF EXISTS `vw_team_start_datetime`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 SQL SECURITY DEFINER */
/*!50001 VIEW `vw_team_start_datetime` AS select `results`.`team_id` AS `team_id`,`results`.`year` AS `year`,`results`.`entry_datetime` AS `start_datetime` from `results` where `results`.`checkpoint_number` = 0 */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
