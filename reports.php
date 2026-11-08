<?php
/**
 * reports.php
 * ---------------------------------------------------------------------------
 * Academic reports generated live from the students, subjects, exams and
 * results tables (no duplicated report data is stored). Pick a student and an
 * examination to produce a complete, printable report with subject marks,
 * grades, totals, average and overall grade. Replaces reports.html.
 * ---------------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();

$pdo = db();

$students = $pdo->query('SELECT student_id, admission_no, full_name, class_id FROM students ORDER BY full_name')->fetchAll();
$exams    = $pdo->query('SELECT exam_id, exam_name, term, year FROM exams ORDER BY year DESC, term')->fetchAll();
$classes  = $pdo->query('SELECT class_id, class_name FROM classes ORDER BY class_name')->fetchAll();
$subjects = $pdo->query('SELECT subject_id, subject_name FROM subjects ORDER BY subject_name')->fetchAll();

$studentId = (int)input('student_id');
$examId    = (int)input('exam_id');
$filterAdmission = trim(input('filter_admission') ?? '');
$filterClass     = (int)input('filter_class');
$filterSubject   = (int)input('filter_subject');

$student = null;
$rows    = [];
$summary = ['count' => 0, 'total' => 0.0, 'average' => 0.0, 'grade' => '', 'position' => null, 'class_size' => null];
$examLabel = '';

if ($studentId > 0) {
    // Student header info including class name.
    $stmt = $pdo->prepare(
        "SELECT s.admission_no, s.full_name, s.gender, c.class_name
           FROM students s
           LEFT JOIN classes c ON c.class_id = s.class_id
          WHERE s.student_id = :id"
    );
    $stmt->execute([':id' => $studentId]);
    $student = $stmt->fetch();

    // Marks by subject for the chosen exam (or all exams if none selected).
    $sql = "SELECT sub.subject_name, e.exam_name, e.term, e.year, r.marks, r.grade
              FROM results r
              JOIN subjects sub ON sub.subject_id = r.subject_id
              JOIN exams e      ON e.exam_id = r.exam_id
             WHERE r.student_id = :sid";
    $params = [':sid' => $studentId];
    if ($examId > 0) {
        $sql .= " AND r.exam_id = :eid";
        $params[':eid'] = $examId;
    }
    if ($filterSubject > 0) {
        $sql .= " AND r.subject_id = :subId";
        $params[':subId'] = $filterSubject;
    }
    $sql .= " ORDER BY e.year DESC, e.term, sub.subject_name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Summary figures (computed, not stored).
    $summary['count'] = count($rows);
    foreach ($rows as $r) {
        $summary['total'] += (float)$r['marks'];
    }
    if ($summary['count'] > 0) {
        $summary['average'] = $summary['total'] / $summary['count'];
        $summary['grade']   = calculate_grade($summary['average']);
    }

    // Position in class for a specific exam (ranked by average marks).
    if ($examId > 0 && $student) {
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
    }

    if ($examId > 0) {
        foreach ($exams as $ex) {
            if ((int)$ex['exam_id'] === $examId) {
                $examLabel = $ex['exam_name'] . ' - ' . $ex['term'] . ' ' . $ex['year'];
            }
        }
    } else {
        $examLabel = 'All Examinations';
    }
}

/** Format a numeric mark without trailing zeros. */
function fmt_marks($m): string
{
    return rtrim(rtrim(number_format((float)$m, 2, '.', ''), '0'), '.');
}

$page_title = 'Reports';
$page_subtitle = 'Generate a printable report card for a student';
require_once __DIR__ . '/includes/header.php';
?>

<div class="card no-print">
    <h2>Generate Report</h2>
    <form action="reports.php" method="get" class="inline" id="reportForm"
          style="display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:15px;">
        <!-- Filter: Admission Number -->
        <div>
            <label>Admission Number</label>
            <input type="text" name="filter_admission" placeholder="e.g., GV/2024/001" value="<?= e($filterAdmission) ?>"
                   style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;">
        </div>
        <!-- Filter: Class -->
        <div>
            <label>Class</label>
            <select name="filter_class"
                    style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;">
                <option value="0">-- All classes --</option>
                <?php foreach ($classes as $cl): ?>
                    <option value="<?= e($cl['class_id']) ?>" <?= ($filterClass == $cl['class_id']) ? 'selected' : '' ?>>
                        <?= e($cl['class_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <!-- Filter: Subject -->
        <div>
            <label>Subject</label>
            <select name="filter_subject"
                    style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;">
                <option value="0">-- All subjects --</option>
                <?php foreach ($subjects as $sb): ?>
                    <option value="<?= e($sb['subject_id']) ?>" <?= ($filterSubject == $sb['subject_id']) ? 'selected' : '' ?>>
                        <?= e($sb['subject_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>Search Student</label>
            <input type="text" id="studentSearch" placeholder="Type admission no or name..." autocomplete="off"
                   style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;margin-bottom:8px;">
            <select name="student_id" id="studentSelect" required
                    style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;">
                <option value="">-- Select Student --</option>
            </select>
        </div>
        <div><label>Examination</label>
            <select name="exam_id">
                <option value="0">-- All examinations --</option>
                <?php foreach ($exams as $ex): ?>
                    <option value="<?= e($ex['exam_id']) ?>" <?= ($examId == $ex['exam_id']) ? 'selected' : '' ?>>
                        <?= e($ex['exam_name'] . ' - ' . $ex['term'] . ' ' . $ex['year']) ?>
                    </option>
                <?php endforeach; ?>
            </select></div>
        <div style="align-self:end;">
            <button type="submit">Generate Report</button>
            <?php if ($filterAdmission || $filterClass > 0 || $filterSubject > 0): ?>
                <a href="reports.php" class="btn-sm" style="margin-left:8px;color:var(--primary);text-decoration:none;">Clear Filters</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<script>
(function() {
    var allStudents = <?= json_encode($students, JSON_HEX_TAG | JSON_HEX_APOS) ?>;
    var searchInput = document.getElementById('studentSearch');
    var selectEl    = document.getElementById('studentSelect');
    var classSelect = document.querySelector('select[name="filter_class"]');
    var admissionInput = document.querySelector('input[name="filter_admission"]');
    var selectedId  = <?= (int)$studentId ?>;
    var filterClassId = <?= $filterClass ?>;
    var filterAdmission = '<?= addslashes($filterAdmission) ?>'.toLowerCase();

    function populateList(list) {
        selectEl.innerHTML = '<option value="">-- Select Student --</option>';
        for (var i = 0; i < list.length; i++) {
            var opt = document.createElement('option');
            opt.value = list[i].student_id;
            opt.textContent = list[i].admission_no + ' - ' + list[i].full_name;
            if (parseInt(list[i].student_id) === selectedId) {
                opt.selected = true;
            }
            selectEl.appendChild(opt);
        }
    }

    function getFilteredStudents() {
        var students = allStudents;
        // Filter by class
        if (filterClassId > 0) {
            students = students.filter(function(s) { return parseInt(s.class_id) === filterClassId; });
        }
        // Filter by admission number from the filter input
        if (filterAdmission) {
            students = students.filter(function(s) {
                return s.admission_no.toLowerCase().indexOf(filterAdmission) !== -1;
            });
        }
        return students;
    }

    // Initial load with server-side filters applied
    populateList(getFilteredStudents());

    // Restore selected value after populate
    if (selectedId > 0) {
        selectEl.value = selectedId;
    }

    // Filter on type (search box)
    searchInput.addEventListener('input', function() {
        var q = this.value.toLowerCase().trim();
        var base = getFilteredStudents();
        if (q === '') {
            populateList(base);
            return;
        }
        var filtered = [];
        for (var i = 0; i < base.length; i++) {
            var s = base[i];
            if (s.admission_no.toLowerCase().indexOf(q) !== -1 || s.full_name.toLowerCase().indexOf(q) !== -1) {
                filtered.push(s);
            }
        }
        populateList(filtered);
    });

    // Auto-clear search when a student is selected
    selectEl.addEventListener('change', function() {
        if (this.value) {
            var chosen = allStudents.find(function(s) { return parseInt(s.student_id) === parseInt(this.value); }.bind(this));
            if (chosen) {
                searchInput.value = chosen.admission_no + ' - ' + chosen.full_name;
            }
        }
    });
})();
</script>

<?php if ($studentId > 0 && $student): ?>
<div class="card">
    <h2>Student Report</h2>
    <p><strong>Student:</strong> <?= e($student['full_name']) ?></p>
    <p><strong>Admission Number:</strong> <?= e($student['admission_no']) ?></p>
    <p><strong>Class:</strong> <?= e($student['class_name'] ?? '—') ?></p>
    <p><strong>Examination:</strong> <?= e($examLabel) ?></p>

    <?php if ($rows): ?>
        <table>
            <tr>
                <th>Subject</th>
                <?php if ($examId === 0): ?><th>Examination</th><?php endif; ?>
                <th>Marks</th><th>Grade</th>
            </tr>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= e($r['subject_name']) ?></td>
                    <?php if ($examId === 0): ?>
                        <td><?= e($r['exam_name'] . ' - ' . $r['term'] . ' ' . $r['year']) ?></td>
                    <?php endif; ?>
                    <td><?= e(fmt_marks($r['marks'])) ?></td>
                    <td><span class="grade"><?= e($r['grade']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            <tr>
                <th <?= $examId === 0 ? 'colspan="2"' : '' ?>>Total</th>
                <th><?= e(fmt_marks($summary['total'])) ?></th>
                <th>&nbsp;</th>
            </tr>
        </table>

        <p style="margin-top:14px;">
            <strong>Subjects:</strong> <?= e($summary['count']) ?> &nbsp;|&nbsp;
            <strong>Average:</strong> <?= e(fmt_marks($summary['average'])) ?> &nbsp;|&nbsp;
            <strong>Mean Grade:</strong> <span class="grade"><?= e($summary['grade']) ?></span>
            <?php if ($summary['position'] !== null): ?>
                &nbsp;|&nbsp; <strong>Position in class:</strong>
                <?= e($summary['position']) ?> of <?= e($summary['class_size']) ?>
            <?php endif; ?>
        </p>

        <button class="no-print" onclick="window.print()" style="margin-top:14px;">Print Report</button>
    <?php else: ?>
        <p>No marks found for this student<?= $examId > 0 ? ' in the selected examination' : '' ?>.</p>
    <?php endif; ?>
</div>
<?php elseif ($studentId > 0 && !$student): ?>
    <div class="card"><p>Student not found.</p></div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
