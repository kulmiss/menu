<?php

namespace App\Support\Backups;

use App\Support\Security\OutboundHost;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Minimal S3-compatible client (AWS Signature V4, path-style URLs) for the
 * off-site copy: Backblaze B2, Cloudflare R2, AWS S3, Wasabi… No extra
 * package needed. Only PUT and DELETE of single objects.
 */
class S3
{
    public function __construct(
        private readonly string $endpoint, // https://s3.eu-central-003.backblazeb2.com
        private readonly string $region,   // eu-central-003, auto (R2), us-east-1…
        private readonly string $bucket,
        private readonly string $key,
        private readonly string $secret,
    ) {}

    public function put(string $file, string $object): void
    {
        $this->guard();
        $hash = (string) hash_file('sha256', $file);
        $response = Http::timeout(600)->withHeaders($this->sign('PUT', $object, $hash, ['content-type' => 'application/zip', 'content-length' => (string) filesize($file)]))
            ->withBody(Utils::streamFor(fopen($file, 'rb')), 'application/zip')
            ->put($this->url($object));
        if (! $response->successful()) {
            throw new RuntimeException('Off-site upload failed: HTTP '.$response->status().' '.mb_substr(strip_tags($response->body()), 0, 200));
        }
    }

    public function delete(string $object): void
    {
        $this->guard();
        Http::timeout(60)->withHeaders($this->sign('DELETE', $object, hash('sha256', '')))->delete($this->url($object));
    }

    /** Right before connecting: https and a public address (SECURITY_REPORT.md M7). */
    private function guard(): void
    {
        $host = (string) parse_url($this->endpoint, PHP_URL_HOST);
        if (! str_starts_with(strtolower($this->endpoint), 'https://') || OutboundHost::problem($host) !== null) {
            throw new RuntimeException('Off-site storage refused: the endpoint must be https on a public address.');
        }
    }

    private function url(string $object): string
    {
        return rtrim($this->endpoint, '/').'/'.rawurlencode($this->bucket).'/'.implode('/', array_map('rawurlencode', explode('/', $object)));
    }

    /**
     * @param  array<string, string>  $extra
     * @return array<string, string>
     */
    public function sign(string $method, string $object, string $payloadHash, array $extra = [], ?int $time = null): array
    {
        $time ??= time();
        $date = gmdate('Ymd', $time);
        $stamp = gmdate('Ymd\THis\Z', $time);
        $host = (string) parse_url($this->endpoint, PHP_URL_HOST);
        $port = parse_url($this->endpoint, PHP_URL_PORT);
        $headers = array_change_key_case([...$extra, 'host' => $host.($port ? ':'.$port : ''), 'x-amz-content-sha256' => $payloadHash, 'x-amz-date' => $stamp]);
        ksort($headers);
        $path = (string) parse_url($this->url($object), PHP_URL_PATH);
        $signed = implode(';', array_keys($headers));
        $canonical = implode("\n", [
            $method, $path, '',
            implode('', array_map(fn ($k, $v) => $k.':'.trim($v)."\n", array_keys($headers), $headers)),
            $signed,
            $payloadHash,
        ]);
        $scope = "{$date}/{$this->region}/s3/aws4_request";
        $toSign = "AWS4-HMAC-SHA256\n{$stamp}\n{$scope}\n".hash('sha256', $canonical);
        $k = hash_hmac('sha256', 'aws4_request', hash_hmac('sha256', 's3', hash_hmac('sha256', $this->region, hash_hmac('sha256', $date, 'AWS4'.$this->secret, true), true), true), true);
        $signature = hash_hmac('sha256', $toSign, $k);
        unset($headers['host']); // the HTTP client sets it

        return [...$headers, 'Authorization' => "AWS4-HMAC-SHA256 Credential={$this->key}/{$scope}, SignedHeaders={$signed}, Signature={$signature}"];
    }
}
