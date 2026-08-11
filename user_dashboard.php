<?php
require_once('security.php');
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once('db/db.php');



// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index");
    exit();
}

// Verify this is a regular user (not Admin/Supervisor)
$userRole = $_SESSION['role'] ?? 'DataEntry';
if ($userRole === 'Admin' || $userRole === 'Supervisor') {
    // Admin/Supervisor should use the main dashboard
    header("Location: index");
    exit();
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';
$fullName = $_SESSION['full_name'] ?? $username;

// Fetch user-specific data only
$numb = 0;
$stats = [
    'monthly_visas' => 0,
    'pending_visas' => 0,
    'recent_visas' => 0,
    'today_visas' => 0
];
$recent_applications = [];

try {
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'visa_applications'");
    
    if ($table_check && mysqli_num_rows($table_check) > 0) {
        // User's total applications
        $sqluse = "SELECT COUNT(*) as total FROM visa_applications WHERE created_by = '$username'";
        $retrieve = mysqli_query($conn, $sqluse);
        if ($retrieve && mysqli_num_rows($retrieve) > 0) {
            $total_result = mysqli_fetch_assoc($retrieve);
            $numb = $total_result['total'] ?? 0;
        }
        
        // User's monthly applications
        $query = "SELECT COUNT(*) as monthly_visas FROM visa_applications 
                 WHERE MONTH(application_date) = MONTH(CURRENT_DATE()) 
                 AND YEAR(application_date) = YEAR(CURRENT_DATE())
                 AND created_by = '$username'";
        $result = mysqli_query($conn, $query);
        if ($result && mysqli_num_rows($result) > 0) {
            $monthly_data = mysqli_fetch_assoc($result);
            $stats['monthly_visas'] = $monthly_data['monthly_visas'] ?? 0;
        }
        
        // User's pending applications
        $query = "SELECT COUNT(*) as pending_visas FROM visa_applications 
                 WHERE status = 'pending' AND created_by = '$username'";
        $result = mysqli_query($conn, $query);
        if ($result && mysqli_num_rows($result) > 0) {
            $pending_data = mysqli_fetch_assoc($result);
            $stats['pending_visas'] = $pending_data['pending_visas'] ?? 0;
        }
        
        // User's recent applications (last 7 days)
        $query2 = "SELECT COUNT(*) as recent_visas FROM visa_applications 
                  WHERE DATE(application_date) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                  AND created_by = '$username'";
        $result2 = mysqli_query($conn, $query2);
        if ($result2 && mysqli_num_rows($result2) > 0) {
            $recent_data = mysqli_fetch_assoc($result2);
            $stats['recent_visas'] = $recent_data['recent_visas'] ?? 0;
        }
        
        // User's today applications
        $query3 = "SELECT COUNT(*) as today_visas FROM visa_applications 
                  WHERE DATE(application_date) = CURDATE()
                  AND created_by = '$username'";
        $result3 = mysqli_query($conn, $query3);
        if ($result3 && mysqli_num_rows($result3) > 0) {
            $today_data = mysqli_fetch_assoc($result3);
            $stats['today_visas'] = $today_data['today_visas'] ?? 0;
        }
        
        // User's recent applications details (only their own)
        $query4 = "SELECT visa_number, surname, given_name, passport_number, application_date, status 
                  FROM visa_applications 
                  WHERE created_by = '$username'
                  ORDER BY application_date DESC 
                  LIMIT 10";
        $result4 = mysqli_query($conn, $query4);
        if ($result4 && mysqli_num_rows($result4) > 0) {
            while ($row = mysqli_fetch_assoc($result4)) {
                $recent_applications[] = $row;
            }
        }
        
        // Get user's performance data (last 30 days)
        $performance_query = "SELECT 
            DATE(application_date) as date,
            COUNT(*) as count
            FROM visa_applications 
            WHERE created_by = '$username'
            AND application_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY DATE(application_date)
            ORDER BY date ASC";
        $performance_result = mysqli_query($conn, $performance_query);
        $performance_data = [];
        if ($performance_result && mysqli_num_rows($performance_result) > 0) {
            while ($row = mysqli_fetch_assoc($performance_result)) {
                $performance_data[] = $row;
            }
        }

        // Query user status distribution (only their own applications)
        $status_data = ['ACTIVE' => 0, 'PENDING' => 0, 'EXPIRED' => 0];
        $status_query = "SELECT status, COUNT(*) as count FROM visa_applications WHERE created_by = '$username' GROUP BY status";
        $status_result = mysqli_query($conn, $status_query);
        if ($status_result && mysqli_num_rows($status_result) > 0) {
            while ($row = mysqli_fetch_assoc($status_result)) {
                $status_data[strtoupper($row['status'])] = (int)$row['count'];
            }
        }

        // Query user monthly distribution for the last 6 months
        $monthly_labels = [];
        $monthly_counts = [];
        $month_query = "SELECT 
                            DATE_FORMAT(application_date, '%b %Y') as month, 
                            COUNT(*) as count 
                        FROM visa_applications 
                        WHERE created_by = '$username'
                        AND application_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                        GROUP BY YEAR(application_date), MONTH(application_date)
                        ORDER BY YEAR(application_date) ASC, MONTH(application_date) ASC";
        $month_result = mysqli_query($conn, $month_query);
        if ($month_result && mysqli_num_rows($month_result) > 0) {
            while ($row = mysqli_fetch_assoc($month_result)) {
                $monthly_labels[] = strtoupper($row['month']);
                $monthly_counts[] = (int)$row['count'];
            }
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
        
        /* Dashboard Content */
        .dashboard-content {
            padding: 20px;
        }
        
        /* Welcome Banner - SIMPLIFIED FOR USERS */
        .welcome-banner {
            background: linear-gradient(135deg, var(--primary), #2e7d46);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 8px 25px rgba(26, 92, 46, 0.2);
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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .user-stat-card {
            background: white;
            padding: 18px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
            text-align: center;
            border: 1px solid #e1e5e9;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .user-stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }
        
        .user-stat-icon {
            font-size: 1.8rem;
            margin-bottom: 12px;
            opacity: 0.9;
        }
        
        .user-stat-number {
            font-size: 1.7rem;
            font-weight: 700;
            margin: 8px 0;
            color: var(--dark);
            line-height: 1;
        }
        
        .user-stat-card.success .user-stat-number { color: var(--secondary); }
        .user-stat-card.warning .user-stat-number { color: var(--warning); }
        .user-stat-card.info .user-stat-number { color: var(--info); }
        .user-stat-card.danger .user-stat-number { color: var(--danger); }
        
        .user-stat-label {
            color: var(--gray);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
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
            background: linear-gradient(135deg, var(--primary), #2e7d46);
            color: white;
            padding: 6px 15px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .view-all-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(26, 92, 46, 0.3);
            color: white;
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
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.8rem;
            box-shadow: 0 4px 15px rgba(26, 92, 46, 0.3);
            flex-shrink: 0;
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
                    <div class="col-lg-8 col-md-7">
                        <div class="welcome-text">
                            <h2>Welcome, <?php echo htmlspecialchars(explode(' ', $fullName)[0]); ?>! 👋</h2>
                            <p>Track your visa applications and performance</p>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-5">
                        <div class="datetime-display">
                            <div id="currentDateTime"></div>
                            <div class="location"><?php echo date('l, F j, Y'); ?></div>
                        </div>
                    </div>
                </div>
            </div>



            <!-- My Statistics - ONLY USER'S DATA -->
            <div class="stats-grid">
                <div class="user-stat-card">
                    <i class="fa fa-file-alt user-stat-icon" style="color: var(--primary);"></i>
                    <div class="user-stat-number"><?php echo number_format($numb); ?></div>
                    <div class="user-stat-label">Total Applications</div>
                    <small class="text-muted">Your total visa applications</small>
                </div>
                <div class="user-stat-card success">
                    <i class="fa fa-calendar-check user-stat-icon" style="color: var(--secondary);"></i>
                    <div class="user-stat-number"><?php echo number_format($stats['today_visas']); ?></div>
                    <div class="user-stat-label">Today</div>
                    <small class="text-muted">Applications created today</small>
                </div>
                <div class="user-stat-card warning">
                    <i class="fa fa-clock-o user-stat-icon" style="color: var(--warning);"></i>
                    <div class="user-stat-number"><?php echo number_format($stats['pending_visas']); ?></div>
                    <div class="user-stat-label">Pending</div>
                    <small class="text-muted">Awaiting processing</small>
                </div>
                <div class="user-stat-card info">
                    <i class="fa fa-chart-line user-stat-icon" style="color: var(--info);"></i>
                    <div class="user-stat-number"><?php echo number_format($stats['monthly_visas']); ?></div>
                    <div class="user-stat-label">This Month</div>
                    <small class="text-muted">Monthly performance</small>
                </div>
            </div>

            <!-- My Visualizations & Performance Metrics -->
            <div class="row mb-4">
                <div class="col-md-6 mb-3">
                    <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid #e1e5e9; height: 100%;">
                        <h5 class="fw-bold text-primary mb-3" style="font-size: 1.1rem;">
                            <i class="fa fa-pie-chart me-2" style="color: var(--secondary);"></i>My Visa Status Distribution
                        </h5>
                        <div style="position: relative; height: 250px; width: 100%;">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid #e1e5e9; height: 100%;">
                        <h5 class="fw-bold text-primary mb-3" style="font-size: 1.1rem;">
                            <i class="fa fa-bar-chart me-2" style="color: var(--info);"></i>My Visa Issuance (Last 6 Months)
                        </h5>
                        <div style="position: relative; height: 250px; width: 100%;">
                            <canvas id="issuanceChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activities - USER'S OWN ACTIVITIES ONLY -->
            <div class="recent-activities">
                <div class="activities-header">
                    <h5><i class="fa fa-history" style="color: var(--primary);"></i> My Recent Activities</h5>
                    <a href="re-entry-visa" class="view-all-btn">View All Applications</a>
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
                        <a href="new-record" class="view-all-btn">Create Your First Application</a>
                    </div>
                <?php else: ?>
                    <div class="no-data">
                        <i class="fa fa-inbox"></i>
                        <h5>No Recent Activities</h5>
                        <p class="mb-3">You have no recent activities to display.</p>
                        <a href="new-record" class="view-all-btn">Create New Application</a>
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
            

            
            // Status Chart (Pie/Doughnut) - Scoped to User
            const statusCtx = document.getElementById('statusChart').getContext('2d');
            new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: ['ACTIVE', 'PENDING', 'EXPIRED'],
                    datasets: [{
                        data: [
                            <?php echo $status_data['ACTIVE']; ?>,
                            <?php echo $status_data['PENDING']; ?>,
                            <?php echo $status_data['EXPIRED']; ?>
                        ],
                        backgroundColor: ['#2e7d32', '#f9a825', '#c62828'],
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

            // Issuance Chart (Bar Chart) - Scoped to User
            const issuanceCtx = document.getElementById('issuanceChart').getContext('2d');
            new Chart(issuanceCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($monthly_labels); ?>,
                    datasets: [{
                        label: 'Visas Issued',
                        data: <?php echo json_encode($monthly_counts); ?>,
                        backgroundColor: 'rgba(92, 60, 30, 0.85)',
                        borderColor: '#5c3c1e',
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
</body>
</html>




