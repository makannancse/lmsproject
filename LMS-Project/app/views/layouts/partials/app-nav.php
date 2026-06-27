<?php

use function htmlspecialchars as h;

/** @var string $base */
/** @var string $currentPath */

$is = static function (string $href) use ($base, $currentPath): bool {
    $full = rtrim($base . $href, '/');
    $cur = rtrim($currentPath, '/');
    if ($href === '/dashboard' || $href === '') {
        return str_ends_with($cur, '/dashboard');
    }
    if ($cur === $full) {
        return true;
    }
    if (str_starts_with($cur, $full . '/')) {
        if ($href === '/classes' && str_contains($cur, '/classes/completed')) {
            return false;
        }

        return true;
    }

    return false;
};

$item = static function (string $href, string $icon, string $label) use ($base, $is): void {
    $active = $is($href) ? 'active' : '';
    echo '<a class="nav-link app-nav-link ' . $active . '" href="' . h($base . $href) . '">';
    echo '<i class="' . h($icon) . ' fa-fw me-2"></i><span>' . h($label) . '</span></a>';
};
?>
<nav class="nav flex-column gap-1 py-2 px-2">
    <?php $item('/dashboard', 'fa-solid fa-gauge-high', 'Dashboard'); ?>

    <?php if (Auth::isAdmin()): ?>
        <?php $item('/admin/users', 'fa-solid fa-users', 'Users'); ?>
        <?php $item('/admin/teacher-students', 'fa-solid fa-link', 'Teacher-Students'); ?>
        <?php $item('/admin/calendar', 'fa-solid fa-calendar-days', 'Calendar'); ?>
        <?php $item('/classes', 'fa-solid fa-chalkboard-user', 'Classes'); ?>
        <?php $item('/classes/completed', 'fa-solid fa-circle-check', 'Completed'); ?>
        <?php $item('/admin/class-types', 'fa-solid fa-layer-group', 'Class types'); ?>
        <?php $item('/admin/reschedule', 'fa-solid fa-clock-rotate-left', 'Reschedule'); ?>
        <?php $item('/teacher/homework', 'fa-solid fa-book-open', 'Homework'); ?>
        <?php $item('/admin/reports', 'fa-solid fa-file-lines', 'Reports'); ?>
        <?php $item('/admin/payments', 'fa-solid fa-wallet', 'Payments'); ?>
        <?php $item('/admin/feedback', 'fa-solid fa-comment-dots', 'Feedback'); ?>
        <?php $item('/admin/student-payments', 'fa-solid fa-indian-rupee-sign', 'Student Payments'); ?>
        <?php $item('/admin/recordings', 'fa-solid fa-video', 'Recordings'); ?>
    <?php elseif (Auth::isTeacher()): ?>
        <?php $item('/teacher/calendar', 'fa-solid fa-calendar-days', 'Calendar'); ?>
        <?php $item('/teacher/reschedule', 'fa-solid fa-clock-rotate-left', 'Reschedule'); ?>
        <?php $item('/teacher/homework', 'fa-solid fa-book-open', 'Homework'); ?>
        <?php $item('/teacher/reports', 'fa-solid fa-file-lines', 'Reports'); ?>
        <?php $item('/teacher/feedback', 'fa-solid fa-comment-dots', 'Feedback'); ?>
    <?php elseif (Auth::isStudent()): ?>
        <?php $item('/student/calendar', 'fa-solid fa-calendar-days', 'Calendar'); ?>
        <?php $item('/student/reschedule', 'fa-solid fa-clock-rotate-left', 'Reschedule'); ?>
        <?php $item('/student/homework', 'fa-solid fa-book-open', 'Homework'); ?>
        <?php $item('/student/reports', 'fa-solid fa-file-lines', 'Reports'); ?>
        <?php $item('/student/feedback', 'fa-solid fa-comment-dots', 'Feedback'); ?>
        <?php $item('/student/payments', 'fa-solid fa-indian-rupee-sign', 'Payments'); ?>
    <?php endif; ?>
</nav>
