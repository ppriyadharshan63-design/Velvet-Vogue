<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Get featured products
$featured_products = getFeaturedProducts($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Velvet Vogue - Premium Fashion Store</title>
    <meta name="description" content="Discover premium fashion at Velvet Vogue. Shop the latest trends in men's and women's clothing, accessories, and formal wear with fast shipping.">
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <h1>Welcome to Velvet Vogue</h1>
            <p>Discover the finest collection of premium fashion</p>
            <a href="products.php" class="btn btn-primary">Shop Now</a>
        </div>
        <style>
.hero {
    position: relative;
    height: 100vh;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    color: white;
}

.background-video {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    z-index: 0;
    opacity: 0.7; /* Optional: adjust for readability */
}

.hero-content {
    position: relative;
    z-index: 1;
    max-width: 800px;
    padding: 20px;
}
</style>

            
    <video autoplay muted loop playsinline class="background-video">
        <source src="assets/images/videoplayback.mp4" type="video/mp4">
        Your browser does not support the video tag.
    </video>

    

    </section>

   <!-- Categories Section -->
<section class="categories">
    <div class="container">
        <h2 class="section-title">Shop by Category</h2>
        <div class="category-grid">
            <!-- Men's Fashion -->
            <div class="category-card">
                <img src="assets\images\products\The Perfect Valentine’s look for every man.jpeg" alt="Men's Fashion" />
                <div class="category-overlay">
                    <h3>Men's Fashion</h3>
                    <a href="products.php?category=mens" class="btn btn-secondary">Shop Men's</a>
                </div>
            </div>

            <!-- Women's Fashion -->
            <div class="category-card">
                <img src="assets\images\products\Upgrade your wardrobe with the most stylish summer….jpeg" alt="Women's Fashion" />
                <div class="category-overlay">
                    <h3>Women's Fashion</h3>
                    <a href="products.php?category=womens" class="btn btn-secondary">Shop Women's</a>
                </div>
            </div>

            <!-- Accessories -->
            <div class="category-card">
                <img src="assets/images/products/dee94483-782e-4a86-ac60-a88c5b243c55.jpeg" alt="Accessories" />
                <div class="category-overlay">
                    <h3>Accessories</h3>
                    <a href="products.php?category=accessories" class="btn btn-secondary">Shop Accessories</a>
                </div>
            </div>

            <!-- Formal Wear -->
            <div class="category-card">
                <img src="assets/images/products/e43b119f-2e4e-4a1e-91f8-aad4672a01b9.jpeg" alt="Formal Wear" />
                <div class="category-overlay">
                    <h3>Formal Wear</h3>
                    <a href="products.php?category=formal" class="btn btn-secondary">Shop Formal</a>
                </div>
            </div>
        </div>
    </div>
</section>


    <!-- Featured Products -->
    <section class="featured-products">
        <div class="container">
            <h2>Featured Products</h2>
            <div class="products-grid">
                <?php if ($featured_products && $featured_products->num_rows > 0): ?>
                    <?php while($product = $featured_products->fetch_assoc()): ?>
                        <div class="product-card">
                            <div class="product-image">
                                <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" />
                                <?php if ($product['sale_price']): ?>
                                    <span class="sale-badge">Sale</span>
                                <?php endif; ?>
                            </div>
                            <div class="product-info">
                                <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                                <p class="product-category"><?php echo htmlspecialchars($product['category']); ?></p>
                                <div class="product-price">
                                    <?php if ($product['sale_price']): ?>
                                        <span class="original-price">$<?php echo number_format($product['price'], 2); ?></span>
                                        <span class="sale-price">$<?php echo number_format($product['sale_price'], 2); ?></span>
                                    <?php else: ?>
                                        <span class="price">$<?php echo number_format($product['price'], 2); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="product-actions">
                                    <a href="product-detail.php?id=<?php echo $product['id']; ?>" class="btn btn-outline">View Details</a>
                                    <button onclick="addToCart(<?php echo $product['id']; ?>)" class="btn btn-primary">Add to Cart</button>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p>No featured products available.</p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>

    <script src="assets/js/main.js"></script>
</body>
</html>