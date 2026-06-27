<?php

declare(strict_types=1);

use function htmlspecialchars as h;

/** @var string $fieldId */
/** @var string $fieldName */
/** @var string $selectedValue */
/** @var string $label */
/** @var bool $required */

$fieldId = $fieldId ?? 'timezone';
$fieldName = $fieldName ?? 'timezone';
$selectedValue = $selectedValue ?? APP_TIMEZONE;
$label = $label ?? 'Timezone';
$required = $required ?? false;
$options = supportedSchedulingTimezones();
?>
<div class="mb-3">
    <label class="form-label" for="<?= h($fieldId) ?>"><?= h($label) ?></label>
    <select class="form-select timezone-select" id="<?= h($fieldId) ?>" name="<?= h($fieldName) ?>" <?= $required ? 'required' : '' ?>>
        <?php foreach ($options as $option): ?>
            <option value="<?= h($option['value']) ?>" <?= $selectedValue === $option['value'] ? 'selected' : '' ?>>
                <?= h($option['label']) ?>
            </option>
        <?php endforeach; ?>
        <?php
        $normalized = normalizeTimezone($selectedValue, APP_TIMEZONE);
        $inList = false;
        foreach ($options as $option) {
            if ($option['value'] === $normalized) {
                $inList = true;
                break;
            }
        }
        if (!$inList && $normalized !== ''):
        ?>
            <option value="<?= h($normalized) ?>" selected><?= h('Current (' . $normalized . ')') ?></option>
        <?php endif; ?>
    </select>
</div>
