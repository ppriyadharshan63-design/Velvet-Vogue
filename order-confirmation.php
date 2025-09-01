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
    $_SESSION['error'] = 'Order not found';
    header('Location: index.php');
    exit;
}

// Get order items
$items_sql = "SELECT * FROM order_items WHERE order_id = ?";
$items_stmt = $conn->prepare($items_sql);
$items_stmt->bind_param("i", $order['id']);
$items_stmt->execute();
$order_items = $items_stmt->get_result();
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
    <style>
        .confirmation-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .success-header {
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .success-icon {
            background: #28a745;
            color: white;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            font-size: 2rem;
        }
        
        .order-details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .next-steps {
            background: #e8f4f0;
            padding: 2rem;
            border-radius: 10px;
            text-align: center;
            margin: 2rem 0;
        }
        
        .steps-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
            margin-top: 2rem;
        }
        
        .step {
            text-align: center;
        }
        
        .step-icon {
            background: #8b5a3c;
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.2rem;
        }
        
        @media (max-width: 768px) {
            .order-details-grid {
                grid-template-columns: 1fr;
            }
            
            .steps-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <section style="padding: 6rem 0 4rem; background: #f8f9fa; min-height: 100vh;">
        <div class="confirmation-container">
            <!-- Success Header -->
            <div class="success-header">
                <div class="success-icon">
                    <i class="fas fa-check"></i>
                </div>
                <h1 style="color: #2c3e50; margin-bottom: 1rem;">Order Confirmed!</h1>
                <p style="color: #666; font-size: 1.1rem;">
                    Thank you for your purchase. Your order has been successfully placed and will be processed shortly.
                </p>
            </div>

            <!-- Order Summary Card -->
            <div class="card" style="margin-bottom: 2rem;">
                <div class="card-header">
                    <h3 style="margin: 0; color: #2c3e50;">Order Summary</h3>
                </div>
                <div class="card-body">
                    <div class="order-details-grid">
                        <div>
                            <h4 style="color: #8b5a3c; margin-bottom: 1rem;">Order Information</h4>
                            <p style="margin: 0.5rem 0;"><strong>Order Number:</strong> <?php echo htmlspecialchars($order['order_number']); ?></p>
                            <p style="margin: 0.5rem 0;"><strong>Order Date:</strong> <?php echo date('F j, Y \a\t g:i A', strtotime($order['created_at'])); ?></p>
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
                </div>
            </div>

            <!-- Order Items -->
            <div class="card" style="margin-bottom: 2rem;">
                <div class="card-header">
                    <h3 style="margin: 0; color: #2c3e50;">Items Ordered</h3>
                </div>
                <div class="card-body">
                    <?php while ($item = $order_items->fetch_assoc()): ?>
                        <div style="display: flex; gap: 1rem; padding: 1rem 0; border-bottom: 1px solid #eee;">
                            <img src="<?php echo htmlspecialchars($item['product_image']); ?>" 
                                 alt="<?php echo htmlspecialchars($item['product_name']); ?>"
                                 style="width: 60px; height: 60px; object-fit: cover; border-radius: 5px;">
                            <div style="flex: 1;">
                                <h4 style="margin: 0 0 0.25rem; color: #2c3e50;">
                                    <?php echo htmlspecialchars($item['product_name']); ?>
                                </h4>
                                <?php if ($item['size']): ?>
                                    <p style="margin: 0; font-size: 0.9rem; color: #666;">Size: <?php echo htmlspecialchars($item['size']); ?></p>
                                <?php endif; ?>
                                <?php if ($item['color']): ?>
                                    <p style="margin: 0; font-size: 0.9rem; color: #666;">Color: <?php echo htmlspecialchars($item['color']); ?></p>
                                <?php endif; ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.5rem;">
                                    <span style="color: #666;">Qty: <?php echo $item['quantity']; ?></span>
                                    <span style="font-weight: bold; color: #8b5a3c;">
                                        <?php echo formatPrice($item['total_price']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>

            <!-- Billing & Shipping Addresses -->
            <div class="order-details-grid">
                <div class="card">
                    <div class="card-header">
                        <h3 style="margin: 0; color: #2c3e50;">Billing Address</h3>
                    </div>
                    <div class="card-body">
                        <address style="margin: 0; line-height: 1.6;">
                            <strong><?php echo htmlspecialchars($order['billing_first_name'] . ' ' . $order['billing_last_name']); ?></strong><br>
                            <?php echo htmlspecialchars($order['billing_address']); ?><br>
                            <?php echo htmlspecialchars($order['billing_city'] . ', ' . $order['billing_state'] . ' ' . $order['billing_postal_code']); ?><br>
                            <?php echo htmlspecialchars($order['billing_country']); ?>
                            <?php if ($order['billing_email']): ?>
                                <br><?php echo htmlspecialchars($order['billing_email']); ?>
                            <?php endif; ?>
                            <?php if ($order['billing_phone']): ?>
                                <br><?php echo htmlspecialchars($order['billing_phone']); ?>
                            <?php endif; ?>
                        </address>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3 style="margin: 0; color: #2c3e50;">Shipping Address</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($order['shipping_first_name']): ?>
                            <address style="margin: 0; line-height: 1.6;">
                                <strong><?php echo htmlspecialchars($order['shipping_first_name'] . ' ' . $order['shipping_last_name']); ?></strong><br>
                                <?php echo htmlspecialchars($order['shipping_address']); ?><br>
                                <?php echo htmlspecialchars($order['shipping_city'] . ', ' . $order['shipping_state'] . ' ' . $order['shipping_postal_code']); ?><br>
                                <?php echo htmlspecialchars($order['shipping_country']); ?>
                            </address>
                        <?php else: ?>
                            <p style="margin: 0; color: #666; font-style: italic;">Same as billing address</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- What's Next -->
            <div class="next-steps">
                <h3 style="color: #2c3e50; margin-bottom: 1rem;">What's Next?</h3>
                <p style="color: #666; margin-bottom: 0;">
                    We'll start preparing your order for shipment and keep you updated every step of the way.
                </p>
                
                <div class="steps-grid">
                    <div class="step">
                        <div class="step-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <h4 style="color: #2c3e50; margin-bottom: 0.5rem;">Email Confirmation</h4>
                        <p style="color: #666; margin: 0; font-size: 0.9rem;">
                            You'll receive a confirmation email with your order details shortly.
                        </p>
                    </div>
                    
                    <div class="step">
                        <div class="step-icon">
                            <i class="fas fa-cogs"></i>
                        </div>
                        <h4 style="color: #2c3e50; margin-bottom: 0.5rem;">Order Processing</h4>
                        <p style="color: #666; margin: 0; font-size: 0.9rem;">
                            We'll prepare your order for shipment within 1-2 business days.
                        </p>
                    </div>
                    
                    <div class="step">
                        <div class="step-icon">
                            <i class="fas fa-truck"></i>
                        </div>
                        <h4 style="color: #2c3e50; margin-bottom: 0.5rem;">Shipping & Delivery</h4>
                        <p style="color: #666; margin: 0; font-size: 0.9rem;">
                            Once shipped, you'll receive tracking information to monitor your package.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div style="text-align: center; margin: 2rem 0;">
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
            </div>

            <!-- Thank You Message -->
            <div style="background: white; padding: 2rem; border-radius: 10px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                <h4 style="color: #8b5a3c; margin-bottom: 1rem;">
                    <i class="fas fa-heart"></i> Thank You for Choosing Velvet Vogue!
                </h4>
                <p style="color: #666; margin-bottom: 1rem;">
                    We appreciate your business and hope you love your new items. Follow us on social media for the latest fashion trends and exclusive offers!
                </p>
                <div style="display: flex; gap: 1rem; justify-content: center;">
                    <a href="#" style="color: #8b5a3c; font-size: 1.5rem; text-decoration: none;">
                        <i class="fab fa-facebook"></i>
                    </a>
                    <a href="#" style="color: #8b5a3c; font-size: 1.5rem; text-decoration: none;">
                        <i class="fab fa-instagram"></i>
                    </a>
                    <a href="#" style="color: #8b5a3c; font-size: 1.5rem; text-decoration: none;">
                        <i class="fab fa-twitter"></i>
                    </a>
                    <a href="#" style="color: #8b5a3c; font-size: 1.5rem; text-decoration: none;">
                        <i class="fab fa-pinterest"></i>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>

    <script src="assets/js/main.js"></script>
</body>
</html>
