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

$userRole = $_SESSION['role'] ?? 'DataEntry';
if ($userRole === 'ViewOnly' || strcasecmp($userRole, 'viewonly') === 0 || strcasecmp($userRole, 'view only') === 0) {
    header("Location: index");
    exit();
}
$username = $_SESSION['username'] ?? 'User';
$fullName = $_SESSION['full_name'] ?? $username;

// Get current page for active menu
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>NISREV - New Re-Entry Visa</title>
    <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
    <link href="vendors/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
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

        /* ===== FORM STYLES ===== */
        .visa-application-container {
            width: 100%;
            max-width: 1000px;
            margin: 0 auto;
            background-color: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.08);
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
            flex: 1;
            padding-right: 15px;
        }

        .section {
            margin-bottom: 25px;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 8px;
        }

        .section-title {
            color: var(--primary);
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 8px;
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

        .form-group label:after {
            content: " *";
            color: var(--danger);
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1.5px solid #e1e5e9;
            border-radius: 6px;
            font-family: inherit;
            font-size: 0.9rem;
            transition: all 0.3s;
            background: white;
            color: var(--dark);
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26, 92, 46, 0.1);
        }

        .btn-submit {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s;
            display: block;
            margin: 30px auto 0;
            min-width: 180px;
            text-align: center;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(26, 92, 46, 0.2);
        }

        .btn-submit i {
            margin-right: 8px;
        }

        /* ===== STATUS MESSAGES ===== */
        .status-message {
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 6px;
            text-align: center;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .status-success {
            background-color: rgba(39, 174, 96, 0.1);
            color: var(--secondary);
            border: 1px solid var(--secondary);
        }

        .status-error {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger);
            border: 1px solid var(--danger);
        }

        .required {
            color: var(--danger);
        }

        .is-invalid {
            border: 1.5px solid var(--danger) !important;
        }

        /* Info text - Top right */
        .form-info {
            padding: 8px 12px;
            background: #e8f5e8;
            border-radius: 6px;
            color: #2d5016;
            font-weight: 500;
            font-size: 0.8rem;
            max-width: 200px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            flex-shrink: 0;
        }

        .form-info i {
            margin-right: 5px;
            color: var(--secondary);
        }

        /* Centralized Sidebar Responsive Styles moved to sidebar.php */    
        @media (max-width: 991px) {
            .form-header {
                flex-direction: column;
                gap: 12px;
            }
            
            .visa-application-title {
                padding-right: 0;
                text-align: center;
                font-size: 1.4rem;
            }
            
            .form-info {
                max-width: 100%;
                text-align: center;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .page-content {
                padding: 15px;
            }
            
            .visa-application-container {
                padding: 20px;
            }
            
            .section {
                padding: 15px;
            }
            
            .user-info {
                flex-wrap: wrap;
            }
            
            .btn-submit {
                width: 100%;
                margin: 25px auto 0;
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
            
            .visa-application-container {
                padding: 15px;
            }
            
            .form-header {
                margin-bottom: 20px;
            }
            
            .visa-application-title {
                font-size: 1.3rem;
            }
            
            .section {
                padding: 12px;
                margin-bottom: 20px;
            }
            
            .section-title {
                font-size: 1rem;
            }
            
            .btn-submit {
                padding: 10px 20px;
                font-size: 0.9rem;
            }
            
            .form-info {
                padding: 6px 10px;
                font-size: 0.75rem;
            }
        }
        
        @media (max-width: 360px) {
            .page-content {
                padding: 12px;
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
            
            .visa-application-container {
                padding: 12px;
            }
            
            .form-group input,
            .form-group select {
                padding: 8px 10px;
                font-size: 0.85rem;
            }
        }
        
        /* Print Styles */
        @media print {
            .sidebar,
            .top-nav,
            .btn-submit,
            .form-info {
                display: none !important;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .page-content {
                padding: 20px 0;
            }
            
            .visa-application-container {
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
        

        
        /* Application Type Tabs */
        .application-tabs {
            display: flex;
            gap: 12px;
            margin-bottom: 25px;
            border-bottom: 2px solid #e1e5e9;
            padding-bottom: 15px;
        }
        
        .app-tab {
            background: #f8f9fa;
            border: 1.5px solid #e1e5e9;
            padding: 10px 24px;
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--dark);
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            outline: none;
        }
        
        .app-tab:hover {
            background: #eaeaea;
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .app-tab.active {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(26, 92, 46, 0.2);
        }

        /* ===== MULTI-STEP PROGRESS WIZARD ===== */
        .form-steps-nav {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 25px;
            padding: 14px 20px;
            background: #f8fafc;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
        }

        .form-step-indicator {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 8px 16px;
            border-radius: 8px;
            transition: all 0.3s ease;
            user-select: none;
        }

        .form-step-indicator .step-num {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #e2e8f0;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            flex-shrink: 0;
        }

        .form-step-indicator .step-label {
            display: flex;
            flex-direction: column;
        }

        .form-step-indicator .step-title {
            font-size: 0.75rem;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-step-indicator .step-subtitle {
            font-size: 0.88rem;
            font-weight: 600;
            color: #475569;
        }

        .form-step-indicator.active {
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }

        .form-step-indicator.active .step-num {
            background: var(--primary);
            color: white;
            box-shadow: 0 2px 6px rgba(26, 92, 46, 0.3);
        }

        .form-step-indicator.active .step-title {
            color: var(--primary);
        }

        .form-step-indicator.active .step-subtitle {
            color: var(--dark);
        }

        .form-step-indicator.completed .step-num {
            background: var(--secondary);
            color: white;
        }

        .step-connector {
            flex: 1;
            max-width: 60px;
            height: 3px;
            background: #e2e8f0;
            margin: 0 10px;
            border-radius: 2px;
            transition: all 0.3s ease;
        }

        .step-connector.completed {
            background: var(--secondary);
        }

        /* Step Navigation Buttons */
        .step-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }

        .btn-step {
            padding: 12px 28px;
            border-radius: 6px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            border: none;
        }

        .btn-next-step {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            margin-left: auto;
        }

        .btn-next-step:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(26, 92, 46, 0.25);
            color: white;
        }

        .btn-prev-step {
            background: #f1f5f9;
            color: #475569;
            border: 1.5px solid #cbd5e1;
        }

        .btn-prev-step:hover {
            background: #e2e8f0;
            color: #1e293b;
            transform: translateY(-2px);
        }

        .form-step {
            animation: fadeInStep 0.35s ease-in-out;
        }

        @keyframes fadeInStep {
            from {
                opacity: 0;
                transform: translateY(8px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 576px) {
            .form-steps-nav {
                flex-direction: column;
                gap: 10px;
                padding: 12px;
            }
            .step-connector {
                display: none;
            }
            .form-step-indicator {
                width: 100%;
                justify-content: flex-start;
            }
            .step-actions {
                flex-direction: column-reverse;
            }
            .btn-step, .btn-submit {
                width: 100%;
                justify-content: center;
            }
        }

        /* Reduced Motion */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
            
            .btn-submit:hover,
            .logout-btn:hover {
                transform: none;
            }
            
            .sidebar-menu a:hover {
                transform: none;
            }
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
                <h4>New Re-Entry Visa</h4>
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
            <div class="visa-application-container">
                <!-- Form Header with Title on Left and NB on Right -->
                <div class="form-header">
                    <h3 class="visa-application-title">Re-Entry Visa Application Form</h3>
                    <div class="form-info">
                        <i class="fa fa-info-circle"></i> All fields marked with * are compulsory.
                    </div>
                </div>
                
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="status-message status-<?php echo $_SESSION['message']['type']; ?>">
                        <?php echo $_SESSION['message']['text']; ?>
                    </div>
                    <?php unset($_SESSION['message']); ?>
                <?php endif; ?>
                
                <form action="process_visa" method="POST" enctype="multipart/form-data" id="visaForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                    <!-- STEP 1: Personal & Passport Information (up to Contract Duration) -->
                    <div id="step-1" class="form-step">
                        <!-- Application Type Tabs -->
                        <div class="application-tabs">
                            <button type="button" class="app-tab active" id="tab-fresh" onclick="switchTab('fresh')">
                                <i class="fa fa-file-o"></i> Fresh Application
                            </button>
                            <button type="button" class="app-tab" id="tab-renewal" onclick="switchTab('renewal')">
                                <i class="fa fa-refresh"></i> Renewal Application
                            </button>
                        </div>
                        <div id="tab-indicator-sign" style="text-align: center; margin: 15px 0 25px 0; padding: 12px; border-radius: 8px; font-weight: bold; background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; transition: all 0.3s ease; box-shadow: 0 2px 4px rgba(0,0,0,0.02); display: flex; align-items: center; justify-content: center; gap: 8px;">
                            <i class="fa fa-check-circle"></i> FRESH APPLICATION
                        </div>
                        <input type="hidden" name="application_type" id="application_type" value="fresh">
                        
                        <!-- Personal Information Section -->
                        <div class="section">
                            <h2 class="section-title">
                                <i class="fa fa-user"></i> Personal & Passport Information
                            </h2>
                            <div class="form-row">
                                <div class="form-group" id="old-visa-group" style="display: none;">
                                    <label for="old_visa_number">Old Re-Entry Visa Number</label>
                                    <input type="text" id="old-visa-number" name="old_visa_number" oninput="validateAlphaNumeric(this)" placeholder="e.g. R 123456">
                                    <div id="old-visa-status" style="margin-top: 5px; font-size: 0.82rem; font-weight: 600;"></div>
                                </div>
                                <div class="form-group">
                                    <label for="passport_number">Passport Number</label>
                                    <input type="text" id="passport-number" name="passport_number" required
                                           oninput="validatePassportNo(this)" placeholder="enter passport number">
                                    <div id="passport-status" style="margin-top: 5px; font-size: 0.85rem; font-weight: 600;"></div>
                                </div>
                                <div class="form-group">
                                    <label for="passport_date_of_issue">Passport Date of Issue</label>
                                    <input type="date" id="passport-date-of-issue" name="passport_date_of_issue" required>
                                </div>
                                <div class="form-group">
                                    <label for="passport_date_of_expiry">Passport Date of Expiry</label>
                                    <input type="date" id="passport-date-of-expiry" name="passport_date_of_expiry" required>
                                </div>
                                <div class="form-group">
                                    <label for="visa_type">Visa Type</label>
                                    <input type="text" id="visa-type" name="visa_type" required oninput="validateText(this)" placeholder="e.g. TOURIST">
                                </div>
                                <div class="form-group">
                                    <label for="surname">Surname</label>
                                    <input type="text" id="surname" name="surname" required
                                           oninput="validateText(this)" placeholder="enter surname" style="text-transform: uppercase;">
                                </div>
                                <div class="form-group">
                                    <label for="given_name">Given Names</label>
                                    <input type="text" id="given-name" name="given_name" required
                                           oninput="validateText(this)" placeholder="enter given names" style="text-transform: uppercase;">
                                </div>
                                <div class="form-group">
                                    <label for="nationality">Nationality</label>
                                    <select id="nationality" name="nationality" required>
                                        <option value="">Select Nationality</option>
                                        <option value="Afghanistan">Afghanistan</option>
                                        <option value="Albania">Albania</option>
                                        <option value="Algeria">Algeria</option>
                                        <option value="Andorra">Andorra</option>
                                        <option value="Angola">Angola</option>
                                        <option value="Antigua and Barbuda">Antigua and Barbuda</option>
                                        <option value="Argentina">Argentina</option>
                                        <option value="Armenia">Armenia</option>
                                        <option value="Australia">Australia</option>
                                        <option value="Austria">Austria</option>
                                        <option value="Azerbaijan">Azerbaijan</option>
                                        <option value="Bahamas">Bahamas</option>
                                        <option value="Bahrain">Bahrain</option>
                                        <option value="Bangladesh">Bangladesh</option>
                                        <option value="Barbados">Barbados</option>
                                        <option value="Belarus">Belarus</option>
                                        <option value="Belgium">Belgium</option>
                                        <option value="Belize">Belize</option>
                                        <option value="Benin">Benin</option>
                                        <option value="Bhutan">Bhutan</option>
                                        <option value="Bolivia">Bolivia</option>
                                        <option value="Bosnia and Herzegovina">Bosnia and Herzegovina</option>
                                        <option value="Botswana">Botswana</option>
                                        <option value="Brazil">Brazil</option>
                                        <option value="Brunei">Brunei</option>
                                        <option value="Bulgaria">Bulgaria</option>
                                        <option value="Burkina Faso">Burkina Faso</option>
                                        <option value="Burundi">Burundi</option>
                                        <option value="Cabo Verde">Cabo Verde</option>
                                        <option value="Cambodia">Cambodia</option>
                                        <option value="Cameroon">Cameroon</option>
                                        <option value="Canada">Canada</option>
                                        <option value="Central African Republic">Central African Republic</option>
                                        <option value="Chad">Chad</option>
                                        <option value="Chile">Chile</option>
                                        <option value="China">China</option>
                                        <option value="Colombia">Colombia</option>
                                        <option value="Comoros">Comoros</option>
                                        <option value="Congo">Congo</option>
                                        <option value="Costa Rica">Costa Rica</option>
                                        <option value="Croatia">Croatia</option>
                                        <option value="Cuba">Cuba</option>
                                        <option value="Cyprus">Cyprus</option>
                                        <option value="Czech Republic">Czech Republic</option>
                                        <option value="Denmark">Denmark</option>
                                        <option value="Djibouti">Djibouti</option>
                                        <option value="Dominica">Dominica</option>
                                        <option value="Dominican Republic">Dominican Republic</option>
                                        <option value="East Timor">East Timor</option>
                                        <option value="Ecuador">Ecuador</option>
                                        <option value="Egypt">Egypt</option>
                                        <option value="El Salvador">El Salvador</option>
                                        <option value="Equatorial Guinea">Equatorial Guinea</option>
                                        <option value="Eritrea">Eritrea</option>
                                        <option value="Estonia">Estonia</option>
                                        <option value="Eswatini">Eswatini</option>
                                        <option value="Ethiopia">Ethiopia</option>
                                        <option value="Fiji">Fiji</option>
                                        <option value="Finland">Finland</option>
                                        <option value="France">France</option>
                                        <option value="Gabon">Gabon</option>
                                        <option value="Gambia">Gambia</option>
                                        <option value="Georgia">Georgia</option>
                                        <option value="Germany">Germany</option>
                                        <option value="Ghana">Ghana</option>
                                        <option value="Greece">Greece</option>
                                        <option value="Grenada">Grenada</option>
                                        <option value="Guatemala">Guatemala</option>
                                        <option value="Guinea">Guinea</option>
                                        <option value="Guinea-Bissau">Guinea-Bissau</option>
                                        <option value="Guyana">Guyana</option>
                                        <option value="Haiti">Haiti</option>
                                        <option value="Honduras">Honduras</option>
                                        <option value="Hungary">Hungary</option>
                                        <option value="Iceland">Iceland</option>
                                        <option value="India">India</option>
                                        <option value="Indonesia">Indonesia</option>
                                        <option value="Iran">Iran</option>
                                        <option value="Iraq">Iraq</option>
                                        <option value="Ireland">Ireland</option>
                                        <option value="Israel">Israel</option>
                                        <option value="Italy">Italy</option>
                                        <option value="Jamaica">Jamaica</option>
                                        <option value="Japan">Japan</option>
                                        <option value="Jordan">Jordan</option>
                                        <option value="Kazakhstan">Kazakhstan</option>
                                        <option value="Kenya">Kenya</option>
                                        <option value="Kiribati">Kiribati</option>
                                        <option value="Korea, North">Korea, North</option>
                                        <option value="Korea, South">Korea, South</option>
                                        <option value="Kosovo">Kosovo</option>
                                        <option value="Kuwait">Kuwait</option>
                                        <option value="Kyrgyzstan">Kyrgyzstan</option>
                                        <option value="Laos">Laos</option>
                                        <option value="Latvia">Latvia</option>
                                        <option value="Lebanon">Lebanon</option>
                                        <option value="Lesotho">Lesotho</option>
                                        <option value="Liberia">Liberia</option>
                                        <option value="Libya">Libya</option>
                                        <option value="Liechtenstein">Liechtenstein</option>
                                        <option value="Lithuania">Lithuania</option>
                                        <option value="Luxembourg">Luxembourg</option>
                                        <option value="Madagascar">Madagascar</option>
                                        <option value="Malawi">Malawi</option>
                                        <option value="Malaysia">Malaysia</option>
                                        <option value="Maldives">Maldives</option>
                                        <option value="Mali">Mali</option>
                                        <option value="Malta">Malta</option>
                                        <option value="Marshall Islands">Marshall Islands</option>
                                        <option value="Mauritania">Mauritania</option>
                                        <option value="Mauritius">Mauritius</option>
                                        <option value="Mexico">Mexico</option>
                                        <option value="Micronesia">Micronesia</option>
                                        <option value="Moldova">Moldova</option>
                                        <option value="Monaco">Monaco</option>
                                        <option value="Mongolia">Mongolia</option>
                                        <option value="Montenegro">Montenegro</option>
                                        <option value="Morocco">Morocco</option>
                                        <option value="Mozambique">Mozambique</option>
                                        <option value="Myanmar">Myanmar</option>
                                        <option value="Namibia">Namibia</option>
                                        <option value="Nauru">Nauru</option>
                                        <option value="Nepal">Nepal</option>
                                        <option value="Netherlands">Netherlands</option>
                                        <option value="New Zealand">New Zealand</option>
                                        <option value="Nicaragua">Nicaragua</option>
                                        <option value="Niger">Niger</option>
                                        <option value="Nigeria">Nigeria</option>
                                        <option value="North Macedonia">North Macedonia</option>
                                        <option value="Norway">Norway</option>
                                        <option value="Oman">Oman</option>
                                        <option value="Pakistan">Pakistan</option>
                                        <option value="Palau">Palau</option>
                                        <option value="Panama">Panama</option>
                                        <option value="Papua New Guinea">Papua New Guinea</option>
                                        <option value="Paraguay">Paraguay</option>
                                        <option value="Peru">Peru</option>
                                        <option value="Philippines">Philippines</option>
                                        <option value="Poland">Poland</option>
                                        <option value="Portugal">Portugal</option>
                                        <option value="Qatar">Qatar</option>
                                        <option value="Romania">Romania</option>
                                        <option value="Russia">Russia</option>
                                        <option value="Rwanda">Rwanda</option>
                                        <option value="Saint Kitts and Nevis">Saint Kitts and Nevis</option>
                                        <option value="Saint Lucia">Saint Lucia</option>
                                        <option value="Saint Vincent and the Grenadines">Saint Vincent and the Grenadines</option>
                                        <option value="Samoa">Samoa</option>
                                        <option value="San Marino">San Marino</option>
                                        <option value="Sao Tome and Principe">Sao Tome and Principe</option>
                                        <option value="Saudi Arabia">Saudi Arabia</option>
                                        <option value="Senegal">Senegal</option>
                                        <option value="Serbia">Serbia</option>
                                        <option value="Seychelles">Seychelles</option>
                                        <option value="Sierra Leone">Sierra Leone</option>
                                        <option value="Singapore">Singapore</option>
                                        <option value="Slovakia">Slovakia</option>
                                        <option value="Slovenia">Slovenia</option>
                                        <option value="Solomon Islands">Solomon Islands</option>
                                        <option value="Somalia">Somalia</option>
                                        <option value="South Africa">South Africa</option>
                                        <option value="South Sudan">South Sudan</option>
                                        <option value="Spain">Spain</option>
                                        <option value="Sri Lanka">Sri Lanka</option>
                                        <option value="Sudan">Sudan</option>
                                        <option value="Suriname">Suriname</option>
                                        <option value="Sweden">Sweden</option>
                                        <option value="Switzerland">Switzerland</option>
                                        <option value="Syria">Syria</option>
                                        <option value="Taiwan">Taiwan</option>
                                        <option value="Tajikistan">Tajikistan</option>
                                        <option value="Tanzania">Tanzania</option>
                                        <option value="Thailand">Thailand</option>
                                        <option value="Togo">Togo</option>
                                        <option value="Tonga">Tonga</option>
                                        <option value="Trinidad and Tobago">Trinidad and Tobago</option>
                                        <option value="Tunisia">Tunisia</option>
                                        <option value="Turkey">Turkey</option>
                                        <option value="Turkmenistan">Turkmenistan</option>
                                        <option value="Tuvalu">Tuvalu</option>
                                        <option value="Uganda">Uganda</option>
                                        <option value="Ukraine">Ukraine</option>
                                        <option value="United Arab Emirates">United Arab Emirates</option>
                                        <option value="United Kingdom">United Kingdom</option>
                                        <option value="United States">United States</option>
                                        <option value="Uruguay">Uruguay</option>
                                        <option value="Uzbekistan">Uzbekistan</option>
                                        <option value="Vanuatu">Vanuatu</option>
                                        <option value="Vatican City">Vatican City</option>
                                        <option value="Venezuela">Venezuela</option>
                                        <option value="Vietnam">Vietnam</option>
                                        <option value="Yemen">Yemen</option>
                                        <option value="Zambia">Zambia</option>
                                        <option value="Zimbabwe">Zimbabwe</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="mfa_letter">Letter of Introduction from MFA</label>
                                    <select id="mfa-letter" name="mfa_letter" required>
                                        <option value="">Select Option</option>
                                        <option value="YES">YES</option>
                                        <option value="NO">NO</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="gender">Gender</label>
                                    <select id="gender" name="gender" required>
                                        <option value="">Select Gender</option>
                                        <option value="MALE">MALE</option>
                                        <option value="FEMALE">FEMALE</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="number_of_dependencies">Number of Dependencies</label>
                                    <input type="number" id="number-of-dependencies" name="number_of_dependencies" required min="0" step="1" value="0">
                                </div>
                                <div class="form-group">
                                    <label for="covering_letter">Covering Letter</label>
                                    <select id="covering-letter" name="covering_letter" required>
                                        <option value="">Select Option</option>
                                        <option value="EMBASSY">Embassy</option>
                                        <option value="HIGH COMMISSION">High Commission</option>
                                        <option value="NIT/ORG">INT/ORG</option>
                                        <option value="GO">GO</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="entry_stamp_date">Entry Stamp Date</label>
                                    <input type="date" id="entry-stamp-date" name="entry_stamp_date" required>
                                </div>
                                <div class="form-group">
                                    <label for="contract_duration">Contract Duration</label>
                                    <input type="text" id="contract-duration" name="contract_duration" required placeholder="e.g. 2 YEARS" style="text-transform: uppercase;">
                                </div>
                            </div>
                        </div>

                        <!-- Step 1 Navigation Button (After Contract Duration) -->
                        <div class="step-actions">
                            <button type="button" class="btn-step btn-next-step" onclick="goToStep(2)">
                                Next Page <i class="fa fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>

                    <!-- STEP 2: Re-Entry Visa Details Section -->
                    <div id="step-2" class="form-step" style="display: none;">
                        <div class="section">
                            <h2 class="section-title">
                                <i class="fa fa-file-text-o"></i> Re-Entry Visa Details
                            </h2>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="visa_number">Re-Entry Visa Number</label>
                                    <input type="text" id="visa-number" name="visa_number" required 
                                           oninput="validateAlphaNumeric(this)" placeholder="e.g., R 1267693">
                                </div>
                                <div class="form-group">
                                    <label for="embassy">Embassy/High Commission</label>
                                    <input type="text" id="embassy" name="embassy" required maxlength="20" oninput="validateEmbassy(this)" placeholder="Enter Embassy Name" style="text-transform: uppercase;">
                                    <small id="embassy-char-count" style="display: block; margin-top: 4px; color: #6c757d; font-size: 0.78rem;">Max 20 characters</small>
                                </div>
                                <div class="form-group">
                                    <label for="place_of_issue">Place of Issue</label>
                                    <input type="text" id="place-of-issue" name="place_of_issue" required
                                           value="ABUJA HQTRS" oninput="validateText(this)" placeholder="enter place of issue">
                                </div>
                                <div class="form-group">
                                    <label for="date_of_issue">Date of Issue</label>
                                    <input type="date" id="date-of-issue" name="date_of_issue" required>
                                </div>
                                <div class="form-group">
                                    <label for="authority">Authority</label>
                                    <input type="text" id="authority" name="authority" required
                                           value="ABJ/HQ/DIP/" placeholder="ABJ/HQ/DIP/...">
                                    <div id="authority-status" style="margin-top: 5px; font-size: 0.82rem;"></div>
                                </div>
                                <div class="form-group">
                                    <label for="fees">Fees/Tax</label>
                                    <select id="fees" name="fees" required>
                                        <option value="Gratis">Gratis</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="journey">Journey Type</label>
                                    <select id="journey" name="journey" required>
                                        <option value="">Select Journey Type</option>
                                        <option value="single">Single</option>
                                        <option value="multiple">Multiple</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="category">Category</label>
                                    <select id="category" name="category" required>
                                        <option value="NONE">NONE</option>
                                        <option value="DIPLOMAT">DIPLOMAT</option>
                                        <option value="INT/ORG">INT/ORG</option>
                                        <option value="GO">GO</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="within">Within (Validity Period)</label>
                                    <input type="date" id="within" name="within" required>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Step 2 Navigation Buttons -->
                        <div class="step-actions">
                            <button type="button" class="btn-step btn-prev-step" onclick="goToStep(1)">
                                <i class="fa fa-arrow-left"></i> Previous Page
                            </button>
                            <button type="submit" class="btn-submit" style="margin: 0;">
                                <i class="fa fa-paper-plane"></i> Submit Application
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Switch between application tabs
        function switchTab(type) {
            document.getElementById('application_type').value = type;
            
            const tabFresh = document.getElementById('tab-fresh');
            const tabRenewal = document.getElementById('tab-renewal');
            const oldVisaGroup = document.getElementById('old-visa-group');
            const oldVisaInput = document.getElementById('old-visa-number');
            const indicator = document.getElementById('tab-indicator-sign');
            
            if (type === 'fresh') {
                tabFresh.classList.add('active');
                tabRenewal.classList.remove('active');
                if (oldVisaGroup) oldVisaGroup.style.display = 'none';
                if (oldVisaInput) {
                    oldVisaInput.removeAttribute('required');
                    oldVisaInput.value = '';
                    const statusEl = document.getElementById('old-visa-status');
                    if (statusEl) statusEl.innerHTML = '';
                    oldVisaInput.classList.remove('is-invalid');
                }
                if (indicator) {
                    indicator.style.background = '#f0fdf4';
                    indicator.style.color = '#15803d';
                    indicator.style.borderColor = '#bbf7d0';
                    indicator.innerHTML = '<i class="fa fa-check-circle"></i> FRESH APPLICATION';
                }
                if (passportExists) {
                    passportValid = false;
                    passportStatus.innerHTML = '<span style="color: #d9534f;"><i class="fa fa-exclamation-circle"></i> This Passport Number already exists in the system! Please use Renewal Application.</span>';
                    passportInput.style.borderColor = '#d9534f';
                    passportInput.style.backgroundColor = '#fdf2f2';
                } else {
                    makeFieldsStatic(false);
                }
            } else {
                tabFresh.classList.remove('active');
                tabRenewal.classList.add('active');
                if (oldVisaGroup) oldVisaGroup.style.display = 'block';
                if (oldVisaInput) {
                    oldVisaInput.setAttribute('required', 'required');
                }
                if (indicator) {
                    indicator.style.background = '#fffbeb';
                    indicator.style.color = '#b45309';
                    indicator.style.borderColor = '#fde68a';
                    indicator.innerHTML = '<i class="fa fa-refresh"></i> RENEWAL APPLICATION';
                }
                if (passportExists) {
                    passportValid = true;
                    passportStatus.innerHTML = `<span style="color: #15803d;"><i class="fa fa-check-circle"></i> Record found & auto-populated!</span>`;
                    passportInput.style.borderColor = '#27ae60';
                    passportInput.style.backgroundColor = '#f2fdf5';
                }
            }
        }

        // Function to validate text fields (alphabetic, spaces, hyphens, apostrophes, periods, and convert to uppercase)
        function validateText(input) {
            // Remove non-alphabetic, non-space, non-hyphen, non-apostrophe, non-period characters
            input.value = input.value.replace(/[^a-zA-Z\s\-\'\.]/g, '');
            
            // Convert to uppercase
            input.value = input.value.toUpperCase();
            
            // Remove invalid class if value exists
            if (input.value.trim()) {
                input.classList.remove('is-invalid');
            }
        }

        // Function to validate Embassy/High Commission (max 20 characters and uppercase)
        function validateEmbassy(input) {
            input.value = input.value.replace(/[^a-zA-Z0-9\s\-\'\.\/]/g, '');
            if (input.value.length > 20) {
                input.value = input.value.substring(0, 20);
            }
            input.value = input.value.toUpperCase();
            if (input.value.trim()) {
                input.classList.remove('is-invalid');
            }
            const countEl = document.getElementById('embassy-char-count');
            if (countEl) {
                countEl.textContent = `${input.value.length}/20 characters`;
                if (input.value.length >= 20) {
                    countEl.style.color = '#d97706';
                    countEl.style.fontWeight = 'bold';
                } else {
                    countEl.style.color = '#6c757d';
                    countEl.style.fontWeight = 'normal';
                }
            }
        }

        // Function to validate and capitalize alphanumeric fields
        function validateAlphaNumeric(input) {
            // Remove any non-alphanumeric characters
            input.value = input.value.replace(/[^a-zA-Z0-9\s]/g, '');
            
            // Convert to uppercase
            input.value = input.value.toUpperCase();
            
            // Remove invalid class if value exists
            if (input.value.trim()) {
                input.classList.remove('is-invalid');
            }
        }

        // Function to validate and capitalize passport number fields (no spaces)
        function validatePassportNo(input) {
            // Remove any non-alphanumeric characters (no spaces)
            input.value = input.value.replace(/[^a-zA-Z0-9]/g, '');
            
            // Convert to uppercase
            input.value = input.value.toUpperCase();
            
            // Remove invalid class if value exists
            if (input.value.trim()) {
                input.classList.remove('is-invalid');
            }
        }

        // Multi-Step Form Wizard Navigation & Validation
        let currentStep = 1;

        function validateStep1() {
            let isValid = true;
            let firstInvalidEl = null;

            // Check required fields in step-1
            const step1Inputs = document.querySelectorAll('#step-1 [required]');
            step1Inputs.forEach(input => {
                if (!input.value || !input.value.trim()) {
                    isValid = false;
                    input.classList.add('is-invalid');
                    if (!firstInvalidEl) firstInvalidEl = input;
                } else {
                    input.classList.remove('is-invalid');
                }
            });

            // Check passport duplicate status
            if (!passportValid) {
                alert("This Passport Number already exists in the system! Please use Renewal Application.");
                const pInput = document.getElementById('passport-number');
                if (pInput) {
                    pInput.classList.add('is-invalid');
                    pInput.focus();
                }
                return false;
            }

            // Validate passport dates if filled
            const passportIssueEl = document.getElementById('passport-date-of-issue');
            const passportExpiryEl = document.getElementById('passport-date-of-expiry');
            const todayStr = new Date().toISOString().split('T')[0];

            if (passportIssueEl && passportIssueEl.value) {
                if (passportIssueEl.value > todayStr) {
                    alert("Passport Date of Issue cannot be in the future!");
                    passportIssueEl.classList.add('is-invalid');
                    passportIssueEl.focus();
                    return false;
                }
            }

            if (passportIssueEl && passportExpiryEl && passportIssueEl.value && passportExpiryEl.value) {
                if (passportExpiryEl.value <= passportIssueEl.value) {
                    alert("Passport Date of Expiry must be after Passport Date of Issue!");
                    passportExpiryEl.classList.add('is-invalid');
                    passportExpiryEl.focus();
                    return false;
                }
            }

            if (!isValid) {
                alert("Please fill in all required fields on Page 1 before proceeding.");
                if (firstInvalidEl) firstInvalidEl.focus();
                return false;
            }

            return true;
        }

        function goToStep(step) {
            const step1 = document.getElementById('step-1');
            const step2 = document.getElementById('step-2');
            const container = document.querySelector('.visa-application-container');

            if (step === 2) {
                if (!validateStep1()) {
                    return;
                }
                if (step1) step1.style.display = 'none';
                if (step2) step2.style.display = 'block';
                currentStep = 2;
            } else if (step === 1) {
                if (step2) step2.style.display = 'none';
                if (step1) step1.style.display = 'block';
                currentStep = 1;
            }

            if (container) {
                container.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        // Function to validate all required fields across both steps
        function validateForm() {
            let isValid = true;
            let firstInvalidInStep1 = null;
            let firstInvalidInStep2 = null;

            // Check step 1 required inputs
            const step1Inputs = document.querySelectorAll('#step-1 [required]');
            step1Inputs.forEach(input => {
                if (!input.value || !input.value.trim()) {
                    isValid = false;
                    input.classList.add('is-invalid');
                    if (!firstInvalidInStep1) firstInvalidInStep1 = input;
                } else {
                    input.classList.remove('is-invalid');
                }
            });

            // Check step 2 required inputs
            const step2Inputs = document.querySelectorAll('#step-2 [required]');
            step2Inputs.forEach(input => {
                if (input.id === 'authority') {
                    if (input.value.trim() === 'ABJ/HQ/DIP/' || input.value.trim().length <= 11) {
                        isValid = false;
                        input.classList.add('is-invalid');
                        if (!firstInvalidInStep2) firstInvalidInStep2 = input;
                    } else {
                        input.classList.remove('is-invalid');
                    }
                } else if (input.id === 'embassy') {
                    if (!input.value.trim() || input.value.trim().length > 20) {
                        isValid = false;
                        input.classList.add('is-invalid');
                        if (!firstInvalidInStep2) firstInvalidInStep2 = input;
                    } else {
                        input.classList.remove('is-invalid');
                    }
                } else if (!input.value || !input.value.trim()) {
                    isValid = false;
                    input.classList.add('is-invalid');
                    if (!firstInvalidInStep2) firstInvalidInStep2 = input;
                } else {
                    input.classList.remove('is-invalid');
                }
            });

            if (!isValid) {
                if (firstInvalidInStep1) {
                    goToStep(1);
                    firstInvalidInStep1.focus();
                } else if (firstInvalidInStep2) {
                    goToStep(2);
                    firstInvalidInStep2.focus();
                }
            }

            return isValid;
        }

        // Passport number duplication checker
        const passportInput = document.getElementById('passport-number');
        const passportStatus = document.getElementById('passport-status');
        const submitBtn = document.querySelector('.btn-submit');
        let passportValid = true;
        let passportExists = false;

        // Function to lock/unlock select fields
        function lockSelect(selectEl) {
            if (!selectEl) return;
            selectEl.style.pointerEvents = 'none';
            selectEl.style.backgroundColor = '#e9ecef';
            selectEl.tabIndex = -1;
            selectEl.dataset.locked = "true";
        }
        
        function unlockSelect(selectEl) {
            if (!selectEl) return;
            selectEl.style.pointerEvents = '';
            selectEl.style.backgroundColor = '';
            selectEl.removeAttribute('tabindex');
            delete selectEl.dataset.locked;
        }

        // Prevent select interaction when locked
        document.addEventListener('keydown', function(e) {
            if (e.target && e.target.dataset && e.target.dataset.locked === "true") {
                e.preventDefault();
            }
        });

        function makeFieldsStatic(isStatic) {
            const nationalitySelect = document.getElementById('nationality');
            const authorityInput = document.getElementById('authority');
            const feesSelect = document.getElementById('fees');
            const genderSelect = document.getElementById('gender');
            
            const surnameInput = document.getElementById('surname');
            const givenNameInput = document.getElementById('given-name');
            const passportIssueInput = document.getElementById('passport-date-of-issue');
            const passportExpiryInput = document.getElementById('passport-date-of-expiry');
            
            const selectsToLock = [nationalitySelect, feesSelect, genderSelect];
            const inputsToLock = [authorityInput, surnameInput, givenNameInput, passportIssueInput, passportExpiryInput];
            
            if (isStatic) {
                selectsToLock.forEach(selectEl => lockSelect(selectEl));
                inputsToLock.forEach(inputEl => {
                    if (inputEl) {
                        inputEl.readOnly = true;
                        inputEl.style.backgroundColor = '#e9ecef';
                        inputEl.style.cursor = 'not-allowed';
                    }
                });
            } else {
                selectsToLock.forEach(selectEl => unlockSelect(selectEl));
                inputsToLock.forEach(inputEl => {
                    if (inputEl) {
                        inputEl.readOnly = false;
                        inputEl.style.backgroundColor = '';
                        inputEl.style.cursor = '';
                    }
                });
            }
        }

        function setSelectValueCaseInsensitive(selectEl, value) {
            if (!selectEl) return;
            const cleanVal = (value || '').trim().toUpperCase();
            for (let i = 0; i < selectEl.options.length; i++) {
                if (selectEl.options[i].value.toUpperCase() === cleanVal) {
                    selectEl.selectedIndex = i;
                    return;
                }
            }
            selectEl.value = value;
        }

        function populateForm(d) {
            // Populate inputs
            if (document.getElementById('surname')) document.getElementById('surname').value = (d.surname || '').toUpperCase();
            if (document.getElementById('given-name')) document.getElementById('given-name').value = (d.given_name || '').toUpperCase();
            if (document.getElementById('passport-number')) document.getElementById('passport-number').value = d.passport_number || '';
            if (document.getElementById('passport-date-of-issue')) document.getElementById('passport-date-of-issue').value = d.passport_date_of_issue || '';
            if (document.getElementById('passport-date-of-expiry')) document.getElementById('passport-date-of-expiry').value = d.passport_date_of_expiry || '';
            if (document.getElementById('visa-type')) document.getElementById('visa-type').value = (d.visa_type || '').toUpperCase();
            
            setSelectValueCaseInsensitive(document.getElementById('nationality'), d.nationality);
            setSelectValueCaseInsensitive(document.getElementById('mfa-letter'), d.mfa_letter);
            setSelectValueCaseInsensitive(document.getElementById('gender'), d.gender);
            
            if (document.getElementById('number-of-dependencies')) document.getElementById('number-of-dependencies').value = d.number_of_dependencies || 0;
            
            setSelectValueCaseInsensitive(document.getElementById('covering-letter'), d.covering_letter);
            if (document.getElementById('entry-stamp-date')) document.getElementById('entry-stamp-date').value = d.entry_stamp_date || '';
            if (document.getElementById('contract-duration')) document.getElementById('contract-duration').value = (d.contract_duration || '').toUpperCase();
            
            const embassyEl = document.getElementById('embassy');
            if (embassyEl) {
                embassyEl.value = (d.embassy || '').toUpperCase();
                validateEmbassy(embassyEl);
            }
            if (document.getElementById('place-of-issue')) document.getElementById('place-of-issue').value = (d.place_of_issue || '').toUpperCase();
            if (document.getElementById('date-of-issue')) document.getElementById('date-of-issue').value = d.date_of_issue || '';
            if (document.getElementById('authority')) document.getElementById('authority').value = d.authority || '';
            
            const feesSelect = document.getElementById('fees');
            if (feesSelect) {
                const feesVal = d.fees || 'Gratis';
                let exists = false;
                for (let i = 0; i < feesSelect.options.length; i++) {
                    if (feesSelect.options[i].value.toUpperCase() === feesVal.toUpperCase()) {
                        exists = true;
                        feesSelect.selectedIndex = i;
                        break;
                    }
                }
                if (!exists) {
                    const opt = document.createElement('option');
                    opt.value = feesVal;
                    opt.textContent = feesVal;
                    feesSelect.appendChild(opt);
                    feesSelect.value = feesVal;
                }
            }

            if (document.getElementById('old-visa-number') && d.visa_number) {
                document.getElementById('old-visa-number').value = d.visa_number;
            }

            makeFieldsStatic(true);
        }

        passportInput.addEventListener('change', function() {
            const passport = this.value.trim().toUpperCase().replace(/[^A-Z0-9]/g, '');
            this.value = passport;
            if (!passport) {
                passportStatus.innerHTML = '';
                passportExists = false;
                makeFieldsStatic(false);
                return;
            }

            passportStatus.innerHTML = '<span style="color: #666;"><i class="fa fa-spinner fa-spin"></i> Checking...</span>';
            
            fetch('check_passport', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'passport_number=' + encodeURIComponent(passport)
            })
                .then(response => response.json())
                .then(data => {
                    const currentTab = document.getElementById('application_type').value;
                    passportExists = data.exists;
                    
                    if (data.exists) {
                        if (currentTab === 'fresh') {
                            alert("This Passport Number already exists in the system!");
                            switchTab('renewal');
                        }
                        
                        if (data.data) {
                            populateForm(data.data);
                        }
                        
                        passportStatus.innerHTML = `<span style="color: #15803d;"><i class="fa fa-check-circle"></i> Record found & auto-populated!</span>`;
                        passportInput.style.borderColor = '#27ae60';
                        passportInput.style.backgroundColor = '#f2fdf5';
                        passportValid = true;
                    } else {
                        if (currentTab === 'renewal') {
                            passportStatus.innerHTML = '<span style="color: #dc2626;"><i class="fa fa-times-circle"></i> No record found for this passport number</span>';
                            passportInput.style.borderColor = '#d9534f';
                            passportInput.style.backgroundColor = '#fdf2f2';
                        } else {
                            passportStatus.innerHTML = '<span style="color: #27ae60;"><i class="fa fa-check-circle"></i> Passport Number is valid</span>';
                            passportInput.style.borderColor = '#27ae60';
                            passportInput.style.backgroundColor = '#f2fdf5';
                        }
                        passportValid = true;
                        makeFieldsStatic(false);
                    }
                    toggleSubmitButton();
                })
                .catch(error => {
                    console.error('Error checking passport:', error);
                    passportStatus.innerHTML = '';
                    passportValid = true;
                    passportExists = false;
                    makeFieldsStatic(false);
                    toggleSubmitButton();
                });
        });

        passportInput.addEventListener('input', function() {
            passportStatus.innerHTML = '';
            passportInput.style.borderColor = '';
            passportInput.style.backgroundColor = '';
            passportValid = true;
            passportExists = false;
            makeFieldsStatic(false);
            toggleSubmitButton();
        });

        // Authority number validation
        const authorityInputGlobal = document.getElementById('authority');
        const authorityStatus = document.getElementById('authority-status');
        let authorityValid = true;
        let authorityExists = false;

        authorityInputGlobal.addEventListener('blur', function() {
            const auth = this.value.trim();
            
            // Check if it is empty or just the prefix
            if (!auth || auth === 'ABJ/HQ/DIP/' || auth.length <= 11) {
                authorityStatus.innerHTML = '';
                authorityExists = false;
                return;
            }

            authorityStatus.innerHTML = '<span style="color: #27ae60;"><i class="fa fa-check-circle"></i> Authority number format valid</span>';
            authorityInputGlobal.style.borderColor = '#27ae60';
            authorityInputGlobal.style.backgroundColor = '#f2fdf5';
            authorityValid = true;
            toggleSubmitButton();
        });

        authorityInputGlobal.addEventListener('input', function() {
            authorityStatus.innerHTML = '';
            authorityInputGlobal.style.borderColor = '';
            authorityInputGlobal.style.backgroundColor = '';
            authorityValid = true;
            authorityExists = false;
            toggleSubmitButton();
        });

        function toggleSubmitButton() {
            if (!passportValid || !authorityValid) {
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.5';
                submitBtn.style.cursor = 'not-allowed';
            } else {
                submitBtn.disabled = false;
                submitBtn.style.opacity = '1';
                submitBtn.style.cursor = 'pointer';
            }
        }

        // Form submission handler
        let isVisaChecked = false;
        document.getElementById('visaForm').addEventListener('submit', function(e) {
            if (!validateForm() || !passportValid || !authorityValid) {
                e.preventDefault();
                let errMsg = 'Please fill in all required fields correctly.';
                if (!passportValid) {
                    errMsg = 'This Passport Number already exists in the system!';
                }
                alert(errMsg);
                return;
            }
            
            // Validate Passport dates
            const passportIssueDate = document.getElementById('passport-date-of-issue').value;
            const passportExpiryDate = document.getElementById('passport-date-of-expiry').value;
            const todayStr = new Date().toISOString().split('T')[0];

            if (passportIssueDate > todayStr) {
                alert("Passport Date of Issue cannot be in the future!");
                e.preventDefault();
                return;
            }
            if (passportExpiryDate <= passportIssueDate) {
                alert("Passport Date of Expiry must be after Passport Date of Issue!");
                e.preventDefault();
                return;
            }
            
            if (isVisaChecked) {
                return;
            }
            
            e.preventDefault();
            
            const visaInput = document.getElementById('visa-number');
            const visaNo = visaInput.value.trim();
            if (!visaNo) return;
            
            const submitBtnEl = document.querySelector('.btn-submit');
            if (submitBtnEl) submitBtnEl.disabled = true;
            
            fetch('check_visa', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'visa_number=' + encodeURIComponent(visaNo)
            })
                .then(response => response.json())
                .then(data => {
                    if (data.exists) {
                        alert("Visa number already exists in the system! Please edit the Visa Number.");
                        visaInput.focus();
                        visaInput.style.borderColor = '#d9534f';
                        visaInput.style.backgroundColor = '#fdf2f2';
                        if (submitBtnEl) submitBtnEl.disabled = false;
                    } else {
                        isVisaChecked = true;
                        document.getElementById('visaForm').submit();
                    }
                })
                .catch(error => {
                    console.error('Error checking visa:', error);
                    if (submitBtnEl) submitBtnEl.disabled = false;
                });
        });

        // Real-time validation for all fields
        document.addEventListener('DOMContentLoaded', function() {
            const allInputs = document.querySelectorAll('#visaForm input, #visaForm select');
            
            allInputs.forEach(input => {
                input.addEventListener('blur', function() {
                    if (this.id === 'authority') {
                        if (this.value.trim() === 'ABJ/HQ/DIP/' || this.value.trim().length <= 11) {
                            this.classList.add('is-invalid');
                        } else {
                            this.classList.remove('is-invalid');
                        }
                    } else if (this.hasAttribute('required') && !this.value.trim()) {
                        this.classList.add('is-invalid');
                    } else {
                        this.classList.remove('is-invalid');
                    }
                });
                
                input.addEventListener('input', function() {
                    if (this.id === 'authority') {
                        if (this.value.trim() !== 'ABJ/HQ/DIP/' && this.value.trim().length > 11) {
                            this.classList.remove('is-invalid');
                        }
                    } else if (this.value.trim()) {
                        this.classList.remove('is-invalid');
                    }
                });
            });

            // Specific validation and prefix preservation for Authority
            const authorityInput = document.getElementById('authority');
            if (authorityInput) {
                const prefix = "ABJ/HQ/DIP/";
                
                // Helper function to enforce prefix and strip invalid characters
                function formatAuthority(el) {
                    let val = el.value;
                    
                    // Enforce prefix
                    if (!val.startsWith(prefix)) {
                        el.value = prefix;
                        return;
                    }
                    
                    // Allow only alphabet, numbers, and / in suffix
                    let suffix = val.substring(prefix.length).toUpperCase();
                    suffix = suffix.replace(/[^A-Z0-9\/]/g, '');
                    
                    el.value = prefix + suffix;
                }
                
                authorityInput.addEventListener('focus', function() {
                    if (!this.value) {
                        this.value = prefix;
                    }
                });
                
                authorityInput.addEventListener('input', function() {
                    formatAuthority(this);
                });
                
                // Initialize if empty
                if (!authorityInput.value) {
                    authorityInput.value = prefix;
                }
            }
        });

        // Date validation
        document.getElementById('date-of-issue').addEventListener('change', function() {
            const issueDate = new Date(this.value);
            const today = new Date();
            
            if (issueDate > today) {
                alert('Date of issue cannot be in the future!');
                this.value = '';
            }
        });

        document.getElementById('within').addEventListener('change', function() {
            const issueDate = new Date(document.getElementById('date-of-issue').value);
            const validUntil = new Date(this.value);
            
            if (issueDate && validUntil <= issueDate) {
                alert('Valid until date must be after date of issue!');
                this.value = '';
            }
        });

        // Autocomplete old visa details
        let oldVisaDebounceTimer;
        const oldVisaInput = document.getElementById('old-visa-number');
        
        if (oldVisaInput) {
            oldVisaInput.addEventListener('input', function() {
                clearTimeout(oldVisaDebounceTimer);
                const visaNo = this.value.trim();
                
                if (visaNo.length < 3) {
                    const statusEl = document.getElementById('old-visa-status');
                    if (statusEl) statusEl.innerHTML = '';
                    return;
                }
                
                oldVisaDebounceTimer = setTimeout(() => {
                    fetchVisaDetails(visaNo);
                }, 500);
            });
        }

        function fetchVisaDetails(visaNo) {
            const statusEl = document.getElementById('old-visa-status');
            if (statusEl) {
                statusEl.style.color = '#d97706';
                statusEl.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Searching database...';
            }
            
            fetch('get_visa_details', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'visa_number=' + encodeURIComponent(visaNo)
            })
                .then(response => response.json())
                .then(result => {
                    if (result.success && result.data) {
                        populateForm(result.data);
                        
                        passportValid = true;
                        passportExists = true;

                        if (statusEl) {
                            statusEl.style.color = '#15803d';
                            statusEl.innerHTML = '<i class="fa fa-check-circle"></i> Record found & auto-populated!';
                        }
                    } else {
                        if (statusEl) {
                            statusEl.style.color = '#dc2626';
                            statusEl.innerHTML = '<i class="fa fa-times-circle"></i> No record found for this visa number';
                        }
                        makeFieldsStatic(false);
                    }
                })
                .catch(error => {
                    console.error('Fetch visa details error:', error);
                    if (statusEl) {
                        statusEl.style.color = '#dc2626';
                        statusEl.innerHTML = '<i class="fa fa-exclamation-triangle"></i> Error fetching record details';
                    }
                    makeFieldsStatic(false);
                });
        }

        // Sidebar functionality - MATCHING INDEX.PHP
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
            const dateTimeElement = document.getElementById('currentDateTime');
            if (dateTimeElement) {
                dateTimeElement.textContent = dateTimeStr;
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            updateDateTime();
            setInterval(updateDateTime, 1000);
        });
    </script>
    <?php 
    if (file_exists('footer.php')) {
        include('footer.php');
    }
    ?>
</body>
</html>
