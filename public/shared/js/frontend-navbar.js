/**
 * Shared Frontend Navigation Component
 * Reusable navbar for all landing pages
 */

class FrontendNavbar {
    constructor() {
        this.cartCount = 0;
        this.init();
    }

    init() {
        this.render();
        this.setupScrollEffect();
        this.updateCartCount();
        this.setActiveLink();
    }

    render() {
        const navbarHTML = `
        <nav class="navbar navbar-expand-lg" id="mainNavbar">
            <div class="container">
                <a class="navbar-brand" href="/modules/frontend/index.html">
                    <div class="brand-logo">
                        <i class="fas fa-plus-circle"></i>
                    </div>
                    <div class="brand-text">
                        <span class="brand-name">Muneeb Drug House</span>
                        <span class="brand-tagline">Your Health Partner</span>
                    </div>
                </a>

                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon">
                        <i class="fas fa-bars"></i>
                    </span>
                </button>

                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav mx-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="/modules/frontend/index.html" data-page="home">
                                <i class="fas fa-home"></i>
                                <span>Home</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/modules/shop/search.html" data-page="medicines">
                                <i class="fas fa-capsules"></i>
                                <span>Medicines</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/modules/frontend/about.html" data-page="about">
                                <i class="fas fa-info-circle"></i>
                                <span>About Us</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/modules/shop/track-order.html" data-page="track">
                                <i class="fas fa-truck-medical"></i>
                                <span>Track Order</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/modules/frontend/contact.html" data-page="contact">
                                <i class="fas fa-phone-alt"></i>
                                <span>Contact</span>
                            </a>
                        </li>
                    </ul>

                    <div class="navbar-actions">
                        <a class="btn btn-cart" href="/modules/shop/cart.html">
                            <i class="fas fa-shopping-cart"></i>
                            <span class="cart-text">Cart</span>
                            <span class="cart-badge" id="nav-cart-count">0</span>
                        </a>
                        <a class="btn btn-login" href="/modules/auth/login.html">
                            <i class="fas fa-user-md"></i>
                            <span>Login</span>
                        </a>
                    </div>
                </div>
            </div>
        </nav>
        `;

        // Insert navbar at the beginning of body
        document.body.insertAdjacentHTML('afterbegin', navbarHTML);
    }

    setupScrollEffect() {
        window.addEventListener('scroll', () => {
            const navbar = document.getElementById('mainNavbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });
    }

    updateCartCount() {
        try {
            const cart = JSON.parse(localStorage.getItem('cart') || '[]');
            this.cartCount = cart.reduce((sum, item) => sum + (item.quantity || 1), 0);
            const badge = document.getElementById('nav-cart-count');
            if (badge) {
                badge.textContent = this.cartCount;
                badge.style.display = this.cartCount > 0 ? 'flex' : 'none';
            }
        } catch (e) {
            console.error('Error updating cart count:', e);
        }
    }

    setActiveLink() {
        const currentPath = window.location.pathname;
        const navLinks = document.querySelectorAll('.nav-link[data-page]');

        navLinks.forEach(link => {
            const href = link.getAttribute('href');
            if (currentPath.includes(href) ||
                (currentPath.includes('index.html') && link.dataset.page === 'home') ||
                (currentPath.includes('about.html') && link.dataset.page === 'about')) {
                link.classList.add('active');
            }
        });
    }
}

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.frontendNavbar = new FrontendNavbar();
});

// Listen for cart updates
window.addEventListener('storage', (e) => {
    if (e.key === 'cart' && window.frontendNavbar) {
        window.frontendNavbar.updateCartCount();
    }
});
