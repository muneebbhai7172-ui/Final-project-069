<?php
// main-sidebar.php - Advanced Toggle Collapsible Sidebar for Main Website
?>

<!-- Sidebar Overlay for Mobile -->
<div class="sidebar-overlay" id="sidebar-overlay"></div>

<!-- Advanced Main Website Sidebar -->
<div class="main-sidebar" id="main-sidebar">
    <!-- Toggle Button -->
    <div class="sidebar-toggle-btn" id="sidebar-toggle-btn">
        <i class="fas fa-chevron-left" id="toggle-icon"></i>
    </div>
    
    <!-- Brand Section -->
    <div class="sidebar-brand">
        <div class="brand-icon">
            <i class="fas fa-pills"></i>
        </div>
        <div class="brand-text">
            <h4 class="brand-title">PharmaCare</h4>
            <small class="brand-subtitle">Online Pharmacy</small>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
        <div class="quick-action-item">
            <div class="action-icon">
                <i class="fas fa-search"></i>
            </div>
            <div class="action-text">
                <span class="action-title">Quick Search</span>
                <small class="action-subtitle">Find medicines</small>
            </div>
        </div>
    </div>

    <!-- Navigation Menu -->
    <nav class="sidebar-nav">
        <ul class="nav-list">
            <li class="nav-item">
                <a href="../frontend/index.html" class="nav-link" data-page="index" title="Home">
                    <div class="nav-icon">
                        <i class="fas fa-home"></i>
                    </div>
                    <span class="nav-text">Home</span>
                    <div class="nav-indicator"></div>
                </a>
            </li>
            <li class="nav-item">
                <a href="../shop/search.html" class="nav-link" data-page="search" title="Search Medicines">
                    <div class="nav-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <span class="nav-text">Search Medicines</span>
                    <div class="nav-indicator"></div>
                </a>
            </li>
            <li class="nav-item">
                <a href="../shop/cart.html" class="nav-link" data-page="cart" title="Shopping Cart">
                    <div class="nav-icon">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="cart-badge" id="cart-count">0</span>
                    </div>
                    <span class="nav-text">Shopping Cart</span>
                    <div class="nav-indicator"></div>
                </a>
            </li>
            <li class="nav-item">
                <a href="../frontend/about.html" class="nav-link" data-page="about" title="About Us">
                    <div class="nav-icon">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <span class="nav-text">About Us</span>
                    <div class="nav-indicator"></div>
                </a>
            </li>
            <li class="nav-divider">
                <span class="divider-text">Account</span>
            </li>
            <li class="nav-item">
                <a href="../auth/login.html" class="nav-link" data-page="login" title="Login">
                    <div class="nav-icon">
                        <i class="fas fa-sign-in-alt"></i>
                    </div>
                    <span class="nav-text">Login</span>
                    <div class="nav-indicator"></div>
                </a>
            </li>
            <li class="nav-item">
                <a href="../auth/register.html" class="nav-link" data-page="register" title="Register">
                    <div class="nav-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <span class="nav-text">Register</span>
                    <div class="nav-indicator"></div>
                </a>
            </li>
        </ul>
    </nav>

    <!-- Contact Info -->
    <div class="contact-info">
        <div class="contact-item">
            <i class="fas fa-phone"></i>
            <span class="contact-text">+1 234 567 890</span>
        </div>
        <div class="contact-item">
            <i class="fas fa-envelope"></i>
            <span class="contact-text">info@pharmacare.com</span>
        </div>
        <div class="contact-item">
            <i class="fas fa-clock"></i>
            <span class="contact-text">24/7 Service</span>
        </div>
    </div>

    <!-- Emergency Button -->
    <div class="emergency-section">
        <button class="btn btn-emergency" onclick="emergency()">
            <i class="fas fa-phone-alt me-2"></i>
            <span class="btn-text">Emergency Call</span>
        </button>
    </div>
</div>