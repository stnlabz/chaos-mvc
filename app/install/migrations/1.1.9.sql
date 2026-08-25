/* [AI:GPT-5.6 Sol | 2026-08-25 UTC] */
/* Run once when upgrading an existing Chaos MVC installation to 1.1.9. */

CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `password_resets_token_unique` (`token`),
  KEY `password_resets_email_index` (`email`),
  KEY `password_resets_expiry_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `traffic` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `host` varchar(190) NOT NULL,
  `uri` varchar(500) NOT NULL,
  `method` varchar(10) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `referer` varchar(255) DEFAULT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT utc_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `accounts`
  ADD UNIQUE KEY `accounts_username_unique` (`username`),
  ADD UNIQUE KEY `accounts_email_unique` (`email_address`);

ALTER TABLE `modules`
  ADD UNIQUE KEY `modules_slug_unique` (`slug`);

ALTER TABLE `posts`
  ADD UNIQUE KEY `posts_slug_unique` (`slug`);

DELETE FROM `password_resets` WHERE `expires_at` <= NOW();

/* [End AI:GPT-5.6 Sol] */
