<?php
// Resolve current page and user role dynamically
$role = $_SESSION['role'] ?? 'DataEntry';
$current_page = basename($_SERVER['PHP_SELF']);
?>
<style>
    /* Centralized Sidebar Variables and Styles */
    :root {
        --sidebar-width: 260px;
    }
    
    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        bottom: 0;
        width: var(--sidebar-width);
        background: linear-gradient(180deg, #1a5c2e, #113f1f);
        color: white;
        z-index: 1050;
        transition: transform 0.3s ease-in-out;
        overflow-y: auto;
        box-shadow: 0 0 20px rgba(0, 0, 0, 0.15);
        transform: translateX(-100%);
    }
    
    .sidebar.active {
        transform: translateX(0);
    }
    
    .sidebar-header {
        padding: 14px 16px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.12);
        background: rgba(0, 0, 0, 0.18);
        position: relative;
    }
    
    .sidebar-close {
        position: absolute;
        top: 50%;
        right: 12px;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: white;
        font-size: 1.1rem;
        cursor: pointer;
        display: none;
        padding: 4px 8px;
        border-radius: 4px;
        transition: background 0.3s;
        z-index: 10;
    }
    
    .sidebar-close:hover {
        background: rgba(255, 255, 255, 0.15);
    }
    
    .sidebar-brand {
        display: flex;
        align-items: center;
        gap: 12px;
        text-align: left;
    }
    
    .sidebar-logo {
        width: 42px;
        height: 42px;
        min-width: 42px;
        margin: 0;
        background: #ffffff;
        border-radius: 50%;
        padding: 5px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.25);
        flex-shrink: 0;
    }
    
    .sidebar-logo img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }
    
    .sidebar-brand-text {
        display: flex;
        flex-direction: column;
        justify-content: center;
        overflow: hidden;
    }
    
    .sidebar-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: #ffffff;
        letter-spacing: 0.6px;
        line-height: 1.15;
        margin-bottom: 2px;
    }
    
    .sidebar-subtitle {
        font-size: 0.72rem;
        color: rgba(255, 255, 255, 0.85);
        letter-spacing: 0.2px;
        line-height: 1.2;
        white-space: nowrap;
        font-weight: 500;
    }
    
    .sidebar-menu {
        padding: 10px 0;
    }
    
    .sidebar-menu ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .sidebar-menu li {
        margin: 2px 12px;
    }
    
    .sidebar-menu a {
        display: flex;
        align-items: center;
        padding: 10px 12px;
        color: rgba(255, 255, 255, 0.95);
        text-decoration: none;
        border-radius: 6px;
        transition: all 0.3s ease;
        font-size: 0.9rem;
    }
    
    .sidebar-menu a:hover,
    .sidebar-menu a.active {
        background: rgba(255, 255, 255, 0.15);
        color: white;
    }
    
    .sidebar-menu a.active {
        background: linear-gradient(90deg, rgba(212, 175, 55, 0.25), transparent);
    }
    
    .sidebar-menu i {
        width: 18px;
        margin-right: 10px;
        font-size: 1rem;
        text-align: center;
    }
    
    .sidebar-menu span {
        flex: 1;
    }
    
    /* Overlay for mobile */
    .sidebar-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1040;
        backdrop-filter: blur(2px);
    }
    
    .sidebar-overlay.active {
        display: block;
    }
    
    /* Sidebar Layout Media Queries */
    @media (min-width: 992px) {
        .sidebar {
            transform: translateX(0);
            width: var(--sidebar-width);
        }
        
        .main-content {
            margin-left: var(--sidebar-width);
        }
        
        .mobile-menu-btn,
        .sidebar-close,
        .sidebar-overlay {
            display: none !important;
        }
    }
    
    @media (max-width: 991px) {
        .mobile-menu-btn {
            display: flex;
        }
        
        .sidebar-close {
            display: block;
        }
        
        .sidebar {
            width: 250px;
        }
    }
    
    /* Force standard text inputs to uppercase, while strictly preserving passwords, emails, and special fields */
    input[type="text"]:not(.no-uppercase):not([data-no-uppercase="true"]):not([data-password-field="true"]):not([name*="password"]):not([id*="password"]):not([name*="email"]):not([id*="email"]):not([name*="search"]):not([id*="search"]), 
    textarea:not(.no-uppercase):not([data-no-uppercase="true"]), 
    select, 
    option, 
    td, 
    .table td, 
    .table th,
    .visa-text {
        text-transform: uppercase !important;
    }

    /* Never transform password, email, or search fields */
    input[type="password"],
    input[type="email"],
    input.no-uppercase,
    input[data-no-uppercase="true"],
    input[data-password-field="true"],
    input[name*="password" i],
    input[id*="password" i],
    input[name*="email" i],
    input[id*="email" i],
    input[name*="search" i],
    input[id*="search" i],
    .password-field-wrapper input {
        text-transform: none !important;
    }

    /* ==========================================================================
       Master Uniform, Clear & Bold Profile Avatar Styling (System-Wide)
       ========================================================================== */
    .user-avatar {
        width: 48px !important;
        height: 48px !important;
        min-width: 48px !important;
        max-width: 48px !important;
        border-radius: 50% !important;
        background: #ffffff !important;
        border: 2.5px solid #1a5c2e !important;
        color: #1a5c2e !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        font-weight: 800 !important;
        font-size: 1.15rem !important;
        box-shadow: 0 0 0 1px #ffffff, 0 3px 8px rgba(26, 92, 46, 0.35) !important;
        overflow: hidden !important;
        flex-shrink: 0 !important;
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease !important;
        position: relative !important;
    }

    .user-avatar:hover {
        transform: scale(1.06) !important;
        border-color: #27ae60 !important;
        box-shadow: 0 0 0 2px #ffffff, 0 5px 14px rgba(26, 92, 46, 0.45) !important;
    }

    .user-avatar img {
        width: 100% !important;
        height: 100% !important;
        min-width: 100% !important;
        min-height: 100% !important;
        object-fit: cover !important;
        object-position: center top !important;
        display: block !important;
        image-rendering: -webkit-optimize-contrast !important;
        image-rendering: crisp-edges !important;
        filter: contrast(1.08) saturate(1.06) brightness(1.02) !important;
    }

    .avatar-circle {
        width: 48px !important;
        height: 48px !important;
        min-width: 48px !important;
        max-width: 48px !important;
        border-radius: 50% !important;
        border: 2.5px solid #1a5c2e !important;
        background: #ffffff !important;
        box-shadow: 0 0 0 1px #ffffff, 0 2px 8px rgba(26, 92, 46, 0.3) !important;
        overflow: hidden !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        font-weight: 800 !important;
        font-size: 1.1rem !important;
        color: #1a5c2e !important;
        flex-shrink: 0 !important;
    }

    .avatar-circle img {
        width: 100% !important;
        height: 100% !important;
        object-fit: cover !important;
        object-position: center top !important;
        display: block !important;
        image-rendering: -webkit-optimize-contrast !important;
        filter: contrast(1.08) saturate(1.06) brightness(1.02) !important;
    }

    .photo-preview-box {
        width: 68px !important;
        height: 68px !important;
        min-width: 68px !important;
        border-radius: 50% !important;
        border: 3px solid #1a5c2e !important;
        background: #ffffff !important;
        box-shadow: 0 3px 10px rgba(26, 92, 46, 0.3) !important;
        overflow: hidden !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        flex-shrink: 0 !important;
    }

    .photo-preview-box img {
        width: 100% !important;
        height: 100% !important;
        object-fit: cover !important;
        object-position: center top !important;
        display: block !important;
        image-rendering: -webkit-optimize-contrast !important;
        filter: contrast(1.08) saturate(1.06) brightness(1.02) !important;
    }

    .profile-avatar, .user-avatar-large, .profile-avatar-outer {
        border: 3.5px solid #1a5c2e !important;
        box-shadow: 0 4px 15px rgba(26, 92, 46, 0.3) !important;
        overflow: hidden !important;
        background: #ffffff !important;
    }

    .profile-avatar img, .user-avatar-large img {
        width: 100% !important;
        height: 100% !important;
        object-fit: cover !important;
        object-position: center top !important;
        display: block !important;
        image-rendering: -webkit-optimize-contrast !important;
        filter: contrast(1.08) saturate(1.06) brightness(1.02) !important;
    }
</style>

<!-- Sidebar HTML Structure -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <button class="sidebar-close" id="sidebarClose" aria-label="Close Sidebar">
            <i class="fa fa-times"></i>
        </button>
        <div class="sidebar-brand">
            <div class="sidebar-logo">
                <img src="assets/images/logo.png" alt="NIS Logo">
            </div>
            <div class="sidebar-brand-text">
                <div class="sidebar-title">NISREV</div>
                <div class="sidebar-subtitle">Re-Entry Visa Portal</div>
            </div>
        </div>
    </div>
    
    <div class="sidebar-menu">
        <ul>
            <?php if ($role === 'Admin' || $role === 'Supervisor'): ?>
                <li><a href="index" class="<?php echo ($current_page === 'index.php') ? 'active' : ''; ?>"><i class="fa fa-dashboard"></i><span>Dashboard</span></a></li>
                <li><a href="new-record" class="<?php echo ($current_page === 'new-record.php') ? 'active' : ''; ?>"><i class="fa fa-plus"></i><span>New Re-Entry Visa</span></a></li>
                <li><a href="re-entry-visa" class="<?php echo ($current_page === 're-entry-visa.php') ? 'active' : ''; ?>"><i class="fa fa-list"></i><span>View Re-Entry Visas</span></a></li>
                <li><a href="print_visas" class="<?php echo ($current_page === 'print_visas.php') ? 'active' : ''; ?>"><i class="fa fa-print"></i><span>Print Re-Entry Visa</span></a></li>
                <li><a href="globalsearch" class="<?php echo ($current_page === 'globalsearch.php') ? 'active' : ''; ?>"><i class="fa fa-globe"></i><span>Global Search</span></a></li>
                <li><a href="report" class="<?php echo ($current_page === 'report.php') ? 'active' : ''; ?>"><i class="fa fa-bar-chart"></i><span>Reports</span></a></li>
                <li><a href="createuser" class="<?php echo ($current_page === 'createuser.php') ? 'active' : ''; ?>"><i class="fa fa-users"></i><span>User Management</span></a></li>
            <?php elseif ($role === 'ViewOnly' || strcasecmp($role, 'viewonly') === 0 || strcasecmp($role, 'view only') === 0): ?>
                <li><a href="index" class="<?php echo ($current_page === 'index.php') ? 'active' : ''; ?>"><i class="fa fa-dashboard"></i><span>Dashboard</span></a></li>
                <li><a href="re-entry-visa" class="<?php echo ($current_page === 're-entry-visa.php') ? 'active' : ''; ?>"><i class="fa fa-list"></i><span>View Re-Entry Visas</span></a></li>
                <li><a href="globalsearch" class="<?php echo ($current_page === 'globalsearch.php') ? 'active' : ''; ?>"><i class="fa fa-globe"></i><span>Global Search</span></a></li>
                <li><a href="report" class="<?php echo ($current_page === 'report.php') ? 'active' : ''; ?>"><i class="fa fa-bar-chart"></i><span>Reports</span></a></li>
            <?php else: ?>
                <li><a href="user_dashboard" class="<?php echo ($current_page === 'user_dashboard.php' || $current_page === 'index.php') ? 'active' : ''; ?>"><i class="fa fa-dashboard"></i><span>My Dashboard</span></a></li>
                <li><a href="new-record" class="<?php echo ($current_page === 'new-record.php') ? 'active' : ''; ?>"><i class="fa fa-plus"></i><span>New Re-Entry Visa</span></a></li>
                <li><a href="re-entry-visa" class="<?php echo ($current_page === 're-entry-visa.php') ? 'active' : ''; ?>"><i class="fa fa-list"></i><span>View My Visas</span></a></li>
                <li><a href="print_visas" class="<?php echo ($current_page === 'print_visas.php') ? 'active' : ''; ?>"><i class="fa fa-print"></i><span>Print Re-Entry Visa</span></a></li>
                <li><a href="globalsearch" class="<?php echo ($current_page === 'globalsearch.php') ? 'active' : ''; ?>"><i class="fa fa-globe"></i><span>Global Search</span></a></li>
            <?php endif; ?>
            <li><a href="profile" class="<?php echo ($current_page === 'profile.php') ? 'active' : ''; ?>"><i class="fa fa-user"></i><span>My Profile</span></a></li>
        </ul>
    </div>
</div>

<!-- Self-Contained Sidebar Toggle Scripts -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const sidebar = document.getElementById('sidebar');
    const sidebarClose = document.getElementById('sidebarClose');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    
    if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            sidebar.classList.add('active');
            sidebarOverlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        });
    }
    
    if (sidebarClose) {
        sidebarClose.addEventListener('click', function() {
            sidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
            document.body.style.overflow = '';
        });
    }
    
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function() {
            sidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
            document.body.style.overflow = '';
        });
    }
    
    document.addEventListener('click', function(event) {
        if (window.innerWidth < 992) {
            if (sidebar && sidebar.classList.contains('active') && 
                !sidebar.contains(event.target) && 
                mobileMenuBtn && !mobileMenuBtn.contains(event.target)) {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        }
    });
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar && sidebar.classList.contains('active')) {
            sidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
            document.body.style.overflow = '';
        }
    });
    
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (window.innerWidth >= 992 && sidebar) {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        }, 250);
    });
});

// Prompt logout confirmation if any logout link is clicked
document.querySelectorAll('a[href*="logout"]').forEach(link => {
    link.addEventListener('click', function(e) {
        if (!confirm('Are you sure you want to logout?')) {
            e.preventDefault();
        }
    });
});

// Automatically convert appropriate text inputs to uppercase in real-time as the user types
document.addEventListener('input', function(event) {
    if (!event.target || event.target.tagName !== 'INPUT') return;
    const input = event.target;
    
    // Only process text or search inputs
    if (input.type !== 'text' && input.type !== 'search') return;
    
    const name = (input.name || '').toLowerCase();
    const id = (input.id || '').toLowerCase();
    
    // Strictly exclude password fields (even when toggled to type="text"), emails, search filters, and no-uppercase fields
    if (
        input.getAttribute('data-no-uppercase') === 'true' ||
        input.getAttribute('data-password-field') === 'true' ||
        input.classList.contains('no-uppercase') ||
        name.indexOf('password') !== -1 ||
        id.indexOf('password') !== -1 ||
        name.indexOf('pass') !== -1 ||
        id.indexOf('pass') !== -1 ||
        name.indexOf('email') !== -1 ||
        id.indexOf('email') !== -1 ||
        name.indexOf('search') !== -1 ||
        id.indexOf('search') !== -1
    ) {
        return;
    }
    
    input.value = input.value.toUpperCase();
}, true);
</script>

<!-- Offline Mode & Sync Integration -->
<script src="offline-sync.js"></script>
