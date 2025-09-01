<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/order_functions.php';
require_once '../includes/email_functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Check admin access
if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$order_id = intval($input['order_id'] ?? 0);
$new_status = sanitizeInput($input['status'] ?? '');
$comment = sanitizeInput($input['comment'] ?? '');
$tracking_number = sanitizeInput($input['tracking_number'] ?? '');

if (!$order_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit;
}

// Validate status
$valid_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
if ($new_status && !in_array($new_status, $valid_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

// Get current order
$order = getOrderById($conn, $order_id);
if (!$order) {
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    exit;
}

$conn->begin_transaction();

try {
    $admin_id = getCurrentUserId();
    
    // Update order status if provided
    if ($new_status && $new_status !== $order['status']) {
        // Validate status transition
        if (!isValidStatusTransition($order['status'], $new_status)) {
            throw new Exception('Invalid status transition from ' . $order['status'] . ' to ' . $new_status);
        }
        
        // Update order status
        $update_sql = "UPDATE orders SET status = ?, updated_at = NOW()";
        $params = [$new_status];
        $types = "s";
        
        // Update timestamps based on status
        if ($new_status === 'shipped') {
            $update_sql .= ", shipped_at = NOW()";
        } elseif ($new_status === 'delivered') {
            $update_sql .= ", delivered_at = NOW()";
        }
        
        $update_sql .= " WHERE id = ?";
        $params[] = $order_id;
        $types .= "i";
        
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param($types, ...$params);
        
        if (!$update_stmt->execute()) {
            throw new Exception('Failed to update order status');
        }
        
        // Add to status history
        addOrderStatusHistory($conn, $order_id, $new_status, $comment, $admin_id);
        
        // Send notification email
        sendOrderStatusEmail($conn, $order_id, $new_status);
    }
    
    // Update tracking number if provided
    if ($tracking_number) {
        $tracking_sql = "UPDATE orders SET tracking_number = ? WHERE id = ?";
        $tracking_stmt = $conn->prepare($tracking_sql);
        $tracking_stmt->bind_param("si", $tracking_number, $order_id);
        
        if (!$tracking_stmt->execute()) {
            throw new Exception('Failed to update tracking number');
        }
        
        // Add tracking update to history
        addOrderStatusHistory($conn, $order_id, $order['status'], 'Tracking number updated: ' . $tracking_number, $admin_id);
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Order updated successfully'
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Order status update error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function isValidStatusTransition($current_status, $new_status) {
    $valid_transitions = [
        'pending' => ['processing', 'cancelled'],
        'processing' => ['shipped', 'cancelled'],
        'shipped' => ['delivered', 'cancelled'],
        'delivered' => [], // No transitions from delivered
        'cancelled' => [] // No transitions from cancelled
    ];
    
    return in_array($new_status, $valid_transitions[$current_status] ?? []);
}
?>
