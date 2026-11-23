<?php

declare(strict_types=1);

function parse_env_file(string $path): array
{
    $result = [];
    if (!file_exists($path)) {
        return $result;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $result[trim($k)] = trim($v);
    }
    return $result;
}

// Send email via PHPMailer SMTP. Returns false if unavailable.
function send_email(string $to, string $subject, string $body): bool
{
    $env = parse_env_file(__DIR__ . '/../.env');
    $getEnv = function(string $key, $default = '') use ($env) {
        $val = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?? $env[$key] ?? $default;
        return $val ?: $default;
    };

    // Brevo SMTP relay (works on Render free tier)
    $host = $getEnv('SMTP_HOST', 'smtp-relay.brevo.com');
    $port = (int)$getEnv('SMTP_PORT', 587);
    $user = $getEnv('SMTP_USER', '');
    $pass = $getEnv('SMTP_PASS', '');
    $from = $getEnv('SMTP_FROM', 'kiplagatamon22@gmail.com');
    $fromName = $getEnv('SMTP_FROM_NAME', 'Green Valley SRMS');
    if ($from === '') $from = $user;

    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($autoload) && $host !== '' && $user !== '' && $pass !== '') {
        require_once $autoload;

        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->SMTPAuth = true;
            $mail->Username = $user;
            $mail->Password = $pass;
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $port;
            $mail->Timeout = 15;
            $mail->SMTPAutoTLS = false;
            $mail->setFrom($from, $fromName);
            $mail->addAddress($to);
            $mail->isHTML(false);
            $mail->Subject = $subject;
            $mail->Body = $body;

            if ($mail->send()) {
                error_log('Email sent successfully to ' . $to);
                return true;
            } else {
                error_log('PHPMailer send failed: ' . ($mail->ErrorInfo ?: 'unknown error'));
                return false;
            }

        } catch (\PHPMailer\PHPMailer\Exception $e) {
            error_log('PHPMailer error: ' . $e->getMessage());
            return false;
        } catch (\Exception $e) {
            error_log('Email send failed: ' . $e->getMessage());
            return false;
        }
    }

    error_log('Email send failed: PHPMailer unavailable or SMTP credentials missing.');
    return false;
}
