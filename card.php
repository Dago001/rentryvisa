<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once('security.php');

// Check if user is logged in
if (!isset($_SESSION['user_id']) && !isset($_SESSION['username'])) {
    header("Location: index");
    exit();
}

// Block ViewOnly role or requests from Global Search from accessing printing card
$userRole = $_SESSION['role'] ?? 'DataEntry';
if ($userRole === 'ViewOnly' || strcasecmp($userRole, 'viewonly') === 0 || strcasecmp($userRole, 'view only') === 0 || (isset($_GET['ref']) && $_GET['ref'] === 'globalsearch')) {
    header("Location: globalsearch");
    exit();
}

include("db/db.php");

// Check database connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

require "vendors/autoload.php";


// Validate and sanitize input
if (isset($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    
    // Use prepared statement
    $stmt = $conn->prepare("SELECT * FROM visa_applications WHERE visa_number = ? OR id = ? LIMIT 1");
    $stmt->bind_param("ss", $id, $id);
    $stmt->execute();
    $retrieve = $stmt->get_result();
    $numb = $retrieve->num_rows;
    
    if ($numb > 0) {
        $foundk = $retrieve->fetch_assoc();
        $target_visa_no = $foundk['visa_number'];
        $created_by_user = $foundk['created_by'] ?? '';
        $printed_by_user = $foundk['printed_by'] ?? '';
        
        // Ensure DataEntry officers can only print their own work
        $isDataEntry = ($userRole === 'DataEntry' || strcasecmp($userRole, 'dataentry') === 0 || strcasecmp($userRole, 'data entry') === 0);
        $current_user = $_SESSION['username'] ?? '';
        if ($isDataEntry && $created_by_user !== $current_user && $printed_by_user !== $current_user) {
            header("Location: print_visas");
            exit();
        }
        
        // Assign variables (force uppercase for display)
        $profile = strtoupper(htmlspecialchars($foundk['visa_number']));
        $passportnumber = strtoupper(htmlspecialchars($foundk['passport_number']));
        $surname = strtoupper(htmlspecialchars($foundk['surname']));
        $firstname = strtoupper(htmlspecialchars($foundk['given_name']));
        $fullName = trim($surname . ', ' . $firstname);
        $category = isset($foundk['category']) ? strtoupper(htmlspecialchars($foundk['category'])) : '';
        $fees = strtoupper(htmlspecialchars($foundk['fees']));
        $validuntil = formatNISDate($foundk['within']);
        $journey = strtoupper(htmlspecialchars($foundk['journey']));
        $place_of_issue = strtoupper(htmlspecialchars($foundk['place_of_issue']));
        $date_of_issue = formatNISDate($foundk['date_of_issue']);
        $authority = strtoupper(htmlspecialchars($foundk['authority']));
        $authLen = mb_strlen($authority);
        $authFontSize = '22px';
        if ($authLen > 21) {
            $calculatedSize = max(9, floor(22 * (21 / $authLen)));
            $authFontSize = $calculatedSize . 'px';
        }
        $embassy = isset($foundk['embassy']) ? strtoupper(htmlspecialchars($foundk['embassy'])) : '';
        // Update print tracking in visa_applications
        $print_operator = $_SESSION['username'] ?? 'System';
        $update_print = $conn->prepare("UPDATE visa_applications SET print_status = 1, printed_by = ?, printed_at = NOW() WHERE visa_number = ?");
        if ($update_print) {
            $update_print->bind_param("ss", $print_operator, $target_visa_no);
            $update_print->execute();
            $update_print->close();
        }
        
        // Log this card generation/printing activity in the audit trail
        logActivity($conn, "PRINT_VISA_CARD", "Generated/Printed visa vignette card for $surname, $firstname (Visa: $profile, Passport: $passportnumber)");

    } else {
        die("No visa application found with the provided ID.");
    }
} else {
    die("No visa ID provided.");
}
?>

<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
<head>
    <title>Visa Vignette - <?php echo $profile; ?></title>
    <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="vendors/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="vendors/font-awesome/css/font-awesome.min.css" rel="stylesheet">
    <link rel="stylesheet" href="dist/css/style.css">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            margin: 0;
            padding: 0;
            background-color: #f0f0f0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            font-family: 'Lato', sans-serif;
        }
        
        .print-area {
            /* Standard visa sticker size in mm converted to pixels (1mm = 3.78px approx) */
            width: 696px;  /* 184mm * 3.78 ≈ 696px */
            height: 983px; /* 260mm * 3.78 ≈ 983px */
            position: relative;
            transform-origin: 0 0;
            background-color: transparent;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .visa-container {
            width: 100%;
            height: 100%;
            background-image: url('assets/images/visa_vignette.jpg');
            background-size: cover; /* Changed from 100% 100% to cover */
            background-repeat: no-repeat;
            background-position: center center; /* Ensure center positioning */
            position: relative;
            overflow: hidden;
        }
        
        .visa-text {
            position: absolute;
            /*font-weight: bold;*/
            color: #000;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            transform-origin: 0 0;
        }
        
        /* MODIFIED: Visa number will be hidden in print */
        .visa-number {
            top: 3.8%;
            left: 70.9%;
            font-size: 14px;
            letter-spacing: 1.5px;
            width: 40%;
        }
        
        .category {
            top: 50%;
            left: 9.5%;
            font-size: 22px;
            writing-mode: vertical-rl;
            text-orientation: mixed;
            height: 23.1%;
            line-height: 1.3;
            text-align: center;
            transform: rotate(180deg);
            transform-origin: center;
        }
        
        .unicef-tag {
    top: 40%;
    left: 90%;
    font-size: 18px;
    writing-mode: vertical-rl;
    text-orientation: mixed;
    height: 45%;
    line-height: 1.1;
    text-align: center;
    transform: rotate(180deg);
    transform-origin: center;
    overflow: visible;
    text-overflow: clip;
}
        
        .full-name {
            top: 48%;
            left: 27%;
            font-size: 22px;
            width: 56.8%;
            text-transform: uppercase;
            max-height: 4.6%;
        }
        
        .passport-number {
            top: 55%;
            left: 29%;
            font-size: 22px;
            width: 37.9%;
        }
        
        .authority {
            top: 54%;
            left: 64%;
            font-size: 22px;
            width: 34.1%;
        }
        
        .place-of-issue {
            top: 62%;
            left: 29%;
            font-size: 22px;
            width: 37.9%;
        }
        
        .fees {
            top: 62.3%;
            left: 76.8%;
            font-size: 22px;
            width: 34.1%;
        }
        
        .journey {
            top: 68%;
            left: 37%;
            font-size: 22px;
            width: 60%;
        }
        
        .valid-until {
            top: 76%;
            left: 20%;
            font-size: 22px;
            width: 37.9%;
        }
        
        .date-of-issue {
            top: 82%;
            left: 65%;
            font-size: 22px;
            width: 34.1%;
        }
        
        @media print {
            @page {
                size: 260mm 184mm; /* Force 16K landscape size (260x184mm) */
                margin: 0 !important;
                padding: 0 !important;
            }
            
            html, body {
                margin: 0 !important;
                padding: 0 !important;
                width: 260mm !important;
                height: 184mm !important;
                background-color: transparent !important;
                overflow: hidden !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            
            body {
                display: block !important;
                background: none !important;
            }
            
            .print-area {
                width: 260mm !important;  /* Landscape dimensions */
                height: 184mm !important; /* Landscape dimensions */
                margin: 0 !important;
                padding: 0 !important;
                background-color: transparent !important;
                box-shadow: none !important;
                position: absolute !important;
                top: 0 !important;
                left: 0 !important;
                transform: none !important;
                page-break-inside: avoid !important;
                page-break-after: avoid !important;
                page-break-before: avoid !important;
                break-inside: avoid !important;
                overflow: hidden !important;
            }
            
            .visa-container {
                width: 100% !important;
                height: 100% !important;
                background-size: cover !important; /* Ensure image covers entire area */
                background-position: center center !important; /* Center the image */
                background-repeat: no-repeat !important;
                position: relative !important;
                margin: 0 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                overflow: hidden !important;
            }
            
            /* MODIFIED: Hide visa number in print */
            .visa-number {
                display: none !important;
                visibility: hidden !important;
                opacity: 0 !important;
            }
            
            .no-print {
                display: none !important;
            }
        }
        
        /* Button container for both buttons */
        .button-container {
            position: fixed;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
            z-index: 1000;
        }
        
        /* Style for both buttons */
        .print-button,
        .back-button {
            padding: 10px 20px;
            background-color: #2c3e50;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-family: Arial, sans-serif;
            font-size: 14px;
            transition: background-color 0.3s ease;
        }
        /* Screen Wrapper */
        .card-wrapper {
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            width: 100%;
        }
        
        @media screen and (max-width: 768px) {
            .print-area {
                transform: scale(0.65);
                transform-origin: top center;
            }
        }
        
        @media print {
            body {
                background: transparent !important;
            }
            .card-wrapper {
                margin: 0 !important;
                padding: 0 !important;
                min-height: auto !important;
            }
            .print-area {
                visibility: visible !important;
                opacity: 1 !important;
                display: block !important;
                box-shadow: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="card-wrapper">
        <div class="print-area">
            <div class="visa-container">
                <!-- Category (DIPLOMAT/INT/ORG) -->
                <div class="visa-text category">
                    <?php 
                        if (!empty($category) && $category !== 'NONE') {
                            echo $category; 
                        }
                    ?>
                </div>
                
                <!-- Embassy Field -->
                <div class="visa-text unicef-tag">
                    <?php 
                        if (!empty($embassy)) {
                            echo $embassy; 
                        }
                    ?>
                </div>
                
                <!-- Full Name -->
                <div class="visa-text full-name">
                    <?php echo $fullName; ?>
                </div>
                
                <!-- Passport Number -->
                <div class="visa-text passport-number">
                    <?php echo $passportnumber; ?>
                </div>
                
                <!-- Authority -->
                <div class="visa-text authority" style="font-size: <?php echo $authFontSize; ?>; overflow: visible; text-overflow: clip;">
                    <?php echo $authority; ?>
                </div>
                
                <!-- Place of Issue -->
                <div class="visa-text place-of-issue">
                    <?php echo $place_of_issue; ?>
                </div>
                
                <!-- Fees -->
                <div class="visa-text fees">
                    <?php echo $fees; ?>
                </div>
                
                <!-- Journey -->
                <div class="visa-text journey">
                    <?php echo !empty($journey) ? ($journey . '<span style="display: inline-block; margin-left: 155px;">TO NIGERIA</span>') : ''; ?>
                </div>
                
                <!-- Valid Until -->
                <div class="visa-text valid-until">
                    <?php echo $validuntil; ?>
                </div>
                
                <!-- Date of Issue -->
                <div class="visa-text date-of-issue">
                    <?php echo $date_of_issue; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Trigger browser print dialog immediately
        window.addEventListener('DOMContentLoaded', function() {
            const authEl = document.querySelector('.authority');
            if (authEl) {
                const textLen = authEl.textContent.trim().length;
                if (textLen > 21) {
                    let currentSize = parseFloat(window.getComputedStyle(authEl).fontSize) || 22;
                    while (authEl.scrollWidth > authEl.clientWidth && currentSize > 8) {
                        currentSize -= 0.5;
                        authEl.style.fontSize = currentSize + 'px';
                    }
                }
            }
            setTimeout(function() {
                window.print();
            }, 100);
        });

        // Automatically return to print queue when print dialog finishes or is closed
        window.addEventListener('afterprint', function() {
            window.location.href = 'print_visas';
        });
    </script>
</body>
</html>
<?php
if (isset($stmt)) {
    $stmt->close();
}
mysqli_close($conn);
?>

