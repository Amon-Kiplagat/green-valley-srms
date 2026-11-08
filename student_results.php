<?php
/**
 * student_results.php
 * ---------------------------------------------------------------------------
 * Student portal — view, print and download exam results.
 * ---------------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/student_portal.php';
require_student();

$pdo = db();

// Student info for the report header.
$stmt = $pdo->prepare(
    'SELECT s.full_name, s.admission_no, s.gender, c.class_name
       FROM students s
       LEFT JOIN classes c ON c.class_id = s.class_id
      WHERE s.student_id = :id'
);
$stmt->execute([':id' => $_SESSION['student_id']]);
$studentInfo = $stmt->fetch();

// All results for the student.
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

// Group results by exam for summary.
$exams = [];
foreach ($results as $row) {
    $key = $row['exam_name'] . ' - ' . $row['term'] . ' ' . $row['year'];
    if (!isset($exams[$key])) {
        $exams[$key] = ['label' => $key, 'rows' => [], 'total' => 0.0, 'count' => 0];
    }
    $exams[$key]['rows'][] = $row;
    $exams[$key]['total'] += (float)$row['marks'];
    $exams[$key]['count']++;
}
foreach ($exams as &$ex) {
    $ex['average'] = $ex['count'] > 0 ? $ex['total'] / $ex['count'] : 0.0;
    $ex['grade'] = $ex['count'] > 0 ? calculate_grade($ex['average']) : '';
}
unset($ex);

// Handle CSV download.
if (isset($_GET['download']) && $_GET['download'] === 'csv' && $results) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="'
        . preg_replace('/[^a-zA-Z0-9]/', '_', $studentInfo['full_name'] ?? 'student')
        . '_results.csv');
    $out = fopen('php://output', 'w');
    // BOM for Excel UTF-8 compatibility.
    fprintf($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Subject', 'Exam', 'Term', 'Year', 'Marks', 'Grade', 'Remark']);
    foreach ($results as $row) {
        fputcsv($out, [
            $row['subject_name'],
            $row['exam_name'],
            $row['term'],
            $row['year'],
            rtrim(rtrim((string)$row['marks'], '0'), '.'),
            $row['grade'],
            $row['subject_teacher_remark'] ?? $row['class_teacher_remark'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}

$page_title = 'My Results';
$page_subtitle = 'Your examination results';
require_once __DIR__ . '/includes/header.php';
?>

<div class="content">
<?php if ($results): ?>
<div class="card report-card" id="report-card">
    <!-- Report Header -->
    <div class="report-header">
        <img class="report-logo" src="icons/icon-192.png" alt="School Logo">
        <div class="report-school">Green Valley Secondary School</div>
        <div class="report-title">Student Academic Report</div>
        <div class="report-student">
            <table>
                <tr><th>Full Name</th><td><?= e($studentInfo['full_name'] ?? $_SESSION['student_name']) ?></td></tr>
                <tr><th>Admission No</th><td><?= e($studentInfo['admission_no'] ?? $_SESSION['student_admission']) ?></td></tr>
                <tr><th>Gender</th><td><?= e($studentInfo['gender'] ?? '—') ?></td></tr>
                <tr><th>Class</th><td><?= e($studentInfo['class_name'] ?? '—') ?></td></tr>
            </table>
        </div>
    </div>

    <!-- Results by Exam -->
    <?php foreach ($exams as $examKey => $exam): ?>
    <div class="report-exam">
        <h3><?= e($exam['label']) ?></h3>
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
                <?php foreach ($exam['rows'] as $row): ?>
                <tr>
                    <td><?= e($row['subject_name']) ?></td>
                    <td><?= e(rtrim(rtrim((string)$row['marks'], '0'), '.')) ?></td>
                    <td><span class="grade"><?= e($row['grade']) ?></span></td>
                    <td><?= e($row['subject_teacher_remark'] ?? $row['class_teacher_remark'] ?? '—') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th>Total</th>
                    <th><?= e(rtrim(rtrim(number_format($exam['total'], 2), '0'), '.')) ?></th>
                    <th colspan="2"></th>
                </tr>
                <tr>
                    <th>Average</th>
                    <th><?= e(rtrim(rtrim(number_format($exam['average'], 2), '0'), '.')) ?></th>
                    <th><span class="grade"><?= e($exam['grade']) ?></span></th>
                    <th></th>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php endforeach; ?>

    <!-- Report Footer -->
    <div class="report-footer">
        <p>Generated on <?= e(date('d M Y, H:i')) ?></p>
        <p class="report-note">This is a system-generated report. For official use, please contact the school administration.</p>
    </div>
</div>

<!-- Action buttons (hidden when printing) -->
<div class="card no-print">
    <h2><i class="fa-solid fa-download"></i> Export</h2>
    <p>
        <button class="btn btn-primary" onclick="window.print()">
            <i class="fa-solid fa-print"></i> Print Report
        </button>
        <button class="btn btn-secondary" onclick="downloadPdf()">
            <i class="fa-solid fa-file-pdf"></i> Download PDF
        </button>
        <a class="btn btn-secondary" href="student_home.php">
            <i class="fa-solid fa-arrow-left"></i> Back to Home
        </a>
    </p>
</div>

<?php else: ?>
<div class="card">
    <h2><i class="fa-solid fa-chart-column"></i> My Results</h2>
    <p>No results have been entered yet.</p>
    <p><a class="btn btn-secondary" href="student_home.php">
        <i class="fa-solid fa-arrow-left"></i> Back to Home
    </a></p>
</div>
<?php endif; ?>
</div>

<style>
/* Print-friendly styles */
@media print {
    .sidebar, .topbar, .app-foot, .no-print, .nav-toggle, .nav-backdrop {
        display: none !important;
    }
    .main {
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
    }
    .content {
        padding: 0 !important;
    }
    .card {
        box-shadow: none !important;
        border: none !important;
        padding: 0 !important;
        margin: 0 !important;
    }
    .report-card {
        padding: 20px !important;
    }
    .report-header {
        text-align: center;
        border-bottom: 2px solid #000;
        padding-bottom: 15px;
        margin-bottom: 20px;
    }
    .report-school {
        font-size: 18px;
        font-weight: 700;
        text-transform: uppercase;
    }
    .report-title {
        font-size: 14px;
        color: #555;
        margin: 5px 0 15px;
    }
    .report-student table {
        margin: 0 auto;
        text-align: left;
    }
    .report-exam h3 {
        margin-top: 20px;
        margin-bottom: 10px;
        font-size: 14px;
        border-bottom: 1px solid #ccc;
        padding-bottom: 5px;
    }
    .report-exam table {
        width: 100%;
        border-collapse: collapse;
    }
    .report-exam th, .report-exam td {
        border: 1px solid #ccc;
        padding: 6px 10px;
    }
    .report-footer {
        margin-top: 30px;
        padding-top: 15px;
        border-top: 1px solid #ccc;
        font-size: 12px;
        color: #666;
        text-align: center;
    }
    .grade {
        font-weight: 700;
    }
}

/* Screen styles for report */
.report-header {
    text-align: center;
    border-bottom: 2px solid #e2e8f0;
    padding-bottom: 15px;
    margin-bottom: 20px;
}
.report-logo {
    width: 70px;
    height: 70px;
    margin-bottom: 8px;
    object-fit: contain;
}
.report-school {
    font-size: 20px;
    font-weight: 700;
    text-transform: uppercase;
    color: #1e293b;
}
.report-title {
    font-size: 14px;
    color: #64748b;
    margin: 5px 0 15px;
}
.report-student table {
    margin: 0 auto;
    text-align: left;
    border-collapse: collapse;
}
.report-student th {
    text-align: right;
    padding: 4px 12px;
    white-space: nowrap;
    color: #64748b;
    font-weight: 500;
}
.report-student td {
    padding: 4px 12px;
    font-weight: 600;
}
.report-exam h3 {
    margin-top: 20px;
    margin-bottom: 10px;
    font-size: 16px;
    color: #1e293b;
    border-bottom: 1px solid #e2e8f0;
    padding-bottom: 5px;
}
.report-exam table {
    width: 100%;
    border-collapse: collapse;
}
.report-exam thead th {
    border-bottom: 2px solid #e2e8f0;
    padding: 8px 10px;
    text-align: left;
    font-size: 13px;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.report-exam tbody td {
    padding: 8px 10px;
    border-bottom: 1px solid #f1f5f9;
}
.report-exam tfoot th {
    padding: 8px 10px;
    text-align: left;
    border-top: 2px solid #e2e8f0;
    font-size: 14px;
}
.report-footer {
    margin-top: 30px;
    padding-top: 15px;
    border-top: 1px solid #e2e8f0;
    font-size: 12px;
    color: #94a3b8;
    text-align: center;
}
.report-note {
    font-style: italic;
    margin-top: 5px;
}
</style>

<!-- html2pdf.js for PDF download -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
function downloadPdf() {
    const element = document.getElementById('report-card');
    const name = '<?= e($studentInfo['full_name'] ?? 'student') ?>';
    const filename = name.replace(/[^a-zA-Z0-9]/g, '_') + '_results.pdf';
    
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
