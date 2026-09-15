-- ============================================================
-- Waymark: схема базы данных (MariaDB 10.11+, InnoDB, utf8mb4)
--
-- Загрузка:
--   mariadb -uwaymark -ppassword waymark < schema.sql
--
-- Файл идемпотентен: при повторном запуске таблицы ПЕРЕСОЗДАЮТСЯ
-- с полной потерей данных (DROP + CREATE).
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- Удаление (обратный порядок от дочерних к родительским)
-- ------------------------------------------------------------

DROP TABLE IF EXISTS `track_links`;
DROP TABLE IF EXISTS `media`;
DROP TABLE IF EXISTS `pois`;
DROP TABLE IF EXISTS `tracks`;
DROP TABLE IF EXISTS `users`;

-- ------------------------------------------------------------
-- users — пользователи
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `users` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `email` varchar(249) COLLATE utf8mb4_unicode_ci NOT NULL,
    `password` varchar(255) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL COMMENT 'password hash',
    `username` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `status` tinyint(2) unsigned NOT NULL DEFAULT '0',
    `verified` tinyint(1) unsigned NOT NULL DEFAULT '0',
    `resettable` tinyint(1) unsigned NOT NULL DEFAULT '1',
    `roles_mask` int(10) unsigned NOT NULL DEFAULT '0',
    `registered` int(10) unsigned NOT NULL,
    `last_login` int(10) unsigned DEFAULT NULL,
    `force_logout` mediumint(7) unsigned NOT NULL DEFAULT '0',
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`    DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users_confirmations` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `user_id` int(10) unsigned NOT NULL,
    `email` varchar(249) COLLATE utf8mb4_unicode_ci NOT NULL,
    `selector` varchar(16) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
    `token` varchar(255) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
    `expires` int(10) unsigned NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `selector` (`selector`),
    KEY `email_expires` (`email`,`expires`),
    KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users_remembered` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `user` int(10) unsigned NOT NULL,
    `selector` varchar(24) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
    `token` varchar(255) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
    `expires` int(10) unsigned NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `selector` (`selector`),
    KEY `user` (`user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users_resets` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `user` int(10) unsigned NOT NULL,
    `selector` varchar(20) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
    `token` varchar(255) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
    `expires` int(10) unsigned NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `selector` (`selector`),
    KEY `user_expires` (`user`,`expires`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users_throttling` (
    `bucket` varchar(44) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
    `tokens` float unsigned NOT NULL,
    `replenished_at` int(10) unsigned NOT NULL,
    `expires_at` int(10) unsigned NOT NULL,
    PRIMARY KEY (`bucket`),
    KEY `expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




-- ------------------------------------------------------------
-- tracks — треки: метаданные, геометрия (JSON), bbox, видимость
-- ------------------------------------------------------------

CREATE TABLE `tracks` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`       INT UNSIGNED NOT NULL,
    `title`         VARCHAR(255) NOT NULL DEFAULT '',
    `description`   TEXT NULL,
    `source`        ENUM('gpx','json','manual') NOT NULL DEFAULT 'manual',
    `date_recorded` DATETIME NULL,
    `geometry`      JSON NOT NULL,
    `bbox_min_lat`  DECIMAL(9,6) NULL,
    `bbox_max_lat`  DECIMAL(9,6) NULL,
    `bbox_min_lng`  DECIMAL(9,6) NULL,
    `bbox_max_lng`  DECIMAL(9,6) NULL,
    `visibility`    ENUM('private','protected','public') NOT NULL DEFAULT 'private',
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`    DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_tracks_user` (`user_id`, `deleted_at`),
    KEY `idx_tracks_bbox` (`bbox_min_lat`, `bbox_max_lat`, `bbox_min_lng`, `bbox_max_lng`),
    KEY `idx_tracks_visibility` (`visibility`, `deleted_at`),
    CONSTRAINT `fk_tracks_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- pois — точки интереса
-- ------------------------------------------------------------

CREATE TABLE `pois` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `track_id`    INT UNSIGNED NOT NULL,
    `title`       VARCHAR(255) NOT NULL DEFAULT '',
    `description` TEXT NULL,
    `latitude`    DECIMAL(9,6) NOT NULL,
    `longitude`   DECIMAL(9,6) NOT NULL,
    `position`    INT NOT NULL DEFAULT 0,
    `origin`      ENUM('auto','manual') NOT NULL DEFAULT 'auto',
    `visibility`  ENUM('private','public') NOT NULL DEFAULT 'private',
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`  DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_pois_track` (`track_id`, `position`, `deleted_at`),
    KEY `idx_pois_coords` (`latitude`, `longitude`),
    CONSTRAINT `fk_pois_track`
        FOREIGN KEY (`track_id`) REFERENCES `tracks` (`id`)
        ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- media — фото и видео: файлы на диске, в БД путь и метаданные
-- ------------------------------------------------------------

CREATE TABLE `media` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `track_id`      INT UNSIGNED NOT NULL,
    `poi_id`        INT UNSIGNED NULL,
    `type`          ENUM('photo','video') NOT NULL,
    `file_path`     VARCHAR(500) NOT NULL,
    `original_name` VARCHAR(255) NOT NULL DEFAULT '',
    `mime_type`     VARCHAR(127) NOT NULL DEFAULT '',
    `file_size`     BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `latitude`      DECIMAL(9,6) NULL,
    `longitude`     DECIMAL(9,6) NULL,
    `taken_at`      DATETIME NULL,
    `description`   TEXT NULL,
    `position`      INT NOT NULL DEFAULT 0,
    `visibility`    ENUM('private','public') NOT NULL DEFAULT 'private',
    `ignore_exif`   TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`    DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_media_track` (`track_id`, `position`, `deleted_at`),
    KEY `idx_media_poi` (`poi_id`, `position`, `deleted_at`),
    CONSTRAINT `fk_media_track`
        FOREIGN KEY (`track_id`) REFERENCES `tracks` (`id`)
        ON DELETE CASCADE ON UPDATE RESTRICT,
    CONSTRAINT `fk_media_poi`
        FOREIGN KEY (`poi_id`) REFERENCES `pois` (`id`)
        ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- track_links — токены доступа к приватным трекам по ссылке
-- ------------------------------------------------------------

CREATE TABLE `track_links` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `track_id`   INT UNSIGNED NOT NULL,
    `token`      CHAR(64) NOT NULL,
    `expires_at` DATETIME NULL DEFAULT NULL,
    `revoked_at` DATETIME NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_track_links_token` (`token`),
    KEY `idx_track_links_track` (`track_id`),
    CONSTRAINT `fk_track_links_track`
        FOREIGN KEY (`track_id`) REFERENCES `tracks` (`id`)
        ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;