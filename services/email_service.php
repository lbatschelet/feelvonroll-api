<?php
/**
 * Email service for sending password reset emails via SMTP.
 * Single responsibility: email composition and delivery.
 * Exports: send_reset_email.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Sends a password reset email.
 *
 * @param array  $config  App configuration with SMTP settings.
 * @param string $to      Recipient email address.
 * @param string $name    Recipient first name (for personalisation).
 * @param string $link    Full password reset URL.
 * @return void
 * @throws Exception
 */
function send_reset_email(array $config, string $to, string $name, string $link): void
{
    $mail = new PHPMailer(true);

    /* SMTP settings */
    $mail->isSMTP();
    $mail->Host       = $config['smtp_host'] ?? 'smtp.forwardemail.net';
    $port = intval($config['smtp_port'] ?? 587);
    $mail->Port       = $port;
    $mail->SMTPAuth   = true;
    $mail->Username   = $config['smtp_user'] ?? '';
    $mail->Password   = $config['smtp_pass'] ?? '';
    $mail->SMTPSecure = $port === 465 ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->CharSet    = 'UTF-8';

    /* Sender / recipient */
    $mail->setFrom(
        $config['smtp_from'] ?? 'noreply@feelvonroll.ch',
        $config['smtp_from_name'] ?? 'feelvonRoll Admin'
    );
    $mail->addAddress($to, $name);

    /* Content */
    $greeting = $name ? "Hallo $name," : 'Hallo,';
    $mail->isHTML(true);
    $mail->Subject = 'Password reset – feelvonRoll Admin';
    $mail->Body = <<<HTML
<!DOCTYPE html>
<html lang="de">
<head><meta charset="UTF-8"></head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #222; line-height: 1.5;">
  <p>{$greeting}</p>
  <p>Jemand hat einen Passwort-Reset für dein feelvonRoll-Admin-Konto angefordert.</p>
  <p><a href="{$link}" style="display:inline-block;padding:10px 20px;background:#0066cc;color:#fff;text-decoration:none;border-radius:4px;">Passwort zurücksetzen</a></p>
  <p style="font-size:13px;color:#666;">Falls der Button nicht funktioniert, kopiere diesen Link:<br><a href="{$link}">{$link}</a></p>
  <p style="font-size:13px;color:#666;">Dieser Link ist zeitlich begrenzt gültig. Falls du den Reset nicht angefordert hast, kannst du diese Mail ignorieren.</p>
  <p>– feelvonRoll Admin</p>
</body>
</html>
HTML;

    $mail->AltBody = <<<TEXT
{$greeting}

Jemand hat einen Passwort-Reset für dein feelvonRoll-Admin-Konto angefordert.

Passwort zurücksetzen: {$link}

Dieser Link ist zeitlich begrenzt gültig.
Falls du den Reset nicht angefordert hast, kannst du diese Mail ignorieren.

– feelvonRoll Admin
TEXT;

    $mail->send();
}
