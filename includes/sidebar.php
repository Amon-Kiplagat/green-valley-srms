<?php
/**
 * includes/sidebar.php
 * ---------------------------------------------------------------------------
 * Left navigation (redesigned). Links are unchanged; they still point at the
 * .php pages and the active page is highlighted via $navClass(). Nav is now
 * grouped (Overview / Records / Assessment) with icons, and the signed-in
 * user + logout sit in the sidebar footer.
 * ---------------------------------------------------------------------------
 */

$current = basename($_SERVER['PHP_SELF'] ?? '');

/** Helper: mark a nav link active when it matches the current page. */
$navClass = static function (string $file) use ($current): string {
    return $file === $current ? 'active' : '';
};

/** Font Awesome icon class for a nav item. */
$navIcon = static function (string $key): string {
    $icons = [
        'dashboard' => 'fa-solid fa-table-columns',
        'profile'   => 'fa-solid fa-user-gear',
        'students'  => 'fa-solid fa-user-graduate',
        'teachers'  => 'fa-solid fa-chalkboard-user',
        'classes'   => 'fa-solid fa-school',
        'subjects'  => 'fa-solid fa-book',
        'exams'     => 'fa-solid fa-clipboard-list',
        'results'   => 'fa-solid fa-pen-to-square',
        'reports'   => 'fa-solid fa-chart-column',
        'logout'    => 'fa-solid fa-right-from-bracket',
    ];
    $icon = $icons[$key] ?? 'fa-solid fa-circle';
    return '<i class="' . $icon . '" aria-hidden="true"></i>';
};

/** Nav structure: group label => [ file => [label, icon-key] ]. */
$loggedIn = function_exists('is_logged_in') && is_logged_in();
$isStudent = function_exists('is_student') && is_student();
$hasSession = $loggedIn || $isStudent;
$userName = $loggedIn
    ? ($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User')
    : ($isStudent ? ($_SESSION['student_name'] ?? 'Student') : 'User');
$userRole = $loggedIn
    ? (function_exists('current_role') ? (string)(current_role() ?? '') : '')
    : ($isStudent ? 'Student' : '');

/** Student portal nav: only Dashboard, Exams, Results, Reports, Profile. */
$studentNav = [
    'student_home.php'      => ['Dashboard', 'dashboard'],
    'student_exams.php'     => ['Exams',     'exams'],
    'student_results.php'   => ['Results',   'results'],
    'student_reports.php'   => ['Reports',   'reports'],
    'student_password.php'  => ['Profile',   'profile'],
];

/** Admin/teacher nav: full navigation. */
$staffNav = [
    'Overview' => [
        'dashboard.php' => ['Dashboard', 'dashboard'],
        'profile.php'   => ['Profile', 'profile'],
    ],
    'Records' => [
        'students.php'         => ['Students', 'students'],
        'teachers.php'         => ['Teachers', 'teachers'],
        'teacher_accounts.php' => ['Teacher Accounts', 'teachers'],
        'classes.php'          => ['Classes',  'classes'],
        'subjects.php'         => ['Subjects', 'subjects'],
    ],
    'Assessment' => [
        'exams.php'   => ['Exams',   'exams'],
        'results.php' => ['Results', 'results'],
        'reports.php' => ['Reports', 'reports'],
    ],
];
?>
<aside class="sidebar" id="sidebar">
    <div class="brand">
        <div class="brand-crest" aria-hidden="true">GV</div>
        <div class="brand-name">Green Valley<span>Records System</span></div>
    </div>

    <nav class="nav" aria-label="Main navigation">
        <?php if ($isStudent): ?>
            <?php foreach ($studentNav as $file => [$text, $icon]): ?>
                <a class="<?= $navClass($file) ?>" href="<?= e($file) ?>"<?= $navClass($file) ? ' aria-current="page"' : '' ?>>
                    <?= $navIcon($icon) ?><span><?= e($text) ?></span>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <?php foreach ($staffNav as $label => $links): ?>
                <div class="nav-group">
                    <div class="nav-label"><?= e($label) ?></div>
                    <?php foreach ($links as $file => [$text, $icon]): ?>
                        <a class="<?= $navClass($file) ?>" href="<?= e($file) ?>"<?= $navClass($file) ? ' aria-current="page"' : '' ?>>
                            <?= $navIcon($icon) ?><span><?= e($text) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </nav>

    <?php if ($hasSession): ?>
        <div class="side-foot">
            <div class="side-user">
                <b><?= e($userName) ?></b>
                <?php if ($userRole !== ''): ?><small><?= e(ucfirst($userRole)) ?></small><?php endif; ?>
            </div>
            <a class="logout-btn" href="logout.php"><?= $navIcon('logout') ?><span>Sign out</span></a>
        </div>
    <?php else: ?>
        <div class="side-foot">
            <a class="logout-btn" href="login.php"><?= $navIcon('logout') ?><span>Sign in</span></a>
        </div>
    <?php endif; ?>
</aside>
