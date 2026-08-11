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
            width: 104mm;
            height: 73mm;
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
            background-size: cover;
            background-repeat: no-repeat;
            background-position: center center;
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
        
        .category {
            top: 30mm;
            left: 8mm;
            font-size: 3.2mm;
            writing-mode: vertical-rl;
            text-orientation: mixed;
            height: 18mm;
            line-height: 1.3;
            text-align: center;
            transform: rotate(180deg);
            transform-origin: center;
        }
        
        .unicef-tag {
            top: 30mm;
            left: 95mm;
            font-size: 3.2mm;
            writing-mode: vertical-rl;
            text-orientation: mixed;
            height: 18mm;
            line-height: 1.3;
            text-align: center;
            transform: rotate(180deg);
            transform-origin: center;
        }
        
        .full-name {
            top: 37mm;
            left: 27mm;
            font-size: 3.2mm;
            width: 60mm;
            text-transform: uppercase;
        }
        
        .passport-number {
            top: 42.5mm;
            left: 27mm;
            font-size: 3.2mm;
            width: 32mm;
        }
        
        .authority {
            top: 42.5mm;
            left: 65mm;
            font-size: 3.2mm;
            width: 32mm;
        }
        
        .place-of-issue {
            top: 48mm;
            left: 27mm;
            font-size: 3.2mm;
            width: 32mm;
        }
        
        .fees {
            top: 48mm;
            left: 77mm;
            font-size: 3.2mm;
            width: 20mm;
        }
        
        .journey {
            top: 54mm;
            left: 36mm;
            font-size: 3.2mm;
            width: 60mm;
        }
        
        .valid-until {
            top: 59.5mm;
            left: 27mm;
            font-size: 3.2mm;
            width: 32mm;
        }
        
        .date-of-issue {
            top: 59.5mm;
            left: 62mm;
            font-size: 3.2mm;
            width: 32mm;
        }
        
        @media print {
            @page {
                margin: 0 !important;
                padding: 0 !important;
                size: 104mm 73mm !important;
            }
            
            html, body {
                margin: 0 !important;
                padding: 0 !important;
                width: 104mm !important;
                height: 73mm !important;
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
                width: 104mm !important;
                height: 73mm !important;
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
                width: 104mm !important;
                height: 73mm !important;
                background-size: cover !important;
                background-position: center center !important;
                background-repeat: no-repeat !important;
                position: relative !important;
                margin: 0 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                overflow: hidden !important;
            }
            
            .visa-container.hide-bg-print {
                background-image: none !important;
                background: transparent !important;
            }
            
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
    </style>
</head>
<body>
    <!-- Button container for both buttons and background print toggle -->
    <div class="button-container no-print" style="align-items: center;">
        <label style="color: white; display: inline-flex; align-items: center; gap: 6px; font-family: Arial, sans-serif; font-size: 13px; cursor: pointer; background: #2c3e50; padding: 10px 15px; border-radius: 5px; height: 41px;">
            <input type="checkbox" id="toggleBgPrint" checked onchange="toggleBackgroundPrint(this)" style="cursor: pointer; transform: scale(1.15);"> Print Background
        </label>
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
        // Auto-print only if needed
        <?php if (isset($_GET['autoprint'])): ?>
        window.onload = function() {
            setTimeout(() => {
                printWithScaling();
            }, 500);
        }
        <?php endif; ?>
        
        // Function to toggle background visibility on/off dynamically
        function toggleBackgroundPrint(checkbox) {
            const container = document.querySelector('.visa-container');
            if (checkbox.checked) {
                container.classList.remove('hide-bg-print');
            } else {
                container.classList.add('hide-bg-print');
            }
        }
        
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
                width: 104mm !important;
                height: 73mm !important;
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
            
            const bgPrintCheckbox = document.getElementById('toggleBgPrint');
            const bgImage = (bgPrintCheckbox && !bgPrintCheckbox.checked) ? 'none !important' : 'url("assets/images/visa_vignette.jpg") !important';
            
            const visaContainer = document.querySelector('.visa-container');
            visaContainer.style.cssText = `
                width: 100% !important;
                height: 100% !important;
                background-image: ${bgImage};
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
                
                // Restore original styles after print
                setTimeout(() => {
                    document.body.style.cssText = originalBodyStyle;
                    printArea.style.cssText = originalPrintAreaStyle;
                    visaContainer.style.cssText = originalVisaContainerStyle;
                    if (visaNumberElement) {
                        visaNumberElement.style.cssText = originalVisaNumberStyle;
                    }
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

