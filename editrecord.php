<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once('security.php');

require_once('db/db.php');

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: index");
    exit();
}

// Check database connection
if (!$conn) {
    die("<div class='alert alert-danger'>Database connection failed</div>");
}

// Initialize variables
$username = $_SESSION['username'] ?? '';
$userRole = $_SESSION['role'] ?? 'DataEntry';
if ($userRole === 'ViewOnly' || strcasecmp($userRole, 'viewonly') === 0 || strcasecmp($userRole, 'view only') === 0) {
    header("Location: index");
    exit();
}
$record = null;
$success_message = '';
$error_message = '';
$visa_number = '';

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

// Add any other columns that might exist
$additionalColumns = ['sex', 'place_of_birth', 'date_of_birth', 'nationality', 'permanent_address', 
                     'travelling_on', 'port_of_entry', 'date_of_arrival', 'valid_from', 'valid_until',
                     'place_of_issue', 'date_of_issue', 'remarks', 'created_by', 'embassy', 'authority', 'fees', 'category',
                     'application_type', 'number_of_dependencies', 'visa_type', 'gender', 'mfa_letter', 'covering_letter', 'entry_stamp', 'entry_stamp_date', 'contract_duration', 'old_visa_number',
                     'passport_date_of_issue', 'passport_date_of_expiry'];

foreach ($additionalColumns as $column) {
    if (in_array($column, $columns)) {
        $selectColumns[] = $column;
    }
}

// Check if ID parameter is provided
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $visa_number = mysqli_real_escape_string($conn, $_GET['id']);
    
    // Fetch the record to edit
    $sql = "SELECT " . implode(', ', $selectColumns) . " FROM visa_applications WHERE visa_number = '$visa_number'";
    $result = mysqli_query($conn, $sql);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $record = mysqli_fetch_assoc($result);
        
        // Authorization check for DataEntry role
        $isDataEntry = ($userRole === 'DataEntry' || strcasecmp($userRole, 'dataentry') === 0 || strcasecmp($userRole, 'data entry') === 0);
        if ($isDataEntry && isset($record['created_by']) && strcasecmp($record['created_by'], $username) !== 0) {
            header("Location: re-entry-visa?error=Access Denied");
            exit();
        }
        
        mysqli_free_result($result);
    } else {
        $error_message = "Record not found with Visa Number: " . htmlspecialchars($visa_number);
    }
} else {
    $error_message = "No record ID provided";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_record'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "Security validation failed. Please refresh the page.";
    } else {
        $current_visa_number = mysqli_real_escape_string($conn, $_POST['visa_number']);
        
        // Authorization check for DataEntry role before updating
        $auth_check_sql = "SELECT created_by FROM visa_applications WHERE visa_number = '$current_visa_number'";
        $auth_check_result = mysqli_query($conn, $auth_check_sql);
        if ($auth_check_result && mysqli_num_rows($auth_check_result) > 0) {
            $auth_row = mysqli_fetch_assoc($auth_check_result);
            mysqli_free_result($auth_check_result);
            if ($userRole === 'DataEntry' && (!isset($_POST['ref']) || $_POST['ref'] !== 'globalsearch') && isset($auth_row['created_by']) && strcasecmp($auth_row['created_by'], $username) !== 0) {
                header("Location: re-entry-visa?error=Access Denied");
                exit();
            }
        }

        // Sanitize input
        $passport_number = strtoupper(mysqli_real_escape_string($conn, $_POST['passport_number']));
        $surname = strtoupper(mysqli_real_escape_string($conn, $_POST['surname']));
        $given_name = strtoupper(mysqli_real_escape_string($conn, $_POST['given_name']));
        
        // Validate Embassy length
        $embassy_check = $_POST['embassy'] ?? '';
        if (strlen($embassy_check) > 20) {
            $error_message = "Embassy/High Commission name must not exceed 20 characters!";
        }

        // Validate passport dates
        $p_issue = $_POST['passport_date_of_issue'] ?? '';
        $p_expiry = $_POST['passport_date_of_expiry'] ?? '';
        $current_date = date('Y-m-d');
        if (empty($error_message) && !empty($p_issue) && $p_issue > $current_date) {
            $error_message = "Passport Date of Issue cannot be in the future!";
        }
        if (empty($error_message) && !empty($p_issue) && !empty($p_expiry) && $p_expiry <= $p_issue) {
            $error_message = "Passport Date of Expiry must be after Passport Date of Issue!";
        }

        // Get the current visa number from hidden field
        $current_visa_number = strtoupper(mysqli_real_escape_string($conn, $_POST['visa_number']));
        
        // Check if visa number is being changed
        $new_visa_number = strtoupper(mysqli_real_escape_string($conn, $_POST['new_visa_number']));
        
        // Use new visa number if provided, otherwise keep current
        $final_visa_number = !empty($new_visa_number) ? $new_visa_number : $current_visa_number;
        
        if (empty($error_message)) {
            // Check if new visa number already exists (if changed)
            if (!empty($new_visa_number) && $new_visa_number != $current_visa_number) {
                $clean_new = preg_replace('/\s+/', '', $new_visa_number);
                $clean_curr = preg_replace('/\s+/', '', $current_visa_number);
                $check_sql = "SELECT visa_number FROM visa_applications WHERE (REPLACE(visa_number, ' ', '') = '$clean_new' OR visa_number = '$new_visa_number') AND REPLACE(visa_number, ' ', '') != '$clean_curr'";
                $check_result = mysqli_query($conn, $check_sql);
                
                if ($check_result && mysqli_num_rows($check_result) > 0) {
                    $error_message = "Re-Entry Visa Number '$new_visa_number' already exists in the system. Re-Entry Visa Numbers cannot be repeated.";
                } else {
                    // Build dynamic update query based on available columns
                    $updateFields = [
                        "visa_number = '$final_visa_number'",
                        "passport_number = '$passport_number'",
                        "surname = '$surname'",
                        "given_name = '$given_name'"
                    ];
                    
                    // Add optional fields if they exist in the database
                    $optionalFields = [
                        'sex' => strtoupper(mysqli_real_escape_string($conn, $_POST['sex'] ?? '')),
                        'place_of_birth' => strtoupper(mysqli_real_escape_string($conn, $_POST['place_of_birth'] ?? '')),
                        'date_of_birth' => mysqli_real_escape_string($conn, $_POST['date_of_birth'] ?? ''),
                        'nationality' => strtoupper(mysqli_real_escape_string($conn, $_POST['nationality'] ?? '')),
                        'permanent_address' => strtoupper(mysqli_real_escape_string($conn, $_POST['permanent_address'] ?? '')),
                        'travelling_on' => strtoupper(mysqli_real_escape_string($conn, $_POST['travelling_on'] ?? '')),
                        'port_of_entry' => strtoupper(mysqli_real_escape_string($conn, $_POST['port_of_entry'] ?? '')),
                        'date_of_arrival' => mysqli_real_escape_string($conn, $_POST['date_of_arrival'] ?? ''),
                        'valid_from' => mysqli_real_escape_string($conn, $_POST['valid_from'] ?? ''),
                        'valid_until' => mysqli_real_escape_string($conn, $_POST['valid_until'] ?? ''),
                        'place_of_issue' => strtoupper(mysqli_real_escape_string($conn, $_POST['place_of_issue'] ?? '')),
                        'date_of_issue' => mysqli_real_escape_string($conn, $_POST['date_of_issue'] ?? ''),
                        'remarks' => strtoupper(mysqli_real_escape_string($conn, $_POST['remarks'] ?? '')),
                        'purpose' => strtoupper(mysqli_real_escape_string($conn, $_POST['purpose'] ?? '')),
                        'journey' => strtoupper(mysqli_real_escape_string($conn, $_POST['journey'] ?? '')),
                        'within' => mysqli_real_escape_string($conn, $_POST['within'] ?? ''),
                        'embassy' => strtoupper(mysqli_real_escape_string($conn, $_POST['embassy'] ?? '')),
                        'authority' => strtoupper(mysqli_real_escape_string($conn, $_POST['authority'] ?? '')),
                        'fees' => strtoupper(mysqli_real_escape_string($conn, $_POST['fees'] ?? '')),
                        'category' => strtoupper(mysqli_real_escape_string($conn, $_POST['category'] ?? 'NONE')),
                        'application_type' => strtolower(mysqli_real_escape_string($conn, $_POST['application_type'] ?? 'fresh')),
                        'number_of_dependencies' => intval($_POST['number_of_dependencies'] ?? 0),
                        'visa_type' => strtoupper(mysqli_real_escape_string($conn, $_POST['visa_type'] ?? '')),
                        'gender' => strtoupper(mysqli_real_escape_string($conn, $_POST['gender'] ?? '')),
                        'mfa_letter' => strtoupper(mysqli_real_escape_string($conn, $_POST['mfa_letter'] ?? '')),
                        'covering_letter' => strtoupper(mysqli_real_escape_string($conn, $_POST['covering_letter'] ?? '')),
                        'entry_stamp' => strtoupper(mysqli_real_escape_string($conn, $_POST['entry_stamp'] ?? '')),
                        'entry_stamp_date' => mysqli_real_escape_string($conn, $_POST['entry_stamp_date'] ?? ''),
                        'contract_duration' => strtoupper(mysqli_real_escape_string($conn, $_POST['contract_duration'] ?? '')),
                        'old_visa_number' => strtoupper(mysqli_real_escape_string($conn, $_POST['old_visa_number'] ?? '')),
                        'passport_date_of_issue' => mysqli_real_escape_string($conn, $_POST['passport_date_of_issue'] ?? ''),
                        'passport_date_of_expiry' => mysqli_real_escape_string($conn, $_POST['passport_date_of_expiry'] ?? '')
                    ];
                
                foreach ($optionalFields as $field => $value) {
                    if (in_array($field, $columns)) {
                        $updateFields[] = "$field = '$value'";
                    }
                }
                
                // Add updated_at if it exists
                if (in_array('updated_at', $columns)) {
                    $updateFields[] = "updated_at = NOW()";
                }
                
                $update_sql = "UPDATE visa_applications SET " . implode(', ', $updateFields) . " WHERE visa_number = '$current_visa_number'";
                
                if (mysqli_query($conn, $update_sql)) {
                    $success_message = "Record updated successfully!";
                    
                    // Log the edit action to activity_log
                    $log_desc = "Edited visa application record for Visa No: " . $current_visa_number;
                    if (isset($_POST['ref']) && $_POST['ref'] === 'globalsearch') {
                        $log_desc = "Edited visa application record for Visa No: " . $current_visa_number . " via Global Search";
                    }
                    logActivity($conn, "EDIT", $log_desc);
                    
                    $visa_number = $final_visa_number;
                    
                    // Fetch updated record
                    $sql = "SELECT " . implode(', ', $selectColumns) . " FROM visa_applications WHERE visa_number = '$final_visa_number'";
                    $result = mysqli_query($conn, $sql);
                    if ($result) {
                        $record = mysqli_fetch_assoc($result);
                        mysqli_free_result($result);
                    }
                } else {
                    $error_message = "Error updating record: " . mysqli_error($conn);
                }
            }
        } else {
            // Update without changing visa number
            $updateFields = [
                "passport_number = '$passport_number'",
                "surname = '$surname'",
                "given_name = '$given_name'"
            ];
            
            // Add optional fields if they exist in the database
            $optionalFields = [
                'sex' => strtoupper(mysqli_real_escape_string($conn, $_POST['sex'] ?? '')),
                'place_of_birth' => strtoupper(mysqli_real_escape_string($conn, $_POST['place_of_birth'] ?? '')),
                'date_of_birth' => mysqli_real_escape_string($conn, $_POST['date_of_birth'] ?? ''),
                'nationality' => strtoupper(mysqli_real_escape_string($conn, $_POST['nationality'] ?? '')),
                'permanent_address' => strtoupper(mysqli_real_escape_string($conn, $_POST['permanent_address'] ?? '')),
                'travelling_on' => strtoupper(mysqli_real_escape_string($conn, $_POST['travelling_on'] ?? '')),
                'port_of_entry' => strtoupper(mysqli_real_escape_string($conn, $_POST['port_of_entry'] ?? '')),
                'date_of_arrival' => mysqli_real_escape_string($conn, $_POST['date_of_arrival'] ?? ''),
                'valid_from' => mysqli_real_escape_string($conn, $_POST['valid_from'] ?? ''),
                'valid_until' => mysqli_real_escape_string($conn, $_POST['valid_until'] ?? ''),
                'place_of_issue' => strtoupper(mysqli_real_escape_string($conn, $_POST['place_of_issue'] ?? '')),
                'date_of_issue' => mysqli_real_escape_string($conn, $_POST['date_of_issue'] ?? ''),
                'remarks' => strtoupper(mysqli_real_escape_string($conn, $_POST['remarks'] ?? '')),
                'purpose' => strtoupper(mysqli_real_escape_string($conn, $_POST['purpose'] ?? '')),
                'journey' => strtoupper(mysqli_real_escape_string($conn, $_POST['journey'] ?? '')),
                'within' => mysqli_real_escape_string($conn, $_POST['within'] ?? ''),
                'embassy' => strtoupper(mysqli_real_escape_string($conn, $_POST['embassy'] ?? '')),
                'authority' => strtoupper(mysqli_real_escape_string($conn, $_POST['authority'] ?? '')),
                'fees' => strtoupper(mysqli_real_escape_string($conn, $_POST['fees'] ?? '')),
                'category' => strtoupper(mysqli_real_escape_string($conn, $_POST['category'] ?? 'NONE')),
                'application_type' => strtolower(mysqli_real_escape_string($conn, $_POST['application_type'] ?? 'fresh')),
                'number_of_dependencies' => intval($_POST['number_of_dependencies'] ?? 0),
                'visa_type' => strtoupper(mysqli_real_escape_string($conn, $_POST['visa_type'] ?? '')),
                'gender' => strtoupper(mysqli_real_escape_string($conn, $_POST['gender'] ?? '')),
                'mfa_letter' => strtoupper(mysqli_real_escape_string($conn, $_POST['mfa_letter'] ?? '')),
                'covering_letter' => strtoupper(mysqli_real_escape_string($conn, $_POST['covering_letter'] ?? '')),
                'entry_stamp' => strtoupper(mysqli_real_escape_string($conn, $_POST['entry_stamp'] ?? '')),
                'entry_stamp_date' => mysqli_real_escape_string($conn, $_POST['entry_stamp_date'] ?? ''),
                'contract_duration' => strtoupper(mysqli_real_escape_string($conn, $_POST['contract_duration'] ?? '')),
                'old_visa_number' => strtoupper(mysqli_real_escape_string($conn, $_POST['old_visa_number'] ?? '')),
                'passport_date_of_issue' => mysqli_real_escape_string($conn, $_POST['passport_date_of_issue'] ?? ''),
                'passport_date_of_expiry' => mysqli_real_escape_string($conn, $_POST['passport_date_of_expiry'] ?? '')
            ];
            
            foreach ($optionalFields as $field => $value) {
                if (in_array($field, $columns)) {
                    $updateFields[] = "$field = '$value'";
                }
            }
            
            // Add updated_at if it exists
            if (in_array('updated_at', $columns)) {
                $updateFields[] = "updated_at = NOW()";
            }
            
            $update_sql = "UPDATE visa_applications SET " . implode(', ', $updateFields) . " WHERE visa_number = '$current_visa_number'";
            
            if (mysqli_query($conn, $update_sql)) {
                $success_message = "Record updated successfully!";
                
                // Log the edit action to activity_log
                $log_desc = "Edited visa application record for Visa No: " . $current_visa_number;
                if (isset($_POST['ref']) && $_POST['ref'] === 'globalsearch') {
                    $log_desc = "Edited visa application record for Visa No: " . $current_visa_number . " via Global Search";
                }
                logActivity($conn, "EDIT", $log_desc);
                
                // Fetch updated record
                $sql = "SELECT " . implode(', ', $selectColumns) . " FROM visa_applications WHERE visa_number = '$current_visa_number'";
                $result = mysqli_query($conn, $sql);
                if ($result) {
                    $record = mysqli_fetch_assoc($result);
                    mysqli_free_result($result);
                }
            } else {
                $error_message = "Error updating record: " . mysqli_error($conn);
            }
        }
    }
    }
}



// Helper function to safely get array values with fallback
function getFieldValue($record, $field, $default = '') {
    return isset($record[$field]) && $record[$field] !== null ? $record[$field] : $default;
}

// List of all countries for dropdown
$countries = [
    "Afghanistan", "Albania", "Algeria", "Andorra", "Angola", "Antigua and Barbuda", "Argentina", "Armenia", "Australia", "Austria", 
    "Azerbaijan", "Bahamas", "Bahrain", "Bangladesh", "Barbados", "Belarus", "Belgium", "Belize", "Benin", "Bhutan", 
    "Bolivia", "Bosnia and Herzegovina", "Botswana", "Brazil", "Brunei", "Bulgaria", "Burkina Faso", "Burundi", "Cabo Verde", "Cambodia", 
    "Cameroon", "Canada", "Central African Republic", "Chad", "Chile", "China", "Colombia", "Comoros", "Congo", "Costa Rica", 
    "Croatia", "Cuba", "Cyprus", "Czech Republic", "Denmark", "Djibouti", "Dominica", "Dominican Republic", "Ecuador", "Egypt", 
    "El Salvador", "Equatorial Guinea", "Eritrea", "Estonia", "Eswatini", "Ethiopia", "Fiji", "Finland", "France", "Gabon", 
    "Gambia", "Georgia", "Germany", "Ghana", "Greece", "Grenada", "Guatemala", "Guinea", "Guinea-Bissau", "Guyana", 
    "Haiti", "Honduras", "Hungary", "Iceland", "India", "Indonesia", "Iran", "Iraq", "Ireland", "Israel", 
    "Italy", "Jamaica", "Japan", "Jordan", "Kazakhstan", "Kenya", "Kiribati", "Kuwait", "Kyrgyzstan", "Laos", 
    "Latvia", "Lebanon", "Lesotho", "Liberia", "Libya", "Liechtenstein", "Lithuania", "Luxembourg", "Madagascar", "Malawi", 
    "Malaysia", "Maldives", "Mali", "Malta", "Marshall Islands", "Mauritania", "Mauritius", "Mexico", "Micronesia", "Moldova", 
    "Monaco", "Mongolia", "Montenegro", "Morocco", "Mozambique", "Myanmar", "Namibia", "Nauru", "Nepal", "Netherlands", 
    "New Zealand", "Nicaragua", "Niger", "Nigeria", "North Korea", "North Macedonia", "Norway", "Oman", "Pakistan", "Palau", 
    "Palestine", "Panama", "Papua New Guinea", "Paraguay", "Peru", "Philippines", "Poland", "Portugal", "Qatar", "Romania", 
    "Russia", "Rwanda", "Saint Kitts and Nevis", "Saint Lucia", "Saint Vincent", "Samoa", "San Marino", "Sao Tome and Principe", "Saudi Arabia", "Senegal", 
    "Serbia", "Seychelles", "Sierra Leone", "Singapore", "Slovakia", "Slovenia", "Solomon Islands", "Somalia", "South Africa", "South Korea", 
    "South Sudan", "Spain", "Sri Lanka", "Sudan", "Suriname", "Sweden", "Switzerland", "Syria", "Tajikistan", "Tanzania", 
    "Thailand", "Timor-Leste", "Togo", "Tonga", "Trinidad and Barbuda", "Tunisia", "Turkey", "Turkmenistan", "Tuvalu", "Uganda", 
    "Ukraine", "United Arab Emirates", "United Kingdom", "United States", "Uruguay", "Uzbekistan", "Vanuatu", "Vatican City", "Venezuela", "Vietnam", 
    "Yemen", "Zambia", "Zimbabwe"
];

// Check which fields actually exist in the database
$sex_exists = in_array('sex', $columns);
$place_of_birth_exists = in_array('place_of_birth', $columns);
$date_of_birth_exists = in_array('date_of_birth', $columns);
$nationality_exists = in_array('nationality', $columns);
$permanent_address_exists = in_array('permanent_address', $columns);
$travelling_on_exists = in_array('travelling_on', $columns);
$port_of_entry_exists = in_array('port_of_entry', $columns);
$date_of_arrival_exists = in_array('date_of_arrival', $columns);
$valid_from_exists = in_array('valid_from', $columns);
$valid_until_exists = in_array('valid_until', $columns);
$place_of_issue_exists = in_array('place_of_issue', $columns);
$date_of_issue_exists = in_array('date_of_issue', $columns);
$remarks_exists = in_array('remarks', $columns);
$purpose_exists = in_array('purpose', $columns);
$journey_exists = in_array('journey', $columns);
$within_exists = in_array('within', $columns);
$embassy_exists = in_array('embassy', $columns);
$authority_exists = in_array('authority', $columns);
$fees_exists = in_array('fees', $columns);
$category_exists = in_array('category', $columns);
$application_type_exists = in_array('application_type', $columns);
$number_of_dependencies_exists = in_array('number_of_dependencies', $columns);
$visa_type_exists = in_array('visa_type', $columns);
$gender_exists = in_array('gender', $columns);
$mfa_letter_exists = in_array('mfa_letter', $columns);
$covering_letter_exists = in_array('covering_letter', $columns);
$entry_stamp_exists = in_array('entry_stamp', $columns);
$entry_stamp_date_exists = in_array('entry_stamp_date', $columns);
$contract_duration_exists = in_array('contract_duration', $columns);
$old_visa_number_exists = in_array('old_visa_number', $columns);
$passport_date_of_issue_exists = in_array('passport_date_of_issue', $columns);
$passport_date_of_expiry_exists = in_array('passport_date_of_expiry', $columns);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>NISREV - Edit Re-Entry Visa</title>
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
        
        /* Alert Styles */
        .alert {
            border-radius: 8px;
            border: none;
            padding: 12px 16px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
        }
        
        /* Form Styles */
        .form-section {
            margin-bottom: 30px;
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
        
        .section-title {
            color: var(--primary);
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e1e5e9;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--dark);
            font-size: 0.85rem;
            margin-bottom: 6px;
        }
        
        .form-control, .form-select {
            border: 1.5px solid #e1e5e9;
            border-radius: 6px;
            padding: 10px 12px;
            font-size: 0.9rem;
            transition: all 0.3s;
            width: 100%;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26, 92, 46, 0.1);
            outline: none;
        }
        
        .form-control[readonly] {
            background-color: #f8f9fa;
            opacity: 0.8;
        }
        
        /* Button Styles */
        .btn-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e1e5e9;
        }
        
        .btn-save {
            background: linear-gradient(135deg, var(--secondary), #2ecc71);
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-width: 100px;
        }
        
        .btn-save:hover {
            background: linear-gradient(135deg, #2ecc71, var(--secondary));
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(39, 174, 96, 0.3);
            text-decoration: none;
        }
        
        .btn-cancel {
            background: linear-gradient(135deg, var(--gray), #95a5a6);
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-width: 100px;
        }
        
        .btn-cancel:hover {
            background: linear-gradient(135deg, #95a5a6, var(--gray));
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3);
            text-decoration: none;
        }
        
        .btn-back {
            background: linear-gradient(135deg, #1e293b, #334155);
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-width: 100px;
        }
        
        .btn-back:hover {
            background: linear-gradient(135deg, #2e7d46, var(--primary));
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(26, 92, 46, 0.3);
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
        
        /* Responsive Styles */
        /* Centralized Sidebar Responsive Styles moved to sidebar.php */
            

            
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
            

            
            .card {
                margin-bottom: 15px;
            }
            
            .card-body {
                padding: 15px;
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
            

            
            .btn-group {
                flex-direction: column;
            }
            
            .btn-group .btn {
                width: 100%;
                justify-content: center;
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
        }

        /* Visa Application Container styling to match viewrecord.php */
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
        
        .section {
            margin-bottom: 25px;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        
        /* Status pill badge design matching viewrecord.php */
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

        /* Aligned Action Buttons */
        .btn-action {
            font-weight: 600;
            padding: 8px 18px;
            border-radius: 6px;
            transition: all 0.2s;
            font-size: 0.88rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: none;
            cursor: pointer;
        }
        
        .btn-gold {
            background-color: var(--secondary) !important;
            border-color: var(--secondary) !important;
            color: #000 !important;
        }
        
        .btn-gold:hover {
            background-color: #c5a02e !important;
            border-color: #c5a02e !important;
            color: #000 !important;
        }
        
        .btn-green {
            background-color: var(--primary) !important;
            border-color: var(--primary) !important;
            color: white !important;
        }
        
        .btn-green:hover {
            background-color: var(--primary-hover) !important;
            border-color: var(--primary-hover) !important;
            color: white !important;
        }
        
        .btn-back {
            background: white;
            border: 1.5px solid var(--primary) !important;
            color: var(--primary) !important;
            font-weight: 600;
            padding: 8px 18px;
            border-radius: 6px;
            transition: all 0.2s;
            font-size: 0.88rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }
        
        .btn-back:hover {
            background: var(--primary) !important;
            border-color: var(--primary) !important;
            color: white !important;
        }
        
        /* Print Styles */
        @media print {
            .sidebar,
            .top-nav,
            .btn-group,
            .main-footer {
                display: none !important;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .page-content {
                padding: 20px 0;
            }
            
            body {
                background: white !important;
                font-size: 12pt;
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
                <h4>Edit Re-Entry Visa</h4>
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


            
            
            <!-- Alerts -->
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fa fa-check-circle"></i> <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message && !$success_message): ?>
                <div class="alert alert-danger">
                    <i class="fa fa-exclamation-circle"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($record): 
                $status = getFieldValue($record, 'status') ?: 'ACTIVE';
            ?>
            <!-- Edit Form -->
            <div class="visa-application-container">
                <form method="POST" action="" id="editForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="visa_number" value="<?php echo htmlspecialchars(getFieldValue($record, 'visa_number')); ?>">
                    <input type="hidden" name="ref" value="<?php echo htmlspecialchars($_GET['ref'] ?? ($_POST['ref'] ?? '')); ?>">
                    
                    <div class="form-header flex-column flex-md-row gap-3">
                        <div>
                            <h2 class="visa-application-title">
                                <i class="fa fa-edit me-2"></i>Edit Visa Application Details
                            </h2>
                        </div>
                        <div class="text-md-end d-flex flex-column align-items-md-end gap-2">
                            <span class="status-pill status-<?php echo strtolower($status); ?>">
                                <span class="status-dot"></span>
                                <?php echo htmlspecialchars($status); ?>
                            </span>
                            <span class="small text-muted font-monospace fw-bold">
                                Visa No: <?php echo htmlspecialchars(getFieldValue($record, 'visa_number')); ?>
                            </span>
                        </div>
                    </div>

                    <!-- Section 1: Personal Profile -->
                    <div class="section">
                        <h4 class="section-title"><i class="fa fa-user me-2"></i>Personal Profile</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Surname *</label>
                                <input type="text" 
                                       class="form-control" 
                                       name="surname" 
                                       value="<?php echo htmlspecialchars(getFieldValue($record, 'surname')); ?>" 
                                       required style="text-transform: uppercase;">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Given Name *</label>
                                <input type="text" 
                                       class="form-control" 
                                       name="given_name" 
                                       value="<?php echo htmlspecialchars(getFieldValue($record, 'given_name')); ?>" 
                                       required style="text-transform: uppercase;">
                            </div>

                            <?php if ($nationality_exists): ?>
                            <div class="form-group">
                                <label class="form-label">Nationality</label>
                                <select class="form-select" name="nationality" id="nationality">
                                    <option value="">Select Nationality</option>
                                    <?php 
                                    $current_nationality = getFieldValue($record, 'nationality');
                                    foreach ($countries as $country) {
                                        $selected = (strcasecmp($current_nationality, $country) === 0) ? 'selected' : '';
                                        echo "<option value=\"$country\" $selected>$country</option>";
                                    }
                                    if (!empty($current_nationality) && !in_array($current_nationality, $countries)) {
                                        echo "<option value=\"" . htmlspecialchars($current_nationality) . "\" selected>" . htmlspecialchars($current_nationality) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <?php endif; ?>

                            <?php if ($sex_exists): ?>
                            <div class="form-group">
                                <label class="form-label">Sex</label>
                                <select class="form-select" name="sex">
                                    <option value="">Select</option>
                                    <option value="Male" <?php echo (getFieldValue($record, 'sex') == 'Male') ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo (getFieldValue($record, 'sex') == 'Female') ? 'selected' : ''; ?>>Female</option>
                                </select>
                            </div>
                            <?php endif; ?>

                            <?php if ($gender_exists): ?>
                            <div class="form-group">
                                <label class="form-label">Gender</label>
                                <select class="form-select" name="gender">
                                    <option value="">Select Gender</option>
                                    <option value="MALE" <?php echo (strtoupper(getFieldValue($record, 'gender')) == 'MALE') ? 'selected' : ''; ?>>MALE</option>
                                    <option value="FEMALE" <?php echo (strtoupper(getFieldValue($record, 'gender')) == 'FEMALE') ? 'selected' : ''; ?>>FEMALE</option>
                                </select>
                            </div>
                            <?php endif; ?>

                            <?php if ($date_of_birth_exists): ?>
                            <div class="form-group">
                                <label class="form-label">Date of Birth</label>
                                <input type="date" 
                                       class="form-control" 
                                       name="date_of_birth" 
                                       value="<?php echo htmlspecialchars(getFieldValue($record, 'date_of_birth')); ?>">
                            </div>
                            <?php endif; ?>

                            <?php if ($place_of_birth_exists): ?>
                            <div class="form-group">
                                <label class="form-label">Place of Birth</label>
                                <input type="text" 
                                       class="form-control" 
                                       name="place_of_birth" 
                                       value="<?php echo htmlspecialchars(getFieldValue($record, 'place_of_birth')); ?>">
                            </div>
                            <?php endif; ?>

                            <?php if ($permanent_address_exists): ?>
                            <div class="form-group">
                                <label class="form-label">Permanent Address</label>
                                <input type="text" 
                                       class="form-control" 
                                       name="permanent_address" 
                                       value="<?php echo htmlspecialchars(getFieldValue($record, 'permanent_address')); ?>">
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Section 2: Passport & Travel Information -->
                    <div class="section">
                        <h4 class="section-title"><i class="fa fa-plane-departure me-2"></i>Passport & Travel Information</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Passport Number *</label>
                                <input type="text" 
                                       class="form-control" 
                                       name="passport_number" 
                                       value="<?php echo htmlspecialchars(getFieldValue($record, 'passport_number')); ?>" 
                                       readonly 
                                       style="background-color: #e9ecef; cursor: not-allowed;" 
                                       required>
                            </div>

                            <?php if ($passport_date_of_issue_exists): ?>
                            <div class="form-group">
                                <label class="form-label">Passport Date of Issue *</label>
                                <input type="date" 
                                       class="form-control" 
                                       name="passport_date_of_issue" 
                                       value="<?php echo htmlspecialchars(getFieldValue($record, 'passport_date_of_issue')); ?>" 
                                       required>
                            </div>
                            <?php endif; ?>

                            <?php if ($passport_date_of_expiry_exists): ?>
                            <div class="form-group">
                                <label class="form-label">Passport Date of Expiry *</label>
                                <input type="date" 
                                       class="form-control" 
                                       name="passport_date_of_expiry" 
                                       value="<?php echo htmlspecialchars(getFieldValue($record, 'passport_date_of_expiry')); ?>" 
                                       required>
                            </div>
                            <?php endif; ?>

                            <?php if ($place_of_issue_exists): ?>
                            <div class="form-group">
                                <label class="form-label">Place of Issue</label>
                                <input type="text" 
                                       class="form-control" 
                                       name="place_of_issue" 
                                       value="<?php echo htmlspecialchars(getFieldValue($record, 'place_of_issue')); ?>">
                            </div>
                            <?php endif; ?>

                            <?php if ($date_of_issue_exists): ?>
                            <div class="form-group">
                                <label class="form-label">Date of Issue</label>
                                <input type="date" 
                                       class="form-control" 
                                       name="date_of_issue" 
                                       value="<?php echo htmlspecialchars(getFieldValue($record, 'date_of_issue')); ?>">
                            </div>
                            <?php endif; ?>

                            <?php if ($within_exists): ?>
                            <div class="form-group">
                                <label class="form-label">Validity Period (Within)</label>
                                <input type="date" 
                                       class="form-control" 
                                       name="within" 
                                       value="<?php 
                                           $wVal = getFieldValue($record, 'within'); 
                                           echo !empty($wVal) ? date('Y-m-d', strtotime($wVal)) : ''; 
                                       ?>">
                            </div>
                            <?php endif; ?>

                            <?php if ($visa_type_exists): ?>
                            <div class="form-group">
                                <label class="form-label">Visa Type</label>
                                <input type="text" class="form-control" name="visa_type" value="<?php echo htmlspecialchars(getFieldValue($record, 'visa_type')); ?>" placeholder="e.g. TOURIST">
                            </div>
                            <?php endif; ?>

                            <?php if ($journey_exists): ?>
                            <div class="form-group">
                                <label class="form-label">Journey</label>
                                <select class="form-select" name="journey">
                                    <option value="">Select Journey</option>
                                    <option value="SINGLE" <?php echo (strtoupper(getFieldValue($record, 'journey')) === 'SINGLE') ? 'selected' : ''; ?>>SINGLE</option>
                                    <option value="MULTIPLE" <?php echo (strtoupper(getFieldValue($record, 'journey')) === 'MULTIPLE') ? 'selected' : ''; ?>>MULTIPLE</option>
                                    <?php 
                                    $curr_journey = getFieldValue($record, 'journey');
                                    if (!empty($curr_journey) && !in_array(strtoupper($curr_journey), ['SINGLE', 'MULTIPLE'])) {
                                        echo "<option value=\"" . htmlspecialchars($curr_journey) . "\" selected>" . htmlspecialchars($curr_journey) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <?php endif; ?>

                            <?php if ($purpose_exists): ?>
                            <div class="form-group">
                                <label class="form-label">Purpose of Travel</label>
                                <input type="text" 
                                       class="form-control" 
                                       name="purpose" 
                                       value="<?php echo htmlspecialchars(getFieldValue($record, 'purpose')); ?>">
                            </div>
                            <?php endif; ?>

                            <?php if ($travelling_on_exists): ?>
                            <div class="form-group">
                                <label class="form-label">Travelling On</label>
                                <input type="text" 
                                       class="form-control" 
                                       name="travelling_on" 
                                       value="<?php echo htmlspecialchars(getFieldValue($record, 'travelling_on')); ?>">
                            </div>
                            <?php endif; ?>

                            <?php if ($port_of_entry_exists): ?>
                            <div class="form-group">
                                <label class="form-label">Port of Entry</label>
                                <input type="text" 
                                       class="form-control" 
                                       name="port_of_entry" 
                                       value="<?php echo htmlspecialchars(getFieldValue($record, 'port_of_entry')); ?>">
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Section 3: Visa Details & Authority -->
                    <div class="section">
                        <h4 class="section-title"><i class="fa fa-globe me-2"></i>Visa Details & Authority</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Re-Entry Visa Number *</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="newVisaNumberInput" 
                                       name="new_visa_number" 
                                       value="<?php echo htmlspecialchars(getFieldValue($record, 'visa_number')); ?>" 
                                       required>
                                <small class="text-muted" id="visaNumberHelp" style="margin-top: 5px; display: block;">
                                    Changing visa number will create a new record. The old number will become available.
                                </small>
                            </div>

                            <?php if ($old_visa_number_exists): ?>
                            <div class="form-group" id="edit-old-visa-group">
                                <label class="form-label">Old Re-Entry Visa Number</label>
                                <input type="text" class="form-control" id="edit-old-visa-number" name="old_visa_number" value="<?php echo htmlspecialchars(getFieldValue($record, 'old_visa_number')); ?>" placeholder="e.g. R 123456">
                            </div>
                            <?php endif; ?>

                            <?php if ($authority_exists): ?>
                            <div class="form-group">
                                <label class="form-label">Authority</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="authority"
                                       name="authority" 
                                       value="<?php echo htmlspecialchars(getFieldValue($record, 'authority')); ?>">
                                <div id="authority-status" style="margin-top: 5px; font-size: 0.82rem;"></div>
                            </div>
                            <?php endif; ?>

                            <?php if ($category_exists): ?>
                            <div class="form-group">
                                <label class="form-label">Category</label>
                                <select class="form-select" name="category">
                                     <option value="NONE" <?php echo (strtoupper(getFieldValue($record, 'category')) === 'NONE') ? 'selected' : ''; ?>>NONE</option>
                                     <option value="DIPLOMAT" <?php echo (strtoupper(getFieldValue($record, 'category')) === 'DIPLOMAT') ? 'selected' : ''; ?>>DIPLOMAT</option>
                                     <option value="INT/ORG" <?php echo (strtoupper(getFieldValue($record, 'category')) === 'INT/ORG') ? 'selected' : ''; ?>>INT/ORG</option>
                                     <option value="GO" <?php echo (strtoupper(getFieldValue($record, 'category')) === 'GO') ? 'selected' : ''; ?>>GO</option>
                                 </select>
                            </div>
                            <?php endif; ?>

                            <?php if ($fees_exists): ?>
                            <div class="form-group">
                                <label class="form-label">Fees/Tax</label>
                                <select class="form-select" name="fees">
                                    <option value="GRATIS" <?php echo (strtoupper(getFieldValue($record, 'fees')) === 'GRATIS') ? 'selected' : ''; ?>>GRATIS</option>
                                    <?php 
                                    $curr_fees = getFieldValue($record, 'fees');
                                    if (!empty($curr_fees) && strtoupper($curr_fees) !== 'GRATIS') {
                                        echo "<option value=\"" . htmlspecialchars($curr_fees) . "\" selected>" . htmlspecialchars($curr_fees) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <?php endif; ?>

                            <?php if ($embassy_exists): ?>
                            <div class="form-group">
                                <label class="form-label">Embassy/High Commission</label>
                                <input type="text" class="form-control" name="embassy" value="<?php echo htmlspecialchars(getFieldValue($record, 'embassy')); ?>" placeholder="Enter Embassy Name" style="text-transform: uppercase;" maxlength="20">
                            </div>
                            <?php endif; ?>

                            <?php if ($mfa_letter_exists): ?>
                            <div class="form-group">
                                <label class="form-label">Letter of Introduction from MFA</label>
                                <select class="form-select" name="mfa_letter">
                                    <option value="">Select Option</option>
                                    <option value="YES" <?php echo (getFieldValue($record, 'mfa_letter') == 'YES') ? 'selected' : ''; ?>>YES</option>
                                    <option value="NO" <?php echo (getFieldValue($record, 'mfa_letter') == 'NO') ? 'selected' : ''; ?>>NO</option>
                                </select>
                            </div>
                            <?php endif; ?>

                            <?php if ($covering_letter_exists): ?>
                            <div class="form-group">
                                <label class="form-label">Covering Letter</label>
                                <select class="form-select" name="covering_letter">
                                    <option value="">Select Option</option>
                                    <option value="EMBASSY" <?php echo (getFieldValue($record, 'covering_letter') == 'EMBASSY') ? 'selected' : ''; ?>>Embassy</option>
                                    <option value="HIGH COMMISSION" <?php echo (getFieldValue($record, 'covering_letter') == 'HIGH COMMISSION') ? 'selected' : ''; ?>>High Commission</option>
                                    <option value="NIT/ORG" <?php echo (getFieldValue($record, 'covering_letter') == 'NIT/ORG') ? 'selected' : ''; ?>>NIT/ORG</option>
                                    <option value="GO" <?php echo (getFieldValue($record, 'covering_letter') == 'GO') ? 'selected' : ''; ?>>GO</option>
                                </select>
                            </div>
                            <?php endif; ?>

                            <?php if ($application_type_exists): ?>
                            <div class="form-group">
                                <label class="form-label">Application Type</label>
                                <select class="form-select" name="application_type" id="edit-application-type">
                                    <option value="fresh" <?php echo (getFieldValue($record, 'application_type') == 'fresh') ? 'selected' : ''; ?>>Fresh Application</option>
                                    <option value="renewal" <?php echo (getFieldValue($record, 'application_type') == 'renewal') ? 'selected' : ''; ?>>Renewal Application</option>
                                </select>
                            </div>
                            <?php endif; ?>

                            <?php if ($entry_stamp_date_exists): ?>
                            <div class="form-group">
                                <label class="form-label">Entry Stamp Date</label>
                                <input type="date" class="form-control" name="entry_stamp_date" value="<?php echo htmlspecialchars(getFieldValue($record, 'entry_stamp_date')); ?>">
                            </div>
                            <?php endif; ?>

                            <?php if ($date_of_arrival_exists): ?>
                            <div class="form-group">
                                <label class="form-label">Date of Arrival</label>
                                <input type="date" 
                                       class="form-control" 
                                       name="date_of_arrival" 
                                       value="<?php echo htmlspecialchars(getFieldValue($record, 'date_of_arrival')); ?>">
                            </div>
                            <?php endif; ?>

                            <?php if ($valid_from_exists): ?>
                            <div class="form-group">
                                <label class="form-label">Valid From</label>
                                <input type="date" 
                                       class="form-control" 
                                       name="valid_from" 
                                       value="<?php echo htmlspecialchars(getFieldValue($record, 'valid_from')); ?>">
                            </div>
                            <?php endif; ?>

                            <?php if ($valid_until_exists): ?>
                            <div class="form-group">
                                <label class="form-label">Valid Until</label>
                                <input type="date" 
                                       class="form-control" 
                                       name="valid_until" 
                                       value="<?php echo htmlspecialchars(getFieldValue($record, 'valid_until')); ?>">
                            </div>
                            <?php endif; ?>

                            <?php if ($contract_duration_exists): ?>
                            <div class="form-group">
                                <label class="form-label">Contract Duration</label>
                                <input type="text" class="form-control" name="contract_duration" value="<?php echo htmlspecialchars(getFieldValue($record, 'contract_duration')); ?>" placeholder="e.g. 2 YEARS" style="text-transform: uppercase;">
                            </div>
                            <?php endif; ?>

                            <?php if ($number_of_dependencies_exists): ?>
                            <div class="form-group">
                                <label class="form-label">Number of Dependencies</label>
                                <input type="number" class="form-control" name="number_of_dependencies" min="0" step="1" value="<?php echo htmlspecialchars(getFieldValue($record, 'number_of_dependencies', 0)); ?>">
                            </div>
                            <?php endif; ?>

                            <?php if ($remarks_exists): ?>
                            <div class="form-group" style="grid-column: span 2;">
                                <label class="form-label">Remarks</label>
                                <textarea class="form-control" 
                                          name="remarks" 
                                          rows="3"><?php echo htmlspecialchars(getFieldValue($record, 'remarks')); ?></textarea>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div style="display: flex; gap: 15px; margin-top: 30px; justify-content: flex-end; width: 100%; flex-wrap: wrap;">
                        <button type="submit" name="update_record" class="btn btn-action btn-green">
                            <i class="fa fa-save"></i> Save Changes
                        </button>
                        <button type="button" class="btn btn-back" onclick="window.location.href='re-entry-visa'">
                            <i class="fa fa-times"></i> Cancel
                        </button>
                        <a href="viewrecord?id=<?php echo urlencode($visa_number); ?>" class="btn btn-action btn-gold text-dark" style="text-decoration: none;">
                            <i class="fa fa-eye"></i> View Record
                        </a>
                    </div>
                </form>
            </div>
            <?php elseif (!$error_message): ?>
                <!-- No Record Found -->
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fa fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                        <h4>Record Not Found</h4>
                        <p class="text-muted">The visa record you're trying to edit does not exist or has been removed.</p>
                        <a href="re-entry-visa" class="btn btn-primary mt-3">
                            <i class="fa fa-arrow-left"></i> Back to Records
                        </a>
                    </div>
                </div>
            <?php endif; ?>
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
        // Function to update date and time
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
            document.getElementById('currentDateTime').textContent = now.toLocaleDateString('en-US', options);
        }
        
        // Update date and time immediately and then every second
        updateDateTime();
        setInterval(updateDateTime, 1000);
        
        // Form validation
        const authorityInput = document.getElementById('authority');
        const authorityStatus = document.getElementById('authority-status');
        const submitBtn = document.querySelector('button[name="update_record"]');
        let authorityValid = true;

        if (authorityInput) {
            const prefix = "ABJ/HQ/DIP/";
            
            // Protect prefix from deletion or editing
            authorityInput.addEventListener('keydown', function(e) {
                const len = prefix.length;
                if (this.selectionStart < len || this.selectionEnd < len) {
                    const allowedKeys = ['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Tab', 'Shift', 'Control', 'Alt', 'Meta'];
                    if (!allowedKeys.includes(e.key) && !(e.ctrlKey || e.metaKey)) {
                        e.preventDefault();
                    }
                } else if (e.key === 'Backspace' && this.selectionStart === len && this.selectionEnd === len) {
                    e.preventDefault();
                }
            });

            authorityInput.addEventListener('input', function(e) {
                if (!this.value.startsWith(prefix)) {
                    this.value = prefix;
                }
                
                let suffix = this.value.substring(prefix.length);
                let cleanedSuffix = suffix.toUpperCase().replace(/[^A-Z0-9\/]/g, '');
                
                if (suffix !== cleanedSuffix) {
                    this.value = prefix + cleanedSuffix;
                }
            });
            
            authorityInput.addEventListener('paste', function(e) {
                setTimeout(() => {
                    if (!this.value.startsWith(prefix)) {
                        this.value = prefix;
                    }
                    let suffix = this.value.substring(prefix.length);
                    this.value = prefix + suffix.toUpperCase().replace(/[^A-Z0-9\/]/g, '');
                }, 0);
            });

            if (authorityStatus) {
                const currentVisaNumber = "<?php echo htmlspecialchars(getFieldValue($record, 'visa_number')); ?>";
                
                authorityInput.addEventListener('blur', function() {
                    const auth = this.value.trim();
                    if (!auth || auth === 'ABJ/HQ/DIP/' || auth.length <= 11) {
                        authorityStatus.innerHTML = '';
                        return;
                    }

                    fetch('check_authority', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `authority=${encodeURIComponent(auth)}&exclude_visa=${encodeURIComponent(currentVisaNumber)}`
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.exists) {
                                authorityStatus.innerHTML = `<span style="color: #d9534f;"><i class="fa fa-exclamation-circle"></i> Authority number ${data.authority} already exists in the system!</span>`;
                                authorityInput.style.borderColor = '#d9534f';
                                authorityInput.style.backgroundColor = '#fdf2f2';
                                authorityValid = false;
                            } else {
                                authorityStatus.innerHTML = '<span style="color: #2b7a43;"><i class="fa fa-check-circle"></i> Authority number is valid</span>';
                                authorityInput.style.borderColor = '#2b7a43';
                                authorityInput.style.backgroundColor = '#f2fdf5';
                                authorityValid = true;
                            }
                            toggleSubmitButton();
                        })
                        .catch(error => {
                            console.error('Error checking authority:', error);
                            authorityStatus.innerHTML = '';
                        });
                });

                authorityInput.addEventListener('input', function() {
                    authorityStatus.innerHTML = '';
                    authorityInput.style.borderColor = '';
                    authorityInput.style.backgroundColor = '';
                    authorityValid = true;
                    toggleSubmitButton();
                });
            }
        }

        function toggleEditOldVisa() {
            const appTypeSelect = document.getElementById('edit-application-type');
            const oldVisaGroup = document.getElementById('edit-old-visa-group');
            const oldVisaInput = document.getElementById('edit-old-visa-number');
            
            if (appTypeSelect && oldVisaGroup) {
                if (appTypeSelect.value === 'renewal') {
                    oldVisaGroup.style.display = 'block';
                    if (oldVisaInput) oldVisaInput.setAttribute('required', 'required');
                } else {
                    oldVisaGroup.style.display = 'none';
                    if (oldVisaInput) {
                        oldVisaInput.removeAttribute('required');
                        oldVisaInput.value = '';
                    }
                }
            }
        }

        function toggleSubmitButton() {
            if (submitBtn) {
                if (!authorityValid) {
                    submitBtn.disabled = true;
                    submitBtn.style.opacity = '0.5';
                    submitBtn.style.cursor = 'not-allowed';
                } else {
                    submitBtn.disabled = false;
                    submitBtn.style.opacity = '1';
                    submitBtn.style.cursor = 'pointer';
                }
            }
        }

        document.getElementById('editForm').addEventListener('submit', function(e) {
            const validFrom = document.getElementsByName('valid_from')[0];
            const validUntil = document.getElementsByName('valid_until')[0];
            const dateOfIssue = document.getElementsByName('date_of_issue')[0];
            
            // Check Authority duplicate status
            if (!authorityValid) {
                alert('This Authority Number already exists in the system!');
                e.preventDefault();
                return false;
            }

            // Check if Authority prefix is complete
            if (authorityInput && (authorityInput.value.trim() === 'ABJ/HQ/DIP/' || authorityInput.value.trim().length <= 11)) {
                alert('Please enter a suffix value for the Authority field.');
                e.preventDefault();
                return false;
            }

            // Check if valid until is after valid from (if both exist)
            if (validFrom && validUntil && validFrom.value && validUntil.value) {
                const fromDate = new Date(validFrom.value);
                const untilDate = new Date(validUntil.value);
                
                if (untilDate < fromDate) {
                    alert('Valid Until date must be after Valid From date.');
                    e.preventDefault();
                    return false;
                }
            }
            
            // Check if date of issue is not in the future (if exists)
            if (dateOfIssue && dateOfIssue.value) {
                const issueDate = new Date(dateOfIssue.value);
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                
                if (issueDate > today) {
                    alert('Date of Issue cannot be in the future.');
                    e.preventDefault();
                    return false;
                }
            }
            
            return true;
        });
        
        // Sidebar functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle edit old visa number based on application type
            const appTypeSelect = document.getElementById('edit-application-type');
            if (appTypeSelect) {
                appTypeSelect.addEventListener('change', toggleEditOldVisa);
                toggleEditOldVisa(); // run initially
            }

            // Auto-focus first field if no errors
            <?php if (!$error_message && $record): ?>
                setTimeout(function() {
                    const firstInput = document.querySelector('form input:not([type="hidden"]):not([readonly])');
                    if (firstInput) {
                        firstInput.focus();
                    }
                }, 100);
            <?php endif; ?>
        });
    </script>
</body>
</html>
<?php
// Close database connection
if (isset($conn) && $conn) {
    mysqli_close($conn);
}
?>




