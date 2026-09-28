<?php
/**
 * includes/header.php
 * ---------------------------------------------------------------------------
 * Top of every protected page. Renders <head> (links css/js), opens <body>,
 * pulls in the sidebar, and opens the main content area with a top bar.
 *
 * A page should set $page_title before including this file:
 *     $page_title = 'Students';
 *     require_once __DIR__ . '/includes/header.php';
 * ---------------------------------------------------------------------------
 */

if (!isset($page_title)) {
    $page_title = 'Green Valley SRMS';
}
// Auth/functions are expected to be loaded by the page already; be defensive.
if (!function_exists('e')) {
    require_once __DIR__ . '/functions.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> - Green Valley SRMS</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<?php require __DIR__ . '/sidebar.php'; ?>

<div class="main">
    <div class="topbar">
        <h1><?= e($page_title) ?></h1>
        <?php if (function_exists('is_logged_in') && is_logged_in()): ?>
            <span class="userbox">
                <?= e($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User') ?>
                <small>(<?= e(ucfirst((string)(current_role() ?? ''))) ?>)</small>
            </span>
        <?php endif; ?>
    </div>

    <?= function_exists('render_flashes') ? render_flashes() : '' ?>
