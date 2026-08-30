
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--
-- A tiny key/value store for runtime-editable platform settings (managed from
-- the admin area). Reads fall back to code defaults when a row is absent, so an
-- install with no rows behaves exactly as it did before this table existed.
--

CREATE TABLE `settings` (
  `name` varchar(191) NOT NULL,
  `value` varchar(1000) NOT NULL DEFAULT '',
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Admin flag on users. A logged-in user reaches the admin area when this flag
-- is set OR when their email matches the optional ADMIN_EMAIL config constant.
--

ALTER TABLE `users` ADD COLUMN `is_admin` tinyint UNSIGNED NOT NULL DEFAULT 0;
