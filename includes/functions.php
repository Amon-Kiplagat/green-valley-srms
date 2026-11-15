<?php

declare(strict_types=1);

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

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

function set_flash(string $type, string $message): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

function render_flashes(): string
{
    $html = '';
    foreach (get_flashes() as $f) {
        $cls = $f['type'] === 'success' ? 'alert-success' : 'alert-error';
        $html .= '<div class="alert ' . $cls . '">' . e($f['message']) . '</div>';
    }
    return $html;
}

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

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

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

// Normalize Kenyan phone number to 10-digit local form (0712345678).
// Returns ['ok' => bool, 'value' => ?string, 'error' => ?string].
function normalize_phone(string $raw): array
{
    $raw = trim($raw);

    if ($raw === '') {
        return ['ok' => true, 'value' => null, 'error' => null];
    }

    if (preg_match('/[^0-9+\s\-().]/', $raw)) {
        return ['ok' => false, 'value' => null,
            'error' => 'Phone number may contain digits only - no letters or symbols. Example: 0712345678.'];
    }

    if (strpos($raw, '+') !== false && strpos($raw, '+') !== 0) {
        return ['ok' => false, 'value' => null,
            'error' => 'The "+" may only appear at the start of a phone number. Example: +254712345678.'];
    }

    $digits = preg_replace('/\D+/', '', $raw);

    if ($digits === '') {
        return ['ok' => false, 'value' => null,
            'error' => 'Please enter a phone number using digits. Example: 0712345678.'];
    }

    if (str_starts_with($digits, '00254')) {
        $subscriber = substr($digits, 5);
    } elseif (str_starts_with($digits, '254')) {
        $subscriber = substr($digits, 3);
    } elseif (str_starts_with($digits, '0')) {
        $subscriber = substr($digits, 1);
    } else {
        $subscriber = $digits;
    }

    if (!preg_match('/^[71][0-9]{8}$/', $subscriber)) {
        return ['ok' => false, 'value' => null,
            'error' => 'Enter a valid Kenyan phone number: 10 digits starting 07 or 01 (e.g. 0712345678), or the +254 form.'];
    }

    return ['ok' => true, 'value' => '0' . $subscriber, 'error' => null];
}

// Format stored phone for display: 0712345678 -> 0712 345 678.
function format_phone_display(?string $phone): string
{
    $phone = (string)($phone ?? '');
    if (preg_match('/^0([71][0-9]{2})([0-9]{3})([0-9]{3})$/', $phone, $m)) {
        return '0' . $m[1] . ' ' . $m[2] . ' ' . $m[3];
    }
    return $phone;
}

function str_cap(string $value, int $limit): string
{
    return function_exists('mb_substr')
        ? mb_substr($value, 0, $limit)
        : substr($value, 0, $limit);
}

function name_initial(?string $name): string
{
    $name = trim((string)($name ?? ''));
    if ($name === '') {
        return '?';
    }
    return function_exists('mb_strtoupper')
        ? mb_strtoupper(mb_substr($name, 0, 1))
        : strtoupper(substr($name, 0, 1));
}

// Log to activity_log table. Silently fails if table missing.
function log_activity(string $action, ?string $entity = null, ?string $entityLabel = null, ?array $actor = null): void
{
    try {
        if (!function_exists('db')) {
            return;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $userId   = $actor['user_id']   ?? ($_SESSION['user_id']   ?? null);
        $username = $actor['username']  ?? ($_SESSION['username']  ?? 'unknown');
        $fullName = $actor['full_name'] ?? ($_SESSION['full_name'] ?? 'Unknown user');
        $role     = $actor['role']      ?? ($_SESSION['role']      ?? null);
        $ip       = (string)($_SERVER['REMOTE_ADDR'] ?? '');

        $stmt = db()->prepare(
            'INSERT INTO activity_log
                (user_id, username, full_name, role, action, entity, entity_label, ip_address)
             VALUES
                (:user_id, :username, :full_name, :role, :action, :entity, :entity_label, :ip_address)'
        );
        $stmt->execute([
            ':user_id'      => $userId !== null ? (int)$userId : null,
            ':username'     => str_cap((string)$username, 60),
            ':full_name'    => str_cap((string)$fullName, 120),
            ':role'         => $role,
            ':action'       => str_cap($action, 40),
            ':entity'       => $entity !== null ? str_cap($entity, 40) : null,
            ':entity_label' => $entityLabel !== null ? str_cap($entityLabel, 160) : null,
            ':ip_address'   => $ip !== '' ? str_cap($ip, 45) : null,
        ]);
    } catch (Throwable $e) {
        // Audit failure must not block the user.
    }
}

function activity_sentence(array $row): string
{
    $text = (string)($row['action'] ?? '');
    if (!empty($row['entity'])) {
        $text .= ' ' . $row['entity'];
    }
    if (!empty($row['entity_label'])) {
        $text .= ' - ' . $row['entity_label'];
    }
    return $text;
}

function time_ago(?string $timestamp): string
{
    if (empty($timestamp)) {
        return '';
    }
    $then = strtotime($timestamp);
    if ($then === false) {
        return '';
    }

    $seconds = time() - $then;
    if ($seconds < 60) { return 'just now'; }

    $minutes = (int)floor($seconds / 60);
    if ($minutes < 60) { return $minutes . ' minute' . ($minutes === 1 ? '' : 's') . ' ago'; }

    $hours = (int)floor($minutes / 60);
    if ($hours < 24) { return $hours . ' hour' . ($hours === 1 ? '' : 's') . ' ago'; }

    $days = (int)floor($hours / 24);
    if ($days < 30) { return $days . ' day' . ($days === 1 ? '' : 's') . ' ago'; }

    $months = (int)floor($days / 30);
    if ($months < 12) { return $months . ' month' . ($months === 1 ? '' : 's') . ' ago'; }

    $years = (int)floor($months / 12);
    return $years . ' year' . ($years === 1 ? '' : 's') . ' ago';
}

function format_when(?string $timestamp, string $format = 'D j M Y, H:i'): string
{
    if (empty($timestamp)) {
        return '—';
    }
    $ts = strtotime($timestamp);
    return $ts === false ? '—' : date($format, $ts);
}

function input(string $key, string $default = ''): string
{
    $val = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($val) ? trim($val) : $default;
}

function redirect(string $to): void
{
    if ($to !== '' && $to[0] !== '/' && !str_starts_with($to, 'http')) {
        $to = '/' . $to;
    }
    header('Location: ' . $to);
    exit;
}
