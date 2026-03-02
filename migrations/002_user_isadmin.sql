-- Add is_admin boolean column to user table
ALTER TABLE `user`
    ADD COLUMN `is_admin` TINYINT(1) NOT NULL DEFAULT 0;
