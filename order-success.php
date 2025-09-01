<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$order_number = $_GET['order'] ?? null;
$order = null;

if ($order_number) {
    $sql = "SELECT * FROM orders WHERE order_number = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $order_number);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
}

if (!$order) {
    header('Location: index.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - Velvet Vogue</title>
    <meta name="description" content="Order confirmation for your Velvet Vogue purchase">
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <section style="padding: 6rem 0 4rem; background: #f8f9fa; min-height: 100vh;">
        <div class="container">
            <div style="max-width: 800px; margin: 0 auto; text-align: center;">
                <!-- Success Icon -->
                <div style="background: #28a745; color: white; width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 2rem; font-size: 2rem;">
                    <i class="fas fa-check"></i>
                </div>

                <h1 style="color: #2c3e50; margin-bottom: 1rem;">Order Confirmed!</h1>
                <p style="color: #666; font-size: 1.1rem; margin-bottom: 2rem;">
                    Thank you for your purchase. Your order has been successfully placed and will be processed shortly.
                </p>

                <!-- Order Details Card -->
                <div class="card" style="text-align: left; margin-bottom: 2rem;">
                    <div class="card-header">
                        <h3 style="margin: 0; color: #2c3e50;">Order Details</h3>
                    </div>
                    <div class="card-body">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                            <div>
                                <h4 style="color: #8b5a3c; margin-bottom: 1rem;">Order Information</h4>
                                <p style="margin: 0.5rem 0;"><strong>Order Number:</strong> <?php echo htmlspecialchars($order['order_number']); ?></p>
                                <p style="margin: 0.5rem 0;"><strong>Order Date:</strong> <?php echo date('F j, Y', strtotime($order['created_at'])); ?></p>
                                <p style="margin: 0.5rem 0;"><strong>Status:</strong> 
                                    <span class="status-badge <?php echo getStatusBadgeClass($order['status']); ?>">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </p>
                                <p style="margin: 0.5rem 0;"><strong>Payment Method:</strong> <?php echo ucfirst(str_replace('_', ' ', $order['payment_method'])); ?></p>
                            </div>
                            <div>
                                <h4 style="color: #8b5a3c; margin-bottom: 1rem;">Order Total</h4>
                                <div style="background: #f8f9fa; padding: 1rem; border-radius: 5px;">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                        <span>Subtotal:</span>
                                        <span><?php echo formatPrice($order['subtotal']); ?></span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                        <span>Tax:</span>
                                        <span><?php echo formatPrice($order['tax_amount']); ?></span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 1rem;">
                                        <span>Shipping:</span>
                                        <span>
                                            <?php if ($order['shipping_amount'] == 0): ?>
                                                <span style="color: #28a745;">FREE</span>
                                            <?php else: ?>
                                                <?php echo formatPrice($order['shipping_amount']); ?>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; padding-top: 1rem; border-top: 2px solid #8b5a3c; font-size: 1.2rem; font-weight: bold;">
                                        <span>Total:</span>
                                        <span style="color: #8b5a3c;"><?php echo formatPrice($order['total_amount']); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Billing & Shipping Address -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                            <div>
                                <h4 style="color: #8b5a3c; margin-bottom: 1rem;">Billing Address</h4>
                                <div style="background: #f8f9fa; padding: 1rem; border-radius: 5px;">
                                    <p style="margin: 0; font-weight: bold;">
                                        <?php echo htmlspecialchars($order['billing_first_name'] . ' ' . $order['billing_last_name']); ?>
                                    </p>
                                    <p style="margin: 0.25rem 0;"><?php echo htmlspecialchars($order['billing_address']); ?></p>
                                    <p style="margin: 0.25rem 0;">
                                        <?php echo htmlspecialchars($order['billing_city'] . ', ' . $order['billing_state'] . ' ' . $order['billing_postal_code']); ?>
                                    </p>
                                    <p style="margin: 0.25rem 0;"><?php echo htmlspecialchars($order['billing_country']); ?></p>
                                    <?php if ($order['billing_email']): ?>
                                        <p style="margin: 0.25rem 0;"><?php echo htmlspecialchars($order['billing_email']); ?></p>
                                    <?php endif; ?>
                                    <?php if ($order['billing_phone']): ?>
                                        <p style="margin: 0.25rem 0;"><?php echo htmlspecialchars($order['billing_phone']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div>
                                <h4 style="color: #8b5a3c; margin-bottom: 1rem;">Shipping Address</h4>
                                <div style="background: #f8f9fa; padding: 1rem; border-radius: 5px;">
                                    <?php if ($order['shipping_first_name']): ?>
                                        <p style="margin: 0; font-weight: bold;">
                                            <?php echo htmlspecialchars($order['shipping_first_name'] . ' ' . $order['shipping_last_name']); ?>
                                        </p>
                                        <p style="margin: 0.25rem 0;"><?php echo htmlspecialchars($order['shipping_address']); ?></p>
                                        <p style="margin: 0.25rem 0;">
                                            <?php echo htmlspecialchars($order['shipping_city'] . ', ' . $order['shipping_state'] . ' ' . $order['shipping_postal_code']); ?>
                                        </p>
                                        <p style="margin: 0.25rem 0;"><?php echo htmlspecialchars($order['shipping_country']); ?></p>
                                    <?php else: ?>
                                        <p style="margin: 0; color: #666; font-style: italic;">Same as billing address</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- What's Next -->
                <div class="card" style="text-align: left; margin-bottom: 2rem;">
                    <div class="card-header">
                        <h3 style="margin: 0; color: #2c3e50;">What's Next?</h3>
                    </div>
                    <div class="card-body">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem;">
                            <div style="text-align: center;">
                                <div style="background: #8b5a3c; color: white; width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; font-size: 1.2rem;">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <h4 style="color: #2c3e50; margin-bottom: 0.5rem;">Order Confirmation</h4>
                                <p style="color: #666; margin: 0;">You'll receive an email confirmation shortly with your order details.</p>
                            </div>
                            <div style="text-align: center;">
                                <div style="background: #8b5a3c; color: white; width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; font-size: 1.2rem;">
                                    <i class="fas fa-cogs"></i>
                                </div>
                                <h4 style="color: #2c3e50; margin-bottom: 0.5rem;">Order Processing</h4>
                                <p style="color: #666; margin: 0;">We'll start preparing your order for shipment within 1-2 business days.</p>
                            </div>
                            <div style="text-align: center;">
                                <div style="background: #8b5a3c; color: white; width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; font-size: 1.2rem;">
                                    <i class="fas fa-truck"></i>
                                </div>
                                <h4 style="color: #2c3e50; margin-bottom: 0.5rem;">Shipping & Tracking</h4>
                                <p style="color: #666; margin: 0;">Once shipped, you'll receive tracking information to monitor your package.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                    <?php if (isLoggedIn()): ?>
                        <a href="orders.php" class="btn btn-primary">
                            <i class="fas fa-list"></i> View My Orders
                        </a>
                    <?php endif; ?>
                    <a href="products.php" class="btn btn-outline">
                        <i class="fas fa-shopping-bag"></i> Continue Shopping
                    </a>
                    <a href="contact.php" class="btn btn-secondary">
                        <i class="fas fa-question-circle"></i> Need Help?
                    </a>
                </div>

                <!-- Additional Info -->
                <div style="margin-top: 3rem; padding: 2rem; background: #e8f4f0; border-radius: 10px; text-align: center;">
                    <h4 style="color: #155724; margin-bottom: 1rem;">
                        <i class="fas fa-gift"></i> Thank You for Choosing Velvet Vogue!
                    </h4>
                    <p style="color: #155724; margin-bottom: 1rem;">
                        We appreciate your business and hope you love your new items. Don't forget to follow us on social media for the latest fashion trends and exclusive offers!
                    </p>
                    <div style="display: flex; gap: 1rem; justify-content: center;">
                        <a href="#" style="color: #8b5a3c; font-size: 1.5rem;">
                            <i class="fab fa-facebook"></i>
                        </a>
                        <a href="#" style="color: #8b5a3c; font-size: 1.5rem;">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="#" style="color: #8b5a3c; font-size: 1.5rem;">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" style="color: #8b5a3c; font-size: 1.5rem;">
                            <i class="fab fa-pinterest"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>

    <script src="assets/js/main.js"></script>
</body>
</html>
