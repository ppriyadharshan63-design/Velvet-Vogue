<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Require login and admin access
if (!isLoggedIn()) {
    header('Location: login.php?redirect=admin-customers.php');
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

// Handle search and filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$order = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// Build query conditions
$where_conditions = ["is_admin = 0"];
$params = [];
$types = "";

if (!empty($search)) {
    $where_conditions[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

if ($status_filter === 'active') {
    $where_conditions[] = "created_at IS NOT NULL";
} elseif ($status_filter === 'recent') {
    $where_conditions[] = "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
}

// Validate sort column
$valid_sort_columns = ['first_name', 'last_name', 'email', 'created_at'];
if (!in_array($sort_by, $valid_sort_columns)) {
    $sort_by = 'created_at';
}

$order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

// Build final query
$where_clause = implode(' AND ', $where_conditions);
$customers_sql = "SELECT u.*, 
                         COUNT(o.id) as total_orders,
                         SUM(o.total_amount) as total_spent,
                         MAX(o.created_at) as last_order_date
                  FROM users u 
                  LEFT JOIN orders o ON u.id = o.user_id 
                  WHERE $where_clause 
                  GROUP BY u.id 
                  ORDER BY $sort_by $order";

$customers_stmt = $conn->prepare($customers_sql);
if (!empty($params)) {
    $customers_stmt->bind_param($types, ...$params);
}
$customers_stmt->execute();
$customers_result = $customers_stmt->get_result();

// Get statistics
$stats_sql = "SELECT 
                COUNT(*) as total_customers,
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_customers,
                COUNT(CASE WHEN id IN (SELECT DISTINCT user_id FROM orders) THEN 1 END) as customers_with_orders
              FROM users WHERE is_admin = 0";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Management - Velvet Vogue Admin</title>
    <meta name="description" content="Manage customers in Velvet Vogue admin dashboard">
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

        .admin-header h1 {
            font-size: 1.8rem;
            font-weight: 600;
        }

        .admin-nav {
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .admin-nav a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .admin-nav a:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-1px);
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
            text-decoration: none;
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

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(139, 90, 60, 0.3);
        }

        .btn-outline {
            background: transparent;
            color: #8b5a3c;
            border: 2px solid #8b5a3c;
        }

        .btn-outline:hover {
            background: #8b5a3c;
            color: white;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }

        .search-filters {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .customer-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #8b5a3c;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-active { background: #d4edda; color: #155724; }
        .status-new { background: #cce7ff; color: #004085; }
        .status-inactive { background: #f8d7da; color: #721c24; }
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
                <a href="admin-orders.php"><i class="fas fa-shopping-cart"></i> Orders</a>
                <a href="admin-categories.php"><i class="fas fa-tags"></i> Categories</a>
                <a href="account.php"><i class="fas fa-user"></i> Account</a>
            </nav>
        </div>
    </div>

    <section style="padding: 2rem 0; min-height: 100vh;">
        <div class="container">
            <!-- Header -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <div>
                    <h1 style="color: #2c3e50; margin: 0;">Customer Management</h1>
                    <p style="color: #666; margin: 0.5rem 0 0;">Manage and view customer information</p>
                </div>
                <a href="admin.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3 style="color: #8b5a3c; margin: 0; font-size: 1.8rem;"><?php echo $stats['total_customers']; ?></h3>
                    <p style="color: #666; margin: 0;">Total Customers</p>
                </div>
                <div class="stat-card">
                    <h3 style="color: #28a745; margin: 0; font-size: 1.8rem;"><?php echo $stats['new_customers']; ?></h3>
                    <p style="color: #666; margin: 0;">New This Month</p>
                </div>
                <div class="stat-card">
                    <h3 style="color: #007bff; margin: 0; font-size: 1.8rem;"><?php echo $stats['customers_with_orders']; ?></h3>
                    <p style="color: #666; margin: 0;">With Orders</p>
                </div>
                <div class="stat-card">
                    <h3 style="color: #6c757d; margin: 0; font-size: 1.8rem;"><?php echo $stats['total_customers'] - $stats['customers_with_orders']; ?></h3>
                    <p style="color: #666; margin: 0;">No Orders Yet</p>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="search-filters">
                <form method="GET" style="display: grid; grid-template-columns: 1fr auto auto auto auto; gap: 1rem; align-items: end;">
                    <div>
                        <label style="display: block; margin-bottom: 0.5rem; color: #2c3e50; font-weight: 500;">Search Customers</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Search by name or email..." 
                               style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px;">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 0.5rem; color: #2c3e50; font-weight: 500;">Status</label>
                        <select name="status" style="padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px;">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Customers</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="recent" <?php echo $status_filter === 'recent' ? 'selected' : ''; ?>>New (30 days)</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 0.5rem; color: #2c3e50; font-weight: 500;">Sort By</label>
                        <select name="sort" style="padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px;">
                            <option value="created_at" <?php echo $sort_by === 'created_at' ? 'selected' : ''; ?>>Registration Date</option>
                            <option value="first_name" <?php echo $sort_by === 'first_name' ? 'selected' : ''; ?>>First Name</option>
                            <option value="last_name" <?php echo $sort_by === 'last_name' ? 'selected' : ''; ?>>Last Name</option>
                            <option value="email" <?php echo $sort_by === 'email' ? 'selected' : ''; ?>>Email</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 0.5rem; color: #2c3e50; font-weight: 500;">Order</label>
                        <select name="order" style="padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px;">
                            <option value="DESC" <?php echo $order === 'DESC' ? 'selected' : ''; ?>>Descending</option>
                            <option value="ASC" <?php echo $order === 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                </form>
            </div>

            <!-- Customers Table -->
            <div class="table-container">
                <div style="padding: 1.5rem; border-bottom: 1px solid #eee;">
                    <h3 style="color: #2c3e50; margin: 0;">Customers (<?php echo $customers_result->num_rows; ?>)</h3>
                </div>
                
                <?php if ($customers_result && $customers_result->num_rows > 0): ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: #f8f9fa;">
                                    <th style="padding: 1rem; text-align: left; border-bottom: 1px solid #ddd;">Customer</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 1px solid #ddd;">Email</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 1px solid #ddd;">Phone</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 1px solid #ddd;">Orders</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 1px solid #ddd;">Total Spent</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 1px solid #ddd;">Last Order</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 1px solid #ddd;">Status</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 1px solid #ddd;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($customer = $customers_result->fetch_assoc()): ?>
                                    <tr>
                                        <td style="padding: 1rem; border-bottom: 1px solid #eee;">
                                            <div style="display: flex; align-items: center; gap: 1rem;">
                                                <div class="customer-avatar">
                                                    <?php 
                                                    $initials = '';
                                                    if ($customer['first_name']) $initials .= strtoupper(substr($customer['first_name'], 0, 1));
                                                    if ($customer['last_name']) $initials .= strtoupper(substr($customer['last_name'], 0, 1));
                                                    if (empty($initials)) $initials = strtoupper(substr($customer['email'], 0, 1));
                                                    echo $initials;
                                                    ?>
                                                </div>
                                                <div>
                                                    <strong><?php echo htmlspecialchars(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')); ?></strong>
                                                    <br>
                                                    <small style="color: #666;">ID: <?php echo $customer['id']; ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td style="padding: 1rem; border-bottom: 1px solid #eee;">
                                            <?php echo htmlspecialchars($customer['email']); ?>
                                        </td>
                                        <td style="padding: 1rem; border-bottom: 1px solid #eee;">
                                            <?php echo $customer['phone'] ? htmlspecialchars($customer['phone']) : '<span style="color: #999;">Not provided</span>'; ?>
                                        </td>
                                        <td style="padding: 1rem; border-bottom: 1px solid #eee;">
                                            <strong><?php echo $customer['total_orders']; ?></strong>
                                        </td>
                                        <td style="padding: 1rem; border-bottom: 1px solid #eee;">
                                            <strong>$<?php echo number_format($customer['total_spent'] ?? 0, 2); ?></strong>
                                        </td>
                                        <td style="padding: 1rem; border-bottom: 1px solid #eee;">
                                            <?php if ($customer['last_order_date']): ?>
                                                <?php echo date('M j, Y', strtotime($customer['last_order_date'])); ?>
                                            <?php else: ?>
                                                <span style="color: #999;">Never</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 1rem; border-bottom: 1px solid #eee;">
                                            <?php
                                            $days_since_registration = (time() - strtotime($customer['created_at'])) / (24 * 60 * 60);
                                            if ($days_since_registration <= 30) {
                                                echo '<span class="status-badge status-new">New</span>';
                                            } elseif ($customer['total_orders'] > 0) {
                                                echo '<span class="status-badge status-active">Active</span>';
                                            } else {
                                                echo '<span class="status-badge status-inactive">Inactive</span>';
                                            }
                                            ?>
                                        </td>
                                        <td style="padding: 1rem; border-bottom: 1px solid #eee;">
                                            <a href="admin-customer-detail.php?id=<?php echo $customer['id']; ?>" 
                                               class="btn btn-outline" style="font-size: 0.8rem; padding: 0.5rem;">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div style="padding: 3rem; text-align: center; color: #666;">
                        <i class="fas fa-users" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                        <p>No customers found.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
</body>
</html>