<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/order-functions.php';
require_once '../includes/email-functions.php';

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

$order_ids = $input['order_ids'] ?? [];
$action = sanitizeInput($input['action'] ?? '');
$new_status = sanitizeInput($input['status'] ?? '');
$comment = sanitizeInput($input['comment'] ?? '');
$notify_customers = $input['notify_customers'] ?? true;

if (empty($order_ids) || !is_array($order_ids)) {
    echo json_encode(['success' => false, 'message' => 'No orders selected']);
    exit;
}

if (!$action) {
    echo json_encode(['success' => false, 'message' => 'No action specified']);
    exit;
}

// Validate order IDs
$order_ids = array_filter(array_map('intval', $order_ids));
if (empty($order_ids)) {
    echo json_encode(['success' => false, 'message' => 'Invalid order IDs']);
    exit;
}

// Limit bulk operations to prevent timeout
if (count($order_ids) > 100) {
    echo json_encode(['success' => false, 'message' => 'Too many orders selected. Maximum 100 orders allowed.']);
    exit;
}

$conn->begin_transaction();

try {
    $admin_id = getCurrentUserId();
    $success_count = 0;
    $error_count = 0;
    $errors = [];
    
    foreach ($order_ids as $order_id) {
        try {
            // Get order
            $order = getOrderById($conn, $order_id);
            if (!$order) {
                $errors[] = "Order ID $order_id not found";
                $error_count++;
                continue;
            }
            
            switch ($action) {
                case 'update_status':
                    if (!$new_status) {
                        $errors[] = "Status required for order ID $order_id";
                        $error_count++;
                        continue 2;
                    }
                    
                    // Validate status
                    $valid_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
                    if (!in_array($new_status, $valid_statuses)) {
                        $errors[] = "Invalid status for order ID $order_id";
                        $error_count++;
                        continue 2;
                    }
                    
                    if ($new_status === $order['status']) {
                        $errors[] = "Order ID $order_id already has status $new_status";
                        $error_count++;
                        continue 2;
                    }
                    
                    // Validate transition
                    if (!isValidBulkStatusTransition($order['status'], $new_status)) {
                        $errors[] = "Invalid status transition for order ID $order_id from {$order['status']} to $new_status";
                        $error_count++;
                        continue 2;
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
                        $errors[] = "Failed to update order ID $order_id";
                        $error_count++;
                        continue 2;
                    }
                    
                    // Add to status history
                    $bulk_comment = $comment ?: "Bulk status update to $new_status";
                    addOrderStatusHistory($conn, $order_id, $new_status, $bulk_comment, $admin_id);
                    
                    // Send notification email if requested
                    if ($notify_customers) {
                        sendOrderStatusEmail($conn, $order_id, $new_status);
                    }
                    
                    $success_count++;
                    break;
                    
                case 'mark_processing':
                    if ($order['status'] !== 'pending') {
                        $errors[] = "Order ID $order_id cannot be marked as processing (current status: {$order['status']})";
                        $error_count++;
                        continue 2;
                    }
                    
                    $update_sql = "UPDATE orders SET status = 'processing', updated_at = NOW() WHERE id = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param("i", $order_id);
                    
                    if (!$update_stmt->execute()) {
                        $errors[] = "Failed to update order ID $order_id";
                        $error_count++;
                        continue 2;
                    }
                    
                    addOrderStatusHistory($conn, $order_id, 'processing', $comment ?: 'Bulk processing update', $admin_id);
                    
                    if ($notify_customers) {
                        sendOrderStatusEmail($conn, $order_id, 'processing');
                    }
                    
                    $success_count++;
                    break;
                    
                case 'mark_shipped':
                    if (!in_array($order['status'], ['pending', 'processing'])) {
                        $errors[] = "Order ID $order_id cannot be marked as shipped (current status: {$order['status']})";
                        $error_count++;
                        continue 2;
                    }
                    
                    $update_sql = "UPDATE orders SET status = 'shipped', shipped_at = NOW(), updated_at = NOW() WHERE id = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param("i", $order_id);
                    
                    if (!$update_stmt->execute()) {
                        $errors[] = "Failed to update order ID $order_id";
                        $error_count++;
                        continue 2;
                    }
                    
                    addOrderStatusHistory($conn, $order_id, 'shipped', $comment ?: 'Bulk shipping update', $admin_id);
                    
                    if ($notify_customers) {
                        sendOrderStatusEmail($conn, $order_id, 'shipped');
                    }
                    
                    $success_count++;
                    break;
                    
                case 'add_comment':
                    if (!$comment) {
                        $errors[] = "Comment required for order ID $order_id";
                        $error_count++;
                        continue 2;
                    }
                    
                    addOrderStatusHistory($conn, $order_id, $order['status'], $comment, $admin_id);
                    $success_count++;
                    break;
                    
                case 'export_orders':
                    // This would be handled differently, just count as success for now
                    $success_count++;
                    break;
                    
                default:
                    $errors[] = "Unknown action: $action";
                    $error_count++;
                    break;
            }
            
        } catch (Exception $e) {
            $errors[] = "Error processing order ID $order_id: " . $e->getMessage();
            $error_count++;
        }
    }
    
    if ($error_count > 0 && $success_count === 0) {
        throw new Exception('All operations failed: ' . implode(', ', array_slice($errors, 0, 5)));
    }
    
    $conn->commit();
    
    $message = "$success_count order(s) updated successfully";
    if ($error_count > 0) {
        $message .= ", $error_count failed";
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'success_count' => $success_count,
        'error_count' => $error_count,
        'errors' => array_slice($errors, 0, 10) // Limit errors shown
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Bulk order update error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function isValidBulkStatusTransition($current_status, $new_status) {
    $valid_transitions = [
        'pending' => ['processing', 'shipped', 'cancelled'],
        'processing' => ['shipped', 'delivered', 'cancelled'],
        'shipped' => ['delivered'],
        'delivered' => [],
        'cancelled' => []
    ];
    
    return in_array($new_status, $valid_transitions[$current_status] ?? []);
}
?>
