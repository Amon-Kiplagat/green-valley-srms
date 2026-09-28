<?php
/**
 * login.php
 * ---------------------------------------------------------------------------
 * Authenticates a user against the `users` table using password_verify(),
 * starts a session, and redirects to dashboard.php. Replaces login.html.
 * ---------------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

// Already signed in? Go straight to the dashboard.
if (is_logged_in()) {
    redirect('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $username = input('username');
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        // Parameterised query - no SQL injection possible.
        $stmt = db()->prepare(
            'SELECT user_id, username, password_hash, full_name, role, is_active
               FROM users
              WHERE username = :u
              LIMIT 1'
        );
        $stmt->execute([':u' => $username]);
        $user = $stmt->fetch();

        // Verify the hash produced by PHP password_hash().
        if ($user && $user['is_active'] && password_verify($password, $user['password_hash'])) {

            // Transparently upgrade the hash if PHP's default cost changed.
            if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                $new = password_hash($password, PASSWORD_DEFAULT);
                $up  = db()->prepare('UPDATE users SET password_hash = :h WHERE user_id = :id');
                $up->execute([':h' => $new, ':id' => $user['user_id']]);
            }

            login_user($user);
            set_flash('success', 'Welcome back, ' . $user['full_name'] . '!');
            redirect('dashboard.php');
        }

        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Green Valley SRMS</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="login-page">
    <div class="login-box">
        <h2>Green Valley SRMS</h2>
        <p>Student Records Management System</p>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form action="login.php" method="post" data-validate class="inline">
            <?= csrf_field() ?>
            <label for="username">Username</label>
            <input type="text" id="username" name="username"
                   value="<?= e($_POST['username'] ?? '') ?>"
                   placeholder="Enter username" required>

            <label for="password">Password</label>
            <input type="password" id="password" name="password"
                   placeholder="Enter password" required>

            <button type="submit">Login</button>
        </form>
    </div>
    <script src="js/script.js"></script>
</body>
</html>
