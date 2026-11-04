<?php
/**
 * includes/mail.php
 * ---------------------------------------------------------------------------
 * SMTP email sender using PHPMailer for reliable delivery.
 * Falls back to a clear error message if SMTP is unavailable.
 *
 * Reads SMTP_* values from the .env file.
 * ---------------------------------------------------------------------------
 */

declare(strict_types=1);

/**
 * Send an email via SMTP using PHPMailer.
 *
 * Reads SMTP_* values from the .env file.
 * Returns true on success, false on failure.
 * Logs technical errors without exposing secrets to users.
 */
function send_email(string $to, string $subject, string $body): bool
{
    // Helper: read an environment variable from all possible sources.
    // PHP-FPM (Nginx) may not expose env vars via getenv(), so try $_ENV, $_SERVER, getenv, and .env.
    $env = parse_env_file(__DIR__ . '/../.env');
    $getEnv = function(string $key, $default = '') {
        $val = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?? $default;
        return $val ?: $default;
    };

    $host = $getEnv('SMTP_HOST', $env['SMTP_HOST'] ?? '');
    $port = (int)$getEnv('SMTP_PORT', $env['SMTP_PORT'] ?? 587);
    $user = $getEnv('SMTP_USER', $env['SMTP_USER'] ?? '');
    $pass = $getEnv('SMTP_PASS', $env['SMTP_PASS'] ?? '');
    $from = $getEnv('SMTP_FROM', $env['SMTP_FROM'] ?? $user);
    $fromName = $getEnv('SMTP_FROM_NAME', $env['SMTP_FROM_NAME'] ?? 'Green Valley SRMS');

    // Log which source provided credentials for debugging.
    if ($host === '' || $user === '' || $pass === '') {
        error_log('SMTP: missing credentials. host=' . ($host ? 'set' : 'empty') .
                  ', user=' . ($user ? 'set' : 'empty') . ', pass=' . ($pass ? 'set' : 'empty'));
    }

    // Load PHPMailer via Composer autoloader
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($autoload) && $host !== '' && $user !== '' && $pass !== '') {
        require_once $autoload;
        if (class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
            try {
                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

                // SMTP configuration
                $mail->isSMTP();
                $mail->Host = $host;
                $mail->SMTPAuth = true;
                $mail->Username = $user;
                $mail->Password = $pass;
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = $port;
                $mail->Timeout = 10;

                // Sender and recipient
                $mail->setFrom($from, $fromName);
                $mail->addAddress($to);

                // Email content
                $mail->isHTML(false);
                $mail->Subject = $subject;
                $mail->Body = $body;

                return $mail->send();

            } catch (\PHPMailer\PHPMailer\Exception $e) {
                // Log the mailer error without exposing credentials
                error_log('PHPMailer error: ' . $e->getMessage());
            } catch (\Exception $e) {
                error_log('Email send failed: ' . $e->getMessage());
            }
            // Fall through to mail() fallback
        } else {
            error_log('PHPMailer class not found. Run "composer install".');
        }
    } elseif ($host === '' || $user === '' || $pass === '') {
        error_log('SMTP: missing credentials in environment or .env');
    }

    // No fallback to mail() — it returns true on Docker without actually sending.
    // The caller (reset_actions.php) will show the reset link on-screen if this returns false.
    error_log('Email send failed: PHPMailer unavailable or SMTP credentials missing.');
    return false;
}

/**
 * Parse a simple .env file into an associative array.
 */
function parse_env_file(string $path): array
{
    $result = [];
    if (!file_exists($path)) {
        return $result;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        // Skip comments.
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        // Remove surrounding quotes.
        if (strlen($value) >= 2 && $value[0] === '"' && $value[strlen($value) - 1] === '"') {
            $value = substr($value, 1, -1);
        }
        $result[$key] = $value;
    }
    return $result;
}
