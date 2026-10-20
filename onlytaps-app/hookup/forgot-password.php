<?php
session_start();
require_once 'config/database.php';
require_once 'includes/maintenance_check.php';
require_once 'includes/session_security.php';
require_once 'includes/security_headers.php';
require_once 'classes/CSRF.php';
require_once 'classes/RateLimiter.php';
require_once 'classes/InputSanitizer.php';
require_once 'classes/SecurityLogger.php';

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

$rateLimiter = new RateLimiter($db);
$securityLogger = new SecurityLogger($db);

$error = '';
$success = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Check CSRF token
    if(!isset($_POST['csrf_token']) || !CSRF::validateToken($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        // Check rate limit
        $identifier = RateLimiter::getIdentifier();
        $rateCheck = $rateLimiter->checkLimit($identifier, 'forgot_password', 3, 3600); // 3 attempts per hour
        
        if(!$rateCheck['allowed']) {
            $minutes = ceil($rateCheck['retry_after'] / 60);
            $error = "Too many password reset attempts. Please try again in {$minutes} minutes.";
            $securityLogger->log('password_reset_rate_limit', "Identifier: {$identifier}", 'medium');
        } else {
            $email = InputSanitizer::cleanEmail($_POST['email']);
            
            if(!$email) {
                $error = 'Please enter a valid email address';
            } else {
                // Check if email exists (but don't reveal if it doesn't for security)
                $query = "SELECT id, username, email FROM users WHERE email = :email LIMIT 1";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':email', $email);
                $stmt->execute();
                
                $user = $stmt->fetch();
                
                if($user) {
                    // Generate password reset token
                    $token = bin2hex(random_bytes(32));
                    $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour
                    
                    // Create password_resets table if it doesn't exist
                    try {
                        $createTable = "CREATE TABLE IF NOT EXISTS password_resets (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            user_id INT NOT NULL,
                            email VARCHAR(255) NOT NULL,
                            token VARCHAR(64) NOT NULL UNIQUE,
                            expires_at TIMESTAMP NOT NULL,
                            used_at TIMESTAMP NULL,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                            INDEX idx_token (token),
                            INDEX idx_expires (expires_at)
                        )";
                        $db->exec($createTable);
                    } catch(PDOException $e) {
                        error_log("Error creating password_resets table: " . $e->getMessage());
                    }
                    
                    // Delete old tokens for this user
                    $query = "DELETE FROM password_resets WHERE user_id = :user_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':user_id', $user['id']);
                    $stmt->execute();
                    
                    // Insert new token
                    $query = "INSERT INTO password_resets (user_id, email, token, expires_at) 
                              VALUES (:user_id, :email, :token, :expires_at)";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':user_id', $user['id']);
                    $stmt->bindParam(':email', $email);
                    $stmt->bindParam(':token', $token);
                    $stmt->bindParam(':expires_at', $expires);
                    
                    if($stmt->execute()) {
                        // Send email
                        $resetUrl = "https://turnpage.io/reset-password.php?token=" . $token;
                        
                        $subject = "Password Reset Request - Turnpage";
                        
                        $message = "
                        <html>
                        <head>
                            <style>
                                body { font-family: Arial, sans-serif; background: #0A0F1E; color: #ffffff; }
                                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                                .header { background: linear-gradient(135deg, #4267F5, #1D9BF0); padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                                .content { background: #141B2E; padding: 30px; border-radius: 0 0 10px 10px; }
                                .button { display: inline-block; background: #4267F5; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; margin: 20px 0; }
                                .footer { text-align: center; color: #6B7280; margin-top: 20px; font-size: 12px; }
                            </style>
                        </head>
                        <body>
                            <div class='container'>
                                <div class='header'>
                                    <h1 style='margin: 0; color: white;'>Password Reset</h1>
                                </div>
                                <div class='content'>
                                    <h2>Hello {$user['username']},</h2>
                                    <p>We received a request to reset your password. Click the button below to create a new password:</p>
                                    <div style='text-align: center;'>
                                        <a href='{$resetUrl}' class='button'>Reset Password</a>
                                    </div>
                                    <p>Or copy and paste this link into your browser:</p>
                                    <p style='word-break: break-all; color: #4267F5;'>{$resetUrl}</p>
                                    <p style='margin-top: 30px; color: #9CA3AF;'><strong>Note:</strong> This link will expire in 1 hour.</p>
                                    <p style='color: #9CA3AF; font-size: 14px;'>If you didn't request a password reset, please ignore this email and your password will remain unchanged.</p>
                                </div>
                                <div class='footer'>
                                    <p>© 2025 Turnpage. All rights reserved.</p>
                                    <p>2261 Market Street #4626, San Francisco, CA 94114</p>
                                </div>
                            </div>
                        </body>
                        </html>
                        ";
                        
                        $headers = [
                            'MIME-Version: 1.0',
                            'Content-type: text/html; charset=utf-8',
                            'From: Turnpage <noreply@turnpage.io>',
                            'Reply-To: support@turnpage.io',
                            'X-Mailer: PHP/' . phpversion()
                        ];
                        
                        mail($email, $subject, $message, implode("\r\n", $headers));
                        
                        $securityLogger->log('password_reset_requested', "Email: {$email}", 'low', $user['id']);
                    }
                }
                
                // Always show success message (don't reveal if email exists)
                $success = 'If an account exists with that email, we\'ve sent password reset instructions.';
            }
        }
    }
}

// Generate CSRF token
$csrf_token = CSRF::getToken();

$auth_title = 'Forgot password';
include 'views/auth_header.php';
?>

<div class="auth-wrap">
    <div class="container-narrow">
        <div class="auth-card">
            <div class="auth-head">
                <div class="mb-3 text-5xl">🔐</div>
                <h2>Forgot Password?</h2>
                <p class="muted mt-1.5">
                    Enter your email and we'll send you a reset link
                </p>
            </div>

            <?php if($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if($success): ?>
            <div class="alert alert-success">
                <strong>✓ Email Sent!</strong><br>
                <?php echo htmlspecialchars($success); ?>
                <p class="mt-3 text-sm">
                    Check your inbox (and spam folder) for the password reset link.
                </p>
            </div>
            <div class="mt-6 text-center">
                <a href="login.php" class="btn btn-secondary">
                    ← Back to Login
                </a>
            </div>
            <?php else: ?>
            <form method="POST" action="forgot-password.php">
                <?php echo CSRF::getHiddenInput(); ?>
                
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" required 
                           placeholder="Enter your registered email"
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                           autocomplete="email">
                </div>

                <div class="alert alert-info">
                    <strong>📧 Password Reset Process:</strong>
                    <ol class="mt-2 list-decimal space-y-1 pl-6">
                        <li>Enter your email address</li>
                        <li>Check your inbox for the reset link</li>
                        <li>Click the link and create a new password</li>
                        <li>Link expires in 1 hour</li>
                    </ol>
                </div>

                <button type="submit" class="btn btn-primary w-full">
                    Send Reset Link
                </button>

                <p class="muted mt-6 text-center">
                    Remember your password? 
                    <a href="login.php">Login here</a>
                </p>
            </form>
            <?php endif; ?>
        </div>
        
        <div class="muted mt-8 text-center text-sm">
            <p>Need help? <a href="support.php">Contact Support</a></p>
        </div>
    </div>
</div>

<?php include 'views/auth_footer.php'; ?>