<?php

use function htmlspecialchars as h;

$errors = $errors ?? [];
$old = $old ?? [];

?>

<div class="row justify-content-center">
    <div class="col-12 col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="h4 mb-3">Schedule Class</h1>
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger small">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= h($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <form method="post"
                      action="<?= h((defined('BASE_PATH') ? BASE_PATH : '') . '/classes') ?>"
                      data-loader-title="Scheduling class..."
                      data-loader-text="Creating the Google Meet event, saving class timings, and notifying participants.">
                    <?php if (!empty($classTypes)): ?>
                        <div class="mb-3">
                            <label class="form-label" for="class_master_id">Class type (optional)</label>
                            <select id="class_master_id" name="class_master_id" class="form-select">
                                <option value="">- Custom title -</option>
                                <?php foreach ($classTypes as $ct): ?>
                                    <option value="<?= (int) $ct['id'] ?>"
                                        <?= isset($old['class_master_id']) && (int)$old['class_master_id'] === (int)$ct['id'] ? 'selected' : '' ?>>
                                        <?= h($ct['class_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Selecting a type can prefill title/description from catalog.</div>
                        </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label" for="title">Title</label>
                        <input type="text" id="title" name="title" class="form-control"
                               value="<?= h($old['title'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="3"><?= h($old['description'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="teacher_id">Teacher</label>
                        <select id="teacher_id" name="teacher_id" class="form-select" required>
                            <option value="">Select teacher</option>
                            <?php foreach ($teachers as $t): ?>
                                <option value="<?= (int) $t['id'] ?>"
                                    <?= isset($old['teacher_id']) && (int)$old['teacher_id'] === (int)$t['id'] ? 'selected' : '' ?>>
                                    <?= h($t['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">The selected teacher must have a connected Google account (Workspace or Gmail) so Calendar can create Meet links.</div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="start_datetime">Start (local)</label>
                            <input type="datetime-local" id="start_datetime" name="start_datetime" class="form-control"
                                   value="<?= h($old['start_datetime'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="end_datetime">End (local)</label>
                            <input type="datetime-local" id="end_datetime" name="end_datetime" class="form-control"
                                   value="<?= h($old['end_datetime'] ?? '') ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="payout_amount">Teacher payout for this class (INR)</label>
                        <input type="number" step="0.01" min="0" id="payout_amount" name="payout_amount" class="form-control"
                               value="<?= h($old['payout_amount'] ?? '0') ?>">
                        <div class="form-text">Stored per class; used when the class is marked completed.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="student_fee">Student fee for this class (INR)</label>
                        <input type="number" step="0.01" min="0" id="student_fee" name="student_fee" class="form-control"
                               value="<?= h($old['student_fee'] ?? '0') ?>">
                        <div class="form-text">This creates pending payment entries for each enrolled student.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="timezone">Timezone</label>
                        <?php $timezoneValue = (string) ($old['timezone'] ?? APP_TIMEZONE); ?>
                        <select id="timezone" name="timezone" class="form-select">
                            <?php foreach (supportedSchedulingTimezones() as $tz): ?>
                                <option value="<?= h($tz['value']) ?>" <?= $timezoneValue === $tz['value'] ? 'selected' : '' ?>><?= h($tz['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Selected local time is converted to UTC before saving, and the Meet is created using the assigned teacher's Workspace calendar.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="student_ids">Students</label>
                        <select id="student_ids" name="student_ids[]" class="form-select" multiple size="5">
                            <?php foreach ($students as $s): ?>
                                <option value="<?= (int) $s['id'] ?>"
                                    <?= isset($old['student_ids']) && in_array((string)$s['id'], (array)$old['student_ids'], true) ? 'selected' : '' ?>>
                                    <?= h($s['name'] . ' (' . $s['email'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Hold Ctrl (Cmd on Mac) to select multiple.</div>
                    </div>
                    <button type="submit" class="btn btn-primary">Create Class</button>
                </form>
            </div>
        </div>
    </div>
</div>
