<?php
include 'includes/db.php';
$message = "";
$alertType = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $customer = trim($_POST['customer_name'] ?? '');
    $service = trim($_POST['service'] ?? '');
    $date = $_POST['appointment_date'] ?? '';
    $time = $_POST['appointment_time'] ?? '';

    if (!$customer || !$service || !$date || !$time) {
        $alertType = 'error';
        $message = "⚠ Please fill in all fields.";
    } else {
        try {
            $stmt = $conn->prepare("
                INSERT INTO appointments (customer_name, service, appointment_date, appointment_time)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("ssss", $customer, $service, $date, $time);

            if ($stmt->execute()) {
                echo "<script>
                    alert('✅ Appointment booked successfully!');
                    window.location='index.php';
                </script>";
                exit;
            } else {
                $alertType = 'error';
                $message = "❌ Database Error: " . htmlspecialchars($stmt->error);
            }

            $stmt->close();
        } catch (Exception $e) {
            $alertType = 'error';
            $message = "❌ Unexpected Error: " . htmlspecialchars($e->getMessage());
        }
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Book Appointment - Kawshalya Beauty Salon</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f7f9fc;
            margin: 0;
            padding: 0;
        }
        header {
            background: #b84dff;
            color: #fff;
            text-align: center;
            padding: 25px 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .form-container {
            max-width: 480px;
            margin: 40px auto;
            background: white;
            padding: 35px 30px;
            border-radius: 14px;
            box-shadow: 0 4px 18px rgba(0,0,0,0.1);
        }
        label {
            display: block;
            margin: 16px 0 6px;
            font-weight: 600;
            color: #333;
        }
        input, select {
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #ccc;
            font-size: 15px;
            margin-bottom: 10px;
            outline: none;
            transition: 0.2s;
        }
        input:focus, select:focus {
            border-color: #b84dff;
            box-shadow: 0 0 5px rgba(184, 77, 255, 0.2);
        }
        button {
            width: 100%;
            padding: 14px;
            background: #b84dff;
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.2s;
        }
        button:hover {
            background: #9c3edb;
        }
        .back-link {
            text-align: center;
            display: block;
            margin-top: 25px;
            color: #b84dff;
            text-decoration: none;
            font-weight: 500;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        .alert {
            text-align: center;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .alert.error {
            background: #ffe5e5;
            color: #b30000;
        }
        .alert.success {
            background: #e1ffe1;
            color: #007f00;
        }
    </style>
</head>
<body>
    <header>
        <h1>Kawshalya Beauty Salon</h1>
        <p>Book an Appointment</p>
    </header>

    <div class="form-container">
        <?php if (!empty($message)): ?>
            <div class="alert <?= $alertType ?>"><?= $message ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <label for="customer_name">Customer Name:</label>
            <input type="text" id="customer_name" name="customer_name" required minlength="2" placeholder="Enter full name">

            <label for="service">Service:</label>
            <input type="text" id="service" name="service" required placeholder="e.g. Haircut, Facial">

            <label for="appointment_date">Date:</label>
            <input type="date" id="appointment_date" name="appointment_date" required min="<?= date('Y-m-d'); ?>">

            <label for="appointment_time">Time:</label>
            <input type="time" id="appointment_time" name="appointment_time" required step="900">

            <button type="submit">📆 Book Appointment</button>
        </form>
    </div>

    <a href="index.php" class="back-link">⬅ Back to Calendar</a>
</body>
</html>
