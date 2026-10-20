<?php
// Get unread messages count if user is logged in
$unread_messages = 0;
$unread_notifications = 0;
$incognito_active = false;
$is_admin = false;
$is_moderator = false;

if(isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../classes/Message.php';
    require_once __DIR__ . '/../classes/SmartNotifications.php';
    require_once __DIR__ . '/../classes/IncognitoMode.php';
    require_once __DIR__ . '/../classes/Moderator.php';
    
    $db_header = new Database();
    $conn_header = $db_header->getConnection();
    
    // Get message count
    $msg_header = new Message($conn_header);
    $unread_messages = $msg_header->getTotalUnreadCount($_SESSION['user_id']);
    
    // Get notification count
    $notif_header = new SmartNotifications($conn_header);
    $unread_notifications = $notif_header->getUnreadCount($_SESSION['user_id']);
    
    // Check incognito status
    $incognito_header = new IncognitoMode($conn_header);
    $incognito_active = $incognito_header->isActive($_SESSION['user_id']);
    
    // Check admin status
    $admin_check = $conn_header->prepare("SELECT is_admin FROM users WHERE id = :id LIMIT 1");
    $admin_check->bindParam(':id', $_SESSION['user_id'], PDO::PARAM_INT);
    $admin_check->execute();
    $admin_result = $admin_check->fetch(PDO::FETCH_ASSOC);
    $is_admin = $admin_result && $admin_result['is_admin'] == 1;
    
    // Check moderator status
    $mod_check = new Moderator($conn_header);
    $is_moderator = $mod_check->isModerator($_SESSION['user_id']);
}

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="description" content="Turnpage - Local hookup classifieds and marketplace. Post ads, buy & sell, and connect in your area.">
    <meta name="theme-color" content="#0b0a14">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Turnpage">
    <title>Turnpage - Local Hookup Classifieds & Marketplace</title>
    <link rel="stylesheet" href="/assets/css/dark-blue-theme.css">
    <link rel="stylesheet" href="/assets/css/bottom-nav.css">
    <link rel="stylesheet" href="/assets/css/app.css?v=1">
    <meta name="format-detection" content="telephone=no">
    <link rel="icon" type="image/png" href="/assets/images/favicon.png">
    <link rel="apple-touch-icon" href="/assets/images/apple-touch-icon.png">
    <style>
        .navbar-enhanced {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            border-bottom: 2px solid rgba(66, 103, 245, 0.3);
            padding: 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
        }
        
        .nav-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 70px;
        }
        
        .nav-brand {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.5rem;
            font-weight: bold;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .nav-brand:hover {
            transform: scale(1.05);
        }
        
        .nav-brand .brand-icon {
            font-size: 2rem;
            filter: drop-shadow(0 2px 4px rgba(66, 103, 245, 0.5));
        }
        
        .nav-center {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        
        .nav-link {
            padding: 0.75rem 1.25rem;
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s;
            font-weight: 500;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            position: relative;
        }
        
        .nav-link:hover {
            background: rgba(66, 103, 245, 0.2);
            color: white;
            transform: translateY(-2px);
        }
        
        .nav-link.active {
            background: rgba(66, 103, 245, 0.3);
            color: white;
            border: 1px solid rgba(66, 103, 245, 0.5);
        }
        
        .nav-link.admin-link {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.3), rgba(220, 38, 38, 0.3));
            border: 1px solid rgba(239, 68, 68, 0.5);
            animation: pulse-admin 2s infinite;
        }
        
        @keyframes pulse-admin {
            0%, 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
            50% { box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
        }
        
        .nav-link.marketplace-link {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.3), rgba(217, 119, 6, 0.3));
            border: 1px solid rgba(245, 158, 11, 0.5);
        }
        
        .badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            border-radius: 10px;
            padding: 2px 6px;
            font-size: 0.7rem;
            font-weight: bold;
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.5);
        }
        
        .dropdown {
            position: relative;
        }
        
        .dropdown-toggle {
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .dropdown-toggle::after {
            content: '▼';
            font-size: 0.7rem;
            transition: transform 0.3s;
        }
        
        .dropdown.active .dropdown-toggle::after {
            transform: rotate(180deg);
        }
        
        .dropdown-menu {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            background: #1a1a2e;
            border: 2px solid rgba(66, 103, 245, 0.3);
            border-radius: 12px;
            min-width: 220px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s;
            z-index: 1001;
        }
        
        .dropdown.active .dropdown-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .dropdown-item {
            padding: 0.875rem 1.25rem;
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transition: all 0.3s;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 0.95rem;
        }
        
        .dropdown-item:last-child {
            border-bottom: none;
        }
        
        .dropdown-item:hover {
            background: rgba(66, 103, 245, 0.2);
            color: white;
            padding-left: 1.5rem;
        }
        
        .dropdown-divider {
            height: 2px;
            background: rgba(66, 103, 245, 0.3);
            margin: 0.5rem 0;
        }
        
        .mobile-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.5rem;
        }
        
        @media (max-width: 768px) {
            .nav-center {
                position: fixed;
                top: 70px;
                left: 0;
                right: 0;
                background: #1a1a2e;
                flex-direction: column;
                padding: 1rem;
                gap: 0.5rem;
                transform: translateX(-100%);
                transition: transform 0.3s;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
                max-height: calc(100vh - 70px);
                overflow-y: auto;
            }
            
            .nav-center.active {
                transform: translateX(0);
            }
            
            .mobile-toggle {
                display: block;
            }
            
            .nav-link {
                width: 100%;
                justify-content: flex-start;
            }
            
            .dropdown-menu {
                position: relative;
                top: 0;
                box-shadow: none;
                border: none;
                border-left: 2px solid rgba(66, 103, 245, 0.5);
                margin-left: 1rem;
                border-radius: 0;
            }
        }
    </style>
</head>
<body class="<?php echo isset($_SESSION['user_id']) ? 'has-bottom-nav' : ''; ?>">
    <nav class="navbar-enhanced">
        <div class="nav-container">
            <a href="<?php echo isset($_SESSION['current_city']) ? '/city.php?location=' . $_SESSION['current_city'] : '/choose-location.php'; ?>" class="nav-brand">
                <span class="brand-icon">📋</span>
                <span>Turnpage</span>
            </a>
            
            <button class="mobile-toggle" id="mobileToggle" aria-label="Toggle menu">
                ☰
            </button>
            
            <div class="nav-center" id="navCenter">
                <a href="/browse.php" class="nav-link <?php echo $current_page == 'browse' ? 'active' : ''; ?>">
                    🔍 Browse
                </a>
                
                <a href="/marketplace.php" class="nav-link marketplace-link <?php echo $current_page == 'marketplace' ? 'active' : ''; ?>">
                    🛍️ Marketplace
                </a>
                
                <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="/create-listing.php" class="nav-link <?php echo $current_page == 'create-listing' ? 'active' : ''; ?>">
                        ➕ Post Ad
                    </a>
                    
                    <a href="/messages.php" class="nav-link <?php echo $current_page == 'messages' ? 'active' : ''; ?>">
                        💬 Messages
                        <span class="badge" data-unread-badge <?php echo $unread_messages > 0 ? '' : 'hidden'; ?>><?php echo (int)$unread_messages; ?></span>
                    </a>
                    
                    <?php if($is_admin): ?>
                    <a href="/admin/dashboard.php" class="nav-link admin-link">
                        🛡️ Admin
                    </a>
                    <?php elseif($is_moderator): ?>
                    <a href="/moderator/dashboard.php" class="nav-link" style="background: rgba(245, 158, 11, 0.2); border: 1px solid rgba(245, 158, 11, 0.5);">
                        🛡️ Moderator
                    </a>
                    <?php endif; ?>
                    
                    <div class="dropdown" id="userDropdown">
                        <div class="nav-link dropdown-toggle">
                            👤 <?php echo htmlspecialchars($_SESSION['username'] ?? 'Account'); ?>
                        </div>
                        <div class="dropdown-menu">
                            <a href="/profile.php?id=<?php echo $_SESSION['user_id']; ?>" class="dropdown-item">
                                👤 My Profile
                            </a>
                            <a href="/my-listings.php" class="dropdown-item">
                                📝 My Ads
                            </a>
                            <a href="/favorites.php" class="dropdown-item">
                                ⭐ Favorites
                            </a>
                            <a href="/notifications.php" class="dropdown-item">
                                🔔 Notifications
                                <?php if($unread_notifications > 0): ?>
                                    <span class="badge" style="position: static; margin-left: auto;"><?php echo $unread_notifications; ?></span>
                                <?php endif; ?>
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="/membership.php" class="dropdown-item">
                                💎 Premium
                            </a>
                            <a href="/settings.php" class="dropdown-item">
                                ⚙️ Settings
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="/logout.php" class="dropdown-item" style="color: #ef4444;">
                                🚪 Logout
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="/membership.php" class="nav-link <?php echo $current_page == 'membership' ? 'active' : ''; ?>">
                        💎 Premium
                    </a>
                    <a href="/login.php" class="nav-link <?php echo $current_page == 'login' ? 'active' : ''; ?>">
                        🔐 Login
                    </a>
                    <a href="/register.php" class="nav-link" style="background: linear-gradient(135deg, #7c3aed, #d946ef); border: 1px solid rgba(167, 139, 250, 0.5);">
                        ✨ Sign Up
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    
    <?php if($incognito_active && isset($_SESSION['user_id'])): ?>
    <div style="background: linear-gradient(135deg, rgba(107, 114, 128, 0.3), rgba(75, 85, 99, 0.3)); padding: 0.75rem; text-align: center; border-bottom: 1px solid rgba(107, 114, 128, 0.5); color: white; font-size: 0.9rem;">
        🕶️ <strong>Incognito Mode Active</strong> - Your profile is hidden from search
    </div>
    <?php endif; ?>
    
    <?php if(isset($_SESSION['user_id'])): ?>
    <!-- Bottom Navigation Bar (Mobile Only) -->
    <nav class="bottom-nav">
        <div class="bottom-nav-container">
            <a href="<?php echo isset($_SESSION['current_city']) ? '/city.php?location=' . $_SESSION['current_city'] : '/choose-location.php'; ?>" 
               class="bottom-nav-item <?php echo in_array($current_page, ['index', 'city', 'choose-location']) ? 'active' : ''; ?>">
                <div class="bottom-nav-icon">🏠</div>
                <span class="bottom-nav-label">Home</span>
            </a>
            
            <a href="/marketplace.php" class="bottom-nav-item <?php echo $current_page == 'marketplace' ? 'active' : ''; ?>">
                <div class="bottom-nav-icon">🛍️</div>
                <span class="bottom-nav-label">Market</span>
            </a>
            
            <a href="/post-ad.php" class="bottom-nav-item <?php echo $current_page == 'post-ad' ? 'active' : ''; ?>">
                <div class="bottom-nav-icon">➕</div>
                <span class="bottom-nav-label">Post</span>
            </a>
            
            <a href="/messages.php" class="bottom-nav-item <?php echo $current_page == 'messages' ? 'active' : ''; ?>">
                <div class="bottom-nav-icon">💬</div>
                <span class="bottom-nav-label">Messages</span>
                <span class="bottom-nav-badge" data-unread-badge <?php echo $unread_messages > 0 ? '' : 'hidden'; ?>><?php echo (int)$unread_messages; ?></span>
            </a>
            
            <a href="/profile.php?id=<?php echo $_SESSION['user_id']; ?>" class="bottom-nav-item <?php echo $current_page == 'profile' ? 'active' : ''; ?>">
                <div class="bottom-nav-icon">👤</div>
                <span class="bottom-nav-label">Profile</span>
            </a>
        </div>
    </nav>
    <?php endif; ?>
    
    <main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Mobile menu toggle
    const mobileToggle = document.getElementById('mobileToggle');
    const navCenter = document.getElementById('navCenter');
    
    if(mobileToggle && navCenter) {
        mobileToggle.addEventListener('click', function() {
            navCenter.classList.toggle('active');
            this.textContent = navCenter.classList.contains('active') ? '✕' : '☰';
        });
    }
    
    // Dropdown toggle
    const dropdown = document.getElementById('userDropdown');
    if(dropdown) {
        const toggle = dropdown.querySelector('.dropdown-toggle');
        toggle.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdown.classList.toggle('active');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if(!dropdown.contains(e.target)) {
                dropdown.classList.remove('active');
            }
        });
    }
});
</script>