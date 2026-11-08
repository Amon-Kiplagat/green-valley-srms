<?php
/**
 * login.php
 * ---------------------------------------------------------------------------
 * Dual-panel login page: Staff/Admin and Student portals on one screen with
 * a sliding overlay animation. Replaces the old single-panel login.php.
 * ---------------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/student_portal.php';

// Already signed in? Go straight to the appropriate dashboard.
if (is_logged_in()) {
    redirect('dashboard.php');
}
if (is_student()) {
    redirect('student_home.php');
}

/* ------------------------------------------------------------------------ */
/* Staff / Admin login handling                                             */
/* ------------------------------------------------------------------------ */

$staffError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['staff_login'])) {
    verify_csrf();

    $username = input('username');
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $staffError = 'Please enter both username and password.';
    } else {
        $stmt = db()->prepare(
            'SELECT user_id, username, password_hash, full_name, role, is_active
               FROM users
              WHERE username = :u
              LIMIT 1'
        );
        $stmt->execute([':u' => $username]);
        $user = $stmt->fetch();

        if ($user && db_bool($user['is_active']) && password_verify($password, $user['password_hash'])) {
            if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                $new = password_hash($password, PASSWORD_DEFAULT);
                $up  = db()->prepare('UPDATE users SET password_hash = :h WHERE user_id = :id');
                $up->execute([':h' => $new, ':id' => $user['user_id']]);
            }
            login_user($user);
            log_activity('Signed in', 'Session', null);
            set_flash('success', 'Welcome back, ' . $user['full_name'] . '!');
            redirect('dashboard.php');
        }

        log_activity(
            'Failed sign-in',
            'Session',
            ($user && !db_bool($user['is_active'])) ? 'account is deactivated' : null,
            [
                'user_id'   => $user['user_id'] ?? null,
                'username'  => $username,
                'full_name' => $user['full_name'] ?? 'Unknown user',
                'role'      => $user['role'] ?? null,
            ]
        );

        $staffError = 'Invalid username or password.';
    }
}

/* ------------------------------------------------------------------------ */
/* Student portal login handling                                            */
/* ------------------------------------------------------------------------ */

$studentError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_login'])) {
    verify_csrf();

    $username = input('username');
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $studentError = 'Please enter both your username and password.';
    } else {
        $pdo = db();
        $stmt = $pdo->prepare(
            'SELECT student_id, full_name, admission_no, portal_password_hash,
                    portal_enabled, portal_must_change,
                    CASE WHEN portal_locked_until > now()
                         THEN CAST(CEIL(EXTRACT(EPOCH FROM (portal_locked_until - now())) / 60) AS integer)
                    END AS lock_minutes
               FROM students
              WHERE portal_username IS NOT NULL
                AND lower(portal_username) = lower(:u)
              LIMIT 1'
        );
        $stmt->execute([':u' => $username]);
        $student = $stmt->fetch();

        if ($student && $student['lock_minutes'] !== null) {
            $studentError = portal_lock_message((int)$student['lock_minutes']);
        } elseif ($student
            && db_bool($student['portal_enabled'])
            && !empty($student['portal_password_hash'])
            && password_verify($password, $student['portal_password_hash'])) {

            if (password_needs_rehash($student['portal_password_hash'], PASSWORD_DEFAULT)) {
                $new = password_hash($password, PASSWORD_DEFAULT);
                $up  = $pdo->prepare('UPDATE students SET portal_password_hash = :h WHERE student_id = :id');
                $up->execute([':h' => $new, ':id' => $student['student_id']]);
            }

            portal_record_success($pdo, (int)$student['student_id']);
            $mustChange = db_bool($student['portal_must_change']);
            login_student($student, $mustChange);

            if ($mustChange) {
                set_flash('success', 'Welcome, ' . $student['full_name']
                    . '. Before you continue, please choose your own password.');
                redirect('student_password.php');
            }
            set_flash('success', 'Welcome, ' . $student['full_name'] . '.');
            redirect('student_home.php');

        } else {
            $studentError = 'Invalid username or password, or your portal access is not yet enabled.';
            if ($student && portal_record_failure($pdo, (int)$student['student_id'])) {
                portal_log_lockout($student['full_name'], $student['admission_no']);
                $studentError = portal_lock_message(PORTAL_LOCK_MINUTES);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Login - Green Valley SRMS</title>

    <!-- Progressive Web App tags -->
    <meta name="theme-color" content="#4f46e5">
    <link rel="manifest" href="manifest.webmanifest">
    <link rel="icon" href="icons/favicon-32.png" sizes="32x32" type="image/png">
    <link rel="apple-touch-icon" href="icons/apple-touch-icon.png">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="GV Records">

    <!-- Google Fonts & Font Awesome Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* --- CSS RESET & BASE STYLES --- */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #311042 100%);
            padding: 20px;
            color: #334155;
        }

        /* --- MAIN CONTAINER --- */
        .slide-container {
            position: relative;
            width: 1000px;
            max-width: 100%;
            min-height: 620px;
            background: #ffffff;
            border-radius: 28px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
            overflow: hidden;
            display: flex;
        }

        /* --- FORM PANELS --- */
        .form-box {
            position: absolute;
            top: 0;
            height: 100%;
            width: 50%;
            padding: 50px 45px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            transition: all 0.6s ease-in-out;
        }

        /* Student Form (Left Side) */
        .student-box {
            left: 0;
            z-index: 2;
        }

        /* Staff/Admin Form (Right Side) */
        .staff-box {
            left: 0;
            opacity: 0;
            z-index: 1;
        }

        /* Active state when shifted */
        .slide-container.panel-active .student-box {
            transform: translateX(100%);
            opacity: 0;
            z-index: 1;
        }

        .slide-container.panel-active .staff-box {
            transform: translateX(100%);
            opacity: 1;
            z-index: 5;
        }

        /* --- SLIDING OVERLAY PANEL --- */
        .overlay-container {
            position: absolute;
            top: 0;
            left: 50%;
            width: 50%;
            height: 100%;
            overflow: hidden;
            transition: transform 0.6s ease-in-out;
            z-index: 100;
            border-radius: 0 28px 28px 0;
        }

        .slide-container.panel-active .overlay-container {
            transform: translateX(-100%);
            border-radius: 28px 0 0 28px;
        }

        .overlay {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 50%, #4338ca 100%);
            color: #ffffff;
            position: relative;
            left: -100%;
            height: 100%;
            width: 200%;
            transform: translateX(0);
            transition: transform 0.6s ease-in-out;
            display: flex;
        }

        .slide-container.panel-active .overlay {
            transform: translateX(50%);
        }

        .overlay-panel {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            padding: 0 50px;
            text-align: center;
            top: 0;
            height: 100%;
            width: 50%;
            transform: translateX(0);
            transition: transform 0.6s ease-in-out;
        }

        .overlay-left {
            transform: translateX(-20%);
        }

        .slide-container.panel-active .overlay-left {
            transform: translateX(0);
        }

        .overlay-right {
            right: 0;
            transform: translateX(0);
        }

        .slide-container.panel-active .overlay-right {
            transform: translateX(20%);
        }

        /* --- TYPOGRAPHY & ELEMENTS --- */
        .form-header {
            margin-bottom: 28px;
        }

        .badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 12px;
        }

        .badge-student {
            background: #e0e7ff;
            color: #4338ca;
        }

        .badge-staff {
            background: #fae8ff;
            color: #86198f;
        }

        .form-header h2 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 6px;
        }

        .form-header p {
            font-size: 0.875rem;
            color: #64748b;
        }

        .input-group {
            margin-bottom: 18px;
            position: relative;
        }

        .input-group label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 6px;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-wrapper i.input-icon {
            position: absolute;
            left: 14px;
            color: #94a3b8;
            font-size: 0.95rem;
            transition: color 0.2s;
        }

        .input-wrapper input, .input-wrapper select {
            width: 100%;
            padding: 12px 14px 12px 42px;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            font-size: 0.9rem;
            color: #0f172a;
            outline: none;
            background: #f8fafc;
            transition: all 0.2s;
        }

        .input-wrapper input:focus, .input-wrapper select:focus {
            border-color: #6366f1;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);
        }

        .input-wrapper input:focus + i.input-icon {
            color: #6366f1;
        }

        .toggle-pwd {
            position: absolute;
            right: 14px;
            color: #94a3b8;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .toggle-pwd:hover {
            color: #475569;
        }

        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            font-size: 0.825rem;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #64748b;
            cursor: pointer;
        }

        .remember-me input {
            accent-color: #6366f1;
            width: 16px;
            height: 16px;
        }

        .forgot-link {
            color: #6366f1;
            text-decoration: none;
            font-weight: 600;
        }

        .forgot-link:hover {
            text-decoration: underline;
        }

        /* --- BUTTONS --- */
        .btn-submit {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 12px;
            background: #4f46e5;
            color: white;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
            transition: all 0.2s;
        }

        .btn-submit:hover {
            background: #4338ca;
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(79, 70, 229, 0.4);
        }

        .btn-ghost {
            background: transparent;
            border: 2px solid #ffffff;
            color: #ffffff;
            padding: 12px 32px;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 20px;
            transition: all 0.2s;
        }

        .btn-ghost:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-1px);
        }

        .overlay-panel h3 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .overlay-panel p {
            font-size: 0.9rem;
            line-height: 1.5;
            opacity: 0.9;
        }

        /* --- ALERT STYLES --- */
        .alert {
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 0.85rem;
            margin-bottom: 18px;
            line-height: 1.4;
        }
        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        /* --- EXTRA LINKS --- */
        .form-extra {
            margin-top: 16px;
            text-align: center;
            font-size: 0.825rem;
            color: #64748b;
        }
        .form-extra a {
            color: #6366f1;
            text-decoration: none;
            font-weight: 600;
        }
        .form-extra a:hover {
            text-decoration: underline;
        }

        /* --- RESPONSIVE MOBILE STYLES --- */
        @media (max-width: 768px) {
            .slide-container {
                min-height: auto;
                flex-direction: column;
            }
            .form-box {
                position: relative;
                width: 100%;
                height: auto;
                padding: 35px 25px;
            }
            .overlay-container {
                display: none;
            }
            .slide-container.panel-active .student-box {
                display: none;
            }
            .slide-container.panel-active .staff-box {
                transform: none;
                opacity: 1;
                position: relative;
            }
            .mobile-switch {
                display: block !important;
                text-align: center;
                margin-top: 20px;
                font-size: 0.85rem;
            }
            .mobile-switch a {
                color: #6366f1;
                font-weight: 700;
                text-decoration: none;
            }
        }

        .mobile-switch {
            display: none;
        }
    </style>
</head>
<body>

  <div class="slide-container<?php if ($staffError !== ''): ?> panel-active<?php endif; ?>" id="slideContainer">

    <!-- 1. STUDENT LOGIN FORM (LEFT) -->
    <div class="form-box student-box">
      <form method="post" data-validate>
        <?= csrf_field() ?>
        <input type="hidden" name="student_login" value="1">

        <div class="form-header">
          <span class="badge badge-student">Student Portal</span>
          <h2>Welcome Back</h2>
          <p>Sign in to view your own results</p>
        </div>

        <?= render_flashes() ?>

        <?php if ($studentError !== ''): ?>
            <div class="alert alert-error"><?= e($studentError) ?></div>
        <?php endif; ?>

        <div class="input-group">
          <label for="student-username">Username</label>
          <div class="input-wrapper">
            <input type="text" id="student-username" name="username"
                   value="<?= e($_POST['username'] ?? '') ?>"
                   placeholder="Enter your username" required>
            <i class="fa-solid fa-user-graduate input-icon"></i>
          </div>
        </div>

        <div class="input-group">
          <label for="student-pass">Password</label>
          <div class="input-wrapper">
            <input type="password" id="student-pass" name="password"
                   placeholder="••••••••" required>
            <i class="fa-solid fa-lock input-icon"></i>
            <i class="fa-solid fa-eye toggle-pwd" onclick="togglePassword('student-pass', this)"></i>
          </div>
        </div>

        <div class="form-options" style="margin-bottom: 18px;">
          <span></span>
          <a href="reset-password.php" class="forgot-link">Forgot password?</a>
        </div>

        <button type="submit" class="btn-submit">Sign In as Student</button>

        <div class="form-extra">
          No account yet? Ask the school office.
        </div>

        <div class="mobile-switch">
          Are you a staff member?
          <a href="#" onclick="togglePanel(true); return false;">Switch to Staff</a>
        </div>
      </form>
    </div>

    <!-- 2. STAFF & ADMIN LOGIN FORM (RIGHT) -->
    <div class="form-box staff-box">
      <form method="post" data-validate>
        <?= csrf_field() ?>
        <input type="hidden" name="staff_login" value="1">

        <div class="form-header">
          <span class="badge badge-staff">Faculty & Admin</span>
          <h2>Access Control</h2>
          <p>Sign in to the records system</p>
        </div>

        <?php if ($staffError !== ''): ?>
            <div class="alert alert-error"><?= e($staffError) ?></div>
        <?php endif; ?>

        <div class="input-group">
          <label for="staff-username">Username</label>
          <div class="input-wrapper">
            <input type="text" id="staff-username" name="username"
                   value="<?= e($_POST['username'] ?? '') ?>"
                   placeholder="Enter username" required>
            <i class="fa-solid fa-user-shield input-icon"></i>
          </div>
        </div>

        <div class="input-group">
          <label for="staff-pass">Password</label>
          <div class="input-wrapper">
            <input type="password" id="staff-pass" name="password"
                   placeholder="••••••••" required>
            <i class="fa-solid fa-key input-icon"></i>
            <i class="fa-solid fa-eye toggle-pwd" onclick="togglePassword('staff-pass', this)"></i>
          </div>
        </div>

        <div class="form-options">
          <label class="remember-me">
            <input type="checkbox"> Remember device
          </label>
          <a href="reset-password.php" class="forgot-link">Forgot password?</a>
        </div>

        <button type="submit" class="btn-submit" style="background: #7e22ce;">Sign In to Staff Portal</button>

        <div class="form-extra">
          New teacher? <a href="signup.php">Create an account</a>
        </div>

        <div class="mobile-switch">
          Are you a student?
          <a href="#" onclick="togglePanel(false); return false;">Switch to Student</a>
        </div>
      </form>
    </div>

    <!-- 3. SLIDING OVERLAY CONTAINER -->
    <div class="overlay-container">
      <div class="overlay">

        <!-- Left Overlay (Visible when Staff form is active) -->
        <div class="overlay-panel overlay-left">
          <h3>Student Portal?</h3>
          <p>Looking for your grades, report cards, or academic schedule? Head over to the Student Sign-In panel.</p>
          <button class="btn-ghost" onclick="togglePanel(false)">Switch to Student</button>
        </div>

        <!-- Right Overlay (Visible when Student form is active) -->
        <div class="overlay-panel overlay-right">
          <h3>Staff or Admin?</h3>
          <p>Access gradebooks, student records, management analytics, and administrative settings.</p>
          <button class="btn-ghost" onclick="togglePanel(true)">Switch to Staff / Admin</button>
        </div>

      </div>
    </div>

  </div>

  <!-- JS LOGIC FOR SLIDE ANIMATION & PASSWORD TOGGLE -->
  <script>
    const slideContainer = document.getElementById('slideContainer');

    // Toggle sliding panel state
    function togglePanel(isStaff) {
      if (isStaff) {
        slideContainer.classList.add('panel-active');
      } else {
        slideContainer.classList.remove('panel-active');
      }
    }

    // Toggle password input visibility
    function togglePassword(inputId, icon) {
      const input = document.getElementById(inputId);
      if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
      } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
      }
    }
  </script>

  <script src="js/pwa.js" defer></script>
</body>
</html>
