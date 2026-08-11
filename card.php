<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once('security.php');

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
    $stmt = $conn->prepare("SELECT * FROM visa_applications WHERE visa_number = ?");
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $retrieve = $stmt->get_result();
    $numb = $retrieve->num_rows;
    
    if ($numb > 0) {
        $foundk = $retrieve->fetch_assoc();
        
        // Assign variables (force uppercase for display)
        $profile = strtoupper(htmlspecialchars($foundk['visa_number']));
        $passportnumber = strtoupper(htmlspecialchars($foundk['passport_number']));
        $surname = strtoupper(htmlspecialchars($foundk['surname']));
        $firstname = strtoupper(htmlspecialchars($foundk['given_name']));
        $fees = strtoupper(htmlspecialchars($foundk['fees']));
        $validuntil = formatNISDate($foundk['within']);
        $journey = strtoupper(htmlspecialchars($foundk['journey']));
        $place_of_issue = strtoupper(htmlspecialchars($foundk['place_of_issue']));
        $date_of_issue = formatNISDate($foundk['date_of_issue']);
        $authority = strtoupper(htmlspecialchars($foundk['authority']));
        $embassy = isset($foundk['embassy']) ? strtoupper(htmlspecialchars($foundk['embassy'])) : '';
        $category = isset($foundk['category']) ? strtoupper(htmlspecialchars($foundk['category'])) : 'NONE';
        

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
    <link rel="stylesheet" href="dist/css/style.css">
    <!--<link href="dist/css/imperator.css" rel="stylesheet">-->
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
            font-family: 'Courier New', Courier, monospace;
        }
        
        .print-area {
            /* Standard visa sticker size in mm converted to pixels (1mm = 3.78px approx) */
            width: 500px;  /* 184mm * 3.78 ≈ 696px */
            height: 400px; /* 260mm * 3.78 ≈ 983px */
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
            font-weight: bold;
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
            font-size: 16px;
            letter-spacing: 1.5px;
            width: 40%;
        }
        
        .category {
            top: 40.8%;
            left: 17.6%;
            font-size: 16px;
            writing-mode: vertical-rl;
            text-orientation: mixed;
            height: 23.1%;
            line-height: 1.3;
            text-align: center;
            transform: rotate(180deg);
            transform-origin: center;
        }
        
        .unicef-tag {
            top: 40.8%;
            left: 80.5%;
            font-size: 16px;
            writing-mode: vertical-rl;
            text-orientation: mixed;
            height: 23.1%;
            line-height: 1.3;
            text-align: center;
            transform: rotate(180deg);
            transform-origin: center;
        }
        
        .full-name {
            top: 38.9%;
            left: 34.5%;
            font-size: 16px;
            width: 56.8%;
            text-transform: uppercase;
            max-height: 4.6%;
        }
        
        .passport-number {
            top: 45.4%;
            left: 34.5%;
            font-size: 16px;
            width: 37.9%;
        }
        
        .authority {
            top: 45.5%;
            left: 56.4%;
            font-size: 16px;
            width: 34.1%;
        }
        
        .place-of-issue {
            top: 53%;
            left: 32.6%;
            font-size: 16px;
            width: 37.9%;
        }
        
        .fees {
            top: 53.3%;
            left: 67.8%;
            font-size: 16px;
            width: 34.1%;
        }
        
        .journey {
            top: 58.9%;
            left: 43.9%;
            font-size: 16px;
            width: 60%;
        }
        
        .valid-until {
            top: 67%;
            left: 30.7%;
            font-size: 16px;
            width: 37.9%;
        }
        
        .date-of-issue {
            top: 74.5%;
            left: 52.6%;
            font-size: 16px;
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
        
        .print-button:hover,
        .back-button:hover {
            background-color: #113f1f;
        }
        
        /* Responsive adjustments for screen view */
        @media screen and (max-width: 600px) {
            body {
                padding: 20px;
            }
            
            .print-area {
                transform: scale(0.8);
                transform-origin: top center;
            }
            
            .button-container {
                flex-direction: column;
                top: 10px;
                right: 10px;
            }
        }
        
        @media screen and (max-width: 400px) {
            .print-area {
                transform: scale(0.6);
            }
        }
        
        /* Hide print preview and button controls on screen */
        @media screen {
            .print-area, .button-container {
                visibility: hidden !important;
                opacity: 0 !important;
                display: none !important;
            }
            body {
                background-color: white !important;
                background: white !important;
            }
        }
        
        @media print {
            .print-area {
                visibility: visible !important;
                opacity: 1 !important;
                display: block !important;
            }
        }
    </style>
</head>
<body>
    <!-- Button container for both buttons -->
    <div class="button-container no-print">
        <button class="print-button" onclick="printWithScaling()">
            Print Visa
        </button>
        <button class="back-button" onclick="window.location.href='print_visas'">
            Back to Dashboard
        </button>
    </div>
    
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
            
            <!-- Embassy Field (Replaces Static UNICEF Tag) -->
            <div class="visa-text unicef-tag">
                <?php 
                    if (!empty($embassy)) {
                        echo $embassy; 
                    }
                ?>
            </div>
            
            <!-- Full Name -->
            <div class="visa-text full-name">
                <?php 
                    $fullName = strtoupper($surname) . ', ' . strtoupper($firstname);
                    echo $fullName;
                ?>
            </div>
            
            <!-- Passport Number -->
            <div class="visa-text passport-number">
                <?php echo $passportnumber; ?>
            </div>
            
            <!-- Authority -->
            <div class="visa-text authority">
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
                <?php echo !empty($journey) ? ($journey . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;TO NIGERIA') : ''; ?>
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

    <script>
        // Always auto-print on page load
        window.onload = function() {
            setTimeout(() => {
                printWithScaling();
            }, 300);
            
            // Go back automatically after printing / cancellation
            window.onafterprint = function() {
                const destination = (document.referrer && document.referrer.includes('print_visas')) ? document.referrer : 'print_visas';
                window.location.href = destination;
            };
        };
        
        // Function to handle printing
        function printWithScaling() {
            // Hide visa number during printing (if it exists)
            const visaNumberElement = document.querySelector('.visa-number');
            let originalVisaNumberStyle = '';
            if (visaNumberElement) {
                originalVisaNumberStyle = visaNumberElement.style.cssText;
                visaNumberElement.style.cssText = `
                    display: none !important;
                    visibility: hidden !important;
                    opacity: 0 !important;
                `;
            }
            
            // Print
            setTimeout(() => {
                window.print();
                
                // Restore original styles
                setTimeout(() => {
                    if (visaNumberElement) {
                        visaNumberElement.style.cssText = originalVisaNumberStyle;
                    }
                    
                    // Fallback redirect if window hasn't transitioned
                    setTimeout(() => {
                        const destination = (document.referrer && document.referrer.includes('print_visas')) ? document.referrer : 'print_visas';
                        window.location.href = destination;
                    }, 500);
                }, 500);
            }, 100);
        }
    </script>
</body>
</html>
<?php
// Close database connection
if (isset($stmt)) {
    $stmt->close();
}
mysqli_close($conn);
?>

