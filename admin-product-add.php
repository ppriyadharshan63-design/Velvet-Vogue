<?php
session_start();
require_once __DIR__ . '/config/database.php';          // provides $conn (MySQLi)
require_once __DIR__ . '/includes/functions.php';       // isLoggedIn(), getCurrentUserId(), etc.
require_once __DIR__ . '/includes/product-functions.php'; // validateProductData(), uploadProductImage(), getActiveCategories()

// -------- CSRF --------
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="'.htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8').'">';
}
function verify_csrf(): bool {
    return isset($_POST['csrf']) && hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf']);
}

// -------- Auth: login + admin --------
if (!isLoggedIn()) {
    header('Location: login.php?redirect=' . urlencode('admin-product-add.php'));
    exit;
}

$user_id = getCurrentUserId();
$user = null;
if ($stmt = $conn->prepare("SELECT is_admin FROM users WHERE id = ?")) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
if (!$user || (int)$user['is_admin'] !== 1) {
    header('Location: account.php');
    exit;
}

$errors = [];
$success = false;

// Load Category IDs from DB (id + name)
$categoryOptions = getActiveCategories($conn); // expects [ ['id'=>..., 'name'=>...], ... ]

// Allowed enum categories for `products.category`
$validCategoriesEnum = ['mens','womens','accessories','formal'];

// -------- Handle POST --------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF
    if (!verify_csrf()) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    }

    // Collect inputs
    $name        = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price       = (float)($_POST['price'] ?? 0);
    $stock       = (int)($_POST['stock'] ?? 0);

    // Keep both: enum category and category_id
    $category    = trim($_POST['category'] ?? '');     // enum string
    $category_id = (int)($_POST['category_id'] ?? 0);  // FK from categories table

    $subcategory = trim($_POST['subcategory'] ?? '');
    $colors      = trim($_POST['colors'] ?? '');

    $sizes_array = $_POST['sizes'] ?? [];
    $sizes       = is_array($sizes_array) ? implode(',', array_map('trim', $sizes_array)) : '';

    $is_active   = isset($_POST['is_active']) ? 1 : 0;
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;

    // Basic field checks (complementing validateProductData)
    if ($name === '' || mb_strlen($name) < 2) {
        $errors[] = "Product name must be at least 2 characters long.";
    } elseif (mb_strlen($name) > 255) {
        $errors[] = "Product name must not exceed 255 characters.";
    }
    if ($price <= 0) {
        $errors[] = "Please enter a valid price greater than 0.";
    }
    if ($stock < 0) {
        $errors[] = "Stock quantity cannot be negative.";
    }
    if ($category === '' || !in_array($category, $validCategoriesEnum, true)) {
        $errors[] = "Please select a valid category.";
    }
    if ($category_id <= 0) {
        $errors[] = "Please select a valid Category ID.";
    }
    if ($subcategory === '') {
        $errors[] = "Subcategory is required.";
    }
    if ($colors === '') {
        $errors[] = "Please enter at least one color.";
    }
    if ($sizes === '') {
        $errors[] = "Please select at least one size.";
    }

    // Image (use the helper)
    $image_url = null;
    if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploaded = uploadProductImage($_FILES['image'], 0); // product id unknown before insert
        if ($uploaded) {
            $image_url = $uploaded;
        } else {
            $errors[] = "Image upload failed (type/size).";
        }
    }

    if (empty($errors)) {
        // Insert product
        $sql = "INSERT INTO products
                (name, description, price, stock, category, subcategory, category_id, colors, sizes, image_url, is_active, is_featured, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            $errors[] = "Failed to prepare statement: " . $conn->error;
        } else {
            // types: s s d i s s i s s s i i
            $stmt->bind_param(
                "ssdisissssii",
                $name,
                $description,
                $price,
                $stock,
                $category,
                $subcategory,
                $category_id,
                $colors,
                $sizes,
                $image_url,
                $is_active,
                $is_featured
            );

            if ($stmt->execute()) {
                // Success → redirect to list
                header('Location: admin-products.php?added=1');
                exit;
            } else {
                $errors[] = "Error executing query: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Add Product - Velvet Vogue Admin</title>
  <meta name="description" content="Add new product to Velvet Vogue inventory">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <!-- Tagify -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@yaireo/tagify/dist/tagify.css">
  <style>
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;background:#f5f7fa;color:#2c3e50;line-height:1.6}
    .admin-header{background:linear-gradient(135deg,#8b5a3c 0%,#a0522d 100%);color:#fff;padding:1.25rem 0;position:sticky;top:0;z-index:10;box-shadow:0 2px 10px rgba(0,0,0,.1)}
    .admin-header .container{max-width:960px;margin:0 auto;padding:0 1rem;display:flex;justify-content:space-between;align-items:center}
    .admin-nav{display:flex;gap:1rem}
    .admin-nav a{color:#fff;text-decoration:none;padding:.5rem .75rem;border-radius:8px}
    .admin-nav a:hover{background:rgba(255,255,255,.18)}
    .container{max-width:960px;margin:0 auto;padding:2rem 1rem}
    .page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem}
    .btn{padding:.65rem 1rem;border:none;border-radius:8px;text-decoration:none;display:inline-flex;gap:.5rem;align-items:center;font-weight:600;cursor:pointer}
    .btn-primary{background:linear-gradient(135deg,#8b5a3c,#a0522d);color:#fff}
    .btn-outline{border:2px solid #8b5a3c;background:#fff;color:#8b5a3c}
    .form-container{background:#fff;border:1px solid #e1e8ed;border-radius:14px;box-shadow:0 4px 18px rgba(0,0,0,.06);padding:2rem}
    .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:1.25rem}
    .form-group{margin-bottom:1rem}
    .form-group.full-width{grid-column:1 / -1}
    label{display:block;margin-bottom:.4rem;font-weight:700}
    .form-control{width:100%;padding:.75rem;border:2px solid #e1e8ed;border-radius:8px;font:inherit}
    .form-control:focus{outline:none;border-color:#8b5a3c;box-shadow:0 0 0 3px rgba(139,90,60,.12)}
    textarea.form-control{min-height:100px;resize:vertical}
    .checkbox-group{display:flex;gap:2rem;flex-wrap:wrap}
    .checkbox-item{display:flex;gap:.5rem;align-items:center}
    .file-upload{position:relative}
    .file-upload input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer}
    .file-upload-label{padding:2rem;border:2px dashed #e1e8ed;border-radius:10px;background:#fafbfc;text-align:center;color:#7f8c8d}
    .file-preview{margin-top:.75rem;text-align:center;display:none}
    .file-preview img{max-width:220px;max-height:220px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.08)}
    .alert{padding:1rem 1.25rem;border-radius:10px;border-left:4px solid;margin-bottom:1rem}
    .alert-danger{background:#f8d7da;color:#721c24;border-color:#dc3545}
    .input-hint{font-size:.85rem;color:#7f8c8d;margin-top:.25rem}
    .form-actions{display:flex;gap:1rem;justify-content:flex-end;margin-top:1.25rem;padding-top:1.25rem;border-top:1px solid #e1e8ed}
    @media (max-width:768px){.form-grid{grid-template-columns:1fr}.admin-nav{flex-direction:column}}
  </style>
</head>
<body>
<header class="admin-header">
  <div class="container">
    <h1><i class="fas fa-gem"></i> Velvet Vogue Admin</h1>
    <nav class="admin-nav">
      <a href="admin.php"><i class="fas fa-dashboard"></i> Dashboard</a>
      <a href="admin-products.php"><i class="fas fa-box"></i> Products</a>
      <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>
  </div>
</header>

<main class="container">
  <div class="page-header">
    <h1><i class="fas fa-plus-circle"></i> Add New Product</h1>
    <a href="admin-products.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Products</a>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
      <i class="fas fa-exclamation-circle"></i> <strong>Please fix the following errors:</strong>
      <ul style="margin:.5rem 0 0 1.25rem;">
        <?php foreach ($errors as $er): ?>
          <li><?= htmlspecialchars($er, ENT_QUOTES, 'UTF-8') ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="form-container">
    <form method="POST" enctype="multipart/form-data" id="addProductForm">
      <?= csrf_field(); ?>
      <div class="form-grid">

        <div class="form-group">
          <label for="name">Product Name *</label>
          <input type="text" id="name" name="name" class="form-control" value="<?= htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required maxlength="255">
          <div class="input-hint">Enter a clear, descriptive product name.</div>
        </div>

        <div class="form-group">
          <label for="category">Category (enum) *</label>
          <select id="category" name="category" class="form-control" required>
            <option value="">Select a category</option>
            <?php
              $curEnum = $_POST['category'] ?? '';
              foreach ($validCategoriesEnum as $opt) {
                  $sel = ($curEnum === $opt) ? 'selected' : '';
                  echo '<option value="'.htmlspecialchars($opt).'" '.$sel.'>'.ucfirst($opt).'</option>';
              }
            ?>
          </select>
          <div class="input-hint">Stored in the <code>products.category</code> enum column.</div>
        </div>

        <div class="form-group">
          <label for="category_id">Category ID (from DB) *</label>
          <select id="category_id" name="category_id" class="form-control" required>
            <option value="">Select Category ID</option>
            <?php
              $curId = (int)($_POST['category_id'] ?? 0);
              foreach ($categoryOptions as $c) {
                  $sel = ((int)$c['id'] === $curId) ? 'selected' : '';
                  echo '<option value="'.(int)$c['id'].'" '.$sel.'>'.(int)$c['id'].' - '.htmlspecialchars($c['name']).'</option>';
              }
            ?>
          </select>
          <div class="input-hint">Foreign key for the category record.</div>
        </div>

        <div class="form-group">
          <label for="subcategory">Subcategory *</label>
          <select id="subcategory" name="subcategory" class="form-control" required>
            <?php
              $sub = $_POST['subcategory'] ?? '';
              $subs = ['T-Shirts','Jeans','Watches','Dresses','Shoes','Accessories'];
              echo '<option value="" '.($sub===''?'selected':'').'>-- Select Subcategory --</option>';
              foreach ($subs as $s) {
                $sel = ($sub === $s) ? 'selected' : '';
                echo '<option value="'.htmlspecialchars($s).'" '.$sel.'>'.htmlspecialchars($s).'</option>';
              }
            ?>
          </select>
        </div>

        <div class="form-group">
          <label for="price">Price ($) *</label>
          <input type="number" id="price" name="price" class="form-control" step="0.01" min="0.01" required value="<?= htmlspecialchars($_POST['price'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
          <div class="input-hint">Enter price in USD (e.g., 29.99).</div>
        </div>

        <div class="form-group">
          <label for="stock">Stock Quantity *</label>
          <input type="number" id="stock" name="stock" class="form-control" min="0" required value="<?= htmlspecialchars($_POST['stock'] ?? '0', ENT_QUOTES, 'UTF-8') ?>">
          <div class="input-hint">Number of items in inventory.</div>
        </div>

        <div class="form-group full-width">
          <label for="description">Product Description</label>
          <textarea id="description" name="description" class="form-control" rows="4" maxlength="1000"><?= htmlspecialchars($_POST['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>

        <div class="form-group full-width">
          <label for="colors">Available Colors *</label>
          <input type="text" id="colors" name="colors" class="form-control" placeholder="Enter colors (e.g., Red, Blue, Green)" value="<?= htmlspecialchars($_POST['colors'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
          <div class="input-hint">Press Enter after each color or separate with commas.</div>
        </div>

        <div class="form-group full-width">
          <label>Available Sizes *</label>
          <div class="checkbox-group">
            <?php
              $postedSizes = $_POST['sizes'] ?? [];
              $sizeOpts = ['S','M','L','XL'];
              foreach ($sizeOpts as $size) {
                $checked = in_array($size, $postedSizes ?? [], true) ? 'checked' : '';
                echo '<label class="checkbox-item"><input type="checkbox" name="sizes[]" value="'.htmlspecialchars($size).'" '.$checked.'> '.$size.'</label>';
              }
            ?>
          </div>
        </div>

        <div class="form-group full-width">
          <label for="image">Product Image</label>
          <div class="file-upload">
            <input type="file" id="image" name="image" accept="image/*">
            <div class="file-upload-label">
              <i class="fas fa-cloud-upload-alt fa-2x"></i>
              <div>
                <div><strong>Click to upload image</strong></div>
                <div>or drag and drop</div>
                <div style="font-size:.85rem;margin-top:.35rem;">JPEG, PNG, GIF, WebP (max 5MB)</div>
              </div>
            </div>
          </div>
          <div class="file-preview" id="imagePreview">
            <img id="previewImg" src="" alt="Preview">
            <div style="margin-top:.5rem;">
              <button type="button" onclick="removeImage()" style="color:#dc3545;background:none;border:none;cursor:pointer;">
                <i class="fas fa-times"></i> Remove image
              </button>
            </div>
          </div>
        </div>

        <div class="form-group full-width">
          <div class="checkbox-group">
            <label class="checkbox-item">
              <input type="checkbox" id="is_active" name="is_active" <?= isset($_POST['is_active']) ? 'checked' : 'checked' ?>> Active (visible to customers)
            </label>
            <label class="checkbox-item">
              <input type="checkbox" id="is_featured" name="is_featured" <?= isset($_POST['is_featured']) ? 'checked' : '' ?>> Featured product
            </label>
          </div>
        </div>

      </div>

      <div class="form-actions">
        <a href="admin-products.php" class="btn btn-outline"><i class="fas fa-times"></i> Cancel</a>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add Product</button>
      </div>
    </form>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/@yaireo/tagify"></script>
<script>
  // Tagify for colors
  const colorsInput = document.getElementById('colors');
  if (colorsInput) new Tagify(colorsInput);

  // Image preview / drag-drop
  const fileInput   = document.getElementById('image');
  const previewWrap = document.getElementById('imagePreview');
  const previewImg  = document.getElementById('previewImg');

  function showPreview(file) {
    const r = new FileReader();
    r.onload = e => {
      previewImg.src = e.target.result;
      previewWrap.style.display = 'block';
    };
    r.readAsDataURL(file);
  }

  fileInput?.addEventListener('change', e => {
    const f = e.target.files[0];
    if (f) showPreview(f);
    else previewWrap.style.display = 'none';
  });

  function removeImage() {
    fileInput.value = '';
    previewWrap.style.display = 'none';
  }

  const dropZone = document.querySelector('.file-upload');
  ['dragenter','dragover','dragleave','drop'].forEach(ev => {
    dropZone.addEventListener(ev, e => { e.preventDefault(); e.stopPropagation(); }, false);
  });
  dropZone.addEventListener('drop', e => {
    const files = e.dataTransfer.files;
    if (files && files.length) {
      fileInput.files = files;
      fileInput.dispatchEvent(new Event('change'));
    }
  });

  // Simple client-side validation to match server rules
  document.getElementById('addProductForm').addEventListener('submit', function(e) {
    const name   = document.getElementById('name').value.trim();
    const price  = parseFloat(document.getElementById('price').value);
    const stock  = parseInt(document.getElementById('stock').value, 10);
    const cat    = document.getElementById('category').value;
    const catId  = parseInt(document.getElementById('category_id').value, 10);
    const sub    = document.getElementById('subcategory').value.trim();
    const cols   = document.getElementById('colors').value.trim();
    const sizeChecks = Array.from(document.querySelectorAll('input[name="sizes[]"]:checked'));

    if (!name || name.length < 2) { alert('Please enter a valid product name (min 2 chars).'); e.preventDefault(); return; }
    if (!(price > 0)) { alert('Please enter a valid price greater than 0.'); e.preventDefault(); return; }
    if (isNaN(stock) || stock < 0) { alert('Please enter a valid stock (0 or greater).'); e.preventDefault(); return; }
    if (!cat) { alert('Please select a category (enum).'); e.preventDefault(); return; }
    if (!(catId > 0)) { alert('Please select a Category ID.'); e.preventDefault(); return; }
    if (!sub) { alert('Please select a subcategory.'); e.preventDefault(); return; }
    if (!cols) { alert('Please enter at least one color.'); e.preventDefault(); return; }
    if (sizeChecks.length === 0) { alert('Please select at least one size.'); e.preventDefault(); return; }
  });
</script>
</body>
</html>
