<?php
include 'includes/db.php';

// Fetch all invoices
$stmt = $conn->prepare("SELECT id, customer_name, service, total_amount, payment_method, created_at FROM invoices ORDER BY created_at DESC");
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

// Handle CSV report generation
if (isset($_GET['generate_report'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="invoices_report.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Customer Name', 'Service', 'Amount (Rs)', 'Payment Method', 'Date']);  // Column headers

    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['id'], 
            $row['customer_name'], 
            $row['service'], 
            number_format($row['total_amount'], 2), 
            $row['payment_method'], 
            date("d M Y, h:i A", strtotime($row['created_at']))
        ]);
    }

    fclose($output);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>All Invoices - Kawshalya Beauty Salon</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #f7f9fc; margin: 0; padding: 0; }
        header { background: #b84dff; color: white; text-align: center; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.15); }
        h2 { text-align: center; margin: 25px 0; color: #333; }
        .table-container { max-width: 1000px; margin: auto; background: white; border-radius: 12px; padding: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f0e6ff; color: #333; }
        tr:nth-child(even) { background: #fafafa; }
        tr:hover { background: #f5f0ff; }
        .empty { text-align: center; padding: 20px; color: #777; }
        nav { text-align: center; margin: 25px 0; }
        nav a { display: inline-block; margin: 8px; padding: 12px 18px; border-radius: 8px; background: #b84dff; color: white; text-decoration: none; font-weight: 500; transition: background 0.2s; }
        nav a:hover { background: #9c3edb; }
        /* Action buttons */
        .btn { display: inline-block; padding: 6px 12px; margin: 2px; font-size: 0.9rem; border-radius: 6px; text-decoration: none; color: white; border: none; cursor: pointer; }
        .btn-edit { background: #4CAF50; }
        .btn-edit:hover { background: #3e8e41; }
        .btn-delete { background: #e74c3c; }
        .btn-delete:hover { background: #c0392b; }
        .btn-report { background: #f39c12; }
        .btn-report:hover { background: #e67e22; }
    </style>
</head>
<body>
    <header>
        <h1>Kawshalya Beauty Salon</h1>
        <p>Invoice Records</p>
    </header>

    <h2>📄 All Invoices</h2>

    <div class="table-container">
        <!-- Button to generate the CSV report -->
        <div style="text-align: center; margin-bottom: 20px;">
            <a href="?generate_report=true" class="btn btn-report">📊 Generate Report (CSV)</a>
        </div>

        <?php if ($result->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Customer</th>
                    <th>Service</th>
                    <th>Amount (Rs.)</th>
                    <th>Payment</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="invoice-table">
                <?php while($row = $result->fetch_assoc()): ?>
                <tr id="row-<?= $row['id'] ?>">
                    <td><?= htmlspecialchars($row['id']) ?></td>
                    <td><?= htmlspecialchars($row['customer_name']) ?></td>
                    <td><?= htmlspecialchars($row['service']) ?></td>
                    <td>Rs. <?= number_format($row['total_amount'], 2) ?></td>
                    <td><?= htmlspecialchars($row['payment_method']) ?></td>
                    <td><?= date("d M Y, h:i A", strtotime($row['created_at'])) ?></td>
                    <td>
                        <a href="edit_invoice.php?id=<?= $row['id'] ?>" class="btn btn-edit">✏️ Edit</a>
                        <button class="btn btn-delete" onclick="deleteInvoice(<?= $row['id'] ?>)">🗑️ Delete</button>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
            <div class="empty">No invoices found.</div>
        <?php endif; ?>
    </div>

    <nav>
        <a href="index.php">🏠 Back to Dashboard</a>
        <a href="add_invoice.php">➕ Add New Invoice</a>
    </nav>

    <script>
        function deleteInvoice(id) {
            if (confirm('Are you sure you want to delete this invoice?')) {
                fetch('delete_invoice.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'delete_id=' + id
                })
                .then(response => response.text())
                .then(data => {
                    // Remove the deleted row from table
                    const row = document.getElementById('row-' + id);
                    if(row) row.remove();
                })
                .catch(err => alert('Error deleting invoice.'));
            }
        }
    </script>
</body>
</html>

<?php $conn->close(); ?>
