<?php
/**
 * NISREV Mailer Utility
 * Handles official Nigeria Immigration Service email dispatches
 * Supports SMTP (TLS/SSL) and native PHP mail() fallback with audit logging.
 */

if (!defined('SMTP_HOST')) define('SMTP_HOST', 'localhost');
if (!defined('SMTP_PORT')) define('SMTP_PORT', 587);
if (!defined('SMTP_USER')) define('SMTP_USER', '');
if (!defined('SMTP_PASS')) define('SMTP_PASS', '');
if (!defined('SMTP_ENCRYPTION')) define('SMTP_ENCRYPTION', 'tls'); // 'tls', 'ssl', or 'none'
if (!defined('MAIL_FROM_ADDRESS')) define('MAIL_FROM_ADDRESS', 'noreply@nis.gov.ng');
if (!defined('MAIL_FROM_NAME')) define('MAIL_FROM_NAME', 'Nigeria Immigration Service (NISREV)');

class NISMailer {

    /**
     * Resolves base application portal URL
     */
    public static function getPortalUrl() {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        if (isset($_SERVER['SCRIPT_NAME']) && !empty($_SERVER['SCRIPT_NAME']) && $_SERVER['SCRIPT_NAME'] !== '.') {
            $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
            $scriptDir = str_replace('\\', '/', $scriptDir);
            $scriptDir = trim($scriptDir, '/.');
            $portalPath = empty($scriptDir) ? '/' : '/' . $scriptDir . '/';
        } else {
            $portalPath = '/rentryvisa.nisapps.com.ng/';
        }
        
        return rtrim($protocol . $host, '/') . $portalPath;
    }

    /**
     * Send Welcome Email to newly created user
     *
     * @param mysqli $conn Database connection for activity logging
     * @param string $email User's email address
     * @param string $fullName User's full name
     * @param string $serviceNo User's service number
     * @param string $defaultPassword Auto-generated default password
     * @param string $role Assigned user role
     * @param string|null $portalUrl Optional explicit portal URL
     * @return array ['success' => bool, 'message' => string]
     */
    public static function sendWelcomeEmail($conn, $email, $fullName, $serviceNo, $defaultPassword, $role, $portalUrl = null) {
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email address provided.'];
        }

        $portalUrl = $portalUrl ?: self::getPortalUrl();
        $safeName = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
        $safeServiceNo = htmlspecialchars($serviceNo, ENT_QUOTES, 'UTF-8');
        $safeRole = htmlspecialchars($role, ENT_QUOTES, 'UTF-8');
        $safePassword = htmlspecialchars($defaultPassword, ENT_QUOTES, 'UTF-8');
        $safePortalUrl = htmlspecialchars($portalUrl, ENT_QUOTES, 'UTF-8');

        $subject = "NIS Re-Entry Visa Portal - Account Created (Service No: {$serviceNo})";

        // Generate HTML Body
        $htmlBody = self::buildWelcomeEmailHtml($safeName, $safeServiceNo, $safePassword, $safeRole, $safePortalUrl);

        // Generate Plain Text Version
        $plainText = "NIGERIA IMMIGRATION SERVICE\n";
        $plainText .= "Re-Entry Visa Management System (NISREV)\n\n";
        $plainText .= "Dear {$fullName},\n\n";
        $plainText .= "An official user account has been created for you on the NIS Re-Entry Visa Portal.\n\n";
        $plainText .= "YOUR LOGIN CREDENTIALS:\n";
        $plainText .= "----------------------------------------\n";
        $plainText .= "Username:       {$serviceNo}  (NOTE: Use your Service Number as username, NOT your email)\n";
        $plainText .= "Default Password: {$defaultPassword}\n";
        $plainText .= "Assigned Role:  {$role}\n";
        $plainText .= "Portal URL:     {$portalUrl}\n";
        $plainText .= "----------------------------------------\n\n";
        $plainText .= "FIRST-TIME LOGIN INSTRUCTIONS:\n";
        $plainText .= "1. Go to the portal URL: {$portalUrl}\n";
        $plainText .= "2. Sign in using your Service Number ({$serviceNo}) and Default Password ({$defaultPassword}).\n";
        $plainText .= "3. Set up Two-Factor Authentication (2FA) by scanning the Google Authenticator barcode on your mobile device.\n";
        $plainText .= "4. You will be prompted immediately to change your default password to a new secure personal password.\n\n";
        $plainText .= "SECURITY ADVISORY:\n";
        $plainText .= "Keep this information strictly confidential. Never share your password with anyone.\n\n";
        $plainText .= "--\nNigeria Immigration Service\nAutomated Notification System";

        $dispatch = self::sendMail($email, $fullName, $subject, $htmlBody, $plainText);

        // Audit Log
        $logAction = $dispatch['success'] ? "EMAIL_WELCOME_SENT" : "EMAIL_WELCOME_FAILED";
        $logDesc = "Welcome email dispatch for {$serviceNo} ({$email}): " . ($dispatch['success'] ? "Successful" : $dispatch['message']);
        self::logEmailActivity($conn, $serviceNo, $logAction, $logDesc);

        return $dispatch;
    }

    /**
     * Send Password Reset Notification Email
     */
    public static function sendPasswordResetNotification($conn, $email, $fullName, $serviceNo, $defaultPassword, $portalUrl = null) {
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email address provided.'];
        }

        $portalUrl = $portalUrl ?: self::getPortalUrl();
        $safeName = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
        $safeServiceNo = htmlspecialchars($serviceNo, ENT_QUOTES, 'UTF-8');
        $safePassword = htmlspecialchars($defaultPassword, ENT_QUOTES, 'UTF-8');
        $safePortalUrl = htmlspecialchars($portalUrl, ENT_QUOTES, 'UTF-8');

        $subject = "NIS Re-Entry Visa Portal - Password Reset Notification (Service No: {$serviceNo})";

        $htmlBody = self::buildPasswordResetEmailHtml($safeName, $safeServiceNo, $safePassword, $safePortalUrl);

        $plainText = "NIGERIA IMMIGRATION SERVICE\n";
        $plainText .= "Re-Entry Visa Management System (NISREV)\n\n";
        $plainText .= "Dear {$fullName},\n\n";
        $plainText .= "Your account password for the NIS Re-Entry Visa Portal has been reset by an Administrator.\n\n";
        $plainText .= "YOUR LOGIN DETAILS:\n";
        $plainText .= "----------------------------------------\n";
        $plainText .= "Username:       {$serviceNo}  (Always use your Service Number)\n";
        $plainText .= "Default Password: {$defaultPassword}\n";
        $plainText .= "Portal URL:     {$portalUrl}\n";
        $plainText .= "----------------------------------------\n\n";
        $plainText .= "Please log in using your Service Number and this default password. You will be prompted to set your new personal password immediately upon login.\n\n";
        $plainText .= "--\nNigeria Immigration Service";

        $dispatch = self::sendMail($email, $fullName, $subject, $htmlBody, $plainText);

        $logAction = $dispatch['success'] ? "EMAIL_RESET_SENT" : "EMAIL_RESET_FAILED";
        $logDesc = "Password reset notification email dispatch for {$serviceNo} ({$email}): " . ($dispatch['success'] ? "Successful" : $dispatch['message']);
        self::logEmailActivity($conn, $serviceNo, $logAction, $logDesc);

        return $dispatch;
    }

    /**
     * Test SMTP configuration and connection
     */
    public static function testSmtpConnection($toEmail, $host, $port, $username, $password, $encryption, $fromEmail, $fromName) {
        $subject = "NIS Re-Entry Visa Portal - SMTP Configuration Test";
        $htmlBody = "<div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #1a5c2e; border-radius: 8px; max-width: 500px;'>
            <h3 style='color: #1a5c2e; margin-top: 0;'>✓ SMTP Connection Successful!</h3>
            <p>This is a verification test email from the <strong>Nigeria Immigration Service Re-Entry Visa Portal (NISREV)</strong>.</p>
            <p>Your SMTP server settings are correctly configured and live emails can now be dispatched to users.</p>
            <hr style='border: none; border-top: 1px solid #e2e8f0;'>
            <small style='color: #64748b;'>Tested at: " . date('Y-m-d H:i:s') . " (Server Time)</small>
        </div>";
        $plainText = "NIS Re-Entry Visa Portal - SMTP Test\n\nSMTP Connection Successful! Your NISREV email engine is functioning properly.\nTested at: " . date('Y-m-d H:i:s');
        
        return self::sendSocketSMTP($toEmail, 'NIS Administrator', $subject, $htmlBody, $host, $port, $username, $password, $encryption, $fromEmail, $fromName);
    }

    /**
     * Helper to read RFC 5321 compliant multi-line SMTP responses
     */
    private static function readSmtpResponse($socket) {
        $response = "";
        while (!feof($socket)) {
            $line = fgets($socket, 1024);
            if ($line === false) break;
            $response .= $line;
            // In SMTP, a multi-line response line has a hyphen after the 3-digit code (e.g. "250-").
            // The final line of the response has a space after the 3-digit code (e.g. "250 ").
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
            if (strlen(trim($line)) === 3) {
                break;
            }
        }
        return $response;
    }

    /**
     * Dispatch email via SMTP or native mail()
     */
    public static function sendMail($toEmail, $toName, $subject, $htmlBody, $plainText = '', $customConfig = []) {
        $fromEmail = !empty($customConfig['from_address']) ? $customConfig['from_address'] : (defined('MAIL_FROM_ADDRESS') ? MAIL_FROM_ADDRESS : 'noreply@nisapps.com.ng');
        $fromName = !empty($customConfig['from_name']) ? $customConfig['from_name'] : (defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Nigeria Immigration Service (NISREV)');

        $smtpHost = !empty($customConfig['smtp_host']) ? $customConfig['smtp_host'] : (defined('SMTP_HOST') ? SMTP_HOST : 'mail.nisapps.com.ng');
        $smtpPort = !empty($customConfig['smtp_port']) ? intval($customConfig['smtp_port']) : (defined('SMTP_PORT') ? intval(SMTP_PORT) : 587);
        $smtpUser = isset($customConfig['smtp_user']) ? $customConfig['smtp_user'] : (defined('SMTP_USER') ? SMTP_USER : '');
        $smtpPass = isset($customConfig['smtp_pass']) ? $customConfig['smtp_pass'] : (defined('SMTP_PASS') ? SMTP_PASS : '');
        $smtpEncryption = !empty($customConfig['smtp_encryption']) ? strtolower($customConfig['smtp_encryption']) : (defined('SMTP_ENCRYPTION') ? strtolower(SMTP_ENCRYPTION) : 'tls');

        // 1. Attempt Socket SMTP if host is provided
        if (!empty($smtpHost) && $smtpHost !== 'localhost') {
            if (!empty($smtpUser) && !empty($smtpPass)) {
                $smtpRes = self::sendSocketSMTP($toEmail, $toName, $subject, $htmlBody, $smtpHost, $smtpPort, $smtpUser, $smtpPass, $smtpEncryption, $fromEmail, $fromName);
                if ($smtpRes['success']) {
                    return $smtpRes;
                } else {
                    // SMTP failed with explicit error
                    return $smtpRes;
                }
            } else {
                // Host provided but missing credentials
                return [
                    'success' => false, 
                    'message' => "SMTP credentials (username/password) are not configured. Please set your SMTP credentials in Email Settings to dispatch real emails to {$toEmail}."
                ];
            }
        }

        // 2. Native PHP mail() with robust MIME encoding (Fallback)
        try {
            $boundary = "==Multipart_Boundary_x" . md5(time()) . "x";

            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "From: {$fromName} <{$fromEmail}>\r\n";
            $headers .= "Reply-To: {$fromEmail}\r\n";
            $headers .= "X-Mailer: NISREV Portal Mailer\r\n";
            $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";

            $message = "--{$boundary}\r\n";
            $message .= "Content-Type: text/plain; charset=\"UTF-8\"\r\n";
            $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
            $message .= $plainText . "\r\n\r\n";

            $message .= "--{$boundary}\r\n";
            $message .= "Content-Type: text/html; charset=\"UTF-8\"\r\n";
            $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
            $message .= $htmlBody . "\r\n\r\n";
            $message .= "--{$boundary}--";

            $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
            $sent = @mail($toEmail, $encodedSubject, $message, $headers);

            if ($sent) {
                return ['success' => true, 'message' => 'Email dispatched successfully via PHP mail().'];
            } else {
                return ['success' => false, 'message' => 'Native PHP mail() delivery failed. Please configure live SMTP credentials.'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Mail delivery error: ' . $e->getMessage()];
        }
    }

    /**
     * Direct RFC 5321 Socket SMTP Engine with Multi-Line Response Support
     */
    public static function sendSocketSMTP($toEmail, $toName, $subject, $htmlBody, $host, $port, $username, $password, $encryption, $fromEmail, $fromName) {
        $encryption = strtolower($encryption);
        $socketHost = ($encryption === 'ssl' ? 'ssl://' : '') . $host;
        $timeout = 15;
        
        $socket = @fsockopen($socketHost, $port, $errno, $errstr, $timeout);
        if (!$socket) {
            return ['success' => false, 'message' => "SMTP Connection to {$host}:{$port} failed: {$errstr} ({$errno})"];
        }

        stream_set_timeout($socket, $timeout);
        $greeting = self::readSmtpResponse($socket);
        if (substr($greeting, 0, 3) !== '220') {
            fclose($socket);
            return ['success' => false, 'message' => "SMTP Greeting rejected: " . trim($greeting)];
        }

        $clientHost = $_SERVER['SERVER_NAME'] ?? 'nisapps.com.ng';
        fputs($socket, "EHLO {$clientHost}\r\n");
        $ehloRes = self::readSmtpResponse($socket);

        // STARTTLS Negotiation
        if ($encryption === 'tls') {
            fputs($socket, "STARTTLS\r\n");
            $tlsRes = self::readSmtpResponse($socket);
            if (substr($tlsRes, 0, 3) == '220') {
                $cryptoMethod = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                    $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
                }
                if (!stream_socket_enable_crypto($socket, true, $cryptoMethod)) {
                    fclose($socket);
                    return ['success' => false, 'message' => 'TLS encryption handshake failed with mail server.'];
                }
                // Re-send EHLO over secure TLS socket
                fputs($socket, "EHLO {$clientHost}\r\n");
                $ehloRes = self::readSmtpResponse($socket);
            } else {
                fclose($socket);
                return ['success' => false, 'message' => "STARTTLS rejected by server: " . trim($tlsRes)];
            }
        }

        // Authentication
        if (!empty($username) && !empty($password)) {
            fputs($socket, "AUTH LOGIN\r\n");
            $authInit = self::readSmtpResponse($socket);
            if (substr($authInit, 0, 3) !== '334') {
                fclose($socket);
                return ['success' => false, 'message' => "AUTH LOGIN not accepted: " . trim($authInit)];
            }

            fputs($socket, base64_encode($username) . "\r\n");
            $userRes = self::readSmtpResponse($socket);
            if (substr($userRes, 0, 3) !== '334') {
                fclose($socket);
                return ['success' => false, 'message' => "SMTP Username rejected: " . trim($userRes)];
            }

            fputs($socket, base64_encode($password) . "\r\n");
            $authRes = self::readSmtpResponse($socket);

            if (substr($authRes, 0, 3) !== '235') {
                fclose($socket);
                return ['success' => false, 'message' => "SMTP Authentication failed: " . trim($authRes)];
            }
        }

        // Sender Envelope
        fputs($socket, "MAIL FROM: <{$fromEmail}>\r\n");
        $mailRes = self::readSmtpResponse($socket);
        if (substr($mailRes, 0, 3) !== '250') {
            fclose($socket);
            return ['success' => false, 'message' => "Sender <{$fromEmail}> rejected: " . trim($mailRes)];
        }

        // Recipient Envelope
        fputs($socket, "RCPT TO: <{$toEmail}>\r\n");
        $rcptRes = self::readSmtpResponse($socket);
        if (substr($rcptRes, 0, 3) !== '250') {
            fclose($socket);
            return ['success' => false, 'message' => "Recipient <{$toEmail}> rejected: " . trim($rcptRes)];
        }

        // Data Initiation
        fputs($socket, "DATA\r\n");
        $dataInit = self::readSmtpResponse($socket);
        if (substr($dataInit, 0, 3) !== '354') {
            fclose($socket);
            return ['success' => false, 'message' => "DATA command rejected: " . trim($dataInit)];
        }

        // Message Headers & Body
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $encodedTo = !empty($toName) ? '=?UTF-8?B?' . base64_encode($toName) . '?= <' . $toEmail . '>' : '<' . $toEmail . '>';
        $encodedFrom = '=?UTF-8?B?' . base64_encode($fromName) . '?= <' . $fromEmail . '>';

        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: {$encodedFrom}\r\n";
        $headers .= "To: {$encodedTo}\r\n";
        $headers .= "Reply-To: {$fromEmail}\r\n";
        $headers .= "Subject: {$encodedSubject}\r\n";
        $headers .= "Date: " . date('r') . "\r\n";
        $headers .= "X-Mailer: NISREV Portal Automated Mailer\r\n\r\n";

        // Dot termination
        fputs($socket, $headers . $htmlBody . "\r\n.\r\n");
        $dataRes = self::readSmtpResponse($socket);

        fputs($socket, "QUIT\r\n");
        fclose($socket);

        if (substr($dataRes, 0, 3) === '250') {
            return ['success' => true, 'message' => 'Email sent successfully via SMTP.'];
        }

        return ['success' => false, 'message' => "SMTP DATA transmission rejected: " . trim($dataRes)];
    }

    /**
     * Helper to write audit trail record
     */
    private static function logEmailActivity($conn, $serviceNo, $action, $description) {
        if (!$conn) return;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'NISREV System';
        $currentAdmin = $_SESSION['username'] ?? 'SYSTEM';

        $stmt = mysqli_prepare($conn, "INSERT INTO activity_log (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "sssss", $currentAdmin, $action, $description, $ip, $userAgent);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }

    /**
     * Builds standard Welcome Email HTML
     */
    private static function buildWelcomeEmailHtml($name, $serviceNo, $password, $role, $portalUrl) {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Created - NIS Re-Entry Visa Portal</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f1f5f9; font-family: 'Segoe UI', Arial, Helvetica, sans-serif; -webkit-font-smoothing: antialiased;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #f1f5f9; padding: 30px 10px;">
        <tr>
            <td align="center">
                <!-- Main Container -->
                <table role="presentation" width="100%" max-width="620" cellspacing="0" cellpadding="0" border="0" style="max-width: 620px; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08); border: 1px solid #e2e8f0;">
                    
                    <!-- Header Banner -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #1a5c2e, #113f1f); padding: 30px 25px; text-align: center; color: #ffffff;">
                            <div style="font-size: 13px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #86efac; margin-bottom: 6px;">
                                NIGERIA IMMIGRATION SERVICE
                            </div>
                            <div style="font-size: 22px; font-weight: 700; color: #ffffff; letter-spacing: 0.5px; margin: 0;">
                                Re-Entry Visa Management System
                            </div>
                            <div style="font-size: 12px; color: #e2e8f0; margin-top: 4px; opacity: 0.9;">
                                NISREV Portal User Access
                            </div>
                        </td>
                    </tr>

                    <!-- Body Content -->
                    <tr>
                        <td style="padding: 30px 28px; color: #334155; font-size: 14.5px; line-height: 1.6;">
                            
                            <p style="margin-top: 0; font-size: 16px; color: #0f172a;">
                                Dear <strong>{$name}</strong>,
                            </p>

                            <p style="margin-bottom: 22px;">
                                An official user account has been successfully created for you on the <strong>Nigeria Immigration Service Re-Entry Visa Portal (NISREV)</strong>.
                            </p>

                            <!-- Credentials Box -->
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: 10px; margin-bottom: 24px; overflow: hidden;">
                                <tr>
                                    <td style="background-color: #1a5c2e; color: #ffffff; padding: 10px 18px; font-size: 13px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase;">
                                        Your Login Credentials
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 18px 20px;">
                                        
                                        <!-- Service No as Username Callout -->
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-bottom: 12px;">
                                            <tr>
                                                <td width="35%" style="font-size: 13px; color: #64748b; font-weight: 600; vertical-align: top; padding: 6px 0;">Username:</td>
                                                <td width="65%" style="font-size: 16px; color: #1a5c2e; font-weight: 700; font-family: monospace; padding: 6px 0;">
                                                    {$serviceNo}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td colspan="2" style="padding-bottom: 12px;">
                                                    <div style="background-color: #fef3c7; border-left: 3px solid #d97706; padding: 6px 12px; border-radius: 4px; font-size: 12px; color: #92400e; font-weight: 600;">
                                                        &#9888; IMPORTANT: Use your <u>Service Number</u> ({$serviceNo}) as your username to log in, NOT your email.
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="font-size: 13px; color: #64748b; font-weight: 600; padding: 6px 0; border-top: 1px dashed #e2e8f0;">Default Password:</td>
                                                <td style="font-size: 15px; color: #0f172a; font-weight: 700; font-family: monospace; padding: 6px 0; border-top: 1px dashed #e2e8f0;">
                                                    <span style="background: #e2e8f0; padding: 3px 8px; border-radius: 4px; letter-spacing: 0.5px;">{$password}</span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="font-size: 13px; color: #64748b; font-weight: 600; padding: 6px 0; border-top: 1px dashed #e2e8f0;">Assigned Role:</td>
                                                <td style="font-size: 14px; color: #334155; font-weight: 600; padding: 6px 0; border-top: 1px dashed #e2e8f0;">
                                                    {$role}
                                                </td>
                                            </tr>
                                        </table>

                                    </td>
                                </tr>
                            </table>

                            <!-- Step by Step First-Time Login Instructions -->
                            <div style="background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 18px 20px; margin-bottom: 25px;">
                                <div style="font-size: 14px; font-weight: 700; color: #166534; margin-bottom: 10px;">
                                    &#128274; First-Time Login & Security Setup:
                                </div>
                                <ol style="margin: 0; padding-left: 20px; color: #1e293b; font-size: 13.5px; line-height: 1.65;">
                                    <li style="margin-bottom: 6px;">
                                        <strong>Access Portal:</strong> Click the access button below to open the login page.
                                    </li>
                                    <li style="margin-bottom: 6px;">
                                        <strong>Sign In:</strong> Enter your <strong>Service Number</strong> (<code style="color: #1a5c2e;">{$serviceNo}</code>) and your default password (<code style="color: #1a5c2e;">{$password}</code>).
                                    </li>
                                    <li style="margin-bottom: 6px;">
                                        <strong>Configure 2FA:</strong> Scan the Google Authenticator barcode on your screen using the <em>Google Authenticator</em> or <em>Microsoft Authenticator</em> mobile app and enter the 6-digit code.
                                    </li>
                                    <li>
                                        <strong>Set Personal Password:</strong> You will be prompted to set your new private password immediately to replace the temporary default password.
                                    </li>
                                </ol>
                            </div>

                            <!-- Login CTA Button -->
                            <div style="text-align: center; margin: 30px 0 25px 0;">
                                <a href="{$portalUrl}" target="_blank" style="display: inline-block; background: linear-gradient(135deg, #1a5c2e, #27ae60); color: #ffffff; text-decoration: none; padding: 13px 34px; font-size: 15px; font-weight: 700; border-radius: 8px; box-shadow: 0 4px 12px rgba(26, 92, 46, 0.25); letter-spacing: 0.3px;">
                                    Log In to NISREV Portal &rarr;
                                </a>
                            </div>

                            <p style="font-size: 12px; color: #64748b; text-align: center; margin-bottom: 25px;">
                                Or copy and paste this link in your browser: <br>
                                <a href="{$portalUrl}" style="color: #1a5c2e; word-break: break-all;">{$portalUrl}</a>
                            </p>

                            <!-- Security Advisory Footer Box -->
                            <div style="border-top: 1px solid #e2e8f0; padding-top: 18px; font-size: 12px; color: #64748b; line-height: 1.5;">
                                <strong style="color: #475569;">Security Advisory:</strong>
                                This email contains confidential access information. Do not share your login credentials or authentication codes with anyone. Nigeria Immigration Service ICT will never request your password.
                            </div>

                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8fafc; padding: 18px 25px; text-align: center; border-top: 1px solid #e2e8f0; color: #94a3b8; font-size: 11.5px;">
                            <div style="font-weight: 600; color: #64748b; margin-bottom: 3px;">
                                &copy; 2026 Nigeria Immigration Service. All Rights Reserved.
                            </div>
                            <div>
                                NIS Headquarters, Airport Road, Sauka, Abuja, Nigeria.
                            </div>
                            <div style="margin-top: 4px; font-size: 11px;">
                                This is an automated system dispatch. Please do not reply directly to this email.
                            </div>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    /**
     * Builds Password Reset Email HTML
     */
    private static function buildPasswordResetEmailHtml($name, $serviceNo, $password, $portalUrl) {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset - NIS Re-Entry Visa Portal</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f1f5f9; font-family: 'Segoe UI', Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #f1f5f9; padding: 30px 10px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" max-width="620" cellspacing="0" cellpadding="0" border="0" style="max-width: 620px; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 16px rgba(0,0,0,0.08); border: 1px solid #e2e8f0;">
                    <tr>
                        <td style="background: linear-gradient(135deg, #1a5c2e, #113f1f); padding: 25px; text-align: center; color: #ffffff;">
                            <div style="font-size: 12px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #86efac; margin-bottom: 4px;">NIGERIA IMMIGRATION SERVICE</div>
                            <div style="font-size: 20px; font-weight: 700; color: #ffffff;">Password Reset Notification</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 28px; color: #334155; font-size: 14.5px; line-height: 1.6;">
                            <p style="margin-top: 0; font-size: 16px; color: #0f172a;">Dear <strong>{$name}</strong>,</p>
                            <p>Your password for the <strong>NIS Re-Entry Visa Portal</strong> has been reset by an authorized Administrator.</p>
                            
                            <div style="background-color: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: 8px; padding: 16px 20px; margin: 20px 0;">
                                <div style="margin-bottom: 8px; font-size: 13px; color: #64748b;">Username: <strong style="color: #1a5c2e; font-family: monospace; font-size: 15px;">{$serviceNo}</strong> <span style="font-size: 11px; color: #92400e;">(Use your Service No)</span></div>
                                <div style="font-size: 13px; color: #64748b;">New Temporary Password: <strong style="color: #0f172a; font-family: monospace; font-size: 15px; background: #e2e8f0; padding: 2px 6px; border-radius: 4px;">{$password}</strong></div>
                            </div>

                            <p style="font-size: 13.5px; color: #475569;">
                                Please log in using your <strong>Service Number</strong> and this temporary password. You will be required to set your own new personal password immediately upon signing in.
                            </p>

                            <div style="text-align: center; margin: 25px 0;">
                                <a href="{$portalUrl}" target="_blank" style="display: inline-block; background: #1a5c2e; color: #ffffff; text-decoration: none; padding: 12px 30px; font-size: 14.5px; font-weight: 700; border-radius: 6px;">Log In to NISREV Portal &rarr;</a>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #f8fafc; padding: 16px; text-align: center; border-top: 1px solid #e2e8f0; color: #94a3b8; font-size: 11.5px;">
                            &copy; 2026 Nigeria Immigration Service. Automated Notification.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }
}
