<?php
/**
 * includes/sidebar.php
 * ---------------------------------------------------------------------------
 * Left navigation. Links now point to the .php pages. The Results link is new
 * (marks interface). The currently active page is highlighted.
 * ---------------------------------------------------------------------------
 */

$current = basename($_SERVER['PHP_SELF'] ?? '');

/** Helper: mark a nav link active when it matches the current page. */
$navClass = static function (string $file) use ($current): string {
    return $file === $current ? 'active' : '';
};
?>
<div class="sidebar">
    <h2>Green Valley SRMS</h2>
    <a class="<?= $navClass('dashboard.php') ?>" href="dashboard.php">Dashboard</a>
    <a class="<?= $navClass('students.php')  ?>" href="students.php">Students</a>
    <a class="<?= $navClass('teachers.php')  ?>" href="teachers.php">Teachers</a>
    <a class="<?= $navClass('classes.php')   ?>" href="classes.php">Classes</a>
    <a class="<?= $navClass('subjects.php')  ?>" href="subjects.php">Subjects</a>
    <a class="<?= $navClass('exams.php')     ?>" href="exams.php">Exams</a>
    <a class="<?= $navClass('results.php')   ?>" href="results.php">Results</a>
    <a class="<?= $navClass('reports.php')   ?>" href="reports.php">Reports</a>
    <div class="logout">
        <a href="logout.php">Logout</a>
    </div>
</div>
