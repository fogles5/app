<?php
/**
 * UserProfile Class - Handles extended user profiles
 */
class UserProfile {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Create or update profile
    public function saveProfile($user_id, $data) {
        // Check if profile exists
        $query = "SELECT id FROM user_profiles WHERE user_id = :user_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            return $this->updateProfile($user_id, $data);
        } else {
            return $this->createProfile($user_id, $data);
        }
    }

    // Create profile
    private function createProfile($user_id, $data) {
        $query = "INSERT INTO user_profiles 
                  (user_id, bio, height, body_type, ethnicity, relationship_status, 
                   looking_for, interests, occupation, education, smoking, drinking, 
                   has_kids, wants_kids, languages, display_distance, show_age, show_online_status) 
                  VALUES 
                  (:user_id, :bio, :height, :body_type, :ethnicity, :relationship_status,
                   :looking_for, :interests, :occupation, :education, :smoking, :drinking,
                   :has_kids, :wants_kids, :languages, :display_distance, :show_age, :show_online_status)";
        
        $stmt = $this->conn->prepare($query);
        $this->bindProfileParams($stmt, $user_id, $data);
        
        if($stmt->execute()) {
            $this->calculateProfileCompletion($user_id);
            return true;
        }
        return false;
    }

    // Update profile
    private function updateProfile($user_id, $data) {
        $query = "UPDATE user_profiles SET 
                  bio = :bio, height = :height, body_type = :body_type, 
                  ethnicity = :ethnicity, relationship_status = :relationship_status,
                  looking_for = :looking_for, interests = :interests, 
                  occupation = :occupation, education = :education, 
                  smoking = :smoking, drinking = :drinking, has_kids = :has_kids,
                  wants_kids = :wants_kids, languages = :languages,
                  display_distance = :display_distance, show_age = :show_age,
                  show_online_status = :show_online_status
                  WHERE user_id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $this->bindProfileParams($stmt, $user_id, $data);
        
        if($stmt->execute()) {
            $this->calculateProfileCompletion($user_id);
            return true;
        }
        return false;
    }

    // Bind parameters
    private function bindProfileParams($stmt, $user_id, $data) {
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':bio', $data['bio']);
        $stmt->bindParam(':height', $data['height']);
        $stmt->bindParam(':body_type', $data['body_type']);
        $stmt->bindParam(':ethnicity', $data['ethnicity']);
        $stmt->bindParam(':relationship_status', $data['relationship_status']);
        
        $looking_for = json_encode($data['looking_for'] ?? []);
        $interests = json_encode($data['interests'] ?? []);
        $languages = json_encode($data['languages'] ?? []);
        
        $stmt->bindParam(':looking_for', $looking_for);
        $stmt->bindParam(':interests', $interests);
        $stmt->bindParam(':occupation', $data['occupation']);
        $stmt->bindParam(':education', $data['education']);
        $stmt->bindParam(':smoking', $data['smoking']);
        $stmt->bindParam(':drinking', $data['drinking']);
        $stmt->bindParam(':has_kids', $data['has_kids'], PDO::PARAM_BOOL);
        $stmt->bindParam(':wants_kids', $data['wants_kids']);
        $stmt->bindParam(':languages', $languages);
        $stmt->bindParam(':display_distance', $data['display_distance'], PDO::PARAM_BOOL);
        $stmt->bindParam(':show_age', $data['show_age'], PDO::PARAM_BOOL);
        $stmt->bindParam(':show_online_status', $data['show_online_status'], PDO::PARAM_BOOL);
    }

    // Get profile
    public function getProfile($user_id) {
        $query = "SELECT up.*, u.username, u.email, u.created_at as member_since, u.is_online, u.last_activity
                  FROM user_profiles up
                  LEFT JOIN users u ON up.user_id = u.id
                  WHERE up.user_id = :user_id 
                  LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        $profile = $stmt->fetch();
        
        if($profile) {
            // Decode JSON fields
            $profile['looking_for'] = json_decode($profile['looking_for'], true) ?? [];
            $profile['interests'] = json_decode($profile['interests'], true) ?? [];
            $profile['languages'] = json_decode($profile['languages'], true) ?? [];
        }
        
        return $profile;
    }

    // Calculate profile completion percentage
    private function calculateProfileCompletion($user_id) {
        $profile = $this->getProfile($user_id);
        if(!$profile) return 0;
        
        $fields = ['bio', 'height', 'body_type', 'ethnicity', 'relationship_status', 
                   'occupation', 'education', 'smoking', 'drinking'];
        
        $completed = 0;
        $total = count($fields) + 3; // +3 for arrays
        
        foreach($fields as $field) {
            if(!empty($profile[$field])) $completed++;
        }
        
        if(!empty($profile['looking_for'])) $completed++;
        if(!empty($profile['interests'])) $completed++;
        if(!empty($profile['languages'])) $completed++;
        
        $percentage = round(($completed / $total) * 100);
        
        $query = "UPDATE user_profiles SET profile_completion = :percentage WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':percentage', $percentage);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        return $percentage;
    }

    // Save user location
    public function saveLocation($user_id, $city_id, $latitude, $longitude, $postal_code = null, $max_distance = 50) {
        $query = "INSERT INTO user_locations 
                  (user_id, city_id, latitude, longitude, postal_code, max_distance) 
                  VALUES (:user_id, :city_id, :latitude, :longitude, :postal_code, :max_distance)
                  ON DUPLICATE KEY UPDATE 
                  city_id = :city_id2, latitude = :latitude2, longitude = :longitude2, 
                  postal_code = :postal_code2, max_distance = :max_distance2";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':city_id', $city_id);
        $stmt->bindParam(':latitude', $latitude);
        $stmt->bindParam(':longitude', $longitude);
        $stmt->bindParam(':postal_code', $postal_code);
        $stmt->bindParam(':max_distance', $max_distance);
        $stmt->bindParam(':city_id2', $city_id);
        $stmt->bindParam(':latitude2', $latitude);
        $stmt->bindParam(':longitude2', $longitude);
        $stmt->bindParam(':postal_code2', $postal_code);
        $stmt->bindParam(':max_distance2', $max_distance);
        
        return $stmt->execute();
    }

    // Get user location
    public function getUserLocation($user_id) {
        $query = "SELECT * FROM user_locations WHERE user_id = :user_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        return $stmt->fetch();
    }

    // Calculate distance between two users (Haversine formula)
    public function calculateDistance($lat1, $lon1, $lat2, $lon2) {
        $earthRadius = 3959; // miles (use 6371 for km)
        
        $latFrom = deg2rad($lat1);
        $lonFrom = deg2rad($lon1);
        $latTo = deg2rad($lat2);
        $lonTo = deg2rad($lon2);
        
        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;
        
        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
                 cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
        
        return round($angle * $earthRadius, 1);
    }

    // Save preferences
    public function savePreferences($user_id, $preferences) {
        $query = "INSERT INTO user_preferences 
                  (user_id, min_age, max_age, preferred_gender, preferred_body_types, 
                   preferred_ethnicity, max_distance, only_with_photos, only_verified, 
                   preferred_relationship_status, deal_breakers) 
                  VALUES 
                  (:user_id, :min_age, :max_age, :preferred_gender, :preferred_body_types,
                   :preferred_ethnicity, :max_distance, :only_with_photos, :only_verified,
                   :preferred_relationship_status, :deal_breakers)
                  ON DUPLICATE KEY UPDATE
                  min_age = :min_age2, max_age = :max_age2, preferred_gender = :preferred_gender2,
                  preferred_body_types = :preferred_body_types2, preferred_ethnicity = :preferred_ethnicity2,
                  max_distance = :max_distance2, only_with_photos = :only_with_photos2,
                  only_verified = :only_verified2, preferred_relationship_status = :preferred_relationship_status2,
                  deal_breakers = :deal_breakers2";
        
        $stmt = $this->conn->prepare($query);
        
        $preferred_gender = json_encode($preferences['preferred_gender'] ?? []);
        $preferred_body_types = json_encode($preferences['preferred_body_types'] ?? []);
        $preferred_ethnicity = json_encode($preferences['preferred_ethnicity'] ?? []);
        $preferred_relationship = json_encode($preferences['preferred_relationship_status'] ?? []);
        $deal_breakers = json_encode($preferences['deal_breakers'] ?? []);
        
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':min_age', $preferences['min_age']);
        $stmt->bindParam(':max_age', $preferences['max_age']);
        $stmt->bindParam(':preferred_gender', $preferred_gender);
        $stmt->bindParam(':preferred_body_types', $preferred_body_types);
        $stmt->bindParam(':preferred_ethnicity', $preferred_ethnicity);
        $stmt->bindParam(':max_distance', $preferences['max_distance']);
        $stmt->bindParam(':only_with_photos', $preferences['only_with_photos'], PDO::PARAM_BOOL);
        $stmt->bindParam(':only_verified', $preferences['only_verified'], PDO::PARAM_BOOL);
        $stmt->bindParam(':preferred_relationship_status', $preferred_relationship);
        $stmt->bindParam(':deal_breakers', $deal_breakers);
        
        // Duplicate key bindings
        $stmt->bindParam(':min_age2', $preferences['min_age']);
        $stmt->bindParam(':max_age2', $preferences['max_age']);
        $stmt->bindParam(':preferred_gender2', $preferred_gender);
        $stmt->bindParam(':preferred_body_types2', $preferred_body_types);
        $stmt->bindParam(':preferred_ethnicity2', $preferred_ethnicity);
        $stmt->bindParam(':max_distance2', $preferences['max_distance']);
        $stmt->bindParam(':only_with_photos2', $preferences['only_with_photos'], PDO::PARAM_BOOL);
        $stmt->bindParam(':only_verified2', $preferences['only_verified'], PDO::PARAM_BOOL);
        $stmt->bindParam(':preferred_relationship_status2', $preferred_relationship);
        $stmt->bindParam(':deal_breakers2', $deal_breakers);
        
        return $stmt->execute();
    }

    // Get preferences
    public function getPreferences($user_id) {
        $query = "SELECT * FROM user_preferences WHERE user_id = :user_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        $prefs = $stmt->fetch();
        
        if($prefs) {
            $prefs['preferred_gender'] = json_decode($prefs['preferred_gender'], true) ?? [];
            $prefs['preferred_body_types'] = json_decode($prefs['preferred_body_types'], true) ?? [];
            $prefs['preferred_ethnicity'] = json_decode($prefs['preferred_ethnicity'], true) ?? [];
            $prefs['preferred_relationship_status'] = json_decode($prefs['preferred_relationship_status'], true) ?? [];
            $prefs['deal_breakers'] = json_decode($prefs['deal_breakers'], true) ?? [];
        }
        
        return $prefs;
    }

    // Record profile view
    public function recordView($viewer_id, $viewed_id) {
        if($viewer_id == $viewed_id) return; // Don't count self-views
        
        $query = "INSERT INTO profile_views (viewer_id, viewed_id, view_count) 
                  VALUES (:viewer_id, :viewed_id, 1)
                  ON DUPLICATE KEY UPDATE 
                  view_count = view_count + 1, last_viewed = CURRENT_TIMESTAMP";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':viewer_id', $viewer_id);
        $stmt->bindParam(':viewed_id', $viewed_id);
        $stmt->execute();
    }

    // Get who viewed my profile
    public function getProfileViews($user_id, $limit = 20) {
        $query = "SELECT pv.*, u.username, up.bio, 
                         (SELECT file_path FROM user_photos WHERE user_id = pv.viewer_id AND is_primary = TRUE LIMIT 1) as photo
                  FROM profile_views pv
                  LEFT JOIN users u ON pv.viewer_id = u.id
                  LEFT JOIN user_profiles up ON pv.viewer_id = up.user_id
                  WHERE pv.viewed_id = :user_id
                  ORDER BY pv.last_viewed DESC
                  LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    // Add to favorites
    public function addFavorite($user_id, $favorited_user_id) {
        $query = "INSERT INTO user_favorites (user_id, favorited_user_id) 
                  VALUES (:user_id, :favorited_id)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':favorited_id', $favorited_user_id);
        
        try {
            return $stmt->execute();
        } catch(PDOException $e) {
            return false; // Already favorited
        }
    }

    // Remove from favorites
    public function removeFavorite($user_id, $favorited_user_id) {
        $query = "DELETE FROM user_favorites WHERE user_id = :user_id AND favorited_user_id = :favorited_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':favorited_id', $favorited_user_id);
        return $stmt->execute();
    }

    // Check if favorited
    public function isFavorited($user_id, $favorited_user_id) {
        $query = "SELECT id FROM user_favorites WHERE user_id = :user_id AND favorited_user_id = :favorited_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':favorited_id', $favorited_user_id);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    // Get favorites
    public function getFavorites($user_id, $limit = 50) {
        $query = "SELECT u.id, u.username, up.bio, up.body_type, up.relationship_status,
                         (SELECT file_path FROM user_photos WHERE user_id = u.id AND is_primary = TRUE LIMIT 1) as photo,
                         ul.latitude, ul.longitude, uf.created_at as favorited_at
                  FROM user_favorites uf
                  LEFT JOIN users u ON uf.favorited_user_id = u.id
                  LEFT JOIN user_profiles up ON u.id = up.user_id
                  LEFT JOIN user_locations ul ON u.id = ul.user_id
                  WHERE uf.user_id = :user_id
                  ORDER BY uf.created_at DESC
                  LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    // Block user
    public function blockUser($blocker_id, $blocked_id, $reason = null) {
        $query = "INSERT INTO user_blocks (blocker_id, blocked_id, reason) 
                  VALUES (:blocker_id, :blocked_id, :reason)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':blocker_id', $blocker_id);
        $stmt->bindParam(':blocked_id', $blocked_id);
        $stmt->bindParam(':reason', $reason);
        
        try {
            return $stmt->execute();
        } catch(PDOException $e) {
            return false;
        }
    }

    // Check if blocked
    public function isBlocked($blocker_id, $blocked_id) {
        $query = "SELECT id FROM user_blocks 
                  WHERE (blocker_id = :id1 AND blocked_id = :id2) 
                  OR (blocker_id = :id2 AND blocked_id = :id1) 
                  LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id1', $blocker_id);
        $stmt->bindParam(':id2', $blocked_id);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }
}
?>