<?php
/** admin-products.php — matches velvet_vogue.sql */
declare(strict_types=1);
session_start();

/* =========================
   DB CONNECTION (PDO)
   ========================= */
const DB_HOST = 'localhost';
const DB_NAME = 'velvet_vogue';
const DB_USER = 'root';
const DB_PASS = '';
const DB_CHARSET = 'utf8mb4';

$dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset='.DB_CHARSET;
$options = [
  PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES   => false,
];
try { $pdo = new PDO($dsn, DB_USER, DB_PASS, $options); }
catch (Throwable $e) { http_response_code(500); exit('DB connection failed.'); }

/* =========================
   SECURITY HEADERS
   ========================= */
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer-when-downgrade');

/* =========================
   HELPERS
   ========================= */
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function isLoggedIn(): bool { return isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id']); }
function getCurrentUserId(): ?int { return isLoggedIn() ? (int)$_SESSION['user_id'] : null; }

if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="'.e($_SESSION['csrf_token']).'">'; }
function verify_csrf(): bool {
  return isset($_POST['csrf']) && hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf']);
}

/* =========================
   AUTHN + AUTHZ
   ========================= */
if (!isLoggedIn()) { header('Location: login.php?redirect=admin-products.php'); exit; }
$user_id = getCurrentUserId();
$me = $pdo->prepare('SELECT is_admin FROM users WHERE id = ?');
$me->execute([$user_id]);
$me = $me->fetch();
if (!$me || (int)$me['is_admin'] !== 1) { http_response_code(403); exit('Access denied.'); }

/* =========================
   ENUM CATEGORIES (per schema)
   ========================= */
$ENUM_CATEGORIES = ['mens' => "Men's", 'womens' => "Women's", 'accessories' => 'Accessories', 'formal' => 'Formal'];

/* =========================
   FILTERS + PAGINATION
   ========================= */
$search          = trim((string)($_GET['search'] ?? ''));
$category_filter = trim((string)($_GET['category'] ?? '')); // enum value
$stock_filter    = (string)($_GET['stock'] ?? '');
$status_filter   = (string)($_GET['status'] ?? 'all');

$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset   = ($page - 1) * $per_page;

/* WHERE */
$where  = [];
$params = [];

if ($status_filter === 'active') { $where[] = 'p.is_active = 1'; }
elseif ($status_filter === 'inactive') { $where[] = 'p.is_active = 0'; }

if ($category_filter !== '' && isset($ENUM_CATEGORIES[$category_filter])) {
  $where[] = 'p.category = ?';
  $params[] = $category_filter;
}

if ($stock_filter === 'low') { $where[] = 'p.stock <= 5'; }
elseif ($stock_filter === 'out') { $where[] = 'p.stock = 0'; }

if ($search !== '') {
  $where[] = '(p.name LIKE ? OR p.description LIKE ?)';
  $like = "%{$search}%";
  $params[] = $like; $params[] = $like;
}

$where_sql = $where ? ('WHERE '.implode(' AND ', $where)) : '';

/* =========================
   BULK ACTIONS
   ========================= */
$success_message = '';
$error_message   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
  if (!verify_csrf()) {
    $error_message = 'Invalid security token.';
  } else {
    $action = (string)($_POST['bulk_action'] ?? '');
    $idsRaw = $_POST['selected_products'] ?? [];
    $ids = array_values(array_unique(array_map('intval', is_array($idsRaw) ? $idsRaw : [])));

    if (!$ids) {
      $error_message = 'No products selected.';
    } else {
      $in = implode(',', array_fill(0, count($ids), '?'));
      try {
        $pdo->beginTransaction();

        if ($action === 'delete') {
          // fetch images
          $imgs = $pdo->prepare("SELECT id, image_url FROM products WHERE id IN ($in)");
          $imgs->execute($ids);
          $imgRows = $imgs->fetchAll();

          // delete
          $del = $pdo->prepare("DELETE FROM products WHERE id IN ($in)");
          $del->execute($ids);
          $pdo->commit();

          // best-effort file cleanup (only inside uploads/ or uploads/products/)
          foreach ($imgRows as $r) {
            $path = (string)($r['image_url'] ?? '');
            $path = trim($path, "\"'"); // in case of accidental quotes in DB
            $path = str_replace(['\\'], '/', $path); // normalise
            if ($path !== '' && (str_starts_with($path, 'uploads/') || str_starts_with($path, 'uploads/products/'))) {
              $full = __DIR__ . '/' . $path;
              if (is_file($full)) { @unlink($full); }
            }
          }
          $success_message = 'Selected products deleted.';
        } elseif ($action === 'activate' || $action === 'deactivate') {
          $val = $action === 'activate' ? 1 : 0;
          $upd = $pdo->prepare("UPDATE products SET is_active = ? WHERE id IN ($in)");
          $upd->execute(array_merge([$val], $ids));
          $pdo->commit();
          $success_message = 'Selected products updated.';
        } else {
          $pdo->rollBack();
          $error_message = 'Please choose a valid bulk action.';
        }
      } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('Bulk action error: '.$e->getMessage());
        $error_message = 'An error occurred while performing the bulk action.';
      }
    }
  }
}

/* =========================
   COUNTS + DATA (join categories for friendly name)
   ========================= */
try {
  $count = $pdo->prepare("SELECT COUNT(*) FROM products p $where_sql");
  $count->execute($params);
  $total_products = (int)$count->fetchColumn();
  $total_pages    = max(1, (int)ceil($total_products / $per_page));
} catch (Throwable $e) {
  error_log('Count error: '.$e->getMessage());
  $total_products = 0; $total_pages = 1;
}

try {
  $sql = "
    SELECT 
      p.id, p.name, p.description, p.price, p.sale_price,
      p.category, p.subcategory, p.image_url, p.stock, p.is_featured, p.is_active, p.created_at,
      p.category_id, c.name AS category_name
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    $where_sql
    ORDER BY p.created_at DESC, p.id DESC
    LIMIT :limit OFFSET :offset
  ";
  $stmt = $pdo->prepare($sql);

  // bind positional filters
  $pos = 1;
  foreach ($params as $p) { $stmt->bindValue($pos++, $p, PDO::PARAM_STR); }
  $stmt->bindValue(':limit',  $per_page, PDO::PARAM_INT);
  $stmt->bindValue(':offset', $offset,   PDO::PARAM_INT);

  $stmt->execute();
  $products = $stmt->fetchAll() ?: [];
} catch (Throwable $e) {
  error_log('Fetch error: '.$e->getMessage());
  $products = [];
}

try {
  $stats = $pdo->query("
    SELECT 
      COUNT(*) AS total,
      SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active,
      SUM(CASE WHEN stock <= 5 THEN 1 ELSE 0 END)   AS low_stock,
      SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END)    AS out_of_stock
    FROM products
  ")->fetch() ?: ['total'=>0,'active'=>0,'low_stock'=>0,'out_of_stock'=>0];
} catch (Throwable $e) {
  error_log('Stats error: '.$e->getMessage());
  $stats = ['total'=>0,'active'=>0,'low_stock'=>0,'out_of_stock'=>0];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Product Management - Velvet Vogue Admin</title>
<meta name="description" content="Manage products in Velvet Vogue admin dashboard">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:#f5f7fa;color:#2c3e50;line-height:1.6}
.admin-header{background:linear-gradient(135deg,#8b5a3c 0%,#a0522d 100%);color:#fff;padding:1.25rem 0;position:sticky;top:0;z-index:100;box-shadow:0 2px 10px rgba(0,0,0,.1)}
.admin-header .container{max-width:1200px;margin:0 auto;padding:0 2rem;display:flex;justify-content:space-between;align-items:center}
.admin-nav{display:flex;gap:1rem}
.admin-nav a{color:#fff;text-decoration:none;padding:.5rem .9rem;border-radius:8px;transition:.2s}
.admin-nav a:hover{background:rgba(255,255,255,.2)}
.container{max-width:1200px;margin:0 auto;padding:2rem}
.page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem}
.btn{padding:.65rem 1rem;border:none;border-radius:8px;text-decoration:none;display:inline-flex;align-items:center;gap:.5rem;font-weight:600;cursor:pointer}
.btn-primary{background:linear-gradient(135deg,#8b5a3c,#a0522d);color:#fff}
.btn-outline{border:2px solid #8b5a3c;color:#8b5a3c;background:transparent}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin:1rem 0 1.5rem}
.stat-card{background:#fff;padding:1.25rem;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.06);text-align:center;border:1px solid #e1e8ed}
.stat-card .icon{width:48px;height:48px;margin:0 auto .75rem;background:linear-gradient(135deg,#8b5a3c,#a0522d);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff}
.filters-section{background:#fff;padding:1.25rem;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.06);margin-bottom:1.25rem;border:1px solid #e1e8ed}
.filters-grid{display:grid;grid-template-columns:2fr 1fr 1fr 1fr auto;gap:1rem;align-items:end}
.form-group{display:flex;flex-direction:column}
.form-group label{margin-bottom:.4rem;font-weight:600;font-size:.9rem}
.form-control{padding:.7rem;border:2px solid #e1e8ed;border-radius:8px}
.form-control:focus{outline:none;border-color:#8b5a3c;box-shadow:0 0 0 3px rgba(139,90,60,.12)}
.table-container{background:#fff;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.06);border:1px solid #e1e8ed;overflow:hidden}
.table-header{background:#f8f9fa;padding:1rem 1.25rem;border-bottom:1px solid #e1e8ed;display:flex;justify-content:space-between;align-items:center}
.bulk-actions{background:#fff3cd;border:1px solid #ffc107;padding:.75rem 1.25rem;display:none;align-items:center;gap:1rem}
.table-responsive{overflow-x:auto}
table{width:100%;border-collapse:collapse}
th,td{padding:1rem;text-align:left;border-bottom:1px solid #e1e8ed}
th{background:#f8f9fa;position:sticky;top:0}
tr:hover{background:#fafbfd}
.product-image{width:60px;height:60px;object-fit:cover;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.08)}
.product-name{font-weight:700}
.product-description{color:#667;font-size:.85rem;max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.status-badge{padding:.35rem .8rem;border-radius:20px;font-size:.8rem;font-weight:700;text-transform:uppercase}
.status-active{background:#d4edda;color:#155724}
.status-inactive{background:#f8d7da;color:#721c24}
.category-badge{background:#e3f2fd;color:#1976d2;padding:.25rem .6rem;border-radius:12px;font-size:.8rem;text-transform:capitalize}
.price-display{font-weight:700;color:#8b5a3c}
.stock-indicator{font-weight:700}
.stock-good{color:#28a745}.stock-low{color:#ffc107}.stock-out{color:#dc3545}
.action-buttons{display:flex;gap:.4rem}
.pagination{display:flex;justify-content:center;gap:.5rem;padding:1.25rem}
.pagination a,.pagination span{padding:.6rem .85rem;border-radius:8px;border:1px solid #e1e8ed;color:#8b5a3c;text-decoration:none}
.pagination .current{background:#8b5a3c;color:#fff}
.alert{padding:1rem 1.25rem;border-radius:8px;margin-bottom:1rem;border-left:4px solid}
.alert-success{background:#d4edda;color:#155724;border-color:#28a745}
.alert-danger{background:#f8d7da;color:#721c24;border-color:#dc3545}
.featured-badge{background:#fff3cd;color:#856404;padding:.2rem .45rem;border-radius:10px;font-size:.7rem;font-weight:700;margin-left:.4rem}
@media (max-width:768px){.container{padding:1rem}.filters-grid{grid-template-columns:1fr}.stats-grid{grid-template-columns:repeat(2,1fr)}.admin-nav{flex-direction:column;gap:.5rem}.page-header{flex-direction:column;gap:.75rem;align-items:stretch}}
</style>
</head>
<body>
<header class="admin-header">
  <div class="container">
    <h1><i class="fas fa-gem"></i> Velvet Vogue Admin</h1>
    <nav class="admin-nav">
      <a href="admin.php"><i class="fas fa-gauge"></i> Dashboard</a>
      <a href="admin-products.php" style="background:rgba(255,255,255,.2)"><i class="fas fa-box"></i> Products</a>
      <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>
  </div>
</header>

<main class="container">
  <?php if ($success_message): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= e($success_message) ?></div>
  <?php endif; ?>
  <?php if ($error_message): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= e($error_message) ?></div>
  <?php endif; ?>

  <div class="page-header">
    <h1><i class="fas fa-box"></i> Product Management</h1>
    <a href="admin-product-add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add New Product</a>
  </div>

  <!-- Stats -->
  <div class="stats-grid">
    <div class="stat-card"><div class="icon"><i class="fas fa-boxes"></i></div><h3><?= number_format((int)$stats['total']) ?></h3><p>Total Products</p></div>
    <div class="stat-card"><div class="icon"><i class="fas fa-check-circle"></i></div><h3><?= number_format((int)$stats['active']) ?></h3><p>Active</p></div>
    <div class="stat-card"><div class="icon"><i class="fas fa-exclamation-triangle"></i></div><h3><?= number_format((int)$stats['low_stock']) ?></h3><p>Low Stock</p></div>
    <div class="stat-card"><div class="icon"><i class="fas fa-times-circle"></i></div><h3><?= number_format((int)$stats['out_of_stock']) ?></h3><p>Out of Stock</p></div>
  </div>

  <!-- Filters -->
  <div class="filters-section">
    <form method="GET" class="filters-grid">
      <div class="form-group">
        <label for="search">Search Products</label>
        <input id="search" name="search" class="form-control" placeholder="Search by name or description..." value="<?= e($search) ?>">
      </div>

      <div class="form-group">
        <label for="category">Category</label>
        <select id="category" name="category" class="form-control">
          <option value="">All Categories</option>
          <?php foreach ($ENUM_CATEGORIES as $val => $label): ?>
            <option value="<?= e($val) ?>" <?= $category_filter===$val?'selected':'' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label for="stock">Stock Level</label>
        <select id="stock" name="stock" class="form-control">
          <option value="">All Stock</option>
          <option value="low" <?= $stock_filter==='low'?'selected':'' ?>>Low (≤5)</option>
          <option value="out" <?= $stock_filter==='out'?'selected':'' ?>>Out of Stock</option>
        </select>
      </div>

      <div class="form-group">
        <label for="status">Status</label>
        <select id="status" name="status" class="form-control">
          <option value="all"      <?= $status_filter==='all'?'selected':'' ?>>All</option>
          <option value="active"   <?= $status_filter==='active'?'selected':'' ?>>Active</option>
          <option value="inactive" <?= $status_filter==='inactive'?'selected':'' ?>>Inactive</option>
        </select>
      </div>

      <div><button class="btn btn-primary" type="submit"><i class="fas fa-search"></i> Filter</button></div>
    </form>
  </div>

  <!-- Table -->
  <div class="table-container">
    <div class="table-header">
      <h3><i class="fas fa-list"></i> Products (<?= number_format($total_products) ?> total)</h3>
      <div><button type="button" id="select-all" class="btn btn-outline btn-sm"><i class="fas fa-check-square"></i> Select All</button></div>
    </div>

    <!-- Bulk actions -->
    <div class="bulk-actions" id="bulk-actions">
      <form method="POST" id="bulk-form">
        <?= csrf_field(); ?>
        <div style="display:flex;align-items:center;gap:1rem;">
          <span><strong id="selected-count">0</strong> items selected</span>
          <select name="bulk_action" class="form-control" style="width:auto;">
            <option value="">Choose action...</option>
            <option value="activate">Activate Selected</option>
            <option value="deactivate">Deactivate Selected</option>
            <option value="delete">Delete Selected</option>
          </select>
          <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('Are you sure?')"><i class="fas fa-play"></i> Apply</button>
          <button type="button" id="cancel-selection" class="btn btn-outline btn-sm"><i class="fas fa-times"></i> Cancel</button>
        </div>
      </form>
    </div>

    <div class="table-responsive">
      <table>
        <thead>
          <tr>
            <th width="50"><input type="checkbox" id="master-checkbox"></th>
            <th width="80">Image</th>
            <th>Product</th>
            <th>Category</th>
            <th>Price</th>
            <th>Stock</th>
            <th>Status</th>
            <th width="150">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($products): foreach ($products as $p): ?>
            <tr>
              <td><input type="checkbox" class="product-checkbox" value="<?= (int)$p['id'] ?>"></td>
              <td>
                <?php
                  $img = trim((string)($p['image_url'] ?? ''), "\"'");
                  $img = str_replace('\\','/',$img);
                  $safeImg = $img !== '' ? $img : '';
                ?>
                <?php if ($safeImg): ?>
                  <img src="<?= e($safeImg) ?>" alt="<?= e($p['name']) ?>" class="product-image" onerror="this.src='assets/images/placeholder.png'">
                <?php else: ?>
                  <div class="product-image" style="background:#f8f9fa;display:flex;align-items:center;justify-content:center;color:#7f8c8d;">
                    <i class="fas fa-image"></i>
                  </div>
                <?php endif; ?>
              </td>
              <td>
                <div class="product-name"><?= e($p['name']) ?></div>
                <?php if (!empty($p['description'])): ?>
                  <div class="product-description"><?= e($p['description']) ?></div>
                <?php endif; ?>
                <?php if (!empty($p['is_featured'])): ?>
                  <span class="featured-badge"><i class="fas fa-star"></i> Featured</span>
                <?php endif; ?>
              </td>
              <td>
                <?php
                  $friendly = $p['category_name'] ?: ($ENUM_CATEGORIES[$p['category']] ?? ucfirst((string)$p['category']));
                ?>
                <span class="category-badge"><?= e($friendly) ?></span>
              </td>
              <td>
                <?php
                  $price = (float)$p['price'];
                  $sale  = $p['sale_price'] !== null ? (float)$p['sale_price'] : null;
                ?>
                <span class="price-display">
                  <?php if ($sale !== null): ?>
                    <span style="text-decoration:line-through;opacity:.7;margin-right:.35rem">$<?= number_format($price,2) ?></span>
                    $<?= number_format($sale,2) ?>
                  <?php else: ?>
                    $<?= number_format($price,2) ?>
                  <?php endif; ?>
                </span>
              </td>
              <td>
                <?php
                  $stock = (int)$p['stock'];
                  $sc = $stock === 0 ? 'stock-out' : ($stock <= 5 ? 'stock-low' : 'stock-good');
                ?>
                <span class="stock-indicator <?= $sc ?>">
                  <?= number_format($stock) ?>
                  <?php if ($stock === 0): ?><i class="fas fa-times-circle"></i><?php elseif ($stock <= 5): ?><i class="fas fa-exclamation-triangle"></i><?php endif; ?>
                </span>
              </td>
              <td>
                <?php $active = (int)$p['is_active'] === 1; ?>
                <span class="status-badge <?= $active ? 'status-active':'status-inactive' ?>"><?= $active ? 'Active' : 'Inactive' ?></span>
              </td>
              <td>
                <div class="action-buttons">
                  <a class="btn btn-outline btn-sm" href="admin-product-edit.php?id=<?= (int)$p['id'] ?>" title="Edit"><i class="fas fa-edit"></i></a>
                  <a class="btn btn-outline btn-sm" style="border-color:#dc3545;color:#dc3545" href="admin-product-delete.php?id=<?= (int)$p['id'] ?>" title="Delete" onclick="return confirm('Delete this product?')"><i class="fas fa-trash"></i></a>
                </div>
              </td>
            </tr>
          <?php endforeach; else: ?>
            <tr>
              <td colspan="8" style="text-align:center;padding:3rem;color:#7f8c8d;">
                <i class="fas fa-box-open" style="font-size:3rem;display:block;margin-bottom:1rem;"></i>
                <h3>No products found</h3>
                <p>
                  <?php if ($search || $category_filter || $stock_filter || $status_filter !== 'all'): ?>
                    Try adjusting your filters or <a href="admin-products.php">view all products</a>.
                  <?php else: ?>
                    <a href="admin-product-add.php" class="btn btn-primary" style="margin-top:1rem;"><i class="fas fa-plus"></i> Add Your First Product</a>
                  <?php endif; ?>
                </p>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
      <div class="pagination">
        <?php
          $qp = $_GET;
          if ($page > 1) { $qp['page'] = $page - 1; echo '<a href="?'.e(http_build_query($qp)).'"><i class="fas fa-chevron-left"></i> Previous</a>'; }
          for ($i = max(1,$page-2); $i <= min($total_pages,$page+2); $i++) {
            $qp['page'] = $i;
            if ($i === $page) echo '<span class="current">'.$i.'</span>';
            else echo '<a href="?'.e(http_build_query($qp)).'">'.$i.'</a>';
          }
          if ($page < $total_pages) { $qp['page'] = $page + 1; echo '<a href="?'.e(http_build_query($qp)).'">Next <i class="fas fa-chevron-right"></i></a>'; }
        ?>
      </div>
    <?php endif; ?>
  </div>
</main>

<script>
const master = document.getElementById('master-checkbox');
const productBoxes = Array.from(document.querySelectorAll('.product-checkbox'));
const bulkBar = document.getElementById('bulk-actions');
const selectedCount = document.getElementById('selected-count');
const selectAllBtn = document.getElementById('select-all');
const cancelBtn = document.getElementById('cancel-selection');
const bulkForm = document.getElementById('bulk-form');

function syncBulk() {
  const selected = productBoxes.filter(cb => cb.checked);
  selectedCount.textContent = String(selected.length);
  bulkBar.style.display = selected.length ? 'flex' : 'none';

  bulkForm.querySelectorAll('input[name="selected_products[]"]').forEach(n => n.remove());
  selected.forEach(cb => {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'selected_products[]';
    input.value = cb.value;
    bulkForm.appendChild(input);
  });

  if (selected.length === productBoxes.length && productBoxes.length) {
    master.checked = true; master.indeterminate = false;
  } else if (selected.length > 0) {
    master.checked = false; master.indeterminate = true;
  } else {
    master.checked = false; master.indeterminate = false;
  }
}
master?.addEventListener('change', () => { productBoxes.forEach(cb => cb.checked = master.checked); syncBulk(); });
productBoxes.forEach(cb => cb.addEventListener('change', syncBulk));
selectAllBtn?.addEventListener('click', () => { productBoxes.forEach(cb => cb.checked = true); syncBulk(); });
cancelBtn?.addEventListener('click', () => { productBoxes.forEach(cb => cb.checked = false); master.checked = false; master.indeterminate = false; syncBulk(); });

// Auto-submit on filter change
document.querySelectorAll('.filters-section select').forEach(sel => sel.addEventListener('change', () => sel.form.submit()));
// Debounced search
let t; document.getElementById('search')?.addEventListener('input', function(){ clearTimeout(t); t = setTimeout(() => this.form.submit(), 500); });
</script>
<?php include 'includes/footer.php'; ?>
</body>
</html>
