<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Require login and admin access
if (!isLoggedIn()) {
    header('Location: login.php?redirect=admin-category-add.php');
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

$errors = [];
$success = false;

// Process form submission
if ($_POST) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validation
    if (empty($name)) {
        $errors[] = "Category name is required";
    } elseif (strlen($name) < 2) {
        $errors[] = "Category name must be at least 2 characters long";
    } elseif (strlen($name) > 100) {
        $errors[] = "Category name must not exceed 100 characters";
    }
    
    // Check for duplicate name
    if (empty($errors)) {
        $check_sql = "SELECT id FROM categories WHERE name = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $name);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $errors[] = "A category with this name already exists";
        }
    }
    
    // Insert category if no errors
    if (empty($errors)) {
        $sql = "INSERT INTO categories (name, description, is_active) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $name, $description, $is_active);
        
        if ($stmt->execute()) {
            header('Location: admin-categories.php?added=1');
            exit;
        } else {
            $errors[] = "Failed to create category. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Category - Velvet Vogue Admin</title>
    <meta name="description" content="Add new product category">
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .form-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 2rem;
            max-width: 600px;
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
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <section style="padding: 6rem 0 4rem; background: #f8f9fa; min-height: 100vh;">
        <div class="container">
            <!-- Header -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <div>
                    <h1 style="color: #2c3e50; margin: 0;">Add New Category</h1>
                    <p style="color: #666; margin: 0.5rem 0 0;">Create a new product category</p>
                </div>
                <a href="admin-categories.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Back to Categories
                </a>
            </div>

            <div class="form-container">
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

                <form method="POST">
                    <div class="form-group">
                        <label for="name">Category Name *</label>
                        <input type="text" id="name" name="name" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" 
                               required maxlength="100">
                        <small style="color: #666; font-size: 0.9rem;">
                            Choose a descriptive name for your category (2-100 characters)
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" 
                                  rows="3" placeholder="Optional description for this category..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        <small style="color: #666; font-size: 0.9rem;">
                            Provide additional details about this category (optional)
                        </small>
                    </div>

                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="is_active" name="is_active" 
                                   <?php echo (isset($_POST['is_active']) || !$_POST) ? 'checked' : ''; ?>>
                            <label for="is_active" style="margin: 0;">Active (visible when adding products)</label>
                        </div>
                        <small style="color: #666; font-size: 0.9rem; margin-top: 0.5rem; display: block;">
                            Active categories will be available for selection when creating products
                        </small>
                    </div>

                    <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                        <a href="admin-categories.php" class="btn btn-outline">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Create Category
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>

    <script>
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();

            if (!name) {
                alert('Please enter a category name');
                e.preventDefault();
                return;
            }

            if (name.length < 2) {
                alert('Category name must be at least 2 characters long');
                e.preventDefault();
                return;
            }
        });
    </script>
</body>
</html>
