<?php
/**
 * profile.php
 * ---------------------------------------------------------------------------
 * Staff profile page — change your own password.
 * ---------------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();

$pdo = db();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword     = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        $error = 'All password fields are required.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'New password and confirmation do not match.';
    } elseif (strlen($newPassword) < 6) {
        $error = 'New password must be at least 6 characters.';
    } else {
        $userId = (int)$_SESSION['user_id'];
        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE user_id = :id');
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
            $error = 'Current password is incorrect.';
        } else {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE users SET password_hash = :h WHERE user_id = :id')
                ->execute([':h' => $hash, ':id' => $userId]);

            log_activity('Changed password', 'Profile', null);
            $success = 'Password updated successfully.';
        }
    }
}

// Load current user info
$stmt = $pdo->prepare('SELECT full_name, username, role FROM users WHERE user_id = :id');
$stmt->execute([':id' => (int)$_SESSION['user_id']]);
$profile = $stmt->fetch();

$page_title    = 'Profile';
$page_subtitle = 'Manage your account settings';
require_once __DIR__ . '/includes/header.php';
?>

<div class="content">
<div class="card" style="max-width:480px;">
    <h2><i class="fa-solid fa-user-gear"></i> My Profile</h2>

    <table style="margin-bottom:24px;">
        <tr><td><strong>Name</strong></td><td><?= e($profile['full_name'] ?? '') ?></td></tr>
        <tr><td><strong>Username</strong></td><td><?= e($profile['username'] ?? '') ?></td></tr>
        <tr><td><strong>Role</strong></td><td><?= e(ucfirst($profile['role'] ?? '')) ?></td></tr>
    </table>

    <hr style="margin:24px 0; border:none; border-top:1px solid #e2e8f0;">

    <h3><i class="fa-solid fa-lock"></i> Change Password</h3>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>

    <form action="profile.php" method="post" data-validate>
        <?= csrf_field() ?>
        <div>
            <label>Current Password</label>
            <input type="password" name="current_password" required>
        </div>
        <div>
            <label>New Password</label>
            <input type="password" name="new_password" required minlength="6"
                   placeholder="At least 6 characters">
        </div>
        <div>
            <label>Confirm New Password</label>
            <input type="password" name="confirm_password" required minlength="6"
                   placeholder="Repeat new password">
        </div>
        <div style="align-self:end; margin-top:8px;">
            <button type="submit">
                <i class="fa-solid fa-check"></i> Update Password
            </button>
        </div>
    </form>
</div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
