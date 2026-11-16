<?php

namespace App\Services\Cdn;

use App\Models\UploadConfig;
use GuzzleHttp\Promise\Each;
use GuzzleHttp\Utils;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Promises\LazyPromise;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class TikTokUploader implements CdnUploader
{
    public function upload(string $filePath, string $filename, UploadConfig $account): string
    {
        [$cookie, $csrf] = $this->parseCookie((string) $account->data('cookie'));

        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            throw new UploadFailedException("Không đọc được file: {$filePath}");
        }

        try {
            $response = Http::connectTimeout(25)
                ->timeout(config('cdnpro.tiktok.timeout'))
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Origin' => 'https://ads.tiktok.com',
                    'Referer' => $account->data('referer') ?: config('cdnpro.tiktok.referer'),
                    'x-csrftoken' => $csrf,
                    'Cookie' => $cookie,
                ])
                ->attach('file', $handle, $filename, ['Content-Type' => 'image/png'])
                ->post(config('cdnpro.tiktok.endpoint'), ['name' => $filename]);
        } catch (ConnectionException $e) {
            throw new UploadFailedException('Lỗi kết nối TikTok: '.$e->getMessage());
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        return $this->parseResponse($response);
    }

    /**
     * Upload nhiều file với tối đa $concurrency request cùng lúc theo kiểu "cửa sổ trượt": request nào xong là
     * request kế tiếp được lấy từ $items ngay (không đợi cả lô), nên một ảnh chậm không kìm các ảnh còn lại.
     * $items được duyệt từng phần tử khi có chỗ trống, nên người gọi có thể tạo ảnh PNG "đúng lúc cần".
     * Mỗi kết quả được báo ngay qua $onResult(key, url | UploadFailedException).
     *
     * @param  iterable<int, array{key: int|string, path: string, filename: string, account: UploadConfig}>  $items
     * @param  callable(int|string, string|UploadFailedException): void  $onResult
     */
    public function uploadStream(iterable $items, int $concurrency, callable $onResult): void
    {
        $handles = [];

        // Dùng CHUNG một handler cho cả luồng: mỗi Http::async() riêng lẻ có handler curl_multi riêng nên các
        // request sẽ bị chạy lần lượt thay vì song song (giống cách Http::pool xử lý).
        $handler = Utils::chooseHandler();

        $requests = (function () use ($items, &$handles, $handler) {
            foreach ($items as $item) {
                [$cookie, $csrf] = $this->parseCookie((string) $item['account']->data('cookie'));
                $handles[$item['key']] = fopen($item['path'], 'rb');

                $request = Http::setHandler($handler)->async()
                    ->connectTimeout(25)
                    ->timeout(config('cdnpro.tiktok.timeout'))
                    ->withHeaders([
                        'Accept' => 'application/json',
                        'Origin' => 'https://ads.tiktok.com',
                        'Referer' => $item['account']->data('referer') ?: config('cdnpro.tiktok.referer'),
                        'x-csrftoken' => $csrf,
                        'Cookie' => $cookie,
                    ])
                    ->attach('file', $handles[$item['key']], $item['filename'], ['Content-Type' => 'image/png'])
                    ->post(config('cdnpro.tiktok.endpoint'), ['name' => $item['filename']]);

                yield $item['key'] => $request instanceof LazyPromise ? $request->buildPromise() : $request;
            }
        })();

        $settle = function (mixed $outcome, int|string $key) use (&$handles, $onResult): void {
            if (isset($handles[$key])) {
                if (is_resource($handles[$key])) {
                    fclose($handles[$key]);
                }
                unset($handles[$key]);
            }

            if (! $outcome instanceof Response) {
                $detail = $outcome instanceof Throwable ? $outcome->getMessage() : 'không có phản hồi';
                $onResult($key, new UploadFailedException("Lỗi kết nối TikTok: {$detail}"));

                return;
            }

            try {
                $onResult($key, $this->parseResponse($outcome));
            } catch (UploadFailedException $e) {
                $onResult($key, $e);
            }
        };

        try {
            Each::ofLimit($requests, max(1, $concurrency), $settle, $settle)->wait();
        } finally {
            foreach ($handles as $handle) {
                if (is_resource($handle)) {
                    fclose($handle);
                }
            }
        }
    }

    /**
     * @throws UploadFailedException
     */
    public function parseResponse(Response $response): string
    {
        $data = $response->json();

        if ($response->successful() && is_array($data) && ($data['code'] ?? -1) === 0 && ! empty($data['data'])) {
            $url = $this->urlFromData($data['data']);
            if ($url !== null) {
                return $url;
            }
        }

        $message = (is_array($data) ? ($data['msg'] ?? $data['message'] ?? null) : null) ?? 'HTTP '.$response->status();
        $dead = in_array($response->status(), [401, 403], true)
            || (bool) preg_match('/login|auth|csrf|session|permission|forbidden/i', (string) $message);

        throw new UploadFailedException("TikTok từ chối upload: {$message}", $dead);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function urlFromData(array $data): ?string
    {
        if (! empty($data['src'])) {
            $last = basename((string) $data['src']);
            $id = explode('~', $last)[0];

            return "https://p16-lp-sg.ibyteimg.com/origin/tos-alisg-i-375lmtcpo0-sg/{$id}";
        }

        if (! empty($data['uri'])) {
            return 'https://p16-tiktokcdn-com.akamaized.net/origin/ad-site-i18n/'.str_replace('ad-site-i18n-sg/', '', (string) $data['uri']);
        }

        if (! empty($data['original_img_uri'])) {
            return 'https://p16-tiktok-ads-sg.tiktokcdn.com/obj/'.$data['original_img_uri'];
        }

        return null;
    }

    /**
     * Nhận cookie dạng JSON ([{name,value}] hoặc {cookies:[...]}) hoặc chuỗi "a=b; c=d".
     *
     * @return array{0: string, 1: string} [cookie header, csrf token]
     */
    public function parseCookie(string $raw): array
    {
        $raw = trim($raw);
        $json = ($raw !== '' && in_array($raw[0], ['[', '{'], true)) ? json_decode($raw, true) : null;

        if (is_array($json)) {
            $pairs = [];
            foreach ($json['cookies'] ?? $json as $key => $item) {
                if (is_array($item) && isset($item['name'], $item['value'])) {
                    $pairs[] = $item['name'].'='.$item['value'];
                } elseif (is_string($key) && is_string($item)) {
                    $pairs[] = $key.'='.$item;
                }
            }
            $raw = implode('; ', $pairs);
        }

        $csrf = preg_match('/csrftoken=([^;]+)/', $raw, $m) ? trim($m[1]) : '';

        return [$raw, $csrf];
    }
}
