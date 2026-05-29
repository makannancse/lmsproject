<?php

declare(strict_types=1);

use Google\Client as GoogleClient;
use Google\Service\Calendar as GoogleCalendar;
use Google\Service\Meet as GoogleMeet;
use Google\Service\PeopleService as GooglePeopleService;
use GuzzleHttp\Client as GuzzleClient;

require_once dirname(__DIR__) . '/models/SystemConfig.php';
require_once dirname(__DIR__) . '/models/TeacherGoogleAccount.php';
require_once dirname(__DIR__) . '/lib/GoogleAccountType.php';
require_once dirname(__DIR__) . '/lib/SyncLog.php';

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

    public function buildAuthUrl(int $teacherId): string
    {
        $client = $this->client();
        $state = base64_encode(json_encode([
            'teacher_id' => $teacherId,
            'nonce' => bin2hex(random_bytes(8)),
        ], JSON_UNESCAPED_SLASHES));
        $_SESSION['google_oauth_state'] = $state;

        $client->setState($state);
        return $client->createAuthUrl();
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
            if (is_array($payload ?? null) && !empty($payload['sub'])) {
                $googlePersonId = (string) $payload['sub'];
                $googlePersonResourceName = 'people/' . $googlePersonId;
            }
        }

        $existing = TeacherGoogleAccount::getCredentialsForTeacher($teacherId);
        if (($email === null || trim($email) === '') && $existing !== null && !empty($existing['google_email'])) {
            $email = (string) $existing['google_email'];
        }
        $refresh = (string) ($token['refresh_token'] ?? ($existing['refresh_token'] ?? ''));
        $saved = $refresh !== '';
        $this->validateTeacherGoogleEmail($email);

        TeacherGoogleAccount::upsertConnection(
            $teacherId,
            $email,
            (string) ($token['access_token'] ?? ''),
            $refresh !== '' ? $refresh : null,
            $this->tokenExpiryFromPayload($token),
            'active',
            $googlePersonResourceName,
            $googlePersonId
        );
        $profile = GoogleAccountType::profileFromEmail($email);
        SyncLog::write('google_account_type.log', [
            'message' => 'Teacher Google OAuth connected',
            'teacher_id' => $teacherId,
            'google_email' => $email,
            'google_person_resource_name' => $googlePersonResourceName,
            'google_person_id' => $googlePersonId,
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
            'account_type' => $profile['account_type'],
            'recording_supported' => $profile['recording_supported'],
            'workspace_domain' => $this->workspaceDomain(),
        ]);

        return ['teacher_id' => $teacherId, 'email' => $email, 'refresh_token_saved' => $saved];
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
        $client->setIncludeGrantedScopes(true);
        $client->setScopes([
            GoogleCalendar::CALENDAR,
            GoogleMeet::MEETINGS_SPACE_READONLY,
            'https://www.googleapis.com/auth/drive.readonly',
            GooglePeopleService::USERINFO_EMAIL,
            'openid',
            'email',
            'profile',
        ]);
        $client->setHttpClient($this->buildHttpClient());

        return $client;
    }

    public function configuredRedirectUri(): string
    {
        $configured = trim((string) env('GOOGLE_REDIRECT_URI', ''));
        if ($configured === '') {
            return $this->defaultRedirectUri();
        }

        $path = (string) parse_url($configured, PHP_URL_PATH);
        if ($path === '' || str_ends_with(rtrim($path, '/'), '/auth/google/callback')) {
            return $configured;
        }

        return $this->defaultRedirectUri();
    }

    private function defaultRedirectUri(): string
    {
        $base = defined('BASE_PATH') ? BASE_PATH : '';
        $appUrl = rtrim((string) env('APP_URL', 'http://localhost'), '/');
        if ($base !== '' && str_ends_with($appUrl, $base)) {
            return $appUrl . '/auth/google/callback';
        }

        return $appUrl . $base . '/auth/google/callback';
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
