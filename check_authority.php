<?php
require_once('security.php');
require_once('db/db.php');

header('Content-Type: application/json');

// Check database connection
if (!$conn) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

$authority = $_GET['authority'] ?? '';
$authority = strtoupper(trim($authority));

if (empty($authority)) {
    echo json_encode(['exists' => false]);
    exit();
}

// Search for any existing application with this authority number
$sql = "SELECT id FROM visa_applications WHERE authority = ?";
$stmt = mysqli_prepare($conn, $sql);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "s", $authority);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    $exists = mysqli_stmt_num_rows($stmt) > 0;
    mysqli_stmt_close($stmt);
    
    echo json_encode(['exists' => $exists, 'authority' => $authority]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Database query preparation failed']);
}
?>
