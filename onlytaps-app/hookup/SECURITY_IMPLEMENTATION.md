# Security Implementation Guide

Comprehensive security enhancements for the Turnpage application.

## 📋 Table of Contents

1. [Installation](#installation)
2. [SecurityManager Usage](#securitymanager-usage)
3. [ImageUploader Usage](#imageuploader-usage)
4. [SessionManager Usage](#sessionmanager-usage)
5. [Integration Examples](#integration-examples)
6. [Best Practices](#best-practices)

---

## 🚀 Installation

### Step 1: Run Database Migration

```sql
-- Execute the security tables migration
source database/migrations/security_tables.sql;
```

### Step 2: Create Uploads Directory

```bash
mkdir -p uploads/images/avatars
chmod 755 uploads
chmod 755 uploads/images
chmod 755 uploads/images/avatars
```

### Step 3: Update .htaccess (Apache)

```apache
# Prevent direct access to PHP files in uploads
<FilesMatch "\.php$">
    Order Deny,Allow
    Deny from all
</FilesMatch>
```

---

## 🛡️ SecurityManager Usage

### Initialize SecurityManager

```php
require_once 'classes/SecurityManager.php';

$database = new Database();
$db = $database->getConnection();
$security = new SecurityManager($db);
```

### Input Sanitization

```php
// Sanitize user input
$username = $security->sanitizeString($_POST['username']);
$email = $security->sanitizeEmail($_POST['email']);
$bio = $security->sanitizeHTML($_POST['bio']);
$age = $security->sanitizeInt($_POST['age']);
$website = $security->sanitizeURL($_POST['website']);

// Sanitize entire array
$clean_data = $security->sanitizeArray($_POST);
```

### Input Validation

```php
// Validate email
if (!$security->validateEmail($email)) {
    die('Invalid email address');
}

// Validate integer with range
if (!$security->validateInt($age, 18, 100)) {
    die('Age must be between 18 and 100');
}

// Validate URL
if (!$security->validateURL($website)) {
    die('Invalid website URL');
}
```

### CSRF Protection

```php
// Generate token (in form)
$csrf_token = $security->generateCSRFToken();
?>
<form method="POST">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <!-- form fields -->
</form>
<?php

// Verify token (on submission)
if (!$security->verifyCSRFToken($_POST['csrf_token'])) {
    die('Invalid CSRF token');
}
```

### IP Blocking

```php
// Get client IP
$ip = $security->getClientIP();

// Check if IP is blocked
if ($security->isIPBlocked($ip)) {
    die('Your IP has been blocked due to suspicious activity');
}

// Block an IP (24 hours)
$security->blockIP($ip, 'Spam detected', 86400);

// Unblock an IP
$security->unblockIP($ip);
```

### Login Attempt Tracking

```php
// Record failed login
$security->recordFailedLogin($email, $ip);

// Clear attempts on successful login
$security->clearLoginAttempts($email, $ip);
```

### Rate Limiting

```php
// Check rate limit (10 requests per 60 seconds)
if (!$security->checkRateLimit('api_call', $user_id, 10, 60)) {
    http_response_code(429);
    die('Rate limit exceeded. Please try again later.');
}
```

### Password Validation

```php
$errors = $security->validatePasswordStrength($password);
if (!empty($errors)) {
    foreach ($errors as $error) {
        echo $error . "<br>";
    }
}
```

---

## 📸 ImageUploader Usage

### Initialize ImageUploader

```php
require_once 'classes/ImageUploader.php';

$uploader = new ImageUploader('uploads/images');
```

### Upload Profile Avatar

```php
if (isset($_FILES['avatar'])) {
    $result = $uploader->uploadAvatar($_FILES['avatar'], $user_id);
    
    if ($result['success']) {
        // Update database
        $query = "UPDATE users SET avatar = :avatar WHERE id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':avatar' => $result['url'],
            ':user_id' => $user_id
        ]);
        
        echo "Avatar uploaded: " . $result['url'];
        echo "Thumbnail: " . $result['thumbnail'];
    } else {
        echo "Error: " . $result['error'];
    }
}
```

### Upload Regular Image

```php
if (isset($_FILES['image'])) {
    // Upload with automatic resizing
    $result = $uploader->upload($_FILES['image'], 'listings', true);
    
    if ($result['success']) {
        echo "Uploaded: " . $result['url'];
        echo "Size: " . $result['size'] . " bytes";
    } else {
        echo "Error: " . $result['error'];
    }
}
```

### Create Thumbnail

```php
// Create 300x300 square thumbnail with crop
$thumbnail_url = $uploader->createThumbnail(
    '/uploads/images/photo.jpg',
    300,
    300,
    true // crop to square
);
```

### Delete Image

```php
// Delete image and thumbnail
$uploader->delete('/uploads/images/photo.jpg');
```

---

## 🔐 SessionManager Usage

### Initialize SessionManager

```php
require_once 'classes/SessionManager.php';

$database = new Database();
$db = $database->getConnection();
$session = new SessionManager($db);

// Initialize secure session
if (!$session->init()) {
    // Session invalid or expired
    header('Location: login.php');
    exit();
}
```

### User Login

```php
// Login user with remember me option
$session->login($user_id, $remember = true);
```

### User Logout

```php
// Logout and destroy session
$session->logout();
```

### Session Data Management

```php
// Set session data
$session->set('user_role', 'admin');
$session->set('preferences', ['theme' => 'dark']);

// Get session data
$role = $session->get('user_role');
$theme = $session->get('preferences')['theme'] ?? 'light';

// Check if exists
if ($session->has('user_role')) {
    // ...
}

// Remove session data
$session->remove('temp_data');
```

### Flash Messages

```php
// Set flash message
$session->setFlash('success', 'Profile updated successfully!');

// Get and display flash message (only once)
if ($message = $session->getFlash('success')) {
    echo '<div class="alert alert-success">' . $message . '</div>';
}
```

### Check Login Status

```php
if (!$session->isLoggedIn()) {
    header('Location: login.php');
    exit();
}
```

---

## 💡 Integration Examples

### Secure Login Form

```php
<?php
session_start();
require_once 'config/database.php';
require_once 'classes/SecurityManager.php';
require_once 'classes/SessionManager.php';

$database = new Database();
$db = $database->getConnection();
$security = new SecurityManager($db);
$session = new SessionManager($db);
$session->init();

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get client IP
    $ip = $security->getClientIP();
    
    // Check if IP is blocked
    if ($security->isIPBlocked($ip)) {
        $error = 'Too many failed attempts. Please try again later.';
    } else {
        // Verify CSRF token
        if (!$security->verifyCSRFToken($_POST['csrf_token'])) {
            $error = 'Invalid request';
        } else {
            // Sanitize input
            $email = $security->sanitizeEmail($_POST['email']);
            $password = $_POST['password'];
            $remember = isset($_POST['remember']);
            
            // Validate email
            if (!$security->validateEmail($email)) {
                $error = 'Invalid email address';
            } else {
                // Check credentials
                $query = "SELECT id, password FROM users WHERE email = :email LIMIT 1";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':email', $email);
                $stmt->execute();
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user && password_verify($password, $user['password'])) {
                    // Successful login
                    $security->clearLoginAttempts($email, $ip);
                    $session->login($user['id'], $remember);
                    header('Location: dashboard.php');
                    exit();
                } else {
                    // Failed login
                    $security->recordFailedLogin($email, $ip);
                    $error = 'Invalid email or password';
                }
            }
        }
    }
}

$csrf_token = $security->generateCSRFToken();
?>

<form method="POST">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <input type="email" name="email" required>
    <input type="password" name="password" required>
    <label>
        <input type="checkbox" name="remember"> Remember me
    </label>
    <button type="submit">Login</button>
</form>
```

### Secure File Upload Form

```php
<?php
session_start();
require_once 'config/database.php';
require_once 'classes/SecurityManager.php';
require_once 'classes/ImageUploader.php';
require_once 'classes/SessionManager.php';

$database = new Database();
$db = $database->getConnection();
$security = new SecurityManager($db);
$uploader = new ImageUploader('uploads/images');
$session = new SessionManager($db);

if (!$session->init() || !$session->isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$user_id = $session->get('user_id');
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF
    if (!$security->verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request';
    } 
    // Rate limit uploads
    elseif (!$security->checkRateLimit('upload', $user_id, 5, 300)) {
        $error = 'Upload limit exceeded. Please wait before uploading again.';
    } 
    // Process upload
    elseif (isset($_FILES['photo'])) {
        $result = $uploader->uploadAvatar($_FILES['photo'], $user_id);
        
        if ($result['success']) {
            // Update user profile
            $query = "UPDATE users SET avatar = :avatar WHERE id = :user_id";
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':avatar' => $result['url'],
                ':user_id' => $user_id
            ]);
            
            $success = 'Profile photo updated successfully!';
        } else {
            $error = $result['error'];
        }
    }
}

$csrf_token = $security->generateCSRFToken();
?>

<form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <input type="file" name="photo" accept="image/*" required>
    <button type="submit">Upload Photo</button>
</form>
```

---

## ✅ Best Practices

### 1. Always Sanitize User Input

```php
// BAD
$username = $_POST['username'];

// GOOD
$username = $security->sanitizeString($_POST['username']);
```

### 2. Use Prepared Statements

```php
// BAD
$query = "SELECT * FROM users WHERE id = " . $_GET['id'];

// GOOD
$query = "SELECT * FROM users WHERE id = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $id, PDO::PARAM_INT);
```

### 3. Implement CSRF Protection

Always use CSRF tokens for state-changing operations (POST, PUT, DELETE).

### 4. Rate Limit Sensitive Operations

```php
// Login attempts
// Password resets
// File uploads
// API calls
```

### 5. Log Security Events

```php
error_log("Failed login attempt from IP: $ip for email: $email");
```

### 6. Regular Security Maintenance

```php
// Clean up old data regularly (cron job)
// DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 24 HOUR);
// DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR);
// DELETE FROM blocked_ips WHERE expires_at < NOW();
```

### 7. Monitor for Suspicious Activity

- Multiple failed login attempts
- Rapid file uploads
- Unusual IP addresses
- Session hijacking attempts

### 8. Keep Software Updated

- PHP version
- Database server
- Third-party libraries
- Operating system

---

## 🔒 Security Checklist

- [x] Input sanitization implemented
- [x] SQL injection prevention (prepared statements)
- [x] XSS protection (HTML encoding)
- [x] CSRF protection
- [x] Session security (hijacking prevention)
- [x] Rate limiting
- [x] IP blocking
- [x] Secure file uploads
- [x] Password strength validation
- [x] Login attempt tracking
- [ ] HTTPS enabled (configure on server)
- [ ] Security headers configured
- [ ] Regular backups scheduled
- [ ] Monitoring/alerting set up

---

## 📞 Support

For security issues, please report privately to security@turnpage.io

---

**Last Updated:** November 28, 2025