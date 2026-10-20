<?php
session_start();
require_once 'config/database.php';
require_once 'classes/UserProfile.php';
require_once 'classes/MessageLimits.php';

if(!isset($_GET['id'])) {
    if(isset($_SESSION['user_id'])) {
        header('Location: profile.php?id=' . $_SESSION['user_id']);
    } else {
        header('Location: login.php');
    }
    exit();
}

$database = new Database();
$db = $database->getConnection();
$userProfile = new UserProfile($db);

$profile_user_id = $_GET['id'];
$profile_data = $userProfile->getProfile($profile_user_id);

if(!$profile_data) {
    header('Location: choose-location.php');
    exit();
}

$is_own_profile = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $profile_user_id;

// Check message limits
$can_message = false;
$message_limit_info = null;
if(isset($_SESSION['user_id']) && !$is_own_profile) {
    $messageLimits = new MessageLimits($db);
    $message_limit_info = $messageLimits->canSendMessage($_SESSION['user_id']);
    $can_message = $message_limit_info['can_send'];
}

// Get user's listings
$query = "SELECT l.*, c.name as category_name, ct.name as city_name, s.abbreviation as state_abbr
          FROM listings l
          LEFT JOIN categories c ON l.category_id = c.id
          LEFT JOIN cities ct ON l.city_id = ct.id
          LEFT JOIN states s ON ct.state_id = s.id
          WHERE l.user_id = :user_id AND l.status = 'active'
          ORDER BY l.created_at DESC
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $profile_user_id);
$stmt->execute();
$user_listings = $stmt->fetchAll();

include 'views/header.php';
?>
<!-- Existing Theme Styles -->
<link rel="stylesheet" href="/assets/css/dark-blue-theme.css">
<link rel="stylesheet" href="/assets/css/creator-cards.css">

<!-- Bootstrap & Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css" rel="stylesheet">

<style>
.profile-wrapper {
    max-width: 1200px;
    margin: 2rem auto;
    padding: 0 1.5rem;
}

.profile-main-card {
    background: var(--card-bg);
    border-radius: 24px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    position: relative;
    z-index: 10;
    border: 2px solid var(--border-color);
}

.listing-card {
    background: var(--card-bg);
    border-radius: 16px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    border: 2px solid var(--border-color);
    transition: all 0.3s;
}

.listing-card:hover {
    border-color: var(--primary-blue);
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(66,103,245,0.2);
}

.section-title {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: var(--text-white);
}

@media (max-width: 768px) {
    .profile-cover { height: 200px; }
    .profile-username { font-size: 1.5rem; }
}
</style>

<div class="page-content">
    <div class="profile-wrapper">
        <div class="profile-header-card profile-main-card">
            <!-- Profile Cover -->
            <div class="profile-cover">
                <div class="profile-stats-bar">
                    <div class="stat-badge">
                        <i class="bi bi-calendar3"></i>
                        <span>Joined <?php echo date('M Y', strtotime($profile_data['created_at'])); ?></span>
                    </div>
                    <?php if($profile_data['occupation'] ?? false): ?>
                    <div class="stat-badge">
                        <i class="bi bi-briefcase"></i>
                        <span><?php echo htmlspecialchars($profile_data['occupation']); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="stat-badge">
                        <i class="bi bi-list-check"></i>
                        <span><?php echo count($user_listings); ?> Ads</span>
                    </div>
                </div>
            </div>
            
            <!-- Profile Main -->
            <div class="profile-main">
                <div class="profile-avatar-wrapper">
                    <div class="profile-avatar">
                        <img src="<?php echo htmlspecialchars($profile_data['avatar'] ?? '/assets/images/default-avatar.png'); ?>" alt="Profile">
                    </div>
                    <?php if($profile_data['is_online'] ?? false): ?>
                        <div class="online-indicator"></div>
                    <?php endif; ?>
                    <?php if($profile_data['verified'] ?? false): ?>
                        <div class="verification-badge">
                            <i class="bi bi-check-lg"></i>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="profile-info">
                    <h1 class="profile-username">
                        <?php echo htmlspecialchars($profile_data['username']); ?>
                        <?php if($profile_data['verified'] ?? false): ?>
                            <i class="bi bi-patch-check-fill" style="color:var(--primary-blue)"></i>
                        <?php endif; ?>
                    </h1>
                    <p class="profile-handle">@<?php echo htmlspecialchars($profile_data['username']); ?></p>
                    
                    <?php if($profile_data['is_online'] ?? false): ?>
                    <div class="profile-status">
                        <i class="bi bi-circle-fill"></i>
                        <span>Online now</span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if($profile_data['bio']): ?>
                    <p class="profile-bio"><?php echo nl2br(htmlspecialchars($profile_data['bio'])); ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- Profile Actions -->
                <div class="profile-actions">
                    <?php if($is_own_profile): ?>
                        <a href="edit-profile.php" class="btn-primary">
                            <i class="bi bi-pencil"></i> Edit Profile
                        </a>
                        <a href="my-listings.php" class="btn-secondary">
                            <i class="bi bi-list-check"></i> My Ads
                        </a>
                        <a href="settings.php" class="btn-secondary">
                            <i class="bi bi-gear"></i> Settings
                        </a>
                    <?php else: ?>
                        <?php if(isset($_SESSION['user_id'])): ?>
                            <?php if($can_message): ?>
                                <a href="messages-compose.php?to=<?php echo $profile_user_id; ?>" class="btn-primary">
                                    <i class="bi bi-chat-dots"></i> Send Message
                                </a>
                            <?php else: ?>
                                <button class="btn-primary" data-bs-toggle="modal" data-bs-target="#upgradeModal">
                                    <i class="bi bi-chat-dots"></i> Send Message
                                </button>
                            <?php endif; ?>
                            <button class="btn-danger" data-bs-toggle="modal" data-bs-target="#reportModal">
                                <i class="bi bi-flag"></i> Report
                            </button>
                        <?php else: ?>
                            <a href="login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="btn-primary">
                                <i class="bi bi-box-arrow-in-right"></i> Login to Message
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php if(count($user_listings) > 0): ?>
        <div class="card" style="margin-top:2rem">
            <h2 class="section-title">
                <i class="bi bi-grid-3x3"></i>
                <?php echo $is_own_profile ? 'My Listings' : 'Listings'; ?>
            </h2>
            
            <?php foreach($user_listings as $listing): ?>
            <a href="listing.php?id=<?php echo $listing['id']; ?>" class="listing-card" style="text-decoration:none;display:block">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <h3 class="h5 mb-2" style="color:var(--primary-blue)"><?php echo htmlspecialchars($listing['title']); ?></h3>
                        <div class="d-flex gap-2 mb-2 flex-wrap">
                            <span class="badge" style="background:var(--primary-blue);color:#fff"><?php echo htmlspecialchars($listing['category_name']); ?></span>
                            <span class="badge" style="background:var(--info-cyan);color:#fff"><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($listing['city_name']); ?></span>
                        </div>
                        <p style="color:var(--text-gray);margin:0"><?php echo htmlspecialchars(substr($listing['description'], 0, 150)); ?>...</p>
                    </div>
                    <div style="color:var(--text-gray);font-size:0.85rem">
                        <?php echo date('M j', strtotime($listing['created_at'])); ?>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Upgrade Modal -->
<div class="modal fade" id="upgradeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background:var(--card-bg);border:2px solid var(--border-color)">
            <div class="modal-header border-0">
                <h5 class="modal-title" style="color:var(--text-white)">💎 Upgrade to Premium</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-4">
                <p class="lead mb-4" style="color:var(--text-gray)">You've reached your daily message limit. Upgrade for unlimited messaging!</p>
                <div class="p-4 rounded-3 mb-4" style="background:rgba(66,103,245,0.1);border:2px solid var(--primary-blue)">
                    <h6 class="fw-bold mb-3" style="color:var(--text-white)">Premium Benefits</h6>
                    <ul class="list-unstyled text-start" style="color:var(--text-gray)">
                        <li class="mb-2"><i class="bi bi-check-circle-fill" style="color:var(--success-green)"></i> Unlimited messages</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill" style="color:var(--success-green)"></i> Featured listings</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill" style="color:var(--success-green)"></i> Priority support</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill" style="color:var(--success-green)"></i> Advanced search</li>
                    </ul>
                </div>
                <a href="membership.php" class="btn-primary" style="width:100%;display:block">Upgrade Now</a>
            </div>
        </div>
    </div>
</div>

<!-- Report Modal -->
<div class="modal fade" id="reportModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background:var(--card-bg);border:2px solid var(--border-color)">
            <div class="modal-header border-0">
                <h5 class="modal-title" style="color:var(--text-white)">🚨 Report User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form method="POST" action="report.php">
                    <input type="hidden" name="reported_type" value="user">
                    <input type="hidden" name="reported_id" value="<?php echo $profile_user_id; ?>">
                    
                    <div class="form-group mb-3">
                        <label style="color:var(--text-gray)">Reason</label>
                        <select name="reason" class="form-control" required style="background:rgba(26,31,46,0.5);border:2px solid var(--border-color);color:var(--text-white)">
                            <option value="">Select reason...</option>
                            <option value="spam">Spam or fake profile</option>
                            <option value="inappropriate">Inappropriate behavior</option>
                            <option value="scam">Scam or fraud</option>
                            <option value="harassment">Harassment</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label style="color:var(--text-gray)">Details (optional)</label>
                        <textarea name="description" class="form-control" rows="4" placeholder="Provide additional information..." style="background:rgba(26,31,46,0.5);border:2px solid var(--border-color);color:var(--text-white)"></textarea>
                    </div>
                    
                    <button type="submit" class="btn-danger" style="width:100%">Submit Report</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<?php include 'views/footer.php'; ?>