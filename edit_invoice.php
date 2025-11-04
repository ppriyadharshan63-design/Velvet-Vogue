<?php
include 'includes/db.php';

// Check if invoice ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("<p style='color:red; text-align:center;'>❌ Invoice ID not provided.</p>");
}

$invoice_id = intval($_GET['id']);
$message = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_name = trim($_POST['customer_name']);
    $service = trim($_POST['service']);
    $total_amount = floatval($_POST['total_amount']);
    $payment_method = $_POST['payment_method'];

    $stmt = $conn->prepare("UPDATE invoices SET customer_name=?, service=?, total_amount=?, payment_method=? WHERE id=?");
    $stmt->bind_param("ssdsi", $customer_name, $service, $total_amount, $payment_method, $invoice_id);

    if ($stmt->execute()) {
        $message = "<p class='success'>✅ Invoice updated successfully!</p>";
    } else {
        $message = "<p class='error'>❌ Error updating invoice.</p>";
    }
}

// Fetch invoice details
$stmt = $conn->prepare("SELECT * FROM invoices WHERE id=?");
$stmt->bind_param("i", $invoice_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("<p style='color:red; text-align:center;'>❌ Invoice not found.</p>");
}

$invoice = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Invoice - Kawshalya Beauty Salon</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: #f7f9fc;
            margin: 0;
            padding: 0;
        }
        header {
            background: #b84dff;
            color: white;
            text-align: center;
            padding: 25px 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        h2 {
            text-align: center;
            margin: 30px 0 20px;
            color: #333;
        }
        .form-container {
            max-width: 500px;
            margin: auto;
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        label {
            display: block;
            margin-top: 15px;
            font-weight: 500;
        }
        input, select {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border-radius: 8px;
            border: 1px solid #ccc;
            box-sizing: border-box;
            font-size: 1rem;
        }
        button {
            margin-top: 20px;
            padding: 12px;
            width: 100%;
            background: #28a745;
            color: white;
            border: none;
            font-size: 1rem;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.2s;
        }
        button:hover {
            background: #218838;
        }
        .success { color: #155724; background: #d4edda; padding: 10px; border-radius: 8px; margin-bottom: 15px; text-align: center; }
        .error { color: #721c24; background: #f8d7da; padding: 10px; border-radius: 8px; margin-bottom: 15px; text-align: center; }
        nav {
            text-align: center;
            margin: 25px 0;
        }
        nav a {
            display: inline-block;
            margin: 8px;
            padding: 12px 18px;
            border-radius: 8px;
            background: #b84dff;
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: background 0.2s;
        }
        nav a:hover { background: #9c3edb; }
    </style>
</head>
<body>
    <header>
        <h1>Kawshalya Beauty Salon</h1>
        <p>Edit Invoice Details</p>
    </header>

    <h2>✏️ Edit Invoice</h2>

    <div class="form-container">
        <?php if($message) echo $message; ?>

        <form method="post">
            <label>Customer Name:</label>
            <input type="text" name="customer_name" value="<?= htmlspecialchars($invoice['customer_name']) ?>" required>

            <label>Service:</label>
            <input type="text" name="service" value="<?= htmlspecialchars($invoice['service']) ?>" required>

            <label>Total Amount (Rs.):</label>
            <input type="number" step="0.01" name="total_amount" value="<?= htmlspecialchars($invoice['total_amount']) ?>" required>

            <label>Payment Method:</label>
            <select name="payment_method" required>
                <option value="Cash" <?= ($invoice['payment_method'] === 'Cash') ? 'selected' : '' ?>>Cash</option>
                <option value="Card" <?= ($invoice['payment_method'] === 'Card') ? 'selected' : '' ?>>Card</option>
                <option value="Online" <?= ($invoice['payment_method'] === 'Online') ? 'selected' : '' ?>>Online</option>
            </select>

            <button type="submit">Update Invoice</button>
        </form>
    </div>

    <nav>
        <a href="all_invoices.php">📄 View All Invoices</a>
        <a href="index.php">🏠 Back to Dashboard</a>
    </nav>
</body>
</html>

<?php $conn->close(); ?>
