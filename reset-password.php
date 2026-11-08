<?php
/**
 * reset-password.php
 * ---------------------------------------------------------------------------
 * Password reset page. Three modes:
 * 1. Request reset — enter email to generate a reset link (shown on-screen).
 * 2. Reset form — click the link, enter new password.
 * 3. Success — password updated.
 * ---------------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

// Already logged in? Go to dashboard.
if (is_logged_in()) {
    redirect('dashboard.php');
}

$token = (string)($_GET['token'] ?? '');
$error = '';
$success = '';
$resetLink = '';

// Check for flash messages.
if (isset($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if (isset($_SESSION['flash_info'])) {
    $resetLink = $_SESSION['flash_info'];
    unset($_SESSION['flash_info']);
}
if (isset($_SESSION['flash_error'])) {
    $error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

// If a reset link was generated, show it.
if ($resetLink !== '') {
    $success = 'Your password reset link is ready. Click the link below to reset your password:';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Green Valley SRMS</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
        }
        .reset-container {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
            width: 100%;
            max-width: 450px;
        }
        .reset-container h1 {
            text-align: center;
            color: #1e293b;
            margin-bottom: 8px;
            font-size: 24px;
        }
        .reset-container p {
            text-align: center;
            color: #64748b;
            margin-bottom: 24px;
            font-size: 14px;
        }
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 14px;
        }
        .alert-error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        .alert-success {
            background: #f0fdf4;
            color: #16a34a;
            border: 1px solid #bbf7d0;
        }
        .alert-info {
            background: #eff6ff;
            color: #2563eb;
            border: 1px solid #bfdbfe;
            word-break: break-all;
        }
        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #334155;
            font-size: 14px;
        }
        .form-group input {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            box-sizing: border-box;
            transition: border-color 0.2s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
        }
        .btn-submit {
            width: 100%;
            padding: 12px;
            background: #6366f1;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            margin-top: 8px;
        }
        .btn-submit:hover {
            background: #4f46e5;
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #6366f1;
            text-decoration: none;
            font-size: 14px;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        .reset-link-box {
            background: #f8fafc;
            border: 2px dashed #6366f1;
            border-radius: 8px;
            padding: 12px;
            margin: 16px 0;
            word-break: break-all;
        }
        .reset-link-box a {
            color: #6366f1;
            font-weight: 600;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <h1><i class="fa-solid fa-key"></i> Reset Password</h1>
        <p>Green Valley Secondary School</p>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success !== ''): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($resetLink !== ''): ?>
            <div class="alert alert-info">
                <p style="margin:0 0 8px;font-weight:600;color:#1e293b;">
                    <i class="fa-solid fa-link"></i> Your password reset link is ready:
                </p>
                <div class="reset-link-box">
                    <a href="<?= htmlspecialchars($resetLink) ?>" target="_blank" style="display:inline-block; background:#22c55e; color:white; padding:12px 24px; border-radius:8px; text-decoration:none; font-weight:700; font-size:15px;">
                        <i class="fa-solid fa-key"></i> Click Here to Reset Password
                    </a>
                </div>
                <p style="margin:8px 0 0;font-size:12px;color:#64748b;">
                    Click the button above to open the reset page. The link expires in 1 hour.
                </p>
            </div>
        <?php endif; ?>

        <?php if ($token !== ''): ?>
            <!-- Mode 2: Reset form with token -->
            <form method="post" action="actions/reset_actions.php">
                <input type="hidden" name="action" value="update_password">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                <div class="form-group">
                    <label for="password">New Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter new password" required minlength="6">
                </div>

                <div class="form-group">
                    <label for="password_confirm">Confirm Password</label>
                    <input type="password" id="password_confirm" name="password_confirm" placeholder="Confirm new password" required minlength="6">
                </div>

                <button type="submit" class="btn-submit">Update Password</button>
            </form>
        <?php else: ?>
            <!-- Mode 1: Request reset -->
            <form method="post" action="actions/reset_actions.php">
                <input type="hidden" name="action" value="request_reset">

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" placeholder="Enter your email" required>
                </div>

                <button type="submit" class="btn-submit">Generate Reset Link</button>
            </form>
        <?php endif; ?>

        <a href="login.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Login</a>
    </div>
</body>
</html>
