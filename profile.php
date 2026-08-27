<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once('security.php');
require_once('db/db.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index");
    exit();
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'DataEntry';
$username = $_SESSION['username'] ?? 'User';
$fullName = $_SESSION['full_name'] ?? $username;

// Get current page for active menu
$current_page = basename($_SERVER['PHP_SELF']);

// Check database connection
if (!$conn) {
    die("<div class='alert alert-danger'>Database connection failed</div>");
}

// Fetch current user details
$query = "SELECT * FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$user) {
    die("<div class='alert alert-danger'>User account not found</div>");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_msg = "Security validation failed. Please refresh the page.";
    } else {
        $fname = mysqli_real_escape_string($conn, trim($_POST['fname']));
        $lname = mysqli_real_escape_string($conn, trim($_POST['lname']));
        $email = mysqli_real_escape_string($conn, trim($_POST['email']));
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // 1. Verify current password
        if (empty($current_password)) {
            $error_msg = "Please enter your current password to authorize changes.";
        } else {
            // Re-fetch user password hash
            $pwd_query = "SELECT password, photo FROM users WHERE id = ?";
            $stmt_pwd = mysqli_prepare($conn, $pwd_query);
            mysqli_stmt_bind_param($stmt_pwd, "i", $userId);
            mysqli_stmt_execute($stmt_pwd);
            $pwd_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_pwd));
            mysqli_stmt_close($stmt_pwd);
            
            if (!password_verify($current_password, $pwd_row['password'])) {
                $error_msg = "Incorrect current password.";
            } else {
                // Current password is valid! Proceed with validation and updates
                $update_password = false;
                $new_pwd_hash = '';
                
                // If they want to change password
                if (!empty($new_password) || !empty($confirm_password)) {
                    if ($new_password !== $confirm_password) {
                        $error_msg = "New password and confirmation do not match.";
                    } elseif (strlen($new_password) < 6) {
                        $error_msg = "New password must be at least 6 characters long.";
                    } else {
                        $update_password = true;
                        $new_pwd_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    }
                }
                
                if (!isset($error_msg)) {
                    // Check if email already exists for another user
                    $email_check_query = "SELECT id FROM users WHERE email = ? AND id != ?";
                    $stmt_email = mysqli_prepare($conn, $email_check_query);
                    mysqli_stmt_bind_param($stmt_email, "si", $email, $userId);
                    mysqli_stmt_execute($stmt_email);
                    mysqli_stmt_store_result($stmt_email);
                    $email_exists = mysqli_stmt_num_rows($stmt_email) > 0;
                    mysqli_stmt_close($stmt_email);
                    
                    if ($email_exists) {
                        $error_msg = "Email address is already in use by another user.";
                    } else {
                        // File upload for avatar (photo)
                        $photo = $pwd_row['photo'];
                        
                        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
                            $allowed = ['jpg', 'jpeg', 'png'];
                            $filename = $_FILES['photo']['name'];
                            $filetmp = $_FILES['photo']['tmp_name'];
                            $fileSize = $_FILES['photo']['size'];
                            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                            
                            if (in_array($ext, $allowed)) {
                                if ($fileSize <= 2097152) { // 2MB
                                    $newFilename = uniqid() . '.' . $ext;
                                    $uploadPath = 'uploads/' . $newFilename;
                                    
                                    if (!is_dir('uploads')) {
                                        mkdir('uploads', 0755, true);
                                    }
                                    
                                    if (move_uploaded_file($filetmp, $uploadPath)) {
                                        // Delete old photo file if exists
                                        if (!empty($photo) && file_exists($photo)) {
                                            @unlink($photo);
                                        }
                                        $photo = $uploadPath;
                                    } else {
                                        $error_msg = "Failed to upload photo.";
                                    }
                                } else {
                                    $error_msg = "Photo file size must be less than 2MB.";
                                }
                            } else {
                                $error_msg = "Only JPG, JPEG, and PNG photo formats are allowed.";
                            }
                        }
                        
                        if (!isset($error_msg)) {
                            // Update users table
                            if ($update_password) {
                                $update_query = "UPDATE users SET fname = ?, lname = ?, email = ?, password = ?, photo = ?, password_reset_required = 0 WHERE id = ?";
                                $stmt_update = mysqli_prepare($conn, $update_query);
                                mysqli_stmt_bind_param($stmt_update, "sssssi", $fname, $lname, $email, $new_pwd_hash, $photo, $userId);
                            } else {
                                $update_query = "UPDATE users SET fname = ?, lname = ?, email = ?, photo = ? WHERE id = ?";
                                $stmt_update = mysqli_prepare($conn, $update_query);
                                mysqli_stmt_bind_param($stmt_update, "ssssi", $fname, $lname, $email, $photo, $userId);
                            }
                            
                            if (mysqli_stmt_execute($stmt_update)) {
                                if ($update_password) {
                                    // Insert activity log
                                    logActivity($conn, "UPDATE_PROFILE", "Updated password and profile details", $username);
                                    mysqli_stmt_close($stmt_update);

                                    // Unset user session variables to automatically log out user
                                    unset($_SESSION['user_id']);
                                    unset($_SESSION['username']);
                                    unset($_SESSION['full_name']);
                                    unset($_SESSION['email']);
                                    unset($_SESSION['role']);
                                    unset($_SESSION['photo']);
                                    unset($_SESSION['temp_user']);
                                    unset($_SESSION['temp_mfa_secret']);

                                    $_SESSION['message'] = [
                                        'type' => 'success',
                                        'text' => 'Password updated successfully! Please sign in with your new password.'
                                    ];

                                    header("Location: index");
                                    exit();
                                }

                                $success_msg = "Profile updated successfully!";
                                
                                // Update session variables
                                $_SESSION['full_name'] = $fname . ' ' . $lname;
                                $_SESSION['email'] = $email;
                                $_SESSION['photo'] = $photo;
                                $fullName = $_SESSION['full_name'];
                                
                                // Insert activity log
                                logActivity($conn, "UPDATE_PROFILE", "Updated user profile details", $username);
                                
                                // Re-fetch user details for the form
                                $user_query = "SELECT * FROM users WHERE id = ?";
                                $stmt_user = mysqli_prepare($conn, $user_query);
                                mysqli_stmt_bind_param($stmt_user, "i", $userId);
                                mysqli_stmt_execute($stmt_user);
                                $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_user));
                                mysqli_stmt_close($stmt_user);
                            } else {
                                $error_msg = "Database update failed: " . mysqli_error($conn);
                            }
                            if (isset($stmt_update) && $stmt_update) {
                                mysqli_stmt_close($stmt_update);
                            }
                        }
                    }
                }
            }
        }
    }
}

// Resolve photo path
$user_photo_path = '';
if (!empty($user['photo'])) {
    if (strpos($user['photo'], 'uploads/') === 0) {
        $user_photo_path = $user['photo'];
    } else {
        $user_photo_path = 'uploads/' . $user['photo'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>NISREV - My Profile</title>
    <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
    <!-- Bootstrap CSS -->
    <link href="vendors/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="vendors/font-awesome/css/font-awesome.min.css" rel="stylesheet">
    
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
        
        .page-content {
            padding: 30px;
        }
        
        /* Profile Layout */
        .profile-container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .profile-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            border: none;
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .profile-header-banner {
            height: 55px;
            background: linear-gradient(135deg, var(--primary), #113f1f);
            position: relative;
        }
        
        .profile-body {
            padding: 16px 28px 28px;
            position: relative;
        }
        
        .profile-avatar-section {
            margin-top: -42px;
            margin-bottom: 10px;
            text-align: center;
            position: relative;
            z-index: 2;
        }
        
        .profile-avatar-outer {
            width: 86px;
            height: 86px;
            border-radius: 50%;
            background: #ffffff;
            border: 3.5px solid #1a5c2e;
            padding: 2px;
            display: inline-block;
            box-shadow: 0 4px 14px rgba(26, 92, 46, 0.3);
        }
        
        .profile-avatar {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            overflow: hidden;
            background: #ffffff;
            color: #1a5c2e;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            font-weight: 800;
        }
        
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center top;
            display: block;
            image-rendering: -webkit-optimize-contrast;
            image-rendering: crisp-edges;
            filter: contrast(1.08) saturate(1.06) brightness(1.02);
        }
        
        .profile-title {
            text-align: center;
            margin-bottom: 22px;
        }
        
        .profile-title h2 {
            font-weight: 700;
            color: var(--dark);
            font-size: 1.35rem;
            margin-bottom: 2px;
        }
        
        .profile-title p {
            color: var(--gray);
            font-weight: 500;
            font-size: 0.85rem;
            margin-bottom: 0;
        }
        
        .form-section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eef2f5;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            font-weight: 600;
            font-size: 0.85rem;
            color: #4a5568;
            margin-bottom: 8px;
            display: block;
        }
        
        .form-control, .form-select {
            height: 44px;
            border-radius: 8px;
            border: 1px solid #dcdcdc;
            padding: 10px 16px;
            font-size: 0.95rem;
            transition: all 0.2s ease-in-out;
            background-color: #fff;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26, 92, 46, 0.15);
            outline: none;
        }
        
        .form-control[disabled], .form-control[readonly] {
            background-color: #f1f3f5;
            color: #495057;
            cursor: not-allowed;
            border-color: #dee2e6;
        }
        
        .btn-submit {
            height: 46px;
            padding: 0 30px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            background: var(--primary);
            border: none;
            color: white;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(26, 92, 46, 0.2);
        }
        
        .btn-submit:hover {
            background: #113f1f;
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 6px 15px rgba(26, 92, 46, 0.3);
        }

        /* Password toggle wrapper & button */
        .password-field-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .password-field-wrapper .form-control {
            padding-right: 42px;
            width: 100%;
        }
        
        .password-field-wrapper .toggle-password-btn {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            color: #6c757d;
            cursor: pointer;
            padding: 8px 10px;
            z-index: 4;
            transition: color 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }
        
        .password-field-wrapper .toggle-password-btn:hover {
            color: var(--primary);
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
            width: 68px;
            height: 68px;
            min-width: 68px;
            border-radius: 50%;
            overflow: hidden;
            background: #ffffff;
            border: 3px solid #1a5c2e;
            box-shadow: 0 3px 10px rgba(26, 92, 46, 0.3);
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
            object-position: center top;
            display: block;
            image-rendering: -webkit-optimize-contrast;
            image-rendering: crisp-edges;
            filter: contrast(1.08) saturate(1.06) brightness(1.02);
        }

        .photo-placeholder i {
            font-size: 28px;
            color: #94a3b8;
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
        
        /* Alert customization */
        .alert {
            border-left: none !important;
            border-radius: 8px;
            padding: 15px 20px;
            font-weight: 500;
            font-size: 0.95rem;
            margin-bottom: 25px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.02);
        }
        
        .alert-success {
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534;
        }
        
        .alert-danger {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }
    </style>
</head>
<body class="d-flex">
    <!-- Sidebar navigation component -->
    <?php include('sidebar.php'); ?>
    
    <div class="main-content flex-grow-1">
        <!-- Top Navigation -->
        <nav class="top-nav">
            <div class="nav-left">
                <button class="mobile-menu-btn" id="mobileMenuBtn">
                    <i class="fa fa-bars"></i>
                    <span class="d-none d-sm-inline">Menu</span>
                </button>
                <h4>My Profile</h4>
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

        <div class="page-content">
            <div class="profile-container">
                
                <?php if (isset($success_msg)): ?>
                    <div class="alert alert-success">
                        <i class="fa fa-check-circle me-2"></i><?php echo htmlspecialchars($success_msg); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error_msg)): ?>
                    <div class="alert alert-danger">
                        <i class="fa fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error_msg); ?>
                    </div>
                <?php endif; ?>
                
                <div class="profile-card">
                    <!-- Banner -->
                    <div class="profile-header-banner"></div>
                    
                    <div class="profile-body">
                        <form method="POST" action="profile" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            
                            <!-- Avatar section -->
                            <div class="profile-avatar-section">
                                <div class="profile-avatar-outer">
                                    <div class="profile-avatar">
                                        <?php if (!empty($user_photo_path) && file_exists($user_photo_path)): ?>
                                            <img src="<?php echo htmlspecialchars($user_photo_path); ?>" alt="Avatar">
                                        <?php else: ?>
                                            <?php 
                                            $initials = strtoupper(substr($user['fname'] ?? '', 0, 1) . substr($user['lname'] ?? '', 0, 1));
                                            echo htmlspecialchars($initials ?: 'U');
                                            ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Title -->
                            <div class="profile-title">
                                <h2><?php echo htmlspecialchars($user['fname'] . ' ' . $user['lname']); ?></h2>
                                <p><?php echo htmlspecialchars($user['role']); ?> | Service No: <?php echo htmlspecialchars($user['service_no']); ?></p>
                            </div>
                            
                            <!-- Basic details -->
                            <h5 class="form-section-title">
                                <i class="fa fa-id-card-o text-primary"></i> Personal Details
                            </h5>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="service_no">Service Number</label>
                                        <input type="text" id="service_no" class="form-control" name="service_no" 
                                               value="<?php echo htmlspecialchars($user['service_no']); ?>" readonly disabled>
                                        <small class="text-muted">The service number is linked to your login ID and cannot be changed.</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="email">Email Address</label>
                                        <input type="email" id="email" class="form-control" name="email" 
                                               value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="fname">First Name</label>
                                        <input type="text" id="fname" class="form-control" name="fname" 
                                               value="<?php echo htmlspecialchars($user['fname']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="lname">Last Name</label>
                                        <input type="text" id="lname" class="form-control" name="lname" 
                                               value="<?php echo htmlspecialchars($user['lname']); ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Photo photograph upload -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="d-flex justify-content-between align-items-center">
                                            <span>Passport Photograph (JPG or PNG)</span>
                                            <span class="badge bg-light text-muted" style="font-size: 0.75rem; font-weight: normal; border: 1px solid #e2e8f0;">Max size: 2MB</span>
                                        </label>
                                        
                                        <div class="photo-upload-wrapper" id="photoUploadWrapper" style="max-width: 440px;">
                                            <div class="photo-preview-box" id="photoPreviewContainer">
                                                <?php if (!empty($user_photo_path) && file_exists($user_photo_path)): ?>
                                                    <img id="photoPreviewImg" src="<?php echo htmlspecialchars($user_photo_path); ?>" alt="Preview">
                                                    <div class="photo-placeholder" id="photoPlaceholder" style="display: none;">
                                                        <i class="fa fa-user-circle"></i>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="photo-placeholder" id="photoPlaceholder">
                                                        <i class="fa fa-user-circle"></i>
                                                    </div>
                                                    <img id="photoPreviewImg" src="" alt="Preview" style="display: none;">
                                                <?php endif; ?>
                                            </div>
                                            <div class="photo-upload-controls">
                                                <div class="photo-dropzone" id="photoDropzone" onclick="document.getElementById('photo').click()">
                                                    <i class="fa fa-cloud-upload upload-icon"></i>
                                                    <div class="upload-text">
                                                        <strong>Click to browse</strong> or drag & drop new photo here
                                                    </div>
                                                    <div class="upload-hint">Upload a new photograph. Max file size: 2MB.</div>
                                                </div>
                                                <input name="photo" class="d-none" id="photo" type="file" accept=".jpg,.jpeg,.png" />
                                                <div id="photoFileInfo" class="photo-file-info" style="display: none;">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <i class="fa fa-image text-success"></i>
                                                        <span id="photoFileName" class="text-truncate fw-bold" style="max-width: 140px; font-size: 0.78rem; color: #1e293b;"></span>
                                                        <span id="photoFileSize" class="badge bg-secondary" style="font-size: 0.7rem;"></span>
                                                    </div>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" id="removePhotoBtn" style="padding: 2px 8px; font-size: 0.75rem;" title="Cancel selected image">
                                                        <i class="fa fa-trash"></i> Cancel
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Password modification section -->
                            <h5 class="form-section-title mt-4">
                                <i class="fa fa-lock text-primary"></i> Change Password
                            </h5>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="new_password">New Password</label>
                                        <div class="password-field-wrapper">
                                            <input type="password" id="new_password" class="form-control no-uppercase" name="new_password" 
                                                   placeholder="Leave blank to keep current password" data-password-field="true" data-no-uppercase="true" style="text-transform: none !important;">
                                            <button type="button" class="toggle-password-btn" onclick="togglePasswordVisibility('new_password', this)" aria-label="Toggle password visibility">
                                                <i class="fa fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="confirm_password">Confirm New Password</label>
                                        <div class="password-field-wrapper">
                                            <input type="password" id="confirm_password" class="form-control no-uppercase" name="confirm_password" 
                                                   placeholder="Confirm new password" data-password-field="true" data-no-uppercase="true" style="text-transform: none !important;">
                                            <button type="button" class="toggle-password-btn" onclick="togglePasswordVisibility('confirm_password', this)" aria-label="Toggle password visibility">
                                                <i class="fa fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Security verification before saving changes -->
                            <h5 class="form-section-title mt-4">
                                <i class="fa fa-shield text-primary"></i> Confirm Authorization
                            </h5>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="current_password" class="text-danger fw-bold">Current Password (Required)</label>
                                        <div class="password-field-wrapper">
                                            <input type="password" id="current_password" class="form-control border-danger no-uppercase" name="current_password" 
                                                   placeholder="Enter current password to save profile changes" data-password-field="true" data-no-uppercase="true" style="text-transform: none !important;" required>
                                            <button type="button" class="toggle-password-btn" onclick="togglePasswordVisibility('current_password', this)" aria-label="Toggle password visibility">
                                                <i class="fa fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-end mt-4">
                                <button type="submit" name="update_profile" class="btn btn-submit">
                                    <i class="fa fa-save me-1"></i> Save Changes
                                </button>
                            </div>
                        </form>
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
    </div>
    
    <!-- Bootstrap JS -->
    <script src="vendors/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle password visibility
        function togglePasswordVisibility(fieldId, btn) {
            const field = document.getElementById(fieldId);
            if (!field) return;
            const isPassword = field.type === 'password';
            field.type = isPassword ? 'text' : 'password';
            const icon = btn.querySelector('i');
            if (icon) {
                icon.className = isPassword ? 'fa fa-eye-slash' : 'fa fa-eye';
            }
        }

        // Handle Passport Photograph Preview & Drag-Drop
        document.addEventListener('DOMContentLoaded', function() {
            const photoInput = document.getElementById('photo');
            const photoWrapper = document.getElementById('photoUploadWrapper');
            const photoPreviewImg = document.getElementById('photoPreviewImg');
            const photoPlaceholder = document.getElementById('photoPlaceholder');
            const photoFileInfo = document.getElementById('photoFileInfo');
            const photoFileName = document.getElementById('photoFileName');
            const photoFileSize = document.getElementById('photoFileSize');
            const removePhotoBtn = document.getElementById('removePhotoBtn');
            const initialPhotoSrc = "<?php echo (!empty($user_photo_path) && file_exists($user_photo_path)) ? htmlspecialchars($user_photo_path) : ''; ?>";

            function handlePhotoSelection(file) {
                if (!file) return;

                const validTypes = ['image/jpeg', 'image/jpg', 'image/png'];
                if (!validTypes.includes(file.type.toLowerCase())) {
                    alert('Invalid file format. Please upload a JPG or PNG image.');
                    if (photoInput) photoInput.value = '';
                    return;
                }

                const fileSizeMB = file.size / (1024 * 1024);
                if (fileSizeMB > 2) {
                    alert('File size exceeds 2MB limit. Please choose a smaller image.');
                    if (photoInput) photoInput.value = '';
                    return;
                }

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
                    if (initialPhotoSrc) {
                        if (photoPreviewImg) {
                            photoPreviewImg.src = initialPhotoSrc;
                            photoPreviewImg.style.display = 'block';
                        }
                        if (photoPlaceholder) photoPlaceholder.style.display = 'none';
                    } else {
                        if (photoPreviewImg) {
                            photoPreviewImg.src = '';
                            photoPreviewImg.style.display = 'none';
                        }
                        if (photoPlaceholder) photoPlaceholder.style.display = 'flex';
                    }
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

            // Handle Sidebar Toggle on Mobile
            const toggle = document.getElementById('sidebarToggle');
            const sidebar = document.querySelector('.sidebar');
            if (toggle && sidebar) {
                toggle.addEventListener('click', function() {
                    sidebar.classList.toggle('active');
                });
            }
        });
    </script>
</body>
</html>
<?php
// Close database connection
mysqli_close($conn);
?>
