<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// ---- Auth: require login + admin ----
if (!function_exists('isLoggedIn')) { function isLoggedIn(){ return isset($_SESSION['user_id']); } }
if (!function_exists('getCurrentUserId')) { function getCurrentUserId(){ return (int)($_SESSION['user_id'] ?? 0); } }

if (!isLoggedIn()) {
    header('Location: login.php?redirect=admin-customer-detail.php');
    exit;
}

$user_id = getCurrentUserId();
$user_stmt = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$is_admin_row = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();

if (!$is_admin_row || (int)$is_admin_row['is_admin'] !== 1) {
    header('Location: account.php');
    exit;
}

// ---- Input: customer id ----
$customer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($customer_id <= 0) {
    header('Location: admin-customers.php');
    exit;
}

// ---- Fetch customer + billing/shipping addresses ----
$customer_sql = "
    SELECT u.*,
           b.address_line_1 AS billing_address_1,
           b.address_line_2 AS billing_address_2,
           b.city AS billing_city,
           b.state AS billing_state,
           b.postal_code AS billing_postal_code,
           b.country AS billing_country,
           s.address_line_1 AS shipping_address_1,
           s.address_line_2 AS shipping_address_2,
           s.city AS shipping_city,
           s.state AS shipping_state,
           s.postal_code AS shipping_postal_code,
           s.country AS shipping_country
    FROM users u
    LEFT JOIN user_addresses b ON b.user_id = u.id AND b.type = 'billing'
    LEFT JOIN user_addresses s ON s.user_id = u.id AND s.type = 'shipping'
    WHERE u.id = ? AND u.is_admin = 0
    LIMIT 1
";
$customer_stmt = $conn->prepare($customer_sql);
$customer_stmt->bind_param("i", $customer_id);
$customer_stmt->execute();
$customer = $customer_stmt->get_result()->fetch_assoc();
$customer_stmt->close();

if (!$customer) {
    header('Location: admin-customers.php?notfound=1');
    exit;
}

// ---- Aggregate stats for customer ----
$stats = [
    'total_orders' => 0, 'total_spent' => 0, 'avg_order_value' => 0,
    'last_order_date' => null, 'first_order_date' => null,
    'pending_orders' => 0, 'processing_orders' => 0, 'shipped_orders' => 0,
    'delivered_orders' => 0, 'cancelled_orders' => 0,
    'last_30_days_spent' => 0, 'last_30_days_orders' => 0
];

$stats_sql = "SELECT 
    COUNT(o.id) as total_orders,
    COALESCE(SUM(o.total_amount),0) as total_spent,
    COALESCE(AVG(o.total_amount),0) as avg_order_value,
    MAX(o.created_at) as last_order_date,
    MIN(o.created_at) as first_order_date,
    SUM(o.status = 'pending') as pending_orders,
    SUM(o.status = 'processing') as processing_orders,
    SUM(o.status = 'shipped') as shipped_orders,
    SUM(o.status = 'delivered') as delivered_orders,
    SUM(o.status = 'cancelled') as cancelled_orders,
    COALESCE(SUM(CASE WHEN o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN o.total_amount END),0) as last_30_days_spent,
    SUM(o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as last_30_days_orders
  FROM orders o
  WHERE o.user_id = ?";

$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("i", $customer_id);
$stats_stmt->execute();
$stats_row = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();
if ($stats_row) { $stats = array_merge($stats, $stats_row); }

// ---- Orders list (summary per order) ----
$orders_result = [];
$orders_sql = "SELECT o.*,
                      COUNT(oi.id) AS item_count,
                      COALESCE(SUM(oi.quantity),0) AS total_items
               FROM orders o
               LEFT JOIN order_items oi ON oi.order_id = o.id
               WHERE o.user_id = ?
               GROUP BY o.id
               ORDER BY o.created_at DESC";
$orders_stmt = $conn->prepare($orders_sql);
$orders_stmt->bind_param("i", $customer_id);
$orders_stmt->execute();
$res = $orders_stmt->get_result();
while ($row = $res->fetch_assoc()) { $orders_result[] = $row; }
$orders_stmt->close();

// ---- Status counts ----
$status_counts = [];
$status_sql = "SELECT status, COUNT(*) AS count FROM orders WHERE user_id = ? GROUP BY status";
$status_stmt = $conn->prepare($status_sql);
$status_stmt->bind_param("i", $customer_id);
$status_stmt->execute();
$res = $status_stmt->get_result();
while ($row = $res->fetch_assoc()) { $status_counts[$row['status']] = (int)$row['count']; }
$status_stmt->close();

// ---- Optional tables (preferences, login logs, wishlist, cart) ----
function tableExists(mysqli $conn, string $table): bool {
    $table = $conn->real_escape_string($table);
    $sql = "SHOW TABLES LIKE '$table'";
    $res = $conn->query($sql);
    return $res && $res->num_rows > 0;
}

$preferences = [];
if (tableExists($conn, 'customer_preferences')) {
    $p_stmt = $conn->prepare("SELECT * FROM customer_preferences WHERE customer_id = ? LIMIT 1");
    $p_stmt->bind_param("i", $customer_id);
    $p_stmt->execute();
    $preferences = $p_stmt->get_result()->fetch_assoc() ?: [];
    $p_stmt->close();
}

$login_activity = [];
if (tableExists($conn, 'user_login_logs')) {
    $l_stmt = $conn->prepare("SELECT * FROM user_login_logs WHERE user_id = ? ORDER BY login_time DESC LIMIT 10");
    $l_stmt->bind_param("i", $customer_id);
    $l_stmt->execute();
    $res = $l_stmt->get_result();
    while ($row = $res->fetch_assoc()) { $login_activity[] = $row; }
    $l_stmt->close();
}

$wishlist_count = 0;
if (tableExists($conn, 'wishlists')) {
    $w_stmt = $conn->prepare("SELECT COUNT(*) AS c FROM wishlists WHERE user_id = ?");
    $w_stmt->bind_param("i", $customer_id);
    $w_stmt->execute();
    $wishlist_count = (int)($w_stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $w_stmt->close();
}

$cart_count = 0;
if (tableExists($conn, 'cart_items')) {
    $c_stmt = $conn->prepare("SELECT COALESCE(SUM(quantity),0) AS c FROM cart_items WHERE user_id = ?");
    $c_stmt->bind_param("i", $customer_id);
    $c_stmt->execute();
    $cart_count = (int)($c_stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $c_stmt->close();
}

// helper for safe out
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>
  Customer Details - <?= e(trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''))) ?> - Velvet Vogue Admin
</title>
<meta name="description" content="Customer profile and order history">
<link rel="stylesheet" href="assets/css/style.css">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
  .customer-header{background:#fff;padding:2rem;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.1);margin-bottom:2rem}
  .customer-avatar{width:80px;height:80px;border-radius:50%;background:#8b5a3c;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:bold;font-size:2rem}
  .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1.5rem;margin-bottom:2rem}
  .stat-card{background:#fff;padding:1.5rem;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.1);text-align:center}
  .orders-container{background:#fff;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.1);overflow:hidden}
  .status-badge{padding:.25rem .75rem;border-radius:20px;font-size:.8rem;font-weight:500}
  .status-pending{background:#fff3cd;color:#856404}
  .status-processing{background:#cce7ff;color:#004085}
  .status-shipped{background:#d1ecf1;color:#0c5460}
  .status-delivered{background:#d4edda;color:#155724}
  .status-cancelled{background:#f8d7da;color:#721c24}
  .info-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:2rem;margin-bottom:2rem}
  .info-card{background:#fff;padding:1.5rem;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.1)}
  .address-card{background:#f8f9fa;padding:1rem;border-radius:8px;margin-bottom:1rem;border-left:4px solid #8b5a3c}
  .data-table{width:100%;border-collapse:collapse}
  .data-table td{padding:.5rem 0;border-bottom:1px solid #eee}
  .data-table td:first-child{font-weight:500;width:40%;color:#666}
  .expandable-section{margin-bottom:2rem}
  .section-toggle{background:#fff;border:none;padding:1rem 1.5rem;width:100%;text-align:left;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.1);cursor:pointer;font-size:1.1rem;font-weight:600;color:#2c3e50;display:flex;justify-content:space-between;align-items:center}
  .section-content{background:#fff;border-radius:0 0 10px 10px;padding:1.5rem;margin-top:-10px;box-shadow:0 2px 10px rgba(0,0,0,.1)}
</style>
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<section style="padding:6rem 0 4rem;background:#f8f9fa;min-height:100vh;">
  <div class="container">
    <!-- Top bar -->
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem;">
      <div>
        <h1 style="color:#2c3e50;margin:0;">Customer Details</h1>
        <p style="color:#666;margin:.5rem 0 0;">Complete customer profile and order history</p>
      </div>
      <a href="admin-customers.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Customers</a>
    </div>

    <!-- Customer Header -->
    <div class="customer-header" style="background:#f9f9f9;padding:1.5rem 2rem;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,.05);">
      <div style="display:flex;align-items:center;gap:2rem;flex-wrap:wrap;">
        <?php
          $initials = '';
          if (!empty($customer['first_name'])) $initials .= strtoupper(substr($customer['first_name'],0,1));
          if (!empty($customer['last_name']))  $initials .= strtoupper(substr($customer['last_name'],0,1));
          if ($initials === '' && !empty($customer['email'])) $initials = strtoupper(substr($customer['email'],0,1));
        ?>
        <div class="customer-avatar" aria-hidden="true"><?= e($initials ?: '?') ?></div>
        <div style="flex:1;min-width:250px;">
          <h2 style="margin:0;font-size:1.8rem;color:#2c3e50;display:flex;align-items:center;gap:.5rem;">
            <?= e(trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''))) ?>
            <?php if (!empty($customer['email_verified'])): ?>
              <i class="fas fa-check-circle" style="color:#28a745;" title="Email Verified"></i>
            <?php endif; ?>
          </h2>
          <p style="color:#555;margin:.3rem 0;"><i class="fas fa-envelope" style="margin-right:5px;"></i><?= e($customer['email'] ?? 'No email') ?></p>
          <?php if (!empty($customer['phone'])): ?>
            <p style="color:#555;margin:.3rem 0;"><i class="fas fa-phone" style="margin-right:5px;"></i><?= e($customer['phone']) ?></p>
          <?php endif; ?>
          <p style="color:#555;margin:.3rem 0;"><i class="fas fa-calendar-alt" style="margin-right:5px;"></i>
            Member since <?= !empty($customer['created_at']) ? e(date('M j, Y', strtotime($customer['created_at']))) : 'Unknown' ?>
          </p>
          <?php if (!empty($customer['last_login'])): ?>
            <p style="color:#555;margin:.3rem 0;"><i class="fas fa-clock" style="margin-right:5px;"></i>
              Last seen <?= e(date('M j, Y g:i A', strtotime($customer['last_login']))) ?>
            </p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon" style="color:#8b5a3c;"><i class="fas fa-shopping-bag"></i></div>
        <div class="stat-value"><?= (int)($stats['total_orders'] ?? 0) ?></div>
        <div class="stat-label">Total Orders</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="color:#28a745;"><i class="fas fa-dollar-sign"></i></div>
        <div class="stat-value">$<?= number_format((float)($stats['total_spent'] ?? 0), 2) ?></div>
        <div class="stat-label">Total Spent</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="color:#007bff;"><i class="fas fa-chart-line"></i></div>
        <div class="stat-value">$<?= number_format((float)($stats['avg_order_value'] ?? 0), 2) ?></div>
        <div class="stat-label">Avg. Order Value</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="color:#6c757d;"><i class="fas fa-clock"></i></div>
        <div class="stat-value">
          <?php
            if (!empty($stats['last_order_date'])) {
              $days = max(0, ceil((time() - strtotime($stats['last_order_date'])) / 86400));
              echo $days . ' days';
            } else { echo 'Never'; }
          ?>
        </div>
        <div class="stat-label">Last Order</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="color:#17a2b8;"><i class="fas fa-calendar-alt"></i></div>
        <div class="stat-value">$<?= number_format((float)($stats['last_30_days_spent'] ?? 0), 2) ?></div>
        <div class="stat-label">Last 30 Days</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="color:#ffc107;"><i class="fas fa-heart"></i></div>
        <div class="stat-value"><?= (int)$wishlist_count ?></div>
        <div class="stat-label">Wishlist Items</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="color:#fd7e14;"><i class="fas fa-shopping-cart"></i></div>
        <div class="stat-value"><?= (int)$cart_count ?></div>
        <div class="stat-label">Cart Items</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="color:#dc3545;"><i class="fas fa-times-circle"></i></div>
        <div class="stat-value"><?= (int)($stats['cancelled_orders'] ?? 0) ?></div>
        <div class="stat-label">Cancelled Orders</div>
      </div>
    </div>

    <!-- Info cards -->
    <div class="info-grid">
      <!-- Personal -->
      <div class="info-card" style="padding:2rem;">
        <h3 style="color:#2c3e50;font-size:1.5rem;margin-bottom:1rem;border-bottom:2px solid #eee;padding-bottom:.5rem;">👤 Personal Information</h3>
        <table class="data-table" style="width:100%;">
          <?php
            $fields = [
                'Customer ID'   => (int)($customer['id'] ?? 0),
                'First Name'    => e($customer['first_name'] ?? 'Not provided'),
                'Last Name'     => e($customer['last_name'] ?? 'Not provided'),
                'Email'         => e($customer['email'] ?? 'Not provided'),
                'Email Verified'=> isset($customer['email_verified']) ? ($customer['email_verified'] ? '<span style="color:#28a745;font-weight:600;">Yes</span>' : '<span style="color:#dc3545;font-weight:600;">No</span>') : 'N/A',
                'Phone'         => !empty($customer['phone']) ? e($customer['phone']) : '<span style="color:#999;">Not provided</span>',
                'Date of Birth' => !empty($customer['date_of_birth']) ? e(date('M j, Y', strtotime($customer['date_of_birth']))) : '<span style="color:#999;">Not provided</span>',
                'Gender'        => !empty($customer['gender']) ? e(ucfirst($customer['gender'])) : '<span style="color:#999;">Not specified</span>',
                'Registered'    => !empty($customer['created_at']) ? e(date('M j, Y g:i A', strtotime($customer['created_at']))) : 'N/A',
                'Last Updated'  => !empty($customer['updated_at']) ? e(date('M j, Y g:i A', strtotime($customer['updated_at']))) : 'N/A'
            ];
            if (!empty($customer['last_login'])) {
                $fields['Last Login'] = e(date('M j, Y g:i A', strtotime($customer['last_login'])));
            }
            foreach ($fields as $label => $value) {
                echo '<tr><td>'.$label.':</td><td>'.$value.'</td></tr>';
            }
          ?>
        </table>
      </div>

      <!-- Account status -->
      <div class="info-card" style="padding:2rem;">
        <h3 style="color:#2c3e50;font-size:1.5rem;margin-bottom:1rem;border-bottom:2px solid #eee;padding-bottom:.5rem;">⚙️ Account Status & Settings</h3>
        <table class="data-table" style="width:100%;">
          <?php
            $fields2 = [
              'Status'            => (!isset($customer['is_active']) || (int)$customer['is_active'] === 1) ? '<span style="color:#28a745;font-weight:600;">Active</span>' : '<span style="color:#dc3545;font-weight:600;">Inactive</span>',
              'Newsletter'        => !empty($customer['newsletter_subscribed']) ? '<span style="color:#28a745;font-weight:600;">Subscribed</span>' : '<span style="color:#999;">Not subscribed</span>',
              'Marketing Emails'  => !empty($customer['marketing_emails']) ? '<span style="color:#28a745;font-weight:600;">Enabled</span>' : '<span style="color:#999;">Disabled</span>',
              'SMS Notifications' => !empty($customer['sms_notifications']) ? '<span style="color:#28a745;font-weight:600;">Enabled</span>' : '<span style="color:#999;">Disabled</span>',
              'Two-Factor Auth'   => !empty($customer['two_factor_enabled']) ? '<span style="color:#28a745;font-weight:600;">Enabled</span>' : '<span style="color:#999;">Disabled</span>',
              'Preferred Language' => e($customer['preferred_language'] ?? 'English (default)'),
              'Timezone'           => e($customer['timezone'] ?? 'UTC (default)')
            ];
            foreach ($fields2 as $label => $value) {
                echo '<tr><td>'.$label.':</td><td>'.$value.'</td></tr>';
            }
          ?>
        </table>
      </div>

      <!-- Order status summary -->
      <div class="info-card">
        <h3 style="color:#2c3e50;margin:0 0 1rem;">Order Status Summary</h3>
        <?php if (!empty($status_counts)): ?>
          <div style="display:flex;flex-direction:column;gap:.5rem;">
            <?php foreach ($status_counts as $status => $count): 
                $cls = 'status-'.preg_replace('/[^a-z]/','', strtolower($status));
            ?>
              <div style="display:flex;justify-content:space-between;align-items:center;">
                <span class="status-badge <?= e($cls) ?>"><?= e(ucfirst($status)) ?></span>
                <strong><?= (int)$count ?></strong>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p style="color:#666;margin:0;">No orders yet</p>
        <?php endif; ?>
      </div>

      <!-- Preferences -->
      <?php if (!empty($preferences)): ?>
        <div class="info-card">
          <h3 style="color:#2c3e50;margin:0 0 1rem;">Preferences</h3>
          <table class="data-table">
            <?php foreach ($preferences as $k => $v): if (in_array($k, ['id','customer_id'], true)) continue; ?>
              <tr><td><?= e(ucwords(str_replace('_',' ', $k))) ?>:</td><td><?= e($v) ?></td></tr>
            <?php endforeach; ?>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <!-- Addresses -->
    <?php if (!empty($customer['billing_address_1']) || !empty($customer['shipping_address_1'])): ?>
      <div class="expandable-section">
        <button class="section-toggle" onclick="toggleSection('addresses')">
          <span><i class="fas fa-map-marker-alt"></i> Address Information</span>
          <i class="fas fa-chevron-down" id="addresses-icon"></i>
        </button>
        <div class="section-content" id="addresses-content" style="display:none;">
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:2rem;">
            <?php if (!empty($customer['billing_address_1'])): ?>
              <div class="address-card">
                <h4 style="color:#2c3e50;margin:0 0 1rem;"><i class="fas fa-credit-card"></i> Billing Address</h4>
                <p style="margin:.25rem 0;"><?= e($customer['billing_address_1']) ?></p>
                <?php if (!empty($customer['billing_address_2'])): ?>
                  <p style="margin:.25rem 0;"><?= e($customer['billing_address_2']) ?></p>
                <?php endif; ?>
                <p style="margin:.25rem 0;">
                  <?= e($customer['billing_city']) ?>, <?= e($customer['billing_state']) ?> <?= e($customer['billing_postal_code']) ?>
                </p>
                <p style="margin:.25rem 0;"><?= e($customer['billing_country']) ?></p>
              </div>
            <?php endif; ?>

            <?php if (!empty($customer['shipping_address_1'])): ?>
              <div class="address-card">
                <h4 style="color:#2c3e50;margin:0 0 1rem;"><i class="fas fa-shipping-fast"></i> Shipping Address</h4>
                <p style="margin:.25rem 0;"><?= e($customer['shipping_address_1']) ?></p>
                <?php if (!empty($customer['shipping_address_2'])): ?>
                  <p style="margin:.25rem 0;"><?= e($customer['shipping_address_2']) ?></p>
                <?php endif; ?>
                <p style="margin:.25rem 0;">
                  <?= e($customer['shipping_city']) ?>, <?= e($customer['shipping_state']) ?> <?= e($customer['shipping_postal_code']) ?>
                </p>
                <p style="margin:.25rem 0;"><?= e($customer['shipping_country']) ?></p>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <!-- Login activity -->
    <?php if (!empty($login_activity)): ?>
      <div class="expandable-section">
        <button class="section-toggle" onclick="toggleSection('activity')">
          <span><i class="fas fa-history"></i> Recent Login Activity</span>
          <i class="fas fa-chevron-down" id="activity-icon"></i>
        </button>
        <div class="section-content" id="activity-content" style="display:none;">
          <table style="width:100%;border-collapse:collapse;">
            <thead>
              <tr style="background:#f8f9fa;">
                <th style="padding:1rem;text-align:left;border-bottom:2px solid #dee2e6;">Date & Time</th>
                <th style="padding:1rem;text-align:left;border-bottom:2px solid #dee2e6;">IP Address</th>
                <th style="padding:1rem;text-align:left;border-bottom:2px solid #dee2e6;">User Agent</th>
                <th style="padding:1rem;text-align:left;border-bottom:2px solid #dee2e6;">Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($login_activity as $a): ?>
                <tr>
                  <td style="padding:.75rem;border-bottom:1px solid #dee2e6;"><?= e(date('M j, Y g:i A', strtotime($a['login_time']))) ?></td>
                  <td style="padding:.75rem;border-bottom:1px solid #dee2e6;"><?= e($a['ip_address'] ?? 'Unknown') ?></td>
                  <td style="padding:.75rem;border-bottom:1px solid #dee2e6;"><?= e(mb_strimwidth($a['user_agent'] ?? 'Unknown', 0, 80, '…')) ?></td>
                  <td style="padding:.75rem;border-bottom:1px solid #dee2e6;">
                    <span class="status-badge <?= !empty($a['success']) ? 'status-delivered' : 'status-cancelled' ?>">
                      <?= !empty($a['success']) ? 'Success' : 'Failed' ?>
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

    <!-- Orders table -->
    <div class="orders-container">
      <div style="padding:1.5rem;border-bottom:1px solid #eee;">
        <h3 style="color:#2c3e50;margin:0;">Complete Order History (<?= count($orders_result) ?>)</h3>
      </div>

      <?php if (!empty($orders_result)): ?>
        <div style="overflow-x:auto;">
          <table style="width:100%;border-collapse:collapse;">
            <thead>
              <tr style="background:#f8f9fa;">
                <th style="padding:1rem;text-align:left;border-bottom:2px solid #dee2e6;">Order #</th>
                <th style="padding:1rem;text-align:left;border-bottom:2px solid #dee2e6;">Date</th>
                <th style="padding:1rem;text-align:left;border-bottom:2px solid #dee2e6;">Status</th>
                <th style="padding:1rem;text-align:left;border-bottom:2px solid #dee2e6;">Items</th>
                <th style="padding:1rem;text-align:left;border-bottom:2px solid #dee2e6;">Total</th>
                <th style="padding:1rem;text-align:left;border-bottom:2px solid #dee2e6;">Payment</th>
                <th style="padding:1rem;text-align:left;border-bottom:2px solid #dee2e6;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($orders_result as $order): 
                $status = strtolower((string)$order['status']);
                $cls = 'status-'.preg_replace('/[^a-z]/','', $status);
                $total_amount = (float)($order['total_amount'] ?? 0);
                $discount = (float)($order['discount_amount'] ?? 0);
                $pay_status = strtolower((string)($order['payment_status'] ?? 'pending'));
              ?>
                <tr>
                  <td style="padding:.75rem;border-bottom:1px solid #dee2e6;"><strong>#<?= (int)$order['id'] ?></strong></td>
                  <td style="padding:.75rem;border-bottom:1px solid #dee2e6;">
                    <?= e(date('M j, Y', strtotime($order['created_at']))) ?><br>
                    <small style="color:#666;"><?= e(date('g:i A', strtotime($order['created_at']))) ?></small>
                  </td>
                  <td style="padding:.75rem;border-bottom:1px solid #dee2e6;">
                    <span class="status-badge <?= e($cls) ?>"><?= e(ucfirst($status)) ?></span>
                  </td>
                  <td style="padding:.75rem;border-bottom:1px solid #dee2e6;">
                    <?= (int)($order['total_items'] ?? 0) ?> items<br>
                    <small style="color:#666;"><?= (int)($order['item_count'] ?? 0) ?> products</small>
                  </td>
                  <td style="padding:.75rem;border-bottom:1px solid #dee2e6;">
                    <strong>$<?= number_format($total_amount, 2) ?></strong>
                    <?php if ($discount > 0): ?>
                      <br><small style="color:#28a745;">- $<?= number_format($discount, 2) ?> discount</small>
                    <?php endif; ?>
                  </td>
                  <td style="padding:.75rem;border-bottom:1px solid #dee2e6;">
                    <?= e(ucfirst($order['payment_method'] ?? 'N/A')) ?><br>
                    <span class="status-badge <?= $pay_status === 'paid' ? 'status-delivered' : 'status-pending' ?>">
                      <?= e(ucfirst($order['payment_status'] ?? 'Pending')) ?>
                    </span>
                  </td>
                  <td style="padding:.75rem;border-bottom:1px solid #dee2e6;">
                    <a href="admin-order-detail.php?id=<?= (int)$order['id'] ?>" style="color:#8b5a3c;text-decoration:none;">
                      <i class="fas fa-eye"></i> View
                    </a>
                    <br>
                    <button onclick="toggleOrderDetails(<?= (int)$order['id'] ?>)"
                            style="background:none;border:none;color:#007bff;cursor:pointer;font-size:.9rem;">
                      <i class="fas fa-info-circle"></i> Details
                    </button>
                  </td>
                </tr>

                <!-- Collapsible details row -->
                <tr id="order-details-<?= (int)$order['id'] ?>" style="display:none;">
                  <td colspan="7" style="padding:0;">
                    <div class="order-detail-container" style="margin-top:1rem;border-top:1px solid #eee;padding:1rem;">
                      <?php
                        $items_sql = "SELECT oi.*, p.name AS product_name, p.sku
                                      FROM order_items oi
                                      LEFT JOIN products p ON p.id = oi.product_id
                                      WHERE oi.order_id = ?";
                        $items_stmt = $conn->prepare($items_sql);
                        $oid = (int)$order['id'];
                        $items_stmt->bind_param("i", $oid);
                        $items_stmt->execute();
                        $items_res = $items_stmt->get_result();
                      ?>
                      <h4 style="color:#2c3e50;margin:0 0 1rem;">Order Items</h4>
                      <table style="width:100%;border-collapse:collapse;">
                        <thead>
                          <tr style="background:#f8f9fa;">
                            <th style="padding:.5rem;text-align:left;">Product</th>
                            <th style="padding:.5rem;text-align:left;">SKU</th>
                            <th style="padding:.5rem;text-align:center;">Qty</th>
                            <th style="padding:.5rem;text-align:right;">Price</th>
                            <th style="padding:.5rem;text-align:right;">Total</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php while ($it = $items_res->fetch_assoc()): ?>
                            <tr>
                              <td style="padding:.5rem;"><?= e($it['product_name'] ?? ('Product #'.(int)$it['product_id'])) ?></td>
                              <td style="padding:.5rem;"><?= e($it['sku'] ?? 'N/A') ?></td>
                              <td style="padding:.5rem;text-align:center;"><?= (int)($it['quantity'] ?? 0) ?></td>
                              <td style="padding:.5rem;text-align:right;">$<?= number_format((float)($it['price'] ?? 0), 2) ?></td>
                              <td style="padding:.5rem;text-align:right;">$<?= number_format((float)($it['price'] ?? 0) * (int)($it['quantity'] ?? 0), 2) ?></td>
                            </tr>
                          <?php endwhile; $items_stmt->close(); ?>
                        </tbody>
                      </table>

                      <?php if (!empty($order['shipping_address']) || !empty($order['billing_address'])): ?>
                        <div style="margin-top:1rem;display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                          <?php if (!empty($order['shipping_address'])): ?>
                            <div>
                              <h5 style="color:#2c3e50;margin:0 0 .5rem;">Shipping Address</h5>
                              <p style="margin:0;font-size:.9rem;color:#666;"><?= nl2br(e($order['shipping_address'])) ?></p>
                            </div>
                          <?php endif; ?>
                          <?php if (!empty($order['billing_address'])): ?>
                            <div>
                              <h5 style="color:#2c3e50;margin:0 0 .5rem;">Billing Address</h5>
                              <p style="margin:0;font-size:.9rem;color:#666;"><?= nl2br(e($order['billing_address'])) ?></p>
                            </div>
                          <?php endif; ?>
                        </div>
                      <?php endif; ?>

                      <?php if (!empty($order['notes'])): ?>
                        <div style="margin-top:1rem;">
                          <h5 style="color:#2c3e50;margin:0 0 .5rem;">Order Notes</h5>
                          <p style="margin:0;font-size:.9rem;color:#666;font-style:italic;"><?= nl2br(e($order['notes'])) ?></p>
                        </div>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div style="padding:2rem;text-align:center;color:#666;">
          <i class="fas fa-shopping-cart" style="font-size:3rem;margin-bottom:1rem;opacity:.3;"></i>
          <p style="margin:0;">This customer hasn't placed any orders yet.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
<script>
  function toggleSection(id){
    const c = document.getElementById(id+'-content');
    const i = document.getElementById(id+'-icon');
    const show = (c.style.display === 'none' || !c.style.display);
    c.style.display = show ? 'block' : 'none';
    i.classList.toggle('fa-chevron-down', !show);
    i.classList.toggle('fa-chevron-up', show);
  }
  function toggleOrderDetails(orderId){
    const r = document.getElementById('order-details-'+orderId);
    r.style.display = (r.style.display === 'none' || !r.style.display) ? 'table-row' : 'none';
  }
</script>
</body>
</html>
