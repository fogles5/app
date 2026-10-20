<?php
session_start();
require_once 'config/database.php';
require_once 'classes/Category.php';
require_once 'classes/Listing.php';
require_once 'classes/Location.php';

if(!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header('Location: my-listings.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$category = new Category($db);
$listing = new Listing($db);
$location = new Location($db);

$listing_id = $_GET['id'];

// Get listing and verify ownership
$query = "SELECT * FROM listings WHERE id = :id AND user_id = :user_id LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $listing_id);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$item = $stmt->fetch();

if(!$item) {
    $_SESSION['error'] = 'Listing not found or access denied';
    header('Location: my-listings.php');
    exit();
}

$categories = $category->getAll();
$states = $location->getAllStates();

// Get current city info
$current_city = null;
if($item['city_id']) {
    $current_city = $location->getCityById($item['city_id']);
}

$error = '';
$success = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $listing->id = $listing_id;
    $listing->user_id = $_SESSION['user_id'];
    $listing->category_id = $_POST['category_id'];
    $listing->title = $_POST['title'];
    $listing->description = $_POST['description'];
    $listing->location = $_POST['location'];
    $listing->age = !empty($_POST['age']) ? $_POST['age'] : null;
    $listing->gender = $_POST['gender'];
    $listing->seeking = $_POST['seeking'];
    
    // Update city_id if provided
    if(!empty($_POST['city_id'])) {
        $query = "UPDATE listings SET city_id = :city_id WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':city_id', $_POST['city_id']);
        $stmt->bindParam(':id', $listing_id);
        $stmt->execute();
    }
    
    if($listing->update()) {
        $_SESSION['success'] = 'Listing updated successfully';
        header('Location: listing.php?id=' . $listing_id);
        exit();
    } else {
        $error = 'Failed to update listing';
    }
}

include 'views/header.php';
?>

<div class="page-content">
    <div class="container-narrow">
        <div class="card">
            <h2>Edit Listing</h2>
            
            <?php if($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="edit-listing.php?id=<?php echo $listing_id; ?>">
                <div class="form-group">
                    <label>Category *</label>
                    <select name="category_id" required>
                        <option value="">Select a category</option>
                        <?php foreach($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo ($cat['id'] == $item['category_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Title *</label>
                    <input type="text" name="title" required maxlength="255" value="<?php echo htmlspecialchars($item['title']); ?>">
                </div>
                
                <div class="form-group">
                    <label>Description *</label>
                    <textarea name="description" required rows="6"><?php echo htmlspecialchars($item['description']); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label>Location *</label>
                    <input type="text" name="location" required value="<?php echo htmlspecialchars($item['location']); ?>">
                    <?php if($current_city): ?>
                    <small style="color: var(--text-gray);">Current city: <?php echo htmlspecialchars($current_city['name']); ?>, <?php echo htmlspecialchars($current_city['state_name']); ?></small>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label>City (Optional - Update Location)</label>
                    <select name="state_id" id="stateSelect">
                        <option value="">Select State to Update City</option>
                        <?php foreach($states as $state): ?>
                        <option value="<?php echo $state['id']; ?>" <?php echo ($current_city && $current_city['state_abbr'] == $state['abbreviation']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($state['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <select name="city_id" id="citySelect">
                        <option value="">Select City</option>
                        <?php if($current_city): ?>
                        <option value="<?php echo $current_city['id']; ?>" selected><?php echo htmlspecialchars($current_city['name']); ?></option>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem;">
                    <div class="form-group">
                        <label>Your Age</label>
                        <input type="number" name="age" min="18" max="99" value="<?php echo $item['age']; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Your Gender</label>
                        <select name="gender">
                            <option value="">Select...</option>
                            <option value="male" <?php echo ($item['gender'] == 'male') ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?php echo ($item['gender'] == 'female') ? 'selected' : ''; ?>>Female</option>
                            <option value="couple" <?php echo ($item['gender'] == 'couple') ? 'selected' : ''; ?>>Couple</option>
                            <option value="trans" <?php echo ($item['gender'] == 'trans') ? 'selected' : ''; ?>>Trans</option>
                            <option value="other" <?php echo ($item['gender'] == 'other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Seeking</label>
                        <select name="seeking">
                            <option value="any" <?php echo ($item['seeking'] == 'any') ? 'selected' : ''; ?>>Anyone</option>
                            <option value="male" <?php echo ($item['seeking'] == 'male') ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?php echo ($item['seeking'] == 'female') ? 'selected' : ''; ?>>Female</option>
                            <option value="couple" <?php echo ($item['seeking'] == 'couple') ? 'selected' : ''; ?>>Couple</option>
                            <option value="trans" <?php echo ($item['seeking'] == 'trans') ? 'selected' : ''; ?>>Trans</option>
                        </select>
                    </div>
                </div>
                
                <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                    <button type="submit" class="btn-primary" style="flex: 1;">Update Listing</button>
                    <a href="listing.php?id=<?php echo $listing_id; ?>" class="btn-secondary" style="flex: 1; text-align: center;">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('stateSelect').addEventListener('change', function() {
    const stateId = this.value;
    const citySelect = document.getElementById('citySelect');
    
    if(!stateId) {
        citySelect.innerHTML = '<option value="">Select City</option>';
        return;
    }
    
    fetch('get-cities.php?state_id=' + stateId)
        .then(response => response.json())
        .then(cities => {
            citySelect.innerHTML = '<option value="">Select City</option>';
            cities.forEach(city => {
                const option = document.createElement('option');
                option.value = city.id;
                option.textContent = city.name;
                citySelect.appendChild(option);
            });
        })
        .catch(error => {
            console.error('Error fetching cities:', error);
        });
});
</script>

<?php include 'views/footer.php'; ?>