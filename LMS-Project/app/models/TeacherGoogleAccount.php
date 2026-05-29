<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Database.php';
require_once dirname(__DIR__) . '/lib/Crypto.php';
require_once dirname(__DIR__) . '/lib/GoogleAccountType.php';

class TeacherGoogleAccount
{
    public static function findByTeacherId(int $teacherId): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT *
             FROM teacher_google_accounts
             WHERE teacher_id = :teacher_id
             LIMIT 1'
        );
        $stmt->execute(['teacher_id' => $teacherId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @return array{
     *   id:int,
     *   teacher_id:int,
     *   google_email:?string,
     *   google_person_resource_name:?string,
     *   google_person_id:?string,
     *   access_token:string,
     *   refresh_token:string,
     *   token_expiry:?string,
     *   connected_at:?string,
     *   status:string
     * }|null
     */
    public static function getCredentialsForTeacher(int $teacherId): ?array
    {
        $row = self::findByTeacherId($teacherId);
        if ($row === null) {
            return null;
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'teacher_id' => (int) ($row['teacher_id'] ?? 0),
            'google_email' => isset($row['google_email']) ? (string) $row['google_email'] : null,
            'google_person_resource_name' => isset($row['google_person_resource_name']) && $row['google_person_resource_name'] !== null
                ? (string) $row['google_person_resource_name']
                : null,
            'google_person_id' => isset($row['google_person_id']) && $row['google_person_id'] !== null
                ? (string) $row['google_person_id']
                : null,
            'account_type' => self::effectiveAccountType($row),
            'recording_supported' => self::recordingSupportedFromAccountRow($row) ? 1 : 0,
            'access_token' => self::decryptNullable($row['access_token'] ?? null),
            'refresh_token' => self::decryptNullable($row['refresh_token'] ?? null),
            'token_expiry' => isset($row['token_expiry']) && $row['token_expiry'] !== null ? (string) $row['token_expiry'] : null,
            'connected_at' => isset($row['connected_at']) && $row['connected_at'] !== null ? (string) $row['connected_at'] : null,
            'status' => (string) ($row['status'] ?? 'disconnected'),
        ];
    }

    /**
     * Whether Drive-backed recording sync / admin recording workflow applies.
     */
    public static function recordingSupportedFromAccountRow(?array $row): bool
    {
        if ($row === null) {
            return false;
        }
        if (array_key_exists('recording_supported', $row) && $row['recording_supported'] !== null) {
            return (int) $row['recording_supported'] === 1;
        }

        return GoogleAccountType::isRecordingSupported(isset($row['google_email']) ? (string) $row['google_email'] : null);
    }

    public static function effectiveAccountType(array $row): string
    {
        if (!empty($row['account_type']) && in_array((string) $row['account_type'], ['workspace', 'personal'], true)) {
            return (string) $row['account_type'];
        }

        $email = isset($row['google_email']) ? (string) $row['google_email'] : '';

        return GoogleAccountType::profileFromEmail($email)['account_type'];
    }

    public static function upsertConnection(
        int $teacherId,
        ?string $googleEmail,
        ?string $accessToken,
        ?string $refreshToken,
        ?DateTimeImmutable $tokenExpiry,
        string $status = 'active',
        ?string $googlePersonResourceName = null,
        ?string $googlePersonId = null
    ): void {
        $existing = self::findByTeacherId($teacherId);
        $encryptedAccessToken = self::encryptNullable($accessToken);
        $encryptedRefreshToken = self::encryptNullable($refreshToken);
        $emailForProfile = trim((string) ($googleEmail ?? ''));
        if ($emailForProfile === '' && $existing !== null && !empty($existing['google_email'])) {
            $emailForProfile = (string) $existing['google_email'];
        }
        $profile = GoogleAccountType::profileFromEmail($emailForProfile !== '' ? $emailForProfile : null);

        if ($existing === null) {
            $pdo = Database::connection();
            $stmt = $pdo->prepare(
                'INSERT INTO teacher_google_accounts
                    (teacher_id, google_email, google_person_resource_name, google_person_id, account_type, recording_supported, access_token, refresh_token, token_expiry, connected_at, status, created_at, updated_at)
                 VALUES
                    (:teacher_id, :google_email, :google_person_resource_name, :google_person_id, :account_type, :recording_supported, :access_token, :refresh_token, :token_expiry, NOW(), :status, NOW(), NOW())'
            );
            $stmt->execute([
                'teacher_id' => $teacherId,
                'google_email' => $googleEmail,
                'google_person_resource_name' => $googlePersonResourceName,
                'google_person_id' => $googlePersonId,
                'account_type' => $profile['account_type'],
                'recording_supported' => $profile['recording_supported'] ? 1 : 0,
                'access_token' => $encryptedAccessToken,
                'refresh_token' => $encryptedRefreshToken,
                'token_expiry' => $tokenExpiry?->format('Y-m-d H:i:s'),
                'status' => $status,
            ]);
            return;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'UPDATE teacher_google_accounts
             SET google_email = :google_email,
                 google_person_resource_name = :google_person_resource_name,
                 google_person_id = :google_person_id,
                 account_type = :account_type,
                 recording_supported = :recording_supported,
                 access_token = :access_token,
                 refresh_token = :refresh_token,
                 token_expiry = :token_expiry,
                 connected_at = COALESCE(connected_at, NOW()),
                 status = :status,
                 updated_at = NOW()
             WHERE teacher_id = :teacher_id'
        );
        $stmt->execute([
            'teacher_id' => $teacherId,
            'google_email' => $googleEmail !== null && $googleEmail !== '' ? $googleEmail : ($existing['google_email'] ?? null),
            'google_person_resource_name' => $googlePersonResourceName !== null && $googlePersonResourceName !== ''
                ? $googlePersonResourceName
                : ($existing['google_person_resource_name'] ?? null),
            'google_person_id' => $googlePersonId !== null && $googlePersonId !== ''
                ? $googlePersonId
                : ($existing['google_person_id'] ?? null),
            'account_type' => $profile['account_type'],
            'recording_supported' => $profile['recording_supported'] ? 1 : 0,
            'access_token' => $encryptedAccessToken ?? ($existing['access_token'] ?? null),
            'refresh_token' => $encryptedRefreshToken ?? ($existing['refresh_token'] ?? null),
            'token_expiry' => $tokenExpiry?->format('Y-m-d H:i:s'),
            'status' => $status,
        ]);
    }

    public static function updateIdentity(int $teacherId, ?string $googlePersonResourceName, ?string $googlePersonId): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'UPDATE teacher_google_accounts
             SET google_person_resource_name = :google_person_resource_name,
                 google_person_id = :google_person_id,
                 updated_at = NOW()
             WHERE teacher_id = :teacher_id'
        );
        $stmt->execute([
            'teacher_id' => $teacherId,
            'google_person_resource_name' => $googlePersonResourceName,
            'google_person_id' => $googlePersonId,
        ]);
    }

    public static function disconnect(int $teacherId): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'UPDATE teacher_google_accounts
             SET access_token = NULL,
                 refresh_token = NULL,
                 token_expiry = NULL,
                 status = "disconnected",
                 updated_at = NOW()
             WHERE teacher_id = :teacher_id'
        );
        $stmt->execute(['teacher_id' => $teacherId]);
    }

    public static function allWithTeacherNames(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->query(
            'SELECT u.id AS teacher_id,
                    u.name AS teacher_name,
                    u.email AS teacher_email,
                    tga.google_email,
                    tga.google_person_resource_name,
                    tga.google_person_id,
                    tga.account_type,
                    tga.recording_supported,
                    tga.connected_at,
                    tga.token_expiry,
                    tga.status
             FROM users u
             LEFT JOIN teacher_google_accounts tga ON tga.teacher_id = u.id
             WHERE u.role = "teacher"
             ORDER BY u.name ASC'
        );

        return $stmt->fetchAll() ?: [];
    }

    private static function encryptNullable(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : null;
        if ($value === null || $value === '') {
            return null;
        }

        return Crypto::encrypt($value);
    }

    private static function decryptNullable($value): string
    {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            return '';
        }

        return Crypto::decrypt($value);
    }
}
