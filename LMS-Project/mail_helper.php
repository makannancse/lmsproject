<?php

declare(strict_types=1);

require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/lib/Mailer.php';
require_once __DIR__ . '/app/controllers/ClassController.php';
require_once __DIR__ . '/app/lib/PayoutService.php';

function sendMailToRecipient(string $to, string $subject, string $htmlBody): array
{
    return Mailer::send($to, $subject, $htmlBody, true);
}

function sendClassNotification(int $classId): array
{
    return ClassController::sendClassNotification($classId);
}

/** @return array{pending: float, paid: float, total: float, completed_classes: int} */
function calculateTeacherPayout(int $teacherId): array
{
    return PayoutService::calculateTeacherPayout($teacherId);
}

