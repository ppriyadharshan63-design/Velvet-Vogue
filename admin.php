<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Require login and admin access
if (!isLoggedIn()) {
    header('Location: login.php?redirect=admin.php');
    exit;
}

$user_id = getCurrentUserId();
$user_sql = "SELECT is_admin FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();

if (!$user || !$user['is_admin']) {
    header('Location: account.php');
    exit;
}

// Get statistics
$stats = [];

// Total products
$stats_sql = "SELECT COUNT(*) as total FROM products WHERE is_active = 1";
$stats['products'] = $conn->query($stats_sql)->fetch_assoc()['total'];

// Total orders
$stats_sql = "SELECT COUNT(*) as total FROM orders";
$stats['orders'] = $conn->query($stats_sql)->fetch_assoc()['total'];

// Total customers
$stats_sql = "SELECT COUNT(*) as total FROM users WHERE is_admin = 0";
$stats['customers'] = $conn->query($stats_sql)->fetch_assoc()['total'];

// Total revenue
$stats_sql = "SELECT SUM(total_amount) as total FROM orders WHERE status != 'cancelled'";
$revenue_result = $conn->query($stats_sql)->fetch_assoc();
$stats['revenue'] = $revenue_result['total'] ?? 0;

// Recent orders
$recent_orders_sql = "SELECT o.*, u.first_name, u.last_name FROM orders o LEFT JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC LIMIT 10";
$recent_orders = $conn->query($recent_orders_sql);

// Low stock products
$low_stock_sql = "SELECT * FROM products WHERE stock <= 5 AND is_active = 1 ORDER BY stock ASC LIMIT 10";
$low_stock = $conn->query($low_stock_sql);

// Recent contact messages
$messages_sql = "SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 5";
$messages = $conn->query($messages_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Velvet Vogue</title>
    <meta name="description" content="Velvet Vogue admin dashboard for managing products, orders, customers, and store analytics.">
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <section style="padding: 6rem 0 4rem; background: #f8f9fa; min-height: 100vh;">
        <div class="container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 3rem;">
                <h1 style="color: #2c3e50; margin: 0;">Admin Dashboard</h1>
                <a href="account.php" class="btn btn-outline">
                    <i class="fas fa-user"></i> Back to Account
                </a>
            </div>

            <!-- Statistics Cards -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem; margin-bottom: 3rem;">
                
                <div style="background: white; padding: 2rem; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); border-left: 4px solid #8b5a3c;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h3 style="color: #8b5a3c; margin: 0; font-size: 2rem;"><?php echo $stats['products']; ?></h3>
                            <p style="color: #666; margin: 0;">Total Products</p>
                        </div>
                        <i class="fas fa-box" style="font-size: 2rem; color: #8b5a3c; opacity: 0.7;"></i>
                    </div>
                </div>
                
                <div style="background: white; padding: 2rem; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); border-left: 4px solid #28a745;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h3 style="color: #28a745; margin: 0; font-size: 2rem;"><?php echo $stats['orders']; ?></h3>
                            <p style="color: #666; margin: 0;">Total Orders</p>
                        </div>
                        <i class="fas fa-shopping-cart" style="font-size: 2rem; color: #28a745; opacity: 0.7;"></i>
                    </div>
                </div>
                
                <div style="background: white; padding: 2rem; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); border-left: 4px solid #007bff;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h3 style="color: #007bff; margin: 0; font-size: 2rem;"><?php echo $stats['customers']; ?></h3>
                            <p style="color: #666; margin: 0;">Customers</p>
                        </div>
                        <i class="fas fa-users" style="font-size: 2rem; color: #007bff; opacity: 0.7;"></i>
                    </div>
                </div>
                
                <div style="background: white; padding: 2rem; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); border-left: 4px solid #ffc107;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h3 style="color: #ffc107; margin: 0; font-size: 2rem;">$<?php echo number_format($stats['revenue'], 0); ?></h3>
                            <p style="color: #666; margin: 0;">Total Revenue</p>
                        </div>
                        <i class="fas fa-dollar-sign" style="font-size: 2rem; color: #ffc107; opacity: 0.7;"></i>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div style="background: white; padding: 2rem; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 3rem;">
                <h3 style="color: #2c3e50; margin-bottom: 1.5rem;">Quick Actions</h3>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <a href="admin-products.php" class="btn btn-primary" style="text-decoration: none; text-align: center;">
                        <i class="fas fa-plus"></i> Manage Product
                        
                    </a>
                    
                    <a href="admin-orders.php" class="btn btn-outline" style="text-decoration: none; text-align: center;">
                        <i class="fas fa-list"></i> Manage Orders
                    </a>
                    
                    <a href="admin-customers.php" class="btn btn-outline" style="text-decoration: none; text-align: center;">
                        <i class="fas fa-users"></i> View Customers
                    </a>
                    
                    <a href="admin-messages.php" class="btn btn-outline" style="text-decoration: none; text-align: center;">
                        <i class="fas fa-envelope"></i> Contact Messages
                    </a>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 3rem;">
                
                <!-- Recent Orders -->
                <div style="background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    <div style="padding: 1.5rem; border-bottom: 1px solid #eee;">
                        <h3 style="color: #2c3e50; margin: 0;">Recent Orders</h3>
                    </div>
                    
                    <?php if ($recent_orders && $recent_orders->num_rows > 0): ?>
                        <div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="background: #f8f9fa;">
                                        <th style="padding: 1rem; text-align: left; border-bottom: 1px solid #ddd;">Order #</th>
                                        <th style="padding: 1rem; text-align: left; border-bottom: 1px solid #ddd;">Customer</th>
                                        <th style="padding: 1rem; text-align: left; border-bottom: 1px solid #ddd;">Total</th>
                                        <th style="padding: 1rem; text-align: left; border-bottom: 1px solid #ddd;">Status</th>
                                        <th style="padding: 1rem; text-align: left; border-bottom: 1px solid #ddd;">Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($order = $recent_orders->fetch_assoc()): ?>
                                        <tr>
                                            <td style="padding: 1rem; border-bottom: 1px solid #eee;">
                                                <a href="admin-order-detail.php?id=<?php echo $order['id']; ?>" style="color: #8b5a3c; text-decoration: none;">
                                                    #<?php echo $order['id']; ?>
                                                </a>
                                            </td>
                                            <td style="padding: 1rem; border-bottom: 1px solid #eee;">
                                                <?php echo htmlspecialchars(($order['first_name'] ?? 'Guest') . ' ' . ($order['last_name'] ?? '')); ?>
                                            </td>
                                            <td style="padding: 1rem; border-bottom: 1px solid #eee;">
                                                $<?php echo number_format($order['total_amount'], 2); ?>
                                            </td>
                                            <td style="padding: 1rem; border-bottom: 1px solid #eee;">
                                                <span style="padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem; 
                                                            background: <?php 
                                                                switch($order['status']) {
                                                                    case 'pending': echo '#fff3cd; color: #856404;'; break;
                                                                    case 'processing': echo '#cce7ff; color: #004085;'; break;
                                                                    case 'shipped': echo '#d1ecf1; color: #0c5460;'; break;
                                                                    case 'delivered': echo '#d4edda; color: #155724;'; break;
                                                                    case 'cancelled': echo '#f8d7da; color: #721c24;'; break;
                                                                    default: echo '#f8f9fa; color: #666;';
                                                                }
                                                            ?>">
                                                    <?php echo ucfirst($order['status']); ?>
                                                </span>
                                            </td>
                                            <td style="padding: 1rem; border-bottom: 1px solid #eee;">
                                                <?php echo date('M j, Y', strtotime($order['created_at'])); ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div style="padding: 1rem; text-align: center; border-top: 1px solid #eee;">
                            <a href="admin-orders.php" class="btn btn-outline">View All Orders</a>
                        </div>
                    <?php else: ?>
                        <div style="padding: 3rem; text-align: center; color: #666;">
                            <i class="fas fa-shopping-cart" style="font-size: 3rem; margin-bottom: 1rem; color: #ccc;"></i>
                            <p>No orders yet</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Sidebar -->
                <div>
                    <!-- Low Stock Alert -->
                    <div style="background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 2rem;">
                        <div style="padding: 1.5rem; border-bottom: 1px solid #eee;">
                            <h3 style="color: #e74c3c; margin: 0;">
                                <i class="fas fa-exclamation-triangle"></i> Low Stock Alert
                            </h3>
                        </div>
                        
                        <?php if ($low_stock && $low_stock->num_rows > 0): ?>
                            <div style="max-height: 300px; overflow-y: auto;">
                                <?php while ($product = $low_stock->fetch_assoc()): ?>
                                    <div style="padding: 1rem; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                                        <div>
                                            <strong style="display: block; color: #2c3e50;"><?php echo htmlspecialchars($product['name']); ?></strong>
                                            <small style="color: #666;">ID: #<?php echo $product['id']; ?></small>
                                        </div>
                                        <span style="background: #f8d7da; color: #721c24; padding: 0.25rem 0.5rem; border-radius: 20px; font-size: 0.8rem;">
                                            <?php echo $product['stock']; ?> left
                                        </span>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                            
                            <div style="padding: 1rem; text-align: center; border-top: 1px solid #eee;">
                                <a href="admin-products.php?filter=low_stock" class="btn btn-outline" style="font-size: 0.9rem;">
                                    View All
                                </a>
                            </div>
                        <?php else: ?>
                            <div style="padding: 2rem; text-align: center; color: #666;">
                                <i class="fas fa-check-circle" style="color: #28a745; font-size: 2rem; margin-bottom: 0.5rem;"></i>
                                <p style="margin: 0;">All products well stocked!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Recent Messages -->
                    <div style="background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                        <div style="padding: 1.5rem; border-bottom: 1px solid #eee;">
                            <h3 style="color: #2c3e50; margin: 0;">Recent Messages</h3>
                        </div>
                        
                        <?php if ($messages && $messages->num_rows > 0): ?>
                            <div style="max-height: 300px; overflow-y: auto;">
                                <?php while ($message = $messages->fetch_assoc()): ?>
                                    <div style="padding: 1rem; border-bottom: 1px solid #eee;">
                                        <div style="display: flex; justify-content: between; align-items: start; margin-bottom: 0.5rem;">
                                            <strong style="color: #2c3e50;"><?php echo htmlspecialchars($message['name']); ?></strong>
                                            <?php if (!$message['is_read']): ?>
                                                <span style="background: #007bff; color: white; padding: 0.2rem 0.5rem; border-radius: 10px; font-size: 0.7rem; margin-left: auto;">
                                                    New
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <p style="color: #666; font-size: 0.9rem; margin: 0.5rem 0;"><?php echo htmlspecialchars($message['subject']); ?></p>
                                        <small style="color: #999;"><?php echo date('M j, g:i A', strtotime($message['created_at'])); ?></small>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                            
                            <div style="padding: 1rem; text-align: center; border-top: 1px solid #eee;">
                                <a href="admin-messages.php" class="btn btn-outline" style="font-size: 0.9rem;">
                                    View All Messages
                                </a>
                            </div>
                        <?php else: ?>
                            <div style="padding: 2rem; text-align: center; color: #666;">
                                <i class="fas fa-envelope" style="font-size: 2rem; margin-bottom: 0.5rem; color: #ccc;"></i>
                                <p style="margin: 0;">No messages yet</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/main.js"></script>
</body>
</html>