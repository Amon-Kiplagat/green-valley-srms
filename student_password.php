<?php
/**
 * student_password.php
 * ---------------------------------------------------------------------------
 * Student portal — change password on first login.
 * ---------------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/student_portal.php';
require_student();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string)($_POST['password'] ?? '');
    $confirm  = (string)($_POST['confirm'] ?? '');

    if ($password === '' || strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $pdo = db();
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare(
            'UPDATE students
                 SET portal_password_hash = :h,
                     portal_must_change   = FALSE
               WHERE student_id = :id'
        )->execute([':h' => $hash, ':id' => $_SESSION['student_id']]);
        unset($_SESSION['portal_must_change']);
        $success = 'Password changed successfully!';
    }
}

$page_title = 'Change Password';
$page_subtitle = 'Set your own password';
require_once __DIR__ . '/includes/header.php';
?>

<div class="content">
<div class="card" style="max-width: 480px;">
    <h2><i class="fa-solid fa-lock"></i> Change Password</h2>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
        <p><a class="btn btn-primary" href="student_home.php">
            <i class="fa-solid fa-arrow-right"></i> Go to Home
        </a></p>
    <?php else: ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label for="password">New Password</label>
                <input type="password" id="password" name="password" required minlength="6"
                       placeholder="At least 6 characters">
            </div>

            <div class="form-group">
                <label for="confirm">Confirm Password</label>
                <input type="password" id="confirm" name="confirm" required minlength="6"
                       placeholder="Repeat your password">
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-check"></i> Change Password
            </button>
        </form>
    <?php endif; ?>
</div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
