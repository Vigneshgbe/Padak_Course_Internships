-- MESSENGER POGE CODE ALTER
-- Add columns to existing tables for new features

-- Add attachment and reply columns to chat_messages table
ALTER TABLE `chat_messages` 
ADD COLUMN `reply_to_id` INT(11) NULL DEFAULT NULL AFTER `message`,
ADD COLUMN `attachment_path` VARCHAR(500) NULL DEFAULT NULL AFTER `reply_to_id`,
ADD COLUMN `attachment_type` ENUM('image','file') NULL DEFAULT NULL AFTER `attachment_path`,
ADD COLUMN `attachment_name` VARCHAR(255) NULL DEFAULT NULL AFTER `attachment_type`,
ADD INDEX `idx_reply_to` (`reply_to_id`),
ADD FOREIGN KEY (`reply_to_id`) REFERENCES `chat_messages`(`id`) ON DELETE SET NULL;

-- Create reactions table (uses existing internship_students table)
CREATE TABLE IF NOT EXISTS `message_reactions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `message_id` INT(11) NOT NULL,
  `student_id` INT(11) NOT NULL,
  `emoji` VARCHAR(10) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_reaction` (`message_id`, `student_id`, `emoji`),
  KEY `idx_message` (`message_id`),
  KEY `idx_student` (`student_id`),
  FOREIGN KEY (`message_id`) REFERENCES `chat_messages`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `internship_students`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add index for better query performance
ALTER TABLE `chat_messages` ADD INDEX `idx_room_created` (`room_id`, `created_at`);
ALTER TABLE `chat_messages` ADD INDEX `idx_sender` (`sender_id`);



