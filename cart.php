<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$user_id = getUserId();

// Handle cart actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    $cart_id  = isset($_POST['cart_id']) ? intval($_POST['cart_id']) : null;
    $quantity = isset($_POST['quantity']) ? max(1, intval($_POST['quantity'])) : 1;

    switch ($_POST['action']) {
        case 'update_quantity':
            if ($cart_id) {
                $stmt = $conn->prepare("UPDATE cart_items SET quantity = ? WHERE id = ? AND user_id = ?");
                $stmt->bind_param("iii", $quantity, $cart_id, $user_id);
                $stmt->execute();
            }
            break;

        case 'remove_item':
            if ($cart_id) {
                $stmt = $conn->prepare("DELETE FROM cart_items WHERE id = ? AND user_id = ?");
                $stmt->bind_param("ii", $cart_id, $user_id);
                $stmt->execute();
            }
            break;

        case 'clear_cart':
            $stmt = $conn->prepare("DELETE FROM cart_items WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            break;
    }

    // Prevent form resubmission
    header('Location: cart.php');
    exit;
}

// Fetch cart items
$stmt = $conn->prepare("
    SELECT ci.id, ci.product_id, ci.quantity, ci.size, ci.color, 
           p.name, p.price, p.sale_price, p.image_url, p.stock
    FROM cart_items ci
    JOIN products p ON ci.product_id = p.id
    WHERE ci.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$cart_items = $result->fetch_all(MYSQLI_ASSOC);

// Totals
$cart_total = 0;
foreach ($cart_items as $item) {
    $price = $item['sale_price'] ?? $item['price'];
    $cart_total += $price * $item['quantity'];
}
$cart_count = count($cart_items);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - Velvet Vogue</title>
    <meta name="description" content="Review your Velvet Vogue shopping cart items and proceed to secure checkout. Free shipping on orders over $50.">
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <section style="padding: 6rem 0 4rem; background: #f8f9fa; min-height: 100vh;">
        <div class="container">
            <h1 style="color: #2c3e50; margin-bottom: 2rem;">Shopping Cart</h1>
            
            <?php if (!empty($cart_items)): ?>
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 3rem;">
                    
                    <!-- Cart Items -->
                    <div>
                        <div style="background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow: hidden;">
                            <div style="padding: 1.5rem; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                                <h3 style="color: #2c3e50; margin: 0;">Cart Items (<?php echo $cart_count; ?>)</h3>
                                <form method="POST" style="margin: 0;" onsubmit="return confirm('Are you sure you want to clear your cart?')">
                                    <input type="hidden" name="action" value="clear_cart">
                                    <button type="submit" class="btn btn-outline" style="font-size: 0.9rem; padding: 0.5rem 1rem;">
                                        <i class="fas fa-trash"></i> Clear Cart
                                    </button>
                                </form>
                            </div>
                            
                            <?php foreach ($cart_items as $item): ?>
                                <div style="padding: 1.5rem; border-bottom: 1px solid #eee; display: flex; gap: 1.5rem;">
                                    <div style="flex-shrink: 0;">
                                        <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                                             alt="<?php echo htmlspecialchars($item['name']); ?>"
                                             style="width: 120px; height: 120px; object-fit: cover; border-radius: 8px;"
                                             onerror="this.src='assets/images/placeholder.jpg'">
                                    </div>
                                    
                                    <div style="flex: 1;">
                                        <h4 style="margin-bottom: 0.5rem; color: #2c3e50;">
                                            <a href="product-detail.php?id=<?php echo $item['product_id']; ?>" 
                                               style="text-decoration: none; color: inherit;">
                                                <?php echo htmlspecialchars($item['name']); ?>
                                            </a>
                                        </h4>
                                        
                                        <?php if ($item['size']): ?>
                                            <p style="margin: 0.25rem 0; color: #666; font-size: 0.9rem;">
                                                <strong>Size:</strong> <?php echo htmlspecialchars($item['size']); ?>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <?php if ($item['color']): ?>
                                            <p style="margin: 0.25rem 0; color: #666; font-size: 0.9rem;">
                                                <strong>Color:</strong> <?php echo htmlspecialchars($item['color']); ?>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <div style="margin: 1rem 0;">
                                            <?php if ($item['sale_price']): ?>
                                                <span style="text-decoration: line-through; color: #999; margin-right: 0.5rem;">
                                                    $<?php echo number_format($item['price'], 2); ?>
                                                </span>
                                                <span style="color: #e74c3c; font-weight: bold; font-size: 1.1rem;">
                                                    $<?php echo number_format($item['sale_price'], 2); ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="color: #8b5a3c; font-weight: bold; font-size: 1.1rem;">
                                                    $<?php echo number_format($item['price'], 2); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div style="display: flex; align-items: center; gap: 1rem;">
                                            <form method="POST" style="display: flex; align-items: center; gap: 0.5rem; margin: 0;">
                                                <input type="hidden" name="action" value="update_quantity">
                                                <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                                                <label for="qty_<?php echo $item['id']; ?>" style="font-size: 0.9rem; color: #666;">Qty:</label>
                                                <input type="number" id="qty_<?php echo $item['id']; ?>" name="quantity" 
                                                       value="<?php echo $item['quantity']; ?>" min="1" max="10"
                                                       style="width: 70px; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px;"
                                                       onchange="this.form.submit()">
                                            </form>
                                            
                                            <form method="POST" style="margin: 0;" onsubmit="return confirm('Remove this item from cart?')">
                                                <input type="hidden" name="action" value="remove_item">
                                                <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                                                <button type="submit" style="background: none; border: none; color: #e74c3c; cursor: pointer; font-size: 0.9rem;">
                                                    <i class="fas fa-trash"></i> Remove
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                    
                                    <div style="text-align: right;">
                                        <div style="font-weight: bold; font-size: 1.1rem; color: #2c3e50;">
                                            <?php
                                            $item_price = $item['sale_price'] ?? $item['price'];
                                            $item_total = $item_price * $item['quantity'];
                                            echo '$' . number_format($item_total, 2);
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Continue Shopping -->
                        <div style="margin-top: 2rem;">
                            <a href="products.php" class="btn btn-outline">
                                <i class="fas fa-arrow-left"></i> Continue Shopping
                            </a>
                        </div>
                    </div>
                    
                    <!-- Order Summary -->
                    <div>
                        <div style="background: white; padding: 2rem; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); position: sticky; top: 2rem;">
                            <h3 style="color: #2c3e50; margin-bottom: 1.5rem;">Order Summary</h3>
                            
                            <div style="margin-bottom: 1rem; display: flex; justify-content: space-between;">
                                <span>Subtotal:</span>
                                <span>$<?php echo number_format($cart_total, 2); ?></span>
                            </div>
                            
                            <div style="margin-bottom: 1rem; display: flex; justify-content: space-between;">
                                <span>Shipping:</span>
                                <span>
                                    <?php if ($cart_total >= 50): ?>
                                        <span style="color: #28a745;">FREE</span>
                                    <?php else: ?>
                                        $<?php echo number_format(9.99, 2); ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                            
                            <?php if ($cart_total < 50 && $cart_total > 0): ?>
                                <div style="margin-bottom: 1rem; padding: 1rem; background: #fff3cd; border-radius: 5px; font-size: 0.9rem;">
                                    <i class="fas fa-info-circle" style="color: #856404;"></i>
                                    Add $<?php echo number_format(50 - $cart_total, 2); ?> more for free shipping!
                                </div>
                            <?php endif; ?>
                            
                            <hr style="margin: 1.5rem 0; border: none; border-top: 1px solid #eee;">
                            
                            <div style="margin-bottom: 2rem; display: flex; justify-content: space-between; font-size: 1.2rem; font-weight: bold;">
                                <span>Total:</span>
                                <span style="color: #8b5a3c;">
                                    $<?php echo number_format($cart_total + ($cart_total >= 50 ? 0 : 9.99), 2); ?>
                                </span>
                            </div>
                            
                            <?php if (isLoggedIn()): ?>
                                <a href="checkout.php" class="btn btn-primary" style="width: 100%; text-decoration: none; text-align: center; margin-bottom: 1rem;">
                                    <i class="fas fa-credit-card"></i> Proceed to Checkout
                                </a>
                            <?php else: ?>
                                <a href="login.php?redirect=checkout.php" class="btn btn-primary" style="width: 100%; text-decoration: none; text-align: center; margin-bottom: 1rem;">
                                    <i class="fas fa-sign-in-alt"></i> Login to Checkout
                                </a>
                            <?php endif; ?>
                            
                            <div style="text-align: center; margin-top: 1rem;">
                                <small style="color: #666;">
                                    <i class="fas fa-lock"></i> Secure checkout with SSL encryption
                                </small>
                            </div>
                        </div>
                        
                        <!-- Trust Badges -->
                        <div style="background: white; padding: 2rem; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-top: 2rem;">
                            <h4 style="color: #2c3e50; margin-bottom: 1rem;">Why shop with us?</h4>
                            
                            <div style="margin-bottom: 1rem; display: flex; align-items: center;">
                                <i class="fas fa-shipping-fast" style="color: #8b5a3c; margin-right: 1rem; width: 20px;"></i>
                                <div>
                                    <strong>Free Shipping</strong><br>
                                    <small style="color: #666;">On orders over $50</small>
                                </div>
                            </div>
                            
                            <div style="margin-bottom: 1rem; display: flex; align-items: center;">
                                <i class="fas fa-undo" style="color: #8b5a3c; margin-right: 1rem; width: 20px;"></i>
                                <div>
                                    <strong>Easy Returns</strong><br>
                                    <small style="color: #666;">30-day return policy</small>
                                </div>
                            </div>
                            
                            <div style="display: flex; align-items: center;">
                                <i class="fas fa-shield-alt" style="color: #8b5a3c; margin-right: 1rem; width: 20px;"></i>
                                <div>
                                    <strong>Secure Payment</strong><br>
                                    <small style="color: #666;">SSL encrypted checkout</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
            <?php else: ?>
                <!-- Empty Cart -->
                <div style="text-align: center; padding: 4rem 2rem; background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    <i class="fas fa-shopping-cart" style="font-size: 4rem; color: #ccc; margin-bottom: 2rem;"></i>
                    <h3 style="color: #666; margin-bottom: 1rem;">Your cart is empty</h3>
                    <p style="color: #999; margin-bottom: 2rem;">Add some items to get started!</p>
                    <a href="products.php" class="btn btn-primary">Start Shopping</a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/main.js"></script>
</body>
</html>
