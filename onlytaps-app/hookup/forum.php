<?php
session_start();
require_once 'config/database.php';
require_once 'classes/Forum.php';

$database = new Database();
$db = $database->getConnection();
$forum = new Forum($db);

$categories = $forum->getCategories();

// Get recent threads across all categories
$query = "SELECT t.*, u.username, c.name as category_name, c.slug as category_slug, c.color
          FROM forum_threads t
          LEFT JOIN users u ON t.user_id = u.id
          LEFT JOIN forum_categories c ON t.category_id = c.id
          WHERE t.is_deleted = FALSE
          ORDER BY t.created_at DESC
          LIMIT 10";
$stmt = $db->query($query);
$recent_threads = $stmt->fetchAll();

// Get forum stats
$query = "SELECT 
          (SELECT COUNT(*) FROM forum_threads WHERE is_deleted = FALSE) as total_threads,
          (SELECT COUNT(*) FROM forum_posts WHERE is_deleted = FALSE) as total_posts,
          (SELECT COUNT(DISTINCT user_id) FROM forum_threads) as total_members";
$stmt = $db->query($query);
$stats = $stmt->fetch();

include 'views/header.php';
?>

<style>
.forum-container {
    max-width: 1400px;
    margin: 2rem auto;
    padding: 0 1rem;
}

.forum-header {
    background: linear-gradient(135deg, #4267F5, #1D9BF0);
    color: white;
    padding: 3rem 2rem;
    border-radius: 20px;
    margin-bottom: 2rem;
    text-align: center;
}

.forum-header h1 {
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
}

.forum-search {
    max-width: 600px;
    margin: 2rem auto 0;
    position: relative;
}

.forum-search input {
    width: 100%;
    padding: 1rem 3rem 1rem 1.5rem;
    border-radius: 50px;
    border: none;
    font-size: 1rem;
}

.forum-search button {
    position: absolute;
    right: 0.5rem;
    top: 50%;
    transform: translateY(-50%);
    background: linear-gradient(135deg, #4267F5, #1D9BF0);
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 50px;
    color: white;
    cursor: pointer;
}

.forum-stats {
    display: flex;
    justify-content: center;
    gap: 3rem;
    margin-top: 2rem;
    flex-wrap: wrap;
}

.stat-item {
    text-align: center;
}

.stat-value {
    font-size: 2.5rem;
    font-weight: 700;
}

.stat-label {
    opacity: 0.9;
    font-size: 0.9rem;
}

.forum-layout {
    display: grid;
    grid-template-columns: 1fr 350px;
    gap: 2rem;
}

.categories-section {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.category-card {
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-left: 4px solid;
    border-radius: 15px;
    padding: 1.5rem;
    transition: all 0.3s;
    cursor: pointer;
}

.category-card:hover {
    transform: translateX(5px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
}

.category-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}

.category-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

.category-info {
    flex: 1;
}

.category-name {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 0.25rem;
}

.category-description {
    font-size: 0.9rem;
    color: var(--text-gray);
}

.category-stats {
    display: flex;
    gap: 2rem;
    font-size: 0.9rem;
    color: var(--text-gray);
}

.category-last-post {
    border-top: 1px solid var(--border-color);
    padding-top: 1rem;
    margin-top: 1rem;
    font-size: 0.85rem;
    color: var(--text-gray);
}

.sidebar {
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
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.recent-thread {
    padding: 1rem 0;
    border-bottom: 1px solid var(--border-color);
}

.recent-thread:last-child {
    border-bottom: none;
}

.recent-thread-title {
    font-weight: 600;
    color: var(--text-white);
    margin-bottom: 0.25rem;
    display: block;
    text-decoration: none;
}

.recent-thread-title:hover {
    color: var(--primary-blue);
}

.recent-thread-meta {
    font-size: 0.8rem;
    color: var(--text-gray);
}

.create-thread-btn {
    width: 100%;
    padding: 1rem;
    background: linear-gradient(135deg, #4267F5, #1D9BF0);
    color: white;
    border: none;
    border-radius: 12px;
    font-weight: 700;
    font-size: 1.1rem;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    text-decoration: none;
}

.create-thread-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(66, 103, 245, 0.4);
}

@media (max-width: 1024px) {
    .forum-layout {
        grid-template-columns: 1fr;
    }
    
    .sidebar {
        position: static;
    }
}

@media (max-width: 768px) {
    .forum-header h1 {
        font-size: 1.8rem;
    }
    
    .forum-stats {
        gap: 1.5rem;
    }
    
    .stat-value {
        font-size: 1.8rem;
    }
}
</style>

<div class="forum-container">
    
    <!-- Forum Header -->
    <div class="forum-header">
        <h1><i class="bi bi-chat-square-text"></i> Community Forum</h1>
        <p style="font-size: 1.1rem; opacity: 0.95;">Connect, discuss, and share with the Turnpage community</p>
        
        <!-- Search -->
        <div class="forum-search">
            <form action="/forum-search.php" method="GET">
                <input type="text" name="q" placeholder="Search discussions..." required>
                <button type="submit">
                    <i class="bi bi-search"></i>
                </button>
            </form>
        </div>
        
        <!-- Stats -->
        <div class="forum-stats">
            <div class="stat-item">
                <div class="stat-value"><?php echo number_format($stats['total_threads']); ?></div>
                <div class="stat-label">Threads</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?php echo number_format($stats['total_posts']); ?></div>
                <div class="stat-label">Posts</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?php echo number_format($stats['total_members']); ?></div>
                <div class="stat-label">Members</div>
            </div>
        </div>
    </div>

    <div class="forum-layout">
        
        <!-- Categories -->
        <div class="categories-section">
            <?php foreach($categories as $category): ?>
            <div class="category-card" 
                 style="border-left-color: <?php echo $category['color']; ?>"
                 onclick="location.href='/forum-category.php?slug=<?php echo $category['slug']; ?>'">
                
                <div class="category-header">
                    <div class="category-icon" style="background: <?php echo $category['color']; ?>">
                        <i class="bi bi-<?php echo $category['icon']; ?>"></i>
                    </div>
                    <div class="category-info">
                        <div class="category-name"><?php echo htmlspecialchars($category['name']); ?></div>
                        <div class="category-description"><?php echo htmlspecialchars($category['description']); ?></div>
                    </div>
                    <?php if($category['is_locked']): ?>
                    <span class="badge bg-danger"><i class="bi bi-lock-fill"></i> Locked</span>
                    <?php endif; ?>
                </div>
                
                <div class="category-stats">
                    <span>
                        <i class="bi bi-file-text"></i> 
                        <?php echo number_format($category['threads_count']); ?> Threads
                    </span>
                    <span>
                        <i class="bi bi-chat-dots"></i> 
                        <?php echo number_format($category['posts_count']); ?> Posts
                    </span>
                </div>
                
                <?php if($category['last_post_username']): ?>
                <div class="category-last-post">
                    <i class="bi bi-clock"></i> Last post by 
                    <strong><?php echo htmlspecialchars($category['last_post_username']); ?></strong>
                    in <em><?php echo htmlspecialchars($category['last_thread_title']); ?></em>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Sidebar -->
        <div class="sidebar">
            
            <!-- Create Thread Button -->
            <?php if(isset($_SESSION['user_id'])): ?>
            <a href="/forum-create-thread.php" class="create-thread-btn">
                <i class="bi bi-plus-circle-fill"></i>
                Create New Thread
            </a>
            <?php else: ?>
            <a href="/login.php" class="create-thread-btn">
                <i class="bi bi-box-arrow-in-right"></i>
                Login to Post
            </a>
            <?php endif; ?>
            
            <!-- Recent Threads -->
            <div class="sidebar-card">
                <div class="sidebar-title">
                    <i class="bi bi-fire"></i>
                    Recent Threads
                </div>
                <?php foreach($recent_threads as $thread): ?>
                <div class="recent-thread">
                    <a href="/forum-thread.php?slug=<?php echo $thread['slug']; ?>" class="recent-thread-title">
                        <?php echo htmlspecialchars($thread['title']); ?>
                    </a>
                    <div class="recent-thread-meta">
                        <span style="color: <?php echo $thread['color']; ?>">
                            <?php echo htmlspecialchars($thread['category_name']); ?>
                        </span>
                        • by <?php echo htmlspecialchars($thread['username']); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Forum Guidelines -->
            <div class="sidebar-card">
                <div class="sidebar-title">
                    <i class="bi bi-shield-check"></i>
                    Forum Guidelines
                </div>
                <ul class="list-unstyled" style="font-size: 0.9rem; color: var(--text-gray); line-height: 1.8;">
                    <li><i class="bi bi-check-circle text-success"></i> Be respectful and courteous</li>
                    <li><i class="bi bi-check-circle text-success"></i> No spam or self-promotion</li>
                    <li><i class="bi bi-check-circle text-success"></i> Stay on topic</li>
                    <li><i class="bi bi-check-circle text-success"></i> Report inappropriate content</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include 'views/footer.php'; ?>