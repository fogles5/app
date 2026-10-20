# Creator & User Card Components

OnlyFans/Fansly-style profile cards and subscription cards for your platform.

## 📦 Installation

### 1. Include CSS
```html
<link rel="stylesheet" href="/assets/css/creator-cards.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.min.css" rel="stylesheet">
```

### 2. View Examples
Visit `/components/creator-card-examples.html` to see all components in action.

---

## 🎨 Components

### 1. Profile Header Card
**Full-featured creator profile with stats, cover image, and content grid**

```php
<div class="profile-header-card">
  <!-- Cover Image -->
  <div class="profile-cover">
    <?php if($user['cover_image']): ?>
    <img src="<?php echo htmlspecialchars($user['cover_image']); ?>" alt="Cover">
    <?php endif; ?>
    
    <!-- Stats Bar -->
    <div class="profile-stats-bar">
      <div class="stat-badge"><i class="bi bi-images"></i> <?php echo $stats['photos']; ?></div>
      <div class="stat-badge"><i class="bi bi-play-circle"></i> <?php echo $stats['videos']; ?></div>
      <div class="stat-badge"><i class="bi bi-broadcast"></i> <?php echo $stats['streams']; ?></div>
      <div class="stat-badge"><i class="bi bi-heart-fill"></i> <?php echo number_format($stats['likes']); ?></div>
      <div class="stat-badge"><i class="bi bi-people"></i> <?php echo number_format($stats['followers']); ?></div>
    </div>
  </div>

  <div class="profile-main">
    <!-- Avatar -->
    <div class="profile-avatar-wrapper">
      <div class="profile-avatar">
        <?php if($user['avatar']): ?>
        <img src="<?php echo htmlspecialchars($user['avatar']); ?>" alt="Avatar">
        <?php else: ?>
        <div class="avatar-placeholder">👤</div>
        <?php endif; ?>
      </div>
      <?php if($user['is_online']): ?>
      <div class="online-indicator"></div>
      <?php endif; ?>
      <?php if($user['is_verified']): ?>
      <div class="verification-badge"><i class="bi bi-check"></i></div>
      <?php endif; ?>
    </div>

    <!-- User Info -->
    <div class="profile-info">
      <div class="profile-username">
        <?php echo htmlspecialchars($user['display_name']); ?>
        <?php if($user['is_verified']): ?>
        <i class="bi bi-patch-check-fill" style="color:#4267f5"></i>
        <?php endif; ?>
      </div>
      <div class="profile-handle">@<?php echo htmlspecialchars($user['username']); ?></div>
      <?php if($user['is_online']): ?>
      <div class="profile-status">Online now</div>
      <?php else: ?>
      <div class="profile-status">Seen <?php echo timeAgo($user['last_seen']); ?></div>
      <?php endif; ?>
      <?php if($user['bio']): ?>
      <div class="profile-bio"><?php echo nl2br(htmlspecialchars($user['bio'])); ?></div>
      <?php endif; ?>
      <div class="profile-meta">
        <?php if($user['location']): ?>
        <span class="meta-item">📍 <?php echo htmlspecialchars($user['location']); ?></span>
        <?php endif; ?>
        <span class="meta-item">🎂 Joined <?php echo date('M Y', strtotime($user['created_at'])); ?></span>
      </div>
    </div>

    <!-- Actions -->
    <div class="profile-actions">
      <button class="action-btn primary" onclick="showTipModal(<?php echo $user['id']; ?>)" title="Send Tip">💰</button>
      <a href="/messages-compose.php?to=<?php echo $user['id']; ?>" class="action-btn" title="Message">💬</a>
      <button class="action-btn" onclick="toggleFavorite(<?php echo $user['id']; ?>)" title="Favorite">⭐</button>
      <button class="action-btn" onclick="shareProfile(<?php echo $user['id']; ?>)" title="Share">🔗</button>
    </div>

    <!-- Social Links -->
    <?php if(!empty($user['social_links'])): ?>
    <div class="profile-social">
      <?php if($user['social_links']['tiktok']): ?>
      <a href="<?php echo $user['social_links']['tiktok']; ?>" target="_blank" class="social-btn tiktok">
        <i class="bi bi-tiktok"></i> TikTok
      </a>
      <?php endif; ?>
      <?php if($user['social_links']['youtube']): ?>
      <a href="<?php echo $user['social_links']['youtube']; ?>" target="_blank" class="social-btn youtube">
        <i class="bi bi-youtube"></i> Youtube
      </a>
      <?php endif; ?>
      <?php if($user['social_links']['instagram']): ?>
      <a href="<?php echo $user['social_links']['instagram']; ?>" target="_blank" class="social-btn instagram">
        <i class="bi bi-instagram"></i> Instagram
      </a>
      <?php endif; ?>
      <?php if($user['social_links']['twitch']): ?>
      <a href="<?php echo $user['social_links']['twitch']; ?>" target="_blank" class="social-btn twitch">
        <i class="bi bi-twitch"></i> Twitch
      </a>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Content Grid -->
    <div class="profile-content-grid">
      <?php foreach($content_items as $item): ?>
      <div class="content-thumb" onclick="window.location.href='/view-content.php?id=<?php echo $item['id']; ?>'">
        <img src="<?php echo htmlspecialchars($item['thumbnail']); ?>" alt="Content">
        <div class="content-thumb-overlay">
          <span class="thumb-icon"><?php echo $item['type'] === 'video' ? '🎥' : '📸'; ?></span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
```

---

### 2. Subscription Card
**Compact card for following/subscription lists**

```php
<div class="subscription-card" onclick="window.location.href='/profile.php?id=<?php echo $user['id']; ?>'">
  <!-- Cover -->
  <div class="subscription-cover">
    <?php if($user['cover_image']): ?>
    <img src="<?php echo htmlspecialchars($user['cover_image']); ?>" alt="Cover">
    <?php endif; ?>
    <div class="subscription-status">
      <?php echo $user['is_online'] ? 'Available now' : 'Offline'; ?>
    </div>
  </div>

  <div class="subscription-body">
    <!-- Avatar -->
    <div class="subscription-avatar-wrapper">
      <div class="subscription-avatar">
        <?php if($user['avatar']): ?>
        <img src="<?php echo htmlspecialchars($user['avatar']); ?>" alt="Avatar">
        <?php endif; ?>
      </div>
      <?php if($user['is_online']): ?>
      <div class="online-indicator"></div>
      <?php endif; ?>
      <?php if($user['is_verified']): ?>
      <div class="verification-badge"><i class="bi bi-check"></i></div>
      <?php endif; ?>
    </div>

    <!-- Info -->
    <div class="subscription-info">
      <div class="subscription-username">
        <?php echo htmlspecialchars($user['display_name']); ?>
        <?php if($user['is_verified']): ?>
        <i class="bi bi-patch-check-fill" style="color:#4267f5"></i>
        <?php endif; ?>
      </div>
      <div class="subscription-handle">@<?php echo htmlspecialchars($user['username']); ?></div>
    </div>

    <!-- Favorite Link -->
    <div style="text-align:center;margin-bottom:1rem">
      <a href="#" onclick="event.stopPropagation();toggleFavorite(<?php echo $user['id']; ?>)" style="color:#4267f5;font-size:.9rem;text-decoration:none">
        ⭐ Add to favorites and other lists
      </a>
    </div>

    <!-- Actions -->
    <div class="subscription-actions">
      <a href="/messages-compose.php?to=<?php echo $user['id']; ?>" class="subscription-btn message" onclick="event.stopPropagation()">
        <i class="bi bi-chat"></i> Message
      </a>
      <button class="subscription-btn tip" onclick="event.stopPropagation();showTipModal(<?php echo $user['id']; ?>)">
        <i class="bi bi-currency-dollar"></i> Send a tip
      </button>
    </div>

    <!-- Subscription Badge -->
    <?php if($subscription['is_free']): ?>
    <div class="subscription-badge free">SUBSCRIBED FOR FREE</div>
    <?php elseif($subscription['is_subscribed']): ?>
    <div class="subscription-badge premium">
      SUBSCRIBED - $<?php echo number_format($subscription['price'], 2); ?>/mo
    </div>
    <?php else: ?>
    <div class="subscription-badge" style="border-color:#ef4444;color:#ef4444;background:rgba(239,68,68,.1)">
      🔒 NOT SUBSCRIBED
    </div>
    <?php endif; ?>
  </div>
</div>
```

---

### 3. User List Item
**Horizontal list item for search results, followers, etc.**

```php
<div class="user-list-item" onclick="window.location.href='/profile.php?id=<?php echo $user['id']; ?>'">
  <div class="user-list-avatar">
    <?php if($user['avatar']): ?>
    <img src="<?php echo htmlspecialchars($user['avatar']); ?>" alt="Avatar">
    <?php endif; ?>
    <?php if($user['is_online']): ?>
    <div class="online-indicator"></div>
    <?php endif; ?>
  </div>
  
  <div class="user-list-info">
    <div class="user-list-username">
      <?php echo htmlspecialchars($user['display_name']); ?>
      <?php if($user['is_verified']): ?>
      <i class="bi bi-patch-check-fill" style="color:#4267f5"></i>
      <?php endif; ?>
    </div>
    <div class="user-list-meta">
      <span>@<?php echo htmlspecialchars($user['username']); ?></span>
      <span>• <?php echo $user['is_online'] ? 'Online now' : 'Last seen ' . timeAgo($user['last_seen']); ?></span>
      <span>• <?php echo number_format($user['post_count']); ?> posts</span>
    </div>
  </div>
  
  <div class="user-list-actions">
    <a href="/messages-compose.php?to=<?php echo $user['id']; ?>" class="user-list-btn" onclick="event.stopPropagation()">
      <i class="bi bi-chat"></i> Message
    </a>
    <button class="user-list-btn" onclick="event.stopPropagation();toggleFollow(<?php echo $user['id']; ?>)">
      <i class="bi bi-star"></i>
    </button>
  </div>
</div>
```

---

## 🎯 Features

### Profile Header Card
- ✅ Large cover image/video
- ✅ Stats bar (photos, videos, streams, likes, followers)
- ✅ Profile avatar with online indicator
- ✅ Verification badge
- ✅ Username, handle, bio
- ✅ Last seen status
- ✅ Action buttons (tip, message, favorite, share)
- ✅ Social media links
- ✅ Content preview grid

### Subscription Card
- ✅ Cover image
- ✅ Online/offline status
- ✅ Avatar with badges
- ✅ Quick actions (message, tip)
- ✅ Subscription status display
- ✅ Favorite link

### User List Item
- ✅ Horizontal layout
- ✅ Avatar with online indicator
- ✅ Username and handle
- ✅ Last seen status
- ✅ Post count
- ✅ Quick actions

---

## 🎨 Customization

### CSS Variables
```css
:root {
  --bg-dark: #0a0a0f;
  --bg-card: #1a1a2e;
  --border: #2d2d44;
  --blue: #4267f5;
  --text: #fff;
  --gray: #a0a0b0;
  --green: #10b981;
  --orange: #f59e0b;
  --red: #ef4444;
  --cyan: #06b6d4;
}
```

### Modify Colors
Change these variables to match your brand:
```css
--blue: #your-primary-color;
--green: #your-success-color;
--orange: #your-warning-color;
```

---

## 📱 Responsive Design

All components are fully responsive:
- **Desktop**: Full layout with all features
- **Tablet**: Adjusted spacing and sizes
- **Mobile**: Optimized for small screens
  - 3-column grid becomes 2-column on very small screens
  - Stats bar compacts
  - Actions stack vertically when needed

---

## 🚀 Usage Examples

### Profile Page
```php
<?php
require_once 'config/database.php';
// Fetch user data
$user = getUserById($_GET['id']);
$stats = getUserStats($user['id']);
$content_items = getUserContent($user['id'], 0, 9);
include 'views/header.php';
?>

<link rel="stylesheet" href="/assets/css/creator-cards.css">

<div class="container">
  <!-- Use Profile Header Card here -->
</div>

<?php include 'views/footer.php'; ?>
```

### Following Page
```php
<?php
$subscriptions = getUserSubscriptions($_SESSION['user_id']);
?>

<div class="container">
  <h2>Following</h2>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:2rem">
    <?php foreach($subscriptions as $sub): ?>
      <!-- Use Subscription Card here -->
    <?php endforeach; ?>
  </div>
</div>
```

### Search Results
```php
<?php
$users = searchUsers($_GET['q']);
?>

<div class="container">
  <h2>Search Results</h2>
  <?php foreach($users as $user): ?>
    <!-- Use User List Item here -->
  <?php endforeach; ?>
</div>
```

---

## 💡 Tips

1. **Always include Bootstrap Icons** for proper icon display
2. **Use `onclick="event.stopPropagation()"` on nested buttons** inside clickable cards
3. **Lazy load images** for better performance: `<img loading="lazy">`
4. **Add blur to locked content** for premium users
5. **Use timeAgo() function** for friendly timestamps

---

## 🔧 Helper Functions

```php
function timeAgo($timestamp) {
    $time_ago = strtotime($timestamp);
    $current_time = time();
    $time_difference = $current_time - $time_ago;
    
    if($time_difference < 60) return 'Just now';
    if($time_difference < 3600) return floor($time_difference / 60) . ' minutes ago';
    if($time_difference < 86400) return floor($time_difference / 3600) . ' hours ago';
    if($time_difference < 604800) return floor($time_difference / 86400) . ' days ago';
    return date('M j, Y', $time_ago);
}
```

---

**Need help? Check `/components/creator-card-examples.html` for live examples!**