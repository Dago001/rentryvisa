<?php
require_once('security.php');
require_once('db/db.php');

header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$passport_no = isset($_GET['passport_number']) ? trim($_GET['passport_number']) : '';

if (empty($passport_no)) {
    echo json_encode(['exists' => false]);
    exit();
}

// Strip spaces and special characters for safety/consistency
$passport_no = preg_replace('/[^a-zA-Z0-9]/', '', $passport_no);
$passport_no = mysqli_real_escape_string($conn, $passport_no);

// Query to find if the passport number already exists in the database
$query = "SELECT surname, given_name FROM visa_applications 
          WHERE REPLACE(passport_number, ' ', '') = '$passport_no' 
          ORDER BY application_date DESC LIMIT 1";

$result = mysqli_query($conn, $query);

if ($result && mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    echo json_encode([
        'exists' => true,
        'surname' => strtoupper($row['surname']),
        'given_name' => strtoupper($row['given_name'])
    ]);
} else {
    echo json_encode(['exists' => false]);
}
?>
