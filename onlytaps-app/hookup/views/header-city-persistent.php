<?php
// Add this to the top of your existing header.php

// Get saved city if user is logged in
$saved_city = null;
$show_city_selector = false;

if(isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../classes/CityPersistence.php';
    $cityPersistence = new CityPersistence($conn_header);
    $saved_city = $cityPersistence->getSavedCity($_SESSION['user_id']);
    
    if($saved_city) {
        $_SESSION['current_city'] = $saved_city['city_slug'];
        $_SESSION['current_city_name'] = $saved_city['city_name'];
        $_SESSION['current_state'] = $saved_city['state_abbr'];
    }
}

// Add this button to your navigation (after the logo):
?>
<!-- City Selector Button (Add to navbar) -->
<?php if(isset($_SESSION['user_id']) && $saved_city): ?>
<div class="nav-item">
    <button class="nav-link" onclick="showCityModal()" style="display: flex; align-items: center; gap: 0.5rem;">
        <i class="bi bi-geo-alt-fill"></i>
        <span><?php echo htmlspecialchars($saved_city['city_name']); ?>, <?php echo $saved_city['state_abbr']; ?></span>
        <i class="bi bi-chevron-down"></i>
    </button>
</div>
<?php endif; ?>

<!-- City Change Modal -->
<div id="cityModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.85); z-index: 9999; align-items: center; justify-content: center;">
    <div style="background: var(--card-bg); border-radius: 20px; max-width: 500px; width: 90%; padding: 2rem; border: 2px solid var(--border-color);">
        <h3 style="margin-bottom: 1.5rem;">📍 Change City</h3>
        
        <div class="form-group">
            <label>Current City</label>
            <div style="background: rgba(66, 103, 245, 0.1); padding: 1rem; border-radius: 10px; margin-bottom: 1rem;">
                <strong style="color: var(--primary-blue);">
                    <?php echo htmlspecialchars($saved_city['city_name'] ?? 'Not Set'); ?>, 
                    <?php echo htmlspecialchars($saved_city['state_abbr'] ?? ''); ?>
                </strong>
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <button onclick="closeCityModal()" class="btn-secondary btn-block">Cancel</button>
            <a href="/choose-location.php?change=1" class="btn-primary btn-block">Change City</a>
        </div>
    </div>
</div>

<script>
function showCityModal() {
    document.getElementById('cityModal').style.display = 'flex';
}

function closeCityModal() {
    document.getElementById('cityModal').style.display = 'none';
}

document.getElementById('cityModal')?.addEventListener('click', function(e) {
    if(e.target === this) closeCityModal();
});
</script>