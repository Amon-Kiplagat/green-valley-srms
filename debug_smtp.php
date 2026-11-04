<?php
/**
 * Debug page to check SMTP environment variables.
 * DELETE THIS FILE after debugging.
 */
require_once __DIR__ . '/includes/auth.php';
require_login();

$env = [];
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
        list($k, $v) = explode('=', $line, 2);
        $env[trim($k)] = trim($v);
    }
}

function get_val($key) {
    $val = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?? '';
    return $val ?: '';
}

$keys = ['SMTP_HOST', 'SMTP_PORT', 'SMTP_USER', 'SMTP_PASS', 'SMTP_FROM', 'APP_BASE_URL'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMTP Debug</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1a1a2e; color: #eee; }
        table { border-collapse: collapse; width: 100%; max-width: 800px; }
        th, td { padding: 8px 12px; border: 1px solid #444; text-align: left; }
        th { background: #16213e; }
        .set { color: #4ade80; }
        .empty { color: #f87171; }
        h1 { color: #4ade80; }
    </style>
</head>
<body>
    <h1>SMTP Environment Variables Debug</h1>
    <table>
        <tr>
            <th>Variable</th>
            <th>$_ENV</th>
            <th>$_SERVER</th>
            <th>getenv()</th>
            <th>.env file</th>
        </tr>
        <?php foreach ($keys as $key): ?>
        <tr>
            <td><strong><?= htmlspecialchars($key) ?></strong></td>
            <td class="<?= !empty($_ENV[$key]) ? 'set' : 'empty' ?>">
                <?= !empty($_ENV[$key]) ? '✓ set (' . strlen($_ENV[$key]) . ' chars)' : '✗ empty' ?>
            </td>
            <td class="<?= !empty($_SERVER[$key]) ? 'set' : 'empty' ?>">
                <?= !empty($_SERVER[$key]) ? '✓ set (' . strlen($_SERVER[$key]) . ' chars)' : '✗ empty' ?>
            </td>
            <td class="<?= !empty(get_val($key)) ? 'set' : 'empty' ?>">
                <?php
                $g = getenv($key) ?: '';
                echo !empty($g) ? '✓ set (' . strlen($g) . ' chars)' : '✗ empty';
                ?>
            </td>
            <td class="<?= !empty($env[$key] ?? '') ? 'set' : 'empty' ?>">
                <?= !empty($env[$key] ?? '') ? '✓ set (' . strlen($env[$key]) . ' chars)' : '✗ empty' ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>

    <h2>PHPMailer Check</h2>
    <?php
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
        if (class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
            echo '<p class="set">✓ PHPMailer is installed</p>';
        } else {
            echo '<p class="empty">✗ PHPMailer class not found</p>';
        }
    } else {
        echo '<p class="empty">✗ vendor/autoload.php not found</p>';
    }
    ?>

    <h2>mail() function</h2>
    <p class="<?= function_exists('mail') ? 'set' : 'empty' ?>">
        <?= function_exists('mail') ? '✓ mail() function exists' : '✗ mail() function not available' ?>
    </p>

    <p><a href="dashboard.php">← Back to Dashboard</a></p>
</body>
</html>
