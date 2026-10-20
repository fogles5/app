<?php
session_start();
require_once 'config/database.php';
require_once 'classes/Listing.php';
require_once 'includes/profile_required.php';
require_once 'classes/CSRF.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Require complete profile
requireCompleteProfile($db, $_SESSION['user_id']);

$success = '';
$error = '';

// Get categories
$query = "SELECT * FROM categories ORDER BY name ASC";
$stmt = $db->query($query);
$categories = $stmt->fetchAll();

// Get cities
$query = "SELECT * FROM cities ORDER BY name ASC LIMIT 100";
$stmt = $db->query($query);
$cities = $stmt->fetchAll();

// Check daily limit
$query = "SELECT COUNT(*) FROM listings 
          WHERE user_id = :user_id 
          AND DATE(created_at) = CURDATE()";
$stmt = $db->prepare($query);
$stmt->execute(['user_id' => $_SESSION['user_id']]);
$today_count = $stmt->fetchColumn();

// Check if user is premium
$query = "SELECT is_premium FROM users WHERE id = :user_id";
$stmt = $db->prepare($query);
$stmt->execute(['user_id' => $_SESSION['user_id']]);
$is_premium = $stmt->fetchColumn();

$daily_limit = $is_premium ? 999 : 3;
$can_post = $today_count < $daily_limit;

if($_SERVER['REQUEST_METHOD'] === 'POST' && $can_post) {
    if(!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $category_id = (int)$_POST['category_id'];
        $city_id = (int)$_POST['city_id'];
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $contact_method = $_POST['contact_method'] ?? 'message';
        
        if(empty($title) || empty($description)) {
            $error = 'Please fill in all required fields';
        } elseif(strlen($title) < 10) {
            $error = 'Title must be at least 10 characters';
        } elseif(strlen($description) < 50) {
            $error = 'Description must be at least 50 characters';
        } else {
            try {
                $listing = new Listing($db);
                
                // Handle photo upload
                $photo_url = null;
                if(isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = 'uploads/listings/';
                    if(!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $file_ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                    $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
                    
                    if(in_array($file_ext, $allowed_exts)) {
                        $file_name = uniqid() . '_' . time() . '.' . $file_ext;
                        $file_path = $upload_dir . $file_name;
                        
                        if(move_uploaded_file($_FILES['photo']['tmp_name'], $file_path)) {
                            $photo_url = '/' . $file_path;
                        }
                    }
                }
                
                $result = $listing->create(
                    $_SESSION['user_id'],
                    $category_id,
                    $city_id,
                    $title,
                    $description,
                    $photo_url,
                    $contact_method
                );
                
                if($result) {
                    header('Location: my-listings.php?success=1');
                    exit();
                } else {
                    $error = 'Failed to create listing';
                }
            } catch(Exception $e) {
                $error = 'An error occurred: ' . $e->getMessage();
            }
        }
    }
}

include 'views/header.php';
?>

<style>
.post-container {
    max-width: 900px;
    margin: 2rem auto;
    padding: 0 1rem;
}

.post-header {
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

.post-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 300px;
    height: 300px;
    background: radial-gradient(circle, rgba(66, 103, 245, 0.15), transparent);
    border-radius: 50%;
}

.post-header h1 {
    position: relative;
    z-index: 1;
    font-size: 2rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
}

.post-subtitle {
    position: relative;
    z-index: 1;
    opacity: 0.9;
    font-size: 1rem;
}

.limit-info {
    background: rgba(66, 103, 245, 0.1);
    border: 2px solid rgba(66, 103, 245, 0.3);
    border-radius: 16px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.limit-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: linear-gradient(135deg, #4267F5, #1D9BF0);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

.post-form {
    background: var(--card-bg);
    backdrop-filter: blur(20px);
    border: 2px solid var(--border-color);
    border-radius: 24px;
    padding: 2.5rem;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
}

.form-section {
    margin-bottom: 2rem;
    padding-bottom: 2rem;
    border-bottom: 2px solid var(--border-color);
}

.form-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.section-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.section-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: linear-gradient(135deg, #4267F5, #1D9BF0);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    color: var(--text-white);
    margin-bottom: 0.75rem;
}

.required {
    color: var(--danger-red);
}

.form-control {
    width: 100%;
    padding: 1rem;
    border-radius: 12px;
    border: 2px solid var(--border-color);
    background: var(--background-dark);
    color: var(--text-white);
    font-size: 1rem;
    transition: all 0.3s;
}

.form-control:focus {
    outline: none;
    border-color: #4267F5;
    box-shadow: 0 0 0 4px rgba(66, 103, 245, 0.1);
}

textarea.form-control {
    min-height: 200px;
    resize: vertical;
    font-family: inherit;
    line-height: 1.6;
}

.form-help {
    font-size: 0.85rem;
    color: var(--text-gray);
    margin-top: 0.5rem;
}

.char-counter {
    text-align: right;
    font-size: 0.85rem;
    color: var(--text-gray);
    margin-top: 0.5rem;
}

.select-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1rem;
}

.select-option {
    position: relative;
}

.select-option input[type="radio"] {
    position: absolute;
    opacity: 0;
}

.select-label {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
    padding: 1.5rem;
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 16px;
    cursor: pointer;
    transition: all 0.3s;
    text-align: center;
}

.select-label:hover {
    border-color: #4267F5;
    transform: translateY(-2px);
}

.select-option input:checked + .select-label {
    border-color: #4267F5;
    background: rgba(66, 103, 245, 0.1);
}

.select-icon {
    font-size: 2rem;
}

.upload-area {
    border: 3px dashed var(--border-color);
    border-radius: 16px;
    padding: 3rem 2rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
    background: var(--background-dark);
}

.upload-area:hover {
    border-color: #4267F5;
    background: rgba(66, 103, 245, 0.05);
}

.upload-icon {
    font-size: 3rem;
    color: var(--text-gray);
    margin-bottom: 1rem;
}

.preview-image {
    max-width: 100%;
    max-height: 400px;
    border-radius: 16px;
    margin-top: 1rem;
}

.submit-section {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    margin-top: 2rem;
}

.btn-submit {
    padding: 1.25rem 3rem;
    border-radius: 16px;
    border: none;
    font-weight: 700;
    font-size: 1.1rem;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.btn-primary {
    background: linear-gradient(135deg, #4267F5, #1D9BF0);
    color: white;
}

.btn-primary:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 12px 40px rgba(66, 103, 245, 0.4);
}

.btn-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

@media (max-width: 768px) {
    .select-grid {
        grid-template-columns: 1fr;
    }
    
    .submit-section {
        flex-direction: column;
    }
    
    .btn-submit {
        width: 100%;
        justify-content: center;
    }
}
</style>

<div class="post-container">
    
    <!-- Header -->
    <div class="post-header">
        <h1><i class="bi bi-plus-circle-fill"></i> Create New Listing</h1>
        <p class="post-subtitle">Share your ad with the community</p>
    </div>

    <!-- Alerts -->
    <?php if($success): ?>
    <div class="alert alert-success">
        <i class="bi bi-check-circle-fill"></i>
        <?php echo htmlspecialchars($success); ?>
    </div>
    <?php endif; ?>
    
    <?php if($error): ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <!-- Limit Info -->
    <div class="limit-info">
        <div class="limit-icon">
            <i class="bi bi-clipboard-check"></i>
        </div>
        <div>
            <strong>Daily Posting Limit</strong>
            <p class="mb-0 text-muted">
                You've posted <?php echo $today_count; ?> of <?php echo $daily_limit; ?> listings today.
                <?php if(!$is_premium && $today_count >= 2): ?>
                <a href="/subscription-bitcoin.php" class="text-primary">Upgrade to Premium</a> for unlimited posts.
                <?php endif; ?>
            </p>
        </div>
    </div>

    <?php if(!$can_post): ?>
    <!-- Daily Limit Reached -->
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <strong>Daily limit reached!</strong> You can post again tomorrow or 
        <a href="/subscription-bitcoin.php" class="alert-link">upgrade to Premium</a> for unlimited posts.
    </div>
    <?php else: ?>

    <!-- Post Form -->
    <form method="POST" enctype="multipart/form-data" class="post-form" id="postForm">
        <?php echo CSRF::getHiddenInput(); ?>
        
        <!-- Category Selection -->
        <div class="form-section">
            <h3 class="section-title">
                <span class="section-icon"><i class="bi bi-grid-3x3-gap"></i></span>
                Select Category
            </h3>
            
            <div class="select-grid">
                <?php foreach($categories as $cat): ?>
                <div class="select-option">
                    <input type="radio" name="category_id" id="cat-<?php echo $cat['id']; ?>" value="<?php echo $cat['id']; ?>" required>
                    <label for="cat-<?php echo $cat['id']; ?>" class="select-label">
                        <span class="select-icon">
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
                        </span>
                        <span><?php echo htmlspecialchars($cat['name']); ?></span>
                    </label>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Location Selection -->
        <div class="form-section">
            <h3 class="section-title">
                <span class="section-icon"><i class="bi bi-geo-alt"></i></span>
                Choose Location
            </h3>
            
            <div class="form-group">
                <label>
                    <i class="bi bi-geo-alt-fill"></i>
                    City <span class="required">*</span>
                </label>
                <select name="city_id" class="form-control" required>
                    <option value="">Select a city...</option>
                    <?php foreach($cities as $city): ?>
                    <option value="<?php echo $city['id']; ?>" <?php echo (isset($_SESSION['current_city']) && $_SESSION['current_city'] === $city['name']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($city['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-help">
                    <i class="bi bi-info-circle"></i> Your listing will appear in this city
                </div>
            </div>
        </div>

        <!-- Listing Details -->
        <div class="form-section">
            <h3 class="section-title">
                <span class="section-icon"><i class="bi bi-file-text"></i></span>
                Listing Details
            </h3>
            
            <div class="form-group">
                <label>
                    <i class="bi bi-type"></i>
                    Title <span class="required">*</span>
                </label>
                <input type="text" 
                       name="title" 
                       class="form-control" 
                       placeholder="Enter a catchy title for your listing..."
                       maxlength="200"
                       required
                       id="titleInput">
                <div class="char-counter">
                    <span id="titleCount">0</span> / 200 characters
                </div>
            </div>

            <div class="form-group">
                <label>
                    <i class="bi bi-card-text"></i>
                    Description <span class="required">*</span>
                </label>
                <textarea name="description" 
                          class="form-control" 
                          placeholder="Describe what you're looking for in detail..."
                          required
                          id="descInput"></textarea>
                <div class="char-counter">
                    <span id="descCount">0</span> characters (min 50)
                </div>
            </div>
        </div>

        <!-- Photo Upload -->
        <div class="form-section">
            <h3 class="section-title">
                <span class="section-icon"><i class="bi bi-image"></i></span>
                Add Photo (Optional)
            </h3>
            
            <input type="file" name="photo" id="photoInput" accept="image/*" style="display: none;">
            <div class="upload-area" onclick="document.getElementById('photoInput').click()">
                <div class="upload-icon">
                    <i class="bi bi-cloud-upload"></i>
                </div>
                <p class="mb-0"><strong>Click to upload</strong> or drag and drop</p>
                <p class="text-muted mb-0">JPG, PNG or GIF (Max 5MB)</p>
            </div>
            <img id="imagePreview" class="preview-image" style="display: none;">
        </div>

        <!-- Contact Method -->
        <div class="form-section">
            <h3 class="section-title">
                <span class="section-icon"><i class="bi bi-chat-dots"></i></span>
                Contact Method
            </h3>
            
            <div class="select-grid" style="grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));">
                <div class="select-option">
                    <input type="radio" name="contact_method" id="method-message" value="message" checked>
                    <label for="method-message" class="select-label">
                        <i class="bi bi-chat-dots-fill" style="font-size: 2rem; color: #4267F5;"></i>
                        <strong>Site Messages</strong>
                        <small class="text-muted">Contact via internal messaging</small>
                    </label>
                </div>
                
                <div class="select-option">
                    <input type="radio" name="contact_method" id="method-both" value="both">
                    <label for="method-both" class="select-label">
                        <i class="bi bi-envelope-fill" style="font-size: 2rem; color: #10b981;"></i>
                        <strong>Email & Messages</strong>
                        <small class="text-muted">Allow email contact</small>
                    </label>
                </div>
            </div>
        </div>

        <!-- Submit -->
        <div class="submit-section">
            <button type="submit" class="btn-submit btn-primary" id="submitBtn">
                <i class="bi bi-send-fill"></i>
                Post Listing
            </button>
        </div>
    </form>

    <?php endif; ?>
</div>

<script>
// Character counters
const titleInput = document.getElementById('titleInput');
const descInput = document.getElementById('descInput');
const titleCount = document.getElementById('titleCount');
const descCount = document.getElementById('descCount');

if(titleInput && titleCount) {
    titleInput.addEventListener('input', () => {
        titleCount.textContent = titleInput.value.length;
    });
}

if(descInput && descCount) {
    descInput.addEventListener('input', () => {
        const count = descInput.value.length;
        descCount.textContent = count;
        descCount.style.color = count >= 50 ? 'var(--success-green)' : 'var(--danger-red)';
    });
}

// Image preview
document.getElementById('photoInput').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if(file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('imagePreview');
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(file);
    }
});

// Form submission
document.getElementById('postForm').addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Posting...';
});
</script>

<?php include 'views/footer.php'; ?>