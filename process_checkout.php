<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/order-functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../checkout.php');
    exit;
}

// Basic CSRF protection
if (!isset($_SESSION['checkout_token']) || !isset($_POST['checkout_token']) || 
    $_SESSION['checkout_token'] !== $_POST['checkout_token']) {
    $_SESSION['error'] = 'Invalid security token. Please try again.';
    header('Location: ../checkout.php');
    exit;
}
unset($_SESSION['checkout_token']);

// Validate and sanitize input
$required = [
    'billing_first_name', 'billing_last_name', 'billing_email',
    'billing_address', 'billing_city', 'billing_state', 'billing_postal_code', 'billing_country',
    'subtotal', 'tax_amount', 'shipping_amount', 'total_amount', 'payment_method'
];

$errors = [];
$data = [];

foreach ($required as $field) {
    if (empty($_POST[$field])) {
        $errors[] = "Missing: " . $field;
    } else {
        $data[$field] = sanitizeInput($_POST[$field]);
    }
}

if (!filter_var($data['billing_email'], FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Invalid email format";
}

foreach (['subtotal', 'tax_amount', 'shipping_amount', 'total_amount'] as $field) {
    if (!is_numeric($data[$field])) {
        $errors[] = "Invalid number: $field";
    }
}

$valid_payments = ['credit_card', 'paypal', 'cash_on_delivery'];
if (!in_array($data['payment_method'], $valid_payments)) {
    $errors[] = "Invalid payment method";
}

if (!empty($errors)) {
    $_SESSION['error'] = implode(', ', $errors);
    header('Location: ../checkout.php');
    exit;
}

// Get cart items
$cart_items = [];
$cart_result = getCartItems($conn);
if (!$cart_result || $cart_result->num_rows === 0) {
    $_SESSION['error'] = 'Cart is empty';
    header('Location: ../products.php');
    exit;
}
while ($item = $cart_result->fetch_assoc()) {
    $cart_items[] = $item;
}

// Validate stock
foreach ($cart_items as $item) {
    $product = getProductById($conn, $item['product_id']);
    if (!$product || $product['stock'] < $item['quantity']) {
        $_SESSION['error'] = 'Insufficient stock for ' . htmlspecialchars($item['name']);
        header('Location: ../checkout.php');
        exit;
    }
}

// Begin transaction
$conn->begin_transaction();

try {
    $user_id = getCurrentUserId();
    $shipping_name = $data['billing_first_name'] . ' ' . $data['billing_last_name'];
    $shipping_email = $data['billing_email'];
    $shipping_address = $data['billing_address'];
    $shipping_city = $data['billing_city'];
    $shipping_state = $data['billing_state'];
    $shipping_zip = $data['billing_postal_code'];
    $shipping_country = $data['billing_country'];

    if (empty($_POST['same_as_billing'])) {
        $shipping_name = ($_POST['shipping_first_name'] ?? '') . ' ' . ($_POST['shipping_last_name'] ?? '');
        $shipping_address = $_POST['shipping_address'] ?? '';
        $shipping_city = $_POST['shipping_city'] ?? '';
        $shipping_state = $_POST['shipping_state'] ?? '';
        $shipping_zip = $_POST['shipping_postal_code'] ?? '';
        $shipping_country = $_POST['shipping_country'] ?? '';
    }

    // Insert into orders table
    $stmt = $conn->prepare("INSERT INTO orders (
        user_id, total_amount, status, shipping_name, shipping_email, shipping_address,
        shipping_city, shipping_state, shipping_zip, shipping_country
    ) VALUES (?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->bind_param("sdsssssss", 
        $user_id,
        $data['total_amount'],
        $shipping_name,
        $shipping_email,
        $shipping_address,
        $shipping_city,
        $shipping_state,
        $shipping_zip,
        $shipping_country
    );

    if (!$stmt->execute()) {
        throw new Exception("Failed to create order: " . $stmt->error);
    }

    $order_id = $stmt->insert_id;
    $stmt->close();

    // Add order items
    if (!addOrderItems($conn, $order_id, $cart_items)) {
        throw new Exception("Failed to add order items");
    }

    // Update stock
    foreach ($cart_items as $item) {
        $update = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
        $update->bind_param("ii", $item['quantity'], $item['product_id']);
        if (!$update->execute()) {
            throw new Exception("Stock update failed");
        }
    }

    // Order number (e.g., VV-000123)
    $order_number = 'VV-' . str_pad($order_id, 6, '0', STR_PAD_LEFT);

    // Optional: Status history and emails
    addOrderStatusHistory($conn, $order_id, 'pending', 'Order placed', $user_id);
    clearCart($conn, $user_id);
    sendOrderConfirmationEmail($conn, $order_id);
    sendAdminOrderNotification($conn, $order_id);

    $conn->commit();

    $_SESSION['success'] = 'Order placed successfully!';
    header('Location: ../order-confirmation.php?order=' . urlencode($order_number));
    exit;

} catch (Exception $e) {
    $conn->rollback();
    error_log('Checkout Error: ' . $e->getMessage());
    $_SESSION['error'] = 'Order failed. Please try again.';
    header('Location: ../checkout.php');
    exit;
}
?>
