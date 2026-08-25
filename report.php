<?php
require_once('security.php');
error_reporting(E_ALL);
require_once('db/db.php');

// Check if user is logged in - use the same session variable as process_visa.php
if (!isset($_SESSION['username'])) {
    header("Location: index");
    exit();
}

// Verify role is Admin, Supervisor, or ViewOnly
$userRole = $_SESSION['role'] ?? 'DataEntry';
if ($userRole !== 'Admin' && $userRole !== 'Supervisor' && $userRole !== 'ViewOnly') {
    header("Location: user_dashboard");
    exit();
}



// Get report data based on filters
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // Default to start of current month
$end_date = $_GET['end_date'] ?? date('Y-m-t'); // Default to end of current month
$report_type = $_GET['report_type'] ?? 'monthly';

// Static stats that are not affected by filter
$static_total = 0;
$static_active = 0;
$static_fresh = 0;
$static_renewal = 0;

$filtered_fresh_count = 0;
$filtered_renewal_count = 0;

// Initialize data arrays
$status_data = ['active' => 0, 'pending' => 0];
$monthly_data = [];
$user_stats = [];
$gender_stats = [];
$audit_logs = [];
$filtered_applications = [];

try {
    // First, ensure the visa_applications table exists
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'visa_applications'");
    if (mysqli_num_rows($table_check) == 0) {
        // Table doesn't exist, initialize empty data
        $overall_stats = ['total' => 0, 'approved' => 0, 'pending' => 0, 'rejected' => 0];
    } else {
        // Fetch static counts
        $q_total = mysqli_query($conn, "SELECT COUNT(*) as count FROM visa_applications");
        if ($q_total && $row = mysqli_fetch_assoc($q_total)) {
            $static_total = (int)$row['count'];
        }
        $q_current_month = mysqli_query($conn, "SELECT COUNT(*) as count FROM visa_applications WHERE MONTH(application_date) = MONTH(CURRENT_DATE()) AND YEAR(application_date) = YEAR(CURRENT_DATE())");
        $static_current_month = 0;
        if ($q_current_month && $row = mysqli_fetch_assoc($q_current_month)) {
            $static_current_month = (int)$row['count'];
        }
        $q_fresh = mysqli_query($conn, "SELECT COUNT(*) as count FROM visa_applications WHERE application_type = 'fresh'");
        if ($q_fresh && $row = mysqli_fetch_assoc($q_fresh)) {
            $static_fresh = (int)$row['count'];
        }
        $q_renewal = mysqli_query($conn, "SELECT COUNT(*) as count FROM visa_applications WHERE application_type = 'renewal'");
        if ($q_renewal && $row = mysqli_fetch_assoc($q_renewal)) {
            $static_renewal = (int)$row['count'];
        }

        // Fetch filtered applications list
        $filter_query = "SELECT visa_number, passport_number, surname, given_name, application_type, application_date 
                         FROM visa_applications 
                         WHERE application_date BETWEEN ? AND ? 
                         ORDER BY application_date DESC";
        if ($stmt_filter = mysqli_prepare($conn, $filter_query)) {
            $sd = $start_date . ' 00:00:00';
            $ed = $end_date . ' 23:59:59';
            mysqli_stmt_bind_param($stmt_filter, "ss", $sd, $ed);
            mysqli_stmt_execute($stmt_filter);
            $filter_result = mysqli_stmt_get_result($stmt_filter);
            while ($row = mysqli_fetch_assoc($filter_result)) {
                $filtered_applications[] = $row;
                if (strtolower($row['application_type'] ?? 'fresh') === 'renewal') {
                    $filtered_renewal_count++;
                } else {
                    $filtered_fresh_count++;
                }
            }
            mysqli_stmt_close($stmt_filter);
        }
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

        // Monthly application data (unaffected by filters)
        $monthly_query = "SELECT 
                            MONTH(application_date) as month, 
                            COUNT(*) as count 
                          FROM visa_applications 
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

        // Applications by gender
        $gender_field = 'gender';
        $cols_query = mysqli_query($conn, "SHOW COLUMNS FROM visa_applications");
        if ($cols_query) {
            $db_cols = [];
            while ($c = mysqli_fetch_assoc($cols_query)) {
                $db_cols[] = $c['Field'];
            }
            if (in_array('gender', $db_cols) && in_array('sex', $db_cols)) {
                $gender_field = "COALESCE(NULLIF(TRIM(gender), ''), NULLIF(TRIM(sex), ''))";
            } elseif (in_array('sex', $db_cols)) {
                $gender_field = 'sex';
            }
        }

        $gender_query = "SELECT 
                            CASE 
                                WHEN UPPER(TRIM($gender_field)) IN ('M', 'MALE') THEN 'Male'
                                WHEN UPPER(TRIM($gender_field)) IN ('F', 'FEMALE') THEN 'Female'
                            END as gender,
                            COUNT(*) as count 
                          FROM visa_applications 
                          WHERE $gender_field IS NOT NULL 
                            AND TRIM($gender_field) != ''
                            AND UPPER(TRIM($gender_field)) IN ('M', 'MALE', 'F', 'FEMALE')
                          GROUP BY gender 
                          ORDER BY count DESC";
        $stmt = mysqli_prepare($conn, $gender_query);
        mysqli_stmt_execute($stmt);
        $gender_result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($gender_result)) {
            $gender_stats[] = $row;
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
                            LIMIT 200";
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

        /* Custom Stats Boxes for Filtered Results */
        .filtered-stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            padding: 15px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
        }
        .filtered-stat-box {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 18px;
            border-radius: 8px;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            border: none !important;
        }
        .filtered-stat-box.fresh-box {
            border-left: none !important;
        }
        .filtered-stat-box.renewal-box {
            border-left: none !important;
        }
        .filtered-stat-box.total-box {
            border-left: none !important;
        }
        .stat-icon-wrapper {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }
        .fresh-box .stat-icon-wrapper { background: #f0fdf4; color: #16a34a; }
        .renewal-box .stat-icon-wrapper { background: #fffbeb; color: #d97706; }
        .total-box .stat-icon-wrapper { background: #eff6ff; color: #3b82f6; }
        
        .stat-details {
            display: flex;
            flex-direction: column;
        }
        .stat-val {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1e293b;
            line-height: 1.2;
        }
        .stat-lbl {
            font-size: 0.75rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* Filter Button Custom Styling */
        .btn-filter-green {
            background: linear-gradient(135deg, #16a34a, #15803d) !important;
            border: none !important;
            color: white !important;
            font-weight: 600 !important;
            transition: all 0.3s ease !important;
        }
        .btn-filter-green:hover {
            background: linear-gradient(135deg, #15803d, #166534) !important;
            box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3) !important;
            transform: translateY(-1px);
        }

        /* Audit Table Styles */
        .audit-table thead th {
            background-color: #f8fafc !important;
            color: #475569 !important;
            font-size: 0.8rem !important;
            font-weight: 700 !important;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 2px solid #e2e8f0 !important;
            padding: 14px 16px !important;
        }
        .audit-table tbody td {
            padding: 14px 16px !important;
            border-bottom: 1px solid #f1f5f9 !important;
            font-size: 0.875rem !important;
            color: #334155;
        }
        .audit-table tbody tr:hover {
            background-color: #f8fafc !important;
        }
        .audit-timestamp {
            color: #64748b;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .audit-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }
        .audit-badge.badge-create {
            background: #dcfce7 !important;
            color: #166534 !important;
            border: 1px solid #bbf7d0 !important;
        }
        .audit-badge.badge-update {
            background: #dbeafe !important;
            color: #1e40af !important;
            border: 1px solid #bfdbfe !important;
        }
        .audit-badge.badge-delete {
            background: #fee2e2 !important;
            color: #991b1b !important;
            border: 1px solid #fecaca !important;
        }
        .audit-badge.badge-login {
            background: #fef3c7 !important;
            color: #92400e !important;
            border: 1px solid #fde68a !important;
        }
        .audit-badge.badge-other {
            background: #f1f5f9 !important;
            color: #475569 !important;
            border: 1px solid #e2e8f0 !important;
        }
        .audit-ip {
            background: #f1f5f9;
            color: #64748b;
            font-family: monospace;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.8rem;
            border: 1px solid #e2e8f0;
            display: inline-block;
        }
        .btn-minimize {
            background: #f1f5f9 !important;
            border: 1px solid #cbd5e1 !important;
            color: #475569 !important;
            font-weight: 600 !important;
            font-size: 0.8rem !important;
            padding: 5px 12px !important;
            border-radius: 6px !important;
            transition: all 0.2s ease !important;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        /* Audit trail filters */
        .audit-filter-section {
            background-color: #f8f9fa;
            border: 1px solid #e2dcd0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .audit-filter-section .form-control, .audit-filter-section .form-select {
            border: 1.5px solid #e2dcd0;
            border-radius: 6px;
            height: 38px;
            font-size: 0.88rem;
        }
        .audit-filter-section .form-control:focus, .audit-filter-section .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26, 92, 46, 0.1);
        }
        .audit-filter-section .btn-secondary {
            background-color: #ffffff;
            border: 1.5px solid #e2dcd0;
            color: var(--dark);
            height: 38px;
            font-weight: 600;
            border-radius: 6px;
            transition: all 0.3s;
        }
        .audit-filter-section .btn-secondary:hover {
            background-color: #f5f5f5;
            color: var(--primary);
            border-color: var(--gray);
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
            <!-- Statistics Overview (Moved Up & Static Figures) -->
            <div class="stats-grid" style="margin-bottom: 25px;">
                <div class="stat-card">
                    <i class="fa fa-list-alt text-info" style="font-size: 1.8rem; margin-bottom: 10px; color: var(--info);"></i>
                    <div class="stat-number" style="color: var(--info);"><?php echo number_format($static_total); ?></div>
                    <div class="stat-label">Total Applications</div>
                </div>
                <div class="stat-card">
                    <i class="fa fa-file-o text-success" style="font-size: 1.8rem; margin-bottom: 10px; color: #16a34a;"></i>
                    <div class="stat-number" style="color: #16a34a;"><?php echo number_format($static_fresh); ?></div>
                    <div class="stat-label">Total Fresh Applications</div>
                </div>
                <div class="stat-card">
                    <i class="fa fa-refresh text-warning" style="font-size: 1.8rem; margin-bottom: 10px; color: #d97706;"></i>
                    <div class="stat-number" style="color: #d97706;"><?php echo number_format($static_renewal); ?></div>
                    <div class="stat-label">Total Renewal Applications</div>
                </div>
                <div class="stat-card">
                    <i class="fa fa-calendar text-primary" style="font-size: 1.8rem; margin-bottom: 10px; color: var(--primary);"></i>
                    <div class="stat-number" style="color: var(--primary);"><?php echo number_format($static_current_month); ?></div>
                    <div class="stat-label">Applications This Month</div>
                </div>
            </div>

            <!-- Report Header with Filters (Moved Below Stats Grid) -->
            <div class="report-header" style="margin-bottom: 25px;">
                <div class="row align-items-center">
                    <div class="col-lg-6 col-md-12 mb-3 mb-lg-0">
                        <h4>Filter Applications Report</h4>
                        <p class="mb-0">Select dates to filter applications by month</p>
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
                                    <button type="submit" class="btn btn-filter-green btn-block">
                                        <i class="fa fa-filter"></i> Filter
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Filtered Application Records Section (Placed Under Filters) -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="data-card">
                        <h5 class="d-flex align-items-center mb-3 flex-wrap gap-2">
                            <span><i class="fa fa-list-alt me-2" style="color: var(--primary);"></i> Filtered Application Records (<?php echo htmlspecialchars($start_date); ?> to <?php echo htmlspecialchars($end_date); ?>)</span>
                            <div class="ms-md-auto d-flex gap-2 align-items-center flex-wrap">
                                <button type="button" class="btn btn-sm btn-warning text-white" onclick="printTable('filteredTableContainer')" title="Print Table">
                                    <i class="fa fa-print"></i> Print Table
                                </button>
                                <button type="button" class="btn btn-minimize" onclick="toggleFilterTable()" id="toggleTableBtn">
                                    <i class="fa fa-minus"></i> Minimize
                                </button>
                            </div>
                        </h5>
                        <div id="filteredTableContainer">
                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                <table id="filteredTable" class="table table-hover table-striped">
                                    <thead style="position: sticky; top: 0; background: white; z-index: 1;">
                                        <tr>
                                            <th>S/No</th>
                                            <th>Application Date</th>
                                            <th>Re-Entry Visa Number</th>
                                            <th>Applicant Name</th>
                                            <th>Passport Number</th>
                                            <th>Type of Application</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($filtered_applications)): ?>
                                            <?php 
                                            $f_sno = 1;
                                            foreach ($filtered_applications as $f_app): 
                                            ?>
                                                <tr>
                                                    <td><?php echo $f_sno++; ?></td>
                                                    <td class="text-nowrap small">
                                                        <?php echo date('M j, Y g:i A', strtotime($f_app['application_date'])); ?>
                                                    </td>
                                                    <td class="font-monospace fw-bold">
                                                        <?php echo htmlspecialchars($f_app['visa_number']); ?>
                                                    </td>
                                                    <td>
                                                        <?php echo htmlspecialchars(strtoupper($f_app['surname'] . ' ' . $f_app['given_name'])); ?>
                                                    </td>
                                                    <td>
                                                        <code><?php echo htmlspecialchars($f_app['passport_number']); ?></code>
                                                    </td>
                                                    <td>
                                                         <?php 
                                                             $f_type = strtolower($f_app['application_type'] ?? 'fresh');
                                                             if ($f_type === 'renewal') {
                                                                 echo '<span style="font-size: 0.85rem; font-weight: 600;">RENEWAL</span>';
                                                             } else {
                                                                 echo '<span style="font-size: 0.85rem; font-weight: 600;">FRESH</span>';
                                                             }
                                                         ?>
                                                     </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center text-muted">No records found for the selected period.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="filtered-stats-container mt-4 mb-2">
                                <div class="filtered-stat-box fresh-box">
                                    <div class="stat-icon-wrapper"><i class="fa fa-file-text"></i></div>
                                    <div class="stat-details">
                                        <span class="stat-val"><?php echo $filtered_fresh_count; ?></span>
                                        <span class="stat-lbl">Filtered Fresh</span>
                                    </div>
                                </div>
                                <div class="filtered-stat-box renewal-box">
                                    <div class="stat-icon-wrapper"><i class="fa fa-refresh"></i></div>
                                    <div class="stat-details">
                                        <span class="stat-val"><?php echo $filtered_renewal_count; ?></span>
                                        <span class="stat-lbl">Filtered Renewal</span>
                                    </div>
                                </div>
                                <div class="filtered-stat-box total-box">
                                    <div class="stat-icon-wrapper"><i class="fa fa-list"></i></div>
                                    <div class="stat-details">
                                        <span class="stat-val"><?php echo count($filtered_applications); ?></span>
                                        <span class="stat-lbl">Total Filtered</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="charts-grid">
                <!-- Status Distribution Pie Chart -->
                <div class="chart-card">
                    <h5><i class="fa fa-pie-chart" style="color: var(--primary);"></i> Application Status Distribution (Fresh vs Renewal)</h5>
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

                <!-- Gender Distribution Bar Chart -->
                <div class="chart-card">
                    <h5><i class="fa fa-venus-mars" style="color: var(--warning);"></i> Applications by Gender</h5>
                    <div class="chart-container">
                        <canvas id="genderChart"></canvas>
                    </div>
                </div>
            </div>



            <!-- System Audit Trail Section -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="data-card">
                        <h5><i class="fa fa-history" style="color: var(--primary);"></i> System Audit Trail</h5>
                        
                        <!-- Interactive Audit Trail Filters -->
                        <div class="audit-filter-section">
                            <div class="row g-2 align-items-center">
                                <div class="col-md-4">
                                    <input type="text" id="auditSearchInput" class="form-control" placeholder="Search description...">
                                </div>
                                <div class="col-md-3">
                                    <select id="auditFilterOperator" class="form-select">
                                        <option value="">All Operators</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <select id="auditFilterAction" class="form-select">
                                        <option value="">All Actions</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button id="resetAuditFilters" class="btn btn-secondary w-100">
                                        <i class="fa fa-refresh"></i> Reset
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-hover table-striped audit-table">
                                <thead style="position: sticky; top: 0; background: white; z-index: 1;">
                                    <tr>
                                        <th>S/No</th>
                                        <th>Timestamp</th>
                                        <th>Operator</th>
                                        <th>Action</th>
                                        <th>Description</th>
                                        <th>IP Address</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($audit_logs)): ?>
                                        <?php 
                                        $a_sno = 1;
                                        foreach ($audit_logs as $log): 
                                        ?>
                                            <tr>
                                                <td><?php echo $a_sno++; ?></td>
                                                <td>
                                                    <div class="audit-timestamp">
                                                        <i class="fa fa-clock-o"></i> <?php echo htmlspecialchars($log['created_at']); ?>
                                                    </div>
                                                </td>
                                                <td class="font-monospace fw-bold">
                                                    <?php echo htmlspecialchars($log['user_id']); ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                        $action = strtoupper($log['action']);
                                                        $badge_class = 'badge-other';
                                                        if (strpos($action, 'CREATE') !== false) {
                                                            $badge_class = 'badge-create';
                                                        } elseif (strpos($action, 'UPDATE') !== false || strpos($action, 'EDIT') !== false) {
                                                            $badge_class = 'badge-update';
                                                        } elseif (strpos($action, 'DELETE') !== false || strpos($action, 'REMOVE') !== false) {
                                                            $badge_class = 'badge-delete';
                                                        } elseif (strpos($action, 'LOGIN') !== false) {
                                                            $badge_class = 'badge-login';
                                                        }
                                                    ?>
                                                    <span class="audit-badge <?php echo $badge_class; ?>">
                                                        <?php echo htmlspecialchars($action); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($log['description']); ?>
                                                </td>
                                                <td>
                                                    <span class="audit-ip"><?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted">No audit trail logs recorded for this period.</td>
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
                labels: ['Fresh Applications', 'Renewal Applications'],
                datasets: [{
                    data: [
                        <?php echo $static_fresh; ?>,
                        <?php echo $static_renewal; ?>
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

        // Gender Distribution Pie Chart
        const genderCtx = document.getElementById('genderChart').getContext('2d');
        const genderChart = new Chart(genderCtx, {
            type: 'pie',
            data: {
                labels: <?php echo !empty($gender_stats) ? json_encode(array_column($gender_stats, 'gender')) : json_encode([]); ?>,
                datasets: [{
                    label: 'Applications',
                    data: <?php echo !empty($gender_stats) ? json_encode(array_column($gender_stats, 'count')) : json_encode([]); ?>,
                    backgroundColor: [
                        '#3498db', '#f43f5e', '#64748b'
                    ],
                    borderColor: '#ffffff',
                    borderWidth: 1.5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });

        function toggleFilterTable() {
            const container = document.getElementById('filteredTableContainer');
            const btn = document.getElementById('toggleTableBtn');
            if (container.style.display === 'none') {
                container.style.display = 'block';
                btn.innerHTML = '<i class="fa fa-minus"></i> Minimize';
            } else {
                container.style.display = 'none';
                btn.innerHTML = '<i class="fa fa-plus"></i> Maximize';
            }
        }

        function printReport() {
            window.print();
        }

        function printTable(tableContainerId) {
            const container = document.getElementById(tableContainerId);
            if (!container) {
                alert('No table container found to print');
                return;
            }
            const tableContent = container.innerHTML;
            
            const printWindow = window.open('', '', 'height=600,width=800');
            printWindow.document.write('<html><head><title>Print Filtered Application Records</title>');
            printWindow.document.write('<link href="vendors/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">');
            printWindow.document.write('<style>');
            printWindow.document.write('body{padding:30px; font-family:sans-serif; color:#333;}');
            printWindow.document.write('table{width:100%; border-collapse:collapse; margin-top:20px;}');
            printWindow.document.write('th,td{border:1px solid #e2e8f0; padding:12px; text-align:left; font-size:14px;}');
            printWindow.document.write('th{background-color:#f8fafc; color:#475569; font-weight:bold; text-transform:uppercase; font-size:12px; letter-spacing:0.05em;}');
            printWindow.document.write('.text-nowrap{white-space:nowrap;} .small{font-size:12px;}');
            printWindow.document.write('h2{color:#1a5c2e; margin-bottom:5px;} h5{color:#64748b; margin-top:0; font-weight:normal;}');
            printWindow.document.write('.table-responsive { overflow: visible !important; max-height: none !important; }');
            printWindow.document.write('</style></head><body>');
            printWindow.document.write('<h2>Filtered Application Records</h2>');
            printWindow.document.write('<h5>Report Period: <?php echo htmlspecialchars($start_date); ?> to <?php echo htmlspecialchars($end_date); ?></h5>');
            printWindow.document.write(tableContent);
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            
            printWindow.focus();
            setTimeout(function() {
                printWindow.print();
                printWindow.close();
            }, 500);
        }
        

        
        // System Audit Trail filter logic
        document.addEventListener('DOMContentLoaded', function() {
            const table = document.querySelector('.audit-table');
            if (!table) return;
            const tbody = table.querySelector('tbody');
            if (!tbody) return;
            const rows = tbody.querySelectorAll('tr');

            const searchInput = document.getElementById('auditSearchInput');
            const filterOperator = document.getElementById('auditFilterOperator');
            const filterAction = document.getElementById('auditFilterAction');
            const resetBtn = document.getElementById('resetAuditFilters');

            // Dynamically populate Operator and Action options
            const operators = new Set();
            const actions = new Set();

            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length >= 6) {
                    const operatorText = cells[2].textContent.trim();
                    const actionBadge = cells[3].querySelector('.audit-badge');
                    const actionText = actionBadge ? actionBadge.textContent.trim() : cells[3].textContent.trim();

                    if (operatorText) operators.add(operatorText);
                    if (actionText) actions.add(actionText);
                }
            });

            if (filterOperator) {
                Array.from(operators).sort().forEach(op => {
                    const opt = document.createElement('option');
                    opt.value = op;
                    opt.textContent = op;
                    filterOperator.appendChild(opt);
                });
            }

            if (filterAction) {
                Array.from(actions).sort().forEach(act => {
                    const opt = document.createElement('option');
                    opt.value = act;
                    opt.textContent = act;
                    filterAction.appendChild(opt);
                });
            }

            function applyAuditFilters() {
                const searchVal = searchInput ? searchInput.value.toLowerCase().trim() : '';
                const operatorVal = filterOperator ? filterOperator.value.toLowerCase() : '';
                const actionVal = filterAction ? filterAction.value.toLowerCase() : '';

                rows.forEach(row => {
                    const cells = row.querySelectorAll('td');
                    if (cells.length < 6) return;

                    const operatorText = cells[2].textContent.toLowerCase().trim();
                    const actionBadge = cells[3].querySelector('.audit-badge');
                    const actionText = (actionBadge ? actionBadge.textContent : cells[3].textContent).toLowerCase().trim();
                    const descriptionText = cells[4].textContent.toLowerCase().trim();

                    let matchSearch = true;
                    let matchOperator = true;
                    let matchAction = true;

                    if (searchVal && !descriptionText.includes(searchVal)) {
                        matchSearch = false;
                    }
                    if (operatorVal && operatorText !== operatorVal) {
                        matchOperator = false;
                    }
                    if (actionVal && actionText !== actionVal) {
                        matchAction = false;
                    }

                    if (matchSearch && matchOperator && matchAction) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            }

            if (searchInput) searchInput.addEventListener('input', applyAuditFilters);
            if (filterOperator) filterOperator.addEventListener('change', applyAuditFilters);
            if (filterAction) filterAction.addEventListener('change', applyAuditFilters);
            if (resetBtn) {
                resetBtn.addEventListener('click', function() {
                    if (searchInput) searchInput.value = '';
                    if (filterOperator) filterOperator.value = '';
                    if (filterAction) filterAction.value = '';
                    applyAuditFilters();
                });
            }
        });
    </script>
    <?php 
    if (file_exists('footer.php')) {
        include('footer.php');
    }
    ?>
</body>
</html>




