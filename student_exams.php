<?php
/**
 * student_exams.php
 * ---------------------------------------------------------------------------
 * Student portal — view examinations the student has results for.
 * ---------------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/student_portal.php';
require_student();

$pdo = db();

// Get all exams where the student has results.
$stmt = $pdo->prepare(
    "SELECT e.exam_id, e.exam_name, e.term, e.year,
            COUNT(r.result_id) AS subject_count,
            AVG(r.marks) AS average_marks,
            MIN(r.marks) AS lowest_mark,
            MAX(r.marks) AS highest_mark
       FROM exams e
       JOIN results r ON r.exam_id = e.exam_id
      WHERE r.student_id = :id
      GROUP BY e.exam_id, e.exam_name, e.term, e.year
      ORDER BY e.year DESC, e.term, e.exam_name"
);
$stmt->execute([':id' => $_SESSION['student_id']]);
$studentExams = $stmt->fetchAll();

// Get all exams in the system (for showing upcoming/exams without results yet).
$allExams = $pdo->query(
    "SELECT e.exam_id, e.exam_name, e.term, e.year
       FROM exams e
      ORDER BY e.year DESC, e.term, e.exam_name"
)->fetchAll();

// Build a set of exam IDs the student has results for.
$studentExamIds = array_column($studentExams, 'exam_id');

$page_title = 'My Exams';
$page_subtitle = 'Your examination history';
require_once __DIR__ . '/includes/header.php';
?>

<div class="content">
<?php if ($studentExams): ?>
    <div class="card">
        <h2><i class="fa-solid fa-clipboard-list"></i> Examinations Completed</h2>
        <table>
            <thead>
                <tr>
                    <th>Exam Name</th>
                    <th>Term</th>
                    <th>Year</th>
                    <th>Subjects</th>
                    <th>Average</th>
                    <th>Grade</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($studentExams as $ex): ?>
                    <?php
                        $avg = (float)$ex['average_marks'];
                        $grade = calculate_grade($avg);
                        $gradeClass = $grade === 'A' || $grade === 'B' ? 'badge-success' : ($grade === 'F' ? 'badge-danger' : '');
                    ?>
                    <tr>
                        <td><?= e($ex['exam_name']) ?></td>
                        <td><?= e($ex['term']) ?></td>
                        <td><?= e($ex['year']) ?></td>
                        <td><?= e($ex['subject_count']) ?></td>
                        <td><?= e(number_format($avg, 1)) ?></td>
                        <td><span class="badge <?= $gradeClass ?>"><?= e($grade) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="card">
        <h2><i class="fa-solid fa-clipboard-list"></i> No Examinations Yet</h2>
        <p>No examination results have been entered for you yet. Check back after your teacher enters your marks.</p>
    </div>
<?php endif; ?>

<?php if (count($allExams) > count($studentExams)): ?>
    <div class="card">
        <h2><i class="fa-solid fa-clock"></i> All Examinations</h2>
        <table>
            <thead>
                <tr>
                    <th>Exam Name</th>
                    <th>Term</th>
                    <th>Year</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allExams as $ex): ?>
                    <?php $hasResults = in_array((int)$ex['exam_id'], $studentExamIds, true); ?>
                    <tr>
                        <td><?= e($ex['exam_name']) ?></td>
                        <td><?= e($ex['term']) ?></td>
                        <td><?= e($ex['year']) ?></td>
                        <td>
                            <?php if ($hasResults): ?>
                                <span class="badge badge-success">Results Available</span>
                            <?php else: ?>
                                <span class="badge badge-muted">No Results Yet</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
