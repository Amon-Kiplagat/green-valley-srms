<?php
/**
 * student_results.php
 * ---------------------------------------------------------------------------
 * Student portal — view all exam results for the logged-in student.
 * ---------------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/student_portal.php';
require_student();

$pdo = db();
$stmt = $pdo->prepare(
    'SELECT sub.subject_name, e.exam_name, e.term, e.year,
            r.marks, r.grade, r.class_teacher_remark, r.subject_teacher_remark
       FROM results r
       JOIN subjects sub ON sub.subject_id = r.subject_id
       JOIN exams e ON e.exam_id = r.exam_id
      WHERE r.student_id = :id
      ORDER BY e.year DESC, e.term DESC, sub.subject_name ASC'
);
$stmt->execute([':id' => $_SESSION['student_id']]);
$results = $stmt->fetchAll();

$page_title = 'My Results';
$page_subtitle = 'Your examination results';
require_once __DIR__ . '/includes/header.php';
?>

<div class="content">
<div class="card">
    <h2><i class="fa-solid fa-chart-column"></i> My Results</h2>
    <?php if ($results): ?>
        <table>
            <tr>
                <th>Subject</th>
                <th>Exam</th>
                <th>Term</th>
                <th>Year</th>
                <th>Marks</th>
                <th>Grade</th>
                <th>Remark</th>
            </tr>
            <?php foreach ($results as $row): ?>
                <tr>
                    <td><?= e($row['subject_name']) ?></td>
                    <td><?= e($row['exam_name']) ?></td>
                    <td><?= e($row['term']) ?></td>
                    <td><?= e($row['year']) ?></td>
                    <td><?= e(rtrim(rtrim((string)$row['marks'], '0'), '.')) ?></td>
                    <td><span class="grade"><?= e($row['grade']) ?></span></td>
                    <td><?= e($row['subject_teacher_remark'] ?? $row['class_teacher_remark'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p>No results have been entered yet.</p>
    <?php endif; ?>
</div>

<div class="card">
    <a class="btn btn-secondary" href="student_home.php">
        <i class="fa-solid fa-arrow-left"></i> Back to Home
    </a>
</div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
