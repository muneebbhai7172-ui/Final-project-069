/**
 * Global Cart and Utility Functions
 * Compatible with all pages
 */

// Initialize cart from localStorage
function initCart() {
    return JSON.parse(localStorage.getItem('cart')) || [];
}

// Save cart to localStorage
function saveCart(cart) {
    localStorage.setItem('cart', JSON.stringify(cart));
    updateCartCount();
}

// Update cart count badge
function updateCartCount() {
    const cart = initCart();
    const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
    const cartCountElement = document.getElementById('cart-count');
    const floatCartCount = document.getElementById('float-cart-count');
    const floatingCartBtn = document.getElementById('floating-cart-btn');

    // Update navbar cart badge
    if (cartCountElement) {
        if (totalItems > 0) {
            cartCountElement.textContent = totalItems;
            cartCountElement.classList.remove('d-none');
        } else {
            cartCountElement.classList.add('d-none');
        }
    }

    // Update floating cart button count
    if (floatCartCount) {
        floatCartCount.textContent = totalItems;
    }

    // Show/hide floating cart button
    if (floatingCartBtn) {
        if (totalItems > 0) {
            floatingCartBtn.style.display = 'block';
        } else {
            floatingCartBtn.style.display = 'none';
        }
    }

    console.log('Cart count updated:', totalItems);
}

// Add to cart with quantity
function addToCartWithQuantity(id, name, price, image = '') {
    const cart = initCart();
    const qtyInput = document.getElementById('qty-' + id);
    const quantity = qtyInput ? parseInt(qtyInput.value) || 1 : 1;

    // Check if item already exists
    const existingItem = cart.find(item => item.id === id);

    if (existingItem) {
        existingItem.quantity += quantity;
    } else {
        cart.push({
            id: id,
            name: name,
            price: parseFloat(price),
            quantity: quantity,
            image: image
        });
    }

    saveCart(cart);

    // Show notification
    if (typeof showNotification === 'function') {
        showNotification(`${name} added to cart!`, 'success');
    }
}

// Go to cart page
function goToCart() {
    window.location.href = '../shop/cart.html';
}

// Update cart display on cart.html page
function updateCartDisplay() {
    const cart = initCart();
    const cartItemsBody = document.getElementById('cart-items');
    const cartTotalElement = document.getElementById('cart-total');
    const clearCartBtn = document.getElementById('clear-cart-btn');

    if (!cartItemsBody || !cartTotalElement) {
        console.log('Cart display elements not found - not on cart page');
        return;
    }

    console.log('Updating cart display, cart items:', cart);

    // Clear existing items
    cartItemsBody.innerHTML = '';

    if (cart.length === 0) {
        // Show empty cart message
        cartItemsBody.innerHTML = `
            <tr>
                <td colspan="5" class="text-center py-5">
                    <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                    <p class="text-muted mb-0">Your cart is empty</p>
                    <a href="../shop/search.html" class="btn btn-primary mt-3">
                        <i class="fas fa-pills me-2"></i>Browse Medicines
                    </a>
                </td>
            </tr>
        `;
        cartTotalElement.textContent = '0.00';
        if (clearCartBtn) {
            clearCartBtn.style.display = 'none';
        }
        return;
    }

    // Display cart items
    let total = 0;
    cart.forEach((item, index) => {
        const itemTotal = item.price * item.quantity;
        total += itemTotal;

        const row = document.createElement('tr');
        row.innerHTML = `
            <td>
                <div>
                    <strong>${item.name}</strong>
                    ${item.generic ? `<br><small class="text-muted">${item.generic}</small>` : ''}
                </div>
            </td>
            <td>Rs. ${item.price.toFixed(2)}</td>
            <td>
                <div class="input-group input-group-sm" style="width: 120px;">
                    <button class="btn btn-outline-secondary" type="button" onclick="decreaseCartQuantity(${index})">
                        <i class="fas fa-minus"></i>
                    </button>
                    <input type="text" class="form-control text-center" value="${item.quantity}" readonly>
                    <button class="btn btn-outline-secondary" type="button" onclick="increaseCartQuantity(${index})">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
            </td>
            <td><strong>Rs. ${itemTotal.toFixed(2)}</strong></td>
            <td>
                <button class="btn btn-danger btn-sm" onclick="removeFromCart(${index})">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;
        cartItemsBody.appendChild(row);
    });

    // Update total
    cartTotalElement.textContent = total.toFixed(2);

    // Show/hide clear cart button
    if (clearCartBtn) {
        clearCartBtn.style.display = cart.length > 0 ? 'block' : 'none';
    }

    console.log('Cart display updated, total:', total);
}

// Increase item quantity in cart
function increaseCartQuantity(index) {
    const cart = initCart();
    if (cart[index]) {
        cart[index].quantity += 1;
        saveCart(cart);
        updateCartDisplay();
    }
}

// Decrease item quantity in cart
function decreaseCartQuantity(index) {
    const cart = initCart();
    if (cart[index] && cart[index].quantity > 1) {
        cart[index].quantity -= 1;
        saveCart(cart);
        updateCartDisplay();
    }
}

// Remove item from cart
let pendingRemoveIndex = null;

function removeFromCart(index) {
    const cart = initCart();
    const item = cart[index];

    // Store the index for confirmation
    pendingRemoveIndex = index;

    // Update modal with item name
    const itemNameElement = document.getElementById('removeItemName');
    if (itemNameElement && item) {
        itemNameElement.textContent = `Are you sure you want to remove "${item.name}" from your cart?`;
    }

    // Show Bootstrap modal
    const removeModal = new bootstrap.Modal(document.getElementById('removeItemModal'));
    removeModal.show();
}

function confirmRemoveItem() {
    if (pendingRemoveIndex === null) return;

    const cart = initCart();
    cart.splice(pendingRemoveIndex, 1);
    saveCart(cart);
    updateCartDisplay();

    if (typeof showNotification === 'function') {
        showNotification('Item removed from cart', 'success');
    }

    // Hide modal
    const removeModal = bootstrap.Modal.getInstance(document.getElementById('removeItemModal'));
    if (removeModal) {
        removeModal.hide();
    }

    // Reset pending index
    pendingRemoveIndex = null;
}

// Clear entire cart (with confirmation)
function clearCart() {
    if (confirm('Are you sure you want to clear your entire cart?')) {
        localStorage.removeItem('cart');
        updateCartCount();
        updateCartDisplay();

        if (typeof showNotification === 'function') {
            showNotification('Cart cleared', 'success');
        }
    }
}

// Force clear cart (without confirmation - used by modal)
function forceClearCart() {
    console.log('Force clearing cart...');
    localStorage.removeItem('cart');
    updateCartCount();
    updateCartDisplay();
    console.log('Cart cleared successfully');
}

// Update cart count on page load
document.addEventListener('DOMContentLoaded', function() {
    updateCartCount();

    // If on cart page, display cart items
    if (document.getElementById('cart-items')) {
        updateCartDisplay();
    }
});
