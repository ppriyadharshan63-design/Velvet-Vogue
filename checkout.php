<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

function clearUserCart($conn, $user_id) {
    $param_type = is_int($user_id) ? 'i' : 's';

    $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    if (!$stmt->bind_param($param_type, $user_id)) {
        die("Bind param failed: " . $stmt->error);
    }

    if (!$stmt->execute()) {
        die("Execute failed: " . $stmt->error);
    }

    if ($stmt->affected_rows === 0) {
        error_log("clearUserCart: No cart items deleted for user_id = $user_id");
    }

    $stmt->close();
}

if (!isset($_SESSION['user_id'])) {
    die("User not logged in.");
}
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $billing_first_name = trim($_POST['billing_first_name'] ?? '');
    $billing_last_name = trim($_POST['billing_last_name'] ?? '');
    $billing_email = trim($_POST['billing_email'] ?? '');
    $billing_phone = trim($_POST['billing_phone'] ?? '');
    $billing_address = trim($_POST['billing_address'] ?? '');
    $billing_city = trim($_POST['billing_city'] ?? '');
    $billing_state = trim($_POST['billing_state'] ?? '');
    $billing_postal_code = trim($_POST['billing_postal_code'] ?? '');
    $billing_country = trim($_POST['billing_country'] ?? 'LKR');

    $same_as_billing = isset($_POST['same_as_billing']);
    if ($same_as_billing) {
        $shipping_first_name = $billing_first_name;
        $shipping_last_name = $billing_last_name;
        $shipping_address = $billing_address;
        $shipping_city = $billing_city;
        $shipping_state = $billing_state;
        $shipping_postal_code = $billing_postal_code;
        $shipping_country = $billing_country;
    } else {
        $shipping_first_name = trim($_POST['shipping_first_name'] ?? '');
        $shipping_last_name = trim($_POST['shipping_last_name'] ?? '');
        $shipping_address = trim($_POST['shipping_address'] ?? '');
        $shipping_city = trim($_POST['shipping_city'] ?? '');
        $shipping_state = trim($_POST['shipping_state'] ?? '');
        $shipping_postal_code = trim($_POST['shipping_postal_code'] ?? '');
        $shipping_country = trim($_POST['shipping_country'] ?? '');
    }

    $shipping_name = trim($shipping_first_name . ' ' . $shipping_last_name);
    $shipping_email = $billing_email;

    // Get cart items
    $cart_result = getCartItems($conn, $user_id);
    $cart_items = [];
    $cart_total = 0;

    if ($cart_result && $cart_result->num_rows > 0) {
        while ($item = $cart_result->fetch_assoc()) {
            $price = $item['sale_price'] ?? $item['price'];
            $line_total = $price * $item['quantity'];
            $cart_total += $line_total;

            $cart_items[] = [
                'product_id' => $item['product_id'],
                'name' => $item['name'],
                'unit_price' => $price,
                'quantity' => $item['quantity'],
                'line_total' => $line_total
            ];
        }
    } else {
        header("Location: products.php");
        exit;
    }

    // Calculate totals
    $subtotal = $cart_total;
    $tax_amount = calculateTax($subtotal);
    $shipping_amount = calculateShipping($subtotal);
    $total_amount = $subtotal + $tax_amount + $shipping_amount;
    $status = 'pending';

    // Insert order
    $order_sql = "INSERT INTO orders (user_id, total_amount, status, shipping_name, shipping_email, shipping_address, shipping_city, shipping_state, shipping_zip, shipping_country) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $order_stmt = $conn->prepare($order_sql);
    if (!$order_stmt) {
        die("Prepare for orders failed: " . $conn->error);
    }

    $order_stmt->bind_param(
        'sdssssssss',
        $user_id,
        $total_amount,
        $status,
        $shipping_name,
        $shipping_email,
        $shipping_address,
        $shipping_city,
        $shipping_state,
        $shipping_postal_code,
        $shipping_country
    );

    if (!$order_stmt->execute()) {
        die("Execute order insert failed: " . $order_stmt->error);
    }

    $order_id = $order_stmt->insert_id;
    $order_stmt->close();

    // Insert cart items into order_items table
    $item_sql = "INSERT INTO order_items (order_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)";
    $item_stmt = $conn->prepare($item_sql);
    if (!$item_stmt) {
        die("Prepare for order_items failed: " . $conn->error);
    }

    foreach ($cart_items as $item) {
        $item_stmt->bind_param(
            'iiid',
            $order_id,
            $item['product_id'],
            $item['quantity'],
            $item['unit_price']
        );

        if (!$item_stmt->execute()) {
            error_log("Failed to insert item (Product ID: {$item['product_id']}): " . $item_stmt->error);
        }
    }

    $item_stmt->close();

    // Clear user cart
    clearUserCart($conn, $user_id);

    // Success: Show message and redirect after a brief delay
    echo "<script>
            alert('Order placed successfully!');
            window.location.href = 'index.php';
          </script>";
    exit;
}

// Page Load Logic
$cart_items = [];
$cart_total = 0;
$cart_result = getCartItems($conn, $user_id);
if ($cart_result && $cart_result->num_rows > 0) {
    while ($item = $cart_result->fetch_assoc()) {
        $price = $item['sale_price'] ?? $item['price'];
        $item['unit_price'] = $price;
        $item['line_total'] = $price * $item['quantity'];
        $cart_total += $item['line_total'];
        $cart_items[] = $item;
    }
} else {
    header("Location: products.php");
    exit;
}

$subtotal = $cart_total;
$tax_amount = calculateTax($subtotal);
$shipping_amount = calculateShipping($subtotal);
$total_amount = $subtotal + $tax_amount + $shipping_amount;

$user_data = isLoggedIn() ? getUserById($conn, getCurrentUserId()) : null;
?>

<!-- The HTML content remains unchanged. Use your existing HTML section here -->
<!-- Paste your HTML block here, as provided in your original message -->

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Checkout - Velvet Vogue</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Complete your purchase at Velvet Vogue with secure checkout">
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <button onclick="showDeleteModal(<?= $order['id']; ?>)">Delete</button>
</head>
<body>
<?php include 'includes/header.php'; ?>

<section style="padding: 6rem 0 4rem; background: #f8f9fa; min-height: 100vh;">
    <div class="container">
        <h1 style="text-align:center; color:#2c3e50; margin-bottom:2rem;">Checkout</h1>

        <form id="checkout-form" method="POST" action="">
            <div style="display: grid; grid-template-columns: 1fr 400px; gap: 3rem;">
                <!-- Billing and Shipping -->
                <div>
                    <!-- Billing Info -->
                    <div class="card" style="margin-bottom: 2rem;">
                        <div class="card-header">
                            <h3><i class="fas fa-user"></i> Billing Information</h3>
                        </div>
                        <div class="card-body">
                            <div class="grid-2">
                                <div class="form-group">
                                    <label for="billing_first_name">First Name *</label>
                                    <input type="text" name="billing_first_name" id="billing_first_name" class="form-control" required value="<?= htmlspecialchars($user_data['first_name'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label for="billing_last_name">Last Name *</label>
                                    <input type="text" name="billing_last_name" id="billing_last_name" class="form-control" required value="<?= htmlspecialchars($user_data['last_name'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="billing_email">Email *</label>
                                <input type="email" name="billing_email" id="billing_email" class="form-control" required value="<?= htmlspecialchars($user_data['email'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label for="billing_phone">Phone</label>
                                <input type="tel" name="billing_phone" id="billing_phone" class="form-control" value="<?= htmlspecialchars($user_data['phone'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label for="billing_address">Address *</label>
                                <input type="text" name="billing_address" id="billing_address" class="form-control" required>
                            </div>
                            <div class="grid-3">
                                <div class="form-group">
                                    <label for="billing_city">City *</label>
                                    <input type="text" name="billing_city" id="billing_city" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label for="billing_state">State *</label>
                                    <input type="text" name="billing_state" id="billing_state" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label for="billing_postal_code">ZIP Code *</label>
                                    <input type="text" name="billing_postal_code" id="billing_postal_code" class="form-control" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="billing_country">Country *</label>
                                <select name="billing_country" id="billing_country" class="form-control" required>
                                    <option value="LKR" selected>Sri Lanka</option>
                                    <option value="US">United States</option>
                                    <option value="CA">Canada</option>
                                    <option value="GB">United Kingdom</option>
                                    <option value="AU">Australia</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Shipping -->
                    <div class="card" style="margin-bottom: 2rem;">
                        <div class="card-header">
                            <h3><i class="fas fa-truck"></i> Shipping Information</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label style="display: flex; gap: .5rem;">
                                    <input type="checkbox" id="same_as_billing" name="same_as_billing" checked onchange="toggleShippingForm()"> Same as billing address
                                </label>
                            </div>

                            <div id="shipping-form" style="display:none;">
                                <div class="grid-2">
                                    <div class="form-group">
                                        <label for="shipping_first_name">First Name</label>
                                        <input type="text" name="shipping_first_name" id="shipping_first_name" class="form-control">
                                    </div>
                                    <div class="form-group">
                                        <label for="shipping_last_name">Last Name</label>
                                        <input type="text" name="shipping_last_name" id="shipping_last_name" class="form-control">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="shipping_address">Address</label>
                                    <input type="text" name="shipping_address" id="shipping_address" class="form-control">
                                </div>
                                <div class="grid-3">
                                    <div class="form-group">
                                        <label for="shipping_city">City</label>
                                        <input type="text" name="shipping_city" id="shipping_city" class="form-control">
                                    </div>
                                    <div class="form-group">
                                        <label for="shipping_state">State</label>
                                        <input type="text" name="shipping_state" id="shipping_state" class="form-control">
                                    </div>
                                    <div class="form-group">
                                        <label for="shipping_postal_code">ZIP Code</label>
                                        <input type="text" name="shipping_postal_code" id="shipping_postal_code" class="form-control">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="shipping_country">Country</label>
                                    <select name="shipping_country" id="shipping_country" class="form-control">
                                        <option value="LKR">Sri Lanka</option>
                                        <option value="US">United States</option>
                                        <option value="CA">Canada</option>
                                        <option value="GB">United Kingdom</option>
                                        <option value="AU">Australia</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment -->
                    <div class="card" style="margin-bottom: 2rem;">
                        <div class="card-header">
                            <h3><i class="fas fa-credit-card"></i> Payment Method</h3>
                        </div>
                        <div class="card-body">
                            <label><input type="radio" name="payment_method" value="credit_card" checked> Credit/Debit Card</label><br>
                            <label><input type="radio" name="payment_method" value="paypal"> PayPal</label><br>
                            <label><input type="radio" name="payment_method" value="cash_on_delivery"> Cash on Delivery</label>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-sticky-note"></i> Order Notes</h3>
                        </div>
                        <div class="card-body">
                            <textarea name="order_notes" class="form-control" placeholder="Any special delivery instructions..." rows="3"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Order Summary -->
                <div>
                    <div class="card">
                        <div class="card-header">
                            <h3>Order Summary</h3>
                        </div>
                        <div class="card-body">
                            <?php foreach ($cart_items as $item): ?>
                                <div style="display: flex; gap: 1rem; padding: 1rem 0; border-bottom: 1px solid #eee;">
                                    <img src="<?= htmlspecialchars($item['image_url'] ?? '') ?>" alt="<?= htmlspecialchars($item['name']) ?>" style="width: 60px; height: 60px; object-fit: cover; border-radius: 5px;">
                                    <div style="flex: 1;">
                                        <h4 style="margin: 0; font-size: 0.9rem;"><?= htmlspecialchars($item['name']) ?></h4>
                                        <?php if (!empty($item['size'])): ?><p>Size: <?= htmlspecialchars($item['size']) ?></p><?php endif; ?>
                                        <?php if (!empty($item['color'])): ?><p>Color: <?= htmlspecialchars($item['color']) ?></p><?php endif; ?>
                                        <div style="display:flex; justify-content:space-between;">
                                            <span>Qty: <?= $item['quantity'] ?></span>
                                            <span><strong><?= formatPrice($item['line_total']) ?></strong></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <div style="padding: 1rem 0;">
                                <p>Subtotal: <strong><?= formatPrice($subtotal) ?></strong></p>
                                <p>Tax: <strong><?= formatPrice($tax_amount) ?></strong></p>
                                <p>Shipping: <strong><?= $shipping_amount == 0 ? '<span style="color:green;">FREE</span>' : formatPrice($shipping_amount) ?></strong></p>
                                <p style="border-top: 2px solid #ccc; padding-top: 1rem;">Total: <strong><?= formatPrice($total_amount) ?></strong></p>
                            </div>

                            <!-- Hidden Fields -->
                            <input type="hidden" name="subtotal" value="<?= $subtotal ?>">
                            <input type="hidden" name="tax_amount" value="<?= $tax_amount ?>">
                            <input type="hidden" name="shipping_amount" value="<?= $shipping_amount ?>">
                            <input type="hidden" name="total_amount" value="<?= $total_amount ?>">

                            <button type="submit" class="btn btn-primary" style="width:100%; margin-top:1rem; padding:1rem;">
                                <i class="fas fa-lock"></i> Place Order
                            </button>

                            <p style="text-align:center; font-size:0.9rem; margin-top:1rem; color:#666;">
                                <i class="fas fa-shield-alt"></i> Secure checkout protected by SSL
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</section>

<?php include 'includes/footer.php'; ?>

<script>
    function toggleShippingForm() {
        const checkbox = document.getElementById('same_as_billing');
        const shippingForm = document.getElementById('shipping-form');
        shippingForm.style.display = checkbox.checked ? 'none' : 'block';
    }
    // Init toggle on page load
    toggleShippingForm();
</script>
</body>
</html>
