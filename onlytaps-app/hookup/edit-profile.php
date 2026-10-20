<?php
session_start();
require_once 'config/database.php';
require_once 'classes/UserProfile.php';
require_once 'classes/Location.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$userProfile = new UserProfile($db);
$location = new Location($db);

$profile = $userProfile->getProfile($_SESSION['user_id']);
$userLocation = $userProfile->getUserLocation($_SESSION['user_id']);
$states = $location->getAllStates();

$error = '';
$success = '';

// Handle avatar upload/removal
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_avatar'])) {
    if (isset($_POST['remove_avatar'])) {
        $userProfile->removeAvatar($_SESSION['user_id']);
        $success = 'Profile photo removed.';
    } elseif (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == UPLOAD_ERR_OK) {
        $result = $userProfile->uploadAvatar($_SESSION['user_id'], $_FILES['avatar']);
        if($result === true) {
            $success = 'Profile photo updated!';
        } else {
            $error = $result;
        }
    }
    $profile = $userProfile->getProfile($_SESSION['user_id']);
}

if($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['change_avatar'])) {
    $data = [
        'bio' => $_POST['bio'] ?? '',
        'height' => !empty($_POST['height']) ? $_POST['height'] : null,
        'body_type' => $_POST['body_type'] ?? null,
        'ethnicity' => $_POST['ethnicity'] ?? '',
        'relationship_status' => $_POST['relationship_status'] ?? null,
        'looking_for' => isset($_POST['looking_for']) ? $_POST['looking_for'] : [],
        'interests' => isset($_POST['interests']) ? explode(',', $_POST['interests']) : [],
        'occupation' => $_POST['occupation'] ?? '',
        'education' => $_POST['education'] ?? null,
        'smoking' => $_POST['smoking'] ?? null,
        'drinking' => $_POST['drinking'] ?? null,
        'has_kids' => isset($_POST['has_kids']) ? (bool)$_POST['has_kids'] : false,
        'wants_kids' => $_POST['wants_kids'] ?? null,
        'languages' => isset($_POST['languages']) ? explode(',', $_POST['languages']) : [],
        'display_distance' => isset($_POST['display_distance']) ? true : false,
        'show_age' => isset($_POST['show_age']) ? true : false,
        'show_online_status' => isset($_POST['show_online_status']) ? true : false
    ];
    
    if($userProfile->saveProfile($_SESSION['user_id'], $data)) {
        if(!empty($_POST['city_id'])) {
            $city = $location->getCityById($_POST['city_id']);
            if($city) {
                $userProfile->saveLocation(
                    $_SESSION['user_id'],
                    $_POST['city_id'],
                    null,
                    null,
                    $_POST['postal_code'] ?? null,
                    $_POST['max_distance'] ?? 50
                );
            }
        }
        $success = 'Profile updated successfully!';
        $profile = $userProfile->getProfile($_SESSION['user_id']);
    } else {
        $error = 'Failed to update profile';
    }
}

include 'views/header.php';
?>
<!-- Existing Theme Styles -->
<link rel="stylesheet" href="/assets/css/dark-blue-theme.css">
<link rel="stylesheet" href="/assets/css/creator-cards.css">

<!-- Bootstrap & Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css" rel="stylesheet">

<style>
.edit-profile-container {
    max-width: 1100px;
    margin: 2rem auto;
    padding: 0 1.5rem;
}

.profile-card {
    background: var(--card-bg);
    border-radius: 24px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    overflow: hidden;
    margin-bottom: 2rem;
    border: 2px solid var(--border-color);
}

.profile-header-section {
    background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
    padding: 3rem 2rem;
    text-align: center;
    position: relative;
}

.avatar-upload-wrapper {
    position: relative;
    display: inline-block;
    margin-bottom: 1.5rem;
}

.avatar-preview {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    object-fit: cover;
    border: 5px solid rgba(255,255,255,0.3);
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    transition: all 0.3s;
}

.avatar-preview:hover {
    transform: scale(1.05);
    border-color: rgba(255,255,255,0.5);
}

.badge-container {
    position: absolute;
    bottom: -5px;
    right: -5px;
    display: flex;
    gap: 5px;
}

.profile-badge {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    border: 2px solid white;
}

.avatar-actions {
    margin-top: 1rem;
    display: flex;
    gap: 0.75rem;
    justify-content: center;
    flex-wrap: wrap;
}

.form-section {
    padding: 2rem;
    border-bottom: 1px solid var(--border-color);
}

.form-section:last-of-type {
    border-bottom: none;
}

.section-title {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 1.5rem;
    color: var(--text-white);
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.section-title i {
    font-size: 1.75rem;
    color: var(--primary-blue);
}

.form-control, .form-select {
    background: rgba(26, 31, 46, 0.5);
    border: 2px solid var(--border-color);
    color: var(--text-white);
    border-radius: 12px;
    padding: 0.75rem 1rem;
    transition: all 0.3s;
}

.form-control:focus, .form-select:focus {
    background: rgba(26, 31, 46, 0.8);
    border-color: var(--primary-blue);
    box-shadow: 0 0 0 4px rgba(66,103,245,0.1);
    color: var(--text-white);
}

.form-control::placeholder {
    color: var(--text-gray);
}

.form-label {
    color: var(--text-gray);
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.form-text {
    color: var(--text-gray);
    font-size: 0.875rem;
}

.form-check-input {
    background-color: rgba(26, 31, 46, 0.5);
    border: 2px solid var(--border-color);
}

.form-check-input:checked {
    background-color: var(--primary-blue);
    border-color: var(--primary-blue);
}

.form-check-label {
    color: var(--text-gray);
}

.save-actions {
    display: flex;
    gap: 1rem;
    padding: 2rem;
    background: rgba(26, 31, 46, 0.3);
}

@media (max-width: 768px) {
    .save-actions {
        flex-direction: column;
    }
    
    .avatar-preview {
        width: 120px;
        height: 120px;
    }
}
</style>

<div class="page-content">
    <div class="edit-profile-container">
        <!-- Header with Avatar -->
        <div class="profile-card">
            <div class="profile-header-section">
                <div class="avatar-upload-wrapper">
                    <img src="<?php echo htmlspecialchars($profile['avatar'] ?? '/assets/images/default-avatar.png'); ?>" 
                         alt="Avatar" 
                         class="avatar-preview" 
                         id="avatarPreview">
                    <div class="badge-container">
                        <?php if($profile['is_admin'] ?? false): ?>
                            <span class="profile-badge" style="background:var(--danger-red)" title="Admin">🛡️</span>
                        <?php endif; ?>
                        <?php if($profile['verified'] ?? false): ?>
                            <span class="profile-badge" style="background:var(--primary-blue)" title="Verified">✓</span>
                        <?php endif; ?>
                        <?php if($profile['creator'] ?? false): ?>
                            <span class="profile-badge" style="background:var(--warning-orange)" title="Creator">⭐</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <form method="POST" enctype="multipart/form-data" id="avatarForm">
                    <div class="avatar-actions">
                        <label for="avatarInput" class="btn btn-light btn-sm">
                            <i class="bi bi-camera"></i> Change Photo
                        </label>
                        <input type="file" name="avatar" id="avatarInput" class="d-none" accept="image/png,image/jpeg">
                        
                        <?php if(!empty($profile['avatar'])): ?>
                            <button type="submit" name="remove_avatar" class="btn btn-danger btn-sm">
                                <i class="bi bi-trash"></i> Remove
                            </button>
                        <?php endif; ?>
                        <input type="hidden" name="change_avatar" value="1">
                    </div>
                    <small class="text-white-50 d-block mt-2">JPEG or PNG up to 2MB</small>
                </form>
            </div>
            
            <?php if($success): ?>
            <div class="alert alert-success m-3">
                <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
            <?php endif; ?>
            
            <?php if($error): ?>
            <div class="alert alert-error m-3">
                <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" id="profileForm">
                <!-- About Me Section -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="bi bi-person-lines-fill"></i>
                        About Me
                    </h3>
                    <div class="mb-3">
                        <label class="form-label">Bio</label>
                        <textarea name="bio" class="form-control" rows="5" 
                                  placeholder="Tell others about yourself..."><?php echo htmlspecialchars($profile['bio'] ?? ''); ?></textarea>
                        <div class="form-text">Share your interests, hobbies, and what makes you unique</div>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Occupation</label>
                            <input type="text" name="occupation" class="form-control" 
                                   value="<?php echo htmlspecialchars($profile['occupation'] ?? ''); ?>"
                                   placeholder="Your profession">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Education</label>
                            <select name="education" class="form-select">
                                <option value="">Select...</option>
                                <option value="high_school" <?php echo ($profile['education'] ?? '') == 'high_school' ? 'selected' : ''; ?>>High School</option>
                                <option value="some_college" <?php echo ($profile['education'] ?? '') == 'some_college' ? 'selected' : ''; ?>>Some College</option>
                                <option value="bachelors" <?php echo ($profile['education'] ?? '') == 'bachelors' ? 'selected' : ''; ?>>Bachelor's Degree</option>
                                <option value="masters" <?php echo ($profile['education'] ?? '') == 'masters' ? 'selected' : ''; ?>>Master's Degree</option>
                                <option value="phd" <?php echo ($profile['education'] ?? '') == 'phd' ? 'selected' : ''; ?>>PhD</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Physical Attributes -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="bi bi-rulers"></i>
                        Physical Attributes
                    </h3>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Height (cm)</label>
                            <input type="number" name="height" class="form-control" 
                                   value="<?php echo htmlspecialchars($profile['height'] ?? ''); ?>"
                                   placeholder="170">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Body Type</label>
                            <select name="body_type" class="form-select">
                                <option value="">Select...</option>
                                <option value="slim" <?php echo ($profile['body_type'] ?? '') == 'slim' ? 'selected' : ''; ?>>Slim</option>
                                <option value="athletic" <?php echo ($profile['body_type'] ?? '') == 'athletic' ? 'selected' : ''; ?>>Athletic</option>
                                <option value="average" <?php echo ($profile['body_type'] ?? '') == 'average' ? 'selected' : ''; ?>>Average</option>
                                <option value="curvy" <?php echo ($profile['body_type'] ?? '') == 'curvy' ? 'selected' : ''; ?>>Curvy</option>
                                <option value="muscular" <?php echo ($profile['body_type'] ?? '') == 'muscular' ? 'selected' : ''; ?>>Muscular</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Ethnicity</label>
                            <input type="text" name="ethnicity" class="form-control" 
                                   value="<?php echo htmlspecialchars($profile['ethnicity'] ?? ''); ?>"
                                   placeholder="Your ethnicity">
                        </div>
                    </div>
                </div>
                
                <!-- Lifestyle -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="bi bi-hearts"></i>
                        Lifestyle & Preferences
                    </h3>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Relationship Status</label>
                            <select name="relationship_status" class="form-select">
                                <option value="">Select...</option>
                                <option value="single" <?php echo ($profile['relationship_status'] ?? '') == 'single' ? 'selected' : ''; ?>>Single</option>
                                <option value="dating" <?php echo ($profile['relationship_status'] ?? '') == 'dating' ? 'selected' : ''; ?>>Dating</option>
                                <option value="relationship" <?php echo ($profile['relationship_status'] ?? '') == 'relationship' ? 'selected' : ''; ?>>In a Relationship</option>
                                <option value="married" <?php echo ($profile['relationship_status'] ?? '') == 'married' ? 'selected' : ''; ?>>Married</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Looking For</label>
                            <select name="looking_for[]" class="form-select" multiple size="3">
                                <option value="friendship">Friendship</option>
                                <option value="dating">Dating</option>
                                <option value="relationship">Relationship</option>
                                <option value="casual">Casual</option>
                            </select>
                            <div class="form-text">Hold Ctrl/Cmd to select multiple</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Smoking</label>
                            <select name="smoking" class="form-select">
                                <option value="">Select...</option>
                                <option value="never" <?php echo ($profile['smoking'] ?? '') == 'never' ? 'selected' : ''; ?>>Never</option>
                                <option value="occasionally" <?php echo ($profile['smoking'] ?? '') == 'occasionally' ? 'selected' : ''; ?>>Occasionally</option>
                                <option value="regularly" <?php echo ($profile['smoking'] ?? '') == 'regularly' ? 'selected' : ''; ?>>Regularly</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Drinking</label>
                            <select name="drinking" class="form-select">
                                <option value="">Select...</option>
                                <option value="never" <?php echo ($profile['drinking'] ?? '') == 'never' ? 'selected' : ''; ?>>Never</option>
                                <option value="socially" <?php echo ($profile['drinking'] ?? '') == 'socially' ? 'selected' : ''; ?>>Socially</option>
                                <option value="regularly" <?php echo ($profile['drinking'] ?? '') == 'regularly' ? 'selected' : ''; ?>>Regularly</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Want Kids?</label>
                            <select name="wants_kids" class="form-select">
                                <option value="">Select...</option>
                                <option value="yes" <?php echo ($profile['wants_kids'] ?? '') == 'yes' ? 'selected' : ''; ?>>Yes</option>
                                <option value="no" <?php echo ($profile['wants_kids'] ?? '') == 'no' ? 'selected' : ''; ?>>No</option>
                                <option value="maybe" <?php echo ($profile['wants_kids'] ?? '') == 'maybe' ? 'selected' : ''; ?>>Maybe</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <div class="form-check">
                            <input type="checkbox" name="has_kids" class="form-check-input" id="hasKids" 
                                   <?php echo ($profile['has_kids'] ?? false) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="hasKids">I have children</label>
                        </div>
                    </div>
                </div>
                
                <!-- Interests -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="bi bi-star"></i>
                        Interests & Hobbies
                    </h3>
                    <div class="mb-3">
                        <label class="form-label">Interests</label>
                        <input type="text" name="interests" class="form-control" 
                               placeholder="Travel, Music, Sports, Cooking..." 
                               value="<?php echo htmlspecialchars(is_array($profile['interests'] ?? null) ? implode(', ', $profile['interests']) : ''); ?>">
                        <div class="form-text">Separate with commas</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Languages</label>
                        <input type="text" name="languages" class="form-control" 
                               placeholder="English, Spanish, French..." 
                               value="<?php echo htmlspecialchars(is_array($profile['languages'] ?? null) ? implode(', ', $profile['languages']) : ''); ?>">
                        <div class="form-text">Separate with commas</div>
                    </div>
                </div>
                
                <!-- Privacy Settings -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="bi bi-shield-lock"></i>
                        Privacy Settings
                    </h3>
                    <div class="form-check mb-3">
                        <input type="checkbox" name="show_online_status" class="form-check-input" id="showOnline" 
                               <?php echo ($profile['show_online_status'] ?? true) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="showOnline">
                            Show when I'm online
                        </label>
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" name="show_age" class="form-check-input" id="showAge" 
                               <?php echo ($profile['show_age'] ?? true) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="showAge">
                            Display my age
                        </label>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="display_distance" class="form-check-input" id="showDistance" 
                               <?php echo ($profile['display_distance'] ?? true) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="showDistance">
                            Show distance to other users
                        </label>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="save-actions">
                    <button type="submit" class="btn-primary flex-fill">
                        <i class="bi bi-check-circle"></i> Save Changes
                    </button>
                    <a href="profile.php?id=<?php echo $_SESSION['user_id']; ?>" class="btn-secondary flex-fill" style="text-align:center">
                        <i class="bi bi-x-circle"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Avatar preview
document.getElementById('avatarInput')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('avatarPreview').src = e.target.result;
        }
        reader.readAsDataURL(file);
        // Auto-submit avatar form
        document.getElementById('avatarForm').submit();
    }
});
</script>

<?php include 'views/footer.php'; ?>