<?php
session_start();
require_once 'config/database.php';
require_once 'includes/maintenance_check.php';
require_once 'includes/session_security.php';
require_once 'includes/security_headers.php';
require_once 'classes/CSRF.php';
require_once 'classes/RateLimiter.php';
require_once 'classes/InputSanitizer.php';
require_once 'classes/SpamProtection.php';

// Set security headers
SecurityHeaders::set();

// Initialize secure session
SessionSecurity::init();

$database = new Database();
$db = $database->getConnection();

// Check for maintenance mode
if(checkMaintenanceMode($db)) {
    header('Location: maintenance.php');
    exit();
}

// If already logged in, redirect
if(isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Initialize security classes
$rateLimiter = new RateLimiter($db);
$spamProtection = new SpamProtection($db);

$error = '';

// Check if IP is blocked
$ipCheck = $spamProtection->isBlocked();
if($ipCheck) {
    $error = 'Access denied. ' . ($ipCheck['reason'] ?? 'Please contact support.');
}

if($_SERVER['REQUEST_METHOD'] == 'POST' && !$ipCheck) {
    // Check CSRF token
    if(!isset($_POST['csrf_token']) || !CSRF::validateToken($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        // Check rate limit
        $identifier = RateLimiter::getIdentifier();
        $rateCheck = $rateLimiter->checkLimit($identifier, 'login', 5, 900); // 5 attempts per 15 minutes
        
        if(!$rateCheck['allowed']) {
            $minutes = ceil($rateCheck['retry_after'] / 60);
            $error = "Too many login attempts. Please try again in {$minutes} minutes.";
            
            // Block IP after too many attempts
            if($rateCheck['retry_after'] > 3600) {
                $spamProtection->blockIP(
                    RateLimiter::getClientIP(), 
                    'Excessive login attempts', 
                    $rateCheck['retry_after']
                );
            }
        } else {
            $username = InputSanitizer::cleanString($_POST['username'], 50);
            $password = $_POST['password'];
            
            if(empty($username) || empty($password)) {
                $error = 'Please enter both username and password';
            } else {
                // Check for SQL injection attempts
                if(InputSanitizer::detectSQLInjection($username)) {
                    $error = 'Invalid login attempt detected';
                    $spamProtection->blockIP(RateLimiter::getClientIP(), 'SQL injection attempt', 86400);
                } else {
                    $query = "SELECT id, username, email, password, is_suspended, is_banned, email_verified 
                              FROM users 
                              WHERE username = :username OR email = :email 
                              LIMIT 1";
                    
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':username', $username);
                    $stmt->bindParam(':email', $username);
                    $stmt->execute();
                    
                    $user = $stmt->fetch();
                    
                    if($user && password_verify($password, $user['password'])) {
                        // Check if account is suspended or banned
                        if($user['is_banned']) {
                            $error = 'Your account has been permanently banned. Contact support for more information.';
                        } elseif($user['is_suspended']) {
                            $error = 'Your account is currently suspended. Contact support for more information.';
                        } else {
                            // Successful login
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['username'] = $user['username'];
                            $_SESSION['email'] = $user['email'];
                            $_SESSION['email_verified'] = (bool)$user['email_verified'];
                            
                            // Update last login
                            $ip = RateLimiter::getClientIP();
                            $query = "UPDATE users 
                                     SET last_login = NOW(), last_ip = :ip, is_online = TRUE 
                                     WHERE id = :user_id";
                            $stmt = $db->prepare($query);
                            $stmt->bindParam(':ip', $ip);
                            $stmt->bindParam(':user_id', $user['id']);
                            $stmt->execute();
                            
                            // Reset rate limit for this user
                            $rateLimiter->checkLimit($user['id'], 'login_reset', 999, 1);
                            
                            // Destroy CSRF token
                            CSRF::destroyToken();
                            
                            // Redirect
                            $redirect = $_GET['redirect'] ?? 'choose-location.php';
                            $redirect = filter_var($redirect, FILTER_SANITIZE_URL);
                            header('Location: ' . $redirect);
                            exit();
                        }
                    } else {
                        $error = 'Invalid username or password';
                    }
                }
            }
        }
    }
}

// Generate new CSRF token
$csrf_token = CSRF::getToken();

$auth_title = 'Log in';
include 'views/auth_header.php';
?>

<div class="auth-wrap">
    <div class="container-narrow">
        <div class="auth-card">
            <div class="auth-head">
                <img src="/logo.png" alt="Turnpage" class="auth-logo">
                <h2>Welcome Back</h2>
                <p class="muted mt-1.5">
                    Login to your Turnpage account
                </p>
            </div>

            <?php if($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="login.php<?php echo isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''; ?>">
                <?php echo CSRF::getHiddenInput(); ?>
                
                <div class="form-group">
                    <label>Username or Email</label>
                    <input type="text" name="username" required 
                           placeholder="Enter your username or email"
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                           autocomplete="username">
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required 
                           placeholder="Enter your password"
                           autocomplete="current-password">
                </div>

                <div class="auth-row">
                    <label class="check">
                        <input type="checkbox" name="remember">
                        Remember me
                    </label>
                    <a href="forgot-password.php" class="text-sm">
                        Forgot password?
                    </a>
                </div>

                <button type="submit" class="btn btn-primary w-full">
                    Login
                </button>

                <p class="muted mt-6 text-center">
                    Don't have an account? 
                    <a href="register-tos.php">Sign up here</a>
                </p>
            </form>
        </div>
        
        <div class="muted mt-8 text-center text-sm">
            <p>🔒 Your connection is secure and encrypted</p>
        </div>
    </div>
</div>

<?php include 'views/auth_footer.php'; ?>