<?php
/**
 * Personal Storage - SMTP Mail Client
 */

declare(strict_types=1);

class Mailer {
    private string $host;
    private int $port;
    private string $username;
    private string $password;
    private string $encryption;
    private string $fromEmail;
    private string $fromName;
    private int $timeout = 15;

    /** @var resource|null */
    private $socket = null;
    private array $log = [];
    private static array $lastLog = [];

    public function __construct() {
        $this->host = Config::get('SMTP_HOST', 'smtp.gmail.com');
        $this->port = (int)Config::get('SMTP_PORT', 587);
        $this->username = Config::get('SMTP_USERNAME', '');
        $this->password = str_replace(' ', '', (string)Config::get('SMTP_PASSWORD', ''));
        $this->encryption = strtolower((string)Config::get('SMTP_ENCRYPTION', 'tls'));
        $this->fromEmail = Config::get('SMTP_FROM_EMAIL', $this->username);
        $this->fromName = Config::get('SMTP_FROM_NAME', 'Personal Storage');
    }

    public static function getLastLog(): array {
        return self::$lastLog;
    }

    public function sendMail(string $to, string $subject, string $body, string $toName = ''): bool {
        try {
            $this->connect();
            $this->authenticate();

            $fromHeader = "{$this->fromName} <{$this->fromEmail}>";
            $toHeader = !empty($toName) ? "{$toName} <{$to}>" : $to;

            $headers = [];
            $headers[] = "Date: " . date('r');
            $headers[] = "To: {$toHeader}";
            $headers[] = "From: {$fromHeader}";
            $headers[] = "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=";
            $headers[] = "MIME-Version: 1.0";
            $headers[] = "Content-Type: text/html; charset=UTF-8";
            $headers[] = "Content-Transfer-Encoding: 8bit";

            $message = implode("\r\n", $headers) . "\r\n\r\n" . $body;

            $this->sendCommand("MAIL FROM:<{$this->fromEmail}>", 250);
            $this->sendCommand("RCPT TO:<{$to}>", 250);
            $this->sendCommand("DATA", 354);
            $this->sendCommand($message . "\r\n.", 250);
            $this->sendCommand("QUIT", 221);

            $this->disconnect();
            $this->log[] = "Email sent successfully to {$to}";
            self::$lastLog = $this->log;
            return true;
        } catch (\Throwable $e) {
            $this->log[] = 'Mail Error: ' . $e->getMessage();
            error_log('Mail Error: ' . $e->getMessage());
            self::$lastLog = $this->log;
            $this->disconnect();
            return false;
        }
    }

    public static function send(string $to, string $subject, string $htmlBody, string $textBody = ''): bool {
        $mailer = new self();
        return $mailer->sendMail($to, $subject, $htmlBody);
    }

    public static function sendOtpEmail(string $email, string $name, string $otp): bool {
        $mailer = new self();
        return $mailer->sendOTP($email, $name, $otp, 'verification');
    }

    public static function sendPasswordResetOtp(string $email, string $name, string $otp): bool {
        $mailer = new self();
        return $mailer->sendOTP($email, $name, $otp, 'reset');
    }

    public function sendOtp(string $to, string $otp, string $purpose = 'verification'): bool {
        return $this->sendOTP($to, 'User', $otp, $purpose);
    }

    public function sendOTP(string $to, string $name, string $otp, string $purpose = 'verification'): bool {
        $appName = $this->fromName ?: 'Personal Storage';
        $purposeText = (stripos($purpose, 'reset') !== false) ? 'password reset' : 'email verification';
        $subject = "Your OTP is {$otp} for {$purposeText} - {$appName}";
        $expiryMinutes = max(1, round((int)Config::get('OTP_EXPIRY_SECONDS', 600) / 60));

        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safeOtp = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');

        $body = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#0a0e17;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#0a0e17;padding:40px 0;">
  <tr>
    <td align="center">
      <table width="480" cellpadding="0" cellspacing="0" style="background:#111827;border-radius:16px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,0.5);border:1px solid #1f2937;">
        <tr>
          <td style="background:linear-gradient(135deg,#0ea5e9,#8b5cf6);padding:30px;text-align:center;">
            <h1 style="color:#ffffff;margin:0;font-size:22px;font-weight:800;letter-spacing:-0.5px;">{$appName}</h1>
            <p style="color:rgba(255,255,255,0.85);margin:5px 0 0;font-size:13px;">Private Cloud Vault</p>
          </td>
        </tr>
        <tr>
          <td style="padding:35px 30px;">
            <p style="color:#f3f4f6;font-size:16px;margin:0 0 10px;">
              Hello <strong>{$safeName}</strong>,
            </p>
            <p style="color:#9ca3af;font-size:14px;line-height:1.6;margin:0 0 25px;">
              Your one-time verification code (OTP) for <strong>{$purposeText}</strong> is:
            </p>
            <div style="text-align:center;margin:25px 0;">
              <span style="display:inline-block;background:#0f172a;color:#38bdf8;font-family:monospace;font-size:32px;font-weight:800;letter-spacing:10px;padding:16px 32px;border-radius:12px;border:2px dashed #0ea5e9;">
                {$safeOtp}
              </span>
            </div>
            <p style="color:#6b7280;font-size:13px;line-height:1.5;margin:20px 0 0;text-align:center;">
              ⏱ This code expires in <strong>{$expiryMinutes} minutes</strong>.<br>
              🔒 Do not share this code with anyone.
            </p>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>
HTML;

        return $this->sendMail($to, $subject, $body, $name);
    }

    public static function sendPasswordChangedEmail(string $email, string $name): bool {
        $mailer = new self();
        $appName = $mailer->fromName ?: 'Personal Storage';
        $subject = "Security Alert: Password Changed - {$appName}";
        $time = date('F j, Y, g:i A') . ' IST';
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');

        $body = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#0a0e17;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#0a0e17;padding:40px 0;">
  <tr>
    <td align="center">
      <table width="480" cellpadding="0" cellspacing="0" style="background:#111827;border-radius:16px;overflow:hidden;border:1px solid #1f2937;padding:30px;">
        <tr>
          <td>
            <h2 style="color:#ffffff;margin:0 0 15px;">Password Updated</h2>
            <p style="color:#9ca3af;font-size:14px;line-height:1.6;">
              Hello <strong>{$safeName}</strong>,<br><br>
              Your account password was successfully updated on <strong>{$time}</strong>.<br><br>
              If you did not perform this change, please contact support or reset your password immediately.
            </p>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>
HTML;

        return $mailer->sendMail($email, $subject, $body, $name);
    }

    public function getLog(): array {
        return $this->log;
    }

    private function connect(): void {
        $host = $this->host;
        $port = $this->port;

        if ($this->encryption === 'ssl') {
            $host = 'ssl://' . $host;
        }

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ]
        ]);

        $this->log[] = "Connecting to {$host}:{$port}...";
        $this->socket = @stream_socket_client(
            "{$host}:{$port}",
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$this->socket) {
            throw new \Exception("Connection to {$host}:{$port} failed: {$errstr} ({$errno})");
        }

        stream_set_timeout($this->socket, $this->timeout);
        $this->getResponse(220);

        $clientName = !empty($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost';
        $this->sendCommand("EHLO {$clientName}", 250);

        if ($this->encryption === 'tls') {
            $this->sendCommand("STARTTLS", 220);
            $crypto = @stream_socket_enable_crypto(
                $this->socket,
                true,
                STREAM_CRYPTO_METHOD_TLS_CLIENT
            );

            if (!$crypto) {
                throw new \Exception('STARTTLS negotiation failed.');
            }

            $this->sendCommand("EHLO {$clientName}", 250);
        }
    }

    private function authenticate(): void {
        if (empty($this->username)) {
            return;
        }

        $this->log[] = "Authenticating as {$this->username}...";
        $this->sendCommand("AUTH LOGIN", 334);
        $this->sendCommand(base64_encode($this->username), 334);
        $this->sendCommand(base64_encode($this->password), 235);
        $this->log[] = "Authentication successful!";
    }

    private function sendCommand(string $command, int $expectedCode): string {
        if (!$this->socket) {
            throw new \Exception('SMTP socket not connected.');
        }

        $cmdDisplay = str_starts_with($command, 'AUTH') || strlen($command) > 50 ? substr($command, 0, 10) . '...' : $command;
        $this->log[] = ">> " . $cmdDisplay;
        fwrite($this->socket, $command . "\r\n");
        return $this->getResponse($expectedCode);
    }

    private function getResponse(int $expectedCode): string {
        $response = '';
        while ($line = fgets($this->socket, 515)) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        $this->log[] = "<< " . trim($response);
        $code = (int)substr($response, 0, 3);
        if ($code !== $expectedCode) {
            throw new \Exception("SMTP Expected code {$expectedCode}, got {$code}: " . trim($response));
        }
        return $response;
    }

    private function disconnect(): void {
        if ($this->socket) {
            @fclose($this->socket);
            $this->socket = null;
        }
    }

    public function __destruct() {
        $this->disconnect();
    }
}