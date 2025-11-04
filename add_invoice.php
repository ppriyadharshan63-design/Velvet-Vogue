<?php
include 'includes/db.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Sanitize and validate inputs
    $customer = trim($_POST['customer_name']);
    $service = trim($_POST['service']);
    $amount = $_POST['total_amount'];
    $payment = $_POST['payment_method'];

    // Check if inputs are valid
    if (!empty($customer) && !empty($service) && is_numeric($amount) && $amount > 0 && in_array($payment, ['Cash', 'Card', 'Online'])) {
        
        // Prepared statement for security
        $stmt = $conn->prepare("INSERT INTO invoices (customer_name, service, total_amount, payment_method) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssds", $customer, $service, $amount, $payment);

        if ($stmt->execute()) {
            echo "<script>alert('✅ Invoice added successfully!'); window.location='index.php';</script>";
        } else {
            echo "<div class='error'>❌ Error: " . htmlspecialchars($stmt->error) . "</div>";
        }
        $stmt->close();
    } else {
        echo "<div class='error'>❌ Invalid input. Please check your details.</div>";
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Invoice - Kawshalya Beauty Salon</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f7f9fc;
            margin: 0;
            padding: 0;
        }
        header {
            background: #b84dff;
            color: #fff;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        h2 {
            text-align: center;
            margin: 30px 0;
            color: #333;
        }
        .form-container {
            max-width: 450px;
            margin: auto;
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        label {
            display: block;
            margin: 12px 0 6px;
            font-weight: 600;
            color: #555;
        }
        input, select {
            width: 100%;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #ccc;
            font-size: 15px;
            margin-bottom: 15px;
            outline: none;
            transition: border 0.2s;
        }
        input:focus, select:focus {
            border-color: #b84dff;
        }
        button {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 8px;
            background: #b84dff;
            color: white;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.2s;
        }
        button:hover {
            background: #9c3edb;
        }
        .error {
            max-width: 450px;
            margin: 15px auto;
            padding: 12px;
            background: #ffe5e5;
            color: #b30000;
            border-radius: 8px;
            text-align: center;
            font-weight: 500;
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            text-decoration: none;
            color: #b84dff;
            font-weight: 500;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <header>
        <h1>Kawshalya Beauty Salon</h1>
        <p>Add New Invoice</p>
    </header>

    <h2>➕ Add Invoice</h2>

    <div class="form-container">
        <form method="post" action="">
            <label for="customer_name">Customer Name:</label>
            <input type="text" id="customer_name" name="customer_name" required>

            <label for="service">Service/Item:</label>
            <input type="text" id="service" name="service" required>

            <label for="total_amount">Total Amount (Rs):</label>
            <input type="number" step="0.01" id="total_amount" name="total_amount" required>

            <label for="payment_method">Payment Method:</label>
            <select id="payment_method" name="payment_method" required>
                <option value="">-- Select Payment --</option>
                <option value="Cash">Cash</option>
                <option value="Card">Card</option>
                <option value="Online">Online</option>
            </select>

            <button type="submit">💾 Save Invoice</button>
        </form>
    </div>

    <a href="index.php" class="back-link">⬅ Back to Dashboard</a>
</body>
</html>
