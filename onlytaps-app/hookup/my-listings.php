<?php
session_start();
require_once 'config/database.php';
require_once 'classes/Listing.php';
require_once 'classes/CSRF.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$listing = new Listing($db);

$success = '';
$error = '';

// Handle delete action
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if(!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $listing_id = (int)$_POST['listing_id'];
        if($listing->delete($listing_id, $_SESSION['user_id'])) {
            $success = 'Listing deleted successfully!';
            CSRF::regenerateToken();
        } else {
            $error = 'Failed to delete listing';
        }
    }
}

// Get user's listings
$my_listings = $listing->getUserListings($_SESSION['user_id']);

// Get stats
$query = "SELECT 
          COUNT(*) as total_listings,
          SUM(views) as total_views
          FROM listings 
          WHERE user_id = :user_id";
          
$stmt = $db->prepare($query);
$stmt->execute(['user_id' => $_SESSION['user_id']]);
$stats = $stmt->fetch();

include 'views/header.php';
?>

<style>
.listings-container {
    max-width: 1400px;
    margin: 2rem auto;
    padding: 0 1rem;
}

.listings-header {
    background: linear-gradient(135deg, rgba(15, 23, 42, 0.95), rgba(30, 41, 59, 0.95));
    backdrop-filter: blur(20px);
    border: 2px solid rgba(66, 103, 245, 0.2);
    color: white;
    padding: 2.5rem 2rem;
    border-radius: 24px;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
}

.listings-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 300px;
    height: 300px;
    background: radial-gradient(circle, rgba(66, 103, 245, 0.15), transparent);
    border-radius: 50%;
}

.header-content {
    position: relative;
    z-index: 1;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 2rem;
}

.header-title h1 {
    font-size: 2rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
}

.header-subtitle {
    opacity: 0.9;
}

.stats-row {
    display: flex;
    gap: 2rem;
    margin-top: 1.5rem;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.stat-value {
    font-size: 1.75rem;
    font-weight: 700;
}

.stat-label {
    font-size: 0.9rem;
    opacity: 0.8;
}

.create-btn {
    padding: 1rem 2rem;
    border-radius: 16px;
    background: linear-gradient(135deg, #4267F5, #1D9BF0);
    color: white;
    border: none;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.create-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 40px rgba(66, 103, 245, 0.5);
    color: white;
}

.listings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
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
    height: 220px;
    object-fit: cover;
    background: linear-gradient(135deg, #4267F5, #1D9BF0);
}

.listing-badge {
    position: absolute;
    top: 1rem;
    left: 1rem;
    padding: 0.5rem 1rem;
    border-radius: 25px;
    font-size: 0.85rem;
    font-weight: 700;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.badge-featured {
    background: linear-gradient(135deg, #fbbf24, #f59e0b);
    color: white;
}

.badge-active {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
}

.badge-expired {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
}

.listing-content {
    padding: 1.5rem;
}

.listing-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 1rem;
}

.listing-title {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--text-white);
    margin: 0 0 0.5rem;
    line-height: 1.3;
}

.listing-category {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    background: rgba(66, 103, 245, 0.1);
    border: 1px solid rgba(66, 103, 245, 0.3);
    border-radius: 12px;
    font-size: 0.85rem;
    color: #4267F5;
}

.listing-meta {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
    padding: 1rem 0;
    border-top: 2px solid var(--border-color);
    border-bottom: 2px solid var(--border-color);
    margin: 1rem 0;
}

.meta-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
}

.meta-value {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-white);
}

.meta-label {
    font-size: 0.8rem;
    color: var(--text-gray);
}

.listing-actions {
    display: flex;
    gap: 0.75rem;
}

.action-btn {
    flex: 1;
    padding: 0.75rem;
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

.btn-view {
    background: linear-gradient(135deg, #4267F5, #1D9BF0);
    color: white;
}

.btn-view:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(66, 103, 245, 0.4);
    color: white;
}

.btn-edit {
    background: rgba(16, 185, 129, 0.1);
    border: 2px solid rgba(16, 185, 129, 0.3);
    color: var(--success-green);
}

.btn-edit:hover {
    background: rgba(16, 185, 129, 0.2);
    border-color: var(--success-green);
    color: var(--success-green);
}

.btn-delete {
    background: rgba(239, 68, 68, 0.1);
    border: 2px solid rgba(239, 68, 68, 0.3);
    color: var(--danger-red);
}

.btn-delete:hover {
    background: rgba(239, 68, 68, 0.2);
    border-color: var(--danger-red);
}

.empty-state {
    text-align: center;
    padding: 6rem 2rem;
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 24px;
}

.empty-icon {
    font-size: 5rem;
    margin-bottom: 1.5rem;
    opacity: 0.3;
}

.filter-bar {
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 16px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.filter-chips {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.filter-chip {
    padding: 0.5rem 1.25rem;
    border-radius: 25px;
    border: 2px solid var(--border-color);
    background: transparent;
    color: var(--text-gray);
    cursor: pointer;
    transition: all 0.3s;
    font-weight: 600;
}

.filter-chip:hover,
.filter-chip.active {
    border-color: #4267F5;
    background: rgba(66, 103, 245, 0.1);
    color: #4267F5;
}

@media (max-width: 768px) {
    .listings-grid {
        grid-template-columns: 1fr;
    }
    
    .header-content {
        flex-direction: column;
        align-items: stretch;
    }
    
    .stats-row {
        flex-direction: column;
        gap: 1rem;
    }
    
    .create-btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<div class="listings-container">
    
    <!-- Header -->
    <div class="listings-header">
        <div class="header-content">
            <div class="header-title">
                <h1><i class="bi bi-file-text-fill"></i> My Listings</h1>
                <p class="header-subtitle">Manage your posts and track performance</p>
                
                <div class="stats-row">
                    <div class="stat-item">
                        <div class="stat-icon">
                            <i class="bi bi-file-text"></i>
                        </div>
                        <div>
                            <div class="stat-value"><?php echo number_format($stats['total_listings'] ?? 0); ?></div>
                            <div class="stat-label">Total Listings</div>
                        </div>
                    </div>
                    
                    <div class="stat-item">
                        <div class="stat-icon">
                            <i class="bi bi-eye-fill"></i>
                        </div>
                        <div>
                            <div class="stat-value"><?php echo number_format($stats['total_views'] ?? 0); ?></div>
                            <div class="stat-label">Total Views</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <a href="/post-ad.php" class="create-btn">
                <i class="bi bi-plus-circle-fill"></i>
                Create New Listing
            </a>
        </div>
    </div>

    <!-- Alerts -->
    <?php if($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>
        <?php echo htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <?php if($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if(empty($my_listings)): ?>
    <!-- Empty State -->
    <div class="empty-state">
        <div class="empty-icon">📝</div>
        <h2>No Listings Yet</h2>
        <p class="text-muted mb-4">You haven't created any listings yet. Start by posting your first ad!</p>
        <a href="/post-ad.php" class="btn btn-primary btn-lg">
            <i class="bi bi-plus-circle-fill"></i> Create First Listing
        </a>
    </div>
    <?php else: ?>

    <!-- Filter Bar -->
    <div class="filter-bar">
        <div class="filter-chips">
            <button class="filter-chip active" onclick="filterListings('all')">
                All (<?php echo count($my_listings); ?>)
            </button>
            <button class="filter-chip" onclick="filterListings('active')">
                Active
            </button>
            <button class="filter-chip" onclick="filterListings('featured')">
                Featured
            </button>
        </div>
        
        <div class="dropdown">
            <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <i class="bi bi-funnel"></i> Sort
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="#" onclick="sortListings('newest')">Newest First</a></li>
                <li><a class="dropdown-item" href="#" onclick="sortListings('oldest')">Oldest First</a></li>
                <li><a class="dropdown-item" href="#" onclick="sortListings('views')">Most Views</a></li>
            </ul>
        </div>
    </div>

    <!-- Listings Grid -->
    <div class="listings-grid">
        <?php foreach($my_listings as $item): ?>
        <div class="listing-card" data-status="<?php echo $item['status'] ?? 'active'; ?>" data-featured="<?php echo $item['is_featured'] ?? 0; ?>">
            
            <!-- Badge -->
            <?php if($item['is_featured'] ?? false): ?>
            <div class="listing-badge badge-featured">
                <i class="bi bi-star-fill"></i> Featured
            </div>
            <?php else: ?>
            <div class="listing-badge badge-active">
                <i class="bi bi-check-circle-fill"></i> Active
            </div>
            <?php endif; ?>
            
            <!-- Image -->
            <?php if($item['photo_url']): ?>
            <img src="<?php echo htmlspecialchars($item['photo_url']); ?>" 
                 alt="<?php echo htmlspecialchars($item['title']); ?>"
                 class="listing-image">
            <?php else: ?>
            <div class="listing-image d-flex align-items-center justify-content-center">
                <i class="bi bi-image" style="font-size: 4rem; color: white; opacity: 0.5;"></i>
            </div>
            <?php endif; ?>
            
            <!-- Content -->
            <div class="listing-content">
                <div class="listing-header">
                    <div style="flex: 1;">
                        <h3 class="listing-title"><?php echo htmlspecialchars($item['title']); ?></h3>
                        <span class="listing-category">
                            <i class="bi bi-folder"></i>
                            <?php echo htmlspecialchars($item['category_name']); ?>
                        </span>
                    </div>
                </div>
                
                <!-- Meta Stats -->
                <div class="listing-meta">
                    <div class="meta-item">
                        <div class="meta-value">
                            <i class="bi bi-eye-fill"></i>
                            <?php echo number_format($item['views'] ?? 0); ?>
                        </div>
                        <div class="meta-label">Views</div>
                    </div>
                    
                    <div class="meta-item">
                        <div class="meta-value">
                            <i class="bi bi-geo-alt-fill"></i>
                        </div>
                        <div class="meta-label"><?php echo htmlspecialchars($item['city_name']); ?></div>
                    </div>
                    
                    <div class="meta-item">
                        <div class="meta-value">
                            <i class="bi bi-calendar"></i>
                        </div>
                        <div class="meta-label">
                            <?php 
                            $days_ago = floor((time() - strtotime($item['created_at'])) / 86400);
                            echo $days_ago . 'd ago';
                            ?>
                        </div>
                    </div>
                </div>
                
                <!-- Actions -->
                <div class="listing-actions">
                    <a href="/listing.php?id=<?php echo $item['id']; ?>" class="action-btn btn-view" target="_blank">
                        <i class="bi bi-eye"></i> View
                    </a>
                    
                    <a href="/edit-listing.php?id=<?php echo $item['id']; ?>" class="action-btn btn-edit">
                        <i class="bi bi-pencil"></i> Edit
                    </a>
                    
                    <button class="action-btn btn-delete" onclick="confirmDelete(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars(addslashes($item['title'])); ?>')">
                        <i class="bi bi-trash"></i> Delete
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="bi bi-exclamation-triangle-fill"></i> Confirm Delete
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">Are you sure you want to delete this listing?</p>
                <div class="alert alert-warning">
                    <strong id="deleteTitle"></strong>
                </div>
                <p class="text-muted mb-0">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" style="display: inline;">
                    <?php echo CSRF::getHiddenInput(); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="listing_id" id="deleteListingId">
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash-fill"></i> Delete Listing
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(listingId, title) {
    document.getElementById('deleteListingId').value = listingId;
    document.getElementById('deleteTitle').textContent = title;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

function filterListings(filter) {
    const cards = document.querySelectorAll('.listing-card');
    const chips = document.querySelectorAll('.filter-chip');
    
    // Update active chip
    chips.forEach(chip => {
        chip.classList.remove('active');
        if(chip.textContent.toLowerCase().includes(filter)) {
            chip.classList.add('active');
        }
    });
    
    // Filter cards
    cards.forEach(card => {
        if(filter === 'all') {
            card.style.display = 'block';
        } else if(filter === 'featured') {
            card.style.display = card.dataset.featured === '1' ? 'block' : 'none';
        } else if(filter === 'active') {
            card.style.display = card.dataset.status === 'active' ? 'block' : 'none';
        }
    });
}

function sortListings(sort) {
    // Implement sorting logic here
    console.log('Sort by:', sort);
}

// Auto-dismiss alerts after 5 seconds
setTimeout(() => {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);
</script>

<?php include 'views/footer.php'; ?>