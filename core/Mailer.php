<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __BASEDIR__ . '/vendor/autoload.php';

/**
 * Legacy mailer, kept for the strangler-fig migration.
 *
 * SECURITY: all SMTP credentials are read from the environment (.env / MAIL_*).
 * Never hardcode a username, password or app password in this file — it is
 * tracked in git. See .env.example for the available keys.
 */
class Mailer
{
    private static function config(string $key, $default = null)
    {
        if (function_exists('env')) {
            return env($key, $default);
        }

        $value = $_ENV[$key] ?? getenv($key);

        return ($value === false || $value === null || $value === '') ? $default : $value;
    }

    protected static function getMailer()
    {
        $mail = new PHPMailer(true);

        // SMTP config — sourced from the environment, never hardcoded.
        $mail->isSMTP();
        $mail->Host       = (string) self::config('MAIL_HOST', 'smtp.gmail.com');
        $mail->SMTPAuth   = true;
        $mail->Username   = (string) self::config('MAIL_USERNAME', '');
        $mail->Password   = (string) self::config('MAIL_PASSWORD', '');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int) self::config('MAIL_PORT', 587);

        $mail->setFrom(
            (string) self::config('MAIL_FROM_ADDRESS', self::config('MAIL_USERNAME', '')),
            (string) self::config('MAIL_FROM_NAME', 'Vayu')
        );

        return $mail;
    }

    public static function send($to, $subject, $body, $isHtml = false)
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid recipient email: $to");
        }

        $mail = self::getMailer();
        try {
            $mail->addAddress($to);
            $mail->isHTML($isHtml);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->send();
            return true;
        } catch (Exception $e) {
            throw new Exception("Mailer Error: {$mail->ErrorInfo}");
        }
    }

    public static function VerifyMail($mail){
        $appUrl = rtrim((string) self::config('APP_URL', 'https://yourdomain.com'), '/');
        $address = $mail->getToAddresses()[0][0];
        $mail->Subject = "Email Verification";
        $mail->Body    = "Please click the link below to verify your email address:\n\n" .
                         $appUrl . "/verify?email=" . urlencode($address);
        return self::send($address, $mail->Subject, $mail->Body);
    }
}
