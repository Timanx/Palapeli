-- 000: individual start ("klouzavý start") as an edition setting + per-checkpoint entry message
--
-- See docs/palainfo-audit.md, section 11. Target: MariaDB 10.4 (docs/schema.sql).
-- Run manually, once, BEFORE deploying the code that uses the new columns
-- (the results pages and Palainfo fail with "Unknown column" without them).
-- The results table is not touched; results of all editions stay the same.

SET NAMES utf8mb4;


-- 1) years.individual_start – replaces the hard-coded AppConstants::IndividualStartYear = 10.
--    Each team starts whenever it enters the start code; its times are relative to that moment.

ALTER TABLE `years`
	ADD COLUMN `individual_start` tinyint(1) NOT NULL DEFAULT 0
		COMMENT 'klouzavý start: each team starts when it enters the start code, results are relative to that moment'
		AFTER `hint_for_start_exists`;

UPDATE `years` SET `individual_start` = 1 WHERE `year` = 10;


-- 2) ciphers.entry_message – text shown to the team (and written to its Palainfo log) right after
--    it enters the code of the checkpoint. Replaces the text hard-coded in InfoScreen::describeArrival()
--    for the start of edition 10.

ALTER TABLE `ciphers`
	ADD COLUMN `entry_message` varchar(1023) DEFAULT NULL
		COMMENT 'text shown to the team after it enters the code of this checkpoint'
		AFTER `dead_solution`;

INSERT INTO `ciphers` (`year`, `checkpoint_number`, `entry_message`)
VALUES (10, 0, 'První šifra se nachází v Lelekovicích u pumptracku, v dutině křoví asi 10 metrů jižně od dřevěné sochy. Upřesnítka k dalším šifrám naleznete v záložce Karta.')
ON DUPLICATE KEY UPDATE `entry_message` = VALUES(`entry_message`);
