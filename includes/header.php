<?php

if (!isset($page_title)) {
    $page_title = 'Green Valley SRMS';
}
if (!isset($page_subtitle)) {
    $page_subtitle = '';
}
if (!function_exists('e')) {
    require_once __DIR__ . '/functions.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= e($page_title) ?> — Green Valley SRMS</title>
    <link rel="stylesheet" href="css/style.css?v=<?= time() ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <meta name="theme-color" content="#4f46e5">
    <meta name="description" content="Student Records Management System for Green Valley Secondary School, Murang'a County.">
    <link rel="manifest" href="manifest.webmanifest">
    <link rel="icon" href="icons/favicon-32.png" sizes="32x32" type="image/png">
    <link rel="apple-touch-icon" href="icons/apple-touch-icon.png">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="GV Records">
</head>
<body>

<?php require __DIR__ . '/sidebar.php'; ?>

<div class="main">
    <header class="topbar">
        <button class="nav-toggle no-print" type="button" aria-label="Open menu"
                aria-controls="sidebar" aria-expanded="false">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M3 6h18M3 12h18M3 18h18"/>
            </svg>
        </button>
        <div class="topbar-title">
            <h1><?= e($page_title) ?></h1>
            <?php if ($page_subtitle !== ''): ?>
                <span class="page-sub"><?= e($page_subtitle) ?></span>
            <?php endif; ?>
        </div>
        <div class="topbar-meta">
            <div class="today"><?= e(date('D, j M Y')) ?></div>
            <div>Green Valley Secondary School</div>
        </div>
    </header>

    <?= function_exists('render_flashes') ? render_flashes() : '' ?>
