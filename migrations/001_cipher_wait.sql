-- 001: mandatory wait before the cipher of a checkpoint is handed out ("campfire")
--
-- Target: MariaDB 10.4 (docs/schema.sql). Run manually, once, BEFORE deploying the code that uses
-- the new columns (Palainfo and the cipher administration fail with "Unknown column" without them).
-- Existing ciphers get no wait; existing results are not changed.

SET NAMES utf8mb4;


-- ciphers.wait_minutes – after entering the code of the checkpoint the team waits this long before
-- organizers hand out the cipher. Palainfo shows an orange countdown that turns green with the team
-- name when it runs out; the team shows it to the organizers as a proof. NULL = no wait.
-- ciphers.wait_message – extra sentence shown on the orange screen (e.g. "you can roast a sausage").
-- ciphers.pickup_code – code written on the cipher handed out after the wait; entering it into Palainfo
-- records that the team received the cipher and removes the green screen.
-- results.pickup_datetime – when the team entered the pickup code.

ALTER TABLE `ciphers`
	ADD COLUMN `wait_minutes` smallint(5) unsigned DEFAULT NULL
		COMMENT 'minutes the team waits after entering the code before it gets the cipher; NULL = no wait'
		AFTER `entry_message`,
	ADD COLUMN `wait_message` varchar(1023) DEFAULT NULL
		COMMENT 'text shown to the team on the Palainfo screen while it waits for the cipher'
		AFTER `wait_minutes`,
	ADD COLUMN `pickup_code` varchar(255) DEFAULT NULL
		COMMENT 'code written on the cipher handed out after the wait; entering it records the pickup'
		AFTER `wait_message`;

ALTER TABLE `results`
	ADD COLUMN `pickup_datetime` datetime DEFAULT NULL
		COMMENT 'when the team entered the pickup code of the cipher (checkpoints with ciphers.wait_minutes)'
		AFTER `entry_datetime`;
