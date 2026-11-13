<?php

namespace App\Support\Push;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Firebase Cloud Messaging HTTP v1 (QUESTIONS #189) without an SDK: a
 * service-account JWT signed with openssl, exchanged for an access token
 * (cached 50 minutes), then one POST per phone.
 */
class FcmClient
{
    public const OK = 'ok';

    public const GONE = 'gone';

    public const FAILED = 'failed';

    /** @var array{project_id: string, client_email: string, private_key: string}|null */
    private ?array $account = null;

    public function configured(): bool
    {
        return $this->account() !== null;
    }

    /** @param  array<string, string>  $data */
    public function send(string $token, string $title, string $body, array $data): string
    {
        $account = $this->account() ?? throw new RuntimeException('FCM is not configured.');
        $response = Http::withToken($this->accessToken($account))->timeout(10)
            ->post("https://fcm.googleapis.com/v1/projects/{$account['project_id']}/messages:send", ['message' => [
                'token' => $token,
                'notification' => ['title' => $title, 'body' => $body],
                'data' => $data,
                'android' => ['priority' => 'high', 'notification' => ['channel_id' => 'default']],
                'apns' => ['payload' => ['aps' => ['sound' => 'default']]],
            ]]);

        if ($response->successful()) {
            return self::OK;
        }
        $status = (string) $response->json('error.status');
        // The app was removed or the token replaced: forget it.
        if ($response->status() === 404 || $status === 'UNREGISTERED' || ($status === 'INVALID_ARGUMENT' && str_contains((string) $response->json('error.message'), 'token'))) {
            return self::GONE;
        }
        if ($response->status() === 401) {
            Cache::forget('fcm:access-token');
        }

        return self::FAILED;
    }

    /** @param  array{project_id: string, client_email: string, private_key: string}  $account */
    private function accessToken(array $account): string
    {
        return Cache::remember('fcm:access-token', now()->addMinutes(50), function () use ($account) {
            $now = time();
            $jwt = $this->jwt([
                'iss' => $account['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ], $account['private_key']);

            $token = Http::asForm()->timeout(10)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ])->throw()->json('access_token');

            return is_string($token) ? $token : throw new RuntimeException('No FCM access token.');
        });
    }

    /** @param  array<string, mixed>  $claims */
    private function jwt(array $claims, string $privateKey): string
    {
        $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $input = $b64((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT'])).'.'.$b64((string) json_encode($claims));
        if (! openssl_sign($input, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Could not sign the FCM token request.');
        }

        return $input.'.'.$b64($signature);
    }

    /** @return array{project_id: string, client_email: string, private_key: string}|null */
    private function account(): ?array
    {
        if ($this->account !== null) {
            return $this->account;
        }
        $path = (string) config('services.fcm.credentials');
        if ($path === '') {
            return null;
        }
        $path = str_starts_with($path, '/') ? $path : base_path($path);
        if (! is_readable($path)) {
            return null;
        }
        $json = json_decode((string) file_get_contents($path), true);
        if (! is_array($json) || ! isset($json['project_id'], $json['client_email'], $json['private_key'])) {
            return null;
        }

        return $this->account = ['project_id' => (string) $json['project_id'], 'client_email' => (string) $json['client_email'], 'private_key' => (string) $json['private_key']];
    }
}
