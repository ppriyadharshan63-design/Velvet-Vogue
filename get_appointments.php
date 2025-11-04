<?php
header('Content-Type: application/json');

// Database connection
$servername = "localhost";
$username   = "root";   // change if different
$password   = "";       // change if different
$dbname     = "salon_billing";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die(json_encode(["error" => "Database connection failed: " . $conn->connect_error]));
}

// Check which columns exist in appointments table
$columns = [];
$colResult = $conn->query("SHOW COLUMNS FROM appointments");
while ($col = $colResult->fetch_assoc()) {
    $columns[] = $col['Field'];
}

// Decide which date/time fields to use
if (in_array("booking_date", $columns) && in_array("booking_time", $columns)) {
    $dateField = "booking_date";
    $timeField = "booking_time";
} elseif (in_array("appointment_date", $columns) && in_array("appointment_time", $columns)) {
    $dateField = "appointment_date";
    $timeField = "appointment_time";
} else {
    die(json_encode(["error" => "No valid date/time fields found in appointments table."]));
}

// Build query dynamically
$sql = "SELECT id, customer_name, service, $dateField AS event_date, $timeField AS event_time FROM appointments";
$result = $conn->query($sql);

$events = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $events[] = [
            "id"            => $row['id'],
            "title"         => $row['customer_name'] . " - " . $row['service'],
            "start"         => $row['event_date'] . "T" . $row['event_time'],
            "customer_name" => $row['customer_name'],
            "service"       => $row['service']
        ];
    }
}

echo json_encode($events);

$conn->close();
?>
