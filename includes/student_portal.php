<?php
/**
 * includes/student_portal.php
 * ---------------------------------------------------------------------------
 * Student portal session helpers, lockout logic, and access control.
 *
 * Include this where student portal functions are needed:
 *     require_once __DIR__ . '/includes/student_portal.php';
 * ---------------------------------------------------------------------------
 */

declare(strict_types=1);

/** Maximum consecutive failed attempts before lockout. */
define('PORTAL_MAX_ATTEMPTS', 5);

/** Lockout duration in minutes after max failures. */
define('PORTAL_LOCK_MINUTES', 15);

/**
 * Cast a PostgreSQL boolean column value to a PHP bool.
 */
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

/**
 * True if a student portal session is active.
 */
function is_student(): bool
{
    return !empty($_SESSION['student_id']);
}

/**
 * Block access unless a student is logged in. Redirects to login otherwise.
 */
function require_student(): void
{
    if (!is_student()) {
        set_flash('error', 'Please log in to the student portal to continue.');
        redirect('login.php');
    }
}

/**
 * Log a student in from a matched students row (sets session state).
 * Regenerates the session id to prevent fixation.
 */
function login_student(array $student, bool $mustChange = false): void
{
    session_regenerate_id(true);
    $_SESSION['student_id']       = (int)$student['student_id'];
    $_SESSION['student_name']     = $student['full_name'];
    $_SESSION['student_admission'] = $student['admission_no'];
    $_SESSION['portal_must_change'] = $mustChange;
}

/**
 * Return a human-readable lockout message.
 */
function portal_lock_message(int $minutes): string
{
    return 'Too many failed attempts. Please try again in ' . $minutes . ' minute' . ($minutes !== 1 ? 's' : '') . '.';
}

/**
 * Record a successful login: reset failed attempts and update last login.
 */
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

/**
 * Record a failed login attempt.
 * Returns true if the student has just hit the lockout threshold.
 */
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

/**
 * Log a student portal lockout to the activity_log table (best-effort).
 */
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
