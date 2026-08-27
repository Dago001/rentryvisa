<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once('security.php');

require_once('db/db.php');

// Check database connection
if (!$conn) {
    die("<div class='alert alert-danger'>Database connection failed</div>");
}

// Initialize variables
$visa_number = $passport_number = $surname = $given_name = $nationality = $place_of_issue = '';
$date_of_issue = $embassy = $authority = $within = $journey = $fees = $application_date = $status = $category = '';
$application_type = $number_of_dependencies = $visa_type = $gender = $mfa_letter = $covering_letter = $entry_stamp = $entry_stamp_date = $contract_duration = $old_visa_number = '';

// Check if ID parameter is provided
if(isset($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    
    // First, check what columns exist in the table
    $checkColumns = mysqli_query($conn, "DESCRIBE visa_applications");
    $columns = [];
    if ($checkColumns) {
        while ($row = mysqli_fetch_assoc($checkColumns)) {
            $columns[] = $row['Field'];
        }
        mysqli_free_result($checkColumns);
    }

    // Build query based on available columns
    $selectColumns = ['visa_number', 'passport_number', 'surname', 'given_name'];
    
    // Add optional columns if they exist
    $optionalColumns = [
        'nationality', 'place_of_issue', 'date_of_issue', 'embassy', 'authority',
        'within', 'validity_date', 'expiry_date', 'journey', 'purpose', 'travel_purpose',
        'fees', 'application_date', 'created_at', 'status', 'created_by', 'category',
        'application_type', 'number_of_dependencies', 'visa_type', 'gender', 'mfa_letter',
        'covering_letter', 'entry_stamp', 'entry_stamp_date', 'contract_duration', 'old_visa_number',
        'passport_date_of_issue', 'passport_date_of_expiry'
    ];
    
    foreach($optionalColumns as $col) {
        if(in_array($col, $columns)) {
            $selectColumns[] = $col;
        }
    }

    $sqluse = "SELECT " . implode(', ', $selectColumns) . " FROM visa_applications WHERE visa_number='$id'";
    $retrieve = mysqli_query($conn, $sqluse);
    
    if ($retrieve && mysqli_num_rows($retrieve) > 0) {
        $foundk = mysqli_fetch_assoc($retrieve);
        
        // Authorization check for DataEntry role
        $userRole = $_SESSION['role'] ?? 'DataEntry';
        $username = $_SESSION['username'] ?? '';
        if ($userRole === 'DataEntry' && (!isset($_GET['ref']) || $_GET['ref'] !== 'globalsearch') && isset($foundk['created_by']) && strcasecmp($foundk['created_by'], $username) !== 0) {
            header("Location: re-entry-visa?error=Access Denied");
            exit();
        }
        
        // Assign values with proper null checks
        $visa_number = strtoupper($foundk['visa_number'] ?? '');
        $passport_number = strtoupper($foundk['passport_number'] ?? '');
        $surname = strtoupper($foundk['surname'] ?? '');
        $given_name = strtoupper($foundk['given_name'] ?? '');
        $nationality = strtoupper($foundk['nationality'] ?? 'N/A');
        $place_of_issue = strtoupper($foundk['place_of_issue'] ?? 'N/A');
        $date_of_issue = formatNISDate($foundk['date_of_issue'] ?? '');
        $embassy = strtoupper($foundk['embassy'] ?? 'N/A');
        $authority = strtoupper($foundk['authority'] ?? 'N/A');
        $category = strtoupper($foundk['category'] ?? 'NONE');
        
        $application_type = htmlspecialchars($foundk['application_type'] ?? 'fresh');
        $number_of_dependencies = intval($foundk['number_of_dependencies'] ?? 0);
        $visa_type = strtoupper($foundk['visa_type'] ?? 'N/A');
        $gender = strtoupper($foundk['gender'] ?? 'N/A');
        $mfa_letter = strtoupper($foundk['mfa_letter'] ?? 'N/A');
        $covering_letter = strtoupper($foundk['covering_letter'] ?? 'N/A');
        $entry_stamp = strtoupper($foundk['entry_stamp'] ?? 'N/A');
        $entry_stamp_date = formatNISDate($foundk['entry_stamp_date'] ?? '');
        $contract_duration = strtoupper($foundk['contract_duration'] ?? 'N/A');
        $old_visa_number = strtoupper($foundk['old_visa_number'] ?? 'N/A');
        $passport_date_of_issue = in_array('passport_date_of_issue', $columns) ? formatNISDate($foundk['passport_date_of_issue'] ?? '') : 'N/A';
        $passport_date_of_expiry = in_array('passport_date_of_expiry', $columns) ? formatNISDate($foundk['passport_date_of_expiry'] ?? '') : 'N/A';
        
        // Check for within/validity period
        if (isset($foundk['within'])) {
            $within = formatNISDate($foundk['within']);
        } elseif (isset($foundk['validity_date'])) {
            $within = formatNISDate($foundk['validity_date']);
        } elseif (isset($foundk['expiry_date'])) {
            $within = formatNISDate($foundk['expiry_date']);
        } else {
            $within = 'N/A';
        }
        
        // Check for journey
        if (isset($foundk['journey'])) {
            $journey = strtoupper($foundk['journey']);
        } elseif (isset($foundk['purpose'])) {
            $journey = strtoupper($foundk['purpose']);
        } elseif (isset($foundk['travel_purpose'])) {
            $journey = strtoupper($foundk['travel_purpose']);
        } else {
            $journey = 'N/A';
        }
        
        // Check for fees
        $fees = strtoupper($foundk['fees'] ?? 'N/A');
        
        // Check for application date
        if (isset($foundk['application_date'])) {
            $application_date = $foundk['application_date'];
        } elseif (isset($foundk['created_at'])) {
            $application_date = $foundk['created_at'];
        } else {
            $application_date = 'N/A';
        }
        
        $status = $foundk['status'] ?? 'N/A';
        
        // Log this viewing action in the activity_log for audit trail
        $log_desc = "Viewed visa application details for " . $surname . ", " . $given_name . " (Visa No: " . $visa_number . ", Passport: " . $passport_number . ")";
        if (isset($_GET['ref']) && $_GET['ref'] === 'globalsearch') {
            $log_desc .= " via Global Search";
        }
        logActivity($conn, "VIEW", $log_desc);
    } else {
        // No record found
        header("Location: re-entry-visa?error=Record not found");
        exit();
    }
} else {
    // No ID provided
    header("Location: re-entry-visa?error=No record specified");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>NISREV - View Application</title>
    <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
    <!-- Bootstrap CSS -->
    <link href="vendors/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="vendors/font-awesome/css/font-awesome.min.css">
    
    <style>
        :root {
            --primary: #1a5c2e;
            --primary-hover: #113f1f;
            --secondary: #d4af37;
            --dark: #2c3e50;
            --light: #f5f7fa;
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
        
        /* Main Content wrapper */
        .main-content {
            min-height: 100vh;
            transition: all 0.3s ease;
        }
        
        /* Top Navigation styling */
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
        }
        
        /* Dashboard Container styling */
        .page-content {
            padding: 24px;
        }
        
        /* Visa Application Container (matching new-record.php) */
        .visa-application-container {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.08);
            border: 1px solid #e1e5e9;
            margin-bottom: 24px;
        }
        
        .form-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid var(--primary);
        }
        
        .visa-application-title {
            color: var(--primary);
            margin: 0;
            font-size: 1.6rem;
            font-weight: 700;
        }
        
        /* Forms and Sections styling (matching new-record.php) */
        .section {
            margin-bottom: 25px;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        
        .section-title {
            color: var(--primary);
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
        }
        
        .section-title i {
            color: var(--secondary);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 15px;
        }
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: var(--dark);
            font-size: 0.85rem;
        }
        
        /* Readonly value presentation designed to match input fields */
        .detail-value-field {
            width: 100%;
            padding: 10px 12px;
            border: 1.5px solid #e1e5e9;
            border-radius: 6px;
            background: white;
            color: var(--dark);
            font-size: 0.9rem;
            font-weight: 600;
            min-height: 41px;
            display: flex;
            align-items: center;
        }
        
        /* Status pill badge design */
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .status-pill.status-active {
            background-color: #dcfce7;
            color: #15803d;
        }
        
        .status-pill.status-pending {
            background-color: #fef9c3;
            color: #a16207;
        }
        
        .status-pill.status-expired {
            background-color: #fee2e2;
            color: #b91c1c;
        }
        
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            background-color: currentColor;
        }
        
        /* Premium Action buttons */
        .btn-back {
            background: white;
            border: 1.5px solid var(--primary);
            color: var(--primary);
            font-weight: 600;
            padding: 8px 18px;
            border-radius: 6px;
            transition: all 0.2s;
            font-size: 0.88rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-back:hover {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }
        
        .btn-action {
            font-weight: 600;
            padding: 8px 18px;
            border-radius: 6px;
            transition: all 0.2s;
            font-size: 0.88rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-gold {
            background-color: var(--secondary);
            border-color: var(--secondary);
            color: #2c3e50 !important;
        }
        
        .btn-gold:hover {
            background-color: #c5a030;
            border-color: #c5a030;
            color: #2c3e50 !important;
        }
        
        .btn-green {
            background-color: var(--primary);
            border-color: var(--primary);
            color: white !important;
        }
        
        .btn-green:hover {
            background-color: var(--primary-hover);
            border-color: var(--primary-hover);
        }
        
        /* Desktop Layout Offset */
        @media (min-width: 992px) {
            .main-content {
                margin-left: var(--sidebar-width);
            }
        }
        
        /* Responsive tweaks */
        @media (max-width: 768px) {
            .page-content {
                padding: 15px;
            }
            .top-nav {
                padding: 10px 15px;
            }
            .section {
                padding: 15px;
            }
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
        
        /* Professional print layout stylesheet overrides */
        @media print {
            .sidebar,
            .top-nav,
            .action-header,
            .main-footer {
                display: none !important;
            }
            
            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }
            
            .page-content {
                padding: 0 !important;
            }
            
            body {
                background: white !important;
            }
            
            .visa-application-container {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
                border-radius: 0 !important;
                padding: 15px !important;
            }
            
            .form-header {
                border-bottom: 2px solid #000 !important;
            }
            
            .section {
                background: white !important;
                border: 1px solid #ddd !important;
                page-break-inside: avoid;
            }
            
            .detail-value-field {
                background: white !important;
                border: 1px solid #ddd !important;
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
                <h4>Application Details</h4>
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

        <div class="page-content container-fluid">
            <!-- Actions Header -->
            <?php 
            $is_from_globalsearch = (isset($_GET['ref']) && $_GET['ref'] === 'globalsearch');
            ?>
            <div class="action-header" style="display: flex; justify-content: <?php echo $is_from_globalsearch ? 'space-between' : 'flex-end'; ?>; align-items: center; gap: 15px; margin-bottom: 20px; width: 100%;">
                <?php if ($is_from_globalsearch): ?>
                    <a href="globalsearch<?php echo isset($_GET['q']) ? '?q=' . urlencode($_GET['q']) : ''; ?>" class="btn btn-secondary d-inline-flex align-items-center gap-1" style="font-weight: 600; padding: 8px 16px; border-radius: 6px;">
                        <i class="fa fa-arrow-left"></i> Back to Global Search
                    </a>
                    <span class="badge bg-light text-muted border px-3 py-2" style="font-size: 0.82rem;">
                        <i class="fa fa-lock me-1"></i> Read-Only View (Global Search)
                    </span>
                <?php else: ?>
                    <?php if ($_SESSION['role'] !== 'ViewOnly'): ?>
                    <a href="card?id=<?php echo urlencode($visa_number); ?>" class="btn btn-action btn-gold text-dark">
                        <i class="fa fa-id-card"></i>Print Vignette
                    </a>
                    <?php endif; ?>
                    <button class="btn btn-action btn-green" onclick="window.print()">
                        <i class="fa fa-print"></i>Print Details
                    </button>
                <?php endif; ?>
            </div>

            <!-- Visa Application Card matching new-record.php layout structure -->
            <div class="visa-application-container">
                <!-- Header block matching new-record.php -->
                <div class="form-header flex-column flex-md-row gap-3">
                    <div>
                        <h2 class="visa-application-title">
                            <i class="fa fa-globe me-2"></i>Re-Entry Visa Application Details
                        </h2>
                    </div>
                    <div class="text-md-end d-flex flex-column align-items-md-end gap-2">
                        <span class="status-pill status-<?php echo strtolower($status); ?>">
                            <span class="status-dot"></span>
                            <?php echo htmlspecialchars($status); ?>
                        </span>
                        <span class="small text-muted font-monospace fw-bold">
                            Re-Entry Visa No: <?php echo htmlspecialchars($visa_number); ?>
                        </span>
                    </div>
                </div>

                <!-- Section: Personal & Passport Information -->
                <div class="section">
                    <h2 class="section-title"><i class="fa fa-user"></i> Personal & Passport Information</h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Passport Number</label>
                            <div class="detail-value-field font-monospace fw-bold"><?php echo htmlspecialchars($passport_number); ?></div>
                        </div>
                        <?php if (in_array('visa_type', $columns)): ?>
                        <div class="form-group">
                            <label>Visa Type</label>
                            <div class="detail-value-field"><?php echo htmlspecialchars($visa_type); ?></div>
                        </div>
                        <?php else: ?>
                        <div class="form-group"></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-row">
                        <?php if (in_array('passport_date_of_issue', $columns)): ?>
                        <div class="form-group">
                            <label>Passport Date of Issue</label>
                            <div class="detail-value-field"><?php echo htmlspecialchars($passport_date_of_issue); ?></div>
                        </div>
                        <?php else: ?>
                        <div class="form-group"></div>
                        <?php endif; ?>

                        <?php if (in_array('passport_date_of_expiry', $columns)): ?>
                        <div class="form-group">
                            <label>Passport Date of Expiry</label>
                            <div class="detail-value-field"><?php echo htmlspecialchars($passport_date_of_expiry); ?></div>
                        </div>
                        <?php else: ?>
                        <div class="form-group"></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Surname</label>
                            <div class="detail-value-field" style="text-transform: uppercase;"><?php echo htmlspecialchars($surname); ?></div>
                        </div>
                        <div class="form-group">
                            <label>Given Names</label>
                            <div class="detail-value-field" style="text-transform: uppercase;"><?php echo htmlspecialchars($given_name); ?></div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nationality</label>
                            <div class="detail-value-field"><?php echo htmlspecialchars($nationality); ?></div>
                        </div>
                        <?php if (in_array('gender', $columns)): ?>
                        <div class="form-group">
                            <label>Gender</label>
                            <div class="detail-value-field"><?php echo htmlspecialchars($gender); ?></div>
                        </div>
                        <?php else: ?>
                        <div class="form-group"></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-row">
                        <?php if (in_array('application_type', $columns)): ?>
                        <div class="form-group">
                            <label>Application Type</label>
                            <div class="detail-value-field"><?php echo ($application_type === 'renewal') ? 'RENEWAL APPLICATION' : 'FRESH APPLICATION'; ?></div>
                        </div>
                        <?php else: ?>
                        <div class="form-group"></div>
                        <?php endif; ?>
                        
                        <?php if ($application_type === 'renewal' && in_array('old_visa_number', $columns)): ?>
                        <div class="form-group">
                            <label>Old Re-Entry Visa Number</label>
                            <div class="detail-value-field font-monospace fw-bold"><?php echo htmlspecialchars($old_visa_number); ?></div>
                        </div>
                        <?php else: ?>
                        <div class="form-group"></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-row">
                        <?php if (in_array('number_of_dependencies', $columns)): ?>
                        <div class="form-group">
                            <label>Number of Dependencies</label>
                            <div class="detail-value-field"><?php echo htmlspecialchars($number_of_dependencies); ?></div>
                        </div>
                        <?php else: ?>
                        <div class="form-group"></div>
                        <?php endif; ?>
                        
                        <?php if (in_array('contract_duration', $columns)): ?>
                        <div class="form-group">
                            <label>Contract Duration</label>
                            <div class="detail-value-field" style="text-transform: uppercase;"><?php echo htmlspecialchars($contract_duration); ?></div>
                        </div>
                        <?php else: ?>
                        <div class="form-group"></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-row">
                        <?php if (in_array('mfa_letter', $columns)): ?>
                        <div class="form-group">
                            <label>MFA Letter of Introduction</label>
                            <div class="detail-value-field"><?php echo htmlspecialchars($mfa_letter); ?></div>
                        </div>
                        <?php else: ?>
                        <div class="form-group"></div>
                        <?php endif; ?>
                        
                        <?php if (in_array('covering_letter', $columns)): ?>
                        <div class="form-group">
                            <label>Covering Letter</label>
                            <div class="detail-value-field"><?php echo htmlspecialchars($covering_letter); ?></div>
                        </div>
                        <?php else: ?>
                        <div class="form-group"></div>
                        <?php endif; ?>
                    </div>

                    <?php if (in_array('entry_stamp_date', $columns)): ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Entry Stamp Date</label>
                            <div class="detail-value-field"><?php echo htmlspecialchars($entry_stamp_date); ?></div>
                        </div>
                        <div class="form-group"></div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Section: Re-Entry Visa Details -->
                <div class="section">
                    <h2 class="section-title"><i class="fa fa-file-text-o"></i> Re-Entry Visa Details</h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Re-Entry Visa Number</label>
                            <div class="detail-value-field font-monospace fw-bold"><?php echo htmlspecialchars($visa_number); ?></div>
                        </div>
                        <div class="form-group"></div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Embassy/High Commission</label>
                            <div class="detail-value-field" style="text-transform: uppercase;"><?php echo htmlspecialchars($embassy); ?></div>
                        </div>
                        <div class="form-group">
                            <label>Place of Issue</label>
                            <div class="detail-value-field" style="text-transform: uppercase;"><?php echo htmlspecialchars($place_of_issue); ?></div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Date of Issue</label>
                            <div class="detail-value-field"><?php echo htmlspecialchars($date_of_issue); ?></div>
                        </div>
                        <div class="form-group">
                            <label>Authority</label>
                            <div class="detail-value-field"><?php echo htmlspecialchars($authority); ?></div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Fees/Tax</label>
                            <div class="detail-value-field fw-bold text-success" style="background-color: #f0fdf4; border-color: #bbf7d0;"><?php echo htmlspecialchars($fees); ?></div>
                        </div>
                        <div class="form-group">
                            <label>Journey Type</label>
                            <div class="detail-value-field" style="text-transform: uppercase;"><?php echo htmlspecialchars($journey); ?></div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Category</label>
                            <div class="detail-value-field" style="text-transform: uppercase;"><?php echo htmlspecialchars($category); ?></div>
                        </div>
                        <div class="form-group">
                            <label>Validity Period (Within)</label>
                            <div class="detail-value-field"><?php echo htmlspecialchars($within); ?></div>
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
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-format dates nicely in the browser
            const dateElements = document.querySelectorAll('.detail-value-field');
            dateElements.forEach(element => {
                const text = element.textContent.trim();
                // Simple ISO date format regex
                if (text.match(/^\d{4}-\d{2}-\d{2}$/)) {
                    const date = new Date(text);
                    if (!isNaN(date)) {
                        element.textContent = date.toLocaleDateString('en-US', {
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric'
                        });
                    }
                }
            });
        });
    </script>
</body>
</html>
<?php
// Close database connection
mysqli_close($conn);
?>

