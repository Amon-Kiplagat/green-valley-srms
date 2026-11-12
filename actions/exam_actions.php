<?php
/**
 * actions/exam_actions.php
 * ---------------------------------------------------------------------------
 * Server-side handler for exams CRUD.
 * Called via POST from exams.php.
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

    $examId = (int)($_GET['exam_id'] ?? 0);
    if ($examId <= 0) {
        set_flash('error', 'Invalid exam ID.');
        redirect('exams.php');
    }

    // Fetch context for audit log.
    $stmt = $pdo->prepare('SELECT exam_name, term, year FROM exams WHERE exam_id = :id');
    $stmt->execute([':id' => $examId]);
    $row = $stmt->fetch();

    if (!$row) {
        set_flash('error', 'Examination not found.');
        redirect('exams.php');
    }

    // Check if this exam has results.
    $stmt = $pdo->prepare('SELECT COUNT(*) AS cnt FROM results WHERE exam_id = :id');
    $stmt->execute([':id' => $examId]);
    $cnt = $stmt->fetch()['cnt'];

    if ($cnt > 0) {
        set_flash('error', 'Cannot delete this exam because it has ' . $cnt . ' mark(s) recorded. Delete the marks first.');
        redirect('exams.php');
    }

    $pdo->prepare('DELETE FROM exams WHERE exam_id = :id')
        ->execute([':id' => $examId]);

    $label = $row['exam_name'] . ' (' . $row['term'] . ' ' . $row['year'] . ')';
    log_activity('Deleted', 'Exam', $label);
    set_flash('success', 'Examination deleted.');
    redirect('exams.php');
}

/* ------------------------------------------------------------------ */
/* POST actions: create / update                                      */
/* ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method not allowed.');
}

require_role('admin');
verify_csrf();

if ($action === 'create' || $action === 'update') {
    $examId    = (int)($_POST['exam_id'] ?? 0);
    $examName  = trim($_POST['exam_name'] ?? '');
    $term      = trim($_POST['term'] ?? '');
    $year      = (int)($_POST['year'] ?? 0);

    // Validation.
    if ($examName === '') {
        set_flash('error', 'Exam name is required.');
        redirect('exams.php');
    }

    if (!in_array($term, ['Term 1', 'Term 2', 'Term 3'], true)) {
        set_flash('error', 'Invalid term selected.');
        redirect('exams.php');
    }

    if ($year < 2000 || $year > 2100) {
        set_flash('error', 'Year must be between 2000 and 2100.');
        redirect('exams.php');
    }

    if ($action === 'create') {
        // Check for duplicate.
        $stmt = $pdo->prepare(
            'SELECT exam_id FROM exams
               WHERE exam_name = :name AND term = :term AND year = :year
               LIMIT 1'
        );
        $stmt->execute([
            ':name' => $examName,
            ':term' => $term,
            ':year' => $year,
        ]);
        if ($stmt->fetch()) {
            set_flash('error', 'This examination already exists for the selected term and year.');
            redirect('exams.php');
        }

        $stmt = $pdo->prepare(
            'INSERT INTO exams (exam_name, term, year)
             VALUES (:name, :term, :year)'
        );
        $stmt->execute([
            ':name' => $examName,
            ':term' => $term,
            ':year' => $year,
        ]);

        $label = $examName . ' (' . $term . ' ' . $year . ')';
        log_activity('Added', 'Exam', $label);
        set_flash('success', 'Examination saved.');

    } else {
        $stmt = $pdo->prepare(
            'UPDATE exams
               SET exam_name = :name, term = :term, year = :year
             WHERE exam_id = :id'
        );
        $stmt->execute([
            ':name' => $examName,
            ':term' => $term,
            ':year' => $year,
            ':id'    => $examId,
        ]);

        $label = $examName . ' (' . $term . ' ' . $year . ')';
        log_activity('Updated', 'Exam', $label);
        set_flash('success', 'Examination updated.');
    }

    redirect('exams.php');
}

/* ------------------------------------------------------------------ */
/* Fallback                                                           */
/* ------------------------------------------------------------------ */
set_flash('error', 'Unknown action.');
redirect('exams.php');
