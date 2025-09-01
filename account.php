<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// ---------- Small fallbacks if your helpers don't exist ----------
if (!function_exists('sanitizeInput')) {
    function sanitizeInput($s) {
        return htmlspecialchars(trim((string)$s), ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('getUserOrders')) {
    // Fallback: returns mysqli_result or null
    function getUserOrders(mysqli $conn, int $user_id) {
        $sql = "SELECT o.id, o.created_at, o.status,
                       COALESCE(SUM(oi.quantity),0)    AS item_count,
                       COALESCE(SUM(oi.quantity * oi.unit_price),0) AS total_amount
                FROM orders o
                LEFT JOIN order_items oi ON oi.order_id = o.id
                WHERE o.user_id = ?
                GROUP BY o.id
                ORDER BY o.created_at DESC
                LIMIT 10";
        if ($st = $conn->prepare($sql)) {
            $st->bind_param('i', $user_id);
            $st->execute();
            return $st->get_result();
        }
        return null;
    }
}
if (!function_exists('getCartCount')) {
    function getCartCount(mysqli $conn, int $user_id): int {
        $sql = "SELECT COALESCE(SUM(quantity),0) AS cnt FROM cart_items WHERE user_id = ?";
        if ($st = $conn->prepare($sql)) {
            $st->bind_param('i', $user_id);
            $st->execute();
            $res = $st->get_result()->fetch_assoc();
            return (int)($res['cnt'] ?? 0);
        }
        return 0;
    }
}
if (!function_exists('isLoggedIn')) {
    function isLoggedIn(): bool { return isset($_SESSION['user_id']); }
}
if (!function_exists('getCurrentUserId')) {
    function getCurrentUserId(): int { return (int)($_SESSION['user_id'] ?? 0); }
}

// ---------- Require login ----------
if (!isLoggedIn()) {
    header('Location: login.php?redirect=' . urlencode('account.php'));
    exit;
}

$user_id = getCurrentUserId();

// ---------- CSRF for profile update ----------
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="'.htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8').'">';
}
function verify_csrf(): bool {
    return isset($_POST['csrf']) && hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf']);
}

// ---------- Fetch user ----------
$user = null;
if ($st = $conn->prepare("SELECT id, email, first_name, last_name, phone, is_admin, created_at FROM users WHERE id = ?")) {
    $st->bind_param('i', $user_id);
    $st->execute();
    $user = $st->get_result()->fetch_assoc();
    $st->close();
}
if (!$user) {
    // If user missing, force logout for safety
    session_destroy();
    header('Location: login.php');
    exit;
}

// ---------- Load dashboard data ----------
$orders     = getUserOrders($conn, $user_id);
$cart_count = getCartCount($conn, $user_id);

// ---------- Handle profile update ----------
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!verify_csrf()) {
        $message = 'Your session has expired. Please try again.';
        $message_type = 'error';
    } else {
        $first_name = sanitizeInput($_POST['first_name'] ?? '');
        $last_name  = sanitizeInput($_POST['last_name'] ?? '');
        $phone_raw  = trim((string)($_POST['phone'] ?? ''));

        // Basic validation
        if ($first_name === '' || mb_strlen($first_name) < 2 || $last_name === '' || mb_strlen($last_name) < 2) {
            $message = 'Please enter a valid first and last name (min. 2 characters).';
            $message_type = 'error';
        } else {
            // Optional phone validation: digits, spaces, +, -, ()
            $phone = '';
            if ($phone_raw !== '') {
                if (!preg_match('/^[0-9+\-\s()]{6,20}$/', $phone_raw)) {
                    $message = 'Please enter a valid phone number.';
                    $message_type = 'error';
                } else {
                    $phone = $phone_raw;
                }
            }

            if ($message_type !== 'error') {
                $sql = "UPDATE users SET first_name = ?, last_name = ?, phone = ?, updated_at = NOW() WHERE id = ?";
                if ($up = $conn->prepare($sql)) {
                    $up->bind_param('sssi', $first_name, $last_name, $phone, $user_id);
                    if ($up->execute()) {
                        $message = 'Profile updated successfully!';
                        $message_type = 'success';
                        $_SESSION['user_name'] = $first_name . ' ' . $last_name;

                        // Re-fetch user
                        if ($st = $conn->prepare("SELECT id, email, first_name, last_name, phone, is_admin, created_at FROM users WHERE id = ?")) {
                            $st->bind_param('i', $user_id);
                            $st->execute();
                            $user = $st->get_result()->fetch_assoc();
                            $st->close();
                        }
                    } else {
                        $message = 'Error updating profile. Please try again.';
                        $message_type = 'error';
                    }
                    $up->close();
                } else {
                    $message = 'Server error. Please try again later.';
                    $message_type = 'error';
                }
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
  <title>My Account - Velvet Vogue</title>
  <meta name="description" content="Manage your Velvet Vogue account, view orders, update profile information, and track your shopping activity.">
  <link rel="stylesheet" href="assets/css/style.css">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <style>
    .account-grid{display:grid;grid-template-columns:1fr 2fr;gap:3rem}
    .card{background:#fff;padding:2rem;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.1)}
    .alert{padding:1rem;border-radius:8px;margin-bottom:1.25rem}
    .alert-success{background:#d4edda;color:#155724;border-left:4px solid #28a745}
    .alert-error{background:#f8d7da;color:#721c24;border-left:4px solid #dc3545}
    .btn{display:inline-flex;align-items:center;gap:.5rem;padding:.65rem 1rem;border-radius:8px;text-decoration:none}
    .btn-primary{background:linear-gradient(135deg,#8b5a3c,#a0522d);color:#fff;border:none}
    .btn-outline{border:2px solid #8b5a3c;color:#8b5a3c;background:#fff}
    .btn-secondary{background:#6c757d;color:#fff;border:none}
    .form-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
    .form-group{margin-bottom:1rem}
    .form-group label{display:block;margin-bottom:.35rem}
    .form-group input{width:100%}
    @media (max-width: 900px){.account-grid{grid-template-columns:1fr}}
  </style>
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<section style="padding:6rem 0 4rem;background:#f8f9fa;min-height:100vh;">
  <div class="container">
    <!-- Page Header -->
    <div style="margin-bottom:3rem;">
      <h1 style="color:#2c3e50;margin-bottom:.5rem;">My Account</h1>
      <p style="color:#666;">Welcome back, <?= htmlspecialchars($user['first_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>!</p>
    </div>

    <?php if (!empty($message)): ?>
      <div class="alert <?= $message_type === 'success' ? 'alert-success' : 'alert-error' ?>">
        <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
      </div>
    <?php endif; ?>

    <div class="account-grid">
      <!-- Sidebar -->
      <div>
        <div class="card" style="margin-bottom:2rem;">
          <h3 style="margin-bottom:1.5rem;color:#2c3e50;">Account Overview</h3>
          <div style="display:flex;align-items:center;margin-bottom:1rem;">
            <i class="fas fa-user" style="color:#8b5a3c;margin-right:1rem;width:20px;"></i>
            <div>
              <strong><?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong><br>
              <small style="color:#666;"><?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></small>
            </div>
          </div>
          <div style="display:flex;align-items:center;margin-bottom:1rem;">
            <i class="fas fa-shopping-cart" style="color:#8b5a3c;margin-right:1rem;width:20px;"></i>
            <span><?= (int)$cart_count ?> items in cart</span>
          </div>
          <div style="display:flex;align-items:center;margin-bottom:1rem;">
            <i class="fas fa-box" style="color:#8b5a3c;margin-right:1rem;width:20px;"></i>
            <span>
              <?php
                $orderCount = 0;
                if ($orders instanceof mysqli_result) $orderCount = $orders->num_rows;
                elseif (is_array($orders)) $orderCount = count($orders);
                echo (int)$orderCount;
              ?> total orders
            </span>
          </div>
          <div style="display:flex;align-items:center;">
            <i class="fas fa-calendar" style="color:#8b5a3c;margin-right:1rem;width:20px;"></i>
            <span>Member since <?= htmlspecialchars(date('M Y', strtotime($user['created_at'] ?? date('Y-m-d'))), ENT_QUOTES, 'UTF-8') ?></span>
          </div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
          <h3 style="margin-bottom:1.5rem;color:#2c3e50;">Quick Actions</h3>
          <a href="cart.php" class="btn btn-primary" style="width:100%;margin-bottom:1rem;"><i class="fas fa-shopping-cart"></i> View Cart</a>
          <a href="products.php" class="btn btn-outline" style="width:100%;margin-bottom:1rem;"><i class="fas fa-search"></i> Browse Products</a>
          <?php if (!empty($user['is_admin'])): ?>
            <a href="admin.php" class="btn btn-secondary" style="width:100%;margin-bottom:1rem;"><i class="fas fa-cogs"></i> Admin Panel</a>
          <?php endif; ?>
          <a href="logout.php" class="btn btn-outline" style="width:100%;color:#e74c3c;border-color:#e74c3c;"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
      </div>

      <!-- Main Content -->
      <div>
        <!-- Profile -->
        <div class="card" style="margin-bottom:2rem;">
          <h3 style="margin-bottom:2rem;color:#2c3e50;">Profile Information</h3>
          <form method="POST" action="account.php" novalidate>
            <?= csrf_field(); ?>
            <div class="form-row">
              <div class="form-group">
                <label for="first_name">First Name</label>
                <input type="text" id="first_name" name="first_name" required minlength="2"
                  value="<?= htmlspecialchars($user['first_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
              </div>
              <div class="form-group">
                <label for="last_name">Last Name</label>
                <input type="text" id="last_name" name="last_name" required minlength="2"
                  value="<?= htmlspecialchars($user['last_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
              </div>
            </div>

            <div class="form-group">
              <label for="email">Email Address</label>
              <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly
                     style="background:#f8f9fa;cursor:not-allowed;">
              <small style="color:#666;">Email cannot be changed. Contact support if needed.</small>
            </div>

            <div class="form-group">
              <label for="phone">Phone Number</label>
              <input type="tel" id="phone" name="phone"
                     value="<?= htmlspecialchars($user['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                     placeholder="Enter your phone number">
            </div>

            <button type="submit" name="update_profile" class="btn btn-primary">
              <i class="fas fa-save"></i> Update Profile
            </button>
          </form>
        </div>

        <!-- Recent Orders -->
        <div class="card">
          <h3 style="margin-bottom:2rem;color:#2c3e50;">Recent Orders</h3>
          <?php if ($orders instanceof mysqli_result && $orders->num_rows > 0): ?>
            <div style="overflow-x:auto;">
              <table style="width:100%;border-collapse:collapse;">
                <thead>
                  <tr style="background:#f8f9fa;">
                    <th style="padding:1rem;text-align:left;border-bottom:1px solid #ddd;">Order #</th>
                    <th style="padding:1rem;text-align:left;border-bottom:1px solid #ddd;">Date</th>
                    <th style="padding:1rem;text-align:left;border-bottom:1px solid #ddd;">Items</th>
                    <th style="padding:1rem;text-align:left;border-bottom:1px solid #ddd;">Total</th>
                    <th style="padding:1rem;text-align:left;border-bottom:1px solid #ddd;">Status</th>
                    <th style="padding:1rem;text-align:left;border-bottom:1px solid #ddd;">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php while ($order = $orders->fetch_assoc()): ?>
                    <tr>
                      <td style="padding:1rem;border-bottom:1px solid #eee;">#<?= (int)$order['id'] ?></td>
                      <td style="padding:1rem;border-bottom:1px solid #eee;"><?= htmlspecialchars(date('M j, Y', strtotime($order['created_at'])), ENT_QUOTES, 'UTF-8') ?></td>
                      <td style="padding:1rem;border-bottom:1px solid #eee;"><?= (int)$order['item_count'] ?> item<?= ((int)$order['item_count'] !== 1 ? 's':'') ?></td>
                      <td style="padding:1rem;border-bottom:1px solid #eee;">$<?= number_format((float)$order['total_amount'], 2) ?></td>
                      <td style="padding:1rem;border-bottom:1px solid #eee;">
                        <?php
                          $status = strtolower((string)$order['status']);
                          $style  = '#f8f9fa; color:#666;';
                          if ($status === 'pending')    $style = '#fff3cd; color:#856404;';
                          if ($status === 'processing') $style = '#cce7ff; color:#004085;';
                          if ($status === 'shipped')    $style = '#d1ecf1; color:#0c5460;';
                          if ($status === 'delivered')  $style = '#d4edda; color:#155724;';
                          if ($status === 'cancelled')  $style = '#f8d7da; color:#721c24;';
                        ?>
                        <span style="padding:.25rem .75rem;border-radius:20px;font-size:.8rem;background:<?= $style ?>"><?= htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8') ?></span>
                      </td>
                      <td style="padding:1rem;border-bottom:1px solid #eee;">
                        <a href="order-details.php?id=<?= (int)$order['id'] ?>" class="btn btn-outline" style="font-size:.85rem;padding:.4rem .8rem;">View Details</a>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <div style="text-align:center;padding:3rem;color:#666;">
              <i class="fas fa-shopping-bag" style="font-size:3rem;margin-bottom:1rem;color:#ccc;"></i>
              <h4 style="margin-bottom:1rem;">No orders yet</h4>
              <p style="margin-bottom:2rem;">Start shopping to see your orders here!</p>
              <a href="products.php" class="btn btn-primary">Browse Products</a>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
<script src="assets/js/main.js"></script>
</body>
</html>
