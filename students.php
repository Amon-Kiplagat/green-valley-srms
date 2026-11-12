<?php
/**
 * students.php
 * ---------------------------------------------------------------------------
 * Student management: add, view, search, edit, delete. Joins the classes
 * table so each student's class is shown/selected. Replaces students.html.
 * ---------------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/student_portal.php';
require_login();

$pdo = db();

// Class list for the dropdown.
$classes = $pdo->query('SELECT class_id, class_name FROM classes ORDER BY class_name')->fetchAll();

// If editing, load the student being edited.
$editing = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM students WHERE student_id = :id');
    $stmt->execute([':id' => (int)$_GET['edit']]);
    $editing = $stmt->fetch();
    if (!$editing) {
        set_flash('error', 'Student not found.');
        redirect('students.php');
    }
}

// Search term (safe: used only as a bound ILIKE parameter).
$search = input('q');

$sql = "SELECT s.student_id, s.admission_no, s.full_name, s.gender,
               s.date_of_birth, s.guardian_name, s.guardian_phone,
               s.portal_enabled, s.portal_username,
               c.class_name
          FROM students s
          LEFT JOIN classes c ON c.class_id = s.class_id";
$params = [];
if ($search !== '') {
    $sql .= " WHERE s.admission_no ILIKE :q OR s.full_name ILIKE :q";
    $params[':q'] = '%' . $search . '%';
}
$sql .= " ORDER BY s.full_name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

$page_title = 'Students';
$page_subtitle = 'Add, search and manage student records';
require_once __DIR__ . '/includes/header.php';
?>

<div class="card">
    <h2><?= $editing ? 'Edit Student' : 'Add Student' ?></h2>
    <form action="actions/student_actions.php" method="post" data-validate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
        <?php if ($editing): ?>
            <input type="hidden" name="student_id" value="<?= e($editing['student_id']) ?>">
        <?php endif; ?>

        <div>
            <label>Admission Number</label>
            <input type="text" name="admission_no"
                   value="<?= e($editing['admission_no'] ?? '') ?>" required>
        </div>
        <div>
            <label>Full Name</label>
            <input type="text" name="full_name"
                   value="<?= e($editing['full_name'] ?? '') ?>" required>
        </div>
        <div>
            <label>Gender</label>
            <select name="gender">
                <?php foreach (['Male', 'Female'] as $g): ?>
                    <option value="<?= $g ?>" <?= (($editing['gender'] ?? '') === $g) ? 'selected' : '' ?>><?= $g ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>Date of Birth</label>
            <input type="date" name="date_of_birth" value="<?= e($editing['date_of_birth'] ?? '') ?>"
                   min="<?= (new DateTime())->modify('-15 years')->format('Y-m-d') ?>"
                   max="<?= (new DateTime())->modify('-14 years')->format('Y-m-d') ?>">
            <small class="field-hint">Student must be 14–15 years old (born between <?= (new DateTime())->modify('-15 years')->format('Y') ?> and <?= (new DateTime())->modify('-14 years')->format('Y') ?>).</small>
        </div>
        <div>
            <label>Class</label>
            <select name="class_id">
                <option value="">-- Select Class --</option>
                <?php foreach ($classes as $c): ?>
                    <option value="<?= e($c['class_id']) ?>"
                        <?= (($editing['class_id'] ?? '') == $c['class_id']) ? 'selected' : '' ?>>
                        <?= e($c['class_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>Parent/Guardian</label>
            <input type="text" name="guardian_name" value="<?= e($editing['guardian_name'] ?? '') ?>">
        </div>
        <div>
            <label>Phone Number</label>
            <input type="tel" name="guardian_phone"
                   value="<?= e($editing['guardian_phone'] ?? '') ?>"
                   inputmode="numeric"
                   autocomplete="tel"
                   maxlength="16"
                   pattern="[0-9+][0-9 ().+-]{7,18}"
                   placeholder="0712345678"
                   title="Digits only: 10 digits starting 07 or 01, or the +254 form."
                   data-phone>
            <small class="field-hint">Digits only &mdash; e.g. 0712345678 or +254712345678.</small>
        </div>
        <div style="align-self:end;">
            <button type="submit"><?= $editing ? 'Update Student' : 'Save Student' ?></button>
            <?php if ($editing): ?>
                <a href="students.php" class="btn-sm" style="margin-left:8px;">Cancel</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card">
    <h2>Student List</h2>
    <form action="students.php" method="get" class="searchbar">
        <div>
            <label>Search (name or admission no.)</label>
            <input type="text" name="q" value="<?= e($search) ?>" placeholder="e.g. Mary or GV001">
        </div>
        <div><button type="submit">Search</button>
            <?php if ($search !== ''): ?><a href="students.php" class="btn-sm">Clear</a><?php endif; ?>
        </div>
    </form>

    <table>
        <tr><th>Adm No.</th><th>Name</th><th>Gender</th><th>Class</th><th>Parent Phone</th><th>Portal</th><th>Action</th></tr>
        <?php if ($students): ?>
            <?php foreach ($students as $s): ?>
                <?php $portalActive = function_exists('db_bool') && db_bool($s['portal_enabled'] ?? false); ?>
                <tr>
                    <td><?= e($s['admission_no']) ?></td>
                    <td><?= e($s['full_name']) ?></td>
                    <td><?= e($s['gender']) ?></td>
                    <td><?= e($s['class_name'] ?? '—') ?></td>
                    <td><?= e(format_phone_display($s['guardian_phone'] ?? '')) ?></td>
                    <td>
                        <?php if ($portalActive): ?>
                            <span class="badge badge-success">Active</span>
                            <small><?= e($s['portal_username'] ?? '') ?></small>
                        <?php else: ?>
                            <span class="badge badge-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="actions">
                        <a href="students.php?edit=<?= e($s['student_id']) ?>">Edit</a>
                        <?php if (is_admin()): ?>
                            <?php if ($portalActive): ?>
                                <a href="#" class="btn-sm" data-open-portal-modal="<?= e($s['student_id']) ?>" data-username="<?= e($s['portal_username'] ?? '') ?>">Manage</a>
                            <?php else: ?>
                                <a href="#" class="btn-sm" data-open-portal-modal="<?= e($s['student_id']) ?>" data-username="">Enable</a>
                            <?php endif; ?>
                            <a class="btn-danger"
                               href="actions/student_actions.php?action=delete&student_id=<?= e($s['student_id']) ?>&csrf_token=<?= e(csrf_token()) ?>"
                               data-confirm="Delete <?= e($s['full_name']) ?>? This cannot be undone.">Delete</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="7">No students found.</td></tr>
        <?php endif; ?>
    </table>
</div>

<!-- Portal Account Management Modal -->
<div id="portal-modal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:480px; padding:30px; border-radius:16px; background:#fff;">
        <h2 id="portal-modal-title" style="margin-bottom:16px;"><i class="fa-solid fa-key"></i> Portal Account</h2>
        <p id="portal-modal-student" style="margin-bottom:20px; color:#64748b;"></p>

        <!-- Enable Portal Form -->
        <form id="portal-enable-form" action="actions/student_actions.php" method="post" style="display:none;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="enable_portal">
            <input type="hidden" name="student_id" id="portal-enable-student-id">
            <div class="form-group">
                <label for="portal-username">Portal Username</label>
                <input type="text" id="portal-username" name="portal_username" required minlength="3"
                       placeholder="e.g. mary.w">
            </div>
            <div class="form-group">
                <label for="portal-password">Default Password</label>
                <input type="password" id="portal-password" name="portal_password" required minlength="6"
                       placeholder="At least 6 characters">
            </div>
            <p style="margin-top:10px;">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-check"></i> Create Portal Account
                </button>
                <a href="#" class="btn-sm" data-close-modal>Cancel</a>
            </p>
        </form>

        <!-- Manage Portal Form (reset password + disable) -->
        <form id="portal-manage-form" action="actions/student_actions.php" method="post" style="display:none;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reset_portal_password">
            <input type="hidden" name="student_id" id="portal-manage-student-id">
            <p>Current username: <strong id="portal-manage-username"></strong></p>
            <div class="form-group">
                <label for="portal-reset-password">New Default Password</label>
                <input type="password" id="portal-reset-password" name="portal_password" required minlength="6"
                       placeholder="At least 6 characters">
            </div>
            <p style="margin-top:10px;">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-rotate"></i> Reset Password
                </button>
                <a href="#" class="btn-sm" data-close-modal>Cancel</a>
            </p>
        </form>

        <!-- Disable Portal -->
        <form id="portal-disable-form" action="actions/student_actions.php" method="post" style="display:none;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="disable_portal">
            <input type="hidden" name="student_id" id="portal-disable-student-id">
            <p style="color:#ef4444; margin-bottom:16px;">
                <i class="fa-solid fa-triangle-exclamation"></i>
                This will remove the student's portal access. They will no longer be able to log in.
            </p>
            <p>
                <button type="submit" class="btn btn-danger">
                    <i class="fa-solid fa-ban"></i> Disable Portal Account
                </button>
                <a href="#" class="btn-sm" data-close-modal>Cancel</a>
            </p>
        </form>

        <!-- Manage actions (shown when portal is active) -->
        <div id="portal-manage-actions" style="display:none; margin-top:16px; padding-top:16px; border-top:1px solid #e2e8f0;">
            <p style="font-size:14px; color:#64748b; margin-bottom:10px;">Account actions:</p>
            <button type="button" class="btn btn-sm" id="show-reset-btn" style="margin-right:8px;">
                <i class="fa-solid fa-rotate"></i> Reset Password
            </button>
            <button type="button" class="btn btn-sm btn-danger" id="show-disable-btn">
                <i class="fa-solid fa-ban"></i> Disable Account
            </button>
        </div>
    </div>
</div>

<script>
(function(){
    var modal = document.getElementById('portal-modal');
    var enableForm = document.getElementById('portal-enable-form');
    var manageForm = document.getElementById('portal-manage-form');
    var disableForm = document.getElementById('portal-disable-form');
    var manageActions = document.getElementById('portal-manage-actions');
    var modalTitle = document.getElementById('portal-modal-title');
    var modalStudent = document.getElementById('portal-modal-student');

    function closeModal(){
        modal.style.display = 'none';
        enableForm.style.display = 'none';
        manageForm.style.display = 'none';
        disableForm.style.display = 'none';
        manageActions.style.display = 'none';
    }

    // Close on backdrop click
    modal.addEventListener('click', function(ev){
        if(ev.target === modal) closeModal();
    });

    // Close on data-close-modal links
    document.querySelectorAll('[data-close-modal]').forEach(function(link){
        link.addEventListener('click', function(ev){
            ev.preventDefault();
            closeModal();
        });
    });

    // Open modal
    document.querySelectorAll('[data-open-portal-modal]').forEach(function(link){
        link.addEventListener('click', function(ev){
            ev.preventDefault();
            var studentId = this.getAttribute('data-open-portal-modal');
            var username = this.getAttribute('data-username');
            var studentName = this.closest('tr').children[1].textContent.trim();

            modalStudent.textContent = studentName;

            if(!username){
                // Enable form
                modalTitle.innerHTML = '<i class="fa-solid fa-key"></i> Create Portal Account';
                document.getElementById('portal-enable-student-id').value = studentId;
                enableForm.style.display = 'block';
                manageForm.style.display = 'none';
                disableForm.style.display = 'none';
                manageActions.style.display = 'none';
                document.getElementById('portal-username').value = '';
                document.getElementById('portal-password').value = '';
            } else {
                // Manage form
                modalTitle.innerHTML = '<i class="fa-solid fa-key"></i> Manage Portal Account';
                document.getElementById('portal-manage-student-id').value = studentId;
                document.getElementById('portal-manage-username').textContent = username;
                enableForm.style.display = 'none';
                manageForm.style.display = 'block';
                disableForm.style.display = 'none';
                manageActions.style.display = 'block';
                document.getElementById('portal-reset-password').value = '';
            }

            modal.style.display = 'flex';
        });
    });

    // Show reset password form
    document.getElementById('show-reset-btn').addEventListener('click', function(){
        enableForm.style.display = 'none';
        manageForm.style.display = 'block';
        disableForm.style.display = 'none';
    });

    // Show disable form
    document.getElementById('show-disable-btn').addEventListener('click', function(){
        enableForm.style.display = 'none';
        manageForm.style.display = 'none';
        disableForm.style.display = 'block';
    });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
