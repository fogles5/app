<?php
session_start();
require_once 'config/database.php';
require_once 'classes/Listing.php';
require_once 'classes/Favorites.php';

$database = new Database();
$db = $database->getConnection();

$listing_id = (int)($_GET['id'] ?? 0);

if(!$listing_id) {
    header('Location: index.php');
    exit();
}

// Get listing with user details
$query = "SELECT l.*, 
          c.name as category_name,
          ct.name as city_name,
          u.username,
          u.created_at as user_created,
          u.is_online,
          u.last_seen
          FROM listings l
          LEFT JOIN categories c ON l.category_id = c.id
          LEFT JOIN cities ct ON l.city_id = ct.id
          LEFT JOIN users u ON l.user_id = u.id
          WHERE l.id = :listing_id
          LIMIT 1";

$stmt = $db->prepare($query);
$stmt->bindParam(':listing_id', $listing_id);
$stmt->execute();
$listing = $stmt->fetch();

if(!$listing) {
    header('Location: index.php');
    exit();
}

// Increment view count
$listingObj = new Listing($db);
$listingObj->incrementViews($listing_id);

// Check if favorited
$is_favorited = false;
if(isset($_SESSION['user_id'])) {
    $favorites = new Favorites($db);
    $is_favorited = $favorites->isFavorited($_SESSION['user_id'], $listing_id);
}

$is_own_listing = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $listing['user_id'];

include 'views/header.php';
?>

<style>
.listing-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 1rem;
}

.listing-header {
    background: linear-gradient(135deg, #4267F5, #1D9BF0);
    padding: 2rem;
    border-radius: 15px;
    margin-bottom: 2rem;
    color: white;
    position: relative;
}

.listing-actions-top {
    position: absolute;
    top: 1rem;
    right: 1rem;
    display: flex;
    gap: 0.75rem;
}

/* Favorite Button (Heart) */
.favorite-btn {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    border: none;
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s cubic-bezier(0.23, 1, 0.320, 1);
    position: relative;
    overflow: hidden;
}

.favorite-btn::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #ff6b6b, #ee5a6f);
    transform: scale(0);
    transition: transform 0.6s cubic-bezier(0.23, 1, 0.320, 1);
    border-radius: 50%;
    z-index: 0;
}

.favorite-btn:hover::before {
    transform: scale(1);
}

.favorite-btn.favorited::before {
    transform: scale(1);
}

.favorite-btn svg {
    width: 24px;
    height: 24px;
    position: relative;
    z-index: 1;
    fill: white;
    transition: all 0.3s;
}

.favorite-btn:hover {
    transform: scale(1.1);
    box-shadow: 0 8px 20px rgba(239, 68, 68, 0.4);
}

.favorite-btn:active {
    transform: scale(0.95);
}

.favorite-btn.favorited svg {
    animation: heartBeat 0.6s ease-in-out;
}

@keyframes heartBeat {
    0%, 100% { transform: scale(1); }
    25% { transform: scale(1.3); }
    50% { transform: scale(1.1); }
    75% { transform: scale(1.2); }
}

/* Share Button */
.share-btn {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    overflow: hidden;
}

.share-btn::before {
    content: "";
    position: absolute;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 50%;
    transform: scale(0);
    transition: transform 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
}

.share-btn:hover::before {
    transform: scale(1);
}

.share-btn svg {
    width: 24px;
    height: 24px;
    z-index: 1;
    fill: white;
    transition: all 0.3s;
}

.share-btn:hover {
    transform: scale(1.1) rotate(10deg);
    box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
}

.share-btn:active {
    transform: scale(0.95) rotate(-5deg);
}

.share-btn:hover svg {
    transform: scale(1.1);
}

/* Share Menu */
.share-menu {
    position: absolute;
    top: 60px;
    right: 0;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 12px;
    padding: 0.5rem;
    display: none;
    flex-direction: column;
    gap: 0.5rem;
    min-width: 200px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
    z-index: 1000;
    animation: slideDown 0.3s ease;
}

.share-menu.active {
    display: flex;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.share-option {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    color: #1e293b;
    font-weight: 500;
}

.share-option:hover {
    background: rgba(66, 103, 245, 0.1);
    transform: translateX(5px);
}

.share-option svg {
    width: 20px;
    height: 20px;
    flex-shrink: 0;
}

.listing-meta {
    display: flex;
    gap: 1.5rem;
    flex-wrap: wrap;
    font-size: 0.9rem;
    opacity: 0.95;
    margin-top: 1rem;
}

.listing-content {
    display: grid;
    grid-template-columns: 1fr 350px;
    gap: 2rem;
    margin-bottom: 2rem;
}

.listing-main {
    min-width: 0;
}

.listing-image {
    width: 100%;
    height: auto;
    max-height: 600px;
    object-fit: cover;
    border-radius: 15px;
    margin-bottom: 2rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
}

.listing-body {
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 15px;
    padding: 2rem;
    margin-bottom: 2rem;
}

.listing-description {
    line-height: 1.8;
    color: var(--text-white);
    white-space: pre-wrap;
    word-wrap: break-word;
}

.listing-sidebar {
    position: sticky;
    top: 80px;
    height: fit-content;
}

.sidebar-card {
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 15px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.sidebar-title {
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--text-white);
    margin-bottom: 1rem;
}

.user-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    margin: 0 auto 1rem;
    position: relative;
}

.online-badge {
    position: absolute;
    bottom: 5px;
    right: 5px;
    width: 20px;
    height: 20px;
    background: var(--success-green);
    border: 3px solid var(--card-bg);
    border-radius: 50%;
    box-shadow: 0 0 10px rgba(16, 185, 129, 0.5);
}

.action-buttons {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.action-btn {
    width: 100%;
    padding: 0.875rem;
    border-radius: 12px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    text-decoration: none;
}

.btn-message {
    background: linear-gradient(135deg, #4267F5, #1D9BF0);
    color: white;
}

.btn-message:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(66, 103, 245, 0.4);
}

.btn-report {
    background: transparent;
    border: 2px solid var(--border-color);
    color: var(--text-gray);
    font-size: 0.9rem;
}

.btn-report:hover {
    border-color: var(--primary-blue);
    color: var(--primary-blue);
}

.listing-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 1.5rem;
}

.tag {
    padding: 0.5rem 1rem;
    background: rgba(66, 103, 245, 0.1);
    border: 1px solid rgba(66, 103, 245, 0.3);
    border-radius: 20px;
    font-size: 0.85rem;
    color: var(--primary-blue);
}

.warning-banner {
    background: rgba(239, 68, 68, 0.1);
    border: 2px solid var(--danger-red);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 2rem;
}

/* Toast Notification */
.toast-notification {
    position: fixed;
    top: 80px;
    right: 20px;
    background: white;
    padding: 1rem 1.5rem;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
    display: none;
    align-items: center;
    gap: 1rem;
    z-index: 9999;
    animation: slideInRight 0.3s ease;
}

.toast-notification.show {
    display: flex;
}

@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(100%);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

/* Mobile Responsiveness */
@media (max-width: 1024px) {
    .listing-content {
        grid-template-columns: 1fr;
    }
    
    .listing-sidebar {
        position: static;
    }
}

@media (max-width: 768px) {
    .listing-container {
        padding: 0.5rem;
    }
    
    .listing-header {
        padding: 1.5rem 1rem;
        border-radius: 0;
        margin: 0 0 1rem;
    }
    
    .listing-header h1 {
        font-size: 1.5rem;
        padding-right: 120px;
    }
    
    .listing-actions-top {
        top: 0.75rem;
        right: 0.75rem;
        gap: 0.5rem;
    }
    
    .favorite-btn,
    .share-btn {
        width: 45px;
        height: 45px;
    }
    
    .listing-meta {
        font-size: 0.85rem;
        gap: 1rem;
    }
    
    .listing-image {
        border-radius: 0;
        margin-bottom: 1rem;
    }
    
    .listing-body {
        padding: 1.5rem 1rem;
        border-radius: 12px;
        margin: 0 0.5rem 1rem;
    }
    
    .listing-description {
        font-size: 0.95rem;
        line-height: 1.6;
    }
    
    .sidebar-card {
        margin: 0 0.5rem 1rem;
        padding: 1.25rem;
    }
    
    .action-buttons {
        position: fixed;
        bottom: 70px;
        left: 0;
        right: 0;
        background: var(--card-bg);
        padding: 1rem;
        border-top: 2px solid var(--border-color);
        z-index: 100;
        flex-direction: row;
        box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.3);
    }
    
    .action-btn {
        font-size: 0.9rem;
        padding: 0.75rem 0.5rem;
    }
    
    body.has-bottom-nav {
        padding-bottom: 200px !important;
    }
}

@media (max-width: 480px) {
    .listing-meta {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .listing-tags {
        gap: 0.375rem;
    }
    
    .tag {
        padding: 0.375rem 0.75rem;
        font-size: 0.8rem;
    }
}
</style>

<div class="listing-container">
    
    <!-- Listing Header -->
    <div class="listing-header">
        <div class="listing-actions-top">
            <!-- Favorite Button -->
            <button class="favorite-btn <?php echo $is_favorited ? 'favorited' : ''; ?>" 
                    id="favoriteBtn" 
                    onclick="toggleFavorite()"
                    title="<?php echo $is_favorited ? 'Remove from favorites' : 'Add to favorites'; ?>">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12,21.35L10.55,20.03C5.4,15.36 2,12.27 2,8.5C2,5.41 4.42,3 7.5,3C9.24,3 10.91,3.81 12,5.08C13.09,3.81 14.76,3 16.5,3C19.58,3 22,5.41 22,8.5C22,12.27 18.6,15.36 13.45,20.03L12,21.35Z"/>
                </svg>
            </button>
            
            <!-- Share Button -->
            <div style="position: relative;">
                <button class="share-btn" id="shareBtn" onclick="toggleShareMenu()">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M18,16.08C17.24,16.08 16.56,16.38 16.04,16.85L8.91,12.7C8.96,12.47 9,12.24 9,12C9,11.76 8.96,11.53 8.91,11.3L15.96,7.19C16.5,7.69 17.21,8 18,8A3,3 0 0,0 21,5A3,3 0 0,0 18,2A3,3 0 0,0 15,5C15,5.24 15.04,5.47 15.09,5.7L8.04,9.81C7.5,9.31 6.79,9 6,9A3,3 0 0,0 3,12A3,3 0 0,0 6,15C6.79,15 7.5,14.69 8.04,14.19L15.16,18.34C15.11,18.55 15.08,18.77 15.08,19C15.08,20.61 16.39,21.91 18,21.91C19.61,21.91 20.92,20.61 20.92,19A2.92,2.92 0 0,0 18,16.08Z"/>
                    </svg>
                </button>
                
                <!-- Share Menu -->
                <div class="share-menu" id="shareMenu">
                    <a href="#" class="share-option" onclick="shareToFacebook(event)">
                        <svg viewBox="0 0 24 24" fill="#1877F2">
                            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                        </svg>
                        Share on Facebook
                    </a>
                    <a href="#" class="share-option" onclick="shareToTwitter(event)">
                        <svg viewBox="0 0 24 24" fill="#1DA1F2">
                            <path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/>
                        </svg>
                        Share on Twitter
                    </a>
                    <a href="#" class="share-option" onclick="shareViaEmail(event)">
                        <svg viewBox="0 0 24 24" fill="#EA4335">
                            <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                        </svg>
                        Share via Email
                    </a>
                    <a href="#" class="share-option" onclick="copyLink(event)">
                        <svg viewBox="0 0 24 24" fill="#6B7280">
                            <path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/>
                        </svg>
                        Copy Link
                    </a>
                </div>
            </div>
        </div>
        
        <h1 style="margin-bottom: 0.5rem;"><?php echo htmlspecialchars($listing['title']); ?></h1>
        <div class="listing-meta">
            <span><i class="bi bi-folder-fill"></i> <?php echo htmlspecialchars($listing['category_name']); ?></span>
            <span><i class="bi bi-geo-alt-fill"></i> <?php echo htmlspecialchars($listing['city_name']); ?></span>
            <span><i class="bi bi-eye-fill"></i> <?php echo number_format($listing['views']); ?> views</span>
            <span><i class="bi bi-clock-fill"></i> <?php echo date('M j, Y', strtotime($listing['created_at'])); ?></span>
            <?php if($listing['is_featured']): ?>
            <span class="badge bg-warning text-dark"><i class="bi bi-star-fill"></i> Featured</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Safety Warning -->
    <div class="warning-banner">
        <strong style="color: var(--danger-red);"><i class="bi bi-exclamation-triangle-fill"></i> Safety First</strong>
        <ul style="margin: 0.5rem 0 0 1.5rem; line-height: 1.8; color: var(--text-gray);">
            <li>Meet in public places</li>
            <li>Tell someone where you're going</li>
            <li>Never send money in advance</li>
            <li>Trust your instincts</li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="listing-content">
        <div class="listing-main">
            <?php if($listing['photo_url']): ?>
            <img src="<?php echo htmlspecialchars($listing['photo_url']); ?>" 
                 alt="<?php echo htmlspecialchars($listing['title']); ?>"
                 class="listing-image">
            <?php endif; ?>
            
            <div class="listing-body">
                <div class="listing-description">
                    <?php echo nl2br(htmlspecialchars($listing['description'])); ?>
                </div>
                
                <?php if(!empty($listing['tags'])): ?>
                <div class="listing-tags">
                    <?php foreach(explode(',', $listing['tags']) as $tag): ?>
                    <span class="tag"><?php echo htmlspecialchars(trim($tag)); ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="listing-sidebar">
            <?php if(!$is_own_listing): ?>
            <!-- Contact Card -->
            <div class="sidebar-card">
                <div class="sidebar-title"><i class="bi bi-chat-dots-fill"></i> Contact Poster</div>
                <div class="user-avatar">
                    <i class="bi bi-person-fill"></i>
                    <?php if($listing['is_online']): ?>
                    <span class="online-badge"></span>
                    <?php endif; ?>
                </div>
                <div style="text-align: center; margin-bottom: 1.5rem;">
                    <div style="font-weight: 600; color: var(--text-white); margin-bottom: 0.25rem;">
                        <?php echo htmlspecialchars($listing['username']); ?>
                    </div>
                    <div style="font-size: 0.85rem; color: var(--text-gray);">
                        <?php if($listing['is_online']): ?>
                            <i class="bi bi-circle-fill text-success"></i> Online Now
                        <?php elseif($listing['last_seen']): ?>
                            <i class="bi bi-circle"></i> Last seen <?php 
                                $diff = time() - strtotime($listing['last_seen']);
                                if($diff < 3600) echo floor($diff/60) . 'm ago';
                                elseif($diff < 86400) echo floor($diff/3600) . 'h ago';
                                else echo floor($diff/86400) . 'd ago';
                            ?>
                        <?php else: ?>
                            <i class="bi bi-circle"></i> Offline
                        <?php endif; ?>
                    </div>
                    <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">
                        Member since <?php echo !empty($listing['user_created']) ? date('M Y', strtotime($listing['user_created'])) : 'Unknown'; ?>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="/messages-chat-simple.php?user=<?php echo $listing['user_id']; ?>" class="action-btn btn-message">
                        <i class="bi bi-chat-dots-fill"></i> Send Message
                    </a>
                    <a href="/profile.php?id=<?php echo $listing['user_id']; ?>" class="action-btn btn-report">
                        <i class="bi bi-person-fill"></i> View Profile
                    </a>
                    <a href="/report-listing.php?listing_id=<?php echo $listing_id; ?>" class="action-btn btn-report">
                        <i class="bi bi-flag-fill"></i> Report Listing
                    </a>
                    <?php else: ?>
                    <a href="/login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="action-btn btn-message">
                        <i class="bi bi-chat-dots-fill"></i> Login to Message
                    </a>
                    <a href="/login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="action-btn btn-report">
                        <i class="bi bi-heart-fill"></i> Login to Favorite
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php else: ?>
            <!-- Owner Actions -->
            <div class="sidebar-card">
                <div class="sidebar-title"><i class="bi bi-file-text-fill"></i> Your Listing</div>
                <div class="action-buttons">
                    <a href="/edit-listing.php?id=<?php echo $listing_id; ?>" class="action-btn btn-message">
                        <i class="bi bi-pencil-fill"></i> Edit Listing
                    </a>
                    <button onclick="if(confirm('Delete this listing?')) location.href='/delete-listing.php?id=<?php echo $listing_id; ?>'" class="action-btn btn-report">
                        <i class="bi bi-trash-fill"></i> Delete Listing
                    </button>
                    <a href="/my-listings.php" class="action-btn btn-report">
                        <i class="bi bi-file-text-fill"></i> View All My Ads
                    </a>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Listing Info -->
            <div class="sidebar-card">
                <div class="sidebar-title"><i class="bi bi-info-circle-fill"></i> Listing Details</div>
                <div style="display: flex; flex-direction: column; gap: 0.75rem; font-size: 0.9rem;">
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-gray);">Posted:</span>
                        <span style="color: var(--text-white); font-weight: 600;"><?php echo date('M j, Y', strtotime($listing['created_at'])); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-gray);">Views:</span>
                        <span style="color: var(--text-white); font-weight: 600;"><?php echo number_format($listing['views']); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-gray);">Location:</span>
                        <span style="color: var(--text-white); font-weight: 600;"><?php echo htmlspecialchars($listing['city_name']); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-gray);">Category:</span>
                        <span style="color: var(--text-white); font-weight: 600;"><?php echo htmlspecialchars($listing['category_name']); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-gray);">Listing ID:</span>
                        <span style="color: var(--text-muted); font-family: monospace; font-size: 0.85rem;">#<?php echo $listing_id; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Toast Notification -->
<div class="toast-notification" id="toastNotification">
    <span id="toastMessage"></span>
</div>

<script>
let isFavorited = <?php echo $is_favorited ? 'true' : 'false'; ?>;
const listingUrl = window.location.href;
const listingTitle = <?php echo json_encode($listing['title']); ?>;

function toggleFavorite() {
    <?php if(!isset($_SESSION['user_id'])): ?>
    window.location.href = '/login.php';
    return;
    <?php else: ?>
    
    const btn = document.getElementById('favoriteBtn');
    btn.disabled = true;
    
    fetch('/api/favorites.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=toggle&listing_id=<?php echo $listing_id; ?>'
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            isFavorited = data.is_favorited;
            
            if(isFavorited) {
                btn.classList.add('favorited');
                showToast('Added to favorites! ❤️');
            } else {
                btn.classList.remove('favorited');
                showToast('Removed from favorites');
            }
        } else {
            showToast(data.error || 'Failed to update favorite');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to update favorite');
    })
    .finally(() => {
        btn.disabled = false;
    });
    <?php endif; ?>
}

function toggleShareMenu() {
    const menu = document.getElementById('shareMenu');
    menu.classList.toggle('active');
    
    // Close menu when clicking outside
    if(menu.classList.contains('active')) {
        document.addEventListener('click', closeShareMenuOutside);
    }
}

function closeShareMenuOutside(event) {
    const menu = document.getElementById('shareMenu');
    const btn = document.getElementById('shareBtn');
    
    if(!menu.contains(event.target) && !btn.contains(event.target)) {
        menu.classList.remove('active');
        document.removeEventListener('click', closeShareMenuOutside);
    }
}

function shareToFacebook(event) {
    event.preventDefault();
    const url = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(listingUrl)}`;
    window.open(url, '_blank', 'width=600,height=400');
    document.getElementById('shareMenu').classList.remove('active');
    showToast('Opening Facebook...');
}

function shareToTwitter(event) {
    event.preventDefault();
    const url = `https://twitter.com/intent/tweet?url=${encodeURIComponent(listingUrl)}&text=${encodeURIComponent(listingTitle)}`;
    window.open(url, '_blank', 'width=600,height=400');
    document.getElementById('shareMenu').classList.remove('active');
    showToast('Opening Twitter...');
}

function shareViaEmail(event) {
    event.preventDefault();
    const subject = encodeURIComponent(listingTitle);
    const body = encodeURIComponent(`Check out this listing on Turnpage: ${listingUrl}`);
    window.location.href = `mailto:?subject=${subject}&body=${body}`;
    document.getElementById('shareMenu').classList.remove('active');
    showToast('Opening email client...');
}

function copyLink(event) {
    event.preventDefault();
    navigator.clipboard.writeText(listingUrl).then(() => {
        showToast('Link copied to clipboard! 📋');
    }).catch(() => {
        showToast('Failed to copy link');
    });
    document.getElementById('shareMenu').classList.remove('active');
}

function showToast(message) {
    const toast = document.getElementById('toastNotification');
    const toastMessage = document.getElementById('toastMessage');
    
    toastMessage.textContent = message;
    toast.classList.add('show');
    
    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}
</script>

<?php include 'views/footer.php'; ?>