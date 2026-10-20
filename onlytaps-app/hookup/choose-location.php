<?php
session_start();
require_once 'config/database.php';
require_once 'classes/Location.php';
require_once 'includes/maintenance_check.php';

$database = new Database();
$db = $database->getConnection();

// Check for maintenance mode
if(checkMaintenanceMode($db)) {
    header('Location: maintenance.php');
    exit();
}

$location = new Location($db);

// Get all states
$states = $location->getAllStates();
$total_locations = $location->getTotalLocationsCount();

// Get popular cities
$popular_cities = [];
try {
    $query = "SELECT c.*, s.name as state_name, s.abbreviation as state_abbr, 
              (SELECT COUNT(*) FROM listings WHERE city_id = c.id AND status = 'active') as listing_count
              FROM cities c
              LEFT JOIN states s ON c.state_id = s.id
              ORDER BY listing_count DESC, c.name ASC
              LIMIT 20";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $popular_cities = $stmt->fetchAll();
} catch(PDOException $e) {
    error_log("Error fetching popular cities: " . $e->getMessage());
}

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['state']) && isset($_POST['city'])) {
    $_SESSION['selected_state'] = $_POST['state'];
    $_SESSION['selected_city'] = $_POST['city'];
    header('Location: city.php?location=' . $_POST['city']);
    exit();
}

include 'views/header.php';
?>

<style>
.location-page {
    min-height: 100vh;
    background: linear-gradient(135deg, rgba(10, 15, 30, 0.95), rgba(20, 30, 60, 0.95));
    padding: 2rem 0;
}

.location-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 0 20px;
}

.location-brand {
    text-align: center;
    margin-bottom: 3rem;
}

.brand-icon {
    font-size: 5rem;
    margin-bottom: 1rem;
    animation: float 3s ease-in-out infinite;
}

@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
}

.location-content {
    background: var(--card-bg);
    border-radius: 20px;
    padding: 2.5rem;
    border: 2px solid var(--border-color);
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
}

.locations-count {
    text-align: center;
    color: var(--primary-blue);
    font-size: 1.1rem;
    margin: 1rem 0 2rem;
    font-weight: 600;
}

.popular-cities {
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 1px solid var(--border-color);
}

.city-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 1rem;
    margin-top: 1.5rem;
}

.city-card {
    background: rgba(66, 103, 245, 0.05);
    border: 2px solid var(--border-color);
    border-radius: 12px;
    padding: 1rem;
    text-align: center;
    text-decoration: none;
    color: var(--text-white);
    transition: all 0.3s;
    cursor: pointer;
}

.city-card:hover {
    background: rgba(66, 103, 245, 0.15);
    border-color: var(--primary-blue);
    transform: translateY(-3px);
}

.city-name {
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: var(--text-white);
}

.city-info {
    font-size: 0.85rem;
    color: var(--text-gray);
}

@media (max-width: 768px) {
    .location-content {
        padding: 1.5rem;
    }
    
    .city-grid {
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    }
}
</style>

<div class="location-page">
    <div class="location-container">
        <div class="location-brand">
            <div class="brand-icon">📋</div>
            <h1 style="font-size: 2.5rem; margin-bottom: 0.5rem;">Turnpage</h1>
            <p style="color: var(--primary-blue); font-size: 1.3rem;">Local Hookup Classifieds</p>
        </div>

        <div class="location-content">
            <h2 style="text-align: center; margin-bottom: 0.5rem;">Choose Your City</h2>
            <h3 style="text-align: center; color: var(--text-gray); font-weight: normal; margin-bottom: 1rem;">
                Browse Personal Ads in United States
            </h3>
            <p class="locations-count">We are active in <?php echo number_format($total_locations); ?> locations 🎉</p>

            <form method="POST" action="choose-location.php" id="locationForm">
                <div class="form-group">
                    <label>Select State</label>
                    <select name="state" id="stateSelect" required>
                        <option value="">Choose a state...</option>
                        <?php foreach($states as $state): ?>
                        <option value="<?php echo $state['id']; ?>" data-abbr="<?php echo $state['abbreviation']; ?>">
                            <?php echo htmlspecialchars($state['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Select City</label>
                    <select name="city" id="citySelect" required disabled>
                        <option value="">Select state first...</option>
                    </select>
                </div>

                <button type="submit" class="btn-primary btn-block" id="continueBtn" disabled>
                    Continue to Classifieds →
                </button>
            </form>

            <?php if(count($popular_cities) > 0): ?>
            <div class="popular-cities">
                <h3 style="text-align: center; margin-bottom: 1rem; color: var(--primary-blue);">
                    🔥 Popular Cities
                </h3>
                <div class="city-grid">
                    <?php foreach($popular_cities as $city): ?>
                    <a href="city.php?location=<?php echo htmlspecialchars($city['slug']); ?>" class="city-card">
                        <div class="city-name">
                            <?php echo htmlspecialchars($city['name']); ?>
                        </div>
                        <div class="city-info">
                            <?php echo htmlspecialchars($city['state_abbr']); ?>
                            <?php if($city['listing_count'] > 0): ?>
                            <br>
                            <span style="color: var(--primary-blue);">
                                <?php echo number_format($city['listing_count']); ?> ads
                            </span>
                            <?php endif; ?>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div style="text-align: center; margin-top: 2rem; color: var(--text-gray);">
            <p style="margin-bottom: 0.5rem;">Currently set for United States</p>
            <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; margin-top: 1rem;">
                <a href="terms.php" style="color: var(--text-gray);">Terms</a>
                <a href="privacy.php" style="color: var(--text-gray);">Privacy</a>
                <a href="safety.php" style="color: var(--text-gray);">Safety</a>
                <a href="support.php" style="color: var(--text-gray);">Support</a>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('stateSelect').addEventListener('change', function() {
    const stateId = this.value;
    const citySelect = document.getElementById('citySelect');
    const continueBtn = document.getElementById('continueBtn');
    
    if(!stateId) {
        citySelect.disabled = true;
        citySelect.innerHTML = '<option value="">Select state first...</option>';
        continueBtn.disabled = true;
        return;
    }
    
    citySelect.disabled = true;
    citySelect.innerHTML = '<option value="">Loading cities...</option>';
    
    // Fetch cities for selected state
    fetch('get-cities.php?state_id=' + stateId)
        .then(response => response.json())
        .then(cities => {
            citySelect.innerHTML = '<option value="">Choose a city...</option>';
            cities.forEach(city => {
                const option = document.createElement('option');
                option.value = city.slug;
                option.textContent = city.name;
                citySelect.appendChild(option);
            });
            citySelect.disabled = false;
        })
        .catch(error => {
            console.error('Error fetching cities:', error);
            citySelect.innerHTML = '<option value="">Error loading cities</option>';
        });
});

document.getElementById('citySelect').addEventListener('change', function() {
    const continueBtn = document.getElementById('continueBtn');
    continueBtn.disabled = !this.value;
});
</script>

<?php include 'views/footer.php'; ?>