<?php
/**
 * logout.php
 * ---------------------------------------------------------------------------
 * Destroys the session and returns the user to the login page.
 * ---------------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

logout_user();

// Start a fresh session just to carry the goodbye flash message.
session_start();
set_flash('success', 'You have been logged out.');
redirect('login.php');
