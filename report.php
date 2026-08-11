<?php
require_once('security.php');
error_reporting(E_ALL);
require_once('db/db.php');

// Check if user is logged in - use the same session variable as process_visa.php
if (!isset($_SESSION['username'])) {
    header("Location: index");
    exit();
}

// Verify role is Admin or Supervisor
$userRole = $_SESSION['role'] ?? 'DataEntry';
if ($userRole !== 'Admin' && $userRole !== 'Supervisor') {
    header("Location: user_dashboard");
    exit();
}



// Get report data based on filters
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // Default to start of current month
$end_date = $_GET['end_date'] ?? date('Y-m-t'); // Default to end of current month
$report_type = $_GET['report_type'] ?? 'monthly';

// Initialize data arrays
$status_data = ['active' => 0, 'pending' => 0];
$monthly_data = [];
$user_stats = [];
$top_applications = [];
$audit_logs = [];

try {
    // First, ensure the visa_applications table exists
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'visa_applications'");
    if (mysqli_num_rows($table_check) == 0) {
        // Table doesn't exist, initialize empty data
        $overall_stats = ['total' => 0, 'approved' => 0, 'pending' => 0, 'rejected' => 0];
    } else {
        // Status distribution data - using the actual statuses from process_visa.php
        $status_query = "SELECT status, COUNT(*) as count FROM visa_applications 
                        WHERE application_date BETWEEN ? AND ? 
                        GROUP BY status";
        $stmt = mysqli_prepare($conn, $status_query);
        mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
        mysqli_stmt_execute($stmt);
        $status_result = mysqli_stmt_get_result($stmt);
        
        // Initialize all possible statuses
        $all_statuses = ['active', 'pending']; // Add other statuses if they exist
        foreach ($all_statuses as $status) {
            $status_data[$status] = 0;
        }
        
        while ($row = mysqli_fetch_assoc($status_result)) {
            $status_data[$row['status']] = $row['count'];
        }

        // Monthly application data for the current year - using application_date field
        $monthly_query = "SELECT 
                            MONTH(application_date) as month, 
                            COUNT(*) as count 
                          FROM visa_applications 
                          WHERE YEAR(application_date) = YEAR(CURRENT_DATE())
                          GROUP BY MONTH(application_date) 
                          ORDER BY month";
        $monthly_result = mysqli_query($conn, $monthly_query);
        
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        foreach ($months as $index => $month) {
            $monthly_data[$month] = 0;
        }
        
        while ($row = mysqli_fetch_assoc($monthly_result)) {
            $month_name = $months[$row['month'] - 1];
            $monthly_data[$month_name] = $row['count'];
        }

        // User statistics - using created_by field directly
        $user_query = "SELECT 
                        created_by as username,
                        COUNT(id) as applications,
                        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as approved
                      FROM visa_applications 
                      WHERE application_date BETWEEN ? AND ?
                      GROUP BY created_by 
                      ORDER BY applications DESC 
                      LIMIT 10";
        $stmt = mysqli_prepare($conn, $user_query);
        mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
        mysqli_stmt_execute($stmt);
        $user_result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($user_result)) {
            $user_stats[] = $row;
        }

        // Top applications by nationality
        $top_query = "SELECT 
                        nationality as country,
                        COUNT(*) as count 
                      FROM visa_applications 
                      WHERE application_date BETWEEN ? AND ?
                      GROUP BY nationality 
                      ORDER BY count DESC 
                      LIMIT 8";
        $stmt = mysqli_prepare($conn, $top_query);
        mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
        mysqli_stmt_execute($stmt);
        $top_result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($top_result)) {
            $top_applications[] = $row;
        }

        // Overall statistics - adjusted for actual statuses
        $total_query = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as approved,
                        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
                      FROM visa_applications 
                      WHERE application_date BETWEEN ? AND ?";
        $stmt = mysqli_prepare($conn, $total_query);
        mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
        mysqli_stmt_execute($stmt);
        $total_result = mysqli_stmt_get_result($stmt);
        $overall_stats = mysqli_fetch_assoc($total_result);
        $overall_stats['rejected'] = 0; // Add rejected count if you have this status

        // Fetch Audit Logs (Activity Trail) if table exists
        $log_table_check = mysqli_query($conn, "SHOW TABLES LIKE 'activity_log'");
        if (mysqli_num_rows($log_table_check) > 0) {
            $start_date_ts = $start_date . ' 00:00:00';
            $end_date_ts = $end_date . ' 23:59:59';
            
            $audit_query = "SELECT id, user_id, action, description, ip_address, created_at 
                            FROM activity_log 
                            WHERE created_at BETWEEN ? AND ? 
                            ORDER BY id DESC 
                            LIMIT 50";
            if ($stmt_audit = mysqli_prepare($conn, $audit_query)) {
                mysqli_stmt_bind_param($stmt_audit, "ss", $start_date_ts, $end_date_ts);
                mysqli_stmt_execute($stmt_audit);
                $audit_result = mysqli_stmt_get_result($stmt_audit);
                while ($row = mysqli_fetch_assoc($audit_result)) {
                    $audit_logs[] = $row;
                }
                mysqli_stmt_close($stmt_audit);
            }
        }
    }

} catch (Exception $e) {
    error_log("Report error: " . $e->getMessage());
    $overall_stats = ['total' => 0, 'approved' => 0, 'pending' => 0, 'rejected' => 0];
    $status_data = ['active' => 0, 'pending' => 0];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>NIS Re-Entry Visa System - Reports</title>
    <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
    <link href="vendors/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="vendors/font-awesome/css/font-awesome.min.css" rel="stylesheet">
    <script src="vendors/chartjs/chart.js"></script>
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
        
        /* Centralized Sidebar Styles moved to sidebar.php */
        
        /* Main Content */
        .main-content {
            min-height: 100vh;
            transition: all 0.3s ease;
        }
        
        /* Top Navigation - MATCHING INDEX.PHP */
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
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .user-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1rem;
            box-shadow: 0 2px 8px rgba(26, 92, 46, 0.2);
            overflow: hidden;
        }
        
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
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
        
        /* Page Content */
        .page-content {
            padding: 20px;
            min-height: calc(100vh - 64px);
        }

        /* Report Header with Filters */
        .report-header {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        .report-header h4 {
            color: var(--primary);
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .report-header p {
            color: var(--gray);
            font-size: 0.9rem;
            margin-bottom: 0;
        }
        
        /* Filter Form */
        .filter-form .form-group {
            margin-bottom: 0;
        }
        
        .filter-form .form-control {
            border: 1.5px solid #e1e5e9;
            border-radius: 6px;
            padding: 8px 12px;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        .filter-form .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26, 92, 46, 0.1);
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: white;
            padding: 18px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            text-align: center;
            border: 1px solid #e1e5e9;
        }
        
        .stat-card i {
            font-size: 1.8rem;
            margin-bottom: 10px;
            display: block;
        }
        
        .stat-number {
            font-size: 1.7rem;
            font-weight: 700;
            margin: 8px 0;
            line-height: 1;
        }
        
        .stat-card.total .stat-number { color: var(--info); }
        .stat-card.approved .stat-number { color: var(--secondary); }
        .stat-card.pending .stat-number { color: var(--warning); }
        .stat-card.rejected .stat-number { color: var(--danger); }
        
        .stat-label {
            color: var(--gray);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
        }
        
        /* Charts Grid */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .chart-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
        }
        
        .chart-card h5 {
            margin-bottom: 15px;
            color: var(--primary);
            font-weight: 600;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .chart-container {
            position: relative;
            height: 250px;
            width: 100%;
        }
        
        /* Data Tables */
        .data-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        .data-card h5 {
            margin-bottom: 15px;
            color: var(--primary);
            font-weight: 600;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .table-responsive {
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid #e1e5e9;
        }
        
        .table {
            margin: 0;
            min-width: 500px;
        }
        
        .table thead th {
            background: #f8f9fa;
            border: none;
            padding: 12px;
            font-weight: 600;
            color: var(--dark);
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e1e5e9;
        }
        
        .table tbody td {
            padding: 12px;
            vertical-align: middle;
            border-color: #e1e5e9;
            font-size: 0.9rem;
        }
        
        .table tbody tr:hover {
            background: #f8f9fa;
        }
        
        /* Export Section */
        .export-section {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
            text-align: center;
            margin-top: 20px;
        }
        
        .export-section h5 {
            margin-bottom: 15px;
            color: var(--primary);
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-export {
            margin: 5px;
            border-radius: 6px;
            padding: 8px 15px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .btn-success {
            background: linear-gradient(135deg, var(--secondary), #219a52);
            border: none;
            color: white;
        }
        
        .btn-success:hover {
            background: linear-gradient(135deg, #219a52, #1e8a47);
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(39, 174, 96, 0.3);
        }
        
        .btn-info {
            background: linear-gradient(135deg, var(--info), #2e7d46);
            border: none;
            color: white;
        }
        
        .btn-info:hover {
            background: linear-gradient(135deg, #2e7d46, var(--info));
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, var(--warning), #e67e22);
            border: none;
            color: white;
        }
        
        .btn-warning:hover {
            background: linear-gradient(135deg, #e67e22, var(--warning));
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(243, 156, 18, 0.3);
        }
        
        /* Responsive Styles - MATCHING INDEX.PHP */
        /* Centralized Sidebar Responsive Styles moved to sidebar.php */
            
            .report-header {
                text-align: center;
            }
            
            .filter-form .form-row {
                gap: 10px;
            }
            
            .charts-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .page-content {
                padding: 15px;
            }
            
            .top-nav {
                padding: 10px 15px;
                flex-direction: column;
                gap: 12px;
            }
            
            .nav-left {
                width: 100%;
                justify-content: space-between;
            }
            
            .user-info {
                width: 100%;
                justify-content: space-between;
                padding-top: 12px;
                border-top: 1px solid #e1e5e9;
            }
            
            .report-header {
                padding: 15px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .chart-container {
                height: 200px;
            }
        }
        
        @media (max-width: 576px) {
            .sidebar {
                width: 85%;
                max-width: 280px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-form .form-row {
                flex-direction: column;
                gap: 10px;
            }
            
            .filter-form .col {
                width: 100%;
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
            
            .chart-card,
            .data-card {
                padding: 15px;
            }
            
            .chart-container {
                height: 180px;
            }
        }
        
        @media (max-width: 360px) {
            .page-content {
                padding: 12px;
            }
            
            .stat-card {
                padding: 15px;
            }
            
            .stat-number {
                font-size: 1.5rem;
            }
            
            .btn-export {
                width: 100%;
                margin: 5px 0;
                justify-content: center;
            }
        }
        
        /* Print Styles */
        @media print {
            .sidebar,
            .top-nav,
            .report-header,
            .export-section,
            .btn,
            .main-footer {
                display: none !important;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .page-content {
                padding: 20px 0;
            }
            
            .chart-card,
            .data-card,
            .stat-card {
                box-shadow: none;
                border: 1px solid #ddd;
                page-break-inside: avoid;
            }
            
            .table-responsive {
                max-height: none !important;
                overflow: visible !important;
            }
            
            body {
                background: white !important;
                font-size: 12pt;
            }
            
            .table {
                font-size: 10pt;
            }
            
            .table th {
                background-color: #f8f9fa !important;
                color: black !important;
                border: 1px solid #ddd !important;
            }
        }
        
        @media (prefers-contrast: high) {
            .table thead th {
                border-bottom-width: 3px;
            }
        }
        
        /* Reduced Motion */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
            
            .btn-export:hover,
            .logout-btn:hover {
                transform: none;
            }
            
            .sidebar-menu a:hover {
                transform: none;
            }
        }
    </style>
</head>
<body>
    <!-- Shared Sidebar Component -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navigation - MATCHING INDEX.PHP -->
        <nav class="top-nav">
            <div class="nav-left">
                <button class="mobile-menu-btn" id="mobileMenuBtn">
                    <i class="fa fa-bars"></i>
                    <span class="d-none d-sm-inline">Menu</span>
                </button>
                <h4>
                    <i class="fa fa-bar-chart" style="color: var(--primary);"></i>
                    Reports & Analytics
                </h4>
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

        <!-- Report Content -->
        <div class="page-content">
            <!-- Report Header with Filters -->
            <div class="report-header">
                <div class="row align-items-center">
                    <div class="col-lg-6 col-md-12 mb-3 mb-lg-0">
                        <h4>Visa Application Analytics</h4>
                        <p class="mb-0">Comprehensive reports and statistics</p>
                    </div>
                    <div class="col-lg-6 col-md-12">
                        <form method="GET" class="filter-form">
                            <div class="form-row">
                                <div class="col-12 col-sm mb-2 mb-sm-0">
                                    <input type="date" class="form-control" name="start_date" 
                                           value="<?php echo htmlspecialchars($start_date); ?>">
                                </div>
                                <div class="col-12 col-sm mb-2 mb-sm-0">
                                    <input type="date" class="form-control" name="end_date" 
                                           value="<?php echo htmlspecialchars($end_date); ?>">
                                </div>
                                <div class="col-12 col-sm mb-2 mb-sm-0">
                                    <select class="form-control" name="report_type">
                                        <option value="monthly" <?php echo $report_type === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                        <option value="quarterly" <?php echo $report_type === 'quarterly' ? 'selected' : ''; ?>>Quarterly</option>
                                        <option value="yearly" <?php echo $report_type === 'yearly' ? 'selected' : ''; ?>>Yearly</option>
                                    </select>
                                </div>
                                <div class="col-12 col-sm">
                                    <button type="submit" class="btn btn-primary btn-block">
                                        <i class="fa fa-filter"></i> Filter
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Statistics Overview -->
            <div class="stats-grid">
                <div class="stat-card total">
                    <i class="fa fa-passport text-info"></i>
                    <div class="stat-number"><?php echo $overall_stats['total'] ?? 0; ?></div>
                    <div class="stat-label">Total Applications</div>
                </div>
                <div class="stat-card approved">
                    <i class="fa fa-check-circle text-success"></i>
                    <div class="stat-number"><?php echo $overall_stats['approved'] ?? 0; ?></div>
                    <div class="stat-label">Active</div>
                </div>
                <div class="stat-card pending">
                    <i class="fa fa-clock-o text-warning"></i>
                    <div class="stat-number"><?php echo $overall_stats['pending'] ?? 0; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-card rejected">
                    <i class="fa fa-times-circle text-danger"></i>
                    <div class="stat-number"><?php echo $overall_stats['rejected'] ?? 0; ?></div>
                    <div class="stat-label">Rejected</div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="charts-grid">
                <!-- Status Distribution Pie Chart -->
                <div class="chart-card">
                    <h5><i class="fa fa-pie-chart" style="color: var(--primary);"></i> Application Status Distribution</h5>
                    <div class="chart-container">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>

                <!-- Monthly Trends Line Chart -->
                <div class="chart-card">
                    <h5><i class="fa fa-line-chart" style="color: var(--secondary);"></i> Monthly Application Trends</h5>
                    <div class="chart-container">
                        <canvas id="monthlyChart"></canvas>
                    </div>
                </div>

                <!-- User Performance Bar Chart -->
                <div class="chart-card">
                    <h5><i class="fa fa-users" style="color: var(--info);"></i> Top Performers (Users)</h5>
                    <div class="chart-container">
                        <canvas id="userChart"></canvas>
                    </div>
                </div>

                <!-- Country Distribution Bar Chart -->
                <div class="chart-card">
                    <h5><i class="fa fa-globe" style="color: var(--warning);"></i> Applications by Country</h5>
                    <div class="chart-container">
                        <canvas id="countryChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Detailed Data Tables -->
            <div class="row">
                <!-- User Statistics Table -->
                <div class="col-lg-6 col-md-12 mb-3 mb-lg-0">
                    <div class="data-card">
                        <h5><i class="fa fa-user-secret" style="color: var(--primary);"></i> User Performance</h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Total Apps</th>
                                        <th>Active</th>
                                        <th>Success Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($user_stats)): ?>
                                        <?php foreach ($user_stats as $user): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                                <td><?php echo $user['applications']; ?></td>
                                                <td><?php echo $user['approved']; ?></td>
                                                <td>
                                                    <?php 
                                                        $rate = $user['applications'] > 0 ? ($user['approved'] / $user['applications']) * 100 : 0;
                                                        echo number_format($rate, 1) . '%';
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center">No user data available</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Country Statistics Table -->
                <div class="col-lg-6 col-md-12">
                    <div class="data-card">
                        <h5><i class="fa fa-flag" style="color: var(--secondary);"></i> Applications by Country</h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Country</th>
                                        <th>Applications</th>
                                        <th>Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($top_applications)): ?>
                                        <?php 
                                        $total_country = array_sum(array_column($top_applications, 'count'));
                                        foreach ($top_applications as $country): 
                                            $percentage = $total_country > 0 ? ($country['count'] / $total_country) * 100 : 0;
                                        ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($country['country']); ?></td>
                                                <td><?php echo $country['count']; ?></td>
                                                <td><?php echo number_format($percentage, 1); ?>%</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" class="text-center">No country data available</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
            </div>

            <!-- System Audit Trail Section -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="data-card">
                        <h5><i class="fa fa-history" style="color: var(--primary);"></i> System Audit Trail</h5>
                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-hover table-striped">
                                <thead style="position: sticky; top: 0; background: white; z-index: 1;">
                                    <tr>
                                        <th>Timestamp</th>
                                        <th>Operator</th>
                                        <th>Action</th>
                                        <th>Description</th>
                                        <th>IP Address</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($audit_logs)): ?>
                                        <?php foreach ($audit_logs as $log): ?>
                                            <tr>
                                                <td class="text-nowrap small">
                                                    <?php echo htmlspecialchars($log['created_at']); ?>
                                                </td>
                                                <td class="font-monospace fw-bold">
                                                    <?php echo htmlspecialchars($log['user_id']); ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                        $action = strtoupper($log['action']);
                                                        $badge_class = 'bg-secondary text-white';
                                                        if (strpos($action, 'CREATE') !== false) {
                                                            $badge_class = 'bg-success text-white';
                                                        } elseif (strpos($action, 'UPDATE') !== false || strpos($action, 'EDIT') !== false) {
                                                            $badge_class = 'bg-primary text-white';
                                                        } elseif (strpos($action, 'DELETE') !== false || strpos($action, 'REMOVE') !== false) {
                                                            $badge_class = 'bg-danger text-white';
                                                        } elseif (strpos($action, 'LOGIN') !== false) {
                                                            $badge_class = 'bg-warning text-dark';
                                                        }
                                                    ?>
                                                    <span class="badge <?php echo $badge_class; ?>" style="font-size: 0.75rem;">
                                                        <?php echo htmlspecialchars($action); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($log['description']); ?>
                                                </td>
                                                <td class="font-monospace small">
                                                    <?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">No audit trail logs recorded for this period.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Export Section -->
            <div class="export-section">
                <h5><i class="fa fa-download" style="color: var(--primary);"></i> Export Reports</h5>
                <div class="btn-group flex-wrap">
                    <button class="btn btn-success btn-export" onclick="exportPDF()">
                        <i class="fa fa-file-pdf-o"></i> Export as PDF
                    </button>
                    <button class="btn btn-info btn-export" onclick="exportExcel()">
                        <i class="fa fa-file-excel-o"></i> Export as Excel
                    </button>
                    <button class="btn btn-warning btn-export" onclick="printReport()">
                        <i class="fa fa-print"></i> Print Report
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Status Distribution Pie Chart - adjusted for actual statuses
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusChart = new Chart(statusCtx, {
            type: 'pie',
            data: {
                labels: ['Active', 'Pending'],
                datasets: [{
                    data: [
                        <?php echo $status_data['active'] ?? 0; ?>,
                        <?php echo $status_data['pending'] ?? 0; ?>
                    ],
                    backgroundColor: ['#27ae60', '#f39c12'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Monthly Trends Line Chart
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        const monthlyChart = new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_keys($monthly_data)); ?>,
                datasets: [{
                    label: 'Applications',
                    data: <?php echo json_encode(array_values($monthly_data)); ?>,
                    borderColor: '#27ae60',
                    backgroundColor: 'rgba(39, 174, 96, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });

        // User Performance Bar Chart
        const userCtx = document.getElementById('userChart').getContext('2d');
        const userChart = new Chart(userCtx, {
            type: 'bar',
            data: {
                labels: <?php echo !empty($user_stats) ? json_encode(array_column($user_stats, 'username')) : json_encode([]); ?>,
                datasets: [{
                    label: 'Applications',
                    data: <?php echo !empty($user_stats) ? json_encode(array_column($user_stats, 'applications')) : json_encode([]); ?>,
                    backgroundColor: '#3498db',
                    borderColor: '#2e7d46',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Country Distribution Bar Chart
        const countryCtx = document.getElementById('countryChart').getContext('2d');
        const countryChart = new Chart(countryCtx, {
            type: 'bar',
            data: {
                labels: <?php echo !empty($top_applications) ? json_encode(array_column($top_applications, 'country')) : json_encode([]); ?>,
                datasets: [{
                    label: 'Applications',
                    data: <?php echo !empty($top_applications) ? json_encode(array_column($top_applications, 'count')) : json_encode([]); ?>,
                    backgroundColor: '#f39c12',
                    borderColor: '#e67e22',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Export Functions
        function exportPDF() {
            alert('PDF export functionality would be implemented here');
            // In a real application, this would generate a PDF report
        }

        function exportExcel() {
            alert('Excel export functionality would be implemented here');
            // In a real application, this would generate an Excel report
        }

        function printReport() {
            window.print();
        }
        

    </script>
    <?php 
    if (file_exists('footer.php')) {
        include('footer.php');
    }
    ?>
</body>
</html>




