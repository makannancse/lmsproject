<?php
/**
 * Production diagnostic for Reports page.
 * DELETE THIS FILE after diagnosing. Access: /diag_reports.php
 */

// Basic auth guard — change 'secret123' to something unique before uploading
$secret = $_GET['key'] ?? '';
if ($secret !== 'edulearnwise_diag_2026') {
    http_response_code(403);
    exit('Forbidden');
}

header('Content-Type: text/plain; charset=utf-8');

echo "=== Reports Diagnostic ===\n\n";

// 1. PHP version & limits
echo "PHP Version: " . PHP_VERSION . "\n";
echo "memory_limit: " . ini_get('memory_limit') . "\n";
echo "max_execution_time: " . ini_get('max_execution_time') . "\n";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
echo "post_max_size: " . ini_get('post_max_size') . "\n\n";

// 2. Vendor autoload
$autoload = __DIR__ . '/vendor/autoload.php';
echo "Vendor autoload exists: " . (is_file($autoload) ? 'YES' : 'NO — run composer install') . "\n";

// 3. Dompdf availability
if (is_file($autoload)) {
    require_once $autoload;
    echo "Dompdf class exists: " . (class_exists('\\Dompdf\\Dompdf') ? 'YES' : 'NO — dompdf not installed') . "\n";
} else {
    echo "Dompdf check: SKIPPED (no autoload)\n";
}

// 4. uploads/reports directory
$uploadsDir = __DIR__ . '/uploads/reports';
echo "\nuploads/reports directory:\n";
echo "  Exists: " . (is_dir($uploadsDir) ? 'YES' : 'NO') . "\n";
if (!is_dir($uploadsDir)) {
    $made = @mkdir($uploadsDir, 0755, true);
    echo "  Created: " . ($made ? 'YES' : 'FAILED') . "\n";
}
echo "  Writable: " . (is_writable($uploadsDir) ? 'YES' : 'NO — chmod 755 or 777 needed') . "\n";

// 5. logs directory
$logsDir = __DIR__ . '/logs';
echo "\nlogs directory:\n";
echo "  Exists: " . (is_dir($logsDir) ? 'YES' : 'NO') . "\n";
echo "  Writable: " . (is_writable($logsDir) ? 'YES' : 'NO') . "\n";

// 6. Read last 20 lines of report.log if exists
$reportLog = $logsDir . '/report.log';
echo "\nreport.log (" . (is_file($reportLog) ? 'exists' : 'not found') . "):\n";
if (is_file($reportLog)) {
    $lines = file($reportLog);
    $lines = array_slice($lines, -20);
    echo implode('', $lines);
} else {
    echo "  (no log file yet)\n";
}

// 7. Test PDF generation
echo "\n=== PDF Generation Test ===\n";
$testReport = [
    'id' => 9999,
    'email' => 'test@test.com',
    'student_name' => 'Test Student',
    'teacher_name' => 'Test Teacher',
    'subject' => 'Test',
    'report_date' => date('Y-m-d'),
    'overall_performance' => 'Good',
    'concept_understanding' => 'Strong understanding',
    'application_ability' => 'Applies independently',
    'homework_completion' => 'Always on time',
    'attention_level' => 'Highly attentive',
    'participation_level' => 'Active',
    'behaviour' => 'Good',
    'subjects_addressed' => 'Math, English',
    'future_focus' => 'Algebra',
    'recommended_focus' => 'Reading comprehension',
    'study_strategies' => 'Daily practice',
    'additional_support' => 'None needed',
    'overall_feedback' => 'Great progress this month.',
];

require_once __DIR__ . '/app/lib/ReportLog.php';
require_once __DIR__ . '/reports/generate_report_pdf.php';
$result = generate_student_report_pdf($testReport);
echo "PDF ok: " . ($result['ok'] ? 'YES' : 'NO') . "\n";
if (!$result['ok']) {
    echo "PDF error: " . ($result['error'] ?? 'unknown') . "\n";
} else {
    echo "PDF path: " . ($result['absolute_path'] ?? '') . "\n";
    @unlink($result['absolute_path']); // clean up test file
}

echo "\n=== Done ===\n";
