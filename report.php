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

$sd = $start_date . ' 00:00:00';
$ed = $end_date . ' 23:59:59';

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
        $q_fresh = mysqli_query($conn, "SELECT COUNT(*) as count FROM visa_applications WHERE LOWER(application_type) = 'fresh'");
        if ($q_fresh && $row = mysqli_fetch_assoc($q_fresh)) {
            $static_fresh = (int)$row['count'];
        }
        $q_renewal = mysqli_query($conn, "SELECT COUNT(*) as count FROM visa_applications WHERE LOWER(application_type) = 'renewal'");
        if ($q_renewal && $row = mysqli_fetch_assoc($q_renewal)) {
            $static_renewal = (int)$row['count'];
        }

        // Fetch filtered applications list
        $filter_query = "SELECT visa_number, passport_number, surname, given_name, application_type, application_date 
                         FROM visa_applications 
                         WHERE application_date BETWEEN ? AND ? 
                         ORDER BY application_date DESC";
        if ($stmt_filter = mysqli_prepare($conn, $filter_query)) {
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
        // Status distribution data
        $status_query = "SELECT status, COUNT(*) as count FROM visa_applications 
                        WHERE application_date BETWEEN ? AND ? 
                        GROUP BY status";
        if ($stmt = mysqli_prepare($conn, $status_query)) {
            mysqli_stmt_bind_param($stmt, "ss", $sd, $ed);
            mysqli_stmt_execute($stmt);
            $status_result = mysqli_stmt_get_result($stmt);
            
            $all_statuses = ['active', 'pending'];
            foreach ($all_statuses as $status) {
                $status_data[$status] = 0;
            }
            
            while ($row = mysqli_fetch_assoc($status_result)) {
                $status_data[$row['status']] = (int)$row['count'];
            }
            mysqli_stmt_close($stmt);
        }

        // Monthly application data for the selected report year
        $report_year = (int)date('Y', strtotime($start_date));
        $monthly_query = "SELECT 
                            MONTH(application_date) as month, 
                            COUNT(*) as count 
                          FROM visa_applications 
                          WHERE YEAR(application_date) = ?
                          GROUP BY MONTH(application_date) 
                          ORDER BY month";
        if ($stmt_m = mysqli_prepare($conn, $monthly_query)) {
            mysqli_stmt_bind_param($stmt_m, "i", $report_year);
            mysqli_stmt_execute($stmt_m);
            $monthly_result = mysqli_stmt_get_result($stmt_m);
            
            $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            foreach ($months as $month) {
                $monthly_data[$month] = 0;
            }
            
            while ($row = mysqli_fetch_assoc($monthly_result)) {
                $month_name = $months[$row['month'] - 1];
                $monthly_data[$month_name] = (int)$row['count'];
            }
            mysqli_stmt_close($stmt_m);
        }

        // User statistics
        $user_query = "SELECT 
                        created_by as username,
                        COUNT(id) as applications,
                        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as approved
                      FROM visa_applications 
                      WHERE application_date BETWEEN ? AND ?
                      GROUP BY created_by 
                      ORDER BY applications DESC 
                      LIMIT 10";
        if ($stmt = mysqli_prepare($conn, $user_query)) {
            mysqli_stmt_bind_param($stmt, "ss", $sd, $ed);
            mysqli_stmt_execute($stmt);
            $user_result = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($user_result)) {
                $user_stats[] = $row;
            }
            mysqli_stmt_close($stmt);
        }

        // Applications by gender in the filtered date range
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
                                ELSE 'Other'
                            END as gender,
                            COUNT(*) as count 
                          FROM visa_applications 
                          WHERE application_date BETWEEN ? AND ?
                            AND $gender_field IS NOT NULL 
                            AND TRIM($gender_field) != ''
                            AND UPPER(TRIM($gender_field)) IN ('M', 'MALE', 'F', 'FEMALE')
                          GROUP BY gender 
                          ORDER BY count DESC";
        if ($stmt = mysqli_prepare($conn, $gender_query)) {
            mysqli_stmt_bind_param($stmt, "ss", $sd, $ed);
            mysqli_stmt_execute($stmt);
            $gender_result = mysqli_stmt_get_result($stmt);
            
            while ($row = mysqli_fetch_assoc($gender_result)) {
                $gender_stats[] = $row;
            }
            mysqli_stmt_close($stmt);
        }

        // Overall statistics
        $total_query = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as approved,
                        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
                      FROM visa_applications 
                      WHERE application_date BETWEEN ? AND ?";
        if ($stmt = mysqli_prepare($conn, $total_query)) {
            mysqli_stmt_bind_param($stmt, "ss", $sd, $ed);
            mysqli_stmt_execute($stmt);
            $total_result = mysqli_stmt_get_result($stmt);
            $overall_stats = mysqli_fetch_assoc($total_result);
            $overall_stats['rejected'] = 0;
            mysqli_stmt_close($stmt);
        }

        // Fetch Audit Logs (Activity Trail) if table exists
        $log_table_check = mysqli_query($conn, "SHOW TABLES LIKE 'activity_log'");
        if (mysqli_num_rows($log_table_check) > 0) {
            $audit_query = "SELECT id, user_id, action, description, ip_address, user_agent, created_at 
                            FROM activity_log 
                            WHERE created_at BETWEEN ? AND ? 
                            ORDER BY id DESC 
                            LIMIT 50000";
            if ($stmt_audit = mysqli_prepare($conn, $audit_query)) {
                mysqli_stmt_bind_param($stmt_audit, "ss", $sd, $ed);
                mysqli_stmt_execute($stmt_audit);
                $audit_result = mysqli_stmt_get_result($stmt_audit);
                while ($row = mysqli_fetch_assoc($audit_result)) {
                    $raw_ip = trim($row['ip_address'] ?? '');
                    if (empty($raw_ip) || $raw_ip === '::1' || $raw_ip === '0:0:0:0:0:0:0:1') {
                        $row['display_ip'] = '127.0.0.1';
                        $row['is_local'] = true;
                    } else {
                        $row['display_ip'] = $raw_ip;
                        $row['is_local'] = ($raw_ip === '127.0.0.1' || $raw_ip === 'localhost');
                    }
                    $row['ua_info'] = parseUserAgent($row['user_agent'] ?? '');
                    $audit_logs[] = $row;
                }
                mysqli_stmt_close($stmt_audit);
            }
        }
        
        // Calculate Audit & Officer Productivity Statistics
        $audit_total_count = count($audit_logs);
        $audit_operators = array_unique(array_filter(array_column($audit_logs, 'user_id')));
        $audit_distinct_operators = count($audit_operators);
        $audit_logins_count = 0;
        $audit_security_count = 0;
        $audit_visa_ops_count = 0;
        $distinct_ips = [];

        foreach ($audit_logs as $log) {
            $act = strtoupper($log['action'] ?? '');
            if (strpos($act, 'LOGIN') !== false) {
                $audit_logins_count++;
            }
            if (strpos($act, 'RESET') !== false || strpos($act, 'PASSWORD') !== false || strpos($act, 'MFA') !== false || strpos($act, 'FAILED') !== false) {
                $audit_security_count++;
            }
            if ($act === 'CREATE' || strpos($act, 'EDIT') !== false || strpos($act, 'UPDATE') !== false || strpos($act, 'VIEW') !== false || strpos($act, 'PRINT') !== false) {
                $audit_visa_ops_count++;
            }
            if (!empty($log['display_ip'])) {
                $distinct_ips[$log['display_ip']] = true;
            }
        }

        // Compute exact ground-truth Officer Productivity from visa_applications and activity_log
        $officer_activity_summary = [];

        // 1. Applications created per user in date range
        $q_created = mysqli_query($conn, "SELECT created_by, COUNT(*) as cnt, MAX(application_date) as last_created 
                                          FROM visa_applications 
                                          WHERE application_date BETWEEN '$sd' AND '$ed' 
                                            AND created_by IS NOT NULL AND created_by != '' 
                                          GROUP BY created_by");
        if ($q_created) {
            while ($r = mysqli_fetch_assoc($q_created)) {
                $op = $r['created_by'];
                if (!isset($officer_activity_summary[$op])) {
                    $officer_activity_summary[$op] = [
                        'operator' => $op,
                        'total' => 0,
                        'creates' => 0,
                        'edits' => 0,
                        'prints' => 0,
                        'logins' => 0,
                        'last_seen' => $r['last_created']
                    ];
                }
                $officer_activity_summary[$op]['creates'] = (int)$r['cnt'];
            }
        }

        // 2. Visas printed per user in date range (matching print_visas.php ground truth)
        $q_printed = mysqli_query($conn, "SELECT printed_by, COUNT(*) as cnt, MAX(printed_at) as last_printed 
                                          FROM visa_applications 
                                          WHERE print_status = 1 
                                            AND printed_by IS NOT NULL AND printed_by != '' 
                                            AND printed_at BETWEEN '$sd' AND '$ed' 
                                          GROUP BY printed_by");
        if ($q_printed) {
            while ($r = mysqli_fetch_assoc($q_printed)) {
                $op = $r['printed_by'];
                if (!isset($officer_activity_summary[$op])) {
                    $officer_activity_summary[$op] = [
                        'operator' => $op,
                        'total' => 0,
                        'creates' => 0,
                        'edits' => 0,
                        'prints' => 0,
                        'logins' => 0,
                        'last_seen' => $r['last_printed']
                    ];
                }
                $officer_activity_summary[$op]['prints'] = (int)$r['cnt'];
                if (!empty($r['last_printed']) && (empty($officer_activity_summary[$op]['last_seen']) || $r['last_printed'] > $officer_activity_summary[$op]['last_seen'])) {
                    $officer_activity_summary[$op]['last_seen'] = $r['last_printed'];
                }
            }
        }

        // 3. Edits, Logins and latest activity from activity_log
        foreach ($audit_logs as $log) {
            $op = $log['user_id'] ?: 'SYSTEM';
            $act = strtoupper($log['action'] ?? '');
            
            if (!isset($officer_activity_summary[$op])) {
                $officer_activity_summary[$op] = [
                    'operator' => $op,
                    'total' => 0,
                    'creates' => 0,
                    'edits' => 0,
                    'prints' => 0,
                    'logins' => 0,
                    'last_seen' => $log['created_at']
                ];
            }

            if (strpos($act, 'EDIT') !== false || (strpos($act, 'UPDATE') !== false && strpos($act, 'USER') === false)) {
                $officer_activity_summary[$op]['edits']++;
            } elseif (strpos($act, 'LOGIN') !== false) {
                $officer_activity_summary[$op]['logins']++;
            }

            if (!empty($log['created_at']) && (empty($officer_activity_summary[$op]['last_seen']) || $log['created_at'] > $officer_activity_summary[$op]['last_seen'])) {
                $officer_activity_summary[$op]['last_seen'] = $log['created_at'];
            }
        }

        // Calculate total actions for each officer
        foreach ($officer_activity_summary as $op => &$stats_item) {
            $stats_item['total'] = $stats_item['creates'] + $stats_item['edits'] + $stats_item['prints'] + $stats_item['logins'];
        }
        unset($stats_item);

        uasort($officer_activity_summary, function($a, $b) {
            return $b['total'] <=> $a['total'];
        });
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
            font-size: 0.95rem;
            line-height: 1.2;
        }
        
        .user-role {
            font-size: 0.75rem;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 500;
        }
        
        .logout-btn {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
            border: 1px solid rgba(220, 53, 69, 0.2);
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .logout-btn:hover {
            background: var(--danger);
            color: white;
            transform: translateY(-1px);
        }
        
        .page-content {
            padding: 20px;
            min-height: calc(100vh - 64px);
        }

        /* Professional Compact Filter Header */
        .report-header {
            background: #ffffff;
            padding: 16px 20px;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 22px;
        }

        .report-header-flex {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 14px;
        }

        .report-header-title-box h4 {
            color: #1a5c2e;
            font-weight: 700;
            font-size: 1.12rem;
            margin-bottom: 3px;
            display: flex;
            align-items: center;
        }

        .report-header-title-box p {
            color: #64748b;
            font-size: 0.82rem;
            margin-bottom: 0;
        }

        .filter-compact-form {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 8px;
        }

        .filter-inputs-row {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: nowrap;
        }

        .filter-date-field {
            display: flex;
            align-items: center;
            background: #ffffff;
            border: 1.5px solid #cbd5e1;
            border-radius: 6px;
            overflow: hidden;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .filter-date-field:focus-within {
            border-color: #1a5c2e;
            box-shadow: 0 0 0 3px rgba(26, 92, 46, 0.12);
        }

        .filter-date-tag {
            background: #f1f5f9;
            color: #475569;
            font-size: 0.76rem;
            font-weight: 700;
            padding: 6px 9px;
            border-right: 1px solid #cbd5e1;
            user-select: none;
            white-space: nowrap;
            text-transform: uppercase;
        }

        .filter-date-control {
            border: none !important;
            box-shadow: none !important;
            padding: 4px 10px !important;
            font-size: 0.86rem !important;
            font-weight: 600 !important;
            color: #1e293b !important;
            background: transparent !important;
            height: 34px !important;
            outline: none !important;
            width: 140px;
        }

        .btn-filter-compact {
            height: 34px !important;
            padding: 0 16px !important;
            font-size: 0.84rem !important;
            font-weight: 700 !important;
            border-radius: 6px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 6px !important;
            white-space: nowrap !important;
            cursor: pointer;
            box-shadow: 0 2px 6px rgba(26, 92, 46, 0.2);
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
            .btn-minimize,
            .audit-filter-section,
            .pagination-container,
            .table-pagination-nav,
            .main-footer {
                display: none !important;
            }
            
            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }
            
            .page-content {
                padding: 10px 0 !important;
            }
            
            .chart-card,
            .data-card,
            .stat-card {
                box-shadow: none !important;
                border: 1px solid #cbd5e1 !important;
                page-break-inside: avoid;
            }
            
            .table-responsive {
                max-height: none !important;
                overflow: visible !important;
            }
            
            body {
                background: white !important;
                font-size: 11pt;
            }
            
            .table {
                font-size: 9pt;
                width: 100% !important;
            }
            
            /* Unhide all matching paginated records across all pages during printing */
            #filteredTable tbody tr,
            #officerTable tbody tr,
            #auditTable tbody tr:not([data-filter-match="0"]) {
                display: table-row !important;
            }
            
            #auditTable tbody tr[data-filter-match="0"] {
                display: none !important;
            }
            
            .table th {
                background-color: #f8fafc !important;
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
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            white-space: nowrap;
        }
        .audit-badge.badge-create { background: #dcfce7 !important; color: #166534 !important; border: 1px solid #bbf7d0 !important; }
        .audit-badge.badge-update { background: #dbeafe !important; color: #1e40af !important; border: 1px solid #bfdbfe !important; }
        .audit-badge.badge-delete { background: #fee2e2 !important; color: #991b1b !important; border: 1px solid #fecaca !important; }
        .audit-badge.badge-login { background: #ccfbf1 !important; color: #115e59 !important; border: 1px solid #99f6e4 !important; }
        .audit-badge.badge-login-failed { background: #ffe4e6 !important; color: #9f1239 !important; border: 1px solid #fecdd3 !important; }
        .audit-badge.badge-logout { background: #f1f5f9 !important; color: #475569 !important; border: 1px solid #cbd5e1 !important; }
        .audit-badge.badge-security { background: #fef3c7 !important; color: #92400e !important; border: 1px solid #fde68a !important; }
        .audit-badge.badge-print { background: #f3e8ff !important; color: #6b21a8 !important; border: 1px solid #e9d5ff !important; }
        .audit-badge.badge-view { background: #e0f2fe !important; color: #075985 !important; border: 1px solid #bae6fd !important; }
        .audit-badge.badge-email { background: #e0e7ff !important; color: #3730a3 !important; border: 1px solid #c7d2fe !important; }
        .audit-badge.badge-other { background: #f1f5f9 !important; color: #475569 !important; border: 1px solid #e2e8f0 !important; }
        
        /* Modern IP Address & Device Styling */
        .audit-ip-box {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }
        .audit-ip {
            display: inline-flex;
            align-items: center;
            padding: 3px 8px;
            border-radius: 6px;
            font-family: 'SFMono-Regular', Consolas, monospace;
            font-size: 0.82rem;
            font-weight: 600;
            white-space: nowrap;
            width: fit-content;
        }
        .audit-ip.ip-local {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        .audit-ip.ip-remote {
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
        }
        .badge-local-tag {
            font-size: 0.65rem;
            background: #dcfce7;
            color: #15803d;
            padding: 1px 5px;
            border-radius: 3px;
            margin-left: 5px;
            font-weight: 700;
            text-transform: uppercase;
            border: 1px solid #86efac;
        }
        .audit-device-text {
            font-size: 0.73rem;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* Preset Date Buttons */
        .quick-presets-bar {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px dashed #e2e8f0;
        }
        .btn-preset {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            color: #334155;
            font-size: 0.76rem;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 16px;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .btn-preset:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            transform: translateY(-1px);
        }
        .btn-preset.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        /* Summary Stat Pills for Audit Trail */
        .audit-summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 12px;
            margin-bottom: 18px;
        }
        .audit-summary-pill {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 14px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .audit-summary-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }
        .audit-summary-info {
            display: flex;
            flex-direction: column;
        }
        .audit-summary-val {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1e293b;
            line-height: 1.1;
        }
        .audit-summary-lbl {
            font-size: 0.72rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.04em;
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

        /* Professional Table Pagination Controls */
        .pagination-container {
            padding: 12px 4px;
            font-size: 0.88rem;
            color: #475569;
        }

        .pagination-info {
            font-weight: 500;
            color: #475569;
        }

        .pagination-info b {
            color: #0f172a;
            font-weight: 700;
        }

        .table-pagination-nav .pagination {
            margin: 0;
            gap: 4px;
            display: flex;
            align-items: center;
            list-style: none;
            padding-left: 0;
        }

        .table-pagination-nav .page-link {
            border-radius: 6px !important;
            border: 1px solid #cbd5e1;
            color: #334155;
            padding: 5px 12px;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.2s ease;
            cursor: pointer;
            background: #ffffff;
            text-decoration: none;
            display: inline-block;
            user-select: none;
        }

        .table-pagination-nav .page-link:hover {
            background-color: #f1f5f9;
            border-color: #94a3b8;
            color: var(--primary);
        }

        .table-pagination-nav .page-item.active .page-link {
            background-color: var(--primary) !important;
            border-color: var(--primary) !important;
            color: #ffffff !important;
            box-shadow: 0 2px 6px rgba(26, 92, 46, 0.25);
        }

        .table-pagination-nav .page-item.disabled .page-link {
            color: #94a3b8 !important;
            background-color: #f8fafc !important;
            border-color: #e2e8f0 !important;
            cursor: not-allowed !important;
            pointer-events: none;
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

            <!-- Report Header with Professional Compact Filter -->
            <div class="report-header" style="margin-bottom: 25px;">
                <div class="report-header-flex">
                    <div class="report-header-title-box">
                        <h4><i class="fa fa-calendar-check-o text-success me-2"></i> Filter Applications & Audit Report</h4>
                        <p class="mb-0">Select custom dates or click a quick preset period</p>
                    </div>
                    <div class="report-header-filter-box">
                        <form method="GET" class="filter-compact-form" id="mainFilterForm">
                            <div class="filter-inputs-row">
                                <div class="filter-date-field">
                                    <span class="filter-date-tag">From</span>
                                    <input type="date" class="form-control filter-date-control" name="start_date" id="filterStartDate"
                                           value="<?php echo htmlspecialchars($start_date); ?>" required>
                                </div>
                                <div class="filter-date-field">
                                    <span class="filter-date-tag">To</span>
                                    <input type="date" class="form-control filter-date-control" name="end_date" id="filterEndDate"
                                           value="<?php echo htmlspecialchars($end_date); ?>" required>
                                </div>
                                <button type="submit" class="btn btn-filter-green btn-filter-compact">
                                    <i class="fa fa-filter"></i> Filter
                                </button>
                            </div>
                            <!-- Date Range Quick Presets -->
                            <div class="quick-presets-bar">
                                <span class="presets-label"><i class="fa fa-bolt text-warning"></i> Presets:</span>
                                <button type="button" class="btn-preset <?php echo ($start_date === date('Y-m-d') && $end_date === date('Y-m-d')) ? 'active' : ''; ?>" onclick="setFilterPreset('today')">Today</button>
                                <button type="button" class="btn-preset <?php echo ($start_date === date('Y-m-d', strtotime('-1 day')) && $end_date === date('Y-m-d', strtotime('-1 day'))) ? 'active' : ''; ?>" onclick="setFilterPreset('yesterday')">Yesterday</button>
                                <button type="button" class="btn-preset" onclick="setFilterPreset('this_week')">This Week</button>
                                <button type="button" class="btn-preset <?php echo ($start_date === date('Y-m-01') && $end_date === date('Y-m-t')) ? 'active' : ''; ?>" onclick="setFilterPreset('this_month')">This Month</button>
                                <button type="button" class="btn-preset" onclick="setFilterPreset('last_month')">Last Month</button>
                                <button type="button" class="btn-preset" onclick="setFilterPreset('ytd')">Year to Date</button>
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
                            <div class="table-responsive">
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
                            <div id="filteredPagination" class="pagination-container d-flex justify-content-between align-items-center flex-wrap gap-2 mt-2 pt-2 border-top"></div>
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



            <!-- Officer Activity & Productivity Section -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="data-card">
                        <h5 class="d-flex align-items-center mb-3 flex-wrap gap-2">
                            <span><i class="fa fa-users me-2" style="color: var(--primary);"></i> Officer Productivity & Activity Summary</span>
                            <div class="ms-md-auto d-flex gap-2 align-items-center flex-wrap">
                                <button type="button" class="btn btn-sm btn-success text-white" onclick="exportOfficerToCSV()" title="Export Officer Summary to CSV / Excel">
                                    <i class="fa fa-file-excel-o"></i> Export CSV
                                </button>
                                <button type="button" class="btn btn-sm btn-warning text-white" onclick="printTable('officerTableContainer')" title="Print Table">
                                    <i class="fa fa-print"></i> Print Table
                                </button>
                                <button type="button" class="btn btn-minimize" onclick="toggleOfficerTable()" id="toggleOfficerBtn">
                                    <i class="fa fa-minus"></i> Minimize
                                </button>
                            </div>
                        </h5>
                        <div id="officerTableContainer">
                            <div class="table-responsive">
                                <table class="table table-hover table-striped" id="officerTable">
                                    <thead>
                                        <tr>
                                            <th>S/No</th>
                                            <th>Officer / Username</th>
                                            <th>Applications Created</th>
                                            <th>Records Edited</th>
                                            <th>Vignettes Printed</th>
                                            <th>Logins</th>
                                            <th>Total Actions</th>
                                            <th>Last Active Timestamp</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($officer_activity_summary)): ?>
                                            <?php 
                                            $o_sno = 1;
                                            foreach ($officer_activity_summary as $op_name => $op_stat): 
                                            ?>
                                                <tr>
                                                    <td><?php echo $o_sno++; ?></td>
                                                    <td class="font-monospace fw-bold text-dark">
                                                        <i class="fa fa-user-circle text-primary me-1"></i> <?php echo htmlspecialchars($op_name); ?>
                                                    </td>
                                                    <td>
                                                        <span style="background:#dcfce7; color:#166534; padding:3px 8px; border-radius:4px; font-weight:700; font-size:0.85rem; border:1px solid #bbf7d0;">
                                                            <?php echo number_format($op_stat['creates']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span style="background:#dbeafe; color:#1e40af; padding:3px 8px; border-radius:4px; font-weight:700; font-size:0.85rem; border:1px solid #bfdbfe;">
                                                            <?php echo number_format($op_stat['edits']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span style="background:#f3e8ff; color:#6b21a8; padding:3px 8px; border-radius:4px; font-weight:700; font-size:0.85rem; border:1px solid #e9d5ff;">
                                                            <?php echo number_format($op_stat['prints']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span style="background:#ccfbf1; color:#115e59; padding:3px 8px; border-radius:4px; font-weight:700; font-size:0.85rem; border:1px solid #99f6e4;">
                                                            <?php echo number_format($op_stat['logins']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <strong class="text-dark" style="font-size:0.95rem;"><?php echo number_format($op_stat['total']); ?></strong>
                                                    </td>
                                                    <td class="small text-muted">
                                                        <i class="fa fa-clock-o me-1"></i><?php echo date('M j, Y g:i A', strtotime($op_stat['last_seen'])); ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="8" class="text-center text-muted py-3">No officer activity recorded during this period.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div id="officerPagination" class="pagination-container d-flex justify-content-between align-items-center flex-wrap gap-2 mt-2 pt-2 border-top"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Audit Trail Section -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="data-card">
                        <h5 class="d-flex align-items-center mb-3 flex-wrap gap-2">
                            <span><i class="fa fa-shield me-2" style="color: var(--primary);"></i> System Audit Trail & Security Logs</span>
                            <div class="ms-md-auto d-flex gap-2 align-items-center flex-wrap">
                                <button type="button" class="btn btn-sm btn-success text-white" onclick="exportAuditTrailToCSV()" title="Export Audit Trail to CSV / Excel">
                                    <i class="fa fa-file-excel-o"></i> Export CSV
                                </button>
                                <button type="button" class="btn btn-sm btn-warning text-white" onclick="printTable('auditTableContainer')" title="Print Audit Trail">
                                    <i class="fa fa-print"></i> Print Table
                                </button>
                                <button type="button" class="btn btn-minimize" onclick="toggleAuditTable()" id="toggleAuditBtn">
                                    <i class="fa fa-minus"></i> Minimize
                                </button>
                            </div>
                        </h5>

                        <!-- Summary Counters for Audit Trail -->
                        <div class="audit-summary-grid">
                            <div class="audit-summary-pill">
                                <div class="audit-summary-icon" style="background:#e0f2fe; color:#0284c7;">
                                    <i class="fa fa-list-ul"></i>
                                </div>
                                <div class="audit-summary-info">
                                    <span class="audit-summary-val"><?php echo number_format($audit_total_count); ?></span>
                                    <span class="audit-summary-lbl">Total Events</span>
                                </div>
                            </div>
                            <div class="audit-summary-pill">
                                <div class="audit-summary-icon" style="background:#dcfce7; color:#16a34a;">
                                    <i class="fa fa-users"></i>
                                </div>
                                <div class="audit-summary-info">
                                    <span class="audit-summary-val"><?php echo $audit_distinct_operators; ?></span>
                                    <span class="audit-summary-lbl">Active Users</span>
                                </div>
                            </div>
                            <div class="audit-summary-pill">
                                <div class="audit-summary-icon" style="background:#ccfbf1; color:#0d9488;">
                                    <i class="fa fa-sign-in"></i>
                                </div>
                                <div class="audit-summary-info">
                                    <span class="audit-summary-val"><?php echo $audit_logins_count; ?></span>
                                    <span class="audit-summary-lbl">Total Logins</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Interactive Audit Trail Filters -->
                        <div class="audit-filter-section">
                            <div class="row g-2 align-items-center">
                                <div class="col-md-3">
                                    <input type="text" id="auditSearchInput" class="form-control" placeholder="Search description/user...">
                                </div>
                                <div class="col-md-3">
                                    <select id="auditFilterOperator" class="form-select">
                                        <option value="">All Operators</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <select id="auditFilterAction" class="form-select">
                                        <option value="">All Actions</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <select id="auditFilterIP" class="form-select">
                                        <option value="">All IPs</option>
                                        <?php if (!empty($distinct_ips)): ?>
                                            <?php foreach (array_keys($distinct_ips) as $ip_opt): ?>
                                                <option value="<?php echo htmlspecialchars($ip_opt); ?>"><?php echo htmlspecialchars($ip_opt); ?><?php echo $ip_opt === '127.0.0.1' ? ' (Local)' : ''; ?></option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button id="resetAuditFilters" class="btn btn-secondary w-100">
                                        <i class="fa fa-refresh"></i> Reset
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div id="auditTableContainer">
                            <div class="table-responsive">
                                <table class="table table-hover table-striped audit-table" id="auditTable">
                                    <thead style="position: sticky; top: 0; background: white; z-index: 1;">
                                        <tr>
                                            <th>S/No</th>
                                            <th>Timestamp</th>
                                            <th>Operator</th>
                                            <th>Action</th>
                                            <th>Description</th>
                                            <th>IP Address & Device</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($audit_logs)): ?>
                                            <?php 
                                            $a_sno = 1;
                                            foreach ($audit_logs as $log): 
                                                $badgeInfo = getAuditBadgeClass($log['action']);
                                                $disp_ip = $log['display_ip'] ?? '127.0.0.1';
                                                $is_local = !empty($log['is_local']);
                                                $ua_info = $log['ua_info'] ?? ['summary' => 'Local Client', 'icon' => 'fa-desktop'];
                                            ?>
                                                <tr data-action="<?php echo htmlspecialchars($log['action']); ?>" data-ip="<?php echo htmlspecialchars($disp_ip); ?>">
                                                    <td><?php echo $a_sno++; ?></td>
                                                    <td>
                                                        <div class="audit-timestamp">
                                                            <i class="fa fa-clock-o"></i> <?php echo htmlspecialchars($log['created_at']); ?>
                                                        </div>
                                                    </td>
                                                    <td class="font-monospace fw-bold">
                                                        <i class="fa fa-user-circle text-muted me-1"></i><?php echo htmlspecialchars($log['user_id']); ?>
                                                    </td>
                                                    <td>
                                                        <span class="audit-badge <?php echo $badgeInfo['class']; ?>">
                                                            <i class="fa <?php echo $badgeInfo['icon']; ?> me-1"></i>
                                                            <?php echo htmlspecialchars($log['action']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php echo htmlspecialchars($log['description']); ?>
                                                    </td>
                                                    <td>
                                                        <div class="audit-ip-box">
                                                            <?php if ($is_local): ?>
                                                                <span class="audit-ip ip-local" title="Localhost Loopback Connection (127.0.0.1)">
                                                                    <i class="fa fa-desktop text-success me-1"></i>
                                                                    <span><?php echo $disp_ip; ?></span>
                                                                    <span class="badge-local-tag">Local</span>
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="audit-ip ip-remote" title="Remote Client IP: <?php echo $disp_ip; ?>">
                                                                    <i class="fa fa-globe text-primary me-1"></i>
                                                                    <span><?php echo $disp_ip; ?></span>
                                                                </span>
                                                            <?php endif; ?>
                                                            <div class="audit-device-text" title="<?php echo htmlspecialchars($log['user_agent'] ?? ''); ?>">
                                                                <i class="fa <?php echo $ua_info['icon']; ?>"></i>
                                                                <span><?php echo htmlspecialchars($ua_info['summary']); ?></span>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-4">
                                                    <i class="fa fa-info-circle fa-2x mb-2 d-block text-muted"></i>
                                                    No audit trail logs recorded for this period.
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div id="auditPagination" class="pagination-container d-flex justify-content-between align-items-center flex-wrap gap-2 mt-2 pt-2 border-top"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Export Section -->
            <div class="export-section">
                <h5><i class="fa fa-download" style="color: var(--primary);"></i> Reports & Export Center</h5>
                <p class="text-muted mb-3" style="font-size:0.9rem;">Download activity logs or print official reports for the selected period (<?php echo htmlspecialchars($start_date); ?> to <?php echo htmlspecialchars($end_date); ?>)</p>
                <div class="btn-group flex-wrap gap-2 justify-content-center">
                    <button class="btn btn-success btn-export text-white" onclick="exportOfficerToCSV()">
                        <i class="fa fa-file-excel-o"></i> Export Officer Summary (CSV)
                    </button>
                    <button class="btn btn-info btn-export text-white" onclick="exportAuditTrailToCSV()">
                        <i class="fa fa-shield"></i> Export Audit Trail (CSV)
                    </button>
                    <button class="btn btn-warning btn-export" onclick="printReport()">
                        <i class="fa fa-print"></i> Print Full Official Report
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Status Distribution Pie Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusChart = new Chart(statusCtx, {
            type: 'pie',
            data: {
                labels: ['Fresh Applications', 'Renewal Applications'],
                datasets: [{
                    data: [
                        <?php echo $filtered_fresh_count; ?>,
                        <?php echo $filtered_renewal_count; ?>
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

        // Quick Date Presets Helper
        function setFilterPreset(type) {
            const today = new Date();
            let start = new Date();
            let end = new Date();

            const formatDate = (d) => {
                const year = d.getFullYear();
                const month = String(d.getMonth() + 1).padStart(2, '0');
                const day = String(d.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            };

            if (type === 'today') {
                start = today;
                end = today;
            } else if (type === 'yesterday') {
                start.setDate(today.getDate() - 1);
                end.setDate(today.getDate() - 1);
            } else if (type === 'this_week') {
                const day = today.getDay();
                const diff = today.getDate() - day + (day === 0 ? -6 : 1);
                start.setDate(diff);
                end = today;
            } else if (type === 'this_month') {
                start = new Date(today.getFullYear(), today.getMonth(), 1);
                end = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            } else if (type === 'last_month') {
                start = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                end = new Date(today.getFullYear(), today.getMonth(), 0);
            } else if (type === 'ytd') {
                start = new Date(today.getFullYear(), 0, 1);
                end = today;
            }

            document.getElementById('filterStartDate').value = formatDate(start);
            document.getElementById('filterEndDate').value = formatDate(end);
            document.getElementById('mainFilterForm').submit();
        }

        // Toggle UI Sections
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

        function toggleOfficerTable() {
            const container = document.getElementById('officerTableContainer');
            const btn = document.getElementById('toggleOfficerBtn');
            if (container.style.display === 'none') {
                container.style.display = 'block';
                btn.innerHTML = '<i class="fa fa-minus"></i> Minimize';
            } else {
                container.style.display = 'none';
                btn.innerHTML = '<i class="fa fa-plus"></i> Maximize';
            }
        }

        function toggleAuditTable() {
            const container = document.getElementById('auditTableContainer');
            const btn = document.getElementById('toggleAuditBtn');
            if (container.style.display === 'none') {
                container.style.display = 'block';
                btn.innerHTML = '<i class="fa fa-minus"></i> Minimize';
            } else {
                container.style.display = 'none';
                btn.innerHTML = '<i class="fa fa-plus"></i> Maximize';
            }
        }

        // CSV Helper
        function downloadCSV(csvContent, filename) {
            const blob = new Blob(["\uFEFF" + csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement("a");
            if (link.download !== undefined) {
                const url = URL.createObjectURL(blob);
                link.setAttribute("href", url);
                link.setAttribute("download", filename);
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        }

        // Export Applications to CSV
        function exportApplicationsToCSV() {
            const rows = document.querySelectorAll('#filteredTable tbody tr');
            if (!rows || rows.length === 0 || rows[0].querySelectorAll('td').length <= 1) {
                alert('No application records available to export.');
                return;
            }

            let csv = '"S/No","Application Date","Re-Entry Visa Number","Applicant Name","Passport Number","Application Type"\n';
            let sno = 1;
            rows.forEach(r => {
                if (r.querySelector('td[colspan]')) return;
                const cols = r.querySelectorAll('td');
                if (cols.length >= 6) {
                    const date = cols[1].innerText.replace(/"/g, '""').trim();
                    const visaNo = cols[2].innerText.replace(/"/g, '""').trim();
                    const name = cols[3].innerText.replace(/"/g, '""').trim();
                    const passport = cols[4].innerText.replace(/"/g, '""').trim();
                    const type = cols[5].innerText.replace(/"/g, '""').trim();
                    csv += `"${sno++}","${date}","${visaNo}","${name}","${passport}","${type}"\n`;
                }
            });

            const startDate = document.getElementById('filterStartDate')?.value || 'report';
            const endDate = document.getElementById('filterEndDate')?.value || 'period';
            downloadCSV(csv, `visa_applications_${startDate}_to_${endDate}.csv`);
        }

        // Export Audit Trail to CSV (Exports all records matching active filter)
        function exportAuditTrailToCSV() {
            const rows = document.querySelectorAll('#auditTable tbody tr');
            if (!rows || rows.length === 0 || (rows.length === 1 && rows[0].querySelector('td[colspan]'))) {
                alert('No audit trail records available to export.');
                return;
            }

            let csv = '"S/No","Timestamp","Operator","Action","Description","IP Address","Device / Client"\n';
            let sno = 1;
            rows.forEach(r => {
                if (r.querySelector('td[colspan]')) return;
                if (r.dataset.filterMatch === '0') return; // Only export records matching current filter

                const cols = r.querySelectorAll('td');
                if (cols.length >= 6) {
                    const time = cols[1].innerText.replace(/"/g, '""').trim();
                    const op = cols[2].innerText.replace(/"/g, '""').trim();
                    const act = cols[3].innerText.replace(/"/g, '""').trim();
                    const desc = cols[4].innerText.replace(/"/g, '""').trim();
                    const ip = (r.getAttribute('data-ip') || cols[5].querySelector('.audit-ip span')?.innerText || '127.0.0.1').replace(/"/g, '""').trim();
                    const device = (cols[5].querySelector('.audit-device-text span')?.innerText || '').replace(/"/g, '""').trim();
                    csv += `"${sno++}","${time}","${op}","${act}","${desc}","${ip}","${device}"\n`;
                }
            });

            if (sno === 1) {
                alert('No matching audit records found for the active filter to export.');
                return;
            }

            const startDate = document.getElementById('filterStartDate')?.value || 'report';
            const endDate = document.getElementById('filterEndDate')?.value || 'period';
            downloadCSV(csv, `system_audit_trail_${startDate}_to_${endDate}.csv`);
        }

        // Export Officer Productivity to CSV
        function exportOfficerToCSV() {
            const rows = document.querySelectorAll('#officerTable tbody tr');
            if (!rows || rows.length === 0 || (rows.length === 1 && rows[0].querySelector('td[colspan]'))) {
                alert('No officer productivity records available to export.');
                return;
            }

            let csv = '"S/No","Officer / Username","Applications Created","Records Edited","Vignettes Printed","Logins","Total Actions","Last Active Timestamp"\n';
            let sno = 1;
            rows.forEach(r => {
                if (r.querySelector('td[colspan]')) return;
                const cols = r.querySelectorAll('td');
                if (cols.length >= 8) {
                    const user = cols[1].innerText.replace(/"/g, '""').trim();
                    const creates = cols[2].innerText.replace(/"/g, '""').trim();
                    const edits = cols[3].innerText.replace(/"/g, '""').trim();
                    const prints = cols[4].innerText.replace(/"/g, '""').trim();
                    const logins = cols[5].innerText.replace(/"/g, '""').trim();
                    const total = cols[6].innerText.replace(/"/g, '""').trim();
                    const lastActive = cols[7].innerText.replace(/"/g, '""').trim();
                    csv += `"${sno++}","${user}","${creates}","${edits}","${prints}","${logins}","${total}","${lastActive}"\n`;
                }
            });

            const startDate = document.getElementById('filterStartDate')?.value || 'report';
            const endDate = document.getElementById('filterEndDate')?.value || 'period';
            downloadCSV(csv, `officer_productivity_${startDate}_to_${endDate}.csv`);
        }

        // Print Official Report (Full Page)
        function printReport() {
            window.print();
        }

        // Print Specific Table (Prints ALL filtered records for that table, not just 10 records)
        function printTable(tableContainerId) {
            const container = document.getElementById(tableContainerId);
            if (!container) {
                alert('No table container found to print.');
                return;
            }
            const table = container.querySelector('table');
            if (!table) {
                alert('No table element found to print.');
                return;
            }

            // Clone table to unhide all paginated rows without altering live screen
            const clonedTable = table.cloneNode(true);
            clonedTable.querySelectorAll('.pagination-container, .table-pagination-nav').forEach(el => el.remove());

            const rows = clonedTable.querySelectorAll('tbody tr');
            let totalPrintRecords = 0;
            rows.forEach(r => {
                if (r.querySelector('td[colspan]')) return;
                if (r.getAttribute('data-filter-match') === '0') {
                    r.remove(); // Remove rows filtered out by active search/filters
                } else {
                    r.style.display = ''; // Ensure all paginated records are fully visible
                    totalPrintRecords++;
                }
            });

            let reportTitle = "Official Records Report";
            if (tableContainerId === 'filteredTableContainer') {
                reportTitle = "Filtered Visa Application Records";
            } else if (tableContainerId === 'officerTableContainer') {
                reportTitle = "Officer Productivity & Activity Summary";
            } else if (tableContainerId === 'auditTableContainer') {
                reportTitle = "System Audit Trail & Security Logs";
            }

            const startDate = document.getElementById('filterStartDate')?.value || '<?php echo htmlspecialchars($start_date); ?>';
            const endDate = document.getElementById('filterEndDate')?.value || '<?php echo htmlspecialchars($end_date); ?>';

            const printWindow = window.open('', '_blank', 'height=750,width=1000');
            printWindow.document.write('<!DOCTYPE html><html><head><title>' + reportTitle + ' - Nigeria Immigration Service</title>');
            printWindow.document.write('<link href="vendors/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">');
            printWindow.document.write('<link href="vendors/font-awesome/css/font-awesome.min.css" rel="stylesheet">');
            printWindow.document.write('<style>');
            printWindow.document.write('@page { size: landscape; margin: 12mm; }');
            printWindow.document.write('body { padding: 20px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #0f172a; background: #fff; }');
            printWindow.document.write('.header-block { border-bottom: 2.5px solid #1a5c2e; padding-bottom: 12px; margin-bottom: 18px; display: flex; justify-content: space-between; align-items: flex-start; }');
            printWindow.document.write('.header-block h3 { color: #1a5c2e; font-weight: 800; margin: 0 0 4px 0; font-size: 1.35rem; }');
            printWindow.document.write('.header-block h4 { color: #334155; font-size: 1rem; margin: 0 0 4px 0; font-weight: 600; }');
            printWindow.document.write('.header-block p { margin: 0; color: #64748b; font-size: 0.82rem; }');
            printWindow.document.write('.meta-box { text-align: right; font-size: 0.82rem; color: #475569; }');
            printWindow.document.write('.meta-box b { color: #0f172a; }');
            printWindow.document.write('table { width: 100%; border-collapse: collapse; margin-top: 10px; }');
            printWindow.document.write('th, td { border: 1px solid #cbd5e1 !important; padding: 7px 10px; text-align: left; font-size: 11px; vertical-align: middle; }');
            printWindow.document.write('th { background-color: #f1f5f9 !important; color: #1e293b !important; font-weight: 700; text-transform: uppercase; font-size: 10.5px; }');
            printWindow.document.write('tbody tr { display: table-row !important; page-break-inside: avoid; }');
            printWindow.document.write('.audit-badge { display: inline-block; padding: 2px 6px; font-size: 9.5px; font-weight: 700; border: 1px solid #ccc; border-radius: 4px; }');
            printWindow.document.write('.audit-ip { font-family: monospace; font-weight: 700; font-size: 10.5px; }');
            printWindow.document.write('.audit-device-text { font-size: 9.5px; color: #64748b; }');
            printWindow.document.write('.badge-local-tag { display: none; }');
            printWindow.document.write('.pagination-container, .table-pagination-nav { display: none !important; }');
            printWindow.document.write('.table-responsive { overflow: visible !important; max-height: none !important; }');
            printWindow.document.write('.footer-note { margin-top: 20px; border-top: 1px solid #e2e8f0; padding-top: 10px; font-size: 0.78rem; color: #64748b; display: flex; justify-content: space-between; }');
            printWindow.document.write('</style></head><body>');
            printWindow.document.write('<div class="header-block">');
            printWindow.document.write('  <div>');
            printWindow.document.write('    <h3>NIGERIA IMMIGRATION SERVICE (NISREV)</h3>');
            printWindow.document.write('    <h4>' + reportTitle + '</h4>');
            printWindow.document.write('    <p>Official System Audit & Operational Record Log</p>');
            printWindow.document.write('  </div>');
            printWindow.document.write('  <div class="meta-box">');
            printWindow.document.write('    <div>Report Period: <b>' + startDate + '</b> to <b>' + endDate + '</b></div>');
            printWindow.document.write('    <div>Total Records Printed: <b>' + totalPrintRecords + '</b></div>');
            printWindow.document.write('    <div>Printed on: <b>' + new Date().toLocaleString() + '</b></div>');
            printWindow.document.write('  </div>');
            printWindow.document.write('</div>');
            printWindow.document.write('<div class="table-responsive">');
            printWindow.document.write(clonedTable.outerHTML);
            printWindow.document.write('</div>');
            printWindow.document.write('<div class="footer-note">');
            printWindow.document.write('  <span>Confidential — Official Nigeria Immigration Service Document</span>');
            printWindow.document.write('  <span>Page Printed from NISREV Portal</span>');
            printWindow.document.write('</div>');
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            
            printWindow.focus();
            setTimeout(function() {
                printWindow.print();
                printWindow.close();
            }, 500);
        }

        // Clean Reusable Table Pagination Engine (No "Per page:" dropdown)
        class TablePaginator {
            constructor(tableId, paginationContainerId, options = {}) {
                this.tableId = tableId;
                this.containerId = paginationContainerId;
                this.table = document.getElementById(tableId);
                this.container = document.getElementById(paginationContainerId);
                if (!this.table || !this.container) return;

                this.pageSize = parseInt(options.pageSize, 10) || 10;
                this.currentPage = 1;
                this.rowFilter = typeof options.rowFilter === 'function' ? options.rowFilter : null;
                
                // Store global reference for inline onclick callbacks
                window['paginator_' + this.containerId] = this;
                
                this.render();
            }

            getRows() {
                const tbody = this.table.querySelector('tbody');
                if (!tbody) return [];
                const rows = Array.from(tbody.querySelectorAll('tr'));
                return rows.filter(r => !r.querySelector('td[colspan]'));
            }

            getMatchingRows() {
                const rows = this.getRows();
                if (this.rowFilter) {
                    return rows.filter(r => this.rowFilter(r));
                }
                return rows;
            }

            setPage(pageNumber) {
                const totalRows = this.getMatchingRows().length;
                const totalPages = Math.ceil(totalRows / this.pageSize) || 1;
                let page = parseInt(pageNumber, 10);
                if (isNaN(page) || page < 1) page = 1;
                if (page > totalPages) page = totalPages;
                this.currentPage = page;
                this.render();
            }

            refresh() {
                this.currentPage = 1;
                this.render();
            }

            render() {
                const allRows = this.getRows();
                const matchingRows = this.getMatchingRows();
                const totalRows = matchingRows.length;
                const totalPages = Math.ceil(totalRows / this.pageSize) || 1;

                if (this.currentPage > totalPages) {
                    this.currentPage = totalPages;
                }

                const startIndex = (this.currentPage - 1) * this.pageSize;
                const endIndex = startIndex + this.pageSize;

                // Loop all rows in the table
                allRows.forEach(row => {
                    const isMatch = this.rowFilter ? this.rowFilter(row) : true;
                    if (!isMatch) {
                        row.style.display = 'none';
                    } else {
                        const matchIndex = matchingRows.indexOf(row);
                        if (matchIndex >= startIndex && matchIndex < endIndex) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    }
                });

                // Check for empty placeholder row
                const tbody = this.table.querySelector('tbody');
                const emptyPlaceholder = tbody ? tbody.querySelector('tr td[colspan]') : null;
                if (emptyPlaceholder) {
                    emptyPlaceholder.closest('tr').style.display = totalRows === 0 ? '' : 'none';
                }

                if (totalRows === 0) {
                    this.container.innerHTML = `
                        <div class="pagination-info text-muted">Showing <b>0</b> to <b>0</b> of <b>0</b> records</div>
                    `;
                    return;
                }

                const dispStart = startIndex + 1;
                const dispEnd = Math.min(endIndex, totalRows);

                let html = `
                    <div class="pagination-info">
                        Showing <b>${dispStart}</b> to <b>${dispEnd}</b> of <b>${totalRows}</b> records
                    </div>
                `;

                // If multiple pages exist, show navigation buttons
                if (totalPages > 1) {
                    html += `
                        <nav class="table-pagination-nav" aria-label="Table navigation">
                            <ul class="pagination">
                    `;

                    // Previous Button
                    const prevDisabled = this.currentPage === 1 ? 'disabled' : '';
                    html += `
                        <li class="page-item ${prevDisabled}">
                            <button type="button" class="page-link" onclick="window['paginator_${this.containerId}'].setPage(${this.currentPage - 1})" ${prevDisabled ? 'disabled' : ''}>
                                &laquo; Prev
                            </button>
                        </li>
                    `;

                    // Numbered Page Buttons
                    const delta = 2;
                    let left = Math.max(1, this.currentPage - delta);
                    let right = Math.min(totalPages, this.currentPage + delta);

                    if (left > 1) {
                        html += `<li class="page-item"><button type="button" class="page-link" onclick="window['paginator_${this.containerId}'].setPage(1)">1</button></li>`;
                        if (left > 2) {
                            html += `<li class="page-item disabled"><span class="page-link">&hellip;</span></li>`;
                        }
                    }

                    for (let i = left; i <= right; i++) {
                        const activeClass = i === this.currentPage ? 'active' : '';
                        html += `
                            <li class="page-item ${activeClass}">
                                <button type="button" class="page-link" onclick="window['paginator_${this.containerId}'].setPage(${i})">${i}</button>
                            </li>
                        `;
                    }

                    if (right < totalPages) {
                        if (right < totalPages - 1) {
                            html += `<li class="page-item disabled"><span class="page-link">&hellip;</span></li>`;
                        }
                        html += `<li class="page-item"><button type="button" class="page-link" onclick="window['paginator_${this.containerId}'].setPage(${totalPages})">${totalPages}</button></li>`;
                    }

                    // Next Button
                    const nextDisabled = this.currentPage === totalPages ? 'disabled' : '';
                    html += `
                        <li class="page-item ${nextDisabled}">
                            <button type="button" class="page-link" onclick="window['paginator_${this.containerId}'].setPage(${this.currentPage + 1})" ${nextDisabled ? 'disabled' : ''}>
                                Next &raquo;
                            </button>
                        </li>
                    `;

                    html += `
                            </ul>
                        </nav>
                    `;
                }

                this.container.innerHTML = html;
            }
        }

        // Global Paginator References
        let auditPaginator = null;
        let filteredAppsPaginator = null;
        let officerPaginator = null;

        function applyAuditFilters() {
            const searchInput = document.getElementById('auditSearchInput');
            const filterOperator = document.getElementById('auditFilterOperator');
            const filterAction = document.getElementById('auditFilterAction');
            const filterIP = document.getElementById('auditFilterIP');

            const searchVal = searchInput ? searchInput.value.toLowerCase().trim() : '';
            const operatorVal = filterOperator ? filterOperator.value.toLowerCase().trim() : '';
            const actionVal = filterAction ? filterAction.value.toLowerCase().trim() : '';
            const ipVal = filterIP ? filterIP.value.toLowerCase().trim() : '';

            const rows = document.querySelectorAll('#auditTable tbody tr');
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length < 6) return;

                const operatorText = cells[2].textContent.toLowerCase().trim();
                const actionBadge = cells[3].querySelector('.audit-badge');
                const rawAction = (row.getAttribute('data-action') || actionBadge?.textContent || cells[3].textContent).toUpperCase().trim();
                const descriptionText = cells[4].textContent.toLowerCase().trim();
                const rawIP = (row.getAttribute('data-ip') || '').toLowerCase().trim();

                let matchSearch = !searchVal || descriptionText.includes(searchVal) || rawAction.toLowerCase().includes(searchVal) || operatorText.includes(searchVal);
                let matchOperator = !operatorVal || operatorText === operatorVal;
                let matchAction = !actionVal || rawAction.toLowerCase() === actionVal;
                let matchIP = !ipVal || rawIP === ipVal;

                if (matchSearch && matchOperator && matchAction && matchIP) {
                    row.dataset.filterMatch = '1';
                } else {
                    row.dataset.filterMatch = '0';
                }
            });

            if (auditPaginator) {
                auditPaginator.refresh();
            }
        }

        // Initialize Audit Filter Options and Table Pagination on DOM Ready
        document.addEventListener('DOMContentLoaded', function() {
            // 1. Initialize Filtered Applications Table Pagination (10 per page, no per-page select)
            filteredAppsPaginator = new TablePaginator('filteredTable', 'filteredPagination', {
                pageSize: 10
            });

            // 2. Initialize Officer Productivity Table Pagination (10 per page, no per-page select)
            officerPaginator = new TablePaginator('officerTable', 'officerPagination', {
                pageSize: 10
            });

            // 3. Initialize Audit Trail Table Pagination (10 per page, no per-page select)
            auditPaginator = new TablePaginator('auditTable', 'auditPagination', {
                pageSize: 10,
                rowFilter: (row) => row.dataset.filterMatch !== '0'
            });

            // Set up Audit Filter dropdowns
            const table = document.querySelector('#auditTable');
            if (table) {
                const tbody = table.querySelector('tbody');
                if (tbody) {
                    const rows = tbody.querySelectorAll('tr');

                    const searchInput = document.getElementById('auditSearchInput');
                    const filterOperator = document.getElementById('auditFilterOperator');
                    const filterAction = document.getElementById('auditFilterAction');
                    const filterIP = document.getElementById('auditFilterIP');
                    const resetBtn = document.getElementById('resetAuditFilters');

                    const operators = new Set();
                    const actions = new Set();

                    rows.forEach(row => {
                        const cells = row.querySelectorAll('td');
                        if (cells.length >= 6) {
                            const operatorText = cells[2].textContent.trim();
                            const rawAction = row.getAttribute('data-action') || cells[3].textContent.trim();

                            if (operatorText) operators.add(operatorText);
                            if (rawAction) actions.add(rawAction);
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

                    if (searchInput) searchInput.addEventListener('input', applyAuditFilters);
                    if (filterOperator) filterOperator.addEventListener('change', applyAuditFilters);
                    if (filterAction) filterAction.addEventListener('change', applyAuditFilters);
                    if (filterIP) filterIP.addEventListener('change', applyAuditFilters);

                    if (resetBtn) {
                        resetBtn.addEventListener('click', function() {
                            if (searchInput) searchInput.value = '';
                            if (filterOperator) filterOperator.value = '';
                            if (filterAction) filterAction.value = '';
                            if (filterIP) filterIP.value = '';
                            applyAuditFilters();
                        });
                    }
                }
            }

            // Initial filter run for Audit Trail
            applyAuditFilters();
        });
    </script>
    <?php 
    if (file_exists('footer.php')) {
        include('footer.php');
    }
    ?>
</body>
</html>




