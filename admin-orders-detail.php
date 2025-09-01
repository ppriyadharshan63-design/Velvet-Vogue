<?php
session_start();
require_once 'config/database.php';

// Check if the user is logged in and is an admin
function checkAdmin($conn, $user_id) {
    $stmt = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0 || (int) $result->fetch_assoc()['is_admin'] !== 1) {
        $_SESSION['error_message'] = "Unauthorized access.";
        header('Location: unauthorized.php');
        exit;
    }
}

// Get order details
function getOrderDetails($conn, $order_id) {
    $stmt = $conn->prepare("
        SELECT o.*, u.first_name, u.last_name, u.email, u.phone
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        WHERE o.id = ?
    ");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Get order items
function getOrderItems($conn, $order_id) {
    $stmt = $conn->prepare("
        SELECT oi.*, p.name AS product_name, p.image_url 
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id 
        WHERE oi.order_id = ?
        ORDER BY oi.id
    ");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    return $stmt->get_result();
}

// Update order status
function updateOrderStatus($conn, $order_id, $status, $tracking_number, $notes) {
    $stmt = $conn->prepare("UPDATE orders SET status = ?, tracking_number = ?, notes = ? WHERE id = ?");
    $stmt->bind_param("sssi", $status, $tracking_number, $notes, $order_id);
    return $stmt->execute();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];
checkAdmin($conn, $user_id);

// Validate order ID
$order_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($order_id <= 0) {
    $_SESSION['error_message'] = "Invalid order ID.";
    header('Location: admin-orders.php');
    exit;
}

// Handle POST request to update order status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = trim($_POST['status']);
    $tracking_number = trim($_POST['tracking_number']);
    $notes = trim($_POST['notes']);

    if (empty($new_status)) {
        $_SESSION['error_message'] = "Status cannot be empty.";
        header("Location: admin-order-detail.php?id={$order_id}");
        exit;
    }

    if (updateOrderStatus($conn, $order_id, $new_status, $tracking_number, $notes)) {
        $_SESSION['success_message'] = "Order status updated successfully!";
    } else {
        $_SESSION['error_message'] = "Failed to update order status. Please try again.";
    }

    header("Location: admin-order-detail.php?id={$order_id}");
    exit;
}

// Get order details and items
$order = getOrderDetails($conn, $order_id);
$items_result = getOrderItems($conn, $order_id);
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order #<?php echo $order['id']; ?> - Velvet Vogue Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            color: #2c3e50;
            line-height: 1.6;
        }

        .admin-header {
            background: linear-gradient(135deg, #8b5a3c 0%, #a0522d 100%);
            color: white;
            padding: 1.5rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .admin-header .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .admin-nav a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 500;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #8b5a3c 0%, #a0522d 100%);
            color: white;
        }

        .order-header {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .order-info {
            margin-bottom: 2rem;
        }

        .order-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 1.5rem;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            display: inline-block;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-processing { background: #cce7ff; color: #004085; }
        .status-shipped { background: #d1ecf1; color: #0c5460; }
        .status-delivered { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }

        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <div class="container">
            <h1>Velvet Vogue Admin</h1>
            <nav class="admin-nav">
                <a href="admin.php">Dashboard</a>
                <a href="admin-products.php">Products</a>
                <a href="admin-orders.php">Orders</a>
                <a href="admin-categories.php">Categories</a>
                <a href="account.php">Account</a>
            </nav>
        </div>
    </div>

    <div class="container">
        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error">
                <?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <!-- Order Header -->
        <div class="order-header">
            <h2>Order #<?= $order['id']; ?></h2>
            <p>Placed on <?= date('F j, Y', strtotime($order['created_at'])); ?></p>
            <a href="admin-orders.php" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Back to Orders</a>
        </div>

        <!-- Order Summary -->
        <section class="order-summary">
            <span class="status-badge status-<?= $order['status']; ?>"><?= ucfirst($order['status']); ?></span>
            <div>
                <h3>Total: $<?= number_format($order['total_amount'], 2); ?></h3>
                <p><strong>Tracking Number:</strong> <?= htmlspecialchars($order['tracking_number'] ?? 'N/A'); ?></p>
            </div>
        </section>

        <!-- Order Information -->
        <section class="order-info">
            <!-- Customer Info -->
            <div class="order-section">
                <h4>Customer Information</h4>
                <p><strong>Name:</strong> <?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($order['email']); ?></p>
            </div>

            <!-- Shipping Info -->
            <div class="order-section">
                <h4>Shipping Address</h4>
                <p><?= htmlspecialchars($order['shipping_address']); ?></p>
                <p><?= htmlspecialchars($order['shipping_city']); ?>, <?= htmlspecialchars($order['shipping_state']); ?> <?= htmlspecialchars($order['shipping_zip']); ?></p>
                <p><?= htmlspecialchars($order['shipping_country']); ?></p>
            </div>
        </section>

        <!-- Order Status Update Form -->
        <section class="order-update">
            <h4>Update Order Status</h4>
            <form method="POST">
                <div class="form-group">
                    <label for="status">Status</label>
                    <select name="status" id="status" required>
                        <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="processing" <?= $order['status'] === 'processing' ? 'selected' : ''; ?>>Processing</option>
                        <option value="shipped" <?= $order['status'] === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                        <option value="delivered" <?= $order['status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                        <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="tracking_number">Tracking Number</label>
                    <input type="text" name="tracking_number" id="tracking_number" value="<?= htmlspecialchars($order['tracking_number'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea name="notes" id="notes"><?= htmlspecialchars($order['notes'] ?? ''); ?></textarea>
                </div>
                <button type="submit" name="update_status" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Status
                </button>
            </form>
        </section>

        <!-- Order Items Table -->
        <section class="order-items">
            <h4>Order Items (<?= $items_result->num_rows; ?>)</h4>
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Price</th>
                        <th>Quantity</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($item = $items_result->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <img src="<?= htmlspecialchars($item['image_url']); ?>" alt="Product Image" class="product-image">
                                <?= htmlspecialchars($item['current_product_name']); ?>
                            </td>
                            <td>$<?= number_format($item['product_price'], 2); ?></td>
                            <td><?= $item['quantity']; ?></td>
                            <td>$<?= number_format($item['product_price'] * $item['quantity'], 2); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </section>
    </div>
</body>
</html>
