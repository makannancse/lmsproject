<?php

use function htmlspecialchars as h;

$base = appWebPath();
$students = $students ?? [];
$teachers = $teachers ?? [];
$errors = $errors ?? [];
$old = $old ?? [];
$isAdmin = !empty($isAdmin);
$studentListTeacherId = (int) ($studentListTeacherId ?? 0);
$teacherSelectId = (int) ($old['teacher_id'] ?? 0) > 0 ? (int) $old['teacher_id'] : $studentListTeacherId;
$oldStudentIds = array_map('intval', (array) ($old['student_ids'] ?? []));
$dueTimezoneValue = normalizeTimezone((string) ($old['due_timezone'] ?? (Auth::user()['timezone'] ?? APP_TIMEZONE)), APP_TIMEZONE);
?>

<div class="row justify-content-center">
    <div class="col-12 col-lg-9">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="h4 mb-2">Assign Homework</h1>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger small">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= h((string) $error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post"
                      action="<?= h(path('teacher/homework')) ?>"
                      enctype="multipart/form-data"
                      data-loader-title="Assigning homework..."
                      data-loader-text="Saving homework details, uploading attachments, and notifying students.">
                    <?php if ($isAdmin): ?>
                        <div class="mb-3">
                            <label class="form-label" for="teacher_id">Teacher</label>
                            <select class="form-select" id="teacher_id" name="teacher_id" required
                                    data-homework-create-base="<?= h(rtrim(path(), '/')) ?>"
                                    onchange="var basePath=this.dataset.homeworkCreateBase; if(basePath){ window.location.href=basePath+'/teacher/homework/create?teacher_id='+encodeURIComponent(this.value); }">
                                <option value="">Select teacher</option>
                                <?php foreach ($teachers as $teacher): ?>
                                    <option value="<?= (int) ($teacher['id'] ?? 0) ?>" <?= $teacherSelectId === (int) ($teacher['id'] ?? 0) ? 'selected' : '' ?>><?= h((string) ($teacher['name'] ?? '')) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Only students linked to this teacher in <code>teacher_students</code> can receive homework.</div>
                        </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label" for="title">Title</label>
                        <input type="text" class="form-control" name="title" id="title" required value="<?= h((string) ($old['title'] ?? '')) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="description">Description</label>
                        <textarea class="form-control" name="description" id="description" rows="4"><?= h((string) ($old['description'] ?? '')) ?></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-7 mb-3">
                            <label class="form-label" for="due_date">Due date (optional)</label>
                            <input type="datetime-local" class="form-control" name="due_date" id="due_date" value="<?= h((string) ($old['due_date'] ?? '')) ?>">
                        </div>
                        <div class="col-md-5 mb-3">
                            <label class="form-label" for="due_timezone">Due timezone</label>
                            <select class="form-select" name="due_timezone" id="due_timezone">
                                <?php foreach (supportedSchedulingTimezones() as $timezone): ?>
                                    <option value="<?= h($timezone['value']) ?>" <?= $dueTimezoneValue === $timezone['value'] ? 'selected' : '' ?>><?= h($timezone['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="student_ids">Assign to students</label>
                        <?php if ($students === []): ?>
                            <div class="alert alert-warning small mb-0">
                                <?php if ($isAdmin): ?>
                                    No students are linked to this teacher.
                                    <a class="alert-link fw-semibold" href="<?= h($base . '/admin/teacher-students?teacher_id=' . $studentListTeacherId) ?>">Open Teacher-Students mapping</a>
                                <?php else: ?>
                                    You have no students assigned to you yet. Ask an admin to link students in <code>teacher_students</code>.
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <select id="student_ids" name="student_ids[]" class="form-select" multiple size="8" required>
                                <?php foreach ($students as $student): ?>
                                    <option value="<?= (int) ($student['id'] ?? 0) ?>" <?= in_array((int) ($student['id'] ?? 0), $oldStudentIds, true) ? 'selected' : '' ?>>
                                        <?= h((string) ($student['name'] ?? '')) ?> (<?= h((string) ($student['email'] ?? '')) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="attachments">Attachments (multiple)</label>
                        <input type="file" class="form-control" name="attachments[]" id="attachments" multiple>
                        <div class="form-text">Allowed: PDF, DOC, DOCX, TXT, JPG, PNG (max 50MB each)</div>
                    </div>

                    <button type="submit" class="btn btn-primary" <?= $students === [] ? 'disabled' : '' ?>>Save and notify students</button>
                    <a href="<?= h($base . '/teacher/homework') ?>" class="btn btn-link">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>
