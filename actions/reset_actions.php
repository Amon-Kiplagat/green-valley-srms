<?php
/**
 * actions/reset_actions.php
 * ---------------------------------------------------------------------------
 * Password reset backend: generates single-use, SHA-256-hashed tokens and
 * updates passwords when a valid token is presented.
 *
 * Two endpoints (POST):
 *   action=request  — look up the user by email, create a token, send email.
 *   action=reset    — validate the token, update the password, mark used.
 *
 * Tokens are hashed before DB storage, so a compromised database cannot be
 * replayed.  Each token is single-use (enforced via `used_at`) and expires
 * after 60 minutes.
 * ---------------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mail.php';

// Already signed in? Go to dashboard.
if (is_logged_in()) {
    redirect('dashboard.php');
}

/* ------------------------------------------------------------------------ */
/* Helpers                                                                  */
/* ------------------------------------------------------------------------ */

/** Hash a raw token with SHA-256 before storing or looking it up. */
function hash_token(string $raw): string
{
    return hash('sha256', $raw);
}

/**
 * Create a reset token for the given user.
 * Invalidates any previous unused tokens first.
 * Returns the raw (unhashed) token — only the hash is stored.
 */
function create_reset_token(int $userId): string
{
    $pdo = db();

    // Invalidate old tokens.
    $pdo->prepare('UPDATE reset_tokens SET used_at = now() WHERE user_id = :id AND used_at IS NULL')
        ->execute([':id' => $userId]);

    // Generate a fresh raw token and store its hash.
    $raw   = bin2hex(random_bytes(32));
    $hash  = hash_token($raw);

    // Use PostgreSQL to compute expires_at so there is no PHP/DB timezone mismatch.
    $pdo->prepare(
        'INSERT INTO reset_tokens (user_id, token_hash, expires_at)
          VALUES (:id, :hash, now() + interval \'60 minutes\')'
    )->execute([':id' => $userId, ':hash' => $hash]);

    return $raw;
}

/**
 * Look up a valid, unused token by its raw value.
 * Returns the row from reset_tokens joined with users, or null.
 */
function find_valid_token(string $rawToken): ?array
{
    $hash = hash_token($rawToken);
    $stmt = db()->prepare(
        'SELECT rt.*, u.user_id, u.username, u.full_name
           FROM reset_tokens rt
           JOIN users u ON u.user_id = rt.user_id
          WHERE rt.token_hash = :hash
            AND rt.used_at IS NULL
            AND rt.expires_at > now()'
    );
    $stmt->execute([':hash' => $hash]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/* ------------------------------------------------------------------------ */
/* Route dispatch                                                           */
/* ------------------------------------------------------------------------ */

$action = input('action', '');

if ($action === 'request') {
    /* ---- Step 1: request a reset link -------------------------------- */

    $email = input('email');

    if ($email === '') {
        set_flash('error', 'Please enter your email address.');
        redirect('reset-password.php');
    }

    $stmt = db()->prepare(
        'SELECT user_id, full_name, email FROM users WHERE email = :e AND is_active = TRUE LIMIT 1'
    );
    $stmt->execute([':e' => $email]);
    $user = $stmt->fetch();

    if (!$user) {
        // Do not reveal whether the email exists.
        set_flash('success', 'If that email is registered, a reset link has been sent.');
        redirect('reset-password.php');
    }

    $rawToken = create_reset_token((int)$user['user_id']);

    // Build the reset URL.
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $baseUrl = $scheme . '://' . $host;
    $resetUrl = $baseUrl . '/reset-password.php?token=' . urlencode($rawToken);

    // Attempt to send the email.
    $subject = 'Reset your Green Valley SRMS password';
    $body    = "Hello {$user['full_name']},\n\n"
             . "Click the link below to reset your password:\n\n"
             . $resetUrl . "\n\n"
             . "The link expires in 60 minutes.\n"
             . "If you did not request this, please ignore this email.";

    $sent = send_email($user['email'], $subject, $body);

    if ($sent) {
        set_flash('success', 'A reset link has been sent to ' . e($user['email']) . '. Check your inbox.');
    } else {
        // Fallback: show the token in the URL for development.
        error_log('SMTP send failed for ' . $user['email'] . ' — falling back to URL token.');
        set_flash('success', 'Email could not be sent. Use this direct reset link: ' . $resetUrl);
    }

    redirect('reset-password.php');

} elseif ($action === 'reset') {
    /* ---- Step 2: set a new password ---------------------------------- */

    $rawToken = input('token');
    $password = (string)($_POST['password'] ?? '');
    $confirm  = (string)($_POST['confirm'] ?? '');

    if ($rawToken === '') {
        set_flash('error', 'Invalid reset link.');
        redirect('reset-password.php');
    }

    $tokenRow = find_valid_token($rawToken);
    if (!$tokenRow) {
        set_flash('error', 'This reset link is invalid or has expired.');
        redirect('reset-password.php');
    }

    if ($password === '' || strlen($password) < 6) {
        set_flash('error', 'Password must be at least 6 characters.');
        redirect('reset-password.php?token=' . urlencode($rawToken));
    }

    if ($password !== $confirm) {
        set_flash('error', 'Passwords do not match.');
        redirect('reset-password.php?token=' . urlencode($rawToken));
    }

    $pdo = db();

    // Update the password.
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->prepare('UPDATE users SET password_hash = :h WHERE user_id = :id')
        ->execute([':h' => $hash, ':id' => $tokenRow['user_id']]);

    // Mark the token as used (single-use enforcement).
    $pdo->prepare('UPDATE reset_tokens SET used_at = now() WHERE token_hash = :hash')
        ->execute([':hash' => hash_token($rawToken)]);

    set_flash('success', 'Password updated successfully. You can now sign in.');
    redirect('login.php');

} else {
    // Unknown action.
    set_flash('error', 'Invalid action.');
    redirect('reset-password.php');
}
