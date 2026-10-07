<?php
/**
 * Personal Storage - SMTP Diagnostic CLI Tool
 */

require_once dirname(__DIR__) . '/app/bootstrap.php';

echo "====================================\n";
echo "Personal Storage - SMTP Diagnostic\n";
echo "====================================\n\n";

$host = Config::get('SMTP_HOST');
$port = Config::get('SMTP_PORT');
$user = Config::get('SMTP_USERNAME');
$from = Config::get('SMTP_FROM_EMAIL');

echo "Host:     {$host}:{$port}\n";
echo "Username: {$user}\n";
echo "From:     {$from}\n\n";

echo "Enter destination email to receive test OTP: ";
$to = trim(fgets(STDIN));

if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    echo "\n❌ Invalid email address.\n";
    exit(1);
}

$otp = (string)random_int(10000, 99999);
echo "\nSending OTP: {$otp} to {$to}...\n";

$mailer = new Mailer();
$success = $mailer->sendOTP($to, 'Test Recipient', $otp, 'verification');

echo "\n--- SMTP Execution Log ---\n";
foreach ($mailer->getLog() as $entry) {
    echo "  " . $entry . "\n";
}
echo "--------------------------\n\n";

if ($success) {
    echo "✅ SUCCESS! Check your inbox (and Spam folder) at: {$to}\n\n";
} else {
    echo "❌ FAILED! Email could not be sent.\n\n";
}