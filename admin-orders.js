// Admin Orders JavaScript functionality

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips and other interactive elements
    initializeTooltips();
    
    // Add event listeners
    addEventListeners();
    
    // Auto-refresh functionality (optional)
    // setInterval(refreshOrderStats, 60000); // Refresh every minute
});

function initializeTooltips() {
    // Add tooltip functionality for status badges and buttons
    const tooltipElements = document.querySelectorAll('[title]');
    tooltipElements.forEach(element => {
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
    });
}

function addEventListeners() {
    // Search form auto-submit on enter
    const searchInput = document.querySelector('input[name="search"]');
    if (searchInput) {
        searchInput.addEventListener('keyup', function(e) {
            if (e.key === 'Enter') {
                this.form.submit();
            }
        });
    }
    
    // Filter change auto-submit
    const filterSelects = document.querySelectorAll('select[name="status"], select[name="date"], select[name="sort"], select[name="order"]');
    filterSelects.forEach(select => {
        select.addEventListener('change', function() {
            this.form.submit();
        });
    });
    
    // Row click handlers
    const tableRows = document.querySelectorAll('tbody tr');
    tableRows.forEach(row => {
        row.addEventListener('click', function(e) {
            if (e.target.tagName !== 'BUTTON' && e.target.tagName !== 'A') {
                const viewButton = this.querySelector('a[href*="admin-order-detail"]');
                if (viewButton) {
                    window.location.href = viewButton.href;
                }
            }
        });
    });
}

function quickStatusUpdate(orderId) {
    const statusOptions = [
        { value: 'pending', label: 'Pending' },
        { value: 'processing', label: 'Processing' },
        { value: 'shipped', label: 'Shipped' },
        { value: 'delivered', label: 'Delivered' },
        { value: 'cancelled', label: 'Cancelled' }
    ];
    
    let optionsHtml = '';
    statusOptions.forEach(option => {
        optionsHtml += `<option value="${option.value}">${option.label}</option>`;
    });
    
    const newStatus = prompt(`Select new status for Order #${orderId}:\n\n${statusOptions.map(opt => `${opt.value} - ${opt.label}`).join('\n')}\n\nEnter status:`);
    
    if (newStatus && statusOptions.some(opt => opt.value === newStatus.toLowerCase())) {
        updateOrderStatus(orderId, newStatus.toLowerCase());
    } else if (newStatus) {
        alert('Invalid status selected. Please try again.');
    }
}

function updateOrderStatus(orderId, newStatus, comment = '') {
    const formData = new FormData();
    formData.append('action', 'update_status');
    formData.append('order_id', orderId);
    formData.append('status', newStatus);
    formData.append('comment', comment);
    
    fetch('admin-order-detail.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Order status updated successfully', 'success');
            // Refresh the page or update the UI
            location.reload();
        } else {
            showNotification('Error updating order status: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('An error occurred while updating the order status', 'error');
    });
}

function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
            <span>${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" class="notification-close">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    // Add styles
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#d4edda' : type === 'error' ? '#f8d7da' : '#d1ecf1'};
        color: ${type === 'success' ? '#155724' : type === 'error' ? '#721c24' : '#0c5460'};
        padding: 1rem;
        border-radius: 5px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        z-index: 1000;
        max-width: 300px;
        animation: slideIn 0.3s ease;
    `;
    
    document.body.appendChild(notification);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 5000);
}

function refreshOrderStats() {
    // Refresh order statistics without full page reload
    fetch('admin-orders.php?ajax=stats')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateStatsDisplay(data.stats);
            }
        })
        .catch(error => {
            console.error('Error refreshing stats:', error);
        });
}

function updateStatsDisplay(stats) {
    const statCards = document.querySelectorAll('.stat-card h3');
    if (statCards.length >= 4) {
        statCards[0].textContent = stats.total_orders;
        statCards[1].textContent = '$' + parseFloat(stats.total_revenue).toFixed(2);
        statCards[2].textContent = '$' + parseFloat(stats.avg_order_value).toFixed(2);
        statCards[3].textContent = stats.pending_orders;
    }
}

function showTooltip(e) {
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip';
    tooltip.textContent = e.target.getAttribute('title');
    tooltip.style.cssText = `
        position: absolute;
        background: #333;
        color: white;
        padding: 0.5rem;
        border-radius: 3px;
        font-size: 0.8rem;
        z-index: 1000;
        pointer-events: none;
        max-width: 200px;
    `;
    
    document.body.appendChild(tooltip);
    
    const rect = e.target.getBoundingClientRect();
    tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
    tooltip.style.top = rect.top - tooltip.offsetHeight - 5 + 'px';
    
    e.target.tooltipElement = tooltip;
    e.target.removeAttribute('title');
}

function hideTooltip(e) {
    if (e.target.tooltipElement) {
        e.target.tooltipElement.remove();
        e.target.setAttribute('title', e.target.tooltipElement.textContent);
        e.target.tooltipElement = null;
    }
}

// Add CSS for animations
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
    
    .notification-content {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .notification-close {
        background: none;
        border: none;
        cursor: pointer;
        opacity: 0.7;
        transition: opacity 0.2s;
    }
    
    .notification-close:hover {
        opacity: 1;
    }
    
    tbody tr {
        cursor: pointer;
        transition: background-color 0.2s;
    }
    
    tbody tr:hover {
        background-color: #f8f9fa;
    }
`;
document.head.appendChild(style);
