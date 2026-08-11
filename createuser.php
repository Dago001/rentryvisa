<?php 
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once('security.php');

if (!isset($_SESSION['username'])) {
    header("Location: index");
    die();
}

// Only Administrator and Supervisor roles can access User Management
$currentUserRole = $_SESSION['role'] ?? 'DataEntry';
if ($currentUserRole !== 'Admin' && $currentUserRole !== 'Supervisor') {
    header("Location: user_dashboard");
    exit();
}

require_once('db/db.php');

// Sync current user's profile photo from database to ensure it's always up to date
if (isset($_SESSION['user_id'])) {
    $current_uid = $_SESSION['user_id'];
    $photo_sync = mysqli_query($conn, "SELECT photo FROM users WHERE id = '$current_uid'");
    if ($photo_sync && $photo_row = mysqli_fetch_assoc($photo_sync)) {
        $_SESSION['photo'] = $photo_row['photo'];
    }
}

// Check if users table exists, create if not
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'users'");
if (mysqli_num_rows($table_check) == 0) {
    $create_table_sql = "CREATE TABLE users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        service_no VARCHAR(50) UNIQUE NOT NULL,
        fname VARCHAR(100) NOT NULL,
        lname VARCHAR(100) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role VARCHAR(50) NOT NULL,
        photo VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        status ENUM('active', 'inactive') DEFAULT 'active'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    if (!mysqli_query($conn, $create_table_sql)) {
        die("Error creating users table: " . mysqli_error($conn));
    }
}



$username = $_SESSION['username'];
$role = $_SESSION['role'];

// Get current user's ID from database
$current_user_query = mysqli_query($conn, "SELECT id FROM users WHERE service_no = '$username'");
$current_user = mysqli_fetch_assoc($current_user_query);
$current_user_id = $current_user ? $current_user['id'] : 0;

// Handle user status update
if (isset($_POST['update_status'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo "Security validation failed. Please refresh the page.";
        exit();
    }
    $user_id = mysqli_real_escape_string($conn, $_POST['user_id']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    if ($user_id != $current_user_id) {
        $update_sql = "UPDATE users SET status = '$status' WHERE id = '$user_id'";
        
        if (mysqli_query($conn, $update_sql)) {
            echo "User status updated successfully!";
        } else {
            echo "Error updating user status: " . mysqli_error($conn);
        }
    } else {
        echo "You cannot change your own status!";
    }
    exit();
}

// Handle user password reset
if (isset($_POST['reset_password'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo "Security validation failed. Please refresh the page.";
        exit();
    }
    $user_id = mysqli_real_escape_string($conn, $_POST['user_id']);
    
    // Hash the default password password@123
    $default_password = password_hash('password@123', PASSWORD_DEFAULT);
    
    // Update the password in database
    $update_sql = "UPDATE users SET password = '$default_password' WHERE id = '$user_id'";
    
    if (mysqli_query($conn, $update_sql)) {
        // Log this action to activity_log (audit trail)
        $username_who_did = $_SESSION['username'] ?? '';
        
        $user_q = mysqli_query($conn, "SELECT service_no FROM users WHERE id = '$user_id'");
        $reset_username = '';
        if ($user_q && mysqli_num_rows($user_q) > 0) {
            $user_r = mysqli_fetch_assoc($user_q);
            $reset_username = $user_r['service_no'];
        }
        
        $log_action = "RESET_PASSWORD";
        $log_desc = "Reset password to default 'password@123' for user: " . $reset_username;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $log_sql = "INSERT INTO activity_log (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)";
        $log_stmt = mysqli_prepare($conn, $log_sql);
        if ($log_stmt) {
            mysqli_stmt_bind_param($log_stmt, "ssss", $username_who_did, $log_action, $log_desc, $ip);
            mysqli_stmt_execute($log_stmt);
            mysqli_stmt_close($log_stmt);
        }
        
        echo "Password reset to default 'password@123' successfully!";
    } else {
        echo "Error resetting password: " . mysqli_error($conn);
    }
    exit();
}

// Handle user creation form submission
if (isset($_POST['regUser'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_msg = "Security validation failed. Please refresh the page.";
    } else {
        $serviceNo = mysqli_real_escape_string($conn, $_POST['serviceNo']);
        $userRole = mysqli_real_escape_string($conn, $_POST['userRole']);
        
        if ($currentUserRole === 'Supervisor' && $userRole === 'Admin') {
            $error_msg = "Error: Supervisors are not authorized to create Admin users.";
        } else {
            $fname = mysqli_real_escape_string($conn, $_POST['fname']);
            $lname = mysqli_real_escape_string($conn, $_POST['lname']);
            $email = mysqli_real_escape_string($conn, $_POST['email']);
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            
            $photo = '';
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
                $allowed = ['jpg', 'jpeg', 'png'];
                $filename = $_FILES['photo']['name'];
                $filetmp = $_FILES['photo']['tmp_name'];
                $fileSize = $_FILES['photo']['size'];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                
                if (in_array($ext, $allowed)) {
                    if ($fileSize <= 2097152) {
                        $newFilename = uniqid() . '.' . $ext;
                        $uploadPath = 'uploads/' . $newFilename;
                        
                        if (!is_dir('uploads')) {
                            mkdir('uploads', 0755, true);
                        }
                        
                        if (move_uploaded_file($filetmp, $uploadPath)) {
                            $photo = $uploadPath;
                        } else {
                            $error_msg = "Failed to upload file.";
                        }
                    } else {
                        $error_msg = "File size must be less than 2MB.";
                    }
                } else {
                    $error_msg = "Only JPG, JPEG, and PNG files are allowed.";
                }
            }
            
            if (!isset($error_msg)) {
                $check_sql = "SELECT id, service_no, email FROM users WHERE service_no = '$serviceNo' OR email = '$email' LIMIT 1";
                $check_result = mysqli_query($conn, $check_sql);
                
                if (mysqli_num_rows($check_result) > 0) {
                    $existing = mysqli_fetch_assoc($check_result);
                    if ($existing['service_no'] == $serviceNo) {
                        $error_msg = "Error: Service number '$serviceNo' already exists!";
                    } else {
                        $error_msg = "Error: Email '$email' already exists!";
                    }
                } else {
                    $sql = "INSERT INTO users (service_no, role, fname, lname, email, password, photo) 
                            VALUES ('$serviceNo', '$userRole', '$fname', '$lname', '$email', '$password', '$photo')";
                    
                    if (mysqli_query($conn, $sql)) {
                        $success_msg = "User created successfully!";
                        $_POST = array();
                    } else {
                        $error_msg = "Error: " . mysqli_error($conn);
                    }
                }
            }
        }
    }
}

// Fetch existing users for display
if ($currentUserRole === 'Supervisor') {
    $users_sql = "SELECT id, service_no, fname, lname, email, role, status, photo, created_at FROM users WHERE role != 'Admin' ORDER BY created_at DESC";
} else {
    $users_sql = "SELECT id, service_no, fname, lname, email, role, status, photo, created_at FROM users ORDER BY created_at DESC";
}
$users_result = mysqli_query($conn, $users_sql);
$total_users = mysqli_num_rows($users_result);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>NISREV - User Management</title>
    <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
    <!-- Bootstrap CSS -->
    <link href="vendors/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="vendors/font-awesome/css/font-awesome.min.css">
    
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
        
        /* Top Navigation - MATCHING re-entry-visa.php */
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
        
        /* Page Content */
        .page-content {
            padding: 20px;
            min-height: calc(100vh - 64px);
        }

        /* Welcome Banner */
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
        
        .welcome-banner h2 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .welcome-banner p {
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
        
        /* Page Title */
        .page-title h3 {
            color: var(--primary);
            font-weight: 600;
            margin-bottom: 20px;
            font-size: 1.4rem;
        }
        
        /* Card Styles */
        .card {
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
            border: none;
            margin-bottom: 20px;
            background: white;
        }
        
        .card-header {
            background: white;
            border-bottom: 1px solid #e1e5e9;
            padding: 18px 25px;
            border-radius: 12px 12px 0 0 !important;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-header h4 {
            color: var(--dark);
            margin: 0;
            font-weight: 600;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .card-body {
            padding: 25px;
        }
        
        /* Form Styles */
        .form-label {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 8px;
            font-size: 0.9rem;
        }
        
        .form-control, .form-select {
            border-radius: 8px;
            border: 2px solid #e1e5e9;
            padding: 10px 15px;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26, 92, 46, 0.1);
            outline: none;
        }
        
        /* Button Styles */
        .btn-success {
            background: linear-gradient(135deg, var(--secondary), #219a52);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-success:hover {
            background: linear-gradient(135deg, #219a52, #1e8a47);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(39, 174, 96, 0.3);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, var(--warning), #e67e22);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            transition: all 0.3s;
        }
        
        .btn-warning:hover {
            background: linear-gradient(135deg, #e67e22, #d35400);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(243, 156, 18, 0.3);
        }
        
        /* Alert Styles */
        .alert {
            border-radius: 12px;
            border: none;
            padding: 15px 20px;
            margin-bottom: 20px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
        }
        
        /* Table Styles */
        .table-responsive {
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid #e1e5e9;
        }
        
        .table {
            margin: 0;
            min-width: 700px;
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
        
        /* Premium Avatar styling */
        .avatar-container {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #1a5c2e;
            color: white;
            font-weight: 600;
            font-size: 0.85rem;
            border: 2px solid #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            flex-shrink: 0;
        }
        
        .avatar-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .avatar-initials {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
        }

        /* Profile cell block style */
        .profile-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .profile-details {
            display: flex;
            flex-direction: column;
            line-height: 1.25;
        }
        
        .profile-name {
            font-weight: 600;
            color: var(--dark);
        }
        
        .profile-service {
            font-size: 0.75rem;
            color: var(--gray);
            font-family: monospace;
        }

        .btn-action-user {
            padding: 5px 12px;
            font-size: 0.8rem;
            border-radius: 6px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }
        
        /* Badge Styles */
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.75rem;
        }
        
        .badge-admin {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .badge-supervisor {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        
        .badge-dataentry {
            background: #e8f5e9;
            color: #388e3c;
        }
        
        .badge-active {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
        }
        
        .badge-inactive {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        /* Role Cards */
        .role-item {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e1e5e9;
        }
        
        .role-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .role-title {
            font-weight: 600;
            margin-bottom: 4px;
            font-size: 0.95rem;
        }
        
        .role-description {
            font-size: 0.85rem;
            color: var(--gray);
        }
        
        /* Footer Styles */
        .main-footer {
            background: transparent;
            color: var(--gray);
            padding: 15px 20px;
            text-align: right;
            border-top: 1px solid #e1e5e9;
            font-size: 0.8rem;
        }
        
        .main-footer a {
            color: var(--secondary);
            text-decoration: none;
            font-weight: 500;
        }
        
        .main-footer a:hover {
            text-decoration: underline;
        }
        
        /* Responsive Styles - MATCHING re-entry-visa.php */
        /* Centralized Sidebar Responsive Styles moved to sidebar.php */
            
            .welcome-banner {
                text-align: center;
            }
            
            .datetime-display {
                text-align: center;
                margin-top: 15px;
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
            
            .welcome-banner {
                padding: 15px;
            }
            
            .welcome-banner h2 {
                font-size: 1.3rem;
            }
            
            .card {
                margin-bottom: 15px;
            }
            
            .card-body {
                padding: 20px;
            }
            
            .card-header {
                padding: 15px 20px;
            }
        }
        
        @media (max-width: 576px) {
            .sidebar {
                width: 85%;
                max-width: 280px;
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
                flex-direction: row;
                justify-content: space-between;
            }
            
            .page-content {
                padding: 12px;
            }
            
            .welcome-banner {
                padding: 12px;
            }
            
            .card-body {
                padding: 15px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn-warning, .btn-success {
                width: 100%;
                margin-bottom: 3px;
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
            
            .table thead th,
            .table tbody td {
                padding: 8px;
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
                <h4>User Management</h4>
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

        <!-- Page Content -->
        <div class="page-content">

            <!-- Page Title -->
            <div class="page-title">
                <h3>User Management</h3>
            </div>
            
            <!-- Display success/error messages -->
            <?php if (isset($success_msg)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fa fa-check-circle"></i> <?php echo $success_msg; ?>
                    <button type="button" class="btn-close" onclick="this.parentElement.style.display='none'">&times;</button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_msg)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fa fa-exclamation-circle"></i> <?php echo $error_msg; ?>
                    <button type="button" class="btn-close" onclick="this.parentElement.style.display='none'">&times;</button>
                </div>
            <?php endif; ?>

            <!-- Main Content Grid -->
            <div class="row">
                <div class="col-lg-8 col-md-7">
                    <!-- User Creation Form -->
                    <div class="card">
                        <div class="card-header">
                            <h4><i class="fa fa-user-plus" style="color: var(--secondary);"></i> Create New User</h4>
                        </div>
                        <div class="card-body">
                            <form class="form-horizontal" enctype="multipart/form-data" action="createuser" method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="serviceNo" class="form-label">Service No.</label>
                                        <input type="text" class="form-control" id="serviceNo" name="serviceNo" placeholder="NIS Service No." 
                                               value="<?php echo isset($_POST['serviceNo']) ? htmlspecialchars($_POST['serviceNo']) : ''; ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="userRole" class="form-label">User Role</label>
                                        <select name="userRole" class="form-select" id="userRole" required>
                                            <option value="">--Select User Role--</option>
                                            <?php if ($currentUserRole === 'Admin'): ?>
                                                <option value="Admin" <?php echo (isset($_POST['userRole']) && $_POST['userRole'] == 'Admin') ? 'selected' : ''; ?>>Administrator</option>
                                            <?php endif; ?>
                                            <?php if ($currentUserRole === 'Admin' || $currentUserRole === 'Supervisor'): ?>
                                                <option value="Supervisor" <?php echo (isset($_POST['userRole']) && $_POST['userRole'] == 'Supervisor') ? 'selected' : ''; ?>>Supervisor</option>
                                            <?php endif; ?>
                                            <option value="DataEntry" <?php echo (isset($_POST['userRole']) && $_POST['userRole'] == 'DataEntry') ? 'selected' : ''; ?>>Data Entry</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="fname" class="form-label">First Name</label>
                                        <input type="text" class="form-control" name="fname" id="fname" placeholder="First Name Here" 
                                               value="<?php echo isset($_POST['fname']) ? htmlspecialchars($_POST['fname']) : ''; ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="lname" class="form-label">Last Name</label>
                                        <input type="text" class="form-control" name="lname" id="lname" placeholder="Last Name Here" 
                                               value="<?php echo isset($_POST['lname']) ? htmlspecialchars($_POST['lname']) : ''; ?>" required>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" name="email" id="email" placeholder="Email Address here" 
                                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="password" class="form-label">Password</label>
                                        <input type="password" class="form-control" name="password" id="password" placeholder="Password" required>
                                    </div>
                                </div>
                                
                                <div class="row mb-4">
                                    <div class="col-md-12">
                                        <label for="photo" class="form-label">Passport Photograph (JPG or PNG)</label>
                                        <input name="photo" class="form-control" id="photo" type="file" accept=".jpg,.jpeg,.png" />
                                        <small class="text-muted">Max file size: 2MB</small>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-end">
                                    <button type="submit" name="regUser" class="btn btn-success">
                                        <i class="fa fa-user-plus"></i> Create User
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                </div> <!-- End of col-lg-8 col-md-7 -->
                
                <!-- Sidebar Cards -->
                <div class="col-lg-4 col-md-5">
                    <!-- User Roles Card -->
                    <div class="card">
                        <div class="card-header">
                            <h4><i class="fa fa-key" style="color: var(--warning);"></i> User Roles</h4>
                        </div>
                        <div class="card-body">
                            <div class="role-item">
                                <div class="role-title text-primary">Administrator</div>
                                <div class="role-description">Full system access with user management capabilities</div>
                            </div>
                            <div class="role-item">
                                <div class="role-title text-info">Supervisor</div>
                                <div class="role-description">Can create, edit, print and view reports</div>
                            </div>
                            <div class="role-item">
                                <div class="role-title text-success">Data Entry</div>
                                <div class="role-description">Can create, edit and print visa applications</div>
                            </div>
                        </div>
                    </div>
                </div> <!-- End of col-lg-4 col-md-5 -->
            </div> <!-- End of first row -->

            <!-- Existing Users Table - Expanded Full Width -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card shadow-sm border-0">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h4 class="mb-0 text-primary fw-bold"><i class="fa fa-users" style="color: var(--secondary);"></i> Existing Users (<?php echo $total_users; ?>)</h4>
                        </div>
                        <div class="card-body">
                            <?php if ($total_users > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0" style="font-size: 0.9rem; width: 100%;">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="padding-left: 20px;">User Profile</th>
                                                <th>Email</th>
                                                <th>Role</th>
                                                <th>Status</th>
                                                <th>Joined Date</th>
                                                <th style="padding-right: 20px; text-align: right;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            mysqli_data_seek($users_result, 0);
                                            while ($user = mysqli_fetch_assoc($users_result)): 
                                                $role_class = 'badge-' . strtolower($user['role']);
                                                if ($role_class == 'badge-dat') $role_class = 'badge-dataentry';
                                                if ($role_class == 'badge-adm') $role_class = 'badge-admin';
                                            ?>
                                            <tr>
                                                <td style="padding-left: 20px;">
                                                    <div class="profile-cell">
                                                        <div class="avatar-container">
                                                            <?php 
                                                            $user_photo_path = '';
                                                            if (!empty($user['photo'])) {
                                                                if (strpos($user['photo'], 'uploads/') === 0) {
                                                                    $user_photo_path = $user['photo'];
                                                                } else {
                                                                    $user_photo_path = 'uploads/' . $user['photo'];
                                                                }
                                                            }
                                                            if (!empty($user_photo_path) && file_exists($user_photo_path)): 
                                                            ?>
                                                                <img src="<?php echo htmlspecialchars($user_photo_path); ?>" alt="Profile" class="avatar-img">
                                                            <?php else: ?>
                                                                <div class="avatar-initials">
                                                                    <?php 
                                                                    $initials = strtoupper(substr($user['fname'] ?? '', 0, 1) . substr($user['lname'] ?? '', 0, 1));
                                                                    echo htmlspecialchars($initials ?: 'U');
                                                                    ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="profile-details">
                                                            <span class="profile-name"><?php echo htmlspecialchars($user['fname'] . ' ' . $user['lname']); ?></span>
                                                            <span class="profile-service">Service No: <?php echo htmlspecialchars($user['service_no']); ?></span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td>
                                                    <span class="badge <?php echo $role_class; ?>">
                                                        <?php echo htmlspecialchars($user['role']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?php echo strtolower($user['status']); ?>">
                                                        <?php echo ucfirst($user['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="text-muted" style="font-size: 0.85rem;">
                                                        <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                                    </span>
                                                </td>
                                                <td style="padding-right: 20px; text-align: right;">
                                                    <div class="d-flex justify-content-end align-items-center">
                                                        <?php if ($user['service_no'] != $username): ?>
                                                            <?php if ($user['status'] == 'active'): ?>
                                                                <button class="btn btn-sm btn-outline-danger btn-action-user deactivate-user" 
                                                                        data-id="<?php echo $user['id']; ?>" 
                                                                        data-name="<?php echo htmlspecialchars($user['fname'] . ' ' . $user['lname']); ?>">
                                                                    <i class="fa fa-ban"></i> Deactivate
                                                                </button>
                                                            <?php else: ?>
                                                                <button class="btn btn-sm btn-outline-success btn-action-user activate-user" 
                                                                        data-id="<?php echo $user['id']; ?>" 
                                                                        data-name="<?php echo htmlspecialchars($user['fname'] . ' ' . $user['lname']); ?>">
                                                                    <i class="fa fa-check"></i> Activate
                                                                </button>
                                                            <?php endif; ?>
                                                            <button class="btn btn-sm btn-outline-warning btn-action-user reset-password-btn ms-1" 
                                                                    data-id="<?php echo $user['id']; ?>" 
                                                                    data-name="<?php echo htmlspecialchars($user['fname'] . ' ' . $user['lname']); ?>">
                                                                <i class="fa fa-key"></i> Reset Password
                                                            </button>
                                                        <?php else: ?>
                                                            <span class="badge bg-light text-muted border" style="font-size: 0.75rem;">Current User</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fa fa-users fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No users found. Create your first user above.</p>
                                </div>
                            <?php endif; ?>
                        </div>
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
        // Function to update date and time safely
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
        
        // Update date and time immediately and then every second
        updateDateTime();
        setInterval(updateDateTime, 1000);
        
        // Sidebar functionality - MATCHING re-entry-visa.php
        document.addEventListener('DOMContentLoaded', function() {
            // File size validation
            document.getElementById('photo')?.addEventListener('change', function(e) {
                var file = e.target.files[0];
                if (file) {
                    var fileSize = file.size / 1024 / 1024; // in MB
                    if (fileSize > 2) {
                        alert('File size must be less than 2MB');
                        e.target.value = '';
                    }
                }
            });
            
            // Handle user activation/deactivation
            document.querySelectorAll('.deactivate-user').forEach(button => {
                button.addEventListener('click', function() {
                    const userId = this.getAttribute('data-id');
                    const userName = this.getAttribute('data-name');
                    
                    if (confirm(`Are you sure you want to deactivate ${userName}? This will prevent them from logging in.`)) {
                        updateUserStatus(userId, 'inactive');
                    }
                });
            });
            
            document.querySelectorAll('.activate-user').forEach(button => {
                button.addEventListener('click', function() {
                    const userId = this.getAttribute('data-id');
                    const userName = this.getAttribute('data-name');
                    
                    if (confirm(`Are you sure you want to activate ${userName}?`)) {
                        updateUserStatus(userId, 'active');
                    }
                });
            });

            document.querySelectorAll('.reset-password-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const userId = this.getAttribute('data-id');
                    const userName = this.getAttribute('data-name');
                    
                    if (confirm(`Are you sure you want to reset the password for ${userName} to 'password@123'?`)) {
                        resetUserPassword(userId);
                    }
                });
            });
            
            // Clear form after successful submission
            <?php if (isset($success_msg)): ?>
                setTimeout(function() {
                    const form = document.querySelector('form[enctype="multipart/form-data"]');
                    if (form) form.reset();
                }, 3000);
            <?php endif; ?>
        });

        function updateUserStatus(userId, status) {
            const formData = new FormData();
            formData.append('user_id', userId);
            formData.append('status', status);
            formData.append('update_status', true);
            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                alert(data);
                location.reload();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred.');
            });
        }
        
        function resetUserPassword(userId) {
            const formData = new FormData();
            formData.append('user_id', userId);
            formData.append('reset_password', true);
            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                alert(data);
                location.reload();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while resetting the password.');
            });
        }
    </script>
</body>
</html>

