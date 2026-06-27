<?php

declare(strict_types=1);

use function htmlspecialchars as h;

/** @var string $prefix optional id prefix e.g. cal_ */
$prefix = $prefix ?? '';
$old = $old ?? [];

$rule = (string) ($old['recurrence_rule'] ?? 'none');
$endMode = (string) ($old['recurrence_end_mode'] ?? 'until');
$weeklyInterval = max(1, min(12, (int) ($old['recurrence_weekly_interval'] ?? 1)));
$weeklyDaysOld = $old['recurrence_weekly_days'] ?? [];
if (!is_array($weeklyDaysOld)) {
    $weeklyDaysOld = [$weeklyDaysOld];
}
$weeklyDaysOld = array_map('intval', $weeklyDaysOld);
$monthlyPattern = (string) ($old['recurrence_monthly_pattern'] ?? 'day');

$dayLabels = [
    1 => 'Monday',
    2 => 'Tuesday',
    3 => 'Wednesday',
    4 => 'Thursday',
    5 => 'Friday',
    6 => 'Saturday',
    7 => 'Sunday',
];

$monthlyOptions = [
    'day' => 'Monthly on day of month',
    'first_monday' => 'First Monday',
    'second_tuesday' => 'Second Tuesday',
    'third_wednesday' => 'Third Wednesday',
    'fourth_thursday' => 'Fourth Thursday',
    'last_friday' => 'Last Friday',
    'last_sunday' => 'Last Sunday',
];

?>
<div class="col-12 recurrence-panel border rounded p-3 bg-light-subtle">
    <label class="form-label fw-semibold mb-2" for="<?= h($prefix) ?>recurrence_rule">Repeat</label>
    <select name="recurrence_rule" id="<?= h($prefix) ?>recurrence_rule" class="form-select form-select-sm mb-2 recurrence-rule-select">
        <?php
        $rules = [
            'none' => 'Does not repeat',
            'daily' => 'Daily',
            'weekdays' => 'Every weekday (Mon–Fri)',
            'weekly' => 'Weekly',
            'monthly' => 'Monthly',
            'custom' => 'Custom',
        ];
        foreach ($rules as $val => $label):
        ?>
            <option value="<?= h($val) ?>" <?= $rule === $val ? 'selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
    </select>

    <div class="form-text small mb-2 recurrence-hint <?= $rule === 'none' ? 'd-none' : '' ?>">
        Set the class <strong>end date</strong> to the last day of the series, or choose end conditions below.
    </div>

    <div class="recurrence-options <?= $rule === 'none' ? 'd-none' : '' ?>">
        <div class="recurrence-weekly-panel mb-3 <?= in_array($rule, ['weekly', 'custom'], true) ? '' : 'd-none' ?>">
            <label class="form-label form-label-sm">Repeat every</label>
            <div class="input-group input-group-sm mb-2" style="max-width: 220px;">
                <select name="recurrence_weekly_interval" id="<?= h($prefix) ?>recurrence_weekly_interval" class="form-select">
                    <?php for ($w = 1; $w <= 12; $w++): ?>
                        <option value="<?= $w ?>" <?= $weeklyInterval === $w ? 'selected' : '' ?>><?= $w ?> week<?= $w > 1 ? 's' : '' ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <label class="form-label form-label-sm">On days</label>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($dayLabels as $num => $label): ?>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="recurrence_weekly_days[]"
                               id="<?= h($prefix) ?>recurrence_day_<?= $num ?>" value="<?= $num ?>"
                            <?= in_array($num, $weeklyDaysOld, true) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="<?= h($prefix) ?>recurrence_day_<?= $num ?>"><?= h($label) ?></label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="recurrence-monthly-panel mb-3 <?= $rule === 'monthly' ? '' : 'd-none' ?>">
            <label class="form-label form-label-sm" for="<?= h($prefix) ?>recurrence_monthly_pattern">Monthly pattern</label>
            <select name="recurrence_monthly_pattern" id="<?= h($prefix) ?>recurrence_monthly_pattern" class="form-select form-select-sm">
                <?php foreach ($monthlyOptions as $val => $label): ?>
                    <option value="<?= h($val) ?>" <?= $monthlyPattern === $val ? 'selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-2">
            <label class="form-label form-label-sm">Ends</label>
            <div class="form-check">
                <input class="form-check-input recurrence-end-mode" type="radio" name="recurrence_end_mode"
                       id="<?= h($prefix) ?>recurrence_never_mode" value="never"
                    <?= $endMode === 'never' ? 'checked' : '' ?>>
                <label class="form-check-label" for="<?= h($prefix) ?>recurrence_never_mode">Never (max 52 sessions)</label>
            </div>
            <div class="form-check mt-1">
                <input class="form-check-input recurrence-end-mode" type="radio" name="recurrence_end_mode"
                       id="<?= h($prefix) ?>recurrence_until_mode" value="until"
                    <?= $endMode === 'until' || ($endMode !== 'never' && $endMode !== 'count') ? 'checked' : '' ?>>
                <label class="form-check-label" for="<?= h($prefix) ?>recurrence_until_mode">On date</label>
            </div>
            <input type="date" name="recurrence_until" id="<?= h($prefix) ?>recurrence_until"
                   class="form-control form-control-sm mt-1 recurrence-until-input"
                   value="<?= h((string) ($old['recurrence_until'] ?? '')) ?>">
            <div class="form-check mt-2">
                <input class="form-check-input recurrence-end-mode" type="radio" name="recurrence_end_mode"
                       id="<?= h($prefix) ?>recurrence_count_mode" value="count"
                    <?= $endMode === 'count' ? 'checked' : '' ?>>
                <label class="form-check-label" for="<?= h($prefix) ?>recurrence_count_mode">After</label>
            </div>
            <div class="input-group input-group-sm mt-1">
                <input type="number" min="2" max="52"
                       name="recurrence_count" id="<?= h($prefix) ?>recurrence_count" class="form-control"
                       value="<?= h((string) ($old['recurrence_count'] ?? '4')) ?>">
                <span class="input-group-text">occurrences</span>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var sel = document.getElementById(<?= json_encode($prefix . 'recurrence_rule') ?>);
    if (!sel) return;

    var panel = sel.closest('.recurrence-panel');
    if (!panel) return;

    var opts = panel.querySelector('.recurrence-options');
    var hint = panel.querySelector('.recurrence-hint');
    var weeklyPanel = panel.querySelector('.recurrence-weekly-panel');
    var monthlyPanel = panel.querySelector('.recurrence-monthly-panel');
    var untilInput = panel.querySelector('.recurrence-until-input');
    var endModeInputs = panel.querySelectorAll('.recurrence-end-mode');

    function findStartEndInputs() {
        var form = sel.closest('form');
        if (!form) return { start: null, end: null };
        return {
            start: form.querySelector('[name="start_datetime"]'),
            end: form.querySelector('[name="end_datetime"]'),
        };
    }

    function selectedEndMode() {
        var checked = panel.querySelector('.recurrence-end-mode:checked');
        return checked ? checked.value : 'until';
    }

    function syncUntilFromEndDate() {
        if (!untilInput || sel.value === 'none' || sel.value === '') return;
        if (selectedEndMode() !== 'until') return;
        var fields = findStartEndInputs();
        if (!fields.start || !fields.end || !fields.end.value) return;
        var endVal = String(fields.end.value).slice(0, 10);
        var startVal = String(fields.start.value).slice(0, 10);
        if (endVal && startVal && endVal > startVal && !untilInput.value) {
            untilInput.value = endVal;
        }
    }

    function syncEndInputs() {
        var mode = selectedEndMode();
        if (untilInput) {
            untilInput.disabled = mode !== 'until';
            untilInput.closest('.form-control, .input-group')?.classList?.toggle('opacity-50', mode !== 'until');
        }
        var countInput = panel.querySelector('[name="recurrence_count"]');
        if (countInput) {
            countInput.disabled = mode !== 'count';
        }
    }

    function sync() {
        var active = sel.value !== 'none' && sel.value !== '';
        if (opts) opts.classList.toggle('d-none', !active);
        if (hint) hint.classList.toggle('d-none', !active);
        if (weeklyPanel) {
            weeklyPanel.classList.toggle('d-none', !active || (sel.value !== 'weekly' && sel.value !== 'custom'));
        }
        if (monthlyPanel) {
            monthlyPanel.classList.toggle('d-none', !active || sel.value !== 'monthly');
        }
        if (active) syncUntilFromEndDate();
        syncEndInputs();
    }

    sel.addEventListener('change', sync);
    endModeInputs.forEach(function (input) {
        input.addEventListener('change', function () {
            syncEndInputs();
            syncUntilFromEndDate();
        });
    });

    var fields = findStartEndInputs();
    if (fields.end) fields.end.addEventListener('change', syncUntilFromEndDate);
    if (fields.start) fields.start.addEventListener('change', syncUntilFromEndDate);

    sync();
})();
</script>
