<?php

function fh_env_get(string $key): string {
    $v = getenv($key);
    if ($v !== false && $v !== null && $v !== '') {
        return (string)$v;
    }
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
        return (string)$_ENV[$key];
    }
    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
        return (string)$_SERVER[$key];
    }
    return '';
}

function fh_mail_configured(): bool {
    return fh_env_get('SMTP_HOST') !== '' && fh_env_get('SMTP_USER') !== '' && fh_env_get('SMTP_PASS') !== '';
}

function fh_mail_missing_keys(): array {
    $keys = ['SMTP_HOST', 'SMTP_PORT', 'SMTP_USER', 'SMTP_PASS', 'SMTP_FROM_EMAIL', 'SMTP_FROM_NAME', 'SMTP_ENCRYPTION'];
    $missing = [];
    foreach ($keys as $k) {
        if (fh_env_get($k) === '') {
            $missing[] = $k;
        }
    }
    return $missing;
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
        $missing = fh_mail_missing_keys();
        $ctx = [
            'missing' => $missing,
            'is_local' => defined('IS_LOCAL') ? (IS_LOCAL ? 1 : 0) : null,
            'local_config_exists' => defined('LOCAL_CONFIG_EXISTS') ? (LOCAL_CONFIG_EXISTS ? 1 : 0) : null,
            'local_config_path' => defined('LOCAL_CONFIG_PATH') ? (string)LOCAL_CONFIG_PATH : null,
        ];
        error_log('SMTP config missing | ctx=' . json_encode($ctx));
        throw new RuntimeException('SMTP is not configured. Missing: ' . implode(', ', $missing));
    }

    $host = fh_env_get('SMTP_HOST');
    $port = (int)(fh_env_get('SMTP_PORT') ?: 587);
    $user = fh_env_get('SMTP_USER');
    $pass = fh_env_get('SMTP_PASS');
    $fromEmail = fh_env_get('SMTP_FROM_EMAIL') ?: $user;
    $fromName = fh_env_get('SMTP_FROM_NAME') ?: 'Fitshop Hub';
    $encryption = strtolower(fh_env_get('SMTP_ENCRYPTION') ?: 'tls');

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $smtpDebug = (string)(fh_env_get('SMTP_DEBUG') ?: '');
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
