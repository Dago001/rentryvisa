<?php
define('DB_SERVER', '127.0.0.1');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_DATABASE', 'nisappsc');
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
?>
