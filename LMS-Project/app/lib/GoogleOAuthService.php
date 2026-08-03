<?php

declare(strict_types=1);

use Google\Client as GoogleClient;
use Google\Service\Calendar as GoogleCalendar;
use Google\Service\Meet as GoogleMeet;
use Google\Service\PeopleService as GooglePeopleService;
use GuzzleHttp\Client as GuzzleClient;

require_once dirname(__DIR__) . '/models/SystemConfig.php';
require_once dirname(__DIR__) . '/models/TeacherGoogleAccount.php';
require_once dirname(__DIR__) . '/models/AdminGoogleAccount.php';
require_once dirname(__DIR__) . '/lib/GoogleAccountType.php';

class GoogleOAuthService
{
    private function workspaceDomain(): string
    {
        $envValue = trim((string) env('GOOGLE_WORKSPACE_DOMAIN', ''));
        if ($envValue !== '') {
            return strtolower($envValue);
        }

        return strtolower(trim((string) SystemConfig::get('google_workspace_domain', '')));
    }

    private function googleClientId(): string
    {
        $envValue = trim((string) env('GOOGLE_CLIENT_ID', ''));
        if ($envValue !== '') {
            return $envValue;
        }

        return trim((string) SystemConfig::get('google_client_id', ''));
    }

    private function googleClientSecret(): string
    {
        $envValue = trim((string) env('GOOGLE_CLIENT_SECRET', ''));
        if ($envValue !== '') {
            return $envValue;
        }

        return trim((string) SystemConfig::get('google_client_secret', ''));
    }

    /**
     * Scopes required to create Google Calendar events with Meet links.
     *
     * @return list<string>
     */
    public static function requiredScopes(): array
    {
        return [
            GoogleCalendar::CALENDAR,
            GoogleMeet::MEETINGS_SPACE_READONLY,
            GoogleMeet::MEETINGS_SPACE_CREATED,
            GoogleMeet::MEETINGS_SPACE_SETTINGS,
            'https://www.googleapis.com/auth/drive',
            GooglePeopleService::USERINFO_EMAIL,
            'openid',
            'email',
            'profile',
        ];
    }

    public function buildAuthUrl(int $teacherId): string
    {
        $this->prepareReconnect($teacherId);

        $client = $this->client();
        $state = base64_encode(json_encode([
            'teacher_id' => $teacherId,
            'nonce' => bin2hex(random_bytes(8)),
        ], JSON_UNESCAPED_SLASHES));
        $_SESSION['google_oauth_state'] = $state;

        $client->setState($state);
        $authUrl = $client->createAuthUrl();
        if (function_exists('logGoogleAuth')) {
            logGoogleAuth([
                'event' => 'oauth_auth_url_built',
                'teacher_id' => $teacherId,
                'redirect_uri' => $this->configuredRedirectUri(),
                'oauth_url' => $authUrl,
                'user_role' => (string) ($_SESSION['role'] ?? ''),
            ]);
        }

        return $authUrl;
    }

    public function buildAdminAuthUrl(): string
    {
        $this->prepareAdminReconnect();

        $client = $this->client();
        $state = base64_encode(json_encode([
            'admin_connect' => true,
            'nonce' => bin2hex(random_bytes(8)),
        ], JSON_UNESCAPED_SLASHES));
        $_SESSION['google_oauth_state'] = $state;

        $client->setState($state);
        $authUrl = $client->createAuthUrl();

        return $authUrl;
    }

    /**
     * Revoke any existing Google tokens so reconnect always issues fresh consent + refresh token.
     */
    public function prepareReconnect(int $teacherId): void
    {
        $account = TeacherGoogleAccount::getCredentialsForTeacher($teacherId);
        if ($account === null) {
            return;
        }

        $tokenToRevoke = trim((string) ($account['refresh_token'] ?? ''));
        if ($tokenToRevoke === '') {
            $tokenToRevoke = trim((string) ($account['access_token'] ?? ''));
        }

        if ($tokenToRevoke !== '') {
            try {
                $this->client()->revokeToken($tokenToRevoke);
            } catch (\Throwable $e) {
                // Non-fatal: token may already be invalid.
            }
        }

        TeacherGoogleAccount::disconnect($teacherId);
    }

    public function prepareAdminReconnect(): void
    {
        $account = AdminGoogleAccount::getCredentials();
        if ($account === null) {
            return;
        }

        $tokenToRevoke = trim((string) ($account['refresh_token'] ?? ''));
        if ($tokenToRevoke === '') {
            $tokenToRevoke = trim((string) ($account['access_token'] ?? ''));
        }

        if ($tokenToRevoke !== '') {
            try {
                $this->client()->revokeToken($tokenToRevoke);
            } catch (\Throwable $e) {
                // Non-fatal: token may already be invalid.
            }
        }

        AdminGoogleAccount::disconnect();
    }

    /**
     * @return array{teacher_id:int,email:?string,refresh_token_saved:bool}
     */
    public function handleCallback(string $code, string $state): array
    {
        $expected = (string) ($_SESSION['google_oauth_state'] ?? '');
        if ($expected === '' || !hash_equals($expected, $state)) {
            throw new RuntimeException('Invalid OAuth state.');
        }
        unset($_SESSION['google_oauth_state']);

        $stateData = json_decode((string) base64_decode($state, true), true);
        $teacherId = (int) ($stateData['teacher_id'] ?? 0);
        if ($teacherId <= 0) {
            throw new RuntimeException('Invalid teacher in OAuth state.');
        }

        $client = $this->client();
        $token = $client->fetchAccessTokenWithAuthCode($code);
        if (!is_array($token) || isset($token['error'])) {
            $err = is_array($token) ? ($token['error_description'] ?? $token['error'] ?? 'OAuth token exchange failed') : 'OAuth token exchange failed';
            throw new RuntimeException((string) $err);
        }

        $refresh = trim((string) ($token['refresh_token'] ?? ''));
        if ($refresh === '') {
            throw new RuntimeException(
                'Google did not return a refresh token. Disconnect the account, then connect again and approve all requested permissions (including Google Calendar).'
            );
        }

        self::assertCalendarScopeGranted($token);

        $email = null;
        $payload = null;
        try {
            $client->setAccessToken($token);
            $payload = $client->verifyIdToken();
            if (is_array($payload) && !empty($payload['email'])) {
                $email = (string) $payload['email'];
            }
        } catch (\Throwable $e) {
            // non-fatal
        }

        $googlePersonResourceName = null;
        $googlePersonId = null;
        $googleUserId = is_array($payload) && !empty($payload['sub']) ? trim((string) $payload['sub']) : null;
        try {
            $client->setAccessToken($token);
            $people = new GooglePeopleService($client);
            $person = $people->people->get('people/me', [
                'personFields' => 'emailAddresses',
            ]);
            $googlePersonResourceName = trim((string) $person->getResourceName());
            if ($googlePersonResourceName !== '' && str_starts_with($googlePersonResourceName, 'people/')) {
                $googlePersonId = substr($googlePersonResourceName, strlen('people/')) ?: null;
            }
        } catch (\Throwable $e) {
            if ($googleUserId !== null && $googleUserId !== '') {
                $googlePersonId = $googleUserId;
                $googlePersonResourceName = 'people/' . $googleUserId;
            }
        }

        $existing = TeacherGoogleAccount::getCredentialsForTeacher($teacherId);
        if (($email === null || trim($email) === '') && $existing !== null && !empty($existing['google_email'])) {
            $email = (string) $existing['google_email'];
        }
        $this->validateTeacherGoogleEmail($email);

        TeacherGoogleAccount::upsertConnection(
            $teacherId,
            $email,
            (string) ($token['access_token'] ?? ''),
            $refresh !== '' ? $refresh : null,
            $this->tokenExpiryFromPayload($token),
            'active',
            $googlePersonResourceName,
            $googlePersonId,
            $googleUserId
        );
        $profile = GoogleAccountType::profileFromEmail($email);
        SyncLog::write('google_account_type.log', [
            'message' => 'Teacher Google OAuth connected',
            'teacher_id' => $teacherId,
            'google_email' => $email,
            'google_person_resource_name' => $googlePersonResourceName,
            'google_person_id' => $googlePersonId,
            'google_user_id' => $googleUserId,
            'account_type' => $profile['account_type'],
            'recording_supported' => $profile['recording_supported'],
            'configured_workspace_domain' => $this->workspaceDomain(),
        ]);
        logMeetingHost([
            'event' => 'teacher_google_account_connected',
            'teacher_id' => $teacherId,
            'teacher_google_email' => $email,
            'google_person_resource_name' => $googlePersonResourceName,
            'google_person_id' => $googlePersonId,
            'google_user_id' => $googleUserId,
            'account_type' => $profile['account_type'],
            'recording_supported' => $profile['recording_supported'],
            'workspace_domain' => $this->workspaceDomain(),
        ]);

        if (function_exists('logGoogleAuth')) {
            logGoogleAuth([
                'event' => 'oauth_callback_success',
                'teacher_id' => $teacherId,
                'email' => $email,
                'refresh_token_saved' => true,
                'user_role' => (string) ($_SESSION['role'] ?? ''),
            ]);
        }

        return ['teacher_id' => $teacherId, 'email' => $email, 'refresh_token_saved' => true];
    }

    /**
     * @return array{email:?string,refresh_token_saved:bool}
     */
    public function handleAdminCallback(string $code, string $state): array
    {
        $expected = (string) ($_SESSION['google_oauth_state'] ?? '');
        if ($expected === '' || !hash_equals($expected, $state)) {
            throw new RuntimeException('Invalid OAuth state.');
        }
        unset($_SESSION['google_oauth_state']);

        $stateData = json_decode((string) base64_decode($state, true), true);
        if (empty($stateData['admin_connect'])) {
            throw new RuntimeException('Invalid admin connect state.');
        }

        $client = $this->client();
        $token = $client->fetchAccessTokenWithAuthCode($code);
        if (!is_array($token) || isset($token['error'])) {
            $err = is_array($token) ? ($token['error_description'] ?? $token['error'] ?? 'OAuth token exchange failed') : 'OAuth token exchange failed';
            throw new RuntimeException((string) $err);
        }

        $refresh = trim((string) ($token['refresh_token'] ?? ''));
        if ($refresh === '') {
            throw new RuntimeException(
                'Google did not return a refresh token. Disconnect the account, then connect again and approve all requested permissions.'
            );
        }

        self::assertCalendarScopeGranted($token);

        $email = null;
        try {
            $client->setAccessToken($token);
            $payload = $client->verifyIdToken();
            if (is_array($payload) && !empty($payload['email'])) {
                $email = (string) $payload['email'];
            }
        } catch (\Throwable $e) {
            // non-fatal
        }

        $existing = AdminGoogleAccount::getCredentials();
        if (($email === null || trim($email) === '') && $existing !== null && !empty($existing['google_email'])) {
            $email = (string) $existing['google_email'];
        }
        $this->validateTeacherGoogleEmail($email); // Reusing this validation to enforce Workspace if needed

        AdminGoogleAccount::upsertConnection(
            $email,
            (string) ($token['access_token'] ?? ''),
            $refresh !== '' ? $refresh : null,
            $this->tokenExpiryFromPayload($token),
            'active'
        );

        return ['email' => $email, 'refresh_token_saved' => true];
    }

    /**
     * @param array<string, mixed> $token
     */
    public static function assertCalendarScopeGranted(array $token): void
    {
        $granted = array_filter(explode(' ', trim((string) ($token['scope'] ?? ''))));
        if ($granted === []) {
            return;
        }

        $calendarScopes = [
            GoogleCalendar::CALENDAR,
            GoogleCalendar::CALENDAR_EVENTS,
            GoogleCalendar::CALENDAR_EVENTS_OWNED,
        ];

        foreach ($granted as $scope) {
            if (in_array($scope, $calendarScopes, true)) {
                return;
            }
        }

        throw new RuntimeException(
            'Google Calendar permission was not granted. In Google Cloud Console, add the '
            . 'Google Calendar API and the "calendar" scope to your OAuth consent screen, then '
            . 'disconnect and reconnect the teacher Google account, approving all permissions.'
        );
    }

    public static function scopeInsufficientMessage(): string
    {
        return 'This teacher\'s Google token is missing Calendar permission. Disconnect the Google account '
            . 'on the Admin dashboard, reconnect it, and approve all permissions (including Google Calendar). '
            . 'Also verify the Google Calendar API is enabled and the "calendar" scope is on your OAuth consent screen.';
    }

    public static function isScopeInsufficientError(\Throwable $e): bool
    {
        $lower = strtolower($e->getMessage());

        return str_contains($lower, 'insufficient authentication scopes')
            || str_contains($lower, 'insufficientpermission')
            || str_contains($lower, 'insufficient permission')
            || str_contains($lower, 'access_token_scope_insufficient');
    }

    /**
     * @return array{account_id:int,teacher_id:int,google_email:?string,google_person_resource_name:?string,google_person_id:?string,access_token:string,refresh_token:string,token_expiry:?string,status:string}
     */
    public function getTeacherAccount(int $teacherId): array
    {
        $account = TeacherGoogleAccount::getCredentialsForTeacher($teacherId);
        if ($account === null) {
            throw new RuntimeException('Teacher has not connected a Google account.');
        }
        if (($account['status'] ?? '') !== 'active') {
            throw new RuntimeException('Teacher Google account is not active. Please reconnect the Google account.');
        }
        if (($account['refresh_token'] ?? '') === '') {
            throw new RuntimeException('Teacher Google refresh token is missing. Reconnect the Google account.');
        }
        $this->validateTeacherGoogleEmail(isset($account['google_email']) ? (string) $account['google_email'] : null);

        return [
            'account_id' => (int) ($account['id'] ?? 0),
            'teacher_id' => $teacherId,
            'google_email' => $account['google_email'] ?? null,
            'google_person_resource_name' => $account['google_person_resource_name'] ?? null,
            'google_person_id' => $account['google_person_id'] ?? null,
            'access_token' => (string) ($account['access_token'] ?? ''),
            'refresh_token' => (string) ($account['refresh_token'] ?? ''),
            'token_expiry' => $account['token_expiry'] ?? null,
            'status' => (string) ($account['status'] ?? 'disconnected'),
        ];
    }

    /**
     * @return array{access_token:string,refresh_token:string,created?:mixed,expires_in?:mixed}
     */
    public function getActiveAccessTokenForTeacher(int $teacherId): array
    {
        $account = $this->getTeacherAccount($teacherId);
        if ($account['access_token'] !== '' && !$this->isExpired($account['token_expiry'])) {
            return [
                'access_token' => $account['access_token'],
                'refresh_token' => $account['refresh_token'],
            ];
        }

        return $this->refreshAccessTokenForTeacher($teacherId, $account);
    }

    /**
     * @param array{account_id:int,teacher_id:int,google_email:?string,google_person_resource_name:?string,google_person_id:?string,access_token:string,refresh_token:string,token_expiry:?string,status:string}|null $account
     * @return array{access_token:string,refresh_token:string,created?:mixed,expires_in?:mixed}
     */
    public function refreshAccessTokenForTeacher(int $teacherId, ?array $account = null): array
    {
        $account = $account ?? $this->getTeacherAccount($teacherId);
        $refreshToken = (string) ($account['refresh_token'] ?? '');
        if ($refreshToken === '') {
            throw new RuntimeException('Teacher Google refresh token is missing. Reconnect the Google account.');
        }

        $client = $this->client();
        $token = $client->fetchAccessTokenWithRefreshToken($refreshToken);
        if (!is_array($token) || isset($token['error']) || empty($token['access_token'])) {
            $err = is_array($token) ? ($token['error_description'] ?? $token['error'] ?? 'Token refresh failed') : 'Token refresh failed';
            throw new RuntimeException((string) $err);
        }

        $token['refresh_token'] = (string) ($token['refresh_token'] ?? $refreshToken);
        TeacherGoogleAccount::upsertConnection(
            $teacherId,
            $account['google_email'] ?? null,
            (string) $token['access_token'],
            (string) $token['refresh_token'],
            $this->tokenExpiryFromPayload($token),
            'active'
        );

        return $token;
    }

    /**
     * @return array{id:int,google_email:?string,access_token:string,refresh_token:string,token_expiry:?string,status:string,connected_at:?string}
     */
    public function getAdminAccount(): array
    {
        $account = AdminGoogleAccount::getCredentials();
        if ($account === null) {
            throw new RuntimeException('Admin has not connected a Google Workspace account.');
        }
        if (($account['status'] ?? '') !== 'active') {
            throw new RuntimeException('Admin Google Workspace account is not active. Please reconnect it in settings.');
        }
        if (($account['refresh_token'] ?? '') === '') {
            throw new RuntimeException('Admin Google refresh token is missing. Reconnect the Google account.');
        }

        return $account;
    }

    /**
     * @return array{access_token:string,refresh_token:string,created?:mixed,expires_in?:mixed}
     */
    public function getActiveAccessTokenForAdmin(): array
    {
        $account = $this->getAdminAccount();
        if ($account['access_token'] !== '' && !$this->isExpired($account['token_expiry'])) {
            return [
                'access_token' => $account['access_token'],
                'refresh_token' => $account['refresh_token'],
            ];
        }

        return $this->refreshAccessTokenForAdmin($account);
    }

    /**
     * @param array{id:int,google_email:?string,access_token:string,refresh_token:string,token_expiry:?string,status:string,connected_at:?string}|null $account
     * @return array{access_token:string,refresh_token:string,created?:mixed,expires_in?:mixed}
     */
    public function refreshAccessTokenForAdmin(?array $account = null): array
    {
        $account = $account ?? $this->getAdminAccount();
        $refreshToken = (string) ($account['refresh_token'] ?? '');
        if ($refreshToken === '') {
            throw new RuntimeException('Admin Google refresh token is missing. Reconnect the Google account.');
        }

        $client = $this->client();
        $token = $client->fetchAccessTokenWithRefreshToken($refreshToken);
        if (!is_array($token) || isset($token['error']) || empty($token['access_token'])) {
            $err = is_array($token) ? ($token['error_description'] ?? $token['error'] ?? 'Token refresh failed') : 'Token refresh failed';
            throw new RuntimeException((string) $err);
        }

        $token['refresh_token'] = (string) ($token['refresh_token'] ?? $refreshToken);
        AdminGoogleAccount::upsertConnection(
            $account['google_email'] ?? null,
            (string) $token['access_token'],
            (string) $token['refresh_token'],
            $this->tokenExpiryFromPayload($token),
            'active'
        );

        return $token;
    }

    public function client(): GoogleClient
    {
        if (!class_exists(GoogleClient::class)) {
            throw new RuntimeException('google/apiclient is not installed. Run composer install/update.');
        }

        $clientId = $this->googleClientId();
        $clientSecret = $this->googleClientSecret();
        $redirectUri = $this->configuredRedirectUri();
        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException('Missing Google client credentials.');
        }

        $client = new GoogleClient();
        $client->setClientId($clientId);
        $client->setClientSecret($clientSecret);
        $client->setRedirectUri($redirectUri);
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setIncludeGrantedScopes(false);
        $client->setScopes(self::requiredScopes());
        $client->setHttpClient($this->buildHttpClient());

        return $client;
    }

    public function configuredRedirectUri(): string
    {
        if (function_exists('googleOAuthRedirectUri')) {
            return googleOAuthRedirectUri();
        }

        return appUrl('/auth/google/callback');
    }

    private function buildHttpClient(): GuzzleClient
    {
        $verify = $this->resolveCaBundlePath();
        if ($verify === null) {
            return new GuzzleClient();
        }

        return new GuzzleClient([
            'verify' => $verify,
        ]);
    }

    private function resolveCaBundlePath(): ?string
    {
        $configured = trim((string) env('GOOGLE_CAINFO', env('SSL_CAINFO', '')));
        $candidates = [];
        if ($configured !== '') {
            $candidates[] = $configured;
        }

        $root = dirname(__DIR__, 2);
        $candidates[] = $root . DIRECTORY_SEPARATOR . 'certs' . DIRECTORY_SEPARATOR . 'cacert.pem';

        foreach ($candidates as $candidate) {
            $path = $candidate;
            if (!preg_match('/^[A-Za-z]:\\\\/', $path) && !str_starts_with($path, DIRECTORY_SEPARATOR)) {
                $path = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
            }
            if (is_file($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    private function tokenExpiryFromPayload(array $token): ?DateTimeImmutable
    {
        $created = isset($token['created']) ? (int) $token['created'] : time();
        $expiresIn = isset($token['expires_in']) ? (int) $token['expires_in'] : 0;
        if ($expiresIn <= 0) {
            return null;
        }

        return (new DateTimeImmutable('@' . $created))
            ->setTimezone(new DateTimeZone(APP_TIMEZONE))
            ->modify('+' . $expiresIn . ' seconds');
    }

    private function isExpired(?string $expiry): bool
    {
        if ($expiry === null || trim($expiry) === '') {
            return true;
        }

        try {
            $expiresAt = new DateTimeImmutable($expiry, new DateTimeZone(APP_TIMEZONE));
        } catch (\Throwable $e) {
            return true;
        }

        return $expiresAt <= (new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE)))->modify('+60 seconds');
    }

    /**
     * Personal Gmail (@gmail.com / @googlemail.com) may host Meet sessions; Workspace-style accounts unlock recording/Drive sync.
     */
    private function validateTeacherGoogleEmail(?string $email): void
    {
        $email = strtolower(trim((string) $email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Google could not confirm this account email. Reconnect Google and ensure email consent is granted.');
        }

        if ($this->shouldEnforceWorkspaceDomain()) {
            $domain = strtolower(substr(strrchr($email, '@') ?: '', 1));
            $workspaceDomain = $this->workspaceDomain();
            if ($workspaceDomain !== '' && $domain !== '' && $domain !== $workspaceDomain) {
                throw new RuntimeException('This LearnWise tenant requires Google login with the Workspace domain "' . $workspaceDomain . '".');
            }
        }
    }

    private function shouldEnforceWorkspaceDomain(): bool
    {
        return filter_var(trim((string) env('GOOGLE_REQUIRE_WORKSPACE_DOMAIN', '0')), FILTER_VALIDATE_BOOLEAN);
    }
}
