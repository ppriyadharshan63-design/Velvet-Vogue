<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$product_id = intval($_GET['id'] ?? 0);

if (!$product_id) {
    header('Location: products.php');
    exit;
}

$product = getProductById($conn, $product_id);

if (!$product) {
    header('Location: products.php');
    exit;
}

// Parse JSON fields
$sizes = json_decode($product['sizes'] ?? '[]', true) ?: [];
$colors = json_decode($product['colors'] ?? '[]', true) ?: [];
$gallery_images = json_decode($product['gallery_images'] ?? '[]', true) ?: [];

// Get categories for navigation
$categories = getCategories();

// Handle add to cart
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $quantity = max(1, intval($_POST['quantity'] ?? 1));
    $size = sanitizeInput($_POST['size'] ?? '');
    $color = sanitizeInput($_POST['color'] ?? '');
    
    if ($product['stock'] >= $quantity) {
        $user_id = getUserId();
        $result = addToCart($conn, $user_id, $product_id, $quantity, $size ?: null, $color ?: null);
        
        if ($result) {
            $message = 'Item added to cart successfully!';
            $message_type = 'success';
        } else {
            $message = 'Failed to add item to cart.';
            $message_type = 'error';
        }
    } else {
        $message = 'Sorry, we don\'t have enough stock for your request.';
        $message_type = 'error';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?> - Velvet Vogue</title>
    <meta name="description" content="<?php echo htmlspecialchars(substr($product['description'], 0, 160)); ?>... Shop this premium <?php echo strtolower($categories[$product['category']] ?? $product['category']); ?> item at Velvet Vogue.">
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <section style="padding: 6rem 0 4rem; background: #f8f9fa; min-height: 100vh;">
        <div class="container">
            <!-- Breadcrumb -->
            <nav style="margin-bottom: 2rem;">
                <ol style="display: flex; list-style: none; padding: 0; margin: 0; color: #666;">
                    <li><a href="index.php" style="color: #666; text-decoration: none;">Home</a></li>
                    <li style="margin: 0 0.5rem;">/</li>
                    <li><a href="products.php" style="color: #666; text-decoration: none;">Products</a></li>
                    <li style="margin: 0 0.5rem;">/</li>
                    <li><a href="products.php?category=<?php echo $product['category']; ?>" style="color: #666; text-decoration: none;">
                        <?php echo $categories[$product['category']] ?? ucfirst($product['category']); ?>
                    </a></li>
                    <li style="margin: 0 0.5rem;">/</li>
                    <li style="color: #8b5a3c;"><?php echo htmlspecialchars($product['name']); ?></li>
                </ol>
            </nav>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>" style="margin-bottom: 2rem;">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 4rem; background: white; padding: 3rem; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                
                <!-- Product Images -->
                <div class="product-image-section">
    <!-- Main Product Image -->
    <div class="main-image-wrapper" style="margin-bottom: 1rem;">
        <img id="main-product-image"
             src="<?= htmlspecialchars($product['image_url']) ?>"
             alt="<?= htmlspecialchars($product['name']) ?>"
             style="width: 100%; height: 500px; object-fit: cover; border-radius: 10px;"
             onerror="this.onerror=null; this.src='assets/images/placeholder.jpg';">
    </div>

    <!-- Thumbnail Gallery -->
    <?php if (!empty($gallery_images)): ?>
        <div class="thumbnail-gallery" style="display: flex; gap: 1rem; overflow-x: auto;">
            <?php
            // Combine main image and gallery images for loop
            $all_images = array_merge([$product['image_url']], $gallery_images);
            foreach ($all_images as $image_url):
                $safe_url = htmlspecialchars($image_url);
            ?>
                <img src="<?= $safe_url ?>"
                     alt="<?= htmlspecialchars($product['name']) ?>"
                     onclick="changeProductImage('<?= $safe_url ?>')"
                     style="width: 80px; height: 80px; object-fit: cover; border-radius: 5px; cursor: pointer; border: 2px solid transparent;"
                     onmouseover="this.style.borderColor='#8b5a3c'"
                     onmouseout="this.style.borderColor='transparent'"
                     onerror="this.style.display='none'">
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<script>
function changeProductImage(src) {
    const mainImage = document.getElementById('main-product-image');
    if (mainImage) {
        mainImage.src = src;
    }
}
</script>

                
                <!-- Product Details -->
                <div>
                    <h1 style="color: #2c3e50; margin-bottom: 1rem; font-size: 2rem;">
                        <?php echo htmlspecialchars($product['name']); ?>
                    </h1>
                    
                    <div style="margin-bottom: 1rem;">
                        <span style="color: #666; background: #f8f9fa; padding: 0.5rem 1rem; border-radius: 20px; font-size: 0.9rem;">
                            <?php echo $categories[$product['category']] ?? ucfirst($product['category']); ?>
                        </span>
                        
                        <?php if (isOnSale($product)): ?>
                            <span style="background: #e74c3c; color: white; padding: 0.5rem 1rem; border-radius: 20px; font-size: 0.9rem; margin-left: 0.5rem;">
                                Sale
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <div style="margin-bottom: 2rem;">
                        <?php if (isOnSale($product)): ?>
                            <span style="text-decoration: line-through; color: #999; font-size: 1.2rem; margin-right: 1rem;">
                                $<?php echo number_format($product['price'], 2); ?>
                            </span>
                            <span style="color: #e74c3c; font-weight: bold; font-size: 2rem;">
                                $<?php echo number_format($product['sale_price'], 2); ?>
                            </span>
                            <span style="color: #28a745; font-size: 0.9rem; margin-left: 1rem;">
                                Save $<?php echo number_format($product['price'] - $product['sale_price'], 2); ?>
                            </span>
                        <?php else: ?>
                            <span style="color: #8b5a3c; font-weight: bold; font-size: 2rem;">
                                $<?php echo number_format($product['price'], 2); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <div style="margin-bottom: 2rem; color: #666; line-height: 1.6;">
                        <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                    </div>
                    
                    <!-- Product Options -->
                    <form method="POST" action="product-detail.php?id=<?php echo $product_id; ?>" style="margin-bottom: 2rem;">
                        <?php if (!empty($sizes)): ?>
                            <div style="margin-bottom: 1.5rem;">
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: bold; color: #333;">Size:</label>
                                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                    <?php foreach ($sizes as $size): ?>
                                        <label style="cursor: pointer;">
                                            <input type="radio" name="size" value="<?php echo htmlspecialchars($size); ?>" 
                                                   style="display: none;" onchange="updateSelection()">
                                            <span style="display: inline-block; padding: 0.5rem 1rem; border: 2px solid #ddd; border-radius: 5px; transition: all 0.3s;"
                                                  onmouseover="this.style.borderColor='#8b5a3c'"
                                                  onmouseout="if(!this.previousElementSibling.checked) this.style.borderColor='#ddd'">
                                                <?php echo htmlspecialchars($size); ?>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($colors)): ?>
                            <div style="margin-bottom: 1.5rem;">
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: bold; color: #333;">Color:</label>
                                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                    <?php foreach ($colors as $color): ?>
                                        <label style="cursor: pointer;">
                                            <input type="radio" name="color" value="<?php echo htmlspecialchars($color); ?>" 
                                                   style="display: none;" onchange="updateSelection()">
                                            <span style="display: inline-block; padding: 0.5rem 1rem; border: 2px solid #ddd; border-radius: 5px; transition: all 0.3s;"
                                                  onmouseover="this.style.borderColor='#8b5a3c'"
                                                  onmouseout="if(!this.previousElementSibling.checked) this.style.borderColor='#ddd'">
                                                <?php echo htmlspecialchars($color); ?>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div style="margin-bottom: 1.5rem;">
                            <label for="quantity" style="display: block; margin-bottom: 0.5rem; font-weight: bold; color: #333;">Quantity:</label>
                            <input type="number" id="quantity" name="quantity" value="1" min="1" max="<?php echo min(10, $product['stock']); ?>"
                                   style="width: 100px; padding: 0.75rem; border: 2px solid #ddd; border-radius: 5px; font-size: 1rem;">
                        </div>
                        
                        <div style="margin-bottom: 2rem;">
                            <?php if ($product['stock'] > 0): ?>
                                <span style="color: #28a745; font-size: 0.9rem;">
                                    <i class="fas fa-check-circle"></i> <?php echo $product['stock']; ?> in stock
                                </span>
                            <?php else: ?>
                                <span style="color: #e74c3c; font-size: 0.9rem;">
                                    <i class="fas fa-times-circle"></i> Out of stock
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <div style="display: flex; gap: 1rem; margin-bottom: 2rem;">
                            <?php if ($product['stock'] > 0): ?>
                                <button type="submit" name="add_to_cart" class="btn btn-primary" style="flex: 1;">
                                    <i class="fas fa-shopping-cart"></i> Add to Cart
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn" disabled style="flex: 1; background: #ccc; cursor: not-allowed;">
                                    <i class="fas fa-times"></i> Out of Stock
                                </button>
                            <?php endif; ?>
                            
                            <button type="button" onclick="addToWishlist(<?php echo $product_id; ?>)" 
                                    class="btn btn-outline" style="padding: 0.75rem 1.5rem;">
                                <i class="fas fa-heart"></i>
                            </button>
                        </div>
                    </form>
                    
                    <!-- Product Features -->
                    <div style="border-top: 1px solid #eee; padding-top: 2rem;">
                        <h4 style="margin-bottom: 1rem; color: #2c3e50;">Product Features</h4>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; font-size: 0.9rem;">
                            <div style="display: flex; align-items: center;">
                                <i class="fas fa-shipping-fast" style="color: #8b5a3c; margin-right: 0.5rem;"></i>
                                <span>Free shipping over $50</span>
                            </div>
                            
                            <div style="display: flex; align-items: center;">
                                <i class="fas fa-undo" style="color: #8b5a3c; margin-right: 0.5rem;"></i>
                                <span>30-day returns</span>
                            </div>
                            
                            <div style="display: flex; align-items: center;">
                                <i class="fas fa-shield-alt" style="color: #8b5a3c; margin-right: 0.5rem;"></i>
                                <span>Quality guarantee</span>
                            </div>
                            
                            <div style="display: flex; align-items: center;">
                                <i class="fas fa-award" style="color: #8b5a3c; margin-right: 0.5rem;"></i>
                                <span>Premium materials</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Related Products -->
            <section style="margin-top: 4rem;">
                <h3 style="color: #2c3e50; margin-bottom: 2rem;">You might also like</h3>
                
                <?php
                // Get related products from same category
                $related_sql = "SELECT * FROM products WHERE category = ? AND id != ? AND is_active = 1 ORDER BY RAND() LIMIT 4";
                $related_stmt = $conn->prepare($related_sql);
                $related_stmt->bind_param("si", $product['category'], $product_id);
                $related_stmt->execute();
                $related_result = $related_stmt->get_result();
                ?>
                
                <?php if ($related_result && $related_result->num_rows > 0): ?>
                    <div class="products-grid">
                        <?php while($related_product = $related_result->fetch_assoc()): ?>
                            <div class="product-card">
                                <div class="product-image">
                                    <img src="<?php echo htmlspecialchars($related_product['image_url']); ?>" 
                                         alt="<?php echo htmlspecialchars($related_product['name']); ?>"
                                         onerror="this.src='assets/images/placeholder.jpg'" />
                                    
                                    <?php if (isOnSale($related_product)): ?>
                                        <span class="sale-badge">Sale</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="product-info">
                                    <h3><?php echo htmlspecialchars($related_product['name']); ?></h3>
                                    <p class="product-category"><?php echo htmlspecialchars($categories[$related_product['category']] ?? $related_product['category']); ?></p>
                                    
                                    <div class="product-price">
                                        <?php if (isOnSale($related_product)): ?>
                                            <span class="original-price">$<?php echo number_format($related_product['price'], 2); ?></span>
                                            <span class="sale-price">$<?php echo number_format($related_product['sale_price'], 2); ?></span>
                                        <?php else: ?>
                                            <span class="price">$<?php echo number_format($related_product['price'], 2); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="product-actions">
                                        <a href="product-detail.php?id=<?php echo $related_product['id']; ?>" class="btn btn-outline">
                                            View Details
                                        </a>
                                        <button onclick="addToCart(<?php echo $related_product['id']; ?>)" class="btn btn-primary">
                                            Add to Cart
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>
    
    <script src="assets/js/main.js"></script>
    <script>
        // Update selection styles
        function updateSelection() {
            // Handle size selection
            document.querySelectorAll('input[name="size"]').forEach(input => {
                const span = input.nextElementSibling;
                if (input.checked) {
                    span.style.borderColor = '#8b5a3c';
                    span.style.backgroundColor = '#f8f5f3';
                } else {
                    span.style.borderColor = '#ddd';
                    span.style.backgroundColor = 'transparent';
                }
            });
            
            // Handle color selection
            document.querySelectorAll('input[name="color"]').forEach(input => {
                const span = input.nextElementSibling;
                if (input.checked) {
                    span.style.borderColor = '#8b5a3c';
                    span.style.backgroundColor = '#f8f5f3';
                } else {
                    span.style.borderColor = '#ddd';
                    span.style.backgroundColor = 'transparent';
                }
            });
        }
        
        // Auto-select first options if available
        document.addEventListener('DOMContentLoaded', function() {
            const firstSize = document.querySelector('input[name="size"]');
            const firstColor = document.querySelector('input[name="color"]');
            
            if (firstSize) {
                firstSize.checked = true;
                updateSelection();
            }
            
            if (firstColor) {
                firstColor.checked = true;
                updateSelection();
            }
        });
    </script>
</body>
</html>