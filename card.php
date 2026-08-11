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
            width: 983px;  /* 184mm * 3.78 ≈ 696px */
            height: 696px; /* 260mm * 3.78 ≈ 983px */
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
            font-size: 19px;
            letter-spacing: 1.5px;
            width: 40%;
        }
        
        .category {
            top: 40.8%;
            left: 7.6%;
            font-size: 19px;
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
            left: 90.5%;
            font-size: 19px;
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
            left: 26.5%;
            font-size: 19px;
            width: 56.8%;
            text-transform: uppercase;
            max-height: 4.6%;
        }
        
        .passport-number {
            top: 45.4%;
            left: 26.5%;
            font-size: 19px;
            width: 37.9%;
        }
        
        .authority {
            top: 45.5%;
            left: 64.4%;
            font-size: 19px;
            width: 34.1%;
        }
        
        .place-of-issue {
            top: 53%;
            left: 24.6%;
            font-size: 19px;
            width: 37.9%;
        }
        
        .fees {
            top: 53.3%;
            left: 75.8%;
            font-size: 19px;
            width: 34.1%;
        }
        
        .journey {
            top: 58.9%;
            left: 35.9%;
            font-size: 19px;
            width: 60%;
        }
        
        .valid-until {
            top: 67%;
            left: 22.7%;
            font-size: 19px;
            width: 37.9%;
        }
        
        .date-of-issue {
            top: 74.5%;
            left: 60.6%;
            font-size: 19px;
            width: 34.1%;
        }
        
        @media print {
            @page {
                margin: 0 !important;
                padding: 0 !important;
                size: auto; /* Set exact page size */
            }
            
            html, body {
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                height: 100% !important;
                background-color: transparent !important;
                overflow: hidden !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            
            body {
                display: flex !important;
                justify-content: center !important;
                align-items: center !important;
                min-height: 100vh !important;
                background: none !important;
            }
            
            .print-area {
                width: 260mm !important;  /* Exact print dimensions */
                height: 184mm !important; /* Exact print dimensions */
                margin: 0 auto !important;
                padding: 0 !important;
                background-color: transparent !important;
                box-shadow: none !important;
                position: relative !important;
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
            background-color: #0f172a;
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

            
            <!-- Category (DIPLOMAT) -->
            <div class="visa-text category">
                <?php 
                    if (!empty($embassy) && $embassy !== 'NONE') {
                        echo $embassy; 
                    }
                ?>
            </div>
            
            <!-- Static UNICEF Tag -->
            <div class="visa-text unicef-tag">
                UNICEF
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
                <?php echo !empty($journey) ? ($journey . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;TO NIGERIA') : ''; ?>
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
        
        // Function to handle printing with better scaling
        function printWithScaling() {
            // Store original styles
            const originalBodyStyle = document.body.style.cssText;
            const originalPrintAreaStyle = document.querySelector('.print-area').style.cssText;
            const originalVisaContainerStyle = document.querySelector('.visa-container').style.cssText;
            
            // Apply print styles
            document.body.style.cssText = `
                margin: 0 !important;
                padding: 0 !important;
                background-color: transparent !important;
                display: flex !important;
                justify-content: center !important;
                align-items: center !important;
                min-height: 100vh !important;
                overflow: hidden !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            `;
            
            // Set exact print dimensions
            const printArea = document.querySelector('.print-area');
            printArea.style.cssText = `
                width: 260mm !important;
                height: 184mm !important;
                margin: 0 auto !important;
                padding: 0 !important;
                background-color: transparent !important;
                box-shadow: none !important;
                position: relative !important;
                page-break-inside: avoid !important;
                page-break-after: avoid !important;
                page-break-before: avoid !important;
                overflow: hidden !important;
            `;
            
            const visaContainer = document.querySelector('.visa-container');
            visaContainer.style.cssText = `
                width: 100% !important;
                height: 100% !important;
                background-size: cover !important;
                background-position: center center !important;
                background-repeat: no-repeat !important;
                position: relative !important;
                margin: 0 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                overflow: hidden !important;
            `;
            
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
            
            // Force single page by setting page breaks
            document.body.style.pageBreakInside = 'avoid';
            document.body.style.pageBreakAfter = 'avoid';
            document.body.style.pageBreakBefore = 'avoid';
            
            // Print
            setTimeout(() => {
                window.print();
                
                // Restore original styles and execute fallback redirect
                setTimeout(() => {
                    document.body.style.cssText = originalBodyStyle;
                    printArea.style.cssText = originalPrintAreaStyle;
                    visaContainer.style.cssText = originalVisaContainerStyle;
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

