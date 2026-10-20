<?php
session_start();
require_once 'config/database.php';
require_once 'classes/Listing.php';

$database = new Database();
$db = $database->getConnection();

$location = $_GET['location'] ?? '';
$category = $_GET['category'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$per_page = 20;

if(!$location) {
    header('Location: choose-location.php');
    exit();
}

// Save current city to session
$_SESSION['current_city'] = $location;

// Get city info
$query = "SELECT * FROM cities WHERE name = :location LIMIT 1";
$stmt = $db->prepare($query);
$stmt->execute(['location' => $location]);
$city = $stmt->fetch();

if(!$city) {
    header('Location: choose-location.php');
    exit();
}

// Get categories
$query = "SELECT * FROM categories ORDER BY name ASC";
$stmt = $db->query($query);
$categories = $stmt->fetchAll();

// Check which columns exist
$query = "SHOW COLUMNS FROM listings";
$stmt = $db->query($query);
$existing_columns = [];
while($col = $stmt->fetch()) {
    $existing_columns[] = $col['Field'];
}

$has_is_deleted = in_array('is_deleted', $existing_columns);
$has_status = in_array('status', $existing_columns);

// Build WHERE clause
$where_conditions = ["l.city_id = :city_id"];
if($has_is_deleted) $where_conditions[] = "l.is_deleted = FALSE";
if($has_status) $where_conditions[] = "l.status = 'active'";
$where_clause = implode(' AND ', $where_conditions);

// Get listings
$offset = ($page - 1) * $per_page;

if($category) {
    $query = "SELECT l.*, c.name as category_name, u.username, u.is_premium
              FROM listings l
              LEFT JOIN categories c ON l.category_id = c.id
              LEFT JOIN users u ON l.user_id = u.id
              WHERE $where_clause
              AND c.slug = :category
              ORDER BY l.is_featured DESC, l.created_at DESC
              LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':city_id', $city['id'], PDO::PARAM_INT);
    $stmt->bindParam(':category', $category);
    $stmt->bindParam(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
} else {
    $query = "SELECT l.*, c.name as category_name, u.username, u.is_premium
              FROM listings l
              LEFT JOIN categories c ON l.category_id = c.id
              LEFT JOIN users u ON l.user_id = u.id
              WHERE $where_clause
              ORDER BY l.is_featured DESC, l.created_at DESC
              LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':city_id', $city['id'], PDO::PARAM_INT);
    $stmt->bindParam(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
}

$stmt->execute();
$listings = $stmt->fetchAll();

// Get total count
if($category) {
    $query = "SELECT COUNT(*) FROM listings l
              LEFT JOIN categories c ON l.category_id = c.id
              WHERE $where_clause AND c.slug = :category";
    $stmt = $db->prepare($query);
    $stmt->execute(['city_id' => $city['id'], 'category' => $category]);
} else {
    $query = "SELECT COUNT(*) FROM listings l WHERE $where_clause";
    $stmt = $db->prepare($query);
    $stmt->execute(['city_id' => $city['id']]);
}
$total_listings = $stmt->fetchColumn();
$total_pages = ceil($total_listings / $per_page);

// Get nearby users (if logged in and has location)
$nearby_users = [];
if(isset($_SESSION['user_id'])) {
    $query = "SELECT current_latitude, current_longitude FROM users WHERE id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $my_location = $stmt->fetch();
    
    if($my_location && $my_location['current_latitude']) {
        $query = "SELECT u.id, u.username, u.is_premium, u.is_online,
                  (6371 * acos(cos(radians(:lat)) * cos(radians(current_latitude)) * 
                  cos(radians(current_longitude) - radians(:lng)) + 
                  sin(radians(:lat2)) * sin(radians(current_latitude)))) AS distance
                  FROM users u
                  WHERE u.id != :user_id
                  AND u.current_latitude IS NOT NULL
                  AND u.is_online = 1
                  HAVING distance < 50
                  ORDER BY distance ASC
                  LIMIT 12";
        
        $stmt = $db->prepare($query);
        $stmt->execute([
            'lat' => $my_location['current_latitude'],
            'lng' => $my_location['current_longitude'],
            'lat2' => $my_location['current_latitude'],
            'user_id' => $_SESSION['user_id']
        ]);
        $nearby_users = $stmt->fetchAll();
    }
}

include 'views/header.php';
?>

<style>
.city-hero {
    background: linear-gradient(135deg, rgba(15, 23, 42, 0.95), rgba(30, 41, 59, 0.95));
    backdrop-filter: blur(20px);
    border-bottom: 2px solid rgba(66, 103, 245, 0.2);
    padding: 4rem 0 3rem;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
}

.city-hero::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 500px;
    height: 500px;
    background: radial-gradient(circle, rgba(66, 103, 245, 0.15), transparent);
    border-radius: 50%;
    animation: float 20s infinite;
}

.city-hero::after {
    content: '';
    position: absolute;
    bottom: -30%;
    left: -5%;
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, rgba(29, 155, 240, 0.1), transparent);
    border-radius: 50%;
    animation: float 15s infinite reverse;
}

@keyframes float {
    0%, 100% { transform: translate(0, 0); }
    50% { transform: translate(30px, 30px); }
}

.hero-content {
    position: relative;
    z-index: 1;
}

.city-title {
    font-size: 3rem;
    font-weight: 800;
    color: white;
    margin-bottom: 1rem;
    text-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
}

.city-subtitle {
    font-size: 1.2rem;
    color: rgba(255, 255, 255, 0.9);
    margin-bottom: 2rem;
}

.search-bar-container {
    max-width: 800px;
    margin: 0 auto;
}

.search-bar {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(20px);
    border: 2px solid rgba(255, 255, 255, 0.2);
    border-radius: 60px;
    padding: 0.5rem;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.search-input {
    flex: 1;
    border: none;
    padding: 1rem 1.5rem;
    font-size: 1rem;
    border-radius: 50px;
    background: transparent;
    color: white;
}

.search-input::placeholder {
    color: rgba(255, 255, 255, 0.6);
}

.search-input:focus {
    outline: none;
}

.search-btn {
    background: linear-gradient(135deg, #4267F5, #1D9BF0);
    border: none;
    color: white;
    padding: 1rem 2.5rem;
    border-radius: 50px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s;
    white-space: nowrap;
}

.search-btn:hover {
    transform: scale(1.05);
    box-shadow: 0 8px 24px rgba(66, 103, 245, 0.6);
}

.quick-filters {
    display: flex;
    gap: 1rem;
    justify-content: center;
    margin-top: 1.5rem;
    flex-wrap: wrap;
}

.filter-chip {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    color: white;
    padding: 0.5rem 1.25rem;
    border-radius: 25px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s;
    border: 2px solid rgba(255, 255, 255, 0.2);
}

.filter-chip:hover,
.filter-chip.active {
    background: rgba(255, 255, 255, 0.95);
    color: #1e293b;
    transform: translateY(-2px);
    border-color: rgba(255, 255, 255, 0.95);
}

.container-modern {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 1.5rem;
}

.content-section {
    margin: 3rem 0;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}

.section-title {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-white);
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.location-setter {
    background: linear-gradient(135deg, rgba(15, 23, 42, 0.95), rgba(30, 41, 59, 0.95));
    backdrop-filter: blur(20px);
    border: 2px solid rgba(66, 103, 245, 0.3);
    border-radius: 20px;
    padding: 2rem;
    margin-bottom: 3rem;
    display: flex;
    align-items: center;
    gap: 1.5rem;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
}

.location-icon {
    width: 64px;
    height: 64px;
    border-radius: 16px;
    background: linear-gradient(135deg, #4267F5, #1D9BF0);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: white;
    flex-shrink: 0;
}

.location-content {
    flex: 1;
}

.nearby-users-section {
    background: linear-gradient(135deg, rgba(15, 23, 42, 0.95), rgba(30, 41, 59, 0.95));
    backdrop-filter: blur(20px);
    border: 2px solid rgba(66, 103, 245, 0.3);
    border-radius: 24px;
    padding: 2rem;
    margin-bottom: 3rem;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

.nearby-users-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-top: 1.5rem;
}

.user-card {
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(10px);
    border: 2px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    padding: 1.5rem;
    transition: all 0.3s;
    cursor: pointer;
}

.user-card:hover {
    transform: translateY(-4px);
    border-color: #4267F5;
    box-shadow: 0 12px 40px rgba(66, 103, 245, 0.3);
}

.user-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}

.user-avatar-large {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: linear-gradient(135deg, #4267F5, #1D9BF0);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    color: white;
    font-weight: 700;
    position: relative;
}

.online-indicator {
    position: absolute;
    bottom: 0;
    right: 0;
    width: 16px;
    height: 16px;
    background: #10b981;
    border: 3px solid rgba(15, 23, 42, 0.95);
    border-radius: 50%;
    box-shadow: 0 0 10px rgba(16, 185, 129, 0.5);
}

.user-info {
    flex: 1;
}

.user-name {
    font-weight: 700;
    color: white;
    margin-bottom: 0.25rem;
}

.user-distance {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.6);
}

.categories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 3rem;
}

.category-card {
    background: var(--card-bg);
    backdrop-filter: blur(20px);
    border: 2px solid var(--border-color);
    border-radius: 16px;
    padding: 1.5rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
    position: relative;
    overflow: hidden;
    text-decoration: none;
}

.category-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #4267F5, #1D9BF0);
    transform: scaleX(0);
    transition: transform 0.3s;
}

.category-card:hover::before {
    transform: scaleX(1);
}

.category-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.2);
    border-color: #4267F5;
}

.category-icon {
    font-size: 2.5rem;
    margin-bottom: 0.75rem;
}

.category-name {
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 0.5rem;
}

.category-count {
    color: var(--text-gray);
    font-size: 0.9rem;
}

.listings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
}

.listing-card {
    background: var(--card-bg);
    backdrop-filter: blur(20px);
    border: 2px solid var(--border-color);
    border-radius: 20px;
    overflow: hidden;
    transition: all 0.3s;
    position: relative;
}

.listing-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    border-color: #4267F5;
}

.listing-image {
    width: 100%;
    height: 250px;
    object-fit: cover;
    background: linear-gradient(135deg, #4267F5, #1D9BF0);
}

.listing-badge {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: linear-gradient(135deg, #fbbf24, #f59e0b);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 25px;
    font-size: 0.85rem;
    font-weight: 700;
    box-shadow: 0 4px 12px rgba(251, 191, 36, 0.4);
}

.listing-content {
    padding: 1.5rem;
}

.listing-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-white);
    margin: 0 0 0.5rem;
    line-height: 1.3;
}

.listing-title a {
    color: inherit;
    text-decoration: none;
}

.listing-title a:hover {
    color: #4267F5;
}

.listing-meta {
    display: flex;
    gap: 1.5rem;
    color: var(--text-gray);
    font-size: 0.9rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}

.listing-meta span {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.listing-excerpt {
    color: var(--text-gray);
    line-height: 1.6;
    margin-bottom: 1rem;
}

.listing-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 1rem;
    border-top: 2px solid var(--border-color);
}

.listing-author {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.author-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, #4267F5, #1D9BF0);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
}

.view-btn {
    background: linear-gradient(135deg, #4267F5, #1D9BF0);
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
    display: inline-block;
}

.view-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(66, 103, 245, 0.4);
    color: white;
}

.empty-state {
    text-align: center;
    padding: 6rem 2rem;
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 20px;
}

.empty-icon {
    font-size: 5rem;
    margin-bottom: 1.5rem;
    opacity: 0.3;
}

.floating-action {
    position: fixed;
    bottom: 100px;
    right: 30px;
    z-index: 999;
}

.fab-btn {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    background: linear-gradient(135deg, #4267F5, #1D9BF0);
    color: white;
    border: none;
    font-size: 2rem;
    cursor: pointer;
    box-shadow: 0 12px 40px rgba(66, 103, 245, 0.5);
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
}

.fab-btn:hover {
    transform: scale(1.1) rotate(90deg);
    box-shadow: 0 16px 60px rgba(66, 103, 245, 0.7);
    color: white;
}

@media (max-width: 768px) {
    .city-title {
        font-size: 2rem;
    }
    
    .search-bar {
        flex-direction: column;
        border-radius: 20px;
    }
    
    .search-input {
        width: 100%;
    }
    
    .search-btn {
        width: 100%;
    }
    
    .location-setter {
        flex-direction: column;
        text-align: center;
    }
    
    .categories-grid,
    .nearby-users-grid {
        grid-template-columns: 1fr;
    }
    
    .listings-grid {
        grid-template-columns: 1fr;
    }
    
    .floating-action {
        bottom: 80px;
        right: 20px;
    }
}
</style>

<!-- Hero Section -->
<div class="city-hero">
    <div class="container-modern">
        <div class="hero-content text-center">
            <h1 class="city-title">
                📍 <?php echo htmlspecialchars($city['name']); ?>
            </h1>
            <p class="city-subtitle">
                Discover <?php echo number_format($total_listings); ?> active listings in your area
            </p>
            
            <!-- Search Bar -->
            <div class="search-bar-container">
                <form action="/search.php" method="GET" class="search-bar">
                    <input type="text" 
                           name="q" 
                           class="search-input" 
                           placeholder="Search listings in <?php echo htmlspecialchars($city['name']); ?>..."
                           value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>">
                    <input type="hidden" name="location" value="<?php echo htmlspecialchars($location); ?>">
                    <button type="submit" class="search-btn">
                        <i class="bi bi-search"></i> Search
                    </button>
                </form>
            </div>

            <!-- Quick Filters -->
            <div class="quick-filters">
                <a href="?location=<?php echo urlencode($location); ?>" 
                   class="filter-chip <?php echo empty($category) ? 'active' : ''; ?>">
                    All
                </a>
                <?php foreach(array_slice($categories, 0, 5) as $cat): ?>
                <a href="?location=<?php echo urlencode($location); ?>&category=<?php echo $cat['slug']; ?>" 
                   class="filter-chip <?php echo $category === $cat['slug'] ? 'active' : ''; ?>">
                    <?php echo htmlspecialchars($cat['name']); ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="container-modern">
    
    <!-- Location Setter -->
    <?php if(isset($_SESSION['user_id']) && empty($my_location['current_latitude'])): ?>
    <div class="location-setter">
        <div class="location-icon">
            <i class="bi bi-geo-alt-fill"></i>
        </div>
        <div class="location-content">
            <h3 style="color: white; margin: 0 0 0.5rem;">Enable Your Location</h3>
            <p style="color: rgba(255, 255, 255, 0.8); margin: 0;">
                Share your location to see nearby users and get personalized recommendations
            </p>
        </div>
        <button class="btn btn-primary" onclick="enableLocation()">
            <i class="bi bi-geo-alt"></i> Enable Now
        </button>
    </div>
    <?php endif; ?>

    <!-- Nearby Users -->
    <?php if(!empty($nearby_users)): ?>
    <div class="nearby-users-section">
        <h2 class="section-title" style="color: white;">
            <i class="bi bi-people-fill"></i>
            Nearby Users
            <span class="badge bg-success ms-2"><?php echo count($nearby_users); ?> Online</span>
        </h2>
        
        <div class="nearby-users-grid">
            <?php foreach($nearby_users as $user): ?>
            <a href="/profile.php?id=<?php echo $user['id']; ?>" class="user-card">
                <div class="user-header">
                    <div class="user-avatar-large">
                        <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                        <span class="online-indicator"></span>
                    </div>
                    <div class="user-info">
                        <div class="user-name">
                            <?php echo htmlspecialchars($user['username']); ?>
                            <?php if($user['is_premium']): ?>
                            <i class="bi bi-patch-check-fill text-warning"></i>
                            <?php endif; ?>
                        </div>
                        <div class="user-distance">
                            <i class="bi bi-geo-alt-fill"></i>
                            <?php echo round($user['distance'], 1); ?> km away
                        </div>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
	

    <!-- Categories Section -->
    <?php if(empty($category)): ?>
    <div class="content-section">
        <div class="section-header">
            <h2 class="section-title">
                <i class="bi bi-grid-3x3-gap-fill"></i>
                Browse by Category
            </h2>
        </div>
        
        <div class="categories-grid">
            <?php foreach($categories as $cat): ?>
            <?php
            $count_query = "SELECT COUNT(*) FROM listings l
                           LEFT JOIN categories c ON l.category_id = c.id
                           WHERE l.city_id = :city_id AND c.id = :cat_id";
            if($has_is_deleted) $count_query .= " AND l.is_deleted = FALSE";
            if($has_status) $count_query .= " AND l.status = 'active'";
            
            $stmt = $db->prepare($count_query);
            $stmt->execute(['city_id' => $city['id'], 'cat_id' => $cat['id']]);
            $cat_count = $stmt->fetchColumn();
            ?>
            <a href="?location=<?php echo urlencode($location); ?>&category=<?php echo $cat['slug']; ?>" 
               class="category-card">
                <div class="category-icon">
                    <?php
                    $icons = [
                        'women-seeking-men' => '👩',
                        'men-seeking-women' => '👨',
                        'couples' => '💑',
                        'transgender' => '⚧️',
                        'casual-encounters' => '💋',
                        'other' => '✨'
                    ];
                    echo $icons[$cat['slug']] ?? '📌';
                    ?>
                </div>
                <div class="category-name"><?php echo htmlspecialchars($cat['name']); ?></div>
                <div class="category-count"><?php echo number_format($cat_count); ?> listings</div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Listings Section -->
    <div class="content-section">
        <div class="section-header">
            <h2 class="section-title">
                <i class="bi bi-fire"></i>
                <?php echo $category ? htmlspecialchars(str_replace('-', ' ', ucwords($category, '-'))) : 'Latest Listings'; ?>
            </h2>
            <div class="dropdown">
                <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-funnel"></i> Sort
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="?location=<?php echo urlencode($location); ?>&sort=newest">Newest First</a></li>
                    <li><a class="dropdown-item" href="?location=<?php echo urlencode($location); ?>&sort=popular">Most Popular</a></li>
                    <li><a class="dropdown-item" href="?location=<?php echo urlencode($location); ?>&sort=featured">Featured</a></li>
                </ul>
            </div>
        </div>

        <?php if(empty($listings)): ?>
        <div class="empty-state">
            <div class="empty-icon">🔍</div>
            <h3>No listings found</h3>
            <p class="text-muted">Be the first to post in this category!</p>
            <?php if(isset($_SESSION['user_id'])): ?>
            <a href="/post-ad.php" class="btn btn-primary mt-3">
                <i class="bi bi-plus-circle"></i> Post First Listing
            </a>
            <?php else: ?>
            <a href="/login.php" class="btn btn-primary mt-3">
                <i class="bi bi-box-arrow-in-right"></i> Login to Post
            </a>
            <?php endif; ?>
        </div>
        <?php else: ?>
        
        <div class="listings-grid">
            <?php foreach($listings as $listing): ?>
            <div class="listing-card">
                <?php if($listing['is_featured']): ?>
                <div class="listing-badge">
                    <i class="bi bi-star-fill"></i> Featured
                </div>
                <?php endif; ?>
                
                <?php if($listing['photo_url']): ?>
                <img src="<?php echo htmlspecialchars($listing['photo_url']); ?>" 
                     alt="<?php echo htmlspecialchars($listing['title']); ?>"
                     class="listing-image">
                <?php else: ?>
                <div class="listing-image d-flex align-items-center justify-content-center">
                    <i class="bi bi-image" style="font-size: 4rem; color: white; opacity: 0.5;"></i>
                </div>
                <?php endif; ?>
                
                <div class="listing-content">
                    <div>
                        <h3 class="listing-title">
                            <a href="/listing.php?id=<?php echo $listing['id']; ?>">
                                <?php echo htmlspecialchars($listing['title']); ?>
                            </a>
                        </h3>
                        <span class="badge" style="background: rgba(66, 103, 245, 0.2); color: #4267F5;">
                            <?php echo htmlspecialchars($listing['category_name']); ?>
                        </span>
                    </div>
                    
                    <div class="listing-meta">
                        <span>
                            <i class="bi bi-geo-alt-fill"></i>
                            <?php echo htmlspecialchars($city['name']); ?>
                        </span>
                        <span>
                            <i class="bi bi-clock-fill"></i>
                            <?php
                            $diff = time() - strtotime($listing['created_at']);
                            if($diff < 3600) echo floor($diff/60) . 'm ago';
                            elseif($diff < 86400) echo floor($diff/3600) . 'h ago';
                            else echo floor($diff/86400) . 'd ago';
                            ?>
                        </span>
                        <span>
                            <i class="bi bi-eye-fill"></i>
                            <?php echo number_format($listing['views']); ?>
                        </span>
                    </div>
                    
                    <div class="listing-excerpt">
                        <?php 
                        $excerpt = strip_tags($listing['description']);
                        echo htmlspecialchars(substr($excerpt, 0, 120));
                        echo strlen($excerpt) > 120 ? '...' : '';
                        ?>
                    </div>
                    
                    <div class="listing-footer">
                        <div class="listing-author">
                            <div class="author-avatar">
                                <?php echo strtoupper(substr($listing['username'], 0, 1)); ?>
                            </div>
                            <div>
                                <div style="font-weight: 600; color: var(--text-white); font-size: 0.9rem;">
                                    <?php echo htmlspecialchars($listing['username']); ?>
                                    <?php if($listing['is_premium']): ?>
                                    <i class="bi bi-patch-check-fill text-warning"></i>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <a href="/listing.php?id=<?php echo $listing['id']; ?>" class="view-btn">
                            View <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if($total_pages > 1): ?>
        <nav class="mt-5">
            <ul class="pagination justify-content-center">
                <?php if($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?location=<?php echo urlencode($location); ?>&category=<?php echo urlencode($category); ?>&page=<?php echo $page - 1; ?>">
                        <i class="bi bi-chevron-left"></i> Previous
                    </a>
                </li>
                <?php endif; ?>
                
                <?php for($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?location=<?php echo urlencode($location); ?>&category=<?php echo urlencode($category); ?>&page=<?php echo $i; ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
                <?php endfor; ?>
                
                <?php if($page < $total_pages): ?>
                <li class="page-item">
                    <a class="page-link" href="?location=<?php echo urlencode($location); ?>&category=<?php echo urlencode($category); ?>&page=<?php echo $page + 1; ?>">
                        Next <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
        
        <?php endif; ?>
    </div>
</div>

<!-- Floating Action Button -->
<?php if(isset($_SESSION['user_id'])): ?>
<div class="floating-action">
    <a href="/post-ad.php" class="fab-btn" title="Post New Ad">
        <i class="bi bi-plus-lg"></i>
    </a>
</div>
<?php endif; ?>

<script>
function enableLocation() {
    if(!navigator.geolocation) {
        alert('Geolocation not supported');
        return;
    }
    
    navigator.geolocation.getCurrentPosition(
        (position) => {
            const formData = new FormData();
            formData.append('action', 'update_location');
            formData.append('latitude', position.coords.latitude);
            formData.append('longitude', position.coords.longitude);
            
            fetch('/api/location.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if(data.success) {
                    location.reload();
                }
            });
        },
        (error) => {
            alert('Unable to get location');
        }
    );
}
</script>

<?php include 'views/footer.php'; ?>