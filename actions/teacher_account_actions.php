<?php
/**
 * actions/teacher_account_actions.php
 * ---------------------------------------------------------------------------
 * Server-side handler for teacher account management.
 * Called via POST/GET from teacher_accounts.php.
 * Handles: create_account, reset_password, activate, deactivate.
 * ---------------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = db();
$action = (string)($_POST['action'] ?? $_GET['action'] ?? '');

/* ------------------------------------------------------------------ */
/* ACTIVATE / DEACTIVATE (via GET with CSRF token in URL)              */
/* ------------------------------------------------------------------ */
if ($action === 'activate' || $action === 'deactivate') {
    verify_csrf();

    $teacherId = (int)($_GET['teacher_id'] ?? 0);
    if ($teacherId <= 0) {
        set_flash('error', 'Invalid teacher ID.');
        redirect('teacher_accounts.php');
    }

    // Find the user account linked to this teacher.
    $stmt = $pdo->prepare(
        'SELECT u.user_id, u.username, t.full_name
           FROM users u
           JOIN teachers t ON t.teacher_id = u.teacher_id
          WHERE u.teacher_id = :id AND u.role = \'teacher\''
    );
    $stmt->execute([':id' => $teacherId]);
    $user = $stmt->fetch();

    if (!$user) {
        set_flash('error', 'Teacher account not found.');
        redirect('teacher_accounts.php');
    }

    if ($action === 'activate') {
        $pdo->prepare('UPDATE users SET is_active = TRUE WHERE user_id = :id')
            ->execute([':id' => $user['user_id']]);
        set_flash('success', "Account for {$user['full_name']} activated.");
    } else {
        $pdo->prepare('UPDATE users SET is_active = FALSE WHERE user_id = :id')
            ->execute([':id' => $user['user_id']]);
        set_flash('success', "Account for {$user['full_name']} deactivated.");
    }

    log_activity($action === 'activate' ? 'Activated' : 'Deactivated', 'Teacher Account', "{$user['full_name']} ({$user['username']})");
    redirect('teacher_accounts.php');
}

/* ------------------------------------------------------------------ */
/* POST actions                                                        */
/* ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method not allowed.');
}

verify_csrf();

/* ------------------------------------------------------------------ */
/* CREATE ACCOUNT                                                      */
/* ------------------------------------------------------------------ */
if ($action === 'create_account') {
    $teacherId = (int)($_POST['teacher_id'] ?? 0);
    $username  = trim((string)($_POST['username'] ?? ''));
    $password  = (string)($_POST['password'] ?? '');
    $confirm   = (string)($_POST['confirm'] ?? '');

    // Validation.
    if ($teacherId <= 0) {
        set_flash('error', 'Invalid teacher ID.');
        redirect('teacher_accounts.php');
    }

    if ($username === '' || !preg_match('/^[a-z0-9]+$/', $username)) {
        set_flash('error', 'Username must contain only lowercase letters and numbers.');
        redirect('teacher_accounts.php');
    }

    if (strlen($password) < 8) {
        set_flash('error', 'Password must be at least 8 characters.');
        redirect('teacher_accounts.php');
    }

    if ($password !== $confirm) {
        set_flash('error', 'Password and confirmation do not match.');
        redirect('teacher_accounts.php');
    }

    // Check if this teacher already has an account.
    $stmt = $pdo->prepare(
        'SELECT u.user_id FROM users u WHERE u.teacher_id = :id AND u.role = \'teacher\''
    );
    $stmt->execute([':id' => $teacherId]);
    if ($stmt->fetch()) {
        set_flash('error', 'This teacher already has a login account.');
        redirect('teacher_accounts.php');
    }

    // Check if username is already taken.
    $stmt = $pdo->prepare('SELECT user_id FROM users WHERE username = :u LIMIT 1');
    $stmt->execute([':u' => $username]);
    if ($stmt->fetch()) {
        set_flash('error', 'Username is already taken. Choose a different one.');
        redirect('teacher_accounts.php');
    }

    // Fetch teacher details.
    $stmt = $pdo->prepare('SELECT full_name, email FROM teachers WHERE teacher_id = :id');
    $stmt->execute([':id' => $teacherId]);
    $teacher = $stmt->fetch();

    if (!$teacher) {
        set_flash('error', 'Teacher not found.');
        redirect('teacher_accounts.php');
    }

    // Create the account.
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare(
        'INSERT INTO users (username, password_hash, full_name, email, role, teacher_id, is_active)
         VALUES (:u, :h, :fn, :em, \'teacher\', :tid, TRUE)'
    );
    $stmt->execute([
        ':u'  => $username,
        ':h'  => $hash,
        ':fn' => $teacher['full_name'],
        ':em' => $teacher['email'] !== '' ? $teacher['email'] : null,
        ':tid' => $teacherId,
    ]);

    log_activity('Created account for', 'Teacher', "{$teacher['full_name']} ($username)");
    set_flash('success', "Account created for {$teacher['full_name']}. Username: {$username}.");
    redirect('teacher_accounts.php');
}

/* ------------------------------------------------------------------ */
/* RESET PASSWORD                                                      */
/* ------------------------------------------------------------------ */
if ($action === 'reset_password') {
    $teacherId = (int)($_POST['teacher_id'] ?? 0);
    $password  = (string)($_POST['password'] ?? '');
    $confirm   = (string)($_POST['confirm'] ?? '');

    if ($teacherId <= 0) {
        set_flash('error', 'Invalid teacher ID.');
        redirect('teacher_accounts.php');
    }

    if (strlen($password) < 8) {
        set_flash('error', 'Password must be at least 8 characters.');
        redirect('teacher_accounts.php');
    }

    if ($password !== $confirm) {
        set_flash('error', 'Password and confirmation do not match.');
        redirect('teacher_accounts.php');
    }

    // Find the user account linked to this teacher.
    $stmt = $pdo->prepare(
        'SELECT u.user_id, u.username, t.full_name
           FROM users u
           JOIN teachers t ON t.teacher_id = u.teacher_id
          WHERE u.teacher_id = :id AND u.role = \'teacher\''
    );
    $stmt->execute([':id' => $teacherId]);
    $user = $stmt->fetch();

    if (!$user) {
        set_flash('error', 'Teacher account not found.');
        redirect('teacher_accounts.php');
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->prepare('UPDATE users SET password_hash = :h WHERE user_id = :id')
        ->execute([':h' => $hash, ':id' => $user['user_id']]);

    log_activity('Reset password for', 'Teacher Account', "{$user['full_name']} ({$user['username']})");
    set_flash('success', "Password reset for {$user['full_name']}.");
    redirect('teacher_accounts.php');
}

/* ------------------------------------------------------------------ */
/* Fallback                                                            */
/* ------------------------------------------------------------------ */
set_flash('error', 'Unknown action.');
redirect('teacher_accounts.php');
