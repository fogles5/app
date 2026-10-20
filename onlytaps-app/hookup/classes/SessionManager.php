<?php
/**
 * SessionManager Class
 * Secure session management with hijacking prevention and timeout handling
 */
class SessionManager {
    private $db;
    private $session_timeout = 3600; // 1 hour
    private $absolute_timeout = 28800; // 8 hours
    private $regenerate_interval = 600; // 10 minutes
    
    public function __construct($db = null) {
        $this->db = $db;
    }
    
    /**
     * Initialize secure session
     */
    public function init() {
        // Set secure session cookie parameters
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        $httponly = true;
        $samesite = 'Lax';
        
        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => $_SERVER['HTTP_HOST'] ?? '',
                'secure' => $secure,
                'httponly' => $httponly,
                'samesite' => $samesite
            ]);
        } else {
            session_set_cookie_params(0, '/', $_SERVER['HTTP_HOST'] ?? '', $secure, $httponly);
        }
        
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Initialize session security
        $this->initSecurity();
        
        // Check for session hijacking
        if (!$this->validateSession()) {
            $this->destroy();
            return false;
        }
        
        // Check timeouts
        if (!$this->checkTimeout()) {
            $this->destroy();
            return false;
        }
        
        // Regenerate session ID periodically
        $this->periodicRegeneration();
        
        return true;
    }
    
    /**
     * Initialize session security variables
     */
    private function initSecurity() {
        if (!isset($_SESSION['_security'])) {
            $_SESSION['_security'] = [
                'created' => time(),
                'last_activity' => time(),
                'last_regeneration' => time(),
                'fingerprint' => $this->generateFingerprint(),
                'ip' => $this->getClientIP(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ];
        }
    }
    
    /**
     * Validate session integrity
     */
    private function validateSession() {
        if (!isset($_SESSION['_security'])) {
            return false;
        }
        
        $security = $_SESSION['_security'];
        
        // Check fingerprint
        if ($security['fingerprint'] !== $this->generateFingerprint()) {
            error_log('Session hijacking attempt detected: Fingerprint mismatch');
            return false;
        }
        
        // Check IP (allow same subnet for mobile users)
        $current_ip = $this->getClientIP();
        if (!$this->isSameSubnet($security['ip'], $current_ip)) {
            error_log('Session hijacking attempt detected: IP change from ' . $security['ip'] . ' to ' . $current_ip);
            return false;
        }
        
        // Check user agent
        $current_ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if ($security['user_agent'] !== $current_ua) {
            error_log('Session hijacking attempt detected: User agent change');
            return false;
        }
        
        return true;
    }
    
    /**
     * Check session timeouts
     */
    private function checkTimeout() {
        if (!isset($_SESSION['_security'])) {
            return false;
        }
        
        $security = $_SESSION['_security'];
        $current_time = time();
        
        // Check absolute timeout
        if (($current_time - $security['created']) > $this->absolute_timeout) {
            error_log('Session expired: Absolute timeout reached');
            return false;
        }
        
        // Check inactivity timeout
        if (($current_time - $security['last_activity']) > $this->session_timeout) {
            error_log('Session expired: Inactivity timeout');
            return false;
        }
        
        // Update last activity
        $_SESSION['_security']['last_activity'] = $current_time;
        
        return true;
    }
    
    /**
     * Periodic session ID regeneration
     */
    private function periodicRegeneration() {
        if (!isset($_SESSION['_security'])) {
            return;
        }
        
        $current_time = time();
        $last_regen = $_SESSION['_security']['last_regeneration'];
        
        if (($current_time - $last_regen) > $this->regenerate_interval) {
            $this->regenerate();
        }
    }
    
    /**
     * Regenerate session ID
     */
    public function regenerate($delete_old = true) {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id($delete_old);
            
            if (isset($_SESSION['_security'])) {
                $_SESSION['_security']['last_regeneration'] = time();
            }
        }
    }
    
    /**
     * Generate session fingerprint
     */
    private function generateFingerprint() {
        $data = [
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
            $_SERVER['HTTP_ACCEPT_ENCODING'] ?? ''
        ];
        
        return hash('sha256', implode('|', $data));
    }
    
    /**
     * Get client IP address
     */
    private function getClientIP() {
        $ip_keys = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER)) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Check if IPs are in same subnet (allows for mobile switching)
     */
    private function isSameSubnet($ip1, $ip2) {
        // Allow exact match
        if ($ip1 === $ip2) {
            return true;
        }
        
        // For IPv4, check if same /24 subnet
        if (filter_var($ip1, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && 
            filter_var($ip2, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts1 = explode('.', $ip1);
            $parts2 = explode('.', $ip2);
            
            // Same /24 subnet (first 3 octets)
            return ($parts1[0] === $parts2[0] && 
                    $parts1[1] === $parts2[1] && 
                    $parts1[2] === $parts2[2]);
        }
        
        // For IPv6 or mixed, require exact match
        return false;
    }
    
    /**
     * Set session variable
     */
    public function set($key, $value) {
        $_SESSION[$key] = $value;
    }
    
    /**
     * Get session variable
     */
    public function get($key, $default = null) {
        return $_SESSION[$key] ?? $default;
    }
    
    /**
     * Check if session variable exists
     */
    public function has($key) {
        return isset($_SESSION[$key]);
    }
    
    /**
     * Remove session variable
     */
    public function remove($key) {
        unset($_SESSION[$key]);
    }
    
    /**
     * Destroy session completely
     */
    public function destroy() {
        $_SESSION = [];
        
        // Delete session cookie
        if (isset($_COOKIE[session_name()])) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        
        // Destroy session
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
    
    /**
     * Set flash message
     */
    public function setFlash($key, $message) {
        if (!isset($_SESSION['_flash'])) {
            $_SESSION['_flash'] = [];
        }
        $_SESSION['_flash'][$key] = $message;
    }
    
    /**
     * Get and remove flash message
     */
    public function getFlash($key, $default = null) {
        if (!isset($_SESSION['_flash'][$key])) {
            return $default;
        }
        
        $message = $_SESSION['_flash'][$key];
        unset($_SESSION['_flash'][$key]);
        
        return $message;
    }
    
    /**
     * Check if user is logged in
     */
    public function isLoggedIn() {
        return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
    }
    
    /**
     * Login user
     */
    public function login($user_id, $remember = false) {
        // Regenerate session ID on login to prevent session fixation
        $this->regenerate(true);
        
        // Set user ID
        $_SESSION['user_id'] = $user_id;
        $_SESSION['logged_in_at'] = time();
        
        // Update security info
        if (isset($_SESSION['_security'])) {
            $_SESSION['_security']['last_activity'] = time();
        }
        
        // Set remember me cookie if requested
        if ($remember && $this->db) {
            $this->setRememberMeCookie($user_id);
        }
        
        // Log successful login
        if ($this->db) {
            $this->logLogin($user_id, true);
        }
    }
    
    /**
     * Logout user
     */
    public function logout() {
        $user_id = $_SESSION['user_id'] ?? null;
        
        // Remove remember me cookie
        if ($this->db && $user_id) {
            $this->removeRememberMeCookie($user_id);
        }
        
        // Destroy session
        $this->destroy();
    }
    
    /**
     * Set remember me cookie
     */
    private function setRememberMeCookie($user_id) {
        $token = bin2hex(random_bytes(32));
        $expires = time() + (30 * 24 * 60 * 60); // 30 days
        
        try {
            $query = "INSERT INTO remember_tokens (user_id, token, expires_at) 
                     VALUES (:user_id, :token, FROM_UNIXTIME(:expires))";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':user_id' => $user_id,
                ':token' => hash('sha256', $token),
                ':expires' => $expires
            ]);
            
            setcookie('remember_token', $token, $expires, '/', '', true, true);
        } catch (PDOException $e) {
            error_log("Error setting remember me cookie: " . $e->getMessage());
        }
    }
    
    /**
     * Remove remember me cookie
     */
    private function removeRememberMeCookie($user_id) {
        if (isset($_COOKIE['remember_token'])) {
            try {
                $query = "DELETE FROM remember_tokens WHERE user_id = :user_id";
                $stmt = $this->db->prepare($query);
                $stmt->execute([':user_id' => $user_id]);
            } catch (PDOException $e) {
                error_log("Error removing remember me cookie: " . $e->getMessage());
            }
            
            setcookie('remember_token', '', time() - 3600, '/', '', true, true);
        }
    }
    
    /**
     * Log login attempt
     */
    private function logLogin($user_id, $success) {
        try {
            $query = "INSERT INTO login_history (user_id, ip_address, user_agent, success, created_at) 
                     VALUES (:user_id, :ip, :ua, :success, NOW())";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':user_id' => $user_id,
                ':ip' => $this->getClientIP(),
                ':ua' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                ':success' => $success ? 1 : 0
            ]);
        } catch (PDOException $e) {
            error_log("Error logging login: " . $e->getMessage());
        }
    }
}