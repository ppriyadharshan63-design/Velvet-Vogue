<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Must be logged in
if (!isLoggedIn()) {
    $_SESSION['error_message'] = "Please log in to view orders.";
    header("Location: login.php");
    exit;
}

$user_id = getUserId();

// Escape helper
function e($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

// Validate order id
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($order_id <= 0) {
    $_SESSION['error_message'] = "Invalid order ID.";
    header('Location: orders.php');
    exit;
}

// Load order
$order_sql = "
    SELECT o.*, u.first_name, u.last_name, u.email, u.phone, u.is_admin
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    WHERE o.id = ?
";
$order_stmt = $conn->prepare($order_sql);
$order_stmt->bind_param("i", $order_id);
$order_stmt->execute();
$order = $order_stmt->get_result()->fetch_assoc();
$order_stmt->close();

if (!$order) {
    $_SESSION['error_message'] = "Order not found.";
    header('Location: orders.php');
    exit;
}

$is_admin = $order['is_admin'] ?? false;
if (!$is_admin && $order['user_id'] != $user_id) {
    $_SESSION['error_message'] = "You are not allowed to view this order.";
    header('Location: orders.php');
    exit;
}

// Handle Delete Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_order') {
    if ($is_admin || $order['user_id'] == $user_id) {
        $stmt = $conn->prepare("DELETE FROM orders WHERE id=?");
        $stmt->bind_param("i", $order_id);
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Order deleted successfully.";
            header("Location: " . ($is_admin ? "admin-orders.php" : "orders.php"));
            exit;
        } else {
            $error_message = "Failed to delete order.";
        }
        $stmt->close();
    } else {
        $error_message = "You cannot delete this order.";
    }
}

// Fetch order items
$items_sql = "
    SELECT oi.*, p.name, p.image_url
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
";
$items_stmt = $conn->prepare($items_sql);
$items_stmt->bind_param("i", $order_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();
$items_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Order #<?= e($order['id']) ?> - Velvet Vogue Admin</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
/* Simplified Styles */
body{font-family:Segoe UI,Tahoma,sans-serif;background:#f5f7fa;color:#2c3e50;line-height:1.6;margin:0}
.container{max-width:1200px;margin:0 auto;padding:2rem}
.card{background:#fff;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.1);padding:2rem;margin-bottom:2rem}
.btn{padding:.7rem 1.2rem;border:none;border-radius:6px;cursor:pointer;font-size:1rem}
.btn-outline{background:#fff;border:1px solid #333;color:#333}
.btn-outline:hover{background:#333;color:#fff}
.btn-danger{background:#dc3545;color:#fff}
.btn-danger:hover{background:#c82333}
.order-item{display:flex;align-items:center;gap:1rem;padding:1rem;border:1px solid #eee;border-radius:8px;margin-bottom:1rem}
.item-image{width:60px;height:60px;object-fit:cover;border-radius:5px}
.alert{padding:1rem;margin-bottom:1rem;border-radius:8px}
.alert-success{background:#d4edda;color:#155724;border:1px solid #c3e6cb}
.alert-error{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb}
</style>
</head>
<body>
<div class="container">
  <h1>🧾 Order #<?= e($order['id']) ?></h1>
  <p>Placed on <?= e(date('F j, Y g:i A', strtotime($order['created_at']))) ?></p>

  <?php if (!empty($error_message)): ?>
    <div class="alert alert-error"><?= e($error_message) ?></div>
  <?php endif; ?>

  <div class="card">
    <h2>Order Information</h2>
    <p><strong>Status:</strong> <?= e(ucfirst($order['status'])) ?></p>
    <p><strong>Total:</strong> $<?= number_format((float)$order['total_amount'],2) ?></p>
    <p><strong>Payment:</strong> <?= e($order['payment_status'] ?? 'Pending') ?> (<?= e($order['payment_method'] ?? 'N/A') ?>)</p>
  </div>

  <div class="card">
    <h2>Customer Information</h2>
    <p><strong>Name:</strong> <?= e(($order['first_name'] ?? '').' '.($order['last_name'] ?? '')) ?></p>
    <p><strong>Email:</strong> <?= e($order['email'] ?? '') ?></p>
    <p><strong>Phone:</strong> <?= e($order['phone'] ?? 'N/A') ?></p>
  </div>

  <div class="card">
    <h2>Order Items</h2>
    <?php if ($items_result->num_rows > 0): ?>
      <?php $total = 0; while ($item = $items_result->fetch_assoc()): ?>
        <?php $price = (float)($item['unit_price'] ?? 0); $qty = (int)$item['quantity']; $item_total = $price * $qty; $total += $item_total; ?>
        <div class="order-item">
          <?php if (!empty($item['image_url'])): ?>
            <img src="<?= e($item['image_url']) ?>" class="item-image" alt="">
          <?php endif; ?>
          <div>
            <p><strong><?= e($item['name'] ?? 'Product') ?></strong></p>
            <p><?= $qty ?> × $<?= number_format($price,2) ?> = $<?= number_format($item_total,2) ?></p>
          </div>
        </div>
      <?php endwhile; ?>
      <p style="text-align:right;font-weight:600;">Total: $<?= number_format($total,2) ?></p>
    <?php else: ?>
      <p>No items found for this order.</p>
    <?php endif; ?>
  </div>

  <!-- Delete Form -->
  <form method="POST" onsubmit="return confirm('Are you sure you want to delete this order?');">
    <input type="hidden" name="action" value="delete_order">
    <button type="submit" class="btn btn-danger"><i class="fas fa-trash"></i> Delete Order</button>
    <a href="<?= $is_admin ? 'account.php' : 'orders.php' ?>" class="btn btn-outline">Back</a>
  </form>
</div>
</body>
</html>
