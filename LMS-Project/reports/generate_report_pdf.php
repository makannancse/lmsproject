<?php

declare(strict_types=1);

/**
 * Generate student report PDF using Dompdf (pure PHP — no Python).
 * Output: /uploads/reports/report_{id}.pdf
 */
function generate_student_report_pdf(array $report): array
{
    require_once dirname(__DIR__) . '/app/lib/ReportLog.php';

    // Boost limits for PDF generation — production servers often cap at 128M/30s
    @ini_set('memory_limit', '256M');
    @ini_set('max_execution_time', '120');

    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        ReportLog::line('PDF FAILED: Composer autoload missing. Run composer install.');
        return ['ok' => false, 'error' => 'Composer dependencies not installed (dompdf).'];
    }
    require_once $autoload;

    $projectRoot = dirname(__DIR__);
    $uploadsDir = $projectRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'reports';
    if (!is_dir($uploadsDir)) {
        if (!@mkdir($uploadsDir, 0755, true) && !is_dir($uploadsDir)) {
            ReportLog::line('PDF FAILED: Could not create uploads/reports directory at ' . $uploadsDir);
            return ['ok' => false, 'error' => 'Could not create reports upload directory'];
        }
    }
    if (!is_writable($uploadsDir)) {
        ReportLog::line('PDF FAILED: uploads/reports directory is not writable: ' . $uploadsDir);
        return ['ok' => false, 'error' => 'Reports upload directory is not writable — check server file permissions.'];
    }

    $reportId = (int) ($report['id'] ?? 0);
    if ($reportId <= 0) {
        ReportLog::line('PDF FAILED: Invalid report id.');
        return ['ok' => false, 'error' => 'Invalid report id'];
    }

    $fileName = 'report_' . $reportId . '.pdf';
    $outputPdf = $uploadsDir . DIRECTORY_SEPARATOR . $fileName;
    $relativePath = 'uploads/reports/' . $fileName;

    $esc = static function (string $s): string {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    };

    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
        h1 { font-size: 18px; margin: 0 0 12px; color: #1a1a2e; }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .meta td { padding: 4px 8px; border: 1px solid #ddd; vertical-align: top; }
        .meta td.lbl { width: 28%; background: #f5f6f8; font-weight: bold; }
        h2 { font-size: 12px; margin: 14px 0 6px; color: #16213e; border-bottom: 1px solid #e0e0e0; padding-bottom: 4px; }
        .block { margin-bottom: 10px; line-height: 1.45; white-space: pre-wrap; }
    </style></head><body>';

    $html .= '<h1>Student Report Card</h1>';
    $html .= '<table class="meta">';
    $rows = [
        ['Email', (string) ($report['email'] ?? '')],
        ['Student Name', (string) ($report['student_name'] ?? '')],
        ['Teacher Name', (string) ($report['teacher_name'] ?? '')],
        ['Subject', (string) ($report['subject'] ?? '')],
        ['Report Date', (string) ($report['report_date'] ?? '')],
    ];
    foreach ($rows as [$lbl, $val]) {
        $html .= '<tr><td class="lbl">' . $esc($lbl) . '</td><td>' . $esc($val) . '</td></tr>';
    }
    $html .= '</table>';

    $sections = [
        'Overall Academic Performance' => 'overall_performance',
        'Level of Concept Understanding' => 'concept_understanding',
        'Ability to Apply Concepts and Knowledge Retention' => 'application_ability',
        'Homework Completion' => 'homework_completion',
        'Attention During Class' => 'attention_level',
        'Participation Level' => 'participation_level',
        'Behaviour & Discipline' => 'behaviour',
        'Subjects Addressed' => 'subjects_addressed',
        'Future Focus' => 'future_focus',
        'Recommended Areas for Focus' => 'recommended_focus',
        'Suggested Study Strategies' => 'study_strategies',
        'Additional Support Required' => 'additional_support',
        'Overall Feedback' => 'overall_feedback',
    ];

    foreach ($sections as $title => $key) {
        $val = trim((string) ($report[$key] ?? ''));
        if ($val === '') {
            $val = '—';
        }
        $html .= '<h2>' . $esc($title) . '</h2>';
        $html .= '<div class="block">' . nl2br($esc($val)) . '</div>';
    }

    $html .= '</body></html>';

    try {
        $options = new \Dompdf\Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->setChroot($projectRoot);

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdfOutput = $dompdf->output();
        if ($pdfOutput === false || $pdfOutput === '') {
            ReportLog::line('PDF FAILED: Dompdf returned empty output for report id ' . $reportId);
            return ['ok' => false, 'error' => 'PDF render returned empty output'];
        }
        if (@file_put_contents($outputPdf, $pdfOutput) === false) {
            ReportLog::line('PDF FAILED: Could not write file ' . $outputPdf);
            return ['ok' => false, 'error' => 'Could not write PDF file — check server file permissions on uploads/reports/'];
        }
    } catch (\Throwable $ex) {
        ReportLog::line('PDF FAILED: ' . $ex->getMessage() . ' in ' . $ex->getFile() . ':' . $ex->getLine());
        return ['ok' => false, 'error' => 'PDF generation error: ' . $ex->getMessage()];
    }

    if (!file_exists($outputPdf)) {
        ReportLog::line('PDF FAILED: file_exists check failed for ' . $outputPdf);
        return ['ok' => false, 'error' => 'PDF file missing after write'];
    }

    ReportLog::line('PDF Created: ' . $outputPdf);

    return [
        'ok' => true,
        'absolute_path' => $outputPdf,
        'relative_path' => $relativePath,
        'file_name' => $fileName,
    ];
}
