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
                                $update_query = "UPDATE users SET fname = ?, lname = ?, email = ?, password = ?, photo = ? WHERE id = ?";
                                $stmt_update = mysqli_prepare($conn, $update_query);
                                mysqli_stmt_bind_param($stmt_update, "sssssi", $fname, $lname, $email, $new_pwd_hash, $photo, $userId);
                            } else {
                                $update_query = "UPDATE users SET fname = ?, lname = ?, email = ?, photo = ? WHERE id = ?";
                                $stmt_update = mysqli_prepare($conn, $update_query);
                                mysqli_stmt_bind_param($stmt_update, "ssssi", $fname, $lname, $email, $photo, $userId);
                            }
                            
                            if (mysqli_stmt_execute($stmt_update)) {
                                $success_msg = "Profile updated successfully!";
                                
                                // Update session variables
                                $_SESSION['full_name'] = $fname . ' ' . $lname;
                                $_SESSION['email'] = $email;
                                $_SESSION['photo'] = $photo;
                                $fullName = $_SESSION['full_name'];
                                
                                // Insert activity log
                                $log_action = "UPDATE";
                                $log_desc = "Updated user profile details";
                                $log_query = "INSERT INTO activity_log (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)";
                                $stmt_log = mysqli_prepare($conn, $log_query);
                                $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                                mysqli_stmt_bind_param($stmt_log, "ssss", $username, $log_action, $log_desc, $ip);
                                mysqli_stmt_execute($stmt_log);
                                mysqli_stmt_close($stmt_log);
                                
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
                            mysqli_stmt_close($stmt_update);
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
            height: 140px;
            background: linear-gradient(135deg, var(--primary), #113f1f);
            position: relative;
        }
        
        .profile-body {
            padding: 40px;
            position: relative;
        }
        
        .profile-avatar-section {
            margin-top: -100px;
            margin-bottom: 30px;
            text-align: center;
            position: relative;
            z-index: 2;
        }
        
        .profile-avatar-outer {
            width: 130px;
            height: 130px;
            border-radius: 50%;
            background: white;
            padding: 5px;
            display: inline-block;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }
        
        .profile-avatar {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            overflow: hidden;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: bold;
        }
        
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .profile-title {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .profile-title h2 {
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 5px;
        }
        
        .profile-title p {
            color: var(--gray);
            font-weight: 500;
            font-size: 0.95rem;
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
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="photo">Passport Photograph (JPG or PNG)</label>
                                        <input type="file" id="photo" class="form-control" name="photo" accept=".jpg,.jpeg,.png">
                                        <small class="text-muted">Upload a new photograph to change your current avatar. Max file size: 2MB.</small>
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
                                        <input type="password" id="new_password" class="form-control" name="new_password" 
                                               placeholder="Leave blank to keep current password">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="confirm_password">Confirm New Password</label>
                                        <input type="password" id="confirm_password" class="form-control" name="confirm_password" 
                                               placeholder="Confirm new password">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Security verification before saving changes -->
                            <h5 class="form-section-title mt-4">
                                <i class="fa fa-shield text-primary"></i> Confirm Authorization
                            </h5>
                            
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="current_password" class="text-danger fw-bold">Current Password (Required)</label>
                                        <input type="password" id="current_password" class="form-control border-danger" name="current_password" 
                                               placeholder="Enter current password to save profile changes" required>
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
        // Handle Sidebar Toggle on Mobile
        document.addEventListener('DOMContentLoaded', function() {
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
