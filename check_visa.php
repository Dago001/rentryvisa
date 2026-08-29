<?php
require_once('security.php');
require_once('db/db.php');

header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$raw_visa = isset($_REQUEST['visa_number']) ? trim($_REQUEST['visa_number']) : '';
$exclude_id = isset($_REQUEST['exclude_id']) ? intval($_REQUEST['exclude_id']) : 0;

if (empty($raw_visa)) {
    echo json_encode(['exists' => false]);
    exit();
}

$clean_visa = preg_replace('/\s+/', '', $raw_visa);

if ($exclude_id > 0) {
    $stmt = mysqli_prepare($conn, "SELECT id, visa_number FROM visa_applications WHERE (REPLACE(visa_number, ' ', '') = ? OR visa_number = ?) AND id != ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "ssi", $clean_visa, $raw_visa, $exclude_id);
} else {
    $stmt = mysqli_prepare($conn, "SELECT id, visa_number FROM visa_applications WHERE REPLACE(visa_number, ' ', '') = ? OR visa_number = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "ss", $clean_visa, $raw_visa);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($result && mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    echo json_encode([
        'exists' => true,
        'visa_number' => $row['visa_number'],
        'message' => 'This Re-Entry Visa Number already exists in the system!'
    ]);
} else {
    echo json_encode([
        'exists' => false,
        'message' => 'Re-Entry Visa Number is unique and available.'
    ]);
}
mysqli_stmt_close($stmt);
?>
