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
if ($userRole === 'ViewOnly' || strcasecmp($userRole, 'viewonly') === 0 || strcasecmp($userRole, 'view only') === 0) {
    header("Location: index");
    exit();
}
$isDataEntry = ($userRole === 'DataEntry' || strcasecmp($userRole, 'dataentry') === 0 || strcasecmp($userRole, 'data entry') === 0);
$username = $_SESSION['username'] ?? 'User';
$fullName = $_SESSION['full_name'] ?? $username;
$safeUsername = mysqli_real_escape_string($conn, $username);

// Get current page for active menu
$current_page = basename($_SERVER['PHP_SELF']);

// Check if database connection is successful
if (!$conn) {
    die("<div class='alert alert-danger'>Database connection failed: " . htmlspecialchars(mysqli_connect_error()) . "</div>");
}

// 1. Fetch Dynamic Printing Statistics (Scoped to officer if DataEntry, all if Admin/Supervisor)
if ($isDataEntry) {
    $stats_sql = "SELECT 
        COUNT(*) as total_records,
        COALESCE(SUM(CASE WHEN print_status = 1 THEN 1 ELSE 0 END), 0) as total_printed,
        COALESCE(SUM(CASE WHEN print_status = 0 OR print_status IS NULL THEN 1 ELSE 0 END), 0) as yet_to_print,
        COALESCE(SUM(CASE WHEN print_status = 1 AND DATE(printed_at) = CURDATE() THEN 1 ELSE 0 END), 0) as printed_today
    FROM visa_applications
    WHERE created_by = '$safeUsername' OR printed_by = '$safeUsername'";
} else {
    $stats_sql = "SELECT 
        COUNT(*) as total_records,
        COALESCE(SUM(CASE WHEN print_status = 1 THEN 1 ELSE 0 END), 0) as total_printed,
        COALESCE(SUM(CASE WHEN print_status = 0 OR print_status IS NULL THEN 1 ELSE 0 END), 0) as yet_to_print,
        COALESCE(SUM(CASE WHEN print_status = 1 AND DATE(printed_at) = CURDATE() THEN 1 ELSE 0 END), 0) as printed_today
    FROM visa_applications";
}

$stats_res = mysqli_query($conn, $stats_sql);
$stats_data = ($stats_res) ? mysqli_fetch_assoc($stats_res) : [];
$stats = [
    'total_records' => intval($stats_data['total_records'] ?? 0),
    'total_printed' => intval($stats_data['total_printed'] ?? 0),
    'yet_to_print' => intval($stats_data['yet_to_print'] ?? 0),
    'printed_today' => intval($stats_data['printed_today'] ?? 0)
];

// 2. Fetch all system users & officers who printed records for the dropdown filter
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
$printers_q = mysqli_query($conn, "SELECT DISTINCT printed_by FROM visa_applications WHERE printed_by IS NOT NULL AND printed_by != ''");
if ($printers_q) {
    while ($p = mysqli_fetch_assoc($printers_q)) {
        $pb = trim($p['printed_by']);
        if (!empty($pb) && !isset($all_officers[$pb])) {
            $all_officers[$pb] = [
                'value' => $pb,
                'label' => $pb
            ];
        }
    }
}

// 3. Query visa applications
$sqluse = "SELECT 
    v.id,
    v.visa_number,
    v.passport_number,
    v.surname,
    v.given_name,
    v.nationality,
    v.within,
    v.journey,
    v.application_type,
    v.category,
    v.print_status,
    v.printed_by,
    v.printed_at,
    v.created_by,
    v.created_at,
    v.application_date,
    u_c.fname as creator_fname,
    u_c.lname as creator_lname,
    u_p.fname as printer_fname,
    u_p.lname as printer_lname
FROM visa_applications v
LEFT JOIN users u_c ON v.created_by = u_c.service_no
LEFT JOIN users u_p ON v.printed_by = u_p.service_no";

if ($isDataEntry) {
    $sqluse .= " WHERE v.created_by = '$safeUsername' OR v.printed_by = '$safeUsername'";
}

$sqluse .= " ORDER BY v.id DESC";

$retrieve = mysqli_query($conn, $sqluse);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>NISREV - Print Re-Entry Visas</title>
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
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e1e5e9;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            overflow: hidden;
        }

        .user-avatar img { width: 100%; height: 100%; object-fit: cover; }
        
        .logout-btn {
            background: transparent;
            border: 1px solid var(--danger);
            color: var(--danger);
            padding: 5px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.8rem;
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
        .stat-card.card-printed .stat-number { color: #059669; }
        .stat-card.card-pending .stat-number { color: #ea580c; }
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
            min-width: 860px;
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
            vertical-align: middle;
        }

        .table thead th.th-sno {
            width: 50px;
            text-align: center;
        }
        
        .table tbody td {
            padding: 10px 12px;
            vertical-align: middle;
            border-color: #f1f5f9;
            font-size: 0.86rem;
            color: #334155;
        }
        
        .table tbody tr:hover {
            background-color: #f8fafc;
        }

        /* Application Type Badges */
        .badge-app-fresh {
            background-color: #e0f2fe;
            color: #0369a1;
            border: 1px solid #bae6fd;
            font-weight: 600;
            padding: 3px 7px;
            border-radius: 4px;
            font-size: 0.74rem;
        }
        
        .badge-app-renewal {
            background-color: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
            font-weight: 600;
            padding: 3px 7px;
            border-radius: 4px;
            font-size: 0.74rem;
        }

        /* Print Status Badges */
        .badge-print-status {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 7px;
            border-radius: 4px;
            font-size: 0.74rem;
            font-weight: 700;
        }
        
        .badge-print-status.status-printed {
            background-color: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        
        .badge-print-status.status-pending {
            background-color: #fef9c3;
            color: #854d0e;
            border: 1px solid #fef08a;
        }

        .print-meta-officer {
            font-size: 0.72rem;
            color: #64748b;
            margin-top: 2px;
            display: flex;
            align-items: center;
            gap: 3px;
        }

        /* Action Print / Reprint Buttons */
        .btn-print {
            background-color: #1a5c2e !important;
            border: 1px solid #1a5c2e !important;
            color: #ffffff !important;
            font-size: 0.78rem !important;
            padding: 4px 11px !important;
            border-radius: 5px !important;
            font-weight: 600 !important;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(26, 92, 46, 0.2);
        }
        
        .btn-print:hover {
            background-color: #144824 !important;
            border-color: #144824 !important;
            color: #ffffff !important;
            box-shadow: 0 3px 8px rgba(26, 92, 46, 0.35);
            transform: translateY(-1px);
        }

        .btn-reprint {
            background-color: #334155 !important;
            border: 1px solid #334155 !important;
            color: #ffffff !important;
            font-size: 0.78rem !important;
            padding: 4px 11px !important;
            border-radius: 5px !important;
            font-weight: 600 !important;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(51, 65, 85, 0.25);
        }
        
        .btn-reprint:hover {
            background-color: #1e293b !important;
            border-color: #1e293b !important;
            color: #ffffff !important;
            box-shadow: 0 3px 8px rgba(30, 41, 59, 0.35);
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

        .no-results {
            text-align: center;
            padding: 36px 15px;
            color: #64748b;
            display: none;
            background: #ffffff;
            border-radius: 8px;
        }

        /* Print Media Styles */
        @media print {
            .top-nav, .sidebar, .welcome-banner, .stats-grid, .search-box-compact, .filter-control-box, #clearSearch, .pagination-footer, .btn-print, .btn-reprint {
                display: none !important;
            }
            .page-content {
                padding: 0 !important;
            }
            .card {
                box-shadow: none !important;
                border: none !important;
            }
            .table-responsive {
                border: none !important;
                overflow: visible !important;
            }
            .table {
                width: 100% !important;
            }
            tbody tr {
                display: table-row !important;
                page-break-inside: avoid;
            }
        }

        /* Responsive Breakpoints */
        @media (max-width: 991px) {
            .welcome-banner {
                text-align: center;
            }
            .datetime-display {
                text-align: center;
                margin-top: 10px;
            }
            .card-title-section {
                flex-direction: column;
                align-items: flex-start;
            }
            .filter-and-search-group {
                width: 100%;
            }
        }
        
        @media (max-width: 768px) {
            .page-content {
                padding: 14px;
            }
            .search-box-compact {
                width: 100%;
                max-width: 100%;
            }
            .filter-select-compact {
                max-width: 100%;
                width: 100%;
            }
            .filter-control-box {
                width: 100%;
                flex-direction: column;
                align-items: flex-start;
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
                <h4>Print Re-Entry Visas</h4>
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
                <a href="logout" class="logout-btn">Logout</a>
            </div>
        </nav>

        <!-- Page Content -->
        <div class="page-content">

            <!-- Welcome Banner -->
            <div class="welcome-banner">
                <div class="row align-items-center">
                    <div class="col-lg-7 col-md-7">
                        <div class="welcome-text">
                            <h2 style="font-size: 1.45rem; font-weight: 700; letter-spacing: -0.3px; margin-bottom: 6px;"><i class="fa fa-id-card-o text-success me-2"></i>Visa Vignette Printing Center</h2>
                            <p style="font-size: 0.88rem; color: #cbd5e1; font-weight: 500; margin-bottom: 0;">
                                <i class="fa fa-shield" style="color: #22c55e; margin-right: 6px;"></i>Generate, monitor, and print official Re-Entry Visa vignette cards
                            </p>
                        </div>
                    </div>
                    <div class="col-lg-5 col-md-5">
                        <div class="datetime-display">
                            <div id="currentDateTime" style="font-size: 0.98rem; font-weight: 700; letter-spacing: 0.2px; color: #f8fafc; margin-bottom: 4px;">Loading date...</div>
                            <div class="location" style="font-size: 0.84rem; color: #94a3b8; font-weight: 600; display: inline-flex; align-items: center; gap: 5px;">
                                <i class="fa fa-map-marker" style="color: #ef4444;"></i> NIS Headquarters, Abuja
                            </div>
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

                <!-- Stat 2: Total Printed Cards -->
                <div class="stat-card card-printed">
                    <div class="stat-card-icon" style="color: #059669;"><i class="fa fa-check-circle-o"></i></div>
                    <div class="stat-number"><?php echo number_format($stats['total_printed']); ?></div>
                    <div class="stat-label">Total Visas Printed</div>
                </div>

                <!-- Stat 3: Yet to be Printed Cards -->
                <div class="stat-card card-pending">
                    <div class="stat-card-icon" style="color: #ea580c;"><i class="fa fa-print"></i></div>
                    <div class="stat-number"><?php echo number_format($stats['yet_to_print']); ?></div>
                    <div class="stat-label">Yet to be Printed Visas</div>
                </div>

                <!-- Stat 4: Cards Printed Today -->
                <div class="stat-card card-today">
                    <div class="stat-card-icon" style="color: #2563eb;"><i class="fa fa-calendar-check-o"></i></div>
                    <div class="stat-number"><?php echo number_format($stats['printed_today']); ?></div>
                    <div class="stat-label">Total Visas Printed Today</div>
                </div>
            </div>
            
            <!-- Vignette Printing Table Card -->
            <div class="card">
                <div class="card-body">
                    <!-- Title Section -->
                    <div class="card-title-section">
                        <h5 class="card-title">
                            <i class="fa fa-list-alt text-success"></i> Vignette Processing & Printing Queue
                        </h5>
                    </div>
                    
                    <!-- Dedicated Clean Toolbar (Custom Executive Responsive Flexbox) -->
                    <div class="vignette-toolbar">
                        <div class="vignette-toolbar-grid">
                            <!-- Filter 1: Printed By Officer -->
                            <div class="vignette-field vignette-field-user">
                                <span class="vignette-field-addon">
                                    <i class="fa fa-user text-success"></i> <?php echo $isDataEntry ? 'Officer:' : 'Printed By:'; ?>
                                </span>
                                <?php if ($isDataEntry): ?>
                                    <select id="filterUser" class="vignette-select" disabled>
                                        <option value=""><?php echo htmlspecialchars($fullName . ' (' . $username . ')'); ?></option>
                                    </select>
                                <?php else: ?>
                                    <select id="filterUser" class="vignette-select">
                                        <option value="">All Officers / Users</option>
                                        <option value="__ANY_PRINTED__">✓ All Printed Applications</option>
                                        <option value="__UNPRINTED__">⏳ Yet to be Printed (Pending)</option>
                                        <optgroup label="Filter by Specific Officer">
                                            <?php foreach ($all_officers as $off): ?>
                                                <option value="<?php echo htmlspecialchars($off['value']); ?>">
                                                    <?php echo htmlspecialchars($off['label']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    </select>
                                <?php endif; ?>
                            </div>

                            <!-- Filter 2: Print Status -->
                            <div class="vignette-field vignette-field-status">
                                <span class="vignette-field-addon">
                                    <i class="fa fa-tag text-success"></i> Status:
                                </span>
                                <select id="filterStatus" class="vignette-select">
                                    <option value="">All Statuses</option>
                                    <option value="printed">Printed Only</option>
                                    <option value="pending">Yet to Print</option>
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
                    
                    <!-- Table Container -->
                    <div class="table-responsive">
                        <table id="applicationsTable" class="table table-striped table-hover table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th class="th-sno">S/N</th>
                                    <th>Visa No.</th>
                                    <th>Surname</th>
                                    <th>Given Name</th>
                                    <th>Passport No.</th>
                                    <th>Type</th>
                                    <th>Journey</th>
                                    <th>Validity (Within)</th>
                                    <th>Print Status</th>
                                    <th style="width: 105px; text-align: center;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="applicationsTableBody">
                                <?php
                                $sno = 1;
                                if ($retrieve && mysqli_num_rows($retrieve) > 0) {
                                    while ($ret = mysqli_fetch_assoc($retrieve)) {
                                        $visa_number = htmlspecialchars($ret['visa_number']);
                                        $surname = htmlspecialchars($ret['surname']);
                                        $given_name = htmlspecialchars($ret['given_name']);
                                        $passport_number = htmlspecialchars($ret['passport_number']);
                                        $application_type = isset($ret['application_type']) ? htmlspecialchars($ret['application_type']) : 'fresh';
                                        $application_type_label = ($application_type === 'renewal') ? 'Renewal' : (($application_type === 'fresh') ? 'Fresh' : ucfirst(strtolower($application_type)));
                                        $journey = isset($ret['journey']) ? htmlspecialchars($ret['journey']) : 'N/A';
                                        $within = isset($ret['within']) ? htmlspecialchars($ret['within']) : 'N/A';
                                        
                                        $print_status = intval($ret['print_status'] ?? 0);
                                        $printed_by = htmlspecialchars($ret['printed_by'] ?? '');
                                        $printed_at = !empty($ret['printed_at']) ? date('d M Y, h:i A', strtotime($ret['printed_at'])) : '';
                                        $created_by = htmlspecialchars($ret['created_by'] ?? '');
                                        $creator_name = trim(($ret['creator_fname'] ?? '') . ' ' . ($ret['creator_lname'] ?? ''));
                                        $printer_name = trim(($ret['printer_fname'] ?? '') . ' ' . ($ret['printer_lname'] ?? ''));
                                        
                                        $jsVisa = json_encode($ret['visa_number']);
                                ?>
                                <tr class="app-row" 
                                    data-visa="<?php echo strtolower($visa_number); ?>"
                                    data-surname="<?php echo strtolower($surname); ?>"
                                    data-givenname="<?php echo strtolower($given_name); ?>"
                                    data-passport="<?php echo strtolower($passport_number); ?>"
                                    data-type="<?php echo strtolower($application_type); ?>"
                                    data-print-status="<?php echo $print_status ? 'printed' : 'pending'; ?>"
                                    data-printed-by="<?php echo strtolower($printed_by); ?>"
                                    data-printed-name="<?php echo strtolower(htmlspecialchars($printer_name)); ?>"
                                    data-created-by="<?php echo strtolower($created_by); ?>"
                                    data-created-name="<?php echo strtolower(htmlspecialchars($creator_name)); ?>">
                                    <td class="cell-sno text-center fw-bold text-muted"><?php echo $sno; ?></td>
                                    <td class="fw-bold text-dark font-monospace"><?php echo $visa_number; ?></td>
                                    <td class="fw-semibold text-dark"><?php echo $surname; ?></td>
                                    <td><?php echo $given_name; ?></td>
                                    <td class="font-monospace text-dark"><?php echo $passport_number; ?></td>
                                    <td>
                                        <span class="badge <?php echo ($application_type === 'renewal') ? 'badge-app-renewal' : 'badge-app-fresh'; ?>">
                                            <?php echo $application_type_label; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $journey; ?></td>
                                    <td><?php echo $within; ?></td>
                                    <td>
                                        <?php if ($print_status === 1): ?>
                                            <span class="badge-print-status status-printed" title="<?php echo !empty($printed_at) ? "Printed on {$printed_at}" : 'Printed'; ?>">
                                                <i class="fa fa-check-circle"></i> Printed
                                            </span>
                                            <?php if (!empty($printed_by)): ?>
                                                <div class="print-meta-officer"><i class="fa fa-user-circle"></i> <?php echo $printed_by; ?></div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge-print-status status-pending">
                                                <i class="fa fa-clock-o"></i> Yet to Print
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($print_status === 1): ?>
                                            <a href="card?id=<?php echo urlencode($ret['visa_number']); ?>" class="btn btn-reprint" title="Reprint Visa Vignette">
                                                <i class="fa fa-print"></i> Reprint
                                            </a>
                                        <?php else: ?>
                                            <a href="card?id=<?php echo urlencode($ret['visa_number']); ?>" class="btn btn-print" title="Print Visa Vignette">
                                                <i class="fa fa-print"></i> Print
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php
                                        $sno++;
                                    }
                                    mysqli_free_result($retrieve);
                                } else {
                                    echo "<tr><td colspan='10' class='text-center py-4 text-muted'><i class='fa fa-info-circle me-1'></i> No records found.</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                        
                        <div id="noResults" class="no-results">
                            <i class="fa fa-search fa-2x text-muted mb-2 d-block"></i>
                            <strong>No matching applications found</strong>
                            <p class="small text-muted mb-0">Try changing your search term or selecting a different user/status filter.</p>
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
        
        // Print / Reprint Visa Handler
        function printVisa(id) {
            if (!id) {
                alert('Invalid visa record identifier.');
                return;
            }
            window.location.href = 'card?id=' + encodeURIComponent(id);
        }
        
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
            
            const userFilter = document.getElementById('filterUser');
            const selectedUser = userFilter ? userFilter.value.toLowerCase().trim() : '';
            
            const statusFilter = document.getElementById('filterStatus');
            const selectedStatus = statusFilter ? statusFilter.value.toLowerCase().trim() : '';

            // Filter rows based on search, status, and user
            filteredRows = allRows.filter(row => {
                const rowStatus = (row.getAttribute('data-print-status') || '').toLowerCase().trim(); // 'printed' or 'pending'
                const rowPrintedBy = (row.getAttribute('data-printed-by') || '').toLowerCase().trim();
                const rowPrintedName = (row.getAttribute('data-printed-name') || '').toLowerCase().trim();
                const rowCreatedBy = (row.getAttribute('data-created-by') || '').toLowerCase().trim();
                const rowCreatedName = (row.getAttribute('data-created-name') || '').toLowerCase().trim();

                // 1. Status Filter
                if (selectedStatus && selectedStatus !== rowStatus) {
                    return false;
                }

                // 2. User / Printed By Filter
                if (selectedUser) {
                    if (selectedUser === '__any_printed__') {
                        if (rowStatus !== 'printed') return false;
                    } else if (selectedUser === '__unprinted__') {
                        if (rowStatus !== 'pending') return false;
                    } else {
                        const matchesPrinter = (rowPrintedBy === selectedUser || rowPrintedBy.includes(selectedUser) || (rowPrintedName && rowPrintedName.includes(selectedUser)));
                        const matchesCreator = (rowCreatedBy === selectedUser || rowCreatedBy.includes(selectedUser) || (rowCreatedName && rowCreatedName.includes(selectedUser)));

                        if (selectedStatus === 'printed') {
                            // If "Printed Only" is selected, match work printed by this officer
                            if (!matchesPrinter) {
                                return false;
                            }
                        } else if (selectedStatus === 'pending') {
                            // If "Yet to Print" is selected, match work created by this officer awaiting printing
                            if (!matchesCreator) {
                                return false;
                            }
                        } else {
                            // If "All Statuses" is selected, display all work associated with this officer (either printed or created)
                            if (!matchesPrinter && !matchesCreator) {
                                return false;
                            }
                        }
                    }
                }

                // 3. Search Query Filter (Searches visa, surname, given name, passport, printed by, created by)
                if (searchWords.length > 0) {
                    const visa = row.getAttribute('data-visa') || '';
                    const surname = row.getAttribute('data-surname') || '';
                    const givenName = row.getAttribute('data-givenname') || '';
                    const passport = row.getAttribute('data-passport') || '';
                    const combinedText = `${visa} ${surname} ${givenName} ${passport} ${rowPrintedBy} ${rowPrintedName} ${rowCreatedBy} ${rowCreatedName}`.toLowerCase();
                    
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
            const userFilter = document.getElementById('filterUser');
            const statusFilter = document.getElementById('filterStatus');
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

            if (statusFilter) {
                statusFilter.addEventListener('change', function() {
                    applyFiltersAndPagination(1);
                });
            }

            if (clearBtn) {
                clearBtn.addEventListener('click', function() {
                    if (searchInput) searchInput.value = '';
                    if (userFilter) userFilter.value = '';
                    if (statusFilter) statusFilter.value = '';
                    applyFiltersAndPagination(1);
                    if (searchInput) searchInput.focus();
                });
            }

            // Initial pagination render on load
            applyFiltersAndPagination(1);
        });
    </script>
</body>

