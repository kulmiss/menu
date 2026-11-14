<?php

namespace App\Support\Backups;

use RuntimeException;

/**
 * Backup encryption at rest (SECURITY_REPORT.md H4): libsodium secretstream
 * (XChaCha20-Poly1305), built into PHP, streamed in 1 MiB chunks so memory stays
 * flat for any size. The key is BACKUP_KEY in .env, never inside the backup.
 *
 * File: "MENUBK1\n" (8 bytes) + stream header (24) + chunks, each a
 * 4-byte big-endian length + ciphertext; the last chunk carries TAG_FINAL, so a
 * cut-off or altered file is detected.
 */
final class BackupCrypto
{
    public const MAGIC = "MENUBK1\n";

    public const SUFFIX = '.enc';

    private const CHUNK = 1048576;

    public static function key(): ?string
    {
        $raw = (string) config('backups.key');
        if ($raw === '') {
            return null;
        }
        $key = base64_decode(str_starts_with($raw, 'base64:') ? substr($raw, 7) : $raw, true);

        return is_string($key) && strlen($key) === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES ? $key : null;
    }

    public static function enabled(): bool
    {
        return self::key() !== null;
    }

    /** A configured but unusable key (wrong length, not base64) is an error, not "off". */
    public static function misconfigured(): bool
    {
        return (string) config('backups.key') !== '' && self::key() === null;
    }

    public static function newKey(): string
    {
        return 'base64:'.base64_encode(sodium_crypto_secretstream_xchacha20poly1305_keygen());
    }

    public static function encryptFile(string $in, string $out, string $key): void
    {
        $src = fopen($in, 'rb');
        $dst = fopen($out, 'wb');
        if ($src === false || $dst === false) {
            throw new RuntimeException('Cannot open backup files for encryption');
        }
        try {
            [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
            fwrite($dst, self::MAGIC.$header);
            // Read one chunk ahead, so the last chunk is known without trusting filesize().
            $chunk = (string) fread($src, self::CHUNK);
            do {
                $next = feof($src) ? '' : (string) fread($src, self::CHUNK);
                $last = $next === '';
                $tag = $last ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;
                $cipher = sodium_crypto_secretstream_xchacha20poly1305_push($state, $chunk, '', $tag);
                if (fwrite($dst, pack('N', strlen($cipher)).$cipher) === false) {
                    throw new RuntimeException('Writing the encrypted backup failed (disk full?)');
                }
                $chunk = $next;
            } while (! $last);
        } finally {
            fclose($src);
            fclose($dst);
        }
        @chmod($out, 0600);
    }

    public static function decryptFile(string $in, string $out, string $key): void
    {
        $src = fopen($in, 'rb');
        if ($src === false || fread($src, strlen(self::MAGIC)) !== self::MAGIC) {
            throw new RuntimeException('Not an encrypted backup file');
        }
        $header = (string) fread($src, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
        $dst = fopen($out, 'wb');
        if ($dst === false) {
            throw new RuntimeException('Cannot write '.$out);
        }
        try {
            $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $key);
            $final = false;
            while (! $final) {
                $len = fread($src, 4);
                if ($len === false || strlen($len) !== 4) {
                    throw new RuntimeException('The backup file is cut off');
                }
                $n = (int) (unpack('N', $len)[1] ?? 0);
                $result = sodium_crypto_secretstream_xchacha20poly1305_pull($state, (string) fread($src, $n));
                if ($result === false) {
                    throw new RuntimeException('Wrong BACKUP_KEY, or the file was changed');
                }
                [$plain, $tag] = $result;
                fwrite($dst, $plain);
                $final = $tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL;
            }
            if (fread($src, 1) !== '') {
                throw new RuntimeException('Unexpected data after the end of the backup');
            }
        } catch (RuntimeException $e) {
            fclose($dst);
            @unlink($out);
            throw $e;
        } finally {
            fclose($src);
            if (is_resource($dst)) {
                fclose($dst);
            }
        }
    }
}
