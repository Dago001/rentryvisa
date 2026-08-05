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
$username = $_SESSION['username'] ?? 'User';
$fullName = $_SESSION['full_name'] ?? $username;

// Get current page for active menu
$current_page = basename($_SERVER['PHP_SELF']);

// Check database connection
if (!$conn) {
    die("<div class='alert alert-danger'>Database connection failed</div>");
}

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
if (in_array('within', $columns)) {
    $selectColumns[] = 'within';
} elseif (in_array('validity_date', $columns)) {
    $selectColumns[] = 'validity_date as within';
} elseif (in_array('expiry_date', $columns)) {
    $selectColumns[] = 'expiry_date as within';
} else {
    $selectColumns[] = "'' as within";
}

if (in_array('journey', $columns)) {
    $selectColumns[] = 'journey';
} elseif (in_array('purpose', $columns)) {
    $selectColumns[] = 'purpose as journey';
} elseif (in_array('travel_purpose', $columns)) {
    $selectColumns[] = 'travel_purpose as journey';
} else {
    $selectColumns[] = "'' as journey";
}

$sqluse = "SELECT " . implode(', ', $selectColumns) . " FROM visa_applications";
if ($userRole === 'DataEntry') {
    $sqluse .= " WHERE created_by = '" . mysqli_real_escape_string($conn, $username) . "'";
}
$retrieve = mysqli_query($conn, $sqluse);

if (!$retrieve) {
    // If complex query fails, try basic query
    $sqluse = "SELECT visa_number, passport_number, surname, given_name FROM visa_applications";
    if ($userRole === 'DataEntry') {
        $sqluse .= " WHERE created_by = '" . mysqli_real_escape_string($conn, $username) . "'";
    }
    $retrieve = mysqli_query($conn, $sqluse);
}

$numb = $retrieve ? mysqli_num_rows($retrieve) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>NISREV - View Re-Entry Visas</title>
    <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
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

        /* Welcome Banner */
        .welcome-banner {
            background: linear-gradient(135deg, var(--primary), #2e7d46);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 8px 25px rgba(26, 92, 46, 0.2);
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
        
        .card-body {
            padding: 20px;
        }
        
        .card-title-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .card-title {
            margin-bottom: 0;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark);
        }
        
        /* Search Section */
        .search-section {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .search-box {
            position: relative;
            flex: 1;
            min-width: 200px;
        }
        
        .search-box input {
            padding: 8px 35px 8px 12px;
            border: 1.5px solid #e1e5e9;
            border-radius: 6px;
            font-size: 0.9rem;
            width: 100%;
            transition: all 0.3s;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26, 92, 46, 0.1);
        }
        
        .search-box i {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
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
        
        .no-results {
            text-align: center;
            padding: 30px 15px;
            color: var(--gray);
            font-style: italic;
            display: none;
        }
        
        /* Button Styles */
        .btn-view {
            background: linear-gradient(135deg, var(--primary), #2e7d46);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            min-width: 80px;
        }
        
        .btn-view:hover {
            background: linear-gradient(135deg, #2e7d46, var(--primary));
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(26, 92, 46, 0.3);
            text-decoration: none;
        }
        
        .btn-outline-secondary {
            border: 1.5px solid var(--gray);
            color: var(--gray);
            padding: 8px 15px;
            border-radius: 6px;
            font-size: 0.85rem;
            transition: all 0.3s;
        }
        
        .btn-outline-secondary:hover {
            background: var(--gray);
            color: white;
            border-color: var(--gray);
        }
        

    /* Button Group Styles */
    .btn-group {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
    }
    
    .btn-view {
        background: linear-gradient(135deg, var(--primary), #2e7d46);
        color: white;
        border: none;
        padding: 6px 12px;
        border-radius: 6px;
        text-decoration: none;
        font-size: 0.85rem;
        font-weight: 500;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        min-width: 80px;
    }
    
    .btn-view:hover {
        background: linear-gradient(135deg, #2e7d46, var(--primary));
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(26, 92, 46, 0.3);
        text-decoration: none;
    }
    
    .btn-edit {
        background: linear-gradient(135deg, var(--warning), #e67e22);
        color: white;
        border: none;
        padding: 6px 12px;
        border-radius: 6px;
        text-decoration: none;
        font-size: 0.85rem;
        font-weight: 500;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        min-width: 80px;
    }
    
    .btn-edit:hover {
        background: linear-gradient(135deg, #e67e22, var(--warning));
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(243, 156, 18, 0.3);
        text-decoration: none;
    }
    
    .btn-global-search {
        background: linear-gradient(135deg, var(--info), #2980b9);
        color: white;
        border: none;
        padding: 6px 12px;
        border-radius: 6px;
        text-decoration: none;
        font-size: 0.85rem;
        font-weight: 500;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        min-width: 110px;
    }
    
    .btn-global-search:hover {
        background: linear-gradient(135deg, #2980b9, var(--info));
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
        text-decoration: none;
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
        
        /* Centralized Sidebar Responsive Styles moved to sidebar.php */    
            .welcome-banner {
                text-align: center;
            }
            
            .datetime-display {
                text-align: center;
                margin-top: 15px;
            }
            
            .card-title-section {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .search-section {
                width: 100%;
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
                padding: 15px;
            }
            
            .search-box {
                min-width: 150px;
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
            
            .card-title-section {
                gap: 10px;
            }
            
            .search-section {
                flex-direction: column;
                align-items: stretch;
            }
            
            .btn-outline-secondary {
                width: 100%;
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
        
        /* Print Styles */
        @media print {
            .sidebar,
            .top-nav,
            .welcome-banner,
            .search-section,
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
            
            .card {
                box-shadow: none;
                border: 1px solid #ddd;
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
            
            .btn-view:hover,
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
                <h4>View Re-Entry Visas</h4>
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
            <!-- Page Content -->
            <div class="page-title">
                <h3>Re-Entry Visas</h3>
            </div>
            
            <!-- Start Page Content -->
            <div class="card">
                <div class="card-body">
                    <!-- Updated Search Section -->
                    <div class="card-title-section">
                        <h5 class="card-title">Applications (<?php echo $numb; ?> records)</h5>
                        
                        <!-- Search Bar -->
                        <div class="search-section">
                            <div class="search-box">
                                <input type="text" id="searchInput" class="form-control" placeholder="Search by name, visa number, or passport number...">
                                <i class="fa fa-search"></i>
                            </div>
                            <button id="clearSearch" class="btn btn-outline-secondary">
                                <i class="fa fa-times"></i> Clear
                            </button>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table id="applicationsTable" class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>Visa No.</th>
                                    <th>Full Name</th>
                                    <th>Passport Number</th>
                                    <th>Journey</th>
                                    <th>Within (Validity Period)</th> 
                                    <th>Action</th>
                                </tr>
                            </thead>
                           <!-- In the table body section, update the button cell: -->
<tbody>
    <?php
    if($numb > 0 && $retrieve) {
        while ($ret = mysqli_fetch_assoc($retrieve)) {
            // Escape output for security
            $visa_number = htmlspecialchars($ret['visa_number']);
            $surname = htmlspecialchars($ret['surname']);
            $given_name = htmlspecialchars($ret['given_name']);
            $passport_number = htmlspecialchars($ret['passport_number']);
            $journey = isset($ret['journey']) ? htmlspecialchars($ret['journey']) : 'N/A';
            $within = isset($ret['within']) ? htmlspecialchars($ret['within']) : 'N/A';
            ?>
            <tr>
                <td><?php echo $visa_number; ?></td>
                <td><?php echo strtoupper($surname . ' ' . $given_name); ?></td>
                <td><?php echo $passport_number; ?></td>
                <td><?php echo $journey; ?></td>
                <td><?php echo $within; ?></td>
                <td>
                    <div class="btn-group">
                        <button class="btn btn-view" type="button" 
                            onclick="viewRecord('<?php echo $visa_number; ?>')">
                            <i class="fa fa-eye"></i> View
                        </button>
                        <button class="btn btn-edit" type="button" 
                            onclick="editRecord('<?php echo $visa_number; ?>')">
                            <i class="fa fa-edit"></i> Edit
                        </button>
                        <button class="btn btn-global-search" type="button" onclick="location.href='globalsearch'">
                            <i class="fa fa-search-plus"></i> Global Search
                        </button>
                 </div>
                </td>
            </tr>
            <?php
        }
        mysqli_free_result($retrieve);
    } else {
        echo "<tr><td colspan='6' class='text-center'>No records found</td></tr>";
    }
    
    // Close database connection
    mysqli_close($conn);
    ?>
</tbody>
                        </table>
                        <div id="noResults" class="no-results">
                            No matching records found
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Footer Section -->
        <footer class="main-footer">
            <div>
                All Rights Reserved by Nigeria Immigration Service. Designed and Developed by
                <a href="https://www.immigration.gov.ng" target="_blank">
                    NIS Web Team
                </a>
            </div>
        </footer>
    </div>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // JavaScript functions for record actions
        function viewRecord(id) {
            window.location.href = 'viewrecord?id=' + id;
        }
        
        function editRecord(id) {
            window.location.href = 'editrecord?id=' + id;
        }
        
        function downloadRecord(id) {
            window.location.href = 'download?id=' + id;
        }
        
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
        
        // Search functionality
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const clearSearch = document.getElementById('clearSearch');
            const table = document.getElementById('applicationsTable');
            
            if (!searchInput || !table) return;
            
            const tbody = table.getElementsByTagName('tbody')[0];
            if (!tbody) return;
            
            const tableRows = tbody.getElementsByTagName('tr');
            const noResults = document.getElementById('noResults');
            
            // Search function
            searchInput.addEventListener('keyup', function() {
                const searchTerm = this.value.toLowerCase().trim();
                let hasResults = false;
                
                // Loop through all table rows
                for (let i = 0; i < tableRows.length; i++) {
                    const row = tableRows[i];
                    const cells = row.getElementsByTagName('td');
                    
                    // Safe guard: ignore row if it's a message row (like "No records found")
                    if (cells.length < 3) {
                        continue;
                    }
                    
                    let rowMatches = false;
                    
                    // Check visa number (first cell)
                    if (cells[0] && cells[0].textContent.toLowerCase().includes(searchTerm)) {
                        rowMatches = true;
                    }
                    
                    // Check full name (second cell)
                    if (cells[1] && cells[1].textContent.toLowerCase().includes(searchTerm)) {
                        rowMatches = true;
                    }
                    
                    // Check passport number (third cell)
                    if (cells[2] && cells[2].textContent.toLowerCase().includes(searchTerm)) {
                        rowMatches = true;
                    }
                    
                    // Show or hide the row based on search match
                    if (rowMatches) {
                        row.style.display = '';
                        hasResults = true;
                    } else {
                        row.style.display = 'none';
                    }
                }
                
                // Show/hide no results message
                if (noResults) {
                    if (hasResults || searchTerm === '') {
                        noResults.style.display = 'none';
                        table.style.display = '';
                    } else {
                        noResults.style.display = 'block';
                        table.style.display = 'none';
                    }
                }
            });
            
            // Clear search function
            if (clearSearch) {
                clearSearch.addEventListener('click', function() {
                    searchInput.value = '';
                    searchInput.dispatchEvent(new Event('keyup'));
                    searchInput.focus();
                });
            }
        });
    </script>
</body>
</html>




