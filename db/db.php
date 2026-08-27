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
    'passport_date_of_expiry' => "DATE DEFAULT NULL"
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

// Mail / SMTP Configuration & Database Table Management
$tbl_email_check = mysqli_query($conn, "SHOW TABLES LIKE 'email_settings'");
if (!$tbl_email_check || mysqli_num_rows($tbl_email_check) == 0) {
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS email_settings (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        smtp_host VARCHAR(255) NOT NULL DEFAULT 'mail.nisapps.com.ng',
        smtp_port INT(11) NOT NULL DEFAULT 587,
        smtp_user VARCHAR(255) DEFAULT '',
        smtp_pass VARCHAR(255) DEFAULT '',
        smtp_encryption VARCHAR(20) NOT NULL DEFAULT 'tls',
        from_address VARCHAR(255) NOT NULL DEFAULT 'noreply@nisapps.com.ng',
        from_name VARCHAR(255) NOT NULL DEFAULT 'Nigeria Immigration Service (NISREV)',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    
    mysqli_query($conn, "INSERT INTO email_settings (smtp_host, smtp_port, smtp_user, smtp_pass, smtp_encryption, from_address, from_name) 
        VALUES ('mail.nisapps.com.ng', 587, '', '', 'tls', 'noreply@nisapps.com.ng', 'Nigeria Immigration Service (NISREV)')");
}

// Load dynamic email settings from database
$es_check = mysqli_query($conn, "SELECT * FROM email_settings LIMIT 1");
if ($es_check && $es = mysqli_fetch_assoc($es_check)) {
    if (!defined('SMTP_HOST')) define('SMTP_HOST', $es['smtp_host'] ?: 'mail.nisapps.com.ng');
    if (!defined('SMTP_PORT')) define('SMTP_PORT', intval($es['smtp_port']) ?: 587);
    if (!defined('SMTP_USER')) define('SMTP_USER', $es['smtp_user'] ?? '');
    if (!defined('SMTP_PASS')) define('SMTP_PASS', $es['smtp_pass'] ?? '');
    if (!defined('SMTP_ENCRYPTION')) define('SMTP_ENCRYPTION', $es['smtp_encryption'] ?: 'tls');
    if (!defined('MAIL_FROM_ADDRESS')) define('MAIL_FROM_ADDRESS', $es['from_address'] ?: 'noreply@nisapps.com.ng');
    if (!defined('MAIL_FROM_NAME')) define('MAIL_FROM_NAME', $es['from_name'] ?: 'Nigeria Immigration Service (NISREV)');
} else {
    if (!defined('SMTP_HOST')) define('SMTP_HOST', 'mail.nisapps.com.ng');
    if (!defined('SMTP_PORT')) define('SMTP_PORT', 587);
    if (!defined('SMTP_USER')) define('SMTP_USER', '');
    if (!defined('SMTP_PASS')) define('SMTP_PASS', '');
    if (!defined('SMTP_ENCRYPTION')) define('SMTP_ENCRYPTION', 'tls');
    if (!defined('MAIL_FROM_ADDRESS')) define('MAIL_FROM_ADDRESS', 'noreply@nisapps.com.ng');
    if (!defined('MAIL_FROM_NAME')) define('MAIL_FROM_NAME', 'Nigeria Immigration Service (NISREV)');
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
