<?php
/**
 * includes/functions.php
 * ---------------------------------------------------------------------------
 * Small helpers shared across the app: output escaping, flash messages,
 * CSRF protection, and the grade calculator.
 * ---------------------------------------------------------------------------
 */

declare(strict_types=1);

/**
 * Escape a value for safe output in HTML (prevents XSS).
 * Use everywhere untrusted/DB data is printed into a page.
 */
function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Convert a numeric mark (0-100) into a KCSE 12-point grade.
 * Returns the grade string (A, A-, B+, ... E).
 */
function calculate_grade($marks): string
{
    $m = (float)$marks;
    if ($m < 0)  { $m = 0; }
    if ($m > 100){ $m = 100; }

    return match (true) {
        $m >= 80 => 'A',
        $m >= 75 => 'A-',
        $m >= 70 => 'B+',
        $m >= 65 => 'B',
        $m >= 60 => 'B-',
        $m >= 55 => 'C+',
        $m >= 50 => 'C',
        $m >= 45 => 'C-',
        $m >= 40 => 'D+',
        $m >= 35 => 'D',
        $m >= 30 => 'D-',
        default  => 'E',
    };
}

/* ------------------------------- Flash messages ------------------------- */

/**
 * Queue a one-time message shown on the next page load.
 * $type is 'success' or 'error'.
 */
function set_flash(string $type, string $message): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/**
 * Pull all queued flash messages and clear them.
 * @return array<int,array{type:string,message:string}>
 */
function get_flashes(): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/**
 * Render queued flash messages as HTML (call once near the top of a page).
 */
function render_flashes(): string
{
    $html = '';
    foreach (get_flashes() as $f) {
        $cls = $f['type'] === 'success' ? 'alert-success' : 'alert-error';
        $html .= '<div class="alert ' . $cls . '">' . e($f['message']) . '</div>';
    }
    return $html;
}

/* ------------------------------- CSRF tokens ---------------------------- */

/**
 * Return the current CSRF token, generating one if needed.
 */
function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Hidden input carrying the CSRF token; drop this inside every POST form.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Validate the submitted CSRF token. Aborts the request on mismatch.
 */
function verify_csrf(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $sent = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$sent)) {
        http_response_code(400);
        die('Invalid or missing CSRF token. Please go back and try again.');
    }
}

/* ------------------------------- Misc helpers --------------------------- */

/**
 * Trimmed string from POST (or GET) with a default.
 */
function input(string $key, string $default = ''): string
{
    $val = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($val) ? trim($val) : $default;
}

/**
 * Redirect helper (adds an optional flash first).
 */
function redirect(string $to): void
{
    header('Location: ' . $to);
    exit;
}
