<?php
/**
 * actions/reset_actions.php
 * ---------------------------------------------------------------------------
 * Handles password reset actions: request reset, verify token, update password.
 * The reset link is shown on-screen (no email).
 * ---------------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mail.php';

$pdo = db();
$action = (string)($_POST['action'] ?? '');

if ($action === 'request_reset') {
    // User entered their email/username to request a reset.
    $email = trim((string)($_POST['email'] ?? ''));

    if ($email === '') {
        set_flash('error', 'Please enter your email address.');
        redirect('reset-password.php');
    }

    // Look up the user by email.
    $stmt = $pdo->prepare(
        'SELECT user_id, username, full_name, email
           FROM users
          WHERE email = :e
          LIMIT 1'
    );
    $stmt->execute([':e' => $email]);
    $user = $stmt->fetch();

    if (!$user) {
        // Don't reveal whether the email exists.
        set_flash('success', 'If an account with that email exists, a reset link has been generated.');
        redirect('reset-password.php');
    }

    // Generate a unique token and store it.
    $token = bin2hex(random_bytes(32));
    $hash  = hash('sha256', $token);
    $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour

    $stmt = $pdo->prepare(
        'INSERT INTO reset_tokens (user_id, token_hash, expires_at)
          VALUES (:uid, :h, :exp)'
    );
    $stmt->execute([':uid' => $user['user_id'], ':h' => $hash, ':exp' => $expires]);

    // Build the reset link.
    $baseUrl = rtrim(getenv('APP_BASE_URL') ?: ($_SERVER['APP_BASE_URL'] ?: ''), '/');
    if ($baseUrl === '') {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = $protocol . '://' . $host;
    }
    $baseUrl = rtrim($baseUrl, '/');
    $resetLink = $baseUrl . '/reset-password.php?token=' . $token;

    // Show the reset link directly on-screen (no email).
    set_flash('success', 'Password reset link generated for ' . htmlspecialchars($user['full_name']) . '. Click the link below to reset your password:');
    set_flash('info', $resetLink);

    redirect('reset-password.php');
}

if ($action === 'update_password') {
    $token = trim((string)($_POST['token'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $passwordConfirm = (string)($_POST['password_confirm'] ?? '');

    if ($token === '' || $password === '' || $passwordConfirm === '') {
        set_flash('error', 'Please fill in all fields.');
        redirect('reset-password.php?token=' . $token);
    }

    if ($password !== $passwordConfirm) {
        set_flash('error', 'Passwords do not match.');
        redirect('reset-password.php?token=' . $token);
    }

    if (strlen($password) < 6) {
        set_flash('error', 'Password must be at least 6 characters.');
        redirect('reset-password.php?token=' . $token);
    }

    // Verify the token.
    $hash = hash('sha256', $token);
    $stmt = $pdo->prepare(
        'SELECT rt.user_id, rt.expires_at, u.username, u.full_name
           FROM reset_tokens rt
           JOIN users u ON u.user_id = rt.user_id
          WHERE rt.token_hash = :h
            AND rt.expires_at > now()
          LIMIT 1'
    );
    $stmt->execute([':h' => $hash]);
    $reset = $stmt->fetch();

    if (!$reset) {
        set_flash('error', 'Invalid or expired reset link. Please request a new one.');
        redirect('reset-password.php');
    }

    // Update the password.
    $newHash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('UPDATE users SET password_hash = :h WHERE user_id = :id');
    $stmt->execute([':h' => $newHash, ':id' => $reset['user_id']]);

    // Invalidate the token.
    $stmt = $pdo->prepare('DELETE FROM reset_tokens WHERE user_id = :id');
    $stmt->execute([':id' => $reset['user_id']]);

    set_flash('success', 'Password updated successfully for ' . htmlspecialchars($reset['full_name']) . '. You can now log in with your new password.');
    redirect('login.php');
}

// Invalid action.
redirect('reset-password.php');
