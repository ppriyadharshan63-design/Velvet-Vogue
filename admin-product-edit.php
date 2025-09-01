<?php
// admin-product-edit.php (fixed, MySQLi)

session_start();
require_once __DIR__ . '/config/database.php';        // must define $conn (MySQLi)
require_once __DIR__ . '/includes/functions.php';     // isLoggedIn(), etc.
require_once __DIR__ . '/includes/product-functions.php'; // uploadProductImage(), deleteProductImage(), getActiveCategories()

// ---------- Helpers & CSRF ----------
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="'.e($_SESSION['csrf_token']).'">';
}
function verify_csrf(): bool {
    return isset($_POST['csrf']) && hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf']);
}

// Fallbacks if helper functions are missing
if (!function_exists('getActiveCategories')) {
    function getActiveCategories(mysqli $conn): array {
        $rows = [];
        if ($res = $conn->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC")) {
            while ($r = $res->fetch_assoc()) { $rows[] = $r; }
        }
        return $rows;
    }
}
if (!function_exists('uploadProductImage')) {
    function uploadProductImage(array $file, int $product_id): ?string {
        if ($file['error'] !== UPLOAD_ERR_OK) return null;
        $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
        $mime = mime_content_type($file['tmp_name']);
        if (!isset($allowed[$mime])) return null;
        if ($file['size'] > 5 * 1024 * 1024) return null; // 5MB

        $ext = $allowed[$mime];
        $dir = __DIR__ . '/uploads/products';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $name = 'p'.$product_id.'_'.bin2hex(random_bytes(6)).'.'.$ext;
        $dest = $dir . '/' . $name;

        if (!move_uploaded_file($file['tmp_name'], $dest)) return null;

        // Return relative path to serve
        return 'uploads/products/' . $name;
    }
}
if (!function_exists('deleteProductImage')) {
    function deleteProductImage(?string $path): void {
        if (!$path) return;
        $path = str_replace('\\', '/', $path);
        if (str_starts_with($path, 'uploads/')) {
            $full = __DIR__ . '/' . $path;
            if (is_file($full)) @unlink($full);
        }
    }
}

// ---------- Auth ----------
if (!isLoggedIn()) {
    $back = 'admin-product-edit.php';
    if (isset($_GET['id']) && ctype_digit((string)$_GET['id'])) {
        $back .= '?id=' . (int)$_GET['id'];
    }
    header('Location: login.php?redirect=' . urlencode($back));
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header('Location: login.php');
    exit;
}

$user_stmt = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();

if (!$user || (int)$user['is_admin'] !== 1) {
    header('Location: account.php');
    exit;
}

// ---------- Get product ----------
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($product_id <= 0) {
    header('Location: admin-products.php');
    exit;
}

$product_stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$product_stmt->bind_param("i", $product_id);
$product_stmt->execute();
$product = $product_stmt->get_result()->fetch_assoc();
$product_stmt->close();

if (!$product) {
    header('Location: admin-products.php?error=product_not_found');
    exit;
}

$errors = [];
$success = false;

// ---------- Validation (server-side) ----------
function validateProductData(array $data): array {
    $errs = [];
    if (empty(trim($data['name'] ?? ''))) {
        $errs[] = "Product name is required.";
    }
    if (!isset($data['price']) || !is_numeric($data['price']) || (float)$data['price'] < 0) {
        $errs[] = "Price must be a valid non-negative number.";
    }
    if (!isset($data['stock']) || !ctype_digit((string)$data['stock'])) {
        $errs[] = "Stock must be a valid non-negative integer.";
    }
    if (!isset($data['categories']) || !ctype_digit((string)$data['categories']) || (int)$data['categories'] <= 0) {
        $errs[] = "Please select a valid category.";
    }
    return $errs;
}

// ---------- Handle POST ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    } else {
        $errors = validateProductData($_POST);

        if (empty($errors)) {
            // Sanitize
            $name        = trim($_POST['name']);
            $description = trim($_POST['description'] ?? '');
            $price       = (float)$_POST['price'];
            $stock       = (int)$_POST['stock'];
            $category_id = (int)($_POST['categories'] ?? 0);
            $is_active   = isset($_POST['is_active']) ? 1 : 0;

            $image_url = $product['image_url']; // keep current

            // New image upload
            if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                // remove old image first (optional)
                if (!empty($image_url)) {
                    deleteProductImage($image_url);
                }
                $newPath = uploadProductImage($_FILES['image'], $product_id);
                if ($newPath) {
                    $image_url = $newPath;
                } else {
                    $errors[] = 'Image upload failed (type/size).';
                }
            }

            // Remove image toggle
            if (isset($_POST['remove_image']) && $_POST['remove_image'] === '1') {
                if (!empty($image_url)) {
                    deleteProductImage($image_url);
                }
                $image_url = null;
            }

            if (empty($errors)) {
                // Update (use category_id; do NOT write to enum 'category' here)
                $sql = "UPDATE products 
                           SET name = ?, description = ?, price = ?, stock = ?, category_id = ?, image_url = ?, is_active = ?, updated_at = NOW()
                         WHERE id = ?";
                $stmt = $conn->prepare($sql);

                // Bind (image_url can be NULL; MySQLi will send it as NULL if the variable is NULL)
                $stmt->bind_param(
                    "ssdiisii",
                    $name,
                    $description,
                    $price,
                    $stock,
                    $category_id,
                    $image_url,   // null ok
                    $is_active,
                    $product_id
                );

                if ($stmt->execute()) {
                    $success = true;

                    // Refresh product
                    $product_stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
                    $product_stmt->bind_param("i", $product_id);
                    $product_stmt->execute();
                    $product = $product_stmt->get_result()->fetch_assoc();
                    $product_stmt->close();
                } else {
                    $errors[] = "Failed to update product. Please try again.";
                }
                $stmt->close();
            }
        }
    }
}

// Load categories
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
    .edit-product-section{padding:6rem 0 4rem;background:#f8f9fa;min-height:100vh}
    .form-container{background:#fff;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.1);padding:2rem;max-width:800px;margin:0 auto}
    .form-group{margin-bottom:1.25rem}
    .form-group label{display:block;margin-bottom:.5rem;color:#2c3e50;font-weight:600}
    .form-control{width:100%;padding:.75rem;border:1px solid #ddd;border-radius:5px;font-size:1rem}
    .form-control:focus{outline:none;border-color:#8b5a3c;box-shadow:0 0 0 2px rgba(139,90,60,.2)}
    .form-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
    .current-image,.image-preview{max-width:200px;border-radius:5px}
    .alert{padding:1rem;border-radius:5px;margin-bottom:1rem}
    .alert-success{background:#d4edda;color:#155724}
    .alert-danger{background:#f8d7da;color:#721c24}
    .section-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem}
    .btn{display:inline-flex;align-items:center;gap:.5rem;padding:.6rem 1rem;border-radius:6px;text-decoration:none;border:1px solid transparent;cursor:pointer}
    .btn-outline{border-color:#8b5a3c;color:#8b5a3c;background:#fff}
    .btn-primary{background:linear-gradient(135deg,#8b5a3c,#a0522d);color:#fff;border:none}
    .btn-danger{background:#dc3545;color:#fff;border:none}
    .form-actions{display:flex;gap:1rem;justify-content:flex-end;margin-top:1.25rem}
  </style>
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<section class="edit-product-section">
  <div class="container">
    <div class="section-header">
      <div>
        <h1>Edit Product</h1>
        <p>Update product information</p>
      </div>
      <div>
        <a href="admin-products.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Products</a>
        <a href="admin-product-delete.php?id=<?= (int)$product['id'] ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this product?')">
          <i class="fas fa-trash"></i> Delete
        </a>
      </div>
    </div>

    <?php if ($success): ?>
      <div class="alert alert-success"><i class="fas fa-check-circle"></i> Product updated successfully!</div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-danger">
        <h4 style="margin:0 0 .5rem 0;"><i class="fas fa-exclamation-circle"></i> Please fix the following errors:</h4>
        <ul style="margin:0 0 0 1rem;">
          <?php foreach ($errors as $err): ?>
            <li><?= e($err) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <div class="form-container">
      <form method="POST" enctype="multipart/form-data">
        <?= csrf_field(); ?>

        <div class="form-group">
          <label for="name">Product Name <span style="color:red">*</span></label>
          <input type="text" id="name" name="name" class="form-control" value="<?= e($_POST['name'] ?? $product['name']) ?>" required>
        </div>

        <div class="form-group">
          <label for="description">Description</label>
          <textarea id="description" name="description" class="form-control" rows="4" placeholder="Enter product description..."><?= e($_POST['description'] ?? $product['description']) ?></textarea>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="price">Price ($) <span style="color:red">*</span></label>
            <input type="number" step="0.01" min="0" id="price" name="price" class="form-control" value="<?= e((string)($_POST['price'] ?? $product['price'])) ?>" required>
          </div>
          <div class="form-group">
            <label for="stock">Stock Quantity <span style="color:red">*</span></label>
            <input type="number" min="0" id="stock" name="stock" class="form-control" value="<?= e((string)($_POST['stock'] ?? $product['stock'])) ?>" required>
          </div>
        </div>

        <!-- Category -->
        <div class="form-group">
          <label for="categories">Category <span style="color:red">*</span></label>
          <select id="categories" name="categories" class="form-control" required>
            <option value="" disabled <?= (!isset($_POST['categories']) && empty($product['category_id'])) ? 'selected' : '' ?>>-- Select a category --</option>
            <?php
              $selected_cat = isset($_POST['categories']) ? (int)$_POST['categories'] : (int)($product['category_id'] ?? 0);
              foreach ($categories as $cat):
                  $sel = ((int)$cat['id'] === $selected_cat) ? 'selected' : '';
            ?>
              <option value="<?= (int)$cat['id'] ?>" <?= $sel ?>><?= e($cat['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Image -->
        <div class="form-group">
          <label>Product Image</label>
          <?php if (!empty($product['image_url'])): ?>
            <div id="currentImageContainer">
              <img src="<?= e($product['image_url']) ?>" class="current-image" alt="Current Product Image" onerror="this.style.display='none'">
              <div class="image-actions" style="margin-top:.5rem">
                <button type="button" class="btn btn-danger" onclick="removeCurrentImage()"><i class="fas fa-trash"></i> Remove Image</button>
              </div>
            </div>
            <input type="hidden" id="remove_image" name="remove_image" value="0">
          <?php endif; ?>
          <input type="file" id="image" name="image" class="form-control" accept="image/*">
          <small class="form-text">Accepted: JPEG, PNG, GIF, WebP. Max 5MB. <?= !empty($product['image_url']) ? 'Leave empty to keep current image.' : '' ?></small>
          <div id="imagePreview" style="margin-top:.75rem"></div>
        </div>

        <!-- Active -->
        <div class="form-group" style="display:flex;align-items:center;gap:.5rem">
          <input type="checkbox" id="is_active" name="is_active" <?= ((isset($_POST['is_active']) ? 1 : (int)$product['is_active']) ? 'checked' : '') ?>>
          <label for="is_active">Active (visible to customers)</label>
        </div>

        <div class="form-actions">
          <a href="admin-products.php" class="btn btn-outline">Cancel</a>
          <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Product</button>
        </div>
      </form>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
// Image Preview
document.getElementById('image')?.addEventListener('change', function (e) {
  const file = e.target.files[0];
  const preview = document.getElementById('imagePreview');
  if (file) {
    const reader = new FileReader();
    reader.onload = evt => preview.innerHTML = '<img src="'+evt.target.result+'" class="image-preview" alt="Preview">';
    reader.readAsDataURL(file);
  } else {
    preview.innerHTML = '';
  }
});

// Remove Current Image
function removeCurrentImage() {
  if (confirm('Remove the current image?')) {
    const c = document.getElementById('currentImageContainer');
    if (c) c.style.display = 'none';
    const x = document.getElementById('remove_image');
    if (x) x.value = '1';
  }
}
</script>
</body>
</html>
