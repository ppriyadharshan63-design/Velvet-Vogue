// Velvet Vogue E-commerce JavaScript

// Global variables
let isCartOpen = false;

// DOM ready function
document.addEventListener('DOMContentLoaded', function() {
    // Initialize cart functionality
    loadCartItems();
    
    // Add event listeners for quantity controls
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('quantity-btn')) {
            handleQuantityChange(e);
        }
        
        if (e.target.classList.contains('remove-item')) {
            handleRemoveItem(e);
        }
    });
});

// Toggle cart sidebar
function toggleCart() {
    const cartSidebar = document.getElementById('cart-sidebar');
    const cartOverlay = document.getElementById('cart-overlay');
    
    if (isCartOpen) {
        cartSidebar.classList.remove('open');
        cartOverlay.style.display = 'none';
        isCartOpen = false;
    } else {
        cartSidebar.classList.add('open');
        cartOverlay.style.display = 'block';
        isCartOpen = true;
        loadCartItems(); // Refresh cart items when opening
    }
}
function updateTrackingNumber() {
    const trackingNumber = document.getElementById('tracking_number').value.trim();
    if (trackingNumber === '') {
        alert("Please enter a tracking number.");
        return;
    }

    // Send to backend via AJAX or form post...
    console.log("Tracking number updated:", trackingNumber);

    // Show feedback to user (optional)
    alert("Tracking number saved.");
}


// Add item to cart
async function addToCart(productId, quantity = 1, size = null, color = null) {
    const data = {
        product_id: productId,
        quantity: quantity,
        size: size,
        color: color
    };
    
    try {
        const response = await fetch('api/cart_add.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showMessage('Item added to cart!', 'success');
            updateCartCount();
            loadCartItems();
        } else {
            showMessage(result.message || 'Failed to add item to cart', 'error');
        }
    } catch (error) {
        console.error('Error adding to cart:', error);
        showMessage('Failed to add item to cart', 'error');
    }
}

// Load cart items
async function loadCartItems() {
    try {
        const response = await fetch('api/cart_get.php');
        const result = await response.json();
        
        if (result.success) {
            displayCartItems(result.items);
            updateCartTotal(result.total);
            updateCartCount(result.count);
        }
    } catch (error) {
        console.error('Error loading cart items:', error);
    }
}

// Display cart items in sidebar
function displayCartItems(items) {
    const cartItemsContainer = document.getElementById('cart-items');
    
    if (items.length === 0) {
        cartItemsContainer.innerHTML = '<p style="text-align: center; padding: 2rem; color: #666;">Your cart is empty</p>';
        return;
    }
    
    let html = '';
    items.forEach(item => {
        const price = item.sale_price || item.price;
        const originalPrice = item.sale_price ? item.price : null;
        
        html += `
            <div class="cart-item" data-cart-id="${item.id}">
                <img src="${item.image_url}" alt="${item.name}" onerror="this.src='assets/images/placeholder.jpg'">
                <div class="cart-item-info">
                    <div class="cart-item-name">${item.name}</div>
                    ${item.size ? `<div style="font-size: 0.9rem; color: #666;">Size: ${item.size}</div>` : ''}
                    ${item.color ? `<div style="font-size: 0.9rem; color: #666;">Color: ${item.color}</div>` : ''}
                    <div class="cart-item-price">
                        ${originalPrice ? `<span style="text-decoration: line-through; color: #999; margin-right: 0.5rem;">$${parseFloat(originalPrice).toFixed(2)}</span>` : ''}
                        $${parseFloat(price).toFixed(2)}
                    </div>
                    <div class="quantity-controls">
                        <button class="quantity-btn" data-action="decrease" data-cart-id="${item.id}">-</button>
                        <span style="padding: 0 10px;">${item.quantity}</span>
                        <button class="quantity-btn" data-action="increase" data-cart-id="${item.id}">+</button>
                        <button class="remove-item" data-cart-id="${item.id}" style="margin-left: 10px; color: #e74c3c; background: none; border: none; cursor: pointer;">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
    });
    
    cartItemsContainer.innerHTML = html;
}

// Handle quantity change
async function handleQuantityChange(e) {
    const cartId = e.target.getAttribute('data-cart-id');
    const action = e.target.getAttribute('data-action');
    
    try {
        const response = await fetch('api/cart_update.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                cart_id: cartId,
                action: action
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            loadCartItems();
        } else {
            showMessage(result.message || 'Failed to update cart', 'error');
        }
    } catch (error) {
        console.error('Error updating cart:', error);
        showMessage('Failed to update cart', 'error');
    }
}

// Handle remove item
async function handleRemoveItem(e) {
    if (!confirm('Are you sure you want to remove this item from your cart?')) {
        return;
    }
    
    const cartId = e.target.closest('.remove-item').getAttribute('data-cart-id');
    
    try {
        const response = await fetch('api/cart_remove.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                cart_id: cartId
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showMessage('Item removed from cart', 'success');
            loadCartItems();
        } else {
            showMessage(result.message || 'Failed to remove item', 'error');
        }
    } catch (error) {
        console.error('Error removing item:', error);
        showMessage('Failed to remove item', 'error');
    }
}

// Update cart count in header
function updateCartCount(count = null) {
    if (count === null) {
        // Fetch current count
        fetch('api/cart_count.php')
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    updateCartCountDisplay(result.count);
                }
            });
    } else {
        updateCartCountDisplay(count);
    }
}

// Update cart count display
function updateCartCountDisplay(count) {
    const cartCountElement = document.getElementById('cart-count');
    
    if (count > 0) {
        if (cartCountElement) {
            cartCountElement.textContent = count;
            cartCountElement.style.display = 'flex';
        } else {
            // Create cart count element if it doesn't exist
            const cartIcon = document.querySelector('.cart-icon');
            const newCountElement = document.createElement('span');
            newCountElement.id = 'cart-count';
            newCountElement.className = 'cart-count';
            newCountElement.textContent = count;
            cartIcon.appendChild(newCountElement);
        }
    } else {
        if (cartCountElement) {
            cartCountElement.style.display = 'none';
        }
    }
}

// Update cart total
function updateCartTotal(total) {
    const cartTotalElement = document.getElementById('cart-total');
    if (cartTotalElement) {
        cartTotalElement.textContent = `Total: $${parseFloat(total || 0).toFixed(2)}`;
    }
}

// Show message to user
function showMessage(message, type = 'info') {
    // Remove existing messages
    const existingMessages = document.querySelectorAll('.alert-message');
    existingMessages.forEach(msg => msg.remove());
    
    // Create new message
    const messageDiv = document.createElement('div');
    messageDiv.className = `alert alert-${type} alert-message`;
    messageDiv.textContent = message;
    messageDiv.style.position = 'fixed';
    messageDiv.style.top = '100px';
    messageDiv.style.right = '20px';
    messageDiv.style.zIndex = '9999';
    messageDiv.style.minWidth = '300px';
    messageDiv.style.animation = 'slideIn 0.3s ease-out';
    
    document.body.appendChild(messageDiv);
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        messageDiv.style.animation = 'slideOut 0.3s ease-out';
        setTimeout(() => {
            if (messageDiv.parentNode) {
                messageDiv.remove();
            }
        }, 300);
    }, 3000);
}

// Add to wishlist
async function addToWishlist(productId) {
    try {
        const response = await fetch('api/wishlist_add.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                product_id: productId
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showMessage('Item added to wishlist!', 'success');
        } else {
            showMessage(result.message || 'Failed to add to wishlist', 'error');
        }
    } catch (error) {
        console.error('Error adding to wishlist:', error);
        showMessage('Failed to add to wishlist', 'error');
    }
}

// Product filtering
function filterProducts() {
    const category = document.getElementById('category-filter')?.value || '';
    const minPrice = document.getElementById('min-price')?.value || '';
    const maxPrice = document.getElementById('max-price')?.value || '';
    const search = document.getElementById('search-input')?.value || '';
    
    const params = new URLSearchParams();
    if (category) params.append('category', category);
    if (minPrice) params.append('min_price', minPrice);
    if (maxPrice) params.append('max_price', maxPrice);
    if (search) params.append('search', search);
    
    window.location.href = 'products.php?' + params.toString();
}

// Image gallery functionality
function changeProductImage(imageSrc) {
    const mainImage = document.getElementById('main-product-image');
    if (mainImage) {
        mainImage.src = imageSrc;
    }
}

// Form validation
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;
    
    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.style.borderColor = '#e74c3c';
            isValid = false;
        } else {
            field.style.borderColor = '#ddd';
        }
    });
    
    // Email validation
    const emailFields = form.querySelectorAll('input[type="email"]');
    emailFields.forEach(field => {
        if (field.value && !isValidEmail(field.value)) {
            field.style.borderColor = '#e74c3c';
            isValid = false;
        }
    });
    
    return isValid;
}

// Email validation helper
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Loading state management
function setLoadingState(buttonElement, isLoading) {
    if (isLoading) {
        buttonElement.disabled = true;
        buttonElement.innerHTML = '<span class="loading"></span> Loading...';
    } else {
        buttonElement.disabled = false;
        buttonElement.innerHTML = buttonElement.getAttribute('data-original-text') || 'Submit';
    }
}

// Initialize loading buttons
document.addEventListener('DOMContentLoaded', function() {
    const submitButtons = document.querySelectorAll('button[type="submit"], .submit-btn');
    submitButtons.forEach(button => {
        button.setAttribute('data-original-text', button.innerHTML);
    });
});

// Handle form submissions with loading states
function handleFormSubmit(formElement, callback) {
    const submitButton = formElement.querySelector('button[type="submit"], .submit-btn');
    
    if (submitButton) {
        setLoadingState(submitButton, true);
    }
    
    callback().finally(() => {
        if (submitButton) {
            setLoadingState(submitButton, false);
        }
    });
}

// CSS animations for messages
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);