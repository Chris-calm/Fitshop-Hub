<?php

function fh_mail_configured(): bool {
    return (getenv('SMTP_HOST') ?: '') !== '' && (getenv('SMTP_USER') ?: '') !== '' && (getenv('SMTP_PASS') ?: '') !== '';
}

function fh_send_mail(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody = ''): void {
    $phpmailerBase = __DIR__ . '/phpmailer/src';
    $req = [
        $phpmailerBase . '/Exception.php',
        $phpmailerBase . '/PHPMailer.php',
        $phpmailerBase . '/SMTP.php',
    ];

    foreach ($req as $f) {
        if (!file_exists($f)) {
            throw new RuntimeException('PHPMailer is missing. Place PHPMailer src files in includes/phpmailer/src/');
        }
        require_once $f;
    }

    if (!fh_mail_configured()) {
        throw new RuntimeException('SMTP is not configured. Set SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, SMTP_FROM_EMAIL, SMTP_FROM_NAME.');
    }

    $host = getenv('SMTP_HOST') ?: '';
    $port = (int)(getenv('SMTP_PORT') ?: 587);
    $user = getenv('SMTP_USER') ?: '';
    $pass = getenv('SMTP_PASS') ?: '';
    $fromEmail = getenv('SMTP_FROM_EMAIL') ?: $user;
    $fromName = getenv('SMTP_FROM_NAME') ?: 'Fitshop Hub';
    $encryption = strtolower(getenv('SMTP_ENCRYPTION') ?: 'tls');

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $smtpDebug = (string)(getenv('SMTP_DEBUG') ?: '');
    if ($smtpDebug !== '' && $smtpDebug !== '0') {
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = function ($str, $level) {
            error_log('SMTP[' . $level . '] ' . $str);
        };
    }
    $mail->isSMTP();
    $mail->Host = $host;
    $mail->SMTPAuth = true;
    $mail->Username = $user;
    $mail->Password = $pass;

    if ($encryption === 'ssl') {
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
    } else {
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    }

    $mail->Port = $port;
    $mail->CharSet = 'UTF-8';

    $mail->setFrom($fromEmail, $fromName);
    $mail->addAddress($toEmail, $toName ?: $toEmail);

    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = $htmlBody;
    $mail->AltBody = $textBody ?: strip_tags($htmlBody);

    try {
        $mail->send();
    } catch (Throwable $e) {
        $info = '';
        try {
            $info = (string)$mail->ErrorInfo;
        } catch (Throwable $e2) {
        }
        error_log('PHPMailer send failed: ' . $e->getMessage() . ($info ? (' | ErrorInfo=' . $info) : ''));
        throw $e;
    }
}
