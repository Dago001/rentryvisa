<?php
require_once('security.php');
require_once('db/db.php');

header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$visa_no = isset($_REQUEST['visa_number']) ? trim($_REQUEST['visa_number']) : '';

if (empty($visa_no)) {
    echo json_encode(['exists' => false]);
    exit();
}

// Strip spaces and special characters for safety/consistency
$visa_no = preg_replace('/[^a-zA-Z0-9]/', '', $visa_no);
$visa_no = mysqli_real_escape_string($conn, $visa_no);

// Query to find if the visa number already exists in the database
$query = "SELECT id FROM visa_applications 
          WHERE REPLACE(visa_number, ' ', '') = '$visa_no' 
          LIMIT 1";

$result = mysqli_query($conn, $query);

if ($result && mysqli_num_rows($result) > 0) {
    echo json_encode(['exists' => true]);
} else {
    echo json_encode(['exists' => false]);
}
?>
