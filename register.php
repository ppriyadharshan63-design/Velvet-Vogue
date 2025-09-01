<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: account.php');
    exit;
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = sanitizeInput($_POST['first_name'] ?? '');
    $last_name = sanitizeInput($_POST['last_name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (!$first_name || !$last_name || !$email || !$password) {
        $message = 'Please fill in all required fields.';
        $message_type = 'error';
    } elseif ($password !== $confirm_password) {
        $message = 'Passwords do not match.';
        $message_type = 'error';
    } elseif (strlen($password) < 6) {
        $message = 'Password must be at least 6 characters long.';
        $message_type = 'error';
    } else {
        // Check if email already exists
        $check_sql = "SELECT id FROM users WHERE email = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $message = 'An account with this email already exists.';
            $message_type = 'error';
        } else {
            // Create new user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert_sql = "INSERT INTO users (email, password, first_name, last_name, phone, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("sssss", $email, $hashed_password, $first_name, $last_name, $phone);
            
            if ($insert_stmt->execute()) {
                $user_id = $conn->insert_id;
                
                // Auto login
                $_SESSION['user_id'] = $user_id;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_name'] = $first_name . ' ' . $last_name;
                $_SESSION['is_admin'] = false;
                
                // Merge guest cart if exists
                if (isset($_SESSION['guest_id'])) {
                    $guest_id = $_SESSION['guest_id'];
                    $transfer_sql = "UPDATE cart_items SET user_id = ? WHERE user_id = ?";
                    $transfer_stmt = $conn->prepare($transfer_sql);
                    $transfer_stmt->bind_param("ss", $user_id, $guest_id);
                    $transfer_stmt->execute();
                    unset($_SESSION['guest_id']);
                }
                
                $redirect = $_GET['redirect'] ?? 'account.php';
                header('Location: ' . $redirect);
                exit;
            } else {
                $message = 'Error creating account. Please try again.';
                $message_type = 'error';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - Velvet Vogue</title>
    <meta name="description" content="Create your Velvet Vogue account to enjoy personalized shopping, order tracking, and exclusive member benefits.">
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <section style="padding: 6rem 0 4rem; background: #f8f9fa; min-height: 100vh;">
        <div class="container">
            <div style="max-width: 500px; margin: 0 auto; background: white; padding: 3rem; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
                <h1 style="text-align: center; margin-bottom: 2rem; color: #2c3e50;">Create Account</h1>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="register.php<?php echo isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''; ?>">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">First Name *</label>
                            <input type="text" id="first_name" name="first_name" required 
                                   value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>"
                                   placeholder="First name">
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name">Last Name *</label>
                            <input type="text" id="last_name" name="last_name" required 
                                   value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>"
                                   placeholder="Last name">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" required 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                               placeholder="Enter your email">
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" 
                               value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                               placeholder="Optional: Your phone number">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="password">Password *</label>
                            <input type="password" id="password" name="password" required 
                                   placeholder="At least 6 characters"
                                   minlength="6">
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm Password *</label>
                            <input type="password" id="confirm_password" name="confirm_password" required 
                                   placeholder="Confirm password">
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 2rem; padding: 1rem; background: #f8f9fa; border-radius: 5px; font-size: 0.9rem; color: #666;">
                        <h4 style="margin-bottom: 0.5rem; color: #333;">Account Benefits:</h4>
                        <ul style="margin: 0; padding-left: 1.5rem;">
                            <li>Track your orders and view order history</li>
                            <li>Save items to your wishlist</li>
                            <li>Faster checkout with saved addresses</li>
                            <li>Exclusive member offers and early access</li>
                        </ul>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-bottom: 1rem;">
                        <i class="fas fa-user-plus"></i> Create Account
                    </button>
                </form>
                
                <div style="text-align: center; margin-top: 2rem;">
                    <p style="color: #666; margin-bottom: 1rem;">Already have an account?</p>
                    <a href="login.php<?php echo isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''; ?>" 
                       class="btn btn-outline" style="width: 100%;">
                        Sign In
                    </a>
                </div>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>
    
    <script src="assets/js/main.js"></script>
    <script>
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.style.borderColor = '#e74c3c';
            } else {
                this.style.borderColor = '#ddd';
            }
        });
    </script>
</body>
</html>