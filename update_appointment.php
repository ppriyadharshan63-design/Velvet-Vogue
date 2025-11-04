<?php
include 'includes/db.php';

// Check if all required POST data exists
if (isset($_POST['id'], $_POST['customer_name'], $_POST['service'], $_POST['appointment_date'], $_POST['appointment_time'])) {

    $id = $_POST['id'];
    $customer_name = $_POST['customer_name'];
    $service = $_POST['service'];
    $appointment_date = $_POST['appointment_date'];
    $appointment_time = $_POST['appointment_time'];

    // Prepare the update statement
    $stmt = $conn->prepare("UPDATE appointments SET customer_name = ?, service = ?, appointment_date = ?, appointment_time = ? WHERE id = ?");
    if ($stmt === false) {
        die("❌ Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("ssssi", $customer_name, $service, $appointment_date, $appointment_time, $id);

    // Execute and return result
    if ($stmt->execute()) {
        echo "✅ Appointment updated successfully";
    } else {
        echo "❌ Error updating appointment: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();

} else {
    echo "❌ Missing required fields";
}
?>
