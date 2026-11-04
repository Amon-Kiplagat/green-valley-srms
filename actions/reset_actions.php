<?php
/**
 * actions/reset_actions.php
 * ---------------------------------------------------------------------------
 * Handles password reset requests and password resets.
 * Called via POST from reset-password.php.
 *
 * Two actions:
 *   request — look up a user by email, generate a token, and send the link.
 *   reset   — validate a token and update the password.
 * ---------------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/mail.php';

$pdo = db();
$action = (string)($_POST['action'] ?? '');

/* ------------------------------------------------------------------ */
/* REQUEST — generate a reset token and email it                       */
/* ------------------------------------------------------------------ */
if ($action === 'request' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email = trim((string)($_POST['email'] ?? ''));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        set_flash('error', 'Please enter a valid email address.');
        redirect('reset-password.php');
    }

    // Look up the user by email (case-insensitive).
    $stmt = $pdo->prepare(
        'SELECT user_id, username, full_name, email
           FROM users
          WHERE lower(email) = lower(:email)
            AND is_active = TRUE
          LIMIT 1'
    );
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    // Always show a success message to avoid confirming whether an email exists.
    if (!$user) {
        set_flash('success', 'If that email address is registered, a reset link has been sent.');
        redirect('reset-password.php');
    }

    // Invalidate any existing unused tokens for this user.
    $pdo->prepare('UPDATE reset_tokens SET used_at = now() WHERE user_id = :id AND used_at IS NULL')
        ->execute([':id' => $user['user_id']]);

    // Generate a cryptographically secure random token.
    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);

    // Store the hashed token — let PostgreSQL handle the expiration time
    // so there is no PHP/DB timezone mismatch.
    $stmt = $pdo->prepare(
        'INSERT INTO reset_tokens (user_id, token_hash, expires_at)
         VALUES (:id, :hash, now() + interval \'1 hour\')'
    );
    $stmt->execute([
        ':id'   => $user['user_id'],
        ':hash' => $tokenHash,
    ]);

    // Build the reset link using APP_BASE_URL from .env.
    $env = parse_env_file(__DIR__ . '/../../.env');
    $baseUrl = rtrim($env['APP_BASE_URL'] ?? 'http://localhost', '/');
    $resetLink = $baseUrl . '/reset-password.php?token=' . $rawToken;

    // Compose the email.
    $subject = 'Password Reset - Green Valley SRMS';
    $body = "Hello {$user['full_name']},\n\n"
          . "You requested a password reset for your Green Valley SRMS account.\n"
          . "Click the link below to reset your password (valid for 1 hour):\n\n"
          . "{$resetLink}\n\n"
          . "If you did not request this, please ignore this email.\n\n"
          . "Green Valley Secondary School";

    // Attempt to send the email.
    $sent = send_email($user['email'], $subject, $body);

    if ($sent) {
        set_flash('success', 'A password reset link has been sent to ' . htmlspecialchars($user['email']) . '. Check your inbox.');
    } else {
        // If email fails, show the link on-screen so the user can still reset.
        set_flash('success', 'Reset link generated (email could not be sent). Use the link below directly:');
        set_flash('info', $resetLink);
    }

    redirect('reset-password.php');
}

/* ------------------------------------------------------------------ */
/* RESET — validate token and update the password                      */
/* ------------------------------------------------------------------ */
if ($action === 'reset' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $token = (string)($_POST['token'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm'] ?? '');

    // Validate the raw token.
    if ($token === '') {
        set_flash('error', 'Invalid reset link.');
        redirect('reset-password.php');
    }

    $tokenHash = hash('sha256', $token);
    $stmt = $pdo->prepare(
        'SELECT rt.*, u.user_id, u.username, u.full_name
           FROM reset_tokens rt
           JOIN users u ON u.user_id = rt.user_id
          WHERE rt.token_hash = :hash
            AND rt.used_at IS NULL
            AND rt.expires_at > now()
          LIMIT 1'
    );
    $stmt->execute([':hash' => $tokenHash]);
    $tokenRow = $stmt->fetch();

    if (!$tokenRow) {
        set_flash('error', 'This reset link is invalid or has expired. Please request a new one.');
        redirect('reset-password.php');
    }

    // Validate password requirements.
    if (strlen($password) < 8) {
        set_flash('error', 'Password must be at least 8 characters long.');
        redirect('reset-password.php?token=' . $token);
    }

    if (!preg_match('/[A-Z]/', $password)) {
        set_flash('error', 'Password must contain at least one uppercase letter.');
        redirect('reset-password.php?token=' . $token);
    }

    if (!preg_match('/[0-9]/', $password)) {
        set_flash('error', 'Password must contain at least one number.');
        redirect('reset-password.php?token=' . $token);
    }

    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        set_flash('error', 'Password must contain at least one special character.');
        redirect('reset-password.php?token=' . $token);
    }

    if ($password !== $confirm) {
        set_flash('error', 'Passwords do not match.');
        redirect('reset-password.php?token=' . $token);
    }

    // Update the password and mark the token as used.
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->prepare('UPDATE users SET password_hash = :h WHERE user_id = :id')
        ->execute([':h' => $hash, ':id' => $tokenRow['user_id']]);
    $pdo->prepare('UPDATE reset_tokens SET used_at = now() WHERE reset_token_id = :id')
        ->execute([':id' => $tokenRow['reset_token_id']]);

    set_flash('success', 'Password updated successfully for ' . htmlspecialchars($tokenRow['full_name']) . '. You can now sign in with your new password.');
    redirect('login.php');
}

// If we reach here, the action was invalid.
http_response_code(405);
set_flash('error', 'Invalid action.');
redirect('reset-password.php');
