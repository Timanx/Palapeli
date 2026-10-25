-- 002: discussion below the start cipher stored under two thread names
--
-- Target: MariaDB 10.0 / MySQL 5.6. Run manually, once, together with deploying the code that
-- reads the start discussion from "sifry_<year>_0" (until both are done, part of the posts is hidden).
-- The start has the default checkpoint 0, which is left out of its URL, so posts written there
-- since 2019 got the thread "sifry_<year>_" while the older ones have "sifry_<year>_0", the name
-- the page uses now. Without this, the start discussion of 2019 and later editions looks empty.

SET NAMES utf8mb4;

UPDATE `discussion`
SET `thread` = CONCAT(`thread`, '0')
WHERE `thread` REGEXP '^sifry_[0-9]+_$';
