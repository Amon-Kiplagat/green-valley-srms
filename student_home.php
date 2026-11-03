<?php
/**
 * student_home.php
 * ---------------------------------------------------------------------------
 * Student portal home page — shows student info and links to results.
 * ---------------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/student_portal.php';
require_student();

// Redirect to password change if required
if (!empty($_SESSION['portal_must_change'])) {
    redirect('student_password.php');
}

$pdo = db();
$stmt = $pdo->prepare(
    'SELECT s.full_name, s.admission_no, c.class_name, c.set_teacher_id,
            t.full_name AS teacher_name
       FROM students s
       JOIN classes c ON c.class_id = s.class_id
       LEFT JOIN teachers t ON t.teacher_id = c.set_teacher_id
      WHERE s.student_id = :id'
);
$stmt->execute([':id' => $_SESSION['student_id']]);
$student = $stmt->fetch();

$page_title = 'Student Portal';
$page_subtitle = 'Welcome, ' . ($student['full_name'] ?? $_SESSION['student_name']);
require_once __DIR__ . '/includes/header.php';
?>

<div class="content">
<div class="card">
    <h2><i class="fa-solid fa-user-graduate"></i> My Information</h2>
    <table>
        <tr><th>Full Name</th><td><?= e($student['full_name'] ?? $_SESSION['student_name']) ?></td></tr>
        <tr><th>Admission No</th><td><?= e($student['admission_no'] ?? $_SESSION['student_admission']) ?></td></tr>
        <tr><th>Class</th><td><?= e($student['class_name'] ?? '—') ?></td></tr>
        <tr><th>Class Teacher</th><td><?= e($student['teacher_name'] ?? '—') ?></td></tr>
    </table>
</div>

<div class="card">
    <h2><i class="fa-solid fa-chart-column"></i> Quick Links</h2>
    <p>
        <a class="btn btn-primary" href="student_results.php">
            <i class="fa-solid fa-eye"></i> View My Results
        </a>
    </p>
</div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
