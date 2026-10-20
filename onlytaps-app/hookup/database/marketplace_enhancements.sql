-- Optional Marketplace Enhancements
-- These are OPTIONAL additions to enhance the marketplace functionality
-- Only run if you want these additional features

-- Add cover_image field to users table if it doesn't exist
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS cover_image VARCHAR(255) DEFAULT NULL COMMENT 'Profile cover/banner image' AFTER avatar;

-- Add display_name field to users table if it doesn't exist  
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS display_name VARCHAR(100) DEFAULT NULL COMMENT 'User display name (optional)' AFTER username;

-- Optional: Create a posts/content table to track post counts
CREATE TABLE IF NOT EXISTS user_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255),
    content TEXT,
    media_url VARCHAR(255),
    media_type ENUM('image', 'video', 'audio') DEFAULT 'image',
    is_premium TINYINT(1) DEFAULT 0 COMMENT 'Premium/locked content',
    price DECIMAL(10,2) DEFAULT 0.00,
    views INT DEFAULT 0,
    likes INT DEFAULT 0,
    status ENUM('active', 'deleted', 'hidden') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='User posts/content for creators';

-- Optional: Create subscriptions table for creator subscriptions
CREATE TABLE IF NOT EXISTS creator_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subscriber_id INT NOT NULL COMMENT 'User who is subscribing',
    creator_id INT NOT NULL COMMENT 'Creator being subscribed to',
    subscription_type ENUM('free', 'premium') DEFAULT 'free',
    price DECIMAL(10,2) DEFAULT 0.00,
    status ENUM('active', 'expired', 'cancelled') DEFAULT 'active',
    start_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    end_date DATETIME DEFAULT NULL,
    auto_renew TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (subscriber_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_subscription (subscriber_id, creator_id),
    INDEX idx_subscriber (subscriber_id),
    INDEX idx_creator (creator_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Creator subscription tracking';

-- Optional: Create tips/donations table
CREATE TABLE IF NOT EXISTS creator_tips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipper_id INT NOT NULL COMMENT 'User sending the tip',
    creator_id INT NOT NULL COMMENT 'Creator receiving the tip',
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    message TEXT DEFAULT NULL,
    payment_method VARCHAR(50) DEFAULT NULL,
    transaction_id VARCHAR(255) DEFAULT NULL,
    status ENUM('pending', 'completed', 'refunded') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tipper_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_tipper (tipper_id),
    INDEX idx_creator (creator_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tips/donations to creators';

-- Optional: Create creator settings table
CREATE TABLE IF NOT EXISTS creator_settings (
    user_id INT PRIMARY KEY,
    subscription_price DECIMAL(10,2) DEFAULT 0.00,
    allow_tips TINYINT(1) DEFAULT 1,
    allow_free_subscription TINYINT(1) DEFAULT 0,
    minimum_tip DECIMAL(10,2) DEFAULT 1.00,
    about TEXT DEFAULT NULL,
    social_links JSON DEFAULT NULL COMMENT 'Social media links',
    featured TINYINT(1) DEFAULT 0 COMMENT 'Featured creator',
    featured_until DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_featured (featured),
    INDEX idx_subscription_price (subscription_price)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Creator-specific settings and pricing';

-- Sample data for testing (OPTIONAL - Comment out if not needed)
-- Update some existing users to have cover images and display names
-- UPDATE users SET display_name = username WHERE display_name IS NULL LIMIT 10;
-- UPDATE users SET cover_image = '/assets/images/default-cover.jpg' WHERE cover_image IS NULL AND (verified = 1 OR creator = 1);

-- Views for easy querying
CREATE OR REPLACE VIEW v_marketplace_creators AS
SELECT 
    u.id,
    u.username,
    COALESCE(u.display_name, u.username) as display_name,
    u.avatar,
    u.cover_image,
    u.verified,
    u.creator,
    u.last_seen,
    CASE WHEN TIMESTAMPDIFF(MINUTE, u.last_seen, NOW()) < 15 THEN 1 ELSE 0 END as is_online,
    COALESCE(cs.subscription_price, 0) as subscription_price,
    COALESCE(cs.allow_free_subscription, 0) as is_free,
    COALESCE(post_count.total, 0) as post_count,
    COALESCE(cs.featured, 0) as is_featured
FROM users u
LEFT JOIN creator_settings cs ON u.id = cs.user_id
LEFT JOIN (
    SELECT user_id, COUNT(*) as total 
    FROM user_posts 
    WHERE status = 'active' 
    GROUP BY user_id
) post_count ON u.id = post_count.user_id
WHERE u.verified = 1 OR u.creator = 1
ORDER BY cs.featured DESC, u.verified DESC, u.last_seen DESC;

-- Indexes for performance
CREATE INDEX idx_users_creator_verified ON users(creator, verified, last_seen);
CREATE INDEX idx_users_last_seen ON users(last_seen DESC);

-- Complete!
-- Run the queries above as needed for your marketplace features
