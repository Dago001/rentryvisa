<?php 
ob_start();
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
    if ($currentUserRole === 'ViewOnly' || strcasecmp($currentUserRole, 'viewonly') === 0 || strcasecmp($currentUserRole, 'view only') === 0) {
        header("Location: index");
    } else {
        header("Location: user_dashboard");
    }
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

$user_created_detail = null;
if (isset($_SESSION['user_created_detail'])) {
    $user_created_detail = $_SESSION['user_created_detail'];
    unset($_SESSION['user_created_detail']);
}
$success_msg = null;
if (isset($_SESSION['success_msg'])) {
    $success_msg = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
} elseif (isset($_GET['success']) && !$user_created_detail) {
    $success_msg = "User created successfully!";
}
$error_msg = null;
if (isset($_SESSION['error_msg'])) {
    $error_msg = $_SESSION['error_msg'];
    unset($_SESSION['error_msg']);
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
            $user_q = mysqli_query($conn, "SELECT service_no, fname, lname FROM users WHERE id = '$user_id'");
            $target_user = '';
            if ($user_q && $r = mysqli_fetch_assoc($user_q)) {
                $target_user = $r['service_no'] . ' (' . trim($r['fname'] . ' ' . $r['lname']) . ')';
            } else {
                $target_user = $user_id;
            }
            logActivity($conn, "UPDATE_USER_STATUS", "Changed status to '" . strtoupper($status) . "' for user: " . $target_user);
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
    
    // Update the password in database and mark password reset required
    $update_sql = "UPDATE users SET password = '$default_password', password_reset_required = 1 WHERE id = '$user_id'";
    
    if (mysqli_query($conn, $update_sql)) {
        $user_q = mysqli_query($conn, "SELECT service_no, email, fname, lname FROM users WHERE id = '$user_id'");
        $reset_username = '';
        if ($user_q && mysqli_num_rows($user_q) > 0) {
            $user_r = mysqli_fetch_assoc($user_q);
            $reset_username = $user_r['service_no'];
        }
        
        logActivity($conn, "RESET_PASSWORD", "Reset password to default 'password@123' for user: " . $reset_username);
        
        echo "Password reset to default 'password@123' successfully for ($reset_username)!";
    } else {
        echo "Error resetting password: " . mysqli_error($conn);
    }
    exit();
}

// Handle Google Authenticator (MFA) reset
if (isset($_POST['reset_mfa'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo "Security validation failed. Please refresh the page.";
        exit();
    }
    $user_id = intval($_POST['user_id']);
    
    // Clear the google_auth_secret in database
    $stmt = mysqli_prepare($conn, "UPDATE users SET google_auth_secret = NULL WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $user_q = mysqli_query($conn, "SELECT service_no FROM users WHERE id = '$user_id'");
        $reset_username = '';
        if ($user_q && mysqli_num_rows($user_q) > 0) {
            $user_r = mysqli_fetch_assoc($user_q);
            $reset_username = $user_r['service_no'];
        }
        
        logActivity($conn, "RESET_GOOGLE_AUTH", "Reset Google Authenticator barcode for user: " . $reset_username);
        
        echo "Google Authenticator barcode has been reset successfully!\n\nThe user will be prompted to scan a new barcode on their next login.";
    } else {
        echo "Error resetting Google Authenticator: " . mysqli_error($conn);
    }
    mysqli_stmt_close($stmt);
    exit();
}

// Handle user role update
if (isset($_POST['update_role'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo "Security validation failed. Please refresh the page.";
        exit();
    }
    $user_id = intval($_POST['user_id']);
    $new_role = trim($_POST['role'] ?? '');
    
    if (!in_array($new_role, ['Admin', 'Supervisor', 'DataEntry', 'ViewOnly'])) {
        echo "Invalid user role selected.";
        exit();
    }
    
    if ($currentUserRole === 'Supervisor' && $new_role === 'Admin') {
        echo "Supervisors are not authorized to assign Admin role.";
        exit();
    }
    
    $stmt = mysqli_prepare($conn, "UPDATE users SET role = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "si", $new_role, $user_id);
    if (mysqli_stmt_execute($stmt)) {
        $user_q = mysqli_query($conn, "SELECT service_no, fname, lname FROM users WHERE id = '$user_id'");
        $target_user = '';
        if ($user_q && $r = mysqli_fetch_assoc($user_q)) {
            $target_user = $r['service_no'] . ' (' . trim($r['fname'] . ' ' . $r['lname']) . ')';
        } else {
            $target_user = $user_id;
        }
        logActivity($conn, "UPDATE_USER_ROLE", "Updated role to '$new_role' for user: " . $target_user);
        echo "User role updated successfully!";
    } else {
        echo "Error updating user role: " . mysqli_error($conn);
    }
    mysqli_stmt_close($stmt);
    exit();
}

// Handle user creation form submission
if (isset($_POST['regUser']) || isset($_POST['serviceNo'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_msg = "Security validation failed. Please refresh the page.";
    } else {
        $serviceNo = trim($_POST['serviceNo'] ?? '');
        $userRole = trim($_POST['userRole'] ?? $_POST['role'] ?? '');
        $fname = trim($_POST['fname'] ?? '');
        $lname = trim($_POST['lname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password_raw = trim($_POST['password'] ?? '');
        
        // Auto-assign default password if not provided
        if (empty($password_raw)) {
            $password_raw = 'password@123';
        }
        
        if (empty($serviceNo)) {
            $error_msg = "Error: Service Number is required.";
        } elseif (empty($userRole)) {
            $error_msg = "Error: User Role is required. Please select a valid role.";
        } elseif (!in_array($userRole, ['Admin', 'Supervisor', 'DataEntry', 'ViewOnly'])) {
            $error_msg = "Error: Invalid user role selected.";
        } elseif ($currentUserRole === 'Supervisor' && $userRole === 'Admin') {
            $error_msg = "Error: Supervisors are not authorized to create Admin users.";
        } elseif (empty($fname)) {
            $error_msg = "Error: First Name is required.";
        } elseif (empty($lname)) {
            $error_msg = "Error: Last Name is required.";
        } elseif (empty($email)) {
            $error_msg = "Error: Email is required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_msg = "Error: Invalid email format. Please enter a valid email address (e.g. ezeab@gmail.com).";
        } elseif (strlen($password_raw) < 6) {
            $error_msg = "Error: Password must be at least 6 characters long.";
        } else {
            $serviceNo = mysqli_real_escape_string($conn, $serviceNo);
            $userRole = mysqli_real_escape_string($conn, $userRole);
            $fname = mysqli_real_escape_string($conn, $fname);
            $lname = mysqli_real_escape_string($conn, $lname);
            $email = mysqli_real_escape_string($conn, $email);
            $password = password_hash($password_raw, PASSWORD_DEFAULT);
            
            $photo = '';
            // Passport photo upload is optional. Check if a photo is provided.
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($_FILES['photo']['error'] === UPLOAD_ERR_OK) {
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
                } else {
                    $upload_errors = [
                        UPLOAD_ERR_INI_SIZE   => "The uploaded file exceeds the upload_max_filesize directive in php.ini.",
                        UPLOAD_ERR_FORM_SIZE  => "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.",
                        UPLOAD_ERR_PARTIAL    => "The uploaded file was only partially uploaded.",
                        UPLOAD_ERR_NO_TMP_DIR => "Missing a temporary folder.",
                        UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk.",
                        UPLOAD_ERR_EXTENSION  => "A PHP extension stopped the file upload."
                    ];
                    $error_code = $_FILES['photo']['error'];
                    $error_msg = isset($upload_errors[$error_code]) ? $upload_errors[$error_code] : "Unknown upload error occurred.";
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
                    $sql = "INSERT INTO users (service_no, role, fname, lname, email, password, photo, password_reset_required) 
                            VALUES ('$serviceNo', '$userRole', '$fname', '$lname', '$email', '$password', '$photo', 1)";
                    
                    if (mysqli_query($conn, $sql)) {
                        // Audit Log user creation
                        logActivity($conn, "CREATE_USER", "Created user: $serviceNo ($fname $lname), Role: $userRole, Email: $email");

                        $_SESSION['user_created_detail'] = [
                            'name' => trim($fname . ' ' . $lname),
                            'service_no' => $serviceNo,
                            'password' => $password_raw,
                            'role' => $userRole,
                            'email' => $email
                        ];
                        
                        session_write_close();
                        header("Location: createuser?success=1");
                        exit();
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
            display: block;
            width: 100%;
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
        
        /* Password Field Wrapper & Action Buttons Group */
        .password-field-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .password-field-wrapper .form-control {
            padding-right: 120px;
            width: 100%;
        }
        
        .password-actions-group {
            position: absolute;
            right: 4px;
            top: 50%;
            transform: translateY(-50%);
            display: flex;
            align-items: center;
            gap: 2px;
            z-index: 4;
        }
        
        .pwd-action-btn {
            background: transparent;
            border: none;
            color: #64748b;
            cursor: pointer;
            padding: 5px 6px;
            border-radius: 4px;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.88rem;
            line-height: 1;
        }
        
        .pwd-action-btn:hover {
            color: var(--primary);
            background: rgba(26, 92, 46, 0.08);
        }
        
        .password-field-wrapper .toggle-password-btn {
            position: static;
            transform: none;
            padding: 5px 6px;
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
        
        /* Ultra-Compact User Created Notification Alert */
        .user-created-alert-mini {
            background: #f0fdf4;
            border: 1.5px solid #86efac;
            border-left: 5px solid #16a34a;
            border-radius: 8px;
            padding: 8px 14px;
            margin-bottom: 18px;
            box-shadow: 0 2px 8px rgba(22, 163, 74, 0.08);
            animation: slideDownAlert 0.25s ease-out;
        }

        @keyframes slideDownAlert {
            from {
                opacity: 0;
                transform: translateY(-6px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-mini-flex {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
        }

        .alert-mini-lead {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.88rem;
            flex-wrap: wrap;
        }

        .alert-mini-badge {
            width: 22px;
            height: 22px;
            min-width: 22px;
            border-radius: 50%;
            background: #16a34a;
            color: #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .alert-mini-heading {
            font-weight: 700;
            color: #14532d;
            font-size: 0.9rem;
        }

        .alert-mini-officer {
            font-weight: 700;
            color: #0f172a;
            font-size: 0.9rem;
        }

        .alert-mini-details {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.84rem;
            background: #ffffff;
            border: 1px solid #dcfce7;
            padding: 3px 10px;
            border-radius: 6px;
            flex-wrap: wrap;
        }

        .alert-mini-tag {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: #475569;
        }

        .alert-mini-divider {
            color: #cbd5e1;
            font-weight: 300;
        }

        .btn-copy-micro {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            color: #475569;
            padding: 1px 6px;
            border-radius: 4px;
            font-size: 0.72rem;
            cursor: pointer;
            line-height: 1.2;
            transition: all 0.15s ease;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }

        .btn-copy-micro:hover {
            background: #1a5c2e;
            color: #ffffff;
            border-color: #1a5c2e;
        }

        .alert-mini-close {
            background: transparent;
            border: none;
            color: #166534;
            opacity: 0.6;
            font-size: 1.15rem;
            cursor: pointer;
            padding: 0 4px;
            line-height: 1;
            transition: opacity 0.15s ease;
        }

        .alert-mini-close:hover {
            opacity: 1;
        }

        /* Generic Alert/Status Message Styles */
        .status-message {
            padding: 14px 18px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
        }

        .status-success {
            background-color: #f0fdf4;
            color: #166534;
            border: 1.5px solid #86efac;
            border-left: 5px solid #16a34a;
        }

        .status-error {
            background-color: #fef2f2;
            color: #991b1b;
            border: 1.5px solid #fca5a5;
            border-left: 5px solid #dc2626;
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
            color: #0f172a;
        }
        
        /* Users Table Clean Container & Table */
        .users-table-container {
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
            overflow: hidden;
        }

        .custom-users-table {
            width: 100%;
            margin-bottom: 0;
            border-collapse: collapse;
        }

        .custom-users-table thead th {
            background: #f8fafc;
            color: #475569;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 12px 14px;
            border-bottom: 2px solid #e2e8f0;
            border-top: none;
            white-space: nowrap;
            vertical-align: middle;
        }

        .custom-users-table tbody td {
            padding: 10px 14px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.86rem;
            color: #334155;
        }

        .custom-users-table tbody tr {
            transition: background-color 0.15s ease;
        }

        .custom-users-table tbody tr:hover {
            background-color: #f8fafc;
        }

        .custom-users-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Service Number Badge */
        .service-badge {
            font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
            font-size: 0.8rem;
            font-weight: 700;
            background: #f1f5f9;
            color: #1e293b;
            padding: 3px 8px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            letter-spacing: 0.3px;
            display: inline-block;
            white-space: nowrap;
        }

        /* User Profile Info Cell - Bolder & Clearer Avatars */
        .user-info-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .avatar-circle {
            width: 48px;
            height: 48px;
            min-width: 48px;
            border-radius: 50%;
            overflow: hidden;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #ffffff;
            border: 2.5px solid #1a5c2e;
            box-shadow: 0 0 0 1px #ffffff, 0 3px 8px rgba(26, 92, 46, 0.3);
            image-rendering: -webkit-optimize-contrast;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .avatar-circle:hover {
            transform: scale(1.06);
            border-color: #27ae60;
            box-shadow: 0 0 0 2px #ffffff, 0 5px 14px rgba(26, 92, 46, 0.4);
        }

        .avatar-circle img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center top;
            display: block;
            image-rendering: -webkit-optimize-contrast;
            image-rendering: crisp-edges;
            filter: contrast(1.08) saturate(1.06) brightness(1.02);
        }

        .avatar-initials-text {
            color: #ffffff;
            font-weight: 800;
            font-size: 0.95rem;
            letter-spacing: 0.8px;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.35);
        }

        .user-fullname {
            font-weight: 700;
            color: #0f172a;
            font-size: 0.92rem;
            white-space: nowrap;
        }

        .user-email-text {
            color: #64748b;
            font-size: 0.84rem;
            display: inline-block;
            white-space: nowrap;
        }

        .date-joined-text {
            color: #64748b;
            font-size: 0.82rem;
            white-space: nowrap;
        }

        /* Role Badges */
        .role-badge {
            display: inline-block;
            padding: 3px 9px;
            border-radius: 5px;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.3px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .role-badge.badge-admin {
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
        }

        .role-badge.badge-supervisor {
            background: #faf5ff;
            color: #7e22ce;
            border: 1px solid #e9d5ff;
        }

        .role-badge.badge-dataentry {
            background: #f0fdf4;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .role-badge.badge-viewonly {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
        }

        .role-badge.badge-secondary {
            background: #fffbeb;
            color: #b45309;
            border: 1px solid #fde68a;
        }

        /* Status Indicator Pill */
        .status-indicator-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 9px;
            border-radius: 12px;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.3px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .status-indicator-badge.active {
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
        }

        .status-indicator-badge.active::before {
            content: "";
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #10b981;
            display: inline-block;
        }

        .status-indicator-badge.inactive {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .status-indicator-badge.inactive::before {
            content: "";
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #ef4444;
            display: inline-block;
        }
        
        /* Self-Contained Custom Role Modal Styles */
        .custom-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(3px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 999999;
            padding: 20px;
            opacity: 0;
            transition: opacity 0.2s ease-in-out;
        }
        
        .custom-modal-overlay.active {
            display: flex;
            opacity: 1;
        }
        
        .custom-modal-box {
            background: #ffffff;
            width: 100%;
            max-width: 480px;
            border-radius: 12px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transform: translateY(-20px) scale(0.98);
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }
        
        .custom-modal-overlay.active .custom-modal-box {
            transform: translateY(0) scale(1);
        }
        
        .custom-modal-header {
            background: linear-gradient(135deg, #1a5c2e, #113f1f);
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            color: #ffffff;
        }
        
        .custom-modal-header h5 {
            margin: 0;
            font-weight: 700;
            font-size: 1.05rem;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #ffffff;
        }
        
        .custom-modal-close {
            background: transparent;
            border: none;
            color: rgba(255, 255, 255, 0.85);
            font-size: 1.6rem;
            line-height: 1;
            cursor: pointer;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.2s;
        }
        
        .custom-modal-close:hover {
            color: #ffffff;
        }
        
        .custom-modal-body {
            padding: 22px 24px;
        }
        
        .custom-modal-footer {
            background: #f8fafc;
            padding: 14px 20px;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            border-top: 1px solid #e2e8f0;
        }
        
        /* Professional User Table Action Buttons - Two-Row Layout */
        .user-actions-container {
            display: inline-flex !important;
            flex-direction: column !important;
            gap: 4px !important;
            align-items: flex-end !important;
            width: 100% !important;
            min-width: 215px !important;
            max-width: 235px !important;
        }
        
        .user-actions-row {
            display: flex !important;
            align-items: center !important;
            justify-content: flex-end !important;
            gap: 4px !important;
            width: 100% !important;
            white-space: nowrap !important;
        }
        
        .btn-action-user {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 4px !important;
            padding: 4px 6px !important;
            font-size: 0.73rem !important;
            font-weight: 600 !important;
            line-height: 1.3 !important;
            border-radius: 5px !important;
            border: 1px solid transparent !important;
            cursor: pointer !important;
            text-decoration: none !important;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
            white-space: nowrap !important;
            outline: none !important;
            box-shadow: 0 1px 2px rgba(0,0,0,0.03) !important;
            flex: 1 1 50% !important;
            text-align: center !important;
        }
        
        .btn-action-user i {
            font-size: 0.75rem !important;
            flex-shrink: 0 !important;
        }
        
        /* Deactivate Button (Rose) */
        .btn-action-deactivate {
            background-color: #fff1f2 !important;
            color: #e11d48 !important;
            border-color: #fecdd3 !important;
        }
        .btn-action-deactivate:hover {
            background-color: #e11d48 !important;
            color: #ffffff !important;
            border-color: #e11d48 !important;
            box-shadow: 0 2px 6px rgba(225, 29, 72, 0.28) !important;
            transform: translateY(-1px);
        }
        
        /* Activate Button (Emerald) */
        .btn-action-activate {
            background-color: #ecfdf5 !important;
            color: #059669 !important;
            border-color: #a7f3d0 !important;
        }
        .btn-action-activate:hover {
            background-color: #059669 !important;
            color: #ffffff !important;
            border-color: #059669 !important;
            box-shadow: 0 2px 6px rgba(5, 150, 105, 0.28) !important;
            transform: translateY(-1px);
        }
        
        /* Edit Role Button (Blue) */
        .btn-action-role {
            background-color: #eff6ff !important;
            color: #2563eb !important;
            border-color: #bfdbfe !important;
        }
        .btn-action-role:hover {
            background-color: #2563eb !important;
            color: #ffffff !important;
            border-color: #2563eb !important;
            box-shadow: 0 2px 6px rgba(37, 99, 235, 0.28) !important;
            transform: translateY(-1px);
        }
        
        /* Reset Password Button (Amber) */
        .btn-action-reset {
            background-color: #fffbeb !important;
            color: #d97706 !important;
            border-color: #fde68a !important;
        }
        .btn-action-reset:hover {
            background-color: #d97706 !important;
            color: #ffffff !important;
            border-color: #d97706 !important;
            box-shadow: 0 2px 6px rgba(217, 119, 6, 0.28) !important;
            transform: translateY(-1px);
        }

        /* Reset Google Authenticator 2FA Button (Purple) */
        .btn-action-mfa {
            background-color: #f5f3ff !important;
            color: #7c3aed !important;
            border-color: #ddd6fe !important;
        }
        .btn-action-mfa:hover {
            background-color: #7c3aed !important;
            color: #ffffff !important;
            border-color: #7c3aed !important;
            box-shadow: 0 2px 6px rgba(124, 58, 237, 0.28) !important;
            transform: translateY(-1px);
        }

        /* Resend Welcome Email Button (Teal / Forest Green) */
        .btn-action-email {
            background-color: #f0fdf4 !important;
            color: #166534 !important;
            border-color: #bbf7d0 !important;
            width: 100% !important;
            flex: 1 1 100% !important;
        }
        .btn-action-email:hover {
            background-color: #166534 !important;
            color: #ffffff !important;
            border-color: #166534 !important;
            box-shadow: 0 2px 6px rgba(22, 101, 52, 0.28) !important;
            transform: translateY(-1px);
        }
        
        /* Role Cards */
        .role-item {
            margin-bottom: 12px;
            padding: 12px 16px;
            border-radius: 8px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        .role-item:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }

        
        .role-title {
            font-weight: 700;
            margin-bottom: 4px;
            font-size: 0.95rem;
        }
        
        .role-description {
            font-size: 0.85rem;
            color: #64748b;
        }
        
        /* Compact Professional Passport Photograph Upload Component */
        .photo-upload-wrapper {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 12px;
            background: #f8fafc;
            border: 1.5px dashed #cbd5e1;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .photo-upload-wrapper:hover, .photo-upload-wrapper.dragover {
            border-color: var(--primary);
            background: #f0fdf4;
        }

        .photo-preview-box {
            width: 60px;
            height: 60px;
            min-width: 60px;
            border-radius: 50%;
            overflow: hidden;
            background: #ffffff;
            border: 3px solid #1a5c2e;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.12);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            flex-shrink: 0;
        }

        .photo-preview-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            image-rendering: -webkit-optimize-contrast;
            filter: contrast(1.05);
        }

        .photo-placeholder i {
            font-size: 32px;
            color: #1a5c2e;
            opacity: 0.7;
        }

        .photo-upload-controls {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .photo-dropzone {
            cursor: pointer;
            padding: 5px 8px;
            border-radius: 6px;
            background: white;
            border: 1px solid #e2e8f0;
            transition: all 0.2s ease;
        }

        .photo-dropzone:hover {
            border-color: var(--primary);
            background: #f8fdf9;
        }

        .upload-icon {
            font-size: 0.95rem;
            color: var(--primary);
            margin-right: 5px;
        }

        .upload-text {
            font-size: 0.8rem;
            color: var(--dark);
            display: inline-block;
        }

        .upload-text strong {
            color: var(--primary);
        }

        .upload-hint {
            font-size: 0.7rem;
            color: var(--gray);
            margin-top: 1px;
        }

        .photo-file-info {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 4px 8px;
            background: white;
            border-radius: 6px;
            border: 1px solid #bbf7d0;
        }

        /* Users Table Header & Search */
        .users-table-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 18px;
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            flex-wrap: wrap;
            gap: 12px;
        }

        .users-table-title {
            margin: 0;
            font-size: 1.05rem;
            font-weight: 700;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .user-count-pill {
            background: #e2e8f0;
            color: #334155;
            font-size: 0.78rem;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 12px;
        }

        /* User search input group - Professionally compact (290px max-width) */
        .user-search-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            width: 100%;
            max-width: 290px;
            margin-left: auto;
        }

        .user-search-wrapper .form-control {
            padding-left: 34px;
            padding-right: 78px;
            height: 36px;
            border-radius: 6px;
            font-size: 0.82rem;
            border: 1px solid #cbd5e1;
            box-shadow: none;
        }

        .user-search-wrapper .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(26, 92, 46, 0.15);
        }

        .user-search-wrapper .search-icon {
            position: absolute;
            left: 11px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 0.82rem;
            pointer-events: none;
        }

        .user-search-wrapper .btn-search-trigger {
            position: absolute;
            right: 3px;
            top: 50%;
            transform: translateY(-50%);
            height: 30px;
            padding: 0 10px;
            font-size: 0.76rem;
            font-weight: 600;
            border-radius: 5px;
            display: flex;
            align-items: center;
            gap: 4px;
            background: linear-gradient(135deg, var(--secondary), #219a52);
            border: none;
            color: white;
            transition: all 0.2s ease;
        }

        .user-search-wrapper .btn-search-trigger:hover {
            background: linear-gradient(135deg, #219a52, #1a5c2e);
            color: white;
            box-shadow: 0 2px 6px rgba(39, 174, 96, 0.3);
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

        /* Users Table Pagination */
        .users-pagination-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 18px;
            background: #ffffff;
            border-top: 1px solid #e2e8f0;
            border-radius: 0 0 10px 10px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .users-pagination-info {
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
            <div class="page-title mb-3">
                <h3 class="mb-0">User Management</h3>
            </div>

            <!-- Display User Created Notification / Success Message (Ultra-Compact & Actionable) -->
            <?php if (isset($user_created_detail)): ?>
                <div class="user-created-alert-mini" role="alert">
                    <div class="alert-mini-flex">
                        <div class="alert-mini-lead">
                            <span class="alert-mini-badge"><i class="fa fa-check"></i></span>
                            <span class="alert-mini-heading">Account Created:</span>
                            <span class="alert-mini-officer"><?php echo htmlspecialchars($user_created_detail['name']); ?></span>
                        </div>
                        <div class="alert-mini-details">
                            <span class="alert-mini-tag">
                                Service No: <strong class="text-success font-monospace"><?php echo htmlspecialchars($user_created_detail['service_no']); ?></strong>
                                <button type="button" class="btn-copy-micro" onclick="copyCredText('<?php echo addslashes($user_created_detail['service_no']); ?>', this)" title="Copy Service Number">
                                    <i class="fa fa-clone"></i> Copy
                                </button>
                            </span>
                            <span class="alert-mini-divider">|</span>
                            <span class="alert-mini-tag">
                                Password: <strong class="text-dark font-monospace"><?php echo htmlspecialchars($user_created_detail['password']); ?></strong>
                                <button type="button" class="btn-copy-micro" onclick="copyCredText('<?php echo addslashes($user_created_detail['password']); ?>', this)" title="Copy Default Password">
                                    <i class="fa fa-clone"></i> Copy
                                </button>
                            </span>
                            <span class="alert-mini-divider">|</span>
                            <span class="alert-mini-tag">
                                Role: <span class="badge bg-light text-dark border" style="font-size: 0.74rem;"><?php echo htmlspecialchars($user_created_detail['role']); ?></span>
                            </span>
                        </div>
                        <button type="button" class="alert-mini-close" onclick="this.closest('.user-created-alert-mini').style.display='none';" aria-label="Close">
                            <i class="fa fa-times"></i>
                        </button>
                    </div>
                </div>
            <?php elseif (isset($success_msg)): ?>
                <div class="user-created-alert-mini" role="alert">
                    <div class="alert-mini-flex">
                        <div class="alert-mini-lead">
                            <span class="alert-mini-badge"><i class="fa fa-check"></i></span>
                            <span class="alert-mini-heading"><?php echo $success_msg; ?></span>
                        </div>
                        <button type="button" class="alert-mini-close" onclick="this.closest('.user-created-alert-mini').style.display='none';" aria-label="Close">
                            <i class="fa fa-times"></i>
                        </button>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_msg)): ?>
                <div class="status-message status-error">
                    <i class="fa fa-exclamation-circle"></i> <?php echo $error_msg; ?>
                </div>
            <?php endif; ?>

            <!-- Main Content Grid -->
            <div class="row g-4 align-items-stretch">
                <div class="col-lg-8 col-md-7 d-flex">
                    <!-- User Creation Form -->
                    <div class="card w-100 mb-0">
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
                                            <!-- <?php if ($currentUserRole === 'Admin'): ?>
                                                <option value="Admin" <?php echo (isset($_POST['userRole']) && $_POST['userRole'] == 'Admin') ? 'selected' : ''; ?>>Administrator</option>
                                            <?php endif; ?> -->
                                            <?php if ($currentUserRole === 'Admin' || $currentUserRole === 'Supervisor'): ?>
                                                <option value="Supervisor" <?php echo (isset($_POST['userRole']) && $_POST['userRole'] == 'Supervisor') ? 'selected' : ''; ?>>Administrator</option>
                                            <?php endif; ?>
                                            <option value="DataEntry" <?php echo (isset($_POST['userRole']) && $_POST['userRole'] == 'DataEntry') ? 'selected' : ''; ?>>Data Entry</option>
                                            <option value="ViewOnly" <?php echo (isset($_POST['userRole']) && $_POST['userRole'] == 'ViewOnly') ? 'selected' : ''; ?>>View only</option>
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
                                        <label for="email" class="form-label">Email Address</label>
                                        <input type="email" class="form-control" name="email" id="email" 
                                               placeholder="e.g. ezeab@gmail.com" 
                                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                                        <div class="form-text text-muted" style="font-size: 0.77rem; margin-top: 4px;">
                                            <i class="fa fa-envelope-o text-success me-1"></i> Email to receive login credentials & temporary password
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="password" class="form-label d-flex justify-content-between align-items-center">
                                            <span>Default Password</span>
                                            <span class="badge" style="background: #e8f5e9; color: #1b5e20; font-size: 0.72rem; font-weight: 600; padding: 2px 6px; border-radius: 4px; border: 1px solid #c8e6c9;">
                                                <i class="fa fa-magic"></i> Auto-Generated
                                            </span>
                                        </label>
                                        <div class="password-field-wrapper">
                                            <input type="text" class="form-control no-uppercase" name="password" id="password" 
                                                   value="<?php echo isset($_POST['password']) ? htmlspecialchars($_POST['password']) : 'password@123'; ?>" 
                                                   placeholder="Auto-Generated Password" data-password-field="true" data-no-uppercase="true" 
                                                   style="text-transform: none !important; font-family: 'SFMono-Regular', Consolas, monospace; font-weight: 600;" required>
                                            <div class="password-actions-group">
                                                <button type="button" class="pwd-action-btn" onclick="generateRandomPassword()" title="Generate New Random Password">
                                                    <i class="fa fa-refresh"></i>
                                                </button>
                                                <button type="button" class="pwd-action-btn" onclick="resetToDefaultPassword()" title="Reset to standard 'password@123'">
                                                    <i class="fa fa-undo"></i>
                                                </button>
                                                <button type="button" class="pwd-action-btn" onclick="copyPasswordValue()" title="Copy Password to Clipboard">
                                                    <i class="fa fa-clone" id="copyPwdIcon"></i>
                                                </button>
                                                <button type="button" class="pwd-action-btn toggle-password-btn" onclick="togglePasswordVisibility('password', this)" title="Show/Hide Password">
                                                    <i class="fa fa-eye-slash" id="pwdEyeIcon"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mt-1" style="font-size: 0.77rem;">
                                            <span class="text-muted"><i class="fa fa-info-circle text-primary me-1"></i> Current: <strong id="pwdHint"><?php echo isset($_POST['password']) ? htmlspecialchars($_POST['password']) : 'password@123'; ?></strong> (User resets on 1st login)</span>
                                            <button type="button" class="btn btn-link p-0 text-decoration-none" onclick="generateRandomPassword()" style="font-size: 0.77rem; color: #1a5c2e; font-weight: 600;">
                                                <i class="fa fa-magic me-1"></i>New Random
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Professional Passport Photograph Upload Section -->
                                <div class="row mb-4">
                                    <div class="col-md-12">
                                        <label class="form-label d-flex justify-content-between align-items-center">
                                            <span>Passport Photograph (JPG or PNG)</span>
                                            <span class="badge bg-light text-muted" style="font-size: 0.75rem; font-weight: normal; border: 1px solid #e2e8f0;">Max size: 2MB</span>
                                        </label>
                                        
                                        <div class="photo-upload-wrapper" id="photoUploadWrapper">
                                            <div class="photo-preview-box" id="photoPreviewContainer">
                                                <div class="photo-placeholder" id="photoPlaceholder">
                                                    <i class="fa fa-user-circle"></i>
                                                </div>
                                                <img id="photoPreviewImg" src="" alt="Preview" style="display: none;">
                                            </div>
                                            <div class="photo-upload-controls">
                                                <div class="photo-dropzone" id="photoDropzone" onclick="document.getElementById('photo').click()">
                                                    <i class="fa fa-cloud-upload upload-icon"></i>
                                                    <div class="upload-text">
                                                        <strong>Click to browse</strong> or drag & drop photo here
                                                    </div>
                                                    <div class="upload-hint">Supports JPEG, JPG, PNG</div>
                                                </div>
                                                <input name="photo" class="d-none" id="photo" type="file" accept=".jpg,.jpeg,.png" />
                                                <div id="photoFileInfo" class="photo-file-info" style="display: none;">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <i class="fa fa-image text-success"></i>
                                                        <span id="photoFileName" class="text-truncate fw-bold" style="max-width: 220px; font-size: 0.82rem; color: #1e293b;"></span>
                                                        <span id="photoFileSize" class="badge bg-secondary" style="font-size: 0.72rem;"></span>
                                                    </div>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" id="removePhotoBtn" style="padding: 2px 8px; font-size: 0.75rem;" title="Remove selected image">
                                                        <i class="fa fa-trash"></i> Remove
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-end" style="margin-top: 36px; padding-top: 8px;">
                                    <button type="submit" name="regUser" class="btn btn-success" style="padding: 11px 28px; font-size: 0.95rem; box-shadow: 0 4px 12px rgba(26, 92, 46, 0.2);">
                                        <i class="fa fa-user-plus"></i> Create User
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div> <!-- End of col-lg-8 col-md-7 -->
                
                <!-- Sidebar Cards -->
                <div class="col-lg-4 col-md-5 d-flex">
                    <!-- User Roles Card -->
                    <div class="card w-100 mb-0">
                        <div class="card-header">
                            <h4><i class="fa fa-key" style="color: var(--warning);"></i> User Roles</h4>
                        </div>
                        <div class="card-body">
                            <!-- <div class="role-item role-admin">
                                <div class="role-title text-primary">Administrator</div>
                                <div class="role-description">Full system access with user management capabilities</div>
                            </div> -->
                            <div class="role-item role-Administrator">
                                <div class="role-title text-info">Administrator</div>
                                <div class="role-description">Can create, edit, print and view reports</div>
                            </div>
                            <div class="role-item role-dataentry">
                                <div class="role-title text-success">Data Entry</div>
                                <div class="role-description">Can create, edit and print visa applications</div>
                            </div>
                            <div class="role-item role-viewonly">
                                <div class="role-title text-secondary">View only</div>
                                <div class="role-description">Allowed access to Dashboard, View Re-Entry Visas, Reports, and Profile only</div>
                            </div>
                        </div>
                    </div>
                </div> <!-- End of col-lg-4 col-md-5 -->
            </div> <!-- End of first row -->

            <!-- Existing Users Table - Expanded Full Width -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="users-table-container">
                        <div class="users-table-header">
                            <h4 class="users-table-title">
                                <i class="fa fa-users" style="color: var(--primary);"></i> 
                                Existing Users 
                                <span class="user-count-pill" id="userCount"><?php echo $total_users; ?></span>
                            </h4>
                            
                            <!-- Search Existing Users Controls -->
                            <div class="user-search-wrapper">
                                <i class="fa fa-search search-icon"></i>
                                <input type="text" id="userSearchInput" class="form-control no-uppercase" 
                                       placeholder="Search by name or service no..." 
                                       data-no-uppercase="true" autocomplete="off">
                                <button type="button" class="btn btn-sm btn-success btn-search-trigger" id="userSearchBtn">
                                    <i class="fa fa-search"></i> Search
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="clearUserSearch" 
                                        style="display: none; position: absolute; right: 85px; top: 50%; transform: translateY(-50%); padding: 2px 6px; font-size: 0.75rem; border: none; background: transparent;" 
                                        title="Clear search">
                                    <i class="fa fa-times text-muted"></i>
                                </button>
                            </div>
                        </div>
                        
                        <?php if ($total_users > 0): ?>
                            <div class="table-responsive" style="margin-bottom: 0;">
                                <table class="custom-users-table" id="usersTable">
                                    <thead>
                                        <tr>
                                            <th style="width: 110px; text-align: center;">Service No</th>
                                            <th style="text-align: left;">User Profile</th>
                                            <th style="text-align: left;">Email</th>
                                            <th style="width: 115px; text-align: center;">Role</th>
                                            <th style="width: 95px; text-align: center;">Status</th>
                                            <th style="width: 110px; text-align: center;">Joined Date</th>
                                            <th style="width: 235px; text-align: right; padding-right: 14px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="usersTableBody">
                                        <?php 
                                         mysqli_data_seek($users_result, 0);
                                         while ($user = mysqli_fetch_assoc($users_result)): 
                                             $raw_role = trim($user['role'] ?? '');
                                             $role_clean = str_replace([' ', '_', '-'], '', strtolower($raw_role));
                                             
                                             if ($role_clean === 'admin' || $role_clean === 'administrator') {
                                                 $role_badge_class = 'badge-admin';
                                                 $role_display_text = 'ADMINISTRATOR';
                                                 $role_val = 'Admin';
                                             } elseif ($role_clean === 'supervisor') {
                                                 $role_badge_class = 'badge-supervisor';
                                                 $role_display_text = 'SUPERVISOR';
                                                 $role_val = 'Supervisor';
                                             } elseif ($role_clean === 'dataentry' || $role_clean === 'dat') {
                                                 $role_badge_class = 'badge-dataentry';
                                                 $role_display_text = 'DATA ENTRY';
                                                 $role_val = 'DataEntry';
                                             } elseif ($role_clean === 'viewonly' || $role_clean === 'view') {
                                                 $role_badge_class = 'badge-viewonly';
                                                 $role_display_text = 'VIEW ONLY';
                                                 $role_val = 'ViewOnly';
                                             } elseif (empty($raw_role)) {
                                                 $role_badge_class = 'badge-secondary';
                                                 $role_display_text = 'NOT SET';
                                                 $role_val = '';
                                             } else {
                                                 $role_badge_class = 'badge-secondary';
                                                 $role_display_text = strtoupper($raw_role);
                                                 $role_val = $raw_role;
                                             }
                                         ?>
                                         <tr>
                                             <td style="text-align: center;">
                                                 <span class="service-badge">
                                                     <?php echo htmlspecialchars($user['service_no']); ?>
                                                 </span>
                                             </td>
                                             <td>
                                                 <div class="user-info-cell">
                                                     <div class="avatar-circle">
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
                                                             <img src="<?php echo htmlspecialchars($user_photo_path); ?>" alt="Profile">
                                                         <?php else: ?>
                                                             <div class="avatar-initials-text">
                                                                 <?php 
                                                                 $initials = strtoupper(substr($user['fname'] ?? '', 0, 1) . substr($user['lname'] ?? '', 0, 1));
                                                                 echo htmlspecialchars($initials ?: 'U');
                                                                 ?>
                                                             </div>
                                                         <?php endif; ?>
                                                     </div>
                                                     <span class="user-fullname"><?php echo htmlspecialchars($user['fname'] . ' ' . $user['lname']); ?></span>
                                                 </div>
                                             </td>
                                             <td>
                                                 <span class="user-email-text"><?php echo htmlspecialchars(strtolower($user['email'])); ?></span>
                                             </td>
                                             <td style="text-align: center;">
                                                 <span class="role-badge <?php echo $role_badge_class; ?>">
                                                     <?php echo htmlspecialchars($role_display_text); ?>
                                                 </span>
                                             </td>
                                             <td style="text-align: center;">
                                                 <span class="status-indicator-badge <?php echo strtolower($user['status']); ?>">
                                                     <?php echo ucfirst($user['status']); ?>
                                                 </span>
                                             </td>
                                             <td style="text-align: center;">
                                                 <span class="date-joined-text">
                                                     <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                                                 </span>
                                             </td>
                                             <td style="text-align: right; padding-right: 14px; white-space: nowrap; width: 235px;">
                                                 <?php if ($user['service_no'] != $username): ?>
                                                     <div class="user-actions-container">
                                                         <!-- Row 1: Deactivate/Activate & Edit Role -->
                                                         <div class="user-actions-row">
                                                             <?php if ($user['status'] == 'active'): ?>
                                                                 <button class="btn-action-user btn-action-deactivate deactivate-user" 
                                                                         data-id="<?php echo $user['id']; ?>" 
                                                                         data-name="<?php echo htmlspecialchars($user['fname'] . ' ' . $user['lname']); ?>"
                                                                         title="Deactivate Account">
                                                                     <i class="fa fa-ban"></i> Deactivate
                                                                 </button>
                                                             <?php else: ?>
                                                                 <button class="btn-action-user btn-action-activate activate-user" 
                                                                         data-id="<?php echo $user['id']; ?>" 
                                                                         data-name="<?php echo htmlspecialchars($user['fname'] . ' ' . $user['lname']); ?>"
                                                                         title="Activate Account">
                                                                     <i class="fa fa-check"></i> Activate
                                                                 </button>
                                                             <?php endif; ?>
                                                             <button class="btn-action-user btn-action-role edit-role-btn" 
                                                                     data-id="<?php echo $user['id']; ?>" 
                                                                     data-name="<?php echo htmlspecialchars($user['fname'] . ' ' . $user['lname']); ?>"
                                                                     data-role="<?php echo htmlspecialchars($role_val); ?>"
                                                                     title="Change User Role">
                                                                 <i class="fa fa-user-circle"></i> Edit Role
                                                             </button>
                                                         </div>
                                                          <!-- Row 2: Reset Password & Reset Google Authenticator -->
                                                          <div class="user-actions-row">
                                                              <button class="btn-action-user btn-action-reset reset-password-btn" 
                                                                      data-id="<?php echo $user['id']; ?>" 
                                                                      data-name="<?php echo htmlspecialchars($user['fname'] . ' ' . $user['lname']); ?>"
                                                                      title="Reset Password to default (password@123)">
                                                                  <i class="fa fa-key"></i> Reset Pwd
                                                              </button>
                                                              <button class="btn-action-user btn-action-mfa reset-mfa-btn" 
                                                                      data-id="<?php echo $user['id']; ?>" 
                                                                      data-name="<?php echo htmlspecialchars($user['fname'] . ' ' . $user['lname']); ?>"
                                                                      title="Reset Google Authenticator Barcode">
                                                                  <i class="fa fa-qrcode"></i> Reset 2FA
                                                              </button>
                                                          </div>
                                                      </div>
                                                 <?php else: ?>
                                                     <span class="badge bg-light text-muted border" style="font-size: 0.75rem; padding: 5px 10px;">Current User</span>
                                                 <?php endif; ?>
                                             </td>
                                         </tr>
                                         <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Users Pagination Footer (10 records per page) -->
                            <div class="users-pagination-footer" id="usersPaginationFooter">
                                <div class="users-pagination-info" id="usersPaginationInfo">
                                    Showing <strong id="userPageStart" class="text-dark">1</strong> to <strong id="userPageEnd" class="text-dark">10</strong> of <strong id="userTotalItems" class="text-dark"><?php echo $total_users; ?></strong> users
                                </div>
                                <nav aria-label="Users Records Pagination">
                                    <ul class="pagination pagination-sm mb-0 gap-1" id="usersPaginationNav">
                                        <!-- Dynamically generated by JS -->
                                    </ul>
                                </nav>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fa fa-users fa-3x text-muted mb-3" style="opacity: 0.4;"></i>
                                <p class="text-muted mb-0">No users found. Create your first user above.</p>
                            </div>
                        <?php endif; ?>
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
    
    <!-- Self-Contained Edit Role Modal -->
    <div class="custom-modal-overlay" id="editRoleModalOverlay">
        <div class="custom-modal-box">
            <div class="custom-modal-header">
                <h5><i class="fa fa-user-circle"></i> Change User Role</h5>
                <button type="button" class="custom-modal-close" id="closeRoleModalBtn" aria-label="Close">&times;</button>
            </div>
            <div class="custom-modal-body">
                <div class="mb-3" style="background: #f1f5f9; padding: 12px 16px; border-radius: 8px; border-left: 4px solid #1a5c2e;">
                    <div style="font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; font-weight: 600;">Updating Role For</div>
                    <div id="modalUserName" style="font-size: 1rem; font-weight: 700; color: #0f172a; margin-top: 2px;"></div>
                </div>
                
                <input type="hidden" id="modalUserId">
                
                <div class="form-group mb-2">
                    <label for="modalUserRoleSelect" class="form-label fw-bold" style="font-size: 0.9rem; color: #334155;">Select Role <span class="text-danger">*</span></label>
                    <select class="form-select" id="modalUserRoleSelect" style="border: 1.5px solid #cbd5e1; border-radius: 8px; padding: 10px 14px; font-size: 0.92rem; width: 100%;" required>
                        <option value="">-- Choose Role --</option>
                        <?php if ($currentUserRole === 'Admin'): ?>
                            <!-- <option value="Admin">Administrator (Full Access)</option> -->
                        <?php endif; ?>
                        <option value="Supervisor">Administrator</option>
                        <option value="DataEntry">Data Entry</option>
                        <option value="ViewOnly">View only</option>
                    </select>
                    
                    <div style="margin-top: 14px; font-size: 0.8rem; color: #64748b; background: #f8fafc; padding: 10px 14px; border-radius: 8px; border: 1px solid #e2e8f0;">
                        <div style="font-weight: 600; color: #1e293b; margin-bottom: 4px;"><i class="fa fa-shield text-primary"></i> Role Access Summary:</div>
                        <ul style="margin: 0; padding-left: 18px; font-size: 0.78rem; line-height: 1.5;">
                            <li><strong>View only:</strong> General Dashboard, View Re-Entry Visas, Reports, and Profile only.</li>
                            <li><strong>Data Entry:</strong> Data Entry Dashboard, Create, Edit and Print Visas.</li>
                            <li><strong>Administrator:</strong> Full Access with User Management.</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="custom-modal-footer">
                <button type="button" class="btn btn-secondary" id="cancelRoleModalBtn" style="padding: 8px 18px; font-size: 0.88rem;">Cancel</button>
                <button type="button" class="btn btn-success" id="saveRoleBtn" style="padding: 8px 22px; font-size: 0.88rem;">
                    <i class="fa fa-save me-1"></i> Update Role
                </button>
            </div>
        </div>
    </div>
    
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
            // Passport Photograph upload preview & drag-drop handling
            const photoInput = document.getElementById('photo');
            const photoWrapper = document.getElementById('photoUploadWrapper');
            const photoPreviewImg = document.getElementById('photoPreviewImg');
            const photoPlaceholder = document.getElementById('photoPlaceholder');
            const photoFileInfo = document.getElementById('photoFileInfo');
            const photoFileName = document.getElementById('photoFileName');
            const photoFileSize = document.getElementById('photoFileSize');
            const removePhotoBtn = document.getElementById('removePhotoBtn');

            function handlePhotoSelection(file) {
                if (!file) return;

                // Validate file type
                const validTypes = ['image/jpeg', 'image/jpg', 'image/png'];
                if (!validTypes.includes(file.type.toLowerCase())) {
                    alert('Invalid file format. Please upload a JPG or PNG image.');
                    if (photoInput) photoInput.value = '';
                    return;
                }

                // Validate file size (max 2MB)
                const fileSizeMB = file.size / (1024 * 1024);
                if (fileSizeMB > 2) {
                    alert('File size exceeds 2MB limit. Please choose a smaller image.');
                    if (photoInput) photoInput.value = '';
                    return;
                }

                // Render live preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    if (photoPreviewImg && photoPlaceholder) {
                        photoPreviewImg.src = e.target.result;
                        photoPreviewImg.style.display = 'block';
                        photoPlaceholder.style.display = 'none';
                    }
                    if (photoFileInfo && photoFileName && photoFileSize) {
                        photoFileName.textContent = file.name;
                        photoFileSize.textContent = fileSizeMB < 1 
                            ? (file.size / 1024).toFixed(1) + ' KB' 
                            : fileSizeMB.toFixed(2) + ' MB';
                        photoFileInfo.style.display = 'flex';
                    }
                };
                reader.readAsDataURL(file);
            }

            if (photoInput) {
                photoInput.addEventListener('change', function(e) {
                    if (e.target.files && e.target.files[0]) {
                        handlePhotoSelection(e.target.files[0]);
                    }
                });
            }

            if (removePhotoBtn) {
                removePhotoBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    if (photoInput) photoInput.value = '';
                    if (photoPreviewImg) {
                        photoPreviewImg.src = '';
                        photoPreviewImg.style.display = 'none';
                    }
                    if (photoPlaceholder) photoPlaceholder.style.display = 'flex';
                    if (photoFileInfo) photoFileInfo.style.display = 'none';
                });
            }

            // Drag and drop for photo upload
            if (photoWrapper) {
                ['dragenter', 'dragover'].forEach(eventName => {
                    photoWrapper.addEventListener(eventName, function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        photoWrapper.classList.add('dragover');
                    });
                });

                ['dragleave', 'drop'].forEach(eventName => {
                    photoWrapper.addEventListener(eventName, function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        photoWrapper.classList.remove('dragover');
                    });
                });

                photoWrapper.addEventListener('drop', function(e) {
                    const dt = e.dataTransfer;
                    if (dt && dt.files && dt.files[0]) {
                        if (photoInput) {
                            photoInput.files = dt.files;
                            handlePhotoSelection(dt.files[0]);
                        }
                    }
                });
            }

            // Existing Users Real-Time Search & 10-per-page Pagination Engine
            const USERS_PER_PAGE = 10;
            let currentUsersPage = 1;
            const userSearchInput = document.getElementById('userSearchInput');
            const userSearchBtn = document.getElementById('userSearchBtn');
            const clearUserSearch = document.getElementById('clearUserSearch');
            const usersTableBody = document.getElementById('usersTableBody');
            const userCountSpan = document.getElementById('userCount');
            const initialUserCount = <?php echo $total_users; ?>;

            function getAllUserRows() {
                if (!usersTableBody) return [];
                return Array.from(usersTableBody.querySelectorAll('tr:not(.no-search-results)'));
            }

            function escapeHtml(str) {
                return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
            }

            function applyUsersFilterAndPagination(targetPage = 1) {
                if (!usersTableBody) return;
                const rows = getAllUserRows();
                const query = userSearchInput ? userSearchInput.value.trim().toLowerCase() : '';

                // Toggle clear button
                if (clearUserSearch) {
                    clearUserSearch.style.display = query.length > 0 ? 'inline-block' : 'none';
                }

                // Match rows
                const matchedRows = rows.filter(row => {
                    const text = row.innerText.toLowerCase();
                    return !query || text.includes(query);
                });

                const totalMatched = matchedRows.length;
                const totalPages = Math.max(1, Math.ceil(totalMatched / USERS_PER_PAGE));

                if (targetPage > totalPages) targetPage = totalPages;
                if (targetPage < 1) targetPage = 1;
                currentUsersPage = targetPage;

                const startIndex = (currentUsersPage - 1) * USERS_PER_PAGE;
                const endIndex = Math.min(startIndex + USERS_PER_PAGE, totalMatched);

                // Hide all rows
                rows.forEach(r => r.style.display = 'none');

                // Display current page slice
                matchedRows.forEach((row, idx) => {
                    if (idx >= startIndex && idx < endIndex) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });

                // No results message
                let noResultRow = usersTableBody.querySelector('.no-search-results');
                if (totalMatched === 0 && query.length > 0) {
                    if (!noResultRow) {
                        noResultRow = document.createElement('tr');
                        noResultRow.className = 'no-search-results';
                        noResultRow.innerHTML = `<td colspan="7" class="text-center py-4 text-muted">
                            <i class="fa fa-search fa-2x mb-2 text-secondary opacity-50"></i>
                            <div>No users found matching "<strong>${escapeHtml(query)}</strong>"</div>
                        </td>`;
                        usersTableBody.appendChild(noResultRow);
                    } else {
                        noResultRow.style.display = '';
                        noResultRow.querySelector('strong').textContent = query;
                    }
                } else if (noResultRow) {
                    noResultRow.style.display = 'none';
                }

                // Update count and footer
                if (userCountSpan) {
                    userCountSpan.textContent = query.length > 0 ? `${totalMatched} of ${initialUserCount}` : initialUserCount;
                }

                const pageStartEl = document.getElementById('userPageStart');
                const pageEndEl = document.getElementById('userPageEnd');
                const totalItemsEl = document.getElementById('userTotalItems');
                const footerEl = document.getElementById('usersPaginationFooter');

                if (pageStartEl) pageStartEl.textContent = totalMatched === 0 ? 0 : startIndex + 1;
                if (pageEndEl) pageEndEl.textContent = endIndex;
                if (totalItemsEl) totalItemsEl.textContent = totalMatched;
                if (footerEl) footerEl.style.display = totalMatched === 0 ? 'none' : 'flex';

                renderUsersPaginationNav(totalPages, currentUsersPage);
            }

            function renderUsersPaginationNav(totalPages, activePage) {
                const navEl = document.getElementById('usersPaginationNav');
                if (!navEl) return;

                if (totalPages <= 1) {
                    navEl.innerHTML = '';
                    return;
                }

                let html = '';
                // Prev button
                if (activePage > 1) {
                    html += `<li class="page-item"><a class="page-link" href="#" onclick="applyUsersFilterAndPagination(${activePage - 1}); return false;">&laquo; Prev</a></li>`;
                } else {
                    html += `<li class="page-item disabled"><span class="page-link">&laquo; Prev</span></li>`;
                }

                // Numbers
                for (let p = 1; p <= totalPages; p++) {
                    if (p === 1 || p === totalPages || (p >= activePage - 2 && p <= activePage + 2)) {
                        if (p === activePage) {
                            html += `<li class="page-item active"><span class="page-link">${p}</span></li>`;
                        } else {
                            html += `<li class="page-item"><a class="page-link" href="#" onclick="applyUsersFilterAndPagination(${p}); return false;">${p}</a></li>`;
                        }
                    } else if (p === activePage - 3 || p === activePage + 3) {
                        html += `<li class="page-item disabled"><span class="page-link">&hellip;</span></li>`;
                    }
                }

                // Next button
                if (activePage < totalPages) {
                    html += `<li class="page-item"><a class="page-link" href="#" onclick="applyUsersFilterAndPagination(${activePage + 1}); return false;">Next &raquo;</a></li>`;
                } else {
                    html += `<li class="page-item disabled"><span class="page-link">Next &raquo;</span></li>`;
                }

                navEl.innerHTML = html;
            }

            // Expose pagination globally for onclick
            window.applyUsersFilterAndPagination = applyUsersFilterAndPagination;

            if (userSearchInput) {
                userSearchInput.addEventListener('input', () => applyUsersFilterAndPagination(1));
                userSearchInput.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        applyUsersFilterAndPagination(1);
                    }
                });
            }

            if (userSearchBtn) {
                userSearchBtn.addEventListener('click', () => applyUsersFilterAndPagination(1));
            }

            if (clearUserSearch) {
                clearUserSearch.addEventListener('click', function() {
                    if (userSearchInput) {
                        userSearchInput.value = '';
                        applyUsersFilterAndPagination(1);
                        userSearchInput.focus();
                    }
                });
            }

            // Initial pagination render
            applyUsersFilterAndPagination(1);

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

            document.querySelectorAll('.reset-mfa-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const userId = this.getAttribute('data-id');
                    const userName = this.getAttribute('data-name');
                    
                    if (confirm(`Are you sure you want to reset the Google Authenticator barcode for ${userName}?\n\nThis will clear their current setup so they can scan a fresh new barcode on their next login.`)) {
                        resetUserMfa(userId);
                    }
                });
            });

            // Modal open/close helpers
            function openRoleModal(userId, userName, currentRole) {
                const modalOverlay = document.getElementById('editRoleModalOverlay');
                const modalUserId = document.getElementById('modalUserId');
                const modalUserName = document.getElementById('modalUserName');
                const modalRoleSelect = document.getElementById('modalUserRoleSelect');
                
                if (!modalOverlay) return;
                
                modalUserId.value = userId;
                modalUserName.textContent = userName;
                
                if (modalRoleSelect) {
                    modalRoleSelect.value = currentRole || '';
                }
                
                modalOverlay.style.display = 'flex';
                setTimeout(function() {
                    modalOverlay.classList.add('active');
                }, 10);
            }

            function closeRoleModal() {
                const modalOverlay = document.getElementById('editRoleModalOverlay');
                if (!modalOverlay) return;
                
                modalOverlay.classList.remove('active');
                setTimeout(function() {
                    modalOverlay.style.display = 'none';
                }, 200);
            }

            // Handle Edit Role button clicks (Modal Trigger)
            document.querySelectorAll('.edit-role-btn').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const userId = this.getAttribute('data-id');
                    const userName = this.getAttribute('data-name');
                    const currentRole = this.getAttribute('data-role');
                    openRoleModal(userId, userName, currentRole);
                });
            });

            // Modal Close Buttons
            const closeRoleBtn = document.getElementById('closeRoleModalBtn');
            if (closeRoleBtn) {
                closeRoleBtn.addEventListener('click', closeRoleModal);
            }

            const cancelRoleBtn = document.getElementById('cancelRoleModalBtn');
            if (cancelRoleBtn) {
                cancelRoleBtn.addEventListener('click', closeRoleModal);
            }

            const modalOverlay = document.getElementById('editRoleModalOverlay');
            if (modalOverlay) {
                modalOverlay.addEventListener('click', function(e) {
                    if (e.target === modalOverlay) {
                        closeRoleModal();
                    }
                });
            }

            // Handle Save Role click
            const saveRoleBtn = document.getElementById('saveRoleBtn');
            if (saveRoleBtn) {
                saveRoleBtn.addEventListener('click', function() {
                    const userId = document.getElementById('modalUserId').value;
                    const newRole = document.getElementById('modalUserRoleSelect').value;
                    
                    if (!newRole) {
                        alert('Please select a valid user role.');
                        return;
                    }
                    
                    saveRoleBtn.disabled = true;
                    saveRoleBtn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> Updating...';
                    
                    const formData = new FormData();
                    formData.append('user_id', userId);
                    formData.append('role', newRole);
                    formData.append('update_role', '1');
                    formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
                    
                    fetch('createuser', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.text())
                    .then(data => {
                        alert(data);
                        window.location.href = 'createuser';
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while updating the role.');
                        saveRoleBtn.disabled = false;
                        saveRoleBtn.innerHTML = '<i class="fa fa-save me-1"></i> Update Role';
                    });
                });
            }
            
            // SMTP Settings Modal Controls
            const openSmtpBtn = document.getElementById('openSmtpConfigBtn');
            const smtpModal = document.getElementById('smtpModalOverlay');
            const closeSmtpBtn = document.getElementById('closeSmtpModalBtn');
            const cancelSmtpBtn = document.getElementById('cancelSmtpModalBtn');
            const saveSmtpBtn = document.getElementById('saveSmtpBtn');
            const sendSmtpTestBtn = document.getElementById('sendSmtpTestBtn');

            function openSmtpModal() {
                if (!smtpModal) return;
                smtpModal.style.display = 'flex';
                setTimeout(() => smtpModal.classList.add('active'), 10);
            }

            function closeSmtpModal() {
                if (!smtpModal) return;
                smtpModal.classList.remove('active');
                setTimeout(() => smtpModal.style.display = 'none', 200);
            }

            if (openSmtpBtn) openSmtpBtn.addEventListener('click', openSmtpModal);
            if (closeSmtpBtn) closeSmtpBtn.addEventListener('click', closeSmtpModal);
            if (cancelSmtpBtn) cancelSmtpBtn.addEventListener('click', closeSmtpModal);
            if (smtpModal) {
                smtpModal.addEventListener('click', function(e) {
                    if (e.target === smtpModal) closeSmtpModal();
                });
            }

            if (saveSmtpBtn) {
                saveSmtpBtn.addEventListener('click', function() {
                    const host = document.getElementById('smtpHostInput').value.trim();
                    const port = document.getElementById('smtpPortInput').value.trim();
                    const enc = document.getElementById('smtpEncInput').value;
                    const user = document.getElementById('smtpUserInput').value.trim();
                    const pass = document.getElementById('smtpPassInput').value.trim();
                    const fromAddr = document.getElementById('smtpFromAddrInput').value.trim();
                    const fromName = document.getElementById('smtpFromNameInput').value.trim();
                    const feedback = document.getElementById('smtpSaveFeedback');

                    saveSmtpBtn.disabled = true;
                    saveSmtpBtn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> Saving...';
                    if (feedback) feedback.innerHTML = '';

                    const fd = new FormData();
                    fd.append('save_smtp_settings', '1');
                    fd.append('smtp_host', host);
                    fd.append('smtp_port', port);
                    fd.append('smtp_encryption', enc);
                    fd.append('smtp_user', user);
                    fd.append('smtp_pass', pass);
                    fd.append('from_address', fromAddr);
                    fd.append('from_name', fromName);
                    fd.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');

                    fetch('createuser', {
                        method: 'POST',
                        body: fd
                    })
                    .then(res => res.json())
                    .then(data => {
                        saveSmtpBtn.disabled = false;
                        saveSmtpBtn.innerHTML = '<i class="fa fa-save me-1"></i> Save Settings';
                        if (feedback) {
                            if (data.success) {
                                feedback.innerHTML = '<span class="text-success fw-bold"><i class="fa fa-check-circle"></i> ' + data.message + '</span>';
                                setTimeout(() => { window.location.href = 'createuser'; }, 1200);
                            } else {
                                feedback.innerHTML = '<span class="text-danger fw-bold"><i class="fa fa-times-circle"></i> ' + data.message + '</span>';
                            }
                        }
                    })
                    .catch(err => {
                        saveSmtpBtn.disabled = false;
                        saveSmtpBtn.innerHTML = '<i class="fa fa-save me-1"></i> Save Settings';
                        if (feedback) feedback.innerHTML = '<span class="text-danger fw-bold">An error occurred while saving.</span>';
                    });
                });
            }

            if (sendSmtpTestBtn) {
                sendSmtpTestBtn.addEventListener('click', function() {
                    const testEmail = document.getElementById('smtpTestEmailInput').value.trim();
                    const host = document.getElementById('smtpHostInput').value.trim();
                    const port = document.getElementById('smtpPortInput').value.trim();
                    const enc = document.getElementById('smtpEncInput').value;
                    const user = document.getElementById('smtpUserInput').value.trim();
                    const pass = document.getElementById('smtpPassInput').value.trim();
                    const fromAddr = document.getElementById('smtpFromAddrInput').value.trim();
                    const fromName = document.getElementById('smtpFromNameInput').value.trim();
                    const feedback = document.getElementById('smtpTestFeedback');

                    if (!testEmail) {
                        alert('Please enter a destination email address to receive the test email.');
                        return;
                    }

                    sendSmtpTestBtn.disabled = true;
                    sendSmtpTestBtn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> Testing...';
                    if (feedback) feedback.innerHTML = '<span class="text-muted"><i class="fa fa-spinner fa-spin me-1"></i> Connecting to ' + host + ':' + port + ' via ' + enc.toUpperCase() + '...</span>';

                    const fd = new FormData();
                    fd.append('test_smtp_email', '1');
                    fd.append('test_email', testEmail);
                    fd.append('smtp_host', host);
                    fd.append('smtp_port', port);
                    fd.append('smtp_encryption', enc);
                    fd.append('smtp_user', user);
                    fd.append('smtp_pass', pass);
                    fd.append('from_address', fromAddr);
                    fd.append('from_name', fromName);
                    fd.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');

                    fetch('createuser', {
                        method: 'POST',
                        body: fd
                    })
                    .then(res => res.json())
                    .then(data => {
                        sendSmtpTestBtn.disabled = false;
                        sendSmtpTestBtn.innerHTML = '<i class="fa fa-send me-1"></i> Send Test Email';
                        if (feedback) {
                            if (data.success) {
                                feedback.innerHTML = '<div class="alert alert-success py-2 px-3 mb-0 mt-2"><i class="fa fa-check-circle me-1"></i> ' + data.message + '</div>';
                            } else {
                                feedback.innerHTML = '<div class="alert alert-danger py-2 px-3 mb-0 mt-2"><i class="fa fa-exclamation-circle me-1"></i> ' + data.message + '</div>';
                            }
                        }
                    })
                    .catch(err => {
                        sendSmtpTestBtn.disabled = false;
                        sendSmtpTestBtn.innerHTML = '<i class="fa fa-send me-1"></i> Send Test Email';
                        if (feedback) feedback.innerHTML = '<div class="alert alert-danger py-2 px-3 mb-0 mt-2">Connection or server error during SMTP test.</div>';
                    });
                });
            }
            
            // Clear form after successful submission
            <?php if (isset($success_msg)): ?>
                setTimeout(function() {
                    const form = document.querySelector('form[enctype="multipart/form-data"]');
                    if (form) form.reset();
                }, 3000);
            <?php endif; ?>
        });

        // Action: Activate / Deactivate User
        function updateUserStatus(userId, status) {
            const formData = new FormData();
            formData.append('user_id', userId);
            formData.append('status', status);
            formData.append('update_status', '1');
            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
            
            fetch('createuser', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                alert(data);
                window.location.href = 'createuser';
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating user status.');
            });
        }
        
        // Helper: Generate Random Password
        function generateRandomPassword() {
            const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%&*';
            let newPwd = 'Nis@';
            for (let i = 0; i < 6; i++) {
                newPwd += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            const pwdInput = document.getElementById('password');
            if (pwdInput) {
                pwdInput.value = newPwd;
                if (pwdInput.type === 'password') {
                    pwdInput.type = 'text';
                    const eyeIcon = document.getElementById('pwdEyeIcon');
                    if (eyeIcon) eyeIcon.className = 'fa fa-eye-slash';
                }
                const hint = document.getElementById('pwdHint');
                if (hint) hint.textContent = newPwd;
            }
        }

        // Helper: Reset to Default Password in Form
        function resetToDefaultPassword() {
            const pwdInput = document.getElementById('password');
            if (pwdInput) {
                pwdInput.value = 'password@123';
                const hint = document.getElementById('pwdHint');
                if (hint) hint.textContent = 'password@123';
            }
        }

        // Helper: Copy Password Value in Form
        function copyPasswordValue() {
            const pwdInput = document.getElementById('password');
            if (!pwdInput || !pwdInput.value) return;
            
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(pwdInput.value).then(function() {
                    flashCopySuccess();
                }).catch(function() {
                    fallbackCopy(pwdInput);
                });
            } else {
                fallbackCopy(pwdInput);
            }
        }

        function fallbackCopy(inputEl) {
            inputEl.select();
            inputEl.setSelectionRange(0, 99999);
            try {
                document.execCommand('copy');
                flashCopySuccess();
            } catch (err) {}
        }

        function flashCopySuccess() {
            const copyIcon = document.getElementById('copyPwdIcon');
            if (copyIcon) {
                copyIcon.className = 'fa fa-check text-success';
                setTimeout(function() {
                    copyIcon.className = 'fa fa-clone';
                }, 1800);
            }
        }
        
        function togglePasswordVisibility(fieldId, btn) {
            const field = document.getElementById(fieldId);
            if (!field) return;
            const isPassword = field.type === 'password';
            field.type = isPassword ? 'text' : 'password';
            const icon = (btn && btn.querySelector('i')) || document.getElementById('pwdEyeIcon');
            if (icon) {
                icon.className = isPassword ? 'fa fa-eye-slash' : 'fa fa-eye';
            }
        }

        // Action: Reset User Password to default
        function resetUserPassword(userId) {
            const formData = new FormData();
            formData.append('user_id', userId);
            formData.append('reset_password', '1');
            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
            
            fetch('createuser', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                alert(data);
                window.location.href = 'createuser';
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while resetting the password.');
            });
        }

        // Action: Reset Google Authenticator MFA
        function resetUserMfa(userId) {
            const formData = new FormData();
            formData.append('user_id', userId);
            formData.append('reset_mfa', '1');
            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
            
            fetch('createuser', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                alert(data);
                window.location.href = 'createuser';
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while resetting Google Authenticator.');
            });
        }



        // Helper: Copy Credentials in Success Alert
        function copyCredText(text, btnElement) {
            if (!navigator.clipboard) {
                const tempInput = document.createElement('input');
                tempInput.value = text;
                document.body.appendChild(tempInput);
                tempInput.select();
                document.execCommand('copy');
                document.body.removeChild(tempInput);
            } else {
                navigator.clipboard.writeText(text);
            }
            if (btnElement) {
                const origHtml = btnElement.innerHTML;
                btnElement.innerHTML = '<i class="fa fa-check text-success"></i>';
                setTimeout(() => {
                    btnElement.innerHTML = origHtml;
                }, 1800);
            }
        }
    </script>
</body>
</html>

