<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once 'includes/functions.php';

requireAdminAuth();

$page_title = 'Customer Details';
$stats = getDashboardStats($conn);

$customer_id = intval($_GET['id'] ?? 0);

if (!$customer_id) {
    header('Location: customers.php');
    exit;
}

$customer = getCustomerDetails($conn, $customer_id);
if (!$customer) {
    header('Location: customers.php');
    exit;
}

include 'includes/header.php';
?>

<?php include 'includes/sidebar.php'; ?>

<main class="col-md-10 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <a href="customers.php" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Back to Customers
                </a>
                <a href="mailto:<?php echo htmlspecialchars($customer['email']); ?>" class="btn btn-sm btn-outline-info">
                    <i class="fas fa-envelope me-1"></i>Send Email
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <!-- Customer Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-user me-2"></i>Customer Information
                    </h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <div class="avatar-circle-large">
                            <?php echo strtoupper(substr($customer['first_name'], 0, 1) . substr($customer['last_name'], 0, 1)); ?>
                        </div>
                        <h5 class="mt-2"><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></h5>
                        <span class="badge bg-success">Customer</span>
                    </div>
                    
                    <dl class="row">
                        <dt class="col-sm-4">ID:</dt>
                        <dd class="col-sm-8"><?php echo $customer['id']; ?></dd>
                        
                        <dt class="col-sm-4">Email:</dt>
                        <dd class="col-sm-8">
                            <a href="mailto:<?php echo htmlspecialchars($customer['email']); ?>">
                                <?php echo htmlspecialchars($customer['email']); ?>
                            </a>
                        </dd>
                        
                        <?php if ($customer['phone']): ?>
                            <dt class="col-sm-4">Phone:</dt>
                            <dd class="col-sm-8">
                                <a href="tel:<?php echo htmlspecialchars($customer['phone']); ?>">
                                    <?php echo htmlspecialchars($customer['phone']); ?>
                                </a>
                            </dd>
                        <?php endif; ?>
                        
                        <dt class="col-sm-4">Joined:</dt>
                        <dd class="col-sm-8"><?php echo date('M j, Y', strtotime($customer['created_at'])); ?></dd>
                        
                        <dt class="col-sm-4">Last Update:</dt>
                        <dd class="col-sm-8"><?php echo date('M j, Y', strtotime($customer['updated_at'])); ?></dd>
                    </dl>
                </div>
            </div>

            <!-- Customer Statistics -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-bar me-2"></i>Statistics
                    </h5>
                </div>
                <div class="card-body">
                    <?php 
                    $total_orders = 0;
                    $total_spent = 0;
                    $orders_temp = $customer['orders'];
                    $orders_temp->data_seek(0);
                    while ($order = $orders_temp->fetch_assoc()) {
                        $total_orders++;
                        $total_spent += $order['total_amount'];
                    }
                    ?>
                    
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="border rounded p-3 mb-3">
                                <h4 class="text-primary mb-0"><?php echo $total_orders; ?></h4>
                                <small class="text-muted">Total Orders</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded p-3 mb-3">
                                <h4 class="text-success mb-0">$<?php echo number_format($total_spent, 2); ?></h4>
                                <small class="text-muted">Total Spent</small>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($total_orders > 0): ?>
                        <div class="text-center">
                            <div class="border rounded p-3">
                                <h4 class="text-info mb-0">$<?php echo number_format($total_spent / $total_orders, 2); ?></h4>
                                <small class="text-muted">Average Order Value</small>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <!-- Order History -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-shopping-cart me-2"></i>Order History
                    </h5>
                </div>
                <div class="card-body">
                    <?php 
                    $orders = $customer['orders'];
                    $orders->data_seek(0); // Reset pointer
                    if ($orders->num_rows > 0): 
                    ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Date</th>
                                        <th>Items</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($order = $orders->fetch_assoc()): ?>
                                        <tr>
                                            <td><strong>#<?php echo $order['id']; ?></strong></td>
                                            <td>
                                                <div>
                                                    <?php echo date('M j, Y', strtotime($order['created_at'])); ?>
                                                    <br><small class="text-muted"><?php echo date('g:i A', strtotime($order['created_at'])); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $order['total_items']; ?> items</span>
                                            </td>
                                            <td><strong>$<?php echo number_format($order['total_amount'], 2); ?></strong></td>
                                            <td>
                                                <span class="badge bg-<?php echo $order['status'] == 'completed' ? 'success' : ($order['status'] == 'pending' ? 'warning' : 'secondary'); ?>">
                                                    <?php echo ucfirst($order['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="order-detail.php?id=<?php echo $order['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No orders found</h5>
                            <p class="text-muted">This customer hasn't placed any orders yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
