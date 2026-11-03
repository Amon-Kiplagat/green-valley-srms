<?php
/**
 * teacher_accounts.php
 * ---------------------------------------------------------------------------
 * Admin page to manage teacher login accounts: create accounts for teachers
 * who don't have one, reset passwords, and activate/deactivate accounts.
 * Only accessible to admins.
 * ---------------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_role('admin');

$pdo = db();

// Fetch all teachers with their login account status
$sql = "SELECT t.teacher_id, t.staff_no, t.full_name, t.email, t.phone,
               u.user_id, u.username, u.is_active AS account_active
          FROM teachers t
          LEFT JOIN users u ON u.teacher_id = t.teacher_id AND u.role = 'teacher'
         ORDER BY t.full_name";
$teachers = $pdo->query($sql)->fetchAll();

$page_title = 'Teacher Accounts';
$page_subtitle = 'Manage teacher login accounts and access';
require_once __DIR__ . '/includes/header.php';
?>

<div class="card">
    <h2><i class="fa-solid fa-user-lock"></i> Teacher Account Management</h2>
    <p style="color:#64748b;margin-bottom:20px;">
        Create login accounts for teachers, reset passwords, and control access.
        Teachers without an account cannot log in to the system.
    </p>

    <table>
        <tr>
            <th>Staff No</th>
            <th>Name</th>
            <th>Email</th>
            <th>Username</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
        <?php if ($teachers): ?>
            <?php foreach ($teachers as $t): ?>
                <tr>
                    <td><?= e($t['staff_no']) ?></td>
                    <td><?= e($t['full_name']) ?></td>
                    <td><?= e($t['email'] ?? '—') ?></td>
                    <td>
                        <?php if ($t['user_id']): ?>
                            <?= e($t['username']) ?>
                        <?php else: ?>
                            <span style="color:#94a3b8;">No account</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($t['user_id']): ?>
                            <?php if ($t['account_active']): ?>
                                <span class="badge badge-success">Active</span>
                            <?php else: ?>
                                <span class="badge badge-warning">Inactive</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="badge badge-danger">No account</span>
                        <?php endif; ?>
                    </td>
                    <td class="actions">
                        <?php if (!$t['user_id']): ?>
                            <button type="button" class="btn-sm"
                                    onclick="showCreateForm(<?= (int)$t['teacher_id'] ?>, '<?= e($t['full_name']) ?>', '<?= e($t['email'] ?? '') ?>')">
                                <i class="fa-solid fa-user-plus"></i> Create Account
                            </button>
                        <?php else: ?>
                            <button type="button" class="btn-sm"
                                    onclick="showResetForm(<?= (int)$t['teacher_id'] ?>, '<?= e($t['full_name']) ?>', '<?= e($t['username']) ?>')">
                                <i class="fa-solid fa-key"></i> Reset Password
                            </button>
                            <?php if ($t['account_active']): ?>
                                <a class="btn-sm btn-danger"
                                   href="vendor/actions/teacher_account_actions.php?action=deactivate&teacher_id=<?= (int)$t['teacher_id'] ?>&csrf_token=<?= e(csrf_token()) ?>"
                                   data-confirm="Deactivate <?= e($t['full_name']) ?>? They will not be able to log in.">
                                    <i class="fa-solid fa-ban"></i> Deactivate
                                </a>
                            <?php else: ?>
                                <a class="btn-sm"
                                   href="vendor/actions/teacher_account_actions.php?action=activate&teacher_id=<?= (int)$t['teacher_id'] ?>&csrf_token=<?= e(csrf_token()) ?>">
                                    <i class="fa-solid fa-check"></i> Activate
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="6">No teachers found. Add teachers first via the Teachers page.</td></tr>
        <?php endif; ?>
    </table>
</div>

<!-- Create Account Modal -->
<div id="createAccountModal" class="modal-overlay" style="display:none;">
    <div class="card" style="max-width:480px;margin:40px auto;padding:32px;">
        <h3><i class="fa-solid fa-user-plus"></i> Create Teacher Account</h3>
        <p style="color:#64748b;margin-bottom:20px;font-size:0.9rem;">
            Creating account for: <strong id="createTeacherName"></strong>
        </p>
        <form action="vendor/actions/teacher_account_actions.php" method="post" data-validate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_account">
            <input type="hidden" name="teacher_id" id="createTeacherId">

            <div class="form-group">
                <label for="newUsername">Username</label>
                <input type="text" id="newUsername" name="username" required
                       placeholder="e.g. jmwangi" autocomplete="off">
                <small class="field-hint">Used to log in. Lowercase letters and numbers only.</small>
            </div>

            <div class="form-group">
                <label for="newPassword">Temporary Password</label>
                <input type="password" id="newPassword" name="password" required minlength="8"
                       placeholder="At least 8 characters">
                <small class="field-hint">The teacher can change this on first login.</small>
            </div>

            <div class="form-group">
                <label for="newConfirm">Confirm Password</label>
                <input type="password" id="newConfirm" name="confirm" required minlength="8"
                       placeholder="Repeat the password">
            </div>

            <div style="display:flex;gap:10px;align-self:end;">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-user-plus"></i> Create Account
                </button>
                <button type="button" class="btn-sm" onclick="hideModal('createAccountModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Reset Password Modal -->
<div id="resetPasswordModal" class="modal-overlay" style="display:none;">
    <div class="card" style="max-width:480px;margin:40px auto;padding:32px;">
        <h3><i class="fa-solid fa-key"></i> Reset Password</h3>
        <p style="color:#64748b;margin-bottom:20px;font-size:0.9rem;">
            Resetting password for: <strong id="resetTeacherName"></strong>
            (<span id="resetUsername"></span>)
        </p>
        <form action="vendor/actions/teacher_account_actions.php" method="post" data-validate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="teacher_id" id="resetTeacherId">

            <div class="form-group">
                <label for="resetPassword">New Password</label>
                <input type="password" id="resetPassword" name="password" required minlength="8"
                       placeholder="At least 8 characters">
            </div>

            <div class="form-group">
                <label for="resetConfirm">Confirm Password</label>
                <input type="password" id="resetConfirm" name="confirm" required minlength="8"
                       placeholder="Repeat the password">
            </div>

            <div style="display:flex;gap:10px;align-self:end;">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-key"></i> Reset Password
                </button>
                <button type="button" class="btn-sm" onclick="hideModal('resetPasswordModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function showCreateForm(teacherId, name, email) {
    document.getElementById('createTeacherId').value = teacherId;
    document.getElementById('createTeacherName').textContent = name;
    // Suggest username from email or name
    var suggested = '';
    if (email && email.indexOf('@') > 0) {
        suggested = email.split('@')[0];
    } else {
        suggested = name.toLowerCase().replace(/[^a-z0-9]/g, '').substring(0, 10);
    }
    document.getElementById('newUsername').value = suggested;
    document.getElementById('createAccountModal').style.display = 'flex';
}

function showResetForm(teacherId, name, username) {
    document.getElementById('resetTeacherId').value = teacherId;
    document.getElementById('resetTeacherName').textContent = name;
    document.getElementById('resetUsername').textContent = username;
    document.getElementById('resetPasswordModal').style.display = 'flex';
}

function hideModal(id) {
    document.getElementById(id).style.display = 'none';
}

// Close modal on overlay click
document.querySelectorAll('.modal-overlay').forEach(function (overlay) {
    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) {
            overlay.style.display = 'none';
        }
    });
});
</script>

<style>
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(4px);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}
.badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
}
.badge-success {
    background: #d1fae5;
    color: #065f46;
}
.badge-warning {
    background: #fef3c7;
    color: #92400e;
}
.badge-danger {
    background: #fee2e2;
    color: #991b1b;
}
.btn-danger {
    color: #dc2626 !important;
}
.btn-danger:hover {
    text-decoration: underline;
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
