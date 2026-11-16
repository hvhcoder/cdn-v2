<?php

namespace App\Services\Storage;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Cloudflare R2 (S3 compatible) qua presigned URL SigV4, khong can SDK.
 */
class R2Service
{
    /**
     * @param  array<string, mixed>|null  $config
     */
    public function __construct(private ?array $config = null)
    {
        $this->config ??= config('cdnpro.r2');
    }

    public function newObjectKey(string $filename): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION)) ?: 'mp4';

        return 'raw/'.date('Y/m').'/'.Str::uuid().'.'.$extension;
    }

    public function presignUpload(string $key, ?int $minutes = null): string
    {
        return $this->presign('PUT', $key, $minutes);
    }

    public function presign(string $method, string $key, ?int $minutes = null): string
    {
        $host = $this->host();
        $path = '/'.$this->required('bucket').'/'.$this->encodePath($key);
        $query = $this->signedQuery($method, $host, $path, ($minutes ?? $this->config['presign_minutes']) * 60);

        return "{$this->scheme()}://{$host}{$path}?{$query}";
    }

    /**
     * Tai object ve dia theo luong (khong nap vao RAM).
     */
    public function download(string $key, string $localPath): void
    {
        $dir = dirname($localPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $response = Http::timeout(3600)->withOptions(['sink' => $localPath])->get($this->presign('GET', $key));

        if (! $response->successful()) {
            throw new RuntimeException("Tải file từ R2 thất bại (HTTP {$response->status()}).");
        }
    }

    public function size(string $key): ?int
    {
        $response = Http::timeout(30)->send('HEAD', $this->presign('HEAD', $key, 5));

        return $response->successful() ? (int) $response->header('Content-Length') : null;
    }

    /**
     * Kiểm tra endpoint/bucket/khóa: HEAD một object không tồn tại. 404 = xác thực OK, 403 = sai khóa.
     *
     * @return array{ok: bool, message: string}
     */
    public function testConnection(): array
    {
        try {
            $status = Http::timeout(15)->send('HEAD', $this->presign('HEAD', '.cdnpro-healthcheck', 2))->status();
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Không kết nối được: '.$e->getMessage()];
        }

        return match (true) {
            in_array($status, [200, 404], true) => ['ok' => true, 'message' => 'Kết nối thành công.'],
            in_array($status, [401, 403], true) => ['ok' => false, 'message' => 'Sai Access Key / Secret Key hoặc không có quyền với bucket.'],
            default => ['ok' => false, 'message' => "R2 trả về HTTP {$status}, kiểm tra lại endpoint và bucket."],
        };
    }

    public function delete(string $key): void
    {
        $response = Http::timeout(30)->send('DELETE', $this->presign('DELETE', $key, 5));

        if (! $response->successful() && $response->status() !== 404) {
            throw new RuntimeException("Xóa file trên R2 thất bại (HTTP {$response->status()}).");
        }
    }

    /**
     * Ky mot request theo SigV4 query-string (payload UNSIGNED). Tach rieng de test voi vector cua AWS.
     */
    public function signedQuery(string $method, string $host, string $canonicalPath, int $expiresSeconds, ?int $time = null): string
    {
        $time ??= time();
        $amzDate = gmdate('Ymd\THis\Z', $time);
        $dateStamp = gmdate('Ymd', $time);
        $region = $this->config['region'] ?: 'auto';
        $scope = "{$dateStamp}/{$region}/s3/aws4_request";

        $params = [
            'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential' => $this->required('access_key')."/{$scope}",
            'X-Amz-Date' => $amzDate,
            'X-Amz-Expires' => (string) $expiresSeconds,
            'X-Amz-SignedHeaders' => 'host',
        ];
        ksort($params);

        $canonicalQuery = implode('&', array_map(
            fn ($k, $v) => rawurlencode($k).'='.rawurlencode($v),
            array_keys($params),
            $params,
        ));

        $canonicalRequest = implode("\n", [$method, $canonicalPath, $canonicalQuery, "host:{$host}\n", 'host', 'UNSIGNED-PAYLOAD']);
        $stringToSign = implode("\n", ['AWS4-HMAC-SHA256', $amzDate, $scope, hash('sha256', $canonicalRequest)]);

        $key = hash_hmac('sha256', $dateStamp, 'AWS4'.$this->required('secret_key'), true);
        foreach ([$region, 's3', 'aws4_request'] as $part) {
            $key = hash_hmac('sha256', $part, $key, true);
        }

        return $canonicalQuery.'&X-Amz-Signature='.hash_hmac('sha256', $stringToSign, $key);
    }

    private function encodePath(string $key): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $key)));
    }

    private function host(): string
    {
        return parse_url($this->required('endpoint'), PHP_URL_HOST) ?: throw new RuntimeException('R2_ENDPOINT không hợp lệ.');
    }

    private function scheme(): string
    {
        return parse_url($this->required('endpoint'), PHP_URL_SCHEME) ?: 'https';
    }

    private function required(string $name): string
    {
        $value = trim((string) ($this->config[$name] ?? ''));

        return $value !== '' ? $value : throw new RuntimeException("Thiếu cấu hình R2: {$name}.");
    }
}
