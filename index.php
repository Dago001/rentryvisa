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

require_once('GoogleAuthenticator.php');
require_once('vendors/phpqrcode/qrlib.php');

// Check if google_auth_secret column exists, add if not
$column_check = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'google_auth_secret'");
if ($column_check && mysqli_num_rows($column_check) == 0) {
    mysqli_query($conn, "ALTER TABLE users ADD COLUMN google_auth_secret VARCHAR(32) DEFAULT NULL");
}



// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    // Redirect based on user role
    $role = $_SESSION['role'] ?? 'DataEntry';
    
    // Admin, Supervisor, and ViewOnly go to general admin dashboard
    if ($role === 'Admin' || $role === 'Supervisor' || $role === 'ViewOnly' || strcasecmp($role, 'viewonly') === 0 || strcasecmp($role, 'view only') === 0) {
        showDashboard(); // General Dashboard for Admin, Supervisor, and ViewOnly
    } else {
        // Regular DataEntry users go to user dashboard
        header("Location: user_dashboard");
        exit();
    }
} else {
    showLoginForm();
}

function showLoginForm() {
    global $conn;
    
    $error_message = '';

    // Handle cancel request to go back to username/password login
    if (isset($_GET['cancel_mfa'])) {
        unset($_SESSION['temp_user']);
        unset($_SESSION['temp_mfa_secret']);
        header("Location: index");
        exit();
    }

    // Handle submission of new password when password reset is required (Step 3)
    if (isset($_POST['set_new_password']) && isset($_SESSION['temp_user']) && !empty($_SESSION['temp_user']['mfa_verified'])) {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $error_message = "Security validation failed. Please refresh the page.";
        } else {
            $new_pwd = $_POST['new_password'] ?? '';
            $confirm_pwd = $_POST['confirm_password'] ?? '';
            $userId = intval($_SESSION['temp_user']['id']);

            if (empty($new_pwd) || empty($confirm_pwd)) {
                $error_message = "All password fields are required.";
            } elseif (strlen($new_pwd) < 6) {
                $error_message = "New password must be at least 6 characters long.";
            } elseif ($new_pwd !== $confirm_pwd) {
                $error_message = "New password and confirmation password do not match.";
            } elseif ($new_pwd === 'password@123') {
                $error_message = "New password cannot be the default 'password@123'. Please choose a unique password.";
            } else {
                $hashed_password = password_hash($new_pwd, PASSWORD_DEFAULT);
                $stmt = mysqli_prepare($conn, "UPDATE users SET password = ?, password_reset_required = 0 WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "si", $hashed_password, $userId);

                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);

                    // Audit log
                    $username_who_did = $_SESSION['temp_user']['username'];
                    $log_action = "PASSWORD_RESET_ON_LOGIN";
                    $log_desc = "User reset default password upon login";
                    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                    $log_sql = "INSERT INTO activity_log (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)";
                    $log_stmt = mysqli_prepare($conn, $log_sql);
                    if ($log_stmt) {
                        mysqli_stmt_bind_param($log_stmt, "ssss", $username_who_did, $log_action, $log_desc, $ip);
                        mysqli_stmt_execute($log_stmt);
                        mysqli_stmt_close($log_stmt);
                    }

                    // Complete login
                    $_SESSION['username'] = $_SESSION['temp_user']['username'];
                    $_SESSION['user_id'] = $_SESSION['temp_user']['id'];
                    $_SESSION['full_name'] = $_SESSION['temp_user']['full_name'];
                    $_SESSION['email'] = $_SESSION['temp_user']['email'];
                    $_SESSION['role'] = $_SESSION['temp_user']['role'];
                    $_SESSION['photo'] = $_SESSION['temp_user']['photo'];

                    unset($_SESSION['temp_user']);
                    unset($_SESSION['temp_mfa_secret']);
                    session_regenerate_id(true);

                    // Redirect based on role
                    $finalRole = $_SESSION['role'] ?? 'DataEntry';
                    if ($finalRole === 'Admin' || $finalRole === 'Supervisor' || $finalRole === 'ViewOnly' || strcasecmp($finalRole, 'viewonly') === 0 || strcasecmp($finalRole, 'view only') === 0) {
                        header("Location: index");
                    } else {
                        header("Location: user_dashboard");
                    }
                    exit();
                } else {
                    $error_message = "Error updating password: " . mysqli_error($conn);
                }
            }
        }
    }

    // Handle verification of Google Authenticator code (Step 2)
    if (isset($_POST['verify_mfa']) && isset($_SESSION['temp_user'])) {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $error_message = "Security validation failed. Please refresh the page.";
        } else {
            $mfa_code = trim($_POST['mfa_code'] ?? '');
            $secret = $_SESSION['temp_mfa_secret'] ?? '';

            if (empty($mfa_code)) {
                $error_message = "Authenticator code is required.";
            } else {
                $ga = new GoogleAuthenticator();
                if ($ga->verifyCode($secret, $mfa_code, 2)) {
                    // Code is valid! 
                    // Check if we need to save the secret for a first-time user
                    $userId = intval($_SESSION['temp_user']['id']);
                    $db_check = mysqli_query($conn, "SELECT google_auth_secret, password_reset_required, password FROM users WHERE id = '$userId'");
                    $user_row = mysqli_fetch_assoc($db_check);
                    
                    if (empty($user_row['google_auth_secret'])) {
                        // Save the secret to the database
                        $stmt = mysqli_prepare($conn, "UPDATE users SET google_auth_secret = ? WHERE id = ?");
                        mysqli_stmt_bind_param($stmt, "si", $secret, $userId);
                        mysqli_stmt_execute($stmt);
                        mysqli_stmt_close($stmt);
                    }

                    // Check if user is required to reset default password
                    $must_reset = (!empty($user_row['password_reset_required']) && $user_row['password_reset_required'] == 1) 
                                  || (!empty($_SESSION['temp_user']['password_reset_required']) && $_SESSION['temp_user']['password_reset_required'] == 1)
                                  || password_verify('password@123', $user_row['password']);

                    if ($must_reset) {
                        $_SESSION['temp_user']['mfa_verified'] = true;
                        $_SESSION['temp_user']['password_reset_required'] = 1;
                        header("Location: index");
                        exit();
                    }

                    // Complete the normal login
                    $_SESSION['username'] = $_SESSION['temp_user']['username'];
                    $_SESSION['user_id'] = $_SESSION['temp_user']['id'];
                    $_SESSION['full_name'] = $_SESSION['temp_user']['full_name'];
                    $_SESSION['email'] = $_SESSION['temp_user']['email'];
                    $_SESSION['role'] = $_SESSION['temp_user']['role'];
                    $_SESSION['photo'] = $_SESSION['temp_user']['photo'];

                    unset($_SESSION['temp_user']);
                    unset($_SESSION['temp_mfa_secret']);
                    session_regenerate_id(true);

                    // Redirect based on role
                    $finalRole = $_SESSION['role'] ?? 'DataEntry';
                    if ($finalRole === 'Admin' || $finalRole === 'Supervisor' || $finalRole === 'ViewOnly' || strcasecmp($finalRole, 'viewonly') === 0 || strcasecmp($finalRole, 'view only') === 0) {
                        header("Location: index");
                    } else {
                        header("Location: user_dashboard");
                    }
                    exit();
                } else {
                    $error_message = "Invalid Google Authenticator code. Please try again.";
                }
            }
        }
    }

    // Handle primary credentials verification (Step 1)
    if (isset($_POST['login']) && !isset($_SESSION['temp_user'])) {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $error_message = "Security validation failed. Please refresh the page.";
        } else {
            $username = trim($_POST['username']);
            $password = trim($_POST['password']);

            if (!empty($username) && !empty($password)) {
                $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE (email = ? OR service_no = ?) AND status = 'active'");
                mysqli_stmt_bind_param($stmt, "ss", $username, $username);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                if ($row = mysqli_fetch_assoc($result)) {
                    if (password_verify($password, $row['password'])) {
                        // Store user details in temporary session
                        $is_first_time = empty($row['google_auth_secret']);
                        $is_pwd_reset_required = (!empty($row['password_reset_required']) && $row['password_reset_required'] == 1) || ($password === 'password@123');
                        
                        $_SESSION['temp_user'] = [
                            'id' => $row['id'],
                            'username' => $row['service_no'],
                            'full_name' => $row['fname'] . ' ' . $row['lname'],
                            'email' => $row['email'],
                            'role' => $row['role'],
                            'photo' => $row['photo'],
                            'first_time' => $is_first_time,
                            'password_reset_required' => $is_pwd_reset_required ? 1 : 0
                        ];

                        $ga = new GoogleAuthenticator();
                        if ($is_first_time) {
                            // First-time user: Generate a new secret
                            $secret = $ga->createSecret();
                            $_SESSION['temp_mfa_secret'] = $secret;
                        } else {
                            // Returning user: Load their existing secret
                            $_SESSION['temp_mfa_secret'] = $row['google_auth_secret'];
                        }

                        // Refresh page to show MFA screen
                        header("Location: index");
                        exit();
                    } else {
                        $error_message = "Invalid login credentials";
                    }
                } else {
                    $error_message = "Invalid login credentials or account is deactivated";
                }
                mysqli_stmt_close($stmt);
            } else {
                $error_message = "All fields are required.";
            }
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>NIS Re-Entry Visa System - Login</title>
        <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
        <link href="vendors/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="vendors/font-awesome/css/font-awesome.min.css" rel="stylesheet">
        <script src="vendors/qrcodejs/qrcode.min.js"></script>
        <style>
            :root {
                --primary: #1a5c2e;
                --secondary: #d4af37;
                --dark: #2c3e50;
                --light: #f8f9fa;
                --danger: #dc3545;
                --gray: #6c757d;
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
                height: 100%;
                background: linear-gradient(135deg, rgba(26, 92, 46, 0.4) 0%, rgba(44, 62, 80, 0.4) 100%),
                            url('assets/images/techbuilding.jpg') no-repeat center center fixed;
                background-size: cover;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                padding: 15px;
            }
            
            .login-wrapper {
                width: 100%;
                max-width: 350px;
                margin: 0 auto;
                padding: 10px;
            }
            
            .login-container {
                background: rgba(255, 255, 255, 0.98);
                border-radius: 12px;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2), 0 0 0 1px rgba(255, 255, 255, 0.1);
                overflow: hidden;
                backdrop-filter: blur(10px);
                transform: translateY(0);
                transition: transform 0.3s ease, box-shadow 0.3s ease;
            }
            
            .login-container:hover {
                transform: translateY(-2px);
                box-shadow: 0 12px 35px rgba(0, 0, 0, 0.22);
            }
            
            .login-header {
                background: linear-gradient(135deg, var(--dark), #113f1f);
                color: white;
                padding: 16px 15px;
                text-align: center;
                position: relative;
                overflow: hidden;
            }
            
            .login-header::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 3px;
                background: linear-gradient(90deg, var(--secondary), var(--primary));
            }
            
            .login-logo {
                width: 48px;
                height: 48px;
                margin: 0 auto 6px;
                background: white;
                border-radius: 50%;
                padding: 7px;
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 3px 8px rgba(0, 0, 0, 0.15);
            }
            
            .login-logo img {
                width: 100%;
                height: auto;
                object-fit: contain;
            }
            
            .login-header h4 {
                font-size: 1.05rem;
                font-weight: 600;
                margin-bottom: 2px;
                color: white;
            }
            
            .login-header p {
                font-size: 0.72rem;
                opacity: 0.9;
                margin: 0;
                color: rgba(255, 255, 255, 0.9);
            }
            
            .login-body {
                padding: 18px;
            }
            
            .alert-danger {
                background: linear-gradient(135deg, var(--danger), #c82333);
                color: white;
                border: none;
                border-radius: 7px;
                padding: 7px 10px;
                margin-bottom: 10px;
                font-size: 0.78rem;
                box-shadow: 0 3px 8px rgba(220, 53, 69, 0.15);
                display: flex;
                align-items: flex-start;
                gap: 5px;
            }
            
            .alert-danger i {
                margin-top: 0;
                flex-shrink: 0;
                font-size: 0.85rem;
            }

            .alert-success {
                background: linear-gradient(135deg, #15803d, #16a34a);
                color: white;
                border: none;
                border-radius: 7px;
                padding: 7px 10px;
                margin-bottom: 10px;
                font-size: 0.78rem;
                box-shadow: 0 3px 8px rgba(22, 163, 74, 0.15);
                display: flex;
                align-items: flex-start;
                gap: 5px;
            }
            
            .alert-success i {
                margin-top: 0;
                flex-shrink: 0;
                font-size: 0.85rem;
            }
            
            .form-group {
                margin-bottom: 10px;
                position: relative;
            }
            
            .form-label {
                font-weight: 600;
                color: var(--dark);
                margin-bottom: 3px;
                display: block;
                font-size: 0.78rem;
            }
            
            .form-control {
                width: 100%;
                padding: 8px 9px;
                border: 1.5px solid #e1e5e9;
                border-radius: 5px;
                font-size: 0.85rem;
                transition: all 0.3s ease;
                background: white;
                color: var(--dark);
            }
            
            .form-control:focus {
                outline: none;
                border-color: var(--primary);
                box-shadow: 0 0 0 2px rgba(26, 92, 46, 0.1);
            }
            
            .password-wrapper {
                position: relative;
            }
            
            .toggle-password {
                position: absolute;
                right: 7px;
                top: 50%;
                transform: translateY(-50%);
                background: none;
                border: none;
                color: var(--gray);
                cursor: pointer;
                padding: 4px;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: color 0.3s ease;
                z-index: 5;
                font-size: 0.85rem;
            }
            
            .toggle-password:hover {
                color: var(--primary);
            }
            
            .btn-login {
                width: 100%;
                padding: 9px;
                background: linear-gradient(135deg, #1e293b, #334155);
                color: white;
                border: none;
                border-radius: 5px;
                font-size: 0.85rem;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 5px;
                margin-top: 5px;
            }
            
            .btn-login:hover {
                background: linear-gradient(135deg, #2e7d46, #113f1f);
                transform: translateY(-1px);
                box-shadow: 0 5px 10px rgba(26, 92, 46, 0.25);
            }
            
            .security-note {
                text-align: center;
                margin-top: 12px;
                padding-top: 10px;
                border-top: 1px solid #e1e5e9;
                color: var(--gray);
                font-size: 0.72rem;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 4px;
            }
            
            .security-note i {
                color: var(--secondary);
                font-size: 0.75rem;
            }
            
            /* Responsive Design */
            @media (max-width: 768px) {
                body {
                    padding: 12px;
                }
                
                .login-wrapper {
                    padding: 8px;
                    max-width: 330px;
                }
                
                .login-body {
                    padding: 16px;
                }
            }
            
            @media (max-width: 576px) {
                body {
                    padding: 8px;
                    align-items: flex-start;
                    padding-top: 15px;
                }
                
                .login-wrapper {
                    padding: 6px;
                    max-width: 100%;
                }
                
                .login-container {
                    border-radius: 10px;
                }
                
                .login-header {
                    padding: 14px 10px;
                }
                
                .login-body {
                    padding: 14px;
                }
                
                .login-logo {
                    width: 44px;
                    height: 44px;
                    padding: 6px;
                }
                
                .login-header h4 {
                    font-size: 0.95rem;
                }
            }
        </style>
    </head>
    <body>
        <div class="login-wrapper">
            <div class="login-container">
                <div class="login-header">
                    <div class="login-logo">
                        <img src="assets/images/logo.png" alt="NIS Logo">
                    </div>
                    <h4>NIS Re-Entry Visa Portal</h4>
                    <p>(NISREV)</p>
                </div>
                
                <div class="login-body">
                    <?php if (isset($_SESSION['message'])): ?>
                        <div class="alert alert-<?php echo $_SESSION['message']['type'] === 'success' ? 'success' : 'danger'; ?>">
                            <i class="fa fa-<?php echo $_SESSION['message']['type'] === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i> 
                            <span><?php echo htmlspecialchars($_SESSION['message']['text']); ?></span>
                        </div>
                        <?php unset($_SESSION['message']); ?>
                    <?php endif; ?>

                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger">
                            <i class="fa fa-exclamation-triangle"></i> 
                            <span><?php echo htmlspecialchars($error_message); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['temp_user']) && !empty($_SESSION['temp_user']['mfa_verified']) && !empty($_SESSION['temp_user']['password_reset_required'])): ?>
                        <!-- Step 3: Password Reset Required Form -->
                        <form method="post" id="resetPasswordForm" autocomplete="off">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            
                            <div class="text-center mb-3">
                                <div style="width: 46px; height: 46px; background: #fef3c7; color: #b45309; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; border: 2px solid #fde68a; margin-bottom: 8px;">
                                    <i class="fa fa-key"></i>
                                </div>
                                <p class="fw-bold text-dark mb-1" style="font-size: 0.95rem;">Password Reset Required</p>
                                <p class="text-muted small mb-0" style="font-size: 0.72rem; line-height: 1.3;">
                                    Welcome, <strong><?php echo htmlspecialchars($_SESSION['temp_user']['full_name']); ?></strong>.<br>
                                    Your account is configured with a default password. Please create a new password to continue.
                                </p>
                            </div>

                            <div class="form-group mb-2">
                                <label class="form-label">New Password <span class="text-danger">*</span></label>
                                <div class="password-wrapper">
                                    <input type="password" name="new_password" class="form-control" 
                                           placeholder="Enter new password (min. 6 chars)" required 
                                           minlength="6" autocomplete="new-password" id="firstNewPwd">
                                    <button type="button" class="toggle-password" aria-label="Toggle password visibility">
                                        <i class="fa fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="form-group mb-3">
                                <label class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                                <div class="password-wrapper">
                                    <input type="password" name="confirm_password" class="form-control" 
                                           placeholder="Confirm new password" required 
                                           minlength="6" autocomplete="new-password" id="firstConfirmPwd">
                                    <button type="button" class="toggle-password" aria-label="Toggle password visibility">
                                        <i class="fa fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <button type="submit" name="set_new_password" class="btn-login w-100">
                                <i class="fa fa-check-circle"></i>
                                <span>Save Password & Sign In</span>
                            </button>

                            <div class="text-center mt-3" style="border-top: 1px solid #e1e5e9; padding-top: 10px; margin-top: 15px;">
                                <a href="index?cancel_mfa=1" class="text-decoration-none text-muted small" style="font-size: 0.72rem;">
                                    <i class="fa fa-arrow-left"></i> Cancel and sign out
                                </a>
                            </div>
                        </form>
                    <?php elseif (isset($_SESSION['temp_user'])): ?>
                        <!-- MFA Verification/Setup Form -->
                        <form method="post" id="mfaForm" autocomplete="off">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            
                            <?php if ($_SESSION['temp_user']['first_time']): ?>
                                <!-- First-time User: Show Setup QR Code -->
                                <div class="mfa-instructions text-center mb-3">
                                    <p class="fw-bold text-dark" style="font-size: 0.95rem;">Google Authenticator Setup</p>
                                    <p class="text-muted small" style="font-size: 0.72rem; line-height: 1.2;">Scan this QR Code with your Google Authenticator app to link your account.</p>
                                </div>
                                
                                <?php
                                $secret = $_SESSION['temp_mfa_secret'];
                                $label = 'NISREV:' . $_SESSION['temp_user']['username'];
                                $otpauthUrl = 'otpauth://totp/' . rawurlencode($label) . '?secret=' . $secret . '&issuer=' . rawurlencode('NISREV');
                                ?>
                                
                                <div class="qr-container text-center mb-3">
                                    <div id="qrcode" style="display: inline-block; border: 1.5px solid #e1e5e9; padding: 10px; border-radius: 8px; background: white; box-shadow: 0 4px 12px rgba(0,0,0,0.05); min-width: 150px; min-height: 150px;"></div>
                                    <div class="mt-2">
                                        <span class="text-muted small" style="font-size: 0.68rem; display: block;">Setup Key:</span>
                                        <code class="text-dark small font-monospace fw-bold" style="font-size: 0.78rem; letter-spacing: 1px;"><?php echo htmlspecialchars($secret); ?></code>
                                    </div>
                                </div>
                            <?php else: ?>
                                <!-- Returning User: Prompt for Code -->
                                <div class="mfa-instructions text-center mb-3">
                                    <p class="fw-bold text-dark" style="font-size: 0.95rem;">Two-Factor Authentication</p>
                                    <p class="text-muted small" style="font-size: 0.72rem; line-height: 1.2;">Enter the 6-digit verification code from your Google Authenticator app.</p>
                                </div>
                            <?php endif; ?>

                            <div class="form-group mb-3">
                                <label class="form-label text-center d-block">MFA Verification Code</label>
                                <input type="text" name="mfa_code" class="form-control text-center font-monospace" 
                                       placeholder="000000" required maxlength="6" pattern="\d{6}" 
                                       style="font-size: 1.3rem; letter-spacing: 0.4rem; padding-left: 0.6rem; font-weight: 700;"
                                       autocomplete="one-time-code" autofocus id="mfaCodeField">
                            </div>

                            <button type="submit" name="verify_mfa" class="btn-login w-100">
                                <i class="fa fa-shield"></i>
                                <span>Verify & Sign In</span>
                            </button>

                            <div class="text-center mt-3" style="border-top: 1px solid #e1e5e9; padding-top: 10px; margin-top: 15px;">
                                <a href="index?cancel_mfa=1" class="text-decoration-none text-muted small" style="font-size: 0.72rem;">
                                    <i class="fa fa-arrow-left"></i> Cancel and return
                                </a>
                            </div>
                        </form>
                    <?php else: ?>
                        <!-- Standard Login Form (MFA Step 1) -->
                        <form method="post" id="loginForm" autocomplete="off">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <div class="form-group">
                                <label class="form-label">Service No</label>
                                <input type="text" name="username" class="form-control" 
                                       placeholder="Enter Service Number" required 
                                       autocomplete="username">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Password</label>
                                <div class="password-wrapper">
                                    <input type="password" name="password" class="form-control" 
                                           placeholder="Enter Password" required 
                                           autocomplete="current-password" id="passwordField">
                                    <button type="button" class="toggle-password" id="togglePassword" 
                                            aria-label="Toggle password visibility">
                                        <i class="fa fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <button type="submit" name="login" class="btn-login">
                                <i class="fa fa-sign-in"></i>
                                <span>Sign In</span>
                            </button>
                            
                            <div class="security-note">
                                <i class="fa fa-shield"></i>
                                <span>Designed and Developed by NIS Web Team</span>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const firstNewPwd = document.getElementById('firstNewPwd');
                const mfaCodeField = document.getElementById('mfaCodeField');
                if (firstNewPwd) {
                    firstNewPwd.focus();
                } else if (mfaCodeField) {
                    mfaCodeField.focus();
                } else {
                    document.querySelector('input[name="username"]')?.focus();
                }
                
                // Universal password visibility toggle
                document.querySelectorAll('.password-wrapper').forEach(wrapper => {
                    const input = wrapper.querySelector('input');
                    const btn = wrapper.querySelector('.toggle-password');
                    if (input && btn) {
                        btn.addEventListener('click', function(e) {
                            e.preventDefault();
                            const isPassword = input.type === 'password';
                            input.type = isPassword ? 'text' : 'password';
                            const icon = this.querySelector('i');
                            if (icon) {
                                icon.className = isPassword ? 'fa fa-eye-slash' : 'fa fa-eye';
                            }
                        });
                    }
                });

                // Render QR code client-side if the container is present
                const qrElement = document.getElementById('qrcode');
                if (qrElement) {
                    new QRCode(qrElement, {
                        text: <?php echo isset($otpauthUrl) ? json_encode($otpauthUrl) : "''"; ?>,
                        width: 150,
                        height: 150,
                        colorDark : "#1a5c2e",
                        colorLight : "#ffffff",
                        correctLevel : QRCode.CorrectLevel.M
                    });
                    
                    // Center the generated canvas/image inside the qrElement
                    const qrImg = qrElement.querySelector('img');
                    if (qrImg) qrImg.style.margin = '0 auto';
                    const qrCanvas = qrElement.querySelector('canvas');
                    if (qrCanvas) qrCanvas.style.margin = '0 auto';
                }
            });
        </script>
    </body>
    </html>
    <?php
}

function showDashboard() {
    global $conn;
    
    $numb = 0;
    $total_fresh = 0;
    $total_renewal = 0;
    $stats = [
        'monthly_visas' => 0,
        'pending_visas' => 0,
        'recent_visas' => 0
    ];
    $recent_applications = [];

    try {
        if (!$conn) {
            throw new Exception("Database connection failed");
        }
        
        $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'visa_applications'");
        
        if ($table_check && mysqli_num_rows($table_check) > 0) {
            $sqluse = "SELECT COUNT(*) as total FROM visa_applications";
            $retrieve = mysqli_query($conn, $sqluse);
            if ($retrieve && mysqli_num_rows($retrieve) > 0) {
                $total_result = mysqli_fetch_assoc($retrieve);
                $numb = $total_result['total'] ?? 0;
            }
            
            // Count fresh and renewal applications
            $check_fresh = mysqli_query($conn, "SELECT COUNT(*) as total FROM visa_applications WHERE application_type = 'fresh'");
            if ($check_fresh && $row = mysqli_fetch_assoc($check_fresh)) {
                $total_fresh = (int)$row['total'];
            }
            $check_renewal = mysqli_query($conn, "SELECT COUNT(*) as total FROM visa_applications WHERE application_type = 'renewal'");
            if ($check_renewal && $row = mysqli_fetch_assoc($check_renewal)) {
                $total_renewal = (int)$row['total'];
            }
            
            // Count daily total applications
            $check_daily = mysqli_query($conn, "SELECT COUNT(*) as total FROM visa_applications WHERE DATE(application_date) = CURDATE()");
            $daily_total = 0;
            if ($check_daily && $row = mysqli_fetch_assoc($check_daily)) {
                $daily_total = (int)$row['total'];
            }
            
            $query = "SELECT COUNT(*) as monthly_visas FROM visa_applications 
                     WHERE MONTH(application_date) = MONTH(CURRENT_DATE()) 
                     AND YEAR(application_date) = YEAR(CURRENT_DATE())";
            $result = mysqli_query($conn, $query);
            if ($result && mysqli_num_rows($result) > 0) {
                $monthly_data = mysqli_fetch_assoc($result);
                $stats['monthly_visas'] = $monthly_data['monthly_visas'] ?? 0;
            }
            
            $query = "SELECT COUNT(*) as pending_visas FROM visa_applications WHERE status = 'pending'";
            $result = mysqli_query($conn, $query);
            if ($result && mysqli_num_rows($result) > 0) {
                $pending_data = mysqli_fetch_assoc($result);
                $stats['pending_visas'] = $pending_data['pending_visas'] ?? 0;
            }
            
            $query2 = "SELECT COUNT(*) as recent_visas FROM visa_applications 
                      WHERE DATE(application_date) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            $result2 = mysqli_query($conn, $query2);
            if ($result2 && mysqli_num_rows($result2) > 0) {
                $recent_data = mysqli_fetch_assoc($result2);
                $stats['recent_visas'] = $recent_data['recent_visas'] ?? 0;
            }
            
            $query3 = "SELECT v.visa_number, v.surname, v.given_name, v.passport_number, v.application_date, v.created_by,
                              v.application_type, u.fname, u.lname
                       FROM visa_applications v
                       LEFT JOIN users u ON v.created_by = u.service_no
                       ORDER BY v.application_date DESC 
                       LIMIT 5";
            $result3 = mysqli_query($conn, $query3);
            if ($result3 && mysqli_num_rows($result3) > 0) {
                while ($row = mysqli_fetch_assoc($result3)) {
                    $recent_applications[] = $row;
                }
            }
            
            // Query status distribution (Admin and Supervisor see EVERYTHING)
            $status_data = ['ACTIVE' => 0, 'PENDING' => 0, 'EXPIRED' => 0];
            $status_query = "SELECT status, COUNT(*) as count FROM visa_applications GROUP BY status";
            $status_result = mysqli_query($conn, $status_query);
            if ($status_result && mysqli_num_rows($status_result) > 0) {
                while ($row = mysqli_fetch_assoc($status_result)) {
                    $status_data[strtoupper($row['status'])] = (int)$row['count'];
                }
            }

            // Query daily distribution for the last Month (30 days)
            $daily_labels = [];
            $daily_counts = [];
            $day_query = "SELECT 
                              DATE_FORMAT(application_date, '%d %b') as day, 
                              COUNT(*) as count 
                          FROM visa_applications 
                          WHERE application_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                          GROUP BY DATE(application_date)
                          ORDER BY DATE(application_date) ASC";
            $day_result = mysqli_query($conn, $day_query);
            if ($day_result && mysqli_num_rows($day_result) > 0) {
                while ($row = mysqli_fetch_assoc($day_result)) {
                    $daily_labels[] = strtoupper($row['day']);
                    $daily_counts[] = (int)$row['count'];
                }
            }

            // Query expired visas
            $expired_visas = [];
            $expired_query = "SELECT visa_number, surname, given_name, passport_number, within 
                             FROM visa_applications 
                             WHERE within < CURDATE() 
                             ORDER BY within DESC 
                             LIMIT 10";
            $expired_result = mysqli_query($conn, $expired_query);
            if ($expired_result && mysqli_num_rows($expired_result) > 0) {
                while ($row = mysqli_fetch_assoc($expired_result)) {
                    $expired_visas[] = $row;
                }
            }

            // Query visas expiring soon (next 30 days)
            $expiring_soon_visas = [];
            $expiring_soon_query = "SELECT visa_number, surname, given_name, passport_number, within 
                                   FROM visa_applications 
                                   WHERE within >= CURDATE() AND within <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) 
                                   ORDER BY within ASC 
                                   LIMIT 10";
            $expiring_soon_result = mysqli_query($conn, $expiring_soon_query);
            if ($expiring_soon_result && mysqli_num_rows($expiring_soon_result) > 0) {
                while ($row = mysqli_fetch_assoc($expiring_soon_result)) {
                    $expiring_soon_visas[] = $row;
                }
            }
        }
        
    } catch (Exception $e) {
        error_log("Dashboard error: " . $e->getMessage());
    }
    
    $hasData = $numb > 0 || !empty($recent_applications);
    $userRole = $_SESSION['role'] ?? 'DataEntry';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>NIS Re-Entry Visa System - Dashboard</title>
        <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
        <link href="vendors/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="vendors/font-awesome/css/font-awesome.min.css" rel="stylesheet">
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
            
            /* Centralized Sidebar Styles moved to sidebar.php */
            
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
            
            /* Quick Actions */
            .quick-actions {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 15px;
                margin-bottom: 20px;
            }
            
            .action-btn {
                background: white;
                border: 2px solid #e1e5e9;
                border-radius: 10px;
                padding: 18px 15px;
                text-decoration: none;
                color: var(--dark);
                transition: all 0.3s ease;
                display: block;
                text-align: center;
                box-shadow: 0 3px 8px rgba(0, 0, 0, 0.05);
            }
            
            .action-btn:hover {
                border-color: var(--primary);
                transform: translateY(-3px);
                text-decoration: none;
                color: var(--dark);
                box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            }
            
            .action-btn i {
                font-size: 1.8rem;
                margin-bottom: 12px;
                display: block;
            }
            
            .action-btn strong {
                display: block;
                font-size: 0.95rem;
                margin-bottom: 6px;
                font-weight: 600;
            }
            
            .action-btn small {
                color: var(--gray);
                font-size: 0.8rem;
                display: block;
            }
            
            /* Statistics Grid */
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 15px;
                margin-bottom: 20px;
            }
            @media (max-width: 992px) {
                .stats-grid {
                    grid-template-columns: repeat(2, 1fr);
                }
            }
            @media (max-width: 576px) {
                .stats-grid {
                    grid-template-columns: 1fr;
                }
            }
            
            .stat-card {
                background: white;
                padding: 18px;
                border-radius: 10px;
                box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
                text-align: center;
                border: 1px solid #e1e5e9;
                transition: all 0.3s ease;
            }
            
            .stat-card:hover {
                transform: translateY(-3px);
                box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            }
            
            .stat-icon {
                font-size: 1.8rem;
                margin-bottom: 12px;
                opacity: 0.9;
            }
            
            .stat-number {
                font-size: 1.7rem;
                font-weight: 700;
                margin: 8px 0;
                color: var(--dark);
                line-height: 1;
            }
            
            .stat-card.success .stat-number { color: var(--secondary); }
            .stat-card.warning .stat-number { color: var(--warning); }
            .stat-card.info .stat-number { color: var(--info); }
            
            .stat-label {
                color: var(--gray);
                font-weight: 600;
                text-transform: uppercase;
                font-size: 0.8rem;
                letter-spacing: 0.5px;
            }
            
            /* Recent Applications */
            .recent-card {
                background: white;
                padding: 20px;
                border-radius: 12px;
                box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
                margin-top: 15px;
            }
            
            .card-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 18px;
            }
            
            .card-header h5 {
                font-size: 1.1rem;
                font-weight: 600;
                color: var(--dark);
                margin: 0;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            
            .view-all-btn {
                background: linear-gradient(135deg, #1a5c2e, #113f1f);
                color: white;
                padding: 6px 15px;
                border-radius: 6px;
                text-decoration: none;
                font-size: 0.85rem;
                font-weight: 500;
                transition: all 0.3s ease;
            }
            
            .view-all-btn:hover {
                background: linear-gradient(135deg, #113f1f, #0d3017);
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
            
            .no-data {
                text-align: center;
                padding: 30px 15px;
            }
            
            .no-data i {
                font-size: 2.5rem;
                color: var(--gray);
                margin-bottom: 15px;
                opacity: 0.5;
            }
            
            /* Centralized Sidebar Responsive Styles moved to sidebar.php */
            
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
                
                .recent-card {
                    padding: 15px;
                }
                
                .card-header {
                    flex-direction: column;
                    gap: 12px;
                    align-items: flex-start;
                }
                
                .view-all-btn {
                    width: 100%;
                    text-align: center;
                }
                
                .action-btn {
                    padding: 15px 12px;
                }
                
                .stat-card {
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
                    <h4>Dashboard</h4>
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

            <!-- Dashboard Content -->
            <div class="dashboard-content">
                <!-- Welcome Banner -->
                <div class="welcome-banner">
                    <div class="row align-items-center">
                        <div class="col-lg-8 col-md-7">
                            <div class="welcome-text">
                                <h2>Welcome back, <?php 
                                    echo isset($_SESSION['full_name']) 
                                        ? htmlspecialchars(explode(' ', $_SESSION['full_name'])[0])
                                        : (isset($_SESSION['username']) 
                                            ? htmlspecialchars($_SESSION['username']) 
                                            : 'User');
                                ?>! 👋</h2>
                                <p>NIS Re-Entry Visa Management Portal</p>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-5">
                            <div class="datetime-display">
                                <div id="currentDateTime"></div>
                                <div class="location">Abuja, Nigeria</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <i class="fa fa-globe stat-icon" style="color: var(--primary);"></i>
                        <div class="stat-number"><?php echo number_format($numb); ?></div>
                        <div class="stat-label">Total Re-Entry Visas</div>
                    </div>
                    <div class="stat-card">
                        <i class="fa fa-file-o stat-icon" style="color: #16a34a;"></i>
                        <div class="stat-number"><?php echo number_format($total_fresh); ?></div>
                        <div class="stat-label">Total Fresh Applications</div>
                    </div>
                    <div class="stat-card">
                        <i class="fa fa-refresh stat-icon" style="color: #d97706;"></i>
                        <div class="stat-number"><?php echo number_format($total_renewal); ?></div>
                        <div class="stat-label">Total Renewal Applications</div>
                    </div>
                    <div class="stat-card">
                        <i class="fa fa-calendar-plus-o stat-icon" style="color: #3b82f6;"></i>
                        <div class="stat-number"><?php echo number_format($daily_total); ?></div>
                        <div class="stat-label">Daily Total Applications</div>
                    </div>
                    <div class="stat-card">
                        <i class="fa fa-line-chart stat-icon" style="color: var(--info);"></i>
                        <div class="stat-number"><?php echo number_format($stats['recent_visas']); ?></div>
                        <div class="stat-label">Last 7 Days</div>
                    </div>
                    <div class="stat-card">
                        <i class="fa fa-calendar-check-o stat-icon" style="color: var(--secondary);"></i>
                        <div class="stat-number"><?php echo number_format($stats['monthly_visas']); ?></div>
                        <div class="stat-label">This Month</div>
                    </div>
                </div>

                <!-- System Visualizations & Performance Metrics -->
                <div class="row mb-4">
                    <div class="col-md-6 mb-3">
                        <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid #e1e5e9; height: 100%;">
                            <h5 class="fw-bold mb-3" style="font-size: 1.1rem; color: #5c3c1e;">
                                <i class="fa fa-pie-chart me-2" style="color: #5c3c1e;"></i>Re-entry Visa Status Distribution
                            </h5>
                            <div style="position: relative; height: 250px; width: 100%;">
                                <canvas id="statusChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid #e1e5e9; height: 100%;">
                            <h5 class="fw-bold mb-3" style="font-size: 1.1rem; color: #5c3c1e;">
                                <i class="fa fa-bar-chart me-2" style="color: #5c3c1e;"></i>Re-entry Visa Issuance (last Month)
                            </h5>
                            <div style="position: relative; height: 250px; width: 100%;">
                                <canvas id="issuanceChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mt-5" style="margin-top: 3rem !important;">
                    <!-- Recent Applications -->
                    <div class="col-md-12 mb-4">
                        <div class="recent-card" style="height: 100%; margin-top: 0;">
                            <div class="card-header">
                                <h5 style="color: #5c3c1e;"><i class="fa fa-history" style="color: #5c3c1e;"></i> Recent Applications</h5>
                                <a href="re-entry-visa" class="view-all-btn">View All</a>
                            </div>
                            
                            <?php if (!empty($recent_applications)): ?>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>S/No</th>
                                                <th>Visa Number</th>
                                                <th>Applicant Name</th>
                                                <th>Passport Number</th>
                                                <th>Type of Application</th>
                                                <th>Date Applied</th>
                                                <th>Processed By</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $r_sno = 1;
                                            foreach ($recent_applications as $app): 
                                            ?>
                                                <tr>
                                                    <td><?php echo $r_sno++; ?></td>
                                                    <td><strong><?php echo htmlspecialchars($app['visa_number'] ?? 'N/A'); ?></strong></td>
                                                    <td><?php 
                                                        $name = ($app['surname'] ?? '') . ', ' . ($app['given_name'] ?? '');
                                                        echo htmlspecialchars(trim($name, ', ')); 
                                                    ?></td>
                                                    <td><code><?php echo htmlspecialchars($app['passport_number'] ?? 'N/A'); ?></code></td>
                                                    <td>
                                                        <?php 
                                                            $app_type = strtolower($app['application_type'] ?? 'fresh');
                                                            if ($app_type === 'renewal') {
                                                                echo '<span style="font-size: 0.85rem; font-weight: 600;">RENEWAL</span>';
                                                            } else {
                                                                echo '<span style="font-size: 0.85rem; font-weight: 600;">FRESH</span>';
                                                            }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                            if (!empty($app['application_date'])) {
                                                                echo date('M j, Y g:i A', strtotime($app['application_date']));
                                                            } else {
                                                                echo 'N/A';
                                                            }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                            if (!empty($app['fname'])) {
                                                                echo htmlspecialchars(strtoupper($app['fname'] . ' ' . $app['lname'] . ' (' . $app['created_by'] . ')'));
                                                            } else {
                                                                echo htmlspecialchars(strtoupper($app['created_by'] ?? 'N/A'));
                                                            }
                                                        ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php elseif (!$hasData): ?>
                                <div class="alert alert-info" style="background: #e3f2fd; border: 1px solid #bbdefb; color: #1976d2; padding: 15px; border-radius: 8px; text-align: center; margin: 15px;">
                                    <i class="fa fa-info-circle fa-2x mb-2"></i>
                                    <h5>No Visa Applications Found</h5>
                                    <p class="mb-0">There are no visa applications in the system yet.</p>
                                </div>
                            <?php else: ?>
                                <div class="no-data" style="padding: 30px; text-align: center;">
                                    <i class="fa fa-inbox fa-3x mb-3 text-muted"></i>
                                    <h5>No Recent Applications</h5>
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
                    minute: '2-digit',
                    second: '2-digit'
                };
                const dateTimeStr = now.toLocaleDateString('en-US', options);
                document.getElementById('currentDateTime').textContent = dateTimeStr;
            }
            
            document.addEventListener('DOMContentLoaded', function() {
                updateDateTime();
                setInterval(updateDateTime, 1000);
                
                // Status Chart (Pie/Doughnut)
                const statusCtx = document.getElementById('statusChart').getContext('2d');
                new Chart(statusCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['FRESH APPLICATIONS', 'RENEWAL APPLICATIONS'],
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

                // Issuance Chart (Bar Chart)
                const issuanceCtx = document.getElementById('issuanceChart').getContext('2d');
                new Chart(issuanceCtx, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode($daily_labels); ?>,
                        datasets: [{
                            label: 'Visas Issued',
                            data: <?php echo json_encode($daily_counts); ?>,
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
    <?php
}
?>


