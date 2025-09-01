<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Require login and admin access
if (!isLoggedIn()) {
    header('Location: login.php?redirect=admin-categories.php');
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

// Get category ID
$category_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$category_id) {
    header('Location: admin-categories.php');
    exit;
}

// Get category data with product count
$category_sql = "SELECT c.*, COUNT(p.id) as product_count FROM categories c LEFT JOIN products p ON c.id = p.category_id WHERE c.id = ? GROUP BY c.id";
$category_stmt = $conn->prepare($category_sql);
$category_stmt->bind_param("i", $category_id);
$category_stmt->execute();
$category_result = $category_stmt->get_result();
$category = $category_result->fetch_assoc();

if (!$category) {
    header('Location: admin-categories.php?error=category_not_found');
    exit;
}

// Check if category has products
if ($category['product_count'] > 0) {
    header('Location: admin-categories.php?error=category_has_products');
    exit;
}

// Process deletion
if ($_POST && isset($_POST['confirm_delete'])) {
    try {
        // Delete category from database
        $delete_sql = "DELETE FROM categories WHERE id = ? AND (SELECT COUNT(*) FROM products WHERE category_id = ?) = 0";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("ii", $category_id, $category_id);
        
        if ($delete_stmt->execute() && $delete_stmt->affected_rows > 0) {
            header('Location: admin-categories.php?deleted=1');
            exit;
        } else {
            $error_message = "Failed to delete category. It may have products assigned to it.";
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
    <title>Delete Category - Velvet Vogue Admin</title>
    <meta name="description" content="Delete product category">
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
        .category-info {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 5px;
            margin: 1.5rem 0;
            border-left: 4px solid #dc3545;
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
                    <h1 style="color: #2c3e50; margin: 0;">Delete Category</h1>
                    <p style="color: #666; margin: 0.5rem 0 0;">Permanently remove category</p>
                </div>
                <a href="admin-categories.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Back to Categories
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
                    <h2 style="color: #dc3545; margin: 0;">Confirm Category Deletion</h2>
                </div>

                <div class="category-info">
                    <h3 style="margin: 0 0 1rem; color: #2c3e50;">Category Details</h3>
                    
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="padding: 0.5rem 0; font-weight: 500; width: 30%;">Name:</td>
                            <td style="padding: 0.5rem 0;"><?php echo htmlspecialchars($category['name']); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 0.5rem 0; font-weight: 500;">Description:</td>
                            <td style="padding: 0.5rem 0;">
                                <?php if ($category['description']): ?>
                                    <?php echo htmlspecialchars($category['description']); ?>
                                <?php else: ?>
                                    <span style="color: #999; font-style: italic;">No description</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 0.5rem 0; font-weight: 500;">Products:</td>
                            <td style="padding: 0.5rem 0;"><?php echo $category['product_count']; ?> products</td>
                        </tr>
                        <tr>
                            <td style="padding: 0.5rem 0; font-weight: 500;">Status:</td>
                            <td style="padding: 0.5rem 0;">
                                <span style="color: <?php echo $category['is_active'] ? '#28a745' : '#dc3545'; ?>;">
                                    <?php echo $category['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 0.5rem 0; font-weight: 500;">Created:</td>
                            <td style="padding: 0.5rem 0;"><?php echo date('M j, Y', strtotime($category['created_at'])); ?></td>
                        </tr>
                    </table>
                </div>

                <div class="warning-text">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Warning:</strong> This action cannot be undone. Deleting this category will permanently remove it from your system.
                </div>

                <?php if ($category['product_count'] > 0): ?>
                    <div style="background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 5px; margin: 1.5rem 0;">
                        <strong>Cannot Delete:</strong> This category cannot be deleted because it has <?php echo $category['product_count']; ?> products assigned to it.
                        You must first reassign or delete all products in this category before you can delete the category itself.
                    </div>
                    
                    <div style="text-align: center; margin-top: 2rem;">
                        <a href="admin-categories.php" class="btn btn-primary">
                            <i class="fas fa-arrow-left"></i> Back to Categories
                        </a>
                        <a href="admin-category-edit.php?id=<?php echo $category['id']; ?>" class="btn btn-outline">
                            <i class="fas fa-edit"></i> Edit Category
                        </a>
                    </div>
                <?php else: ?>
                    <div style="background: #d4edda; color: #155724; padding: 1rem; border-radius: 5px; margin: 1.5rem 0;">
                        <strong>Safe to Delete:</strong> This category has no products assigned to it and can be safely deleted.
                    </div>

                    <form method="POST" style="margin-top: 2rem;">
                        <div style="display: flex; gap: 1rem; justify-content: center;">
                            <a href="admin-categories.php" class="btn btn-outline">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            <a href="admin-category-edit.php?id=<?php echo $category['id']; ?>" class="btn btn-primary">
                                <i class="fas fa-edit"></i> Edit Instead
                            </a>
                            <button type="submit" name="confirm_delete" class="btn btn-primary" 
                                    style="background: #dc3545; border-color: #dc3545;"
                                    onclick="return confirm('Are you absolutely sure you want to delete this category? This action cannot be undone.')">
                                <i class="fas fa-trash"></i> Delete Permanently
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
