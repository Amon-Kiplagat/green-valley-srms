<?php
/**
 * signup.php
 * ---------------------------------------------------------------------------
 * Teacher self-signup page. Creates a new teacher record and user account.
 * ---------------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

// Already signed in?
if (is_logged_in()) {
    redirect('dashboard.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = (string)($_POST['full_name'] ?? '');
    $email    = (string)($_POST['email'] ?? '');
    $phone    = (string)($_POST['phone'] ?? '');
    $username = (string)($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $confirm  = (string)($_POST['confirm'] ?? '');

    if ($fullName === '' || $username === '' || $password === '') {
        $error = 'All fields are required.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $pdo = db();

        // Check if username exists
        $stmt = $pdo->prepare('SELECT user_id FROM users WHERE username = :u');
        $stmt->execute([':u' => $username]);
        if ($stmt->fetch()) {
            $error = 'Username already taken.';
        } else {
            // Insert teacher
            $pdo->prepare(
                'INSERT INTO teachers (full_name, email, phone, is_active)
                 VALUES (:name, :email, :phone, TRUE)'
            )->execute([
                ':name'  => $fullName,
                ':email' => $email,
                ':phone' => $phone,
            ]);
            $teacherId = (int)$pdo->lastInsertId('teacher_id');

            // Insert user
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare(
                'INSERT INTO users (username, password_hash, full_name, role, teacher_id, email, phone, is_active)
                 VALUES (:u, :h, :name, :role, :tid, :email, :phone, TRUE)'
            )->execute([
                ':u'    => $username,
                ':h'    => $hash,
                ':name' => $fullName,
                ':role' => 'teacher',
                ':tid'  => $teacherId,
                ':email'=> $email,
                ':phone'=> $phone,
            ]);

            log_activity('Teacher signup', 'User', $fullName, [
                'username'  => $username,
                'teacher_id'=> $teacherId,
            ]);

            $success = 'Account created! You can now log in.';
        }
    }
}

$page_title = 'Teacher Signup';
$page_subtitle = 'Create a new account';
require_once __DIR__ . '/includes/header.php';
?>

<div class="content">
<div class="card" style="max-width: 480px;">
    <h2><i class="fa-solid fa-chalkboard-user"></i> Teacher Signup</h2>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
        <p><a class="btn btn-primary" href="login.php">
            <i class="fa-solid fa-right-to-bracket"></i> Go to Login
        </a></p>
    <?php else: ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name" required
                       placeholder="e.g. John Doe">
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email"
                       placeholder="you@example.com">
            </div>

            <div class="form-group">
                <label for="phone">Phone</label>
                <input type="tel" id="phone" name="phone"
                       placeholder="0712 345 678">
            </div>

            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required
                       placeholder="Choose a username">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required minlength="6"
                       placeholder="At least 6 characters">
            </div>

            <div class="form-group">
                <label for="confirm">Confirm Password</label>
                <input type="password" id="confirm" name="confirm" required minlength="6"
                       placeholder="Repeat your password">
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-user-plus"></i> Create Account
            </button>
        </form>

        <p style="margin-top: 16px; font-size: 0.85rem;">
            Already have an account? <a href="login.php">Log in</a>
        </p>
    <?php endif; ?>
</div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
