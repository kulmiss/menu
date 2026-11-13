<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Jobs\RunBackup;
use App\Models\Backup;
use App\Support\ActivityLogger;
use App\Support\Backups\Backups;
use App\Support\PlatformSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Super Admin → Backups (owner, 2026-11-13). Downloads need the password or
 * a two-factor code again, then a signed link valid for 5 minutes.
 */
class BackupController extends Controller
{
    public function __construct(private readonly ActivityLogger $activity, private readonly Backups $backups) {}

    public function index(): JsonResponse
    {
        $root = storage_path();

        return response()->json(['data' => [
            'backups' => Backup::latest('id')->limit(100)->get()->map(fn (Backup $b) => self::row($b)),
            'disk_free' => @disk_free_space($root) ?: null,
            'disk_total' => @disk_total_space($root) ?: null,
            'problem' => PlatformSettings::get('backup_enabled') ? Backups::problem() : null,
            'settings' => array_intersect_key(PlatformSettings::forScreen(), array_flip([
                'backup_enabled', 'backup_keep_daily', 'backup_keep_weekly', 'backup_keep_monthly',
                'backup_s3_enabled', 'backup_s3_endpoint', 'backup_s3_region', 'backup_s3_bucket', 'backup_s3_prefix', 'backup_s3_key', 'backup_s3_secret_set',
            ])),
        ]]);
    }

    /** "Back up now": queued, so the page answers at once. */
    public function store(Request $request): JsonResponse
    {
        abort_if(Backup::whereIn('status', ['queued', 'running'])->where('created_at', '>', now()->subHours(2))->exists(), 409, __('platform.backup_running'));
        $backup = Backup::create(['kind' => 'manual', 'status' => 'queued', 'created_by' => $request->user()->id]);
        RunBackup::dispatch($backup->id);
        $this->activity->log('platform.backup_started', $backup, [], null);

        return response()->json(['data' => self::row($backup)], 202);
    }

    /** Re-confirm with the password or a two-factor code, then get a 5-minute download link. */
    public function link(Request $request, Backup $backup, TwoFactorAuthenticationProvider $twoFactor): JsonResponse
    {
        $data = $request->validate(['password' => ['nullable', 'string', 'required_without:code'], 'code' => ['nullable', 'string', 'required_without:password']]);
        $user = $request->user();
        try {
            $ok = isset($data['password'])
                ? Hash::check($data['password'], $user->password)
                : ($user->two_factor_secret && $twoFactor->verify(Crypt::decrypt($user->two_factor_secret), (string) preg_replace('/\s+/', '', (string) $data['code'])));
        } catch (Throwable) {
            $ok = false;
        }
        if (! $ok) {
            throw ValidationException::withMessages([isset($data['password']) ? 'password' : 'code' => __('backups.wrong_password')]);
        }
        abort_unless($backup->status === 'ok' && $backup->path() && is_file((string) $backup->path()), 404, __('backups.not_ready'));
        $this->activity->log('platform.backup_downloaded', $backup, [], null);

        return response()->json(['data' => ['url' => URL::temporarySignedRoute('platform.backups.file', now()->addMinutes(5), ['backup' => $backup->id])]]);
    }

    public function file(Backup $backup): BinaryFileResponse
    {
        abort_unless($backup->status === 'ok' && $backup->path() && is_file((string) $backup->path()), 404);

        return response()->download((string) $backup->path(), (string) $backup->filename, ['Content-Type' => 'application/zip', 'Cache-Control' => 'private, no-store']);
    }

    public function destroy(Backup $backup): JsonResponse
    {
        abort_if(in_array($backup->status, ['queued', 'running'], true), 409, __('platform.backup_running'));
        $this->backups->delete($backup);
        $this->activity->log('platform.backup_deleted', null, ['file' => ['from' => $backup->filename]], null);

        return response()->json(null, 204);
    }

    public function settings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'backup_enabled' => ['sometimes', 'boolean'],
            'backup_keep_daily' => ['sometimes', 'integer', 'between:1,60'],
            'backup_keep_weekly' => ['sometimes', 'integer', 'between:0,52'],
            'backup_keep_monthly' => ['sometimes', 'integer', 'between:0,36'],
            'backup_s3_enabled' => ['sometimes', 'boolean'],
            'backup_s3_endpoint' => ['sometimes', 'nullable', 'url:https,http', 'max:200'],
            'backup_s3_region' => ['sometimes', 'nullable', 'string', 'max:40'],
            'backup_s3_bucket' => ['sometimes', 'nullable', 'string', 'max:100'],
            'backup_s3_prefix' => ['sometimes', 'nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._\/-]*$/'],
            'backup_s3_key' => ['sometimes', 'nullable', 'string', 'max:200'],
            'backup_s3_secret' => ['sometimes', 'nullable', 'string', 'max:300'],
        ]);
        foreach (['backup_s3_endpoint', 'backup_s3_region', 'backup_s3_bucket', 'backup_s3_prefix', 'backup_s3_key'] as $k) {
            if (array_key_exists($k, $data)) {
                $data[$k] = trim((string) $data[$k]);
            }
        }
        // Empty keeps the stored secret; it is stored encrypted and never sent back.
        if (($data['backup_s3_secret'] ?? '') === '') {
            unset($data['backup_s3_secret']);
        } else {
            $data['backup_s3_secret'] = Crypt::encryptString($data['backup_s3_secret']);
        }
        if (($data['backup_s3_enabled'] ?? false)) {
            $merged = [...PlatformSettings::all(), ...$data];
            if ($merged['backup_s3_endpoint'] === '' || $merged['backup_s3_bucket'] === '' || $merged['backup_s3_key'] === '' || $merged['backup_s3_secret'] === '') {
                throw ValidationException::withMessages(['backup_s3_enabled' => __('platform.backup_s3_incomplete')]);
            }
        }
        PlatformSettings::save($data);
        $this->activity->log('platform.settings_saved', null, ['keys' => ['to' => array_keys($data)]], null);

        return $this->index();
    }

    /** @return array<string, mixed> */
    public static function row(Backup $b): array
    {
        return [
            'id' => $b->id, 'filename' => $b->filename, 'size' => $b->size, 'kind' => $b->kind, 'status' => $b->status,
            'offsite' => $b->offsite, 'error' => $b->error,
            'created_at' => $b->created_at->toIso8601String(), 'finished_at' => $b->finished_at?->toIso8601String(),
        ];
    }
}
