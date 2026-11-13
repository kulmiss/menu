<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use App\Support\IpHash;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Every create/change request must carry an `Idempotency-Key` header.
 * A repeat of the same request (double tap, retry on a slow network) gets the
 * stored response instead of doing the work twice. See PLAN.md §6.3.
 */
class EnsureIdempotency
{
    public const HEADER = 'Idempotency-Key';

    private const MUTATING = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /** Largest response we keep for replay; bigger ones are replayed as 409. */
    private const MAX_STORED_BYTES = 1_000_000;

    /** Marker for answers with secrets (Cache-Control: no-store): the key is kept, the body is not. */
    private const NO_STORE = 'no-store';

    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->getMethod(), self::MUTATING, true)) {
            return $next($request);
        }

        $key = (string) $request->header(self::HEADER, '');

        if ($key === '') {
            return $this->error(428, 'idempotency.missing');
        }

        if (! preg_match('/^[A-Za-z0-9_-]{8,100}$/', $key)) {
            return $this->error(422, 'idempotency.invalid');
        }

        $scope = $this->scope($request);
        $hash = $this->hash($request);

        try {
            $record = IdempotencyKey::create([
                'scope' => $scope,
                'key' => $key,
                'method' => $request->getMethod(),
                'path' => substr($request->path(), 0, 255),
                'request_hash' => $hash,
                'status' => IdempotencyKey::PENDING,
                'expires_at' => now()->addHours((int) config('menu.idempotency_hours')),
            ]);
        } catch (UniqueConstraintViolationException) {
            return $this->replay($scope, $key, $hash);
        }

        try {
            $response = $next($request);
        } catch (Throwable $e) {
            $record->delete();
            throw $e;
        }

        $this->remember($record, $response);

        return $response;
    }

    private function replay(string $scope, string $key, string $hash): Response
    {
        $existing = IdempotencyKey::where('scope', $scope)->where('key', $key)->first();

        if ($existing === null) {
            // Removed between our insert attempt and now (failed request): ask to retry.
            return $this->error(409, 'idempotency.in_progress');
        }

        if (! hash_equals($existing->request_hash, $hash)) {
            return $this->error(422, 'idempotency.mismatch');
        }

        if ($existing->status === IdempotencyKey::PENDING || $existing->response_status === null) {
            return $this->error(409, 'idempotency.in_progress');
        }
        if ($existing->response_type === self::NO_STORE) {
            return $this->error(409, 'idempotency.not_replayable');
        }

        return response($existing->response_body ?? '', $existing->response_status, array_filter([
            'Content-Type' => $existing->response_type,
            'Idempotent-Replayed' => 'true',
        ]));
    }

    private function remember(IdempotencyKey $record, Response $response): void
    {
        $status = $response->getStatusCode();
        $body = (string) $response->getContent();

        // Server errors and oversized bodies are not stored: the client may retry them.
        if ($status >= 500 || strlen($body) > self::MAX_STORED_BYTES) {
            $record->delete();

            return;
        }

        // Answers carrying secrets (recovery codes, tokens) are never written to the database.
        if (str_contains((string) $response->headers->get('Cache-Control'), 'no-store')) {
            $record->update(['status' => IdempotencyKey::DONE, 'response_status' => $status, 'response_body' => null, 'response_type' => self::NO_STORE]);

            return;
        }

        $record->update([
            'status' => IdempotencyKey::DONE,
            'response_status' => $status,
            'response_body' => $body,
            'response_type' => $response->headers->get('Content-Type'),
        ]);
    }

    private function scope(Request $request): string
    {
        $userId = $request->user()?->getAuthIdentifier();

        return $userId !== null ? 'u:'.$userId : 'g:'.IpHash::make($request->ip());
    }

    private function hash(Request $request): string
    {
        $payload = $request->except(['password', 'password_confirmation', 'current_password']);
        ksort($payload);

        return hash('sha256', implode('|', [
            $request->getMethod(),
            $request->path(),
            json_encode($payload) ?: '',
            // Hash secrets separately so they still count without being readable.
            hash('sha256', (string) $request->input('password', '')),
        ]));
    }

    private function error(int $status, string $key): Response
    {
        return response()->json(['message' => __('menu.'.$key), 'code' => $key], $status);
    }
}
