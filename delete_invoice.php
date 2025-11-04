<?php
include 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delete_id = intval($_POST['delete_id']);
    $stmt = $conn->prepare("DELETE FROM invoices WHERE id=?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $stmt->close();
    echo "success";
}

$conn->close();
?>
