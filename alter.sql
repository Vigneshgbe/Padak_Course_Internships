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



-- =====================================================
-- SOCIAL FEED TABLE - Single table design
-- =====================================================
-- This single table handles posts, likes, and comments
-- item_type column determines the type of record
-- parent_id links comments and likes to their parent post
-- =====================================================

CREATE TABLE IF NOT EXISTS `social_feed` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) DEFAULT NULL,
  `student_id` int(11) NOT NULL,
  `item_type` enum('post','like','comment') NOT NULL DEFAULT 'post',
  `content` text DEFAULT NULL,
  `media_path` varchar(500) DEFAULT NULL,
  `media_type` enum('image','video') DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_parent_id` (`parent_id`),
  KEY `idx_student_id` (`student_id`),
  KEY `idx_item_type` (`item_type`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_parent_type` (`parent_id`, `item_type`),
  CONSTRAINT `fk_social_feed_parent` FOREIGN KEY (`parent_id`) REFERENCES `social_feed` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_social_feed_student` FOREIGN KEY (`student_id`) REFERENCES `internship_students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE STRUCTURE EXPLANATION:
-- =====================================================
-- 
-- POSTS (item_type='post'):
--   - parent_id: NULL (posts are top-level)
--   - content: Post text content
--   - media_path: Path to uploaded image/video
--   - media_type: 'image' or 'video'
--
-- LIKES (item_type='like'):
--   - parent_id: ID of the post being liked
--   - content: NULL
--   - media_path: NULL
--   - media_type: NULL
--
-- COMMENTS (item_type='comment'):
--   - parent_id: ID of the post being commented on
--   - content: Comment text
--   - media_path: NULL (comments don't support media)
--   - media_type: NULL
--
-- BENEFITS:
--   - Single table reduces complexity
--   - Easy to query related data
--   - Efficient indexing
--   - Scalable for future features (shares, reactions, etc.)
--   - Maintains referential integrity
--
-- =====================================================

-- Sample queries for reference:

-- Get all posts with like and comment counts:
/*
SELECT 
  sf.*,
  s.full_name,
  s.profile_photo,
  (SELECT COUNT(*) FROM social_feed WHERE parent_id=sf.id AND item_type='like' AND is_deleted=0) as likes_count,
  (SELECT COUNT(*) FROM social_feed WHERE parent_id=sf.id AND item_type='comment' AND is_deleted=0) as comments_count
FROM social_feed sf
JOIN internship_students s ON s.id = sf.student_id
WHERE sf.item_type='post' AND sf.is_deleted=0
ORDER BY sf.created_at DESC;
*/

-- Get all comments for a specific post:
/*
SELECT sf.*, s.full_name, s.profile_photo
FROM social_feed sf
JOIN internship_students s ON s.id = sf.student_id
WHERE sf.parent_id = ? AND sf.item_type='comment' AND sf.is_deleted=0
ORDER BY sf.created_at ASC;
*/

-- Check if user has liked a post:
/*
SELECT COUNT(*) as has_liked
FROM social_feed
WHERE parent_id = ? AND student_id = ? AND item_type='like' AND is_deleted=0;
*/