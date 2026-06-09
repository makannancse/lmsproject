<?php

use function htmlspecialchars as h;

$base = defined('BASE_PATH') ? BASE_PATH : '';
$user = $user ?? [];
$role = $role ?? 'student';
$errors = $errors ?? [];
$old = $old ?? [];
$teachers = $teachers ?? [];
$timezoneOptions = $timezoneOptions ?? [];

$val = static function (string $key, $default = '') use ($old, $user): string {
    if (array_key_exists($key, $old)) {
        return (string) $old[$key];
    }

    return (string) ($user[$key] ?? $default);
};

$userId = (int) ($editUserId ?? $user['id'] ?? 0);
$isStudent = $role === 'student';
?>

<div class="mb-3">
    <a href="<?= h($base . '/admin/users?role=' . urlencode($role)) ?>" class="btn btn-sm btn-outline-secondary">&larr; Back to users</a>
</div>

<h1 class="h4 mb-3">Edit <?= h(ucfirst($role)) ?></h1>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0 small">
            <?php foreach ($errors as $err): ?>
                <li><?= h($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="post" action="<?= h($base . '/admin/users/update') ?>" class="row g-3 no-app-loader">
            <input type="hidden" name="user_id" value="<?= (int) $userId ?>">

            <div class="col-md-6">
                <label class="form-label" for="first_name">First name</label>
                <input type="text" name="first_name" id="first_name" class="form-control form-control-sm" required
                       value="<?= h($val('first_name', (string) ($firstName ?? ''))) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="last_name">Last name</label>
                <input type="text" name="last_name" id="last_name" class="form-control form-control-sm"
                       value="<?= h($val('last_name', $lastName ?? '')) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="email">Email</label>
                <input type="email" name="email" id="email" class="form-control form-control-sm" required
                       value="<?= h($val('email', $user['email'] ?? '')) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="phone">Phone</label>
                <input type="text" name="phone" id="phone" class="form-control form-control-sm"
                       value="<?= h($val('phone', $user['phone'] ?? '')) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="timezone">Timezone</label>
                <select name="timezone" id="timezone" class="form-select form-select-sm">
                    <?php foreach ($timezoneOptions as $option): ?>
                        <option value="<?= h($option['value']) ?>"
                            <?= ($val('timezone', $user['timezone'] ?? APP_TIMEZONE) === $option['value']) ? 'selected' : '' ?>>
                            <?= h($option['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="status">Status</label>
                <select name="status" id="status" class="form-select form-select-sm">
                    <option value="active" <?= $val('status', $user['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $val('status', $user['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>

            <?php if ($isStudent): ?>
                <div class="col-md-6">
                    <label class="form-label" for="parent_email">Parent email</label>
                    <input type="email" name="parent_email" id="parent_email" class="form-control form-control-sm"
                           value="<?= h($val('parent_email', $user['parent_email'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="subject">Subject</label>
                    <input type="text" name="subject" id="subject" class="form-control form-control-sm"
                           value="<?= h($val('subject', $user['subject'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="assigned_teacher_id">Assigned teacher</label>
                    <select name="assigned_teacher_id" id="assigned_teacher_id" class="form-select form-select-sm">
                        <option value="0">— None —</option>
                        <?php
                        $assignedId = (int) $val('assigned_teacher_id', (string) ($user['assigned_teacher_id'] ?? 0));
                        foreach ($teachers as $t):
                            ?>
                            <option value="<?= (int) $t['id'] ?>"
                                <?= $assignedId === (int) $t['id'] ? 'selected' : '' ?>>
                                <?= h($t['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="default_payment_amount">Default payment amount (INR)</label>
                    <input type="number" step="1" min="0" name="default_payment_amount" id="default_payment_amount"
                           class="form-control form-control-sm"
                           value="<?= h($val('default_payment_amount', (string) ($user['default_payment_amount'] ?? '0'))) ?>">
                </div>
            <?php endif; ?>

            <div class="col-12">
                <button type="submit" class="btn btn-primary btn-sm">Save changes</button>
            </div>
        </form>
    </div>
</div>
