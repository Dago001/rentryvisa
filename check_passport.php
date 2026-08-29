<?php
require_once('security.php');
require_once('db/db.php');

header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$passport_no = isset($_REQUEST['passport_number']) ? trim($_REQUEST['passport_number']) : '';

if (empty($passport_no)) {
    echo json_encode(['exists' => false]);
    exit();
}

$passport_no = mysqli_real_escape_string($conn, $passport_no);

// Query to find if the passport number already exists in the database
$query = "SELECT * FROM visa_applications 
          WHERE passport_number = '$passport_no' 
          ORDER BY application_date DESC LIMIT 1";

$result = mysqli_query($conn, $query);

if ($result && mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    $data = [];
    foreach ($row as $key => $val) {
         $data[$key] = $val !== null ? $val : '';
    }
    echo json_encode([
        'exists' => true,
        'surname' => strtoupper($row['surname']),
        'given_name' => strtoupper($row['given_name']),
        'data' => $data
    ]);
} else {
    echo json_encode(['exists' => false]);
}
?>
