<?php
require_once('security.php');
require_once('db/db.php');

// Check login
if (!isset($_SESSION['username'])) {
    header("Location: index");
    exit();
}

$visa_number = isset($_GET['id']) ? mysqli_real_escape_string($conn, $_GET['id']) : '';

if (empty($visa_number)) {
    die("No visa number provided");
}

// Simple query - adjust based on your actual columns
$sql = "SELECT visa_number, passport_number, surname, given_name FROM visa_applications WHERE visa_number = '$visa_number'";
$result = mysqli_query($conn, $sql);

if (!$result || mysqli_num_rows($result) == 0) {
    die("Record not found");
}

$record = mysqli_fetch_assoc($result);

// Simple HTML that can be printed as PDF
?>
<!DOCTYPE html>
<html>
<head>
    <title>Visa <?php echo htmlspecialchars($visa_number); ?></title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 40px; }
        .header { text-align: center; margin-bottom: 30px; }
        .title { font-size: 24px; font-weight: bold; color: #1a5c2e; }
        .subtitle { color: #666; }
        .info { margin: 20px 0; }
        .label { font-weight: bold; }
        .footer { margin-top: 50px; text-align: center; font-size: 12px; color: #666; }
        @media print {
            button { display: none; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">Nigeria Immigration Service</div>
        <div class="subtitle">Re-Entry Visa</div>
    </div>
    
    <div class="info">
        <p><span class="label">Visa Number:</span> <?php echo htmlspecialchars($record['visa_number']); ?></p>
        <p><span class="label">Passport Number:</span> <?php echo htmlspecialchars($record['passport_number']); ?></p>
        <p><span class="label">Full Name:</span> <?php echo htmlspecialchars($record['surname'] . ' ' . $record['given_name']); ?></p>
        <p><span class="label">Surname:</span> <?php echo htmlspecialchars($record['surname']); ?></p>
        <p><span class="label">Given Name:</span> <?php echo htmlspecialchars($record['given_name']); ?></p>
    </div>
    
    <div class="footer">
        <p>Generated: <?php echo date('d/m/Y H:i:s'); ?></p>
        <p>By: <?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?></p>
    </div>
    
    <div style="text-align: center; margin-top: 30px;">
        <button onclick="window.print()" style="padding: 10px 20px; background: #1a5c2e; color: white; border: none; cursor: pointer;">
            Print / Save as PDF
        </button>
        <p style="font-size: 12px; color: #666; margin-top: 10px;">
            Click above to print or save as PDF
        </p>
    </div>
    
    <script>
        // Auto-show print dialog
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</body>
</html>
<?php
mysqli_close($conn);
?>

