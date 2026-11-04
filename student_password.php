<?php
/**
 * student_password.php
 * ---------------------------------------------------------------------------
 * Student portal — Profile page: view student info and change password.
 * ---------------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/student_portal.php';
require_student();

$pdo = db();

// Fetch student profile info.
$stmt = $pdo->prepare(
    'SELECT s.full_name, s.admission_no, s.gender, c.class_name, c.teacher_id,
            t.full_name AS teacher_name
       FROM students s
       JOIN classes c ON c.class_id = s.class_id
       LEFT JOIN teachers t ON t.teacher_id = c.teacher_id
      WHERE s.student_id = :id'
);
$stmt->execute([':id' => $_SESSION['student_id']]);
$profile = $stmt->fetch();

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

$page_title = 'Profile';
$page_subtitle = 'Your account information';
require_once __DIR__ . '/includes/header.php';
?>

<div class="content">
    <div class="card">
        <h2><i class="fa-solid fa-user-gear"></i> My Profile</h2>
        <table>
            <tr><th>Full Name</th><td><?= e($profile['full_name'] ?? $_SESSION['student_name']) ?></td></tr>
            <tr><th>Admission No</th><td><?= e($profile['admission_no'] ?? $_SESSION['student_admission']) ?></td></tr>
            <tr><th>Gender</th><td><?= e($profile['gender'] ?? '—') ?></td></tr>
            <tr><th>Class</th><td><?= e($profile['class_name'] ?? '—') ?></td></tr>
            <tr><th>Class Teacher</th><td><?= e($profile['teacher_name'] ?? '—') ?></td></tr>
        </table>
    </div>

    <div class="card" style="max-width: 480px;">
        <h2><i class="fa-solid fa-lock"></i> Change Password</h2>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= e($success) ?></div>
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
