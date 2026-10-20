<?php
/**
 * SecurityManager Class
 * Comprehensive security utilities for input sanitization, validation, and protection
 */
class SecurityManager {
    private $db;
    private $max_login_attempts = 5;
    private $lockout_time = 900; // 15 minutes
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Sanitize string input - removes dangerous characters
     */
    public function sanitizeString($input) {
        if ($input === null) return null;
        
        // Remove null bytes
        $input = str_replace(chr(0), '', $input);
        
        // Strip tags and encode special characters
        $input = strip_tags($input);
        $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Remove any remaining script tags
        $input = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $input);
        
        // Trim whitespace
        $input = trim($input);
        
        return $input;
    }
    
    /**
     * Sanitize HTML - allows safe HTML tags only
     */
    public function sanitizeHTML($input, $allowed_tags = '<p><br><b><i><u><strong><em><a><ul><ol><li>') {
        if ($input === null) return null;
        
        // Strip dangerous tags
        $input = strip_tags($input, $allowed_tags);
        
        // Remove dangerous attributes
        $input = preg_replace('/<(\w+)[^>]*\son\w+="[^"]*"/i', '<$1', $input);
        $input = preg_replace('/<(\w+)[^>]*\sstyle="[^"]*"/i', '<$1', $input);
        
        return $input;
    }
    
    /**
     * Sanitize email address
     */
    public function sanitizeEmail($email) {
        if ($email === null) return null;
        
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        return strtolower(trim($email));
    }
    
    /**
     * Sanitize URL
     */
    public function sanitizeURL($url) {
        if ($url === null) return null;
        
        $url = filter_var($url, FILTER_SANITIZE_URL);
        return $url;
    }
    
    /**
     * Sanitize integer
     */
    public function sanitizeInt($input) {
        return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
    }
    
    /**
     * Sanitize float
     */
    public function sanitizeFloat($input) {
        return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }
    
    /**
     * Sanitize filename - removes dangerous characters
     */
    public function sanitizeFilename($filename) {
        // Remove any path information
        $filename = basename($filename);
        
        // Remove dangerous characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
        
        // Prevent directory traversal
        $filename = str_replace('..', '', $filename);
        
        return $filename;
    }
    
    /**
     * Validate email address
     */
    public function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validate URL
     */
    public function validateURL($url) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    
    /**
     * Validate integer
     */
    public function validateInt($value, $min = null, $max = null) {
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            return false;
        }
        
        $int_value = (int)$value;
        
        if ($min !== null && $int_value < $min) return false;
        if ($max !== null && $int_value > $max) return false;
        
        return true;
    }
    
    /**
     * Generate CSRF token
     */
    public function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Verify CSRF token
     */
    public function verifyCSRFToken($token) {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Check if IP is blocked
     */
    public function isIPBlocked($ip) {
        try {
            $query = "SELECT COUNT(*) as count FROM blocked_ips 
                     WHERE ip_address = :ip AND (expires_at IS NULL OR expires_at > NOW())";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':ip', $ip);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result['count'] > 0;
        } catch (PDOException $e) {
            error_log("Error checking blocked IP: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Block IP address
     */
    public function blockIP($ip, $reason = 'Spam/Abuse', $duration = 86400) {
        try {
            $expires_at = date('Y-m-d H:i:s', time() + $duration);
            
            $query = "INSERT INTO blocked_ips (ip_address, reason, expires_at, created_at) 
                     VALUES (:ip, :reason, :expires, NOW())
                     ON DUPLICATE KEY UPDATE reason = :reason2, expires_at = :expires2, updated_at = NOW()";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':ip', $ip);
            $stmt->bindParam(':reason', $reason);
            $stmt->bindParam(':expires', $expires_at);
            $stmt->bindParam(':reason2', $reason);
            $stmt->bindParam(':expires2', $expires_at);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error blocking IP: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Unblock IP address
     */
    public function unblockIP($ip) {
        try {
            $query = "DELETE FROM blocked_ips WHERE ip_address = :ip";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':ip', $ip);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error unblocking IP: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Record failed login attempt
     */
    public function recordFailedLogin($identifier, $ip) {
        try {
            $query = "INSERT INTO login_attempts (identifier, ip_address, attempted_at) 
                     VALUES (:identifier, :ip, NOW())";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':identifier', $identifier);
            $stmt->bindParam(':ip', $ip);
            $stmt->execute();
            
            // Check if account should be locked
            $this->checkLoginAttempts($identifier, $ip);
        } catch (PDOException $e) {
            error_log("Error recording failed login: " . $e->getMessage());
        }
    }
    
    /**
     * Check login attempts and lock if necessary
     */
    private function checkLoginAttempts($identifier, $ip) {
        try {
            $query = "SELECT COUNT(*) as count FROM login_attempts 
                     WHERE (identifier = :identifier OR ip_address = :ip) 
                     AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':identifier', $identifier);
            $stmt->bindParam(':ip', $ip);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['count'] >= $this->max_login_attempts) {
                $this->blockIP($ip, 'Too many failed login attempts', $this->lockout_time);
            }
        } catch (PDOException $e) {
            error_log("Error checking login attempts: " . $e->getMessage());
        }
    }
    
    /**
     * Clear successful login attempts
     */
    public function clearLoginAttempts($identifier, $ip) {
        try {
            $query = "DELETE FROM login_attempts 
                     WHERE identifier = :identifier OR ip_address = :ip";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':identifier', $identifier);
            $stmt->bindParam(':ip', $ip);
            $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error clearing login attempts: " . $e->getMessage());
        }
    }
    
    /**
     * Get client IP address
     */
    public function getClientIP() {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER)) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Rate limiting check
     */
    public function checkRateLimit($action, $identifier, $max_requests = 10, $time_window = 60) {
        try {
            $query = "SELECT COUNT(*) as count FROM rate_limits 
                     WHERE action = :action AND identifier = :identifier 
                     AND created_at > DATE_SUB(NOW(), INTERVAL :window SECOND)";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':action', $action);
            $stmt->bindParam(':identifier', $identifier);
            $stmt->bindParam(':window', $time_window, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['count'] >= $max_requests) {
                return false;
            }
            
            // Record this request
            $insert = "INSERT INTO rate_limits (action, identifier, created_at) 
                      VALUES (:action, :identifier, NOW())";
            $stmt = $this->db->prepare($insert);
            $stmt->bindParam(':action', $action);
            $stmt->bindParam(':identifier', $identifier);
            $stmt->execute();
            
            return true;
        } catch (PDOException $e) {
            error_log("Error checking rate limit: " . $e->getMessage());
            return true; // Allow on error
        }
    }
    
    /**
     * Password strength validation
     */
    public function validatePasswordStrength($password) {
        $errors = [];
        
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long';
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        }
        
        return $errors;
    }
    
    /**
     * Sanitize array recursively
     */
    public function sanitizeArray($array) {
        $cleaned = [];
        foreach ($array as $key => $value) {
            $key = $this->sanitizeString($key);
            if (is_array($value)) {
                $cleaned[$key] = $this->sanitizeArray($value);
            } else {
                $cleaned[$key] = $this->sanitizeString($value);
            }
        }
        return $cleaned;
    }
}