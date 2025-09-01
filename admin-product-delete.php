<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/product-functions.php';

// Require login and admin access
if (!isLoggedIn()) {
    header('Location: login.php?redirect=admin-products.php');
    exit;
}

$user_id = getCurrentUserId();
$user_sql = "SELECT is_admin FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();

if (!$user || !$user['is_admin']) {
    header('Location: account.php');
    exit;
}

// Get product ID
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$product_id) {
    header('Location: admin-products.php');
    exit;
}

// Get product data
$product_sql = "SELECT * FROM products WHERE id = ?";
$product_stmt = $conn->prepare($product_sql);
$product_stmt->bind_param("i", $product_id);
$product_stmt->execute();
$product_result = $product_stmt->get_result();
$product = $product_result->fetch_assoc();

if (!$product) {
    header('Location: admin-products.php?error=product_not_found');
    exit;
}

// Process deletion
if ($_POST && isset($_POST['confirm_delete'])) {
    try {
        // Delete associated image
        if ($product['image_url']) {
            deleteProductImage($product['image_url']);
        }
        
        // Delete product from database
        $delete_sql = "DELETE FROM products WHERE id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $product_id);
        
        if ($delete_stmt->execute()) {
            header('Location: admin-products.php?deleted=1');
            exit;
        } else {
            $error_message = "Failed to delete product. Please try again.";
        }
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Product - Velvet Vogue Admin</title>
    <meta name="description" content="Delete product from Velvet Vogue inventory">
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .delete-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 2rem;
            max-width: 600px;
            margin: 0 auto;
        }
        .product-info {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 5px;
            margin: 1.5rem 0;
            border-left: 4px solid #dc3545;
        }
        .product-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        .warning-text {
            color: #dc3545;
            font-weight: 500;
            margin: 1rem 0;
        }
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <section style="padding: 6rem 0 4rem; background: #f8f9fa; min-height: 100vh;">
        <div class="container">
            <!-- Header -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <div>
                    <h1 style="color: #2c3e50; margin: 0;">Delete Product</h1>
                    <p style="color: #666; margin: 0.5rem 0 0;">Permanently remove product from inventory</p>
                </div>
                <a href="admin-products.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Back to Products
                </a>
            </div>

            <div class="delete-container">
                <?php if (isset($error_message)): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <div style="text-align: center; margin-bottom: 2rem;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: #dc3545; margin-bottom: 1rem;"></i>
                    <h2 style="color: #dc3545; margin: 0;">Confirm Product Deletion</h2>
                </div>

                <div class="product-info">
                    <h3 style="margin: 0 0 1rem; color: #2c3e50;">Product Details</h3>
                    
                    <?php if ($product['image_url']): ?>
                        <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                             alt="Product Image" class="product-image">
                    <?php endif; ?>
                    
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="padding: 0.5rem 0; font-weight: 500; width: 30%;">Name:</td>
                            <td style="padding: 0.5rem 0;"><?php echo htmlspecialchars($product['name']); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 0.5rem 0; font-weight: 500;">Price:</td>
                            <td style="padding: 0.5rem 0;">$<?php echo number_format($product['price'], 2); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 0.5rem 0; font-weight: 500;">Stock:</td>
                            <td style="padding: 0.5rem 0;"><?php echo $product['stock']; ?> units</td>
                        </tr>
                        <tr>
                            <td style="padding: 0.5rem 0; font-weight: 500;">Status:</td>
                            <td style="padding: 0.5rem 0;">
                                <span style="color: <?php echo $product['is_active'] ? '#28a745' : '#dc3545'; ?>;">
                                    <?php echo $product['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 0.5rem 0; font-weight: 500;">Created:</td>
                            <td style="padding: 0.5rem 0;"><?php echo date('M j, Y', strtotime($product['created_at'])); ?></td>
                        </tr>
                    </table>
                </div>

                <div class="warning-text">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Warning:</strong> This action cannot be undone. Deleting this product will:
                </div>

                <ul style="color: #dc3545; margin: 1rem 0; padding-left: 2rem;">
                    <li>Permanently remove the product from your inventory</li>
                    <li>Delete the product image (if any)</li>
                    <li>Remove it from customer wishlists and shopping carts</li>
                    <li>Make it unavailable for future orders</li>
                </ul>

                <div style="background: #fff3cd; color: #856404; padding: 1rem; border-radius: 5px; margin: 1.5rem 0;">
                    <strong>Alternative:</strong> Consider deactivating the product instead of deleting it. 
                    This will hide it from customers while preserving historical data.
                    <div style="margin-top: 0.5rem;">
                        <a href="admin-product-edit.php?id=<?php echo $product['id']; ?>" 
                           style="color: #856404; text-decoration: underline;">
                            Edit product to deactivate
                        </a>
                    </div>
                </div>

                <form method="POST" style="margin-top: 2rem;">
                    <div style="display: flex; gap: 1rem; justify-content: center;">
                        <a href="admin-products.php" class="btn btn-outline">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <a href="admin-product-edit.php?id=<?php echo $product['id']; ?>" class="btn btn-primary">
                            <i class="fas fa-edit"></i> Edit Instead
                        </a>
                        <button type="submit" name="confirm_delete" class="btn btn-primary" 
                                style="background: #dc3545; border-color: #dc3545;"
                                onclick="return confirm('Are you absolutely sure you want to delete this product? This action cannot be undone.')">
                            <i class="fas fa-trash"></i> Delete Permanently
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
