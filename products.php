<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Get filter parameters
$category = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';
$min_price = $_GET['min_price'] ?? '';
$max_price = $_GET['max_price'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Build filters array
$filters = [];
if ($category) $filters['category'] = $category;
if ($search) $filters['search'] = $search;
if ($min_price) $filters['min_price'] = floatval($min_price);
if ($max_price) $filters['max_price'] = floatval($max_price);

// Get products
$products = getProductsByCategory($conn, $category, $search, $per_page, $offset);

// Get categories for filter
$categories = getCategories();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $category ? ucfirst($categories[$category] ?? $category) . ' - ' : ''; ?>Products - Velvet Vogue</title>
    <meta name="description" content="Shop our <?php echo $category ? strtolower($categories[$category] ?? $category) . ' collection' : 'complete collection of fashion products'; ?> at Velvet Vogue. High-quality clothing and accessories with fast shipping.">
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <!-- Page Header -->
    <section style="padding: 6rem 0 2rem; background: #f8f9fa; text-align: center;">
        <div class="container">
            <h1 style="font-size: 2.5rem; margin-bottom: 1rem; color: #2c3e50;">
                <?php 
                if ($category) {
                    echo $categories[$category] ?? ucfirst($category);
                } else {
                    echo 'All Products';
                }
                ?>
            </h1>
            <?php if ($search): ?>
                <p style="color: #666; font-size: 1.1rem;">Search results for: "<?php echo htmlspecialchars($search); ?>"</p>
            <?php endif; ?>
        </div>
    </section>

    <!-- Filters Section -->
    <section class="filters">
        <div class="container">
            <div class="filter-container">
                <div class="filter-group">
                    <select id="category-filter" onchange="filterProducts()">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat_key => $cat_name): ?>
                            <option value="<?php echo $cat_key; ?>" <?php echo $category === $cat_key ? 'selected' : ''; ?>>
                                <?php echo $cat_name; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <input type="number" id="min-price" placeholder="Min Price" value="<?php echo $min_price; ?>" min="0" step="0.01">
                    
                    <input type="number" id="max-price" placeholder="Max Price" value="<?php echo $max_price; ?>" min="0" step="0.01">
                    
                    <button onclick="filterProducts()" class="btn btn-primary">Apply Filters</button>
                    
                    <?php if ($category || $search || $min_price || $max_price): ?>
                        <a href="products.php" class="btn btn-outline">Clear Filters</a>
                    <?php endif; ?>
                </div>
                
                <div class="filter-group">
                    <form action="products.php" method="GET" style="display: flex; align-items: center; gap: 0.5rem;">
                        <?php if ($category): ?>
                            <input type="hidden" name="category" value="<?php echo $category; ?>">
                        <?php endif; ?>
                        <input type="text" name="search" id="search-input" placeholder="Search products..." 
                               value="<?php echo htmlspecialchars($search); ?>"
                               style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Products Grid -->
    <section style="padding: 3rem 0;">
        <div class="container">
            <?php if ($products && $products->num_rows > 0): ?>
                <div class="products-grid">
                    <?php while($product = $products->fetch_assoc()): ?>
                        <div class="product-card">
                            <div class="product-image">
                                <img 
    src="<?= htmlspecialchars($product['image_url']) ?>" 
    alt="<?= htmlspecialchars($product['name']) ?>" 
    loading="lazy" 
    class="product-image"
    onerror="this.onerror=null; this.src='assets/images/placeholder.jpg';" 
/>

                                
                                <?php if (isOnSale($product)): ?>
                                    <span class="sale-badge">Sale</span>
                                <?php endif; ?>
                                
                                <!-- Quick action buttons -->
                                <div style="position: absolute; top: 10px; left: 10px;">
                                    <button onclick="addToWishlist(<?php echo $product['id']; ?>)" 
                                            class="btn" 
                                            style="background: rgba(255,255,255,0.9); border: none; border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-heart" style="color: #e74c3c;"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="product-info">
                                <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                                <p class="product-category"><?php echo htmlspecialchars($categories[$product['category']] ?? $product['category']); ?></p>
                                
                                <div class="product-price">
                                    <?php if (isOnSale($product)): ?>
                                        <span class="original-price">$<?php echo number_format($product['price'], 2); ?></span>
                                        <span class="sale-price">$<?php echo number_format($product['sale_price'], 2); ?></span>
                                    <?php else: ?>
                                        <span class="price">$<?php echo number_format($product['price'], 2); ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($product['stock'] > 0): ?>
                                    <div class="product-actions">
                                        <a href="product-detail.php?id=<?php echo $product['id']; ?>" class="btn btn-outline">
                                            View Details
                                        </a>
                                        <button onclick="addToCart(<?php echo $product['id']; ?>)" class="btn btn-primary">
                                            Add to Cart
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div class="product-actions">
                                        <a href="product-detail.php?id=<?php echo $product['id']; ?>" class="btn btn-outline">
                                            View Details
                                        </a>
                                        <button class="btn" disabled style="background: #ccc; cursor: not-allowed;">
                                            Out of Stock
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
                
                <!-- Pagination would go here -->
                <div style="text-align: center; margin-top: 3rem;">
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                           class="btn btn-outline" style="margin-right: 1rem;">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>
                    
                    <span style="padding: 0 1rem; color: #666;">Page <?php echo $page; ?></span>
                    
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                       class="btn btn-outline" style="margin-left: 1rem;">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 4rem 2rem;">
                    <i class="fas fa-search" style="font-size: 4rem; color: #ccc; margin-bottom: 1rem;"></i>
                    <h3 style="color: #666; margin-bottom: 1rem;">No products found</h3>
                    <p style="color: #999; margin-bottom: 2rem;">
                        <?php if ($search): ?>
                            No products match your search criteria. Try adjusting your search terms or filters.
                        <?php else: ?>
                            No products available in this category at the moment.
                        <?php endif; ?>
                    </p>
                    <a href="products.php" class="btn btn-primary">Browse All Products</a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>

    <script src="assets/js/main.js"></script>
    <script>
        // Enhanced filtering function with price range
        function filterProducts() {
            const category = document.getElementById('category-filter').value;
            const minPrice = document.getElementById('min-price').value;
            const maxPrice = document.getElementById('max-price').value;
            const search = document.getElementById('search-input').value;
            
            const params = new URLSearchParams();
            if (category) params.append('category', category);
            if (minPrice) params.append('min_price', minPrice);
            if (maxPrice) params.append('max_price', maxPrice);
            if (search) params.append('search', search);
            
            window.location.href = 'products.php?' + params.toString();
        }
        
        // Handle Enter key in search input
        document.getElementById('search-input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                filterProducts();
            }
        });
    </script>
</body>
</html>