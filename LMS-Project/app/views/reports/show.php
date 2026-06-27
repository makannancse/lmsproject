<?php

use function htmlspecialchars as h;

$base = appWebPath();
$r = $report ?? [];

$back = '/student/reports';
if (Auth::isAdmin()) {
    $back = '/admin/reports';
} elseif (Auth::isTeacher()) {
    $back = '/teacher/reports';
}
?>

<div class="row justify-content-center">
    <div class="col-12 col-lg-9">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h1 class="h4 mb-0">Student Report Card</h1>
                    <div class="d-flex gap-1">
                        <?php
                        $rid = (int) ($r['id'] ?? 0);
                        $pdfRel = (string) ($r['pdf_path'] ?? '');
                        $pdfAbs = $pdfRel !== '' ? dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($pdfRel, '/\\')) : '';
                        $pdfOk = $pdfRel !== '' && is_file($pdfAbs);
                        ?>
                        <?php if ($pdfOk): ?>
                            <a href="<?= h($base . '/reports/download?id=' . $rid) ?>" class="btn btn-outline-secondary btn-sm">Download PDF</a>
                        <?php elseif ($pdfRel !== ''): ?>
                            <span class="btn btn-outline-secondary btn-sm disabled">PDF file missing</span>
                        <?php endif; ?>
                        <a href="<?= h($base . $back) ?>" class="btn btn-outline-primary btn-sm">Back</a>
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-md-6"><strong>Email:</strong> <?= h((string) ($r['email'] ?? '')) ?></div>
                    <div class="col-md-6"><strong>Student Name:</strong> <?= h((string) ($r['student_name'] ?? '')) ?></div>
                    <div class="col-md-6"><strong>Teacher Name:</strong> <?= h((string) ($r['teacher_name'] ?? '')) ?></div>
                    <div class="col-md-6"><strong>Class Title:</strong> <?= h((string) ($r['subject'] ?? '')) ?></div>
                    <div class="col-md-6"><strong>Report Date:</strong> <?= h((string) ($r['report_date'] ?? '')) ?></div>
                </div>
                <hr>
                <p><strong>Overall Academic Performance:</strong> <?= h((string) ($r['overall_performance'] ?? '')) ?></p>
                <p><strong>Level of Concept Understanding:</strong> <?= h((string) ($r['concept_understanding'] ?? '')) ?></p>
                <p><strong>Ability to Apply Concepts and Knowledge Retention:</strong> <?= h((string) ($r['application_ability'] ?? '')) ?></p>
                <p><strong>Homework Completion:</strong> <?= h((string) ($r['homework_completion'] ?? '')) ?></p>
                <p><strong>Attention During Class:</strong> <?= h((string) ($r['attention_level'] ?? '')) ?></p>
                <p><strong>Participation Level:</strong> <?= h((string) ($r['participation_level'] ?? '')) ?></p>
                <p><strong>Behaviour &amp; Discipline:</strong> <?= h((string) ($r['behaviour'] ?? '')) ?></p>
                <p><strong>Topics Covered:</strong><br><?= nl2br(h((string) ($r['subjects_addressed'] ?? ''))) ?></p>
                <p><strong>Future Focus:</strong><br><?= nl2br(h((string) ($r['future_focus'] ?? ''))) ?></p>
                <p><strong>Recommended Areas for Focus:</strong><br><?= nl2br(h((string) ($r['recommended_focus'] ?? ''))) ?></p>
                <p><strong>Suggested Study Strategies:</strong><br><?= nl2br(h((string) ($r['study_strategies'] ?? ''))) ?></p>
                <p><strong>Additional Support Required:</strong><br><?= nl2br(h((string) ($r['additional_support'] ?? ''))) ?></p>
                <p><strong>Overall Feedback:</strong><br><?= nl2br(h((string) ($r['overall_feedback'] ?? ''))) ?></p>
            </div>
        </div>
    </div>
</div>

