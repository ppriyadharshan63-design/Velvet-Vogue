<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitizeInput($_POST['name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $subject = sanitizeInput($_POST['subject'] ?? '');
    $message_text = sanitizeInput($_POST['message'] ?? '');
    
    if ($name && $email && $subject && $message_text) {
        // Insert contact message
        $sql = "INSERT INTO contact_messages (name, email, subject, message, created_at) VALUES (?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $name, $email, $subject, $message_text);
        
        if ($stmt->execute()) {
            $message = 'Thank you for your message! We will get back to you soon.';
            $message_type = 'success';
        } else {
            $message = 'Sorry, there was an error sending your message. Please try again.';
            $message_type = 'error';
        }
    } else {
        $message = 'Please fill in all required fields.';
        $message_type = 'error';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - Velvet Vogue</title>
    <meta name="description" content="Get in touch with Velvet Vogue. Contact us for customer support, product inquiries, or any questions about our fashion collections.">
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <!-- Page Header -->
    <section style="padding: 6rem 0 2rem; background: #f8f9fa; text-align: center;">
        <div class="container">
            <h1 style="font-size: 2.5rem; margin-bottom: 1rem; color: #2c3e50;">Contact Us</h1>
            <p style="color: #666; font-size: 1.1rem;">We'd love to hear from you. Send us a message and we'll respond as soon as possible.</p>
        </div>
    </section>

    <!-- Contact Form Section -->
    <section style="padding: 4rem 0;">
        <div class="container">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 4rem; align-items: start;">
                
                <!-- Contact Form -->
                <div>
                    <h2 style="margin-bottom: 2rem; color: #2c3e50;">Send us a Message</h2>
                    
                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $message_type; ?>">
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="contact.php">
                        <div class="form-group">
                            <label for="name">Full Name *</label>
                            <input type="text" id="name" name="name" required 
                                   value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" required 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="subject">Subject *</label>
                            <select id="subject" name="subject" required>
                                <option value="">Choose a subject</option>
                                <option value="Product Inquiry" <?php echo ($_POST['subject'] ?? '') === 'Product Inquiry' ? 'selected' : ''; ?>>Product Inquiry</option>
                                <option value="Order Support" <?php echo ($_POST['subject'] ?? '') === 'Order Support' ? 'selected' : ''; ?>>Order Support</option>
                                <option value="Shipping Question" <?php echo ($_POST['subject'] ?? '') === 'Shipping Question' ? 'selected' : ''; ?>>Shipping Question</option>
                                <option value="Return/Exchange" <?php echo ($_POST['subject'] ?? '') === 'Return/Exchange' ? 'selected' : ''; ?>>Return/Exchange</option>
                                <option value="General Inquiry" <?php echo ($_POST['subject'] ?? '') === 'General Inquiry' ? 'selected' : ''; ?>>General Inquiry</option>
                                <option value="Other" <?php echo ($_POST['subject'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="message">Message *</label>
                            <textarea id="message" name="message" rows="6" required 
                                      placeholder="Please provide details about your inquiry..."><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Send Message
                        </button>
                    </form>
                </div>
                
                <!-- Contact Information -->
                <div>
                    <h2 style="margin-bottom: 2rem; color: #2c3e50;">Get in Touch</h2>
                    
                    <div style="background: #f8f9fa; padding: 2rem; border-radius: 10px; margin-bottom: 2rem;">
                        <div style="margin-bottom: 2rem;">
                            <h3 style="color: #8b5a3c; margin-bottom: 1rem;">
                                <i class="fas fa-map-marker-alt"></i> Store Location
                            </h3>
                            <p style="color: #666; line-height: 1.6;">
                                123 Fashion Avenue<br>
                                Style District, NY 10001<br>
                                United States
                            </p>
                        </div>
                        
                        <div style="margin-bottom: 2rem;">
                            <h3 style="color: #8b5a3c; margin-bottom: 1rem;">
                                <i class="fas fa-phone"></i> Phone
                            </h3>
                            <p style="color: #666;">
                                <a href="tel:+1234567890" style="color: #666; text-decoration: none;">+1 (234) 567-8900</a>
                            </p>
                        </div>
                        
                        <div style="margin-bottom: 2rem;">
                            <h3 style="color: #8b5a3c; margin-bottom: 1rem;">
                                <i class="fas fa-envelope"></i> Email
                            </h3>
                            <p style="color: #666;">
                                <a href="mailto:info@velvetvogue.com" style="color: #666; text-decoration: none;">info@velvetvogue.com</a>
                            </p>
                        </div>
                        
                        <div>
                            <h3 style="color: #8b5a3c; margin-bottom: 1rem;">
                                <i class="fas fa-clock"></i> Store Hours
                            </h3>
                            <p style="color: #666; line-height: 1.6;">
                                Monday - Friday: 9:00 AM - 8:00 PM<br>
                                Saturday: 10:00 AM - 6:00 PM<br>
                                Sunday: 12:00 PM - 5:00 PM
                            </p>
                        </div>
                    </div>
                    
                    <div style="background: #2c3e50; color: white; padding: 2rem; border-radius: 10px;">
                        <h3 style="color: #8b5a3c; margin-bottom: 1rem;">Customer Support</h3>
                        <p style="margin-bottom: 1rem; line-height: 1.6;">
                            Our customer service team is here to help you with any questions about our products, 
                            orders, shipping, or returns.
                        </p>
                        <p style="font-weight: bold;">
                            Response Time: Within 24 hours
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section style="padding: 4rem 0; background: #f8f9fa;">
        <div class="container">
            <h2 style="text-align: center; margin-bottom: 3rem; color: #2c3e50;">Frequently Asked Questions</h2>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; max-width: 1000px; margin: 0 auto;">
                <div style="background: white; padding: 2rem; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    <h3 style="color: #8b5a3c; margin-bottom: 1rem;">Shipping & Delivery</h3>
                    <p style="color: #666; line-height: 1.6;">
                        We offer free shipping on orders over $50. Standard delivery takes 3-5 business days, 
                        and express delivery takes 1-2 business days.
                    </p>
                </div>
                
                <div style="background: white; padding: 2rem; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    <h3 style="color: #8b5a3c; margin-bottom: 1rem;">Returns & Exchanges</h3>
                    <p style="color: #666; line-height: 1.6;">
                        We accept returns within 30 days of purchase. Items must be unworn, unwashed, 
                        and in original condition with tags attached.
                    </p>
                </div>
                
                <div style="background: white; padding: 2rem; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    <h3 style="color: #8b5a3c; margin-bottom: 1rem;">Size Guide</h3>
                    <p style="color: #666; line-height: 1.6;">
                        Check our detailed size charts on each product page. If you're between sizes, 
                        we recommend sizing up for a comfortable fit.
                    </p>
                </div>
                
                <div style="background: white; padding: 2rem; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    <h3 style="color: #8b5a3c; margin-bottom: 1rem;">Payment Methods</h3>
                    <p style="color: #666; line-height: 1.6;">
                        We accept all major credit cards, PayPal, Apple Pay, and Google Pay. 
                        All transactions are secure and encrypted.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>

    <script src="assets/js/main.js"></script>
    <script>
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            if (!validateForm('contact-form')) {
                e.preventDefault();
                showMessage('Please fill in all required fields correctly.', 'error');
            }
        });
    </script>
</body>
</html>