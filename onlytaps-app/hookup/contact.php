<?php
session_start();
include 'views/header.php';

$success = '';
$error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = htmlspecialchars($_POST['name']);
    $email = htmlspecialchars($_POST['email']);
    $subject = htmlspecialchars($_POST['subject']);
    $message = htmlspecialchars($_POST['message']);
    
    // In production, send email or store in database
    // For now, just show success
    $success = 'Thank you for contacting us! We\'ll get back to you within 24-48 hours.';
}
?>

<div class="page-content">
    <div class="container-narrow">
        <div class="card" style="max-width: 600px; margin: 0 auto;">
            <h1 style="text-align: center; margin-bottom: 2rem;">Contact Us</h1>
            
            <?php if($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="contact.php">
                <div class="form-group">
                    <label>Name *</label>
                    <input type="text" name="name" required>
                </div>
                
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" required value="<?php echo isset($_SESSION['email']) ? htmlspecialchars($_SESSION['email']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label>Subject *</label>
                    <select name="subject" required>
                        <option value="">Select a subject</option>
                        <option value="general">General Inquiry</option>
                        <option value="technical">Technical Support</option>
                        <option value="account">Account Issues</option>
                        <option value="billing">Billing Question</option>
                        <option value="feature">Feature Request</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Message *</label>
                    <textarea name="message" rows="6" required placeholder="Please describe your issue or question in detail..."></textarea>
                </div>
                
                <button type="submit" class="btn-primary btn-block">Send Message</button>
            </form>
            
            <div style="margin-top: 3rem; padding-top: 2rem; border-top: 1px solid var(--border-color); text-align: center;">
                <h3 style="margin-bottom: 1rem;">Other Ways to Reach Us</h3>
                <p style="color: var(--text-gray);"><strong>Email:</strong> support@doublelist-clone.com</p>
                <p style="color: var(--text-gray);"><strong>Response Time:</strong> 24-48 hours</p>
                <p style="color: var(--text-gray);"><strong>Hours:</strong> Monday - Friday, 9AM - 5PM PST</p>
            </div>
        </div>
    </div>
</div>

<?php include 'views/footer.php'; ?>