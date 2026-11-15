<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/student_portal.php';

if (is_logged_in()) {
    log_activity('Signed out', 'Session', null);
} elseif (is_student()) {
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

session_start();
set_flash('success', 'You have been logged out.');
redirect('login.php');
