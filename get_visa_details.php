<?php
// Ensure no warning/error output is prepended to the JSON response
error_reporting(0);
ini_set('display_errors', 0);

// Start output buffering to catch any stray output from included files
ob_start();

require_once('security.php');
require_once('db/db.php');

// Discard any output buffered so far (e.g. whitespace, warnings)
ob_clean();

header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$visa_no = isset($_REQUEST['visa_number']) ? trim($_REQUEST['visa_number']) : '';

if (empty($visa_no)) {
    echo json_encode(['error' => 'Visa number is required']);
    exit();
}

// Strip spaces and special characters for safety/consistency in comparison
$clean_visa_no = preg_replace('/[^a-zA-Z0-9]/', '', $visa_no);
$clean_visa_no = mysqli_real_escape_string($conn, $clean_visa_no);

$query = "SELECT * FROM visa_applications 
          WHERE REPLACE(visa_number, ' ', '') = '$clean_visa_no' 
          ORDER BY application_date DESC LIMIT 1";

$result = mysqli_query($conn, $query);

if ($result && mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    // Sanitize values for JSON output
    $data = [];
    foreach ($row as $key => $val) {
         $data[$key] = $val !== null ? $val : '';
    }
    echo json_encode(['success' => true, 'data' => $data]);
} else {
    echo json_encode(['success' => false, 'message' => 'Record not found']);
}
exit();
?>
