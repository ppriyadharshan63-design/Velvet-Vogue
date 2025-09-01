<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if the user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Fetch the logged-in user ID
$user_id = getUserId();
$is_admin = false;

// Check if the logged-in user is an admin
$check_stmt = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
$check_stmt->bind_param("i", $user_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();
if ($check_result && $check_result->num_rows > 0) {
    $is_admin = (bool)$check_result->fetch_assoc()['is_admin'];
}
$check_stmt->close();

// ---------- Inputs ----------
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$date_filter = isset($_GET['date']) ? trim($_GET['date']) : 'all';
$sort_by = isset($_GET['sort']) ? trim($_GET['sort']) : 'created_at';
$order_dir = strtoupper($_GET['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Whitelist sort columns (avoid SQL injection)
$valid_sort_columns = ['id', 'total_amount', 'status', 'created_at'];
if (!in_array($sort_by, $valid_sort_columns, true)) {
    $sort_by = 'created_at';
}

// ---------- Build WHERE ----------
$where = ["1=1"];
$params = [];
$types = '';

if ($search !== '') {
    // free text on name/email/order id-like
    $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR o.id LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= 'ssss';

    // If numeric, also allow exact match on order id
    if (ctype_digit($search)) {
        $where[] = "o.id = ?";
        $params[] = (int)$search;
        $types .= 'i';
    }
}

if ($status_filter !== 'all') {
    $where[] = "o.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if ($date_filter !== 'all') {
    if ($date_filter === 'today') {
        $where[] = "DATE(o.created_at) = CURDATE()";
    } elseif ($date_filter === 'week') {
        $where[] = "o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    } elseif ($date_filter === 'month') {
        $where[] = "o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    }
}

$where_clause = implode(' AND ', $where);

// ---------- Count for pagination ----------
$count_sql = "
    SELECT COUNT(*) AS total
    FROM orders o
    LEFT JOIN users u ON u.id = o.user_id
    WHERE $where_clause
";
$count_stmt = $conn->prepare($count_sql);
if ($types !== '') { $count_stmt->bind_param($types, ...$params); }
$count_stmt->execute();
$total_rows = ($count_stmt->get_result()->fetch_assoc()['total'] ?? 0);
$count_stmt->close();
$total_pages = (int)ceil($total_rows / $per_page);

// ---------- Stats (null-safe) ----------
$stats_sql = "
    SELECT 
        COUNT(*)                               AS total_orders,
        COALESCE(SUM(total_amount), 0)         AS total_revenue,
        COALESCE(AVG(total_amount), 0)         AS avg_order_value,
        SUM(status='pending')                  AS pending_orders,
        SUM(status='processing')               AS processing_orders,
        SUM(status='shipped')                  AS shipped_orders,
        SUM(status='delivered')                AS delivered_orders,
        SUM(status='cancelled')                AS cancelled_orders
    FROM orders
";
$stats_res = $conn->query($stats_sql);
$stats = $stats_res ? $stats_res->fetch_assoc() : [
    'total_orders' => 0, 'total_revenue' => 0, 'avg_order_value' => 0,
    'pending_orders' => 0, 'processing_orders' => 0, 'shipped_orders' => 0,
    'delivered_orders' => 0, 'cancelled_orders' => 0
];

// ---------- MAIN LIST (safe for ONLY_FULL_GROUP_BY) ----------
$list_sql = "
    SELECT 
        o.id, o.user_id, o.total_amount, o.status, o.created_at, o.updated_at,
        o.payment_method, o.payment_status,
        u.first_name, u.last_name, u.email,
        COALESCE(ic.item_count, 0) AS item_count
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN (
        SELECT order_id, COUNT(*) AS item_count
        FROM order_items
        GROUP BY order_id
    ) ic ON ic.order_id = o.id
    WHERE $where_clause
    ORDER BY o.$sort_by $order_dir
    LIMIT ? OFFSET ?
";
$list_stmt = $conn->prepare($list_sql);
$types_with_limits = $types . 'ii';
$params_with_limits = $params;
$params_with_limits[] = $per_page;
$params_with_limits[] = $offset;
if ($types !== '') {
    $list_stmt->bind_param($types_with_limits, ...$params_with_limits);
} else {
    $list_stmt->bind_param('ii', $per_page, $offset);
}
$list_stmt->execute();
$orders_result = $list_stmt->get_result();
$list_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Order Management - Velvet Vogue Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="description" content="Manage orders in Velvet Vogue admin dashboard">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;background:#f5f7fa;color:#2c3e50;line-height:1.6}
        .admin-header{background:linear-gradient(135deg,#8b5a3c,#a0522d);color:#fff;padding:1.25rem 0;position:sticky;top:0;z-index:100;box-shadow:0 2px 10px rgba(0,0,0,.1)}
        .container{max-width:1200px;margin:0 auto;padding:2rem}
        .admin-header .container{padding:0 2rem;display:flex;justify-content:space-between;align-items:center}
        .admin-nav{display:flex;gap:1rem;flex-wrap:wrap}
        .admin-nav a{color:#fff;text-decoration:none;padding:.5rem .75rem;border-radius:8px;transition:.2s}
        .admin-nav a:hover{background:rgba(255,255,255,.2)}
        .btn{display:inline-flex;align-items:center;gap:.5rem;padding:.6rem 1rem;border:0;border-radius:8px;cursor:pointer;transition:.2s;font-weight:500}
        .btn-primary{background:linear-gradient(135deg,#8b5a3c,#a0522d);color:#fff}
        .btn-outline{background:transparent;border:2px solid #8b5a3c;color:#8b5a3c}
        .btn-outline:hover{background:#8b5a3c;color:#fff}
        .btn-sm{padding:.4rem .7rem;font-size:.9rem}
        .btn-success{background:#28a745;color:#fff}
        .btn-warning{background:#ffc107;color:#212529}
        .btn-info{background:#17a2b8;color:#fff}
        .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin-bottom:2rem}
        .stat-card{background:#fff;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.08);padding:1.25rem;text-align:center}
        .search-filters{background:#fff;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.08);padding:1rem 1.25rem;margin-bottom:2rem}
        .table-container{background:#fff;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.08);overflow:hidden}
        table{width:100%;border-collapse:collapse}
        th,td{padding:1rem;border-bottom:1px solid #eee;text-align:left}
        th{background:#f8f9fa}
        .status-badge{padding:.25rem .75rem;border-radius:20px;font-size:.8rem;font-weight:600}
        .status-pending{background:#fff3cd;color:#856404}
        .status-processing{background:#cce7ff;color:#004085}
        .status-shipped{background:#d1ecf1;color:#0c5460}
        .status-delivered{background:#d4edda;color:#155724}
        .status-cancelled{background:#f8d7da;color:#721c24}
        .alert{display:none;margin:1rem 0;padding:1rem;border-radius:8px}
        .alert-success{display:block;background:#d4edda;color:#155724;border:1px solid #c3e6cb}
        .alert-error{display:block;background:#f8d7da;color:#721c24;border:1px solid #f5c6cb}
        .pagination{display:flex;gap:.5rem;margin:1rem 0;flex-wrap:wrap}
        .pagination a{border:1px solid #8b5a3c;color:#8b5a3c;border-radius:6px;padding:.4rem .7rem;text-decoration:none}
        .pagination a.active{background:#8b5a3c;color:#fff}
        @media (max-width:768px){
            .admin-nav{flex-direction:column;align-items:flex-start}
            .search-grid{grid-template-columns:1fr}
        }
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
                <a href="admin-orders.php" style="background:rgba(255,255,255,.2)"><i class="fas fa-shopping-cart"></i> Orders</a>
                <a href="admin-categories.php"><i class="fas fa-tags"></i> Categories</a>
                <a href="account.php"><i class="fas fa-user"></i> Account</a>
            </nav>
        </div>
    </div>

    <section style="min-height:100vh;padding:2rem 0;">
        <div class="container">
            <!-- Header -->
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
                <div>
                    <h2 style="margin:0;">Order Management</h2>
                    <p style="color:#666;margin:.25rem 0 0;">Manage and track customer orders</p>
                </div>
                <a href="admin.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            </div>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3 style="color:#8b5a3c;margin:0;font-size:1.8rem;"><?= (int)$stats['total_orders'] ?></h3>
                    <p style="color:#666;margin:0;">Total Orders</p>
                </div>
                <div class="stat-card">
                    <h3 style="color:#28a745;margin:0;font-size:1.8rem;">$<?= number_format((float)$stats['total_revenue'], 2) ?></h3>
                    <p style="color:#666;margin:0;">Total Revenue</p>
                </div>
                <div class="stat-card">
                    <h3 style="color:#007bff;margin:0;font-size:1.8rem;">$<?= number_format((float)$stats['avg_order_value'], 2) ?></h3>
                    <p style="color:#666;margin:0;">Average Order Value</p>
                </div>
                <div class="stat-card">
                    <h3 style="color:#ffc107;margin:0;font-size:1.8rem;"><?= (int)$stats['pending_orders'] ?></h3>
                    <p style="color:#666;margin:0;">Pending Orders</p>
                </div>
            </div>

            <!-- Alerts -->
            <div id="alert-success" class="alert"></div>
            <div id="alert-error" class="alert"></div>

            <!-- Filters -->
            <div class="search-filters">
                <form method="GET" class="search-grid" style="display:grid;grid-template-columns:1fr repeat(5, minmax(140px,auto));gap:1rem;align-items:end;">
                    <div>
                        <label style="display:block;margin-bottom:.4rem;">Search Orders</label>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Order ID, name, email" style="width:100%;padding:.7rem;border:1px solid #ddd;border-radius:6px">
                    </div>
                    <div>
                        <label style="display:block;margin-bottom:.4rem;">Status</label>
                        <select name="status" style="padding:.7rem;border:1px solid #ddd;border-radius:6px;width:100%">
                            <?php
                            $statuses = ['all'=>'All Status','pending'=>'Pending','processing'=>'Processing','shipped'=>'Shipped','delivered'=>'Delivered','cancelled'=>'Cancelled'];
                            foreach ($statuses as $val=>$label) {
                                $sel = $status_filter === $val ? 'selected' : '';
                                echo "<option value=\"$val\" $sel>$label</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div>
                        <label style="display:block;margin-bottom:.4rem;">Date</label>
                        <select name="date" style="padding:.7rem;border:1px solid #ddd;border-radius:6px;width:100%">
                            <?php
                            $dates = ['all'=>'All Time','today'=>'Today','week'=>'This Week','month'=>'This Month'];
                            foreach ($dates as $val=>$label) {
                                $sel = $date_filter === $val ? 'selected' : '';
                                echo "<option value=\"$val\" $sel>$label</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div>
                        <label style="display:block;margin-bottom:.4rem;">Sort By</label>
                        <select name="sort" style="padding:.7rem;border:1px solid #ddd;border-radius:6px;width:100%">
                            <option value="created_at" <?= $sort_by==='created_at'?'selected':'' ?>>Date Created</option>
                            <option value="id"         <?= $sort_by==='id'?'selected':'' ?>>Order ID</option>
                            <option value="total_amount" <?= $sort_by==='total_amount'?'selected':'' ?>>Total Amount</option>
                            <option value="status"     <?= $sort_by==='status'?'selected':'' ?>>Status</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block;margin-bottom:.4rem;">Order</label>
                        <select name="order" style="padding:.7rem;border:1px solid #ddd;border-radius:6px;width:100%">
                            <option value="DESC" <?= $order_dir==='DESC'?'selected':'' ?>>Descending</option>
                            <option value="ASC"  <?= $order_dir==='ASC'?'selected':'' ?>>Ascending</option>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary" style="width:100%"><i class="fas fa-search"></i> Search</button>
                    </div>
                </form>
            </div>

            <!-- Table -->
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th style="min-width:240px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($orders_result && $orders_result->num_rows > 0): ?>
                        <?php while ($row = $orders_result->fetch_assoc()): ?>
                            <tr id="order-row-<?= (int)$row['id'] ?>">
                                <td style="font-weight:600">#<?= (int)$row['id'] ?></td>
                                <td>
                                    <div style="font-weight:500"><?= htmlspecialchars(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) ?></div>
                                    <div style="color:#666;font-size:.9rem"><?= htmlspecialchars($row['email'] ?? '') ?></div>
                                </td>
                                <td><?= (int)$row['item_count'] ?> item<?= ((int)$row['item_count'] !== 1) ? 's' : '' ?></td>
                                <td style="font-weight:600">$<?= number_format((float)$row['total_amount'], 2) ?></td>
                                <td><span class="status-badge status-<?= htmlspecialchars($row['status']) ?>"><?= ucfirst(htmlspecialchars($row['status'])) ?></span></td>
                                <td><?= htmlspecialchars(date('M j, Y g:i A', strtotime($row['created_at']))) ?></td>
                                <td>
                                    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
                                        <a href="admin-order-details.php?id=<?= (int)$row['id'] ?>" class="btn btn-primary btn-sm"><i class="fas fa-eye"></i> View</a>
                                        <?php if ($row['status']==='pending'): ?>
                                            <a href="admin-order-details.php?id=<?= (int)$row['id'] ?>&quick=processing" class="btn btn-success btn-sm"><i class="fas fa-play"></i> Process</a>
                                        <?php elseif ($row['status']==='processing'): ?>
                                            <a href="admin-order-details.php?id=<?= (int)$row['id'] ?>&quick=shipped" class="btn btn-info btn-sm"><i class="fas fa-shipping-fast"></i> Ship</a>
                                        <?php elseif ($row['status']==='shipped'): ?>
                                            <a href="admin-order-details.php?id=<?= (int)$row['id'] ?>&quick=delivered" class="btn btn-success btn-sm"><i class="fas fa-check"></i> Deliver</a>
                                        <?php endif; ?>
                                        <?php if (!in_array($row['status'], ['cancelled','delivered'], true)): ?>
                                            <a href="admin-order-details.php?id=<?= (int)$row['id'] ?>&quick=cancelled" class="btn btn-warning btn-sm"><i class="fas fa-times"></i> Cancel</a>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-warning btn-sm" onclick="confirmDelete(<?= (int)$row['id'] ?>)">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align:center;padding:3rem;color:#666">
                                <i class="fas fa-shopping-cart" style="font-size:3rem;margin-bottom:1rem;opacity:.3"></i>
                                <div style="font-size:1.1rem;margin-bottom:.25rem;">No orders found</div>
                                <div>Try changing filters or check back later.</div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php
                    // Keep existing query params except page:
                    $base_params = $_GET;
                    foreach (range(1, $total_pages) as $p) {
                        $base_params['page'] = $p;
                        $url = '?' . http_build_query($base_params);
                        $active = $p === $page ? 'active' : '';
                        echo "<a class=\"$active\" href=\"$url\">$p</a>";
                    }
                    ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <script>
        function showSuccess(msg){
            const el = document.getElementById('alert-success');
            el.className = 'alert alert-success';
            el.textContent = msg;
            el.style.display = 'block';
            setTimeout(()=>{ el.style.display='none'; }, 3000);
        }
        function showError(msg){
            const el = document.getElementById('alert-error');
            el.className = 'alert alert-error';
            el.textContent = msg;
            el.style.display = 'block';
            setTimeout(()=>{ el.style.display='none'; }, 4000);
        }
        function confirmDelete(orderId){
            if(!confirm('Delete order #' + orderId + '? This cannot be undone.')) return;
            fetch('ajax/delete-order.php', {
                method:'POST',
                headers:{'Content-Type':'application/json'},
                body: JSON.stringify({ order_id: orderId })
            })
            .then(r=>r.json())
            .then(d=>{
                if(d.success){
                    const row = document.getElementById('order-row-' + orderId);
                    if (row) row.remove();
                    showSuccess('Order deleted.');
                }else{
                    showError(d.message || 'Delete failed.');
                }
            })
            .catch(()=>showError('Network error.'));
        }
    </script>
</body>
</html>
