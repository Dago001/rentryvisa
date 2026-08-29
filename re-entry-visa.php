<?php
require_once('security.php');
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once('db/db.php');

// Check if user is logged in
if (!isset($_SESSION['user_id']) && !isset($_SESSION['username'])) {
    header("Location: index");
    exit();
}

$userRole = $_SESSION['role'] ?? 'DataEntry';
$isDataEntry = ($userRole === 'DataEntry' || strcasecmp($userRole, 'dataentry') === 0 || strcasecmp($userRole, 'data entry') === 0);
$username = $_SESSION['username'] ?? 'User';
$fullName = $_SESSION['full_name'] ?? $username;

// Get current page for active menu
$current_page = basename($_SERVER['PHP_SELF']);

// Check database connection
if (!$conn) {
    die("<div class='alert alert-danger'>Database connection failed: " . htmlspecialchars(mysqli_connect_error()) . "</div>");
}

// 1. Fetch Dynamic Statistics for Re-Entry Visas (Scoped to individual work for DataEntry)
$whereClause = $isDataEntry ? " WHERE created_by = '" . mysqli_real_escape_string($conn, $username) . "'" : "";

$stats_sql = "SELECT 
    COUNT(*) as total_records,
    COALESCE(SUM(CASE WHEN LOWER(application_type) = 'fresh' OR application_type IS NULL OR application_type = '' THEN 1 ELSE 0 END), 0) as total_fresh,
    COALESCE(SUM(CASE WHEN LOWER(application_type) = 'renewal' THEN 1 ELSE 0 END), 0) as total_renewal,
    COALESCE(SUM(CASE WHEN DATE(application_date) = CURDATE() OR (application_date IS NULL AND DATE(created_at) = CURDATE()) THEN 1 ELSE 0 END), 0) as total_today
FROM visa_applications" . $whereClause;

$stats_res = mysqli_query($conn, $stats_sql);
$stats_data = ($stats_res) ? mysqli_fetch_assoc($stats_res) : [];
$stats = [
    'total_records' => intval($stats_data['total_records'] ?? 0),
    'total_fresh' => intval($stats_data['total_fresh'] ?? 0),
    'total_renewal' => intval($stats_data['total_renewal'] ?? 0),
    'total_today' => intval($stats_data['total_today'] ?? 0)
];

// 2. Fetch all system users & officers who created records for the dropdown filter
$all_officers = [];
$users_q = mysqli_query($conn, "SELECT service_no, fname, lname, role FROM users ORDER BY fname ASC, lname ASC");
if ($users_q) {
    while ($u = mysqli_fetch_assoc($users_q)) {
        $sno = trim($u['service_no']);
        $name = trim($u['fname'] . ' ' . $u['lname']);
        if (empty($name)) $name = $sno;
        $all_officers[$sno] = [
            'value' => $sno,
            'label' => $name . ' (' . $sno . ')'
        ];
    }
}
$creators_q = mysqli_query($conn, "SELECT DISTINCT created_by FROM visa_applications WHERE created_by IS NOT NULL AND created_by != ''");
if ($creators_q) {
    while ($c = mysqli_fetch_assoc($creators_q)) {
        $cb = trim($c['created_by']);
        if (!empty($cb) && !isset($all_officers[$cb])) {
            $all_officers[$cb] = [
                'value' => $cb,
                'label' => $cb
            ];
        }
    }
}

// 3. Query all visa applications
$sqluse = "SELECT 
    v.id,
    v.visa_number,
    v.passport_number,
    v.surname,
    v.given_name,
    v.application_type,
    v.within,
    v.journey,
    v.created_by,
    v.created_at,
    v.application_date,
    u.fname,
    u.lname 
FROM visa_applications v 
LEFT JOIN users u ON v.created_by = u.service_no";

if ($isDataEntry) {
    $sqluse .= " WHERE v.created_by = '" . mysqli_real_escape_string($conn, $username) . "'";
}
$sqluse .= " ORDER BY v.application_date DESC, v.id DESC";
$retrieve = mysqli_query($conn, $sqluse);
$numb = $retrieve ? mysqli_num_rows($retrieve) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>NISREV - View Re-Entry Visas</title>
    <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
    <!-- Bootstrap CSS -->
    <link href="vendors/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="vendors/font-awesome/css/font-awesome.min.css">
    
    <style>
        :root {
            --primary: #1a5c2e;
            --primary-dark: #144824;
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
        }
        
        .page-content {
            padding: 20px;
            min-height: calc(100vh - 64px);
        }

        /* Welcome Banner */
        .welcome-banner {
            background: #fdfbf7 !important;
            color: #5c3c1e !important;
            border: 1px solid #e2dcd0 !important;
            box-shadow: 0 4px 15px rgba(92, 60, 30, 0.05) !important;
            padding: 18px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
        }
        
        .welcome-banner h2 {
            font-size: 1.45rem;
            font-weight: 700;
            margin-bottom: 6px;
            color: #5c3c1e !important;
        }
        
        .welcome-banner p {
            margin-bottom: 0;
            font-size: 0.88rem;
            color: #7c6e60 !important;
        }
        
        .datetime-display {
            text-align: right;
        }
        
        #currentDateTime {
            font-size: 1.05rem;
            font-weight: 700;
            margin-bottom: 3px;
            color: #5c3c1e !important;
        }
        
        .location {
            font-size: 0.82rem;
            color: #7c6e60 !important;
        }
        
        /* Stat Cards Section */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 16px;
            margin-bottom: 22px;
        }
        
        .stat-card {
            background: #ffffff;
            padding: 18px 14px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.06);
            text-align: center;
            border: 1px solid #e1e5e9;
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 120px;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            border-color: #cbd5e1;
        }
        
        .stat-card-icon {
            font-size: 1.75rem;
            margin-bottom: 8px;
            line-height: 1;
            display: inline-block;
        }
        
        .stat-number {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 1.65rem;
            font-weight: 600;
            margin: 4px 0;
            line-height: 1.1;
            letter-spacing: -0.3px;
        }
        
        .stat-label {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.4px;
            margin-top: 4px;
            line-height: 1.2;
        }

        .stat-card.card-total .stat-number { color: #1a5c2e; }
        .stat-card.card-fresh .stat-number { color: #16a34a; }
        .stat-card.card-renewal .stat-number { color: #d97706; }
        .stat-card.card-today .stat-number { color: #2563eb; }

        /* Card Container */
        .card {
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.06);
            border: 1px solid #e2e8f0;
            margin-bottom: 20px;
            background: white;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .card-title-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 12px;
        }
        
        .card-title {
            margin-bottom: 0;
            font-size: 1.18rem;
            font-weight: 700;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Custom Executive Vignette Toolbar (Fully Independent Responsive Flexbox) */
        .vignette-toolbar {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px 14px;
            margin-bottom: 20px;
            width: 100%;
        }

        .vignette-toolbar-grid {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
            width: 100%;
        }

        /* Individual Toolbar Field Boxes */
        .vignette-field {
            display: flex;
            align-items: stretch;
            background: #ffffff;
            border: 1.5px solid #cbd5e1;
            border-radius: 6px;
            height: 38px;
            overflow: hidden;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .vignette-field:focus-within {
            border-color: #1a5c2e;
            box-shadow: 0 0 0 3px rgba(26, 92, 46, 0.15);
        }

        /* Addon Labels & Icons */
        .vignette-field-addon {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #f1f5f9;
            color: #334155;
            font-size: 0.82rem;
            font-weight: 700;
            padding: 0 12px;
            border-right: 1px solid #cbd5e1;
            white-space: nowrap;
            user-select: none;
            flex-shrink: 0;
            line-height: 1;
        }

        /* Inputs & Selects inside Fields */
        .vignette-select,
        .vignette-input {
            border: none !important;
            outline: none !important;
            box-shadow: none !important;
            background: #ffffff !important;
            padding: 0 12px !important;
            height: 100% !important;
            font-size: 0.84rem !important;
            color: #1e293b !important;
            flex: 1 1 auto !important;
            min-width: 0 !important;
            line-height: normal !important;
        }

        .vignette-select {
            cursor: pointer;
        }

        /* Responsive proportions */
        .vignette-field-user {
            flex: 1 1 260px;
            min-width: 220px;
        }

        .vignette-field-status {
            flex: 1 1 180px;
            min-width: 160px;
        }

        .vignette-field-search {
            flex: 2 1 300px;
            min-width: 240px;
        }

        .vignette-field-reset {
            border: none !important;
            background: transparent !important;
            box-shadow: none !important;
            flex: 0 0 auto;
        }

        /* Reset Button */
        .vignette-btn-reset {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            height: 38px;
            padding: 0 16px;
            background: #ffffff;
            border: 1.5px solid #cbd5e1;
            border-radius: 6px;
            color: #475569;
            font-size: 0.84rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
        }

        .vignette-btn-reset:hover {
            background: #f1f5f9;
            color: #0f172a;
            border-color: #94a3b8;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
        }

        /* Table Styles */
        .table-responsive {
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        
        .table {
            margin: 0;
            width: 100%;
            min-width: 880px;
        }
        
        .table thead th {
            background: #f1f5f9;
            border: none;
            padding: 10px 12px;
            font-weight: 700;
            color: #1e293b;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.4px;
            border-bottom: 2px solid #cbd5e1;
        }
        
        .table tbody td {
            padding: 11px 12px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            font-size: 0.85rem;
        }
        
        .table tbody tr:hover {
            background-color: #f8fafc;
        }

        /* Badge Styles */
        .badge-app-fresh {
            background-color: #e0f2fe;
            color: #0369a1;
            border: 1px solid #bae6fd;
            font-size: 0.75rem;
            padding: 3px 8px;
            border-radius: 4px;
            font-weight: 700;
        }

        .badge-app-renewal {
            background-color: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
            font-size: 0.75rem;
            padding: 3px 8px;
            border-radius: 4px;
            font-weight: 700;
        }

        /* Action Buttons */
        .btn-action-group {
            display: inline-flex;
            gap: 5px;
            align-items: center;
            justify-content: center;
        }

        .btn-view {
            background: #1e293b;
            border: 1px solid #1e293b;
            color: #ffffff;
            font-size: 0.78rem;
            padding: 4px 10px;
            border-radius: 5px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(30, 41, 59, 0.2);
            text-decoration: none;
            cursor: pointer;
        }

        .btn-view:hover {
            background: #0f172a;
            color: #ffffff;
            border-color: #0f172a;
            transform: translateY(-1px);
        }

        .btn-edit {
            background: #d97706;
            border: 1px solid #d97706;
            color: #ffffff;
            font-size: 0.78rem;
            padding: 4px 10px;
            border-radius: 5px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(217, 119, 6, 0.2);
            text-decoration: none;
            cursor: pointer;
        }

        .btn-edit:hover {
            background: #b45309;
            color: #ffffff;
            border-color: #b45309;
            transform: translateY(-1px);
        }

        /* Pagination Styling */
        .pagination-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            padding-top: 14px;
            margin-top: 14px;
            border-top: 1px solid #e2e8f0;
        }

        .pagination-info {
            font-size: 0.84rem;
            color: #64748b;
            font-weight: 500;
        }

        .pagination .page-item .page-link {
            color: #334155;
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            padding: 4px 10px;
            font-size: 0.82rem;
            font-weight: 600;
            min-width: 32px;
            text-align: center;
            transition: all 0.15s ease;
        }

        .pagination .page-item.active .page-link {
            background-color: #1a5c2e !important;
            border-color: #1a5c2e !important;
            color: #ffffff !important;
            box-shadow: 0 2px 4px rgba(26, 92, 46, 0.25);
        }

        .pagination .page-item.disabled .page-link {
            color: #94a3b8;
            background-color: #f8fafc;
            border-color: #e2e8f0;
            cursor: not-allowed;
        }

        .pagination .page-item:not(.active):not(.disabled) .page-link:hover {
            background-color: #f1f5f9;
            color: #1a5c2e;
            border-color: #94a3b8;
        }

        /* No Results State */
        .no-results {
            display: none;
            text-align: center;
            padding: 40px 20px;
            color: #64748b;
        }

        @media (max-width: 768px) {
            .page-content {
                padding: 12px;
            }
            .datetime-display {
                text-align: left;
                margin-top: 12px;
            }
            .vignette-field-user,
            .vignette-field-status,
            .vignette-field-search,
            .vignette-field-reset {
                flex: 1 1 100%;
                min-width: 100%;
            }
            .vignette-btn-reset {
                width: 100%;
            }
            .pagination-footer {
                flex-direction: column;
                align-items: center;
            }
        }
    </style>
</head>
<body>
    <!-- Shared Sidebar Component -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navigation -->
        <nav class="top-nav">
            <div class="nav-left">
                <button class="mobile-menu-btn" id="mobileMenuBtn">
                    <i class="fa fa-bars"></i>
                    <span class="d-none d-sm-inline">Menu</span>
                </button>
                <h4>View Re-Entry Visas</h4>
            </div>
            
            <div class="user-info">
                <div class="user-avatar">
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
                        <img src="<?php echo htmlspecialchars($user_photo_path); ?>" alt="Avatar">
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

        <!-- Page Content -->
        <div class="page-content">
            <!-- Welcome Banner -->
            <div class="welcome-banner">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2>Welcome, <?php echo htmlspecialchars($fullName); ?></h2>
                        <p>View, manage, and search processed Re-Entry Visa applications.</p>
                    </div>
                    <div class="col-md-4">
                        <div class="datetime-display">
                            <div id="currentDateTime">Loading date...</div>
                            <div class="location"><i class="fa fa-map-marker text-danger me-1"></i> NIS Headquarters, Abuja</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Overview Cards -->
            <div class="stats-grid">
                <!-- Stat 1: Total Applications -->
                <div class="stat-card card-total">
                    <div class="stat-card-icon" style="color: #1a5c2e;"><i class="fa fa-folder-open-o"></i></div>
                    <div class="stat-number"><?php echo number_format($stats['total_records']); ?></div>
                    <div class="stat-label">Total Applications</div>
                </div>

                <!-- Stat 2: Fresh Applications -->
                <div class="stat-card card-fresh">
                    <div class="stat-card-icon text-success"><i class="fa fa-plus-circle"></i></div>
                    <div class="stat-number"><?php echo number_format($stats['total_fresh']); ?></div>
                    <div class="stat-label">Fresh Applications</div>
                </div>

                <!-- Stat 3: Renewal Applications -->
                <div class="stat-card card-renewal">
                    <div class="stat-card-icon text-warning"><i class="fa fa-refresh"></i></div>
                    <div class="stat-number"><?php echo number_format($stats['total_renewal']); ?></div>
                    <div class="stat-label">Renewal Applications</div>
                </div>

                <!-- Stat 4: Applications Today -->
                <div class="stat-card card-today">
                    <div class="stat-card-icon" style="color: #2563eb;"><i class="fa fa-calendar-check-o"></i></div>
                    <div class="stat-number"><?php echo number_format($stats['total_today']); ?></div>
                    <div class="stat-label">Total Applications Today</div>
                </div>
            </div>
            
            <!-- Re-Entry Visas Table Card -->
            <div class="card">
                <div class="card-body">
                    <!-- Title Section -->
                    <div class="card-title-section">
                        <h5 class="card-title">
                            <i class="fa fa-list-alt text-success"></i> Re-Entry Visa Applications Records
                        </h5>
                    </div>
                    
                    <!-- Dedicated Clean Toolbar -->
                    <div class="vignette-toolbar">
                        <div class="vignette-toolbar-grid">
                            <!-- Filter 1: Processed By Officer -->
                            <div class="vignette-field vignette-field-user">
                                <span class="vignette-field-addon">
                                    <i class="fa fa-user text-success"></i> <?php echo $isDataEntry ? 'Officer:' : 'Processed By:'; ?>
                                </span>
                                <?php if ($isDataEntry): ?>
                                    <select id="filterProcessedBy" class="vignette-select" disabled>
                                        <option value=""><?php echo htmlspecialchars($fullName . ' (' . $username . ')'); ?></option>
                                    </select>
                                <?php else: ?>
                                    <select id="filterProcessedBy" class="vignette-select">
                                        <option value="">All Officers / Users</option>
                                        <?php foreach ($all_officers as $off): ?>
                                            <option value="<?php echo htmlspecialchars($off['value']); ?>">
                                                <?php echo htmlspecialchars($off['label']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                            </div>

                            <!-- Filter 2: Application Type -->
                            <div class="vignette-field vignette-field-status">
                                <span class="vignette-field-addon">
                                    <i class="fa fa-tag text-success"></i> Type:
                                </span>
                                <select id="filterAppType" class="vignette-select">
                                    <option value="">All Types (Fresh & Renewal)</option>
                                    <option value="fresh">Fresh</option>
                                    <option value="renewal">Renewal</option>
                                </select>
                            </div>

                            <!-- Search Input -->
                            <div class="vignette-field vignette-field-search">
                                <span class="vignette-field-addon">
                                    <i class="fa fa-search text-muted"></i>
                                </span>
                                <input type="text" id="searchInput" class="vignette-input" placeholder="Search by Visa No, Applicant Name, Passport No...">
                            </div>

                            <!-- Reset / Clear Button -->
                            <div class="vignette-field vignette-field-reset">
                                <button id="clearSearch" type="button" class="vignette-btn-reset" title="Clear search and reset filters">
                                    <i class="fa fa-refresh"></i> Reset
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Responsive Table Container -->
                    <div class="table-responsive">
                        <table class="table table-hover table-striped" id="applicationsTable">
                            <thead>
                                <tr>
                                    <th style="width: 55px;" class="text-center">S/No</th>
                                    <th>Re-Entry Visa No.</th>
                                    <th>Full Name</th>
                                    <th>Passport No.</th>
                                    <th>Type</th>
                                    <th>Journey</th>
                                    <th>Within (Validity)</th>
                                    <th>Processed By</th>
                                    <th style="width: 180px;" class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody id="applicationsTableBody">
                                <?php
                                if ($numb > 0 && $retrieve) {
                                    $sno = 1;
                                    while ($ret = mysqli_fetch_assoc($retrieve)) {
                                        $visa_number = htmlspecialchars($ret['visa_number']);
                                        $surname = htmlspecialchars($ret['surname']);
                                        $given_name = htmlspecialchars($ret['given_name']);
                                        $passport_number = htmlspecialchars($ret['passport_number']);
                                        $journey = isset($ret['journey']) ? htmlspecialchars($ret['journey']) : 'N/A';
                                        $within = isset($ret['within']) ? formatNISDate($ret['within']) : 'N/A';
                                        $app_type = strtolower($ret['application_type'] ?? 'fresh');
                                        $created_by = $ret['created_by'] ?? '';
                                        
                                        if (!empty($ret['fname'])) {
                                            $officer_display = htmlspecialchars(strtoupper($ret['fname'] . ' ' . $ret['lname'] . ' (' . $created_by . ')'));
                                        } else {
                                            $officer_display = htmlspecialchars(strtoupper($created_by ?: 'N/A'));
                                        }
                                        $jsVisa = json_encode($ret['visa_number']);
                                ?>
                                <tr class="app-row"
                                    data-visa="<?php echo strtolower($visa_number); ?>"
                                    data-surname="<?php echo strtolower($ret['surname']); ?>"
                                    data-givenname="<?php echo strtolower($ret['given_name']); ?>"
                                    data-passport="<?php echo strtolower($ret['passport_number']); ?>"
                                    data-type="<?php echo strtolower($app_type); ?>"
                                    data-created-by="<?php echo strtolower($created_by); ?>"
                                    data-officer-display="<?php echo strtolower($officer_display); ?>">
                                    <td class="cell-sno text-center fw-bold text-muted"><?php echo $sno; ?></td>
                                    <td class="fw-bold text-dark font-monospace"><?php echo $visa_number; ?></td>
                                    <td class="fw-semibold text-dark"><?php echo strtoupper($surname . ' ' . $given_name); ?></td>
                                    <td class="font-monospace text-dark"><?php echo $passport_number; ?></td>
                                    <td>
                                        <span class="badge <?php echo ($app_type === 'renewal') ? 'badge-app-renewal' : 'badge-app-fresh'; ?>">
                                            <?php echo strtoupper($app_type); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $journey; ?></td>
                                    <td><?php echo $within; ?></td>
                                    <td>
                                        <div class="fw-semibold text-dark" style="font-size: 0.8rem;"><?php echo $officer_display; ?></div>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-action-group">
                                            <a href="viewrecord?id=<?php echo urlencode($ret['visa_number']); ?>" class="btn-view" title="View Application Details">
                                                <i class="fa fa-eye"></i> View
                                            </a>
                                            <?php if ($userRole !== 'ViewOnly'): ?>
                                                <a href="editrecord?id=<?php echo urlencode($ret['visa_number']); ?>" class="btn-edit" title="Edit Application">
                                                    <i class="fa fa-edit"></i> Edit
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php
                                        $sno++;
                                    }
                                    mysqli_free_result($retrieve);
                                } else {
                                    echo "<tr><td colspan='9' class='text-center py-4 text-muted'><i class='fa fa-info-circle me-1'></i> No records found.</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                        
                        <div id="noResults" class="no-results">
                            <i class="fa fa-search fa-2x text-muted mb-2 d-block"></i>
                            <strong>No matching applications found</strong>
                            <p class="small text-muted mb-0">Try changing your search term or selecting a different officer/type filter.</p>
                        </div>
                    </div>

                    <!-- Pagination Footer (Breaks at 20 records per page) -->
                    <div class="pagination-footer">
                        <div class="pagination-info" id="paginationInfo">
                            Showing <span id="pageStart">1</span> to <span id="pageEnd">20</span> of <span id="totalItems">0</span> records
                        </div>
                        <nav aria-label="Visa Records Pagination">
                            <ul class="pagination pagination-sm mb-0 gap-1" id="paginationNav">
                                <!-- Dynamically generated by JS -->
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
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
        // JavaScript functions for record actions
        function viewRecord(id) {
            window.location.href = 'viewrecord?id=' + encodeURIComponent(id);
        }
        
        function editRecord(id) {
            window.location.href = 'editrecord?id=' + encodeURIComponent(id);
        }
        
        function downloadRecord(id) {
            window.location.href = 'download?id=' + encodeURIComponent(id);
        }
        
        // Update Live Date & Time
        function updateDateTime() {
            const dateTimeEl = document.getElementById('currentDateTime');
            if (!dateTimeEl) return;
            
            const now = new Date();
            const options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            };
            dateTimeEl.textContent = now.toLocaleDateString('en-US', options);
        }
        
        updateDateTime();
        setInterval(updateDateTime, 1000);
        
        // Interactive Pagination & Filter Engine (20 records per page)
        const PAGE_SIZE = 20;
        let currentPage = 1;
        let filteredRows = [];

        function getRows() {
            return Array.from(document.querySelectorAll('#applicationsTableBody tr.app-row'));
        }

        function applyFiltersAndPagination(goToPage = 1) {
            const allRows = getRows();
            const searchInput = document.getElementById('searchInput');
            const searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : '';
            const searchWords = searchTerm.split(/\s+/).filter(w => w.length > 0);
            
            const userFilter = document.getElementById('filterProcessedBy');
            const selectedUser = userFilter ? userFilter.value.toLowerCase().trim() : '';
            
            const typeFilter = document.getElementById('filterAppType');
            const selectedType = typeFilter ? typeFilter.value.toLowerCase().trim() : '';

            // Filter rows based on search, type, and officer
            filteredRows = allRows.filter(row => {
                // 1. Application Type Filter
                const rowType = row.getAttribute('data-type') || '';
                if (selectedType && selectedType !== rowType) {
                    return false;
                }

                // 2. Processed By Filter
                const rowCreatedBy = row.getAttribute('data-created-by') || '';
                const rowOfficerDisplay = row.getAttribute('data-officer-display') || '';
                if (selectedUser) {
                    if (rowCreatedBy !== selectedUser && !rowOfficerDisplay.includes(selectedUser)) {
                        return false;
                    }
                }

                // 3. Search Query Filter (Searches visa, surname, given name, passport, officer display)
                if (searchWords.length > 0) {
                    const visa = row.getAttribute('data-visa') || '';
                    const surname = row.getAttribute('data-surname') || '';
                    const givenName = row.getAttribute('data-givenname') || '';
                    const passport = row.getAttribute('data-passport') || '';
                    const combinedText = `${visa} ${surname} ${givenName} ${passport} ${rowCreatedBy} ${rowOfficerDisplay} ${rowType}`;
                    
                    for (let word of searchWords) {
                        if (!combinedText.includes(word)) {
                            return false;
                        }
                    }
                }

                return true;
            });

            const totalMatching = filteredRows.length;
            const totalPages = Math.max(1, Math.ceil(totalMatching / PAGE_SIZE));
            
            if (goToPage > totalPages) goToPage = totalPages;
            if (goToPage < 1) goToPage = 1;
            currentPage = goToPage;

            const startIndex = (currentPage - 1) * PAGE_SIZE;
            const endIndex = Math.min(startIndex + PAGE_SIZE, totalMatching);

            // Hide all rows initially
            allRows.forEach(r => r.style.display = 'none');

            // Show and re-number only current page's matching rows
            filteredRows.forEach((row, idx) => {
                if (idx >= startIndex && idx < endIndex) {
                    row.style.display = '';
                    const snoCell = row.querySelector('.cell-sno');
                    if (snoCell) {
                        snoCell.textContent = (idx + 1);
                    }
                } else {
                    row.style.display = 'none';
                }
            });

            // Update UI elements
            const infoEl = document.getElementById('paginationInfo');
            const tableEl = document.getElementById('applicationsTable');
            const noResultsEl = document.getElementById('noResults');

            if (totalMatching === 0) {
                if (tableEl) tableEl.style.display = 'none';
                if (noResultsEl) noResultsEl.style.display = 'block';
                if (infoEl) infoEl.innerHTML = 'Showing <strong class="text-dark">0</strong> records';
            } else {
                if (tableEl) tableEl.style.display = '';
                if (noResultsEl) noResultsEl.style.display = 'none';
                if (infoEl) {
                    infoEl.innerHTML = `Showing <strong class="text-dark">${startIndex + 1}</strong> to <strong class="text-dark">${endIndex}</strong> of <strong class="text-dark">${totalMatching}</strong> records`;
                }
            }

            renderPaginationNav(totalPages, currentPage);
        }

        function renderPaginationNav(totalPages, activePage) {
            const navEl = document.getElementById('paginationNav');
            if (!navEl) return;

            if (totalPages <= 1) {
                navEl.innerHTML = '';
                return;
            }

            let html = '';
            
            // Previous Button
            if (activePage > 1) {
                html += `<li class="page-item"><a class="page-link" href="#" onclick="applyFiltersAndPagination(${activePage - 1}); return false;" aria-label="Previous">&laquo; Prev</a></li>`;
            } else {
                html += `<li class="page-item disabled"><span class="page-link">&laquo; Prev</span></li>`;
            }

            // Numbered Page Buttons with Smart Ellipsis
            for (let p = 1; p <= totalPages; p++) {
                if (p === 1 || p === totalPages || (p >= activePage - 2 && p <= activePage + 2)) {
                    if (p === activePage) {
                        html += `<li class="page-item active"><span class="page-link">${p}</span></li>`;
                    } else {
                        html += `<li class="page-item"><a class="page-link" href="#" onclick="applyFiltersAndPagination(${p}); return false;">${p}</a></li>`;
                    }
                } else if (p === activePage - 3 || p === activePage + 3) {
                    html += `<li class="page-item disabled"><span class="page-link">&hellip;</span></li>`;
                }
            }

            // Next Button
            if (activePage < totalPages) {
                html += `<li class="page-item"><a class="page-link" href="#" onclick="applyFiltersAndPagination(${activePage + 1}); return false;" aria-label="Next">Next &raquo;</a></li>`;
            } else {
                html += `<li class="page-item disabled"><span class="page-link">Next &raquo;</span></li>`;
            }

            navEl.innerHTML = html;
        }

        // Initialize Filter & Search Listeners
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const userFilter = document.getElementById('filterProcessedBy');
            const typeFilter = document.getElementById('filterAppType');
            const clearBtn = document.getElementById('clearSearch');

            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    applyFiltersAndPagination(1);
                });
            }

            if (userFilter) {
                userFilter.addEventListener('change', function() {
                    applyFiltersAndPagination(1);
                });
            }

            if (typeFilter) {
                typeFilter.addEventListener('change', function() {
                    applyFiltersAndPagination(1);
                });
            }

            if (clearBtn) {
                clearBtn.addEventListener('click', function() {
                    if (searchInput) searchInput.value = '';
                    if (userFilter) userFilter.value = '';
                    if (typeFilter) typeFilter.value = '';
                    applyFiltersAndPagination(1);
                    if (searchInput) searchInput.focus();
                });
            }

            // Initial pagination render on load
            applyFiltersAndPagination(1);
        });
    </script>
</body>

