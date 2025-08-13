-- Add last_activity field to users table for tracking active users
ALTER TABLE `users` ADD COLUMN `last_activity` TIMESTAMP NULL DEFAULT NULL AFTER `updated_at`;

-- Update existing users to have current timestamp as last_activity
UPDATE `users` SET `last_activity` = `updated_at` WHERE `last_activity` IS NULL;
