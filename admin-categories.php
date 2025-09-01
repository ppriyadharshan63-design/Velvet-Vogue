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

// Get all categories with product counts
$categories_sql = "SELECT c.*, COUNT(p.id) AS product_count
                   FROM categories c
                   LEFT JOIN products p ON c.id = p.category_id
                   GROUP BY c.id
                   ORDER BY c.name";
$categories_result = $conn->query($categories_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Category Management - Velvet Vogue Admin</title>
    <meta name="description" content="Manage product categories in Velvet Vogue admin dashboard">
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        .table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
                    <h1 style="color: #2c3e50; margin: 0;">Category Management</h1>
                    <p style="color: #666; margin: 0.5rem 0 0;">Organize your products with categories</p>
                </div>
                <div>
                    <a href="admin-products.php" class="btn btn-outline" style="margin-right: 1rem;">
                        <i class="fas fa-box"></i> Back to Products
                    </a>
                    <a href="admin-category-add.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Category
                    </a>
                </div>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <?php
                $total_categories = 0;
                $active_categories = 0;
                $categories_with_products = 0;
                
                if ($categories_result) {
                    $categories_result->data_seek(0);
                    while ($cat = $categories_result->fetch_assoc()) {
                        $total_categories++;
                        if ($cat['is_active']) $active_categories++;
                        if ($cat['product_count'] > 0) $categories_with_products++;
                    }
                    $categories_result->data_seek(0);
                }
                ?>
                
                <div class="stat-card">
                    <h3 style="color: #8b5a3c; margin: 0; font-size: 1.5rem;"><?php echo $total_categories; ?></h3>
                    <p style="color: #666; margin: 0;">Total Categories</p>
                </div>
                
                <div class="stat-card">
                    <h3 style="color: #28a745; margin: 0; font-size: 1.5rem;"><?php echo $active_categories; ?></h3>
                    <p style="color: #666; margin: 0;">Active Categories</p>
                </div>
                
                <div class="stat-card">
                    <h3 style="color: #007bff; margin: 0; font-size: 1.5rem;"><?php echo $categories_with_products; ?></h3>
                    <p style="color: #666; margin: 0;">With Products</p>
                </div>
                
                <div class="stat-card">
                    <h3 style="color: #6c757d; margin: 0; font-size: 1.5rem;"><?php echo $total_categories - $active_categories; ?></h3>
                    <p style="color: #666; margin: 0;">Inactive Categories</p>
                </div>
            </div>

            <!-- Categories Table -->
            <div class="table-container">
                <div style="padding: 1.5rem; border-bottom: 1px solid #eee;">
                    <h3 style="color: #2c3e50; margin: 0;">Categories</h3>
                </div>
                
                <?php if ($categories_result && $categories_result->num_rows > 0): ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: #f8f9fa;">
                                    <th style="padding: 1rem; text-align: left; border-bottom: 1px solid #ddd;">Name</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 1px solid #ddd;">Description</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 1px solid #ddd;">Products</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 1px solid #ddd;">Status</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 1px solid #ddd;">Created</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 1px solid #ddd;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($category = $categories_result->fetch_assoc()): ?>
                                    <tr>
                                        <td style="padding: 1rem; border-bottom: 1px solid #eee;">
                                            <strong><?php echo htmlspecialchars($category['name']); ?></strong>
                                        </td>
                                        <td style="padding: 1rem; border-bottom: 1px solid #eee;">
                                            <?php if ($category['description']): ?>
                                                <?php echo htmlspecialchars(substr($category['description'], 0, 100)); ?>
                                                <?php if (strlen($category['description']) > 100) echo '...'; ?>
                                            <?php else: ?>
                                                <span style="color: #999; font-style: italic;">No description</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 1rem; border-bottom: 1px solid #eee;">
                                            <span style="font-weight: 500;">
                                                <?php echo $category['product_count']; ?> products
                                            </span>
                                        </td>
                                        <td style="padding: 1rem; border-bottom: 1px solid #eee;">
                                            <span class="status-badge <?php echo $category['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                                <?php echo $category['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td style="padding: 1rem; border-bottom: 1px solid #eee;">
                                            <?php echo date('M j, Y', strtotime($category['created_at'])); ?>
                                        </td>
                                        <td style="padding: 1rem; border-bottom: 1px solid #eee;">
                                            <div style="display: flex; gap: 0.5rem;">
                                                <a href="admin-category-edit.php?id=<?php echo $category['id']; ?>" 
                                                   class="btn btn-outline" style="font-size: 0.8rem; padding: 0.5rem;">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php if ($category['product_count'] == 0): ?>
                                                    <a href="admin-category-delete.php?id=<?php echo $category['id']; ?>" 
                                                       class="btn btn-outline" style="font-size: 0.8rem; padding: 0.5rem; color: #dc3545; border-color: #dc3545;"
                                                       onclick="return confirm('Are you sure you want to delete this category?')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <span style="font-size: 0.8rem; padding: 0.5rem; color: #999;" 
                                                          title="Cannot delete category with products">
                                                        <i class="fas fa-trash"></i>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    
                <?php else: ?>
                    <div style="padding: 3rem; text-align: center; color: #666;">
                        <i class="fas fa-tags" style="font-size: 3rem; margin-bottom: 1rem; color: #ccc;"></i>
                        <h3>No Categories Found</h3>
                        <p>Start organizing your products by creating categories.</p>
                        <a href="admin-category-add.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Create Your First Category
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
