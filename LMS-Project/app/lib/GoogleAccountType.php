<?php

declare(strict_types=1);

/**
 * Classifies connected Google identities for Workspace vs consumer Gmail Meet usage.
 *
 * gmail.com / googlemail.com → personal (no Drive-backed organizational recording UX).
 */
class GoogleAccountType
{
    /** @var list<string> */
    private const PERSONAL_SUFFIXES = ['@gmail.com', '@googlemail.com'];

    /**
     * True if Meet cloud recording/Drive-sync workflow applies (Workspace/custom domain).
     */
    public static function isRecordingSupported(?string $email): bool
    {
        return self::profileFromEmail($email)['recording_supported'];
    }

    /**
     * @return array{account_type:string,recording_supported:bool}
     */
    public static function profileFromEmail(?string $email): array
    {
        $normalized = strtolower(trim((string) $email));
        if ($normalized === '' || !filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            return ['account_type' => 'workspace', 'recording_supported' => true];
        }

        foreach (self::PERSONAL_SUFFIXES as $suffix) {
            if (str_ends_with($normalized, $suffix)) {
                return ['account_type' => 'personal', 'recording_supported' => false];
            }
        }

        return ['account_type' => 'workspace', 'recording_supported' => true];
    }
}
