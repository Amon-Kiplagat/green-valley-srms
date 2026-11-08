<?php
/**
 * student_reports.php
 * ---------------------------------------------------------------------------
 * Student portal — generate a printable report card for the logged-in student.
 * Auto-selects the student; the student picks an examination.
 * ---------------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/student_portal.php';
require_student();

$pdo = db();
$studentId = $_SESSION['student_id'];

// Student info.
$stmt = $pdo->prepare(
    "SELECT s.admission_no, s.full_name, s.gender, c.class_name
       FROM students s
       LEFT JOIN classes c ON c.class_id = s.class_id
      WHERE s.student_id = :id"
);
$stmt->execute([':id' => $studentId]);
$student = $stmt->fetch();

// All exams for dropdown.
$exams = $pdo->query(
    "SELECT exam_id, exam_name, term, year FROM exams ORDER BY year DESC, term"
)->fetchAll();

$examId    = (int)input('exam_id');
$examLabel = '';
$rows      = [];
$summary   = ['count' => 0, 'total' => 0.0, 'average' => 0.0, 'grade' => '', 'position' => null, 'class_size' => null];

if ($examId > 0) {
    // Fetch marks for the chosen exam.
    $stmt = $pdo->prepare(
        "SELECT sub.subject_name, e.exam_name, e.term, e.year, r.marks, r.grade,
                r.class_teacher_remark, r.subject_teacher_remark
           FROM results r
           JOIN subjects sub ON sub.subject_id = r.subject_id
           JOIN exams e      ON e.exam_id = r.exam_id
          WHERE r.student_id = :sid AND r.exam_id = :eid
          ORDER BY sub.subject_name"
    );
    $stmt->execute([':sid' => $studentId, ':eid' => $examId]);
    $rows = $stmt->fetchAll();

    // Summary.
    $summary['count'] = count($rows);
    foreach ($rows as $r) {
        $summary['total'] += (float)$r['marks'];
    }
    if ($summary['count'] > 0) {
        $summary['average'] = $summary['total'] / $summary['count'];
        $summary['grade']   = calculate_grade($summary['average']);
    }

    // Position in class.
    $posStmt = $pdo->prepare(
        "WITH exam_avg AS (
             SELECT s.student_id, AVG(r.marks) AS avg_marks
               FROM students s
               JOIN results r ON r.student_id = s.student_id
              WHERE r.exam_id = :eid
                AND s.class_id = (SELECT class_id FROM students WHERE student_id = :sid)
              GROUP BY s.student_id
         ),
         ranked AS (
             SELECT student_id,
                    RANK() OVER (ORDER BY avg_marks DESC) AS position,
                    COUNT(*) OVER () AS class_size
               FROM exam_avg
         )
         SELECT position, class_size FROM ranked WHERE student_id = :sid"
    );
    $posStmt->execute([':eid' => $examId, ':sid' => $studentId]);
    if ($pos = $posStmt->fetch()) {
        $summary['position']   = (int)$pos['position'];
        $summary['class_size'] = (int)$pos['class_size'];
    }

    // Exam label.
    foreach ($exams as $ex) {
        if ((int)$ex['exam_id'] === $examId) {
            $examLabel = $ex['exam_name'] . ' - ' . $ex['term'] . ' ' . $ex['year'];
        }
    }
}

/** Format a numeric mark without trailing zeros. */
function fmt_marks($m): string
{
    return rtrim(rtrim(number_format((float)$m, 2, '.', ''), '0'), '.');
}

$page_title = 'My Reports';
$page_subtitle = 'Generate a printable report card';
require_once __DIR__ . '/includes/header.php';
?>

<div class="content">
    <div class="card no-print">
        <h2><i class="fa-solid fa-chart-column"></i> Generate Report</h2>
        <form action="student_reports.php" method="get" class="inline"
              style="display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:15px;">
            <div><label>Examination</label>
                <select name="exam_id" required>
                    <option value="">-- Select Examination --</option>
                    <?php foreach ($exams as $ex): ?>
                        <option value="<?= e($ex['exam_id']) ?>" <?= ($examId == $ex['exam_id']) ? 'selected' : '' ?>>
                            <?= e($ex['exam_name'] . ' - ' . $ex['term'] . ' ' . $ex['year']) ?>
                        </option>
                    <?php endforeach; ?>
                </select></div>
            <div style="align-self:end;"><button type="submit">Generate Report</button></div>
        </form>
    </div>

    <?php if ($examId > 0 && $student): ?>
    <div class="card report-card" id="report-card">
        <!-- Report Header -->
        <div class="report-header">
            <img class="report-logo" src="icons/icon-192.png" alt="School Logo">
            <div class="report-school">Green Valley Secondary School</div>
            <div class="report-title">Student Academic Report</div>
            <div class="report-student">
                <table>
                    <tr><th>Full Name</th><td><?= e($student['full_name']) ?></td></tr>
                    <tr><th>Admission No</th><td><?= e($student['admission_no']) ?></td></tr>
                    <tr><th>Gender</th><td><?= e($student['gender'] ?? '—') ?></td></tr>
                    <tr><th>Class</th><td><?= e($student['class_name'] ?? '—') ?></td></tr>
                    <tr><th>Examination</th><td><?= e($examLabel) ?></td></tr>
                </table>
            </div>
        </div>

        <?php if ($rows): ?>
            <table>
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Marks</th>
                        <th>Grade</th>
                        <th>Remark</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><?= e($r['subject_name']) ?></td>
                            <td><?= e(fmt_marks($r['marks'])) ?></td>
                            <td><span class="grade"><?= e($r['grade']) ?></span></td>
                            <td><?= e($r['subject_teacher_remark'] ?? $r['class_teacher_remark'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr>
                        <th>Total</th>
                        <th><?= e(fmt_marks($summary['total'])) ?></th>
                        <th colspan="2"></th>
                    </tr>
                </tbody>
            </table>

            <div class="report-summary">
                <p>
                    <strong>Subjects:</strong> <?= e($summary['count']) ?> &nbsp;|&nbsp;
                    <strong>Average:</strong> <?= e(fmt_marks($summary['average'])) ?> &nbsp;|&nbsp;
                    <strong>Mean Grade:</strong> <span class="grade"><?= e($summary['grade']) ?></span>
                    <?php if ($summary['position'] !== null): ?>
                        &nbsp;|&nbsp; <strong>Position in class:</strong>
                        <?= e($summary['position']) ?> of <?= e($summary['class_size']) ?>
                    <?php endif; ?>
                </p>
            </div>

            <div class="no-print" style="margin-top:14px;">
                <button class="btn btn-primary" onclick="window.print()">
                    <i class="fa-solid fa-print"></i> Print Report
                </button>
                <button class="btn btn-secondary" onclick="downloadPdf()">
                    <i class="fa-solid fa-file-pdf"></i> Download PDF
                </button>
            </div>
        <?php else: ?>
            <p>No marks found for this examination.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<style>
.report-logo {
    width: 70px;
    height: 70px;
    margin-bottom: 8px;
    object-fit: contain;
}
.report-header {
    text-align: center;
}
.report-summary {
    margin-top: 15px;
    padding: 12px;
    background: #f8fafc;
    border-radius: 8px;
    font-size: 14px;
}
</style>

<!-- html2pdf.js for PDF download -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
function downloadPdf() {
    const element = document.getElementById('report-card');
    const name = '<?= e($student['full_name'] ?? 'student') ?>';
    const filename = name.replace(/[^a-zA-Z0-9]/g, '_') + '_report.pdf';
    
    const opt = {
        margin: 10,
        filename: filename,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };
    
    // Hide buttons during PDF generation
    const noPrint = document.querySelectorAll('.no-print');
    noPrint.forEach(el => el.style.display = 'none');
    
    html2pdf().set(opt).from(element).save().then(() => {
        // Show buttons again
        noPrint.forEach(el => el.style.display = '');
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
