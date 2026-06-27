<?php

declare(strict_types=1);

$files = [
    'app/lib/Auth.php',
    'app/controllers/ClassController.php',
    'app/controllers/RecordingController.php',
    'app/controllers/MeetingTrackingController.php',
    'app/controllers/ReportController.php',
    'app/controllers/RescheduleController.php',
    'app/controllers/HomeworkController.php',
    'app/controllers/TeacherStudentMapController.php',
    'app/controllers/AdminController.php',
    'app/routes.php',
    'app/controllers/StudentPaymentController.php',
    'app/controllers/SettingsController.php',
    'app/controllers/FeedbackController.php',
    'app/controllers/DashboardController.php',
    'app/controllers/ClassMasterController.php',
    'app/controllers/CalendarController.php',
];

$root = dirname(__DIR__) . DIRECTORY_SEPARATOR;
$old = "\$base = defined('BASE_PATH') ? BASE_PATH : '';";
$new = '$base = appWebPath();';

foreach ($files as $file) {
    $path = $root . $file;
    $content = file_get_contents($path);
    $updated = str_replace($old, $new, $content, $count);
    if ($count > 0) {
        file_put_contents($path, $updated);
        echo $file . ': ' . $count . PHP_EOL;
    }
}
