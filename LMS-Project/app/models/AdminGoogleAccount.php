<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Database.php';
require_once dirname(__DIR__) . '/lib/Crypto.php';

/**
 * Model for the centralized Admin Google Workspace account.
 * One row stores the OAuth tokens used for all Google Calendar / Meet / Drive operations.
 */
class AdminGoogleAccount
{
    /**
     * @return array{id:int,google_email:?string,access_token:string,refresh_token:string,token_expiry:?string,status:string,connected_at:?string}|null
     */
    public static function getCredentials(): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM admin_google_account ORDER BY id ASC LIMIT 1');
        $stmt->execute();
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'google_email' => $row['google_email'] ?? null,
            'access_token' => self::decryptNullable((string) ($row['access_token'] ?? '')),
            'refresh_token' => self::decryptNullable((string) ($row['refresh_token'] ?? '')),
            'token_expiry' => $row['token_expiry'] ?? null,
            'status' => (string) ($row['status'] ?? 'disconnected'),
            'connected_at' => $row['connected_at'] ?? null,
        ];
    }

    /**
     * Insert or update the single admin Google account row.
     */
    public static function upsertConnection(
        ?string $email,
        string $accessToken,
        ?string $refreshToken,
        ?\DateTimeImmutable $tokenExpiry,
        string $status = 'active'
    ): void {
        $pdo = Database::connection();

        $encAccess = self::encryptNullable($accessToken);
        $encRefresh = $refreshToken !== null && $refreshToken !== ''
            ? self::encryptNullable($refreshToken)
            : null;
        $expiryStr = $tokenExpiry !== null
            ? $tokenExpiry->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s')
            : null;

        $existing = self::getCredentials();
        if ($existing !== null) {
            // Update existing row
            $sql = 'UPDATE admin_google_account SET
                        google_email = :email,
                        access_token = :access_token,
                        status = :status,
                        token_expiry = :token_expiry,
                        updated_at = NOW()';
            $params = [
                'email' => $email,
                'access_token' => $encAccess,
                'status' => $status,
                'token_expiry' => $expiryStr,
            ];

            if ($encRefresh !== null) {
                $sql .= ', refresh_token = :refresh_token';
                $params['refresh_token'] = $encRefresh;
            }

            if ($status === 'active' && ($existing['status'] ?? '') !== 'active') {
                $sql .= ', connected_at = NOW()';
            }

            $sql .= ' WHERE id = :id';
            $params['id'] = $existing['id'];

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        } else {
            // Insert new row
            $stmt = $pdo->prepare(
                'INSERT INTO admin_google_account (google_email, access_token, refresh_token, token_expiry, status, connected_at)
                 VALUES (:email, :access_token, :refresh_token, :token_expiry, :status, NOW())'
            );
            $stmt->execute([
                'email' => $email,
                'access_token' => $encAccess,
                'refresh_token' => $encRefresh,
                'token_expiry' => $expiryStr,
                'status' => $status,
            ]);
        }
    }

    /**
     * Disconnect the admin Google account.
     */
    public static function disconnect(): void
    {
        $pdo = Database::connection();
        $pdo->prepare(
            'UPDATE admin_google_account SET status = "disconnected", access_token = NULL, refresh_token = NULL, token_expiry = NULL, updated_at = NOW()'
        )->execute();
    }

    /**
     * Check if the admin Google account is connected and active.
     */
    public static function isConnected(): bool
    {
        $creds = self::getCredentials();
        return $creds !== null
            && ($creds['status'] ?? '') === 'active'
            && ($creds['refresh_token'] ?? '') !== '';
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
