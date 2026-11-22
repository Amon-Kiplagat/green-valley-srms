<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mail.php';

$pdo = db();
$action = (string)($_POST['action'] ?? '');

if ($action === 'request_reset') {
    $email = trim((string)($_POST['email'] ?? ''));

    if ($email === '') {
        set_flash('error', 'Please enter your email address.');
        redirect('reset-password.php');
    }

    $stmt = $pdo->prepare(
        'SELECT user_id, username, full_name, email
           FROM users
          WHERE email = :e
          LIMIT 1'
    );
    $stmt->execute([':e' => $email]);
    $user = $stmt->fetch();

    if (!$user) {
        set_flash('success', 'If an account with that email exists, a reset link has been generated.');
        redirect('reset-password.php');
    }

    $token = bin2hex(random_bytes(32));
    $hash  = hash('sha256', $token);
    $expires = date('Y-m-d H:i:s', time() + 3600);

    $stmt = $pdo->prepare(
        'INSERT INTO reset_tokens (user_id, token_hash, expires_at)
          VALUES (:uid, :h, :exp)'
    );
    $stmt->execute([':uid' => $user['user_id'], ':h' => $hash, ':exp' => $expires]);

    $baseUrl = rtrim(getenv('APP_BASE_URL') ?: ($_SERVER['APP_BASE_URL'] ?: ''), '/');
    if ($baseUrl === '') {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = $protocol . '://' . $host;
    }
    $baseUrl = rtrim($baseUrl, '/');
    $resetLink = $baseUrl . '/reset-password.php?token=' . $token;

    // Try to send reset link to admin email
    $emailSent = send_email(
        'kiplamon29@gmail.com',
        'Password Reset Request - Green Valley SRMS',
        "Hello {$user['full_name']},\n\n"
        . "You requested a password reset for your Green Valley SRMS account.\n"
        . "Click the link below to reset your password:\n\n"
        . $resetLink . "\n\n"
        . "This link expires in 1 hour. If you did not request this, ignore this email.\n"
    );

    if ($emailSent) {
        set_flash('success', 'A password reset link has been sent to kiplamon29@gmail.com. Please check your inbox.');
    } else {
        set_flash('success', 'Password reset link generated. Click the link below to reset your password:');
        set_flash('info', $resetLink);
    }

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

    $newHash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('UPDATE users SET password_hash = :h WHERE user_id = :id');
    $stmt->execute([':h' => $newHash, ':id' => $reset['user_id']]);

    $stmt = $pdo->prepare('DELETE FROM reset_tokens WHERE user_id = :id');
    $stmt->execute([':id' => $reset['user_id']]);

    set_flash('success', 'Password updated successfully for ' . htmlspecialchars($reset['full_name']) . '. You can now log in with your new password.');
    redirect('login.php');
}

redirect('reset-password.php');
