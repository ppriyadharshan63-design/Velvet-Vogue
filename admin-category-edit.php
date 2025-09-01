<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/product-functions.php';

// Require login and admin access
if (!isLoggedIn()) {
    header('Location: login.php?redirect=admin-product-edit.php');
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

$errors = [];
$success = false;

// Process form submission
if ($_POST) {
    try {
        // Validate product data
        $errors = validateProductData($_POST);
        
        if (empty($errors)) {
            $name = trim($_POST['name']);
            $description = trim($_POST['description']);
            $price = floatval($_POST['price']);
            $stock = intval($_POST['stock']);
            $category_id = intval($_POST['category']);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            // Handle image upload
            $image_url = $product['image_url']; // Keep existing image by default
            
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                // Delete old image if it exists
                if ($product['image_url']) {
                    deleteProductImage($product['image_url']);
                }
                
                // Upload new image
                $image_url = uploadProductImage($_FILES['image'], $product_id);
            } elseif (isset($_POST['remove_image']) && $_POST['remove_image'] === '1') {
                // Remove existing image
                if ($product['image_url']) {
                    deleteProductImage($product['image_url']);
                }
                $image_url = null;
            }
            
            // Update product
            $sql = "UPDATE products SET name = ?, description = ?, price = ?, stock = ?, category = ?, image_url = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssdissii", $name, $description, $price, $stock, $category_id, $image_url, $is_active, $product_id);
            
            if ($stmt->execute()) {
                $success = true;
                // Refresh product data
                $product_stmt->execute();
                $product = $product_stmt->get_result()->fetch_assoc();
            } else {
                $errors[] = "Failed to update product. Please try again.";
            }
        }
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}

// Get categories for dropdown
$categories = getActiveCategories($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product - Velvet Vogue Admin</title>
    <meta name="description" content="Edit product in Velvet Vogue inventory">
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .form-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 2rem;
            max-width: 800px;
            margin: 0 auto;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #2c3e50;
            font-weight: 500;
        }
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }
        .form-control:focus {
            outline: none;
            border-color: #8b5a3c;
            box-shadow: 0 0 0 2px rgba(139, 90, 60, 0.2);
        }
        .current-image {
            max-width: 200px;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        .image-preview {
            margin-top: 1rem;
            max-width: 200px;
            border-radius: 5px;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .error-list {
            background: #f8d7da;
            color: #721c24;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1.5rem;
        }
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1.5rem;
        }
        .image-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
            margin-top: 0.5rem;
        }
        .remove-image-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.9rem;
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
                    <h1 style="color: #2c3e50; margin: 0;">Edit Product</h1>
                    <p style="color: #666; margin: 0.5rem 0 0;">Update product information</p>
                </div>
                <div>
                    <a href="admin-products.php" class="btn btn-outline" style="margin-right: 1rem;">
                        <i class="fas fa-arrow-left"></i> Back to Products
                    </a>
                    <a href="admin-product-delete.php?id=<?php echo $product['id']; ?>" 
                       class="btn btn-outline" style="color: #dc3545; border-color: #dc3545;"
                       onclick="return confirm('Are you sure you want to delete this product?')">
                        <i class="fas fa-trash"></i> Delete
                    </a>
                </div>
            </div>

            <div class="form-container">
                <?php if ($success): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i> Product updated successfully!
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="error-list">
                        <h4><i class="fas fa-exclamation-circle"></i> Please fix the following errors:</h4>
                        <ul style="margin: 0.5rem 0 0; padding-left: 1.5rem;">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="name">Product Name *</label>
                        <input type="text" id="name" name="name" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['name'] ?? $product['name']); ?>" 
                               required>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" 
                                  rows="4" placeholder="Enter product description..."><?php echo htmlspecialchars($_POST['description'] ?? $product['description']); ?></textarea>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                        <div class="form-group">
                            <label for="price">Price ($) *</label>
                            <input type="number" id="price" name="price" class="form-control" 
                                   step="0.01" min="0" 
                                   value="<?php echo htmlspecialchars($_POST['price'] ?? $product['price']); ?>" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="stock">Stock Quantity *</label>
                            <input type="number" id="stock" name="stock" class="form-control" 
                                   min="0" 
                                   value="<?php echo htmlspecialchars($_POST['stock'] ?? $product['stock']); ?>" 
                                   required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="category">Category *</label>
                        <select id="category" name="category" class="form-control" required>
                            <option value="">Select a category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" 
                                        <?php 
                                        $selected_category = $_POST['category'] ?? $product['category'];
                                        echo ($selected_category == $category['id']) ? 'selected' : ''; 
                                        ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Product Image</label>
                        
                        <?php if ($product['image_url']): ?>
                            <div id="currentImageContainer">
                                <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                     class="current-image" alt="Current Product Image">
                                <div class="image-actions">
                                    <button type="button" class="remove-image-btn" onclick="removeCurrentImage()">
                                        <i class="fas fa-trash"></i> Remove Image
                                    </button>
                                </div>
                            </div>
                            <input type="hidden" id="remove_image" name="remove_image" value="0">
                        <?php endif; ?>
                        
                        <input type="file" id="image" name="image" class="form-control" 
                               accept="image/jpeg,image/png,image/gif,image/webp">
                        <small style="color: #666; font-size: 0.9rem;">
                            Accepted formats: JPEG, PNG, GIF, WebP. Maximum size: 5MB.
                            <?php if ($product['image_url']): ?>
                                Leave empty to keep current image.
                            <?php endif; ?>
                        </small>
                        <div id="imagePreview"></div>
                    </div>

                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="is_active" name="is_active" 
                                   <?php echo ($_POST['is_active'] ?? $product['is_active']) ? 'checked' : ''; ?>>
                            <label for="is_active" style="margin: 0;">Active (visible to customers)</label>
                        </div>
                    </div>

                    <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                        <a href="admin-products.php" class="btn btn-outline">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Product
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>

    <script>
        // Image preview functionality
        document.getElementById('image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const preview = document.getElementById('imagePreview');
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = <img src="${e.target.result}" class="image-preview" alt="Preview">;
                };
                reader.readAsDataURL(file);
            } else {
                preview.innerHTML = '';
            }
        });

        // Remove current image functionality
        function removeCurrentImage() {
            if (confirm('Are you sure you want to remove the current image?')) {
                document.getElementById('currentImageContainer').style.display = 'none';
                document.getElementById('remove_image').value = '1';
            }
        }

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            const price = document.getElementById('price').value;
            const stock = document.getElementById('stock').value;
            const category = document.getElementById('category_id').value;

            if (!name) {
                alert('Please enter a product name');
                e.preventDefault();
                return;
            }

            if (!price || parseFloat(price) < 0) {
                alert('Please enter a valid price');
                e.preventDefault();
                return;
            }

            if (!stock || parseInt(stock) < 0) {
                alert('Please enter a valid stock quantity');
                e.preventDefault();
                return;
            }

            if (!category) {
                alert('Please select a category');
                e.preventDefault();
                return;
            }
        });
    </script>
</body>
</html>