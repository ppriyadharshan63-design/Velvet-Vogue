<?php
include 'includes/db.php';

// Get today's and this month's totals
$today = date("Y-m-d");
$month = date("Y-m");

// Daily Collection
$daily = 0;
$stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) AS daily_total FROM invoices WHERE DATE(created_at) = ?");
$stmt->bind_param("s", $today);
$stmt->execute();
$stmt->bind_result($daily);
$stmt->fetch();
$stmt->close();

// Monthly Collection
$monthly = 0;
$stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) AS monthly_total FROM invoices WHERE DATE_FORMAT(created_at, '%Y-%m') = ?");
$stmt->bind_param("s", $month);
$stmt->execute();
$stmt->bind_result($monthly);
$stmt->fetch();
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Kawshalya Beauty Salon - Dashboard</title>
  <style>
    body {
      font-family: 'Segoe UI', Tahoma, sans-serif;
      background: linear-gradient(135deg, #f4f0ff, #ffffff);
      margin: 0;
      padding: 0;
      color: #333;
      animation: fadeIn 0.6s ease;
    }

    header {
      background: linear-gradient(135deg, #b84dff, #8e2de2);
      color: white;
      padding: 30px 20px;
      text-align: center;
      box-shadow: 0 4px 15px rgba(0,0,0,0.2);
      border-bottom-left-radius: 40px;
      border-bottom-right-radius: 40px;
    }

    header h1 {
      margin: 0;
      font-size: 32px;
      font-weight: bold;
      letter-spacing: 1px;
    }
    header p {
      margin-top: 6px;
      font-size: 16px;
      opacity: 0.9;
    }

    .dashboard {
      display: flex;
      justify-content: center;
      gap: 30px;
      padding: 50px 20px;
      flex-wrap: wrap;
    }

    .card {
      background: rgba(255,255,255,0.85);
      backdrop-filter: blur(10px);
      border-radius: 16px;
      padding: 30px;
      flex: 1;
      min-width: 260px;
      max-width: 320px;
      text-align: center;
      box-shadow: 0 6px 18px rgba(0,0,0,0.1);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
      animation: slideUp 0.6s ease;
    }

    .card:hover {
      transform: translateY(-8px);
      box-shadow: 0 10px 24px rgba(184,77,255,0.25);
    }

    .card h2 {
      margin: 10px 0;
      font-size: 22px;
      color: #5a2c91;
    }

    .card p {
      font-size: 20px;
      font-weight: bold;
      color: #28a745;
    }

    .card a {
      display: inline-block;
      margin-top: 10px;
      padding: 10px 18px;
      border-radius: 8px;
      background: linear-gradient(135deg, #b84dff, #8e2de2);
      color: white;
      text-decoration: none;
      font-weight: 500;
      transition: all 0.3s ease;
    }
    .card a:hover {
      background: linear-gradient(135deg, #9c3edb, #6c1bb7);
      transform: scale(1.05);
    }

    nav {
      text-align: center;
      padding: 20px;
    }
    nav a {
      display: inline-block;
      margin: 10px;
      padding: 12px 24px;
      border-radius: 10px;
      background: linear-gradient(135deg, #b84dff, #8e2de2);
      color: white;
      text-decoration: none;
      font-weight: 500;
      box-shadow: 0 4px 10px rgba(0,0,0,0.15);
      transition: all 0.3s ease;
    }
    nav a:hover {
      background: linear-gradient(135deg, #9c3edb, #6c1bb7);
      transform: translateY(-3px);
    }

    @keyframes fadeIn {
      from {opacity: 0;}
      to {opacity: 1;}
    }
    @keyframes slideUp {
      from {opacity: 0; transform: translateY(20px);}
      to {opacity: 1; transform: translateY(0);}
    }
  </style>
</head>
<body>
  <header>
    <h1>Kawshalya Beauty Salon</h1>
    <p>✨ Billing Dashboard</p>
  </header>

  <div class="dashboard">
    <div class="card">
      <h2>💰 Daily Collection</h2>
      <p>Rs. <?= number_format($daily, 2) ?></p>
    </div>
    <div class="card">
      <h2>📅 Monthly Collection</h2>
      <p>Rs. <?= number_format($monthly, 2) ?></p>
    </div>
    <div class="card">
      <h2>📆 Book Appointment</h2>
      <a href="book_appointment.php">➕ Schedule Now</a>
    </div>
    <div class="card">
      <h2>🗓 View Calendar</h2>
      <a href="calendar.php">📅 Open Calendar</a>
    </div>
  </div>

  <nav>
    <a href="add_invoice.php">➕ Add Invoice</a>
    <a href="view_invoices.php">📄 View Invoices</a>
    <a href="book_appointment.php">📆 Book Appointment</a>
    <a href="calendar.php">🗓 Appointment Calendar</a>
  </nav>
</body>
</html>
