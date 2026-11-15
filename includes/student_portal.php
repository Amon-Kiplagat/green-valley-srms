<?php

declare(strict_types=1);

define('PORTAL_MAX_ATTEMPTS', 5);
define('PORTAL_LOCK_MINUTES', 15);

function db_bool($value): bool
{
    if (is_bool($value)) {
        return $value;
    }
    if (is_int($value)) {
        return (int)$value !== 0;
    }
    return filter_var((string)$value, FILTER_VALIDATE_BOOLEAN);
}

function is_student(): bool
{
    return !empty($_SESSION['student_id']);
}

function require_student(): void
{
    if (!is_student()) {
        set_flash('error', 'Please log in to the student portal to continue.');
        redirect('login.php');
    }
}

function login_student(array $student, bool $mustChange = false): void
{
    session_regenerate_id(true);
    $_SESSION['student_id']       = (int)$student['student_id'];
    $_SESSION['student_name']     = $student['full_name'];
    $_SESSION['student_admission'] = $student['admission_no'];
    $_SESSION['portal_must_change'] = $mustChange;
}

function portal_lock_message(int $minutes): string
{
    return 'Too many failed attempts. Please try again in ' . $minutes . ' minute' . ($minutes !== 1 ? 's' : '') . '.';
}

function portal_record_success(PDO $pdo, int $studentId): void
{
    $pdo->prepare(
        'UPDATE students
             SET portal_failed_attempts = 0,
                 portal_locked_until    = NULL,
                 portal_last_login      = now()
           WHERE student_id = :id'
    )->execute([':id' => $studentId]);
}

function portal_record_failure(PDO $pdo, int $studentId): bool
{
    $stmt = $pdo->prepare(
        'UPDATE students
             SET portal_failed_attempts = portal_failed_attempts + 1,
                 portal_locked_until    = CASE
                                             WHEN portal_failed_attempts + 1 >= :max
                                             THEN now() + interval \'1 minute\' * :lockMin
                                             ELSE portal_locked_until
                                         END
           WHERE student_id = :id
         RETURNING portal_failed_attempts'
    );
    $stmt->bindValue(':max', PORTAL_MAX_ATTEMPTS, PDO::PARAM_INT);
    $stmt->bindValue(':lockMin', PORTAL_LOCK_MINUTES, PDO::PARAM_INT);
    $stmt->bindValue(':id', $studentId, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch();
    return $row !== false && (int)$row['portal_failed_attempts'] >= PORTAL_MAX_ATTEMPTS;
}

// Log student portal lockout to activity_log (best-effort).
function portal_log_lockout(string $fullName, string $admissionNo): void
{
    try {
        $pdo = db();
        $detail = json_encode([
            'full_name'    => $fullName,
            'admission_no' => $admissionNo,
        ]);
        $pdo->prepare(
            'INSERT INTO activity_log (action, entity, entity_label, detail, created_at)
              VALUES (:action, :entity, :entity_label, :detail, now())'
        )->execute([
            ':action'      => 'Student portal locked out',
            ':entity'      => 'Student',
            ':entity_label' => $fullName . ' (' . $admissionNo . ')',
            ':detail'      => $detail,
        ]);
    } catch (\Throwable $e) {
        error_log('portal_log_lockout error: ' . $e->getMessage());
    }
}
