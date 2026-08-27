<?php
define('DB_SERVER', 'localhost:3306');
define('DB_USERNAME', 'nisappsc_admin');
define('DB_PASSWORD', 'Password@2025');
define('DB_DATABASE', 'nisappsc_visavignette');
$conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_DATABASE) or die("Database connection failed");
//$database = mysqli_select_db($connection, DB_DATABASE) or die(mysqli_error());

/* check connection */
if (mysqli_connect_errno()) {
    printf("Connect failed: %s\n", mysqli_connect_error());
    exit();
}

// Check if category column exists in visa_applications table, add if not
$category_col_check = mysqli_query($conn, "SHOW COLUMNS FROM visa_applications LIKE 'category'");
if ($category_col_check && mysqli_num_rows($category_col_check) == 0) {
    mysqli_query($conn, "ALTER TABLE visa_applications ADD COLUMN category VARCHAR(50) NOT NULL DEFAULT 'NONE'");
}

// Check and add new columns for Fresh and Renewal applications
$new_cols = [
    'application_type' => "VARCHAR(20) NOT NULL DEFAULT 'fresh'",
    'number_of_dependencies' => "INT NOT NULL DEFAULT 0",
    'visa_type' => "VARCHAR(50) DEFAULT NULL",
    'gender' => "VARCHAR(20) DEFAULT NULL",
    'mfa_letter' => "VARCHAR(3) DEFAULT NULL",
    'covering_letter' => "VARCHAR(50) DEFAULT NULL",
    'entry_stamp' => "VARCHAR(3) DEFAULT NULL",
    'entry_stamp_date' => "DATE DEFAULT NULL",
    'contract_duration' => "VARCHAR(100) DEFAULT NULL",
    'old_visa_number' => "VARCHAR(50) DEFAULT NULL",
    'passport_date_of_issue' => "DATE DEFAULT NULL",
    'passport_date_of_expiry' => "DATE DEFAULT NULL",
    'print_status' => "TINYINT(1) NOT NULL DEFAULT 0",
    'printed_by' => "VARCHAR(100) DEFAULT NULL",
    'printed_at' => "DATETIME DEFAULT NULL"
];

foreach ($new_cols as $col_name => $col_def) {
    $col_check = mysqli_query($conn, "SHOW COLUMNS FROM visa_applications LIKE '$col_name'");
    if ($col_check && mysqli_num_rows($col_check) == 0) {
        mysqli_query($conn, "ALTER TABLE visa_applications ADD COLUMN $col_name $col_def");
    }
}

// Check if google_auth_secret and password_reset_required columns exist in users table, add if not
$ga_check = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'google_auth_secret'");
if ($ga_check && mysqli_num_rows($ga_check) == 0) {
    mysqli_query($conn, "ALTER TABLE users ADD COLUMN google_auth_secret VARCHAR(32) DEFAULT NULL");
}

$pwd_reset_check = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'password_reset_required'");
if ($pwd_reset_check && mysqli_num_rows($pwd_reset_check) == 0) {
    mysqli_query($conn, "ALTER TABLE users ADD COLUMN password_reset_required TINYINT(1) NOT NULL DEFAULT 0");
}

// Ensure activity_log table structure and normalize loopback IPs
$act_table_check = mysqli_query($conn, "SHOW TABLES LIKE 'activity_log'");
if ($act_table_check && mysqli_num_rows($act_table_check) > 0) {
    $ua_col_check = mysqli_query($conn, "SHOW COLUMNS FROM activity_log LIKE 'user_agent'");
    if ($ua_col_check && mysqli_num_rows($ua_col_check) == 0) {
        mysqli_query($conn, "ALTER TABLE activity_log ADD COLUMN user_agent TEXT DEFAULT NULL AFTER ip_address");
    }
    mysqli_query($conn, "UPDATE activity_log SET ip_address = '127.0.0.1' WHERE ip_address = '::1' OR ip_address = '0:0:0:0:0:0:0:1' OR ip_address IS NULL OR ip_address = ''");
}
?>
