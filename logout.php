<?php
/**
 * logout.php
 * ---------------------------------------------------------------------------
 * Destroys the session and returns the user to the login page.
 * Handles both staff (admin/teacher) and student portal sessions.
 * ---------------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/student_portal.php';

// Record the sign-out while the session still knows who this is.
if (is_logged_in()) {
    log_activity('Signed out', 'Session', null);
} elseif (is_student()) {
    // Student portal sign-out — log it without a staff user_id.
    try {
        $pdo = db();
        $stmt = $pdo->prepare(
            'INSERT INTO activity_log (user_id, action, entity, entity_label, detail, created_at)
             VALUES (NULL, :action, :entity, :entity_label, :detail, now())'
        );
        $stmt->execute([
            ':action'      => 'Student portal signed out',
            ':entity'      => 'Student',
            ':entity_label' => $_SESSION['student_name'] ?? 'Unknown',
            ':detail'      => json_encode([
                'student_id'    => $_SESSION['student_id'] ?? null,
                'student_name'  => $_SESSION['student_name'] ?? null,
                'admission_no'  => $_SESSION['student_admission'] ?? null,
            ]),
        ]);
    } catch (\Throwable $e) {
        error_log('logout activity log error: ' . $e->getMessage());
    }
}

logout_user();

// Start a fresh session just to carry the goodbye flash message.
session_start();
set_flash('success', 'You have been logged out.');
redirect('login.php');
