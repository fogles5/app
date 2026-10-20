# Complete Database Schema - Turnpage/Hookup Application

> **Last Updated:** November 28, 2025  
> **Database Engine:** MySQL/MariaDB  
> **Character Set:** utf8mb4_unicode_ci

---

## 📋 Table of Contents

1. [Core Tables](#core-tables)
2. [User Management](#user-management)
3. [Listings & Classifieds](#listings--classifieds)
4. [Messaging System](#messaging-system)
5. [Location & Geography](#location--geography)
6. [Membership & Payments](#membership--payments)
7. [Security & Authentication](#security--authentication)
8. [Moderation System](#moderation-system)
9. [Gamification](#gamification)
10. [Advertising System](#advertising-system)
11. [Notifications](#notifications)
12. [Site Management](#site-management)
13. [Table Relationships](#table-relationships)

---

## Core Tables

### `users`
Core user accounts and profiles

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | INT | PRIMARY KEY, AUTO_INCREMENT | User ID |
| username | VARCHAR(50) | UNIQUE, NOT NULL | Username |
| email | VARCHAR(255) | UNIQUE, NOT NULL | Email address |
| password | VARCHAR(255) | NOT NULL | Hashed password |
| gender | ENUM | 'male','female','other' | User gender |
| date_of_birth | DATE | | Birth date |
| is_admin | TINYINT(1) | DEFAULT 0 | Admin flag |
| is_moderator | TINYINT(1) | DEFAULT 0 | Moderator flag |
| verified | TINYINT(1) | DEFAULT 0 | Verified badge |
| creator | TINYINT(1) | DEFAULT 0 | Creator badge |
| avatar | VARCHAR(255) | | Profile photo path |
| bio | TEXT | | User biography |
| current_latitude | DECIMAL(10,8) | | Current location lat |
| current_longitude | DECIMAL(11,8) | | Current location lng |
| search_radius | INT | DEFAULT 50 | Search radius in km |
| show_distance | TINYINT(1) | DEFAULT 1 | Show distance to others |
| last_seen | TIMESTAMP | | Last activity time |
| created_at | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Account creation |
| updated_at | TIMESTAMP | ON UPDATE CURRENT_TIMESTAMP | Last update |

**Indexes:**
- `idx_email` (email)
- `idx_username` (username)
- `idx_location` (current_latitude, current_longitude)
- `idx_last_seen` (last_seen)

---

### `user_profiles`
Extended user profile information

| Column | Type | Description |
|--------|------|-------------|
| user_id | INT | FOREIGN KEY → users(id) |
| height | INT | Height in cm |
| body_type | ENUM | slim, athletic, average, curvy, muscular, heavyset |
| ethnicity | VARCHAR(100) | Ethnicity |
| relationship_status | ENUM | single, married, divorced, etc. |
| looking_for | JSON | Array of relationship goals |
| interests | JSON | Array of interests/hobbies |
| occupation | VARCHAR(100) | Job/profession |
| education | ENUM | high_school, bachelors, masters, phd |
| smoking | ENUM | never, occasionally, regularly |
| drinking | ENUM | never, socially, regularly |
| has_kids | TINYINT(1) | Has children |
| wants_kids | ENUM | yes, no, maybe |
| languages | JSON | Spoken languages |
| display_distance | TINYINT(1) | Show distance on profile |
| show_age | TINYINT(1) | Display age |
| show_online_status | TINYINT(1) | Show online status |

---

## Listings & Classifieds

### `listings`
User-created classified ads

| Column | Type | Description |
|--------|------|-------------|
| id | INT | PRIMARY KEY |
| user_id | INT | FOREIGN KEY → users(id) |
| title | VARCHAR(255) | Ad title |
| description | TEXT | Ad description |
| category_id | INT | FOREIGN KEY → categories(id) |
| city_id | INT | Location |
| latitude | DECIMAL(10,8) | Location lat |
| longitude | DECIMAL(11,8) | Location lng |
| price | DECIMAL(10,2) | Item price |
| featured | TINYINT(1) | Featured listing |
| urgent | TINYINT(1) | Urgent tag |
| verified | TINYINT(1) | Verified listing |
| status | ENUM | active, pending, expired, deleted |
| views | INT | View count |
| expires_at | DATETIME | Expiration date |
| created_at | TIMESTAMP | Creation time |

**Indexes:**
- `idx_user` (user_id)
- `idx_category` (category_id)
- `idx_location` (latitude, longitude)
- `idx_status` (status)
- `idx_featured` (featured)

---

### `categories`
Listing categories

| Column | Type | Description |
|--------|------|-------------|
| id | INT | PRIMARY KEY |
| name | VARCHAR(100) | Category name |
| slug | VARCHAR(100) | URL-friendly slug |
| icon | VARCHAR(50) | Icon/emoji |
| parent_id | INT | Parent category (NULL = top level) |
| display_order | INT | Sort order |
| is_active | TINYINT(1) | Active status |

---

### `listing_images`
Images attached to listings

| Column | Type | Description |
|--------|------|-------------|
| id | INT | PRIMARY KEY |
| listing_id | INT | FOREIGN KEY → listings(id) |
| image_url | VARCHAR(255) | Image path |
| display_order | INT | Sort order |
| is_primary | TINYINT(1) | Primary image flag |

---

### `favorites`
User saved/favorited listings

| Column | Type | Description |
|--------|------|-------------|
| id | INT | PRIMARY KEY |
| user_id | INT | FOREIGN KEY → users(id) |
| listing_id | INT | FOREIGN KEY → listings(id) |
| created_at | TIMESTAMP | When favorited |

**Unique:** (user_id, listing_id)

---

## Messaging System

### `conversations`
Chat conversations between users

| Column | Type | Description |
|--------|------|-------------|
| id | INT | PRIMARY KEY |
| user1_id | INT | FOREIGN KEY → users(id) |
| user2_id | INT | FOREIGN KEY → users(id) |
| last_message_at | TIMESTAMP | Last message time |
| created_at | TIMESTAMP | Conversation start |

**Indexes:**
- `idx_users` (user1_id, user2_id)
- `idx_last_message` (last_message_at)

---

### `messages`
Individual messages

| Column | Type | Description |
|--------|------|-------------|
| id | INT | PRIMARY KEY |
| conversation_id | INT | FOREIGN KEY → conversations(id) |
| sender_id | INT | FOREIGN KEY → users(id) |
| receiver_id | INT | FOREIGN KEY → users(id) |
| message | TEXT | Message content |
| is_read | TINYINT(1) | Read status |
| read_at | TIMESTAMP | When read |
| created_at | TIMESTAMP | Send time |

**Indexes:**
- `idx_conversation` (conversation_id)
- `idx_sender` (sender_id)
- `idx_receiver` (receiver_id)
- `idx_read` (is_read)

---

### `message_attachments`
File attachments in messages

| Column | Type | Description |
|--------|------|-------------|
| id | INT | PRIMARY KEY |
| message_id | INT | FOREIGN KEY → messages(id) |
| file_path | VARCHAR(255) | File location |
| file_type | VARCHAR(50) | MIME type |
| file_size | INT | Size in bytes |
| created_at | TIMESTAMP | Upload time |

---

## Location & Geography

### `states`
US States

| Column | Type | Description |
|--------|------|-------------|
| id | INT | PRIMARY KEY |
| name | VARCHAR(100) | State name |
| code | CHAR(2) | State code (e.g., 'CA') |

---

### `cities`
Cities within states

| Column | Type | Description |
|--------|------|-------------|
| id | INT | PRIMARY KEY |
| state_id | INT | FOREIGN KEY → states(id) |
| name | VARCHAR(100) | City name |
| latitude | DECIMAL(10,8) | City center lat |
| longitude | DECIMAL(11,8) | City center lng |
| population | INT | Population count |
| is_featured | TINYINT(1) | Featured city |

**Indexes:**
- `idx_state` (state_id)
- `idx_name` (name)

---

### `user_locations`
User location preferences

| Column | Type | Description |
|--------|------|-------------|
| user_id | INT | FOREIGN KEY → users(id) |
| city_id | INT | FOREIGN KEY → cities(id) |
| latitude | DECIMAL(10,8) | Precise location lat |
| longitude | DECIMAL(11,8) | Precise location lng |
| postal_code | VARCHAR(20) | ZIP/postal code |
| max_distance | INT | Search radius (km) |
| updated_at | TIMESTAMP | Last update |

---

## Membership & Payments

### `membership_plans`
Available premium plans

| Column | Type | Description |
|--------|------|-------------|
| id | INT | PRIMARY KEY |
| name | VARCHAR(100) | Plan name |
| description | TEXT | Plan description |
| price | DECIMAL(10,2) | Plan price |
| duration_days | INT | Duration in days |
| features | JSON | Plan features array |
| is_active | TINYINT(1) | Active status |
| display_order | INT | Sort order |

---

### `user_subscriptions`
Active user subscriptions

| Column | Type | Description |
|--------|------|-------------|
| id | INT | PRIMARY KEY |
| user_id | INT | FOREIGN KEY → users(id) |
| plan_id | INT | FOREIGN KEY → membership_plans(id) |
| status | ENUM | pending, active, expired, cancelled, rejected |
| payment_method | VARCHAR(50) | Payment method used |
| transaction_id | VARCHAR(255) | Transaction reference |
| rejection_reason | TEXT | Rejection reason (if any) |
| start_date | DATETIME | Subscription start |
| end_date | DATETIME | Subscription end |
| approved_by | INT | Admin who approved |
| approved_at | TIMESTAMP | Approval time |
| created_at | TIMESTAMP | Purchase time |

**Indexes:**
- `idx_user` (user_id)
- `idx_status` (status)
- `idx_dates` (start_date, end_date)

---

### `bitcoin_payments`
Bitcoin payment tracking

| Column | Type | Description |
|--------|------|-------------|
| id | INT | PRIMARY KEY |
| user_id | INT | FOREIGN KEY → users(id) |
| plan_id | INT | FOREIGN KEY → membership_plans(id) |
| btc_address | VARCHAR(100) | Payment address |
| btc_amount | DECIMAL(16,8) | BTC amount |
| usd_amount | DECIMAL(10,2) | USD equivalent |
| status | ENUM | pending, confirmed, expired |
| confirmations | INT | Blockchain confirmations |
| txid | VARCHAR(100) | Transaction hash |
| expires_at | DATETIME | Payment expiration |
| confirmed_at | DATETIME | Confirmation time |
| created_at | TIMESTAMP | Payment creation |

---

## Security & Authentication

### `blocked_ips`
Blocked IP addresses

| Column | Type | Description |
|--------|------|-------------|
| id | INT | PRIMARY KEY |
| ip_address | VARCHAR(45) | UNIQUE, IPv4/IPv6 |
| reason | VARCHAR(255) | Block reason |
| expires_at | DATETIME | Expiration (NULL = permanent) |
| created_at | TIMESTAMP | When blocked |
| updated_at | TIMESTAMP | Last update |

**Indexes:**
- `idx_ip` (ip_address)
- `idx_expires` (expires_at)

---

### `login_attempts`
Failed login tracking

| Column | Type | Description |
|--------|------|-------------|
| id | INT | PRIMARY KEY |
| identifier | VARCHAR(255) | Email/username |
| ip_address | VARCHAR(45) | Attempt IP |
| attempted_at | TIMESTAMP | Attempt time |

**Indexes:**
- `idx_identifier` (identifier)
- `idx_ip` (ip_address)
- `idx_attempted` (attempted_at)

---

### `rate_limits`
General rate limiting

| Column | Type | Description |
|--------|------|-------------|
| id | INT | PRIMARY KEY |
| action | VARCHAR(100) | Action being limited |
| identifier | VARCHAR(255) | User ID/IP/email |
| created_at | TIMESTAMP | Action time |

**Indexes:**
- `idx_action_identifier` (action, identifier)
- `idx_created` (created_at)

---

### `remember_tokens`
Remember me tokens

| Column | Type | Description |
|--------|------|-------------|
| id | INT | PRIMARY KEY |
| user_id | INT | FOREIGN KEY → users(id) |
| token | VARCHAR(255) | UNIQUE token hash |
| expires_at | DATETIME | Token expiration |
| created_at | TIMESTAMP | Token creation |

---

### `login_history`
Complete login audit trail

| Column | Type | Description |
|--------|------|-------------|
| id | INT | PRIMARY KEY |
| user_id | INT | FOREIGN KEY → users(id) |
| ip_address | VARCHAR(45) | Login IP |
| user_agent | TEXT | Browser/device info |
| success | TINYINT(1) | Login success flag |
| created_at | TIMESTAMP | Login time |

**Indexes:**
- `idx_user` (user_id)
- `idx_ip` (ip_address)
- `idx_success` (success)

---

## Moderation System

### `moderators`
Moderator permissions

| Column | Type | Description |
|--------|------|-------------|
| id | INT | PRIMARY KEY |
| user_id | INT | FOREIGN KEY → users(id) |
| permissions | JSON | Permission array |
| assigned_by | INT | Admin who assigned |
| created_at | TIMESTAMP | Assignment date |

---

### `moderation_queue`
Items pending moderation

| Column | Type | Description |
|--------|------|-------------|
| id | INT | PRIMARY KEY |
| item_type | ENUM | listing, user, message, report |
| item_id | INT | ID of item |
| reporter_id | INT | User who reported |
| reason | VARCHAR(255) | Report reason |
| status | ENUM | pending, approved, rejected |
| reviewed_by | INT | Moderator ID |
| reviewed_at | TIMESTAMP | Review time |
| created_at | TIMESTAMP | Report time |

---

### `reports`
User-generated reports

| Column | Type | Description |
|--------|------|-------------|
| id | INT | PRIMARY KEY |
| reporter_id | INT | FOREIGN KEY → users(id) |
| report_type | ENUM | listing, user, message |
| reported_id | INT | ID of reported item |
| reason | VARCHAR(100) | Report reason |
| description | TEXT | Detailed description |
| status | ENUM | pending, resolved, dismissed |
| action_taken | TEXT | Action taken |
| resolved_by | INT | Admin/mod who resolved |
| resolved_at | TIMESTAMP | Resolution time |
| created_at | TIMESTAMP | Report time |

---

## Gamification

### `user_achievements`
User earned achievements

| Column | Type | Description |
|--------|------|-------------|
| id | INT | PRIMARY KEY |
| user_id | INT | FOREIGN KEY → users(id) |
| achievement_type | VARCHAR(50) | Achievement key |
| earned_at | TIMESTAMP | When earned |

---

### `user_points`
Point/karma system

| Column | Type | Description |
|--------|------|-------------|
| user_id | INT | FOREIGN KEY → users(id) |
| total_points | INT | Total points |
| level | INT | User level |
| badges | JSON | Badge array |
| updated_at | TIMESTAMP | Last update |

---

## Advertising System

### `ad_campaigns`
Advertising campaigns

| Column | Type | Description |
|--------|------|-------------|
| id | INT | PRIMARY KEY |
| advertiser_id | INT | FOREIGN KEY → users(id) |
| name | VARCHAR(255) | Campaign name |
| budget | DECIMAL(10,2) | Total budget |
| spent | DECIMAL(10,2) | Amount spent |
| status | ENUM | active, paused, completed |
| start_date | DATETIME | Campaign start |
| end_date | DATETIME | Campaign end |
| created_at | TIMESTAMP | Creation time |

---

### `ad_creatives`
Ad content/creatives

| Column | Type | Description |
|--------|------|-------------|
| id | INT | PRIMARY KEY |
| campaign_id | INT | FOREIGN KEY → ad_campaigns(id) |
| title | VARCHAR(255) | Ad title |
| description | TEXT | Ad copy |
| image_url | VARCHAR(255) | Ad image |
| destination_url | VARCHAR(255) | Click destination |
| ad_type | ENUM | banner, native, popup |
| is_active | TINYINT(1) | Active status |

---

### `ad_impressions`
Ad view tracking

| Column | Type | Description |
|--------|------|-------------|
| id | INT | PRIMARY KEY |
| creative_id | INT | FOREIGN KEY → ad_creatives(id) |
| user_id | INT | Viewer (NULL = anonymous) |
| ip_address | VARCHAR(45) | Viewer IP |
| viewed_at | TIMESTAMP | View time |

---

### `ad_clicks`
Ad click tracking

| Column | Type | Description |
|--------|------|-------------|
| id | INT | PRIMARY KEY |
| creative_id | INT | FOREIGN KEY → ad_creatives(id) |
| user_id | INT | Clicker (NULL = anonymous) |
| ip_address | VARCHAR(45) | Click IP |
| clicked_at | TIMESTAMP | Click time |

---

## Notifications

### `notifications`
User notifications

| Column | Type | Description |
|--------|------|-------------|
| id | INT | PRIMARY KEY |
| user_id | INT | FOREIGN KEY → users(id) |
| type | VARCHAR(50) | Notification type |
| title | VARCHAR(255) | Notification title |
| message | TEXT | Notification message |
| link | VARCHAR(255) | Action link |
| is_read | TINYINT(1) | Read status |
| priority | ENUM | low, normal, high |
| created_at | TIMESTAMP | Creation time |

**Indexes:**
- `idx_user_read` (user_id, is_read)
- `idx_created` (created_at)

---

## Site Management

### `site_settings`
System configuration

| Column | Type | Description |
|--------|------|-------------|
| id | INT | PRIMARY KEY |
| setting_key | VARCHAR(100) | UNIQUE setting name |
| setting_value | TEXT | Setting value |
| description | TEXT | Setting description |
| updated_by | INT | Admin who updated |
| updated_at | TIMESTAMP | Last update |

**Index:** `idx_key` (setting_key)

---

### `site_announcements`
System-wide announcements

| Column | Type | Description |
|--------|------|-------------|
| id | INT | PRIMARY KEY |
| title | VARCHAR(255) | Announcement title |
| message | TEXT | Announcement message |
| type | ENUM | info, success, warning, danger |
| show_on_homepage | TINYINT(1) | Show on home |
| show_on_all_pages | TINYINT(1) | Show everywhere |
| priority | INT | Display priority |
| start_date | DATETIME | Start showing |
| end_date | DATETIME | Stop showing |
| is_active | TINYINT(1) | Active status |
| created_by | INT | Admin who created |
| created_at | TIMESTAMP | Creation time |

---

## Table Relationships

### Primary Relationships

```
users (1) ──→ (N) user_profiles
users (1) ──→ (N) listings
users (1) ──→ (N) messages
users (1) ──→ (N) favorites
users (1) ──→ (N) user_subscriptions
users (1) ──→ (N) notifications

listings (1) ──→ (N) listing_images
listings (N) ──→ (1) categories
listings (N) ──→ (1) cities

conversations (1) ──→ (N) messages
messages (1) ──→ (N) message_attachments

states (1) ──→ (N) cities
cities (1) ──→ (N) user_locations

membership_plans (1) ──→ (N) user_subscriptions

ad_campaigns (1) ──→ (N) ad_creatives
ad_creatives (1) ──→ (N) ad_impressions
ad_creatives (1) ──→ (N) ad_clicks
```

---

## Database Statistics

**Total Tables:** 40+  
**Core Tables:** 15  
**Feature Tables:** 25+  
**Security Tables:** 5  
**Moderation Tables:** 3  
**Advertising Tables:** 4  

---

## Maintenance Queries

### Cleanup Old Data

```sql
-- Remove expired sessions
DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 24 HOUR);

-- Clean rate limits
DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR);

-- Remove expired blocks
DELETE FROM blocked_ips WHERE expires_at IS NOT NULL AND expires_at < NOW();

-- Clean expired listings
UPDATE listings SET status = 'expired' WHERE expires_at < NOW() AND status = 'active';
```

### Performance Optimization

```sql
-- Analyze tables
ANALYZE TABLE users, listings, messages, conversations;

-- Optimize tables
OPTIMIZE TABLE login_attempts, rate_limits, ad_impressions;
```

---

## Schema Files Reference

- `schema.sql` - Core tables
- `locations_schema.sql` - Geography tables
- `messaging_and_features_schema.sql` - Messaging system
- `membership_and_moderator_schema.sql` - Premium features
- `gamification_and_advanced_schema.sql` - Achievements
- `advertising_system.sql` - Ad platform
- `security_tables.sql` - Security features
- `announcements_schema.sql` - Site announcements
- `bitcoin_payments.sql` - Crypto payments
- `maintenance_mode_schema.sql` - Maintenance settings

---

**For implementation details, see:**
- [SECURITY_IMPLEMENTATION.md](../SECURITY_IMPLEMENTATION.md)
- Individual schema files in `/database/`

---