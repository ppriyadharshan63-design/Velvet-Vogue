<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once 'includes/functions.php';

requireAdminAuth();

$page_title = 'Customers Management';
$stats = getDashboardStats($conn);

// Get filter parameters
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;

// Get customers
$customers = getAllCustomers($conn, $page, $per_page);

include 'includes/header.php';
?>

<?php include 'includes/sidebar.php'; ?>

<main class="col-md-10 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Customers Management</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportCustomers()">
                    <i class="fas fa-download me-1"></i>Export
                </button>
            </div>
        </div>
    </div>

    <!-- Customers Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="fas fa-users me-2"></i>Customers
            </h5>
        </div>
        <div class="card-body">
            <?php if ($customers && $customers->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="customersTable">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Joined</th>
                                <th>Orders</th>
                                <th>Total Spent</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($customer = $customers->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle me-3">
                                                <?php echo strtoupper(substr($customer['first_name'], 0, 1) . substr($customer['last_name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <strong><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></strong>
                                                <br><small class="text-muted">ID: <?php echo $customer['id']; ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="mailto:<?php echo htmlspecialchars($customer['email']); ?>">
                                            <?php echo htmlspecialchars($customer['email']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <?php if ($customer['phone']): ?>
                                            <a href="tel:<?php echo htmlspecialchars($customer['phone']); ?>">
                                                <?php echo htmlspecialchars($customer['phone']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">Not provided</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div>
                                            <?php echo date('M j, Y', strtotime($customer['created_at'])); ?>
                                            <br><small class="text-muted"><?php echo date('g:i A', strtotime($customer['created_at'])); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $customer['total_orders'] > 0 ? 'success' : 'secondary'; ?>">
                                            <?php echo $customer['total_orders']; ?> orders
                                        </span>
                                    </td>
                                    <td>
                                        <strong>$<?php echo number_format($customer['total_spent'] ?? 0, 2); ?></strong>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="customer-detail.php?id=<?php echo $customer['id']; ?>" 
                                               class="btn btn-outline-primary" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="mailto:<?php echo htmlspecialchars($customer['email']); ?>" 
                                               class="btn btn-outline-info" title="Send Email">
                                                <i class="fas fa-envelope"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div class="d-flex justify-content-center mt-4">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>" class="btn btn-outline-secondary me-2">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>
                    
                    <span class="btn btn-outline-secondary disabled">Page <?php echo $page; ?></span>
                    
                    <a href="?page=<?php echo $page + 1; ?>" class="btn btn-outline-secondary ms-2">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No customers found</h5>
                    <p class="text-muted">No customers have registered yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#customersTable').DataTable({
        "pageLength": 20,
        "order": [[ 3, "desc" ]],
        "columnDefs": [
            { "orderable": false, "targets": [6] }
        ]
    });
});

function exportCustomers() {
    // Simple CSV export
    const table = document.getElementById('customersTable');
    let csv = 'Name,Email,Phone,Joined,Orders,Total Spent\n';
    
    for (let i = 1; i < table.rows.length; i++) {
        const row = table.rows[i];
        const name = row.cells[0].textContent.trim().replace(/\n/g, ' ');
        const email = row.cells[1].textContent.trim();
        const phone = row.cells[2].textContent.trim();
        const joined = row.cells[3].textContent.trim().replace(/\n/g, ' ');
        const orders = row.cells[4].textContent.trim();
        const totalSpent = row.cells[5].textContent.trim();
        
        csv += `"${name}","${email}","${phone}","${joined}","${orders}","${totalSpent}"\n`;
    }
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'customers.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}
</script>

</body>
</html>
