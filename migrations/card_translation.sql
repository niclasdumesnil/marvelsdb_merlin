-- Migration: create card_translation table
-- Run once: mysql -u <user> -p <database> < migrations/card_translation.sql

CREATE TABLE IF NOT EXISTS `card_translation` (
  `id`      INT          NOT NULL AUTO_INCREMENT,
  `locale`  VARCHAR(5)   NOT NULL,
  `code`    VARCHAR(20)  NOT NULL,
  `name`    VARCHAR(255) DEFAULT NULL,
  `subname` VARCHAR(255) DEFAULT NULL,
  `text`    LONGTEXT     DEFAULT NULL,
  `flavor`  LONGTEXT     DEFAULT NULL,
  `traits`  VARCHAR(512) DEFAULT NULL,
  `errata`  LONGTEXT     DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_card_translation_locale_code` (`locale`, `code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
