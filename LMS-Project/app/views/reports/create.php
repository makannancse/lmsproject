<?php

use function htmlspecialchars as h;

$base = appWebPath();
$students = $students ?? [];
$teachers = $teachers ?? [];
$errors = $errors ?? [];
$old = $old ?? [];
$options = $options ?? [];

$radioField = static function (string $name, string $label) use ($options, $old): void {
    $selected = (string) ($old[$name] ?? '');
    $items = $options[$name] ?? [];
    echo '<div class="report-field-group">';
    echo '<label class="report-field-label">' . h($label) . ' <span class="text-danger">*</span></label>';
    echo '<div class="report-radio-grid">';
    foreach ($items as $item) {
        $id = $name . '_' . preg_replace('/[^a-z0-9]+/i', '_', strtolower($item));
        $checked = $selected === $item ? 'checked' : '';
        echo '<div class="form-check report-radio-item">';
        echo '<input class="form-check-input" type="radio" id="' . h($id) . '" name="' . h($name) . '" value="' . h($item) . '" ' . $checked . ' required>';
        echo '<label class="form-check-label" for="' . h($id) . '">' . h($item) . '</label>';
        echo '</div>';
    }
    echo '</div></div>';
};

$action = $base . (Auth::isAdmin() ? '/admin/reports' : '/teacher/reports');
?>

<div class="report-form-page">
    <div class="report-form-header card border-0 shadow-sm mb-4">
        <div class="card-body p-4 p-md-5">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <p class="text-uppercase text-muted small fw-semibold mb-1 letter-space">LMS — Student report</p>
                    <h1 class="h3 mb-2 fw-semibold text-dark">Create student report card</h1>
                    <p class="text-muted mb-0 small">All fields are required. Answers are saved in the LMS and a PDF is emailed to the parent when their email is on file.</p>
                </div>
                <a href="<?= h((string) ($googleFormUrl ?? '#')) ?>" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                    <i class="fa-solid fa-external-link-alt me-1"></i> Open Google Form
                </a>
            </div>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger border-0 shadow-sm mb-4" role="alert">
            <strong>Please fix the following:</strong>
            <ul class="mb-0 mt-2"><?php foreach ($errors as $error): ?><li><?= h((string) $error) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= h($action) ?>" class="report-google-form" id="reportCreateForm">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body p-4">
                <h2 class="h6 text-primary mb-3 pb-2 border-bottom"><i class="fa-solid fa-user-graduate me-2"></i>Student &amp; class</h2>
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Student <span class="text-danger">*</span></label>
                        <select class="form-select form-select-lg" name="student_id" required>
                            <option value="">Choose a student</option>
                            <?php foreach ($students as $s): ?>
                                <option value="<?= (int) ($s['id'] ?? 0) ?>" <?= (int) ($old['student_id'] ?? 0) === (int) ($s['id'] ?? 0) ? 'selected' : '' ?>>
                                    <?= h((string) ($s['name'] ?? '')) ?> — <?= h((string) ($s['email'] ?? '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Student email</label>
                        <input type="text" class="form-control form-control-lg bg-light" value="Filled automatically from profile" disabled readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Teacher <span class="text-danger">*</span></label>
                        <?php if (Auth::isAdmin()): ?>
                            <select class="form-select form-select-lg" name="teacher_id" required>
                                <option value="">Choose a teacher</option>
                                <?php foreach ($teachers as $t): ?>
                                    <option value="<?= (int) ($t['id'] ?? 0) ?>" <?= (int) ($old['teacher_id'] ?? 0) === (int) ($t['id'] ?? 0) ? 'selected' : '' ?>>
                                        <?= h((string) ($t['name'] ?? '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <input type="text" class="form-control form-control-lg bg-light" value="<?= h((string) (Auth::user()['name'] ?? 'Teacher')) ?>" disabled readonly>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="subject">Subject <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-lg" id="subject" name="subject" required
                               value="<?= h((string) ($old['subject'] ?? '')) ?>" placeholder="e.g. Mathematics">
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body p-4">
                <h2 class="h6 text-primary mb-3 pb-2 border-bottom"><i class="fa-solid fa-chart-line me-2"></i>Performance &amp; learning habits</h2>
                <?php $radioField('overall_performance', 'Overall Academic Performance'); ?>
                <?php $radioField('concept_understanding', 'Level of Concept Understanding'); ?>
                <?php $radioField('application_ability', 'Ability to Apply Concepts and Knowledge Retention'); ?>
                <?php $radioField('homework_completion', 'Homework Completion'); ?>
                <?php $radioField('attention_level', 'Attention During Class'); ?>
                <?php $radioField('participation_level', 'Participation Level'); ?>
                <?php $radioField('behaviour', 'Behaviour & Discipline'); ?>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body p-4">
                <h2 class="h6 text-primary mb-3 pb-2 border-bottom"><i class="fa-solid fa-pen-to-square me-2"></i>Written feedback</h2>
                <div class="mb-4">
                    <label class="form-label fw-semibold">Subjects Addressed <span class="text-danger">*</span></label>
                    <textarea class="form-control" name="subjects_addressed" rows="3" required placeholder="Topics covered in this report period"><?= h((string) ($old['subjects_addressed'] ?? '')) ?></textarea>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold">Future Focus <span class="text-danger">*</span></label>
                    <textarea class="form-control" name="future_focus" rows="3" required><?= h((string) ($old['future_focus'] ?? '')) ?></textarea>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold">Recommended Areas for Focus <span class="text-danger">*</span></label>
                    <textarea class="form-control" name="recommended_focus" rows="3" required><?= h((string) ($old['recommended_focus'] ?? '')) ?></textarea>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold">Suggested Study Strategies <span class="text-danger">*</span></label>
                    <textarea class="form-control" name="study_strategies" rows="3" required><?= h((string) ($old['study_strategies'] ?? '')) ?></textarea>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold">Additional Support Required <span class="text-danger">*</span></label>
                    <textarea class="form-control" name="additional_support" rows="3" required><?= h((string) ($old['additional_support'] ?? '')) ?></textarea>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold">Overall Feedback <span class="text-danger">*</span></label>
                    <textarea class="form-control" name="overall_feedback" rows="4" required><?= h((string) ($old['overall_feedback'] ?? '')) ?></textarea>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="report_date">Report Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control form-control-lg" id="report_date" name="report_date" required
                               value="<?= h((string) ($old['report_date'] ?? date('Y-m-d'))) ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center pt-2 pb-5">
            <button type="submit" class="btn btn-primary btn-lg rounded-pill px-4 shadow-sm">
                <i class="fa-solid fa-paper-plane me-2"></i>Save, generate PDF &amp; email parent
            </button>
            <a href="<?= h($base . (Auth::isAdmin() ? '/admin/reports' : '/teacher/reports')) ?>" class="btn btn-link text-muted">Back to list</a>
        </div>
    </form>
</div>
