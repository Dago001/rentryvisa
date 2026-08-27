<?php
require_once('security.php');
error_reporting(E_ALL);

// Database connection
require_once('db/db.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['message'] = [
            'type' => 'error',
            'text' => 'Security validation failed. Please refresh the page.'
        ];
        header("Location: new-record");
        exit();
    }

    // Check if user is logged in
    if (!isset($_SESSION['username'])) {
        $_SESSION['message'] = [
            'type' => 'error',
            'text' => 'Please login to submit visa applications.'
        ];
        header("Location: index");
        exit();
    }

    // Block ViewOnly users from submitting applications
    $userRole = $_SESSION['role'] ?? 'DataEntry';
    if ($userRole === 'ViewOnly' || strcasecmp($userRole, 'viewonly') === 0 || strcasecmp($userRole, 'view only') === 0) {
        $_SESSION['message'] = [
            'type' => 'error',
            'text' => 'Access Denied: View Only users cannot create or submit visa applications.'
        ];
        header("Location: index");
        exit();
    }

    // Check if all required fields are present
    $required_fields = [
        'visa_number', 'passport_number', 'surname', 'given_name', 'nationality',
        'place_of_issue', 'date_of_issue', 'embassy', 'authority', 'within', 'journey', 'fees', 'category',
        'application_type', 'number_of_dependencies', 'visa_type', 'gender', 'mfa_letter', 'covering_letter', 
        'entry_stamp_date', 'contract_duration', 'passport_date_of_issue', 'passport_date_of_expiry'
    ];
    
    // Add old_visa_number to required if renewal
    if (isset($_POST['application_type']) && $_POST['application_type'] === 'renewal') {
        $required_fields[] = 'old_visa_number';
    }
    
    $missing_fields = [];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || $_POST[$field] === '') {
            $missing_fields[] = $field;
        }
    }
    
    if (!empty($missing_fields)) {
        $_SESSION['message'] = [
            'type' => 'error',
            'text' => 'Missing required fields: ' . implode(', ', $missing_fields)
        ];
        header("Location: new-record");
        exit();
    }

    // Sanitize and validate input data (force uppercase on all text inputs)
    $visa_number = strtoupper(mysqli_real_escape_string($conn, trim($_POST['visa_number'])));
    $passport_number = strtoupper(mysqli_real_escape_string($conn, trim($_POST['passport_number'])));
    $surname = strtoupper(mysqli_real_escape_string($conn, trim($_POST['surname'])));
    $given_name = strtoupper(mysqli_real_escape_string($conn, trim($_POST['given_name'])));
    $nationality = strtoupper(mysqli_real_escape_string($conn, trim($_POST['nationality'])));
    $place_of_issue = strtoupper(mysqli_real_escape_string($conn, trim($_POST['place_of_issue'])));
    $date_of_issue = mysqli_real_escape_string($conn, $_POST['date_of_issue']);
    $embassy = strtoupper(mysqli_real_escape_string($conn, trim($_POST['embassy'])));
    $authority = strtoupper(mysqli_real_escape_string($conn, trim($_POST['authority'])));
    $within = mysqli_real_escape_string($conn, $_POST['within']);
    $journey = strtoupper(mysqli_real_escape_string($conn, $_POST['journey']));
    $fees = strtoupper(mysqli_real_escape_string($conn, $_POST['fees']));
    $category = strtoupper(mysqli_real_escape_string($conn, $_POST['category']));
    $created_by = mysqli_real_escape_string($conn, $_SESSION['username']);

    $application_type = strtolower(mysqli_real_escape_string($conn, trim($_POST['application_type'] ?? 'fresh')));
    $number_of_dependencies = intval($_POST['number_of_dependencies'] ?? 0);
    $visa_type = strtoupper(mysqli_real_escape_string($conn, trim($_POST['visa_type'] ?? '')));
    $gender = strtoupper(mysqli_real_escape_string($conn, trim($_POST['gender'] ?? '')));
    $mfa_letter = strtoupper(mysqli_real_escape_string($conn, trim($_POST['mfa_letter'] ?? '')));
    $covering_letter = strtoupper(mysqli_real_escape_string($conn, trim($_POST['covering_letter'] ?? '')));
    $entry_stamp_date = mysqli_real_escape_string($conn, $_POST['entry_stamp_date'] ?? '');
    $contract_duration = strtoupper(mysqli_real_escape_string($conn, trim($_POST['contract_duration'] ?? '')));
    $old_visa_number = ($application_type === 'renewal') ? strtoupper(mysqli_real_escape_string($conn, trim($_POST['old_visa_number'] ?? ''))) : null;
    
    $passport_date_of_issue = mysqli_real_escape_string($conn, $_POST['passport_date_of_issue']);
    $passport_date_of_expiry = mysqli_real_escape_string($conn, $_POST['passport_date_of_expiry']);

    // Validate Embassy/High Commission name to only accept up to 20 characters
    if (strlen($_POST['embassy']) > 20) {
        $_SESSION['message'] = [
            'type' => 'error',
            'text' => 'Embassy/High Commission name must not exceed 20 characters!'
        ];
        header("Location: new-record");
        exit();
    }

    // Validate Passport Number (Numbers and Alphabets only, no spaces or special characters)
    $passport_clean = preg_replace('/[^A-Z0-9]/', '', $passport_number);
    if ($passport_clean !== $passport_number || empty($passport_clean)) {
        $_SESSION['message'] = [
            'type' => 'error',
            'text' => 'Passport Number must contain only alphabetic characters and numbers (no spaces or special characters).'
        ];
        header("Location: new-record");
        exit();
    }

    // Validate dates
    $current_date = date('Y-m-d');
    if ($date_of_issue > $current_date) {
        $_SESSION['message'] = [
            'type' => 'error',
            'text' => 'Date of issue cannot be in the future!'
        ];
        header("Location: new-record");
        exit();
    }

    if ($within <= $date_of_issue) {
        $_SESSION['message'] = [
            'type' => 'error',
            'text' => 'Validity period (Within) must be after date of issue!'
        ];
        header("Location: new-record");
        exit();
    }

    if ($passport_date_of_issue > $current_date) {
        $_SESSION['message'] = [
            'type' => 'error',
            'text' => 'Passport date of issue cannot be in the future!'
        ];
        header("Location: new-record");
        exit();
    }

    if ($passport_date_of_expiry <= $passport_date_of_issue) {
        $_SESSION['message'] = [
            'type' => 'error',
            'text' => 'Passport date of expiry must be after passport date of issue!'
        ];
        header("Location: new-record");
        exit();
    }

    // First, let's check if the table exists, if not create it
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'visa_applications'");
    if (mysqli_num_rows($table_check) == 0) {
        // Create the table if it doesn't exist
        $create_table_sql = "CREATE TABLE visa_applications (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            visa_number VARCHAR(50) NOT NULL UNIQUE,
            passport_number VARCHAR(50) NOT NULL,
            surname VARCHAR(100) NOT NULL,
            given_name VARCHAR(100) NOT NULL,
            nationality VARCHAR(100) NOT NULL,
            place_of_issue VARCHAR(100) NOT NULL,
            date_of_issue DATE NOT NULL,
            embassy VARCHAR(100) NOT NULL,
            authority VARCHAR(50) NOT NULL,
            within DATE NOT NULL,
            journey VARCHAR(20) NOT NULL,
            fees VARCHAR(50) NOT NULL,
            category VARCHAR(50) NOT NULL DEFAULT 'NONE',
            application_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status VARCHAR(20) DEFAULT 'pending',
            created_by VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            application_type VARCHAR(20) NOT NULL DEFAULT 'fresh',
            number_of_dependencies INT NOT NULL DEFAULT 0,
            visa_type VARCHAR(50) DEFAULT NULL,
            gender VARCHAR(20) DEFAULT NULL,
            mfa_letter VARCHAR(3) DEFAULT NULL,
            covering_letter VARCHAR(50) DEFAULT NULL,
            entry_stamp VARCHAR(3) DEFAULT NULL,
            entry_stamp_date DATE DEFAULT NULL,
            contract_duration VARCHAR(100) DEFAULT NULL,
            old_visa_number VARCHAR(50) DEFAULT NULL,
            passport_date_of_issue DATE DEFAULT NULL,
            passport_date_of_expiry DATE DEFAULT NULL
        )";
        
        if (!mysqli_query($conn, $create_table_sql)) {
            $_SESSION['message'] = [
                'type' => 'error',
                'text' => 'Error creating table: ' . mysqli_error($conn)
            ];
            header("Location: new-record");
            exit();
        }
    }
    
    // Check if visa number already exists
    $check_sql = "SELECT id FROM visa_applications WHERE visa_number = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "s", $visa_number);
    mysqli_stmt_execute($check_stmt);
    mysqli_stmt_store_result($check_stmt);
    
    if (mysqli_stmt_num_rows($check_stmt) > 0) {
        $_SESSION['message'] = [
            'type' => 'error',
            'text' => 'Visa number already exists in the system!'
        ];
        mysqli_stmt_close($check_stmt);
        header("Location: new-record");
        exit();
    }
    mysqli_stmt_close($check_stmt);

    // Check if passport number already exists in the system
    $check_passport_sql = "SELECT surname, given_name FROM visa_applications WHERE passport_number = ?";
    $check_passport_stmt = mysqli_prepare($conn, $check_passport_sql);
    mysqli_stmt_bind_param($check_passport_stmt, "s", $passport_number);
    mysqli_stmt_execute($check_passport_stmt);
    $result_passport = mysqli_stmt_get_result($check_passport_stmt);
    
    if ($row_passport = mysqli_fetch_assoc($result_passport)) {
        $existing_surname = strtoupper(trim($row_passport['surname']));
        $existing_given = strtoupper(trim($row_passport['given_name']));
        
        if ($existing_surname !== $surname || $existing_given !== $given_name) {
            $_SESSION['message'] = [
                'type' => 'error',
                'text' => "This Passport Number already exists in the system under the name: $existing_surname $existing_given. To proceed, please use the matching name to tie this record to them."
            ];
            mysqli_stmt_close($check_passport_stmt);
            header("Location: new-record");
            exit();
        }
    }
    mysqli_stmt_close($check_passport_stmt);
    
    // Insert into database
    $sql = "INSERT INTO visa_applications 
            (visa_number, passport_number, surname, given_name, nationality, place_of_issue, 
             date_of_issue, embassy, authority, within, journey, fees, category, created_by, status,
             application_type, number_of_dependencies, visa_type, gender, mfa_letter, covering_letter, entry_stamp_date, contract_duration, old_visa_number, passport_date_of_issue, passport_date_of_expiry) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "sssssssssssssssisssssssss", 
            $visa_number, $passport_number, $surname, $given_name, $nationality,
            $place_of_issue, $date_of_issue, $embassy, $authority, $within, 
            $journey, $fees, $category, $created_by,
            $application_type, $number_of_dependencies, $visa_type, $gender,
            $mfa_letter, $covering_letter, $entry_stamp_date, $contract_duration, $old_visa_number, $passport_date_of_issue, $passport_date_of_expiry);
        
        if ($stmt->execute()) {
    $applicationId = $conn->insert_id;
    
    // Get the passport number from the POST data
    $passportNumber = $_POST['passport_number'];
    
    $_SESSION['message'] = [
        'type' => 'success',
        'text' => "Visa application submitted successfully! Application ID: " . htmlspecialchars($passportNumber)
    ];
    
            
            // Log the activity with full IP and user agent
            $log_description = "Created new visa application for " . $surname . ", " . $given_name . " (Visa: " . $visa_number . ", Passport: " . $passport_number . ")";
            logActivity($conn, "CREATE", $log_description);
            
        } else {
            $_SESSION['message'] = [
                'type' => 'error',
                'text' => 'Error submitting application: ' . mysqli_error($conn)
            ];
        }
        
        mysqli_stmt_close($stmt);
    } else {
        $_SESSION['message'] = [
            'type' => 'error',
            'text' => 'Error preparing statement: ' . mysqli_error($conn)
        ];
    }
    
    mysqli_close($conn);
    
    header("Location: new-record");
    exit();
} else {
    // If not POST request, redirect to form
    $_SESSION['message'] = [
        'type' => 'error',
        'text' => 'Invalid request method.'
    ];
    header("Location: new-record");
    exit();
}
?>
