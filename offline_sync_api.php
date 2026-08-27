<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once('security.php');
require_once('db/db.php');

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    echo json_encode(['status' => 'error', 'message' => 'Please login to submit applications.']);
    exit();
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['status' => 'error', 'message' => 'Security token verification failed. Please reload the page.']);
    exit();
}

// Check database connection
if (!$conn) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit();
}

// Check required fields
$required_fields = [
    'visa_number', 'passport_number', 'surname', 'given_name', 'nationality',
    'place_of_issue', 'date_of_issue', 'embassy', 'authority', 'within', 'journey', 'fees'
];

$missing_fields = [];
foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        $missing_fields[] = $field;
    }
}

if (!empty($missing_fields)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing fields: ' . implode(', ', $missing_fields)]);
    exit();
}

// Sanitize inputs (force uppercase on all text inputs)
$visa_number = strtoupper(mysqli_real_escape_string($conn, trim($_POST['visa_number'])));
$passport_number = strtoupper(mysqli_real_escape_string($conn, trim($_POST['passport_number'])));
$surname = strtoupper(mysqli_real_escape_string($conn, trim($_POST['surname'])));
$given_name = strtoupper(mysqli_real_escape_string($conn, trim($_POST['given_name'])));
$nationality = strtoupper(mysqli_real_escape_string($conn, trim($_POST['nationality'])));
$place_of_issue = strtoupper(mysqli_real_escape_string($conn, trim($_POST['place_of_issue'])));
$date_of_issue = mysqli_real_escape_string($conn, $_POST['date_of_issue']);
$embassy = strtoupper(mysqli_real_escape_string($conn, trim($_POST['embassy'])));
if (strlen($_POST['embassy']) > 20) {
    echo json_encode(['status' => 'error', 'message' => 'Embassy/High Commission name must not exceed 20 characters!']);
    exit();
}
$authority = strtoupper(mysqli_real_escape_string($conn, trim($_POST['authority'])));
$within = mysqli_real_escape_string($conn, $_POST['within']);
$journey = strtoupper(mysqli_real_escape_string($conn, $_POST['journey']));
$fees = strtoupper(mysqli_real_escape_string($conn, $_POST['fees']));
$remarks = strtoupper(mysqli_real_escape_string($conn, trim($_POST['remarks'] ?? '')));
$created_by = mysqli_real_escape_string($conn, $_SESSION['username']);

// Check duplicate passport (reject only if name doesn't match)
$check_passport = mysqli_query($conn, "SELECT surname, given_name FROM visa_applications WHERE passport_number = '$passport_number' LIMIT 1");
if ($check_passport && mysqli_num_rows($check_passport) > 0) {
    $row_passport = mysqli_fetch_assoc($check_passport);
    $existing_surname = strtoupper(trim($row_passport['surname']));
    $existing_given = strtoupper(trim($row_passport['given_name']));
    
    if ($existing_surname !== $surname || $existing_given !== $given_name) {
        echo json_encode(['status' => 'error', 'message' => "Passport Number '$passport_number' already exists in system for another person ($existing_surname $existing_given)."]);
        exit();
    }
}

// Check duplicate visa number. If it exists, append suffix to avoid conflicts
$check_visa = mysqli_query($conn, "SELECT id FROM visa_applications WHERE visa_number = '$visa_number'");
if ($check_visa && mysqli_num_rows($check_visa) > 0) {
    // Generate a fresh unique visa number offline replacement
    $visa_number = $visa_number . '-' . strtoupper(bin2hex(random_bytes(2)));
}

// Prepare SQL statement mapping the exact columns present in database
$checkColumns = mysqli_query($conn, "DESCRIBE visa_applications");
$columns = [];
if ($checkColumns) {
    while ($row = mysqli_fetch_assoc($checkColumns)) {
        $columns[] = $row['Field'];
    }
    mysqli_free_result($checkColumns);
}

$insertFields = [
    'visa_number' => $visa_number,
    'passport_number' => $passport_number,
    'surname' => $surname,
    'given_name' => $given_name,
    'nationality' => $nationality,
    'place_of_issue' => $place_of_issue,
    'date_of_issue' => $date_of_issue,
    'embassy' => $embassy,
    'authority' => $authority,
    'within' => $within,
    'journey' => $journey,
    'fees' => $fees,
    'created_by' => $created_by,
    'status' => 'active'
];

if (in_array('remarks', $columns)) {
    $insertFields['remarks'] = $remarks;
}

$newOptionalFields = [
    'application_type' => 'application_type',
    'number_of_dependencies' => 'number_of_dependencies',
    'visa_type' => 'visa_type',
    'gender' => 'gender',
    'mfa_letter' => 'mfa_letter',
    'covering_letter' => 'covering_letter',
    'entry_stamp' => 'entry_stamp',
    'entry_stamp_date' => 'entry_stamp_date',
    'contract_duration' => 'contract_duration',
    'old_visa_number' => 'old_visa_number',
    'passport_date_of_issue' => 'passport_date_of_issue',
    'passport_date_of_expiry' => 'passport_date_of_expiry'
];
foreach ($newOptionalFields as $post_key => $col_name) {
    if (in_array($col_name, $columns) && isset($_POST[$post_key]) && $_POST[$post_key] !== '') {
        if ($col_name === 'number_of_dependencies') {
            $insertFields[$col_name] = intval($_POST[$post_key]);
        } else {
            $insertFields[$col_name] = strtoupper(mysqli_real_escape_string($conn, trim($_POST[$post_key])));
        }
    }
}

$fields_str = implode(', ', array_keys($insertFields));
$vals = [];
foreach ($insertFields as $val) {
    $vals[] = "'" . $val . "'";
}
$vals_str = implode(', ', $vals);

$sql = "INSERT INTO visa_applications ($fields_str) VALUES ($vals_str)";

if (mysqli_query($conn, $sql)) {
    // Log the sync action to activity_log (audit trail)
    $log_description = "Created new visa application for $surname, $given_name (Visa: $visa_number, Passport: $passport_number) via Offline Sync";
    logActivity($conn, "CREATE", $log_description);
    
    echo json_encode(['status' => 'success', 'message' => "Application for Passport: $passport_number successfully synced!"]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Error inserting application: ' . mysqli_error($conn)]);
}

mysqli_close($conn);
?>
