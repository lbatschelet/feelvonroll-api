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
 * Formats expiry hours into a human-readable English string.
 *
 * @param int $hours
 * @return string
 */
function format_expiry(int $hours): string
{
    if ($hours < 24) {
        return $hours === 1 ? '1 hour' : "$hours hours";
    }
    $days = intdiv($hours, 24);
    return $days === 1 ? '1 day' : "$days days";
}

/**
 * Sends a password reset email.
 *
 * @param array  $config      App configuration with SMTP settings.
 * @param string $to          Recipient email address.
 * @param string $name        Recipient first name (for personalisation).
 * @param string $link        Full password reset URL.
 * @param int    $expiryHours How long the link is valid (for display in email).
 * @return void
 * @throws Exception
 */
function send_reset_email(array $config, string $to, string $name, string $link, int $expiryHours = 24): void
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
        $config['smtp_from'] ?? 'noreply@example.com',
        $config['smtp_from_name'] ?? 'feelvonRoll Admin'
    );
    $mail->addAddress($to, $name);

    /* Content */
    $greeting = $name ? "Hi $name," : 'Hi,';
    $validity = format_expiry($expiryHours);
    $year = date('Y');

    $mail->isHTML(true);
    $mail->Subject = 'Password reset – feelvonRoll Admin';
    $mail->Body = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"></head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #222; line-height: 1.6; max-width: 520px; margin: 0 auto; padding: 20px;">

  <!-- Logo / brand -->
  <p style="font-size: 1.4em; margin: 0 0 24px;">
    <em style="font-style: italic; font-weight: 400;">feel</em><strong style="font-weight: 700;">vonRoll</strong>
    <span style="color: #94a3b8; font-weight: 400;"> Admin</span>
  </p>

  <p>{$greeting}</p>
  <p>Someone requested a password reset for your feelvonRoll Admin account.</p>
  <p>This link is valid for <strong>{$validity}</strong>.</p>

  <p style="margin: 24px 0;">
    <a href="{$link}" style="display:inline-block;padding:10px 24px;background:#0066cc;color:#fff;text-decoration:none;border-radius:4px;font-weight:500;">Reset password</a>
  </p>

  <p style="font-size: 13px; color: #666;">
    If the button doesn't work, copy this link:<br>
    <a href="{$link}" style="color: #0066cc;">{$link}</a>
  </p>

  <p style="font-size: 13px; color: #666;">
    If you didn't request this reset, you can safely ignore this email.
  </p>

  <!-- Footer -->
  <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 32px 0 16px;">
  <p style="font-size: 12px; color: #94a3b8; line-height: 1.6; margin: 0;">
    &copy; {$year}
    <a href="https://lukasbatschelet.ch" style="color: #64748b; text-decoration: none;">Lukas Batschelet</a>
    for
    <a href="https://www.phbern.ch" style="color: #64748b; text-decoration: none;"><strong>PH</strong>&thinsp;Bern</a>
    <br>
    <a href="https://github.com/lbatschelet/feelvonroll" style="color: #64748b; text-decoration: none;">
      <em style="font-style: italic;">feel</em><strong style="font-weight: 700;">vonRoll</strong></a>
    is an open-source project licensed under the
    <a href="https://github.com/lbatschelet/feelvonroll/blob/main/LICENSE" style="color: #64748b; text-decoration: none;">AGPL-3.0</a> license.
  </p>

</body>
</html>
HTML;

    $mail->AltBody = <<<TEXT
feelVONROLL Admin
─────────────────

{$greeting}

Someone requested a password reset for your feelvonRoll Admin account.

This link is valid for {$validity}.

Reset password: {$link}

If you didn't request this reset, you can safely ignore this email.

─────────────────
© {$year} Lukas Batschelet for PHBern
feelvonRoll is an open-source project licensed under the AGPL-3.0 license.
https://github.com/lbatschelet/feelvonroll
TEXT;

    $mail->send();
}
