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
        padding: 20px 15px;
        text-align: center;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        background: rgba(0, 0, 0, 0.2);
        position: relative;
    }
    
    .sidebar-close {
        position: absolute;
        top: 12px;
        right: 12px;
        background: none;
        border: none;
        color: white;
        font-size: 1.1rem;
        cursor: pointer;
        display: none;
        padding: 4px;
        border-radius: 4px;
        transition: background 0.3s;
    }
    
    .sidebar-close:hover {
        background: rgba(255, 255, 255, 0.1);
    }
    
    .sidebar-logo {
        width: 48px;
        height: 48px;
        margin: 0 auto 12px;
        background: white;
        border-radius: 50%;
        padding: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .sidebar-logo img {
        width: 100%;
        height: auto;
        object-fit: contain;
    }
    
    .sidebar-title {
        font-size: 1.05rem;
        font-weight: 600;
        margin-bottom: 3px;
        color: white;
    }
    
    .sidebar-subtitle {
        font-size: 0.75rem;
        opacity: 0.8;
        color: rgba(255, 255, 255, 0.9);
    }
    
    .sidebar-menu {
        padding: 15px 0;
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
</style>

<!-- Sidebar HTML Structure -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <button class="sidebar-close" id="sidebarClose">
            <i class="fa fa-times"></i>
        </button>
        <div class="sidebar-logo">
            <img src="assets/images/logo.png" alt="NIS Logo">
        </div>
        <div class="sidebar-title">NISREV</div>
        <div class="sidebar-subtitle">Re-Entry Visa Portal</div>
    </div>
    
    <div class="sidebar-menu">
        <ul>
            <?php if ($role === 'Admin'): ?>
                <li><a href="index" class="<?php echo ($current_page === 'index.php') ? 'active' : ''; ?>"><i class="fa fa-dashboard"></i><span>Dashboard</span></a></li>
                <li><a href="new-record" class="<?php echo ($current_page === 'new-record.php') ? 'active' : ''; ?>"><i class="fa fa-plus"></i><span>New Re-Entry Visa</span></a></li>
                <li><a href="re-entry-visa" class="<?php echo ($current_page === 're-entry-visa.php') ? 'active' : ''; ?>"><i class="fa fa-list"></i><span>View Re-Entry Visas</span></a></li>
                <li><a href="print_visas" class="<?php echo ($current_page === 'print_visas.php') ? 'active' : ''; ?>"><i class="fa fa-print"></i><span>Print Re-Entry Visa</span></a></li>
                <li><a href="report" class="<?php echo ($current_page === 'report.php') ? 'active' : ''; ?>"><i class="fa fa-bar-chart"></i><span>Reports</span></a></li>
                <li><a href="createuser" class="<?php echo ($current_page === 'createuser.php') ? 'active' : ''; ?>"><i class="fa fa-users"></i><span>User Management</span></a></li>
                
            <?php elseif ($role === 'Supervisor'): ?>
                <li><a href="index" class="<?php echo ($current_page === 'index.php') ? 'active' : ''; ?>"><i class="fa fa-dashboard"></i><span>Dashboard</span></a></li>
                <li><a href="new-record" class="<?php echo ($current_page === 'new-record.php') ? 'active' : ''; ?>"><i class="fa fa-plus"></i><span>New Re-Entry Visa</span></a></li>
                <li><a href="re-entry-visa" class="<?php echo ($current_page === 're-entry-visa.php') ? 'active' : ''; ?>"><i class="fa fa-list"></i><span>View Re-Entry Visas</span></a></li>
                <li><a href="print_visas" class="<?php echo ($current_page === 'print_visas.php') ? 'active' : ''; ?>"><i class="fa fa-print"></i><span>Print Re-Entry Visa</span></a></li>
                <li><a href="report" class="<?php echo ($current_page === 'report.php') ? 'active' : ''; ?>"><i class="fa fa-bar-chart"></i><span>Reports</span></a></li>
                
            <?php else: ?>
                <li><a href="user_dashboard" class="<?php echo ($current_page === 'user_dashboard.php' || $current_page === 'index.php') ? 'active' : ''; ?>"><i class="fa fa-dashboard"></i><span>My Dashboard</span></a></li>
                <li><a href="new-record" class="<?php echo ($current_page === 'new-record.php') ? 'active' : ''; ?>"><i class="fa fa-plus"></i><span>New Re-Entry Visa</span></a></li>
                <li><a href="re-entry-visa" class="<?php echo ($current_page === 're-entry-visa.php') ? 'active' : ''; ?>"><i class="fa fa-list"></i><span>View My Visas</span></a></li>
                <li><a href="print_visas" class="<?php echo ($current_page === 'print_visas.php') ? 'active' : ''; ?>"><i class="fa fa-print"></i><span>Print Re-Entry Visa</span></a></li>
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
</script>

<!-- Offline Mode & Sync Integration -->
<script src="offline-sync.js"></script>
