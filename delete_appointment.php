<?php
include 'includes/db.php';

$id = $_POST['id'];
$stmt = $conn->prepare("DELETE FROM appointments WHERE id=?");
$stmt->bind_param("i", $id);

echo $stmt->execute() ? "✅ Appointment deleted successfully" : "❌ Error deleting appointment";
