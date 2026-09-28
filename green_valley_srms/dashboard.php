<?php
/**
 * dashboard.php
 * ---------------------------------------------------------------------------
 * Landing page after login. Shows live counts pulled from PostgreSQL and the
 * most recent marks entered. Replaces dashboard.html.
 * ---------------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();

$pdo = db();

/** Small helper to fetch a single COUNT(*) value. */
$count = static function (string $table) use ($pdo): int {
    // Table name is a hard-coded literal (never user input), so it is safe here.
    return (int)$pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
};

$totalStudents = $count('students');
$totalTeachers = $count('teachers');
$totalClasses  = $count('classes');
$totalSubjects = $count('subjects');
$totalExams    = $count('exams');

// Most recent marks entered (real data, joined across tables).
$recent = $pdo->query(
    "SELECT s.full_name AS student, sub.subject_name AS subject,
            e.exam_name, e.term, e.year, r.marks, r.grade, r.created_at
       FROM results r
       JOIN students s   ON s.student_id = r.student_id
       JOIN subjects sub ON sub.subject_id = r.subject_id
       JOIN exams e      ON e.exam_id = r.exam_id
      ORDER BY r.created_at DESC
      LIMIT 5"
)->fetchAll();

$page_title = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<div class="cards">
    <div class="stat"><h3>Total Students</h3><p><?= e($totalStudents) ?></p></div>
    <div class="stat"><h3>Total Teachers</h3><p><?= e($totalTeachers) ?></p></div>
    <div class="stat"><h3>Total Classes</h3><p><?= e($totalClasses) ?></p></div>
    <div class="stat"><h3>Total Subjects</h3><p><?= e($totalSubjects) ?></p></div>
    <div class="stat"><h3>Total Examinations</h3><p><?= e($totalExams) ?></p></div>
</div>

<div class="card">
    <h2>Recently Entered Marks</h2>
    <?php if ($recent): ?>
        <table>
            <tr><th>Student</th><th>Subject</th><th>Examination</th><th>Marks</th><th>Grade</th></tr>
            <?php foreach ($recent as $row): ?>
                <tr>
                    <td><?= e($row['student']) ?></td>
                    <td><?= e($row['subject']) ?></td>
                    <td><?= e($row['exam_name'] . ' - ' . $row['term'] . ' ' . $row['year']) ?></td>
                    <td><?= e(rtrim(rtrim((string)$row['marks'], '0'), '.')) ?></td>
                    <td><span class="grade"><?= e($row['grade']) ?></span></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p>No marks have been entered yet. Go to <a href="results.php">Results</a> to add some.</p>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
