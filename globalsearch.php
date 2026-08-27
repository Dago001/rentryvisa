<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once('security.php');
require_once('db/db.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index");
    exit();
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'DataEntry';
$username = $_SESSION['username'] ?? 'User';
$fullName = $_SESSION['full_name'] ?? $username;

// Get current page for active menu
$current_page = basename($_SERVER['PHP_SELF']);

// Check database connection
if (!$conn) {
    die("<div class='alert alert-danger'>Database connection failed</div>");
}

// Handle search query
$searchTerm = isset($_GET['q']) ? trim($_GET['q']) : '';
$num_results = 0;
$results = null;

if (!empty($searchTerm)) {
    // Audit log: Log the global search action
    logActivity($conn, "SEARCH", "Performed global database search for query: '" . $searchTerm . "'");

    // Perform global prepared statement search on passport number, visa number, surname, or given name
    $search_wildcard = '%' . $searchTerm . '%';
    $search_query = "SELECT * FROM visa_applications WHERE passport_number LIKE ? OR visa_number LIKE ? OR surname LIKE ? OR given_name LIKE ? ORDER BY application_date DESC";
    $stmt = mysqli_prepare($conn, $search_query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ssss", $search_wildcard, $search_wildcard, $search_wildcard, $search_wildcard);
        mysqli_stmt_execute($stmt);
        $results = mysqli_stmt_get_result($stmt);
        $num_results = mysqli_num_rows($results);
        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>NISREV - Global Search</title>
    <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
    <!-- Bootstrap CSS -->
    <link href="vendors/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="vendors/font-awesome/css/font-awesome.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary: #1a5c2e;
            --secondary: #27ae60;
            --dark: #2c3e50;
            --light: #f8f9fa;
            --warning: #f39c12;
            --info: #3498db;
            --danger: #dc3545;
            --gray: #6c757d;
            --sidebar-width: 260px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html {
            font-size: 14px;
            height: 100%;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            background: #f5f7fa;
            color: var(--dark);
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        /* Main Content */
        .main-content {
            min-height: 100vh;
            transition: all 0.3s ease;
        }
        
        /* Top Navigation */
        .top-nav {
            background: white;
            padding: 12px 20px;
            border-bottom: 1px solid #e1e5e9;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1030;
        }
        
        .nav-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .mobile-menu-btn {
            background: none;
            border: none;
            color: var(--dark);
            font-size: 1.3rem;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 4px;
            transition: background 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .mobile-menu-btn:hover {
            background: rgba(0, 0, 0, 0.05);
        }
        
        .nav-left h4 {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark);
            margin: 0;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .user-avatar {
            width: 48px;
            height: 48px;
            min-width: 48px;
            max-width: 48px;
            border-radius: 50%;
            background: #ffffff;
            border: 2.5px solid #1a5c2e;
            color: #1a5c2e;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1.15rem;
            box-shadow: 0 0 0 1px #ffffff, 0 3px 8px rgba(26, 92, 46, 0.35);
            overflow: hidden;
            flex-shrink: 0;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .user-avatar:hover {
            transform: scale(1.06);
            border-color: #27ae60;
            box-shadow: 0 0 0 2px #ffffff, 0 5px 14px rgba(26, 92, 46, 0.45);
        }
        
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center top;
            display: block;
            image-rendering: -webkit-optimize-contrast;
            image-rendering: crisp-edges;
            filter: contrast(1.08) saturate(1.06) brightness(1.02);
        }
        
        .user-details {
            display: flex;
            flex-direction: column;
        }
        
        .user-name {
            font-weight: 600;
            color: var(--dark);
            font-size: 0.9rem;
        }
        
        .user-role {
            font-size: 0.75rem;
            color: var(--gray);
        }
        
        .logout-btn {
            background: transparent;
            border: 2px solid var(--danger);
            color: var(--danger);
            padding: 5px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
        }
        
        .logout-btn:hover {
            background: var(--danger);
            color: white;
            text-decoration: none;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
        }
        
        .page-content {
            padding: 30px;
        }
        
        /* Card Layout */
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            border: none;
            margin-bottom: 30px;
        }
        
        .card-header {
            background: transparent;
            border-bottom: 1px solid #eef2f5;
            padding: 20px 25px;
        }
        
        .card-title {
            margin: 0;
            font-weight: 700;
            color: var(--dark);
            font-size: 1.15rem;
        }
        
        .card-body {
            padding: 25px;
        }
        
        /* Search Box Layout */
        .search-form-container {
            max-width: 750px;
            margin: 0 auto;
        }
        
        .search-input-group {
            position: relative;
            display: flex;
            gap: 10px;
        }
        
        .search-input-group input {
            height: 48px;
            border-radius: 8px;
            border: 1px solid #dcdcdc;
            padding: 10px 16px 10px 42px;
            font-size: 1rem;
            flex-grow: 1;
            transition: all 0.2s ease;
        }
        
        .search-input-group input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26, 92, 46, 0.15);
            outline: none;
        }
        
        .search-input-group i.search-icon {
            position: absolute;
            left: 15px;
            top: 16px;
            color: var(--gray);
            font-size: 1.1rem;
        }
        
        .btn-search-go {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0 25px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            height: 48px;
            transition: all 0.2s ease;
            box-shadow: 0 4px 10px rgba(26, 92, 46, 0.15);
        }
        
        .btn-search-go:hover {
            background: #113f1f;
            color: white;
            box-shadow: 0 6px 14px rgba(26, 92, 46, 0.25);
        }
        
        /* Action Buttons */
        .btn-view {
            background: linear-gradient(135deg, #1e293b, #334155);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            min-width: 80px;
        }
        
        .btn-view:hover {
            background: linear-gradient(135deg, #2e7d46, var(--primary));
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(26, 92, 46, 0.3);
        }
        
        .btn-edit {
            background: linear-gradient(135deg, var(--warning), #e67e22);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            min-width: 80px;
        }
        
        .btn-edit:hover {
            background: linear-gradient(135deg, #e67e22, var(--warning));
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(243, 156, 18, 0.3);
        }
        
        .btn-group {
            display: flex;
            gap: 6px;
        }
        
        /* Table Styles */
        .table {
            margin-bottom: 0;
        }
        
        .table th {
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: #495057;
        }
        
        /* Footer Styles */
        .main-footer {
            background: transparent;
            color: var(--gray);
            padding: 15px 20px;
            text-align: right;
            border-top: 1px solid #e1e5e9;
            font-size: 0.8rem;
            margin-top: 30px;
        }
        
        .main-footer a {
            color: var(--secondary);
            text-decoration: none;
            font-weight: 500;
        }
        
        .main-footer a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 991px) {
            .main-content {
                margin-left: 0;
            }
        }
        
        @media (max-width: 768px) {
            .page-content {
                padding: 15px;
            }
            
            .top-nav {
                padding: 10px 15px;
            }
            
            .user-info {
                flex-wrap: wrap;
            }
            
            .card {
                margin-bottom: 15px;
            }
            
            .card-body {
                padding: 15px;
            }
        }
        
        @media (max-width: 576px) {
            .top-nav {
                flex-direction: column;
                gap: 12px;
                align-items: stretch;
            }
            
            .nav-left {
                justify-content: space-between;
            }
            
            .user-info {
                padding-top: 12px;
                border-top: 1px solid #e1e5e9;
                flex-direction: row;
                justify-content: space-between;
            }
            
            .page-content {
                padding: 12px;
            }
            
            .search-input-group {
                flex-direction: column;
                gap: 12px;
            }
            
            .search-input-group input {
                width: 100%;
                padding-left: 42px;
            }
            
            .btn-search-go {
                width: 100%;
                justify-content: center;
            }
        }
        
        @media (max-width: 360px) {
            .page-content {
                padding: 10px;
            }
            
            .user-info {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
            
            .logout-btn {
                width: 100%;
                justify-content: center;
            }
            
            .table {
                font-size: 0.85rem;
            }
            
            .table th,
            .table td {
                padding: 8px !important;
            }
            
            .btn-group {
                flex-direction: column;
                gap: 4px;
            }
            
            .btn-view, .btn-edit {
                width: 100%;
                min-width: unset;
            }
        }
    
        /* Welcome Banner Cream/Off-White Theme Override */
        .welcome-banner {
            background: #fdfbf7 !important;
            color: #5c3c1e !important;
            border: 1px solid #e2dcd0 !important;
            box-shadow: 0 4px 15px rgba(92, 60, 30, 0.05) !important;
        }
        .welcome-banner h2, .welcome-text h2, #currentDateTime {
            color: #5c3c1e !important;
        }
        .welcome-banner p, .welcome-text p, .location {
            color: #7c6e60 !important;
            opacity: 1 !important;
        }
</style>
</head>
<body>
    <!-- Sidebar navigation component -->
    <?php include('sidebar.php'); ?>
    
    <div class="main-content flex-grow-1">
        <!-- Top Navigation -->
        <nav class="top-nav">
            <div class="nav-left">
                <button class="mobile-menu-btn" id="mobileMenuBtn">
                    <i class="fa fa-bars"></i>
                    <span class="d-none d-sm-inline">Menu</span>
                </button>
                <h4>Global Search</h4>
            </div>
            
            <div class="user-info">
                <div class="user-avatar" style="overflow: hidden; display: flex; align-items: center; justify-content: center;">
                    <?php 
                    $session_photo = $_SESSION['photo'] ?? '';
                    $user_photo_path = '';
                    if (!empty($session_photo)) {
                        if (strpos($session_photo, 'uploads/') === 0) {
                            $user_photo_path = $session_photo;
                        } else {
                            $user_photo_path = 'uploads/' . $session_photo;
                        }
                    }
                    if (!empty($user_photo_path) && file_exists($user_photo_path)): 
                    ?>
                        <img src="<?php echo htmlspecialchars($user_photo_path); ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <?php 
                            $initials = isset($_SESSION['username']) 
                                ? strtoupper(substr($_SESSION['username'], 0, 1)) 
                                : 'U';
                            echo htmlspecialchars($initials);
                        ?>
                    <?php endif; ?>
                </div>
                <div class="user-details">
                    <div class="user-name">
                        <?php 
                            echo isset($_SESSION['full_name']) 
                                ? htmlspecialchars($_SESSION['full_name'])
                                : (isset($_SESSION['username']) 
                                    ? htmlspecialchars($_SESSION['username']) 
                                    : 'User');
                        ?>
                    </div>
                    <div class="user-role">
                        <?php echo isset($_SESSION['role']) ? htmlspecialchars($_SESSION['role']) : 'User'; ?>
                    </div>
                </div>
                <a href="logout" class="logout-btn">
                    <i class="fa fa-sign-out"></i>
                    <span>Logout</span>
                </a>
            </div>
        </nav>

        <div class="page-content">
            <!-- Search Query Box -->
            <div class="card">
                <div class="card-body">
                    <div class="search-form-container">
                        <h4 class="text-center mb-4" style="color: var(--primary); font-weight: 600;">
                            <i class="fa fa-globe"></i> Global Applicant Search
                        </h4>
                        <form method="GET" action="globalsearch">
                            <div class="search-input-group">
                                <i class="fa fa-search search-icon"></i>
                                <input type="text" name="q" value="<?php echo htmlspecialchars($searchTerm); ?>" 
                                       placeholder="Enter Passport Number, Full Name, or Visa Number..." required>
                                <button type="submit" class="btn-search-go">
                                    <i class="fa fa-search me-1"></i> Search
                                </button>
                            </div>
                            <small class="text-muted d-block mt-2 text-center">
                                Searches globally across all visa applications inside the Portal.
                            </small>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Results Card -->
            <?php if (!empty($searchTerm)): ?>
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title">Search Results (<?php echo $num_results; ?> found)</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($num_results > 0 && $results): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover align-middle mb-0" style="font-size: 0.9rem;">
                                    <thead>
                                        <tr>
                                            <th style="padding-left: 20px;">Visa Number</th>
                                            <th>Applicant Name</th>
                                            <th>Passport Number</th>
                                            <th>Nationality</th>
                                            <th>Journey</th>
                                            <th>Validity Period</th>
                                            <th>Application Date</th>
                                            <th style="padding-right: 20px; text-align: right;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = mysqli_fetch_assoc($results)): ?>
                                            <tr>
                                                <td style="padding-left: 20px;"><strong><?php echo htmlspecialchars(strtoupper($row['visa_number'])); ?></strong></td>
                                                <td><?php echo htmlspecialchars(strtoupper(($row['surname'] ?? '') . ' ' . ($row['given_name'] ?? ''))); ?></td>
                                                <td><?php echo htmlspecialchars(strtoupper($row['passport_number'])); ?></td>
                                                <td><?php echo htmlspecialchars(strtoupper($row['nationality'] ?? 'N/A')); ?></td>
                                                <td>
                                                    <span class="badge bg-light text-dark border">
                                                        <?php echo htmlspecialchars(strtoupper($row['journey'] ?? 'N/A')); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $wVal = $row['within'] ?? ($row['validity_date'] ?? ($row['expiry_date'] ?? '')); 
                                                    echo !empty($wVal) ? formatNISDate($wVal) : 'N/A';
                                                    ?>
                                                </td>
                                                <td>
                                                    <span class="text-muted">
                                                        <?php echo date('d/m/Y', strtotime($row['application_date'])); ?>
                                                    </span>
                                                </td>
                                                <td style="padding-right: 20px; text-align: right;">
                                                    <div class="btn-group justify-content-end">
                                                        <a href="viewrecord?id=<?php echo urlencode($row['visa_number']); ?>&ref=globalsearch" 
                                                           class="btn btn-view" title="View Application Details">
                                                            <i class="fa fa-eye"></i> View
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fa fa-folder-open-o fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No Matching Records Found</h5>
                                <p class="text-muted mb-0">Double check spelling or try a different Passport Number.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <!-- Idle State: Prompt user to search -->
                <div class="card py-5">
                    <div class="card-body text-center">
                        <i class="fa fa-globe fa-4x text-muted mb-3" style="opacity: 0.5;"></i>
                        <h5 class="text-muted" style="font-weight: 600;">Global Lookup System</h5>
                        <p class="text-muted mx-auto" style="max-width: 450px;">
                            Enter a search query in the search box above to look up visa application records across the entire registry.
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Footer Section -->
        <?php 
        if (file_exists('footer.php')) {
            include('footer.php');
        }
        ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="vendors/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Handle Sidebar Toggle on Mobile
        document.addEventListener('DOMContentLoaded', function() {
            const toggle = document.getElementById('sidebarToggle');
            const sidebar = document.querySelector('.sidebar');
            if (toggle && sidebar) {
                toggle.addEventListener('click', function() {
                    sidebar.classList.toggle('active');
                });
            }
        });
    </script>
</body>
</html>
<?php
// Close database connection
mysqli_close($conn);
?>
