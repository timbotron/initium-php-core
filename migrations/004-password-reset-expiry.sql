
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------

--
-- Expiry for password-reset / set-password tokens. A token is only accepted
-- while password_reset_expires is in the future; NULL (a legacy token issued
-- before this column existed, or a cleared slot) is treated as not valid, so the
-- check fails closed. The window is set when a token is issued (see
-- Auth\Controller, PASSWORD_RESET_TTL).
--

ALTER TABLE `users` ADD COLUMN `password_reset_expires` datetime DEFAULT NULL;
