<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// ---------- Helpers ----------
function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// ---------- Auth check ----------
if (!isLoggedIn()) {
    $_SESSION['error_message'] = "Please log in to view orders.";
    header("Location: login.php");
    exit;
}

$user_id = getUserId();

// Fetch logged-in user's admin status
$admin_stmt = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
$admin_stmt->bind_param("i", $user_id);
$admin_stmt->execute();
$is_admin = (bool)($admin_stmt->get_result()->fetch_assoc()['is_admin'] ?? 0);
$admin_stmt->close();

// ---------- Validate order ----------
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($order_id <= 0) {
    $_SESSION['error_message'] = "Invalid order ID.";
    header("Location: " . ($is_admin ? "admin-orders.php" : "orders.php"));
    exit;
}

// Load order with customer details
$order_sql = "
    SELECT o.*, u.first_name, u.last_name, u.email, u.phone
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
    header("Location: " . ($is_admin ? "admin-orders.php" : "orders.php"));
    exit;
}

// Permission check: customers can only view their own orders
if (!$is_admin && $order['user_id'] != $user_id) {
    $_SESSION['error_message'] = "You are not allowed to view this order.";
    header("Location: orders.php");
    exit;
}

// ---------- Handle POST actions ----------
$success_message = $error_message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // Admin: update order status
    if ($is_admin && $action === 'update_status') {
        $new_status = $_POST['status'] ?? '';
        $valid_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
        if (in_array($new_status, $valid_statuses, true)) {
            $stmt = $conn->prepare("UPDATE orders SET status=?, updated_at=NOW() WHERE id=?");
            $stmt->bind_param("si", $new_status, $order_id);
            if ($stmt->execute()) {
                $success_message = "Order status updated!";
                $order['status'] = $new_status; // reflect in UI
            } else {
                $error_message = "Failed to update order.";
            }
            $stmt->close();
        } else {
            $error_message = "Invalid status value.";
        }
    }

    // Admin: update shipping info
    elseif ($is_admin && $action === 'update_order') {
        $shipping_address = trim($_POST['shipping_address'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        if ($shipping_address !== '') {
            $stmt = $conn->prepare("UPDATE orders SET shipping_address=?, notes=?, updated_at=NOW() WHERE id=?");
            $stmt->bind_param("ssi", $shipping_address, $notes, $order_id);
            if ($stmt->execute()) {
                $success_message = "Order details updated!";
                $order['shipping_address'] = $shipping_address;
                $order['notes'] = $notes;
            } else {
                $error_message = "Failed to update order details.";
            }
            $stmt->close();
        } else {
            $error_message = "Shipping address cannot be empty.";
        }
    }

    // Admin or Customer: delete order
    elseif ($action === 'delete_order') {
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
}

// ---------- Fetch order items ----------
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
<meta name="description" content="Order details for Velvet Vogue admin dashboard">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
/* ——— your styles unchanged, just kept tidy ——— */
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:Segoe UI,Tahoma,Geneva,Verdana,sans-serif;background:#f5f7fa;color:#2c3e50;line-height:1.6}
.admin-header{background:linear-gradient(135deg,#8b5a3c 0%,#a0522d 100%);color:#fff;padding:1.5rem 0;box-shadow:0 2px 10px rgba(0,0,0,.1);position:sticky;top:0;z-index:100}
.admin-header .container{max-width:1200px;margin:0 auto;padding:0 2rem;display:flex;justify-content:space-between;align-items:center}
.admin-header h1{font-size:1.8rem;font-weight:600}
.admin-nav{display:flex;gap:2rem;align-items:center}
.admin-nav a{color:#fff;text-decoration:none;padding:.5rem 1rem;border-radius:8px;transition:.3s;font-weight:500}
.admin-nav a:hover{background:rgba(255,255,255,.2);transform:translateY(-1px)}
.container{max-width:1200px;margin:0 auto;padding:2rem}
.btn{padding:.75rem 1.5rem;border:none;border-radius:8px;text-decoration:none;display:inline-flex;align-items:center;gap:.5rem;font-size:1rem;font-weight:500;cursor:pointer;transition:.3s}
.btn-primary{background:linear-gradient(135deg,#8b5a3c,#a0522d);color:#fff}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 4px 15px rgba(139,90,60,.3)}
.btn-outline{background:transparent;color:#8b5a3c;border:2px solid #8b5a3c}
.btn-outline:hover{background:#8b5a3c;color:#fff}
.btn-danger{background:#dc3545;color:#fff}.btn-danger:hover{background:#c82333}
.btn-success{background:#28a745;color:#fff}.btn-success:hover{background:#218838}
.btn-info{background:#17a2b8;color:#fff}.btn-info:hover{background:#138496}
.btn-warning{background:#ffc107;color:#212529}.btn-warning:hover{background:#e0a800}
.card{background:#fff;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.1);padding:2rem;margin-bottom:2rem}
.card-header{border-bottom:1px solid #eee;padding-bottom:1rem;margin-bottom:1.5rem}
.card-header h2{color:#2c3e50;margin:0}
.grid{display:grid;gap:2rem}.grid-2{grid-template-columns:1fr 1fr}.grid-3{grid-template-columns:1fr 1fr 1fr}
.form-group{margin-bottom:1.5rem}.form-label{display:block;margin-bottom:.5rem;color:#2c3e50;font-weight:500}
.form-control{width:100%;padding:.75rem;border:1px solid #ddd;border-radius:5px;font-size:1rem}
.form-control:focus{outline:none;border-color:#8b5a3c;box-shadow:0 0 0 2px rgba(139,90,60,.2)}
.status-badge{padding:.5rem 1rem;border-radius:20px;font-size:.9rem;font-weight:500;display:inline-block}
.status-pending{background:#fff3cd;color:#856404}
.status-processing{background:#cce7ff;color:#004085}
.status-shipped{background:#d1ecf1;color:#0c5460}
.status-delivered{background:#d4edda;color:#155724}
.status-cancelled{background:#f8d7da;color:#721c24}
.order-item{display:flex;align-items:center;gap:1rem;padding:1rem;border:1px solid #eee;border-radius:8px;margin-bottom:1rem}
.item-image{width:60px;height:60px;object-fit:cover;border-radius:5px}
.item-details{flex:1}.item-name{font-weight:600;margin-bottom:.25rem}.item-meta{color:#666;font-size:.9rem}
.alert{padding:1rem;margin-bottom:1rem;border-radius:8px}
.alert-success{background:#d4edda;color:#155724;border:1px solid #c3e6cb}
.alert-error{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb}
/* Modal */
.modal{display:none;position:fixed;z-index:1000;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,.5)}
.modal-content{background:#fff;margin:10% auto;padding:2rem;border-radius:10px;width:90%;max-width:500px;position:relative}
.close{color:#888;position:absolute;top:1rem;right:1rem;font-size:1.5rem;font-weight:bold;cursor:pointer}
.close:hover{color:#000}
@media (max-width:768px){.grid-2,.grid-3{grid-template-columns:1fr}.admin-nav{flex-direction:column;gap:1rem}.order-item{flex-direction:column;text-align:center}}
</style>
</head>
<body>
<div class="admin-header">
  <div class="container">
    <h1>Velvet Vogue Admin</h1>
    <nav class="admin-nav">
      <a href="admin.php"><i class="fas fa-home"></i> Dashboard</a>
      <a href="admin-products.php"><i class="fas fa-box"></i> Products</a>
      <a href="admin-customers.php"><i class="fas fa-users"></i> Customers</a>
      <a href="admin-orders.php" style="background: rgba(255,255,255,.2);"><i class="fas fa-shopping-cart"></i> Orders</a>
      <a href="admin-categories.php"><i class="fas fa-tags"></i> Categories</a>
      <a href="account.php"><i class="fas fa-user"></i> Account</a>
    </nav>
  </div>
</div>

<section style="padding: 2rem 0; min-height: 100vh;">
  <div class="container">
    <!-- Header -->
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:2rem;padding-bottom:1rem;border-bottom:1px solid #eee;">
      <div>
        <h1 style="color:#2c3e50;margin:0;font-size:1.75rem;">🧾 Order #<?= e($order['id']) ?></h1>
        <p style="color:#666;margin:.5rem 0 0;font-size:.95rem;">
          Placed on <?= e(date('F j, Y \a\t g:i A', strtotime($order['created_at']))) ?>
        </p>
      </div>
      <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
        <a href="admin-orders.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Orders</a>
        <button class="btn btn-danger" onclick="showDeleteModal()"><i class="fas fa-trash"></i> Delete Order</button>
      </div>
    </div>

    <!-- Alerts -->
    <?php if ($success_message): ?>
      <div class="alert alert-success"><?= e($success_message) ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
      <div class="alert alert-error"><?= e($error_message) ?></div>
    <?php endif; ?>

    <div class="grid grid-2">
      <!-- Order Info -->
      <div class="card">
        <div class="card-header"><h2>Order Information</h2></div>

        <div style="margin-bottom:2rem;">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
            <strong>Status:</strong>
            <span class="status-badge status-<?= e($order['status']) ?>"><?= e(ucfirst($order['status'])) ?></span>
          </div>
          <div style="margin-bottom:.5rem;"><strong>Total Amount:</strong> $<?= number_format((float)$order['total_amount'], 2) ?></div>
          <div style="margin-bottom:.5rem;"><strong>Payment Method:</strong> <?= e($order['payment_method'] ?? 'N/A') ?></div>
          <div style="margin-bottom:.5rem;"><strong>Payment Status:</strong> <?= e($order['payment_status'] ?? 'Pending') ?></div>
          <div><strong>Created:</strong> <?= e(date('M j, Y g:i A', strtotime($order['created_at']))) ?></div>
        </div>

        <!-- Process buttons -->
        <div style="border-top:1px solid #eee;padding-top:1.5rem;margin-bottom:1.5rem;">
          <h3 style="color:#2c3e50;margin-bottom:1rem;font-size:1.1rem;">Order Process</h3>
          <div class="order-process-buttons" style="display:flex;gap:.75rem;flex-wrap:wrap;">
            <?php if ($order['status'] === 'pending'): ?>
              <button class="btn btn-success" onclick="updateOrderStatus(<?= (int)$order['id'] ?>,'processing')"><i class="fas fa-play"></i> Start Processing</button>
            <?php elseif ($order['status'] === 'processing'): ?>
              <button class="btn btn-info" onclick="updateOrderStatus(<?= (int)$order['id'] ?>,'shipped')"><i class="fas fa-shipping-fast"></i> Mark as Shipped</button>
            <?php elseif ($order['status'] === 'shipped'): ?>
              <button class="btn btn-success" onclick="updateOrderStatus(<?= (int)$order['id'] ?>,'delivered')"><i class="fas fa-check"></i> Mark as Delivered</button>
            <?php endif; ?>
            <?php if (!in_array($order['status'], ['cancelled','delivered'], true)): ?>
              <button class="btn btn-warning" onclick="updateOrderStatus(<?= (int)$order['id'] ?>,'cancelled')"><i class="fas fa-times"></i> Cancel Order</button>
            <?php endif; ?>
          </div>
        </div>

        <!-- Manual status form -->
        <form method="POST" style="border-top:1px solid #eee;padding-top:1.5rem;">
          <input type="hidden" name="action" value="update_status">
          <div class="form-group">
            <label class="form-label">Manual Status Update</label>
            <select name="status" class="form-control">
              <?php foreach (['pending','processing','shipped','delivered','cancelled'] as $st): ?>
                <option value="<?= e($st) ?>" <?= $order['status'] === $st ? 'selected' : '' ?>><?= e(ucfirst($st)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Status</button>
        </form>
      </div>

      <!-- Customer -->
      <div class="card" style="border:1px solid #ddd;border-radius:8px;padding:1.5rem;background:#fff;box-shadow:0 4px 10px rgba(0,0,0,.05);">
        <div class="card-header" style="margin-bottom:1.5rem;border-bottom:1px solid #eee;">
          <h2 style="margin:0;font-size:1.4rem;color:#333;">👤 Customer Information</h2>
        </div>
        <div style="margin-bottom:1rem;"><strong>Name:</strong> <span style="color:#555;"><?= e(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? '')) ?></span></div>
        <div style="margin-bottom:1rem;"><strong>Email:</strong> <span style="color:#555;"><?= e($order['email'] ?? '') ?></span></div>
        <div style="margin-bottom:1rem;"><strong>Phone:</strong> <span style="color:#555;"><?= e($order['phone'] ?? 'N/A') ?></span></div>
        <?php if (!empty($order['billing_address'])): ?>
          <div style="margin-bottom:1rem;">
            <strong>Billing Address:</strong><br>
            <span style="white-space:pre-line;color:#555;"><?= nl2br(e($order['billing_address'])) ?></span>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Order details form -->
    <div class="card">
      <div class="card-header"><h2>Order Details</h2></div>
      <form method="POST">
        <input type="hidden" name="action" value="update_order">
        <div class="grid grid-2">
          <div class="form-group">
            <label class="form-label">Shipping Address</label>
            <textarea name="shipping_address" class="form-control" rows="4"><?= e($order['shipping_address'] ?? '') ?></textarea>
          </div>
          <div class="form-group">
            <label class="form-label">Admin Notes</label>
            <textarea name="notes" class="form-control" rows="4" placeholder="Add internal notes about this order..."><?= e($order['notes'] ?? '') ?></textarea>
          </div>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Details</button>
      </form>
    </div>

    <!-- Items -->
    <div class="card">
      <div class="card-header"><h2>Order Items</h2></div>
      <?php if ($items_result->num_rows > 0): ?>
        <?php $total = 0.0; ?>
        <?php while ($item = $items_result->fetch_assoc()): ?>
          <?php
            // Support either unit_price or price (fallback)
            $price = isset($item['unit_price']) ? (float)$item['unit_price'] : (float)($item['price'] ?? 0);
            $qty   = (int)$item['quantity'];
            $item_total = $price * $qty;
            $total += $item_total;
          ?>
          <div class="order-item">
            <?php if (!empty($item['image_url'])): ?>
              <img src="<?= e($item['image_url']) ?>" alt="<?= e($item['name'] ?? 'Product') ?>" class="item-image">
            <?php else: ?>
              <div class="item-image" style="background:#f8f9fa;display:flex;align-items:center;justify-content:center;">
                <i class="fas fa-image" style="color:#ccc;"></i>
              </div>
            <?php endif; ?>
            <div class="item-details">
              <div class="item-name"><?= e($item['name'] ?? 'Product #'.(int)$item['product_id']) ?></div>
              <div class="item-meta">
                Quantity: <?= $qty ?> × $<?= number_format($price, 2) ?> = $<?= number_format($item_total, 2) ?>
              </div>
              <?php if (!empty($item['size'])): ?><div class="item-meta">Size: <?= e($item['size']) ?></div><?php endif; ?>
              <?php if (!empty($item['color'])): ?><div class="item-meta">Color: <?= e($item['color']) ?></div><?php endif; ?>
            </div>
          </div>
        <?php endwhile; ?>
        <div style="border-top:2px solid #8b5a3c;padding-top:1rem;margin-top:1rem;text-align:right;font-size:1.2rem;font-weight:600;">
          Total: $<?= number_format($total, 2) ?>
        </div>
      <?php else: ?>
        <div style="text-align:center;padding:2rem;color:#666;">
          <i class="fas fa-box-open" style="font-size:3rem;margin-bottom:1rem;opacity:.3;"></i>
          <div>No items found for this order.</div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- Status Modal -->
<div id="statusModal" class="modal" aria-hidden="true">
  <div class="modal-content">
    <button class="close" onclick="closeStatusModal()" aria-label="Close">&times;</button>
    <h2 style="margin-bottom:1rem;">Update Order Status</h2>
    <p id="statusModalText"></p>
    <div style="margin-top:2rem;display:flex;gap:1rem;justify-content:flex-end;">
      <button class="btn btn-outline" onclick="closeStatusModal()">Cancel</button>
      <button class="btn btn-primary" id="confirmStatusUpdate">Confirm</button>
    </div>
  </div>
</div>

<!-- Delete Modal -->
<div id="deleteModal" class="modal" aria-hidden="true">
  <div class="modal-content">
    <button class="close" onclick="closeDeleteModal()" aria-label="Close">&times;</button>
    <h2 style="margin-bottom:1rem;color:#dc3545;">Delete Order</h2>
    <p>Are you sure you want to delete this order? <strong>This action cannot be undone.</strong></p>
    <div style="margin-top:2rem;display:flex;gap:1rem;justify-content:flex-end;">
      <button class="btn btn-outline" onclick="closeDeleteModal()">Cancel</button>
      <button class="btn btn-danger" onclick="deleteOrder()">Delete Order</button>
    </div>
  </div>
</div>

<script>
  // Single source of truth for current order id
  const CURRENT_ORDER_ID = <?= (int)$order['id'] ?>;
  let pendingStatus = null;

  // Delete modal
  function showDeleteModal(){ document.getElementById('deleteModal').style.display = 'block'; }
  function closeDeleteModal(){ document.getElementById('deleteModal').style.display = 'none'; }

  function deleteOrder(){
    fetch('ajax/delete-order.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ order_id: CURRENT_ORDER_ID })
    })
    .then(r => r.ok ? r.json() : Promise.reject(r.status))
    .then(data => {
      if (data.success) {
        showAlert('✅ Order deleted successfully!','success');
        setTimeout(()=>location.href='admin-orders.php', 1000);
      } else {
        showAlert('❌ ' + (data.message || 'Failed to delete.'), 'error');
      }
    })
    .catch(() => showAlert('✅ Order deleted successfully!','error'))
    .finally(closeDeleteModal);
  }

  // Status modal
  function updateOrderStatus(orderId, newStatus){
    pendingStatus = newStatus;
    const map = {
      processing: 'start processing this order',
      shipped: 'mark this order as shipped',
      delivered: 'mark this order as delivered',
      cancelled: 'cancel this order'
    };
    document.getElementById('statusModalText').textContent =
      `Are you sure you want to ${map[newStatus]}?`;
    document.getElementById('statusModal').style.display = 'block';
  }
  function closeStatusModal(){ document.getElementById('statusModal').style.display = 'none'; }

  document.getElementById('confirmStatusUpdate').addEventListener('click', function(){
    if (!pendingStatus) return;
    fetch('ajax/update-order-status.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ order_id: CURRENT_ORDER_ID, status: pendingStatus })
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        showAlert('Order status updated successfully!','success');
        // Update badge instantly
        const badge = document.querySelector('.status-badge');
        if (badge) {
          badge.className = 'status-badge status-' + pendingStatus;
          badge.textContent = (data.status_label || pendingStatus[0].toUpperCase() + pendingStatus.slice(1));
        }
        // Refresh buttons and page
        setTimeout(()=>location.reload(), 1200);
      } else {
        showAlert('Error: ' + (data.message || 'Unable to update status'), 'error');
      }
    })
    .catch(()=> showAlert('An error occurred while updating the order status.','error'))
    .finally(closeStatusModal);
  });

  // Click outside to close modals
  window.addEventListener('click', function(ev){
    const sm = document.getElementById('statusModal');
    const dm = document.getElementById('deleteModal');
    if (ev.target === sm) closeStatusModal();
    if (ev.target === dm) closeDeleteModal();
  });

  // Toast
  function showAlert(message, type){
    document.querySelectorAll('.alert-notification').forEach(a=>a.remove());
    const el = document.createElement('div');
    el.className = 'alert-notification';
    el.style.cssText = `
      position: fixed; top: 20px; right: 20px; padding: 1rem 1.5rem; border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0,0,0,.15); z-index: 1050; max-width: 400px; font-weight: 500;
      ${type === 'success'
        ? 'background:#d4edda;color:#155724;border:1px solid #c3e6cb;'
        : 'background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;'}
    `;
    el.textContent = message;
    document.body.appendChild(el);
    setTimeout(()=>{ if (el.parentNode) el.remove(); }, 5000);
  }
</script>
</body>
</html>
