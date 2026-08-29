<?php
require_once('security.php');
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once('db/db.php');

// Sync current user's profile photo from database to ensure it's always up to date
if (isset($_SESSION['user_id'])) {
    $current_uid = $_SESSION['user_id'];
    $photo_sync = mysqli_query($conn, "SELECT photo FROM users WHERE id = '$current_uid'");
    if ($photo_sync && $photo_row = mysqli_fetch_assoc($photo_sync)) {
        $_SESSION['photo'] = $photo_row['photo'];
    }
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index");
    exit();
}

// Verify this is a regular DataEntry user (not Admin/Supervisor/ViewOnly)
$userRole = $_SESSION['role'] ?? 'DataEntry';
if ($userRole === 'Admin' || $userRole === 'Supervisor' || $userRole === 'ViewOnly' || strcasecmp($userRole, 'viewonly') === 0 || strcasecmp($userRole, 'view only') === 0) {
    // Admin, Supervisor, and ViewOnly should use the main general dashboard
    header("Location: index");
    exit();
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';
$fullName = $_SESSION['full_name'] ?? $username;

// Fetch user-specific data only
$numb = 0;
$total_fresh = 0;
$total_renewal = 0;
$today_visas = 0;
$recent_visas = 0;
$monthly_visas = 0;
$total_printed = 0;
$total_pending_print = 0;
$recent_applications = [];
$last_month_labels = [];
$last_month_counts = [];

try {
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'visa_applications'");
    
    if ($table_check && mysqli_num_rows($table_check) > 0) {
        // Fetch all comprehensive user statistics in one robust query
        $stats_sql = "SELECT 
            COUNT(*) as total_records,
            COALESCE(SUM(CASE WHEN LOWER(application_type) = 'fresh' OR application_type IS NULL OR application_type = '' THEN 1 ELSE 0 END), 0) as total_fresh,
            COALESCE(SUM(CASE WHEN LOWER(application_type) = 'renewal' THEN 1 ELSE 0 END), 0) as total_renewal,
            COALESCE(SUM(CASE WHEN DATE(application_date) = CURDATE() THEN 1 ELSE 0 END), 0) as today_visas,
            COALESCE(SUM(CASE WHEN DATE(application_date) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END), 0) as recent_visas,
            COALESCE(SUM(CASE WHEN MONTH(application_date) = MONTH(CURRENT_DATE()) AND YEAR(application_date) = YEAR(CURRENT_DATE()) THEN 1 ELSE 0 END), 0) as monthly_visas,
            COALESCE(SUM(CASE WHEN print_status = 1 THEN 1 ELSE 0 END), 0) as total_printed,
            COALESCE(SUM(CASE WHEN print_status = 0 OR print_status IS NULL THEN 1 ELSE 0 END), 0) as total_pending_print
        FROM visa_applications 
        WHERE created_by = '$username'";
        
        $stats_res = mysqli_query($conn, $stats_sql);
        if ($stats_res && $stats_data = mysqli_fetch_assoc($stats_res)) {
            $numb = intval($stats_data['total_records'] ?? 0);
            $total_fresh = intval($stats_data['total_fresh'] ?? 0);
            $total_renewal = intval($stats_data['total_renewal'] ?? 0);
            $today_visas = intval($stats_data['today_visas'] ?? 0);
            $recent_visas = intval($stats_data['recent_visas'] ?? 0);
            $monthly_visas = intval($stats_data['monthly_visas'] ?? 0);
            $total_printed = intval($stats_data['total_printed'] ?? 0);
            $total_pending_print = intval($stats_data['total_pending_print'] ?? 0);
        }
        
        // User's recent applications details (only their own)
        $query4 = "SELECT visa_number, surname, given_name, passport_number, application_type, application_date, status, print_status 
                  FROM visa_applications 
                  WHERE created_by = '$username'
                  ORDER BY application_date DESC, id DESC 
                  LIMIT 10";
        $result4 = mysqli_query($conn, $query4);
        if ($result4 && mysqli_num_rows($result4) > 0) {
            while ($row = mysqli_fetch_assoc($result4)) {
                $recent_applications[] = $row;
            }
        }

        // Query user issuance for the Last Month (Daily issuance grouped by date)
        $month_query = "SELECT 
                            DATE_FORMAT(application_date, '%d %b') as day_label, 
                            COUNT(*) as count 
                        FROM visa_applications 
                        WHERE created_by = '$username'
                        AND application_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                        GROUP BY DATE(application_date)
                        ORDER BY DATE(application_date) ASC";
        $month_result = mysqli_query($conn, $month_query);
        if ($month_result && mysqli_num_rows($month_result) > 0) {
            while ($row = mysqli_fetch_assoc($month_result)) {
                $last_month_labels[] = $row['day_label'];
                $last_month_counts[] = (int)$row['count'];
            }
        }
        
        // If empty in last 30 days, populate with friendly label
        if (empty($last_month_labels)) {
            $last_month_labels = [date('d M', strtotime('-7 days')), date('d M')];
            $last_month_counts = [0, 0];
        }
    }
    
} catch (Exception $e) {
    error_log("User Dashboard error: " . $e->getMessage());
}

$hasData = $numb > 0 || !empty($recent_applications);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>NISREV - My Dashboard</title>
    <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
    <link href="vendors/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="vendors/font-awesome/css/font-awesome.min.css" rel="stylesheet">
    <!-- Chart.js for simple charts -->
    <script src="vendors/chartjs/chart.js"></script>
    <style>
        :root {
            --primary: #5c3c1e; /* NIS Coffee Brown */
            --secondary: #1a5c2e; /* NIS Green */
            --dark: #0f172a;
            --light: #f8fafc;
            --warning: #f59e0b;
            --info: #0ea5e9;
            --danger: #f43f5e;
            --gray: #64748b;
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
        
        /* Dashboard Content */
        .dashboard-content {
            padding: 20px;
        }
        
        /* Welcome Banner - SIMPLIFIED FOR USERS */
        .welcome-banner {
            background: linear-gradient(135deg, #1e293b, #334155);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 8px 25px rgba(30, 41, 59, 0.15);
            position: relative;
            overflow: hidden;
        }
        
        .welcome-text h2 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .welcome-text p {
            opacity: 0.9;
            margin-bottom: 0;
            font-size: 0.9rem;
        }
        
        .datetime-display {
            text-align: right;
        }
        
        #currentDateTime {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        .location {
            font-size: 0.85rem;
            opacity: 0.9;
        }
        
        /* User Stats Grid - DIFFERENT FROM ADMIN */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        @media (min-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }
        
        .user-stat-card {
            background: white;
            padding: 18px 14px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.06);
            text-align: center;
            border: 1px solid #e1e5e9;
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 120px;
        }
        
        .user-stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            border-color: #cbd5e1;
        }
        
        .user-stat-icon {
            font-size: 1.75rem;
            margin-bottom: 8px;
            line-height: 1;
            display: inline-block;
        }
        
        .user-stat-number {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 1.65rem;
            font-weight: 600;
            margin: 4px 0;
            color: var(--dark);
            line-height: 1.1;
            letter-spacing: -0.3px;
        }
        
        .user-stat-label {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.4px;
            margin-top: 4px;
            line-height: 1.2;
        }
        
        /* Quick Actions - SIMPLIFIED */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .user-action-btn {
            background: white;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            padding: 18px 15px;
            text-decoration: none;
            color: var(--dark);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.05);
        }
        
        .user-action-btn:hover {
            border-color: var(--primary);
            transform: translateY(-3px);
            text-decoration: none;
            color: var(--dark);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }
        
        .user-action-btn i {
            font-size: 1.8rem;
            margin-bottom: 12px;
            display: block;
        }
        
        .user-action-btn strong {
            display: block;
            font-size: 0.95rem;
            margin-bottom: 6px;
            font-weight: 600;
        }
        
        .user-action-btn small {
            color: var(--gray);
            font-size: 0.8rem;
            display: block;
        }
        
        /* My Performance Chart - ONLY FOR USER */
        .performance-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
        }
        
        .performance-card .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
        }
        
        .performance-card .card-header h5 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        /* Recent Activities - USER ONLY */
        .recent-activities {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
            margin-top: 15px;
        }
        
        .activities-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
        }
        
        .activities-header h5 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .view-all-btn {
            background: #1a5c2e !important;
            color: #ffffff !important;
            padding: 7px 18px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: none;
        }
        
        .view-all-btn:hover {
            background: #144824 !important;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(26, 92, 46, 0.35);
            color: #ffffff !important;
            text-decoration: none;
        }
        
        /* Table Styles */
        .table-responsive {
            overflow-x: auto;
            border-radius: 6px;
            border: 1px solid #e1e5e9;
        }
        
        .table {
            margin: 0;
            min-width: 600px;
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
        }
        
        .table tbody tr:hover {
            background: #f8f9fa;
        }
        
        /* Status Badges */
        .status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .status-pending {
            background: rgba(243, 156, 18, 0.1);
            color: var(--warning);
            border: 1px solid rgba(243, 156, 18, 0.3);
        }
        
        .status-approved {
            background: rgba(39, 174, 96, 0.1);
            color: var(--secondary);
            border: 1px solid rgba(39, 174, 96, 0.3);
        }
        
        .status-rejected {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
            border: 1px solid rgba(220, 53, 69, 0.3);
        }
        
        .status-completed {
            background: rgba(52, 152, 219, 0.1);
            color: var(--info);
            border: 1px solid rgba(52, 152, 219, 0.3);
        }
        
        /* No Data State */
        .no-data {
            text-align: center;
            padding: 40px 15px;
            color: var(--gray);
        }
        
        .no-data i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.3;
        }
        
        .no-data h5 {
            font-size: 1.1rem;
            margin-bottom: 10px;
            color: var(--dark);
        }
        
        /* User Info Card */
        .user-info-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
        }
        
        .user-info-content {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .user-avatar-large {
            width: 84px;
            height: 84px;
            border-radius: 50%;
            background: #ffffff;
            border: 3.5px solid #1a5c2e;
            color: #1a5c2e;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1.8rem;
            box-shadow: 0 4px 15px rgba(26, 92, 46, 0.3);
            overflow: hidden;
            flex-shrink: 0;
        }

        .user-avatar-large img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center top;
            display: block;
            image-rendering: -webkit-optimize-contrast;
            image-rendering: crisp-edges;
            filter: contrast(1.08) saturate(1.06) brightness(1.02);
        }
        
        .user-details-large {
            flex: 1;
        }
        
        .user-details-large h5 {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 5px;
        }
        
        .user-details-large p {
            color: var(--gray);
            margin-bottom: 8px;
            font-size: 0.9rem;
        }
        
        .user-meta {
            display: flex;
            gap: 15px;
            margin-top: 10px;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.85rem;
            color: var(--gray);
        }
        
        .meta-item i {
            color: var(--primary);
        }
        

        
        @media (max-width: 768px) {
            .dashboard-content {
                padding: 15px;
            }
            
            .top-nav {
                padding: 10px 15px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .welcome-banner {
                padding: 18px;
                text-align: center;
            }
            
            .datetime-display {
                text-align: center;
                margin-top: 15px;
            }
            
            .user-info-content {
                flex-direction: column;
                text-align: center;
            }
            
            .user-meta {
                justify-content: center;
            }
            
            .user-info {
                flex-wrap: wrap;
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
            
            .quick-actions {
                grid-template-columns: 1fr;
            }
            
            .recent-activities {
                padding: 15px;
            }
            
            .activities-header {
                flex-direction: column;
                gap: 12px;
                align-items: flex-start;
            }
            
            .view-all-btn {
                width: 100%;
                text-align: center;
            }
            
            .user-action-btn {
                padding: 15px 12px;
            }
            
            .user-stat-card {
                padding: 15px;
            }
            
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
            }
        }
        
        @media (max-width: 360px) {
            .dashboard-content {
                padding: 12px;
            }
            
            .welcome-banner {
                padding: 15px;
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
                <h4>My Dashboard</h4>
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
                        <?php echo htmlspecialchars($fullName); ?>
                    </div>
                    <div class="user-role">
                        <?php echo htmlspecialchars($userRole); ?>
                    </div>
                </div>
                <a href="logout" class="logout-btn">
                    <i class="fa fa-sign-out"></i>
                    <span>Logout</span>
                </a>
            </div>
        </nav>

        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <!-- Welcome Banner - SIMPLIFIED -->
            <div class="welcome-banner">
                <div class="row align-items-center">
                    <div class="col-lg-7 col-md-7">
                        <div class="welcome-text">
                            <h2 style="font-size: 1.45rem; font-weight: 700; letter-spacing: -0.3px; margin-bottom: 6px;">Welcome, <?php echo htmlspecialchars(strtoupper($fullName)); ?></h2>
                            <p style="font-size: 0.88rem; color: #cbd5e1; font-weight: 500; margin-bottom: 0;">
                                <i class="fa fa-shield" style="color: #22c55e; margin-right: 6px;"></i>NIS Re-Entry Visa Management Portal
                            </p>
                        </div>
                    </div>
                    <div class="col-lg-5 col-md-5">
                        <div class="datetime-display">
                            <div id="currentDateTime" style="font-size: 0.98rem; font-weight: 700; letter-spacing: 0.2px; color: #f8fafc; margin-bottom: 4px;"></div>
                            <div class="location" style="font-size: 0.84rem; color: #94a3b8; font-weight: 600; display: inline-flex; align-items: center; gap: 5px;">
                                <i class="fa fa-map-marker" style="color: #ef4444;"></i> NIS Headquarters, Abuja
                            </div>
                        </div>
                    </div>
                </div>
            </div>



            <!-- My Statistics - ONLY USER'S DATA -->
            <div class="stats-grid">
                <div class="user-stat-card">
                    <i class="fa fa-folder-open-o user-stat-icon" style="color: #1a5c2e;"></i>
                    <div class="user-stat-number" style="color: #1a5c2e;"><?php echo number_format($numb); ?></div>
                    <div class="user-stat-label">Total Applications</div>
                    <small class="text-muted">Your total visa applications</small>
                </div>
                <div class="user-stat-card">
                    <i class="fa fa-file-o user-stat-icon" style="color: #16a34a;"></i>
                    <div class="user-stat-number" style="color: #16a34a;"><?php echo number_format($total_fresh); ?></div>
                    <div class="user-stat-label">Total Fresh</div>
                    <small class="text-muted">Fresh applications created</small>
                </div>
                <div class="user-stat-card">
                    <i class="fa fa-refresh user-stat-icon" style="color: #d97706;"></i>
                    <div class="user-stat-number" style="color: #d97706;"><?php echo number_format($total_renewal); ?></div>
                    <div class="user-stat-label">Total Renewal</div>
                    <small class="text-muted">Renewal applications created</small>
                </div>
                <div class="user-stat-card">
                    <i class="fa fa-calendar-check-o user-stat-icon" style="color: #2563eb;"></i>
                    <div class="user-stat-number" style="color: #2563eb;"><?php echo number_format($today_visas); ?></div>
                    <div class="user-stat-label">Today</div>
                    <small class="text-muted">Applications created today</small>
                </div>
                <div class="user-stat-card">
                    <i class="fa fa-line-chart user-stat-icon" style="color: #0284c7;"></i>
                    <div class="user-stat-number" style="color: #0284c7;"><?php echo number_format($recent_visas); ?></div>
                    <div class="user-stat-label">Last 7 Days</div>
                    <small class="text-muted">Recent weekly activity</small>
                </div>
                <div class="user-stat-card">
                    <i class="fa fa-calendar user-stat-icon" style="color: #7c3aed;"></i>
                    <div class="user-stat-number" style="color: #7c3aed;"><?php echo number_format($monthly_visas); ?></div>
                    <div class="user-stat-label">This Month</div>
                    <small class="text-muted">Monthly performance</small>
                </div>
                <div class="user-stat-card">
                    <i class="fa fa-check-circle-o user-stat-icon" style="color: #059669;"></i>
                    <div class="user-stat-number" style="color: #059669;"><?php echo number_format($total_printed); ?></div>
                    <div class="user-stat-label">Total Printed Visas</div>
                    <small class="text-muted">Completed vignette prints</small>
                </div>
                <div class="user-stat-card">
                    <i class="fa fa-print user-stat-icon" style="color: #ea580c;"></i>
                    <div class="user-stat-number" style="color: #ea580c;"><?php echo number_format($total_pending_print); ?></div>
                    <div class="user-stat-label">Visas Pending Print</div>
                    <small class="text-muted">Awaiting vignette print</small>
                </div>
            </div>

            <!-- My Visualizations & Performance Metrics -->
            <div class="row mb-4">
                <div class="col-md-6 mb-3">
                    <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid #e1e5e9; height: 100%;">
                        <h5 class="fw-bold mb-3" style="font-size: 1.05rem; color: #5c3c1e;">
                            <i class="fa fa-pie-chart me-2" style="color: #5c3c1e;"></i>My Re-entry Visa Status Distribution
                        </h5>
                        <div style="position: relative; height: 250px; width: 100%;">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid #e1e5e9; height: 100%;">
                        <h5 class="fw-bold mb-3" style="font-size: 1.05rem; color: #5c3c1e;">
                            <i class="fa fa-bar-chart me-2" style="color: #5c3c1e;"></i>My Re-entry Visa Issuance (last Month)
                        </h5>
                        <div style="position: relative; height: 250px; width: 100%;">
                            <canvas id="issuanceChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activities - USER'S OWN ACTIVITIES ONLY -->
            <div class="recent-activities" style="margin-top: 3rem !important;">
                <div class="activities-header">
                    <h5 style="color: #5c3c1e;"><i class="fa fa-history" style="color: #5c3c1e;"></i> My Recent Activities</h5>
                    <a href="re-entry-visa" class="view-all-btn"><i class="fa fa-list"></i> View All Applications</a>
                </div>
                
                <?php if (!empty($recent_applications)): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Visa Number</th>
                                    <th>Applicant Name</th>
                                    <th>Passport No.</th>
                                    <th>Status</th>
                                    <th>Date Created</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $counter = 1; ?>
                                <?php foreach ($recent_applications as $app): ?>
                                    <tr>
                                        <td><?php echo $counter++; ?></td>
                                        <td><strong><?php echo htmlspecialchars($app['visa_number'] ?? 'N/A'); ?></strong></td>
                                        <td><?php 
                                            $name = ($app['surname'] ?? '') . ', ' . ($app['given_name'] ?? '');
                                            echo htmlspecialchars(trim($name, ', ')); 
                                        ?></td>
                                        <td><code><?php echo htmlspecialchars($app['passport_number'] ?? 'N/A'); ?></code></td>
                                        <td>
                                            <?php 
                                            $status = $app['status'] ?? 'pending';
                                            $statusClass = 'status-' . strtolower($status);
                                            ?>
                                            <span class="status-badge <?php echo $statusClass; ?>">
                                                <?php echo ucfirst($status); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                                if (!empty($app['application_date'])) {
                                                     echo date('M j, Y', strtotime($app['application_date']));
                                                } else {
                                                    echo 'N/A';
                                                }
                                            ?>
                                        </td>
                                        <td>
                                            <a href="viewrecord?id=<?php echo urlencode($app['visa_number'] ?? ''); ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fa fa-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php elseif (!$hasData): ?>
                    <div class="no-data">
                        <i class="fa fa-inbox"></i>
                        <h5>No Applications Yet</h5>
                        <p class="mb-3">You haven't created any visa applications.</p>
                        <a href="new-record" class="view-all-btn"><i class="fa fa-plus-circle"></i> Create Your First Application</a>
                    </div>
                <?php else: ?>
                    <div class="no-data">
                        <i class="fa fa-inbox"></i>
                        <h5>No Recent Activities</h5>
                        <p class="mb-3">You have no recent activities to display.</p>
                        <a href="new-record" class="view-all-btn"><i class="fa fa-plus-circle"></i> Create New Application</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function updateDateTime() {
            const now = new Date();
            const options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            };
            const dateTimeStr = now.toLocaleDateString('en-US', options);
            document.getElementById('currentDateTime').textContent = dateTimeStr;
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            updateDateTime();
            setInterval(updateDateTime, 60000); // Update every minute
            
            // Status Chart (Pie/Doughnut) - Fresh vs Renewal
            const statusCtx = document.getElementById('statusChart').getContext('2d');
            new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Fresh Applications', 'Renewal Applications'],
                    datasets: [{
                        data: [
                            <?php echo $total_fresh; ?>,
                            <?php echo $total_renewal; ?>
                        ],
                        backgroundColor: ['#16a34a', '#d97706'],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                font: { family: "'Segoe UI', sans-serif", weight: 'bold' },
                                padding: 15
                            }
                        }
                    },
                    cutout: '60%'
                }
            });

            // Issuance Chart (Bar Chart) - Last Month Daily Trends
            const issuanceCtx = document.getElementById('issuanceChart').getContext('2d');
            new Chart(issuanceCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($last_month_labels); ?>,
                    datasets: [{
                        label: 'Visas Created',
                        data: <?php echo json_encode($last_month_counts); ?>,
                        backgroundColor: 'rgba(26, 92, 46, 0.85)',
                        borderColor: '#1a5c2e',
                        borderWidth: 1.5,
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { stepSize: 1 }
                        }
                    }
                }
            });
        });
        
        // Logout is handled globally by sidebar.js/php
    </script>
    <?php 
    if (file_exists('footer.php')) {
        include('footer.php');
    }
    ?>
</body>
</html>




