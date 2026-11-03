<?php
/**
 * reset-password.php
 * ---------------------------------------------------------------------------
 * Password reset page for staff accounts (admin/teacher).
 * Bento Grid design with dual-panel layout and step indicators.
 *
 * Two views on the same page:
 *   1. Request form — enter your email to start the reset flow.
 *   2. Reset form   — set a new password (shown when a valid token is present).
 *
 * Backend: actions/reset_actions.php
 * ---------------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

// Already signed in? Go to dashboard.
if (is_logged_in()) {
    redirect('dashboard.php');
}

// Flash messages from the action handler.
$flashes = get_flashes();

// If a token is in the URL, validate it so we can show the reset form.
// We hash the raw token before lookup — the DB stores only SHA-256 hashes.
$token = input('token');
$hasValidToken = false;
$resetUser = null;
if ($token !== '') {
    $tokenHash = hash('sha256', $token);
    $stmt = db()->prepare(
        'SELECT rt.*, u.username, u.full_name
           FROM reset_tokens rt
           JOIN users u ON u.user_id = rt.user_id
          WHERE rt.token_hash = :hash
            AND rt.used_at IS NULL
            AND rt.expires_at > now()'
    );
    $stmt->execute([':hash' => $tokenHash]);
    $resetUser = $stmt->fetch();
    $hasValidToken = !empty($resetUser);
}

$error = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Reset Password - Green Valley SRMS</title>

    <!-- Google Fonts & Font Awesome Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <meta name="theme-color" content="#4f46e5">

    <style>
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
            background: #0f172a;
            padding: 24px;
            color: #0f172a;
        }

        /* BENTO WRAPPER CONTAINER */
        .bento-container {
            display: grid;
            grid-template-columns: 360px 440px;
            gap: 20px;
            max-width: 840px;
            width: 100%;
        }

        /* BENTO CARD STYLING */
        .bento-card {
            background: #ffffff;
            border-radius: 28px;
            padding: 32px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        /* LEFT INFO PANEL (BENTO CELL 1) */
        .bento-info {
            background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);
            color: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .brand-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 800;
            font-size: 1.1rem;
            margin-bottom: 24px;
        }

        .brand-badge i {
            font-size: 1.3rem;
            color: #38bdf8;
        }

        .info-content h2 {
            font-size: 1.8rem;
            font-weight: 800;
            line-height: 1.25;
            margin-bottom: 12px;
        }

        .info-content p {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.85);
            line-height: 1.6;
        }

        .security-pill {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(10px);
            padding: 10px 16px;
            border-radius: 16px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-top: 32px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        /* RIGHT FORM PANEL (BENTO CELL 2) */
        .bento-form-panel {
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        /* STEP INDICATOR DOTS */
        .step-indicators {
            display: flex;
            gap: 6px;
            margin-bottom: 24px;
        }

        .dot {
            width: 8px;
            height: 8px;
            border-radius: 4px;
            background: #e2e8f0;
            transition: all 0.3s ease;
        }

        .dot.active {
            width: 24px;
            background: #4f46e5;
        }

        .dot.completed {
            background: #10b981;
        }

        /* HEADINGS */
        .form-header h3 {
            font-size: 1.4rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 6px;
        }

        .form-header p {
            font-size: 0.85rem;
            color: #64748b;
            margin-bottom: 20px;
        }

        /* FORM CONTROLS */
        .input-group {
            margin-bottom: 16px;
        }

        .input-group label {
            display: block;
            font-size: 0.75rem;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .input-box {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-box i {
            position: absolute;
            left: 14px;
            color: #94a3b8;
            font-size: 0.95rem;
        }

        .input-box input {
            width: 100%;
            padding: 12px 14px 12px 42px;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            font-size: 0.9rem;
            color: #0f172a;
            background: #f8fafc;
            outline: none;
            transition: all 0.2s;
        }

        .input-box input:focus {
            border-color: #4f46e5;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        }

        .toggle-pass {
            position: absolute;
            right: 14px;
            cursor: pointer;
            color: #94a3b8;
        }

        /* PASSWORD STRENGTH BAR */
        .strength-wrapper {
            margin-top: 6px;
            margin-bottom: 16px;
        }

        .strength-track {
            height: 4px;
            background: #e2e8f0;
            border-radius: 2px;
            overflow: hidden;
            margin-bottom: 4px;
        }

        .strength-indicator-fill {
            height: 100%;
            width: 0%;
            background: #f59e0b;
            border-radius: 2px;
            transition: width 0.3s ease, background 0.3s ease;
        }

        .strength-label {
            font-size: 0.72rem;
            color: #64748b;
            display: flex;
            justify-content: space-between;
        }

        /* SUBMIT BUTTON */
        .btn-bento {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 12px;
            background: #4f46e5;
            color: #fff;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
            transition: all 0.2s;
        }

        .btn-bento:hover {
            background: #4338ca;
            transform: translateY(-1px);
        }

        /* BACK LINK */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            color: #64748b;
            text-decoration: none;
            margin-top: 16px;
            justify-content: center;
            width: 100%;
        }

        .back-link:hover {
            color: #4f46e5;
        }

        /* ALERT MESSAGES */
        .bento-alert {
            padding: 10px 14px;
            border-radius: 12px;
            font-size: 0.82rem;
            font-weight: 600;
            margin-bottom: 16px;
            line-height: 1.4;
        }
        .bento-alert-success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        .bento-alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        /* RESPONSIVE LAYOUT */
        @media (max-width: 800px) {
            .bento-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

  <div class="bento-container">

    <!-- BENTO CELL 1: BRAND & INFO PANEL -->
    <div class="bento-card bento-info">
      <div>
        <div class="brand-badge">
          <i class="fa-solid fa-graduation-cap"></i>
          <span>Green Valley SRMS</span>
        </div>
        <div class="info-content">
          <h2>Account Recovery Center</h2>
          <p>Securely reset your credentials using token verification. A reset link will be sent to your registered email address.</p>
        </div>
      </div>

      <div class="security-pill">
        <i class="fa-solid fa-shield-halved" style="color: #38bdf8;"></i>
        <span>End-to-End Encrypted Workflow</span>
      </div>
    </div>

    <!-- BENTO CELL 2: INTERACTIVE STEP FORM -->
    <div class="bento-card bento-form-panel">

      <!-- Progress Indicator -->
      <div class="step-indicators">
        <div class="dot <?= $hasValidToken ? 'completed' : 'active' ?>" id="dot-1"></div>
        <div class="dot <?= $hasValidToken ? 'active' : '' ?>" id="dot-2"></div>
      </div>

      <?php foreach ($flashes as $f): ?>
          <?php if ($f['type'] === 'info' && filter_var($f['message'], FILTER_VALIDATE_URL)): ?>
              <div class="bento-alert bento-alert-success" style="word-break:break-all;">
                  <strong>Reset link (copy and open in your browser):</strong><br>
                  <input type="text" value="<?= e($f['message']) ?>" readonly
                         style="width:100%;margin-top:6px;padding:8px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:0.8rem;background:#f8fafc;"
                         onclick="this.select();this.focus();">
              </div>
          <?php elseif ($f['type'] === 'success'): ?>
              <div class="bento-alert bento-alert-success"><?= e($f['message']) ?></div>
          <?php else: ?>
              <div class="bento-alert bento-alert-error"><?= e($f['message']) ?></div>
          <?php endif; ?>
      <?php endforeach; ?>

      <?php if ($error !== ''): ?>
          <div class="bento-alert bento-alert-error"><?= e($error) ?></div>
      <?php endif; ?>

      <?php if ($hasValidToken): ?>
          <!-- STEP 2: NEW PASSWORD -->
          <div class="form-header">
            <h3>New Credentials</h3>
            <p>Set a new password for <strong><?= e($resetUser['full_name']) ?></strong></p>
          </div>

          <form action="vendor/actions/reset_actions.php" method="post" data-validate>
              <input type="hidden" name="action" value="reset">
              <input type="hidden" name="token" value="<?= e($token) ?>">
              <?= csrf_field() ?>

              <div class="input-group">
                  <label>New Password</label>
                  <div class="input-box">
                      <i class="fa-solid fa-lock"></i>
                      <input type="password" id="password" name="password"
                             placeholder="••••••••" required minlength="8">
                      <i class="fa-solid fa-eye toggle-pass" onclick="togglePwd('password', this)"></i>
                  </div>
                  <div class="strength-wrapper">
                      <div class="strength-track"><div class="strength-indicator-fill" id="strengthFill"></div></div>
                      <div class="strength-label"><span>Strength Check</span> <b id="strengthText">—</b></div>
                  </div>
              </div>

              <div class="input-group">
                  <label>Confirm Password</label>
                  <div class="input-box">
                      <i class="fa-solid fa-key"></i>
                      <input type="password" id="confirm" name="confirm"
                             placeholder="••••••••" required minlength="8">
                  </div>
              </div>

              <p style="font-size:0.72rem;color:#64748b;margin-bottom:16px;">
                Password must be at least 8 characters with an uppercase letter, a number, and a special character.
              </p>

              <button type="submit" class="btn-bento">Update Password</button>
          </form>

      <?php else: ?>
          <!-- STEP 1: REQUEST CODE -->
          <div class="form-header">
            <h3>Forgot Password?</h3>
            <p>Enter the email address associated with your account to receive a reset link.</p>
          </div>

          <form action="vendor/actions/reset_actions.php" method="post">
              <input type="hidden" name="action" value="request">
              <?= csrf_field() ?>

              <div class="input-group">
                  <label>Email Address</label>
                  <div class="input-box">
                      <i class="fa-solid fa-envelope"></i>
                      <input type="email" id="email" name="email"
                             placeholder="you@example.com" required>
                  </div>
              </div>

              <button type="submit" class="btn-bento">Send Reset Link</button>
          </form>

      <?php endif; ?>

      <!-- Back Link -->
      <a href="login.php" class="back-link">
        <i class="fa-solid fa-arrow-left"></i> Return to Sign In
      </a>
    </div>

  </div>

  <script>
    function togglePwd(fieldId, iconElem) {
      const field = document.getElementById(fieldId);
      if (field.type === 'password') {
        field.type = 'text';
        iconElem.classList.remove('fa-eye');
        iconElem.classList.add('fa-eye-slash');
      } else {
        field.type = 'password';
        iconElem.classList.remove('fa-eye-slash');
        iconElem.classList.add('fa-eye');
      }
    }

    // Password strength checker
    (function () {
      var pwd = document.getElementById('password');
      var fill = document.getElementById('strengthFill');
      var text = document.getElementById('strengthText');
      if (!pwd || !fill || !text) return;
      pwd.addEventListener('input', function () {
        var v = pwd.value;
        var score = 0;
        if (v.length >= 8) score++;
        if (v.length >= 10) score++;
        if (/[A-Z]/.test(v)) score++;
        if (/[0-9]/.test(v)) score++;
        if (/[^A-Za-z0-9]/.test(v)) score++;
        var pct, color, label;
        if (score <= 1) { pct = 20; color = '#ef4444'; label = 'Weak'; }
        else if (score <= 3) { pct = 55; color = '#f59e0b'; label = 'Fair'; }
        else { pct = 100; color = '#10b981'; label = 'Strong'; }
        fill.style.width = pct + '%';
        fill.style.background = color;
        text.textContent = label;
        text.style.color = color;
      });
    })();
  </script>

</body>
</html>
