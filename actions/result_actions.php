<?php
/**
 * actions/result_actions.php
 * ---------------------------------------------------------------------------
 * Server-side handler for results (marks) CRUD.
 * Called via POST from results.php.
 * ---------------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_login();

$pdo = db();
$action = (string)($_POST['action'] ?? $_GET['action'] ?? '');

/* ------------------------------------------------------------------ */
/* DELETE (via GET with CSRF token in URL)                             */
/* ------------------------------------------------------------------ */
if ($action === 'delete') {
    require_role('admin');
    verify_csrf();

    $resultId = (int)($_GET['result_id'] ?? 0);
    if ($resultId <= 0) {
        set_flash('error', 'Invalid result ID.');
        redirect('results.php');
    }

    // Fetch context for audit log.
    $stmt = $pdo->prepare(
        'SELECT s.full_name, s.admission_no, sub.subject_name, e.exam_name, e.term, e.year
           FROM results r
           JOIN students s   ON s.student_id = r.student_id
           JOIN subjects sub ON sub.subject_id = r.subject_id
           JOIN exams e      ON e.exam_id = r.exam_id
          WHERE r.result_id = :id'
    );
    $stmt->execute([':id' => $resultId]);
    $row = $stmt->fetch();

    if (!$row) {
        set_flash('error', 'Result not found.');
        redirect('results.php');
    }

    $pdo->prepare('DELETE FROM results WHERE result_id = :id')
        ->execute([':id' => $resultId]);

    $label = $row['full_name'] . ' - ' . $row['subject_name'] . ' (' . $row['exam_name'] . ')';
    log_activity('Deleted', 'Marks', $label);
    set_flash('success', 'Mark deleted.');
    redirect('results.php');
}

/* ------------------------------------------------------------------ */
/* POST actions: create / update                                      */
/* ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method not allowed.');
}

verify_csrf();

if ($action === 'create' || $action === 'update') {
    $resultId  = (int)($_POST['result_id'] ?? 0);
    $studentId = (int)($_POST['student_id'] ?? 0);
    $subjectId = (int)($_POST['subject_id'] ?? 0);
    $examId    = (int)($_POST['exam_id'] ?? 0);
    $marks     = (float)($_POST['marks'] ?? 0);

    // Validation.
    if ($studentId <= 0 || $subjectId <= 0 || $examId <= 0) {
        set_flash('error', 'Student, subject and exam are required.');
        redirect('results.php');
    }

    if ($marks < 0 || $marks > 100) {
        set_flash('error', 'Marks must be between 0 and 100.');
        redirect('results.php');
    }

    $grade = calculate_grade($marks);

    if ($action === 'create') {
        // Check for duplicate.
        $stmt = $pdo->prepare(
            'SELECT result_id FROM results
               WHERE student_id = :sid AND subject_id = :subid AND exam_id = :eid
               LIMIT 1'
        );
        $stmt->execute([
            ':sid'  => $studentId,
            ':subid' => $subjectId,
            ':eid'  => $examId,
        ]);
        if ($stmt->fetch()) {
            set_flash('error', 'This mark has already been recorded for the student, subject and exam.');
            redirect('results.php');
        }

        $stmt = $pdo->prepare(
            'INSERT INTO results (student_id, subject_id, exam_id, marks, grade)
             VALUES (:sid, :subid, :eid, :marks, :grade)'
        );
        $stmt->execute([
            ':sid'   => $studentId,
            ':subid' => $subjectId,
            ':eid'   => $examId,
            ':marks' => $marks,
            ':grade' => $grade,
        ]);

        // Fetch context for audit log.
        $stmt = $pdo->prepare(
            'SELECT s.full_name, s.admission_no, sub.subject_name, e.exam_name
               FROM students s
               JOIN subjects sub ON sub.subject_id = :subid
               JOIN exams e ON e.exam_id = :eid
              WHERE s.student_id = :sid'
        );
        $stmt->execute([':sid' => $studentId, ':subid' => $subjectId, ':eid' => $examId]);
        $ctx = $stmt->fetch();
        $label = ($ctx['full_name'] ?? 'Unknown') . ' - ' . ($ctx['subject_name'] ?? '?') . ' (' . ($ctx['exam_name'] ?? '?') . ')';
        log_activity('Added', 'Marks', $label);
        set_flash('success', 'Mark saved.');

    } else {
        $stmt = $pdo->prepare(
            'UPDATE results
               SET marks = :marks, grade = :grade
             WHERE result_id = :id'
        );
        $stmt->execute([
            ':marks' => $marks,
            ':grade' => $grade,
            ':id'    => $resultId,
        ]);

        // Fetch context for audit log.
        $stmt = $pdo->prepare(
            'SELECT s.full_name, s.admission_no, sub.subject_name, e.exam_name
               FROM results r
               JOIN students s   ON s.student_id = r.student_id
               JOIN subjects sub ON sub.subject_id = r.subject_id
               JOIN exams e      ON e.exam_id = r.exam_id
              WHERE r.result_id = :id'
        );
        $stmt->execute([':id' => $resultId]);
        $ctx = $stmt->fetch();
        $label = ($ctx['full_name'] ?? 'Unknown') . ' - ' . ($ctx['subject_name'] ?? '?') . ' (' . ($ctx['exam_name'] ?? '?') . ')';
        log_activity('Updated', 'Marks', $label);
        set_flash('success', 'Mark updated.');
    }

    redirect('results.php');
}

/* ------------------------------------------------------------------ */
/* Fallback                                                           */
/* ------------------------------------------------------------------ */
set_flash('error', 'Unknown action.');
redirect('results.php');
